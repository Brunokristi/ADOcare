<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use App\Models\User;
use App\Models\Company;
use App\Models\Branch;
use App\Models\Patient;
use App\Services\ProposalDocumentService;

class ProposalDocumentTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_proposal_succeeds_for_nurse_in_same_branch()
    {
        Storage::fake('local');

        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $branch = Branch::factory()->create(['company_id' => $company->id]);
        $patient = Patient::factory()->create(['branch_id' => $branch->id]);

        // ensure user is assigned to the branch (authorization requires isInBranch)
        $user->branches()->attach($branch->id);

        $payload = [
            'patient_id' => $patient->id,
            'date' => now()->toDateString(),
            'epicrisis_description' => 'E',
            'care_plan' => 'C',
            'expected_duration' => '2 weeks',
        ];

        $this->actingAs($user)
            ->postJson('/api/v1/proposals', $payload)
            ->assertStatus(201)
            ->assertJsonStructure(['message', 'data' => ['document_id', 'proposal']]);

        $this->assertDatabaseHas('documents', ['type' => 'proposal']);
    }

    public function test_store_proposal_forbidden_for_user_outside_branch()
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();

        $user = User::factory()->create(['company_id' => $otherCompany->id]);
        $branch = Branch::factory()->create(['company_id' => $company->id]);
        $patient = Patient::factory()->create(['branch_id' => $branch->id]);

        $payload = [
            'patient_id' => $patient->id,
            'date' => now()->toDateString(),
            'epicrisis_description' => 'E',
            'care_plan' => 'C',
            'expected_duration' => '2 weeks',
        ];

        $this->actingAs($user)
            ->postJson('/api/v1/proposals', $payload)
            ->assertStatus(403);
    }

    public function test_show_proposal_and_latest_by_patient()
    {
        Storage::fake('local');

        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $branch = Branch::factory()->create(['company_id' => $company->id]);
        $patient = Patient::factory()->create(['branch_id' => $branch->id]);

        $service = app(ProposalDocumentService::class);
        [$document, $payload] = $service->createProposal([
            'patient_id' => $patient->id,
            'date' => now()->toDateString(),
            'epicrisis_description' => 'E',
            'care_plan' => 'C',
            'expected_duration' => '2 weeks',
        ], $user);

        $this->actingAs($user)
            ->getJson("/api/v1/proposals/{$document->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.proposal_data.document_id', $document->id);

        $this->actingAs($user)
            ->getJson("/api/v1/patients/{$patient->id}/proposals/latest")
            ->assertStatus(200)
            ->assertJsonPath('data.proposal_data.document_id', $document->id);
    }
}
