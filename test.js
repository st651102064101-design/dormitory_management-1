<script>
window.showAppleToast = window.showAppleToast || function(msg, type) {
  if (window.appleSettings && typeof window.appleSettings.showToast === 'function') {
    window.appleSettings.showToast(msg, type);
  } else {
    alert(msg);
  }
};

function closeLineSheet(sheetId) {
  if (window.appleSettings && typeof window.appleSettings.closeSheet === 'function') {
    window.appleSettings.closeSheet(sheetId);
    return;
  }

  const overlay = document.getElementById(sheetId);
  if (!overlay) {
    return;
  }

  overlay.classList.remove('active');
  if (!document.querySelector('.apple-sheet-overlay.active')) {
    document.body.style.overflow = '';
  }
}

// LINE Broadcast Form
document.getElementById('lineBroadcastForm')?.addEventListener('submit', async function(e) {
  e.preventDefault();
  const value = document.getElementById('lineBroadcastMessage').value;
  if (!value.trim()) return;
  
  try {
    const res = await fetch('../Manage/send_line_broadcast.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `message=${encodeURIComponent(value)}`
    });
    const data = await res.json();
    if (data.success) {
      showAppleToast('ส่งข้อความ Broadcast สำเร็จ', 'success');
      document.getElementById('lineBroadcastMessage').value = '';
      closeLineSheet('sheet-line-broadcast');
    } else {
      showAppleToast('ส่งไม่สำเร็จ: ' + data.error, 'error');
    }
  } catch (err) {
    showAppleToast('เกิดข้อผิดพลาดในการเชื่อมต่อ', 'error');
  }
});

// LINE Channel Token Form
document.getElementById('lineChannelTokenForm')?.addEventListener('submit', async function(e) {
  e.preventDefault();
  const value = document.getElementById('lineChannelTokenInput').value;
  
  try {
    const res = await fetch('../Manage/save_system_settings.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `line_channel_token=${encodeURIComponent(value)}`
    });
    const data = await res.json();
    if (data.success) {
      showAppleToast('บันทึก Channel Token สำเร็จ', 'success');
      closeLineSheet('sheet-line-channel-token');
      setTimeout(() => location.reload(), 1000);
    } else {
      showAppleToast('เกิดข้อผิดพลาด: ' + data.error, 'error');
    }
  } catch (err) {
    showAppleToast('เกิดข้อผิดพลาดในการเชื่อมต่อ', 'error');
  }
});

// LINE Channel Secret Form
document.getElementById('lineChannelSecretForm')?.addEventListener('submit', async function(e) {
  e.preventDefault();
  const value = document.getElementById('lineChannelSecretInput').value;
  
  try {
    const res = await fetch('../Manage/save_system_settings.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `line_channel_secret=${encodeURIComponent(value)}`
    });
    const data = await res.json();
    if (data.success) {
      showAppleToast('บันทึก Channel Secret สำเร็จ', 'success');
      closeLineSheet('sheet-line-channel-secret');
      setTimeout(() => location.reload(), 1000);
    } else {
      showAppleToast('เกิดข้อผิดพลาด: ' + data.error, 'error');
    }
  } catch (err) {
    showAppleToast('เกิดข้อผิดพลาดในการเชื่อมต่อ', 'error');
  }
});

// LINE Login Channel ID
document.getElementById('lineLoginChannelIdForm')?.addEventListener('submit', async function(e) {
  e.preventDefault();
  const value = document.getElementById('lineLoginChannelIdInput').value;
  try {
    const res = await fetch('../Manage/save_system_settings.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `line_login_channel_id=${encodeURIComponent(value)}`
    });
    const data = await res.json();
    if (data.success) {
      showAppleToast('บันทึก LINE Login Channel ID สำเร็จ', 'success');
      closeLineSheet('sheet-line-login-channel-id');
      const lbl = document.getElementById('lbl-line-login-channel-id');
      if (lbl) lbl.textContent = value.trim() ? 'ตั้งค่าแล้ว' : 'ยังไม่ได้ตั้งค่า';
    }
  } catch (err) { showAppleToast('เกิดข้อผิดพลาด', 'error'); }
});

