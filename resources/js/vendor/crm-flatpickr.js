/**
 * Global Flatpickr Configuration for CRM
 * Version: 2.0
 * Created: 2026-01-09
 * 
 * Migration from CRM_DatePicker (daterangepicker) to CRM_Flatpickr (flatpickr)
 *
 * Do not load legacy jQuery/Bootstrap datepicker bundles (removed from public/js and
 * public/css); layouts use Flatpickr only. Grep the repo before re-adding vendor scripts.
 *
 * Usage Guide:
 * - For NEW code: Use CRM_Flatpickr methods
 * - API compatible with CRM_DatePicker for easy migration
 * 
 * Example: CRM_Flatpickr.initStandard('.my-date-field');
 */

window.CRM_Flatpickr = {
    /**
     * Standard single date picker (DD/MM/YYYY format)
     * Use for: New forms, modals, any new date inputs
     * 
     * Example: CRM_Flatpickr.initStandard('.my-date-field');
     * 
     * @param {string|jQuery|HTMLElement} selector - CSS selector, jQuery object, or DOM element
     * @param {object} options - Optional configuration overrides
     */
    initStandard: function(selector, options) {
        if (typeof flatpickr === 'undefined') {
            console.warn('⚠️ CRM_Flatpickr: flatpickr library not loaded');
            return;
        }

        // Calculate maxDate as Date object to avoid format mismatch issues
        var maxYear = new Date().getFullYear() + 50;
        var maxDateObj = new Date(maxYear, 11, 31); // Month is 0-indexed, so 11 = December
        
        var defaults = {
            dateFormat: 'd/m/Y', // DD/MM/YYYY display format
            allowInput: true,
            clickOpens: true,
            locale: {
                firstDayOfWeek: 1 // Monday
            },
            minDate: '01/01/1900',
            maxDate: maxDateObj
        };
        
        var config = $.extend({}, defaults, options || {});
        
        $(selector).each(function() {
            var $this = $(this);
            var element = this;
            
            // Skip if already initialized
            if ($this.data('flatpickr')) {
                return;
            }
            
            // Set default date if value exists
            if ($this.val() && !config.defaultDate) {
                config.defaultDate = $this.val();
            }
            
            // Initialize Flatpickr
            var fp = flatpickr(element, config);
            
            // Trigger change event on date selection
            fp.config.onChange.push(function(selectedDates, dateStr, instance) {
                $this.val(dateStr);
                $this.trigger('change');
            });
            
            $this.data('flatpickr', fp);
        });
        
        console.log('✅ CRM_Flatpickr.initStandard:', selector);
    },

    /**
     * DOB picker with automatic age calculation
     * Use for: Date of birth fields
     * 
     * Example: CRM_Flatpickr.initDOB('#dob', '#age');
     * 
     * @param {string|jQuery|HTMLElement} dobSelector - DOB input selector
     * @param {string|jQuery|HTMLElement} ageSelector - Age output field selector (optional)
     * @param {object} options - Optional configuration overrides
     */
    /**
     * Past-dates-only picker (maxDate: today)
     * Use for: Address dates, travel dates, employment dates, document issue dates, visa grant dates
     * 
     * Example: CRM_Flatpickr.initPastDates('.address-start-date');
     * 
     * @param {string|jQuery|HTMLElement} selector - CSS selector, jQuery object, or DOM element
     * @param {object} options - Optional configuration overrides
     */
    initPastDates: function(selector, options) {
        if (typeof flatpickr === 'undefined') {
            console.warn('⚠️ CRM_Flatpickr: flatpickr library not loaded');
            return;
        }

        var defaults = {
            dateFormat: 'd/m/Y',
            allowInput: true,
            clickOpens: true,
            locale: {
                firstDayOfWeek: 1
            },
            minDate: '01/01/1900',
            maxDate: 'today'
        };

        var config = $.extend({}, defaults, options || {});

        $(selector).each(function() {
            var $this = $(this);
            var element = this;

            if ($this.data('flatpickr')) {
                return;
            }

            if ($this.val() && !config.defaultDate) {
                config.defaultDate = $this.val();
            }

            var fp = flatpickr(element, config);

            fp.config.onChange.push(function(selectedDates, dateStr, instance) {
                $this.val(dateStr);
                $this.trigger('change');
            });

            $this.data('flatpickr', fp);
        });

        console.log('✅ CRM_Flatpickr.initPastDates:', selector);
    },

    initDOB: function(dobSelector, ageSelector, options) {
        if (typeof flatpickr === 'undefined') {
            console.warn('⚠️ CRM_Flatpickr: flatpickr library not loaded');
            return;
        }

        var defaults = {
            dateFormat: 'd/m/Y', // DD/MM/YYYY display format
            allowInput: true,
            clickOpens: true,
            locale: {
                firstDayOfWeek: 1
            },
            minDate: '01/01/1900',
            maxDate: 'today'
        };
        
        var config = $.extend({}, defaults, options || {});
        
        $(dobSelector).each(function() {
            var $this = $(this);
            var element = this;
            
            if ($this.data('flatpickr')) {
                return;
            }
            
            if ($this.val() && !config.defaultDate) {
                config.defaultDate = $this.val();
            }
            
            // Initialize Flatpickr
            var fp = flatpickr(element, config);
            
            // Add onChange handler for age calculation
            fp.config.onChange.push(function(selectedDates, dateStr, instance) {
                $this.val(dateStr);
                
                // Calculate age if age field is provided
                if (ageSelector && $(ageSelector).length) {
                    var age = CRM_Flatpickr.calculateAge(dateStr);
                    $(ageSelector).val(age);
                }
                
                $this.trigger('change');
            });
            
            $this.data('flatpickr', fp);
        });
        
        console.log('✅ CRM_Flatpickr.initDOB:', dobSelector, '→', ageSelector);
    },

    /**
     * DateTime picker (date + time selection)
     * Use for: Date and time selection (appointments, schedules)
     * 
     * Example: CRM_Flatpickr.initDateTime('.datetime-field');
     * 
     * @param {string|jQuery|HTMLElement} selector - CSS selector, jQuery object, or DOM element
     * @param {object} options - Optional configuration overrides
     */
    initDateTime: function(selector, options) {
        if (typeof flatpickr === 'undefined') {
            console.warn('⚠️ CRM_Flatpickr: flatpickr library not loaded');
            return;
        }

        var defaults = {
            dateFormat: 'd/m/Y H:i', // DD/MM/YYYY HH:mm format
            allowInput: true,
            clickOpens: true,
            enableTime: true,
            time_24hr: false, // 12-hour format with AM/PM
            locale: {
                firstDayOfWeek: 1
            },
            minDate: '01/01/1900'
        };
        
        var config = $.extend({}, defaults, options || {});
        
        $(selector).each(function() {
            var $this = $(this);
            var element = this;
            
            if ($this.data('flatpickr')) {
                return;
            }
            
            if ($this.val() && !config.defaultDate) {
                config.defaultDate = $this.val();
            }
            
            var fp = flatpickr(element, config);
            
            fp.config.onChange.push(function(selectedDates, dateStr, instance) {
                $this.val(dateStr);
                $this.trigger('change');
            });
            
            $this.data('flatpickr', fp);
        });
        
        console.log('✅ CRM_Flatpickr.initDateTime:', selector);
    },

    /**
     * Date range picker
     * Use for: Report filters, date range selection
     * 
     * Example: CRM_Flatpickr.initRange('.date-range-field');
     * 
     * @param {string|jQuery|HTMLElement} selector - CSS selector, jQuery object, or DOM element
     * @param {object} options - Optional configuration overrides
     */
    initRange: function(selector, options) {
        if (typeof flatpickr === 'undefined') {
            console.warn('⚠️ CRM_Flatpickr: flatpickr library not loaded');
            return;
        }

        var defaults = {
            mode: 'range', // Enable range selection
            dateFormat: 'd/m/Y', // DD/MM/YYYY display format
            allowInput: true,
            clickOpens: true,
            locale: {
                firstDayOfWeek: 1
            }
        };
        
        var config = $.extend({}, defaults, options || {});
        
        $(selector).each(function() {
            var $this = $(this);
            var element = this;
            
            if ($this.data('flatpickr')) {
                return;
            }
            
            if ($this.val() && !config.defaultDate) {
                // Try to parse existing range value
                var existingValue = $this.val();
                if (existingValue.includes(' - ')) {
                    var dates = existingValue.split(' - ');
                    if (dates.length === 2) {
                        config.defaultDate = dates;
                    }
                } else {
                    config.defaultDate = existingValue;
                }
            }
            
            var fp = flatpickr(element, config);
            
            fp.config.onChange.push(function(selectedDates, dateStr, instance) {
                if (selectedDates.length === 2) {
                    // Format as "DD/MM/YYYY - DD/MM/YYYY"
                    var startDate = this.formatDate(selectedDates[0], 'd/m/Y');
                    var endDate = this.formatDate(selectedDates[1], 'd/m/Y');
                    $this.val(startDate + ' - ' + endDate);
                } else if (selectedDates.length === 1) {
                    $this.val(dateStr);
                } else {
                    $this.val('');
                }
                $this.trigger('change');
            });
            
            $this.data('flatpickr', fp);
        });
        
        console.log('✅ CRM_Flatpickr.initRange:', selector);
    },

    /**
     * Calculate age from DD/MM/YYYY date string
     * Returns: "XX years YY months" format
     * 
     * @param {string} dob - Date of birth in DD/MM/YYYY format
     * @returns {string} Age string or empty string if invalid
     */
    calculateAge: function(dob) {
        if (!dob || !/^\d{2}\/\d{2}\/\d{4}$/.test(dob)) return '';
        
        try {
            const [day, month, year] = dob.split('/').map(Number);
            const dobDate = new Date(year, month - 1, day);
            if (isNaN(dobDate.getTime())) return '';

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
            console.error('Age calculation error:', e);
            return '';
        }
    },

    /**
     * Convert DD/MM/YYYY to YYYY-MM-DD for database storage
     * 
     * @param {string} dateStr - Date in DD/MM/YYYY format
     * @returns {string|null} Date in YYYY-MM-DD format or null
     */
    toDatabase: function(dateStr) {
        if (!dateStr || !/^\d{2}\/\d{2}\/\d{4}$/.test(dateStr)) return null;
        const [day, month, year] = dateStr.split('/');
        return `${year}-${month.padStart(2, '0')}-${day.padStart(2, '0')}`;
    },

    /**
     * Convert YYYY-MM-DD to DD/MM/YYYY for display
     * 
     * @param {string} dateStr - Date in YYYY-MM-DD format
     * @returns {string} Date in DD/MM/YYYY format or empty string
     */
    toDisplay: function(dateStr) {
        if (!dateStr || !/^\d{4}-\d{2}-\d{2}$/.test(dateStr)) return '';
        const [year, month, day] = dateStr.split('-');
        return `${day}/${month}/${year}`;
    },

    /**
     * Validate date string format
     * 
     * @param {string} dateStr - Date string to validate
     * @param {string} format - Expected format ('DD/MM/YYYY' or 'YYYY-MM-DD')
     * @returns {boolean} True if valid
     */
    isValid: function(dateStr, format) {
        if (!dateStr) return false;
        
        if (format === 'DD/MM/YYYY') {
            if (!/^\d{2}\/\d{2}\/\d{4}$/.test(dateStr)) return false;
            const [day, month, year] = dateStr.split('/').map(Number);
            const date = new Date(year, month - 1, day);
            return date.getFullYear() === year && 
                   date.getMonth() === month - 1 && 
                   date.getDate() === day;
        } else if (format === 'YYYY-MM-DD') {
            if (!/^\d{4}-\d{2}-\d{2}$/.test(dateStr)) return false;
            const [year, month, day] = dateStr.split('-').map(Number);
            const date = new Date(year, month - 1, day);
            return date.getFullYear() === year && 
                   date.getMonth() === month - 1 && 
                   date.getDate() === day;
        }
        
        return false;
    }
};

