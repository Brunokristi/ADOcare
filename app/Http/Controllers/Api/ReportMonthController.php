<?php

namespace App\Http\Controllers\Api;

use App\Http\Filters\ApiQuery;
use App\Http\Resources\BaseCollection;
use App\Http\Responses\ApiResponse;
use App\Models\ReportMonth;
use Illuminate\Http\Request;
use \App\Http\Controllers\Controller;
use Illuminate\Http\Response;

class ReportMonthController extends Controller
{

    public function index(Request $request)
    {
        $query = ReportMonth::query();
        $results = ApiQuery::apply(request(), $query);
        return $this->success(new BaseCollection($results), 'Report months retrieved');
    }

    public function store(\App\Http\Requests\StoreReportMonthRequest $request)
    {
        $item = ReportMonth::create($request->all());
        return $this->success($item, 'Created', Response::HTTP_CREATED);
    }

    public function show(ReportMonth $reportMonth)
    {
        return $this->success($reportMonth, 'Report month retrieved');
    }

    public function update(\App\Http\Requests\UpdateReportMonthRequest $request, ReportMonth $reportMonth)
    {
        $reportMonth->update($request->all());
        return $this->success($reportMonth, 'Updated');
    }

    public function destroy(ReportMonth $reportMonth)
    {
        $reportMonth->delete();
        return $this->success(null, 'Deleted', Response::HTTP_NO_CONTENT);
    }
}
