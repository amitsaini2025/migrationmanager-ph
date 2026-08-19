/**
 * Client Detail — Personal / Visa / Not Used document tab lazy loaders.
 * Stub on first paint; load once on click; re-bind upload/rename/preview/context
 * menus after inject. Matter change loads the tab first, then filters rows.
 */
(function() {
    'use strict';

    var TAB_SPECS = {
        personaldocuments: {
            paneId: 'personaldocuments-tab',
            attr: 'personaldocuments',
            urlKey: 'personalDocumentsTab',
            path: 'detail-personal-documents-tab',
            label: 'Personal Documents'
        },
        visadocuments: {
            paneId: 'visadocuments-tab',
            attr: 'visadocuments',
            urlKey: 'visaDocumentsTab',
            path: 'detail-visa-documents-tab',
            label: 'Visa Documents'
        },
        notuseddocuments: {
            paneId: 'notuseddocuments-tab',
            attr: 'notuseddocuments',
            urlKey: 'notUsedDocumentsTab',
            path: 'detail-not-used-documents-tab',
            label: 'Not Used Documents'
        }
    };

    var loadPromises = {};

    function tabSpec(tabId) {
        return TAB_SPECS[tabId] || null;
    }

    function fragmentUrl(tabId, tabEl) {
        var spec = tabSpec(tabId);
        if (!spec) {
            return '';
        }
        var el = tabEl || document.getElementById(spec.paneId);
        var attr = 'data-' + spec.attr + '-url';
        if (el && el.getAttribute(attr)) {
            return el.getAttribute(attr);
        }
        var urls = (window.ClientDetailConfig && window.ClientDetailConfig.urls) || {};
        if (urls[spec.urlKey]) {
            return urls[spec.urlKey];
        }
        var encodeId = window.ClientDetailConfig && window.ClientDetailConfig.encodeId;
        if (!encodeId) {
            return '';
        }
        var matterRef = (window.ClientDetailConfig.matterId || window.ClientDetailConfig.matterRefNo || '');
        var url = '/clients/' + spec.path + '/' + encodeURIComponent(encodeId);
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

    function showLazyError(tabId, message) {
        var spec = tabSpec(tabId);
        if (!spec) {
            return;
        }
        var tab = document.getElementById(spec.paneId);
        if (!tab) {
            return;
        }
        var placeholderSel = '[data-' + spec.attr + '-lazy-placeholder]';
        var placeholder = tab.querySelector(placeholderSel);
        var text = message || ('Failed to load ' + spec.label.toLowerCase() + '. Please refresh the page.');
        if (placeholder) {
            placeholder.textContent = text;
            return;
        }
        var container = tab.querySelector('.documentalls-container') || tab.querySelector('.card') || tab;
        container.innerHTML = '<div class="workflow-v2-empty" data-' + spec.attr + '-lazy-placeholder style="padding: 24px; color: #6c757d;">' +
            text +
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

    function rebindSidebarNavButtons() {
        if (typeof window.SidebarTabs !== 'undefined' && typeof window.SidebarTabs.bindNavButtons === 'function') {
            window.SidebarTabs.bindNavButtons();
        }
    }

    function hideDocumentGridView(tabEl) {
        if (!tabEl) {
            return;
        }
        if (typeof $ !== 'undefined') {
            $(tabEl).find('.grid_data').hide();
            return;
        }
        Array.prototype.slice.call(tabEl.querySelectorAll('.grid_data')).forEach(function(grid) {
            grid.style.display = 'none';
        });
    }

    function initAfterInject(tabId, tabEl) {
        if (typeof refreshLucideIcons === 'function' && tabEl) {
            refreshLucideIcons(tabEl);
        }
        hideDocumentGridView(tabEl);
        rebindSidebarNavButtons();
        if (tabId === 'personaldocuments' && typeof window.initPersonalDocDragDrop === 'function') {
            window.initPersonalDocDragDrop();
        }
        if (tabId === 'visadocuments' && typeof window.initVisaDocDragDrop === 'function') {
            window.initVisaDocDragDrop();
        }
        if (tabId === 'visadocuments' && typeof window.SidebarTabs !== 'undefined' && typeof window.SidebarTabs.filterVisaDocumentsByMatter === 'function') {
            var matterId = '';
            if (typeof $ !== 'undefined') {
                if ($('.general_matter_checkbox_client_detail').is(':checked')) {
                    matterId = $('.general_matter_checkbox_client_detail').val();
                } else {
                    matterId = $('#sel_matter_id_client_detail').val();
                }
            }
            window.SidebarTabs.filterVisaDocumentsByMatter(matterId || window.SidebarTabs.selectedMatter || '');
        }
    }

    function needsFragmentLoad(tabId, tabEl, force) {
        var spec = tabSpec(tabId);
        if (!spec || !tabEl) {
            return false;
        }
        if (force === true) {
            return true;
        }
        return tabEl.getAttribute('data-' + spec.attr + '-lazy') === '1'
            || !!tabEl.querySelector('[data-' + spec.attr + '-lazy-placeholder]');
    }

    function rebindClientDocumentTab(tabId, tabEl) {
        var spec = tabSpec(tabId);
        var el = tabEl || (spec && document.getElementById(spec.paneId));
        if (!el) {
            return Promise.resolve(null);
        }
        return activateInjectedScripts(el).then(function() {
            initAfterInject(tabId, el);
            return el;
        });
    }

    function ensureDocumentTabLoaded(tabId, force) {
        var spec = tabSpec(tabId);
        if (!spec) {
            return Promise.resolve(null);
        }
        var currentTab = document.getElementById(spec.paneId);
        if (!currentTab) {
            return Promise.resolve(null);
        }

        if (!needsFragmentLoad(tabId, currentTab, force)) {
            initAfterInject(tabId, currentTab);
            return Promise.resolve(currentTab);
        }

        if (loadPromises[tabId]) {
            return loadPromises[tabId];
        }

        var url = fragmentUrl(tabId, currentTab);
        if (!url) {
            showLazyError(tabId, spec.label + ' URL is missing. Please refresh the page.');
            return Promise.reject(new Error(spec.label + ' fragment URL missing'));
        }

        currentTab.setAttribute('data-' + spec.attr + '-loading', '1');
        var wasActive = currentTab.classList.contains('active');

        loadPromises[tabId] = fetch(url, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'text/html'
            },
            credentials: 'same-origin'
        })
        .then(function(response) {
            if (!response.ok) {
                throw new Error('Failed to load ' + spec.label + ' tab fragment (' + response.status + ')');
            }
            return response.text();
        })
        .then(function(html) {
            currentTab = document.getElementById(spec.paneId);
            if (!currentTab || !currentTab.parentNode) {
                throw new Error(spec.label + ' tab element no longer in document');
            }
            var doc = new DOMParser().parseFromString(html, 'text/html');
            var parsedTab = doc.querySelector('#' + spec.paneId);
            if (!parsedTab) {
                throw new Error(spec.label + ' tab fragment not found in response');
            }
            var newTab = importTabFragment(parsedTab);
            if (!newTab) {
                throw new Error('Failed to import ' + spec.label + ' tab fragment');
            }
            newTab.removeAttribute('data-' + spec.attr + '-lazy');
            newTab.removeAttribute('data-' + spec.attr + '-loading');
            if (!newTab.getAttribute('data-' + spec.attr + '-url') && url) {
                newTab.setAttribute('data-' + spec.attr + '-url', url);
            }
            if (wasActive || currentTab.classList.contains('active')) {
                newTab.classList.add('active');
            }
            currentTab.replaceWith(newTab);
            return rebindClientDocumentTab(tabId, newTab).then(function() {
                return newTab;
            });
        })
        .catch(function(err) {
            var tab = document.getElementById(spec.paneId);
            if (tab) {
                tab.removeAttribute('data-' + spec.attr + '-loading');
            }
            showLazyError(tabId, 'Failed to load ' + spec.label.toLowerCase() + '. Please refresh the page.');
            throw err;
        })
        .finally(function() {
            loadPromises[tabId] = null;
        });

        return loadPromises[tabId];
    }

    function bootDocumentTabIfNeeded(tabId) {
        var spec = tabSpec(tabId);
        if (!spec) {
            return;
        }
        var tab = document.getElementById(spec.paneId);
        if (!tab) {
            return;
        }
        var activeNav = document.querySelector('.client-nav-button.active');
        var activeTab = (activeNav && activeNav.getAttribute('data-tab'))
            || (window.ClientDetailConfig && window.ClientDetailConfig.activeTab)
            || '';
        var path = window.location.pathname || '';
        var pathRe = new RegExp('\\/' + tabId + '\\/?$');
        if (activeTab !== tabId && !pathRe.test(path)) {
            return;
        }
        ensureDocumentTabLoaded(tabId).catch(function(err) {
            console.error('[' + spec.label + '] Boot load failed', err);
        });
    }

    function ensureClientDetailDocumentTabLoaded(tabId) {
        if (!TAB_SPECS[tabId]) {
            return Promise.resolve(null);
        }
        return ensureDocumentTabLoaded(tabId);
    }

    window.ensurePersonalDocumentsTabLoaded = function(force) {
        return ensureDocumentTabLoaded('personaldocuments', force);
    };
    window.ensureVisaDocumentsTabLoaded = function(force) {
        return ensureDocumentTabLoaded('visadocuments', force);
    };
    window.ensureNotUsedDocumentsTabLoaded = function(force) {
        return ensureDocumentTabLoaded('notuseddocuments', force);
    };
    window.ensureClientDetailDocumentTabLoaded = ensureClientDetailDocumentTabLoaded;
    window.rebindClientDocumentTab = rebindClientDocumentTab;

    function bootAllDocumentTabsIfNeeded() {
        bootDocumentTabIfNeeded('personaldocuments');
        bootDocumentTabIfNeeded('visadocuments');
        bootDocumentTabIfNeeded('notuseddocuments');
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            bootAllDocumentTabsIfNeeded();
            setTimeout(bootAllDocumentTabsIfNeeded, 300);
        });
    } else {
        bootAllDocumentTabsIfNeeded();
        setTimeout(bootAllDocumentTabsIfNeeded, 300);
    }
})();
