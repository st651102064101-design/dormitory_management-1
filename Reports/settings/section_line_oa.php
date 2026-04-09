<!-- Section: Line Notification -->
<?php
$lineTokenTrim = trim((string)($lineChannelToken ?? ''));
$lineSecretTrim = trim((string)($lineChannelSecret ?? ''));
$lineTokenLooksValid = ($lineTokenTrim !== '') && (preg_match('/^\d+$/', $lineTokenTrim) !== 1) && (strlen($lineTokenTrim) >= 30);
$lineReady = $lineTokenLooksValid && ($lineSecretTrim !== '');
?>
<div class="apple-section-group">
  <h2 class="apple-section-title">LINE OA Notifications (แจ้งเตือนผ่านไลน์)</h2>
  <div class="apple-section-card">
    
    <!-- LINE Status -->
    <div class="apple-settings-row" data-sheet="sheet-line-channel-token">
      <div class="apple-row-icon line-oa-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-animated">
          <path d="M21 11.5a8.38 8.38 0 0 1-9 8.3c-1.72-.08-3.45-.63-4.9-1.63L3 19l1.45-3.5a10.87 10.87 0 0 1-1.45-4 8.38 8.38 0 0 1 9-8.3 8.38 8.38 0 0 1 9 8.3z"/>
        </svg>
      </div>
      <div class="apple-row-content">
        <p class="apple-row-label">สถานะการทำงาน LINE OA</p>
        <p class="apple-row-sublabel">
          <?php
          if ($lineReady) {
              echo 'เปิดใช้งานอยู่';
          } elseif ($lineTokenTrim !== '' && !$lineTokenLooksValid) {
              echo 'Token ไม่ถูกต้อง (ดูเหมือน Channel ID)';
          } else {
              echo 'ยังไม่ได้เชื่อมต่อ';
          }
          ?>
        </p>
      </div>
      <span class="apple-row-badge <?php echo $lineReady ? 'success' : 'warning'; ?>">
        <?php echo $lineReady ? 'เปิดใช้งานแล้ว' : 'ปิดใช้งาน'; ?>
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
        <p class="apple-row-sublabel">
          <?php
          if ($lineTokenTrim === '') {
              echo 'ยังไม่ได้กำหนด';
          } elseif (!$lineTokenLooksValid) {
              echo 'รูปแบบไม่ถูกต้อง (กรุณาวาง Long-lived token)';
          } else {
              echo substr($lineTokenTrim, 0, 15) . '...';
          }
          ?>
        </p>
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
        <p class="apple-row-label">ส่งข้อความประกาศ (Broadcast/Multicast)</p>
        <p class="apple-row-sublabel">ส่งข้อความถึงผู้เช่าที่ผูกเบอร์ในระบบ</p>
      </div>
      <span class="apple-row-chevron">›</span>
    </div>

    <!-- URL สำหรับเพิ่มเพื่อน -->
    <?php
    $lineAddFriendUrl = '';
    $lineQrCodeImage = '';
    $lineLoginChannelId = '';
    $lineLoginChannelSecret = '';
    try {
        $stmtLineOpts = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('line_add_friend_url', 'line_qr_code_image', 'line_login_channel_id', 'line_login_channel_secret')");
        $lineSettingsOpts = $stmtLineOpts->fetchAll(PDO::FETCH_KEY_PAIR);
        $lineAddFriendUrl = $lineSettingsOpts['line_add_friend_url'] ?? '';
        $lineQrCodeImage = $lineSettingsOpts['line_qr_code_image'] ?? '';
        $lineLoginChannelId = $lineSettingsOpts['line_login_channel_id'] ?? '';
        $lineLoginChannelSecret = $lineSettingsOpts['line_login_channel_secret'] ?? '';
    } catch (Exception $e) { }
    ?>
    <div class="apple-settings-row" data-sheet="sheet-line-add-friend-url">
      <div class="apple-row-icon line-oa-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-animated">
          <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="8.5" cy="7" r="4"></circle><line x1="20" y1="8" x2="20" y2="14"></line><line x1="23" y1="11" x2="17" y2="11"></line>
        </svg>
      </div>
      <div class="apple-row-content">
        <p class="apple-row-label">URL เพิ่มเพื่อน (สำหรับกดลิงก์)</p>
        <p class="apple-row-sublabel"><?php echo !empty($lineAddFriendUrl) ? htmlspecialchars($lineAddFriendUrl) : 'ยังไม่ได้เชื่อมต่อ URL (ปุ่มจะไม่มีลิงก์)'; ?></p>
      </div>
      <span class="apple-row-chevron">›</span>
    </div>
    
    <!-- ภาพ QR Code LINE -->
    <div class="apple-settings-row" data-sheet="sheet-line-qr-code">
      <div class="apple-row-icon line-oa-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-animated">
          <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><rect x="7" y="7" width="3" height="3"></rect><rect x="14" y="7" width="3" height="3"></rect><rect x="7" y="14" width="3" height="3"></rect><rect x="14" y="14" width="3" height="3"></rect>
        </svg>
      </div>
      <div class="apple-row-content">
        <p class="apple-row-label">ภาพ QR Code LINE (แทนการสุ่มอัตโนมัติ)</p>
        <p class="apple-row-sublabel">
          <?php echo !empty($lineQrCodeImage) ? "อัปโหลดแล้ว (" . htmlspecialchars($lineQrCodeImage) . ")" : "ยังไม่ได้อัปโหลด (ระบบจะสุ่มสร้างจาก URL อัตโนมัติแทน)"; ?>
        </p>
      </div>
      <span class="apple-row-chevron">›</span>
    </div>

    <!-- LINE Login Channel ID -->
    <div class="apple-settings-row" data-sheet="sheet-line-login-channel-id">
      <div class="apple-row-icon line-oa-icon" style="background:#f1f5f9; color:#06c755;">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-animated">
          <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline>
        </svg>
      </div>
      <div class="apple-row-content">
        <p class="apple-row-label">LINE Login Channel ID</p>
        <p class="apple-row-sublabel"><?php echo !empty($lineLoginChannelId) ? 'ตั้งค่าแล้ว' : 'ยังไม่ได้ตั้งค่า'; ?></p>
      </div>
      <span class="apple-row-chevron">›</span>
    </div>

    <!-- LINE Login Channel Secret -->
    <div class="apple-settings-row" data-sheet="sheet-line-login-channel-secret">
      <div class="apple-row-icon line-oa-icon" style="background:#f1f5f9; color:#06c755;">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-animated">
          <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
        </svg>
      </div>
      <div class="apple-row-content">
        <p class="apple-row-label">LINE Login Channel Secret</p>
        <p class="apple-row-sublabel"><?php echo !empty($lineLoginChannelSecret) ? 'ตั้งค่าแล้ว (ซ่อนเพื่อความปลอดภัย)' : 'ยังไม่ได้ตั้งค่า'; ?></p>
      </div>
      <span class="apple-row-chevron">›</span>
    </div>

    <!-- LINE Console Link -->
    <div class="apple-settings-row" onclick="window.open('https://developers.line.biz/console/', '_blank')">
      <div class="apple-row-icon line-oa-icon">
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
  <div class="apple-sheet">
    <div class="apple-sheet-handle"></div>
    <div class="apple-sheet-header">
      <button type="button" class="apple-sheet-action" data-close-sheet="sheet-line-broadcast">ยกเลิก</button>
      <h3 class="apple-sheet-title">ส่งข้อความประกาศ</h3>
      <div style="width: 50px;"></div>
    </div>
    <div class="apple-sheet-body">
      <form id="lineBroadcastForm">
        <div class="apple-input-group">
          <label class="apple-input-label">ข้อความ Broadcast</label>
          <textarea id="lineBroadcastMessage" name="message" class="apple-input" rows="5" placeholder="พิมพ์ข้อความที่ต้องการแจ้งเตือนไปยังลูกหอ" required style="resize:vertical;"></textarea>
          <p style="font-size: 13px; color: var(--apple-text-secondary); margin-top: 8px;">* จะส่งถึงผู้เช่าเฉพาะคนที่ลงทะเบียนผูกเบอร์ไว้ในระบบเท่านั้น</p>
        </div>
        <button type="submit" class="apple-button primary" style="width: 100%; margin-top: 16px;">ส่งข้อความทันที</button>
      </form>
    </div>
  </div>
