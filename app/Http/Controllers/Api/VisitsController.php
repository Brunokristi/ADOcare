<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Visits\MonthTimelineRequest;
use App\Http\Requests\Visits\CheckCalculationStatusRequest;
use App\Http\Requests\Visits\DayTotalsRequest;
use App\Http\Requests\Visits\MonthTotalsRequest;
use App\Services\VisitsService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class VisitsController extends Controller
{
    private VisitsService $service;

    public function __construct(VisitsService $service)
    {
        $this->service = $service;
    }
    /**
     * Queue a timeline calculation for the given month.
     *
     * @group Visits
     * @bodyParam month date required Any date within the month to calculate. Example: 2021-01-01
     * @bodyParam branch_id int required Branch ID. Example: 1
     * @bodyParam user_id int|null Optional user id; defaults to authenticated user.
     * @bodyParam persist bool|null Whether to persist results in the visits table. Example: true
     * @response 200 {"message":"...","data":{"queued":true}}
     */
    public function monthTimeline(MonthTimelineRequest $request)
    {
        $data = $request->validated();

        $this->service->requestTimeline($data);

        // response structure (queued flag) preserved for backwards compatibility
        return $this->success(
            ['queued' => true],
            'Timeline calculation has been queued and will be processed in the background.'
        );
    }

    /**
     * Return status of the previously queued timeline calculation.
     *
     * @group Visits
     * @queryParam month date required Month used in the calculation. Example: 2021-01-01
     * @queryParam branch_id int required Branch ID. Example: 1
     * @queryParam user_id int|null Optional user id; defaults to authenticated user.
     * @response 200 {"message":"OK","data":{"status":"pending"}}
     */
    public function checkCalculationStatus(CheckCalculationStatusRequest $request)
    {
        $data = $request->validated();

        $result = $this->service->checkCalculationStatus(
            $data['month'],
            $data['branch_id'],
            $data['user_id'] ?? null
        );

        return $this->success($result);
    }

    /**
     * Get aggregated visit totals for a specific day.
     *
     * @group Visits
     * @queryParam date string required Date (YYYY-MM-DD). Example: 2021-01-15
     * @queryParam branch_id int required Branch ID. Example: 1
     * @queryParam user_id int|null Optional user ID (defaults to current user).
     * @queryParam include_on_location bool|null Include on-location seconds in total. Default true.
     * @response 200 {"message":"OK","data":{...}}
     */
    public function dayTotals(DayTotalsRequest $request)
    {
        $data = $request->validated();

        $result = $this->service->getDayTotals(
            $data['date'],
            $data['branch_id'],
            $data['user_id'] ?? null,
            $data['include_on_location'] ?? true
        );

        return $this->success($result);
    }

    /**
     * Get aggregated visit totals for an entire month.
     *
     * @group Visits
     * @queryParam month date required Any date within the month. Example: 2021-01-15
     * @queryParam branch_id int required Branch ID. Example: 1
     * @queryParam user_id int|null Optional user ID (defaults to current user).
     * @queryParam include_on_location bool|null Include on-location seconds in total. Default true.
     * @response 200 {"message":"OK","data":{...}}
     */
    public function monthTotals(MonthTotalsRequest $request)
    {
        $data = $request->validated();

        $result = $this->service->getMonthTotals(
            $data['month'],
            $data['branch_id'],
            $data['user_id'] ?? null,
            $data['include_on_location'] ?? true
        );

        return $this->success($result);
    }

    /**
     * Placeholder for patientTimeForDay endpoint - kept because route exists,
     * but no business logic was defined previously. Returns 501 Not Implemented.
     *
     * @group Visits
     */
    public function patientTimeForDay()
    {
        return $this->error('Endpoint not implemented', 501);
    }
}