<?php
namespace App\Services;

use App\Models\Collection;
use App\Models\Invoice;

class CollectionService
{
    protected $invoiceService;

    public function __construct(InvoiceService $invoiceService)
    {
        $this->invoiceService = $invoiceService;
    }

    public function createCollection(array $data)
    {
        $collection = Collection::create($data);
        
        $invoice = Invoice::find($data['invoice_id']);
        if ($invoice) {
            $this->invoiceService->updateStatus($invoice);
        }

        return $collection;
    }
}
