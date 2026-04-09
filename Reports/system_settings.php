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

// Load language helper for page content translation
require_once __DIR__ . '/../includes/lang.php';
require_once __DIR__ . '/../includes/thai_date_helper.php';

// Include settings data
include __DIR__ . '/settings/settings_data.php';

// Get current language from language helper system
$systemLanguage = getLang();

$pageTitle = __('settings');

$toastScriptPath = __DIR__ . '/../Public/Assets/Javascript/toast-notification.js';
$toastScriptVersion = is_file($toastScriptPath) ? (string)filemtime($toastScriptPath) : (string)time();

$appleSettingsCssPath = __DIR__ . '/settings/apple-settings.css';
$appleSettingsCssVersion = is_file($appleSettingsCssPath) ? (string)filemtime($appleSettingsCssPath) : (string)time();

$appleSettingsScriptPath = __DIR__ . '/settings/apple-settings.js';
$appleSettingsScriptVersion = is_file($appleSettingsScriptPath) ? (string)filemtime($appleSettingsScriptPath) : (string)time();
?>
<!doctype html>
<html lang="<?php echo htmlspecialchars($systemLanguage === 'en' ? 'en' : 'th', ENT_QUOTES, 'UTF-8'); ?>" class="apple-settings-html">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?> - <?php echo htmlspecialchars(__('settings'), ENT_QUOTES, 'UTF-8'); ?></title>
  <link rel="icon" type="image/jpeg" href="/dormitory_management/Public/Assets/Images/<?php echo htmlspecialchars($logoFilename, ENT_QUOTES, 'UTF-8'); ?>">
  <link rel="stylesheet" href="/dormitory_management/Public/Assets/Css/animate-ui.css">
  <link rel="stylesheet" href="/dormitory_management/Public/Assets/Css/main.css">
  <link rel="stylesheet" href="/dormitory_management/Public/Assets/Css/particle-effects.css">
  <script src="/dormitory_management/Public/Assets/Javascript/particle-effects.js"></script>
  <link rel="stylesheet" href="/dormitory_management/Public/Assets/Css/lottie-icons.css">
  <link rel="stylesheet" href="/dormitory_management/Reports/settings/apple-settings.css?v=<?php echo urlencode($appleSettingsCssVersion); ?>">
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
      padding-top: 10px !important;
      padding-left: 10px !important;
      padding-right: 10px !important;
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
      padding: 0 24px 100px;
      min-height: 100vh;
      box-sizing: border-box;
    }

    body.apple-settings-page .apple-settings-wrapper .page-header-bar {
      position: sticky !important;
      top: 0 !important;
      margin-top: 0 !important;
      margin-bottom: 1rem !important;
    }

    body.apple-settings-page .apple-settings-wrapper .apple-settings-header {
      padding-top: 8px;
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
        padding: 0 16px 100px !important;
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
        padding: 0 24px 100px !important;
      }
    }

  </style>
