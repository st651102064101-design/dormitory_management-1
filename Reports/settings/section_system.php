<!-- Section: System Info & Backup -->
<div class="apple-section-group">
  <h2 class="apple-section-title">ระบบ</h2>
  <div class="apple-section-card">
    <!-- System Info -->
    <div class="apple-settings-row" data-sheet="sheet-system-info">
      <div class="apple-row-icon blue"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-animated"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg></div>
      <div class="apple-row-content">
        <p class="apple-row-label">ข้อมูลระบบ</p>
        <p class="apple-row-sublabel">PHP, Database, สถานะ</p>
      </div>
      <span class="apple-row-chevron">›</span>
    </div>
    
    <!-- Backup -->
    <div class="apple-settings-row" data-sheet="sheet-backup">
      <div class="apple-row-icon green"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-animated"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg></div>
      <div class="apple-row-content">
        <p class="apple-row-label">สำรองข้อมูล</p>
        <p class="apple-row-sublabel">Backup ฐานข้อมูล</p>
      </div>
      <span class="apple-row-chevron">›</span>
    </div>
  </div>
</div>

<!-- Sheet: System Info -->
<div class="apple-sheet-overlay" id="sheet-system-info">
  <div class="apple-sheet">
    <div class="apple-sheet-handle"></div>
    <div class="apple-sheet-header">
      <button class="apple-sheet-action" data-close-sheet="sheet-system-info">เสร็จ</button>
      <h3 class="apple-sheet-title">ข้อมูลระบบ</h3>
      <div style="width: 50px;"></div>
    </div>
    <div class="apple-sheet-body">
      <div class="apple-section-card">
        <div class="apple-info-row">
          <span class="apple-info-label">เวอร์ชัน PHP</span>
          <span class="apple-info-value"><?php echo phpversion(); ?></span>
        </div>
        <div class="apple-info-row">
          <span class="apple-info-label">ฐานข้อมูล</span>
          <span class="apple-info-value">MySQL</span>
        </div>
        <div class="apple-info-row">
          <span class="apple-info-label">สถานะระบบ</span>
          <span class="apple-info-value success">✓ ทำงานปกติ</span>
        </div>
        <div class="apple-info-row">
          <span class="apple-info-label">อัพเดทล่าสุด</span>
          <span class="apple-info-value"><?php echo date('d/m/Y H:i'); ?></span>
        </div>
      </div>
      
      <!-- Stats -->
      <h4 style="font-size: 13px; font-weight: 600; color: var(--apple-text-secondary); text-transform: uppercase; letter-spacing: 0.5px; margin: 24px 0 12px; padding-left: 4px;">สถิติข้อมูล</h4>
      
      <div class="apple-section-card">
        <div class="apple-info-row">
          <span class="apple-info-label"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;vertical-align:-2px;margin-right:4px;"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>จำนวนห้อง</span>
          <span class="apple-info-value"><?php echo number_format($totalRooms); ?> ห้อง</span>
        </div>
        <div class="apple-info-row">
          <span class="apple-info-label"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;vertical-align:-2px;margin-right:4px;"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>ผู้เช่าปัจจุบัน</span>
          <span class="apple-info-value"><?php echo number_format($totalTenants); ?> คน</span>
        </div>
        <div class="apple-info-row">
          <span class="apple-info-label"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;vertical-align:-2px;margin-right:4px;"><rect x="9" y="2" width="6" height="4" rx="1"/><rect x="4" y="4" width="16" height="18" rx="2"/><line x1="9" y1="11" x2="15" y2="11"/><line x1="9" y1="15" x2="15" y2="15"/></svg>การจองรอดำเนินการ</span>
          <span class="apple-info-value"><?php echo number_format($totalBookings); ?> รายการ</span>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Sheet: Backup -->
