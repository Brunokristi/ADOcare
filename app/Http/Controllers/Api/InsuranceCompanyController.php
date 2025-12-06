<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Filters\ApiQuery;
use App\Http\Resources\InsuranceCompanyCollection;
use App\Http\Resources\InsuranceCompanyResource;
use App\Http\Responses\ApiResponse;
use App\Models\InsuranceCompany;
use Illuminate\Http\Request;

class InsuranceCompanyController extends Controller
{
    use ApiResponse;

    public function index()
    {
      $query = InsuranceCompany::query();

      // tweak searchable/filters as you like
      $results = ApiQuery::apply(
          request(),
          $query,
          searchable: ['name', 'code', 'city', 'ico'],
          allowedFilters: ['city', 'code', 'branch_code']
      );

      return $this->success(
          new InsuranceCompanyCollection($results),
          'Insurance companies retrieved'
      );
    }

    public function store(Request $request)
    {
      $data = $request->validate([
          'name'        => 'required|string|max:255',
          'address'     => 'nullable|string|max:255',
          'city'        => 'nullable|string|max:255',
          'psc'         => 'nullable|string|max:20',
          'ico'         => 'nullable|string|max:50',
          'dic'         => 'nullable|string|max:50',
          'ic_dph'      => 'nullable|string|max:50',
          'register'    => 'nullable|string|max:255',
          'code'        => 'nullable|string|max:50',
          'branch_code' => 'nullable|string|max:50',
      ]);

      $company = InsuranceCompany::create($data);

      return $this->success(
          new InsuranceCompanyResource($company),
          'Created',
          201
      );
    }

    public function show($id)
    {
      $company = InsuranceCompany::find($id);

      if (! $company) {
          return $this->error('Not found', 404);
      }

      return $this->success(
          new InsuranceCompanyResource($company),
          'Insurance company retrieved'
      );
    }

    public function update(Request $request, $id)
    {
      $data = $request->validate([
          'name'        => 'sometimes|required|string|max:255',
          'address'     => 'nullable|string|max:255',
          'city'        => 'nullable|string|max:255',
          'psc'         => 'nullable|string|max:20',
          'ico'         => 'nullable|string|max:50',
          'dic'         => 'nullable|string|max:50',
          'ic_dph'      => 'nullable|string|max:50',
          'register'    => 'nullable|string|max:255',
          'code'        => 'nullable|string|max:50',
          'branch_code' => 'nullable|string|max:50',
      ]);

      $company = InsuranceCompany::find($id);

      if (! $company) {
          return $this->error('Not found', 404);
      }

      $company->fill($data);
      $company->save();

      return $this->success(
          new InsuranceCompanyResource($company),
          'Updated'
      );
    }

    public function destroy($id)
    {
      $company = InsuranceCompany::find($id);

      if (! $company) {
          return $this->error('Not found', 404);
      }

      $company->delete();

      return $this->success(null, 'Deleted');
    }
}
