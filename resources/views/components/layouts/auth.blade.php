<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <title>{{ isset($title) ? $title.' - '.config('app.name') : config('app.name') }}</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            background-size: 400% 400%;
            animation: gradientShift 15s ease infinite;
            position: relative;
            overflow: hidden;
        }
        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        #background-canvas {
            position: fixed;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            z-index: 1;
            pointer-events: none;
        }
        .auth-container {
            position: relative;
            z-index: 10;
        }
    </style>
</head>
<body class="min-h-screen font-sans flex items-center justify-center antialiased">
    <canvas id="background-canvas"></canvas>
    <div class="auth-container">
        <x-main full-width>
            <x-slot:content>
                {{ $slot }}
            </x-slot:content>
        </x-main>
    </div>

    <script>
        (function() {
            'use strict';
            
            const backgroundCanvas = document.getElementById('background-canvas');
            const backgroundCtx = backgroundCanvas.getContext('2d');
            
            let animationId;
            let mousePosition = { x: 0, y: 0 };
            let snakeSegments = [];
            let isAnimating = false;
            
            const config = {
                segmentCount: 25,
                segmentDistance: 20,
                maxSize: 12,
                minSize: 3,
                followSpeed: 0.08,
                colorSpeed: 0.02
            };

            function initCanvas() {
                backgroundCanvas.width = window.innerWidth;
                backgroundCanvas.height = window.innerHeight;
                mousePosition = { 
                    x: backgroundCanvas.width / 2, 
                    y: backgroundCanvas.height / 2 
                };
                
                // Initialize snake segments
                snakeSegments = [];
                for (let i = 0; i < config.segmentCount; i++) {
                    snakeSegments.push({
                        x: mousePosition.x,
                        y: mousePosition.y,
                        targetX: mousePosition.x,
                        targetY: mousePosition.y,
                        size: config.maxSize - (i * (config.maxSize - config.minSize) / config.segmentCount),
                        hue: (i * 15) % 360,
                        opacity: Math.max(0.8 - (i * 0.03), 0.1)
                    });
                }
            }

            function updateSnake(timestamp) {
                if (snakeSegments.length === 0) return;
                
                // Update head position
                const head = snakeSegments[0];
                head.targetX = mousePosition.x;
                head.targetY = mousePosition.y;
                head.x += (head.targetX - head.x) * config.followSpeed;
                head.y += (head.targetY - head.y) * config.followSpeed;
                
                // Update body segments
                for (let i = 1; i < snakeSegments.length; i++) {
                    const current = snakeSegments[i];
                    const previous = snakeSegments[i - 1];
                    
                    const dx = previous.x - current.x;
                    const dy = previous.y - current.y;
                    const distance = Math.sqrt(dx * dx + dy * dy);
                    
                    if (distance > config.segmentDistance) {
                        const angle = Math.atan2(dy, dx);
                        current.targetX = previous.x - Math.cos(angle) * config.segmentDistance;
                        current.targetY = previous.y - Math.sin(angle) * config.segmentDistance;
                    }
                    
                    // Smooth movement
                    current.x += (current.targetX - current.x) * 0.15;
                    current.y += (current.targetY - current.y) * 0.15;
                    
                    // Update colors
                    current.hue = (current.hue + config.colorSpeed) % 360;
                }
            }

            function drawSnake(timestamp) {
                backgroundCtx.clearRect(0, 0, backgroundCanvas.width, backgroundCanvas.height);
                
                // Draw connections first
                backgroundCtx.lineCap = 'round';
                for (let i = 0; i < snakeSegments.length - 1; i++) {
                    const current = snakeSegments[i];
                    const next = snakeSegments[i + 1];
                    
                    const gradient = backgroundCtx.createLinearGradient(
                        current.x, current.y, next.x, next.y
                    );
                    gradient.addColorStop(0, `hsla(${180 + current.hue * 0.5}, 70%, 60%, ${current.opacity * 0.4})`);
                    gradient.addColorStop(1, `hsla(${180 + next.hue * 0.5}, 70%, 60%, ${next.opacity * 0.4})`);
                    
                    backgroundCtx.beginPath();
                    backgroundCtx.moveTo(current.x, current.y);
                    backgroundCtx.lineTo(next.x, next.y);
                    backgroundCtx.strokeStyle = gradient;
                    backgroundCtx.lineWidth = Math.max(current.size * 0.6, 2);
                    backgroundCtx.stroke();
                }
                
                // Draw segments
                for (let i = snakeSegments.length - 1; i >= 0; i--) {
                    const segment = snakeSegments[i];
                    
                    // Main circle
                    const mainGradient = backgroundCtx.createRadialGradient(
                        segment.x, segment.y, 0,
                        segment.x, segment.y, segment.size
                    );
                    mainGradient.addColorStop(0, `hsla(${120 + segment.hue * 0.3}, 80%, 70%, ${segment.opacity})`);
                    mainGradient.addColorStop(0.7, `hsla(${120 + segment.hue * 0.3}, 70%, 50%, ${segment.opacity * 0.8})`);
                    mainGradient.addColorStop(1, `hsla(${120 + segment.hue * 0.3}, 60%, 30%, ${segment.opacity * 0.3})`);
                    
                    backgroundCtx.beginPath();
                    backgroundCtx.arc(segment.x, segment.y, segment.size, 0, Math.PI * 2);
                    backgroundCtx.fillStyle = mainGradient;
                    backgroundCtx.fill();
                    
                    // Glow effect
                    backgroundCtx.beginPath();
                    backgroundCtx.arc(segment.x, segment.y, segment.size * 1.5, 0, Math.PI * 2);
                    backgroundCtx.fillStyle = `hsla(${120 + segment.hue * 0.3}, 80%, 60%, ${segment.opacity * 0.1})`;
                    backgroundCtx.fill();
                }
            }

            function animate(timestamp) {
                if (!isAnimating) return;
                
                updateSnake(timestamp);
                drawSnake(timestamp);
                animationId = requestAnimationFrame(animate);
            }

            function handleMouseMove(event) {
                mousePosition.x = event.clientX;
                mousePosition.y = event.clientY;
            }

            function handleResize() {
                initCanvas();
            }

            function startAnimation() {
                if (isAnimating) return;
                isAnimating = true;
                animate();
            }

            function stopAnimation() {
                isAnimating = false;
                if (animationId) {
                    cancelAnimationFrame(animationId);
                }
            }

            // Initialize
            initCanvas();
            
            // Event listeners
            document.addEventListener('mousemove', handleMouseMove);
            window.addEventListener('resize', handleResize);
            
            // Start animation
            startAnimation();
            
            // Cleanup function for Livewire compatibility
            window.addEventListener('beforeunload', stopAnimation);
            
            // Handle Livewire navigation
            document.addEventListener('livewire:navigating', stopAnimation);
            document.addEventListener('livewire:navigated', () => {
                setTimeout(() => {
                    const newCanvas = document.getElementById('background-canvas');
                    if (newCanvas) {
                        initCanvas();
                        startAnimation();
                    }
                }, 100);
            });
            
        })();
    </script>
</body>
</html>