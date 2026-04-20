<?php
/**
 * Sidebar Toggle Component
 * 
 * Include this file in <head> section of any page that uses sidebar.
 * This provides the toggle functionality for both mobile and desktop.
 * 
 * Usage: <?php include __DIR__ . '/includes/sidebar_toggle.php'; ?>
 * 
 * Must be included BEFORE page_header.php and sidebar.php
 */
?>
<script>
// Ultra-early sidebar toggle - must be in <head> before any sidebar elements load
(function() {
  'use strict';

  // Configuration
  var MOBILE_BREAKPOINT = 1024;
  var STORAGE_KEY = 'sidebarCollapsed';

  // Safe localStorage access
  function safeGet(key) {
    try { return localStorage.getItem(key); } catch(e) { return null; }
  }

  function safeSet(key, value) {
    try { localStorage.setItem(key, value); } catch(e) {}
  }

  // Check if mobile view
  function isMobile() {
    return window.innerWidth <= MOBILE_BREAKPOINT;
  }

  // Main toggle function - available globally
  window.__directSidebarToggle = function(event) {
    if (event) {
      event.preventDefault();
      event.stopPropagation();
      event.stopImmediatePropagation();
    }

    var sidebar = document.querySelector('.app-sidebar');
    if (!sidebar) {
      console.warn('Sidebar not found');
      return false;
    }

    if (isMobile()) {
      var isOpen = sidebar.classList.toggle('mobile-open');
      document.body.classList.toggle('sidebar-open', isOpen);

      // Update aria-expanded
      var btn = document.getElementById('sidebar-toggle');
      if (btn) btn.setAttribute('aria-expanded', isOpen.toString());
    } else {
      var isCollapsed = sidebar.classList.toggle('collapsed');
      // Save whether sidebar is expanded or collapsed
      safeSet(STORAGE_KEY, isCollapsed ? 'true' : 'false');

      // Update aria-expanded
      var btn = document.getElementById('sidebar-toggle');
      if (btn) btn.setAttribute('aria-expanded', (!isCollapsed).toString());
    }

    return false;
  };

  // Close sidebar when clicking outside (mobile only)
  window.__closeSidebarOnOutsideClick = function(e) {
    if (!isMobile()) return;

    var sidebar = document.querySelector('.app-sidebar');
    var toggleBtn = document.getElementById('sidebar-toggle');

    if (sidebar && sidebar.classList.contains('mobile-open')) {
      if (!sidebar.contains(e.target) && (!toggleBtn || !toggleBtn.contains(e.target))) {
        sidebar.classList.remove('mobile-open');
        document.body.classList.remove('sidebar-open');
        if (toggleBtn) toggleBtn.setAttribute('aria-expanded', 'false');
      }
    }
  };

  // Initialize sidebar state from localStorage (desktop only)
  window.__initSidebarState = function() {
    var sidebar = document.querySelector('.app-sidebar');
    if (!sidebar) return;

    // Desktop: Check saved state, default is expanded (not collapsed)
    if (!isMobile()) {
      var savedState = safeGet(STORAGE_KEY);
      // Only collapse if explicitly saved as 'true'
      if (savedState === 'true') {
        sidebar.classList.add('collapsed');
      }
    }

    // Bind outside click handler
    document.addEventListener('click', window.__closeSidebarOnOutsideClick);
  };

  // Mark that toggle system is ready
  window.__sidebarToggleReady = true;
})();
</script>

<!-- Global Skeleton Loader -->
<style>
#global-skeleton-loader {
  position: fixed;
  inset: 0;
  z-index: 999999;
  background-color: #f8fafc;
  display: flex;
  transition: opacity 0.35s ease, visibility 0.35s ease;
  pointer-events: none;
}
#global-skeleton-loader * {
  box-sizing: border-box;
}

.skeleton-block {
  position: relative;
  overflow: hidden;
  background: #e2e8f0;
  border-radius: 0.75rem;
}
.skeleton-block::after {
  content: "";
  position: absolute;
  inset: 0;
  transform: translateX(-100%);
  background: linear-gradient(
    90deg,
    rgba(226, 232, 240, 0) 0%,
    rgba(255, 255, 255, 0.75) 50%,
    rgba(226, 232, 240, 0) 100%
  );
  animation: skeleton-shimmer 1.55s ease-in-out infinite;
}

