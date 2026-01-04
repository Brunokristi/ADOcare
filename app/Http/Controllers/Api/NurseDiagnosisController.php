<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NurseDiagnosis;
use Illuminate\Http\Request;

class NurseDiagnosisController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $query = NurseDiagnosis::query()
            ->when($q !== '', function ($qb) use ($q) {
                $qb->whereRaw(
                    "
                    regexp_replace(
                        lower(unaccent(code)),
                        '[^a-z0-9]+',
                        '',
                        'g'
                    ) LIKE ?
                    OR
                    regexp_replace(
                        lower(unaccent(description)),
                        '[^a-z0-9]+',
                        '',
                        'g'
                    ) LIKE ?
                    ",
                    [
                        '%' . $this->normalize($q) . '%',
                        '%' . $this->normalize($q) . '%',
                    ]
                );
            })
            ->orderBy('code');

        $paginate = filter_var(
            $request->query('paginate', true),
            FILTER_VALIDATE_BOOLEAN
        );

        if ($paginate === false) {
            return $query->get();
        }

        return $query->paginate(
            min((int) $request->query('per_page', 20), 200)
        );
    }

    /**
     * Normalize search string (same logic as SQL)
     */
    private function normalize(string $value): string
    {
        $value = mb_strtolower($value, 'UTF-8');
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        return preg_replace('/[^a-z0-9]+/', '', $value);
    }
}
