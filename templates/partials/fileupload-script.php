        <script>
        (function() {
            'use strict';

            // Configuration
            const CONFIG = {
                maxFileSize: <?= $uploadMaxSize ?>,
                maxConcurrent: 2,
                allowedTypes: ['sql', 'gz', 'csv'],
                uploadUrl: '<?= $view->e($scriptUri) ?>'
            };

            // State
            const state = {
                files: new Map(),
                uploading: 0,
                queue: []
            };

            // DOM Elements
            const dropzone = document.getElementById('dropzone');
            const fileInput = document.getElementById('fileInput');
            const fileList = document.getElementById('fileList');
            const uploadActions = document.getElementById('uploadActions');
            const uploadBtn = document.getElementById('uploadBtn');
            const clearBtn = document.getElementById('clearBtn');

            // Utility: Format bytes
            function formatBytes(bytes) {
                if (bytes === 0) return '0 B';
                const k = 1024;
                const sizes = ['B', 'KB', 'MB', 'GB'];
                const i = Math.floor(Math.log(bytes) / Math.log(k));
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
                const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
                svg.setAttribute('viewBox', '0 0 24 24');

                const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');

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
                const ext = getExtension(file.name);

                if (!CONFIG.allowedTypes.includes(ext)) {
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
                const ext = getExtension(file.name);
                const isValid = validation.valid;

                // Main container
                const item = document.createElement('div');
                item.className = 'file-upload__item' + (!isValid ? ' file-upload__item--error' : '');
                item.id = id;

                // Icon
                const icon = document.createElement('div');
                icon.className = 'file-upload__item-icon file-upload__item-icon--' + ext;
                icon.textContent = ext;
                item.appendChild(icon);

                // Info container
                const info = document.createElement('div');
                info.className = 'file-upload__item-info';

                const name = document.createElement('div');
                name.className = 'file-upload__item-name';
                name.textContent = file.name;
                info.appendChild(name);

                const meta = document.createElement('div');
                meta.className = 'file-upload__item-meta';
                meta.textContent = formatBytes(file.size);
                if (!isValid) {
                    const errorSpan = document.createElement('span');
                    errorSpan.className = 'file-upload__error';
                    errorSpan.textContent = ' — ' + validation.error;
                    meta.appendChild(errorSpan);
                }
                info.appendChild(meta);
                item.appendChild(info);

                // Progress container
                const progressContainer = document.createElement('div');
                progressContainer.className = 'file-upload__item-progress';
                progressContainer.style.display = 'none';

                const progressBar = document.createElement('div');
                progressBar.className = 'file-upload__progress-bar';

                const progressFill = document.createElement('div');
                progressFill.className = 'file-upload__progress-fill';
                progressFill.style.width = '0%';
                progressBar.appendChild(progressFill);
                progressContainer.appendChild(progressBar);

                const progressText = document.createElement('div');
                progressText.className = 'file-upload__progress-text';
                progressText.textContent = '0%';
                progressContainer.appendChild(progressText);
                item.appendChild(progressContainer);

                // Status
                const status = document.createElement('div');
                status.className = 'file-upload__item-status';
                item.appendChild(status);

                // Remove button
                const removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.className = 'file-upload__item-remove';
                removeBtn.dataset.id = id;
                removeBtn.appendChild(createSvg('remove'));
                item.appendChild(removeBtn);

                return item;
            }

            // Add files to queue
            function addFiles(filesList) {
                for (const file of filesList) {
                    const id = generateId();
                    const validation = validateFile(file);

                    state.files.set(id, {
                        file: file,
                        status: validation.valid ? 'pending' : 'invalid',
                        progress: 0,
                        validation: validation
                    });

                    const item = createFileItem(id, file, validation);
                    fileList.appendChild(item);
                }

                updateActions();
            }

            // Remove file from queue
            function removeFile(id) {
                const item = document.getElementById(id);
                if (item) {
                    item.remove();
                }
                state.files.delete(id);
                updateActions();
            }

            // Update actions visibility
            function updateActions() {
                const hasValidFiles = Array.from(state.files.values())
                    .some(f => f.status === 'pending');
                uploadActions.style.display = state.files.size > 0 ? 'flex' : 'none';
                uploadBtn.disabled = !hasValidFiles || state.uploading > 0;
            }

            // Upload single file
            function uploadFile(id) {
                const fileData = state.files.get(id);
                if (!fileData || fileData.status !== 'pending') return Promise.resolve();

                return new Promise((resolve) => {
                    const item = document.getElementById(id);
                    const progressContainer = item.querySelector('.file-upload__item-progress');
                    const progressFill = item.querySelector('.file-upload__progress-fill');
                    const progressText = item.querySelector('.file-upload__progress-text');
                    const statusEl = item.querySelector('.file-upload__item-status');
                    const removeBtn = item.querySelector('.file-upload__item-remove');

                    // Update UI
                    item.classList.add('file-upload__item--uploading');
                    progressContainer.style.display = 'block';
                    removeBtn.style.display = 'none';

                    // Add spinner
                    statusEl.textContent = '';
                    const spinner = document.createElement('div');
                    spinner.className = 'file-upload__spinner';
                    statusEl.appendChild(spinner);

                    fileData.status = 'uploading';

                    // Create FormData
                    const formData = new FormData();
                    formData.append('dumpfile', fileData.file);
                    formData.append('uploadbutton', '1');

                    // Create XHR
                    const xhr = new XMLHttpRequest();

                    xhr.upload.addEventListener('progress', (e) => {
                        if (e.lengthComputable) {
                            const percent = Math.round((e.loaded / e.total) * 100);
                            progressFill.style.width = percent + '%';
                            progressText.textContent = percent + '%';
                            fileData.progress = percent;
                        }
                    });

                    xhr.addEventListener('load', () => {
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

                    xhr.addEventListener('error', () => {
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
                    const id = state.queue.shift();
                    uploadFile(id);
                }

                updateActions();

                // Check if all done
                if (state.uploading === 0 && state.queue.length === 0) {
                    const hasSuccess = Array.from(state.files.values())
                        .some(f => f.status === 'success');
                    if (hasSuccess) {
                        // Reload page after short delay to show updated file list
                        setTimeout(() => location.reload(), 1000);
                    }
                }
            }

            // Start all uploads
            function startUpload() {
                state.queue = [];
                for (const [id, fileData] of state.files) {
                    if (fileData.status === 'pending') {
                        state.queue.push(id);
                    }
                }
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
            dropzone.addEventListener('click', () => fileInput.click());

            // Event: File input change
            fileInput.addEventListener('change', (e) => {
                addFiles(e.target.files);
                fileInput.value = '';
            });

            // Event: Drag events
            ['dragenter', 'dragover'].forEach(event => {
                dropzone.addEventListener(event, (e) => {
                    e.preventDefault();
                    dropzone.classList.add('file-upload__dropzone--active');
                });
            });

            ['dragleave', 'drop'].forEach(event => {
                dropzone.addEventListener(event, (e) => {
                    e.preventDefault();
                    dropzone.classList.remove('file-upload__dropzone--active');
                });
            });

            dropzone.addEventListener('drop', (e) => {
                const files = e.dataTransfer.files;
                addFiles(files);
            });

            // Event: Remove file
            fileList.addEventListener('click', (e) => {
                const removeBtn = e.target.closest('.file-upload__item-remove');
                if (removeBtn) {
                    const id = removeBtn.dataset.id;
                    removeFile(id);
                }
            });

            // Event: Upload button
            uploadBtn.addEventListener('click', startUpload);

            // Event: Clear button
            clearBtn.addEventListener('click', clearAll);

            // Prevent default drag behavior on window
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(event => {
                window.addEventListener(event, (e) => e.preventDefault());
            });
        })();
        </script>
