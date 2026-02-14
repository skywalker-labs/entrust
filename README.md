# Skywalker Entrust (Laravel 10, 11 & 12)

[![Latest Version on Packagist](https://img.shields.io/packagist/v/skywalker-labs/entrust.svg?style=flat-square)](https://packagist.org/packages/skywalker-labs/entrust)
[![License](https://img.shields.io/packagist/l/skywalker-labs/entrust.svg?style=flat-square)](LICENSE)

**Skywalker Entrust** is the ultimate identity and access management (IAM) solution for Laravel. From simple role-based access control (RBAC) to enterprise-grade governance, auditing, and AI-ready security heuristics.

---

## 🚀 Quick Start

### 1. Installation
```bash
composer require skywalker-labs/entrust
```

### 2. Setup (One-Liner)
Run the setup command to publish config and migrations:
```bash
php artisan vendor:publish --provider="Skywalker\Entrust\EntrustServiceProvider"
php artisan entrust:migration
php artisan migrate
```

### 3. User Setup
Add the `EntrustUserTrait` to your `User` model:
```php
use Skywalker\Entrust\Traits\EntrustUserTrait;

class User extends Authenticatable
{
    use EntrustUserTrait;
}
```

---

## 📚 Core Concepts & Usage

### 1. Creating Roles & Permissions
```php
use App\Models\Role;
use App\Models\Permission;

$admin = Role::create(['name' => 'admin', 'display_name' => 'Administrator']);
$editUser = Permission::create(['name' => 'edit-user', 'display_name' => 'Edit Users']);

// Assign permission to role
$admin->attachPermission($editUser);

// Assign role to user
$user->attachRole($admin);
```

### 2. Checking Access
```php
// Check for Role
$user->hasRole('admin'); 

// Check for Permission
$user->can('edit-user'); 

// Check for Multiple (True if ANY match)
$user->hasRole(['admin', 'editor']);
```

### 3. Blade Directives
```blade
@role('admin')
    <button>Delete User</button>
@endrole

@permission('edit-user')
    <button>Edit User</button>
@endpermission

@can('edit-user') 
    <!-- Works automatically via Smart Gate Bridge! -->
@endcan
```

---

## 🛡️ Intermediate Features (The "Ultimate" Suite)

### Multi-Tenancy (Teams)
Scope roles and permissions to specific teams/organizations.
```php
$user->attachRole($admin, $teamId);
$user->withTeam($teamId)->can('edit-user');
```

### Wildcard Permissions
Pattern matching for granular control.
```php
$user->can('users.*'); // Matches users.create, users.edit, etc.
```

### Middleware
Protect routes easily in `routes/web.php`.
```php
Route::group(['middleware' => ['role:admin']], function() { ... });
Route::group(['middleware' => ['permission:publish-post']], function() { ... });
```

---

## ♟️ Advanced Features (The "Grandmaster" Suite)

### Role Inheritance
Create hierarchies where parents inherit child permissions.
```php
$manager->parent_id = $employee->id;
$manager->save(); // Manager now has all Employee permissions
```

### Temporary Access (Expiring Roles)
Grant access for a limited time.
```php
$user->attachRole($consultant, null, now()->addDays(7));
```

### Permission Dependencies
Enforce prerequisites. `delete-post` requires `view-post`.
```php
$deletePost->depends_on = ['view-post'];
$deletePost->save();
```

---

## 🏆 Enterprise Governance (The "Legendary" Suite)

### Global State Rollback
Made a mistake? Snapshot and restore your entire ACL configuration.
```bash
php artisan skywalker:rollback --backup
php artisan skywalker:rollback --restore=snapshot_2023.json
```

### Security Scanner & Heuristics
Find risks like dormant admins (inactive >30 days) or over-privileged users.
```bash
php artisan skywalker:scan
```

### Contextual Permissions
Check permissions against specific model instances (e.g., Ownership).
```php
$user->can('edit-post', false, null, $post); 
// Validates if $post->user_id == $user->id (if configured)
```

### Audit Logs & Notifications
Track every assignment. Configure webhooks in `entrust.php` for real-time alerts on sensitive role changes.

---

## 👽 Mythic Access Control (v4.0)

### Access Request Workflow
Users request access, Admins approve via CLI.
```php
// User Code
$user->requestAccess('role', 'editor', 'I need to write blog posts');

// Admin CLI
php artisan skywalker:approve {id}
```

### Sudo Mode (Elevation)
Require re-authentication for sensitive actions.
```php
if ($user->requiresSudo($permission) && !$user->sudoMode()) {
    return redirect()->route('confirm-password');
}
```

### Role-Based Rate Limiting
Get API rate limits dynamically based on user roles.
```php
$limit = $user->getRateLimit(); // e.g., 60 for user, 1000 for VIP
```

### Recursive Resource Inheritance
Automatic permission flow for nested resources.
```php
// Having 'project.1' automatically grants 'project.1.task.5'
$user->canInherited('project.1.task.5'); 
```

---

## 🛠️ Artisan Command Reference

| Command | Description |
|:---|:---|
| `skywalker:setup` | Run migrations and publish config. |
| `skywalker:scan` | Scan for security risks and heuristic anomalies. |
| `skywalker:map` | Visualize role hierarchy in ASCII. |
| `skywalker:trace {user} {perm}` | Debug how a user got a permission. |
| `skywalker:rollback` | Manage ACL state snapshots. |
| `skywalker:approve` | Manage pending access requests. |
| `skywalker:sync` | Sync DB roles/perms with config file. |
| `skywalker:ui` | Generate a management dashboard scaffold. |
| `skywalker:audit-cleanup` | Prune old audit logs. |

---

## License
Skywalker Entrust is open-sourced software licensed under the [MIT license](LICENSE).
