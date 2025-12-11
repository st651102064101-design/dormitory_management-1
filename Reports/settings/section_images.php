<!-- Section: Logo Settings -->
<div class="apple-section-group">
  <h2 class="apple-section-title">รูปภาพ</h2>
  <div class="apple-section-card">
    <!-- Logo -->
    <div class="apple-settings-row" data-sheet="sheet-logo">
      <div class="apple-row-icon orange">🎨</div>
      <div class="apple-row-content">
        <p class="apple-row-label">โลโก้</p>
        <p class="apple-row-sublabel">รูปโลโก้ที่แสดงในระบบ</p>
      </div>
      <img src="../Assets/Images/<?php echo htmlspecialchars($logoFilename); ?>" alt="Logo" style="width: 40px; height: 40px; border-radius: 8px; object-fit: cover; margin-right: 8px;">
      <span class="apple-row-chevron">›</span>
    </div>
    
    <!-- Background -->
    <div class="apple-settings-row" data-sheet="sheet-background">
      <div class="apple-row-icon purple">🖼️</div>
      <div class="apple-row-content">
        <p class="apple-row-label">ภาพพื้นหลัง</p>
        <p class="apple-row-sublabel">ภาพ Hero หน้าแรก</p>
      </div>
      <img src="../Assets/Images/<?php echo htmlspecialchars($bgFilename); ?>" alt="BG" style="width: 50px; height: 30px; border-radius: 6px; object-fit: cover; margin-right: 8px;">
      <span class="apple-row-chevron">›</span>
    </div>
  </div>
</div>

<!-- Sheet: Logo -->
<div class="apple-sheet-overlay" id="sheet-logo">
  <div class="apple-sheet">
    <div class="apple-sheet-handle"></div>
    <div class="apple-sheet-header">
      <button class="apple-sheet-action" data-close-sheet="sheet-logo">ยกเลิก</button>
      <h3 class="apple-sheet-title">จัดการโลโก้</h3>
      <div style="width: 50px;"></div>
    </div>
    <div class="apple-sheet-body">
      <!-- Current Logo -->
      <div class="apple-image-preview">
        <img id="logoPreviewImg" src="../Assets/Images/<?php echo htmlspecialchars($logoFilename); ?>" alt="Logo">
        <div class="apple-image-info">
          <h4>โลโก้ปัจจุบัน</h4>
          <p><?php echo htmlspecialchars($logoFilename); ?></p>
        </div>
      </div>
      
      <!-- Select from existing -->
      <div class="apple-input-group">
        <label class="apple-input-label">เลือกจากรูปที่มี</label>
        <select id="oldLogoSelect" class="apple-input">
          <option value="">-- เลือกรูป --</option>
          <?php foreach ($imageFiles as $file): ?>
            <option value="<?php echo htmlspecialchars($file); ?>"><?php echo htmlspecialchars($file); ?></option>
          <?php endforeach; ?>
        </select>
        <div id="oldLogoPreview" style="margin-top: 12px;"></div>
      </div>
      
      <!-- Upload new -->
      <div class="apple-input-group">
        <label class="apple-input-label">อัพโหลดรูปใหม่</label>
        <div class="apple-upload-area" onclick="document.getElementById('logoInput').click()">
          <div class="apple-upload-icon">📸</div>
          <p class="apple-upload-text">คลิกเพื่อเลือกรูป</p>
          <p class="apple-upload-hint">รองรับ JPG, PNG</p>
          <input type="file" id="logoInput" accept="image/jpeg,image/png">
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Sheet: Background -->
<div class="apple-sheet-overlay" id="sheet-background">
  <div class="apple-sheet">
    <div class="apple-sheet-handle"></div>
    <div class="apple-sheet-header">
      <button class="apple-sheet-action" data-close-sheet="sheet-background">ยกเลิก</button>
      <h3 class="apple-sheet-title">จัดการภาพพื้นหลัง</h3>
      <div style="width: 50px;"></div>
    </div>
    <div class="apple-sheet-body">
      <!-- Current Background -->
      <div class="apple-image-preview">
        <img id="bgPreviewImg" src="../Assets/Images/<?php echo htmlspecialchars($bgFilename); ?>" alt="Background" style="width: 120px; height: 70px;">
        <div class="apple-image-info">
          <h4>ภาพพื้นหลังปัจจุบัน</h4>
          <p><?php echo htmlspecialchars($bgFilename); ?></p>
        </div>
      </div>
      
      <!-- Select from existing -->
      <div class="apple-input-group">
        <label class="apple-input-label">เลือกจากรูปที่มี</label>
        <select id="bgSelect" class="apple-input">
          <option value="">-- เลือกรูป --</option>
          <?php foreach ($imageFiles as $file): ?>
            <option value="<?php echo htmlspecialchars($file); ?>" <?php echo ($file === $bgFilename) ? 'selected' : ''; ?>><?php echo htmlspecialchars($file); ?></option>
          <?php endforeach; ?>
        </select>
        <div id="bgSelectPreview" style="margin-top: 12px;"></div>
      </div>
      
      <!-- Upload new -->
      <div class="apple-input-group">
        <label class="apple-input-label">อัพโหลดรูปใหม่</label>
        <div class="apple-upload-area" onclick="document.getElementById('bgInput').click()">
          <div class="apple-upload-icon">🖼️</div>
          <p class="apple-upload-text">คลิกเพื่อเลือกรูป</p>
          <p class="apple-upload-hint">รองรับ JPG, PNG, WebP</p>
          <input type="file" id="bgInput" accept="image/jpeg,image/png,image/webp">
        </div>
      </div>
    </div>
  </div>
</div>
