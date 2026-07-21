<?php
namespace App\Http\Controllers\CRM;
use App\Http\Controllers\Concerns\EnsuresCrmRecordAccess;
use App\Http\Controllers\Controller;

use App\Models\Document;
use App\Models\Signer;
use Illuminate\Http\Request;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Models\ActivitiesLog;
use App\Models\ClientMatter;
use App\Models\Admin;
use Illuminate\Support\Facades\Auth;
use App\Models\UploadChecklist;
use App\Models\Email;
use App\Models\EmailLog;
use App\Helpers\TempFileHelper;
use App\Services\PythonService;
use App\Services\SignedDocumentS3PathResolver;

class DocumentController extends Controller
{
    use EnsuresCrmRecordAccess;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth:admin');
    }

    protected function authorizeDocumentAssociatedAccess(Document $document): void
    {
        // For documents linked to a client or lead, enforce access.
        // ensureCrmRecordAccess silently skips rows where the stored ID is not a
        // client/lead (e.g. ad-hoc docs where client_id holds the uploading staff's id).
        if ($document->lead_id) {
            $this->ensureCrmRecordAccessStrict((int) $document->lead_id);
        } elseif ($document->client_id) {
            $this->ensureCrmRecordAccess((int) $document->client_id);
        }
    }
    /**
     * Standard error handling for controller methods
     *
     * @param \Exception $e The exception that occurred
     * @param string $context Context of where the error occurred
     * @param string $userMessage User-friendly error message
     * @param string $redirectRoute Route to redirect to (default: back)
     * @param array $additionalContext Additional context for logging
     * @return \Illuminate\Http\RedirectResponse
     */
    private function handleError(\Exception $e, $context, $userMessage = 'An error occurred', $redirectRoute = 'back', $additionalContext = [])
    {
        // Log the error with context
        $logContext = array_merge([
            'context' => $context,
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'user_id' => auth('admin')->id(),
            'url' => request()->url(),
            'ip' => request()->ip()
        ], $additionalContext);

        Log::error("Controller error in {$context}", $logContext);

        // Include actual error message for debugging
        $errorMessage = $userMessage;
        if (config('app.debug', false)) {
            $errorMessage .= ' Error: ' . $e->getMessage() . ' (File: ' . basename($e->getFile()) . ':' . $e->getLine() . ')';
        } else {
            // Even in production, show a more descriptive error
            $errorMessage .= ' Error details: ' . $e->getMessage();
        }

        // Return appropriate redirect
        if ($redirectRoute === 'back') {
            return redirect()->back()->with('error', $errorMessage);
        } else {
            return redirect()->route($redirectRoute)->with('error', $errorMessage);
        }
    }

    /**
     * Handle validation errors consistently
     *
     * @param array $errors Array of validation errors
     * @param string $context Context where validation failed
     * @return \Illuminate\Http\RedirectResponse
     */
    private function handleValidationError($errors, $context = 'validation')
    {
        Log::warning("Validation failed in {$context}", [
            'errors' => $errors,
            'user_id' => auth('admin')->id(),
            'input' => request()->except(['password', 'password_confirmation', '_token'])
        ]);

        return redirect()->back()
            ->withErrors($errors)
            ->withInput();
    }

    /**
     * General purpose input sanitization for string data
     *
     * @param string $input The input string to sanitize
     * @param bool $allowHtml Whether to allow HTML tags (default: false)
     * @return string Sanitized string
     */
    private function sanitizeStringInput($input, $allowHtml = false)
    {
        if (!is_string($input)) {
            return '';
        }

        // Trim whitespace
        $input = trim($input);

        // Remove null bytes to prevent null byte injection
        $input = str_replace(chr(0), '', $input);

        // Remove or escape HTML based on requirements
        if (!$allowHtml) {
            $input = strip_tags($input);
            $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
        }

        // Remove potentially dangerous characters and patterns
        $dangerousPatterns = [
            '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', // Control characters
            '/javascript:/i',
            '/vbscript:/i',
            '/data:/i',
            '/onclick/i',
            '/onload/i',
            '/onerror/i'
        ];

        foreach ($dangerousPatterns as $pattern) {
            $input = preg_replace($pattern, '', $input);
        }

        return $input;
    }

    /**
     * Sanitize and validate base64 signature data to prevent XSS and code injection
     *
     * @param string $signatureData The raw signature data from client
     * @param int $fieldId The field ID for logging
     * @return array|false Returns sanitized data array or false if invalid
     */
    private function sanitizeSignatureData($signatureData, $fieldId)
    {
        // Check if signature data is string and not empty
        if (!is_string($signatureData) || empty($signatureData)) {
            Log::warning('Empty or non-string signature data', ['fieldId' => $fieldId]);
            return false;
        }

        // Prevent XSS by removing any potential script tags or HTML
        $signatureData = strip_tags($signatureData);

        // Additional XSS protection - remove javascript: protocols and other dangerous patterns
        $dangerousPatterns = [
            '/javascript:/i',
            '/vbscript:/i',
            '/data:text\/html/i',
            '/data:application\/javascript/i',
            '/onclick/i',
            '/onload/i',
            '/onerror/i',
            '/<script/i',
            '/<iframe/i',
            '/<object/i',
            '/<embed/i',
            '/<form/i'
        ];

        foreach ($dangerousPatterns as $pattern) {
            if (preg_match($pattern, $signatureData)) {
                Log::warning('Dangerous pattern detected in signature data', [
                    'fieldId' => $fieldId,
                    'pattern' => $pattern
                ]);
                return false;
            }
        }

        // Strict validation of base64 image data format
        if (!preg_match('/^data:image\/png;base64,([A-Za-z0-9+\/=]+)$/', $signatureData, $matches)) {
            Log::warning('Invalid signature data format', ['fieldId' => $fieldId]);
            return false;
        }

        $base64Data = $matches[1]; // Use captured group instead of substr for safety

        // Validate base64 data length (prevent DoS)
        if (strlen($base64Data) > 500000) { // 500KB limit
            Log::warning('Signature data too large', ['fieldId' => $fieldId, 'size' => strlen($base64Data)]);
            return false;
        }

        // Validate base64 format more strictly
        if (!preg_match('/^[A-Za-z0-9+\/]*={0,2}$/', $base64Data)) {
            Log::warning('Invalid base64 format', ['fieldId' => $fieldId]);
            return false;
        }

        // Decode with strict mode
        $imageData = base64_decode($base64Data, true);
        if ($imageData === false) {
            Log::warning('Failed to decode base64 data', ['fieldId' => $fieldId]);
            return false;
        }

        // Validate decoded data size
        if (strlen($imageData) < 100 || strlen($imageData) > 1000000) { // 100 bytes min, 1MB max
            Log::warning('Invalid decoded image size', [
                'fieldId' => $fieldId,
                'size' => strlen($imageData)
            ]);
            return false;
        }

        // Validate PNG file signature (magic bytes)
        $pngSignature = "\x89\x50\x4E\x47\x0D\x0A\x1A\x0A";
        if (substr($imageData, 0, 8) !== $pngSignature) {
            Log::warning('Invalid PNG signature', ['fieldId' => $fieldId]);
            return false;
        }

        // Additional PNG structure validation
        if (!$this->validatePngStructure($imageData)) {
            Log::warning('Invalid PNG structure', ['fieldId' => $fieldId]);
            return false;
        }

        return [
            'imageData' => $imageData,
            'base64Data' => $base64Data,
            'size' => strlen($imageData)
        ];
    }

    /**
     * Validate PNG file structure to ensure it's a legitimate image
     *
     * @param string $imageData Binary image data
     * @return bool True if valid PNG structure
     */
    private function validatePngStructure($imageData)
    {
        $length = strlen($imageData);

        // PNG must be at least 8 bytes (signature) + 25 bytes (IHDR chunk) + 12 bytes (IEND chunk)
        if ($length < 45) {
            return false;
        }

        // Check for IHDR chunk (must be first chunk after signature)
        $ihdrPos = strpos($imageData, 'IHDR', 8);
        if ($ihdrPos === false || $ihdrPos !== 12) {
            return false;
        }

        // Check for IEND chunk (must be at the end)
        $iendPos = strrpos($imageData, 'IEND');
        if ($iendPos === false || $iendPos + 8 !== $length) {
            return false;
        }

        // Basic dimension validation from IHDR
        $ihdrData = substr($imageData, 16, 8); // Width and height (4 bytes each)
        $width = unpack('N', substr($ihdrData, 0, 4))[1];
        $height = unpack('N', substr($ihdrData, 4, 4))[1];

        // Reasonable dimension limits for signatures
        if ($width < 10 || $width > 2000 || $height < 10 || $height > 2000) {
            Log::warning('PNG dimensions out of acceptable range', [
                'width' => $width,
                'height' => $height
            ]);
            return false;
        }

        return true;
    }

    /**
     * Sanitize position data to prevent injection attacks
     *
     * @param array $position Raw position data
     * @return array Sanitized position data
     */
    private function sanitizePositionData($position)
    {
        $sanitized = [];

        $fields = ['x_percent', 'y_percent', 'w_percent', 'h_percent'];

        foreach ($fields as $field) {
            $value = $position[$field] ?? 0;
            // Convert to float and validate range, then store as decimal (0-1)
            $value = (float) $value;
            $value = max(0, min(1, $value));  // Clamp 0-1 (since /100 already done)
            if ($field === 'w_percent') $value = max(0.1, $value);  // Min 10%
            if ($field === 'h_percent') $value = max(0.05, $value);  // Min 5%
            $sanitized[$field] = $value;
        }

        return $sanitized;
    }

    /*public function index()
    {
        $documents = Document::with('signers')->get();
        return view('crm.documents.index', compact('documents'));
    }*/

    // This method is now handled by route redirect to Signature Dashboard
    // public function index($id = null) - REMOVED - now redirects to signatures.index

    public function create()
    {
        return view('crm.documents.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255|regex:/^[a-zA-Z0-9\s\-\_\.\(\)]+$/',
            'document' => 'required|file|mimes:pdf|max:10240', // Max 10MB
        ], [
            'title.regex' => 'Title can only contain letters, numbers, spaces, hyphens, underscores, periods, and parentheses.'
        ]);

        try {
            // Sanitize title to prevent XSS - used as file_name for display
            $sanitizedTitle = strip_tags(trim($request->title));
            $sanitizedTitle = htmlspecialchars($sanitizedTitle, ENT_QUOTES, 'UTF-8');

            $document = auth('admin')->user()->documents()->create([
                'file_name' => $sanitizedTitle,
                'status' => 'draft',
                'created_by' => auth('admin')->id(),
            ]);

            // Verify the uploaded file is a valid PDF
            $uploadedFile = $request->file('document');
            if (!$uploadedFile->isValid()) {
                throw new \Exception('Invalid file upload');
            }

            // Additional MIME type verification
            $mimeType = $uploadedFile->getMimeType();
            if (!in_array($mimeType, ['application/pdf'])) {
                throw new \Exception('Invalid file type. Only PDF files are allowed.');
            }

            // Store uploaded file temporarily
            $tempPath = $uploadedFile->storeAs('tmp', uniqid('pdf_', true) . '.pdf', 'public');
            $tempFullPath = storage_path('app/public/' . $tempPath);
            Log::info('After storeAs', ['tempFullPath' => $tempFullPath, 'exists' => file_exists($tempFullPath)]);
            $normalizedDir = storage_path('app/public/tmp/normalized');
            if (!file_exists($normalizedDir)) {
                mkdir($normalizedDir, 0777, true);
            }
            $normalizedPath = $normalizedDir . '/' . basename($tempFullPath);

            // Normalize PDF with Ghostscript
            if ($this->normalizePdfWithGhostscript($tempFullPath, $normalizedPath)) {
                $pdfToAdd = $normalizedPath;
                Log::info('PDF normalized with Ghostscript', ['original' => $tempFullPath, 'normalized' => $normalizedPath]);
            } else {
                $pdfToAdd = $tempFullPath;
                Log::warning('Ghostscript normalization failed, using original PDF', ['path' => $tempFullPath]);
            }

            // Upload to S3
            $adminId = auth('admin')->id();
            $docType = 'ad_hoc_documents'; // Category for manually uploaded documents
            
            // Verify PDF file exists before upload
            if (!file_exists($pdfToAdd)) {
                throw new \Exception("PDF file not found at path: {$pdfToAdd}");
            }
            
            Log::info('Preparing S3 upload', [
                'pdf_path' => $pdfToAdd,
                'file_exists' => file_exists($pdfToAdd),
                'file_size' => file_exists($pdfToAdd) ? filesize($pdfToAdd) : 0,
                'admin_id' => $adminId
            ]);
            
            // Sanitize filename to avoid issues with special characters
            $originalFileName = $uploadedFile->getClientOriginalName();
            $sanitizedFileName = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', pathinfo($originalFileName, PATHINFO_FILENAME));
            $extension = pathinfo($originalFileName, PATHINFO_EXTENSION);
            $fileName = time() . '_' . $sanitizedFileName . '.' . $extension;
            
            $s3FilePath = $adminId . '/' . $docType . '/' . $fileName;
            
            // Verify S3 configuration
            $region = config('filesystems.disks.s3.region');
            $bucket = config('filesystems.disks.s3.bucket');
            $awsKey = config('filesystems.disks.s3.key');
            $awsSecret = config('filesystems.disks.s3.secret');
            
            Log::info('S3 configuration check', [
                'region' => $region,
                'bucket' => $bucket,
                'has_key' => !empty($awsKey),
                'has_secret' => !empty($awsSecret),
                's3_path' => $s3FilePath
            ]);
            
            if (empty($bucket) || empty($region)) {
                throw new \Exception("S3 configuration incomplete. Bucket: " . ($bucket ?: 'missing') . ", Region: " . ($region ?: 'missing'));
            }
            
            // Read file contents
            $fileContents = file_get_contents($pdfToAdd);
            if ($fileContents === false) {
                throw new \Exception("Failed to read PDF file contents from: {$pdfToAdd}");
            }
            
            Log::info('File contents read', [
                'content_size' => strlen($fileContents),
                's3_path' => $s3FilePath
            ]);
            
            // Upload to S3 (matching pattern from working examples in codebase)
            try {
                $uploadResult = Storage::disk('s3')->put($s3FilePath, $fileContents);
                
                if ($uploadResult === false) {
                    throw new \Exception("S3 upload returned false - upload may have failed");
                }
                
                Log::info('S3 put command executed successfully', [
                    's3_path' => $s3FilePath,
                    'result' => $uploadResult
                ]);
            } catch (\Exception $s3Exception) {
                Log::error('S3 upload failed', [
                    'error' => $s3Exception->getMessage(),
                    'error_class' => get_class($s3Exception),
                    'trace' => $s3Exception->getTraceAsString(),
                    's3_path' => $s3FilePath,
                    'bucket' => $bucket,
                    'region' => $region
                ]);
                throw $s3Exception;
            }
            
            // Get S3 URL using Laravel's url() method (matches working examples)
            $s3Url = Storage::disk('s3')->url($s3FilePath);
            
            // Update document with S3 file information.
            // client_id is intentionally left null here — this is a staff-authored
            // ad-hoc document not yet linked to any client/lead. It will be linked
            // later if the user explicitly assigns it via the edit/signature flow.
            $document->update([
                'file_name' => $originalFileName,
                'filetype' => $uploadedFile->getMimeType(),
                'myfile' => $s3Url,          // Full S3 URL
                'myfile_key' => $fileName,   // S3 key for reference
                'doc_type' => $docType,      // Document category
                'file_size' => $uploadedFile->getSize(),
            ]);
            
            Log::info('Document file uploaded to S3 successfully', [
                'document_id' => $document->id,
                's3_url' => $s3Url,
                's3_path' => $s3FilePath,
                'file_size' => $uploadedFile->getSize()
            ]);

            // Clean up temp files
            @unlink($tempFullPath);
            if (file_exists($normalizedPath)) {
                @unlink($normalizedPath);
            }

            Log::info('Document uploaded successfully', [
                'document_id' => $document->id,
                'user_id' => auth('admin')->id(),
                'filename' => $uploadedFile->getClientOriginalName()
            ]);

            // Redirect directly to edit page to place signature fields
            return redirect()->route('documents.edit', $document->id)
                ->with('success', 'Document uploaded successfully! Now place signature fields on the document.');
        } catch (\Exception $e) {
            // Initialize variables for cleanup
            $s3FilePathForCleanup = $s3FilePath ?? null;
            
            // Clean up document record if it was created but S3 upload failed
            if (isset($document) && $document->id) {
                try {
                    // Check if document has S3 file that needs cleanup
                    if (!empty($s3FilePathForCleanup) && Storage::disk('s3')->exists($s3FilePathForCleanup)) {
                        Storage::disk('s3')->delete($s3FilePathForCleanup);
                        Log::info('Deleted S3 file after failed upload', ['s3_path' => $s3FilePathForCleanup]);
                    }
                    // Delete the document record
                    $document->delete();
                    Log::info('Cleaned up document record after failed upload', ['document_id' => $document->id]);
                } catch (\Exception $cleanupException) {
                    Log::error('Failed to cleanup document after upload error', [
                        'document_id' => $document->id ?? null,
                        'cleanup_error' => $cleanupException->getMessage(),
                        'original_error' => $e->getMessage()
                    ]);
                }
            }
            
            // Clean up temp files if they exist
            if (isset($tempFullPath) && file_exists($tempFullPath)) {
                @unlink($tempFullPath);
            }
            if (isset($normalizedPath) && file_exists($normalizedPath)) {
                @unlink($normalizedPath);
            }
            
            // Log detailed error
            Log::error('Document upload failed with detailed error', [
                'error_message' => $e->getMessage(),
                'error_class' => get_class($e),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'error_trace' => $e->getTraceAsString(),
                'filename' => request()->file('document')?->getClientOriginalName(),
                's3_path' => $s3FilePathForCleanup,
                's3_bucket' => config('filesystems.disks.s3.bucket'),
                's3_region' => config('filesystems.disks.s3.region'),
            ]);
            
            return $this->handleError(
                $e,
                'document_upload',
                'An error occurred while uploading the document. Please try again.',
                'documents.create',
                ['filename' => request()->file('document')?->getClientOriginalName()]
            );
        }
    }

    /**
     * Normalize a PDF using Ghostscript to ensure compatibility with FPDI.
     * Returns true on success, false on failure.
     */
    private function normalizePdfWithGhostscript($inputPath, $outputPath)
    {
        // Path to Ghostscript executable (ensure it's in your PATH or use full path)
        $gsPath = 'gswin64c'; // or e.g. 'C:\\Program Files\\gs\\gs10.03.0\\bin\\gswin64c.exe'
        $cmd = '"' . $gsPath . '" -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -dNOPAUSE -dQUIET -dBATCH -sOutputFile=' . escapeshellarg($outputPath) . ' ' . escapeshellarg($inputPath);
        exec($cmd, $output, $returnVar);
        return $returnVar === 0 && file_exists($outputPath);
    }

    public function edit($id)
    { 
        // Sanitize and validate document ID
        $documentId = (int) $id;
        if ($documentId <= 0) {
            Log::warning('Invalid document ID provided for edit', ['id' => $id]);
            return redirect()->route('signatures.index')->with('error', 'Invalid document ID.');
        }

        $tmpPdfPath = null;
        $isLocalFile = false;

        try {
            $document = \App\Models\Document::findOrFail($documentId);
            $this->authorizeDocumentAssociatedAccess($document);
            $url = $document->myfile;
            $pdfPath = null;
            
            // Initialize default values
            $pdfPages = 1;
            $pdfWidthMM = 210;
            $pdfHeightMM = 297;
            $pagesDimensions = [];

            // Check if URL is a full S3 URL or local path
            if ($url && filter_var($url, FILTER_VALIDATE_URL) && strpos($url, 's3') !== false) {
                // This is an S3 URL - extract the key
                $s3Key = null; // Initialize here
                $parsed = parse_url($url);
                if (isset($parsed['path'])) {
                    $s3Key = ltrim(urldecode($parsed['path']), '/');
                }
                
                if (!$s3Key || !Storage::disk('s3')->exists($s3Key)) {
                    Log::error('PDF file not found in S3 for document: ' . $documentId, [
                        'url' => $url,
                        's3Key' => $s3Key,
                        's3_exists' => $s3Key ? Storage::disk('s3')->exists($s3Key) : 'no_path'
                    ]);
                    return redirect()->route('signatures.index')->with('error', 'Document file not found.');
                }

                // Download PDF from S3 to a temp file
                $tmpPdfPath = storage_path('app/tmp_' . uniqid() . '.pdf');
                $pdfStream = Storage::disk('s3')->get($s3Key);
                file_put_contents($tmpPdfPath, $pdfStream);
                Log::info('Downloaded S3 file for document edit', ['s3Key' => $s3Key, 'tempPath' => $tmpPdfPath]);
            } elseif ($url && file_exists(storage_path('app/public/' . $url))) {
                // This is a local file path and file exists
                $tmpPdfPath = storage_path('app/public/' . $url);
                $isLocalFile = true;
                Log::info('Using local file for document edit', [
                    'path' => $tmpPdfPath,
                    'exists' => file_exists($tmpPdfPath),
                    'readable' => is_readable($tmpPdfPath),
                    'size' => file_exists($tmpPdfPath) ? filesize($tmpPdfPath) : 0
                ]);
            } else {
                // Try to build S3 key from DB fields as fallback
                if (!empty($document->myfile_key) && !empty($document->doc_type) && !empty($document->client_id)) {
                    $admin = DB::table('admins')->select('client_id')->where('id', $document->client_id)->first();
                    if ($admin && $admin->client_id) {
                        $s3Key = $admin->client_id . '/' . $document->doc_type . '/' . $document->myfile_key;
                        
                        if (Storage::disk('s3')->exists($s3Key)) {
                            // Download PDF from S3 to a temp file
                            $tmpPdfPath = storage_path('app/tmp_' . uniqid() . '.pdf');
                            $pdfStream = Storage::disk('s3')->get($s3Key);
                            file_put_contents($tmpPdfPath, $pdfStream);
                            Log::info('Downloaded S3 file via fallback for document edit', ['s3Key' => $s3Key, 'tempPath' => $tmpPdfPath]);
                        } else {
                            Log::error('PDF file not found in S3 fallback for document: ' . $documentId, [
                                'url' => $url,
                                's3Key' => $s3Key,
                                'myfile_key' => $document->myfile_key,
                                'doc_type' => $document->doc_type,
                                'client_id' => $document->client_id,
                                'local_exists' => $url ? file_exists(storage_path('app/public/' . $url)) : false
                            ]);
                            return redirect()->route('signatures.index')->with('error', 'Document file not found.');
                        }
                    } else {
                        Log::error('PDF file not found - no valid storage method for document: ' . $documentId, [
                            'url' => $url,
                            'myfile_key' => $document->myfile_key,
                            'doc_type' => $document->doc_type,
                            'client_id' => $document->client_id,
                            'local_exists' => $url ? file_exists(storage_path('app/public/' . $url)) : false
                        ]);
                        return redirect()->route('signatures.index')->with('error', 'Document file not found.');
                    }
                } else {
                    Log::error('PDF file not found - no storage information for document: ' . $documentId, [
                        'url' => $url,
                        'myfile_key' => $document->myfile_key,
                        'doc_type' => $document->doc_type,
                        'client_id' => $document->client_id,
                        'local_exists' => $url ? file_exists(storage_path('app/public/' . $url)) : false
                    ]);
                    return redirect()->route('signatures.index')->with('error', 'Document file not found.');
                }
            }

            // Process PDF pages and dimensions
            try { 
                $pdfPages = $this->countPdfPages($tmpPdfPath);
                if (!$pdfPages || $pdfPages < 1) {
                   Log::error('Failed to count PDF pages for document: ' . $documentId, [
                       'tmpPdfPath' => $tmpPdfPath,
                       'file_exists' => file_exists($tmpPdfPath),
                       'file_size' => file_exists($tmpPdfPath) ? filesize($tmpPdfPath) : 'N/A',
                       'is_readable' => is_readable($tmpPdfPath)
                   ]);
                   // Don't fail - use default values instead
                   $pdfPages = 1;  
                   $pagesDimensions = [1 => ['width' => 210, 'height' => 297, 'orientation' => 'P']];
                   Log::warning('Using default PDF dimensions due to counting failure');
               } else {
                   // Get page dimensions
                   $pagesDimensions = $this->getPdfPageDimensions($tmpPdfPath, $pdfPages);

                   // Set default dimensions from first page or use A4 defaults
                   $pdfWidthMM = $pagesDimensions[1]['width'] ?? 210;
                   $pdfHeightMM = $pagesDimensions[1]['height'] ?? 297;
               }
            } catch (\Exception $e) {
                Log::error('Error getting PDF pages or size: ' . $e->getMessage());
                // Use defaults on error
                $pdfPages = 1;
                $pagesDimensions = [1 => ['width' => 210, 'height' => 297, 'orientation' => 'P']];
                $pdfWidthMM = 210;
                $pdfHeightMM = 297;
            }

            // Clean up temp file (only if it was created from S3, not local file)
            // Use finally block below instead of inline cleanup here.

            // Use the correct view path for admin documents edit
            return view('crm.documents.edit', compact('document', 'pdfPages', 'pdfWidthMM', 'pdfHeightMM', 'pagesDimensions'));
        } catch (\Exception $e) {
            Log::error('Exception in DocumentController@edit', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'document_id' => $documentId
            ]);
            return $this->handleError(
                $e,
                'document_edit',
                'An error occurred while loading the document for editing.',
                'signatures.index',
                ['document_id' => $documentId]
            );
        } finally {
            if (!$isLocalFile) {
                TempFileHelper::delete($tmpPdfPath);
            }
        }
    }

    /**
     * Get signature placement metadata for inline checklist modal (JSON API).
     */
    public function getSignaturePlacementData($id)
    {
        $documentId = (int) $id;
        if ($documentId <= 0) {
            return response()->json(['success' => false, 'message' => 'Invalid document ID.'], 400);
        }

        $tmpPdfPath = null;
        $isLocalFile = false;

        try {
            $document = Document::findOrFail($documentId);
            $this->authorizeDocumentAssociatedAccess($document);
            $url = $document->myfile;

            if ($url && filter_var($url, FILTER_VALIDATE_URL) && strpos($url, 's3') !== false) {
                $parsed = parse_url($url);
                $s3Key = isset($parsed['path']) ? ltrim(urldecode($parsed['path']), '/') : null;
                if (!$s3Key || !Storage::disk('s3')->exists($s3Key)) {
                    return response()->json(['success' => false, 'message' => 'Document file not found.'], 404);
                }
                $tmpPdfPath = storage_path('app/tmp_' . uniqid() . '.pdf');
                file_put_contents($tmpPdfPath, Storage::disk('s3')->get($s3Key));
            } elseif ($url && file_exists(storage_path('app/public/' . $url))) {
                $tmpPdfPath = storage_path('app/public/' . $url);
                $isLocalFile = true;
            } else {
                if (!empty($document->myfile_key) && !empty($document->doc_type) && !empty($document->client_id)) {
                    $admin = DB::table('admins')->select('client_id')->where('id', $document->client_id)->first();
                    if ($admin && $admin->client_id) {
                        $s3Key = $admin->client_id . '/' . $document->doc_type . '/' . $document->myfile_key;
                        if (Storage::disk('s3')->exists($s3Key)) {
                            $tmpPdfPath = storage_path('app/tmp_' . uniqid() . '.pdf');
                            file_put_contents($tmpPdfPath, Storage::disk('s3')->get($s3Key));
                        }
                    }
                }
                if (!$tmpPdfPath || !file_exists($tmpPdfPath)) {
                    return response()->json(['success' => false, 'message' => 'Document file not found.'], 404);
                }
            }

            $pdfPages = 1;
            $pdfWidthMM = 210;
            $pdfHeightMM = 297;
            $pagesDimensions = [];

            if ($tmpPdfPath && file_exists($tmpPdfPath)) {
                try {
                    $pdfPages = $this->countPdfPages($tmpPdfPath) ?: 1;
                    $pagesDimensions = $this->getPdfPageDimensions($tmpPdfPath, $pdfPages);
                    $pdfWidthMM = $pagesDimensions[1]['width'] ?? 210;
                    $pdfHeightMM = $pagesDimensions[1]['height'] ?? 297;
                } catch (\Exception $e) {
                    $pagesDimensions = [1 => ['width' => 210, 'height' => 297, 'orientation' => 'P']];
                }
            }

            $existingFields = $document->signatureFields->map(function ($f) {
                return [
                    'page_number' => $f->page_number,
                    'x_percent' => (float) ($f->x_percent ?? 0),
                    'y_percent' => (float) ($f->y_percent ?? 0),
                    'w_percent' => (float) ($f->width_percent ?? 0),
                    'h_percent' => (float) ($f->height_percent ?? 0),
                ];
            })->values()->toArray();

            return response()->json([
                'success' => true,
                'documentId' => $document->id,
                'pdfPages' => $pdfPages,
                'pdfWidthMM' => $pdfWidthMM,
                'pdfHeightMM' => $pdfHeightMM,
                'pagesDimensions' => $pagesDimensions,
                'existingFields' => $existingFields,
            ]);
        } catch (\Exception $e) {
            Log::error('getSignaturePlacementData failed', ['id' => $id, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to load document.'], 500);
        } finally {
            TempFileHelper::delete($tmpPdfPath ?? null);
        }
    }

    /**
     * Count the number of pages in a PDF file using Smalot\PdfParser.
     * Enhanced with configuration and better error handling.
     */
    protected function countPdfPages($pathToPdf)
    {
        // Validate file exists and is readable
        if (!file_exists($pathToPdf)) {
            Log::error('PDF file does not exist for page counting', ['path' => $pathToPdf]);
            return null;
        }
        
        if (!is_readable($pathToPdf)) {
            Log::error('PDF file is not readable for page counting', ['path' => $pathToPdf]);
            return null;
        }
        
        // Get file size for logging
        $fileSize = filesize($pathToPdf);
        
        // Use Python service (primary method)
        try {
            $pythonService = app(\App\Services\PythonService::class);
            
            if (!$pythonService->isHealthy()) {
                Log::error('Python service unavailable for PDF page counting');
                return null;
            }
            
            $pdfInfo = $pythonService->getPdfInfo($pathToPdf);
            
            if ($pdfInfo && isset($pdfInfo['success']) && $pdfInfo['success'] === true && isset($pdfInfo['page_count'])) {
                $pageCount = (int) $pdfInfo['page_count'];
                
                if ($pageCount > 0) {
                    Log::info('Successfully counted PDF pages using Python service', [
                        'path' => $pathToPdf,
                        'page_count' => $pageCount,
                        'file_size' => $fileSize
                    ]);
                    return $pageCount;
                } else {
                    Log::warning('Python service returned invalid page count', [
                        'path' => $pathToPdf,
                        'page_count' => $pageCount,
                        'pdf_info' => $pdfInfo
                    ]);
                }
            } else {
                Log::error('Python service failed to get PDF info', [
                    'path' => $pathToPdf,
                    'pdf_info' => $pdfInfo,
                    'error' => $pdfInfo['error'] ?? ($pdfInfo ? 'Invalid response format' : 'Service returned null')
                ]);
            }
            
            return null;
        } catch (\Exception $e) {
            Log::error('Error counting PDF pages with Python service', [
                'error' => $e->getMessage(),
                'error_type' => get_class($e),
                'path' => $pathToPdf,
                'file_size' => $fileSize
            ]);
            return null;
        }
    }

    /**
     * Get PDF page dimensions using Python service
     */
    private function getPdfPageDimensions($pdfPath, $pageCount)
    {
        $pagesDimensions = [];

        try {
            $pythonService = app(\App\Services\PythonService::class);
            
            if (!$pythonService->isHealthy()) {
                Log::warning('Python service unavailable, using A4 defaults');
                for ($pageNum = 1; $pageNum <= $pageCount; $pageNum++) {
                    $pagesDimensions[$pageNum] = [
                        'width' => 210,
                        'height' => 297,
                        'orientation' => 'P'
                    ];
                }
                return $pagesDimensions;
            }
            
            $pdfInfo = $pythonService->getPdfInfo($pdfPath);
            
            if ($pdfInfo && isset($pdfInfo['success']) && $pdfInfo['success'] === true && isset($pdfInfo['pages'])) {
                foreach ($pdfInfo['pages'] as $index => $pageInfo) {
                    $pageNum = $index + 1;
                    $pagesDimensions[$pageNum] = [
                        'width' => $pageInfo['width_mm'] ?? 210,
                        'height' => $pageInfo['height_mm'] ?? 297,
                        'orientation' => ($pageInfo['width_mm'] ?? 210) > ($pageInfo['height_mm'] ?? 297) ? 'L' : 'P'
                    ];
                }
                
                // Ensure all pages from 1 to $pageCount are present
                // Fill in any missing pages with default values
                for ($pageNum = 1; $pageNum <= $pageCount; $pageNum++) {
                    if (!isset($pagesDimensions[$pageNum])) {
                        // Use dimensions from first available page, or A4 defaults
                        $defaultWidth = isset($pagesDimensions[1]) ? $pagesDimensions[1]['width'] : 210;
                        $defaultHeight = isset($pagesDimensions[1]) ? $pagesDimensions[1]['height'] : 297;
                        $pagesDimensions[$pageNum] = [
                            'width' => $defaultWidth,
                            'height' => $defaultHeight,
                            'orientation' => $defaultWidth > $defaultHeight ? 'L' : 'P'
                        ];
                    }
                }
            } else {
                Log::warning('Python service failed to get PDF dimensions, using A4 defaults');
                for ($pageNum = 1; $pageNum <= $pageCount; $pageNum++) {
                    $pagesDimensions[$pageNum] = [
                        'width' => 210,
                        'height' => 297,
                        'orientation' => 'P'
                    ];
                }
            }
        } catch (\Exception $e) {
            Log::warning('Error getting page dimensions, using defaults', ['error' => $e->getMessage()]);
            for ($pageNum = 1; $pageNum <= $pageCount; $pageNum++) {
                $pagesDimensions[$pageNum] = [
                    'width' => 210,
                    'height' => 297,
                    'orientation' => 'P'
                ];
            }
        }

        return $pagesDimensions;
    }

    public function update(Request $request, $id)
    {
        // Sanitize document ID
        $documentId = (int) $id;
        if ($documentId <= 0) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Invalid document ID.'], 400);
            }
            return back()->withErrors(['error' => 'Invalid document ID.']);
        }

        //$document = auth()->user()->documents()->findOrFail($documentId);
        $document = \App\Models\Document::findOrFail($documentId);
        $this->authorizeDocumentAssociatedAccess($document);

        // Debug logging - check document attributes
        Log::info('Document update method called', [
            'document_id' => $document->id,
            'client_matter_id' => $document->client_matter_id,
            'doc_type' => $document->doc_type,
            'client_id' => $document->client_id,
            'type' => $document->type,
            'all_attributes' => $document->toArray()
        ]);

        // Normalize signatures to ensure it is always an array (fixes jQuery form-urlencoded serialization issues)
        $signaturesInput = $request->input('signatures');
        if (is_string($signaturesInput)) {
            $decoded = json_decode($signaturesInput, true);
            $signaturesInput = is_array($decoded) ? $decoded : $signaturesInput;
        }
        if (is_object($signaturesInput) || (is_array($signaturesInput) && !empty($signaturesInput) && !array_is_list($signaturesInput))) {
            $signaturesInput = array_values((array) $signaturesInput);
        }
        $request->merge(['signatures' => $signaturesInput]);

        // Updated validation for percentages
        $request->validate([
            'signatures' => 'required|array|min:1|max:100',
            'signatures.*.page_number' => 'required|integer|min:1|max:999',
            'signatures.*.x_percent' => 'required|numeric|min:0|max:100',
            'signatures.*.y_percent' => 'required|numeric|min:0|max:100',
            'signatures.*.w_percent' => 'required|numeric|min:0|max:100',
            'signatures.*.h_percent' => 'required|numeric|min:0|max:100',
        ]);

        $pdfPath = null;
        try {
            // Remove old fields for this document
            $document->signatureFields()->delete();

            // Get PDF path and page count for calculating positions
            $pdfPages = 1;
            $pagesDimensions = [];
            
            // Get PDF file path (similar to edit method)
            $url = $document->myfile;
            if ($url && filter_var($url, FILTER_VALIDATE_URL) && strpos($url, 's3') !== false) {
                // S3 URL - download to temp file
                $parsed = parse_url($url);
                $s3Key = isset($parsed['path']) ? ltrim(urldecode($parsed['path']), '/') : null;
                if ($s3Key && Storage::disk('s3')->exists($s3Key)) {
                    $pdfPath = storage_path('app/tmp_' . uniqid() . '.pdf');
                    $pdfStream = Storage::disk('s3')->get($s3Key);
                    file_put_contents($pdfPath, $pdfStream);
                }
            } elseif ($url && file_exists(storage_path('app/public/' . $url))) {
                // Local file
                $pdfPath = storage_path('app/public/' . $url);
            }
            
            // Get page count and dimensions if PDF path is available
            if ($pdfPath && file_exists($pdfPath)) {
                try {
                    $pdfPages = $this->countPdfPages($pdfPath);
                    if ($pdfPages > 0) {
                        $pagesDimensions = $this->getPdfPageDimensions($pdfPath, $pdfPages);
                    }
                } catch (\Exception $e) {
                    Log::warning('Error getting PDF dimensions in update method', ['error' => $e->getMessage()]);
                }
            }
            
            $validatedSignatures = [];
            foreach ($request->signatures as $signature) {
                $pageNumber = max(1, min(999, (int) $signature['page_number']));
                
                // Get dimensions for this specific page
                $pageDims = $pagesDimensions[$pageNumber] ?? [
                    'width' => 210, // Default A4 width in mm
                    'height' => 297, // Default A4 height in mm
                ];
                
                // Sanitize to decimals (0-1) for percentages
                $x_percent = max(0, min(100, (float) $signature['x_percent'])) / 100;
                $y_percent = max(0, min(100, (float) $signature['y_percent'])) / 100;
                $width_percent = max(0, min(100, (float) ($signature['w_percent'] ?? 0))) / 100;
                $height_percent = max(0, min(100, (float) ($signature['h_percent'] ?? 0))) / 100;
                
                // Calculate positions in millimeters (rounded to integers as database column is integer type)
                $x_position = (int) round($x_percent * $pageDims['width']);
                $y_position = (int) round($y_percent * $pageDims['height']);
                
                $sanitizedSignature = [
                    'page_number' => $pageNumber,
                    'x_percent' => $x_percent,
                    'y_percent' => $y_percent,
                    'width_percent' => $width_percent,
                    'height_percent' => $height_percent,
                    'x_position' => $x_position,
                    'y_position' => $y_position,
                ];
                $validatedSignatures[] = $sanitizedSignature;
            }

            // Create signature fields with validated data
            foreach ($validatedSignatures as $signature) {
                $document->signatureFields()->create([
                    'signer_id' => null, // Will be set when assigning a signer later
                    'page_number' => $signature['page_number'],
                    'x_position' => $signature['x_position'],
                    'y_position' => $signature['y_position'],
                    'x_percent' => $signature['x_percent'],
                    'y_percent' => $signature['y_percent'],
                    'width_percent' => $signature['width_percent'],
                    'height_percent' => $signature['height_percent'],
                ]);
            }

            Log::info('Signature fields updated', [
                'document_id' => $document->id,
                'fields_count' => count($validatedSignatures),
                'user_id' => auth('admin')->id()
            ]);

            // Check if there's pending signer information from document upload
            $pendingSigner = session('pending_document_signer');
            if ($pendingSigner && isset($pendingSigner['email']) && isset($pendingSigner['name'])) {
                // Send document for signature using service
                $signatureService = app(\App\Services\SignatureService::class);
                $signers = [
                    ['email' => $pendingSigner['email'], 'name' => $pendingSigner['name']]
                ];
                
                // Prepare email options
                $emailOptions = [
                    'subject' => $pendingSigner['email_subject'] ?? 'Document Signature Request from Bansal Migration',
                    'message' => $pendingSigner['email_message'] ?? 'Please review and sign the attached document.',
                    'template' => $pendingSigner['email_template'] ?? 'emails.signature.send',
                ];
                
                // Add from_email if specified
                if (!empty($pendingSigner['from_email'])) {
                    $emailOptions['from_email'] = $pendingSigner['from_email'];
                }
                
                $success = $signatureService->send($document, $signers, $emailOptions);
                
                // Clear the session data
                session()->forget('pending_document_signer');
                
                if ($success) {
                    return redirect()->route('signatures.index')
                        ->with('success', 'Signature fields placed and document sent for signature successfully!');
                } else {
                    return redirect()->route('signatures.index')
                        ->with('warning', 'Signature fields saved but email failed to send. Please try resending from the document details.');
                }
            }

            // Check if this is an agreement document with client_matter_id (from checklist workflow)
            // This check MUST happen BEFORE updating status to determine the correct flow
            $isChecklistAgreement = !empty($document->client_matter_id) && 
                                   $document->doc_type === 'agreement' && 
                                   !empty($document->client_id);
            
            // Visa/nomination action-bar workflow: create signer and link, but do NOT send email
            // (user clicks Send from the document tab action bar)
            $isActionBarSignatureDoc = !empty($document->client_matter_id) &&
                              in_array($document->doc_type, ['visa', 'nomination'], true) &&
                              !empty($document->client_id);
            
            // Force refresh by reloading document from database
            $document->refresh();
            
            Log::info('Document update - checking workflow type', [
                'document_id' => $document->id,
                'client_matter_id' => $document->client_matter_id,
                'client_matter_id_type' => gettype($document->client_matter_id),
                'client_matter_id_empty' => empty($document->client_matter_id),
                'doc_type' => $document->doc_type,
                'doc_type_match' => ($document->doc_type === 'agreement'),
                'client_id' => $document->client_id,
                'client_id_empty' => empty($document->client_id),
                'is_checklist_agreement' => $isChecklistAgreement
            ]);
            
            if ($isChecklistAgreement) {
                // Checklist workflow: Automatically generate signing link and return to checklist
                try {
                    // Get client information using the relationship
                    $client = $document->client; // This is defined in Document model as belongsTo(Admin::class, 'client_id')
                    
                    if (!$client) {
                        throw new \Exception('Client not found for document');
                    }

                    $recipient = $client->resolveSigningRecipient();
                    if (!$recipient) {
                        throw new \Exception('Client email not available. Please add a real email on the company/client profile.');
                    }
                    
                    // Use the client's ID (primary key from admins table)
                    $clientDatabaseId = $client->id;
                    
                    // Create signer with token
                    $signer = $document->signers()->create([
                        'email' => $recipient['email'],
                        'name' => $recipient['name'],
                        'token' => \Illuminate\Support\Str::random(64),
                        'status' => 'pending',
                        'reminder_count' => 0,
                    ]);
                    
                    // Generate signing URL
                    $signingUrl = url("/sign/{$document->id}/{$signer->token}");
                    
                    // Store signing link in document
                    $signatureLinks = [
                        [
                            'email' => $signer->email,
                            'name' => $signer->name,
                            'url' => $signingUrl
                        ]
                    ];
                    
                    // Update document with signing information
                    $document->update([
                        'status' => 'sent',
                        'signature_doc_link' => json_encode($signatureLinks),
                    ]);
                    
                    Log::info('Signature link generated successfully for checklist agreement', [
                        'document_id' => $document->id,
                        'client_matter_id' => $document->client_matter_id,
                        'signer_email' => $signer->email,
                        'signing_url' => $signingUrl
                    ]);
                    
                    // Redirect back to client detail page with checklists tab
                    // Encode client ID the same way the rest of the app does (using the database ID)
                    $encodedClientId = base64_encode(convert_uuencode($clientDatabaseId));
                    $redirectUrl = url("/clients/detail/{$encodedClientId}/checklists");
                    
                    ActivitiesLog::create([
                        'client_id' => $clientDatabaseId,
                        'created_by' => auth('admin')->id(),
                        'subject' => 'placed signature fields and sent cost agreement for signature',
                        'description' => '<ul><li><strong>Document:</strong> ' . htmlspecialchars($document->file_name ?? 'Agreement') . '</li><li><strong>Sent to:</strong> ' . htmlspecialchars($signer->email) . '</li></ul>',
                        'activity_type' => 'signature',
                        'task_status' => 0,
                        'pin' => 0,
                    ]);
                    
                    Log::info('Redirecting to client detail page', [
                        'document_id' => $document->id,
                        'client_database_id' => $clientDatabaseId,
                        'encoded_client_id' => $encodedClientId,
                        'redirect_url' => $redirectUrl
                    ]);
                    
                    if ($request->expectsJson()) {
                        return response()->json([
                            'success' => true,
                            'message' => 'Signature fields placed successfully! The signing link is now available in the checklist.',
                        ]);
                    }
                    return redirect($redirectUrl)
                        ->with('success', 'Signature fields placed successfully! The signing link is now available in the checklist.');
                        
                } catch (\Exception $e) {
                    Log::error('Failed to auto-generate signature link for checklist agreement', [
                        'document_id' => $document->id,
                        'client_matter_id' => $document->client_matter_id,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    
                    // For checklist agreements, even if auto-generation fails, 
                    // still redirect back to checklist with error message
                    // Try to get client ID for redirect
                    $clientForRedirect = $document->client;
                    if ($clientForRedirect) {
                        $encodedClientId = base64_encode(convert_uuencode($clientForRedirect->id));
                        $redirectUrl = url("/clients/detail/{$encodedClientId}/checklists");
                        if ($request->expectsJson()) {
                            return response()->json([
                                'success' => false,
                                'message' => 'Signature fields saved, but failed to generate link automatically. Please try again from the checklist.',
                            ], 422);
                        }
                        return redirect($redirectUrl)
                            ->with('warning', 'Signature fields saved, but failed to generate link automatically. Please try again from the checklist.');
                    }
                }
            }
            
            if ($isActionBarSignatureDoc) {
                // Visa/nomination workflow: Create signer + link, status = placed (user sends from action bar)
                try {
                    $client = $document->client;
                    if (!$client) {
                        throw new \Exception('Client not found');
                    }
                    $recipient = $client->resolveSigningRecipient();
                    if (!$recipient) {
                        throw new \Exception('Client email not available. Please add a real email on the company/client profile.');
                    }
                    $signerEmail = $recipient['email'];
                    $signerName = $recipient['name'];
                    // Reuse pending signer for this email, or upgrade a placeholder signer
                    $signer = $document->signers()
                        ->where('status', 'pending')
                        ->where('email', $signerEmail)
                        ->first();
                    if (!$signer) {
                        $placeholderSigner = $document->signers()
                            ->where('status', 'pending')
                            ->get()
                            ->first(fn ($s) => \App\Models\Admin::isInternalPlaceholderEmail($s->email));
                        if ($placeholderSigner) {
                            $placeholderSigner->update([
                                'email' => $signerEmail,
                                'name' => $signerName,
                                'token' => $placeholderSigner->token ?: \Illuminate\Support\Str::random(64),
                            ]);
                            $signer = $placeholderSigner->fresh();
                        }
                    }
                    if ($signer) {
                        if (empty($signer->token)) {
                            $signer->update([
                                'token' => \Illuminate\Support\Str::random(64),
                                'name' => $signerName ?: $signer->name,
                            ]);
                            $signer->refresh();
                        }
                    } else {
                        $signer = $document->signers()->create([
                            'email' => $signerEmail,
                            'name' => $signerName,
                            'token' => \Illuminate\Support\Str::random(64),
                            'status' => 'pending',
                            'reminder_count' => 0,
                        ]);
                    }
                    $signingUrl = url("/sign/{$document->id}/{$signer->token}");
                    $signatureLinks = [['email' => $signer->email, 'name' => $signer->name, 'url' => $signingUrl]];
                    $document->update([
                        'status' => 'placed',
                        'signature_doc_link' => json_encode($signatureLinks),
                    ]);
                    $encodedClientId = base64_encode(convert_uuencode($client->id));
                    $docsTab = $document->doc_type === 'nomination' ? 'nominationdocuments' : 'visadocuments';
                    $redirectUrl = url("/clients/detail/{$encodedClientId}/{$docsTab}");
                    $docLabel = $document->doc_type === 'nomination' ? 'nomination document' : 'visa document';
                    $sourceKey = $document->doc_type === 'nomination' ? 'nomination_documents' : 'visa_documents';
                    ActivitiesLog::create([
                        'client_id' => $client->id,
                        'created_by' => auth('admin')->id(),
                        'subject' => "placed signature fields on {$docLabel}",
                        'description' => '<ul><li><strong>Document:</strong> ' . htmlspecialchars($document->checklist ?? $document->file_name ?? ucfirst($docLabel)) . '</li><li><strong>Signer:</strong> ' . htmlspecialchars($signer->email) . '</li><li><strong>Next:</strong> Click Send in the action bar to send the signing link</li></ul>',
                        'activity_type' => 'signature',
                        'task_status' => 0,
                        'pin' => 0,
                    ]);
                    if ($request->expectsJson()) {
                        return response()->json([
                            'success' => true,
                            'message' => 'Signature fields saved. Use Send to send the signing link to the client.',
                            'source' => $sourceKey,
                            'redirect_url' => $redirectUrl,
                        ]);
                    }
                    return redirect($redirectUrl)->with('success', 'Signature fields saved. Use the action bar to send.');
                } catch (\Exception $e) {
                    Log::error('Action-bar signature document setup failed', [
                        'document_id' => $document->id,
                        'doc_type' => $document->doc_type,
                        'error' => $e->getMessage(),
                    ]);
                    if ($request->expectsJson()) {
                        return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
                    }
                    throw $e;
                }
            }
            
            // Normal signature dashboard workflow: Update status and redirect to create page
            // This handles all non-checklist documents (ad-hoc documents, etc.)
            $document->update(['status' => 'placed']);
            
            Log::info('Document following normal signature workflow', [
                'document_id' => $document->id,
                'redirecting_to' => 'signatures.create'
            ]);
            
            // Default behavior: Redirect to the signature creation page where user can associate with client/lead and send
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Signature locations added. Please complete the process on the Signatures page.',
                    'redirect_url' => route('signatures.create', ['document_id' => $document->id]),
                ]);
            }
            return redirect()->route('signatures.create', ['document_id' => $document->id])
                ->with('success', 'Signature locations added successfully! Now associate with client/lead and send for signing.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                Log::error('Signature fields update failed', [
                    'document_id' => $documentId,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                return response()->json([
                    'success' => false,
                    'message' => config('app.debug') ? $e->getMessage() : 'An error occurred while saving signature fields.',
                ], 500);
            }
            return $this->handleError(
                $e,
                'signature_fields_update',
                'An error occurred while saving signature fields.',
                'back',
                ['document_id' => $documentId, 'fields_count' => count($validatedSignatures ?? [])]
            );
        } finally {
            TempFileHelper::delete($pdfPath);
        }
    }

    public function sendSigningLink(Request $request, $id)
    {
        //dd($request->all());
        // Sanitize and validate document ID
        $documentId = (int) $id;
        if ($documentId <= 0) {
            return back()->withErrors(['error' => 'Invalid document ID.']);
        }
        $document = \App\Models\Document::findOrFail($documentId);
        $this->authorizeDocumentAssociatedAccess($document);

        // Enhanced validation for email and name
        /*$request->validate([
            'signer_email' => 'required|email|max:255|regex:/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/',
            'signer_name' => 'required|string|min:2|max:100|regex:/^[a-zA-Z\s\-\'\.]+$/',
        ], [
            'signer_email.regex' => 'Please enter a valid email address.',
            'signer_name.regex' => 'Name can only contain letters, spaces, hyphens, apostrophes, and periods.',
            'signer_name.min' => 'Name must be at least 2 characters long.',
            'signer_name.max' => 'Name cannot be longer than 100 characters.',
        ]);*/

        $request->validate([
            'signer_email' => 'required|email',
            'signer_name' => 'required|string|min:2|max:100',
        ], [
            'signer_email.regex' => 'Please enter a valid email address.',
            'signer_name.min' => 'Name must be at least 2 characters long.',
            'signer_name.max' => 'Name cannot be longer than 100 characters.',
        ]);

        try {
            // Sanitize input data
            $signerEmail = strtolower(trim($request->signer_email));
            $signerName = trim($request->signer_name);
            //dd($signerEmail.' '. $signerName);
            // Additional email domain validation (optional - add your trusted domains)
            /*$allowedDomains = config('app.allowed_email_domains', []);
            if (!empty($allowedDomains)) {
                $emailDomain = substr($signerEmail, strpos($signerEmail, '@') + 1);
                if (!in_array($emailDomain, $allowedDomains)) {
                    return back()->withErrors(['signer_email' => 'Email domain not allowed.']);
                }
            }*/

            // Check for duplicate signer
            $existingSigner = $document->signers()->where('email', $signerEmail)->first();
            if ($existingSigner && $existingSigner->status === 'pending') {
                return back()->withErrors(['signer_email' => 'A signing link has already been sent to this email address.']);
            }

            if( isset($request->doc_type) && $request->doc_type == 'agreement')
            {
                $token = $request->pdf_sign_token;
                $isDocumentExistInSignerTbl = $document->signers()->where('document_id', $documentId )->first();
                if($isDocumentExistInSignerTbl)
                {
                    // Update existing document in signer table
                    $signer = $isDocumentExistInSignerTbl->update(['email' => $signerEmail,'name' => $signerName,'token' => $token,'status' => 'pending']);
                    //$signer = $document->signers()->where('token', $token)->first();
                }
                else
                {
                    // Insert document in signer table
                    $signer = $document->signers()->create([
                        'email' => $signerEmail,
                        'name' => $signerName,
                        'token' => $token,
                        'status' => 'pending',
                        'reminder_count' => 0, // PostgreSQL NOT NULL constraint - must set this field
                    ]);
                }
            }
            else
            {
                $token = Str::random(64); // Increased token length for better security
                $signer = $document->signers()->create([
                    'email' => $signerEmail,
                    'name' => $signerName,
                    'token' => $token,
                    'status' => 'pending',
                    'reminder_count' => 0, // PostgreSQL NOT NULL constraint - must set this field
                ]);
            }
            //dd($token);
            $document->status = 'sent';
            $document->save();

            // Define the signing URL before sending the email
            $signingUrl = url("/sign/{$document->id}/{$token}");

            try {
                if( isset($request->doc_type) && $request->doc_type == 'agreement')
                {
                    // Gather uploaded attachments
                    $attachments = [];
                    if ($request->hasFile('attach')) {
                        foreach ($request->file('attach') as $file) {
                            if ($file->isValid()) {
                                $attachments[] = [
                                    'path' => $file->getRealPath(),
                                    'name' => $file->getClientOriginalName(),
                                    'mime' => $file->getMimeType(),
                                ];
                            }
                        }
                    }

                    // Gather checklist files
                    $checklistFiles = [];
                    if ($request->has('checklistfile')) {
                        $checklistIds = $request->input('checklistfile');
                        $checklists = UploadChecklist::whereIn('id', $checklistIds)->get();
                        foreach ($checklists as $checklist) {
                            $filePath = public_path('checklists/' . $checklist->file);
                            if (file_exists($filePath)) {
                                $checklistFiles[] = $filePath;
                            }
                        }
                    }

                    $fromAddress = config('mail.from.address');
                    $fromName = config('mail.from.name', 'Bansal Migration');
                    $emailSignature = '';
                    $activeEmail = Email::where('status', true)->orderBy('id')->first();
                    if ($activeEmail) {
                        $fromAddress = $activeEmail->email ?: $fromAddress;
                        $fromName = $activeEmail->display_name ?: $fromName;
                        $emailSignature = $activeEmail->email_signature ?: '';
                    }
                    
                    // Prepare all attachments
                    $allAttachments = [];
                    foreach ($attachments as $file) {
                        $allAttachments[] = [
                            'path' => $file['path'],
                            'name' => $file['name'],
                            'mime' => $file['mime'],
                        ];
                    }
                    foreach ($checklistFiles as $file) {
                        $allAttachments[] = [
                            'path' => $file,
                            'name' => basename($file),
                        ];
                    }
                    
                    try {
                        Mail::mailer('sendgrid')->send(
                            'emails.sign_agreement_document_email',
                            [
                                'signingUrl' => $signingUrl,
                                'firstName' => $signerName,
                                'emailmessage' => $request->message,
                                'emailSignature' => $emailSignature
                            ],
                            function ($message) use ($signerEmail, $signerName, $request, $fromAddress, $fromName, $allAttachments) {
                                $message->to($signerEmail, $signerName)
                                    ->subject($request->subject)
                                    ->from($fromAddress, $fromName);

                                foreach ($allAttachments as $attachment) {
                                    $path = $attachment['path'] ?? null;
                                    if (!$path || !file_exists($path)) {
                                        continue;
                                    }
                                    $options = [];
                                    if (!empty($attachment['name'])) {
                                        $options['as'] = $attachment['name'];
                                    }
                                    if (!empty($attachment['mime'])) {
                                        $options['mime'] = $attachment['mime'];
                                    }
                                    $message->attach($path, $options);
                                }
                            }
                        );
                        $sendMail = true;
                        
                        // Create activity note for successful email
                        \App\Models\SignatureActivity::create([
                            'document_id' => $document->id,
                            'created_by' => Auth::guard('admin')->id() ?? 1,
                            'action_type' => 'email_sent',
                            'note' => "Email sent successfully to {$signerName} ({$signerEmail})",
                            'metadata' => [
                                'signer_email' => $signerEmail,
                                'signer_name' => $signerName,
                                'subject' => $request->subject,
                                'status' => 'sent_via_sendgrid',
                            ]
                        ]);
                    } catch (\Exception $emailException) {
                        // Create activity note for failed email
                        \App\Models\SignatureActivity::create([
                            'document_id' => $document->id,
                            'created_by' => Auth::guard('admin')->id() ?? 1,
                            'action_type' => 'email_failed',
                            'note' => "Failed to send email to {$signerName} ({$signerEmail}): {$emailException->getMessage()}",
                            'metadata' => [
                                'signer_email' => $signerEmail,
                                'signer_name' => $signerName,
                                'error' => $emailException->getMessage(),
                            ]
                        ]);
                        throw $emailException;
                    }

                    if($sendMail){
                        //Save to mail reports table
                        $obj5 = new \App\Models\EmailLog;
                        $obj5->user_id 		=  @Auth::guard('admin')->user()->id;
                        $obj5->from_mail 	=  $fromAddress;
                        $obj5->to_mail 		=  $document->client_id;
                        $obj5->template_id 	=  $request->template;
                        $obj5->subject		=  $request->subject; //'Bansal Migration Requesting To Sign Your Agreement Document';
                        $obj5->type 		=  'client';
                        $obj5->message		=  $request->message ?? '';
                        $obj5->mail_type    =  1;
                        $obj5->client_id	=  $document->client_id;
                        $obj5->client_matter_id	=  $document->client_matter_id;

                        $attachments = array();
                        if(isset($request->checklistfile)){
                            if(!empty($request->checklistfile)){
                                $checklistfiles = $request->checklistfile;
                                $attachments = array();
                                foreach($checklistfiles as $checklistfile){
                                    $filechecklist =  \App\Models\UploadChecklist::where('id', $checklistfile)->first();
                                    if($filechecklist){
                                        $attachments[] = array('file_name' => $filechecklist->name,'file_url' => $filechecklist->file);
                                    }
                                }
                                $obj5->attachments = json_encode($attachments);
                            }
                        }
                        // Validate required fields before saving
                        if (empty($obj5->from_mail) || empty($obj5->to_mail)) {
                            Log::error('EmailLog validation failed - missing required fields', [
                                'from_mail' => $obj5->from_mail,
                                'to_mail' => $obj5->to_mail
                            ]);
                        } else {
                            try {
                                $saved = $obj5->save();

                                // Log the save result for debugging
                                Log::info('EmailLog save attempt', [
                                    'saved' => $saved,
                                    'from_mail' => $obj5->from_mail,
                                    'to_mail' => $obj5->to_mail,
                                    'client_id' => $obj5->client_id,
                                    'client_matter_id' => $obj5->client_matter_id
                                ]);

                                if (!$saved) {
                                    Log::error('Failed to save EmailLog', [
                                        'data' => $obj5->toArray()
                                    ]);
                                } elseif (!empty($request->checklistfile)) {
                                    // Visa sheet integration: update reference table when checklist sent (TR, Visitor, Student, PR, Employer Sponsored)
                                    $clientMatter = \App\Models\ClientMatter::with('matter')->find($document->client_matter_id);
                                    if ($clientMatter) {
                                        $clientMatter->recordChecklistSent(Auth::guard('admin')->id());
                                    }
                                }
                            } catch (\Exception $e) {
                                Log::error('Exception while saving EmailLog', [
                                    'error' => $e->getMessage(),
                                    'trace' => $e->getTraceAsString(),
                                    'data' => $obj5->toArray()
                                ]);
                            }
                        }
                    }
                }
                else
                {
                    $fromAddress = config('mail.from.address');
                    $fromName = config('mail.from.name', 'Bansal Migration');
                    $emailSignature = '';
                    $activeEmail = Email::where('status', true)->orderBy('id')->first();
                    if ($activeEmail) {
                        $fromAddress = $activeEmail->email ?: $fromAddress;
                        $fromName = $activeEmail->display_name ?: $fromName;
                        $emailSignature = $activeEmail->email_signature ?: '';
                    }

                    try {
                        Mail::mailer('sendgrid')->send(
                            'emails.sign_document_email',
                            [
                                'signingUrl' => $signingUrl,
                                'firstName' => $signerName,
                                'emailSignature' => $emailSignature
                            ],
                            function ($message) use ($signerEmail, $signerName, $fromAddress, $fromName) {
                                $message->to($signerEmail, $signerName)
                                    ->subject('Bansal Migration Requesting To Sign Your Document')
                                    ->from($fromAddress, $fromName);
                            }
                        );
                        
                        // Create activity note for successful email
                        \App\Models\SignatureActivity::create([
                            'document_id' => $document->id,
                            'created_by' => Auth::guard('admin')->id() ?? 1,
                            'action_type' => 'email_sent',
                            'note' => "Email sent successfully to {$signerName} ({$signerEmail})",
                            'metadata' => [
                                'signer_email' => $signerEmail,
                                'signer_name' => $signerName,
                                'status' => 'sent_via_sendgrid',
                            ]
                        ]);
                    } catch (\Exception $emailException) {
                        // Create activity note for failed email
                        \App\Models\SignatureActivity::create([
                            'document_id' => $document->id,
                            'created_by' => Auth::guard('admin')->id() ?? 1,
                            'action_type' => 'email_failed',
                            'note' => "Failed to send email to {$signerName} ({$signerEmail}): {$emailException->getMessage()}",
                            'metadata' => [
                                'signer_email' => $signerEmail,
                                'signer_name' => $signerName,
                                'error' => $emailException->getMessage(),
                            ]
                        ]);
                        throw $emailException;
                    }
                }
            } catch (\Exception $e) {
                Log::error('Mail sending failed: ' . $e->getMessage());
            }

            Log::info('Signing link sent', [
                'document_id' => $document->id,
                'signer_email' => $signerEmail,
                'user_id' => auth('admin')->id()
            ]);

            return redirect()->route('signatures.show', $document->id)->with('success', 'Signing link sent successfully!');
        } catch (\Exception $e) {
            return $this->handleError(
                $e,
                'sending_signing_link',
                'An error occurred while sending the signing link.',
                'back',
                ['document_id' => $documentId, 'signer_email' => $signerEmail ?? 'unknown']
            );
        }
    }

    public function showSignForm($id)
    {
        $pdfPath = null;
        try {
            $document = Document::findOrFail($id);
            $this->authorizeDocumentAssociatedAccess($document);
            $signer = $document->signers()->where('user_id', auth('admin')->id())->first();

            if (!$signer || $signer->status === 'signed') {
                return redirect('/')->with('error', 'Invalid or expired signing link.');
            }

            if (!$signer->opened_at) {
                $signer->update(['opened_at' => now()]);
            }

            $signatureFields = $document->signatureFields()->get();
            
            // Check if URL is a full S3 URL or local path
            $url = $document->myfile;
            
            if ($url && filter_var($url, FILTER_VALIDATE_URL) && strpos($url, 's3') !== false) {
                // This is an S3 URL - extract the key
                $s3Key = null; // Initialize here
                $parsed = parse_url($url);
                if (isset($parsed['path'])) {
                    $s3Key = ltrim(urldecode($parsed['path']), '/');
                }
                
                if (!$s3Key || !Storage::disk('s3')->exists($s3Key)) {
                    Log::error('PDF file not found in S3 for document: ' . $id, [
                        'url' => $url,
                        's3Key' => $s3Key,
                        's3_exists' => $s3Key ? Storage::disk('s3')->exists($s3Key) : 'no_path'
                    ]);
                    return redirect('/')->with('error', 'Document file not found.');
                }

                // Download PDF from S3 to a temp file
                $pdfPath = storage_path('app/tmp_' . uniqid() . '.pdf');
                $pdfStream = Storage::disk('s3')->get($s3Key);
                file_put_contents($pdfPath, $pdfStream);
                Log::info('Downloaded S3 file for document sign form', ['s3Key' => $s3Key, 'tempPath' => $pdfPath]);
            } elseif ($url && file_exists(storage_path('app/public/' . $url))) {
                // This is a local file path and file exists
                $pdfPath = storage_path('app/public/' . $url);
                Log::info('Using local file for document sign form', ['path' => $pdfPath]);
            } else {
                // Try to get from media library (legacy support)
                $pdfPath = $document->getFirstMediaPath('documents');
                
                if (!$pdfPath || !file_exists($pdfPath)) {
                    Log::error('PDF file not found for document: ' . $id, [
                        'url' => $url,
                        'local_exists' => $url ? file_exists(storage_path('app/public/' . $url)) : false,
                        'media_path' => $pdfPath
                    ]);
                    return redirect('/')->with('error', 'Document file not found.');
                }
            }

            // Use the improved PDF page counting method
            $pdfPages = $this->countPdfPages($pdfPath) ?: 1;

            return view('crm.documents.sign', compact('document', 'signer', 'signatureFields', 'pdfPages'));
        } catch (\Exception $e) {
            return $this->handleError(
                $e,
                'show_sign_form',
                'An error occurred while loading the document.',
                'dashboard',
                ['document_id' => $id]
            );
        } finally {
            TempFileHelper::delete($pdfPath);
        }
    }

    public function getPage($id, $page)
    {
        // Log the request for debugging
        Log::info('getPage method called', [
            'document_id' => $id,
            'page' => $page,
            'user_id' => auth('admin')->id(),
            'authenticated' => auth('admin')->check()
        ]);
        
        // Use unified Python Service
        $pythonService = app(\App\Services\PythonService::class);
        $tmpPdfPath = null;
        $isLocalFile = false;
        
        try {
            $document = Document::findOrFail($id);
            $this->authorizeDocumentAssociatedAccess($document);
            $url = $document->myfile;
            $pdfPath = null;

            // Check if URL is a full S3 URL or local path
            if ($url && filter_var($url, FILTER_VALIDATE_URL) && strpos($url, 's3') !== false) {
                // This is an S3 URL - extract the key
                $s3Key = null; // Initialize here
                $parsed = parse_url($url);
                if (isset($parsed['path'])) {
                    $s3Key = ltrim(urldecode($parsed['path']), '/');
                }
                
                if (!$s3Key || !Storage::disk('s3')->exists($s3Key)) {
                    Log::error('PDF file not found in S3 for document: ' . $id, [
                        'document_id' => $id,
                        's3Key' => $s3Key,
                        'myfile' => $url,
                        's3_exists' => $s3Key ? Storage::disk('s3')->exists($s3Key) : 'no_path'
                    ]);
                    abort(404, 'Document file not found');
                }

                // Download PDF from S3 to a temp file
                $tmpPdfPath = storage_path('app/tmp_' . uniqid() . '.pdf');
                $pdfStream = Storage::disk('s3')->get($s3Key);
                file_put_contents($tmpPdfPath, $pdfStream);
                Log::info('Downloaded S3 file for document page', ['s3Key' => $s3Key, 'tempPath' => $tmpPdfPath]);
            } elseif ($url && file_exists(storage_path('app/public/' . $url))) {
                // This is a local file path and file exists
                $tmpPdfPath = storage_path('app/public/' . $url);
                $isLocalFile = true;
                Log::info('Using local file for document page', ['path' => $tmpPdfPath]);
            } else {
                // Try to build S3 key from DB fields as fallback
                if (!empty($document->myfile_key) && !empty($document->doc_type) && !empty($document->client_id)) {
                    $admin = DB::table('admins')->select('client_id')->where('id', $document->client_id)->first();
                    if ($admin && $admin->client_id) {
                        $s3Key = $admin->client_id . '/' . $document->doc_type . '/' . $document->myfile_key;
                        
                        if (Storage::disk('s3')->exists($s3Key)) {
                            // Download PDF from S3 to a temp file
                            $tmpPdfPath = storage_path('app/tmp_' . uniqid() . '.pdf');
                            $pdfStream = Storage::disk('s3')->get($s3Key);
                            file_put_contents($tmpPdfPath, $pdfStream);
                            Log::info('Downloaded S3 file via fallback for document page', ['s3Key' => $s3Key, 'tempPath' => $tmpPdfPath]);
                        } else {
                            Log::error('PDF file not found in S3 fallback for document: ' . $id, [
                                'document_id' => $id,
                                's3Key' => $s3Key,
                                'myfile' => $url,
                                'myfile_key' => $document->myfile_key,
                                'doc_type' => $document->doc_type,
                                'client_id' => $document->client_id,
                                'local_exists' => $url ? file_exists(storage_path('app/public/' . $url)) : false
                            ]);
                            abort(404, 'Document file not found');
                        }
                    } else {
                        Log::error('PDF file not found - no valid storage method for document: ' . $id, [
                            'document_id' => $id,
                            'myfile' => $url,
                            'myfile_key' => $document->myfile_key,
                            'doc_type' => $document->doc_type,
                            'client_id' => $document->client_id,
                            'local_exists' => $url ? file_exists(storage_path('app/public/' . $url)) : false
                        ]);
                        abort(404, 'Document file not found');
                    }
                } else {
                    Log::error('PDF file not found - no storage information for document: ' . $id, [
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

            // Use Python service exclusively
            if (!$pythonService->isHealthy()) {
                Log::error('Python PDF service unavailable', [
                    'document_id' => $id,
                    'page' => $page
                ]);
                
                return response()->json([
                    'error' => 'PDF processing service unavailable',
                    'message' => 'Unable to generate page preview. Please try again later.',
                    'document_id' => $id,
                    'page' => $page
                ], 503);
            }
            
            $result = $pythonService->convertPageToImage($tmpPdfPath, $page, 150);
            
            if ($result && ($result['success'] ?? false)) {
                // Clear any output buffers to prevent image corruption
                if (ob_get_level()) {
                    ob_end_clean();
                }
                
                // Decode base64 and return
                $imageData = base64_decode(explode(',', $result['image_data'])[1]);
                
                return response($imageData, 200, [
                    'Content-Type' => 'image/png',
                    'Content-Length' => strlen($imageData),
                    'Cache-Control' => 'public, max-age=3600',
                ]);
            }
            
            Log::error('Python PDF service failed to convert page', [
                'document_id' => $id,
                'page' => $page,
                'result' => $result
            ]);
            
            return response()->json([
                'error' => 'Failed to generate page preview',
                'message' => 'PDF processing failed. Please try again later.',
                'document_id' => $id,
                'page' => $page
            ], 503);
        } catch (\Exception $e) {
            Log::error('Error in getPage', [
                'context' => 'get_page',
                'document_id' => $id,
                'page' => $page,
                'error' => $e->getMessage(),
                'user_id' => auth('admin')->id()
            ]);
            abort(500, 'An error occurred while retrieving the page');
        } finally {
            TempFileHelper::delete($tmpPdfPath);
        }
    }

    /**
     * LEGACY METHOD - COMMENTED OUT
     * This method is no longer used. All signature submissions now go through
     * PublicDocumentController::submitSignatures() which handles both public and admin signing.
     * 
     * Route for this method is commented out in routes/documents.php (line 227-228)
     * This method also has a broken redirect to route('documents.thankyou') which doesn't exist.
     * 
     * @deprecated Use PublicDocumentController::submitSignatures() instead
     */
    /*
    public function submitSignatures(Request $request, $id)
    {
        // Input validation - Critical security fix
        Log::debug('submitSignatures: incoming request', [
            'request_all' => $request->all(),
            'signatures_type' => gettype($request->signatures),
            'signature_positions_type' => gettype($request->signature_positions)
        ]);
        $request->validate([
            'signer_id' => 'required|integer|exists:signers,id',
            'signatures' => 'required|array',
            'signatures.*' => 'nullable|string',
            'signature_positions' => 'required|array',
            'signature_positions.*' => 'nullable|string'
        ]);

        // Sanitize and validate the document ID
        $documentId = (int) $id;
        if ($documentId <= 0) {
            Log::warning('Invalid document ID provided', ['id' => $id]);
            return redirect('/')->with('error', 'Invalid document ID.');
        }

        try {
            $document = Document::findOrFail($documentId);
            $signer = Signer::findOrFail($request->signer_id);

            // Verify signer belongs to this document
            if ($signer->document_id !== $document->id) {
                Log::warning('Signer does not belong to document', [
                    'signer_id' => $signer->id,
                    'document_id' => $document->id,
                    'signer_document_id' => $signer->document_id
                ]);
                return redirect()->route('signatures.show', $document->id)->with('error', 'Invalid signing attempt.');
            }

            Log::info("Starting signature submission", [
                'document_id' => $id,
                'signer_id' => $signer->id,
                'signer_status' => $signer->status,
                'signer_token' => $signer->token
            ]);

            if ($signer->token !== null && $signer->status === 'pending') {
                // S3 path setup
                $clientId = null;
                if ($document->client_id) {
                    $admin = DB::table('admins')->select('client_id')->where('id', $document->client_id)->first();
                    if ($admin && $admin->client_id) {
                        $clientId = $admin->client_id;
                    }
                }
                $docType = $document->doc_type ?? '';
                $myfileKey = $document->myfile_key ?? '';
                $s3Key = $clientId && $docType && $myfileKey ? ($clientId . '/' . $docType . '/' . $myfileKey) : null;

                $tmpPdfPath = storage_path('app/tmp_' . uniqid() . '.pdf');
                $pdfStream = Storage::disk('s3')->get($s3Key);
                file_put_contents($tmpPdfPath, $pdfStream);
                $outputTmpPath = storage_path('app/tmp_' . uniqid() . '_signed.pdf');

                Log::info("PDF paths", [
                    'input' => $tmpPdfPath,
                    'output' => $outputTmpPath
                ]);

                // Process signatures with their positions - Enhanced security
                $signaturesSaved = false;
                $signaturePositions = [];
                $signatureLinks = [];
                $maxSignatures = 50; // Limit number of signatures to prevent DoS
                $processedCount = 0;
                $errorMessages = [];

                Log::debug('submitSignatures: signatures array', [
                    'signatures' => $request->signatures,
                    'signature_positions' => $request->signature_positions
                ]);

                foreach ($request->signatures as $page => $signaturesJson) {
                    // Validate page number
                    $pageNum = (int) $page;
                    if ($pageNum < 1 || $pageNum > 999) { // Reasonable page limit
                        Log::warning('Invalid page number provided', ['page' => $page]);
                        $errorMessages[] = "Invalid page number: $page";
                        continue;
                    }

                    if ($signaturesJson && $processedCount < $maxSignatures) {
                        // Validate JSON and decode safely
                        if (!is_string($signaturesJson) || strlen($signaturesJson) > 100000) { // 100KB limit
                            Log::warning('Invalid signatures JSON data', ['page' => $page]);
                            $errorMessages[] = "Signature data for page $page is too large or invalid.";
                            continue;
                        }

                        $signatures = json_decode($signaturesJson, true, 10); // Depth limit of 10
                        $positions = json_decode($request->signature_positions[$page] ?? '{}', true, 10);

                        Log::debug('submitSignatures: decoded signatures and positions', [
                            'page' => $page,
                            'signatures' => $signatures,
                            'positions' => $positions,
                            'json_error' => json_last_error_msg()
                        ]);

                        // Validate JSON decode was successful
                        if (json_last_error() !== JSON_ERROR_NONE || !is_array($signatures)) {
                            Log::warning('Failed to decode signatures JSON', [
                                'page' => $page,
                                'json_error' => json_last_error_msg()
                            ]);
                            $errorMessages[] = "Failed to decode signature data for page $page.";
                            continue;
                        }

                        if (!is_array($positions)) {
                            $positions = [];
                        }

                        foreach ($signatures as $fieldId => $signatureData) {
                            if ($processedCount >= $maxSignatures) {
                                break 2; // Break out of both loops
                            }

                            // Validate field ID
                            $sanitizedFieldId = (int) $fieldId;
                            if ($sanitizedFieldId <= 0) {
                                Log::warning('Invalid field ID', ['fieldId' => $fieldId]);
                                $errorMessages[] = "Invalid field ID: $fieldId.";
                                continue;
                            }

                            // Use our enhanced signature data sanitization
                            $sanitizedSignature = $this->sanitizeSignatureData($signatureData, $sanitizedFieldId);
                            if ($sanitizedSignature === false) {
                                $errorMessages[] = "Signature for field $fieldId is invalid or corrupted. Please re-sign.";
                                Log::warning('Signature sanitization failed', ['fieldId' => $fieldId, 'signatureData' => $signatureData]);
                                continue; // Sanitization failed, skip this signature
                            }

                            $imageData = $sanitizedSignature['imageData'];
                            $base64Data = $sanitizedSignature['base64Data'];

                            // S3 signature path
                            $filename = sprintf(
                                '%d_field_%d_%s.png',
                                $signer->id,
                                $sanitizedFieldId,
                                bin2hex(random_bytes(8)) // Add random component
                            );
                            $s3SignaturePath = $clientId . '/' . $docType . '/signatures/' . $filename;
                            // Upload to S3
                            Storage::disk('s3')->put($s3SignaturePath, $imageData);
                            $s3SignatureUrl = Storage::disk('s3')->url($s3SignaturePath);

                            // Use our enhanced position data sanitization
                            $position = $positions[$fieldId] ?? [];
                            $sanitizedPosition = $this->sanitizePositionData($position);

                            $signaturePositions[$sanitizedFieldId] = [
                                'path' => $s3SignaturePath, // S3 path for reference
                                'page' => $pageNum,
                                'x_percent' => $sanitizedPosition['x_percent'],
                                'y_percent' => $sanitizedPosition['y_percent'],
                                'w_percent' => $sanitizedPosition['w_percent'],
                                'h_percent' => $sanitizedPosition['h_percent']
                            ];
                            $signatureLinks[$sanitizedFieldId] = $s3SignatureUrl;

                            Log::info("Saved signature to S3", [
                                'field_id' => $sanitizedFieldId,
                                's3_path' => $s3SignaturePath,
                                's3_url' => $s3SignatureUrl,
                                'size_bytes' => strlen($imageData),
                                'position' => $sanitizedPosition
                            ]);

                            $signaturesSaved = true;
                            $processedCount++;
                        }
                    }
                }

                if (!$signaturesSaved) {
                    $errorMsg = !empty($errorMessages) ? implode(' ', $errorMessages) : "No signatures provided. Please draw signatures before submitting.";
                    Log::warning("No valid signatures provided", ['errorMessages' => $errorMessages]);
                    return redirect('/')->with('error', $errorMsg);
                }

                // Initialize FPDI with TCPDF
                $pdf = new \setasign\Fpdi\TcpdfFpdi('P', 'mm', 'A4', true, 'UTF-8', false);
                $pdf->SetAutoPageBreak(false);

                // Load source PDF
                if (!file_exists($tmpPdfPath)) {
                    Log::error("Source PDF not found", ['path' => $tmpPdfPath]);
                    return redirect('/')->with('error', 'Source PDF not found.');
                }
                $pageCount = $pdf->setSourceFile($tmpPdfPath);
                Log::info("PDF loaded", ['page_count' => $pageCount]);

                // Process each page (unchanged)
                for ($page = 1; $page <= $pageCount; $page++) {
                    Log::info("Processing page {$page}");
                    // Import page
                    $tplIdx = $pdf->importPage($page);
                    $specs = $pdf->getTemplateSize($tplIdx);
                    Log::info("Page {$page} dimensions", [
                        'width_mm' => $specs['width'],
                        'height_mm' => $specs['height'],
                        'orientation' => $specs['orientation']
                    ]);
                    // Add page with matching dimensions
                    $pdf->AddPage($specs['orientation'], [$specs['width'], $specs['height']]);
                    $pdf->useTemplate($tplIdx, 0, 0, $specs['width'], $specs['height']);
                    // Get signature fields for this page
                    $fields = $document->signatureFields()->where('page_number', $page)->get();
                    Log::info("Found {$fields->count()} signature fields for page {$page}", [
                        'field_ids' => $fields->pluck('id')->toArray(),
                        'signaturePositions_keys' => array_keys($signaturePositions)
                    ]);
                    foreach ($fields as $field) {
                        if (isset($signaturePositions[$field->id])) {
                            $signatureInfo = $signaturePositions[$field->id];
                            $s3SignaturePath = $signatureInfo['path'];
                            $x_percent = $signatureInfo['x_percent'] ?? 0;
                            $y_percent = $signatureInfo['y_percent'] ?? 0;
                            $w_percent = $signatureInfo['w_percent'] ?? 0;
                            $h_percent = $signatureInfo['h_percent'] ?? 0;
                            $pdfWidth = $specs['width'];
                            $pdfHeight = $specs['height'];
                            $x_mm = $x_percent * $pdfWidth;
                            $w_mm = max(15, $w_percent * $pdfWidth);
                            $h_mm = max(15, $h_percent * $pdfHeight);
                            $y_mm = max(0, min($y_percent * $pdfHeight, $pdfHeight - $h_mm));
                            // Download signature from S3 to temp file for PDF overlay
                            $tmpSignaturePath = storage_path('app/tmp_signature_' . uniqid() . '.png');
                            $s3Image = Storage::disk('s3')->get($s3SignaturePath);
                            file_put_contents($tmpSignaturePath, $s3Image);
                            if (file_exists($tmpSignaturePath)) {
                                $pdf->Image($tmpSignaturePath, $x_mm, $y_mm, $w_mm, $h_mm, 'PNG');
                                @unlink($tmpSignaturePath);
                            }
                        } else {
                            Log::warning("No signature data for field", ['field_id' => $field->id]);
                        }
                    }
                }

                // Save the final PDF to a temp file
                try {
                    Log::info("Saving PDF", ['path' => $outputTmpPath]);
                    $pdf->Output($outputTmpPath, 'F');
                    $pdfSaved = file_exists($outputTmpPath) ? filesize($outputTmpPath) : 0;
                    Log::info("PDF saved", [
                        'path' => $outputTmpPath,
                        'size_bytes' => $pdfSaved
                    ]);
                    if (!$pdfSaved) {
                        return redirect('/')->with('error', 'Failed to save the signed PDF. Please contact support.');
                    }
                } catch (\Exception $e) {
                    Log::error("Error saving PDF", ['error' => $e->getMessage()]);
                    return redirect('/')->with('error', 'Error saving signed PDF: ' . $e->getMessage());
                }

                // Upload signed PDF to S3
                $s3SignedPath = $clientId . '/' . $docType . '/signed/' . $document->id . '_signed.pdf';
                Storage::disk('s3')->put($s3SignedPath, fopen($outputTmpPath, 'r'));
                $s3SignedUrl = Storage::disk('s3')->url($s3SignedPath);

                // Clean up temp files
                @unlink($tmpPdfPath);
                @unlink($outputTmpPath);

                // Update statuses and document links
                $signer->update(['status' => 'signed', 'signed_at' => now()]);

                $document->status = 'signed';
                $document->signature_doc_link = json_encode($signatureLinks);
                $document->signed_doc_link = $s3SignedUrl ?? null;
                $docSigned = $document->save();

                if( $docSigned && $document->doc_type == 'agreement'){
                    $clientMatterInfo = \App\Models\ClientMatter::select('client_unique_matter_no','sel_person_responsible')->where('id', $document->client_matter_id)->first();
                    if(isset($clientMatterInfo->sel_person_responsible) && $clientMatterInfo->sel_person_responsible != ''){
                        $docSignerInfo = \App\Models\Admin::select('first_name','last_name','client_id')->where('id', $document->client_id)->first();
                        if( $docSignerInfo){
                            $docSignerFullName = $docSignerInfo->first_name.' '.$docSignerInfo->last_name;
                            $docSignerClientId = $docSignerInfo->client_id;
                        } else {
                            $docSignerFullName = 'NA';
                            $docSignerClientId = 'NA';
                        }
                        $clientMatterReference = $docSignerClientId.'-'.$clientMatterInfo->client_unique_matter_no;
                        $signedDocName = $document->getFilenameWithExtensionForDisplay();
                        $subject = $docSignerFullName.' signed cost agreement for matter ref no - '.$clientMatterReference.' at document '.$signedDocName;
                        $objs = new ActivitiesLog;
                        $objs->client_id = $document->client_id;
                        $objs->created_by = $clientMatterInfo->sel_person_responsible;
                        $objs->description = '';
                        $objs->subject = $subject;
                        $objs->activity_type = 'document';
                        $objs->task_status = 0;
                        $objs->pin = 0;
                        $objsupd = $objs->save();
                        if($objsupd){
                            //update client matter table
                            \App\Models\ClientMatter::where('id', $document->client_matter_id)->update(['updated_at_type' => 'signed','updated_at' => now()]);
                        }
                    }
                }

                Log::info("Document and signer status updated, S3 links saved", [
                    'signature_doc_link' => $signatureLinks,
                    'signed_doc_link' => $s3SignedUrl
                ]);

                // Redirect to thank you page with document ID
                return redirect()->route('documents.thankyou', ['id' => $document->id]);
            }

            Log::warning("Invalid signing attempt", [
                'signer_status' => $signer->status,
                'has_token' => $signer->token !== null
            ]);
            return redirect('/')->with('error', 'Invalid signing attempt.');
        } catch (\Exception $e) {
            Log::error("Unexpected error in submitSignatures", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return redirect('/')->with('error', 'An unexpected error occurred: ' . $e->getMessage());
        }
    }
    */

    public function downloadSigned($id)
    {
        try {
            $document = Document::findOrFail($id);
            $this->authorizeDocumentAssociatedAccess($document);

            if (! $document->signed_doc_link) {
                abort(400, 'Missing signed document link');
            }

            $filename = str_replace('"', "'", $document->getSignedDownloadFilename());

            Log::info('Download signed document attempt', [
                'document_id' => $id,
                'signed_doc_link' => $document->signed_doc_link,
            ]);

            $location = SignedDocumentS3PathResolver::locateSignedPdfFile($document);

            if ($location === null) {
                Log::error('Signed document file not found', [
                    'document_id' => $id,
                    'signed_doc_link' => $document->signed_doc_link,
                ]);
                abort(404, 'Signed document file not found');
            }

            if ($location['disk'] === 'local') {
                return response()->download($location['path'], $filename, [
                    'Content-Type' => 'application/pdf',
                ]);
            }

            /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
            $disk = Storage::disk('s3');
            $s3Key = $location['key'];

            try {
                $tempUrl = $disk->temporaryUrl(
                    $s3Key,
                    now()->addMinutes(5),
                    ['ResponseContentDisposition' => 'attachment; filename="' . $filename . '"']
                );

                Log::info('S3 temporary URL generated for signed download', [
                    'document_id' => $id,
                    's3_key' => $s3Key,
                ]);

                return redirect($tempUrl);
            } catch (\Throwable $e) {
                Log::warning('S3 temporaryUrl failed, streaming signed PDF through app', [
                    'document_id' => $id,
                    's3_key' => $s3Key,
                    'error' => $e->getMessage(),
                ]);

                return response($disk->get($s3Key), 200, [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                    'Content-Length' => $disk->size($s3Key),
                    'Cache-Control' => 'private, no-cache',
                ]);
            }
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            throw $e;
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            throw $e;
        } catch (\Illuminate\Http\Exceptions\HttpResponseException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Error downloading signed document', [
                'document_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            abort(500, 'Error generating download link');
        }
    }

    /**
     * Inline preview of signed PDF with descriptive filename (same as download). Streams through app so URL and viewer show correct name.
     */
    public function previewSigned($id)
    {
        try {
            $document = Document::findOrFail($id);
            $this->authorizeDocumentAssociatedAccess($document);
            $fileUrl = $document->signed_doc_link;
            if (! $fileUrl && str_ends_with((string) ($document->checklist ?? ''), '_signed')) {
                $fileUrl = $document->myfile;
            }
            if (! $fileUrl) {
                abort(404, 'Signed document not available for preview.');
            }

            $filename = str_replace('"', "'", $document->getSignedDownloadFilename());
            $location = SignedDocumentS3PathResolver::locateSignedPdfFile($document, $fileUrl);

            if ($location === null) {
                return redirect()->away($fileUrl);
            }

            if ($location['disk'] === 'local') {
                return response()->file($location['path'], [
                    'Content-Disposition' => 'inline; filename="' . $filename . '"',
                ]);
            }

            $disk = Storage::disk('s3');

            return response($disk->get($location['key']), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="' . $filename . '"',
                'Content-Length' => $disk->size($location['key']),
                'Cache-Control' => 'private, max-age=300',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            throw $e;
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            throw $e;
        } catch (\Illuminate\Http\Exceptions\HttpResponseException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('previewSigned failed', ['id' => $id, 'error' => $e->getMessage()]);

            abort(500, 'Unable to preview signed document.');
        }
    }

    public function downloadSignedAndThankyou($id)
    {
        try {
            $document = \App\Models\Document::findOrFail($id);
            $this->authorizeDocumentAssociatedAccess($document);
            if ($document->signed_doc_link) {
                $signedDocUrl = $document->signed_doc_link;
                $downloadUrl = null;
                $location = SignedDocumentS3PathResolver::locateSignedPdfFile($document);

                if ($location !== null) {
                    if ($location['disk'] === 'local') {
                        $downloadUrl = route('documents.download.signed', $document->id);
                    } else {
                        try {
                            /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
                            $disk = Storage::disk('s3');
                            $dlName = str_replace('"', "'", $document->getSignedDownloadFilename());
                            $downloadUrl = $disk->temporaryUrl(
                                $location['key'],
                                now()->addMinutes(5),
                                [
                                    'ResponseContentDisposition' => 'attachment; filename="' . $dlName . '"',
                                ]
                            );
                        } catch (\Throwable $e) {
                            $downloadUrl = route('documents.download.signed', $document->id);
                        }
                    }
                }

                if ($downloadUrl) {
                    return view('crm.documents.download_and_thankyou', [
                        'downloadUrl' => $downloadUrl,
                        'thankyouUrl' => route('public.documents.thankyou', ['id' => $id]),
                    ]);
                }

                return view('crm.documents.download_and_thankyou', [
                    'downloadUrl' => $signedDocUrl,
                    'thankyouUrl' => route('public.documents.thankyou', ['id' => $id]),
                ]);
            }
            return redirect()->back()->with('error', 'Signed document not found.');
        } catch (\Exception $e) {
            return $this->handleError(
                $e,
                'download_signed_document_and_thankyou',
                'An error occurred while downloading the document.',
                'back',
                ['document_id' => $id]
            );
        }
    }

    public function thankyou(Request $request, $id = null)
    {
        $downloadUrl = null;
        if ($id) {
            $document = \App\Models\Document::find($id);
            if ($document) {
                $this->authorizeDocumentAssociatedAccess($document);
            }
            if ($document && $document->signed_doc_link) {
                $parsed = parse_url($document->signed_doc_link);
                if (isset($parsed['path'])) {
                    $s3Key = ltrim($parsed['path'], '/');
                    /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
                    $disk = Storage::disk('s3');
                    if ($disk->exists($s3Key)) {
                        $dlName = str_replace('"', "'", $document->getSignedDownloadFilename());
                        $downloadUrl = $disk->temporaryUrl(
                            $s3Key,
                            now()->addMinutes(5),
                            [
                                'ResponseContentDisposition' => 'attachment; filename="' . $dlName . '"',
                            ]
                        );
                    }
                }
            }
        }
        $message = 'You have successfully signed your document.';
        return view('thanks', compact('downloadUrl', 'message','id'));
    }

    public function sendReminder(Request $request, $id)
    {
        // Sanitize and validate input
        $documentId = (int) $id;
        if ($documentId <= 0) {
            return redirect()->back()->with('error', 'Invalid document ID.');
        }

        // Validate signer_id input
        $request->validate([
            'signer_id' => 'required|integer|exists:signers,id'
        ]);

        $signerId = (int) $request->signer_id;
        if ($signerId <= 0) {
            return redirect()->back()->with('error', 'Invalid signer ID.');
        }

        try {
            $document = \App\Models\Document::findOrFail($documentId); //dd($document);
            $this->authorizeDocumentAssociatedAccess($document);
            $signer = $document->signers()->findOrFail($signerId);

            // Verify signer belongs to this document
            if ($signer->document_id !== $document->id) {
                Log::warning('Attempt to send reminder for mismatched signer', [
                    'document_id' => $document->id,
                    'signer_id' => $signer->id,
                    'signer_document_id' => $signer->document_id
                ]);
                return redirect()->back()->with('error', 'Invalid signer for this document.');
            }

        if ($signer->status === 'signed') {
            return redirect()->back()->with('error', 'Document is already signed.');
        }

        // Check if we can send a reminder (limit to 3 reminders, 24 hours apart)
        if ($signer->reminder_count >= 3) {
            return redirect()->back()->with('error', 'Maximum reminders already sent.');
        }

        // Send reminder email
        $signingUrl = url("/sign/{$document->id}/{$signer->token}");
        Mail::raw("This is a reminder to sign your document: " . $signingUrl . "\n\nConsumer guide: https://www.mara.gov.au/get-help-visa-subsite/FIles/consumer_guide_english.pdf", function ($message) use ($signer) {
            $message->to($signer->email, $signer->name)
                    ->subject('Reminder: Please Sign Your Document');
        });

        // Update reminder tracking
        $signer->update([
            'last_reminder_sent_at' => now(),
            'reminder_count' => $signer->reminder_count + 1
        ]);

        return redirect()->back()->with('success', 'Reminder sent successfully!');
        } catch (\Exception $e) {
            return $this->handleError(
                $e,
                'send_reminder',
                'An error occurred while sending the reminder.',
                'back',
                ['document_id' => $documentId, 'signer_id' => $signerId ?? 'unknown']
            );
        }
    }

    /**
     * Show the sign form for a document using a tokenized link.
     */
    /*public function sign($id, $token)
    { dd($id);
        // Sanitize and validate inputs
        $documentId = (int) $id;
        if ($documentId <= 0) {
            Log::warning('Invalid document ID in sign method', ['id' => $id]);
            return redirect()->route('signatures.index')->with('error', 'Invalid document link.');
        }

        // Validate token format
        if (!$token || !is_string($token) || strlen($token) < 32 || !preg_match('/^[a-zA-Z0-9]+$/', $token)) {
            Log::warning('Invalid token format in sign method', ['token_length' => strlen($token ?? '')]);
            return redirect()->route('signatures.show', $documentId)->with('error', 'Invalid or expired signing link.');
        }

        try {
            $document = Document::findOrFail($documentId);  //dd($document);
            $this->authorizeDocumentAssociatedAccess($document);
            if( isset($document->doc_type) && $document->doc_type == 'agreement')
            {
                $isDocumentExistInSignerTbl = $document->signers()->where('document_id', $documentId )->first(); //dd($isDocumentExistInSignerTbl);
                if($isDocumentExistInSignerTbl)
                {
                    // Only update token/status when still pending - never overwrite 'signed' or 'cancelled'
                    if ($isDocumentExistInSignerTbl->status === 'pending') {
                        $isDocumentExistInSignerTbl->update(['token' => $token,'status' => 'pending']);
                        $signer = $document->signers()->where('token', $token)->first();
                    } else {
                        $signer = $isDocumentExistInSignerTbl;
                    }
                }
                else
                {
                    // Insert document in signer table
                    $signer = $document->signers()->create([
                        'token' => $token,
                        'status' => 'pending',
                        'reminder_count' => 0, // PostgreSQL NOT NULL constraint - must set this field
                    ]);
                }
            } else {
                $signer = $document->signers()->where('token', $token)->first();
            }
            //dd($signer);

            if (!$signer || $signer->status === 'signed') {
                Log::warning('Invalid signer or already signed', [
                    'document_id' => $documentId,
                    'signer_exists' => !is_null($signer),
                    'signer_status' => $signer ? $signer->status : 'none'
                ]);
                return redirect()->route('signatures.show', $documentId)->with('error', 'Invalid or expired signing link.');
            }

            // Check token expiration (optional - add expiration logic)
            // if ($signer->created_at->addHours(72)->isPast()) {
            //     return redirect('/')->with('error', 'Signing link has expired.');
            // }

            if (!$signer->opened_at) {
                $signer->update(['opened_at' => now()]);
            }

            $signatureFields = $document->signatureFields()->get();
            
            // Check if file exists locally first (for newly uploaded documents)
            $url = $document->myfile;
            $pdfPath = null;
            $pdfPages = 1;
            
            if ($url && file_exists(storage_path('app/public/' . $url))) {
                $pdfPath = storage_path('app/public/' . $url);
                $pdfPages = $this->countPdfPages($pdfPath) ?: 1;
                Log::info('Using local file for document sign', ['path' => $pdfPath, 'pages' => $pdfPages]);
            } else {
                // Try to extract S3 key from URL if possible
                if ($url) {
                    $parsed = parse_url($url);
                    if (isset($parsed['path'])) {
                        $pdfPath = ltrim(urldecode($parsed['path']), '/');
                    }
                }

                if (empty($pdfPath) && !empty($document->myfile_key) && !empty($document->doc_type) && !empty($document->client_id)) {
                    $admin = DB::table('admins')->select('client_id')->where('id', $document->client_id)->first();
                    if ($admin && $admin->client_id) {
                        $pdfPath = $admin->client_id . '/' . $document->doc_type . '/' . $document->myfile_key;
                    }
                }

                // Use the improved PDF page counting method
                if ($pdfPath && Storage::disk('s3')->exists($pdfPath)) {
                    $tmpPdfPath = storage_path('app/tmp_' . uniqid() . '.pdf');
                    $pdfStream = Storage::disk('s3')->get($pdfPath);
                    file_put_contents($tmpPdfPath, $pdfStream);
                    $pdfPages = $this->countPdfPages($tmpPdfPath) ?: 1;
                    @unlink($tmpPdfPath);
                }
            }

            return view('crm.documents.sign', compact('document', 'signer', 'signatureFields', 'pdfPages'));
        } catch (\Exception $e) {
            return $this->handleError(
                $e,
                'document_sign',
                'An error occurred while loading the signing page.',
                'signatures.index',
                ['document_id' => $documentId, 'token_present' => !empty($token)]
            );
        }
    }*/
}
