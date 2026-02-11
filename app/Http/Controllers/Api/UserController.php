<?php

namespace App\Http\Controllers\Api;

use App\Http\Filters\ApiQuery;
use App\Http\Resources\BaseCollection;
use App\Http\Resources\UserCollection;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\Request;
use \App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    protected UserService $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    public function index(Request $request)
    {
        $query = User::query();
        $results = ApiQuery::apply(request(), $query);
        return $this->success(new UserCollection($results), 'Users retrieved');
    }


    public function store(\App\Http\Requests\StoreUserRequest $request)
    {
        $data = $request->all();
        $item = $this->userService->create($data);
        return $this->success($item, 'Created', Response::HTTP_CREATED);
    }

    public function show(User $user)
    {
        return $this->success($user->load(['branches', 'roles', 'company']), 'User retrieved');
    }

    public function update(\App\Http\Requests\UpdateUserRequest $request, User $user)
    {
        $data = $request->all();
        $updated = $this->userService->update($user, $data);
        return $this->success($updated, 'Updated');
    }

    public function destroy(User $user)
    {
        $user->delete();
        return $this->success(null, 'Deleted', Response::HTTP_NO_CONTENT);
    }
}

