/**
 * BigDump - File Polling Module
 * Real-time file list updates without page reload.
 */
(function() {
    'use strict';

    // ============================================
    // Configuration
    // ============================================

    var configEl = document.getElementById('bigdump-config');
    if (!configEl) return; // Not on home page

    var DB_CONFIGURED = JSON.parse(configEl.dataset.dbConfigured || 'false');
    var GZIP_SUPPORTED = JSON.parse(configEl.dataset.gzipSupported || 'false');
    var POLL_INTERVAL = 4000; // 4 seconds

    // ============================================
    // State
    // ============================================

    var filePollingInterval = null;
    var knownFiles = new Set();
    var uploadingFiles = new Set();

    // ============================================
    // Helper Functions
    // ============================================

    /**
     * Get CSS class for file type badge
     * @param {string} type - File type (SQL, GZip, CSV)
     * @returns {string} Tailwind CSS classes
     */
    function getTypeBadgeClass(type) {
        switch (type) {
            case 'SQL': return 'bg-blue-100 dark:bg-blue-900/50 text-blue-800 dark:text-blue-200';
            case 'GZip': return 'bg-purple-100 dark:bg-purple-900/50 text-purple-800 dark:text-purple-200';
            case 'CSV': return 'bg-green-100 dark:bg-green-900/50 text-green-800 dark:text-green-200';
            default: return 'bg-blue-100 dark:bg-blue-900/50 text-blue-800 dark:text-blue-200';
        }
    }

    /**
     * Get file type from extension
     * @param {string} filename - File name
     * @returns {string} File type
     */
    function getFileType(filename) {
        var ext = filename.split('.').pop().toLowerCase();
        if (ext === 'sql') return 'SQL';
        if (ext === 'gz') return 'GZip';
        if (ext === 'csv') return 'CSV';
        return 'SQL';
    }

    /**
     * Check if any modal is currently open
     * @returns {boolean}
     */
    function isModalOpen() {
        var previewModal = document.getElementById('previewModal');
        var historyModal = document.getElementById('historyModal');
        return (previewModal && !previewModal.classList.contains('hidden')) ||
               (historyModal && !historyModal.classList.contains('hidden'));
    }

    // ============================================
    // DOM Functions
    // ============================================

    /**
     * Toggle empty state visibility
     * @param {boolean} isEmpty - Whether file list is empty
     */
    function toggleEmptyState(isEmpty) {
        var noFilesMsg = document.getElementById('noFilesMessage');
        var tableContainer = document.getElementById('filesTableContainer');

        if (!noFilesMsg || !tableContainer) return;

        if (isEmpty) {
            noFilesMsg.classList.remove('hidden');
            tableContainer.classList.add('hidden');
        } else {
            noFilesMsg.classList.add('hidden');
            tableContainer.classList.remove('hidden');
        }
    }

    /**
     * Create a file row element using safe DOM methods
     * @param {Object} file - File data object
     * @param {boolean} isNew - Whether file is newly added
     * @param {boolean} isUploading - Whether file is currently uploading
     * @returns {HTMLTableRowElement}
     */
    function createFileRow(file, isNew, isUploading) {
        var tr = document.createElement('tr');
        tr.className = 'hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-150';
        tr.setAttribute('data-filename', file.name);

        // Add highlight animation for new files
        if (isNew) {
            tr.classList.add('bg-green-50', 'dark:bg-green-900/20', 'animate-pulse');
            setTimeout(function() {
                tr.classList.remove('bg-green-50', 'dark:bg-green-900/20', 'animate-pulse');
            }, 3000);
        }

        // Uploading state
        if (isUploading) {
            tr.classList.add('bg-amber-50', 'dark:bg-amber-900/20');
        }

        var canImport = DB_CONFIGURED && (file.type !== 'GZip' || GZIP_SUPPORTED);
        var cellClass = 'px-4 py-3 border-b border-gray-200 dark:border-gray-700 text-gray-900 dark:text-gray-100';

        // Cell 1: Filename
        var td1 = document.createElement('td');
        td1.className = cellClass;
        var strong = document.createElement('strong');
        strong.textContent = file.name;
        td1.appendChild(strong);
        if (isUploading) {
            var uploadSpan = document.createElement('span');
            uploadSpan.className = 'ml-2 text-amber-600 dark:text-amber-400 text-xs';
            var spinner = document.createElement('i');
            spinner.className = 'fa-solid fa-spinner fa-spin';
            uploadSpan.appendChild(spinner);
            uploadSpan.appendChild(document.createTextNode(' Uploading...'));
            td1.appendChild(uploadSpan);
        } else if (isNew) {
            var newBadge = document.createElement('span');
            newBadge.className = 'ml-2 px-1.5 py-0.5 bg-green-500 text-white text-xs rounded font-medium';
            newBadge.textContent = 'NEW';
            td1.appendChild(newBadge);
        }
        tr.appendChild(td1);

        // Cell 2: Size
        var td2 = document.createElement('td');
        td2.className = cellClass;
        td2.textContent = isUploading ? '--' : file.sizeFormatted;
        tr.appendChild(td2);

        // Cell 3: Date
        var td3 = document.createElement('td');
        td3.className = cellClass;
        td3.textContent = isUploading ? '--' : file.date;
        tr.appendChild(td3);

        // Cell 4: Type badge
        var td4 = document.createElement('td');
        td4.className = cellClass;
        var typeBadge = document.createElement('span');
        typeBadge.className = 'px-2 py-1 rounded text-xs font-medium ' + getTypeBadgeClass(file.type);
        typeBadge.textContent = file.type;
        td4.appendChild(typeBadge);
        tr.appendChild(td4);

        // Cell 5: Actions
        var td5 = document.createElement('td');
        td5.className = cellClass + ' text-center';

        if (isUploading) {
            var placeholder = document.createElement('span');
            placeholder.className = 'text-gray-400';
            placeholder.textContent = '--';
            td5.appendChild(placeholder);
        } else {
            if (canImport) {
                // Preview button
                var previewBtn = document.createElement('button');
                previewBtn.type = 'button';
                previewBtn.className = 'btn btn-icon btn-purple';
                previewBtn.title = 'Preview SQL content';
                previewBtn.onclick = function() { window.previewFile(file.name); };
                var eyeIcon = document.createElement('i');
                eyeIcon.className = 'fa-solid fa-eye';
                previewBtn.appendChild(eyeIcon);
                td5.appendChild(previewBtn);
                td5.appendChild(document.createTextNode(' '));

                // Import form
                var form = document.createElement('form');
                form.method = 'post';
                form.action = '';
                form.style.display = 'inline';
                var hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'fn';
                hiddenInput.value = file.name;
                form.appendChild(hiddenInput);
                var importBtn = document.createElement('button');
                importBtn.type = 'submit';
                importBtn.className = 'btn btn-green';
                importBtn.textContent = 'Import';
                form.appendChild(importBtn);
                td5.appendChild(form);
                td5.appendChild(document.createTextNode(' '));
            } else if (file.type === 'GZip') {
                var notSupported = document.createElement('span');
                notSupported.className = 'text-muted';
                notSupported.textContent = 'GZip not supported';
                td5.appendChild(notSupported);
                td5.appendChild(document.createTextNode(' '));
            }

            // Delete button
            var deleteLink = document.createElement('a');
            deleteLink.href = '?delete=' + encodeURIComponent(file.name);
            deleteLink.className = 'btn btn-red';
            deleteLink.textContent = 'Delete';
            deleteLink.onclick = function() { return confirm('Delete ' + file.name + '?'); };
            td5.appendChild(deleteLink);
        }

        tr.appendChild(td5);
        return tr;
    }

    /**
     * Update file table with new data
     * @param {Array} files - Array of file objects from server
     * @param {Array} newFiles - Array of newly detected files
     */
    function updateFileTable(files, newFiles) {
        var tbody = document.getElementById('fileTableBody');
        if (!tbody) return;

        var newFileNames = new Set(newFiles.map(function(f) { return f.name; }));

        // Build new tbody content
        var fragment = document.createDocumentFragment();

        // First, add uploading files (not yet on server)
        uploadingFiles.forEach(function(filename) {
            if (!files.find(function(f) { return f.name === filename; })) {
                var uploadingFile = { name: filename, type: getFileType(filename), sizeFormatted: '--', date: '--' };
                fragment.appendChild(createFileRow(uploadingFile, false, true));
            }
        });

        // Then add server files
        files.forEach(function(file) {
            var isNew = newFileNames.has(file.name);
            var isUploading = uploadingFiles.has(file.name);
            fragment.appendChild(createFileRow(file, isNew, isUploading));
        });

        // Replace tbody content
        tbody.replaceChildren(fragment);
    }

    // ============================================
    // Polling Functions
    // ============================================

    /**
     * Initialize known files from current DOM
     */
    function initKnownFiles() {
        document.querySelectorAll('#fileTableBody tr[data-filename]').forEach(function(row) {
            knownFiles.add(row.getAttribute('data-filename'));
        });
    }

    /**
     * Start file list polling
     */
    function startFilePolling() {
        initKnownFiles();
        filePollingInterval = setInterval(refreshFileList, POLL_INTERVAL);
    }

    /**
     * Stop file list polling
     */
    function stopFilePolling() {
        if (filePollingInterval) {
            clearInterval(filePollingInterval);
            filePollingInterval = null;
        }
    }

    /**
     * Refresh file list from server
     */
    function refreshFileList() {
        // Skip if modal is open
        if (isModalOpen()) return;

        fetch('/files/list')
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (!data.success) return;

                // Detect new files
                var currentFiles = new Set(data.files.map(function(f) { return f.name; }));
                var newFiles = data.files.filter(function(f) { return !knownFiles.has(f.name); });

                // Update the table
                updateFileTable(data.files, newFiles);

                // Update known files
                knownFiles = currentFiles;

                // Show/hide empty state
                toggleEmptyState(data.files.length === 0);
            })
            .catch(function(err) {
                console.error('File polling error:', err);
            });
    }

    /**
     * Track upload start (for UI feedback)
     * @param {string} filename - File name being uploaded
     */
    function trackUploadStart(filename) {
        uploadingFiles.add(filename);
        refreshFileList();
    }

    /**
     * Track upload end (for UI feedback)
     * @param {string} filename - File name that finished uploading
     */
    function trackUploadEnd(filename) {
        uploadingFiles.delete(filename);
        knownFiles.add(filename); // Don't show as "new" if we just uploaded it
        refreshFileList();
    }

    // ============================================
    // Event Listeners
    // ============================================

    // Pause polling when tab is hidden, resume when visible
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            stopFilePolling();
        } else {
            startFilePolling();
        }
    });

    // Start polling on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', startFilePolling);
    } else {
        startFilePolling();
    }

    // ============================================
    // Expose to Global Namespace
    // ============================================

    window.BigDump = window.BigDump || {};
    window.BigDump.refreshFileList = refreshFileList;
    window.BigDump.trackUploadStart = trackUploadStart;
    window.BigDump.trackUploadEnd = trackUploadEnd;
    window.BigDump.startFilePolling = startFilePolling;
    window.BigDump.stopFilePolling = stopFilePolling;

    // Global alias for fileupload.js compatibility
    window.refreshFileList = refreshFileList;

})();
