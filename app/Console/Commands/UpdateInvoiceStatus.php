<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Invoice;
use App\Services\InvoiceService;

class UpdateInvoiceStatus extends Command
{
    protected $signature = 'invoices:update-status';
    protected $description = 'Update invoice statuses based on due dates and collections';

    public function handle(InvoiceService $invoiceService)
    {
        $invoices = Invoice::where('status', '!=', 'paid')->get();
        $count = 0;
        foreach ($invoices as $invoice) {
            $invoiceService->updateStatus($invoice);
            $count++;
        }
        $this->info("Updated {$count} invoices.");
    }
}
