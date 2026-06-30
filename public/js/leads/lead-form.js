/**
 * Lead Form JavaScript Functions
 * 
 * Handles dynamic form fields for phone numbers, emails, addresses, and other repeatable sections
 */

/**
 * Add Phone Number Field
 */
function addPhoneNumber() {
    const container = document.getElementById('phoneNumbersContainer');
    if (!container) return;
    
    const index = container.children.length;

    const defaultDial = (typeof window.phoneDefaultDialCode !== 'undefined' && window.phoneDefaultDialCode)
        ? window.phoneDefaultDialCode
        : '+61';
    const dialOpts = typeof window.buildPhoneDialCodeOptionsHtml === 'function'
        ? window.buildPhoneDialCodeOptionsHtml(defaultDial, { showPlaceholder: false })
        : '<option value="+61" selected>+61 (Australia)</option>';

    const phoneNumberHTML = `
        <div class="repeatable-section" data-index="${index}">
            <button type="button" class="remove-item-btn" title="Remove Phone" onclick="removePhoneField(this)">
                ${typeof crmIconLegacy === 'function' ? crmIconLegacy('fas fa-trash') : '<i class="fas fa-trash"></i>'}
            </button>
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
                        <option value="Partner">Partner</option>
                        <option value="Others">Others</option>
                        <option value="Not In Use">Not In Use</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Country Code</label>
                    <select name="country_code[${index}]" class="country-code-selector">${dialOpts}</select>
                </div>
                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="text" name="phone[${index}]" class="phone-number-input" placeholder="Enter phone number" pattern="[0-9]*" inputmode="numeric" required>
                    <div class="phone-error phone-error-${index}" style="color: #dc3545; font-size: 12px; margin-top: 5px; display: none;"></div>
                </div>
            </div>
        </div>
    `;
    
    container.insertAdjacentHTML('beforeend', phoneNumberHTML);
}

/**
 * Remove Phone Number Field
 */
function removePhoneField(button) {
    const section = button.closest('.repeatable-section');
    if (section) {
        // Check if it's the last phone field
        const container = document.getElementById('phoneNumbersContainer');
        if (container && container.children.length > 1) {
            section.remove();
        } else {
            alert('At least one phone number is required.');
        }
    }
}

/**
 * Add Email Address Field
 */
function addEmailAddress() {
    const container = document.getElementById('emailAddressesContainer');
    if (!container) return;
    
    const index = container.children.length;
    
    const emailHTML = `
        <div class="repeatable-section" data-index="${index}">
            <button type="button" class="remove-item-btn" title="Remove Email" onclick="removeEmailField(this)">
                ${typeof crmIconLegacy === 'function' ? crmIconLegacy('fas fa-trash') : '<i class="fas fa-trash"></i>'}
            </button>
            <div class="content-grid">
                <div class="form-group">
                    <label>Type</label>
                    <select name="email_type_hidden[${index}]" class="email-type-selector">
                        <option value="Personal">Personal</option>
                        <option value="Work">Work</option>
                        <option value="Business">Business</option>
                        <option value="Secondary">Secondary</option>
                        <option value="Father">Father</option>
                        <option value="Mother">Mother</option>
                        <option value="Brother">Brother</option>
                        <option value="Sister">Sister</option>
                        <option value="Uncle">Uncle</option>
                        <option value="Aunt">Aunt</option>
                        <option value="Cousin">Cousin</option>
                        <option value="Partner">Partner</option>
                        <option value="Others">Others</option>
                        <option value="Not In Use">Not In Use</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email[${index}]" class="email-input" placeholder="Enter email address" required>
                    <div class="email-error email-error-${index}" style="color: #dc3545; font-size: 12px; margin-top: 5px; display: none;"></div>
                </div>
            </div>
        </div>
    `;
    
    container.insertAdjacentHTML('beforeend', emailHTML);
}

/**
 * Remove Email Address Field
 */
