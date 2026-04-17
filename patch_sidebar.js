// Close on outside click (mobile & desktop)
document.addEventListener('click', function(e) {
  // Check if click is on a navigation link
  const navLink = e.target.closest('.app-nav a');
  
  if (window.innerWidth <= 1024 && sidebar.classList.contains('mobile-open')) {
    if ((!sidebar.contains(e.target) && !toggleBtn.contains(e.target)) || navLink) {
      sidebar.classList.remove('mobile-open');
      document.body.classList.remove('sidebar-open');
    }
  } else if (window.innerWidth > 1024 && !sidebar.classList.contains('collapsed')) {
    if ((!sidebar.contains(e.target) && !toggleBtn.contains(e.target)) || navLink) {
      sidebar.classList.add('collapsed');
      try {
        localStorage.setItem('sidebarCollapsed', 'true');
      } catch(ex) {}
    }
  }
});
