<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Filters\ApiQuery;
use App\Http\Resources\BaseCollection;
use App\Models\Document;
use App\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProposalDocumentController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'medical_diagnosis_ids' => 'nullable|array',
            'medical_diagnosis_ids.*' => 'nullable|exists:diagnoses,id',
            'nurse_diagnosis_ids' => 'nullable|array',
            'nurse_diagnosis_ids.*' => 'nullable|exists:nurse_diagnoses,id',
            'date' => 'required|date',
            'epicrisis_description' => 'required|string',
            'care_plan' => 'required|string',
            'patient_mobility' => 'nullable|array',
            'expected_duration' => 'required|string',
            'procedures' => 'nullable|array',
            'procedures.*.procedure_id' => 'nullable|exists:procedures,id',
            'procedures.*.frequency' => 'nullable|string',
        ]);

        $document = Document::create([
            'patient_id' => $validated['patient_id'],
            'user_id' => Auth::id(),
            'type' => 'proposal',
            'mime_type' => 'application/json',
            'name' => 'navrh_' . now()->format('d.m.Y'),
            'path' => 'proposals/' . 'navrh_' . now()->timestamp . '.json',
        ]);

        $patient = Patient::findOrFail($validated['patient_id']);
        $user = Auth::user();
        $company = $user->company()->first();
        $doctor = $patient->doctor;

        $companyName = $company ? $company->name : '';
        $companyAddress = $company ? $company->address : '';
        $patientName = $patient->first_name . ' ' . $patient->last_name . ' ' . $patient->title;
        $patientBirthNumber = $patient->personal_number;
        $patientAddress = $patient->address . ', ' . $patient->city . ', ' . $patient->postal_code;
        $insuranceCode = $patient->insuranceCompany->branch_code ?? '';
        $userName = $user->first_name . ' ' . $user->last_name . ' ' . $user->title;
        $doctorName = ($doctor->title ?? '') . ' ' . $doctor->first_name . ' ' . $doctor->last_name;

        $diagnoses = [];
        if (!empty($validated['medical_diagnosis_ids'])) {
            foreach ($validated['medical_diagnosis_ids'] as $diagnosisId) {
                if ($diagnosisId) {
                    $diagnosis = \DB::table('diagnoses')->find((int) $diagnosisId);
                    if ($diagnosis) {
                        $diagnoses[] = $diagnosis->code . ' - ' . $diagnosis->description;
                    }
                }
            }
        }

        $nurseDiagnoses = [];
        if (!empty($validated['nurse_diagnosis_ids'])) {
            foreach ($validated['nurse_diagnosis_ids'] as $nurseDiagnosisId) {
                if ($nurseDiagnosisId) {
                    $nurseDiagnosis = \DB::table('nurse_diagnoses')->find((int) $nurseDiagnosisId);
                    if ($nurseDiagnosis) {
                        $nurseDiagnoses[] = $nurseDiagnosis->code . ' - ' . $nurseDiagnosis->description;
                    }
                }
            }
        }

        $epicrisis = $validated['epicrisis_description'];
        $carePlan = $validated['care_plan'];
        $mobility = $validated['patient_mobility'] ?? [];
        $expectedDuration = $validated['expected_duration'];
        $date = $validated['date'];

        $procedures = [];
        if (!empty($validated['procedures'])) {
            foreach ($validated['procedures'] as $proc) {
                if (!empty($proc['procedure_id'])) {
                    $procedure = \DB::table('procedures')->find((int) $proc['procedure_id']);
                    $procedures[] = [
                        'code' => $procedure->code,
                        'frequency' => $proc['frequency'] ?? ''
                    ];
                }
            }
        }

        $proposalData = [
            'company_address' => $companyAddress,
            'company_name' => $companyName,
            'user_name' => $userName,
            'doctor_name' => $doctorName,
            'patient_name' => $patientName,
            'patient_birth_number' => $patientBirthNumber,
            'patient_address' => $patientAddress,
            'insurance_code' => $insuranceCode,
            'diagnosis' => $diagnoses,
            'nurse_diagnosis' => $nurseDiagnoses,
            'epicrisis' => $epicrisis,
            'care_plan' => $carePlan,
            'mobility' => $mobility,
            'expected_duration' => $expectedDuration,
            'date' => $date,
            'procedures' => $procedures,
            'document_id' => $document->id,
            'created_at' => now(),
        ];

        // Store the JSON data using local disk
        Storage::disk('local')->put(
            'proposals/' . now()->timestamp . '.json',
            json_encode($proposalData, JSON_PRETTY_PRINT)
        );

        return response()->json([
            'success' => true,
            'document_id' => $document->id,
            'message' => 'Návrh ošetrovateľskej starostlivosti bol úspešne vytvorený',
        ], 201);
    }

    public function show($documentId)
    {
        $documentId = (int) $documentId;
        $document = Document::with(['patient'])->findOrFail($documentId);

        $proposalFile = null;

        $files = Storage::disk('local')->files('proposals');
        foreach ($files as $file) {
            $content = json_decode(Storage::disk('local')->get($file), true);
            if ($content['document_id'] === $documentId) {
                $proposalFile = $content;
                break;
            }
        }

        if (!$proposalFile) {
            return response()->json(['message' => 'Proposal data not found'], 404);
        }

        $responseData = [
            'document' => $document,
            'proposal_data' => $proposalFile,
        ];

        return response()->json($responseData);
    }

    public function latestByPatient($patientId)
    {
        $patientId = (int) $patientId;

        $document = Document::where('patient_id', $patientId)
            ->where('type', 'proposal')
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$document) {
            return response()->json(['message' => 'No proposal found'], 404);
        }

        $proposalFile = null;
        $files = Storage::disk('local')->files('proposals');

        foreach ($files as $file) {
            $content = json_decode(Storage::disk('local')->get($file), true);
            if (($content['document_id'] ?? null) === $document->id) {
                $proposalFile = $content;
                break;
            }
        }

        if (!$proposalFile) {
            return response()->json(['message' => 'Proposal data not found'], 404);
        }

        return response()->json([
            'document_id' => $document->id,
            'proposal_data' => $proposalFile,
        ]);
    }
}
