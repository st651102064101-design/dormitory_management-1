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

  /* Uniform icon sizing for nav, footer, and rails */
  .app-nav-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.8rem;
    height: 1.8rem;
    font-size: 1.1rem;
    line-height: 1;
    flex-shrink: 0;
    text-align: center;
  }
  
  /* Dashboard and Manage summary styling */
  #nav-dashboard > summary,
  #nav-management > summary {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.6rem 0.85rem;
    margin: 0.1rem 0;
    background: transparent;
    border: 1px solid transparent;
    border-radius: 12px;
    transition: all 0.3s ease;
    list-style: none;
  }
  
  #nav-dashboard > summary .summary-link,
  #nav-management > summary .summary-link {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    flex: 1;
    text-decoration: none;
    color: inherit;
    cursor: pointer;
  }
  
  #nav-dashboard > summary .summary-link:hover,
  #nav-management > summary .summary-link:hover {
    opacity: 0.8;
  }
  
  /* Dashboard and Manage icons - ensure consistent sizing */
  #nav-dashboard .summary-link .app-nav-icon,
  #nav-management .summary-link .app-nav-icon {
    width: 1.8rem;
    height: 1.8rem;
    font-size: 1.1rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    margin: 0;
  }
  
  /* Add margin only when sidebar is NOT collapsed */
  aside.app-sidebar:not(.collapsed) .app-nav-icon {
    margin-right: 0.4rem;
  }

  /* Ensure icons stay square in collapsed rail just like user/logout */
  aside.sidebar-collapsed .app-nav-icon,
  .app-sidebar.collapsed .app-nav-icon {
    width: 2.25rem !important;
    height: 2.25rem !important;
    min-width: 2.25rem !important;
    min-height: 2.25rem !important;
    font-size: 1.1rem !important;
    margin: 0 !important;
    padding: 0 !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
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
    scrollbar-width: none;
    -ms-overflow-style: none;
  }
  aside.app-sidebar::-webkit-scrollbar { display: none; }
  
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
    justify-content: flex-start;
    gap: 0.35rem;
    padding: 0.6rem 2.5rem 0.6rem 0.85rem !important;
    margin: 0 !important;
    transition: all 0.3s ease;
    background: transparent;
    border: 1px solid transparent;
    border-radius: 12px;
    cursor: pointer;
    position: relative;
    width: 100%;
    min-height: 2.25rem;
  }
  
  /* ลิงก์ภายใน summary */
  details summary .summary-link {
    display: flex;
    align-items: center;
    justify-content: flex-start;
    gap: 0.35rem;
    text-decoration: none;
    color: inherit;
    padding: 0;
    margin: 0;
    flex: 1;
  }
  
  details summary .summary-link:hover {
    text-decoration: none;
    opacity: 0.8;
  }
  
  /* ข้อความธรรมดาใน summary */
  details summary .summary-text {
    display: flex;
    align-items: center;
    justify-content: flex-start;
    gap: 0.35rem;
  }
  
  details summary .app-nav-icon {
    width: 1.8rem;
    height: 1.8rem;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    transition: all 0.3s ease;
  }
  
  /* Override for dashboard and management icons - force perfect centering (only when sidebar is open) */
  aside.app-sidebar:not(.collapsed) details summary .app-nav-icon {
    width: 1.8rem !important;
    height: 1.8rem !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    text-align: center !important;
    padding: 0 !important;
    margin: 0 !important;
    line-height: 1 !important;
    border-radius: 12px !important;
    background: transparent !important;
    font-size: 1.1rem !important;
    flex-shrink: 0 !important;
  }
  details summary .chev {
    cursor: pointer;
    padding: 0.5rem 0.65rem;
    position: absolute;
    right: 0.35rem;
    font-size: 1.2rem;
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 2rem;
    min-height: 2rem;
  }
  
  details summary .summary-label {
    transition: opacity 0.3s ease, transform 0.3s ease;
    font-size: 0.95rem;
    white-space: nowrap;
    flex: 1;
  }
  details[open] summary .chev {
    transform: rotate(90deg);
    transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1), background 0.2s ease;
  }
  
  summary .chev {
    transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1), background 0.2s ease;
  }
  
  /* Hide all dropdown items by default - completely invisible */
  details > a {
    display: none !important;
    opacity: 0;
    pointer-events: none;
  }
  
  /* Show dropdown items only when details[open] */
  details[open] > a {
    display: block !important;
    opacity: 1;
    pointer-events: auto;
    animation: slideDown 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    animation-fill-mode: both;
  }
  
  /* Staggered animations for each dropdown item */
  details[open] > a:nth-child(2) {
    animation-delay: 0.05s;
  }
  
  details[open] > a:nth-child(3) {
    animation-delay: 0.1s;
  }
  
  details[open] > a:nth-child(4) {
    animation-delay: 0.15s;
  }
  
  details[open] > a:nth-child(5) {
    animation-delay: 0.2s;
  }
  
  details[open] > a:nth-child(6) {
    animation-delay: 0.25s;
  }
  
  details[open] > a:nth-child(7) {
    animation-delay: 0.3s;
  }
  
  details[open] > a:nth-child(8) {
    animation-delay: 0.35s;
  }
  
  details[open] > a:nth-child(9) {
    animation-delay: 0.4s;
  }
  
  details[open] > a:nth-child(10) {
    animation-delay: 0.45s;
  }
  
  details[open] > a:nth-child(11) {
    animation-delay: 0.5s;
  }
  
  details[open] > a:nth-child(12) {
    animation-delay: 0.55s;
  }
  
  /* Animation keyframes for smooth slide-in */
  @keyframes slideDown {
    from {
      opacity: 0;
      transform: translateY(-8px);
    }
    to {
      opacity: 1;
      transform: translateY(0);
    }
  }
  
  /* Animation keyframes for closing */
  @keyframes slideUp {
    from {
      opacity: 1;
      transform: translateY(0);
    }
    to {
      opacity: 0;
      transform: translateY(-8px);
    }
  }
  
  /* Closing animation - hide items immediately */
  details:not([open]) > a {
    display: none !important;
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
    padding: 0.6rem 0.85rem;
    margin: 0.1rem 0;
    background: transparent;
    border: 1px solid transparent;
    border-radius: 12px;
    transition: all 0.3s ease;
  }
  /* Ensure dark-mode nav text is visible */
  .app-nav a,
  details summary,
  .subitem {
    color: #e2e8f0;
  }
  /* Tighten nav vertical spacing */
  aside.app-sidebar {
    display: flex;
    flex-direction: column;
    align-items: stretch;
    gap: 0.25rem;
  }
  .app-sidebar nav {
    margin: 0 !important;
    padding: 0 !important;
  }
  .app-nav {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    margin: 0 !important;
    padding: 0 !important;
    flex: 0 0 auto !important;
    width: 100% !important;
  }
  .app-sidebar nav + nav {
    margin-top: 0rem !important;
  }
  .app-nav .group {
    gap: 0.1rem;
    margin: 0 !important;
    padding: 0 !important;
    border: none !important;
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
    text-align: center;
    font-size: 1.25rem;
  }
  .subitem .app-nav-icon {
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
  }
  /* Base link styling for main nav items */
  .app-nav a {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.6rem 0.85rem;
    margin: 0.1rem 0;
    background: transparent;
    border: 1px solid transparent;
    border-radius: 12px;
    transition: all 0.3s ease;
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
    text-align: center !important;
  }
  aside.sidebar-collapsed * {
    text-align: center !important;
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
    width: 100% !important;
    display: flex !important;
    flex-direction: column !important;
    align-items: stretch !important;
  }
  aside.sidebar-collapsed details {
    width: 100% !important;
  }
  
  /* Make details 100% width */
  details {
    width: 100% !important;
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
  aside.sidebar-collapsed .subitem .app-nav-icon {
    margin-left: auto !important;
    margin-right: auto !important;
  }
  aside.sidebar-collapsed .subitem .app-nav-label {
    display: none;
  }
  
  /* Hide dropdown content when sidebar is collapsed */
  aside.sidebar-collapsed details[open] > :not(summary) {
    display: none !important;
  }
  
  /* Hide all labels when sidebar is collapsed */
  aside.sidebar-collapsed .app-nav-label,
  aside.sidebar-collapsed .summary-label {
    display: none !important;
  }
  
  .app-sidebar.collapsed .app-nav-label,
  .app-sidebar.collapsed .summary-label {
    display: none !important;
  }
  
  /* Show dropdown items as icon-only in vertical column when sidebar is collapsed */
  aside.sidebar-collapsed details,
  .app-sidebar.collapsed details {
    display: flex !important;
    flex-direction: column !important;
    align-items: center !important;
    width: 100% !important;
  }
  
  aside.sidebar-collapsed details > a,
  .app-sidebar.collapsed details > a {
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    padding: 0.6rem 0 !important;
    margin: 0 !important;
    width: 100% !important;
    animation: none !important;
  }
  
  aside.sidebar-collapsed details > a .app-nav-icon,
  .app-sidebar.collapsed details > a .app-nav-icon {
    width: 2rem !important;
    height: 2rem !important;
    font-size: 1.2rem !important;
    margin: 0 !important;
  }
  
  aside.sidebar-collapsed details > a .app-nav-label,
  .app-sidebar.collapsed details > a .app-nav-label {
    display: none !important;
  }
  
  /* Reset summary styling when sidebar is collapsed */
  aside.sidebar-collapsed details summary {
    padding: 0.75rem 0 !important;
    width: 100% !important;
    display: block !important;
    position: relative !important;
    min-height: 3rem !important;
  }
  aside.sidebar-collapsed details summary .summary-link {
    display: block !important;
    width: 100% !important;
    position: relative !important;
    height: 2.5rem !important;
  }
  aside.sidebar-collapsed details summary .app-nav-icon {
    width: 2.5rem !important;
    height: 2.5rem !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    position: absolute !important;
    left: 50% !important;
    top: 50% !important;
    transform: translate(-50%, -50%) !important;
    font-size: 1.5rem !important;
  }
  .app-sidebar.collapsed details summary {
    pointer-events: auto !important;
    cursor: pointer !important;
    padding: 0.75rem 0.5rem !important;
    width: 100% !important;
    display: flex !important;
    align-items: center !important;
    justify-content: space-between !important;
    gap: 0.5rem !important;
    min-height: 3rem !important;
  }
  .app-sidebar.collapsed details summary .summary-link {
    pointer-events: auto !important;
    cursor: pointer !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    flex: 1 !important;
    height: auto !important;
    position: static !important;
  }
  .app-sidebar.collapsed details summary .app-nav-icon {
    width: 2rem !important;
    height: 2rem !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    position: static !important;
    left: auto !important;
    top: auto !important;
    transform: none !important;
    font-size: 1.2rem !important;
  }
  .app-sidebar.collapsed details summary .chev {
    display: none !important;
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
  <div class="" >
    <div class="team-avatar" >
      <!-- Project logo from database -->
      <img src="/Dormitory_Management/Assets/Images/<?php echo htmlspecialchars($logoFilename); ?>" alt="Logo" class="team-avatar-img"  />
    </div>
    <div class="team-meta">
      <div class="name"><?php echo htmlspecialchars($siteName); ?></div>
    </div>
  </div>

  <nav class="app-nav" aria-label="Main navigation" >
    <div class="group" >
      <details id="nav-dashboard" open>
        <summary>
          <a href="dashboard.php" class="summary-link">
            <span class="app-nav-icon" aria-hidden="true">📊</span>
            <span class="summary-label">แดชบอร์ด</span>
          </a>
          <span class="chev" style="font-size: 1.5rem;">›</span>
        </summary>
        <!-- รายงานการจองถูกปิดใช้งานชั่วคราว -->
        <!-- รายงานการเข้าพักถูกปิดใช้งานชั่วคราว -->
        <a class="" href="manage_utility.php"><span class="app-nav-icon" aria-hidden="true">💧</span><span class="app-nav-label" style="font-size: 0.8rem;">รายงานสาธารณูปโภค</span></a>
        <a class="" href="manage_revenue.php"><span class="app-nav-icon" aria-hidden="true">💵</span><span class="app-nav-label">รายงานรายรับ</span></a>
        <a class="" href="report_rooms.php"><span class="app-nav-icon" aria-hidden="true">🏠</span><span class="app-nav-label">รายงานห้องพัก</span></a>
        <a class="" href="report_payments.php"><span class="app-nav-icon" aria-hidden="true">💳</span><span class="app-nav-label">รายงานชำระเงิน</span></a>
        <a class="" href="report_invoice.php"><span class="app-nav-icon" aria-hidden="true">📄</span><span class="app-nav-label">รายงานใบแจ้ง</span></a>
        <a class="" href="report_repairs.php"><span class="app-nav-icon" aria-hidden="true">🔧</span><span class="app-nav-label">รายงานแจ้งซ่อม</span></a>
        <a class="" href="report_news.php"><span class="app-nav-icon" aria-hidden="true">📰</span><span class="app-nav-label">รายงานข่าวสาร</span></a>
        <a class="" href="print_contract.php"><span class="app-nav-icon" aria-hidden="true">🖨️</span><span class="app-nav-label">พิมพ์สัญญา</span></a>
      </details>
    </div>
  </nav>

  <nav class="app-nav" aria-label="Reports navigation">
    <div class="group">
      <details id="nav-management" open>
        <summary>
          <a href="manage.php" class="summary-link">
            <span class="app-nav-icon app-nav-icon--management" aria-hidden="true">⚙️</span>
            <span class="summary-label">จัดการ</span>
          </a>
          <span class="chev chev-toggle" data-target="nav-management" style="cursor:pointer;font-size: 1.5rem;">›</span>
        </summary>
        <!-- manage_stay.php removed; link intentionally omitted -->
        <a class="" href="manage_tenants.php"><span class="app-nav-icon" aria-hidden="true">👥</span><span class="app-nav-label">ผู้เช่า</span></a>
        <a class="" href="manage_booking.php"><span class="app-nav-icon" aria-hidden="true">📋</span><span class="app-nav-label">การจองห้อง</span></a>
        <a class="" href="manage_news.php"><span class="app-nav-icon" aria-hidden="true">📰</span><span class="app-nav-label">ข่าวประชาสัมพันธ์</span></a>
        <a class="" href="manage_rooms.php"><span class="app-nav-icon" aria-hidden="true">🛏️</span><span class="app-nav-label">ห้องพัก</span></a>
        <a class="" href="manage_contracts.php"><span class="app-nav-icon" aria-hidden="true">📝</span><span class="app-nav-label">จัดการสัญญา</span></a>
        <a class="" href="manage_expenses.php"><span class="app-nav-icon" aria-hidden="true">💰</span><span class="app-nav-label">ค่าใช้จ่าย</span></a>
        <a class="" href="manage_payments.php"><span class="app-nav-icon" aria-hidden="true">💳</span><span class="app-nav-label">การชำระเงิน</span></a>
        <a class="" href="manage_repairs.php"><span class="app-nav-icon" aria-hidden="true">🛠️</span><span class="app-nav-label">แจ้งซ่อม</span></a>
        <a class="" href="system_settings.php"><span class="app-nav-icon" aria-hidden="true">🎨</span><span class="app-nav-label">ตั้งค่าระบบ</span></a>
      </details>
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
  
  // Restore sidebar state on page load (desktop only)
  // Note: Sidebar toggle handler is now managed by animate-ui.js
  const savedState = localStorage.getItem('sidebarCollapsed');
  if (savedState === 'true' && window.innerWidth > 1024) {
    sidebar.classList.add('collapsed');
    console.log('Sidebar state restored from localStorage');
  }
  
  // Set active menu item based on current page
  function setActiveMenu() {
    const currentPage = window.location.pathname.split('/').pop();
    const menuLinks = document.querySelectorAll('.app-nav a');
    
    menuLinks.forEach(link => {
      const href = link.getAttribute('href');
      if (href && (href === currentPage || href.endsWith('/' + currentPage))) {
        link.classList.add('active');
      }
    });
  }
  
  // Run on page load
  setActiveMenu();

  // Ensure summary links navigate (แดชบอร์ด/จัดการ)
  document.querySelectorAll('summary .summary-link').forEach(function(link) {
    link.addEventListener('click', function(e) {
      e.stopPropagation(); // ป้องกันไม่ให้ toggle dropdown
      // ให้ลิงก์ทำงานทันที
      window.location.href = link.getAttribute('href');
    });
  });
  
  // Close sidebar when clicking overlay
  const toggleBtn = document.getElementById('sidebar-toggle');
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

// Save and restore collapsible details state
(function() {
  let isInitializing = true;
  
  // Function to restore state - ทำงาน FORCE เพื่อ override ทุกอย่าง
  function restoreDetailsState() {
    document.querySelectorAll('details[id]').forEach(function(details) {
      const id = details.id;
      if (id) {
        const key = 'sidebar_details_' + id;
        const savedState = localStorage.getItem(key);
        
        // ใช้สถานะที่บันทึกไว้เสมอ ถ้ามี
        if (savedState === 'closed') {
          // ปิด dropdown - FORCE
          details.removeAttribute('open');
          details.open = false;
        } else if (savedState === 'open') {
          // เปิด dropdown - FORCE
          details.setAttribute('open', '');
          details.open = true;
        }
        // ถ้าไม่มีการบันทึก ใช้สถานะเริ่มต้นจาก HTML (ครั้งแรก)
      }
    });
    
    // หลังจาก restore เสร็จ ให้เริ่มบันทึกการเปลี่ยนแปลง
    setTimeout(function() {
      isInitializing = false;
    }, 100);
  }
  
  // Save collapsible state on toggle
  document.addEventListener('toggle', function(e) {
    if (e.target.tagName === 'DETAILS' && e.target.id && !isInitializing) {
      const key = 'sidebar_details_' + e.target.id;
      const newState = e.target.open ? 'open' : 'closed';
      localStorage.setItem(key, newState);
      console.log('💾 Saved:', key, '=', newState);
    }
  }, true);
  
  // Restore state when DOM is ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
      setTimeout(restoreDetailsState, 50);
    });
  } else {
    // ทำงานทันทีและซ้ำอีกครั้งหลังจาก setActiveMenu ทำงานเสร็จ
    restoreDetailsState();
    setTimeout(restoreDetailsState, 50);
    setTimeout(restoreDetailsState, 200);
  }
})();

