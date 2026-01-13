/**
 * BigDump - SQL Preview Module
 * Handles SQL file preview modal functionality with safe DOM methods.
 */
(function() {
    'use strict';

    // ============================================
    // Helper Functions
    // ============================================

    /**
     * Classify SQL query type from content
     * @param {string} query - SQL query string
     * @returns {string} Query type label
     */
    function getQueryType(query) {
        var q = query.trim().toUpperCase();
        if (q.startsWith('CREATE TABLE')) return 'CREATE TABLE';
        if (q.startsWith('CREATE DATABASE')) return 'CREATE DATABASE';
        if (q.startsWith('DROP TABLE')) return 'DROP TABLE';
        if (q.startsWith('INSERT')) return 'INSERT';
        if (q.startsWith('UPDATE')) return 'UPDATE';
        if (q.startsWith('DELETE')) return 'DELETE';
        if (q.startsWith('ALTER')) return 'ALTER';
        if (q.startsWith('SET')) return 'SET';
        if (q.startsWith('USE')) return 'USE';
        return 'SQL';
    }

    /**
     * Get CSS class for query type badge
     * @param {string} query - SQL query string
     * @returns {string} Tailwind CSS classes
     */
    function getQueryTypeClass(query) {
        var type = getQueryType(query);
        switch (type) {
            case 'CREATE TABLE':
            case 'CREATE DATABASE':
                return 'bg-green-100 dark:bg-green-900/50 text-green-800 dark:text-green-200';
            case 'DROP TABLE':
            case 'DELETE':
                return 'bg-red-100 dark:bg-red-900/50 text-red-800 dark:text-red-200';
            case 'INSERT':
                return 'bg-blue-100 dark:bg-blue-900/50 text-blue-800 dark:text-blue-200';
            case 'UPDATE':
            case 'ALTER':
                return 'bg-amber-100 dark:bg-amber-900/50 text-amber-800 dark:text-amber-200';
            default:
                return 'bg-gray-100 dark:bg-gray-600 text-gray-800 dark:text-gray-200';
        }
    }

    // ============================================
    // Main Functions
    // ============================================

    /**
     * Open preview modal and fetch SQL file content
     * @param {string} filename - Name of file to preview
     */
    function previewFile(filename) {
        var modal = document.getElementById('previewModal');
        var loading = document.getElementById('previewLoading');
        var error = document.getElementById('previewError');
        var content = document.getElementById('previewContent');

        // Reset state
        modal.classList.remove('hidden');
        loading.classList.remove('hidden');
        error.classList.add('hidden');
        content.classList.add('hidden');

        // Update title using textContent (safe)
        document.getElementById('previewModalTitle').textContent = filename;
        document.getElementById('previewModalSubtitle').textContent = 'Loading preview...';
        document.getElementById('previewImportFilename').value = filename;

        // Fetch preview
        fetch('/preview?fn=' + encodeURIComponent(filename))
            .then(function(response) { return response.json(); })
            .then(function(data) {
                loading.classList.add('hidden');

                if (data.error) {
                    error.classList.remove('hidden');
                    document.getElementById('previewErrorMessage').textContent = data.error;
                    return;
                }

                // Update info using textContent (safe)
                document.getElementById('previewModalSubtitle').textContent = data.fileSizeFormatted + (data.isGzip ? ' (GZip compressed)' : '');
                document.getElementById('previewFileSize').textContent = data.fileSizeFormatted;
                document.getElementById('previewFileType').textContent = data.isGzip ? 'GZip' : 'SQL';
                document.getElementById('previewTotalLines').textContent = data.totalLines.toLocaleString();
                document.getElementById('previewQueriesCount').textContent = data.queriesPreview;
                document.getElementById('tabQueriesCount').textContent = data.queriesPreview;

                // Raw content - use textContent for safety
                document.getElementById('previewRaw').textContent = data.rawContent;

                // Queries list - build safely with DOM methods
                var queriesEl = document.getElementById('previewQueries');
                queriesEl.replaceChildren(); // Clear safely

                data.queries.forEach(function(query, index) {
                    var div = document.createElement('div');
                    div.className = 'bg-gray-50 dark:bg-gray-700 rounded-lg p-4';

                    // Header row
                    var header = document.createElement('div');
                    header.className = 'flex items-center justify-between mb-2';

                    var label = document.createElement('span');
                    label.className = 'text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400';
                    label.textContent = 'Query ' + (index + 1);

                    var badge = document.createElement('span');
                    badge.className = 'text-xs px-2 py-1 rounded ' + getQueryTypeClass(query);
                    badge.textContent = getQueryType(query);

                    header.appendChild(label);
                    header.appendChild(badge);

                    // Query content
                    var pre = document.createElement('pre');
                    pre.className = 'text-sm font-mono text-gray-800 dark:text-gray-200 overflow-x-auto whitespace-pre-wrap';
                    pre.textContent = query; // Safe: textContent escapes HTML

                    div.appendChild(header);
                    div.appendChild(pre);
                    queriesEl.appendChild(div);
                });

                content.classList.remove('hidden');
                switchPreviewTab('raw');
            })
            .catch(function(err) {
                loading.classList.add('hidden');
                error.classList.remove('hidden');
                document.getElementById('previewErrorMessage').textContent = 'Network error: ' + err.message;
            });
    }

    /**
     * Close preview modal
     * @param {Event} [event] - Click event (for backdrop click detection)
     */
    function closePreviewModal(event) {
        if (event && event.target !== event.currentTarget) return;
        document.getElementById('previewModal').classList.add('hidden');
    }

    /**
     * Switch between raw and queries tabs in preview modal
     * @param {string} tab - Tab name ('raw' or 'queries')
     */
    function switchPreviewTab(tab) {
        var rawTab = document.getElementById('tabRaw');
        var queriesTab = document.getElementById('tabQueries');
        var rawContent = document.getElementById('previewRaw');
        var queriesContent = document.getElementById('previewQueries');

        if (tab === 'raw') {
            rawTab.classList.add('border-blue-500', 'text-blue-600', 'dark:text-blue-400');
            rawTab.classList.remove('border-transparent', 'text-gray-500');
            queriesTab.classList.remove('border-blue-500', 'text-blue-600', 'dark:text-blue-400');
            queriesTab.classList.add('border-transparent', 'text-gray-500');
            rawContent.classList.remove('hidden');
            queriesContent.classList.add('hidden');
        } else {
            queriesTab.classList.add('border-blue-500', 'text-blue-600', 'dark:text-blue-400');
            queriesTab.classList.remove('border-transparent', 'text-gray-500');
            rawTab.classList.remove('border-blue-500', 'text-blue-600', 'dark:text-blue-400');
            rawTab.classList.add('border-transparent', 'text-gray-500');
            queriesContent.classList.remove('hidden');
            rawContent.classList.add('hidden');
        }
    }

    // ============================================
    // Expose to Global Namespace
    // ============================================

    window.BigDump = window.BigDump || {};
    window.BigDump.previewFile = previewFile;
    window.BigDump.closePreviewModal = closePreviewModal;
    window.BigDump.switchPreviewTab = switchPreviewTab;

    // Global aliases for onclick handlers in HTML
    window.previewFile = previewFile;
    window.closePreviewModal = closePreviewModal;
    window.switchPreviewTab = switchPreviewTab;

})();
