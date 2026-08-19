/**
 * Client Detail — Emails tab lazy fragment loader.
 * Stub on first paint; load the shell once on click, then cache the list.
 * Compose (#emailmodal), folders, and matter filter stay on existing JS.
 */
(function() {
    'use strict';

    var emailsTabLoadPromise = null;

    function emailsTabFragmentUrl(tabEl) {
        var el = tabEl || document.getElementById('emails-tab');
        if (el && el.getAttribute('data-emails-url')) {
            return el.getAttribute('data-emails-url');
        }
        var urls = (window.ClientDetailConfig && window.ClientDetailConfig.urls) || {};
        if (urls.emailsTab) {
            return urls.emailsTab;
        }
        var encodeId = window.ClientDetailConfig && window.ClientDetailConfig.encodeId;
        if (!encodeId) {
            return '';
        }
        var matterRef = (window.ClientDetailConfig.matterId || window.ClientDetailConfig.matterRefNo || '');
        var url = '/clients/detail-emails-tab/' + encodeURIComponent(encodeId);
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

    function showEmailsLazyError(message) {
        var tab = document.getElementById('emails-tab');
        if (!tab) {
            return;
        }
        var placeholder = tab.querySelector('[data-emails-lazy-placeholder]');
        if (placeholder) {
            placeholder.textContent = message || 'Failed to load emails. Please refresh the page.';
            return;
        }
        var container = tab.querySelector('.email-interface-container') || tab.querySelector('.card') || tab;
        container.innerHTML = '<div class="workflow-v2-empty" data-emails-lazy-placeholder style="padding: 24px; color: #6c757d;">' +
            (message || 'Failed to load emails. Please refresh the page.') +
            '</div>';
    }

    function isEmailsJsSrc(src) {
        return typeof src === 'string' && /\/emails\.js(\?|$)/.test(src);
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
                if (isEmailsJsSrc(oldScript.src) && typeof window.loadEmails === 'function') {
                    if (oldScript.parentNode) {
                        oldScript.parentNode.removeChild(oldScript);
                    }
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

    function waitForLoadEmails(timeoutMs) {
        return new Promise(function(resolve) {
            if (typeof window.loadEmails === 'function') {
                resolve();
                return;
            }
            var start = Date.now();
            var timer = setInterval(function() {
                if (typeof window.loadEmails === 'function' || Date.now() - start > timeoutMs) {
                    clearInterval(timer);
                    resolve();
                }
            }, 40);
        });
    }

    function requestEmailsListOnce() {
        if (typeof window.loadEmails === 'function') {
            window.loadEmails({ once: true });
        }
    }

    function initEmailsTabAfterInject(tabEl) {
        return activateInjectedScripts(tabEl).then(function() {
            if (typeof refreshLucideIcons === 'function' && tabEl) {
                refreshLucideIcons(tabEl);
            }
            return waitForLoadEmails(8000);
        }).then(function() {
            requestEmailsListOnce();
        });
    }

    function ensureEmailsTabLoaded(force) {
        var currentTab = document.getElementById('emails-tab');
        if (!currentTab) {
            return Promise.resolve(null);
        }

        var needsLoad = force === true
            || currentTab.getAttribute('data-emails-lazy') === '1'
            || !!currentTab.querySelector('[data-emails-lazy-placeholder]');

        if (!needsLoad) {
            requestEmailsListOnce();
            return Promise.resolve(currentTab);
        }

        if (emailsTabLoadPromise) {
            return emailsTabLoadPromise;
        }

        var url = emailsTabFragmentUrl(currentTab);
        if (!url) {
            showEmailsLazyError('Emails URL is missing. Please refresh the page.');
            return Promise.reject(new Error('Emails fragment URL missing'));
        }

        currentTab.setAttribute('data-emails-loading', '1');
        var wasActive = currentTab.classList.contains('active');

        emailsTabLoadPromise = fetch(url, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'text/html'
            },
            credentials: 'same-origin'
        })
        .then(function(response) {
            if (!response.ok) {
                throw new Error('Failed to load emails tab fragment (' + response.status + ')');
            }
            return response.text();
        })
        .then(function(html) {
            currentTab = document.getElementById('emails-tab');
            if (!currentTab || !currentTab.parentNode) {
                throw new Error('Emails tab element no longer in document');
            }
            var doc = new DOMParser().parseFromString(html, 'text/html');
            var parsedTab = doc.querySelector('#emails-tab');
            if (!parsedTab) {
                throw new Error('Emails tab fragment not found in response');
            }
            var newTab = importTabFragment(parsedTab);
            if (!newTab) {
                throw new Error('Failed to import emails tab fragment');
            }
            newTab.removeAttribute('data-emails-lazy');
            newTab.removeAttribute('data-emails-loading');
            if (!newTab.getAttribute('data-emails-url') && url) {
                newTab.setAttribute('data-emails-url', url);
            }
            if (wasActive || currentTab.classList.contains('active')) {
                newTab.classList.add('active');
            }
            currentTab.replaceWith(newTab);
            return initEmailsTabAfterInject(newTab).then(function() {
                return newTab;
            });
        })
        .catch(function(err) {
            var tab = document.getElementById('emails-tab');
            if (tab) {
                tab.removeAttribute('data-emails-loading');
            }
            showEmailsLazyError('Failed to load emails. Please refresh the page.');
            throw err;
        })
        .finally(function() {
            emailsTabLoadPromise = null;
        });

        return emailsTabLoadPromise;
    }

    function bootEmailsTabIfNeeded() {
        var tab = document.getElementById('emails-tab');
        if (!tab) {
            return;
        }
        var activeNav = document.querySelector('.client-nav-button.active');
        var activeTab = (activeNav && activeNav.getAttribute('data-tab'))
            || (window.ClientDetailConfig && window.ClientDetailConfig.activeTab)
            || '';
        var path = window.location.pathname || '';
        var isEmailsActive = activeTab === 'emails' || /\/emails\/?$/.test(path);
        if (!isEmailsActive) {
            return;
        }
        ensureEmailsTabLoaded().catch(function(err) {
            console.error('[EmailsTab] Boot load failed', err);
        });
    }

    window.ensureEmailsTabLoaded = ensureEmailsTabLoaded;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            bootEmailsTabIfNeeded();
            setTimeout(bootEmailsTabIfNeeded, 300);
        });
    } else {
        bootEmailsTabIfNeeded();
        setTimeout(bootEmailsTabIfNeeded, 300);
    }
})();
