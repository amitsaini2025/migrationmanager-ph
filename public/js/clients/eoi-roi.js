/**
 * EOI/ROI Management JavaScript
 * Handles CRUD operations for EOI/ROI records and points calculation
 */

(function() {
    'use strict';

    // State management
    const state = {
        clientId: null,
        selectedEoiId: null,
        eoiRecords: [],
        currentPoints: null
    };

    // Initialize when DOM is ready
    $(document).ready(function() {
        console.log('[EOI-ROI] DOM ready, initializing...');
        initEoiRoi();
        // Don't load data immediately - wait for tab activation
    });

    function initEoiRoi() {
        // Get client ID from page configuration
        // NOTE: For API routes, we need the actual database ID, not the encoded ID
        if (window.ClientDetailConfig && window.ClientDetailConfig.clientId) {
            state.clientId = window.ClientDetailConfig.clientId;
            console.log('[EOI-ROI] Client ID detected:', state.clientId);
        } else if (window.ClientDetailConfig && window.ClientDetailConfig.encodeId) {
            // Fallback to encoded ID if clientId not available
            state.clientId = window.ClientDetailConfig.encodeId;
            console.log('[EOI-ROI] Using encoded ID as fallback:', state.clientId);
        } else {
            console.error('[EOI-ROI] Client ID not found - ClientDetailConfig missing');
            return;
        }

        // Initialize components
        initializeEoiStatesMmSelect();
        initializeDatepickers();
        bindEventHandlers();
        
        // Listen for tab activation via sidebar tabs (using correct ID)
        $(document).on('click', '.client-nav-button[data-tab="eoiroi"]', function() {
            console.log('[EOI-ROI] Tab button clicked, loading records...');
            setTimeout(function() {
                if (state.clientId) {
                    loadEoiRecords();
                }
            }, 200);
        });
        
        // Check if tab is already active (e.g., direct URL navigation)
        if ($('#eoiroi-tab').hasClass('active')) {
            console.log('[EOI-ROI] Tab already active on load, loading records...');
            loadEoiRecords();
        }
        
        // Always show points summary section when EOI-ROI tab is initialized
        $('#points-summary-section').show();
        
        // Check if data was pre-loaded by sidebar-tabs.js
        if (window.tempEoiData) {
            console.log('[EOI-ROI] Found pre-loaded data, rendering...');
            state.eoiRecords = window.tempEoiData;
            renderEoiTable();
            delete window.tempEoiData;
        }
        
        console.log('[EOI-ROI] Initialized, waiting for tab activation');
    }

    function initializeEoiStatesMmSelect() {
        $('#eoi-states').mmSelect({
            placeholder: 'Select state(s)',
            allowClear: true,
            width: '100%',
            minimumResultsForSearch: Infinity,
            dropdownAutoWidth: true,
            tags: false,
            tokenSeparators: [','],
            closeOnSelect: false
        });
        
        // Initialize occupation autocomplete
        initializeOccupationAutocomplete();
    }

    function initializeDatepickers() {
        if (typeof flatpickr === 'undefined') {
            console.warn('⚠️ Flatpickr not loaded, skipping datepicker initialization');
            return;
        }

        $('.eoi-datepicker').each(function() {
            const element = this;
            const $this = $(this);
            
            // Skip if already initialized
            if ($this.data('flatpickr')) {
                return;
            }
            
            // Initialize Flatpickr
            flatpickr(element, {
                dateFormat: 'd/m/Y',
                allowInput: true,
                clickOpens: true,
                defaultDate: $this.val() || null,
                locale: {
                    firstDayOfWeek: 1 // Monday
                },
                onChange: function(selectedDates, dateStr, instance) {
                    $this.val(dateStr);
                    $this.trigger('change');
                }
            });
        });
    }

    function bindEventHandlers() {
        // Add new EOI button
        $('#btn-add-eoi').on('click', showAddForm);

        // Form submission
        $('#eoi-roi-form').on('submit', handleFormSubmit);

        // Cancel button
        $('#btn-cancel-eoi').on('click', hideForm);

        // Delete button
        $('#btn-delete-eoi').on('click', handleDelete);

        // EOI reference link click
        $(document).on('click', '.eoi-ref-link', handleEoiRefClick);

        // Password toggle
        $('#toggle-password').on('click', togglePasswordVisibility);

        // Points refresh
        $('#btn-refresh-points').on('click', refreshPoints);

        // Points subclass selector
        $('#points-subclass-selector').on('change', handleSubclassChange);

        // OLD: Map to Visa Documents buttons - REMOVED (now using compose modal)
        // $(document).on('click', '.eoi-map-doc-btn', function() {
            // const categoryName = $(this).data('visa-category');
            // const eoiNumber = $('#eoi-number').val() ? $('#eoi-number').val().trim() : '';
            // const $visaTab = $('.client-nav-button[data-tab="visadocuments"]');
            // if ($visaTab.length) {
            //     $visaTab[0].click();
            //     setTimeout(function() {
            //         const perEoiName = eoiNumber ? (categoryName + ' - ' + eoiNumber) : null;
            //         let $subtab = perEoiName ? $('.subtab6-button').filter(function() {
            //             return $(this).text().trim().toLowerCase() === perEoiName.toLowerCase();
            //         }) : $();
            //         if (!$subtab.length) {
            //             $subtab = $('.subtab6-button').filter(function() {
            //                 return $(this).text().trim().toLowerCase() === (categoryName || '').toLowerCase();
            //             });
            //         }
            //         if ($subtab.length) {
            //             $subtab[0].click();
            //         }
            //     }, 400);
            // }
        // });
    }

    // Load EOI records from API
    function loadEoiRecords() {
        if (!state.clientId) {
            console.error('Client ID not found');
            return;
        }

        const url = `/clients/${state.clientId}/eoi-roi`;

        $.ajax({
            url: url,
            method: 'GET',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                if (response.success) {
                    state.eoiRecords = response.data;
                    renderEoiTable();
                    
                    // Always show points section and load points calculation
                    $('#points-summary-section').show();
                    loadPoints();
                }
            },
            error: function(xhr) {
                console.error('Error loading EOI records:', xhr);
                showNotification('Error loading EOI records', 'error');
            }
        });
    }

    // Render EOI table
    function renderEoiTable() {
        const tbody = $('#eoi-roi-tbody');
        tbody.empty();

        if (state.eoiRecords.length === 0) {
            tbody.html(`
                <tr class="no-data-row">
                    <td colspan="8" class="text-center">
                        ${typeof crmIconLegacy === 'function' ? crmIconLegacy('fas fa-info-circle') : '<i class="fas fa-info-circle"></i>'} No EOI/ROI records found. Click "Add New EOI" to get started.
                    </td>
                </tr>
            `);
            return;
        }

        state.eoiRecords.forEach(function(eoi) {
            const row = $('<tr>').attr('data-eoi-id', eoi.id);
            
            row.html(`
                <td><a href="#" class="eoi-ref-link" data-eoi-id="${eoi.id}">${eoi.eoi_number || 'N/A'}</a></td>
                <td>${eoi.formatted_subclasses || 'N/A'}</td>
                <td>${eoi.formatted_states || 'N/A'}</td>
                <td>${eoi.occupation || 'N/A'}</td>
                <td><strong>${eoi.points || 0}</strong></td>
                <td>${eoi.submission_date || 'N/A'}</td>
                <td>${eoi.roi || 'N/A'}</td>
                <td><span class="badge-status ${eoi.status}">${capitalizeFirst(eoi.status)}</span></td>
            `);
            
            tbody.append(row);
        });
    }

    // Show add form
    function showAddForm() {
        resetForm();
        $('#form-title').text('Add New EOI Record');
        $('#btn-delete-eoi').hide();
        $('#eoi-roi-form-section').slideDown();
        state.selectedEoiId = null;
    }

    // Hide form
    function hideForm() {
        $('#eoi-roi-form-section').slideUp();
        $('#workflow-section-compact').slideUp();
        resetForm();
    }

    // Reset form
    function resetForm() {
        $('#eoi-roi-form')[0].reset();
        $('#eoi-id').val('');
        $('#eoi-states').val(null).trigger('change');
        $('input[name="eoi_subclasses[]"]').prop('checked', false);
        clearOccupationSelection();
        state.selectedEoiId = null;
    }

    // Handle EOI reference click
    function handleEoiRefClick(e) {
        e.preventDefault();
        const link = $(e.currentTarget);
        const eoiId = link.data('eoi-id');
        
        loadEoiRecord(eoiId);
    }

    // Load single EOI record
    function loadEoiRecord(eoiId) {
        const url = `/clients/${state.clientId}/eoi-roi/${eoiId}`;

        $.ajax({
            url: url,
            method: 'GET',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                if (response.success) {
                    populateForm(response.data);
                    $('#form-title').text('Edit EOI Record');
                    $('#btn-delete-eoi').show();
                    $('#eoi-roi-form-section').slideDown();
                    state.selectedEoiId = eoiId;
                    
                    // Load workflow section
                    loadWorkflowSection(eoiId);
                }
            },
            error: function(xhr) {
                console.error('Error loading EOI record:', xhr);
                showNotification('Error loading EOI record', 'error');
            }
        });
    }

    // Populate form with data
    function populateForm(data) {
        $('#eoi-id').val(data.id);
        $('#eoi-number').val(data.eoi_number);
        
        // Handle occupation data
        if (data.occupation) {
            $('#eoi-occupation').val(data.occupation);
            // Populate or clear hidden fields - avoid stale values when switching records
            if (data.anzsco_occupation_id) {
                $('#eoi-anzsco-id').val(data.anzsco_occupation_id);
                $('#eoi-anzsco-code').val(data.anzsco_code || '');
            } else {
                $('#eoi-anzsco-id').val('');
                $('#eoi-anzsco-code').val('');
                // Fallback: try to resolve from occupation text (e.g. "254412 - Title")
                resolveOccupationFromDisplayText(data.occupation);
            }
        } else {
            $('#eoi-anzsco-id').val('');
            $('#eoi-anzsco-code').val('');
        }
        
        $('#eoi-points').val(data.points);
        $('#eoi-submission-date').val(data.submission_date);
        $('#eoi-invitation-date').val(data.invitation_date);
        $('#eoi-nomination-date').val(data.nomination_date);
        $('#eoi-roi').val(data.roi);
        $('#eoi-status').val(data.status);
        $('#eoi-password').val(data.password || '');

        // Set subclasses
        $('input[name="eoi_subclasses[]"]').prop('checked', false);
        if (data.eoi_subclasses) {
            data.eoi_subclasses.forEach(function(subclass) {
                $(`input[name="eoi_subclasses[]"][value="${subclass}"]`).prop('checked', true);
            });
        }

        // Set states
        if (data.eoi_states) {
            $('#eoi-states').val(data.eoi_states).trigger('change');
        }
    }

    // Handle form submission
    function handleFormSubmit(e) {
        e.preventDefault();

        // Validate at least one subclass is selected
        const checkedSubclasses = $('input[name="eoi_subclasses[]"]:checked');
        if (checkedSubclasses.length === 0) {
            showNotification('Please select at least one subclass', 'error');
            return false;
        }

        // Validate at least one state is selected
        const selectedStates = $('#eoi-states').val();
        if (!selectedStates || selectedStates.length === 0) {
            showNotification('Please select at least one state', 'error');
            return false;
        }

        // Validate occupation is selected from database
        const occupationInput = $('#eoi-occupation');
        const anzscoId = $('#eoi-anzsco-id').val();
        if (!occupationInput.val() || !anzscoId) {
            showNotification('Please select a valid occupation from the dropdown', 'error');
            occupationInput.focus();
            return false;
        }

        const formData = new FormData($('#eoi-roi-form')[0]);
        const url = `/clients/${state.clientId}/eoi-roi`;

        // Show loading
        $('#btn-save-eoi').prop('disabled', true).html((typeof crmIconLegacy === 'function' ? crmIconLegacy('fas fa-spinner fa-spin') : '<i class="fas fa-spinner fa-spin"></i>') + ' Saving...');

        $.ajax({
            url: url,
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                if (response.success) {
                    showNotification(response.message, 'success');
                    hideForm();
                    loadEoiRecords();
                    loadPoints(); // Refresh points
                }
            },
            error: function(xhr) {
                console.error('Error saving EOI record:', xhr);
                let errorMsg = 'Error saving EOI record';
                
                if (xhr.responseJSON && xhr.responseJSON.errors) {
                    const errors = Object.values(xhr.responseJSON.errors).flat();
                    errorMsg = errors.join('<br>');
                } else if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                }
                
                showNotification(errorMsg, 'error');
            },
            complete: function() {
                $('#btn-save-eoi').prop('disabled', false).html((typeof crmIconLegacy === 'function' ? crmIconLegacy('fas fa-save') : '<i class="fas fa-save"></i>') + ' Save EOI');
            }
        });

        return false;
    }

    // Handle delete
    function handleDelete() {
        if (!state.selectedEoiId) {
            showNotification('No EOI record selected', 'error');
            return;
        }

        if (!confirm('Are you sure you want to delete this EOI record? This action cannot be undone.')) {
            return;
        }

        const url = `/clients/${state.clientId}/eoi-roi/${state.selectedEoiId}`;

        $.ajax({
            url: url,
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                if (response.success) {
                    showNotification(response.message, 'success');
                    hideForm();
                    loadEoiRecords();
                    loadPoints();
                }
            },
            error: function(xhr) {
                console.error('Error deleting EOI record:', xhr);
                showNotification('Error deleting EOI record', 'error');
            }
        });
    }

    // Load points calculation
    function loadPoints(subclass) {
        if (!state.clientId) {
            console.error('[EOI-ROI] Cannot load points - client ID not set');
            return;
        }

        subclass = subclass || $('#points-subclass-selector').val() || '189';

        const url = `/clients/${state.clientId}/eoi-roi/calculate-points?subclass=${subclass || ''}`;
        console.log('[EOI-ROI] Loading points from URL:', url);

        $('#points-summary-content').html('<div class="text-center text-muted">' + (typeof crmIconLegacy === 'function' ? crmIconLegacy('fas fa-spinner fa-spin') : '<i class="fas fa-spinner fa-spin"></i>') + ' Calculating points...</div>');

        $.ajax({
            url: url,
            method: 'GET',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                console.log('[EOI-ROI] Points calculation response:', response);
                if (response.success) {
                    state.currentPoints = response.data;
                    renderPointsSummary(response.data);
                } else {
                    console.error('[EOI-ROI] Points calculation failed:', response.message);
                    $('#points-summary-content').html('<div class="text-center text-danger">' + (typeof crmIconLegacy === 'function' ? crmIconLegacy('fas fa-exclamation-triangle') : '<i class="fas fa-exclamation-triangle"></i>') + ' Points calculation failed: ' + (response.message || 'Unknown error') + '</div>');
                }
            },
            error: function(xhr) {
                console.error('[EOI-ROI] Error calculating points:', xhr);
                let errorMsg = 'Error calculating points';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                } else if (xhr.status === 404) {
                    errorMsg = 'Points calculation endpoint not found (404)';
                } else if (xhr.status === 500) {
                    errorMsg = 'Server error during points calculation (500)';
                }
                $('#points-summary-content').html('<div class="text-center text-danger">' + (typeof crmIconLegacy === 'function' ? crmIconLegacy('fas fa-exclamation-triangle') : '<i class="fas fa-exclamation-triangle"></i>') + ' ' + errorMsg + '</div>');
            }
        });
    }

    // Render points summary
    function renderPointsSummary(data) {
        const content = $('#points-summary-content');
        
        // Map category names to display names
        const categoryNames = {
            'age': 'Age',
            'english': 'English Test',
            'education': 'Education',
            'australian_work_experience': 'Australian Work Experience',
            'overseas_work_experience': 'Overseas Work Experience',
            'australian_study': 'Australian Study',
            'specialist_education': 'Specialist Education',
            'regional_study': 'Regional Study',
            'naati_ccl': 'NAATI/CCL',
            'professional_year': 'Professional Year',
            'partner': 'Partner Skills',
            'nomination': 'Nomination'
        };

        // Build the main table rows
        let tableRows = '';
        for (const [category, info] of Object.entries(data.breakdown)) {
            const displayCategory = categoryNames[category] || category.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase());
            const details = info.detail || 'Not claimed';
            
            tableRows += `
                <tr>
                    <td class="points-category">${displayCategory}</td>
                    <td class="points-details">${details}</td>
                    <td class="points-value">${info.points}</td>
                </tr>
            `;
        }

        // Build warnings section
        let warningsHtml = '';
        if (data.warnings && data.warnings.length > 0) {
            warningsHtml += '<div class="points-warnings-title">Upcoming Changes</div>';
            
            data.warnings.forEach(function(warning) {
                const iconClass = warning.severity === 'high' ? 'fas fa-exclamation-triangle' : 'fas fa-info-circle';
                warningsHtml += `
                    <div class="points-warning severity-${warning.severity}">
                        ${typeof crmIconLegacy === 'function' ? crmIconLegacy(iconClass, { class: 'points-warning-icon' }) : '<i class="' + iconClass + ' points-warning-icon"></i>'}
                        ${warning.message}
                    </div>
                `;
            });
        }

        const html = `
            <div class="points-summary-layout">
                <div class="points-summary-main">
                    <table class="points-table">
                        <thead>
                            <tr>
                                <th>Category</th>
                                <th>Details</th>
                                <th>Points</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${tableRows}
                        </tbody>
                    </table>
                </div>
                
                <div class="points-summary-sidebar">
                    <div class="points-total-display">
                        <div class="points-total-number">${data.total}</div>
                        <div class="points-total-label">Current total</div>
                    </div>
                    
                    ${warningsHtml}
                    
                    <div class="points-info-text">
                        Points calculated based on current client data from Personal Details, Qualifications, Experience, and Test Scores sections.
                    </div>
                </div>
            </div>
        `;

        content.html(html);
    }

    // Refresh points
    function refreshPoints() {
        loadPoints();
    }

    // Handle subclass change
    function handleSubclassChange() {
        const subclass = $('#points-subclass-selector').val();
        loadPoints(subclass);
    }

    // Toggle password visibility
    function togglePasswordVisibility() {
        const input = $('#eoi-password');
        const btn = $('#toggle-password');

        if (input.attr('type') === 'password') {
            input.attr('type', 'text');
            btn.html(typeof crmIconLegacy === 'function' ? crmIconLegacy('fas fa-eye-slash') : '<i class="fas fa-eye-slash"></i>');
        } else {
            input.attr('type', 'password');
            btn.html(typeof crmIconLegacy === 'function' ? crmIconLegacy('fas fa-eye') : '<i class="fas fa-eye"></i>');
        }
    }

    // Show notification
    function showNotification(message, type) {
        type = type || 'info';
        
        // Use toastr if available
        if (typeof toastr !== 'undefined') {
            toastr[type](message);
        } else {
            alert(message);
        }
    }

    // Capitalize first letter
    function capitalizeFirst(str) {
        if (!str) return '';
        return str.charAt(0).toUpperCase() + str.slice(1);
    }

    // Initialize occupation autocomplete
    function initializeOccupationAutocomplete() {
        const occupationInput = $('#eoi-occupation');
        const autocompleteContainer = occupationInput.closest('.form-group').find('.autocomplete-items');
        
        let searchTimeout;
        
        occupationInput.on('input', function() {
            const query = $(this).val().trim();
            
            // Clear previous timeout
            if (searchTimeout) {
                clearTimeout(searchTimeout);
            }
            
            // Clear autocomplete if input is empty
            if (query.length === 0) {
                autocompleteContainer.hide();
                clearOccupationSelection();
                return;
            }
            
            // If occupation text matches "CODE - Title" and anzsco-id is empty (e.g. after undo),
            // try to restore the hidden fields - skip search dropdown
            if (!$('#eoi-anzsco-id').val() && /^\d{4,6}\s*-\s*.+/.test(query)) {
                searchTimeout = setTimeout(() => resolveOccupationFromDisplayText(query), 150);
                return;
            }
            
            // Debounce search - wait 300ms after user stops typing
            searchTimeout = setTimeout(() => {
                if (query.length >= 2) {
                    searchOccupations(query, autocompleteContainer[0], $(this));
                }
            }, 300);
        });
        
        occupationInput.on('focus', function() {
            const query = $(this).val().trim();
            if (query.length >= 2) {
                autocompleteContainer.show();
            }
        });
        
        // Hide autocomplete when clicking outside
        $(document).on('click', function(e) {
            if (!$(e.target).closest('.form-group').length) {
                autocompleteContainer.hide();
            }
        });
        
        // Handle keyboard navigation
        occupationInput.on('keydown', function(e) {
            const items = autocompleteContainer.find('.autocomplete-item');
            const selected = autocompleteContainer.find('.autocomplete-item.selected');
            
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                if (selected.length) {
                    selected.removeClass('selected');
                    selected.next().addClass('selected');
                } else {
                    items.first().addClass('selected');
                }
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                if (selected.length) {
                    selected.removeClass('selected');
                    selected.prev().addClass('selected');
                } else {
                    items.last().addClass('selected');
                }
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (selected.length) {
                    selected.click();
                }
            } else if (e.key === 'Escape') {
                autocompleteContainer.hide();
            }
        });
    }
    
    // Search occupations via API
    async function searchOccupations(query, autocompleteContainer, inputElement) {
        try {
            // Show loading indicator
            autocompleteContainer.innerHTML = '<div class="autocomplete-item"><span class="anzsco-loading"></span> Searching...</div>';
            autocompleteContainer.style.display = 'block';
            
            const response = await fetch(`/anzsco/search?q=${encodeURIComponent(query)}`, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                credentials: 'same-origin'
            });
            
            if (!response.ok) {
                throw new Error(`Search failed: ${response.status} ${response.statusText}`);
            }
            
            const occupations = await response.json();
            
            if (occupations.length === 0) {
                autocompleteContainer.innerHTML = '<div class="autocomplete-item text-muted">No occupations found</div>';
                return;
            }
            
            // Build autocomplete items
            autocompleteContainer.innerHTML = '';
            occupations.forEach(occ => {
                const item = document.createElement('div');
                item.className = 'autocomplete-item';
                
                item.innerHTML = `
                    <div>
                        <span class="anzsco-code">${occ.anzsco_code}</span> - 
                        <span class="anzsco-title">${occ.occupation_title}</span>
                    </div>
                `;
                
                item.addEventListener('click', function() {
                    fillOccupationData(inputElement, occ);
                    autocompleteContainer.innerHTML = '';
                    autocompleteContainer.style.display = 'none';
                });
                
                autocompleteContainer.appendChild(item);
            });
            
        } catch (error) {
            console.error('Occupation search error:', error);
            autocompleteContainer.innerHTML = '<div class="autocomplete-item text-danger">Error searching occupations. Please try again.</div>';
        }
    }
    
    /**
     * Resolve occupation from display text (e.g. "254412 - Registered Nurse (Aged Care)").
     * Extracts ANZSCO code and fetches occupation by code to populate hidden fields.
     * Fixes legacy records and undo-after-clear scenarios.
     */
    async function resolveOccupationFromDisplayText(displayText) {
        const m = (displayText || '').trim().match(/^(\d{4,6})\s*-\s*.+/);
        if (!m) return;
        const code = m[1];
        try {
            const response = await fetch(`/anzsco/code/${encodeURIComponent(code)}`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                credentials: 'same-origin'
            });
            if (response.ok) {
                const json = await response.json();
                if (json.success && json.data) {
                    $('#eoi-anzsco-id').val(json.data.id);
                    $('#eoi-anzsco-code').val(json.data.anzsco_code || code);
                }
            }
        } catch (err) {
            console.warn('[EOI-ROI] Could not resolve occupation from text:', displayText, err);
        }
    }

    // Fill occupation data into form fields
    function fillOccupationData(inputElement, occupationData) {
        if (!inputElement) return;
        
        // Fill occupation name with code for display
        const displayText = `${occupationData.anzsco_code} - ${occupationData.occupation_title}`;
        inputElement.val(displayText);
        inputElement.addClass('from-database');
        inputElement.data('anzsco-id', occupationData.id);
        
        // Fill hidden fields
        $('#eoi-anzsco-id').val(occupationData.id);
        $('#eoi-anzsco-code').val(occupationData.anzsco_code);
        
        console.log('[EOI-ROI] Occupation selected:', occupationData.occupation_title, occupationData.anzsco_code);
    }
    
    // Clear occupation selection
    function clearOccupationSelection() {
        $('#eoi-anzsco-id').val('');
        $('#eoi-anzsco-code').val('');
        $('#eoi-occupation').removeClass('from-database').removeData('anzsco-id');
    }

    // Export for external access if needed
    window.EoiRoi = {
        reload: function() {
            console.log('[EOI-ROI] Manual reload triggered');
            loadEoiRecords();
        },
        refreshPoints: function() {
            console.log('[EOI-ROI] Manual refresh points triggered');
            loadPoints();
        },
        init: initEoiRoi
    };

    // Load workflow status and actions
    function loadWorkflowSection(eoiId) {
        console.log('[EOI-ROI] Loading workflow for EOI:', eoiId);
        
        const eoi = state.eoiRecords.find(e => e.id == eoiId);
        if (!eoi) {
            console.warn('[EOI-ROI] EOI not found in state');
            return;
        }

        $.ajax({
            url: `/clients/${state.clientId}/eoi-roi/${eoiId}`,
            method: 'GET',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                if (response.success) {
                    renderWorkflowSection(response.data);
                    $('#workflow-section-compact').show();
                }
            },
            error: function(xhr) {
                console.error('Error loading workflow:', xhr);
            }
        });
    }

    // Render workflow section (compact version)
    function renderWorkflowSection(eoi) {
        const workflowContent = $('#workflow-content-compact');
        
        let html = '';

        // Staff Verification Box
        if (!eoi.staff_verified) {
            html += `
                <div class="workflow-box warning">
                    <h5>${typeof crmIconLegacy === 'function' ? crmIconLegacy('fas fa-user-shield') : '<i class="fas fa-user-shield"></i>'} Staff Verification Required</h5>
                    <p style="margin-bottom: 10px;">This EOI needs to be verified by staff before sending to the client.</p>
                    <div class="workflow-actions">
                        <button type="button" class="btn btn-success btn-sm" id="btn-verify-eoi" data-eoi-id="${eoi.id}">
                            ${typeof crmIconLegacy === 'function' ? crmIconLegacy('fas fa-check-circle') : '<i class="fas fa-check-circle"></i>'} Verify EOI Details
                        </button>
                    </div>
                </div>
            `;
        } else {
            html += `
                <div class="workflow-box success">
                    <h5>${typeof crmIconLegacy === 'function' ? crmIconLegacy('fas fa-check-circle') : '<i class="fas fa-check-circle"></i>'} Staff Verification Complete</h5>
                    <div class="workflow-detail">
                        <strong>Verified By:</strong>
                        <span>${eoi.verified_by || 'N/A'}</span>
                    </div>
                    <div class="workflow-detail">
                        <strong>Date:</strong>
                        <span>${eoi.verification_date || 'N/A'}</span>
                    </div>
                </div>
            `;
        }

        // Client Confirmation Box
        if (eoi.staff_verified) {
            if (eoi.client_confirmation_status === 'confirmed') {
                html += `
                    <div class="workflow-box success">
                        <h5>${typeof crmIconLegacy === 'function' ? crmIconLegacy('fas fa-check-double') : '<i class="fas fa-check-double"></i>'} Client Confirmed</h5>
                        <div class="workflow-detail">
                            <strong>Confirmed On:</strong>
                            <span>${eoi.client_confirmation_date || 'N/A'}</span>
                        </div>
                        <p class="text-success" style="margin: 0; font-size: 12px;">${typeof crmIconLegacy === 'function' ? crmIconLegacy('fas fa-info-circle') : '<i class="fas fa-info-circle"></i>'} Client confirmed all details are correct.</p>
                    </div>
                `;
            } else if (eoi.client_confirmation_status === 'amendment_requested') {
                html += `
                    <div class="workflow-box danger">
                        <h5>${typeof crmIconLegacy === 'function' ? crmIconLegacy('fas fa-exclamation-triangle') : '<i class="fas fa-exclamation-triangle"></i>'} Amendment Requested</h5>
                        <div class="workflow-detail">
                            <strong>Requested On:</strong>
                            <span>${eoi.client_confirmation_date || 'N/A'}</span>
                        </div>
                        <div class="amendment-notes">
                            <strong>Client Notes:</strong><br>
                            ${eoi.client_notes || 'No notes provided'}
                        </div>
                        <div class="workflow-actions">
                            <button type="button" class="btn btn-primary btn-sm" id="btn-resend-email" data-eoi-id="${eoi.id}">
                                ${typeof crmIconLegacy === 'function' ? crmIconLegacy('fas fa-envelope') : '<i class="fas fa-envelope"></i>'} Resend Email
                            </button>
                            <button type="button" class="btn btn-success btn-sm" id="btn-resolve-amendment" data-eoi-id="${eoi.id}">
                                ${typeof crmIconLegacy === 'function' ? crmIconLegacy('fas fa-check') : '<i class="fas fa-check"></i>'} Mark Resolved
                            </button>
                        </div>
                    </div>
                `;
            } else if (eoi.email_sent_at) {
                html += `
                    <div class="workflow-box info">
                        <h5>${typeof crmIconLegacy === 'function' ? crmIconLegacy('fas fa-clock') : '<i class="fas fa-clock"></i>'} Awaiting Client Response</h5>
                        <div class="workflow-detail">
                            <strong>Email Sent:</strong>
                            <span>${typeof formatDisplayDateTime === 'function' ? formatDisplayDateTime(eoi.email_sent_at) : (eoi.email_sent_at || 'N/A')}</span>
                        </div>
                        <div class="workflow-actions">
                            <button type="button" class="btn btn-primary btn-sm" id="btn-resend-email" data-eoi-id="${eoi.id}">
                                ${typeof crmIconLegacy === 'function' ? crmIconLegacy('fas fa-envelope') : '<i class="fas fa-envelope"></i>'} Resend Email
                            </button>
                        </div>
                    </div>
                `;
            } else {
                html += `
                    <div class="workflow-box info">
                        <h5>${typeof crmIconLegacy === 'function' ? crmIconLegacy('fas fa-envelope') : '<i class="fas fa-envelope"></i>'} Ready to Send</h5>
                        <p style="margin-bottom: 10px;">EOI verified. Send confirmation email to client.</p>
                        <div class="workflow-actions">
                            <button type="button" class="btn btn-primary btn-sm" id="btn-send-email" data-eoi-id="${eoi.id}">
                                ${typeof crmIconLegacy === 'function' ? crmIconLegacy('fas fa-paper-plane') : '<i class="fas fa-paper-plane"></i>'} Send to Client
                            </button>
                        </div>
                    </div>
                `;
            }
        }

        workflowContent.html(html);
        bindWorkflowHandlers();
    }

    // Bind workflow action handlers
    function bindWorkflowHandlers() {
        // Verify EOI
        $('#btn-verify-eoi').off('click').on('click', function() {
            const eoiId = $(this).data('eoi-id');
            verifyEoi(eoiId);
        });

        // Send Email
        $('#btn-send-email').off('click').on('click', function() {
            const eoiId = $(this).data('eoi-id');
            sendConfirmationEmail(eoiId, false);
        });

        // Resend Email
        $('#btn-resend-email').off('click').on('click', function() {
            const eoiId = $(this).data('eoi-id');
            sendConfirmationEmail(eoiId, true);
        });

        // Resolve Amendment
        $('#btn-resolve-amendment').off('click').on('click', function() {
            const eoiId = $(this).data('eoi-id');
            resolveAmendment(eoiId);
        });
    }

    // Verify EOI
    function verifyEoi(eoiId) {
        if (!confirm('Are you sure you want to verify this EOI? This confirms you have reviewed all details and they are correct.')) {
            return;
        }

        $.ajax({
            url: `/clients/${state.clientId}/eoi-roi/${eoiId}/verify`,
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                if (response.success) {
                    showNotification(response.message, 'success');
                    loadEoiRecords();
                    loadWorkflowSection(eoiId);
                }
            },
            error: function(xhr) {
                showNotification('Error verifying EOI', 'error');
            }
        });
    }

    // Send confirmation email
    function sendConfirmationEmail(eoiId, isResend) {
        // NEW: Open compose modal instead of directly sending
        // Find the EOI record to get the EOI number
        const eoiRecord = state.eoiRecords.find(eoi => eoi.id === eoiId);
        if (!eoiRecord) {
            showNotification('EOI record not found', 'error');
            return;
        }
        
        // Open compose modal
        openEoiComposeModal(eoiId, eoiRecord.eoi_number, state.clientId, isResend);
    }

    // Resolve amendment
    function resolveAmendment(eoiId) {
        if (!confirm('Are you sure you want to mark this amendment as resolved? This will allow you to resend the confirmation email.')) {
            return;
        }

        $.ajax({
            url: `/clients/${state.clientId}/eoi-roi/${eoiId}/resolve-amendment`,
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                if (response.success) {
                    showNotification(response.message, 'success');
                    loadEoiRecords();
                    loadWorkflowSection(eoiId);
                }
            },
            error: function(xhr) {
                showNotification('Error resolving amendment', 'error');
            }
        });
    }

    // Helper: Show notification
    function showNotification(message, type) {
        const icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
        const bgColor = type === 'success' ? '#28a745' : '#dc3545';
        
        const notification = $(`
            <div class="notification-toast" style="position: fixed; top: 20px; right: 20px; z-index: 9999; background: ${bgColor}; color: white; padding: 15px 20px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.3); display: flex; align-items: center; gap: 10px; min-width: 300px;">
                ${typeof crmIconLegacy === 'function' ? crmIconLegacy('fas ' + icon) : '<i class="fas ' + icon + '"></i>'}
                <span>${message}</span>
            </div>
        `);
        
        $('body').append(notification);
        
        setTimeout(() => {
            notification.fadeOut(300, function() {
                $(this).remove();
            });
        }, 3000);
    }

    /**
     * =====================================================
     * EOI COMPOSE MODAL - NEW (Adapted from existing email compose)
     * =====================================================
     */

    // Global variables for current compose session
    let currentCompose = {
        eoiId: null,
        eoiNumber: null,
        clientId: null
    };

    /**
     * Open EOI compose modal
     */
    window.openEoiComposeModal = function(eoiId, eoiNumber, clientId, isResend = false) {
        console.log('[EOI-COMPOSE] Opening modal with:', { eoiId, eoiNumber, clientId, isResend });
        
        if (!eoiId) {
            showNotification('Please save the EOI first before sending email.', 'error');
            return;
        }
        
        // Set compose variables
        currentCompose.eoiId = eoiId;
        currentCompose.eoiNumber = eoiNumber;
        currentCompose.clientId = clientId || state.clientId;
        
        console.log('[EOI-COMPOSE] Current compose state:', currentCompose);
        
        // Update modal title
        $('#eoi-compose-modal .modal-title').text(isResend ? 'Resend EOI Confirmation Email' : 'Send EOI Confirmation Email');
        
        // Set hidden fields
        $('#eoi-compose-eoi-id').val(eoiId);
        $('#eoi-compose-client-id').val(currentCompose.clientId);
        
        // Show modal
        $('#eoi-compose-modal').modal('show');
        
        // Load email preview (subject + body)
        loadEoiEmailPreview(currentCompose.clientId, eoiId);
        
        // Load visa documents for attachment selection
        loadEoiVisaDocuments(currentCompose.clientId, eoiNumber);
    };

    /**
     * Load email preview (subject and body) from server
     */
    function loadEoiEmailPreview(clientId, eoiId) {
        console.log('[EOI-COMPOSE] Loading email preview for client:', clientId, 'eoi:', eoiId);
        
        $('#eoi-email-subject').val('Loading...');
        $('#eoi-email-to').val('Loading...');
        $('#eoi-email-body').html('<div style="text-align: center; color: #999;">Loading preview...</div>');
        
        const url = `/clients/${clientId}/eoi-roi/${eoiId}/email-preview`;
        console.log('[EOI-COMPOSE] Preview URL:', url);
        
        $.ajax({
            url: url,
            method: 'GET',
            timeout: 30000, // 30 second timeout
            success: function(response) {
                console.log('[EOI-COMPOSE] Preview loaded successfully:', response);
                if (response.success) {
                    $('#eoi-email-subject').val(response.data.subject);
                    $('#eoi-email-to').val(response.data.client_name + ' <' + response.data.client_email + '>');
                    $('#eoi-email-to-help').text('Email will be sent to: ' + response.data.client_email);
                    // Render the HTML email preview (scaled down to fit)
                    $('#eoi-email-body').html(response.data.body_html || '<div style="color: #999;">No preview available</div>');
                } else {
                    showNotification(response.message || 'Failed to load email preview', 'error');
                    $('#eoi-compose-modal').modal('hide');
                }
            },
            error: function(xhr, status, error) {
                console.error('[EOI-COMPOSE] Preview load failed:', { xhr, status, error, responseText: xhr.responseText });
                const msg = xhr.responseJSON?.message || `Failed to load email preview: ${status} - ${error}`;
                showNotification(msg, 'error');
                
                // Show error details in the modal instead of closing it
                $('#eoi-email-subject').val('ERROR: Could not load preview');
                $('#eoi-email-body').html(`<div style="color: #dc3545; font-family: monospace; font-size: 12px; white-space: pre-wrap;">Error: ${status}\n${xhr.responseText || error}</div>`);
            }
        });
    }

    /**
     * Load visa documents for attachment selection
     */
    function loadEoiVisaDocuments(clientId, eoiNumber) {
        console.log('[EOI-COMPOSE] Loading visa documents for client:', clientId, 'eoi:', eoiNumber);
        
        $('#eoi-attachment-list').html('<div class="text-center text-muted py-3">' + (typeof crmIconLegacy === 'function' ? crmIconLegacy('fas fa-spinner fa-spin') : '<i class="fas fa-spinner fa-spin"></i>') + ' Loading documents...</div>');
        
        const url = `/clients/${clientId}/eoi-roi/visa-documents`;
        console.log('[EOI-COMPOSE] Documents URL:', url, 'params:', { eoi_number: eoiNumber });
        
        $.ajax({
            url: url,
            method: 'GET',
            data: { eoi_number: eoiNumber },
            timeout: 30000, // 30 second timeout
            success: function(response) {
                console.log('[EOI-COMPOSE] Documents loaded successfully:', response);
                if (response.success) {
                    renderEoiVisaDocuments(response.data, eoiNumber);
                } else {
                    $('#eoi-attachment-list').html('<div class="text-danger">Failed to load documents.</div>');
                }
            },
            error: function(xhr, status, error) {
                console.error('[EOI-COMPOSE] Documents load failed:', { xhr, status, error, responseText: xhr.responseText });
                $('#eoi-attachment-list').html(`<div class="text-danger">Error loading documents: ${status} - ${error}<br><small>${xhr.responseText || 'Please try again or check console for details.'}</small></div>`);
            }
        });
    }

    /**
     * Render visa documents as checkboxes
     */
    function renderEoiVisaDocuments(data, eoiNumber) {
        let html = '';
        let totalDocs = 0;
        
        // Group 1: Documents referencing this EOI
        if (data.eoi_references && data.eoi_references.length > 0) {
            html += `<div class="mb-3">
                        <h6 class="mb-2">${typeof crmIconLegacy === 'function' ? crmIconLegacy('fas fa-star text-warning') : '<i class="fas fa-star text-warning"></i>'} Documents referencing ${eoiNumber}</h6>
                        <div class="pl-3">`;
            
            data.eoi_references.forEach(doc => {
                html += `<div class="form-check mb-2">
                            <input class="form-check-input eoi-attachment-checkbox" type="checkbox" 
                                   value="${doc.id}" data-size="${doc.file_size_mb}" id="doc-${doc.id}">
                            <label class="form-check-label" for="doc-${doc.id}">
                                <strong>${doc.file_name}</strong>
                                <br><small class="text-muted">${doc.category} • ${doc.file_size_mb} MB • ${typeof formatDisplayDateTime === 'function' ? formatDisplayDateTime(doc.created_at) : doc.created_at}</small>
                            </label>
                        </div>`;
                totalDocs++;
            });
            
            html += `</div></div>`;
        }
        
        // Group 2: Other visa documents
        if (data.other_documents && data.other_documents.length > 0) {
            html += `<div class="mb-3">
                        <h6 class="mb-2">${typeof crmIconLegacy === 'function' ? crmIconLegacy('fas fa-folder text-secondary') : '<i class="fas fa-folder text-secondary"></i>'} Other Visa Documents</h6>
                        <div class="pl-3">`;
            
            data.other_documents.forEach(doc => {
                html += `<div class="form-check mb-2">
                            <input class="form-check-input eoi-attachment-checkbox" type="checkbox" 
                                   value="${doc.id}" data-size="${doc.file_size_mb}" id="doc-${doc.id}">
                            <label class="form-check-label" for="doc-${doc.id}">
                                ${doc.file_name}
                                <br><small class="text-muted">${doc.category} • ${doc.file_size_mb} MB • ${typeof formatDisplayDateTime === 'function' ? formatDisplayDateTime(doc.created_at) : doc.created_at}</small>
                            </label>
                        </div>`;
                totalDocs++;
            });
            
            html += `</div></div>`;
        }
        
        // No documents found
        if (totalDocs === 0) {
            html = '<div class="text-center text-muted py-3">' + (typeof crmIconLegacy === 'function' ? crmIconLegacy('fas fa-info-circle') : '<i class="fas fa-info-circle"></i>') + ' No visa documents available. You can still send the email without attachments.</div>';
        }
        
        $('#eoi-attachment-list').html(html);
        
        // Update attachment selection on checkbox change
        $('.eoi-attachment-checkbox').on('change', updateEoiAttachmentSummary);
        updateEoiAttachmentSummary();
    }

    /**
     * Update attachment summary (count and size)
     */
    function updateEoiAttachmentSummary() {
        const checked = $('.eoi-attachment-checkbox:checked');
        const count = checked.length;
        let totalSize = 0;
        
        checked.each(function() {
            totalSize += parseFloat($(this).data('size')) || 0;
        });
        
        totalSize = totalSize.toFixed(2);
        
        if (count > 0) {
            let color = 'text-success';
            if (count > 10) color = 'text-danger';
            else if (totalSize > 25) color = 'text-danger';
            else if (totalSize > 20) color = 'text-warning';
            
            $('#eoi-attachment-summary').html(`<span class="${color}"><strong>${count} selected</strong> (${totalSize} MB)</span>`);
        } else {
            $('#eoi-attachment-summary').html('<span class="text-muted">No attachments selected</span>');
        }
    }

    /**
     * Send EOI confirmation email with composed data
     */
    $('#btn-eoi-send-email').on('click', function() {
        const subject = $('#eoi-email-subject').val().trim();
        const body = ''; // Phase 1: always use server-rendered body (readonly field)
        const documentIds = [];
        let totalSize = 0;
        
        // Collect selected document IDs
        $('.eoi-attachment-checkbox:checked').each(function() {
            documentIds.push(parseInt($(this).val()));
            totalSize += parseFloat($(this).data('size')) || 0;
        });
        
        // Validation
        if (!subject) {
            showNotification('Subject is required.', 'error');
            return;
        }
        
        if (documentIds.length > 10) {
            showNotification('Maximum 10 attachments allowed. Please deselect some documents.', 'error');
            return;
        }
        
        if (totalSize > 25) {
            showNotification(`Total attachment size (${totalSize.toFixed(2)} MB) exceeds 25MB limit. Please deselect some documents.`, 'error');
            return;
        }
        
        // Confirm send
        const confirmMsg = documentIds.length > 0 
            ? `Send email with ${documentIds.length} attachment(s) (${totalSize.toFixed(2)} MB)?`
            : 'Send email without attachments?';
        
        if (!confirm(confirmMsg)) {
            return;
        }
        
        // Disable button and show loading
        const $btn = $('#btn-eoi-send-email');
        $btn.prop('disabled', true).html((typeof crmIconLegacy === 'function' ? crmIconLegacy('fas fa-spinner fa-spin') : '<i class="fas fa-spinner fa-spin"></i>') + ' Sending...');
        
        // Send AJAX request
        $.ajax({
            url: `/clients/${currentCompose.clientId}/eoi-roi/${currentCompose.eoiId}/send-email`,
            method: 'POST',
            data: {
                _token: $('meta[name="csrf-token"]').attr('content'),
                subject: subject,
                body: body || null,
                document_ids: documentIds
            },
            success: function(response) {
                showNotification(response.message || 'Email sent successfully!', 'success');
                $('#eoi-compose-modal').modal('hide');
                
                // Refresh EOI list to show updated status
                loadEoiRecords();
                
                // Refresh workflow section if EOI is currently loaded
                if (state.selectedEoiId === currentCompose.eoiId) {
                    loadWorkflowSection(currentCompose.eoiId);
                }
            },
            error: function(xhr) {
                const msg = xhr.responseJSON?.message || 'Failed to send email. Please try again.';
                showNotification(msg, 'error');
            },
            complete: function() {
                $btn.prop('disabled', false).html((typeof crmIconLegacy === 'function' ? crmIconLegacy('fas fa-paper-plane') : '<i class="fas fa-paper-plane"></i>') + ' Send Email');
            }
        });
    });

    // Export for global access if needed
    window.EoiRoiModule = {
        loadWorkflowSection: loadWorkflowSection,
        openEoiComposeModal: openEoiComposeModal,
        state: state
    };

})();

