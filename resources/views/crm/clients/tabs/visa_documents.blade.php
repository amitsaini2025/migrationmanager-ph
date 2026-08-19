           <!-- Visa Documents Tab (Matter-Specific) -->
           <div class="tab-pane" id="visadocuments-tab"
                @if(!empty($encodeId))
                    data-visadocuments-url="{{ route('clients.detail.visa-documents-tab', array_filter([
                        'client_id' => $encodeId,
                        'client_unique_matter_ref_no' => $id1 ?? null,
                    ], static fn ($v) => $v !== null && $v !== '')) }}"
                @endif>
                <div class="card full-width documentalls-container">
                    <?php
                    $client_selected_matter_id1 = null;
                    $matter_cnt = \App\Models\ClientMatter::select('id')->where('client_id',$fetchedData->id)->where('matter_status',1)->count();
                    if( $matter_cnt >0 ) {
                        //if client unique reference id is present in url
                        if( isset($id1) && $id1 != "") {
                            // Only resolve by ref if it matches an active matter; a discontinued
                            // matter ref here would make all active-matter documents invisible.
                            $matter_get_id = \App\Models\ClientMatter::select('id')
                                ->where('client_id',$fetchedData->id)
                                ->where('client_unique_matter_no',$id1)
                                ->where('matter_status', 1)
                                ->first();
                            // Fall back to latest active matter if the ref belongs to a discontinued one
                            if (!$matter_get_id) {
                                $matter_get_id = \App\Models\ClientMatter::select('id')
                                    ->where('client_id', $fetchedData->id)
                                    ->where('matter_status', 1)
                                    ->orderBy('id', 'desc')
                                    ->first();
                            }
                        } else {
                            $matter_get_id = \App\Models\ClientMatter::select('id')
                                ->where('client_id', $fetchedData->id)
                                ->where('matter_status', 1)
                                ->orderBy('id', 'desc')
                                ->first();
                        }
                        if($matter_get_id ){
                            $client_selected_matter_id1 = $matter_get_id->id;
                        }
                    }

                    /*$visaDocCatList = \App\Models\VisaDocumentType::select('id', 'title','client_id','client_matter_id')
                    ->where('status', 1)
                    ->where(function($query) use ($client_selected_matter_id1) {
                        $query->whereNull('client_matter_id')
                            ->orWhere('client_matter_id', (int) $client_selected_matter_id1);
                    })
                    ->orderBy('id', 'ASC')
                    ->get();*/


                    $SelectedClientId = $fetchedData->id;
                    $visaDocCatList = \App\Models\VisaDocumentType::select('id', 'title', 'client_id', 'client_matter_id')
                        ->where('status', 1)
                        ->where(function($query) use ($SelectedClientId,$client_selected_matter_id1) {
                            $query->where(function($q) {
                                    // 1️⃣ Both client_id and client_matter_id are NULL
                                    $q->whereNull('client_id')
                                    ->whereNull('client_matter_id');
                                })
                                ->orWhere(function($q) use ($SelectedClientId) {
                                    // 2️⃣ client_id matches and client_matter_id is NULL
                                    $q->where('client_id', $SelectedClientId)
                                    ->whereNull('client_matter_id');
                                })
                                ->orWhere(function($q) use ($SelectedClientId, $client_selected_matter_id1) {
                                    // 3️⃣ client_id matches and client_matter_id matches
                                    $q->where('client_id', $SelectedClientId)
                                    ->where('client_matter_id', $client_selected_matter_id1);
                                });
                        })
                        ->orderByRaw("
                            CASE
                                WHEN (client_id IS NULL AND client_matter_id IS NULL) THEN 1
                                WHEN (client_id = ? AND client_matter_id = ?) THEN 2
                                WHEN (client_id = ? AND client_matter_id IS NULL) THEN 3
                                ELSE 4
                            END, id ASC
                        ", [$SelectedClientId, $client_selected_matter_id1, $SelectedClientId])
                        ->get();

                    $canDeleteVisaDocCategory = \Illuminate\Support\Facades\Auth::check()
                        && in_array((int) (\Illuminate\Support\Facades\Auth::user()->role ?? 0), config('crm.visa_document_category_delete_role_ids', [1, 16]), true);

                    if (! isset($visaDocumentsByFolder) || ! $visaDocumentsByFolder instanceof \Illuminate\Support\Collection) {
                        $visaDocumentsByFolder = \App\Support\ClientDetailDocumentsTab::visaDocumentsByFolder((int) $fetchedData->id);
                    }

                    ?>

                    <!-- Visa Documents Content -->
                    <div class="visa-documents-content" id="visa-documents-content">
                        <!-- Visa Document Type Subtabs Container -->
                        <div class="subtab-header-container" style="background-color: #4a90e2; padding: 10px; border-radius: 8px 8px 0 0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                            <nav class="subtabs6" style="display: flex; gap: 5px; flex-wrap: wrap; flex: 1;">
                                <?php foreach ($visaDocCatList as $catVal): ?>
                                    <?php
                                    $id = $catVal->id;
                                    $isActive = $id == 1 ? 'active' : '';
                                    $folderName = $id;
                                    $isClientGenerated = $catVal->client_matter_id !== null;
                                    ?>
                                    <div style="display: inline-block; position: relative;" class="button-container">
                                        <button class="subtab6-button <?= $isActive ?>" data-subtab6="<?= $id ?>">
                                            <?= htmlspecialchars($catVal->title) ?>
                                        </button>
                                        <?php if ($isClientGenerated): ?>
                                            <div class="action-buttons" style="display: none; position: absolute;">
                                                <button type="button" class="btn btn-sm btn-warning update-visa-cat-title" data-id="<?= $id ?>" data-title="<?= htmlspecialchars($catVal->title) ?>">@icon('fa-edit')</button>
                                                <?php if ($canDeleteVisaDocCategory): ?>
                                                    <button type="button" class="btn btn-sm btn-danger delete-visa-cat-title" data-id="<?= $id ?>" data-title="<?= htmlspecialchars($catVal->title) ?>">@icon('fa-trash')</button>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </nav>
                            <div style="display: flex; gap: 10px; align-items: center;">
                                <button type="button" class="btn add-visa-doc-category-btn add-visa-doc-category" data-type="visa" data-categoryid="">
                                    @icon('fa-plus') Add Category
                                </button>
                                <!-- Add link to Not Used Documents -->
                                <button type="button" class="btn btn-secondary client-nav-button client-nav-button--inline" data-tab="notuseddocuments">
                                    @icon('fa-folder-minus') Not Used Documents
                                </button>
                            </div>
                        </div>

                        <!-- Subtab6 Contents -->
                        <div class="subtab6-content">
                            <?php foreach ($visaDocCatList as $catVal):
                                $id = $catVal->id;
                                $isActive = $id == 1 ? 'active' : '';
                                $folderName = $id;
                                ?>
                                <div class="subtab6-pane <?= $isActive ?>" id="<?= $id ?>-subtab6">
                                    <div class="checklist-table-container" style="vertical-align: top; margin-top: 10px; width: 760px; overflow: visible;">
                                        <div class="subtab6-header" style="margin-left: 10px;">
                                            <h3>@icon('fa-file-alt') <?= htmlspecialchars($catVal->title) ?> Documents</h3>
                                            <div style="display: flex; gap: 10px;">
                                                <button type="button" class="btn btn-primary btn-sm form956CreateForm inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition duration-200" data-form956-folder="<?= $id ?>">
                                                    @icon('fa-plus', ['class' => 'mr-2']) Create Form 956
                                                </button>
                                                <button type="button" class="btn add-checklist-btn add_migration_doc" data-type="visa" data-categoryid="<?= $id ?>">
                                                    @icon('fa-plus') Add Checklist
                                                </button>
                                                <button type="button" class="btn btn-info bulk-upload-toggle-btn-visa" data-categoryid="<?= $id ?>" data-categoryname="<?= htmlspecialchars($catVal->title) ?>" data-matterid="<?= $client_selected_matter_id1 ?? '' ?>">
                                                    @icon('fa-upload') Bulk Upload
                                                </button>
                                            </div>
                                        </div>
                                        
                                        <!-- Bulk Upload Dropzone for Visa (Hidden by default) -->
                                        <div class="bulk-upload-dropzone-container-visa" id="bulk-upload-visa-<?= $id ?>" style="display: none; margin: 15px 0; padding: 20px; border: 2px dashed #4a90e2; border-radius: 8px; background-color: #f8f9fa;">
                                            <div class="bulk-upload-dropzone-visa" data-categoryid="<?= $id ?>" data-matterid="<?= $client_selected_matter_id1 ?? '' ?>" style="text-align: center; padding: 30px; cursor: pointer;">
                                                @icon('fa-cloud-upload-alt', ['style' => 'font-size: 48px; color: #2563eb; margin-bottom: 15px;'])
                                                <p style="font-size: 16px; color: #374151; margin-bottom: 10px;">
                                                    <strong>Drag and drop files here</strong> or <strong>click to browse</strong>
                                                </p>
                                                <p style="font-size: 14px; color: #4b5563;">You can select multiple files at once</p>
                                                <input type="file" class="bulk-upload-file-input-visa" data-categoryid="<?= $id ?>" data-matterid="<?= $client_selected_matter_id1 ?? '' ?>" multiple style="display: none;" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                                            </div>
                                            <div class="bulk-upload-file-list-visa" style="display: none; margin-top: 20px;">
                                                <h5 style="margin-bottom: 15px;">Files Selected: <span class="file-count-visa">0</span></h5>
                                                <div class="bulk-upload-files-container-visa"></div>
                                            </div>
                                        </div>
                                        <table class="checklist-table">
                                            <thead>
                                                <tr>
                                                    <th>Checklist</th>
                                                    <th>File Name</th>
                                                    <th></th>
                                                </tr>
                                            </thead>
                                            <tbody class="tdata migdocumnetlist1 migdocumnetlist_<?= $id ?>">
                                                <?php
                                                 $documents = \App\Support\ClientDetailDocumentsTab::documentsForFolder($visaDocumentsByFolder, $folderName);
                                                 $parentDocs = $documents->filter(fn($d) => !str_ends_with($d->checklist ?? '', '_signed'));
                                                 $signedByParent = $documents->filter(fn($d) => str_ends_with($d->checklist ?? '', '_signed'))
                                                    ->groupBy(fn($d) => ($d->folder_name ?? '') . '|' . ($d->client_matter_id ?? '') . '|' . substr($d->checklist ?? '', 0, -7));
                                                 // Keys that still have an active (visible) parent row — signed docs under these are rendered in the main loop
                                                 $parentKeysWithActiveParent = $parentDocs->map(fn($d) => ($d->folder_name ?? '') . '|' . ($d->client_matter_id ?? '') . '|' . ($d->checklist ?? ''))->unique()->values();
                                                 // Signed groups with no active parent (parent moved to not used or deleted) — show signed rows standalone
                                                 $orphanSignedKeys = $signedByParent->keys()->filter(fn($k) => !$parentKeysWithActiveParent->contains($k))->sortBy(fn($k) => $signedByParent->get($k)->min('created_at'));
                                                ?>
                                                <?php foreach ($parentDocs as $visaKey => $fetch): ?>
                                                    <?php
                                                    $admin = $fetch->staff;
                                                    $isForm956 = !empty($fetch->form956_id);

                                                    // Build file URL: once a file is uploaded to a Form 956 row, use it directly
                                                    // so the preview shows the actual uploaded (agent-signed) PDF, not the server-regenerated form.
                                                    if ($isForm956 && !empty($fetch->myfile)) {
                                                        $fileUrl = $fetch->myfile;
                                                        $downloadUrl = $fetch->myfile;
                                                    } elseif ($isForm956) {
                                                        $fileUrl = url()->route('forms.preview', $fetch->form956_id);
                                                        $downloadUrl = url()->route('forms.pdf', $fetch->form956_id);
                                                    } elseif (!empty($fetch->myfile) && strpos($fetch->myfile, 'http') === 0) {
                                                        $fileUrl = $fetch->myfile;
                                                        $downloadUrl = $fetch->myfile;
                                                    } else {
                                                        $fileUrl = 'https://' . env('AWS_BUCKET') . '.s3.' . env('AWS_DEFAULT_REGION') . '.amazonaws.com/' . $fetchedData->id . '/visa/' . ($fetch->myfile ?? '');
                                                        $downloadUrl = $fileUrl;
                                                    }
                                                    ?>
                                                    <tr class="drow" data-matterid="<?= $fetch->client_matter_id ?>" data-catid="<?= $fetch->folder_name ?>" id="id_<?= $fetch->id ?>">
                                                        <td style="white-space: initial;">
                                                            <div data-id="<?= $fetch->id ?>" data-visachecklistname="<?= htmlspecialchars($fetch->checklist) ?>" class="visachecklist-row" title="Uploaded by: <?= htmlspecialchars($admin->first_name ?? 'NA') ?> on <?= date('d/m/Y H:i', strtotime($fetch->created_at)) ?>" style="display: flex; align-items: center; gap: 8px;" oncontextmenu="showVisaChecklistContextMenu(event, <?= $fetch->id ?>); return false;">
                                                                <span style="flex: 1;"><?= htmlspecialchars($fetch->checklist) ?></span>
                                                            </div>
                                                        </td>
                                                        <td style="white-space: initial;">
                                                            <?php if ($fetch->file_name): ?>
                                                                <?php
                                                                $displayFileName = $fetch->getFilenameWithExtensionForDisplay();
                                                                $previewExtension = $fetch->getPreviewFileExtension();
                                                                $fileUrlJs = addslashes($fileUrl);
                                                                $downloadUrlJs = addslashes($downloadUrl ?? $fileUrl);
                                                                ?>
                                                                <div data-id="<?= $fetch->id ?>" data-name="<?= htmlspecialchars($fetch->file_name) ?>" class="doc-row" title="Uploaded by: <?= htmlspecialchars($admin->first_name ?? 'NA') ?> on <?= date('d/m/Y H:i', strtotime($fetch->created_at)) ?>" oncontextmenu="showVisaFileContextMenu(event, <?= $fetch->id ?>, '<?= htmlspecialchars($previewExtension) ?>', '<?= $fileUrlJs ?>', '<?= $id ?>', '<?= $fetch->status ?? 'draft' ?>'); return false;">
                                                                    <a href="javascript:void(0);" onclick="previewFile('<?= $previewExtension ?>','<?= $fileUrlJs ?>','preview-container-migdocumnetlist')">
                                                                        @icon('fa-file-image') <span><?= htmlspecialchars($displayFileName) ?></span>
                                                                    </a>
                                                                </div>
                                                            <?php elseif ($isForm956): ?>
                                                                <div class="form956-download-upload" style="display: flex; flex-direction: column; gap: 10px;" data-download-url="<?= e($downloadUrl) ?>" data-doc-id="<?= $fetch->id ?>">
                                                                    <p class="mb-0" style="font-size: 12px; color: #374151;">The Form 956 PDF downloads when you create it. Check, update, then upload your completed form below.</p>
                                                                    <div class="migration_upload_document" style="display: inline-block;">
                                                                        <form method="POST" enctype="multipart/form-data" id="mig_upload_form_<?= $fetch->id ?>">
                                                                            @csrf
                                                                            <input type="hidden" name="clientid" value="<?= $fetchedData->id ?>">
                                                                            <input type="hidden" name="client_matter_id" value="<?= $fetch->client_matter_id ?? '' ?>">
                                                                            <input type="hidden" name="fileid" value="<?= $fetch->id ?>">
                                                                            <input type="hidden" name="type" value="client">
                                                                            <input type="hidden" name="doctype" value="visa">
                                                                            <input type="hidden" name="doccategory" value="<?= $catVal->title ?>">
                                                                            <div class="document-drag-drop-zone visa-doc-drag-zone" data-fileid="<?= $fetch->id ?>" data-doccategory="<?= $id ?>" data-formid="mig_upload_form_<?= $fetch->id ?>">
                                                                                <div class="drag-zone-inner">
                                                                                    @icon('fa-cloud-upload-alt')
                                                                                    <span class="drag-zone-text">Drag file here or <strong>click to browse</strong></span>
                                                                                </div>
                                                                            </div>
                                                                            <input class="migdocupload d-none" data-fileid="<?= $fetch->id ?>" data-doccategory="<?= $id ?>" type="file" name="document_upload" style="display: none;"/>
                                                                        </form>
                                                                    </div>
                                                                </div>
                                                            <?php else: ?>
                                                                <div class="migration_upload_document" style="display: inline-block;">
                                                                    <form method="POST" enctype="multipart/form-data" id="mig_upload_form_<?= $fetch->id ?>">
                                                                        @csrf
                                                                        <input type="hidden" name="clientid" value="<?= $fetchedData->id ?>">
                                                                        <input type="hidden" name="client_matter_id" value="<?= $fetch->client_matter_id ?? '' ?>">
                                                                        <input type="hidden" name="fileid" value="<?= $fetch->id ?>">
                                                                        <input type="hidden" name="type" value="client">
                                                                        <input type="hidden" name="doctype" value="visa">
                                                                        <input type="hidden" name="doccategory" value="<?= $catVal->title ?>">
                                                                        <div class="document-drag-drop-zone visa-doc-drag-zone" data-fileid="<?= $fetch->id ?>" data-doccategory="<?= $id ?>" data-formid="mig_upload_form_<?= $fetch->id ?>">
                                                                            <div class="drag-zone-inner">
                                                                                @icon('fa-cloud-upload-alt')
                                                                                <span class="drag-zone-text">Drag file here or <strong>click to browse</strong></span>
                                                                            </div>
                                                                        </div>
                                                                        <input class="migdocupload d-none" data-fileid="<?= $fetch->id ?>" data-doccategory="<?= $id ?>" type="file" name="document_upload" style="display: none;"/>
                                                                    </form>
                                                                </div>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <a class="renamechecklist" data-id="<?= $fetch->id ?>" href="javascript:;" style="display: none;"></a>
                                                            <?php if (!$fetch->file_name): ?>
                                                            <a class="delete-checklist-btn" data-id="<?= $fetch->id ?>" data-checklist="<?= htmlspecialchars($fetch->checklist) ?>" href="javascript:;" style="display: none;"></a>
                                                            <?php endif; ?>
                                                            <?php if ($fetch->myfile): ?>
                                                                <a class="renamedoc" data-id="<?= $fetch->id ?>" href="javascript:;" style="display: none;"></a>
                                                                <a class="download-file" data-filelink="<?= e($downloadUrl ?? $fileUrl) ?>" data-filename="<?= e($fetch->myfile_key ?: basename($fetch->myfile ?? '')) ?>" data-id="<?= $fetch->id ?>" href="#" style="display: none;"></a>
                                                                <a class="notuseddoc" data-id="<?= $fetch->id ?>" data-doctype="visa" data-href="documents/not-used" href="javascript:;" style="display: none;"></a>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                    <?php
                                                    $docStatus = $fetch->status ?? '';
                                                    $showSigActionBar = in_array($docStatus, ['placed', 'sent']) && $fetch->doc_type === 'visa' && $fetch->file_name && ($fetch->filetype ?? '') === 'pdf';
                                                    if ($showSigActionBar):
                                                        $signingUrl = null;
                                                        if ($fetch->signature_doc_link) {
                                                            $links = json_decode($fetch->signature_doc_link, true);
                                                            $signingUrl = is_array($links) && isset($links[0]['url']) ? $links[0]['url'] : null;
                                                        }
                                                        $pendingSigner = $fetch->signers()->whereIn('status', ['pending'])->first();
                                                        $signerId = $pendingSigner ? $pendingSigner->id : null;
                                                    ?>
                                                    <tr class="visa-sig-action-bar" data-matterid="<?= $fetch->client_matter_id ?>" data-doc-id="<?= $fetch->id ?>" data-signer-id="<?= $signerId ?>" style="background: #f8f9fa; border-left: 4px solid #4a90e2;">
                                                        <td colspan="3" style="padding: 10px 16px;">
                                                            <div class="d-flex flex-wrap align-items-center gap-2" style="flex-wrap: wrap;">
                                                                <button type="button" class="btn btn-sm btn-primary visa-sig-send-btn" data-doc-id="<?= $fetch->id ?>" <?= $docStatus === 'sent' ? 'disabled' : '' ?>>
                                                                    @icon('fa-paper-plane', ['class' => 'mr-1']) Send
                                                                </button>
                                                                <button type="button" class="btn btn-sm btn-outline-secondary visa-sig-revise-btn" data-doc-id="<?= $fetch->id ?>">
                                                                    @icon('fa-edit', ['class' => 'mr-1']) Revise
                                                                </button>
                                                                <button type="button" class="btn btn-sm btn-outline-danger visa-sig-remove-btn" data-doc-id="<?= $fetch->id ?>">
                                                                    @icon('fa-times', ['class' => 'mr-1']) Remove
                                                                </button>
                                                                <?php if ($docStatus === 'sent' && $signingUrl && $signerId): ?>
                                                                <button type="button" class="btn btn-sm btn-outline-info visa-sig-reminder-btn" data-doc-id="<?= $fetch->id ?>" data-signer-id="<?= $signerId ?>">
                                                                    @icon('fa-bell', ['class' => 'mr-1']) Reminder
                                                                </button>
                                                                <?php endif; ?>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                    <?php endif; ?>
                                                    <?php
                                                    $signedKey = ($fetch->folder_name ?? '') . '|' . ($fetch->client_matter_id ?? '') . '|' . ($fetch->checklist ?? '');
                                                    $signedDocs = $signedByParent->get($signedKey, collect());
                                                    foreach ($signedDocs as $signedDoc):
                                                        $signedIsForm956 = !empty($signedDoc->form956_id);
                                                        if ($signedIsForm956 && !empty($signedDoc->myfile)) {
                                                            $signedFileUrl = $signedDoc->myfile;
                                                            $signedDownloadUrl = $signedDoc->myfile;
                                                        } elseif ($signedIsForm956) {
                                                            $signedFileUrl = url()->route('forms.preview', $signedDoc->form956_id);
                                                            $signedDownloadUrl = url()->route('forms.pdf', $signedDoc->form956_id);
                                                        } else {
                                                            $signedFileUrl = url()->route('documents.preview.signed', $signedDoc->id);
                                                            $signedDownloadUrl = $signedDoc->signed_doc_link ?? $signedDoc->myfile;
                                                        }
                                                        $signedPreviewExtension = $signedDoc->getPreviewFileExtension();
                                                        $signedDisplayName = $signedDoc->file_name
                                                            ? $signedDoc->getFilenameWithExtensionForDisplay()
                                                            : 'signed.' . $signedPreviewExtension;
                                                    ?>
                                                    <tr class="drow visa-signed-row" data-matterid="<?= $signedDoc->client_matter_id ?>" data-catid="<?= $signedDoc->folder_name ?>" id="id_<?= $signedDoc->id ?>">
                                                        <td style="white-space: initial;">
                                                            <div data-id="<?= $signedDoc->id ?>" data-visachecklistname="<?= htmlspecialchars($signedDoc->checklist) ?>" class="visachecklist-row" style="display: flex; align-items: center; gap: 8px;" oncontextmenu="showVisaChecklistContextMenu(event, <?= $signedDoc->id ?>); return false;">
                                                                <span style="flex: 1;"><?= htmlspecialchars($signedDoc->checklist) ?></span>
                                                            </div>
                                                        </td>
                                                        <td style="white-space: initial;">
                                                            <?php
                                                            $signedFileUrlJs = addslashes($signedFileUrl);
                                                            ?>
                                                            <div data-id="<?= $signedDoc->id ?>" data-name="<?= htmlspecialchars($signedDoc->file_name ?? '') ?>" class="doc-row" title="Signed document" oncontextmenu="showVisaFileContextMenu(event, <?= $signedDoc->id ?>, '<?= htmlspecialchars($signedPreviewExtension) ?>', '<?= $signedFileUrlJs ?>', '<?= $id ?>', '<?= $signedDoc->status ?? 'signed' ?>'); return false;">
                                                                <a href="javascript:void(0);" onclick="previewFile('<?= $signedPreviewExtension ?>','<?= $signedFileUrlJs ?>','preview-container-migdocumnetlist')">
                                                                    @icon('fa-file-image') <span><?= htmlspecialchars($signedDisplayName) ?></span>
                                                                </a>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <a class="renamechecklist" data-id="<?= $signedDoc->id ?>" href="javascript:;" style="display: none;"></a>
                                                            <a class="renamedoc" data-id="<?= $signedDoc->id ?>" href="javascript:;" style="display: none;"></a>
                                                            <a class="download-file" data-filelink="<?= e($signedDownloadUrl) ?>" data-filename="<?= e($signedDoc->getSignedDownloadFilename()) ?>" data-id="<?= $signedDoc->id ?>" href="#" style="display: none;"></a>
                                                            <a class="notuseddoc" data-id="<?= $signedDoc->id ?>" data-doctype="visa" data-href="documents/not-used" href="javascript:;" style="display: none;"></a>
                                                        </td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                <?php endforeach; ?>
                                                <?php
                                                // Orphan signed docs: parent moved to not used or deleted — show signed row(s) so signed version still displays
                                                foreach ($orphanSignedKeys as $orphanKey):
                                                    $signedDocs = $signedByParent->get($orphanKey, collect());
                                                    foreach ($signedDocs as $signedDoc):
                                                        $signedIsForm956 = !empty($signedDoc->form956_id);
                                                        if ($signedIsForm956 && !empty($signedDoc->myfile)) {
                                                            $signedFileUrl = $signedDoc->myfile;
                                                            $signedDownloadUrl = $signedDoc->myfile;
                                                        } elseif ($signedIsForm956) {
                                                            $signedFileUrl = url()->route('forms.preview', $signedDoc->form956_id);
                                                            $signedDownloadUrl = url()->route('forms.pdf', $signedDoc->form956_id);
                                                        } else {
                                                            $signedFileUrl = url()->route('documents.preview.signed', $signedDoc->id);
                                                            $signedDownloadUrl = $signedDoc->signed_doc_link ?? $signedDoc->myfile;
                                                        }
                                                        $signedPreviewExtension = $signedDoc->getPreviewFileExtension();
                                                        $signedDisplayName = $signedDoc->file_name
                                                            ? $signedDoc->getFilenameWithExtensionForDisplay()
                                                            : 'signed.' . $signedPreviewExtension;
                                                        $signedFileUrlJs = addslashes($signedFileUrl);
                                                ?>
                                                    <tr class="drow visa-signed-row" data-matterid="<?= $signedDoc->client_matter_id ?>" data-catid="<?= $signedDoc->folder_name ?>" id="id_<?= $signedDoc->id ?>">
                                                        <td style="white-space: initial;">
                                                            <div data-id="<?= $signedDoc->id ?>" data-visachecklistname="<?= htmlspecialchars($signedDoc->checklist) ?>" class="visachecklist-row" style="display: flex; align-items: center; gap: 8px;" oncontextmenu="showVisaChecklistContextMenu(event, <?= $signedDoc->id ?>); return false;">
                                                                <span style="flex: 1;"><?= htmlspecialchars($signedDoc->checklist) ?></span>
                                                            </div>
                                                        </td>
                                                        <td style="white-space: initial;">
                                                            <div data-id="<?= $signedDoc->id ?>" data-name="<?= htmlspecialchars($signedDoc->file_name ?? '') ?>" class="doc-row" title="Signed document" oncontextmenu="showVisaFileContextMenu(event, <?= $signedDoc->id ?>, '<?= htmlspecialchars($signedPreviewExtension) ?>', '<?= $signedFileUrlJs ?>', '<?= $id ?>', '<?= $signedDoc->status ?? 'signed' ?>'); return false;">
                                                                <a href="javascript:void(0);" onclick="previewFile('<?= $signedPreviewExtension ?>','<?= $signedFileUrlJs ?>','preview-container-migdocumnetlist')">
                                                                    @icon('fa-file-image') <span><?= htmlspecialchars($signedDisplayName) ?></span>
                                                                </a>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <a class="renamechecklist" data-id="<?= $signedDoc->id ?>" href="javascript:;" style="display: none;"></a>
                                                            <a class="renamedoc" data-id="<?= $signedDoc->id ?>" href="javascript:;" style="display: none;"></a>
                                                            <a class="download-file" data-filelink="<?= e($signedDownloadUrl) ?>" data-filename="<?= e($signedDoc->getSignedDownloadFilename()) ?>" data-id="<?= $signedDoc->id ?>" href="#" style="display: none;"></a>
                                                            <a class="notuseddoc" data-id="<?= $signedDoc->id ?>" data-doctype="visa" data-href="documents/not-used" href="javascript:;" style="display: none;"></a>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>

                                    <div class="grid_data miggriddata" style="display:none;">
                                        <?php foreach ($visaDocCatList as $gridCatVal):
                                            $documents = \App\Support\ClientDetailDocumentsTab::documentsForFolder($visaDocumentsByFolder, $gridCatVal->id);
                                            foreach ($documents as $fetch):
                                                if ($fetch->myfile):
                                                    ?>
                                                    <div class="grid_list" id="gid_<?= $fetch->id ?>">
                                                        <div class="grid_col">
                                                            <div class="grid_icon">
                                                                @icon('fa-file-image')
                                                            </div>
                                                            <div class="grid_content">
                                                                <span id="grid_<?= $fetch->id ?>" class="gridfilename"><?= htmlspecialchars($fetch->file_name) ?></span>
                                                                <div class="dropdown d-inline dropdown_ellipsis_icon">
                                                                    <a class="dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">@icon('fa-ellipsis-v')</a>
                                                                    <div class="dropdown-menu">
                                                                        <a target="_blank" class="dropdown-item" href="<?= $fetch->myfile ?>">Preview</a>
                                                                        <a href="#" class="dropdown-item download-file" data-filelink="<?= $fetch->myfile ?>" data-filename="<?= $fetch->myfile_key ?>">Download</a>
                                                                        <a data-id="<?= $fetch->id ?>" class="dropdown-item notuseddoc" data-doctype="visa" data-href="notuseddoc" href="javascript:;">Not Used</a>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                            <div class="clearfix"></div>
                                        <?php endforeach; ?>
                                    </div>

                                    <div class="preview-pane file-preview-container preview-container-migdocumnetlist" style="display: inline;margin-top: 15px !important; width: 499px;">
                                        <p style="color: #374151;">Click on a file to preview it here.</p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Context menu for visa checklist column (right-click checklist name) -->
            <div id="visaChecklistContextMenu" class="context-menu" style="display: none; position: fixed; background: white; border: 1px solid #ccc; border-radius: 4px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); z-index: 10000; min-width: 180px;">
                <div id="visa-checklist-context-rename" class="context-menu-item" onclick="handleVisaChecklistContextAction('rename-checklist')" style="padding: 8px 12px; cursor: pointer; border-bottom: 1px solid #eee;">
                    @icon('fa-edit', ['style' => 'margin-right: 8px;']) Rename Checklist
                </div>
                <div id="visa-checklist-context-delete" class="context-menu-item" onclick="handleVisaChecklistContextAction('delete-checklist')" style="padding: 8px 12px; cursor: pointer;">
                    @icon('fa-trash', ['style' => 'margin-right: 8px;']) Delete Checklist
                </div>
            </div>

            <!-- Custom Context Menu for Visa Documents -->
            <div id="visaFileContextMenu" class="context-menu" style="display: none; position: fixed; background: white; border: 1px solid #ccc; border-radius: 4px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); z-index: 10000; min-width: 180px;">
                <div id="visa-context-send-signature" class="context-menu-item" onclick="handleVisaContextAction('send-for-signature')" style="padding: 8px 12px; cursor: pointer; border-bottom: 1px solid #eee; display: none;">
                    @icon('fa-pen-fancy', ['style' => 'margin-right: 8px;']) Send for Signature
                </div>
                <div class="context-menu-item" onclick="handleVisaContextAction('rename-doc')" style="padding: 8px 12px; cursor: pointer; border-bottom: 1px solid #eee;">
                    @icon('fa-file-text', ['style' => 'margin-right: 8px;']) Rename File Name
                </div>
                <div class="context-menu-item" onclick="handleVisaContextAction('move')" style="padding: 8px 12px; cursor: pointer; border-bottom: 1px solid #eee;">
                    @icon('fa-arrows-alt', ['style' => 'margin-right: 8px;']) Move Document
                </div>
                <div class="context-menu-item" onclick="handleVisaContextAction('preview')" style="padding: 8px 12px; cursor: pointer; border-bottom: 1px solid #eee;">
                    @icon('fa-eye', ['style' => 'margin-right: 8px;']) Preview
                </div>
                <div id="visa-context-pdf-option" class="context-menu-item" onclick="handleVisaContextAction('pdf')" style="padding: 8px 12px; cursor: pointer; border-bottom: 1px solid #eee; display: none;">
                    @icon('fa-file-pdf', ['style' => 'margin-right: 8px;']) PDF
                </div>
                <div class="context-menu-item" onclick="handleVisaContextAction('download')" style="padding: 8px 12px; cursor: pointer; border-bottom: 1px solid #eee;">
                    @icon('fa-download', ['style' => 'margin-right: 8px;']) Download
                </div>
                <div class="context-menu-item" onclick="handleVisaContextAction('not-used')" style="padding: 8px 12px; cursor: pointer; border-bottom: 1px solid #eee;">
                    @icon('fa-trash', ['style' => 'margin-right: 8px;']) Not Used
                </div>
            </div>

            <!-- Move Visa Document Modal (shared with personal docs or separate if needed) -->
            <div class="modal fade" id="moveVisaDocumentModal" tabindex="-1" role="dialog">
                <div class="modal-dialog" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Move Document</h5>
                            <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <div class="form-group">
                                <label>Move to:</label>
                                <select id="moveVisaTargetType" class="form-control" style="margin-bottom: 15px;">
                                    <option value="">-- Select Destination --</option>
                                    <option value="personal">Personal Documents</option>
                                    <option value="visa">Visa Documents</option>
                                </select>
                            </div>
                            
                            <!-- For Personal Documents: Show Categories -->
                            <div class="form-group" id="moveVisaPersonalCategoryContainer" style="display: none;">
                                <label>Select Personal Category:</label>
                                <select id="moveVisaPersonalCategoryId" class="form-control">
                                    <option value="">-- Select Category --</option>
                                </select>
                            </div>
                            
                            <!-- For Visa Documents: Show Categories (like Personal Documents) -->
                            <div class="form-group" id="moveVisaVisaCategoryContainer" style="display: none;">
                                <label>Select Visa Category:</label>
                                <select id="moveVisaVisaCategoryId" class="form-control">
                                    <option value="">-- Select Category --</option>
                                </select>
                            </div>
                            
                            <div id="moveVisaDocumentError" class="alert alert-danger" style="display: none; margin-top: 10px;"></div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-primary" id="confirmMoveVisaDocument">Move Document</button>
                        </div>
                    </div>
                </div>
            </div>

            <script>
                let currentVisaContextFile = null;
                let currentVisaContextData = {};
                let currentVisaChecklistContextFile = null;

                function positionContextMenuAtCursor(menu, event) {
                    const MIN_PADDING = 5;
                    const CURSOR_OFFSET = 2;
                    menu.style.visibility = 'hidden';
                    menu.style.display = 'block';
                    const menuWidth = menu.offsetWidth;
                    const menuHeight = menu.offsetHeight;
                    menu.style.visibility = 'visible';

                    let menuLeft = event.clientX + CURSOR_OFFSET;
                    let menuTop = event.clientY + CURSOR_OFFSET;
                    if (menuLeft + menuWidth > window.innerWidth - MIN_PADDING) {
                        menuLeft = event.clientX - menuWidth - CURSOR_OFFSET;
                    }
                    if (menuTop + menuHeight > window.innerHeight - MIN_PADDING) {
                        menuTop = event.clientY - menuHeight - CURSOR_OFFSET;
                    }
                    menuLeft = Math.max(MIN_PADDING, Math.min(menuLeft, window.innerWidth - menuWidth - MIN_PADDING));
                    menuTop = Math.max(MIN_PADDING, Math.min(menuTop, window.innerHeight - menuHeight - MIN_PADDING));
                    menu.style.left = menuLeft + 'px';
                    menu.style.top = menuTop + 'px';
                    menu.style.display = 'block';
                }

                function configureVisaChecklistContextMenu(docId) {
                    const menu = document.getElementById('visaChecklistContextMenu');
                    if (!menu) return;
                    const hasFile = $('#id_' + docId).find('.doc-row').length > 0;
                    const deleteItem = document.getElementById('visa-checklist-context-delete');
                    const renameItem = document.getElementById('visa-checklist-context-rename');
                    if (deleteItem) {
                        deleteItem.style.display = hasFile ? 'none' : 'block';
                    }
                    if (renameItem) {
                        renameItem.style.borderBottom = hasFile ? 'none' : '1px solid #eee';
                    }
                }

                function showVisaChecklistContextMenu(event, docId) {
                    event.preventDefault();
                    event.stopPropagation();
                    hideVisaContextMenu();
                    hideVisaChecklistContextMenu();

                    currentVisaChecklistContextFile = docId;
                    const menu = document.getElementById('visaChecklistContextMenu');
                    configureVisaChecklistContextMenu(docId);
                    positionContextMenuAtCursor(menu, event);

                    setTimeout(function() {
                        document.addEventListener('click', hideVisaChecklistContextMenu);
                    }, 100);
                }

                function hideVisaChecklistContextMenu() {
                    const menu = document.getElementById('visaChecklistContextMenu');
                    if (menu) {
                        menu.style.display = 'none';
                    }
                    document.removeEventListener('click', hideVisaChecklistContextMenu);
                }

                function handleVisaChecklistContextAction(action) {
                    if (!currentVisaChecklistContextFile) return;
                    var docId = currentVisaChecklistContextFile;
                    hideVisaChecklistContextMenu();

                    switch (action) {
                        case 'rename-checklist':
                            $('.renamechecklist[data-id="' + docId + '"]').first().click();
                            break;
                        case 'delete-checklist':
                            $('.delete-checklist-btn[data-id="' + docId + '"]').first().click();
                            break;
                    }
                }

                function showVisaFileContextMenu(event, fileId, fileType, fileUrl, categoryId, fileStatus) {
                    event.preventDefault();
                    event.stopPropagation();
                    hideVisaChecklistContextMenu();
                    
                    currentVisaContextFile = fileId;
                    currentVisaContextData = {
                        fileId: fileId,
                        fileType: fileType,
                        fileUrl: fileUrl,
                        categoryId: categoryId,
                        fileStatus: fileStatus
                    };

                    const menu = document.getElementById('visaFileContextMenu');
                    
                    // Show/hide PDF option based on file type
                    const pdfOption = document.getElementById('visa-context-pdf-option');
                    const sendSigOption = document.getElementById('visa-context-send-signature');
                    const fileExt = fileType.toLowerCase();
                    if (['jpg', 'png', 'jpeg'].includes(fileExt)) {
                        pdfOption.style.display = 'block';
                    } else {
                        pdfOption.style.display = 'none';
                    }
                    // Show "Send for Signature" only for PDF and when not already signed
                    if (fileExt === 'pdf' && fileStatus !== 'signed') {
                        sendSigOption.style.display = 'block';
                    } else {
                        sendSigOption.style.display = 'none';
                    }


                    // Position menu at cursor (position: fixed uses viewport coordinates)
                    const MENU_WIDTH = 180;
                    const MENU_HEIGHT = 350;
                    const viewportWidth = window.innerWidth;
                    const viewportHeight = window.innerHeight;
                    const offset = 5;
                    
                    let menuLeft = event.clientX + offset;
                    let menuTop = event.clientY + offset;
                    
                    // Check right edge - if menu would go beyond viewport, show to the left of cursor
                    if (menuLeft + MENU_WIDTH > viewportWidth) {
                        menuLeft = event.clientX - MENU_WIDTH - offset;
                    }
                    
                    // Check bottom edge - if menu would go beyond viewport, show above cursor
                    if (menuTop + MENU_HEIGHT > viewportHeight) {
                        menuTop = event.clientY - MENU_HEIGHT - offset;
                    }
                    
                    // Keep menu inside viewport (left/top edges)
                    menuLeft = Math.max(offset, menuLeft);
                    menuTop = Math.max(offset, menuTop);
                    
                    menu.style.left = menuLeft + 'px';
                    menu.style.top = menuTop + 'px';

                    menu.style.display = 'block';

                    // Hide menu when clicking elsewhere
                    setTimeout(() => {
                        document.addEventListener('click', hideVisaContextMenu);
                    }, 100);
                }

                function hideVisaContextMenu() {
                    const menu = document.getElementById('visaFileContextMenu');
                    menu.style.display = 'none';
                    document.removeEventListener('click', hideVisaContextMenu);
                }

                function handleVisaContextAction(action) {
                    if (!currentVisaContextFile) return;

                    hideVisaContextMenu();

                    switch(action) {
                        case 'send-for-signature':
                            if (typeof $ !== 'undefined') {
                                $(document).trigger('openSignaturePlacementModal', { documentId: currentVisaContextFile });
                            }
                            break;
                        case 'rename-doc':
                            $('.renamedoc[data-id="' + currentVisaContextFile + '"]').click();
                            break;
                        case 'move':
                            openMoveVisaDocumentModal(currentVisaContextFile, 'visa');
                            break;
                        case 'preview':
                            // Prefer context menu fileUrl (preview route for signed docs; direct URL for unsigned). Fallback to download link for compatibility.
                            var $previewLink = $('.download-file[data-id="' + currentVisaContextFile + '"]').first();
                            var previewUrl = currentVisaContextData.fileUrl || ($previewLink.length ? $previewLink.attr('data-filelink') : null);
                            if (previewUrl) window.open(previewUrl, '_blank');
                            break;
                        case 'pdf':
                            const pdfUrl = '{{ URL::to('/document/download/pdf') }}/' + currentVisaContextFile;
                            window.open(pdfUrl, '_blank');
                            break;
                        case 'download':
                            // Prefer finding by document ID so we use the current link (updated after rename); fallback to filelink match
                            let $downloadBtn = $('.download-file[data-id="' + currentVisaContextFile + '"]');
                            if ($downloadBtn.length === 0) {
                                $downloadBtn = $('.download-file[data-filelink="' + currentVisaContextData.fileUrl + '"]');
                            }
                            if ($downloadBtn.length > 0) {
                                $downloadBtn.first().click();
                            } else {
                                console.error('Download button not found for file ID:', currentVisaContextFile);
                                alert('Download link not found. Please refresh the page and try again.');
                            }
                            break;
                        case 'not-used':
                            $('.notuseddoc[data-id="' + currentVisaContextFile + '"]').click();
                            break;
                    }
                }

                // ============================================================================
                // MOVE VISA DOCUMENT FUNCTIONALITY
                // ============================================================================
                let currentMoveVisaDocumentId = null;
                let currentMoveVisaDocumentType = null;

                function openMoveVisaDocumentModal(documentId, currentType) {
                    currentMoveVisaDocumentId = documentId;
                    currentMoveVisaDocumentType = currentType;
                    
                    // Reset modal
                    $('#moveVisaTargetType').val('');
                    $('#moveVisaPersonalCategoryContainer').hide();
                    $('#moveVisaVisaCategoryContainer').hide();
                    $('#moveVisaPersonalCategoryId').empty().append('<option value="">-- Select Category --</option>');
                    $('#moveVisaVisaCategoryId').empty().append('<option value="">-- Select Category --</option>');
                    $('#moveVisaDocumentError').hide();
                    
                    // Show modal
                    $('#moveVisaDocumentModal').modal('show');
                }

                // Handle target type change for visa documents
                $(document).on('change', '#moveVisaTargetType', function() {
                    const targetType = $(this).val();
                    
                    // Hide all containers first
                    $('#moveVisaPersonalCategoryContainer').hide();
                    $('#moveVisaVisaCategoryContainer').hide();
                    $('#moveVisaDocumentError').hide();
                    
                    if (!targetType) {
                        return;
                    }
                    
                    if (targetType === 'personal') {
                        // Load personal document categories from DOM (like personal tab)
                        const categories = [];
                        $('.subtab2-button').each(function() {
                            const catId = $(this).data('subtab2');
                            const catTitle = $(this).text().trim();
                            if (catId && catTitle) {
                                categories.push({ id: catId, title: catTitle });
                            }
                        });
                        
                        $('#moveVisaPersonalCategoryId').empty().append('<option value="">-- Select Category --</option>');
                        if (categories.length > 0) {
                            categories.forEach(cat => {
                                $('#moveVisaPersonalCategoryId').append(`<option value="${cat.id}">${cat.title}</option>`);
                            });
                        } else {
                            $('#moveVisaPersonalCategoryId').append('<option value="">No categories found</option>');
                        }
                        $('#moveVisaPersonalCategoryContainer').show();
                        
                    } else if (targetType === 'visa') {
                        // Load visa document categories from DOM (like personal - same UX)
                        const categories = [];
                        $('.subtab6-button').each(function() {
                            const catId = $(this).data('subtab6');
                            const catTitle = $(this).text().trim();
                            if (catId && catTitle) {
                                categories.push({ id: catId, title: catTitle });
                            }
                        });
                        
                        $('#moveVisaVisaCategoryId').empty().append('<option value="">-- Select Category --</option>');
                        if (categories.length > 0) {
                            categories.forEach(cat => {
                                $('#moveVisaVisaCategoryId').append(`<option value="${cat.id}">${cat.title}</option>`);
                            });
                        } else {
                            $('#moveVisaVisaCategoryId').append('<option value="">No categories found</option>');
                        }
                        $('#moveVisaVisaCategoryContainer').show();
                    }
                });

                // --- Visa Signature Action Bar: Send, Revise, Remove, Reminder ---
                $(document).on('click', '.visa-sig-send-btn', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    var docId = $(this).data('doc-id');
                    if (!docId) return;
                    var $btn = $(this);
                    var sendLabel = (typeof crmI === 'function' ? crmI('fas fa-paper-plane', { class: 'mr-1' }) : '<i class="fas fa-paper-plane mr-1"></i>') + ' Send';
                    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm mr-1"></span>Sending...');
                    $.ajax({
                        url: '{{ url("/signatures") }}/' + docId + '/send',
                        method: 'POST',
                        data: { _token: '{{ csrf_token() }}' },
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                    })
                        .done(function(resp) {
                            if (resp && resp.success === false) {
                                var errMsg = resp.message || 'Failed to send';
                                if (typeof iziToast !== 'undefined' && iziToast.show) {
                                    iziToast.show({ message: errMsg, color: 'red', position: 'topRight', timeout: 5000 });
                                } else { alert(errMsg); }
                                $btn.prop('disabled', false).html(sendLabel);
                                return;
                            }
                            var okMsg = (resp && resp.message) ? resp.message : 'Document sent for signature successfully.';
                            if (typeof iziToast !== 'undefined' && iziToast.show) {
                                iziToast.show({ message: okMsg, color: 'green', position: 'topRight', timeout: 4000 });
                            } else { alert(okMsg); }
                            $btn.prop('disabled', true).html(sendLabel);
                        })
                        .fail(function(xhr) {
                            var errMsg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Failed to send';
                            if (typeof iziToast !== 'undefined' && iziToast.show) {
                                iziToast.show({ message: errMsg, color: 'red', position: 'topRight', timeout: 5000 });
                            } else { alert(errMsg); }
                            $btn.prop('disabled', false).html(sendLabel);
                        });
                });
                $(document).on('click', '.visa-sig-revise-btn', function() {
                    var docId = $(this).data('doc-id');
                    if (docId) $(document).trigger('openSignaturePlacementModal', { documentId: docId });
                });
                $(document).on('click', '.visa-sig-remove-btn', function() {
                    if (!confirm('Remove signature request? The client will no longer be able to sign this document.')) return;
                    var $bar = $(this).closest('.visa-sig-action-bar');
                    var docId = $bar.data('doc-id');
                    var signerId = $bar.data('signer-id');
                    if (!docId || !signerId) { alert('Unable to remove.'); return; }
                    $.post('{{ url("/signatures") }}/' + docId + '/cancel', { _token: '{{ csrf_token() }}', signer_id: signerId })
                        .done(function() { location.reload(); })
                        .fail(function(xhr) { alert(xhr.responseJSON?.message || 'Failed to remove'); });
                });
                $(document).on('click', '.visa-sig-reminder-btn', function() {
                    var docId = $(this).data('doc-id');
                    var signerId = $(this).data('signer-id');
                    if (!docId || !signerId) return;
                    $.post('{{ url("/signatures") }}/' + docId + '/reminder', { _token: '{{ csrf_token() }}', signer_id: signerId })
                        .done(function() { alert('Reminder sent.'); location.reload(); })
                        .fail(function(xhr) { alert(xhr.responseJSON?.message || 'Failed to send reminder'); });
                });

                // Handle move visa document confirmation
                $(document).on('click', '#confirmMoveVisaDocument', function() {
                    const targetType = $('#moveVisaTargetType').val();
                    let targetId = null;
                    const $error = $('#moveVisaDocumentError');
                    const $btn = $(this);
                    
                    // Validate based on target type
                    if (!targetType) {
                        $error.text('Please select a destination type').show();
                        return;
                    }
                    
                    if (targetType === 'personal') {
                        targetId = $('#moveVisaPersonalCategoryId').val();
                        if (!targetId) {
                            $error.text('Please select a personal category').show();
                            return;
                        }
                    } else if (targetType === 'visa') {
                        targetId = $('#moveVisaVisaCategoryId').val();
                        if (!targetId) {
                            $error.text('Please select a visa category').show();
                            return;
                        }
                    }
                    
                    // Disable button and show loading
                    $btn.prop('disabled', true).text('Moving...');
                    $error.hide();
                    $('.popuploader').show();
                    
                    // Make AJAX request
                    $.ajax({
                        url: '{{ URL::to('/documents/move') }}',
                        type: 'POST',
                        data: {
                            _token: '{{ csrf_token() }}',
                            document_id: currentMoveVisaDocumentId,
                            target_type: targetType,
                            target_id: targetId
                        },
                        success: function(response) {
                            if (response.status) {
                                // Close modal
                                $('#moveVisaDocumentModal').modal('hide');
                                if (typeof hideVisaContextMenu === 'function') {
                                    hideVisaContextMenu();
                                }
                                
                                // Show success message using alert
                                alert(response.message || 'Document moved successfully');

                                var moved = false;
                                try {
                                    moved = !!applyVisaDocumentMoveUi(
                                        currentMoveVisaDocumentId,
                                        targetType,
                                        targetId,
                                        response.new_location || '',
                                        response.document || null
                                    );
                                } catch (moveErr) {
                                    console.warn('[MoveVisaDoc] UI update failed, falling back to reload', moveErr);
                                    moved = false;
                                }

                                if (!moved) {
                                    location.reload();
                                    return;
                                }
                            } else {
                                $error.text(response.message || 'Failed to move document').show();
                            }
                        },
                        error: function(xhr) {
                            let errorMsg = 'An error occurred while moving the document';
                            if (xhr.responseJSON && xhr.responseJSON.message) {
                                errorMsg = xhr.responseJSON.message;
                            }
                            $error.text(errorMsg).show();
                        },
                        complete: function() {
                            $('.popuploader').hide();
                            $btn.prop('disabled', false).text('Move Document');
                        }
                    });
                });

                function escapeVisaMoveJsAttr(value) {
                    return String(value == null ? '' : value)
                        .replace(/\\/g, '\\\\')
                        .replace(/'/g, "\\'");
                }

                function escapeVisaMoveHtml(value) {
                    return String(value == null ? '' : value)
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;')
                        .replace(/"/g, '&quot;')
                        .replace(/'/g, '&#39;');
                }

                /**
                 * Update Visa Documents UI after a successful move without full page reload.
                 * Visa → visa: relocate row (+ signature bar) into the target category.
                 * Visa → personal: remove from visa, insert into personal category, switch tab.
                 */
                function applyVisaDocumentMoveUi(documentId, targetType, targetId, targetTitle, docPayload) {
                    if (!documentId || !targetType || !targetId) {
                        return false;
                    }

                    var $visaTab = $('#visadocuments-tab');
                    if (!$visaTab.length) {
                        return false;
                    }

                    var $row = $visaTab.find('#id_' + documentId);
                    var $sigBar = $visaTab.find('.visa-sig-action-bar[data-doc-id="' + documentId + '"]');

                    if (targetType === 'visa') {
                        return moveVisaDocWithinVisaUi($row, $sigBar, documentId, targetId, targetTitle);
                    }

                    if (targetType === 'personal') {
                        var finishVisaToPersonalMove = function() {
                            if ($row.length) {
                                $row.remove();
                            }
                            if ($sigBar.length) {
                                $sigBar.remove();
                            }
                            if (!appendMovedVisaDocToPersonalCategory(docPayload, targetId, targetTitle, documentId)) {
                                return false;
                            }
                            activatePersonalDocumentsCategory(targetId);
                            return true;
                        };
                        if (typeof window.ensurePersonalDocumentsTabLoaded === 'function') {
                            return window.ensurePersonalDocumentsTabLoaded().then(finishVisaToPersonalMove);
                        }
                        return finishVisaToPersonalMove();
                    }

                    return false;
                }

                function moveVisaDocWithinVisaUi($row, $sigBar, documentId, targetId, targetTitle) {
                    var $visaTab = $('#visadocuments-tab');
                    var $targetTbody = $visaTab.find('.migdocumnetlist_' + targetId);
                    if (!$row.length || !$targetTbody.length) {
                        return false;
                    }

                    var categoryTitle = targetTitle
                        || $visaTab.find('.subtab6-button[data-subtab6="' + targetId + '"]').first().text().trim()
                        || '';

                    $row.attr('data-catid', targetId);

                    $row.find('.doc-row').each(function() {
                        var $docRow = $(this);
                        var fileId = $docRow.data('id') || documentId;
                        var fileType = 'pdf';
                        var fileUrl = '';
                        var fileStatus = 'draft';
                        var onctx = $docRow.attr('oncontextmenu') || '';
                        var parsed = onctx.match(/showVisaFileContextMenu\(\s*event\s*,\s*([^,]+)\s*,\s*'((?:\\'|[^'])*)'\s*,\s*'((?:\\'|[^'])*)'\s*,\s*'((?:\\'|[^'])*)'\s*,\s*'((?:\\'|[^'])*)'/);
                        if (parsed) {
                            fileType = parsed[2].replace(/\\'/g, "'") || fileType;
                            fileUrl = parsed[3].replace(/\\'/g, "'") || fileUrl;
                            fileStatus = parsed[5].replace(/\\'/g, "'") || fileStatus;
                        } else if (typeof currentVisaContextData !== 'undefined' && currentVisaContextData) {
                            fileType = currentVisaContextData.fileType || fileType;
                            fileUrl = currentVisaContextData.fileUrl || fileUrl;
                            fileStatus = currentVisaContextData.fileStatus || fileStatus;
                        }

                        $docRow.attr(
                            'oncontextmenu',
                            "showVisaFileContextMenu(event, " + fileId + ", '" + escapeVisaMoveJsAttr(fileType) + "', '" + escapeVisaMoveJsAttr(fileUrl) + "', '" + escapeVisaMoveJsAttr(targetId) + "', '" + escapeVisaMoveJsAttr(fileStatus) + "'); return false;"
                        );
                    });

                    $row.find('.visa-doc-drag-zone, .migdocupload').attr('data-doccategory', targetId);
                    if (categoryTitle) {
                        $row.find('input[name="doccategory"]').val(categoryTitle);
                        $row.find('a.notuseddoc').attr('data-doccategory', categoryTitle);
                    }

                    $targetTbody.prepend($row);
                    if ($sigBar.length) {
                        $row.after($sigBar);
                    }

                    $visaTab.find('.subtab6-button').removeClass('active');
                    $visaTab.find('.subtab6-pane').removeClass('active');
                    $visaTab.find('.subtab6-button[data-subtab6="' + targetId + '"]').addClass('active');
                    $visaTab.find('[id="' + targetId + '-subtab6"]').addClass('active');

                    if (typeof window.initVisaDocDragDrop === 'function') {
                        window.initVisaDocDragDrop();
                    }

                    return true;
                }

                function appendMovedVisaDocToPersonalCategory(docPayload, categoryId, categoryTitle, fallbackDocId) {
                    var docId = (docPayload && docPayload.id) ? docPayload.id : fallbackDocId;
                    if (!docId) {
                        return false;
                    }

                    var $tab = $('#personaldocuments-tab');
                    var $tbody = $tab.find('.documnetlist_' + categoryId);
                    if (!$tab.length || !$tbody.length) {
                        return false;
                    }
                    if ($tbody.find('#id_' + docId).length) {
                        return true;
                    }

                    var checklist = (docPayload && docPayload.checklist) ? docPayload.checklist : 'Document';
                    var fileName = (docPayload && docPayload.file_name) ? docPayload.file_name : '';
                    var fileType = (docPayload && docPayload.filetype) ? docPayload.filetype : 'pdf';
                    var fileUrl = (docPayload && docPayload.myfile) ? docPayload.myfile : '';
                    var status = (docPayload && docPayload.status) ? docPayload.status : 'draft';
                    var catTitle = categoryTitle
                        || $tab.find('.subtab2-button[data-subtab2="' + categoryId + '"]').first().text().trim()
                        || '';
                    var iconHtml = (typeof crmI === 'function') ? crmI('fa-file-image') : '';
                    var displayName = fileName ? (fileName + (fileType ? ('.' + fileType) : '')) : '';

                    var fileCellHtml;
                    if (fileName && fileUrl) {
                        fileCellHtml =
                            '<div data-id="' + docId + '" data-name="' + escapeVisaMoveHtml(fileName) + '" class="doc-row" ' +
                            'oncontextmenu="showFileContextMenu(event, ' + docId + ', \'' + escapeVisaMoveJsAttr(fileType) + '\', \'' + escapeVisaMoveJsAttr(fileUrl) + '\', \'' + escapeVisaMoveJsAttr(categoryId) + '\', \'' + escapeVisaMoveJsAttr(status) + '\'); return false;">' +
                            '<a href="javascript:void(0);" onclick="previewFile(\'' + escapeVisaMoveJsAttr(fileType) + '\',\'' + escapeVisaMoveJsAttr(fileUrl) + '\',\'preview-container-' + escapeVisaMoveJsAttr(categoryId) + '\')">' +
                            iconHtml + ' <span>' + escapeVisaMoveHtml(displayName) + '</span></a></div>';
                    } else {
                        fileCellHtml =
                            '<div class="upload_document" style="display: inline-block;">' +
                            '<form method="POST" enctype="multipart/form-data" id="upload_form_' + docId + '">' +
                            '<input type="hidden" name="_token" value="' + ($('meta[name="csrf-token"]').attr('content') || '') + '">' +
                            '<input type="hidden" name="clientid" value="' + escapeVisaMoveHtml((window.ClientDetailConfig && window.ClientDetailConfig.clientId) || '') + '">' +
                            '<input type="hidden" name="fileid" value="' + docId + '">' +
                            '<input type="hidden" name="type" value="client">' +
                            '<input type="hidden" name="doctype" value="personal">' +
                            '<input type="hidden" name="doccategory" value="' + escapeVisaMoveHtml(catTitle) + '">' +
                            '<div class="document-drag-drop-zone personal-doc-drag-zone" data-fileid="' + docId + '" data-doccategory="' + escapeVisaMoveHtml(categoryId) + '" data-formid="upload_form_' + docId + '">' +
                            '<div class="drag-zone-inner"><span class="drag-zone-text">Drag file here or <strong>click to browse</strong></span></div></div>' +
                            '<input class="docupload d-none" data-fileid="' + docId + '" data-doccategory="' + escapeVisaMoveHtml(categoryId) + '" type="file" name="document_upload" style="display: none;"/>' +
                            '</form></div>';
                    }

                    var rowHtml =
                        '<tr class="drow" id="id_' + docId + '">' +
                            '<td style="white-space: initial;">' +
                                '<div data-id="' + docId + '" data-personalchecklistname="' + escapeVisaMoveHtml(checklist) + '" class="personalchecklist-row" ' +
                                'style="display: flex; align-items: center; gap: 8px;" ' +
                                'oncontextmenu="showPersonalChecklistContextMenu(event, ' + docId + '); return false;">' +
                                '<span style="flex: 1;">' + escapeVisaMoveHtml(checklist) + '</span></div>' +
                            '</td>' +
                            '<td style="white-space: initial;">' + fileCellHtml + '</td>' +
                            '<td>' +
                                '<a class="renamechecklist" data-id="' + docId + '" href="javascript:;" style="display: none;"></a>' +
                                (fileName
                                    ? '<a class="renamedoc" data-id="' + docId + '" href="javascript:;" style="display: none;"></a>' +
                                      '<a class="download-file" data-filelink="' + escapeVisaMoveHtml(fileUrl) + '" data-filename="' + escapeVisaMoveHtml(fileName) + '" data-id="' + docId + '" href="#" style="display: none;"></a>' +
                                      '<a class="notuseddoc" data-id="' + docId + '" data-doctype="personal" data-doccategory="' + escapeVisaMoveHtml(catTitle) + '" data-href="documents/not-used" href="javascript:;" style="display: none;"></a>'
                                    : '<a class="delete-checklist-btn" data-id="' + docId + '" data-checklist="' + escapeVisaMoveHtml(checklist) + '" href="javascript:;" style="display: none;"></a>') +
                            '</td>' +
                        '</tr>';

                    $tbody.prepend(rowHtml);
                    if (typeof refreshLucideIcons === 'function') {
                        refreshLucideIcons($tbody.find('#id_' + docId)[0]);
                    }
                    return true;
                }

                function activatePersonalDocumentsCategory(categoryId) {
                    var apply = function() {
                        if (typeof SidebarTabs !== 'undefined' && typeof SidebarTabs.activateTab === 'function') {
                            SidebarTabs.activateTab('personaldocuments');
                        } else {
                            $('.client-nav-button').removeClass('active');
                            $('.tab-pane').removeClass('active');
                            $('.client-nav-button[data-tab="personaldocuments"]').addClass('active');
                            $('#personaldocuments-tab').addClass('active');
                            localStorage.setItem('activeTab', 'personaldocuments');
                        }

                        var $tab = $('#personaldocuments-tab');
                        $tab.find('.subtab2-button').removeClass('active');
                        $tab.find('.subtab2-pane').removeClass('active');
                        $tab.find('.subtab2-button[data-subtab2="' + categoryId + '"]').addClass('active');
                        $tab.find('[id="' + categoryId + '-subtab2"]').addClass('active');
                    };

                    if (typeof window.ensurePersonalDocumentsTabLoaded === 'function') {
                        window.ensurePersonalDocumentsTabLoaded().then(apply).catch(function() {
                            apply();
                        });
                        return;
                    }
                    apply();
                }

                // Reset button state when modal is closed
                $('#moveVisaDocumentModal').on('hidden.bs.modal', function() {
                    $('#confirmMoveVisaDocument').prop('disabled', false).text('Move Document');
                });

                // Hide context menu on escape key
                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape') {
                        hideVisaContextMenu();
                    }
                });

                // Form 956: PDF downloads once when the form is created (see detail-main.js + Form956Controller@store).
                // Kept as a no-op so older callers (if any) do not throw; sidebar-tabs no longer invokes bulk download on reload.
                window.autoDownloadForm956Pdfs = function() {};
            </script>

            <script>
                // ============================================================================
                // VISA DOCUMENTS - DRAG AND DROP INITIALIZATION
                // ============================================================================
                console.log('🚀 Visa Documents Tab Script Loading...');
                
                function initVisaDocDragDrop() {
                    console.log('🔄 Initializing Visa Doc Drag & Drop...');
                    console.log('📊 Drop zones found:', $('.visa-doc-drag-zone').length);
                    console.log('📊 Visible drop zones:', $('.visa-doc-drag-zone:visible').length);
                    
                    // Check each drop zone
                    $('.visa-doc-drag-zone').each(function(index) {
                        var $zone = $(this);
                        var fileid = $zone.data('fileid');
                        var formid = $zone.data('formid');
                        var isVisible = $zone.is(':visible');
                        console.log('🔍 Drop zone #' + index + ':', {
                            fileid: fileid,
                            formid: formid,
                            visible: isVisible,
                            hasFileInput: $('#' + formid).find('.migdocupload').length > 0
                        });
                    });
                    
                    // IMPORTANT: Remove ALL handlers (including those from detail-main.js)
                    $('.visa-doc-drag-zone').off('click');
                    $('.visa-doc-drag-zone').off('dragenter');
                    $('.visa-doc-drag-zone').off('dragover');
                    $('.visa-doc-drag-zone').off('dragleave');
                    $('.visa-doc-drag-zone').off('drop');
                    
                    // Also remove delegated event handlers
                    $(document).off('click', '.visa-doc-drag-zone');
                    $(document).off('dragenter', '.visa-doc-drag-zone');
                    $(document).off('dragover', '.visa-doc-drag-zone');
                    $(document).off('dragleave', '.visa-doc-drag-zone');
                    $(document).off('drop', '.visa-doc-drag-zone');
                    
                    // Attach handlers DIRECTLY to each drop zone element
                    $('.visa-doc-drag-zone').each(function() {
                        var $zone = $(this);
                        
                        // Click handler
                        $zone.on('click', function(e) {
                            console.log('🎯 DIRECT CLICK HANDLER - visa-doc-drag-zone clicked');
                            e.preventDefault();
                            e.stopPropagation();
                            e.stopImmediatePropagation();
                            
                            var fileid = $(this).data('fileid');
                            var formid = $(this).data('formid');
                            console.log('📂 File ID:', fileid, 'Form ID:', formid);
                            
                            var fileInput = $('#' + formid).find('.migdocupload');
                            console.log('📁 File input found:', fileInput.length > 0);
                            
                            if (fileInput.length > 0) {
                                console.log('✅ Triggering file input click...');
                                fileInput[0].click();
                            } else {
                                console.error('❌ File input not found for fileid:', fileid);
                            }
                            
                            return false;
                        });
                        
                        // Dragenter handler
                        $zone.on('dragenter', function(e) {
                            console.log('🔥 DIRECT DRAGENTER HANDLER (VISA)');
                            e.preventDefault();
                            e.stopPropagation();
                            e.stopImmediatePropagation();
                            $(this).addClass('drag_over');
                            return false;
                        });
                        
                        // Dragover handler
                        $zone.on('dragover', function(e) {
                            console.log('🔥 DIRECT DRAGOVER HANDLER (VISA)');
                            var event = e.originalEvent || e;
                            event.preventDefault();
                            event.stopPropagation();
                            
                            if (event.dataTransfer) {
                                event.dataTransfer.dropEffect = 'copy';
                            }
                            
                            $(this).addClass('drag_over');
                            return false;
                        });
                        
                        // Dragleave handler
                        $zone.on('dragleave', function(e) {
                            console.log('⚠️ DIRECT DRAGLEAVE HANDLER (VISA)');
                            e.preventDefault();
                            e.stopPropagation();
                            
                            var rect = this.getBoundingClientRect();
                            var x = e.originalEvent.clientX;
                            var y = e.originalEvent.clientY;
                            
                            if (x <= rect.left || x >= rect.right || y <= rect.top || y >= rect.bottom) {
                                $(this).removeClass('drag_over');
                            }
                            return false;
                        });
                        
                        // Drop handler
                        $zone.on('drop', function(e) {
                            console.log('🎯 DIRECT DROP HANDLER (VISA)');
                            var event = e.originalEvent || e;
                            event.preventDefault();
                            event.stopPropagation();
                            event.stopImmediatePropagation();
                            
                            $(this).removeClass('drag_over');
                            
                            var files = event.dataTransfer ? event.dataTransfer.files : null;
                            if (files && files.length > 0) {
                                console.log('📄 File dropped:', files[0].name);
                                
                                var fileid = $(this).data('fileid');
                                var formid = $(this).data('formid');
                                var fileInput = $('#' + formid).find('.migdocupload')[0];
                                
                                if (fileInput) {
                                    try {
                                        var dataTransfer = new DataTransfer();
                                        dataTransfer.items.add(files[0]);
                                        fileInput.files = dataTransfer.files;
                                        console.log('✅ File assigned using DataTransfer');
                                    } catch(err) {
                                        console.warn('⚠️ Fallback to direct assignment');
                                        try {
                                            fileInput.files = files;
                                        } catch(err2) {
                                            console.error('❌ Could not assign file:', err2);
                                        }
                                    }
                                    
                                    $(fileInput).trigger('change');
                                    console.log('✅ Change event triggered');
                                } else {
                                    console.error('❌ File input not found');
                                }
                            }
                            return false;
                        });
                    });
                    
                    // Prevent default drag behavior on document
                    $(document).off('dragover.visadoc').on('dragover.visadoc', function(e) {
                        if ($(e.target).closest('.visa-doc-drag-zone').length > 0) {
                            return;
                        }
                        e.preventDefault();
                    });
                    
                    $(document).off('drop.visadoc').on('drop.visadoc', function(e) {
                        if ($(e.target).closest('.visa-doc-drag-zone').length > 0) {
                            return;
                        }
                        e.preventDefault();
                    });
                    
                    console.log('✅ Visa doc drag-drop handlers attached');
                }
                
                // Initialize on DOM ready
                $(document).ready(function() {
                    console.log('✅ Visa Documents DOM Ready');
                    initVisaDocDragDrop();
                });
                
                // Re-initialize when Visa Documents tab is shown
                $(document).on('click', '.client-nav-button[data-tab="visadocuments"]', function() {
                    console.log('📂 Visa Documents tab clicked, reinitializing...');
                    setTimeout(function() {
                        initVisaDocDragDrop();
                    }, 200);
                });
                
                // Also check if tab is already active (e.g., direct URL navigation)
                if ($('#visadocuments-tab').hasClass('active')) {
                    console.log('📂 Visa Documents tab already active on load');
                    setTimeout(function() {
                        initVisaDocDragDrop();
                    }, 500);
                }

                // Soft Personal→Visa matter switch reuses this after replacing #visadocuments-tab HTML.
                window.initVisaDocDragDrop = initVisaDocDragDrop;

                // After Personal → Visa move navigation, open the destination category.
                (function activatePendingVisaCategoryAfterMove() {
                    var pendingCategoryId = localStorage.getItem('pendingVisaDocCategoryId');
                    if (!pendingCategoryId) {
                        return;
                    }
                    localStorage.removeItem('pendingVisaDocCategoryId');
                    setTimeout(function() {
                        var $btn = $('#visadocuments-tab .subtab6-button[data-subtab6="' + pendingCategoryId + '"]');
                        if ($btn.length) {
                            $btn.trigger('click');
                        }
                    }, 400);
                })();
                
                // ============================================================================
                // VISA BULK UPLOAD FUNCTIONALITY
                // ============================================================================
                
                let bulkUploadVisaFiles = {};
                let currentVisaCategoryId = null;
                let currentVisaMatterId = <?= $client_selected_matter_id1 ?? 'null' ?>;
                let currentVisaClientId = <?= $fetchedData->id ?>;
                
                // Toggle bulk upload dropzone for visa
                $(document).on('click', '.bulk-upload-toggle-btn-visa', function() {
                    const categoryId = $(this).data('categoryid');
                    const matterId = $(this).data('matterid');
                    const dropzoneContainer = $('#bulk-upload-visa-' + categoryId);
                    
                    // Hide all other dropzones first
                    $('.bulk-upload-dropzone-container-visa').not('#bulk-upload-visa-' + categoryId).slideUp();
                    $('.bulk-upload-toggle-btn-visa').not(this).html(crmI('fas fa-upload') + ' Bulk Upload');
                    
                    if (dropzoneContainer.is(':visible')) {
                        dropzoneContainer.slideUp();
                        $(this).html(crmI('fas fa-upload') + ' Bulk Upload');
                        // Clear files if closing
                        bulkUploadVisaFiles[categoryId] = [];
                        dropzoneContainer.find('.bulk-upload-file-list-visa').hide();
                        dropzoneContainer.find('.bulk-upload-files-container-visa').empty();
                        dropzoneContainer.find('.file-count-visa').text('0');
                    } else {
                        dropzoneContainer.slideDown();
                        $(this).html(crmI('fas fa-times') + ' Close');
                        currentVisaCategoryId = categoryId;
                        currentVisaMatterId = matterId || null;
                    }
                });
                
                // Initialize bulk upload files array for each visa category
                $('.bulk-upload-dropzone-visa').each(function() {
                    const categoryId = $(this).data('categoryid');
                    if (!bulkUploadVisaFiles[categoryId]) {
                        bulkUploadVisaFiles[categoryId] = [];
                    }
                });
                
                // Click to browse files for visa
                $(document).on('click', '.bulk-upload-dropzone-visa', function(e) {
                    if (!$(e.target).is('input')) {
                        const categoryId = $(this).data('categoryid');
                        $(this).closest('.bulk-upload-dropzone-container-visa').find('.bulk-upload-file-input-visa[data-categoryid="' + categoryId + '"]').click();
                    }
                });
                
                // File input change for visa
                $(document).on('change', '.bulk-upload-file-input-visa', function() {
                    const categoryId = $(this).data('categoryid');
                    const matterId = $(this).data('matterid');
                    const files = this.files;
                    
                    if (files.length > 0) {
                        handleBulkVisaFilesSelected(categoryId, matterId, files);
                    }
                });
                
                // Attach DIRECT handlers to visa bulk upload dropzones for highest priority
                function initVisaBulkUploadDragDrop() {
                    console.log('🔄 Initializing Visa Bulk Upload Drag & Drop...');
                    console.log('📊 Visa bulk upload zones found:', $('.bulk-upload-dropzone-visa').length);
                    
                    $('.bulk-upload-dropzone-visa').each(function() {
                        var $zone = $(this);
                        var elem = this;
                        
                        // Remove old native listeners if they exist
                        if (elem._visaBulkDragOver) {
                            elem.removeEventListener('dragover', elem._visaBulkDragOver);
                        }
                        if (elem._visaBulkDrop) {
                            elem.removeEventListener('drop', elem._visaBulkDrop);
                        }
                        if (elem._visaBulkDragEnter) {
                            elem.removeEventListener('dragenter', elem._visaBulkDragEnter);
                        }
                        if (elem._visaBulkDragLeave) {
                            elem.removeEventListener('dragleave', elem._visaBulkDragLeave);
                        }
                        
                        // Dragover handler (REQUIRED for drop to work)
                        elem._visaBulkDragOver = function(e) {
                            console.log('🔥 NATIVE VISA BULK DRAGOVER');
                            e.preventDefault();
                            e.stopPropagation();
                            e.dataTransfer.dropEffect = 'copy';
                            $zone.addClass('drag_over');
                        };
                        elem.addEventListener('dragover', elem._visaBulkDragOver);
                        
                        // Dragenter handler
                        elem._visaBulkDragEnter = function(e) {
                            console.log('🔥 NATIVE VISA BULK DRAGENTER');
                            e.preventDefault();
                            e.stopPropagation();
                            $zone.addClass('drag_over');
                        };
                        elem.addEventListener('dragenter', elem._visaBulkDragEnter);
                        
                        // Dragleave handler
                        elem._visaBulkDragLeave = function(e) {
                            console.log('⚠️ NATIVE VISA BULK DRAGLEAVE');
                            e.preventDefault();
                            e.stopPropagation();
                            
                            var rect = elem.getBoundingClientRect();
                            if (e.clientX <= rect.left || e.clientX >= rect.right || 
                                e.clientY <= rect.top || e.clientY >= rect.bottom) {
                                $zone.removeClass('drag_over');
                            }
                        };
                        elem.addEventListener('dragleave', elem._visaBulkDragLeave);
                        
                        // Drop handler
                        elem._visaBulkDrop = function(e) {
                            console.log('🎯 NATIVE VISA BULK DROP');
                            e.preventDefault();
                            e.stopPropagation();
                            $zone.removeClass('drag_over');
                            
                            var files = e.dataTransfer ? e.dataTransfer.files : null;
                            console.log('📄 Visa files dropped:', files ? files.length : 0);
                            
                            if (files && files.length > 0) {
                                var categoryId = $zone.data('categoryid');
                                var matterId = $zone.data('matterid');
                                console.log('📂 Category ID:', categoryId, 'Matter ID:', matterId);
                                handleBulkVisaFilesSelected(categoryId, matterId, files);
                            } else {
                                console.error('❌ No files in visa drop event');
                            }
                        };
                        elem.addEventListener('drop', elem._visaBulkDrop);
                        
                        console.log('✅ Attached native handlers to visa bulk dropzone:', $zone.data('categoryid'));
                    });
                }
                
                // Initialize visa bulk upload drag-drop when container becomes visible
                $(document).on('click', '.bulk-upload-toggle-btn-visa', function() {
                    setTimeout(function() {
                        initVisaBulkUploadDragDrop();
                    }, 300); // Wait for slideDown animation
                });
                
                // Also initialize on DOM ready for any visible dropzones
                $(document).ready(function() {
                    initVisaBulkUploadDragDrop();
                });
                
                // Keep delegated handlers as fallback
                $(document).on('dragover', '.bulk-upload-dropzone-visa', function(e) {
                    console.log('🔥 DELEGATED VISA BULK DRAGOVER');
                    e.preventDefault();
                    e.stopPropagation();
                    $(this).addClass('drag_over');
                    if (e.originalEvent && e.originalEvent.dataTransfer) {
                        e.originalEvent.dataTransfer.dropEffect = 'copy';
                    }
                    return false;
                });
                
                $(document).on('dragenter', '.bulk-upload-dropzone-visa', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    $(this).addClass('drag_over');
                    return false;
                });
                
                $(document).on('dragleave', '.bulk-upload-dropzone-visa', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    var rect = this.getBoundingClientRect();
                    var x = e.originalEvent.clientX;
                    var y = e.originalEvent.clientY;
                    if (x <= rect.left || x >= rect.right || y <= rect.top || y >= rect.bottom) {
                        $(this).removeClass('drag_over');
                    }
                    return false;
                });
                
                $(document).on('drop', '.bulk-upload-dropzone-visa', function(e) {
                    console.log('🎯 DELEGATED VISA BULK DROP');
                    e.preventDefault();
                    e.stopPropagation();
                    $(this).removeClass('drag_over');
                    
                    const categoryId = $(this).data('categoryid');
                    const matterId = $(this).data('matterid');
                    const files = e.originalEvent && e.originalEvent.dataTransfer ? e.originalEvent.dataTransfer.files : null;
                    
                    console.log('📄 Visa files dropped:', files ? files.length : 0);
                    
                    if (files && files.length > 0) {
                        handleBulkVisaFilesSelected(categoryId, matterId, files);
                    } else {
                        console.error('❌ No files in visa drop event');
                    }
                    return false;
                });
                
                // Handle visa files selected
                function handleBulkVisaFilesSelected(categoryId, matterId, files) {
                    if (!bulkUploadVisaFiles[categoryId]) {
                        bulkUploadVisaFiles[categoryId] = [];
                    }
                    
                    // Validate and add files to array
                    const invalidFiles = [];
                    const maxSize = 20 * 1024 * 1024; // 20MB
                    const allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
                    
                    Array.from(files).forEach(file => {
                        // Check file size
                        if (file.size > maxSize) {
                            invalidFiles.push(file.name + ' (exceeds 20MB)');
                            return;
                        }
                        
                        // Check file extension
                        const ext = file.name.split('.').pop().toLowerCase();
                        if (!allowedExtensions.includes(ext)) {
                            invalidFiles.push(file.name + ' (invalid file type)');
                            return;
                        }

                        // Check filename characters (same rules as single upload; WAF-safe name applied on POST)
                        if (typeof mmIsAllowedDocumentFilename === 'function' ? !mmIsAllowedDocumentFilename(file.name) : !/^[a-zA-Z0-9_\-\.\s\$\(\),&+']+$/.test(file.name)) {
                            invalidFiles.push(file.name + ' (invalid characters in name)');
                            return;
                        }
                        
                        // Check if file already exists
                        const exists = bulkUploadVisaFiles[categoryId].some(f => f.name === file.name && f.size === file.size);
                        if (!exists) {
                            bulkUploadVisaFiles[categoryId].push(file);
                        }
                    });
                    
                    if (invalidFiles.length > 0) {
                        alert('The following files were skipped:\n' + invalidFiles.join('\n'));
                    }
                    
                    if (bulkUploadVisaFiles[categoryId].length === 0) {
                        alert('No valid files selected. Please select PDF, JPG, PNG, DOC, or DOCX files under 20MB.');
                        return;
                    }
                    
                    // Show file list
                    const container = $('#bulk-upload-visa-' + categoryId);
                    container.find('.bulk-upload-file-list-visa').show();
                    container.find('.file-count-visa').text(bulkUploadVisaFiles[categoryId].length);
                    
                    // Show mapping interface
                    showBulkVisaUploadMapping(categoryId, matterId);
                }
                
                // Show visa mapping interface
                function showBulkVisaUploadMapping(categoryId, matterId) {
                    currentVisaCategoryId = categoryId;
                    currentVisaMatterId = matterId || null;
                    const files = bulkUploadVisaFiles[categoryId];
                    
                    if (files.length === 0) {
                        return;
                    }
                    
                    // Get existing checklists for this visa category
                    getExistingVisaChecklists(categoryId, function(checklists) {
                        // Call backend to get auto-matches
                        getAutoVisaChecklistMatches(categoryId, files, checklists, function(matches) {
                            displayVisaMappingInterface(files, checklists, matches);
                        });
                    });
                }
                
                // Get existing visa checklists
                function getExistingVisaChecklists(categoryId, callback) {
                    const checklists = [];
                    const checklistNames = new Set();
                    
                    $('.migdocumnetlist_' + categoryId + ' .visachecklist-row').each(function() {
                        const checklistName = $(this).data('visachecklistname');
                        const checklistId = $(this).closest('tr').attr('id').replace('id_', '');
                        
                        if (checklistName && !checklistNames.has(checklistName)) {
                            checklistNames.add(checklistName);
                            checklists.push({
                                id: checklistId,
                                name: checklistName
                            });
                        }
                    });
                    
                    callback(checklists);
                }
                
                // Get auto-checklist matches for visa from backend
                function getAutoVisaChecklistMatches(categoryId, files, checklists, callback) {
                    const fileData = Array.from(files).map(file => ({
                        name: file.name,
                        size: file.size,
                        type: file.type
                    }));
                    
                    const checklistNames = checklists.map(c => c.name);
                    
                    $.ajax({
                        url: '{{ route("clients.documents.getAutoChecklistMatches") }}',
                        method: 'POST',
                        data: {
                            _token: '{{ csrf_token() }}',
                            clientid: currentVisaClientId,
                            categoryid: categoryId,
                            files: fileData,
                            checklists: checklistNames
                        },
                        success: function(response) {
                            if (response.status) {
                                callback(response.matches || {});
                            } else {
                                callback({});
                            }
                        },
                        error: function() {
                            callback({});
                        }
                    });
                }
                
                // Display visa mapping interface (reuse the same modal)
                function displayVisaMappingInterface(files, checklists, matches) {
                    const modal = $('#bulk-upload-mapping-modal');
                    const tableContainer = $('#bulk-upload-mapping-table');
                    
                    let html = '<table class="table table-bordered" style="width: 100%;">';
                    html += '<thead><tr><th style="width: 25%;">File Name</th><th style="width: 45%;">Checklist Assignment</th><th style="width: 20%;">Status</th><th style="width: 10%; text-align: center;">Actions</th></tr></thead>';
                    html += '<tbody>';
                    
                    Array.from(files).forEach((file, index) => {
                        const fileName = file.name;
                        const fileSize = formatFileSize(file.size);
                        const match = matches[fileName] || null;
                        
                        let selectedChecklist = '';
                        let statusClass = 'manual';
                        let statusText = 'Manual selection';
                        
                        if (match && match.checklist) {
                            selectedChecklist = match.checklist;
                            statusClass = match.confidence === 'high' ? 'auto-matched' : 'manual';
                            statusText = match.confidence === 'high' ? 'Auto-matched' : 'Suggested';
                        }
                        
                        html += '<tr class="bulk-upload-file-item" data-file-index="' + index + '" data-file-name="' + escapeHtml(fileName) + '">';
                        html += '<td>';
                        html += '<div class="file-info">';
                        html += crmI('fas fa-file');
                        html += '<div>';
                        html += '<div class="file-name">' + escapeHtml(fileName) + '</div>';
                        html += '<div class="file-size">' + fileSize + '</div>';
                        html += '</div>';
                        html += '</div>';
                        html += '</td>';
                        html += '<td>';
                        html += '<select class="form-control checklist-select" data-file-index="' + index + '" data-file-name="' + escapeHtml(fileName) + '">';
                        html += '<option value="">-- Select Checklist --</option>';
                        html += '<option value="__NEW__">+ Create New Checklist</option>';
                        checklists.forEach(checklist => {
                            const selected = selectedChecklist === checklist.name ? 'selected' : '';
                            html += '<option value="' + escapeHtml(checklist.name) + '" ' + selected + '>' + escapeHtml(checklist.name) + '</option>';
                        });
                        html += '</select>';
                        html += '<input type="text" class="form-control mt-2 new-checklist-input" data-file-index="' + index + '" placeholder="Enter new checklist name" style="display: none;">';
                        html += '</td>';
                        html += '<td>';
                        html += '<span class="match-status ' + statusClass + '">' + statusText + '</span>';
                        html += '</td>';
                        html += '<td style="text-align: center;">';
                        html += '<button type="button" class="btn btn-sm btn-danger remove-bulk-file" data-file-index="' + index + '" title="Remove file">';
                        html += crmI('far fa-trash-alt');
                        html += '</button>';
                        html += '</td>';
                        html += '</tr>';
                    });
                    
                    html += '</tbody></table>';
                    tableContainer.html(html);
                    
                    // Handle new checklist option
                    $(document).off('change', '.checklist-select').on('change', '.checklist-select', function() {
                        const fileIndex = $(this).data('file-index');
                        const value = $(this).val();
                        const newInput = $('.new-checklist-input[data-file-index="' + fileIndex + '"]');
                        
                        if (value === '__NEW__') {
                            newInput.show();
                            newInput.attr('required', true);
                            $(this).closest('tr').find('.match-status').removeClass('auto-matched manual').addClass('new-checklist').text('New checklist');
                        } else {
                            newInput.hide();
                            newInput.removeAttr('required');
                            if (value) {
                                $(this).closest('tr').find('.match-status').removeClass('new-checklist').addClass('manual').text('Manual selection');
                            }
                        }
                    });
                    
                    // Handle remove file button for visa documents
                    $(document).off('click', '.remove-bulk-file').on('click', '.remove-bulk-file', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        
                        const $row = $(this).closest('tr');
                        const fileName = $row.data('file-name');
                        const categoryId = currentVisaCategoryId;
                        
                        // Confirm before removing
                        if (!confirm('Are you sure you want to remove "' + fileName + '" from the upload list?')) {
                            return;
                        }
                        
                        // Find and remove the file from the array by matching file name
                        const fileArray = bulkUploadVisaFiles[categoryId];
                        const fileIndex = fileArray.findIndex(f => f.name === fileName);
                        
                        if (fileIndex > -1) {
                            fileArray.splice(fileIndex, 1);
                        }
                        
                        // Remove the row
                        $row.remove();
                        
                        // Update file count
                        const remainingCount = fileArray.length;
                        const container = $('#bulk-upload-visa-' + categoryId);
                        container.find('.file-count-visa').text(remainingCount);
                        
                        // If no files left, hide the file list and modal
                        if (remainingCount === 0) {
                            $('#bulk-upload-mapping-modal').hide();
                            container.find('.bulk-upload-file-list-visa').hide();
                            container.find('.bulk-upload-files-container-visa').empty();
                            alert('All files have been removed. Please select files again to upload.');
                        } else {
                            // Reindex remaining rows to maintain correct file indices
                            $('#bulk-upload-mapping-table tbody tr').each(function(newIndex) {
                                $(this).attr('data-file-index', newIndex);
                                $(this).find('.checklist-select').attr('data-file-index', newIndex);
                                $(this).find('.new-checklist-input').attr('data-file-index', newIndex);
                                $(this).find('.remove-bulk-file').attr('data-file-index', newIndex);
                            });
                        }
                    });
                    
                    // Update the confirm button to handle visa upload
                    $('#confirm-bulk-upload').off('click').on('click', function() {
                        confirmVisaBulkUpload();
                    });
                    
                    modal.show();
                }
                
                // Confirm visa bulk upload
                function confirmVisaBulkUpload() {
                    const categoryId = currentVisaCategoryId;
                    const matterId = currentVisaMatterId;
                    const files = bulkUploadVisaFiles[categoryId];
                    const mappings = [];
                    const autoCreate = $('#auto-create-unmatched').is(':checked');
                    
                    // Collect mappings in order of files
                    Array.from(files).forEach((file, fileIndex) => {
                        const fileName = file.name;
                        const selectElement = $('.checklist-select[data-file-index="' + fileIndex + '"]');
                        
                        if (selectElement.length === 0) {
                            mappings.push(null);
                            return;
                        }
                        
                        const checklist = selectElement.val();
                        
                        let mapping = null;
                        
                        if (checklist === '__NEW__') {
                            const newChecklistName = selectElement.closest('tr').find('.new-checklist-input').val();
                            if (newChecklistName) {
                                mapping = {
                                    type: 'new',
                                    name: newChecklistName.trim()
                                };
                            } else if (autoCreate) {
                                mapping = {
                                    type: 'new',
                                    name: extractChecklistNameFromFile(fileName)
                                };
                            }
                        } else if (checklist) {
                            mapping = {
                                type: 'existing',
                                name: checklist
                            };
                        } else if (autoCreate) {
                            mapping = {
                                type: 'new',
                                name: extractChecklistNameFromFile(fileName)
                            };
                        }
                        
                        if (!mapping) {
                            const matchStatus = selectElement.closest('tr').find('.match-status');
                            if (matchStatus.hasClass('auto-matched') || matchStatus.hasClass('manual')) {
                                const selectedOption = selectElement.find('option:selected');
                                if (selectedOption.val() && selectedOption.val() !== '__NEW__') {
                                    mapping = {
                                        type: 'existing',
                                        name: selectedOption.val()
                                    };
                                }
                            }
                        }
                        
                        mappings.push(mapping);
                    });
                    
                    // Validate all files have mappings
                    const unmappedFiles = [];
                    mappings.forEach((mapping, index) => {
                        if (!mapping || !mapping.name) {
                            unmappedFiles.push(files[index].name);
                        }
                    });
                    
                    if (unmappedFiles.length > 0 && !autoCreate) {
                        alert('Please map all files to checklists or enable "Auto-create checklist for unmatched files"');
                        return;
                    }
                    
                    // Fill in any missing mappings with auto-create
                    mappings.forEach((mapping, index) => {
                        if (!mapping || !mapping.name) {
                            mappings[index] = {
                                type: 'new',
                                name: extractChecklistNameFromFile(files[index].name)
                            };
                        }
                    });
                    
                    // Upload files
                    uploadBulkVisaFiles(categoryId, matterId, files, mappings);
                }
                
                // Upload bulk visa files
                function uploadBulkVisaFiles(categoryId, matterId, files, mappings) {
                    const formData = new FormData();
                    formData.append('_token', '{{ csrf_token() }}');
                    formData.append('clientid', currentVisaClientId);
                    formData.append('categoryid', categoryId);
                    formData.append('matterid', matterId || '');
                    formData.append('doctype', 'visa');
                    formData.append('type', 'client');
                    
                    // Add files (sanitize multipart filename for WAF — same as single upload)
                    Array.from(files).forEach((file, index) => {
                        if (typeof mmAppendBulkDocumentUploadFile === 'function') {
                            mmAppendBulkDocumentUploadFile(formData, file);
                        } else {
                            const safeName = (typeof mmSanitizeDocumentUploadFilename === 'function')
                                ? mmSanitizeDocumentUploadFilename(file.name)
                                : String(file.name).replace(/[^a-zA-Z0-9\-_.]/g, '_');
                            formData.append('files[]', file, safeName);
                        }
                        const mapping = mappings[index] || { type: 'new', name: extractChecklistNameFromFile(file.name) };
                        formData.append('mappings[]', JSON.stringify(mapping));
                    });
                    
                    // Show progress
                    $('#bulk-upload-progress').show();
                    $('#bulk-upload-progress-bar').css('width', '0%').text('0%');
                    $('#confirm-bulk-upload').prop('disabled', true);
                    
                    $.ajax({
                        url: '{{ route("clients.documents.bulkUploadVisaDocuments") }}',
                        method: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        xhr: function() {
                            const xhr = new window.XMLHttpRequest();
                            xhr.upload.addEventListener('progress', function(e) {
                                if (e.lengthComputable) {
                                    const percentComplete = (e.loaded / e.total) * 100;
                                    $('#bulk-upload-progress-bar').css('width', percentComplete + '%').text(Math.round(percentComplete) + '%');
                                }
                            }, false);
                            return xhr;
                        },
                        success: function(response) {
                            if (response.status) {
                                let message = response.message || 'Files uploaded successfully!';
                                if (response.errors && response.errors.length > 0) {
                                    message += '\n\nWarnings:\n' + response.errors.join('\n');
                                }
                                alert(message);
                                location.reload();
                            } else {
                                let errorMsg = 'Error: ' + (response.message || 'Upload failed');
                                if (response.errors && response.errors.length > 0) {
                                    errorMsg += '\n\nDetails:\n' + response.errors.join('\n');
                                }
                                alert(errorMsg);
                                $('#bulk-upload-progress').hide();
                                $('#confirm-bulk-upload').prop('disabled', false);
                            }
                        },
                        error: function(xhr) {
                            let errorMsg = 'Upload failed';
                            if (xhr.responseJSON && xhr.responseJSON.message) {
                                errorMsg = xhr.responseJSON.message;
                            }
                            alert('Error: ' + errorMsg);
                            $('#bulk-upload-progress').hide();
                            $('#confirm-bulk-upload').prop('disabled', false);
                        }
                    });
                }
            </script>

            <style>
                .context-menu-item:hover {
                    background-color: #f8f9fa;
                }

                /* Bulk Upload Dropzone Styles for Visa */
                .bulk-upload-dropzone-visa {
                    position: relative;
                }
                
                /* Make all child elements transparent to pointer events so drag events reach the dropzone */
                .bulk-upload-dropzone-visa * {
                    pointer-events: none;
                }
                
                .bulk-upload-dropzone-visa.drag_over {
                    border-color: #28a745;
                    background-color: #e8f5e9;
                }

                /* Drag and Drop Zone Styles */
                .document-drag-drop-zone {
                    border: 2px dashed #ccc;
                    border-radius: 4px;
                    padding: 15px 20px;
                    text-align: center;
                    background-color: #f9f9f9;
                    cursor: pointer !important;
                    transition: all 0.3s ease;
                    min-height: 60px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    margin: 5px 0;
                    position: relative;
                    z-index: 1;
                }
                
                /* Make all child elements transparent to pointer events so drag events reach the dropzone */
                .document-drag-drop-zone * {
                    pointer-events: none;
                }

                .document-drag-drop-zone:hover {
                    border-color: #007bff;
                    background-color: #f0f8ff;
                }

                .document-drag-drop-zone.drag_over {
                    border-color: #28a745;
                    background-color: #e8f5e9;
                    border-width: 3px;
                }

                .drag-zone-inner {
                    display: flex;
                    align-items: center;
                    gap: 10px;
                    color: #374151;
                }

                .drag-zone-inner i {
                    font-size: 20px;
                    color: #2563eb;
                }

                .drag-zone-text {
                    font-size: 14px;
                    color: inherit;
                }

                .document-drag-drop-zone.uploading {
                    pointer-events: none;
                    opacity: 0.6;
                }

                .document-drag-drop-zone.uploading .drag-zone-text::after {
                    content: ' Uploading...';
                    font-weight: bold;
                    color: #007bff;
                }

                /* Bulk Upload File List Styles */
                #bulk-upload-mapping-table table tbody tr {
                    border-bottom: 1px solid #dee2e6;
                }

                #bulk-upload-mapping-table table tbody tr td {
                    padding: 15px 10px !important;
                }

                .bulk-upload-file-item {
                    vertical-align: top;
                }

                .bulk-upload-file-item td {
                    padding: 12px 8px !important;
                    vertical-align: top !important;
                }

                .bulk-upload-file-item .file-info {
                    display: flex;
                    align-items: flex-start;
                    gap: 10px;
                    min-height: 40px;
                }

                .bulk-upload-file-item .file-info > div {
                    flex: 1;
                    display: flex;
                    flex-direction: column;
                    gap: 4px;
                }

                .bulk-upload-file-item .file-name {
                    font-weight: 500;
                    color: #333;
                    word-break: break-word;
                    overflow-wrap: break-word;
                    white-space: normal;
                    line-height: 1.4;
                    display: block;
                }

                .bulk-upload-file-item .file-size {
                    font-size: 12px;
                    color: #4b5563;
                }

                .bulk-upload-file-item .checklist-select {
                    min-width: 200px;
                }

                .bulk-upload-file-item .match-status {
                    font-size: 12px;
                    padding: 2px 8px;
                    border-radius: 3px;
                }

                .match-status.auto-matched {
                    background-color: #d4edda;
                    color: #155724;
                }

                .match-status.manual {
                    background-color: #fff3cd;
                    color: #856404;
                }

                .match-status.new-checklist {
                    background-color: #cce5ff;
                    color: #004085;
                }

                .remove-bulk-file {
                    padding: 4px 8px;
                    font-size: 14px;
                    transition: all 0.2s ease;
                }

                .remove-bulk-file:hover {
                    background-color: #c82333;
                    border-color: #bd2130;
                    transform: scale(1.1);
                }

                .remove-bulk-file i {
                    pointer-events: none;
                }
            </style>

