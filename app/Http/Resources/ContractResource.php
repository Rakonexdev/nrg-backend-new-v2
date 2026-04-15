<?php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContractResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'staff_id' => $this->staff_id,
            'staff' => new StaffResource($this->whenLoaded('staff')),
            'company_id' => $this->company_id,
            'company' => new CompanyResource($this->whenLoaded('company')),
            'start_date' => $this->start_date->format('Y-m-d'),
            'end_date' => $this->end_date->format('Y-m-d'),
            'contract_value' => (float) $this->contract_value,
            'paid_amount' => (float) $this->paid_amount,
            'pending_amount' => (float) $this->pending_amount,
            'payment_status' => $this->payment_status,
            'payment_type' => $this->payment_type,
            'payments' => ContractPaymentResource::collection($this->whenLoaded('payments')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
