<?php
namespace App\Listeners;

use App\Events\ContractCreated;
use App\Models\Invoice;
use Carbon\Carbon;

class GenerateInitialInvoice
{
    public function handle(ContractCreated $event)
    {
        $contract = $event->contract;
        
        // As per instructions: generate pending invoice based on agreed amount.
        // E.g., for standard billing, an invoice is raised mapping the monthly_salary 
        // to a 30-day term, or a full term if structured differently. Let's create an initial.

        Invoice::create([
            'contract_id' => $contract->id,
            'company_id' => $contract->company_id,
            'invoice_number' => 'INV-' . strtoupper(uniqid()),
            'total_amount' => $contract->monthly_salary, // Taking monthly_salary as standard format.
            'amount_paid' => 0,
            'due_date' => Carbon::parse($contract->contract_start)->addDays(15), // Arbitrary 15 day logic
            'status' => 'pending'
        ]);
    }
}
