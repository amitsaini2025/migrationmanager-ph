/**
 * Client Edit Page JavaScript
 * Contains all functionality for the client edit form
 */

// ===== SCROLL-TO-SECTION FUNCTIONALITY =====

// Define scrollToSection function IMMEDIATELY in global scope
window.scrollToSection = function(sectionId) {
    try {
        const section = document.getElementById(sectionId);
        if (section) {
            // Smooth scroll to section
            section.scrollIntoView({ 
                behavior: 'smooth', 
                block: 'start' 
            });
            
            // Update active tab button
            updateActiveTabButton(sectionId);
        } else {
            console.error('Section not found:', sectionId);
        }
    } catch (error) {
        console.error('Error in scrollToSection function:', error);
    }
};

// Update active nav item based on section
function updateActiveTabButton(sectionId) {
    // Remove active class from all nav items
    const navItems = document.querySelectorAll('.nav-item');
    navItems.forEach(item => {
        item.classList.remove('active');
    });
    
    // Add active class to the corresponding nav item
    const items = document.querySelectorAll('.nav-item');
    items.forEach(item => {
        const onclick = item.getAttribute('onclick');
        if (onclick && onclick.includes(sectionId)) {
            item.classList.add('active');
        }
    });
}

// Toggle sidebar for mobile
window.toggleSidebar = function() {
    const sidebar = document.getElementById('sidebarNav');
    if (sidebar) {
        sidebar.classList.toggle('open');
    }
};

// Close sidebar when clicking outside on mobile
document.addEventListener('click', function(event) {
    const sidebar = document.getElementById('sidebarNav');
    const toggle = document.querySelector('.sidebar-toggle');
    
    if (sidebar && toggle && window.innerWidth <= 1024) {
        if (!sidebar.contains(event.target) && !toggle.contains(event.target)) {
            sidebar.classList.remove('open');
        }
    }
});

// Close sidebar when clicking on nav item on mobile
document.addEventListener('click', function(event) {
    if (event.target.closest('.nav-item') && window.innerWidth <= 1024) {
        const sidebar = document.getElementById('sidebarNav');
        if (sidebar) {
            sidebar.classList.remove('open');
        }
    }
});

// Scroll spy functionality - update active nav item based on scroll position
function initScrollSpy() {
    const sections = document.querySelectorAll('.content-section');
    const navItems = document.querySelectorAll('.nav-item:not(.summary-nav)');
    
    if (sections.length === 0 || navItems.length === 0) return;
    
    function updateActiveNav() {
        let current = '';
        const scrollPosition = window.scrollY + 100; // Offset for better detection
        
        sections.forEach(section => {
            const sectionTop = section.offsetTop;
            const sectionHeight = section.offsetHeight;
            
            if (scrollPosition >= sectionTop && scrollPosition < sectionTop + sectionHeight) {
                current = section.getAttribute('id');
            }
        });
        
        if (current) {
            // Remove active class from all nav items
            navItems.forEach(item => item.classList.remove('active'));
            
            // Add active class to current nav item
            navItems.forEach(item => {
                const onclick = item.getAttribute('onclick');
                if (onclick && onclick.includes(current)) {
                    item.classList.add('active');
                }
            });
        }
    }
    
    // Throttle scroll events for better performance
    let ticking = false;
    window.addEventListener('scroll', function() {
        if (!ticking) {
            requestAnimationFrame(function() {
                updateActiveNav();
                ticking = false;
            });
            ticking = true;
        }
    });
    
    // Initial call
    updateActiveNav();
}

// ===== GO TO TOP BUTTON FUNCTIONALITY =====

// Scroll to top function
window.scrollToTop = function() {
    try {
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    } catch (error) {
        console.error('Error scrolling to top:', error);
        // Fallback for older browsers
        document.documentElement.scrollTop = 0;
    }
};

// Show/hide Go to Top button based on scroll position
function initGoToTopButton() {
    const goToTopBtn = document.getElementById('goToTopBtn');
    if (!goToTopBtn) return;
    
    function toggleGoToTopButton() {
        const scrollPosition = window.scrollY;
        const showThreshold = 300; // Show button after scrolling 300px
        
        if (scrollPosition > showThreshold) {
            if (!goToTopBtn.classList.contains('show')) {
                goToTopBtn.classList.remove('hide');
                goToTopBtn.classList.add('show');
            }
        } else {
            if (goToTopBtn.classList.contains('show')) {
                goToTopBtn.classList.remove('show');
                goToTopBtn.classList.add('hide');
                
                // Remove hide class after animation completes
                setTimeout(() => {
                    goToTopBtn.classList.remove('hide');
                }, 300);
            }
        }
    }
    
    // Throttle scroll events for better performance
    let ticking = false;
    window.addEventListener('scroll', function() {
        if (!ticking) {
            requestAnimationFrame(function() {
                toggleGoToTopButton();
                ticking = false;
            });
            ticking = true;
        }
    });
    
    // Initial call
    toggleGoToTopButton();
}





// ===== LEGACY TAB FUNCTIONALITY (KEPT FOR COMPATIBILITY) =====

// Define openTab function IMMEDIATELY in global scope (legacy support)
window.openTab = function(evt, tabName) {
    try {
        // Prevent default behavior
        if (evt && evt.preventDefault) {
            evt.preventDefault();
        }
        
        // Hide all tab content
        var tabcontent = document.getElementsByClassName("tab-content");
        for (var i = 0; i < tabcontent.length; i++) {
            tabcontent[i].style.display = "none";
        }
        
        // Remove active class from all tab buttons
        var tablinks = document.getElementsByClassName("tab-button");
        for (var i = 0; i < tablinks.length; i++) {
            tablinks[i].className = tablinks[i].className.replace(" active", "");
        }
        
        // Show the selected tab content
        var targetTab = document.getElementById(tabName);
        if (targetTab) {
            targetTab.style.display = "block";
        } else {
            console.error('Tab content not found:', tabName);
        }
        
        // Add active class to the clicked button
        if (evt && evt.currentTarget) {
            evt.currentTarget.className += " active";
        }
        
        // Fallback: if evt.currentTarget is not available, find the button by onclick attribute
        if (!evt || !evt.currentTarget) {
            var buttons = document.querySelectorAll('.tab-button');
            for (var i = 0; i < buttons.length; i++) {
                if (buttons[i].getAttribute('onclick') && buttons[i].getAttribute('onclick').includes(tabName)) {
                    buttons[i].className += " active";
                    break;
                }
            }
        }
        
        console.log('Tab switched to:', tabName);
        
    } catch (error) {
        console.error('Error in openTab function:', error);
        // Fallback: try to show the tab content directly
        try {
            var fallbackTab = document.getElementById(tabName);
            if (fallbackTab) {
                // Hide all tabs first
                var allTabs = document.getElementsByClassName("tab-content");
                for (var i = 0; i < allTabs.length; i++) {
                    allTabs[i].style.display = "none";
                }
                // Show target tab
                fallbackTab.style.display = "block";
            }
        } catch (fallbackError) {
            console.error('Fallback also failed:', fallbackError);
        }
    }
};

// Ensure openTab is globally available
window.openTab = openTab;

// Initialize tab functionality when DOM is ready
function initializeTabs() {
    try {
        // Set up event listeners for tab buttons as backup
        var tabButtons = document.querySelectorAll('.tab-button');
        tabButtons.forEach(function(button) {
            // Remove existing onclick to prevent conflicts
            var onclickAttr = button.getAttribute('onclick');
            if (onclickAttr && onclickAttr.includes('openTab')) {
                // Extract tab name from onclick
                var match = onclickAttr.match(/openTab\(event,\s*['"]([^'"]+)['"]\)/);
                if (match) {
                    var tabName = match[1];
                    // Add event listener as backup
                    button.addEventListener('click', function(e) {
                        openTab(e, tabName);
                    });
                }
            }
        });
        
        // Ensure first tab is active by default
        var firstTab = document.querySelector('.tab-button');
        var firstTabContent = document.getElementById('personalTab');
        if (firstTab && firstTabContent) {
            firstTab.classList.add('active');
            firstTabContent.style.display = 'block';
        }
        
        console.log('Tab functionality initialized successfully');
        
    } catch (error) {
        console.error('Error initializing tabs:', error);
    }
}

// Define validateForm function IMMEDIATELY to prevent "not defined" errors
window.validateForm = function() {
    const form = getClientEditForm();
    const errors = [];
    if (!form) {
        return false;
    }
    
    // Check all required fields
    const requiredFields = form.querySelectorAll('[required]');
    requiredFields.forEach(function(field) {
        const fieldName = field.name;
        let isValid = false;
        
        // Debug log for gender fields
        if (fieldName && (fieldName.includes('gender') || fieldName.includes('siblings_gender'))) {
            const tabContent = field.closest('.tab-content');
            const tabId = tabContent ? tabContent.id : 'unknown';
            console.log('Checking gender field:', fieldName, 'Value:', field.value, 'SelectedIndex:', field.selectedIndex, 'Tab:', tabId);
        }
        
        // Handle different field types
        if (field.tagName === 'SELECT') {
            // For select fields, check if a valid option is selected (not the first empty option)
            isValid = field.selectedIndex > 0 && field.value !== '';
        } else {
            // For other fields (input, textarea), check if they have a value
            isValid = field.value && field.value.trim() !== '';
        }
        
        // If field is valid, skip to next field
        if (isValid) {
            return;
        }
        
        // Field is invalid, add error with more context
        const label = field.closest('.form-group')?.querySelector('label')?.textContent?.trim() || field.name || 'Unknown field';
        
        // Add context about which tab/section the field is in
        let context = '';
        const tabContent = field.closest('.tab-content');
        if (tabContent) {
            const tabId = tabContent.id;
            if (tabId === 'personalTab') {
                context = ' (Personal tab)';
            } else if (tabId === 'familyTab') {
                context = ' (Family Information tab)';
            } else if (tabId === 'visaPassportCitizenshipTab') {
                context = ' (Visa, Passport & Citizenship tab)';
            } else if (tabId === 'addressTravelTab') {
                context = ' (Address & Travel tab)';
            } else if (tabId === 'skillsEducationTab') {
                context = ' (Skills & Education tab)';
            } else if (tabId === 'otherInformationTab') {
                context = ' (Other Information tab)';
            } else if (tabId === 'eoiReferenceTab') {
                context = ' (EOI Reference tab)';
            }
        }
        
        errors.push(`"${label}" is required${context}`);
    });
    
    // If there are errors, show them in alert and prevent submission
    if (errors.length > 0) {
        const errorMessage = 'Please fix the following errors:\n\n' + errors.join('\n');
        alert(errorMessage);
        return false;
    }
    
    return true;
};

// ADDITIONAL FALLBACK: Set up event listeners as backup
document.addEventListener('DOMContentLoaded', function() {
    // C3: block native full-form POST on personal/company edit.
    // Do NOT include editLeadForm — leads still submit to leads.update.
    // Real saves use type="button" section Save → /clients/save-section.
    var clientOrCompanyForm = document.getElementById('editCompanyForm')
        || document.getElementById('editClientForm');
    if (clientOrCompanyForm) {
        clientOrCompanyForm.addEventListener('submit', function(e) {
            e.preventDefault();
            if (typeof showNotification === 'function') {
                showNotification('Please use the Save button on each section to save changes.', 'info');
            } else {
                alert('Please use the Save button on each section to save changes.');
            }
        });
    }

    var tabButtons = document.querySelectorAll('.tab-button');
    tabButtons.forEach(function(button) {
        var onclickAttr = button.getAttribute('onclick');
        if (onclickAttr && onclickAttr.includes('openTab')) {
            var match = onclickAttr.match(/openTab\(event,\s*['"]([^'"]+)['"]\)/);
            if (match) {
                var tabName = match[1];
                // Add event listener as backup
                button.addEventListener('click', function(e) {
                    if (typeof window.openTab === 'function') {
                        window.openTab(e, tabName);
                    } else {
                        // Direct fallback if function still not available
                        try {
                            var tabcontent = document.getElementsByClassName("tab-content");
                            for (var i = 0; i < tabcontent.length; i++) {
                                tabcontent[i].style.display = "none";
                            }
                            var tablinks = document.getElementsByClassName("tab-button");
                            for (var i = 0; i < tablinks.length; i++) {
                                tablinks[i].className = tablinks[i].className.replace(" active", "");
                            }
                            var targetTab = document.getElementById(tabName);
                            if (targetTab) {
                                targetTab.style.display = "block";
                            }
                            e.currentTarget.className += " active";
                        } catch (directError) {
                            console.error('Direct fallback failed:', directError);
                        }
                    }
                });
            }
        }
    });
});

// ===== END TAB FUNCTIONALITY =====

// Initialize arrays to track IDs of records marked for deletion
let phoneNumbersToDelete = [];
let emailsToDelete = [];

// Cache visa types to avoid multiple AJAX calls
let visaTypesCache = null;
let countriesCache = null;

/**
 * Function to fetch visa types via AJAX
 */
async function fetchVisaTypes() {
    if (visaTypesCache) {
        return visaTypesCache; // Return cached data if available
    }

    // Get the route from window object (set from blade)
    const route = window.editClientConfig?.visaTypesRoute;
    if (!route) {
        console.error('Visa types route not configured');
        return [];
    }

    try {
        const response = await $.ajax({
            url: route,
            method: 'GET',
            dataType: 'json',
        });
        visaTypesCache = response; // Cache the response
        return response;
    } catch (error) {
        console.error('Error fetching visa types:', error);
        return [];
    }
}

/**
 * Function to fetch countries via AJAX
 */
async function fetchCountries() {
    if (countriesCache) {
        return countriesCache; // Return cached data if available
    }

    // Get the route from window object (set from blade)
    const route = window.editClientConfig?.countriesRoute;
    if (!route) {
        console.error('Countries route not configured');
        return [];
    }

    try {
        const response = await $.ajax({
            url: route,
            method: 'GET',
            dataType: 'json',
        });
        countriesCache = response; // Cache the response
        return response;
    } catch (error) {
        console.error('Error fetching countries:', error);
        return [];
    }
}

/**
 * Modified addPartnerRow to handle different types with hidden extra fields by default
 */
function addPartnerRow(type) {
    const sectionTypeMap = {
        partner: 'partnerInfo',
        children: 'childrenInfo',
        parent: 'parentsInfo',
        siblings: 'siblingsInfo',
        others: 'othersInfo',
    };
    const sectionType = sectionTypeMap[type] || 'partnerInfo';
    const summaryView = document.getElementById(sectionType + 'Summary');
    const editView = document.getElementById(sectionType + 'Edit');
    if (summaryView && editView) {
        const summaryHidden =
            summaryView.style.display === 'none' || summaryView.classList.contains('hidden');
        if (!summaryHidden) {
            toggleEditMode(sectionType);
        }
    }

    const containerId = type + 'Container';
    const container = document.getElementById(containerId);
    if (!container) {
        console.error('addPartnerRow: container not found:', containerId);
        return;
    }
    const index = container.children.length;

    let relationshipOptions = '';
    switch (type) {
        case 'children':
            relationshipOptions = `
                <option value="">Select Relationship</option>
                <option value="Son">Son</option>
                <option value="Daughter">Daughter</option>
                <option value="Step Son">Step Son</option>
                <option value="Step Daughter">Step Daughter</option>
            `;
            break;
        case 'parent':
            relationshipOptions = `
                <option value="">Select Relationship</option>
                <option value="Father">Father</option>
                <option value="Mother">Mother</option>
                <option value="Step Father">Step Father</option>
                <option value="Step Mother">Step Mother</option>
                <option value="Mother-in-law">Mother-in-law</option>
                <option value="Father-in-law">Father-in-law</option>
            `;
            break;
        case 'siblings':
            relationshipOptions = `
                <option value="">Select Relationship</option>
                <option value="Brother">Brother</option>
                <option value="Sister">Sister</option>
                <option value="Step Brother">Step Brother</option>
                <option value="Step Sister">Step Sister</option>
            `;
            break;
        case 'others':
            relationshipOptions = `
                <option value="">Select Relationship</option>
                <option value="Cousin">Cousin</option>
                <option value="Friend">Friend</option>
                <option value="Uncle">Uncle</option>
                <option value="Aunt">Aunt</option>
                <option value="Grandchild">Grandchild</option>
                <option value="Granddaughter">Granddaughter</option>
                <option value="Grandparent">Grandparent</option>
                <option value="Niece">Niece</option>
                <option value="Nephew">Nephew</option>
                <option value="Grandfather">Grandfather</option>
                <option value="Son-in-law">Son-in-law</option>
                <option value="Daughter-in-law">Daughter-in-law</option>
                <option value="Brother-in-law">Brother-in-law</option>
                <option value="Sister-in-law">Sister-in-law</option>
            `;
            break;
        default: // Partner
            relationshipOptions = `
                <option value="">Select Relationship</option>
                <option value="Husband">Husband</option>
                <option value="Wife">Wife</option>
                <option value="Ex-Husband">Ex-Husband</option>
                <option value="Ex-Wife">Ex-Wife</option>
                <option value="Defacto">De Facto</option>
                <option value="Engaged">Engaged</option>
            `;
    }

    container.insertAdjacentHTML('beforeend', `
        <div class="repeatable-section">
            <button type="button" class="remove-item-btn" title="Remove ${type.charAt(0).toUpperCase() + type.slice(1)}" onclick="removePartnerRow(this, '${type}')">${crmI('fas fa-times-circle')}</button>
            <input type="hidden" name="${type}_id[${index}]" class="partner-id">
            <div class="content-grid">
                <div class="form-group">
                    <label>Details</label>
                    <input type="text" name="${type}_details[${index}]" class="partner-details" placeholder="Search by Name, Email, Client ID, or Phone">
                    <div class="autocomplete-items"></div>
                </div>
                <div class="form-group">
                    <label>Relationship Type <span class="text-danger">*</span></label>
                    <select name="${type}_relationship_type[${index}]" required>
                        ${relationshipOptions}
                    </select>
                </div>
                <div class="form-group">
                    <label>Gender <span class="text-danger">*</span></label>
                    <select name="${type}_gender[${index}]" required>
                        <option value="">Select Gender</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Company Type <span class="text-danger">*</span></label>
                    <select name="${type}_company_type[${index}]" required>
                        <option value="">Select Company Type</option>
                        <option value="Accompany Member">Accompany Member</option>
                        <option value="Non-Accompany Member">Non-Accompany Member</option>
                    </select>
                </div>
            </div>
            <div class="partner-extra-fields" style="display: none;">
                <div class="content-grid single-row">
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="${type}_email[${index}]" placeholder="Enter Email">
                    </div>
                    <div class="form-group">
                        <label>First Name</label>
                        <input type="text" name="${type}_first_name[${index}]" placeholder="Enter First Name">
                    </div>
                    <div class="form-group">
                        <label>Last Name <span class="text-danger">*</span></label>
                        <input type="text" name="${type}_last_name[${index}]" placeholder="Enter Last Name">
                    </div>
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="text" name="${type}_phone[${index}]" placeholder="Enter Phone">
                    </div>
                    <div class="form-group">
                        <label>DOB <span class="text-danger">*</span></label>
                        <input type="text" name="${type}_dob[${index}]"  placeholder="dd/mm/yyyy" class="date-picker">
                    </div>
                </div>
            </div>
        </div>
    `);

    // Reinitialize datepickers for the newly added fields
    initializeDatepickers();
}

/**
 * Modified removePartnerRow to handle different types
 */
function removePartnerRow(button, type, relationshipId = null) {
    const section = button.closest('.repeatable-section');
    const confirmDelete = confirm(`Are you sure you want to delete this ${type} record?`);

    if (confirmDelete) {
        if (relationshipId) {
            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = `delete_${type}_ids[]`;
            hiddenInput.value = relationshipId;
            const form = getClientEditForm();
            if (form) {
                form.appendChild(hiddenInput);
            } else {
                console.error('removePartnerRow: edit form not found');
            }
        }
        section.remove();
    }
}

/**
 * Function to add a new EOI Reference row
 */
function addEoiReference() {
    // Check if we're in summary mode, if so switch to edit mode first
    const summaryView = document.getElementById('eoiInfoSummary');
    const editView = document.getElementById('eoiInfoEdit');
    
    if (summaryView && editView) {
        const summaryHidden =
            summaryView.style.display === 'none' || summaryView.classList.contains('hidden');
        if (!summaryHidden) {
            toggleEditMode('eoiInfo');
        }
    }
    
    const container = document.getElementById('eoiReferencesContainer');
    const index = container.children.length;

    container.insertAdjacentHTML('beforeend', `
        <div class="repeatable-section">
            <button type="button" class="remove-item-btn" title="Remove EOI Reference" onclick="removeEoiField(this)">${crmI('fas fa-trash')}</button>
            <div class="content-grid" style="grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px;">
                <div class="form-group">
                    <label>EOI Number</label>
                    <input type="text" name="EOI_number[${index}]" placeholder="EOI Number">
                </div>
                <div class="form-group">
                    <label>Subclass</label>
                    <input type="text" name="EOI_subclass[${index}]" placeholder="Subclass">
                </div>
                <div class="form-group">
                    <label>Occupation</label>
                    <input type="text" name="EOI_occupation[${index}]" placeholder="Occupation">
                </div>
                <div class="form-group">
                    <label>Point</label>
                    <input type="text" name="EOI_point[${index}]" placeholder="Point">
                </div>
                <div class="form-group">
                    <label>State</label>
                    <input type="text" name="EOI_state[${index}]" placeholder="State">
                </div>
                <div class="form-group">
                    <label>Submission Date</label>
                    <input type="text" name="EOI_submission_date[${index}]" placeholder="dd/mm/yyyy" class="date-picker">
                </div>
                <div class="form-group">
                    <label>ROI</label>
                    <input type="text" name="EOI_ROI[${index}]" placeholder="ROI">
                </div>
            </div>
        </div>
    `);

    // Reinitialize datepickers for the newly added field
    initializeDatepickers();
}

/**
 * Toggle Visa Details and Visa Expiry Verified based on passport country
 */
function toggleVisaDetails() {
    const passportCountrySelector = document.getElementById('passportCountry');
    const visaDetailsSection = document.getElementById('visaDetailsSection');
    const addVisaButton = document.querySelector('button[onclick="addVisaDetail()"]');
    const visaExpiryVerifiedContainer = document.getElementById('visaExpiryVerifiedContainer');

    // Check if passportCountry element exists before accessing its value
    if (!passportCountrySelector) {
        return;
    }

    const isAustralia = passportCountrySelector.value === 'Australia';

    if (isAustralia) {
        visaDetailsSection.style.display = 'none';
        addVisaButton.style.display = 'none';
        visaExpiryVerifiedContainer.style.display = 'none';
    } else {
        visaDetailsSection.style.display = 'block';
        addVisaButton.style.display = 'block';
        visaExpiryVerifiedContainer.style.display = 'flex';
    }
}

/**
 * Add Passport Detail
 */
function addPassportDetail() {
    // Check if we're in summary mode, if so switch to edit mode first
    const summaryView = document.getElementById('passportInfoSummary');
    const editView = document.getElementById('passportInfoEdit');
    
    if (summaryView && editView && summaryView.style.display !== 'none') {
        toggleEditMode('passportInfo');
    }
    
    const container = document.getElementById('passportDetailsContainer');
    const index = container.children.length;

    // Build country options from the countries data passed from PHP
    let countryOptions = '<option value="">Select Country</option>';
    
    // Add India and Australia first (priority countries)
    countryOptions += '<option value="India">India</option>';
    countryOptions += '<option value="Australia">Australia</option>';
    
    // Add all other countries from the database
    if (window.countriesData && Array.isArray(window.countriesData)) {
        window.countriesData.forEach(country => {
            // Skip India and Australia as they're already added above
            if (country.name !== 'India' && country.name !== 'Australia') {
                countryOptions += `<option value="${country.name}">${country.name}</option>`;
            }
        });
    }

    container.insertAdjacentHTML('beforeend', `
        <div class="repeatable-section">
            <button type="button" class="remove-item-btn" title="Remove Passport" onclick="removePassportField(this)">${crmI('fas fa-trash')}</button>
            <div class="content-grid">
                <div class="form-group">
                    <label>Country</label>
                    <select name="passports[${index}][passport_country]" class="passport-country-field">
                        ${countryOptions}
                    </select>
                </div>
                <div class="form-group">
                    <label>Passport #</label>
                    <input type="text" name="passports[${index}][passport_number]" placeholder="Passport Number">
                </div>
                <div class="form-group">
                    <label>Issue Date</label>
                    <input type="text" name="passports[${index}][issue_date]" placeholder="dd/mm/yyyy" class="date-picker date-picker-past-only">
                </div>
                <div class="form-group">
                    <label>Expiry Date</label>
                    <input type="text" name="passports[${index}][expiry_date]" placeholder="dd/mm/yyyy" class="date-picker">
                </div>
            </div>
        </div>
    `);

    // Reinitialize datepickers for the newly added fields
    initializeDatepickers();
}

/**
 * Parse dd/mm/yyyy address start date for current-address comparison.
 */
function parseAddressStartDateMs(value) {
    if (!value || !String(value).trim()) {
        return null;
    }
    const parts = String(value).trim().split('/');
    if (parts.length !== 3) {
        return null;
    }
    const day = parseInt(parts[0], 10);
    const month = parseInt(parts[1], 10) - 1;
    const year = parseInt(parts[2], 10);
    const date = new Date(year, month, day);
    if (isNaN(date.getTime()) || date.getDate() !== day || date.getMonth() !== month || date.getFullYear() !== year) {
        return null;
    }
    return date.getTime();
}

/**
 * Matches ClientAddress::ORDER_BY_DISPLAY — latest start_date, then id DESC.
 */
function isBetterCurrentAddressCandidate(candidate, winner) {
    if (candidate.hasDate !== winner.hasDate) {
        return candidate.hasDate;
    }
    if (candidate.hasDate && candidate.dateMs !== winner.dateMs) {
        return candidate.dateMs > winner.dateMs;
    }
    if (candidate.addressId !== winner.addressId) {
        return candidate.addressId > winner.addressId;
    }
    return candidate.idx > winner.idx;
}

function createReadonlyCurrentToggle() {
    const div = document.createElement('div');
    div.className = 'address-current-toggle address-current-readonly';
    div.innerHTML = `
        <span class="address-current-label">Current Address?</span>
        <label class="switch switch-readonly" title="Current address (based on latest start date)">
            <input type="checkbox" checked disabled>
            <span class="slider round"></span>
        </label>
    `;
    return div;
}

/**
 * Show readonly current-address indicator on the row with the latest start date only.
 */
function syncCurrentAddressIndicators() {
    const container = document.getElementById('addresses-container');
    if (!container) {
        return;
    }

    const wrappers = Array.from(container.querySelectorAll('.address-entry-wrapper'));
    if (wrappers.length === 0) {
        return;
    }

    let winnerIdx = 0;
    let winnerScore = null;

    wrappers.forEach(function(wrapper, idx) {
        const startInput = wrapper.querySelector('input[name="address_start_date[]"]');
        const addressId = parseInt(wrapper.querySelector('input[name="address_id[]"]')?.value || '0', 10) || 0;
        const dateMs = parseAddressStartDateMs(startInput?.value || '');
        const score = {
            hasDate: dateMs !== null,
            dateMs: dateMs ?? 0,
            addressId: addressId,
            idx: idx
        };

        if (winnerScore === null || isBetterCurrentAddressCandidate(score, winnerScore)) {
            winnerIdx = idx;
            winnerScore = score;
        }
    });

    wrappers.forEach(function(wrapper, idx) {
        let header = wrapper.querySelector('.address-entry-header');
        if (!header) {
            header = document.createElement('div');
            header.className = 'address-entry-header';
            const hiddenInput = wrapper.querySelector('input[name="address_id[]"]');
            if (hiddenInput) {
                hiddenInput.insertAdjacentElement('afterend', header);
            } else {
                wrapper.prepend(header);
            }
        }

        const existingToggle = header.querySelector('.address-current-readonly');
        const isWinner = idx === winnerIdx;

        if (isWinner) {
            if (!existingToggle) {
                const removeBtn = header.querySelector('.remove-address-btn');
                if (removeBtn) {
                    header.insertBefore(createReadonlyCurrentToggle(), removeBtn);
                } else {
                    header.appendChild(createReadonlyCurrentToggle());
                }
            }
        } else if (existingToggle) {
            existingToggle.remove();
        }

        if (!header.children.length) {
            header.style.display = 'none';
        } else {
            header.style.display = '';
        }
    });
}

/**
 * Matches ClientExperience::ORDER_BY_DISPLAY — ongoing (no finish) first,
 * then latest job_finish_date, then latest job_start_date, then id DESC.
 */
