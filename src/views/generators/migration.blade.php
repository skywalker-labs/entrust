<?php echo '<?php' ?>

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class EntrustSetupTables extends Migration
{
/**
* Run the migrations.
*
* @return void
*/
public function up()
{
DB::beginTransaction();

// Create table for storing roles
Schema::create('{{ $rolesTable }}', function (Blueprint $table) {
$table->increments('id');
$table->string('name');
$table->string('guard_name')->default('web');
$table->unsignedInteger('parent_id')->nullable();
$table->string('display_name')->nullable();
$table->string('description')->nullable();
$table->json('access_rules')->nullable();
$table->integer('rate_limit')->nullable()->default(60); // Mythic feature (req/min)
$table->boolean('is_protected')->default(false);
$table->timestamps();

$table->unique(['name', 'guard_name']);
$table->foreign('parent_id')->references('id')->on('{{ $rolesTable }}')
->onUpdate('cascade')->onDelete('set null');
});

// Create table for associating roles to users (Many-to-Many)
Schema::create('{{ $roleUserTable }}', function (Blueprint $table) {
$table->unsignedBigInteger('user_id');
$table->unsignedInteger('role_id');
$table->unsignedBigInteger('team_id')->nullable();
$table->timestamp('expires_at')->nullable();

$table->foreign('user_id')->references('{{ $userKeyName }}')->on('{{ $usersTable }}')
->onUpdate('cascade')->onDelete('cascade');
$table->foreign('role_id')->references('id')->on('{{ $rolesTable }}')
->onUpdate('cascade')->onDelete('cascade');

$table->unique(['user_id', 'role_id', 'team_id']);
});

// Create table for storing permissions
Schema::create('{{ $permissionsTable }}', function (Blueprint $table) {
$table->increments('id');
$table->string('name');
$table->string('guard_name')->default('web');
$table->string('group_name')->nullable();
$table->json('depends_on')->nullable();
$table->json('context_rules')->nullable(); // Dynamic attribute checks
$table->string('display_name')->nullable();
$table->string('description')->nullable();
$table->timestamps();

$table->unique(['name', 'guard_name']);
});

// Create table for associating permissions to roles (Many-to-Many)
Schema::create('{{ $permissionRoleTable }}', function (Blueprint $table) {
$table->unsignedInteger('permission_id');
$table->unsignedInteger('role_id');

$table->foreign('permission_id')->references('id')->on('{{ $permissionsTable }}')
->onUpdate('cascade')->onDelete('cascade');
$table->foreign('role_id')->references('id')->on('{{ $rolesTable }}')
->onUpdate('cascade')->onDelete('cascade');

$table->primary(['permission_id', 'role_id']);
});

// Create table for associating permissions to users (Many-to-Many)
Schema::create('permission_user', function (Blueprint $table) {
$table->unsignedInteger('permission_id');
$table->unsignedBigInteger('team_id')->nullable();
$table->boolean('is_denied')->default(false);

$table->foreign('user_id')->references('{{ $userKeyName }}')->on('{{ $usersTable }}')
->onUpdate('cascade')->onDelete('cascade');
$table->foreign('permission_id')->references('id')->on('{{ $permissionsTable }}')
->onUpdate('cascade')->onDelete('cascade');

$table->unique(['user_id', 'permission_id', 'team_id']);
});

// Create table for audit logs
Schema::create('entrust_audit_logs', function (Blueprint $table) {
$table->bigIncrements('id');
$table->unsignedBigInteger('user_id')->nullable();
$table->string('event'); // e.g., role_attached, role_detached
$table->unsignedBigInteger('target_user_id');
$table->unsignedInteger('role_id')->nullable();
$table->unsignedInteger('permission_id')->nullable();
$table->unsignedBigInteger('team_id')->nullable();
$table->json('metadata')->nullable();
$table->timestamps();

$table->foreign('user_id')->references('{{ $userKeyName }}')->on('{{ $usersTable }}')
->onDelete('set null');
$table->foreign('target_user_id')->references('{{ $userKeyName }}')->on('{{ $usersTable }}')
->onDelete('cascade');
});

// Create skywalker_access_requests table (Mythic v4.0)
Schema::create('skywalker_access_requests', function (Blueprint $table) {
$table->increments('id');
$table->unsignedBigInteger('user_id');
$table->string('type'); // 'role' or 'permission'
$table->string('requested_item'); // name of role or perm
$table->text('reason')->nullable();
$table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
$table->unsignedBigInteger('processed_by')->nullable();
$table->timestamps();

$table->foreign('user_id')->references('{{ $userKeyName }}')->on('{{ $usersTable }}')
->onDelete('cascade');
});

DB::commit();
}

/**
* Reverse the migrations.
*
* @return void
*/
public function down()
{
Schema::drop('permission_user');
Schema::drop('{{ $permissionRoleTable }}');
Schema::drop('{{ $permissionsTable }}');
Schema::drop('{{ $roleUserTable }}');
Schema::drop('{{ $rolesTable }}');
}
}