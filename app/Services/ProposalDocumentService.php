<?php

namespace App\Services;

use App\Models\Document;
use App\Models\Patient;
use App\Models\Procedure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProposalDocumentService
{
    /**
     * Create proposal document and persist JSON payload.
     * @return array [Document, array(payload)]
     */
    public function createProposal(array $data, $actor): array
    {
        $patient = Patient::findOrFail($data['patient_id']);

        $document = Document::create([
            'patient_id' => $patient->id,
            'user_id' => $actor->id,
            'type' => 'proposal',
            'mime_type' => 'application/json',
            'name' => 'navrh_' . now()->format('d.m.Y'),
            'path' => 'proposals/navrh_' . now()->timestamp . '.json',
        ]);

        $company = $actor->company;
        $doctor = $patient->doctor;

        $diagnosis = [];
        if (!empty($data['medical_diagnosis_ids']) && is_array($data['medical_diagnosis_ids'])) {
            foreach ($data['medical_diagnosis_ids'] as $diagId) {
                $d = DB::table('diagnoses')->find((int) $diagId);
                if ($d) {
                    $diagnosis[] = $d->code . ' - ' . $d->description;
                }
            }
        }

        $nurseDiagnosis = [];
        if (!empty($data['nurse_diagnosis_ids']) && is_array($data['nurse_diagnosis_ids'])) {
            foreach ($data['nurse_diagnosis_ids'] as $nurseDiagId) {
                $nd = DB::table('nurse_diagnoses')->find((int) $nurseDiagId);
                if ($nd) {
                    $nurseDiagnosis[] = $nd->code . ' - ' . $nd->description;
                }
            }
        }

        $procedures = [];
        if (!empty($data['procedures']) && is_array($data['procedures'])) {
            foreach ($data['procedures'] as $procEntry) {
                if (empty($procEntry['procedure_id'])) continue;
                $proc = DB::table('procedures')->find((int) $procEntry['procedure_id']);
                if (! $proc) continue;
                $procedures[] = [
                    'code' => $proc->code,
                    'frequency' => $procEntry['frequency'] ?? '',
                ];
            }
        }

        $payload = [
            'company_address' => $company?->address ?? '',
            'company_name' => $company?->name ?? '',
            'user_name' => trim(($actor->first_name ?? '') . ' ' . ($actor->last_name ?? '') . ' ' . ($actor->title ?? '')),
            'doctor_name' => trim(($doctor->title ?? '') . ' ' . ($doctor->first_name ?? '') . ' ' . ($doctor->last_name ?? '')),
            'patient_name' => trim(($patient->first_name ?? '') . ' ' . ($patient->last_name ?? '') . ' ' . ($patient->title ?? '')),
            'patient_birth_number' => $patient->personal_number ?? '',
            'patient_address' => trim(($patient->address ?? '') . ', ' . ($patient->city ?? '') . ', ' . ($patient->postal_code ?? '')),
            'insurance_code' => $patient->insuranceCompany->branch_code ?? '',
            'diagnosis' => $diagnosis,
            'nurse_diagnosis' => $nurseDiagnosis,
            'epicrisis' => $data['epicrisis_description'] ?? '',
            'care_plan' => $data['care_plan'] ?? '',
            'mobility' => $data['patient_mobility'] ?? [],
            'expected_duration' => $data['expected_duration'] ?? '',
            'date' => $data['date'] ?? null,
            'procedures' => $procedures,
            'document_id' => $document->id,
            'created_at' => now(),
        ];

        Storage::disk('local')->put($document->path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return [$document, $payload];
    }

    public function getProposalPayload(Document $document): ?array
    {
        if (! $document->path || ! Storage::disk('local')->exists($document->path)) return null;
        $content = Storage::disk('local')->get($document->path);
        return json_decode($content, true);
    }
}
