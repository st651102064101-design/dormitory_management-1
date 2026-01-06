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

/* Dark Theme */
.apple-alert-overlay[data-theme="dark"] .apple-alert-dialog {
    background: rgba(28, 28, 30, 0.95);
}

.apple-alert-overlay[data-theme="dark"] .apple-alert-title,
.apple-alert-overlay[data-theme="dark"] .apple-alert-message {
    color: #fff;
}

.apple-alert-overlay[data-theme="dark"] .apple-alert-actions {
    border-top: 0.5px solid rgba(255, 255, 255, 0.15);
}

.apple-alert-overlay[data-theme="dark"] .apple-alert-btn:hover {
    background: rgba(255, 255, 255, 0.08);
}

.apple-alert-overlay[data-theme="dark"] .apple-alert-btn:active {
    background: rgba(255, 255, 255, 0.15);
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

/* Auto theme dark hours (18:00-6:00) */
body.auto-dark .apple-alert-overlay[data-theme="auto"] .apple-alert-dialog {
    background: rgba(28, 28, 30, 0.95);
}

body.auto-dark .apple-alert-overlay[data-theme="auto"] .apple-alert-title,
body.auto-dark .apple-alert-overlay[data-theme="auto"] .apple-alert-message {
    color: #fff;
}

body.auto-dark .apple-alert-overlay[data-theme="auto"] .apple-alert-actions {
    border-top: 0.5px solid rgba(255, 255, 255, 0.15);
}

body.auto-dark .apple-alert-overlay[data-theme="auto"] .apple-alert-btn:hover {
    background: rgba(255, 255, 255, 0.08);
}

body.auto-dark .apple-alert-overlay[data-theme="auto"] .apple-alert-btn:active {
    background: rgba(255, 255, 255, 0.15);
}

.apple-alert-dialog {
    backdrop-filter: blur(30px);
    -webkit-backdrop-filter: blur(30px);
    border-radius: 14px;
    width: 90%;
    max-width: 280px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    overflow: hidden;
    animation: appleAlertScaleIn 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
    transform-origin: center;
}

.apple-alert-content {
    padding: 20px 16px;
    text-align: center;
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
    transition: background 0.15s;
    letter-spacing: -0.4px;
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
    from {
        transform: scale(1.1);
        opacity: 0;
    }
    to {
        transform: scale(1);
        opacity: 1;
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
