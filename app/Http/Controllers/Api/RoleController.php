<?php

namespace App\Http\Controllers\Api;

use App\Http\Filters\ApiQuery;
use App\Http\Resources\BaseCollection;
use App\Http\Responses\ApiResponse;
use App\Models\Role;
use Illuminate\Http\Request;
use \App\Http\Controllers\Controller;
use Illuminate\Http\Response;

class RoleController extends Controller
{

    public function index(Request $request)
    {
        $query = Role::query();
        $results = ApiQuery::apply(request(), $query);
        return $this->success(new BaseCollection($results), 'Roles retrieved');
    }

    public function store(\App\Http\Requests\StoreRoleRequest $request)
    {
        $item = Role::create($request->all());
        return $this->success($item, 'Created', Response::HTTP_CREATED);
    }

    public function show(Role $role)
    {
        return $this->success($role, 'Role retrieved');
    }

    public function update(\App\Http\Requests\UpdateRoleRequest $request, Role $role)
    {
        $role->update($request->all());
        return $this->success($role, 'Updated');
    }

    public function destroy(Role $role)
    {
        $role->delete();
        return $this->success(null, 'Deleted', Response::HTTP_NO_CONTENT);
    }
}
