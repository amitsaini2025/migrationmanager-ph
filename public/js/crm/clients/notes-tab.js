/**
 * Client Detail — Notes tab lazy fragment loader.
 * Stub on first paint; load once on click. Pin, type pills, and matter
 * filter stay on the existing notes blade script + notes.js / detail-main.js.
 */
(function() {
    'use strict';

    var notesTabLoadPromise = null;

    function notesTabFragmentUrl(tabEl) {
        var el = tabEl || document.getElementById('noteterm-tab');
        if (el && el.getAttribute('data-noteterm-url')) {
            return el.getAttribute('data-noteterm-url');
        }
        var urls = (window.ClientDetailConfig && window.ClientDetailConfig.urls) || {};
        if (urls.notesTab) {
            return urls.notesTab;
        }
        var encodeId = window.ClientDetailConfig && window.ClientDetailConfig.encodeId;
        if (!encodeId) {
            return '';
        }
        var matterRef = (window.ClientDetailConfig.matterId || window.ClientDetailConfig.matterRefNo || '');
        var url = '/clients/detail-notes-tab/' + encodeURIComponent(encodeId);
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

    function showNotesLazyError(message) {
        var tab = document.getElementById('noteterm-tab');
        if (!tab) {
            return;
        }
        var placeholder = tab.querySelector('[data-noteterm-lazy-placeholder]');
        if (placeholder) {
            placeholder.textContent = message || 'Failed to load notes. Please refresh the page.';
            return;
        }
        var container = tab.querySelector('.notes-container') || tab.querySelector('.card') || tab;
        container.innerHTML = '<div class="workflow-v2-empty" data-noteterm-lazy-placeholder style="padding: 24px; color: #6c757d;">' +
            (message || 'Failed to load notes. Please refresh the page.') +
            '</div>';
    }

    function activateInjectedScripts(root) {
        return new Promise(function(resolve) {
            if (!root || !root.querySelectorAll) {
                resolve();
                return;
            }
            var scripts = Array.prototype.slice.call(root.querySelectorAll('script'));
            var i = 0;

            function next() {
                if (i >= scripts.length) {
                    resolve();
                    return;
                }
                var oldScript = scripts[i++];
                var scriptType = (oldScript.getAttribute('type') || '').toLowerCase();
                if (scriptType && scriptType !== 'text/javascript' && scriptType !== 'application/javascript') {
                    next();
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
                    s.async = false;
                    s.onload = s.onerror = next;
                    s.src = oldScript.src;
                    oldScript.parentNode.replaceChild(s, oldScript);
                    return;
                }
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
                oldScript.parentNode.replaceChild(s, oldScript);
                next();
            }

            next();
        });
    }

    function applyNotesFilters() {
        if (typeof window.SidebarTabs !== 'undefined' && typeof window.SidebarTabs.ensureAllTabActive === 'function') {
            window.SidebarTabs.ensureAllTabActive();
        }
        if (typeof window.filterNotes === 'function') {
            window.filterNotes();
        } else if (typeof window.SidebarTabs !== 'undefined' && typeof window.SidebarTabs.filterNotesByMatter === 'function') {
            var matterId = '';
            if (typeof $ !== 'undefined') {
                if ($('.general_matter_checkbox_client_detail').is(':checked')) {
                    matterId = $('.general_matter_checkbox_client_detail').val();
                } else {
                    matterId = $('#sel_matter_id_client_detail').val();
                }
            }
            window.SidebarTabs.filterNotesByMatter(matterId || window.SidebarTabs.selectedMatter || '');
        }
    }

    function initNotesTabAfterInject(tabEl) {
        return activateInjectedScripts(tabEl).then(function() {
            if (typeof refreshLucideIcons === 'function' && tabEl) {
                refreshLucideIcons(tabEl);
            }
            applyNotesFilters();
        });
    }

    function ensureNotesTabLoaded(force) {
        var currentTab = document.getElementById('noteterm-tab');
        if (!currentTab) {
            return Promise.resolve(null);
        }

        var needsLoad = force === true
            || currentTab.getAttribute('data-noteterm-lazy') === '1'
            || !!currentTab.querySelector('[data-noteterm-lazy-placeholder]');

        if (!needsLoad) {
            applyNotesFilters();
            return Promise.resolve(currentTab);
        }

        if (notesTabLoadPromise) {
            return notesTabLoadPromise;
        }

        var url = notesTabFragmentUrl(currentTab);
        if (!url) {
            showNotesLazyError('Notes URL is missing. Please refresh the page.');
            return Promise.reject(new Error('Notes fragment URL missing'));
        }

        currentTab.setAttribute('data-noteterm-loading', '1');
        var wasActive = currentTab.classList.contains('active');

        notesTabLoadPromise = fetch(url, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'text/html'
            },
            credentials: 'same-origin'
        })
        .then(function(response) {
            if (!response.ok) {
                throw new Error('Failed to load notes tab fragment (' + response.status + ')');
            }
            return response.text();
        })
        .then(function(html) {
            currentTab = document.getElementById('noteterm-tab');
            if (!currentTab || !currentTab.parentNode) {
                throw new Error('Notes tab element no longer in document');
            }
            var doc = new DOMParser().parseFromString(html, 'text/html');
            var parsedTab = doc.querySelector('#noteterm-tab');
            if (!parsedTab) {
                throw new Error('Notes tab fragment not found in response');
            }
            var newTab = importTabFragment(parsedTab);
            if (!newTab) {
                throw new Error('Failed to import notes tab fragment');
            }
            newTab.removeAttribute('data-noteterm-lazy');
            newTab.removeAttribute('data-noteterm-loading');
            if (!newTab.getAttribute('data-noteterm-url') && url) {
                newTab.setAttribute('data-noteterm-url', url);
            }
            if (wasActive || currentTab.classList.contains('active')) {
                newTab.classList.add('active');
            }
            currentTab.replaceWith(newTab);
            return initNotesTabAfterInject(newTab).then(function() {
                return newTab;
            });
        })
        .catch(function(err) {
            var tab = document.getElementById('noteterm-tab');
            if (tab) {
                tab.removeAttribute('data-noteterm-loading');
            }
            showNotesLazyError('Failed to load notes. Please refresh the page.');
            throw err;
        })
        .finally(function() {
            notesTabLoadPromise = null;
        });

        return notesTabLoadPromise;
    }

    function bootNotesTabIfNeeded() {
        var tab = document.getElementById('noteterm-tab');
        if (!tab) {
            return;
        }
        var activeNav = document.querySelector('.client-nav-button.active');
        var activeTab = (activeNav && activeNav.getAttribute('data-tab'))
            || (window.ClientDetailConfig && window.ClientDetailConfig.activeTab)
            || '';
        var path = window.location.pathname || '';
        if (activeTab !== 'noteterm' && !/\/noteterm\/?$/.test(path)) {
            return;
        }
        ensureNotesTabLoaded().catch(function(err) {
            console.error('[NotesTab] Boot load failed', err);
        });
    }

    window.ensureNotesTabLoaded = ensureNotesTabLoaded;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            bootNotesTabIfNeeded();
            setTimeout(bootNotesTabIfNeeded, 300);
        });
    } else {
        bootNotesTabIfNeeded();
        setTimeout(bootNotesTabIfNeeded, 300);
    }
})();
