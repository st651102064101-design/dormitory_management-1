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
  <link rel="stylesheet" href="settings/apple-settings.css">
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
            <div class="apple-stat-icon blue">🏠</div>
            <div class="apple-stat-value"><?php echo number_format($totalRooms); ?></div>
            <div class="apple-stat-label">ห้องพัก</div>
          </div>
          <div class="apple-stat-card">
            <div class="apple-stat-icon green">👥</div>
            <div class="apple-stat-value"><?php echo number_format($totalTenants); ?></div>
            <div class="apple-stat-label">ผู้เช่า</div>
          </div>
          <div class="apple-stat-card">
            <div class="apple-stat-icon orange">📋</div>
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
