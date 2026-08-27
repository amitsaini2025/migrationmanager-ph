/**
 * Client Detail — fetch-then-init for always-on modals.
 * Keeps the same modal IDs. First click waits for HTML, then replays so existing
 * handlers can fill fields and call .modal('show'). Company/lead pages do not load this file.
 */
(function() {
    'use strict';

    var packPromises = {};
    var packLoaded = {};
    var replayingClick = false;
    var modalPatchInstalled = false;

    function config() {
        return (window.ClientDetailConfig && window.ClientDetailConfig.lazyModals) || null;
    }

    function enabled() {
        var cfg = config();
        return !!(cfg && cfg.enabled);
    }

    function packMeta(name) {
        var cfg = config();
        return (cfg && cfg.packs && cfg.packs[name]) || null;
    }

    function isLazyEl(el) {
        return !!(el && el.getAttribute && el.getAttribute('data-lazy-modal') === '1');
    }

    function packForEl(el) {
        if (!el || !el.getAttribute) {
            return null;
        }
        return el.getAttribute('data-lazy-pack') || null;
    }

    function packForModalId(id) {
        if (!id) {
            return null;
        }
        var cfg = config();
        if (!cfg || !cfg.packs) {
            return null;
        }
        var names = Object.keys(cfg.packs);
        for (var i = 0; i < names.length; i++) {
            var ids = cfg.packs[names[i]].ids || [];
            if (ids.indexOf(id) !== -1) {
                return names[i];
            }
        }
        return null;
    }

    function importNode(node) {
        if (!node) {
            return null;
        }
        try {
            return document.importNode(node, true);
        } catch (err) {
            return node;
        }
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
                    if (oldScript.parentNode) {
                        oldScript.parentNode.replaceChild(s, oldScript);
                    } else {
                        document.body.appendChild(s);
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
                    document.body.appendChild(s);
                }
                next();
            }

            next();
        });
    }

    function fillExistingModal(existing, incoming) {
        if (!existing || !incoming) {
            return;
        }
        Array.prototype.slice.call(incoming.attributes || []).forEach(function(attr) {
            if (attr.name === 'id' || attr.name === 'data-lazy-modal' || attr.name === 'data-lazy-pack' || attr.name === 'data-lazy-class') {
                return;
            }
            existing.setAttribute(attr.name, attr.value);
        });
        existing.className = incoming.className || existing.className;
        existing.innerHTML = incoming.innerHTML;
        existing.removeAttribute('data-lazy-modal');
        existing.removeAttribute('data-lazy-pack');
        existing.removeAttribute('data-lazy-class');
        existing.removeAttribute('aria-hidden');
    }

    function findStubForIncoming(incoming) {
        var id = incoming.id;
        if (id) {
            return document.getElementById(id);
        }
        var classList = incoming.className ? String(incoming.className).split(/\s+/) : [];
        for (var i = 0; i < classList.length; i++) {
            var name = classList[i];
            if (!name || name === 'modal' || name === 'fade' || name === 'custom_modal' || name === 'show') {
                continue;
            }
            var stub = document.querySelector('[data-lazy-class="' + name + '"]');
            if (stub) {
                return stub;
            }
            stub = document.querySelector('.' + name + '[data-lazy-modal="1"]');
            if (stub) {
                return stub;
            }
        }
        return null;
    }

    function injectPackHtml(html) {
        var doc = new DOMParser().parseFromString('<div id="client-detail-lazy-pack-root">' + html + '</div>', 'text/html');
        var root = doc.getElementById('client-detail-lazy-pack-root') || doc.body;
        var incomingModals = Array.prototype.slice.call(root.querySelectorAll('.modal'));
        var leftovers = [];

        incomingModals.forEach(function(incoming) {
            var existing = findStubForIncoming(incoming);
            if (existing) {
                fillExistingModal(existing, incoming);
            } else {
                leftovers.push(incoming);
            }
        });

        var host = document.getElementById('client-detail-lazy-pack-host');
        if (!host) {
            host = document.createElement('div');
            host.id = 'client-detail-lazy-pack-host';
            document.body.appendChild(host);
        }

        leftovers.forEach(function(incoming) {
            host.appendChild(importNode(incoming));
        });

        Array.prototype.slice.call(root.querySelectorAll('style, link[rel="stylesheet"]')).forEach(function(node) {
            host.appendChild(importNode(node));
        });

        var scriptHolder = document.createElement('div');
        Array.prototype.slice.call(root.querySelectorAll('script')).forEach(function(node) {
            scriptHolder.appendChild(importNode(node));
        });
        host.appendChild(scriptHolder);

        return activateInjectedScripts(scriptHolder).then(function() {
            if (typeof refreshLucideIcons === 'function') {
                refreshLucideIcons(document.body);
            }
        });
    }

    function initAfterPack(name) {
        if (typeof window.initClientDetailShellModalWidgets === 'function') {
            window.initClientDetailShellModalWidgets();
        }
        if (typeof window.initTinyMCEForModals === 'function') {
            window.initTinyMCEForModals();
        }
        if (typeof window.refreshEmailFromSenders === 'function') {
            window.refreshEmailFromSenders();
        }
        if (typeof window.initReceiptModalDatepickers === 'function') {
            window.initReceiptModalDatepickers();
        }
    }

    function ensurePack(name) {
        if (!name) {
            return Promise.resolve();
        }
        if (packLoaded[name]) {
            return Promise.resolve();
        }
        if (packPromises[name]) {
            return packPromises[name];
        }

        var meta = packMeta(name);
        if (!meta || !meta.url) {
            return Promise.reject(new Error('Missing client-detail modal pack URL for ' + name));
        }

        packPromises[name] = fetch(meta.url, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'text/html'
            },
            credentials: 'same-origin'
        })
        .then(function(response) {
            if (!response.ok) {
                throw new Error('Failed to load ' + name + ' modals (' + response.status + ')');
            }
            return response.text();
        })
        .then(function(html) {
            return injectPackHtml(html);
        })
        .then(function() {
            packLoaded[name] = true;
            initAfterPack(name);
        })
        .catch(function(err) {
            packPromises[name] = null;
            console.error('[Client detail modals] Failed to load pack ' + name, err);
            throw err;
        });

        return packPromises[name];
    }

    function packNeededForClick(event) {
        var cfg = config();
        if (!cfg || !cfg.packs) {
            return null;
        }
        var target = event.target;
        if (!target || !target.closest) {
            return null;
        }

        var toggleEl = target.closest('[data-bs-target], [data-target]');
        if (toggleEl) {
            var sel = toggleEl.getAttribute('data-bs-target') || toggleEl.getAttribute('data-target') || '';
            var id = sel.charAt(0) === '#' ? sel.slice(1) : sel;
            var el = id ? document.getElementById(id) : null;
            if (isLazyEl(el)) {
                return packForEl(el) || packForModalId(id);
            }
        }

        var names = Object.keys(cfg.packs);
        for (var i = 0; i < names.length; i++) {
            if (packLoaded[names[i]]) {
                continue;
            }
            var triggers = cfg.packs[names[i]].triggers || [];
            for (var t = 0; t < triggers.length; t++) {
                try {
                    if (target.closest(triggers[t])) {
                        return names[i];
                    }
                } catch (err) {
                    // invalid selector
                }
            }
        }

        return null;
    }

    function replayClick(target) {
        replayingClick = true;
        try {
            var clickable = target.closest
                ? (target.closest('a, button, [data-bs-toggle], [onclick], [role="button"]') || target)
                : target;
            clickable.dispatchEvent(new MouseEvent('click', {
                bubbles: true,
                cancelable: true,
                composed: true,
                view: window
            }));
        } finally {
            replayingClick = false;
        }
    }

    function onCaptureClick(event) {
        if (!enabled() || replayingClick || event.button) {
            return;
        }
        var pack = packNeededForClick(event);
        if (!pack || packLoaded[pack]) {
            return;
        }
        event.preventDefault();
        event.stopImmediatePropagation();
        var target = event.target;
        ensurePack(pack).then(function() {
            replayClick(target);
        }).catch(function() {
            if (typeof iziToast !== 'undefined') {
                iziToast.error({
                    title: 'Error',
                    message: 'Could not load the form. Please refresh the page.',
                    position: 'topRight'
                });
            }
        });
    }

    function patchJqueryModal() {
        if (modalPatchInstalled || typeof window.jQuery === 'undefined') {
            return;
        }
        var $ = window.jQuery;
        if (!$ || !$.fn || typeof $.fn.modal !== 'function') {
            return;
        }
        modalPatchInstalled = true;
        var original = $.fn.modal;
        $.fn.modal = function(action) {
            if (!enabled() || action !== 'show' || !this.length) {
                return original.apply(this, arguments);
            }
            var el = this[0];
            if (!isLazyEl(el)) {
                return original.apply(this, arguments);
            }
            var pack = packForEl(el) || packForModalId(el.id);
            var args = arguments;
            var $ctx = this;
            ensurePack(pack).then(function() {
                var $ready = el.id ? $('#' + el.id) : $ctx;
                original.apply($ready, args);
            });
            return this;
        };
    }

    function patchBootstrapModal() {
        if (typeof window.bootstrap === 'undefined' || !window.bootstrap.Modal || !window.bootstrap.Modal.prototype) {
            return;
        }
        if (window.bootstrap.Modal.prototype.__mmLazyPatched) {
            return;
        }
        var originalShow = window.bootstrap.Modal.prototype.show;
        window.bootstrap.Modal.prototype.show = function() {
            var el = this._element;
            if (!enabled() || !isLazyEl(el)) {
                return originalShow.apply(this, arguments);
            }
            var self = this;
            var args = arguments;
            var pack = packForEl(el) || packForModalId(el && el.id);
            ensurePack(pack).then(function() {
                originalShow.apply(self, args);
            });
        };
        window.bootstrap.Modal.prototype.__mmLazyPatched = true;
    }

    function prefetchPacks() {
        var cfg = config();
        if (!cfg || !cfg.packs) {
            return;
        }
        Object.keys(cfg.packs).forEach(function(name) {
            ensurePack(name).catch(function() {});
        });
    }

    window.ensureClientDetailModalPack = ensurePack;
    window.ensureClientDetailModal = function(id) {
        var el = id ? document.getElementById(id) : null;
        var pack = packForEl(el) || packForModalId(id);
        return ensurePack(pack);
    };

    document.addEventListener('click', onCaptureClick, true);

    function boot() {
        if (!enabled()) {
            return;
        }
        patchJqueryModal();
        patchBootstrapModal();
        prefetchPacks();
        setTimeout(function() {
            patchJqueryModal();
            patchBootstrapModal();
        }, 0);
        setTimeout(function() {
            patchJqueryModal();
        }, 500);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }

    patchJqueryModal();
    patchBootstrapModal();
})();