.skeleton-sidebar {
  width: 260px;
  background: #ffffff;
  border-right: 1px solid #e2e8f0;
  padding: 1.25rem 0.75rem;
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  flex-shrink: 0;
}
.skeleton-sidebar-header {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.6rem;
  padding: 0.4rem 0.25rem 1rem;
  border-bottom: 1px solid #f1f5f9;
}
.skeleton-sidebar-logo {
  width: 88px;
  height: 88px;
  border-radius: 14px;
}
.skeleton-sidebar-title {
  width: 126px;
  height: 13px;
  border-radius: 999px;
}
.skeleton-sidebar-nav {
  display: flex;
  flex-direction: column;
  gap: 0.45rem;
  padding: 0.15rem 0.25rem;
}
.skeleton-sidebar-item {
  height: 40px;
  border-radius: 0.7rem;
}
.skeleton-sidebar-item.short {
  width: 84%;
}

.skeleton-main {
  flex: 1 1 auto;
  min-width: 0;
  padding: 1rem;
  overflow: hidden;
}
@media (min-width: 640px) {
  .skeleton-main {
    padding: 2rem;
  }
}
@media (min-width: 1024px) {
  .skeleton-main {
    padding: 2.5rem;
  }
}

.skeleton-container {
  max-width: 80rem;
  margin: 0 auto;
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
  padding-bottom: 2.5rem;
}

.skeleton-header-row {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
}
.skeleton-header-left {
  display: flex;
  align-items: center;
  gap: 0.9rem;
  min-width: 0;
}
.skeleton-toggle {
  width: 42px;
  height: 42px;
  border-radius: 0.65rem;
  border: 1px solid #e2e8f0;
  background: #ffffff;
  flex-shrink: 0;
}
.skeleton-heading-lines {
  display: flex;
  flex-direction: column;
  gap: 0.55rem;
}
.skeleton-heading-title {
  width: 132px;
  height: 27px;
}
.skeleton-heading-subtitle {
  width: min(430px, 56vw);
  max-width: 100%;
  height: 14px;
}
.skeleton-date-pill {
  width: 146px;
  height: 38px;
  border-radius: 999px;
  border: 1px solid #e2e8f0;
  background: #ffffff;
}

.skeleton-stats-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 1.5rem;
}
@media (min-width: 640px) {
  .skeleton-stats-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
@media (min-width: 1024px) {
  .skeleton-stats-grid {
    grid-template-columns: repeat(4, minmax(0, 1fr));
  }
}

.skeleton-stat-card {
  height: 114px;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 1rem;
  padding: 1rem;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
}
.skeleton-stat-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.skeleton-stat-title {
  width: 40%;
  height: 11px;
}
.skeleton-stat-icon {
  width: 28px;
  height: 28px;
  border-radius: 0.55rem;
}
.skeleton-stat-value {
  width: 44%;
  height: 22px;
}
.skeleton-stat-foot {
  width: 60%;
  height: 11px;
}

.skeleton-charts-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 1.5rem;
}
@media (min-width: 1024px) {
  .skeleton-charts-grid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}

.skeleton-card {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 1rem;
  padding: 1.25rem;
}
.skeleton-chart-revenue {
  min-height: 315px;
}
@media (min-width: 1024px) {
  .skeleton-chart-revenue {
    grid-column: span 2 / span 2;
  }
}
.skeleton-chart-room {
  min-height: 315px;
  display: flex;
  flex-direction: column;
}
.skeleton-card-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 1rem;
}
.skeleton-card-title {
  width: 120px;
  height: 16px;
}
.skeleton-card-link {
  width: 72px;
  height: 12px;
}
.skeleton-chart-area {
  height: 230px;
  border-radius: 0.9rem;
}
.skeleton-room-donut-wrap {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
}
.skeleton-room-donut {
  width: 170px;
  height: 170px;
  border-radius: 50%;
  border: 20px solid #d1fae5;
  border-right-color: #e2e8f0;
  border-top-color: #10b981;
}
.skeleton-room-legend {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.6rem;
  margin-top: 0.8rem;
}
.skeleton-room-legend-item {
  height: 48px;
  border-radius: 0.75rem;
}

