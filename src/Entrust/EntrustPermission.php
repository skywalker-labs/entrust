<?php

declare(strict_types=1);

namespace Skywalker\Entrust;

use Skywalker\Support\Database\PrefixedModel;
use Illuminate\Support\Facades\Config;

/**
 * Class EntrustPermission
 *
 * @package Skywalker\Entrust
 */
class EntrustPermission extends PrefixedModel implements Contracts\EntrustPermissionInterface
{
    use Traits\EntrustPermissionTrait;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table;

    /**
     * Create a new Eloquent model instance.
     *
     * @param array $attributes
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->table = Config::get('entrust.permissions_table');
    }
}
