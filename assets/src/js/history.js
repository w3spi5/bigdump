/**
 * BigDump - Import History Module
 * Handles import history modal functionality.
 */
(function() {
    'use strict';

    // ============================================
    // Main Functions
    // ============================================

    /**
     * Open history modal and fetch import history data
     */
    function showHistory() {
        var modal = document.getElementById('historyModal');
        var loading = document.getElementById('historyLoading');
        var content = document.getElementById('historyContent');

        modal.classList.remove('hidden');
        loading.classList.remove('hidden');
        content.classList.add('hidden');

        fetch('?action=history&limit=20')
            .then(function(response) { return response.json(); })
            .then(function(data) {
                loading.classList.add('hidden');

                if (!data.success) {
                    content.textContent = 'Error loading history';
                    content.classList.remove('hidden');
                    return;
                }

                // Update statistics
                var stats = data.statistics;
                document.getElementById('histStatTotal').textContent = stats.total_imports;
                document.getElementById('histStatSuccess').textContent = stats.successful_imports;
                document.getElementById('histStatFailed').textContent = stats.failed_imports;
                document.getElementById('histStatQueries').textContent = stats.total_queries.toLocaleString();

                // Build history table
                var tbody = document.getElementById('historyTableBody');
                tbody.replaceChildren();

                if (data.history.length === 0) {
                    var tr = document.createElement('tr');
                    var td = document.createElement('td');
                    td.colSpan = 5;
                    td.className = 'px-4 py-8 text-center text-gray-500 dark:text-gray-400';
                    td.textContent = 'No import history yet';
                    tr.appendChild(td);
                    tbody.appendChild(tr);
                } else {
                    data.history.forEach(function(entry) {
                        var tr = document.createElement('tr');
                        tr.className = 'hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-150';

                        // Status icon
                        var tdStatus = document.createElement('td');
                        tdStatus.className = 'px-4 py-3 border-b border-gray-200 dark:border-gray-700 text-center';
                        var iconName = entry.success ? 'circle-check' : 'circle-xmark';
                        var iconColor = entry.success ? 'text-green-500' : 'text-red-500';
                        var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
                        svg.setAttribute('class', 'icon w-5 h-5 fill-current inline-block ' + iconColor);
                        var use = document.createElementNS('http://www.w3.org/2000/svg', 'use');
                        use.setAttributeNS('http://www.w3.org/1999/xlink', 'href', 'assets/icons.svg#' + iconName);
                        svg.appendChild(use);
                        tdStatus.appendChild(svg);

                        // Filename
                        var tdFile = document.createElement('td');
                        tdFile.className = 'px-4 py-3 border-b border-gray-200 dark:border-gray-700 text-gray-900 dark:text-gray-100 font-medium';
                        tdFile.textContent = entry.filename;

                        // Date
                        var tdDate = document.createElement('td');
                        tdDate.className = 'px-4 py-3 border-b border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-400 text-sm';
                        tdDate.textContent = entry.datetime;

                        // Stats
                        var tdStats = document.createElement('td');
                        tdStats.className = 'px-4 py-3 border-b border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-400 text-sm';
                        tdStats.textContent = entry.queries_executed.toLocaleString() + ' queries / ' + entry.size_formatted;

                        // Result
                        var tdResult = document.createElement('td');
                        tdResult.className = 'px-4 py-3 border-b border-gray-200 dark:border-gray-700';
                        var badge = document.createElement('span');
                        badge.className = entry.success
                            ? 'px-2 py-1 rounded text-xs font-medium bg-green-100 dark:bg-green-900/50 text-green-800 dark:text-green-200'
                            : 'px-2 py-1 rounded text-xs font-medium bg-red-100 dark:bg-red-900/50 text-red-800 dark:text-red-200';
                        badge.textContent = entry.success ? 'Success' : 'Failed';
                        tdResult.appendChild(badge);

                        tr.appendChild(tdStatus);
                        tr.appendChild(tdFile);
                        tr.appendChild(tdDate);
                        tr.appendChild(tdStats);
                        tr.appendChild(tdResult);
                        tbody.appendChild(tr);
                    });
                }

                content.classList.remove('hidden');
            })
            .catch(function(err) {
                loading.classList.add('hidden');
                content.textContent = 'Network error: ' + err.message;
                content.classList.remove('hidden');
            });
    }

    /**
     * Close history modal
     * @param {Event} [event] - Click event (for backdrop click detection)
     */
    function closeHistoryModal(event) {
        if (event && event.target !== event.currentTarget) return;
        document.getElementById('historyModal').classList.add('hidden');
    }

    /**
     * Clear all import history with confirmation
     */
    function clearHistory() {
        if (!confirm('Are you sure you want to clear all import history?')) return;

        fetch('?action=history&do=clear')
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success) {
                    showHistory(); // Refresh
                }
            });
    }

    // ============================================
    // Expose to Global Namespace
    // ============================================

    window.BigDump = window.BigDump || {};
    window.BigDump.showHistory = showHistory;
    window.BigDump.closeHistoryModal = closeHistoryModal;
    window.BigDump.clearHistory = clearHistory;

    // Global aliases for onclick handlers in HTML
    window.showHistory = showHistory;
    window.closeHistoryModal = closeHistoryModal;
    window.clearHistory = clearHistory;

})();
