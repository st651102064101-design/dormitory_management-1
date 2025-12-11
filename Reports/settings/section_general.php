<!-- Section: General Settings -->
<div class="apple-section-group">
  <h2 class="apple-section-title">ทั่วไป</h2>
  <div class="apple-section-card">
    <!-- Site Name -->
    <div class="apple-settings-row" data-sheet="sheet-sitename">
      <div class="apple-row-icon blue">🏢</div>
      <div class="apple-row-content">
        <p class="apple-row-label">ชื่อหอพัก</p>
      </div>
      <span class="apple-row-value" data-display="sitename"><?php echo htmlspecialchars($siteName); ?></span>
      <span class="apple-row-chevron">›</span>
    </div>
    
    <!-- Phone -->
    <div class="apple-settings-row" data-sheet="sheet-phone">
      <div class="apple-row-icon green">📞</div>
      <div class="apple-row-content">
        <p class="apple-row-label">เบอร์โทรศัพท์</p>
      </div>
      <span class="apple-row-value" data-display="phone"><?php echo htmlspecialchars($contactPhone); ?></span>
      <span class="apple-row-chevron">›</span>
    </div>
    
    <!-- Email -->
    <div class="apple-settings-row" data-sheet="sheet-email">
      <div class="apple-row-icon teal">✉️</div>
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