</div>

<!-- Sheet: Add Friend URL -->
<div class="apple-sheet-overlay" id="sheet-line-add-friend-url">
  <div class="apple-sheet">
    <div class="apple-sheet-handle"></div>
    <div class="apple-sheet-header">
      <button type="button" class="apple-sheet-action" data-close-sheet="sheet-line-add-friend-url">ยกเลิก</button>
      <h3 class="apple-sheet-title">ลิงก์เพิ่มเพื่อน LINE</h3>
      <div style="width: 50px;"></div>
    </div>
    <div class="apple-sheet-body">
      <form id="lineAddFriendUrlForm">
        <div class="apple-input-group">
          <label class="apple-input-label">URL เพิ่มเพื่อน</label>
          <input type="url" id="lineAddFriendUrlInput" name="line_add_friend_url" class="apple-input" value="<?php echo htmlspecialchars($lineAddFriendUrl); ?>" placeholder="ตัวอย่าง: https://lin.ee/xxxxx">
          <p style="font-size: 13px; color: var(--apple-text-secondary); margin-top: 8px;">
            วิธีก๊อปลิงก์ที่ถูกต้อง: เข้า LINE OA Manager &gt; "เพิ่มเพื่อน" (Gain friends) &gt; "ลิงก์" (Link)<br>
            ลิงก์นี้จะเอาไปใช้สร้างปุ่มแอดไลน์ และสร้าง QR Code อัตโนมัติที่หน้าจองห้องพัก และหน้าผู้เช่า
          </p>
        </div>
        <button type="submit" class="apple-button primary">บันทึก</button>
      </form>
    </div>
  </div>
