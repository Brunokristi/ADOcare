<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ManagerController extends Controller
{
    public function userStatistics(Request $request)
    {
        $month = $request->string('month')->toString(); // "2026-01"
        $dateFrom = $request->input('date_from');
        $dateTo   = $request->input('date_to');

        // Pagination params from frontend table
        $perPage = (int)$request->input('per_page', 50);
        $page = (int)$request->input('page', 1);
        $offset = ($page - 1) * $perPage;

        // Set date range
        if ($dateFrom && $dateTo) {
            $from = $dateFrom;
            $to   = $dateTo;
        } else {
            // default: current month if not provided
            $month = $month ?: now()->format('Y-m');
            $from = $month . '-01';
            $to = date('Y-m-d', strtotime($from . ' +1 month'));
        }

        $twoMonthsBack = date('Y-m-d', strtotime($from . ' -1 month'));

        $user = $request->user();
        $company = $user->company;

        if (!$company) {
            return response()->json([
                'message' => 'No company associated with user',
                'data' => [],
                'meta' => [
                    'date_from' => $from,
                    'date_to' => $to,
                    'companies' => [],
                ],
            ]);
        }

        // Get company branch ids
        $branchIds = DB::table('branches')
            ->where('company_id', $company->id)
            ->pluck('id')
            ->toArray();

        if (empty($branchIds)) {
            return response()->json([
                'message' => 'No branches found for company',
                'data' => [],
                'meta' => [
                    'date_from' => $from ?? now()->format('Y-m-d'),
                    'date_to' => $to ?? now()->addMonth()->format('Y-m-d'),
                    'companies' => [],
                ],
            ]);
        }

        // load insurance companies (to build columns)
        $companies = DB::table('insurance_companies')
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        // long rows: (branch, user, insurance) -> patients_count
        $placeholders = implode(',', array_fill(0, count($branchIds), '?'));
        $rows = DB::select(
            "
            SELECT
              pp.branch_id,
              b.address AS branch_name,

              pp.user_id,
              COALESCE(u.first_name, '') AS user_first_name,
              COALESCE(u.last_name, '')  AS user_last_name,

              p.insurance_company_id,
              ic.name AS insurance_company_name,

              COUNT(DISTINCT pp.patient_id) AS patients_count,
              SUM(pp.quantity) AS total_points,
              COUNT(DISTINCT CASE 
                WHEN (
                  SELECT COUNT(*) FROM patient_points pp2 
                  WHERE pp2.patient_id = pp.patient_id 
                  AND pp2.user_id = pp.user_id
                  AND pp2.branch_id = pp.branch_id
                  AND pp2.date >= ?
                  AND pp2.date < ?
                ) > 14 
                THEN pp.patient_id ELSE NULL 
              END) AS chronic_patients_count

            FROM patient_points pp
            JOIN patients p
              ON p.id = pp.patient_id

            LEFT JOIN branches b
              ON b.id = pp.branch_id

            LEFT JOIN users u
              ON u.id = pp.user_id

            LEFT JOIN insurance_companies ic
              ON ic.id = p.insurance_company_id

            WHERE pp.branch_id IN (" . $placeholders . ")
              AND pp.date >= ?
              AND pp.date <  ?

            GROUP BY
              pp.branch_id, b.address,
              pp.user_id, u.first_name, u.last_name,
              p.insurance_company_id, ic.name

            ORDER BY
              b.address NULLS LAST,
              u.first_name, u.last_name
            ",
            array_merge([$twoMonthsBack, $to], $branchIds, [$from, $to])
        );

        $wide = [];
        foreach ($rows as $r) {
            $key = $r->branch_id . ':' . $r->user_id;

            if (!isset($wide[$key])) {
                $wide[$key] = [
                    'id' => $key,
                    'branch_id' => $r->branch_id,
                    'branch_name' => $r->branch_name,
                    'user_id' => $r->user_id,
                    'user_name' => trim(($r->user_first_name ?? '') . ' ' . ($r->user_last_name ?? '')),
                    'patients_total' => 0,
                    'chronic_patients_count' => 0,
                    'points_total' => 0,
                ];

                // initialize all insurance columns to 0
                foreach ($companies as $c) {
                    $wide[$key]['insurance_' . $c->id] = 0;
                }
            }

            $col = 'insurance_' . (int)$r->insurance_company_id;
            $wide[$key][$col] = (int)$r->patients_count;

            $wide[$key]['patients_total'] += (int)$r->patients_count;
            $wide[$key]['chronic_patients_count'] += (int)$r->chronic_patients_count;
            $wide[$key]['points_total'] += (int)$r->total_points;
        }

        $data = array_values($wide);
        usort($data, function ($a, $b) {
            return [$a['branch_name'], $a['user_name']] <=> [$b['branch_name'], $b['user_name']];
        });

        // Apply pagination on the aggregated data
        $total = count($data);
        $paginated = array_slice($data, $offset, $perPage);

        // Debug logging
        \Log::info('ManagerController::userStatistics', [
            'month' => $month,
            'from' => $from,
            'to' => $to,
            'two_months_back' => $twoMonthsBack,
            'user_id' => $user->id,
            'company_id' => $company->id,
            'branch_ids' => $branchIds,
            'raw_rows_count' => count($rows),
            'data_count' => count($data),
            'companies_count' => count($companies),
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'paginated_count' => count($paginated),
        ]);

        return response()->json([
            'message' => 'User statistics retrieved',
            'data' => $paginated,
            'meta' => [
                'date_from' => $from,
                'date_to' => $to,
                'companies' => $companies,
                'total' => $total,
                'per_page' => $perPage,
                'page' => $page,
                'last_page' => ceil($total / $perPage),
            ],
        ]);
    }

    public function doctorStatistics(Request $request)
    {
        $month = $request->string('month')->toString(); // "2026-01"
        $dateFrom = $request->input('date_from');
        $dateTo   = $request->input('date_to');

        // Set date range
        if ($dateFrom && $dateTo) {
            $from = $dateFrom;
            $to   = $dateTo;
        } else {
            // default: current month if not provided
            $month = $month ?: now()->format('Y-m');
            $from = $month . '-01';
            $to = date('Y-m-d', strtotime($from . ' +1 month'));
        }

        $user = $request->user();
        $company = $user->company;

        if (!$company) {
            return response()->json([
                'message' => 'No company associated with user',
                'data' => [],
            ]);
        }

        // Get company branch ids
        $branchIds = DB::table('branches')
            ->where('company_id', $company->id)
            ->pluck('id')
            ->toArray();

        if (empty($branchIds)) {
            return response()->json([
                'message' => 'No branches found for company',
                'data' => [],
            ]);
        }

        // Query doctors and their patient counts for the month
        $placeholders = implode(',', array_fill(0, count($branchIds), '?'));
        $rows = DB::select(
            "
            SELECT
              d.id as doctor_id,
              CONCAT(COALESCE(d.title, ''), ' ', COALESCE(d.first_name, ''), ' ', COALESCE(d.last_name, '')) as doctor_name,
              COUNT(DISTINCT pp.patient_id) as patients_count

            FROM doctors d
            LEFT JOIN patient_points pp
              ON pp.doctor_id = d.id
              AND pp.branch_id IN (" . $placeholders . ")
              AND pp.date >= ?
              AND pp.date < ?

            WHERE d.id IN (
              SELECT DISTINCT pp.doctor_id
              FROM patient_points pp
              WHERE pp.branch_id IN (" . $placeholders . ")
                AND pp.date >= ?
                AND pp.date < ?
            )

            GROUP BY d.id, d.title, d.first_name, d.last_name
            ORDER BY patients_count DESC, doctor_name ASC
            ",
            array_merge($branchIds, [$from, $to], $branchIds, [$from, $to])
        );

        $data = array_map(function($row) {
            return [
                'id' => $row->doctor_id,
                'doctor_id' => $row->doctor_id,
                'doctor_name' => trim($row->doctor_name),
                'patients_count' => (int)$row->patients_count,
            ];
        }, $rows);

        return response()->json([
            'message' => 'Doctor statistics retrieved',
            'data' => $data,
        ]);
    }

    public function userTotals(Request $request)
    {
        $month = $request->string('month')->toString(); // "2026-01"

        $user = $request->user();
        $company = $user->company;

        if (!$company) {
            return response()->json([
                'message' => 'No company associated with user',
                'data' => [],
                'meta' => ['companies' => []],
            ]);
        }

        // Get all users of the company
        $userIds = DB::table('users')
            ->where('company_id', $company->id)
            ->pluck('id')
            ->toArray();

        if (empty($userIds)) {
            return response()->json([
                'message' => 'No users found for company',
                'data' => [],
                'meta' => ['companies' => []],
            ]);
        }

        // Load insurance companies for columns
        $insuranceCompanies = DB::table('insurance_companies')
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        // Query totals table broken down by user and insurance company
        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $rows = DB::select(
            "
            SELECT
              u.id as user_id,
              CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) as user_name,
              t.month,
              t.branch_id,
              b.address as branch_name,
              t.insurance_company_id,
              ic.name as insurance_company_name,
              t.points_total,
              t.kilometers_total

            FROM totals t
            JOIN users u ON u.id = t.user_id
            LEFT JOIN insurance_companies ic ON ic.id = t.insurance_company_id
            LEFT JOIN branches b ON b.id = t.branch_id

            WHERE t.user_id IN (" . $placeholders . ")
              AND t.month = ?

            ORDER BY u.first_name, u.last_name, b.address, ic.name
            ",
            array_merge($userIds, [$month])
        );

        // Transform into pivoted format with two rows per user per branch (Doprava, Výkony)
        $byUserBranch = [];
        foreach ($rows as $r) {
            $userId = $r->user_id;
            $branchId = $r->branch_id;
            $userName = trim($r->user_name);
            $branchName = $r->branch_name ?? 'Unknown';
            
            $key = $userId . ':' . ($branchId ?? 'null');
            
            if (!isset($byUserBranch[$key])) {
                $byUserBranch[$key] = [
                    'user_id' => $userId,
                    'user_name' => $userName,
                    'branch_id' => $branchId,
                    'branch_name' => $branchName,
                ];
            }

            $col = 'insurance_' . (int)$r->insurance_company_id;
            
            // Initialize columns if not exists
            if (!isset($byUserBranch[$key][$col . '_points'])) {
                $byUserBranch[$key][$col . '_points'] = 0;
                $byUserBranch[$key][$col . '_km'] = 0;
            }

            $byUserBranch[$key][$col . '_points'] += (float)$r->points_total;
            $byUserBranch[$key][$col . '_km'] += (float)$r->kilometers_total;
        }

        // Create two rows per user per branch: one for Výkony (points), one for Doprava (km)
        $data = [];
        foreach ($byUserBranch as $userBranchRow) {
            // Initialize all company columns to 0
            $baseRow = [
                'user_id' => $userBranchRow['user_id'],
                'user_name' => $userBranchRow['user_name'],
                'branch_id' => $userBranchRow['branch_id'],
                'branch_name' => $userBranchRow['branch_name'],
                'month' => $month,
            ];

            foreach ($insuranceCompanies as $ic) {
                $baseRow['insurance_' . $ic->id . '_points'] = 0;
                $baseRow['insurance_' . $ic->id . '_km'] = 0;
            }

            // Copy values
            foreach ($insuranceCompanies as $ic) {
                $col = 'insurance_' . $ic->id;
                $baseRow[$col . '_points'] = $userBranchRow[$col . '_points'] ?? 0;
                $baseRow[$col . '_km'] = $userBranchRow[$col . '_km'] ?? 0;
            }

            // Add Výkony (points) row
            $data[] = array_merge($baseRow, ['type' => 'Výkony']);

            // Add Doprava (kilometers) row
            $data[] = array_merge($baseRow, ['type' => 'Doprava']);
        }

        return response()->json([
            'message' => 'User totals retrieved',
            'data' => $data,
            'meta' => [
                'companies' => $insuranceCompanies,
            ],
        ]);
    }

    public function branchStatistics(Request $request)
    {
        $month = $request->string('month')->toString(); // "2026-01"

        // Set date range
        $month = $month ?: now()->format('Y-m');
        $from = $month . '-01';
        $to = date('Y-m-d', strtotime($from . ' +1 month'));

        $user = $request->user();
        $company = $user->company;

        if (!$company) {
            return response()->json([
                'message' => 'No company associated with user',
                'data' => [],
            ]);
        }

        // Get company branch ids
        $branchIds = DB::table('branches')
            ->where('company_id', $company->id)
            ->pluck('id')
            ->toArray();

        if (empty($branchIds)) {
            return response()->json([
                'message' => 'No branches found for company',
                'data' => [],
            ]);
        }

        // Query patients by branch for the month
        $placeholders = implode(',', array_fill(0, count($branchIds), '?'));
        $rows = DB::select(
            "
            SELECT
              pp.branch_id,
              b.address as branch_name,
              COUNT(DISTINCT pp.patient_id) as patients_count

            FROM patient_points pp
            LEFT JOIN branches b ON b.id = pp.branch_id

            WHERE pp.branch_id IN (" . $placeholders . ")
              AND pp.date >= ?
              AND pp.date < ?

            GROUP BY pp.branch_id, b.address
            ORDER BY patients_count DESC, b.address
            ",
            array_merge($branchIds, [$from, $to])
        );

        $data = array_map(function($row) {
            return [
                'id' => $row->branch_id,
                'branch_id' => $row->branch_id,
                'branch_name' => $row->branch_name ?? 'Unknown',
                'patients_count' => (int)$row->patients_count,
            ];
        }, $rows);

        return response()->json([
            'message' => 'Branch statistics retrieved',
            'data' => $data,
        ]);
    }

    public function branchTotals(Request $request)
    {
        $month = $request->string('month')->toString(); // "2026-01"

        $user = $request->user();
        $company = $user->company;

        if (!$company) {
            return response()->json([
                'message' => 'No company associated with user',
                'data' => [],
            ]);
        }

        // Get company branch ids
        $branchIds = DB::table('branches')
            ->where('company_id', $company->id)
            ->pluck('id')
            ->toArray();

        if (empty($branchIds)) {
            return response()->json([
                'message' => 'No branches found for company',
                'data' => [],
            ]);
        }

        // Query totals aggregated by branch for the month
        $placeholders = implode(',', array_fill(0, count($branchIds), '?'));
        $rows = DB::select(
            "
            SELECT
              t.branch_id,
              b.address as branch_name,
              SUM(t.points_total) as total_points,
              SUM(t.kilometers_total) as total_kilometers

            FROM totals t
            LEFT JOIN branches b ON b.id = t.branch_id

            WHERE t.branch_id IN (" . $placeholders . ")
              AND t.month = ?

            GROUP BY t.branch_id, b.address
            ORDER BY (SUM(t.points_total) + SUM(t.kilometers_total)) DESC, b.address
            ",
            array_merge($branchIds, [$month])
        );

        $data = array_map(function($row) {
            return [
                'id' => $row->branch_id,
                'branch_id' => $row->branch_id,
                'branch_name' => $row->branch_name ?? 'Unknown',
                'total_points' => (float)$row->total_points ?? 0,
                'total_kilometers' => (float)$row->total_kilometers ?? 0,
                'total_amount' => ((float)$row->total_points ?? 0) + ((float)$row->total_kilometers ?? 0),
            ];
        }, $rows);

        return response()->json([
            'message' => 'Branch totals retrieved',
            'data' => $data,
        ]);
    }

    public function userTotalsAggregated(Request $request)
    {
        $month = $request->string('month')->toString(); // "2026-01"

        $user = $request->user();
        $company = $user->company;

        if (!$company) {
            return response()->json([
                'message' => 'No company associated with user',
                'data' => [],
            ]);
        }

        // Get all users of the company
        $userIds = DB::table('users')
            ->where('company_id', $company->id)
            ->pluck('id')
            ->toArray();

        if (empty($userIds)) {
            return response()->json([
                'message' => 'No users found for company',
                'data' => [],
            ]);
        }

        // Query totals aggregated by user for the month
        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $rows = DB::select(
            "
            SELECT
              t.user_id,
              CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) as user_name,
              SUM(t.points_total) as total_points,
              SUM(t.kilometers_total) as total_kilometers

            FROM totals t
            JOIN users u ON u.id = t.user_id

            WHERE t.user_id IN (" . $placeholders . ")
              AND t.month = ?

            GROUP BY t.user_id, u.first_name, u.last_name
            ORDER BY (SUM(t.points_total) + SUM(t.kilometers_total)) DESC, u.first_name, u.last_name
            ",
            array_merge($userIds, [$month])
        );

        $data = array_map(function($row) {
            return [
                'id' => $row->user_id,
                'user_id' => $row->user_id,
                'user_name' => trim($row->user_name),
                'total_points' => (float)$row->total_points ?? 0,
                'total_kilometers' => (float)$row->total_kilometers ?? 0,
                'total_amount' => ((float)$row->total_points ?? 0) + ((float)$row->total_kilometers ?? 0),
            ];
        }, $rows);

        return response()->json([
            'message' => 'User totals aggregated retrieved',
            'data' => $data,
        ]);
    }
}
