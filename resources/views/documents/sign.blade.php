<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign Document - E-Signature App</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        /* DocuSign-like UI additions */
        .ds-tag {
            position: absolute;
            top: -14px;
            left: 0;
            background-color: #ffcc00;
            color: #111827;
            font-weight: 600;
            font-size: 12px;
            padding: 2px 8px;
            border-radius: 3px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.15);
            pointer-events: none;
        }
        .ds-tag::after {
            content: '';
            position: absolute;
            left: 8px;
            bottom: -6px;
            width: 0;
            height: 0;
            border-left: 6px solid transparent;
            border-right: 6px solid transparent;
            border-top: 6px solid #ffcc00;
        }
        .signature-field { 
            border: 2px dashed #9ca3af; 
            background-color: rgba(255, 204, 0, 0.15);
            transition: box-shadow 0.2s ease, transform 0.1s ease;
        }
        .signature-field:hover { box-shadow: 0 0 0 3px rgba(59,130,246,0.35); }
        .signature-field.active-focus { animation: pulseFocus 1.2s ease-in-out 2; box-shadow: 0 0 0 3px rgba(59,130,246,0.55); }
        @keyframes pulseFocus { 0% { transform: scale(1);} 50% { transform: scale(1.02);} 100% { transform: scale(1);} }

        .signing-header {
            position: sticky; top: 0; z-index: 100; background: #111827; color: #fff;
            padding: 10px 12px; border-bottom: 1px solid #1f2937; display: flex; align-items: center; justify-content: space-between; gap: 12px;
        }
        .signing-header .controls { display: flex; gap: 8px; }
        .btn { border: none; border-radius: 6px; padding: 8px 12px; font-weight: 600; cursor: pointer; }
        .btn-primary { background: #2563eb; color: #fff; }
        .btn-secondary { background: #374151; color: #e5e7eb; }
        .btn-success { background: #059669; color: #fff; }
        .btn[disabled] { opacity: 0.5; cursor: not-allowed; }
        .signature-pad {
            touch-action: none;
            -webkit-user-select: none;
            -moz-user-select: none;
            -ms-user-select: none;
            user-select: none;
        }
        .signature-pad canvas {
            border: 1px solid #ccc;
            border-radius: 4px;
            background-color: transparent;
        }
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(2px);
        }
        
        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 20px;
            border-radius: 8px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            animation: modalSlideIn 0.3s ease-out;
        }
        
        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .modal-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #333;
        }
        
        .close {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            line-height: 1;
        }
        
        .close:hover {
            color: #000;
        }
        
        .signature-canvas-container {
            text-align: center;
            margin: 20px 0;
        }
        
        .signature-controls {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 15px;
        }
        
        .signature-controls button {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            transition: background-color 0.2s;
        }
        
        .clear-btn {
            background-color: #f44336;
            color: white;
        }
        
        .clear-btn:hover {
            background-color: #d32f2f;
        }
        
        .save-btn {
            background-color: #4CAF50;
            color: white;
        }
        
        .save-btn:hover {
            background-color: #388e3c;
        }
        
        .cancel-btn {
            background-color: #9e9e9e;
            color: white;
        }
        
        .cancel-btn:hover {
            background-color: #757575;
        }
        
        /* Tabs Styles */
        .signature-tabs {
            display: flex;
            border-bottom: 2px solid #eee;
            margin-bottom: 20px;
        }
        
        .signature-tab {
            flex: 1;
            padding: 12px 20px;
            text-align: center;
            cursor: pointer;
            background-color: #f5f5f5;
            border: none;
            border-bottom: 3px solid transparent;
            font-size: 14px;
            font-weight: 500;
            color: #666;
            transition: all 0.3s;
        }
        
        .signature-tab:hover {
            background-color: #e8e8e8;
            color: #333;
        }
        
        .signature-tab.active {
            background-color: white;
            color: #4CAF50;
            border-bottom-color: #4CAF50;
        }
        
        .signature-tab-content {
            display: none;
        }
        
        .signature-tab-content.active {
            display: block;
        }
        
        .signature-instructions {
            text-align: center;
            color: #666;
            margin-bottom: 15px;
            font-size: 14px;
        }

        /* Type Signature Styles */
        .fallback-signature {
            width: 100%;
            min-height: 150px;
            padding: 15px;
            border: 2px solid #ccc;
            border-radius: 4px;
            font-size: 24px;
            font-family: 'Brush Script MT', 'Lucida Handwriting', cursive, sans-serif;
            resize: vertical;
            text-align: center;
            display: block !important;
        }
        
        .fallback-signature:focus {
            outline: none;
            border-color: #4CAF50;
        }
        
        /* Ensure textarea is visible when type tab is active */
        .signature-tab-content.active .fallback-signature {
            display: block !important;
        }
        
        /* Upload Signature Styles */
        .upload-signature-container {
            text-align: center;
            padding: 20px;
        }
        
        .upload-signature-input {
            display: none;
        }
        
        .upload-signature-label {
            display: inline-block;
            padding: 12px 24px;
            background-color: #2196F3;
            color: white;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            transition: background-color 0.2s;
        }
        
        .upload-signature-label:hover {
            background-color: #1976D2;
        }
        
        .upload-signature-preview {
            margin-top: 20px;
            max-width: 100%;
            max-height: 200px;
            border: 2px solid #ccc;
            border-radius: 4px;
            display: none;
        }
        
        .upload-signature-preview.active {
            display: block;
            margin: 20px auto;
        }
    </style>
