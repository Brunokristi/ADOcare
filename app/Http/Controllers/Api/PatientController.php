<?php
namespace App\Http\Controllers\Api;

use App\Http\Filters\ApiQuery;
use App\Http\Requests\PatientDeleteManyRequest;
use App\Http\Resources\BaseCollection;
use App\Http\Resources\PatientCollection;
use App\Http\Resources\PatientResource;
use App\Http\Responses\ApiResponse;
use App\Models\Diagnosis;
use App\Models\Patient;
use App\Models\PatientPoint;
use App\Models\Procedure;
use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

class PatientController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $user = $request->user();
        $branchId = (int) $request->input('branch_id');

        $query = Patient::with(['doctor', 'visits', 'insuranceCompany'])
            ->whereHas('assignedUsers', function ($q) use ($user, $branchId) {
                $q->where('users.id', $user->id)
                ->where('patient_branch_users.branch_id', $branchId);
            });

        if (!$request->filled('sort')) {
            $query->orderBy('last_name')
                ->orderBy('first_name');
        }

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
            'branch_id' => 'required|integer|exists:branches,id',

            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'title' => 'nullable|string|max:255',
            'personal_number' => 'nullable|string|max:255',
            'sex' => 'nullable|in:M,F',
            'contact' => 'nullable|string|max:255',

            'doctor_id' => 'nullable|integer|exists:doctors,id',
            'insurance_company_id' => 'nullable|integer|exists:insurance_companies,id',

            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'zip' => 'nullable|string|max:50',

            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',

            'reference_date' => 'nullable|date',
        ]);

        $patient = Patient::create(collect($data)->except('branch_id')->toArray());

        // Attach current user + branch in pivot (patient_branch_users)
        $patient->assignedUsers()->syncWithoutDetaching([
            $request->user()->id => ['branch_id' => (int) $data['branch_id']],
        ]);

        $patient->load(['doctor', 'visits', 'insuranceCompany']);

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
            'branch_id' => 'sometimes|integer|exists:branches,id',

            'first_name' => 'sometimes|required|string|max:255',
            'last_name' => 'sometimes|required|string|max:255',
            'title' => 'nullable|string|max:255',
            'personal_number' => 'nullable|string|max:255',
            'sex' => 'nullable|in:M,F',
            'contact' => 'nullable|string|max:255',

            'doctor_id' => 'nullable|integer|exists:doctors,id',
            'insurance_company_id' => 'nullable|integer|exists:insurance_companies,id',

            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'zip' => 'nullable|string|max:50',

            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',

            'reference_date' => 'nullable|date',
        ]);

        $patient = Patient::find($id);
        if (!$patient) {
            return $this->error('Not found', 404);
        }

        $patient->fill(collect($data)->except('branch_id')->toArray());
        $patient->save();

        if (array_key_exists('branch_id', $data)) {
            $patient->assignedUsers()->syncWithoutDetaching([
                $request->user()->id => ['branch_id' => (int) $data['branch_id']],
            ]);
        }

        $patient->load(['doctor', 'visits', 'insuranceCompany']);

        return $this->success(new PatientResource($patient), 'Updated');
    }


    public function destroy(Patient $patient)
    {
        if (!$patient) {
            return $this->error('Not found', 404);
        }
        // Soft delete
        $patient->delete();
        return $this->success(null, 'Deleted');
    }

    public function destroyMany(PatientDeleteManyRequest $request)
    {

        Patient::whereIn('id', $request->input('ids'))->delete();

        return $this->success(null, 'Deleted');
    }


    public function insuranceCompany(Request $request, Patient $patient)
    {
        $insuranceCompany = $patient->insuranceCompany;
        if (!$insuranceCompany)
            return $this->notFound();
        return $this->success($insuranceCompany, 'Insurance Company retrieved');
    }

    public function doctor(Request $request, Patient $patient)
    {
        $doctor = $patient->doctor;
        if (!$doctor)
            return $this->notFound();
        return $this->success($doctor, 'Doctor retrieved');
    }

    public function diagnoses(Request $request, Patient $patient)
    {
        $query = Diagnosis::query();
        $results = ApiQuery::apply($request, $query, searchable: ['code', 'description']);
        return $this->success(new BaseCollection($results), 'Diagnoses retrieved');
    }

    public function procedures(Request $request, Patient $patient)
    {
        $query = Procedure::query();
        $results = ApiQuery::apply($request, $query, searchable: ['code', 'description']);
        return $this->success(new BaseCollection($results), 'Procedures retrieved');
    }

    public function patientPoints(Request $request, Patient $patient)
    {
        $query = PatientPoint::query()->where('patient_id', $patient->id);
        $results = ApiQuery::apply($request, $query, searchable: ['reference_date', 'user_id', 'branch_id']);
        return $this->success(new BaseCollection($results), 'Patient points retrieved');
    }

    public function documents(Request $request, Patient $patient)
    {
        $query = Document::where('patient_id', $patient->id)
            ->orderByDesc('created_at');

        $results = ApiQuery::apply($request, $query, searchable: ['name', 'type']);

        return $this->success(new BaseCollection($results), 'Patient documents retrieved');
    }

    
}
