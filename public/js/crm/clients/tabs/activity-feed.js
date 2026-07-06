/**
 * Activity Feed Functionality
 * Handles filtering, width toggle, and activity feed interactions
 */

(function($) {
    'use strict';

    /**
     * Initialize Activity Feed functionality
     */
    function init() {
        setupFilterButtons();
        setupWidthToggle();
        setupExtendedFilters();
        setupRefreshButton();
        setupLoadMoreButton();
    }

    /**
     * Setup refresh button to reload activities
     */
    function setupRefreshButton() {
        $('#activity-feed-refresh').on('click', function() {
            var $btn = $(this).find('i');
            $btn.addClass('fa-spin');
            if (typeof window.loadActivities === 'function') {
                window.loadActivities({ reset: true });
            }
            if (typeof getallactivities === 'function') {
                getallactivities();
            }
            setTimeout(function() { $btn.removeClass('fa-spin'); }, 800);
        });
    }

    function setupLoadMoreButton() {
        $('#activity-feed-load-more').on('click', function() {
            if (typeof window.loadActivities === 'function') {
                window.loadActivities({ reset: false, append: true });
            }
        });
    }

    /**
     * Setup activity filter buttons
     * Type filter works with extended filters (search, date) when they are active
     */
    function setupFilterButtons() {
        $('.activity-filter-btn').on('click', function() {
            $('.activity-filter-btn').removeClass('active');
            $(this).addClass('active');
            // Use applyExtendedFilters so type + search + date are combined when filter bar is visible
            if ($('#activity-feed-filter-bar').is(':visible')) {
                applyExtendedFilters();
            } else {
                filterActivities($(this).data('filter'));
            }
        });
    }

    /**
     * Subject-line patterns for legacy rows where activity_type is not set but the entry is financial
     * (invoices, receipts, ledger, etc.). Aligns with activities_logs.activity_type === 'financial'.
     */
    function getFinancialSubjectPatterns() {
        return [
            'invoice',
            'added invoice',
            'updated invoice',
            'deleted invoice',
            'receipt',
            'office receipt',
            'client receipt',
            'journal receipt',
            'receipt document',
            'journal receipt document',
            'client receipt document',
            'office receipt document',
            'added.*receipt',
            'updated.*receipt',
            'ledger',
            'client funds ledger',
            'fee transfer',
            'allocation',
            'allocated',
            'payment',
            'deposit',
            'withdrawal',
            'balance',
            'cost agreement',
            'account'
        ];
    }

    /** Ledger/financial receipt uploads — treat as financial, not generic "document" */
    function isFinancialReceiptDocumentSubject(subjectText) {
        return /(receipt document|journal receipt document|client receipt document|office receipt document)/i.test(subjectText || '');
    }

    /** Subject patterns for legacy rows without activity_type === 'document' (must match filterActivities) */
    function getDocumentFallbackPatterns() {
        return [
            'document',
            'added.*document',
            'updated.*document',
            'deleted.*document',
            'renamed.*document',
            'added.*migration document',
            'updated.*migration document',
            'added.*personal document',
            'updated.*personal document',
            'added.*visa document',
            'updated.*visa document',
            'added.*personal checklist',
            'added.*visa checklist',
            'updated.*checklist',
            'signed document',
            'signed cost agreement',
            'document.*attached',
            'document.*detached'
        ];
    }

    function subjectMatchesDocumentFallback(subjectText) {
        if (isFinancialReceiptDocumentSubject(subjectText)) {
            return false;
        }
        return getDocumentFallbackPatterns().some(function(pattern) {
            return new RegExp(pattern, 'i').test(subjectText || '');
        });
    }

    /** True for staff note rows: base class or structured note subtype (activity-type-note-*) */
    function isNoteFeedItem($item) {
        return $item.hasClass('activity-type-note') || $item.is('[class*="activity-type-note-"]');
    }

    /** Uploaded / synced email log rows (activity_type email, or legacy subject prefix from EmailUploadController) */
    function getFeedSubjectStrongText($item) {
        return ($item.find('.feed-content strong').first().text() || '').trim();
    }

    function isEmailFeedItem($item) {
        if ($item.hasClass('activity-type-email')) {
            return true;
        }
        return /uploaded email:/i.test(getFeedSubjectStrongText($item));
    }

    /**
     * Filter activities based on type
     * @param {string} filterType - all | activity | note | email | document | signature | financial
     */
    function filterActivities(filterType) {
        if (filterType === 'all') {
            $('.feed-item.activity').show();
        } else if (filterType === 'activity') {
            // Show activity-type-activity, activity-type-sms, and activity-type-stage (workflow)
            $('.feed-item.activity').hide();
            $('.feed-item.activity-type-activity, .feed-item.activity-type-sms, .feed-item.activity-type-stage').show();
        } else if (filterType === 'note') {
            // Staff notes only (activity_type === 'note'; Blade adds activity-type-note plus optional activity-type-note-* subtype)
            $('.feed-item.activity').each(function() {
                var $item = $(this);
                if (isNoteFeedItem($item)) {
                    $item.show();
                } else {
                    $item.hide();
                }
            });
        } else if (filterType === 'email') {
            $('.feed-item.activity').each(function() {
                var $item = $(this);
                if (isEmailFeedItem($item)) {
                    $item.show();
                } else {
                    $item.hide();
                }
            });
        } else if (filterType === 'document') {
            $('.feed-item.activity').hide();
            // Show document activities - check both class and subject text
            $('.feed-item.activity').each(function() {
                var $item = $(this);
                var hasDocumentClass = $item.hasClass('activity-type-document');
                
                // If it has the document class, show it
                if (hasDocumentClass) {
                    $item.show();
                    return;
                }
                
                // Fallback: Check subject text for document-related keywords
                // This handles legacy activities that don't have activity_type set
                if (isEmailFeedItem($item)) {
                    return;
                }
                var subject = $item.find('.feed-content strong').text().toLowerCase();
                var subjectText = subject || '';

                if (subjectMatchesDocumentFallback(subjectText)) {
                    $item.show();
                }
            });
        } else if (filterType === 'signature') {
            $('.feed-item.activity').hide();
            $('.feed-item.activity-type-signature').show();
        } else if (filterType === 'financial') {
            $('.feed-item.activity').hide();
            // Financial: activity_type financial in DB => activity-type-financial; plus legacy subject fallback
            $('.feed-item.activity').each(function() {
                var $item = $(this);
                if ($item.hasClass('activity-type-financial')) {
                    $item.show();
                    return;
                }

                if (isNoteFeedItem($item)) {
                    return;
                }

                if (isEmailFeedItem($item)) {
                    return;
                }

                var subject = $item.find('.feed-content strong').text().toLowerCase();
                var subjectText = subject || '';

                var isFinancial = getFinancialSubjectPatterns().some(function(pattern) {
                    return new RegExp(pattern, 'i').test(subjectText);
                });

                if (isFinancial) {
                    $item.show();
                }
            });
        } else {
            // Show only activities with a specific activity_type (matches activity-type-{filterType})
            $('.feed-item.activity').hide();
            $('.feed-item.activity-type-' + filterType).show();
        }
        updateEmptyState();
    }

    /**
     * Setup width toggle checkbox
     * When checked, shows extended filter bar (search, date range, apply/reset)
     */
    function setupWidthToggle() {
        $('#increase-activity-feed-width').on('change', function() {
            var onActivityTab = $('.crm-container').hasClass('crm-container--activity-tab');
            if ($(this).is(':checked')) {
                // On the full-width Activity tab the feed already fills the viewport —
                // only open the filter bar; don't add wide-mode / compact-mode.
                if (!onActivityTab) {
                    $('.activity-feed').addClass('wide-mode');
                    if ($('.main-content').is(':visible')) {
                        $('.main-content').addClass('compact-mode');
                    }
                }
                $('#activity-feed-filter-bar').slideDown(200);
                initActivityFeedDatepickers();
            } else {
                $('#activity-feed-filter-bar').slideUp(200);
                if (!onActivityTab) {
                    $('.activity-feed').removeClass('wide-mode');
                    $('.main-content').removeClass('compact-mode');
                }
            }
            
            // Adjust Activity Feed height after layout change
            if (typeof adjustActivityFeedHeight === 'function') {
                adjustActivityFeedHeight();
                setTimeout(function() {
                    adjustActivityFeedHeight();
                }, 150);
            }
        });
    }

    /**
     * Initialize Flatpickr on activity feed date inputs (when filter bar is visible)
     */
    function initActivityFeedDatepickers() {
        if (typeof flatpickr === 'undefined') return;
        var $from = $('#activity-feed-date-from');
        var $to = $('#activity-feed-date-to');
        if (!$from.length || !$to.length) return;
        if ($from.data('flatpickr')) return; // Already initialized
        flatpickr('#activity-feed-date-from', { dateFormat: 'Y-m-d', allowInput: true });
        flatpickr('#activity-feed-date-to', { dateFormat: 'Y-m-d', allowInput: true });
    }

    /**
     * Setup extended filters (search, date range, apply, reset)
     * Only active when checkbox is ticked
     */
    function setupExtendedFilters() {
        $('#activity-feed-apply').on('click', function() {
            applyExtendedFilters();
        });
        $('#activity-feed-reset').on('click', function() {
            $('#activity-feed-search').val('');
            $('#activity-feed-date-from').val('');
            $('#activity-feed-date-to').val('');
            applyExtendedFilters();
        });
        $('#activity-feed-search').on('keypress', function(e) {
            if (e.which === 13) { applyExtendedFilters(); }
        });
    }

    /**
     * Apply search and date filters, combined with current type filter
     */
    function applyExtendedFilters() {
        var searchVal = ($('#activity-feed-search').val() || '').trim().toLowerCase();
        var dateFrom = ($('#activity-feed-date-from').val() || '').trim();
        var dateTo = ($('#activity-feed-date-to').val() || '').trim();
        var activeType = $('.activity-filter-btn.active').data('filter') || 'all';

        $('.feed-item.activity').each(function() {
            var $item = $(this);
            var typeMatch = matchesTypeFilter($item, activeType);
            var searchMatch = !searchVal || $item.find('.feed-content').text().toLowerCase().indexOf(searchVal) >= 0;
            var itemDate = $item.attr('data-created-at') || '';
            var dateMatch = true;
            if (itemDate) {
                if (dateFrom && itemDate < dateFrom) dateMatch = false;
                if (dateTo && itemDate > dateTo) dateMatch = false;
            }
            $item.toggle(typeMatch && searchMatch && dateMatch);
        });

        updateEmptyState();
    }

    /**
     * Check if item matches the current type filter
     */
    function matchesTypeFilter($item, filterType) {
        if (filterType === 'all') return true;
        if (filterType === 'activity') {
            return $item.hasClass('activity-type-activity') || $item.hasClass('activity-type-sms') || $item.hasClass('activity-type-stage');
        }
        if (filterType === 'note') {
            return isNoteFeedItem($item);
        }
        if (filterType === 'email') {
            return isEmailFeedItem($item);
        }
        if (filterType === 'signature') {
            return $item.hasClass('activity-type-signature');
        }
        if (filterType === 'document') {
            if ($item.hasClass('activity-type-document')) return true;
            if (isEmailFeedItem($item)) return false;
            var subject = ($item.find('.feed-content strong').text() || '').toLowerCase();
            return subjectMatchesDocumentFallback(subject);
        }
        if (filterType === 'financial') {
            if ($item.hasClass('activity-type-financial')) return true;
            if (isNoteFeedItem($item)) return false;
            if (isEmailFeedItem($item)) return false;
            var subj = ($item.find('.feed-content strong').text() || '').toLowerCase();
            return getFinancialSubjectPatterns().some(function(pattern) {
                return new RegExp(pattern, 'i').test(subj);
            });
        }
        return $item.hasClass('activity-type-' + filterType);
    }

    /**
     * Show/hide empty state when no activities match
     */
    function updateEmptyState() {
        var visible = $('.feed-item.activity:visible').length;
        $('.feed-item--empty').toggle(visible === 0);
        $('.feed-item-no-results').toggle(visible === 0 && $('.feed-item.activity').length > 0);
    }

    /**
     * Tag legacy backfilled appointment rows and add missing highlight classes/chip.
     */
    function enhanceAppointmentActivityRows() {
        $('.appointment-activity-detail__body').each(function() {
            var $body = $(this);
            if ($body.find('.appointment-activity-detail__chip').length === 0) {
                $body.prepend('<div class="appointment-activity-detail__chip">Appointment</div>');
            }
        });

        $('.appointment-activity-detail__row').each(function() {
            var $row = $(this);
            if ($row.is('[data-field="datetime"], .appointment-activity-detail__row--datetime')) {
                return;
            }
            var label = ($row.find('.appointment-activity-detail__label').first().text() || '').trim();
            if (label === 'Date & Time:') {
                $row.addClass('appointment-activity-detail__row--datetime').attr('data-field', 'datetime');
            }
        });
    }

    // Initialize when DOM is ready
    $(document).ready(function() {
        init();
        if ($('.activity-feed').length) {
            enhanceAppointmentActivityRows();
        }
    });

    /**
     * Re-apply the current type filter (and extended search/date when bar is open) after AJAX replaces .feed-list HTML.
     */
    function reapplyCurrentFilter() {
        if (!$('.activity-feed').length || !$('.feed-list').length) {
            return;
        }
        if ($('#activity-feed-filter-bar').is(':visible')) {
            applyExtendedFilters();
        } else {
            var activeFilter = $('.activity-filter-btn.active').data('filter') || 'all';
            filterActivities(activeFilter);
        }
        enhanceAppointmentActivityRows();
    }

    // Expose public API
    window.ActivityFeed = {
        init: init,
        filterActivities: filterActivities,
        applyExtendedFilters: applyExtendedFilters,
        reapplyCurrentFilter: reapplyCurrentFilter,
        enhanceAppointmentActivityRows: enhanceAppointmentActivityRows
    };

})(jQuery);

