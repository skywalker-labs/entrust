<?php

declare(strict_types=1);

namespace Skywalker\Entrust;

use Skywalker\Support\Database\PrefixedModel;
use Illuminate\Support\Facades\Config;

/**
 * This file is part of Entrust,
 * a role & permission management solution for Laravel.
 *
 * @license MIT
 * @package Skywalker\Entrust
 */
class EntrustRole extends PrefixedModel implements Contracts\EntrustRoleInterface
{
    use Traits\EntrustRoleTrait;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table;

    /**
     * Creates a new instance of the model.
     *
     * @param array $attributes
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->table = Config::get('entrust.roles_table');
    }
}
