<!-- Section: Google Authentication -->
<div class="apple-section-group">
  <h2 class="apple-section-title">Google Authentication</h2>
  <div class="apple-section-card">
    <!-- Google Status -->
    <div class="apple-settings-row">
      <div class="apple-row-icon" style="background: linear-gradient(135deg, #4285F4, #34A853);">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-animated">
          <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/>
          <polyline points="10 17 15 12 10 7"/>
          <line x1="15" y1="12" x2="3" y2="12"/>
        </svg>
      </div>
      <div class="apple-row-content">
        <p class="apple-row-label">สถานะ Google Login</p>
        <p class="apple-row-sublabel"><?php echo !empty($googleClientId) ? 'เปิดใช้งานแล้ว' : 'ยังไม่ได้ตั้งค่า'; ?></p>
      </div>
      <span class="apple-row-badge <?php echo !empty($googleClientId) ? 'success' : 'warning'; ?>">
        <?php echo !empty($googleClientId) ? 'เปิดใช้' : 'ปิด'; ?>
      </span>
    </div>
    
    <!-- Google Client ID -->
    <div class="apple-settings-row" data-sheet="sheet-google-client-id">
      <div class="apple-row-icon blue">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-animated">
          <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
          <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
        </svg>
      </div>
      <div class="apple-row-content">
        <p class="apple-row-label">Client ID</p>
        <p class="apple-row-sublabel"><?php echo !empty($googleClientId) ? substr($googleClientId, 0, 30) . '...' : 'ยังไม่ได้กำหนด'; ?></p>
      </div>
      <span class="apple-row-chevron">›</span>
    </div>
    
    <!-- Google Client Secret -->
    <div class="apple-settings-row" data-sheet="sheet-google-client-secret">
      <div class="apple-row-icon orange">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-animated">
          <circle cx="12" cy="12" r="3"/>
          <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/>
        </svg>
      </div>
      <div class="apple-row-content">
        <p class="apple-row-label">Client Secret</p>
        <p class="apple-row-sublabel"><?php echo !empty($googleClientSecret) ? '••••••••••••' : 'ยังไม่ได้กำหนด'; ?></p>
      </div>
      <span class="apple-row-chevron">›</span>
    </div>
    
    <!-- Google Redirect URI (Read Only) -->
    <div class="apple-settings-row">
      <div class="apple-row-icon purple">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-animated">
          <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/>
          <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>
        </svg>
      </div>
      <div class="apple-row-content">
        <p class="apple-row-label">Redirect URI</p>
        <p class="apple-row-sublabel" style="font-size: 11px; word-break: break-all;"><?php echo htmlspecialchars($googleRedirectUri); ?></p>
      </div>
    </div>
  </div>
  
  <!-- Help Section -->
  <div class="apple-section-card" style="margin-top: 12px;">
    <div class="apple-settings-row" onclick="window.open('https://console.cloud.google.com/apis/credentials', '_blank')">
      <div class="apple-row-icon" style="background: linear-gradient(135deg, #4285F4, #34A853);">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-animated">
          <circle cx="12" cy="12" r="10"/>
          <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/>
          <line x1="12" y1="17" x2="12.01" y2="17"/>
        </svg>
      </div>
      <div class="apple-row-content">
        <p class="apple-row-label">ตั้งค่า Google Cloud Console</p>
        <p class="apple-row-sublabel">เปิด Google Cloud Console เพื่อสร้าง OAuth Credentials</p>
      </div>
      <span class="apple-row-chevron">›</span>
    </div>
  </div>
  
  <p class="apple-section-footer">
    สร้าง OAuth 2.0 Client ID จาก Google Cloud Console<br>
    กำหนด Authorized redirect URI เป็น: <br>
    <code style="background: rgba(0,0,0,0.1); padding: 4px 8px; border-radius: 4px; font-size: 11px;">
      <?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $googleRedirectUri; ?>
    </code>
  </p>
