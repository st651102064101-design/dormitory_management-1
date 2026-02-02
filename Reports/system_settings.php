<?php
/**
 * System Settings - หน้าจัดการระบบ (Apple Settings Style)
 * 
 * ไฟล์นี้รวมไฟล์ย่อยจากโฟลเดอร์ settings/:
 * - settings_data.php     : ดึงข้อมูลการตั้งค่าจาก database
 * - section_images.php    : ส่วนจัดการรูปภาพ (โลโก้, พื้นหลัง)
 * - section_general.php   : ส่วนตั้งค่าทั่วไป (ชื่อ, เบอร์โทร, อีเมล)
 * - section_appearance.php: ส่วนการแสดงผล (ธีม, สี, ขนาดตัวอักษร)
 * - section_rates.php     : ส่วนอัตราค่าน้ำค่าไฟ
 * - section_system.php    : ส่วนข้อมูลระบบและ Backup
 */

declare(strict_types=1);
session_start();
if (empty($_SESSION['admin_username'])) {
    header('Location: ../Login.php');
    exit;
}
require_once __DIR__ . '/../ConnectDB.php';
$pdo = connectDB();

// Include settings data
include __DIR__ . '/settings/settings_data.php';
?>
<!doctype html>
<html lang="th" class="apple-settings-html">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?> - จัดการระบบ</title>
  <link rel="icon" type="image/jpeg" href="/dormitory_management/Public/Assets/Images/<?php echo htmlspecialchars($logoFilename, ENT_QUOTES, 'UTF-8'); ?>">
  <link rel="stylesheet" href="/dormitory_management/Public/Assets/Css/animate-ui.css">
  <link rel="stylesheet" href="/dormitory_management/Public/Assets/Css/main.css">
  <link rel="stylesheet" href="/dormitory_management/Public/Assets/Css/particle-effects.css">
  <script src="/dormitory_management/Public/Assets/Javascript/particle-effects.js"></script>
  <link rel="stylesheet" href="/dormitory_management/Public/Assets/Css/lottie-icons.css">
  <link rel="stylesheet" href="/dormitory_management/Reports/settings/apple-settings.css">
  <!-- Explicit Prompt font load to ensure Thai sans-serif (no serifs) -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <?php include __DIR__ . '/../includes/sidebar_toggle.php'; ?>
  <?php
    $hex = ltrim((string)$themeColor, '#');
    $themeIsLight = false;
    if (strlen($hex) === 6) {
      $r = hexdec(substr($hex, 0, 2));
      $g = hexdec(substr($hex, 2, 2));
      $b = hexdec(substr($hex, 4, 2));
      $brightness = (($r * 299) + ($g * 587) + ($b * 114)) / 1000;
      $themeIsLight = $brightness > 155;
    }
    $fallbackTextColor = $themeIsLight ? '#111827' : '#f1f5f9';
  ?>
  <style>
    /* Force Prompt everywhere (Thai sans-serif, no serifs) */
    html, body, * {
      font-family: 'Prompt', 'Noto Sans Thai', 'Segoe UI', 'Helvetica Neue', sans-serif !important;
      -webkit-font-smoothing: antialiased;
      -moz-osx-font-smoothing: grayscale;
    }

    /* Fallback text color if settings CSS fails to load */
    body.apple-settings-page {
      color: var(--apple-text, <?php echo $fallbackTextColor; ?>);
    }

    /* ===== CRITICAL FIX: Desktop & Mobile Responsive Layout ===== */
    
    /* Override animate-ui.css overflow hidden */
    html.apple-settings-html,
    body.apple-settings-page {
      overflow: visible !important;
      overflow-y: auto !important;
      height: auto !important;
      min-height: 100vh !important;
    }
    
    /* Hide any default manage-panel that may exist */
    body.apple-settings-page .manage-panel {
      display: none !important;
    }

    /* Override animate-ui.css flex display on .app-main */
    body.apple-settings-page .app-main {
      display: block !important;
      flex: 1 1 auto !important;
      height: auto !important;
      min-height: 100vh !important;
      overflow-y: auto !important;
      overflow-x: hidden !important;
      box-sizing: border-box !important;
      padding: 0 !important;
      gap: 0 !important;
      align-items: stretch !important;
    }

    /* Override .app-main > div rules from page_header.php */
    body.apple-settings-page .app-main > .apple-settings-wrapper {
      padding-left: 0 !important;
      padding-right: 0 !important;
      width: 100% !important;
    }

    /* Desktop - flex layout with sidebar */
    body.apple-settings-page .app-shell {
      display: flex !important;
      flex-direction: row !important;
      height: auto !important;
      min-height: 100vh !important;
      position: relative !important;
      width: 100% !important;
    }

    /* Ensure content is visible */
    body.apple-settings-page .apple-settings-wrapper {
      display: block !important;
      visibility: visible !important;
      opacity: 1 !important;
      max-width: 700px;
      margin: 0 auto;
      padding: 40px 24px 100px;
      min-height: 100vh;
      box-sizing: border-box;
    }

    /* Mobile - sidebar hidden, full width content */
    @media (max-width: 1024px) {
      body.apple-settings-page .app-shell {
        display: block !important;
        flex-direction: column !important;
      }

      body.apple-settings-page .app-main {
        width: 100% !important;
        max-width: 100% !important;
        margin-left: 0 !important;
        padding: 0 !important;
      }

      body.apple-settings-page .apple-settings-wrapper {
        padding: 60px 16px 100px !important;
        max-width: 100% !important;
      }
    }

    /* Desktop - content next to sidebar */
    @media (min-width: 1025px) {
      body.apple-settings-page .app-main {
        margin-left: 0 !important;
        width: auto !important;
        max-width: none !important;
      }

      body.apple-settings-page .apple-settings-wrapper {
        max-width: 700px !important;
        margin: 0 auto !important;
        padding: 40px 24px 100px !important;
      }
    }
  </style>
