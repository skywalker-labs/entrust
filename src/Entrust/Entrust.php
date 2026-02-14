<?php

declare(strict_types=1);

namespace Skywalker\Entrust;

use Illuminate\Contracts\Foundation\Application;

/**
 * This class is the main entry point of entrust. Usually the interaction
 * with this class will be done through the Entrust Facade
 *
 * @license MIT
 * @package Skywalker\Entrust
 */
class Entrust
{
    /**
     * Create a new entrust instance.
     */
    public function __construct(public \Illuminate\Contracts\Foundation\Application $app) {}

    /**
     * Set the active team for the current user.
     */
    public function withTeam(mixed $team): static
    {
        if ($user = $this->user()) {
            $user->withTeam($team);
        }

        return $this;
    }

    /**
     * Checks if the current user has a role by its name
     *
     * @param string|array $role Role name(s).
     */
    public function hasRole(string|array $role, bool $requireAll = false, ?string $guard = null): bool
    {
        if ($user = $this->user()) {
            return $user->hasRole($role, $requireAll, $guard);
        }

        return false;
    }

    /**
     * Check if the current user has a permission by its name
     *
     * @param string|array $permission Permission(s).
     */
    public function can(string|array $permission, bool $requireAll = false, ?string $guard = null): bool
    {
        if ($user = $this->user()) {
            return $user->can($permission, $requireAll, $guard);
        }

        return false;
    }

    /**
     * Check if the current user has a role or permission by its name
     *
     * @param array|string $roles            The role(s) needed.
     * @param array|string $permissions      The permission(s) needed.
     * @param array $options                 The Options.
     */
    public function ability(array|string $roles, array|string $permissions, array $options = []): array|bool
    {
        if ($user = $this->user()) {
            return $user->ability($roles, $permissions, $options);
        }

        return false;
    }

    /**
     * Get the currently authenticated user or null.
     */
    public function user(): mixed
    {
        return $this->app->auth?->user();
    }

    /**
     * Filters a route for a role or set of roles.
     *
     * If the third parameter is null then abort with status code 403.
     * Otherwise the $result is returned.
     */
    public function routeNeedsRole(string $route, array|string $roles, mixed $result = null, bool $requireAll = true): void
    {
        $filterName  = is_array($roles) ? implode('_', $roles) : $roles;
        $filterName .= '_' . substr(md5($route), 0, 6);

        $closure = function () use ($roles, $result, $requireAll) {
            $hasRole = $this->hasRole($roles, $requireAll);

            if (!$hasRole) {
                return empty($result) ? $this->app->abort(403) : $result;
            }
        };

        // Same as Route::filter, registers a new filter
        $this->app->router->filter($filterName, $closure);

        // Same as Route::when, assigns a route pattern to the
        // previously created filter.
        $this->app->router->when($route, $filterName);
    }

    /**
     * Filters a route for a permission or set of permissions.
     *
     * If the third parameter is null then abort with status code 403.
     * Otherwise the $result is returned.
     */
    public function routeNeedsPermission(string $route, array|string $permissions, mixed $result = null, bool $requireAll = true): void
    {
        $filterName  = is_array($permissions) ? implode('_', $permissions) : $permissions;
        $filterName .= '_' . substr(md5($route), 0, 6);

        $closure = function () use ($permissions, $result, $requireAll) {
            $hasPerm = $this->can($permissions, $requireAll);

            if (!$hasPerm) {
                return empty($result) ? $this->app->abort(403) : $result;
            }
        };

        // Same as Route::filter, registers a new filter
        $this->app->router->filter($filterName, $closure);

        // Same as Route::when, assigns a route pattern to the
        // previously created filter.
        $this->app->router->when($route, $filterName);
    }

    /**
     * Filters a route for role(s) and/or permission(s).
     *
     * If the third parameter is null then abort with status code 403.
     * Otherwise the $result is returned.
     */
    public function routeNeedsRoleOrPermission(string $route, array|string $roles, array|string $permissions, mixed $result = null, bool $requireAll = false): void
    {
        $filterName  =      is_array($roles)       ? implode('_', $roles)       : $roles;
        $filterName .= '_' . (is_array($permissions) ? implode('_', $permissions) : $permissions);
        $filterName .= '_' . substr(md5($route), 0, 6);

        $closure = function () use ($roles, $permissions, $result, $requireAll) {
            $hasRole  = $this->hasRole($roles, $requireAll);
            $hasPerms = $this->can($permissions, $requireAll);

            $hasRolePerm = $requireAll ? ($hasRole && $hasPerms) : ($hasRole || $hasPerms);

            if (!$hasRolePerm) {
                return empty($result) ? $this->app->abort(403) : $result;
            }
        };

        // Same as Route::filter, registers a new filter
        $this->app->router->filter($filterName, $closure);

        // Same as Route::when, assigns a route pattern to the
        // previously created filter.
        $this->app->router->when($route, $filterName);
    }
}
