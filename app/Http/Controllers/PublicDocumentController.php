<?php

namespace App\Http\Controllers;

use App\Models\ActivitiesLog;
use App\Models\ClientMatter;
use App\Models\Document;
use App\Models\Signer;
use App\Models\WorkflowStage;
use App\Helpers\TempFileHelper;
use App\Services\PythonService;
use App\Services\SignedDocumentS3PathResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/**
 * Public Document Controller
 * 
 * Handles public-facing document signing operations without authentication.
 * Access is controlled through unique tokens sent via email.
 */
class PublicDocumentController extends Controller
{
    /**
     * No authentication required - using token-based validation
     */
    public function __construct()
    {
        // Public controller - no authentication middleware
    }

    /**
     * Show the public signing form for a document using a tokenized link.
     * 
     * @param int $id Document ID
     * @param string $token Unique signer token
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse
     */
    public function sign($id, $token)
    {
        // Sanitize and validate inputs
        $documentId = (int) $id;
        if ($documentId <= 0) {
            Log::warning('Invalid document ID in public sign method', ['id' => $id]);
            // ✅ FIX #4: Don't redirect public users to admin login
            abort(404, 'Invalid document link.');
        }

        // Validate token format
        if (!$token || !is_string($token) || strlen($token) < 32 || !preg_match('/^[a-zA-Z0-9]+$/', $token)) {
            Log::warning('Invalid token format in public sign method', ['token_length' => strlen($token ?? '')]);
            // ✅ FIX #4: Don't redirect public users to admin login
            abort(403, 'Invalid or expired signing link.');
        }

        $tmpPdfPath = null;

        try {
            $document = Document::findOrFail($documentId);
            
            // Handle agreement type documents specially
            if (isset($document->doc_type) && $document->doc_type == 'agreement') {
                $signer = $document->signers()->where('document_id', '=', $documentId)->first();
                if ($signer) {
                    // Only update token/status when still pending - never overwrite 'signed' or 'cancelled'
                    if ($signer->status === 'pending') {
                        $signer->update(['token' => $token, 'status' => 'pending']);
                        $signer = $document->signers()->where('token', '=', $token)->first();
                    }
                    // If signed or cancelled, $signer stays as-is; status check below will redirect
                } else {
                    $signer = $document->signers()->create([
                        'token' => $token,
                        'status' => 'pending'
                    ]);
                }
            } else {
                $signer = $document->signers()->where('token', '=', $token)->first();
            }

            if (!$signer || $signer->status === 'signed' || $signer->status === 'cancelled') {
                Log::warning('Invalid signer, already signed, or cancelled', [
                    'document_id' => $documentId,
                    'signer_exists' => !is_null($signer),
                    'signer_status' => $signer ? $signer->status : 'none'
                ]);
                // ✅ FIX #4: If already signed, go to thank you page
                if ($signer && $signer->status === 'signed') {
                    return redirect()->route('public.documents.thankyou', ['id' => $document->id])
                        ->with('info', 'This document has already been signed.');
                }
                // If cancelled, show proper error page (don't use abort to avoid redirect to login)
                if ($signer && $signer->status === 'cancelled') {
                    return $this->showCancelledError($document);
                }
                // For invalid signer, show error page
                return $this->showErrorPage('Invalid Signing Link', 'Invalid or expired signing link. Please contact the document sender for assistance.', $document ?? null);
            }

            // Track when document was opened
            if (!$signer->opened_at) {
                $signer->update(['opened_at' => now()]);
            }

            $signatureFields = $document->signatureFields()->get();
            
            // Get PDF path - handle both S3 and local files
            $url = $document->myfile;
            $pdfPath = null;
            $isLocalFile = false;
            
            // Check if URL is a full S3 URL or local path
            if ($url && filter_var($url, FILTER_VALIDATE_URL) && strpos($url, 's3') !== false) {
                // This is an S3 URL - extract the key
                $parsed = parse_url($url);
                if (isset($parsed['path'])) {
                    $pdfPath = ltrim(urldecode($parsed['path']), '/');
                }
                
                if ($pdfPath && Storage::disk('s3')->exists($pdfPath)) {
                    // Download PDF from S3 to a temp file
                    $tmpPdfPath = storage_path('app/tmp_' . uniqid() . '.pdf');
                    $pdfStream = Storage::disk('s3')->get($pdfPath);
                    file_put_contents($tmpPdfPath, $pdfStream);
                }
            } elseif ($url && file_exists(storage_path('app/public/' . $url))) {
                // This is a local file path and file exists
                $tmpPdfPath = storage_path('app/public/' . $url);
                $isLocalFile = true;
                Log::info('Using local file for document signing page', ['path' => $tmpPdfPath]);
            } else {
                // Try to build S3 key from DB fields as fallback
                if (!empty($document->myfile_key) && !empty($document->doc_type) && !empty($document->client_id)) {
                    $admin = DB::table('admins')->select('client_id')->where('id', '=', $document->client_id)->first();
                    if ($admin && $admin->client_id) {
                        $pdfPath = $admin->client_id . '/' . $document->doc_type . '/' . $document->myfile_key;
                        
                        if (Storage::disk('s3')->exists($pdfPath)) {
                            // Download PDF from S3 to a temp file
                            $tmpPdfPath = storage_path('app/tmp_' . uniqid() . '.pdf');
                            $pdfStream = Storage::disk('s3')->get($pdfPath);
                            file_put_contents($tmpPdfPath, $pdfStream);
                        }
                    }
                }
            }

            // Count PDF pages
            $pdfPages = 1;
            if ($tmpPdfPath && file_exists($tmpPdfPath)) {
                $pdfPages = $this->countPdfPages($tmpPdfPath) ?: 1;
            }

            return view('documents.sign', compact('document', 'signer', 'signatureFields', 'pdfPages'));
        } catch (\Exception $e) {
            Log::error('Error in public sign method', [
                'error' => $e->getMessage(),
                'document_id' => $documentId,
                'token_present' => !empty($token)
            ]);
            return redirect('/')->with('error', 'An error occurred while loading the signing page.');
        } finally {
            TempFileHelper::delete($tmpPdfPath);
        }
    }

