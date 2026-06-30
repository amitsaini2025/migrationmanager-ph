/**
 * Emails Module for CRM Client Email Tab
 * Handles upload, search, and display of .msg email files
 * Adapted from email-viewer app to work with migration manager backend
 */

(function() {
    'use strict';

    // =========================================================================
    // Module State
    // =========================================================================
    let currentPage = 1;
    let lastPage = 1;
    let isLoading = false;
    let isUploading = false;
    const MAX_FILES_PER_UPLOAD = 10;
    let currentMailType = 'inbox'; // 'inbox' or 'sent' - determines endpoint
    let currentLabelId = ''; // EmailLabel.id for filtering
    let currentSearch = '';
    let currentSort = 'date';
    let availableLabels = []; // Loaded from API

    function getMailTypeStorageKey() {
        const clientId = getClientId();
        if (!clientId) {
            return null;
        }
        if (isLeadContext()) {
            return 'crmEmailMailType:lead:' + clientId;
        }
        const matterId = getMatterId();
        if (!matterId) {
            return 'crmEmailMailType:client:' + clientId;
        }
        return 'crmEmailMailType:matter:' + clientId + ':' + matterId;
    }

    function persistMailType(type) {
        if (type !== 'inbox' && type !== 'sent') {
            return;
        }
        const key = getMailTypeStorageKey();
        if (!key) {
            return;
        }
        try {
            sessionStorage.setItem(key, type);
        } catch (e) {
            // sessionStorage may be unavailable in some browsers/private mode
        }
    }

    function loadPersistedMailType() {
        const key = getMailTypeStorageKey();
        if (!key) {
            return 'inbox';
        }
        try {
            const stored = sessionStorage.getItem(key);
            return stored === 'sent' || stored === 'inbox' ? stored : 'inbox';
        } catch (e) {
            return 'inbox';
        }
    }

    // Expose function to set mail type (for external use)
    window.setEmailMailType = function(type) {
        if (type !== 'inbox' && type !== 'sent') {
            return;
        }
        currentMailType = type;
        const mailTypeFilter = document.getElementById('mailTypeFilter');
        if (mailTypeFilter) {
            mailTypeFilter.value = type;
        }
        persistMailType(type);
    };

    /**
     * Restore Inbox/Sent filter from session (per client/matter) or post-send localStorage flag.
     */
    window.restoreEmailMailType = function() {
        let type = 'inbox';
        try {
            const switchToSent = localStorage.getItem('emailTabSwitchToSent');
            if (switchToSent === '1') {
                localStorage.removeItem('emailTabSwitchToSent');
                type = 'sent';
            } else {
                type = loadPersistedMailType();
            }
        } catch (e) {
            type = 'inbox';
        }
        window.setEmailMailType(type);
        return type;
    };

    // =========================================================================
    // Utility Functions
    // =========================================================================

    /**
     * Get client ID from the DOM (kept for backward compatibility)
     */
    function getClientId() {
        const container = document.querySelector('.email-interface-container');
        if (!container) {
            // Page doesn't have email interface - this is normal for pages that don't support emails
            return null;
        }
        
        // Check if the container has the required attribute
        const clientId = container.dataset.clientId;
        if (!clientId || clientId === '') {
            // Container exists but client ID is not set - page may not be configured for emails
            // This is not an error, just return null silently
            return null;
        }
        
        return clientId;
    }

    /**
     * Get matter ID from the DOM
     */
    function getMatterId() {
        const container = document.querySelector('.email-interface-container');
        if (!container) {
            // Page doesn't have email interface - this is normal for pages that don't support emails
            return null;
        }
        
        // Check if the container has the required attribute
        const matterId = container.dataset.matterId;
        if (!matterId || matterId === '') {
            return null;
        }
        return matterId;
    }

    /**
     * Check if we're in lead context (lead detail page - no matter)
     */
    function isLeadContext() {
        const container = document.querySelector('.email-interface-container');
        return container && container.dataset.context === 'lead';
    }

    /**
     * Get CSRF token from meta tag
     */
    function getCsrfToken() {
        const token = document.querySelector('meta[name="csrf-token"]');
        return token ? token.getAttribute('content') : '';
    }

    /**
     * Sanitize filename for multipart upload (WAF-safe).
     * Mirrors EmailUploadController::sanitizeFilename — apostrophes and other
     * special chars in Content-Disposition can trigger mod_security 403 blocks.
     */
    function sanitizeUploadFilename(filename) {
        if (!filename || typeof filename !== 'string') {
            return 'email_' + Date.now() + '.msg';
        }

        const lastDot = filename.lastIndexOf('.');
        const extension = lastDot >= 0 ? filename.slice(lastDot + 1) : '';
        const nameWithoutExt = lastDot >= 0 ? filename.slice(0, lastDot) : filename;

        let sanitizedName = nameWithoutExt.replace(/[^a-zA-Z0-9\-_.]/g, '_');
        sanitizedName = sanitizedName.replace(/_+/g, '_').replace(/^_+|_+$/g, '');

        if (!sanitizedName) {
            sanitizedName = 'email_' + Date.now();
        }

        let sanitizedFilename = extension ? sanitizedName + '.' + extension : sanitizedName;

        if (sanitizedFilename.length > 255) {
            const maxNameLength = 255 - extension.length - (extension ? 1 : 0);
            if (maxNameLength > 0) {
                sanitizedName = sanitizedName.slice(0, maxNameLength);
                sanitizedFilename = extension ? sanitizedName + '.' + extension : sanitizedName;
            } else {
                sanitizedFilename = 'email_' + Date.now() + (extension ? '.' + extension : '');
            }
        }

        return sanitizedFilename;
    }

    /**
     * Map HTTP 403 response body to a user-facing message (Laravel JSON vs WAF HTML vs CSRF).
     */
    function messageFor403Response(errorText) {
        const trimmed = (errorText || '').trim();
        let parsed = null;

        if (trimmed.startsWith('{')) {
            try {
                parsed = JSON.parse(trimmed);
            } catch (e) {
                parsed = null;
            }
        }

        if (parsed && typeof parsed.message === 'string' && parsed.message.trim() !== '') {
            return parsed.message.trim();
        }

        const isHtml = /<html[\s>]/i.test(trimmed) || /<!DOCTYPE/i.test(trimmed);

        if (isHtml || (trimmed.includes('Forbidden') && !parsed)) {
            console.error('Upload blocked by server security filter (likely WAF/mod_security)', trimmed.substring(0, 200));
            return 'The server blocked this upload (security filter). Rename files to remove special characters such as apostrophes (\') and try again, or contact support if it persists.';
        }

        if (/csrf token mismatch/i.test(trimmed) || (/csrf/i.test(trimmed) && !isHtml)) {
            console.error('CSRF token error - page may need to be refreshed');
            return 'Security token expired. Please refresh the page and try again.';
        }

        return 'Access denied. You may not have permission to upload emails for this client. Refresh the page and try again if your session may have expired.';
    }

    /**
     * Show notification message
     */
    function showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `email-notification email-notification-${type}`;
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            border-radius: 4px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            z-index: 10000;
            max-width: 500px;
            max-height: 400px;
            overflow-y: auto;
            animation: slideIn 0.3s ease-out;
            font-size: 14px;
            white-space: pre-wrap;
            word-wrap: break-word;
            ${type === 'success' ? 'background: #10b981; color: white;' : ''}
            ${type === 'error' ? 'background: #ef4444; color: white;' : ''}
            ${type === 'info' ? 'background: #3b82f6; color: white;' : ''}
        `;
        notification.textContent = message;

        document.body.appendChild(notification);

        // Longer display time for error messages
        const displayTime = type === 'error' ? 8000 : 4000;

        setTimeout(() => {
            notification.style.animation = 'slideOut 0.3s ease-out';
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 300);
        }, displayTime);
    }

    /**
     * Format date to readable string
     * Handles both ISO date strings and formatted strings like "d/m/Y h:i a"
     */
    function formatDate(dateString) {
        if (!dateString) return 'Unknown';
        try {
            // Check if it's already in formatted format (d/m/Y h:i a)
            if (typeof dateString === 'string' && dateString.match(/^\d{2}\/\d{2}\/\d{4} \d{2}:\d{2} (am|pm)$/i)) {
                // Parse formatted date: "dd/mm/yyyy hh:mm am/pm"
                const parts = dateString.match(/^(\d{2})\/(\d{2})\/(\d{4}) (\d{2}):(\d{2}) (am|pm)$/i);
                if (parts) {
                    const [, day, month, year, hour, minute, ampm] = parts;
                    let hour24 = parseInt(hour);
                    if (ampm.toLowerCase() === 'pm' && hour24 !== 12) hour24 += 12;
                    if (ampm.toLowerCase() === 'am' && hour24 === 12) hour24 = 0;
                    const date = new Date(year, month - 1, day, hour24, minute);
                    return date.toLocaleString('en-AU', {
                        year: 'numeric',
                        month: 'short',
                        day: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                }
            }
            // Try parsing as ISO date string
            const date = new Date(dateString);
            if (isNaN(date.getTime())) {
                return dateString; // Return as-is if can't parse
            }
            return date.toLocaleString('en-AU', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        } catch (e) {
            return dateString;
        }
    }

    /**
     * Get the email date to display (prefers sent date over upload date)
     */
    function getEmailDate(email) {
        // Prefer fetch_mail_sent_time (email's original sent date)
        if (email.fetch_mail_sent_time) {
            return email.fetch_mail_sent_time;
        }
        // Fallback to received_date if available
        if (email.received_date) {
            return email.received_date;
        }
        // Last resort: use created_at - CRM-sent emails have this (was bug: recursive call caused stack overflow)
        return email.created_at || null;
    }

    /**
     * Format file size to readable string
     */
    function formatFileSize(bytes) {
        if (!bytes || bytes === 0) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + ' ' + sizes[i];
    }

    /**
     * Get attachment icon class based on content type
     */
    function getAttachmentIcon(contentType) {
        if (!contentType) return 'fas fa-paperclip';
        
        const type = contentType.toLowerCase();
        
        // Images
        if (type.includes('image')) {
            return 'fas fa-image';
        }
        
        // PDFs
        if (type.includes('pdf')) {
            return 'fas fa-file-pdf';
        }
        
        // Word documents
        if (type.includes('word') || type.includes('document') || type.includes('.docx')) {
            return 'fas fa-file-word';
        }
        
        // Excel spreadsheets
        if (type.includes('excel') || type.includes('spreadsheet') || type.includes('.xlsx')) {
            return 'fas fa-file-excel';
        }
        
        // PowerPoint
        if (type.includes('powerpoint') || type.includes('presentation')) {
            return 'fas fa-file-powerpoint';
        }
        
        // Archives
        if (type.includes('zip') || type.includes('rar') || type.includes('archive')) {
            return 'fas fa-file-archive';
        }
        
        // Code files
        if (type.includes('text/plain') || type.includes('code') || type.includes('javascript') || type.includes('html')) {
            return 'fas fa-file-code';
        }
        
        // Default
        return 'fas fa-paperclip';
    }

    /**
     * Get attachment icon color class based on content type
     */
    function getAttachmentIconColor(contentType) {
        if (!contentType) return '';
        
        const type = contentType.toLowerCase();
        
        if (type.includes('image')) return 'attachment-icon-image';
        if (type.includes('pdf')) return 'attachment-icon-pdf';
        if (type.includes('word') || type.includes('document')) return 'attachment-icon-word';
        if (type.includes('excel') || type.includes('spreadsheet')) return 'attachment-icon-excel';
        
        return '';
    }

    /**
     * Render attachment type icon HTML (Lucide via crmIconAny).
     */
    function renderAttachmentIcon(contentType) {
        const legacy = getAttachmentIcon(contentType);
        const colorClass = getAttachmentIconColor(contentType);
        const extraClass = ('attachment-icon ' + colorClass).trim();
        return typeof crmIconAny === 'function'
            ? crmIconAny(legacy, { class: extraClass })
            : '<i class="' + legacy + ' ' + extraClass + '"></i>';
    }

    /**
     * Render label icon from stored Lucide name or legacy FA class string.
     */
    function renderLabelIcon(icon) {
        const stored = icon || 'tag';
        return typeof crmIconAny === 'function'
            ? crmIconAny(stored)
            : '<i class="' + (stored.includes(' ') ? stored : 'fas ' + stored) + '"></i>';
    }

    /**
     * Check if attachment can be previewed (images/PDFs only).
     * Filename extension is used as fallback when content_type is missing or generic.
     */
    function canPreviewAttachment(contentType, filename) {
        if (contentType) {
            const type = contentType.toLowerCase();
            if (type.includes('image/') || type.includes('pdf')) {
                return true;
            }
        }

        if (filename) {
            const ext = filename.split('.').pop()?.toLowerCase();
            if (['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'pdf'].includes(ext)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Resolve inline cid: attachment to a preview URL, or null if not previewable/stored.
     */
    function getInlineAttachmentPreviewUrl(attachment) {
        if (!attachment || !attachment.id || !attachment.s3_key) {
            return null;
        }

        const filename = attachment.filename || attachment.display_name || '';
        if (!canPreviewAttachment(attachment.content_type, filename)) {
            return null;
        }

        return `/mail-attachments/${attachment.id}/preview`;
    }

    /**
     * Sanitize filename for safe download
     */
    function sanitizeFilename(filename) {
        if (!filename) return 'download';
        
        // Remove invalid filename characters
        return filename
            .replace(/[/\\?%*:|"<>]/g, '-')  // Replace invalid chars
            .replace(/\s+/g, '_')             // Replace spaces with underscore
            .substring(0, 200);               // Limit length
    }

    /**
     * Filter to get only regular (non-inline) attachments
     */
    function getRegularAttachments(attachments) {
        if (!attachments || !Array.isArray(attachments)) {
            return [];
        }
        
        return attachments.filter(att => !att.is_inline);
    }

    /**
     * Debounce function
     */
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    // =========================================================================
    // Upload Functionality
    // =========================================================================

    /**
     * Initialize upload functionality with drag & drop
     */
    window.initializeUpload = function() {
        console.log('Initializing upload module...');
        
        const fileInput = document.getElementById('emailFileInput');
        const uploadArea = document.getElementById('upload-area');
        const fileStatus = document.getElementById('fileStatus');
        const fileCountBadge = document.getElementById('file-count');
        const uploadProgress = document.getElementById('upload-progress');

        if (!fileInput || !uploadArea || !fileStatus) {
            console.warn('Upload elements not found - skipping email upload initialization (page may not have emails UI)');
            return;
        }

        let dragCounter = 0;

        // Prevent default drag behaviors on document
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            document.body.addEventListener(eventName, preventDefaults, false);
        });

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        // Highlight drop area when item is dragged over it
        uploadArea.addEventListener('dragenter', function(e) {
            dragCounter++;
            uploadArea.classList.add('drag-over');
        });

        uploadArea.addEventListener('dragleave', function(e) {
            dragCounter--;
            if (dragCounter === 0) {
                uploadArea.classList.remove('drag-over');
            }
        });

        uploadArea.addEventListener('dragover', function(e) {
            e.preventDefault();
        });

        // Handle dropped files
        uploadArea.addEventListener('drop', function(e) {
            dragCounter = 0;
            uploadArea.classList.remove('drag-over');
            
            const dt = e.dataTransfer;
            const files = dt.files;
            
            if (files && files.length > 0) {
                handleFiles(files);
            }
        });

        // Click to open file dialog
        uploadArea.addEventListener('click', function() {
            if (!isUploading) {
                fileInput.click();
            }
        });

        // Handle file input change
        fileInput.addEventListener('change', function() {
            const files = this.files;
            if (files && files.length > 0) {
                handleFiles(files);
            }
        });

        function handleFiles(files) {
            if (isUploading) {
                console.log('Upload already in progress');
                return;
            }

            console.log('Files selected:', files.length);

            // Filter to only .msg files
            const msgFiles = Array.from(files).filter(file => 
                file.name.toLowerCase().endsWith('.msg')
            );

            if (msgFiles.length === 0) {
                showNotification('Please select .msg files only', 'error');
                fileStatus.textContent = 'Only .msg files allowed';
                fileStatus.parentElement.className = 'upload-progress error';
                setTimeout(() => {
                    fileStatus.textContent = 'Ready to upload';
                    fileStatus.parentElement.className = 'upload-progress';
                }, 3000);
                return;
            }

            if (msgFiles.length !== files.length) {
                showNotification(`Only ${msgFiles.length} of ${files.length} files are .msg files`, 'info');
            }

            if (msgFiles.length > MAX_FILES_PER_UPLOAD) {
                showNotification(
                    `Maximum ${MAX_FILES_PER_UPLOAD} files allowed per upload. You selected ${msgFiles.length}. Please upload in smaller batches.`,
                    'error'
                );
                fileStatus.textContent = `Maximum ${MAX_FILES_PER_UPLOAD} files per upload`;
                fileStatus.parentElement.className = 'upload-progress error';
                updateFileCount(0);
                fileInput.value = '';
                setTimeout(() => {
                    fileStatus.textContent = 'Ready to upload';
                    fileStatus.parentElement.className = 'upload-progress';
                }, 4000);
                return;
            }

            // Update file count badge
            updateFileCount(msgFiles.length);

            // Update status
            fileStatus.textContent = `${msgFiles.length} file(s) ready to upload`;
            fileStatus.parentElement.className = 'upload-progress';

            // Auto-upload immediately
            uploadFiles(msgFiles);
        }

        function updateFileCount(count) {
            if (fileCountBadge) {
                fileCountBadge.textContent = count;
                if (count > 0) {
                    fileCountBadge.classList.add('show');
                } else {
                    fileCountBadge.classList.remove('show');
                }
            }
        }

        console.log('Upload module initialized with drag & drop');
    };

    /**
     * Upload files to server
     */
    async function uploadFiles(files) {
        const clientId = getClientId();
        const matterId = getMatterId();
        
        if (!clientId) {
            showNotification('Client ID not found', 'error');
            return;
        }
        
        if (!matterId) {
            showNotification('Matter ID not found. Please select a matter.', 'error');
            return;
        }

        isUploading = true;
        
        const fileStatus = document.getElementById('fileStatus');
        const uploadProgress = document.getElementById('upload-progress');
        const fileCountBadge = document.getElementById('file-count');
        
        // Update UI - uploading state
        if (uploadProgress) {
            uploadProgress.className = 'upload-progress uploading';
        }
        fileStatus.textContent = `Uploading ${files.length} file(s)...`;

        try {
            const formData = new FormData();
            
            // Add files (sanitized names avoid WAF/mod_security blocks on apostrophes etc.)
            files.forEach(file => {
                const safeName = sanitizeUploadFilename(file.name);
                if (safeName !== file.name) {
                    console.log('Sanitized upload filename:', file.name, '->', safeName);
                }
                formData.append('email_files[]', file, safeName);
            });

            // Add required fields based on current mail type (inbox or sent)
            formData.append('client_id', clientId);
            formData.append('type', 'client');
            
            // Add matter ID - this is now REQUIRED for matter-specific emails
            formData.append(
                currentMailType === 'sent' ? 'upload_sent_mail_client_matter_id' : 'upload_inbox_mail_client_matter_id',
                matterId
            );

            // Validate and add CSRF token (both in header and form data for compatibility)
            const csrfToken = getCsrfToken();
            if (!csrfToken) {
                throw new Error('Security token not found. Please refresh the page and try again.');
            }
            formData.append('_token', csrfToken);

            console.log('Uploading to:', currentMailType === 'sent' ? '/upload-sent-fetch-mail' : '/upload-fetch-mail');

            // Note: Don't set Content-Type header when using FormData - browser sets it automatically with boundary
            const response = await fetch(
                currentMailType === 'sent' ? '/upload-sent-fetch-mail' : '/upload-fetch-mail',
                {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                        // Don't set Content-Type - browser will set it with multipart/form-data boundary
                    },
                    body: formData,
                    credentials: 'same-origin' // Include cookies for session/CSRF
                }
            );

            // Validate response before parsing JSON
            if (!response.ok) {
                const errorText = await response.text();
                
                // Handle specific error codes with user-friendly messages
                if (response.status === 403) {
                    throw new Error(messageFor403Response(errorText));
                } else if (response.status === 419) {
                    // Laravel's CSRF token mismatch status code
                    console.error('CSRF token mismatch - page needs refresh');
                    throw new Error('Security token expired. Please refresh the page and try again.');
                } else if (response.status === 413) {
                    throw new Error('File too large. Maximum file size is 30MB per file.');
                } else if (response.status === 422) {
                    // Validation error - try to parse as JSON for better error message
                    try {
                        const errorData = JSON.parse(errorText);
                        const errorMsg = errorData.message || errorData.errors ? 
                            Object.values(errorData.errors || {}).flat().join(', ') : 
                            'Validation failed';
                        throw new Error(`Upload validation failed: ${errorMsg}`);
                    } catch {
                        throw new Error(`Upload validation failed. Please check your file format and try again.`);
                    }
                }
                
                throw new Error(`Upload failed: ${response.status} ${response.statusText}. ${errorText.substring(0, 200)}`);
            }

            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                const errorText = await response.text();
                console.error('Server returned non-JSON response:', errorText.substring(0, 500));
                
                // Check for common HTML error pages
                if (errorText.includes('403 Forbidden') || errorText.includes('Forbidden')) {
                    throw new Error(messageFor403Response(errorText));
                } else if (errorText.includes('419') || errorText.includes('CSRF')) {
                    throw new Error('Security token expired. Please refresh the page and try again.');
                }
                
                throw new Error('Server returned invalid response format. Please try again or contact support if the issue persists.');
            }

            const data = await response.json();
            console.log('Upload response:', data);

            if (data.status || data.success) {
                // Check if there were any failures
                const failedCount = data.failed || 0;
                const uploadedCount = data.uploaded || 0;
                
                if (failedCount > 0) {
                    // Partial or complete failure
                    if (uploadProgress) {
                        uploadProgress.className = 'upload-progress error';
                    }
                    fileStatus.textContent = 'Upload completed with errors';
                    
                    // Build detailed error message
                    let errorMessage = data.message || `Upload failed: ${failedCount} file(s) failed`;
                    
                    // Add specific error details if available
                    if (data.errors && Array.isArray(data.errors) && data.errors.length > 0) {
                        const errorDetails = data.errors.map((err, index) => {
                            const filename = err.filename || 'Unknown file';
                            const error = err.error || 'Unknown error';
                            const fileSize = err.file_size ? ` (${formatFileSize(err.file_size)})` : '';
                            return `${index + 1}. ${filename}${fileSize}\n   ${error}`;
                        }).join('\n\n');
                        errorMessage += '\n\nError Details:\n' + errorDetails;
                        
                        // Add helpful tip if all files failed
                        if (uploadedCount === 0 && failedCount > 0) {
                            errorMessage += '\n\n💡 Tip: Ensure the Python service is running and the .msg files are valid Outlook email files.';
                        }
                    }
                    
                    showNotification(errorMessage, 'error');
                    
                    // Log errors to console for debugging
                    if (data.errors) {
                        console.error('Upload errors:', data.errors);
                    }
                    
                    // Reset after delay
                    setTimeout(() => {
                        fileStatus.textContent = 'Ready to upload';
                        if (uploadProgress) {
                            uploadProgress.className = 'upload-progress';
                        }
                        if (fileCountBadge && uploadedCount === 0) {
                            fileCountBadge.classList.remove('show');
                        }
                    }, 5000); // Longer delay for error messages
                    
                    // Only reload if some files were successfully uploaded
                    if (uploadedCount > 0) {
                        loadEmails();
                    }
                } else {
                    // Complete success
                    if (uploadProgress) {
                        uploadProgress.className = 'upload-progress success';
                    }
                    fileStatus.textContent = 'Upload successful!';
                    showNotification(data.message || 'Files uploaded successfully!', 'success');
                    
                    // Reset form after delay
                    setTimeout(() => {
                        document.getElementById('emailFileInput').value = '';
                        fileStatus.textContent = 'Ready to upload';
                        if (uploadProgress) {
                            uploadProgress.className = 'upload-progress';
                        }
                        if (fileCountBadge) {
                            fileCountBadge.classList.remove('show');
                        }
                    }, 2000);
                    
                    // Reload email list
                    loadEmails();
                }
            } else {
                // Complete error state
                if (uploadProgress) {
                    uploadProgress.className = 'upload-progress error';
                }
                fileStatus.textContent = 'Upload failed';
                
                // Build error message with details
                let errorMessage = data.message || 'Upload failed';
                
                // Add validation errors if available
                if (data.errors) {
                    if (typeof data.errors === 'object' && !Array.isArray(data.errors)) {
                        const errorDetails = [];
                        for (const [key, value] of Object.entries(data.errors)) {
                            if (Array.isArray(value)) {
                                const fieldName = key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
                                errorDetails.push(`• ${fieldName}: ${value.join(', ')}`);
                            } else {
                                const fieldName = key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
                                errorDetails.push(`• ${fieldName}: ${value}`);
                            }
                        }
                        if (errorDetails.length > 0) {
                            errorMessage += '\n\nValidation Errors:\n' + errorDetails.join('\n');
                        }
                    } else if (Array.isArray(data.errors) && data.errors.length > 0) {
                        // Handle array of errors
                        const errorDetails = data.errors.map((err, index) => {
                            if (typeof err === 'string') {
                                return `${index + 1}. ${err}`;
                            } else if (err.filename && err.error) {
                                return `${index + 1}. ${err.filename}: ${err.error}`;
                            }
                            return `${index + 1}. ${JSON.stringify(err)}`;
                        }).join('\n');
                        errorMessage += '\n\nErrors:\n' + errorDetails;
                    }
                    console.error('Upload errors:', data.errors);
                }
                
                // Add technical error for debugging (if available)
                if (data.technical_error && data.technical_error !== errorMessage) {
                    console.error('Technical error:', data.technical_error);
                }
                
                showNotification(errorMessage, 'error');
                
                // Reset after delay
                setTimeout(() => {
                    fileStatus.textContent = 'Ready to upload';
                    if (uploadProgress) {
                        uploadProgress.className = 'upload-progress';
                    }
                }, 5000);
            }

        } catch (error) {
            console.error('Upload error:', error);
            if (uploadProgress) {
                uploadProgress.className = 'upload-progress error';
            }
            fileStatus.textContent = 'Upload failed';
            showNotification('Upload failed: ' + error.message, 'error');
            
            // Reset after delay
            setTimeout(() => {
                fileStatus.textContent = 'Ready to upload';
                if (uploadProgress) {
                    uploadProgress.className = 'upload-progress';
                }
            }, 3000);
        } finally {
            isUploading = false;
        }
    }

    // =========================================================================
    // Search Functionality
    // =========================================================================

    /**
     * Initialize search functionality
     */
    window.initializeSearch = function() {
        console.log('Initializing search module...');

        const searchInput = document.getElementById('emailSearchInput');
        const labelFilter = document.getElementById('labelFilter');

        if (!searchInput) {
            console.warn('Search input not found - skipping search initialization');
            return;
        }
        
        if (!labelFilter) {
            console.warn('Label filter not found - search will work with limited functionality');
        }

        // Real-time search (debounced)
        const debouncedSearch = debounce(function() {
            currentSearch = searchInput.value;
            currentPage = 1;
            loadEmails();
        }, 500);

        searchInput.addEventListener('input', debouncedSearch);

        // Label filter change - auto-applies when changed
        if (labelFilter) {
            labelFilter.addEventListener('change', function() {
                currentLabelId = this.value;
                currentPage = 1;
                loadEmails();
            });
        }

        console.log('Search module initialized');
    };

    // =========================================================================
    // Email List Functionality
    // =========================================================================

    /**
     * Initialize email list and load initial emails
     */
    window.loadEmails = function() {
        const container = document.querySelector('.email-interface-container');
        if (!container) {
            return;
        }
        const isLead = isLeadContext();
        if (!container.dataset.clientId) {
            return;
        }
        if (!isLead && !container.dataset.matterId) {
            return;
        }
        console.log('Loading emails...' + (isLead ? ' (lead context)' : ''));
        loadEmailsFromServer();
    };

    /**
     * Fetch and display emails from server
     */
    async function loadEmailsFromServer() {
        const clientId = getClientId();
        const matterId = getMatterId();
        const isLead = isLeadContext();
        
        if (!clientId) {
            return;
        }
        
        if (!isLead && !matterId) {
            const container = document.querySelector('.email-interface-container');
            if (container) {
                renderEmptyState('Please select a matter to view emails');
            }
            return;
        }

        if (isLoading) {
            console.log('Already loading emails');
            return;
        }

        isLoading = true;
        updateLoadingState(true);

        try {
            const endpoint = isLead 
                ? '/clients/filter-lead-emails'
                : (currentMailType === 'sent' ? '/clients/filter-sentemails' : '/clients/filter-emails');

            const requestBody = isLead
                ? { client_id: clientId, search: currentSearch, status: '', label_id: currentLabelId }
                : { client_id: clientId, client_matter_id: matterId, search: currentSearch, status: '', label_id: currentLabelId };

            console.log('Fetching emails from:', endpoint, requestBody);

            const response = await fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                    'Accept': 'application/json'
                },
                body: JSON.stringify(requestBody)
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const emails = await response.json();
            console.log('Emails received:', emails);
            
            // Debug: Check attachments in received emails
            emails.forEach((email, index) => {
                if (email.attachments && email.attachments.length > 0) {
                    console.log(`Email ${index} (ID: ${email.id}) has ${email.attachments.length} attachments`);
                }
            });

            // Apply sorting
            const sortedEmails = sortEmails(emails);

            // Render emails
            renderEmails(sortedEmails);

            // Update counts
            updateEmailCounts(sortedEmails.length);

        } catch (error) {
            console.error('Error loading emails:', error);
            showNotification('Failed to load emails: ' + error.message, 'error');
            renderEmptyState('Error loading emails');
        } finally {
            isLoading = false;
            updateLoadingState(false);
        }
    }

    /**
     * Sort emails based on current sort option
     */
    function sortEmails(emails) {
        if (!Array.isArray(emails)) {
            console.error('Emails is not an array:', emails);
            return [];
        }

        return emails.slice().sort((a, b) => {
            switch (currentSort) {
                case 'subject':
                    return (a.subject || '').localeCompare(b.subject || '');
                case 'sender':
                    return (a.from_mail || '').localeCompare(b.from_mail || '');
                case 'date':
                default:
                    // Use sent date for sorting, fallback to created_at
                    const getDateForSort = (email) => {
                        if (email.fetch_mail_sent_time) {
                            // Parse formatted date: "dd/mm/yyyy hh:mm am/pm"
                            const parts = email.fetch_mail_sent_time.match(/^(\d{2})\/(\d{2})\/(\d{4}) (\d{2}):(\d{2}) (am|pm)$/i);
                            if (parts) {
                                const [, day, month, year, hour, minute, ampm] = parts;
                                let hour24 = parseInt(hour);
                                if (ampm.toLowerCase() === 'pm' && hour24 !== 12) hour24 += 12;
                                if (ampm.toLowerCase() === 'am' && hour24 === 12) hour24 = 0;
                                return new Date(year, month - 1, day, hour24, minute);
                            }
                        }
                        if (email.received_date) {
                            return new Date(email.received_date);
                        }
                        return new Date(email.created_at || 0);
                    };
                    const dateA = getDateForSort(a);
                    const dateB = getDateForSort(b);
                    return dateB - dateA; // Newest first
            }
        });
    }

    /**
     * Render emails in the list
     */
    function renderEmails(emails) {
        const emailList = document.getElementById('emailList');
        if (!emailList) {
            console.error('Email list element not found');
            return;
        }

        // Clear existing content
        emailList.innerHTML = '';

        if (!emails || emails.length === 0) {
            let emptyMsg = null;
            let emptySub = null;
            if (isLeadContext()) {
                emptySub = 'Emails sent to this lead from the CRM will appear here.';
            } else if (currentMailType === 'sent') {
                emptySub = 'Emails sent from the CRM will appear here.';
            }
            renderEmptyState(emptyMsg, emptySub);
            return;
        }

        emails.forEach(email => {
            const emailItem = createEmailItem(email);
            emailList.appendChild(emailItem);
        });
    }

    /**
     * Create email list item element
     */
    function createEmailItem(email) {
        const div = document.createElement('div');
        div.className = 'email-item';
        div.dataset.emailId = email.id;

        const subject = email.subject || '(No subject)';
        const from = email.from_mail || 'Unknown sender';
        const to = cleanRecipients(email.to_mail) || 'Unknown recipient';
        const cc = cleanRecipients(email.cc) || '';
        const date = formatDate(getEmailDate(email));
        const isRead = email.mail_is_read == 1;

        // NEW: Attachment indicator
        const hasAttachments = email.attachments && Array.isArray(email.attachments) && email.attachments.length > 0;
        const attachmentIcon = hasAttachments
            ? (typeof crmIconAny === 'function' ? crmIconAny('paperclip', { class: 'attachment-indicator' }) : '<i class="fas fa-paperclip attachment-indicator"></i>')
            : '';

        // NEW: Label badges
        const labelBadges = (email.labels && Array.isArray(email.labels))
            ? email.labels.map(label =>
                `<span class="label-badge" style="background-color: ${label.color}20; border-color: ${label.color}; color: ${label.color}">
                    ${renderLabelIcon(label.icon)} ${label.name}
                </span>`
            ).join('')
            : '';

        div.innerHTML = `
            <div class="email-item-header">
                <div class="email-subject" style="${!isRead ? 'font-weight: 700;' : ''}">
                    ${escapeHtml(subject)}
                    ${attachmentIcon}
                </div>
                <div class="email-date">${date}</div>
            </div>
            <div class="email-sender">From: ${escapeHtml(from)}</div>
            <div class="email-sender" style="font-size: 12px; color: #999;">To: ${escapeHtml(to)}</div>
            ${cc ? `<div class="email-sender" style="font-size: 12px; color: #999;">Cc: ${escapeHtml(cc)}</div>` : ''}
            <div class="email-badges">
                ${labelBadges}
            </div>
        `;

        // Add click handler to view email
        div.addEventListener('click', function(e) {
            // Don't trigger if context menu is open (close it first on click)
            const contextMenu = document.getElementById('emailContextMenu');
            if (contextMenu && contextMenu.style.display === 'block') {
                hideContextMenu();
                return;
            }
            
            // Remove selection from other items
            document.querySelectorAll('.email-item').forEach(item => {
                item.classList.remove('selected');
            });
            
            // Add selection to this item
            this.classList.add('selected');
            
            // Load email details
            loadEmailDetail(email);
        });

        // Add right-click handler for context menu
        div.addEventListener('contextmenu', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            // Store current email for context menu actions
            this.dataset.emailData = JSON.stringify(email);
            
            // Show context menu at cursor position
            showContextMenu(e.clientX, e.clientY, email);
        });

        return div;
    }

    /**
     * Render empty state
     */
    function renderEmptyState(message = null, subtitle = null) {
        const emailList = document.getElementById('emailList');
        if (!emailList) return;

        const sub = subtitle || (message ? 'Please try again.' : (currentMailType === 'sent' ? 'Emails sent from the CRM will appear here.' : 'Upload .msg files to get started with email management.'));
        emailList.innerHTML = `
            <div class="empty-state">
                <div class="empty-state-icon">
                    ${typeof crmIconAny === 'function' ? crmIconAny('fas fa-inbox') : '<i class="fas fa-inbox"></i>'}
                </div>
                <div class="empty-state-text">
                    <h3>${message || 'No emails found'}</h3>
                    <p>${sub}</p>
                </div>
            </div>
        `;
    }

    /**
     * Update loading state visual indicator
     */
    function updateLoadingState(loading) {
        const emailList = document.getElementById('emailList');
        if (!emailList) return;

        if (loading) {
            emailList.innerHTML = `
                <div class="empty-state">
                    <div class="empty-state-icon">
                        ${typeof crmIconAny === 'function' ? crmIconAny('fas fa-spinner fa-spin') : '<i class="fas fa-spinner fa-spin"></i>'}
                    </div>
                    <div class="empty-state-text">
                        <h3>Loading emails...</h3>
                        <p>Please wait</p>
                    </div>
                </div>
            `;
        }
    }

    /**
     * Update email counts
     */
    function updateEmailCounts(total) {
        const resultsCount = document.getElementById('resultsCount');
        if (resultsCount) {
            resultsCount.textContent = `${total} result${total !== 1 ? 's' : ''}`;
        }
    }

    /**
     * Load and display email details with attachments
     */
    function loadEmailDetail(email) {
        const emailContentView = document.getElementById('emailContentView');
        const emailContentPlaceholder = document.getElementById('emailContentPlaceholder');

        if (!emailContentView || !emailContentPlaceholder) {
            console.error('Email detail elements not found');
            return;
        }

        // Hide placeholder, show content
        emailContentPlaceholder.style.display = 'none';
        emailContentView.style.display = 'block';

        const subject = email.subject || '(No subject)';
        const from = email.from_mail || 'Unknown';
        const to = cleanRecipients(email.to_mail) || 'Unknown';
        const cc = cleanRecipients(email.cc) || '';
        const date = formatDate(getEmailDate(email));
        const hasDbBody = typeof email.message === 'string' && email.message.trim() !== '';
        let message = hasDbBody ? email.message : '(No content)';

        if (!hasDbBody && email.has_archived_body && email.archived_body_view_url) {
            message = `
                <div class="archived-body-notice">
                    <p>Email body has been moved to S3 storage.</p>
                    <a href="${email.archived_body_view_url}" target="_blank" rel="noopener noreferrer" class="archived-body-view-link">
                        ${typeof crmIconAny === 'function' ? crmIconAny('fas fa-external-link-alt') : '<i class="fas fa-external-link-alt"></i>'} View Email Body
                    </a>
                </div>
            `;
        }

        // Get all attachments (including inline) - show all so users can download important files like payment receipts
        // Even if they're displayed inline in the email body, they should also be available as downloadable attachments
        const allAttachments = email.attachments && Array.isArray(email.attachments) ? email.attachments : [];
        const regularAttachments = allAttachments; // Show all attachments, not just non-inline
        const hasAttachments = regularAttachments.length > 0;
        
        // Replace cid: references in email message with actual preview URLs for inline images
        if (hasDbBody) {
            message = replaceCidReferences(message, allAttachments);
            message = sanitizeEmailHtmlForDisplay(message);
        }
        
        // Debug logging
        console.log('Loading email detail:', {
            id: email.id,
            subject: email.subject,
            attachments: email.attachments,
            allAttachments: allAttachments,
            regularAttachments: regularAttachments,
            hasAttachments: hasAttachments
        });

        // Build attachment list HTML
        let attachmentHtml = '';
        if (hasAttachments) {
            const attachmentItems = regularAttachments.map(att => `
                <div class="attachment-item" data-attachment-id="${att.id}">
                    <div class="attachment-info">
                        ${renderAttachmentIcon(att.content_type)}
                        <div class="attachment-details">
                            <div class="attachment-name">${escapeHtml(att.filename || att.display_name || 'Unknown')}</div>
                            <div class="attachment-size">${formatFileSize(att.file_size || 0)}</div>
                        </div>
                    </div>
                    <div class="attachment-actions">
                        <button class="download-btn download-attachment-btn" 
                                data-attachment-id="${att.id}" 
                                data-filename="${escapeHtml(att.filename || att.display_name || 'file')}"
                                title="Download ${escapeHtml(att.filename || 'file')}">
                            ${typeof crmIconAny === 'function' ? crmIconAny('fas fa-download') : '<i class="fas fa-download"></i>'} Download
                        </button>
                        ${canPreviewAttachment(att.content_type, att.filename || att.display_name) ? `
                        <button class="preview-btn preview-attachment-btn" 
                                data-attachment-id="${att.id}" 
                                data-filename="${escapeHtml(att.filename || att.display_name || 'file')}"
                                title="Preview ${escapeHtml(att.filename || 'file')}">
                            ${typeof crmIconAny === 'function' ? crmIconAny('fas fa-eye') : '<i class="fas fa-eye"></i>'} Preview
                        </button>
                        ` : ''}
                    </div>
                </div>
            `).join('');

            attachmentHtml = `
                <div class="attachment-list">
                    <div class="attachment-list-header">
                        <span class="attachment-list-title">
                            ${typeof crmIconAny === 'function' ? crmIconAny('fas fa-paperclip') : '<i class="fas fa-paperclip"></i>'} 
                            ${regularAttachments.length} Attachment${regularAttachments.length !== 1 ? 's' : ''}
                        </span>
                        ${regularAttachments.length > 1 ? `
                        <button class="download-all-btn" 
                                data-mail-report-id="${email.id}"
                                data-email-subject="${escapeHtml(subject)}"
                                title="Download all attachments as ZIP">
                            ${typeof crmIconAny === 'function' ? crmIconAny('fas fa-download') : '<i class="fas fa-download"></i>'} Download All
                        </button>
                        ` : ''}
                    </div>
                    ${attachmentItems}
                </div>
            `;
        }

        // Original .msg file download section
        let previewSection = '';
        if (email.preview_url) {
            previewSection = `
                <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #e0e0e0;">
                    <h4 style="margin-bottom: 10px; font-weight: 600;">Original Email File</h4>
                    <a href="${email.preview_url}" target="_blank" class="btn btn-sm btn-primary">
                        ${typeof crmIconAny === 'function' ? crmIconAny('fas fa-download') : '<i class="fas fa-download"></i>'} Download .msg File
                    </a>
                </div>
            `;
        }

        // Render complete email detail
        emailContentView.innerHTML = `
            <div class="email-content-header">
                <div class="email-content-subject">${escapeHtml(subject)}</div>
                <div class="email-content-meta">
                    <div><strong>From:</strong> ${escapeHtml(from)}</div>
                    <div><strong>To:</strong> ${escapeHtml(to)}</div>
                    ${cc ? `<div><strong>Cc:</strong> ${escapeHtml(cc)}</div>` : ''}
                    <div><strong>Date:</strong> ${date}</div>
                </div>
            </div>
            <div class="email-content-body">
                ${message}
            </div>
            ${attachmentHtml}
            ${previewSection}
        `;
    }

    /**
     * Whether a table cell or block element is an empty layout spacer (no visible content).
     */
    function isEmptyLayoutCell(element) {
        if (!element) {
            return true;
        }

        const clone = element.cloneNode(true);
        clone.querySelectorAll('script, style').forEach(function(el) {
            el.remove();
        });

        const images = clone.querySelectorAll('img');
        for (let i = 0; i < images.length; i++) {
            const src = (images[i].getAttribute('src') || '').trim();
            if (src && !/^cid:/i.test(src)) {
                return false;
            }
        }

        const style = element.getAttribute('style') || '';
        if (/background-image:\s*url\(['"]?(?!cid:)/i.test(style)) {
            return false;
        }

        if (clone.querySelector('iframe, object, embed, video, ul, ol, li, h1, h2, h3, h4, h5, h6, a[href]')) {
            return false;
        }

        const nestedTables = clone.querySelectorAll('table');
        for (let t = 0; t < nestedTables.length; t++) {
            if (tableHasVisibleContent(nestedTables[t])) {
                return false;
            }
        }

        let text = clone.textContent || '';
        text = text.replace(/\u00a0/g, ' ').replace(/\s+/g, '').trim();

        return text.length === 0;
    }

    /**
     * Whether a table contains any non-spacer cell content.
     */
    function tableHasVisibleContent(table) {
        if (!table) {
            return false;
        }

        const cells = table.querySelectorAll('td, th');
        for (let i = 0; i < cells.length; i++) {
            if (!isEmptyLayoutCell(cells[i])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get direct table row cells (td/th only).
     */
    function getRowCells(row) {
        return Array.from(row.children).filter(function(el) {
            return el.tagName === 'TD' || el.tagName === 'TH';
        });
    }

    /**
     * Remove leading columns from a table when every row's cell in that column is empty.
     */
    function collapseLeadingEmptyTableColumns(table) {
        if (!table) {
            return;
        }

        const rows = Array.from(table.querySelectorAll(':scope > tbody > tr, :scope > tr'));
        if (rows.length === 0) {
            return;
        }

        let keepRemoving = true;

        while (keepRemoving) {
            keepRemoving = false;

            let columnIsEmpty = true;
            let columnExists = false;

            for (let r = 0; r < rows.length; r++) {
                const cells = getRowCells(rows[r]);
                if (cells.length === 0) {
                    continue;
                }

                columnExists = true;

                if (!isEmptyLayoutCell(cells[0])) {
                    columnIsEmpty = false;
                    break;
                }
            }

            if (columnExists && columnIsEmpty) {
                for (let r = 0; r < rows.length; r++) {
                    const cells = getRowCells(rows[r]);
                    if (cells.length > 0) {
                        cells[0].remove();
                    }
                }
                keepRemoving = true;
            }
        }
    }

    /**
     * Remove leading empty block elements before the first visible content node.
     */
    function removeLeadingEmptyBlockSpacers(container) {
        if (!container) {
            return;
        }

        const removableTags = { DIV: true, P: true, SPAN: true, CENTER: true };

        while (container.firstElementChild) {
            const first = container.firstElementChild;

            if (first.tagName === 'TABLE') {
                collapseLeadingEmptyTableColumns(first);
                if (tableHasVisibleContent(first)) {
                    break;
                }
                first.remove();
                continue;
            }

            if (!removableTags[first.tagName]) {
                break;
            }

            if (first.querySelector('table')) {
                first.querySelectorAll('table').forEach(collapseLeadingEmptyTableColumns);
            }

            if (isEmptyLayoutCell(first)) {
                first.remove();
                continue;
            }

            break;
        }
    }

    /**
     * Strip document wrappers and collapse leading empty layout spacers in HTML emails.
     * Only affects empty left columns/cells — preserves content columns (signatures, body, etc.).
     */
    function sanitizeEmailHtmlForDisplay(htmlContent) {
        if (!htmlContent || typeof htmlContent !== 'string') {
            return htmlContent;
        }

        const trimmed = htmlContent.trim();
        if (!trimmed || trimmed === '(No content)') {
            return htmlContent;
        }

        if (!/<[a-z][\s\S]*>/i.test(trimmed)) {
            return htmlContent;
        }

        try {
            const parser = new DOMParser();
            const doc = parser.parseFromString(trimmed, 'text/html');
            const root = doc.body;

            if (!root) {
                return htmlContent;
            }

            root.querySelectorAll('table').forEach(collapseLeadingEmptyTableColumns);
            removeLeadingEmptyBlockSpacers(root);

            const sanitized = root.innerHTML;
            return sanitized || htmlContent;
        } catch (error) {
            console.warn('sanitizeEmailHtmlForDisplay failed, using original HTML:', error);
            return htmlContent;
        }
    }

    /**
     * Replace cid: references in email HTML with actual preview URLs for inline attachments
     */
    function replaceCidReferences(htmlContent, attachments) {
        if (!htmlContent || !attachments || attachments.length === 0) {
            return htmlContent;
        }
        
        // Create a map of content_id to attachment for quick lookup
        const cidMap = {};
        attachments.forEach(att => {
            if (!att.id) return; // Skip if no attachment ID
            
            // Always add filename to map (case-insensitive) as fallback
            if (att.filename) {
                const filenameKey = att.filename.toLowerCase();
                cidMap[filenameKey] = att;
                // Also try without extension
                const filenameWithoutExt = filenameKey.replace(/\.[^.]+$/, '');
                if (filenameWithoutExt !== filenameKey) {
                    cidMap[filenameWithoutExt] = att;
                }
            }
            
            // If content_id exists, add it to map (normalized)
            if (att.content_id) {
                // Normalize content_id (remove < > brackets if present)
                const normalizedCid = att.content_id.replace(/^<|>$/g, '').trim();
                if (normalizedCid) {
                    cidMap[normalizedCid.toLowerCase()] = att;
                }
            }
        });
        
        // Replace cid: references in img src attributes
        // Pattern: cid:filename or cid:<content-id>
        htmlContent = htmlContent.replace(/src=["']cid:([^"'>]+)["']/gi, (match, cidValue) => {
            // Remove any brackets and normalize
            const normalizedCid = cidValue.replace(/^<|>$/g, '').trim().toLowerCase();
            
            // Try to find matching attachment
            let attachment = cidMap[normalizedCid];
            
            // If not found, try with the original value
            if (!attachment) {
                attachment = cidMap[cidValue.toLowerCase()];
            }
            
            const previewUrl = getInlineAttachmentPreviewUrl(attachment);
            if (previewUrl) {
                return `src="${previewUrl}"`;
            }

            // If not found or not previewable, return original (broken image will show)
            return match;
        });
        
        // Also handle background-image CSS with cid: references
        htmlContent = htmlContent.replace(/background-image:\s*url\(["']?cid:([^"')]+)["']?\)/gi, (match, cidValue) => {
            const normalizedCid = cidValue.replace(/^<|>$/g, '').trim().toLowerCase();
            let attachment = cidMap[normalizedCid] || cidMap[cidValue.toLowerCase()];

            const previewUrl = getInlineAttachmentPreviewUrl(attachment);
            if (previewUrl) {
                return `background-image: url("${previewUrl}")`;
            }

            return match;
        });
        
        return htmlContent;
    }

    /**
     * Clean recipient strings by removing Python object representations
     */
    function cleanRecipients(recipientString) {
        if (!recipientString) return '';
        
        // Split by comma to handle multiple recipients
        const recipients = recipientString.split(',');
        
        // Filter out invalid recipients (Python object strings, malformed addresses)
        const validRecipients = recipients
            .map(r => r.trim())
            .filter(r => {
                // Remove entries that look like Python object representations
                if (r.includes('<extract_msg.') || r.includes('object at 0x')) {
                    return false;
                }
                // Remove entries that look like raw object references
                if (r.includes('Recipient') && r.includes('0x')) {
                    return false;
                }
                // Keep only entries that look like valid email addresses or names
                return r.length > 0 && !r.startsWith('<') && !r.includes('0x');
            });
        
        // Return cleaned recipient list or a placeholder if none are valid
        return validRecipients.length > 0 ? validRecipients.join(', ') : '';
    }

    /**
     * Escape HTML to prevent XSS
     */
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, m => map[m]);
    }

    // =========================================================================
    // Pagination
    // =========================================================================

    function initializePagination() {
        const prevBtn = document.getElementById('prevBtn');
        const nextBtn = document.getElementById('nextBtn');

        if (prevBtn) {
            prevBtn.addEventListener('click', function() {
                if (currentPage > 1) {
                    currentPage--;
                    loadEmailsFromServer();
                }
            });
        }

        if (nextBtn) {
            nextBtn.addEventListener('click', function() {
                if (currentPage < lastPage) {
                    currentPage++;
                    loadEmailsFromServer();
                }
            });
        }
    }

    // =========================================================================
    // Context Menu Management
    // =========================================================================

    let currentContextEmail = null; // Store email object for context menu actions

    /**
     * Format reply subject (add "Re:" prefix if not already present)
     */
    function formatReplySubject(originalSubject) {
        if (!originalSubject) return 'Re:';
        const subject = originalSubject.trim();
        if (subject.toLowerCase().startsWith('re:')) {
            return subject;
        }
        return 'Re: ' + subject;
    }

    /**
     * Format forward subject (add "Fwd:" prefix if not already present)
     */
    function formatForwardSubject(originalSubject) {
        if (!originalSubject) return 'Fwd:';
        const subject = originalSubject.trim();
        if (subject.toLowerCase().startsWith('fwd:') || subject.toLowerCase().startsWith('fw:')) {
            return subject;
        }
        return 'Fwd: ' + subject;
    }

    /**
     * Format quoted message for reply/forward
     */
    function formatQuotedMessage(email, isForward = false) {
        const from = email.from_mail || 'Unknown';
        const to = cleanRecipients(email.to_mail) || 'Unknown';
        const cc = cleanRecipients(email.cc) || '';
        const date = formatDate(getEmailDate(email));
        const subject = email.subject || '(No subject)';
        const message = email.message || '(No content)';
        
        let quotedText = '';
        
        if (isForward) {
            // Forward format with headers
            quotedText = '\n\n---------- Forwarded message ----------\n';
            quotedText += 'From: ' + from + '\n';
            quotedText += 'To: ' + to + '\n';
            if (cc) {
                quotedText += 'Cc: ' + cc + '\n';
            }
            quotedText += 'Date: ' + date + '\n';
            quotedText += 'Subject: ' + subject + '\n\n';
        } else {
            // Reply format (simpler)
            quotedText = '\n\n';
        }
        
        // Add original message with quote markers
        quotedText += 'On ' + date + ', ' + from + ' wrote:\n';
        quotedText += '> ' + message.replace(/\n/g, '\n> ');
        
        return quotedText;
    }

    /**
     * Extract email address from a string (handles "Name <email@domain.com>" format)
     */
    function extractEmailAddress(emailString) {
        if (!emailString) return '';
        
        // Try to extract email from angle brackets
        const match = emailString.match(/<([^>]+)>/);
        if (match) {
            return match[1].trim();
        }
        
        // If no brackets, check if it's a valid email
        if (emailString.includes('@')) {
            return emailString.trim();
        }
        
        return emailString.trim();
    }

    /**
     * Get current matter ID from the matter dropdown
     */
    function getCurrentMatterIdFromDropdown() {
        const matterDropdown = document.getElementById('sel_matter_id_client_detail');
        if (matterDropdown && matterDropdown.value) {
            return matterDropdown.value;
        }
        // Fallback: try to get from email interface container
        return getMatterId();
    }

    const COMPANY_EMAIL_DOMAINS = [
        '@bansalimmigration.com.au',
        '@bansaleducation.com.au',
        '@bansallawyers.com.au'
    ];

    /**
     * Whether an email was sent from the CRM or uploaded as outbound mail.
     */
    function isSentEmail(email) {
        return currentMailType === 'sent' || email.mail_body_type === 'sent';
    }

    function isFromCompanyDomain(emailAddress) {
        const addr = extractEmailAddress(emailAddress).toLowerCase();
        return COMPANY_EMAIL_DOMAINS.some(domain => addr.includes(domain));
    }

    function hasSentLabel(email) {
        if (!email.labels || !Array.isArray(email.labels)) {
            return false;
        }
        return email.labels.some(label => (label.name || '').toLowerCase() === 'sent');
    }

    /**
     * Sent / outbound mail: reply to original To. Inbox / inbound: reply to original From.
     */
    function shouldReplyToOriginalRecipient(email) {
        return isSentEmail(email)
            || hasSentLabel(email)
            || isFromCompanyDomain(email.from_mail);
    }

    /**
     * Reply targets: inbox -> original sender; sent/outbound -> original recipient(s).
     */
    function getReplyRecipientAddresses(email) {
        const raw = shouldReplyToOriginalRecipient(email)
            ? (email.to_mail || '')
            : (email.from_mail || '');
        if (!raw) {
            return [];
        }
        return raw.split(',')
            .map(part => extractEmailAddress(part.trim()))
            .filter(addr => addr && addr.includes('@'));
    }

    /**
     * Look up a CRM client/lead record by email for the compose To dropdown.
     */
    async function fetchRecipientByEmail(emailAddress) {
        const query = extractEmailAddress(emailAddress);
        if (!query) {
            return null;
        }

        const getRecipientsUrl = window.ClientDetailConfig
            && window.ClientDetailConfig.urls
            && window.ClientDetailConfig.urls.getRecipients;

        if (!getRecipientsUrl) {
            return null;
        }

        try {
            const response = await fetch(getRecipientsUrl + '?q=' + encodeURIComponent(query), {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            });

            if (!response.ok) {
                return null;
            }

            const payload = await response.json();
            const items = payload.items || [];
            const queryLower = query.toLowerCase();
            const exact = items.find(item => (item.email || '').toLowerCase() === queryLower);
            return exact || (items.length ? items[0] : null);
        } catch (error) {
            console.error('Recipient lookup failed:', error);
            return null;
        }
    }

    /**
     * Build Tom Select recipient row when address is not in CRM (backend accepts raw email fallback).
     */
    function buildExternalRecipient(emailAddress) {
        const addr = extractEmailAddress(emailAddress);
        return {
            id: addr,
            name: addr,
            email: addr,
            status: 'External'
        };
    }

    /**
     * Resolve email address(es) to CRM records and populate the compose To mmSelect.
     */
    async function resolveAndSetComposeTo(emailAddresses) {
        if (!emailAddresses || !emailAddresses.length || typeof jQuery === 'undefined') {
            return;
        }

        const selectedIds = [];
        const recipients = [];

        for (const raw of emailAddresses) {
            const addr = extractEmailAddress(raw);
            if (!addr) {
                continue;
            }

            let recipient = await fetchRecipientByEmail(addr);
            if (!recipient) {
                recipient = buildExternalRecipient(addr);
            }

            const id = String(recipient.id);
            if (selectedIds.includes(id)) {
                continue;
            }

            selectedIds.push(id);
            recipients.push(recipient);
        }

        if (!selectedIds.length) {
            return;
        }

        if (typeof window.initComposeEmailToField === 'function') {
            window.initComposeEmailToField(selectedIds, recipients);
            return;
        }

        // Fallback when detail-main.js helpers are unavailable (e.g. lead detail page).
        const $toSelect = jQuery('#emailmodal .js-data-example-ajax');
        if (!$toSelect.length || typeof jQuery.fn.mmSelect === 'undefined') {
            return;
        }
        if ($toSelect[0].tomselect) {
            $toSelect.mmSelect('destroy');
        }
        const data = recipients.map(recipient => {
            const name = recipient.name || recipient.email || recipient.id;
            const email = recipient.email || '';
            const status = recipient.status || 'Client';
            return {
                id: String(recipient.id),
                text: name,
                html: "<div class='mm-result-repository ag-flex ag-space-between ag-align-center'>" +
                    "<div class='ag-flex ag-align-start'><div class='ag-flex ag-flex-column col-hr-1'><div class='ag-flex'>" +
                    "<span class='mm-result-repository__title text-semi-bold'>" + escapeHtml(name) + "</span></div>" +
                    "<div class='ag-flex ag-align-center'><small class='mm-result-repository__description'>" + escapeHtml(email) + "</small></div>" +
                    "</div></div>" +
                    "<div class='ag-flex ag-flex-column ag-align-end'>" +
                    "<span class='ui label yellow mm-result-repository__statistics'>" + escapeHtml(status) + "</span>" +
                    "</div></div>",
                title: name
            };
        });
        const getRecipientsUrl = window.ClientDetailConfig?.urls?.getRecipients;
        const config = {
            multiple: true,
            closeOnSelect: false,
            dropdownParent: jQuery('body'),
            dropdownCssClass: 'mm-compose-email-recipients-dropdown',
            data: data,
            escapeMarkup: markup => markup,
            templateResult: d => d.html,
            templateSelection: d => d.text
        };
        if (getRecipientsUrl) {
            config.ajax = {
                url: getRecipientsUrl,
                dataType: 'json',
                data: function (params) {
                    return { q: params.term || '' };
                },
                processResults: ajaxData => ({ results: ajaxData.items || [] }),
                cache: true
            };
        }
        $toSelect.mmSelect(config);
        $toSelect.val(selectedIds).trigger('change');
        jQuery('#emailmodal').data('composeToFieldCustomized', true);
    }

    /**
     * Clear and restore the compose To field to default ajax search.
     */
    function clearComposeToField() {
        if (typeof window.resetComposeEmailToField === 'function') {
            window.resetComposeEmailToField();
        }
        if (typeof jQuery !== 'undefined') {
            const $toSelect = jQuery('#emailmodal .js-data-example-ajax');
            if ($toSelect.length) {
                $toSelect.val(null).trigger('change');
            }
        }
    }

    /**
     * Match and select a SendGrid From address after the modal opens.
     */
    function setComposeFromEmail(emailAddress) {
        const addr = extractEmailAddress(emailAddress);
        if (!addr) {
            return;
        }
        const fromSelect = document.querySelector('#emailmodal .email-from-sendgrid, #emailmodal select[name="email_from"]');
        if (!fromSelect) {
            return;
        }
        const match = Array.from(fromSelect.options).find(
            opt => (opt.value || '').toLowerCase() === addr.toLowerCase()
        );
        if (match) {
            fromSelect.value = match.value;
        }
    }

    /**
     * Open compose modal and populate fields
     */
    function openComposeModal(data) {
        const modal = document.getElementById('emailmodal');
        if (!modal) {
            showNotification('Compose email modal not found. Please ensure you are on the client detail page.', 'error');
            return;
        }

        // Skip default template auto-load on the client detail page so quoted reply/forward body is not overwritten.
        if (typeof jQuery !== 'undefined') {
            jQuery('#emailmodal').data('preserveReplyForwardBody', true);
        }

        // Always set matter ID - use provided one or get from dropdown
        const matterIdInput = document.getElementById('compose_client_matter_id');
        if (matterIdInput) {
            const matterId = data.matterId || getCurrentMatterIdFromDropdown();
            if (matterId) {
                matterIdInput.value = matterId;
            }
        }

        // Set subject
        const subjectInput = document.getElementById('compose_email_subject');
        if (subjectInput && data.subject) {
            subjectInput.value = data.subject;
        }

        // Set message (for TinyMCE editor)
        const messageTextarea = document.querySelector('#compose_email_message');
        if (messageTextarea && data.message) {
            // Fill textarea immediately so content is not blank while TinyMCE initializes (~100ms after shown).
            messageTextarea.value = data.message;
            const setMessageContent = () => {
                if (typeof tinymce !== 'undefined' && tinymce.get('compose_email_message')) {
                    try {
                        tinymce.get('compose_email_message').setContent(messageTextarea.value);
                    } catch (e) {
                        /* editor not ready */
                    }
                }
            };
            // Defer sync into TinyMCE until after detail.blade.php initTinyMCEForModals (100ms timeout).
            const delayMs = 180;
            const scheduleBody = () => setTimeout(setMessageContent, delayMs);
            if (modal.classList.contains('show') || modal.style.display === 'block') {
                scheduleBody();
            } else if (typeof jQuery !== 'undefined') {
                jQuery(modal).one('shown.bs.modal', scheduleBody);
            } else {
                modal.addEventListener('shown.bs.modal', scheduleBody, { once: true });
            }
        }

        // Set To / From on modal shown (Tom Select + SendGrid From need the modal DOM ready)
        const applyComposeRecipients = () => {
            if (data.to && data.to.length > 0) {
                resolveAndSetComposeTo(data.to);
            } else {
                clearComposeToField();
            }
            if (data.from) {
                setTimeout(() => setComposeFromEmail(data.from), 150);
            }
        };

        if (modal.classList.contains('show') || modal.style.display === 'block') {
            setTimeout(applyComposeRecipients, 50);
        } else if (typeof jQuery !== 'undefined') {
            jQuery(modal).one('shown.bs.modal', applyComposeRecipients);
        } else {
            modal.addEventListener('shown.bs.modal', applyComposeRecipients, { once: true });
        }

        // Open modal using Bootstrap
        if (typeof jQuery !== 'undefined') {
            jQuery(modal).modal('show');
        } else if (typeof bootstrap !== 'undefined') {
            const bsModal = new bootstrap.Modal(modal);
            bsModal.show();
        } else {
            // Fallback: just show the modal
            modal.style.display = 'block';
            modal.classList.add('show');
        }
    }

    /**
     * Handle Reply action
     */
    function handleReply(email) {
        if (!email) {
            showNotification('No email selected for reply', 'error');
            return;
        }

        const recipientAddresses = getReplyRecipientAddresses(email);
        if (!recipientAddresses.length) {
            showNotification('Could not extract recipient email address', 'error');
            return;
        }

        // Get matter ID
        const matterId = getMatterId();

        // Format subject
        const replySubject = formatReplySubject(email.subject);

        // Format message with quoted original
        const replyMessage = formatQuotedMessage(email, false);

        const composeData = {
            to: recipientAddresses,
            subject: replySubject,
            message: replyMessage,
            matterId: matterId
        };

        // Outbound mail: pre-select the mailbox that sent the original email
        if (shouldReplyToOriginalRecipient(email) && email.from_mail) {
            composeData.from = email.from_mail;
        }

        openComposeModal(composeData);

        showNotification('Reply email opened', 'info');
    }

    /**
     * Handle Forward action
     */
    function handleForward(email) {
        if (!email) {
            showNotification('No email selected for forward', 'error');
            return;
        }

        const recipientAddresses = getReplyRecipientAddresses(email);
        if (!recipientAddresses.length) {
            showNotification('Could not extract recipient email address', 'error');
            return;
        }

        // Get matter ID
        const matterId = getMatterId();

        // Format subject
        const forwardSubject = formatForwardSubject(email.subject);

        // Format message with forwarded content
        const forwardMessage = formatQuotedMessage(email, true);

        const composeData = {
            to: recipientAddresses,
            subject: forwardSubject,
            message: forwardMessage,
            matterId: matterId
        };

        // Outbound mail: pre-select the mailbox that sent the original email
        if (shouldReplyToOriginalRecipient(email) && email.from_mail) {
            composeData.from = email.from_mail;
        }

        openComposeModal(composeData);

        showNotification('Forward email opened', 'info');
    }

    /**
     * Handle Delete email action (admin only - option shown based on server-side check)
     */
    async function handleDeleteEmail(email) {
        if (!email || !email.id) {
            showNotification('No email selected for delete', 'error');
            return;
        }

        if (!confirm('Are you sure you want to delete this email? This action cannot be undone.')) {
            return;
        }

        try {
            const payload = {};
            const matterId = getMatterId();
            const clientId = getClientId();
            if (matterId) {
                payload.client_matter_id = matterId;
            }
            if (clientId) {
                payload.client_id = clientId;
            }

            // POST avoids 403 from some proxies/WAFs that block HTTP DELETE.
            const response = await fetch(`/email-logs/${email.id}/delete`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken()
                },
                body: JSON.stringify(payload)
            });

            const data = await response.json().catch(() => ({}));

            if (response.ok && data.success) {
                showNotification('Email deleted successfully', 'success');
                // Reset content pane to placeholder when the viewed email was deleted
                const emailContentView = document.getElementById('emailContentView');
                const emailContentPlaceholder = document.getElementById('emailContentPlaceholder');
                if (emailContentView && emailContentPlaceholder) {
                    emailContentView.style.display = 'none';
                    emailContentPlaceholder.style.display = 'block';
                }
                loadEmailsFromServer();
            } else {
                const message = data.message || `Failed to delete email (${response.status})`;
                showNotification(message, 'error');
            }
        } catch (error) {
            console.error('Error deleting email:', error);
            showNotification('Failed to delete email: ' + error.message, 'error');
        }
    }

    /**
     * Show context menu at specified coordinates
     */
    function showContextMenu(x, y, email) {
        const contextMenu = document.getElementById('emailContextMenu');
        const overlay = document.getElementById('contextMenuOverlay');
        
        if (!contextMenu || !overlay) return;
        
        // Store current email
        currentContextEmail = email;
        
        // Position menu
        contextMenu.style.display = 'block';
        contextMenu.style.left = x + 'px';
        contextMenu.style.top = y + 'px';
        
        // Show overlay
        overlay.style.display = 'block';
        
        // Adjust menu position if it goes off-screen
        setTimeout(() => {
            const rect = contextMenu.getBoundingClientRect();
            const windowWidth = window.innerWidth;
            const windowHeight = window.innerHeight;
            
            if (rect.right > windowWidth) {
                contextMenu.style.left = (x - rect.width) + 'px';
            }
            if (rect.bottom > windowHeight) {
                contextMenu.style.top = (y - rect.height) + 'px';
            }
        }, 0);
    }

    /**
     * Hide context menu
     */
    function hideContextMenu() {
        const contextMenu = document.getElementById('emailContextMenu');
        const submenu = document.getElementById('labelSubmenu');
        const overlay = document.getElementById('contextMenuOverlay');
        
        if (contextMenu) contextMenu.style.display = 'none';
        if (submenu) submenu.style.display = 'none';
        if (overlay) overlay.style.display = 'none';
        
        currentContextEmail = null;
    }

    /**
     * Show label submenu
     */
    function showLabelSubmenu() {
        const contextMenu = document.getElementById('emailContextMenu');
        const submenu = document.getElementById('labelSubmenu');
        const labelContent = document.getElementById('labelSubmenuContent');
        
        if (!submenu || !labelContent || !currentContextEmail) return;
        
        // Get context menu position before hiding it
        const rect = contextMenu.getBoundingClientRect();
        
        // Hide main context menu
        contextMenu.style.display = 'none';
        
        // Position submenu next to context menu
        submenu.style.display = 'block';
        submenu.style.left = (rect.right + 2) + 'px';
        submenu.style.top = rect.top + 'px';
        
        // Get current email labels
        const currentLabels = currentContextEmail.labels || [];
        const currentLabelIds = currentLabels.map(l => l.id);
        
        // Filter out already applied labels
        const filteredLabels = availableLabels.filter(label => {
            return !currentLabelIds.includes(label.id);
        });
        
        // Build label options HTML
        if (filteredLabels.length === 0) {
            labelContent.innerHTML = `
                <div class="submenu-empty">
                    <p>All available labels are already applied</p>
                </div>
            `;
        } else {
            labelContent.innerHTML = filteredLabels.map(label => {
                const isApplied = currentLabelIds.includes(label.id);
                const icon = label.icon || 'tag';
                const color = label.color || '#3B82F6';
                
                return `
                    <div class="submenu-item ${isApplied ? 'applied' : ''}" 
                         data-label-id="${label.id}" 
                         data-label-name="${escapeHtml(label.name)}">
                        <span class="submenu-item-badge" style="background-color: ${color}20; border-color: ${color}; color: ${color}">
                            ${renderLabelIcon(icon)}
                        </span>
                        <span class="submenu-item-text">${escapeHtml(label.name)}</span>
                        ${isApplied ? (typeof crmIconAny === 'function' ? crmIconAny('check', { class: 'submenu-item-check' }) : '<i class="fas fa-check submenu-item-check"></i>') : ''}
                    </div>
                `;
            }).join('');
            
            // Add click handlers
            labelContent.querySelectorAll('.submenu-item').forEach(item => {
                item.addEventListener('click', async function() {
                    const labelId = this.dataset.labelId;
                    const labelName = this.dataset.labelName;
                    const isApplied = this.classList.contains('applied');
                    
                    if (isApplied) {
                        // Already applied (shouldn't happen due to filter, but handle it)
                        return;
                    }
                    
                    // Apply label
                    const success = await applyLabel(currentContextEmail.id, labelId);
                    if (success) {
                        // Reload email list to show updated labels
                        loadEmailsFromServer();
                        hideContextMenu();
                    }
                });
            });
        }
        
        // Back button handler
        const backBtn = submenu.querySelector('.submenu-back');
        if (backBtn) {
            backBtn.onclick = function() {
                submenu.style.display = 'none';
                contextMenu.style.display = 'block';
            };
        }
        
        // Adjust submenu position if it goes off-screen
        setTimeout(() => {
            const submenuRect = submenu.getBoundingClientRect();
            const windowWidth = window.innerWidth;
            
            if (submenuRect.right > windowWidth) {
                submenu.style.left = (rect.left - submenuRect.width) + 'px';
            }
        }, 0);
    }

    /**
     * Initialize context menu handlers
     */
    function initializeContextMenu() {
        const contextMenu = document.getElementById('emailContextMenu');
        const overlay = document.getElementById('contextMenuOverlay');
        
        if (!contextMenu || !overlay) return;
        
        // Handle menu item clicks
        contextMenu.addEventListener('click', function(e) {
            const item = e.target.closest('.context-menu-item');
            if (!item) return;
            
            const action = item.dataset.action;
            
            switch (action) {
                case 'apply-label':
                    showLabelSubmenu();
                    break;
                case 'reply':
                    if (currentContextEmail) {
                        handleReply(currentContextEmail);
                    }
                    hideContextMenu();
                    break;
                case 'forward':
                    if (currentContextEmail) {
                        handleForward(currentContextEmail);
                    }
                    hideContextMenu();
                    break;
                case 'delete':
                    if (currentContextEmail) {
                        handleDeleteEmail(currentContextEmail);
                    }
                    hideContextMenu();
                    break;
                default:
                    hideContextMenu();
            }
        });
        
        // Close menu when clicking overlay or outside
        overlay.addEventListener('click', hideContextMenu);
        
        // Close menu on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                hideContextMenu();
            }
        });
        
        // Close menu on scroll
        document.addEventListener('scroll', hideContextMenu, true);
    }

    // =========================================================================
    // Label Management
    // =========================================================================

    /**
     * Fetch all labels from API
     */
    async function fetchLabels() {
        try {
            const response = await fetch('/email-labels', {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken()
                }
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();
            if (data.success && Array.isArray(data.labels)) {
                availableLabels = data.labels;
                populateLabelFilter();
            }
        } catch (error) {
            console.error('Error fetching labels:', error);
        }
    }

    /**
     * Populate label filter dropdown
     */
    function populateLabelFilter() {
        const labelFilter = document.getElementById('labelFilter');
        if (!labelFilter) return;

        // Clear existing options (except "All Labels")
        while (labelFilter.options.length > 1) {
            labelFilter.remove(1);
        }

        // Add label options
        availableLabels.forEach(label => {
            const option = document.createElement('option');
            option.value = label.id;
            option.textContent = label.name;
            labelFilter.appendChild(option);
        });
    }

    /**
     * Label creation removed - labels are now managed in Admin Console
     * Use /adminconsole/features/email-labels to create/edit labels
     * Frontend only handles filtering and applying existing labels
     */

    /**
     * Apply label to email
     */
    async function applyLabel(mailReportId, labelId) {
        try {
            const response = await fetch('/email-labels/apply', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken()
                },
                body: JSON.stringify({ mail_report_id: mailReportId, label_id: labelId })
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();
            if (data.success) {
                showNotification('Label applied successfully', 'success');
                return true;
            } else {
                throw new Error(data.message || 'Failed to apply label');
            }
        } catch (error) {
            console.error('Error applying label:', error);
            showNotification('Error applying label: ' + error.message, 'error');
            return false;
        }
    }

    /**
     * Remove label from email
     */
    async function removeLabel(mailReportId, labelId) {
        try {
            const response = await fetch('/email-labels/remove', {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken()
                },
                body: JSON.stringify({ mail_report_id: mailReportId, label_id: labelId })
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();
            if (data.success) {
                showNotification('Label removed successfully', 'success');
                return true;
            } else {
                throw new Error(data.message || 'Failed to remove label');
            }
        } catch (error) {
            console.error('Error removing label:', error);
            showNotification('Error removing label: ' + error.message, 'error');
            return false;
        }
    }

    // =========================================================================
    // Attachment Handling
    // =========================================================================

    /**
     * Download individual attachment
     */
    async function downloadAttachment(attachmentId, filename) {
        try {
            const response = await fetch(`/mail-attachments/${attachmentId}/download`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/octet-stream'
                }
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const blob = await response.blob();
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);

            showNotification(`Downloaded: ${filename}`, 'success');
        } catch (error) {
            console.error('Error downloading attachment:', error);
            showNotification('Error downloading attachment: ' + error.message, 'error');
        }
    }

    /**
     * Download all attachments as ZIP
     */
    async function downloadAllAttachments(mailReportId, emailSubject) {
        try {
            const response = await fetch(`/mail-attachments/email/${mailReportId}/download-all`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/octet-stream'
                }
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const blob = await response.blob();
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            const sanitizedSubject = sanitizeFilename(emailSubject || 'email');
            a.download = `${sanitizedSubject}_attachments.zip`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);

            showNotification('Attachments downloaded successfully', 'success');
        } catch (error) {
            console.error('Error downloading attachments:', error);
            showNotification('Error downloading attachments: ' + error.message, 'error');
        }
    }

    /**
     * Preview attachment
     */
    async function previewAttachment(attachmentId, filename) {
        try {
            const previewUrl = `/mail-attachments/${attachmentId}/preview`;
            const modal = document.getElementById('attachmentPreviewModal');
            const frame = document.getElementById('previewFrame');
            const filenameEl = document.getElementById('previewFileName');

            if (modal && frame && filenameEl) {
                filenameEl.textContent = filename;
                frame.src = previewUrl;
                modal.style.display = 'flex';
            }
        } catch (error) {
            console.error('Error previewing attachment:', error);
            showNotification('Error previewing attachment: ' + error.message, 'error');
        }
    }

    // =========================================================================
    // Initialization
    // =========================================================================

    // Initialize pagination on load
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializePagination);
    } else {
        initializePagination();
    }

    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeNewFeatures);
    } else {
        initializeNewFeatures();
    }

    /**
     * Initialize new filter and modal features
     */
    function initializeNewFeatures() {
        // Restore mail type before filters bind and initial email load
        const mailTypeFilter = document.getElementById('mailTypeFilter');
        if (mailTypeFilter && typeof window.restoreEmailMailType === 'function') {
            window.restoreEmailMailType();
        }

        // Fetch labels on load
        fetchLabels();

        // Initialize context menu
        initializeContextMenu();

        // Mail type filter (Inbox/Sent)
        if (mailTypeFilter) {
            mailTypeFilter.addEventListener('change', function() {
                window.setEmailMailType(this.value);
                loadEmailsFromServer();
            });
        }

        // Label filter
        const labelFilter = document.getElementById('labelFilter');
        if (labelFilter) {
            labelFilter.addEventListener('change', function() {
                currentLabelId = this.value;
            });
        }

        // Apply button removed - all filters auto-apply:
        // - Search auto-applies as you type (debounced)
        // - Label filter auto-applies on change
        // - Mail type filter auto-applies on change

        // Label creation removed - now managed in Admin Console
        // Labels can only be created via /adminconsole/features/email-labels

        // Preview modal close
        const closePreviewBtn = document.getElementById('closePreviewBtn');
        const previewOverlay = document.getElementById('previewOverlay');
        if (closePreviewBtn) {
            closePreviewBtn.addEventListener('click', hidePreviewModal);
        }
        if (previewOverlay) {
            previewOverlay.addEventListener('click', hidePreviewModal);
        }

        // Initialize attachment handlers
        initializeAttachmentHandlers();

        // Auto-set matter ID when compose modal opens (for all email composes)
        const composeModal = document.getElementById('emailmodal');
        if (composeModal) {
            if (typeof jQuery !== 'undefined') {
                jQuery(composeModal)
                    .off('hidden.bs.modal.preserveReplyForward')
                    .on('hidden.bs.modal.preserveReplyForward', function() {
                        jQuery(this).removeData('preserveReplyForwardBody');
                        if (typeof window.resetComposeEmailToField === 'function') {
                            window.resetComposeEmailToField();
                        }
                        if (typeof window.resetComposeEmailCcField === 'function') {
                            window.resetComposeEmailCcField();
                        }
                    });
            }
            // Listen for modal show event (Bootstrap 4)
            if (typeof jQuery !== 'undefined') {
                jQuery(composeModal).on('show.bs.modal', function() {
                    const matterIdInput = document.getElementById('compose_client_matter_id');
                    if (matterIdInput && !matterIdInput.value) {
                        // Only set if not already set (to preserve reply/forward matter ID)
                        const matterId = getCurrentMatterIdFromDropdown();
                        if (matterId) {
                            matterIdInput.value = matterId;
                        }
                    }
                });
            }
            // Also listen for native modal show event
            composeModal.addEventListener('show.bs.modal', function() {
                const matterIdInput = document.getElementById('compose_client_matter_id');
                if (matterIdInput && !matterIdInput.value) {
                    // Only set if not already set (to preserve reply/forward matter ID)
                    const matterId = getCurrentMatterIdFromDropdown();
                    if (matterId) {
                        matterIdInput.value = matterId;
                    }
                }
            });
        }
    }

    /**
     * Event delegation for attachment buttons
     * Handles all attachment-related clicks
     */
    function initializeAttachmentHandlers() {
        // Single delegated listener for all attachment actions
        document.addEventListener('click', function(e) {
            const target = e.target.closest('button');
            if (!target) return;

            // Download individual attachment
            if (target.classList.contains('download-attachment-btn')) {
                e.preventDefault();
                const attachmentId = target.dataset.attachmentId;
                const filename = target.dataset.filename;
                
                if (attachmentId && filename) {
                    // Disable button during download
                    const originalHtml = target.innerHTML;
                    target.disabled = true;
                    target.innerHTML = (typeof crmIconAny === 'function' ? crmIconAny('fas fa-spinner fa-spin') : '<i class="fas fa-spinner fa-spin"></i>') + ' Downloading...';
                    
                    downloadAttachment(attachmentId, filename).finally(() => {
                        target.disabled = false;
                        target.innerHTML = originalHtml;
                    });
                }
            }

            // Preview attachment
            if (target.classList.contains('preview-attachment-btn')) {
                e.preventDefault();
                const attachmentId = target.dataset.attachmentId;
                const filename = target.dataset.filename;
                
                if (attachmentId && filename) {
                    previewAttachment(attachmentId, filename);
                }
            }

            // Download all attachments as ZIP
            if (target.classList.contains('download-all-btn')) {
                e.preventDefault();
                const mailReportId = target.dataset.mailReportId;
                const emailSubject = target.dataset.emailSubject;
                
                if (mailReportId) {
                    // Disable button during download
                    const originalHtml = target.innerHTML;
                    target.disabled = true;
                    target.innerHTML = (typeof crmIconAny === 'function' ? crmIconAny('fas fa-spinner fa-spin') : '<i class="fas fa-spinner fa-spin"></i>') + ' Creating ZIP...';
                    
                    downloadAllAttachments(mailReportId, emailSubject).finally(() => {
                        target.disabled = false;
                        target.innerHTML = originalHtml;
                    });
                }
            }
        });
    }

    /**
     * Label creation functions removed - labels are now managed in Admin Console
     * Navigate to /adminconsole/features/email-labels to create/edit labels
     */

    function canSendEmailBodiesToS3() {
        const container = document.querySelector('.email-interface-container');
        return container && container.dataset.canSendEmailBodiesToS3 === '1';
    }

    window.initializeSendBodiesToS3Button = function() {
        const button = document.getElementById('sendEmailBodiesToS3Btn');
        if (!button || !canSendEmailBodiesToS3()) {
            return;
        }

        if (button.dataset.bound === '1') {
            return;
        }
        button.dataset.bound = '1';

        button.addEventListener('click', async function() {
            const matterId = getMatterId();
            if (!matterId) {
                showNotification('Please select a matter first.', 'error');
                return;
            }

            const confirmed = window.confirm(
                'Do u want to Send All Email Body To S3 From Db for this matter? If Yes then Action will not Redone.'
            );
            if (!confirmed) {
                return;
            }

            const originalText = button.textContent;
            button.disabled = true;
            button.textContent = 'Processing...';

            try {
                const response = await fetch('/clients/email/send-bodies-to-s3', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': getCsrfToken(),
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ client_matter_id: matterId })
                });

                const data = await response.json();

                if (data.already_archived) {
                    alert('All emails are already moved to S3 from db');
                    return;
                }

                if (!response.ok || !data.status) {
                    throw new Error(data.message || 'Failed to send email bodies to S3.');
                }

                showNotification(data.message || 'Email bodies sent to S3 successfully.', 'success');

                if (typeof window.loadEmails === 'function') {
                    window.loadEmails();
                }
            } catch (error) {
                console.error('Send bodies to S3 failed:', error);
                showNotification(error.message || 'Failed to send email bodies to S3.', 'error');
            } finally {
                button.disabled = false;
                button.textContent = originalText;
            }
        });
    };

    /**
     * Hide preview modal
     */
    function hidePreviewModal() {
        const modal = document.getElementById('attachmentPreviewModal');
        const frame = document.getElementById('previewFrame');
        if (modal && frame) {
            modal.style.display = 'none';
            frame.src = ''; // Stop loading
        }
    }

    // Add CSS animations
    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes slideOut {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }
    `;
    document.head.appendChild(style);

    console.log('Emails module loaded');

})();

