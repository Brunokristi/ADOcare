<?php

namespace App\Http\Controllers\Api;

use App\Http\Filters\ApiQuery;
use App\Http\Resources\BaseCollection;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Http\Response;

class CompanyController extends Controller
{
    public function index(Request $request)
    {
        $query = Company::query();
        $results = ApiQuery::apply($request, $query);
        return new BaseCollection($results);
    }

    public function store(Request $request)
    {
        $item = Company::create($request->all());
        return response()->json($item, Response::HTTP_CREATED);
    }

    public function show(Company $company)
    {
        return $company;
    }

    public function update(Request $request, Company $company)
    {
        $company->update($request->all());
        return response()->json($company);
    }

    public function destroy(Company $company)
    {
        $company->delete();
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
