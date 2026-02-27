<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperadminStatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_can_retrieve_statistics()
    {
        // prepare some data
        // ensure the superadmin role exists (RefreshDatabase does not run seeders)
        \App\Models\Role::create(['position' => 'superadmin']);
        $admin = User::factory()->create();
        $admin->assignRole('superadmin');

        Company::factory()->count(2)->create();
        Branch::factory()->count(3)->create();
        // create a few extra users so total > 1
        User::factory()->count(4)->create();
        // we don't bother with patients/doctors/documents here since factories
        // are not available in the test suite and they are not required for
        // the basic assertion that stats endpoint returns a structure.

        $resp = $this->actingAs($admin)->getJson('/api/v1/superadmin/statistics');
        $resp->assertStatus(200);

        $resp->assertJsonStructure([
            'data' => [
                'companies',
                'branches',
                'users',
                'patients',
                'doctors',
                'documents',
            ],
        ]);

        $data = $resp->json('data');
        $this->assertEquals(2, $data['companies']);
        $this->assertEquals(3, $data['branches']);
        // users count should be at least the ones we created (+ superadmin)
        $this->assertGreaterThanOrEqual(5, $data['users']);
    }
}
