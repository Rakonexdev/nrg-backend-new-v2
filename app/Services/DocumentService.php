<?php
namespace App\Services;

use App\Models\StaffDocument;

class DocumentService
{
    public function storeDocument($staffId, array $data)
    {
        $data['staff_id'] = $staffId;
        return StaffDocument::create($data);
    }
}
