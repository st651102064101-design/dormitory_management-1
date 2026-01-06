<?php
/**
 * Page Header Component
 * 
 * Includes the hamburger menu button and page title.
 * 
 * Usage: 
 *   <?php $pageTitle = 'หน้าจัดการ'; include __DIR__ . '/includes/page_header.php'; ?>
 * 
 * Required: sidebar_toggle.php must be included in <head> first
 */
?>
<header class="page-header-bar">
  <div>
    <button id="sidebar-toggle" data-sidebar-toggle aria-label="Toggle sidebar" aria-expanded="false" class="sidebar-toggle-btn">
      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <line x1="3" y1="6" x2="21" y2="6"/>
        <line x1="3" y1="12" x2="21" y2="12"/>
        <line x1="3" y1="18" x2="21" y2="18"/>
      </svg>
    </button>
    <h2><?php echo htmlspecialchars($pageTitle ?? 'หน้าจัดการ', ENT_QUOTES, 'UTF-8'); ?></h2>
  </div>
</header>
<script>
(function() {
  var btn = document.getElementById('sidebar-toggle');
  if (btn && !btn.__toggleBound) {
    btn.__toggleBound = true;
    btn.addEventListener('click', function(e) {
      if (typeof window.__directSidebarToggle === 'function') {
        window.__directSidebarToggle(e);
      }
    }, true);
  }
  
  // Initialize sidebar state if not already done
  if (typeof window.__initSidebarState === 'function' && !window.__sidebarStateInitialized) {
    window.__sidebarStateInitialized = true;
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', window.__initSidebarState);
    } else {
      window.__initSidebarState();
    }
  }
})();
</script>
<style>
/* Page Header Styles */
.page-header-bar {
  position: relative;
  z-index: 10000;
  margin-bottom: 1rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.page-header-bar > div {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}
.page-header-bar h2 {
  margin: 0;
  font-size: 1.5rem;
  font-weight: 600;
  color: #f5f8ff;
}
.sidebar-toggle-btn {
  position: relative;
  z-index: 10001;
  background: transparent;
  border: 0;
  color: #fff;
  padding: 0.6rem 0.85rem;
  border-radius: 6px;
  cursor: pointer;
  font-size: 1.25rem;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: background 0.3s ease;
  outline: none;
  pointer-events: auto !important;
  visibility: visible !important;
  opacity: 1 !important;
}
.sidebar-toggle-btn:hover {
  background: rgba(255, 255, 255, 0.1);
}
.sidebar-toggle-btn svg {
  width: 24px;
  height: 24px;
  stroke-width: 2;
}
@media (max-width: 768px) {
  .page-header-bar h2 {
    font-size: 1rem;
  }
}
</style>
