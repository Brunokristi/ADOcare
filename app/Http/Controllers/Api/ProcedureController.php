<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Procedure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ProcedureController extends Controller
{
    use ApiResponse;
    public function index(Request $request)
    {
        $q = $request->query('q', '');

        $query = Procedure::query();

        if ($q !== '') {
            $qLower = mb_strtolower($q);

            $query->where(function ($sub) use ($qLower) {
                $sub->whereRaw('LOWER(code) LIKE ?', ["%{$qLower}%"])
                    ->orWhereRaw('LOWER(description) LIKE ?', ["%{$qLower}%"]);
            });
        }

        $items = $query
            ->orderBy('code')
            ->limit(50)
            ->get(['id', 'code', 'description']);

        return $this->success($items, 'Procedures retrieved');
    }

    /**
     * POST /v1/procedures
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:procedures,code'],
            'description' => ['required', 'string', 'max:255'],
        ]);

        $procedure = Procedure::create($validated);

        return $this->success($procedure, 'Created', Response::HTTP_CREATED);
    }

    /**
     * GET /v1/procedures/{procedure}
     */
    public function show(Procedure $procedure)
    {
        return $this->success($procedure, 'Procedure retrieved');
    }

    /**
     * PUT/PATCH /v1/procedures/{procedure}
     */
    public function update(Request $request, Procedure $procedure)
    {
        $validated = $request->validate([
            'code' => ['sometimes', 'required', 'string', 'max:50', 'unique:procedures,code,' . $procedure->id],
            'description' => ['sometimes', 'required', 'string', 'max:255'],
        ]);

        $procedure->update($validated);

        return $this->success($procedure, 'Updated');
    }

    /**
     * DELETE /v1/procedures/{procedure}
     */
    public function destroy(Procedure $procedure)
    {
        $procedure->delete();

        return $this->success(null, 'Deleted', Response::HTTP_NO_CONTENT);
    }
}
