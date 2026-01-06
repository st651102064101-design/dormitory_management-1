<!-- Apple-Style Alert Component -->
<!-- ใช้งาน: include_once __DIR__ . '/../includes/apple_alert.php'; -->
<!-- แล้วเรียก: alert('ข้อความ'); หรือ showAppleAlert('ข้อความ', 'หัวข้อ'); -->

<?php
// Get system theme
$appleAlertTheme = 'dark'; // default
try {
    if (isset($pdo) && $pdo instanceof PDO) {
        $themeStmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'public_theme' LIMIT 1");
        $themeRow = $themeStmt->fetch(PDO::FETCH_ASSOC);
        if ($themeRow) {
            $appleAlertTheme = $themeRow['setting_value'];
        }
    }
} catch (Exception $e) {
    // Fallback to dark if error
}

// Get theme color
$appleAlertColor = '#007AFF'; // iOS Blue default
try {
    if (isset($pdo) && $pdo instanceof PDO) {
        $colorStmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'theme_color' LIMIT 1");
        $colorRow = $colorStmt->fetch(PDO::FETCH_ASSOC);
        if ($colorRow && !empty($colorRow['setting_value'])) {
            $appleAlertColor = $colorRow['setting_value'];
        }
    }
} catch (Exception $e) {}
?>

<div id="appleAlert" class="apple-alert-overlay" data-theme="<?php echo htmlspecialchars($appleAlertTheme, ENT_QUOTES, 'UTF-8'); ?>">
    <div class="apple-alert-dialog">
        <div class="apple-alert-content">
            <div class="apple-alert-title" id="appleAlertTitle">แจ้งเตือน</div>
            <div class="apple-alert-message" id="appleAlertMessage"></div>
        </div>
        <div class="apple-alert-actions">
            <button class="apple-alert-btn" onclick="closeAppleAlert()">ตกลง</button>
        </div>
    </div>
</div>

<style>
:root {
    --alert-theme-color: <?php echo htmlspecialchars($appleAlertColor, ENT_QUOTES, 'UTF-8'); ?>;
}

.apple-alert-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.4);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    z-index: 99999;
    align-items: center;
    justify-content: center;
    animation: appleAlertFadeIn 0.2s ease-out;
}

.apple-alert-overlay.show {
    display: flex;
}

/* Animated gradient background for dark mode */
.apple-alert-overlay[data-theme="dark"]::before,
body.auto-dark .apple-alert-overlay[data-theme="auto"]::before {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(
        circle at center,
        var(--alert-theme-color) 0%,
        transparent 60%
    );
    opacity: 0.08;
    animation: gradientRotate 15s linear infinite;
    pointer-events: none;
}

/* Light Theme (Default iOS Style) */
.apple-alert-overlay[data-theme="light"] .apple-alert-dialog {
    background: rgba(255, 255, 255, 0.95);
}

.apple-alert-overlay[data-theme="light"] .apple-alert-title,
.apple-alert-overlay[data-theme="light"] .apple-alert-message {
    color: #000;
}

.apple-alert-overlay[data-theme="light"] .apple-alert-actions {
    border-top: 0.5px solid rgba(0, 0, 0, 0.1);
}

.apple-alert-overlay[data-theme="light"] .apple-alert-btn:hover {
    background: rgba(0, 0, 0, 0.05);
}

.apple-alert-overlay[data-theme="light"] .apple-alert-btn:active {
    background: rgba(0, 0, 0, 0.1);
}

/* Dark Theme - Premium Glass Morphism */
.apple-alert-overlay[data-theme="dark"] .apple-alert-dialog {
    background: linear-gradient(
        135deg,
        rgba(20, 20, 25, 0.98) 0%,
        rgba(30, 30, 35, 0.95) 100%
    );
    border: 1px solid rgba(255, 255, 255, 0.1);
    box-shadow: 
        0 0 40px rgba(0, 0, 0, 0.6),
        0 8px 32px rgba(0, 0, 0, 0.4),
        inset 0 1px 0 rgba(255, 255, 255, 0.05),
        0 0 80px var(--alert-theme-color);
    position: relative;
    overflow: hidden;
}

/* Animated glow border for dark theme */
.apple-alert-overlay[data-theme="dark"] .apple-alert-dialog::before {
    content: '';
    position: absolute;
    top: -2px;
    left: -2px;
    right: -2px;
    bottom: -2px;
    background: linear-gradient(
        45deg,
        var(--alert-theme-color),
        transparent,
        var(--alert-theme-color)
    );
    border-radius: 16px;
    opacity: 0.3;
    z-index: -1;
    animation: borderGlow 3s ease-in-out infinite;
}

/* Shimmer effect */
.apple-alert-overlay[data-theme="dark"] .apple-alert-dialog::after {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: linear-gradient(
        45deg,
        transparent 30%,
        rgba(255, 255, 255, 0.03) 50%,
        transparent 70%
    );
    animation: shimmer 3s linear infinite;
    pointer-events: none;
}

.apple-alert-overlay[data-theme="dark"] .apple-alert-title,
.apple-alert-overlay[data-theme="dark"] .apple-alert-message {
    color: #fff;
    text-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
}

