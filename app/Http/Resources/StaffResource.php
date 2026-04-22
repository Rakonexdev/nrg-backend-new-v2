<?php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

use Illuminate\Support\Facades\Storage;

class StaffResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'nationality' => $this->nationality,
            'profession' => $this->profession,
            'mobile' => $this->mobile,
            'date_of_birth' => $this->date_of_birth ? $this->date_of_birth->format('Y-m-d') : null,
            'passport_number' => $this->passport_number,
            'passport_expiry' => $this->passport_expiry ? $this->passport_expiry->format('Y-m-d') : null,
            'qid_number' => $this->qid_number,
            'qid_expiry' => $this->qid_expiry ? $this->qid_expiry->format('Y-m-d') : null,
            'joining_date' => $this->joining_date ? $this->joining_date->format('Y-m-d') : null,
            'company_id' => $this->company_id,
            'company' => $this->company,
            'company_name' => $this->company ? $this->company->name : 'N/A',
            'status' => $this->status,
            'qid_documents' => $this->documents->where('document_type', 'qid')->map(function($doc) {
                return [
                    'id' => $doc->id,
                    'name' => $doc->file_name,
                    'url' => Storage::url($doc->file_path),
                ];
            })->values(),
            'passport_documents' => $this->documents->where('document_type', 'passport')->map(function($doc) {
                return [
                    'id' => $doc->id,
                    'name' => $doc->file_name,
                    'url' => Storage::url($doc->file_path),
                ];
            })->values(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
