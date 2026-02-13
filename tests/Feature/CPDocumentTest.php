<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use App\Models\User;
use App\Models\Company;
use App\Models\Branch;
use App\Services\CPDocumentService;

class CPDocumentTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_only_current_user_documents()
    {
        Storage::fake('local');

        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $other = User::factory()->create(['company_id' => $company->id]);

        $branch = Branch::factory()->create(['company_id' => $company->id]);

        $service = app(CPDocumentService::class);
        [$doc1, $payload1] = $service->createCp(['start' => now()->toDateString(), 'end' => now()->addDay()->toDateString(), 'branch_id' => $branch->id], $user);
        [$doc2, $payload2] = $service->createCp(['start' => now()->toDateString(), 'end' => now()->addDay()->toDateString(), 'branch_id' => $branch->id], $other);

        $this->actingAs($user)
            ->getJson('/api/v1/cps')
            ->assertStatus(200)
            ->assertJsonPath('data.data.0.id', $doc1->id)
            ->assertJsonMissing(['data' => ['data' => [['id' => $doc2->id]]]]);
    }

    public function test_store_cp_succeeds_for_user_in_branch()
    {
        Storage::fake('local');

        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $branch = Branch::factory()->create(['company_id' => $company->id]);
        $user->branches()->attach($branch->id);

        $payload = ['start' => now()->toDateString(), 'end' => now()->addDay()->toDateString(), 'branch_id' => $branch->id];

        $this->actingAs($user)
            ->postJson('/api/v1/cps', $payload)
            ->assertStatus(201)
            ->assertJsonStructure(['message', 'data' => ['document_id', 'cp']]);

        $this->assertDatabaseHas('documents', ['type' => 'cp', 'branch_id' => $branch->id]);
    }

    public function test_store_cp_forbidden_for_user_outside_branch()
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();

        $user = User::factory()->create(['company_id' => $otherCompany->id]);
        $branch = Branch::factory()->create(['company_id' => $company->id]);

        $payload = ['start' => now()->toDateString(), 'end' => now()->addDay()->toDateString(), 'branch_id' => $branch->id];

        $this->actingAs($user)
            ->postJson('/api/v1/cps', $payload)
            ->assertStatus(403);
    }

    public function test_show_cp_returns_payload()
    {
        Storage::fake('local');

        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $branch = Branch::factory()->create(['company_id' => $company->id]);
        $user->branches()->attach($branch->id);

        $service = app(CPDocumentService::class);
        [$document, $payload] = $service->createCp(['start' => now()->toDateString(), 'end' => now()->addDay()->toDateString(), 'branch_id' => $branch->id], $user);

        $this->actingAs($user)
            ->getJson("/api/v1/cps/{$document->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.cp_data.document_id', $document->id)
            ->assertJsonPath('data.cp_data.user_id', $user->id);
    }
}
