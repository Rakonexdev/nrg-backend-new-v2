<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use App\Models\Company;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $now = Carbon::now();
        $thirtyDaysFromNow = Carbon::now()->addDays(30);

        $stats = [
            'total_staff' => Staff::count(),
            'active_staff' => Staff::where('status', 'active')->count(),
            'total_companies' => Company::count(),
            'active_companies' => Company::where('is_active', true)->count(),
            'expiring_qid' => Staff::whereBetween('qid_expiry', [$now, $thirtyDaysFromNow])->count(),
            'expired_qid' => Staff::where('qid_expiry', '<', $now)->count(),
            'expiring_passport' => Staff::whereBetween('passport_expiry', [$now, $thirtyDaysFromNow])->count(),
            'expired_passport' => Staff::where('passport_expiry', '<', $now)->count(),
        ];

        // Monthly joining trend (last 6 months)
        $monthlyTrend = Staff::select(
            DB::raw('COUNT(*) as count'),
            DB::raw("DATE_FORMAT(joining_date, '%b %Y') as month")
        )
        ->whereNotNull('joining_date')
        ->where('joining_date', '>=', Carbon::now()->subMonths(6))
        ->groupBy('month')
        ->orderBy('joining_date')
        ->get();

        return response()->json([
            'stats' => $stats,
            'monthlyTrend' => $monthlyTrend
        ]);
    }
}