function isBetterCurrentExperienceCandidate(candidate, winner) {
    // Empty finish date = ongoing employment = Current
    if (candidate.hasFinishDate !== winner.hasFinishDate) {
        return !candidate.hasFinishDate;
    }
    if (candidate.hasFinishDate && candidate.finishDateMs !== winner.finishDateMs) {
        return candidate.finishDateMs > winner.finishDateMs;
    }
    if (candidate.hasStartDate !== winner.hasStartDate) {
        return candidate.hasStartDate;
    }
    if (candidate.hasStartDate && candidate.startDateMs !== winner.startDateMs) {
        return candidate.startDateMs > winner.startDateMs;
    }
    if (candidate.experienceId !== winner.experienceId) {
        return candidate.experienceId > winner.experienceId;
    }
    return candidate.idx > winner.idx;
}

function scoreExperienceCandidate(experienceOrWrapper, idx) {
    const isDom = experienceOrWrapper && experienceOrWrapper.querySelector;
    const finishValue = isDom
        ? (experienceOrWrapper.querySelector('input[name*="job_finish_date"]')?.value || '')
        : (experienceOrWrapper.job_finish_date || '');
    const startValue = isDom
        ? (experienceOrWrapper.querySelector('input[name*="job_start_date"]')?.value || '')
        : (experienceOrWrapper.job_start_date || '');
    const experienceId = isDom
        ? (parseInt(experienceOrWrapper.querySelector('input[name*="experience_id"]')?.value || '0', 10) || 0)
        : (parseInt(experienceOrWrapper.experience_id || '0', 10) || 0);
    const finishDateMs = parseAddressStartDateMs(finishValue);
    const startDateMs = parseAddressStartDateMs(startValue);

    return {
        hasFinishDate: finishDateMs !== null,
        finishDateMs: finishDateMs ?? 0,
        hasStartDate: startDateMs !== null,
        startDateMs: startDateMs ?? 0,
        experienceId: experienceId,
        idx: idx
    };
}

function createReadonlyCurrentExperienceToggle() {
    const div = document.createElement('div');
    div.className = 'experience-current-toggle experience-current-readonly';
    div.innerHTML = `
        <span class="experience-current-label">Current Experience?</span>
        <label class="switch switch-readonly" title="Current experience (ongoing / no finish date, else latest finish)">
            <input type="checkbox" checked disabled>
            <span class="slider round"></span>
        </label>
    `;
    return div;
}

/**
 * Show readonly current-experience indicator on the ongoing (no finish) row,
 * else the row with the latest finish date.
 */
function syncCurrentExperienceIndicators() {
    const container = document.getElementById('experienceContainer');
    if (!container) {
        return;
    }

    const wrappers = Array.from(container.querySelectorAll('.experience-entry-wrapper'));
    if (wrappers.length === 0) {
        return;
    }

    let winnerIdx = 0;
    let winnerScore = null;

    wrappers.forEach(function(wrapper, idx) {
        const score = scoreExperienceCandidate(wrapper, idx);

        if (winnerScore === null || isBetterCurrentExperienceCandidate(score, winnerScore)) {
            winnerIdx = idx;
            winnerScore = score;
        }
    });

    wrappers.forEach(function(wrapper, idx) {
        let header = wrapper.querySelector('.experience-entry-header');
        if (!header) {
            header = document.createElement('div');
            header.className = 'experience-entry-header';
            wrapper.prepend(header);
        }

        const existingToggle = header.querySelector('.experience-current-readonly');
        const isWinner = idx === winnerIdx;

        if (isWinner) {
            if (!existingToggle) {
                const removeBtn = header.querySelector('.remove-item-btn');
                if (removeBtn) {
                    header.insertBefore(createReadonlyCurrentExperienceToggle(), removeBtn);
                } else {
                    header.appendChild(createReadonlyCurrentExperienceToggle());
                }
            }
        } else if (existingToggle) {
            existingToggle.remove();
        }
    });
}

function sortExperiencesForDisplay(experiences) {
    return [...experiences].sort(function(a, b) {
        const scoreA = scoreExperienceCandidate(a, 0);
        const scoreB = scoreExperienceCandidate(b, 0);

        if (isBetterCurrentExperienceCandidate(scoreA, scoreB)) {
            return -1;
        }
        if (isBetterCurrentExperienceCandidate(scoreB, scoreA)) {
            return 1;
        }
        return 0;
    });
}

function buildExperienceCurrentSummaryHtml() {
    return `
        <div class="summary-item-inline experience-current-summary">
            <span class="summary-label">CURRENT:</span>
            <label class="switch switch-readonly" style="margin: 0;" title="Current experience">
                <input type="checkbox" checked disabled>
                <span class="slider round"></span>
            </label>
        </div>
    `;
}

/**
 * Add Another Address (for new component system)
 */
function addAnotherAddress() {
    console.log('🚀 addAnotherAddress called from edit-client.js');
    
    // Note: Mode switching is handled by addAddress() when called from header button
    // This function should only add a new address entry to the container
    
    const container = document.getElementById('addresses-container');
    if (!container) {
        console.error('❌ Address container not found');
        return;
    }
    
    // Get the current number of address entries
    const existingEntries = container.querySelectorAll('.address-entry-wrapper');
    const index = existingEntries.length;
    
    console.log(`📝 Adding address entry at index: ${index}`);
    console.log(`📊 Current entries count: ${existingEntries.length}`);
    
    const addressHTML = `
        <div class="address-entry-wrapper" data-address-index="${index}">
            <input type="hidden" name="address_id[]" value="">

            <div class="address-entry-header">
                <button type="button" class="remove-address-btn" onclick="removeAddressEntry(this)" title="Remove Address">
                    ${crmI('fas fa-times')}
                </button>
            </div>
            
            <div class="form-group address-search-container">
                <label for="address_search_${index}">Search Address</label>
                <input type="text" 
                       id="address_search_${index}" 
                       name="address_search[]" 
                       class="address-search-input" 
                       placeholder="Start typing an address..."
                       autocomplete="off"
                       data-address-index="${index}">
            </div>
            
            <div class="address-fields-grid">
                <div class="form-group">
                    <label for="address_line_1_${index}">Address Line 1 *</label>
                    <input type="text" 
                           id="address_line_1_${index}" 
                           name="address_line_1[]" 
                           placeholder="Street number and name"
                           class="address-required">
                </div>
                
                <div class="form-group">
                    <label for="address_line_2_${index}">Address Line 2</label>
                    <input type="text" 
                           id="address_line_2_${index}" 
                           name="address_line_2[]" 
                           placeholder="Apartment, suite, unit, etc.">
                </div>
            </div>
            
            <div class="address-fields-grid">
                <div class="form-group">
                    <label for="suburb_${index}">Suburb *</label>
                    <input type="text" 
                           id="suburb_${index}" 
                           name="suburb[]" 
                           placeholder="Suburb"
                           class="address-required">
                </div>
                
                <div class="form-group">
                    <label for="state_${index}">State *</label>
                    <input type="text" 
                           id="state_${index}" 
                           name="state[]" 
                           placeholder="State"
                           class="address-required">
                </div>
            </div>
            
            <div class="address-fields-grid">
                <div class="form-group">
                    <label for="zip_${index}">Postcode *</label>
                    <input type="text" 
                           id="zip_${index}" 
                           name="zip[]" 
                           placeholder="Postcode"
                           class="address-required">
                </div>
                
                <div class="form-group">
                    <label for="country_${index}">Country *</label>
                    <input type="text" 
                           id="country_${index}" 
                           name="country[]" 
                           value="Australia"
                           placeholder="Country"
                           class="address-required">
                </div>
            </div>
            
            <div class="form-group">
                <label for="regional_code_${index}">Regional Code</label>
                <input type="text" 
                       id="regional_code_${index}" 
                       name="regional_code[]" 
                       placeholder="Regional code (auto-calculated)"
                       class="regional-code-field"
                       readonly>
            </div>
            
            <div class="date-fields">
                <div class="form-group">
                    <label for="address_start_date_${index}">Start Date</label>
                    <input type="text" 
                           id="address_start_date_${index}" 
                           name="address_start_date[]" 
                           placeholder="dd/mm/yyyy"
                           class="date-picker date-picker-past-only">
                </div>
                
                <div class="form-group">
                    <label for="address_end_date_${index}">End Date</label>
                    <input type="text" 
                           id="address_end_date_${index}" 
                           name="address_end_date[]" 
                           placeholder="dd/mm/yyyy"
                           class="date-picker date-picker-past-only">
                </div>
            </div>
        </div>
    `;
    
    container.insertAdjacentHTML('beforeend', addressHTML);

    // Get the newly added wrapper element
    const newWrapper = container.querySelector(`.address-entry-wrapper[data-address-index="${index}"]`);
    
    // Reinitialize date pickers for all fields (including the new one)
    if (typeof initializeDatepickers === 'function') {
        initializeDatepickers();
    }
    
    // Initialize autocomplete for the new address field
    // Note: Event listeners use delegation, so they work automatically
    // But we need to initialize datepickers for the new field
    if (typeof window.initAddressAutocompleteForNewField === 'function' && newWrapper) {
        // Use jQuery wrapper for consistency with address-autocomplete.js
        window.initAddressAutocompleteForNewField($(newWrapper));
    } else if (typeof initAddressAutocomplete === 'function') {
        // Fallback: Re-initialize everything (less efficient but works)
        initAddressAutocomplete();
    } else {
        // Last resort: Just initialize Flatpickr manually
        if (typeof flatpickr !== 'undefined' && newWrapper) {
            $(newWrapper).find('.date-picker').each(function() {
                if (!$(this).data('flatpickr')) {
                    const isPastOnly = $(this).hasClass('date-picker-past-only');
                    flatpickr(this, {
                        dateFormat: 'd/m/Y',
                        allowInput: true,
                        clickOpens: true,
                        maxDate: isPastOnly ? 'today' : undefined,
                        locale: {
                            firstDayOfWeek: 1
                        }
                    });
                }
            });
        }
    }
    
    console.log(`✅ Added new address entry with index: ${index}`);
    console.log(`📊 Total address entries now: ${container.querySelectorAll('.address-entry-wrapper').length}`);

    syncCurrentAddressIndicators();
}

/**
 * Remove Address Entry
 */
function removeAddressEntry(button) {
    if (confirm('Are you sure you want to remove this address?')) {
        const wrapper = button.closest('.address-entry-wrapper');
        wrapper.remove();
        syncCurrentAddressIndicators();
    }
}

/**
 * Add Address (old function kept for backward compatibility)
 */
function addAddress() {
    // Check if we're in summary mode, if so switch to edit mode first
    const summaryView = document.getElementById('addressInfoSummary');
    const editView = document.getElementById('addressInfoEdit');
    
    if (summaryView && editView && summaryView.style.display !== 'none') {
        toggleEditMode('addressInfo');
        
        // Wait for edit mode to be fully visible before adding address
        setTimeout(function() {
            if (typeof addAnotherAddress === 'function') {
                addAnotherAddress();
            }
        }, 150);
        return;
    }
    
    // If already in edit mode, add immediately
    if (typeof addAnotherAddress === 'function') {
        addAnotherAddress();
        return;
    }
    
    // Fallback to manual row addition (legacy system)
    const container = document.getElementById('address-fields-wrapper');
    if (!container) {
        console.error('Address container not found');
        return;
    }
    const index = container.querySelectorAll('.address-fields').length;

    container.insertAdjacentHTML('beforeend', `
        <div class="address-fields row mb-3">
            <div class="col-sm-3">
                <div class="form-group">
                    <label for="zip">Post Code</label>
                    <input type="text" name="zip[]" class="form-control postal_code" autocomplete="off" placeholder="Enter Post Code">
                    <div class="autocomplete-items"></div>
                </div>
            </div>
            <div class="col-sm-3">
                <div class="form-group">
                    <label for="address">Address</label>
                    <input type="text" name="address[]" class="form-control address-input" autocomplete="off" placeholder="Search Box">
                </div>
            </div>
            <div class="col-sm-2">
                <div class="form-group">
                    <label for="regional_code">Regional Code Info</label>
                    <input type="text" name="regional_code[]"  class="form-control regional_code_info" placeholder="Regional Code info" readonly>
                </div>
            </div>
            <div class="col-sm-2">
                <div class="form-group">
                    <label for="address_start_date">Start Date</label>
                    <input type="text" name="address_start_date[]" class="form-control date-picker" placeholder="dd/mm/yyyy">
                </div>
            </div>
            <div class="col-sm-2">
                <div class="form-group">
                    <label for="address_end_date">End Date</label>
                    <input type="text" name="address_end_date[]" class="form-control date-picker" placeholder="dd/mm/yyyy">
                </div>
            </div>
            <div class="col-sm-1 d-flex align-items-center">
                <button type="button" class="btn btn-primary add-row-btn">+</button>
            </div>
        </div>
    `);
    
    // Reinitialize datepickers for the newly added fields
    initializeDatepickers();
}

/**
 * Add Travel Detail
 */
async function addTravelDetail() {
    // Check if we're in summary mode, if so switch to edit mode first
    const summaryView = document.getElementById('travelInfoSummary');
    const editView = document.getElementById('travelInfoEdit');
    
    if (summaryView && editView && summaryView.style.display !== 'none') {
        toggleEditMode('travelInfo');
    }
    
    const container = document.getElementById('travelDetailsContainer');
    const index = container.children.length;

    // Get country options from existing select if available, otherwise use default
    let countryOptionsHtml = '<option value="">Select Country</option>';
    const existingSelect = document.querySelector('.travel-country-field');
    if (existingSelect) {
        Array.from(existingSelect.options).forEach(option => {
            if (option.value !== '') { // Skip the "Select Country" option
                countryOptionsHtml += `<option value="${option.value}">${option.text}</option>`;
            }
        });
    } else {
        // Fallback if no existing select found - fetch countries
        const countries = await fetchCountries();
        countries.forEach(country => {
            countryOptionsHtml += `<option value="${country}">${country}</option>`;
        });
    }

    container.insertAdjacentHTML('beforeend', `
        <div class="repeatable-section">
            <button type="button" class="remove-item-btn" title="Remove Travel" onclick="removeTravelField(this)">${crmI('fas fa-trash')}</button>
            <div class="content-grid">
                <div class="form-group">
                    <label>Country Visited</label>
                    <select name="travel_country_visited[${index}]" class="travel-country-field">
                        ${countryOptionsHtml}
                    </select>
                </div>
                <div class="form-group">
                    <label>Arrival Date</label>
                    <input type="text" name="travel_arrival_date[${index}]" placeholder="dd/mm/yyyy" class="date-picker date-picker-past-only">
                </div>
                <div class="form-group">
                    <label>Departure Date</label>
                    <input type="text" name="travel_departure_date[${index}]" placeholder="dd/mm/yyyy" class="date-picker date-picker-past-only">
                </div>
                <div class="form-group">
                    <label>Travel Purpose</label>
                    <input type="text" name="travel_purpose[${index}]" placeholder="Travel Purpose">
                </div>
            </div>
        </div>
    `);

    // Reinitialize datepickers for the newly added fields
    initializeDatepickers();
}

/**
 * Function to calculate age from date of birth (expects dd/mm/yyyy format)
 */
function calculateAge(dob) {
    if (!dob || !/^\d{2}\/\d{2}\/\d{4}$/.test(dob)) return '';

    try {
        const [day, month, year] = dob.split('/').map(Number);
        const dobDate = new Date(year, month - 1, day);
        if (isNaN(dobDate.getTime())) return ''; // Invalid date

        const today = new Date();
        let years = today.getFullYear() - dobDate.getFullYear();
        let months = today.getMonth() - dobDate.getMonth();

        if (months < 0) {
            years--;
            months += 12;
        }

        if (today.getDate() < dobDate.getDate()) {
            months--;
            if (months < 0) {
                years--;
                months += 12;
            }
        }

        return years + ' years ' + months + ' months';
    } catch (e) {
        return '';
    }
}

/**
 * Add Phone Number (Updated to exclude verification slider in repeatable section)
 */
function addPhoneNumber() {
    // Check if we're in summary mode, if so switch to edit mode first
    const summaryView = document.getElementById('phoneNumbersSummary');
    const editView = document.getElementById('phoneNumbersEdit');
    
    if (summaryView && editView && summaryView.style.display !== 'none') {
        toggleEditMode('phoneNumbers');
    }
    
    const container = document.getElementById('phoneNumbersContainer');
    const index = container.children.length;

    const defaultDial = (typeof window.phoneDefaultDialCode !== 'undefined' && window.phoneDefaultDialCode)
        ? window.phoneDefaultDialCode
        : '+61';
    const dialOpts = typeof window.buildPhoneDialCodeOptionsHtml === 'function'
        ? window.buildPhoneDialCodeOptionsHtml(defaultDial, { showPlaceholder: false })
        : '<option value="+61" selected>+61 (Australia)</option>';

    container.insertAdjacentHTML('beforeend', `
        <div class="repeatable-section">
            <button type="button" class="remove-item-btn" title="Remove Phone" onclick="removePhoneField(this)">${crmI('fas fa-trash')}</button>
            <div class="content-grid">
                <div class="form-group">
                    <label>Type</label>
                    <select name="contact_type_hidden[${index}]" class="contact-type-selector">
                        <option value="Personal">Personal</option>
                        <option value="Work">Work</option>
                        <option value="Mobile">Mobile</option>
                        <option value="Business">Business</option>
                        <option value="Secondary">Secondary</option>
                        <option value="Father">Father</option>
                        <option value="Mother">Mother</option>
                        <option value="Brother">Brother</option>
                        <option value="Sister">Sister</option>
                        <option value="Uncle">Uncle</option>
                        <option value="Aunt">Aunt</option>
                        <option value="Cousin">Cousin</option>
                        <option value="Others">Others</option>
                        <option value="Partner">Partner</option>
                        <option value="Not In Use">Not In Use</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Number</label>
                    <div class="cus_field_input" style="display:flex;">
                        <div class="country_code">
                            <select name="country_code[${index}]" class="country-code-input">${dialOpts}</select>
                        </div>
                                                    <input type="tel" name="phone[${index}]" placeholder="Phone Number" class="phone-number-input" style="width: 140px;" autocomplete="off">
                    </div>
                </div>
            </div>
        </div>
    `);
    validatePersonalPhoneNumbers();
}

/**
 * Function to validate that "Personal" phone numbers are unique
 */
function validatePersonalPhoneNumbers() {
    const container = document.getElementById('phoneNumbersContainer');
    const sections = container.getElementsByClassName('repeatable-section');
    const personalPhones = {};

    // Clear previous error messages
    Array.from(sections).forEach(section => {
        const errorSpan = section.querySelector('.text-danger');
        if (errorSpan) errorSpan.remove();
    });

    // Check for duplicate "Personal" phone numbers
    Array.from(sections).forEach((section, index) => {
        const type = section.querySelector('.contact-type-selector').value;
        const countryCode = section.querySelector('.country-code-input').value;
        const phone = section.querySelector('.phone-number-input').value;
        const fullPhone = countryCode + phone;

        if (type === 'Personal' && phone) {
            // Validate phone number first
            const validation = validatePhoneNumber(phone);
            if (!validation.valid) {
                const errorMessage = `<span class="text-danger">Personal phone number: ${validation.message}</span>`;
                section.querySelector('.content-grid').insertAdjacentHTML('afterend', errorMessage);
                // Disable the submit button
                const submitButton = document.querySelector('button[type="submit"]');
                if (submitButton) {
                    submitButton.disabled = true;
                }
                return;
            }

            // Skip duplicate check for placeholder numbers
            if (!validation.isPlaceholder && personalPhones[fullPhone]) {
                // Duplicate found
                const errorMessage = `<span class="text-danger">Personal phone number ${fullPhone} is already used in another entry.</span>`;
                section.querySelector('.content-grid').insertAdjacentHTML('afterend', errorMessage);
                // Disable the submit button
                const submitButton = document.querySelector('button[type="submit"]');
                if (submitButton) {
                    submitButton.disabled = true;
                }
            } else if (!validation.isPlaceholder) {
                personalPhones[fullPhone] = true;
            }
        }
    });

    // Re-enable the submit button if no duplicates are found
    if (!Object.keys(personalPhones).some(phone => personalPhones[phone] === true && Object.keys(personalPhones).filter(p => p === phone).length > 1)) {
        const submitButton = document.querySelector('button[type="submit"]');
        if (submitButton) {
            submitButton.disabled = false;
        }
    }
}

/**
 * Add Email Address (Updated to exclude verification slider in repeatable section)
 */
function addEmailAddress() {
    // Check if we're in summary mode, if so switch to edit mode first
    const summaryView = document.getElementById('emailAddressesSummary');
    const editView = document.getElementById('emailAddressesEdit');
    
    if (summaryView && editView && summaryView.style.display !== 'none') {
        toggleEditMode('emailAddresses');
    }
    
    const container = document.getElementById('emailAddressesContainer');
    const index = container.children.length;
    container.insertAdjacentHTML('beforeend', `
        <div class="repeatable-section">
            <button type="button" class="remove-item-btn" title="Remove Email" onclick="removeEmailField(this)">${crmI('fas fa-trash')}</button>
            <div class="content-grid">
                <div class="form-group">
                    <label>Type</label>
                    <select name="email_type_hidden[${index}]" class="email-type-selector">
                        <option value="Personal">Personal</option>
                        <option value="Work">Work</option>
                        <option value="Business">Business</option>
                        <option value="Mobile">Mobile</option>
                        <option value="Secondary">Secondary</option>
                        <option value="Father">Father</option>
                        <option value="Mother">Mother</option>
                        <option value="Brother">Brother</option>
                        <option value="Sister">Sister</option>
                        <option value="Uncle">Uncle</option>
                        <option value="Aunt">Aunt</option>
                        <option value="Cousin">Cousin</option>
                        <option value="Others">Others</option>
                        <option value="Partner">Partner</option>
                        <option value="Not In Use">Not In Use</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email[${index}]" placeholder="Enter Email Address">
                </div>
            </div>
        </div>
    `);
    validatePersonalEmailTypes();
}

/**
 * Function to validate that there is at most one "Personal" type for emails
 */
function validatePersonalEmailTypes() {
    const container = document.getElementById('emailAddressesContainer');
    const sections = container.getElementsByClassName('repeatable-section');
    let personalCount = 0;

    // Clear previous error messages
    Array.from(sections).forEach(section => {
        const errorSpan = section.querySelector('.text-danger-email-personal');
        if (errorSpan) errorSpan.remove();
    });

    // Count "Personal" types
    Array.from(sections).forEach((section, index) => {
        const type = section.querySelector('.email-type-selector').value;
        if (type === 'Personal') {
            personalCount++;
            if (personalCount > 1) {
                // Display error message
                const errorMessage = `<span class="text-danger text-danger-email-personal">Only one email address can be of type Personal.</span>`;
                section.querySelector('.form-group').insertAdjacentHTML('afterend', errorMessage);
            }
        }
    });

    // Enable or disable the submit button based on validation
    const submitButton = document.querySelector('button[type="submit"]');
    if (submitButton) {
        if (personalCount > 1) {
            submitButton.disabled = true;
        } else {
            submitButton.disabled = false;
        }
    }

    return personalCount <= 1;
}

/**
 * Add Visa Detail
 */
async function addVisaDetail() {
    // Check if we're in summary mode, if so switch to edit mode first
    const summaryView = document.getElementById('visaInfoSummary');
    const editView = document.getElementById('visaInfoEdit');
    
    if (summaryView && editView && summaryView.style.display !== 'none') {
        toggleEditMode('visaInfo');
    }
    
    const container = document.getElementById('visaDetailsContainer');
    const index = container.children.length;

    // Fetch visa types
    const visaTypes = await fetchVisaTypes();

    // Build the options for the dropdown
    let optionsHtml = '<option value="">Select Visa Type</option>';
    visaTypes.forEach(visa => {
        const nickName = visa.nick_name ? ` (${visa.nick_name})` : '';
        optionsHtml += `<option value="${visa.id}">${visa.title}${nickName}</option>`;
    });

    container.insertAdjacentHTML('beforeend', `
        <div class="repeatable-section">
            <button type="button" class="remove-item-btn" title="Remove Visa" onclick="removeVisaField(this)">${crmI('fas fa-trash')}</button>
            <div class="content-grid">
                <div class="form-group">
                    <label>Visa Type / Subclass</label>
                    <select name="visa_type_hidden[${index}]" class="visa-type-field">
                        ${optionsHtml}
                    </select>
                </div>
                <div class="form-group">
                    <label>Visa Expiry Date</label>
                    <input type="text" name="visa_expiry_date[${index}]" placeholder="dd/mm/yyyy" class="visa-expiry-field date-picker">
                </div>
                <div class="form-group">
                    <label>Visa Grant Date</label>
                    <input type="text" name="visa_grant_date[${index}]" placeholder="dd/mm/yyyy" class="visa-grant-field date-picker date-picker-past-only">
                </div>
                <div class="form-group">
                    <label>Visa Description</label>
                    <input type="text" name="visa_description[${index}]" class="visa-description-field" placeholder="Description">
                </div>
            </div>
        </div>
    `);

    // Reinitialize datepicker for the newly added field
    initializeDatepickers();
    toggleVisaDetails();
}

/**
 * Initialize Flatpickr datepickers for both empty and non-empty fields
 */
function initializeDatepickers() {
    if (typeof flatpickr === 'undefined') {
        console.warn('⚠️ Flatpickr not loaded, skipping datepicker initialization');
        return;
    }

    $('.date-picker').each(function() {
        const $this = $(this);
        const element = this;
        const currentValue = $this.val(); // Get the current value of the field
        const isPastOnly = $this.hasClass('date-picker-past-only');

        // Skip if already initialized
        if ($this.data('flatpickr')) {
            return;
        }

        // Initialize Flatpickr
        // Past-only fields (DOB, address, travel, employment, passport issue, visa grant): maxDate = today
        // Other fields (passport expiry, visa expiry): allow future
        const maxDateObj = isPastOnly ? 'today' : new Date(new Date().getFullYear() + 50, 11, 31);
        
        const fp = flatpickr(element, {
            dateFormat: 'd/m/Y',
            allowInput: true,
            clickOpens: true,
            defaultDate: currentValue || null,
            minDate: '01/01/1000',
            maxDate: maxDateObj,
            locale: {
                firstDayOfWeek: 1 // Monday
            },
            onChange: function(selectedDates, dateStr, instance) {
                // Update the input value when date is selected
                $this.val(dateStr);
                $this.trigger('change'); // Trigger change event for any listeners
            }
        });

        // Store instance for later reference
        $this.data('flatpickr', fp);
    });
}

/**
 * Toggle Spouse Details Section based on Marital Status
 */
function toggleSpouseDetailsSection() {
    const maritalStatusEl = document.getElementById('maritalStatus');
    if (!maritalStatusEl) return;
    const maritalStatus = maritalStatusEl.value;
    const spouseDetailsSection = document.getElementById('spouseDetailsSection');

    // Check if the spouseDetailsSection element exists before trying to access its style
    if (spouseDetailsSection) {
        // Handle both "Defacto" and "De Facto" for consistency
        if (maritalStatus === 'Married' || maritalStatus === 'Defacto' || maritalStatus === 'De Facto') {
            spouseDetailsSection.style.display = 'block';
        } else {
            spouseDetailsSection.style.display = 'none';
        }
    }

    // Reinitialize datepickers when showing spouse details
    if (maritalStatus === 'Married' || maritalStatus === 'Defacto' || maritalStatus === 'De Facto') {
        initializeDatepickers();
    }
}

/**
 * Add Qualification
 */
