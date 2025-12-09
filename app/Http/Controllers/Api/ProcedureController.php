<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Procedure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ProcedureController extends Controller
{
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

        return $query
            ->orderBy('code')
            ->limit(50)
            ->get(['id', 'code', 'description']);
    }

    /**
     * POST /v1/procedures
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'code'        => ['required', 'string', 'max:50', 'unique:procedures,code'],
            'description' => ['required', 'string', 'max:255'],
        ]);

        $procedure = Procedure::create($validated);

        return response()->json($procedure, Response::HTTP_CREATED);
    }

    /**
     * GET /v1/procedures/{procedure}
     */
    public function show(Procedure $procedure)
    {
        return $procedure;
    }

    /**
     * PUT/PATCH /v1/procedures/{procedure}
     */
    public function update(Request $request, Procedure $procedure)
    {
        $validated = $request->validate([
            'code'        => ['sometimes', 'required', 'string', 'max:50', 'unique:procedures,code,' . $procedure->id],
            'description' => ['sometimes', 'required', 'string', 'max:255'],
        ]);

        $procedure->update($validated);

        return response()->json($procedure);
    }

    /**
     * DELETE /v1/procedures/{procedure}
     */
    public function destroy(Procedure $procedure)
    {
        $procedure->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
