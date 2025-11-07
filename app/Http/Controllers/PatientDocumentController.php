<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PatientDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Illuminate\Support\Str;

class PatientDocumentController extends Controller
{
    /**
     * Display a listing of patient documents.
     */
    public function index(Request $request, $birthcare_id): JsonResponse
    {
        $query = PatientDocument::with(['patient', 'createdBy'])
            ->where('birth_care_id', $birthcare_id);

        // Apply filters
        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('file_name', 'like', "%{$search}%")
                  ->orWhereHas('patient', function ($patientQuery) use ($search) {
                      $patientQuery->where('first_name', 'like', "%{$search}%")
                                   ->orWhere('last_name', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->filled('patient_id')) {
            $query->where('patient_id', $request->get('patient_id'));
        }

        if ($request->filled('document_type')) {
            $query->where('document_type', $request->get('document_type'));
        }

        $documents = $query->orderBy('created_at', 'desc')
                          ->paginate(10);

        return response()->json($documents);
    }

    /**
     * Store a newly created patient document.
     */
    public function store(Request $request, $birthcare_id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'patient_id' => 'required|exists:patients,id',
            'title' => 'required|string|max:255',
            'file' => 'required|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240', // 10MB max
            'document_type' => 'required|string|max:100',
            'metadata' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $file = $request->file('file');
            $fileName = (string) Str::uuid() . '.' . $file->getClientOriginalExtension();
            $filePath = $fileName;
            // Upload to Supabase patient-documents bucket
            Storage::disk('supabase_patient')->put($filePath, file_get_contents($file->getRealPath()), [
                'mimetype' => $file->getMimeType(),
            ]);

            $document = PatientDocument::create([
                'patient_id' => $request->patient_id,
                'birth_care_id' => $birthcare_id,
                'title' => $request->title,
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $filePath,
                'document_type' => $request->document_type,
                'file_size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'metadata' => $request->metadata,
                'created_by' => auth()->id(),
            ]);

            return response()->json([
                'message' => 'Document uploaded successfully',
                'document' => $document->load(['patient', 'createdBy'])
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to upload document',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified patient document.
     */
    public function show($birthcare_id, PatientDocument $document): JsonResponse
    {
        $document->load(['patient', 'createdBy']);
        return response()->json($document);
    }

    /**
     * Download the specified patient document.
     */
    public function download($birthcare_id, PatientDocument $document): BinaryFileResponse
    {
        // Try Supabase first, then fall back to public/local storage
        if (Storage::disk('supabase_patient')->exists($document->file_path)) {
            return Storage::disk('supabase_patient')->download($document->file_path, $document->file_name);
        } elseif (Storage::disk('public')->exists($document->file_path)) {
            return Storage::disk('public')->download($document->file_path, $document->file_name);
        } elseif (Storage::exists($document->file_path)) {
            return Storage::download($document->file_path, $document->file_name);
        }
        
        abort(404, 'File not found');
    }

    /**
     * View the specified patient document in browser.
     */
    public function view($birthcare_id, PatientDocument $document)
    {
        // Try Supabase first, then fall back to public/local storage
        if (Storage::disk('supabase_patient')->exists($document->file_path)) {
            return Storage::disk('supabase_patient')->response($document->file_path);
        } elseif (Storage::disk('public')->exists($document->file_path)) {
            return Storage::disk('public')->response($document->file_path);
        } elseif (Storage::exists($document->file_path)) {
            return Storage::response($document->file_path);
        }
        
        abort(404, 'File not found');
    }

    /**
     * Remove the specified patient document.
     */
    public function destroy($birthcare_id, PatientDocument $document): JsonResponse
    {
        try {
            $document->delete();
            
            return response()->json([
                'message' => 'Document deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete document',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a document from form data (for PDF generation)
     */
    public function storeFromData(Request $request, $birthcare_id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'patient_id' => 'required|exists:patients,id',
            'title' => 'required|string|max:255',
            'document_type' => 'required|string|max:100',
            'content' => 'required|string', // PDF content or base64
            'metadata' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Check for duplicate titles and apply numeric suffix if needed
            $originalTitle = $request->title;
            $finalTitle = $this->getUniqueTitle($originalTitle, $request->patient_id, $birthcare_id);

            // Generate filename based on the unique title
            $fileName = time() . '_' . str_replace(' ', '_', $finalTitle) . '.pdf';
            $filePath = $fileName;

            // Store the PDF content
            Storage::disk('supabase_patient')->put($filePath, base64_decode($request->content));
            $fileSize = Storage::disk('supabase_patient')->size($filePath);

            $document = PatientDocument::create([
                'patient_id' => $request->patient_id,
                'birth_care_id' => $birthcare_id,
                'title' => $finalTitle,
                'file_name' => $fileName,
                'file_path' => $filePath,
                'document_type' => $request->document_type,
                'file_size' => $fileSize,
                'mime_type' => 'application/pdf',
                'metadata' => $request->metadata,
                'created_by' => auth()->id(),
            ]);

            return response()->json([
                'message' => 'Document created successfully',
                'document' => $document->load(['patient', 'createdBy'])
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create document',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate a unique title by adding numeric suffix if title already exists
     * 
     * @param string $originalTitle
     * @param int $patientId
     * @param int $birthcareId
     * @return string
     */
    private function getUniqueTitle(string $originalTitle, int $patientId, int $birthcareId): string
    {
        // Check if the original title exists
        $existingDocument = PatientDocument::where('title', $originalTitle)
            ->where('patient_id', $patientId)
            ->where('birth_care_id', $birthcareId)
            ->first();
        
        // If original title doesn't exist, use it as is
        if (!$existingDocument) {
            return $originalTitle;
        }
        
        // Find all documents with titles that start with the original title
        // This will match "Title", "Title (1)", "Title (2)", etc.
        $pattern = $originalTitle . '%';
        $existingTitles = PatientDocument::where('title', 'like', $pattern)
            ->where('patient_id', $patientId)
            ->where('birth_care_id', $birthcareId)
            ->pluck('title')
            ->toArray();
        
        // Extract numeric suffixes from existing titles
        $maxSuffix = 0;
        $baseTitle = $originalTitle;
        
        foreach ($existingTitles as $title) {
            if ($title === $originalTitle) {
                // Original title exists, so we need at least (1)
                $maxSuffix = max($maxSuffix, 0);
            } else {
                // Check if title matches pattern "BaseTitle (n)"
                $pattern = '/^' . preg_quote($originalTitle, '/') . ' \((\d+)\)$/';
                if (preg_match($pattern, $title, $matches)) {
                    $maxSuffix = max($maxSuffix, (int)$matches[1]);
                }
            }
        }
        
        // Generate next available suffix
        $nextSuffix = $maxSuffix + 1;
        return $originalTitle . ' (' . $nextSuffix . ')';
    }
}
