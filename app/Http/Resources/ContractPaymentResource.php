<?php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContractPaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'contract_id' => $this->contract_id,
            'amount' => (float) $this->amount,
            'payment_date' => optional($this->payment_date)->toIso8601String(),
            'payment_method' => $this->payment_method,
            'subcategory' => $this->subcategory,
            'next_payment_date' => optional($this->next_payment_date)->format('Y-m-d'),
            'notes' => $this->notes,
            'is_settled' => (bool) $this->is_settled,
            'settled_at' => $this->settled_at ? $this->settled_at->toIso8601String() : null,
            'settlement' => $this->whenLoaded('settlement'),
            'contract_adjustment_id' => $this->contract_adjustment_id,
            'status' => $this->status ?? 'not_collected',
            'created_by' => $this->created_by,
            'recorded_by' => $this->creator ? $this->creator->name : 'N/A',
            'recorded_by_role' => $this->creator ? ($this->creator->role === 'super_admin' ? 'Super Admin' : ($this->creator->role === 'admin' ? 'Admin' : $this->creator->role)) : null,
            'created_at' => $this->created_at,
        ];
    }
}
