           <!-- Not Used Documents Tab (Shared - Client Level) -->
           @php
                $notUsedDocumentsTabFragmentUrl = ! empty($encodeId)
                    ? route('clients.detail.notuseddocuments-tab', array_filter([
                        'client_id' => $encodeId,
                        'client_unique_matter_ref_no' => $id1 ?? null,
                    ], static function ($value) {
                        return $value !== null && $value !== '';
                    }))
                    : '';
           @endphp
           <div class="tab-pane" id="notuseddocuments-tab"
                @if($notUsedDocumentsTabFragmentUrl !== '') data-notuseddocuments-url="{{ $notUsedDocumentsTabFragmentUrl }}" @endif>
                <div class="card full-width documentalls-container">
                    <div style="display: flex; gap: 20px; padding: 15px;">
                        <!-- Table Container -->
                        <div style="flex: 1; min-width: 0;">
                            <div class="subtab-header" style="margin-bottom: 15px;">
                                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                                    <div>
                                        <h3>@icon('fa-folder') Not Used Documents</h3>
                                        <p style="color: #374151; margin-bottom: 0;">Documents marked as "Not Used" from both Personal and Visa document tabs are shown here.</p>
                                    </div>
                                    <button type="button" class="btn btn-secondary client-nav-button client-nav-button--inline" data-tab="personaldocuments">
                                        @icon('fa-folder') Personal Documents
                                    </button>
                                </div>
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
                                        $fetchd = \App\Support\ClientDetailDocumentsTab::notUsedDocuments((int) $fetchedData->id);

                                        $personalCategoryIds = $fetchd->where('doc_type', 'personal')->pluck('folder_name')->filter()->unique()->values();
                                        $visaCategoryIds = $fetchd->where('doc_type', 'visa')->pluck('folder_name')->filter()->unique()->values();
                                        $nominationCategoryIds = $fetchd->where('doc_type', 'nomination')->pluck('folder_name')->filter()->unique()->values();

                                        $personalCategoryTitles = $personalCategoryIds->isNotEmpty()
                                            ? \App\Models\PersonalDocumentType::whereIn('id', $personalCategoryIds)->pluck('title', 'id')
                                            : collect();
                                        $visaCategoryTitles = $visaCategoryIds->isNotEmpty()
                                            ? \App\Models\VisaDocumentType::whereIn('id', $visaCategoryIds)->pluck('title', 'id')
                                            : collect();
                                        $nominationCategoryTitles = $nominationCategoryIds->isNotEmpty()
                                            ? \App\Models\NominationDocumentType::whereIn('id', $nominationCategoryIds)->pluck('title', 'id')
                                            : collect();

                                        $matterIds = $fetchd->whereIn('doc_type', ['visa', 'nomination'])
                                            ->pluck('client_matter_id')
                                            ->filter()
                                            ->unique()
                                            ->values();
                                        $matterDisplayNames = collect();
                                        if ($matterIds->isNotEmpty()) {
                                            $matterDisplayNames = \App\Models\ClientMatter::with('matter:id,title')
                                                ->whereIn('id', $matterIds)
                                                ->get()
                                                ->mapWithKeys(function ($clientMatter) {
                                                    $label = $clientMatter->client_unique_matter_no ?? '';
                                                    if ($clientMatter->matter && !empty($clientMatter->matter->title)) {
                                                        $label = trim($label) !== ''
                                                            ? $label . ' - ' . $clientMatter->matter->title
                                                            : $clientMatter->matter->title;
                                                    }

                                                    return [$clientMatter->id => ($label !== '' ? $label : 'N/A')];
                                                });
                                        }

                                        foreach($fetchd as $notuseKey=>$fetch)
                                        {
                                            $admin = $fetch->staff;

                                            $categoryLabel = '';
                                            if ($fetch->doc_type === 'personal') {
                                                $categoryTitle = $personalCategoryTitles->get((int) $fetch->folder_name)
                                                    ?? $personalCategoryTitles->get($fetch->folder_name);
                                                if ($categoryTitle) {
                                                    $categoryLabel = $categoryTitle;
                                                }
                                            } elseif ($fetch->doc_type === 'visa') {
                                                $categoryTitle = $visaCategoryTitles->get((int) $fetch->folder_name)
                                                    ?? $visaCategoryTitles->get($fetch->folder_name);
                                                if ($categoryTitle) {
                                                    $categoryLabel = $categoryTitle;
                                                    if (!empty($fetch->client_matter_id)) {
                                                        $matterName = $matterDisplayNames->get((int) $fetch->client_matter_id);
                                                        if ($matterName) {
                                                            $categoryLabel .= ' (' . $matterName . ')';
                                                        }
                                                    }
                                                }
                                            } elseif ($fetch->doc_type === 'nomination') {
                                                $categoryTitle = $nominationCategoryTitles->get((int) $fetch->folder_name)
                                                    ?? $nominationCategoryTitles->get($fetch->folder_name);
                                                if ($categoryTitle) {
                                                    $categoryLabel = $categoryTitle;
                                                    if (!empty($fetch->client_matter_id)) {
                                                        $matterName = $matterDisplayNames->get((int) $fetch->client_matter_id);
                                                        if ($matterName) {
                                                            $categoryLabel .= ' (' . $matterName . ')';
                                                        }
                                                    }
                                                }
                                            }
                                            ?>
                                            <tr class="drow" id="id_{{$fetch->id}}">
                                                <td style="white-space: initial;">
                                                    <span title="Uploaded by: <?php echo htmlspecialchars($admin->first_name ?? 'NA'); ?> on <?php echo date('d/m/Y H:i', strtotime($fetch->created_at)); ?>"><?php echo htmlspecialchars($fetch->checklist ?? ''); ?></span>
                                                    <?php if ($categoryLabel !== ''): ?>
                                                        <small style="display: block; margin-top: 4px; color: #6b7280; font-size: 12px;"><?php echo htmlspecialchars($categoryLabel); ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td style="white-space: initial;">
                                                    <span class="badge badge-<?php echo $fetch->doc_type === 'personal' ? 'primary' : 'success'; ?>">
                                                        <?php echo $fetch->doc_type === 'nomination' ? 'File Document' : ucfirst((string) $fetch->doc_type); ?>
                                                    </span>
                                                </td>
                                                <td style="white-space: initial;">
                                                    <?php
                                                    if( isset($fetch->file_name) && $fetch->file_name !=""){ 
                                                        $fileUrl = isset($fetch->myfile_key) && $fetch->myfile_key != "" ? $fetch->myfile : 'https://'.env('AWS_BUCKET').'.s3.'. env('AWS_DEFAULT_REGION') . '.amazonaws.com/'.$fetchedData->client_id.'/'.$fetch->doc_type.'/'.$fetch->myfile;
                                                    ?>
                                                        <div data-id="{{$fetch->id}}"
                                                             data-name="<?php echo $fetch->file_name; ?>"
                                                             data-file-ext="<?= htmlspecialchars($fetch->getPreviewFileExtension(), ENT_QUOTES, 'UTF-8') ?>"
                                                             data-file-url="<?= htmlspecialchars($fileUrl, ENT_QUOTES, 'UTF-8') ?>"
                                                             data-doc-type="<?= htmlspecialchars((string) $fetch->doc_type, ENT_QUOTES, 'UTF-8') ?>"
                                                             data-file-status="<?= htmlspecialchars((string) ($fetch->status ?? 'draft'), ENT_QUOTES, 'UTF-8') ?>"
                                                             class="doc-row"
                                                             title="Uploaded by: <?php echo ($admin->first_name ?? 'NA'); ?> on <?php echo date('d/m/Y H:i', strtotime($fetch->created_at)); ?>"
                                                             oncontextmenu="showNotUsedFileContextMenu(event, <?= (int) $fetch->id ?>, this.getAttribute('data-file-ext'), this.getAttribute('data-file-url'), this.getAttribute('data-doc-type'), this.getAttribute('data-file-status')); return false;">
                                                            <?php if( isset($fetch->myfile_key) && $fetch->myfile_key != ""){ //For new file upload ?>
                                                                <a href="javascript:void(0);" onclick="previewFile('<?php echo $fetch->getPreviewFileExtension();?>','<?php echo $fetch->myfile; ?>','preview-container-notuseddocumnetlist')">
                                                                    {!! \App\Helpers\IconHelper::fromLegacy('fas fa-file-image') !!} <span><?php echo $fetch->getFilenameWithExtensionForDisplay(); ?></span>
                                                                </a>
                                                            <?php } else {  //For old file upload
                                                                $url = 'https://'.env('AWS_BUCKET').'.s3.'. env('AWS_DEFAULT_REGION') . '.amazonaws.com/';
                                                                ?>
                                                                <a href="javascript:void(0);" onclick="previewFile('<?php echo $fetch->getPreviewFileExtension();?>','<?php echo $myawsfile; ?>','preview-container-notuseddocumnetlist')">
                                                                    {!! \App\Helpers\IconHelper::fromLegacy('fas fa-file-image') !!} <span><?php echo $fetch->getFilenameWithExtensionForDisplay(); ?></span>
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
            {{-- Handlers live on window from notuseddocuments-tab.js (always loaded).
                 Do not redefine them here: lazy inject wraps this script in an IIFE and
                 a second currentNotUsedContextFile would break Preview/Delete/Back To Document. --}}
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

            <style>
                #notUsedFileContextMenu .context-menu-item:hover {
                    background-color: #f8f9fa;
                }
            </style>

