
(function() {
  const sidebar = document.querySelector('.app-sidebar');
  let isFreshLoginSession = false;

  // First page load after a new login session: close all dropdowns.
  try {
    const currentLoginSession = "m0ocupuovnksmh1rh3vavlnkje";
    const loginSessionKey = 'sidebar_login_session_id';
    const savedLoginSession = localStorage.getItem(loginSessionKey);
    if (savedLoginSession !== currentLoginSession) {
      isFreshLoginSession = true;
      localStorage.setItem(loginSessionKey, currentLoginSession);
    }
  } catch (e) {}

  window.__sidebarFreshLogin = isFreshLoginSession;
  
  // Restore sidebar state on page load (desktop only)
  // Note: Sidebar toggle handler is now managed by animate-ui.js
  const savedState = localStorage.getItem('sidebarCollapsed');
  if (savedState === 'true' && window.innerWidth > 1024) {
    sidebar.classList.add('collapsed');
    console.log('Sidebar state restored from localStorage');
  }
  
  // Set active menu item based on current page
  function setActiveMenu() {
    const currentPage = (window.location.pathname.split('/').pop() || '').split('?')[0];
    const menuLinks = document.querySelectorAll('.app-nav a');
    
    menuLinks.forEach(link => {
      const href = link.getAttribute('href');
      if (!href) return;

      const normalizedHref = href.split('#')[0];
      const hrefFile = (normalizedHref.split('/').pop() || '').split('?')[0];
      if (hrefFile && hrefFile === currentPage) {
        link.classList.add('active');

        // Skip auto-open on the very first page after login.
        // Also skip auto-open when active link is a summary-link itself.
        const parentDetails = link.closest('details[id]');
        if (link.classList.contains('summary-link')) {
          if (parentDetails) {
            parentDetails.removeAttribute('open');
            parentDetails.open = false;
            try {
              localStorage.setItem('sidebar_details_' + parentDetails.id, 'closed');
            } catch (e) {}
          }
        } else if (!window.__sidebarFreshLogin) {
          if (parentDetails) {
            parentDetails.open = true;
            try {
              localStorage.setItem('sidebar_details_' + parentDetails.id, 'open');
            } catch (e) {}
          }
        }
      }
    });
  }
  
  // Run on page load
  setActiveMenu();

  // Ensure summary links navigate (แดชบอร์ด/จัดการ)
  document.querySelectorAll('summary .summary-link').forEach(function(link) {
    link.addEventListener('click', function(e) {
      e.stopPropagation(); // ป้องกันไม่ให้ toggle dropdown

      // คลิกเมนูหลักของ dropdown นี้ ให้จำสถานะเป็นปิดอัตโนมัติ
      const parentDetails = link.closest('details[id]');
      if (parentDetails) {
        parentDetails.removeAttribute('open');
        parentDetails.open = false;
        try {
          localStorage.setItem('sidebar_details_' + parentDetails.id, 'closed');
        } catch (err) {}
      }

      // ให้ลิงก์ทำงานทันที
      window.location.href = link.getAttribute('href');
    });
  });
  
  // Close sidebar when clicking overlay
  const toggleBtn = document.getElementById('sidebar-toggle');
  document.addEventListener('click', function(e) {
      if (window.innerWidth <= 1024 && 
        sidebar.classList.contains('mobile-open') && 
        !sidebar.contains(e.target) &&
        e.target !== toggleBtn) {
      sidebar.classList.remove('mobile-open');
      document.body.classList.remove('sidebar-open');
    }
  });
  
  // Handle window resize
  window.addEventListener('resize', function() {
    if (window.innerWidth > 1024) {
      sidebar.classList.remove('mobile-open');
      document.body.classList.remove('sidebar-open');
    } else {
      // On mobile, remove collapsed state
      sidebar.classList.remove('collapsed');
    }
  });
})();

