<?php
namespace App\Services;

use App\Models\Invoice;

class InvoiceService
{
    public function updateStatus(Invoice $invoice)
    {
        $paid = $invoice->collections()->sum('collected_amount');
        $invoice->amount_paid = $paid;
        
        if ($paid >= $invoice->total_amount) {
            $invoice->status = 'paid';
        } elseif ($paid > 0) {
            $invoice->status = 'partial';
        } else {
            $invoice->status = 'pending';
        }

        if ($invoice->status !== 'paid' && now()->isAfter($invoice->due_date)) {
            $invoice->status = 'overdue';
        }

        $invoice->save();
        return $invoice;
    }
}
