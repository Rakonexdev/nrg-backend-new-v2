<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GeneralDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class GeneralDocumentController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:view_documentation', only: ['index']),
            new Middleware('can:documentation_create', only: ['store']),
            new Middleware('can:documentation_edit', only: ['update']),
            new Middleware('can:documentation_delete', only: ['destroy']),
            new Middleware('can:documentation_download', only: ['download']),
        ];
    }

    public function index(Request $request)
    {
        try {
            $request->validate([
                'category' => 'required|in:company,other'
            ]);

            $query = GeneralDocument::where('category', $request->category);

            if ($request->has('search') && $request->search != '') {
                $query->where('document_name', 'like', '%' . $request->search . '%');
            }

            return $query->with('uploader:id,name')
                ->orderBy('created_at', 'desc')
                ->paginate(15);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error loading documents: ' . $e->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        $request->validate([
            'document_name' => 'required|string|max:255',
            'category' => 'required|in:company,other',
            'file' => 'required|file|max:10240', // Max 10MB
        ]);

        $file = $request->file('file');
        $path = $file->store('general_documents/' . $request->category, 'public');

        $document = GeneralDocument::create([
            'document_name' => $request->document_name,
            'category' => $request->category,
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'uploaded_by' => $request->user()->id
        ]);

        return response()->json($document->load('uploader:id,name'), 201);
    }

    public function destroy($id)
    {
        $document = GeneralDocument::findOrFail($id);

        // Delete file from storage
        if (Storage::disk('public')->exists($document->file_path)) {
            Storage::disk('public')->delete($document->file_path);
        }

        $document->delete();

        return response()->json(['message' => 'Document deleted successfully']);
    }

    public function update(Request $request, $id)
    {
        try {
            $request->validate([
                'document_name' => 'required|string|max:255',
                'file' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240'
            ]);

            $document = GeneralDocument::findOrFail($id);
            $data = [
                'document_name' => $request->document_name
            ];

            if ($request->hasFile('file')) {
                // Delete old file
                if (\Illuminate\Support\Facades\Storage::disk('public')->exists($document->file_path)) {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($document->file_path);
                }

                $file = $request->file('file');
                $path = $file->store('general_documents', 'public');

                $data['file_path'] = $path;
                $data['file_name'] = $file->getClientOriginalName();
            }

            $document->update($data);

            return response()->json([
                'message' => 'Document updated successfully',
                'document' => $document->load('uploader')
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error updating document: ' . $e->getMessage()
            ], 500);
        }
    }

    public function download($id)
    {
        $document = GeneralDocument::findOrFail($id);

        if (!Storage::disk('public')->exists($document->file_path)) {
            return response()->json(['message' => 'File not found on server'], 404);
        }

        return Storage::disk('public')->download($document->file_path, $document->file_name);
    }
}
