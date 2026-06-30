/**
 * Ledger and Office Receipt Drag Drop module
 * Extracted from detail-main.js - Phase 3j refactoring.
 * Requires: jQuery, listOfInvoice (invoices.js), clientLedgerBalanceAmount (accounts.js), safeParseJsonResponse (detail-main.js)
 */
(function($) {
    'use strict';
    if (!$) return;

    $(document).ready(function() {
        // --- DRAG AND DROP: Client Funds Ledger Form ---
        
        
        function initLedgerDragDrop() {
            
            var $zone = $('#ledgerDragDropZone');
            if ($zone.length === 0) {
                console.warn('⚠️ Ledger drag zone not found');
                return;
            }
            
            
            // Remove all existing handlers
            $zone.off('click dragenter dragover dragleave drop');
            $(document).off('dragover.ledger dragenter.ledger');
            
            // Prevent default drag behaviors
            $(document).on('dragover.ledger dragenter.ledger', '#createreceiptmodal', function(e) {
                e.preventDefault();
                e.stopPropagation();
            });
            
            // DIRECT BINDING to ledger drag zone for priority
            $zone.on('dragenter', function(e) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                $(this).addClass('drag_over');
                return false;
            });
            
            $zone.on('dragover', function(e) {
                var event = e.originalEvent || e;
                event.preventDefault();
                event.stopPropagation();
                
                if (event.dataTransfer) {
                    event.dataTransfer.dropEffect = 'copy';
                }
                
                $(this).addClass('drag_over');
                return false;
            });

            $zone.on('dragleave', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                // Only remove highlight if actually leaving the zone
                var rect = this.getBoundingClientRect();
                var x = e.originalEvent.clientX;
                var y = e.originalEvent.clientY;
                
                if (x <= rect.left || x >= rect.right || y <= rect.top || y >= rect.bottom) {
                    $(this).removeClass('drag_over');
                }
                return false;
            });

            $zone.on('drop', function(e) {
                var event = e.originalEvent || e;
                event.preventDefault();
                event.stopPropagation();
                event.stopImmediatePropagation();
                
                $(this).removeClass('drag_over');
                
                var files = event.dataTransfer ? event.dataTransfer.files : null;
                if (files && files.length > 0) {
                    handleLedgerFilesDrop(files);
                } else {
                    console.error('❌ No files in drop event');
                }
                return false;
            });

            // Click to browse
            $zone.on('click', function(e) {
                e.preventDefault();
                if (!$(e.target).closest('.remove-file, .remove-all-files').length) {
                    $('.docclientreceiptupload').click();
                }
            });
            
        }
        
        // Initialize when modal is shown
        $('#createreceiptmodal').on('shown.bs.modal', function() {
            setTimeout(initLedgerDragDrop, 100);
        });
        
        // Also initialize on page load (in case modal is already open)
        initLedgerDragDrop();
        
        // File input change handler (for when user clicks to browse) - enhanced
        $(document).on('change', '.docclientreceiptupload', function() {
            var files = this.files;
            if (files && files.length > 0) {
                displayLedgerSelectedFiles(files);
                updateFileSelectionHint(files);
            } else {
                clearLedgerFiles();
            }
        });
        
        // Remove individual file
        $(document).on('click', '.ledger-remove-file', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var fileIndex = $(this).data('file-index');
            removeLedgerFile(fileIndex);
        });
        
        // Remove all files
        $(document).on('click', '.remove-all-files', function(e) {
            e.preventDefault();
            e.stopPropagation();
            clearLedgerFiles();
        });
        
        // Function to handle dropped files
        function handleLedgerFilesDrop(files) {
            var validFiles = [];
            var fileInput = $('.docclientreceiptupload')[0];
            var existingFiles = fileInput.files ? Array.from(fileInput.files) : [];
            
            // Validate each file
            for (var i = 0; i < files.length; i++) {
                if (validateLedgerFile(files[i])) {
                    validFiles.push(files[i]);
                }
            }
            
            if (validFiles.length === 0) {
                return false;
            }
            
            // Combine with existing files
            var allFiles = existingFiles.concat(validFiles);
            
            // Create new FileList using DataTransfer
            var dataTransfer = new DataTransfer();
            allFiles.forEach(function(file) {
                dataTransfer.items.add(file);
            });
            
            fileInput.files = dataTransfer.files;
            
            // Display selected files
            displayLedgerSelectedFiles(fileInput.files);
            
            // Update file-selection-hint for compatibility
            updateFileSelectionHint(fileInput.files);
        }
        
        // Function to validate file
        function validateLedgerFile(file) {
            var allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
            var fileExtension = file.name.split('.').pop().toLowerCase();
            
            if (!allowedExtensions.includes(fileExtension)) {
                if (typeof toastr !== 'undefined') {
                    toastr.error('Invalid file type: ' + file.name + '. Please upload PDF, JPG, PNG, DOC, or DOCX files only.');
                } else {
                    alert('Invalid file type: ' + file.name + '. Please upload PDF, JPG, PNG, DOC, or DOCX files only.');
                }
                return false;
            }
            
            // Validate file size (10MB max)
            var maxSize = 10 * 1024 * 1024;
            if (file.size > maxSize) {
                if (typeof toastr !== 'undefined') {
                    toastr.error('File too large: ' + file.name + '. Maximum size is 10MB.');
                } else {
                    alert('File too large: ' + file.name + '. Maximum size is 10MB.');
                }
                return false;
            }
            
            return true;
        }
        
        // Function to display selected files
        function displayLedgerSelectedFiles(files) {
            var filesList = $('#ledger-files-list');
            filesList.empty();
            
            for (var i = 0; i < files.length; i++) {
                var file = files[i];
                var fileItem = $('<div class="file-item">' +
                    crmI('fas fa-file-alt') +
                    '<span class="file-name">' + file.name + ' (' + formatLedgerFileSize(file.size) + ')</span>' +
                    '<a href="javascript:;" class="ledger-remove-file" data-file-index="' + i + '" title="Remove file">' +
                        crmI('fa fa-times') +
                    '</a>' +
                '</div>');
                filesList.append(fileItem);
            }
            
            $('#ledger-selected-files-display').show();
            $('#ledgerDragDropZone').addClass('file-selected');
        }
        
        // Function to remove a specific file
        function removeLedgerFile(fileIndex) {
            var fileInput = $('.docclientreceiptupload')[0];
            var files = Array.from(fileInput.files);
            files.splice(fileIndex, 1);
            
            var dataTransfer = new DataTransfer();
            files.forEach(function(file) {
                dataTransfer.items.add(file);
            });
            
            fileInput.files = dataTransfer.files;
            
            if (files.length > 0) {
                displayLedgerSelectedFiles(fileInput.files);
            } else {
                clearLedgerFiles();
            }
            
            updateFileSelectionHint(fileInput.files);
        }
        
        // Function to clear all files
        function clearLedgerFiles() {
            $('.docclientreceiptupload').val('');
            $('#ledger-selected-files-display').hide();
            $('#ledger-files-list').empty();
            $('#ledgerDragDropZone').removeClass('file-selected');
            $('.file-selection-hint').text('');
        }
        
        // Function to format file size
        function formatLedgerFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            var k = 1024;
            var sizes = ['Bytes', 'KB', 'MB', 'GB'];
            var i = Math.floor(Math.log(bytes) / Math.log(k));
            return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
        }
        
        // Function to update file selection hint (for compatibility with existing code)
        function updateFileSelectionHint(files) {
            var hintElement = $('.file-selection-hint');
            if (files.length > 0) {
                if (files.length === 1) {
                    hintElement.text(files[0].name + ' selected');
                } else {
                    hintElement.text(files.length + ' Files selected');
                }
            } else {
                hintElement.text('');
            }
        }
        
        // Reset when modal is closed
        $('#createreceiptmodal').on('hidden.bs.modal', function() {
            clearLedgerFiles();
            $('#ledgerDragDropZone').removeClass('drag_over');
            clearOfficeFiles();
            $('.office-drag-drop-zone').removeClass('drag_over');
        });




        // --- DRAG AND DROP: Office Receipt Form ---
        
        
        function initOfficeDragDrop() {

            var $zones = $('.office-drag-drop-zone');
            if ($zones.length === 0) {
                console.warn('⚠️ Office drag zones not found');
                return;
            }
            

            // Remove all existing handlers
            $zones.off('click dragenter dragover dragleave drop');
            $(document).off('dragover.office dragenter.office');
            
            // Prevent default drag behaviors
            $(document).on('dragover.office dragenter.office', '#createreceiptmodal, #createofficereceiptmodal', function(e) {
                e.preventDefault();
                e.stopPropagation();
            });
            
            // DIRECT BINDING to each office drag zone for priority
            $zones.each(function() {
                var $zone = $(this);
                var zoneId = $zone.attr('id');
                
                $zone.on('dragenter', function(e) {

                    e.preventDefault();
                    e.stopPropagation();
                    e.stopImmediatePropagation();
                    $(this).addClass('drag_over');
                    return false;
                });
                
                $zone.on('dragover', function(e) {

                    var event = e.originalEvent || e;
                    event.preventDefault();
                    event.stopPropagation();
                    
                    if (event.dataTransfer) {
                        event.dataTransfer.dropEffect = 'copy';
                    }
                    
                    $(this).addClass('drag_over');
                    return false;
                });

                $zone.on('dragleave', function(e) {

                    e.preventDefault();
                    e.stopPropagation();
                    
                    // Only remove highlight if actually leaving the zone
                    var rect = this.getBoundingClientRect();
                    var x = e.originalEvent.clientX;
                    var y = e.originalEvent.clientY;
                    
                    if (x <= rect.left || x >= rect.right || y <= rect.top || y >= rect.bottom) {
                        $(this).removeClass('drag_over');
                    }
                    return false;
                });

                $zone.on('drop', function(e) {

                    var event = e.originalEvent || e;
                    event.preventDefault();
                    event.stopPropagation();
                    event.stopImmediatePropagation();
                    
                    $(this).removeClass('drag_over');
                    
                    var files = event.dataTransfer ? event.dataTransfer.files : null;
                    if (files && files.length > 0) {
                        handleOfficeFilesDrop(files, zoneId);
                    } else {
                        console.error('❌ No files in drop event');
                    }
                    return false;
                });

                // Click to browse
                $zone.on('click', function(e) {

                    e.preventDefault();
                    if (!$(e.target).closest('.remove-file, .remove-all-files-office').length) {
                        $('.docofficereceiptupload').click();
                    }
                });
            });
            

        }
        
        // Initialize when either modal is shown
        $('#createreceiptmodal, #createofficereceiptmodal').on('shown.bs.modal', function() {

            setTimeout(initOfficeDragDrop, 100);
        });
        
        // Also initialize on page load (in case modal is already open)
        initOfficeDragDrop();
        
        // File input change handler (for when user clicks to browse) - enhanced
        $(document).on('change', '.docofficereceiptupload', function() {
            var files = this.files;
            var zoneId = $(this).closest('.upload_office_receipt_document').find('.office-drag-drop-zone').attr('id');
            if (files && files.length > 0) {
                displayOfficeSelectedFiles(files, zoneId);
                updateOfficeFileSelectionHint(files);
            } else {
                clearOfficeFiles(zoneId);
            }
        });
        
        // Remove individual file
        $(document).on('click', '.office-remove-file', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var fileIndex = $(this).data('file-index');
            var zoneId = $(this).closest('.ledger-selected-files-display').attr('id').replace('office-selected-files-display', '').replace('office-selected-files-display2', '');
            zoneId = zoneId ? 'officeDragDropZone' + zoneId : 'officeDragDropZone';
            removeOfficeFile(fileIndex, zoneId);
        });
        
        // Remove all files
        $(document).on('click', '.remove-all-files-office', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var zoneId = $(this).closest('.ledger-selected-files-display').attr('id').replace('office-selected-files-display', '').replace('office-selected-files-display2', '');
            zoneId = zoneId ? 'officeDragDropZone' + zoneId : 'officeDragDropZone';
            clearOfficeFiles(zoneId);
        });
        
        // Function to handle dropped files
        function handleOfficeFilesDrop(files, zoneId) {
            var validFiles = [];
            var fileInput = $('.docofficereceiptupload')[0];
            var existingFiles = fileInput.files ? Array.from(fileInput.files) : [];
            
            // Validate each file
            for (var i = 0; i < files.length; i++) {
                if (validateOfficeFile(files[i])) {
                    validFiles.push(files[i]);
                }
            }
            
            if (validFiles.length === 0) {
                return false;
            }
            
            // Combine with existing files
            var allFiles = existingFiles.concat(validFiles);
            
            // Create new FileList using DataTransfer
            var dataTransfer = new DataTransfer();
            allFiles.forEach(function(file) {
                dataTransfer.items.add(file);
            });
            
            fileInput.files = dataTransfer.files;
            
            // Display selected files
            displayOfficeSelectedFiles(fileInput.files, zoneId);
            
            // Update file-selection-hint for compatibility
            updateOfficeFileSelectionHint(fileInput.files);
        }
        
        // Function to validate file
        function validateOfficeFile(file) {
            var allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
            var fileExtension = file.name.split('.').pop().toLowerCase();
            
            if (!allowedExtensions.includes(fileExtension)) {
                if (typeof toastr !== 'undefined') {
                    toastr.error('Invalid file type: ' + file.name + '. Please upload PDF, JPG, PNG, DOC, or DOCX files only.');
                } else {
                    alert('Invalid file type: ' + file.name + '. Please upload PDF, JPG, PNG, DOC, or DOCX files only.');
                }
                return false;
            }
            
            // Validate file size (10MB max)
            var maxSize = 10 * 1024 * 1024;
            if (file.size > maxSize) {
                if (typeof toastr !== 'undefined') {
                    toastr.error('File too large: ' + file.name + '. Maximum size is 10MB.');
                } else {
                    alert('File too large: ' + file.name + '. Maximum size is 10MB.');
                }
                return false;
            }
            
            return true;
        }
        
        // Function to display selected files
        function displayOfficeSelectedFiles(files, zoneId) {
            var displayId = zoneId === 'officeDragDropZone2' ? 'office-selected-files-display2' : 'office-selected-files-display';
            var listId = zoneId === 'officeDragDropZone2' ? 'office-files-list2' : 'office-files-list';
            var filesList = $('#' + listId);
            filesList.empty();
            
            for (var i = 0; i < files.length; i++) {
                var file = files[i];
                var fileItem = $('<div class="file-item">' +
                    crmI('fas fa-file-alt') +
                    '<span class="file-name">' + file.name + ' (' + formatOfficeFileSize(file.size) + ')</span>' +
                    '<a href="javascript:;" class="office-remove-file" data-file-index="' + i + '" title="Remove file">' +
                        crmI('fa fa-times') +
                    '</a>' +
                '</div>');
                filesList.append(fileItem);
            }
            
            $('#' + displayId).show();
            $('#' + zoneId).addClass('file-selected');
        }
        
        // Function to remove a specific file
        function removeOfficeFile(fileIndex, zoneId) {
            var fileInput = $('.docofficereceiptupload')[0];
            var files = Array.from(fileInput.files);
            files.splice(fileIndex, 1);
            
            var dataTransfer = new DataTransfer();
            files.forEach(function(file) {
                dataTransfer.items.add(file);
            });
            
            fileInput.files = dataTransfer.files;
            
            if (files.length > 0) {
                displayOfficeSelectedFiles(fileInput.files, zoneId);
            } else {
                clearOfficeFiles(zoneId);
            }
            
            updateOfficeFileSelectionHint(fileInput.files);
        }
        
        // Function to clear all files
        function clearOfficeFiles(zoneId) {
            if (!zoneId) {
                // Clear all office files
                $('.docofficereceiptupload').val('');
                $('#office-selected-files-display, #office-selected-files-display2').hide();
                $('#office-files-list, #office-files-list2').empty();
                $('.office-drag-drop-zone').removeClass('file-selected');
                $('.file-selection-hint1').text('');
            } else {
                var displayId = zoneId === 'officeDragDropZone2' ? 'office-selected-files-display2' : 'office-selected-files-display';
                var listId = zoneId === 'officeDragDropZone2' ? 'office-files-list2' : 'office-files-list';
                $('.docofficereceiptupload').val('');
                $('#' + displayId).hide();
                $('#' + listId).empty();
                $('#' + zoneId).removeClass('file-selected');
                $('.file-selection-hint1').text('');
            }
        }
        
        // Function to format file size
        function formatOfficeFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            var k = 1024;
            var sizes = ['Bytes', 'KB', 'MB', 'GB'];
            var i = Math.floor(Math.log(bytes) / Math.log(k));
            return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
        }
        
        // Function to update file selection hint (for compatibility with existing code)
        function updateOfficeFileSelectionHint(files) {
            var hintElement = $('.file-selection-hint1');
            if (files.length > 0) {
                if (files.length === 1) {
                    hintElement.text(files[0].name + ' selected');
                } else {
                    hintElement.text(files.length + ' Files selected');
                }
            } else {
                hintElement.text('');
            }
        }



        // Show file selection hint when files are selected

        const docOfficeReceiptUpload = document.querySelector('.docofficereceiptupload');
        if (docOfficeReceiptUpload) {
            docOfficeReceiptUpload.addEventListener('change', function(e) {

            const files = e.target.files;

            const hintElement1 = document.querySelector('.file-selection-hint1');



            if (files.length > 0) {

                if (files.length === 1) {

                    // Show the file name if only one file is selected

                    hintElement1.textContent = `${files[0].name} selected`;

                } else {

                    // Show the number of files if multiple files are selected

                    hintElement1.textContent = `${files.length} Files selected`;

                }

            } else {

                // Clear the hint if no files are selected

                hintElement1.textContent = '';

            }

            });
        }

    }); // end $(document).ready

})(typeof jQuery !== 'undefined' ? jQuery : null);
