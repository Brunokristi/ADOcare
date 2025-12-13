<?php

namespace App\Http\Controllers\Api;

use App\Http\Filters\ApiQuery;
use App\Http\Resources\BaseCollection;
use App\Http\Responses\ApiResponse;
use App\Models\VisitText;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Http\Response;

class VisitTextController extends Controller
{
    use ApiResponse;
    public function index(Request $request)
    {
        $query = VisitText::query();
        $results = ApiQuery::apply(request(), $query);
        return $this->success(new BaseCollection($results), 'Visit texts retrieved');
    }

    public function store(\App\Http\Requests\StoreVisitTextRequest $request)
    {
        $item = VisitText::create($request->all());
        return $this->success($item, 'Created', Response::HTTP_CREATED);
    }

    public function show(VisitText $visitText)
    {
        return $this->success($visitText, 'Visit text retrieved');
    }

    public function update(\App\Http\Requests\UpdateVisitTextRequest $request, VisitText $visitText)
    {
        $visitText->update($request->all());
        return $this->success($visitText, 'Updated');
    }

    public function destroy(VisitText $visitText)
    {
        $visitText->delete();
        return $this->success(null, 'Deleted', Response::HTTP_NO_CONTENT);
    }
}
