/**
 * Client Detail — Visa Documents tab lazy fragment loader.
 * Stub on first paint; load once on click; re-bind upload/rename/preview/context menus.
 * On matter change / tab open: load first, then filter rows by matter.
 */
(function() {
    'use strict';

    var visaDocumentsTabLoadPromise = null;

    function visaDocumentsTabFragmentUrl(tabEl) {
        var el = tabEl || document.getElementById('visadocuments-tab');
        if (el && el.getAttribute('data-visadocuments-url')) {
            return el.getAttribute('data-visadocuments-url');
        }
        var urls = (window.ClientDetailConfig && window.ClientDetailConfig.urls) || {};
        if (urls.visaDocumentsTab) {
            return urls.visaDocumentsTab;
        }
        var encodeId = window.ClientDetailConfig && window.ClientDetailConfig.encodeId;
        if (!encodeId) {
            return '';
        }
        var matterRef = (window.ClientDetailConfig.matterId || window.ClientDetailConfig.matterRefNo || '');
        var url = '/clients/detail-visadocuments-tab/' + encodeURIComponent(encodeId);
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

    function showVisaDocumentsLazyError(message) {
        var tab = document.getElementById('visadocuments-tab');
        if (!tab) {
            return;
        }
        var placeholder = tab.querySelector('[data-visadocuments-lazy-placeholder]');
        if (placeholder) {
            placeholder.textContent = message || 'Failed to load visa documents. Please refresh the page.';
            return;
        }
        var container = tab.querySelector('.card') || tab;
        container.innerHTML = '<div class="workflow-v2-empty" data-visadocuments-lazy-placeholder style="padding: 24px; color: #6c757d;">' +
            (message || 'Failed to load visa documents. Please refresh the page.') +
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

    /**
     * Context menus, move modal, and visa scripts are siblings of
     * #visadocuments-tab in the fragment — import them once so right-click
     * (incl. after Form 956 soft upload) works after lazy load.
     */
    function importVisaDocumentsOrphanAssets(doc, afterEl) {
        return new Promise(function(resolve) {
            if (!doc || !doc.body || !afterEl || !afterEl.parentNode) {
                resolve();
                return;
            }

            if (!document.querySelector('style[data-visadocuments-fragment-style]')) {
                Array.prototype.slice.call(doc.querySelectorAll('style')).forEach(function(styleEl) {
                    var text = styleEl.textContent || '';
                    if (!text.trim()) {
                        return;
                    }
                    var s = document.createElement('style');
                    s.setAttribute('data-visadocuments-fragment-style', '1');
                    s.textContent = text;
                    document.head.appendChild(s);
                });
            }

            var insertAfter = afterEl;
            var scriptHosts = [];

            Array.prototype.forEach.call(doc.body.children, function(child) {
                if (child.id === 'visadocuments-tab') {
                    return;
                }
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

            if (window.__visaDocumentsOrphanScriptsLoaded) {
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
                window.__visaDocumentsOrphanScriptsLoaded = true;
                resolve();
            }).catch(function(err) {
                console.error('[VisaDocuments] Orphan script activate failed', err);
                resolve();
            });
        });
    }

    function filterVisaRowsAfterLoad() {
        var matterId = '';
        if (window.jQuery) {
            matterId = window.jQuery('#sel_matter_id_client_detail').val() || '';
        }
        if (window.SidebarTabs && typeof window.SidebarTabs.filterVisaDocumentsByMatter === 'function') {
            window.SidebarTabs.filterVisaDocumentsByMatter(matterId);
        }
    }

    function rebindVisaDocumentsUi(tabEl) {
        if (typeof refreshLucideIcons === 'function' && tabEl) {
            refreshLucideIcons(tabEl);
        }
        if (window.SidebarTabs && typeof window.SidebarTabs.bindNavButtons === 'function') {
            window.SidebarTabs.bindNavButtons(tabEl || document.getElementById('visadocuments-tab'));
        }
        if (typeof window.initVisaDocDragDrop === 'function') {
            window.initVisaDocDragDrop();
        }
        if (typeof window.initVisaBulkUploadDragDrop === 'function') {
            window.initVisaBulkUploadDragDrop();
        }
        filterVisaRowsAfterLoad();
    }

    function initVisaDocumentsTabAfterInject(tabEl) {
        rebindVisaDocumentsUi(tabEl);
    }

    function needsFragmentLoad(tabEl, force) {
        if (!tabEl) {
            return false;
        }
        if (force === true) {
            return true;
        }
        return tabEl.getAttribute('data-visadocuments-lazy') === '1'
            || !!tabEl.querySelector('[data-visadocuments-lazy-placeholder]');
    }

    function ensureVisaDocumentsTabLoaded(force) {
        var currentTab = document.getElementById('visadocuments-tab');
        if (!currentTab) {
            return Promise.resolve(null);
        }

        if (!needsFragmentLoad(currentTab, force)) {
            initVisaDocumentsTabAfterInject(currentTab);
            return Promise.resolve(currentTab);
        }

        if (visaDocumentsTabLoadPromise) {
            return visaDocumentsTabLoadPromise;
        }

        var url = visaDocumentsTabFragmentUrl(currentTab);
        if (!url) {
            showVisaDocumentsLazyError('Visa Documents URL is missing. Please refresh the page.');
            return Promise.reject(new Error('Visa Documents fragment URL missing'));
        }

        currentTab.setAttribute('data-visadocuments-loading', '1');
        var wasActive = currentTab.classList.contains('active');

        visaDocumentsTabLoadPromise = fetch(url, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'text/html'
            },
            credentials: 'same-origin'
        })
        .then(function(response) {
            if (!response.ok) {
                throw new Error('Failed to load Visa Documents tab fragment (' + response.status + ')');
            }
            return response.text();
        })
        .then(function(html) {
            currentTab = document.getElementById('visadocuments-tab');
            if (!currentTab || !currentTab.parentNode) {
                throw new Error('Visa Documents tab element no longer in document');
            }
            var doc = new DOMParser().parseFromString(html, 'text/html');
            var parsedTab = doc.querySelector('#visadocuments-tab');
            if (!parsedTab) {
                throw new Error('Visa Documents tab fragment not found in response');
            }
            var newTab = importTabFragment(parsedTab);
            if (!newTab) {
                throw new Error('Failed to import Visa Documents tab fragment');
            }
            newTab.removeAttribute('data-visadocuments-lazy');
            newTab.removeAttribute('data-visadocuments-loading');
            if (!newTab.getAttribute('data-visadocuments-url') && url) {
                newTab.setAttribute('data-visadocuments-url', url);
            }
            if (wasActive || currentTab.classList.contains('active')) {
                newTab.classList.add('active');
            }
            currentTab.replaceWith(newTab);
            return activateInjectedScripts(newTab)
                .then(function() {
                    return importVisaDocumentsOrphanAssets(doc, newTab);
                })
                .then(function() {
                    initVisaDocumentsTabAfterInject(newTab);
                    return newTab;
                });
        })
        .catch(function(err) {
            var tab = document.getElementById('visadocuments-tab');
            if (tab) {
                tab.removeAttribute('data-visadocuments-loading');
            }
            showVisaDocumentsLazyError('Failed to load visa documents. Please refresh the page.');
            throw err;
        })
        .finally(function() {
            visaDocumentsTabLoadPromise = null;
        });

        return visaDocumentsTabLoadPromise;
    }

    function bootVisaDocumentsTabIfNeeded() {
        var tab = document.getElementById('visadocuments-tab');
        if (!tab) {
            return;
        }
        var activeNav = document.querySelector('.client-nav-button.active');
        var activeTab = (activeNav && activeNav.getAttribute('data-tab'))
            || (window.ClientDetailConfig && window.ClientDetailConfig.activeTab)
            || '';
        var path = window.location.pathname || '';
        if (activeTab !== 'visadocuments' && !/\/visadocuments\/?$/.test(path)) {
            return;
        }
        ensureVisaDocumentsTabLoaded().catch(function(err) {
            console.error('[VisaDocuments] Boot load failed', err);
        });
    }

    window.ensureVisaDocumentsTabLoaded = ensureVisaDocumentsTabLoaded;
    window.initVisaDocumentsTabAfterInject = initVisaDocumentsTabAfterInject;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            bootVisaDocumentsTabIfNeeded();
            setTimeout(bootVisaDocumentsTabIfNeeded, 300);
        });
    } else {
        bootVisaDocumentsTabIfNeeded();
        setTimeout(bootVisaDocumentsTabIfNeeded, 300);
    }
})();
