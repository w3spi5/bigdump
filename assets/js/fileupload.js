/**
 * BigDump - File Upload Module
 *
 * Handles drag & drop file uploads with progress tracking.
 * Configuration is passed via data attributes on #fileUpload element.
 */

(function() {
    'use strict';

    // Check if upload component exists
    var fileUploadEl = document.getElementById('fileUpload');
    if (!fileUploadEl) return;

    // Read BZ2 support from bigdump-config element
    var configEl = document.getElementById('bigdump-config');
    var bz2Supported = false;
    if (configEl && configEl.dataset.bz2Supported !== undefined) {
        bz2Supported = configEl.dataset.bz2Supported === 'true';
    }

    // Build allowed types array based on extension support
    var allowedTypes = ['sql', 'gz'];
    if (bz2Supported) {
        allowedTypes.push('bz2');
    }
    allowedTypes.push('csv');

    // Read configuration from data attributes
    var CONFIG = {
        maxFileSize: parseInt(fileUploadEl.dataset.maxFileSize, 10) || 0,
        maxConcurrent: 2,
        allowedTypes: allowedTypes,
        uploadUrl: fileUploadEl.dataset.uploadUrl || '',
        bz2Supported: bz2Supported
    };

    // State
    var state = {
        files: new Map(),
        uploading: 0,
        queue: []
    };

    // DOM Elements
    var dropzone = document.getElementById('dropzone');
    var fileInput = document.getElementById('fileInput');
    var fileList = document.getElementById('fileList');
    var uploadActions = document.getElementById('uploadActions');
    var uploadBtn = document.getElementById('uploadBtn');
    var clearBtn = document.getElementById('clearBtn');

    // Utility: Format bytes
    function formatBytes(bytes) {
        if (bytes === 0) return '0 B';
        var k = 1024;
        var sizes = ['B', 'KB', 'MB', 'GB'];
        var i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    // Utility: Get file extension
    function getExtension(filename) {
        return filename.split('.').pop().toLowerCase();
    }

    // Utility: Generate unique ID
    function generateId() {
        return 'file_' + Math.random().toString(36).substr(2, 9);
    }

    // Utility: Create SVG element
    function createSvg(type) {
        var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        svg.setAttribute('viewBox', '0 0 24 24');

        var path = document.createElementNS('http://www.w3.org/2000/svg', 'path');

        if (type === 'success') {
            svg.setAttribute('fill', '#38a169');
            path.setAttribute('d', 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z');
        } else if (type === 'error') {
            svg.setAttribute('fill', '#e53e3e');
            path.setAttribute('d', 'M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z');
        } else if (type === 'remove') {
            path.setAttribute('d', 'M6 18L18 6M6 6l12 12');
            path.setAttribute('stroke', 'currentColor');
            path.setAttribute('stroke-width', '2');
            path.setAttribute('stroke-linecap', 'round');
        }

        svg.appendChild(path);
        return svg;
    }

    // Validate file
    function validateFile(file) {
        var ext = getExtension(file.name);

        // Special error message for .bz2 when extension not supported
        if (ext === 'bz2' && !CONFIG.bz2Supported) {
            return { valid: false, error: 'BZ2 files require the PHP bz2 extension which is not installed on the server' };
        }

        if (CONFIG.allowedTypes.indexOf(ext) === -1) {
            return { valid: false, error: 'Invalid file type. Allowed: ' + CONFIG.allowedTypes.join(', ') };
        }

        if (file.size > CONFIG.maxFileSize) {
            return { valid: false, error: 'File too large. Max: ' + formatBytes(CONFIG.maxFileSize) };
        }

        if (file.size === 0) {
            return { valid: false, error: 'File is empty' };
        }

        return { valid: true };
    }

    // Create file item using DOM methods (no innerHTML)
    function createFileItem(id, file, validation) {
        var ext = getExtension(file.name);
        var isValid = validation.valid;

        // Main container
        var item = document.createElement('div');
        item.className = 'file-upload__item' + (!isValid ? ' file-upload__item--error' : '');
        item.id = id;

        // Icon
        var icon = document.createElement('div');
        icon.className = 'file-upload__item-icon file-upload__item-icon--' + ext;
        icon.textContent = ext;
        item.appendChild(icon);

        // Info container
        var info = document.createElement('div');
        info.className = 'file-upload__item-info';

        var name = document.createElement('div');
        name.className = 'file-upload__item-name';
        name.textContent = file.name;
        info.appendChild(name);

        var meta = document.createElement('div');
        meta.className = 'file-upload__item-meta';
        meta.textContent = formatBytes(file.size);
        if (!isValid) {
            var errorSpan = document.createElement('span');
            errorSpan.className = 'file-upload__error';
            errorSpan.textContent = ' — ' + validation.error;
            meta.appendChild(errorSpan);
        }
        info.appendChild(meta);
        item.appendChild(info);

        // Progress container
        var progressContainer = document.createElement('div');
        progressContainer.className = 'file-upload__item-progress';
        progressContainer.style.display = 'none';

        var progressBar = document.createElement('div');
        progressBar.className = 'file-upload__progress-bar';

        var progressFill = document.createElement('div');
        progressFill.className = 'file-upload__progress-fill';
        progressFill.style.width = '0%';
        progressBar.appendChild(progressFill);
        progressContainer.appendChild(progressBar);

        var progressText = document.createElement('div');
        progressText.className = 'file-upload__progress-text';
        progressText.textContent = '0%';
        progressContainer.appendChild(progressText);
        item.appendChild(progressContainer);

        // Status
        var status = document.createElement('div');
        status.className = 'file-upload__item-status';
        item.appendChild(status);

        // Remove button
        var removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'file-upload__item-remove';
        removeBtn.dataset.id = id;
        removeBtn.appendChild(createSvg('remove'));
        item.appendChild(removeBtn);

        return item;
    }

    // Add files to queue
    function addFiles(filesList) {
        for (var i = 0; i < filesList.length; i++) {
            var file = filesList[i];
            var id = generateId();
            var validation = validateFile(file);

            state.files.set(id, {
                file: file,
                status: validation.valid ? 'pending' : 'invalid',
                progress: 0,
                validation: validation
            });

            var item = createFileItem(id, file, validation);
            fileList.appendChild(item);
        }

        updateActions();
    }

    // Remove file from queue
    function removeFile(id) {
        var item = document.getElementById(id);
        if (item) {
            item.remove();
        }
        state.files.delete(id);
        updateActions();
    }

    // Update actions visibility
    function updateActions() {
        var hasValidFiles = false;
        state.files.forEach(function(f) {
            if (f.status === 'pending') hasValidFiles = true;
        });
        uploadActions.style.display = state.files.size > 0 ? 'flex' : 'none';
        uploadBtn.disabled = !hasValidFiles || state.uploading > 0;
    }

    // Upload single file
    function uploadFile(id) {
        var fileData = state.files.get(id);
        if (!fileData || fileData.status !== 'pending') return Promise.resolve();

        return new Promise(function(resolve) {
            var item = document.getElementById(id);
            var progressContainer = item.querySelector('.file-upload__item-progress');
            var progressFill = item.querySelector('.file-upload__progress-fill');
            var progressText = item.querySelector('.file-upload__progress-text');
            var statusEl = item.querySelector('.file-upload__item-status');
            var removeBtn = item.querySelector('.file-upload__item-remove');

            // Update UI
            item.classList.add('file-upload__item--uploading');
            progressContainer.style.display = 'block';
            removeBtn.style.display = 'none';

            // Add spinner
            statusEl.textContent = '';
            var spinner = document.createElement('div');
            spinner.className = 'file-upload__spinner';
            statusEl.appendChild(spinner);

            fileData.status = 'uploading';

            // Create FormData
            var formData = new FormData();
            formData.append('dumpfile', fileData.file);
            formData.append('uploadbutton', '1');

            // Create XHR
            var xhr = new XMLHttpRequest();

            xhr.upload.addEventListener('progress', function(e) {
                if (e.lengthComputable) {
                    var percent = Math.round((e.loaded / e.total) * 100);
                    progressFill.style.width = percent + '%';
                    progressText.textContent = percent + '%';
                    fileData.progress = percent;
                }
            });

            xhr.addEventListener('load', function() {
                item.classList.remove('file-upload__item--uploading');
                progressContainer.style.display = 'none';
                statusEl.textContent = '';

                if (xhr.status === 200) {
                    item.classList.add('file-upload__item--success');
                    statusEl.appendChild(createSvg('success'));
                    fileData.status = 'success';
                } else {
                    item.classList.add('file-upload__item--error');
                    statusEl.appendChild(createSvg('error'));
                    fileData.status = 'error';
                }

                state.uploading--;
                resolve();
                processQueue();
            });

            xhr.addEventListener('error', function() {
                item.classList.remove('file-upload__item--uploading');
                item.classList.add('file-upload__item--error');
                progressContainer.style.display = 'none';
                statusEl.textContent = '';
                statusEl.appendChild(createSvg('error'));
                fileData.status = 'error';
                state.uploading--;
                resolve();
                processQueue();
            });

            xhr.open('POST', CONFIG.uploadUrl);
            xhr.send(formData);
            state.uploading++;
        });
    }

    // Process upload queue
    function processQueue() {
        while (state.uploading < CONFIG.maxConcurrent && state.queue.length > 0) {
            var id = state.queue.shift();
            uploadFile(id);
        }

        updateActions();

        // Check if all done
        if (state.uploading === 0 && state.queue.length === 0) {
            var hasSuccess = false;
            state.files.forEach(function(f) {
                if (f.status === 'success') hasSuccess = true;
            });
            if (hasSuccess) {
                // Refresh file list using real-time polling (no page reload)
                setTimeout(function() {
                    if (typeof refreshFileList === 'function') {
                        // Clear upload UI and refresh file list
                        clearAll();
                        refreshFileList();
                    } else {
                        // Fallback: reload page if polling not available
                        location.reload();
                    }
                }, 500);
            }
        }
    }

    // Start all uploads
    function startUpload() {
        state.queue = [];
        state.files.forEach(function(fileData, id) {
            if (fileData.status === 'pending') {
                state.queue.push(id);
            }
        });
        processQueue();
    }

    // Clear all files
    function clearAll() {
        state.files.clear();
        state.queue = [];
        fileList.textContent = '';
        updateActions();
    }

    // Event: Dropzone click
    dropzone.addEventListener('click', function() { fileInput.click(); });

    // Event: File input change
    fileInput.addEventListener('change', function(e) {
        addFiles(e.target.files);
        fileInput.value = '';
    });

    // Event: Drag events
    ['dragenter', 'dragover'].forEach(function(event) {
        dropzone.addEventListener(event, function(e) {
            e.preventDefault();
            dropzone.classList.add('file-upload__dropzone--active');
        });
    });

    ['dragleave', 'drop'].forEach(function(event) {
        dropzone.addEventListener(event, function(e) {
            e.preventDefault();
            dropzone.classList.remove('file-upload__dropzone--active');
        });
    });

    dropzone.addEventListener('drop', function(e) {
        var files = e.dataTransfer.files;
        addFiles(files);
    });

    // Event: Remove file
    fileList.addEventListener('click', function(e) {
        var removeBtn = e.target.closest('.file-upload__item-remove');
        if (removeBtn) {
            var id = removeBtn.dataset.id;
            removeFile(id);
        }
    });

    // Event: Upload button
    uploadBtn.addEventListener('click', startUpload);

    // Event: Clear button
    clearBtn.addEventListener('click', clearAll);

    // Prevent default drag behavior on window
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(function(event) {
        window.addEventListener(event, function(e) { e.preventDefault(); });
    });
})();
