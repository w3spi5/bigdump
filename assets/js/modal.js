/**
 * BigDump - Modal Accessibility Module
 * Handles focus trap, keyboard navigation, and ARIA for modals.
 * WCAG 2.1 AA Compliant
 */
(function() {
    'use strict';

    // Track the element that triggered the modal
    var triggerElements = {};

    // Focusable elements selector
    var FOCUSABLE_SELECTOR = [
        'button:not([disabled])',
        'a[href]',
        'input:not([disabled]):not([type="hidden"])',
        'select:not([disabled])',
        'textarea:not([disabled])',
        '[tabindex]:not([tabindex="-1"])'
    ].join(', ');

    /**
     * Get all focusable elements within a container
     * @param {HTMLElement} container - Container element
     * @returns {HTMLElement[]} Array of focusable elements
     */
    function getFocusableElements(container) {
        var elements = container.querySelectorAll(FOCUSABLE_SELECTOR);
        return Array.prototype.filter.call(elements, function(el) {
            return el.offsetParent !== null; // Only visible elements
        });
    }

    /**
     * Trap focus within a modal
     * @param {KeyboardEvent} event - Keyboard event
     * @param {HTMLElement} modal - Modal container
     */
    function trapFocus(event, modal) {
        if (event.key !== 'Tab') return;

        var focusable = getFocusableElements(modal);
        if (focusable.length === 0) return;

        var firstElement = focusable[0];
        var lastElement = focusable[focusable.length - 1];

        if (event.shiftKey) {
            // Shift + Tab: going backwards
            if (document.activeElement === firstElement) {
                event.preventDefault();
                lastElement.focus();
            }
        } else {
            // Tab: going forwards
            if (document.activeElement === lastElement) {
                event.preventDefault();
                firstElement.focus();
            }
        }
    }

    /**
     * Handle keydown events for modal
     * @param {KeyboardEvent} event - Keyboard event
     * @param {string} modalId - Modal element ID
     * @param {Function} closeFunction - Function to close modal
     */
    function handleModalKeydown(event, modalId, closeFunction) {
        var modal = document.getElementById(modalId);
        if (!modal || modal.classList.contains('hidden')) return;

        // ESC key closes modal
        if (event.key === 'Escape') {
            event.preventDefault();
            closeFunction();
            return;
        }

        // Trap focus within modal
        trapFocus(event, modal);
    }

    /**
     * Open modal with accessibility support
     * @param {string} modalId - Modal overlay element ID
     * @param {HTMLElement} [triggerEl] - Element that triggered the modal
     */
    function openModal(modalId, triggerEl) {
        var overlay = document.getElementById(modalId);
        if (!overlay) return;

        // Store trigger element for focus restoration
        triggerElements[modalId] = triggerEl || document.activeElement;

        // Show modal
        overlay.classList.remove('hidden');

        // Find the dialog element (first child with role="dialog")
        var dialog = overlay.querySelector('[role="dialog"]') || overlay.querySelector('.modal');

        // Focus first focusable element in modal
        setTimeout(function() {
            var focusable = getFocusableElements(dialog || overlay);
            if (focusable.length > 0) {
                focusable[0].focus();
            }
        }, 50);
    }

    /**
     * Close modal with accessibility support
     * @param {string} modalId - Modal overlay element ID
     */
    function closeModal(modalId) {
        var overlay = document.getElementById(modalId);
        if (!overlay) return;

        // Hide modal
        overlay.classList.add('hidden');

        // Restore focus to trigger element
        var triggerEl = triggerElements[modalId];
        if (triggerEl && typeof triggerEl.focus === 'function') {
            setTimeout(function() {
                triggerEl.focus();
            }, 50);
        }

        // Clean up
        delete triggerElements[modalId];
    }

    /**
     * Initialize modal accessibility handlers
     */
    function initModals() {
        // Preview Modal handlers
        document.addEventListener('keydown', function(event) {
            handleModalKeydown(event, 'previewModal', function() {
                if (typeof window.closePreviewModal === 'function') {
                    window.closePreviewModal();
                }
            });
        });

        // History Modal handlers
        document.addEventListener('keydown', function(event) {
            handleModalKeydown(event, 'historyModal', function() {
                if (typeof window.closeHistoryModal === 'function') {
                    window.closeHistoryModal();
                }
            });
        });

        // Wrap existing modal functions to add accessibility
        wrapModalFunctions();
    }

    /**
     * Wrap existing modal open/close functions with accessibility
     */
    function wrapModalFunctions() {
        // Wrap previewFile to track trigger
        var originalPreviewFile = window.previewFile;
        if (typeof originalPreviewFile === 'function') {
            window.previewFile = function(filename) {
                triggerElements['previewModal'] = document.activeElement;
                originalPreviewFile(filename);
                // Focus first element after content loads
                setTimeout(function() {
                    var modal = document.getElementById('previewModal');
                    if (modal && !modal.classList.contains('hidden')) {
                        var dialog = modal.querySelector('[role="dialog"]');
                        var focusable = getFocusableElements(dialog || modal);
                        if (focusable.length > 0) {
                            focusable[0].focus();
                        }
                    }
                }, 100);
            };
            // Preserve reference
            window.BigDump = window.BigDump || {};
            window.BigDump.previewFile = window.previewFile;
        }

        // Wrap closePreviewModal to restore focus
        var originalClosePreview = window.closePreviewModal;
        if (typeof originalClosePreview === 'function') {
            window.closePreviewModal = function(event) {
                if (event && event.target !== event.currentTarget) return;
                var triggerEl = triggerElements['previewModal'];
                originalClosePreview.call(this, event);
                // Restore focus
                if (triggerEl && typeof triggerEl.focus === 'function') {
                    setTimeout(function() { triggerEl.focus(); }, 50);
                }
                delete triggerElements['previewModal'];
            };
            window.BigDump = window.BigDump || {};
            window.BigDump.closePreviewModal = window.closePreviewModal;
        }

        // Wrap showHistory to track trigger
        var originalShowHistory = window.showHistory;
        if (typeof originalShowHistory === 'function') {
            window.showHistory = function() {
                triggerElements['historyModal'] = document.activeElement;
                originalShowHistory();
                // Focus first element after content loads
                setTimeout(function() {
                    var modal = document.getElementById('historyModal');
                    if (modal && !modal.classList.contains('hidden')) {
                        var dialog = modal.querySelector('[role="dialog"]');
                        var focusable = getFocusableElements(dialog || modal);
                        if (focusable.length > 0) {
                            focusable[0].focus();
                        }
                    }
                }, 100);
            };
            window.BigDump = window.BigDump || {};
            window.BigDump.showHistory = window.showHistory;
        }

        // Wrap closeHistoryModal to restore focus
        var originalCloseHistory = window.closeHistoryModal;
        if (typeof originalCloseHistory === 'function') {
            window.closeHistoryModal = function(event) {
                if (event && event.target !== event.currentTarget) return;
                var triggerEl = triggerElements['historyModal'];
                originalCloseHistory.call(this, event);
                // Restore focus
                if (triggerEl && typeof triggerEl.focus === 'function') {
                    setTimeout(function() { triggerEl.focus(); }, 50);
                }
                delete triggerElements['historyModal'];
            };
            window.BigDump = window.BigDump || {};
            window.BigDump.closeHistoryModal = window.closeHistoryModal;
        }
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initModals);
    } else {
        // DOM already loaded, but wait for other scripts
        setTimeout(initModals, 0);
    }

    // Expose utility functions
    window.BigDump = window.BigDump || {};
    window.BigDump.modal = {
        open: openModal,
        close: closeModal,
        getFocusable: getFocusableElements
    };

})();
