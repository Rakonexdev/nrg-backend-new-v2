<?php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpenseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'expense_date' => $this->expense_date ? $this->expense_date->format('Y-m-d') : null,
            'amount' => (float) $this->amount,
            'description' => $this->description,
            'payment_method' => $this->payment_method,
            'category' => [
                'id' => $this->category_id,
                'name' => $this->category?->name,
            ],
            'subcategory' => [
                'id' => $this->subcategory_id,
                'name' => $this->subcategory?->name,
            ],
            'vendor_name' => $this->vendor_name,
            'is_recoverable' => (bool) $this->is_recoverable,
            'notes' => $this->notes,
            'staff' => $this->staff ? [
                'id' => $this->staff->id,
                'name' => $this->staff->name,
            ] : null,
        ];
    }
}
