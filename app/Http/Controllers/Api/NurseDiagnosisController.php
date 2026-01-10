<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Filters\ApiQuery;
use App\Models\NurseDiagnosis;
use Illuminate\Http\Request;

class NurseDiagnosisController extends Controller
{
    public function index()
    {
        $query = NurseDiagnosis::query();

        $results = ApiQuery::apply(request(), $query,
            searchable: ['code', 'normalized_code', 'description'],
            allowedFilters: ['code', 'normalized_code'],
                defaults: ['code']
    );
        return $this->success($results, 'Nurse diagnoses retrieved');
    }
}