    /**
     * Submit signatures for a public document
     * 
     * @param Request $request
     * @param int $id Document ID
     * @return \Illuminate\Http\RedirectResponse
     */
    public function submitSignatures(Request $request, $id)
    {
        Log::info('=== START submitSignatures ===', [
            'document_id' => $id,
            'signer_id' => $request->signer_id,
            'has_signatures' => $request->has('signatures'),
            'has_positions' => $request->has('signature_positions')
        ]);
        
        // Validation
        $request->validate([
            'signer_id' => 'required|integer|exists:signers,id',
            'token' => 'required|string|min:32',
            'signatures' => 'required|array',
            'signatures.*' => 'nullable|string',
            'signature_positions' => 'required|array',
            'signature_positions.*' => 'nullable|string'
        ]);

        $documentId = (int) $id;
        if ($documentId <= 0) {
            Log::error('Invalid document ID in submitSignatures', ['id' => $id]);
            return redirect('/')->with('error', 'Invalid document ID.');
        }

        try {
            $document = Document::findOrFail($documentId);
            $signer = Signer::findOrFail($request->signer_id);
            
            Log::info('Document and signer loaded', [
                'document_id' => $document->id,
                'signer_id' => $signer->id,
                'signer_status' => $signer->status,
                'signer_token_set' => $signer->token !== null
            ]);

            // Verify signer belongs to this document
            if ($signer->document_id !== $document->id) {
                Log::warning('Signer does not belong to document', [
                    'signer_id' => $signer->id,
                    'document_id' => $document->id
                ]);
                return redirect('/')->with('error', 'Invalid signing attempt.');
            }

            // Verify token matches
            if ($signer->token !== $request->token) {
                Log::warning('Token mismatch for signer', [
                    'signer_id' => $signer->id,
                    'document_id' => $document->id,
                    'provided_token' => substr($request->token, 0, 8) . '...',
                    'expected_token' => substr($signer->token, 0, 8) . '...'
                ]);
                return redirect('/')->with('error', 'Invalid or expired signing link.');
            }

            // Check if signer has already signed this document
            if ($signer->status === 'signed') {
                Log::warning('Signer attempting to re-sign already signed document', [
                    'signer_id' => $signer->id,
                    'document_id' => $document->id
                ]);
                return redirect()->route('public.documents.thankyou', ['id' => $document->id])
                    ->with('info', 'This document has already been signed. You can download the signed copy below.');
            }
            
            // Check if signature has been cancelled
            if ($signer->status === 'cancelled') {
                Log::warning('Signer attempting to sign cancelled document', [
                    'signer_id' => $signer->id,
                    'document_id' => $document->id
                ]);
                return redirect('/')->with('error', 'This signing link has been cancelled. Please contact the document sender for assistance.');
            }
            
            if ($signer->token !== null && $signer->status === 'pending') {
                $tmpPdfPath = null;
                $outputTmpPath = null;
                $isLocalFile = false;

                try {
                // Get PDF file using multiple fallback methods (like CRM)
                $url = $document->myfile;
                $pdfPath = null;
                
                // Fallback 1: Check if URL is a full S3 URL
                if ($url && filter_var($url, FILTER_VALIDATE_URL) && strpos($url, 's3') !== false) {
                    $parsed = parse_url($url);
                    if (isset($parsed['path'])) {
                        $pdfPath = ltrim(urldecode($parsed['path']), '/');
                    }
                    
                    if ($pdfPath && Storage::disk('s3')->exists($pdfPath)) {
                        $tmpPdfPath = storage_path('app/tmp_' . uniqid() . '.pdf');
                        try {
                            $pdfStream = Storage::disk('s3')->get($pdfPath);
                            if (!$pdfStream || strlen($pdfStream) === 0) {
                                throw new \Exception('Empty PDF file downloaded from S3');
                            }
                            file_put_contents($tmpPdfPath, $pdfStream);
                            Log::info('Using S3 URL for document submission', ['url' => $url, 'path' => $pdfPath]);
                        } catch (\Exception $e) {
                            Log::error('Failed to download from S3 URL', ['url' => $url, 'error' => $e->getMessage()]);
                            $tmpPdfPath = null;
                        }
                    }
                }
                
                // Fallback 2: Check if file exists locally
                if (!$tmpPdfPath && $url && file_exists(storage_path('app/public/' . $url))) {
                    $tmpPdfPath = storage_path('app/public/' . $url);
                    $isLocalFile = true;
                    Log::info('Using local file for document submission', ['path' => $tmpPdfPath]);
                }
                
                // Fallback 3: Try media library (legacy support)
                if (!$tmpPdfPath) {
                    $mediaPath = $document->getFirstMediaPath('documents');
                    if ($mediaPath && file_exists($mediaPath)) {
                        $tmpPdfPath = $mediaPath;
                        $isLocalFile = true;
                        Log::info('Using media library for document submission', ['path' => $tmpPdfPath]);
                    }
                }
                
                // Fallback 4: Try to build S3 key from DB fields
                if (!$tmpPdfPath) {
                    $clientId = null;
                    if ($document->client_id) {
                        $admin = DB::table('admins')->select('client_id')->where('id', '=', $document->client_id)->first();
                        if ($admin && $admin->client_id) {
                            $clientId = $admin->client_id;
                        }
                    }
                    
                    $docType = $document->doc_type ?? '';
                    $myfileKey = $document->myfile_key ?? '';
                    $s3Key = $clientId && $docType && $myfileKey ? ($clientId . '/' . $docType . '/' . $myfileKey) : null;
                    
                    if ($s3Key && Storage::disk('s3')->exists($s3Key)) {
                        $tmpPdfPath = storage_path('app/tmp_' . uniqid() . '.pdf');
                        try {
                            $pdfStream = Storage::disk('s3')->get($s3Key);
                            if (!$pdfStream || strlen($pdfStream) === 0) {
                                throw new \Exception('Empty PDF file downloaded from S3');
                            }
                            file_put_contents($tmpPdfPath, $pdfStream);
                            Log::info('Using S3 key from DB fields for document submission', ['s3_key' => $s3Key]);
                        } catch (\Exception $e) {
                            Log::error('Failed to download from S3 using DB fields', ['s3_key' => $s3Key, 'error' => $e->getMessage()]);
                            $tmpPdfPath = null;
                        }
                    } else {
                        Log::error('S3 key not found or file does not exist', [
                            'document_id' => $document->id,
                            'client_id' => $clientId,
                            'doc_type' => $docType,
                            'myfile_key' => $myfileKey,
                            's3_key' => $s3Key
                        ]);
                    }
                }
                
                // Final check: If no file found, return error
                if (!$tmpPdfPath || !file_exists($tmpPdfPath) || filesize($tmpPdfPath) === 0) {
                    Log::error('PDF file not found for document submission', [
                        'document_id' => $document->id,
                        'url' => $url,
                        'tmp_pdf_path' => $tmpPdfPath,
                        'file_exists' => $tmpPdfPath ? file_exists($tmpPdfPath) : false
                    ]);
                    return redirect()->back()->with('error', 'Document file not found. Please contact support.');
                }
                
                $outputTmpPath = storage_path('app/tmp_' . uniqid() . '_signed.pdf');
                
                // S3 prefix: admins.client_id for client docs; myfile URL or user_id for ad-hoc docs
                $clientId = SignedDocumentS3PathResolver::resolvePrefix($document);
                $docType = SignedDocumentS3PathResolver::resolveDocType($document);

                // Process signatures
                $signaturePositions = [];
                $signatureLinks = [];
                $signaturesSaved = false;

                foreach ($request->signatures as $page => $signaturesJson) {
                    $pageNum = (int) $page;
                    if ($pageNum < 1 || $pageNum > 999 || !$signaturesJson) {
                        continue;
                    }

                    $signatures = json_decode($signaturesJson, true);
                    $positions = json_decode($request->signature_positions[$page] ?? '{}', true);

                    if (!is_array($signatures) || !is_array($positions)) {
                        continue;
                    }

                    foreach ($signatures as $fieldId => $signatureData) {
                        $sanitizedFieldId = (int) $fieldId;
                        if ($sanitizedFieldId <= 0) continue;

                        // Validate and decode signature
                        $sanitizedSignature = $this->sanitizeSignatureData($signatureData, $sanitizedFieldId);
                        if ($sanitizedSignature === false) continue;

                        $imageData = $sanitizedSignature['imageData'];

                        // Store signature (try S3 first, fallback to local storage)
                        $filename = sprintf('%d_field_%d_%s.png', $signer->id, $sanitizedFieldId, bin2hex(random_bytes(8)));
                        $signaturePath = null;
                        $signatureUrl = null;
                        
                        if ($clientId && $docType) {
                            // Upload to S3 if we have the necessary info
                            try {
                                $s3SignaturePath = $clientId . '/' . $docType . '/signatures/' . $filename;
                                /** @var \Illuminate\Filesystem\FilesystemAdapter $s3Disk */
                                $s3Disk = Storage::disk('s3');
                                $s3Disk->put($s3SignaturePath, $imageData);
                                $signaturePath = $s3SignaturePath;
                                $signatureUrl = $s3Disk->url($s3SignaturePath);
                                Log::info('Signature uploaded to S3', ['path' => $s3SignaturePath]);
                            } catch (\Exception $e) {
                                Log::warning('Failed to upload signature to S3, using local storage', ['error' => $e->getMessage()]);
                                $signaturePath = null;
                            }
                        }
                        
                        // Fallback to local storage
                        if (!$signaturePath) {
                            $localSignaturePath = 'signatures/' . $filename;
                            Storage::disk('public')->put($localSignaturePath, $imageData);
                            $signaturePath = storage_path('app/public/' . $localSignaturePath);
                            $signatureUrl = asset('storage/' . $localSignaturePath);
                            Log::info('Signature saved locally', ['path' => $signaturePath]);
                        }

                        // Store position
                        $position = $positions[$fieldId] ?? [];
                        $sanitizedPosition = $this->sanitizePositionData($position);

                        $signaturePositions[$sanitizedFieldId] = [
                            'path' => $signaturePath,
                            'page' => $pageNum,
                            'x_percent' => $sanitizedPosition['x_percent'],
                            'y_percent' => $sanitizedPosition['y_percent'],
                            'w_percent' => $sanitizedPosition['w_percent'],
                            'h_percent' => $sanitizedPosition['h_percent']
                        ];
                        $signatureLinks[$sanitizedFieldId] = $signatureUrl;
                        $signaturesSaved = true;
                    }
                }

                if (!$signaturesSaved) {
                    // ✅ FIX #4: Return to signing page with error
                    Log::error('No valid signatures saved', [
                        'document_id' => $document->id,
                        'signer_id' => $signer->id
                    ]);
                    return redirect()->back()
                        ->with('error', 'No valid signatures were detected. Please ensure all signature fields are properly signed.')
                        ->withInput();
                }

                // ✅ PYTHON SERVICE: Try Python service first (supports ALL PDF compressions)
                $pythonService = app(\App\Services\PythonService::class);
                
                if ($pythonService->isHealthy()) {
                    Log::info('Attempting to create signed PDF with Python service');
                    
                    // Prepare signatures in Python service format
                    $signaturesForPython = [];
                    foreach ($signaturePositions as $fieldId => $signatureInfo) {
                        // Get the signature file path (handle both S3 and local)
                        $signaturePath = $signatureInfo['path'];
                        $tmpSignaturePath = null;
                        
                        if (file_exists($signaturePath)) {
                            // Local file
                            $tmpSignaturePath = $signaturePath;
                        } else {
                            // Try S3
                            try {
                                $tmpSignaturePath = storage_path('app/tmp_signature_' . uniqid() . '.png');
                                $s3Image = Storage::disk('s3')->get($signaturePath);
                                file_put_contents($tmpSignaturePath, $s3Image);
                            } catch (\Exception $e) {
                                Log::warning('Failed to get signature file from S3', ['path' => $signaturePath, 'error' => $e->getMessage()]);
                                continue; // Skip this signature
                            }
                        }
                        
                        if ($tmpSignaturePath && file_exists($tmpSignaturePath)) {
                            // Read signature as base64
                            $signatureBase64 = base64_encode(file_get_contents($tmpSignaturePath));
                            
                            $signaturesForPython[] = [
                                'field_id' => $fieldId,
                                'page_number' => $signatureInfo['page'],
                                'x_percent' => $signatureInfo['x_percent'],
                                'y_percent' => $signatureInfo['y_percent'],
                                'width_percent' => $signatureInfo['w_percent'],
                                'height_percent' => $signatureInfo['h_percent'],
                                'signature_data' => $signatureBase64
                            ];
                            
                            // Clean up temp S3 file
                            if (strpos($tmpSignaturePath, 'tmp_signature_') !== false) {
                                @unlink($tmpSignaturePath);
                            }
                        }
                    }
                    
                    // Call Python service to add signatures
                    $pythonSuccess = $pythonService->addSignaturesToPdf(
                        $tmpPdfPath,
                        $outputTmpPath,
                        $signaturesForPython
                    );
                    
                    if ($pythonSuccess && file_exists($outputTmpPath) && filesize($outputTmpPath) > 0) {
                        Log::info('Signed PDF created successfully with Python service', [
                            'document_id' => $document->id,
                            'output_size' => filesize($outputTmpPath)
                        ]);
                    } else {
                        Log::error('Python service failed to create signed PDF', [
                            'file_exists' => file_exists($outputTmpPath),
                            'file_size' => file_exists($outputTmpPath) ? filesize($outputTmpPath) : 0
                        ]);
                        
                        return redirect()->back()
                            ->with('error', 'Signature processing service is currently unavailable. Please try again later or contact support if the issue persists.')
                            ->withInput();
                    }
                } else {
                    Log::error('Python PDF service not available');
                    
                    return redirect()->back()
                        ->with('error', 'Signature processing service is currently unavailable. Please try again later or contact support if the issue persists.')
                        ->withInput();
                }

                // Upload signed PDF (try S3 first, fallback to local)
                $signedPdfUrl = null;
                $signedPdfPath = null;
                
                if ($clientId && $docType) {
                    try {
                        $s3SignedPath = $clientId . '/' . $docType . '/signed/' . $document->id . '_signed.pdf';
                        /** @var \Illuminate\Filesystem\FilesystemAdapter $s3Disk */
                        $s3Disk = Storage::disk('s3');
                        $s3Disk->put($s3SignedPath, fopen($outputTmpPath, 'r'));
                        $signedPdfUrl = $s3Disk->url($s3SignedPath);
                        $signedPdfPath = $s3SignedPath;
                        Log::info('Signed PDF uploaded to S3', ['path' => $s3SignedPath]);
                    } catch (\Exception $e) {
                        Log::warning('Failed to upload signed PDF to S3, using local storage', ['error' => $e->getMessage()]);
                        $signedPdfUrl = null;
                    }
                }
                
                // Fallback to local storage
                if (!$signedPdfUrl) {
                    $localSignedPath = 'signed/' . $document->id . '_signed.pdf';
                    Storage::disk('public')->put($localSignedPath, fopen($outputTmpPath, 'r'));
                    $signedPdfUrl = asset('storage/' . $localSignedPath);
                    $signedPdfPath = storage_path('app/public/' . $localSignedPath);
                    Log::info('Signed PDF saved locally', ['path' => $signedPdfPath]);
                }

                // Update statuses
                $signer->update(['status' => 'signed', 'signed_at' => now()]);
                $document->status = 'signed';
                $document->signature_doc_link = json_encode($signatureLinks);
                $document->signed_doc_link = $signedPdfUrl;
                $document->save();

                Log::info("Public document signed successfully", [
                    'document_id' => $document->id,
                    'signer_id' => $signer->id,
                    'signed_at' => now()->toISOString()
                ]);

                if ($document->client_id) {
                    $docName = $document->checklist ?? $document->file_name ?? 'Document';
                    \App\Models\ActivitiesLog::create([
                        'client_id' => $document->client_id,
                        'created_by' => $document->user_id,
                        'subject' => 'client signed document',
                        'description' => '<ul><li><strong>Document:</strong> ' . htmlspecialchars($docName) . '</li><li><strong>Signed by:</strong> ' . htmlspecialchars($signer->name . ' (' . $signer->email . ')') . '</li></ul>',
                        'activity_type' => 'signature',
                        'task_status' => 0,
                        'pin' => 0,
                    ]);
                }

                // For visa/nomination documents: create {checklist}_signed row in same category (if not already exists).
                // Refresh so folder_name / checklist / matter match DB after save (avoid stale model skipping this block).
                $document->refresh();

                $docTypeNorm = strtolower(trim((string) ($document->doc_type ?? '')));
                $folderGuard = trim((string) ($document->folder_name ?? ''));
                $baseChecklist = trim((string) str_replace("\0", '', (string) ($document->checklist ?? '')));
                if ($baseChecklist === '') {
                    $baseChecklist = 'Document';
                }
                $checklistAlreadySignedSuffix = str_ends_with($baseChecklist, '_signed');
                $isVisaOrNominationDoc = in_array($docTypeNorm, ['visa', 'nomination'], true);

                $shouldCreateSignedCopy = $isVisaOrNominationDoc
                    && (bool) $document->client_id
                    && $folderGuard !== ''
                    && !$checklistAlreadySignedSuffix;

                if ($isVisaOrNominationDoc && !$shouldCreateSignedCopy) {
                    Log::info('Skipping _signed document row', [
                        'document_id' => $document->id,
                        'doc_type' => $document->doc_type,
                        'has_client_id' => (bool) $document->client_id,
                        'folder_name_raw' => $document->folder_name,
                        'folder_empty_after_trim' => $folderGuard === '',
                        'checklist' => $document->checklist,
                        'checklist_ends_with__signed' => $checklistAlreadySignedSuffix,
                    ]);
                }

                if ($shouldCreateSignedCopy) {
                    $signedChecklist = $baseChecklist . '_signed';
                    $exists = Document::where('client_id', '=', $document->client_id, 'and')
                        ->where('folder_name', '=', $document->folder_name, 'and')
                        ->where('client_matter_id', '=', $document->client_matter_id, 'and')
                        ->where('checklist', '=', $signedChecklist, 'and')
                        ->whereNull('not_used_doc')
                        ->exists();

                    if ($exists) {
                        Log::info('Signed companion row already present (active)', [
                            'document_id' => $document->id,
                            'doc_type' => $docTypeNorm,
                            'signed_checklist' => $signedChecklist,
                            'folder_name' => $document->folder_name,
                            'client_matter_id' => $document->client_matter_id,
                        ]);
                    }

                    if (!$exists) {
                        try {
                            $parentKey = (string) ($document->myfile_key ?? '');
                            $parentStem = preg_replace('/\.(pdf|PDF)$/', '', $document->file_name ?? 'document');
                            if ($parentKey !== '' && !preg_match('/^\d+_signed\.pdf$/i', $parentKey)) {
                                $signedKey = pathinfo($parentKey, PATHINFO_FILENAME) . '_signed.pdf';
                            } else {
                                $signedKey = $parentStem . '_signed.pdf';
                            }
                            $signedKey = preg_replace('/[^a-zA-Z0-9_\-\.\s\$]+/', '_', $signedKey);
                            $signedKey = trim($signedKey);
                            if ($signedKey === '' || strcasecmp($signedKey, '_signed.pdf') === 0 || strlen($signedKey) > 200) {
                                $signedKey = mb_substr(preg_replace('/[^a-zA-Z0-9_\-\.\s\$]+/', '_', $parentStem . '_signed.pdf'), 0, 200);
                            }
                            if ($signedKey === '' || strcasecmp($signedKey, '_signed.pdf') === 0) {
                                $signedKey = $document->id . '_signed.pdf';
                            }
                            $signedDoc = new Document();
                            $signedDoc->checklist = $signedChecklist;
                            $signedDoc->file_name = preg_replace('/\.(pdf|PDF)$/', '', $document->file_name ?? 'document') . '_signed';
                            $signedDoc->filetype = 'pdf';
                            $signedDoc->myfile = $signedPdfUrl;
                            $signedDoc->myfile_key = $signedKey;
                            $signedDoc->client_id = $document->client_id;
                            $signedDoc->client_matter_id = $document->client_matter_id;
                            $signedDoc->folder_name = (string) $document->folder_name;
                            // Keep companion in the same tab as the parent (visa vs nomination).
                            $signedDoc->doc_type = $docTypeNorm;
                            $signedDoc->type = 'client';
                            $signedDoc->user_id = $document->user_id;
                            $signedDoc->status = 'signed';
                            $signedDoc->signed_doc_link = $signedPdfUrl;
                            $signedDoc->save();
                            Log::info('Created _signed companion row', [
                                'original_id' => $document->id,
                                'signed_id' => $signedDoc->id,
                                'doc_type' => $docTypeNorm,
                            ]);
                        } catch (\Exception $e) {
                            Log::error('Failed to create _signed companion row', [
                                'document_id' => $document->id,
                                'doc_type' => $docTypeNorm,
                                'signed_checklist' => $signedChecklist ?? null,
                                'error' => $e->getMessage(),
                                'trace' => $e->getTraceAsString(),
                            ]);
                        }
                    }
                }

                // Advance workflow to next stage when document is tied to a checklist/client matter
                if ($document->client_matter_id && $document->client_id) {
                    try {
                        ClientMatter::where('id', '=', $document->client_matter_id, 'and')->update(['updated_at_type' => 'signed', 'updated_at' => now()]);

                        // Agreement-specific activity log (Cost Agreement)
                        if ($document->doc_type === 'agreement') {
                            $clientMatterInfo = ClientMatter::select(['client_unique_matter_no', 'sel_person_responsible'])
                                ->where('id', '=', $document->client_matter_id)->first();
                            if ($clientMatterInfo && $clientMatterInfo->sel_person_responsible) {
                                $docSignerInfo = \App\Models\Admin::select(['first_name', 'last_name', 'client_id'])
                                    ->where('id', '=', $document->client_id)->first();
                                $docSignerFullName = $docSignerInfo ? trim(($docSignerInfo->first_name ?? '') . ' ' . ($docSignerInfo->last_name ?? '')) : 'NA';
                                $docSignerClientId = $docSignerInfo?->client_id ?? 'NA';
                                $clientMatterReference = $docSignerClientId . '-' . ($clientMatterInfo->client_unique_matter_no ?? '');
                                $signedDocName = $document->getFilenameWithExtensionForDisplay();
                                $subject = $docSignerFullName . ' signed cost agreement for matter ref no - ' . $clientMatterReference . ' at document ' . $signedDocName;
                                ActivitiesLog::create([
                                    'client_id' => $document->client_id,
                                    'created_by' => $clientMatterInfo->sel_person_responsible,
                                    'description' => '',
                                    'subject' => $subject,
                                    'activity_type' => 'document',
                                    'task_status' => 0,
                                    'pin' => 0,
                                ]);
                            }
                        }

                        // Advance workflow stage to next in sequence (when next stage doesn't require manual input)
                        $clientMatter = ClientMatter::find($document->client_matter_id, ['*']);
                        if ($clientMatter && $clientMatter->workflow_stage_id) {
                            $currentStage = WorkflowStage::find($clientMatter->workflow_stage_id, ['*']);
                            if ($currentStage) {
                                $currentOrder = $currentStage->sort_order ?? $currentStage->id;
                                $stageQuery = WorkflowStage::whereRaw('COALESCE(sort_order, id) > ?', [$currentOrder], 'and');
                                $workflowId = $clientMatter->workflow_id ?? $currentStage->workflow_id;
                                if ($workflowId) {
                                    $stageQuery->where('workflow_id', '=', $workflowId);
                                }
                                $nextStage = $stageQuery->orderByRaw('COALESCE(sort_order, id) ASC')->first();

                                // Only auto-advance if next stage is NOT "Decision Received" (requires outcome/note)
                                if ($nextStage && strtolower(trim($nextStage->name ?? '')) !== 'decision received') {
                                    $clientMatter->workflow_stage_id = $nextStage->id;
                                    $clientMatter->save();

                                    // applications table removed - workflow tracked via client_matters

                                    Log::info('Workflow advanced on document sign', [
                                        'document_id' => $document->id,
                                        'client_matter_id' => $document->client_matter_id,
                                        'from_stage' => $currentStage->name,
                                        'to_stage' => $nextStage->name,
                                    ]);
                                }
                            }
                        }
                    } catch (\Exception $e) {
                        Log::warning('Workflow advancement on sign failed', [
                            'document_id' => $document->id,
                            'client_matter_id' => $document->client_matter_id ?? null,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                // Create notifications and activity logs
                $this->createSignatureNotifications($document, $signer);

                // Redirect to thank you page with success message
                return redirect()->route('public.documents.thankyou', ['id' => $document->id])
                    ->with('success', 'Document signed successfully! You can now download your signed document.');
                } finally {
                    TempFileHelper::deleteMany([$tmpPdfPath, $outputTmpPath]);
                }
            }

            // If we reach here, something unexpected happened
            Log::error('Unexpected state in submitSignatures', [
                'document_id' => $document->id,
                'signer_id' => $signer->id,
                'signer_status' => $signer->status,
                'signer_token_set' => $signer->token !== null
            ]);
            // ✅ FIX #4: Return to signing page with error
            return redirect()->back()
                ->with('error', 'An unexpected error occurred while processing your signature. Please try again or contact support if the issue persists.');
        } catch (\Exception $e) {
            Log::error("Error in public submitSignatures", [
                'error' => $e->getMessage(),
                'document_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);
            // ✅ FIX #4: Return to signing page with error
            return redirect()->back()
                ->with('error', 'An unexpected error occurred while processing your signature. Please try again or contact support.');
        }
    }

    /**
     * Get a specific page of the PDF as an image
     * 
     * @param int $id Document ID
     * @param int $page Page number
     * @return \Illuminate\Http\Response
     */
    public function getPage($id, $page)
    {
        // Clear any existing output buffers
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        $tmpPdfPath = null;

        try {
            $document = Document::findOrFail($id);

            $url = $document->myfile;
            $pdfPath = null;
            $isLocalFile = false;

            // Check if URL is a full S3 URL or local path
            if ($url && filter_var($url, FILTER_VALIDATE_URL) && strpos($url, 's3') !== false) {
                // This is an S3 URL - extract the key
                $parsed = parse_url($url);
                if (isset($parsed['path'])) {
                    $pdfPath = ltrim(urldecode($parsed['path']), '/');
                }
                
                if (!$pdfPath || !Storage::disk('s3')->exists($pdfPath)) {
                    Log::error('PDF file not found in S3 for document: ' . $id, [
                        'document_id' => $id,
                        's3Key' => $pdfPath,
                        'myfile' => $url,
                        's3_exists' => $pdfPath ? Storage::disk('s3')->exists($pdfPath) : 'no_path'
                    ]);
                    abort(404, 'Document file not found');
                }

                // Download PDF from S3 to a temp file
                $tmpPdfPath = storage_path('app/tmp_' . uniqid() . '.pdf');
                $pdfStream = Storage::disk('s3')->get($pdfPath);
                file_put_contents($tmpPdfPath, $pdfStream);
                Log::info('Downloaded S3 file for document page', ['s3Key' => $pdfPath, 'tempPath' => $tmpPdfPath]);
            } elseif ($url && file_exists(storage_path('app/public/' . $url))) {
                // This is a local file path and file exists
                $tmpPdfPath = storage_path('app/public/' . $url);
                $isLocalFile = true;
                Log::info('Using local file for document page', ['path' => $tmpPdfPath]);
            } else {
                // Try to build S3 key from DB fields as fallback
                if (!empty($document->myfile_key) && !empty($document->doc_type) && !empty($document->client_id)) {
                    $admin = DB::table('admins')->select('client_id')->where('id', '=', $document->client_id)->first();
                    if ($admin && $admin->client_id) {
                        $pdfPath = $admin->client_id . '/' . $document->doc_type . '/' . $document->myfile_key;
                        
                        if (Storage::disk('s3')->exists($pdfPath)) {
                            // Download PDF from S3 to a temp file
                            $tmpPdfPath = storage_path('app/tmp_' . uniqid() . '.pdf');
                            $pdfStream = Storage::disk('s3')->get($pdfPath);
                            file_put_contents($tmpPdfPath, $pdfStream);
                            Log::info('Downloaded S3 file via fallback for document page', ['s3Key' => $pdfPath, 'tempPath' => $tmpPdfPath]);
                        } else {
                            Log::error('PDF file not found in S3 fallback for document: ' . $id, [
                                'document_id' => $id,
                                's3Key' => $pdfPath,
                                'myfile' => $url,
                                'myfile_key' => $document->myfile_key,
                                'doc_type' => $document->doc_type,
                                'client_id' => $document->client_id,
                                'local_exists' => $url ? file_exists(storage_path('app/public/' . $url)) : false
                            ]);
                            abort(404, 'Document file not found');
                        }
                    } else {
                        Log::error('Admin not found for document: ' . $id, [
                            'document_id' => $id,
                            'client_id' => $document->client_id
                        ]);
                        abort(404, 'Document file not found');
                    }
                } else {
                    Log::error('PDF file not found for document: ' . $id, [
                        'document_id' => $id,
                        'myfile' => $url,
                        'myfile_key' => $document->myfile_key,
                        'doc_type' => $document->doc_type,
                        'client_id' => $document->client_id,
                        'local_exists' => $url ? file_exists(storage_path('app/public/' . $url)) : false
                    ]);
                    abort(404, 'Document file not found');
                }
            }

            // Ensure we have a valid PDF path
            if (!$tmpPdfPath || !file_exists($tmpPdfPath)) {
                Log::error('No valid PDF path found for document: ' . $id, [
                    'document_id' => $id,
                    'tmpPdfPath' => $tmpPdfPath,
                    'file_exists' => $tmpPdfPath ? file_exists($tmpPdfPath) : false
                ]);
                abort(404, 'Document file not found');
            }

            try {
                // Try Python Service first
                $pythonService = app(\App\Services\PythonService::class);
                
                if ($pythonService->isHealthy()) {
                    $result = $pythonService->convertPageToImage($tmpPdfPath, (int)$page, 72);
                    
                    if ($result && $result['success']) {
                        $imageData = $result['image_data'];
                        
                        // Remove data URI prefix if present
                        if (strpos($imageData, 'data:image/png;base64,') === 0) {
                            $imageData = substr($imageData, strlen('data:image/png;base64,'));
                        }
                        
                        // Decode base64 to image bytes and stream directly (no disk cache)
                        $imageBytes = base64_decode($imageData);

                        if ($imageBytes === false || $imageBytes === '') {
                            Log::error('Failed to decode page image', [
                                'document_id' => $id,
                                'page' => $page,
                            ]);
                        } else {
                            Log::info('Page image generated using Python service', [
                                'document_id' => $id,
                                'page' => $page,
                            ]);

                            return response($imageBytes, 200, [
                                'Content-Type' => 'image/png',
                                'Content-Length' => strlen($imageBytes),
                                'Cache-Control' => 'public, max-age=86400',
                            ]);
                        }
                    }
                }

                // Python service failed or unavailable
                Log::error('Python PDF service unavailable for page conversion', [
                    'document_id' => $id,
                    'page' => $page
                ]);

                abort(503, 'PDF processing service unavailable. Please try again later.');
            } catch (\Exception $e) {
                Log::error('Error generating PDF page image', [
                    'document_id' => $id,
                    'page' => $page,
                    'error' => $e->getMessage()
                ]);
                abort(500, 'Error generating page image');
            }
        } catch (\Exception $e) {
            Log::error('Error in getPage', [
                'document_id' => $id,
                'page' => $page,
                'error' => $e->getMessage()
            ]);
            abort(500, 'An error occurred while retrieving the page');
        } finally {
            TempFileHelper::delete($tmpPdfPath);
        }
    }

    /**
     * Download signed document
     * 
     * @param int $id Document ID
     * @return \Illuminate\Http\RedirectResponse
     */
    public function downloadSigned($id)
    {
        try {
            $document = Document::findOrFail($id);

            if ($document->signed_doc_link) {
                $signedDocUrl = $document->signed_doc_link;
                $location = SignedDocumentS3PathResolver::locateSignedPdfFile($document);

                if ($location !== null) {
                    if ($location['disk'] === 'local') {
                        return response()->download(
                            $location['path'],
                            $document->getSignedDownloadFilename()
                        );
                    }

                    /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
                    $disk = Storage::disk('s3');
                    $dlName = str_replace('"', "'", $document->getSignedDownloadFilename());

                    try {
                        return redirect($disk->temporaryUrl(
                            $location['key'],
                            now()->addMinutes(5),
                            ['ResponseContentDisposition' => 'attachment; filename="' . $dlName . '"']
                        ));
                    } catch (\Throwable $e) {
                        return response($disk->get($location['key']), 200, [
                            'Content-Type' => 'application/pdf',
                            'Content-Disposition' => 'attachment; filename="' . $dlName . '"',
                            'Content-Length' => $disk->size($location['key']),
                        ]);
                    }
                }

                return redirect($signedDocUrl);
            }

            return redirect('/')->with('error', 'Signed document not found.');
        } catch (\Exception $e) {
            Log::error('Error downloading signed document', [
                'document_id' => $id,
                'error' => $e->getMessage()
            ]);
            return redirect('/')->with('error', 'An error occurred while downloading the document.');
        }
    }

    /**
     * Download signed document and redirect to thank you page
     * 
     * @param int $id Document ID
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse
     */
    public function downloadSignedAndThankyou($id)
    {
        try {
            $document = Document::findOrFail($id);
            
            if ($document->signed_doc_link) {
                $signedDocUrl = $document->signed_doc_link;
                $downloadUrl = null;
                
                // Parse the URL to get the path
                $parsed = parse_url($signedDocUrl);
                $urlPath = $parsed['path'] ?? '';
                
                // Check if it's a local storage path (contains /storage/)
                if (strpos($urlPath, '/storage/') !== false) {
                    // Extract the path after /storage/
                    $parts = explode('/storage/', $urlPath);
                    $relativePath = end($parts);
                    
                    // Check if file exists in local storage
                    if (Storage::disk('public')->exists($relativePath)) {
                        // Generate a temporary download route for local files
                        $downloadUrl = route('public.documents.download.signed', $document->id);
                    }
                } else {
                    // Try S3 storage
                    if (isset($parsed['path'])) {
                        $s3Key = ltrim($parsed['path'], '/');
                        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
                        $disk = Storage::disk('s3');
                        
                        if ($disk->exists($s3Key)) {
                            $dlName = $document->getSignedDownloadFilename();
                            $downloadUrl = $disk->temporaryUrl(
                                $s3Key,
                                now()->addMinutes(5),
                                ['ResponseContentDisposition' => 'attachment; filename="' . str_replace('"', "'", $dlName) . '"']
                            );
                        }
                    }
                }
                
                // If we found a valid download URL, show the download & thank you view
                if ($downloadUrl) {
                    return view('documents.download_and_thankyou', [
                        'downloadUrl' => $downloadUrl,
                        'thankyouUrl' => route('public.documents.thankyou', ['id' => $id])
                    ]);
                }
                
                return view('documents.download_and_thankyou', [
                    'downloadUrl' => $signedDocUrl,
                    'thankyouUrl' => route('public.documents.thankyou', ['id' => $id])
                ]);
            }
            
            return redirect('/')->with('error', 'Signed document not found.');
        } catch (\Exception $e) {
            Log::error('Error in downloadSignedAndThankyou', [
                'document_id' => $id,
                'error' => $e->getMessage()
            ]);
            return redirect('/')->with('error', 'An error occurred while preparing your download.');
        }
    }

    /**
     * Show thank you page after signing
     * 
     * @param Request $request
     * @param int|null $id Document ID
     * @return \Illuminate\View\View
     */
    public function thankyou(Request $request, $id = null)
    {
        $downloadUrl = null;
        $document = null;
        
        if ($id) {
            $document = Document::find($id, ['*']);
            if ($document && $document->signed_doc_link) {
                $parsed = parse_url($document->signed_doc_link);
                if (isset($parsed['path'])) {
                    $s3Key = ltrim($parsed['path'], '/');
                    /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
                    $disk = Storage::disk('s3');
                    if ($disk->exists($s3Key)) {
                        $dlName = $document->getSignedDownloadFilename();
                        $downloadUrl = $disk->temporaryUrl(
                            $s3Key,
                            now()->addMinutes(5),
                            ['ResponseContentDisposition' => 'attachment; filename="' . str_replace('"', "'", $dlName) . '"']
                        );
                    }
                }
            }
        }
        
        $message = 'You have successfully signed your document.';
        return view('thanks', compact('downloadUrl', 'message', 'id', 'document'));
    }

    /**
     * Send reminder to signer
     * 
     * @param Request $request
     * @param int $id Document ID
     * @return \Illuminate\Http\RedirectResponse
     */
    public function sendReminder(Request $request, $id)
    {
        $documentId = (int) $id;
        if ($documentId <= 0) {
            return redirect()->back()->with('error', 'Invalid document ID.');
        }

        $request->validate([
            'signer_id' => 'required|integer|exists:signers,id'
        ]);

        $signerId = (int) $request->signer_id;

        try {
            $document = Document::findOrFail($documentId);
            $signer = $document->signers()->findOrFail($signerId);

            if ($signer->status === 'signed') {
                return redirect()->back()->with('error', 'Document is already signed.');
            }

            if ($signer->reminder_count >= 3) {
                return redirect()->back()->with('error', 'Maximum reminders already sent.');
            }

            // Send reminder email
            $signingUrl = url("/sign/{$document->id}/{$signer->token}");
            Mail::raw("This is a reminder to sign your document: " . $signingUrl . "\n\nConsumer guide: https://www.mara.gov.au/get-help-visa-subsite/FIles/consumer_guide_english.pdf", function ($message) use ($signer) {
                $message->to($signer->email, $signer->name)
                        ->subject('Reminder: Please Sign Your Document');
            });

            $signer->update([
                'last_reminder_sent_at' => now(),
                'reminder_count' => $signer->reminder_count + 1
            ]);

            return redirect()->back()->with('success', 'Reminder sent successfully!');
        } catch (\Exception $e) {
            Log::error('Error sending reminder', [
                'document_id' => $documentId,
                'signer_id' => $signerId,
                'error' => $e->getMessage()
            ]);
            return redirect()->back()->with('error', 'An error occurred while sending the reminder.');
        }
    }

    /**
     * Show document index (optional)
     * 
     * @param int|null $id Document ID
     * @return \Illuminate\View\View
     */
    public function index($id = null)
    {
        // This is typically not used for public access
        // Redirect to home or show error
        return redirect('/')->with('info', 'Please use the link provided in your email.');
    }

    // ==================== Private Helper Methods ====================

    /**
     * Count PDF pages
     *
     * @return int|null
     */
    protected function countPdfPages(string $pathToPdf): ?int
    {
        try {
            $pythonService = app(\App\Services\PythonService::class);
            
            if (!$pythonService->isHealthy()) {
                Log::error('Python service unavailable for PDF page counting');
                return null;
            }
            
            $pdfInfo = $pythonService->getPdfInfo($pathToPdf);
            
            if ($pdfInfo && isset($pdfInfo['success']) && $pdfInfo['success'] === true && isset($pdfInfo['page_count'])) {
                return (int) $pdfInfo['page_count'];
            }
            
            Log::error('Python service failed to get PDF page count', ['pdf_info' => $pdfInfo]);
            return null;
        } catch (\Exception $e) {
            Log::error('Error counting PDF pages', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Sanitize signature data
     *
     * @return array{imageData: string, base64Data: string, size: int}|false
     */
    private function sanitizeSignatureData(mixed $signatureData, int $fieldId): array|false
    {
        if (!is_string($signatureData) || empty($signatureData)) {
            return false;
        }

        $signatureData = strip_tags($signatureData);

        $dangerousPatterns = [
            '/javascript:/i', '/vbscript:/i', '/data:text\/html/i',
            '/data:application\/javascript/i', '/onclick/i', '/onload/i',
            '/onerror/i', '/<script/i', '/<iframe/i'
        ];

        foreach ($dangerousPatterns as $pattern) {
            if (preg_match($pattern, $signatureData)) {
                return false;
            }
        }

        if (!preg_match('/^data:image\/png;base64,([A-Za-z0-9+\/=]+)$/', $signatureData, $matches)) {
            return false;
        }

        $base64Data = $matches[1];

        if (strlen($base64Data) > 500000) {
            return false;
        }

        $imageData = base64_decode($base64Data, true);
        if ($imageData === false || strlen($imageData) < 100 || strlen($imageData) > 1000000) {
            return false;
        }

        // Validate PNG signature
        $pngSignature = "\x89\x50\x4E\x47\x0D\x0A\x1A\x0A";
        if (substr($imageData, 0, 8) !== $pngSignature) {
            return false;
        }

        return [
            'imageData' => $imageData,
            'base64Data' => $base64Data,
            'size' => strlen($imageData)
        ];
    }

    /**
     * Sanitize position data
     *
     * @param  array<string, mixed>  $position
     * @return array<string, float>
     */
    private function sanitizePositionData($position): array
    {
        $sanitized = [];
        $fields = ['x_percent', 'y_percent', 'w_percent', 'h_percent'];

        foreach ($fields as $field) {
            $value = $position[$field] ?? 0;
            $value = (float) $value;
            $value = max(0, min(1, $value));
            if ($field === 'w_percent') $value = max(0.1, $value);
            if ($field === 'h_percent') $value = max(0.05, $value);
            $sanitized[$field] = $value;
        }

        return $sanitized;
    }

    /**
     * Create notifications and activity logs when document is signed
     *
     * @param  Document  $document
     * @param  Signer  $signer
     */
    private function createSignatureNotifications($document, $signer)
    {
        try {
            // Get signer information
            $signerName = $signer->name ?? 'Unknown Signer';
            $signedAt = now()->format('d/m/Y h:i A');
            
            // Determine the associated client/lead and responsible person
            $associatedEntity = null;
            $responsiblePersonId = null;
            $entityType = null;
            $notificationUrl = null;
            
            // Check if document is associated with a client matter
            if ($document->client_matter_id) {
                $clientMatter = \App\Models\ClientMatter::select(['client_unique_matter_no', 'sel_person_responsible', 'client_id'])
                    ->where('id', '=', $document->client_matter_id)
                    ->first();
                
                if ($clientMatter) {
                    $associatedEntity = \App\Models\Admin::find($clientMatter->client_id, ['*']);
                    $responsiblePersonId = $clientMatter->sel_person_responsible;
                    $entityType = 'client';
                    $notificationUrl = url("/clients/detail/{$clientMatter->client_id}");
                    
                    if ($associatedEntity) {
                        $this->createActivityAndNotification(
                            $document,
                            $associatedEntity,
                            $responsiblePersonId,
                            $signerName,
                            $signedAt,
                            $entityType,
                            $notificationUrl,
                            $clientMatter->client_unique_matter_no
                        );
                    }
                }
            }
            // Check if document has client_id (direct client association)
            elseif ($document->client_id) {
                $associatedEntity = \App\Models\Admin::find($document->client_id, ['*']);
                
                if ($associatedEntity) {
                    $entityType = ($associatedEntity->type === 'lead') ? 'lead' : 'client';
                    $notificationUrl = url("/clients/detail/{$associatedEntity->id}");
                    
                    // For direct client associations, try to find responsible person
                    // You can set this to a default admin or get from document creator
                    $responsiblePersonId = $document->created_by ?? null;
                    
                    $this->createActivityAndNotification(
                        $document,
                        $associatedEntity,
                        $responsiblePersonId,
                        $signerName,
                        $signedAt,
                        $entityType,
                        $notificationUrl
                    );
                }
            }
            // Check if document has client_id or lead_id
            elseif ($document->client_id || $document->lead_id) {
                if ($document->client_id) {
                    $associatedEntity = \App\Models\Admin::find($document->client_id, ['*']);
                    
                    if ($associatedEntity) {
                        $entityType = ($associatedEntity->type === 'lead') ? 'lead' : 'client';
                        $notificationUrl = url("/clients/detail/{$associatedEntity->id}");
                        $responsiblePersonId = $document->created_by ?? null;
                        $matterRef = null;
                        
                        if ($document->client_matter_id) {
                            $clientMatter = \App\Models\ClientMatter::select(['client_unique_matter_no', 'sel_person_responsible'])
                                ->where('id', '=', $document->client_matter_id)
                                ->first();
                            $matterRef = $clientMatter?->client_unique_matter_no;
                            $responsiblePersonId = $clientMatter?->sel_person_responsible ?? $responsiblePersonId;
                        }
                        
                        $this->createActivityAndNotification(
                            $document,
                            $associatedEntity,
                            $responsiblePersonId,
                            $signerName,
                            $signedAt,
                            $entityType,
                            $notificationUrl,
                            $matterRef
                        );
                    }
                } elseif ($document->lead_id) {
                    $associatedEntity = \App\Models\Admin::find($document->lead_id, ['*']);
                    
                    if ($associatedEntity) {
                        $entityType = 'lead';
                        $notificationUrl = url("/leads/detail/{$associatedEntity->id}");
                        $responsiblePersonId = $document->created_by ?? null;
                        
                        $this->createActivityAndNotification(
                            $document,
                            $associatedEntity,
                            $responsiblePersonId,
                            $signerName,
                            $signedAt,
                            $entityType,
                            $notificationUrl
                        );
                    }
                }
            }
            
            Log::info('Document signature notifications processed', [
                'document_id' => $document->id,
                'entity_type' => $entityType,
                'has_entity' => !is_null($associatedEntity),
                'has_responsible_person' => !is_null($responsiblePersonId)
            ]);
            
        } catch (\Exception $e) {
            // Log the error but don't break the signing flow
            Log::error('Error creating signature notifications', [
                'document_id' => $document->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
    
    /**
     * Helper method to create activity log and notification
     *
     * @param  Document  $document
     * @param  \App\Models\Admin  $entity
     * @param  int|string|null  $responsiblePersonId
     * @param  string  $signerName
     * @param  string  $signedAt
     * @param  string  $entityType  'client' or 'lead'
     * @param  string  $notificationUrl
     * @param  string|null  $matterReference
     */
    private function createActivityAndNotification(
        $document, 
        $entity, 
        $responsiblePersonId, 
        $signerName, 
        $signedAt, 
        $entityType,
        $notificationUrl,
        $matterReference = null
    ) {
        $entityName = $entity->first_name . ' ' . $entity->last_name;
        $documentTitle = $document->file_name ?? 'Document #' . $document->id;
        
        // Create subject line
        if ($matterReference) {
            $subject = "{$signerName} signed document '{$documentTitle}' for matter {$matterReference}";
        } else {
            $subject = "{$signerName} signed document '{$documentTitle}' for {$entityType} {$entityName}";
        }
        
        $description = "Document signed by {$signerName} at {$signedAt}";
        
        // Create Activity Log (appears on client/lead timeline)
        \App\Models\ActivitiesLog::create([
            'client_id' => $entity->id,
            'created_by' => $responsiblePersonId ?? 1, // Default to system user if no responsible person
            'subject' => $subject,
            'description' => $description,
            'activity_type' => 'document',
            'task_status' => 0,
            'pin' => 0,
        ]);
        
        Log::info('Activity log created for document signature', [
            'document_id' => $document->id,
            'client_id' => $entity->id,
            'entity_type' => $entityType
        ]);
        
        // Create Notification (appears in notification tab)
        if ($responsiblePersonId) {
            \App\Models\Notification::create([
                'sender_id' => $entity->id, // The client/lead is the "sender"
                'receiver_id' => $responsiblePersonId, // The responsible person receives it
                'module_id' => $document->id,
                'url' => $notificationUrl,
                'notification_type' => 'document',
                'message' => $subject,
                'receiver_status' => 0, // Unread
                'seen' => 0,
            ]);
            
            Log::info('Notification created for document signature', [
                'document_id' => $document->id,
                'receiver_id' => $responsiblePersonId,
                'entity_type' => $entityType
            ]);
        } else {
            Log::warning('No responsible person found for notification', [
                'document_id' => $document->id,
                'entity_id' => $entity->id
            ]);
        }
    }

    /**
     * Show cancelled signature error page
     * 
     * @param Document|null $document
     * @return \Illuminate\View\View
     */
    private function showCancelledError($document = null)
    {
        return $this->showErrorPage(
            'Signature Cancelled',
            'This signing link has been cancelled. Please contact the document sender for assistance.',
            $document
        );
    }

    /**
     * Show error page for public document access
     * 
     * @param string $title
     * @param string $message
     * @param Document|null $document
     * @return \Illuminate\View\View
     */
    private function showErrorPage($title, $message, $document = null)
    {
        // Use 200 status to avoid any middleware redirects to login page
        // The error is shown in the page content, not via HTTP status
        return response()->view('documents.error', [
            'title' => $title,
            'message' => $message,
            'document' => $document
        ], 200);
    }
}