</head>
<body class="reports-page apple-settings-page" data-theme-color="<?php echo htmlspecialchars($themeColor); ?>">
  <div class="app-shell">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    
    <!-- Layout Override - Keep sidebar visible on settings page -->
    <style>
      /* Override sidebar.php CSS for apple-settings-page */
      body.apple-settings-page,
      html.apple-settings-html {
        overflow: visible !important;
        overflow-y: auto !important;
        height: auto !important;
        min-height: 100vh !important;
      }
      
      body.apple-settings-page .app-shell {
        display: flex !important;
        flex-direction: row !important;
        height: auto !important;
        min-height: 100vh !important;
      }

      /* apple-settings.css hides sidebar by default using translateX(-100%).
         Force visible sidebar on desktop for consistent reports layout. */
      @media (min-width: 1025px) {
        body.apple-settings-page .app-sidebar {
          position: fixed !important;
          top: 0 !important;
          left: 0 !important;
          bottom: 0 !important;
          transform: none !important;
          z-index: 1100 !important;
          overflow-y: auto !important;
          width: 220px !important;
          min-width: 220px !important;
          max-width: 220px !important;
          display: flex !important;
          flex-direction: column !important;
        }

        /* Keep collapsed rail width consistent with global sidebar behavior */
        body.apple-settings-page .app-sidebar.collapsed,
        body.apple-settings-page aside.sidebar-collapsed {
          width: 80px !important;
          min-width: 80px !important;
          max-width: 80px !important;
        }

        body.apple-settings-page .apple-menu-btn {
          display: none !important;
        }
      }
      
      body.apple-settings-page .app-main {
        display: block !important;
        flex: 1 1 auto !important;
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

      @media (min-width: 1025px) {
        body.apple-settings-page .app-main {
          margin-left: 220px !important;
          width: calc(100% - 220px) !important;
          max-width: calc(100% - 220px) !important;
        }

        body.apple-settings-page .app-sidebar.collapsed ~ .app-main,
        body.apple-settings-page aside.sidebar-collapsed ~ .app-main {
          margin-left: 80px !important;
          width: calc(100% - 80px) !important;
          max-width: calc(100% - 80px) !important;
        }
      }

      /* Prevent hidden mobile sidebar layers from intercepting taps on settings content */
      @media (max-width: 1024px) {
        body.apple-settings-page .app-sidebar:not(.mobile-open) {
          pointer-events: none !important;
        }
      }
      
      body.apple-settings-page .apple-settings-wrapper {
        display: block !important;
        visibility: visible !important;
        opacity: 1 !important;
        max-width: 900px !important;
        margin: 0 auto !important;
        padding: 0 24px 100px !important;
      }
      
      /* Mobile adjustments */
      @media (max-width: 768px) {
        body.apple-settings-page .app-shell {
          display: block !important;
          flex-direction: column !important;
        }

        body.apple-settings-page .apple-settings-wrapper {
          padding: 0 16px 100px !important;
        }
      }
    </style>
    
    <main class="app-main">
      <!-- Apple Settings UI -->
      <div class="apple-settings-wrapper">
        <?php include __DIR__ . '/../includes/page_header.php'; ?>

        <!-- Header -->
        <div class="apple-settings-header">
          <h1><?php echo htmlspecialchars(__('settings'), ENT_QUOTES, 'UTF-8'); ?></h1>
          <p><?php echo htmlspecialchars(__('settings_subtitle'), ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
        
        <!-- Profile Card -->
        <div class="apple-profile-card">
          <img src="/dormitory_management/Public/Assets/Images/<?php echo htmlspecialchars($logoFilename); ?>" alt="Logo" class="apple-profile-avatar">
          <div class="apple-profile-info">
            <h2 class="apple-profile-name"><?php echo htmlspecialchars($siteName); ?></h2>
            <p class="apple-profile-detail"><?php echo htmlspecialchars(__('admin'), ENT_QUOTES, 'UTF-8'); ?>: <?php echo htmlspecialchars($_SESSION['admin_username']); ?></p>
          </div>
          <span class="apple-profile-chevron">›</span>
        </div>
        
        <!-- Stats -->
        <div class="apple-stats-grid">
          <a href="manage_rooms.php" class="apple-stat-card particle-wrapper">
            <div class="particle-container" data-particles="3"></div>
            <div class="lottie-icon blue">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="icon-animated"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            </div>
            <div class="apple-stat-value"><?php echo number_format($totalRooms); ?></div>
            <div class="apple-stat-label"><?php echo __('rooms'); ?></div>
          </a>
          <a href="manage_tenants.php" class="apple-stat-card particle-wrapper">
            <div class="particle-container" data-particles="3"></div>
            <div class="lottie-icon green">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="icon-animated"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </div>
            <div class="apple-stat-value"><?php echo number_format($totalTenants); ?></div>
            <div class="apple-stat-label"><?php echo __('tenants'); ?></div>
          </a>
          <a href="manage_booking.php" class="apple-stat-card particle-wrapper">
            <div class="particle-container" data-particles="3"></div>
            <div class="lottie-icon orange">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="icon-animated"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h18"/><path d="M8 14h.01"/><path d="M12 14h.01"/><path d="M16 14h.01"/></svg>
            </div>
            <div class="apple-stat-value"><?php echo number_format($totalBookings); ?></div>
            <div class="apple-stat-label"><?php echo __('stat_pending'); ?></div>
          </a>
        </div>
        
        <!-- Include Sections -->
        <?php include __DIR__ . '/settings/section_images.php'; ?>
        <?php include __DIR__ . '/settings/section_general.php'; ?>
        <?php include __DIR__ . '/settings/section_appearance.php'; ?>
        <?php include __DIR__ . '/settings/section_rates.php'; ?>
        <?php include __DIR__ . '/settings/section_google_auth.php'; ?>
        <?php include __DIR__ . '/settings/section_line_oa.php'; ?>
        <?php include __DIR__ . '/settings/section_apis.php'; ?>
        <?php include __DIR__ . '/settings/section_system.php'; ?>
        
      </div>
    </main>
  </div>
  
  <!-- Scripts -->
  <script src="/dormitory_management/Public/Assets/Javascript/toast-notification.js?v=<?php echo urlencode($toastScriptVersion); ?>"></script>
  <script src="/dormitory_management/Reports/settings/apple-settings.js?v=<?php echo urlencode($appleSettingsScriptVersion); ?>"></script>
  <script>
  // Keep settings layout stable and avoid click conflicts from duplicated handlers.
  (function() {
    const settingsPromptpayI18n = {
      invalidFormat: <?php echo json_encode(__('promptpay_invalid_format'), JSON_UNESCAPED_UNICODE); ?>,
      invalidResponse: <?php echo json_encode(__('invalid_server_response'), JSON_UNESCAPED_UNICODE); ?>,
      saveError: <?php echo json_encode(__('error_occurred'), JSON_UNESCAPED_UNICODE); ?>,
      notSet: <?php echo json_encode(__('not_set'), JSON_UNESCAPED_UNICODE); ?>,
      savedSuccess: <?php echo json_encode(__('promptpay_saved_success'), JSON_UNESCAPED_UNICODE); ?>
    };

    function syncSettingsLayout() {
      const body = document.body;
      const sidebar = document.querySelector('.app-sidebar');
      const appMain = document.querySelector('.app-main');
      const toggleBtn = document.getElementById('sidebar-toggle');

      if (!body || !appMain || !sidebar) {
        return;
      }

      if (window.innerWidth > 1024) {
        sidebar.classList.remove('mobile-open');
        body.classList.remove('sidebar-open');

        appMain.style.removeProperty('margin-left');
        appMain.style.removeProperty('width');
        appMain.style.removeProperty('max-width');

        if (toggleBtn) {
          toggleBtn.setAttribute('aria-expanded', sidebar.classList.contains('collapsed') ? 'false' : 'true');
        }
      } else {
        appMain.style.removeProperty('margin-left');
        appMain.style.removeProperty('width');
        appMain.style.removeProperty('max-width');

        if (toggleBtn) {
          toggleBtn.setAttribute('aria-expanded', sidebar.classList.contains('mobile-open') ? 'true' : 'false');
        }
      }
    }

    function bindQuickActionFallback() {
      if (document.__settingsQuickActionBound) {
        return;
      }
      document.__settingsQuickActionBound = true;

      document.addEventListener('click', function(event) {
        const link = event.target.closest('.quick-action-link[href]');
        if (!link) {
          return;
        }

        if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
          return;
        }

        const href = (link.getAttribute('href') || '').trim();
        if (!href || href === '#') {
          return;
        }

        event.preventDefault();
        window.location.href = href;
      }, true);
    }

    function bindSheetOpenFallback() {
      if (document.__settingsSheetFallbackBound) {
        return;
      }
      document.__settingsSheetFallbackBound = true;

      const logBillingIssue = (level, message, payload) => {
        const data = payload || {};
        if (data.rowId !== 'billingScheduleRow') {
          return;
        }

        if (level === 'error') {
          console.error('[SheetDebug]', message, data);
          return;
        }
        if (level === 'warn') {
          console.warn('[SheetDebug]', message, data);
          return;
        }
        console.info('[SheetDebug]', message, data);
      };

      document.addEventListener('click', function(event) {
        const row = event.target.closest('.apple-settings-row[data-sheet]');
        if (!row) {
          return;
        }

        const debugContext = {
          source: 'system_settings.bindSheetOpenFallback.click',
          rowId: row.id || '',
          targetTag: event.target && event.target.tagName ? event.target.tagName : ''
        };

        if (event.defaultPrevented) {
          logBillingIssue('warn', 'Event already prevented before system fallback handler', debugContext);
          return;
        }

        if (event.target.closest('button, input, select, textarea, a, .apple-toggle, [data-close-sheet]')) {
          logBillingIssue('info', 'Ignored interactive child target', debugContext);
          return;
        }

        const sheetId = row.getAttribute('data-sheet');
        if (!sheetId) {
          logBillingIssue('error', 'Row missing data-sheet attribute', debugContext);
          return;
        }
        debugContext.sheetId = sheetId;

        let sheet = document.getElementById(sheetId);
        if (!sheet && sheetId === 'sheet-billing-schedule' && typeof window.ensureBillingScheduleSheetFallback === 'function') {
          try {
            window.ensureBillingScheduleSheetFallback();
            sheet = document.getElementById(sheetId);
            if (sheet) {
              logBillingIssue('info', 'Created fallback billing schedule sheet', debugContext);
            }
          } catch (fallbackError) {
            logBillingIssue('warn', 'Failed to build billing schedule fallback sheet', fallbackError);
          }
        }

        if (!sheet) {
          logBillingIssue('error', 'Sheet overlay not found in system fallback', debugContext);
          return;
        }

        if (sheet.classList.contains('active')) {
          logBillingIssue('info', 'Sheet already active; skip duplicate open', debugContext);
          return;
        }

        if (window.appleSettings && typeof window.appleSettings.openSheet === 'function') {
          const opened = window.appleSettings.openSheet(sheetId, {
            source: 'system_settings.bindSheetOpenFallback.click',
            rowId: row.id || '',
            sheetId
          }) === true;

          if (opened) {
            event.preventDefault();
            return;
          }

          logBillingIssue('warn', 'appleSettings.openSheet returned false, trying raw DOM fallback', debugContext);
        }

        sheet.classList.add('active');
        document.body.style.overflow = 'hidden';
        event.preventDefault();
        logBillingIssue('info', 'Opened sheet via raw DOM fallback', debugContext);
      }, true);
    }

    function bindSheetBackdropCloseFallback() {
      if (document.__settingsSheetBackdropCloseBound) {
        return;
      }
      document.__settingsSheetBackdropCloseBound = true;

      const closeOverlay = (overlay) => {
        if (!overlay) {
          return;
        }

        overlay.classList.remove('active');
        if (!document.querySelector('.apple-sheet-overlay.active')) {
          document.body.style.overflow = '';
        }
      };

      document.addEventListener('click', function(event) {
        const overlay = event.target.closest('.apple-sheet-overlay.active');
        if (!overlay) {
          return;
        }

        const sheet = overlay.querySelector('.apple-sheet');
        if (sheet && sheet.contains(event.target)) {
          return;
        }

        event.preventDefault();
        closeOverlay(overlay);
      }, true);

      document.addEventListener('touchend', function(event) {
        const overlay = event.target.closest('.apple-sheet-overlay.active');
        if (!overlay) {
          return;
        }

        const sheet = overlay.querySelector('.apple-sheet');
        if (sheet && sheet.contains(event.target)) {
          return;
        }

        closeOverlay(overlay);
      }, { capture: true, passive: true });
    }

    function bindSiteNameSaveFallback() {
      if (document.__siteNameSaveFallbackBound) {
        return;
      }

      const form = document.getElementById('siteNameForm');
      const button = document.getElementById('saveSiteNameBtn');
      const input = document.getElementById('siteName');
      if (!form || !button || !input) {
        return;
      }

      document.__siteNameSaveFallbackBound = true;

      const runSave = async () => {
        const siteName = input.value.trim();
        if (!siteName) {
          if (window.appleSettings && typeof window.appleSettings.showToast === 'function') {
            window.appleSettings.showToast('กรุณากรอกชื่อหอพัก', 'error');
          }
          input.focus();
          return;
        }

        if (button.dataset.saving === '1') {
          return;
        }

        button.dataset.saving = '1';
        button.disabled = true;

        try {
          const response = await fetch('/dormitory_management/Manage/save_system_settings.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `site_name=${encodeURIComponent(siteName)}`
          });

          let result = null;
          try {
            result = await response.json();
          } catch (parseError) {
            throw new Error('ระบบตอบกลับไม่ถูกต้อง');
          }

          if (!response.ok || !result.success) {
            throw new Error(result.error || 'เกิดข้อผิดพลาด');
          }

          const displayEl = document.querySelector('[data-display="sitename"]');
          if (displayEl) {
            displayEl.textContent = siteName;
          }

          const profileNameEl = document.querySelector('.apple-profile-name');
          if (profileNameEl) {
            profileNameEl.textContent = siteName;
          }

          document.title = `${siteName} - จัดการระบบ`;

          const sheet = document.getElementById('sheet-sitename');
          if (sheet) {
            sheet.classList.remove('active');
            document.body.style.overflow = '';
          }

          if (window.appleSettings && typeof window.appleSettings.showToast === 'function') {
            window.appleSettings.showToast('บันทึกชื่อหอพักสำเร็จ', 'success');
          }
        } catch (error) {
          if (window.appleSettings && typeof window.appleSettings.showToast === 'function') {
            window.appleSettings.showToast(error.message, 'error');
          }
        } finally {
          button.dataset.saving = '0';
          button.disabled = false;
        }
      };

      form.addEventListener('submit', (event) => {
        event.preventDefault();
        event.stopPropagation();
        runSave();
      }, true);

      button.addEventListener('click', (event) => {
        event.preventDefault();
        event.stopPropagation();
        runSave();
      }, true);
    }

    function bindPhoneSaveFallback() {
      if (document.__phoneSaveFallbackBound) {
        return;
      }

      const form = document.getElementById('phoneForm');
      const button = document.getElementById('savePhoneBtn');
      const input = document.getElementById('contactPhone');
      if (!form || !button || !input) {
        return;
      }

      document.__phoneSaveFallbackBound = true;

      const runSave = async () => {
        const phone = input.value.trim();
        if (!/^[0-9+\s()\-]{8,20}$/.test(phone)) {
          if (window.appleSettings && typeof window.appleSettings.showToast === 'function') {
            window.appleSettings.showToast('รูปแบบเบอร์โทรไม่ถูกต้อง', 'error');
          }
          input.focus();
          return;
        }

        if (form.dataset.saving === '1') {
          return;
        }

        form.dataset.saving = '1';
        button.disabled = true;

        try {
          const response = await fetch('/dormitory_management/Manage/save_system_settings.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `contact_phone=${encodeURIComponent(phone)}`
          });

          let result = null;
          try {
            result = await response.json();
          } catch (parseError) {
            throw new Error('ระบบตอบกลับไม่ถูกต้อง');
          }

          if (!response.ok || !result.success) {
            throw new Error(result.error || 'เกิดข้อผิดพลาด');
          }

          const displayEl = document.querySelector('[data-display="phone"]');
          if (displayEl) {
            displayEl.textContent = phone;
          }

          const sheet = document.getElementById('sheet-phone');
          if (sheet) {
            sheet.classList.remove('active');
            document.body.style.overflow = '';
          }

          if (window.appleSettings && typeof window.appleSettings.showToast === 'function') {
            window.appleSettings.showToast('บันทึกเบอร์โทรสำเร็จ', 'success');
          }
        } catch (error) {
          if (window.appleSettings && typeof window.appleSettings.showToast === 'function') {
            window.appleSettings.showToast(error.message, 'error');
          }
        } finally {
          form.dataset.saving = '0';
          button.disabled = false;
        }
      };

      form.addEventListener('submit', (event) => {
        event.preventDefault();
        event.stopPropagation();
        runSave();
      }, true);

      button.addEventListener('click', (event) => {
        event.preventDefault();
        event.stopPropagation();
        runSave();
      }, true);
    }

    function bindEmailSaveFallback() {
      if (document.__emailSaveFallbackBound) {
        return;
      }

      const form = document.getElementById('emailForm');
      const button = document.getElementById('saveEmailBtn');
      const input = document.getElementById('contactEmail');
      if (!form || !button || !input) {
        return;
      }

      document.__emailSaveFallbackBound = true;

      const runSave = async () => {
        const email = input.value.trim();
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
          if (window.appleSettings && typeof window.appleSettings.showToast === 'function') {
            window.appleSettings.showToast('รูปแบบอีเมลไม่ถูกต้อง', 'error');
          }
          input.focus();
          return;
        }

        if (form.dataset.saving === '1') {
          return;
        }

        form.dataset.saving = '1';
        button.disabled = true;

        try {
          const response = await fetch('/dormitory_management/Manage/save_system_settings.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `contact_email=${encodeURIComponent(email)}`
          });

          let result = null;
          try {
            result = await response.json();
          } catch (parseError) {
            throw new Error('ระบบตอบกลับไม่ถูกต้อง');
          }

          if (!response.ok || !result.success) {
            throw new Error(result.error || 'เกิดข้อผิดพลาด');
          }

          const displayEl = document.querySelector('[data-display="email"]');
          if (displayEl) {
            displayEl.textContent = email;
          }

          const sheet = document.getElementById('sheet-email');
          if (sheet) {
            sheet.classList.remove('active');
            document.body.style.overflow = '';
          }

          if (window.appleSettings && typeof window.appleSettings.showToast === 'function') {
            window.appleSettings.showToast('บันทึกอีเมลสำเร็จ', 'success');
          }
        } catch (error) {
          if (window.appleSettings && typeof window.appleSettings.showToast === 'function') {
            window.appleSettings.showToast(error.message, 'error');
          }
        } finally {
          form.dataset.saving = '0';
          button.disabled = false;
        }
      };

      form.addEventListener('submit', (event) => {
        event.preventDefault();
        event.stopPropagation();
        runSave();
      }, true);

      button.addEventListener('click', (event) => {
        event.preventDefault();
        event.stopPropagation();
        runSave();
      }, true);
    }

    function bindPromptpaySaveFallback() {
      if (document.__promptpaySaveFallbackBound) {
        return;
      }

      const form = document.getElementById('promptpayForm');
      const button = document.getElementById('savePromptpayBtn');
      const input = document.getElementById('promptpayNumber');
      if (!form || !button || !input) {
        return;
      }

      document.__promptpaySaveFallbackBound = true;

      const isValidPromptpay = (value) => {
        const digits = value.replace(/[^0-9]/g, '');
        return digits.length === 10 || digits.length === 13;
      };

      const runSave = async () => {
        const promptpayNumber = input.value.trim();
        if (promptpayNumber !== '' && !isValidPromptpay(promptpayNumber)) {
          if (window.appleSettings && typeof window.appleSettings.showToast === 'function') {
            window.appleSettings.showToast(settingsPromptpayI18n.invalidFormat, 'error');
          }
          input.focus();
          return;
        }

        if (form.dataset.saving === '1') {
          return;
        }

        form.dataset.saving = '1';
        button.disabled = true;

        try {
          const response = await fetch('/dormitory_management/Manage/save_system_settings.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `promptpay_number=${encodeURIComponent(promptpayNumber)}`
          });

          let result = null;
          try {
            result = await response.json();
          } catch (parseError) {
            throw new Error(settingsPromptpayI18n.invalidResponse);
          }

          if (!response.ok || !result.success) {
            throw new Error(result.error || settingsPromptpayI18n.saveError);
          }

          const displayEl = document.querySelector('[data-display="promptpay"]');
          if (displayEl) {
            displayEl.textContent = promptpayNumber || settingsPromptpayI18n.notSet;
          }

          const sheet = document.getElementById('sheet-promptpay');
          if (sheet) {
            sheet.classList.remove('active');
            document.body.style.overflow = '';
          }

          if (window.appleSettings && typeof window.appleSettings.showToast === 'function') {
            window.appleSettings.showToast(settingsPromptpayI18n.savedSuccess, 'success');
          }
        } catch (error) {
          if (window.appleSettings && typeof window.appleSettings.showToast === 'function') {
            window.appleSettings.showToast(error.message || settingsPromptpayI18n.saveError, 'error');
          }
        } finally {
          form.dataset.saving = '0';
          button.disabled = false;
        }
      };

      form.addEventListener('submit', (event) => {
        event.preventDefault();
        event.stopPropagation();
        runSave();
      }, true);

      button.addEventListener('click', (event) => {
        event.preventDefault();
        event.stopPropagation();
        runSave();
      }, true);
    }

    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', function() {
        bindQuickActionFallback();
        bindSheetOpenFallback();
        bindSheetBackdropCloseFallback();
        bindSiteNameSaveFallback();
        bindPhoneSaveFallback();
        bindEmailSaveFallback();
        bindPromptpaySaveFallback();
        syncSettingsLayout();
      });
    } else {
      bindQuickActionFallback();
      bindSheetOpenFallback();
      bindSheetBackdropCloseFallback();
      bindSiteNameSaveFallback();
      bindPhoneSaveFallback();
      bindEmailSaveFallback();
      bindPromptpaySaveFallback();
      syncSettingsLayout();
    }

    // Auto-open sheet if hash is present
    if (window.location.hash && window.location.hash.startsWith('#sheet-')) {
      setTimeout(() => {
        const sheetId = window.location.hash.substring(1);
        const toggleBtn = document.querySelector(`[data-sheet="${sheetId}"]`);
        if (toggleBtn) {
          toggleBtn.click();
        } else {
          // Fallback if no specific trigger row
          const sheetObj = document.getElementById(sheetId);
          if (sheetObj) sheetObj.classList.add('active');
        }
      }, 300);
    }

    window.addEventListener('resize', syncSettingsLayout, { passive: true });
  })();
  
  </script>
</body>
</html>