function removeEmailField(button) {
    const section = button.closest('.repeatable-section');
    if (section) {
        // Check if it's the last email field
        const container = document.getElementById('emailAddressesContainer');
        if (container && container.children.length > 1) {
            section.remove();
        } else {
            alert('At least one email address is required.');
        }
    }
}

/**
 * Add Passport Field
 */
function addPassport() {
    const container = document.getElementById('passportsContainer');
    if (!container) return;
    
    const index = container.children.length;
    
    const passportHTML = `
        <div class="repeatable-section" data-index="${index}">
            <button type="button" class="remove-item-btn" title="Remove Passport" onclick="removePassport(this)">
                ${typeof crmIconLegacy === 'function' ? crmIconLegacy('fas fa-trash') : '<i class="fas fa-trash"></i>'}
            </button>
            <div class="content-grid">
                <div class="form-group">
                    <label>Passport Number</label>
                    <input type="text" name="passport_number[${index}]" placeholder="Enter passport number">
                </div>
                <div class="form-group">
                    <label>Country</label>
                    <input type="text" name="passport_country[${index}]" placeholder="Issuing country">
                </div>
                <div class="form-group">
                    <label>Issue Date</label>
                    <input type="text" name="passport_issue_date[${index}]" class="date-picker" placeholder="dd/mm/yyyy">
                </div>
                <div class="form-group">
                    <label>Expiry Date</label>
                    <input type="text" name="passport_expiry_date[${index}]" class="date-picker" placeholder="dd/mm/yyyy">
                </div>
            </div>
        </div>
    `;
    
    container.insertAdjacentHTML('beforeend', passportHTML);
}

/**
 * Remove Passport Field
 */
function removePassport(button) {
    const section = button.closest('.repeatable-section');
    if (section) {
        section.remove();
    }
}

/**
 * Add Visa Field
 */
function addVisa() {
    const container = document.getElementById('visasContainer');
    if (!container) return;
    
    const index = container.children.length;
    
    const visaHTML = `
        <div class="repeatable-section" data-index="${index}">
            <button type="button" class="remove-item-btn" title="Remove Visa" onclick="removeVisa(this)">
                ${typeof crmIconLegacy === 'function' ? crmIconLegacy('fas fa-trash') : '<i class="fas fa-trash"></i>'}
            </button>
            <div class="content-grid">
                <div class="form-group">
                    <label>Visa Type</label>
                    <input type="text" name="visa_type[${index}]" placeholder="Enter visa type">
                </div>
                <div class="form-group">
                    <label>Visa Subclass</label>
                    <input type="text" name="visa_subclass[${index}]" placeholder="Enter subclass">
                </div>
                <div class="form-group">
                    <label>Grant Date</label>
                    <input type="text" name="visa_grant_date[${index}]" class="date-picker" placeholder="dd/mm/yyyy">
                </div>
                <div class="form-group">
                    <label>Expiry Date</label>
                    <input type="text" name="visa_expiry_date[${index}]" class="date-picker" placeholder="dd/mm/yyyy">
                </div>
            </div>
        </div>
    `;
    
    container.insertAdjacentHTML('beforeend', visaHTML);
}

/**
 * Remove Visa Field
 */
function removeVisa(button) {
    const section = button.closest('.repeatable-section');
    if (section) {
        section.remove();
    }
}

/**
 * Add Address Field
 */
