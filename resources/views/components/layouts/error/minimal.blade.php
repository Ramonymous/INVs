<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($title) ? $title.' - '.config('app.name') : config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    
    <style>
        /* Animated Background Styles */
        .error-bg {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            overflow: hidden;
        }

        /* Floating geometric shapes */
        .floating-shape {
            position: absolute;
            opacity: 0.1;
            animation: float 6s ease-in-out infinite;
        }

        .shape-1 {
            width: 80px;
            height: 80px;
            background: linear-gradient(45deg, #ff6b6b, #ee5a24);
            border-radius: 50%;
            top: 20%;
            left: 10%;
            animation-delay: 0s;
        }

        .shape-2 {
            width: 60px;
            height: 60px;
            background: linear-gradient(45deg, #ff9ff3, #f368e0);
            border-radius: 10px;
            top: 60%;
            right: 15%;
            animation-delay: -2s;
        }

        .shape-3 {
            width: 100px;
            height: 100px;
            background: linear-gradient(45deg, #ff7675, #d63031);
            clip-path: polygon(50% 0%, 0% 100%, 100% 100%);
            bottom: 30%;
            left: 20%;
            animation-delay: -4s;
        }

        .shape-4 {
            width: 70px;
            height: 70px;
            background: linear-gradient(45deg, #fd79a8, #e84393);
            border-radius: 50%;
            top: 40%;
            right: 30%;
            animation-delay: -1s;
        }

        .shape-5 {
            width: 90px;
            height: 90px;
            background: linear-gradient(45deg, #ff4757, #c44569);
            border-radius: 15px;
            bottom: 60%;
            right: 10%;
            animation-delay: -3s;
        }

        /* Glitch lines effect */
        .glitch-lines {
            position: absolute;
            width: 100%;
            height: 100%;
        }

        .glitch-line {
            position: absolute;
            width: 2px;
            height: 100%;
            background: linear-gradient(to bottom, transparent, #ff6b6b, transparent);
            opacity: 0.3;
            animation: glitch 4s linear infinite;
        }

        .glitch-line:nth-child(1) {
            left: 15%;
            animation-delay: 0s;
        }

        .glitch-line:nth-child(2) {
            left: 35%;
            animation-delay: -1s;
        }

        .glitch-line:nth-child(3) {
            left: 65%;
            animation-delay: -2s;
        }

        .glitch-line:nth-child(4) {
            right: 20%;
            animation-delay: -1.5s;
        }

        /* Particle effect */
        .particles {
            position: absolute;
            width: 100%;
            height: 100%;
        }

        .particle {
            position: absolute;
            width: 4px;
            height: 4px;
            background: #ff6b6b;
            border-radius: 50%;
            opacity: 0.6;
            animation: particle-float 8s linear infinite;
        }

        .particle:nth-child(odd) {
            background: #ee5a24;
        }

        /* Generate particles with different positions and delays */
        .particle:nth-child(1) { left: 10%; animation-delay: 0s; }
        .particle:nth-child(2) { left: 20%; animation-delay: -2s; }
        .particle:nth-child(3) { left: 30%; animation-delay: -4s; }
        .particle:nth-child(4) { left: 40%; animation-delay: -1s; }
        .particle:nth-child(5) { left: 50%; animation-delay: -3s; }
        .particle:nth-child(6) { left: 60%; animation-delay: -5s; }
        .particle:nth-child(7) { left: 70%; animation-delay: -2.5s; }
        .particle:nth-child(8) { left: 80%; animation-delay: -1.5s; }
        .particle:nth-child(9) { left: 90%; animation-delay: -3.5s; }

        /* Subtle pulse effect for the main card */
        .error-card {
            animation: subtle-pulse 3s ease-in-out infinite;
        }

        /* Keyframe animations */
        @keyframes float {
            0%, 100% {
                transform: translateY(0px) rotate(0deg);
            }
            33% {
                transform: translateY(-20px) rotate(120deg);
            }
            66% {
                transform: translateY(10px) rotate(240deg);
            }
        }

        @keyframes glitch {
            0%, 90% {
                opacity: 0;
                transform: translateX(0);
            }
            91%, 95% {
                opacity: 0.3;
                transform: translateX(-2px);
            }
            96%, 100% {
                opacity: 0.6;
                transform: translateX(2px);
            }
        }

        @keyframes particle-float {
            0% {
                transform: translateY(100vh) scale(0);
                opacity: 0;
            }
            10% {
                opacity: 0.6;
            }
            90% {
                opacity: 0.6;
            }
            100% {
                transform: translateY(-100vh) scale(1);
                opacity: 0;
            }
        }

        @keyframes subtle-pulse {
            0%, 100% {
                transform: scale(1);
                box-shadow: 0 0 0 0 rgba(255, 107, 107, 0.1);
            }
            50% {
                transform: scale(1.02);
                box-shadow: 0 0 0 20px rgba(255, 107, 107, 0);
            }
        }

        /* Responsive adjustments */
        @media (max-width: 640px) {
            .floating-shape {
                opacity: 0.05;
            }
            
            .shape-1, .shape-3, .shape-5 {
                transform: scale(0.7);
            }
            
            .glitch-line {
                opacity: 0.2;
            }
        }

        /* Dark mode compatibility */
        @media (prefers-color-scheme: dark) {
            .floating-shape {
                opacity: 0.15;
            }
            
            .glitch-line {
                background: linear-gradient(to bottom, transparent, #ff4757, transparent);
            }
            
            .particle {
                background: #ff4757;
            }
        }
    </style>
</head>

<body class="bg-base-300 min-h-screen flex items-center justify-center relative">
    <!-- Animated Background -->
    <div class="error-bg">
        <!-- Floating shapes -->
        <div class="floating-shape shape-1"></div>
        <div class="floating-shape shape-2"></div>
        <div class="floating-shape shape-3"></div>
        <div class="floating-shape shape-4"></div>
        <div class="floating-shape shape-5"></div>
        
        <!-- Glitch lines -->
        <div class="glitch-lines">
            <div class="glitch-line"></div>
            <div class="glitch-line"></div>
            <div class="glitch-line"></div>
            <div class="glitch-line"></div>
        </div>
        
        <!-- Floating particles -->
        <div class="particles">
            <div class="particle"></div>
            <div class="particle"></div>
            <div class="particle"></div>
            <div class="particle"></div>
            <div class="particle"></div>
            <div class="particle"></div>
            <div class="particle"></div>
            <div class="particle"></div>
            <div class="particle"></div>
        </div>
    </div>

    <!-- Main content -->
    <div class="flex flex-col items-center justify-center max-w-md w-full relative z-10">
        <x-card class="indicator error-card" :title="$title" :subtitle="$message" shadow separator>
            {{ $slot }}
            <x-badge :value="$code" class="badge-error badge-xl indicator-item" />
            <x-button :label="__('Go home')" :link="route('dashboard')" icon="o-home" class="btn-primary mt-2" />
        </x-card>
    </div>
</body>

</html>