/**
 * Custom Confirmation Dialog
 * แสดง modal ยืนยันที่สวยงามแทน browser confirm()
 * 
 * @param {string} title - หัวข้อของ modal
 * @param {string} message - ข้อความที่ต้องการแสดง (รองรับ HTML)
 * @param {string} type - ประเภท: 'delete' (แดง) หรือ 'warning' (สีส้ม) - default: 'delete'
 * @returns {Promise<boolean>} - คืนค่า true ถ้ากดยืนยัน, false ถ้ายกเลิก
 * 
 * ตัวอย่างการใช้งาน:
 * const confirmed = await showConfirmDialog('ยืนยันการลบ', 'คุณต้องการลบรายการนี้หรือไม่?', 'delete');
 * if (confirmed) {
 *   // ทำงานเมื่อกดยืนยัน
 * }
 */
function showConfirmDialog(title, message, type = 'delete') {
  return new Promise((resolve) => {
    // Helper function เพื่อ escape HTML
    function escapeHtml(text) {
      const div = document.createElement('div');
      div.textContent = text;
      return div.innerHTML;
    }
    
    const buttonClass = type === 'warning' ? 'confirm-btn-warning' : 'confirm-btn-delete';
    
    const overlay = document.createElement('div');
    overlay.className = 'confirm-overlay';
    overlay.innerHTML = `
      <div class="confirm-modal">
        <div class="confirm-header">
          <div class="confirm-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="12" cy="12" r="10"/>
              <line x1="12" y1="8" x2="12" y2="12"/>
              <line x1="12" y1="16" x2="12.01" y2="16"/>
            </svg>
          </div>
          <h3 class="confirm-title">${escapeHtml(title)}</h3>
        </div>
        <div class="confirm-message">${message}</div>
        <div class="confirm-actions">
          <button class="confirm-btn confirm-btn-cancel" data-action="cancel">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <line x1="18" y1="6" x2="6" y2="18"/>
              <line x1="6" y1="6" x2="18" y2="18"/>
            </svg>
            ยกเลิก
          </button>
          <button class="confirm-btn ${buttonClass}" data-action="confirm">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <polyline points="20 6 9 17 4 12"/>
            </svg>
            ยืนยัน
          </button>
        </div>
      </div>
    `;
    
    // คลิกนอก modal เพื่อปิด
    overlay.addEventListener('click', (e) => {
      if (e.target === overlay) {
        overlay.remove();
        resolve(false);
      }
    });
    
    // จัดการปุ่ม
    overlay.querySelectorAll('[data-action]').forEach(btn => {
      btn.addEventListener('click', () => {
        const confirmed = btn.dataset.action === 'confirm';
        overlay.remove();
        resolve(confirmed);
      });
    });
    
    // กด ESC เพื่อปิด
    const handleKeyDown = (e) => {
      if (e.key === 'Escape') {
        overlay.remove();
        resolve(false);
        document.removeEventListener('keydown', handleKeyDown);
      }
    };
    document.addEventListener('keydown', handleKeyDown);
    
    document.body.appendChild(overlay);
  });
}