async function addQualification() {
    // Check if we're in summary mode, if so switch to edit mode first
    const summaryView = document.getElementById('qualificationsInfoSummary');
    const editView = document.getElementById('qualificationsInfoEdit');
    
    if (summaryView && editView && summaryView.style.display !== 'none') {
        toggleEditMode('qualificationsInfo');
    }
    
    const container = document.getElementById('qualificationsContainer');
    const index = container.children.length;

    // Get country options from existing select if available, otherwise fetch countries
    let countryOptionsHtml = '<option value="">Select Country</option>';
    const existingSelect = document.querySelector('.qualification-country-field');
    if (existingSelect) {
        Array.from(existingSelect.options).forEach(option => {
            if (option.value !== '') { // Skip the empty option
                countryOptionsHtml += `<option value="${option.value}">${option.text}</option>`;
            }
        });
    } else {
        // Fallback if no existing select found - fetch countries
        const countries = await fetchCountries();
        countries.forEach(country => {
            countryOptionsHtml += `<option value="${country}">${country}</option>`;
        });
    }

    container.insertAdjacentHTML('beforeend', `
        <div class="repeatable-section">
            <button type="button" class="remove-item-btn" title="Remove Qualification" onclick="removeQualificationField(this)">${crmI('fas fa-trash')}</button>
            <div class="content-grid">
                <div class="form-group">
                    <label>Qualification Level</label>
                    <select name="level[${index}]">
                        <option value="">Select Level</option>
                        <option value="Certificate I">Certificate I</option>
                        <option value="Certificate II">Certificate II</option>
                        <option value="Certificate III">Certificate III</option>
                        <option value="Certificate IV">Certificate IV</option>
                        <option value="Diploma">Diploma</option>
                        <option value="Advanced Diploma">Advanced Diploma</option>
                        <option value="Bachelor Degree">Bachelor Degree</option>
                        <option value="Bachelor Honours Degree">Bachelor Honours Degree</option>
                        <option value="Graduate Certificate">Graduate Certificate</option>
                        <option value="Graduate Diploma">Graduate Diploma</option>
                        <option value="Masters Degree">Masters Degree</option>
                        <option value="Doctoral Degree">Doctoral Degree</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Qualification Name</label>
                    <input type="text" name="name[${index}]" placeholder="e.g., Bachelor of Engineering">
                </div>
                <div class="form-group">
                    <label>Institution/College Name</label>
                    <input type="text" name="qual_college_name[${index}]" placeholder="Institution Name">
                </div>
                <div class="form-group">
                    <label>Campus/Address</label>
                    <input type="text" name="qual_campus[${index}]" placeholder="Campus/Address">
                </div>
                <div class="form-group">
                    <label>Country</label>
                    <select name="qual_country[${index}]">
                        ${countryOptionsHtml}
                    </select>
                </div>
                   <div class="form-group">
                       <label>Status</label>
                       <input type="text" name="qual_state[${index}]" placeholder="enrolled, completed, or withdrew">
                   </div>
                <div class="form-group">
                    <label>Start Date</label>
                    <input type="text" name="start_date[${index}]" placeholder="dd/mm/yyyy" class="date-picker">
                </div>
                <div class="form-group">
                    <label>Finish Date</label>
                    <input type="text" name="finish_date[${index}]" placeholder="dd/mm/yyyy" class="date-picker">
                </div>
                <div class="form-group" style="grid-column: span 2;">
                    <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                        <input type="checkbox" name="relevant_qualification[${index}]" value="1" style="width: auto; margin: 0;">
                        <span>Relevant Qualification for Migration</span>
                    </label>
                </div>
            </div>
        </div>
    `);
    
    // Initialize datepickers for the newly added fields
    initializeDatepickers();
}

/**
 * Add Experience
 */
async function addExperience() {
    // Check if we're in summary mode, if so switch to edit mode first
    const summaryView = document.getElementById('experienceInfoSummary');
    const editView = document.getElementById('experienceInfoEdit');
    
    if (summaryView && editView && summaryView.style.display !== 'none') {
        toggleEditMode('experienceInfo');
    }
    
    const container = document.getElementById('experienceContainer');
    const index = container.children.length;

    // Fetch countries for the dropdown
    const countries = await fetchCountries();
    let countryOptionsHtml = '';
    countries.forEach(country => {
        countryOptionsHtml += `<option value="${country}">${country}</option>`;
    });

    container.insertAdjacentHTML('beforeend', `
        <div class="repeatable-section experience-entry-wrapper">
            <div class="experience-entry-header">
                <button type="button" class="remove-item-btn" title="Remove Experience" onclick="removeExperienceField(this)">${crmI('fas fa-times-circle')}</button>
            </div>
            <input type="hidden" name="experience_id[${index}]" value="">
            <div class="content-grid" style="grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px;">
                <div class="form-group">
                    <label>Job Title</label>
                    <input type="text" name="job_title[${index}]" placeholder="e.g., Software Engineer">
                </div>
                <div class="form-group">
                    <label>ANZSCO Code</label>
                    <input type="text" name="job_code[${index}]" placeholder="e.g., 261313">
                </div>
                <div class="form-group">
                    <label>Employer Name</label>
                    <input type="text" name="job_emp_name[${index}]" placeholder="Enter employer name">
                </div>
                <div class="form-group">
                    <label>Country</label>
                    <select name="job_country_hidden[${index}]">
                        <option value="">Select Country</option>
                        ${countryOptionsHtml}
                    </select>
                </div>
                <div class="form-group">
                    <label>Address</label>
                    <textarea name="job_state[${index}]" 
                              rows="2" 
                              placeholder="Enter workplace address"></textarea>
                </div>
                <div class="form-group">
                    <label>Job Type</label>
                    <select name="job_type[${index}]">
                        <option value="">Select job type</option>
                        <option value="Full-time">Full-time</option>
                        <option value="Part-time">Part-time</option>
                        <option value="Contract">Contract</option>
                        <option value="Casual">Casual</option>
                        <option value="Internship">Internship</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Start Date</label>
                    <input type="text" name="job_start_date[${index}]" placeholder="dd/mm/yyyy" class="date-picker date-picker-past-only">
                </div>
                <div class="form-group">
                    <label>Finish Date</label>
                    <input type="text" name="job_finish_date[${index}]" placeholder="dd/mm/yyyy" class="date-picker date-picker-past-only">
                </div>
                <div class="form-group" style="align-items: center;">
                    <label>Relevant?</label>
                    <div class="toggle-switch">
                        <input type="checkbox" name="relevant_experience_hidden[${index}]" id="relevant_${index}" value="1">
                        <label for="relevant_${index}" class="toggle-label"></label>
                    </div>
                </div>
            </div>
        </div>
    `);

    // Reinitialize datepickers for the newly added fields
    initializeDatepickers();
    syncCurrentExperienceIndicators();
}

/**
 * Function to add a new character row
 */
function addCharacterRow(containerId, fieldName) {
    // Check if we're in summary mode, if so switch to edit mode first
    const summaryView = document.getElementById('characterInfoSummary');
    const editView = document.getElementById('characterInfoEdit');
    
    if (summaryView && editView && summaryView.style.display !== 'none') {
        toggleEditMode('characterInfo');
    }
    
    const container = document.getElementById(containerId);
    const index = container.children.length;

    container.insertAdjacentHTML('beforeend', `
        <div class="repeatable-section">
            <button type="button" class="remove-item-btn" title="Remove Character" onclick="removeCharacterField(this)">${crmI('fas fa-trash')}</button>
            <div class="content-grid">
                <div class="form-group">
                    <label>Type</label>
                    <select name="type_of_character[${index}]" required>
                        <option value="">Select Type</option>
                        <option value="1">Criminal</option>
                        <option value="2">Military/ Intelligence Work</option>
                        <option value="3">Visa/ Citizenship/ refusal/ cancellation/ deportation</option>
                        <option value="4">Health Declaration</option>
                        <option value="5">Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Character Detail</label>
                    <textarea name="${fieldName}[${index}]" rows="3" placeholder="Enter character detail"></textarea>
                </div>
            </div>
        </div>
    `);
}

/**
 * Initialize Google Maps autocomplete for address inputs
 */
function initGoogleMaps() {
    const inputs = document.querySelectorAll('.address-input');
    inputs.forEach(input => {
        const autocomplete = new google.maps.places.Autocomplete(input, {
            types: ['address'],
            fields: ['formatted_address', 'address_components']
        });

        autocomplete.addListener('place_changed', () => {
            const place = autocomplete.getPlace();
            if (!place.formatted_address) {
                return;
            }

            input.value = place.formatted_address;

            const row = input.closest('.repeatable-section');
            if (row) {
                const postcodeInput = row.querySelector('.postcode-input, input[name*="zip"]');
                
                let postcode = '';
                if (place.address_components) {
                    place.address_components.forEach(component => {
                        if (component.types.includes('postal_code')) {
                            postcode = component.long_name;
                        }
                    });
                }
                
                if (postcodeInput && postcode) {
                    postcodeInput.value = postcode;
                }
            }
        });
    });
}


// ===== NEW SUMMARY/EDIT MODE FUNCTIONALITY =====

/**
 * Toggle edit mode for sections
 */
window.toggleEditMode = function(sectionType) {
    const summaryView = document.getElementById(sectionType + 'Summary');
    const editView = document.getElementById(sectionType + 'Edit');
    
    if (summaryView && editView) {
        // Hide summary view (support both inline styles and classes)
        summaryView.style.display = 'none';
        summaryView.classList.add('hidden');
        
        // Show edit view (support both inline styles and classes)
        editView.style.display = 'block';
        editView.classList.remove('hidden');
        
        // Section-specific initialization
        if (sectionType === 'addressInfo') {
            // Re-initialize datepickers when entering edit mode for address section
            setTimeout(function() {
                initializeDatepickers();
                syncCurrentAddressIndicators();
                console.log('✅ Date pickers initialized for address edit mode');
            }, 100);
        } else if (sectionType === 'experienceInfo') {
            setTimeout(function() {
                initializeDatepickers();
                syncCurrentExperienceIndicators();
            }, 100);
        } else if (sectionType === 'emailAddresses') {
            // Start email verification polling when opening email section
            console.log('📧 Opening email section - starting verification polling');
            setTimeout(function() {
                initializeEmailSectionPolling();
            }, 100);
        } else if (sectionType === 'partnerEoiInfo') {
            // Attach partner selection event listener when opening partner EOI section
            console.log('👫 Opening partner EOI section - attaching event listener');
            setTimeout(function() {
                const partnerSelect = document.querySelector('select[name="selected_partner_id"]');
                if (partnerSelect) {
                    // Remove any existing event listeners to avoid duplicates
                    const newSelect = partnerSelect.cloneNode(true);
                    partnerSelect.parentNode.replaceChild(newSelect, partnerSelect);
                    
                    // Attach the change event listener
                    newSelect.addEventListener('change', function() {
                        console.log('Partner selected:', this.value);
                        fetchPartnerEoiData(this.value);
                    });
                    
                    // If a partner is already selected, load their data
                    if (newSelect.value) {
                        console.log('Auto-loading partner data for:', newSelect.value);
                        fetchPartnerEoiData(newSelect.value);
                    }
                    console.log('✅ Partner selection event listener attached');
                } else {
                    console.error('❌ Partner select dropdown not found');
                }
            }, 100);
        } else if (sectionType === 'relatedFilesInfo') {
            // Reinitialize mmSelect when opening related files edit mode
            console.log('🔗 Opening related files section - reinitializing mmSelect');
            setTimeout(function() {
                window.reinitializeRelatedFilesMmSelect();
            }, 100);
        }
    }
};

/**
 * Cancel edit mode and return to summary view
 */
window.cancelEdit = function(sectionType) {
    const summaryView = document.getElementById(sectionType + 'Summary');
    const editView = document.getElementById(sectionType + 'Edit');
    
    if (summaryView && editView) {
        // Hide edit view (support both inline styles and classes)
        editView.style.display = 'none';
        editView.classList.add('hidden');
        
        // Show summary view (support both inline styles and classes)
        summaryView.style.display = 'block';
        summaryView.classList.remove('hidden');
        
        // Section-specific cleanup
        if (sectionType === 'emailAddresses') {
            // Stop email verification polling when leaving email section
            console.log('📧 Closing email section - stopping verification polling');
            stopAllEmailPolling();
            
            // Do a final refresh of email statuses
            setTimeout(function() {
                initializeEmailSectionPolling();
            }, 100);
        }
    }
};

/**
 * Resolve the active client/company/lead edit form.
 * Personal edit uses #editClientForm; company edit uses #editCompanyForm.
 */
function getClientEditForm() {
    return document.getElementById('editCompanyForm')
        || document.getElementById('editClientForm')
        || document.getElementById('editLeadForm');
}

/**
 * Save basic information and update summary
 */
/**
 * Generic function to save section data via AJAX
 */
window.saveSectionData = function(sectionName, formData, successCallback) {
    const form = getClientEditForm();
    if (!form) {
        showNotification('Form not found. Please refresh the page and try again.', 'error');
        return;
    }
    const clientId = form.querySelector('input[name="id"]')?.value;
    const type = form.querySelector('input[name="type"]')?.value;
    if (!clientId || !type) {
        showNotification('Invalid form data. Please refresh the page and try again.', 'error');
        return;
    }
    
    // Get CSRF token from meta tag or form
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') 
                     || document.querySelector('input[name="_token"]')?.value 
                     || '';
    
    // Add section data to form data
    formData.append('_token', csrfToken);
    formData.append('id', clientId);
    formData.append('type', type);
    formData.append('section', sectionName);
    
    fetch('/clients/save-section', {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        }
    })
    .then(response => {
        // Handle non-200 responses
        if (!response.ok) {
            return response.json().then(data => {
                throw { status: response.status, data: data };
            }).catch(error => {
                if (error.status) throw error;
                throw { status: response.status, data: { message: 'Server error occurred' } };
            });
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            successCallback(data);  // Pass the response data to the callback
            showNotification(data.message || `${sectionName} updated successfully!`, 'success');
        } else {
            showNotification(data.message || `Error updating ${sectionName}`, 'error');
            if (data.errors) {
                displaySectionErrors(sectionName, data.errors);
            }
        }
    })
    .catch(error => {
        console.error('Error:', error);
        
        // Handle validation errors (422 status)
        if (error.status === 422 && error.data && error.data.errors) {
            displaySectionErrors(sectionName, error.data.errors);
            showNotification('Please fix the validation errors', 'error');
        } else {
            const message = error.data?.message || `Error updating ${sectionName}. Please try again.`;
            showNotification(message, 'error');
        }
    });
};

/**
 * Display errors for a specific section
 */
window.displaySectionErrors = function(sectionName, errors) {
    const editView = document.getElementById(sectionName + 'Edit');
    if (!editView) return;
    
    // Clear previous errors
    editView.querySelectorAll('.field-error').forEach(error => error.remove());
    
    // Display new errors
    Object.keys(errors).forEach(fieldName => {
        const field = editView.querySelector(`[name*="${fieldName}"]`);
        if (field) {
            const errorDiv = document.createElement('div');
            errorDiv.className = 'field-error text-danger';
            errorDiv.textContent = errors[fieldName][0];
            field.parentNode.appendChild(errorDiv);
        }
    });
};

window.saveBasicInfo = function() {
    // Validate required fields
    const firstName = document.getElementById('firstName').value.trim();
    const lastName = document.getElementById('lastName').value.trim();
    const clientId = document.getElementById('clientId').value.trim();
    
    if (!firstName || !lastName || !clientId) {
        showNotification('Please fill in all required fields (First Name, Last Name, Client ID)', 'error');
        return;
    }
    
    const formData = new FormData();
    formData.append('first_name', firstName);
    formData.append('last_name', lastName);
    formData.append('client_id', clientId);
    formData.append('dob', document.getElementById('dob').value);
    formData.append('age', document.getElementById('age').value);
    formData.append('gender', document.getElementById('gender').value);
    formData.append('marital_status', document.getElementById('maritalStatus').value);

    const leadStageEl = document.getElementById('lead_pipeline_status_edit');
    if (leadStageEl) {
        formData.append('lead_status', leadStageEl.value);
        if (leadStageEl.value === 'follow_up') {
            const fuEl = document.getElementById('lead_followup_date_edit');
            if (fuEl) {
                formData.append('followup_date', fuEl.value || '');
            }
        }
        const asEl = document.getElementById('assigned_staff_id_edit');
        if (asEl) {
            formData.append('assigned_staff_id', asEl.value);
        }
    }
    
    saveSectionData('basicInfo', formData, function() {
        // Update summary view on success
        const summaryView = document.getElementById('basicInfoSummary');
        const summaryGrid = summaryView.querySelector('.summary-grid');
        
        summaryGrid.innerHTML = `
            <div class="summary-item">
                <span class="summary-label">Name:</span>
                <span class="summary-value">${firstName} ${lastName}</span>
            </div>
            <div class="summary-item">
                <span class="summary-label">Client ID:</span>
                <span class="summary-value">${clientId}</span>
            </div>
            <div class="summary-item">
                <span class="summary-label">Date of Birth:</span>
                <span class="summary-value">${document.getElementById('dob').value || 'Not set'}</span>
            </div>
            <div class="summary-item">
                <span class="summary-label">Age:</span>
                <span class="summary-value">${document.getElementById('age').value || 'Not calculated'}</span>
            </div>
            <div class="summary-item">
                <span class="summary-label">Gender:</span>
                <span class="summary-value">${document.getElementById('gender').value || 'Not set'}</span>
            </div>
            <div class="summary-item">
                <span class="summary-label">Marital Status:</span>
                <span class="summary-value">${document.getElementById('maritalStatus').value || 'Not set'}</span>
            </div>
        `;

        if (leadStageEl) {
            const stageText = leadStageEl.options[leadStageEl.selectedIndex]?.text || leadStageEl.value;
            let extra = `
            <div class="summary-item">
                <span class="summary-label">Lead stage:</span>
                <span class="summary-value">${stageText}</span>
            </div>`;
            const fuEl = document.getElementById('lead_followup_date_edit');
            let fuDisplay = '—';
            if (leadStageEl.value === 'follow_up' && fuEl && fuEl.value) {
                const p = fuEl.value.split('-');
                if (p.length === 3) {
                    fuDisplay = `${p[2]}/${p[1]}/${p[0]}`;
                }
            }
            extra += `
            <div class="summary-item">
                <span class="summary-label">Follow-up date:</span>
                <span class="summary-value">${fuDisplay}</span>
            </div>`;
            const asEl = document.getElementById('assigned_staff_id_edit');
            const asText = asEl ? (asEl.options[asEl.selectedIndex]?.text || '') : '';
            extra += `
            <div class="summary-item">
                <span class="summary-label">Assigned to:</span>
                <span class="summary-value">${asText || '—'}</span>
            </div>`;
            summaryGrid.innerHTML += extra;
        }
        
        // Return to summary view
        cancelEdit('basicInfo');
    });
};

/**
 * Save phone numbers and update summary
 */
window.savePhoneNumbers = function() {
    // Get all phone number entries
    const container = document.getElementById('phoneNumbersContainer');
    const sections = container.querySelectorAll('.repeatable-section');
    const phoneNumbers = [];
    
    sections.forEach((section, index) => {
        const type = section.querySelector('.contact-type-selector').value;
        const countryCode = section.querySelector('.country-code-input').value;
        const phone = section.querySelector('.phone-number-input').value;
        const contactId = section.querySelector('input[name*="contact_id"]')?.value;
        
        if (type && phone) {
            phoneNumbers.push({
                id: contactId || '',
                contact_type: type,
                country_code: countryCode,
                phone: phone
            });
        }
    });
    
    const formData = new FormData();
    formData.append('phone_numbers', JSON.stringify(phoneNumbers));
    
    saveSectionData('phoneNumbers', formData, function() {
        // Update summary view on success
        const summaryView = document.getElementById('phoneNumbersSummary');
        const summaryGrid = summaryView.querySelector('.summary-grid');
        
        if (phoneNumbers.length > 0) {
            summaryGrid.innerHTML = phoneNumbers.map((phone, index) => {
                // Check if it's a placeholder number
                const isPlaceholder = isPlaceholderNumber(phone.phone);
                
                // Show verify button only for saved Australian numbers (not placeholders, not unsaved)
                let verificationButton = '';
                if (phone.country_code === '+61' && !isPlaceholder) {
                    if (phone.id && phone.id !== 'pending') {
                        verificationButton = `<button type="button" class="btn-verify-phone" onclick="sendOTP('${phone.id}', '${phone.phone}', '${phone.country_code}')" data-contact-id="${phone.id}">
                            ${crmI('fas fa-lock')} Verify
                         </button>`;
                    } else {
                        verificationButton = `<span class="text-muted" style="font-size: 12px;" title="Save the phone first to enable verification">
                            ${crmI('fas fa-info-circle')} Save to verify
                         </span>`;
                    }
                }
                
                return `
                    <div class="summary-item">
                        <span class="summary-label">${phone.contact_type}:</span>
                        <span class="summary-value">${phone.country_code}${phone.phone}</span>
                        ${verificationButton}
                    </div>
                `;
            }).join('');
        } else {
            summaryView.innerHTML = '<div class="empty-state"><p>No phone numbers added yet.</p></div>';
        }
        
        // Return to summary view
        cancelEdit('phoneNumbers');
        
        // Refresh the page to get updated verification status from server
        setTimeout(() => {
            window.location.reload();
        }, 1000);
    });
};

/**
 * Save email addresses and update summary
 */
window.saveEmailAddresses = function() {
    // Get all email entries
    const container = document.getElementById('emailAddressesContainer');
    const sections = container.querySelectorAll('.repeatable-section');
    const emails = [];
    
    sections.forEach(section => {
        const type = section.querySelector('.email-type-selector').value;
        const email = section.querySelector('input[type="email"]').value;
        const emailId = section.querySelector('input[name*="email_id"]')?.value;
        
        if (type && email) {
            emails.push({
                email_id: emailId || '',
                email_type: type,
                email: email
            });
        }
    });
    
    const formData = new FormData();
    formData.append('emails', JSON.stringify(emails));
    
    saveSectionData('emailAddresses', formData, function(response) {
        // Use the email IDs returned from the server (not the old ones)
        const savedEmails = response && response.emails ? response.emails : [];
        
        // Update summary view on success
        const summaryView = document.getElementById('emailAddressesSummary');
        const summaryGrid = summaryView.querySelector('.summary-grid');
        
        if (savedEmails.length > 0) {
            summaryGrid.innerHTML = savedEmails.map((email, index) => {
                // Show verify button only for saved emails with valid ID
                let verificationButton = '';
                if (email.is_verified) {
                    verificationButton = `<span class="verified-badge">
                        ${crmI('fas fa-check-circle')} Verified
                     </span>`;
                } else if (email.id && email.id > 0) {
                    // Use email.id from server response (guaranteed to be correct)
                    verificationButton = `<button type="button" class="btn-verify-email" onclick="sendEmailVerification(${email.id}, '${email.email}')" data-email-id="${email.id}">
                        ${crmI('fas fa-lock')} Verify
                     </button>`;
                } else {
                    verificationButton = `<span class="text-muted" style="font-size: 12px;" title="Save the email first to enable verification">
                        ${crmI('fas fa-info-circle')} Save to verify
                     </span>`;
                }
                
                return `
                    <div class="summary-item">
                        <span class="summary-label">${email.email_type}:</span>
                        <span class="summary-value">${email.email}</span>
                        ${verificationButton}
                    </div>
                `;
            }).join('');
        } else {
            summaryView.innerHTML = '<div class="empty-state"><p>No email addresses added yet.</p></div>';
        }
        
        // Return to summary view
        cancelEdit('emailAddresses');
        
        // Start polling for newly saved unverified emails
        setTimeout(() => {
            const newEmailVerifyButtons = document.querySelectorAll('.btn-verify-email');
            newEmailVerifyButtons.forEach(button => {
                const emailId = button.getAttribute('data-email-id');
                
                // Validate email ID before starting polling
                if (isValidEmailId(emailId)) {
                    console.log(`Starting polling for newly saved email ID: ${emailId}`);
                    startEmailVerificationPolling(parseInt(emailId));
                }
            });
        }, 1000);
    });
};

/**
 * Edit individual phone number
 */
window.editPhoneNumber = function(index) {
    // Switch to edit mode
    toggleEditMode('phoneNumbers');
    
    // Focus on the specific phone number field
    const container = document.getElementById('phoneNumbersContainer');
    const sections = container.querySelectorAll('.repeatable-section');
    if (sections[index]) {
        const phoneInput = sections[index].querySelector('.phone-number-input');
        if (phoneInput) {
            phoneInput.focus();
        }
    }
};

/**
 * Edit individual email address
 */
window.editEmailAddress = function(index) {
    // Switch to edit mode
    toggleEditMode('emailAddresses');
    
    // Focus on the specific email field
    const container = document.getElementById('emailAddressesContainer');
    const sections = container.querySelectorAll('.repeatable-section');
    if (sections[index]) {
        const emailInput = sections[index].querySelector('input[type="email"]');
        if (emailInput) {
            emailInput.focus();
        }
    }
};

/**
 * Remove phone number
 */
window.removePhoneNumber = function(id, index) {
    if (confirm('Are you sure you want to remove this phone number?')) {
        if (id) {
            // Mark for deletion in database
            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = 'delete_contact_ids[]';
            hiddenInput.value = id;
            const form = getClientEditForm();
            if (form) {
                form.appendChild(hiddenInput);
            }
        }
        
        // Remove from DOM
        const container = document.getElementById('phoneNumbersContainer');
        const sections = container.querySelectorAll('.repeatable-section');
        if (sections[index]) {
            sections[index].remove();
        }
        
        // Update summary
        savePhoneNumbers();
    }
};

/**
 * Remove email address
 */
window.removeEmailAddress = function(id, index) {
    if (confirm('Are you sure you want to remove this email address?')) {
        if (id) {
            // Mark for deletion in database
            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = 'delete_email_ids[]';
            hiddenInput.value = id;
            const form = getClientEditForm();
            if (form) {
                form.appendChild(hiddenInput);
            }
        }
        
        // Remove from DOM
        const container = document.getElementById('emailAddressesContainer');
        const sections = container.querySelectorAll('.repeatable-section');
        if (sections[index]) {
            sections[index].remove();
        }
        
        // Update summary
        saveEmailAddresses();
    }
};

/**
 * Save passport information and update summary
 */
window.savePassportInfo = function() {
    // Get all passport entries
    const container = document.getElementById('passportDetailsContainer');
    const sections = container.querySelectorAll('.repeatable-section');
    const passports = [];
    
    sections.forEach(section => {
        const passportId = section.querySelector('input[name*="passport_id"]')?.value;
        const passportCountry = section.querySelector('select[name*="passport_country"]')?.value;
        const passportNumber = section.querySelector('input[name*="passport_number"]').value;
        const issueDate = section.querySelector('input[name*="issue_date"]').value;
        const expiryDate = section.querySelector('input[name*="expiry_date"]').value;
        
        if (passportNumber || issueDate || expiryDate || passportCountry) {
            passports.push({
                passport_id: passportId || '',
                passport_country: passportCountry || '',
                passport_number: passportNumber,
                issue_date: issueDate,
                expiry_date: expiryDate
            });
        }
    });
    
    const formData = new FormData();
    formData.append('passports', JSON.stringify(passports));
    
    saveSectionData('passportInfo', formData, function() {
        // Update summary view on success
        const summaryView = document.getElementById('passportInfoSummary');
        
        let summaryHTML = '';
        
        if (passports.length > 0) {
            summaryHTML += '<div style="margin-top: 15px;">';
            passports.forEach(passport => {
                summaryHTML += `
                    <div class="passport-entry-compact" style="margin-bottom: 12px; padding: 12px; background: #f8f9fa; border-radius: 6px; border-left: 3px solid #007bff;">
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; align-items: center;">
                            <div class="summary-item-inline">
                                <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">COUNTRY:</span>
                                <span class="summary-value" style="color: #212529; font-weight: 500;">${passport.passport_country || 'Not set'}</span>
                            </div>
                            <div class="summary-item-inline">
                                <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">PASSPORT #:</span>
                                <span class="summary-value" style="color: #212529; font-weight: 500;">${passport.passport_number || 'Not set'}</span>
                            </div>
                            <div class="summary-item-inline">
                                <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">ISSUE DATE:</span>
                                <span class="summary-value" style="color: #212529;">${passport.issue_date || 'Not set'}</span>
                            </div>
                            <div class="summary-item-inline">
                                <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">EXPIRY DATE:</span>
                                <span class="summary-value" style="color: #212529;">${passport.expiry_date || 'Not set'}</span>
                            </div>
                        </div>
                    </div>
                `;
            });
            summaryHTML += '</div>';
        } else {
            summaryHTML += '<div class="empty-state" style="margin-top: 15px;"><p>No passport details added yet.</p></div>';
        }
        
        summaryView.innerHTML = summaryHTML;
        
        // Return to summary view
        cancelEdit('passportInfo');
    });
};

/**
 * Save visa information and update summary
 */
