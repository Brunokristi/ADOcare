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
    /**
     * List companies
     *
     * @group Companies
     * @queryParam q string Search query. Example: "Acme"
     * @response 200 {
     *  "data": [ {"id":1, "name":"Acme"} ],
     *  "meta": {"total":1}
     * }
     */
    public function index(Request $request)
    {
        $query = Company::query();
        $results = ApiQuery::apply(request(), $query);
        return $this->success(new BaseCollection($results), 'Companies retrieved');
    }

    /**
     * Create a company
     *
     * @group Companies
     * @bodyParam name string required Company name. Example: "Acme"
     * @response 201 {"id":1, "name":"Acme"}
     */
    public function store(\App\Http\Requests\StoreCompanyRequest $request)
    {
        $item = Company::create($request->validated());
        return $this->success($item, 'Created', Response::HTTP_CREATED);
    }

    /**
     * Get a company
     *
     * @group Companies
     * @urlParam company int required The ID of the company. Example: 1
     * @response 200 {"id":1, "name":"Acme"}
     */
    public function show(Company $company)
    {
        return $this->success($company, 'Company retrieved');
    }

    /**
     * Update a company
     *
     * @group Companies
     * @urlParam company int required The ID of the company. Example: 1
     * @bodyParam name string Company name. Example: "Acme Updated"
     * @response 200 {"id":1, "name":"Acme Updated"}
     */
    public function update(\App\Http\Requests\UpdateCompanyRequest $request, Company $company)
    {
        $company->update($request->validated());
        return $this->success($company, 'Updated');
    }

    /**
     * List patients for a company
     *
     * @group Companies
     * @urlParam company int required Company ID. Example: 1
     * @queryParam per_page int The number of items per page. Example: 15
     * @response 200 {"data": [{"id":1, "first_name":"John", "last_name":"Doe"}], "meta": {"total":1}}
     */
    public function patients(Request $request, Company $company)
    {
        $query = Patient::query()->whereHas('branch', function ($q) use ($company) {
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

    /**
     * Delete a company
     *
     * @group Companies
     * @urlParam company int required Company ID. Example: 1
     * @response 204 {}
     */
    public function destroy(Company $company)
    {
        $company->delete();
        return $this->success(null, 'Deleted', Response::HTTP_NO_CONTENT);
    }

    /**
     * Bulk delete companies
     *
     * @group Companies
     * @bodyParam ids array required Array of company IDs to delete. Example: [1,2,3]
     * @response 204 {}
     */
    public function destroyMany(DestroyManyRequest $request)
    {
        $ids = $request->input('ids', []);
        Company::whereIn('id', $ids)->delete();
        return $this->success(null, 'Deleted', Response::HTTP_NO_CONTENT);
    }

    /**
     * Get current user's company
     *
     * @group Companies
     * @response 200 {"id":1, "name":"Acme"}
     */
    public function myCompany(Request $request)
    {
        $user = $request->user();
        $company = $user->company;
        if (!$company)
            return $this->success(null, 'No company associated with current user');

        return $this->success($company->load(['representative']), 'My company retrieved');
    }
}
