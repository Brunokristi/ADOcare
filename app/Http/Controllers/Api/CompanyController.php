<?php

namespace App\Http\Controllers\Api;

use App\Http\Filters\ApiQuery;
use App\Http\Resources\BaseCollection;
use App\Http\Responses\ApiResponse;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Http\Response;

class CompanyController extends Controller
{
    use ApiResponse;
    public function index(Request $request)
    {
        $query = Company::query();
        $results = ApiQuery::apply(request(), $query);
        return $this->success(new BaseCollection($results), 'Companies retrieved');
    }

    public function store(\App\Http\Requests\StoreCompanyRequest $request)
    {
        $item = Company::create($request->all());
        return $this->success($item, 'Created', Response::HTTP_CREATED);
    }

    public function show(Company $company)
    {
        return $this->success($company, 'Company retrieved');
    }

    public function update(\App\Http\Requests\UpdateCompanyRequest $request, Company $company)
    {
        $company->update($request->all());
        return $this->success($company, 'Updated');
    }

    public function destroy(Company $company)
    {
        $company->delete();
        return $this->success(null, 'Deleted', Response::HTTP_NO_CONTENT);
    }
}
