/**
 * Task Description @mentions
 *
 * Type @ in a task/note field to pick staff. Selecting a person inserts @Name
 * and adds them as an assignee (checkbox or single-select). Assignees stay
 * the source of truth.
 *
 * Opt-in only: .js-staff-mentions on plain textareas. TinyMCE and other
 * note fields are left alone.
 */
(function ($) {
    'use strict';

    if (typeof $ === 'undefined') {
        return;
    }

    if (window.__taskDescMentionsBound) {
        return;
    }
    window.__taskDescMentionsBound = true;

    var MENU_ID = 'task-desc-mention-menu';
    var STYLE_ID = 'task-desc-mention-styles';
    var MENU_Z = 20000;
    var TEXTAREA_SEL = '.js-staff-mentions';
    var MAX_RESULTS = 80;
    var activeState = null;
    var listenersBound = false;
    var $menuCached = null;
    var applyingMention = false;

    $(function () {
        ensureStyles();
        initTaskDescriptionMentions();
    });

    function initTaskDescriptionMentions() {
        if (listenersBound) {
            return;
        }
        listenersBound = true;

        $(document).on('input.taskMentions', TEXTAREA_SEL, function () {
            if (applyingMention || !isPlainMentionField(this)) {
                return;
            }
            handleInput(this);
        });

        $(document).on('keydown.taskMentions', TEXTAREA_SEL, function (e) {
            if (!isPlainMentionField(this)) {
                return;
            }
            handleKeydown(e, this);
        });

        $(document).on('blur.taskMentions', TEXTAREA_SEL, function () {
            setTimeout(function () {
                var active = document.activeElement;
                if (active && $menuCached && $menuCached[0] && $.contains($menuCached[0], active)) {
                    return;
                }
                hideMenu();
            }, 150);
        });

        $(document).on('hide.bs.popover hide.bs.modal', function () {
            hideMenu();
        });

        $(document).on('click.taskMentions', function (e) {
            if (isMenuEvent(e) || isTaskDescriptionTextarea(e.target)) {
                return;
            }
            hideMenu();
        });
    }

    function isPlainMentionField(el) {
        if (!el || el.disabled || el.readOnly) {
            return false;
        }
        var $el = $(el);
        if (!$el.is('textarea' + TEXTAREA_SEL)) {
            return false;
        }
        if ($el.hasClass('tinymce-editor') || $el.hasClass('summernote')) {
            return false;
        }
        if (el.id && window.tinymce && typeof window.tinymce.get === 'function' && window.tinymce.get(el.id)) {
            return false;
        }
        return true;
    }

    function ensureStyles() {
        if (document.getElementById(STYLE_ID)) {
            return;
        }
        var css = [
            '.task-desc-mention-menu{display:none;position:fixed;z-index:20000;max-height:220px;overflow-y:auto;',
            'background:#fff;border:1px solid #c8dcef;border-radius:8px;',
            'box-shadow:0 8px 24px rgba(30,61,96,.14);padding:4px;}',
            '.task-desc-mention-item{display:flex;align-items:center;gap:8px;width:100%;border:0;',
            'background:transparent;text-align:left;padding:8px 10px;border-radius:6px;',
            'color:#1a2c40;font-size:.9rem;cursor:pointer;}',
            '.task-desc-mention-item .mention-at-icon{display:inline-flex;align-items:center;justify-content:center;',
            'flex-shrink:0;width:14px;height:14px;color:#3a6fa8;font-weight:700;font-size:.95rem;line-height:1;}',
            '.task-desc-mention-item svg.mention-at-icon,.task-desc-mention-item .mention-at-icon svg{width:14px;height:14px;}',
            '.task-desc-mention-item:hover,.task-desc-mention-item.is-active{background:#ddeaf8;}'
        ].join('');
        $('<style id="' + STYLE_ID + '">').text(css).appendTo('head');
    }

    function menuHost(textarea) {
        if (textarea) {
            var $modal = $(textarea).closest('.modal');
            if ($modal.length) {
                return $modal.first();
            }
        }
        return $(document.body);
    }

    function ensureMenu(textarea) {
        if ($menuCached && $menuCached.length && document.body.contains($menuCached[0])) {
            var $host = menuHost(textarea);
            if ($menuCached.parent()[0] !== $host[0]) {
                $host.append($menuCached);
            }
            return $menuCached;
        }

        var $existing = $('#' + MENU_ID);
        if ($existing.length) {
            $menuCached = $existing;
        } else {
            $menuCached = $('<div id="' + MENU_ID + '" class="task-desc-mention-menu" role="listbox" aria-label="Tag staff"></div>');
        }
        menuHost(textarea).append($menuCached);

        $menuCached.off('.taskMentions');
        $menuCached.on('mousedown.taskMentions click.taskMentions touchstart.taskMentions', function (e) {
            e.stopPropagation();
        });
        $menuCached.on('mousedown.taskMentions', '.task-desc-mention-item', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var staffId = $(this).attr('data-staff-id');
            var staffName = $(this).attr('data-staff-name') || '';
            if (activeState && staffId) {
                applyMention(activeState.textarea, activeState.mention, staffId, staffName);
            }
        });
        $menuCached.on('click.taskMentions', '.task-desc-mention-item', function (e) {
            e.preventDefault();
            e.stopPropagation();
            hideMenu();
        });

        return $menuCached;
    }

    function isMenuEvent(e) {
        var $menu = $menuCached && $menuCached.length ? $menuCached : $('#' + MENU_ID);
        if (!$menu.length || !e || !e.target) {
            return false;
        }
        return e.target === $menu[0] || $.contains($menu[0], e.target);
    }

    function isTaskDescriptionTextarea(el) {
        return !!(el && $(el).is(TEXTAREA_SEL));
    }

    /**
     * @param {JQuery} $textarea
     * @returns {JQuery}
     */
    function findRoot($textarea) {
        var $root = $textarea.closest('.add-task-layout, .update-task-layout, #create_action_popup, #create_task_modal');
        if ($root.length) {
            return $root.first();
        }
        $root = $textarea.closest('.popover, .modal');
        return $root.first();
    }

    function handleInput(textarea) {
        var mention = getMentionAtCaret(textarea);
        if (!mention) {
            hideMenu();
            return;
        }

        var $root = findRoot($(textarea));
        if (!$root.length) {
            hideMenu();
            return;
        }

        var staff = collectStaff($root);
        var filtered = filterStaff(staff, mention.query);
        if (!filtered.length) {
            hideMenu();
            return;
        }

        activeState = { textarea: textarea, mention: mention, items: filtered, index: 0 };
        renderMenu(textarea, filtered, 0);
    }

    function handleKeydown(e, textarea) {
        var $menu = $menuCached;
        if (!$menu || !$menu.length || !$menu.is(':visible')) {
            return;
        }
        if (!activeState || activeState.textarea !== textarea) {
            return;
        }

        var key = e.key;
        if (key === 'ArrowDown') {
            e.preventDefault();
            activeState.index = (activeState.index + 1) % activeState.items.length;
            highlightMenu(activeState.index);
            return;
        }
        if (key === 'ArrowUp') {
            e.preventDefault();
            activeState.index = (activeState.index - 1 + activeState.items.length) % activeState.items.length;
            highlightMenu(activeState.index);
            return;
        }
        if (key === 'Enter' || key === 'Tab') {
            e.preventDefault();
            var item = activeState.items[activeState.index];
            if (item) {
                applyMention(textarea, activeState.mention, item.id, item.name);
            }
            hideMenu();
            return;
        }
        if (key === 'Escape') {
            e.preventDefault();
            hideMenu();
        }
    }

    function getMentionAtCaret(textarea) {
        var value = textarea.value || '';
        var pos = typeof textarea.selectionStart === 'number' ? textarea.selectionStart : value.length;
        var before = value.slice(0, pos);
        var match = before.match(/(^|[\s(\[{])@([^\s@]*)$/);
        if (!match) {
            return null;
        }
        return {
            start: before.lastIndexOf('@'),
            end: pos,
            query: match[2] || ''
        };
    }

    function collectStaff($root) {
        var list = [];
        var seen = {};

        $root.find('.checkbox-item').each(function () {
            var $cb = $(this);
            var id = String($cb.val() || '');
            if (!id || seen[id]) {
                return;
            }
            var $item = $cb.closest('.assignee-item, .staff-item, .modern-staff-item, label');
            var name = (
                $item.attr('data-staff-name') ||
                $cb.attr('data-staff-name') ||
                $cb.attr('data-name') ||
                $item.find('.staff-name').first().text() ||
                ''
            ).trim();
            if (!name) {
                var raw = ($item.text() || '').replace(/\s+/g, ' ').trim();
                name = raw.replace(/\s*\([^)]*\)\s*$/, '').trim();
            } else {
                name = name.replace(/\s*\([^)]*\)\s*$/, '').trim();
            }
            if (!name) {
                return;
            }
            seen[id] = true;
            var search = ($item.attr('data-searchtext') || $item.attr('data-name') || (name + id))
                .toLowerCase()
                .replace(/\s+/g, '');
            list.push({ id: id, name: name, search: search });
        });

        if (!list.length) {
            $root.find('select#rem_cat option, select#update_task_rem_cat option, select#add_task_rem_cat option, select#dashboard_rem_cat option').each(function () {
                var $opt = $(this);
                var id = String($opt.val() || '');
                if (!id || seen[id]) {
                    return;
                }
                var name = ($opt.text() || '').replace(/\s+/g, ' ').trim().replace(/\s*\([^)]*\)\s*$/, '').trim();
                if (!name) {
                    return;
                }
                seen[id] = true;
                list.push({
                    id: id,
                    name: name,
                    search: (name + id).toLowerCase().replace(/\s+/g, '')
                });
            });
        }

        return list;
    }

    function filterStaff(staff, query) {
        var q = String(query || '').toLowerCase().replace(/\s+/g, '');
        var matched = !q ? staff.slice() : staff.filter(function (s) {
            return s.search.indexOf(q) !== -1 || s.name.toLowerCase().replace(/\s+/g, '').indexOf(q) !== -1;
        });
        return matched.slice(0, MAX_RESULTS);
    }

    function mentionAtIconHtml() {
        var html = '';
        if (typeof window.crmIconLegacy === 'function') {
            html = window.crmIconLegacy('fa-at', { class: 'mention-at-icon', size: 14 }) || '';
        } else if (typeof window.crmI === 'function') {
            html = window.crmI('fa-at', { class: 'mention-at-icon', size: 14 }) || '';
        }
        if (html) {
            return html;
        }
        return '<span class="mention-at-icon" aria-hidden="true">@</span>';
    }

    function renderMenu(textarea, items, activeIndex) {
        var $menu = ensureMenu(textarea);
        var html = items.map(function (item, i) {
            var activeClass = i === activeIndex ? ' is-active' : '';
            return '<button type="button" class="task-desc-mention-item' + activeClass + '" role="option"' +
                ' data-staff-id="' + escapeAttr(item.id) + '"' +
                ' data-staff-name="' + escapeAttr(item.name) + '">' +
                mentionAtIconHtml() +
                '<span>' + escapeHtml(item.name) + '</span>' +
                '</button>';
        }).join('');

        $menu.html(html).show();
        positionMenu(textarea, $menu);
    }

    function highlightMenu(index) {
        var $menu = ensureMenu(activeState && activeState.textarea);
        $menu.find('.task-desc-mention-item').removeClass('is-active')
            .eq(index).addClass('is-active');
        var el = $menu.find('.task-desc-mention-item').get(index);
        if (el && typeof el.scrollIntoView === 'function') {
            el.scrollIntoView({ block: 'nearest' });
        }
    }

    function positionMenu(textarea, $menu) {
        var rect = textarea.getBoundingClientRect();
        var menuHeight = $menu.outerHeight() || 200;
        var spaceBelow = window.innerHeight - rect.bottom;
        var top = spaceBelow < menuHeight && rect.top > menuHeight
            ? (rect.top - menuHeight - 4)
            : (rect.bottom + 4);
        var width = Math.min(Math.max(rect.width, 220), 320);
        var left = Math.min(rect.left, window.innerWidth - width - 8);
        $menu.css({
            position: 'fixed',
            top: Math.max(8, top) + 'px',
            left: Math.max(8, left) + 'px',
            width: width + 'px',
            zIndex: MENU_Z
        });
    }

    function applyMention(textarea, mention, staffId, staffName) {
        applyingMention = true;
        try {
            var value = textarea.value || '';
            var insert = '@' + staffName + ' ';
            textarea.value = value.slice(0, mention.start) + insert + value.slice(mention.end);
            var caret = mention.start + insert.length;
            textarea.focus();
            if (typeof textarea.setSelectionRange === 'function') {
                textarea.setSelectionRange(caret, caret);
            }

            var $root = findRoot($(textarea));
            if (!$root.length) {
                return;
            }
            syncAssignee($root, staffId);
        } finally {
            applyingMention = false;
        }
    }

    function syncAssignee($root, staffId) {
        var id = String(staffId);
        var $cb = $root.find('.checkbox-item').filter(function () {
            return String($(this).val()) === id;
        }).first();

        if ($cb.length) {
            if (!$cb.prop('checked')) {
                $cb.prop('checked', true).trigger('change');
            }
            var $dash = $root.find('#dashboard_rem_cat');
            if ($dash.length && $dash.prop('multiple')) {
                var current = $dash.val() || [];
                if (current.indexOf(id) === -1) {
                    current.push(id);
                    $dash.val(current);
                }
            }
            return;
        }

        $root.find('select#rem_cat, select#update_task_rem_cat').each(function () {
            var $sel = $(this);
            if ($sel.prop('multiple')) {
                return;
            }
            $sel.val(id).trigger('change');
        });
    }

    function hideMenu() {
        if ($menuCached && $menuCached.length) {
            $menuCached.hide().empty();
        } else {
            $('#' + MENU_ID).hide().empty();
        }
        activeState = null;
    }

    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function escapeAttr(str) {
        return escapeHtml(str).replace(/'/g, '&#39;');
    }

    window.TaskDescriptionMentions = {
        init: initTaskDescriptionMentions,
        hide: hideMenu
    };
})(window.jQuery);
