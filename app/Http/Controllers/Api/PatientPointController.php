<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Filters\ApiQuery;
use App\Models\PatientPoint;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PatientPointController extends Controller
{
    public function index(Request $request)
    {
        $query = PatientPoint::query();

        if ($request->filled('patient_id')) {
            $query->where('patient_id', $request->integer('patient_id'));
        }

        $results = ApiQuery::apply(
            $request,
            $query,
            searchable: ['patient_name', 'patient_personal_number', 'diagnosis_code', 'procedure_code'],
            allowedFilters: ['patient_id', 'user_id', 'branch_id', 'doctor_id', 'date'],
            defaults: ['sort' => '-date,id']
        );

        return $this->success($results, 'Patient points retrieved');
    }

    /**
     * POST /v1/patient-points
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'date'                      => ['required', 'date'],
            'patient_personal_number'   => ['required', 'string', 'max:255'],
            'patient_name'              => ['required', 'string', 'max:255'],
            'patient_id'                => ['required', 'integer'], // add "exists:patients,id" if FK is set

            'diagnosis_code'            => ['required', 'string', 'max:255'],
            'diagnosis_id'              => ['required', 'integer'], // "exists:diagnoses,id"

            'procedure_code'            => ['required', 'string', 'max:255'],
            'procedure_id'              => ['required', 'integer'], // "exists:procedures,id"

            'doctor_pzs'                => ['nullable', 'string', 'max:255'],
            'doctor_zpr'                => ['nullable', 'string', 'max:255'],
            'doctor_id'                 => ['nullable', 'integer'], // or required + exists

            'reference_date'            => ['required', 'date'],
            'user_id'                   => ['required', 'integer'], // "exists:users,id"
            'branch_id'                 => ['required', 'integer'], // "exists:branches,id"
            'quantity'                  => ['required', 'integer', 'min:1'],
        ]);

        $point = PatientPoint::create($validated);

        return response()->json($point, Response::HTTP_CREATED);
    }

    /**
     * GET /v1/patient-points/{patient_point}
     */
    public function show(PatientPoint $patientPoint)
    {
        return $patientPoint;
    }

    /**
     * PUT/PATCH /v1/patient-points/{patient_point}
     */
    public function update(Request $request, PatientPoint $patientPoint)
    {
        $validated = $request->validate([
            'date'                      => ['sometimes', 'required', 'date'],
            'patient_personal_number'   => ['sometimes', 'required', 'string', 'max:255'],
            'patient_name'              => ['sometimes', 'required', 'string', 'max:255'],
            'patient_id'                => ['sometimes', 'required', 'integer'],

            'diagnosis_code'            => ['sometimes', 'required', 'string', 'max:255'],
            'diagnosis_id'              => ['sometimes', 'required', 'integer'],

            'procedure_code'            => ['sometimes', 'required', 'string', 'max:255'],
            'procedure_id'              => ['sometimes', 'required', 'integer'],

            'doctor_pzs'                => ['sometimes', 'nullable', 'string', 'max:255'],
            'doctor_zpr'                => ['sometimes', 'nullable', 'string', 'max:255'],
            'doctor_id'                 => ['sometimes', 'nullable', 'integer'],

            'reference_date'            => ['sometimes', 'required', 'date'],
            'user_id'                   => ['sometimes', 'required', 'integer'],
            'branch_id'                 => ['sometimes', 'required', 'integer'],
            'quantity'                  => ['sometimes', 'required', 'integer', 'min:1'],
        ]);

        $patientPoint->update($validated);

        return response()->json($patientPoint);
    }

    /**
     * DELETE /v1/patient-points/{patient_point}
     */
    public function destroy(PatientPoint $patientPoint)
    {
        $patientPoint->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