</head>
<body class="bg-gray-100 dark:bg-gray-900 font-sans antialiased">
    
    <div class="signing-header">
        <div class="progress">
            <span id="loading-progress" style="display: none;">Loading pages: <span id="loaded-pages">0</span>/<span id="total-pages">{{ $pdfPages }}</span> | </span>
            <span id="progress-count">0</span> of <span id="progress-total">0</span> required fields
        </div>
        <div class="controls">
            <button id="start-signing-btn" class="btn btn-primary">Start</button>
            <button id="next-field-btn" class="btn btn-secondary">Next</button>
            <button id="submit-signatures-btn" class="btn btn-success" style="display:none;">Submit Signatures</button>
        </div>
    </div>
    <div class="min-h-screen flex items-center justify-center px-4 sm:px-6 lg:px-8">
        <div class="max-w-4xl w-full bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 sm:p-8">
            <!-- Header -->
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-6 text-center">
                Sign Document: {{ $document->file_name ?? 'Document' }}
            </h1>

            <!-- Error Message -->
            @if (session('error'))
                <div class="mb-4 p-4 bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-300 rounded-lg border border-red-300 dark:border-red-700">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                        </svg>
                        <strong>Error:</strong> {{ session('error') }}
                    </div>
                </div>
            @endif

            <!-- Success Message -->
            @if (session('success'))
                <div class="mb-4 p-4 bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-300 rounded-lg border border-green-300 dark:border-green-700">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                        </svg>
                        <strong>Success:</strong> {{ session('success') }}
                    </div>
                </div>
            @endif

            <!-- Debug Info -->
            <div id="debug-info" class="mb-4 p-4 bg-yellow-100 dark:bg-yellow-900 text-yellow-700 dark:text-yellow-300 rounded-lg" style="display: none;">
                <strong>Debug Info:</strong>
                <div id="debug-content"></div>
            </div>

            @if ($pdfPages > 1)
            <!-- Page Navigation -->
            <div class="mb-6 p-4 bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                        Document Pages ({{ $pdfPages }} total)
                    </h3>
                    <div class="flex items-center space-x-2">
                        <button 
                            onclick="scrollToPage(1)" 
                            class="px-3 py-1 text-sm bg-blue-100 hover:bg-blue-200 dark:bg-blue-900 dark:hover:bg-blue-800 text-blue-800 dark:text-blue-200 rounded"
                        >
                            Go to Top
                        </button>
                        <button 
                            onclick="scrollToPage({{ $pdfPages }})" 
                            class="px-3 py-1 text-sm bg-blue-100 hover:bg-blue-200 dark:bg-blue-900 dark:hover:bg-blue-800 text-blue-800 dark:text-blue-200 rounded"
                        >
                            Go to Last Page
                        </button>
                    </div>
                </div>
                
                <!-- Page Quick Navigation -->
                <div class="mt-3 flex flex-wrap gap-2">
                    @for ($i = 1; $i <= $pdfPages; $i++)
                        <button 
                            onclick="scrollToPage({{ $i }})" 
                            class="px-2 py-1 text-xs bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 rounded border"
                            title="Go to Page {{ $i }}"
                        >
                            {{ $i }}
                        </button>
                    @endfor
                </div>
            </div>
            @endif

            <!-- Signature Pages -->
            <div class="space-y-6">
                @for ($i = 1; $i <= $pdfPages; $i++)
                    <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
                            Page {{ $i }}
                        </h2>
                        <div class="relative">
                            <!-- Loading Placeholder -->
                            <div id="loading-placeholder-{{ $i }}" class="absolute inset-0 flex items-center justify-center bg-gray-100" style="min-height: 600px;">
                                <div class="text-center">
                                    <svg class="animate-spin h-12 w-12 text-blue-600 mx-auto mb-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <p class="text-gray-600">Loading page {{ $i }}...</p>
                                </div>
                            </div>
                            
                            <!-- Error Placeholder (hidden by default) -->
                            <div id="error-placeholder-{{ $i }}" class="absolute inset-0 flex items-center justify-center bg-red-50 hidden" style="min-height: 600px;">
                                <div class="text-center p-6">
                                    <svg class="h-16 w-16 text-red-500 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                    </svg>
                                    <p class="text-red-700 font-semibold mb-2">Failed to load page {{ $i }}</p>
                                    <button onclick="retryLoadImage({{ $i }})" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                                        Retry
                                    </button>
                                </div>
                            </div>
                            
            <img
                id="pdf-image-{{ $i }}"
                src="{{ route('public.documents.page', ['id' => $document->id, 'page' => $i, 'token' => $signer->token]) }}"
                alt="Page {{ $i }}"
                class="w-full h-auto rounded-md shadow-sm pdf-page-image"
                style="max-width: 100%; z-index: 1; pointer-events: none; display: none;"
                data-page="{{ $i }}"
                data-debug-url="{{ route('public.documents.page', ['id' => $document->id, 'page' => $i, 'token' => $signer->token]) }}"
            >
                            @foreach ($signatureFields as $field)
                                @if ($field->page_number == $i)
                                    <div
                                        id="signature-field-{{ $field->id }}"
                                        class="signature-field absolute cursor-pointer"
                                        data-x-percent="{{ $field->x_percent ?? 0 }}"
                                        data-y-percent="{{ $field->y_percent ?? 0 }}"
                                        data-w-percent="{{ $field->width_percent ?? 0 }}"
                                        data-h-percent="{{ $field->height_percent ?? 0 }}"
                                        data-page="{{ $i }}"
                                        style="z-index: 50; pointer-events: auto;"
                                        onclick="activateSignatureField({{ $field->id }}, {{ $i }})"
                                    >
                                        <div class="ds-tag">Sign</div>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endfor
            </div>

            <!-- Signature Form -->
            <form id="signature-form" method="POST" action="{{ route('public.documents.submitSignatures', $document->id) }}">
                @csrf
                <input type="hidden" name="signer_id" value="{{ $signer->id }}">
                <input type="hidden" name="token" value="{{ $signer->token }}">
                @for ($i = 1; $i <= $pdfPages; $i++)
                    <input
                        type="hidden"
                        name="signatures[{{ $i }}]"
                        id="signature-input-{{ $i }}"
                    >
                    <input
                        type="hidden"
                        name="signature_positions[{{ $i }}]"
                        id="signature-position-{{ $i }}"
                    >
                @endfor
                <div class="mt-6 flex justify-end">
                    <button
                        type="button"
                        id="form-submit-btn"
                        class="w-full sm:w-auto px-6 py-2 bg-blue-600 text-white font-medium rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition"
                        style="display:none;"
                    >
                        Submit Signatures
                    </button>
                </div>
            </form>

            
        </div>
    </div>

    <!-- Signature Modal -->
    <div id="signature-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Sign Here</h3>
                <span class="close" onclick="closeSignatureModal()">&times;</span>
            </div>
            
            <!-- Tabs -->
            <div class="signature-tabs">
                <button class="signature-tab active" onclick="switchSignatureTab('draw', this)">Draw</button>
                <button class="signature-tab" onclick="switchSignatureTab('upload', this)">Upload</button>
            </div>
            
            <!-- Draw Tab Content -->
            <div id="draw-tab" class="signature-tab-content active">
                <div class="signature-instructions">
                    Use your mouse, touch, or stylus to draw your signature below
                </div>
                
                <div class="signature-canvas-container">
                    <canvas
                        id="signature-pad"
                        class="signature-pad"
                        width="450"
                        height="250"
                        style="max-width: 100%; width: 100%;"
                    ></canvas>
                </div>
            </div>
            
            <!-- Upload Tab Content -->
            <div id="upload-tab" class="signature-tab-content">
                <div class="signature-instructions">
                    Upload an image of your signature (PNG, JPG, or SVG)
                </div>
                
                <div class="upload-signature-container">
                    <input type="file" id="upload-signature-input" class="upload-signature-input" accept="image/png,image/jpeg,image/jpg,image/svg+xml" onchange="handleSignatureUpload(event)">
                    <label for="upload-signature-input" class="upload-signature-label">Choose Image</label>
                    <img id="upload-signature-preview" class="upload-signature-preview" alt="Signature preview">
                </div>
            </div>
            
            <div class="signature-controls">
                <button type="button" class="clear-btn" onclick="clearSignature()">Clear</button>
                <button type="button" class="cancel-btn" onclick="closeSignatureModal()">Cancel</button>
                <button type="button" class="save-btn" onclick="saveSignature()">Save Signature</button>
                <button type="button" class="save-btn" id="paste-signature-btn" style="display:none; margin-left:8px;" onclick="pasteSignature()">Paste Signature</button>
            </div>
        </div>
    </div>

    <!-- Professional SignaturePad Implementation -->
    <script>
        console.log('=== SIGNATURE SCRIPT LOADING ===');
        
        // Track which images have been processed to avoid duplicates
        const processedImages = new Set();
        let loadedPagesCount = 0;
        const totalPages = {{ $pdfPages ?? 1 }};
        
        console.log('Total pages:', totalPages);
        
        function updateLoadingProgress() {
            const loadingProgress = document.getElementById('loading-progress');
            const loadedPagesEl = document.getElementById('loaded-pages');
            
            if (loadedPagesEl) {
                loadedPagesEl.textContent = loadedPagesCount;
            }
            
            if (loadingProgress) {
                if (loadedPagesCount < totalPages) {
                    loadingProgress.style.display = 'inline';
                } else {
                    loadingProgress.style.display = 'none';
                }
            }
        }
        
        // Responsive positioning for signature fields
        function positionSignatureFields(page) {
            const img = document.getElementById('pdf-image-' + page);
            if (!img) return;

            // ✅ SAFETY CHECK: Ensure image is loaded and has valid dimensions
            const naturalWidth = img.naturalWidth;
            const naturalHeight = img.naturalHeight;
            
            if (!naturalWidth || !naturalHeight || naturalWidth === 0 || naturalHeight === 0) {
                console.warn(`Page ${page} image not loaded yet. Dimensions: ${naturalWidth}x${naturalHeight}`);
                // Retry after a short delay
                setTimeout(() => positionSignatureFields(page), 100);
                return;
            }
            
            const displayWidth = img.clientWidth;
            const displayHeight = img.clientHeight;
            
            if (!displayWidth || !displayHeight) {
                console.warn(`Page ${page} image not rendered yet.`);
                setTimeout(() => positionSignatureFields(page), 100);
                return;
            }

            // Consistency check log
            console.log(`Page ${page} Field Positions:`, {
                natural: {w: naturalWidth, h: naturalHeight},
                display: {w: displayWidth, h: displayHeight},
                aspect_ratio_match: (displayWidth / displayHeight).toFixed(4) === (naturalWidth / naturalHeight).toFixed(4)
            });

            document.querySelectorAll('.signature-field[data-page="' + page + '"]').forEach(field => {
                const xPercent = parseFloat(field.getAttribute('data-x-percent')) || 0;
                const yPercent = parseFloat(field.getAttribute('data-y-percent')) || 0;
                const wPercent = parseFloat(field.getAttribute('data-w-percent')) || 0;
                const hPercent = parseFloat(field.getAttribute('data-h-percent')) || 0;

                // Scale positions to display
                field.style.left = (xPercent * displayWidth) + 'px';
                field.style.top = (yPercent * displayHeight) + 'px';
                field.style.width = (wPercent * displayWidth) + 'px';
                field.style.height = (hPercent * displayHeight) + 'px';
            });
        }
        
        // Image loading handlers - Make them global for inline handlers
        // Define these functions immediately to avoid timing issues with inline onload handlers
        console.log('Defining handleImageLoadSuccess...');
        window.handleImageLoadSuccess = function(pageNum) {
            console.log('handleImageLoadSuccess called for page:', pageNum);
            // Prevent duplicate processing
            if (processedImages.has(pageNum)) {
                console.log('Page ' + pageNum + ' already processed, skipping...');
                return;
            }
            processedImages.add(pageNum);
            
            // Update loading progress
            loadedPagesCount++;
            updateLoadingProgress();
            
            const loadingPlaceholder = document.getElementById('loading-placeholder-' + pageNum);
            const errorPlaceholder = document.getElementById('error-placeholder-' + pageNum);
            const img = document.getElementById('pdf-image-' + pageNum);
            
            if (loadingPlaceholder) loadingPlaceholder.style.display = 'none';
            if (errorPlaceholder) errorPlaceholder.classList.add('hidden');
            if (img) {
                img.style.display = 'block';
                console.log('Successfully loaded PDF page image:', pageNum, 'Dimensions:', img.naturalWidth + 'x' + img.naturalHeight);
                
                // Position signature fields after image loads
                setTimeout(() => positionSignatureFields(pageNum), 100);
            }
        };

        console.log('Defining handleImageLoadError...');
        window.handleImageLoadError = function(imgElement, pageNum) {
            console.error('handleImageLoadError called for page:', pageNum);
            const loadingPlaceholder = document.getElementById('loading-placeholder-' + pageNum);
            const errorPlaceholder = document.getElementById('error-placeholder-' + pageNum);
            
            if (loadingPlaceholder) loadingPlaceholder.style.display = 'none';
            if (errorPlaceholder) errorPlaceholder.classList.remove('hidden');
            
            console.error('Failed to load PDF page image:', pageNum, 'URL:', imgElement.src);
            console.error('Image element:', imgElement);
        };
        
        console.log('Functions defined. handleImageLoadSuccess:', typeof window.handleImageLoadSuccess);
        console.log('Functions defined. handleImageLoadError:', typeof window.handleImageLoadError);

        window.retryLoadImage = function(pageNum) {
            const img = document.getElementById('pdf-image-' + pageNum);
            const loadingPlaceholder = document.getElementById('loading-placeholder-' + pageNum);
            const errorPlaceholder = document.getElementById('error-placeholder-' + pageNum);
            
            if (errorPlaceholder) errorPlaceholder.classList.add('hidden');
            if (loadingPlaceholder) loadingPlaceholder.style.display = 'flex';
            
            // Preserve token query params; only bump a cache-buster
            const baseSrc = img.getAttribute('data-debug-url') || img.src;
            const url = new URL(baseSrc, window.location.origin);
            url.searchParams.set('retry', Date.now());
            img.src = url.toString();
        };

        // Optimized Signature Pad (SignaturePad library + HiDPI scaling)
        function setupHiDPICanvas(canvas) {
            const ratio = Math.max(window.devicePixelRatio || 1, 1);
            
            // Get parent container width for responsive sizing
            const container = canvas.parentElement;
            let cssWidth = container ? container.clientWidth : canvas.clientWidth;
            let cssHeight = 250; // Fixed height
            
            // Mobile responsive: use smaller height on narrow screens
            if (window.innerWidth < 500) {
                cssHeight = 200;
                cssWidth = Math.min(cssWidth, window.innerWidth - 40);
            }
            
            if (!cssWidth || cssWidth < 100) cssWidth = 400;
            
            canvas.style.width = cssWidth + 'px';
            canvas.style.height = cssHeight + 'px';
            canvas.width = Math.max(1, Math.floor(cssWidth * ratio));
            canvas.height = Math.max(1, Math.floor(cssHeight * ratio));
            
            const ctx = canvas.getContext('2d');
            ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
            return ratio;
        }

        function createSignaturePad(canvas) {
            // ✅ CHECK: Ensure SignaturePad library is loaded
            if (typeof window.SignaturePad === 'undefined') {
                console.error('SignaturePad library not loaded!');
                alert('Signature pad failed to load. Please refresh the page.');
                useFallback = true;
                // Show fallback textarea
                canvas.style.display = 'none';
                document.getElementById('fallback-signature').style.display = 'block';
                return null;
            }
            
            // Ensure canvas has CSS size so offsetWidth/Height are non-zero
            if (!canvas.style.width) canvas.style.width = '100%';
            if (!canvas.style.height) canvas.style.height = '200px';
            setupHiDPICanvas(canvas);
            
            try {
                const pad = new window.SignaturePad(canvas, {
                    minWidth: 0.6,
                    maxWidth: 2.8,
                    throttle: 8, // reduce event frequency to avoid lag
                    minDistance: 2, // smoothing
                    penColor: 'rgb(0,0,0)',
                    backgroundColor: 'rgba(0,0,0,0)'
                });
                const onResize = () => {
                    const data = pad.toData();
                    setupHiDPICanvas(canvas);
                    pad.clear();
                    pad.fromData(data);
                };
                window.addEventListener('resize', onResize);
                return pad;
            } catch (error) {
                console.error('Failed to create SignaturePad:', error);
                alert('Signature pad initialization failed. Using text fallback.');
                useFallback = true;
                canvas.style.display = 'none';
                document.getElementById('fallback-signature').style.display = 'block';
                return null;
            }
        }

        let currentActiveField = null;
        let currentActivePage = null;
        let signaturePad = null;
        let savedSignatures = {};
        let useFallback = false;
        let userSavedSignatureData = null; // Store user's signature for reuse
        let currentSignatureMode = 'draw'; // 'draw' or 'upload'
        let uploadedSignatureData = null; // Store uploaded signature image data
        let signingFieldOrder = [];
        let totalRequiredFields = 0;

        // Debug function
        function toggleDebug() {
            const debugInfo = document.getElementById('debug-info');
            const debugContent = document.getElementById('debug-content');
            
            if (debugInfo.style.display === 'none') {
                debugInfo.style.display = 'block';
                updateDebugInfo();
            } else {
                debugInfo.style.display = 'none';
            }
        }

        function updateDebugInfo() {
            const debugContent = document.getElementById('debug-content');
            
            let debugText = `
                <div>Active Page: ${currentActivePage}</div>
                <div>Active Field: ${currentActiveField}</div>
                <div>SignaturePad: ${signaturePad ? 'Initialized' : 'Not Initialized'}</div>
                <div>Using Fallback: ${useFallback ? 'Yes' : 'No'}</div>
                <div>Pad Empty: ${signaturePad ? signaturePad.isEmpty() : 'N/A'}</div>
                <div>Canvas Size: ${signaturePad ? signaturePad.canvas.width + 'x' + signaturePad.canvas.height : 'N/A'}</div>
                <div>Touch Events: ${('ontouchstart' in window) ? 'Supported' : 'Not Supported'}</div>
                <div>Mouse Events: ${('onmousedown' in window) ? 'Supported' : 'Not Supported'}</div>
            `;
            
            debugContent.innerHTML = debugText;
        }

        // remove legacy initializer
        
        document.addEventListener('DOMContentLoaded', function () {
            console.log('Document signing page loaded');
            
            // Attach event listeners to PDF page images
            const pdfImages = document.querySelectorAll('.pdf-page-image');
            console.log('Found', pdfImages.length, 'PDF images to attach listeners to');
            
            pdfImages.forEach(function(img) {
                const pageNum = parseInt(img.getAttribute('data-page'));
                console.log('Attaching listeners to page', pageNum);
                
                img.addEventListener('load', function() {
                    console.log('Image load event fired for page', pageNum);
                    window.handleImageLoadSuccess(pageNum);
                });
                
                img.addEventListener('error', function() {
                    console.log('Image error event fired for page', pageNum);
                    window.handleImageLoadError(img, pageNum);
                });
                
                // Check if image is already loaded (cached)
                if (img.complete && img.naturalWidth > 0) {
                    console.log('Page', pageNum, 'already loaded from cache');
                    loadedPagesCount++;
                    updateLoadingProgress();
                    window.handleImageLoadSuccess(pageNum);
                }
            });
            
            // Show loading progress
            updateLoadingProgress();
            
            // Log all image URLs for debugging
            for (let i = 1; i <= totalPages; i++) {
                const img = document.getElementById('pdf-image-' + i);
                if (img) {
                    console.log('Page ' + i + ' URL:', img.src);
                }
            }
            
            // Delay pad creation until modal is opened so sizing works
            // Don't hide fallback textarea - it's now in a tab and will be shown/hidden by tab switching
            // The textarea visibility is controlled by the tab content display

            // Build guided signing sequence and progress
            signingFieldOrder = Array.from(document.querySelectorAll('.signature-field')).map(el => ({
                element: el,
                id: parseInt(el.id.replace('signature-field-', '')),
                page: parseInt(el.getAttribute('data-page')),
            }));
            totalRequiredFields = signingFieldOrder.length;
            const totalEl = document.getElementById('progress-total');
            if (totalEl) totalEl.textContent = totalRequiredFields;
            updateProgress();

            const startBtn = document.getElementById('start-signing-btn');
            const nextBtn = document.getElementById('next-field-btn');
            const submitBtn = document.getElementById('submit-signatures-btn');
            const formSubmitBtn = document.getElementById('form-submit-btn');
            
            startBtn && startBtn.addEventListener('click', () => goToNextUnsignedField(true));
            nextBtn && nextBtn.addEventListener('click', () => goToNextUnsignedField(true));
            
            // Both submit buttons call the same function
            submitBtn && submitBtn.addEventListener('click', finishSigning);
            formSubmitBtn && formSubmitBtn.addEventListener('click', finishSigning);
        });

        // Tab switching function
        function switchSignatureTab(mode, buttonElement) {
            currentSignatureMode = mode;
            
            // Update tab buttons
            document.querySelectorAll('.signature-tab').forEach(tab => {
                tab.classList.remove('active');
            });
            if (buttonElement) {
                buttonElement.classList.add('active');
            }
            
            // Update tab content
            document.querySelectorAll('.signature-tab-content').forEach(content => {
                content.classList.remove('active');
            });
            
            if (mode === 'draw') {
                document.getElementById('draw-tab').classList.add('active');
                // Initialize signature pad if not already done
                if (!signaturePad && !useFallback) {
                    requestAnimationFrame(() => {
                        const canvas = document.getElementById('signature-pad');
                        if (canvas) {
                            setupHiDPICanvas(canvas);
                            signaturePad = createSignaturePad(canvas);
                        }
                    });
                }
            } else if (mode === 'upload') {
                document.getElementById('upload-tab').classList.add('active');
            }
        }
        
        // Handle signature file upload
        function handleSignatureUpload(event) {
            const file = event.target.files[0];
            if (!file) return;
            
            // Validate file type
            const validTypes = ['image/png', 'image/jpeg', 'image/jpg', 'image/svg+xml'];
            if (!validTypes.includes(file.type)) {
                alert('Please upload a valid image file (PNG, JPG, or SVG)');
                event.target.value = '';
                return;
            }
            
            // Validate file size (max 5MB)
            if (file.size > 5 * 1024 * 1024) {
                alert('File size must be less than 5MB');
                event.target.value = '';
                return;
            }
            
            const reader = new FileReader();
            reader.onload = function(e) {
                uploadedSignatureData = e.target.result;
                const preview = document.getElementById('upload-signature-preview');
                if (preview) {
                    preview.src = uploadedSignatureData;
                    preview.classList.add('active');
                }
            };
            reader.onerror = function() {
                alert('Error reading file. Please try again.');
                event.target.value = '';
            };
            reader.readAsDataURL(file);
        }
        
        // Make functions globally accessible
        window.switchSignatureTab = switchSignatureTab;
        window.handleSignatureUpload = handleSignatureUpload;

        function activateSignatureField(fieldId, page) {
            currentActiveField = fieldId;
            currentActivePage = page;

            // Reset to draw mode
            currentSignatureMode = 'draw';
            
            // Reset tabs
            document.querySelectorAll('.signature-tab').forEach((tab, index) => {
                if (index === 0) {
                    tab.classList.add('active');
                } else {
                    tab.classList.remove('active');
                }
            });
            
            // Reset tab content
            document.querySelectorAll('.signature-tab-content').forEach((content, index) => {
                if (index === 0) {
                    content.classList.add('active');
                } else {
                    content.classList.remove('active');
                }
            });

            // Show the modal
            const modal = document.getElementById('signature-modal');
            modal.style.display = 'block';

            // Show/hide Paste Signature button
            const pasteBtn = document.getElementById('paste-signature-btn');
            if (userSavedSignatureData) {
                pasteBtn.style.display = 'inline-block';
            } else {
                pasteBtn.style.display = 'none';
            }

            // Initialize or reset pad after modal becomes visible
            if (!signaturePad && !useFallback) {
                try {
                    requestAnimationFrame(() => {
                        const canvas = document.getElementById('signature-pad');
                        if (canvas) {
                            setupHiDPICanvas(canvas);
                            signaturePad = createSignaturePad(canvas);
                        }
                    });
                } catch (error) {
                    console.error('Error initializing signature pad:', error);
                }
            } else if (signaturePad) {
                signaturePad.clear();
            }
            
            // Clear type signature
            const fallbackTextarea = document.getElementById('fallback-signature');
            if (fallbackTextarea) {
                fallbackTextarea.value = '';
                // Remove any inline display styles so tab visibility works
                fallbackTextarea.style.display = '';
            }
            
            // Clear upload signature
            uploadedSignatureData = null;
            const uploadInput = document.getElementById('upload-signature-input');
            if (uploadInput) {
                uploadInput.value = '';
            }
            const preview = document.getElementById('upload-signature-preview');
            if (preview) {
                preview.src = '';
                preview.classList.remove('active');
            }

            // Update debug info if visible
            if (document.getElementById('debug-info') && document.getElementById('debug-info').style.display !== 'none') {
                updateDebugInfo();
            }
        }

        function closeSignatureModal() {
            const modal = document.getElementById('signature-modal');
            modal.style.display = 'none';
            
            // Clear based on current mode
            if (currentSignatureMode === 'draw' && signaturePad) {
                signaturePad.clear();
            } else if (currentSignatureMode === 'upload') {
                uploadedSignatureData = null;
                const uploadInput = document.getElementById('upload-signature-input');
                if (uploadInput) {
                    uploadInput.value = '';
                }
                const preview = document.getElementById('upload-signature-preview');
                if (preview) {
                    preview.src = '';
                    preview.classList.remove('active');
                }
            }
        }

        function clearSignature() {
            if (currentSignatureMode === 'draw' && signaturePad) {
                signaturePad.clear();
            } else if (currentSignatureMode === 'upload') {
                uploadedSignatureData = null;
                const uploadInput = document.getElementById('upload-signature-input');
                if (uploadInput) {
                    uploadInput.value = '';
                }
                const preview = document.getElementById('upload-signature-preview');
                if (preview) {
                    preview.src = '';
                    preview.classList.remove('active');
                }
            }
        }

        function saveSignature() {
            let signatureData = '';
            
            if (currentSignatureMode === 'draw') {
                if (signaturePad && !signaturePad.isEmpty()) {
                    signatureData = signaturePad.toDataURL('image/png');
                } else {
                    alert('Please draw a signature first.');
                    return;
                }
            } else if (currentSignatureMode === 'upload') {
                if (uploadedSignatureData) {
                    signatureData = uploadedSignatureData;
                } else {
                    alert('Please upload a signature image first.');
                    return;
                }
            }
            
            if (!signatureData) {
                alert('Please provide a signature first.');
                return;
            }
            // Save the user's signature for reuse
            userSavedSignatureData = signatureData;
            const fieldElement = document.getElementById('signature-field-' + currentActiveField);
            const pdfImage = document.getElementById('pdf-image-' + currentActivePage);
            const fieldRect = fieldElement.getBoundingClientRect();
            const imageRect = pdfImage.getBoundingClientRect();
            // Key Fix: Use natural dimensions for accurate percents
            const naturalWidth = pdfImage.naturalWidth;
            const naturalHeight = pdfImage.naturalHeight;
            const displayWidth = pdfImage.clientWidth;
            const displayHeight = pdfImage.clientHeight;
            const scaleX = displayWidth / naturalWidth || 1;
            const scaleY = displayHeight / naturalHeight || 1;
            // Unscale to natural
            const relativeX = (fieldRect.left - imageRect.left) / scaleX;
            const relativeY = (fieldRect.top - imageRect.top) / scaleY;
            const fieldWidthNatural = fieldElement.offsetWidth / scaleX;
            const fieldHeightNatural = fieldElement.offsetHeight / scaleY;
            // Percents relative to full PDF
            const xPercent = relativeX / naturalWidth;
            const yPercent = relativeY / naturalHeight;
            const wPercent = fieldWidthNatural / naturalWidth;
            const hPercent = fieldHeightNatural / naturalHeight;
            
            // ✅ VALIDATE: Check for invalid calculations
            if (!isFinite(xPercent) || !isFinite(yPercent) || !isFinite(wPercent) || !isFinite(hPercent)) {
                console.error('[Signature Error] Invalid percentage calculations!', {
                    xPercent, yPercent, wPercent, hPercent,
                    naturalWidth, naturalHeight, displayWidth, displayHeight
                });
                alert('Error calculating signature position. Please try again or contact support.');
                return;
            }

            if (xPercent < 0 || xPercent > 1 || yPercent < 0 || yPercent > 1) {
                console.error('[Signature Error] Position out of bounds!', {xPercent, yPercent});
                alert('Signature position is out of bounds. Please contact support.');
                return;
            }
            
            // Log all relevant info for debugging
            console.log('[Signature Debug] Canvas size:', {width: signaturePad ? signaturePad.canvas.width : 'N/A', height: signaturePad ? signaturePad.canvas.height : 'N/A'});
            console.log('[Signature Debug] Field position (px):', {left: fieldRect.left, top: fieldRect.top, width: fieldElement.offsetWidth, height: fieldElement.offsetHeight});
            console.log('[Signature Debug] PDF image natural size:', {width: naturalWidth, height: naturalHeight});
            console.log('[Signature Debug] PDF image display size:', {width: displayWidth, height: displayHeight});
            console.log('[Signature Debug] Scale factors:', {scaleX, scaleY});
            console.log('[Signature Debug] Relative position (natural px):', {x: relativeX, y: relativeY, width: fieldWidthNatural, height: fieldHeightNatural});
            console.log('[Signature Debug] Percentages:', {xPercent, yPercent, wPercent, hPercent});
            console.log('[Signature Debug] Page:', currentActivePage, 'Field ID:', currentActiveField);
            // Store signature data and position as percentages
            savedSignatures[currentActiveField] = {
                data: signatureData,
                x_percent: xPercent,
                y_percent: yPercent,
                w_percent: wPercent,
                h_percent: hPercent,
                page: currentActivePage
            };
            fieldElement.innerHTML = '<div class="ds-tag" style="background:#10b981;color:#fff;">Signed</div>';
            fieldElement.classList.add('signed');
            displaySignatureOnDocument(currentActiveField, signatureData, fieldElement);
            closeSignatureModal();
            updateProgress();
            // Auto-advance to next field
            goToNextUnsignedField(true);
        }

        // Paste signature to current field
        function pasteSignature() {
            if (!userSavedSignatureData) return;
            const fieldElement = document.getElementById('signature-field-' + currentActiveField);
            const pdfImage = document.getElementById('pdf-image-' + currentActivePage);
            const fieldRect = fieldElement.getBoundingClientRect();
            const imageRect = pdfImage.getBoundingClientRect();
            // Key Fix: Use natural dimensions for accurate percents
            const naturalWidth = pdfImage.naturalWidth;
            const naturalHeight = pdfImage.naturalHeight;
            const displayWidth = pdfImage.clientWidth;
            const displayHeight = pdfImage.clientHeight;
            const scaleX = displayWidth / naturalWidth || 1;
            const scaleY = displayHeight / naturalHeight || 1;
            // Unscale to natural
            const relativeX = (fieldRect.left - imageRect.left) / scaleX;
            const relativeY = (fieldRect.top - imageRect.top) / scaleY;
            const fieldWidthNatural = fieldElement.offsetWidth / scaleX;
            const fieldHeightNatural = fieldElement.offsetHeight / scaleY;
            // Percents relative to full PDF
            const xPercent = relativeX / naturalWidth;
            const yPercent = relativeY / naturalHeight;
            const wPercent = fieldWidthNatural / naturalWidth;
            const hPercent = fieldHeightNatural / naturalHeight;
            savedSignatures[currentActiveField] = {
                data: userSavedSignatureData,
                x_percent: xPercent,
                y_percent: yPercent,
                w_percent: wPercent,
                h_percent: hPercent,
                page: currentActivePage
            };
            fieldElement.innerHTML = '<div class="ds-tag" style="background:#10b981;color:#fff;">Signed</div>';
            fieldElement.classList.add('signed');
            displaySignatureOnDocument(currentActiveField, userSavedSignatureData, fieldElement);
            closeSignatureModal();
            updateProgress();
            // Auto-advance to next field
            goToNextUnsignedField(true);
        }

        function displaySignatureOnDocument(fieldId, signatureData, fieldElement) {
            // Create signature image element
            const signatureImg = document.createElement('img');
            signatureImg.src = signatureData;
            signatureImg.style.cssText = `
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                height: 100%;
                object-fit: contain;
                pointer-events: none;
                z-index: 10;
            `;
            
            // Add the signature image to the field
            fieldElement.appendChild(signatureImg);
            
            // Also create a signature overlay on the PDF image for better visibility
            const pdfImage = document.getElementById('pdf-image-' + currentActivePage);
            const pdfContainer = pdfImage.parentElement;
            
            // Check if signature overlay already exists for this field
            const existingOverlay = document.getElementById('signature-overlay-' + fieldId);
            if (existingOverlay) {
                existingOverlay.remove();
            }
            
            const signatureOverlay = document.createElement('img');
            signatureOverlay.id = 'signature-overlay-' + fieldId;
            signatureOverlay.src = signatureData;
            signatureOverlay.style.cssText = `
                position: absolute;
                left: ${fieldElement.style.left};
                top: ${fieldElement.style.top};
                width: ${fieldElement.style.width};
                height: ${fieldElement.style.height};
                object-fit: contain;
                pointer-events: none;
                z-index: 5;
                opacity: 0.9;
            `;
            
            pdfContainer.appendChild(signatureOverlay);
        }

        // Close modal when clicking outside of it
        window.onclick = function(event) {
            const modal = document.getElementById('signature-modal');
            if (event.target === modal) {
                closeSignatureModal();
            }
        }

        // Populate hidden fields before submitting
        function populateHiddenFields() {
            try {
                let populatedCount = 0;
                
                for (let i = 1; i <= {{ $pdfPages }}; i++) {
                    var pageSignatures = {};
                    var pagePositions = {};
                    
                    Object.keys(savedSignatures).forEach(function(fieldId) {
                        var signature = savedSignatures[fieldId];
                        if (signature.page === i) {
                            pageSignatures[fieldId] = signature.data;
                            pagePositions[fieldId] = {
                                x_percent: signature.x_percent,
                                y_percent: signature.y_percent,
                                w_percent: signature.w_percent,
                                h_percent: signature.h_percent
                            };
                        }
                    });

                    // ✅ FIX #5: VALIDATE JSON BEFORE SETTING
                    const signaturesJson = JSON.stringify(pageSignatures);
                    const positionsJson = JSON.stringify(pagePositions);
                    
                    // Verify JSON is valid and not empty
                    if (signaturesJson === 'undefined' || positionsJson === 'undefined') {
                        throw new Error('Invalid signature data for page ' + i);
                    }
                    
                    const signatureInput = document.getElementById('signature-input-' + i);
                    const positionInput = document.getElementById('signature-position-' + i);
                    
                    if (!signatureInput || !positionInput) {
                        throw new Error('Hidden input fields not found for page ' + i);
                    }
                    
                    signatureInput.value = signaturesJson;
                    positionInput.value = positionsJson;
                    
                    // Count pages with signatures
                    if (Object.keys(pageSignatures).length > 0) {
                        populatedCount++;
                    }
                }
                
                console.log('✅ Populated fields for ' + populatedCount + ' pages');
                
                // Verify we populated at least one page
                if (populatedCount === 0) {
                    throw new Error('No signature data was populated');
                }
                
                return true;
            } catch (error) {
                console.error('❌ Error populating hidden fields:', error);
                return false;
            }
        }

        // Expose activateSignatureField to global scope for inline onclick
        window.activateSignatureField = activateSignatureField;

        // Debounce function to prevent excessive calls
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

        // Update resize handler with debouncing (runs once after DOMContentLoaded)
        window.addEventListener('load', function() {
            const debouncedReposition = debounce(function() {
                for (let i = 1; i <= {{ $pdfPages }}; i++) {
                    positionSignatureFields(i);
                }
            }, 150); // Wait 150ms after resize stops

            window.addEventListener('resize', debouncedReposition);
        });

        // Page navigation functions
        function scrollToPage(pageNumber) {
            const pageElement = document.querySelector(`[data-page="${pageNumber}"]`).closest('.bg-gray-50');
            if (pageElement) {
                pageElement.scrollIntoView({ 
                    behavior: 'smooth', 
                    block: 'start' 
                });
                
                // Highlight the page temporarily
                pageElement.style.boxShadow = '0 0 20px rgba(59, 130, 246, 0.5)';
                setTimeout(() => {
                    pageElement.style.boxShadow = '';
                }, 2000);
            }
        }

        // Guided signing helpers
        function updateProgress() {
            const signedCount = Object.keys(savedSignatures).length;
            const total = totalRequiredFields;
            const progressCount = document.getElementById('progress-count');
            const headerSubmitBtn = document.getElementById('submit-signatures-btn');
            const formSubmitBtn = document.getElementById('form-submit-btn');
            
            // Update progress count
            if (progressCount) progressCount.textContent = signedCount;
            
            // Show/hide BOTH submit buttons together (synchronized)
            const shouldShow = (signedCount >= total && total > 0);
            if (headerSubmitBtn) headerSubmitBtn.style.display = shouldShow ? 'inline-block' : 'none';
            if (formSubmitBtn) formSubmitBtn.style.display = shouldShow ? 'inline-block' : 'none';
        }

        function getNextUnsignedField() {
            const signedIds = new Set(Object.keys(savedSignatures).map(x => parseInt(x)));
            for (let i = 0; i < signingFieldOrder.length; i++) {
                const item = signingFieldOrder[i];
                if (!signedIds.has(item.id)) return item;
            }
            return null;
        }

        function goToNextUnsignedField(openModal) {
            const nextItem = getNextUnsignedField();
            if (!nextItem) { updateProgress(); return; }
            scrollToPage(nextItem.page);
            requestAnimationFrame(() => {
                nextItem.element.classList.add('active-focus');
                setTimeout(() => nextItem.element.classList.remove('active-focus'), 1500);
                if (openModal) activateSignatureField(nextItem.id, nextItem.page);
            });
        }

        // Check if all required signature fields are signed
        function validateAllSignatures() {
            const totalFields = document.querySelectorAll('.signature-field').length;
            const signedFields = Object.keys(savedSignatures).length;
            
            // Check if all fields are signed
            if (signedFields < totalFields) {
                alert(`Please sign all signature fields. ${signedFields} of ${totalFields} fields are signed.`);
                return false;
            }
            
            // ✅ FIX #3: VALIDATE EACH SIGNATURE HAS ACTUAL DATA
            const emptySignatures = [];
            Object.keys(savedSignatures).forEach(fieldId => {
                const signature = savedSignatures[fieldId];
                
                // Check if signature data is empty or invalid
                if (!signature.data || 
                    signature.data === '' || 
                    signature.data === 'data:image/png;base64,' || 
                    signature.data.length < 50) { // Minimum size for valid signature
                    emptySignatures.push(fieldId);
                }
            });
            
            if (emptySignatures.length > 0) {
                alert(`Some signature fields appear to be empty or invalid. Please re-sign these fields.`);
                
                // Highlight the empty fields
                emptySignatures.forEach(fieldId => {
                    const field = document.getElementById('signature-field-' + fieldId);
                    if (field) {
                        field.style.border = '3px solid red';
                        field.style.animation = 'pulseFocus 1.2s ease-in-out infinite';
                        
                        // Reset highlighting after 3 seconds
                        setTimeout(() => {
                            field.style.border = '';
                            field.style.animation = '';
                        }, 3000);
                    }
                });
                
                return false;
            }
            
            return true;
        }

        function finishSigning() {
            console.log('=== FINISH SIGNING CALLED ===');
            
            // ✅ FIX #1: PREVENT DUPLICATE CLICKS
            if (window.signingInProgress) {
                console.log('Already processing - ignoring duplicate call');
                return;
            }
            
            // Set flag IMMEDIATELY before validation
            window.signingInProgress = true;
            
            if (!validateAllSignatures()) {
                console.log('Validation failed in finishSigning');
                // Reset flag if validation fails
                window.signingInProgress = false;
                return;
            }
            
            console.log('Validation passed - proceeding with submission');
            
            // Show loading state for BOTH submit buttons (synchronized)
            const headerSubmitBtn = document.getElementById('submit-signatures-btn');
            const formSubmitBtn = document.getElementById('form-submit-btn');
            
            if (headerSubmitBtn) {
                headerSubmitBtn.textContent = 'Processing...';
                headerSubmitBtn.disabled = true;
            }
            if (formSubmitBtn) {
                formSubmitBtn.textContent = 'Processing...';
                formSubmitBtn.disabled = true;
            }
            
            console.log('Loading state applied to both buttons');
            
            // Add loading overlay
            const loadingOverlay = document.createElement('div');
            loadingOverlay.id = 'loading-overlay';
            loadingOverlay.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.7);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 9999;
                color: white;
                font-size: 18px;
            `;
            loadingOverlay.innerHTML = `
                <div style="text-align: center;">
                    <div style="margin-bottom: 20px;">⏳</div>
                    <div>Processing your signatures...</div>
                    <div style="font-size: 14px; margin-top: 10px; opacity: 0.8;">Please wait while we save your document</div>
                </div>
            `;
            document.body.appendChild(loadingOverlay);
            
            // ✅ FIX #5: VALIDATE HIDDEN FIELDS WERE POPULATED
            const populated = populateHiddenFields();
            if (!populated) {
                alert('Failed to prepare signature data for submission. Please try again.');
                
                // Reset state
                window.signingInProgress = false;
                const overlay = document.getElementById('loading-overlay');
                if (overlay) overlay.remove();
                if (headerSubmitBtn) {
                    headerSubmitBtn.textContent = 'Submit Signatures';
                    headerSubmitBtn.disabled = false;
                }
                if (formSubmitBtn) {
                    formSubmitBtn.textContent = 'Submit Signatures';
                    formSubmitBtn.disabled = false;
                }
                return;
            }
            
            console.log('Hidden fields populated successfully');
            
            const form = document.getElementById('signature-form');
            console.log('Form found:', !!form);
            console.log('Form action:', form ? form.action : 'N/A');
            
            if (form) {
                // ✅ FIX #2: ADD TIMEOUT MECHANISM FOR SLOW SUBMISSIONS
                // Store timeout IDs in array for proper cleanup
                window.submissionTimeouts = window.submissionTimeouts || [];
                
                const warningTimeout = setTimeout(() => {
                    console.warn('Submission taking longer than expected...');
                    
                    // Update overlay to show it's still processing
                    const overlay = document.getElementById('loading-overlay');
                    if (overlay) {
                        overlay.innerHTML = `
                            <div style="text-align: center;">
                                <div style="margin-bottom: 20px;">⏳</div>
                                <div>Still processing...</div>
                                <div style="font-size: 14px; margin-top: 10px; opacity: 0.8;">
                                    This is taking longer than expected due to large file size or slow connection.
                                    <br>Please wait, do not close this window.
                                </div>
                            </div>
                        `;
                    }
                }, 30000); // Show warning after 30 seconds
                
                // Set a hard timeout after 90 seconds total
                const hardTimeout = setTimeout(() => {
                    console.error('Submission timeout - resetting');
                    window.signingInProgress = false;
                    const overlay = document.getElementById('loading-overlay');
                    if (overlay) overlay.remove();
                    if (headerSubmitBtn) {
                        headerSubmitBtn.textContent = 'Submit Signatures';
                        headerSubmitBtn.disabled = false;
                    }
                    if (formSubmitBtn) {
                        formSubmitBtn.textContent = 'Submit Signatures';
                        formSubmitBtn.disabled = false;
                    }
                    alert('Submission timeout. This may be due to a network issue or server problem. Please check your connection and try again.');
                }, 90000); // Hard timeout at 90 seconds
                
                // Store BOTH timeout IDs for cleanup on successful redirect
                window.submissionTimeouts.push(warningTimeout, hardTimeout);
                
                console.log('Submitting form programmatically...');
                form.submit();
            } else {
                // Handle missing form
                alert('Error: Form not found. Please refresh the page and try again.');
                window.signingInProgress = false;
                const overlay = document.getElementById('loading-overlay');
                if (overlay) overlay.remove();
                if (headerSubmitBtn) {
                    headerSubmitBtn.textContent = 'Submit Signatures';
                    headerSubmitBtn.disabled = false;
                }
                if (formSubmitBtn) {
                    formSubmitBtn.textContent = 'Submit Signatures';
                    formSubmitBtn.disabled = false;
                }
            }
        }

        // Form submit event - only for safety/edge cases
        // Both buttons call finishSigning() which handles validation and submission
        document.querySelector('form').addEventListener('submit', function (e) {
            console.log('=== FORM SUBMIT EVENT (Safety Check) ===');
            console.log('Form action:', this.action);
            
            // This should only fire if form is submitted programmatically by finishSigning()
            // If signingInProgress is not set, something went wrong
            if (!window.signingInProgress) {
                console.error('Form submitted without going through finishSigning() - this should not happen');
                e.preventDefault();
                return false;
            }
            
            console.log('Form submission allowed - signingInProgress is true');
        });

        // ✅ FIX #2: CLEANUP TIMEOUTS ON PAGE UNLOAD (successful redirect)
        window.addEventListener('beforeunload', function() {
            if (window.submissionTimeouts && window.submissionTimeouts.length > 0) {
                window.submissionTimeouts.forEach(timeoutId => clearTimeout(timeoutId));
                window.submissionTimeouts = [];
            }
        });
    </script>
</body>
</html>