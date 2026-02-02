<?php

namespace App\Http\Controllers\Api;

use App\Http\Filters\ApiQuery;
use App\Http\Resources\BaseCollection;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use Illuminate\Http\Request;
use \App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{

    public function index(Request $request)
    {
        $query = User::query();
        $results = ApiQuery::apply(request(), $query);
        return $this->success(new BaseCollection($results), 'Users retrieved');
    }

    public function myCompanyUsers(Request $request)
    {
        $user = $request->user();
        $company = $user->company;

        if (!$company) {
            return $this->success(new BaseCollection(collect([])), 'Users retrieved');
        }

        $query = User::query()->whereHas('company', function ($q) use ($company) {
            $q->where('company.id', $company->id);
        });
        $results = ApiQuery::apply(request(), $query);
        return $this->success(new BaseCollection($results), 'Users retrieved');
    }

    public function store(\App\Http\Requests\StoreUserRequest $request)
    {
        $data = $request->all();
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }
        $item = User::create($data);
        return $this->success($item, 'Created', Response::HTTP_CREATED);
    }

    public function show(User $user)
    {
        return $this->success($user, 'User retrieved');
    }

    public function update(\App\Http\Requests\UpdateUserRequest $request, User $user)
    {
        $data = $request->all();
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }
        $user->update($data);
        return $this->success($user, 'Updated');
    }

    public function destroy(User $user)
    {
        $user->delete();
        return $this->success(null, 'Deleted', Response::HTTP_NO_CONTENT);
    }
}

