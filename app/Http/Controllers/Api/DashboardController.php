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
            'upcomingExpirations' => $upcomingExpirations
        ]);
    }
}