// LINE Login Channel Secret
document.getElementById('lineLoginChannelSecretForm')?.addEventListener('submit', async function(e) {
  e.preventDefault();
  const input = document.getElementById('lineLoginChannelSecretInput');
  const value = input.value;
  if(value === '********') {
    closeLineSheet('sheet-line-login-channel-secret');
    return;
  }
  try {
    const res = await fetch('../Manage/save_system_settings.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `line_login_channel_secret=${encodeURIComponent(value)}`
    });
    const data = await res.json();
    if (data.success) {
      showAppleToast('บันทึก LINE Login Channel Secret สำเร็จ', 'success');
      closeLineSheet('sheet-line-login-channel-secret');
      const lbl = document.getElementById('lbl-line-login-channel-secret');
      if (lbl) lbl.textContent = value.trim() ? 'ตั้งค่าแล้ว (ซ่อนเพื่อความปลอดภัย)' : 'ยังไม่ได้ตั้งค่า';
      input.value = value.trim() ? '********' : '';
    }
  } catch (err) { showAppleToast('เกิดข้อผิดพลาด', 'error'); }
});

// LINE Add Friend URL Form
document.getElementById('lineAddFriendUrlForm')?.addEventListener('submit', async function(e) {
  e.preventDefault();
  const value = document.getElementById('lineAddFriendUrlInput').value;
  
  try {
    const res = await fetch('../Manage/save_system_settings.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `line_add_friend_url=${encodeURIComponent(value)}`
    });
    const data = await res.json();
    if (data.success) {
      showAppleToast('บันทึกลิงก์เพิ่มเพื่อนสำเร็จ', 'success');
      closeLineSheet('sheet-line-add-friend-url');
      setTimeout(() => location.reload(), 1000);
    } else {
      showAppleToast('เกิดข้อผิดพลาด: ' + data.error, 'error');
    }
  } catch (err) {
    showAppleToast('เกิดข้อผิดพลาดในการเชื่อมต่อ', 'error');
  }
});

// LINE QR Code Form Upload
document.getElementById('lineQrCodeForm')?.addEventListener('submit', async function(e) {
  e.preventDefault();
  const fileInput = document.getElementById('lineQrInput');
  if (!fileInput.files.length) {
    showAppleToast('กรุณาเลือกไฟล์ก่อน', 'warning');
    return;
  }
  
  const formData = new FormData();
  formData.append('line_qr', fileInput.files[0]);
  
  try {
    const res = await fetch('../Manage/save_system_settings.php', {
      method: 'POST',
      body: formData
    });
    const data = await res.json();
    if (data.success) {
      showAppleToast('อัปโหลดภาพเรียบร้อย', 'success');
      closeLineSheet('sheet-line-qr-code');
      setTimeout(() => location.reload(), 1000);
    } else {
      showAppleToast('ผิดพลาด: ' + data.error, 'error');
    }
  } catch (err) {
    showAppleToast('เกิดข้อผิดพลาดในการเชื่อมต่อ', 'error');
  }
});

// Delete LINE QR Code
document.getElementById('btnDeleteLineQr')?.addEventListener('click', async function() {
  if (!confirm('ยืนยันลบภาพ QR Code ใช่หรือไม่?')) return;
  
  try {
    const res = await fetch('../Manage/save_system_settings.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'delete_line_qr=1'
    });
    const data = await res.json();
    if (data.success) {
      showAppleToast('ลบภาพสำเร็จ', 'success');
      setTimeout(() => location.reload(), 1000);
    } else {
      showAppleToast('ลบไม่สำเร็จ: ' + data.error, 'error');
    }
  } catch (err) {
    showAppleToast('เกิดข้อผิดพลาดในการเชื่อมต่อ', 'error');
  }
});
</script>