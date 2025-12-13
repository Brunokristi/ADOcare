<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\PatientCollection;
use App\Http\Resources\PatientResource;
use App\Http\Resources\BaseCollection;
use App\Http\Filters\ApiQuery;
use App\Http\Responses\ApiResponse;
use App\Models\Patient;
use App\Models\InsuranceCompany;
use App\Models\Doctor;
use App\Models\Diagnosis;
use App\Models\Procedure;
use App\Models\PatientPoint;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class PatientController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $user = $request->user();
        $branchId = $request->integer('branch_id');

        $query = Patient::with(['doctor', 'visits', 'insuranceCompany'])
            ->whereHas('assignedUsers', function ($q) use ($user, $branchId) {
                $q->where('users.id', $user->id);
                $q->wherePivot('branch_id', $branchId);
                
            });

        $results = ApiQuery::apply(
            $request,
            $query,
            searchable: ['first_name', 'last_name', 'personal_number'],
            allowedFilters: ['sex']
        );


        return $this->success(new PatientCollection($results), 'Patients retrieved');
    }


    public function store(Request $request)
    {
        $data = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'personal_number' => 'nullable|string',
            'sex' => 'nullable|in:M,F',
            'reference_date' => 'nullable|date',
        ]);

        $patient = Patient::create($data);

        return $this->success(new PatientResource($patient), 'Created', 201);
    }

    public function show($id)
    {
        $patient = Patient::with(['doctor', 'visits', 'insuranceCompany'])->find($id);

        if (!$patient) {
            return $this->error('Not found', 404);
        }

        return $this->success(new PatientResource($patient), 'Patient retrieved');
    }

    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'first_name' => 'sometimes|required|string|max:255',
            'last_name' => 'sometimes|required|string|max:255',
            'personal_number' => 'nullable|string',
            'sex' => 'nullable|in:M,F',
            'reference_date' => 'nullable|date',
        ]);

        $patient = Patient::find($id);
        if (!$patient) {
            return $this->error('Not found', 404);
        }

        $patient->fill($data);
        $patient->save();

        return $this->success(new PatientResource($patient), 'Updated');
    }

    public function destroy($id)
    {
        $patient = Patient::find($id);
        if (!$patient) {
            return $this->error('Not found', 404);
        }

        $patient->delete();

        return $this->success(null, 'Deleted');
    }

    /**
     * GET /v1/patients/{patient}/insurance-companies
     */
    public function insuranceCompany(Request $request, Patient $patient)
    {
        $insuranceCompany = $patient->insuranceCompany;
        if (!$insuranceCompany) {
            return $this->notFound();
        }

        return $this->success($insuranceCompany, 'Insurance Company retrieved');
    }

    /**
     * GET /v1/patients/{patient}/doctor
     */
    public function doctor(Request $request, Patient $patient)
    {
        $doctor = $patient->doctor;
        if (!$doctor) {
            return $this->notFound();
        }

        return $this->success($doctor, 'Doctor retrieved');

    }

    /**
     * GET /v1/patients/{patient}/diagnoses
     */
    public function diagnoses(Request $request, Patient $patient)
    {
        $query = Diagnosis::query();

        $results = ApiQuery::apply($request, $query, searchable: ['code', 'description']);

        return $this->success(new BaseCollection($results), 'Diagnoses retrieved');
    }

    /**
     * GET /v1/patients/{patient}/procedures
     */
    public function procedures(Request $request, Patient $patient)
    {
        $query = Procedure::query();

        $results = ApiQuery::apply($request, $query, searchable: ['code', 'description']);

        return $this->success(new BaseCollection($results), 'Procedures retrieved');
    }

    /**
     * GET /v1/patients/{patient}/patient-points
     */
    public function patientPoints(Request $request, Patient $patient)
    {
        $query = PatientPoint::query()->where('patient_id', $patient->id);

        $results = ApiQuery::apply($request, $query, searchable: ['reference_date', 'user_id', 'branch_id']);

        return $this->success(new BaseCollection($results), 'Patient points retrieved');
    }

}
