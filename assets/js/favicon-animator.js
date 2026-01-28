/**
 * BigDump - Animated Favicon Module
 * Creates a bouncing arrow animation during import process.
 * Uses Canvas API to dynamically generate favicon frames.
 */
(function() {
    'use strict';

    var canvas = null;
    var ctx = null;
    var animationId = null;
    var originalFavicon = null;
    var linkElement = null;
    var frame = 0;
    var isAnimating = false;

    // Animation settings
    var SIZE = 32;           // Favicon size
    var ARROW_COLOR = '#3B82F6';  // Blue-500
    var BG_COLOR = '#1E3A5F';     // Dark blue background
    var BOUNCE_HEIGHT = 6;   // Pixels to bounce
    var FRAME_RATE = 60;     // ms between frames

    /**
     * Initialize canvas and get original favicon
     */
    function init() {
        if (canvas) return;

        canvas = document.createElement('canvas');
        canvas.width = SIZE;
        canvas.height = SIZE;
        ctx = canvas.getContext('2d');

        // Find and store original favicon
        linkElement = document.querySelector('link[rel="icon"][type="image/png"]') ||
                      document.querySelector('link[rel="icon"]');

        if (linkElement) {
            originalFavicon = linkElement.href;
        }
    }

    /**
     * Draw a frame of the bouncing arrow animation
     * @param {number} offset - Vertical bounce offset
     */
    function drawFrame(offset) {
        // Clear canvas
        ctx.clearRect(0, 0, SIZE, SIZE);

        // Draw circular background
        ctx.beginPath();
        ctx.arc(SIZE / 2, SIZE / 2, SIZE / 2 - 1, 0, Math.PI * 2);
        ctx.fillStyle = BG_COLOR;
        ctx.fill();

        // Calculate arrow position with bounce
        var centerX = SIZE / 2;
        var centerY = SIZE / 2 + offset;

        // Draw downward arrow
        ctx.beginPath();
        ctx.strokeStyle = ARROW_COLOR;
        ctx.fillStyle = ARROW_COLOR;
        ctx.lineWidth = 3;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';

        // Arrow shaft
        ctx.moveTo(centerX, centerY - 8);
        ctx.lineTo(centerX, centerY + 4);
        ctx.stroke();

        // Arrow head (triangle)
        ctx.beginPath();
        ctx.moveTo(centerX - 6, centerY);
        ctx.lineTo(centerX, centerY + 8);
        ctx.lineTo(centerX + 6, centerY);
        ctx.closePath();
        ctx.fill();

        // Update favicon
        if (linkElement) {
            linkElement.href = canvas.toDataURL('image/png');
        }
    }

    /**
     * Animation loop using eased bounce
     */
    function animate() {
        if (!isAnimating) return;

        // Eased bounce: sin wave for smooth up/down motion
        var bounce = Math.sin(frame * 0.15) * BOUNCE_HEIGHT;
        drawFrame(bounce);

        frame++;
        animationId = setTimeout(animate, FRAME_RATE);
    }

    /**
     * Start the favicon animation
     */
    function start() {
        if (isAnimating) return;

        init();
        isAnimating = true;
        frame = 0;
        animate();
        console.log('Favicon animation started');
    }

    /**
     * Stop the animation and restore original favicon
     */
    function stop() {
        if (!isAnimating) return;

        isAnimating = false;

        if (animationId) {
            clearTimeout(animationId);
            animationId = null;
        }

        // Restore original favicon
        if (linkElement && originalFavicon) {
            linkElement.href = originalFavicon;
        }

        console.log('Favicon animation stopped');
    }

    // Expose API
    window.BigDump = window.BigDump || {};
    window.BigDump.faviconAnimator = {
        start: start,
        stop: stop,
        isAnimating: function() { return isAnimating; }
    };

})();
