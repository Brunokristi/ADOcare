<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Macro;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Http\Filters\ApiQuery;
use App\Http\Resources\BaseCollection;


class MacroController extends Controller
{
    use ApiResponse;

    /**
     * GET /v1/macros?q=
     * Returns only macros belonging to logged-in user
     */
    public function index(Request $request)
    {
        $userId = (int) ($request->user()?->id);
        if ($userId <= 0) {
            return $this->error('Unauthenticated', 401);
        }

        $query = Macro::query()
            ->where('user_id', $userId);

        // Default alphabetical sort if client doesn't provide ?sort=
        if (!$request->filled('sort')) {
            $query->orderBy('name');
        }

        // Apply server-side search/sort/pagination
        $results = ApiQuery::apply(
            $request,
            $query,
            searchable: ['name', 'abbreviation', 'text']
        );

        return $this->success(new BaseCollection($results), 'Macros retrieved');
    }


    /**
     * POST /v1/macros
     */
    public function store(Request $request)
    {
        $userId = (int) ($request->user()?->id);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'abbreviation' => ['required', 'string', 'max:50'],
            'text' => ['required', 'string'],
        ]);

        $macro = Macro::create([
            'name' => $validated['name'],
            'abbreviation' => $validated['abbreviation'],
            'text' => $validated['text'],
            'user_id' => $userId,
        ]);

        return $this->success($macro, 'Created', Response::HTTP_CREATED);
    }

    /**
     * GET /v1/macros/{macro}
     */
    public function show(Request $request, Macro $macro)
    {
        $this->authorizeMacro($request, $macro);

        return $this->success($macro->only(['id', 'name', 'abbreviation', 'text', 'user_id']), 'Macro retrieved');
    }

    /**
     * PUT/PATCH /v1/macros/{macro}
     */
    public function update(Request $request, Macro $macro)
    {
        $this->authorizeMacro($request, $macro);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'abbreviation' => ['sometimes', 'required', 'string', 'max:50'],
            'text' => ['sometimes', 'required', 'string'],
        ]);

        $macro->update($validated);

        return $this->success($macro->only(['id', 'name', 'abbreviation', 'text', 'user_id']), 'Updated');
    }

    /**
     * DELETE /v1/macros/{macro}
     */
    public function destroy(Request $request, Macro $macro)
    {
        $this->authorizeMacro($request, $macro);

        $macro->delete();

        return $this->success(null, 'Deleted', Response::HTTP_NO_CONTENT);
    }

    /**
     * POST /v1/macros/bulk-delete
     * body: { ids: number[] }
     */
    public function bulkDelete(Request $request)
    {
        $userId = (int) ($request->user()?->id);

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        // Only delete user-owned macros
        Macro::where('user_id', $userId)
            ->whereIn('id', $validated['ids'])
            ->delete();

        return $this->success(null, 'Deleted');
    }

    private function authorizeMacro(Request $request, Macro $macro): void
    {
        $userId = (int) ($request->user()?->id);
        abort_if($macro->user_id !== $userId, Response::HTTP_FORBIDDEN, 'Forbidden');
    }
}
