<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\InsuranceCompany;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class InsuranceCompanyController extends Controller
{
    use ApiResponse;
    public function index(Request $request)
    {
        $q = $request->query('q', '');

        $query = InsuranceCompany::query();

        // Searching across relevant fields
        if ($q) {
            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%")
                    ->orWhere('code', 'like', "%{$q}%")
                    ->orWhere('branch_code', 'like', "%{$q}%")
                    ->orWhere('city', 'like', "%{$q}%");
            });
        }

        // If pagination? Use paginate
        if ($request->boolean('paginate', false)) {
            return $query
                ->orderBy('name')
                ->paginate(20);
        }

        // Otherwise return limited results (useful for dropdowns/autocomplete)
        return $query
            ->orderBy('name')
            ->limit(50)
            ->get();
    }

    /**
     * POST /v1/insurance-companies
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'psc' => ['nullable', 'string', 'max:50'],
            'ico' => ['nullable', 'string', 'max:50'],
            'dic' => ['nullable', 'string', 'max:50'],
            'ic_dph' => ['nullable', 'string', 'max:50'],
            'register' => ['nullable', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50'],
            'branch_code' => ['nullable', 'string', 'max:50'],
        ]);

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
    public function update(Request $request, InsuranceCompany $insuranceCompany)
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'city' => ['sometimes', 'nullable', 'string', 'max:255'],
            'psc' => ['sometimes', 'nullable', 'string', 'max:50'],
            'ico' => ['sometimes', 'nullable', 'string', 'max:50'],
            'dic' => ['sometimes', 'nullable', 'string', 'max:50'],
            'ic_dph' => ['sometimes', 'nullable', 'string', 'max:50'],
            'register' => ['sometimes', 'nullable', 'string', 'max:255'],
            'code' => ['sometimes', 'nullable', 'string', 'max:50'],
            'branch_code' => ['sometimes', 'nullable', 'string', 'max:50'],
        ]);

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