<div class="apple-sheet-overlay" id="sheet-backup">
  <div class="apple-sheet">
    <div class="apple-sheet-handle"></div>
    <div class="apple-sheet-header">
      <button class="apple-sheet-action" data-close-sheet="sheet-backup">เสร็จ</button>
      <h3 class="apple-sheet-title">สำรองข้อมูล</h3>
      <div style="width: 50px;"></div>
    </div>
    <div class="apple-sheet-body">
      <div style="text-align: center; padding: 30px 0;">
        <div style="font-size: 64px; margin-bottom: 16px; color: #22c55e;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="64" height="64"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg></div>
        <h4 style="font-size: 20px; font-weight: 600; color: var(--apple-text); margin: 0 0 8px;">สำรองฐานข้อมูล</h4>
        <p style="font-size: 15px; color: var(--apple-text-secondary); margin: 0 0 24px;">
          สร้างไฟล์ Backup เพื่อป้องกันการสูญเสียข้อมูล
        </p>
        <button type="button" class="apple-button success" onclick="backupDatabase()" id="backupBtn">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px;vertical-align:-3px;margin-right:4px;"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>สำรองข้อมูลเดี๋ยวนี้
        </button>
        
        <!-- Download Link (hidden initially) -->
        <div id="backupDownloadArea" style="display: none; margin-top: 20px; padding: 16px; background: rgba(52, 199, 89, 0.1); border-radius: 12px;">
          <p style="font-size: 14px; color: var(--apple-green); margin: 0 0 12px;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;vertical-align:-2px;margin-right:3px;"><polyline points="20 6 9 17 4 12"/></svg>สำรองข้อมูลสำเร็จ!</p>
          <a id="backupDownloadLink" href="#" download class="apple-button" style="display: inline-flex; align-items: center; gap: 8px; text-decoration: none;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>ดาวน์โหลดไฟล์ Backup
          </a>
        </div>
      </div>
      
      <!-- Previous Backups List -->
      <div style="margin-top: 24px;">
        <h5 style="font-size: 14px; font-weight: 600; color: var(--apple-text-secondary); margin: 0 0 12px; text-transform: uppercase;">ไฟล์สำรองข้อมูลล่าสุด</h5>
        <div id="backupListContainer" class="apple-settings-group">
          <?php
          $backupDir = __DIR__ . '/../../backups/';
          $backupFiles = [];
          if (is_dir($backupDir)) {
              $files = glob($backupDir . 'backup_*.sql');
              usort($files, function($a, $b) {
                  return filemtime($b) - filemtime($a);
              });
              $backupFiles = array_slice($files, 0, 5); // Show last 5 backups
          }
          
          if (empty($backupFiles)): ?>
            <div class="apple-settings-row" style="justify-content: center;">
              <span style="color: var(--apple-text-secondary); font-size: 14px;">ยังไม่มีไฟล์สำรองข้อมูล</span>
            </div>
          <?php else:
            foreach ($backupFiles as $file):
              $fname = basename($file);
              $fsize = filesize($file);
              $fdate = date('d/m/Y H:i', filemtime($file));
              $sizeStr = $fsize > 1048576 ? round($fsize/1048576, 2) . ' MB' : round($fsize/1024, 2) . ' KB';
          ?>
            <div class="apple-settings-row" style="cursor: pointer;" onclick="downloadBackup('<?php echo htmlspecialchars($fname); ?>')">
              <div style="display: flex; align-items: center; gap: 12px;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:24px;height:24px;color:var(--apple-blue);"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                <div>
                  <div style="font-size: 15px; color: var(--apple-text);"><?php echo htmlspecialchars($fname); ?></div>
                  <div style="font-size: 12px; color: var(--apple-text-secondary);"><?php echo $fdate; ?> • <?php echo $sizeStr; ?></div>
                </div>
              </div>
              <svg viewBox="0 0 24 24" fill="none" stroke="var(--apple-blue)" stroke-width="2" style="width:20px;height:20px;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            </div>
          <?php endforeach; endif; ?>
        </div>
      </div>
      
      <div style="background: rgba(255, 149, 0, 0.1); padding: 16px; border-radius: 12px; margin-top: 20px;">
        <p style="font-size: 13px; color: var(--apple-orange); margin: 0;">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;vertical-align:-2px;margin-right:4px;"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>ควรสำรองข้อมูลอย่างน้อยสัปดาห์ละครั้ง และก่อนทำการเปลี่ยนแปลงข้อมูลสำคัญ
        </p>
      </div>
    </div>
  </div>
</div>
