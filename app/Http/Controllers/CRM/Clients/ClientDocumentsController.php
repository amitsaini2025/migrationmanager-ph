<?php

namespace App\Http\Controllers\CRM\Clients;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

use App\Models\Admin;
use App\Models\ActivitiesLog;
use App\Models\Document;
use App\Models\Form956;
use App\Models\ClientMatter;
// use App\Models\VisaDocChecklist; // REMOVED: VisaDocChecklist model has been deleted
use App\Models\PersonalDocumentType;
use App\Models\VisaDocumentType;
use App\Models\NominationDocumentType;

use App\Traits\ClientAuthorization;
use App\Traits\ClientHelpers;
use App\Traits\LogsClientActivity;
use App\Support\DocumentStoredFilename;
use App\Support\DocumentFilenameRules;
use App\Support\StaffClientVisibility;
use App\Helpers\IconHelper;
use Illuminate\Http\JsonResponse;
use mikehaertl\pdftk\Pdf;

class ClientDocumentsController extends Controller
{
    use ClientAuthorization, ClientHelpers, LogsClientActivity;

    /** Max size per file for personal/visa bulk document uploads (aligned with tab Blade JS). */
    private const BULK_UPLOAD_MAX_BYTES = 20 * 1024 * 1024;

    public function __construct()
    {
        $this->middleware('auth:admin');
    }

    /**
     * @return \Illuminate\Filesystem\FilesystemAdapter
     */
    private function s3Disk(): \Illuminate\Filesystem\FilesystemAdapter
    {
        /** @var \Illuminate\Filesystem\FilesystemAdapter */
        return Storage::disk('s3');
    }

    private function denyJsonUnlessStaffClientAccess(int $clientId): ?JsonResponse
    {
        if (! StaffClientVisibility::isRestrictedPersonAssisting(Auth::user())) {
            return null;
        }

        if ($clientId <= 0 || ! StaffClientVisibility::canAccessClientOrLead($clientId)) {
            return response()->json(StaffClientVisibility::unauthorizedPayload(), 403);
        }

        return null;
    }

    /**
     * For legacy handlers that echo JSON instead of returning JsonResponse.
     */
    private function blockEchoUnlessStaffClientAccess(int $clientId): bool
    {
        if (! StaffClientVisibility::isRestrictedPersonAssisting(Auth::user())) {
            return false;
        }

        if ($clientId <= 0 || ! StaffClientVisibility::canAccessClientOrLead($clientId)) {
            header('Content-Type: application/json');
            echo json_encode(StaffClientVisibility::unauthorizedPayload());

            return true;
        }

        return false;
    }

    /**
     * Remove not-used personal documents for a category (scoped to client + category).
     * DB rows are removed in the caller's transaction; S3 files and activity logs run after commit.
     *
     * @return array{removed_count: int, s3_cleanup: list<Document>}
     */
    private function collectNotUsedPersonalDocumentsForCategory(string $folderName, int $clientId): array
    {
        $documents = Document::query()
            ->where('folder_name', $folderName)
            ->where('doc_type', 'personal')
            ->where('client_id', $clientId)
            ->where('not_used_doc', 1)
            ->where('type', 'client')
            ->get();

        return [
            'removed_count' => $documents->count(),
            's3_cleanup' => $documents->all(),
        ];
    }

