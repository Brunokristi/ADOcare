<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Filters\ApiQuery;
use App\Http\Resources\BaseCollection;
use App\Models\Branch;
use App\Models\Document;
use App\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DZCDocumentController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'start' => 'required|date',
            'end' => 'required|date',
            'branch_id' => 'required|exists:branches,id',
        ]);

        $document = Document::create([
            'patient_id' => null,
            'user_id' => Auth::id(),
            'type' => 'dzc',
            'mime_type' => 'application/json',
            'name' => 'dzc_' . now()->format('d.m.Y'),
            'path' => 'dzcs/' . 'dzc_' . now()->timestamp . '.json',
        ]);

        $user = Auth::user();
        $branch = Branch::findOrFail($validated['branch_id']);
        $company = $branch->company;
        $car = $user->cars()->first();
        $representative = $branch->representative;

        $month = date('m', strtotime($startDate));
        $year = date('Y', strtotime($startDate));
        $userName = $user->title . ' ' . $user->first_name . ' ' . $user->last_name;

       

        $cpData = [
            'company_name' => $companyName,
            'user_id' => $userId,
            'ico' => $ico,
            'city' => $city,
            'user_name' => $userName,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'month' => $month,
            'year' => $year,
            'car_model' => $carModel,
            'car_license_plate' => $carLicensePlate,
            'representative_name' => $representativeName,
            'lastday_previous_month' => $lastdayPreviousMonth,
            'document_id' => $document->id,
            'created_at' => now(),
        ];

        Storage::disk('local')->put(
            'cps/' . now()->timestamp . '.json',
            json_encode($cpData, JSON_PRETTY_PRINT)
        );
         
        return response()->json([
            'success' => true,
            'document_id' => $document->id,
            'message' => 'Cestovný príkaz bol úspešne vytvorený',
        ], 201);
    }

    public function show($documentId)
    {
        $documentId = (int) $documentId;
        $document = Document::with(['user'])->findOrFail($documentId);

        $cpFile = null;

        $files = Storage::disk('local')->files('cps');
        foreach ($files as $file) {
            $content = json_decode(Storage::disk('local')->get($file), true);
            if ($content['document_id'] === $documentId) {
                $cpFile = $content;
                break;
            }
        }

        if (!$cpFile) {
            return response()->json(['message' => 'Cestovný príkaz data not found'], 404);
        }

        $responseData = [
            'document' => $document,
            'cp_data' => $cpFile,
        ];

        return response()->json($responseData);
    }
}
