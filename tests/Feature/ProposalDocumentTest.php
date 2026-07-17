<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use App\Models\User;
use App\Models\Company;
use App\Models\Branch;
use App\Models\Document;
use App\Models\Patient;
use App\Services\ProposalDocumentService;

class ProposalDocumentTest extends TestCase
{
    use RefreshDatabase;

    private function createPrefillContext(): array
    {
        Storage::fake('local');

        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $branch = Branch::factory()->create(['company_id' => $company->id]);
        $patient = Patient::factory()->create([
            'branch_id' => $branch->id,
            'nurse_id' => $user->id,
        ]);

        $user->branches()->attach($branch->id);

        $scanPath = 'scans/patient-' . $patient->id . '-latest.json';
        Storage::disk('local')->put($scanPath, json_encode([
            'extracted_text' => 'Pacient je imobilny. Diagnoza I10. Osetrovatelsky plan denne prevazy.',
            'scanned_at' => now()->toIso8601String(),
        ], JSON_UNESCAPED_UNICODE));

        $document = Document::query()->create([
            'patient_id' => $patient->id,
            'user_id' => $user->id,
            'type' => 'scan',
            'mime_type' => 'application/json',
            'name' => 'scan.json',
            'path' => $scanPath,
            'branch_id' => $branch->id,
        ]);

        return [$user, $patient, $document];
    }

    private function createVertexCredentialsFile(): string
    {
        $privateKeyResource = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'private_key_bits' => 2048,
        ]);

        if ($privateKeyResource === false) {
            $this->fail('Nepodarilo sa vytvoriť testovací private key pre Vertex credentials.');
        }

        $privateKey = '';
        openssl_pkey_export($privateKeyResource, $privateKey);

        $credentialsPath = 'vertex/test-service-account.json';
        Storage::disk('local')->put($credentialsPath, json_encode([
            'type' => 'service_account',
            'client_email' => 'vertex-test@acodare.iam.gserviceaccount.com',
            'private_key' => $privateKey,
        ], JSON_UNESCAPED_UNICODE));

        return Storage::disk('local')->path($credentialsPath);
    }

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

    public function test_ocr_prefill_from_latest_scan_succeeds_with_vertex_ai()
    {
        [$user, $patient] = $this->createPrefillContext();
        $credentialsPath = $this->createVertexCredentialsFile();

        config()->set('services.vertex_ai.credentials_path', $credentialsPath);
        config()->set('services.vertex_ai.project_id', 'acodare-test-project');
        config()->set('services.vertex_ai.general_location', 'global');
        config()->set('services.vertex_ai.general_model', 'gemini-2.0-flash');

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'vertex-token',
            ], 200),
            'https://*-aiplatform.googleapis.com/v1/projects/*/locations/*/publishers/google/models/*:generateContent' => Http::response([
                'candidates' => [[
                    'content' => [
                        'parts' => [[
                            'text' => json_encode([
                                'medical_diagnosis_codes' => ['I10'],
                                'nurse_diagnosis_codes' => ['00085'],
                                'epicrisis_description' => 'Pacient je imobilný.',
                                'care_plan' => 'Denné preväzy a monitoring.',
                                'patient_mobility' => ['I'],
                                'expected_duration' => 'three_months',
                                'procedures' => [
                                    ['code' => '00001', 'frequency' => 'daily'],
                                ],
                            ], JSON_UNESCAPED_UNICODE),
                        ]],
                    ],
                ]],
            ], 200),
        ]);

        $this->actingAs($user)
            ->postJson("/api/v1/patients/{$patient->id}/proposals/ocr-prefill")
            ->assertStatus(200)
            ->assertJsonPath('data.prefill.medical_diagnosis_codes.0', 'I10')
            ->assertJsonPath('data.prefill.patient_mobility.0', 'I')
            ->assertJsonPath('data.prefill.expected_duration', 'three_months');
    }

    public function test_ocr_prefill_from_latest_scan_fails_when_vertex_credentials_are_missing()
    {
        [$user, $patient] = $this->createPrefillContext();

        config()->set('services.vertex_ai.credentials_path', '');
        config()->set('services.vertex_ai.project_id', 'acodare-test-project');
        config()->set('services.vertex_ai.general_location', 'global');
        config()->set('services.vertex_ai.general_model', 'gemini-2.0-flash');

        $this->actingAs($user)
            ->postJson("/api/v1/patients/{$patient->id}/proposals/ocr-prefill")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Chýba nastavenie GOOGLE_APPLICATION_CREDENTIALS pre Vertex AI.');
    }

    public function test_ocr_prefill_from_latest_scan_fails_when_vertex_project_id_is_missing()
    {
        [$user, $patient] = $this->createPrefillContext();
        $credentialsPath = $this->createVertexCredentialsFile();

        config()->set('services.vertex_ai.credentials_path', $credentialsPath);
        config()->set('services.vertex_ai.project_id', '');
        config()->set('services.vertex_ai.general_location', 'global');
        config()->set('services.vertex_ai.general_model', 'gemini-2.0-flash');

        $this->actingAs($user)
            ->postJson("/api/v1/patients/{$patient->id}/proposals/ocr-prefill")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Chýba nastavenie VERTEX_PROJECT_ID.');
    }

    public function test_ocr_prefill_from_latest_scan_returns_422_when_vertex_api_fails()
    {
        [$user, $patient] = $this->createPrefillContext();
        $credentialsPath = $this->createVertexCredentialsFile();

        config()->set('services.vertex_ai.credentials_path', $credentialsPath);
        config()->set('services.vertex_ai.project_id', 'acodare-test-project');
        config()->set('services.vertex_ai.general_location', 'global');
        config()->set('services.vertex_ai.general_model', 'gemini-2.0-flash');

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'vertex-token',
            ], 200),
            'https://*-aiplatform.googleapis.com/v1/projects/*/locations/*/publishers/google/models/*:generateContent' => Http::response([
                'error' => ['message' => 'Model unavailable'],
            ], 500),
        ]);

        $this->actingAs($user)
            ->postJson("/api/v1/patients/{$patient->id}/proposals/ocr-prefill")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Vertex AI vrátila chybu. HTTP 500. {"error":{"message":"Model unavailable"}}');
    }

    public function test_ocr_prefill_from_latest_scan_returns_422_when_vertex_returns_invalid_json_payload()
    {
        [$user, $patient] = $this->createPrefillContext();
        $credentialsPath = $this->createVertexCredentialsFile();

        config()->set('services.vertex_ai.credentials_path', $credentialsPath);
        config()->set('services.vertex_ai.project_id', 'acodare-test-project');
        config()->set('services.vertex_ai.general_location', 'global');
        config()->set('services.vertex_ai.general_model', 'gemini-2.0-flash');

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'vertex-token',
            ], 200),
            'https://*-aiplatform.googleapis.com/v1/projects/*/locations/*/publishers/google/models/*:generateContent' => Http::response([
                'candidates' => [[
                    'content' => [
                        'parts' => [[
                            'text' => 'this-is-not-valid-json',
                        ]],
                    ],
                ]],
            ], 200),
        ]);

        $this->actingAs($user)
            ->postJson("/api/v1/patients/{$patient->id}/proposals/ocr-prefill")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Vertex AI vrátila neplatný JSON.');
    }
}
