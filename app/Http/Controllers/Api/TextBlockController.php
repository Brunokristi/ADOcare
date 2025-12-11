<?php

namespace App\Http\Controllers\Api;

use App\Http\Filters\ApiQuery;
use App\Http\Resources\BaseCollection;
use App\Models\TextBlock;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Http\Response;

class TextBlockController extends Controller
{
    public function index(Request $request)
    {
        $query = TextBlock::query();
        $results = ApiQuery::apply($request, $query);
        return new BaseCollection($results);
    }

    public function store(Request $request)
    {
        $item = TextBlock::create($request->all());
        return response()->json($item, Response::HTTP_CREATED);
    }

    public function show(TextBlock $textBlock)
    {
        return $textBlock;
    }

    public function update(Request $request, TextBlock $textBlock)
    {
        $textBlock->update($request->all());
        return response()->json($textBlock);
    }

    public function destroy(TextBlock $textBlock)
    {
        $textBlock->delete();
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
