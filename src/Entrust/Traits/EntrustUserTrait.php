<?php

declare(strict_types=1);

namespace Skywalker\Entrust\Traits;

use Illuminate\Cache\TaggableStore;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * Trait EntrustUserTrait
 *
 * @package Skywalker\Entrust\Traits
 */
trait EntrustUserTrait
{
    use Auditable;

    /**
     * Active team ID for scoping roles and permissions.
     */
    protected mixed $activeTeamId = null;

    /**
     * Set the active team for the current request.
     */
    public function withTeam(mixed $team): static
    {
        $this->activeTeamId = is_object($team) ? $team->getKey() : $team;
        return $this;
    }
    /**
     * Big block of caching functionality.
     */
    public function cachedRoles(): Collection
    {
        $userPrimaryKey = $this->primaryKey;
        $teamId = $this->activeTeamId;
        $cacheKey = 'entrust_roles_for_user_' . $this->$userPrimaryKey . '_team_' . ($teamId ?? 'global');

        if (Cache::getStore() instanceof TaggableStore) {
            $tags = [Config::get('entrust.role_user_table'), 'entrust_user_' . $this->$userPrimaryKey];
            return Cache::tags($tags)->remember($cacheKey, Config::get('cache.ttl'), function () use ($teamId) {
                $query = $this->roles();
                if ($teamId) {
                    $query->wherePivot('team_id', $teamId);
                }
                // Filter out expired roles
                $query->where(function ($q) {
                    $q->whereNull($this->getTable() . '.expires_at')
                        ->orWhere($this->getTable() . '.expires_at', '>', \Illuminate\Support\Carbon::now());
                });
                return $query->get();
            });
        }

        $query = $this->roles();
        if ($teamId) {
            $query->wherePivot('team_id', $teamId);
        }
        $query->where(function ($q) {
            $q->whereNull(Config::get('entrust.role_user_table') . '.expires_at')
                ->orWhere(Config::get('entrust.role_user_table') . '.expires_at', '>', Carbon::now());
        });
        return $query->get();
    }

    /**
     * {@inheritDoc}
     */
    public function save(array $options = []): bool
    {
        if (Cache::getStore() instanceof TaggableStore) {
            $userPrimaryKey = $this->primaryKey;
            Cache::tags('entrust_user_' . $this->$userPrimaryKey)->flush();
        }
        return parent::save($options);
    }

