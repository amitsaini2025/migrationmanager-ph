/**
 * Client Detail — Checklists tab lazy fragment loader.
 * Stub on first paint; load once on click; re-run Create checklist / cost-assignment
 * bindings and mmSelect staff dropdowns after inject.
 * Cost-assignment modals stay on the detail page.
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
        var container = tab.querySelector('.card') || tab;
        container.innerHTML = '<div class="workflow-v2-empty" data-checklists-lazy-placeholder style="padding: 24px; color: #6c757d;">' +
            (message || 'Failed to load checklists. Please refresh the page.') +
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
     * Accordion card CSS and bindChecklistsTabUi live outside #checklists-tab
     * in the fragment — import them once so lazy load matches eager layout/UX.
     */
    function importChecklistsOrphanAssets(doc, afterEl) {
        return new Promise(function(resolve) {
            if (!doc || !doc.body || !afterEl || !afterEl.parentNode) {
                resolve();
                return;
            }

            if (!document.querySelector('style[data-checklists-fragment-style]')) {
                Array.prototype.slice.call(doc.querySelectorAll('style')).forEach(function(styleEl) {
                    var text = styleEl.textContent || '';
                    if (!text.trim()) {
                        return;
                    }
                    var s = document.createElement('style');
                    s.setAttribute('data-checklists-fragment-style', '1');
                    s.textContent = text;
                    document.head.appendChild(s);
                });
            }

            var insertAfter = afterEl;
            var scriptHosts = [];

            Array.prototype.forEach.call(doc.body.children, function(child) {
                if (child.id === 'checklists-tab') {
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

            if (window.__checklistsOrphanScriptsLoaded) {
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
                window.__checklistsOrphanScriptsLoaded = true;
                resolve();
            }).catch(function(err) {
                console.error('[Checklists] Orphan script activate failed', err);
                resolve();
            });
        });
    }

    function initChecklistsTabAfterInject(tabEl) {
        if (typeof refreshLucideIcons === 'function' && tabEl) {
            refreshLucideIcons(tabEl);
        }
        if (typeof window.bindChecklistsTabUi === 'function') {
            window.bindChecklistsTabUi();
        }
    }

    function needsFragmentLoad(tabEl, force) {
        if (!tabEl) {
            return false;
        }
        if (force === true) {
            return true;
        }
        return tabEl.getAttribute('data-checklists-lazy') === '1'
            || !!tabEl.querySelector('[data-checklists-lazy-placeholder]');
    }

    function ensureChecklistsTabLoaded(force) {
        var currentTab = document.getElementById('checklists-tab');
        if (!currentTab) {
            return Promise.resolve(null);
        }

        if (!needsFragmentLoad(currentTab, force)) {
            initChecklistsTabAfterInject(currentTab);
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
                throw new Error('Failed to load Checklists tab fragment (' + response.status + ')');
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
                throw new Error('Failed to import Checklists tab fragment');
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
            return activateInjectedScripts(newTab)
                .then(function() {
                    return importChecklistsOrphanAssets(doc, newTab);
                })
                .then(function() {
                    initChecklistsTabAfterInject(newTab);
                    return newTab;
                });
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
        var activeNav = document.querySelector('.client-nav-button.active');
        var activeTab = (activeNav && activeNav.getAttribute('data-tab'))
            || (window.ClientDetailConfig && window.ClientDetailConfig.activeTab)
            || '';
        var path = window.location.pathname || '';
        if (activeTab !== 'checklists' && !/\/checklists\/?$/.test(path)) {
            return;
        }
        ensureChecklistsTabLoaded().catch(function(err) {
            console.error('[Checklists] Boot load failed', err);
        });
    }

    window.ensureChecklistsTabLoaded = ensureChecklistsTabLoaded;
    window.initChecklistsTabAfterInject = initChecklistsTabAfterInject;

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
