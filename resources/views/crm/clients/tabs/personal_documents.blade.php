           <!-- Personal Documents Tab (Client-Level) -->
           <div class="tab-pane" id="personaldocuments-tab">
                <div class="card full-width documentalls-container">
                    <?php
                    $clientId = $fetchedData->id ?? null;
                    $companyDocumentsOnlyGeneral = !empty($companyDocumentsOnlyGeneral);
                    $canDeletePersonalDocCategory = \Illuminate\Support\Facades\Auth::check()
                        && in_array((int) (\Illuminate\Support\Facades\Auth::user()->role ?? 0), config('crm.personal_document_category_delete_role_ids', [1, 16]), true);
                    $personalDocTypeScope = $companyDocumentsOnlyGeneral
                        ? ['company', 'both']
                        : ['personal', 'both'];
                    $persDocCatList = \App\Models\PersonalDocumentType::select('id', 'title', 'client_id', 'type')
                        ->where('status', 1)
                        ->where(function($query) use ($clientId) {
                            $query->whereNull('client_id')
                                ->orWhere('client_id', $clientId);
                        })
                        ->whereIn('type', $personalDocTypeScope)
                        ->orderBy('id', 'ASC')
                        ->get();
                    if ($companyDocumentsOnlyGeneral) {
                        $companyDocTitlesOrder = ['General', 'Financial'];
                        $companyDocOrdered = collect();
                        foreach ($companyDocTitlesOrder as $title) {
                            $match = $persDocCatList->first(function ($cat) use ($title) {
                                return strcasecmp(trim($cat->title ?? ''), $title) === 0;
                            });
                            if ($match) {
                                $companyDocOrdered->push($match);
                            }
                        }
                        if ($companyDocOrdered->isEmpty()) {
                            $fallback = $persDocCatList->firstWhere('id', 1) ?? $persDocCatList->first();
                            $persDocCatList = $fallback ? collect([$fallback]) : collect();
                        } else {
                            $persDocCatList = $companyDocOrdered->values();
                        }
                    }
                    $firstPersDocCatId = optional($persDocCatList->first())->id;
                    ?>

                    <!-- Personal Documents Content -->
                    <div class="personal-documents-content" id="personal-documents-content">
                        <!-- Document Type Subtabs Container -->
                        <div class="subtab-header-container" style="background-color: #4a90e2; padding: 10px; border-radius: 8px 8px 0 0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                            <nav class="subtabs2" style="display: flex; gap: 5px; flex-wrap: wrap; flex: 1;">
                                <?php foreach ($persDocCatList as $catVal): ?>
                                    <?php
                                    $id = $catVal->id;
                                    $isActive = $companyDocumentsOnlyGeneral
                                        ? ($id == $firstPersDocCatId ? 'active' : '')
                                        : ($id == 1 ? 'active' : '');
                                    $isClientGenerated = $catVal->client_id !== null;
                                    ?>
                                    <div style="display: inline-block; position: relative;" class="button-container">
                                        <button class="subtab2-button <?= $isActive ?>" data-subtab2="<?= $id ?>">
                                            <?= htmlspecialchars($catVal->title) ?>
                                        </button>
                                        <?php if ($isClientGenerated): ?>
                                            <div class="action-buttons" style="display: none; position: absolute; top: 0; right: -8px;">
                                                <button class="btn btn-sm btn-warning update-personal-cat-title" data-id="<?= $id ?>" style="padding: 2px 0px 2px 6px;">@icon('fa-edit')</button>
                                                <?php if ($canDeletePersonalDocCategory): ?>
                                                    <button class="btn btn-sm btn-danger delete-personal-cat-title" data-id="<?= $id ?>" data-title="<?= htmlspecialchars($catVal->title) ?>" style="padding: 2px 0px 2px 6px;">@icon('fa-trash')</button>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </nav>
                            <div style="display: flex; gap: 10px; align-items: center;">
                                @if (!$companyDocumentsOnlyGeneral)
                                <button type="button" class="btn add_personal_doc_cat-btn add_personal_doc_cat" data-type="personal" data-categoryid="">
                                    @icon('fa-plus') Add Category
                                </button>
                                @endif
                                <!-- Add link to Not Used Documents -->
                                <button type="button" class="btn btn-secondary client-nav-button client-nav-button--inline" data-tab="notuseddocuments">
                                    @icon('fa-folder-minus') Not Used Documents
                                </button>
                            </div>
                        </div>

                        <!-- Subtab2 Contents -->
                        <div class="subtab2-content">
                            <?php foreach ($persDocCatList as $catVal): ?>
                                <?php
                                $id = $catVal->id;
                                $isActive = $companyDocumentsOnlyGeneral
                                    ? ($id == $firstPersDocCatId ? 'active' : '')
                                    : ($id == 1 ? 'active' : '');
                                $folderName = $id;
                                ?>

                                <div class="subtab2-pane <?= $isActive ?>" id="<?= $id ?>-subtab2">
                                    <div class="checklist-table-container" style="vertical-align: top; margin-top: 10px; width: 760px;">
                                        <div class="subtab2-header" style="margin-left: 10px;">
                                            <h3>@icon('fa-file-alt') <?= htmlspecialchars($catVal->title) ?> Documents</h3>
                                            <div style="display: flex; gap: 10px;">
                                                <button type="button" class="btn add-checklist-btn add_education_doc" data-type="personal" data-categoryid="<?= $id ?>">
                                                    @icon('fa-plus') Add Checklist
                                                </button>
                                                <button type="button" class="btn btn-info bulk-upload-toggle-btn" data-categoryid="<?= $id ?>" data-categoryname="<?= htmlspecialchars($catVal->title) ?>">
                                                    @icon('fa-upload') Bulk Upload
                                                </button>
                                            </div>
                                        </div>
                                        
                                        <!-- Bulk Upload Dropzone (Hidden by default) -->
                                        <div class="bulk-upload-dropzone-container" id="bulk-upload-<?= $id ?>" style="display: none; margin: 15px 0; padding: 20px; border: 2px dashed #4a90e2; border-radius: 8px; background-color: #f8f9fa;">
                                            <div class="bulk-upload-dropzone" data-categoryid="<?= $id ?>" style="text-align: center; padding: 30px; cursor: pointer;">
                                                @icon('fa-cloud-upload-alt', ['style' => 'font-size: 48px; color: #2563eb; margin-bottom: 15px;'])
                                                <p style="font-size: 16px; color: #374151; margin-bottom: 10px;">
                                                    <strong>Drag and drop files here</strong> or <strong>click to browse</strong>
                                                </p>
                                                <p style="font-size: 14px; color: #4b5563;">You can select multiple files at once</p>
                                                <input type="file" class="bulk-upload-file-input" data-categoryid="<?= $id ?>" multiple style="display: none;" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                                            </div>
                                            <div class="bulk-upload-file-list" style="display: none; margin-top: 20px;">
                                                <h5 style="margin-bottom: 15px;">Files Selected: <span class="file-count">0</span></h5>
                                                <div class="bulk-upload-files-container"></div>
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
                                            <tbody class="tdata persdocumnetlist documnetlist_<?= $id ?>">
                                                <?php
                                                $documents = \App\Models\Document::with('staff')->where('client_id', $clientId)
                                                    ->whereNull('not_used_doc')
                                                    ->where('doc_type', 'personal')
                                                    ->where('folder_name', $folderName)
                                                    ->where('type', 'client')
                                                    ->orderBy('created_at', 'DESC')
                                                    ->get();
                                                ?>
                                                <?php foreach ($documents as $docKey => $fetch): ?>
                                                    <?php
                                                    $admin = $fetch->staff;
                                                    
                                                    // Ensure $fileUrl is always a valid full URL
                                                    if (!empty($fetch->myfile) && strpos($fetch->myfile, 'http') === 0) {
                                                        // Already a full URL
                                                        $fileUrl = $fetch->myfile;
                                                    } else {
                                                        // Legacy format or relative path - construct full URL
                                                        $fileUrl = 'https://' . env('AWS_BUCKET') . '.s3.' . env('AWS_DEFAULT_REGION') . '.amazonaws.com/' . $clientId . '/personal/' . $fetch->myfile;
                                                    }
                                                    ?>
                                                    <tr class="drow" id="id_<?= $fetch->id ?>">
                                                        <td style="white-space: initial;">
                                                            <div data-id="<?= $fetch->id ?>" data-personalchecklistname="<?= htmlspecialchars($fetch->checklist) ?>" class="personalchecklist-row" title="Uploaded by: <?= htmlspecialchars($admin->first_name ?? 'NA') ?> on <?= date('d/m/Y H:i', strtotime($fetch->created_at)) ?>" style="display: flex; align-items: center; gap: 8px;">
                                                                <span style="flex: 1;"><?= htmlspecialchars($fetch->checklist) ?></span>
                                                                <div class="checklist-actions" style="display: flex; gap: 5px;">
                                                                    <?php if (!$fetch->file_name): ?>
                                                                    <a href="javascript:;" class="edit-checklist-btn" data-id="<?= $fetch->id ?>" data-checklist="<?= htmlspecialchars($fetch->checklist) ?>" title="Edit Checklist Name" style="color: #007bff; cursor: pointer;">
                                                                        @icon('fa-edit')
                                                                    </a>
                                                                    <a href="javascript:;" class="delete-checklist-btn" data-id="<?= $fetch->id ?>" data-checklist="<?= htmlspecialchars($fetch->checklist) ?>" title="Delete Checklist" style="color: #dc3545; cursor: pointer;">
                                                                        @icon('fa-trash')
                                                                    </a>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td style="white-space: initial;">
                                                            <?php if ($fetch->file_name): ?>
                                                                <div data-id="<?= $fetch->id ?>" data-name="<?= htmlspecialchars($fetch->file_name) ?>" class="doc-row" title="Uploaded by: <?= htmlspecialchars($admin->first_name ?? 'NA') ?> on <?= date('d/m/Y H:i', strtotime($fetch->created_at)) ?>" oncontextmenu="showFileContextMenu(event, <?= $fetch->id ?>, '<?= htmlspecialchars($fetch->filetype) ?>', '<?= $fileUrl ?>', '<?= $id ?>', '<?= $fetch->status ?? 'draft' ?>'); return false;">
                                                                    <a href="javascript:void(0);" onclick="previewFile('<?= $fetch->filetype ?>','<?= $fileUrl ?>','preview-container-<?= $id ?>')">
                                                                        @icon('fa-file-image') <span><?= htmlspecialchars($fetch->file_name . '.' . $fetch->filetype) ?></span>
                                                                    </a>
                                                                </div>
                                                            <?php else: ?>
                                                                <div class="upload_document" style="display: inline-block;">
                                                                    <form method="POST" enctype="multipart/form-data" id="upload_form_<?= $fetch->id ?>">
                                                                        @csrf
                                                                        <input type="hidden" name="clientid" value="<?= $clientId ?>">
                                                                        <input type="hidden" name="fileid" value="<?= $fetch->id ?>">
                                                                        <input type="hidden" name="type" value="client">
                                                                        <input type="hidden" name="doctype" value="personal">
                                                                        <input type="hidden" name="doccategory" value="<?= $catVal->title ?>">

                                                                        <!-- Drag and Drop Zone -->
                                                                        <div class="document-drag-drop-zone personal-doc-drag-zone" 
                                                                             data-fileid="<?= $fetch->id ?>" 
                                                                             data-doccategory="<?= $id ?>"
                                                                             data-formid="upload_form_<?= $fetch->id ?>">
                                                                            <div class="drag-zone-inner">
                                                                                @icon('fa-cloud-upload-alt')
                                                                                <span class="drag-zone-text">Drag file here or <strong>click to browse</strong></span>
                                                                            </div>
                                                                        </div>
                                                                        
                                                                        <!-- Keep existing file input (hidden, used as fallback) -->
                                                                        <input class="docupload d-none" 
                                                                               data-fileid="<?= $fetch->id ?>" 
                                                                               data-doccategory="<?= $id ?>" 
                                                                               type="file" 
                                                                               name="document_upload" 
                                                                               style="display: none;"/>
                                                                    </form>
                                                                </div>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <!-- Hidden elements for context menu actions -->
                                                            <?php if ($fetch->myfile): ?>
                                                                <a class="renamechecklist" data-id="<?= $fetch->id ?>" href="javascript:;" style="display: none;"></a>
                                                                <a class="renamedoc" data-id="<?= $fetch->id ?>" href="javascript:;" style="display: none;"></a>
                                                                <a class="download-file" data-filelink="<?= $fileUrl ?>" data-filename="<?= $fetch->myfile_key ?: basename($fetch->myfile) ?>" data-id="<?= $fetch->id ?>" href="#" style="display: none;"></a>
                                                                <a class="notuseddoc" data-id="<?= $fetch->id ?>" data-doctype="personal" data-doccategory="<?= $catVal->title ?>" data-href="documents/not-used" href="javascript:;" style="display: none;"></a>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>

                                    <div class="grid_data griddata_<?= $id ?>">
                                        <?php foreach ($documents as $fetch): ?>
                                            <?php if ($fetch->myfile): ?>
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
                                                                    <a data-id="<?= $fetch->id ?>" class="dropdown-item notuseddoc" data-doctype="personal" data-doccategory="<?= $catVal->title ?>" data-href="notuseddoc" href="javascript:;">Not Used</a>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                        <div class="clearfix"></div>
                                    </div>

                                    <div class="preview-pane file-preview-container preview-container-<?= $id ?>" style="display: inline; margin-top: 15px !important; width: 499px;">
                                        <p style="color: #374151;">Click on a file to preview it here.</p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Custom Context Menu -->
            <div id="fileContextMenu" class="context-menu" style="display: none; position: fixed; background: white; border: 1px solid #ccc; border-radius: 4px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); z-index: 10000; min-width: 180px;">
                <div class="context-menu-item" onclick="handleContextAction('rename-checklist')" style="padding: 8px 12px; cursor: pointer; border-bottom: 1px solid #eee;">
                    @icon('fa-edit', ['style' => 'margin-right: 8px;']) Rename Checklist
                </div>
                <div class="context-menu-item" onclick="handleContextAction('rename-doc')" style="padding: 8px 12px; cursor: pointer; border-bottom: 1px solid #eee;">
                    @icon('fa-file-text', ['style' => 'margin-right: 8px;']) Rename File Name
                </div>
                <div class="context-menu-item" onclick="handleContextAction('move')" style="padding: 8px 12px; cursor: pointer; border-bottom: 1px solid #eee;">
                    @icon('fa-arrows-alt', ['style' => 'margin-right: 8px;']) Move Document
                </div>
                <div class="context-menu-item" onclick="handleContextAction('preview')" style="padding: 8px 12px; cursor: pointer; border-bottom: 1px solid #eee;">
                    @icon('fa-eye', ['style' => 'margin-right: 8px;']) Preview
                </div>
                <div id="context-pdf-option" class="context-menu-item" onclick="handleContextAction('pdf')" style="padding: 8px 12px; cursor: pointer; border-bottom: 1px solid #eee; display: none;">
                    @icon('fa-file-pdf', ['style' => 'margin-right: 8px;']) PDF
                </div>
                <div class="context-menu-item" onclick="handleContextAction('download')" style="padding: 8px 12px; cursor: pointer; border-bottom: 1px solid #eee;">
                    @icon('fa-download', ['style' => 'margin-right: 8px;']) Download
                </div>
                <div class="context-menu-item" onclick="handleContextAction('not-used')" style="padding: 8px 12px; cursor: pointer; border-bottom: 1px solid #eee;">
                    @icon('fa-trash', ['style' => 'margin-right: 8px;']) Not Used
                </div>
            </div>

            <!-- Move Document Modal -->
            <div class="modal fade" id="moveDocumentModal" tabindex="-1" role="dialog">
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
                                <select id="moveTargetType" class="form-control" style="margin-bottom: 15px;">
                                    <option value="">-- Select Destination --</option>
                                    <option value="personal">Personal Documents</option>
                                    <option value="visa">Visa Documents</option>
                                </select>
                            </div>
                            
                            <!-- For Personal Documents: Show Categories -->
                            <div class="form-group" id="movePersonalCategoryContainer" style="display: none;">
                                <label>Select Personal Category:</label>
                                <select id="movePersonalCategoryId" class="form-control">
                                    <option value="">-- Select Category --</option>
                                </select>
                            </div>
                            
                            <!-- For Visa Documents: Show Matters first, then Categories -->
                            <div class="form-group" id="moveVisaMatterContainer" style="display: none;">
                                <label>Select Visa Matter:</label>
                                <select id="moveVisaMatterId" class="form-control" style="margin-bottom: 15px;">
                                    <option value="">-- Select Matter --</option>
                                </select>
                            </div>
                            
                            <div class="form-group" id="moveVisaCategoryContainer" style="display: none;">
                                <label>Select Visa Category:</label>
                                <select id="moveVisaCategoryId" class="form-control">
                                    <option value="">-- Select Category --</option>
                                </select>
                            </div>
                            
                            <div id="moveDocumentError" class="alert alert-danger" style="display: none; margin-top: 10px;"></div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-primary" id="confirmMoveDocument">Move Document</button>
                        </div>
                    </div>
                </div>
            </div>

            <script>
                // ============================================================================
                // PERSONAL DOCUMENTS - DRAG AND DROP INITIALIZATION (HYBRID APPROACH)
                // ============================================================================
                // This uses a DUAL-LAYER strategy to ensure handlers work:
                // 1. DIRECT handlers on existing elements (fire FIRST, highest priority)
                // 2. DELEGATED handlers for dynamic elements (fallback)
                // Both use stopImmediatePropagation() to prevent detail-main.js handlers
                // from interfering, while keeping them as a safety fallback.
                // ============================================================================
                console.log('🚀 Personal Documents Tab Script Loading...');
                
                function initPersonalDocDragDrop() {
                    console.log('🔄 Initializing Personal Doc Drag & Drop...');
                    console.log('📊 Drop zones found:', $('.personal-doc-drag-zone').length);
                    console.log('📊 Visible drop zones:', $('.personal-doc-drag-zone:visible').length);
                    
                    // Check each drop zone
                    $('.personal-doc-drag-zone').each(function(index) {
                        var $zone = $(this);
                        var fileid = $zone.data('fileid');
                        var formid = $zone.data('formid');
                        var isVisible = $zone.is(':visible');
                        console.log('🔍 Drop zone #' + index + ':', {
                            fileid: fileid,
                            formid: formid,
                            visible: isVisible,
                            hasFileInput: $('#' + formid).find('.docupload').length > 0
                        });
                    });
                    
                    // Remove only our own handlers to prevent duplicates
                    $(document).off('dragenter.personaldoclocal', '.personal-doc-drag-zone');
                    $(document).off('dragover.personaldoclocal', '.personal-doc-drag-zone');
                    $(document).off('dragleave.personaldoclocal', '.personal-doc-drag-zone');
                    $(document).off('drop.personaldoclocal', '.personal-doc-drag-zone');
                    $(document).off('click.personaldoclocal', '.personal-doc-drag-zone');
                    
                    // ALSO attach direct handlers to existing elements for IMMEDIATE priority
                    // These will fire BEFORE delegated handlers
                    $('.personal-doc-drag-zone').each(function() {
                        var $zone = $(this);
                        
                        // Remove any existing direct handlers first
                        $zone.off('click.personaldocdirect');
                        $zone.off('dragenter.personaldocdirect');
                        $zone.off('dragover.personaldocdirect');
                        $zone.off('dragleave.personaldocdirect');
                        $zone.off('drop.personaldocdirect');
                        
                        // Attach direct handlers with stopImmediatePropagation
                        $zone.on('click.personaldocdirect', function(e) {
                            console.log('🎯 DIRECT CLICK (Highest Priority)');
                            e.preventDefault();
                            e.stopPropagation();
                            e.stopImmediatePropagation();
                            
                            var fileid = $(this).data('fileid');
                            var formid = $(this).data('formid');
                            var fileInput = $('#' + formid).find('.docupload');
                            
                            if (fileInput.length > 0) {
                                fileInput[0].click();
                            }
                            return false;
                        });
                        
                        $zone.on('dragenter.personaldocdirect', function(e) {
                            console.log('🔥 DIRECT DRAGENTER (Highest Priority)');
                            e.preventDefault();
                            e.stopPropagation();
                            e.stopImmediatePropagation();
                            $(this).addClass('drag_over');
                            return false;
                        });
                        
                        $zone.on('dragover.personaldocdirect', function(e) {
                            console.log('🔥 DIRECT DRAGOVER (Highest Priority)');
                            e.preventDefault();
                            e.stopPropagation();
                            e.stopImmediatePropagation();
                            
                            if (e.originalEvent && e.originalEvent.dataTransfer) {
                                e.originalEvent.dataTransfer.dropEffect = 'copy';
                            }
                            
                            $(this).addClass('drag_over');
                            return false;
                        });
                        
                        $zone.on('dragleave.personaldocdirect', function(e) {
                            console.log('⚠️ DIRECT DRAGLEAVE (Highest Priority)');
                            e.preventDefault();
                            e.stopPropagation();
                            e.stopImmediatePropagation();
                            
                            var rect = this.getBoundingClientRect();
                            var x = e.originalEvent.clientX;
                            var y = e.originalEvent.clientY;
                            
                            if (x <= rect.left || x >= rect.right || y <= rect.top || y >= rect.bottom) {
                                $(this).removeClass('drag_over');
                            }
                            return false;
                        });
                        
                        $zone.on('drop.personaldocdirect', function(e) {
                            console.log('🎯 DIRECT DROP (Highest Priority)');
                            e.preventDefault();
                            e.stopPropagation();
                            e.stopImmediatePropagation();
                            
                            $(this).removeClass('drag_over');
                            
                            var files = e.originalEvent && e.originalEvent.dataTransfer ? e.originalEvent.dataTransfer.files : null;
                            if (files && files.length > 0) {
                                var $zone = $(this);
                                var file = files[0];
                                var fileid = $zone.data('fileid');
                                var doccategory = $zone.data('doccategory');
                                var formId = $zone.data('formid');
                                var form = $('#' + formId);
                                
                                if (!form.length) {
                                    console.error('❌ Form not found:', formId);
                                    alert('Error: Upload form not found. Please refresh the page.');
                                    return false;
                                }
                                
                                // Validate filename
                                if (typeof mmIsAllowedDocumentFilename === 'function' ? !mmIsAllowedDocumentFilename(file.name) : !/^[a-zA-Z0-9_\-\.\s\$\(\),&+']+$/.test(file.name)) {
                                    alert(typeof mmDocumentFilenameValidationMessage === 'function' ? mmDocumentFilenameValidationMessage() : "File name can only contain letters, numbers, dashes (-), underscores (_), spaces, dots (.), dollar signs ($), parentheses (( )), commas (,), ampersands (&), apostrophes ('), and plus signs (+). Please rename the file and try again.");
                                    return false;
                                }
                                
                                // Create FormData and upload
                                var formData = new FormData(form[0]);
                                if (typeof mmSetDocumentUploadFile === 'function') {
                                    mmSetDocumentUploadFile(formData, file);
                                } else {
                                    formData.set('document_upload', file);
                                }
                                
                                $zone.addClass('uploading');
                                $('.custom-error-msg').html('<span class="alert alert-info">' + crmI('fa fa-clock-o') + ' Uploading document...</span>');
                                
                                $.ajax({
                                    url: '{{ url("/documents/upload-edu-document") }}',
                                    type: 'POST',
                                    dataType: 'json',
                                    data: formData,
                                    contentType: false,
                                    processData: false,
                                    success: function(ress) {
                                        $zone.removeClass('uploading');
                                        
                                        if (ress.status) {
                                            $('.custom-error-msg').html('<span class="alert alert-success">' + ress.message + '</span>');
                                            
                                            var row = $('#id_' + fileid);
                                            var docNameWithoutExt = ress.filename.replace(/\.[^/.]+$/, "").replace(/\s+/g, "_").toLowerCase();
                                            
                                            var uploadTd = row.find('td').eq(1);
                                            uploadTd.html(
                                                '<div data-id="' + fileid + '" data-name="' + docNameWithoutExt + '" class="doc-row" title="Uploaded by: Admin" oncontextmenu="showFileContextMenu(event, ' + fileid + ', \'' + ress.filetype + '\', \'' + ress.fileurl + '\', \'' + doccategory + '\', \'' + (ress.status_value || 'draft') + '\'); return false;">' +
                                                    '<a href="javascript:void(0);" onclick="previewFile(\'' + ress.filetype + '\', \'' + ress.fileurl + '\', \'preview-container-' + doccategory + '\')">' +
                                                        crmI('fas fa-file-image') + ' <span>' + ress.filename + '</span>' +
                                                    '</a>' +
                                                '</div>'
                                            );
                                            
                                            var actionTd = row.find('td').eq(2);
                                            actionTd.html(
                                                '<a class="renamechecklist" data-id="' + fileid + '" href="javascript:;" style="display: none;"></a>' +
                                                '<a class="renamedoc" data-id="' + fileid + '" href="javascript:;" style="display: none;"></a>' +
                                                '<a class="download-file" data-filelink="' + ress.fileurl + '" data-filename="' + ress.filekey + '" href="#" style="display: none;"></a>' +
                                                '<a class="notuseddoc" data-id="' + fileid + '" data-doctype="' + ress.doctype + '" data-href="notuseddoc" href="javascript:;" style="display: none;"></a>'
                                            );
                                            
                                            row.addClass('drow');
                                            
                                            if (typeof getallactivities === 'function') {
                                                getallactivities();
                                            }
                                            
                                            setTimeout(function() {
                                                location.reload();
                                            }, 1000);
                                        } else {
                                            $('.custom-error-msg').html('<span class="alert alert-danger">' + ress.message + '</span>');
                                        }
                                    },
                                    error: function(xhr, status, error) {
                                        $zone.removeClass('uploading');
                                        $('.custom-error-msg').html('<span class="alert alert-danger">Upload failed. Please try again.</span>');
                                        console.error('Personal doc upload error:', error);
                                    }
                                });
                            }
                            return false;
                        });
                    });
                    
                    // Use DELEGATED event handlers with HIGH PRIORITY (these work for dynamically loaded content)
                    // The .personaldoclocal namespace ensures we can remove/re-attach without affecting detail-main.js
                    
                    // Click handler - for browse functionality
                    $(document).on('click.personaldoclocal', '.personal-doc-drag-zone', function(e) {
                        console.log('🎯 BLADE CLICK HANDLER - Personal Doc (Priority)');
                        e.preventDefault();
                        e.stopPropagation();
                        e.stopImmediatePropagation(); // Stop detail-main.js handler

                        var fileid = $(this).data('fileid');
                        var formid = $(this).data('formid');
                        console.log('📂 File ID:', fileid, 'Form ID:', formid);

                        var $form = $('#' + formid);
                        if (!$form.length) {
                            console.error('❌ Form not found:', formid);
                            alert('Error: Upload form not found. Please refresh the page.');
                            return false;
                        }

                        var fileInput = $form.find('.docupload');
                        console.log('📁 File input found:', fileInput.length > 0);

                        if (fileInput.length > 0) {
                            console.log('✅ Triggering file input click...');
                            // Use native click to ensure it works
                            var nativeInput = fileInput[0];
                            if (nativeInput && typeof nativeInput.click === 'function') {
                                nativeInput.click();
                            } else {
                                console.error('❌ File input element not accessible');
                                alert('Error: File input not accessible. Please refresh the page.');
                            }
                        } else {
                            console.error('❌ File input not found for formid:', formid);
                            alert('Error: File input not found. Please refresh the page.');
                        }

                        return false;
                    });
                    
                    // Dragenter - initial entry into zone
                    $(document).on('dragenter.personaldoclocal', '.personal-doc-drag-zone', function(e) {
                        console.log('🔥 BLADE DRAGENTER HANDLER - Personal Doc (Priority)');
                        e.preventDefault();
                        e.stopPropagation();
                        e.stopImmediatePropagation(); // Stop detail-main.js handler
                        $(this).addClass('drag_over');
                        return false;
                    });
                    
                    // Dragover - continuous while dragging over zone (REQUIRED for drop to work!)
                    $(document).on('dragover.personaldoclocal', '.personal-doc-drag-zone', function(e) {
                        console.log('🔥 BLADE DRAGOVER HANDLER - Personal Doc (Priority)');
                        e.preventDefault();
                        e.stopPropagation();
                        e.stopImmediatePropagation(); // Stop detail-main.js handler
                        
                        // Set dropEffect to indicate this is a valid drop zone
                        if (e.originalEvent && e.originalEvent.dataTransfer) {
                            e.originalEvent.dataTransfer.dropEffect = 'copy';
                        }
                        
                        $(this).addClass('drag_over');
                        return false;
                    });
                    
                    // Dragleave - when dragging out of zone
                    $(document).on('dragleave.personaldoclocal', '.personal-doc-drag-zone', function(e) {
                        console.log('⚠️ BLADE DRAGLEAVE HANDLER - Personal Doc (Priority)');
                        e.preventDefault();
                        e.stopPropagation();
                        e.stopImmediatePropagation(); // Stop detail-main.js handler
                        
                        // Only remove highlight if actually leaving the zone
                        var rect = this.getBoundingClientRect();
                        var x = e.originalEvent.clientX;
                        var y = e.originalEvent.clientY;
                        
                        if (x <= rect.left || x >= rect.right || y <= rect.top || y >= rect.bottom) {
                            $(this).removeClass('drag_over');
                        }
                        return false;
                    });
                    
                    // Drop - when file is dropped
                    $(document).on('drop.personaldoclocal', '.personal-doc-drag-zone', function(e) {
                        console.log('🎯 BLADE DROP HANDLER - Personal Doc (Priority)');
                        e.preventDefault();
                        e.stopPropagation();
                        e.stopImmediatePropagation(); // Stop detail-main.js handler from firing
                        
                        $(this).removeClass('drag_over');
                        
                        var files = e.originalEvent && e.originalEvent.dataTransfer ? e.originalEvent.dataTransfer.files : null;
                        if (files && files.length > 0) {
                            console.log('📄 File dropped:', files[0].name);
                            
                            var $zone = $(this);
                            var file = files[0];
                            var fileid = $zone.data('fileid');
                            var doccategory = $zone.data('doccategory');
                            var formId = $zone.data('formid');
                            var form = $('#' + formId);
                            
                            console.log('📂 File ID:', fileid, 'Category:', doccategory, 'Form ID:', formId);
                            
                            if (!form.length) {
                                console.error('❌ Form not found:', formId);
                                alert('Error: Upload form not found. Please refresh the page.');
                                return false;
                            }
                            
                            // Validate filename
                            if (typeof mmIsAllowedDocumentFilename === 'function' ? !mmIsAllowedDocumentFilename(file.name) : !/^[a-zA-Z0-9_\-\.\s\$\(\),&+']+$/.test(file.name)) {
                                alert(typeof mmDocumentFilenameValidationMessage === 'function' ? mmDocumentFilenameValidationMessage() : "File name can only contain letters, numbers, dashes (-), underscores (_), spaces, dots (.), dollar signs ($), parentheses (( )), commas (,), ampersands (&), apostrophes ('), and plus signs (+). Please rename the file and try again.");
                                return false;
                            }
                            
                            // Create FormData with all form fields
                            var formData = new FormData(form[0]);
                            
                            // Override the file input with dragged file (WAF-safe multipart name)
                            if (typeof mmSetDocumentUploadFile === 'function') {
                                mmSetDocumentUploadFile(formData, file);
                            } else {
                                formData.set('document_upload', file);
                            }
                            
                            // Visual feedback
                            $zone.addClass('uploading');
                            $('.custom-error-msg').html('<span class="alert alert-info">' + crmI('fa fa-clock-o') + ' Uploading document...</span>');
                            
                            // Upload via AJAX
                            $.ajax({
                                url: '{{ url("/documents/upload-edu-document") }}',
                                type: 'POST',
                                dataType: 'json',
                                data: formData,
                                contentType: false,
                                processData: false,
                                success: function(ress) {
                                    $zone.removeClass('uploading');
                                    
                                    if (ress.status) {
                                        $('.custom-error-msg').html('<span class="alert alert-success">' + ress.message + '</span>');
                                        
                                        var row = $('#id_' + fileid);
                                        var docNameWithoutExt = ress.filename.replace(/\.[^/.]+$/, "").replace(/\s+/g, "_").toLowerCase();
                                        
                                        // Replace upload TD content (Column 1 = File Name)
                                        var uploadTd = row.find('td').eq(1);
                                        uploadTd.html(
                                            '<div data-id="' + fileid + '" data-name="' + docNameWithoutExt + '" class="doc-row" title="Uploaded by: Admin" oncontextmenu="showFileContextMenu(event, ' + fileid + ', \'' + ress.filetype + '\', \'' + ress.fileurl + '\', \'' + doccategory + '\', \'' + (ress.status_value || 'draft') + '\'); return false;">' +
                                                '<a href="javascript:void(0);" onclick="previewFile(\'' + ress.filetype + '\', \'' + ress.fileurl + '\', \'preview-container-' + doccategory + '\')">' +
                                                    crmI('fas fa-file-image') + ' <span>' + ress.filename + '</span>' +
                                                '</a>' +
                                            '</div>'
                                        );
                                        
                                        // Add hidden elements for context menu actions (Column 2 = Actions)
                                        var actionTd = row.find('td').eq(2);
                                        actionTd.html(
                                            '<a class="renamechecklist" data-id="' + fileid + '" href="javascript:;" style="display: none;"></a>' +
                                            '<a class="renamedoc" data-id="' + fileid + '" href="javascript:;" style="display: none;"></a>' +
                                            '<a class="download-file" data-filelink="' + ress.fileurl + '" data-filename="' + ress.filekey + '" href="#" style="display: none;"></a>' +
                                            '<a class="notuseddoc" data-id="' + fileid + '" data-doctype="' + ress.doctype + '" data-href="notuseddoc" href="javascript:;" style="display: none;"></a>'
                                        );
                                        
                                        row.addClass('drow');
                                        
                                        // Reload activities
                                        if (typeof getallactivities === 'function') {
                                            getallactivities();
                                        }
                                        
                                        // Reload the page to refresh the document list
                                        setTimeout(function() {
                                            location.reload();
                                        }, 1000);
                                    } else {
                                        $('.custom-error-msg').html('<span class="alert alert-danger">' + ress.message + '</span>');
                                    }
                                },
                                error: function(xhr, status, error) {
                                    $zone.removeClass('uploading');
                                    $('.custom-error-msg').html('<span class="alert alert-danger">Upload failed. Please try again.</span>');
                                    console.error('Personal doc upload error:', error);
                                }
                            });
                        } else {
                            console.error('❌ No files in drop event');
                        }
                        return false;
                    });
                    
                    console.log('✅ Local drag-drop handlers attached');
                }
                
                // CRITICAL: Initialize IMMEDIATELY (before detail-main.js loads)
                // This ensures our handlers are attached first and can use stopImmediatePropagation()
                console.log('🚀 Attaching Personal Documents handlers IMMEDIATELY');
                initPersonalDocDragDrop();
                
                // Also initialize on DOM ready (in case elements weren't ready above)
                $(document).ready(function() {
                    console.log('✅ Personal Documents DOM Ready - Reinitializing');
                    initPersonalDocDragDrop();
                });
                
                // Re-initialize when Personal Documents tab is shown
                // Listen for tab clicks using the sidebar-tabs.js system
                $(document).on('click', '.client-nav-button[data-tab="personaldocuments"]', function() {
                    console.log('📂 Personal Documents tab clicked, reinitializing...');
                    setTimeout(function() {
                        initPersonalDocDragDrop();
                    }, 200); // Delay to ensure tab is visible
                });
                
                // Also check if tab is already active (e.g., direct URL navigation)
                if ($('#personaldocuments-tab').hasClass('active')) {
                    console.log('📂 Personal Documents tab already active on load');
                    setTimeout(function() {
                        initPersonalDocDragDrop();
                    }, 500);
                }
                
                let currentContextFile = null;
                let currentContextData = {};

                function showFileContextMenu(event, fileId, fileType, fileUrl, categoryId, fileStatus) {
                    event.preventDefault();
                    event.stopPropagation();
                    
                    currentContextFile = fileId;
                    currentContextData = {
                        fileId: fileId,
                        fileType: fileType,
                        fileUrl: fileUrl,
                        categoryId: categoryId,
                        fileStatus: fileStatus
                    };

                    const menu = document.getElementById('fileContextMenu');
                    
                    // Show/hide PDF option based on file type
                    const pdfOption = document.getElementById('context-pdf-option');
                    const fileExt = fileType.toLowerCase();
                    if (['jpg', 'png', 'jpeg'].includes(fileExt)) {
                        pdfOption.style.display = 'block';
                    } else {
                        pdfOption.style.display = 'none';
                    }

                    // Measure actual menu dimensions dynamically
                    menu.style.visibility = 'hidden';
                    menu.style.display = 'block';
                    const menuWidth = menu.offsetWidth;
                    const menuHeight = menu.offsetHeight;
                    menu.style.display = 'none';
                    menu.style.visibility = 'visible';

                    // Position menu at cursor (position: fixed uses viewport coordinates)
                    const MIN_PADDING = 5;
                    const CURSOR_OFFSET = 2;
                    const viewportWidth = window.innerWidth;
                    const viewportHeight = window.innerHeight;
                    const cursorX = event.clientX;
                    const cursorY = event.clientY;

                    let menuLeft = cursorX + CURSOR_OFFSET;
                    let menuTop = cursorY + CURSOR_OFFSET;

                    // Check right edge - show to the left of cursor if needed
                    if (menuLeft + menuWidth > viewportWidth - MIN_PADDING) {
                        menuLeft = cursorX - menuWidth - CURSOR_OFFSET;
                    }
                    // Check bottom edge - show above cursor if needed
                    if (menuTop + menuHeight > viewportHeight - MIN_PADDING) {
                        menuTop = cursorY - menuHeight - CURSOR_OFFSET;
                    }
                    // Keep inside viewport (left/top edges)
                    menuLeft = Math.max(MIN_PADDING, Math.min(menuLeft, viewportWidth - menuWidth - MIN_PADDING));
                    menuTop = Math.max(MIN_PADDING, Math.min(menuTop, viewportHeight - menuHeight - MIN_PADDING));

                    menu.style.left = menuLeft + 'px';
                    menu.style.top = menuTop + 'px';
                    menu.style.display = 'block';

                    // Hide menu when clicking elsewhere
                    setTimeout(() => {
                        document.addEventListener('click', hideContextMenu);
                    }, 100);
                }

                function hideContextMenu() {
                    const menu = document.getElementById('fileContextMenu');
                    menu.style.display = 'none';
                    document.removeEventListener('click', hideContextMenu);
                }

                function handleContextAction(action) {
                    if (!currentContextFile) return;

                    hideContextMenu();

                    switch(action) {
                        case 'rename-checklist':
                            $('.renamechecklist[data-id="' + currentContextFile + '"]').click();
                            break;
                        case 'rename-doc':
                            $('.renamedoc[data-id="' + currentContextFile + '"]').click();
                            break;
                        case 'move':
                            openMoveDocumentModal(currentContextFile, 'personal');
                            break;
                        case 'preview':
                            window.open(currentContextData.fileUrl, '_blank');
                            break;
                        case 'pdf':
                            const pdfUrl = '{{ URL::to('/document/download/pdf') }}/' + currentContextFile;
                            window.open(pdfUrl, '_blank');
                            break;
                        case 'download':
                            // Try to find download button by filelink (multiple elements may exist - table + grid; trigger only first to avoid multiple tabs)
                            let $downloadBtn = $('.download-file[data-filelink="' + currentContextData.fileUrl + '"]');
                            if ($downloadBtn.length === 0) {
                                // Fallback: try finding by document ID
                                $downloadBtn = $('.download-file[data-id="' + currentContextFile + '"]');
                            }
                            if ($downloadBtn.length > 0) {
                                $downloadBtn.first().click();
                            } else {
                                console.error('Download button not found for file ID:', currentContextFile);
                                alert('Download link not found. Please refresh the page and try again.');
                            }
                            break;
                        case 'not-used':
                            $('.notuseddoc[data-id="' + currentContextFile + '"]').click();
                            break;
                    }
                }

                // ============================================================================
                // MOVE DOCUMENT FUNCTIONALITY
                // ============================================================================
                let currentMoveDocumentId = null;
                let currentMoveDocumentType = null;

                function openMoveDocumentModal(documentId, currentType) {
                    currentMoveDocumentId = documentId;
                    currentMoveDocumentType = currentType;
                    
                    // Reset modal
                    $('#moveTargetType').val('');
                    $('#movePersonalCategoryContainer').hide();
                    $('#moveVisaMatterContainer').hide();
                    $('#moveVisaCategoryContainer').hide();
                    $('#movePersonalCategoryId').empty().append('<option value="">-- Select Category --</option>');
                    $('#moveVisaMatterId').empty().append('<option value="">-- Select Matter --</option>');
                    $('#moveVisaCategoryId').empty().append('<option value="">-- Select Category --</option>');
                    $('#moveDocumentError').hide();
                    
                    // Show modal
                    $('#moveDocumentModal').modal('show');
                }

                // Handle target type change
                $(document).on('change', '#moveTargetType', function() {
                    const targetType = $(this).val();
                    
                    // Hide all containers first
                    $('#movePersonalCategoryContainer').hide();
                    $('#moveVisaMatterContainer').hide();
                    $('#moveVisaCategoryContainer').hide();
                    $('#moveDocumentError').hide();
                    
                    if (!targetType) {
                        return;
                    }
                    
                    if (targetType === 'personal') {
                        // Load personal document categories
                        const categories = [];
                        $('.subtab2-button').each(function() {
                            const catId = $(this).data('subtab2');
                            const catTitle = $(this).text().trim();
                            if (catId && catTitle) {
                                categories.push({ id: catId, title: catTitle });
                            }
                        });
                        
                        $('#movePersonalCategoryId').empty().append('<option value="">-- Select Category --</option>');
                        categories.forEach(cat => {
                            $('#movePersonalCategoryId').append(`<option value="${cat.id}">${cat.title}</option>`);
                        });
                        $('#movePersonalCategoryContainer').show();
                        
                    } else if (targetType === 'visa') {
                        // Load visa matters first
                        const clientId = '<?= $clientId ?? "" ?>';
                        if (!clientId) {
                            $('#moveDocumentError').text('Error: Client ID not found').show();
                            return;
                        }
                        
                        $('#moveVisaMatterId').empty().append('<option value="">Loading...</option>');
                        $('#moveVisaMatterContainer').show();
                        
                        // Fetch matters via AJAX
                        $.ajax({
                            url: '{{ URL::to('/get-client-matters') }}/' + clientId,
                            type: 'GET',
                            success: function(response) {
                                $('#moveVisaMatterId').empty().append('<option value="">-- Select Matter --</option>');
                                const matters = response && response.matters ? response.matters : [];
                                if (matters.length > 0) {
                                    matters.forEach(matter => {
                                        const matterLabel = matter.client_unique_matter_no || ('Matter #' + matter.id);
                                        $('#moveVisaMatterId').append(`<option value="${matter.id}">${matterLabel}</option>`);
                                    });
                                } else {
                                    $('#moveVisaMatterId').append('<option value="">No matters found</option>');
                                }
                            },
                            error: function() {
                                $('#moveVisaMatterId').empty().append('<option value="">Error loading matters</option>');
                            }
                        });
                    }
                });

                // Handle visa matter selection - load categories for that matter
                $(document).on('change', '#moveVisaMatterId', function() {
                    const matterId = $(this).val();
                    $('#moveVisaCategoryContainer').hide();
                    $('#moveDocumentError').hide();
                    
                    if (!matterId) {
                        return;
                    }
                    
                    const clientId = '<?= $clientId ?? "" ?>';
                    $('#moveVisaCategoryId').empty().append('<option value="">Loading...</option>');
                    $('#moveVisaCategoryContainer').show();
                    
                    // Fetch visa categories for this matter via AJAX
                    $.ajax({
                        url: '{{ URL::to('/get-visa-categories') }}',
                        type: 'GET',
                        data: {
                            client_id: clientId,
                            matter_id: matterId
                        },
                        success: function(response) {
                            $('#moveVisaCategoryId').empty().append('<option value="">-- Select Category --</option>');
                            const categories = Array.isArray(response) ? response : [];
                            if (categories.length > 0) {
                                categories.forEach(category => {
                                    $('#moveVisaCategoryId').append(`<option value="${category.id}">${category.title}</option>`);
                                });
                            } else {
                                $('#moveVisaCategoryId').append('<option value="">No categories found</option>');
                            }
                        },
                        error: function() {
                            $('#moveVisaCategoryId').empty().append('<option value="">Error loading categories</option>');
                        }
                    });
                });

                // Handle move document confirmation
                $(document).on('click', '#confirmMoveDocument', function() {
                    const targetType = $('#moveTargetType').val();
                    let targetId = null;
                    const $error = $('#moveDocumentError');
                    const $btn = $(this);
                    
                    // Validate based on target type
                    if (!targetType) {
                        $error.text('Please select a destination type').show();
                        return;
                    }
                    
                    if (targetType === 'personal') {
                        targetId = $('#movePersonalCategoryId').val();
                        if (!targetId) {
                            $error.text('Please select a personal category').show();
                            return;
                        }
                    } else if (targetType === 'visa') {
                        const matterIdForMove = $('#moveVisaMatterId').val();
                        if (!matterIdForMove) {
                            $error.text('Please select a visa matter').show();
                            return;
                        }
                        targetId = $('#moveVisaCategoryId').val();
                        if (!targetId) {
                            $error.text('Please select a visa category').show();
                            return;
                        }
                    }
                    
                    // Disable button and show loading
                    $btn.prop('disabled', true).text('Moving...');
                    $error.hide();
                    
                    // Make AJAX request
                    $.ajax({
                        url: '{{ URL::to('/documents/move') }}',
                        type: 'POST',
                        data: {
                            _token: '{{ csrf_token() }}',
                            document_id: currentMoveDocumentId,
                            target_type: targetType,
                            target_id: targetId,
                            target_matter_id: targetType === 'visa' ? $('#moveVisaMatterId').val() : ''
                        },
                        success: function(response) {
                            if (response.status) {
                                // Close modal
                                $('#moveDocumentModal').modal('hide');
                                
                                // Show success message using alert
                                alert(response.message || 'Document moved successfully');
                                
                                // Reload the page to refresh document lists
                                location.reload();
                            } else {
                                $error.text(response.message || 'Failed to move document').show();
                                $btn.prop('disabled', false).text('Move Document');
                            }
                        },
                        error: function(xhr) {
                            let errorMsg = 'An error occurred while moving the document';
                            if (xhr.responseJSON && xhr.responseJSON.message) {
                                errorMsg = xhr.responseJSON.message;
                            }
                            $error.text(errorMsg).show();
                            $btn.prop('disabled', false).text('Move Document');
                        }
                    });
                });

                // Reset button state when modal is closed
                $('#moveDocumentModal').on('hidden.bs.modal', function() {
                    $('#confirmMoveDocument').prop('disabled', false).text('Move Document');
                });

                // Hide context menu on escape key
                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape') {
                        hideContextMenu();
                    }
                });
            </script>

            <style>
                .context-menu-item:hover {
                    background-color: #f8f9fa;
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

                /* Bulk Upload Styles */
                .bulk-upload-dropzone {
                    position: relative;
                }
                
                /* Make all child elements transparent to pointer events so drag events reach the dropzone */
                .bulk-upload-dropzone * {
                    pointer-events: none;
                }
                
                .bulk-upload-dropzone.drag_over {
                    border-color: #28a745;
                    background-color: #e8f5e9;
                }

                /* Bulk Upload Mapping Table Styles */
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

                .bulk-upload-mapping-modal {
                    display: none;
                    position: fixed;
                    z-index: 10000;
                    left: 0;
                    top: 0;
                    width: 100%;
                    height: 100%;
                    background-color: rgba(0,0,0,0.5);
                }

                .bulk-upload-mapping-content {
                    background-color: #fefefe;
                    margin: 5% auto;
                    padding: 20px;
                    border: 1px solid #888;
                    border-radius: 8px;
                    width: 90%;
                    max-width: 900px;
                    max-height: 80vh;
                    overflow-y: auto;
                }

                .bulk-upload-mapping-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    margin-bottom: 20px;
                    padding-bottom: 15px;
                    border-bottom: 2px solid #eee;
                }

                .bulk-upload-mapping-header h3 {
                    margin: 0;
                    color: #333;
                }

                .close-mapping-modal {
                    color: #aaa;
                    font-size: 28px;
                    font-weight: bold;
                    cursor: pointer;
                }

                .close-mapping-modal:hover {
                    color: #000;
                }

                .bulk-upload-actions {
                    margin-top: 20px;
                    padding-top: 15px;
                    border-top: 2px solid #eee;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                }

                .bulk-upload-progress {
                    display: none;
                    margin-top: 15px;
                }

                .progress-bar-container {
                    width: 100%;
                    height: 25px;
                    background-color: #f0f0f0;
                    border-radius: 4px;
                    overflow: hidden;
                }

                .progress-bar {
                    height: 100%;
                    background-color: #4a90e2;
                    width: 0%;
                    transition: width 0.3s ease;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    color: white;
                    font-size: 12px;
                }
            </style>

            <!-- Bulk Upload Mapping Modal -->
            <div id="bulk-upload-mapping-modal" class="bulk-upload-mapping-modal">
                <div class="bulk-upload-mapping-content">
                    <div class="bulk-upload-mapping-header">
                        <h3>@icon('fa-link') Map Files to Checklists</h3>
                        <span class="close-mapping-modal">&times;</span>
                    </div>
                    <div id="bulk-upload-mapping-table"></div>
                    <div class="bulk-upload-actions">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="checkbox" id="auto-create-unmatched" checked>
                            <span>Auto-create checklist for unmatched files</span>
                        </label>
                        <div>
                            <button type="button" class="btn btn-secondary" id="cancel-bulk-upload">Cancel</button>
                            <button type="button" class="btn btn-primary" id="confirm-bulk-upload">Upload All</button>
                        </div>
                    </div>
                    <div class="bulk-upload-progress" id="bulk-upload-progress">
                        <p>Uploading files...</p>
                        <div class="progress-bar-container">
                            <div class="progress-bar" id="bulk-upload-progress-bar">0%</div>
                        </div>
                    </div>
                </div>
            </div>

            <script>
                // ============================================================================
                // BULK UPLOAD FUNCTIONALITY
                // ============================================================================
                
                let bulkUploadFiles = {};
                let currentCategoryId = null;
                let currentClientId = <?= $clientId ?>;
                
                // Toggle bulk upload dropzone
                $(document).on('click', '.bulk-upload-toggle-btn', function() {
                    const categoryId = $(this).data('categoryid');
                    const dropzoneContainer = $('#bulk-upload-' + categoryId);
                    
                    // Hide all other dropzones first
                    $('.bulk-upload-dropzone-container').not('#bulk-upload-' + categoryId).slideUp();
                    $('.bulk-upload-toggle-btn').not(this).html(crmI('fas fa-upload') + ' Bulk Upload');
                    
                    if (dropzoneContainer.is(':visible')) {
                        dropzoneContainer.slideUp();
                        $(this).html(crmI('fas fa-upload') + ' Bulk Upload');
                        // Clear files if closing
                        bulkUploadFiles[categoryId] = [];
                        dropzoneContainer.find('.bulk-upload-file-list').hide();
                        dropzoneContainer.find('.bulk-upload-files-container').empty();
                        dropzoneContainer.find('.file-count').text('0');
                    } else {
                        dropzoneContainer.slideDown();
                        $(this).html(crmI('fas fa-times') + ' Close');
                        currentCategoryId = categoryId;
                    }
                });
                
                // Initialize bulk upload files array for each category
                $('.bulk-upload-dropzone').each(function() {
                    const categoryId = $(this).data('categoryid');
                    if (!bulkUploadFiles[categoryId]) {
                        bulkUploadFiles[categoryId] = [];
                    }
                });
                
                // Click to browse files
                $(document).on('click', '.bulk-upload-dropzone', function(e) {
                    if (!$(e.target).is('input')) {
                        const categoryId = $(this).data('categoryid');
                        $(this).closest('.bulk-upload-dropzone-container').find('.bulk-upload-file-input[data-categoryid="' + categoryId + '"]').click();
                    }
                });
                
                // File input change
                $(document).on('change', '.bulk-upload-file-input', function() {
                    const categoryId = $(this).data('categoryid');
                    const files = this.files;
                    
                    if (files.length > 0) {
                        handleBulkFilesSelected(categoryId, files);
                    }
                });
                
                // Attach DIRECT handlers to bulk upload dropzones for highest priority
                function initBulkUploadDragDrop() {
                    console.log('🔄 Initializing Bulk Upload Drag & Drop...');
                    console.log('📊 Bulk upload zones found:', $('.bulk-upload-dropzone').length);
                    
                    $('.bulk-upload-dropzone').each(function() {
                        var $zone = $(this);
                        
                        // Remove any existing handlers first
                        $zone.off('dragenter.bulkdirect dragover.bulkdirect dragleave.bulkdirect drop.bulkdirect');
                        
                        // Use native event listeners for maximum compatibility
                        var elem = this;
                        
                        // Remove old native listeners if they exist
                        if (elem._bulkDragOver) {
                            elem.removeEventListener('dragover', elem._bulkDragOver);
                        }
                        if (elem._bulkDrop) {
                            elem.removeEventListener('drop', elem._bulkDrop);
                        }
                        if (elem._bulkDragEnter) {
                            elem.removeEventListener('dragenter', elem._bulkDragEnter);
                        }
                        if (elem._bulkDragLeave) {
                            elem.removeEventListener('dragleave', elem._bulkDragLeave);
                        }
                        
                        // Dragover handler (REQUIRED for drop to work)
                        elem._bulkDragOver = function(e) {
                            console.log('🔥 NATIVE BULK DRAGOVER');
                            e.preventDefault();
                            e.stopPropagation();
                            e.dataTransfer.dropEffect = 'copy';
                            $zone.addClass('drag_over');
                        };
                        elem.addEventListener('dragover', elem._bulkDragOver);
                        
                        // Dragenter handler
                        elem._bulkDragEnter = function(e) {
                            console.log('🔥 NATIVE BULK DRAGENTER');
                            e.preventDefault();
                            e.stopPropagation();
                            $zone.addClass('drag_over');
                        };
                        elem.addEventListener('dragenter', elem._bulkDragEnter);
                        
                        // Dragleave handler
                        elem._bulkDragLeave = function(e) {
                            console.log('⚠️ NATIVE BULK DRAGLEAVE');
                            e.preventDefault();
                            e.stopPropagation();
                            
                            var rect = elem.getBoundingClientRect();
                            if (e.clientX <= rect.left || e.clientX >= rect.right || 
                                e.clientY <= rect.top || e.clientY >= rect.bottom) {
                                $zone.removeClass('drag_over');
                            }
                        };
                        elem.addEventListener('dragleave', elem._bulkDragLeave);
                        
                        // Drop handler
                        elem._bulkDrop = function(e) {
                            console.log('🎯 NATIVE BULK DROP');
                            e.preventDefault();
                            e.stopPropagation();
                            $zone.removeClass('drag_over');
                            
                            var files = e.dataTransfer ? e.dataTransfer.files : null;
                            console.log('📄 Files dropped:', files ? files.length : 0);
                            
                            if (files && files.length > 0) {
                                var categoryId = $zone.data('categoryid');
                                console.log('📂 Category ID:', categoryId);
                                handleBulkFilesSelected(categoryId, files);
                            } else {
                                console.error('❌ No files in drop event');
                            }
                        };
                        elem.addEventListener('drop', elem._bulkDrop);
                        
                        console.log('✅ Attached native handlers to bulk dropzone:', $zone.data('categoryid'));
                    });
                }
                
                // Initialize bulk upload drag-drop when container becomes visible
                $(document).on('click', '.bulk-upload-toggle-btn', function() {
                    setTimeout(function() {
                        initBulkUploadDragDrop();
                    }, 300); // Wait for slideDown animation
                });
                
                // Also initialize on DOM ready for any visible dropzones
                $(document).ready(function() {
                    initBulkUploadDragDrop();
                });
                
                // Keep delegated handlers as fallback
                $(document).on('dragover', '.bulk-upload-dropzone', function(e) {
                    console.log('🔥 DELEGATED BULK DRAGOVER');
                    e.preventDefault();
                    e.stopPropagation();
                    $(this).addClass('drag_over');
                    if (e.originalEvent && e.originalEvent.dataTransfer) {
                        e.originalEvent.dataTransfer.dropEffect = 'copy';
                    }
                    return false;
                });
                
                $(document).on('dragenter', '.bulk-upload-dropzone', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    $(this).addClass('drag_over');
                    return false;
                });
                
                $(document).on('dragleave', '.bulk-upload-dropzone', function(e) {
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
                
                $(document).on('drop', '.bulk-upload-dropzone', function(e) {
                    console.log('🎯 DELEGATED BULK DROP');
                    e.preventDefault();
                    e.stopPropagation();
                    $(this).removeClass('drag_over');
                    
                    const categoryId = $(this).data('categoryid');
                    const files = e.originalEvent && e.originalEvent.dataTransfer ? e.originalEvent.dataTransfer.files : null;
                    
                    console.log('📄 Files dropped:', files ? files.length : 0);
                    
                    if (files && files.length > 0) {
                        handleBulkFilesSelected(categoryId, files);
                    } else {
                        console.error('❌ No files in drop event');
                    }
                    return false;
                });
                
                // Handle files selected
                function handleBulkFilesSelected(categoryId, files) {
                    if (!bulkUploadFiles[categoryId]) {
                        bulkUploadFiles[categoryId] = [];
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
                        const exists = bulkUploadFiles[categoryId].some(f => f.name === file.name && f.size === file.size);
                        if (!exists) {
                            bulkUploadFiles[categoryId].push(file);
                        }
                    });
                    
                    if (invalidFiles.length > 0) {
                        alert('The following files were skipped:\n' + invalidFiles.join('\n'));
                    }
                    
                    if (bulkUploadFiles[categoryId].length === 0) {
                        alert('No valid files selected. Please select PDF, JPG, PNG, DOC, or DOCX files under 20MB.');
                        return;
                    }
                    
                    // Show file list
                    const container = $('#bulk-upload-' + categoryId);
                    container.find('.bulk-upload-file-list').show();
                    container.find('.file-count').text(bulkUploadFiles[categoryId].length);
                    
                    // Show mapping interface
                    showBulkUploadMapping(categoryId);
                }
                
                // Show mapping interface
                function showBulkUploadMapping(categoryId) {
                    currentCategoryId = categoryId;
                    const files = bulkUploadFiles[categoryId];
                    
                    if (files.length === 0) {
                        return;
                    }
                    
                    // Get existing checklists for this category
                    getExistingChecklists(categoryId, function(checklists) {
                        // Call backend to get auto-matches
                        getAutoChecklistMatches(categoryId, files, checklists, function(matches) {
                            displayMappingInterface(files, checklists, matches);
                        });
                    });
                }
                
                // Get existing checklists
                function getExistingChecklists(categoryId, callback) {
                    const checklists = [];
                    const checklistNames = new Set();
                    
                    $('.documnetlist_' + categoryId + ' .personalchecklist-row').each(function() {
                        const checklistName = $(this).data('personalchecklistname');
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
                
                // Get auto-checklist matches from backend
                function getAutoChecklistMatches(categoryId, files, checklists, callback) {
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
                            clientid: currentClientId,
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
                
                // Display mapping interface
                function displayMappingInterface(files, checklists, matches) {
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
                    
                    // Handle remove file button
                    $(document).off('click', '.remove-bulk-file').on('click', '.remove-bulk-file', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        
                        const $row = $(this).closest('tr');
                        const fileName = $row.data('file-name');
                        const categoryId = currentCategoryId;
                        
                        // Confirm before removing
                        if (!confirm('Are you sure you want to remove "' + fileName + '" from the upload list?')) {
                            return;
                        }
                        
                        // Find and remove the file from the array by matching file name
                        const fileArray = bulkUploadFiles[categoryId];
                        const fileIndex = fileArray.findIndex(f => f.name === fileName);
                        
                        if (fileIndex > -1) {
                            fileArray.splice(fileIndex, 1);
                        }
                        
                        // Remove the row
                        $row.remove();
                        
                        // Update file count
                        const remainingCount = fileArray.length;
                        const container = $('#bulk-upload-' + categoryId);
                        container.find('.file-count').text(remainingCount);
                        
                        // If no files left, hide the file list and modal
                        if (remainingCount === 0) {
                            $('#bulk-upload-mapping-modal').hide();
                            container.find('.bulk-upload-file-list').hide();
                            container.find('.bulk-upload-files-container').empty();
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
                    
                    modal.show();
                }
                
                // Close mapping modal
                $(document).off('click', '.close-mapping-modal, #cancel-bulk-upload').on('click', '.close-mapping-modal, #cancel-bulk-upload', function() {
                    $('#bulk-upload-mapping-modal').hide();
                    $('#bulk-upload-progress').hide();
                    $('#confirm-bulk-upload').prop('disabled', false);
                });
                
                // Confirm bulk upload
                $('#confirm-bulk-upload').on('click', function() {
                    const categoryId = currentCategoryId;
                    const files = bulkUploadFiles[categoryId];
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
                                // Auto-create from filename
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
                            // Auto-create for unmatched
                            mapping = {
                                type: 'new',
                                name: extractChecklistNameFromFile(fileName)
                            };
                        }
                        
                        if (!mapping) {
                            // Try to get from auto-match if available
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
                    uploadBulkFiles(categoryId, files, mappings);
                });
                
                // Extract checklist name from filename
                function extractChecklistNameFromFile(fileName) {
                    // Remove extension
                    let name = fileName.replace(/\.[^/.]+$/, '');
                    // Remove client name prefix (if exists)
                    name = name.replace(/^[^_]+_/, '');
                    // Remove timestamps
                    name = name.replace(/_\d{10,}$/, '');
                    // Replace underscores with spaces
                    name = name.replace(/_/g, ' ');
                    // Capitalize first letter of each word
                    name = name.replace(/\b\w/g, l => l.toUpperCase());
                    return name || 'Document';
                }
                
                // Upload bulk files
                function uploadBulkFiles(categoryId, files, mappings) {
                    const formData = new FormData();
                    formData.append('_token', '{{ csrf_token() }}');
                    formData.append('clientid', currentClientId);
                    formData.append('categoryid', categoryId);
                    formData.append('doctype', 'personal');
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
                        url: '{{ route("clients.documents.bulkUploadPersonalDocuments") }}',
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
                                // Reload the page or refresh the document list
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
                
                // Format file size
                function formatFileSize(bytes) {
                    if (bytes === 0) return '0 Bytes';
                    const k = 1024;
                    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
                    const i = Math.floor(Math.log(bytes) / Math.log(k));
                    return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
                }
                
                // Escape HTML
                function escapeHtml(text) {
                    const map = {
                        '&': '&amp;',
                        '<': '&lt;',
                        '>': '&gt;',
                        '"': '&quot;',
                        "'": '&#039;'
                    };
                    return text.replace(/[&<>"']/g, m => map[m]);
                }
            </script>

