<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Helpers\TempFileHelper;
use App\Http\Controllers\CRM\DocumentController as AdminDocumentController;
use App\Http\Controllers\PublicDocumentController;
use App\Http\Controllers\CRM\SignatureDashboardController;
use App\Http\Controllers\CRM\DocToPdfController;

/*
|--------------------------------------------------------------------------
| Document Signature Routes
|--------------------------------------------------------------------------
|
| WORKFLOW:
| 1. Admin prepares document for signing (CRUD operations)
| 2. Admin sends signing link via email to client
| 3. Client receives email with link: /sign/{id}/{token}
| 4. Client signs document (no login - token validated)
| 5. Client sees thank you page & downloads signed document
| 6. Admin views completed document in admin panel
|
| ROUTE ORGANIZATION:
| - Admin routes: /documents/* and /signatures/* (auth:admin required)
| - Public routes: /sign/* and /documents/* (token-based validation)
|
*/

/*
|--------------------------------------------------------------------------
| PUBLIC DOCUMENT SIGNING ROUTES
|--------------------------------------------------------------------------
| No authentication required - access controlled by token validation
| Route Names: public.documents.*
|
| These routes allow clients to sign documents via email links without
| requiring login. Security is handled through unique tokens sent via email.
*/

/*---------- Public Signing Interface ----------*/
Route::get('/sign/{id}/{token}', [PublicDocumentController::class, 'sign'])
    ->name('public.documents.sign');

Route::post('/documents/{document}/sign', [PublicDocumentController::class, 'submitSignatures'])
    ->name('public.documents.submitSignatures');

/*---------- Public Document Viewing ----------*/
Route::get('/documents/{id}/page/{page}', [PublicDocumentController::class, 'getPage'])
    ->name('public.documents.page');

/*---------- Public Download & Thank You ----------*/
Route::get('/documents/{id}/download-signed', [PublicDocumentController::class, 'downloadSigned'])
    ->name('public.documents.download.signed');

Route::get('/documents/{id}/download-signed-and-thankyou', [PublicDocumentController::class, 'downloadSignedAndThankyou'])
    ->name('public.documents.download_and_thankyou');

Route::get('/documents/thankyou/{id?}', [PublicDocumentController::class, 'thankyou'])
    ->name('public.documents.thankyou');

/*---------- Public Reminder ----------*/
Route::post('/documents/{document}/send-reminder', [PublicDocumentController::class, 'sendReminder'])
    ->name('public.documents.sendReminder');

/*
|--------------------------------------------------------------------------
| ADMIN DOCUMENT MANAGEMENT ROUTES
|--------------------------------------------------------------------------
| Prefix: None (routes at root level)
| Middleware: auth:admin
| Route Names: documents.* and signatures.*
*/

