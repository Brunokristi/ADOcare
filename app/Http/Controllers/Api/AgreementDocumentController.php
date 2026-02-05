<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Filters\ApiQuery;
use App\Http\Resources\BaseCollection;
use App\Models\Document;
use App\Models\Patient;
use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;


class AgreementDocumentController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'patient_id' => 'required|exists:patients,id',
            'branch_id' => 'required|exists:branches,id',
        ]);

        $document = Document::create([
            'patient_id' => $validated['patient_id'],
            'user_id' => Auth::id(),
            'type' => 'agreement',
            'mime_type' => 'application/json',
            'name' => 'dohoda_' . now()->format('d.m.Y'),
            'path' => 'agreements/' . '' . now()->timestamp . '.json',
        ]);

        $patient = Patient::findOrFail($validated['patient_id']);
        $user = Auth::user();
        $branch = Branch::findOrFail((int)$validated['branch_id']);
        $company = $branch->company;
        $user = $branch->representative;

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

        // Store the JSON data using local disk
        Storage::disk('local')->put(
            'agreements/' . now()->timestamp . '.json',
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

        $agreementFile = null;

        $files = Storage::disk('local')->files('agreements');
        foreach ($files as $file) {
            $content = json_decode(Storage::disk('local')->get($file), true);
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
}
