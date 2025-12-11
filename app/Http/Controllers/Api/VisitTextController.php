<?php

namespace App\Http\Controllers\Api;

use App\Http\Filters\ApiQuery;
use App\Http\Resources\BaseCollection;
use App\Models\VisitText;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Http\Response;

class VisitTextController extends Controller
{
    public function index(Request $request)
    {
        $query = VisitText::query();
        $results = ApiQuery::apply($request, $query);
        return new BaseCollection($results);
    }

    public function store(Request $request)
    {
        $item = VisitText::create($request->all());
        return response()->json($item, Response::HTTP_CREATED);
    }

    public function show(VisitText $visitText)
    {
        return $visitText;
    }

    public function update(Request $request, VisitText $visitText)
    {
        $visitText->update($request->all());
        return response()->json($visitText);
    }

    public function destroy(VisitText $visitText)
    {
        $visitText->delete();
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