function addAddress() {
    const container = document.getElementById('addressesContainer');
    if (!container) return;
    
    const index = container.children.length;
    
    const addressHTML = `
        <div class="repeatable-section" data-index="${index}">
            <button type="button" class="remove-item-btn" title="Remove Address" onclick="removeAddress(this)">
                ${typeof crmIconLegacy === 'function' ? crmIconLegacy('fas fa-trash') : '<i class="fas fa-trash"></i>'}
            </button>
            <div class="content-grid">
                <div class="form-group full-width">
                    <label>Address Type</label>
                    <select name="address_type[${index}]">
                        <option value="Residential">Residential</option>
                        <option value="Postal">Postal</option>
                        <option value="Business">Business</option>
                        <option value="Previous">Previous</option>
                    </select>
                </div>
                <div class="form-group full-width">
                    <label>Address</label>
                    <input type="text" name="address[${index}]" placeholder="Enter full address">
                </div>
                <div class="form-group">
                    <label>City</label>
                    <input type="text" name="city[${index}]" placeholder="City">
                </div>
                <div class="form-group">
                    <label>State</label>
                    <input type="text" name="state[${index}]" placeholder="State">
                </div>
                <div class="form-group">
                    <label>Postcode</label>
                    <input type="text" name="zip[${index}]" placeholder="Postcode">
                </div>
                <div class="form-group">
                    <label>Country</label>
                    <input type="text" name="country[${index}]" placeholder="Country">
                </div>
            </div>
        </div>
    `;
    
    container.insertAdjacentHTML('beforeend', addressHTML);
}

/**
 * Remove Address Field
 */
function removeAddress(button) {
    const section = button.closest('.repeatable-section');
    if (section) {
        section.remove();
    }
}

/**
 * Add Travel Entry Field
 */
function addTravel() {
    const container = document.getElementById('travelContainer');
    if (!container) return;
    
    const index = container.children.length;
    
    const travelHTML = `
        <div class="repeatable-section" data-index="${index}">
            <button type="button" class="remove-item-btn" title="Remove Travel Entry" onclick="removeTravel(this)">
                ${typeof crmIconLegacy === 'function' ? crmIconLegacy('fas fa-trash') : '<i class="fas fa-trash"></i>'}
            </button>
            <div class="content-grid">
                <div class="form-group">
                    <label>Country</label>
                    <input type="text" name="travel_country[${index}]" placeholder="Enter country">
                </div>
                <div class="form-group">
                    <label>Arrival Date</label>
                    <input type="text" name="travel_arrival_date[${index}]" class="date-picker" placeholder="dd/mm/yyyy">
                </div>
                <div class="form-group">
                    <label>Departure Date</label>
                    <input type="text" name="travel_departure_date[${index}]" class="date-picker" placeholder="dd/mm/yyyy">
                </div>
                <div class="form-group">
                    <label>Purpose</label>
                    <input type="text" name="travel_purpose[${index}]" placeholder="Purpose of travel">
                </div>
            </div>
        </div>
    `;
    
    container.insertAdjacentHTML('beforeend', travelHTML);
}

/**
 * Remove Travel Entry Field
 */
function removeTravel(button) {
    const section = button.closest('.repeatable-section');
    if (section) {
        section.remove();
    }
}

/**
 * Add Test Score Field
 */
function addTestScore() {
    const container = document.getElementById('testScoresContainer');
    if (!container) return;
    
    const index = container.children.length;
    
    const testScoreHTML = `
        <div class="repeatable-section" data-index="${index}">
            <button type="button" class="remove-item-btn" title="Remove Test Score" onclick="removeTestScore(this)">
                ${typeof crmIconLegacy === 'function' ? crmIconLegacy('fas fa-trash') : '<i class="fas fa-trash"></i>'}
            </button>
            <div class="content-grid">
                <div class="form-group">
                    <label>Test Type</label>
                    <select name="test_type[${index}]">
                        <option value="">Select Test Type</option>
                        <option value="IELTS">IELTS</option>
                        <option value="PTE">PTE</option>
                        <option value="TOEFL">TOEFL</option>
                        <option value="CAE">CAE</option>
                        <option value="OET">OET</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Test Date</label>
                    <input type="text" name="test_date[${index}]" class="date-picker" placeholder="dd/mm/yyyy">
                </div>
                <div class="form-group">
                    <label>Listening</label>
                    <input type="text" name="listening[${index}]" placeholder="Score">
                </div>
                <div class="form-group">
                    <label>Reading</label>
                    <input type="text" name="reading[${index}]" placeholder="Score">
                </div>
                <div class="form-group">
                    <label>Writing</label>
                    <input type="text" name="writing[${index}]" placeholder="Score">
                </div>
                <div class="form-group">
                    <label>Speaking</label>
                    <input type="text" name="speaking[${index}]" placeholder="Score">
                </div>
                <div class="form-group">
                    <label>Overall Score</label>
                    <input type="text" name="overall_score[${index}]" placeholder="Overall">
                </div>
            </div>
        </div>
    `;
    
    container.insertAdjacentHTML('beforeend', testScoreHTML);
}

