<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Procedure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProcedureController extends Controller
{
    use ApiResponse;

    // Insurer codes you show as columns in the UI
    private const INS_CODES = ['25', '24', '27'];

    /**
     * GET /v1/procedures?q=
     *
     * Returns procedures with computed columns: price25, price24, price27
     */
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        // Map insurance code -> insurance_company_branch.id
        $insuranceIdsByCode = $this->insuranceIdsByCode(); // ['25' => 10, '24' => 11, '27' => 12]
        $id25 = $insuranceIdsByCode['25'] ?? null;
        $id24 = $insuranceIdsByCode['24'] ?? null;
        $id27 = $insuranceIdsByCode['27'] ?? null;

        // Build join subqueries for each insurer code
        $p25 = DB::table('procedure_company_prices')
            ->select('procedure_id', 'price')
            ->when($id25, fn($qq) => $qq->where('insurance_company_id', $id25));

        $p24 = DB::table('procedure_company_prices')
            ->select('procedure_id', 'price')
            ->when($id24, fn($qq) => $qq->where('insurance_company_id', $id24));

        $p27 = DB::table('procedure_company_prices')
            ->select('procedure_id', 'price')
            ->when($id27, fn($qq) => $qq->where('insurance_company_id', $id27));

        $query = Procedure::query()
            ->from('procedures as p')
            ->leftJoinSub($p25, 'pc25', fn($join) => $join->on('pc25.procedure_id', '=', 'p.id'))
            ->leftJoinSub($p24, 'pc24', fn($join) => $join->on('pc24.procedure_id', '=', 'p.id'))
            ->leftJoinSub($p27, 'pc27', fn($join) => $join->on('pc27.procedure_id', '=', 'p.id'))
            ->select([
                'p.id',
                'p.code',
                'p.description',
                DB::raw('pc25.price as price25'),
                DB::raw('pc24.price as price24'),
                DB::raw('pc27.price as price27'),
            ]);

        if ($q !== '') {
            $qLower = mb_strtolower($q);
            $query->where(function ($sub) use ($qLower) {
                $sub->whereRaw('LOWER(p.code) LIKE ?', ["%{$qLower}%"])
                    ->orWhereRaw('LOWER(p.description) LIKE ?', ["%{$qLower}%"]);
            });
        }

        $items = $query
            ->orderBy('p.code')
            ->limit(100)
            ->get();

        return $this->success($items, 'Procedures retrieved');
    }

    /**
     * POST /v1/procedures
     *
     * Creates procedure + writes prices for insurers 25/24/27 into procedure_company
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('procedures', 'code')],
            'description' => ['required', 'string', 'max:255'],

            'price25' => ['required', 'numeric', 'min:0'],
            'price24' => ['required', 'numeric', 'min:0'],
            'price27' => ['required', 'numeric', 'min:0'],
        ]);

        $insuranceIdsByCode = $this->insuranceIdsByCode();

        DB::beginTransaction();
        try {
            $procedure = Procedure::create([
                'code' => $validated['code'],
                'description' => $validated['description'],
            ]);

            $this->upsertPricesForProcedure($procedure->id, $validated, $insuranceIdsByCode);

            DB::commit();

            // Return the row shape your UI expects
            $row = $this->procedureRow($procedure->id);
            return $this->success($row, 'Created', Response::HTTP_CREATED);
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * GET /v1/procedures/{procedure}
     */
    public function show(Procedure $procedure)
    {
        $row = $this->procedureRow($procedure->id);
        return $this->success($row, 'Procedure retrieved');
    }

    /**
     * PUT/PATCH /v1/procedures/{procedure}
     *
     * Updates prices (and optionally code/description if you ever allow it)
     */
    public function update(Request $request, Procedure $procedure)
    {
        $validated = $request->validate([
            // Your UI disables these in edit, but keeping support doesn’t hurt:
            'code' => ['sometimes', 'required', 'string', 'max:50', Rule::unique('procedures', 'code')->ignore($procedure->id)],
            'description' => ['sometimes', 'required', 'string', 'max:255'],

            'price25' => ['sometimes', 'required', 'numeric', 'min:0'],
            'price24' => ['sometimes', 'required', 'numeric', 'min:0'],
            'price27' => ['sometimes', 'required', 'numeric', 'min:0'],
        ]);

        $insuranceIdsByCode = $this->insuranceIdsByCode();

        DB::beginTransaction();
        try {
            // Only update procedure fields if present
            $procedureUpdates = array_intersect_key($validated, array_flip(['code', 'description']));
            if (!empty($procedureUpdates)) {
                $procedure->update($procedureUpdates);
            }

            // Upsert any provided prices
            $this->upsertPricesForProcedure($procedure->id, $validated, $insuranceIdsByCode);

            DB::commit();

            $row = $this->procedureRow($procedure->id);
            return $this->success($row, 'Updated');
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * DELETE /v1/procedures/{procedure}
     */
    public function destroy(Procedure $procedure)
    {
        DB::beginTransaction();
        try {
            DB::table('procedure_company_prices')->where('procedure_id', $procedure->id)->delete();
            $procedure->delete();

            DB::commit();
            return $this->success(null, 'Deleted', Response::HTTP_NO_CONTENT);
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * POST /v1/procedures/bulk-delete
     * body: { ids: number[] }
     */
    public function bulkDelete(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:procedures,id'],
        ]);

        DB::beginTransaction();
        try {
            DB::table('procedure_company_prices')->whereIn('procedure_id', $validated['ids'])->delete();
            Procedure::whereIn('id', $validated['ids'])->delete();

            DB::commit();
            return $this->success(null, 'Procedures deleted');
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Helpers
     */

    private function insuranceIdsByCode(): array
    {
        return DB::table('insurance_companies')
            ->whereIn('code', self::INS_CODES)
            ->pluck('id', 'code')
            ->mapWithKeys(fn($id, $code) => [(string)$code => (int)$id])
            ->all();
    }

    private function upsertPricesForProcedure(int $procedureId, array $validated, array $insuranceIdsByCode): void
    {
        $map = [
            '25' => 'price25',
            '24' => 'price24',
            '27' => 'price27',
        ];

        foreach ($map as $code => $field) {
            if (!array_key_exists($field, $validated)) {
                continue; // update() might send only some prices
            }

            $insuranceId = $insuranceIdsByCode[$code] ?? null;
            if (!$insuranceId) {
                // If the insurer row doesn't exist, skip silently (or throw if you prefer)
                continue;
            }

            DB::table('procedure_company_prices')->updateOrInsert(
                [
                    'procedure_id' => $procedureId,
                    'insurance_company_id' => $insuranceId,
                ],
                [
                    'price' => $validated[$field],
                ]
            );
        }
    }

    private function procedureRow(int $procedureId)
    {
        $insuranceIdsByCode = $this->insuranceIdsByCode();
        $id25 = $insuranceIdsByCode['25'] ?? null;
        $id24 = $insuranceIdsByCode['24'] ?? null;
        $id27 = $insuranceIdsByCode['27'] ?? null;

        $p25 = DB::table('procedure_company_prices')->select('procedure_id', 'price')->when($id25, fn($q) => $q->where('insurance_company_id', $id25));
        $p24 = DB::table('procedure_company_prices')->select('procedure_id', 'price')->when($id24, fn($q) => $q->where('insurance_company_id', $id24));
        $p27 = DB::table('procedure_company_prices')->select('procedure_id', 'price')->when($id27, fn($q) => $q->where('insurance_company_id', $id27));

        return DB::table('procedures as p')
            ->leftJoinSub($p25, 'pc25', fn($join) => $join->on('pc25.procedure_id', '=', 'p.id'))
            ->leftJoinSub($p24, 'pc24', fn($join) => $join->on('pc24.procedure_id', '=', 'p.id'))
            ->leftJoinSub($p27, 'pc27', fn($join) => $join->on('pc27.procedure_id', '=', 'p.id'))
            ->where('p.id', $procedureId)
            ->select([
                'p.id',
                'p.code',
                'p.description',
                DB::raw('pc25.price as price25'),
                DB::raw('pc24.price as price24'),
                DB::raw('pc27.price as price27'),
            ])
            ->first();
    }
}
