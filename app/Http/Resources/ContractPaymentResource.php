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
            'payment_date' => optional($this->payment_date)->format('Y-m-d'),
            'payment_method' => $this->payment_method,
            'notes' => $this->notes,
            'is_settled' => (bool) $this->is_settled,
            'settled_at' => $this->settled_at ? $this->settled_at->format('Y-m-d H:i:s') : null,
            'settlement' => $this->whenLoaded('settlement'),
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
        ];
    }
}
