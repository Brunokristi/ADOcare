<?php

namespace Tests\Unit;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserRoleRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_provide_role_id_on_store()
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $req = new StoreUserRequest();
        $req->merge(['first_name' => 'a', 'last_name' => 'b', 'role_id' => 1]);
        $this->assertFalse($req->authorize());

        $req2 = new StoreUserRequest();
        $req2->merge(['first_name' => 'a', 'last_name' => 'b']);
        $this->assertTrue($req2->authorize());
    }

    public function test_non_admin_cannot_provide_role_id_on_update()
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $req = new UpdateUserRequest();
        $req->merge(['role_id' => 2]);
        $this->assertFalse($req->authorize());

        $req2 = new UpdateUserRequest();
        $req2->merge(['first_name' => 'x']);
        $this->assertTrue($req2->authorize());
    }
}
