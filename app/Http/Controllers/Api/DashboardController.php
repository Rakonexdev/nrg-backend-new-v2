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
            SUM(paid_amount) as total_collected
        ')->first();

        $recentCollections = \App\Models\ContractPayment::with(['contract.staff.company', 'creator'])
            ->latest('payment_date')
            ->latest('id')
            ->take(10)
            ->get()
            ->map(function ($payment) {
                return [
                    'id' => $payment->id,
                    'time_ago' => $payment->created_at->diffForHumans(),
                    'date' => $payment->payment_date->format('d M Y'),
                    'collector' => $payment->creator?->name ?? 'System',
                    'company' => $payment->contract?->staff?->company?->name ?? 'N/A',
                    'company_id' => $payment->contract?->staff?->company_id,
                    'staff' => $payment->contract?->staff?->name ?? 'N/A',
                    'staff_id' => $payment->contract?->staff_id,
                    'amount' => $payment->amount,
                    'method' => $payment->payment_method
                ];
            });

        $now = Carbon::now();
        $thisMonthStart = $now->copy()->startOfMonth();
        $thisMonthEnd = $now->copy()->endOfMonth();

        $expiringQidCount = \App\Models\Staff::whereBetween('qid_expiry', [$thisMonthStart, $thisMonthEnd])->count();
        $expiringPassportCount = \App\Models\Staff::whereBetween('passport_expiry', [$thisMonthStart, $thisMonthEnd])->count();

        // Upcoming expirations (next 30 days)
        $upcomingExpirations = \App\Models\Staff::where(function($q) use ($now) {
                $thirtyDays = $now->copy()->addDays(30);
                $q->whereBetween('qid_expiry', [$now, $thirtyDays])
                  ->orWhereBetween('passport_expiry', [$now, $thirtyDays]);
            })
            ->select('id', 'name', 'qid_expiry', 'passport_expiry')
            ->orderByRaw('LEAST(IFNULL(qid_expiry, "9999-12-31"), IFNULL(passport_expiry, "9999-12-31")) ASC')
            ->take(5)
            ->get()
            ->map(function($staff) use ($now) {
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

        // Pending Document Updates (Expense recorded but Staff record not updated yet)
        $pendingUpdates = \App\Models\Expense::with(['staff', 'subcategory'])
            ->whereNotNull('validation_date')
            ->whereNotNull('staff_id')
            ->latest()
            ->get()
            ->filter(function($expense) {
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
            ->take(10)
            ->map(function($expense) {
                $subName = strtoupper($expense->subcategory?->name ?? '');
                $type = str_contains($subName, 'QID') ? 'QID' : 'Passport';
                
                return [
                    'id' => $expense->id,
                    'staff_id' => $expense->staff_id,
                    'staff_name' => $expense->staff->name,
                    'type' => $type,
                    'expense_date' => $expense->expense_date->format('d M Y'),
                    'new_expiry' => $expense->validation_date->format('d M Y'),
                    'current_expiry' => $type === 'QID' 
                        ? ($expense->staff->qid_expiry ? $expense->staff->qid_expiry->format('d M Y') : 'Expired/Missing')
                        : ($expense->staff->passport_expiry ? $expense->staff->passport_expiry->format('d M Y') : 'Expired/Missing'),
                    'renewal_status' => $expense->renewal_status ?? 'processing',
                    'renewal_notes' => $expense->renewal_notes
                ];
            })
            ->values();

        $stats = [
            'total_staff' => (int) $staffStats->total,
            'active_staff' => (int) $staffStats->active,
            'on_leave_staff' => (int) $staffStats->on_leave,
            'total_active_contracts' => (int) $contractStats->total,
            'total_collected' => round((float) $contractStats->total_collected, 2),
            'expiring_qid' => $expiringQidCount,
            'expired_passport' => $expiringPassportCount,
        ];

        return response()->json([
            'stats' => $stats,
            'recentCollections' => $recentCollections,
            'upcomingExpirations' => $upcomingExpirations,
            'pendingUpdates' => $pendingUpdates
        ]);
    }
}