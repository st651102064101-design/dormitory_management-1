<!-- Section: Appearance Settings -->
<div class="apple-section-group">
  <h2 class="apple-section-title">การแสดงผล</h2>
  <div class="apple-section-card">
    <!-- Default View Mode -->
    <div class="apple-settings-row" data-sheet="sheet-default-view">
      <div class="apple-row-icon cyan"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-animated"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg></div>
      <div class="apple-row-content">
        <p class="apple-row-label">รูปแบบการแสดงผลเริ่มต้น</p>
        <p class="apple-row-sublabel">ใช้กับทุกหน้าแอดมิน</p>
      </div>
      <span class="apple-row-value"><?php 
        $viewModeNames = ['grid' => 'Grid', 'list' => 'List'];
        echo $viewModeNames[$defaultViewMode] ?? 'Grid';
      ?></span>
      <span class="apple-row-chevron">›</span>
    </div>
    
    <!-- Public Theme -->
    <div class="apple-settings-row" data-sheet="sheet-public-theme">
      <div class="apple-row-icon indigo"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-animated"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg></div>
      <div class="apple-row-content">
        <p class="apple-row-label">ธีมหน้าสาธารณะ</p>
        <p class="apple-row-sublabel">ธีมสำหรับผู้เยี่ยมชม</p>
      </div>
      <span class="apple-row-value"><?php 
        $themeNames = ['dark' => 'มืด', 'light' => 'สว่าง', 'auto' => 'อัตโนมัติ'];
        echo $themeNames[$publicTheme] ?? 'มืด';
      ?></span>
      <span class="apple-row-chevron">›</span>
    </div>
    
    <!-- Use Background Image -->
    <div class="apple-settings-row">
      <div class="apple-row-icon blue"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-animated"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg></div>
      <div class="apple-row-content">
        <p class="apple-row-label">ใช้ภาพพื้นหลัง</p>
        <p class="apple-row-sublabel">แสดงภาพพื้นหลังบนหน้าแรก</p>
      </div>
      <div class="apple-toggle" id="bgImageToggle" data-setting="use_bg_image" data-value="<?php echo htmlspecialchars($useBgImage); ?>"></div>
    </div>
    
    <!-- System Theme Color -->
    <div class="apple-settings-row" data-sheet="sheet-theme-color">
      <div class="apple-row-icon pink"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-animated"><path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"/></svg></div>
      <div class="apple-row-content">
        <p class="apple-row-label">สีพื้นหลังระบบ</p>
      </div>
      <div style="width: 24px; height: 24px; border-radius: 6px; background: <?php echo htmlspecialchars($themeColor); ?>; border: 2px solid rgba(0,0,0,0.1); margin-right: 8px;"></div>
      <span class="apple-row-chevron">›</span>
    </div>
    
    <!-- Font Size -->
    <div class="apple-settings-row" data-sheet="sheet-font-size">
      <div class="apple-row-icon gray"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-animated"><polyline points="4 7 4 4 20 4 20 7"/><line x1="9" y1="20" x2="15" y2="20"/><line x1="12" y1="4" x2="12" y2="20"/></svg></div>
      <div class="apple-row-content">
        <p class="apple-row-label">ขนาดตัวอักษร</p>
      </div>
      <span class="apple-row-value"><?php 
        $fontSizeNames = ['0.9' => 'เล็ก', '1' => 'ปกติ', '1.1' => 'ใหญ่', '1.25' => 'ใหญ่มาก'];
        echo $fontSizeNames[$fontSize] ?? 'ปกติ';
      ?></span>
      <span class="apple-row-chevron">›</span>
    </div>
    
    <!-- FPS Threshold -->
    <div class="apple-settings-row" data-sheet="sheet-fps-threshold">
      <div class="apple-row-icon orange"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-animated"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg></div>
      <div class="apple-row-content">
        <p class="apple-row-label">FPS Threshold</p>
        <p class="apple-row-sublabel">แจ้งเตือนเมื่อ FPS ต่ำกว่า</p>
      </div>
      <span class="apple-row-value"><?php echo htmlspecialchars($fpsThreshold ?? '60'); ?> FPS</span>
      <span class="apple-row-chevron">›</span>
    </div>
  </div>
</div>

