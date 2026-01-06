<!-- Apple-Style Alert Component -->
<!-- ใช้งาน: include_once __DIR__ . '/../includes/apple_alert.php'; -->
<!-- แล้วเรียก: alert('ข้อความ'); หรือ showAppleAlert('ข้อความ', 'หัวข้อ'); -->

<div id="appleAlert" class="apple-alert-overlay">
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

.apple-alert-dialog {
    background: rgba(255, 255, 255, 0.95);
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
    color: #000;
    margin-bottom: 8px;
    letter-spacing: -0.4px;
}

.apple-alert-message {
    font-size: 13px;
    color: #000;
    line-height: 1.4;
    letter-spacing: -0.08px;
}

.apple-alert-actions {
    border-top: 0.5px solid rgba(0, 0, 0, 0.1);
}

.apple-alert-btn {
    width: 100%;
    padding: 12px;
    background: transparent;
    border: none;
    color: #007AFF;
    font-size: 17px;
    font-weight: 400;
    cursor: pointer;
    transition: background 0.15s;
    letter-spacing: -0.4px;
}

.apple-alert-btn:hover {
    background: rgba(0, 0, 0, 0.05);
}

.apple-alert-btn:active {
    background: rgba(0, 0, 0, 0.1);
    transform: scale(0.98);
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

/* Dark Mode Support */
@media (prefers-color-scheme: dark) {
    .apple-alert-dialog {
        background: rgba(50, 50, 50, 0.95);
    }
    
    .apple-alert-title,
    .apple-alert-message {
        color: #fff;
    }
    
    .apple-alert-actions {
        border-top: 0.5px solid rgba(255, 255, 255, 0.1);
    }
    
    .apple-alert-btn:hover {
        background: rgba(255, 255, 255, 0.05);
    }
    
    .apple-alert-btn:active {
        background: rgba(255, 255, 255, 0.1);
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

// Close on backdrop click
document.addEventListener('DOMContentLoaded', function() {
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
