<?php

/**
 * This file is part of Entrust,
 * a role & permission management solution for Laravel.
 *
 * @license MIT
 * @package Skywalker\Entrust
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Entrust Role Model
    |--------------------------------------------------------------------------
    |
    | This is the Role model used by Entrust to create correct relations.  Update
    | the role if it is in a different namespace.
    |
    */
    'role' => 'App\Role',

    /*
    |--------------------------------------------------------------------------
    | Entrust Roles Table
    |--------------------------------------------------------------------------
    |
    | This is the roles table used by Entrust to save roles to the database.
    |
    */
    'roles_table' => 'roles',

    /*
    |--------------------------------------------------------------------------
    | Entrust role foreign key
    |--------------------------------------------------------------------------
    |
    | This is the role foreign key used by Entrust to make a proper
    | relation between permissions and roles & roles and users
    |
    */
    'role_foreign_key' => 'role_id',

    /*
    |--------------------------------------------------------------------------
    | Application User Model
    |--------------------------------------------------------------------------
    |
    | This is the User model used by Entrust to create correct relations.
    | Update the User if it is in a different namespace.
    |
    */
    'user' => 'App\User',

    /*
    |--------------------------------------------------------------------------
    | Application Users Table
    |--------------------------------------------------------------------------
    |
    | This is the users table used by the application to save users to the
    | database.
    |
    */
    'users_table' => 'users',

    /*
    |--------------------------------------------------------------------------
    | Entrust role_user Table
    |--------------------------------------------------------------------------
    |
    | This is the role_user table used by Entrust to save assigned roles to the
    | database.
    |
    */
    'role_user_table' => 'role_user',

    /*
    |--------------------------------------------------------------------------
    | Entrust user foreign key
    |--------------------------------------------------------------------------
    |
    | This is the user foreign key used by Entrust to make a proper
    | relation between roles and users
    |
    */
    'user_foreign_key' => 'user_id',

    /*
    |--------------------------------------------------------------------------
    | Entrust Permission Model
    |--------------------------------------------------------------------------
    |
    | This is the Permission model used by Entrust to create correct relations.
    | Update the permission if it is in a different namespace.
    |
    */
    'permission' => 'App\Permission',

    /*
    |--------------------------------------------------------------------------
    | Entrust Permissions Table
    |--------------------------------------------------------------------------
    |
    | This is the permissions table used by Entrust to save permissions to the
    | database.
    |
    */
    'permissions_table' => 'permissions',

    /*
    |--------------------------------------------------------------------------
    | Entrust permission_role Table
    |--------------------------------------------------------------------------
    |
    | This is the permission_role table used by Entrust to save relationship
    | between permissions and roles to the database.
    |
    */
    'permission_role_table' => 'permission_role',

    /*
    |--------------------------------------------------------------------------
    | Entrust permission foreign key
    |--------------------------------------------------------------------------
    |
    | This is the permission foreign key used by Entrust to make a proper
    | relation between permissions and roles
    |
    */
    'permission_foreign_key' => 'permission_id',

    /*
    |--------------------------------------------------------------------------
    | Sync Definitions
    |--------------------------------------------------------------------------
    |
    | This is where you can define your roles and permissions to be synced
    | to the database using the `php artisan skywalker:sync` command.
    |
    */
    'sync' => [
        'roles' => [
            'admin' => [
                'display_name' => 'Administrator',
                'description'  => 'User has full access to the system',
                'permissions'  => ['users-manage', 'roles-manage'],
            ],
            'user' => [
                'display_name' => 'Standard User',
                'description'  => 'User has basic access',
                'permissions'  => ['profile-manage'],
            ],
        ],
        'permissions' => [
            'users-manage'   => ['display_name' => 'Manage Users'],
            'roles-manage'   => ['display_name' => 'Manage Roles'],
            'profile-manage' => ['display_name' => 'Manage Profile'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto-Assignment Rules
    |--------------------------------------------------------------------------
    |
    | Define rules to automatically assign roles to users based on attributes.
    |
    */
    'auto_assign' => [
        'enabled' => false,
        'rules' => [
            // 'role_name' => ['attribute' => 'value_pattern'],
            // Example: 'employee' => ['email' => '*@company.com'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Protected Roles (Safe Mode)
    |--------------------------------------------------------------------------
    |
    | Roles that cannot be deleted if they still have members.
    |
    */
    'protected_roles' => ['admin'],

    /*
    |--------------------------------------------------------------------------
    | Security Notifications (Legendary Suite)
    |--------------------------------------------------------------------------
    |
    | Configure webhooks for critical security events.
    |
    */
    'security_notifications' => [
        'enabled' => false,
        'webhook_url' => null, // e.g., Discord or Slack webhook
    ],
];
