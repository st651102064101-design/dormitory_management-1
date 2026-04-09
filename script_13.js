
(function() {
  function fallbackSidebarToggle(event) {
    if (event) {
      event.preventDefault();
      event.stopPropagation();
      if (typeof event.stopImmediatePropagation === 'function') {
        event.stopImmediatePropagation();
      }
    }

    var sidebar = document.querySelector('.app-sidebar');
    var btn = document.getElementById('sidebar-toggle');
    if (!sidebar) return false;

    var isMobile = window.innerWidth <= 1024;
    if (isMobile) {
      var isOpen = sidebar.classList.toggle('mobile-open');
      document.body.classList.toggle('sidebar-open', isOpen);
      if (btn) btn.setAttribute('aria-expanded', isOpen.toString());
    } else {
      var isCollapsed = sidebar.classList.toggle('collapsed');
      try { localStorage.setItem('sidebarCollapsed', isCollapsed ? 'true' : 'false'); } catch (e) {}
      if (btn) btn.setAttribute('aria-expanded', (!isCollapsed).toString());
    }

    return false;
  }

  window.__fallbackSidebarToggle = fallbackSidebarToggle;

  var btn = document.getElementById('sidebar-toggle');
  if (btn && !btn.__toggleBound) {
    btn.__toggleBound = true;
    btn.addEventListener('click', function(e) {
      if (typeof window.__directSidebarToggle === 'function') {
        return window.__directSidebarToggle(e);
      }
      return fallbackSidebarToggle(e);
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

  function initHeaderAutoHide() {
    var header = document.querySelector('.page-header-bar');
    if (!header || header.__autoHideBound) {
      return;
    }

    header.__autoHideBound = true;

    var scrollContainer = document.querySelector('.app-main') || document.querySelector('.main-content') || window;
    var lastScrollTop = 0;
    var ticking = false;

    function getScrollTop() {
      if (scrollContainer && scrollContainer !== window) {
        return scrollContainer.scrollTop || 0;
      }

      return window.pageYOffset || document.documentElement.scrollTop || 0;
    }

    function updateHeaderState() {
      var currentScrollTop = getScrollTop();
      var scrollDelta = currentScrollTop - lastScrollTop;

      if (currentScrollTop > 8) {
        header.classList.add('header-scrolled');
      } else {
        header.classList.remove('header-scrolled');
      }

      if (currentScrollTop <= 16) {
        header.classList.remove('header-hidden');
      } else if (scrollDelta > 3) {
        header.classList.add('header-hidden');
      } else if (scrollDelta < -1) {
        header.classList.remove('header-hidden');
      }

      lastScrollTop = currentScrollTop < 0 ? 0 : currentScrollTop;
      ticking = false;
    }

    function requestUpdate() {
      if (ticking) {
        return;
      }

      ticking = true;
      window.requestAnimationFrame(updateHeaderState);
    }

    if (scrollContainer && scrollContainer !== window) {
      scrollContainer.addEventListener('scroll', requestUpdate, { passive: true });
    }
    window.addEventListener('scroll', requestUpdate, { passive: true });
    window.addEventListener('resize', requestUpdate, { passive: true });

    updateHeaderState();
  }

  function fillMissingHeaderTitle() {
    var titleEl = document.getElementById('page-header-title');
    if (!titleEl || titleEl.textContent.trim() !== '') {
      return;
    }

    var documentTitle = (document.title || '').trim();
    if (!documentTitle) {
      return;
    }

    var parts = documentTitle.split(' - ').map(function(part) {
      return part.trim();
    }).filter(Boolean);

    titleEl.textContent = parts.length > 1 ? parts[parts.length - 1] : documentTitle;
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initHeaderAutoHide);
    document.addEventListener('DOMContentLoaded', fillMissingHeaderTitle);
  } else {
    initHeaderAutoHide();
    fillMissingHeaderTitle();
  }
})();
