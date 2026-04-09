
  // ====== Global Admin Font Scale Sync ======
  // โหลด font scale จาก localStorage ทันทีก่อน DOM render
  (function() {
    const savedScale = localStorage.getItem('adminFontScale');
    if (savedScale && !isNaN(parseFloat(savedScale))) {
      document.documentElement.style.setProperty('--font-scale', savedScale);
      document.documentElement.style.setProperty('--admin-font-scale', savedScale);
    }
    
    // Listen สำหรับ font scale change จากหน้าอื่น (เช่น Settings)
    window.addEventListener('storage', function(e) {
      if (e.key === 'adminFontScale' && e.newValue) {
        document.documentElement.style.setProperty('--font-scale', e.newValue);
        document.documentElement.style.setProperty('--admin-font-scale', e.newValue);
      }
      // Cross-tab: refresh other pages on data change
      if (e.key === 'dataChanged' && e.newValue) {
        try {
          var currentPage = location.pathname.split('/').pop().split('?')[0];
          var autoRefreshPages = {
            'tenant_wizard.php':   'refreshWizardTable',
            'todo_tasks.php':      '_reload',
            'manage_expenses.php': 'reloadExpensesAjax',
            'manage_utility.php':  '_reload',
            'manage_payments.php': '_reload',
            'manage_contracts.php':'refreshContractsTable',
            'manage_tenants.php':  '_reload',
            'report_rooms.php':    '_reload',
            'manage.php':          '_reload'
          };
          var fn = autoRefreshPages[currentPage];
          if (fn) {
            if (fn === '_reload') {
              location.reload();
            } else if (typeof window[fn] === 'function') {
              window[fn]();
            } else {
              location.reload();
            }
          }
        } catch(ex) {}
      }
    });
  })();
  
  // Force reset collapsed on mobile IMMEDIATELY before CSS applies
  // Use a safer media-query injection so desktop collapsed styles are not overridden
  if (window.innerWidth <= 1024) {
     document.write('<style>@media (max-width:1024px){ .app-sidebar.collapsed { width: 240px !important; } .app-sidebar.collapsed .app-nav-label, .app-sidebar.collapsed .summary-label, .app-sidebar.collapsed .chev { display: revert !important; } .app-sidebar.collapsed .team-avatar { width: 120px !important; height: 120px !important; padding: 0 !important; margin: 0 auto !important; } .app-sidebar.collapsed .team-avatar-img { width: 120px !important; height: 120px !important; object-fit: cover !important; } .app-sidebar.collapsed .team-meta { display: block !important; text-align: center !important; padding-top: 0.75rem !important; } }</style>');
  }
