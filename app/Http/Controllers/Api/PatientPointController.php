<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Filters\ApiQuery;
use App\Http\Resources\BaseCollection;
use App\Models\PatientPoint;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PatientPointController extends Controller
{
    public function __construct()
    {
        // Same as MacroController
        $this->middleware('api.auth')->only(['index']);
    }

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

        // ✅ same shape as macros
        return $this->success(new BaseCollection($results), 'Patient points retrieved');
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
            'patient_id'                => ['required', 'integer'],

            'diagnosis_code'            => ['required', 'string', 'max:255'],
            'diagnosis_id'              => ['required', 'integer'],

            'procedure_code'            => ['required', 'string', 'max:255'],
            'procedure_id'              => ['required', 'integer'],

            'doctor_pzs'                => ['nullable', 'string', 'max:255'],
            'doctor_zpr'                => ['nullable', 'string', 'max:255'],
            'doctor_id'                 => ['nullable', 'integer'],

            'reference_date'            => ['required', 'date'],
            'user_id'                   => ['required', 'integer'],
            'branch_id'                 => ['required', 'integer'],
            'quantity'                  => ['required', 'integer', 'min:1'],
        ]);

        $point = PatientPoint::create($validated);

        // (Optional) make consistent with macros:
        return $this->success($point, 'Created', Response::HTTP_CREATED);
    }

    /**
     * GET /v1/patient-points/{patient_point}
     */
    public function show(PatientPoint $patientPoint)
    {
        return $this->success($patientPoint, 'Patient point retrieved');
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

        // (Optional) make consistent with macros:
        return $this->success($patientPoint, 'Updated');
    }

    /**
     * DELETE /v1/patient-points/{patient_point}
     */
    public function destroy(PatientPoint $patientPoint)
    {
        $patientPoint->delete();

        // (Optional) make consistent with macros:
        return $this->success(null, 'Deleted', Response::HTTP_NO_CONTENT);
    }
}
