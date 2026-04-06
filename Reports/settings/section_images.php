<!-- Section: Logo Settings -->
<div class="apple-section-group">
  <h2 class="apple-section-title"><?php echo __('settings_images'); ?></h2>
  <div class="apple-section-card">
    <!-- Logo -->
    <div class="apple-settings-row" data-sheet="sheet-logo" role="button" tabindex="0" aria-haspopup="dialog" aria-controls="sheet-logo" onclick="event.preventDefault(); event.stopPropagation(); (window.appleSettings && typeof window.appleSettings.openSheet === 'function') ? window.appleSettings.openSheet('sheet-logo') : (function(){ var sheet = document.getElementById('sheet-logo'); if (sheet) { sheet.classList.add('active'); document.body.style.overflow = 'hidden'; } })();" onkeydown="if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); event.stopPropagation(); (window.appleSettings && typeof window.appleSettings.openSheet === 'function') ? window.appleSettings.openSheet('sheet-logo') : (function(){ var sheet = document.getElementById('sheet-logo'); if (sheet) { sheet.classList.add('active'); document.body.style.overflow = 'hidden'; } })(); }">
      <div class="apple-row-icon orange"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-animated"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="3"/></svg></div>
      <div class="apple-row-content">
        <p class="apple-row-label"><?php echo __('logo'); ?></p>
        <p class="apple-row-sublabel"><?php echo __('logo_desc'); ?></p>
      </div>
      <img id="logoRowImg" src="/dormitory_management/Public/Assets/Images/<?php echo htmlspecialchars($logoFilename); ?>" alt="Logo" style="width: 40px; height: 40px; border-radius: 8px; object-fit: cover; margin-right: 8px;">
      <span class="apple-row-chevron">›</span>
    </div>
    
    <!-- Background -->
    <div class="apple-settings-row" data-sheet="sheet-background">
      <div class="apple-row-icon purple"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-animated"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg></div>
      <div class="apple-row-content">
        <p class="apple-row-label"><?php echo __('background_image'); ?></p>
        <p class="apple-row-sublabel"><?php echo __('bg_desc'); ?></p>
      </div>
      <img id="bgRowImg" src="/dormitory_management/Public/Assets/Images/<?php echo htmlspecialchars($bgFilename); ?>" alt="BG" style="width: 50px; height: 30px; border-radius: 6px; object-fit: cover; margin-right: 8px;">
      <span class="apple-row-chevron">›</span>
    </div>
    
    <!-- Owner Signature -->
    <div class="apple-settings-row" data-sheet="sheet-signature">
      <div class="apple-row-icon green"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-animated"><path d="M12 19l7-7 3 3-7 7-3-3z"/><path d="M18 13l-1.5-7.5L2 2l3.5 14.5L13 18l5-5z"/><path d="M2 2l7.586 7.586"/><circle cx="11" cy="11" r="2"/></svg></div>
      <div class="apple-row-content">
        <p class="apple-row-label"><?php echo __('owner_signature'); ?></p>
        <p class="apple-row-sublabel"><?php echo __('signature_desc'); ?></p>
      </div>
      <?php if (!empty($ownerSignature)): ?>
      <img id="signatureRowImg" src="/dormitory_management/Public/Assets/Images/<?php echo htmlspecialchars($ownerSignature); ?>" alt="Signature" style="width: 60px; height: 30px; object-fit: contain; margin-right: 8px; background: rgba(255,255,255,0.1); border-radius: 4px; padding: 2px;">
      <?php else: ?>
      <span id="signatureRowImg" style="font-size: 12px; color: rgba(255,255,255,0.5); margin-right: 8px;"><?php echo __('not_set'); ?></span>
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
      <button type="button" class="apple-sheet-action" data-close-sheet="sheet-logo" onclick="event.preventDefault(); event.stopPropagation(); var sheet=document.getElementById('sheet-logo'); if (sheet) { sheet.classList.remove('active'); document.body.style.overflow=''; } "><?php echo __('cancel'); ?></button>
      <h3 class="apple-sheet-title"><?php echo __('manage_logo'); ?></h3>
      <div style="width: 50px;"></div>
    </div>
    <div class="apple-sheet-body">
      <!-- Current Logo -->
      <div class="apple-image-preview">
        <img id="logoPreviewImg" src="/dormitory_management/Public/Assets/Images/<?php echo htmlspecialchars($logoFilename); ?>" alt="Logo">
        <div class="apple-image-info">
          <h4><?php echo __('current_logo'); ?></h4>
          <p><?php echo htmlspecialchars($logoFilename); ?></p>
        </div>
      </div>
      
      <!-- Select from existing -->
      <div class="apple-input-group">
        <label class="apple-input-label"><?php echo __('select_existing'); ?></label>
        <select id="oldLogoSelect" class="apple-input">
          <option value="">-- <?php echo __('select_existing'); ?> --</option>
          <?php foreach ($imageFiles as $file): ?>
            <option value="<?php echo htmlspecialchars($file); ?>"><?php echo htmlspecialchars($file); ?></option>
          <?php endforeach; ?>
        </select>
        <div id="oldLogoPreview" style="margin-top: 12px;"></div>
      </div>
      
      <!-- Upload new -->
      <div class="apple-input-group">
        <label class="apple-input-label"><?php echo __('upload_new'); ?></label>
        <div class="apple-upload-area" onclick="document.getElementById('logoInput').click()">
          <div class="apple-upload-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="32" height="32"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg></div>
          <p class="apple-upload-text"><?php echo __('click_to_select'); ?></p>
          <p class="apple-upload-hint">รองรับ JPG, PNG</p>
          <input type="file" id="logoInput" accept="image/jpeg,image/png">
        </div>
      </div>
    </div>
  </div>
