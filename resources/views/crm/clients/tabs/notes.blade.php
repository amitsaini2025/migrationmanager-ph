            <!-- Notes Tab -->
            @php
                $notelist = $clientNotes ?? \App\Models\Note::where('client_id', $fetchedData->id)
                    ->whereNull('assigned_to')
                    ->where('type', 'client')
                    ->with('user')
                    ->orderby('pin', 'DESC')
                    ->orderBy('updated_at', 'DESC')
                    ->get();
                $matterNotesCount = $notelist->filter(fn ($n) => !empty($n->matter_id))->count();
                $leadNotesCount = $notelist->filter(fn ($n) => empty($n->matter_id))->count();
                $showNotesScopeTabs = $matterNotesCount > 0 && $leadNotesCount > 0;
            @endphp
            <div class="tab-pane" id="noteterm-tab"
                data-has-matter-notes="{{ $matterNotesCount > 0 ? '1' : '0' }}"
                data-has-lead-notes="{{ $leadNotesCount > 0 ? '1' : '0' }}">
                <div class="card full-width notes-container">
                    <div class="notes-header">
                        <h3>@icon('fa-file-alt') Notes</h3>
                        <button class="btn btn-primary btn-sm create_note_d" datatype="note">
                            @icon('fa-plus') Add Note
                        </button>
                    </div>

                    <!-- Search Filter -->
                    <div class="notes-search-container" style="margin: 10px 0 0 10px; padding: 10px 0;">
                        <div class="input-group" style="max-width: 400px;">
                            <div class="input-group-prepend">
                                <span class="input-group-text" style="background: #f8f9fa; border-right: none;">
                                    @icon('fa-search')
                                </span>
                            </div>
                            <input type="text" id="notes-search-input" class="form-control" placeholder="Search notes..." style="border-left: none;">
                        </div>
                    </div>

                    <!-- Matter Specific / Lead Notes scope tabs (Case 3 only) -->
                    <div class="notes-scope-tabs-container" style="margin: 10px 0 0 10px; padding: 10px 0;{{ $showNotesScopeTabs ? '' : ' display: none;' }}">
                        <nav class="notes-scope-pills note-pills" style="display: flex; gap: 10px;">
                            <button type="button" class="notes-scope-tab pill-tab active" data-notes-scope="matter">Matter Specific</button>
                            <button type="button" class="notes-scope-tab pill-tab" data-notes-scope="lead">Lead Notes</button>
                        </nav>
                    </div>

                    <!-- Redesigned Tabs (Hidden) -->
                    <div class="subtab-header-container" style="display: none;">
                        <nav class="subtabs8 note-pills" style="margin: 10px 0 0 10px; display: flex; gap: 10px;">
                            <button class="subtab8-button pill-tab active" data-subtab8="All">All</button>
                            <button class="subtab8-button pill-tab" data-subtab8="Call">Call</button>
                            <button class="subtab8-button pill-tab" data-subtab8="Email">Email</button>
                            <button class="subtab8-button pill-tab" data-subtab8="In-Person">In-Person</button>
                            <button class="subtab8-button pill-tab" data-subtab8="Others">Others</button>
                            <button class="subtab8-button pill-tab" data-subtab8="Attention">Attention</button>
                        </nav>
                    </div>

                    <style>
                        .note-pills .pill-tab {
                            border-radius: 999px;
                            padding: 8px 22px;
                            border: none;
                            background: #f1f5f9;
                            color: #333;
                            font-weight: 500;
                            font-size: 1rem;
                            transition: background 0.2s, color 0.2s;
                        }
                        .note-pills .pill-tab.active {
                            background: #2563eb;
                            color: #fff;
                        }
                        .note-pills .pill-tab:not(.active):hover {
                            background: #e0e7ef;
                        }
                        .note-card-redesign {
                            background: #ffffff;
                            border-radius: 16px;
                            box-shadow: 0 2px 12px rgba(0,0,0,0.1);
                            padding: 24px 28px 20px 28px;
                            margin-bottom: 18px;
                            border: 1px solid #e0e0e0;
                            position: relative;
                            overflow: visible;
                        }
                        .note-card-redesign .dropdown-menu {
                            z-index: 1000;
                            position: absolute;
                            right: 0;
                            left: auto;
                        }
                        .note-type-label {
                            display: inline-block;
                            font-size: 0.75rem;
                            font-weight: 600;
                            border-radius: 12px;
                            padding: 4px 12px;
                            margin-bottom: 0;
                        }
                        .note-type-inperson { background: #e6f4ea; color: #219653; }
                        .note-type-call { background: #e3f0fd; color: #2563eb; }
                        .note-type-email { background: #fdeaea; color: #e74c3c; }
                        .note-type-attention { background: #f3e8ff; color: #8e44ad; }
                        .note-type-others { background: #f5f5f5; color: #888; }
                        .note-type-uncategorized { background: #fff3cd; color: #856404; }
                        .note-title {
                            font-size: 1.18rem;
                            font-weight: 700;
                            color: #22223b;
                            margin-bottom: 2px;
                        }
                        .note-meta-redesign {
                            font-size: 0.97rem;
                            color: #6c757d;
                            margin-bottom: 8px;
                        }
                        .note-content-redesign {
                            color: #1a1a1a;
                            font-size: 1.15rem;
                            line-height: 1.6;
                            margin-top: 0;
                            margin-bottom: 0;
                        }
                        .note-content-redesign p {
                            color: #1a1a1a;
                        }
                        .viewnote {
                            color: #2563eb;
                            font-size: 0.97rem;
                            text-decoration: underline;
                            cursor: pointer;
                        }
                        .author-name-created {
                            font-size: 0.85rem;
                            color: #1a1a1a;
                            font-weight: 500;
                        }
                        .note-type-inline {
                            font-weight: 700;
                            font-size: 0.85rem;
                            margin-left: 4px;
                        }
                        .note-type-inline.call { color: #2563eb; }
                        .note-type-inline.email { color: #e74c3c; }
                        .note-type-inline.inperson { color: #219653; }
                        .note-type-inline.attention { color: #8e44ad; }
                        .note-type-inline.others { color: #888; }
                        .date-time-menu-container {
                            position: absolute;
                            top: 22px;
                            right: 0px;
                            display: flex;
                            align-items: center;
                            gap: 8px;
                            z-index: 10;
                        }
                        .author-updated-date-time {
                            font-size: 0.75rem;
                            color: #6c757d;
                            line-height: 1.2;
                            white-space: nowrap;
                        }
                        .note-card-info {
                            display: flex;
                            flex-direction: row;
                            align-items: center;
                            gap: 0;
                            margin-top: 0;
                            margin-bottom: 12px;
                            padding-top: 0;
                            padding-bottom: 12px;
                            border-bottom: 1px solid #e0e0e0;
                            padding-right: 150px;
                            line-height: 1.2;
                        }
                        .note-category-top {
                            position: absolute;
                            top: 18px;
                            right: 50px;
                        }
                        .note-toggle-btn-div {
                            display: flex;
                            align-items: center;
                            line-height: 1.2;
                        }
                        .note-toggle-btn-div .btn-link {
                            padding: 0;
                            color: #6c757d;
                            font-size: 0.75rem;
                            line-height: 1.2;
                            vertical-align: baseline;
                            display: flex;
                            align-items: center;
                        }
                        .note-toggle-btn-div .fa-ellipsis-v {
                            font-size: 0.75rem;
                            vertical-align: baseline;
                        }
                        .note-toggle-btn-div-type {
                            display:inline-grid;
                            width: 133px;
                        }
                        .pined_note {
                            position: absolute;
                            top: 24px;
                            right: 180px;
                            z-index: 5;
                            display: inline-flex;
                            align-items: center;
                            padding: 4px 8px;
                            background: #e3f0fd;
                            border-radius: 6px;
                            border: 1px solid #2563eb;
                        }
                        .pined_note i {
                            color: #2563eb;
                            font-size: 0.9rem;
                        }
                        .note-card-redesign.pinned {
                            border-left: 3px solid #2563eb;
                        }
                    </style>

                    <!-- Notes List -->
                    <div class="note_term_list subtab8-content">
                        @foreach($notelist as $list)
                            @php
                            $admin = $list->user;
                            if($list->task_group === null || $list->task_group === '') {
                                $typeLabel = 'Others';
                                $typeClass = 'note-type-others';
                                $typeInlineClass = 'others';
                            } else {
                                $type = strtolower($list->task_group);
                                $typeLabel = 'Others';
                                $typeClass = 'note-type-others';
                                $typeInlineClass = 'others';

                                if(strpos($type, 'call') !== false) { 
                                    $typeLabel = 'Call'; 
                                    $typeClass = 'note-type-call'; 
                                    $typeInlineClass = 'call';
                                }
                                else if(strpos($type, 'email') !== false) { 
                                    $typeLabel = 'Email'; 
                                    $typeClass = 'note-type-email'; 
                                    $typeInlineClass = 'email';
                                }
                                else if(strpos($type, 'in-person') !== false) { 
                                    $typeLabel = 'In-Person'; 
                                    $typeClass = 'note-type-inperson'; 
                                    $typeInlineClass = 'inperson';
                                }
                                else if(strpos($type, 'others') !== false) { 
                                    $typeLabel = 'Others'; 
                                    $typeClass = 'note-type-others'; 
                                    $typeInlineClass = 'others';
                                }
                                else if(strpos($type, 'attention') !== false) { 
                                    $typeLabel = 'Attention'; 
                                    $typeClass = 'note-type-attention'; 
                                    $typeInlineClass = 'attention';
                                }
                            }
                            @endphp
                        <div class="note-card-redesign @if($list->pin == 1) pinned @endif" data-matterid="{{ $list->matter_id }}" id="note_id_{{$list->id}}" data-id="{{$list->id}}" data-type="{{ $typeLabel }}">
                            @if($list->pin == 1)
                                <div class="pined_note">
                                    @icon('fa-thumb-tack')
                                </div>
                            @endif

                            <div class="date-time-menu-container">
                                <span class="author-updated-date-time">{{date('d/m/Y h:i A', strtotime($list->updated_at))}}</span>
                                <div class="note-toggle-btn-div">
                                    <div class="dropdown">
                                        <button class="btn btn-link dropdown-toggle note-toggle-btn-div-type" type="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                            @icon('fa-ellipsis-v')
                                        </button>
                                        <div class="dropdown-menu">
                                            <a class="dropdown-item opennoteform" data-id="{{$list->id}}" href="javascript:;">Edit</a>
                                            @if(Auth::user()->role == 1 || Auth::user()->role == 16)
                                                <a class="dropdown-item editdatetime" data-id="{{$list->id}}" href="javascript:;">Edit Date Time</a>
                                            @endif
                                            <a data-id="{{$list->id}}" data-href="deletenote" class="dropdown-item deletenote" href="javascript:;">Delete</a>
                                            @if($list->pin == 1)
                                                <a data-id="{{$list->id}}" class="dropdown-item pinnote" href="javascript:;">Unpin</a>
                                            @else
                                                <a data-id="{{$list->id}}" class="dropdown-item pinnote" href="javascript:;">Pin</a>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="note-card-info">
                                <span class="author-name-created">{{ $admin->first_name ?? 'NA' }} {{ $admin->last_name ?? 'NA' }} added the</span><span class="note-type-inline {{ $typeInlineClass }}">{{ $typeLabel }} notes</span>
                            </div>
                            @if(!empty(trim((string) ($list->mobile_number ?? ''))))
                                <div class="note-meta-redesign" style="margin-bottom: 10px;">
                                    @icon('fa-phone', ['style' => 'color: #2563eb;'])
                                    <strong style="margin-left: 6px;">Number:</strong> {{ $list->mobile_number }}
                                </div>
                            @endif

                            <div class="note-content-redesign">
                                @if(!empty($list->description))
                                    {!! \App\Support\NoteDescriptionHtml::forDisplay($list->description) !!}
                                @endif
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <script>
            window.isLeadNoteMatterId = function(matterId) {
                return matterId === null || matterId === undefined || matterId === '' || matterId === 'null';
            };

            window.getSelectedMatterForNotes = function() {
                if (typeof $ !== 'undefined' && $('.general_matter_checkbox_client_detail').is(':checked')) {
                    return $('.general_matter_checkbox_client_detail').val() || '';
                }
                const matterSelect = document.getElementById('sel_matter_id_client_detail');
                return matterSelect ? (matterSelect.value || '') : '';
            };

            window.refreshNotesScopeTabs = function() {
                const tabPane = document.getElementById('noteterm-tab');
                const container = document.querySelector('.notes-scope-tabs-container');
                if (!tabPane || !container) {
                    return;
                }

                let matterCount = 0;
                let leadCount = 0;
                document.querySelectorAll('#noteterm-tab .note-card-redesign').forEach(function(card) {
                    if (window.isLeadNoteMatterId(card.getAttribute('data-matterid'))) {
                        leadCount++;
                    } else {
                        matterCount++;
                    }
                });

                const hasMatterNotes = matterCount > 0;
                const hasLeadNotes = leadCount > 0;
                const showScopeTabs = hasMatterNotes && hasLeadNotes;

                tabPane.dataset.hasMatterNotes = hasMatterNotes ? '1' : '0';
                tabPane.dataset.hasLeadNotes = hasLeadNotes ? '1' : '0';
                container.style.display = showScopeTabs ? '' : 'none';

                if (showScopeTabs) {
                    const activeScope = document.querySelector('.notes-scope-tab.pill-tab.active');
                    if (!activeScope) {
                        document.querySelectorAll('.notes-scope-tab.pill-tab').forEach(function(tab) {
                            tab.classList.remove('active');
                        });
                        const matterTab = document.querySelector('.notes-scope-tab[data-notes-scope="matter"]');
                        if (matterTab) {
                            matterTab.classList.add('active');
                        }
                    }
                }
            };

            window.filterNotes = function() {
                window.refreshNotesScopeTabs();

                const tabPane = document.getElementById('noteterm-tab');
                if (!tabPane) {
                    return;
                }

                const hasMatterNotes = tabPane.dataset.hasMatterNotes === '1';
                const hasLeadNotes = tabPane.dataset.hasLeadNotes === '1';
                const showScopeTabs = hasMatterNotes && hasLeadNotes;
                const searchText = document.getElementById('notes-search-input')?.value.toLowerCase().trim() || '';
                const selectedMatter = window.getSelectedMatterForNotes();
                const activeTypeTab = document.querySelector('.subtab8-button.pill-tab.active');
                const type = activeTypeTab ? activeTypeTab.getAttribute('data-subtab8') : 'All';

                let scope = 'matter';
                if (showScopeTabs) {
                    const activeScopeTab = document.querySelector('.notes-scope-tab.pill-tab.active');
                    scope = activeScopeTab ? activeScopeTab.getAttribute('data-notes-scope') : 'matter';
                }

                document.querySelectorAll('#noteterm-tab .note-card-redesign').forEach(function(card) {
                    const cardType = card.getAttribute('data-type');
                    const cardMatter = card.getAttribute('data-matterid');
                    const isLeadNote = window.isLeadNoteMatterId(cardMatter);
                    const typeMatch = (type === 'All' || cardType === type);

                    let scopeMatch = true;
                    if (showScopeTabs) {
                        scopeMatch = (scope === 'lead') ? isLeadNote : !isLeadNote;
                    }

                    let matterMatch = true;
                    if (showScopeTabs && scope === 'lead') {
                        matterMatch = true;
                    } else if (hasLeadNotes && !hasMatterNotes) {
                        matterMatch = true;
                    } else if (selectedMatter && selectedMatter !== '') {
                        matterMatch = (cardMatter == selectedMatter);
                    }

                    let searchMatch = true;
                    if (searchText) {
                        searchMatch = card.textContent.toLowerCase().includes(searchText);
                    }

                    card.style.display = (typeMatch && scopeMatch && matterMatch && searchMatch) ? '' : 'none';
                });
            };

            document.addEventListener('DOMContentLoaded', function() {
                const searchInput = document.getElementById('notes-search-input');
                if (searchInput) {
                    searchInput.addEventListener('input', function() {
                        window.filterNotes();
                    });
                    searchInput.addEventListener('keyup', function() {
                        window.filterNotes();
                    });
                }

                document.querySelectorAll('.notes-scope-tab.pill-tab').forEach(function(tab) {
                    tab.addEventListener('click', function() {
                        document.querySelectorAll('.notes-scope-tab.pill-tab').forEach(function(t) {
                            t.classList.remove('active');
                        });
                        this.classList.add('active');
                        window.filterNotes();
                    });
                });

                document.querySelectorAll('.subtab8-button.pill-tab').forEach(function(tab) {
                    tab.addEventListener('click', function() {
                        document.querySelectorAll('.subtab8-button.pill-tab').forEach(function(t) {
                            t.classList.remove('active');
                        });
                        this.classList.add('active');
                        window.filterNotes();
                    });
                });

                setTimeout(function() {
                    const allTab = document.querySelector('.subtab8-button.pill-tab[data-subtab8="All"]');
                    if (allTab) {
                        document.querySelectorAll('.subtab8-button.pill-tab').forEach(function(t) {
                            t.classList.remove('active');
                        });
                        allTab.classList.add('active');
                    }
                    window.filterNotes();
                }, 200);
                
                // SAFE FIX: Ensure dropdown menus close properly to prevent overlay issues
                $(document).on('click', function(e) {
                    // Close dropdown if clicking outside
                    if (!$(e.target).closest('.note-toggle-btn-div').length && 
                        !$(e.target).closest('.dropdown-menu').length) {
                        $('.note-card-redesign .dropdown-menu').removeClass('show').css('display', 'none');
                        $('.note-card-redesign .dropdown-toggle').attr('aria-expanded', 'false');
                    }
                });
                
                // Close dropdowns when clicking on dropdown items
                $(document).on('click', '.note-card-redesign .dropdown-item', function() {
                    setTimeout(function() {
                        $('.note-card-redesign .dropdown-menu').removeClass('show').css('display', 'none');
                        $('.note-card-redesign .dropdown-toggle').attr('aria-expanded', 'false');
                    }, 100);
                });
            });
            </script>
