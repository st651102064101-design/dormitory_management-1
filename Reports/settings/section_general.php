<!-- Section: General Settings -->
<div class="apple-section-group">
  <h2 class="apple-section-title">ทั่วไป</h2>
  <div class="apple-section-card">
    <!-- Site Name -->
    <div class="apple-settings-row" data-sheet="sheet-sitename">
      <div class="apple-row-icon blue"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg></div>
      <div class="apple-row-content">
        <p class="apple-row-label">ชื่อหอพัก</p>
      </div>
      <span class="apple-row-value" data-display="sitename"><?php echo htmlspecialchars($siteName); ?></span>
      <span class="apple-row-chevron">›</span>
    </div>
    
    <!-- Phone -->
    <div class="apple-settings-row" data-sheet="sheet-phone">
      <div class="apple-row-icon green"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg></div>
      <div class="apple-row-content">
        <p class="apple-row-label">เบอร์โทรศัพท์</p>
      </div>
      <span class="apple-row-value" data-display="phone"><?php echo htmlspecialchars($contactPhone); ?></span>
      <span class="apple-row-chevron">›</span>
    </div>
    
    <!-- Email -->
    <div class="apple-settings-row" data-sheet="sheet-email">
      <div class="apple-row-icon teal"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg></div>
      <div class="apple-row-content">
        <p class="apple-row-label">อีเมล</p>
      </div>
      <span class="apple-row-value" data-display="email"><?php echo htmlspecialchars($contactEmail); ?></span>
      <span class="apple-row-chevron">›</span>
    </div>
  </div>
</div>

<!-- Sheet: Site Name -->
<div class="apple-sheet-overlay" id="sheet-sitename">
  <div class="apple-sheet">
    <div class="apple-sheet-handle"></div>
    <div class="apple-sheet-header">
      <button class="apple-sheet-action" data-close-sheet="sheet-sitename">ยกเลิก</button>
      <h3 class="apple-sheet-title">ชื่อหอพัก</h3>
      <div style="width: 50px;"></div>
    </div>
    <div class="apple-sheet-body">
      <form id="siteNameForm">
        <div class="apple-input-group">
          <label class="apple-input-label">ชื่อ</label>
          <input type="text" id="siteName" class="apple-input" value="<?php echo htmlspecialchars($siteName); ?>" maxlength="100" required>
        </div>
        <button type="submit" class="apple-button primary">บันทึก</button>
      </form>
    </div>
  </div>
</div>

<!-- Sheet: Phone -->
<div class="apple-sheet-overlay" id="sheet-phone">
  <div class="apple-sheet">
    <div class="apple-sheet-handle"></div>
    <div class="apple-sheet-header">
      <button class="apple-sheet-action" data-close-sheet="sheet-phone">ยกเลิก</button>
      <h3 class="apple-sheet-title">เบอร์โทรศัพท์</h3>
      <div style="width: 50px;"></div>
    </div>
    <div class="apple-sheet-body">
      <form id="phoneForm">
        <div class="apple-input-group">
          <label class="apple-input-label">เบอร์โทร</label>
          <input type="tel" id="contactPhone" class="apple-input" value="<?php echo htmlspecialchars($contactPhone); ?>" pattern="[0-9\-\+\s()]{8,20}" maxlength="20" required>
          <p style="font-size: 13px; color: var(--apple-text-secondary); margin-top: 8px;">เช่น 089-565-6083</p>
        </div>
        <button type="submit" class="apple-button primary">บันทึก</button>
      </form>
    </div>
  </div>
</div>

<!-- Sheet: Email -->
<div class="apple-sheet-overlay" id="sheet-email">
  <div class="apple-sheet">
    <div class="apple-sheet-handle"></div>
    <div class="apple-sheet-header">
      <button class="apple-sheet-action" data-close-sheet="sheet-email">ยกเลิก</button>
      <h3 class="apple-sheet-title">อีเมล</h3>
      <div style="width: 50px;"></div>
    </div>
    <div class="apple-sheet-body">
      <form id="emailForm">
        <div class="apple-input-group">
          <label class="apple-input-label">อีเมล</label>
          <input type="email" id="contactEmail" class="apple-input" value="<?php echo htmlspecialchars($contactEmail); ?>" maxlength="100" required>
        </div>
        <button type="submit" class="apple-button primary">บันทึก</button>
      </form>
    </div>
  </div>
</div>
