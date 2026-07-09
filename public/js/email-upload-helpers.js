/**
 * Shared Outlook email upload helpers (.msg / .eml).
 * Mirrors EmailUploadController extension validation.
 */
(function(global) {
    'use strict';

    function getAllowedEmailUploadExtensions() {
        if (Array.isArray(global.__CRM_EMAIL_ALLOWED_EXTENSIONS__) && global.__CRM_EMAIL_ALLOWED_EXTENSIONS__.length) {
            return global.__CRM_EMAIL_ALLOWED_EXTENSIONS__.map(function(ext) {
                return String(ext).toLowerCase().replace(/^\./, '');
            });
        }
        return ['msg', 'eml'];
    }

    function emailUploadAcceptAttribute() {
        return getAllowedEmailUploadExtensions().map(function(ext) {
            return '.' + ext;
        }).join(',');
    }

    function emailUploadExtensionsLabel() {
        return getAllowedEmailUploadExtensions().map(function(ext) {
            return '.' + ext;
        }).join(', ');
    }

    function isAllowedEmailUploadFilename(filename) {
        if (!filename || typeof filename !== 'string') {
            return false;
        }
        var lower = filename.toLowerCase();
        var allowed = getAllowedEmailUploadExtensions();
        return allowed.some(function(ext) {
            return lower.endsWith('.' + ext);
        });
    }

    function filterAllowedEmailUploadFiles(files) {
        return Array.from(files || []).filter(function(file) {
            return isAllowedEmailUploadFilename(file.name);
        });
    }

    function sanitizeEmailUploadFilename(filename) {
        if (!filename || typeof filename !== 'string') {
            return 'email_' + Date.now() + '.msg';
        }

        var lastDot = filename.lastIndexOf('.');
        var extension = lastDot >= 0 ? filename.slice(lastDot + 1) : '';
        var nameWithoutExt = lastDot >= 0 ? filename.slice(0, lastDot) : filename;

        var sanitizedName = nameWithoutExt.replace(/[^a-zA-Z0-9\-_.]/g, '_');
        sanitizedName = sanitizedName.replace(/_+/g, '_').replace(/^_+|_+$/g, '');

        if (!sanitizedName) {
            sanitizedName = 'email_' + Date.now();
        }

        var sanitizedFilename = extension ? sanitizedName + '.' + extension : sanitizedName;

        if (sanitizedFilename.length > 255) {
            var maxNameLength = 255 - extension.length - (extension ? 1 : 0);
            if (maxNameLength > 0) {
                sanitizedName = sanitizedName.slice(0, maxNameLength);
                sanitizedFilename = extension ? sanitizedName + '.' + extension : sanitizedName;
            } else {
                sanitizedFilename = 'email_' + Date.now() + (extension ? '.' + extension : '');
            }
        }

        return sanitizedFilename;
    }

    function buildEmailUploadFormData(form) {
        if (!form || typeof FormData === 'undefined') {
            return null;
        }

        var rebuilt = new FormData();
        var elements = form.elements || [];

        for (var i = 0; i < elements.length; i++) {
            var el = elements[i];
            if (!el.name || el.type === 'file') {
                continue;
            }
            if ((el.type === 'checkbox' || el.type === 'radio') && !el.checked) {
                continue;
            }
            rebuilt.append(el.name, el.value);
        }

        var fileInput = form.querySelector('input[type="file"][name="email_files[]"], input[type="file"]#email_files, input[type="file"]#email_files1');
        if (!fileInput || !fileInput.files) {
            return rebuilt;
        }

        Array.from(fileInput.files).forEach(function(file) {
            rebuilt.append('email_files[]', file, sanitizeEmailUploadFilename(file.name));
        });

        return rebuilt;
    }

    global.crmGetAllowedEmailUploadExtensions = getAllowedEmailUploadExtensions;
    global.crmEmailUploadAcceptAttribute = emailUploadAcceptAttribute;
    global.crmEmailUploadExtensionsLabel = emailUploadExtensionsLabel;
    global.crmIsAllowedEmailUploadFilename = isAllowedEmailUploadFilename;
    global.crmFilterAllowedEmailUploadFiles = filterAllowedEmailUploadFiles;
    global.crmSanitizeEmailUploadFilename = sanitizeEmailUploadFilename;
    global.crmBuildEmailUploadFormData = buildEmailUploadFormData;
})(window);
