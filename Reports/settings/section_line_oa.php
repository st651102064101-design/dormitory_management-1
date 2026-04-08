<!-- Section: Line Notification -->
<div class="apple-section-group">
  <h2 class="apple-section-title">LINE OA Notifications (แจ้งเตือนผ่านไลน์)</h2>
  <div class="apple-section-card">
    
    <!-- LINE Status -->
    <div class="apple-settings-row">
      <div class="apple-row-icon line-oa-icon" style="background: linear-gradient(135deg, #00C300, #00A300);">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-animated">
          <path d="M21 11.5a8.38 8.38 0 0 1-9 8.3c-1.72-.08-3.45-.63-4.9-1.63L3 19l1.45-3.5a10.87 10.87 0 0 1-1.45-4 8.38 8.38 0 0 1 9-8.3 8.38 8.38 0 0 1 9 8.3z"/>
        </svg>
      </div>
      <div class="apple-row-content">
        <p class="apple-row-label">สถานะการทำงาน LINE OA</p>
        <p class="apple-row-sublabel"><?php echo (!empty($lineChannelToken) && !empty($lineChannelSecret)) ? 'เปิดใช้งานอยู่' : 'ยังไม่ได้เชื่อมต่อ'; ?></p>
      </div>
      <span class="apple-row-badge <?php echo (!empty($lineChannelToken) && !empty($lineChannelSecret)) ? 'success' : 'warning'; ?>">
        <?php echo (!empty($lineChannelToken) && !empty($lineChannelSecret)) ? 'เปิดใช้งานแล้ว' : 'ปิดใช้งาน'; ?>
      </span>
    </div>
    
    <!-- LINE Channel Access Token -->
    <div class="apple-settings-row" data-sheet="sheet-line-channel-token">
      <div class="apple-row-icon green">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-animated">
          <path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/>
        </svg>
      </div>
      <div class="apple-row-content">
        <p class="apple-row-label">Channel Access Token</p>
        <p class="apple-row-sublabel"><?php echo !empty($lineChannelToken) ? substr($lineChannelToken, 0, 15) . '...' : 'ยังไม่ได้กำหนด'; ?></p>
      </div>
      <span class="apple-row-chevron">›</span>
    </div>
    
    <!-- LINE Channel Secret -->
    <div class="apple-settings-row" data-sheet="sheet-line-channel-secret">
      <div class="apple-row-icon orange">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-animated">
          <circle cx="12" cy="12" r="3"/>
          <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/>
        </svg>
      </div>
      <div class="apple-row-content">
        <p class="apple-row-label">Channel Secret</p>
        <p class="apple-row-sublabel"><?php echo !empty($lineChannelSecret) ? '••••••••••••' : 'ยังไม่ได้กำหนด'; ?></p>
      </div>
      <span class="apple-row-chevron">›</span>
    </div>

    <!-- Broadcast Message -->
    <div class="apple-settings-row" data-sheet="sheet-line-broadcast">
      <div class="apple-row-icon blue">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-animated">
          <path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"/>
        </svg>
      </div>
      <div class="apple-row-content">
        <p class="apple-row-label">ส่งข้อความประกาศ (Broadcast)</p>
        <p class="apple-row-sublabel">ส่งข้อความถึงผู้ติดตาม LINE OA ทุกคน</p>
      </div>
      <span class="apple-row-chevron">›</span>
    </div>

    <!-- LINE Console Link -->
    <div class="apple-settings-row" onclick="window.open('https://developers.line.biz/console/', '_blank')">
      <div class="apple-row-icon line-oa-icon" style="background: linear-gradient(135deg, #00C300, #00A300);">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-animated">
          <circle cx="12" cy="12" r="10"/>
          <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/>
          <line x1="12" y1="17" x2="12.01" y2="17"/>
        </svg>
      </div>
      <div class="apple-row-content">
        <p class="apple-row-label">จัดการที่ LINE Console</p>
        <p class="apple-row-sublabel">เปิดหน้าต่างใหม่เพื่อตั้งค่าใน LINE Developers</p>
      </div>
      <span class="apple-row-chevron">↗</span>
    </div>
  </div>
</div>

<!-- ==============================================
     BOTTOM SHEETS (สำหรับ LINE OA)
     ============================================== -->

