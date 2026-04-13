<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\StaffDocument;
use App\Models\Staff;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class CheckDocumentExpiry extends Command
{
    protected $signature = 'documents:check-expiry';
    protected $description = 'Check for staff documents expiring within 30 days';

    public function handle()
    {
        $thirtyDaysFromNow = Carbon::now()->addDays(30);

        // Checking QID expiry
        $staffQid = Staff::whereNotNull('qid_expiry')->whereBetween('qid_expiry', [now(), $thirtyDaysFromNow])->get();
        foreach ($staffQid as $staff) {
            Log::warning("QID for Staff {$staff->name} expires on {$staff->qid_expiry}");
        }

        // Checking Passport expiry
        $staffPassport = Staff::whereNotNull('passport_expiry')->whereBetween('passport_expiry', [now(), $thirtyDaysFromNow])->get();
        foreach ($staffPassport as $staff) {
            Log::warning("Passport for Staff {$staff->name} expires on {$staff->passport_expiry}");
        }

        $this->info('Expiry check completed.');
    }
}
