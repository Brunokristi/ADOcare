<?php

namespace App\Http\Controllers\Api;

use App\Http\Filters\ApiQuery;
use App\Http\Resources\BaseCollection;
use App\Http\Resources\CarCollection;
use App\Http\Resources\DoctorCollection;
use App\Http\Resources\UserCollection;
use App\Models\Branch;
use App\Models\Car;
use App\Models\Doctor;
use App\Models\User;
use Illuminate\Http\Request;
use \App\Http\Controllers\Controller;

class MyCompanyController extends Controller
{
    /**
     * Get current user's company
     *
     * @group Companies
     */
    public function show(Request $request)
    {
        $user = $request->user();
        $company = $user->company;
        if (!$company) {
            return $this->success(null, 'No company associated with current user');
        }

        return $this->success($company->load(['representative']), 'My company retrieved');
    }

    /**
     * List branches for current user's company
     *
     * @group Companies
     */
    public function branches(Request $request)
    {
        $user = $request->user();

        $companyId = $user->company->id ?? null;
        if (!$companyId) {
            return $this->success(new BaseCollection(collect([])), 'Company branches retrieved');
        }

        $query = Branch::query()->where('company_id', $companyId);
        $results = ApiQuery::apply($request, $query);
        return $this->success(new BaseCollection($results), 'Company branches retrieved');
    }

    /**
     * List cars for the user's company
     *
     * @group Cars
     */
    public function cars(Request $request)
    {
        $user = $request->user();
        $company = $user->company;

        if (!$company) {
            return $this->success(new CarCollection(collect([])), 'Cars retrieved');
        }

        $query = Car::query()->where('company_id', $company->id);
        $results = ApiQuery::apply(request(), $query, searchable: ['evc', 'model', 'user' => ['first_name', 'last_name']], allowedFilters: ['user_id']);
        return $this->success(new CarCollection($results), 'Cars retrieved');
    }

    /**
     * List users for the current user's company
     *
     * @group Users
     */
    public function users(Request $request)
    {
        $user = $request->user();
        $company = $user->company;

        if (!$company) {
            return $this->success(new UserCollection(collect([])), 'Users retrieved');
        }

        $query = User::query()->whereHas('company', function ($q) use ($company) {
            $q->where('company.id', $company->id);
        });
        $results = ApiQuery::apply($request, $query);
        return $this->success(new UserCollection($results), 'Users retrieved');
    }

    public function doctors()
    {
        $user = request()->user();
        $company = $user->company;

        if (!$company) {
            return $this->success(new DoctorCollection(collect([])), 'Doctors retrieved');
        }

        $query = Doctor::query()->whereHas('assigned_branches', function ($q) use ($company) {
            $q->where('company_id', $company->id);
        });

        $results = ApiQuery::apply(request(), $query);

        return $this->success(new DoctorCollection($results), 'Doctors retrieved');
    }

}
