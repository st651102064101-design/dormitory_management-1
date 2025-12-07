<?php
// Expect session already started and $adminName set in including script
$adminName = $_SESSION['admin_name'] ?? $_SESSION['admin_username'] ?? 'Admin';

// ดึงชื่อระบบและการตั้งค่าจาก database
$siteName = 'Sangthian Dormitory';
$logoFilename = 'Logo.jpg';
$themeColor = '#0f172a';
$fontSize = '1';
try {
    require_once __DIR__ . '/../ConnectDB.php';
    $pdo = connectDB();
    
    $settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('site_name', 'logo_filename', 'theme_color', 'font_size')");
    $settings = [];
    foreach ($settingsStmt->fetchAll(PDO::FETCH_ASSOC) as $setting) {
        $settings[$setting['setting_key']] = $setting['setting_value'];
    }
    
    $siteName = $settings['site_name'] ?? $siteName;
    $logoFilename = $settings['logo_filename'] ?? $logoFilename;
    $themeColor = $settings['theme_color'] ?? $themeColor;
    $fontSize = $settings['font_size'] ?? $fontSize;
} catch (Exception $e) {
    // ใช้ค่า default ถ้า database error
}
?>
<style>
  :root {
    --theme-bg-color: <?php echo htmlspecialchars($themeColor, ENT_QUOTES, 'UTF-8'); ?>;
    --font-scale: <?php echo htmlspecialchars($fontSize, ENT_QUOTES, 'UTF-8'); ?>;
  }

  html { font-size: calc(16px * var(--font-scale, 1)); }
  
  /* พื้นหลังหลัก - ใช้ theme color */
  html, body, .app-shell, .app-main, .reports-page {
    background: var(--theme-bg-color) !important;
  }

  /* Smooth animation when switching theme */
  html, body, .app-shell, .app-main, .reports-page,
  aside.app-sidebar, .manage-panel, .card, .panel, .stat-card,
  .report-section, .report-item, .chart-container, .settings-card,
  input, select, textarea, button {
    transition: background-color 0.35s ease, color 0.35s ease,
                border-color 0.35s ease, box-shadow 0.35s ease;
  }

  @keyframes themeFadeIn {
    from { opacity: 0; filter: saturate(0.6); }
    to { opacity: 1; filter: saturate(1); }
  }

  body.theme-fade {
    animation: themeFadeIn 0.45s ease;
  }

  /* Live light mode (no reload) */
  body.live-light,
  body.live-light .app-shell,
  body.live-light .app-main,
  body.live-light.reports-page,
  body.live-light .reports-page {
    background: #ffffff !important;
    color: #111827 !important;
  }

  body.live-light aside.app-sidebar {
    background: #f9fafb !important;
    border-right: 1px solid #e5e7eb !important;
  }

  body.live-light, body.live-light * {
    color: #111827 !important;
  }

  body.live-light .settings-card,
  body.live-light .manage-panel,
  body.live-light .card,
  body.live-light .panel,
  body.live-light .stat-card,
  body.live-light .report-section,
  body.live-light .report-item,
  body.live-light .chart-container,
  body.live-light .booking-stat-card,
  body.live-light .tenant-stat-card,
  body.live-light .expense-stat-card,
  body.live-light .news-stat-card,
  body.live-light .contract-stat-card,
  body.live-light .dashboard-grid .stat-card,
  body.live-light .report-grid .report-item,
  body.live-light .charts-row .chart-container {
    background: #ffffff !important;
    border: 1px solid #e5e7eb !important;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08) !important;
  }

  body.live-light input,
  body.live-light select,
  body.live-light textarea,
  body.live-light button,
  body.live-light .form-control {
    background: #ffffff !important;
    color: #111827 !important;
    border: 1px solid #e5e7eb !important;
  }

  /* User icon always white */
  .sidebar-footer .avatar svg *,
  .sidebar-footer .rail-user svg *,
  .sidebar-footer .rail-logout .app-nav-icon,
  .sidebar-footer .user-row .app-nav-icon {
    color: #ffffff !important;
    fill: currentColor !important;
  }

  body.live-light .status-badge.time-fresh { background:#d1fae5 !important; color:#065f46 !important; }
  body.live-light .status-badge.time-warning { background:#fef3c7 !important; color:#92400e !important; }
  body.live-light .status-badge.time-danger { background:#fee2e2 !important; color:#b91c1c !important; }
  body.live-light .status-badge.time-neutral { background:#e5e7eb !important; color:#111827 !important; }

  /* Soft fade animation (no overlay) */
  @keyframes themeSoftFade {
    from { opacity: 0.6; filter: blur(1px) saturate(0.8); }
    to { opacity: 1; filter: blur(0) saturate(1); }
  }

  body.theme-softfade {
    animation: themeSoftFade 0.45s ease;
  }
  
  /* Sidebar - ใช้ theme color */
  aside.app-sidebar {
    background: var(--theme-bg-color) !important;
  }
  
  /* ปรับสีตัวหนังสือตามความสว่างของพื้นหลัง */
  <?php
  // คำนวณความสว่างของสี
  $hex = ltrim($themeColor, '#');
  if (strlen($hex) === 6) {
      $r = hexdec(substr($hex, 0, 2));
      $g = hexdec(substr($hex, 2, 2));
      $b = hexdec(substr($hex, 4, 2));
      $brightness = (($r * 299) + ($g * 587) + ($b * 114)) / 1000;
      $isLight = $brightness > 155;
  } else {
      $isLight = false;
  }
  
  if ($isLight): ?>
  /* ===== LIGHT MODE - พื้นหลังสว่าง ===== */
  
  /* พื้นหลังทั้งหมดเป็นสีขาว */
  html, body {
    background: #ffffff !important;
  }
  
  .app-shell,
  .app-main,
  .reports-page,
  body.reports-page {
    background: #ffffff !important;
  }
  
  /* Sidebar สีขาว */
  aside.app-sidebar {
    background: #f9fafb !important;
    border-right: 1px solid #e5e7eb !important;
  }
  
  /* ตัวหนังสือทั้งหมดเป็นสีดำ */
  body, body *,
  .app-main, .app-main *,
  .reports-page, .reports-page *,
  h1, h2, h3, h4, h5, h6, p, span, div, label, a,
  .section-header h1,
  .settings-card h3,
  .settings-card label,
  .settings-card small,
  aside.app-sidebar,
  aside.app-sidebar *,
  nav a, .sidebar-nav a, details summary,
  .team-meta .name, .team-meta .role,
  .manage-panel *,
  .settings-card * {
    color: #111827 !important;
  }
  
  /* Cards และ Panels สีขาว/เทาอ่อน */
  .settings-card,
  .manage-panel,
  .card,
  .panel {
    background: #ffffff !important;
    border: 1px solid #e5e7eb !important;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1) !important;
  }
  
  /* Stat Cards สีขาว */
  .booking-stat-card,
  .tenant-stat-card,
  .expense-stat-card,
  .news-stat-card,
  .contract-stat-card,
  .stat-card {
    background: #ffffff !important;
    border: 1px solid #e5e7eb !important;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1) !important;
  }
  
  .booking-stat-card *,
  .tenant-stat-card *,
  .expense-stat-card *,
  .news-stat-card *,
  .contract-stat-card *,
  .stat-card * {
    color: #111827 !important;
  }
  
  /* Form Elements สีขาว */
  input[type="text"],
  input[type="email"],
  input[type="number"],
  input[type="date"],
  input[type="password"],
  input[type="file"],
  input[type="color"],
  select,
  textarea {
    background: #ffffff !important;
    border: 1px solid #d1d5db !important;
    color: #111827 !important;
  }
  
  input::placeholder,
  textarea::placeholder {
    color: #9ca3af !important;
  }
  
  /* Buttons */
  .quick-color,
  button:not(.btn-save):not([type="submit"]) {
    background: #f3f4f6 !important;
    border: 1px solid #d1d5db !important;
    color: #111827 !important;
  }
  
  /* Sidebar Navigation */
  nav a, 
  .sidebar-nav a,
  details summary {
    color: #374151 !important;
  }
  
  nav a:hover,
  .sidebar-nav a:hover,
  details summary:hover {
    background: #f3f4f6 !important;
    color: #111827 !important;
  }
  
  nav a.active,
  .sidebar-nav a.active {
    background: #dbeafe !important;
    color: #1e40af !important;
  }
  
  /* Tables */
  table {
    background: #ffffff !important;
    border: 1px solid #e5e7eb !important;
  }
  
  table thead {
    background: #f9fafb !important;
  }
  
  table thead th {
    color: #111827 !important;
    border-bottom: 1px solid #e5e7eb !important;
  }
  
  table tbody tr {
    background: #ffffff !important;
    border-bottom: 1px solid #f3f4f6 !important;
  }
  
  table tbody tr:hover {
    background: #f9fafb !important;
  }
  
  table tbody td {
    color: #111827 !important;
  }
  
  /* Header */
  header {
    background: #ffffff !important;
    border-bottom: 1px solid #e5e7eb !important;
  }
  
  header h2,
  header * {
    color: #111827 !important;
  }
  
  #sidebar-toggle {
    color: #111827 !important;
  }
  
  #sidebar-toggle svg {
    stroke: #111827 !important;
  }
  
  /* Modals */
  .modal-content,
  .booking-modal-content,
  .confirm-modal {
    background: #ffffff !important;
    border: 1px solid #e5e7eb !important;
    color: #111827 !important;
  }
  
  /* Logo Preview */
  .logo-preview,
  .preview-area {
    background: #f9fafb !important;
    border: 1px solid #e5e7eb !important;
  }
  
  /* Team Avatar Section - User Icon */
  .team-switcher,
  aside.app-sidebar .team-switcher {
    background: #ffffff !important;
    border-bottom: 1px solid #e5e7eb !important;
  }
  
  /* Simple avatar without border */
  .team-avatar,
  aside.app-sidebar .team-avatar,
  .app-sidebar .team-avatar {
    position: relative;
    width: 120px !important;
    height: 120px !important;
    border-radius: 12px !important;
    background: transparent !important;
    border: none !important;
    padding: 0 !important;
    margin: 0 auto !important;
  }
  
  .team-avatar-img,
  aside.app-sidebar .team-avatar-img,
  .app-sidebar .team-avatar-img {
    width: 120px !important;
    height: 120px !important;
    border-radius: 12px !important;
    background: #ffffff !important;
    border: 1px solid rgba(0,0,0,0.08) !important;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    object-fit: cover !important;
  }
  
  .team-meta .name,
  .team-meta .role {
    color: #111827 !important;
  }
  
  .team-info {
    background: #ffffff !important;
  }
  
  /* ปุ่มออกจากระบบ */
  .logout-btn,
  aside.app-sidebar .logout-btn,
  .app-sidebar .logout-btn {
    background: #ffffff !important;
    background-color: #ffffff !important;
    border: 1px solid #e5e7eb !important;
    color: #111827 !important;
  }
  
  .logout-btn:hover,
  aside.app-sidebar .logout-btn:hover {
    background: #f3f4f6 !important;
    background-color: #f3f4f6 !important;
    color: #111827 !important;
  }
  
  .logout-btn .app-nav-icon,
  .logout-btn .app-nav-label,
  aside.app-sidebar .logout-btn .app-nav-icon,
  aside.app-sidebar .logout-btn .app-nav-label {
    color: #111827 !important;
  }
  
  /* Dashboard Cards */
  .dashboard-grid .card {
    background: #ffffff !important;
    border: 1px solid #e5e7eb !important;
  }
  
  /* Color Preview */
  .color-preview {
    color: #111827 !important;
    border: 1px solid #d1d5db !important;
  }
  
  /* Expense Stats Cards */
  .expense-stats,
  .expense-stat-card,
  .booking-stats,
  .room-stats {
    background: #ffffff !important;
  }
  
  .expense-stat-card,
  .booking-stat-card,
  .room-stat-card {
    background: #ffffff !important;
    border: 1px solid #e5e7eb !important;
  }
  
  .expense-stat-card *,
  .booking-stat-card *,
  .room-stat-card * {
    color: #111827 !important;
  }
  
  /* Available Rooms Grid */
  .available-rooms-grid,
  .rooms-grid {
    background: transparent !important;
  }
  
  .room-card,
  .available-room-card {
    background: #ffffff !important;
    border: 1px solid #e5e7eb !important;
    color: #111827 !important;
  }
  
  .room-card *,
  .available-room-card * {
    color: #111827 !important;
  }
  
  /* Room Stats */
  .room-stats .stat-card {
    background: #ffffff !important;
    border: 1px solid #e5e7eb !important;
  }
  
  .room-stats .stat-value,
  .room-stats h3,
  .room-stats p {
    color: #111827 !important;
  }
  
  /* Dashboard Cards - เฉพาะเจาะจง */
  body.reports-page .dashboard-grid,
  body.reports-page .dashboard-grid > div,
  body.reports-page .dashboard-grid .card,
  body.reports-page .chart-container,
  body.reports-page .stat-overview,
  body.reports-page .overview-card,
  body.reports-page section,
  body.reports-page section > div {
    background: #ffffff !important;
    background-color: #ffffff !important;
    border: 1px solid #e5e7eb !important;
  }
  
  body.reports-page .dashboard-grid *,
  body.reports-page .chart-container *,
  body.reports-page .stat-overview *,
  body.reports-page .overview-card *,
  body.reports-page section *,
  body.reports-page section h2,
  body.reports-page section h3,
  body.reports-page section h4,
  body.reports-page section p,
  body.reports-page section span {
    color: #111827 !important;
  }
  
  /* Chart Cards */
  body.reports-page .chart-card,
  body.reports-page canvas {
    background: #ffffff !important;
    background-color: #ffffff !important;
  }
  
  /* Override gradient backgrounds */
  body.reports-page [style*="background: linear-gradient"],
  body.reports-page [style*="background:linear-gradient"],
  body.reports-page div[style*="background"],
  body.reports-page section[style*="background"] {
    background: #ffffff !important;
    background-color: #ffffff !important;
  }
  
  <?php endif; ?>
  
  <?php if ($isLight): ?>
  <script>
  // Force override inline styles for Light Mode
  document.addEventListener('DOMContentLoaded', function() {
    const allElements = document.querySelectorAll('section, div, .dashboard-grid, .chart-container');
    allElements.forEach(el => {
      const style = el.getAttribute('style');
      if (style && (style.includes('background') || style.includes('linear-gradient'))) {
        el.style.setProperty('background', '#ffffff', 'important');
        el.style.setProperty('background-color', '#ffffff', 'important');
        el.style.setProperty('color', '#111827', 'important');
      }
    });
  });
  </script>
  <?php endif; ?>
  details summary {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 0.75rem !important;
    margin: 0 !important;
    transition: all 0.3s ease;
  }
  details summary .app-nav-icon {
    flex-shrink: 0;
    width: 1.25rem;
    height: 1.25rem;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
  }
  details summary .chev {
    transition: transform 0.3s ease;
  }
  details summary .summary-label {
    transition: opacity 0.3s ease, transform 0.3s ease;
  }
  details[open] summary .chev {
    transform: rotate(90deg);
  }
  .team-switcher {
    width: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 0.5rem !important;
    gap: 0.75rem;
    transition: all 0.3s ease;
  }
  .team-avatar {
    width: 120px;
    height: 120px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 !important;
    overflow: hidden;
    margin: 0 auto !important;
    border-radius: 16px;
    background: transparent;
    box-shadow: none;
    transition: width 0.3s ease, height 0.3s ease, opacity 0.3s ease;
  }
  .team-avatar-img {
    width: 120px !important;
    height: 120px !important;
    border-radius: 12px;
    box-shadow: 0 10px 24px rgba(0,0,0,0.12);
    object-fit: cover;
    background: #ffffff;
    border: 1px solid rgba(255,255,255,0.6);
    transition: all 0.3s ease;
  } 
  .team-meta {
    display: block;
    text-align: center;
    width: 100%;
    padding: 0.75rem 0.5rem 0 0.5rem;
    transition: opacity 0.3s ease, transform 0.3s ease;
  }
  .team-meta .name {
    font-size: 1rem;
    font-weight: 700;
    color: #e2e8f0;
    line-height: 1.4;
    margin-bottom: 0.25rem;
    transition: all 0.3s ease;
  }
  .subitem {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    padding-left: 0.5rem;
    transition: all 0.3s ease;
  }
  /* Tighten nav vertical spacing */
  .app-nav {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    margin: 0;
    padding: 0;
  }
  .app-nav .group {
    gap: 0.2rem;
  }
  /* Dashboard button: tighter background around icon/text */
  .app-nav .group:first-child .subitem {
    padding: 0rem 0.6rem;
    border-radius: 12px;
    min-height: auto;
  }
  .app-nav .group:first-child .subitem .app-nav-icon {
    width: 2.5rem;
    height: 2.5rem;
    border-radius: 12px;
    justify-content: center;
    align-items: center;
  }
  /* Center the gear (manage) and palette (system settings) icons */
  .app-nav-icon--management,
  a[href="system_settings.php"] .app-nav-icon {
    width: 2.5rem;
    height: 2.5rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 12px;
    margin: 0;
    line-height: 1;
    flex-shrink: 0;
  }
  .subitem .app-nav-icon {
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
  }
  .group {
    display: flex;
    flex-direction: column;
    align-items: stretch;
    width: 100%;
  }
  
  /* Active menu item styles */
  .app-nav a.active,
  .app-nav a.subitem.active {
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.15), rgba(37, 99, 235, 0.1));
    border-left: 3px solid #3b82f6;
    color: #60a5fa;
    font-weight: 600;
  }
  
  .app-nav a.active .app-nav-icon,
  .app-nav a.subitem.active .app-nav-icon {
    transform: scale(1.1);
  }
  
  /* Sidebar collapsed state - icon centered */
  aside.sidebar-collapsed {
    display: flex !important;
    flex-direction: column !important;
    align-items: center !important;
  }
  aside.sidebar-collapsed .team-switcher {
    width: auto !important;
      gap: 0.5rem !important;
    padding: 0.5rem 0 !important;
  }
  aside.sidebar-collapsed .team-avatar {
      width: 48px !important;
      height: 48px !important;
      padding: 0 !important;
      margin: 0 auto !important;
  }
    aside.sidebar-collapsed .team-avatar-img {
      width: 48px !important;
      height: 48px !important;
      object-fit: cover !important;
    }
  aside.sidebar-collapsed .team-meta {
    display: none !important;
  }
  
  /* Also apply to .app-sidebar.collapsed */
  .app-sidebar.collapsed .team-switcher {
    width: auto !important;
      gap: 0.5rem !important;
    padding: 0.5rem 0 !important;
  }
  .app-sidebar.collapsed .team-avatar {
      width: 48px !important;
      height: 48px !important;
      padding: 0 !important;
      margin: 0 auto !important;
  }
    .app-sidebar.collapsed .team-avatar-img {
      width: 48px !important;
      height: 48px !important;
      object-fit: cover !important;
    }
  .app-sidebar.collapsed .team-meta {
    display: none !important;
  }
  aside.sidebar-collapsed .group {
    width: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
  }
  aside.sidebar-collapsed .subitem {
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    width: 100% !important;
    height: auto !important;
    padding: 0.5rem 0 !important;
    margin: 0 !important;
    gap: 0 !important;
  }
  aside.sidebar-collapsed .subitem .app-nav-icon {
    width: auto !important;
    height: auto !important;
    margin: 0 !important;
    padding: 0 !important;
  }
  aside.sidebar-collapsed .subitem .app-nav-label {
    display: none;
  }
  
  /* Mobile Responsive */
  @media (max-width: 1024px) {
    html, body {
      width: 100%;
      overflow-x: hidden;
    }
    
    .app-shell {
      flex-direction: row !important;
      width: 100%;
      overflow-x: hidden;
    }
    
    /* Mobile: sidebar as fixed overlay with slide animation */
    .app-sidebar {
      position: fixed !important;
      top: 0 !important;
      left: 0 !important;
      height: 100vh !important;
      width: 240px !important;
      z-index: 1000 !important;
      background: #0b162a !important;
      transform: translateX(-100%) !important;
      transition: transform 0.35s ease !important;
      will-change: transform;
      box-shadow: 4px 0 24px rgba(0,0,0,0.6) !important;
      padding: 1.25rem 0.75rem !important;
      overflow: auto !important;
      flex-shrink: 0 !important;
      margin: 0 !important;
    }
    
    /* Show team avatar/logo on mobile/tablet */
    .team-switcher {
      display: flex !important;
      flex-direction: column !important;
      align-items: center !important;
      width: 100% !important;
      margin-bottom: 1rem !important;
    }
    
    .team-avatar {
      display: flex !important;
        width: 120px !important;
        height: 120px !important;
        margin: 0 auto !important;
    }
    
    .team-avatar-img {
      display: block !important;
        width: 120px !important;
        height: 120px !important;
        object-fit: cover !important;
        border-radius: 12px !important;
    }

    .team-meta {
      display: block !important;
      text-align: center !important;
      margin-top: 0.75rem !important;
      width: 100% !important;
    }

    .team-meta .name {
        font-size: 1rem !important;
        font-weight: 700 !important;
      color: #e2e8f0 !important;
        line-height: 1.4 !important;
    }
    
    /* When mobile-open class is applied, slide in from left */
    .app-sidebar.mobile-open {
      transform: translateX(0) !important;
    }
    
    /* Alternative selector for when body.sidebar-open is used */
    body.sidebar-open .app-sidebar {
      transform: translateX(0) !important;
    }
    
    /* Reset collapsed styles that might conflict */
    .app-sidebar.collapsed {
      all: revert !important;
      position: fixed !important;
      top: 0 !important;
      left: 0 !important;
      height: 100vh !important;
      width: 240px !important;
      z-index: 1000 !important;
      background: #0b162a !important;
      transform: translateX(-100%) !important;
      transition: transform 0.35s ease !important;
      will-change: transform;
      box-shadow: 4px 0 24px rgba(0,0,0,0.6) !important;
      padding: 1.25rem 0.75rem !important;
      overflow: auto !important;
      display: flex !important;
      flex-direction: column !important;
      gap: 0.5rem !important;
      margin: 0 !important;
    }
    
    .app-sidebar.collapsed.mobile-open {
      transform: translateX(0) !important;
    }
    
    /* Content area takes remaining space */
    .app-main {
      margin: 0 !important;
      width: 100vw !important;
      height: auto !important;
      position: relative;
      z-index: 1 !important;
      flex: 1 1 auto !important;
      padding: 1rem !important;
      transition: all 0.3s ease !important;
      box-sizing: border-box !important;
      overflow-x: hidden;
    }
    
    /* Remove margins/padding that cause gaps */
    .app-main section {
      margin: 0 !important;
      padding: 1.25rem !important;
    }
    
    /* Header responsive */
    .app-main header {
      width: 100% !important;
      margin: 0 !important;
      padding: 0.5rem !important;
      box-sizing: border-box;
    }
    
    .app-main header h2 {
      font-size: 1rem !important;
      margin: 0 !important;
      flex: 1;
      min-width: 0;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }
    
    /* Text wrapping to prevent overflow */
    .app-main h1,
    .app-main h2,
    .app-main h3,
    .app-main p,
    .app-main .card,
    .app-main .manage-panel,
    .app-main .section-header,
    .app-main .chart-card,
    .app-main .small-card {
      word-break: break-word;
      overflow-wrap: break-word;
      max-width: 100%;
      margin: 0 !important;
      padding-right: 0 !important;
    }
    
    /* Global header styles */
    header {
      width: 100% !important;
      margin: 0 !important;
      padding: 0.5rem !important;
      box-sizing: border-box;
    }
    
    header h2 {
      font-size: 1rem !important;
      margin: 0 !important;
    }
    
    /* Mobile overlay - dark background when sidebar open */
    body.sidebar-open::before {
      content: '';
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(0, 0, 0, 0.5);
      z-index: 999;
    }
  }
  
  @media (max-width: 480px) {
    .team-switcher {
      padding: 0.25rem !important;
    }
    .subitem {
      padding-left: 0.5rem;
      font-size: 0.9rem;
    }
    details summary {
      padding: 0.4rem 0.5rem !important;
      font-size: 0.9rem;
    }
  }</style>
