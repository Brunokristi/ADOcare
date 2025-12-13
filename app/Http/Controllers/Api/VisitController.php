<?php

namespace App\Http\Controllers\Api;

use App\Http\Filters\ApiQuery;
use App\Http\Resources\BaseCollection;
use App\Http\Responses\ApiResponse;
use App\Models\Visit;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Http\Response;

class VisitController extends Controller
{
    use ApiResponse;
    public function index(Request $request)
    {
        $query = Visit::query();
        $results = ApiQuery::apply(request(), $query);
        return $this->success(new BaseCollection($results), 'Visits retrieved');
    }

    public function store(\App\Http\Requests\StoreVisitRequest $request)
    {
        $item = Visit::create($request->all());
        return $this->success($item, 'Created', Response::HTTP_CREATED);
    }

    public function show(Visit $visit)
    {
        return $this->success($visit, 'Visit retrieved');
    }

    public function update(\App\Http\Requests\UpdateVisitRequest $request, Visit $visit)
    {
        $visit->update($request->all());
        return $this->success($visit, 'Updated');
    }

    public function destroy(Visit $visit)
    {
        $visit->delete();
        return $this->success(null, 'Deleted', Response::HTTP_NO_CONTENT);
    }
}