.skeleton-secondary-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 1.5rem;
}
@media (min-width: 1024px) {
  .skeleton-secondary-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

.skeleton-action-card {
  min-height: 295px;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 1rem;
  overflow: hidden;
}
.skeleton-action-head {
  padding: 1rem 1.25rem;
  border-bottom: 1px solid #f1f5f9;
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.skeleton-action-title {
  width: 145px;
  height: 15px;
}
.skeleton-action-badge {
  width: 68px;
  height: 22px;
  border-radius: 999px;
}
.skeleton-action-list {
  padding: 0.65rem 1.25rem 1.1rem;
  display: flex;
  flex-direction: column;
  gap: 0.85rem;
}
.skeleton-action-item {
  height: 74px;
  border-radius: 0.9rem;
}

.skeleton-activity-card {
  min-height: 295px;
  border-radius: 1rem;
  padding: 1.4rem;
  background: linear-gradient(135deg, #4f46e5 0%, #4338ca 45%, #2563eb 100%);
  position: relative;
  overflow: hidden;
}
.skeleton-activity-card::before {
  content: "";
  position: absolute;
  right: -60px;
  top: -60px;
  width: 180px;
  height: 180px;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.12);
}
.skeleton-activity-title {
  width: 150px;
  height: 19px;
  background: rgba(255, 255, 255, 0.35);
  border-radius: 0.65rem;
  margin-bottom: 1.2rem;
}
.skeleton-activity-list {
  display: flex;
  flex-direction: column;
  gap: 0.8rem;
}
.skeleton-activity-item {
  height: 64px;
  border-radius: 0.9rem;
  background: rgba(255, 255, 255, 0.18);
  position: relative;
  overflow: hidden;
}
.skeleton-activity-item::after {
  content: "";
  position: absolute;
  inset: 0;
  transform: translateX(-100%);
  background: linear-gradient(90deg, rgba(255, 255, 255, 0) 0%, rgba(255, 255, 255, 0.26) 50%, rgba(255, 255, 255, 0) 100%);
  animation: skeleton-shimmer 1.55s ease-in-out infinite;
}

.skeleton-fallback-card {
  height: 118px;
  border-radius: 1rem;
  border: 1px solid #e2e8f0;
  background: #ffffff;
  padding: 1rem;
}
.skeleton-fallback-main {
  height: 320px;
  border-radius: 1rem;
  border: 1px solid #e2e8f0;
  background: #ffffff;
  padding: 1rem;
}

@media (max-width: 1024px) {
  .skeleton-sidebar {
    display: none;
  }
}
@media (max-width: 640px) {
  .skeleton-header-row {
    flex-direction: column;
  }
}

@keyframes skeleton-shimmer {
  100% { transform: translateX(100%); }
}

body.loading-skeleton {
  overflow: hidden !important;
}
</style>

<script>
(function() {
  function isDesktop() {
    return window.innerWidth > 1024;
  }

  function isSidebarCollapsed() {
    try {
      return localStorage.getItem('sidebarCollapsed') === 'true';
    } catch (e) {
      return false;
    }
  }

  function buildSidebarSkeleton() {
    return `
      <div class="skeleton-sidebar">
        <div class="skeleton-sidebar-header">
          <div class="skeleton-block skeleton-sidebar-logo"></div>
          <div class="skeleton-block skeleton-sidebar-title"></div>
        </div>
        <div class="skeleton-sidebar-nav">
          <div class="skeleton-block skeleton-sidebar-item"></div>
          <div class="skeleton-block skeleton-sidebar-item short"></div>
          <div class="skeleton-block skeleton-sidebar-item"></div>
          <div class="skeleton-block skeleton-sidebar-item"></div>
          <div class="skeleton-block skeleton-sidebar-item short"></div>
          <div class="skeleton-block skeleton-sidebar-item"></div>
          <div class="skeleton-block skeleton-sidebar-item short"></div>
        </div>
      </div>
    `;
  }

  function buildDashboardSkeleton() {
    return `
      <div class="skeleton-main">
        <div class="skeleton-container">
          <div class="skeleton-header-row">
            <div class="skeleton-header-left">
              <div class="skeleton-toggle"></div>
              <div class="skeleton-heading-lines">
                <div class="skeleton-block skeleton-heading-title"></div>
                <div class="skeleton-block skeleton-heading-subtitle"></div>
              </div>
            </div>
            <div class="skeleton-date-pill"></div>
          </div>

          <div class="skeleton-stats-grid">
            <div class="skeleton-stat-card">
              <div class="skeleton-stat-top">
                <div class="skeleton-block skeleton-stat-title"></div>
                <div class="skeleton-block skeleton-stat-icon"></div>
              </div>
              <div class="skeleton-block skeleton-stat-value"></div>
              <div class="skeleton-block skeleton-stat-foot"></div>
            </div>
            <div class="skeleton-stat-card">
              <div class="skeleton-stat-top">
                <div class="skeleton-block skeleton-stat-title"></div>
                <div class="skeleton-block skeleton-stat-icon"></div>
              </div>
              <div class="skeleton-block skeleton-stat-value"></div>
              <div class="skeleton-block skeleton-stat-foot"></div>
            </div>
            <div class="skeleton-stat-card">
              <div class="skeleton-stat-top">
                <div class="skeleton-block skeleton-stat-title"></div>
                <div class="skeleton-block skeleton-stat-icon"></div>
              </div>
              <div class="skeleton-block skeleton-stat-value"></div>
              <div class="skeleton-block skeleton-stat-foot"></div>
            </div>
            <div class="skeleton-stat-card">
              <div class="skeleton-stat-top">
                <div class="skeleton-block skeleton-stat-title"></div>
                <div class="skeleton-block skeleton-stat-icon"></div>
              </div>
              <div class="skeleton-block skeleton-stat-value"></div>
              <div class="skeleton-block skeleton-stat-foot"></div>
            </div>
          </div>

          <div class="skeleton-charts-grid">
            <div class="skeleton-card skeleton-chart-revenue">
              <div class="skeleton-card-head">
                <div class="skeleton-block skeleton-card-title"></div>
                <div class="skeleton-block skeleton-card-link"></div>
              </div>
              <div class="skeleton-block skeleton-chart-area"></div>
            </div>

            <div class="skeleton-card skeleton-chart-room">
              <div class="skeleton-card-head" style="margin-bottom: 0.4rem;">
                <div class="skeleton-block skeleton-card-title"></div>
              </div>
              <div class="skeleton-room-donut-wrap">
                <div class="skeleton-room-donut"></div>
              </div>
              <div class="skeleton-room-legend">
                <div class="skeleton-block skeleton-room-legend-item"></div>
                <div class="skeleton-block skeleton-room-legend-item"></div>
              </div>
            </div>
          </div>

          <div class="skeleton-secondary-grid">
            <div class="skeleton-action-card">
              <div class="skeleton-action-head">
                <div class="skeleton-block skeleton-action-title"></div>
                <div class="skeleton-block skeleton-action-badge"></div>
              </div>
              <div class="skeleton-action-list">
                <div class="skeleton-block skeleton-action-item"></div>
                <div class="skeleton-block skeleton-action-item"></div>
              </div>
            </div>

            <div class="skeleton-activity-card">
              <div class="skeleton-activity-title"></div>
              <div class="skeleton-activity-list">
                <div class="skeleton-activity-item"></div>
                <div class="skeleton-activity-item"></div>
                <div class="skeleton-activity-item"></div>
              </div>
            </div>
          </div>
        </div>
      </div>
    `;
  }

  function buildFallbackSkeleton() {
    return `
      <div class="skeleton-main">
        <div class="skeleton-container">
          <div class="skeleton-header-row">
            <div class="skeleton-header-left">
              <div class="skeleton-toggle"></div>
              <div class="skeleton-heading-lines">
                <div class="skeleton-block skeleton-heading-title"></div>
                <div class="skeleton-block skeleton-heading-subtitle"></div>
              </div>
            </div>
            <div class="skeleton-date-pill"></div>
          </div>
          <div class="skeleton-stats-grid">
            <div class="skeleton-fallback-card"><div class="skeleton-block" style="height: 100%;"></div></div>
            <div class="skeleton-fallback-card"><div class="skeleton-block" style="height: 100%;"></div></div>
            <div class="skeleton-fallback-card"><div class="skeleton-block" style="height: 100%;"></div></div>
          </div>
          <div class="skeleton-fallback-main"><div class="skeleton-block" style="height: 100%;"></div></div>
        </div>
      </div>
    `;
  }

  var observer = new MutationObserver(function(mutations, instance) {
    if (!document.body) {
      return;
    }

    document.body.classList.add('loading-skeleton');
    var loader = document.createElement('div');
    loader.id = 'global-skeleton-loader';
    loader.setAttribute('aria-hidden', 'true');

    var path = (window.location.pathname || '').toLowerCase();
    var isDashboardPage = path.indexOf('/reports/dashboard.php') !== -1 || /\/dashboard\.php$/.test(path);
    var showSidebar = isDesktop() && !isSidebarCollapsed();

    loader.innerHTML =
      (showSidebar ? buildSidebarSkeleton() : '') +
      (isDashboardPage ? buildDashboardSkeleton() : buildFallbackSkeleton());

    document.body.insertBefore(loader, document.body.firstChild);
    instance.disconnect();
  });

  observer.observe(document.documentElement, { childList: true });

  window.addEventListener('load', function() {
    var loader = document.getElementById('global-skeleton-loader');
    if (!loader) {
      return;
    }

    loader.style.opacity = '0';
    loader.style.visibility = 'hidden';
    document.body.classList.remove('loading-skeleton');
    window.setTimeout(function() {
      if (loader.parentNode) {
        loader.parentNode.removeChild(loader);
      }
    }, 360);
  });
})();
</script>
