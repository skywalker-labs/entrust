<?php

namespace Skywalker\Entrust\Test\Stubs;

use Illuminate\Database\Eloquent\Model;
use Skywalker\Entrust\Traits\EntrustUserTrait;

class User extends Model
{
    use EntrustUserTrait;

    protected $guarded = [];
    public $timestamps = true;
}
