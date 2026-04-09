
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
    const header = document.querySelector('.page-header-bar');
    if (!header || header.__autoHideBound) {
      return;
    }

    header.__autoHideBound = true;

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