<script>
  // Force reset collapsed on mobile IMMEDIATELY before CSS applies
  if (window.innerWidth <= 1024) {
     document.write('<style>.app-sidebar.collapsed { all: revert !important; width: 240px !important; } .app-sidebar.collapsed .app-nav-label, .app-sidebar.collapsed .summary-label, .app-sidebar.collapsed .chev { all: revert !important; } .app-sidebar.collapsed .team-avatar { width: 120px !important; height: 120px !important; padding: 0 !important; margin: 0 auto !important; } .app-sidebar.collapsed .team-avatar-img { width: 120px !important; height: 120px !important; object-fit: cover !important; } .app-sidebar.collapsed .team-meta { display: block !important; text-align: center !important; padding-top: 0.75rem !important; }</style>');
  }
</script>
<aside class="app-sidebar">
  <div class="">
    <div class="team-avatar">
      <!-- Project logo from database -->
      <img src="/Dormitory_Management/Assets/Images/<?php echo htmlspecialchars($logoFilename); ?>" alt="Logo" class="team-avatar-img" />
    </div>
    <div class="team-meta">
      <div class="name"><?php echo htmlspecialchars($siteName); ?></div>
    </div>
  </div>

  <nav class="app-nav" aria-label="Main navigation">
    <div class="group">
      <a class="subitem" href="dashboard.php"><span class="app-nav-icon" aria-hidden="true">📊</span><span class="app-nav-label">แดชบอร์ด</span></a>
    </div>
  </nav>

  <nav class="app-nav" aria-label="Reports navigation">
    <div class="group">
      <details open>
        <summary>
          <span class="app-nav-icon app-nav-icon--management" aria-hidden="true">⚙️</span>
          <span class="summary-label">จัดการ</span>
          <span class="chev" style="margin-left:auto">›</span>
        </summary>
        <!-- manage_stay.php removed; link intentionally omitted -->
        <a class="" href="manage_news.php"><span class="app-nav-icon" aria-hidden="true">📰</span><span class="app-nav-label">ข่าวประชาสัมพันธ์</span></a>
        <a class="" href="manage_rooms.php"><span class="app-nav-icon" aria-hidden="true">🛏️</span><span class="app-nav-label">ห้องพัก</span></a>
        <a class="" href="manage_tenants.php"><span class="app-nav-icon" aria-hidden="true">👥</span><span class="app-nav-label">ผู้เช่า</span></a>
        <a class="" href="manage_booking.php"><span class="app-nav-icon" aria-hidden="true">📅</span><span class="app-nav-label">จองห้องพัก</span></a>
        <a class="" href="manage_contracts.php"><span class="app-nav-icon" aria-hidden="true">📝</span><span class="app-nav-label">จัดการสัญญา</span></a>
        <a class="" href="manage_expenses.php"><span class="app-nav-icon" aria-hidden="true">💰</span><span class="app-nav-label">ค่าใช้จ่าย</span></a>
        <a class="" href="manage_repairs.php"><span class="app-nav-icon" aria-hidden="true">🛠️</span><span class="app-nav-label">แจ้งซ่อม</span></a>
      </details>
    </div>

    <div class="group">
      <a class="subitem" href="system_settings.php"><span class="app-nav-icon" aria-hidden="true">🎨</span><span class="app-nav-label">จัดการระบบ</span></a>
    </div>
  </nav>

  <div style="height:0.2rem"></div>

  <div class="sidebar-footer">
    <div class="user-row">
      <div class="avatar">
        <!-- user svg icon -->
        <svg width="36" height="36" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
          <rect width="24" height="24" rx="6" fill="currentColor" opacity="0.06" />
          <path d="M12 12c2.761 0 5-2.239 5-5s-2.239-5-5-5-5 2.239-5 5 2.239 5 5 5z" fill="currentColor" />
          <path d="M2 20c0-3.314 2.686-6 6-6h8c3.314 0 6 2.686 6 6v1H2v-1z" fill="currentColor" />
        </svg>
      </div>
      <div class="user-meta">
        <div class="name"><?php echo htmlspecialchars($adminName, ENT_QUOTES, 'UTF-8'); ?></div>
        <div class="email"><?php echo htmlspecialchars($_SESSION['admin_username'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
      </div>
    </div>
    <div style="margin-top:0.6rem">
      <form action="../logout.php" method="post" data-allow-submit>
        <button type="submit" class="logout-btn" aria-label="Log out">
          <span class="app-nav-icon" aria-hidden="true">⎋</span>
          <span class="app-nav-label">ออกจากระบบ</span>
        </button>
      </form>
    </div>

    <!-- Rail shown only when sidebar is collapsed: icon-only controls -->
    <div class="sidebar-rail">
      <div class="rail-user" title="<?php echo htmlspecialchars($adminName, ENT_QUOTES, 'UTF-8'); ?>">
        <span class="app-nav-icon" aria-hidden="true">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M12 12c2.761 0 5-2.239 5-5s-2.239-5-5-5-5 2.239-5 5 2.239 5 5 5z" fill="currentColor" />
            <path d="M2 20c0-3.314 2.686-6 6-6h8c3.314 0 6 2.686 6 6v1H2v-1z" fill="currentColor" />
          </svg>
        </span>
      </div>
      <form action="../logout.php" method="post" class="rail-logout" data-allow-submit>
        <button type="submit" class="app-nav-icon" aria-label="Log out">⎋</button>
      </form>
    </div>
  </div>
</aside>

<script>
  // Fade-in animation on theme change/page load
  document.addEventListener('DOMContentLoaded', function() {
    document.body.classList.add('theme-fade');
    setTimeout(() => document.body.classList.remove('theme-fade'), 500);
  });
</script>
<script>
(function() {
  const sidebar = document.querySelector('.app-sidebar');
  const toggleBtn = document.getElementById('sidebar-toggle');
  
  // Toggle sidebar collapse on desktop
  if (toggleBtn) {
    toggleBtn.addEventListener('click', function(e) {
      e.preventDefault();
      e.stopPropagation();
      
      // On desktop: toggle collapsed state
      if (window.innerWidth > 1024) {
        // Force browser reflow to ensure transitions work
        sidebar.style.transition = 'none';
        void sidebar.offsetHeight; // Trigger reflow
        sidebar.style.transition = '';
        
        sidebar.classList.toggle('collapsed');
        
        // Save state to localStorage
        localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
        
        console.log('Sidebar toggled:', sidebar.classList.contains('collapsed') ? 'collapsed' : 'expanded');
      } 
      // On mobile: toggle mobile-open
      else {
        sidebar.classList.toggle('mobile-open');
        document.body.classList.toggle('sidebar-open');
      }
    });
  }
  
  // Restore sidebar state on page load (desktop only)
  const savedState = localStorage.getItem('sidebarCollapsed');
  if (savedState === 'true' && window.innerWidth > 1024) {
    sidebar.classList.add('collapsed');
  }
  
  // Set active menu item based on current page
  function setActiveMenu() {
    const currentPage = window.location.pathname.split('/').pop();
    const menuLinks = document.querySelectorAll('.app-nav a');
    
    menuLinks.forEach(link => {
      const href = link.getAttribute('href');
      if (href && (href === currentPage || href.endsWith('/' + currentPage))) {
        link.classList.add('active');
        
        // Open parent details if inside a collapsible section
        const parentDetails = link.closest('details');
        if (parentDetails) {
          parentDetails.setAttribute('open', '');
        }
      }
    });
  }
  
  // Run on page load
  setActiveMenu();
  
  // Close sidebar when clicking overlay
  document.addEventListener('click', function(e) {
      if (window.innerWidth <= 1024 && 
        sidebar.classList.contains('mobile-open') && 
        !sidebar.contains(e.target) &&
        e.target !== toggleBtn) {
      sidebar.classList.remove('mobile-open');
      document.body.classList.remove('sidebar-open');
    }
  });
  
  // Handle window resize
  window.addEventListener('resize', function() {
    if (window.innerWidth > 1024) {
      sidebar.classList.remove('mobile-open');
      document.body.classList.remove('sidebar-open');
    } else {
      // On mobile, remove collapsed state
      sidebar.classList.remove('collapsed');
    }
  });
})();
</script>
