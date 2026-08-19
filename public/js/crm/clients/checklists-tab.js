/**
 * Client Detail — Checklists tab lazy fragment loader.
 * Mirrors Account: stub in first HTML, load once on click,
 * then bind Create checklist / cost-assignment and re-init staff mm-select.
 */
(function() {
    'use strict';

    var checklistsTabLoadPromise = null;

    function checklistsTabFragmentUrl(tabEl) {
        var el = tabEl || document.getElementById('checklists-tab');
        if (el && el.getAttribute('data-checklists-url')) {
            return el.getAttribute('data-checklists-url');
        }
        var urls = (window.ClientDetailConfig && window.ClientDetailConfig.urls) || {};
        if (urls.checklistsTab) {
            return urls.checklistsTab;
        }
        var encodeId = window.ClientDetailConfig && window.ClientDetailConfig.encodeId;
        if (!encodeId) {
            return '';
        }
        var matterRef = (window.ClientDetailConfig.matterId || window.ClientDetailConfig.matterRefNo || '');
        var url = '/clients/detail-checklists-tab/' + encodeURIComponent(encodeId);
        if (matterRef) {
            url += '/' + encodeURIComponent(matterRef);
        }
        return url;
    }

    function importTabFragment(node) {
        if (!node) {
            return null;
        }
        try {
            return document.importNode(node, true);
        } catch (err) {
            return node;
        }
    }

    function showChecklistsLazyError(message) {
        var tab = document.getElementById('checklists-tab');
        if (!tab) {
            return;
        }
        var placeholder = tab.querySelector('[data-checklists-lazy-placeholder]');
        if (placeholder) {
            placeholder.textContent = message || 'Failed to load checklists. Please refresh the page.';
            return;
        }
        var container = tab.querySelector('.checklists-container') || tab.querySelector('.card') || tab;
        container.innerHTML = '<div class="workflow-v2-empty" data-checklists-lazy-placeholder style="padding: 24px; color: #6c757d;">' +
            (message || 'Failed to load checklists. Please refresh the page.') +
            '</div>';
    }

    function activateInjectedScripts(root) {
        if (typeof window.activateInjectedScripts === 'function' && window.activateInjectedScripts !== activateInjectedScripts) {
            window.activateInjectedScripts(root);
            return;
        }
        if (!root || !root.querySelectorAll) {
            return;
        }
        Array.prototype.slice.call(root.querySelectorAll('script')).forEach(function(oldScript) {
            var scriptType = (oldScript.getAttribute('type') || '').toLowerCase();
            if (scriptType && scriptType !== 'text/javascript' && scriptType !== 'application/javascript') {
                return;
            }
            var s = document.createElement('script');
            Array.prototype.slice.call(oldScript.attributes || []).forEach(function(attr) {
                if (attr.name === 'src' || attr.name === 'type') {
                    return;
                }
                s.setAttribute(attr.name, attr.value);
            });
            if (oldScript.src) {
                s.src = oldScript.src;
                s.async = false;
            } else {
                var code = oldScript.textContent || '';
                if (document.readyState !== 'loading') {
                    code = '(function(){\n' +
                        'var __docAdd = Document.prototype.addEventListener;\n' +
                        'Document.prototype.addEventListener = function(type, listener, options){\n' +
                        '  if (String(type).toLowerCase() === "domcontentloaded") {\n' +
                        '    try { listener.call(this); } catch (e) { console.error(e); }\n' +
                        '    return;\n' +
                        '  }\n' +
                        '  return __docAdd.call(this, type, listener, options);\n' +
                        '};\n' +
                        'try {\n' + code + '\n} finally {\n' +
                        '  Document.prototype.addEventListener = __docAdd;\n' +
                        '}\n' +
                        '})();';
                }
                s.textContent = code;
            }
            oldScript.parentNode.replaceChild(s, oldScript);
        });
    }

    function initChecklistsStaffDropdowns(tabEl) {
        if (typeof $ === 'undefined' || typeof $.fn.mmSelect !== 'function') {
            return;
        }
        var $root = tabEl ? $(tabEl) : $('#checklists-tab');
        var $fields = $root.find('#checklist_matter_select, #checklist_migration_agent, #checklist_person_responsible, #checklist_person_assisting, #checklist_office');
        if (!$fields.length) {
            return;
        }
        $fields.each(function() {
            var $el = $(this);
            if ($el.data('mmSelect')) {
                return;
            }
            $el.mmSelect({
                dropdownParent: $('body'),
                width: '100%',
                dropdownCssClass: 'mm-checklist-create-dropdown',
                minimumResultsForSearch: 0
            });
        });
    }

    function initChecklistsTabAfterInject(tabEl) {
        activateInjectedScripts(tabEl);
        if (typeof refreshLucideIcons === 'function' && tabEl) {
            refreshLucideIcons(tabEl);
        }
        initChecklistsStaffDropdowns(tabEl);
    }

    function ensureChecklistsTabLoaded(force) {
        var currentTab = document.getElementById('checklists-tab');
        if (!currentTab) {
            return Promise.resolve(null);
        }

        var needsLoad = force === true
            || currentTab.getAttribute('data-checklists-lazy') === '1'
            || !!currentTab.querySelector('[data-checklists-lazy-placeholder]');

        if (!needsLoad) {
            return Promise.resolve(currentTab);
        }

        if (checklistsTabLoadPromise) {
            return checklistsTabLoadPromise;
        }

        var url = checklistsTabFragmentUrl(currentTab);
        if (!url) {
            showChecklistsLazyError('Checklists URL is missing. Please refresh the page.');
            return Promise.reject(new Error('Checklists fragment URL missing'));
        }

        currentTab.setAttribute('data-checklists-loading', '1');
        var wasActive = currentTab.classList.contains('active');

        checklistsTabLoadPromise = fetch(url, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'text/html'
            },
            credentials: 'same-origin'
        })
        .then(function(response) {
            if (!response.ok) {
                throw new Error('Failed to load checklists tab fragment (' + response.status + ')');
            }
            return response.text();
        })
        .then(function(html) {
            currentTab = document.getElementById('checklists-tab');
            if (!currentTab || !currentTab.parentNode) {
                throw new Error('Checklists tab element no longer in document');
            }
            var doc = new DOMParser().parseFromString(html, 'text/html');
            var parsedTab = doc.querySelector('#checklists-tab');
            if (!parsedTab) {
                throw new Error('Checklists tab fragment not found in response');
            }
            var newTab = importTabFragment(parsedTab);
            if (!newTab) {
                throw new Error('Failed to import checklists tab fragment');
            }
            newTab.removeAttribute('data-checklists-lazy');
            newTab.removeAttribute('data-checklists-loading');
            if (!newTab.getAttribute('data-checklists-url') && url) {
                newTab.setAttribute('data-checklists-url', url);
            }
            if (wasActive || currentTab.classList.contains('active')) {
                newTab.classList.add('active');
            }
            currentTab.replaceWith(newTab);
            initChecklistsTabAfterInject(newTab);
            return newTab;
        })
        .catch(function(err) {
            var tab = document.getElementById('checklists-tab');
            if (tab) {
                tab.removeAttribute('data-checklists-loading');
            }
            showChecklistsLazyError('Failed to load checklists. Please refresh the page.');
            throw err;
        })
        .finally(function() {
            checklistsTabLoadPromise = null;
        });

        return checklistsTabLoadPromise;
    }

    function bootChecklistsTabIfNeeded() {
        var tab = document.getElementById('checklists-tab');
        if (!tab) {
            return;
        }
        var needsLoad = tab.getAttribute('data-checklists-lazy') === '1'
            || !!tab.querySelector('[data-checklists-lazy-placeholder]');
        if (!needsLoad) {
            return;
        }
        var activeNav = document.querySelector('.client-nav-button.active');
        var activeTab = (activeNav && activeNav.getAttribute('data-tab'))
            || (window.ClientDetailConfig && window.ClientDetailConfig.activeTab)
            || '';
        var path = window.location.pathname || '';
        if (activeTab === 'checklists' || /\/checklists\/?$/.test(path)) {
            ensureChecklistsTabLoaded().catch(function(err) {
                console.error('[ChecklistsTab] Boot load failed', err);
            });
        }
    }

    window.ensureChecklistsTabLoaded = ensureChecklistsTabLoaded;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            bootChecklistsTabIfNeeded();
            setTimeout(bootChecklistsTabIfNeeded, 300);
        });
    } else {
        bootChecklistsTabIfNeeded();
        setTimeout(bootChecklistsTabIfNeeded, 300);
    }
})();
