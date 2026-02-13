<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Patient;
use App\Models\PatientPoint;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DekurzDocumentTest extends TestCase
{
    use RefreshDatabase;

    public function test_available_dates_returns_dates_and_days()
    {
        $user = User::factory()->create();
        $patient = Patient::factory()->create();

        // create patient points within month
        PatientPoint::create([
            'patient_id' => $patient->id,
            'date' => '2026-02-05',
            'procedure_code' => '3439',
        ]);

        PatientPoint::create([
            'patient_id' => $patient->id,
            'date' => '2026-02-12',
            'procedure_code' => '3440',
        ]);

        $resp = $this->actingAs($user)->getJson('/api/v1/dekurz/available-dates?patient_id=' . $patient->id . '&month=2026-02-01');
        $resp->assertStatus(200)->assertJsonPath('data.month_from', '2026-02-01');
        $this->assertCount(2, $resp->json('data.dates'));
        $this->assertEquals([5, 12], $resp->json('data.days'));
    }

    public function test_last_returns_null_when_no_dekurz()
    {
        $user = User::factory()->create();
        $patient = Patient::factory()->create();

        $resp = $this->actingAs($user)->getJson('/api/v1/dekurz/last?patient_id=' . $patient->id);
        $resp->assertStatus(200)->assertJson(['success' => true, 'data' => null]);
    }

    public function test_last_returns_dekurz_sections()
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $patient = Patient::factory()->create();

        $document = Document::create([
            'patient_id' => $patient->id,
            'user_id' => $user->id,
            'type' => 'dekurz',
            'mime_type' => 'application/json',
            'name' => 'dekurz',
            'path' => 'dekurz/test.json',
        ]);

        $payload = ['document_id' => $document->id, 'sections' => [['text' => 'a', 'dates' => ['2026-02-05']]]];
        Storage::disk('local')->put('dekurz/test.json', json_encode($payload));

        $resp = $this->actingAs($user)->getJson('/api/v1/dekurz/last?patient_id=' . $patient->id);
        $resp->assertStatus(200)->assertJsonPath('data.document_id', $document->id);
        $this->assertEquals([['text' => 'a', 'dates' => ['2026-02-05']]], $resp->json('data.sections'));
    }
}
