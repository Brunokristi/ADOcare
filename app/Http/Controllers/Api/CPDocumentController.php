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

class CPDocumentController extends Controller
{
    public function index(Request $request)
    {
        $query = Document::where('type', 'cp')
            ->where('user_id', Auth::id())
            ->orderBy('created_at', 'desc');

        if ($request->has('branch_id')) {
            // For CP documents, we can filter by the branch_id from request
            // Since CP documents don't have a branch_id column, we'll just return all for the user
            // But we can add filtering if needed
        }

        $documents = $query->get()->map(function ($doc) {
            return [
                'id' => $doc->id,
                'name' => $doc->name,
                'type' => $doc->type,
                'mime_type' => $doc->mime_type,
                'created_at' => $doc->created_at,
                'path' => $doc->path,
            ];
        });

        return response()->json(['data' => $documents]);
    }

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
            'type' => 'cp',
            'mime_type' => 'application/json',
            'name' => 'cp_' . now()->format('d.m.Y'),
            'path' => 'cps/' . 'cp_' . now()->timestamp . '.json',
        ]);

        $user = Auth::user();
        $branch = Branch::findOrFail($validated['branch_id']);
        $company = $branch->company;
        $car = $user->cars()->first();
        $representative = $branch->representative;


        $companyName = $company ? $company->name : '';
        $ico = $company ? $company->ico : '';
        $userName = $user->title . ' ' . $user->first_name . ' ' . $user->last_name;
        $userId = $user ? $user->id : null;
        $city = $company ? $company->city : '';
        $startDate = $validated['start'];
        $endDate = $validated['end'];
        $month = date('m', strtotime($startDate));
        $year = date('Y', strtotime($startDate));
        $carModel = $car ? $car->model : '';
        $carLicensePlate = $car ? $car->evc : '';
        $representativeName = $representative->title . ' ' . ($representative ? $representative->first_name . ' ' . $representative->last_name : '');
        $lastdayPreviousMonth = date("Y-m-d", strtotime("last day of previous month", strtotime($startDate)));
        while (in_array(date('N', strtotime($lastdayPreviousMonth)), [6, 7])) {
            $lastdayPreviousMonth = date("Y-m-d", strtotime($lastdayPreviousMonth . " -1 day"));
        }        

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

        Log::info('Creating CP Document', $cpData);

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
