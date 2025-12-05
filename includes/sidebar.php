<?php
// Expect session already started and $adminName set in including script
$adminName = $_SESSION['admin_name'] ?? $_SESSION['admin_username'] ?? 'Admin';
?>
<style>
  details summary {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 0.75rem !important;
    margin: 0 !important;
  }
  details summary .app-nav-icon {
    flex-shrink: 0;
    width: 1.25rem;
    height: 1.25rem;
    display: flex;
    align-items: center;
    justify-content: center;
  }
  details summary .chev {
    transition: transform 0.3s ease;
  }
  details[open] summary .chev {
    transform: rotate(90deg);
  }
  .team-switcher {
    width: 100%;
    aspect-ratio: 1 / 1;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0.5rem !important;
  }
  .team-avatar {
    width: 100%;
    height: 100%;
    aspect-ratio: 1 / 1;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    margin: 0 !important;
    background: #0f1a2e;
  }
  .team-avatar-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
  } 
  .team-meta {
    display: none;
  }
  .subitem {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding-left: 0.75rem;
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
  
  /* Sidebar collapsed state - icon centered */
  aside.sidebar-collapsed {
    display: flex;
    flex-direction: column;
    align-items: center;
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
    document.write('<style>.app-sidebar.collapsed { all: revert !important; width: 240px !important; } .app-sidebar.collapsed .app-nav-label, .app-sidebar.collapsed .team-meta, .app-sidebar.collapsed .summary-label, .app-sidebar.collapsed .chev { all: revert !important; }</style>');
  }
</script>
<aside class="app-sidebar">
  <div class="team-switcher">
    <div class="team-avatar">
      <!-- Project logo (replace with Assets/Images/Lgoo.jpg) -->
      <img src="/Dormitory_Management/Assets/Images/Lgoo.jpg" alt="Sangthian Dormitory logo" class="team-avatar-img" />
    </div>
    <div class="team-meta">
      <div class="name">Sangthian Dormitory</div>
      <div class="plan">หอพักแสงเทียน</div>
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
        <a class="" href="manage_booking.php"><span class="app-nav-icon" aria-hidden="true">📅</span><span class="app-nav-label">จองห้องพัก</span></a>
        <a class="" href="manage_contracts.php"><span class="app-nav-icon" aria-hidden="true">📝</span><span class="app-nav-label">จัดการสัญญา</span></a>
        <a class="" href="manage_expenses.php"><span class="app-nav-icon" aria-hidden="true">💰</span><span class="app-nav-label">ค่าใช้จ่าย</span></a>
      </details>
    </div>
  </nav>

  <div style="flex:1"></div>

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
      <form action="../logout.php" method="post">
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
      <form action="../logout.php" method="post" class="rail-logout">
        <button type="submit" class="app-nav-icon" aria-label="Log out">⎋</button>
      </form>
    </div>
  </div>
</aside>

<script>
(function() {
  const sidebar = document.querySelector('.app-sidebar');
  const toggleBtn = document.getElementById('sidebar-toggle');
  
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
    }
  });
})();
</script>