// Chevron toggle for dropdowns with animation (separate from link navigation)
(function() {
  document.addEventListener('click', function(e) {
    const chev = e.target.closest('.chev, .chev-toggle');
    if (!chev) return;

    e.preventDefault();
    e.stopPropagation();

    const details = chev.closest('details');
    if (!details) return;

    const isOpening = !details.open;
    
    if (isOpening) {
      // Opening: set open then trigger animation
      details.open = true;
      
      // Force reflow to trigger animation
      void details.offsetHeight;
      
      const items = details.querySelectorAll(':scope > a');
      items.forEach((item, index) => {
        item.style.animation = 'none';
        void item.offsetHeight;
        item.style.animation = '';
        item.style.animationDelay = (0.05 * (index + 1)) + 's';
      });
    } else {
      // Closing: animate out then close
      const items = details.querySelectorAll(':scope > a');
      
      items.forEach((item, index) => {
        item.style.animation = 'slideUp 0.3s cubic-bezier(0.4, 0, 0.2, 1) forwards';
        item.style.animationDelay = (0.03 * (items.length - index - 1)) + 's';
      });
      
      // Close after animation completes
      setTimeout(() => {
        details.open = false;
      }, 300 + (items.length * 30));
    }

    const key = 'sidebar_details_' + details.id;
    localStorage.setItem(key, isOpening ? 'open' : 'closed');
  });
})();

