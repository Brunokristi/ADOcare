<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Filters\ApiQuery;
use App\Http\Resources\BaseCollection;
use App\Http\Responses\ApiResponse;
use App\Http\Requests\InsuranceCompanyRequest;
use App\Models\InsuranceCompany;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class InsuranceCompanyController extends Controller
{
    public function index(Request $request)
    {
        $query = InsuranceCompany::query();

        $result = ApiQuery::apply(
            request(),
            $query,
            ['name', 'code', 'branch_code', 'city'],
            [],
            [
                'sort' => 'name',
            ]
        );

        return $this->success(new BaseCollection($result), 'Insurance companies retrieved');
    }

    /**
     * POST /v1/insurance-companies
     */
    public function store(InsuranceCompanyRequest $request)
    {
        $validated = $request->validated();

        $company = InsuranceCompany::create($validated);

        return $this->success($company, 'Created', Response::HTTP_CREATED);
    }

    /**
     * GET /v1/insurance-companies/{insurance_company}
     */
    public function show(InsuranceCompany $insuranceCompany)
    {
        return $this->success($insuranceCompany, 'Insurance company retrieved');
    }

    /**
     * PUT/PATCH /v1/insurance-companies/{insurance_company}
     */
    public function update(InsuranceCompanyRequest $request, InsuranceCompany $insuranceCompany)
    {
        $validated = $request->validated();

        $insuranceCompany->update($validated);

        return $this->success($insuranceCompany, 'Updated', Response::HTTP_OK);
    }

    /**
     * DELETE /v1/insurance-companies/{insurance_company}
     */
    public function destroy(InsuranceCompany $insuranceCompany)
    {
        $insuranceCompany->delete();

        return $this->success(null, 'Deleted', Response::HTTP_NO_CONTENT);
    }
}
