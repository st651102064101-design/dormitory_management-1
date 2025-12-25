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
<html lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?> - จัดการระบบ</title>
  <link rel="icon" type="image/jpeg" href="../Assets/Images/<?php echo htmlspecialchars($logoFilename, ENT_QUOTES, 'UTF-8'); ?>">
  <link rel="stylesheet" href="../Assets/Css/animate-ui.css">
  <link rel="stylesheet" href="../Assets/Css/main.css">
  <link rel="stylesheet" href="../Assets/Css/lottie-icons.css">
  <link rel="stylesheet" href="settings/apple-settings.css">
  <!-- Explicit Prompt font load to ensure Thai sans-serif (no serifs) -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    /* Force Prompt everywhere (Thai sans-serif, no serifs) */
    html, body, * {
      font-family: 'Prompt', 'Noto Sans Thai', 'Segoe UI', 'Helvetica Neue', sans-serif !important;
      -webkit-font-smoothing: antialiased;
      -moz-osx-font-smoothing: grayscale;
    }
  </style>
</head>
<body class="reports-page apple-settings-page" data-theme-color="<?php echo htmlspecialchars($themeColor); ?>">
  <div class="app-shell">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    
    <!-- Floating Menu Button -->
    <button class="apple-menu-btn" id="apple-menu-btn" aria-label="เปิดเมนู">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
        <line x1="3" y1="6" x2="21" y2="6"></line>
        <line x1="3" y1="12" x2="21" y2="12"></line>
        <line x1="3" y1="18" x2="21" y2="18"></line>
      </svg>
    </button>
    
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
          <img src="../Assets/Images/<?php echo htmlspecialchars($logoFilename); ?>" alt="Logo" class="apple-profile-avatar">
          <div class="apple-profile-info">
            <h2 class="apple-profile-name"><?php echo htmlspecialchars($siteName); ?></h2>
            <p class="apple-profile-detail">ผู้ดูแลระบบ: <?php echo htmlspecialchars($_SESSION['admin_username']); ?></p>
          </div>
          <span class="apple-profile-chevron">›</span>
        </div>
        
        <!-- Stats -->
        <div class="apple-stats-grid">
          <div class="apple-stat-card">
            <div class="lottie-icon blue">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="icon-animated"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            </div>
            <div class="apple-stat-value"><?php echo number_format($totalRooms); ?></div>
            <div class="apple-stat-label">ห้องพัก</div>
          </div>
          <div class="apple-stat-card">
            <div class="lottie-icon green">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="icon-animated"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </div>
            <div class="apple-stat-value"><?php echo number_format($totalTenants); ?></div>
            <div class="apple-stat-label">ผู้เช่า</div>
          </div>
          <div class="apple-stat-card">
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
        <?php include __DIR__ . '/settings/section_system.php'; ?>
        
      </div>
    </main>
  </div>
  
  <!-- Scripts -->
  <script src="../Assets/Javascript/toast-notification.js"></script>
  <script src="../Assets/Javascript/animate-ui.js"></script>
  <script src="settings/apple-settings.js"></script>
  <script>
  // Direct sidebar toggle - guaranteed to work
  (function() {
    const menuBtn = document.getElementById('apple-menu-btn');
    const sidebar = document.querySelector('.app-sidebar');
    let overlay = document.querySelector('.sidebar-overlay');
    
    if (!overlay) {
      overlay = document.createElement('div');
      overlay.className = 'sidebar-overlay';
      document.body.appendChild(overlay);
    }
    
    if (menuBtn && sidebar) {
      menuBtn.onclick = function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const isOpen = sidebar.classList.contains('mobile-open');
        
        if (isOpen) {
          sidebar.classList.remove('mobile-open');
          document.body.classList.remove('sidebar-open');
          overlay.classList.remove('active');
          menuBtn.classList.remove('active');
        } else {
          sidebar.classList.add('mobile-open');
          document.body.classList.add('sidebar-open');
          overlay.classList.add('active');
          menuBtn.classList.add('active');
        }
      };
      
      overlay.onclick = function() {
        sidebar.classList.remove('mobile-open');
        document.body.classList.remove('sidebar-open');
        overlay.classList.remove('active');
        menuBtn.classList.remove('active');
      };
    }
  })();
  </script>
</body>
</html>
