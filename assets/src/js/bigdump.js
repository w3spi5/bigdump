/**
 * BigDump - Main JavaScript
 *
 * Common functionality shared across pages.
 */

(function() {
    'use strict';

    /**
     * Initialize loading overlay for import forms
     */
    function initLoadingOverlay() {
        var overlay = document.getElementById('loadingOverlay');
        if (!overlay) return;

        document.querySelectorAll('form').forEach(function(form) {
            // Only forms with hidden 'fn' input are import forms
            if (form.querySelector('input[name="fn"]')) {
                form.addEventListener('submit', function(e) {
                    overlay.classList.add('active');

                    // Update text based on filename
                    var fnInput = form.querySelector('input[name="fn"]');
                    if (fnInput) {
                        var filename = fnInput.value;
                        var subtext = overlay.querySelector('.loading-subtext');
                        if (subtext) {
                            subtext.textContent = 'Loading ' + filename;
                        }
                    }
                });
            }
        });
    }

    /**
     * Disable a button with loading state to prevent double-click
     * @param {HTMLElement} button - The button element
     * @param {string} loadingText - Text to show while loading
     */
    function disableButtonWithLoading(button, loadingText) {
        button.disabled = true;
        button.classList.add('opacity-50', 'cursor-not-allowed');
        button.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1"></i>' + loadingText;
    }

    /**
     * Initialize button disable behavior for import and delete forms
     * Prevents double-click by disabling buttons after first click
     */
    function initButtonDisable() {
        // Handle import forms (forms with 'fn' input but no 'action' input)
        document.querySelectorAll('form').forEach(function(form) {
            var fnInput = form.querySelector('input[name="fn"]');
            var actionInput = form.querySelector('input[name="action"]');
            var submitBtn = form.querySelector('button[type="submit"]');

            if (!submitBtn) return;

            // Import forms: have 'fn' but no 'action=delete'
            if (fnInput && (!actionInput || actionInput.value !== 'delete')) {
                form.addEventListener('submit', function() {
                    disableButtonWithLoading(submitBtn, 'Importing...');
                });
            }

            // Delete forms: have 'action=delete'
            if (actionInput && actionInput.value === 'delete') {
                form.addEventListener('submit', function(e) {
                    // The confirm is already handled by onsubmit in HTML
                    // If we reach here, user confirmed, so disable button
                    disableButtonWithLoading(submitBtn, 'Deleting...');
                });
            }
        });
    }

    /**
     * Initialize dark mode toggle
     */
    function initDarkModeToggle() {
        var toggle = document.getElementById('darkModeToggle');
        if (!toggle) return;

        toggle.addEventListener('click', function() {
            var html = document.documentElement;
            var current = html.getAttribute('data-theme') || 'light';
            var next = current === 'dark' ? 'light' : 'dark';
            html.setAttribute('data-theme', next);
            localStorage.setItem('bigdump-theme', next);
        });
    }

    /**
     * Initialize all components
     */
    function init() {
        initLoadingOverlay();
        initDarkModeToggle();
        initButtonDisable();
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
