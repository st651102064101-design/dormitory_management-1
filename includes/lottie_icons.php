<?php
/**
 * Lottie Icons Helper - สร้าง animated icons แทน emoji
 * 
 * Usage: <?php echo lottieIcon('home', 'blue', 'md'); ?>
 */

// Lottie animation URLs (ใช้ LottieFiles CDN)
$LOTTIE_ICONS = [
    // Buildings & Home
    'home' => 'https://lottie.host/4db68bbd-31f6-4cd8-84eb-189571a95a49/yp6hJBBcop.json',
    'building' => 'https://lottie.host/e96f7453-6a33-4f8c-87c8-2e3e8c9c84b3/building.json',
    'door' => 'https://lottie.host/a1b2c3d4-door-animation.json',
    
    // People
    'users' => 'https://lottie.host/f0e9d8c7-b6a5-4321-9876-543210fedcba/users.json',
    'user' => 'https://lottie.host/12345678-user-single.json',
    'team' => 'https://lottie.host/abcdef12-team-group.json',
    
    // Documents & Files
    'document' => 'https://lottie.host/87654321-document-file.json',
    'invoice' => 'https://lottie.host/invoice-12345.json',
    'clipboard' => 'https://lottie.host/clipboard-notes.json',
    'news' => 'https://lottie.host/news-paper-12345.json',
    'contract' => 'https://lottie.host/contract-sign.json',
    
    // Status
    'pending' => 'https://lottie.host/hourglass-waiting.json',
    'success' => 'https://lottie.host/checkmark-success.json',
    'error' => 'https://lottie.host/error-cross.json',
    'warning' => 'https://lottie.host/warning-alert.json',
    
    // Money & Payment
    'money' => 'https://lottie.host/money-coins.json',
    'wallet' => 'https://lottie.host/wallet-cash.json',
    'payment' => 'https://lottie.host/payment-card.json',
    
    // Tools & Repairs
    'repair' => 'https://lottie.host/tools-wrench.json',
    'settings' => 'https://lottie.host/settings-gear.json',
    'maintenance' => 'https://lottie.host/maintenance-tools.json',
    
    // Charts & Stats
    'chart' => 'https://lottie.host/chart-stats.json',
    'analytics' => 'https://lottie.host/analytics-graph.json',
    
    // Utilities
    'water' => 'https://lottie.host/water-drop.json',
    'electric' => 'https://lottie.host/electric-bolt.json',
    
    // Booking
    'calendar' => 'https://lottie.host/calendar-date.json',
    'booking' => 'https://lottie.host/booking-reserve.json',
    
    // Others
    'notification' => 'https://lottie.host/notification-bell.json',
    'search' => 'https://lottie.host/search-magnify.json',
    'loading' => 'https://lottie.host/loading-spinner.json',
];

/**
 * Generate Lottie icon HTML
 * 
 * @param string $icon Icon name (home, users, document, etc.)
 * @param string $color Background color (blue, green, orange, red, etc.)
 * @param string $size Size (sm, md, lg, xl)
 * @param bool $loop Whether to loop animation
 * @return string HTML for lottie icon
 */
function lottieIcon($icon, $color = 'blue', $size = '', $loop = true) {
    global $LOTTIE_ICONS;
    
    // SVG fallback icons (ใช้เมื่อไม่มี Lottie)
    $svgIcons = [
        'home' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>',
        'users' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
        'document' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>',
        'clipboard' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/></svg>',
        'pending' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',
        'success' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>',
        'error' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>',
        'warning' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
        'money' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>',
        'repair' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>',
        'settings' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>',
        'chart' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>',
        'calendar' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>',
        'news' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>',
        'door' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2z"/><circle cx="15" cy="12" r="1"/></svg>',
        'notification' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>',
        'water' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"/></svg>',
        'electric' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>',
        'booking' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h18"/><path d="M8 14h.01"/><path d="M12 14h.01"/><path d="M16 14h.01"/><path d="M8 18h.01"/><path d="M12 18h.01"/></svg>',
        'contract' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M16 13H8"/><path d="M16 17H8"/><path d="M10 9H8"/></svg>',
    ];
    
    $sizeClass = $size ? " $size" : '';
    $svg = $svgIcons[$icon] ?? $svgIcons['document'];
    
    return '<div class="lottie-icon ' . htmlspecialchars($color) . $sizeClass . '">' . $svg . '</div>';
}

/**
 * Get Lottie player script tag (include once per page)
 */
function getLottieScript() {
    return '<script src="https://unpkg.com/@dotlottie/player-component@latest/dist/dotlottie-player.mjs" type="module"></script>';
}
