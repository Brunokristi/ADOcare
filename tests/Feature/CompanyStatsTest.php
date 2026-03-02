<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Branch;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyStatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_stats_returns_expected_counts()
    {
        $company = Company::factory()->create();
        // make branches/users/patients
        Branch::factory()->count(2)->create(['company_id' => $company->id]);
        User::factory()->count(3)->create(['company_id' => $company->id]);
        // patients associated via branches
        $branches = Branch::where('company_id', $company->id)->get();
        foreach ($branches as $b) {
            Patient::factory()->count(4)->create(['branch_id' => $b->id]);
        }

        // authenticate as a normal user (any role) since endpoint requires auth
        $user = User::factory()->create();
        $resp = $this->actingAs($user)->getJson("/api/v1/companies/{$company->id}/stats");
        $resp->assertStatus(200);

        $data = $resp->json('data');
        $this->assertEquals(2, $data['branches']);
        $this->assertEquals(3, $data['users']);
        $this->assertEquals(8, $data['patients']);
    }
}
