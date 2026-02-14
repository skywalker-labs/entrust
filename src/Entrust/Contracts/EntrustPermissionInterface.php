<?php

declare(strict_types=1);

namespace Skywalker\Entrust\Contracts;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Interface EntrustPermissionInterface
 *
 * @package Skywalker\Entrust\Contracts
 */
interface EntrustPermissionInterface
{
    /**
     * Many-to-Many relations with role model.
     */
    public function roles(): BelongsToMany;
}
