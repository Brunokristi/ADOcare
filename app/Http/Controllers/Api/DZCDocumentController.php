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
use Illuminate\Support\Facades\DB;

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
        $car = $user->cars()->first();

        $startDate = $validated['start'];
        $endDate = $validated['end'];
        $month = date('m', strtotime($startDate));
        $year = date('Y', strtotime($startDate));
        $userName = $user->title . ' ' . $user->first_name . ' ' . $user->last_name;
        $userId = Auth::id();
        $branchId = $validated['branch_id'];
        $carModel = $car ? $car->model : '';
        $carLicensePlate = $car ? $car->evc : '';
        $branchAdress = $branch ? $branch->address : '';

        $patientAddressesRaw = DB::table('patient_points')
            ->join('patients', 'patients.id', '=', 'patient_points.patient_id')
            ->select(
                'patient_points.date',
                'patients.address',
                'patients.latitude',
                'patients.longitude'
            )
            ->where('patient_points.user_id', $userId)
            ->where('patient_points.branch_id', $branchId)
            ->whereBetween('patient_points.date', [$startDate, $endDate])
            ->whereIn('patient_points.procedure_code', ['3439', '3440'])
            ->groupBy(
                'patient_points.date',
                'patients.address',
                'patients.latitude',
                'patients.longitude'
            )
            ->orderBy('patient_points.date', 'ASC')
            ->get();

        $patientAddressesByDate = [];
        foreach ($patientAddressesRaw as $record) {
            $date = $record->date;
            if (!isset($patientAddressesByDate[$date])) {
                $patientAddressesByDate[$date] = [];
            }

            $addressKey = $record->address . '|' . $record->latitude . '|' . $record->longitude;
            
            if (!isset($patientAddressesByDate[$date][$addressKey])) {
                $patientAddressesByDate[$date][$addressKey] = [
                    'address' => $record->address,
                    'latitude' => (float) $record->latitude,
                    'longitude' => (float) $record->longitude,
                ];
            }
        }

        $formattedAddresses = [];
        $branchLatitude = (float) $branch->latitude;
        $branchLongitude = (float) $branch->longitude;
        
        foreach ($patientAddressesByDate as $date => $addresses) {
            // Add branch address at the start
            $dayAddresses = [
                [
                    'address' => $branchAdress,
                    'latitude' => $branchLatitude,
                    'longitude' => $branchLongitude,
                ]
            ];
            
            // Add all patient addresses
            $dayAddresses = array_merge($dayAddresses, array_values($addresses));
            
            // Add branch address at the end
            $dayAddresses[] = [
                'address' => $branchAdress,
                'latitude' => $branchLatitude,
                'longitude' => $branchLongitude,
            ];
            
            $formattedAddresses[$date] = $dayAddresses;
        }

        $dzcData = [
            'user_id' => $userId,
            'user_name' => $userName,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'month' => $month,
            'year' => $year,
            'car_model' => $carModel,
            'car_license_plate' => $carLicensePlate,
            'branch_address' => $branchAdress,
            'patient_addresses' => $formattedAddresses,
            'document_id' => $document->id,
            'created_at' => now(),
        ];

        Storage::disk('local')->put(
            '/dzcs/' . now()->timestamp . '.json',
            json_encode($dzcData, JSON_PRETTY_PRINT)
        );
         
        return response()->json([
            'success' => true,
            'document_id' => $document->id,
            'message' => 'Denný záznam ciest bol úspešne vytvorený',
        ], 201);
    }

    public function show($documentId)
    {
        $documentId = (int) $documentId;
        $document = Document::with(['user'])->findOrFail($documentId);

        $dzcFile = null;

        $files = Storage::disk('local')->files('dzcs');
        foreach ($files as $file) {
            $content = json_decode(Storage::disk('local')->get($file), true);
            if ($content['document_id'] === $documentId) {
                $dzcFile = $content;
                break;
            }
        }

        if (!$dzcFile) {
            return response()->json(['message' => 'Denný záznam ciest data not found'], 404);
        }

        $responseData = [
            'document' => $document,
            'dzc_data' => $dzcFile,
        ];

        return response()->json($responseData);
    }
}