/**
 * Remove Test Score Field
 */
function removeTestScore(button) {
    const section = button.closest('.repeatable-section');
    if (section) {
        section.remove();
    }
}

/**
 * Add Qualification Field
 */
function addQualification() {
    const container = document.getElementById('qualificationsContainer');
    if (!container) return;
    
    const index = container.children.length;
    
    const qualificationHTML = `
        <div class="repeatable-section" data-index="${index}">
            <button type="button" class="remove-item-btn" title="Remove Qualification" onclick="removeQualification(this)">
                ${typeof crmIconLegacy === 'function' ? crmIconLegacy('fas fa-trash') : '<i class="fas fa-trash"></i>'}
            </button>
            <div class="content-grid">
                <div class="form-group">
                    <label>Qualification Level</label>
                    <select name="qualification_level[${index}]">
                        <option value="">Select Level</option>
                        <option value="PhD">PhD/Doctorate</option>
                        <option value="Masters">Masters</option>
                        <option value="Bachelor">Bachelor</option>
                        <option value="Diploma">Diploma</option>
                        <option value="Certificate">Certificate</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Institution</label>
                    <input type="text" name="institution[${index}]" placeholder="Institution name">
                </div>
                <div class="form-group">
                    <label>Country</label>
                    <input type="text" name="qualification_country[${index}]" placeholder="Country">
                </div>
                <div class="form-group">
                    <label>Completion Date</label>
                    <input type="text" name="completion_date[${index}]" class="date-picker" placeholder="dd/mm/yyyy">
                </div>
            </div>
        </div>
    `;
    
    container.insertAdjacentHTML('beforeend', qualificationHTML);
}

/**
 * Remove Qualification Field
 */
function removeQualification(button) {
    const section = button.closest('.repeatable-section');
    if (section) {
        section.remove();
    }
}

/**
 * Add Work Experience Field
 */
function addExperience() {
    const container = document.getElementById('experienceContainer');
    if (!container) return;
    
    const index = container.children.length;
    
    const experienceHTML = `
        <div class="repeatable-section" data-index="${index}">
            <button type="button" class="remove-item-btn" title="Remove Experience" onclick="removeExperience(this)">
                ${typeof crmIconLegacy === 'function' ? crmIconLegacy('fas fa-trash') : '<i class="fas fa-trash"></i>'}
            </button>
            <div class="content-grid">
                <div class="form-group">
                    <label>Job Title</label>
                    <input type="text" name="job_title[${index}]" placeholder="Job title">
                </div>
                <div class="form-group">
                    <label>Company</label>
                    <input type="text" name="company[${index}]" placeholder="Company name">
                </div>
                <div class="form-group">
                    <label>Country</label>
                    <input type="text" name="work_country[${index}]" placeholder="Country">
                </div>
                <div class="form-group">
                    <label>Start Date</label>
                    <input type="text" name="work_start_date[${index}]" class="date-picker" placeholder="dd/mm/yyyy">
                </div>
                <div class="form-group">
                    <label>End Date</label>
                    <input type="text" name="work_end_date[${index}]" class="date-picker" placeholder="dd/mm/yyyy">
                </div>
            </div>
        </div>
    `;
    
    container.insertAdjacentHTML('beforeend', experienceHTML);
}

/**
 * Remove Work Experience Field
 */
function removeExperience(button) {
    const section = button.closest('.repeatable-section');
    if (section) {
        section.remove();
    }
}

/**
 * Add Occupation Field
 */
