<?php

declare(strict_types=1);

namespace Skywalker\Entrust\Contracts;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Interface EntrustUserInterface
 *
 * @package Skywalker\Entrust\Contracts
 */
interface EntrustUserInterface
{
    /**
     * Many-to-Many relations with Role.
     */
    public function roles(): BelongsToMany;

    /**
     * Checks if the user has a role by its name.
     */
    public function hasRole(string|array $name, bool $requireAll = false, ?string $guard = null): bool;

    /**
     * Check if user has a permission by its name.
     */
    public function can(string|array $permission, bool $requireAll = false, ?string $guard = null): bool;

    /**
     * Checks role(s) and permission(s).
     */
    public function ability(array|string $roles, array|string $permissions, array $options = []): array|bool;

    /**
     * Alias to eloquent many-to-many relation's attach() method.
     */
    public function attachRole(mixed $role, mixed $teamId = null, mixed $expiresAt = null): void;

    /**
     * Alias to eloquent many-to-many relation's detach() method.
     */
    public function detachRole(mixed $role, mixed $teamId = null): void;

    /**
     * Attach multiple roles to a user
     */
    public function attachRoles(mixed $roles): void;

    /**
     * Detach multiple roles from a user
     */
    public function detachRoles(mixed $roles = null, mixed $teamId = null): void;
}
