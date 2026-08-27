/**
 * Flatpickr helper utilities for client detail pages.
 * Extracted from detail-main.js - Phase 2 refactoring.
 * Requires: jQuery, Flatpickr
 */
(function($) {
    'use strict';
    if (!$) return;

    /**
     * Initialize Flatpickr for elements matching selector.
     * @param {string} selector - jQuery selector for datepicker elements
     * @param {Object} [options] - Flatpickr options (merged with defaults)
     */
    function initFlatpickrForClass(selector, options) {
        if (typeof flatpickr === 'undefined') {
            console.warn('⚠️ Flatpickr not loaded for', selector);
            return;
        }

        var defaults = {
            dateFormat: 'd/m/Y',
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

            if (!$this.val() && config.defaultDate === undefined) {
                config.defaultDate = null;
            } else if ($this.val() && config.defaultDate === undefined) {
                config.defaultDate = $this.val();
            }

            var fp = flatpickr(element, config);
            $this.data('flatpickr', fp);
        });
    }

    /**
     * Initialize Flatpickr with AJAX callback on date change.
     * @param {string} selector - jQuery selector
     * @param {string} ajaxUrl - URL for AJAX request
     * @param {Function} [ajaxDataCallback] - Optional callback(dateStr) to build AJAX data
     * @param {Object} [options] - Flatpickr options
     */
    function initFlatpickrWithAjax(selector, ajaxUrl, ajaxDataCallback, options) {
        if (typeof flatpickr === 'undefined') {
            console.warn('⚠️ Flatpickr not loaded for', selector);
            return;
        }

        var defaults = {
            dateFormat: 'Y-m-d',
            allowInput: true,
            clickOpens: true,
            locale: { firstDayOfWeek: 1 }
        };

        var config = $.extend({}, defaults, options || {});

        $(selector).each(function() {
            var $this = $(this);
            var element = this;

            if ($this.data('flatpickr')) {
                return;
            }

            var originalOnChange = config.onChange;
            config.onChange = function(selectedDates, dateStr, instance) {
                $this.val(dateStr);

                if (dateStr && ajaxUrl) {
                    $('.popuploader').show();
                    var ajaxData = ajaxDataCallback ? ajaxDataCallback(dateStr) : { from: dateStr };
                    $.ajax({
                        url: ajaxUrl,
                        method: 'GET',
                        dataType: 'json',
                        data: ajaxData,
                        success: function() {
                            $('.popuploader').hide();
                        },
                        error: function() {
                            $('.popuploader').hide();
                        }
                    });
                }

                if (originalOnChange) {
                    originalOnChange(selectedDates, dateStr, instance);
                }
            };

            var fp = flatpickr(element, config);
            $this.data('flatpickr', fp);
        });
    }

    /**
     * Bind receipt/ledger date fields. Safe to call more than once: already-bound
     * inputs are skipped. Needed because #createreceiptmodal is a lazy stub until
     * the extra modal pack is injected (page-load init finds no inputs).
     */
    function initReceiptModalDatepickers() {
        initFlatpickrForClass('.report_date_fields');
        initFlatpickrForClass('.report_entry_date_fields', {
            defaultDate: new Date()
        });
        initFlatpickrForClass('.report_date_fields_invoice');
        initFlatpickrForClass('.report_entry_date_fields_invoice', {
            defaultDate: new Date()
        });
        initFlatpickrForClass('.report_date_fields_office');
        initFlatpickrForClass('.report_entry_date_fields_office', {
            defaultDate: new Date()
        });
        initFlatpickrForClass('.report_date_fields_journal');
        initFlatpickrForClass('.report_entry_date_fields_journal', {
            defaultDate: new Date()
        });
    }

    $(document).on(
        'shown.bs.modal',
        '#createreceiptmodal, #createclientreceiptmodal, #createinvoicereceiptmodal, #createofficereceiptmodal, #createjournalreceiptmodal, #createadjustinvoicereceiptmodal',
        function() {
            initReceiptModalDatepickers();
        }
    );

    window.initFlatpickrForClass = initFlatpickrForClass;
    window.initFlatpickrWithAjax = initFlatpickrWithAjax;
    window.initReceiptModalDatepickers = initReceiptModalDatepickers;

})(typeof jQuery !== 'undefined' ? jQuery : null);
