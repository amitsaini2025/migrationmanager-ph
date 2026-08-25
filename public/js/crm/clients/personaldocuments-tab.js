/**
 * Client Detail — Personal Documents tab lazy fragment loader.
 * Stub on first paint; load once on click; re-bind upload/rename/preview/context menus.
 * Inline Personal ↔ Not Used nav buttons are rebound via SidebarTabs.bindNavButtons.
 */
(function() {
    'use strict';

    var personalDocumentsTabLoadPromise = null;

    function personalDocumentsTabFragmentUrl(tabEl) {
        var el = tabEl || document.getElementById('personaldocuments-tab');
        if (el && el.getAttribute('data-personaldocuments-url')) {
            return el.getAttribute('data-personaldocuments-url');
        }
        var urls = (window.ClientDetailConfig && window.ClientDetailConfig.urls) || {};
        if (urls.personalDocumentsTab) {
            return urls.personalDocumentsTab;
        }
        var encodeId = window.ClientDetailConfig && window.ClientDetailConfig.encodeId;
        if (!encodeId) {
            return '';
        }
        var matterRef = (window.ClientDetailConfig.matterId || window.ClientDetailConfig.matterRefNo || '');
        var url = '/clients/detail-personaldocuments-tab/' + encodeURIComponent(encodeId);
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

    function showPersonalDocumentsLazyError(message) {
        var tab = document.getElementById('personaldocuments-tab');
        if (!tab) {
            return;
        }
        var placeholder = tab.querySelector('[data-personaldocuments-lazy-placeholder]');
        if (placeholder) {
            placeholder.textContent = message || 'Failed to load personal documents. Please refresh the page.';
            return;
        }
        var container = tab.querySelector('.card') || tab;
        container.innerHTML = '<div class="workflow-v2-empty" data-personaldocuments-lazy-placeholder style="padding: 24px; color: #6c757d;">' +
            (message || 'Failed to load personal documents. Please refresh the page.') +
            '</div>';
    }

    function activateInjectedScripts(root) {
        return new Promise(function(resolve) {
            if (!root) {
                resolve();
                return;
            }
            var scripts = [];
            if (root.tagName && String(root.tagName).toLowerCase() === 'script') {
                scripts = [root];
            } else if (root.querySelectorAll) {
                scripts = Array.prototype.slice.call(root.querySelectorAll('script'));
            }
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
                    if (oldScript.parentNode) {
                        oldScript.parentNode.replaceChild(s, oldScript);
                    } else {
                        document.head.appendChild(s);
                    }
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
                if (oldScript.parentNode) {
                    oldScript.parentNode.replaceChild(s, oldScript);
                } else {
                    document.head.appendChild(s);
                }
                next();
            }

            next();
        });
    }

    function bulkUploadButtonLabel(isClose) {
        var iconHtml = '';
        if (typeof window.crmI === 'function') {
            iconHtml = window.crmI(isClose ? 'fas fa-times' : 'fas fa-upload') + ' ';
        } else if (typeof window.crmIconLegacy === 'function') {
            iconHtml = window.crmIconLegacy(isClose ? 'fas fa-times' : 'fas fa-upload') + ' ';
        }
        return iconHtml + (isClose ? 'Close' : 'Bulk Upload');
    }

    /**
     * Bulk Upload toggle must live here: blade handlers sit outside
     * #personaldocuments-tab and are skipped when the tab is lazy-injected.
     */
    function bindPersonalBulkUploadToggle() {
        if (!window.jQuery) {
            return;
        }
        var $ = window.jQuery;
        $(document)
            .off('click.personalBulkToggle', '.bulk-upload-toggle-btn')
            .on('click.personalBulkToggle', '.bulk-upload-toggle-btn', function() {
                var $btn = $(this);
                var categoryId = $btn.data('categoryid');
                var $dropzone = $('#bulk-upload-' + categoryId);
                if (!$dropzone.length) {
                    return;
                }

                $('.bulk-upload-dropzone-container').not($dropzone).slideUp();
                $('.bulk-upload-toggle-btn').not($btn).each(function() {
                    $(this).html(bulkUploadButtonLabel(false));
                });

                if ($dropzone.is(':visible')) {
                    $dropzone.slideUp();
                    $btn.html(bulkUploadButtonLabel(false));
                    $dropzone.find('.bulk-upload-file-list').hide();
                    $dropzone.find('.bulk-upload-files-container').empty();
                    $dropzone.find('.file-count').text('0');
                    if (window.bulkUploadFiles) {
                        window.bulkUploadFiles[categoryId] = [];
                    }
                    return;
                }

                $dropzone.slideDown(function() {
                    if (typeof window.initBulkUploadDragDrop === 'function') {
                        window.initBulkUploadDragDrop();
                    }
                });
                $btn.html(bulkUploadButtonLabel(true));
                window.personalBulkUploadCurrentCategoryId = categoryId;
            });
    }

    /**
     * Context menus, mapping modal, and bulk-upload scripts are siblings of
     * #personaldocuments-tab in the fragment response — import them once.
     */
    function importPersonalDocumentsOrphanAssets(doc, afterEl) {
        return new Promise(function(resolve) {
            if (!doc || !doc.body || !afterEl || !afterEl.parentNode) {
                resolve();
                return;
            }

            var insertAfter = afterEl;
            var scriptHosts = [];

            Array.prototype.forEach.call(doc.body.children, function(child) {
                if (child.id === 'personaldocuments-tab') {
                    return;
                }
                // Styles are injected into <head> separately.
                if (String(child.tagName).toLowerCase() === 'style') {
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
                        scriptHosts.push(imported);
                        return;
                    }
                }

                insertAfter.parentNode.insertBefore(imported, insertAfter.nextSibling);
                insertAfter = imported;
                scriptHosts.push(imported);
            });

            if (window.__personalDocumentsOrphanScriptsLoaded) {
                resolve();
                return;
            }

            var chain = Promise.resolve();
            scriptHosts.forEach(function(host) {
                chain = chain.then(function() {
                    return activateInjectedScripts(host);
                });
            });
            chain.then(function() {
                window.__personalDocumentsOrphanScriptsLoaded = true;
                resolve();
            }).catch(function(err) {
                console.error('[PersonalDocuments] Orphan script activate failed', err);
                resolve();
            });
        });
    }

    function rebindPersonalDocumentsUi(tabEl) {
        var root = tabEl || document.getElementById('personaldocuments-tab');
        // Match sidebar-tabs.js / documents.js: list view is default. A visible
        // .grid_data (width:100%) squeezes the checklist column out of the flex row.
        if (window.jQuery && root) {
            window.jQuery(root).find('.grid_data').hide();
        }
        if (typeof refreshLucideIcons === 'function' && root) {
            refreshLucideIcons(root);
        }
        if (window.SidebarTabs && typeof window.SidebarTabs.bindNavButtons === 'function') {
            window.SidebarTabs.bindNavButtons(root);
        }
        bindPersonalBulkUploadToggle();
        if (typeof window.initPersonalDocDragDrop === 'function') {
            window.initPersonalDocDragDrop();
        }
        if (typeof window.initBulkUploadDragDrop === 'function') {
            window.initBulkUploadDragDrop();
        }
    }

    function initPersonalDocumentsTabAfterInject(tabEl) {
        rebindPersonalDocumentsUi(tabEl);
    }

    function needsFragmentLoad(tabEl, force) {
        if (!tabEl) {
            return false;
        }
        if (force === true) {
            return true;
        }
        return tabEl.getAttribute('data-personaldocuments-lazy') === '1'
            || !!tabEl.querySelector('[data-personaldocuments-lazy-placeholder]');
    }

    function ensurePersonalDocumentsTabLoaded(force) {
        var currentTab = document.getElementById('personaldocuments-tab');
        if (!currentTab) {
            return Promise.resolve(null);
        }

        if (!needsFragmentLoad(currentTab, force)) {
            initPersonalDocumentsTabAfterInject(currentTab);
            return Promise.resolve(currentTab);
        }

        if (personalDocumentsTabLoadPromise) {
            return personalDocumentsTabLoadPromise;
        }

        var url = personalDocumentsTabFragmentUrl(currentTab);
        if (!url) {
            showPersonalDocumentsLazyError('Personal Documents URL is missing. Please refresh the page.');
            return Promise.reject(new Error('Personal Documents fragment URL missing'));
        }

        currentTab.setAttribute('data-personaldocuments-loading', '1');
        var wasActive = currentTab.classList.contains('active');

        personalDocumentsTabLoadPromise = fetch(url, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'text/html'
            },
            credentials: 'same-origin'
        })
        .then(function(response) {
            if (!response.ok) {
                throw new Error('Failed to load Personal Documents tab fragment (' + response.status + ')');
            }
            return response.text();
        })
        .then(function(html) {
            currentTab = document.getElementById('personaldocuments-tab');
            if (!currentTab || !currentTab.parentNode) {
                throw new Error('Personal Documents tab element no longer in document');
            }
            var doc = new DOMParser().parseFromString(html, 'text/html');
            var parsedTab = doc.querySelector('#personaldocuments-tab');
            if (!parsedTab) {
                throw new Error('Personal Documents tab fragment not found in response');
            }
            // Styles sit outside #personaldocuments-tab in the blade; inject once so
            // dropzones / bulk-upload UI keep their dashed-box look after lazy load.
            if (!document.querySelector('style[data-personaldocuments-fragment-style]')) {
                Array.prototype.slice.call(doc.querySelectorAll('style')).forEach(function(styleEl) {
                    var text = styleEl.textContent || '';
                    if (!text.trim()) {
                        return;
                    }
                    var s = document.createElement('style');
                    s.setAttribute('data-personaldocuments-fragment-style', '1');
                    s.textContent = text;
                    document.head.appendChild(s);
                });
            }
            var newTab = importTabFragment(parsedTab);
            if (!newTab) {
                throw new Error('Failed to import Personal Documents tab fragment');
            }
            newTab.removeAttribute('data-personaldocuments-lazy');
            newTab.removeAttribute('data-personaldocuments-loading');
            if (!newTab.getAttribute('data-personaldocuments-url') && url) {
                newTab.setAttribute('data-personaldocuments-url', url);
            }
            if (wasActive || currentTab.classList.contains('active')) {
                newTab.classList.add('active');
            }
            currentTab.replaceWith(newTab);
            return activateInjectedScripts(newTab)
                .then(function() {
                    return importPersonalDocumentsOrphanAssets(doc, newTab);
                })
                .then(function() {
                    initPersonalDocumentsTabAfterInject(newTab);
                    return newTab;
                });
        })
        .catch(function(err) {
            var tab = document.getElementById('personaldocuments-tab');
            if (tab) {
                tab.removeAttribute('data-personaldocuments-loading');
            }
            showPersonalDocumentsLazyError('Failed to load personal documents. Please refresh the page.');
            throw err;
        })
        .finally(function() {
            personalDocumentsTabLoadPromise = null;
        });

        return personalDocumentsTabLoadPromise;
    }

    function bootPersonalDocumentsTabIfNeeded() {
        var tab = document.getElementById('personaldocuments-tab');
        if (!tab) {
            return;
        }
        var activeNav = document.querySelector('.client-nav-button.active');
        var activeTab = (activeNav && activeNav.getAttribute('data-tab'))
            || (window.ClientDetailConfig && window.ClientDetailConfig.activeTab)
            || '';
        var path = window.location.pathname || '';
        if (activeTab !== 'personaldocuments' && !/\/personaldocuments\/?$/.test(path)) {
            return;
        }
        ensurePersonalDocumentsTabLoaded().catch(function(err) {
            console.error('[PersonalDocuments] Boot load failed', err);
        });
    }

    window.ensurePersonalDocumentsTabLoaded = ensurePersonalDocumentsTabLoaded;
    window.initPersonalDocumentsTabAfterInject = initPersonalDocumentsTabAfterInject;
    window.bindPersonalBulkUploadToggle = bindPersonalBulkUploadToggle;

    bindPersonalBulkUploadToggle();

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            bindPersonalBulkUploadToggle();
            bootPersonalDocumentsTabIfNeeded();
            setTimeout(bootPersonalDocumentsTabIfNeeded, 300);
        });
    } else {
        bootPersonalDocumentsTabIfNeeded();
        setTimeout(bootPersonalDocumentsTabIfNeeded, 300);
    }
})();
