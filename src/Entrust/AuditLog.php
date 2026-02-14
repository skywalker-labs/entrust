<?php

declare(strict_types=1);

namespace Skywalker\Entrust;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'entrust_audit_logs';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'event',
        'target_user_id',
        'role_id',
        'permission_id',
        'team_id',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'metadata' => 'array',
    ];
}
