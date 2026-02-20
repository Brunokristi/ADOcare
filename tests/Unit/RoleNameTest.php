<?php

namespace Tests\Unit;

use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleNameTest extends TestCase
{
    use RefreshDatabase;

    public function test_name_accessor_returns_readable_label()
    {
        // ensure Slovak locale for translation lookup
        app()->setLocale('sk');

        $role = Role::factory()->create(['position' => 'manager']);
        $this->assertEquals('Manažér', $role->name);
        // if we want nicer formatting, apply additional transformation here
    }
}