(function() {
  const trigger = document.getElementById('sidebarAccountTrigger');
  const modal = document.getElementById('sidebarAccountModal');
  if (!trigger || !modal) {
    return;
  }

  const firstInput = modal.querySelector('input[name="new_admin_username"]');
  const closeButtons = modal.querySelectorAll('[data-close-account-modal]');

  function openModal() {
    modal.hidden = false;
    document.body.classList.add('sidebar-account-modal-open');
    if (firstInput) {
      setTimeout(function() { firstInput.focus(); firstInput.select(); }, 0);
    }
  }

  function closeModal() {
    modal.hidden = true;
    document.body.classList.remove('sidebar-account-modal-open');
  }

  trigger.addEventListener('click', function(e) {
    // ❌ ไม่เปิด modal ถ้าคลิกปุ่ม unlink/link Google หรือ logout
    if (e.target.closest('.google-unlink-btn') ||
        e.target.closest('.google-link-btn') ||
        e.target.closest('.google-link-wrap') ||
        e.target.closest('.logout-btn')) {
      return;
    }
    openModal();
  });
  trigger.addEventListener('keydown', function(e) {
    if (e.key === 'Enter' || e.key === ' ') {
      e.preventDefault();
      openModal();
    }
  });

  closeButtons.forEach(function(btn) {
    btn.addEventListener('click', closeModal);
  });

  modal.addEventListener('click', function(e) {
    if (e.target === modal) {
      closeModal();
    }
  });

  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && !modal.hidden) {
      closeModal();
    }
  });

  if (modal.dataset.autoOpen === '1') {
    openModal();
  }
})();

// Save and restore collapsible details state
(function() {
  let isInitializing = true;
  const shouldCloseAllOnLogin = !!window.__sidebarFreshLogin;
  
  // Function to restore state - ทำงาน FORCE เพื่อ override ทุกอย่าง
  function restoreDetailsState() {
    if (shouldCloseAllOnLogin) {
      document.querySelectorAll('details[id]').forEach(function(details) {
        details.removeAttribute('open');
        details.open = false;
        try {
          localStorage.setItem('sidebar_details_' + details.id, 'closed');
        } catch (e) {}
      });

      setTimeout(function() {
        isInitializing = false;
      }, 100);
      return;
    }

    document.querySelectorAll('details[id]').forEach(function(details) {
      const id = details.id;
      if (id) {
        const key = 'sidebar_details_' + id;
        const savedState = localStorage.getItem(key);
        
        // ใช้สถานะที่บันทึกไว้เสมอ ถ้ามี
        if (savedState === 'closed') {
          // ปิด dropdown - FORCE
          details.removeAttribute('open');
          details.open = false;
        } else if (savedState === 'open') {
          // เปิด dropdown - FORCE
          details.setAttribute('open', '');
          details.open = true;
        }
        // ถ้าไม่มีการบันทึก ใช้สถานะเริ่มต้นจาก HTML (ครั้งแรก)
      }
    });

    // Auto-open only the group for current page, and close all unrelated groups.
    const currentPage = (window.location.pathname.split('/').pop() || '').split('?')[0];
    const activeDetailIds = new Set();

    document.querySelectorAll('.app-nav a[href]').forEach(function(link) {
      const href = link.getAttribute('href');
      if (!href) return;
      const hrefFile = (href.split('#')[0].split('/').pop() || '').split('?')[0];
      if (hrefFile && hrefFile === currentPage) {
        link.classList.add('active');
        const parentDetails = link.closest('details[id]');
        if (!parentDetails) return;

        if (link.classList.contains('summary-link')) {
          parentDetails.removeAttribute('open');
          parentDetails.open = false;
          try {
            localStorage.setItem('sidebar_details_' + parentDetails.id, 'closed');
          } catch (e) {}
          return;
        }

        activeDetailIds.add(parentDetails.id);
        parentDetails.open = true;
        try {
          localStorage.setItem('sidebar_details_' + parentDetails.id, 'open');
        } catch (e) {}
      }
    });

    document.querySelectorAll('details[id]').forEach(function(details) {
      if (!activeDetailIds.has(details.id)) {
        details.removeAttribute('open');
        details.open = false;
        try {
          localStorage.setItem('sidebar_details_' + details.id, 'closed');
        } catch (e) {}
      }
    });
    
    // หลังจาก restore เสร็จ ให้เริ่มบันทึกการเปลี่ยนแปลง
    setTimeout(function() {
      isInitializing = false;
    }, 100);
  }
  
  // Save collapsible state on toggle
  document.addEventListener('toggle', function(e) {
    if (e.target.tagName === 'DETAILS' && e.target.id && !isInitializing) {
      const key = 'sidebar_details_' + e.target.id;
      const newState = e.target.open ? 'open' : 'closed';
      localStorage.setItem(key, newState);
      console.log('Saved:', key, '=', newState);
    }
  }, true);
  
  // Prevent details toggle when summary-link clicked — navigate only, chev-toggle handles open/close
  document.addEventListener('click', function(e) {
    const link = e.target.closest('.summary-link');
    if (!link) return;
    const summary = link.closest('summary');
    if (!summary) return;
    e.preventDefault(); // stop default details toggle
    const href = link.getAttribute('href');
    if (href) window.location.href = href;
  }, true);

  // Restore state when DOM is ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
      setTimeout(restoreDetailsState, 50);
    });
  } else {
    // ทำงานครั้งเดียวก็พอ เพื่อลดงานซ้ำตอนเปลี่ยนหน้า
    restoreDetailsState();
  }
})();

