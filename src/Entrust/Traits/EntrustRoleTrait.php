<?php

declare(strict_types=1);

namespace Skywalker\Entrust\Traits;

use Illuminate\Cache\TaggableStore;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Trait EntrustRoleTrait
 *
 * @package Skywalker\Entrust\Traits
 */
trait EntrustRoleTrait
{
    use Auditable;

    /**
     * Big block of caching functionality.
     */
    public function cachedPermissions(): Collection
    {
        $rolePrimaryKey = $this->primaryKey;
        $cacheKey = 'entrust_permissions_for_role_' . $this->$rolePrimaryKey;

        if (Cache::getStore() instanceof TaggableStore) {
            $tags = [Config::get('entrust.permission_role_table'), 'entrust_role_' . $this->$rolePrimaryKey];
            return Cache::tags($tags)->remember($cacheKey, Config::get('cache.ttl', 60), function () {
                return $this->perms()->get();
            });
        }

        return $this->perms()->get();
    }

    public function save(array $options = []): bool
    {
        if (!parent::save($options)) {
            return false;
        }

        if (Cache::getStore() instanceof TaggableStore) {
            $rolePrimaryKey = $this->primaryKey;
            Cache::tags('entrust_role_' . $this->$rolePrimaryKey)->flush();
        }

        return true;
    }

    public function delete(array $options = []): bool
    {
        if (in_array($this->name, Config::get('entrust.protected_roles', []))) {
            if ($this->users()->count() > 0) {
                throw new \RuntimeException("Cannot delete protected role '{$this->name}' while it has active members.");
            }
        }

        if (!parent::delete($options)) {
            return false;
        }

        if (Cache::getStore() instanceof TaggableStore) {
            $rolePrimaryKey = $this->primaryKey;
            Cache::tags('entrust_role_' . $this->$rolePrimaryKey)->flush();
        }

        return true;
    }

    public function restore(): bool
    {
        if (!parent::restore()) {
            return false;
        }

        if (Cache::getStore() instanceof TaggableStore) {
            Cache::tags(Config::get('entrust.permission_role_table'))->flush();
        }

        return true;
    }

    /**
     * Many-to-Many relations with the user model.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            Config::get('auth.providers.users.model'),
            Config::get('entrust.role_user_table'),
            Config::get('entrust.role_foreign_key'),
            Config::get('entrust.user_foreign_key')
        );
    }

    /**
     * Role hierarchy: parent role.
     */
    public function parent(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(get_class($this), 'parent_id');
    }

    /**
     * Many-to-Many relations with the permission model.
     */
    public function perms(): BelongsToMany
    {
        return $this->belongsToMany(
            Config::get('entrust.permission'),
            Config::get('entrust.permission_role_table'),
            Config::get('entrust.role_foreign_key'),
            Config::get('entrust.permission_foreign_key')
        );
    }

    /**
     * Boot the role model
     * Attach event listener to remove the many-to-many records when trying to delete
     * Will NOT delete any records if the role model uses soft deletes.
     */
    public static function boot(): void
    {
        parent::boot();

        static::creating(function ($role) {
            if (empty($role->guard_name)) {
                $role->guard_name = 'web';
            }
        });

        static::deleting(function ($role) {
            if (!method_exists(Config::get('entrust.role'), 'bootSoftDeletes')) {
                $role->users()->sync([]);
                $role->perms()->sync([]);
            }

            return true;
        });
    }

    /**
     * Checks if the role has a permission by its name.
     */
    public function hasPermission(string|array $name, bool $requireAll = false, ?string $guard = null): bool
    {
        if (is_array($name)) {
            foreach ($name as $permissionName) {
                $hasPermission = $this->hasPermission($permissionName, false, $guard);

                if ($hasPermission && !$requireAll) {
                    return true;
                }

                if (!$hasPermission && $requireAll) {
                    return false;
                }
            }

            return $requireAll;
        }

        // Check current role permissions
        foreach ($this->cachedPermissions() as $permission) {
            if (\Illuminate\Support\Str::is($name, $permission->name) && ($guard === null || $permission->guard_name === $guard)) {
                return true;
            }
        }

        // Check parent role permissions (Inheritance)
        if ($this->parent_id && $this->parent) {
            return $this->parent->hasPermission($name, $requireAll, $guard);
        }

        return false;
    }

    /**
     * Save the inputted permissions.
     */
    public function savePermissions(mixed $inputPermissions): void
    {
        if (!empty($inputPermissions)) {
            $this->perms()->sync($inputPermissions);
        } else {
            $this->perms()->detach();
        }

        if (Cache::getStore() instanceof TaggableStore) {
            Cache::tags(Config::get('entrust.permission_role_table'))->flush();
        }
    }

    /**
     * Attach permission to current role.
     */
    public function attachPermission(mixed $permission): void
    {
        if (is_object($permission)) {
            $permission = $permission->getKey();
        }

        if (is_array($permission)) {
            $this->attachPermissions($permission);
            return;
        }

        $this->perms()->attach($permission);

        $this->logAudit('permission_attached', [
            'target_user_id' => 0, // System/Role event
            'role_id'        => $this->getKey(),
            'permission_id'  => is_object($permission) ? $permission->getKey() : $permission,
        ]);
    }

    /**
     * Detach permission from current role.
     */
    public function detachPermission(mixed $permission): void
    {
        if (is_object($permission)) {
            $permission = $permission->getKey();
        }

        if (is_array($permission)) {
            $this->detachPermissions($permission);
            return;
        }

        $this->perms()->detach($permission);

        $this->logAudit('permission_detached', [
            'target_user_id' => 0, // System/Role event
            'role_id'        => $this->getKey(),
            'permission_id'  => is_object($permission) ? $permission->getKey() : $permission,
        ]);
    }

    /**
     * Attach multiple permissions to current role.
     */
    public function attachPermissions(iterable $permissions): void
    {
        foreach ($permissions as $permission) {
            $this->attachPermission($permission);
        }
    }

    /**
     * Detach multiple permissions from current role
     */
    public function detachPermissions(iterable $permissions = null): void
    {
        if (!$permissions) {
            $permissions = $this->perms()->get();
        }

        foreach ($permissions as $permission) {
            $this->detachPermission($permission);
        }
    }
}
