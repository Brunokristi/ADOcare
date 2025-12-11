<?php

namespace App\Http\Controllers\Api;

use App\Http\Filters\ApiQuery;
use App\Http\Resources\BaseCollection;
use App\Models\Visit;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Http\Response;

class VisitController extends Controller
{
    public function index(Request $request)
    {
        $query = Visit::query();
        $results = ApiQuery::apply($request, $query);
        return new BaseCollection($results);
    }

    public function store(Request $request)
    {
        $item = Visit::create($request->all());
        return response()->json($item, Response::HTTP_CREATED);
    }

    public function show(Visit $visit)
    {
        return $visit;
    }

    public function update(Request $request, Visit $visit)
    {
        $visit->update($request->all());
        return response()->json($visit);
    }

    public function destroy(Visit $visit)
    {
        $visit->delete();
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