function addOccupation() {
    const container = document.getElementById('occupationsContainer');
    if (!container) return;
    
    const index = container.children.length;
    
    const occupationHTML = `
        <div class="repeatable-section" data-index="${index}">
            <button type="button" class="remove-item-btn" title="Remove Occupation" onclick="removeOccupation(this)">
                ${typeof crmIconLegacy === 'function' ? crmIconLegacy('fas fa-trash') : '<i class="fas fa-trash"></i>'}
            </button>
            <div class="content-grid">
                <div class="form-group full-width">
                    <label>Nominated Occupation</label>
                    <input type="text" name="nominated_occupation[${index}]" placeholder="Enter occupation">
                </div>
                <div class="form-group">
                    <label>ANZSCO Code</label>
                    <input type="text" name="anzsco_code[${index}]" placeholder="ANZSCO code">
                </div>
                <div class="form-group">
                    <label>Assessment Status</label>
                    <select name="assessment_status[${index}]">
                        <option value="">Select Status</option>
                        <option value="Assessed">Assessed</option>
                        <option value="In Progress">In Progress</option>
                        <option value="Not Started">Not Started</option>
                    </select>
                </div>
            </div>
        </div>
    `;
    
    container.insertAdjacentHTML('beforeend', occupationHTML);
}

/**
 * Remove Occupation Field
 */
function removeOccupation(button) {
    const section = button.closest('.repeatable-section');
    if (section) {
        section.remove();
    }
}

/**
 * Add Family Member Field
 */
function addFamilyMember() {
    const container = document.getElementById('familyMembersContainer');
    if (!container) return;
    
    const index = container.children.length;
    
    const familyHTML = `
        <div class="repeatable-section" data-index="${index}">
            <button type="button" class="remove-item-btn" title="Remove Family Member" onclick="removeFamilyMember(this)">
                ${typeof crmIconLegacy === 'function' ? crmIconLegacy('fas fa-trash') : '<i class="fas fa-trash"></i>'}
            </button>
            <div class="content-grid">
                <div class="form-group">
                    <label>Relationship</label>
                    <select name="family_relationship[${index}]">
                        <option value="">Select Relationship</option>
                        <option value="Spouse">Spouse</option>
                        <option value="Partner">Partner</option>
                        <option value="Child">Child</option>
                        <option value="Parent">Parent</option>
                        <option value="Sibling">Sibling</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>First Name</label>
                    <input type="text" name="family_first_name[${index}]" placeholder="First name">
                </div>
                <div class="form-group">
                    <label>Last Name</label>
                    <input type="text" name="family_last_name[${index}]" placeholder="Last name">
                </div>
                <div class="form-group">
                    <label>Date of Birth</label>
                    <input type="text" name="family_dob[${index}]" class="date-picker" placeholder="dd/mm/yyyy">
                </div>
            </div>
        </div>
    `;
    
    container.insertAdjacentHTML('beforeend', familyHTML);
}

/**
 * Remove Family Member Field
 */
function removeFamilyMember(button) {
    const section = button.closest('.repeatable-section');
    if (section) {
        section.remove();
    }
}

// Make all functions globally available
window.addPhoneNumber = addPhoneNumber;
window.removePhoneField = removePhoneField;
window.addEmailAddress = addEmailAddress;
window.removeEmailField = removeEmailField;
window.addPassport = addPassport;
window.removePassport = removePassport;
window.addVisa = addVisa;
window.removeVisa = removeVisa;
window.addAddress = addAddress;
window.removeAddress = removeAddress;
window.addTravel = addTravel;
window.removeTravel = removeTravel;
window.addTestScore = addTestScore;
window.removeTestScore = removeTestScore;
window.addQualification = addQualification;
window.removeQualification = removeQualification;
window.addExperience = addExperience;
window.removeExperience = removeExperience;
window.addOccupation = addOccupation;
window.removeOccupation = removeOccupation;
window.addFamilyMember = addFamilyMember;
window.removeFamilyMember = removeFamilyMember;

