<?php

declare(strict_types=1);

namespace Skywalker\Entrust\Traits;

use Illuminate\Support\Facades\Config;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Trait EntrustPermissionTrait
 *
 * @package Skywalker\Entrust\Traits
 */
trait EntrustPermissionTrait
{
    /**
     * Many-to-Many relations with role model.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            Config::get('entrust.role'),
            Config::get('entrust.permission_role_table'),
            Config::get('entrust.permission_foreign_key'),
            Config::get('entrust.role_foreign_key')
        );
    }

    /**
     * Boot the permission model
     * Attach event listener to remove the many-to-many records when trying to delete
     * Will NOT delete any records if the permission model uses soft deletes.
     */
    public static function boot(): void
    {
        parent::boot();

        static::deleting(function ($permission) {
            if (!method_exists(Config::get('entrust.permission'), 'bootSoftDeletes')) {
                $permission->roles()->sync([]);
            }

            return true;
        });
    }

    /**
     * Scope a query to only include permissions of a given group.
     */
    public function scopeGroup(\Illuminate\Database\Eloquent\Builder $query, string $group): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('group_name', $group);
    }
}
