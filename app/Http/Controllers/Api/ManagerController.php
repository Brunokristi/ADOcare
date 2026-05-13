<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

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
                // Performance note:
                // We pre-aggregate 2-month patient_points counts per (patient,user,branch)
                // and join once, instead of a correlated subquery executed repeatedly.
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
                                WHEN COALESCE(recent_pp.recent_points_count, 0) > 14
                                THEN pp.patient_id ELSE NULL
                            END) AS chronic_patients_count

            FROM patient_points pp
            JOIN patients p
              ON p.id = pp.patient_id

                        LEFT JOIN (
                            SELECT
                                pp2.patient_id,
                                pp2.user_id,
                                pp2.branch_id,
                                COUNT(*) AS recent_points_count
                            FROM patient_points pp2
                            WHERE pp2.branch_id IN (" . $placeholders . ")
                                AND pp2.date >= ?
                                AND pp2.date < ?
                            GROUP BY pp2.patient_id, pp2.user_id, pp2.branch_id
                        ) recent_pp
                            ON recent_pp.patient_id = pp.patient_id
                         AND recent_pp.user_id = pp.user_id
                         AND recent_pp.branch_id = pp.branch_id

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
                        array_merge($branchIds, [$twoMonthsBack, $to], $branchIds, [$from, $to])
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
        $dateFrom = $request->input('date_from');
        $dateTo   = $request->input('date_to');

        // Set date range
        if ($dateFrom && $dateTo) {
            $from = $dateFrom;
            $to   = $dateTo;
        } else {
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

        // Query patients by branch for the selected date range
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

    public function financialStatistics(Request $request)
    {
        $month = $request->string('month')->toString();
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        if ($dateFrom && $dateTo) {
            $from = Carbon::parse($dateFrom)->startOfDay();
            $to = Carbon::parse($dateTo)->startOfDay();
        } else {
            $month = $month ?: now()->format('Y-m');
            $from = Carbon::createFromFormat('Y-m-d', $month . '-01')->startOfDay();
            $to = (clone $from)->addMonth();
        }

        if ($to->lessThanOrEqualTo($from)) {
            return response()->json([
                'message' => 'Invalid date range',
                'data' => null,
            ], 422);
        }

        $monthKeys = [];
        $cursor = (clone $from)->startOfMonth();
        $lastMonth = (clone $to)->subDay()->startOfMonth();
        while ($cursor->lessThanOrEqualTo($lastMonth)) {
            $monthKeys[] = $cursor->format('Y-m');
            $cursor->addMonth();
        }

        $user = $request->user();
        $company = $user->company;

        if (!$company) {
            return response()->json([
                'message' => 'No company associated with user',
                'data' => [
                    'range' => [
                        'date_from' => $from->toDateString(),
                        'date_to' => $to->toDateString(),
                        'months' => $monthKeys,
                    ],
                    'kpis' => [],
                    'monthly' => [],
                    'by_user' => [],
                    'by_branch' => [],
                    'by_insurance' => [],
                    'by_user_insurance' => [],
                    'activity' => [],
                ],
            ]);
        }

        $invoiceBase = DB::table('invoices as i')
            ->join('users as u', 'u.id', '=', 'i.user_id')
            ->where('u.company_id', $company->id)
            ->whereIn('i.period', $monthKeys);

        $invoiceAgg = (clone $invoiceBase)
            ->selectRaw("\n                COUNT(*) AS invoices_all_count,\n                SUM(CASE WHEN i.type IN ('procedures', 'transport') THEN 1 ELSE 0 END) AS invoices_count,\n                SUM(CASE WHEN i.type = 'credit_note' THEN 1 ELSE 0 END) AS credit_notes_count,\n                SUM(CASE WHEN i.type = 'debit_note' THEN 1 ELSE 0 END) AS debit_notes_count,\n                SUM(CASE WHEN i.type IN ('procedures', 'transport') THEN i.total ELSE 0 END) AS invoice_revenue,\n                SUM(CASE WHEN i.type = 'credit_note' THEN i.total ELSE 0 END) AS credit_notes_total,\n                SUM(CASE WHEN i.type = 'debit_note' THEN i.total ELSE 0 END) AS debit_notes_total,\n                SUM(CASE WHEN i.type = 'procedures' THEN i.total ELSE 0 END) AS procedures_revenue,\n                SUM(CASE WHEN i.type = 'transport' THEN i.total ELSE 0 END) AS transport_revenue\n            ")
            ->first();

        $invoiceRevenue = (float)($invoiceAgg->invoice_revenue ?? 0);
        $creditNotesTotal = (float)($invoiceAgg->credit_notes_total ?? 0);
        $debitNotesTotal = (float)($invoiceAgg->debit_notes_total ?? 0);
        $notesNet = $creditNotesTotal + $debitNotesTotal;
        $notesAbsolute = abs($creditNotesTotal) + abs($debitNotesTotal);
        $netRevenue = $invoiceRevenue + $notesNet;
        $errorPercentage = $invoiceRevenue > 0 ? (abs($creditNotesTotal) / $invoiceRevenue) * 100 : 0;

        $monthlyRows = (clone $invoiceBase)
            ->selectRaw("\n                i.period AS month,\n                SUM(CASE WHEN i.type IN ('procedures', 'transport') THEN i.total ELSE 0 END) AS invoice_revenue,\n                SUM(CASE WHEN i.type = 'credit_note' THEN i.total ELSE 0 END) AS credit_notes_total,\n                SUM(CASE WHEN i.type = 'debit_note' THEN i.total ELSE 0 END) AS debit_notes_total,\n                SUM(CASE WHEN i.type = 'procedures' THEN i.total ELSE 0 END) AS procedures_revenue,\n                SUM(CASE WHEN i.type = 'transport' THEN i.total ELSE 0 END) AS transport_revenue\n            ")
            ->groupBy('i.period')
            ->orderBy('i.period')
            ->get();

        $monthlyMap = [];
        foreach ($monthKeys as $m) {
            $monthlyMap[$m] = [
                'month' => $m,
                'invoice_revenue' => 0.0,
                'credit_notes_total' => 0.0,
                'debit_notes_total' => 0.0,
                'notes_net' => 0.0,
                'net_revenue' => 0.0,
                'procedures_revenue' => 0.0,
                'transport_revenue' => 0.0,
            ];
        }

        foreach ($monthlyRows as $row) {
            $monthKey = (string)$row->month;
            if (!isset($monthlyMap[$monthKey])) {
                continue;
            }

            $monthInvoiceRevenue = (float)($row->invoice_revenue ?? 0);
            $monthCredit = (float)($row->credit_notes_total ?? 0);
            $monthDebit = (float)($row->debit_notes_total ?? 0);
            $monthNotesNet = $monthCredit + $monthDebit;

            $monthlyMap[$monthKey] = [
                'month' => $monthKey,
                'invoice_revenue' => $monthInvoiceRevenue,
                'credit_notes_total' => $monthCredit,
                'debit_notes_total' => $monthDebit,
                'notes_net' => $monthNotesNet,
                'net_revenue' => $monthInvoiceRevenue + $monthNotesNet,
                'procedures_revenue' => (float)($row->procedures_revenue ?? 0),
                'transport_revenue' => (float)($row->transport_revenue ?? 0),
            ];
        }

        $byInsuranceRows = (clone $invoiceBase)
            ->leftJoin('insurance_companies as ic', 'ic.id', '=', 'i.insurance_company_id')
            ->selectRaw("\n                i.insurance_company_id,\n                COALESCE(ic.name, 'Neznáma poisťovňa') AS insurance_company_name,\n                SUM(CASE WHEN i.type IN ('procedures', 'transport') THEN i.total ELSE 0 END) AS invoice_revenue,\n                SUM(CASE WHEN i.type = 'credit_note' THEN i.total ELSE 0 END) AS credit_notes_total,\n                SUM(CASE WHEN i.type = 'debit_note' THEN i.total ELSE 0 END) AS debit_notes_total\n            ")
            ->groupBy('i.insurance_company_id', 'ic.name')
            ->orderByDesc(DB::raw("SUM(CASE WHEN i.type IN ('procedures', 'transport') THEN i.total ELSE 0 END) + SUM(CASE WHEN i.type = 'credit_note' THEN i.total ELSE 0 END) + SUM(CASE WHEN i.type = 'debit_note' THEN i.total ELSE 0 END)"))
            ->get();

        $insuranceDocCounts = DB::table('documents as d')
            ->join('users as u', 'u.id', '=', 'd.user_id')
            ->where('u.company_id', $company->id)
            ->whereIn('d.period', $monthKeys)
            ->whereIn('d.type', ['points_batch', 'kilometers_batch'])
            ->selectRaw('d.insurance_company_id, COUNT(*) AS documents_count')
            ->groupBy('d.insurance_company_id')
            ->get()
            ->keyBy('insurance_company_id');

        $byInsurance = $byInsuranceRows->map(function ($row) use ($insuranceDocCounts) {
            $invoiceRevenueByInsurance = (float)($row->invoice_revenue ?? 0);
            $creditByInsurance = (float)($row->credit_notes_total ?? 0);
            $debitByInsurance = (float)($row->debit_notes_total ?? 0);
            $notesNetByInsurance = $creditByInsurance + $debitByInsurance;
            $docCount = (int)($insuranceDocCounts[$row->insurance_company_id]->documents_count ?? 0);

            return [
                'insurance_company_id' => $row->insurance_company_id,
                'insurance_company_name' => $row->insurance_company_name,
                'invoice_revenue' => $invoiceRevenueByInsurance,
                'credit_notes_total' => $creditByInsurance,
                'debit_notes_total' => $debitByInsurance,
                'notes_net' => $notesNetByInsurance,
                'net_revenue' => $invoiceRevenueByInsurance + $notesNetByInsurance,
                'documents_count' => $docCount,
            ];
        })->values();

        $batchDocuments = DB::table('documents as d')
            ->join('users as u', 'u.id', '=', 'd.user_id')
            ->leftJoin('branches as b', 'b.id', '=', 'd.branch_id')
            ->leftJoin('insurance_companies as ic', 'ic.id', '=', 'd.insurance_company_id')
            ->where('u.company_id', $company->id)
            ->whereIn('d.period', $monthKeys)
            ->whereIn('d.type', ['points_batch', 'kilometers_batch'])
            ->select(
                'd.type',
                'd.path',
                'd.user_id',
                'd.branch_id',
                'd.insurance_company_id',
                'u.title',
                'u.first_name',
                'u.last_name',
                'b.address as branch_name',
                'ic.name as insurance_company_name'
            )
            ->get();

        $byUserAggregate = [];
        $byBranchAggregate = [];
        $byUserInsuranceAggregate = [];
        $insuranceCompaniesSet = [];

        $activityDocCounts = [
            'points_batch' => 0,
            'kilometers_batch' => 0,
        ];

        $activityDocRevenue = [
            'points_batch' => 0.0,
            'kilometers_batch' => 0.0,
        ];

        foreach ($batchDocuments as $document) {
            $type = (string)($document->type ?? '');
            if (!array_key_exists($type, $activityDocCounts)) {
                continue;
            }

            $amount = $this->readDocumentAmount((string)($document->path ?? ''));
            $userKey = (int)($document->user_id ?? 0);
            $branchKey = $document->branch_id === null ? 'null' : (string)$document->branch_id;
            $insuranceId = $document->insurance_company_id !== null ? (int)$document->insurance_company_id : 0;
            $insuranceName = $document->insurance_company_name ?: 'Neznáma poisťovňa';

            if (!isset($byUserAggregate[$userKey])) {
                $userName = trim(implode(' ', array_filter([
                    (string)($document->title ?? ''),
                    (string)($document->first_name ?? ''),
                    (string)($document->last_name ?? ''),
                ])));

                $byUserAggregate[$userKey] = [
                    'user_id' => $userKey,
                    'user_name' => $userName !== '' ? $userName : 'Neznámy používateľ',
                    'points_revenue' => 0.0,
                    'kilometers_revenue' => 0.0,
                    'revenue_total' => 0.0,
                ];
            }

            if (!isset($insuranceCompaniesSet[$insuranceId])) {
                $insuranceCompaniesSet[$insuranceId] = [
                    'insurance_company_id' => $insuranceId,
                    'insurance_company_name' => $insuranceName,
                ];
            }

            if (!isset($byUserInsuranceAggregate[$userKey])) {
                $byUserInsuranceAggregate[$userKey] = [
                    'user_id' => $userKey,
                    'user_name' => $byUserAggregate[$userKey]['user_name'],
                    'revenue_total' => 0.0,
                    'insurances' => [],
                ];
            }

            if (!isset($byUserInsuranceAggregate[$userKey]['insurances'][$insuranceId])) {
                $byUserInsuranceAggregate[$userKey]['insurances'][$insuranceId] = [
                    'insurance_company_id' => $insuranceId,
                    'insurance_company_name' => $insuranceName,
                    'points_revenue' => 0.0,
                    'kilometers_revenue' => 0.0,
                    'revenue_total' => 0.0,
                ];
            }

            if (!isset($byBranchAggregate[$branchKey])) {
                $byBranchAggregate[$branchKey] = [
                    'branch_id' => $document->branch_id,
                    'branch_name' => $document->branch_name ?: 'Neznáma pobočka',
                    'points_revenue' => 0.0,
                    'kilometers_revenue' => 0.0,
                    'revenue_total' => 0.0,
                ];
            }

            if ($type === 'points_batch') {
                $byUserAggregate[$userKey]['points_revenue'] += $amount;
                $byBranchAggregate[$branchKey]['points_revenue'] += $amount;
                $byUserInsuranceAggregate[$userKey]['insurances'][$insuranceId]['points_revenue'] += $amount;
            } else {
                $byUserAggregate[$userKey]['kilometers_revenue'] += $amount;
                $byBranchAggregate[$branchKey]['kilometers_revenue'] += $amount;
                $byUserInsuranceAggregate[$userKey]['insurances'][$insuranceId]['kilometers_revenue'] += $amount;
            }

            $byUserAggregate[$userKey]['revenue_total'] += $amount;
            $byBranchAggregate[$branchKey]['revenue_total'] += $amount;
            $byUserInsuranceAggregate[$userKey]['insurances'][$insuranceId]['revenue_total'] += $amount;
            $byUserInsuranceAggregate[$userKey]['revenue_total'] += $amount;
            $activityDocCounts[$type] += 1;
            $activityDocRevenue[$type] += $amount;
        }

        $byUserRows = collect(array_values($byUserAggregate))
            ->sortByDesc('revenue_total')
            ->values()
            ->map(function ($row) {
                return [
                    'user_id' => (int)$row['user_id'],
                    'user_name' => (string)$row['user_name'],
                    'points_revenue' => round((float)$row['points_revenue'], 2),
                    'kilometers_revenue' => round((float)$row['kilometers_revenue'], 2),
                    'revenue_total' => round((float)$row['revenue_total'], 2),
                ];
            })
            ->values();

        $byBranchRows = collect(array_values($byBranchAggregate))
            ->sortByDesc('revenue_total')
            ->values()
            ->map(function ($row) {
                return [
                    'branch_id' => $row['branch_id'],
                    'branch_name' => (string)$row['branch_name'],
                    'points_revenue' => round((float)$row['points_revenue'], 2),
                    'kilometers_revenue' => round((float)$row['kilometers_revenue'], 2),
                    'revenue_total' => round((float)$row['revenue_total'], 2),
                ];
            })
            ->values();

        $insuranceCompanies = collect(array_values($insuranceCompaniesSet))
            ->sortBy('insurance_company_name')
            ->values();

        $byUserInsuranceRows = collect(array_values($byUserInsuranceAggregate))
            ->sortByDesc('revenue_total')
            ->values()
            ->map(function ($row) {
                $insuranceRows = collect(array_values($row['insurances']))
                    ->sortBy('insurance_company_name')
                    ->values()
                    ->map(function ($insuranceRow) {
                        return [
                            'insurance_company_id' => (int)$insuranceRow['insurance_company_id'],
                            'insurance_company_name' => (string)$insuranceRow['insurance_company_name'],
                            'points_revenue' => round((float)$insuranceRow['points_revenue'], 2),
                            'kilometers_revenue' => round((float)$insuranceRow['kilometers_revenue'], 2),
                            'revenue_total' => round((float)$insuranceRow['revenue_total'], 2),
                        ];
                    })
                    ->values();

                return [
                    'user_id' => (int)$row['user_id'],
                    'user_name' => (string)$row['user_name'],
                    'revenue_total' => round((float)$row['revenue_total'], 2),
                    'insurances' => $insuranceRows,
                ];
            })
            ->values();

        $activity = [
            [
                'activity_type' => 'points_batch',
                'activity_name' => 'Výkony',
                'documents_count' => $activityDocCounts['points_batch'],
                'revenue' => round($activityDocRevenue['points_batch'], 2),
            ],
            [
                'activity_type' => 'kilometers_batch',
                'activity_name' => 'Doprava',
                'documents_count' => $activityDocCounts['kilometers_batch'],
                'revenue' => round($activityDocRevenue['kilometers_batch'], 2),
            ],
        ];

        $activityPointsRevenue = round($activityDocRevenue['points_batch'], 2);
        $activityKilometersRevenue = round($activityDocRevenue['kilometers_batch'], 2);

        return response()->json([
            'message' => 'Financial statistics retrieved',
            'data' => [
                'range' => [
                    'date_from' => $from->toDateString(),
                    'date_to' => $to->toDateString(),
                    'months' => $monthKeys,
                ],
                'kpis' => [
                    'invoices_count' => (int)($invoiceAgg->invoices_count ?? 0),
                    'credit_notes_count' => (int)($invoiceAgg->credit_notes_count ?? 0),
                    'debit_notes_count' => (int)($invoiceAgg->debit_notes_count ?? 0),
                    'invoice_revenue' => $invoiceRevenue,
                    'credit_notes_total' => $creditNotesTotal,
                    'debit_notes_total' => $debitNotesTotal,
                    'notes_net' => $notesNet,
                    'notes_absolute' => $notesAbsolute,
                    'error_percentage' => $errorPercentage,
                    'net_revenue' => $netRevenue,
                    'procedures_revenue' => $activityPointsRevenue,
                    'transport_revenue' => $activityKilometersRevenue,
                    'activity_total_revenue' => $activityPointsRevenue + $activityKilometersRevenue,
                ],
                'monthly' => array_values($monthlyMap),
                'by_user' => $byUserRows,
                'by_branch' => $byBranchRows,
                'by_insurance' => $byInsurance,
                'by_user_insurance' => [
                    'companies' => $insuranceCompanies,
                    'rows' => $byUserInsuranceRows,
                ],
                'activity' => $activity,
            ],
        ]);
    }

    private function readDocumentAmount(?string $path): float
    {
        if (!$path || !Storage::disk('local')->exists($path)) {
            return 0.0;
        }

        $decoded = json_decode((string)Storage::disk('local')->get($path), true);
        if (!is_array($decoded)) {
            return 0.0;
        }

        return (float)data_get($decoded, 'meta.amount', 0);
    }
}
