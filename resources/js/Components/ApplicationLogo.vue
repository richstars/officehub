<template>
    <svg viewBox="0 0 500 150" xmlns="http://www.w3.org/2000/svg" class="w-auto h-12">
        <!-- Icon Part -->
        <g transform="translate(10, 10) scale(0.8)">
            <rect x="20" y="20" width="100" height="100" rx="20" fill="url(#grad1)" />
            <path d="M50 70 L70 50 L90 70 M70 50 V90" stroke="white" stroke-width="8" stroke-linecap="round" stroke-linejoin="round"/>
            <circle cx="100" cy="40" r="10" fill="#4ade80" />
        </g>
        
        <!-- Text Part -->
        <text x="140" y="95" font-family="'Inter', sans-serif" font-weight="800" font-size="60" fill="#1e40af">OFFICE</text>
        <text x="360" y="95" font-family="'Inter', sans-serif" font-weight="300" font-size="60" fill="#3b82f6">HUB</text>
        
        <!-- Definitions -->
        <defs>
            <linearGradient id="grad1" x1="0%" y1="0%" x2="100%" y2="100%">
                <stop offset="0%" style="stop-color:#3b82f6;stop-opacity:1" />
                <stop offset="100%" style="stop-color:#1e40af;stop-opacity:1" />
            </linearGradient>
        </defs>
    </svg>
</template>
