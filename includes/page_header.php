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
    <h2><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></h2>
  </div>
</header>
<div class="page-header-spacer"></div>
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
/* Page Header Styles - Apple-style Auto-hide */
.page-header-bar {
  position: sticky;
  top: 0;
  z-index: 10000;
  padding: 1rem 1.5rem;
  background: rgba(15, 23, 42, 0.8);
  backdrop-filter: blur(20px) saturate(180%);
  -webkit-backdrop-filter: blur(20px) saturate(180%);
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin: 0 0 1rem 0;
  transform: translateY(0);
  transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1),
              background 0.3s cubic-bezier(0.4, 0, 0.2, 1),
              box-shadow 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

/* Add padding to main content container */
.app-main > div {
  padding-left: 1rem;
  padding-right: 1rem;
}

@media (min-width: 769px) {
  .app-main > div {
    padding-left: 1.5rem;
    padding-right: 1.5rem;
  }
}

.page-header-bar.header-hidden {
  transform: translateY(-100%);
  box-shadow: none;
}

.page-header-bar.header-scrolled {
  background: rgba(15, 23, 42, 0.95);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
}

.page-header-spacer {
  display: none;
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
  transition: opacity 0.3s ease;
}
.page-header-bar.header-hidden h2 {
  opacity: 0;
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
  transition: background 0.3s ease, transform 0.2s ease;
  outline: none;
  pointer-events: auto !important;
  visibility: visible !important;
  opacity: 1 !important;
}
.sidebar-toggle-btn:hover {
  background: rgba(255, 255, 255, 0.1);
  transform: scale(1.05);
}
.sidebar-toggle-btn:active {
  transform: scale(0.95);
}
.sidebar-toggle-btn svg {
  width: 24px;
  height: 24px;
  stroke-width: 2;
  transition: transform 0.3s ease;
}
@media (max-width: 768px) {
  .page-header-bar {
    padding: 0.875rem 1rem;
  }
  .page-header-bar h2 {
    font-size: 1rem;
  }
  .page-header-spacer {
    height: 64px;
  }
}

/* Light Theme Support */
body.light-theme .page-header-bar,
body[data-theme="light"] .page-header-bar {
  background: rgba(255, 255, 255, 0.8);
  border-bottom: 1px solid rgba(0, 0, 0, 0.1);
}

body.light-theme .page-header-bar.header-scrolled,
body[data-theme="light"] .page-header-bar.header-scrolled {
  background: rgba(255, 255, 255, 0.95);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}

body.light-theme .page-header-bar h2,
body[data-theme="light"] .page-header-bar h2 {
  color: #1e293b;
}

body.light-theme .sidebar-toggle-btn,
body[data-theme="light"] .sidebar-toggle-btn {
  color: #1e293b;
}

body.light-theme .sidebar-toggle-btn:hover,
body[data-theme="light"] .sidebar-toggle-btn:hover {
  background: rgba(0, 0, 0, 0.05);
}
</style>
<script>
// Apple-style Auto-hide Header on Scroll - Works on all pages
(function() {
  let lastScrollTop = 0;
  let ticking = false;
  let scrollContainer = null;
  
  function getScrollTop() {
    if (scrollContainer) {
      return scrollContainer.scrollTop;
    }
    return window.pageYOffset || document.documentElement.scrollTop;
  }
  
  function handleHeaderScroll() {
    const header = document.querySelector('.page-header-bar');
    if (!header) return;
    
    const currentScrollTop = getScrollTop();
    const scrollDelta = currentScrollTop - lastScrollTop;
    
    // Add scrolled class when scrolled
    if (currentScrollTop > 5) {
      header.classList.add('header-scrolled');
    } else {
      header.classList.remove('header-scrolled');
    }
    
    // Scrolling DOWN (positive delta) - hide header
    if (scrollDelta > 2 && currentScrollTop > 50) {
      header.classList.add('header-hidden');
    }
    // Scrolling UP (negative delta) - show header IMMEDIATELY
    else if (scrollDelta < -2) {
      header.classList.remove('header-hidden');
    }
    
    // Always show when at very top
    if (currentScrollTop <= 10) {
      header.classList.remove('header-hidden');
    }
    
    lastScrollTop = currentScrollTop <= 0 ? 0 : currentScrollTop;
    ticking = false;
  }
  
  function requestHeaderUpdate() {
    if (!ticking) {
      window.requestAnimationFrame(handleHeaderScroll);
      ticking = true;
    }
  }
  
  function initHeaderScroll() {
    // Find scroll container - check common containers used in the app
    scrollContainer = document.querySelector('.app-main') || 
                      document.querySelector('.main-content') || 
                      document.querySelector('main') ||
                      null;
    
    // Initial check
    handleHeaderScroll();
    
    // Add scroll listener to container if found, otherwise use window
    if (scrollContainer) {
      scrollContainer.addEventListener('scroll', requestHeaderUpdate, { passive: true });
    }
    // Always add window scroll listener as fallback
    window.addEventListener('scroll', requestHeaderUpdate, { passive: true });
    
    // Handle resize
    window.addEventListener('resize', requestHeaderUpdate, { passive: true });
  }
  
  // Initialize when DOM is ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initHeaderScroll);
  } else {
    // Small delay to ensure containers are rendered
    setTimeout(initHeaderScroll, 100);
  }
})();
</script>
