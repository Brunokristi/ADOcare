<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AgreementDocumentController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'patient_id' => 'required|exists:patients,id',
        ]);

        $document = Document::create([
            'patient_id' => $validated['patient_id'],
            'user_id' => Auth::id(),
            'type' => 'agreement',
            'mime_type' => 'application/json',
            'name' => 'agreement_' . now()->format('d.m.Y'),
            'path' => 'agreements/' . 'agreement_' . now()->timestamp . '.json',
        ]);

        $patient = Patient::findOrFail($validated['patient_id']);
        $user = Auth::user();
        $company = $user->company;

        $companyName = $company ? $company->name : '';
        $companyAddress = $company ? $company->address : '';
        $companyCity = $company ? $company->city : '';
        $patientName = $patient->first_name . ' ' . $patient->last_name . ' ' . $patient->title;
        $patientBirthNumber = $patient->personal_number;
        $patientAddress = $patient->address . ', ' . $patient->city . ', ' . $patient->postal_code;
        $insuranceCode = $patient->insuranceCompany->branch_code ?? '';
        $userName = $user->title . ' ' . $user->first_name . ' ' . $user->last_name;
        $userContact = $user->phone;
        $date = $validated['date'];
        
        $agreementData = [
            'company_address' => $companyAddress,
            'company_name' => $companyName,
            'company_city' => $companyCity,
            'user_name' => $userName,
            'user_contact' => $userContact,
            'patient_name' => $patientName,
            'patient_birth_number' => $patientBirthNumber,
            'patient_address' => $patientAddress,
            'date' => $date,
            'document_id' => $document->id,
            'created_at' => now(),
        ];

        // Store the JSON data
        $storagePath = storage_path('app/private/agreements');
        if (!is_dir($storagePath)) {
            mkdir($storagePath, 0755, true);
        }

        file_put_contents(
            $storagePath . '/' . now()->timestamp . '.json',
            json_encode($agreementData, JSON_PRETTY_PRINT)
        );
         
        return response()->json([
            'success' => true,
            'document_id' => $document->id,
            'message' => 'Dohoda o poskytovaní zdravotnej starostlivosti bola úspešne vytvorená',
        ], 201);
    }

    public function show($documentId)
    {
        $documentId = (int) $documentId;
        $document = Document::with(['patient'])->findOrFail($documentId);

        $storagePath = storage_path('app/private/agreements');
        $agreementFile = null;

        $files = glob($storagePath . '/*.json');
        foreach ($files as $file) {
            $content = json_decode(file_get_contents($file), true);
            if ($content['document_id'] === $documentId) {
                $agreementFile = $content;
                break;
            }
        }

        if (!$agreementFile) {
            return response()->json(['message' => 'Agreement data not found'], 404);
        }

        $responseData = [
            'document' => $document,
            'agreement_data' => $agreementFile,
        ];

        return response()->json($responseData);
    }


    public function getByPatient($patientId)
    {
        $patient = Patient::findOrFail($patientId);

        $documents = Document::where('patient_id', $patientId)
            ->where('type', 'agreement')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($documents);
    }
}
