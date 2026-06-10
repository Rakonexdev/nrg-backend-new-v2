<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $staffStats = \App\Models\Staff::selectRaw('
            COUNT(*) as total,
            SUM(CASE WHEN status = "active" THEN 1 ELSE 0 END) as active,
            SUM(CASE WHEN status = "on_leave" THEN 1 ELSE 0 END) as on_leave
        ')->first();

        $contractStats = \App\Models\Contract::selectRaw('
            COUNT(*) as total,
            SUM(COALESCE(paid_amount, 0)) as total_collected,
            SUM(COALESCE(pending_amount, 0)) as total_pending,
            SUM(
                COALESCE(qid_renewal_fee, 0) + 
                COALESCE(passport_renewal_fee, 0) + 
                COALESCE(profession_change_fee, 0) + 
                COALESCE(sponsorship_change_fee, 0) + 
                COALESCE(health_card_fee, 0) + 
                COALESCE(others_fee, 0)
            ) as total_fixed_expenses
        ')->first();

        $totalDailyExpenses = \App\Models\Expense::sum('amount');
        $totalProfit = (float) $contractStats->total_collected - ((float) $contractStats->total_fixed_expenses + (float) $totalDailyExpenses);

        $recentCollections = \App\Models\ContractPayment::with(['contract.staff.company', 'contract.staff.branch', 'creator'])
            ->latest('payment_date')
            ->latest('id')
            ->take(10)
            ->get()
            ->map(function ($payment) {
                return [
                    'id' => $payment->id,
                    'time_ago' => $payment->created_at ? $payment->created_at->diffForHumans() : 'N/A',
                    'date' => $payment->payment_date ? $payment->payment_date->format('d M Y') : 'N/A',
                    'collector' => $payment->creator?->name ?? 'System',
                    'company' => $payment->contract?->staff?->company?->name ?? 'N/A',
                    'company_id' => $payment->contract?->staff?->company_id,
                    'staff' => $payment->contract?->staff?->name ?? 'N/A',
                    'staff_id' => $payment->contract?->staff_id,
                    'amount' => $payment->amount,
                    'method' => $payment->payment_method,
                    'branch_name' => $payment->contract?->staff?->branch?->name,
                    'branch_number' => $payment->contract?->staff?->branch?->branch_number,
                ];
            });

        $now = Carbon::now();
        $thisMonthEnd = $now->copy()->endOfMonth();

        // Helper to get IDs of staff with in-progress renewals (not yet completed)
        $inProgressQuery = \App\Models\Expense::whereNotNull('validation_date')
            ->whereNotNull('staff_id')
            ->where(function($q) {
                $q->whereNull('renewal_status')
                  ->orWhere('renewal_status', '!=', 'completed');
            });

        $qidInProgressIds = (clone $inProgressQuery)
            ->whereHas('subcategory', function ($q) {
                $q->where('name', 'like', '%QID%');
            })
            ->pluck('staff_id')
            ->unique()
            ->toArray();

        $passportInProgressIds = (clone $inProgressQuery)
            ->whereHas('subcategory', function ($q) {
                $q->where('name', 'like', '%PASSPORT%')
                   ->orWhere('name', 'like', '%PP%');
            })
            ->pluck('staff_id')
            ->unique()
            ->toArray();

        $expiringQidCount = \App\Models\Staff::where('qid_expiry', '<=', $thisMonthEnd)
            ->whereNotIn('id', $qidInProgressIds)
            ->count();

        $expiringPassportCount = \App\Models\Staff::where('passport_expiry', '<=', $thisMonthEnd)
            ->whereNotIn('id', $passportInProgressIds)
            ->count();

        // Upcoming expirations (next 30 days)
        // We include them if either doc is expiring, but we will filter in the map if needed
        $upcomingExpirations = \App\Models\Staff::where(function ($q) use ($now) {
            $thirtyDays = $now->copy()->addDays(30);
            $q->where('qid_expiry', '<=', $thirtyDays)
                ->orWhere('passport_expiry', '<=', $thirtyDays);
        })
            ->where(function ($q) {
                $q->whereNotNull('qid_expiry')
                    ->orWhereNotNull('passport_expiry');
            })
            ->select('id', 'name', 'qid_expiry', 'passport_expiry')
            ->orderByRaw('LEAST(IFNULL(qid_expiry, "9999-12-31"), IFNULL(passport_expiry, "9999-12-31")) ASC')
            ->take(10) // Take more then filter
            ->get()
            ->map(function ($staff) use ($now, $qidInProgressIds, $passportInProgressIds) {
                $qidDays = $staff->qid_expiry ? $now->diffInDays($staff->qid_expiry, false) : 999;
                $passportDays = $staff->passport_expiry ? $now->diffInDays($staff->passport_expiry, false) : 999;

                $qidValid = $staff->qid_expiry && !in_array($staff->id, $qidInProgressIds);
                $passportValid = $staff->passport_expiry && !in_array($staff->id, $passportInProgressIds);

                // Determine which one to show (priority to the one NOT in progress and more urgent)
                if ($qidValid && $passportValid) {
                    if ($qidDays < $passportDays) {
                        $type = 'QID';
                        $days = $qidDays;
                    } else {
                        $type = 'Passport';
                        $days = $passportDays;
                    }
                } elseif ($qidValid) {
                    $type = 'QID';
                    $days = $qidDays;
                } elseif ($passportValid) {
                    $type = 'Passport';
                    $days = $passportDays;
                } else {
                    return null; // Both in progress
                }

                return [
                    'id' => $staff->id,
                    'name' => $staff->name,
                    'type' => $type,
                    'days' => (int) $days,
                    'status' => $days < 7 ? 'critical' : ($days < 15 ? 'warning' : 'info')
                ];
            })
            ->filter()
            ->take(5)
            ->values();

        $renewingContractsCount = \App\Models\Staff::where(function ($q) use ($now, $thisMonthEnd) {
            $q->whereHas('latestContract', function ($cq) use ($thisMonthEnd) {
                $cq->where('end_date', '<=', $thisMonthEnd);
            })
            ->orWhere(function ($sq) use ($now) {
                $sq->whereDoesntHave('contracts')
                   ->whereMonth('joining_date', '<=', $now->month)
                   ->whereYear('joining_date', '<', $now->year);
            });
        })
            ->count();

        $renewingContracts = \App\Models\Staff::with(['company', 'latestContract'])
            ->where(function ($q) use ($now, $thisMonthEnd) {
                $q->whereHas('latestContract', function ($cq) use ($thisMonthEnd) {
                    $cq->where('end_date', '<=', $thisMonthEnd);
                })
                ->orWhere(function ($sq) use ($now) {
                    $sq->whereDoesntHave('contracts')
                       ->where(function($q2) use ($now) {
                           $q2->whereMonth('joining_date', '<=', $now->month)
                              ->whereYear('joining_date', '<', $now->year);
                       });
                });
            })
            ->latest('id')
            ->take(10)
            ->get()

            ->map(function ($staff) use ($now) {
                $contract = $staff->latestContract;
                $endDate = $contract ? $contract->end_date : null;
                
                if (!$endDate && $staff->joining_date) {
                    $joiningDate = Carbon::parse($staff->joining_date);
                    $endDate = $joiningDate->copy()->year($now->year);
                }

                $days = $endDate ? $now->diffInDays($endDate, false) : 0;
                
                return [
                    'id' => $staff->id,
                    'staff_name' => $staff->name,
                    'staff' => [
                        'company' => [
                            'name' => $staff->company?->name ?? 'N/A'
                        ]
                    ],
                    'end_date' => $endDate ? $endDate->format('Y-m-d') : null,
                    'days' => (int) $days,
                    'status' => $days < 0 ? 'expired' : ($days < 7 ? 'critical' : ($days < 15 ? 'warning' : 'info'))
                ];
            })
            ->sortBy('days')
            ->take(5)
            ->values();
        // Pending Document Updates (Expense recorded but Staff record not updated yet)
        $pendingUpdates = \App\Models\Expense::with(['staff.branch', 'subcategory'])
            ->whereNotNull('validation_date')
            ->whereNotNull('staff_id')
            ->latest()
            ->get()
            ->filter(function ($expense) {
                if (!$expense->staff || !$expense->subcategory)
                    return false;
                $subName = strtoupper($expense->subcategory->name);

                if (str_contains($subName, 'QID')) {
                    return !$expense->staff->qid_expiry || $expense->staff->qid_expiry < $expense->validation_date;
                }
                if (str_contains($subName, 'PASSPORT') || str_contains($subName, 'PP')) {
                    return !$expense->staff->passport_expiry || $expense->staff->passport_expiry < $expense->validation_date;
                }
                return false;
            })
            ->take(10)
            ->map(function ($expense) {
                $subName = strtoupper($expense->subcategory?->name ?? '');
                $type = str_contains($subName, 'QID') ? 'QID' : 'Passport';

                return [
                    'id' => $expense->id,
                    'staff_id' => $expense->staff_id,
                    'staff_name' => $expense->staff->name,
                    'type' => $type,
                    'expense_date' => $expense->expense_date ? $expense->expense_date->format('d M Y') : 'N/A',
                    'new_expiry' => $expense->validation_date ? $expense->validation_date->format('d M Y') : 'N/A',
                    'current_expiry' => $type === 'QID'
                        ? ($expense->staff?->qid_expiry ? $expense->staff->qid_expiry->format('d M Y') : 'Expired/Missing')
                        : ($expense->staff?->passport_expiry ? $expense->staff->passport_expiry->format('d M Y') : 'Expired/Missing'),
                    'renewal_status' => $expense->renewal_status ?? 'processing',
                    'renewal_notes' => $expense->renewal_notes,
                    'staff' => [
                        'name' => $expense->staff->name,
                        'qid_number' => $expense->staff->qid_number,
                        'phone' => $expense->staff->phone,
                        'branch_name' => $expense->staff->branch?->name,
                        'branch_number' => $expense->staff->branch?->branch_number,
                    ]
                ];
            })
            ->values();



        $adjustmentPaidTotal = (float) \App\Models\ContractAdjustment::sum('paid_amount');

        $stats = [
            'total_staff' => (int) $staffStats->total,
            'active_staff' => (int) $staffStats->active,
            'on_leave_staff' => (int) $staffStats->on_leave,
            'total_active_contracts' => (int) $contractStats->total,
            'total_collected' => round((float) $contractStats->total_collected + $adjustmentPaidTotal, 2),
            'total_pending' => round((float) $contractStats->total_pending + (float) \App\Models\ContractAdjustment::sum('pending_amount'), 2),
            'total_profit' => round((float) $totalProfit, 2),
            'expiring_qid' => $expiringQidCount,
            'expiring_passport' => $expiringPassportCount,
            'renewing_contracts' => $renewingContractsCount,
            'pending_docs_count' => count($pendingUpdates),
        ];

        return response()->json([
            'stats' => $stats,
            'recentCollections' => $recentCollections,
            'upcomingExpirations' => $upcomingExpirations,
            'renewingContracts' => $renewingContracts,
            'pendingUpdates' => $pendingUpdates
        ]);
    }
}