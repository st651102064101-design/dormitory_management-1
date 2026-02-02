<!-- Section: Logo Settings -->
<div class="apple-section-group">
  <h2 class="apple-section-title">รูปภาพ</h2>
  <div class="apple-section-card">
    <!-- Logo -->
    <div class="apple-settings-row" data-sheet="sheet-logo">
      <div class="apple-row-icon orange"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-animated"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="3"/></svg></div>
      <div class="apple-row-content">
        <p class="apple-row-label">โลโก้</p>
        <p class="apple-row-sublabel">รูปโลโก้ที่แสดงในระบบ</p>
      </div>
      <img id="logoRowImg" src="/dormitory_management/Public/Assets/Images/<?php echo htmlspecialchars($logoFilename); ?>" alt="Logo" style="width: 40px; height: 40px; border-radius: 8px; object-fit: cover; margin-right: 8px;">
      <span class="apple-row-chevron">›</span>
    </div>
    
    <!-- Background -->
    <div class="apple-settings-row" data-sheet="sheet-background">
      <div class="apple-row-icon purple"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-animated"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg></div>
      <div class="apple-row-content">
        <p class="apple-row-label">ภาพพื้นหลัง</p>
        <p class="apple-row-sublabel">ภาพ Hero หน้าแรก</p>
      </div>
      <img id="bgRowImg" src="/dormitory_management/Public/Assets/Images/<?php echo htmlspecialchars($bgFilename); ?>" alt="BG" style="width: 50px; height: 30px; border-radius: 6px; object-fit: cover; margin-right: 8px;">
      <span class="apple-row-chevron">›</span>
    </div>
    
    <!-- Owner Signature -->
    <div class="apple-settings-row" data-sheet="sheet-signature">
      <div class="apple-row-icon green"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-animated"><path d="M12 19l7-7 3 3-7 7-3-3z"/><path d="M18 13l-1.5-7.5L2 2l3.5 14.5L13 18l5-5z"/><path d="M2 2l7.586 7.586"/><circle cx="11" cy="11" r="2"/></svg></div>
      <div class="apple-row-content">
        <p class="apple-row-label">ลายเซ็นเจ้าของหอ</p>
        <p class="apple-row-sublabel">สำหรับพิมพ์สัญญาอัตโนมัติ</p>
      </div>
      <?php if (!empty($ownerSignature)): ?>
      <img id="signatureRowImg" src="/dormitory_management/Public/Assets/Images/<?php echo htmlspecialchars($ownerSignature); ?>" alt="Signature" style="width: 60px; height: 30px; object-fit: contain; margin-right: 8px; background: rgba(255,255,255,0.1); border-radius: 4px; padding: 2px;">
      <?php else: ?>
      <span id="signatureRowImg" style="font-size: 12px; color: rgba(255,255,255,0.5); margin-right: 8px;">ยังไม่ได้ตั้งค่า</span>
      <?php endif; ?>
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
        <img id="logoPreviewImg" src="/dormitory_management/Public/Assets/Images/<?php echo htmlspecialchars($logoFilename); ?>" alt="Logo">
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
          <div class="apple-upload-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="32" height="32"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg></div>
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
        <img id="bgPreviewImg" src="/dormitory_management/Public/Assets/Images/<?php echo htmlspecialchars($bgFilename); ?>" alt="Background" style="width: 120px; height: 70px;">
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
          <div class="apple-upload-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="32" height="32"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg></div>
          <p class="apple-upload-text">คลิกเพื่อเลือกรูป</p>
          <p class="apple-upload-hint">รองรับ JPG, PNG, WebP</p>
          <input type="file" id="bgInput" accept="image/jpeg,image/png,image/webp">
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Sheet: Owner Signature -->
<div class="apple-sheet-overlay" id="sheet-signature">
  <div class="apple-sheet">
    <div class="apple-sheet-handle"></div>
    <div class="apple-sheet-header">
      <button class="apple-sheet-action" data-close-sheet="sheet-signature">ยกเลิก</button>
      <h3 class="apple-sheet-title">ลายเซ็นเจ้าของหอ</h3>
      <div style="width: 50px;"></div>
    </div>
    <div class="apple-sheet-body">
      <!-- Current Signature -->
      <div class="apple-image-preview" style="background: #fff; border-radius: 12px; padding: 20px;">
        <?php if (!empty($ownerSignature)): ?>
        <img id="signaturePreviewImg" src="/dormitory_management/Public/Assets/Images/<?php echo htmlspecialchars($ownerSignature); ?>" alt="Signature" style="max-width: 200px; max-height: 80px; object-fit: contain;">
        <div class="apple-image-info">
          <h4 style="color: #333;">ลายเซ็นปัจจุบัน</h4>
          <p style="color: #666;"><?php echo htmlspecialchars($ownerSignature); ?></p>
        </div>
        <?php else: ?>
        <div id="signaturePreviewImg" style="text-align: center; padding: 30px; color: #999;">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="48" height="48" style="margin-bottom: 10px; opacity: 0.5;"><path d="M12 19l7-7 3 3-7 7-3-3z"/><path d="M18 13l-1.5-7.5L2 2l3.5 14.5L13 18l5-5z"/></svg>
          <p>ยังไม่ได้ตั้งค่าลายเซ็น</p>
        </div>
        <div class="apple-image-info">
          <h4 style="color: #333;">ลายเซ็นปัจจุบัน</h4>
          <p style="color: #999;">ยังไม่ได้อัพโหลด</p>
        </div>
        <?php endif; ?>
      </div>
      
      <!-- Info Box -->
      <div style="background: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.3); border-radius: 12px; padding: 16px; margin: 16px 0;">
        <p style="font-size: 14px; color: #60a5fa; margin: 0;">
          💡 <strong>แนะนำ:</strong> ใช้ไฟล์ PNG ที่มีพื้นหลังโปร่งใส เพื่อให้ลายเซ็นแสดงบนสัญญาได้สวยงาม
        </p>
      </div>
      
      <!-- Upload new -->
      <div class="apple-input-group">
        <label class="apple-input-label">อัพโหลดลายเซ็น</label>
        
        <!-- Preview ของไฟล์ที่เลือก -->
        <div id="signatureUploadPreview" style="display: none; background: #f5f5f7; border-radius: 12px; padding: 20px; margin-bottom: 12px; text-align: center;">
          <img id="signatureUploadPreviewImg" src="" alt="Preview" style="max-width: 200px; max-height: 80px; object-fit: contain; margin-bottom: 10px;">
          <p style="font-size: 13px; color: #666; margin: 5px 0;"><strong id="signatureFileName"></strong></p>
          <p style="font-size: 12px; color: #999; margin: 0;">กำลังอัพโหลด...</p>
        </div>
        
        <!-- Hidden file input -->
        <input type="file" id="signatureInput" accept="image/png" style="display: none;" onchange="handleSignatureFileSelect(this)">
        
        <div class="apple-upload-area" id="signatureUploadArea" 
             onclick="document.getElementById('signatureInput').click()"
             ondragover="event.preventDefault(); this.classList.add('dragover');"
             ondragleave="this.classList.remove('dragover');"
             ondrop="event.preventDefault(); this.classList.remove('dragover'); handleSignatureDrop(event);">
          <div class="apple-upload-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="32" height="32"><path d="M12 19l7-7 3 3-7 7-3-3z"/><path d="M18 13l-1.5-7.5L2 2l3.5 14.5L13 18l5-5z"/><path d="M2 2l7.586 7.586"/></svg></div>
          <p class="apple-upload-text">คลิกเพื่อเลือกรูปลายเซ็น</p>
          <p class="apple-upload-hint">รองรับ PNG (แนะนำพื้นหลังโปร่งใส)</p>
          <p class="apple-upload-hint" style="margin-top: 8px; font-size: 12px;">หรือลากไฟล์มาวางที่นี่</p>
        </div>
      </div>
      
      <script>
      // Inline script for immediate signature upload handling
      function handleSignatureFileSelect(input) {
        const file = input.files[0];
        if (file) {
          processSignatureFile(file);
        }
      }
      
      function handleSignatureDrop(event) {
        const file = event.dataTransfer.files[0];
        if (file) {
          processSignatureFile(file);
        }
      }
      
      function processSignatureFile(file) {
        console.log('Processing file:', file.name, file.type);
        
        // Validate PNG
        if (file.type !== 'image/png') {
          alert('กรุณาเลือกไฟล์ PNG เท่านั้น');
          return;
        }
        
        // Show preview
        const previewContainer = document.getElementById('signatureUploadPreview');
        const previewImg = document.getElementById('signatureUploadPreviewImg');
        const fileNameSpan = document.getElementById('signatureFileName');
        const uploadArea = document.getElementById('signatureUploadArea');
        
        const reader = new FileReader();
        reader.onload = function(e) {
          previewImg.src = e.target.result;
          fileNameSpan.textContent = file.name;
          previewContainer.style.display = 'block';
          uploadArea.style.display = 'none';
        };
        reader.readAsDataURL(file);
        
        // Upload file
        const formData = new FormData();
        formData.append('signature', file);
        
        fetch('/dormitory_management/Manage/save_system_settings.php', {
          method: 'POST',
          body: formData
        })
        .then(response => response.json())
        .then(result => {
          console.log('Upload result:', result);
          if (result.success) {
            // Show success
            if (typeof appleSettings !== 'undefined' && appleSettings.showToast) {
              appleSettings.showToast('อัพโหลดลายเซ็นสำเร็จ', 'success');
            } else {
              alert('อัพโหลดลายเซ็นสำเร็จ!');
            }
            // Reload page after short delay
            setTimeout(() => location.reload(), 1000);
          } else {
            throw new Error(result.error || 'เกิดข้อผิดพลาด');
          }
        })
        .catch(error => {
          console.error('Upload error:', error);
          alert('เกิดข้อผิดพลาด: ' + error.message);
          // Reset UI
          previewContainer.style.display = 'none';
          uploadArea.style.display = 'block';
        });
      }
      </script>
      
      <?php if (!empty($ownerSignature)): ?>
      <!-- Delete Button -->
      <div style="margin-top: 20px;">
        <button type="button" id="deleteSignatureBtn" class="apple-btn" style="width: 100%; background: rgba(239, 68, 68, 0.15); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3);">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18" style="margin-right: 8px;"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
          ลบลายเซ็น
        </button>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>
