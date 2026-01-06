<header style="position: relative; z-index: 10000;">
  <div>
    <button id="sidebar-toggle" data-sidebar-toggle aria-label="Toggle sidebar" aria-expanded="false" style="position: relative; z-index: 10001; pointer-events: auto !important; visibility: visible !important; opacity: 1 !important;">
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
      e.preventDefault();
      e.stopPropagation();
      e.stopImmediatePropagation();
      
      var sidebar = document.querySelector('.app-sidebar');
      if (sidebar) {
        if (window.innerWidth <= 1024) {
          var isOpen = sidebar.classList.toggle('mobile-open');
          document.body.classList.toggle('sidebar-open', isOpen);
        } else {
          var isCollapsed = sidebar.classList.toggle('collapsed');
          try { localStorage.setItem('sidebarCollapsed', isCollapsed); } catch(err) {}
        }
      }
    }, true); // Use capture phase
  }
})();
</script>
