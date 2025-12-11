<?php

namespace App\Http\Controllers\Api;

use App\Http\Filters\ApiQuery;
use App\Http\Resources\BaseCollection;
use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Http\Response;

class BranchController extends Controller
{
    public function index(Request $request)
    {
        $query = Branch::query();
        $results = ApiQuery::apply($request, $query);
        return new BaseCollection($results);
    }

    public function patients(Branch $branch)
    {
        $query = $branch->patients()->query();
        $results = ApiQuery::apply(request(), $query);
        return new BaseCollection($results);
    }

    public function store(Request $request)
    {
        $item = Branch::create($request->all());
        return response()->json($item, Response::HTTP_CREATED);
    }

    public function show(Branch $branch)
    {
        return $branch;
    }

    public function update(Request $request, Branch $branch)
    {
        $branch->update($request->all());
        return response()->json($branch);
    }

    public function destroy(Branch $branch)
    {
        $branch->delete();
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
