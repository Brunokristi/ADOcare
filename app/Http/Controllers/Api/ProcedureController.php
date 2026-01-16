<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Filters\ApiQuery;
use App\Http\Requests\DestroyManyRequest;
use App\Http\Requests\ProcedureDeleteManyRequest;
use App\Http\Requests\ProcedureStoreRequest;
use App\Http\Requests\ProcedureUpdateRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Procedure;
use App\Services\ProcedureService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Http\Resources\BaseCollection;
use Illuminate\Pagination\LengthAwarePaginator;


class ProcedureController extends Controller
{
    private ProcedureService $service;

    public function __construct(ProcedureService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        $query = Procedure::query();
        $result = ApiQuery::apply(
            $request,
            $query,
            searchable: ['code', 'description'],
            allowedFilters: [],
            defaults: [
                'sort' => 'code',
                'with' => 'insuranceCompaniesPricesMinimal',

            ]
        );

        return $this->success(new BaseCollection($result), 'Procedures retrieved');
    }


    /**
     * POST /v1/procedures
     *
     * Creates procedure + writes prices for insurers 25/24/27 into procedure_company
     */
    public function store(ProcedureStoreRequest $request)
    {
        $data = $request->validated();

        $procedure = $this->service->createWithPrices($data);

        return $this->success($procedure, 'Created', Response::HTTP_CREATED);
    }

    /**
     * GET /v1/procedures/{procedure}
     */
    public function show(Procedure $procedure)
    {
        $procedure->load(['insuranceCompaniesPricesMinimal']);
        return $this->success($procedure, 'Procedure retrieved');
    }

    /**
     * PUT/PATCH /v1/procedures/{procedure}
     *
     * Updates prices (and optionally code/description if you ever allow it)
     */
    public function update(ProcedureUpdateRequest $request, Procedure $procedure)
    {
        $data = $request->validated();

        $procedure = $this->service->updateWithPrices($procedure, $data);

        return $this->success($procedure, 'Updated');
    }

    /**
     * DELETE /v1/procedures/{procedure}
     */
    public function destroy(Procedure $procedure)
    {
        $this->service->destroy($procedure);

        return $this->success(null, 'Deleted', Response::HTTP_NO_CONTENT);
    }

    /**
     * POST /v1/procedures/bulk-delete
     * body: { ids: number[] }
     */
    public function destroyMany(DestroyManyRequest $request)
    {
        $ids = $request->validated()['ids'];

        $this->service->destroyMany($ids);

        return $this->success(null, 'Procedures deleted');
    }
}