// Global sidebar toggle (ทำงานทุกหน้า)
(function() {
  const sidebar = document.querySelector('.app-sidebar');
  const toggleBtn = document.getElementById('sidebar-toggle');
  if (!sidebar || !toggleBtn) return;

  // โหลดสถานะจาก localStorage (desktop เท่านั้น)
  if (window.innerWidth > 1024 && localStorage.getItem('sidebarCollapsed') === 'true') {
    sidebar.classList.add('collapsed');
  }

  toggleBtn.addEventListener('click', function(e) {
    e.preventDefault();
    e.stopPropagation();

    if (window.innerWidth > 1024) {
      sidebar.style.transition = 'none';
      void sidebar.offsetHeight;
      sidebar.style.transition = '';

      sidebar.classList.toggle('collapsed');
      localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
    } else {
      sidebar.classList.toggle('mobile-open');
      document.body.classList.toggle('sidebar-open');
    }
  });

  // ปิด sidebar เมื่อคลิกนอก (mobile)
  document.addEventListener('click', function(e) {
    if (window.innerWidth <= 1024 && sidebar.classList.contains('mobile-open')) {
      if (!sidebar.contains(e.target) && !toggleBtn.contains(e.target)) {
        sidebar.classList.remove('mobile-open');
        document.body.classList.remove('sidebar-open');
      }
    }
  });
})();
</script>
