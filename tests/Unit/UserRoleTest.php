<?php

namespace Tests\Unit;

use App\Models\User;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_global_role_assignment_and_helpers()
    {
        // create a role and assign it to a user via the `role_id` column
        // use one of the valid positions defined by the system; the
        // database enforces a CHECK constraint so arbitrary strings are
        // rejected.
        $role = Role::factory()->create(['position' => 'manager', 'scope' => 'global']);
        $user = User::factory()->create();

        $this->assertFalse($user->hasGlobalRole('manager'));
        // no role assigned yet
        $this->assertNull($user->role_id);

        // use the helper that replaces the old pivot API
        $user->assignRole($role);
        // refresh the model so that any cached relations/attributes are
        // cleared; this mimics how a real update would behave in the
        // application.
        $user->refresh();
        $this->assertEquals($role->id, $user->role_id);
        $this->assertTrue($user->hasGlobalRole('manager'));
        // verify helper alias still behaves correctly
        // (direct role access below)

        // relationship should resolve to the same object
        $this->assertInstanceOf(Role::class, $user->role);
        $this->assertEquals('manager', $user->role->position);

        // the API resource should include the raw role_id and the related
        // role object
        $array = (new \App\Http\Resources\UserResource($user))->toArray(null);
        $this->assertEquals($role->id, $array['role_id']);
        $this->assertArrayHasKey('role', $array);

        // role pivot no longer used; the migration actually drops the
        // table so we don't need to check its contents here.
    }
}