</div>

<script>
(function() {
  function escapeHtml(value) {
    return String(value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function showSheetToast(message, type) {
    if (window.appleSettings && typeof window.appleSettings.showToast === 'function') {
      window.appleSettings.showToast(message, type || 'success');
      return;
    }

    if ((type || 'success') === 'error') {
      alert(message);
    }
  }

  function closeSheetById(sheetId) {
    var overlay = document.getElementById(sheetId);
    if (!overlay) return;
    overlay.classList.remove('active');
    document.body.style.overflow = '';
  }

  function closeOverlay(overlay) {
    if (!overlay) return;
    overlay.classList.remove('active');
    document.body.style.overflow = '';
  }

  function applyOldLogo(filename) {
    if (!filename) {
      return;
    }

    fetch('/dormitory_management/Manage/save_system_settings.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'load_old_logo=' + encodeURIComponent(filename)
    })
    .then(function(response) { return response.json(); })
    .then(function(result) {
      if (!result || !result.success) {
        throw new Error((result && result.error) ? result.error : 'เปลี่ยนโลโก้ไม่สำเร็จ');
      }

      showSheetToast('เปลี่ยน Logo สำเร็จ', 'success');
      window.location.reload();
    })
    .catch(function(error) {
      showSheetToast(error.message || 'เปลี่ยนโลโก้ไม่สำเร็จ', 'error');
    });
  }

  function bindOldLogoSelectFallback() {
    var select = document.getElementById('oldLogoSelect');
    var previewContainer = document.getElementById('oldLogoPreview');
    var logoPreviewImg = document.getElementById('logoPreviewImg');
    if (!select || !previewContainer || select.__logoSelectBound) {
      return;
    }

    select.__logoSelectBound = true;
    select.dataset.logoSelectBound = '1';
    var originalPreviewSrc = logoPreviewImg ? logoPreviewImg.getAttribute('src') : '';

    function renderLocalPreview(filename) {
      if (!filename) {
        previewContainer.innerHTML = '';
        if (logoPreviewImg && originalPreviewSrc) {
          logoPreviewImg.src = originalPreviewSrc;
        }
        return;
      }

      var encoded = encodeURIComponent(filename);
      previewContainer.innerHTML = '' +
        '<img src="/dormitory_management/Public/Assets/Images/' + encoded + '" alt="Preview" style="max-width: 100px; max-height: 100px; border-radius: 12px;">' +
        '<button type="button" class="apple-button primary" data-use-old-logo="' + escapeHtml(filename) + '" style="width: auto; padding: 10px 16px; margin-top: 8px;">ใช้รูปนี้</button>';

      if (logoPreviewImg) {
        logoPreviewImg.src = '/dormitory_management/Public/Assets/Images/' + encoded;
      }
    }

    select.addEventListener('change', function() {
      var filename = (select.value || '').trim();
      renderLocalPreview(filename);
    });

    previewContainer.addEventListener('click', function(event) {
      var applyBtn = event.target.closest('[data-use-old-logo]');
      if (!applyBtn) return;

      event.preventDefault();
      event.stopPropagation();

      var filename = (applyBtn.getAttribute('data-use-old-logo') || '').trim();
      if (!filename) return;

      applyOldLogo(filename);
    });

    if (!document.__useOldLogoDelegatedBound) {
      document.__useOldLogoDelegatedBound = true;
      document.addEventListener('click', function(event) {
        var applyBtn = event.target.closest('[data-use-old-logo]');
        if (!applyBtn) {
          return;
        }

        event.preventDefault();
        event.stopPropagation();

        var filename = (applyBtn.getAttribute('data-use-old-logo') || '').trim();
        if (!filename) {
          return;
        }

        applyOldLogo(filename);
      }, true);
    }
  }

  function bindSheetHandleDragClose() {
    document.querySelectorAll('.apple-sheet-overlay .apple-sheet-handle').forEach(function(handle) {
      if (handle.__dragCloseBound) {
        return;
      }

      var overlay = handle.closest('.apple-sheet-overlay');
      if (!overlay) {
        return;
      }

      var sheet = overlay.querySelector('.apple-sheet');
      if (!sheet) {
        return;
      }

      handle.__dragCloseBound = true;
      handle.dataset.dragBound = '1';
      handle.style.touchAction = 'none';

      var startY = 0;
      var deltaY = 0;
      var dragging = false;
      var closeThreshold = 0;

      function getCloseThreshold() {
        var sheetHeight = sheet.getBoundingClientRect().height || sheet.offsetHeight || 0;
        // Close when dragged down roughly 50% of the visible sheet height.
        return Math.max(120, Math.round(sheetHeight * 0.5));
      }

      function start(clientY) {
        startY = clientY;
        deltaY = 0;
        closeThreshold = getCloseThreshold();
        dragging = true;
        sheet.style.transition = 'none';
        sheet.style.willChange = 'transform';
      }

      function move(clientY) {
        if (!dragging) return;
        deltaY = Math.max(0, clientY - startY);

        if (deltaY >= closeThreshold) {
          dragging = false;
          sheet.style.transition = 'transform 0.2s ease';
          sheet.style.willChange = '';
          sheet.style.transform = '';
          closeOverlay(overlay);
          return;
        }

        sheet.style.transform = 'translateY(' + deltaY + 'px)';
      }

      function end() {
        if (!dragging) return;
        dragging = false;
        sheet.style.transition = 'transform 0.25s cubic-bezier(0.32, 0.72, 0, 1)';
        sheet.style.willChange = '';

        if (deltaY >= closeThreshold) {
          sheet.style.transform = '';
          closeOverlay(overlay);
          return;
        }

        sheet.style.transform = '';
      }

      handle.addEventListener('pointerdown', function(event) {
        if (!overlay.classList.contains('active')) return;
        event.preventDefault();
        start(event.clientY);
        try {
          handle.setPointerCapture(event.pointerId);
        } catch (e) {}
      });

      handle.addEventListener('pointermove', function(event) {
        move(event.clientY);
      });

      handle.addEventListener('pointerup', end);
      handle.addEventListener('pointercancel', end);

      // Touch fallback for mobile browsers where pointer events are unreliable.
      handle.addEventListener('touchstart', function(event) {
        if (!overlay.classList.contains('active')) return;
        if (!event.touches || !event.touches.length) return;
        event.preventDefault();
        start(event.touches[0].clientY);
      }, { passive: false });

      handle.addEventListener('touchmove', function(event) {
        if (!event.touches || !event.touches.length) return;
        event.preventDefault();
        move(event.touches[0].clientY);
      }, { passive: false });

      handle.addEventListener('touchend', function() {
        end();
      });
    });
  }

  function bindLogoSheetCloseFallback() {
    document.querySelectorAll('[data-close-sheet="sheet-logo"]').forEach(function(btn) {
      if (btn.dataset.closeFallbackBound === '1') return;
      btn.dataset.closeFallbackBound = '1';
      btn.addEventListener('click', function(event) {
        event.preventDefault();
        event.stopPropagation();
        closeSheetById('sheet-logo');
      });
    });
  }

  function bindLogoUploadFallback() {
    var logoInput = document.getElementById('logoInput');
    if (!logoInput || logoInput.__logoFallbackBound) {
      return;
    }

    var uploadArea = document.querySelector('#sheet-logo .apple-upload-area');
    if (uploadArea && !uploadArea.__logoAreaBound) {
      uploadArea.__logoAreaBound = true;
      uploadArea.dataset.logoAreaBound = '1';
      uploadArea.addEventListener('click', function(event) {
        event.preventDefault();
        event.stopPropagation();
        logoInput.click();
      });
    }

    logoInput.__logoFallbackBound = true;
    logoInput.dataset.logoFallbackBound = '1';
    logoInput.addEventListener('change', function() {
      var file = logoInput.files && logoInput.files[0];
      if (!file) return;

      if (!/^image\/(jpeg|png)$/i.test(file.type)) {
        showSheetToast('รองรับเฉพาะไฟล์ JPG และ PNG', 'error');
        logoInput.value = '';
        return;
      }

      if (file.size > 5 * 1024 * 1024) {
        showSheetToast('ขนาดไฟล์ไม่ควรเกิน 5MB', 'error');
        logoInput.value = '';
        return;
      }

      var formData = new FormData();
      formData.append('logo', file);

      fetch('/dormitory_management/Manage/save_system_settings.php', {
        method: 'POST',
        body: formData
      })
      .then(function(response) { return response.json(); })
      .then(function(result) {
        if (!result || !result.success) {
          throw new Error((result && result.error) ? result.error : 'อัพโหลดไม่สำเร็จ');
        }

        showSheetToast('อัพโหลด Logo สำเร็จ', 'success');

        window.location.reload();
      })
      .catch(function(error) {
        showSheetToast(error.message || 'อัพโหลดไม่สำเร็จ', 'error');
      });
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
      bindSheetHandleDragClose();
      bindLogoSheetCloseFallback();
      bindOldLogoSelectFallback();
      setTimeout(bindLogoUploadFallback, 200);
    });
  } else {
    bindSheetHandleDragClose();
    bindLogoSheetCloseFallback();
    bindOldLogoSelectFallback();
    setTimeout(bindLogoUploadFallback, 200);
  }
})();
</script>

<!-- Sheet: Background -->
<div class="apple-sheet-overlay" id="sheet-background">
  <div class="apple-sheet">
    <div class="apple-sheet-handle"></div>
    <div class="apple-sheet-header">
      <button class="apple-sheet-action" data-close-sheet="sheet-background"><?php echo __('cancel'); ?></button>
      <h3 class="apple-sheet-title"><?php echo __('manage_bg'); ?></h3>
      <div style="width: 50px;"></div>
    </div>
    <div class="apple-sheet-body">
      <!-- Current Background -->
      <div class="apple-image-preview">
        <img id="bgPreviewImg" src="/dormitory_management/Public/Assets/Images/<?php echo htmlspecialchars($bgFilename); ?>" alt="Background" style="width: 120px; height: 70px;">
        <div class="apple-image-info">
          <h4><?php echo __('current_bg'); ?></h4>
          <p><?php echo htmlspecialchars($bgFilename); ?></p>
        </div>
      </div>
      
      <!-- Select from existing -->
      <div class="apple-input-group">
        <label class="apple-input-label"><?php echo __('select_existing'); ?></label>
        <select id="bgSelect" class="apple-input">
          <option value="">-- <?php echo __('select_existing'); ?> --</option>
          <?php foreach ($imageFiles as $file): ?>
            <option value="<?php echo htmlspecialchars($file); ?>" <?php echo ($file === $bgFilename) ? 'selected' : ''; ?>><?php echo htmlspecialchars($file); ?></option>
          <?php endforeach; ?>
        </select>
        <div id="bgSelectPreview" style="margin-top: 12px;"></div>
      </div>
      
      <!-- Upload new -->
      <div class="apple-input-group">
        <label class="apple-input-label"><?php echo __('upload_new'); ?></label>
        <div class="apple-upload-area" onclick="document.getElementById('bgInput').click()">
          <div class="apple-upload-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="32" height="32"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg></div>
          <p class="apple-upload-text"><?php echo __('click_to_select'); ?></p>
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
      <button class="apple-sheet-action" data-close-sheet="sheet-signature"><?php echo __('cancel'); ?></button>
      <h3 class="apple-sheet-title"><?php echo __('owner_signature'); ?></h3>
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
          <p style="color: #999;"><?php echo __('not_uploaded'); ?></p>
        </div>
        <div class="apple-image-info">
          <h4 style="color: #333;"><?php echo __('current_signature'); ?></h4>
          <p style="color: #999;"><?php echo __('not_uploaded'); ?></p>
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
        <label class="apple-input-label"><?php echo __('upload_signature'); ?></label>
        
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
