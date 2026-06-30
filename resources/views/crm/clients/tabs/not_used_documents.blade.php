           <!-- Not Used Documents Tab (Shared - Client Level) -->
           <div class="tab-pane" id="notuseddocuments-tab">
                <div class="card full-width documentalls-container">
                    <div style="display: flex; gap: 20px; padding: 15px;">
                        <!-- Table Container -->
                        <div style="flex: 1; min-width: 0;">
                            <div class="subtab-header" style="margin-bottom: 15px;">
                                <h3>@icon('fa-folder') Not Used Documents</h3>
                                <p style="color: #374151;">Documents marked as "Not Used" from both Personal and Visa document tabs are shown here.</p>
                            </div>
                            <div style="overflow: auto; max-height: calc(100vh - 250px);">
                                <table class="checklist-table" style="width: 100%;">
                                    <thead>
                                        <tr>
                                            <th>Checklist</th>
                                            <th>Document Type</th>
                                            <th>File Name</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody class="tdata notuseddocumnetlist">
                                        <?php
                                        $fetchd = \App\Models\Document::where('client_id', $fetchedData->id)
                                        ->where('not_used_doc', 1)
                                        ->where('type','client')
                                        ->where(function($query) {
                                            $query->orWhere('doc_type','personal')
                                            ->orWhere('doc_type','visa')
                                            ->orWhere('doc_type','nomination');
                                        })->orderBy('type', 'DESC')->get();
                                        foreach($fetchd as $notuseKey=>$fetch)
                                        {
                                            $admin = \App\Models\Staff::where('id', $fetch->user_id)->first();
                                            ?>
                                            <tr class="drow" id="id_{{$fetch->id}}">
                                                <td style="white-space: initial;">
                                                    <span title="Uploaded by: <?php echo ($admin->first_name ?? 'NA'); ?> on <?php echo date('d/m/Y H:i', strtotime($fetch->created_at)); ?>"><?php echo $fetch->checklist; ?></span>
                                                </td>
                                                <td style="white-space: initial;">
                                                    <span class="badge badge-<?php echo $fetch->doc_type === 'personal' ? 'primary' : 'success'; ?>">
                                                        <?php echo ucfirst($fetch->doc_type); ?>
                                                    </span>
                                                </td>
                                                <td style="white-space: initial;">
                                                    <?php
                                                    if( isset($fetch->file_name) && $fetch->file_name !=""){ 
                                                        $fileUrl = isset($fetch->myfile_key) && $fetch->myfile_key != "" ? $fetch->myfile : 'https://'.env('AWS_BUCKET').'.s3.'. env('AWS_DEFAULT_REGION') . '.amazonaws.com/'.$fetchedData->client_id.'/'.$fetch->doc_type.'/'.$fetch->myfile;
                                                    ?>
                                                        <div data-id="{{$fetch->id}}" data-name="<?php echo $fetch->file_name; ?>" class="doc-row" title="Uploaded by: <?php echo ($admin->first_name ?? 'NA'); ?> on <?php echo date('d/m/Y H:i', strtotime($fetch->created_at)); ?>" oncontextmenu="showNotUsedFileContextMenu(event, <?= $fetch->id ?>, '<?= htmlspecialchars($fetch->filetype) ?>', '<?= $fileUrl ?>', '<?= $fetch->doc_type ?>', '<?= $fetch->status ?? 'draft' ?>'); return false;">
                                                            <?php if( isset($fetch->myfile_key) && $fetch->myfile_key != ""){ //For new file upload ?>
                                                                <a href="javascript:void(0);" onclick="previewFile('<?php echo $fetch->filetype;?>','<?php echo $fetch->myfile; ?>','preview-container-notuseddocumnetlist')">
                                                                    {!! \App\Helpers\IconHelper::fromLegacy('fas fa-file-image') !!} <span><?php echo $fetch->file_name . '.' . $fetch->filetype; ?></span>
                                                                </a>
                                                            <?php } else {  //For old file upload
                                                                $url = 'https://'.env('AWS_BUCKET').'.s3.'. env('AWS_DEFAULT_REGION') . '.amazonaws.com/';
                                                                ?>
                                                                <a href="javascript:void(0);" onclick="previewFile('<?php echo $fetch->filetype;?>','<?php echo $myawsfile; ?>','preview-container-notuseddocumnetlist')">
                                                                    {!! \App\Helpers\IconHelper::fromLegacy('fas fa-file-image') !!} <span><?php echo $fetch->file_name . '.' . $fetch->filetype; ?></span>
                                                                </a>
                                                            <?php } ?>
                                                        </div>
                                                    <?php
                                                    }
                                                    else
                                                    {
                                                        echo "N/A";
                                                    }?>
                                                </td>
                                                <td>
                                                    <!-- Hidden elements for context menu actions -->
                                                    <a data-id="<?= $fetch->id ?>" class="deletenote" data-doccategory="<?= $fetch->doc_type ?>" data-href="deletedocs" href="javascript:;" style="display: none;"></a>
                                                    <a data-id="{{$fetch->id}}" class="backtodoc" data-doctype="{{$fetch->doc_type}}" data-href="backtodoc" href="javascript:;" style="display: none;"></a>
                                                </td>
                                            </tr>
                                        <?php
                                        } //end foreach ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Preview Container -->
                        <div class="preview-pane file-preview-container preview-container-notuseddocumnetlist" style="display: inline;margin-top: 15px !important;width: 499px;">
                            <p style="color: #374151;">Click on a file to preview it here.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Custom Context Menu for Not Used Documents -->
            <div id="notUsedFileContextMenu" class="context-menu" style="display: none; position: fixed; background: white; border: 1px solid #ccc; border-radius: 4px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); z-index: 10000; min-width: 180px;">
                <div class="context-menu-item" onclick="handleNotUsedContextAction('preview')" style="padding: 8px 12px; cursor: pointer; border-bottom: 1px solid #eee;">
                    @icon('fa-eye', ['style' => 'margin-right: 8px;']) Preview
                </div>
                <div class="context-menu-item" onclick="handleNotUsedContextAction('delete')" style="padding: 8px 12px; cursor: pointer; border-bottom: 1px solid #eee;">
                    @icon('fa-trash', ['style' => 'margin-right: 8px;']) Delete
                </div>
                <div class="context-menu-item" onclick="handleNotUsedContextAction('back-to-doc')" style="padding: 8px 12px; cursor: pointer;">
                    @icon('fa-undo', ['style' => 'margin-right: 8px;']) Back To Document
                </div>
            </div>

            <script>
                let currentNotUsedContextFile = null;
                let currentNotUsedContextData = {};

                function showNotUsedFileContextMenu(event, fileId, fileType, fileUrl, docType, fileStatus) {
                    event.preventDefault();
                    event.stopPropagation();
                    
                    currentNotUsedContextFile = fileId;
                    currentNotUsedContextData = {
                        fileId: fileId,
                        fileType: fileType,
                        fileUrl: fileUrl,
                        docType: docType,
                        fileStatus: fileStatus
                    };

                    const menu = document.getElementById('notUsedFileContextMenu');
                    
                    // Position menu at cursor (position: fixed uses viewport coordinates)
                    const MENU_WIDTH = 180;
                    const MENU_HEIGHT = 120;
                    const viewportWidth = window.innerWidth;
                    const viewportHeight = window.innerHeight;
                    const offset = 5;

                    let menuLeft = event.clientX + offset;
                    let menuTop = event.clientY + offset;

                    if (menuLeft + MENU_WIDTH > viewportWidth) {
                        menuLeft = event.clientX - MENU_WIDTH - offset;
                    }
                    if (menuTop + MENU_HEIGHT > viewportHeight) {
                        menuTop = event.clientY - MENU_HEIGHT - offset;
                    }
                    menuLeft = Math.max(offset, menuLeft);
                    menuTop = Math.max(offset, menuTop);

                    menu.style.left = menuLeft + 'px';
                    menu.style.top = menuTop + 'px';

                    menu.style.display = 'block';

                    // Hide menu when clicking elsewhere
                    setTimeout(() => {
                        document.addEventListener('click', hideNotUsedContextMenu);
                    }, 100);
                }

                function hideNotUsedContextMenu() {
                    const menu = document.getElementById('notUsedFileContextMenu');
                    menu.style.display = 'none';
                    document.removeEventListener('click', hideNotUsedContextMenu);
                }

                function handleNotUsedContextAction(action) {
                    if (!currentNotUsedContextFile) return;

                    hideNotUsedContextMenu();

                    switch(action) {
                        case 'preview':
                            window.open(currentNotUsedContextData.fileUrl, '_blank');
                            break;
                        case 'delete':
                            $('.deletenote[data-id="' + currentNotUsedContextFile + '"]').click();
                            break;
                        case 'back-to-doc':
                            $('.backtodoc[data-id="' + currentNotUsedContextFile + '"]').click();
                            break;
                    }
                }

                // Hide context menu on escape key
                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape') {
                        hideNotUsedContextMenu();
                    }
                });
            </script>

            <style>
                .context-menu-item:hover {
                    background-color: #f8f9fa;
                }
            </style>