Route::middleware('auth:admin')->group(function () {

/*---------- Admin Utilities (debug / converters) ----------*/
Route::get('/test-signature', function () {
    return view('test-signature');
})->name('test.signature');

Route::get('/doc-to-pdf', [DocToPdfController::class, 'showForm'])->name('doc-to-pdf.form');
Route::post('/doc-to-pdf/convert', [DocToPdfController::class, 'convertLocal'])->name('doc-to-pdf.convert');
Route::get('/doc-to-pdf/test', [DocToPdfController::class, 'testLocalConversion'])->name('doc-to-pdf.test');
Route::get('/doc-to-pdf/test-python', [DocToPdfController::class, 'testPythonConversion'])->name('doc-to-pdf.test-python');
Route::get('/doc-to-pdf/debug', [DocToPdfController::class, 'debugConfig'])->name('doc-to-pdf.debug');

// PDF page preview used by CRM signature placement UI (admin session required)
Route::get('/debug-pdf-page/{id}/{page}', function ($id, $page) {
    if (ob_get_level()) {
        ob_end_clean();
    }

    $tmpPdfPath = null;
    $isLocalFile = false;

    try {
        $document = \App\Models\Document::findOrFail($id);
        $url = $document->myfile;

        if ($url && filter_var($url, FILTER_VALIDATE_URL) && strpos($url, 's3') !== false) {
            $parsed = parse_url($url);
            $s3Key = isset($parsed['path']) ? ltrim(urldecode($parsed['path']), '/') : null;
            if ($s3Key && Storage::disk('s3')->exists($s3Key)) {
                $tmpPdfPath = storage_path('app/tmp_' . uniqid() . '.pdf');
                file_put_contents($tmpPdfPath, Storage::disk('s3')->get($s3Key));
            }
        } elseif ($url && file_exists(storage_path('app/public/' . $url))) {
            $tmpPdfPath = storage_path('app/public/' . $url);
            $isLocalFile = true;
        } else {
            if (!empty($document->myfile_key) && !empty($document->doc_type) && !empty($document->client_id)) {
                $admin = \App\Models\Admin::where('id', $document->client_id)->select('client_id')->first();
                if ($admin && $admin->client_id) {
                    $s3Key = $admin->client_id . '/' . $document->doc_type . '/' . $document->myfile_key;
                    if (Storage::disk('s3')->exists($s3Key)) {
                        $tmpPdfPath = storage_path('app/tmp_' . uniqid() . '.pdf');
                        file_put_contents($tmpPdfPath, Storage::disk('s3')->get($s3Key));
                    }
                }
            }
        }

        if ($tmpPdfPath && file_exists($tmpPdfPath)) {
            $pdfService = app(\App\Services\PythonPDFService::class);
            if ($pdfService->isHealthy()) {
                $result = $pdfService->convertPageToImage($tmpPdfPath, $page, 150);

                if ($result && ($result['success'] ?? false)) {
                    $imageData = base64_decode(explode(',', $result['image_data'])[1]);

                    return response($imageData, 200, [
                        'Content-Type' => 'image/png',
                        'Content-Length' => strlen($imageData),
                        'Cache-Control' => 'private, max-age=3600',
                    ]);
                }
            }
        }

        return response()->json(['error' => 'Failed to generate image', 'document_id' => $id, 'page' => $page], 500);
    } catch (\Exception $e) {
        Log::error('Debug route error', ['error' => $e->getMessage(), 'document_id' => $id, 'page' => $page]);
        return response()->json(['error' => $e->getMessage()], 500);
    } finally {
        if (!$isLocalFile) {
            TempFileHelper::delete($tmpPdfPath);
        }
    }
})->name('debug.pdf.page');

/*---------- Document CRUD Operations ----------*/
Route::get('/documents/create', [AdminDocumentController::class, 'create'])
    ->name('documents.create');

Route::post('/documents', [AdminDocumentController::class, 'store'])
    ->name('documents.store');

Route::get('/documents/{id}/edit', [AdminDocumentController::class, 'edit'])
    ->name('documents.edit');

Route::patch('/documents/{id}', [AdminDocumentController::class, 'update'])
    ->name('documents.update');

Route::get('/documents/{id}/signature-placement-data', [AdminDocumentController::class, 'getSignaturePlacementData'])
    ->name('documents.signature-placement-data');

/*---------- Admin Signing & Reminder Operations ----------*/
Route::post('/documents/{document}/send-reminder', [AdminDocumentController::class, 'sendReminder'])
    ->name('documents.sendReminder');

Route::post('/documents/{document}/send-signing-link', [AdminDocumentController::class, 'sendSigningLink'])
    ->name('documents.sendSigningLink');

Route::get('/documents/{document}/sign', [AdminDocumentController::class, 'showSignForm'])
    ->name('documents.showSignForm');

/*---------- Admin Document Viewing & Download ----------*/
Route::get('/documents/{id}/preview-signed', [AdminDocumentController::class, 'previewSigned'])
    ->name('documents.preview.signed');

// Distinct URIs from public download routes so names are not overwritten (G3)
Route::get('/admin/documents/{id}/download-signed', [AdminDocumentController::class, 'downloadSigned'])
    ->name('documents.download.signed');

Route::get('/admin/documents/{id}/download-signed-and-thankyou', [AdminDocumentController::class, 'downloadSignedAndThankyou'])
    ->name('documents.download_and_thankyou');

// Redirect old document index to Signature Dashboard
Route::get('/documents/{id?}', function ($id = null) {
    if ($id) {
        return redirect()->route('signatures.show', $id);
    }
    return redirect()->route('signatures.index');
})->name('documents.index')
    ->where('id', '[0-9]+');

/*---------- Signature Dashboard Routes ----------*/
Route::prefix('signatures')->group(function () {
    Route::get('/', [SignatureDashboardController::class, 'index'])->name('signatures.index');
    Route::get('/create', [SignatureDashboardController::class, 'create'])->name('signatures.create');
    Route::post('/', [SignatureDashboardController::class, 'store'])->name('signatures.store');
    Route::post('/suggest-association', [SignatureDashboardController::class, 'suggestAssociation'])->name('signatures.suggest-association');
    Route::post('/preview-email', [SignatureDashboardController::class, 'previewEmail'])->name('signatures.preview-email');

    Route::post('/bulk-archive', [SignatureDashboardController::class, 'bulkArchive'])->name('signatures.bulk-archive');
    Route::post('/bulk-void', [SignatureDashboardController::class, 'bulkVoid'])->name('signatures.bulk-void');
    Route::post('/bulk-resend', [SignatureDashboardController::class, 'bulkResend'])->name('signatures.bulk-resend');

    Route::get('/{id}', [SignatureDashboardController::class, 'show'])->name('signatures.show');
    Route::post('/{id}/reminder', [SignatureDashboardController::class, 'sendReminder'])->name('signatures.reminder');
    Route::post('/{id}/cancel', [SignatureDashboardController::class, 'cancelSignature'])->name('signatures.cancel');
    Route::post('/{id}/send', [SignatureDashboardController::class, 'sendForSignature'])->name('signatures.send');
    Route::get('/{id}/copy-link', [SignatureDashboardController::class, 'copyLink'])->name('signatures.copy-link');

    Route::post('/{id}/associate', [SignatureDashboardController::class, 'associate'])->name('signatures.associate');
    Route::get('/api/client-matters/{clientId}', [SignatureDashboardController::class, 'getClientMatters'])->name('signatures.client-matters');
    Route::get('/api/document-categories', [SignatureDashboardController::class, 'getDocumentCategories'])->name('signatures.document-categories');
    Route::get('/api/document-checklists', [SignatureDashboardController::class, 'getDocumentChecklists'])->name('signatures.document-checklists');
    Route::post('/{id}/detach', [SignatureDashboardController::class, 'detach'])->name('signatures.detach');
});

/*---------- Client Matters API ----------*/
Route::get('/clients/{id}/matters', [SignatureDashboardController::class, 'getClientMatters'])->name('clients.matters');
Route::get('/api/client-matters/{clientId}', [SignatureDashboardController::class, 'getClientMatters'])->name('api.client-matters');

}); // End of admin routes group