// Chevron toggle for dropdowns with animation (separate from link navigation)
(function() {
  document.addEventListener('click', function(e) {
    const chev = e.target.closest('.chev, .chev-toggle');
    if (!chev) return;

    e.preventDefault();
    e.stopPropagation();

    const details = chev.closest('details');
    if (!details) return;

    const isOpening = !details.open;
    
    if (isOpening) {
      // Opening: set open then trigger animation
      details.open = true;
      
      // Force reflow to trigger animation
      void details.offsetHeight;
      
      const items = details.querySelectorAll(':scope > a');
      items.forEach((item, index) => {
        item.style.animation = 'none';
        void item.offsetHeight;
        item.style.animation = '';
        item.style.animationDelay = (0.05 * (index + 1)) + 's';
      });
    } else {
      // Closing: animate out then close
      const items = details.querySelectorAll(':scope > a');
      
      items.forEach((item, index) => {
        item.style.animation = 'slideUp 0.3s cubic-bezier(0.4, 0, 0.2, 1) forwards';
        item.style.animationDelay = (0.03 * (items.length - index - 1)) + 's';
      });
      
      // Close after animation completes
      setTimeout(() => {
        details.open = false;
      }, 300 + (items.length * 30));
    }

    const key = 'sidebar_details_' + details.id;
    localStorage.setItem(key, isOpening ? 'open' : 'closed');
  });
})();

// Legacy sidebar toggle - only runs if sidebar_toggle.php is not loaded
// This provides backward compatibility for pages that don't include sidebar_toggle.php
(function() {
  // Skip if new toggle system is already loaded
  if (window.__sidebarToggleReady) {
    console.debug('New sidebar toggle system loaded, skipping legacy handler');
    return;
  }
  
  function initLegacySidebarToggle() {
    const sidebar = document.querySelector('.app-sidebar');
    const toggleBtn = document.getElementById('sidebar-toggle');
    
    if (!toggleBtn) {
      setTimeout(initLegacySidebarToggle, 50);
      return;
    }
    
    if (!sidebar) return;
    
    // Skip if already handled
    if (window.__sidebarToggleHandled) {
      return;
    }

    // Mark as handled by legacy system (prevents duplicate binds)
    window.__sidebarToggleHandled = true;
    
    // Load saved state (desktop only)
    if (window.innerWidth > 1024) {
      try {
        if (localStorage.getItem('sidebarCollapsed') === 'true') {
          sidebar.classList.add('collapsed');
        }
      } catch(e) {}
    }
    
    toggleBtn.addEventListener('click', function(e) {
      e.preventDefault();
      e.stopPropagation();
      
      if (window.__sidebarToggleReady) return;
      
      if (window.innerWidth > 1024) {
        sidebar.classList.toggle('collapsed');
        try {
          localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
        } catch(e) {}
      } else {
        var isOpen = sidebar.classList.toggle('mobile-open');
        document.body.classList.toggle('sidebar-open', isOpen);
      }
    });
    
    // Close on outside click (mobile)
    document.addEventListener('click', function(e) {
      if (window.innerWidth <= 1024 && sidebar.classList.contains('mobile-open')) {
        if (!sidebar.contains(e.target) && !toggleBtn.contains(e.target)) {
          sidebar.classList.remove('mobile-open');
          document.body.classList.remove('sidebar-open');
        }
      }
    });
  }
  
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initLegacySidebarToggle);
  } else {
    initLegacySidebarToggle();
  }
})();

