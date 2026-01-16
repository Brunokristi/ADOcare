<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Http\Requests\MacroRequest;
use App\Models\Macro;
use Illuminate\Http\Request;
use App\Http\Requests\DestroyManyRequest;
use Illuminate\Http\Response;
use App\Http\Filters\ApiQuery;
use App\Http\Resources\BaseCollection;


class MacroController extends Controller
{
    public function __construct()
    {
        $this->middleware('api.auth')->only(['index']);
    }

    /**
     * GET /v1/macros?q=
     * Returns only macros belonging to logged-in user
     */
    public function index()
    {
        $userId = request()->user()->id;

        $query = Macro::query()
            ->where('user_id', $userId);

        // Apply server-side search/sort/pagination
        $results = ApiQuery::apply(
            request(),
            $query,
            ['name', 'abbreviation', 'text'],
            ['name', 'abbreviation', 'created_at'],
            ['name' => 'asc']
        );

        return $this->success(new BaseCollection($results), 'Macros retrieved');
    }


    /**
     * POST /v1/macros
     */
    public function store(MacroRequest $request)
    {
        $userId = (int) ($request->user()?->id);

        $validated = $request->validated();

        $macro = Macro::create(array_merge(
            $validated,
            ['user_id' => $userId]
        ));

        return $this->success($macro, 'Created', Response::HTTP_CREATED);
    }

    /**
     * GET /v1/macros/{macro}
     */
    public function show(Request $request, Macro $macro)
    {
        return $this->success($macro->only(['id', 'name', 'abbreviation', 'text', 'user_id']), 'Macro retrieved');
    }

    /**
     * PUT/PATCH /v1/macros/{macro}
     */
    public function update(MacroRequest $request, Macro $macro)
    {
        $validated = $request->validated();

        $macro->update($validated);

        return $this->success($macro->only(['id', 'name', 'abbreviation', 'text', 'user_id']), 'Updated');
    }

    /**
     * DELETE /v1/macros/{macro}
     */
    public function destroy(Request $request, Macro $macro)
    {
        $macro->delete();

        return $this->success(null, 'Deleted', Response::HTTP_NO_CONTENT);
    }

    /**
     * POST /v1/macros/bulk-delete
     * body: { ids: number[] }
     */
    public function destroyMany(DestroyManyRequest $request)
    {
        $userId = (int) ($request->user()?->id);
        $validated = $request->validated();

        // Only delete user-owned macros
        Macro::where('user_id', $userId)
            ->whereIn('id', $validated['ids'])
            ->delete();

        return $this->success(null, 'Deleted');
    }

}
