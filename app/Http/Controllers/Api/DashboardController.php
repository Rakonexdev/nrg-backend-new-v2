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

        // Helper to get IDs of staff with in-progress renewals
        $inProgressStaffIds = \App\Models\Expense::whereNotNull('validation_date')
            ->whereNotNull('staff_id')
            ->get()
            ->filter(function ($expense) {
                if (!$expense->staff || !$expense->subcategory) return false;
                $subName = strtoupper($expense->subcategory->name);
                if (str_contains($subName, 'QID')) {
                    return !$expense->staff->qid_expiry || $expense->staff->qid_expiry < $expense->validation_date;
                }
                if (str_contains($subName, 'PASSPORT') || str_contains($subName, 'PP')) {
                    return !$expense->staff->passport_expiry || $expense->staff->passport_expiry < $expense->validation_date;
                }
                return false;
            })
            ->pluck('staff_id')
            ->unique();

        $expiringQidCount = \App\Models\Staff::where('qid_expiry', '<=', $thisMonthEnd)
            ->whereNotIn('id', $inProgressStaffIds)
            ->count();
            
        $expiringPassportCount = \App\Models\Staff::where('passport_expiry', '<=', $thisMonthEnd)
            ->whereNotIn('id', $inProgressStaffIds)
            ->count();

        // Upcoming expirations (next 30 days), excluding those already in progress
        $upcomingExpirations = \App\Models\Staff::where(function ($q) use ($now) {
            $thirtyDays = $now->copy()->addDays(30);
            $q->where('qid_expiry', '<=', $thirtyDays)
                ->orWhere('passport_expiry', '<=', $thirtyDays);
        })
            ->where(function ($q) {
                $q->whereNotNull('qid_expiry')
                    ->orWhereNotNull('passport_expiry');
            })
            ->whereNotIn('id', $inProgressStaffIds)
            ->select('id', 'name', 'qid_expiry', 'passport_expiry')
            ->orderByRaw('LEAST(IFNULL(qid_expiry, "9999-12-31"), IFNULL(passport_expiry, "9999-12-31")) ASC')
            ->take(5)
            ->get()
            ->map(function ($staff) use ($now) {
                $qidDays = $staff->qid_expiry ? $now->diffInDays($staff->qid_expiry, false) : 999;
                $passportDays = $staff->passport_expiry ? $now->diffInDays($staff->passport_expiry, false) : 999;

                if ($qidDays >= 0 && $qidDays < $passportDays) {
                    $type = 'QID';
                    $days = $qidDays;
                } else {
                    $type = 'Passport';
                    $days = $passportDays;
                }

                return [
                    'id' => $staff->id,
                    'name' => $staff->name,
                    'type' => $type,
                    'days' => (int) $days,
                    'status' => $days < 7 ? 'critical' : ($days < 15 ? 'warning' : 'info')
                ];
            });

        $renewingContractsCount = \App\Models\Contract::where(function($q) use ($now, $thisMonthEnd) {
                $q->where('end_date', '<=', $thisMonthEnd)
                  ->orWhere(function($sq) use ($now) {
                      $sq->whereNull('end_date')
                         ->whereHas('staff', function($ssq) use ($now) {
                             $ssq->whereMonth('joining_date', '<=', $now->month);
                         });
                  });
            })->count();

        $renewingContracts = \App\Models\Contract::with('staff.company')
            ->where(function($q) use ($now, $thisMonthEnd) {
                $q->where('end_date', '<=', $thisMonthEnd)
                  ->orWhere(function($sq) use ($now) {
                      $sq->whereNull('end_date')
                         ->whereHas('staff', function($ssq) use ($now) {
                             $ssq->whereMonth('joining_date', '<=', $now->month);
                         });
                  });
            })
            ->limit(5)
            ->get()
            ->map(function ($contract) use ($now) {
                // Calculate effective end date: explicit end_date or anniversary of joining_date
                $endDate = $contract->end_date;
                if (!$endDate && $contract->staff?->joining_date) {
                    $joiningDate = Carbon::parse($contract->staff->joining_date);
                    // Actually, if joining_date month is May, then renewal is May of ANY year.
                    // But we want the specific end date of the CURRENT cycle.
                    // For preview, we just show the anniversary in the current year.
                    $endDate = $joiningDate->copy()->year($now->year)->subDay();
                    // Wait, if they joined May 7 2025, end is May 6 2026.
                    // If it's May 2026 now, the anniversary is May 6 2026.
                    if ($endDate->lt($now->copy()->startOfMonth())) {
                        $endDate->addYear();
                    }
                }

                $days = $endDate ? $now->diffInDays($endDate, false) : 0;
                return [
                    'id' => $contract->id,
                    'staff_name' => $contract->staff->name ?? 'N/A',
                    'end_date' => $endDate ? $endDate->format('Y-m-d') : null,
                    'days' => (int) $days,
                    'is_auto_renew' => (bool) $contract->is_auto_renew,
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



        $stats = [
            'total_staff' => (int) $staffStats->total,
            'active_staff' => (int) $staffStats->active,
            'on_leave_staff' => (int) $staffStats->on_leave,
            'total_active_contracts' => (int) $contractStats->total,
            'total_collected' => round((float) $contractStats->total_collected, 2),
            'total_pending' => round((float) $contractStats->total_pending, 2),
            'total_profit' => round((float) $totalProfit, 2),
            'expiring_qid' => $expiringQidCount,
            'expired_passport' => $expiringPassportCount,
            'renewing_contracts' => $renewingContractsCount,
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