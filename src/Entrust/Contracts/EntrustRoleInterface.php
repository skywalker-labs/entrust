<?php

declare(strict_types=1);

namespace Skywalker\Entrust\Contracts;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Interface EntrustRoleInterface
 *
 * @package Skywalker\Entrust\Contracts
 */
interface EntrustRoleInterface
{
    /**
     * Many-to-Many relations with the user model.
     */
    public function users(): BelongsToMany;

    /**
     * Many-to-Many relations with the permission model.
     */
    public function perms(): BelongsToMany;

    /**
     * Save the inputted permissions.
     */
    public function savePermissions(mixed $inputPermissions): void;

    /**
     * Attach permission to current role.
     */
    public function attachPermission(mixed $permission): void;

    /**
     * Detach permission form current role.
     */
    public function detachPermission(mixed $permission): void;

    /**
     * Attach multiple permissions to current role.
     */
    public function attachPermissions(iterable $permissions): void;

    /**
     * Detach multiple permissions from current role
     */
    public function detachPermissions(iterable $permissions): void;
}
