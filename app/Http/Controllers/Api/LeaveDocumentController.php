<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class LeaveDocumentController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'date' => 'required|string',
            'problems' => 'nullable|array',
            'other_findings' => 'nullable|string',
            'results' => 'nullable|string',
            'education' => 'nullable|string',
            'received' => 'nullable|string',
        ]);

        $document = Document::create([
            'patient_id' => $validated['patient_id'],
            'user_id' => Auth::id(),
            'type' => 'leave',
            'mime_type' => 'application/json',
            'name' => 'prepustacia_sprava_' . now()->format('d.m.Y'),
            'path' => 'leave/' . now()->timestamp . '.json',
            'period' => date('Y-m', strtotime($validated['date'])),
        ]);

        $patient = Patient::findOrFail($validated['patient_id']);
        $user = Auth::user();

        $patientName = $patient->title . ' ' . $patient->first_name . ' ' . $patient->last_name;
        $patientBirthNumber = $patient->personal_number;
        $userName = $user->title . ' ' . $user->first_name . ' ' . $user->last_name;

        $nursingData = [
            'user_name' => $userName,
            'patient_name' => $patientName,
            'patient_birth_number' => $patientBirthNumber,
            'date' => $validated['date'],
            'problems' => $validated['problems'] ?? [],
            'other_findings' => $validated['other_findings'] ?? '',
            'results' => $validated['results'],
            'education' => $validated['education'],
            'received' => $validated['received'],
            'document_id' => $document->id,
            'created_at' => now(),
        ];

        Storage::disk('local')->put(
            'leave/' . now()->timestamp . '.json',
            json_encode($nursingData, JSON_PRETTY_PRINT)
        );
        
        return response()->json([
            'success' => true,
            'document_id' => $document->id,
            'message' => 'Ošetrovateľský dokument bol úspešne vytvorený',
        ], 201);
    }

    public function show($documentId)
    {
        $documentId = (int) $documentId;
        $document = Document::with(['patient'])->findOrFail($documentId);

        $leaveFile = null;

        $files = Storage::disk('local')->files('leave');
        foreach ($files as $file) {
            $content = json_decode(Storage::disk('local')->get($file), true);
            if ($content['document_id'] === $documentId) {
                $leaveFile = $content;
                break;
            }
        }

        if (!$leaveFile) {
            return response()->json(['message' => 'Leave document data not found'], 404);
        }

        $responseData = [
            'document' => $document,
            'leave_data' => $leaveFile,
        ];

        return response()->json($responseData);
    }

    public function latestByPatient($patientId)
    {
        $patientId = (int) $patientId;

        $document = Document::where('patient_id', $patientId)
            ->where('type', 'leave')
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$document) {
            return response()->json(['message' => 'No leave document found'], 404);
        }

        $leaveFile = null;
        $files = Storage::disk('local')->files('leave');
        foreach ($files as $file) {
            $content = json_decode(Storage::disk('local')->get($file), true);
            if (($content['document_id'] ?? null) === $document->id) {
                $nursingFile = $content;
                break;
            }
        }

        if (!$nursingFile) {
            return response()->json(['message' => 'Nursing document data not found'], 404);
        }

        return response()->json([
            'document_id' => $document->id,
            'nursing_data' => $nursingFile,
        ]);
    }
}
