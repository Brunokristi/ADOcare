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
use App\Models\Document;
use App\Services\PatientService;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;

class PatientController extends Controller
{


    private PatientService $service;

    public function __construct(PatientService $service)
    {
        $this->service = $service;
    }



    /**
     * List patients for a branch
     *
     * @group Patients
     * @queryParam branch_id int required Branch ID to filter patients. Example: 1
     * @queryParam per_page int The number of items per page. Example: 15
     * @response 200 {"data": [{"id":1, "first_name":"John", "last_name":"Doe"}], "meta": {"total":1}}
     */
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
            defaults: ['sort' => 'last_name']
        );

        return $this->success(new PatientCollection($results), 'Patients retrieved');
    }


    /**
     * Create a patient
     *
     * @group Patients
     * @bodyParam first_name string required Example: "John"
     * @bodyParam last_name string required Example: "Doe"
     * @bodyParam branch_id int required Branch ID. Example: 1
     * @response 201 {"data": {"id":1, "first_name":"John", "last_name":"Doe"}}
     */
    public function store(PatientStoreRequest $request)
    {
        $data = $request->validated();

        $patient = $this->service->create($data);

        return $this->success(new PatientResource($patient), 'Created', 201);
    }

    /**
     * Get a patient
     *
     * @group Patients
     * @urlParam id int required Patient ID. Example: 1
     * @response 200 {"data": {"id":1, "first_name":"John", "last_name":"Doe"}}
     */
    public function show($id)
    {
        $patient = Patient::with(['doctor', 'visits', 'insuranceCompany'])->find($id);

        if (!$patient) {
            return $this->error('Not found', 404);
        }

        return $this->success(new PatientResource($patient), 'Patient retrieved');
    }


    /**
     * Update a patient
     *
     * @group Patients
     * @urlParam id int required Patient ID. Example: 1
     * @bodyParam first_name string Example: "Jane"
     * @bodyParam last_name string Example: "Doe"
     * @response 200 {"data": {"id":1, "first_name":"Jane", "last_name":"Doe"}}
     */
    public function update(PatientUpdateRequest $request, Patient $patient)
    {
        $data = $request->validated();

        $patient = $this->service->update($patient, $data);

        return $this->success(new PatientResource($patient), 'Updated');
    }



    /**
     * Delete a patient
     *
     * @group Patients
     * @urlParam patient int required Patient ID. Example: 1
     * @response 200 {"success":true}
     */
    public function destroy(Patient $patient)
    {
        if (!$patient) {
            return $this->error('Not found', 404);
        }

        $this->service->delete($patient);

        return $this->success(null, 'Deleted');
    }

    /**
     * Bulk delete patients
     *
     * @group Patients
     * @bodyParam ids array required Array of patient IDs to delete. Example: [1,2,3]
     * @response 200 {"success":true}
     */
    public function destroyMany(PatientDeleteManyRequest $request)
    {
        $this->service->deleteManyByIds($request->input('ids'));

        return $this->success(null, 'Deleted');
    }

    /**
     * Get patient's insurance company
     *
     * @group Patients
     * @urlParam patient int required Patient ID. Example: 1
     * @response 200 {"data":{"id":1,"name":"Insurance Co"}}
     */
    public function insuranceCompany(Request $request, Patient $patient)
    {
        $insuranceCompany = $patient->insuranceCompany;
        if (!$insuranceCompany)
            return $this->notFound();
        return $this->success($insuranceCompany, 'Insurance Company retrieved');
    }

    /**
     * Get patient's doctor
     *
     * @group Patients
     * @urlParam patient int required Patient ID. Example: 1
     * @response 200 {"data":{"id":1,"first_name":"John","last_name":"Doe"}}
     */
    public function doctor(Request $request, Patient $patient)
    {
        $doctor = $patient->doctor;
        if (!$doctor)
            return $this->notFound();
        return $this->success($doctor, 'Doctor retrieved');
    }

    /**
     * List diagnoses for a patient
     *
     * @group Patients
     * @urlParam patient int required Patient ID. Example: 1
     * @queryParam per_page int The number of items per page. Example: 15
     * @response 200 {"data":[{"id":1,"code":"A01","description":"Diagnosis"}],"meta":{"total":1}}
     */
    public function diagnoses(Request $request, Patient $patient)
    {
        $query = Diagnosis::query();
        $results = ApiQuery::apply($request, $query, searchable: ['code', 'description']);
        return $this->success(new BaseCollection($results), 'Diagnoses retrieved');
    }

    /**
     * List procedures for a patient
     *
     * @group Patients
     * @urlParam patient int required Patient ID. Example: 1
     * @queryParam per_page int The number of items per page. Example: 15
     * @response 200 {"data":[{"id":1,"code":"X123","description":"Procedure"}],"meta":{"total":1}}
     */
    public function procedures(Request $request, Patient $patient)
    {
        $query = Procedure::query();
        $results = ApiQuery::apply($request, $query, searchable: ['code', 'description']);
        return $this->success(new BaseCollection($results), 'Procedures retrieved');
    }

    /**
     * List patient points (time series)
     *
     * @group Patients
     * @urlParam patient int required Patient ID. Example: 1
     * @queryParam date_from date optional Start date. Example: 2025-01-01
     * @queryParam date_to date optional End date. Example: 2025-01-31
     * @queryParam branch_id int optional Branch ID to filter. Example: 1
     * @queryParam per_page int The number of items per page. Example: 15
     * @response 200 {"data":[],"meta":{"total":0}}
     */
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

        $results = ApiQuery::apply(
            $request,
            $query,
            searchable: ['reference_date'],
            allowedFilters: ['reference_date', 'branch_id'],
            defaults: ['sort' => 'reference_date']
        );
        return $this->success(new BaseCollection($results), 'Patient points retrieved');
    }

    /**
     * List patient documents
     *
     * @group Patients
     * @urlParam patient int required Patient ID. Example: 1
     * @queryParam per_page int The number of items per page. Example: 15
     * @response 200 {"data":[],"meta":{"total":0}}
     */
    public function documents(Request $request, Patient $patient)
    {
        $query = Document::where('patient_id', $patient->id)
            ->orderByDesc('created_at');

        $results = ApiQuery::apply($request, $query, searchable: ['name', 'type']);

        return $this->success(new BaseCollection($results), 'Patient documents retrieved');
    }

}
