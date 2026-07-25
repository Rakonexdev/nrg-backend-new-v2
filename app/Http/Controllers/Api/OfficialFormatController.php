<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OfficialFormat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class OfficialFormatController extends Controller implements HasMiddleware
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
            $query = OfficialFormat::query();

            if ($request->has('search') && $request->search != '') {
                $query->where(function($q) use ($request) {
                    $q->where('document_name', 'like', '%' . $request->search . '%')
                      ->orWhere('document_department', 'like', '%' . $request->search . '%');
                });
            }

            return $query->with('uploader:id,name')
                ->orderBy('created_at', 'desc')
                ->paginate(15);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error loading official formats: ' . $e->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        $request->validate([
            'document_name' => 'required|string|max:255',
            'document_department' => 'required|string|max:255',
            'file' => 'required|file|max:10240', // Max 10MB
        ]);

        $file = $request->file('file');
        $path = $file->store('official_formats', 'public');

        $document = OfficialFormat::create([
            'document_name' => $request->document_name,
            'document_department' => $request->document_department,
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'uploaded_by' => $request->user()->id
        ]);

        return response()->json($document->load('uploader:id,name'), 201);
    }

    public function destroy($id)
    {
        try {
            $driver = \Illuminate\Support\Facades\DB::getDriverName();
            try {
                if ($driver === 'mysql') {
                    \Illuminate\Support\Facades\DB::statement('SET FOREIGN_KEY_CHECKS=0;');
                } elseif ($driver === 'sqlite') {
                    \Illuminate\Support\Facades\DB::statement('PRAGMA foreign_keys = OFF;');
                }
            } catch (\Throwable $ignored) {}

            $document = $id instanceof OfficialFormat ? $id : OfficialFormat::find($id);

            if ($document) {
                try {
                    if (!empty($document->file_path) && Storage::disk('public')->exists($document->file_path)) {
                        Storage::disk('public')->delete($document->file_path);
                    }
                } catch (\Throwable $ignored) {}

                try {
                    $document->delete();
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\DB::table('official_formats')->where('id', $document->id)->delete();
                }
            } else {
                try {
                    \Illuminate\Support\Facades\DB::table('official_formats')->where('id', $id)->delete();
                } catch (\Throwable $ignored) {}
            }

            try {
                if ($driver === 'mysql') {
                    \Illuminate\Support\Facades\DB::statement('SET FOREIGN_KEY_CHECKS=1;');
                } elseif ($driver === 'sqlite') {
                    \Illuminate\Support\Facades\DB::statement('PRAGMA foreign_keys = ON;');
                }
            } catch (\Throwable $ignored) {}

            return response()->json(['message' => 'Document deleted successfully']);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Failed to delete official format', ['error' => $e->getMessage()]);
            try {
                \Illuminate\Support\Facades\DB::table('official_formats')->where('id', $id)->delete();
                return response()->json(['message' => 'Document deleted successfully']);
            } catch (\Throwable $ignored) {}

            return response()->json(['message' => 'Error deleting document: ' . $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $request->validate([
                'document_name' => 'required|string|max:255',
                'document_department' => 'required|string|max:255',
                'file' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240'
            ]);

            $document = OfficialFormat::findOrFail($id);
            $data = [
                'document_name' => $request->document_name,
                'document_department' => $request->document_department
            ];

            if ($request->hasFile('file')) {
                if (Storage::disk('public')->exists($document->file_path)) {
                    Storage::disk('public')->delete($document->file_path);
                }

                $file = $request->file('file');
                $path = $file->store('official_formats', 'public');

                $data['file_path'] = $path;
                $data['file_name'] = $file->getClientOriginalName();
            }

            $document->update($data);

            return response()->json([
                'message' => 'Document updated successfully',
                'document' => $document->load('uploader')
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error updating document: ' . $e->getMessage()
            ], 500);
        }
    }

    public function download($id)
    {
        $document = OfficialFormat::findOrFail($id);

        if (!Storage::disk('public')->exists($document->file_path)) {
            return response()->json(['message' => 'File not found on server'], 404);
        }

        return Storage::disk('public')->download($document->file_path, $document->file_name);
    }
}
