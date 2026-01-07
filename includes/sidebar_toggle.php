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
      // บันทึกว่า sidebar เปิดอยู่หรือไม่ ('false' = เปิด/ไม่ collapsed)
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
    
    // Default: sidebar collapsed (full screen content)
    // Only show sidebar if explicitly saved as 'false' (not collapsed)
    if (!isMobile()) {
      var savedState = safeGet(STORAGE_KEY);
      // ถ้า savedState เป็น 'false' หรือ null/undefined ให้ collapsed
      // ถ้า savedState เป็น 'expanded' หรือ 'false' เท่านั้น ถึงจะแสดง sidebar
      if (savedState !== 'false') {
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
