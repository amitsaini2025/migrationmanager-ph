/**
 * Client Detail Page - Sidebar Tab Management
 * Dedicated file for handling sidebar navigation tabs
 * Handles tab switching, URL updates, and content visibility
 */

(function($) {
    'use strict';

    // Module state
    const SidebarTabs = {
        clientId: '',
        matterId: '',
        selectedMatter: '',
        initialized: false
    };

    /** Tabs where the right-rail activity feed should be visible (details tabs + dedicated Activity nav). */
    function isActivityFeedTab(tabId) {
        return tabId === 'personaldetails' || tabId === 'companydetails' || tabId === 'activityfeed';
    }

    function ensureActivityFeedLoaded() {
        if (typeof window.loadActivities !== 'function') {
            return;
        }
        if (window.ActivityFeedState && window.ActivityFeedState.fetched) {
            return;
        }
        window.loadActivities({ reset: true });
    }

    function setMainColumnForTab(tabId) {
        if (tabId === 'activityfeed') {
            $('#main-content').hide();
            $('.crm-container').addClass('crm-container--activity-tab');
        } else {
            $('#main-content').show();
            $('.crm-container').removeClass('crm-container--activity-tab');
        }
    }

    /**
     * Initialize sidebar tabs
     * NOTE: This should be called from within $(document).ready() - don't wrap it again
     */
    function init(config) {
        if (SidebarTabs.initialized) {
            return;
        }

        SidebarTabs.clientId = config.clientId;
        SidebarTabs.matterId = config.matterId;
        SidebarTabs.selectedMatter = config.selectedMatter || '';
        
        // Setup event handlers immediately (caller ensures DOM is ready)
        setupTabClickHandlers();
        setupBrowserNavigation();
        activateInitialTab(config.activeTab);
        
        // Hide grid data by default
        $('.grid_data').hide();
        
        SidebarTabs.initialized = true;
    }

    /**
     * Setup tab click handlers
     * @param {Element|jQuery|Document|null} root Limit to nav buttons under this root (after lazy inject).
     */
    function setupTabClickHandlers(root) {
        // IMPORTANT: Attach handlers DIRECTLY to each button element
        // This ensures our handler runs BEFORE any delegated handlers that might stop propagation
        var $scope = root ? $(root) : $(document);
        var $buttons = root
            ? $scope.find('.client-nav-button').addBack('.client-nav-button')
            : $('.client-nav-button');

        $buttons.each(function() {
            const $button = $(this);
            const tabId = $button.data('tab');
            
            // Remove any existing handler on this specific button
            $button.off('click.sidebarTabs');
            
            // Attach handler directly with namespace
            $button.on('click.sidebarTabs', function(e) {
                // Stop event from propagating to other handlers
                e.preventDefault();
                e.stopImmediatePropagation();
                
                if (!tabId) {
                    console.error('[SidebarTabs] No tab ID found on button');
                    return false;
                }
                
                activateTab(tabId);
                return false;
            });
        });
    }

    /**
     * Re-bind inline client-nav-button jumps (e.g. Personal ↔ Not Used) after a tab inject.
     */
    function bindNavButtons(root) {
        setupTabClickHandlers(root || document);
    }

    /**
     * Activate a specific tab
     */
    function activateTab(tabId) {
        // Remove active class from all sidebar buttons and panes
        $('.client-nav-button').removeClass('active');
        $('.tab-pane').removeClass('active');
        
        // Add active class to the clicked button - use exact match with filter to ensure precision
        $('.client-nav-button').filter(function() {
            return $(this).data('tab') === tabId;
        }).addClass('active');
        
        // Show the corresponding tab pane
        const $tabPane = $(`#${tabId}-tab`);
        if ($tabPane.length) {
            $tabPane.addClass('active');
        } else {
            console.error('[SidebarTabs] Tab pane not found:', `#${tabId}-tab`);
        }

        if (tabId === 'personaldetails' && typeof window.ensurePersonalDetailsTabLoaded === 'function') {
            window.ensurePersonalDetailsTabLoaded().catch(function(err) {
                console.error('[SidebarTabs] Failed to load Personal Details tab', err);
            });
        }
        
        // Update URL
        updateUrl(tabId);
        
        // Handle activity feed visibility
        if (isActivityFeedTab(tabId)) {
            $('#activity-feed').show();
            ensureActivityFeedLoaded();
            if (tabId !== 'activityfeed') {
                $('#main-content').css('flex', '1');
            }
            setMainColumnForTab(tabId);
            
            // Adjust Activity Feed height when it becomes visible
            setTimeout(function() {
                if (typeof adjustActivityFeedHeight === 'function') {
                    adjustActivityFeedHeight();
                }
            }, 100);
        } else {
            handleMatterSpecificTab(tabId);
            $('#activity-feed').hide();
            setMainColumnForTab(tabId);
        }
        
        // Handle EOI-ROI tab activation
        if (tabId === 'eoiroi') {
            console.log('[SidebarTabs] EOI-ROI tab activated');
            
            // Load data directly via AJAX (don't rely on window.EoiRoi which might not be loaded yet)
            setTimeout(function() {
                const clientId = window.ClientDetailConfig.encodeId;
                
                if (!clientId) {
                    console.error('[SidebarTabs] No client ID found for EOI-ROI data loading');
                    return;
                }
                
                console.log('[SidebarTabs] Loading EOI data for client:', clientId);
                
                $.ajax({
                    url: `/clients/${clientId}/eoi-roi`,
                    method: 'GET',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(response) {
                        console.log('[SidebarTabs] EOI data loaded successfully:', response);
                        
                        // Always show points summary section
                        $('#points-summary-section').show();
                        
                        // If window.EoiRoi is available, trigger its reload
                        if (typeof window.EoiRoi !== 'undefined' && window.EoiRoi.reload) {
                            console.log('[SidebarTabs] Calling window.EoiRoi.reload()');
                            window.EoiRoi.reload();
                        } else {
                            console.log('[SidebarTabs] window.EoiRoi not available yet, storing data temporarily');
                            // Store data for when eoi-roi.js loads
                            window.tempEoiData = response.data;
                        }
                    },
                    error: function(xhr) {
                        console.error('[SidebarTabs] Failed to load EOI data');
                        console.error('[SidebarTabs] Status:', xhr.status, xhr.statusText);
                        console.error('[SidebarTabs] Response:', xhr.responseText);
                        
                        // Show user-friendly error in the table
                        const errorMsg = xhr.status === 403 ? 
                            'Access denied. Please check your permissions.' :
                            xhr.status === 404 ?
                            'EOI endpoint not found. Please contact support.' :
                            'Failed to load EOI records. Please try refreshing the page.';
                        
                        // Always show points summary section even on error
                        $('#points-summary-section').show();
                        
                        // Try to update the table if it exists
                        const $tbody = $('#eoi-roi-tbody');
                        if ($tbody.length) {
                            $tbody.html(`
                                <tr class="no-data-row">
                                    <td colspan="9" class="text-center text-danger">
                                        ${typeof crmIconAny === 'function' ? crmIconAny('fas fa-exclamation-triangle') : (typeof crmI === 'function' ? crmI('fas fa-exclamation-triangle') : '<i class="fas fa-exclamation-triangle"></i>')} ${errorMsg}
                                        <br><small>Error ${xhr.status}: ${xhr.statusText}</small>
                                    </td>
                                </tr>
                            `);
                        }
                    }
                });
            }, 300);
        }
        
        // Store active tab
        localStorage.setItem('activeTab', tabId);
    }

    /**
     * Update URL without reloading page
     */
    function updateUrl(tabId) {
        let newUrl = '/clients/detail/' + SidebarTabs.clientId;
        if (SidebarTabs.matterId && SidebarTabs.matterId !== '') {
            newUrl += '/' + SidebarTabs.matterId;
        }
        newUrl += '/' + tabId;
        
        window.history.pushState({tab: tabId}, '', newUrl);
    }

    /**
     * Handle matter-specific tab content
     */
    function handleMatterSpecificTab(tabId) {
        // Get selected matter
        SidebarTabs.selectedMatter = $('#sel_matter_id_client_detail').val();

        const activeSubTab = $('.subtab-button.active').data('subtab');

        // Filter content by matter
        switch(tabId) {
            case 'noteterm':
                (typeof window.ensureNotesTabLoaded === 'function'
                    ? window.ensureNotesTabLoaded()
                    : Promise.resolve()
                ).then(function() {
                    ensureAllTabActive();
                    if (typeof window.filterNotes === 'function') {
                        window.filterNotes();
                    } else {
                        filterNotesByMatter(SidebarTabs.selectedMatter);
                    }
                }).catch(function(err) {
                    console.error('[SidebarTabs] Failed to load Notes tab', err);
                });
                break;
            case 'visadocuments':
                (typeof window.ensureVisaDocumentsTabLoaded === 'function'
                    ? window.ensureVisaDocumentsTabLoaded()
                    : Promise.resolve()
                ).then(function() {
                    filterVisaDocumentsByMatter(SidebarTabs.selectedMatter);
                }).catch(function(err) {
                    console.error('[SidebarTabs] Failed to load Visa Documents tab', err);
                });
                // Form 956 PDF downloads on create only (detail-main.js); do not mass-download on tab open/reload
                break;
            case 'personaldocuments':
                if (typeof window.ensurePersonalDocumentsTabLoaded === 'function') {
                    window.ensurePersonalDocumentsTabLoaded().catch(function(err) {
                        console.error('[SidebarTabs] Failed to load Personal Documents tab', err);
                    });
                }
                break;
            case 'notuseddocuments':
                if (typeof window.ensureNotUsedDocumentsTabLoaded === 'function') {
                    window.ensureNotUsedDocumentsTabLoaded().catch(function(err) {
                        console.error('[SidebarTabs] Failed to load Not Used Documents tab', err);
                    });
                }
                break;
            case 'nominationdocuments':
                filterNominationDocumentsByMatter(SidebarTabs.selectedMatter);
                break;
            case 'client_portal':
                (typeof window.ensureClientPortalTabLoaded === 'function'
                    ? window.ensureClientPortalTabLoaded()
                    : Promise.resolve()
                ).then(function() {
                    if (typeof window.ensureStageNavBackButtonVisible === 'function') {
                        window.ensureStageNavBackButtonVisible();
                    }
                    if (typeof window.ensureWorkflowV2StageIcons === 'function') {
                        window.ensureWorkflowV2StageIcons(document.getElementById('client_portal-tab'));
                    }
                }).catch(function(err) {
                    console.error('[SidebarTabs] Failed to load Client Portal tab', err);
                });
                break;
            case 'emails':
                if (typeof window.ensureEmailsTabLoaded === 'function') {
                    window.ensureEmailsTabLoaded().catch(function(err) {
                        console.error('[SidebarTabs] Failed to load Emails tab', err);
                    });
                } else if (typeof window.loadEmails === 'function') {
                    // Company / pages without emails-tab.js: load once, then cache
                    window.loadEmails();
                }
                break;
            case 'workflow':
                (typeof window.ensureWorkflowTabLoaded === 'function'
                    ? window.ensureWorkflowTabLoaded()
                    : Promise.resolve()
                ).then(function() {
                    if (typeof window.ensureStageNavBackButtonVisible === 'function') {
                        window.ensureStageNavBackButtonVisible();
                    }
                    if (typeof window.ensureWorkflowV2StageIcons === 'function') {
                        window.ensureWorkflowV2StageIcons(document.getElementById('workflow-tab'));
                    }
                }).catch(function(err) {
                    console.error('[SidebarTabs] Failed to load Workflow tab', err);
                });
                break;
            case 'account':
                if (typeof window.ensureAccountTabLoaded === 'function') {
                    window.ensureAccountTabLoaded().catch(function(err) {
                        console.error('[SidebarTabs] Failed to load Account tab', err);
                    });
                }
                break;
            case 'checklists':
                if (typeof window.ensureChecklistsTabLoaded === 'function') {
                    window.ensureChecklistsTabLoaded().catch(function(err) {
                        console.error('[SidebarTabs] Failed to load Checklists tab', err);
                    });
                }
                break;
        }
    }

    /**
     * Ensure All tab is active for notes
     */
    function ensureAllTabActive() {
        // Check if any subtab8 button is active
        const $activeTab = $('.subtab8-button.active');
        
        if (!$activeTab.length) {
            // No active tab, make All tab active
            $('.subtab8-button.pill-tab[data-subtab8="All"]').addClass('active');
            console.log('[SidebarTabs] Activated All tab (no active tab found)');
        } else {
            // Check if All tab is already active
            const activeTabType = $activeTab.data('subtab8');
            if (activeTabType !== 'All') {
                // Remove active from current tab and make All tab active
                $('.subtab8-button.pill-tab').removeClass('active');
                $('.subtab8-button.pill-tab[data-subtab8="All"]').addClass('active');
                console.log('[SidebarTabs] Switched to All tab from:', activeTabType);
            }
        }
    }

    /**
     * Filter notes by matter
     */
    function filterNotesByMatter(matterId) {
        if (typeof window.filterNotes === 'function') {
            window.filterNotes();
            return;
        }

        const activeTaskGroup = $('.subtab8-button.active').data('subtab8') || 'All';

        $('#noteterm-tab').find('.note-card-redesign').each(function() {
            const $note = $(this);
            const noteType = $note.data('type');
            const typeMatch = (activeTaskGroup === 'All' || noteType === activeTaskGroup);

            let matterMatch = true;
            if (matterId && matterId !== '') {
                matterMatch = ($note.data('matterid') == matterId);
            }

            if (typeMatch && matterMatch) {
                $note.show();
            } else {
                $note.hide();
            }
        });
    }

    /**
     * Show/hide document rows (and signature action bars) by matter.
     */
    function filterDocumentListRowsByMatter($container, matterId) {
        var rowSelector = '.drow, .visa-sig-action-bar';

        if (matterId !== "") {
            $container.find(rowSelector).each(function() {
                var docMatterId = $(this).data('matterid');
                // Show if: matches the selected matter, OR has no matter ID at all
                // (covers legacy docs uploaded before matter-scoping was introduced,
                // and docs that were inadvertently saved without a matter reference).
                var hasNoMatter = !docMatterId || docMatterId === '' || docMatterId === 'null' || docMatterId === null || docMatterId === 0;
                if (docMatterId == matterId || hasNoMatter) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
        } else {
            $container.find(rowSelector).hide();
        }
    }

    /**
     * Filter visa documents by matter
     */
    function filterVisaDocumentsByMatter(matterId) {
        filterDocumentListRowsByMatter($('#visadocuments-tab .migdocumnetlist1'), matterId);
    }

    function filterNominationDocumentsByMatter(matterId) {
        filterDocumentListRowsByMatter($('#nominationdocuments-tab .migdocumnetlist1'), matterId);
    }

    /**
     * Filter emails by matter
     */
    function filterEmailsByMatter(matterId, folder) {
        const selector = folder === 'inbox' ? '#inbox-subtab #email-list' : '#sent-subtab #email-list1';
        
        if (matterId !== "") {
            $(selector).find('.email-card').each(function() {
                if ($(this).data('matterid') == matterId) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
        } else {
            $(selector).find('.email-card').hide();
        }
    }

    /**
     * Setup browser navigation (back/forward buttons)
     */
    function setupBrowserNavigation() {
        window.addEventListener('popstate', function(event) {
            if (event.state && event.state.tab) {
                activateTab(event.state.tab);
            }
        });
    }

    /**
     * Activate initial tab from URL or default.
     *
     * For "default" tabs whose pane already carries the `active` class from PHP
     * (personaldetails on client pages, companydetails on company pages) we apply
     * the side-effects (feed visibility, main-column visibility) directly instead of
     * triggering a click — this avoids an unwanted pushState history entry on every
     * fresh page load.
     */
    function activateInitialTab(activeTabFromUrl) {
        // Check localStorage first (takes precedence for better UX when returning to page)
        const storedTab = localStorage.getItem('activeTab');
        // Deep-linked tabs in the URL must win over stale localStorage (e.g. /workflow, /client_portal).
        const deepLinkTabs = ['workflow', 'client_portal', 'account', 'emails', 'checklists', 'eoiroi', 'visadocuments', 'personaldocuments', 'noteterm', 'activityfeed'];
        const urlTab = (activeTabFromUrl || '').toLowerCase();
        let tabId;
        if (urlTab && deepLinkTabs.indexOf(urlTab) !== -1) {
            tabId = urlTab;
        } else {
            tabId = storedTab || activeTabFromUrl || 'personaldetails';
        }
        
        // Clear localStorage after reading to prevent stale tab persistence
        if (storedTab) {
            localStorage.removeItem('activeTab');
        }
        
        // Legacy support: redirect deprecated "accounts-test" slug to the new "account" tab
        if (tabId === 'accounts-test') {
            tabId = 'account';
        }
        
        // Legacy support: redirect deprecated "emailhandling" slug to the new "emails" tab
        if (tabId === 'emailhandling') {
            tabId = 'emails';
        }
        
        // Legacy support: Form Generation tab removed — redirect to checklists
        const normalizedTabId = (tabId || '').toLowerCase();
        if (normalizedTabId === 'formgenerations' || normalizedTabId === 'formgenerationsl') {
            tabId = 'checklists';
        }

        // "Default" tabs: the Blade template already marks the pane and button as active.
        // Apply side-effects directly to avoid an unwanted pushState entry.
        const defaultTabs = ['personaldetails', 'companydetails'];
        if (defaultTabs.includes(tabId)) {
            // Eager Personal Details: no-op. Lazy stub (other URL, then restored): fetch once.
            if (tabId === 'personaldetails' && typeof window.ensurePersonalDetailsTabLoaded === 'function') {
                window.ensurePersonalDetailsTabLoaded().catch(function(err) {
                    console.error('[SidebarTabs] Failed to load Personal Details tab', err);
                });
            }
            // The pane is already active from PHP; just apply feed + column visibility.
            if (isActivityFeedTab(tabId)) {
                $('#activity-feed').show();
                ensureActivityFeedLoaded();
                setMainColumnForTab(tabId);
                setTimeout(function() {
                    if (typeof adjustActivityFeedHeight === 'function') {
                        adjustActivityFeedHeight();
                    }
                }, 100);
            } else {
                $('#activity-feed').hide();
                setMainColumnForTab(tabId);
            }
            return;
        }

        // Non-default tab: trigger click so full tab-switching logic runs.
        const $button = $(`.client-nav-button[data-tab="${tabId}"]`);
        if ($button.length) {
            $button.click();
        } else {
            // Try to find a close match (singular vs plural), excluding hyphenated legacy slugs
            const availableTabs = [];
            $('.client-nav-button').each(function() {
                availableTabs.push($(this).data('tab'));
            });
            
            const closeTabs = availableTabs.filter(t => {
                if (t === tabId) return true;
                if (t.includes('-') || tabId.includes('-')) {
                    return false;
                }
                return t.startsWith(tabId) || tabId.startsWith(t);
            });
            
            if (closeTabs.length > 0) {
                $(`.client-nav-button[data-tab="${closeTabs[0]}"]`).click();
            }
        }
    }

    // Expose public API
    window.SidebarTabs = {
        init: init,
        activateTab: activateTab,
        ensureAllTabActive: ensureAllTabActive,
        bindNavButtons: bindNavButtons,
        filterNotesByMatter: filterNotesByMatter,
        filterVisaDocumentsByMatter: filterVisaDocumentsByMatter,
        filterNominationDocumentsByMatter: filterNominationDocumentsByMatter,
        filterEmailsByMatter: filterEmailsByMatter
    };

})(jQuery);

