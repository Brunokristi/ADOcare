<?php

namespace Tests\Feature;

use App\Enums\RoleScope;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiQueryScopingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Managers should only see doctors associated with their company even if
     * they ask for eager-loaded relationships.  The ApiQuery layer should
     * automatically apply the correct company scope both to the root doctor
     * query and to the relations.
     */
    public function test_doctors_are_scoped_to_managers_company_when_loading()
    {
        $company1 = Company::factory()->create();
        $company2 = Company::factory()->create();

        $branch1 = Branch::factory()->create(['company_id' => $company1->id]);
        $branch2 = Branch::factory()->create(['company_id' => $company2->id]);

        $doc1 = Doctor::factory()->create();
        $doc2 = Doctor::factory()->create();

        // assign patients to create the assigned_branches relationship
        Patient::factory()->create([
            'doctor_id' => $doc1->id,
            'branch_id' => $branch1->id,
        ]);
        Patient::factory()->create([
            'doctor_id' => $doc2->id,
            'branch_id' => $branch2->id,
        ]);

        $role = Role::factory()->create(["scope" => RoleScope::COMPANY]);
        $user = User::factory()->create(['company_id' => $company1->id, 'role_id' => $role->id]);
        $this->actingAs($user);

        $resp = $this->getJson('/api/v1/my-company/doctors?with=assigned_branches,assigned_patients');
        $resp->assertOk();

        // the ApiResponse wraps results under `data.items`
        $data = $resp->json('data.items');
        // results are now scoped at root level as well; only doc1 should
        // remain.
        $this->assertCount(1, $data);
        $this->assertEquals($doc1->id, $data[0]['id']);
        $this->assertCount(1, $data[0]['assigned_branches']);
        $this->assertEquals($branch1->id, $data[0]['assigned_branches'][0]['id']);
    }

    /**
     * The Patient model does not carry a company_id column; the scope should
     * still function by joining through the branch relationship.
     */
    public function test_patient_scope_uses_branch_relation_when_no_column()
    {
        $company = Company::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id]);
        $company2 = Company::factory()->create();
        $other = Branch::factory()->create(['company_id' => $company2->id]);

        $p1 = Patient::factory()->create(['branch_id' => $branch->id]);
        $p2 = Patient::factory()->create(['branch_id' => $other->id]);

        $this->assertCount(1, Patient::query()->forCompany($company->id)->get());
        $this->assertTrue(Patient::query()->forCompany($company->id)->pluck('id')->contains($p1->id));
        $this->assertFalse(Patient::query()->forCompany($company->id)->pluck('id')->contains($p2->id));
    }

    /**
     * ensure that when the client requests counts the scoping is applied
     * as well.  this covers the `withCount` branch of ApiQuery.
     */
    public function test_withcount_respects_scope()
    {
        $company = Company::factory()->create();
        $branch1 = Branch::factory()->create(['company_id' => $company->id]);
        $company2 = Company::factory()->create();
        $branch2 = Branch::factory()->create(['company_id' => $company2->id]);

        $doc1 = Doctor::factory()->create();
        $doc2 = Doctor::factory()->create();

        Patient::factory()->create(['doctor_id' => $doc1->id, 'branch_id' => $branch1->id]);
        Patient::factory()->create(['doctor_id' => $doc1->id, 'branch_id' => $branch2->id]);

        $role = Role::factory()->create(['scope' => RoleScope::COMPANY]);
        $user = User::factory()->create(['company_id' => $company->id, 'role_id' => $role->id]);
        $this->actingAs($user);

        // count assigned_branches and assigned_patients; only branches from
        // the current company should be counted
        $resp = $this->getJson(
            '/api/v1/my-company/doctors?with=assigned_branches&count=assigned_branches,assigned_patients'
        );
        $resp->assertOk();
        $items = $resp->json('data.items');

        $byId = collect($items)->keyBy('id');

        $this->assertEquals(1, $byId[$doc1->id]['assigned_branches_count']);
        // only one of the two patients belongs to a branch of our company
        $this->assertEquals(1, $byId[$doc1->id]['assigned_patients_count']);
    }
}
