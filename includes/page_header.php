<header>
  <div>
    <button id="sidebar-toggle" data-sidebar-toggle aria-label="Toggle sidebar" aria-expanded="false" onclick="return window.__directSidebarToggle ? window.__directSidebarToggle(event) : true;">
      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <line x1="3" y1="6" x2="21" y2="6"/>
        <line x1="3" y1="12" x2="21" y2="12"/>
        <line x1="3" y1="18" x2="21" y2="18"/>
      </svg>
    </button>
    <h2><?php echo htmlspecialchars($pageTitle ?? 'หน้าจัดการ', ENT_QUOTES, 'UTF-8'); ?></h2>
  </div>
</header>
