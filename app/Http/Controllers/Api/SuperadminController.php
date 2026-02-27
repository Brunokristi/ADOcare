<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\User;
use App\Models\Document;
use Illuminate\Http\Request;

class SuperadminController extends Controller
{
    /**
     * Return a small set of global statistics for the superadmin dashboard.
     *
     * This endpoint is intentionally lightweight and does not paginate; it's
     * only used by the Vue page to show summary cards.  All values are simple
     * count() calls so they are fast even on large datasets.
     */
    public function statistics(Request $request)
    {
        // authorization is handled by the route middleware (role:superadmin)

        $stats = [
            'companies' => Company::count(),
            'branches' => Branch::count(),
            'users' => User::count(),
            'patients' => Patient::count(),
            'doctors' => Doctor::count(),
            'documents' => Document::count(),
        ];

        return $this->success($stats, 'Superadmin statistics retrieved');
    }
}
