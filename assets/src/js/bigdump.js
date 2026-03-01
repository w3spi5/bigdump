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
     * Disable a button and swap its icon to a spinner
     * @param {HTMLElement} button - The button element
     */
    function disableButtonWithSpinner(button) {
        button.disabled = true;
        button.classList.add('opacity-50', 'cursor-not-allowed');
        // Find the swappable icon and change it to spinner
        var iconSwap = button.querySelector('.btn-icon-swap');
        if (iconSwap) {
            iconSwap.innerHTML = '<use href="assets/icons2.svg#spinner"></use>';
            iconSwap.classList.add('animate-spin');
        }
    }

    /**
     * Initialize button disable behavior for import and delete forms
     * Prevents double-click by disabling buttons after first click
     */
    function initButtonDisable() {
        // Handle forms with swappable icons (new style)
        document.querySelectorAll('form').forEach(function(form) {
            var submitBtn = form.querySelector('button[type="submit"]');
            if (!submitBtn) return;

            // Only add handler if button has swappable icon
            var iconSwap = submitBtn.querySelector('.btn-icon-swap');
            if (iconSwap) {
                form.addEventListener('submit', function() {
                    disableButtonWithSpinner(submitBtn);
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