</div>

<!-- Sheet: Google Client ID -->
<div class="apple-sheet-overlay" id="sheet-google-client-id">
  <div class="apple-sheet">
    <div class="apple-sheet-handle"></div>
    <div class="apple-sheet-header">
      <button class="apple-sheet-action" data-close-sheet="sheet-google-client-id">ยกเลิก</button>
      <h3 class="apple-sheet-title">Google Client ID</h3>
      <div style="width: 50px;"></div>
    </div>
    <div class="apple-sheet-body">
      <form id="googleClientIdForm">
        <div class="apple-input-group">
          <label class="apple-input-label">Client ID</label>
          <input type="text" id="googleClientId" name="googleClientId" class="apple-input" value="<?php echo htmlspecialchars($googleClientId); ?>" placeholder="xxxxxxxxxxxx.apps.googleusercontent.com">
          <p style="font-size: 13px; color: var(--apple-text-secondary); margin-top: 8px;">Client ID จาก Google Cloud Console</p>
        </div>
        <button type="submit" class="apple-button primary">บันทึก</button>
      </form>
    </div>
  </div>
</div>

<!-- Sheet: Google Client Secret -->
<div class="apple-sheet-overlay" id="sheet-google-client-secret">
  <div class="apple-sheet">
    <div class="apple-sheet-handle"></div>
    <div class="apple-sheet-header">
      <button class="apple-sheet-action" data-close-sheet="sheet-google-client-secret">ยกเลิก</button>
      <h3 class="apple-sheet-title">Google Client Secret</h3>
      <div style="width: 50px;"></div>
    </div>
    <div class="apple-sheet-body">
      <form id="googleClientSecretForm">
        <div class="apple-input-group">
          <label class="apple-input-label">Client Secret</label>
          <input type="password" id="googleClientSecret" name="googleClientSecret" class="apple-input" value="<?php echo htmlspecialchars($googleClientSecret); ?>" placeholder="GOCSPX-xxxxxxxxxxxxx">
          <p style="font-size: 13px; color: var(--apple-text-secondary); margin-top: 8px;">Client Secret จาก Google Cloud Console (ห้ามเปิดเผย)</p>
        </div>
        <button type="submit" class="apple-button primary">บันทึก</button>
      </form>
    </div>
  </div>
</div>

<script>
// Google Client ID Form
document.getElementById('googleClientIdForm')?.addEventListener('submit', async function(e) {
  e.preventDefault();
  const value = document.getElementById('googleClientId').value;
  
  try {
    const res = await fetch('settings/save_setting.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ key: 'google_client_id', value: value })
    });
    const data = await res.json();
    
    if (data.success) {
      showAppleToast('บันทึก Client ID เรียบร้อย', 'success');
      closeSheet('sheet-google-client-id');
      setTimeout(() => location.reload(), 1000);
    } else {
      showAppleToast('เกิดข้อผิดพลาด: ' + data.error, 'error');
    }
  } catch (err) {
    showAppleToast('เกิดข้อผิดพลาดในการเชื่อมต่อ', 'error');
  }
});

// Google Client Secret Form
document.getElementById('googleClientSecretForm')?.addEventListener('submit', async function(e) {
  e.preventDefault();
  const value = document.getElementById('googleClientSecret').value;
  
  try {
    const res = await fetch('settings/save_setting.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ key: 'google_client_secret', value: value })
    });
    const data = await res.json();
    
    if (data.success) {
      showAppleToast('บันทึก Client Secret เรียบร้อย', 'success');
      closeSheet('sheet-google-client-secret');
      setTimeout(() => location.reload(), 1000);
    } else {
      showAppleToast('เกิดข้อผิดพลาด: ' + data.error, 'error');
    }
  } catch (err) {
    showAppleToast('เกิดข้อผิดพลาดในการเชื่อมต่อ', 'error');
  }
});
</script>
