<?php
/**
 * Vue: Page d'accueil
 *
 * Affiche la liste des fichiers disponibles et le formulaire d'upload.
 *
 * @var \BigDump\Core\View $view
 * @var array $files Liste des fichiers
 * @var bool $dbConfigured Base de données configurée
 * @var array|null $connectionInfo Informations de connexion
 * @var bool $uploadEnabled Upload activé
 * @var int $uploadMaxSize Taille maximale d'upload
 * @var string $uploadDir Répertoire d'upload
 * @var string $predefinedFile Fichier prédéfini
 * @var string $dbName Nom de la base de données
 * @var string $dbServer Serveur de base de données
 * @var bool $testMode Mode test
 */
?>

<?php if ($testMode): ?>
<div class="alert alert-warning">
    <strong>Test Mode Enabled</strong> - Queries will be parsed but not executed.
</div>
<?php endif; ?>

<?php if (isset($uploadResult)): ?>
    <?php if ($uploadResult['success']): ?>
        <div class="alert alert-success"><?= $view->e($uploadResult['message']) ?></div>
    <?php else: ?>
        <div class="alert alert-error"><?= $view->e($uploadResult['message']) ?></div>
    <?php endif; ?>
<?php endif; ?>

<?php if (isset($deleteResult)): ?>
    <?php if ($deleteResult['success']): ?>
        <div class="alert alert-success"><?= $view->e($deleteResult['message']) ?></div>
    <?php else: ?>
        <div class="alert alert-error"><?= $view->e($deleteResult['message']) ?></div>
    <?php endif; ?>
<?php endif; ?>

<?php if (!$dbConfigured): ?>
<div class="alert alert-error">
    <strong>Database not configured!</strong><br>
    Please edit <code>config/config.php</code> and set your database credentials.
</div>
<?php elseif ($connectionInfo && !$connectionInfo['success']): ?>
<div class="alert alert-error">
    <strong>Database connection failed!</strong><br>
    <?= $view->e($connectionInfo['message']) ?>
</div>
<?php elseif ($connectionInfo && $connectionInfo['success']): ?>
<div class="alert alert-info">
    Connected to <strong><?= $view->e($dbName) ?></strong> at <strong><?= $view->e($dbServer) ?></strong><br>
    Connection charset: <strong><?= $view->e($connectionInfo['charset']) ?></strong>
    <span class="text-muted">(Your dump file must use the same charset)</span>
</div>
<?php endif; ?>

<?php if (!empty($predefinedFile)): ?>
    <div class="alert alert-info">
        <strong>Predefined file:</strong> <?= $view->e($predefinedFile) ?><br>
        <a href="<?= $view->url(['start' => 1, 'fn' => $predefinedFile, 'foffset' => 0, 'totalqueries' => 0]) ?>" class="btn btn-primary mt-3">
            Start Import
        </a>
    </div>
<?php else: ?>

    <h3 class="mb-3">Available Dump Files</h3>

    <?php if (empty($files)): ?>
        <div class="alert alert-warning">
            No dump files found in <code><?= $view->e($uploadDir) ?></code><br>
            Upload a .sql, .gz or .csv file using the form below, or via FTP.
        </div>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Filename</th>
                    <th>Size</th>
                    <th>Date</th>
                    <th>Type</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($files as $file): ?>
                <tr>
                    <td><strong><?= $view->e($file['name']) ?></strong></td>
                    <td><?= $view->formatBytes($file['size']) ?></td>
                    <td><?= $view->e($file['date']) ?></td>
                    <td>
                        <?php
                        $typeClass = match($file['type']) {
                            'SQL' => 'sql',
                            'GZip' => 'gz',
                            'CSV' => 'csv',
                            default => 'sql'
                        };
                        ?>
                        <span class="file-type file-type-<?= $typeClass ?>"><?= $view->e($file['type']) ?></span>
                    </td>
                    <td class="text-center">
                        <?php if ($dbConfigured && $connectionInfo && $connectionInfo['success']): ?>
                            <?php if ($file['type'] !== 'GZip' || function_exists('gzopen')): ?>
                                <a href="<?= $view->url(['start' => 1, 'fn' => $file['name'], 'foffset' => 0, 'totalqueries' => 0]) ?>"
                                   class="btn btn-primary">
                                    Import
                                </a>
                            <?php else: ?>
                                <span class="text-muted">GZip not supported</span>
                            <?php endif; ?>
                        <?php endif; ?>
                        <a href="<?= $view->url(['delete' => $file['name']]) ?>"
                           class="btn btn-danger"
                           onclick="return confirm('Delete <?= $view->escapeJs($file['name']) ?>?')">
                            Delete
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <hr class="mt-3 mb-3" style="border: none; border-top: 1px solid #e2e8f0;">

    <h3 class="mb-3">Upload Dump Files</h3>

    <?php if (!$uploadEnabled): ?>
        <div class="alert alert-warning">
            Upload disabled. Directory <code><?= $view->e($uploadDir) ?></code> is not writable.<br>
            Set permissions to 755 or 777, or upload files via FTP.
        </div>
    <?php else: ?>
        <p class="text-muted mb-3">
            Maximum file size: <strong><?= $view->formatBytes($uploadMaxSize) ?></strong> &bull;
            Allowed types: <strong>.sql, .gz, .csv</strong><br>
            For larger files, use FTP to upload directly to <code><?= $view->e($uploadDir) ?></code>
        </p>

        <div class="file-upload" id="fileUpload">
            <!-- Dropzone -->
            <div class="file-upload__dropzone" id="dropzone">
                <div class="file-upload__icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <div class="file-upload__text">
                    <strong>Click to upload</strong> or drag and drop
                </div>
                <div class="file-upload__hint">
                    SQL, GZip or CSV files up to <?= $view->formatBytes($uploadMaxSize) ?>
                </div>
                <input type="file"
                       class="file-upload__input"
                       id="fileInput"
                       accept=".sql,.gz,.csv"
                       multiple>
            </div>

            <!-- File List -->
            <div class="file-upload__list" id="fileList"></div>

            <!-- Actions -->
            <div class="file-upload__actions" id="uploadActions" style="display: none;">
                <button type="button" class="btn btn-primary" id="uploadBtn">
                    Upload All Files
                </button>
                <button type="button" class="btn btn-secondary" id="clearBtn">
                    Clear All
                </button>
            </div>
        </div>

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
    <?php endif; ?>

<?php endif; ?>
