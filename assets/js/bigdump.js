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

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initLoadingOverlay);
    } else {
        initLoadingOverlay();
    }
})();
