<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\StaffDocument;
use App\Services\DocumentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    protected $service;
    public function __construct(DocumentService $service) {
        $this->service = $service;
    }

    public function index($staffId)
    {
        return StaffDocument::where('staff_id', $staffId)->get();
    }

    public function store(Request $request, $staffId)
    {
        $request->validate([
            'document_type' => 'required|in:qid,passport',
            'file' => 'required|file'
        ]);

        $path = $request->file('file')->store('documents');

        $doc = $this->service->storeDocument($staffId, [
            'document_type' => $request->document_type,
            'file_name' => $request->file('file')->getClientOriginalName(),
            'file_path' => $path,
            'uploaded_by' => $request->user()->id
        ]);

        return response()->json($doc, 201);
    }
}