.apple-alert-overlay[data-theme="dark"] .apple-alert-title {
    background: linear-gradient(
        135deg,
        #ffffff 0%,
        rgba(255, 255, 255, 0.9) 100%
    );
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.apple-alert-overlay[data-theme="dark"] .apple-alert-actions {
    border-top: 1px solid rgba(255, 255, 255, 0.15);
    position: relative;
    background: linear-gradient(
        to bottom,
        transparent,
        rgba(96, 165, 250, 0.05)
    );
}

.apple-alert-overlay[data-theme="dark"] .apple-alert-btn {
    position: relative;
    font-weight: 600;
    font-size: 18px;
    text-shadow: 0 0 20px var(--alert-theme-color);
    color: #60a5fa !important; /* Bright blue for dark mode */
    z-index: 3;
}

.apple-alert-overlay[data-theme="dark"] .apple-alert-btn::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 0;
    height: 0;
    border-radius: 50%;
    background: radial-gradient(
        circle,
        #60a5fa 0%,
        transparent 70%
    );
    opacity: 0;
    transform: translate(-50%, -50%);
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    z-index: -1;
}

.apple-alert-overlay[data-theme="dark"] .apple-alert-btn:hover::before {
    width: 200px;
    height: 200px;
    opacity: 0.2;
}

.apple-alert-overlay[data-theme="dark"] .apple-alert-btn:hover {
    background: rgba(96, 165, 250, 0.15);
    transform: scale(1.02);
    color: #93c5fd !important;
}

.apple-alert-overlay[data-theme="dark"] .apple-alert-btn:active {
    background: rgba(96, 165, 250, 0.25);
    transform: scale(0.98);
}

/* Auto Theme - follows time of day */
.apple-alert-overlay[data-theme="auto"] .apple-alert-dialog {
    background: rgba(255, 255, 255, 0.95);
}

.apple-alert-overlay[data-theme="auto"] .apple-alert-title,
.apple-alert-overlay[data-theme="auto"] .apple-alert-message {
    color: #000;
}

.apple-alert-overlay[data-theme="auto"] .apple-alert-actions {
    border-top: 0.5px solid rgba(0, 0, 0, 0.1);
}

.apple-alert-overlay[data-theme="auto"] .apple-alert-btn:hover {
    background: rgba(0, 0, 0, 0.05);
}

.apple-alert-overlay[data-theme="auto"] .apple-alert-btn:active {
    background: rgba(0, 0, 0, 0.1);
}

/* Auto theme dark hours (18:00-6:00) - Premium Glass Morphism */
body.auto-dark .apple-alert-overlay[data-theme="auto"] .apple-alert-dialog {
    background: linear-gradient(
        135deg,
        rgba(20, 20, 25, 0.98) 0%,
        rgba(30, 30, 35, 0.95) 100%
    );
    border: 1px solid rgba(255, 255, 255, 0.1);
    box-shadow: 
        0 0 40px rgba(0, 0, 0, 0.6),
        0 8px 32px rgba(0, 0, 0, 0.4),
        inset 0 1px 0 rgba(255, 255, 255, 0.05),
        0 0 80px var(--alert-theme-color);
    position: relative;
    overflow: hidden;
}

body.auto-dark .apple-alert-overlay[data-theme="auto"] .apple-alert-dialog::before {
    content: '';
    position: absolute;
    top: -2px;
    left: -2px;
    right: -2px;
    bottom: -2px;
    background: linear-gradient(
        45deg,
        var(--alert-theme-color),
        transparent,
        var(--alert-theme-color)
    );
    border-radius: 16px;
    opacity: 0.3;
    z-index: -1;
    animation: borderGlow 3s ease-in-out infinite;
}

body.auto-dark .apple-alert-overlay[data-theme="auto"] .apple-alert-dialog::after {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: linear-gradient(
        45deg,
        transparent 30%,
        rgba(255, 255, 255, 0.03) 50%,
        transparent 70%
    );
    animation: shimmer 3s linear infinite;
    pointer-events: none;
}

body.auto-dark .apple-alert-overlay[data-theme="auto"] .apple-alert-title,
body.auto-dark .apple-alert-overlay[data-theme="auto"] .apple-alert-message {
    color: #fff;
    text-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
}

body.auto-dark .apple-alert-overlay[data-theme="auto"] .apple-alert-title {
    background: linear-gradient(
        135deg,
        #ffffff 0%,
        rgba(255, 255, 255, 0.9) 100%
    );
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

body.auto-dark .apple-alert-overlay[data-theme="auto"] .apple-alert-actions {
    border-top: 1px solid rgba(255, 255, 255, 0.15);
    position: relative;
    background: linear-gradient(
        to bottom,
        transparent,
        rgba(96, 165, 250, 0.05)
    );
}

body.auto-dark .apple-alert-overlay[data-theme="auto"] .apple-alert-btn {
    position: relative;
    font-weight: 600;
    font-size: 18px;
    text-shadow: 0 0 20px var(--alert-theme-color);
    color: #60a5fa !important; /* Bright blue for dark mode */
    z-index: 3;
}

body.auto-dark .apple-alert-overlay[data-theme="auto"] .apple-alert-btn::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 0;
    height: 0;
    border-radius: 50%;
    background: radial-gradient(
        circle,
        #60a5fa 0%,
        transparent 70%
    );
    opacity: 0;
    transform: translate(-50%, -50%);
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    z-index: -1;
}