</div>

<!-- Sheet: LINE QR Code Upload -->
<div class="apple-sheet-overlay" id="sheet-line-qr-code">
  <div class="apple-sheet">
    <div class="apple-sheet-handle"></div>
    <div class="apple-sheet-header">
      <button type="button" class="apple-sheet-action" data-close-sheet="sheet-line-qr-code">ยกเลิก</button>
      <h3 class="apple-sheet-title">อัปโหลดภาพ QR Code</h3>
      <div style="width: 50px;"></div>
    </div>
    <div class="apple-sheet-body">
      <form id="lineQrCodeForm">
        <div class="apple-input-group" style="text-align:center;">
          <?php if (!empty($lineQrCodeImage) && file_exists(__DIR__ . '/../../Public/Assets/Images/' . $lineQrCodeImage)): ?>
            <img src="../Public/Assets/Images/<?php echo htmlspecialchars($lineQrCodeImage); ?>" alt="LINE QR Code" style="max-width: 200px; max-height: 200px; border-radius: 8px; margin-bottom: 16px; border: 1px solid var(--apple-border-color);">
            <div style="margin-bottom: 16px;">
              <button type="button" id="btnDeleteLineQr" class="apple-button secondary" style="color: #ef4444; border-color: rgba(239, 68, 68, 0.3);">ลบภาพปัจจุบัน</button>
            </div>
          <?php else: ?>
            <div style="width: 150px; height: 150px; background: var(--apple-bg-primary); border: 2px dashed var(--apple-border-color); border-radius: 12px; display:flex; align-items:center; justify-content:center; margin: 0 auto 16px;">
              <span style="color: var(--apple-text-secondary); font-size: 13px;">ไม่มีภาพ</span>
            </div>
          <?php endif; ?>
          <input type="file" id="lineQrInput" name="line_qr" accept="image/jpeg, image/png, image/jpg" style="display:none;" onchange="document.getElementById('qrFileName').textContent = this.files[0]?.name || 'ไม่ได้เลือกไฟล์';">
          <label for="lineQrInput" class="apple-button secondary" style="display: inline-block; cursor:pointer; width:auto; margin-bottom: 8px;">
            เลือกไฟล์ภาพ (JPG, PNG)
          </label>
          <div id="qrFileName" style="font-size: 12px; color: var(--apple-text-secondary);">ไม่ได้เลือกไฟล์</div>
        </div>
        <button type="submit" class="apple-button primary" style="margin-top: 16px;">บันทึกและอัปโหลด</button>
      </form>
    </div>
  </div>

  <!-- Sheet: LINE Login Channel ID -->
  <div class="apple-sheet-overlay" id="sheet-line-login-channel-id">
    <div class="apple-sheet">
      <div class="apple-sheet-handle"></div>
      <div class="apple-sheet-header">
        <button class="apple-sheet-action" data-close-sheet="sheet-line-login-channel-id">ยกเลิก</button>
        <h3 class="apple-sheet-title">LINE Login Channel ID</h3>
        <div style="width: 50px;"></div>
      </div>
      <div class="apple-sheet-body">
        <form id="lineLoginChannelIdForm">
          <div class="apple-input-group">
            <label class="apple-input-label">ตั้งค่าสำหรับปุ่มผูกบัญชี LINE Login 1-Click</label>
            <input type="text" id="lineLoginChannelIdInput" name="line_login_channel_id" class="apple-input" value="<?php echo htmlspecialchars($lineLoginChannelId); ?>" placeholder="ตัวอย่าง: 165xxxxxxxx">
          </div>
          <button type="submit" class="apple-button primary">บันทึก</button>
        </form>
      </div>
    </div>
  </div>

  <!-- Sheet: LINE Login Channel Secret -->
  <div class="apple-sheet-overlay" id="sheet-line-login-channel-secret">
    <div class="apple-sheet">
      <div class="apple-sheet-handle"></div>
      <div class="apple-sheet-header">
        <button class="apple-sheet-action" data-close-sheet="sheet-line-login-channel-secret">ยกเลิก</button>
        <h3 class="apple-sheet-title">LINE Login Secret</h3>
        <div style="width: 50px;"></div>
      </div>
      <div class="apple-sheet-body">
        <form id="lineLoginChannelSecretForm">
          <div class="apple-input-group">
            <label class="apple-input-label">ซ่อนตัวอักษรเพื่อความปลอดภัย</label>
            <input type="password" id="lineLoginChannelSecretInput" name="line_login_channel_secret" class="apple-input" value="<?php echo !empty($lineLoginChannelSecret) ? '********' : ''; ?>" placeholder="Channel Secret สำหรับ LINE Login">
          </div>
          <button type="submit" class="apple-button primary">บันทึก</button>
        </form>
      </div>
    </div>
  </div>
</div>

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
      setTimeout(() => location.reload(), 1000);
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
      setTimeout(() => location.reload(), 1000);
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