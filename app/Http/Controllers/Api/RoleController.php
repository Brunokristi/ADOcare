<?php

namespace App\Http\Controllers\Api;

use App\Http\Filters\ApiQuery;
use App\Http\Resources\BaseCollection;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Http\Response;

class RoleController extends Controller
{
    public function index(Request $request)
    {
        $query = Role::query();
        $results = ApiQuery::apply($request, $query);
        return new BaseCollection($results);
    }

    public function store(Request $request)
    {
        $item = Role::create($request->all());
        return response()->json($item, Response::HTTP_CREATED);
    }

    public function show(Role $role)
    {
        return $role;
    }

    public function update(Request $request, Role $role)
    {
        $role->update($request->all());
        return response()->json($role);
    }

    public function destroy(Role $role)
    {
        $role->delete();
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
