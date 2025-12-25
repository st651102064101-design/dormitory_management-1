// Toast Notification System
// ใช้สำหรับแสดงข้อความแจ้งเตือนแบบไม่บล็อกการทำงาน

// สร้าง container สำหรับ toast ทั้งหมด
function getToastContainer() {
  let container = document.getElementById('toast-container');
  if (!container) {
    container = document.createElement('div');
    container.id = 'toast-container';
    container.style.cssText = `
      position: fixed;
      bottom: 0;
      right: 0;
      padding: 2rem;
      display: flex;
      flex-direction: column;
      align-items: flex-end;
      gap: 1rem;
      z-index: 9999;
      pointer-events: none;
    `;
    document.body.appendChild(container);
  }
  return container;
}

/**
 * แสดง toast notification
 * @param {string} title - หัวข้อข้อความ
 * @param {string} message - ข้อความ
 * @param {string} type - ประเภท: 'success', 'error', 'warning', 'info'
 */
function showToast(title, message, type = 'success') {
  // กำหนดสีตามประเภท
  const colors = {
    success: 'linear-gradient(135deg, #22c55e, #16a34a)',
    error: 'linear-gradient(135deg, #ef4444, #dc2626)',
    warning: 'linear-gradient(135deg, #f59e0b, #d97706)',
    info: 'linear-gradient(135deg, #3b82f6, #2563eb)'
  };
  
  const container = getToastContainer();
  
  // สร้าง toast notification
  const toast = document.createElement('div');
  const bgColor = colors[type] || colors.success;
  toast.style.cssText = `
    background: ${bgColor};
    color: #fff;
    padding: 1rem 1.5rem;
    border-radius: 12px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
    min-width: 300px;
    max-width: 400px;
    transform: translateX(150%);
    transition: transform 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
    cursor: pointer;
    pointer-events: auto;
  `;
  
  // Title
  const titleEl = document.createElement('div');
  titleEl.textContent = title;
  titleEl.style.cssText = 'font-weight:700; font-size:1rem; margin-bottom:0.25rem;';
  
  // Message
  const messageEl = document.createElement('div');
  messageEl.textContent = message;
  messageEl.style.cssText = 'font-size:0.9rem; opacity:0.95;';
  
  toast.appendChild(titleEl);
  toast.appendChild(messageEl);
  container.appendChild(toast);
  
  // Animate in
  requestAnimationFrame(() => {
    toast.style.transform = 'translateX(0)';
  });
  
  // ฟังก์ชันปิด toast
  const closeToast = () => {
    toast.style.transform = 'translateX(150%)';
    setTimeout(() => toast.remove(), 400);
  };
  
  // ปิดอัตโนมัติหลัง 3 วินาที
  setTimeout(closeToast, 3000);
  
  // คลิกเพื่อปิดก่อนเวลา
  toast.onclick = closeToast;
}

// ฟังก์ชันสำหรับการดำเนินการเฉพาะ
function showSuccessToast(message) {
  showToast('สำเร็จ', message, 'success');
}

function showErrorToast(message) {
  showToast('เกิดข้อผิดพลาด', message, 'error');
}

function showAddSuccessToast() {
  showToast('สำเร็จ', 'เพิ่มข้อมูลเรียบร้อยแล้ว', 'success');
}

function showEditSuccessToast() {
  showToast('สำเร็จ', 'แก้ไขข้อมูลเรียบร้อยแล้ว', 'success');
}

function showDeleteSuccessToast() {
  showToast('สำเร็จ', 'ลบข้อมูลเรียบร้อยแล้ว', 'success');
}