window.saveVisaInfo = function() {
    // Get visa expiry verified status
    const visaExpiryVerified = document.querySelector('input[name="visa_expiry_verified"]').checked ? '1' : '0';
    
    // Get all visa entries
    const container = document.getElementById('visaDetailsContainer');
    const sections = container.querySelectorAll('.repeatable-section');
    const visas = [];
    
    sections.forEach(section => {
        const visaId = section.querySelector('input[name*="visa_id"]')?.value;
        const visaTypeSelect = section.querySelector('.visa-type-field');
        const visaType = visaTypeSelect.value;
        const expiryDate = section.querySelector('.visa-expiry-field').value;
        const grantDate = section.querySelector('.visa-grant-field').value;
        const description = section.querySelector('.visa-description-field').value;
        
        if (visaType || expiryDate || grantDate || description) {
            // Get visa type name from the selected option at collection time
            let visaTypeName = 'Not set';
            if (visaType) {
                const selectedOption = visaTypeSelect.querySelector(`option[value="${visaType}"]`);
                visaTypeName = selectedOption ? selectedOption.textContent.trim() : visaType;
            }
            
            visas.push({
                visa_id: visaId || '',
                visa_type_hidden: visaType,
                visa_type_name: visaTypeName,
                visa_expiry_date: expiryDate,
                visa_grant_date: grantDate,
                visa_description: description
            });
        }
    });
    
    const formData = new FormData();
    formData.append('visa_expiry_verified', visaExpiryVerified);
    formData.append('visas', JSON.stringify(visas));
    
    saveSectionData('visaInfo', formData, function() {
        // Update summary view on success
        const summaryView = document.getElementById('visaInfoSummary');
        
        let summaryHTML = `
            <div class="summary-grid">
                <div class="summary-item">
                    <span class="summary-label">Visa Expiry Verified:</span>
                    <span class="summary-value">${visaExpiryVerified === '1' ? 'Yes' : 'No'}</span>
                </div>
            </div>
        `;
        
        if (visas.length > 0) {
            // Sort visas by expiry date (longest expiry date first)
            visas.sort((a, b) => {
                // Handle cases where expiry date might be null or empty
                if (!a.visa_expiry_date && !b.visa_expiry_date) return 0;
                if (!a.visa_expiry_date) return 1; // Put null dates at the end
                if (!b.visa_expiry_date) return -1; // Put null dates at the end
                
                // Convert dd/mm/yyyy to Date object for comparison
                const dateA = new Date(a.visa_expiry_date.split('/').reverse().join('-'));
                const dateB = new Date(b.visa_expiry_date.split('/').reverse().join('-'));
                
                // Sort in descending order (longest expiry date first)
                return dateB - dateA;
            });
            
            summaryHTML += '<div style="margin-top: 15px;">';
            visas.forEach(visa => {
                // Use the visa type name we already captured during data collection
                const visaTypeName = visa.visa_type_name || 'Not set';
                
                summaryHTML += `
                    <div class="visa-entry-compact" style="margin-bottom: 12px; padding: 12px; background: #f8f9fa; border-radius: 6px; border-left: 3px solid #28a745;">
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; align-items: center;">
                            <div class="summary-item-inline">
                                <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">VISA TYPE:</span>
                                <span class="summary-value" style="color: #212529; font-weight: 500;">${visaTypeName}</span>
                            </div>
                            <div class="summary-item-inline">
                                <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">EXPIRY DATE:</span>
                                <span class="summary-value" style="color: #212529;">${visa.visa_expiry_date || 'Not set'}</span>
                            </div>
                            <div class="summary-item-inline">
                                <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">GRANT DATE:</span>
                                <span class="summary-value" style="color: #212529;">${visa.visa_grant_date || 'Not set'}</span>
                            </div>
                            <div class="summary-item-inline">
                                <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">DESCRIPTION:</span>
                                <span class="summary-value" style="color: #212529;">${visa.visa_description || 'Not set'}</span>
                            </div>
                        </div>
                    </div>
                `;
            });
            summaryHTML += '</div>';
        } else {
            summaryHTML += '<div class="empty-state" style="margin-top: 15px;"><p>No visa details added yet.</p></div>';
        }
        
        summaryView.innerHTML = summaryHTML;
        
        // Return to summary view
        cancelEdit('visaInfo');
    });
};

/**
 * Save address information and update summary
 */
window.saveAddressInfo = function() {
    console.log('🚀 ====== saveAddressInfo START ======');
    console.log('🚀 Function called at:', new Date().toISOString());
    
    const $addressesContainer = $('#addresses-container');
    if (!$addressesContainer.length) {
        console.error('❌ #addresses-container not found!');
        alert('Error: Address container not found. Please refresh the page and try again.');
        return;
    }
    
    const $allWrappers = $addressesContainer.find('.address-entry-wrapper');
    console.log('🔍 Total address wrappers found:', $allWrappers.length);
    
    if ($allWrappers.length === 0) {
        console.error('❌ No address wrappers found!');
        alert('Error: No address entries found. Please refresh the page and try again.');
        return;
    }
    
    // Log each wrapper's details
    $allWrappers.each(function(i) {
        const $wrapper = $(this);
        console.log(`  Wrapper ${i}:`, {
            index: $wrapper.data('address-index'),
            hasTemplateClass: $wrapper.hasClass('address-template'),
            addressLine1: $wrapper.find('input[name="address_line_1[]"]').val(),
            isVisible: $wrapper.is(':visible')
        });
    });
    
    // Get all address entries, but exclude only the template ones
    // Note: We need to include the default empty entry (index 0) even if it has address-template class
    const $addressEntries = $addressesContainer.find('.address-entry-wrapper').filter(function() {
        const $entry = $(this);
        const index = $entry.data('address-index');
        // Include the first entry (index 0) even if it has address-template class
        // Only exclude entries that are actual templates (not the default empty entry)
        return index === 0 || !$entry.hasClass('address-template');
    });
    
    console.log('💾 Address entries to save:', $addressEntries.length);
    
    if ($addressEntries.length === 0) {
        console.error('❌ No valid address entries to save!');
        alert('Error: No valid address entries found. Please add at least one address.');
        return;
    }
    
    // Validation: Only require country and suburb
    let validationErrors = [];
    let hasAtLeastOneValidAddress = false;
    
    $addressEntries.each(function(idx) {
        const $entry = $(this);
        const addressLine1 = $.trim($entry.find('input[name="address_line_1[]"]').val() || '');
        const suburb = $.trim($entry.find('input[name="suburb[]"]').val() || '');
        const state = $.trim($entry.find('input[name="state[]"]').val() || '');
        const zip = $.trim($entry.find('input[name="zip[]"]').val() || '');
        const country = $.trim($entry.find('input[name="country[]"]').val() || '');
        
        console.log(`📝 Validating Address ${idx + 1}:`, {
            addressLine1: addressLine1 || '(empty)',
            suburb: suburb || '(empty)',
            state: state || '(empty)',
            zip: zip || '(empty)',
            country: country || '(empty)'
        });
        
        // Check if any field has data
        const hasAnyData = addressLine1 || suburb || state || zip || country;
        
        if (hasAnyData) {
            // Require suburb, country, and postcode for Australian addresses
            const missingFields = [];
            if (!suburb) missingFields.push('Suburb');
            if (!country) missingFields.push('Country');
            
            // Require postcode for Australian addresses (format allows digits and letters for overseas compatibility)
            if (country && country.toLowerCase().includes('australia') && !zip) {
                missingFields.push('Postcode');
            }
            // Postcode format is not restricted: Australian addresses use 4 digits; overseas may use letters (e.g. UK, Canada)

            if (missingFields.length > 0) {
                validationErrors.push(`Address ${idx + 1} is incomplete. Missing: ${missingFields.join(', ')}`);
                console.warn(`⚠️ Address ${idx + 1} incomplete:`, missingFields);
            } else {
                hasAtLeastOneValidAddress = true;
                console.log(`✅ Address ${idx + 1} is valid (has suburb, country${country && country.toLowerCase().includes('australia') ? ', and postcode' : ''})`);
            }
        } else {
            console.log(`ℹ️ Address ${idx + 1} is empty (will be skipped)`);
        }
    });
    
    // Show validation errors
    if (validationErrors.length > 0) {
        console.error('❌ Validation failed:', validationErrors);
        alert('Please fix the following errors:\n\n' + validationErrors.join('\n'));
        return;
    }
    
    // Check if we have at least one valid address
    if (!hasAtLeastOneValidAddress) {
        console.error('❌ No valid addresses found');
        alert('Please add at least one address with suburb and country before saving.');
        return;
    }
    
    console.log('✅ Validation passed - preparing data...');
    
    const formData = new FormData();
    let addressCount = 0;
    
    $addressEntries.each(function(index) {
        const $entry = $(this);
        
        const addressId = $entry.find('input[name="address_id[]"]').val();
        const addressLine1 = $entry.find('input[name="address_line_1[]"]').val();
        const addressLine2 = $entry.find('input[name="address_line_2[]"]').val();
        const suburb = $entry.find('input[name="suburb[]"]').val();
        const state = $entry.find('input[name="state[]"]').val();
        const country = $entry.find('input[name="country[]"]').val();
        const zip = $entry.find('input[name="zip[]"]').val();
        const regionalCode = $entry.find('input[name="regional_code[]"]').val();
        const startDate = $entry.find('input[name="address_start_date[]"]').val();
        const endDate = $entry.find('input[name="address_end_date[]"]').val();
        
        console.log(`🔍 Processing address entry ${index + 1}:`, {
            addressId: addressId || '(new)',
            addressLine1: addressLine1 || '(empty)',
            suburb: suburb || '(empty)',
            state: state || '(empty)',
            zip: zip || '(empty)',
            country: country || '(empty)',
            hasData: !!(addressLine1 || suburb || state || zip)
        });
        
        // Only include addresses that have data
        if (addressLine1 || suburb || state || zip) {
            console.log(`📦 Packaging Address ${addressCount + 1} for server:`, {
                addressId: addressId || '(new)',
                addressLine1,
                suburb,
                state,
                zip,
                country
            });
            
            // Always append all fields, even if empty (except for addressId which can be empty for new entries)
            formData.append('address_id[]', addressId || '');
            formData.append('address_line_1[]', addressLine1 || '');
            formData.append('address_line_2[]', addressLine2 || '');
            formData.append('suburb[]', suburb || '');
            formData.append('state[]', state || '');
            formData.append('country[]', country || 'Australia');
            formData.append('zip[]', zip || '');
            formData.append('regional_code[]', regionalCode || '');
            formData.append('address_start_date[]', startDate || '');
            formData.append('address_end_date[]', endDate || '');
            
            addressCount++;
        } else {
            console.log(`⏭️ Skipping address entry ${index + 1} - no data`);
        }
    });
    
    console.log(`📤 Sending ${addressCount} addresses to server...`);
    
    // Check if saveSectionData exists
    if (typeof saveSectionData !== 'function') {
        console.error('❌ saveSectionData function not found!');
        alert('Error: Save function not available. Please refresh the page and try again.');
        return;
    }
    
    console.log('📡 Calling saveSectionData...');
    
    saveSectionData('addressInfo', formData, function() {
        console.log('✅ Server responded successfully');
        console.log('🔄 Reloading page...');
        window.location.reload();
    });
    
    console.log('🚀 ====== saveAddressInfo END ======');
};

function updateAddressSummary($entries) {
    const summaryView = document.getElementById('addressInfoSummary');
    let summaryHTML = '<div>';
    
    $entries.each(function() {
        const addressLine1 = $(this).find('input[name="address_line_1[]"]').val();
        const addressLine2 = $(this).find('input[name="address_line_2[]"]').val();
        const suburb = $(this).find('input[name="suburb[]"]').val();
        const state = $(this).find('input[name="state[]"]').val();
        const zip = $(this).find('input[name="zip[]"]').val();
        const country = $(this).find('input[name="country[]"]').val();
        const startDate = $(this).find('input[name="address_start_date[]"]').val();
        const endDate = $(this).find('input[name="address_end_date[]"]').val();
        
        const fullAddress = [addressLine1, addressLine2, suburb, state, zip, country]
            .filter(Boolean).join(', ');
        
        summaryHTML += `
            <div class="address-entry">
                <div class="summary-item">
                    <span class="summary-label">Full Address:</span>
                    <span class="summary-value">${fullAddress || 'Not set'}</span>
                </div>
                ${startDate ? `<div class="summary-item">
                    <span class="summary-label">Start Date:</span>
                    <span class="summary-value">${startDate}</span>
                </div>` : ''}
                ${endDate ? `<div class="summary-item">
                    <span class="summary-label">End Date:</span>
                    <span class="summary-value">${endDate}</span>
                </div>` : ''}
            </div>
        `;
    });
    
    summaryHTML += '</div>';
    summaryView.innerHTML = summaryHTML;
}

/**
 * Save travel information and update summary
 */
window.saveTravelInfo = function() {
    // Get all travel entries
    const container = document.getElementById('travelDetailsContainer');
    const sections = container.querySelectorAll('.repeatable-section');
    const travels = [];
    
    sections.forEach(section => {
        const travelId = section.querySelector('input[name*="travel_id"]')?.value;
        
        // Handle both input and select elements for country (existing vs new fields)
        const countryInput = section.querySelector('input[name*="travel_country_visited"]');
        const countrySelect = section.querySelector('select[name*="travel_country_visited"]');
        const countryVisited = countryInput ? countryInput.value : (countrySelect ? countrySelect.value : '');
        
        const arrivalDate = section.querySelector('input[name*="travel_arrival_date"]').value;
        const departureDate = section.querySelector('input[name*="travel_departure_date"]').value;
        const travelPurpose = section.querySelector('input[name*="travel_purpose"]').value;
        
        if (countryVisited || arrivalDate || departureDate || travelPurpose) {
            travels.push({
                travel_id: travelId || '',
                country_visited: countryVisited,
                arrival_date: arrivalDate,
                departure_date: departureDate,
                purpose: travelPurpose
            });
        }
    });
    
    const formData = new FormData();
    formData.append('travels', JSON.stringify(travels));
    
    saveSectionData('travelInfo', formData, function() {
        // Update summary view on success
        const summaryView = document.getElementById('travelInfoSummary');
        
        if (travels.length > 0) {
            let summaryHTML = '<div>';
            travels.forEach(travel => {
                summaryHTML += `
                    <div class="address-entry-compact">
                        <div class="address-compact-grid">
                            <div class="summary-item-inline">
                                <span class="summary-label">COUNTRY VISITED:</span>
                                <span class="summary-value">${travel.country_visited || 'Not set'}</span>
                            </div>`;
                
                if (travel.arrival_date) {
                    summaryHTML += `
                            <div class="summary-item-inline">
                                <span class="summary-label">ARRIVAL DATE:</span>
                                <span class="summary-value">${travel.arrival_date}</span>
                            </div>`;
                }
                
                if (travel.departure_date) {
                    summaryHTML += `
                            <div class="summary-item-inline">
                                <span class="summary-label">DEPARTURE DATE:</span>
                                <span class="summary-value">${travel.departure_date}</span>
                            </div>`;
                }
                
                if (travel.purpose) {
                    summaryHTML += `
                            <div class="summary-item-inline">
                                <span class="summary-label">TRAVEL PURPOSE:</span>
                                <span class="summary-value">${travel.purpose}</span>
                            </div>`;
                }
                
                summaryHTML += `
                        </div>
                    </div>`;
            });
            summaryHTML += '</div>';
            summaryView.innerHTML = summaryHTML;
        } else {
            summaryView.innerHTML = '<div class="empty-state"><p>No travel details added yet.</p></div>';
        }
        
        // Return to summary view
        cancelEdit('travelInfo');
    });
};

/**
 * Save qualifications information and update summary
 */
window.saveQualificationsInfo = function() {
    const container = document.getElementById('qualificationsContainer');
    const sections = container.querySelectorAll('.repeatable-section');
    
    const formData = new FormData();
    
    // Add delete IDs if any
    const deleteInputs = container.querySelectorAll('input[name="delete_qualification_ids[]"]');
    deleteInputs.forEach(input => {
        formData.append('delete_qualification_ids[]', input.value);
    });
    
    sections.forEach((section, index) => {
        const qualId = section.querySelector('input[name*="qualification_id"]')?.value;
        const level = section.querySelector('select[name*="level"]')?.value;
        const name = section.querySelector('input[name*="name"]')?.value;
        const qualCollegeName = section.querySelector('input[name*="qual_college_name"]')?.value;
        const qualCampus = section.querySelector('input[name*="qual_campus"]')?.value;
        const country = section.querySelector('select[name*="qual_country"]')?.value;
        const qualState = section.querySelector('input[name*="qual_state"]')?.value;
        const startDate = section.querySelector('input[name*="start_date"]')?.value;
        const finishDate = section.querySelector('input[name*="finish_date"]')?.value;
        const relevantQual = section.querySelector('input[name*="relevant_qualification"]')?.checked;
        
        // Append data in array format that controller expects
        if (qualId) formData.append(`qualification_id[${index}]`, qualId);
        formData.append(`level[${index}]`, level || '');
        formData.append(`name[${index}]`, name || '');
        formData.append(`qual_college_name[${index}]`, qualCollegeName || '');
        formData.append(`qual_campus[${index}]`, qualCampus || '');
        formData.append(`qual_country[${index}]`, country || '');
        formData.append(`qual_state[${index}]`, qualState || '');
        formData.append(`start_date[${index}]`, startDate || '');
        formData.append(`finish_date[${index}]`, finishDate || '');
        if (relevantQual) {
            formData.append(`relevant_qualification[${index}]`, '1');
        }
    });
    
    saveSectionData('qualificationsInfo', formData, function() {
        // Reload page to show updated data
        location.reload();
    });
};

/**
 * Save experience information and update summary
 */
window.saveExperienceInfo = function() {
    // Get all experience entries
    const container = document.getElementById('experienceContainer');
    const sections = container.querySelectorAll('.repeatable-section');
    const experiences = [];
    
    sections.forEach(section => {
        const expId = section.querySelector('input[name*="experience_id"]')?.value;
        const jobTitle = section.querySelector('input[name*="job_title"]')?.value || '';
        const jobCode = section.querySelector('input[name*="job_code"]')?.value || '';
        const employerName = section.querySelector('input[name*="job_emp_name"]')?.value || '';
        const country = section.querySelector('select[name*="job_country_hidden"]')?.value || '';
        const address = section.querySelector('textarea[name*="job_state"]')?.value || '';
        const jobType = section.querySelector('select[name*="job_type"]')?.value || '';
        const startDate = section.querySelector('input[name*="job_start_date"]')?.value || '';
        const endDate = section.querySelector('input[name*="job_finish_date"]')?.value || '';
        const relevant = section.querySelector('input[name*="relevant_experience_hidden"]')?.checked || false;
        
        if (jobTitle || jobCode || employerName || country || address || jobType || startDate || endDate) {
            experiences.push({
                experience_id: expId || '',
                job_title: jobTitle,
                job_code: jobCode,
                job_emp_name: employerName,
                job_country: country,
                job_state: address,
                job_type: jobType,
                job_start_date: startDate,
                job_finish_date: endDate,
                relevant_experience: relevant ? 1 : 0
            });
        }
    });
    
    const formData = new FormData();
    formData.append('experiences', JSON.stringify(experiences));
    
    saveSectionData('experienceInfo', formData, function() {
        // Update summary view on success
        const summaryView = document.getElementById('experienceInfoSummary');
        
        if (experiences.length > 0) {
            const sortedExperiences = sortExperiencesForDisplay(experiences);
            let summaryHTML = '<div style="margin-top: 15px;">';
            sortedExperiences.forEach(function(exp, index) {
                summaryHTML += `
                    <div class="experience-entry-compact" style="margin-bottom: 12px; padding: 12px; background: #f8f9fa; border-radius: 6px; border-left: 3px solid #007bff;">
                        <div class="experience-compact-grid">
                            <div class="summary-item-inline">
                                <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">JOB TITLE:</span>
                                <span class="summary-value" style="color: #212529; font-weight: 500;">${exp.job_title || 'Not set'}</span>
                            </div>
                            <div class="summary-item-inline">
                                <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">ANZSCO CODE:</span>
                                <span class="summary-value" style="color: #212529; font-weight: 500;">${exp.job_code || 'Not set'}</span>
                            </div>
                            <div class="summary-item-inline">
                                <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">EMPLOYER NAME:</span>
                                <span class="summary-value" style="color: #212529; font-weight: 500;">${exp.job_emp_name || 'Not set'}</span>
                            </div>
                            <div class="summary-item-inline">
                                <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">COUNTRY:</span>
                                <span class="summary-value" style="color: #212529;">${exp.job_country || 'Not set'}</span>
                            </div>
                            <div class="summary-item-inline">
                                <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">ADDRESS:</span>
                                <span class="summary-value" style="color: #212529;">${exp.job_state || 'Not set'}</span>
                            </div>
                            <div class="summary-item-inline">
                                <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">JOB TYPE:</span>
                                <span class="summary-value" style="color: #212529;">${exp.job_type || 'Not set'}</span>
                            </div>
                            <div class="summary-item-inline">
                                <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">START DATE:</span>
                                <span class="summary-value" style="color: #212529;">${exp.job_start_date || 'Not set'}</span>
                            </div>
                            <div class="summary-item-inline">
                                <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">FINISH DATE:</span>
                                <span class="summary-value" style="color: #212529;">${exp.job_finish_date || 'Not set'}</span>
                            </div>
                            <div class="summary-item-inline">
                                <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">RELEVANT:</span>
                                <span class="summary-value" style="color: #212529;">${exp.relevant_experience ? 'Yes' : 'No'}</span>
                            </div>
                            ${index === 0 ? buildExperienceCurrentSummaryHtml() : ''}
                        </div>
                    </div>
                `;
            });
            summaryHTML += '</div>';
            summaryView.innerHTML = summaryHTML;
        } else {
            summaryView.innerHTML = '<div class="empty-state"><p>No work experience added yet.</p></div>';
        }
        
        // Return to summary view
        cancelEdit('experienceInfo');
    });
};

/**
 * Toggle date field visibility based on requirement selection
 */
function toggleDateFieldVisibility(requirementSelectId, dateFieldId) {
    const requirementSelect = document.getElementById(requirementSelectId);
    const dateField = document.getElementById(dateFieldId);
    const dateFormGroup = dateField.closest('.form-group');
    
    if (requirementSelect && dateFormGroup) {
        if (requirementSelect.value === '1') {
            dateFormGroup.style.display = 'block';
            dateField.required = true;
        } else {
            dateFormGroup.style.display = 'none';
            dateField.required = false;
            dateField.value = ''; // Clear the date when requirement is No
        }
    }
}

/**
 * Initialize Additional Information section
 */
function initializeAdditionalInfo() {
    // Set up event listeners for all requirement dropdowns
    const requirementMappings = [
        { requirement: 'naatiTest', date: 'naatiDate' },
        { requirement: 'pyTest', date: 'pyDate' },
        { requirement: 'australianStudy', date: 'australianStudyDate' },
        { requirement: 'specialistEducation', date: 'specialistEducationDate' },
        { requirement: 'regionalStudy', date: 'regionalStudyDate' }
    ];
    
    requirementMappings.forEach(mapping => {
        const requirementSelect = document.getElementById(mapping.requirement);
        if (requirementSelect) {
            // Initialize visibility on page load
            toggleDateFieldVisibility(mapping.requirement, mapping.date);
            
            // Add change event listener
            requirementSelect.addEventListener('change', function() {
                toggleDateFieldVisibility(mapping.requirement, mapping.date);
            });
        }
    });
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    initializeAdditionalInfo();
});

/**
 * Save additional information and update summary
 */
window.saveAdditionalInfo = function() {
    // Get form values
    const naatiTest = document.getElementById('naatiTest').value;
    const naatiDate = document.getElementById('naatiDate').value;
    const pyTest = document.getElementById('pyTest').value;
    const pyDate = document.getElementById('pyDate').value;
    
    // New EOI qualification fields
    const australianStudy = document.getElementById('australianStudy').value;
    const australianStudyDate = document.getElementById('australianStudyDate').value;
    const specialistEducation = document.getElementById('specialistEducation').value;
    const specialistEducationDate = document.getElementById('specialistEducationDate').value;
    const regionalStudy = document.getElementById('regionalStudy').value;
    const regionalStudyDate = document.getElementById('regionalStudyDate').value;
    
    // Validate required date fields
    if (naatiTest == '1' && !naatiDate.trim()) {
        alert('Please enter NAATI/CCL Date when NAATI/CCL Test is Yes');
        return;
    }
    if (pyTest == '1' && !pyDate.trim()) {
        alert('Please enter PY Completion Date when Professional Year is Yes');
        return;
    }
    if (australianStudy == '1' && !australianStudyDate.trim()) {
        alert('Please enter Australian Study Completion Date when Australian Study Requirement is Yes');
        return;
    }
    if (specialistEducation == '1' && !specialistEducationDate.trim()) {
        alert('Please enter Specialist Education Completion Date when Specialist Education is Yes');
        return;
    }
    if (regionalStudy == '1' && !regionalStudyDate.trim()) {
        alert('Please enter Regional Study Completion Date when Regional Study is Yes');
        return;
    }
    
    const formData = new FormData();
    formData.append('naati_test', naatiTest);
    formData.append('naati_date', naatiDate);
    formData.append('py_test', pyTest);
    formData.append('py_date', pyDate);
    formData.append('australian_study', australianStudy);
    formData.append('australian_study_date', australianStudyDate);
    formData.append('specialist_education', specialistEducation);
    formData.append('specialist_education_date', specialistEducationDate);
    formData.append('regional_study', regionalStudy);
    formData.append('regional_study_date', regionalStudyDate);
    
    saveSectionData('additionalInfo', formData, function() {
        // Update summary view on success
        const summaryView = document.getElementById('additionalInfoSummary');
        const summaryGrid = summaryView.querySelector('.summary-grid');
        
        summaryGrid.innerHTML = `
            <div class="summary-item">
                <span class="summary-label">NAATI/CCL Test:</span>
                <span class="summary-value">${naatiTest == '1' ? 'Yes' : 'No'}</span>
            </div>
            <div class="summary-item">
                <span class="summary-label">NAATI/CCL Date:</span>
                <span class="summary-value">${naatiDate || 'Not set'}</span>
            </div>
            <div class="summary-item">
                <span class="summary-label">Professional Year (PY):</span>
                <span class="summary-value">${pyTest == '1' ? 'Yes' : 'No'}</span>
            </div>
            <div class="summary-item">
                <span class="summary-label">PY Completion Date:</span>
                <span class="summary-value">${pyDate || 'Not set'}</span>
            </div>
            <div class="summary-item">
                <span class="summary-label">Australian Study Requirement:</span>
                <span class="summary-value">${australianStudy == '1' ? 'Yes' : 'No'}</span>
            </div>
            <div class="summary-item">
                <span class="summary-label">Australian Study Completion Date:</span>
                <span class="summary-value">${australianStudyDate || 'Not set'}</span>
            </div>
            <div class="summary-item">
                <span class="summary-label">Specialist Education (STEM):</span>
                <span class="summary-value">${specialistEducation == '1' ? 'Yes' : 'No'}</span>
            </div>
            <div class="summary-item">
                <span class="summary-label">Specialist Education Completion Date:</span>
                <span class="summary-value">${specialistEducationDate || 'Not set'}</span>
            </div>
            <div class="summary-item">
                <span class="summary-label">Regional Study:</span>
                <span class="summary-value">${regionalStudy == '1' ? 'Yes' : 'No'}</span>
            </div>
            <div class="summary-item">
                <span class="summary-label">Regional Study Completion Date:</span>
                <span class="summary-value">${regionalStudyDate || 'Not set'}</span>
            </div>
        `;
        
        // Return to summary view
        cancelEdit('additionalInfo');
    });
};

/**
 * Save character information and update summary
 */
window.saveCharacterInfo = function() {
    console.log('🚀 ====== saveCharacterInfo START ======');
    
    // Get all character entries
    const container = document.getElementById('characterContainer');
    const sections = container.querySelectorAll('.repeatable-section');
    const characters = [];
    
    console.log('📊 Found sections:', sections.length);
    
    sections.forEach((section, index) => {
        const charId = section.querySelector('input[name*="character_id"]')?.value;
        const detail = section.querySelector('textarea[name*="character_detail"]').value;
        const type = section.querySelector('select[name*="type_of_character"]').value;
        
        console.log(`📝 Section ${index}:`, { charId, detail, type });
        
        if (detail && type) {
            characters.push({
                character_id: charId || '',
                detail: detail,
                type_of_character: type
            });
        }
    });
    
    console.log('💾 Characters to save:', characters);
    
    const formData = new FormData();
    formData.append('characters', JSON.stringify(characters));
    
    saveSectionData('characterInfo', formData, function() {
        console.log('✅ Character info saved successfully');
        
        // Update summary view on success
        const summaryView = document.getElementById('characterInfoSummary');
        
        if (characters.length > 0) {
            let summaryHTML = '<div style="margin-top: 15px;">';
            characters.forEach(character => {
                const typeLabels = {
                    '1': 'Criminal',
                    '2': 'Military/ Intelligence Work',
                    '3': 'Visa/ Citizenship/ refusal/ cancellation/ deportation',
                    '4': 'Health Declaration',
                    '5': 'Other'
                };
                const typeLabel = typeLabels[character.type_of_character] || 'Unknown Type';
                
                summaryHTML += `
                    <div class="passport-entry-compact" style="margin-bottom: 12px; padding: 12px; background: #f8f9fa; border-radius: 6px; border-left: 3px solid #dc3545;">
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; align-items: start;">
                            <div class="summary-item-inline">
                                <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">TYPE:</span>
                                <span class="summary-value" style="color: #212529; font-weight: 500;">${typeLabel}</span>
                            </div>
                            <div class="summary-item-inline">
                                <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">CHARACTER DETAIL:</span>
                                <span class="summary-value" style="color: #212529; font-weight: 500;">${character.detail || 'Not set'}</span>
                            </div>
                        </div>
                    </div>
                `;
            });
            summaryHTML += '</div>';
            summaryView.innerHTML = summaryHTML;
        } else {
            summaryView.innerHTML = '<div class="empty-state" style="margin-top: 15px;"><p>No character/health declaration added yet.</p></div>';
        }
        
        // Return to summary view
        cancelEdit('characterInfo');
        console.log('🏁 ====== saveCharacterInfo END ======');
    });
};

/**
 * Save related files information and update summary
 */
