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
                    'staff' => $payment->contract?->staff?->name ?? 'N/A',
                    'amount' => $payment->amount,
                    'method' => $payment->payment_method
                ];
            });

        $stats = [
            'total_staff' => (int) $staffStats->total,
            'active_staff' => (int) $staffStats->active,
            'on_leave_staff' => (int) $staffStats->on_leave,
            'total_active_contracts' => (int) $contractStats->total,
            'total_collected' => round((float) $contractStats->total_collected, 2),
        ];

        return response()->json([
            'stats' => $stats,
            'recentCollections' => $recentCollections
        ]);
    }
}