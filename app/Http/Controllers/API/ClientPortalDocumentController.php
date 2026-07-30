<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Support\DocumentStoredFilename;
use App\Support\DocumentFilenameRules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class ClientPortalDocumentController extends Controller
{
    /**
     * List Of Personal Document Category
     * GET /api/documents/personal/categories
     * 
     * Personal documents are matter independent
     */
    public function getPersonalDocumentCategories(Request $request)
    {
        try {
            $admin = $request->user();
            $clientId = $admin->id;
            $isCompany = (bool) DB::table('admins')->where('id', $clientId)->value('is_company');
            $typeScope = $isCompany ? ['company', 'both'] : ['personal', 'both'];

            // Get personal document categories (matter independent)
            // From personal_document_types table where client_id IS NULL or matches current client
            $categories = DB::table('personal_document_types')
                ->where('status', 1) // Active categories only
                ->whereIn('type', $typeScope)
                ->where(function($query) use ($clientId) {
                    $query->whereNull('client_id') // Global categories
                          ->orWhere('client_id', $clientId); // Client-specific categories
                })
                ->orderBy('id', 'asc')
                ->select(
                    'id',
                    'title',
                    'status',
                    'client_id',
                    'type',
                    'created_at',
                    'updated_at'
                )
                ->get()
                ->map(function ($category) {
                    return [
                        'id' => $category->id,
                        'title' => $category->title,
                        'name' => $category->title, // Alias for consistency
                        'status' => $category->status,
                        'is_active' => $category->status == 1,
                        'is_global' => is_null($category->client_id),
                        'is_client_specific' => !is_null($category->client_id),
                        'client_id' => $category->client_id,
                        'category_type' => 'personal',
                        'created_at' => $category->created_at,
                        'updated_at' => $category->updated_at
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'categories' => $categories,
                    'total_categories' => $categories->count(),
                    'category_type' => 'personal'
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Personal Document Categories API Error: ' . $e->getMessage(), [
                'user_id' => $admin->id ?? null,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch personal document categories',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * List Of Personal Document Checklist
     * GET /api/documents/personal/checklist
     * 
     * Personal documents are matter independent
     */
    public function getPersonalDocumentChecklist(Request $request)
    {
        try {
            $admin = $request->user();
            $clientId = $admin->id;
            
            // Get personal document checklist (matter independent)
            // From portal_document_checklists table where doc_type=1 (Personal) and status=1
            $checklist = DB::table('portal_document_checklists')
                ->where('doc_type', 1) // 1 = Personal documents
                ->where('status', 1) // Active items only
                ->orderBy('id', 'asc')
                ->get()
                ->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'name' => $item->name ?? 'Document',
                        'description' => $item->description ?? '',
                        'doc_type' => $item->doc_type,
                        'doc_type_name' => 'Personal',
                        'status' => $item->status,
                        'is_active' => $item->status == 1,
                        'document_type' => 'personal',
                        'created_at' => $item->created_at ?? null,
                        'updated_at' => $item->updated_at ?? null
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'checklist' => $checklist,
                    'total_items' => $checklist->count(),
                    'active_items' => $checklist->where('is_active', true)->count(),
                    'document_type' => 'personal',
                    'doc_type_id' => 1
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Personal Document Checklist API Error: ' . $e->getMessage(), [
                'user_id' => $admin->id ?? null,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch personal document checklist',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * List Of Visa Document Category
     * GET /api/documents/visa/categories
     * 
     * Visa documents categories with client and matter filtering
     */
    public function getVisaDocumentCategories(Request $request)
    {
        try {
            $admin = $request->user();
            $clientId = $admin->id;
            
            // Get client_matter_id parameter (optional)
            $clientMatterId = $request->get('client_matter_id');
            
            // Get visa document categories from visa_document_types table
            // Where status=1 AND (client_id IS NULL OR client_matter_id IS NULL) 
            // OR (client_id matches AND client_matter_id matches if provided)
            $categories = DB::table('visa_document_types')
                ->where('status', 1) // Active categories only
                ->where(function($query) use ($clientId, $clientMatterId) {
                    // Global categories (both client_id and client_matter_id are NULL)
                    $query->where(function($subQuery) {
                        $subQuery->whereNull('client_id')
                                ->whereNull('client_matter_id');
                    })
                    // OR client-specific categories
                    ->orWhere(function($subQuery) use ($clientId, $clientMatterId) {
                        $subQuery->where('client_id', $clientId);
                        
                        // If client_matter_id is provided, filter by it, otherwise include records where client_matter_id is NULL
                        if (!is_null($clientMatterId)) {
                            $subQuery->where(function($matterQuery) use ($clientMatterId) {
                                $matterQuery->where('client_matter_id', $clientMatterId)
                                           ->orWhereNull('client_matter_id');
                            });
                        } else {
                            $subQuery->whereNull('client_matter_id');
                        }
                    });
                })
                ->orderBy('id', 'asc')
                ->select(
                    'id',
                    'title',
                    'status',
                    'client_id',
                    'client_matter_id',
                    'created_at',
                    'updated_at'
                )
                ->get()
                ->map(function ($category) use ($clientId, $clientMatterId) {
                    return [
                        'id' => $category->id,
                        'title' => $category->title,
                        'name' => $category->title, // Alias for consistency
                        'status' => $category->status,
                        'is_active' => $category->status == 1,
                        'is_global' => is_null($category->client_id) && is_null($category->client_matter_id),
                        'is_client_specific' => !is_null($category->client_id),
                        'is_matter_specific' => !is_null($category->client_matter_id),
                        'client_id' => $category->client_id,
                        'client_matter_id' => $category->client_matter_id,
                        'category_type' => 'visa',
                        'created_at' => $category->created_at,
                        'updated_at' => $category->updated_at
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'categories' => $categories,
                    'total_categories' => $categories->count(),
                    'global_categories' => $categories->where('is_global', true)->count(),
                    'client_specific_categories' => $categories->where('is_client_specific', true)->count(),
                    'matter_specific_categories' => $categories->where('is_matter_specific', true)->count(),
                    'category_type' => 'visa',
                    'client_id' => $clientId,
                    'client_matter_id' => $clientMatterId
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Visa Document Categories API Error: ' . $e->getMessage(), [
                'user_id' => $admin->id ?? null,
                'client_matter_id' => $clientMatterId ?? null,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch visa document categories',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * List Of Visa Document Checklist
     * GET /api/documents/visa/checklist
     * 
     * Visa documents checklist (no matter dependency)
     */
    public function getVisaDocumentChecklist(Request $request)
    {
        try {
            $admin = $request->user();
            $clientId = $admin->id;

            // Get visa document checklist for the specific matter
            // From portal_document_checklists table where doc_type=2 (Visa) and status=1
            $checklist = DB::table('portal_document_checklists')
                ->where('doc_type', 2) // 2 = Visa documents
                ->where('status', 1) // Active items only
                ->orderBy('id', 'asc')
                ->get()
                ->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'name' => $item->name ?? 'Document',
                        'description' => $item->description ?? '',
                        'doc_type' => $item->doc_type,
                        'doc_type_name' => 'Visa',
                        'status' => $item->status,
                        'is_active' => $item->status == 1,
                        'document_type' => 'visa',
                        'created_at' => $item->created_at ?? null,
                        'updated_at' => $item->updated_at ?? null
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'checklist' => $checklist,
                    'total_items' => $checklist->count(),
                    'active_items' => $checklist->where('is_active', true)->count(),
                    'document_type' => 'visa',
                    'doc_type_id' => 2
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Visa Document Checklist API Error: ' . $e->getMessage(), [
                'user_id' => $admin->id ?? null,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch visa document checklist',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Helper method to format file size
     */
    private function formatFileSize($bytes)
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' bytes';
        }
    }

    /**
     * Add Document Checklist
     * POST /api/documents/checklist
     * 
     * Creates a new document checklist entry in the documents table
     */
    public function addDocumentChecklist(Request $request)
    {
        try {
            $admin = $request->user();
            $clientId = $admin->id;

            $checklistId = $request->input('checklist_id');
            $docType = $request->input('doc_type');
            $docCategoryId = $request->input('doc_category_id');
            $clientMatterId = $request->input('client_matter_id');

            // Dynamic validation based on doc_type
            $validationRules = [
                'checklist_id' => 'required|integer|min:1',
                'doc_type' => 'required|string|in:personal,visa',
                'doc_category_id' => 'required|integer|min:1'
            ];

            // Add client_matter_id validation based on doc_type
            if ($docType === 'visa') {
                $validationRules['client_matter_id'] = 'required|integer|min:1';
            } else {
                $validationRules['client_matter_id'] = 'nullable';
            }

            $validator = Validator::make($request->all(), $validationRules);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Additional business logic validation
            if ($docType === 'visa' && empty($clientMatterId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'client_matter_id is mandatory for visa documents'
                ], 422);
            }

            if ($docType === 'personal' && !empty($clientMatterId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'client_matter_id must be null for personal documents'
                ], 422);
            }

            // Force client_matter_id to null for personal documents
            if ($docType === 'personal') {
                $clientMatterId = null;
            }

            // The document row is written under the caller's client_id, so the matter it is
            // linked to has to belong to the same client.
            if ($docType === 'visa') {
                $ownsClientMatter = DB::table('client_matters')
                    ->where('id', $clientMatterId)
                    ->where('client_id', $clientId)
                    ->exists();

                if (!$ownsClientMatter) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid client_matter_id for the authenticated client'
                    ], 422);
                }
            }

            // Get checklist name from portal_document_checklists table based on doc_type
            $docTypeId = ($docType === 'personal') ? 1 : 2;
            $checklistRecord = DB::table('portal_document_checklists')
                ->where('id', $checklistId)
                ->where('doc_type', $docTypeId)
                ->where('status', 1)
                ->first();

            if (!$checklistRecord) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid checklist_id for the specified doc_type'
                ], 422);
            }

            $checklistName = $checklistRecord->name;

            // Verify that the doc_category_id exists and matches the doc_type
            if ($docType === 'personal') {
                $isCompany = (bool) DB::table('admins')->where('id', $clientId)->value('is_company');
                $typeScope = $isCompany ? ['company', 'both'] : ['personal', 'both'];
                $categoryExists = DB::table('personal_document_types')
                    ->where('id', $docCategoryId)
                    ->where('status', 1)
                    ->whereIn('type', $typeScope)
                    ->where(function($query) use ($clientId) {
                        $query->whereNull('client_id')
                              ->orWhere('client_id', $clientId);
                    })
                    ->exists();
            } else {
                $categoryExists = DB::table('visa_document_types')
                    ->where('id', $docCategoryId)
                    ->where('status', 1)
                    ->where(function($query) use ($clientId, $clientMatterId) {
                        $query->where(function($subQuery) {
                            // Global categories
                            $subQuery->whereNull('client_id')
                                    ->whereNull('client_matter_id');
                        })
                        ->orWhere(function($subQuery) use ($clientId) {
                            // Client-specific categories
                            $subQuery->where('client_id', $clientId)
                                    ->whereNull('client_matter_id');
                        })
                        ->orWhere(function($subQuery) use ($clientId, $clientMatterId) {
                            // Matter-specific categories
                            $subQuery->where('client_id', $clientId)
                                    ->where('client_matter_id', $clientMatterId);
                        });
                    })
                    ->exists();
            }

            if (!$categoryExists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid doc_category_id for the specified doc_type'
                ], 422);
            }

            // Prepare data for insertion
            $documentData = [
                'file_name' => null,
                'filetype' => null,
                'myfile' => null,
                'myfile_key' => null,
                'user_id' => $admin->id,
                'client_id' => $clientId,
                'file_size' => null,
                'type' => 'client',
                'doc_type' => $docType,
                'folder_name' => $docCategoryId,
                'mail_type' => null,
                'client_matter_id' => $clientMatterId,
                'checklist' => $checklistName,
                'not_used_doc' => null,
                'status' => 'draft',
                'signature_doc_link' => null,
                'signed_doc_link' => null,
                'is_client_portal_verify' => 2,
                'created_at' => now(),
                'updated_at' => now()
            ];

            // Insert the document checklist entry
            $documentId = DB::table('documents')->insertGetId($documentData);

            if ($documentId) {
                // Get the created document
                $createdDocument = DB::table('documents')->where('id', $documentId)->first();

                return response()->json([
                    'success' => true,
                    'message' => 'Document checklist added successfully',
                    'data' => [
                        'id' => $createdDocument->id,
                        'checklist' => $createdDocument->checklist,
                        'doc_type' => $createdDocument->doc_type,
                        'doc_category_id' => $createdDocument->folder_name,
                        'client_matter_id' => $createdDocument->client_matter_id,
                        'status' => $createdDocument->status,
                        'is_client_portal_verify' => $createdDocument->is_client_portal_verify,
                        'client_id' => $createdDocument->client_id,
                        'user_id' => $createdDocument->user_id,
                        'created_at' => $createdDocument->created_at,
                        'updated_at' => $createdDocument->updated_at
                    ]
                ], 201);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to add document checklist'
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('Add Document Checklist API Error: ' . $e->getMessage(), [
                'user_id' => $admin->id ?? null,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to add document checklist',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload Document
     * POST /api/documents/upload
     */
    public function uploadDocument(Request $request)
    {
        try {
            $admin = $request->user();
            $clientId = $admin->id;

            // Validate request
            $validator = Validator::make($request->all(), [
                'document_id' => 'required|integer|min:1',
                'file' => 'required|file|max:10240' // 10MB max file size, single file only
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Additional validation: Ensure only one file is uploaded
            if (!$request->hasFile('file')) {
                return response()->json([
                    'success' => false,
                    'message' => 'File is required and must be uploaded'
                ], 422);
            }

            // Check if multiple files are uploaded (should not happen with single file input)
            if (is_array($request->file('file'))) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only one file can be uploaded at a time'
                ], 422);
            }

            $documentId = $request->input('document_id');
            $file = $request->file('file');

            // Get client information (company name prefix when is_company)
            $adminForPrefix = Admin::query()
                ->select(['id', 'client_id', 'first_name', 'is_company'])
                ->with('company:id,admin_id,company_name')
                ->where('id', $clientId)
                ->first();

            if (!$adminForPrefix) {
                return response()->json([
                    'success' => false,
                    'message' => 'Client not found'
                ], 404);
            }

            $clientUniqueId = $adminForPrefix->client_id;
            $sanitizedFirstName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $adminForPrefix->first_name ?? '');
            $namePrefix = DocumentStoredFilename::storedNamePrefix($adminForPrefix, $sanitizedFirstName);

            // Get the document record
            $document = DB::table('documents')
                ->where('id', $documentId)
                ->where('client_id', $clientId)
                ->first();

            if (!$document) {
                return response()->json([
                    'success' => false,
                    'message' => 'Document not found or access denied'
                ], 404);
            }

            // Validate file name
            $fileName = $file->getClientOriginalName();
            if (!DocumentFilenameRules::isAllowed($fileName)) {
                return response()->json([
                    'success' => false,
                    'message' => DocumentFilenameRules::validationMessage()
                ], 422);
            }

            // Get file details
            $fileSize = $file->getSize();
            $extension = $file->getClientOriginalExtension();
            $checklistName = $document->checklist;

            $timestamp = time();
            $fileNameStem = $namePrefix . "_" . $checklistName . "_" . $timestamp;
            $newFileName = $fileNameStem . "." . $extension;

            // Build file path for AWS S3
            $filePath = $clientUniqueId . '/' . $document->doc_type . '/' . $newFileName;

            // Upload to AWS S3
            Storage::disk('s3')->put($filePath, file_get_contents($file));

            // Get file URL
            $fileUrl = Storage::disk('s3')->url($filePath);

            // Update document record
            $updateData = [
                'file_name' => $fileNameStem,
                'filetype' => $extension,
                'myfile' => $fileUrl,
                'myfile_key' => $newFileName,
                'file_size' => $fileSize,
                'status' => 'draft', // Set to draft for client portal uploads
                'updated_at' => now()
            ];

            $updated = DB::table('documents')
                ->where('id', $documentId)
                ->update($updateData);

            if ($updated) {
                // Create activity log
                $activitySubject = 'updated ' . $document->doc_type . ' document';
                DB::table('activities_logs')->insert([
                    'client_id' => $clientId,
                    'created_by' => $clientId,
                    'subject' => $activitySubject,
                    'description' => 'Document uploaded via client portal',
                    'activity_type' => 'document',
                    'source' => 'client_portal',
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                // Update client matter if it's a visa document
                if ($document->doc_type === 'visa' && $document->client_matter_id) {
                    DB::table('client_matters')
                        ->where('id', $document->client_matter_id)
                        ->update(['updated_at' => now()]);
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Document uploaded successfully',
                    'data' => [
                        'id' => $documentId,
                        'file_name' => $updateData['file_name'],
                        'file_type' => $extension,
                        'file_size' => $fileSize,
                        'file_size_formatted' => $this->formatFileSize($fileSize),
                        'file_url' => $fileUrl,
                        'file_key' => $newFileName,
                        'doc_type' => $document->doc_type,
                        'checklist' => $checklistName,
                        'status' => 'draft',
                        'uploaded_at' => now()->toISOString()
                    ]
                ], 200);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to update document record'
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('Upload Document API Error: ' . $e->getMessage(), [
                'user_id' => $admin->id ?? null,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to upload document',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