<!-- Sheet: Public Theme -->
<div class="apple-sheet-overlay" id="sheet-public-theme">
  <div class="apple-sheet">
    <div class="apple-sheet-handle"></div>
    <div class="apple-sheet-header">
      <button class="apple-sheet-action" data-close-sheet="sheet-public-theme">เสร็จ</button>
      <h3 class="apple-sheet-title">ธีมหน้าสาธารณะ</h3>
      <div style="width: 50px;"></div>
    </div>
    <div class="apple-sheet-body">
      <p style="font-size: 13px; color: var(--apple-text-secondary); margin-bottom: 16px;">
        เลือกธีมสำหรับหน้าแรก, หน้าจองห้อง และหน้าข่าวสาร
      </p>
      <div class="apple-theme-grid">
        <div class="apple-theme-option <?php echo $publicTheme === 'dark' ? 'active' : ''; ?>" data-theme="dark">
          <div class="apple-theme-preview dark"></div>
          <span class="apple-theme-name"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;vertical-align:-2px;margin-right:3px;"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>มืด</span>
        </div>
        <div class="apple-theme-option <?php echo $publicTheme === 'light' ? 'active' : ''; ?>" data-theme="light">
          <div class="apple-theme-preview light"></div>
          <span class="apple-theme-name"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;vertical-align:-2px;margin-right:3px;"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>สว่าง</span>
        </div>
        <div class="apple-theme-option <?php echo $publicTheme === 'auto' ? 'active' : ''; ?>" data-theme="auto">
          <div class="apple-theme-preview auto"></div>
          <span class="apple-theme-name"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;vertical-align:-2px;margin-right:3px;"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>อัตโนมัติ</span>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Sheet: Theme Color -->
<div class="apple-sheet-overlay" id="sheet-theme-color">
  <div class="apple-sheet">
    <div class="apple-sheet-handle"></div>
    <div class="apple-sheet-header">
      <button class="apple-sheet-action" data-close-sheet="sheet-theme-color">เสร็จ</button>
      <h3 class="apple-sheet-title">สีพื้นหลังระบบ</h3>
      <div style="width: 50px;"></div>
    </div>
    <div class="apple-sheet-body">
      <p style="font-size: 13px; color: var(--apple-text-secondary); margin-bottom: 16px;">
        เลือกสีพื้นหลังสำหรับระบบจัดการ สีจะเปลี่ยนทันที
      </p>
      
      <!-- Dark Colors -->
      <label class="apple-input-label">สีเข้ม</label>
      <div class="apple-color-grid" style="margin-bottom: 20px;">
        <div class="apple-color-option <?php echo $themeColor === '#0f172a' ? 'active' : ''; ?>" data-color="#0f172a" style="background: #0f172a !important;" title="Navy Dark"></div>
        <div class="apple-color-option <?php echo $themeColor === '#1e293b' ? 'active' : ''; ?>" data-color="#1e293b" style="background: #1e293b !important;" title="Slate"></div>
        <div class="apple-color-option <?php echo $themeColor === '#1e3a5f' ? 'active' : ''; ?>" data-color="#1e3a5f" style="background: #1e3a5f !important;" title="Navy Blue"></div>
        <div class="apple-color-option <?php echo $themeColor === '#1e1e1e' ? 'active' : ''; ?>" data-color="#1e1e1e" style="background: #1e1e1e !important;" title="Dark Gray"></div>
        <div class="apple-color-option <?php echo $themeColor === '#2d3748' ? 'active' : ''; ?>" data-color="#2d3748" style="background: #2d3748 !important;" title="Cool Gray"></div>
        <div class="apple-color-option <?php echo $themeColor === '#000000' ? 'active' : ''; ?>" data-color="#000000" style="background: #000000 !important;" title="Pure Black"></div>
      </div>
      
      <!-- Light Colors -->
      <label class="apple-input-label">สีสว่าง</label>
      <div class="apple-color-grid" style="margin-bottom: 20px;">
        <div class="apple-color-option <?php echo $themeColor === '#ffffff' ? 'active' : ''; ?>" data-color="#ffffff" style="background: #ffffff !important; border: 1px solid #ddd;" title="White"></div>
        <div class="apple-color-option <?php echo $themeColor === '#f2f2f7' ? 'active' : ''; ?>" data-color="#f2f2f7" style="background: #f2f2f7 !important; border: 1px solid #ddd;" title="Apple Gray"></div>
        <div class="apple-color-option <?php echo $themeColor === '#f8fafc' ? 'active' : ''; ?>" data-color="#f8fafc" style="background: #f8fafc !important; border: 1px solid #ddd;" title="Snow"></div>
        <div class="apple-color-option <?php echo $themeColor === '#f1f5f9' ? 'active' : ''; ?>" data-color="#f1f5f9" style="background: #f1f5f9 !important; border: 1px solid #ddd;" title="Light Slate"></div>
        <div class="apple-color-option <?php echo $themeColor === '#e2e8f0' ? 'active' : ''; ?>" data-color="#e2e8f0" style="background: #e2e8f0 !important; border: 1px solid #ddd;" title="Silver"></div>
        <div class="apple-color-option <?php echo $themeColor === '#fef3c7' ? 'active' : ''; ?>" data-color="#fef3c7" style="background: #fef3c7 !important; border: 1px solid #ddd;" title="Cream"></div>
      </div>
      
      <!-- Custom Color -->
      <div class="apple-input-group" style="margin-top: 20px;">
        <label class="apple-input-label">สีที่กำหนดเอง</label>
        <div style="display: flex; gap: 12px; align-items: center;">
          <input type="color" id="themeColor" value="<?php echo htmlspecialchars($themeColor); ?>" style="width: 60px; height: 44px; border: none; border-radius: 10px; cursor: pointer;">
          <span id="colorHexDisplay" style="font-size: 17px; color: var(--apple-text);"><?php echo htmlspecialchars($themeColor); ?></span>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Sheet: Font Size -->