    /**
     * {@inheritDoc}
     */
    public function delete(array $options = []): ?bool
    {   //soft or hard
        $result = parent::delete($options);
        if (Cache::getStore() instanceof TaggableStore) {
            Cache::tags(Config::get('entrust.role_user_table'))->flush();
        }
        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function restore(): ?bool
    {   //soft delete undo's
        $result = parent::restore();
        if (Cache::getStore() instanceof TaggableStore) {
            Cache::tags(Config::get('entrust.role_user_table'))->flush();
        }
        return $result;
    }

    /**
     * Many-to-Many relations with Role.
     */
    public function roles(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(
            Config::get('entrust.role'),
            Config::get('entrust.role_user_table'),
            Config::get('entrust.user_foreign_key'),
            Config::get('entrust.role_foreign_key')
        )->withPivot('team_id', 'expires_at');
    }

    /**
     * Many-to-Many relations with the permission model (Direct Permissions).
     */
    public function permissions(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(
            Config::get('entrust.permission'),
            'permission_user',
            Config::get('entrust.user_foreign_key'),
            Config::get('entrust.permission_foreign_key')
        )->withPivot('team_id', 'is_denied');
    }

    /**
     * Boot the user model
     * Attach event listener to remove the many-to-many records when trying to delete
     * Will NOT delete any records if the user model uses soft deletes.
     */
    public static function boot(): void
    {
        parent::boot();

        static::created(function ($user) {
            if (Config::get('entrust.auto_assign.enabled')) {
                $user->runAutoAssignment();
            }
        });

        static::deleting(function ($user) {
            if (!method_exists(Config::get('auth.providers.users.model'), 'bootSoftDeletes')) {
                $user->roles()->sync([]);
            }

            return true;
        });
    }

    /**
     * Checks if the user has a role by its name.
     */
    public function hasRole(string|array $name, bool $requireAll = false, ?string $guard = null): bool
    {
        if (is_array($name)) {
            foreach ($name as $roleName) {
                $hasRole = $this->hasRole($roleName, false, $guard);

                if ($hasRole && !$requireAll) {
                    return true;
                }

                if (!$hasRole && $requireAll) {
                    return false;
                }
            }

            return $requireAll;
        }

        foreach ($this->cachedRoles() as $role) {
            if ($role->name === $name && ($guard === null || $role->guard_name === $guard)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if user has a permission by its name.
     */
    public function can(string|array $permission, bool $requireAll = false, ?string $guard = null, mixed $context = null): bool
    {
        if (is_array($permission)) {
            foreach ($permission as $permName) {
                $hasPerm = $this->can($permName, false, $guard, $context);

                if ($hasPerm && !$requireAll) {
                    return true;
                } elseif (!$hasPerm && $requireAll) {
                    return false;
                }
            }

            return $requireAll;
        }

        // 0. Sabse pehle check karein agar koi explicit "Deny" (Blacklist) hai
        if ($this->isExplicitlyDenied($permission, $guard)) {
            return false;
        }

        // 1. Check if user has the permission directly or via roles
        $permissionObject = $this->getPermissionObject($permission, $guard, $context);

        if (!$permissionObject) {
            return false;
        }

        // 2. Resolve Contextual Rules (NEW in v3.0)
        if ($context !== null && !$this->validateContext($permissionObject, $context)) {
            return false;
        }

        // 3. Sudo Mode Check (NEW in v4.0 Mythic)
        if ($this->requiresSudo($permissionObject) && !$this->sudoMode()) {
            return false;
        }

        // 4. Resolve dependencies (Prerequisites)
        if (!empty($permissionObject->depends_on)) {
            $dependencies = is_string($permissionObject->depends_on)
                ? json_decode($permissionObject->depends_on, true)
                : $permissionObject->depends_on;

            if (is_array($dependencies)) {
                foreach ($dependencies as $dependency) {
                    if (!$this->can($dependency, false, $guard)) {
                        return false;
                    }
                }
            }
        }

        return true;
    }

    /**
     * Recursive permission check for resource inheritance.
     */
    public function canInherited(string $permission, mixed $context = null): bool
    {
        if ($this->can($permission, false, null, $context)) {
            return true;
        }

        // Dotted path fallback: "project.1.task.5" -> "project.1"
        if (str_contains($permission, '.')) {
            $parentPermission = substr($permission, 0, strrpos($permission, '.'));
            return $this->canInherited($parentPermission, $context);
        }

        return false;
    }

    /**
     * Find a permission object that the user possesses (direct or via roles).
     */
    protected function getPermissionObject(string $name, ?string $guard = null): ?object
    {
        // Check direct permissions (Only those NOT denied)
        foreach ($this->cachedPermissions() as $perm) {
            if (\Illuminate\Support\Str::is($name, $perm->name) && ($guard === null || $perm->guard_name === $guard)) {
                if (!$perm->pivot->is_denied) {
                    return $perm;
                }
            }
        }

        // Check role permissions (Validate Access Rules: Time/IP)
        foreach ($this->cachedRoles() as $role) {
            if ($this->validateAccessRules($role)) {
                foreach ($role->cachedPermissions() as $perm) {
                    if (\Illuminate\Support\Str::is($name, $perm->name) && ($guard === null || $perm->guard_name === $guard)) {
                        return $perm;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Validate contextual rules for a permission against a given context.
     */
    protected function validateContext(object $permission, mixed $context): bool
    {
        if (empty($permission->context_rules)) {
            return true;
        }

        $rules = is_string($permission->context_rules) ? json_decode($permission->context_rules, true) : $permission->context_rules;

        foreach ($rules as $userAttr => $contextAttr) {
            // Support "owner" shorthand: user.id == context.user_id
            $val1 = $this->{$userAttr} ?? null;

            $val2 = null;
            if (is_object($context)) {
                $val2 = $context->{$contextAttr} ?? null;
            } elseif (is_array($context)) {
                $val2 = $context[$contextAttr] ?? null;
            }

            if ($val1 !== $val2) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if a permission is explicitly denied for this user.
     */
    protected function isExplicitlyDenied(string $name, ?string $guard = null): bool
    {
        foreach ($this->cachedPermissions() as $perm) {
            if (\Illuminate\Support\Str::is($name, $perm->name) && ($guard === null || $perm->guard_name === $guard)) {
                if ($perm->pivot->is_denied) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Validate Time and IP based access rules for a role.
     */
    protected function validateAccessRules($role): bool
    {
        if (empty($role->access_rules)) {
            return true;
        }

        $rules = is_string($role->access_rules) ? json_decode($role->access_rules, true) : $role->access_rules;

        // 1. IP Whitelist
        if (!empty($rules['ips'])) {
            $currentIp = \Illuminate\Support\Facades\Request::ip();
            $allowed = false;
            foreach ($rules['ips'] as $pattern) {
                if (\Illuminate\Support\Str::is($pattern, $currentIp)) {
                    $allowed = true;
                    break;
                }
            }
            if (!$allowed) return false;
        }

        // 2. Time Scheduling
        if (!empty($rules['times'])) {
            $now = \Carbon\Carbon::now();
            $day = strtolower($now->format('l'));
            if (!isset($rules['times'][$day])) {
                return false; // Not allowed on this day
            }

            $currentSlot = $now->format('H:i');
            $allowed = false;
            foreach ($rules['times'][$day] as $slot) {
                if ($currentSlot >= $slot['start'] && $currentSlot <= $slot['end']) {
                    $allowed = true;
                    break;
                }
            }
            if (!$allowed) return false;
        }

        return true;
    }

    /**
     * Run auto-assignment rules for the user.
     */
    public function runAutoAssignment(): void
    {
        $rules = Config::get('entrust.auto_assign.rules', []);
        foreach ($rules as $roleName => $conditions) {
            $match = true;
            foreach ($conditions as $attribute => $pattern) {
                if (!\Illuminate\Support\Str::is($pattern, (string) $this->{$attribute})) {
                    $match = false;
                    break;
                }
            }

            if ($match) {
                $roleClass = Config::get('entrust.role');
                $role = $roleClass::where('name', $roleName)->first();
                if ($role) {
                    $this->attachRole($role);
                }
            }
        }
    }

    /**
     * Get cached direct permissions for the user.
     */
    public function cachedPermissions(): Collection
    {
        $userPrimaryKey = $this->primaryKey;
        $teamId = $this->activeTeamId;
        $cacheKey = 'entrust_direct_permissions_for_user_' . $this->$userPrimaryKey . '_team_' . ($teamId ?? 'global');

        if (Cache::getStore() instanceof TaggableStore) {
            $tags = ['entrust_user_' . $this->$userPrimaryKey, Config::get('entrust.permissions_table', 'permissions')];
            return Cache::tags($tags)->remember($cacheKey, Config::get('cache.ttl'), function () use ($teamId) {
                $query = $this->permissions();
                if ($teamId) {
                    $query->wherePivot('team_id', $teamId);
                }
                return $query->get();
            });
        }

        $query = $this->permissions();
        if ($teamId) {
            $query->wherePivot('team_id', $teamId);
        }
        return $query->get();
    }

    /**
     * Alias to eloquent many-to-many relation's attach() method for permissions.
     */
    public function attachPermission(mixed $permission, mixed $teamId = null): void
    {
        if (is_object($permission)) {
            $permission = $permission->getKey();
        }

        if (is_array($permission)) {
            $permission = $permission['id'];
        }

        $teamId = $teamId ?: $this->activeTeamId;
        $this->permissions()->attach($permission, ['team_id' => $teamId]);

        $this->logAudit('permission_attached', [
            'target_user_id' => $this->getKey(),
            'permission_id' => $permission,
            'team_id' => $teamId,
        ]);

        if (Cache::getStore() instanceof TaggableStore) {
            Cache::tags('entrust_user_' . $this->getKey())->flush();
        }

        $isDenied = \Illuminate\Support\Facades\Request::has('is_denied') || (is_array($permission) && isset($permission['is_denied']));
        if ($isDenied) {
            $this->alertSecurity('Permission Blacklisted', [
                'user' => $this->email,
                'permission' => $permission,
            ]);
        }
    }

    /**
     * Alias to eloquent many-to-many relation's detach() method for permissions.
     */
    public function detachPermission(mixed $permission, mixed $teamId = null): void
    {
        if (is_object($permission)) {
            $permission = $permission->getKey();
        }

        if (is_array($permission)) {
            $permission = $permission['id'];
        }

        $teamId = $teamId ?: $this->activeTeamId;
        $this->permissions()->wherePivot('team_id', $teamId)->detach($permission);

        $this->logAudit('permission_detached', [
            'target_user_id' => $this->getKey(),
            'permission_id' => $permission,
            'team_id' => $teamId,
        ]);

        if (Cache::getStore() instanceof TaggableStore) {
            Cache::tags('entrust_user_' . $this->getKey())->flush();
        }
    }

    /**
     * Checks role(s) and permission(s).
     *
     * @param array|string $roles       Array of roles or comma separated string
     * @param array|string $permissions Array of permissions or comma separated string.
     * @param array        $options     validate_all (true|false) or return_type (boolean|array|both)
     */
    public function ability(array|string $roles, array|string $permissions, array $options = []): array|bool
    {
        // Convert string to array if that's what is passed in.
        if (!is_array($roles)) {
            $roles = explode(',', $roles);
        }
        if (!is_array($permissions)) {
            $permissions = explode(',', $permissions);
        }

        // Set up default values and validate options.
        $options['validate_all'] ??= false;
        $options['return_type']  ??= 'boolean';

        if (!is_bool($options['validate_all'])) {
            throw new InvalidArgumentException();
        }

        if (!in_array($options['return_type'], ['boolean', 'array', 'both'], true)) {
            throw new InvalidArgumentException();
        }

        // Loop through roles and permissions and check each.
        $checkedRoles = [];
        $checkedPermissions = [];
        foreach ($roles as $role) {
            $checkedRoles[$role] = $this->hasRole($role);
        }
        foreach ($permissions as $permission) {
            $checkedPermissions[$permission] = $this->can($permission);
        }

        // If validate all and there is a false in either
        // Check that if validate all, then there should not be any false.
        // Check that if not validate all, there must be at least one true.
        if (($options['validate_all'] && !(in_array(false, $checkedRoles) || in_array(false, $checkedPermissions))) ||
            (!$options['validate_all'] && (in_array(true, $checkedRoles) || in_array(true, $checkedPermissions)))
        ) {
            $validateAll = true;
        } else {
            $validateAll = false;
        }

        return match ($options['return_type']) {
            'boolean' => $validateAll,
            'array'   => ['roles' => $checkedRoles, 'permissions' => $checkedPermissions],
            'both'    => [$validateAll, ['roles' => $checkedRoles, 'permissions' => $checkedPermissions]],
        };
    }

    /**
     * Alias to eloquent many-to-many relation's attach() method.
     */
    public function attachRole(mixed $role, mixed $teamId = null, mixed $expiresAt = null): void
    {
        if (is_object($role)) {
            $role = $role->getKey();
        }

        if (is_array($role)) {
            $role = $role['id'];
        }

        $teamId = $teamId ?: $this->activeTeamId;

        $pivotData = ['team_id' => $teamId];
        if ($expiresAt) {
            $pivotData['expires_at'] = $expiresAt;
        }

        $this->roles()->attach($role, $pivotData);

        $this->logAudit('role_attached', [
            'target_user_id' => $this->getKey(),
            'role_id'        => is_array($role) ? ($role['id'] ?? null) : $role,
            'team_id'        => $teamId,
        ]);

        if ($this->isSensitiveRole($role)) {
            $this->alertSecurity('Critical Role Attached', [
                'user' => $this->email,
                'role' => $role,
            ]);
        }
    }

    /**
     * Alias to eloquent many-to-many relation's detach() method.
     */
    public function detachRole(mixed $role, mixed $teamId = null): void
    {
        if (is_object($role)) {
            $role = $role->getKey();
        }

        if (is_array($role)) {
            $role = $role['id'];
        }

        $teamId = $teamId ?: $this->activeTeamId;

        $query = $this->roles();
        if ($teamId) {
            $query->wherePivot('team_id', $teamId);
        }

        $query->detach($role);

        $this->logAudit('role_detached', [
            'target_user_id' => $this->getKey(),
            'role_id'        => is_array($role) ? ($role['id'] ?? null) : $role,
            'team_id'        => $teamId,
        ]);
    }

    /**
     * Attach multiple roles to a user
     */
    public function attachRoles(mixed $roles): void
    {
        foreach ($roles as $role) {
            $this->attachRole($role);
        }
    }

    /**
     * Detach multiple roles from a user
     */
    public function detachRoles(mixed $roles = null, mixed $teamId = null): void
    {
        $teamId = $teamId ?: $this->activeTeamId;

        if (!$roles) {
            $query = $this->roles();
            if ($teamId) {
                $query->wherePivot('team_id', $teamId);
            }
            $roles = $query->get();
        }

        foreach ($roles as $role) {
            $this->detachRole($role, $teamId);
        }
    }

    /**
     * Filtering users according to their role
     */
    public function scopeWithRole(mixed $query, string $role, mixed $teamId = null): mixed
    {
        return $query->whereHas('roles', function ($q) use ($role, $teamId) {
            $q->where('name', $role);
            if ($teamId) {
                $q->wherePivot('team_id', $teamId);
            }
        });
    }

    /**
     * Get the rate limit for the user based on their roles.
     * Returns the highest rate limit found among user's roles.
     */
    public function getRateLimit(): int
    {
        $limit = 60; // Default
        foreach ($this->cachedRoles() as $role) {
            if ($role->rate_limit > $limit) {
                $limit = $role->rate_limit;
            }
        }
        return $limit;
    }

    /**
     * Request access to a role or permission (Approval Workflow).
     */
    public function requestAccess(string $type, string $item, ?string $reason = null): bool
    {
        return \Illuminate\Support\Facades\DB::table('skywalker_access_requests')->insert([
            'user_id' => $this->getKey(),
            'type' => $type,
            'requested_item' => $item,
            'reason' => $reason,
            'status' => 'pending',
            'created_at' => \Carbon\Carbon::now(),
            'updated_at' => \Carbon\Carbon::now(),
        ]);
    }

    /**
     * Check if sudo mode is active or handle elevation.
     */
    public function sudoMode(bool $activate = false): bool
    {
        $sessionKey = 'skywalker_sudo_mode_' . $this->getKey();

        if ($activate) {
            \Illuminate\Support\Facades\Session::put($sessionKey, \Carbon\Carbon::now()->addMinutes(15));
            return true;
        }

        $expiry = \Illuminate\Support\Facades\Session::get($sessionKey);
        return $expiry && \Carbon\Carbon::now()->lessThan($expiry);
    }

    /**
     * Check if a permission requires sudo mode.
     */
    protected function requiresSudo(object $permission): bool
    {
        // For now, any permission assigned to a protected role requires sudo if configured
        $protectedRoles = Config::get('entrust.protected_roles', ['admin']);
        foreach ($this->cachedRoles() as $role) {
            if (in_array($role->name, $protectedRoles)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Send a security alert via configured channels.
     */
    protected function alertSecurity(string $event, array $data): void
    {
        $config = Config::get('entrust.security_notifications', []);
        if (!($config['enabled'] ?? false)) {
            return;
        }

        $webhook = $config['webhook_url'] ?? null;
        if ($webhook) {
            try {
                \Illuminate\Support\Facades\Http::post($webhook, [
                    'text' => "🚨 [Skywalker Security] {$event}: " . json_encode($data),
                ]);
            } catch (\Exception $e) {
                // Fail silently
            }
        }
    }

    /**
     * Check if a role is considered sensitive (e.g., admin).
     */
    protected function isSensitiveRole(mixed $role): bool
    {
        $roleName = is_object($role) ? $role->name : (string)$role;
        return in_array($roleName, Config::get('entrust.protected_roles', ['admin']));
    }
}
