<?php

namespace App\Http\Controllers\Api;

use App\Http\Filters\ApiQuery;
use App\Http\Resources\BaseCollection;
use App\Models\ReportMonth;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Http\Response;

class ReportMonthController extends Controller
{
    public function index(Request $request)
    {
        $query = ReportMonth::query();
        $results = ApiQuery::apply($request, $query);
        return new BaseCollection($results);
    }

    public function store(Request $request)
    {
        $item = ReportMonth::create($request->all());
        return response()->json($item, Response::HTTP_CREATED);
    }

    public function show(ReportMonth $reportMonth)
    {
        return $reportMonth;
    }

    public function update(Request $request, ReportMonth $reportMonth)
    {
        $reportMonth->update($request->all());
        return response()->json($reportMonth);
    }

    public function destroy(ReportMonth $reportMonth)
    {
        $reportMonth->delete();
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
