<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class RecordDocumentController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'record_data' => 'required|array',
        ]);

        $document = Document::create([
            'patient_id' => $validated['patient_id'],
            'user_id' => Auth::id(),
            'type' => 'record',
            'mime_type' => 'application/json',
            'name' => 'zaznam_prijatia_' . now()->format('d.m.Y'),
            'path' => 'records/' . 'record_' . now()->timestamp . '.json',
        ]);

        $patient = Patient::findOrFail($validated['patient_id']);
        $user = Auth::user();
        $company = $user->companies()->first();
        $doctor = $patient->doctor;

        $companyName = $company ? $company->name : '';
        $companyAddress = $company ? $company->address : '';
        $patientName = $patient->title . ' ' . $patient->first_name . ' ' . $patient->last_name;
        $patientBirthNumber = $patient->personal_number;
        $patientContact = $patient->contact;
        $patientAddress = $patient->address . ', ' . $patient->city . ', ' . $patient->postal_code;
        $insuranceCode = $patient->insuranceCompany->branch_code ?? '';
        $userName = $user->title . ' ' . $user->first_name . ' ' . $user->last_name;
        $doctorName = ($doctor->title ?? '') . ' ' . $doctor->first_name . ' ' . $doctor->last_name;

        // Process diagnosis from record_data
        $diagnosis = null;
        if (!empty($validated['record_data']['diagnosis']) && is_array($validated['record_data']['diagnosis'])) {
            $diagnosisData = $validated['record_data']['diagnosis'];
            if (!empty($diagnosisData['id'])) {
                $diagnosisRecord = \DB::table('diagnoses')->find((int) $diagnosisData['id']);
                $diagnosis = ($diagnosisRecord) ? $diagnosisRecord->code . ' - ' . $diagnosisRecord->description : null;
            }
        }

        // Process nursing diagnoses from record_data (now an array)
        $nurseDiagnoses = [];
        if (!empty($validated['record_data']['nursingDiagnoses']['list']) && is_array($validated['record_data']['nursingDiagnoses']['list'])) {
            foreach ($validated['record_data']['nursingDiagnoses']['list'] as $nd) {
                if (!empty($nd['id'])) {
                    $nurseDiagnosisRecord = \DB::table('nurse_diagnoses')->find((int) $nd['id']);
                    if ($nurseDiagnosisRecord) {
                        $nurseDiagnoses[] = $nurseDiagnosisRecord->code . ' - ' . $nurseDiagnosisRecord->description;
                    }
                }
            }
        }

        // Create a modified copy of record data with processed diagnoses
        $processedRecordData = $validated['record_data'];
        $processedRecordData['diagnosis'] = $diagnosis;
        $processedRecordData['nursingDiagnoses']['list'] = $nurseDiagnoses;

        $recordData = [
            'company_address' => $companyAddress,
            'company_name' => $companyName,
            'user_name' => $userName,
            'doctor_name' => $doctorName,
            'patient_name' => $patientName,
            'patient_birth_number' => $patientBirthNumber,
            'patient_address' => $patientAddress,
            'patient_contact' => $patientContact,
            'insurance_code' => $insuranceCode,
            'form_data' => $processedRecordData,
            'document_id' => $document->id,
            'created_at' => now(),
        ];

        // Store the JSON data using local disk
        Storage::disk('local')->put(
            'records/' . now()->timestamp . '.json',
            json_encode($recordData, JSON_PRETTY_PRINT)
        );
        
        return response()->json([
            'success' => true,
            'document_id' => $document->id,
            'message' => 'Záznam prijatia bol úspešne vytvorený',
        ], 201);
    }

    public function show($documentId)
    {
        $documentId = (int) $documentId;
        $document = Document::with(['patient'])->findOrFail($documentId);

        $recordFile = null;

        $files = Storage::disk('local')->files('records');
        foreach ($files as $file) {
            $content = json_decode(Storage::disk('local')->get($file), true);
            if ($content['document_id'] === $documentId) {
                $recordFile = $content;
                break;
            }
        }

        if (!$recordFile) {
            return response()->json(['message' => 'Record data not found'], 404);
        }

        $responseData = [
            'document' => $document,
            'record_data' => $recordFile,
        ];

        return response()->json($responseData);
    }

    public function latestByPatient($patientId)
    {
        $patientId = (int) $patientId;

        $document = Document::where('patient_id', $patientId)
            ->where('type', 'record')
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$document) {
            return response()->json(['message' => 'No record found'], 404);
        }

        $recordFile = null;
        $files = Storage::disk('local')->files('records');

        foreach ($files as $file) {
            $content = json_decode(Storage::disk('local')->get($file), true);
            if (($content['document_id'] ?? null) === $document->id) {
                $recordFile = $content;
                break;
            }
        }

        if (!$recordFile) {
            return response()->json(['message' => 'Record data not found'], 404);
        }

        return response()->json([
            'document_id' => $document->id,
            'record_data' => $recordFile,
        ]);
    }
}
