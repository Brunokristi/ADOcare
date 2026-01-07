<?php
namespace App\Http\Controllers\Api;

use App\Http\Filters\ApiQuery;
use App\Http\Requests\PatientDeleteManyRequest;
use App\Http\Requests\PatientStoreRequest;
use App\Http\Requests\PatientUpdateRequest;
use App\Http\Resources\BaseCollection;
use App\Http\Resources\PatientCollection;
use App\Http\Resources\PatientResource;
use App\Http\Responses\ApiResponse;
use App\Models\Branch;
use App\Models\Diagnosis;
use App\Models\Patient;
use App\Models\PatientPoint;
use App\Http\Requests\PatientPointIndexRequest;
use App\Models\Procedure;
use App\Services\PatientService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class PatientController extends Controller
{


    private PatientService $service;

    public function __construct(PatientService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $branchId = (int) $request->input('branch_id');
        $branch = Branch::find($branchId);
        if (!$branch) {
            return $this->success(new PatientCollection(collect([])), 'Patients retrieved');
        }

        $query = $this->service->queryForUserBranch($user, $branch);

        $results = ApiQuery::apply(
            $request,
            $query,
            searchable: ['first_name', 'last_name', 'personal_number'],
            allowedFilters: ['sex'],
            defaults:['sort' => 'last_name']
        );

        return $this->success(new PatientCollection($results), 'Patients retrieved');
    }


    public function store(PatientStoreRequest $request)
    {
        $data = $request->validated();

        $patient = $this->service->create($data, $request->user(), (int) $data['branch_id']);

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


    public function update(PatientUpdateRequest $request, $id)
    {
        $data = $request->validated();

        $patient = Patient::find($id);
        if (!$patient) {
            return $this->error('Not found', 404);
        }

        $branchId = array_key_exists('branch_id', $data) ? (int) $data['branch_id'] : null;

        $patient = $this->service->update($patient, $data, $request->user(), $branchId);

        return $this->success(new PatientResource($patient), 'Updated');
    }


    public function destroy(Patient $patient)
    {
        if (!$patient) {
            return $this->error('Not found', 404);
        }

        $this->service->delete($patient);

        return $this->success(null, 'Deleted');
    }

    public function destroyMany(PatientDeleteManyRequest $request)
    {
        $this->service->deleteManyByIds($request->input('ids'));

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

    public function points(PatientPointIndexRequest $request, Patient $patient)
    {
        $data = $request->validated();

        $query = PatientPoint::query()->where('patient_id', $patient->id);

        if ($request->filled('date_from')) {
            $query->whereDate('reference_date', '>=', $data['date_from']);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('reference_date', '<=', $data['date_to']);
        }

        $results = ApiQuery::apply($request, $query, searchable: ['reference_date'],
            allowedFilters: ['reference_date', 'branch_id'],
            defaults: ['sort' => 'reference_date']
    );
        return $this->success(new BaseCollection($results), 'Patient points retrieved');
    }
}
