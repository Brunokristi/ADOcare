<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use App\Models\User;
use App\Models\Company;
use App\Models\Branch;
use App\Models\Patient;
use App\Models\Visit;
use App\Services\DZCDocumentService;

class DZCDocumentTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_only_current_user_documents()
    {
        Storage::fake('local');

        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $other = User::factory()->create(['company_id' => $company->id]);

        $branch = Branch::factory()->create(['company_id' => $company->id, 'terrain_start_time' => '08:00:00']);

        $service = app(DZCDocumentService::class);
        [$doc1, $payload1] = $service->createDzc(['start' => now()->toDateString(), 'end' => now()->toDateString(), 'branch_id' => $branch->id], $user);
        [$doc2, $payload2] = $service->createDzc(['start' => now()->toDateString(), 'end' => now()->toDateString(), 'branch_id' => $branch->id], $other);

        $this->actingAs($user)
            ->getJson('/api/v1/dzcs')
            ->assertStatus(200)
            ->assertJsonPath('data.data.0.id', $doc1->id)
            ->assertJsonMissing(['data' => ['data' => [['id' => $doc2->id]]]]);
    }

    public function test_store_and_show_returns_expected_payload_with_visits_and_csv()
    {
        Storage::fake('local');

        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $branch = Branch::factory()->create(['company_id' => $company->id, 'terrain_start_time' => '08:00:00']);
        $user->branches()->attach($branch->id);

        $patient = Patient::factory()->create(['branch_id' => $branch->id]);

        // create visits within the date range for the user/branch
        $date = now()->toDateString();
        Visit::factory()->create([ 'user_id' => $user->id, 'branch_id' => $branch->id, 'patient_id' => $patient->id, 'date' => $date, 'terrain_time' => $date . ' 09:00:00', 'distance_to_location' => 1200 ]);
        // return-to-branch row (patient_id null)
        Visit::factory()->create([ 'user_id' => $user->id, 'branch_id' => $branch->id, 'patient_id' => null, 'date' => $date, 'administrative_time' => $date . ' 10:30:00', 'distance_to_location' => 5000 ]);

        $payload = ['start' => $date, 'end' => $date, 'branch_id' => $branch->id];

        $resp = $this->actingAs($user)->postJson('/api/v1/dzcs', $payload)->assertStatus(201)->json();

        $documentId = $resp['data']['document_id'];
        $this->assertDatabaseHas('documents', ['id' => $documentId, 'type' => 'dzc']);

        $this->actingAs($user)
            ->getJson("/api/v1/dzcs/{$documentId}")
            ->assertStatus(200)
            ->assertJsonPath('data.dzc_data.document_id', $documentId)
            ->assertJsonStructure(['data' => ['dzc_data' => ['patient_addresses', 'day_totals', 'month_totals']]]);

        // CSV export
        $this->actingAs($user)
            ->get("/api/v1/dzcs/{$documentId}/csv")
            ->assertStatus(200)
            ->assertSee('DENNÝ ZÁZNAM CIEST');
    }

    public function test_store_forbidden_for_user_outside_branch()
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();

        $user = User::factory()->create(['company_id' => $otherCompany->id]);
        $branch = Branch::factory()->create(['company_id' => $company->id]);

        $payload = ['start' => now()->toDateString(), 'end' => now()->toDateString(), 'branch_id' => $branch->id];

        $this->actingAs($user)
            ->postJson('/api/v1/dzcs', $payload)
            ->assertStatus(403);
    }
}