    /**
     * @param list<Document> $documents
     */
    private function finalizeDeletedPersonalDocuments(int $clientId, array $documents): void
    {
        if ($documents === []) {
            return;
        }

        $admin = Admin::query()->select('client_id')->where('id', $clientId)->first();

        foreach ($documents as $document) {
            if (!empty($document->myfile_key) && $admin && !empty($admin->client_id)) {
                try {
                    $this->s3Disk()->delete($admin->client_id.'/'.$document->doc_type.'/'.$document->myfile_key);
                } catch (\Exception $e) {
                    Log::warning('Failed to delete S3 file for not-used personal document during category deletion', [
                        'document_id' => $document->id,
                        'client_id' => $clientId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $documentName = $document->file_name ?? 'unknown';
            $matterRef = $this->getMatterReference($clientId);
            $subject = !empty($matterRef)
                ? "deleted Personal: {$documentName} - {$matterRef}"
                : "deleted Personal: {$documentName}";

            $this->logClientActivity(
                $clientId,
                $subject,
                '<p>Deleted personal document (category deleted)</p>',
                'document'
            );
        }
    }

    /**
     * @return array{removed_count: int, s3_cleanup: list<Document>}
     */
    private function collectNotUsedVisaDocumentsForCategory(string $folderName, int $clientId, ?int $clientMatterId): array
    {
        $query = Document::query()
            ->where('folder_name', $folderName)
            ->where('doc_type', 'visa')
            ->where('client_id', $clientId)
            ->where('not_used_doc', 1)
            ->where('type', 'client');

        if ($clientMatterId !== null) {
            $query->where('client_matter_id', $clientMatterId);
        }

        $documents = $query->get();

        return [
            'removed_count' => $documents->count(),
            's3_cleanup' => $documents->all(),
        ];
    }

    /**
     * @param list<Document> $documents
     */
    private function finalizeDeletedVisaDocuments(int $clientId, array $documents): void
    {
        if ($documents === []) {
            return;
        }

        $admin = Admin::query()->select('client_id')->where('id', $clientId)->first();

        foreach ($documents as $document) {
            if (!empty($document->myfile_key) && $admin && !empty($admin->client_id)) {
                try {
                    $this->s3Disk()->delete($admin->client_id.'/'.$document->doc_type.'/'.$document->myfile_key);
                } catch (\Exception $e) {
                    Log::warning('Failed to delete S3 file for not-used visa document during category deletion', [
                        'document_id' => $document->id,
                        'client_id' => $clientId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $documentName = $document->file_name ?? 'unknown';
            $matterRef = $this->getMatterReference($clientId);
            $subject = !empty($matterRef)
                ? "deleted Visa: {$documentName} - {$matterRef}"
                : "deleted Visa: {$documentName}";

            $this->logClientActivity(
                $clientId,
                $subject,
                '<p>Deleted visa document (category deleted)</p>',
                'document'
            );
        }
    }

    /**
     * Scope visa category document queries to match the Visa Documents tab filters.
     */
    private function visaCategoryDocumentQuery(string $folderName, int $clientId, ?int $clientMatterId)
    {
        $query = Document::query()
            ->where('folder_name', $folderName)
            ->where('doc_type', 'visa')
            ->where('client_id', $clientId)
            ->where('type', 'client');

        if ($clientMatterId !== null) {
            $query->where('client_matter_id', $clientMatterId);
        }

        return $query;
    }

    /**
     * Category label for Not Used documents: folder/category title; visa/nomination append matter in brackets.
     */
    private function resolveNotUsedDocumentCategoryLabel(Document $document): string
    {
        $folderName = $document->folder_name;
        if ($folderName === null || $folderName === '') {
            return '';
        }

        $categoryTitle = null;
        if ($document->doc_type === 'personal') {
            $categoryTitle = PersonalDocumentType::query()->where('id', $folderName)->value('title');
        } elseif ($document->doc_type === 'visa') {
            $categoryTitle = VisaDocumentType::query()->where('id', $folderName)->value('title');
        } elseif ($document->doc_type === 'nomination') {
            $categoryTitle = NominationDocumentType::query()->where('id', $folderName)->value('title');
        }

        if (!$categoryTitle) {
            return '';
        }

        if (in_array($document->doc_type, ['visa', 'nomination'], true) && !empty($document->client_matter_id)) {
            $clientMatter = ClientMatter::with('matter:id,title')->find($document->client_matter_id);
            if ($clientMatter) {
                $matterLabel = $clientMatter->client_unique_matter_no ?? '';
                if ($clientMatter->matter && !empty($clientMatter->matter->title)) {
                    $matterLabel = trim($matterLabel) !== ''
                        ? $matterLabel . ' - ' . $clientMatter->matter->title
                        : $clientMatter->matter->title;
                }
                if ($matterLabel !== '') {
                    return $categoryTitle . ' (' . $matterLabel . ')';
                }
            }
        }

        return $categoryTitle;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildNotUsedDocumentJsonResponse(Document $docInfo, string $docType, $docId): array
    {
        $addedBy = 'N/A';
        $addedDate = 'N/A';
        if (!empty($docInfo->user_id) && $docInfo->staff) {
            $addedBy = $docInfo->staff->first_name;
            $addedDate = date('d/m/Y', strtotime($docInfo->created_at));
        }

        $categoryLabel = '';
        try {
            $categoryLabel = $this->resolveNotUsedDocumentCategoryLabel($docInfo);
        } catch (\Exception $e) {
            Log::warning('Failed to resolve not-used document category label', [
                'document_id' => $docInfo->id ?? null,
                'error' => $e->getMessage(),
            ]);
        }

        return [
            'status' => true,
            'message' => 'Document moved to Not Used tab',
            'data' => $docType.' document moved to Not Used Tab',
            'doc_type' => $docType,
            'doc_id' => $docId,
            'doc_category' => $docInfo->folder_name ?? '',
            'category_label' => $categoryLabel,
            'docInfo' => $docInfo->toArray(),
            'Added_By' => $addedBy,
            'Added_date' => $addedDate,
            'Verified_By' => 'N/A',
            'Verified_At' => 'N/A',
        ];
    }

    /**
     * Add Personal/Education Document Checklist
     */
    public function addedudocchecklist(Request $request){
        $response = ['status' => false, 'message' => 'Please try again'];
        
        try {
            $clientid = $request->clientid;
            if(empty($clientid)) {
                $response['message'] = 'Client ID is required';
                return response()->json($response);
            }

            if ($deny = $this->denyJsonUnlessStaffClientAccess((int) $clientid)) {
                return $deny;
            }
            
            $admin_info1 = Admin::select('client_id')->where('id', $clientid)->first();
            if(!empty($admin_info1)){
                $client_unique_id = $admin_info1->client_id;
            } else {
                $client_unique_id = "";
            }
            $doctype = isset($request->doctype)? $request->doctype : '';
            
            // Validate folder_name
            if(empty($request->folder_name)) {
                $response['message'] = 'Document category is required';
                return response()->json($response);
            }

            if ($request->has('checklist'))
            {
                $checklistArray = $request->input('checklist');
                if (is_array($checklistArray) && !empty($checklistArray))
                {
                    $saved = false;
                    $savedCount = 0;
                    $errors = [];
                    
                    foreach ($checklistArray as $item)
                    {
                        if(empty(trim($item))) {
                            continue; // Skip empty checklist items
                        }
                        
                        try {
                            $obj = new Document;
                            $obj->user_id = Auth::user()->id;
                            $obj->client_id = $clientid;
                            $obj->type = $request->type ?? 'client';
                            $obj->doc_type = $doctype;
                            // For PostgreSQL, keep folder_name as string to avoid type issues
                            // PostgreSQL will handle the conversion if needed
                            $obj->folder_name = (string)$request->folder_name;
                            $obj->checklist = trim($item);
                            
                            // Validate required fields before saving
                            if(empty($obj->user_id) || empty($obj->client_id) || empty($obj->folder_name) || empty($obj->checklist)) {
                                throw new \Exception('Required fields are missing: user_id=' . $obj->user_id . ', client_id=' . $obj->client_id . ', folder_name=' . $obj->folder_name . ', checklist=' . $obj->checklist);
                            }
                            
                            $saved = $obj->save();
                            
                            if($saved) {
                                $savedCount++;
                            } else {
                                $errors[] = "Failed to save checklist item: {$item}";
                            }
                        } catch (\Exception $e) {
                            Log::error('Error saving checklist item', [
                                'item' => $item,
                                'client_id' => $clientid,
                                'folder_name' => $request->folder_name,
                                'error' => $e->getMessage(),
                                'trace' => $e->getTraceAsString()
                            ]);
                            $errors[] = "Error saving '{$item}': " . $e->getMessage();
                        }
                    } //end foreach

                    if($savedCount > 0)
                {
                    if($request->type == 'client'){
                        $checklistCount = count($checklistArray);
                        $subject = "added Personal Checklist";
                        $description = "<p>Added {$checklistCount} document checklist items in '{$request->folder_name}' category: " . implode(', ', array_slice($checklistArray, 0, 3)) . ($checklistCount > 3 ? '...' : '') . "</p>";
                        
                        $this->logClientActivity(
                            $clientid,
                            $subject,
                            $description,
                            'document'
                        );
                    }

                    $response['status'] = true;
                    $response['message'] = 'You\'ve successfully added your personal checklist';

                    $fetchd = Document::with('staff')->where('client_id',$clientid)->whereNull('not_used_doc')->where('doc_type',$doctype)->where('type',$request->type)->where('folder_name',$request->folder_name)->orderby('updated_at', 'DESC')->get();
                    ob_start();
                    foreach($fetchd as $docKey=>$fetch)
                    {
                        $admin = $fetch->staff;
                        $fileUrl = $fetch->myfile_key ? $fetch->myfile : 'https://' . env('AWS_BUCKET') . '.s3.' . env('AWS_DEFAULT_REGION') . '.amazonaws.com/' . $clientid . '/personal/' . $fetch->myfile;
                        ?>
                        <tr class="drow" id="id_<?php echo $fetch->id; ?>">
                            <td style="white-space: initial;">
                                <div data-id="<?php echo $fetch->id;?>" data-personalchecklistname="<?php echo htmlspecialchars($fetch->checklist); ?>" class="personalchecklist-row" title="Uploaded by: <?php echo htmlspecialchars($admin->first_name ?? 'NA'); ?> on <?php echo date('d/m/Y H:i', strtotime($fetch->created_at)); ?>" style="display: flex; align-items: center; gap: 8px;" oncontextmenu="showPersonalChecklistContextMenu(event, <?php echo $fetch->id; ?>); return false;">
                                    <span style="flex: 1;"><?php echo htmlspecialchars($fetch->checklist); ?></span>
                                </div>
                            </td>
                            <td style="white-space: initial;">
                                <?php
                                if( isset($fetch->file_name) && $fetch->file_name !=""){ ?>
                                    <div data-id="<?php echo $fetch->id; ?>" data-name="<?php echo htmlspecialchars($fetch->file_name); ?>" class="doc-row" title="Uploaded by: <?php echo htmlspecialchars($admin->first_name ?? 'NA'); ?> on <?php echo date('d/m/Y H:i', strtotime($fetch->created_at)); ?>" oncontextmenu="showFileContextMenu(event, <?php echo $fetch->id; ?>, '<?php echo htmlspecialchars($fetch->filetype); ?>', '<?php echo $fileUrl; ?>', '<?php echo $request->folder_name; ?>', '<?php echo $fetch->status ?? 'draft'; ?>'); return false;">
                                        <a href="javascript:void(0);" onclick="previewFile('<?php echo $fetch->filetype;?>','<?php echo $fileUrl; ?>','preview-container-<?php echo $request->folder_name;?>')">
                                            <?php echo \App\Helpers\IconHelper::fromLegacy('fas fa-file-image'); ?> <span><?php echo htmlspecialchars($fetch->file_name . '.' . $fetch->filetype); ?></span>
                                        </a>
                                    </div>
                                <?php
                                }
                                else
                                {?>
                                    <div class="upload_document" style="display:inline-block;">
                                        <form method="POST" enctype="multipart/form-data" id="upload_form_<?php echo $fetch->id;?>">
                                            <input type="hidden" name="_token" value="<?php echo csrf_token();?>" />
                                            <input type="hidden" name="clientid" value="<?php echo $clientid;?>">
                                            <input type="hidden" name="fileid" value="<?php echo $fetch->id;?>">
                                            <input type="hidden" name="type" value="client">
                                            <input type="hidden" name="doctype" value="personal">
                                            <input type="hidden" name="doccategory" value="<?php echo $request->doccategory;?>">
                                            
                                            <!-- Drag and Drop Zone -->
                                            <div class="document-drag-drop-zone personal-doc-drag-zone" 
                                                 data-fileid="<?php echo $fetch->id; ?>" 
                                                 data-doccategory="<?php echo $request->folder_name; ?>"
                                                 data-formid="upload_form_<?php echo $fetch->id; ?>">
                                                <div class="drag-zone-inner">
                                                    <?php echo \App\Helpers\IconHelper::fromLegacy('fas fa-cloud-upload-alt'); ?>
                                                    <span class="drag-zone-text">Drag file here or <strong>click to browse</strong></span>
                                                </div>
                                            </div>
                                            
                                            <!-- Keep existing file input (hidden, used as fallback) -->
                                            <input class="docupload d-none" data-fileid="<?php echo $fetch->id;?>" data-doccategory="<?php echo $request->folder_name;?>" type="file" name="document_upload" style="display: none;"/>
                                        </form>
                                    </div>
                                <?php
                                }?>
                            </td>
                            <td>
                                <!-- Hidden elements for context menu actions -->
                                <a class="renamechecklist" data-id="<?php echo $fetch->id; ?>" href="javascript:;" style="display: none;"></a>
                                <?php if (!$fetch->file_name): ?>
                                <a class="delete-checklist-btn" data-id="<?php echo $fetch->id; ?>" data-checklist="<?php echo htmlspecialchars($fetch->checklist); ?>" href="javascript:;" style="display: none;"></a>
                                <?php endif; ?>
                                <?php if ($fetch->myfile): ?>
                                    <a class="renamedoc" data-id="<?php echo $fetch->id; ?>" href="javascript:;" style="display: none;"></a>
                                    <a class="download-file" data-filelink="<?php echo $fetch->myfile; ?>" data-filename="<?php echo $fetch->myfile_key; ?>" href="#" style="display: none;"></a>
                                    <a class="notuseddoc" data-id="<?php echo $fetch->id; ?>" data-doctype="personal" data-doccategory="<?php echo $request->doccategory;?>" data-href="documents/not-used" href="javascript:;" style="display: none;"></a>
                                <?php endif; ?>
                            </td>
                        </tr>
			        <?php
			        } //end foreach

                    $data = ob_get_clean();
                    ob_start();
                    foreach($fetchd as $fetch)
                    {
                        $admin = $fetch->staff;
                        ?>
                        <div class="grid_list">
                            <div class="grid_col">
                                <div class="grid_icon">
                                    <?php echo \App\Helpers\IconHelper::fromLegacy('fas fa-file-image'); ?>
                                </div>
                                <div class="grid_content">
                                    <span id="grid_<?php echo $fetch->id; ?>" class="gridfilename"><?php echo $fetch->file_name; ?></span>
                                    <div class="dropdown d-inline dropdown_ellipsis_icon">
                                        <a class="dropdown-toggle" type="button" id="" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><?php echo \App\Helpers\IconHelper::fromLegacy('fa fa-ellipsis-v'); ?></a>
                                        <div class="dropdown-menu">
                                            <?php
                                            $url = 'https://'.env('AWS_BUCKET').'.s3.'. env('AWS_DEFAULT_REGION') . '.amazonaws.com/';
                                            ?>
                                            <?php if( isset($fetch->myfile) && $fetch->myfile != ""){?>
                                            <a target="_blank" class="dropdown-item" href="<?php echo $fetch->myfile; ?>">Preview</a>
                                            <a href="#" class="dropdown-item download-file" data-filelink="<?php echo $fetch->myfile; ?>" data-filename="<?php echo $fetch->myfile_key; ?>">Download</a>

                                            <a data-id="<?php echo $fetch->id; ?>" class="dropdown-item notuseddoc" data-doctype="personal" data-doccategory="<?php echo $request->folder_name;?>" data-href="notuseddoc" href="javascript:;">Not Used</a>
                                            <?php }?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php
                    } //end foreach
                    $griddata = ob_get_clean();
                    $response['data'] = $data;
                    $response['griddata'] = $griddata;
                    } //end if
                    else
                    {
                        $response['status'] = false;
                        $errorMsg = !empty($errors) ? implode('; ', $errors) : 'Failed to save checklist. Please try again';
                        $response['message'] = $errorMsg;
                        Log::error('Failed to save any checklist items', [
                            'client_id' => $clientid,
                            'folder_name' => $request->folder_name,
                            'checklist_array' => $checklistArray,
                            'errors' => $errors
                        ]);
                    } //end else
                } //end if
                else
                {
                    $response['status'] = false;
                    $response['message'] = 'Please select at least one checklist item';
                } //end else
            } //end if
            else
            {
                $response['status'] = false;
                $response['message'] = 'Please select at least one checklist item';
            } //end else
        } catch (\Illuminate\Database\QueryException $e) {
            // PostgreSQL-specific errors
            $errorMessage = $e->getMessage();
            Log::error('PostgreSQL error adding personal checklist', [
                'client_id' => $request->clientid ?? null,
                'folder_name' => $request->folder_name ?? null,
                'checklist' => $request->input('checklist'),
                'error' => $errorMessage,
                'sql_state' => $e->errorInfo[0] ?? null,
                'sql_code' => $e->errorInfo[1] ?? null,
                'sql_message' => $e->errorInfo[2] ?? null,
                'trace' => $e->getTraceAsString()
            ]);
            
            // Provide more specific error messages
            if (strpos($errorMessage, 'column') !== false && strpos($errorMessage, 'does not exist') !== false) {
                $response['message'] = 'Database column error. Please contact support.';
            } elseif (strpos($errorMessage, 'null value') !== false || strpos($errorMessage, 'NOT NULL') !== false) {
                $response['message'] = 'Required field is missing. Please check all fields are filled.';
            } elseif (strpos($errorMessage, 'foreign key') !== false) {
                $response['message'] = 'Invalid client or staff reference. Please refresh and try again.';
            } else {
                $response['message'] = 'Database error: ' . substr($errorMessage, 0, 100);
            }
            $response['status'] = false;
            $response['message'] = 'Please try again';
        } catch (\Exception $e) {
            Log::error('Error adding personal document checklist', [
                'client_id' => $request->clientid ?? null,
                'folder_name' => $request->folder_name ?? null,
                'checklist' => $request->input('checklist'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $response['status'] = false;
            $response['message'] = 'An error occurred. Please try again.';
        }
        return response()->json($response);
	}
    
    /**
     * Upload Personal/Education Document
     */
    public function uploadedudocument(Request $request) {
        ob_start();
    
        $response = ['status' => false, 'message' => 'Please try again', 'data' => '', 'griddata' => ''];
        $clientid = $request->clientid;
        if ($this->blockEchoUnlessStaffClientAccess((int) $clientid)) {
            ob_end_clean();
            exit;
        }
        $admin_info1 = Admin::select(['id', 'client_id', 'first_name', 'is_company'])->where('id', $clientid)->first();
        $client_unique_id = !empty($admin_info1) ? $admin_info1->client_id : "";
        $client_first_name = !empty($admin_info1)
            ? preg_replace('/[^a-zA-Z0-9_\-]/', '_', (string) ($admin_info1->first_name ?? ''))
            : 'client';
        $namePrefix = DocumentStoredFilename::storedNamePrefix($admin_info1, $client_first_name);
    
        $doctype = $request->doctype ?? '';
    
        try {
            if ($request->hasfile('document_upload')) {
                $file = $request->file('document_upload');
                $size = $file->getSize();
                $fileName = $file->getClientOriginalName();
    
                if (!DocumentFilenameRules::isAllowed($fileName)) {
                    $response['message'] = DocumentFilenameRules::validationMessage();
                } else {
                    $originalName = $file->getClientOriginalName();
                    $extension = $file->getClientOriginalExtension();
    
                    // Validate fileid
                    if (empty($request->fileid)) {
                        $response['message'] = 'Document ID is required';
                        ob_end_clean();
                        header('Content-Type: application/json');
                        echo json_encode($response);
                        exit;
                    }
    
                    // Fetch and validate document
                    $req_file_id = $request->fileid;
                    $obj = Document::query()->find($req_file_id);
                    
                    if (!$obj) {
                        Log::warning('Document upload failed: Document not found', [
                            'fileid' => $req_file_id,
                            'clientid' => $clientid,
                            'user_id' => Auth::user()->id ?? 'unknown'
                        ]);
                        $response['message'] = 'Document record not found.';
                        ob_end_clean();
                        header('Content-Type: application/json');
                        echo json_encode($response);
                        exit;
                    }
    
                    // Validate document belongs to client (security check)
                    if ($obj->client_id != $clientid) {
                        Log::warning('Document upload failed: Client mismatch', [
                            'fileid' => $req_file_id,
                            'document_client_id' => $obj->client_id,
                            'request_client_id' => $clientid,
                            'user_id' => Auth::user()->id ?? 'unknown'
                        ]);
                        $response['message'] = 'Document does not belong to this client.';
                        ob_end_clean();
                        header('Content-Type: application/json');
                        echo json_encode($response);
                        exit;
                    }
    
                    // Validate checklist exists
                    if (empty($obj->checklist)) {
                        Log::warning('Document upload failed: Missing checklist', [
                            'fileid' => $req_file_id,
                            'clientid' => $clientid,
                            'user_id' => Auth::user()->id ?? 'unknown'
                        ]);
                        $response['message'] = 'Document checklist not found. Please select a valid checklist.';
                        ob_end_clean();
                        header('Content-Type: application/json');
                        echo json_encode($response);
                        exit;
                    }
    
                    // Get checklist name right before use to prevent race conditions
                    // Refresh document to get latest checklist name
                    $obj->refresh();
                    $checklistName = $obj->checklist;
    
                    // Validate checklist name is still present after refresh
                    if (empty($checklistName)) {
                        Log::error('Document upload failed: Checklist disappeared during upload', [
                            'fileid' => $req_file_id,
                            'clientid' => $clientid,
                            'user_id' => Auth::user()->id ?? 'unknown'
                        ]);
                        $response['message'] = 'Checklist name not found. Please try again.';
                        ob_end_clean();
                        header('Content-Type: application/json');
                        echo json_encode($response);
                        exit;
                    }
    
                    // Build file name with current checklist name
                    $timestamp = time();
                    $name = $namePrefix . "_" . $checklistName . "_" . $timestamp . "." . $extension;
    
                    $filePath = $client_unique_id . '/' . $doctype . '/' . $name;
                    $this->s3Disk()->put($filePath, file_get_contents($file));
    
                    // Re-fetch checklist name one more time right before saving to ensure we have the latest
                    $obj->refresh();
                    $finalChecklistName = $obj->checklist;
                    
                    // Use the latest checklist name
                    if (!empty($finalChecklistName) && $finalChecklistName !== $checklistName) {
                        // Checklist changed during upload - rebuild name with same timestamp
                        $checklistName = $finalChecklistName;
                        $name = $namePrefix . "_" . $checklistName . "_" . $timestamp . "." . $extension;
                        // Update file path and move S3 file
                        $newFilePath = $client_unique_id . '/' . $doctype . '/' . $name;
                        if ($newFilePath !== $filePath) {
                            try {
                                // Copy to new path and delete old
                                $this->s3Disk()->copy($filePath, $newFilePath);
                                $this->s3Disk()->delete($filePath);
                                $filePath = $newFilePath;
                                Log::info('Document file moved due to checklist change during upload', [
                                    'old_path' => $filePath,
                                    'new_path' => $newFilePath,
                                    'old_checklist' => $checklistName,
                                    'new_checklist' => $finalChecklistName
                                ]);
                            } catch (\Exception $e) {
                                Log::error('Failed to move S3 file after checklist change', [
                                    'old_path' => $filePath,
                                    'new_path' => $newFilePath,
                                    'error' => $e->getMessage()
                                ]);
                                // Continue with old path - at least the file is uploaded
                            }
                        }
                    }
    
                    // Update document with file information
                    $obj->file_name = $namePrefix . "_" . $checklistName . "_" . $timestamp;
                    $obj->filetype = $extension;
                    $obj->user_id = Auth::user()->id;
                    $fileUrl = $this->s3Disk()->url($filePath);
                    $obj->myfile = $fileUrl;
                    $obj->myfile_key = $name;
                    $obj->type = $request->type;
                    $obj->file_size = $size;
                    $obj->doc_type = $doctype;
                    $saved = $obj->save();
                    
                    Log::info('Document uploaded successfully', [
                        'fileid' => $req_file_id,
                        'checklist_name' => $checklistName,
                        'file_name' => $name,
                        'clientid' => $clientid,
                        'user_id' => Auth::user()->id ?? 'unknown'
                    ]);
    
                        if ($saved && $request->type == 'client') {
                            $matterRef = $this->getMatterReference($clientid);
                            $subject = !empty($matterRef) 
                                ? "uploaded {$checklistName} - {$matterRef}"
                                : "uploaded {$checklistName}";
                            $description = "<p>Uploaded document in '{$request->doccategory}' category</p>";
                            
                            $this->logClientActivity(
                                $clientid,
                                $subject,
                                $description,
                                'document'
                            );
                        }
    
                        if ($saved) {
                            $response['status'] = true;
                            $response['message'] = 'File uploaded successfully';
                            $response['filename'] = $name;
                            $response['filetype'] = $extension;
                            $response['fileurl'] = $fileUrl;
                            $response['filekey'] = $name;
                            $response['doccategory'] = $checklistName;
                            $response['uploaded_by'] = Auth::user()->first_name ?? 'Staff';
                            $response['uploaded_at'] = $obj->created_at ? $obj->created_at->format('d/m/Y H:i') : now()->format('d/m/Y H:i');
                        }
                }
            }
        } catch (\Exception $e) {
            $response['message'] = 'An error occurred: ' . $e->getMessage();
        }
    
        ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }

    /**
     * Add Visa Document Checklist
     */
    public function addvisadocchecklist(Request $request) {
        $response = ['status' => false, 'message' => 'Please try again'];
        
        try {
            $clientid = $request->clientid;
            if ($this->blockEchoUnlessStaffClientAccess((int) $clientid)) {
                return;
            }
            $admin_info1 = Admin::select('client_id')->where('id', $clientid)->first();
            if(!empty($admin_info1)){
                $client_unique_id = $admin_info1->client_id;
            } else {
                $client_unique_id = "";
            }

            $doctype = isset($request->doctype)? $request->doctype : '';
            if ($request->has('visa_checklist'))
        {
            $checklistArray = $request->input('visa_checklist');
            if (is_array($checklistArray))
            {
                $saved = false;
                foreach ($checklistArray as $item)
                {
                    $obj = new Document;
                    $obj->user_id = Auth::user()->id;
                    $obj->client_id = $clientid;
                    $obj->type = $request->type;
                    $obj->doc_type = $doctype;
                    $obj->client_matter_id = $request->client_matter_id;
                    $obj->checklist = $item;
                    $obj->folder_name = $request->folder_name;
                    $saved = $obj->save();
                }  //end foreach

                if($saved)
                {
                    if($request->type == 'client'){
                        $checklistCount = count($checklistArray);
                        $matterRef = $this->getMatterReference($clientid, $request->client_matter_id ?? null);
                        $subject = !empty($matterRef) 
                            ? "added Visa Checklist - {$matterRef}"
                            : "added Visa Checklist";
                        $description = "<p>Added {$checklistCount} visa document checklist items: " . implode(', ', array_slice($checklistArray, 0, 3)) . ($checklistCount > 3 ? '...' : '') . "</p>";
                        
                        $this->logClientActivity(
                            $clientid,
                            $subject,
                            $description,
                            'document'
                        );
                    }

                    //Update date in client matter table
                    if( isset($request->client_matter_id) && $request->client_matter_id != ""){
                        $obj1 = ClientMatter::query()->find($request->client_matter_id);
                        $obj1->updated_at = date('Y-m-d H:i:s');
                        $obj1->save();
                    }
                    $response['status'] 	= 	true;
                    $response['message']	=	'You have added uploaded your visa checklist';

                    // Get all documents for this client (original behavior - no strict filtering)
                    $fetchd = Document::with('staff')->where('client_id',$clientid)
                        ->whereNull('not_used_doc')
                        ->where('doc_type',$doctype)
                        ->where('type',$request->type)
                        ->orderBy('updated_at', 'DESC')
                        ->get();
                    
                    ob_start();
                    foreach($fetchd as $visaKey=>$fetch)
                    {
                        $admin = $fetch->staff;
                        $VisaDocumentType = VisaDocumentType::query()->where('id', $fetch->folder_name)->first();
                        $fileUrl = $fetch->myfile_key ? $fetch->myfile : 'https://' . env('AWS_BUCKET') . '.s3.' . env('AWS_DEFAULT_REGION') . '.amazonaws.com/' . $fetch->client_id . '/visa/' . $fetch->myfile;
                        
                        // Hide non-matching documents with CSS (original behavior)
                        if (
                            $request->client_matter_id != $fetch->client_matter_id ||
                            $request->folder_name != $fetch->folder_name
                        ) {
                            $showCls = "style='display: none;'";
                        } else {
                            $showCls = "";
                        }
                        ?>
                        <tr class="drow" data-matterid="<?php echo $fetch->client_matter_id;?>" data-catid="<?php echo $fetch->folder_name;?>" id="id_<?php echo $fetch->id; ?>" <?php echo $showCls;?>>
                            <td style="white-space: initial;">
                                <div data-id="<?php echo $fetch->id;?>" data-visachecklistname="<?php echo htmlspecialchars($fetch->checklist); ?>" class="visachecklist-row" title="Uploaded by: <?php echo htmlspecialchars($admin->first_name ?? 'NA'); ?> on <?php echo date('d/m/Y H:i', strtotime($fetch->created_at)); ?>" style="display: flex; align-items: center; gap: 8px;" oncontextmenu="showVisaChecklistContextMenu(event, <?php echo $fetch->id; ?>); return false;">
                                    <span style="flex: 1;"><?php echo htmlspecialchars($fetch->checklist); ?></span>
                                </div>
                            </td>
                            <td style="white-space: initial;">
                                <?php
                                if( isset($fetch->file_name) && $fetch->file_name !=""){ ?>
                                    <div data-id="<?php echo $fetch->id; ?>" data-name="<?php echo htmlspecialchars($fetch->file_name); ?>" class="doc-row" title="Uploaded by: <?php echo htmlspecialchars($admin->first_name ?? 'NA'); ?> on <?php echo date('d/m/Y H:i', strtotime($fetch->created_at)); ?>" oncontextmenu="showVisaFileContextMenu(event, <?php echo $fetch->id; ?>, '<?php echo htmlspecialchars($fetch->filetype); ?>', '<?php echo $fileUrl; ?>', '<?php echo $fetch->folder_name; ?>', '<?php echo $fetch->status ?? 'draft'; ?>'); return false;">
                                        <a href="javascript:void(0);" onclick="previewFile('<?php echo $fetch->filetype;?>','<?php echo $fetch->myfile; ?>','preview-container-migdocumnetlist')">
                                            <?php echo \App\Helpers\IconHelper::fromLegacy('fas fa-file-image'); ?> <span><?php echo htmlspecialchars($fetch->file_name . '.' . $fetch->filetype); ?></span>
                                        </a>
                                    </div>
                                <?php
                                }
                                else
                                {?>
                                    <div class="migration_upload_document" style="display: inline-block;">
                                        <form method="POST" enctype="multipart/form-data" id="mig_upload_form_<?php echo $fetch->id;?>">
                                            <input type="hidden" name="_token" value="<?php echo csrf_token();?>" />
                                            <input type="hidden" name="clientid" value="<?php echo $fetch->client_id;?>">
                                            <input type="hidden" name="client_matter_id" value="<?php echo $fetch->client_matter_id;?>">
                                            <input type="hidden" name="fileid" value="<?php echo $fetch->id;?>">
                                            <input type="hidden" name="type" value="client">
                                            <input type="hidden" name="doctype" value="visa">
                                            <input type="hidden" name="doccategory" value="<?php echo $VisaDocumentType->title; ?>">
                                            
                                            <!-- Drag and Drop Zone -->
                                            <div class="document-drag-drop-zone visa-doc-drag-zone" 
                                                 data-fileid="<?php echo $fetch->id;?>" 
                                                 data-doccategory="<?php echo $fetch->folder_name;?>"
                                                 data-formid="mig_upload_form_<?php echo $fetch->id;?>">
                                                <div class="drag-zone-inner">
                                                    <?php echo \App\Helpers\IconHelper::fromLegacy('fas fa-cloud-upload-alt'); ?>
                                                    <span class="drag-zone-text">Drag file here or <strong>click to browse</strong></span>
                                                </div>
                                            </div>
                                            
                                            <!-- Keep existing file input (hidden) -->
                                            <input class="migdocupload d-none" 
                                                   data-fileid="<?php echo $fetch->id;?>" 
                                                   data-doccategory="<?php echo $fetch->folder_name;?>" 
                                                   type="file" 
                                                   name="document_upload" 
                                                   style="display: none;"/>
                                        </form>
                                    </div>
                                <?php
                                }?>
                            </td>
                            <td>
                                <!-- Hidden elements for context menu actions -->
                                <a class="renamechecklist" data-id="<?php echo $fetch->id; ?>" href="javascript:;" style="display: none;"></a>
                                <?php if (!$fetch->file_name): ?>
                                <a class="delete-checklist-btn" data-id="<?php echo $fetch->id; ?>" data-checklist="<?php echo htmlspecialchars($fetch->checklist); ?>" href="javascript:;" style="display: none;"></a>
                                <?php endif; ?>
                                <?php if ($fetch->myfile): ?>
                                    <a class="renamedoc" data-id="<?php echo $fetch->id; ?>" href="javascript:;" style="display: none;"></a>
                                    <a class="download-file" data-filelink="<?php echo $fetch->myfile; ?>" data-filename="<?php echo $fetch->myfile_key; ?>" href="#" style="display: none;"></a>
                                    <a class="notuseddoc" data-id="<?php echo $fetch->id; ?>" data-doctype="visa" data-href="documents/not-used" href="javascript:;" style="display: none;"></a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php
                    } //end foreach

                    $data = ob_get_clean();
                    ob_start();
                    foreach($fetchd as $fetch)
                    {
                        $admin = $fetch->staff;
                        ?>
                        <div class="grid_list">
                            <div class="grid_col">
                                <div class="grid_icon">
                                    <?php echo \App\Helpers\IconHelper::fromLegacy('fas fa-file-image'); ?>
                                </div>
                                <div class="grid_content">
                                    <span id="grid_<?php echo $fetch->id; ?>" class="gridfilename"><?php echo $fetch->file_name; ?></span>
                                    <div class="dropdown d-inline dropdown_ellipsis_icon">
                                        <a class="dropdown-toggle" type="button" id="" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><?php echo \App\Helpers\IconHelper::fromLegacy('fa fa-ellipsis-v'); ?></a>
                                        <div class="dropdown-menu">
                                            <?php
                                            $url = 'https://'.env('AWS_BUCKET').'.s3.'. env('AWS_DEFAULT_REGION') . '.amazonaws.com/';
                                            ?>
                                            <a target="_blank" class="dropdown-item" href="<?php echo $fetch->myfile; ?>">Preview</a>
                                            <a href="#" class="dropdown-item download-file" data-filelink="<?php echo $fetch->myfile; ?>" data-filename="<?php echo $fetch->myfile_key; ?>">Download</a>

                                            <a data-id="<?php echo $fetch->id; ?>" class="dropdown-item notuseddoc" data-doctype="visa" data-href="notuseddoc" href="javascript:;">Not Used</a>
                                           

                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php
                    } //end foreach
                    $griddata = ob_get_clean();
                    $response['data']	= $data;
                    $response['griddata'] = $griddata;
                } //end if
                else
                {
                    $response['status'] = false;
                    $response['message'] = 'Please try again';
                } //end else
            } //end if
            else
            {
                $response['status'] = false;
                $response['message'] = 'Please try again';
            } //end else
        }
        else
        {
            $response['status'] = false;
            $response['message'] = 'Please try again';
        } //end else
        } catch (\Exception $e) {
            Log::error('Error adding visa document checklist', [
                'client_id' => $request->clientid ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $response['status'] = false;
            $response['message'] = 'An error occurred. Please try again.';
        }
        echo json_encode($response);
    }

    /**
     * Refresh personal/visa document category list HTML after external saves (e.g. email attachment upload).
     */
    public function refreshDocumentCategoryList(Request $request): JsonResponse
    {
        $response = ['status' => false, 'message' => 'Please try again'];

        try {
            $clientid = $request->clientid;
            if (empty($clientid)) {
                $response['message'] = 'Client ID is required';

                return response()->json($response);
            }

            if ($deny = $this->denyJsonUnlessStaffClientAccess((int) $clientid)) {
                return $deny;
            }

            if (empty($request->folder_name)) {
                $response['message'] = 'Document category is required';

                return response()->json($response);
            }

            $doctype = $request->doctype ?? 'personal';
            $recordType = $request->type ?? 'client';
            $folderName = (string) $request->folder_name;

            if ($doctype === 'visa') {
                $payload = $this->buildVisaDocumentCategoryListHtml(
                    (int) $clientid,
                    $folderName,
                    $recordType,
                    $doctype,
                    $request->client_matter_id
                );
            } else {
                $category = PersonalDocumentType::query()->find($folderName);
                $doccategoryTitle = $category ? $category->title : '';

                $payload = $this->buildPersonalDocumentCategoryListHtml(
                    (int) $clientid,
                    $folderName,
                    $recordType,
                    $doctype,
                    $doccategoryTitle
                );
            }

            $response['status'] = true;
            $response['message'] = 'Document list refreshed';
            $response['data'] = $payload['data'];
            $response['griddata'] = $payload['griddata'];
        } catch (\Exception $e) {
            Log::error('Error refreshing document category list', [
                'client_id' => $request->clientid ?? null,
                'folder_name' => $request->folder_name ?? null,
                'doctype' => $request->doctype ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $response['status'] = false;
            $response['message'] = 'An error occurred. Please try again.';
        }

        return response()->json($response);
    }

    /**
     * @return array{data: string, griddata: string}
     */
    private function buildPersonalDocumentCategoryListHtml(
        int $clientid,
        string $folderName,
        string $recordType,
        string $docType,
        string $doccategoryTitle
    ): array {
        $documents = Document::with('staff')
            ->where('client_id', $clientid)
            ->whereNull('not_used_doc')
            ->where('doc_type', $docType)
            ->where('type', $recordType)
            ->where('folder_name', $folderName)
            ->orderBy('updated_at', 'DESC')
            ->get();

        ob_start();
        foreach ($documents as $fetch) {
            $admin = $fetch->staff;
            $fileUrl = $fetch->myfile_key ? $fetch->myfile : 'https://' . env('AWS_BUCKET') . '.s3.' . env('AWS_DEFAULT_REGION') . '.amazonaws.com/' . $clientid . '/personal/' . $fetch->myfile;
            ?>
            <tr class="drow" id="id_<?php echo $fetch->id; ?>">
                <td style="white-space: initial;">
                    <div data-id="<?php echo $fetch->id;?>" data-personalchecklistname="<?php echo htmlspecialchars($fetch->checklist); ?>" class="personalchecklist-row" title="Uploaded by: <?php echo htmlspecialchars($admin->first_name ?? 'NA'); ?> on <?php echo date('d/m/Y H:i', strtotime($fetch->created_at)); ?>" style="display: flex; align-items: center; gap: 8px;" oncontextmenu="showPersonalChecklistContextMenu(event, <?php echo $fetch->id; ?>); return false;">
                        <span style="flex: 1;"><?php echo htmlspecialchars($fetch->checklist); ?></span>
                    </div>
                </td>
                <td style="white-space: initial;">
                    <?php if (isset($fetch->file_name) && $fetch->file_name != '') { ?>
                        <div data-id="<?php echo $fetch->id; ?>" data-name="<?php echo htmlspecialchars($fetch->file_name); ?>" class="doc-row" title="Uploaded by: <?php echo htmlspecialchars($admin->first_name ?? 'NA'); ?> on <?php echo date('d/m/Y H:i', strtotime($fetch->created_at)); ?>" oncontextmenu="showFileContextMenu(event, <?php echo $fetch->id; ?>, '<?php echo htmlspecialchars($fetch->filetype); ?>', '<?php echo $fileUrl; ?>', '<?php echo $folderName; ?>', '<?php echo $fetch->status ?? 'draft'; ?>'); return false;">
                            <a href="javascript:void(0);" onclick="previewFile('<?php echo $fetch->filetype;?>','<?php echo $fileUrl; ?>','preview-container-<?php echo $folderName;?>')">
                                <?php echo IconHelper::fromLegacy('fas fa-file-image'); ?> <span><?php echo htmlspecialchars($fetch->file_name . '.' . $fetch->filetype); ?></span>
                            </a>
                        </div>
                    <?php } else { ?>
                        <div class="upload_document" style="display:inline-block;">
                            <form method="POST" enctype="multipart/form-data" id="upload_form_<?php echo $fetch->id;?>">
                                <input type="hidden" name="_token" value="<?php echo csrf_token();?>" />
                                <input type="hidden" name="clientid" value="<?php echo $clientid;?>">
                                <input type="hidden" name="fileid" value="<?php echo $fetch->id;?>">
                                <input type="hidden" name="type" value="client">
                                <input type="hidden" name="doctype" value="personal">
                                <input type="hidden" name="doccategory" value="<?php echo htmlspecialchars($doccategoryTitle); ?>">
                                <div class="document-drag-drop-zone personal-doc-drag-zone"
                                     data-fileid="<?php echo $fetch->id; ?>"
                                     data-doccategory="<?php echo $folderName; ?>"
                                     data-formid="upload_form_<?php echo $fetch->id; ?>">
                                    <div class="drag-zone-inner">
                                        <?php echo IconHelper::fromLegacy('fas fa-cloud-upload-alt'); ?>
                                        <span class="drag-zone-text">Drag file here or <strong>click to browse</strong></span>
                                    </div>
                                </div>
                                <input class="docupload d-none" data-fileid="<?php echo $fetch->id;?>" data-doccategory="<?php echo $folderName;?>" type="file" name="document_upload" style="display: none;"/>
                            </form>
                        </div>
                    <?php } ?>
                </td>
                <td>
                    <a class="renamechecklist" data-id="<?php echo $fetch->id; ?>" href="javascript:;" style="display: none;"></a>
                    <?php if (!$fetch->file_name) { ?>
                    <a class="delete-checklist-btn" data-id="<?php echo $fetch->id; ?>" data-checklist="<?php echo htmlspecialchars($fetch->checklist); ?>" href="javascript:;" style="display: none;"></a>
                    <?php } ?>
                    <?php if ($fetch->myfile) { ?>
                        <a class="renamedoc" data-id="<?php echo $fetch->id; ?>" href="javascript:;" style="display: none;"></a>
                        <a class="download-file" data-filelink="<?php echo $fetch->myfile; ?>" data-filename="<?php echo $fetch->myfile_key; ?>" href="#" style="display: none;"></a>
                        <a class="notuseddoc" data-id="<?php echo $fetch->id; ?>" data-doctype="personal" data-doccategory="<?php echo htmlspecialchars($doccategoryTitle); ?>" data-href="documents/not-used" href="javascript:;" style="display: none;"></a>
                    <?php } ?>
                </td>
            </tr>
            <?php
        }
        $data = ob_get_clean();

        ob_start();
        foreach ($documents as $fetch) {
            ?>
            <div class="grid_list">
                <div class="grid_col">
                    <div class="grid_icon">
                        <?php echo IconHelper::fromLegacy('fas fa-file-image'); ?>
                    </div>
                    <div class="grid_content">
                        <span id="grid_<?php echo $fetch->id; ?>" class="gridfilename"><?php echo $fetch->file_name; ?></span>
                        <div class="dropdown d-inline dropdown_ellipsis_icon">
                            <a class="dropdown-toggle" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><?php echo IconHelper::fromLegacy('fa fa-ellipsis-v'); ?></a>
                            <div class="dropdown-menu">
                                <?php if (isset($fetch->myfile) && $fetch->myfile != '') { ?>
                                <a target="_blank" class="dropdown-item" href="<?php echo $fetch->myfile; ?>">Preview</a>
                                <a href="#" class="dropdown-item download-file" data-filelink="<?php echo $fetch->myfile; ?>" data-filename="<?php echo $fetch->myfile_key; ?>">Download</a>
                                <a data-id="<?php echo $fetch->id; ?>" class="dropdown-item notuseddoc" data-doctype="personal" data-doccategory="<?php echo $folderName; ?>" data-href="notuseddoc" href="javascript:;">Not Used</a>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php
        }
        $griddata = ob_get_clean();

        return ['data' => $data, 'griddata' => $griddata];
    }

    /**
     * @return array{data: string, griddata: string}
     */
    private function buildVisaDocumentCategoryListHtml(
        int $clientid,
        string $folderName,
        string $recordType,
        string $docType,
        $clientMatterId
    ): array {
        $documents = Document::with('staff')
            ->where('client_id', $clientid)
            ->whereNull('not_used_doc')
            ->where('doc_type', $docType)
            ->where('type', $recordType)
            ->orderBy('updated_at', 'DESC')
            ->get();

        ob_start();
        foreach ($documents as $fetch) {
            $admin = $fetch->staff;
            $visaDocumentType = VisaDocumentType::query()->where('id', $fetch->folder_name)->first();
            $fileUrl = $fetch->myfile_key ? $fetch->myfile : 'https://' . env('AWS_BUCKET') . '.s3.' . env('AWS_DEFAULT_REGION') . '.amazonaws.com/' . $fetch->client_id . '/visa/' . $fetch->myfile;

            if ($clientMatterId != $fetch->client_matter_id || $folderName != $fetch->folder_name) {
                $showCls = "style='display: none;'";
            } else {
                $showCls = '';
            }
            ?>
            <tr class="drow" data-matterid="<?php echo $fetch->client_matter_id;?>" data-catid="<?php echo $fetch->folder_name;?>" id="id_<?php echo $fetch->id; ?>" <?php echo $showCls;?>>
                <td style="white-space: initial;">
                    <div data-id="<?php echo $fetch->id;?>" data-visachecklistname="<?php echo htmlspecialchars($fetch->checklist); ?>" class="visachecklist-row" title="Uploaded by: <?php echo htmlspecialchars($admin->first_name ?? 'NA'); ?> on <?php echo date('d/m/Y H:i', strtotime($fetch->created_at)); ?>" style="display: flex; align-items: center; gap: 8px;" oncontextmenu="showVisaChecklistContextMenu(event, <?php echo $fetch->id; ?>); return false;">
                        <span style="flex: 1;"><?php echo htmlspecialchars($fetch->checklist); ?></span>
                    </div>
                </td>
                <td style="white-space: initial;">
                    <?php if (isset($fetch->file_name) && $fetch->file_name != '') { ?>
                        <div data-id="<?php echo $fetch->id; ?>" data-name="<?php echo htmlspecialchars($fetch->file_name); ?>" class="doc-row" title="Uploaded by: <?php echo htmlspecialchars($admin->first_name ?? 'NA'); ?> on <?php echo date('d/m/Y H:i', strtotime($fetch->created_at)); ?>" oncontextmenu="showVisaFileContextMenu(event, <?php echo $fetch->id; ?>, '<?php echo htmlspecialchars($fetch->filetype); ?>', '<?php echo $fileUrl; ?>', '<?php echo $fetch->folder_name; ?>', '<?php echo $fetch->status ?? 'draft'; ?>'); return false;">
                            <a href="javascript:void(0);" onclick="previewFile('<?php echo $fetch->filetype;?>','<?php echo $fetch->myfile; ?>','preview-container-migdocumnetlist')">
                                <?php echo IconHelper::fromLegacy('fas fa-file-image'); ?> <span><?php echo htmlspecialchars($fetch->file_name . '.' . $fetch->filetype); ?></span>
                            </a>
                        </div>
                    <?php } else { ?>
                        <div class="migration_upload_document" style="display: inline-block;">
                            <form method="POST" enctype="multipart/form-data" id="mig_upload_form_<?php echo $fetch->id;?>">
                                <input type="hidden" name="_token" value="<?php echo csrf_token();?>" />
                                <input type="hidden" name="clientid" value="<?php echo $fetch->client_id;?>">
                                <input type="hidden" name="client_matter_id" value="<?php echo $fetch->client_matter_id;?>">
                                <input type="hidden" name="fileid" value="<?php echo $fetch->id;?>">
                                <input type="hidden" name="type" value="client">
                                <input type="hidden" name="doctype" value="visa">
                                <input type="hidden" name="doccategory" value="<?php echo $visaDocumentType->title ?? ''; ?>">
                                <div class="document-drag-drop-zone visa-doc-drag-zone"
                                     data-fileid="<?php echo $fetch->id;?>"
                                     data-doccategory="<?php echo $fetch->folder_name;?>"
                                     data-formid="mig_upload_form_<?php echo $fetch->id;?>">
                                    <div class="drag-zone-inner">
                                        <?php echo IconHelper::fromLegacy('fas fa-cloud-upload-alt'); ?>
                                        <span class="drag-zone-text">Drag file here or <strong>click to browse</strong></span>
                                    </div>
                                </div>
                                <input class="migdocupload d-none"
                                       data-fileid="<?php echo $fetch->id;?>"
                                       data-doccategory="<?php echo $fetch->folder_name;?>"
                                       type="file"
                                       name="document_upload"
                                       style="display: none;"/>
                            </form>
                        </div>
                    <?php } ?>
                </td>
                <td>
                    <a class="renamechecklist" data-id="<?php echo $fetch->id; ?>" href="javascript:;" style="display: none;"></a>
                    <?php if (!$fetch->file_name) { ?>
                    <a class="delete-checklist-btn" data-id="<?php echo $fetch->id; ?>" data-checklist="<?php echo htmlspecialchars($fetch->checklist); ?>" href="javascript:;" style="display: none;"></a>
                    <?php } ?>
                    <?php if ($fetch->myfile) { ?>
                        <a class="renamedoc" data-id="<?php echo $fetch->id; ?>" href="javascript:;" style="display: none;"></a>
                        <a class="download-file" data-filelink="<?php echo $fetch->myfile; ?>" data-filename="<?php echo $fetch->myfile_key; ?>" href="#" style="display: none;"></a>
                        <a class="notuseddoc" data-id="<?php echo $fetch->id; ?>" data-doctype="visa" data-href="documents/not-used" href="javascript:;" style="display: none;"></a>
                    <?php } ?>
                </td>
            </tr>
            <?php
        }
        $data = ob_get_clean();

        ob_start();
        foreach ($documents as $fetch) {
            ?>
            <div class="grid_list">
                <div class="grid_col">
                    <div class="grid_icon">
                        <?php echo IconHelper::fromLegacy('fas fa-file-image'); ?>
                    </div>
                    <div class="grid_content">
                        <span id="grid_<?php echo $fetch->id; ?>" class="gridfilename"><?php echo $fetch->file_name; ?></span>
                        <div class="dropdown d-inline dropdown_ellipsis_icon">
                            <a class="dropdown-toggle" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><?php echo IconHelper::fromLegacy('fa fa-ellipsis-v'); ?></a>
                            <div class="dropdown-menu">
                                <a target="_blank" class="dropdown-item" href="<?php echo $fetch->myfile; ?>">Preview</a>
                                <a href="#" class="dropdown-item download-file" data-filelink="<?php echo $fetch->myfile; ?>" data-filename="<?php echo $fetch->myfile_key; ?>">Download</a>
                                <a data-id="<?php echo $fetch->id; ?>" class="dropdown-item notuseddoc" data-doctype="visa" data-href="notuseddoc" href="javascript:;">Not Used</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php
        }
        $griddata = ob_get_clean();

        return ['data' => $data, 'griddata' => $griddata];
    }

    public function addNominationDocChecklist(Request $request)
    {
        $response = ['status' => false, 'message' => 'Please try again'];

        try {
            $clientid = $request->clientid;
            if ($this->blockEchoUnlessStaffClientAccess((int) $clientid)) {
                return;
            }

            $doctype = $request->doctype ?? '';

            if (!$request->has('nomination_checklist')) {
                echo json_encode($response);

                return;
            }

            $checklistArray = $request->input('nomination_checklist');
            if (!is_array($checklistArray)) {
                echo json_encode($response);

                return;
            }

            $saved = false;
            foreach ($checklistArray as $item) {
                $obj = new Document();
                $obj->user_id = Auth::user()->id;
                $obj->client_id = $clientid;
                $obj->type = $request->type;
                $obj->doc_type = $doctype;
                $obj->client_matter_id = $request->client_matter_id;
                $obj->checklist = $item;
                $obj->folder_name = $request->folder_name;
                $saved = $obj->save();
            }

            if (!$saved) {
                echo json_encode($response);

                return;
            }

            if ($request->type == 'client') {
                $checklistCount = count($checklistArray);
                $matterRef = $this->getMatterReference($clientid, $request->client_matter_id ?? null);
                $subject = !empty($matterRef)
                    ? "added Nomination Checklist - {$matterRef}"
                    : 'added Nomination Checklist';
                $description = '<p>Added '.$checklistCount.' nomination document checklist items: '.implode(', ', array_slice($checklistArray, 0, 3)).($checklistCount > 3 ? '...' : '').'</p>';

                $this->logClientActivity(
                    $clientid,
                    $subject,
                    $description,
                    'document'
                );
            }

            if (isset($request->client_matter_id) && $request->client_matter_id != '') {
                $obj1 = ClientMatter::query()->find($request->client_matter_id);
                if ($obj1) {
                    $obj1->updated_at = date('Y-m-d H:i:s');
                    $obj1->save();
                }
            }

            $response['status'] = true;
            $response['message'] = 'You have added your nomination checklist';

            $fetchd = Document::with('staff')->where('client_id', $clientid)
                ->whereNull('not_used_doc')
                ->where('doc_type', $doctype)
                ->where('type', $request->type)
                ->orderBy('updated_at', 'DESC')
                ->get();

            ob_start();
            foreach ($fetchd as $fetch) {
                $admin = $fetch->staff;
                $nomCat = NominationDocumentType::query()->where('id', $fetch->folder_name)->first();
                $catTitle = $nomCat->title ?? '';
                $fileUrl = $fetch->myfile_key ? $fetch->myfile : 'https://'.env('AWS_BUCKET').'.s3.'.env('AWS_DEFAULT_REGION').'.amazonaws.com/'.$fetch->client_id.'/nomination/'.$fetch->myfile;

                if (
                    $request->client_matter_id != $fetch->client_matter_id
                    || $request->folder_name != $fetch->folder_name
                ) {
                    $showCls = "style='display: none;'";
                } else {
                    $showCls = '';
                }
                ?>
                        <tr class="drow" data-matterid="<?php echo $fetch->client_matter_id; ?>" data-catid="<?php echo $fetch->folder_name; ?>" id="id_<?php echo $fetch->id; ?>" <?php echo $showCls; ?>>
                            <td style="white-space: initial;">
                                <div data-id="<?php echo $fetch->id; ?>" data-visachecklistname="<?php echo htmlspecialchars($fetch->checklist); ?>" class="visachecklist-row" title="Uploaded by: <?php echo htmlspecialchars($admin->first_name ?? 'NA'); ?> on <?php echo date('d/m/Y H:i', strtotime($fetch->created_at)); ?>" style="display: flex; align-items: center; gap: 8px;">
                                    <span style="flex: 1;"><?php echo htmlspecialchars($fetch->checklist); ?></span>
                                    <div class="checklist-actions" style="display: flex; gap: 5px;">
                                        <?php if (!$fetch->file_name) { ?>
                                        <a href="javascript:;" class="edit-checklist-btn" data-id="<?php echo $fetch->id; ?>" data-checklist="<?php echo htmlspecialchars($fetch->checklist); ?>" title="Edit Checklist Name" style="color: #007bff; cursor: pointer;">
                                            <?php echo \App\Helpers\IconHelper::fromLegacy('fas fa-edit'); ?>
                                        </a>
                                        <a href="javascript:;" class="delete-checklist-btn" data-id="<?php echo $fetch->id; ?>" data-checklist="<?php echo htmlspecialchars($fetch->checklist); ?>" title="Delete Checklist" style="color: #dc3545; cursor: pointer;">
                                            <?php echo \App\Helpers\IconHelper::fromLegacy('fas fa-trash'); ?>
                                        </a>
                                        <?php } ?>
                                    </div>
                                </div>
                            </td>
                            <td style="white-space: initial;">
                                <?php if (isset($fetch->file_name) && $fetch->file_name != '') { ?>
                                    <div data-id="<?php echo $fetch->id; ?>" data-name="<?php echo htmlspecialchars($fetch->file_name); ?>" class="doc-row" title="Uploaded by: <?php echo htmlspecialchars($admin->first_name ?? 'NA'); ?> on <?php echo date('d/m/Y H:i', strtotime($fetch->created_at)); ?>" oncontextmenu="showNominationFileContextMenu(event, <?php echo $fetch->id; ?>, '<?php echo htmlspecialchars($fetch->filetype); ?>', '<?php echo $fileUrl; ?>', '<?php echo $fetch->folder_name; ?>', '<?php echo $fetch->status ?? 'draft'; ?>'); return false;">
                                        <a href="javascript:void(0);" onclick="previewFile('<?php echo $fetch->filetype; ?>','<?php echo $fetch->myfile; ?>','preview-container-nomdocumnetlist')">
                                            <?php echo \App\Helpers\IconHelper::fromLegacy('fas fa-file-image'); ?> <span><?php echo htmlspecialchars($fetch->file_name.'.'.$fetch->filetype); ?></span>
                                        </a>
                                    </div>
                                <?php } else { ?>
                                    <div class="migration_upload_document" style="display: inline-block;">
                                        <form method="POST" enctype="multipart/form-data" id="mig_upload_form_<?php echo $fetch->id; ?>">
                                            <input type="hidden" name="_token" value="<?php echo csrf_token(); ?>" />
                                            <input type="hidden" name="clientid" value="<?php echo $fetch->client_id; ?>">
                                            <input type="hidden" name="client_matter_id" value="<?php echo $fetch->client_matter_id; ?>">
                                            <input type="hidden" name="fileid" value="<?php echo $fetch->id; ?>">
                                            <input type="hidden" name="type" value="client">
                                            <input type="hidden" name="doctype" value="nomination">
                                            <input type="hidden" name="doccategory" value="<?php echo htmlspecialchars($catTitle); ?>">

                                            <div class="document-drag-drop-zone nomination-doc-drag-zone"
                                                 data-fileid="<?php echo $fetch->id; ?>"
                                                 data-doccategory="<?php echo $fetch->folder_name; ?>"
                                                 data-formid="mig_upload_form_<?php echo $fetch->id; ?>">
                                                <div class="drag-zone-inner">
                                                    <?php echo \App\Helpers\IconHelper::fromLegacy('fas fa-cloud-upload-alt'); ?>
                                                    <span class="drag-zone-text">Drag file here or <strong>click to browse</strong></span>
                                                </div>
                                            </div>

                                            <input class="migdocupload d-none nomination-migupload"
                                                   data-fileid="<?php echo $fetch->id; ?>"
                                                   data-doccategory="<?php echo $fetch->folder_name; ?>"
                                                   type="file"
                                                   name="document_upload"
                                                   style="display: none;"/>
                                        </form>
                                    </div>
                                <?php } ?>
                            </td>
                            <td>
                                <?php if ($fetch->myfile) { ?>
                                    <a class="renamechecklist" data-id="<?php echo $fetch->id; ?>" href="javascript:;" style="display: none;"></a>
                                    <a class="renamedoc" data-id="<?php echo $fetch->id; ?>" href="javascript:;" style="display: none;"></a>
                                    <a class="download-file" data-filelink="<?php echo $fetch->myfile; ?>" data-filename="<?php echo $fetch->myfile_key; ?>" href="#" style="display: none;"></a>
                                    <a class="notuseddoc" data-id="<?php echo $fetch->id; ?>" data-doctype="nomination" data-href="documents/not-used" href="javascript:;" style="display: none;"></a>
                                <?php } ?>
                            </td>
                        </tr>
                    <?php
            }

            $data = ob_get_clean();
            ob_start();
            foreach ($fetchd as $fetch) {
                ?>
                        <div class="grid_list">
                            <div class="grid_col">
                                <div class="grid_icon">
                                    <?php echo \App\Helpers\IconHelper::fromLegacy('fas fa-file-image'); ?>
                                </div>
                                <div class="grid_content">
                                    <span id="grid_<?php echo $fetch->id; ?>" class="gridfilename"><?php echo $fetch->file_name; ?></span>
                                    <div class="dropdown d-inline dropdown_ellipsis_icon">
                                        <a class="dropdown-toggle" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><?php echo \App\Helpers\IconHelper::fromLegacy('fa fa-ellipsis-v'); ?></a>
                                        <div class="dropdown-menu">
                                            <a target="_blank" class="dropdown-item" href="<?php echo $fetch->myfile; ?>">Preview</a>
                                            <a href="#" class="dropdown-item download-file" data-filelink="<?php echo $fetch->myfile; ?>" data-filename="<?php echo $fetch->myfile_key; ?>">Download</a>
                                            <a data-id="<?php echo $fetch->id; ?>" class="dropdown-item notuseddoc" data-doctype="nomination" data-href="notuseddoc" href="javascript:;">Not Used</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php
            }
            $griddata = ob_get_clean();
            $response['data'] = $data;
            $response['griddata'] = $griddata;
        } catch (\Exception $e) {
            Log::error('Error adding nomination document checklist', [
                'client_id' => $request->clientid ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $response['status'] = false;
            $response['message'] = 'An error occurred. Please try again.';
        }
        echo json_encode($response);
    }

    public function uploadNominationDocument(Request $request): void
    {
        $request->merge(['doctype' => $request->input('doctype', 'nomination')]);
        $this->uploadvisadocument($request);
    }

    public function bulkUploadNominationDocuments(Request $request)
    {
        $request->merge(['doctype' => $request->input('doctype', 'nomination')]);

        return $this->bulkUploadVisaDocuments($request);
    }

    /**
     * Upload Visa Document
     */
    public function uploadvisadocument(Request $request) {
        ob_start();
        
        $response = ['status' => false, 'message' => 'Please try again', 'data' => '', 'griddata' => ''];
        $clientid = $request->clientid;
        if ($this->blockEchoUnlessStaffClientAccess((int) $clientid)) {
            ob_end_clean();
            exit;
        }
        $admin_info1 = Admin::select(['id', 'client_id', 'first_name', 'is_company'])->where('id', $clientid)->first();
        $client_unique_id = !empty($admin_info1) ? $admin_info1->client_id : "";
        $client_first_name = !empty($admin_info1)
            ? preg_replace('/[^a-zA-Z0-9_\-]/', '_', (string) ($admin_info1->first_name ?? ''))
            : 'client';
    
        $doctype = isset($request->doctype)? $request->doctype : '';
        $namePrefix = DocumentStoredFilename::storedNamePrefix($admin_info1, $client_first_name);
        
        try {
            if ($request->hasfile('document_upload')) {
                $file = $request->file('document_upload');
                $size = $file->getSize();
                $fileName = $file->getClientOriginalName();
                
                // Allow only letters, numbers, underscores, dashes, spaces, dots, and dollar signs
                if (!DocumentFilenameRules::isAllowed($fileName)) {
                    $response['message'] = DocumentFilenameRules::validationMessage();
                } else {
                    $originalName = $file->getClientOriginalName();
                    $extension = $file->getClientOriginalExtension();

                    // Validate fileid
                    if (empty($request->fileid)) {
                        $response['message'] = 'Document ID is required';
                        ob_end_clean();
                        header('Content-Type: application/json');
                        echo json_encode($response);
                        exit;
                    }

                    // Fetch and validate document
                    $req_file_id = $request->fileid;
                    $obj = Document::query()->find($req_file_id);
                    
                    if (!$obj) {
                        Log::warning('Visa document upload failed: Document not found', [
                            'fileid' => $req_file_id,
                            'clientid' => $clientid,
                            'user_id' => Auth::user()->id ?? 'unknown'
                        ]);
                        $response['message'] = 'Document record not found.';
                        ob_end_clean();
                        header('Content-Type: application/json');
                        echo json_encode($response);
                        exit;
                    }

                    // Validate document belongs to client (security check)
                    if ($obj->client_id != $clientid) {
                        Log::warning('Visa document upload failed: Client mismatch', [
                            'fileid' => $req_file_id,
                            'document_client_id' => $obj->client_id,
                            'request_client_id' => $clientid,
                            'user_id' => Auth::user()->id ?? 'unknown'
                        ]);
                        $response['message'] = 'Document does not belong to this client.';
                        ob_end_clean();
                        header('Content-Type: application/json');
                        echo json_encode($response);
                        exit;
                    }

                    // Validate checklist exists
                    if (empty($obj->checklist)) {
                        Log::warning('Visa document upload failed: Missing checklist', [
                            'fileid' => $req_file_id,
                            'clientid' => $clientid,
                            'user_id' => Auth::user()->id ?? 'unknown'
                        ]);
                        $response['message'] = 'Document checklist not found. Please select a valid checklist.';
                        ob_end_clean();
                        header('Content-Type: application/json');
                        echo json_encode($response);
                        exit;
                    }

                    // Get checklist name right before use to prevent race conditions
                    // Refresh document to get latest checklist name
                    $obj->refresh();
                    $checklistName = $obj->checklist;

                    // Validate checklist name is still present after refresh
                    if (empty($checklistName)) {
                        Log::error('Visa document upload failed: Checklist disappeared during upload', [
                            'fileid' => $req_file_id,
                            'clientid' => $clientid,
                            'user_id' => Auth::user()->id ?? 'unknown'
                        ]);
                        $response['message'] = 'Checklist name not found. Please try again.';
                        ob_end_clean();
                        header('Content-Type: application/json');
                        echo json_encode($response);
                        exit;
                    }

                    // Build new file name: prefix_checklist_timestamp.ext (company name when is_company)
                    $timestamp = time();
                    $name = $namePrefix . "_" . $checklistName . "_" . $timestamp . "." . $extension;

                    $filePath = $client_unique_id . '/' . $doctype . '/' . $name;
                    $fileContent = $this->flattenPdfIfForm956($file, $obj);
                    $this->s3Disk()->put($filePath, $fileContent);
                    $size = strlen($fileContent);

                    // Re-fetch checklist name one more time right before saving to ensure we have the latest
                    $obj->refresh();
                    $finalChecklistName = $obj->checklist;
                    
                    // Use the latest checklist name
                    if (!empty($finalChecklistName) && $finalChecklistName !== $checklistName) {
                        // Checklist changed during upload - rebuild name with same timestamp
                        $checklistName = $finalChecklistName;
                        $name = $namePrefix . "_" . $checklistName . "_" . $timestamp . "." . $extension;
                        // Update file path and move S3 file
                        $newFilePath = $client_unique_id . '/' . $doctype . '/' . $name;
                        if ($newFilePath !== $filePath) {
                            try {
                                // Copy to new path and delete old
                                $this->s3Disk()->copy($filePath, $newFilePath);
                                $this->s3Disk()->delete($filePath);
                                $filePath = $newFilePath;
                                Log::info('Visa document file moved due to checklist change during upload', [
                                    'old_path' => $filePath,
                                    'new_path' => $newFilePath,
                                    'old_checklist' => $checklistName,
                                    'new_checklist' => $finalChecklistName
                                ]);
                            } catch (\Exception $e) {
                                Log::error('Failed to move S3 file after checklist change', [
                                    'old_path' => $filePath,
                                    'new_path' => $newFilePath,
                                    'error' => $e->getMessage()
                                ]);
                                // Continue with old path - at least the file is uploaded
                            }
                        }
                    }

                    $obj->file_name = $namePrefix . "_" . $checklistName . "_" . $timestamp;
                    $obj->filetype = $extension;
                    $obj->user_id = Auth::user()->id;
                    $fileUrl = $this->s3Disk()->url($filePath);
                    $obj->myfile = $fileUrl;
                    $obj->myfile_key = $name;
                    $obj->type = $request->type;
                    $obj->file_size = $size;
                    $obj->doc_type = $doctype;
                    $saved = $obj->save();
                    
                    Log::info('Visa document uploaded successfully', [
                        'fileid' => $req_file_id,
                        'checklist_name' => $checklistName,
                        'file_name' => $name,
                        'clientid' => $clientid,
                        'user_id' => Auth::user()->id ?? 'unknown'
                    ]);
                    
                    if($saved){
                        if($request->type == 'client'){
                            $matterRef = $this->getMatterReference($clientid, $request->client_matter_id ?? null);
                            $docLabel = $doctype === 'nomination' ? 'Nomination' : 'Visa';
                            $subject = !empty($matterRef)
                                ? "uploaded {$docLabel} Document: {$checklistName} - {$matterRef}"
                                : "uploaded {$docLabel} Document: {$checklistName}";
                            $description = '<p>Uploaded '.strtolower($docLabel).' document</p>';
                            
                            $this->logClientActivity(
                                $clientid,
                                $subject,
                                $description,
                                'document'
                            );
                        }

                        //Update date in client matter table
                        if( isset($request->client_matter_id) && $request->client_matter_id != ""){
                            $obj1 = ClientMatter::query()->find($request->client_matter_id);
                            $obj1->updated_at = date('Y-m-d H:i:s');
                            $obj1->save();
                        }
                        
                        $response['status'] = true;
                        $response['message'] = $doctype === 'nomination'
                            ? 'You have successfully uploaded your nomination document'
                            : 'You have successfully uploaded your visa document';
                        $response['filename'] = $name;
                        $response['filetype'] = $extension;
                        $response['fileurl'] = $fileUrl;
                        $response['uploaded_by'] = Auth::user()->first_name ?? 'Staff';
                        $response['uploaded_at'] = $obj->created_at ? $obj->created_at->format('d/m/Y H:i') : now()->format('d/m/Y H:i');
                        $response['filekey'] = $name;
                        $response['doccategory'] = $checklistName;
                        $response['doctype'] = $doctype;
                        $response['visa_doc_cat'] = $request->visa_doc_cat;
                        $response['status_value'] = $obj->status ?? 'draft'; // Add status for JavaScript
                    } else {
                        $response['message'] = 'Failed to save document record.';
                    }
                }
            }
        } catch (\Exception $e) {
            $response['message'] = 'An error occurred: ' . $e->getMessage();
        }
        
        ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }

    /**
     * Check S3 file existence with retry logic to handle eventual consistency and transient failures
     * 
     * @param string $s3Path The S3 path to check
     * @param int $maxRetries Maximum number of retry attempts (default: 3)
     * @param int $initialDelay Initial delay in milliseconds before first retry (default: 500ms)
     * @return array ['exists' => bool, 'attempts' => int, 'last_error' => string|null]
     */
    private function checkS3FileExistsWithRetry($s3Path, $maxRetries = 3, $initialDelay = 500) {
        if (empty($s3Path)) {
            return ['exists' => false, 'attempts' => 0, 'last_error' => 'Empty S3 path'];
        }

        $attempts = 0;
        $lastError = null;
        $delay = $initialDelay;

        for ($i = 0; $i < $maxRetries; $i++) {
            $attempts++;
            
            try {
                // Attempt to check file existence
                $exists = $this->s3Disk()->exists($s3Path);
                
                if ($exists) {
                    // File exists - success!
                    if ($i > 0) {
                        Log::info('S3 file existence confirmed after retry', [
                            's3_path' => $s3Path,
                            'attempts' => $attempts,
                            'retry_count' => $i
                        ]);
                    }
                    return ['exists' => true, 'attempts' => $attempts, 'last_error' => null];
                }
                
                // File doesn't exist - if this is not the last attempt, wait and retry
                if ($i < $maxRetries - 1) {
                    // Use exponential backoff: 500ms, 1000ms, 2000ms
                    usleep($delay * 1000); // Convert milliseconds to microseconds
                    $delay *= 2; // Double the delay for next retry
                } else {
                    // Last attempt failed - file truly doesn't exist
                    Log::debug('S3 file does not exist after all retries', [
                        's3_path' => $s3Path,
                        'attempts' => $attempts
                    ]);
                    return ['exists' => false, 'attempts' => $attempts, 'last_error' => null];
                }
                
            } catch (\Exception $e) {
                $lastError = $e->getMessage();
                
                // Check if this is a retryable error (network, timeout, throttling)
                $retryableErrors = [
                    'timeout', 
                    'Connection', 
                    'Throttling', 
                    '503', 
                    '500',
                    'SlowDown',
                    'RequestTimeout'
                ];
                
                $isRetryable = false;
                foreach ($retryableErrors as $errorPattern) {
                    if (stripos($e->getMessage(), $errorPattern) !== false || 
                        stripos($e->getCode(), $errorPattern) !== false) {
                        $isRetryable = true;
                        break;
                    }
                }
                
                if ($isRetryable && $i < $maxRetries - 1) {
                    // Retryable error - wait and try again
                    Log::warning('S3 file check failed with retryable error, retrying', [
                        's3_path' => $s3Path,
                        'attempt' => $attempts,
                        'error' => $lastError,
                        'retry_delay_ms' => $delay
                    ]);
                    
                    usleep($delay * 1000);
                    $delay *= 2;
                } else {
                    // Non-retryable error or last attempt - fail
                    Log::error('S3 file check failed', [
                        's3_path' => $s3Path,
                        'attempts' => $attempts,
                        'error' => $lastError,
                        'retryable' => $isRetryable
                    ]);
                    return ['exists' => false, 'attempts' => $attempts, 'last_error' => $lastError];
                }
            }
        }

        return ['exists' => false, 'attempts' => $attempts, 'last_error' => $lastError ?: 'Max retries exceeded'];
    }

    /**
     * Rename Document
     */
    public function renamedoc(Request $request) {
        $response = ['status' => false, 'message' => 'Please try again'];
        $id = $request->id;
        $filename = trim($request->filename ?? ''); // new file name without extension

        try {
            // Step 1: Validate inputs
            if (empty($id)) {
                $response['message'] = 'Document ID is required';
                $response['error_type'] = 'missing_id';
                echo json_encode($response);
                return;
            }

            if (empty($filename)) {
                $response['message'] = 'File name cannot be empty';
                $response['error_type'] = 'empty_filename';
                echo json_encode($response);
                return;
            }

            // Step 2: Validate document exists
            if (!\App\Models\Document::query()->where('id', $id)->exists()) {
                Log::warning('Document rename failed: Document not found', [
                    'document_id' => $id,
                    'user_id' => Auth::user()->id ?? 'unknown'
                ]);
                $response['message'] = 'Document not found';
                $response['error_type'] = 'document_not_found';
                echo json_encode($response);
                return;
            }

            $doc = \App\Models\Document::query()->where('id', $id)->first();
            $client_id = $doc->client_id;
            if ($this->blockEchoUnlessStaffClientAccess((int) $client_id)) {
                return;
            }
            $doc_type = $doc->doc_type ?? '';

            // Step 3: Check if this is a checklist-only document (no file uploaded)
            if (empty($doc->file_name) && empty($doc->myfile_key) && empty($doc->myfile)) {
                Log::info('Document rename: Checklist-only document, no file to rename', [
                    'document_id' => $id,
                    'checklist' => $doc->checklist ?? 'N/A'
                ]);
                $response['message'] = 'This is a checklist item only. No file to rename. Use checklist rename instead.';
                $response['error_type'] = 'checklist_only';
                echo json_encode($response);
                return;
            }

            // Step 4: Get and validate extension
            $extension = $doc->filetype ?? '';
            
            // If extension is missing but myfile_key exists, try to extract it
            if (empty($extension) && !empty($doc->myfile_key)) {
                $extension = pathinfo($doc->myfile_key, PATHINFO_EXTENSION);
            }

            // Step 5: Get client unique id for S3 path
            $admin = \App\Models\Admin::select('client_id')->where('id', $client_id)->first();
            $client_unique_id = $admin ? ($admin->client_id ?? '') : '';

            // Step 6: Validate client_unique_id
            if (empty($client_unique_id)) {
                Log::warning('Document rename failed: Client ID not found', [
                    'document_id' => $id,
                    'client_id' => $client_id,
                    'user_id' => Auth::user()->id ?? 'unknown'
                ]);
                $response['message'] = 'Client ID not found. Cannot rename document.';
                $response['error_type'] = 'missing_client_id';
                echo json_encode($response);
                return;
            }

            // Step 7: Get old S3 key
            $oldKey = $doc->myfile_key ?? '';

            // Step 8: Build new key and S3 paths
            // Use unique number at the end only (same convention as upload: name_timestamp.ext)
            // so download filename does not show unique number twice (at start and end).
            $timestamp = time();
            if (!empty($extension)) {
                $newKey = $filename . '_' . $timestamp . '.' . $extension;
            } else {
                $newKey = $filename . '_' . $timestamp;
            }
            
            $newS3Path = $client_unique_id . '/' . $doc_type . '/' . $newKey;
            $oldS3Path = '';
            
            // Only build old path if we have the key
            if (!empty($oldKey)) {
                $oldS3Path = $client_unique_id . '/' . $doc_type . '/' . $oldKey;
            }

            // Step 9: Attempt S3 file rename if file exists (with retry logic)
            $s3FileExists = false;
            $s3RenameSuccess = false;
            $updateDbOnly = false;
            $fileCheckResult = ['exists' => false, 'attempts' => 0, 'last_error' => null];

            if (!empty($oldS3Path)) {
                // Use retry logic to check file existence (handles eventual consistency and transient failures)
                $fileCheckResult = $this->checkS3FileExistsWithRetry($oldS3Path, 3, 500);
                $s3FileExists = $fileCheckResult['exists'];
                
                Log::info('S3 file existence check completed', [
                    'document_id' => $id,
                    's3_path' => $oldS3Path,
                    'exists' => $s3FileExists,
                    'attempts' => $fileCheckResult['attempts'],
                    'last_error' => $fileCheckResult['last_error'] ?? null
                ]);
            }

            if ($s3FileExists) {
                try {
                    // Attempt to copy first
                    $copySuccess = $this->s3Disk()->copy($oldS3Path, $newS3Path);
                    
                    if ($copySuccess) {
                        // Only delete original if copy was successful
                        $this->s3Disk()->delete($oldS3Path);
                        $s3RenameSuccess = true;
                        
                        Log::info('Document renamed on S3 successfully', [
                            'document_id' => $id,
                            'old_path' => $oldS3Path,
                            'new_path' => $newS3Path,
                            'user_id' => Auth::user()->id ?? 'unknown'
                        ]);
                    } else {
                        // Copy failed, don't proceed with database update
                        Log::error('S3 copy failed: Copy operation returned false', [
                            'document_id' => $id,
                            'old_path' => $oldS3Path,
                            'new_path' => $newS3Path
                        ]);
                        $response['status'] = false;
                        $response['message'] = 'Failed to copy file. Please try again.';
                        $response['error_type'] = 's3_copy_failed';
                        echo json_encode($response);
                        return;
                    }
                } catch (\Exception $e) {
                    // Log the error for debugging
                    Log::error('S3 copy failed: Exception occurred', [
                        'document_id' => $id,
                        'old_path' => $oldS3Path,
                        'new_path' => $newS3Path,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    
                    $response['status'] = false;
                    $response['message'] = 'File operation failed. Please try again.';
                    $response['error_type'] = 's3_exception';
                    echo json_encode($response);
                    return;
                }
            } else {
                // File doesn't exist on S3 (confirmed after retries) - use fallback: update DB only
                $updateDbOnly = true;
                Log::warning('Document rename: S3 file not found after retry attempts, updating database only', [
                    'document_id' => $id,
                    'old_s3_path' => $oldS3Path,
                    'old_key' => $oldKey,
                    'new_filename' => $filename,
                    'user_id' => Auth::user()->id ?? 'unknown',
                    'has_myfile_key' => !empty($oldKey),
                    'has_myfile' => !empty($doc->myfile),
                    'check_attempts' => $fileCheckResult['attempts'] ?? 0,
                    'last_error' => $fileCheckResult['last_error'] ?? null
                ]);
            }

            // Step 10: Update database
            $updateData = ['file_name' => $filename];
            
            if ($s3RenameSuccess) {
                // File was successfully renamed on S3, update all fields
                $newS3Url = $this->s3Disk()->url($newS3Path);
                $updateData['myfile'] = $newS3Url;
                $updateData['myfile_key'] = $newKey;
            } else if ($updateDbOnly) {
                // File doesn't exist on S3, only update file_name
                // Keep existing myfile and myfile_key unchanged
                // This allows the document name to be updated even if file is missing
            }

            $res = DB::table('documents')->where('id', $id)->update($updateData);

            if ($res) {
                // Log activity for document rename
                $oldName = $doc->file_name ?? 'N/A';
                $matterRef = $this->getMatterReference($client_id);
                $subject = !empty($matterRef) 
                    ? "renamed Document - {$matterRef}"
                    : "renamed Document";
                
                $renameNote = $updateDbOnly 
                    ? " (Note: Original file not found on server, name updated in database only)"
                    : "";
                
                $description = "<p>Renamed {$doc_type} document from '{$oldName}' to '{$filename}'{$renameNote}</p>";

                $this->logClientActivity(
                    $client_id,
                    $subject,
                    $description,
                    'document'
                );
                
                $response['status'] = true;
                $response['data'] = 'Document saved successfully';
                $response['Id'] = $id;
                $response['filename'] = $filename;
                $response['filetype'] = $doc->filetype ?? '';

                // Include file URL only if file was renamed on S3
                if ($s3RenameSuccess) {
                    $response['fileurl'] = $this->s3Disk()->url($newS3Path);
                } else if (!empty($doc->myfile)) {
                    // Keep existing URL if available
                    $response['fileurl'] = $doc->myfile;
                }

                // Add warning if file wasn't renamed on S3
                if ($updateDbOnly) {
                    $response['warning'] = 'Document name updated. Note: Original file not found on server.';
                    $response['message'] = 'Document name updated successfully. Original file not found on server.';
                } else {
                    $response['message'] = 'Document renamed successfully';
                }

                if ($doc->doc_type == 'personal') {
                    $response['folder_name'] = 'preview-container-' . ($doc->folder_name ?? '');
                } else if ($doc->doc_type == 'visa') {
                    $response['folder_name'] = 'preview-container-migdocumnetlist';
                } else if ($doc->doc_type == 'nomination') {
                    $response['folder_name'] = 'preview-container-nomdocumnetlist';
                }
            } else {
                Log::error('Document rename failed: Database update failed', [
                    'document_id' => $id,
                    'update_data' => $updateData
                ]);
                $response['status'] = false;
                $response['message'] = 'Failed to update document. Please try again.';
                $response['error_type'] = 'db_update_failed';
            }
        } catch (\Exception $e) {
            Log::error('Document rename failed: Unexpected exception', [
                'document_id' => $id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => Auth::user()->id ?? 'unknown'
            ]);
            $response['status'] = false;
            $response['message'] = 'An unexpected error occurred. Please try again.';
            $response['error_type'] = 'unexpected_error';
        }

        echo json_encode($response);
    }

    /**
     * Delete Document
     */
    public function deletedocs(Request $request) {
        $response = ['status' => false, 'message' => 'Please try again'];
        $data = null;
        
        try {
            $note_id = $request->note_id;
            if(\App\Models\Document::query()->where('id',$note_id)->exists()){
            $data = DB::table('documents')->where('id', @$note_id)->first();
            if ($this->blockEchoUnlessStaffClientAccess((int) ($data->client_id ?? 0))) {
                return;
            }
            $admin = DB::table('admins')->select('client_id')->where('id', @$data->client_id)->first();
            $res = DB::table('documents')->where('id', @$note_id)->delete();
            //$this->s3Disk()->delete('documents/' . $data->myfile);
            if($data->doc_type == 'migration') {
                $this->s3Disk()->delete($admin->client_id.'/'.$data->doc_type.'/'.$data->myfile_key);
            } else {
                $this->s3Disk()->delete($admin->client_id.'/'.$data->doc_type.'/'.$data->myfile_key);
            }
            if($res){
                $documentName = $data->file_name ?? 'unknown';
                $documentType = ucfirst($data->doc_type ?? 'Document');
                $matterRef = $this->getMatterReference($data->client_id);
                $subject = !empty($matterRef) 
                    ? "deleted {$documentType}: {$documentName} - {$matterRef}"
                    : "deleted {$documentType}: {$documentName}";
                $description = "<p>Deleted {$documentType} document</p>";

                $this->logClientActivity(
                    $data->client_id,
                    $subject,
                    $description,
                    'document'
                );
                $response['status'] 	= 	true;
                $response['data']	=	'Document removed successfully';
                if(isset($data->doc_type) && $data->doc_type == 'personal'){
                    $response['doc_categry']	= $data->folder_name;
                } else {
                    $response['doc_categry']	= "";
                }
            }else{
                $response['status'] 	= 	false;
                $response['message']	=	'Please try again';
                if(isset($data->doc_type) && $data->doc_type == 'personal'){
                    $response['doc_categry']	= $data->folder_name;
                } else {
                    $response['doc_categry']	= "";
                }
            }
        } else {
            $response['status'] 	= 	false;
            $response['message']	=	'Please try again';
            if(isset($data->doc_type) && $data->doc_type == 'personal'){
                $response['doc_categry']	= $data->folder_name;
            } else {
                $response['doc_categry']	= "";
            }
        }
        } catch (\Exception $e) {
            Log::error('Error deleting document', [
                'document_id' => $request->note_id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $response['status'] = false;
            $response['message'] = 'An error occurred. Please try again.';
        }
        echo json_encode($response);
    }

    /**
     * BUGFIX #3: Move Document to different folder/category/matter
     * Allows moving documents between:
     * - Personal document categories
     * - Visa document matters
     * - Personal to Visa (with matter selection)
     * - Visa to Personal (with category selection)
     */
    public function moveDocument(Request $request) {
        $response = ['status' => false, 'message' => 'Please try again'];
        
        try {
            // Validate required fields
            $request->validate([
                'document_id' => 'required|integer',
                'target_type' => 'required|in:personal,visa,nomination',
                'target_id' => 'required|integer', // Category ID for both personal and visa
                'target_matter_id' => 'nullable|integer', // Client matter id when moving to visa (e.g. Personal → Visa)
            ]);
            
            $documentId = $request->document_id;
            $targetType = $request->target_type;
            $targetId = $request->target_id;
            
            // Get the document
            $document = \App\Models\Document::query()->find($documentId);
            
            if (!$document) {
                $response['message'] = 'Document not found';
                return response()->json($response);
            }

            if ($deny = $this->denyJsonUnlessStaffClientAccess((int) $document->client_id)) {
                return $deny;
            }
            
            // Check user permission (basic check - user must be authenticated)
            if (!Auth::check()) {
                $response['message'] = 'Unauthorized';
                return response()->json($response, 403);
            }
            
            // Store old values for activity log
            $oldType = $document->doc_type;
            $oldFolderName = $document->folder_name;
            $oldMatterId = $document->client_matter_id;
            $oldChecklistName = $document->checklist;
            
            // Update document based on target type
            $targetName = '';
            if ($targetType === 'personal') {
                // Moving to Personal Documents
                // Verify target category exists
                $category = \App\Models\PersonalDocumentType::query()->find($targetId);
                if (!$category) {
                    $response['message'] = 'Target category not found';
                    return response()->json($response);
                }
                
                $document->type       = 'client';
                $document->doc_type   = 'personal';
                $document->folder_name = $targetId;
                $document->client_matter_id = null; // Clear matter association
                $document->cp_list_id = null;       // Remove from workflow checklist scope
                
                $targetName = $category->title;
                
            } elseif ($targetType === 'visa') {
                // Moving to Visa Documents - targetId is the CATEGORY ID
                // Get the category to find its matter
                $category = \App\Models\VisaDocumentType::query()->find($targetId);
                if (!$category) {
                    $response['message'] = 'Target visa category not found';
                    return response()->json($response);
                }
                
                $document->type       = 'client';
                $document->doc_type   = 'visa';
                $document->folder_name = $targetId; // Category ID
                // When the UI sends target_matter_id (e.g. Personal → Visa), scope the document to that matter.
                // Otherwise keep previous behaviour: category row matter, else existing document matter.
                $requestedMatterRaw = $request->input('target_matter_id');
                if ($requestedMatterRaw !== null && $requestedMatterRaw !== '') {
                    $requestedMatterId = (int) $requestedMatterRaw;
                    $clientMatter = ClientMatter::query()->where('id', $requestedMatterId)
                        ->where('client_id', $document->client_id)
                        ->first();
                    if (! $clientMatter) {
                        $response['message'] = 'The selected matter does not belong to this client.';
                        return response()->json($response);
                    }
                    $document->client_matter_id = $requestedMatterId;
                } else {
                    $document->client_matter_id = $category->client_matter_id ?? $document->client_matter_id;
                }
                $document->cp_list_id = null;       // Remove from workflow checklist scope
                
                $targetName = $category->title;
            } elseif ($targetType === 'nomination') {
                $category = NominationDocumentType::query()->find($targetId);
                if (!$category) {
                    $response['message'] = 'Target nomination category not found';
                    return response()->json($response);
                }

                $document->type = 'client';
                $document->doc_type = 'nomination';
                $document->folder_name = $targetId;
                $document->client_matter_id = $category->client_matter_id ?? $document->client_matter_id;
                $document->cp_list_id = null;

                $targetName = $category->title;
            }
            
            $document->updated_at = now();
            $saved = $document->save();
            
            if ($saved) {
                // Log activity
                $documentName = $document->file_name ?? $document->checklist ?? 'Document';
                $oldLane = $oldType === 'personal' ? 'Personal' : ($oldType === 'nomination' ? 'Nomination' : 'Visa');
                $newLane = $targetType === 'personal' ? 'Personal' : ($targetType === 'nomination' ? 'Nomination' : 'Visa');
                $oldLocation = $oldType === 'personal'
                    ? ($oldFolderName ? "Personal (Category: {$oldFolderName})" : 'Personal')
                    : ($oldChecklistName ? "{$oldLane} ({$oldChecklistName})" : $oldLane);

                $newLocation = $targetType === 'personal' ? "Personal ({$targetName})" : "{$newLane} ({$targetName})";
                
                $matterRef = $this->getMatterReference($document->client_id, $document->client_matter_id);
                $subject = !empty($matterRef) 
                    ? "moved document: {$documentName} - {$matterRef}"
                    : "moved document: {$documentName}";
                $description = "<p>Document moved from <strong>{$oldLocation}</strong> to <strong>{$newLocation}</strong></p>";
                
                $this->logClientActivity(
                    $document->client_id,
                    $subject,
                    $description,
                    'document'
                );
                
                $response['status'] = true;
                $response['message'] = "Document moved successfully to {$newLocation}";
                $response['document_id'] = $documentId;
                $response['new_type'] = $targetType;
                $response['new_location'] = $targetName;
            } else {
                $response['message'] = 'Failed to save document changes';
            }
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            $response['message'] = 'Invalid input: ' . implode(', ', $e->errors());
            Log::warning('Document move validation failed', [
                'document_id' => $request->document_id ?? null,
                'errors' => $e->errors()
            ]);
        } catch (\Exception $e) {
            Log::error('Error moving document', [
                'document_id' => $request->document_id ?? null,
                'target_type' => $request->target_type ?? null,
                'target_id' => $request->target_id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $response['message'] = 'An error occurred while moving the document: ' . $e->getMessage();
        }
        
        return response()->json($response);
    }

    /**
     * BUGFIX #3: Get visa categories for a specific matter
     * Returns all visa document categories for the given client and matter
     */
    public function getVisaCategories(Request $request) {
        try {
            $clientId = $request->client_id;
            $matterId = $request->matter_id;
            
            if (!$clientId || !$matterId) {
                return response()->json([]);
            }

            if ($deny = $this->denyJsonUnlessStaffClientAccess((int) $clientId)) {
                return $deny;
            }
            
            // Get visa document categories for this client and matter
            $categories = \App\Models\VisaDocumentType::select(['id', 'title', 'client_id', 'client_matter_id'])
                ->where('status', 1)
                ->where(function($query) use ($clientId, $matterId) {
                    $query->where(function($q) {
                            // Global categories (both NULL)
                            $q->whereNull('client_id')
                              ->whereNull('client_matter_id');
                        })
                        ->orWhere(function($q) use ($clientId) {
                            // Client-specific categories (matter NULL)
                            $q->where('client_id', $clientId)
                              ->whereNull('client_matter_id');
                        })
                        ->orWhere(function($q) use ($clientId, $matterId) {
                            // Matter-specific categories
                            $q->where('client_id', $clientId)
                              ->where('client_matter_id', $matterId);
                        });
                })
                ->orderBy('id', 'ASC')
                ->get();
            
            return response()->json($categories);
            
        } catch (\Exception $e) {
            Log::error('Error getting visa categories', [
                'client_id' => $request->client_id ?? null,
                'matter_id' => $request->matter_id ?? null,
                'error' => $e->getMessage()
            ]);
            return response()->json([]);
        }
    }

    public function getNominationCategories(Request $request)
    {
        try {
            $clientId = $request->client_id;
            $matterId = $request->matter_id;

            if (!$clientId || !$matterId) {
                return response()->json([]);
            }

            if ($deny = $this->denyJsonUnlessStaffClientAccess((int) $clientId)) {
                return $deny;
            }

            $categories = NominationDocumentType::select(['id', 'title', 'client_id', 'client_matter_id'])
                ->where('status', 1)
                ->where(function ($query) use ($clientId, $matterId) {
                    $query->where(function ($q) {
                        $q->whereNull('client_id')
                            ->whereNull('client_matter_id');
                    })
                        ->orWhere(function ($q) use ($clientId) {
                            $q->where('client_id', $clientId)
                                ->whereNull('client_matter_id');
                        })
                        ->orWhere(function ($q) use ($clientId, $matterId) {
                            $q->where('client_id', $clientId)
                                ->where('client_matter_id', $matterId);
                        });
                })
                ->orderBy('id', 'ASC')
                ->get();

            return response()->json($categories);
        } catch (\Exception $e) {
            Log::error('Error getting nomination categories', [
                'client_id' => $request->client_id ?? null,
                'matter_id' => $request->matter_id ?? null,
                'error' => $e->getMessage(),
            ]);

            return response()->json([]);
        }
    }


    /**
     * Get Visa Checklist
     */
    public function getvisachecklist(Request $request) {
        // DISABLED: VisaDocChecklist model has been removed
        // Visa checklist functionality disabled - VisaDocChecklist model has been removed
        $response = [
            'status' => false, 
            'message' => 'Visa checklist functionality has been disabled - VisaDocChecklist model has been removed', 
            'visaCheckListInfo' => []
        ];
        echo json_encode($response);
        return;
    }

    /**
     * Mark Document as Not Used
     */
    public function notuseddoc(Request $request) {
        $response = ['status' => false, 'message' => 'Please try again'];
        
        try {
            $doc_id = $request->doc_id;
            $doc_type = $request->doc_type;
            if(\App\Models\Document::query()->where('id',$doc_id)->exists()){
            $docInfo = \App\Models\Document::with(['staff'])->where('id',$doc_id)->first();
            if ($this->blockEchoUnlessStaffClientAccess((int) ($docInfo->client_id ?? 0))) {
                return;
            }

            $alreadyNotUsed = (int) ($docInfo->not_used_doc ?? 0) === 1;
            if (!$alreadyNotUsed) {
                DB::table('documents')->where('id', $doc_id)->update(['not_used_doc' => 1]);
                $docInfo->not_used_doc = 1;

                $matterRef = $this->getMatterReference($docInfo->client_id);
                $subject = !empty($matterRef)
                    ? "moved {$doc_type} Document to Not Used - {$matterRef}"
                    : "moved {$doc_type} Document to Not Used";
                $description = '<p>Document moved to Not Used tab</p>';

                $this->logClientActivity(
                    $docInfo->client_id,
                    $subject,
                    $description,
                    'document'
                );
            }

            $response = $this->buildNotUsedDocumentJsonResponse($docInfo, $doc_type, $doc_id);
        } else {
            $response['status'] = false;
            $response['message'] = 'Please try again';
            $response['doc_type'] = "";
            $response['doc_id'] = "";
            $response['docInfo'] = "";
            $response['doc_category'] = "";
            $response['Added_By'] = "";
            $response['Added_date'] = "";
            $response['Verified_By'] = "";
            $response['Verified_At'] = "";
        }
        } catch (\Exception $e) {
            Log::error('Error marking document as not used', [
                'doc_id' => $request->doc_id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $response['status'] = false;
            $response['message'] = 'An error occurred. Please try again.';
        }
        echo json_encode($response);
    }

    /**
     * Rename Checklist in Document
     */
    public function renamechecklistdoc(Request $request) {
        $response = ['status' => false, 'message' => 'Please try again'];
        
        try {
            $id = $request->id;
            $checklist = $request->checklist;
            if(\App\Models\Document::query()->where('id',$id)->exists()){
            $doc = \App\Models\Document::query()->where('id',$id)->first();
            if ($this->blockEchoUnlessStaffClientAccess((int) ($doc->client_id ?? 0))) {
                return;
            }
            $res = DB::table('documents')->where('id', @$id)->update(['checklist' => $checklist]);
            if($res){
                // Build complete HTML structure to restore UI state (actions via right-click only)
                $html = '<span style="flex: 1;">' . htmlspecialchars($checklist) . '</span>';
                
                $response['status'] = true;
                $response['data'] = 'Checklist saved successfully';
                $response['message'] = 'Checklist saved successfully';
                $response['Id'] = $id;
                $response['checklist'] = $checklist;
                $response['html'] = $html;
            }else{
                $response['status'] = false;
                $response['message'] = 'Please try again';
            }
        }else{
            $response['status'] = false;
            $response['message'] = 'Please try again';
        }
        } catch (\Exception $e) {
            Log::error('Error renaming checklist', [
                'document_id' => $request->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $response['status'] = false;
            $response['message'] = 'An error occurred. Please try again.';
        }
        echo json_encode($response);
    }

    /**
     * Move Document Back from Not Used
     */
    public function backtodoc(Request $request) {
        $response = ['status' => false, 'message' => 'Please try again'];
        
        try {
            $doc_id = $request->doc_id;
            $doc_type = $request->doc_type;
            if(\App\Models\Document::query()->where('id',$doc_id)->exists()){
            $docInfo = \App\Models\Document::with(['staff'])->where('id',$doc_id)->first();
            if ($this->blockEchoUnlessStaffClientAccess((int) ($docInfo->client_id ?? 0))) {
                return;
            }
            $upd = DB::table('documents')->where('id', $doc_id)->update(array('not_used_doc' => null));
            if($upd){
                $matterRef = $this->getMatterReference($docInfo->client_id);
                $subject = !empty($matterRef) 
                    ? "restored {$doc_type} Document - {$matterRef}"
                    : "restored {$doc_type} Document";
                $description = "<p>Document moved back to {$doc_type} tab</p>";
                
                $this->logClientActivity(
                    $docInfo->client_id,
                    $subject,
                    $description,
                    'document'
                );

                if($docInfo){
                    if( isset($docInfo->user_id) && $docInfo->user_id!= "" && $docInfo->staff ){
                        $response['Added_By'] = $docInfo->staff->first_name;
                        $response['Added_date'] = date('d/m/Y',strtotime($docInfo->created_at));
                    } else {
                        $response['Added_By'] = "N/A";
                        $response['Added_date'] = "N/A";
                    }

                    $response['Verified_By'] = "N/A";
                    $response['Verified_At'] = "N/A";
                }

                $response['docInfo'] = $docInfo;
                $response['doc_type'] = $doc_type;
                $response['doc_id'] = $doc_id;
                $response['status'] = 	true;
                $response['data']	=	$doc_type.' document moved to '.$doc_type.' document tab';
            } else {
                $response['status'] 	= 	false;
                $response['message']	=	'Please try again';
                $response['doc_type'] = "";
                $response['doc_id'] = "";
                $response['docInfo'] = "";

                $response['Added_By'] = "";
                $response['Added_date'] = "";
            $response['Verified_By'] = "";
            $response['Verified_At'] = "";
        }
        } else {
            $response['status'] 	= 	false;
            $response['message']	=	'Please try again';
            $response['doc_type'] = "";
            $response['doc_id'] = "";
            $response['docInfo'] = "";

            $response['Added_By'] = "";
            $response['Added_date'] = "";
            $response['Verified_By'] = "";
            $response['Verified_At'] = "";
        }
        } catch (\Exception $e) {
            Log::error('Error moving document back from not used', [
                'doc_id' => $request->doc_id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $response['status'] = false;
            $response['message'] = 'An error occurred. Please try again.';
        }
        echo json_encode($response);
    }

    /**
     * Delete Checklist Item (only if no file uploaded)
     */
    public function deleteChecklist(Request $request) {
        $response = ['status' => false, 'message' => 'Please try again'];
        
        try {
            $checklist_id = $request->id;
            
            if (!$checklist_id) {
                $response['message'] = 'Checklist ID is required';
                return response()->json($response);
            }
            
            $document = Document::query()->find($checklist_id);
            
            if (!$document instanceof Document) {
                $response['message'] = 'Checklist not found';
                return response()->json($response);
            }

            if ($deny = $this->denyJsonUnlessStaffClientAccess((int) $document->client_id)) {
                return $deny;
            }
            
            // Only allow deletion if no file has been uploaded
            if ($document->file_name || $document->myfile) {
                $response['message'] = 'Cannot delete checklist with uploaded file. Please remove the file first.';
                return response()->json($response);
            }
            
            $checklistName = $document->checklist;
            $clientId = $document->client_id;
            $folderName = $document->folder_name;
            $form956Id = $document->form956_id;
            
            // Delete the document record
            /** @disregard P1005 Intelephense: Model::delete() is 0-arg; can be confused with Query Builder. */
            $deleted = $document->delete();
            
            if ($deleted) {
                if ($form956Id && !Document::query()->where('form956_id', $form956Id)->exists()) {
                    Form956::query()->where('id', $form956Id)->delete();
                }

                // Log activity
                $matterRef = $this->getMatterReference($clientId);
                $subject = !empty($matterRef) 
                    ? "deleted Checklist: {$checklistName} - {$matterRef}"
                    : "deleted Checklist: {$checklistName}";
                $description = "<p>Deleted personal document checklist</p>";

                $this->logClientActivity(
                    $clientId,
                    $subject,
                    $description,
                    'document'
                );
                
                $response['status'] = true;
                $response['message'] = 'Checklist deleted successfully';
                $response['folder_name'] = $folderName;
            } else {
                $response['message'] = 'Failed to delete checklist';
            }
        } catch (\Exception $e) {
            Log::error('Error deleting checklist', [
                'checklist_id' => $request->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $response['status'] = false;
            $response['message'] = 'An error occurred. Please try again.';
        }
        
        return response()->json($response);
    }

    /**
     * Download Document (S3 Temporary URL)
     */
    public function download_document(Request $request) {
        $fileUrl = $request->input('filelink');
        $filename = $request->input('filename', 'downloaded.pdf');

        if (!$fileUrl) {
            return abort(400, 'Missing file URL');
        }

        // Form 956: filelink is our forms.pdf route URL (e.g. http://app/forms/123/pdf) - redirect directly
        $path = parse_url($fileUrl, PHP_URL_PATH);
        if (filter_var($fileUrl, FILTER_VALIDATE_URL) && $path && preg_match('#/forms/\d+/(?:pdf|preview)#', $path)) {
            return redirect()->away($fileUrl);
        }

        try {
            // Extract S3 key from the URL
            $parsed = parse_url($fileUrl);
            if (!isset($parsed['path'])) {
                return abort(400, 'Invalid S3 URL format');
            }

            // Personal documents only: if a legacy key starts with a unique numeric prefix,
            // move that number to the end for download filename display.
            $path = (string) $parsed['path'];
            if (strpos($path, '/personal/') !== false) {
                $filename = $this->normalizePersonalDownloadFilename($filename);
            }
            
            $s3Key = ltrim(urldecode($parsed['path']), '/');
            
            // Check if file exists in S3
            if (!$this->s3Disk()->exists($s3Key)) {
                return abort(404, 'File not found in S3');
            }
            
            // Generate temporary URL with proper headers
            $tempUrl = $this->s3Disk()->temporaryUrl(
                $s3Key,
                now()->addMinutes(5), // 5 minutes expiration
                [
                    'ResponseContentDisposition' => 'attachment; filename="' . $filename . '"',
                    'ResponseContentType' => 'application/pdf'
                ]
            );
            
            // Redirect to S3 temporary URL
            return redirect($tempUrl);
            
        } catch (\Exception $e) {
            Log::error('S3 download error: ' . $e->getMessage());
            return abort(500, 'Error generating download link');
        }
    }

    /**
     * Convert legacy personal download filename format from:
     *   1234567890_original_name.ext
     * to:
     *   original_name_1234567890.ext
     *
     * Leaves current/valid filenames unchanged.
     */
    private function normalizePersonalDownloadFilename(string $filename): string
    {
        $filename = trim($filename);
        if ($filename === '') {
            return $filename;
        }

        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        $base = pathinfo($filename, PATHINFO_FILENAME);

        // Match leading numeric token with optional separator: 123..., 123_..., 123-..., 123 ...
        if (!preg_match('/^(\d{8,})(?:[_\-\s]+)?(.+)$/', $base, $matches)) {
            return $filename;
        }

        $unique = trim($matches[1]);
        $rest = trim($matches[2] ?? '');
        if ($rest === '') {
            return $filename;
        }

        // Avoid duplicate suffix if already present.
        if (preg_match('/(?:_|-|\s)' . preg_quote($unique, '/') . '$/', $rest)) {
            $newBase = $rest;
        } else {
            $newBase = $rest . '_' . $unique;
        }

        return $ext !== '' ? ($newBase . '.' . $ext) : $newBase;
    }

    /**
     * Add Personal Document Category
     */
    public function addPersonalDocCategory(Request $request) {
        $categoryTitle = trim($request->input('personal_doc_category'));
        $clientId = $request->input('clientid');

        $request->merge(['personal_doc_category' => $categoryTitle]);

        // Basic validation
        $validator = Validator::make($request->all(), [
            'personal_doc_category' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first('personal_doc_category')
            ]);
        }

        if ($clientId !== null && $clientId !== '' && is_numeric($clientId)) {
            if ($deny = $this->denyJsonUnlessStaffClientAccess((int) $clientId)) {
                return $deny;
            }
        }

        // RULE 1: If status=1 and client_id is NULL, title must be unique globally (only one)
        $existsForNullClient = PersonalDocumentType::query()->where('title', $categoryTitle)
            ->where('status', 1)
            ->whereNull('client_id')
            ->exists();

        if ($existsForNullClient) {
            return response()->json([
                'status' => false,
                'message' => 'This category already exists globally (for NULL client).'
            ]);
        }

        // RULE 2: Same title with status=1 for same client_id is not allowed
        $existsForSameClient = PersonalDocumentType::query()->where('title', $categoryTitle)
            ->where('status', 1)
            ->where('client_id', $clientId)
            ->exists();

        if ($existsForSameClient) {
            return response()->json([
                'status' => false,
                'message' => 'This category already exists for this client.'
            ]);
        }

        try {
            $category = new PersonalDocumentType();
            $category->title = $categoryTitle;
            $category->status = 1;
            $category->type = 'personal';
            $category->client_id = $clientId ?? null;
            $category->save();

            $canDelete = Auth::check()
                && in_array(
                    (int) (Auth::user()->role ?? 0),
                    config('crm.personal_document_category_delete_role_ids', [1, 16]),
                    true
                );

            return response()->json([
                'status' => true,
                'message' => 'Personal Document Category added successfully.',
                'id' => $category->id,
                'title' => $category->title,
                'can_delete' => $canDelete,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error adding category: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Update Personal Document Category
     */
    public function updatePersonalDocCategory(Request $request) {
        $request->validate([
            'id' => 'required|exists:personal_document_types,id',
            'title' => 'required|string|max:255',
        ]);

        $category = PersonalDocumentType::findOrFail($request->id);
        $clientId = $category->client_id; // Get the client_id of the category being updated

        if ($clientId !== null && $clientId !== '' && is_numeric($clientId)) {
            if ($deny = $this->denyJsonUnlessStaffClientAccess((int) $clientId)) {
                return $deny;
            }
        }

        // Check if the category is client-generated
        if ($category->client_id === null) {
            return response()->json(['success' => false, 'message' => 'Only client-generated categories can be updated.']);
        }

        $categoryTitle = trim($request->input('title'));

        // RULE 1: If status=1 and client_id is NULL, title must be unique globally (only one)
        $existsForNullClient = PersonalDocumentType::query()->where('title', $categoryTitle)
            ->where('status', 1)
            ->whereNull('client_id')
            ->exists();

        if ($existsForNullClient) {
            return response()->json([
                'status' => false,
                'message' => 'This category already exists globally for all client.Pls try other.'
            ]);
        }

        // RULE 2: Same title with status=1 for same client_id is not allowed (excluding the current category)
        $existsForSameClient = PersonalDocumentType::query()->where('title', $categoryTitle)
            ->where('status', 1)
            ->where('client_id', $clientId)
            ->where('id', '!=', $category->id) // Exclude the current category
            ->exists();

        if ($existsForSameClient) {
            return response()->json([
                'status' => false,
                'message' => 'This category already exists for this client.Pls try other.'
            ]);
        }

        try {
            $category->title = $categoryTitle;
            $category->save();

            return response()->json(['status' => true,'message' => 'This category is updated successfully.']);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Error updating category: ' . $e->getMessage()]);
        }
    }

    /**
     * Add Visa Document Category
     */
    public function addVisaDocCategory(Request $request) {
        $categoryTitle = trim($request->input('visa_doc_category'));
        $clientId = $request->input('clientid');
        $clientMatterId = $request->input('clientmatterid');

        $request->merge(['visa_doc_category' => $categoryTitle]);

        // Basic validation
        $validator = Validator::make($request->all(), [
            'visa_doc_category' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first('visa_doc_category')
            ]);
        }

        if ($clientId !== null && $clientId !== '' && is_numeric($clientId)) {
            if ($deny = $this->denyJsonUnlessStaffClientAccess((int) $clientId)) {
                return $deny;
            }
        }

        // RULE 1: If status=1 and client_id is NULL, title must be unique globally (only one)
        $existsForNullClient = VisaDocumentType::query()->where('title', $categoryTitle)
            ->where('status', 1)
            ->whereNull('client_matter_id')
            ->whereNull('client_id')
            ->exists();

        if ($existsForNullClient) {
            return response()->json([
                'status' => false,
                'message' => 'This category already exists globally.'
            ]);
        }

        // RULE 2: Same title with status=1 for same client_id is not allowed
        $existsForSameClient = VisaDocumentType::query()->where('title', $categoryTitle)
            ->where('status', 1)
            ->where('client_matter_id', $clientMatterId)
            ->exists();

        if ($existsForSameClient) {
            return response()->json([
                'status' => false,
                'message' => 'This category already exists for this client matter.'
            ]);
        }

        try {
            $category = new VisaDocumentType();
            $category->title = $categoryTitle;
            $category->status = 1;
            $category->client_id = $clientId ?? null;
            $category->client_matter_id = $clientMatterId ?? null;
            $category->save();

            $canDelete = Auth::check()
                && in_array(
                    (int) (Auth::user()->role ?? 0),
                    config('crm.visa_document_category_delete_role_ids', [1, 16]),
                    true
                );

            return response()->json([
                'status' => true,
                'message' => 'Visa Document Category added successfully.',
                'id' => $category->id,
                'title' => $category->title,
                'can_delete' => $canDelete,
                'client_matter_id' => $category->client_matter_id,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error adding category: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Update Visa Document Category  
     */
    public function updateVisaDocCategory(Request $request) {
        $request->validate([
            'id' => 'required|exists:visa_document_types,id',
            'title' => 'required|string|max:255',
        ]);

        $category = VisaDocumentType::findOrFail($request->id);
        $clientMatterId = $category->client_matter_id; // Get the client_id of the category being updated

        // Check if the category is client-generated
        if ($category->client_matter_id === null) {
            return response()->json(['success' => false, 'message' => 'Only client-matter-generated categories can be updated.']);
        }

        if ($category->client_id !== null && $category->client_id !== '' && is_numeric($category->client_id)) {
            if ($deny = $this->denyJsonUnlessStaffClientAccess((int) $category->client_id)) {
                return $deny;
            }
        }

        $categoryTitle = trim($request->input('title'));

        // RULE 1: If status=1 and client_id is NULL, title must be unique globally (only one)
        $existsForNullClient = VisaDocumentType::query()->where('title', $categoryTitle)
            ->where('status', 1)
            ->whereNull('client_matter_id')
            ->exists();

        if ($existsForNullClient) {
            return response()->json([
                'status' => false,
                'message' => 'This category already exists globally for all client matters.Pls try other.'
            ]);
        }

        // RULE 2: Same title with status=1 for same client_id is not allowed (excluding the current category)
        $existsForSameClient = VisaDocumentType::query()->where('title', $categoryTitle)
            ->where('status', 1)
            ->where('client_matter_id', $clientMatterId)
            ->where('id', '!=', $request->id)
            ->exists();

        if ($existsForSameClient) {
            return response()->json([
                'status' => false,
                'message' => 'This category already exists for this client matter.'
            ]);
        }

        try {
            $category->title = $categoryTitle;
            $category->save();

            return response()->json([
                'status' => true,
                'message' => 'Visa Document Category updated successfully.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error updating category: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Delete Visa Document Category (allowed roles: config crm.visa_document_category_delete_role_ids;
     * empty categories only; matter-specific rows only — default/global rows have client_matter_id NULL)
     */
    public function deleteVisaDocCategory(Request $request) {
        try {
            $allowedRoles = config('crm.visa_document_category_delete_role_ids', [1, 16]);
            if (! in_array((int) (Auth::user()->role ?? 0), $allowedRoles, true)) {
                return response()->json([
                    'status' => false,
                    'message' => 'You are not allowed to delete visa document categories.',
                ]);
            }

            $request->validate([
                'id' => 'required|exists:visa_document_types,id',
            ]);

            $category = VisaDocumentType::findOrFail($request->id);

            if ($category->client_matter_id === null) {
                return response()->json([
                    'status' => false,
                    'message' => 'Default categories cannot be deleted.',
                ]);
            }

            if ($category->client_id !== null && $category->client_id !== '' && is_numeric($category->client_id)) {
                if ($deny = $this->denyJsonUnlessStaffClientAccess((int) $category->client_id)) {
                    return $deny;
                }
            }

            $folderName = (string) $category->id;
            $clientId = (int) $category->client_id;
            $clientMatterId = $category->client_matter_id !== null ? (int) $category->client_matter_id : null;

            // Match Visa Documents tab: only active (non–not-used) client rows block deletion
            $activeDocumentCount = $this->visaCategoryDocumentQuery($folderName, $clientId, $clientMatterId)
                ->whereNull('not_used_doc')
                ->count();

            if ($activeDocumentCount > 0) {
                return response()->json([
                    'status' => false,
                    'message' => 'Cannot delete category. It contains ' . $activeDocumentCount . ' document(s). Please remove all documents first.',
                ]);
            }

            $notUsedPayload = $this->collectNotUsedVisaDocumentsForCategory($folderName, $clientId, $clientMatterId);
            $notUsedDocuments = $notUsedPayload['s3_cleanup'];
            $notUsedRemovedCount = $notUsedPayload['removed_count'];

            $categoryTitle = $category->title;

            DB::transaction(function () use ($notUsedDocuments, $category, $folderName, $clientId, $clientMatterId) {
                if ($notUsedDocuments !== []) {
                    $deleteQuery = $this->visaCategoryDocumentQuery($folderName, $clientId, $clientMatterId)
                        ->where('not_used_doc', 1);
                    $deleteQuery->delete();
                }

                $category->delete();
            });

            $this->finalizeDeletedVisaDocuments($clientId, $notUsedDocuments);

            $message = 'Category "' . $categoryTitle . '" deleted successfully.';
            if ($notUsedRemovedCount > 0) {
                $message .= ' ' . $notUsedRemovedCount . ' not-used document(s) were also removed.';
            }

            return response()->json([
                'status' => true,
                'message' => $message,
            ]);
        } catch (\Exception $e) {
            Log::error('Error deleting visa document category', [
                'category_id' => $request->id ?? null,
                'user_id' => Auth::user()->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Error deleting category: ' . $e->getMessage(),
            ]);
        }
    }

    public function addNominationDocCategory(Request $request)
    {
        $categoryTitle = trim($request->input('nomination_doc_category'));
        $clientId = $request->input('clientid');
        $clientMatterId = $request->input('clientmatterid');

        $request->merge(['nomination_doc_category' => $categoryTitle]);

        $validator = Validator::make($request->all(), [
            'nomination_doc_category' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first('nomination_doc_category'),
            ]);
        }

        if ($clientId !== null && $clientId !== '' && is_numeric($clientId)) {
            if ($deny = $this->denyJsonUnlessStaffClientAccess((int) $clientId)) {
                return $deny;
            }
        }

        $existsForNullClient = NominationDocumentType::query()->where('title', $categoryTitle)
            ->where('status', 1)
            ->whereNull('client_matter_id')
            ->whereNull('client_id')
            ->exists();

        if ($existsForNullClient) {
            return response()->json([
                'status' => false,
                'message' => 'This category already exists globally.',
            ]);
        }

        $existsForSameClient = NominationDocumentType::query()->where('title', $categoryTitle)
            ->where('status', 1)
            ->where('client_matter_id', $clientMatterId)
            ->exists();

        if ($existsForSameClient) {
            return response()->json([
                'status' => false,
                'message' => 'This category already exists for this client matter.',
            ]);
        }

        try {
            $category = new NominationDocumentType();
            $category->title = $categoryTitle;
            $category->status = 1;
            $category->client_id = $clientId ?? null;
            $category->client_matter_id = $clientMatterId ?? null;
            $category->save();

            return response()->json([
                'status' => true,
                'message' => 'Nomination document category added successfully.',
                'id' => $category->id,
                'title' => $category->title,
                'client_matter_id' => $category->client_matter_id,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error adding category: '.$e->getMessage(),
            ]);
        }
    }

    public function updateNominationDocCategory(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:nomination_document_types,id',
            'title' => 'required|string|max:255',
        ]);

        $category = NominationDocumentType::findOrFail($request->id);
        $clientMatterId = $category->client_matter_id;

        if ($category->client_matter_id === null) {
            return response()->json(['success' => false, 'message' => 'Only client-matter-generated categories can be updated.']);
        }

        if ($category->client_id !== null && $category->client_id !== '' && is_numeric($category->client_id)) {
            if ($deny = $this->denyJsonUnlessStaffClientAccess((int) $category->client_id)) {
                return $deny;
            }
        }

        $categoryTitle = trim($request->input('title'));

        $existsForNullClient = NominationDocumentType::query()->where('title', $categoryTitle)
            ->where('status', 1)
            ->whereNull('client_matter_id')
            ->whereNull('client_id')
            ->exists();

        if ($existsForNullClient) {
            return response()->json([
                'status' => false,
                'message' => 'This category already exists globally for all client matters.Pls try other.',
            ]);
        }

        $existsForSameClient = NominationDocumentType::query()->where('title', $categoryTitle)
            ->where('status', 1)
            ->where('client_matter_id', $clientMatterId)
            ->where('id', '!=', $request->id)
            ->exists();

        if ($existsForSameClient) {
            return response()->json([
                'status' => false,
                'message' => 'This category already exists for this client matter.',
            ]);
        }

        try {
            $category->title = $categoryTitle;
            $category->save();

            return response()->json([
                'status' => true,
                'message' => 'Nomination document category updated successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error updating category: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Delete Personal Document Category (allowed roles: config crm.personal_document_category_delete_role_ids;
     * empty categories only; client-specific categories only — default/global rows have client_id NULL and cannot be deleted)
     */
    public function deletePersonalDocCategory(Request $request) {
        try {
            $allowedRoles = config('crm.personal_document_category_delete_role_ids', [1, 16]);
            if (! in_array((int) (Auth::user()->role ?? 0), $allowedRoles, true)) {
                return response()->json([
                    'status' => false,
                    'message' => 'You are not allowed to delete document categories.',
                ]);
            }

            $request->validate([
                'id' => 'required|exists:personal_document_types,id',
            ]);

            $category = PersonalDocumentType::findOrFail($request->id);

            // System/default categories (global, not tied to a client) cannot be deleted
            if ($category->client_id === null) {
                return response()->json([
                    'status' => false,
                    'message' => 'Default categories cannot be deleted.',
                ]);
            }

            if ($category->client_id !== null && $category->client_id !== '' && is_numeric($category->client_id)) {
                if ($deny = $this->denyJsonUnlessStaffClientAccess((int) $category->client_id)) {
                    return $deny;
                }
            }

            $folderName = (string) $category->id;
            $clientId = (int) $category->client_id;

            // Match Personal Documents tab: only active (non–not-used) client rows block deletion
            $activeDocumentCount = Document::query()
                ->where('folder_name', $folderName)
                ->where('doc_type', 'personal')
                ->where('client_id', $clientId)
                ->whereNull('not_used_doc')
                ->where('type', 'client')
                ->count();

            if ($activeDocumentCount > 0) {
                return response()->json([
                    'status' => false,
                    'message' => 'Cannot delete category. It contains ' . $activeDocumentCount . ' document(s). Please remove all documents first.',
                ]);
            }

            $notUsedPayload = $this->collectNotUsedPersonalDocumentsForCategory($folderName, $clientId);
            $notUsedDocuments = $notUsedPayload['s3_cleanup'];
            $notUsedRemovedCount = $notUsedPayload['removed_count'];

            $categoryTitle = $category->title;

            DB::transaction(function () use ($notUsedDocuments, $category, $folderName, $clientId) {
                if ($notUsedDocuments !== []) {
                    Document::query()
                        ->where('folder_name', $folderName)
                        ->where('doc_type', 'personal')
                        ->where('client_id', $clientId)
                        ->where('not_used_doc', 1)
                        ->where('type', 'client')
                        ->delete();
                }

                $category->delete();
            });

            $this->finalizeDeletedPersonalDocuments($clientId, $notUsedDocuments);

            $message = 'Category "' . $categoryTitle . '" deleted successfully.';
            if ($notUsedRemovedCount > 0) {
                $message .= ' ' . $notUsedRemovedCount . ' not-used document(s) were also removed.';
            }

            return response()->json([
                'status' => true,
                'message' => $message,
            ]);

        } catch (\Exception $e) {
            Log::error('Error deleting personal document category', [
                'category_id' => $request->id ?? null,
                'user_id' => Auth::user()->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Error deleting category: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Get auto-checklist matches for bulk upload
     */
    public function getAutoChecklistMatches(Request $request) {
        $response = ['status' => false, 'matches' => []];
        
        try {
            $files = $request->input('files', []);
            $checklists = $request->input('checklists', []);
            
            if (empty($files) || empty($checklists)) {
                $response['status'] = true;
                return response()->json($response);
            }
            
            $matches = [];
            
            foreach ($files as $file) {
                $fileName = (string) ($file['name'] ?? '');
                $match = $this->findBestChecklistMatch($fileName, $checklists);
                if ($match) {
                    $matches[$fileName] = $match;
                }
            }
            
            $response['status'] = true;
            $response['matches'] = $matches;
            
        } catch (\Exception $e) {
            Log::error('Error getting auto-checklist matches', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
        
        return response()->json($response);
    }
    
    /**
     * Find best checklist match for a filename.
     *
     * @param  list<string>|array<int|string, string>  $checklists
     * @return array{
     *     checklist: string,
     *     confidence: string,
     *     score: float|int,
     *     method: string
     * }|null
     */
    private function findBestChecklistMatch(string $fileName, array $checklists): ?array {
        if (empty($fileName) || empty($checklists)) {
            return null;
        }
        
        // Clean filename
        $cleanFileName = $this->cleanFileName($fileName);
        $fileNameLower = strtolower($cleanFileName);
        $fileNameWords = $this->extractKeywords($cleanFileName);
        
        $bestMatch = null;
        $bestScore = 0;
        $bestConfidence = 'low';
        
        foreach ($checklists as $checklist) {
            $checklist = (string) $checklist;
            $checklistLower = strtolower($checklist);
            $checklistWords = $this->extractKeywords($checklist);
            
            // Strategy 1: Exact match (after cleaning)
            if ($fileNameLower === $checklistLower) {
                return [
                    'checklist' => $checklist,
                    'confidence' => 'high',
                    'score' => 100,
                    'method' => 'exact'
                ];
            }
            
            // Strategy 2: Fuzzy matching
            $similarity = $this->calculateSimilarity($fileNameLower, $checklistLower);
            if ($similarity > 85) {
                return [
                    'checklist' => $checklist,
                    'confidence' => 'high',
                    'score' => $similarity,
                    'method' => 'fuzzy'
                ];
            } elseif ($similarity > 70 && $similarity > $bestScore) {
                $bestMatch = $checklist;
                $bestScore = $similarity;
                $bestConfidence = 'medium';
            }
            
            // Strategy 3: Pattern matching
            $patternMatch = $this->checkPatternMatch($fileNameWords, $checklistWords);
            if ($patternMatch['matched'] && $patternMatch['score'] > $bestScore) {
                $bestMatch = $checklist;
                $bestScore = $patternMatch['score'];
                $bestConfidence = $patternMatch['score'] > 80 ? 'high' : 'medium';
            }
            
            // Strategy 4: Abbreviation matching
            $abbrevMatch = $this->checkAbbreviationMatch($cleanFileName, $checklist);
            if ($abbrevMatch && $abbrevMatch > $bestScore) {
                $bestMatch = $checklist;
                $bestScore = $abbrevMatch;
                $bestConfidence = 'high';
            }
            
            // Strategy 5: Partial word matching
            $partialMatch = $this->checkPartialMatch($fileNameWords, $checklistWords);
            if ($partialMatch && $partialMatch > $bestScore) {
                $bestMatch = $checklist;
                $bestScore = $partialMatch;
                $bestConfidence = 'low';
            }
        }
        
        if ($bestMatch && $bestScore > 50) {
            return [
                'checklist' => $bestMatch,
                'confidence' => $bestConfidence,
                'score' => $bestScore,
                'method' => 'combined'
            ];
        }
        
        return null;
    }
    
    /**
     * Clean filename for matching
     */
    private function cleanFileName(string $fileName): string {
        // Remove extension
        $name = pathinfo($fileName, PATHINFO_FILENAME);
        // Remove common prefixes (client name, timestamps)
        $name = preg_replace('/^[^_]+_/', '', $name); // Remove prefix before first underscore
        $name = preg_replace('/_\d{10,}$/', '', $name); // Remove timestamps
        $name = preg_replace('/[^a-zA-Z0-9\s]/', ' ', $name); // Replace special chars with spaces
        return trim($name);
    }
    
    /**
     * Extract keywords from text
     *
     * @return array<int|string, string>
     */
    private function extractKeywords(string $text): array {
        $text = strtolower($text);
        $words = preg_split('/[\s_\-]+/', $text);
        $stopWords = ['the', 'of', 'and', 'a', 'an', 'in', 'on', 'at', 'to', 'for', 'is', 'are', 'was', 'were'];
        return array_filter($words, function($word) use ($stopWords) {
            return strlen($word) > 2 && !in_array($word, $stopWords);
        });
    }
    
    /**
     * Calculate similarity between two strings (Levenshtein-based)
     */
    private function calculateSimilarity(string $str1, string $str2): float {
        $len1 = strlen($str1);
        $len2 = strlen($str2);
        
        if ($len1 === 0 || $len2 === 0) {
            return 0;
        }
        
        $maxLen = max($len1, $len2);
        $distance = levenshtein($str1, $str2);
        
        return (1 - ($distance / $maxLen)) * 100;
    }
    
    /**
     * Check pattern match
     *
     * @param array<int|string, string> $fileNameWords
     * @param array<int|string, string> $checklistWords
     * @return array{matched: bool, score: int}
     */
    private function checkPatternMatch(array $fileNameWords, array $checklistWords): array {
        $patterns = [
            'passport' => ['passport', 'pass', 'pp'],
            'visa' => ['visa', 'grant', 'vg'],
            'identity' => ['id', 'identity', 'aadhar', 'aadhaar', 'national'],
            'birth' => ['birth', 'certificate', 'bc'],
            'marriage' => ['marriage', 'certificate', 'mc'],
            'education' => ['education', 'degree', 'diploma', 'certificate'],
            'employment' => ['employment', 'experience', 'work', 'job']
        ];
        
        $matched = false;
        $score = 0;
        
        foreach ($patterns as $key => $keywords) {
            $fileHasKeyword = false;
            $checklistHasKeyword = false;
            
            foreach ($keywords as $keyword) {
                if (in_array($keyword, $fileNameWords)) {
                    $fileHasKeyword = true;
                }
                if (in_array($keyword, $checklistWords)) {
                    $checklistHasKeyword = true;
                }
            }
            
            if ($fileHasKeyword && $checklistHasKeyword) {
                $matched = true;
                $score = 90; // High score for pattern match
                break;
            }
        }
        
        return ['matched' => $matched, 'score' => $score];
    }
    
    /**
     * Check abbreviation match
     */
    private function checkAbbreviationMatch(string $fileName, string $checklist): int {
        $abbreviations = [
            'pp' => 'passport',
            'vg' => 'visa grant',
            'nic' => 'national identity',
            'dob' => 'birth',
            'bc' => 'birth certificate',
            'mc' => 'marriage certificate'
        ];
        
        $fileNameLower = strtolower($fileName);
        $checklistLower = strtolower($checklist);
        
        foreach ($abbreviations as $abbrev => $full) {
            if (strpos($fileNameLower, $abbrev) !== false && strpos($checklistLower, $full) !== false) {
                return 85;
            }
        }
        
        return 0;
    }
    
    /**
     * Check partial word match
     *
     * @param array<int|string, string> $fileNameWords
     * @param array<int|string, string> $checklistWords
     */
    private function checkPartialMatch(array $fileNameWords, array $checklistWords): float {
        $matches = 0;
        $total = count($checklistWords);
        
        if ($total === 0) {
            return 0;
        }
        
        foreach ($checklistWords as $checklistWord) {
            foreach ($fileNameWords as $fileNameWord) {
                if (strpos($fileNameWord, $checklistWord) !== false || strpos($checklistWord, $fileNameWord) !== false) {
                    $matches++;
                    break;
                }
            }
        }
        
        return ($matches / $total) * 100;
    }
    
    /**
     * Bulk upload personal documents
     */
    public function bulkUploadPersonalDocuments(Request $request) {
        $response = ['status' => false, 'message' => 'Please try again'];
        
        try {
            $clientid = $request->clientid;
            $categoryid = $request->categoryid;
            $doctype = $request->doctype ?? 'personal';
            $type = $request->type ?? 'client';

            if ($deny = $this->denyJsonUnlessStaffClientAccess((int) $clientid)) {
                return $deny;
            }
            
            $admin_info1 = Admin::select(['id', 'client_id', 'first_name', 'is_company'])->where('id', $clientid)->first();
            $client_unique_id = !empty($admin_info1) ? $admin_info1->client_id : "";
            $client_first_name = !empty($admin_info1)
                ? preg_replace('/[^a-zA-Z0-9_\-]/', '_', (string) ($admin_info1->first_name ?? ''))
                : 'client';
            $namePrefix = DocumentStoredFilename::storedNamePrefix($admin_info1, $client_first_name);
            
            if (!$request->hasFile('files')) {
                $response['message'] = 'No files uploaded';
                return response()->json($response);
            }
            
            $files = $request->file('files');
            $mappingsInput = $request->input('mappings', []);
            
            if (!is_array($files)) {
                $files = [$files];
            }
            
            // Parse mappings JSON strings
            $mappings = [];
            foreach ($mappingsInput as $mappingStr) {
                $mapping = json_decode($mappingStr, true);
                if ($mapping) {
                    $mappings[] = $mapping;
                }
            }
            
            $uploadedCount = 0;
            $errors = [];
            
            foreach ($files as $index => $file) {
                $fileName = $file->getClientOriginalName();
                try {
                    $size = $file->getSize();

                    if ($size > self::BULK_UPLOAD_MAX_BYTES) {
                        $errors[] = "File '{$fileName}' exceeds 20MB limit";
                        continue;
                    }
                    
                    // Validate filename
                    if (!DocumentFilenameRules::isAllowed($fileName)) {
                        $errors[] = "File '{$fileName}' has invalid characters in name";
                        continue;
                    }
                    
                    // Get mapping for this file
                    $mapping = isset($mappings[$index]) ? $mappings[$index] : null;
                    if (!$mapping || !isset($mapping['name'])) {
                        $errors[] = "No mapping found for file '{$fileName}'";
                        continue;
                    }
                    
                    $checklistName = $mapping['name'] ?? null;
                    if (!$checklistName) {
                        $errors[] = "No checklist name specified for file '{$fileName}'";
                        continue;
                    }
                    
                    // Check if checklist exists, create if needed
                    $document = Document::query()->where('client_id', $clientid)
                        ->where('doc_type', $doctype)
                        ->where('folder_name', $categoryid)
                        ->where('checklist', $checklistName)
                        ->where('type', $type)
                        ->whereNull('not_used_doc')
                        ->whereNull('file_name') // Only get checklists without files
                        ->first();
                    
                    // If checklist doesn't exist and mapping type is 'new', create it
                    if (!$document && $mapping['type'] === 'new') {
                        $document = new Document();
                        $document->user_id = Auth::user()->id;
                        $document->client_id = $clientid;
                        $document->type = $type;
                        $document->doc_type = $doctype;
                        $document->folder_name = $categoryid;
                        $document->checklist = $checklistName;
                        $document->save();
                    } elseif (!$document && $mapping['type'] === 'existing') {
                        // If trying to use existing checklist but all instances have files, create new one
                        $hasAnyChecklist = Document::query()->where('client_id', $clientid)
                            ->where('doc_type', $doctype)
                            ->where('folder_name', $categoryid)
                            ->where('checklist', $checklistName)
                            ->where('type', $type)
                            ->whereNull('not_used_doc')
                            ->exists();
                        
                        if ($hasAnyChecklist) {
                            // Checklist exists but all have files - create a new instance
                            $document = new Document();
                            $document->user_id = Auth::user()->id;
                            $document->client_id = $clientid;
                            $document->type = $type;
                            $document->doc_type = $doctype;
                            $document->folder_name = $categoryid;
                            $document->checklist = $checklistName;
                            $document->save();
                        }
                    }
                    
                    if (!$document) {
                        $errors[] = "Checklist '{$checklistName}' not found for file '{$fileName}'";
                        continue;
                    }
                    
                    // Upload file
                    $extension = $file->getClientOriginalExtension();
                    $timestamp = time();
                    $uniqueId = $timestamp . '_' . $index . '_' . mt_rand(1000, 9999);
                    $name = $namePrefix . "_" . $checklistName . "_" . $uniqueId . "." . $extension;
                    $filePath = $client_unique_id . '/' . $doctype . '/' . $name;
                    
                    $this->s3Disk()->put($filePath, file_get_contents($file));
                    
                    // Update document
                    $fileUrl = $this->s3Disk()->url($filePath);
                    $document->file_name = $namePrefix . "_" . $checklistName . "_" . $uniqueId;
                    $document->filetype = $extension;
                    $document->user_id = Auth::user()->id;
                    $document->myfile = $fileUrl;
                    $document->myfile_key = $name;
                    $document->file_size = $size;
                    $document->save();
                    
                    $uploadedCount++;
                    
                } catch (\Exception $e) {
                    $errors[] = "Error uploading '{$fileName}': " . $e->getMessage();
                    Log::error('Bulk upload error for file', [
                        'file' => $fileName,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            
            if ($uploadedCount > 0) {
                // Log activity
                $matterRef = $this->getMatterReference($clientid);
                $subject = !empty($matterRef) 
                    ? "bulk uploaded {$uploadedCount} documents - {$matterRef}"
                    : "bulk uploaded {$uploadedCount} documents";
                $description = "<p>Bulk uploaded {$uploadedCount} personal documents</p>";
                
                $this->logClientActivity(
                    $clientid,
                    $subject,
                    $description,
                    'document'
                );
                
                $response['status'] = true;
                $response['message'] = "Successfully uploaded {$uploadedCount} file(s)";
                $response['uploaded'] = $uploadedCount;
                $response['errors'] = $errors;
            } else {
                $response['message'] = 'No files were uploaded. ' . implode('; ', $errors);
                $response['errors'] = $errors;
            }
            
        } catch (\Exception $e) {
            Log::error('Error in bulk upload', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $response['message'] = 'An error occurred: ' . $e->getMessage();
        }
        
        return response()->json($response);
    }
    
    /**
     * Bulk upload visa documents
     */
    public function bulkUploadVisaDocuments(Request $request) {
        $response = ['status' => false, 'message' => 'Please try again'];
        
        try {
            $clientid = $request->clientid;
            $categoryid = $request->categoryid;
            $matterid = $request->matterid ?? null;
            $doctype = $request->doctype ?? 'visa';
            $type = $request->type ?? 'client';

            if ($deny = $this->denyJsonUnlessStaffClientAccess((int) $clientid)) {
                return $deny;
            }
            
            $admin_info1 = Admin::select(['id', 'client_id', 'first_name', 'is_company'])->where('id', $clientid)->first();
            $client_unique_id = !empty($admin_info1) ? $admin_info1->client_id : "";
            $client_first_name = !empty($admin_info1)
                ? preg_replace('/[^a-zA-Z0-9_\-]/', '_', (string) ($admin_info1->first_name ?? ''))
                : 'client';
            $namePrefix = DocumentStoredFilename::storedNamePrefix($admin_info1, $client_first_name);
            
            if (!$request->hasFile('files')) {
                $response['message'] = 'No files uploaded';
                return response()->json($response);
            }
            
            $files = $request->file('files');
            $mappingsInput = $request->input('mappings', []);
            
            if (!is_array($files)) {
                $files = [$files];
            }
            
            // Parse mappings JSON strings
            $mappings = [];
            foreach ($mappingsInput as $mappingStr) {
                $mapping = json_decode($mappingStr, true);
                if ($mapping) {
                    $mappings[] = $mapping;
                }
            }
            
            $uploadedCount = 0;
            $errors = [];
            
            foreach ($files as $index => $file) {
                $fileName = $file->getClientOriginalName();
                try {
                    $size = $file->getSize();

                    if ($size > self::BULK_UPLOAD_MAX_BYTES) {
                        $errors[] = "File '{$fileName}' exceeds 20MB limit";
                        continue;
                    }
                    
                    // Validate filename
                    if (!DocumentFilenameRules::isAllowed($fileName)) {
                        $errors[] = "File '{$fileName}' has invalid characters in name";
                        continue;
                    }
                    
                    // Get mapping for this file
                    $mapping = isset($mappings[$index]) ? $mappings[$index] : null;
                    if (!$mapping || !isset($mapping['name'])) {
                        $errors[] = "No mapping found for file '{$fileName}'";
                        continue;
                    }
                    
                    $checklistName = $mapping['name'] ?? null;
                    if (!$checklistName) {
                        $errors[] = "No checklist name specified for file '{$fileName}'";
                        continue;
                    }
                    
                    // Check if checklist exists, create if needed
                    $document = Document::query()->where('client_id', $clientid)
                        ->where('doc_type', $doctype)
                        ->where('folder_name', $categoryid)
                        ->where('checklist', $checklistName)
                        ->where('type', $type)
                        ->whereNull('not_used_doc')
                        ->whereNull('file_name') // Only get checklists without files
                        ->when($matterid, function($query) use ($matterid) {
                            return $query->where('client_matter_id', $matterid);
                        })
                        ->first();
                    
                    // If checklist doesn't exist and mapping type is 'new', create it
                    if (!$document && $mapping['type'] === 'new') {
                        $document = new Document();
                        $document->user_id = Auth::user()->id;
                        $document->client_id = $clientid;
                        $document->type = $type;
                        $document->doc_type = $doctype;
                        $document->folder_name = $categoryid;
                        $document->checklist = $checklistName;
                        $document->client_matter_id = $matterid;
                        $document->save();
                    } elseif (!$document && $mapping['type'] === 'existing') {
                        // If trying to use existing checklist but all instances have files, create new one
                        $hasAnyChecklist = Document::query()->where('client_id', $clientid)
                            ->where('doc_type', $doctype)
                            ->where('folder_name', $categoryid)
                            ->where('checklist', $checklistName)
                            ->where('type', $type)
                            ->whereNull('not_used_doc')
                            ->when($matterid, function($query) use ($matterid) {
                                return $query->where('client_matter_id', $matterid);
                            })
                            ->exists();
                        
                        if ($hasAnyChecklist) {
                            // Checklist exists but all have files - create a new instance
                            $document = new Document();
                            $document->user_id = Auth::user()->id;
                            $document->client_id = $clientid;
                            $document->type = $type;
                            $document->doc_type = $doctype;
                            $document->folder_name = $categoryid;
                            $document->checklist = $checklistName;
                            $document->client_matter_id = $matterid;
                            $document->save();
                        }
                    }
                    
                    if (!$document) {
                        $errors[] = "Checklist '{$checklistName}' not found for file '{$fileName}'";
                        continue;
                    }
                    
                    // Refresh document to get latest checklist name (prevent race conditions)
                    $document->refresh();
                    $finalChecklistName = $document->checklist;
                    
                    // Validate checklist name exists
                    if (empty($finalChecklistName)) {
                        $errors[] = "Checklist name not found for file '{$fileName}'";
                        Log::warning('Bulk visa upload: Checklist name missing', [
                            'document_id' => $document->id,
                            'file' => $fileName,
                            'clientid' => $clientid
                        ]);
                        continue;
                    }
                    
                    // Use document's current checklist name (not mapping name) to ensure consistency
                    $checklistName = $finalChecklistName;
                    
                    // Upload file
                    $extension = $file->getClientOriginalExtension();
                    $timestamp = time();
                    $uniqueId = $timestamp . '_' . $index . '_' . mt_rand(1000, 9999);
                    $name = $namePrefix . "_" . $checklistName . "_" . $uniqueId . "." . $extension;
                    $filePath = $client_unique_id . '/' . $doctype . '/' . $name;

                    $fileContent = $this->flattenPdfIfForm956($file, $document);
                    $this->s3Disk()->put($filePath, $fileContent);
                    $size = strlen($fileContent);
                    
                    // Refresh one more time before saving to catch any changes during S3 upload
                    $document->refresh();
                    $finalChecklistName = $document->checklist;
                    
                    // If checklist changed during upload, rebuild filename and move S3 file
                    if (!empty($finalChecklistName) && $finalChecklistName !== $checklistName) {
                        $checklistName = $finalChecklistName;
                        $name = $namePrefix . "_" . $checklistName . "_" . $uniqueId . "." . $extension;
                        $newFilePath = $client_unique_id . '/' . $doctype . '/' . $name;
                        if ($newFilePath !== $filePath) {
                            try {
                                $this->s3Disk()->copy($filePath, $newFilePath);
                                $this->s3Disk()->delete($filePath);
                                $filePath = $newFilePath;
                                Log::info('Bulk visa upload: File moved due to checklist change', [
                                    'old_path' => $filePath,
                                    'new_path' => $newFilePath,
                                    'file' => $fileName
                                ]);
                            } catch (\Exception $e) {
                                Log::error('Bulk visa upload: Failed to move S3 file', [
                                    'old_path' => $filePath,
                                    'new_path' => $newFilePath,
                                    'error' => $e->getMessage()
                                ]);
                            }
                        }
                    }
                    
                    // Update document
                    $fileUrl = $this->s3Disk()->url($filePath);
                    $document->file_name = $namePrefix . "_" . $checklistName . "_" . $uniqueId;
                    $document->filetype = $extension;
                    $document->user_id = Auth::user()->id;
                    $document->myfile = $fileUrl;
                    $document->myfile_key = $name;
                    $document->file_size = $size;
                    $document->save();
                    
                    $uploadedCount++;
                    
                } catch (\Exception $e) {
                    $errors[] = "Error uploading '{$fileName}': " . $e->getMessage();
                    Log::error('Bulk visa upload error for file', [
                        'file' => $fileName,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            
            if ($uploadedCount > 0) {
                // Log activity
                $matterRef = $this->getMatterReference($clientid, $matterid);
                $docLabel = $doctype === 'nomination' ? 'nomination' : 'visa';
                $subject = !empty($matterRef) 
                    ? "bulk uploaded {$uploadedCount} {$docLabel} documents - {$matterRef}"
                    : "bulk uploaded {$uploadedCount} {$docLabel} documents";
                $description = "<p>Bulk uploaded {$uploadedCount} {$docLabel} documents</p>";
                
                $this->logClientActivity(
                    $clientid,
                    $subject,
                    $description,
                    'document'
                );
                
                // Update matter date
                if ($matterid) {
                    $matter = ClientMatter::query()->find($matterid);
                    if ($matter) {
                        $matter->updated_at = now();
                        $matter->save();
                    }
                }
                
                $response['status'] = true;
                $response['message'] = "Successfully uploaded {$uploadedCount} file(s)";
                $response['uploaded'] = $uploadedCount;
                $response['errors'] = $errors;
            } else {
                $response['message'] = 'No files were uploaded. ' . implode('; ', $errors);
                $response['errors'] = $errors;
            }
            
        } catch (\Exception $e) {
            Log::error('Error in visa bulk upload', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $response['message'] = 'An error occurred: ' . $e->getMessage();
        }
        
        return response()->json($response);
    }
    
    /**
     * Get matter reference for activity logging
     *
     * @param int|string|null $clientId
     * @param int|string|null $matterId
     */
    private function getMatterReference(int|string|null $clientId, int|string|null $matterId = null): string
    {
        $matterReference = '';
        
        // First try to get from provided matter ID
        if($matterId) {
            $matter = ClientMatter::query()->find($matterId);
            if($matter && $matter->client_unique_matter_no) {
                return $matter->client_unique_matter_no;
            }
        }
        
        // Fall back to latest active matter
        $latestMatter = ClientMatter::query()->where('client_id', $clientId)
            ->where('matter_status', 1)
            ->orderBy('id', 'desc')
            ->first();
            
        if($latestMatter && $latestMatter->client_unique_matter_no) {
            return $latestMatter->client_unique_matter_no;
        }
        
        return '';
    }

    /**
     * Flattens a PDF if the document is linked to a Form 956.
     * Flattening removes all interactive AcroForm fields, making the PDF
     * non-editable when previewed or downloaded after upload.
     * Falls back to the original file content if flattening fails.
     */
    private function flattenPdfIfForm956(\Illuminate\Http\UploadedFile $file, Document $doc): string
    {
        $content = file_get_contents($file->getRealPath());

        if (strtolower($file->getClientOriginalExtension()) !== 'pdf' || empty($doc->form956_id)) {
            return $content;
        }

        $tempInput  = null;
        $tempOutput = null;

        try {
            $tempInput  = tempnam(sys_get_temp_dir(), 'pdf956in_');
            $tempOutput = tempnam(sys_get_temp_dir(), 'pdf956out_');

            file_put_contents($tempInput, $content);

            $pdf    = new Pdf($tempInput);
            $result = $pdf->flatten()->saveAs($tempOutput);

            if ($result !== false && file_exists($tempOutput) && filesize($tempOutput) > 0) {
                $flatContent = file_get_contents($tempOutput);
            } else {
                Log::warning('Form 956 PDF flattening failed, uploading original', [
                    'doc_id'      => $doc->id,
                    'form956_id'  => $doc->form956_id,
                    'pdftk_error' => $pdf->getError(),
                ]);
                $flatContent = $content;
            }
        } catch (\Exception $e) {
            Log::error('Exception during Form 956 PDF flattening, uploading original', [
                'doc_id'     => $doc->id,
                'form956_id' => $doc->form956_id,
                'error'      => $e->getMessage(),
            ]);
            $flatContent = $content;
        } finally {
            if ($tempInput  && file_exists($tempInput))  @unlink($tempInput);
            if ($tempOutput && file_exists($tempOutput)) @unlink($tempOutput);
        }

        return $flatContent;
    }
}
