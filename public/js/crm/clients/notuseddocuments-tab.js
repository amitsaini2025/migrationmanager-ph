/**
 * Client Detail — Not Used Documents tab lazy fragment loader.
 * Stub on first paint; load once on click; re-bind preview/context menus and
 * Personal ↔ Not Used inline nav jumps.
 */
(function() {
    'use strict';

    var notUsedDocumentsTabLoadPromise = null;
    var currentNotUsedContextFile = null;
    var currentNotUsedContextData = {};

    /**
     * Context-menu helpers live here (always loaded) so they stay on window
     * after lazy inject wraps the tab inline script in an IIFE.
     */
    function hideNotUsedContextMenu() {
        var menu = document.getElementById('notUsedFileContextMenu');
        if (menu) {
            menu.style.display = 'none';
        }
        document.removeEventListener('click', hideNotUsedContextMenu);
    }

    function showNotUsedFileContextMenu(event, fileId, fileType, fileUrl, docType, fileStatus) {
        if (event && typeof event.preventDefault === 'function') {
            event.preventDefault();
            event.stopPropagation();
        }

        currentNotUsedContextFile = fileId;
        currentNotUsedContextData = {
            fileId: fileId,
            fileType: fileType,
            fileUrl: fileUrl,
            docType: docType,
            fileStatus: fileStatus
        };

        var menu = document.getElementById('notUsedFileContextMenu');
        if (!menu) {
            return;
        }

        var MENU_WIDTH = 180;
        var MENU_HEIGHT = 120;
        var viewportWidth = window.innerWidth;
        var viewportHeight = window.innerHeight;
        var offset = 5;
        var clientX = event && typeof event.clientX === 'number' ? event.clientX : 0;
        var clientY = event && typeof event.clientY === 'number' ? event.clientY : 0;
        var menuLeft = clientX + offset;
        var menuTop = clientY + offset;

        if (menuLeft + MENU_WIDTH > viewportWidth) {
            menuLeft = clientX - MENU_WIDTH - offset;
        }
        if (menuTop + MENU_HEIGHT > viewportHeight) {
            menuTop = clientY - MENU_HEIGHT - offset;
        }
        menuLeft = Math.max(offset, menuLeft);
        menuTop = Math.max(offset, menuTop);

        menu.style.left = menuLeft + 'px';
        menu.style.top = menuTop + 'px';
        menu.style.display = 'block';

        setTimeout(function() {
            document.addEventListener('click', hideNotUsedContextMenu);
        }, 100);
    }

    function handleNotUsedContextAction(action) {
        if (!currentNotUsedContextFile) {
            return;
        }

        hideNotUsedContextMenu();

        var id = currentNotUsedContextFile;
        var $ = window.jQuery;

        switch (action) {
            case 'preview':
                if (currentNotUsedContextData.fileUrl) {
                    window.open(currentNotUsedContextData.fileUrl, '_blank');
                }
                break;
            case 'delete':
                if ($) {
                    $('#notuseddocuments-tab .deletenote[data-id="' + id + '"]').click();
                }
                break;
            case 'back-to-doc':
                if ($) {
                    $('#notuseddocuments-tab .backtodoc[data-id="' + id + '"]').click();
                }
                break;
        }
    }

    function bindNotUsedContextMenu(tabEl) {
        var root = tabEl || document.getElementById('notuseddocuments-tab');
        if (!root || root.getAttribute('data-notused-context-bound') === '1') {
            return;
        }
        root.setAttribute('data-notused-context-bound', '1');
        root.addEventListener('contextmenu', function(event) {
            var row = event.target && event.target.closest ? event.target.closest('.doc-row') : null;
            if (!row || !root.contains(row)) {
                return;
            }
            event.preventDefault();
            event.stopPropagation();
            showNotUsedFileContextMenu(
                event,
                row.getAttribute('data-id'),
                row.getAttribute('data-file-ext') || '',
                row.getAttribute('data-file-url') || '',
                row.getAttribute('data-doc-type') || '',
                row.getAttribute('data-file-status') || 'draft'
            );
        }, true);
    }

    window.showNotUsedFileContextMenu = showNotUsedFileContextMenu;
    window.hideNotUsedContextMenu = hideNotUsedContextMenu;
    window.handleNotUsedContextAction = handleNotUsedContextAction;

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            hideNotUsedContextMenu();
        }
    });

    function notUsedDocumentsTabFragmentUrl(tabEl) {
        var el = tabEl || document.getElementById('notuseddocuments-tab');
        if (el && el.getAttribute('data-notuseddocuments-url')) {
            return el.getAttribute('data-notuseddocuments-url');
        }
        var urls = (window.ClientDetailConfig && window.ClientDetailConfig.urls) || {};
        if (urls.notUsedDocumentsTab) {
            return urls.notUsedDocumentsTab;
        }
        var encodeId = window.ClientDetailConfig && window.ClientDetailConfig.encodeId;
        if (!encodeId) {
            return '';
        }
        var matterRef = (window.ClientDetailConfig.matterId || window.ClientDetailConfig.matterRefNo || '');
        var url = '/clients/detail-notuseddocuments-tab/' + encodeURIComponent(encodeId);
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

    function showNotUsedDocumentsLazyError(message) {
        var tab = document.getElementById('notuseddocuments-tab');
        if (!tab) {
            return;
        }
        var placeholder = tab.querySelector('[data-notuseddocuments-lazy-placeholder]');
        if (placeholder) {
            placeholder.textContent = message || 'Failed to load not used documents. Please refresh the page.';
            return;
        }
        var container = tab.querySelector('.card') || tab;
        container.innerHTML = '<div class="workflow-v2-empty" data-notuseddocuments-lazy-placeholder style="padding: 24px; color: #6c757d;">' +
            (message || 'Failed to load not used documents. Please refresh the page.') +
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

    /**
     * #notUsedFileContextMenu (and its hover style) are siblings of
     * #notuseddocuments-tab in the fragment — import them once so right-click
     * Preview / Delete / Back To Document work after lazy load.
     * Do not activate blade scripts here: handlers stay on window from this file.
     */
    function importNotUsedDocumentsOrphanAssets(doc, afterEl) {
        if (!doc || !doc.body || !afterEl || !afterEl.parentNode) {
            return;
        }

        if (!document.querySelector('style[data-notuseddocuments-fragment-style]')) {
            Array.prototype.slice.call(doc.querySelectorAll('style')).forEach(function(styleEl) {
                var text = styleEl.textContent || '';
                if (!text.trim()) {
                    return;
                }
                var s = document.createElement('style');
                s.setAttribute('data-notuseddocuments-fragment-style', '1');
                s.textContent = text;
                document.head.appendChild(s);
            });
        }

        var insertAfter = afterEl;
        Array.prototype.forEach.call(doc.body.children, function(child) {
            if (child.id === 'notuseddocuments-tab') {
                return;
            }
            if (String(child.tagName).toLowerCase() === 'style') {
                return;
            }
            // Never re-run fragment scripts — they would shadow window handlers.
            if (String(child.tagName).toLowerCase() === 'script') {
                return;
            }

            var imported = importTabFragment(child);
            if (!imported) {
                return;
            }

            if (imported.id) {
                var existing = document.getElementById(imported.id);
                if (existing && existing !== afterEl) {
                    existing.replaceWith(imported);
                    insertAfter = imported;
                    return;
                }
            }

            insertAfter.parentNode.insertBefore(imported, insertAfter.nextSibling);
            insertAfter = imported;
        });
    }

    function rebindNotUsedDocumentsUi(tabEl) {
        if (typeof refreshLucideIcons === 'function' && tabEl) {
            refreshLucideIcons(tabEl);
        }
        if (window.SidebarTabs && typeof window.SidebarTabs.bindNavButtons === 'function') {
            window.SidebarTabs.bindNavButtons(tabEl || document.getElementById('notuseddocuments-tab'));
        }
        bindNotUsedContextMenu(tabEl || document.getElementById('notuseddocuments-tab'));
    }

    function initNotUsedDocumentsTabAfterInject(tabEl) {
        rebindNotUsedDocumentsUi(tabEl);
    }

    function needsFragmentLoad(tabEl, force) {
        if (!tabEl) {
            return false;
        }
        if (force === true) {
            return true;
        }
        return tabEl.getAttribute('data-notuseddocuments-lazy') === '1'
            || !!tabEl.querySelector('[data-notuseddocuments-lazy-placeholder]');
    }

    function ensureNotUsedDocumentsTabLoaded(force) {
        var currentTab = document.getElementById('notuseddocuments-tab');
        if (!currentTab) {
            return Promise.resolve(null);
        }

        if (!needsFragmentLoad(currentTab, force)) {
            initNotUsedDocumentsTabAfterInject(currentTab);
            return Promise.resolve(currentTab);
        }

        if (notUsedDocumentsTabLoadPromise) {
            return notUsedDocumentsTabLoadPromise;
        }

        var url = notUsedDocumentsTabFragmentUrl(currentTab);
        if (!url) {
            showNotUsedDocumentsLazyError('Not Used Documents URL is missing. Please refresh the page.');
            return Promise.reject(new Error('Not Used Documents fragment URL missing'));
        }

        currentTab.setAttribute('data-notuseddocuments-loading', '1');
        var wasActive = currentTab.classList.contains('active');

        notUsedDocumentsTabLoadPromise = fetch(url, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'text/html'
            },
            credentials: 'same-origin'
        })
        .then(function(response) {
            if (!response.ok) {
                throw new Error('Failed to load Not Used Documents tab fragment (' + response.status + ')');
            }
            return response.text();
        })
        .then(function(html) {
            currentTab = document.getElementById('notuseddocuments-tab');
            if (!currentTab || !currentTab.parentNode) {
                throw new Error('Not Used Documents tab element no longer in document');
            }
            var doc = new DOMParser().parseFromString(html, 'text/html');
            var parsedTab = doc.querySelector('#notuseddocuments-tab');
            if (!parsedTab) {
                throw new Error('Not Used Documents tab fragment not found in response');
            }
            var newTab = importTabFragment(parsedTab);
            if (!newTab) {
                throw new Error('Failed to import Not Used Documents tab fragment');
            }
            newTab.removeAttribute('data-notuseddocuments-lazy');
            newTab.removeAttribute('data-notuseddocuments-loading');
            if (!newTab.getAttribute('data-notuseddocuments-url') && url) {
                newTab.setAttribute('data-notuseddocuments-url', url);
            }
            if (wasActive || currentTab.classList.contains('active')) {
                newTab.classList.add('active');
            }
            currentTab.replaceWith(newTab);
            return activateInjectedScripts(newTab)
                .then(function() {
                    importNotUsedDocumentsOrphanAssets(doc, newTab);
                    initNotUsedDocumentsTabAfterInject(newTab);
                    return newTab;
                });
        })
        .catch(function(err) {
            var tab = document.getElementById('notuseddocuments-tab');
            if (tab) {
                tab.removeAttribute('data-notuseddocuments-loading');
            }
            showNotUsedDocumentsLazyError('Failed to load not used documents. Please refresh the page.');
            throw err;
        })
        .finally(function() {
            notUsedDocumentsTabLoadPromise = null;
        });

        return notUsedDocumentsTabLoadPromise;
    }

    function bootNotUsedDocumentsTabIfNeeded() {
        var tab = document.getElementById('notuseddocuments-tab');
        if (!tab) {
            return;
        }
        var activeNav = document.querySelector('.client-nav-button.active');
        var activeTab = (activeNav && activeNav.getAttribute('data-tab'))
            || (window.ClientDetailConfig && window.ClientDetailConfig.activeTab)
            || '';
        var path = window.location.pathname || '';
        if (activeTab !== 'notuseddocuments' && !/\/notuseddocuments\/?$/.test(path)) {
            return;
        }
        ensureNotUsedDocumentsTabLoaded().catch(function(err) {
            console.error('[NotUsedDocuments] Boot load failed', err);
        });
    }

    window.ensureNotUsedDocumentsTabLoaded = ensureNotUsedDocumentsTabLoaded;
    window.initNotUsedDocumentsTabAfterInject = initNotUsedDocumentsTabAfterInject;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            bootNotUsedDocumentsTabIfNeeded();
            setTimeout(bootNotUsedDocumentsTabIfNeeded, 300);
        });
    } else {
        bootNotUsedDocumentsTabIfNeeded();
        setTimeout(bootNotUsedDocumentsTabIfNeeded, 300);
    }
})();
