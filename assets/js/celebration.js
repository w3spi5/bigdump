/**
 * BigDump - Celebration Effects Module
 * Creates fireworks and confetti animations on import completion.
 * Uses Canvas API for particle-based visual effects.
 */
(function() {
    'use strict';

    var canvas = null;
    var ctx = null;
    var animationId = null;
    var particles = [];
    var confetti = [];
    var isRunning = false;
    var startTime = 0;
    var duration = 4000; // 4 seconds of celebration

    // Colors for fireworks and confetti
    var fireworkColors = [
        '#FF6B6B', '#4ECDC4', '#45B7D1', '#96CEB4', '#FFEAA7',
        '#DDA0DD', '#98D8C8', '#F7DC6F', '#BB8FCE', '#85C1E9'
    ];

    var confettiColors = [
        '#FF6B6B', '#4ECDC4', '#45B7D1', '#FFD93D', '#6BCB77',
        '#FF8C00', '#DA70D6', '#20B2AA', '#FF69B4', '#87CEEB'
    ];

    /**
     * Particle class for fireworks
     */
    function Particle(x, y, color, velocity, gravity, decay, size) {
        this.x = x;
        this.y = y;
        this.color = color;
        this.velocity = velocity;
        this.gravity = gravity || 0.05;
        this.decay = decay || 0.015;
        this.alpha = 1;
        this.size = size || 3;
    }

    Particle.prototype.update = function() {
        this.velocity.x *= 0.99;
        this.velocity.y *= 0.99;
        this.velocity.y += this.gravity;
        this.x += this.velocity.x;
        this.y += this.velocity.y;
        this.alpha -= this.decay;
        return this.alpha > 0;
    };

    Particle.prototype.draw = function(ctx) {
        ctx.save();
        ctx.globalAlpha = this.alpha;
        ctx.beginPath();
        ctx.arc(this.x, this.y, this.size, 0, Math.PI * 2);
        ctx.fillStyle = this.color;
        ctx.fill();
        ctx.restore();
    };

    /**
     * Confetti piece class
     */
    function ConfettiPiece(x, y, color) {
        this.x = x;
        this.y = y;
        this.color = color;
        this.width = Math.random() * 10 + 5;
        this.height = Math.random() * 6 + 3;
        this.velocity = {
            x: (Math.random() - 0.5) * 6,
            y: Math.random() * 3 + 2
        };
        this.rotation = Math.random() * 360;
        this.rotationSpeed = (Math.random() - 0.5) * 10;
        this.oscillationSpeed = Math.random() * 0.1 + 0.05;
        this.oscillationDistance = Math.random() * 40 + 20;
        this.initialX = x;
        this.time = Math.random() * 100;
    }

    ConfettiPiece.prototype.update = function(canvasHeight) {
        this.time += this.oscillationSpeed;
        this.x = this.initialX + Math.sin(this.time) * this.oscillationDistance;
        this.y += this.velocity.y;
        this.rotation += this.rotationSpeed;
        this.velocity.y += 0.02; // Light gravity
        return this.y < canvasHeight + 50;
    };

    ConfettiPiece.prototype.draw = function(ctx) {
        ctx.save();
        ctx.translate(this.x, this.y);
        ctx.rotate(this.rotation * Math.PI / 180);
        ctx.fillStyle = this.color;
        ctx.fillRect(-this.width / 2, -this.height / 2, this.width, this.height);
        ctx.restore();
    };

    /**
     * Create a firework explosion at position
     */
    function createFirework(x, y) {
        var color = fireworkColors[Math.floor(Math.random() * fireworkColors.length)];
        var particleCount = 50 + Math.floor(Math.random() * 30);

        for (var i = 0; i < particleCount; i++) {
            var angle = (Math.PI * 2 / particleCount) * i;
            var speed = Math.random() * 5 + 3;
            var velocity = {
                x: Math.cos(angle) * speed,
                y: Math.sin(angle) * speed
            };
            particles.push(new Particle(x, y, color, velocity, 0.04, 0.012, Math.random() * 3 + 1));
        }

        // Add some random sparkle particles
        for (var j = 0; j < 20; j++) {
            var sparkAngle = Math.random() * Math.PI * 2;
            var sparkSpeed = Math.random() * 8 + 2;
            var sparkVelocity = {
                x: Math.cos(sparkAngle) * sparkSpeed,
                y: Math.sin(sparkAngle) * sparkSpeed
            };
            particles.push(new Particle(x, y, '#FFFFFF', sparkVelocity, 0.06, 0.025, 2));
        }
    }

    /**
     * Create confetti burst
     */
    function createConfettiBurst(count) {
        if (!canvas) return;

        for (var i = 0; i < count; i++) {
            var x = Math.random() * canvas.width;
            var color = confettiColors[Math.floor(Math.random() * confettiColors.length)];
            confetti.push(new ConfettiPiece(x, -20, color));
        }
    }

    /**
     * Initialize canvas
     */
    function init() {
        if (canvas) return;

        canvas = document.createElement('canvas');
        canvas.id = 'celebration-canvas';
        canvas.style.cssText = 'position: fixed; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none; z-index: 9999;';
        document.body.appendChild(canvas);

        ctx = canvas.getContext('2d');
        resizeCanvas();

        window.addEventListener('resize', resizeCanvas);
    }

    /**
     * Resize canvas to match window
     */
    function resizeCanvas() {
        if (!canvas) return;
        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;
    }

    /**
     * Animation loop
     */
    function animate() {
        if (!isRunning) return;

        var elapsed = Date.now() - startTime;

        // Clear canvas
        ctx.clearRect(0, 0, canvas.width, canvas.height);

        // Update and draw particles
        particles = particles.filter(function(p) {
            var alive = p.update();
            if (alive) p.draw(ctx);
            return alive;
        });

        // Update and draw confetti
        confetti = confetti.filter(function(c) {
            var alive = c.update(canvas.height);
            if (alive) c.draw(ctx);
            return alive;
        });

        // Create new fireworks periodically during first 2 seconds
        if (elapsed < 2000 && Math.random() < 0.08) {
            var x = Math.random() * canvas.width * 0.6 + canvas.width * 0.2;
            var y = Math.random() * canvas.height * 0.4 + canvas.height * 0.1;
            createFirework(x, y);
        }

        // Add confetti continuously during first 3 seconds
        if (elapsed < 3000 && Math.random() < 0.3) {
            createConfettiBurst(3);
        }

        // Continue animation until all particles are gone or duration exceeded
        if (elapsed < duration || particles.length > 0 || confetti.length > 0) {
            animationId = requestAnimationFrame(animate);
        } else {
            cleanup();
        }
    }

    /**
     * Clean up canvas and reset state
     */
    function cleanup() {
        isRunning = false;
        if (animationId) {
            cancelAnimationFrame(animationId);
            animationId = null;
        }
        if (canvas && canvas.parentNode) {
            canvas.parentNode.removeChild(canvas);
        }
        canvas = null;
        ctx = null;
        particles = [];
        confetti = [];
        window.removeEventListener('resize', resizeCanvas);
    }

    /**
     * Start the celebration effects
     */
    function start() {
        if (isRunning) return;

        init();
        isRunning = true;
        startTime = Date.now();
        particles = [];
        confetti = [];

        // Initial burst of fireworks
        for (var i = 0; i < 5; i++) {
            setTimeout(function() {
                if (!isRunning) return;
                var x = Math.random() * canvas.width * 0.6 + canvas.width * 0.2;
                var y = Math.random() * canvas.height * 0.4 + canvas.height * 0.15;
                createFirework(x, y);
            }, i * 200);
        }

        // Initial confetti burst
        createConfettiBurst(100);

        animate();
        console.log('Celebration started!');
    }

    /**
     * Stop the celebration effects immediately
     */
    function stop() {
        if (!isRunning) return;
        cleanup();
        console.log('Celebration stopped');
    }

    // Expose API
    window.BigDump = window.BigDump || {};
    window.BigDump.celebration = {
        start: start,
        stop: stop,
        isRunning: function() { return isRunning; }
    };

})();
