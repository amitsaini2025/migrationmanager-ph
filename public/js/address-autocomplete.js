/**
 * Address Autocomplete Module
 * Handles Google Places address autocomplete and regional code calculation
 */

(function() {
    'use strict';
    
    console.log('🔍 Address autocomplete script loading...');
    console.log('📌 saveAddressInfo function available?', typeof window.saveAddressInfo);
    
    // Note: Regional code functions (getRegionalCodeInfo and isValidAustralianPostcode) 
    // are now defined in address-regional-codes.js to avoid duplication
    
    // Initialize when DOM is ready
    $(document).ready(function() {
        console.log('🔍 External address-autocomplete.js loading...');
        initAddressAutocomplete();
        console.log('✅ External address-autocomplete.js initialized');
    });
    
    /**
     * Initialize address autocomplete functionality
     */
    function initAddressAutocomplete() {
        console.log('✅ jQuery ready - Address autocomplete initialized');
        console.log('📍 Address search inputs found:', $('.address-search-input').length);
        
        // Get configuration from data attributes
        const config = getAutocompleteConfig();
        
        // Initialize date pickers (with error handling)
        initDatePickers();
        
        // Set up event listeners
        bindRegionalCodeCalculation();
        bindAddressSearch(config);
        bindAddressSelection(config);
        bindClickOutside();
    }
    
    /**
     * Initialize autocomplete for a newly added address field
     * This function is exposed globally for use when dynamically adding addresses
     */
    window.initAddressAutocompleteForNewField = function($newFieldWrapper) {
        console.log('🔧 Initializing autocomplete for new address field');
        
        // Get configuration from data attributes (should be same as initial config)
        const config = getAutocompleteConfig();
        
        // Initialize date pickers for the new field only (using Flatpickr)
        try {
            if (typeof flatpickr !== 'undefined') {
                $newFieldWrapper.find('.date-picker').each(function() {
                    // Check if already initialized
                    if ($(this).data('flatpickr')) {
                        return;
                    }
                    
                    flatpickr(this, {
                        dateFormat: 'd/m/Y',
                        allowInput: true,
                        clickOpens: true,
                        locale: {
                            firstDayOfWeek: 1 // Monday
                        }
                    });
                });
                console.log('✅ Flatpickr initialized for new field');
            } else {
                console.warn('⚠️ Flatpickr not available for new field');
            }
        } catch(e) {
            console.warn('⚠️ Flatpickr initialization failed for new field:', e.message);
        }
        
        // Note: Event listeners don't need re-binding because they use delegation
        // bindAddressSearch, bindAddressSelection, bindRegionalCodeCalculation use
        // $(document).on() which automatically handles dynamically added elements
        console.log('✅ Autocomplete ready for new field (event delegation handles it)');
    };
    
    // Expose the main initialization function globally for backward compatibility
    window.initAddressAutocomplete = function() {
        initAddressAutocomplete();
    };
    
    /**
     * Get autocomplete configuration from DOM
     */
    function getAutocompleteConfig() {
        const container = document.getElementById('addressInfoEdit');
        
        // Validate container exists
        if (!container) {
            console.error('❌ CRITICAL: Address container #addressInfoEdit not found!');
            console.error('Address autocomplete will not work. Ensure the view includes the container element.');
            return {
                searchRoute: '',
                detailsRoute: '',
                csrfToken: '',
                addressCount: 0,
                isValid: false
            };
        }
        
        // Get routes from data attributes
        const searchRoute = container.dataset.searchRoute || '';
        const detailsRoute = container.dataset.detailsRoute || '';
        const csrfToken = container.dataset.csrfToken || '';
        
        // Validate required configuration
        if (!searchRoute || !detailsRoute || !csrfToken) {
            console.error('❌ CRITICAL: Address autocomplete configuration missing!');
            console.error('Configuration:', {
                searchRoute: searchRoute || '(missing)',
                detailsRoute: detailsRoute || '(missing)',
                csrfToken: csrfToken ? '(present)' : '(missing)'
            });
            console.error('Ensure the #addressInfoEdit element has data-search-route, data-details-route, and data-csrf-token attributes.');
        }
        
        return {
            searchRoute: searchRoute,
            detailsRoute: detailsRoute,
            csrfToken: csrfToken,
            addressCount: parseInt(container.dataset.addressCount || '0'),
            isValid: !!(searchRoute && detailsRoute && csrfToken)
        };
    }
    
    /**
     * Initialize Flatpickr datepickers with error handling
     */
    function initDatePickers() {
        try {
            if (typeof flatpickr !== 'undefined') {
                $('.date-picker').each(function() {
                    // Check if already initialized
                    if ($(this).data('flatpickr')) {
                        return;
                    }
                    
                    flatpickr(this, {
                        dateFormat: 'd/m/Y',
                        allowInput: true,
                        clickOpens: true,
                        locale: {
                            firstDayOfWeek: 1 // Monday
                        }
                    });
                });
                console.log('✅ Flatpickr initialized for .date-picker fields');
            } else {
                console.warn('⚠️ Flatpickr not available, skipping...');
            }
        } catch(e) {
            console.warn('⚠️ Flatpickr initialization failed:', e.message);
        }
    }
    
    /**
     * Bind regional code auto-calculation based on postcode
     */
    function bindRegionalCodeCalculation() {
        $(document).on('input', 'input[name="zip[]"]', function() {
            const postcode = $(this).val();
            const $wrapper = $(this).closest('.address-entry-wrapper');
            const $regionalCode = $wrapper.find('input[name="regional_code[]"]');
            
            if (postcode && window.isValidAustralianPostcode(postcode)) {
                const regionalInfo = window.getRegionalCodeInfo(postcode);
                $regionalCode.val(regionalInfo);
                console.log('🔢 Regional code calculated:', regionalInfo, 'from postcode:', postcode);
            } else {
                // Clear regional code if postcode is invalid or empty
                $regionalCode.val('');
                if (postcode) {
                    console.warn('⚠️ Invalid postcode format:', postcode, '- regional code cleared');
                } else {
                    console.log('ℹ️ Postcode cleared - regional code also cleared');
                }
            }
        });
    }
    
    /**
     * Bind address search functionality
     */
    function bindAddressSearch(config) {
        $(document).on('input', '.address-search-input', function() {
            console.log('🔍 Input detected in address search field:', $(this).val());
            const query = $(this).val();
            const $wrapper = $(this).closest('.address-entry-wrapper');
            
            if (query.length < 3) {
                console.log('⏸️ Query too short (less than 3 chars)');
                $wrapper.find('.autocomplete-suggestions').remove();
                return;
            }
            
            // CRITICAL SAFETY CHECK: Validate configuration before making AJAX call
            if (!config.isValid || !config.searchRoute) {
                console.error('❌ Cannot perform address search: Configuration is invalid');
                console.error('searchRoute:', config.searchRoute || '(empty)');
                
                // Show user-friendly error message
                const errorMsg = $('<div class="autocomplete-error" style="color: #dc3545; font-size: 12px; margin-top: 5px; padding: 8px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px;">' +
                    (typeof crmIconAny === 'function' ? crmIconAny('fas fa-exclamation-triangle') : '<i class="fas fa-exclamation-triangle"></i>') + ' Address search unavailable. Please refresh the page or contact support.' +
                    '</div>');
                $wrapper.find('.autocomplete-error').remove();
                $wrapper.find('.address-search-container').append(errorMsg);
                
                // Auto-remove error after 5 seconds
                setTimeout(() => errorMsg.fadeOut(() => errorMsg.remove()), 5000);
                return;
            }
            
            console.log('🚀 Sending AJAX request to backend...');
            $.ajax({
                url: config.searchRoute,
                method: 'POST',
                timeout: 35000, // 35 seconds timeout (backend has 30s, allow buffer)
                data: { 
                    query: query,
                    _token: config.csrfToken
                },
                success: function(response) {
                    console.log('✅ Address search response:', response);
                    
                    // CRITICAL: Check for error status from backend (Google API failed + fallback failed)
                    if (response.status === 'ERROR' && response.error_message) {
                        console.warn('⚠️ Backend error:', response.error_message);
                        
                        // Show user-friendly error message from backend
                        const errorMsg = $('<div class="autocomplete-error" style="color: #dc3545; font-size: 12px; margin-top: 5px; padding: 8px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px;">' +
                            (typeof crmIconAny === 'function' ? crmIconAny('fas fa-exclamation-triangle') : '<i class="fas fa-exclamation-triangle"></i>') + ' ' + response.error_message +
                            '</div>');
                        $wrapper.find('.autocomplete-error, .autocomplete-info').remove();
                        $wrapper.find('.address-search-container').append(errorMsg);
                        
                        setTimeout(() => errorMsg.fadeOut(() => errorMsg.remove()), 8000);
                        return;
                    }
                    
                    // Check if we have predictions
                    if (response.predictions && Array.isArray(response.predictions) && response.predictions.length > 0) {
                        renderSuggestions($wrapper, response.predictions);
                        
                        // Optional: Show info if using fallback service (detected by fallback_ prefix in place_id)
                        if (response.predictions.some(p => p.place_id && p.place_id.startsWith('fallback_'))) {
                            // Silently using fallback - no need to bother user unless there are issues
                            console.log('ℹ️ Using fallback address service (OpenStreetMap)');
                        }
                    } else {
                        // No predictions returned - show helpful message
                        console.log('No predictions found for query:', query);
                        
                        // Show informational message that search completed but no results
                        const infoMsg = $('<div class="autocomplete-info" style="color: #856404; font-size: 12px; margin-top: 5px; padding: 8px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px;">' +
                            (typeof crmIconAny === 'function' ? crmIconAny('fas fa-info-circle') : '<i class="fas fa-info-circle"></i>') + ' No addresses found. Please enter the address manually.' +
                            '</div>');
                        $wrapper.find('.autocomplete-info').remove();
                        $wrapper.find('.address-search-container').append(infoMsg);
                        
                        // Auto-remove after 5 seconds
                        setTimeout(() => infoMsg.fadeOut(() => infoMsg.remove()), 5000);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Address search error:', error);
                    console.error('Status:', status);
                    console.error('Response:', xhr.responseText);
                    
                    // Determine error message based on status
                    let errorMessage = 'Address search failed. Please try again or enter manually.';
                    if (status === 'timeout') {
                        errorMessage = 'Address search timed out. The server is taking too long to respond. Please try again or enter manually.';
                        console.warn('⏱️ Request timeout after 15 seconds');
                    } else if (status === 'abort') {
                        errorMessage = 'Address search was cancelled.';
                        console.warn('⚠️ Request was aborted');
                    } else if (xhr.status === 0) {
                        errorMessage = 'Network error. Please check your connection and try again.';
                        console.warn('🌐 Network error detected');
                    }
                    
                    // Show user-friendly error message
                    const errorMsg = $('<div class="autocomplete-error" style="color: #dc3545; font-size: 12px; margin-top: 5px; padding: 8px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px;">' +
                        (typeof crmIconAny === 'function' ? crmIconAny('fas fa-exclamation-triangle') : '<i class="fas fa-exclamation-triangle"></i>') + ' ' + errorMessage +
                        '</div>');
                    $wrapper.find('.autocomplete-error, .autocomplete-info').remove();
                    $wrapper.find('.address-search-container').append(errorMsg);
                    
                    // Auto-remove error after 5 seconds
                    setTimeout(() => errorMsg.fadeOut(() => errorMsg.remove()), 5000);
                }
            });
        });
    }
    
    /**
     * Render autocomplete suggestions
     */
    function renderSuggestions($wrapper, predictions) {
        let suggestions = '<div class="autocomplete-suggestions">';
        predictions.forEach(function(prediction) {
            suggestions += `<div class="autocomplete-suggestion" data-place-id="${prediction.place_id}">${prediction.description}</div>`;
        });
        suggestions += '</div>';
        
        $wrapper.find('.autocomplete-suggestions').remove();
        $wrapper.find('.address-search-container').append(suggestions);
    }
    
    /**
     * Bind address selection handler
     */
    function bindAddressSelection(config) {
        $(document).on('click', '.autocomplete-suggestion', function() {
            const placeId = $(this).data('place-id');
            const description = $(this).text();
            const $wrapper = $(this).closest('.address-entry-wrapper');
            
            $wrapper.find('.address-search-input').val(description);
            $wrapper.find('.autocomplete-suggestions').remove();
            
            // Get place details
            fetchPlaceDetails(config, placeId, $wrapper);
        });
    }
    
    /**
     * Fetch place details from Google Places API
     */
    function fetchPlaceDetails(config, placeId, $wrapper) {
        const description = $wrapper.find('.address-search-input').val();
        
        // CRITICAL SAFETY CHECK: Validate configuration before making AJAX call
        if (!config.isValid || !config.detailsRoute) {
            console.error('❌ Cannot fetch place details: Configuration is invalid');
            console.error('detailsRoute:', config.detailsRoute || '(empty)');
            
            // Show user-friendly error message
            const errorMsg = $('<div class="autocomplete-error" style="color: #dc3545; font-size: 12px; margin-top: 5px; padding: 8px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px;">' +
                (typeof crmIconAny === 'function' ? crmIconAny('fas fa-exclamation-triangle') : '<i class="fas fa-exclamation-triangle"></i>') + ' Unable to fetch address details. Please enter manually.' +
                '</div>');
            $wrapper.find('.autocomplete-error').remove();
            $wrapper.find('.address-search-container').append(errorMsg);
            
            setTimeout(() => errorMsg.fadeOut(() => errorMsg.remove()), 5000);
            return;
        }
        
        $.ajax({
            url: config.detailsRoute,
            method: 'POST',
            timeout: 35000, // 35 seconds timeout (backend has 30s, allow buffer)
            data: { 
                place_id: placeId,
                description: description, // Include description for fallback
                _token: config.csrfToken
            },
            success: function(response) {
                console.log('Place details response:', response);
                if (response.result && response.result.address_components) {
                    populateAddressFields($wrapper, response.result);
                } else {
                    console.log('No address components in response - manual entry required');
                    // Show a message that manual entry is needed
                    showManualEntryMessage($wrapper);
                }
            },
            error: function(xhr, status, error) {
                console.error('Place details error:', error);
                console.error('Status:', status);
                console.error('Response:', xhr.responseText);
                
                // Determine error message based on status
                let errorMessage = 'Unable to fetch address details. Please enter manually.';
                if (status === 'timeout') {
                    errorMessage = 'Address details request timed out. The server is taking too long to respond. Please enter manually.';
                    console.warn('⏱️ Place details request timeout after 15 seconds');
                } else if (status === 'abort') {
                    errorMessage = 'Address details request was cancelled.';
                    console.warn('⚠️ Place details request was aborted');
                } else if (xhr.status === 0) {
                    errorMessage = 'Network error. Please check your connection and enter address manually.';
                    console.warn('🌐 Network error detected');
                }
                
                // Show error message
                const errorMsg = $('<div class="autocomplete-error" style="color: #dc3545; font-size: 12px; margin-top: 5px; padding: 8px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px;">' +
                    (typeof crmIconAny === 'function' ? crmIconAny('fas fa-exclamation-triangle') : '<i class="fas fa-exclamation-triangle"></i>') + ' ' + errorMessage +
                    '</div>');
                $wrapper.find('.autocomplete-error').remove();
                $wrapper.find('.address-search-container').append(errorMsg);
                
                setTimeout(() => errorMsg.fadeOut(() => errorMsg.remove()), 5000);
                
                // Also show manual entry message as fallback
                showManualEntryMessage($wrapper);
            }
        });
    }
    
    /**
     * Show message that manual entry is needed
     */
    function showManualEntryMessage($wrapper) {
        const message = $('<div class="autocomplete-message" style="color: #666; font-size: 12px; margin-top: 5px;">Please fill in address fields manually</div>');
        $wrapper.find('.autocomplete-message').remove();
        $wrapper.find('.address-search-container').append(message);
        
        // Remove message after 3 seconds
        setTimeout(() => {
            message.fadeOut(() => message.remove());
        }, 3000);
    }
    
    /**
     * Populate address fields from Google Places response
     */
    function populateAddressFields($wrapper, result) {
        console.log('🏠 Populating address fields with result:', result);
        
        const components = result.address_components;
        
        // Extract address components with more comprehensive mapping
        let addressLine1 = '';
        let addressLine2 = '';
        let suburb = '';
        let state = '';
        let postcode = '';
        let country = 'Australia';
        
        // Log all components for debugging
        console.log('📍 Address components:', components);
        
        // First pass: collect all components
        let unitNumber = '';
        let streetNumber = '';
        let streetName = '';
        
        components.forEach(function(component) {
            console.log('🔍 Processing component:', component.long_name, 'Types:', component.types);
            
            // Unit/Apartment number (subpremise) - will be combined with address
            if (component.types.includes('subpremise')) {
                unitNumber = component.long_name;
            }
            
            // Street number
            if (component.types.includes('street_number')) {
                streetNumber = component.long_name;
            }
            
            // Street name (route)
            if (component.types.includes('route')) {
                streetName = component.long_name;
            }
            
            // For airports and POIs, use the establishment name as address line 1
            if (component.types.includes('establishment') || component.types.includes('point_of_interest')) {
                addressLine1 = component.long_name;
            }
            
            // Airport specific handling
            if (component.types.includes('airport')) {
                addressLine1 = component.long_name;
            }
            
            // Suburb/Locality
            if (component.types.includes('locality') || component.types.includes('sublocality')) {
                suburb = component.long_name;
            }
            
            // State
            if (component.types.includes('administrative_area_level_1')) {
                state = component.short_name || component.long_name;
            }
            
            // Postcode - check both postal_code and postal_code_prefix
            if (component.types.includes('postal_code')) {
                postcode = component.long_name;
                console.log('📮 Postcode found in address_components:', postcode);
            }
            // Some addresses might have postal_code_prefix instead
            if (!postcode && component.types.includes('postal_code_prefix')) {
                postcode = component.long_name;
                console.log('📮 Postcode prefix found:', postcode);
            }
            
            // Country
            if (component.types.includes('country')) {
                country = component.long_name;
            }
        });
        
        // Build Address Line 1 with proper formatting
        // If we have a unit number, format as "Unit/StreetNumber StreetName"
        if (unitNumber && streetNumber && streetName) {
            addressLine1 = unitNumber + '/' + streetNumber + ' ' + streetName;
            console.log('🏠 Address with unit: ' + addressLine1);
        } else if (streetNumber && streetName) {
            // No unit, just street number and name
            addressLine1 = streetNumber + ' ' + streetName;
        } else if (unitNumber && streetName) {
            // Unit but no street number (unusual case)
            addressLine1 = unitNumber + '/' + streetName;
        } else if (streetName) {
            // Only street name
            addressLine1 = streetName;
        }
        
        // If we still don't have an address line 1, try to extract from formatted address
        if (!addressLine1.trim() && result.formatted_address) {
            const addressParts = result.formatted_address.split(',');
            if (addressParts.length > 0) {
                addressLine1 = addressParts[0].trim();
            }
        }
        
        // If suburb is still empty, try to get it from formatted address
        if (!suburb && result.formatted_address) {
            const addressParts = result.formatted_address.split(',');
            // Usually suburb is the 2nd or 3rd part
            for (let i = 1; i < addressParts.length && i < 4; i++) {
                const part = addressParts[i].trim();
                // Skip if it looks like a state or postcode
                if (!part.match(/^\d{4}$/) && !part.includes('NSW') && !part.includes('VIC') && !part.includes('QLD') && !part.includes('SA') && !part.includes('WA') && !part.includes('TAS') && !part.includes('NT') && !part.includes('ACT')) {
                    suburb = part;
                    break;
                }
            }
        }
        
        // Enhanced fallback postcode extraction from formatted_address
        if (!postcode && result.formatted_address) {
            console.log('🔍 Attempting to extract postcode from formatted_address:', result.formatted_address);
            
            // Try multiple patterns for Australian postcodes
            // Pattern 1: 4-digit number (standard Australian postcode)
            let postcodeMatch = result.formatted_address.match(/\b(\d{4})\b/);
            
            // Pattern 2: Look for postcode after state abbreviation (e.g., "NSW 2000")
            if (!postcodeMatch) {
                postcodeMatch = result.formatted_address.match(/\b(NSW|VIC|QLD|SA|WA|TAS|NT|ACT)\s+(\d{4})\b/i);
                if (postcodeMatch && postcodeMatch[2]) {
                    postcode = postcodeMatch[2];
                    console.log('📮 Postcode extracted after state:', postcode);
                }
            } else {
                postcode = postcodeMatch[1];
                console.log('📮 Postcode extracted from formatted address:', postcode);
            }
            
            // Pattern 3: Look for postcode at the end before country
            if (!postcode) {
                const parts = result.formatted_address.split(',');
                for (let i = parts.length - 1; i >= 0; i--) {
                    const part = parts[i].trim();
                    const match = part.match(/\b(\d{4})\b/);
                    if (match && !part.match(/^(Australia|AU)$/i)) {
                        postcode = match[1];
                        console.log('📮 Postcode extracted from end part:', postcode);
                        break;
                    }
                }
            }
        }
        
        // If still no postcode, try to get from place name itself
        if (!postcode && result.name) {
            const postcodeMatch = result.name.match(/\b(\d{4})\b/);
            if (postcodeMatch) {
                postcode = postcodeMatch[1];
                console.log('📮 Postcode extracted from place name:', postcode);
            }
        }
        
        // Final check: if still no postcode, log warning
        if (!postcode) {
            console.warn('⚠️ WARNING: No postcode found in address components or formatted address');
            console.warn('Address components:', components);
            console.warn('Formatted address:', result.formatted_address);
        }
        
        console.log('🏠 Final address mapping:', {
            addressLine1: addressLine1.trim(),
            addressLine2: addressLine2,
            suburb: suburb,
            state: state,
            postcode: postcode,
            country: country,
            hasUnit: !!unitNumber
        });
        
        // Populate form fields
        $wrapper.find('input[name="address_line_1[]"]').val(addressLine1.trim());
        $wrapper.find('input[name="address_line_2[]"]').val(addressLine2);
        $wrapper.find('input[name="suburb[]"]').val(suburb);
        $wrapper.find('input[name="state[]"]').val(state);
        
        // Set postcode - try multiple field name variations
        const $postcodeField = $wrapper.find('input[name="zip[]"]');
        if ($postcodeField.length > 0) {
            $postcodeField.val(postcode);
            console.log('📮 Postcode set to field:', postcode, 'Field found:', $postcodeField.length);
        } else {
            // Try alternative field names
            const $altPostcode = $wrapper.find('input[name="postcode[]"]');
            if ($altPostcode.length > 0) {
                $altPostcode.val(postcode);
                console.log('📮 Postcode set to alternative field:', postcode);
            } else {
                console.warn('⚠️ Postcode field not found! Available inputs:', $wrapper.find('input').map(function() { return $(this).attr('name'); }).get());
            }
        }
        
        $wrapper.find('input[name="country[]"]').val(country);
        
        // Auto-calculate regional code
        if (postcode && window.isValidAustralianPostcode(postcode)) {
            const regionalInfo = window.getRegionalCodeInfo(postcode);
            $wrapper.find('input[name="regional_code[]"]').val(regionalInfo);
            console.log('🔢 Regional code auto-filled:', regionalInfo, 'from postcode:', postcode);
        } else {
            // Clear regional code if no valid postcode
            $wrapper.find('input[name="regional_code[]"]').val('');
            console.log('⚠️ No valid postcode - regional code cleared');
        }
        
        // Show appropriate message based on completeness
        if (!postcode) {
            const warningMessage = $('<div class="autocomplete-warning" style="color: #ff9800; font-size: 12px; margin-top: 5px;">⚠ Address populated but postcode is missing. Please enter manually.</div>');
            $wrapper.find('.autocomplete-warning, .autocomplete-success, .autocomplete-message').remove();
            $wrapper.find('.address-search-container').append(warningMessage);
            
            // Highlight the postcode field
            $wrapper.find('input[name="zip[]"]').css('border', '2px solid #ff9800');
            
            setTimeout(() => {
                warningMessage.fadeOut(() => warningMessage.remove());
                $wrapper.find('input[name="zip[]"]').css('border', '');
            }, 5000);
        } else {
            // Show success message only if complete
            const successMessage = $('<div class="autocomplete-success" style="color: #28a745; font-size: 12px; margin-top: 5px;">✓ Address populated successfully</div>');
            $wrapper.find('.autocomplete-success, .autocomplete-warning, .autocomplete-message').remove();
            $wrapper.find('.address-search-container').append(successMessage);
            
            setTimeout(() => {
                successMessage.fadeOut(() => successMessage.remove());
            }, 3000);
        }
    }
    
    /**
     * Close suggestions when clicking outside
     */
    function bindClickOutside() {
        $(document).on('click', function(e) {
            if (!$(e.target).closest('.address-search-container').length) {
                $('.autocomplete-suggestions').remove();
            }
        });
    }
    
    /**
     * Note: addAnotherAddress function is now handled by edit-client.js
     * This function has been removed to avoid conflicts
     */
    
    /**
     * Note: removeAddressEntry function is now handled by edit-client.js
     * This function has been removed to avoid conflicts with the main implementation
     */
    
})();