<!-- Sheet: LINE Channel Token -->
<div class="apple-sheet-overlay" id="sheet-line-channel-token">
  <div class="apple-sheet">
    <div class="apple-sheet-handle"></div>
    <div class="apple-sheet-header">
      <button class="apple-sheet-action" data-close-sheet="sheet-line-channel-token">ยกเลิก</button>
      <h3 class="apple-sheet-title">Channel Access Token</h3>
      <div style="width: 50px;"></div>
    </div>
    <div class="apple-sheet-body">
      <form id="lineChannelTokenForm">
        <div class="apple-input-group">
          <label class="apple-input-label">Channel Access Token (Long-lived)</label>
          <textarea id="lineChannelTokenInput" name="lineChannelToken" class="apple-input" rows="6" style="resize:none;" placeholder="xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"><?php echo htmlspecialchars($lineChannelToken); ?></textarea>
          <p style="font-size: 13px; color: var(--apple-text-secondary); margin-top: 8px;">Token สำหรับให้ระบบส่งข้อความแจ้งเตือนอัตโนมัติหานักศึกษาจาก LINE Developers Console</p>
        </div>
        <button type="submit" class="apple-button primary">บันทึก</button>
      </form>
    </div>
  </div>
</div>

<!-- Sheet: LINE Channel Secret -->
<div class="apple-sheet-overlay" id="sheet-line-channel-secret">
  <div class="apple-sheet">
    <div class="apple-sheet-handle"></div>
    <div class="apple-sheet-header">
      <button class="apple-sheet-action" data-close-sheet="sheet-line-channel-secret">ยกเลิก</button>
      <h3 class="apple-sheet-title">Channel Secret</h3>
      <div style="width: 50px;"></div>
    </div>
    <div class="apple-sheet-body">
      <form id="lineChannelSecretForm">
        <div class="apple-input-group">
          <label class="apple-input-label">Channel Secret</label>
          <input type="password" id="lineChannelSecretInput" name="lineChannelSecret" class="apple-input" value="<?php echo htmlspecialchars($lineChannelSecret); ?>" placeholder="xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx">
          <p style="font-size: 13px; color: var(--apple-text-secondary); margin-top: 8px;">รหัส Channel Secret จาก LINE Developers Console</p>
        </div>
        <button type="submit" class="apple-button primary">บันทึก</button>
      </form>
    </div>
  </div>
</div>

<!-- Sheet: Broadcast Message -->
<div class="apple-sheet-overlay" id="sheet-line-broadcast">
  <div class="apple-sheet-container" style="background: var(--apple-card-bg); border-radius: 20px; padding: 24px; max-width: 400px; width: 90%; margin: 40px auto;">
    <div class="apple-sheet-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
      <h3 style="margin: 0; font-size: 18px; font-weight: 600;">ส่งข้อความประกาศ</h3>
      <button type="button" class="apple-sheet-close" onclick="closeSheet('sheet-line-broadcast')" style="background: none; border: none; font-size: 20px; color: var(--apple-text-secondary); cursor: pointer;">&times;</button>
    </div>
    <div class="apple-sheet-body">
      <form id="lineBroadcastForm">
        <div class="apple-input-group">
          <label class="apple-input-label">ข้อความ Broadcast</label>
          <textarea id="lineBroadcastMessage" name="message" class="apple-input" rows="5" placeholder="พิมพ์ข้อความที่ต้องการแจ้งเตือนไปยังลูกหอทุกคน (ต้องแอด Line บอทรับไว้แล้ว)" required style="resize:vertical;"></textarea>
          <p style="font-size: 13px; color: var(--apple-text-secondary); margin-top: 8px;">* จะส่งถึง User ทุกคนที่รับข้อความจากระบบ</p>
        </div>
        <button type="submit" class="apple-button primary" style="width: 100%; margin-top: 16px;">ส่งข้อความทันที</button>
      </form>
    </div>
  </div>
</div>

<script>
// LINE Broadcast Form
document.getElementById('lineBroadcastForm')?.addEventListener('submit', async function(e) {
  e.preventDefault();
  const value = document.getElementById('lineBroadcastMessage').value;
  if (!value.trim()) return;
  if (!confirm('ต้องการส่งข้อความ Broadcast ไปยังทุกคนหรือไม่?')) return;
  
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
      closeSheet('sheet-line-broadcast');
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
      closeSheet('sheet-line-channel-token');
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
      closeSheet('sheet-line-channel-secret');
      setTimeout(() => location.reload(), 1000);
    } else {
      showAppleToast('เกิดข้อผิดพลาด: ' + data.error, 'error');
    }
  } catch (err) {
    showAppleToast('เกิดข้อผิดพลาดในการเชื่อมต่อ', 'error');
  }
});
</script>