<div class="apple-sheet-overlay" id="sheet-font-size">
  <div class="apple-sheet">
    <div class="apple-sheet-handle"></div>
    <div class="apple-sheet-header">
      <button class="apple-sheet-action" data-close-sheet="sheet-font-size">เสร็จ</button>
      <h3 class="apple-sheet-title">ขนาดตัวอักษร</h3>
      <div style="width: 50px;"></div>
    </div>
    <div class="apple-sheet-body">
      <div class="apple-input-group">
        <label class="apple-input-label">เลือกขนาด</label>
        <select id="fontSize" class="apple-input">
          <option value="0.9" <?php echo $fontSize === '0.9' ? 'selected' : ''; ?>>เล็ก (0.9)</option>
          <option value="1" <?php echo $fontSize === '1' ? 'selected' : ''; ?>>ปกติ (1.0)</option>
          <option value="1.1" <?php echo $fontSize === '1.1' ? 'selected' : ''; ?>>ใหญ่ (1.1)</option>
          <option value="1.25" <?php echo $fontSize === '1.25' ? 'selected' : ''; ?>>ใหญ่มาก (1.25)</option>
        </select>
      </div>
      
      <div class="font-size-preview" style="padding: 16px; background: var(--apple-card); border-radius: 12px; text-align: center; font-size: calc(1rem * <?php echo htmlspecialchars($fontSize); ?>); color: var(--apple-text);">
        ตัวอย่างข้อความ - Example Text
      </div>
    </div>
  </div>
</div>

<!-- Sheet: Default View Mode -->
<div class="apple-sheet-overlay" id="sheet-default-view">
  <div class="apple-sheet">
    <div class="apple-sheet-handle"></div>
    <div class="apple-sheet-header">
      <button class="apple-sheet-action" data-close-sheet="sheet-default-view">เสร็จ</button>
      <h3 class="apple-sheet-title">รูปแบบการแสดงผล</h3>
      <div style="width: 50px;"></div>
    </div>
    <div class="apple-sheet-body">
      <p style="font-size: 13px; color: var(--apple-text-secondary); margin-bottom: 16px;">
        เลือกรูปแบบการแสดงผลเริ่มต้นสำหรับทุกหน้าในระบบแอดมิน
      </p>
      <div class="apple-theme-grid">
        <div class="apple-view-option <?php echo $defaultViewMode === 'grid' ? 'active' : ''; ?>" data-view="grid">
          <div class="apple-view-preview">
            <div class="view-grid-preview">
              <div class="grid-box"></div>
              <div class="grid-box"></div>
              <div class="grid-box"></div>
              <div class="grid-box"></div>
            </div>
          </div>
          <span class="apple-theme-name">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;vertical-align:-2px;margin-right:3px;"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
            Grid
          </span>
        </div>
        <div class="apple-view-option <?php echo $defaultViewMode === 'list' ? 'active' : ''; ?>" data-view="list">
          <div class="apple-view-preview">
            <div class="view-list-preview">
              <div class="list-row"></div>
              <div class="list-row"></div>
              <div class="list-row"></div>
            </div>
          </div>
          <span class="apple-theme-name">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;vertical-align:-2px;margin-right:3px;"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
            List
          </span>
        </div>
      </div>
      
      <div style="margin-top: 20px; padding: 12px; background: rgba(59, 130, 246, 0.1); border-radius: 10px; border: 1px solid rgba(59, 130, 246, 0.2);">
        <p style="font-size: 13px; color: var(--apple-text-secondary); margin: 0; display: flex; align-items: center; gap: 6px;">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 14c.2-1 .7-1.7 1.5-2.5 1-.9 1.5-2.2 1.5-3.5A6 6 0 0 0 6 8c0 1 .2 2.2 1.5 3.5.7.7 1.3 1.5 1.5 2.5"/><path d="M9 18h6"/><path d="M10 22h4"/></svg>
          การตั้งค่านี้จะมีผลกับทุกหน้าแอดมิน เช่น หน้าจัดการห้องพัก, หน้าจัดการผู้เช่า, หน้าจองห้อง เป็นต้น
        </p>
      </div>
    </div>
  </div>
