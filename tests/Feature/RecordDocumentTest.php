<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RecordDocumentTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_creates_document_and_writes_file()
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $patient = Patient::factory()->create();

        $payload = [
            'patient_id' => $patient->id,
            'record_data' => ['notes' => 'test', 'nursingDiagnoses' => ['list' => []]],
        ];

        $resp = $this->actingAs($user)->postJson('/api/v1/records', $payload);
        $resp->assertStatus(201)->assertJson(['success' => true]);

        $this->assertDatabaseHas('documents', ['patient_id' => $patient->id, 'type' => 'record']);

        // there should be at least one file in records/ on the local disk
        $this->assertNotEmpty(Storage::disk('local')->files('records'));
    }

    public function test_show_returns_record_data_for_document()
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $patient = Patient::factory()->create();

        $document = Document::create([
            'patient_id' => $patient->id,
            'user_id' => $user->id,
            'type' => 'record',
            'mime_type' => 'application/json',
            'name' => 'zaznam',
            'path' => 'records/test.json',
        ]);

        $file = ['document_id' => $document->id, 'form_data' => ['notes' => 'hello']];
        Storage::disk('local')->put('records/test.json', json_encode($file));

        $resp = $this->actingAs($user)->getJson('/api/v1/records/' . $document->id);
        $resp->assertStatus(200)->assertJsonPath('record_data.document_id', $document->id);
    }

    public function test_latest_by_patient_returns_latest_record()
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $patient = Patient::factory()->create();

        $first = Document::create([
            'patient_id' => $patient->id,
            'user_id' => $user->id,
            'type' => 'record',
            'mime_type' => 'application/json',
            'name' => 'z1',
            'path' => 'records/one.json',
        ]);
        $first->update(['created_at' => now()->subDay()]);

        $second = Document::create([
            'patient_id' => $patient->id,
            'user_id' => $user->id,
            'type' => 'record',
            'mime_type' => 'application/json',
            'name' => 'z2',
            'path' => 'records/two.json',
        ]);
        $second->update(['created_at' => now()]);

        Storage::disk('local')->put('records/one.json', json_encode(['document_id' => $first->id]));
        Storage::disk('local')->put('records/two.json', json_encode(['document_id' => $second->id]));

        $resp = $this->actingAs($user)->getJson('/api/v1/patients/' . $patient->id . '/records/latest');
        $resp->assertStatus(200)->assertJsonPath('document_id', $second->id);
    }
}
