/**
 * Load TinyMCE only when a rich-text editor is actually needed.
 * CRM layouts used to always include tinymce.min.js (and scripts.js auto-inited
 * every .tinymce-editor). Pages without an editor never download the bundle;
 * notes, compose, office visits, and broadcasts still get the same init.
 */
(function() {
    'use strict';

    var scriptEl = document.currentScript;
    var src = (scriptEl && scriptEl.getAttribute('data-tinymce-src')) || '/js/tinymce/js/tinymce/tinymce.min.js';
    var loadPromise = null;
    var initTimer = null;

    function hasTinyMCE() {
        return typeof window.tinymce !== 'undefined';
    }

    window.ensureTinyMCELoaded = function() {
        if (hasTinyMCE()) {
            return Promise.resolve();
        }
        if (loadPromise) {
            return loadPromise;
        }
        loadPromise = new Promise(function(resolve, reject) {
            var existing = document.querySelector('script[src*="tinymce.min.js"]');
            if (existing) {
                existing.addEventListener('load', function() { resolve(); });
                existing.addEventListener('error', reject);
                if (hasTinyMCE()) {
                    resolve();
                }
                return;
            }
            var s = document.createElement('script');
            s.src = src;
            s.async = true;
            s.onload = function() {
                if (typeof window.tinymce !== 'undefined') {
                    var base = src.replace(/\/tinymce\.min\.js(\?.*)?$/, '');
                    window.tinymce.baseURL = base;
                    window.tinymce.suffix = '.min';
                }
                resolve();
            };
            s.onerror = function() {
                loadPromise = null;
                reject(new Error('Failed to load TinyMCE'));
            };
            document.head.appendChild(s);
        });
        return loadPromise;
    };

    function editorIsLive(el) {
        if (!el || el.nodeType !== 1) {
            return false;
        }
        var modal = el.closest ? el.closest('.modal') : null;
        if (!modal) {
            return true;
        }
        return modal.classList.contains('show');
    }

    function liveEditorNodes() {
        var nodes = document.querySelectorAll('.tinymce-editor, .summernote, #broadcast-message');
        var live = [];
        for (var i = 0; i < nodes.length; i++) {
            if (editorIsLive(nodes[i])) {
                live.push(nodes[i]);
            }
        }
        return live;
    }

    function pageNeedsTinyMCE() {
        return liveEditorNodes().length > 0;
    }

    function runEditorInits() {
        if (typeof window.initTinyMCEForModals === 'function') {
            window.initTinyMCEForModals();
        }
        if (typeof window.initScriptsTinyMCEEditors === 'function') {
            window.initScriptsTinyMCEEditors();
        }
        document.dispatchEvent(new Event('tinymce:ready'));
    }

    window.requestTinyMCEInit = function() {
        if (!pageNeedsTinyMCE()) {
            return;
        }
        window.ensureTinyMCELoaded().then(runEditorInits).catch(function(err) {
            if (typeof console !== 'undefined' && console.error) {
                console.error('[TinyMCE]', err);
            }
        });
    };

    function scheduleTinyMCEInit() {
        if (initTimer) {
            clearTimeout(initTimer);
        }
        initTimer = setTimeout(function() {
            initTimer = null;
            window.requestTinyMCEInit();
        }, 50);
    }

    function nodeHasEditor(node) {
        if (!node || node.nodeType !== 1) {
            return false;
        }
        if (node.matches && node.matches('.tinymce-editor, .summernote, #broadcast-message')) {
            return editorIsLive(node);
        }
        if (!node.querySelector) {
            return false;
        }
        var found = node.querySelector('.tinymce-editor, .summernote, #broadcast-message');
        return !!(found && editorIsLive(found));
    }

    function onReady(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn);
        } else {
            fn();
        }
    }

    onReady(function() {
        scheduleTinyMCEInit();

        document.addEventListener('shown.bs.modal', scheduleTinyMCEInit, true);
        if (typeof window.jQuery !== 'undefined') {
            window.jQuery(document).on('shown.bs.modal', scheduleTinyMCEInit);
        }

        if (typeof MutationObserver === 'undefined' || !document.body) {
            return;
        }
        var observer = new MutationObserver(function(mutations) {
            for (var i = 0; i < mutations.length; i++) {
                var added = mutations[i].addedNodes;
                for (var j = 0; j < added.length; j++) {
                    if (nodeHasEditor(added[j])) {
                        scheduleTinyMCEInit();
                        return;
                    }
                }
            }
        });
        observer.observe(document.body, { childList: true, subtree: true });
    });
})();