</div>

<!-- Sheet: FPS Threshold -->
<div class="apple-sheet-overlay" id="sheet-fps-threshold">
  <div class="apple-sheet">
    <div class="apple-sheet-handle"></div>
    <div class="apple-sheet-header">
      <button class="apple-sheet-action" data-close-sheet="sheet-fps-threshold">เสร็จ</button>
      <h3 class="apple-sheet-title">ค่า FPS ขั้นต่ำ</h3>
      <div style="width: 50px;"></div>
    </div>
    <div class="apple-sheet-body">
      <div class="apple-input-group">
        <label class="apple-input-label">กำหนดค่า FPS</label>
        <select id="fpsThreshold" class="apple-input">
          <option value="30" <?php echo $fpsThreshold === '30' ? 'selected' : ''; ?>>30 FPS (ต่ำ)</option>
          <option value="45" <?php echo $fpsThreshold === '45' ? 'selected' : ''; ?>>45 FPS</option>
          <option value="60" <?php echo $fpsThreshold === '60' ? 'selected' : ''; ?>>60 FPS (ปกติ)</option>
          <option value="90" <?php echo $fpsThreshold === '90' ? 'selected' : ''; ?>>90 FPS</option>
          <option value="120" <?php echo $fpsThreshold === '120' ? 'selected' : ''; ?>>120 FPS (สูง)</option>
          <option value="180" <?php echo $fpsThreshold === '180' ? 'selected' : ''; ?>>180 FPS (สูงมาก)</option>
          <option value="240" <?php echo $fpsThreshold === '240' ? 'selected' : ''; ?>>240 FPS (สูงสุด)</option>
          <option value="300" <?php echo $fpsThreshold === '300' ? 'selected' : ''; ?>>300 FPS (สูงเว่อร์)</option>
        </select>
      </div>
      
      <div style="margin-top: 16px; padding: 12px; background: rgba(234, 179, 8, 0.1); border-radius: 10px; border: 1px solid rgba(234, 179, 8, 0.2);">
        <p style="font-size: 13px; color: var(--apple-text-secondary); margin: 0; display: flex; align-items: center; gap: 6px;">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#eab308" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2 3 14h9l-1 8 10-12h-9l1-8z"/></svg>
          ระบบจะแจ้งเตือนเมื่อ FPS ต่ำกว่าค่านี้
        </p>
      </div>
      
      <div style="margin-top: 12px; padding: 12px; background: rgba(59, 130, 246, 0.1); border-radius: 10px; border: 1px solid rgba(59, 130, 246, 0.2);">
        <p style="font-size: 12px; color: var(--apple-text-secondary); margin: 0 0 6px 0;">
          <strong>📍 ตรวจสอบใน:</strong>
        </p>
        <ul style="font-size: 12px; color: var(--apple-text-secondary); margin: 0; padding-left: 16px;">
          <li>หน้าจัดการจองห้องพัก (Booking)</li>
        </ul>
      </div>
      
      <div style="margin-top: 12px; padding: 12px; background: var(--apple-card); border-radius: 10px;">
        <p style="font-size: 12px; color: var(--apple-text-secondary); margin: 0 0 8px 0;">
          <strong>คำแนะนำ:</strong>
        </p>
        <ul style="font-size: 12px; color: var(--apple-text-secondary); margin: 0; padding-left: 16px;">
          <li>คอมพิวเตอร์ทั่วไป: 60 FPS</li>
          <li>คอมพิวเตอร์เก่า: 30-45 FPS</li>
          <li>หน้าจอความถี่สูง: 90-120 FPS</li>
        </ul>
      </div>
    </div>
  </div>
</div>