/**
 * Auto-initialize Flatpickr with data attributes
 * This allows simple HTML-based initialization without JavaScript
 * 
 * Usage in HTML:
 * <input type="text" data-flatpickr="standard" placeholder="dd/mm/yyyy">
 * <input type="text" data-flatpickr="dob" data-age-field="#age">
 * <input type="text" data-flatpickr="datetime">
 * <input type="text" data-flatpickr="range">
 */
$(document).ready(function() {
    // Check if flatpickr is available
    if (typeof flatpickr === 'undefined') {
        console.warn('⚠️ CRM_Flatpickr: flatpickr library not loaded');
        return;
    }

    // Auto-initialize elements with data-flatpickr attributes
    $('[data-flatpickr="standard"]').each(function() {
        CRM_Flatpickr.initStandard(this);
    });

    $('[data-flatpickr="past-only"]').each(function() {
        CRM_Flatpickr.initPastDates(this);
    });
    
    $('[data-flatpickr="dob"]').each(function() {
        var ageField = $(this).data('age-field');
        CRM_Flatpickr.initDOB(this, ageField);
    });
    
    $('[data-flatpickr="datetime"]').each(function() {
        CRM_Flatpickr.initDateTime(this);
    });
    
    $('[data-flatpickr="range"]').each(function() {
        CRM_Flatpickr.initRange(this);
    });

    // Backward compatibility: data-datepicker (legacy CRM/daterangepicker attribute naming)
    $('[data-datepicker="standard"]').each(function() {
        CRM_Flatpickr.initStandard(this);
    });
    $('[data-datepicker="dob"]').each(function() {
        var ageField = $(this).data('age-field');
        CRM_Flatpickr.initDOB(this, ageField);
    });
    $('[data-datepicker="datetime"]').each(function() {
        CRM_Flatpickr.initDateTime(this);
    });
    $('[data-datepicker="range"]').each(function() {
        CRM_Flatpickr.initRange(this);
    });
    
    console.log('✅ CRM_Flatpickr auto-initialization complete');
});

// Export for debugging
if (typeof window !== 'undefined') {
    console.log('✅ CRM_Flatpickr loaded successfully - Use CRM_Flatpickr.initStandard() for new code');
}

// Backward compatibility: Alias CRM_DatePicker to CRM_Flatpickr for gradual migration
if (typeof window.CRM_DatePicker === 'undefined') {
    window.CRM_DatePicker = window.CRM_Flatpickr;
    console.log('✅ CRM_DatePicker aliased to CRM_Flatpickr for backward compatibility');
}
