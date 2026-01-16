<?php

namespace App\Http\Controllers\Api;

use App\Http\Filters\ApiQuery;
use App\Http\Requests\DestroyManyRequest;
use App\Http\Resources\BaseCollection;
use App\Http\Responses\ApiResponse;
use App\Models\Company;
use App\Models\Patient;
use Illuminate\Http\Request;
use \App\Http\Controllers\Controller;
use Illuminate\Http\Response;

class CompanyController extends Controller
{
    public function index(Request $request)
    {
        $query = Company::query();
        $results = ApiQuery::apply(request(), $query);
        return $this->success(new BaseCollection($results), 'Companies retrieved');
    }

    public function store(\App\Http\Requests\StoreCompanyRequest $request)
    {
        $item = Company::create($request->validated());
        return $this->success($item, 'Created', Response::HTTP_CREATED);
    }

    public function show(Company $company)
    {
        return $this->success($company, 'Company retrieved');
    }

    public function update(\App\Http\Requests\UpdateCompanyRequest $request, Company $company)
    {
        $company->update($request->validated());
        return $this->success($company, 'Updated');
    }

    public function patients(Request $request, Company $company)
    {
        $query = Patient::query()->whereHas('branches', function ($q) use ($company) {
            $q->where('company_id', $company->id);
        });
        $results = ApiQuery::apply(
            $request,
            $query,
            searchable: ['first_name', 'last_name', 'personal_number'],
            allowedFilters: ['sex'],
            defaults: ['sort' => 'last_name']
        );
        return $this->success(new BaseCollection(resource: $results), 'Patients retrieved');
    }

    public function destroy(Company $company)
    {
        $company->delete();
        return $this->success(null, 'Deleted', Response::HTTP_NO_CONTENT);
    }

    public function destroyMany(DestroyManyRequest $request)
    {
        $ids = $request->input('ids', []);
        Company::whereIn('id', $ids)->delete();
        return $this->success(null, 'Deleted', Response::HTTP_NO_CONTENT);
    }
}
