<?php

namespace App\Http\Controllers\Api;

use App\Http\Filters\ApiQuery;
use App\Http\Resources\BaseCollection;
use App\Http\Responses\ApiResponse;
use App\Models\TextBlock;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Http\Response;

class TextBlockController extends Controller
{
    use ApiResponse;
    public function index(Request $request)
    {
        $query = TextBlock::query();
        $results = ApiQuery::apply(request(), $query);
        return $this->success(new BaseCollection($results), 'Text blocks retrieved');
    }

    public function store(\App\Http\Requests\StoreTextBlockRequest $request)
    {
        $item = TextBlock::create($request->all());
        return $this->success($item, 'Created', Response::HTTP_CREATED);
    }

    public function show(TextBlock $textBlock)
    {
        return $this->success($textBlock, 'Text block retrieved');
    }

    public function update(\App\Http\Requests\UpdateTextBlockRequest $request, TextBlock $textBlock)
    {
        $textBlock->update($request->all());
        return $this->success($textBlock, 'Updated');
    }

    public function destroy(TextBlock $textBlock)
    {
        $textBlock->delete();
        return $this->success(null, 'Deleted', Response::HTTP_NO_CONTENT);
    }
}
