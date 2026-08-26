/**
 * TinyMCE/Editor helper utilities for client detail pages.
 * Extracted from detail-main.js - Phase 2 refactoring.
 * Requires: jQuery, TinyMCE (optional - falls back to textarea)
 */
(function($) {
    'use strict';
    if (!$) return;

    /**
     * Get content from TinyMCE editor or textarea.
     * @param {string} selector - jQuery selector for editor element
     * @returns {string}
     */
    function getEditorContent(selector) {
        var $element = $(selector);
        var elementId = $element.attr('id');

        if (typeof tinymce !== 'undefined' && elementId && tinymce.get(elementId)) {
            return tinymce.get(elementId).getContent();
        }

        return $element.val() || '';
    }

    /**
     * Set content in TinyMCE editor or textarea.
     * @param {string} selector - jQuery selector
     * @param {string} content - Content to set
     */
    function setEditorContent(selector, content) {
        var $element = $(selector);
        var elementId = $element.attr('id');

        if (typeof tinymce !== 'undefined' && elementId && tinymce.get(elementId)) {
            tinymce.get(elementId).setContent(content || '');
        } else {
            $element.val(content || '');
            if ($element.hasClass('tinymce-editor')) {
                setTimeout(function() {
                    if (elementId && typeof tinymce !== 'undefined' && tinymce.get(elementId)) {
                        tinymce.get(elementId).setContent(content || '');
                    }
                }, 100);
            }
        }
    }

    /**
     * Clear editor content.
     * @param {string} selector - jQuery selector
     */
    function clearEditor(selector) {
        setEditorContent(selector, '');
    }

    /**
     * Check if editor is initialized.
     * @param {string} selector - jQuery selector
     * @returns {boolean}
     */
    function isEditorInitialized(selector) {
        var $element = $(selector);
        var elementId = $element.attr('id');

        if (typeof tinymce !== 'undefined' && elementId && tinymce.get(elementId)) {
            return true;
        }

        return $element.hasClass('tinymce-editor');
    }

    window.getEditorContent = getEditorContent;
    window.setEditorContent = setEditorContent;
    window.clearEditor = clearEditor;
    window.isEditorInitialized = isEditorInitialized;

})(typeof jQuery !== 'undefined' ? jQuery : null);