// Global function สำหรับปิด sidebar บนมือถือ (เรียกจากปุ่ม X)
function closeSidebarMobile() {
  const sidebar = document.querySelector('.app-sidebar');
  if (sidebar) {
    sidebar.classList.remove('mobile-open');
    document.body.classList.remove('sidebar-open');
  }
}

// ปิด sidebar ด้วย ESC key
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    closeSidebarMobile();
    // ปิด alert dialog ถ้ามี
    const alertOverlay = document.querySelector('.apple-alert-overlay');
    if (alertOverlay) {
      alertOverlay.remove();
    }
  }
});

// ===============================================
// Google Link Button Handler - Open in popup (with event delegation)
// ===============================================
// ✅ ใช้ event delegation เพื่อให้ใช้ได้กับปุ่มที่สร้างใหม่
(function() {
  document.addEventListener('click', async (e) => {
    const linkBtn = e.target.closest('.google-link-btn');
    if (!linkBtn || !linkBtn.href.includes('link_google.php')) return;
    
    e.preventDefault();
    e.stopPropagation();
    
    const width = 600;
    const height = 700;
    const left = window.outerWidth / 2 - width / 2;
    const top = window.outerHeight / 2 - height / 2;
    
    // เปิด popup สำหรับ Google OAuth
    const popup = window.open(
      linkBtn.href,
      'GoogleLinkPopup',
      `width=${width},height=${height},left=${left},top=${top},resizable=yes,scrollbars=yes`
    );
    
    if (!popup || popup.closed || typeof popup.closed === 'undefined') {
      await appleAlert('โปรแกรมบล็อก popup โปรดอนุญาตให้เปิด popup');
      return;
    }
    
    popup.focus();
    
    // ✅ ตัวข้อความมาจาก google_callback.php เมื่อ OAuth สำเร็จ หรือ เมื่อเกิดข้อผิดพลาด
    const messageHandler = async (event) => {
      if (!event.data || (!event.data.type)) return;
      
      // ✅ กรณี OAuth สำเร็จ
      if (event.data.type === 'google_link_success') {
        clearInterval(checkClosedInterval);
        window.removeEventListener('message', messageHandler);
        
        // รอสักครู่เพื่อให้ popup ปิดสมบูรณ์
        await new Promise(resolve => setTimeout(resolve, 500));
        
        try {
          // ตรวจสอบสถานะการเชื่อมผ่าน API
          const response = await fetch('/dormitory_management/api/check_google_link.php', {
            method: 'GET',
            headers: { 'Content-Type': 'application/json' }
          });
          
          const result = await response.json();
          
          if (result.success && result.linked) {
            // ✅ Google ถูกเชื่อมสำเร็จ
            window.location.reload();
          }
        } catch (error) {
          console.error('Error checking Google link:', error);
        }
      }
      
      // ❌ กรณี OAuth มีข้อผิดพลาด
      if (event.data.type === 'google_link_error') {
        clearInterval(checkClosedInterval);
        window.removeEventListener('message', messageHandler);
        
        if (window.AnimateUI && typeof window.AnimateUI.showNotification === 'function') {
          window.AnimateUI.showNotification(event.data.message || 'เกิดข้อผิดพลาดในการเชื่อมบัญชี Google', 'error');
        } else {
          await appleAlert('เกิดข้อผิดพลาด: ' + (event.data.message || 'ไม่ทราบสาเหตุ'));
        }
      }
    };
    
    window.addEventListener('message', messageHandler);
    
    // ตรวจสอบทุก 500ms ว่า popup ปิดแล้วหรือยัง (fallback)
    let checkClosedInterval = setInterval(async () => {
      if (popup.closed) {
        clearInterval(checkClosedInterval);
        window.removeEventListener('message', messageHandler);
        await new Promise(resolve => setTimeout(resolve, 1000));
        try {
          const response = await fetch('/dormitory_management/api/check_google_link.php', {
            method: 'GET',
            headers: { 'Content-Type': 'application/json' }
          });
          const result = await response.json();
          if (result.success) {
            if (result.linked) {
              window.location.reload();
            } else {
              console.log('User cancelled Google linking');
            }
          } else {
            console.error('Error checking Google link:', result.message);
          }
        } catch (error) {
          console.error('Error checking Google link status:', error);
        }
      }
    }, 500);
  });
})();
