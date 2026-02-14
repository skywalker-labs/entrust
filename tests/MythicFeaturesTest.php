<?php

namespace Skywalker\Entrust\Test;

use Skywalker\Entrust\EntrustRole;
use Skywalker\Entrust\EntrustPermission;
use Skywalker\Entrust\Test\Stubs\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Cache;

class MythicFeaturesTest extends TestBase
{
    public function setUp(): void
    {
        parent::setUp();

        // Clear cache
        Cache::flush();
    }

    public function testRoleBasedRateLimiting()
    {
        $user = User::create(['email' => 'test@example.com']);
        $role = EntrustRole::create(['name' => 'vip', 'rate_limit' => 1000]);
        $user->attachRole($role);

        $this->assertEquals(1000, $user->getRateLimit());

        // Add a higher limit role
        $superVip = EntrustRole::create(['name' => 'super-vip', 'rate_limit' => 5000]);
        $user->attachRole($superVip);

        // Should get the MAX limit
        $this->assertEquals(5000, $user->getRateLimit());
    }

    public function testAccessRequestWorkflow()
    {
        $user = User::create(['email' => 'requester@example.com']);

        // User requests a role
        $user->requestAccess('role', 'editor', 'Please let me edit');

        $request = DB::table('skywalker_access_requests')->where('user_id', $user->id)->first();
        $this->assertNotNull($request);
        $this->assertEquals('pending', $request->status);
        $this->assertEquals('editor', $request->requested_item);

        // Administrator approves via Command Logic (Simulated)
        $role = EntrustRole::create(['name' => 'editor']);

        // Simulate ApproveCommand logic
        $user->attachRole($role);
        DB::table('skywalker_access_requests')->where('id', $request->id)->update(['status' => 'approved']);

        $this->assertTrue($user->hasRole('editor'));
        $this->assertEquals('approved', DB::table('skywalker_access_requests')->first()->status);
    }

    public function testSudoModeCheck()
    {
        $user = User::create(['email' => 'admin@example.com']);
        $adminRole = EntrustRole::create(['name' => 'admin']);
        $user->attachRole($adminRole);

        // Simulated Permission Object
        $perm = (object)['name' => 'delete-db'];

        // Sudo Mode IS NOT active by default
        $this->assertFalse($user->sudoMode());

        // Activate Sudo Mode
        $user->sudoMode(true);
        $this->assertTrue($user->sudoMode());
    }

    public function testRecursiveResourceInheritance()
    {
        $user = User::create(['email' => 'resource_user@example.com']);
        $perm = EntrustPermission::create(['name' => 'project.1']);
        $user->attachPermission($perm);

        // Should have base permission
        $this->assertTrue($user->can('project.1'));

        // Should Inherit Child Permission automatically via Mythic Logic
        $this->assertTrue($user->canInherited('project.1.task.5'));
        $this->assertTrue($user->canInherited('project.1.comment.42'));

        // Should NOT have unrelated permission
        $this->assertFalse($user->canInherited('project.2'));
    }
}
