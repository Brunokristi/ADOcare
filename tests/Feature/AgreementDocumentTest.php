<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use App\Models\User;
use App\Models\Company;
use App\Models\Branch;
use App\Models\Patient;
use App\Services\AgreementDocumentService;

class AgreementDocumentTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_agreement_succeeds_for_authorized_user()
    {
        Storage::fake('local');

        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $branch = Branch::factory()->create(['company_id' => $company->id]);
        $patient = Patient::factory()->create(['branch_id' => $branch->id]);

        $payload = [
            'date' => now()->toDateString(),
            'patient_id' => $patient->id,
            'branch_id' => $branch->id,
        ];

        $this->actingAs($user)
            ->postJson('/api/v1/agreements', $payload)
            ->assertStatus(201)
            ->assertJsonStructure(['message', 'data' => ['document_id', 'agreement']]);

        // file should have been stored
        $this->assertDatabaseHas('documents', ['type' => 'agreement']);
    }

    public function test_store_agreement_forbidden_for_user_outside_company()
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();

        $user = User::factory()->create(['company_id' => $otherCompany->id]);
        $branch = Branch::factory()->create(['company_id' => $company->id]);
        $patient = Patient::factory()->create(['branch_id' => $branch->id]);

        $payload = [
            'date' => now()->toDateString(),
            'patient_id' => $patient->id,
            'branch_id' => $branch->id,
        ];

        $this->actingAs($user)
            ->postJson('/api/v1/agreements', $payload)
            ->assertStatus(403);
    }

    public function test_show_agreement_returns_payload()
    {
        Storage::fake('local');

        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $branch = Branch::factory()->create(['company_id' => $company->id]);
        $patient = Patient::factory()->create(['branch_id' => $branch->id]);

        $service = app(AgreementDocumentService::class);
        [$document, $agreementPayload] = $service->createAgreement([
            'date' => now()->toDateString(),
            'patient_id' => $patient->id,
            'branch_id' => $branch->id,
        ], $user);

        $this->actingAs($user)
            ->getJson("/api/v1/agreements/{$document->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.agreement_data.document_id', $document->id)
            ->assertJsonPath('data.agreement_data.patient_name', $agreementPayload['patient_name']);
    }
}