</head>
<body class="reports-page apple-settings-page" data-theme-color="<?php echo htmlspecialchars($themeColor); ?>">
  <div class="app-shell">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    
    <!-- Critical Layout Override - After Sidebar CSS -->
    <style>
      /* Hide Sidebar Completely for Apple Settings Page */
      body.apple-settings-page .app-sidebar {
        display: none !important;
      }
      
      /* Hide Menu Button - No Sidebar Needed */
      body.apple-settings-page .apple-menu-btn {
        display: none !important;
      }
      
      /* Override sidebar.php CSS for apple-settings-page */
      body.apple-settings-page,
      html.apple-settings-html {
        overflow: visible !important;
        overflow-y: auto !important;
        height: auto !important;
        min-height: 100vh !important;
      }
      
      body.apple-settings-page .app-shell {
        display: block !important;
        height: auto !important;
        min-height: 100vh !important;
      }
      
      body.apple-settings-page .app-main {
        display: block !important;
        width: 100% !important;
        max-width: 100% !important;
        height: auto !important;
        min-height: 100vh !important;
        overflow-y: auto !important;
        overflow-x: hidden !important;
        padding: 0 !important;
        margin: 0 !important;
        visibility: visible !important;
        opacity: 1 !important;
      }
      
      body.apple-settings-page .apple-settings-wrapper {
        display: block !important;
        visibility: visible !important;
        opacity: 1 !important;
        max-width: 900px !important;
        margin: 0 auto !important;
        padding: 40px 24px 100px !important;
      }
      
      /* Mobile adjustments */
      @media (max-width: 768px) {
        body.apple-settings-page .apple-settings-wrapper {
          padding: 60px 16px 100px !important;
        }
      }
      
      /* Back Button Style */
      .apple-back-btn {
        position: fixed !important;
        top: 20px !important;
        left: 20px !important;
        width: 48px !important;
        height: 48px !important;
        border-radius: 14px !important;
        background: var(--apple-card) !important;
        border: none !important;
        cursor: pointer !important;
        z-index: 10001 !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        box-shadow: 0 2px 12px rgba(0, 0, 0, 0.1), 0 1px 3px rgba(0, 0, 0, 0.08) !important;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1) !important;
        text-decoration: none !important;
      }
      
      .apple-back-btn:hover {
        transform: scale(1.05);
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
        background: var(--apple-blue) !important;
      }
      
      .apple-back-btn:hover svg {
        stroke: #ffffff !important;
      }
      
      .apple-back-btn:active {
        transform: scale(0.95);
      }
      
      .apple-back-btn svg {
        width: 22px;
        height: 22px;
        stroke: var(--apple-text);
        stroke-width: 2.5;
        stroke-linecap: round;
        stroke-linejoin: round;
        fill: none;
        transition: stroke 0.2s ease;
      }
    </style>
    
    <!-- Back Button -->
    <a href="/dormitory_management/Reports/dashboard.php" class="apple-back-btn" aria-label="ย้อนกลับ">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
        <path d="M19 12H5M12 19l-7-7 7-7"/>
      </svg>
    </a>
    
    <main class="app-main">
      <!-- Apple Settings UI -->
      <div class="apple-settings-wrapper">
        
        <!-- Header -->
        <div class="apple-settings-header">
          <h1>ตั้งค่า</h1>
          <p>จัดการระบบหอพัก</p>
        </div>
        
        <!-- Profile Card -->
        <div class="apple-profile-card">
          <img src="/dormitory_management/Public/Assets/Images/<?php echo htmlspecialchars($logoFilename); ?>" alt="Logo" class="apple-profile-avatar">
          <div class="apple-profile-info">
            <h2 class="apple-profile-name"><?php echo htmlspecialchars($siteName); ?></h2>
            <p class="apple-profile-detail">ผู้ดูแลระบบ: <?php echo htmlspecialchars($_SESSION['admin_username']); ?></p>
          </div>
          <span class="apple-profile-chevron">›</span>
        </div>
        
        <!-- Stats -->
        <div class="apple-stats-grid">
          <div class="apple-stat-card particle-wrapper">
            <div class="particle-container" data-particles="3"></div>
            <div class="lottie-icon blue">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="icon-animated"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            </div>
            <div class="apple-stat-value"><?php echo number_format($totalRooms); ?></div>
            <div class="apple-stat-label">ห้องพัก</div>
          </div>
          <div class="apple-stat-card particle-wrapper">
            <div class="particle-container" data-particles="3"></div>
            <div class="lottie-icon green">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="icon-animated"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </div>
            <div class="apple-stat-value"><?php echo number_format($totalTenants); ?></div>
            <div class="apple-stat-label">ผู้เช่า</div>
          </div>
          <div class="apple-stat-card particle-wrapper">
            <div class="particle-container" data-particles="3"></div>
            <div class="lottie-icon orange">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="icon-animated"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h18"/><path d="M8 14h.01"/><path d="M12 14h.01"/><path d="M16 14h.01"/></svg>
            </div>
            <div class="apple-stat-value"><?php echo number_format($totalBookings); ?></div>
            <div class="apple-stat-label">รอจอง</div>
          </div>
        </div>
        
        <!-- Include Sections -->
        <?php include __DIR__ . '/settings/section_images.php'; ?>
        <?php include __DIR__ . '/settings/section_general.php'; ?>
        <?php include __DIR__ . '/settings/section_appearance.php'; ?>
        <?php include __DIR__ . '/settings/section_rates.php'; ?>
        <?php include __DIR__ . '/settings/section_google_auth.php'; ?>
        <?php include __DIR__ . '/settings/section_system.php'; ?>
        
      </div>
    </main>
  </div>
  
  <!-- Scripts -->
  <script src="/dormitory_management/Public/Assets/Javascript/toast-notification.js"></script>
  <script src="/dormitory_management/Public/Assets/Javascript/animate-ui.js"></script>
  <script src="/dormitory_management/Reports/settings/apple-settings.js"></script>
  <script>
  // Force layout fix for desktop
  (function() {
    function forceLayoutFix() {
      const html = document.documentElement;
      const body = document.body;
      const appShell = document.querySelector('.app-shell');
      const appMain = document.querySelector('.app-main');
      const wrapper = document.querySelector('.apple-settings-wrapper');
      
      // Force override styles
      if (html) {
        html.style.overflow = 'visible';
        html.style.overflowY = 'auto';
        html.style.height = 'auto';
        html.style.minHeight = '100vh';
      }
      
      if (body) {
        body.style.overflow = 'visible';
        body.style.overflowY = 'auto';
        body.style.height = 'auto';
        body.style.minHeight = '100vh';
      }
      
      if (appShell) {
        appShell.style.display = 'flex';
        appShell.style.flexDirection = 'row';
        appShell.style.minHeight = '100vh';
        appShell.style.height = 'auto';
      }
      
      if (appMain) {
        appMain.style.display = 'block';
        appMain.style.flex = '1 1 auto';
        appMain.style.minHeight = '100vh';
        appMain.style.height = 'auto';
        appMain.style.overflowY = 'auto';
        appMain.style.visibility = 'visible';
        appMain.style.opacity = '1';
      }
      
      if (wrapper) {
        wrapper.style.display = 'block';
        wrapper.style.visibility = 'visible';
        wrapper.style.opacity = '1';
      }
      
      // Mobile adjustments
      if (window.innerWidth <= 1024) {
        if (appShell) {
          appShell.style.display = 'block';
          appShell.style.flexDirection = 'column';
        }
        if (appMain) {
          appMain.style.width = '100%';
          appMain.style.maxWidth = '100%';
          appMain.style.marginLeft = '0';
        }
      }
    }
    
    // Run on DOM ready and after a short delay
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', forceLayoutFix);
    } else {
      forceLayoutFix();
    }
    
    // Also run after window load to catch late-loading CSS
    window.addEventListener('load', forceLayoutFix);
    
    // Run on resize for responsive adjustments
    window.addEventListener('resize', forceLayoutFix);
    
    // Run immediately as well
    setTimeout(forceLayoutFix, 0);
    setTimeout(forceLayoutFix, 100);
    setTimeout(forceLayoutFix, 500);
  })();
  
  // Connect apple-menu-btn to global sidebar toggle
  (function() {
    const menuBtn = document.getElementById('apple-menu-btn');
    
    if (menuBtn) {
      menuBtn.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        // Use the global sidebar toggle function
        if (typeof window.__directSidebarToggle === 'function') {
          window.__directSidebarToggle(e);
        }
      });
      
      // Initialize sidebar state
      if (typeof window.__initSidebarState === 'function') {
        if (document.readyState === 'loading') {
          document.addEventListener('DOMContentLoaded', window.__initSidebarState);
        } else {
          window.__initSidebarState();
        }
      }
    }
  })();
  </script>
</body>
</html>