body.auto-dark .apple-alert-overlay[data-theme="auto"] .apple-alert-btn:hover::before {
    width: 200px;
    height: 200px;
    opacity: 0.2;
}

body.auto-dark .apple-alert-overlay[data-theme="auto"] .apple-alert-btn:hover {
    background: rgba(96, 165, 250, 0.15);
    transform: scale(1.02);
    color: #93c5fd !important;
}

body.auto-dark .apple-alert-overlay[data-theme="auto"] .apple-alert-btn:active {
    background: rgba(96, 165, 250, 0.25);
    transform: scale(0.98);
}

.apple-alert-dialog {
    backdrop-filter: blur(30px);
    -webkit-backdrop-filter: blur(30px);
    border-radius: 14px;
    width: 90%;
    max-width: 280px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    overflow: hidden;
    animation: appleAlertScaleIn 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
    transform-origin: center;
    position: relative;
    z-index: 1;
}

.apple-alert-content {
    padding: 20px 16px;
    text-align: center;
    position: relative;
    z-index: 2;
}

.apple-alert-title {
    font-size: 17px;
    font-weight: 600;
    margin-bottom: 8px;
    letter-spacing: -0.4px;
}

.apple-alert-message {
    font-size: 13px;
    line-height: 1.4;
    letter-spacing: -0.08px;
}

.apple-alert-actions {
    /* border set by theme */
    position: relative;
    z-index: 2;
}

.apple-alert-btn {
    width: 100%;
    padding: 12px;
    background: transparent;
    border: none;
    color: <?php echo htmlspecialchars($appleAlertColor, ENT_QUOTES, 'UTF-8'); ?>;
    font-size: 17px;
    font-weight: 400;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    letter-spacing: -0.4px;
    position: relative;
    overflow: hidden;
    z-index: 1;
}

@keyframes appleAlertFadeIn {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}

@keyframes appleAlertScaleIn {
    0% {
        transform: scale(1.1);
        opacity: 0;
    }
    50% {
        transform: scale(0.98);
    }
    100% {
        transform: scale(1);
        opacity: 1;
    }
}

@keyframes shimmer {
    0% {
        transform: translate(-50%, -50%) rotate(0deg);
    }
    100% {
        transform: translate(-50%, -50%) rotate(360deg);
    }
}

@keyframes borderGlow {
    0%, 100% {
        opacity: 0.2;
        filter: blur(10px);
    }
    50% {
        opacity: 0.4;
        filter: blur(20px);
    }
}

@keyframes gradientRotate {
    0% {
        transform: translate(-50%, -50%) rotate(0deg);
    }
    100% {
        transform: translate(-50%, -50%) rotate(360deg);
    }
}

@keyframes pulse {
    0%, 100% {
        opacity: 0.6;
        transform: scale(1);
    }
    50% {
        opacity: 1;
        transform: scale(1.05);
    }
}

/* Responsive */
@media (max-width: 768px) {
    .apple-alert-dialog {
        max-width: 270px;
    }
}
</style>

<script>
// Apple Alert Functions
function showAppleAlert(message, title = 'แจ้งเตือน') {
    const alertEl = document.getElementById('appleAlert');
    const titleEl = document.getElementById('appleAlertTitle');
    const messageEl = document.getElementById('appleAlertMessage');
    
    if (!alertEl || !titleEl || !messageEl) {
        console.error('Apple Alert: Elements not found');
        return;
    }
    
    titleEl.textContent = title;
    messageEl.textContent = message;
    alertEl.classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeAppleAlert() {
    const alertEl = document.getElementById('appleAlert');
    if (alertEl) {
        alertEl.classList.remove('show');
        document.body.style.overflow = '';
    }
}

// Override native alert
if (typeof window !== 'undefined') {
    window.alert = function(message) {
        showAppleAlert(message);
    };
}

// Handle auto theme time-based switching
function updateAutoTheme() {
    const alertEl = document.getElementById('appleAlert');
    if (alertEl && alertEl.getAttribute('data-theme') === 'auto') {
        const hour = new Date().getHours();
        const isDark = hour >= 18 || hour < 6;
        
        if (isDark) {
            document.body.classList.add('auto-dark');
        } else {
            document.body.classList.remove('auto-dark');
        }
    }
}

// Initialize and update auto theme
document.addEventListener('DOMContentLoaded', function() {
    updateAutoTheme();
    
    // Update every minute for auto theme
    setInterval(updateAutoTheme, 60000);
    
    // Close on backdrop click
    const alertEl = document.getElementById('appleAlert');
    if (alertEl) {
        alertEl.addEventListener('click', function(e) {
            if (e.target === this) {
                closeAppleAlert();
            }
        });
    }
});

// Close on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeAppleAlert();
    }
});
</script>
