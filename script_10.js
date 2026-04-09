
// Apple-style Alert Function (global — must be outside IIFE so onclick handlers can access)
function appleAlert(message, title = 'project.3bbddns.com:36140 บอกว่า') {
  return new Promise((resolve) => {
    const overlay = document.createElement('div');
    overlay.className = 'apple-alert-overlay';
    overlay.innerHTML = `
      <div class="apple-alert-dialog">
        <div class="apple-alert-content">
          <div class="apple-alert-title">${title}</div>
          <div class="apple-alert-message">${message}</div>
        </div>
        <div class="apple-alert-buttons">
          <button class="apple-alert-button primary">ตกลง</button>
        </div>
      </div>
    `;
    document.body.appendChild(overlay);
    const button = overlay.querySelector('.apple-alert-button');
    button.addEventListener('click', () => {
      overlay.style.animation = 'fadeOut 0.2s ease forwards';
      setTimeout(() => { overlay.remove(); resolve(); }, 200);
    });
    overlay.addEventListener('click', (e) => {
      if (e.target === overlay) {
        overlay.style.animation = 'fadeOut 0.2s ease forwards';
        setTimeout(() => { overlay.remove(); resolve(); }, 200);
      }
    });
  });
}

// Apple-style Confirm Function (global)
function appleConfirm(message, title = 'project.3bbddns.com:36140 บอกว่า') {
  return new Promise((resolve) => {
    const overlay = document.createElement('div');
    overlay.className = 'apple-alert-overlay';
    overlay.innerHTML = `
      <div class="apple-alert-dialog">
        <div class="apple-alert-content">
          <div class="apple-alert-title">${title}</div>
          <div class="apple-alert-message">${message}</div>
        </div>
        <div class="apple-alert-buttons">
          <button class="apple-alert-button">ยกเลิก</button>
          <button class="apple-alert-button destructive">ตกลง</button>
        </div>
      </div>
    `;
    document.body.appendChild(overlay);
    const buttons = overlay.querySelectorAll('.apple-alert-button');
    buttons[0].addEventListener('click', () => {
      overlay.style.animation = 'fadeOut 0.2s ease forwards';
      setTimeout(() => { overlay.remove(); resolve(false); }, 200);
    });
    buttons[1].addEventListener('click', () => {
      overlay.style.animation = 'fadeOut 0.2s ease forwards';
      setTimeout(() => { overlay.remove(); resolve(true); }, 200);
    });
    overlay.addEventListener('click', (e) => {
      if (e.target === overlay) {
        overlay.style.animation = 'fadeOut 0.2s ease forwards';
        setTimeout(() => { overlay.remove(); resolve(false); }, 200);
      }
    });
  });
}

// AJAX สำหรับลบบัญชี Google (global — called from onclick)
async function handleGoogleUnlink(e) {
  if (e && e.preventDefault) { e.preventDefault(); e.stopPropagation(); }
  const unlinkBtn = (e && e.currentTarget) || document.querySelector('.google-unlink-btn');
  if (!unlinkBtn) return;
  console.log('\u2713 Unlink button clicked');
  const sidebar = document.querySelector('[role="complementary"]') || document.querySelector('.sidebar') || document.querySelector('#sidebar');
  if (sidebar) sidebar.classList.remove('collapsed');
  console.log('\u2713 Showing confirmation dialog');
  const confirmed = await appleConfirm('คุณต้องการถอนการเชื่อมต่อบัญชี Google นี้หรือไม่?');
  if (!confirmed) { console.log('\u2713 User cancelled unlink'); return; }
  console.log('\u2713 User confirmed, starting unlink process');
  try {
    unlinkBtn.style.opacity = '0.5';
    unlinkBtn.style.pointerEvents = 'none';
    const response = await fetch('/dormitory_management/unlink_google.php', { method: 'GET', headers: { 'Content-Type': 'application/json' } });
    const result = await response.json();
    console.log('\u2713 Unlink result:', result);
    if (result.success) {
      if (sidebar) sidebar.classList.remove('collapsed');
      const avatarDiv = document.querySelector('.sidebar-footer .avatar');
      if (avatarDiv) { const img = avatarDiv.querySelector('img'); if (img) img.style.display='none'; const svg = avatarDiv.querySelector('svg'); if (svg) svg.style.display='block'; }
      const userRowAvatar = document.querySelector('.user-row .avatar');
      if (userRowAvatar) { const img = userRowAvatar.querySelector('img'); if (img) img.style.display='none'; const svg = userRowAvatar.querySelector('svg'); if (svg) svg.style.display='block'; }
      const railUser = document.querySelector('.rail-user');
      if (railUser) { const img = railUser.querySelector('img'); if (img) img.style.display='none'; const span = railUser.querySelector('span.app-nav-icon'); if (span) span.style.display='inline-block'; }
      const googleLinkWrap = unlinkBtn.closest('.google-link-wrap');
      if (googleLinkWrap) {
        googleLinkWrap.innerHTML = `
          <a href="/dormitory_management/link_google.php?action=link" class="google-link-btn">
            <svg class="google-icon" viewBox="0 0 24 24" width="16" height="16"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>
            <span class="app-nav-label">เชื่อมบัญชี Google</span>
          </a>
        `;
        if (window.AnimateUI && typeof window.AnimateUI.showNotification === 'function') {
          window.AnimateUI.showNotification('ถอนการเชื่อมต่อบัญชี Google', 'success');
        }
      }
    } else {
      console.error('\u2717 Unlink failed:', result.message);
      if (window.AnimateUI && typeof window.AnimateUI.showNotification === 'function') {
        window.AnimateUI.showNotification(result.message, 'error');
      } else {
        await appleAlert('เกิดข้อผิดพลาด: ' + result.message);
      }
      unlinkBtn.style.opacity = '1';
      unlinkBtn.style.pointerEvents = 'auto';
    }
  } catch (error) {
    console.error('\u2717 Exception during unlink:', error);
    await appleAlert('เกิดข้อผิดพลาดในการเชื่อมต่อ: ' + error.message);
  }
}
