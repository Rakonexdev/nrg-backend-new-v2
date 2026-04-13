<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function index()
    {
        return Invoice::with('company', 'contract')->paginate(15);
    }

    public function show($id)
    {
        return Invoice::with('company', 'contract', 'collections')->findOrFail($id);
    }

    public function update(Request $request, $id)
    {
        $invoice = Invoice::findOrFail($id);
        $invoice->update($request->only('due_date'));
        return $invoice;
    }
}