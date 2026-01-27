/**
 * BigDump - Celebration Effects Module (Enhanced)
 * Creates realistic fireworks and confetti animations on import completion.
 * Features: particle trails, glow effects, 3D confetti rotation, air resistance.
 */
(function() {
    'use strict';

    var canvas = null;
    var ctx = null;
    var animationId = null;
    var particles = [];
    var confetti = [];
    var trails = [];
    var isRunning = false;
    var startTime = 0;
    var fireworksDuration = 20000; // 20 seconds of fireworks
    var confettiForever = true; // Confetti falls indefinitely

    // Vibrant colors for fireworks
    var fireworkPalettes = [
        ['#FF6B6B', '#FF8E8E', '#FFB4B4'], // Red
        ['#4ECDC4', '#7EDCD6', '#A8EBE7'], // Teal
        ['#45B7D1', '#6FC9DE', '#99DBEB'], // Blue
        ['#FFD93D', '#FFE469', '#FFEF9A'], // Yellow
        ['#6BCB77', '#8DD896', '#AFE5B5'], // Green
        ['#FF8C00', '#FFA533', '#FFBE66'], // Orange
        ['#DA70D6', '#E494E0', '#EEB8EA'], // Orchid
        ['#9B59B6', '#B07CC6', '#C59FD6'], // Purple
        ['#E74C3C', '#ED7669', '#F3A096'], // Coral
        ['#3498DB', '#5DADE2', '#85C1E9']  // Sky blue
    ];

    var confettiColors = [
        '#FF6B6B', '#4ECDC4', '#45B7D1', '#FFD93D', '#6BCB77',
        '#FF8C00', '#DA70D6', '#9B59B6', '#E74C3C', '#3498DB',
        '#F39C12', '#1ABC9C', '#E91E63', '#00BCD4', '#8BC34A'
    ];

    /**
     * Enhanced Particle class with trails and glow
     */
    function Particle(x, y, color, velocity, options) {
        options = options || {};
        this.x = x;
        this.y = y;
        this.color = color;
        this.velocity = velocity;
        this.gravity = options.gravity || 0.06;
        this.decay = options.decay || 0.015;
        this.alpha = 1;
        this.size = options.size || 3;
        this.hasTrail = options.hasTrail !== false;
        this.glowSize = options.glowSize || this.size * 2;
        this.friction = options.friction || 0.98;
        this.trail = [];
        this.maxTrailLength = options.trailLength || 5;
    }

    Particle.prototype.update = function() {
        // Store trail position
        if (this.hasTrail && this.alpha > 0.3) {
            this.trail.push({ x: this.x, y: this.y, alpha: this.alpha });
            if (this.trail.length > this.maxTrailLength) {
                this.trail.shift();
            }
        }

        this.velocity.x *= this.friction;
        this.velocity.y *= this.friction;
        this.velocity.y += this.gravity;
        this.x += this.velocity.x;
        this.y += this.velocity.y;
        this.alpha -= this.decay;
        return this.alpha > 0;
    };

    Particle.prototype.draw = function(ctx) {
        // Draw trail
        if (this.hasTrail && this.trail.length > 0) {
            for (var i = 0; i < this.trail.length; i++) {
                var t = this.trail[i];
                var trailAlpha = (i / this.trail.length) * this.alpha * 0.5;
                var trailSize = this.size * (0.3 + (i / this.trail.length) * 0.5);
                ctx.save();
                ctx.globalAlpha = trailAlpha;
                ctx.beginPath();
                ctx.arc(t.x, t.y, trailSize, 0, Math.PI * 2);
                ctx.fillStyle = this.color;
                ctx.fill();
                ctx.restore();
            }
        }

        // Draw glow
        ctx.save();
        ctx.globalAlpha = this.alpha * 0.4;
        ctx.beginPath();
        ctx.arc(this.x, this.y, this.glowSize, 0, Math.PI * 2);
        var gradient = ctx.createRadialGradient(this.x, this.y, 0, this.x, this.y, this.glowSize);
        gradient.addColorStop(0, this.color);
        gradient.addColorStop(1, 'transparent');
        ctx.fillStyle = gradient;
        ctx.fill();
        ctx.restore();

        // Draw core
        ctx.save();
        ctx.globalAlpha = this.alpha;
        ctx.beginPath();
        ctx.arc(this.x, this.y, this.size, 0, Math.PI * 2);
        ctx.fillStyle = this.color;
        ctx.fill();

        // Bright center
        ctx.beginPath();
        ctx.arc(this.x, this.y, this.size * 0.5, 0, Math.PI * 2);
        ctx.fillStyle = '#FFFFFF';
        ctx.globalAlpha = this.alpha * 0.8;
        ctx.fill();
        ctx.restore();
    };

    /**
     * Enhanced Confetti with 3D rotation and air resistance
     */
    function ConfettiPiece(x, y, color) {
        this.x = x;
        this.y = y;
        this.color = color;
        this.width = Math.random() * 12 + 6;
        this.height = Math.random() * 8 + 4;
        this.velocity = {
            x: (Math.random() - 0.5) * 8,
            y: Math.random() * 2 + 1
        };
        // 3D rotation angles
        this.rotationX = Math.random() * 360;
        this.rotationY = Math.random() * 360;
        this.rotationZ = Math.random() * 360;
        this.rotationSpeedX = (Math.random() - 0.5) * 8;
        this.rotationSpeedY = (Math.random() - 0.5) * 12;
        this.rotationSpeedZ = (Math.random() - 0.5) * 6;
        // Oscillation (swaying)
        this.oscillationSpeed = Math.random() * 0.08 + 0.03;
        this.oscillationDistance = Math.random() * 60 + 30;
        this.initialX = x;
        this.time = Math.random() * 100;
        // Air resistance
        this.drag = 0.98 + Math.random() * 0.015;
        this.terminalVelocity = 4 + Math.random() * 2;
    }

    ConfettiPiece.prototype.update = function(canvasHeight) {
        this.time += this.oscillationSpeed;

        // Horizontal oscillation (swaying in wind)
        this.x = this.initialX + Math.sin(this.time) * this.oscillationDistance;
        this.initialX += this.velocity.x * 0.1; // Drift

        // Apply drag
        this.velocity.x *= this.drag;
        this.velocity.y += 0.08; // Gravity

        // Terminal velocity
        if (this.velocity.y > this.terminalVelocity) {
            this.velocity.y = this.terminalVelocity;
        }

        this.y += this.velocity.y;

        // Tumbling rotation
        this.rotationX += this.rotationSpeedX;
        this.rotationY += this.rotationSpeedY;
        this.rotationZ += this.rotationSpeedZ;

        // Slow down rotation over time
        this.rotationSpeedX *= 0.999;
        this.rotationSpeedY *= 0.999;

        return this.y < canvasHeight + 50;
    };

    ConfettiPiece.prototype.draw = function(ctx) {
        ctx.save();
        ctx.translate(this.x, this.y);

        // Apply 3D-like rotation (fake perspective)
        var scaleX = Math.cos(this.rotationY * Math.PI / 180);
        var scaleY = Math.cos(this.rotationX * Math.PI / 180);
        ctx.rotate(this.rotationZ * Math.PI / 180);
        ctx.scale(scaleX, scaleY);

        // Draw confetti piece with slight shadow
        ctx.shadowColor = 'rgba(0,0,0,0.2)';
        ctx.shadowBlur = 2;
        ctx.shadowOffsetY = 1;

        ctx.fillStyle = this.color;
        ctx.fillRect(-this.width / 2, -this.height / 2, this.width, this.height);

        // Add shine effect
        ctx.fillStyle = 'rgba(255,255,255,0.3)';
        ctx.fillRect(-this.width / 2, -this.height / 2, this.width * 0.4, this.height);

        ctx.restore();
    };

    /**
     * Create an enhanced firework explosion
     */
    function createFirework(x, y) {
        var palette = fireworkPalettes[Math.floor(Math.random() * fireworkPalettes.length)];
        var particleCount = 60 + Math.floor(Math.random() * 40);
        var burstSpeed = 4 + Math.random() * 3;

        // Main explosion particles
        for (var i = 0; i < particleCount; i++) {
            var angle = (Math.PI * 2 / particleCount) * i + (Math.random() - 0.5) * 0.2;
            var speed = burstSpeed * (0.6 + Math.random() * 0.8);
            var colorIndex = Math.floor(Math.random() * palette.length);
            var velocity = {
                x: Math.cos(angle) * speed,
                y: Math.sin(angle) * speed
            };
            particles.push(new Particle(x, y, palette[colorIndex], velocity, {
                gravity: 0.05,
                decay: 0.010 + Math.random() * 0.008,
                size: 2 + Math.random() * 2,
                trailLength: 4,
                glowSize: 6
            }));
        }

        // Inner ring (brighter, faster decay)
        var innerCount = 20 + Math.floor(Math.random() * 15);
        for (var j = 0; j < innerCount; j++) {
            var innerAngle = Math.random() * Math.PI * 2;
            var innerSpeed = burstSpeed * 1.3 * Math.random();
            var innerVelocity = {
                x: Math.cos(innerAngle) * innerSpeed,
                y: Math.sin(innerAngle) * innerSpeed
            };
            particles.push(new Particle(x, y, '#FFFFFF', innerVelocity, {
                gravity: 0.04,
                decay: 0.025,
                size: 1.5,
                trailLength: 3,
                glowSize: 4,
                hasTrail: true
            }));
        }

        // Sparkle particles (twinkle effect)
        var sparkleCount = 15 + Math.floor(Math.random() * 10);
        for (var k = 0; k < sparkleCount; k++) {
            var sparkAngle = Math.random() * Math.PI * 2;
            var sparkSpeed = burstSpeed * 0.3 + Math.random() * burstSpeed * 0.8;
            var sparkVelocity = {
                x: Math.cos(sparkAngle) * sparkSpeed,
                y: Math.sin(sparkAngle) * sparkSpeed
            };
            particles.push(new Particle(x, y, '#FFFACD', sparkVelocity, {
                gravity: 0.08,
                decay: 0.03 + Math.random() * 0.02,
                size: 1,
                glowSize: 3,
                hasTrail: false
            }));
        }
    }

    /**
     * Create confetti burst from top
     */
    function createConfettiBurst(count) {
        if (!canvas) return;

        for (var i = 0; i < count; i++) {
            var x = Math.random() * canvas.width;
            var color = confettiColors[Math.floor(Math.random() * confettiColors.length)];
            confetti.push(new ConfettiPiece(x, -20 - Math.random() * 50, color));
        }
    }

    /**
     * Create confetti cannon from sides
     */
    function createConfettiCannon(fromLeft) {
        if (!canvas) return;

        var startX = fromLeft ? -10 : canvas.width + 10;
        var angle = fromLeft ? -Math.PI / 4 : -Math.PI * 3 / 4;

        for (var i = 0; i < 30; i++) {
            var spread = (Math.random() - 0.5) * 0.5;
            var speed = 8 + Math.random() * 6;
            var color = confettiColors[Math.floor(Math.random() * confettiColors.length)];
            var piece = new ConfettiPiece(startX, canvas.height * 0.7, color);
            piece.velocity.x = Math.cos(angle + spread) * speed;
            piece.velocity.y = Math.sin(angle + spread) * speed;
            piece.initialX = startX;
            confetti.push(piece);
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

        // Clear canvas with slight fade for trail effect
        ctx.fillStyle = 'rgba(0, 0, 0, 0.1)';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
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

        // Create new fireworks periodically for 20 seconds
        if (elapsed < fireworksDuration) {
            // More frequent fireworks at start, gradually decreasing
            var fireworkChance = elapsed < 5000 ? 0.08 : (elapsed < 10000 ? 0.05 : 0.03);
            if (Math.random() < fireworkChance) {
                var x = Math.random() * canvas.width * 0.7 + canvas.width * 0.15;
                var y = Math.random() * canvas.height * 0.4 + canvas.height * 0.08;
                createFirework(x, y);
            }
        }

        // Add confetti continuously forever (or until stopped)
        if (confettiForever && Math.random() < 0.15) {
            createConfettiBurst(1);
        }

        // Confetti cannons at specific intervals during first 15 seconds
        if (elapsed < 15000) {
            var cannonInterval = 2000; // Every 2 seconds
            var cannonWindow = 30;
            if (elapsed % cannonInterval < cannonWindow && Math.random() < 0.5) {
                createConfettiCannon(Math.random() < 0.5);
            }
        }

        // Continue animation as long as running (confetti forever!)
        if (isRunning) {
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
        trails = [];
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
        trails = [];

        // Initial burst of fireworks (staggered)
        var fireworkPositions = [
            { x: 0.3, y: 0.25, delay: 0 },
            { x: 0.7, y: 0.2, delay: 150 },
            { x: 0.5, y: 0.15, delay: 300 },
            { x: 0.25, y: 0.3, delay: 450 },
            { x: 0.75, y: 0.28, delay: 600 }
        ];

        fireworkPositions.forEach(function(pos) {
            setTimeout(function() {
                if (!isRunning || !canvas) return;
                createFirework(canvas.width * pos.x, canvas.height * pos.y);
            }, pos.delay);
        });

        // Initial confetti burst
        createConfettiBurst(80);

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
