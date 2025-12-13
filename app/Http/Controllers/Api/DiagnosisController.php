<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Diagnosis;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class DiagnosisController extends Controller
{
    use ApiResponse;
    public function index(Request $request)
    {
        $q = $request->query('q', '');

        $query = Diagnosis::query();

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

        return $this->success($items, 'Diagnoses retrieved');
    }


    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:diagnoses,code'],
            'description' => ['required', 'string', 'max:255'],
        ]);

        $diagnosis = Diagnosis::create($validated);

        return $this->success($diagnosis, 'Created', Response::HTTP_CREATED);
    }

    public function show(Diagnosis $diagnosis)
    {
        return $this->success($diagnosis, 'Diagnosis retrieved');
    }

    public function update(Request $request, Diagnosis $diagnosis)
    {
        $validated = $request->validate([
            'code' => ['sometimes', 'required', 'string', 'max:50', 'unique:diagnoses,code,' . $diagnosis->id],
            'description' => ['sometimes', 'required', 'string', 'max:255'],
        ]);

        $diagnosis->update($validated);

        return $this->success($diagnosis, 'Updated');
    }

    public function destroy(Diagnosis $diagnosis)
    {
        $diagnosis->delete();

        return $this->success(null, 'Deleted', Response::HTTP_NO_CONTENT);
    }
}