window.saveRelatedFilesInfo = function() {
    const relatedFilesSelect = document.getElementById('relatedFiles');
    const selectedOptions = Array.from(relatedFilesSelect.selectedOptions);
    const relatedFileIds = selectedOptions.map(option => option.value).filter(id => id && id.trim() !== '');
    
    console.log('Saving related files:', relatedFileIds);
    console.log('Selected options:', selectedOptions.map(option => ({ value: option.value, text: option.text })));
    
    const formData = new FormData();
    relatedFileIds.forEach((id, index) => {
        formData.append(`related_files[${index}]`, id);
    });
    
    // Log what we're sending
    console.log('Form data prepared for related files save');
    for (let [key, value] of formData.entries()) {
        console.log(`${key}: ${value}`);
    }
    
    saveSectionData('relatedFilesInfo', formData, function() {
        console.log('Related files saved successfully');
        
        // Update summary view on success
        const summaryView = document.getElementById('relatedFilesInfoSummary');
        
        if (relatedFileIds.length > 0) {
            let summaryHTML = '<div style="margin-top: 15px;">';
            selectedOptions.forEach(option => {
                const text = option.text;
                summaryHTML += `
                    <div class="related-file-entry-compact" style="margin-bottom: 12px; padding: 12px; background: #f8f9fa; border-radius: 6px; border-left: 3px solid #17a2b8;">
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; align-items: center;">
                            <div class="summary-item-inline">
                                <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">CLIENT NAME:</span>
                                <span class="summary-value" style="color: #212529; font-weight: 500;">${text}</span>
                            </div>
                        </div>
                    </div>
                `;
            });
            summaryHTML += '</div>';
            summaryView.innerHTML = summaryHTML;
        } else {
            summaryView.innerHTML = '<div class="empty-state" style="margin-top: 15px;"><p>No related files added yet.</p></div>';
        }
        
        // Return to summary view
        cancelEdit('relatedFilesInfo');
    });
};

/**
 * Save partner information and update summary
 */
window.savePartnerInfo = function() {
    // Get all partner entries
    const container = document.getElementById('partnerContainer');
    const sections = container.querySelectorAll('.repeatable-section');
    const partners = [];
    let validationErrors = [];
    
    sections.forEach((section, index) => {
        console.log(`Processing partner section ${index + 1}:`);
        console.log('Section element:', section);
        console.log('Section HTML:', section.outerHTML.substring(0, 500) + '...');
        
        const partnerId = section.querySelector('input[name*="partner_id"]')?.value;
        const details = section.querySelector('.partner-details').value;
        const relationshipType = section.querySelector('select[name*="partner_relationship_type"]').value;
        const gender = section.querySelector('select[name*="partner_gender"]').value;
        const companyType = section.querySelector('select[name*="partner_company_type"]').value;
        
        // Check if extra fields exist in this section
        const lastNameField = section.querySelector('input[name*="partner_last_name"]');
        const dobField = section.querySelector('input[name*="partner_dob"]');
        const emailField = section.querySelector('input[name*="partner_email"]');
        const firstNameField = section.querySelector('input[name*="partner_first_name"]');
        const phoneField = section.querySelector('input[name*="partner_phone"]');
        
        const lastName = lastNameField?.value || '';
        const dob = dobField?.value || '';
        const email = emailField?.value || '';
        const firstName = firstNameField?.value || '';
        const phone = phoneField?.value || '';
        
        console.log(`Section ${index + 1} field detection:`, {
            hasDetailsField: !!section.querySelector('.partner-details'),
            hasRelationshipField: !!section.querySelector('select[name*="partner_relationship_type"]'),
            hasGenderField: !!section.querySelector('select[name*="partner_gender"]'),
            hasCompanyTypeField: !!section.querySelector('select[name*="partner_company_type"]'),
            hasLastNameField: !!lastNameField,
            hasDobField: !!dobField,
            hasEmailField: !!emailField,
            hasFirstNameField: !!firstNameField,
            hasPhoneField: !!phoneField
        });
        
        // Simple approach: Only validate Last Name and DOB if they have values or if they're actually visible
        // Check if the extra fields section exists and is not hidden
        const extraFieldsSection = section.querySelector('.partner-extra-fields');
        const isExtraFieldsVisible = extraFieldsSection && !extraFieldsSection.classList.contains('hidden-fields');
        
        // Check if this section has any data
        console.log(`Section ${index + 1} data check:`, {
            details: details,
            relationshipType: relationshipType,
            gender: gender,
            companyType: companyType,
            lastName: lastName,
            dob: dob,
            email: email,
            firstName: firstName,
            phone: phone,
            hasData: !!(details || relationshipType || gender || companyType || lastName || dob || email || firstName || phone)
        });
        
        // Check if this section has any form fields (regardless of whether they have data)
        const hasFormFields = section.querySelector('.partner-details') || 
                             section.querySelector('select[name*="partner_relationship_type"]') || 
                             section.querySelector('select[name*="partner_gender"]') || 
                             section.querySelector('select[name*="partner_company_type"]') ||
                             lastNameField || dobField || emailField || firstNameField || phoneField;
        
        if (hasFormFields) {
            console.log(`Section ${index + 1} - Validation triggered because form fields exist`);
            // Validate required fields
            const errors = [];
            if (!relationshipType) errors.push('Relationship Type');
            if (!gender) errors.push('Gender');
            if (!companyType) errors.push('Company Type');
            
            // Debug logging
            console.log('Validation debug:', {
                details: details,
                detailsEmpty: !details || details.trim() === '',
                lastName: lastName,
                dob: dob,
                hasLastNameField: !!lastNameField,
                hasDobField: !!dobField
            });
            
            // Conditional validation based on Details field:
            // If Details is empty (no existing client found), validate Last Name and DOB
            // If Details is not empty (existing client found), skip Last Name and DOB validation
            if (!details || details.trim() === '') {
                // Details field is empty - validate Last Name and DOB
                console.log('Details is empty - validating Last Name and DOB');
                console.log('Last Name value:', lastName, 'DOB value:', dob);
                
                if (!lastName || lastName.trim() === '') {
                    console.log('Adding Last Name error');
                    errors.push('Last Name');
                }
                if (!dob || dob.trim() === '') {
                    console.log('Adding DOB error');
                    errors.push('DOB');
                }
            } else {
                console.log('Details is not empty - skipping Last Name and DOB validation');
            }
            
            if (errors.length > 0) {
                console.log(`Section ${index + 1} validation errors:`, errors);
                validationErrors = validationErrors.concat(errors);
            } else {
                console.log(`Section ${index + 1} - no validation errors`);
                partners.push({
                    partner_id: (partnerId && partnerId !== '0') ? partnerId : null,
                    details: details,
                    relationship_type: relationshipType,
                    gender: gender,
                    company_type: companyType,
                    last_name: lastName || '',
                    dob: dob || '',
                    email: email || '',
                    first_name: firstName || '',
                    phone: phone || ''
                });
            }
        } else {
            console.log(`Section ${index + 1} - Validation NOT triggered because no form fields exist`);
        }
    });
    
    // Check if there are any validation errors
    console.log('Final validation check:', {
        totalSections: sections.length,
        validationErrors: validationErrors,
        hasErrors: validationErrors.length > 0
    });
    
    if (validationErrors.length > 0) {
        console.log('Total validation errors found:', validationErrors);
        console.log('All partner sections processed:', sections.length);
        showNotification(`Please fill in the following required fields: ${validationErrors.join(', ')}`, 'error');
        return; // Exit the function early
    }
    
    const formData = new FormData();
    formData.append('partners', JSON.stringify(partners));
    
    saveSectionData('partnerInfo', formData, function() {
        // Update summary view on success
        const summaryView = document.getElementById('partnerInfoSummary');
        
        if (partners.length > 0) {
            let summaryHTML = '<div style="margin-top: 15px;">';
            partners.forEach(partner => {
                summaryHTML += `
                    <div class="partner-entry-compact" style="margin-bottom: 12px; padding: 12px; background: #f8f9fa; border-radius: 6px; border-left: 3px solid #007bff;">
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; align-items: center;">
                            <div class="summary-item-inline">
                                <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">DETAILS:</span>
                                <span class="summary-value" style="color: #212529; font-weight: 500;">${partner.details || 'Not set'}</span>
                            </div>
                            <div class="summary-item-inline">
                                <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">RELATIONSHIP:</span>
                                <span class="summary-value" style="color: #212529; font-weight: 500;">${partner.relationship_type || 'Not set'}</span>
                            </div>
                            <div class="summary-item-inline">
                                <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">GENDER:</span>
                                <span class="summary-value" style="color: #212529; font-weight: 500;">${partner.gender || 'Not set'}</span>
                            </div>
                            <div class="summary-item-inline">
                                <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">COMPANY TYPE:</span>
                                <span class="summary-value" style="color: #212529; font-weight: 500;">${partner.company_type || 'Not set'}</span>
                            </div>
                        </div>
                    </div>
                `;
            });
            summaryHTML += '</div>';
            summaryView.innerHTML = summaryHTML;
        } else {
            summaryView.innerHTML = '<div class="empty-state" style="margin-top: 15px;"><p>No partner information added yet.</p></div>';
        }
        
        // Return to summary view
        cancelEdit('partnerInfo');
    });
};

/**
 * Save children information and update summary
 */
window.saveChildrenInfo = function() {
    // Get all children entries
    const container = document.getElementById('childrenContainer');
    const sections = container.querySelectorAll('.repeatable-section');
    const children = [];
    let validationErrors = [];
    
    sections.forEach((section, index) => {
        console.log(`Processing children section ${index + 1}:`);
        console.log('Section element:', section);
        console.log('Section HTML:', section.outerHTML.substring(0, 500) + '...');
        
        const childId = section.querySelector('input[name*="children_id"]')?.value;
        const details = section.querySelector('.partner-details').value;
        const relationshipType = section.querySelector('select[name*="children_relationship_type"]').value;
        const gender = section.querySelector('select[name*="children_gender"]').value;
        const companyType = section.querySelector('select[name*="children_company_type"]').value;
        
        // Check if extra fields exist in this section
        const lastNameField = section.querySelector('input[name*="children_last_name"]');
        const dobField = section.querySelector('input[name*="children_dob"]');
        const emailField = section.querySelector('input[name*="children_email"]');
        const firstNameField = section.querySelector('input[name*="children_first_name"]');
        const phoneField = section.querySelector('input[name*="children_phone"]');
        
        const lastName = lastNameField?.value || '';
        const dob = dobField?.value || '';
        const email = emailField?.value || '';
        const firstName = firstNameField?.value || '';
        const phone = phoneField?.value || '';
        
        console.log(`Section ${index + 1} field detection:`, {
            hasDetailsField: !!section.querySelector('.partner-details'),
            hasRelationshipField: !!section.querySelector('select[name*="children_relationship_type"]'),
            hasGenderField: !!section.querySelector('select[name*="children_gender"]'),
            hasCompanyTypeField: !!section.querySelector('select[name*="children_company_type"]'),
            hasLastNameField: !!lastNameField,
            hasDobField: !!dobField,
            hasEmailField: !!emailField,
            hasFirstNameField: !!firstNameField,
            hasPhoneField: !!phoneField
        });
        
        // Simple approach: Only validate Last Name and DOB if they have values or if they're actually visible
        // Check if the extra fields section exists and is not hidden
        const extraFieldsSection = section.querySelector('.partner-extra-fields');
        const isExtraFieldsVisible = extraFieldsSection && !extraFieldsSection.classList.contains('hidden-fields');
        
        // Check if this section has any data
        console.log(`Section ${index + 1} data check:`, {
            details: details,
            relationshipType: relationshipType,
            gender: gender,
            companyType: companyType,
            lastName: lastName,
            dob: dob,
            email: email,
            firstName: firstName,
            phone: phone,
            hasData: !!(details || relationshipType || gender || companyType || lastName || dob || email || firstName || phone)
        });
        
        // Check if this section has any form fields (regardless of whether they have data)
        const hasFormFields = section.querySelector('.partner-details') || 
                             section.querySelector('select[name*="children_relationship_type"]') || 
                             section.querySelector('select[name*="children_gender"]') || 
                             section.querySelector('select[name*="children_company_type"]') ||
                             lastNameField || dobField || emailField || firstNameField || phoneField;
        
        if (hasFormFields) {
            console.log(`Section ${index + 1} - Validation triggered because form fields exist`);
            // Validate required fields
            const errors = [];
            if (!relationshipType) errors.push('Relationship Type');
            if (!gender) errors.push('Gender');
            if (!companyType) errors.push('Company Type');
            
            // Debug logging
            console.log('Validation debug:', {
                details: details,
                relationshipType: relationshipType,
                gender: gender,
                companyType: companyType,
                lastName: lastName,
                dob: dob,
                isExtraFieldsVisible: isExtraFieldsVisible
            });

            // Conditional validation based on Details field:
            // If Details is empty (no existing client found), validate Last Name and DOB
            // If Details is not empty (existing client found), skip Last Name and DOB validation
            if (!details || details.trim() === '') {
                // Details field is empty - validate Last Name and DOB
                console.log('Details is empty - validating Last Name and DOB');
                console.log('Last Name value:', lastName, 'DOB value:', dob);
                
                if (!lastName || lastName.trim() === '') {
                    console.log('Adding Last Name error');
                    errors.push('Last Name');
                }
                if (!dob || dob.trim() === '') {
                    console.log('Adding DOB error');
                    errors.push('DOB');
                }
            } else {
                console.log('Details is not empty - skipping Last Name and DOB validation');
            }
            
            if (errors.length > 0) {
                console.log(`Section ${index + 1} validation errors:`, errors);
                validationErrors = validationErrors.concat(errors);
            } else {
                console.log(`Section ${index + 1} - no validation errors`);
                children.push({
                    child_id: (childId && childId !== '0') ? childId : '',
                    details: details,
                    relationship_type: relationshipType,
                    gender: gender,
                    company_type: companyType,
                    last_name: lastName || '',
                    dob: dob || '',
                    email: email || '',
                    first_name: firstName || '',
                    phone: phone || ''
                });
            }
        } else {
            console.log(`Section ${index + 1} - No form fields found, skipping`);
        }
    });
    
    // Check for validation errors before proceeding
    if (validationErrors.length > 0) {
        const uniqueErrors = [...new Set(validationErrors)]; // Remove duplicates
        const errorMessage = `Please fill in the following required fields:\n• ${uniqueErrors.join('\n• ')}`;
        showNotification(errorMessage, 'error');
        console.log('Validation errors:', uniqueErrors);
        return; // Stop execution if there are validation errors
    }
    
    const formData = new FormData();
    formData.append('children', JSON.stringify(children));
    
    saveSectionData('childrenInfo', formData, function() {
        // Update summary view on success
        const summaryView = document.getElementById('childrenInfoSummary');
        
        if (children.length > 0) {
            let summaryHTML = '<div style="margin-top: 15px;">';
            children.forEach(child => {
                summaryHTML += `
                    <div class="children-entry-compact" style="margin-bottom: 12px; padding: 12px; background: #f8f9fa; border-radius: 6px; border-left: 3px solid #007bff;">
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; align-items: center;">
                            <div class="summary-item-inline">
                                <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">DETAILS:</span>
                                <span class="summary-value" style="color: #212529; font-weight: 500;">${child.details || 'Not set'}</span>
                            </div>
                            <div class="summary-item-inline">
                                <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">RELATIONSHIP:</span>
                                <span class="summary-value" style="color: #212529; font-weight: 500;">${child.relationship_type || 'Not set'}</span>
                            </div>
                            <div class="summary-item-inline">
                                <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">GENDER:</span>
                                <span class="summary-value" style="color: #212529; font-weight: 500;">${child.gender || 'Not set'}</span>
                            </div>
                            <div class="summary-item-inline">
                                <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">COMPANY TYPE:</span>
                                <span class="summary-value" style="color: #212529; font-weight: 500;">${child.company_type || 'Not set'}</span>
                            </div>
                        </div>
                    </div>
                `;
            });
            summaryHTML += '</div>';
            summaryView.innerHTML = summaryHTML;
        } else {
            summaryView.innerHTML = '<div class="empty-state" style="margin-top: 15px;"><p>No children information added yet.</p></div>';
        }
        
        // Return to summary view
        cancelEdit('childrenInfo');
    });
};

// ===== PARTNER EOI INFORMATION FUNCTIONS =====

window.savePartnerEoiInfo = function() {
    // Get the selected partner ID
    const selectedPartnerSelect = document.querySelector('select[name="selected_partner_id"]');
    const selectedPartnerId = selectedPartnerSelect ? selectedPartnerSelect.value : '';
    
    if (!selectedPartnerId) {
        showNotification('Please select a partner for EOI calculation', 'error');
        return;
    }
    
    const formData = new FormData();
    formData.append('selected_partner_id', selectedPartnerId);
    
    saveSectionData('partnerEoiInfo', formData, function() {
        showNotification('Partner EOI information saved successfully!', 'success');
        
        // Return to summary view
        cancelEdit('partnerEoiInfo');
        
        // Reload page to show updated data
        setTimeout(() => {
            window.location.reload();
        }, 1000);
    });
};

// ===== PARTNER EOI AUTO-POPULATION FUNCTIONS =====

// Function to fetch and display partner EOI data when partner is selected
function fetchPartnerEoiData(partnerId) {
    const formFields = document.getElementById('partnerEoiFormFields');
    
    if (!partnerId || partnerId === '') {
        // Hide form fields when no partner is selected
        if (formFields) {
            formFields.style.display = 'none';
        }
        return;
    }

    // Show form fields and display loading in all fields
    if (formFields) {
        formFields.style.display = 'block';
        // Show loading in each field
        const inputs = formFields.querySelectorAll('input, select');
        inputs.forEach(input => {
            if (input.tagName === 'INPUT') {
                input.value = 'Loading...';
            }
        });
    }

    // Fetch partner data
    fetch(`/clients/partner-eoi-data/${partnerId}`, {
        method: 'GET',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Accept': 'application/json',
            'Content-Type': 'application/json'
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            displayPartnerEoiData(data.data);
        } else {
            // Show error message
            if (formFields) {
                formFields.innerHTML = `
                    <div class="alert alert-danger" style="margin-top: 20px;">
                        ${crmI('fas fa-exclamation-triangle')} 
                        <strong>Error Loading Partner Data</strong>
                        <p>${data.message}</p>
                        <p><small>The partner may not have a complete profile or required data for EOI calculation.</small></p>
                    </div>
                `;
            }
        }
    })
    .catch(error => {
        console.error('Error fetching partner EOI data:', error);
        if (formFields) {
            formFields.innerHTML = `
                <div class="alert alert-danger" style="margin-top: 20px;">
                    ${crmI('fas fa-exclamation-triangle')} 
                    <strong>Error Loading Partner Data</strong>
                    <p>Unable to fetch partner information. Please try again.</p>
                    <p><small>Error: ${error.message}</small></p>
                </div>
            `;
        }
    });
}

// Function to display partner EOI data in form fields
function displayPartnerEoiData(partnerData) {
    // Show the form fields section
    const formFields = document.getElementById('partnerEoiFormFields');
    if (formFields) {
        formFields.style.display = 'block';
    }

    // Populate basic information
    document.getElementById('partner_name').value = partnerData.partner_name || '';
    document.getElementById('partner_dob').value = partnerData.dob || 'Not set';
    
    // Calculate and display age
    if (partnerData.dob && partnerData.dob !== 'Not set') {
        try {
            const dobParts = partnerData.dob.split('/');
            const dob = new Date(dobParts[2], dobParts[1] - 1, dobParts[0]);
            const today = new Date();
            let age = today.getFullYear() - dob.getFullYear();
            const monthDiff = today.getMonth() - dob.getMonth();
            if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < dob.getDate())) {
                age--;
            }
            document.getElementById('partner_age').value = age + ' years old';
        } catch (e) {
            document.getElementById('partner_age').value = 'Not set';
        }
    } else {
        document.getElementById('partner_age').value = 'Not set';
    }
    
    // Populate citizenship and PR status
    document.getElementById('partner_is_citizen').value = partnerData.is_citizen ? '1' : '0';
    document.getElementById('partner_has_pr').value = partnerData.has_pr ? '1' : '0';
    
    // Populate English test scores
    if (partnerData.english_test) {
        document.getElementById('partner_test_type').value = partnerData.english_test.test_type || 'Not set';
        document.getElementById('partner_test_date').value = partnerData.english_test.test_date || 'Not set';
        document.getElementById('partner_listening').value = partnerData.english_test.listening || 'Not set';
        document.getElementById('partner_reading').value = partnerData.english_test.reading || 'Not set';
        document.getElementById('partner_writing').value = partnerData.english_test.writing || 'Not set';
        document.getElementById('partner_speaking').value = partnerData.english_test.speaking || 'Not set';
        document.getElementById('partner_overall').value = partnerData.english_test.overall || 'Not set';
    } else {
        document.getElementById('partner_test_type').value = 'Not set';
        document.getElementById('partner_test_date').value = 'Not set';
        document.getElementById('partner_listening').value = 'Not set';
        document.getElementById('partner_reading').value = 'Not set';
        document.getElementById('partner_writing').value = 'Not set';
        document.getElementById('partner_speaking').value = 'Not set';
        document.getElementById('partner_overall').value = 'Not set';
    }
    
    // Populate skills assessment
    if (partnerData.skills_assessment) {
        document.getElementById('partner_has_assessment').value = partnerData.skills_assessment.has_assessment || 'Not set';
        document.getElementById('partner_occupation').value = partnerData.skills_assessment.occupation || 'Not set';
        document.getElementById('partner_assessment_date').value = partnerData.skills_assessment.assessment_date || 'Not set';
        document.getElementById('partner_assessment_status').value = partnerData.skills_assessment.status || 'Not set';
    } else {
        document.getElementById('partner_has_assessment').value = 'No';
        document.getElementById('partner_occupation').value = 'Not set';
        document.getElementById('partner_assessment_date').value = 'Not set';
        document.getElementById('partner_assessment_status').value = 'Not set';
    }
}

// Partner selection event listener is now attached in toggleEditMode('partnerEoiInfo')
// This ensures the dropdown is visible before attaching the listener

/**
 * Save EOI information and update summary
 */
window.saveEoiInfo = function() {
    // Get all EOI entries
    const container = document.getElementById('eoiReferencesContainer');
    const sections = container.querySelectorAll('.repeatable-section');
    const eois = [];
    
    sections.forEach(section => {
        const eoiId = section.querySelector('input[name*="eoi_id"]')?.value;
        const eoiNumber = section.querySelector('input[name*="EOI_number"]').value;
        const subclass = section.querySelector('input[name*="EOI_subclass"]').value;
        const occupation = section.querySelector('input[name*="EOI_occupation"]').value;
        const point = section.querySelector('input[name*="EOI_point"]').value;
        const state = section.querySelector('input[name*="EOI_state"]').value;
        const submissionDate = section.querySelector('input[name*="EOI_submission_date"]').value;
        const roi = section.querySelector('input[name*="EOI_ROI"]').value;
        
        if (eoiNumber || subclass || occupation || point || state || submissionDate || roi) {
            eois.push({
                eoi_id: eoiId || '',
                eoi_number: eoiNumber,
                subclass: subclass,
                occupation: occupation,
                point: point,
                state: state,
                submission_date: submissionDate,
                roi: roi
            });
        }
    });
    
    const formData = new FormData();
    formData.append('eois', JSON.stringify(eois));
    
    saveSectionData('eoiInfo', formData, function() {
        // Update summary view on success
        const summaryView = document.getElementById('eoiInfoSummary');
        
        if (eois.length > 0) {
            let summaryHTML = '<div style="margin-top: 15px;">';
            eois.forEach(eoi => {
                summaryHTML += `
                    <div class="eoi-entry-compact" style="margin-bottom: 12px; padding: 12px; background: #f8f9fa; border-radius: 6px; border-left: 3px solid #007bff;">
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; align-items: center;">
                            <div class="summary-item-inline">
                                <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">EOI NUMBER:</span>
                                <span class="summary-value" style="color: #212529; font-weight: 500;">${eoi.eoi_number || 'Not set'}</span>
                            </div>
                            <div class="summary-item-inline">
                                <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">SUBCLASS:</span>
                                <span class="summary-value" style="color: #212529; font-weight: 500;">${eoi.subclass || 'Not set'}</span>
                            </div>
                            <div class="summary-item-inline">
                                <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">OCCUPATION:</span>
                                <span class="summary-value" style="color: #212529; font-weight: 500;">${eoi.occupation || 'Not set'}</span>
                            </div>
                            <div class="summary-item-inline">
                                <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">POINT:</span>
                                <span class="summary-value" style="color: #212529; font-weight: 500;">${eoi.point || 'Not set'}</span>
                            </div>
                            <div class="summary-item-inline">
                                <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">STATE:</span>
                                <span class="summary-value" style="color: #212529; font-weight: 500;">${eoi.state || 'Not set'}</span>
                            </div>
                            <div class="summary-item-inline">
                                <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">SUBMISSION DATE:</span>
                                <span class="summary-value" style="color: #212529; font-weight: 500;">${eoi.submission_date || 'Not set'}</span>
                            </div>
                            <div class="summary-item-inline">
                                <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">ROI:</span>
                                <span class="summary-value" style="color: #212529; font-weight: 500;">${eoi.roi || 'Not set'}</span>
                            </div>
                        </div>
                    </div>
                `;
            });
            summaryHTML += '</div>';
            summaryView.innerHTML = summaryHTML;
        } else {
            summaryView.innerHTML = '<div class="empty-state" style="margin-top: 15px;"><p>No EOI references added yet.</p></div>';
        }
        
        // Return to summary view
        cancelEdit('eoiInfo');
    });
};

/**
 * Remove phone field with confirmation
 */
window.removePhoneField = function(button) {
    if (confirm('Are you sure you want to remove this phone number?')) {
        button.closest('.repeatable-section').remove();
        validatePersonalPhoneNumbers();
    }
};

/**
 * Remove email field with confirmation
 */
window.removeEmailField = function(button) {
    if (confirm('Are you sure you want to remove this email address?')) {
        button.closest('.repeatable-section').remove();
        validatePersonalEmailTypes();
    }
};

/**
 * Remove passport field with confirmation
 */
window.removePassportField = function(button) {
    if (confirm('Are you sure you want to remove this passport detail?')) {
        const section = button.closest('.repeatable-section');
        
        if (!section) {
            return;
        }
        
        // Get the passport ID if it exists (for existing records)
        const passportIdInput = section.querySelector('input[name*="passport_id"]');
        if (passportIdInput && passportIdInput.value) {
            // Create hidden input to track deletion
            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = 'delete_passport_ids[]';
            hiddenInput.value = passportIdInput.value;
            
            const form = getClientEditForm();
            if (form) {
                form.appendChild(hiddenInput);
            }
        }
        
        // Remove the section from DOM
        section.remove();
    }
};

/**
 * Remove visa field with confirmation
 */
window.removeVisaField = function(button) {
    if (confirm('Are you sure you want to remove this visa detail?')) {
        button.closest('.repeatable-section').remove();
    }
};

/**
 * Remove address field with confirmation
 */
window.removeAddressField = function(button) {
    if (confirm('Are you sure you want to remove this address?')) {
        button.closest('.repeatable-section').remove();
    }
};

/**
 * Remove travel field with confirmation
 */
window.removeTravelField = function(button) {
    if (confirm('Are you sure you want to remove this travel detail?')) {
        button.closest('.repeatable-section').remove();
    }
};

/**
 * Remove qualification field with confirmation
 */
window.removeQualificationField = function(button) {
    if (confirm('Are you sure you want to remove this qualification?')) {
        const section = button.closest('.repeatable-section');
        const qualificationId = section.querySelector('input[name*="qualification_id"]')?.value;
        
        // If this is an existing qualification (has an ID), track it for deletion
        if (qualificationId) {
            // Create a hidden input to track the deletion
            const container = document.getElementById('qualificationsContainer');
            const deleteInput = document.createElement('input');
            deleteInput.type = 'hidden';
            deleteInput.name = 'delete_qualification_ids[]';
            deleteInput.value = qualificationId;
            container.appendChild(deleteInput);
        }
        
        // Remove the section from the DOM
        section.remove();
    }
};

/**
 * Remove experience field with confirmation
 */
window.removeExperienceField = function(button) {
    if (confirm('Are you sure you want to remove this work experience?')) {
        button.closest('.experience-entry-wrapper, .repeatable-section').remove();
        syncCurrentExperienceIndicators();
    }
};

/**
 * Remove character field with confirmation
 */
window.removeCharacterField = function(button) {
    if (confirm('Are you sure you want to remove this character information?')) {
        button.closest('.repeatable-section').remove();
    }
};

/**
 * Remove EOI field with confirmation
 */
window.removeEoiField = function(button) {
    if (confirm('Are you sure you want to remove this EOI reference?')) {
        button.closest('.repeatable-section').remove();
    }
};

/**
 * Show notification message
 */
