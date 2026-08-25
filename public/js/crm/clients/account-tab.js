/**
 * Client Detail — Account tab lazy fragment loader.
 * Stub on first paint; load once on click; re-run invoice/ledger init after inject.
 * Create-receipt modal (#createreceiptmodal) stays on the detail page.
 */
(function() {
    'use strict';

    var accountTabLoadPromise = null;

    function accountTabFragmentUrl(tabEl) {
        var el = tabEl || document.getElementById('account-tab');
        if (el && el.getAttribute('data-account-url')) {
            return el.getAttribute('data-account-url');
        }
        var urls = (window.ClientDetailConfig && window.ClientDetailConfig.urls) || {};
        if (urls.accountTab) {
            return urls.accountTab;
        }
        var encodeId = window.ClientDetailConfig && window.ClientDetailConfig.encodeId;
        if (!encodeId) {
            return '';
        }
        var matterRef = (window.ClientDetailConfig.matterId || window.ClientDetailConfig.matterRefNo || '');
        var url = '/clients/detail-account-tab/' + encodeURIComponent(encodeId);
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

    function showAccountLazyError(message) {
        var tab = document.getElementById('account-tab');
        if (!tab) {
            return;
        }
        var placeholder = tab.querySelector('[data-account-lazy-placeholder]');
        if (placeholder) {
            placeholder.textContent = message || 'Failed to load account. Please refresh the page.';
            return;
        }
        var container = tab.querySelector('.card') || tab;
        container.innerHTML = '<div class="workflow-v2-empty" data-account-lazy-placeholder style="padding: 24px; color: #6c757d;">' +
            (message || 'Failed to load account. Please refresh the page.') +
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

    function selectedMatterId() {
        if (typeof $ === 'undefined') {
            return '';
        }
        if ($('.general_matter_checkbox_client_detail').is(':checked')) {
            return $('.general_matter_checkbox_client_detail').val();
        }
        return $('#sel_matter_id_client_detail').val() || '';
    }

    function initAccountTabAfterInject(tabEl) {
        if (typeof refreshLucideIcons === 'function' && tabEl) {
            refreshLucideIcons(tabEl);
        }
        if (typeof window.listOfInvoice === 'function') {
            window.listOfInvoice();
        }
        if (typeof window.clientLedgerBalanceAmount === 'function') {
            window.clientLedgerBalanceAmount(selectedMatterId());
        }
    }

    function needsFragmentLoad(tabEl, force) {
        if (!tabEl) {
            return false;
        }
        if (force === true) {
            return true;
        }
        return tabEl.getAttribute('data-account-lazy') === '1'
            || !!tabEl.querySelector('[data-account-lazy-placeholder]');
    }

    function ensureAccountTabLoaded(force) {
        var currentTab = document.getElementById('account-tab');
        if (!currentTab) {
            return Promise.resolve(null);
        }

        if (!needsFragmentLoad(currentTab, force)) {
            initAccountTabAfterInject(currentTab);
            return Promise.resolve(currentTab);
        }

        if (accountTabLoadPromise) {
            return accountTabLoadPromise;
        }

        var url = accountTabFragmentUrl(currentTab);
        if (!url) {
            showAccountLazyError('Account URL is missing. Please refresh the page.');
            return Promise.reject(new Error('Account fragment URL missing'));
        }

        currentTab.setAttribute('data-account-loading', '1');
        var wasActive = currentTab.classList.contains('active');

        accountTabLoadPromise = fetch(url, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'text/html'
            },
            credentials: 'same-origin'
        })
        .then(function(response) {
            if (!response.ok) {
                throw new Error('Failed to load Account tab fragment (' + response.status + ')');
            }
            return response.text();
        })
        .then(function(html) {
            currentTab = document.getElementById('account-tab');
            if (!currentTab || !currentTab.parentNode) {
                throw new Error('Account tab element no longer in document');
            }
            var doc = new DOMParser().parseFromString(html, 'text/html');
            var parsedTab = doc.querySelector('#account-tab');
            if (!parsedTab) {
                throw new Error('Account tab fragment not found in response');
            }
            var newTab = importTabFragment(parsedTab);
            if (!newTab) {
                throw new Error('Failed to import Account tab fragment');
            }
            newTab.removeAttribute('data-account-lazy');
            newTab.removeAttribute('data-account-loading');
            if (!newTab.getAttribute('data-account-url') && url) {
                newTab.setAttribute('data-account-url', url);
            }
            if (wasActive || currentTab.classList.contains('active')) {
                newTab.classList.add('active');
            }
            currentTab.replaceWith(newTab);
            return activateInjectedScripts(newTab).then(function() {
                initAccountTabAfterInject(newTab);
                return newTab;
            });
        })
        .catch(function(err) {
            var tab = document.getElementById('account-tab');
            if (tab) {
                tab.removeAttribute('data-account-loading');
            }
            showAccountLazyError('Failed to load account. Please refresh the page.');
            throw err;
        })
        .finally(function() {
            accountTabLoadPromise = null;
        });

        return accountTabLoadPromise;
    }

    function bootAccountTabIfNeeded() {
        var tab = document.getElementById('account-tab');
        if (!tab) {
            return;
        }
        var activeNav = document.querySelector('.client-nav-button.active');
        var activeTab = (activeNav && activeNav.getAttribute('data-tab'))
            || (window.ClientDetailConfig && window.ClientDetailConfig.activeTab)
            || '';
        var path = window.location.pathname || '';
        if (activeTab !== 'account' && !/\/account\/?$/.test(path)) {
            return;
        }
        ensureAccountTabLoaded().catch(function(err) {
            console.error('[Account] Boot load failed', err);
        });
    }

    window.ensureAccountTabLoaded = ensureAccountTabLoaded;
    window.initAccountTabAfterInject = initAccountTabAfterInject;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            bootAccountTabIfNeeded();
            setTimeout(bootAccountTabIfNeeded, 300);
        });
    } else {
        bootAccountTabIfNeeded();
        setTimeout(bootAccountTabIfNeeded, 300);
    }
})();
