/**
 * Client Detail — Personal Details tab lazy fragment loader.
 * Eager when that tab is the URL; stub otherwise. Load once on click / deep-link.
 */
(function() {
    'use strict';

    var personalDetailsTabLoadPromise = null;

    function personalDetailsTabFragmentUrl(tabEl) {
        var el = tabEl || document.getElementById('personaldetails-tab');
        if (el && el.getAttribute('data-personaldetails-url')) {
            return el.getAttribute('data-personaldetails-url');
        }
        var urls = (window.ClientDetailConfig && window.ClientDetailConfig.urls) || {};
        if (urls.personalDetailsTab) {
            return urls.personalDetailsTab;
        }
        var encodeId = window.ClientDetailConfig && window.ClientDetailConfig.encodeId;
        if (!encodeId) {
            return '';
        }
        var matterRef = (window.ClientDetailConfig.matterId || window.ClientDetailConfig.matterRefNo || '');
        var url = '/clients/detail-personaldetails-tab/' + encodeURIComponent(encodeId);
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

    function showPersonalDetailsLazyError(message) {
        var tab = document.getElementById('personaldetails-tab');
        if (!tab) {
            return;
        }
        var placeholder = tab.querySelector('[data-personaldetails-lazy-placeholder]');
        if (placeholder) {
            placeholder.textContent = message || 'Failed to load personal details. Please refresh the page.';
            return;
        }
        var container = tab.querySelector('.card') || tab;
        container.innerHTML = '<div class="workflow-v2-empty" data-personaldetails-lazy-placeholder style="padding: 24px; color: #6c757d;">' +
            (message || 'Failed to load personal details. Please refresh the page.') +
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

    function initPersonalDetailsTabAfterInject(tabEl) {
        if (typeof refreshLucideIcons === 'function' && tabEl) {
            refreshLucideIcons(tabEl);
        }
        if (typeof window.bindNavButtons === 'function') {
            window.bindNavButtons(tabEl);
        } else if (window.SidebarTabs && typeof window.SidebarTabs.bindNavButtons === 'function') {
            window.SidebarTabs.bindNavButtons(tabEl);
        }
        if (typeof adjustActivityFeedHeight === 'function') {
            adjustActivityFeedHeight();
        }
    }

    function needsFragmentLoad(tabEl, force) {
        if (!tabEl) {
            return false;
        }
        if (force === true) {
            return true;
        }
        return tabEl.getAttribute('data-personaldetails-lazy') === '1'
            || !!tabEl.querySelector('[data-personaldetails-lazy-placeholder]');
    }

    function ensurePersonalDetailsTabLoaded(force) {
        var currentTab = document.getElementById('personaldetails-tab');
        if (!currentTab) {
            return Promise.resolve(null);
        }

        if (!needsFragmentLoad(currentTab, force)) {
            initPersonalDetailsTabAfterInject(currentTab);
            return Promise.resolve(currentTab);
        }

        if (personalDetailsTabLoadPromise) {
            return personalDetailsTabLoadPromise;
        }

        var url = personalDetailsTabFragmentUrl(currentTab);
        if (!url) {
            showPersonalDetailsLazyError('Personal details URL is missing. Please refresh the page.');
            return Promise.reject(new Error('Personal details fragment URL missing'));
        }

        currentTab.setAttribute('data-personaldetails-loading', '1');
        var wasActive = currentTab.classList.contains('active');

        personalDetailsTabLoadPromise = fetch(url, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'text/html'
            },
            credentials: 'same-origin'
        })
        .then(function(response) {
            if (!response.ok) {
                throw new Error('Failed to load Personal Details tab fragment (' + response.status + ')');
            }
            return response.text();
        })
        .then(function(html) {
            currentTab = document.getElementById('personaldetails-tab');
            if (!currentTab || !currentTab.parentNode) {
                throw new Error('Personal Details tab element no longer in document');
            }
            var doc = new DOMParser().parseFromString(html, 'text/html');
            var parsedTab = doc.querySelector('#personaldetails-tab');
            if (!parsedTab) {
                throw new Error('Personal Details tab fragment not found in response');
            }
            var newTab = importTabFragment(parsedTab);
            if (!newTab) {
                throw new Error('Failed to import Personal Details tab fragment');
            }
            newTab.removeAttribute('data-personaldetails-lazy');
            newTab.removeAttribute('data-personaldetails-loading');
            if (!newTab.getAttribute('data-personaldetails-url') && url) {
                newTab.setAttribute('data-personaldetails-url', url);
            }
            if (wasActive || currentTab.classList.contains('active')) {
                newTab.classList.add('active');
            } else {
                newTab.classList.remove('active');
            }
            currentTab.replaceWith(newTab);
            return activateInjectedScripts(newTab).then(function() {
                initPersonalDetailsTabAfterInject(newTab);
                return newTab;
            });
        })
        .catch(function(err) {
            var tab = document.getElementById('personaldetails-tab');
            if (tab) {
                tab.removeAttribute('data-personaldetails-loading');
            }
            showPersonalDetailsLazyError('Failed to load personal details. Please refresh the page.');
            throw err;
        })
        .finally(function() {
            personalDetailsTabLoadPromise = null;
        });

        return personalDetailsTabLoadPromise;
    }

    function bootPersonalDetailsTabIfNeeded() {
        var tab = document.getElementById('personaldetails-tab');
        if (!tab) {
            return;
        }
        var activeNav = document.querySelector('.client-nav-button.active');
        var activeTab = (activeNav && activeNav.getAttribute('data-tab'))
            || (window.ClientDetailConfig && window.ClientDetailConfig.activeTab)
            || '';
        var path = window.location.pathname || '';
        if (activeTab !== 'personaldetails' && !/\/personaldetails\/?$/.test(path)) {
            return;
        }
        ensurePersonalDetailsTabLoaded().catch(function(err) {
            console.error('[Personal Details] Boot load failed', err);
        });
    }

    window.ensurePersonalDetailsTabLoaded = ensurePersonalDetailsTabLoaded;
    window.initPersonalDetailsTabAfterInject = initPersonalDetailsTabAfterInject;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            bootPersonalDetailsTabIfNeeded();
            setTimeout(bootPersonalDetailsTabIfNeeded, 300);
        });
    } else {
        bootPersonalDetailsTabIfNeeded();
        setTimeout(bootPersonalDetailsTabIfNeeded, 300);
    }
})();