function showNotification(message, type = 'info') {
    // Determine icon based on notification type
    let icon = 'info-circle';
    if (type === 'success') {
        icon = 'check-circle';
    } else if (type === 'error') {
        icon = 'exclamation-circle';
    }
    
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <div class="notification-content">
            ${crmI('fas fa-' + icon)}
            <span>${message}</span>
        </div>
        <button class="notification-close" onclick="this.parentElement.remove()">
            ${crmI('fas fa-times')}
        </button>
    `;
    
    // Add to page
    document.body.appendChild(notification);
    
    // Auto remove after 5 seconds for errors, 3 seconds for others
    const duration = type === 'error' ? 5000 : 3000;
    setTimeout(() => {
        if (notification.parentElement) {
            notification.remove();
        }
    }, duration);
}

// Make functions globally available
// Global function assignments
window.initGoogleMaps = initGoogleMaps;
window.addPartnerRow = addPartnerRow;
window.removePartnerRow = removePartnerRow;
window.addEoiReference = addEoiReference;
window.toggleVisaDetails = toggleVisaDetails;
window.addPassportDetail = addPassportDetail;
window.addTravelDetail = addTravelDetail;
window.addAddress = addAddress;
window.addAnotherAddress = addAnotherAddress;
window.syncCurrentAddressIndicators = syncCurrentAddressIndicators;
window.syncCurrentExperienceIndicators = syncCurrentExperienceIndicators;
window.removeAddressEntry = removeAddressEntry;
window.addQualification = addQualification;
window.addExperience = addExperience;
window.calculateAge = calculateAge;
window.addPhoneNumber = addPhoneNumber;
window.validatePersonalPhoneNumbers = validatePersonalPhoneNumbers;
window.addEmailAddress = addEmailAddress;
window.validatePersonalEmailTypes = validatePersonalEmailTypes;
window.addVisaDetail = addVisaDetail;
window.initializeDatepickers = initializeDatepickers;
window.toggleSpouseDetailsSection = toggleSpouseDetailsSection;
window.addCharacterRow = addCharacterRow;

// New scroll and modal functions
window.scrollToSection = scrollToSection;
window.toggleSidebar = toggleSidebar;
window.scrollToTop = scrollToTop;

// ===== DOCUMENT READY =====
$(document).ready(function() {
    // Call initialization when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeTabs);
    } else {
        initializeTabs();
    }
    
    // Initialize datepickers on page load
    initializeDatepickers();

    if (document.getElementById('addresses-container')) {
        syncCurrentAddressIndicators();
    }

    if (document.getElementById('experienceContainer')) {
        syncCurrentExperienceIndicators();
    }

    // Readonly current-address indicator follows latest start date in edit mode
    $(document).on('change input', '#addresses-container input[name="address_start_date[]"]', function() {
        syncCurrentAddressIndicators();
    });

    // Readonly current-experience indicator: ongoing (no finish) else latest finish
    $(document).on('change input', '#experienceContainer input[name*="job_finish_date"], #experienceContainer input[name*="job_start_date"]', function() {
        syncCurrentExperienceIndicators();
    });
    
    // Initialize scroll spy for sidebar navigation
    initScrollSpy();
    
    // Initialize Go to Top button
    initGoToTopButton();

    // Initialize age on page load and set up datepicker for DOB
    const dobInput = document.getElementById('dob');
    const ageInput = document.getElementById('age');

    if (dobInput && ageInput) {
        // Initialize age if DOB exists
        if (dobInput.value) {
            ageInput.value = calculateAge(dobInput.value);
        }

        // Function to update age
        const updateAge = function() {
            const dobValue = dobInput.value;
            ageInput.value = calculateAge(dobValue);
        };

        // Handle manual input changes (e.g., typing or pasting)
        dobInput.addEventListener('input', updateAge);

        // Initialize Flatpickr for DOB field with age calculation
        if (typeof flatpickr !== 'undefined') {
            // Check if already initialized
            if (!$(dobInput).data('flatpickr')) {
                flatpickr(dobInput, {
                    dateFormat: 'd/m/Y',
                    allowInput: true,
                    clickOpens: true,
                    defaultDate: dobInput.value || null,
                    maxDate: 'today', // DOB cannot be in the future
                    minDate: '01/01/1000',
                    locale: {
                        firstDayOfWeek: 1 // Monday
                    },
                    onChange: function(selectedDates, dateStr, instance) {
                        // Update the input value and calculate age when a date is selected
                        dobInput.value = dateStr;
                        updateAge();
                    }
                });
            }
        } else {
            console.warn('⚠️ Flatpickr not loaded for DOB field');
        }
        
        // Fallback for any direct changes
        $(dobInput).on('change', updateAge);
    }


    // Update autocomplete to handle all family member types
    $(document).on('input', '.partner-details', function() {
        const $input = $(this);
        const query = $input.val().trim();
        const $row = $input.closest('.repeatable-section');
        const $autocomplete = $row.find('.autocomplete-items');
        const $extraFields = $row.find('.partner-extra-fields');

        const type = $row.closest('[id$="Container"]').attr('id').replace('Container', '');
        const $partnerIdInput = $row.find('.partner-id');

        // Clear autocomplete if query is too short
        if (query.length < 3) {
            $autocomplete.empty();
            // If query is empty, clear partner ID and hide extra fields
            if (query.length === 0) {
                $partnerIdInput.val(''); // Clear the partner ID
                $extraFields.hide(); // Hide extra fields
                $input.attr('readonly', false); // Make field editable
            }
            return;
        }

        // Get the search route from window config
        const searchRoute = window.editClientConfig?.searchPartnerRoute;
        const csrfToken = window.editClientConfig?.csrfToken;

        if (!searchRoute || !csrfToken) {
            console.error('Search partner route or CSRF token not configured');
            return;
        }

        $.ajax({
            url: searchRoute,
            method: 'POST',
            data: {
                query: query,
                _token: csrfToken
            },
            success: function(response) {
                $autocomplete.empty();
                // Check if response contains partners array
                if (response.partners && Array.isArray(response.partners) && response.partners.length > 0) {
                    response.partners.forEach(function(client) {
                        const displayText = `${client.first_name} ${client.last_name || ''} (${client.email}, ${client.phone}, ${client.client_id})`;
                        const $item = $('<div class="autocomplete-item"></div>')
                            .text(displayText)
                            .data('client', client)
                            .appendTo($autocomplete);

                        $item.on('click', function() {
                            const clientData = $(this).data('client');
                            $input.val(displayText);
                            $input.attr('readonly', true);
                            $partnerIdInput.val(clientData.id); // Set the partner ID
                            $extraFields.hide();
                            $autocomplete.empty();
                        });
                    });
                } else {
                    // No matching records found - clear the Details field and show extra fields
                    $autocomplete.html('<div class="autocomplete-item autocomplete-no-results">No results found</div>');
                    $input.val(''); // Clear the Details field
                    $input.attr('readonly', false); // Make field editable again
                    $partnerIdInput.val(''); // Clear the partner ID
                    $extraFields.show(); // Show extra fields so user can enter details manually
                    
                    // Clear autocomplete after showing message
                    setTimeout(() => {
                        $autocomplete.empty();
                    }, 2000); // Show "No results found" for 2 seconds
                }
            },
            error: function(xhr) {
                $autocomplete.empty();
                let errorMsg = 'Error fetching client details.';
                if (xhr.status === 422) {
                    // Handle validation errors
                    const errors = xhr.responseJSON.errors;
                    if (errors && errors.query) {
                        errorMsg = errors.query[0];
                    }
                }
                $autocomplete.html(`<div class="autocomplete-item autocomplete-error">${errorMsg}</div>`);
            }
        });
    });

    // Close autocomplete when clicking outside
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.partner-details').length && !$(e.target).closest('.autocomplete-items').length) {
            $('.autocomplete-items').empty();
        }
    });

    // Run on page load to set initial state
    toggleSpouseDetailsSection();

    // Run on Marital Status change
    const maritalStatusElement = document.getElementById('maritalStatus');
    if (maritalStatusElement) {
        maritalStatusElement.addEventListener('change', function() {
            toggleSpouseDetailsSection();
        });
    }

    // Run on page load
    toggleVisaDetails();

    // Run on passport country change
    const passportCountryElement = document.getElementById('passportCountry');
    if (passportCountryElement) {
        passportCountryElement.addEventListener('change', function() {
            toggleVisaDetails();
        });
    }

    // Add event listeners for real-time validation and form submission
    const phoneNumbersContainer = document.getElementById('phoneNumbersContainer');
    if (phoneNumbersContainer) {
        // Validate on change of type
        phoneNumbersContainer.addEventListener('change', function(e) {
            if (e.target.classList.contains('contact-type-selector')) {
                validatePersonalPhoneNumbers();
            }
        });

        // Validate on form submission
        const editForm = getClientEditForm();
        if (editForm) {
            editForm.addEventListener('submit', function(e) {
                validatePersonalPhoneNumbers();
            });
        }

        // Initial validation on page load
        validatePersonalPhoneNumbers();
    }

    // Phone Verification Functions
    let currentContactId = null;
    let otpTimer = null;
    let resendTimer = null;
    let otpExpiryTime = null;

    /**
     * Send OTP to phone number
     */
    window.sendOTP = function(contactId, phone, countryCode) {
        // Validate contact ID
        if (!contactId || contactId === 'pending') {
            alert('Please save the phone number first before verifying');
            return;
        }
        
        currentContactId = contactId;
        const fullPhone = countryCode + phone;
        
        // Show modal
        document.getElementById('otpPhoneDisplay').textContent = fullPhone;
        document.getElementById('otpVerificationModal').style.display = 'block';
        
        // Clear any previous messages
        hideOTPMessages();
        
        // Clear OTP inputs
        clearOTPInputs();
        
        // Disable verify button initially
        document.getElementById('verifyOTPBtn').disabled = true;
        
        // Focus first input
        setTimeout(() => {
            document.querySelector('.otp-digit[data-index="0"]').focus();
        }, 100);
        
        // Send OTP request
        fetch('/clients/phone/send-otp', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({
                contact_id: contactId
            })
        })
        .then(response => {
            // Check if response is OK before parsing JSON
            if (!response.ok) {
                // Try to parse JSON error response, fallback to status text
                return response.json().then(data => {
                    throw { status: response.status, data: data };
                }).catch(() => {
                    throw { status: response.status, data: { message: 'Server error occurred' } };
                });
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                showOTPSuccessMessage('Verification code sent to client! Please ask them to provide the code.');
                startOTPTimer(data.expires_in_seconds || 300);
                startResendTimer(30);
            } else {
                showOTPErrorMessage(data.message || 'Failed to send verification code');
            }
        })
        .catch(error => {
            console.error('Error sending OTP:', error);
            // Show error message from server if available, otherwise generic message
            const errorMessage = error.data?.message || 'Network error. Please try again.';
            showOTPErrorMessage(errorMessage);
        });
    }

    /**
     * Verify OTP
     */
    window.verifyOTP = function() {
        console.log('[OTP Verify] Function called');
        const otpCode = getOTPCode();
        console.log('[OTP Verify] Code:', otpCode, 'Length:', otpCode.length);
        
        if (otpCode.length !== 6) {
            showOTPErrorMessage('Please enter all 6 digits');
            return;
        }
        
        // Disable verify button
        document.getElementById('verifyOTPBtn').disabled = true;
        
        fetch('/clients/phone/verify-otp', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({
                contact_id: currentContactId,
                otp_code: otpCode
            })
        })
        .then(response => {
            // Check if response is OK before parsing JSON
            if (!response.ok) {
                // Try to parse JSON error response, fallback to status text
                return response.json().then(data => {
                    throw { status: response.status, data: data };
                }).catch(() => {
                    throw { status: response.status, data: { message: 'Server error occurred' } };
                });
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                showOTPSuccessMessage('Phone number verified successfully!');
                
                // Update UI after a short delay
                setTimeout(() => {
                    updateVerificationStatus(currentContactId, true);
                    closeOTPModal();
                }, 1500);
            } else {
                showOTPErrorMessage(data.message || 'Invalid verification code');
                document.getElementById('verifyOTPBtn').disabled = false;
                
                // Clear OTP inputs on error
                if (data.message && data.message.includes('Invalid')) {
                    clearOTPInputs();
                    document.querySelector('.otp-digit[data-index="0"]').focus();
                }
            }
        })
        .catch(error => {
            console.error('Error verifying OTP:', error);
            // Show error message from server if available, otherwise generic message
            const errorMessage = error.data?.message || 'Network error. Please try again.';
            showOTPErrorMessage(errorMessage);
            document.getElementById('verifyOTPBtn').disabled = false;
        });
    }

    /**
     * Resend OTP
     */
    window.resendOTP = function() {
        if (!currentContactId) return;
        
        // Disable resend button temporarily
        document.getElementById('resendOTPBtn').disabled = true;
        
        fetch('/clients/phone/resend-otp', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({
                contact_id: currentContactId
            })
        })
        .then(response => {
            // Check if response is OK before parsing JSON
            if (!response.ok) {
                // Try to parse JSON error response, fallback to status text
                return response.json().then(data => {
                    throw { status: response.status, data: data };
                }).catch(() => {
                    throw { status: response.status, data: { message: 'Server error occurred' } };
                });
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                showOTPSuccessMessage('New verification code sent to client! Please ask them for the updated code.');
                clearOTPInputs();
                startOTPTimer(data.expires_in_seconds || 300);
                startResendTimer(30);
            } else {
                showOTPErrorMessage(data.message || 'Failed to resend verification code');
                document.getElementById('resendOTPBtn').disabled = false;
            }
        })
        .catch(error => {
            console.error('Error resending OTP:', error);
            // Show error message from server if available, otherwise generic message
            const errorMessage = error.data?.message || 'Network error. Please try again.';
            showOTPErrorMessage(errorMessage);
            document.getElementById('resendOTPBtn').disabled = false;
        });
    }

    /**
     * Close OTP modal
     */
    window.closeOTPModal = function() {
        document.getElementById('otpVerificationModal').style.display = 'none';
        currentContactId = null;
        clearOTPTimers();
        clearOTPInputs();
        hideOTPMessages();
    }

    /**
     * Get OTP code from inputs
     */
    function getOTPCode() {
        let otpCode = '';
        for (let i = 0; i < 6; i++) {
            const digit = document.querySelector(`.otp-digit[data-index="${i}"]`)?.value || '';
            otpCode += digit;
        }
        return otpCode;
    }
    
    /**
     * Check if OTP is complete and enable/disable verify button
     */
    function checkOTPComplete() {
        const otpCode = getOTPCode();
        const verifyBtn = document.getElementById('verifyOTPBtn');
        console.log('[OTP Check] Code:', otpCode, 'Length:', otpCode.length, 'Button found:', !!verifyBtn);
        if (verifyBtn) {
            verifyBtn.disabled = otpCode.length !== 6;
            console.log('[OTP Check] Button disabled:', verifyBtn.disabled);
        }
    }

    /**
     * Clear OTP inputs
     */
    function clearOTPInputs() {
        for (let i = 0; i < 6; i++) {
            const input = document.querySelector(`.otp-digit[data-index="${i}"]`);
            input.value = '';
            input.classList.remove('filled');
        }
    }

    /**
     * Start OTP expiry timer
     */
    function startOTPTimer(seconds) {
        clearOTPTimers();
        
        let timeLeft = seconds;
        const timerElement = document.getElementById('timerCountdown');
        
        otpTimer = setInterval(() => {
            const minutes = Math.floor(timeLeft / 60);
            const secs = timeLeft % 60;
            timerElement.textContent = `${minutes}:${secs.toString().padStart(2, '0')}`;
            
            if (timeLeft <= 0) {
                clearInterval(otpTimer);
                showOTPErrorMessage('Verification code has expired');
                document.getElementById('verifyOTPBtn').disabled = true;
            }
            
            timeLeft--;
        }, 1000);
    }

    /**
     * Start resend timer
     */
    function startResendTimer(seconds) {
        let timeLeft = seconds;
        const resendBtn = document.getElementById('resendOTPBtn');
        const resendTimerDisplay = document.getElementById('resendTimer');
        const countdownElement = document.getElementById('resendCountdown');
        
        resendBtn.disabled = true;
        resendTimerDisplay.style.display = 'inline';
        
        resendTimer = setInterval(() => {
            countdownElement.textContent = timeLeft;
            
            if (timeLeft <= 0) {
                clearInterval(resendTimer);
                resendBtn.disabled = false;
                resendTimerDisplay.style.display = 'none';
            }
            
            timeLeft--;
        }, 1000);
    }

    /**
     * Clear OTP timers
     */
    function clearOTPTimers() {
        if (otpTimer) {
            clearInterval(otpTimer);
            otpTimer = null;
        }
        if (resendTimer) {
            clearInterval(resendTimer);
            resendTimer = null;
        }
    }

    /**
     * Show OTP error message
     */
    function showOTPErrorMessage(message) {
        const errorElement = document.getElementById('otpErrorMessage');
        errorElement.textContent = message;
        errorElement.style.display = 'block';
        document.getElementById('otpSuccessMessage').style.display = 'none';
    }

    /**
     * Show OTP success message
     */
    function showOTPSuccessMessage(message) {
        const successElement = document.getElementById('otpSuccessMessage');
        successElement.textContent = message;
        successElement.style.display = 'block';
        document.getElementById('otpErrorMessage').style.display = 'none';
    }

    /**
     * Hide OTP messages
     */
    function hideOTPMessages() {
        document.getElementById('otpErrorMessage').style.display = 'none';
        document.getElementById('otpSuccessMessage').style.display = 'none';
    }

    /**
     * Update verification status in UI
     */
    function updateVerificationStatus(contactId, isVerified) {
        const verifyBtn = document.querySelector(`button[data-contact-id="${contactId}"]`);
        if (verifyBtn) {
            if (isVerified) {
                const summaryItem = verifyBtn.closest('.summary-item');
                if (summaryItem) {
                    const verifiedBadge = document.createElement('span');
                    verifiedBadge.className = 'verified-badge';
                    verifiedBadge.innerHTML = crmI('fas fa-check-circle') + ' Verified';
                    verifiedBadge.title = 'Verified on ' + new Date().toLocaleString();
                    
                    verifyBtn.replaceWith(verifiedBadge);
                }
            }
        }
    }

    // OTP Input Event Listeners
    // Run immediately if DOM already loaded, otherwise wait for DOMContentLoaded
    function initializeOTPListeners() {
        console.log('[OTP] Event listeners initialized');
        
        // Handle OTP input auto-focus and validation
        document.addEventListener('input', function(e) {
            if (e.target.classList.contains('otp-digit')) {
                const index = parseInt(e.target.dataset.index);
                let value = e.target.value;
                console.log('[OTP Input] Index:', index, 'Value:', value);
                
                // Allow only digits
                value = value.replace(/[^0-9]/g, '');
                e.target.value = value;
                
                // Add filled class for styling
                if (value) {
                    e.target.classList.add('filled');
                    
                    // Auto-focus next input
                    if (index < 5) {
                        const nextInput = document.querySelector(`.otp-digit[data-index="${index + 1}"]`);
                        if (nextInput) {
                            nextInput.focus();
                        }
                    }
                } else {
                    e.target.classList.remove('filled');
                }
                
                // Enable/disable verify button based on completion
                checkOTPComplete();
            }
        });
        
        // Handle backspace navigation
        document.addEventListener('keydown', function(e) {
            if (e.target.classList.contains('otp-digit') && e.key === 'Backspace') {
                const index = parseInt(e.target.dataset.index);
                
                if (!e.target.value && index > 0) {
                    // Move to previous input if current is empty
                    const prevInput = document.querySelector(`.otp-digit[data-index="${index - 1}"]`);
                    if (prevInput) {
                        prevInput.focus();
                    }
                }
            }
        });
        
        // Handle paste event
        document.addEventListener('paste', function(e) {
            if (e.target.classList.contains('otp-digit')) {
                e.preventDefault();
                const pastedData = e.clipboardData.getData('text').replace(/[^0-9]/g, '');
                
                if (pastedData.length > 0) {
                    const startIndex = parseInt(e.target.dataset.index);
                    for (let i = 0; i < pastedData.length && (startIndex + i) < 6; i++) {
                        const input = document.querySelector(`.otp-digit[data-index="${startIndex + i}"]`);
                        if (input) {
                            input.value = pastedData[i];
                            input.classList.add('filled');
                        }
                    }
                    checkOTPComplete();
                    
                    // Focus last filled input or first empty one
                    const lastIndex = Math.min(startIndex + pastedData.length - 1, 5);
                    const nextEmptyIndex = Math.min(startIndex + pastedData.length, 5);
                    const focusInput = document.querySelector(`.otp-digit[data-index="${nextEmptyIndex}"]`);
                    if (focusInput) {
                        focusInput.focus();
                    }
                }
            }
        });
        
        // Handle Enter key to submit
        document.addEventListener('keydown', function(e) {
            if (e.target.classList.contains('otp-digit') && e.key === 'Enter') {
                const otpCode = getOTPCode();
                if (otpCode.length === 6) {
                    verifyOTP();
                }
            }
        });
        
        // Close modal on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && document.getElementById('otpVerificationModal').style.display === 'block') {
                closeOTPModal();
            }
        });
    }
    
    // Initialize OTP listeners immediately or on DOMContentLoaded
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeOTPListeners);
    } else {
        // DOM already loaded, run immediately
        initializeOTPListeners();
    }

    // Add event listeners for real-time validation and form submission (emails)
    const emailAddressesContainer = document.getElementById('emailAddressesContainer');
    if (emailAddressesContainer) {
        // Validate on change of type
        emailAddressesContainer.addEventListener('change', function(e) {
            if (e.target.classList.contains('email-type-selector')) {
                validatePersonalEmailTypes();
            }
        });

        // Validate on form submission
        const editForm = getClientEditForm();
        if (editForm) {
            editForm.addEventListener('submit', function(e) {
                if (!validatePersonalEmailTypes()) {
                    e.preventDefault();
                    alert('Only one email address can be of type Personal. Please correct the entries.');
                }
            });
        }

        // Initial validation on page load
        validatePersonalEmailTypes();
    }

    // Handle EOI Reference removal with confirmation
    const eoiReferencesContainer = document.getElementById('eoiReferencesContainer');
    if (eoiReferencesContainer) {
        eoiReferencesContainer.addEventListener('click', function(e) {
            if (e.target.closest('.remove-item-btn')) {
                const section = e.target.closest('.repeatable-section');
                const eoiIdInput = section.querySelector('input[name^="eoi_id"]');
                const confirmDelete = confirm('Are you sure you want to delete this EOI Reference?');

                if (confirmDelete) {
                    if (eoiIdInput) {
                        const eoiId = eoiIdInput.value;
                        const hiddenInput = document.createElement('input');
                        hiddenInput.type = 'hidden';
                        hiddenInput.name = 'delete_eoi_ids[]';
                        hiddenInput.value = eoiId;
                        const form = getClientEditForm();
                        if (form) {
                            form.appendChild(hiddenInput);
                        }
                    }
                    section.remove();
                }
            }
        });
    }

    // Handle qualification removal
    const qualificationsContainer = document.getElementById('qualificationsContainer');
    if (qualificationsContainer) {
        qualificationsContainer.addEventListener('click', function(e) {
            if (e.target.closest('.remove-item-btn')) {
                const section = e.target.closest('.repeatable-section');
                const qualificationIdInput = section.querySelector('input[name^="qualification_id"]');
                const confirmDelete = confirm('Are you sure you want to delete this qualification?');

                if (confirmDelete) {
                    if (qualificationIdInput) {
                        const qualificationId = qualificationIdInput.value;
                        const hiddenInput = document.createElement('input');
                        hiddenInput.type = 'hidden';
                        hiddenInput.name = 'delete_qualification_ids[]';
                        hiddenInput.value = qualificationId;
                        const form = getClientEditForm();
                        if (form) {
                            form.appendChild(hiddenInput);
                        }
                    }
                    section.remove();
                }
            }
        });
    }

    // Handle experience removal
    const experienceContainer = document.getElementById('experienceContainer');
    if (experienceContainer) {
        experienceContainer.addEventListener('click', function(e) {
            if (e.target.closest('.remove-item-btn')) {
                const section = e.target.closest('.repeatable-section');
                const experienceIdInput = section.querySelector('input[name^="experience_id"]');
                const confirmDelete = confirm('Are you sure you want to delete this experience?');

                if (confirmDelete) {
                    if (experienceIdInput) {
                        const experienceId = experienceIdInput.value;
                        const hiddenInput = document.createElement('input');
                        hiddenInput.type = 'hidden';
                        hiddenInput.name = 'delete_experience_ids[]';
                        hiddenInput.value = experienceId;
                        const form = getClientEditForm();
                        if (form) {
                            form.appendChild(hiddenInput);
                        }
                    }
                    section.remove();
                }
            }
        });
    }

    // Handle occupation removal
    const occupationContainer = document.getElementById('occupationContainer');
    if (occupationContainer) {
        occupationContainer.addEventListener('click', function(e) {
            if (e.target.closest('.remove-item-btn')) {
                const section = e.target.closest('.repeatable-section');
                const occupationIdInput = section.querySelector('input[name^="occupation_id"]');
                const confirmDelete = confirm('Are you sure you want to delete this occupation?');

                if (confirmDelete) {
                    if (occupationIdInput) {
                        const occupationId = occupationIdInput.value;
                        const hiddenInput = document.createElement('input');
                        hiddenInput.type = 'hidden';
                        hiddenInput.name = 'delete_occupation_ids[]';
                        hiddenInput.value = occupationId;
                        const form = getClientEditForm();
                        if (form) {
                            form.appendChild(hiddenInput);
                        }
                    }
                    section.remove();
                }
            }
        });
    }

    // Handle test score removal
    const testScoresContainer = document.getElementById('testScoresContainer');
    if (testScoresContainer) {
        testScoresContainer.addEventListener('click', function(e) {
            if (e.target.closest('.remove-item-btn')) {
                const section = e.target.closest('.repeatable-section');
                const testScoreIdInput = section.querySelector('input[name^="test_score_id"]');
                const confirmDelete = confirm('Are you sure you want to delete this test score?');

                if (confirmDelete) {
                    if (testScoreIdInput) {
                        const testScoreId = testScoreIdInput.value;
                        const hiddenInput = document.createElement('input');
                        hiddenInput.type = 'hidden';
                        hiddenInput.name = 'delete_test_score_ids[]';
                        hiddenInput.value = testScoreId;
                        const form = getClientEditForm();
                        if (form) {
                            form.appendChild(hiddenInput);
                        }
                    }
                    section.remove();
                }
            }
        });
    }

    // One-time check of email verification status on page load
    // (Does NOT start continuous polling - polling starts when email section is opened)
    setTimeout(function() {
        const emailVerifyButtons = document.querySelectorAll('.btn-verify-email');
        if (emailVerifyButtons.length > 0) {
            console.log('🔄 Page load: Checking email verification status (one-time check, no continuous polling)');
            emailVerifyButtons.forEach(button => {
                const emailId = button.getAttribute('data-email-id');
                if (isValidEmailId(emailId)) {
                    checkEmailVerificationStatus(parseInt(emailId));
                }
            });
        }
    }, 1000); // Wait 1 second after page load
});

/**
 * Go back with refresh to ensure consistent information
 */
window.goBackWithRefresh = function() {
    // Extract client ID from current URL
    const currentUrl = window.location.href;
    
    // Check if we're on an edit page
    if (currentUrl.includes('/clients/edit/')) {
        // Extract the client ID from the URL
        const urlParts = currentUrl.split('/');
        const clientId = urlParts[urlParts.indexOf('edit') + 1];

        const typeInput = document.querySelector('#editCompanyForm input[name="type"]') || document.querySelector('#editClientForm input[name="type"]');
        const clientType = (typeInput ? typeInput.value : window.currentClientType || '').toLowerCase();
        let detailPath = '/clients/detail/' + clientId;

        if (clientType === 'client') {
            const latestMatterRef = window.latestClientMatterRef || null;
            if (latestMatterRef) {
                detailPath += '/' + encodeURIComponent(latestMatterRef);
            }
        }

        // Redirect to the client detail page
        const detailUrl = window.location.origin + detailPath;
        //window.location.href = detailUrl + '?_t=' + Date.now();
        window.location.href = detailUrl;
    } else {
        // Fallback to normal back navigation
        window.history.back();
    }
};

/**
 * Email Verification Functions
 */

// Send email verification
window.sendEmailVerification = function(emailId, emailAddress) {
    // Validate email ID
    if (!emailId || emailId === 'pending' || !emailAddress) {
        alert('Please save the email first before verifying');
        return;
    }

    if (!confirm(`Send verification email to ${emailAddress}?`)) {
        return;
    }

    // Show loading state
    const button = document.querySelector(`button[data-email-id="${emailId}"]`);
    const originalContent = button.innerHTML;
    button.innerHTML = crmI('fas fa-spinner fa-spin') + ' Sending...';
    button.disabled = true;

    fetch('/clients/email/send-verification', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({
            email_id: emailId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Verification email sent successfully! Please ask the client to check their email and click the verification link.');
            
            // Update button to show resend option with polling indicator
            button.innerHTML = crmI('fas fa-redo') + ' Resend <span style="margin-left:5px;display:inline-flex;vertical-align:middle;">' + crmI('fas fa-spinner fa-spin', { size: 10 }) + '</span>';
            button.onclick = function() { resendEmailVerification(emailId, emailAddress); };
            
            // Start polling for verification status
            startEmailVerificationPolling(emailId);
        } else {
            alert('Error: ' + (data.message || 'Failed to send verification email'));
            button.innerHTML = originalContent;
        }
        button.disabled = false;
    })
    .catch(error => {
        console.error('Error sending verification email:', error);
        alert('Network error. Please try again.');
        button.innerHTML = originalContent;
        button.disabled = false;
    });
};

// Resend email verification
function resendEmailVerification(emailId, emailAddress) {
    // Validate email ID
    if (!emailId || emailId === 'pending') {
        alert('Please save the email first before verifying');
        return;
    }
    
    if (!confirm(`Resend verification email to ${emailAddress}?`)) {
        return;
    }

    const button = document.querySelector(`button[data-email-id="${emailId}"]`);
    const originalContent = button.innerHTML;
    button.innerHTML = crmI('fas fa-spinner fa-spin') + ' Sending...';
    button.disabled = true;

    fetch('/clients/email/resend-verification', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({
            email_id: emailId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Verification email resent successfully!');
            
            // Start polling for verification status
            startEmailVerificationPolling(emailId);
        } else {
            alert('Error: ' + (data.message || 'Failed to resend verification email'));
        }
        button.innerHTML = originalContent;
        button.disabled = false;
    })
    .catch(error => {
        console.error('Error resending verification email:', error);
        alert('Network error. Please try again.');
        button.innerHTML = originalContent;
        button.disabled = false;
    });
}

// Update verification status in UI
function updateEmailVerificationStatus(emailId, isVerified) {
    const verifyBtn = document.querySelector(`button[data-email-id="${emailId}"]`);
    if (verifyBtn) {
        if (isVerified) {
            const summaryItem = verifyBtn.closest('.summary-item');
            if (summaryItem) {
                const verifiedBadge = document.createElement('span');
                verifiedBadge.className = 'verified-badge';
                verifiedBadge.innerHTML = crmI('fas fa-check-circle') + ' Verified';
                verifiedBadge.title = 'Verified on ' + new Date().toLocaleString();
                
                // Replace the verify button with verified badge
                verifyBtn.parentNode.replaceChild(verifiedBadge, verifyBtn);
            }
        }
    }
    
    // Also update any detail view icons
    updateDetailViewEmailIcons(emailId, isVerified);
}

// Update email verification icons in detail views
function updateDetailViewEmailIcons(emailId, isVerified) {
    // Find the email address in detail views and update its icon
    const emailElements = document.querySelectorAll('span, div');
    emailElements.forEach(element => {
        if (element.textContent && element.textContent.includes('@')) {
            // Check if this element contains an email address
            const emailMatch = element.textContent.match(/([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/);
            if (emailMatch) {
                const emailAddress = emailMatch[1];
                
                // Find the corresponding ClientEmail record (this would need to be passed from the backend)
                // For now, we'll update based on the email address pattern
                const iconElement = element.querySelector('svg.lucide, i[data-lucide], i.verified-icon, i.unverified-icon');
                if (iconElement) {
                    const verifiedHtml = crmI('fas fa-check-circle', { class: 'verified-icon fa-lg' });
                    const unverifiedHtml = crmI('far fa-circle', { class: 'unverified-icon fa-lg' });
                    iconElement.outerHTML = isVerified ? verifiedHtml : unverifiedHtml;
                    const newIcon = element.querySelector('svg.lucide, i[data-lucide]');
                    if (newIcon) {
                        newIcon.style.color = isVerified ? '#28a745' : '#6c757d';
                        newIcon.title = isVerified
                            ? 'Verified on ' + new Date().toLocaleString()
                            : 'Not verified';
                    }
                }
            }
        }
    });
}

/**
 * Validate if email ID is valid for polling
 */
function isValidEmailId(emailId) {
    return emailId && 
           emailId !== 'pending' && 
           emailId !== 'null' && 
           emailId !== 'undefined' &&
           emailId !== '' &&
           emailId !== '0' &&
           !isNaN(parseInt(emailId)) && 
           parseInt(emailId) > 0;
}

/**
 * Store active polling intervals for cleanup
 */
const activeEmailPollingIntervals = new Map();

// Check email verification status
function checkEmailVerificationStatus(emailId) {
    // Validate email ID before making request
    if (!isValidEmailId(emailId)) {
        console.warn(`Invalid email ID for status check: ${emailId}`);
        stopEmailVerificationPolling(emailId);
        return;
    }
    
    fetch(`/clients/email/status/${emailId}`, {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        }
    })
    .then(response => {
        // Check if response is ok (status 200-299)
        if (!response.ok) {
            // Handle 404 - email record doesn't exist
            if (response.status === 404) {
                console.warn(`Email record with ID ${emailId} not found (404). Stopping polling.`);
                stopEmailVerificationPolling(emailId);
                
                // Update UI to show error state
                const verifyBtn = document.querySelector(`button[data-email-id="${emailId}"]`);
                if (verifyBtn) {
                    verifyBtn.innerHTML = crmI('fas fa-exclamation-triangle') + ' Email Not Found';
                    verifyBtn.disabled = true;
                    verifyBtn.style.opacity = '0.6';
                    verifyBtn.title = 'Email record not found. Please refresh the page.';
                }
                return null;
            }
            // For other errors, throw to be caught by catch block
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        // Skip if data is null (404 was handled above)
        if (!data) return;
        
        if (data.success && data.is_verified) {
            updateEmailVerificationStatus(emailId, true);
            
            // Show success notification
            showNotification('Email verified successfully!', 'success');
            
            // Stop polling since email is verified
            stopEmailVerificationPolling(emailId);
            
            // Refresh the page after a short delay to update all views
            setTimeout(() => {
                window.location.reload();
            }, 2000);
        } else if (data.success === false && data.message) {
            // Handle other error responses from the API
            console.warn(`Email status check failed: ${data.message}`);
            // Don't stop polling for other errors, just log them
        }
    })
    .catch(error => {
        console.error('Error checking email verification status:', error);
        // Don't stop polling on network errors, they might be temporary
    });
}

// Stop polling for email verification status
function stopEmailVerificationPolling(emailId) {
    if (activeEmailPollingIntervals.has(emailId)) {
        clearInterval(activeEmailPollingIntervals.get(emailId));
        activeEmailPollingIntervals.delete(emailId);
        console.log(`  ↳ Stopped polling for email ID ${emailId}`);
    }
}

// Start polling for email verification status
function startEmailVerificationPolling(emailId) {
    // Validate email ID before starting polling
    if (!isValidEmailId(emailId)) {
        console.warn(`Cannot start polling: Invalid email ID ${emailId}`);
        return;
    }
    
    // Stop any existing polling for this email ID
    if (activeEmailPollingIntervals.has(emailId)) {
        clearInterval(activeEmailPollingIntervals.get(emailId));
        activeEmailPollingIntervals.delete(emailId);
    }
    
    console.log(`  ↳ Starting continuous polling for email ID: ${emailId}`);
    
    // Check immediately
    checkEmailVerificationStatus(emailId);
    
    // Then check every 5 seconds for 2 minutes
    let pollCount = 0;
    const maxPolls = 24; // 2 minutes (24 * 5 seconds)
    
    const pollInterval = setInterval(() => {
        pollCount++;
        
        // Check if button still exists (not verified yet)
        const verifyBtn = document.querySelector(`button[data-email-id="${emailId}"]`);
        if (!verifyBtn) {
            // Button was replaced with verified badge, stop polling
            clearInterval(pollInterval);
            activeEmailPollingIntervals.delete(emailId);
            console.log(`  ↳ Stopped polling for email ID ${emailId} (verified)`);
            return;
        }
        
        checkEmailVerificationStatus(emailId);
        
        // Stop polling after max attempts
        if (pollCount >= maxPolls) {
            clearInterval(pollInterval);
            activeEmailPollingIntervals.delete(emailId);
            console.log(`  ↳ Stopped polling for email ID ${emailId} (max attempts reached)`);
            // Remove spinner from button
            if (verifyBtn && verifyBtn.innerHTML.includes('fa-spinner')) {
                verifyBtn.innerHTML = verifyBtn.innerHTML.replace(crmI('fas fa-spinner fa-spin'), '');
            }
        }
    }, 5000); // Check every 5 seconds
    
    // Store interval for cleanup
    activeEmailPollingIntervals.set(emailId, pollInterval);
}

/**
 * Stop all email verification polling
 */
function stopAllEmailPolling() {
    console.log('🛑 Stopping all email verification polling');
    activeEmailPollingIntervals.forEach((interval, emailId) => {
        clearInterval(interval);
        console.log(`  ↳ Stopped polling for email ID: ${emailId}`);
    });
    activeEmailPollingIntervals.clear();
}

/**
 * Initialize email section polling (one-time status check + start polling for unverified)
 */
function initializeEmailSectionPolling() {
    console.log('🔄 Initializing email section polling');
    
    const emailSection = document.getElementById('emailAddressesSummary');
    if (!emailSection) {
        console.warn('⚠️ Email section not found');
        return;
    }
    
    const emailVerifyButtons = emailSection.querySelectorAll('.btn-verify-email');
    
    if (emailVerifyButtons.length === 0) {
        console.log('✅ No unverified emails, skipping polling');
        return;
    }
    
    console.log(`📧 Found ${emailVerifyButtons.length} unverified email(s)`);
    
    // First, do a one-time refresh of all email statuses
    emailVerifyButtons.forEach(button => {
        const emailId = button.getAttribute('data-email-id');
        
        if (isValidEmailId(emailId)) {
            console.log(`  ↳ Checking status for email ID: ${emailId}`);
            // Single check, not continuous polling yet
            checkEmailVerificationStatus(parseInt(emailId));
        } else {
            console.warn(`  ↳ Invalid email ID, skipping: ${emailId}`);
        }
    });
    
    // Then start continuous polling only for valid emails
    setTimeout(() => {
        emailVerifyButtons.forEach(button => {
            const emailId = button.getAttribute('data-email-id');
            
            if (isValidEmailId(emailId)) {
                startEmailVerificationPolling(parseInt(emailId));
            }
        });
    }, 1000); // Delay to avoid race condition with initial check
}

// ===== OCCUPATION & SKILLS FUNCTIONS =====

function addOccupation() {
    // Check if we're in summary mode, if so switch to edit mode first
    const summaryView = document.getElementById('occupationInfoSummary');
    const editView = document.getElementById('occupationInfoEdit');
    
    if (summaryView && editView && summaryView.style.display !== 'none') {
        toggleEditMode('occupationInfo');
        // Wait a bit for the edit view to be displayed, then add the occupation
        setTimeout(() => {
            addOccupationRow();
        }, 100);
        return;
    }
    
    addOccupationRow();
}

function addOccupationRow() {
    const container = document.getElementById('occupationContainer');
    if (!container) {
        console.error('Occupation container not found');
        return;
    }
    
    const index = container.children.length;
    
    const newOccupationHTML = `
        <div class="repeatable-section">
            <button type="button" class="remove-item-btn" title="Remove Occupation" onclick="removeOccupationField(this)">${crmI('fas fa-trash')}</button>
            <input type="hidden" name="anzsco_occupation_id[${index}]" class="anzsco_occupation_id" value="">
            <div class="content-grid">
                <div class="form-group">
                    <label>Skill Assessment</label>
                    <select name="skill_assessment_hidden[${index}]" class="skill-assessment-select">
                        <option value="">Select</option>
                        <option value="Yes">Yes</option>
                        <option value="No">No</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Nominated Occupation</label>
                    <input type="text" name="nomi_occupation[${index}]" class="nomi_occupation" placeholder="Enter Occupation">
                    <div class="autocomplete-items"></div>
                </div>
                <div class="form-group">
                    <label>Occupation Code (ANZSCO)</label>
                    <input type="text" name="occupation_code[${index}]" class="occupation_code" placeholder="Enter Code">
                </div>
                <div class="form-group">
                    <label>Assessing Authority</label>
                    <input type="text" name="list[${index}]" class="list" placeholder="e.g., ACS, VETASSESS">
                </div>
                <div class="form-group">
                    <label>Occupation Lists</label>
                    <div class="occupation-lists-display" id="occupation-lists-${index}">
                        <span class="text-muted">Select an occupation to see lists</span>
                    </div>
                </div>
                <div class="form-group">
                    <label>Assessment Date</label>
                    <input type="date" name="dates[${index}]" class="dates">
                </div>
                <div class="form-group">
                    <label>Expiry Date</label>
                    <input type="date" name="expiry_dates[${index}]" class="expiry_dates">
                </div>
                <div class="form-group">
                    <label>Reference No</label>
                    <input type="text" name="occ_reference_no[${index}]" placeholder="Enter Reference No.">
                </div>
                <div class="form-group" style="align-items: center;">
                    <label style="margin-bottom: 0;">Relevant Occupation</label>
                    <input type="checkbox" name="relevant_occupation_hidden[${index}]" value="1" style="margin-left: 10px;">
                </div>
            </div>
        </div>
    `;
    
    container.insertAdjacentHTML('beforeend', newOccupationHTML);
    
    // Initialize autocomplete for nominated occupation
    initializeOccupationAutocomplete();
    
    // Initialize expiry date calculation for the new row (native date inputs)
    const newRow = container.lastElementChild;
    const assessmentDateInput = newRow.querySelector('.dates');
    if (assessmentDateInput) {
        // Add event listener for native date input
        assessmentDateInput.addEventListener('change', function() {
            console.log('New row date change event triggered:', this.value);
            handleExpiryDateCalculation(this);
        });
    }
}

function removeOccupationField(button) {
    const section = button.closest('.repeatable-section');
    const confirmDelete = confirm('Are you sure you want to delete this occupation record?');
    
    if (confirmDelete) {
        section.remove();
    }
}

async function saveOccupationInfo() {
    const form = getClientEditForm();
    if (!form) {
        showNotification('Form not found', 'error');
        return;
    }
    
    const formData = new FormData(form);
    formData.append('section', 'occupation');
    
    // Convert date formats from YYYY-MM-DD (native date input) to DD/MM/YYYY (backend format)
    // Get all date input fields and convert them
    const assessmentDateInputs = form.querySelectorAll('input[name^="dates["]');
    const expiryDateInputs = form.querySelectorAll('input[name^="expiry_dates["]');
    
    console.log('Found assessment date inputs:', assessmentDateInputs.length);
    console.log('Found expiry date inputs:', expiryDateInputs.length);
    
    // Clear existing date fields from FormData
    for (let [key, value] of formData.entries()) {
        if (key.startsWith('dates[') || key.startsWith('expiry_dates[')) {
            formData.delete(key);
        }
    }
    
    // Add converted assessment dates
    assessmentDateInputs.forEach((input, index) => {
        const originalValue = input.value;
        const convertedDate = convertDateForBackend(originalValue);
        formData.append(`dates[${index}]`, convertedDate);
        console.log(`Converted assessment date ${index}: ${originalValue} -> ${convertedDate}`);
    });
    
    // Add converted expiry dates
    expiryDateInputs.forEach((input, index) => {
        const originalValue = input.value;
        const convertedDate = convertDateForBackend(originalValue);
        formData.append(`expiry_dates[${index}]`, convertedDate);
        console.log(`Converted expiry date ${index}: ${originalValue} -> ${convertedDate}`);
    });
    
    // Debug: Log all form data being sent
    console.log('Form data being sent:');
    for (let [key, value] of formData.entries()) {
        console.log(`${key}: ${value}`);
    }
    
    try {
        const response = await fetch('/clients/save-section', {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification('Occupation information saved successfully!', 'success');
            toggleEditMode('occupationInfo');
            // Refresh the page to show updated data
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            showNotification(data.message || 'Error saving occupation information', 'error');
        }
    } catch (error) {
        console.error('Error saving occupation info:', error);
        showNotification('Error saving occupation information', 'error');
    }
}

// ===== ENGLISH TEST SCORES FUNCTIONS =====

function addTestScore() {
    // Check if we're in summary mode, if so switch to edit mode first
    const summaryView = document.getElementById('testScoreInfoSummary');
    const editView = document.getElementById('testScoreInfoEdit');
    
    if (summaryView && editView && summaryView.style.display !== 'none') {
        toggleEditMode('testScoreInfo');
        // Wait a bit for the edit view to be displayed, then add the test score
        setTimeout(() => {
            addTestScoreRow();
        }, 100);
        return;
    }
    
    addTestScoreRow();
}

function addTestScoreRow() {
    const container = document.getElementById('testScoresContainer');
    if (!container) {
        console.error('Test scores container not found');
        return;
    }
    
    const index = container.children.length;
    
    const newTestScoreHTML = `
        <div class="repeatable-section">
            <button type="button" class="remove-item-btn" title="Remove Test" onclick="removeTestScoreField(this)">${crmI('fas fa-trash')}</button>
            <div class="content-grid" style="grid-template-columns: repeat(auto-fit, minmax(100px, 1fr)); gap: 10px;">
                <div class="form-group">
                    <label>Test Type</label>
                    <select name="test_type_hidden[${index}]" class="test-type-selector" onchange="updateTestScoreValidation(this, ${index})">
                        <option value="">Select Test Type</option>
                        <option value="IELTS">IELTS</option>
                        <option value="IELTS_A">IELTS Academic</option>
                        <option value="PTE">PTE</option>
                        <option value="TOEFL">TOEFL</option>
                        <option value="CAE">CAE</option>
                        <option value="OET">OET</option>
                        <option value="CELPIP">CELPIP General</option>
                        <option value="MET">Michigan English Test (MET)</option>
                        <option value="LANGUAGECERT">LANGUAGECERT Academic</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Listening</label>
                    <input type="text" name="listening[${index}]" class="listening" placeholder="Score" maxlength="5">
                </div>
                <div class="form-group">
                    <label>Reading</label>
                    <input type="text" name="reading[${index}]" class="reading" placeholder="Score" maxlength="5">
                </div>
                <div class="form-group">
                    <label>Writing</label>
                    <input type="text" name="writing[${index}]" class="writing" placeholder="Score" maxlength="5">
                </div>
                <div class="form-group">
                    <label>Speaking</label>
                    <input type="text" name="speaking[${index}]" class="speaking" placeholder="Score" maxlength="5">
                </div>
                <div class="form-group">
                    <label>Overall</label>
                    <input type="text" name="overall_score[${index}]" class="overall_score" placeholder="Overall" maxlength="5">
                </div>
                <div class="form-group">
                    <label>Test Date</label>
                    <input type="text" name="test_date[${index}]" class="test_date date-picker" placeholder="dd/mm/yyyy">
                </div>
                <div class="form-group">
                    <label>Reference No</label>
                    <input type="text" name="test_reference_no[${index}]" placeholder="Reference No.">
                </div>
                <div class="form-group" style="align-items: center;">
                    <label style="margin-bottom: 0;">Relevant Test</label>
                    <input type="checkbox" name="relevant_test_hidden[${index}]" value="1" style="margin-left: 10px;">
                </div>
            </div>
        </div>
    `;
    
    container.insertAdjacentHTML('beforeend', newTestScoreHTML);
    
    // Initialize date pickers for the new row
    initializeDatepickers();
}

function removeTestScoreField(button) {
    const section = button.closest('.repeatable-section');
    const confirmDelete = confirm('Are you sure you want to delete this test score record?');
    
    if (confirmDelete) {
        section.remove();
    }
}

async function saveTestScoreInfo() {
    const form = getClientEditForm();
    if (!form) {
        showNotification('Form not found', 'error');
        return;
    }
    
    const formData = new FormData(form);
    formData.append('section', 'test_scores');
    
    try {
        const response = await fetch('/clients/save-section', {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification('Test score information saved successfully!', 'success');
            toggleEditMode('testScoreInfo');
            // Refresh the page to show updated data
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            showNotification(data.message || 'Error saving test score information', 'error');
        }
    } catch (error) {
        console.error('Error saving test score info:', error);
        showNotification('Error saving test score information', 'error');
    }
}

// ===== TEST SCORE VALIDATION FUNCTIONS =====

function updateTestScoreValidation(selectElement, index) {
    const testType = selectElement.value;
    const container = selectElement.closest('.repeatable-section');
    
    if (!container) return;
    
    const listeningInput = container.querySelector('.listening');
    const readingInput = container.querySelector('.reading');
    const writingInput = container.querySelector('.writing');
    const speakingInput = container.querySelector('.speaking');
    const overallInput = container.querySelector('.overall_score');
    
    // Clear existing validation messages
    [listeningInput, readingInput, writingInput, speakingInput, overallInput].forEach(input => {
        if (input) {
            input.style.borderColor = '';
            const existingError = input.parentNode.querySelector('.validation-error');
            if (existingError) {
                existingError.remove();
            }
        }
    });
    
    if (!testType) return;
    
    // Set validation based on test type
    switch (testType) {
        case 'IELTS':
        case 'IELTS_A':
            // IELTS: 0-9 for each component, overall 0-9
            setValidationMessages(container, 'IELTS scores range from 0-9', '0-9');
            break;
        case 'PTE':
            // PTE: 10-90 for each component, overall 10-90
            setValidationMessages(container, 'PTE scores range from 10-90', '10-90');
            break;
        case 'TOEFL':
            // TOEFL: 0-30 for each component, overall 0-120
            setValidationMessages(container, 'TOEFL scores: components 0-30, overall 0-120', '0-30');
            break;
        case 'CAE':
            // CAE: A, B, C, D, E, F for each component, overall A-F
            setValidationMessages(container, 'CAE grades: A, B, C, D, E, F', 'A-F');
            break;
        case 'OET':
            // OET: A, B, C, D, E for each component, overall A-E
            setValidationMessages(container, 'OET grades: A, B, C, D, E', 'A-E');
            break;
        case 'CELPIP':
            // CELPIP: 1-12 for each component, overall 1-12
            setValidationMessages(container, 'CELPIP scores range from 1-12', '1-12');
            break;
        case 'MET':
            // MET: 0-100 for each component, overall 0-100
            setValidationMessages(container, 'MET scores range from 0-100', '0-100');
            break;
        case 'LANGUAGECERT':
            // LANGUAGECERT: 0-100 for each component, overall 0-100
            setValidationMessages(container, 'LANGUAGECERT scores range from 0-100', '0-100');
            break;
    }
}

function setValidationMessages(container, message, range) {
    const inputs = container.querySelectorAll('.listening, .reading, .writing, .speaking, .overall_score');
    
    inputs.forEach(input => {
        // Add validation message
        const existingMsg = input.parentNode.querySelector('.validation-error');
        if (existingMsg) {
            existingMsg.remove();
        }
        
        const msgDiv = document.createElement('div');
        msgDiv.className = 'validation-error';
        msgDiv.style.fontSize = '11px';
        msgDiv.style.color = '#6c757d';
        msgDiv.style.marginTop = '2px';
        msgDiv.textContent = `${message}`;
        input.parentNode.appendChild(msgDiv);
        
        // Add input event listener for real-time validation
        input.addEventListener('input', function() {
            validateTestScoreInput(this, range);
        });
    });
}

function validateTestScoreInput(input, range) {
    const value = input.value.trim();
    
    if (!value) {
        input.style.borderColor = '';
        return true;
    }
    
    let isValid = false;
    
    switch (range) {
        case '0-9':
            isValid = /^[0-9](\.\d)?$/.test(value) && parseFloat(value) >= 0 && parseFloat(value) <= 9;
            break;
        case '10-90':
            isValid = /^\d{1,2}$/.test(value) && parseInt(value) >= 10 && parseInt(value) <= 90;
            break;
        case '0-30':
            isValid = /^\d{1,2}$/.test(value) && parseInt(value) >= 0 && parseInt(value) <= 30;
            break;
        case 'A-F':
        case 'A-E':
            isValid = /^[A-F]$/.test(value);
            break;
    }
    
    if (isValid) {
        input.style.borderColor = '#28a745';
    } else {
        input.style.borderColor = '#dc3545';
    }
    
    return isValid;
}

// ===== OCCUPATION AUTOCOMPLETE FUNCTIONS =====

// ANZSCO Occupation Autocomplete
let occupationAutocompleteTimeout;

function initializeOccupationAutocomplete() {
    const occupationInputs = document.querySelectorAll('.nomi_occupation');
    const codeInputs = document.querySelectorAll('.occupation_code');
    
    // Initialize autocomplete for occupation name fields
    occupationInputs.forEach(input => {
        if (input.dataset.autocompleteInitialized) return;
        
        input.addEventListener('input', function() {
            const query = this.value;
            const autocompleteContainer = this.nextElementSibling;
            const row = this.closest('.repeatable-section') || this.closest('.content-grid');
            
            if (query.length < 2) {
                autocompleteContainer.innerHTML = '';
                autocompleteContainer.style.display = 'none';
                return;
            }
            
            // Clear previous timeout
            clearTimeout(occupationAutocompleteTimeout);
            
            // Debounce API call
            occupationAutocompleteTimeout = setTimeout(() => {
                searchOccupations(query, autocompleteContainer, row, 'name');
            }, 300);
        });
        
        // Close autocomplete on outside click
        document.addEventListener('click', function(e) {
            if (!input.contains(e.target)) {
                const container = input.nextElementSibling;
                if (container && container.classList.contains('autocomplete-items')) {
                    container.innerHTML = '';
                    container.style.display = 'none';
                }
            }
        });
        
        input.dataset.autocompleteInitialized = 'true';
    });
    
    // Initialize autocomplete for occupation code fields
    codeInputs.forEach(input => {
        if (input.dataset.autocompleteInitialized) return;
        
        input.addEventListener('input', function() {
            const query = this.value;
            const row = this.closest('.repeatable-section') || this.closest('.content-grid');
            
            // Search by code if it's numeric and at least 3 digits
            if (query.length >= 3 && /^\d+$/.test(query)) {
                clearTimeout(occupationAutocompleteTimeout);
                occupationAutocompleteTimeout = setTimeout(() => {
                    searchOccupationByCode(query, row);
                }, 300);
            }
        });
        
        input.dataset.autocompleteInitialized = 'true';
    });
}

// Search occupations via API
async function searchOccupations(query, autocompleteContainer, row, searchType) {
    try {
        // Show loading indicator
        autocompleteContainer.innerHTML = '<div class="autocomplete-item"><span class="anzsco-loading"></span> Searching...</div>';
        autocompleteContainer.style.display = 'block';
        
        const response = await fetch(`/anzsco/search?q=${encodeURIComponent(query)}`, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            credentials: 'same-origin'
        });
        
        if (!response.ok) {
            const errorText = await response.text();
            console.error('API response error:', {
                status: response.status,
                statusText: response.statusText,
                body: errorText
            });
            throw new Error(`Search failed: ${response.status} ${response.statusText}`);
        }
        
        const responseText = await response.text();
        console.log('API response:', responseText); // Debug log
        
        let occupations;
        try {
            occupations = JSON.parse(responseText);
        } catch (parseError) {
            console.error('JSON parse error:', parseError);
            console.error('Response text:', responseText);
            throw new Error('Invalid JSON response from server');
        }
        
        if (occupations.length === 0) {
            autocompleteContainer.innerHTML = '<div class="autocomplete-item text-muted">No occupations found</div>';
            return;
        }
        
        // Build autocomplete items
        autocompleteContainer.innerHTML = '';
        occupations.forEach(occ => {
            const item = document.createElement('div');
            item.className = 'autocomplete-item anzsco-autocomplete-item';
            
            // Build lists badges
            let listBadges = '';
            if (occ.lists && occ.lists.length > 0) {
                occ.lists.forEach(list => {
                    const badgeClass = {
                        'MLTSSL': 'success',
                        'STSOL': 'info',
                        'ROL': 'warning',
                        'CSOL': 'secondary'
                    }[list] || 'secondary';
                    listBadges += `<span class="badge badge-${badgeClass} mr-1">${list}</span>`;
                });
            }
            
            item.innerHTML = `
                <div>
                    <span class="anzsco-code">${occ.anzsco_code}</span> - 
                    <span class="anzsco-title">${occ.occupation_title}</span>
                </div>
                <div class="anzsco-lists">${listBadges}</div>
                ${occ.assessing_authority ? `<div class="anzsco-authority"><small>Authority: ${occ.assessing_authority}</small></div>` : ''}
            `;
            
            item.addEventListener('click', function() {
                fillOccupationData(row, occ);
                autocompleteContainer.innerHTML = '';
                autocompleteContainer.style.display = 'none';
            });
            
            autocompleteContainer.appendChild(item);
        });
        
    } catch (error) {
        console.error('Occupation search error:', error);
        console.error('Error details:', {
            message: error.message,
            stack: error.stack,
            query: query
        });
        autocompleteContainer.innerHTML = '<div class="autocomplete-item text-danger">Error searching occupations. Please try again.</div>';
    }
}

// Search occupation by code
async function searchOccupationByCode(code, row) {
    try {
        const response = await fetch(`/anzsco/code/${code}`, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            credentials: 'same-origin'
        });
        
        if (!response.ok) {
            return; // Code not found, user can still enter manually
        }
        
        const result = await response.json();
        
        if (result.success && result.data) {
            // Ask user if they want to autofill
            const shouldFill = confirm(`Found ANZSCO occupation: ${result.data.occupation_title}\n\nAutofill occupation details?`);
            if (shouldFill) {
                fillOccupationData(row, result.data);
            }
        }
        
    } catch (error) {
        console.error('Occupation code search error:', error);
    }
}

// Fill occupation data into form fields
function fillOccupationData(row, occupationData) {
    if (!row) return;
    
    // Fill occupation name
    const nameInput = row.querySelector('.nomi_occupation');
    if (nameInput) {
        nameInput.value = occupationData.occupation_title;
        nameInput.classList.add('from-database');
        nameInput.dataset.anzscoId = occupationData.id;
    }
    
    // Fill occupation code
    const codeInput = row.querySelector('.occupation_code');
    if (codeInput) {
        codeInput.value = occupationData.anzsco_code;
        codeInput.classList.add('from-database');
    }
    
    // Fill assessing authority (into the "list" field)
    const listInput = row.querySelector('.list');
    if (listInput && occupationData.assessing_authority) {
        listInput.value = occupationData.assessing_authority;
        listInput.classList.add('from-database');
        // Store validity years for expiry date calculation
        listInput.dataset.validityYears = occupationData.assessment_validity_years || 3;
    }
    
    // Store ANZSCO occupation ID
    const anzscoIdInput = row.querySelector('.anzsco_occupation_id');
    if (anzscoIdInput) {
        anzscoIdInput.value = occupationData.id;
    }
    
    // Display occupation lists
    displayOccupationLists(occupationData, row);
    
    // Calculate and fill expiry date if assessment date exists
    const assessmentDateInput = row.querySelector('.dates');
    const expiryDateInput = row.querySelector('.expiry_dates');
    
    if (assessmentDateInput && expiryDateInput && assessmentDateInput.value) {
        const validityYears = occupationData.assessment_validity_years || 3;
        const expiryDate = calculateExpiryDate(assessmentDateInput.value, validityYears);
        if (expiryDate) {
            // Convert dd/mm/yyyy to YYYY-MM-DD for HTML date input
            const [day, month, year] = expiryDate.split('/');
            const htmlDateFormat = `${year}-${month.padStart(2, '0')}-${day.padStart(2, '0')}`;
            
            expiryDateInput.value = htmlDateFormat;
            expiryDateInput.classList.add('from-database');
        }
    }
    
    // Show notification
    showNotification(`Occupation filled from ANZSCO database: ${occupationData.occupation_title}`, 'success');
}

// Display occupation lists as badges
function displayOccupationLists(occupationData, row) {
    const listsContainer = row.querySelector('.occupation-lists-display');
    if (!listsContainer) return;
    
    const lists = occupationData.lists || [];
    
    if (lists.length === 0) {
        listsContainer.innerHTML = '<span class="text-muted">No lists available</span>';
        return;
    }
    
    const badges = lists.map(list => {
        const color = getListBadgeColor(list);
        return `<span class="badge badge-${color} mr-1">${list}</span>`;
    }).join('');
    
    listsContainer.innerHTML = badges;
}

// Get badge color for occupation list
function getListBadgeColor(list) {
    const colors = {
        'MLTSSL': 'success',
        'STSOL': 'info', 
        'ROL': 'warning',
        'CSOL': 'secondary'
    };
    return colors[list] || 'secondary';
}

// Calculate expiry date
function calculateExpiryDate(assessmentDateValue, validityYears) {
    try {
        if (!assessmentDateValue) return null;
        
        // assessmentDateValue is in YYYY-MM-DD format from HTML date input
        const assessmentDate = new Date(assessmentDateValue);
        if (isNaN(assessmentDate.getTime())) return null;
        
        // Add validity years
        const expiryDate = new Date(assessmentDate);
        expiryDate.setFullYear(expiryDate.getFullYear() + validityYears);
        
        // Return in dd/mm/yyyy format as expected by PHP backend
        const day = String(expiryDate.getDate()).padStart(2, '0');
        const month = String(expiryDate.getMonth() + 1).padStart(2, '0');
        const year = expiryDate.getFullYear();
        
        return `${day}/${month}/${year}`;
    } catch (error) {
        console.error('Error calculating expiry date:', error);
        return null;
    }
}

// Convert date from YYYY-MM-DD (native date input) to DD/MM/YYYY (backend format)
function convertDateForBackend(dateValue) {
    console.log('convertDateForBackend called with:', dateValue);
    
    if (!dateValue) {
        console.log('No date value provided, returning empty string');
        return '';
    }
    
    try {
        // Parse YYYY-MM-DD format
        const date = new Date(dateValue);
        if (isNaN(date.getTime())) {
            console.log('Invalid date, returning original value:', dateValue);
            return dateValue; // Return original if invalid
        }
        
        // Format as DD/MM/YYYY
        const day = String(date.getDate()).padStart(2, '0');
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const year = date.getFullYear();
        const converted = `${day}/${month}/${year}`;
        
        console.log('Date conversion successful:', dateValue, '->', converted);
        return converted;
    } catch (error) {
        console.error('Error converting date for backend:', error);
        return dateValue; // Return original if conversion fails
    }
}

// Assessment authority validity periods (in years)
const ASSESSMENT_AUTHORITY_VALIDITY = {
    'ACS': 2,           // Australian Computer Society
    'TRA': 3,           // Trades Recognition Australia
    'VETASSESS': 3,     // Vocational Education and Training Assessment Services
    'AITSL': 2,         // Australian Institute for Teaching and School Leadership
    'AACA': 3,          // Architects Accreditation Council of Australia
    'ANMAC': 2,         // Australian Nursing and Midwifery Accreditation Council
    'APC': 3,           // Australian Pharmacy Council
    'CCEA': 3,          // Council on Chiropractic Education Australasia
    'CPAA': 3,          // CPA Australia
    'IPA': 3,           // Institute of Public Accountants
    'CAANZ': 3,         // Chartered Accountants Australia and New Zealand
    'Engineers Australia': 3,
    'IML': 3,           // Institute of Managers and Leaders
    'AASW': 3,          // Australian Association of Social Workers
    'ANZPAC': 3,        // Australian and New Zealand Podiatry Accreditation Council
    'OCANZ': 3,         // Optometry Council of Australia and New Zealand
    'ADC': 3,           // Australian Dental Council
    'AMC': 3,           // Australian Medical Council
    'APEC': 3,          // Australian Psychology Accreditation Council
    'AVBC': 3,          // Australasian Veterinary Boards Council
    'CPSA': 3,          // Council on Chiropractic Education Australasia
    'DAA': 3,           // Dietitians Association of Australia
    'MRCB': 3,          // Medical Radiation Practice Board of Australia
    'NATSIHWA': 3,      // National Aboriginal and Torres Strait Islander Health Workers Association
    'OTBA': 3,          // Occupational Therapy Board of Australia
    'PBA': 3,           // Psychology Board of Australia
    'PBA': 3,           // Physiotherapy Board of Australia
    'PSBA': 3,          // Podiatry Board of Australia
    'SBA': 3,           // Speech Pathology Australia
    'Default': 3        // Default validity period
};

// Function to handle expiry date calculation
function handleExpiryDateCalculation(assessmentDateInput) {
    console.log('handleExpiryDateCalculation called', {
        value: assessmentDateInput.value,
        element: assessmentDateInput
    });
    
    const row = assessmentDateInput.closest('.repeatable-section') || assessmentDateInput.closest('.content-grid');
    if (!row) {
        console.log('No row found for assessment date input');
        return;
    }
    
    const listInput = row.querySelector('.list');
    const expiryDateInput = row.querySelector('.expiry_dates');
    
    console.log('Found elements:', {
        listInput: listInput,
        expiryDateInput: expiryDateInput,
        listInputHasDatabaseClass: listInput ? listInput.classList.contains('from-database') : false,
        listInputValue: listInput ? listInput.value : null
    });
    
    // Always calculate expiry date when assessment date is entered
    if (assessmentDateInput.value && expiryDateInput) {
        let validityYears = 3; // Default validity period
        
        // If field was filled from database, use the specific validity period
        if (listInput && listInput.classList.contains('from-database')) {
            validityYears = parseInt(listInput.dataset.validityYears) || 3;
            console.log('Using database validity years:', validityYears);
        } else if (listInput && listInput.value) {
            // Use validity period based on assessment authority
            const authority = listInput.value.trim().toUpperCase();
            validityYears = ASSESSMENT_AUTHORITY_VALIDITY[authority] || ASSESSMENT_AUTHORITY_VALIDITY['Default'];
            console.log('Using assessment authority validity years:', validityYears, 'for authority:', authority);
        } else {
            console.log('Using default validity years:', validityYears);
        }
        
        const expiryDate = calculateExpiryDate(assessmentDateInput.value, validityYears);
        console.log('Calculated expiry date:', expiryDate);
        
        if (expiryDate) {
            // Convert dd/mm/yyyy to YYYY-MM-DD for HTML date input
            const [day, month, year] = expiryDate.split('/');
            const htmlDateFormat = `${year}-${month.padStart(2, '0')}-${day.padStart(2, '0')}`;
            
            expiryDateInput.value = htmlDateFormat;
            expiryDateInput.classList.add('from-database');
            console.log('Expiry date set successfully:', htmlDateFormat);
        }
    }
}

// Update expiry date when assessment date changes (native date inputs)
document.addEventListener('DOMContentLoaded', function() {
    // Handle change events for native date inputs
    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('dates')) {
            console.log('Date change event triggered:', e.target.value);
            handleExpiryDateCalculation(e.target);
        }
    });
});

// ===== INITIALIZATION FUNCTIONS =====

// Initialize occupation autocomplete and test score validation on page load
document.addEventListener('DOMContentLoaded', function() {
    // Initialize occupation autocomplete for existing fields
    initializeOccupationAutocomplete();
    
    // Initialize test score validation for existing fields
    const existingTestSelectors = document.querySelectorAll('.test-type-selector');
    existingTestSelectors.forEach((selector, index) => {
        if (selector.value) {
            updateTestScoreValidation(selector, index);
        }
    });
    
    // Related Files dropdown (mmSelect + AJAX)
    initializeRelatedFilesMmSelect();
    
    // Fallback if mmSelect did not attach
    setTimeout(function() {
        const relatedFilesSelect = $('#relatedFiles');
        if (relatedFilesSelect.length > 0 && !relatedFilesSelect.hasClass('mm-select-initialized')) {
            console.log('mmSelect not initialized, retrying...');
            initializeRelatedFilesMmSelect();
        }
    }, 1000);
    
    // Another fallback after longer delay
    setTimeout(function() {
        const relatedFilesSelect = $('#relatedFiles');
        if (relatedFilesSelect.length > 0 && !relatedFilesSelect.hasClass('mm-select-initialized')) {
            console.log('mmSelect still not initialized, final retry...');
            initializeRelatedFilesMmSelect();
        }
    }, 3000);
});

/**
 * Related Files dropdown: mmSelect with AJAX search
 */
function initializeRelatedFilesMmSelect() {
    const relatedFilesSelect = $('#relatedFiles');

    if (relatedFilesSelect.length === 0) return;
    
    if (relatedFilesSelect.length > 0 && typeof $.fn.mmSelect !== 'undefined') {
        console.log('Initializing Related Files mmSelect...');
        
        relatedFilesSelect.mmSelect({
            multiple: true,
            closeOnSelect: false,
            placeholder: 'Search for clients by name or client ID',
            allowClear: true,
            minimumInputLength: 2,
            ajax: {
                url: window.editClientConfig?.searchPartnerRoute || '/clients/search-partner',
                type: 'POST',
                dataType: 'json',
                delay: 250,
                headers: {
                    'X-CSRF-TOKEN': window.editClientConfig?.csrfToken || $('meta[name="csrf-token"]').attr('content')
                },
                data: function (params) {
                    console.log('Searching for:', params.term);
                    return {
                        query: params.term,
                        exclude_client: window.currentClientId || null // Exclude current client
                    };
                },
                processResults: function (data) {
                    console.log('Search results received:', data);
                    
                    // Log debug information if available
                    if (data.debug) {
                        console.log('Debug info:', data.debug);
                        console.log('Total clients in DB:', data.debug.total_clients);
                        console.log('Filtered results:', data.debug.filtered_count);
                    }
                    
                    // Log error if present
                    if (data.error) {
                        console.error('Search error from server:', data.error);
                    }
                    
                    // Transform API rows for template
                    if (data.partners && Array.isArray(data.partners)) {
                        const results = data.partners.map(function(partner) {
                            return {
                                id: partner.id,
                                text: partner.first_name + ' ' + partner.last_name + ' (' + (partner.client_id || 'No ID') + ')',
                                email: partner.email,
                                phone: partner.phone,
                                client_id: partner.client_id
                            };
                        });
                        
                        console.log('Processed results:', results);
                        return { results: results };
                    }
                    
                    console.log('No results found');
                    return { results: [] };
                },
                error: function(xhr, status, error) {
                    console.error('Search error:', error, xhr.responseText);
                    console.error('Status:', status);
                    console.error('Response:', xhr.responseText);
                    return { results: [] };
                },
                cache: true
            },
            templateResult: formatRelatedFileResult,
            templateSelection: formatRelatedFileSelection
        });
        
        // Debug: dropdown open
        relatedFilesSelect.on('mmselect:open', function() {
            console.log('Related Files mmSelect dropdown opened');
        });
        
        relatedFilesSelect.on('mmselect:select', function(e) {
            console.log('Item selected:', e.params.data);
        });
        
        // Add test function to window for debugging
        window.testRelatedFilesSearch = function(query) {
            console.log('Testing search with query:', query);
            $.ajax({
                url: window.editClientConfig?.searchPartnerRoute || '/clients/search-partner',
                type: 'POST',
                dataType: 'json',
                headers: {
                    'X-CSRF-TOKEN': window.editClientConfig?.csrfToken || $('meta[name="csrf-token"]').attr('content')
                },
                data: {
                    query: query || 'test',
                    exclude_client: window.currentClientId || null
                },
                success: function(data) {
                    console.log('Test search success:', data);
                },
                error: function(xhr, status, error) {
                    console.error('Test search error:', error, xhr.responseText);
                }
            });
        };
    } else {
        console.error('Related Files mmSelect initialization failed:', {
            elementExists: relatedFilesSelect.length > 0,
            mmSelectAvailable: typeof $.fn.mmSelect !== 'undefined'
        });
    }
}

// Re-run Related Files mmSelect when edit mode is toggled
window.reinitializeRelatedFilesMmSelect = function() {
    console.log('Reinitializing Related Files mmSelect...');
    const relatedFilesSelect = $('#relatedFiles');
    if (relatedFilesSelect.length > 0) {
        // Destroy existing mmSelect if present
        if (relatedFilesSelect.hasClass('mm-select-initialized')) {
            relatedFilesSelect.mmSelect('destroy');
        }
        // Reinitialize
        setTimeout(function() {
            initializeRelatedFilesMmSelect();
        }, 100);
    }
};

/**
 * Format the display of search results in the dropdown
 */
function formatRelatedFileResult(partner) {
    if (partner.loading) {
        return partner.text;
    }

    var $container = $(
        '<div class="mm-result-partner" style="padding: 8px;">' +
        '<div class="mm-result-partner__title" style="font-weight: 600; color: #333; font-size: 14px;"></div>' +
        '</div>'
    );

    // Show only name and client ID
    $container.find('.mm-result-partner__title').text(partner.text);

    return $container;
}

/**
 * Format the display of selected items
 */
function formatRelatedFileSelection(partner) {
    return partner.text || partner.id;
}

// English proficiency functions moved to separate file: english-proficiency.js

/**
 * Save Parents Information
 */
async function saveParentsInfo() {
    const parentsData = [];
    const container = document.getElementById('parentContainer');
    
    if (!container) {
        console.error('Parent container not found');
        showNotification('Parent container not found', 'error');
        return;
    }

    const parentRows = container.querySelectorAll('.repeatable-section');
    console.log('Processing', parentRows.length, 'parent rows');

    for (let i = 0; i < parentRows.length; i++) {
        const row = parentRows[i];
        const parentId = row.querySelector('input[name*="_id"]')?.value || '';
        const details = row.querySelector('input[name*="_details"]')?.value || '';
        const relationshipType = row.querySelector('select[name*="_relationship_type"]')?.value || '';
        const gender = row.querySelector('select[name*="_gender"]')?.value || '';
        const companyType = row.querySelector('select[name*="_company_type"]')?.value || '';
        const email = row.querySelector('input[name*="_email"]')?.value || '';
        const firstName = row.querySelector('input[name*="_first_name"]')?.value || '';
        const lastName = row.querySelector('input[name*="_last_name"]')?.value || '';
        const phone = row.querySelector('input[name*="_phone"]')?.value || '';
        const dob = row.querySelector('input[name*="_dob"]')?.value || '';

        // Validation
        const validationErrors = [];
        if (!relationshipType) validationErrors.push('Relationship Type');
        if (!gender) validationErrors.push('Gender');
        if (!companyType) validationErrors.push('Company Type');
        
        // Conditional validation based on details field
        if (!details || details.trim() === '') {
            if (!lastName || lastName.trim() === '') validationErrors.push('Last Name');
            if (!dob || dob.trim() === '') validationErrors.push('DOB');
        }

        if (validationErrors.length > 0) {
            const uniqueErrors = [...new Set(validationErrors)];
            const errorMessage = `Please fill in the following required fields:\n• ${uniqueErrors.join('\n• ')}`;
            showNotification(errorMessage, 'error');
            return;
        }

        parentsData.push({
            parent_id: (parentId && parentId !== '0') ? parentId : '',
            details: details,
            relationship_type: relationshipType,
            gender: gender,
            company_type: companyType,
            email: email,
            first_name: firstName,
            last_name: lastName,
            phone: phone,
            dob: dob
        });
    }

    try {
        const response = await fetch('/clients/save-section', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({
                section: 'parentsInfo',
                id: window.currentClientId,
                parents: parentsData
            })
        });

        const result = await response.json();
        
        if (result.success) {
            showNotification('Parents information saved successfully!', 'success');
            cancelEdit('parentsInfo');
            location.reload(); // Reload to show updated data
        } else {
            showNotification(result.message || 'Failed to save parents information', 'error');
        }
    } catch (error) {
        console.error('Error saving parents info:', error);
        showNotification('An error occurred while saving parents information', 'error');
    }
}

/**
 * Save Siblings Information
 */
async function saveSiblingsInfo() {
    const siblingsData = [];
    const container = document.getElementById('siblingsContainer');
    
    if (!container) {
        console.error('Siblings container not found');
        showNotification('Siblings container not found', 'error');
        return;
    }

    const siblingRows = container.querySelectorAll('.repeatable-section');
    console.log('Processing', siblingRows.length, 'sibling rows');

    for (let i = 0; i < siblingRows.length; i++) {
        const row = siblingRows[i];
        const siblingId = row.querySelector('input[name*="_id"]')?.value || '';
        const details = row.querySelector('input[name*="_details"]')?.value || '';
        const relationshipType = row.querySelector('select[name*="_relationship_type"]')?.value || '';
        const gender = row.querySelector('select[name*="_gender"]')?.value || '';
        const companyType = row.querySelector('select[name*="_company_type"]')?.value || '';
        const email = row.querySelector('input[name*="_email"]')?.value || '';
        const firstName = row.querySelector('input[name*="_first_name"]')?.value || '';
        const lastName = row.querySelector('input[name*="_last_name"]')?.value || '';
        const phone = row.querySelector('input[name*="_phone"]')?.value || '';
        const dob = row.querySelector('input[name*="_dob"]')?.value || '';

        // Validation
        const validationErrors = [];
        if (!relationshipType) validationErrors.push('Relationship Type');
        if (!gender) validationErrors.push('Gender');
        if (!companyType) validationErrors.push('Company Type');
        
        // Conditional validation based on details field
        if (!details || details.trim() === '') {
            if (!lastName || lastName.trim() === '') validationErrors.push('Last Name');
            if (!dob || dob.trim() === '') validationErrors.push('DOB');
        }

        if (validationErrors.length > 0) {
            const uniqueErrors = [...new Set(validationErrors)];
            const errorMessage = `Please fill in the following required fields:\n• ${uniqueErrors.join('\n• ')}`;
            showNotification(errorMessage, 'error');
            return;
        }

        siblingsData.push({
            sibling_id: (siblingId && siblingId !== '0') ? siblingId : '',
            details: details,
            relationship_type: relationshipType,
            gender: gender,
            company_type: companyType,
            email: email,
            first_name: firstName,
            last_name: lastName,
            phone: phone,
            dob: dob
        });
    }

    try {
        const response = await fetch('/clients/save-section', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({
                section: 'siblingsInfo',
                id: window.currentClientId,
                siblings: siblingsData
            })
        });

        const result = await response.json();
        
        if (result.success) {
            showNotification('Siblings information saved successfully!', 'success');
            cancelEdit('siblingsInfo');
            location.reload(); // Reload to show updated data
        } else {
            showNotification(result.message || 'Failed to save siblings information', 'error');
        }
    } catch (error) {
        console.error('Error saving siblings info:', error);
        showNotification('An error occurred while saving siblings information', 'error');
    }
}

/**
 * Save Others Information
 */
async function saveOthersInfo() {
    const othersData = [];
    const container = document.getElementById('othersContainer');
    
    if (!container) {
        console.error('Others container not found');
        showNotification('Others container not found', 'error');
        return;
    }

    const otherRows = container.querySelectorAll('.repeatable-section');
    console.log('Processing', otherRows.length, 'other rows');

    for (let i = 0; i < otherRows.length; i++) {
        const row = otherRows[i];
        const otherId = row.querySelector('input[name*="_id"]')?.value || '';
        const details = row.querySelector('input[name*="_details"]')?.value || '';
        const relationshipType = row.querySelector('select[name*="_relationship_type"]')?.value || '';
        const gender = row.querySelector('select[name*="_gender"]')?.value || '';
        const companyType = row.querySelector('select[name*="_company_type"]')?.value || '';
        const email = row.querySelector('input[name*="_email"]')?.value || '';
        const firstName = row.querySelector('input[name*="_first_name"]')?.value || '';
        const lastName = row.querySelector('input[name*="_last_name"]')?.value || '';
        const phone = row.querySelector('input[name*="_phone"]')?.value || '';
        const dob = row.querySelector('input[name*="_dob"]')?.value || '';

        // Validation
        const validationErrors = [];
        if (!relationshipType) validationErrors.push('Relationship Type');
        if (!gender) validationErrors.push('Gender');
        if (!companyType) validationErrors.push('Company Type');
        
        // Conditional validation based on details field
        if (!details || details.trim() === '') {
            if (!lastName || lastName.trim() === '') validationErrors.push('Last Name');
            if (!dob || dob.trim() === '') validationErrors.push('DOB');
        }

        if (validationErrors.length > 0) {
            const uniqueErrors = [...new Set(validationErrors)];
            const errorMessage = `Please fill in the following required fields:\n• ${uniqueErrors.join('\n• ')}`;
            showNotification(errorMessage, 'error');
            return;
        }

        othersData.push({
            other_id: (otherId && otherId !== '0') ? otherId : '',
            details: details,
            relationship_type: relationshipType,
            gender: gender,
            company_type: companyType,
            email: email,
            first_name: firstName,
            last_name: lastName,
            phone: phone,
            dob: dob
        });
    }

    try {
        const response = await fetch('/clients/save-section', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({
                section: 'othersInfo',
                id: window.currentClientId,
                others: othersData
            })
        });

        const result = await response.json();
        
        if (result.success) {
            showNotification('Others information saved successfully!', 'success');
            cancelEdit('othersInfo');
            location.reload(); // Reload to show updated data
        } else {
            showNotification(result.message || 'Failed to save others information', 'error');
        }
    } catch (error) {
        console.error('Error saving others info:', error);
        showNotification('An error occurred while saving others information', 'error');
    }
}

/**
 * Check if phone number is a placeholder
 */
function isPlaceholderNumber(phone) {
    // Remove any non-digit characters
    const cleaned = phone.replace(/\D/g, '');
    
    // Check if it starts with 4444444444 (placeholder pattern)
    return cleaned.startsWith('4444444444');
}

/**
 * Validate phone number using standardized rules
 */
function validatePhoneNumber(phone) {
    if (!phone || phone.trim() === '') {
        return {
            valid: false,
            message: 'Phone number is required'
        };
    }

    // Remove any non-digit characters for validation
    const cleaned = phone.replace(/\D/g, '');

    // Check if it's a placeholder number (allow it)
    if (isPlaceholderNumber(cleaned)) {
        return {
            valid: true,
            message: 'Placeholder number detected',
            isPlaceholder: true
        };
    }

    // Check length — align with PhoneValidationHelper (7–15 national digits)
    if (cleaned.length < 7) {
        return {
            valid: false,
            message: 'Phone number must be at least 7 digits'
        };
    }

    if (cleaned.length > 15) {
        return {
            valid: false,
            message: 'Phone number must not exceed 15 digits'
        };
    }

    // Check if it contains only digits
    const phoneRegex = /^[0-9]{7,15}$/;
    if (!phoneRegex.test(cleaned)) {
        return {
            valid: false,
            message: 'Phone number must contain only digits'
        };
    }

    return {
        valid: true,
        message: 'Valid phone number',
        isPlaceholder: false
    };
}

/**
 * Client/Company form details verification (sidebar Verify button)
 */
function confirmVerifyClientDetails() {
    const modal = document.getElementById('confirmVerifyDetailsModal');
    if (!modal) {
        if (confirm('If you want to verify these details. Please click OK, else Cancel.')) {
            submitVerifyClientDetails();
        }
        return;
    }
    modal.style.display = 'flex';
    modal.setAttribute('aria-hidden', 'false');
}

function closeVerifyDetailsModal() {
    const modal = document.getElementById('confirmVerifyDetailsModal');
    if (!modal) {
        return;
    }
    modal.style.display = 'none';
    modal.setAttribute('aria-hidden', 'true');
}

function submitVerifyClientDetails() {
    const wrap = document.getElementById('sidebarDetailsVerify');
    if (!wrap) {
        return;
    }

    const clientId = wrap.getAttribute('data-client-id');
    const url = wrap.getAttribute('data-verify-url');
    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    const csrf = (window.editClientConfig && window.editClientConfig.csrfToken)
        || (csrfMeta && csrfMeta.getAttribute('content'))
        || '';

    if (!clientId || !url) {
        alert('Unable to verify: missing client information.');
        return;
    }

    closeVerifyDetailsModal();

    if (typeof jQuery !== 'undefined') {
        jQuery('.popuploader').show();
    }

    const yesBtn = document.getElementById('confirmVerifyDetailsYesBtn');
    const verifyBtn = document.getElementById('btnVerifyDetails');
    if (yesBtn) yesBtn.disabled = true;
    if (verifyBtn) verifyBtn.disabled = true;

    fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrf,
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ client_id: parseInt(clientId, 10) })
    })
        .then(function (response) {
            return response.json().then(function (data) {
                return { ok: response.ok, data: data };
            });
        })
        .then(function (result) {
            if (typeof jQuery !== 'undefined') {
                jQuery('.popuploader').hide();
            }
            if (yesBtn) yesBtn.disabled = false;
            if (verifyBtn) verifyBtn.disabled = false;

            if (!result.ok || !result.data || result.data.status != 1) {
                alert((result.data && result.data.message) ? result.data.message : 'Verification failed. Please try again.');
                return;
            }

            const meta = document.getElementById('detailsVerifyMeta');
            const byEl = document.getElementById('detailsVerifiedByText');
            const atEl = document.getElementById('detailsVerifiedAtText');
            if (byEl) byEl.textContent = result.data.verified_by || '—';
            if (atEl) atEl.textContent = result.data.verified_at || '—';
            if (meta) meta.style.display = '';

            if (typeof showNotification === 'function') {
                showNotification(result.data.message || 'Details verified successfully.', 'success');
            }
        })
        .catch(function () {
            if (typeof jQuery !== 'undefined') {
                jQuery('.popuploader').hide();
            }
            if (yesBtn) yesBtn.disabled = false;
            if (verifyBtn) verifyBtn.disabled = false;
            alert('Verification failed. Please try again.');
        });
}

window.confirmVerifyClientDetails = confirmVerifyClientDetails;
window.closeVerifyDetailsModal = closeVerifyDetailsModal;
window.submitVerifyClientDetails = submitVerifyClientDetails;

