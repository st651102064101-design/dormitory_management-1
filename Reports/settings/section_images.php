<!-- Section: Logo Settings -->
<div class="apple-section-group">
  <h2 class="apple-section-title"><?php echo __('settings_images'); ?></h2>
  <div class="apple-section-card">
    <!-- Logo -->
    <div class="apple-settings-row" data-sheet="sheet-logo" role="button" tabindex="0" aria-haspopup="dialog" aria-controls="sheet-logo">
      <div class="apple-row-icon orange"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-animated"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="3"/></svg></div>
      <div class="apple-row-content">
        <p class="apple-row-label"><?php echo __('logo'); ?></p>
        <p class="apple-row-sublabel"><?php echo __('logo_desc'); ?></p>
      </div>
      <img id="logoRowImg" src="/dormitory_management/Public/Assets/Images/<?php echo htmlspecialchars($logoFilename); ?>" alt="Logo" style="width: 40px; height: 40px; border-radius: 8px; object-fit: cover; margin-right: 8px;">
      <span class="apple-row-chevron">›</span>
    </div>
    
    <!-- Background -->
    <div class="apple-settings-row" data-sheet="sheet-background" role="button" tabindex="0" aria-haspopup="dialog" aria-controls="sheet-background">
      <div class="apple-row-icon purple"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-animated"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg></div>
      <div class="apple-row-content">
        <p class="apple-row-label"><?php echo __('background_image'); ?></p>
        <p class="apple-row-sublabel"><?php echo __('bg_desc'); ?></p>
      </div>
      <img id="bgRowImg" src="/dormitory_management/Public/Assets/Images/<?php echo htmlspecialchars($bgFilename); ?>" alt="BG" style="width: 50px; height: 30px; border-radius: 6px; object-fit: cover; margin-right: 8px;">
      <span class="apple-row-chevron">›</span>
    </div>
    
    <!-- Owner Signature -->
    <div class="apple-settings-row" data-sheet="sheet-signature" role="button" tabindex="0" aria-haspopup="dialog" aria-controls="sheet-signature">
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
      <button type="button" class="apple-sheet-action" data-close-sheet="sheet-logo"><?php echo __('cancel'); ?></button>
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
        <div style="display: flex; gap: 8px; margin-top: 8px;">
          <select id="oldLogoSelect" class="apple-input" style="flex: 1;">
            <option value="">-- <?php echo __('select_existing'); ?> --</option>
            <?php foreach ($imageFiles as $file): ?>
              <option value="<?php echo htmlspecialchars($file); ?>"><?php echo htmlspecialchars($file); ?></option>
            <?php endforeach; ?>
          </select>
          <button type="button" id="deleteLogoBtn" class="apple-btn" style="background: rgba(239, 68, 68, 0.15); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3); padding: 10px 16px; border-radius: 8px; cursor: pointer; white-space: nowrap; opacity: 0.5; pointer-events: none;" disabled>ลบรูป</button>
        </div>
        <div id="oldLogoPreview" style="margin-top: 12px;"></div>
      </div>
      
      <!-- Upload new -->
      <div class="apple-input-group">
        <label class="apple-input-label"><?php echo __('upload_new'); ?></label>
        <div class="apple-upload-area">
          <div class="apple-upload-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="32" height="32"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg></div>
          <p class="apple-upload-text"><?php echo __('click_to_select'); ?></p>
          <p class="apple-upload-hint">รองรับ JPG, PNG</p>
          <input type="file" id="logoInput" name="logo" accept="image/jpeg,image/png" style="display:block !important; position:absolute; inset:0; width:100%; height:100%; opacity:0; cursor:pointer; pointer-events:auto; z-index:3;">
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

  function showInlineToast(message, type) {
    var existingToast = document.querySelector('.apple-toast');
    if (existingToast && existingToast.parentNode) {
      existingToast.parentNode.removeChild(existingToast);
    }

    var toast = document.createElement('div');
    toast.className = 'apple-toast';

    var icon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>';
    if (type === 'success') icon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px;"><polyline points="20 6 9 17 4 12"/></svg>';
    if (type === 'error') icon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px;"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';

    toast.innerHTML = '<span class="apple-toast-icon">' + icon + '</span>' + escapeHtml(message);
    document.body.appendChild(toast);

    requestAnimationFrame(function() {
      toast.classList.add('show');
    });

    setTimeout(function() {
      toast.classList.remove('show');
      setTimeout(function() {
        if (toast.parentNode) {
          toast.parentNode.removeChild(toast);
        }
      }, 300);
    }, 2000);
  }

  function showSheetToast(message, type) {
    var settingsInstance = null;

    if (typeof appleSettings !== 'undefined' && appleSettings && typeof appleSettings.showToast === 'function') {
      settingsInstance = appleSettings;
    } else if (window.appleSettings && typeof window.appleSettings.showToast === 'function') {
      settingsInstance = window.appleSettings;
    }

    if (settingsInstance) {
      settingsInstance.showToast(message, type || 'success');
      return;
    }

    showInlineToast(message, type || 'info');
  }

  function getSettingsInstance() {
    if (window.appleSettings && typeof window.appleSettings.showConfirm === 'function') {
      return window.appleSettings;
    }

    if (typeof appleSettings !== 'undefined' && appleSettings && typeof appleSettings.showConfirm === 'function') {
      // Keep a stable global reference for inline handlers.
      window.appleSettings = appleSettings;
      return appleSettings;
    }

    return null;
  }

  // Shared helper: Build image URL with path-safe encoding (supports nested paths like Payments/...)
  function buildImageUrl(filename) {
    var normalized = String(filename || '').trim().replace(/\\+/g, '/').replace(/^\/+/, '');
    if (!normalized) {
      return '/dormitory_management/Public/Assets/Images/';
    }
    var encodedPath = normalized
      .split('/')
      .filter(function(part) { return part !== ''; })
      .map(function(part) { return encodeURIComponent(part); })
      .join('/');
    return '/dormitory_management/Public/Assets/Images/' + encodedPath;
  }

  // Force reload image by creating new element (bypasses browser cache completely)
  function forceReloadImage(imgElement, newSrc) {
    if (!imgElement) return;
    // Create new img to force browser to re-fetch
    var tempImg = new Image();
    tempImg.onload = function() {
      imgElement.src = newSrc;
      console.log('[forceReloadImage] Image reloaded:', newSrc);
    };
    tempImg.onerror = function() {
      // Even if preload fails, try to set src anyway
      imgElement.src = newSrc;
      console.warn('[forceReloadImage] Preload failed but setting src anyway:', newSrc);
    };
    tempImg.src = newSrc;
  }

  // Shared helper: Sync all logo UI elements from a single filename with cache-busting
  function syncLogoUiFromFilename(filename) {
    if (!filename) {
      console.warn('[syncLogoUiFromFilename] Empty filename provided');
      return;
    }
    var newSrc = buildImageUrl(filename) + '?t=' + Date.now();
    console.log('[syncLogoUiFromFilename] Updating with filename:', filename, 'newSrc:', newSrc);
    
    // Update preview in logo sheet (MUST be specific to logo sheet - use parent context)
    var logoPreviewImg = document.getElementById('logoPreviewImg');
    if (logoPreviewImg) {
      console.log('[syncLogoUiFromFilename] Updating #logoPreviewImg');
      forceReloadImage(logoPreviewImg, newSrc);
      logoPreviewImg.dataset.baseSrc = buildImageUrl(filename);
    } else {
      console.warn('[syncLogoUiFromFilename] #logoPreviewImg not found');
    }
    
    // Update thumbnail in settings row
    var logoRowImg = document.getElementById('logoRowImg');
    if (logoRowImg) {
      console.log('[syncLogoUiFromFilename] Updating #logoRowImg');
      forceReloadImage(logoRowImg, newSrc);
    } else {
      console.warn('[syncLogoUiFromFilename] #logoRowImg not found');
    }
    
    // Update sidebar logo icons with cache bust
    var sidebarLogos = document.querySelectorAll('.team-avatar-img');
    if (sidebarLogos && sidebarLogos.length > 0) {
      sidebarLogos.forEach(function(img) {
        forceReloadImage(img, newSrc);
      });
      console.log('[syncLogoUiFromFilename] Updated', sidebarLogos.length, '.team-avatar-img elements');
    }

    ensureSelectHasOption(document.getElementById('oldLogoSelect'), filename);
    
    // Update any other logo images on page
    var allLogos = document.querySelectorAll('img[alt="Logo"]');
    if (allLogos && allLogos.length > 0) {
      var count = 0;
      allLogos.forEach(function(img) {
        if (img.id !== 'logoPreviewImg' && !img.classList.contains('team-avatar-img')) {
          forceReloadImage(img, newSrc);
          count++;
        }
      });
      if (count > 0) console.log('[syncLogoUiFromFilename] Updated', count, 'other img[alt="Logo"] elements');
    }
    
    // Update filename text - MUST be inside #sheet-logo to avoid background/signature
    var logoSheet = document.getElementById('sheet-logo');
    if (logoSheet) {
      var infoP = logoSheet.querySelector('.apple-image-preview .apple-image-info p');
      if (infoP) {
        console.log('[syncLogoUiFromFilename] Updating filename text to:', filename);
        infoP.textContent = filename;
      } else {
        console.warn('[syncLogoUiFromFilename] .apple-image-info p not found in logo sheet');
      }
    } else {
      console.warn('[syncLogoUiFromFilename] #sheet-logo not found');
    }
    
    console.log('[syncLogoUiFromFilename] Sync complete');
  }

  // Shared helper: Sync background UI elements from a single filename with cache-busting
  function syncBgUiFromFilename(filename) {
    if (!filename) {
      return;
    }

    var newSrc = buildImageUrl(filename) + '?t=' + Date.now();

    var bgPreviewImg = document.getElementById('bgPreviewImg');
    if (bgPreviewImg) {
      forceReloadImage(bgPreviewImg, newSrc);
      bgPreviewImg.dataset.baseSrc = buildImageUrl(filename);
    }

    var bgRowImg = document.getElementById('bgRowImg');
    if (bgRowImg) {
      forceReloadImage(bgRowImg, newSrc);
    }

    var bgInfoP = document.querySelector('#sheet-background .apple-image-preview .apple-image-info p');
    if (bgInfoP) {
      bgInfoP.textContent = filename;
    }

    ensureSelectHasOption(document.getElementById('bgSelect'), filename);
  }

  function closeSheetById(sheetId) {
    var overlay = document.getElementById(sheetId);
    if (!overlay) return;
    overlay.classList.remove('active');
    document.body.style.overflow = '';
  }

  function openSheetById(sheetId, context) {
    var overlay = document.getElementById(sheetId);
    if (!overlay && sheetId === 'sheet-billing-schedule' && typeof window.ensureBillingScheduleSheetFallback === 'function') {
      try {
        window.ensureBillingScheduleSheetFallback();
      } catch (fallbackError) {
        if (context && context.rowId === 'billingScheduleRow') {
          console.warn('[SheetDebug] Failed to build billing schedule fallback sheet', fallbackError);
        }
      }

      overlay = document.getElementById(sheetId);
    }

    if (!overlay) {
      if (context && context.rowId === 'billingScheduleRow') {
        console.error('[SheetDebug] Overlay not found for sheet:', sheetId, context);
      }
      return false;
    }
    overlay.classList.add('active');
    document.body.style.overflow = 'hidden';

    if (context && context.rowId === 'billingScheduleRow') {
      console.info('[SheetDebug] Opened sheet via openSheetById:', sheetId, context);
    }

    return true;
  }

  function closeOverlay(overlay) {
    if (!overlay) return;
    overlay.classList.remove('active');
    document.body.style.overflow = '';
  }

  function ensureSelectHasOption(selectEl, value) {
    if (!selectEl) {
      return;
    }

    var normalizedValue = String(value || '').trim();
    if (!normalizedValue) {
      return;
    }

    for (var i = 0; i < selectEl.options.length; i++) {
      if ((selectEl.options[i].value || '').trim() === normalizedValue) {
        return;
      }
    }

    var option = document.createElement('option');
    option.value = normalizedValue;
    option.textContent = normalizedValue;
    selectEl.appendChild(option);
  }

  function createSheetInteractionComponent(options) {
    var config = options || {};
    var closeRatio = typeof config.closeRatio === 'number' ? config.closeRatio : 0.5;
    var minClosePx = typeof config.minClosePx === 'number' ? config.minClosePx : 120;

    function getSheetThreshold(sheet) {
      var height = sheet.getBoundingClientRect().height || sheet.offsetHeight || 0;
      return Math.max(minClosePx, Math.round(height * closeRatio));
    }

    function bindRowOpenFallback() {
      if (document.__appleSheetRowFallbackBound) {
        return;
      }

      document.__appleSheetRowFallbackBound = true;

      document.querySelectorAll('.apple-settings-row[data-sheet]').forEach(function(row) {
        if (!row.hasAttribute('role')) {
          row.setAttribute('role', 'button');
        }
        if (!row.hasAttribute('tabindex')) {
          row.setAttribute('tabindex', '0');
        }
      });

      function logBillingIssue(level, message, context) {
        if (!context || context.rowId !== 'billingScheduleRow') {
          return;
        }

        var payload = context || {};
        if (level === 'error') {
          console.error('[SheetDebug]', message, payload);
          return;
        }
        if (level === 'warn') {
          console.warn('[SheetDebug]', message, payload);
          return;
        }
        console.info('[SheetDebug]', message, payload);
      }

      function tryOpenSheetFromFallback(row, sheetId, source) {
        var context = {
          source: source,
          rowId: row && row.id ? row.id : '',
          sheetId: sheetId
        };

        var opened = false;
        if (window.appleSettings && typeof window.appleSettings.openSheet === 'function') {
          opened = window.appleSettings.openSheet(sheetId, context) === true;
          if (!opened) {
            logBillingIssue('warn', 'appleSettings.openSheet returned false, trying DOM fallback', context);
          }
        }

        if (!opened) {
          opened = openSheetById(sheetId, context) === true;
        }

        if (!opened) {
          logBillingIssue('error', 'Unable to open sheet from row fallback', context);
        }

        return opened;
      }

      document.addEventListener('click', function(event) {
        var row = event.target.closest('.apple-settings-row[data-sheet]');
        if (!row) {
          return;
        }

        var contextBase = {
          source: 'section_images.bindRowOpenFallback.click',
          rowId: row.id || '',
          targetTag: event.target && event.target.tagName ? event.target.tagName : ''
        };

        if (event.defaultPrevented) {
          logBillingIssue('warn', 'Click was already prevented before fallback handler', contextBase);
          return;
        }

        if (event.target.closest('button, input, select, textarea, a, label, [data-close-sheet]')) {
          logBillingIssue('info', 'Ignored click on interactive child element', contextBase);
          return;
        }

        var sheetId = (row.getAttribute('data-sheet') || '').trim();
        if (!sheetId) {
          logBillingIssue('error', 'Row missing data-sheet attribute', contextBase);
          return;
        }

        var opened = tryOpenSheetFromFallback(row, sheetId, 'section_images.bindRowOpenFallback.click');
        if (!opened) {
          return;
        }

        event.preventDefault();
        event.stopPropagation();
      }, true);

      document.addEventListener('keydown', function(event) {
        if (event.key !== 'Enter' && event.key !== ' ') {
          return;
        }

        var row = event.target.closest('.apple-settings-row[data-sheet]');
        if (!row) {
          return;
        }

        var contextBase = {
          source: 'section_images.bindRowOpenFallback.keydown',
          rowId: row.id || '',
          key: event.key
        };

        if (event.defaultPrevented) {
          logBillingIssue('warn', 'Keydown was already prevented before fallback handler', contextBase);
          return;
        }

        var sheetId = (row.getAttribute('data-sheet') || '').trim();
        if (!sheetId) {
          logBillingIssue('error', 'Row missing data-sheet attribute on keydown', contextBase);
          return;
        }

        var opened = tryOpenSheetFromFallback(row, sheetId, 'section_images.bindRowOpenFallback.keydown');
        if (!opened) {
          return;
        }

        event.preventDefault();
        event.stopPropagation();
      }, true);
    }

    function bindCloseButtonsFallback() {
      if (!document.__appleSheetCloseFallbackBound) {
        document.__appleSheetCloseFallbackBound = true;
        document.addEventListener('click', function(event) {
          var closeBtn = event.target.closest('[data-close-sheet]');
          if (!closeBtn) {
            return;
          }

          var sheetId = (closeBtn.getAttribute('data-close-sheet') || '').trim();
          if (!sheetId) {
            return;
          }

          event.preventDefault();
          event.stopPropagation();

          if (window.appleSettings && typeof window.appleSettings.closeSheet === 'function') {
            window.appleSettings.closeSheet(sheetId);
            return;
          }

          closeSheetById(sheetId);
        }, true);
      }

      document.querySelectorAll('[data-close-sheet]').forEach(function(btn) {
        if (!btn.getAttribute('type')) {
          btn.setAttribute('type', 'button');
        }
      });
    }

    function bindHandleDragClose() {
      document.querySelectorAll('.apple-sheet-overlay .apple-sheet-handle').forEach(function(handle) {
        if (handle.dataset.dragComponentBound === '1') {
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

        handle.dataset.dragComponentBound = '1';
        handle.dataset.dragBound = '1';
        handle.style.touchAction = 'none';

        var startY = 0;
        var deltaY = 0;
        var dragging = false;
        var closeThreshold = 0;

        function start(clientY) {
          startY = clientY;
          deltaY = 0;
          closeThreshold = getSheetThreshold(sheet);
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

    function init() {
      bindRowOpenFallback();
      bindCloseButtonsFallback();
      bindHandleDragClose();
    }

    return {
      init: init,
      refresh: bindHandleDragClose,
      open: openSheetById,
      close: closeSheetById
    };
  }

  function uploadLogoFile(file, inputEl) {
    if (!file) {
      return;
    }

    if (!/^image\/(jpeg|png)$/i.test(file.type)) {
      showSheetToast('รองรับเฉพาะไฟล์ JPG และ PNG', 'error');
      if (inputEl) inputEl.value = '';
      return;
    }

    if (file.size > 5 * 1024 * 1024) {
      showSheetToast('ขนาดไฟล์ไม่ควรเกิน 5MB', 'error');
      if (inputEl) inputEl.value = '';
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
      console.log('[uploadLogoFile] API response:', result);
      if (!result || !result.success) {
        throw new Error((result && result.error) ? result.error : 'อัพโหลดไม่สำเร็จ');
      }

      showSheetToast('อัพโหลด Logo สำเร็จ', 'success');
      console.log('[uploadLogoFile] Uploaded successfully, filename:', result.filename);
      
      // Sync UI with new filename instead of reloading page
      if (result.filename) {
        console.log('[uploadLogoFile] Calling syncLogoUiFromFilename with:', result.filename);
        syncLogoUiFromFilename(result.filename);
      } else {
        console.warn('[uploadLogoFile] No filename in result!');
      }
      
      // Reset input and close sheet
      if (inputEl) inputEl.value = '';
      setTimeout(function() {
        closeSheetById('sheet-logo');
      }, 500);
    })
    .catch(function(error) {
      console.error('[uploadLogoFile] Error occurred:', error);
      showSheetToast(error.message || 'อัพโหลดไม่สำเร็จ', 'error');
      if (inputEl) inputEl.value = '';
    });
  }

  window.__uploadLogoFromInput = function(inputEl) {
    var file = inputEl && inputEl.files && inputEl.files[0];
    uploadLogoFile(file, inputEl || null);
  };

  function uploadBgFile(file, inputEl) {
    if (!file) {
      return;
    }

    if (!/^image\/(jpeg|png|webp)$/i.test(file.type)) {
      showSheetToast('รองรับเฉพาะไฟล์ JPG, PNG และ WebP', 'error');
      if (inputEl) inputEl.value = '';
      return;
    }

    if (file.size > 10 * 1024 * 1024) {
      showSheetToast('ขนาดไฟล์ไม่ควรเกิน 10MB', 'error');
      if (inputEl) inputEl.value = '';
      return;
    }

    var formData = new FormData();
    formData.append('bg', file);

    fetch('/dormitory_management/Manage/save_system_settings.php', {
      method: 'POST',
      body: formData
    })
    .then(function(response) { return response.json(); })
    .then(function(result) {
      if (!result || !result.success) {
        throw new Error((result && result.error) ? result.error : 'อัพโหลดภาพพื้นหลังไม่สำเร็จ');
      }

      var newFilename = (result.filename || '').trim();
      if (newFilename) {
        syncBgUiFromFilename(newFilename);
      }

      showSheetToast('อัพโหลดภาพพื้นหลังสำเร็จ', 'success');

      var bgSelectEl = document.getElementById('bgSelect');
      if (bgSelectEl) {
        bgSelectEl.value = '';
      }
      var bgSelectPreview = document.getElementById('bgSelectPreview');
      if (bgSelectPreview) {
        bgSelectPreview.innerHTML = '';
      }

      if (inputEl) inputEl.value = '';

      setTimeout(function() {
        closeSheetById('sheet-background');
      }, 250);
    })
    .catch(function(error) {
      showSheetToast(error.message || 'อัพโหลดภาพพื้นหลังไม่สำเร็จ', 'error');
      if (inputEl) inputEl.value = '';
    });
  }

  window.__uploadBgFromInput = function(inputEl) {
    var file = inputEl && inputEl.files && inputEl.files[0];
    uploadBgFile(file, inputEl || null);
  };

  function applyOldBg(filename) {
    if (!filename) {
      return;
    }

    fetch('/dormitory_management/Manage/save_system_settings.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'bg_filename=' + encodeURIComponent(filename)
    })
    .then(function(response) { return response.json(); })
    .then(function(result) {
      if (!result || !result.success) {
        throw new Error((result && result.error) ? result.error : 'เปลี่ยนภาพพื้นหลังไม่สำเร็จ');
      }

      var newFilename = (result.filename || filename || '').trim();
      if (newFilename) {
        syncBgUiFromFilename(newFilename);
      }

      showSheetToast('เปลี่ยนภาพพื้นหลังสำเร็จ', 'success');

      var bgSelectEl = document.getElementById('bgSelect');
      if (bgSelectEl) {
        bgSelectEl.value = '';
      }
      var bgSelectPreview = document.getElementById('bgSelectPreview');
      if (bgSelectPreview) {
        bgSelectPreview.innerHTML = '';
      }

      setTimeout(function() {
        closeSheetById('sheet-background');
      }, 250);
    })
    .catch(function(error) {
      showSheetToast(error.message || 'เปลี่ยนภาพพื้นหลังไม่สำเร็จ', 'error');
    });
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
      console.log('[applyOldLogo] API response:', result);
      if (!result || !result.success) {
        throw new Error((result && result.error) ? result.error : 'เปลี่ยนโลโก้ไม่สำเร็จ');
      }

      showSheetToast('เปลี่ยน Logo สำเร็จ', 'success');
      console.log('[applyOldLogo] Applied successfully, filename:', result.filename);
      
      // Sync UI with result filename (fallback to param filename) instead of reloading page
      var newFilename = (result.filename || filename || '').trim();
      if (newFilename) {
        console.log('[applyOldLogo] Calling syncLogoUiFromFilename with:', newFilename);
        syncLogoUiFromFilename(newFilename);
      } else {
        console.warn('[applyOldLogo] No filename available!');
      }
      
      // Clear old logo selector and preview, then close sheet
      var selectEl = document.getElementById('oldLogoSelect');
      if (selectEl) selectEl.value = '';
      var previewDiv = document.getElementById('oldLogoPreview');
      if (previewDiv) previewDiv.innerHTML = '';
      setTimeout(function() {
        closeSheetById('sheet-logo');
      }, 500);
    })
    .catch(function(error) {
      showSheetToast(error.message || 'เปลี่ยนโลโก้ไม่สำเร็จ', 'error');
    });
  }

  function showInlineConfirmDialog(title, message, onConfirm) {
    var existingDialog = document.querySelector('.apple-confirm-overlay');
    if (existingDialog) {
      existingDialog.remove();
    }

    var overlay = document.createElement('div');
    overlay.className = 'apple-confirm-overlay';
    overlay.innerHTML = '' +
      '<div class="apple-confirm-dialog">' +
        '<div class="apple-confirm-content">' +
          '<h3 class="apple-confirm-title">' + escapeHtml(title) + '</h3>' +
          '<p class="apple-confirm-message">' + escapeHtml(message) + '</p>' +
        '</div>' +
        '<div class="apple-confirm-actions">' +
          '<button type="button" class="apple-confirm-btn cancel">ยกเลิก</button>' +
          '<button type="button" class="apple-confirm-btn confirm">ตกลง</button>' +
        '</div>' +
      '</div>';

    document.body.appendChild(overlay);

    requestAnimationFrame(function() {
      overlay.classList.add('show');
    });

    var isClosed = false;
    var escHandler = function(event) {
      if (event.key === 'Escape') {
        closeDialog(false);
      }
    };

    function closeDialog(confirmed) {
      if (isClosed) {
        return;
      }
      isClosed = true;
      document.removeEventListener('keydown', escHandler);

      overlay.classList.remove('show');
      window.setTimeout(function() {
        if (overlay.parentNode) {
          overlay.parentNode.removeChild(overlay);
        }
      }, 200);

      if (confirmed && typeof onConfirm === 'function') {
        onConfirm();
      }
    }

    var cancelBtn = overlay.querySelector('.cancel');
    var confirmBtn = overlay.querySelector('.confirm');

    if (cancelBtn) {
      cancelBtn.addEventListener('click', function() {
        closeDialog(false);
      });
    }

    if (confirmBtn) {
      confirmBtn.addEventListener('click', function() {
        closeDialog(true);
      });
    }

    overlay.addEventListener('click', function(event) {
      if (event.target === overlay) {
        closeDialog(false);
      }
    });

    document.addEventListener('keydown', escHandler);
  }

  // Custom confirmation dialog for delete actions
  function showDeleteConfirmation(filename, onConfirm) {
    var message = 'คุณแน่ใจหรือว่าต้องการลบรูป ' + filename + ' ?';
    var settingsInstance = getSettingsInstance();

    // Prefer project confirm component
    if (settingsInstance && typeof settingsInstance.showConfirm === 'function') {
      settingsInstance.showConfirm(message, 'ยืนยันการลบรูป').then(function(confirmed) {
        if (confirmed) {
          onConfirm();
        }
      });
      return;
    }

    // Fallback to global confirm dialog component (used in other pages)
    if (typeof window.showConfirmDialog === 'function') {
      window.showConfirmDialog('ยืนยันการลบรูป', message, 'warning').then(function(confirmed) {
        if (confirmed) {
          onConfirm();
        }
      });
      return;
    }

    // Final fallback: local component-style dialog (never use native confirm)
    showInlineConfirmDialog('ยืนยันการลบรูป', message, onConfirm);
  }

  function deleteSelectedLogo(filename) {
    if (!filename) {
      console.warn('[deleteSelectedLogo] Empty filename');
      return;
    }

    console.log('[deleteSelectedLogo] Deleting logo:', filename);

    fetch('/dormitory_management/Manage/save_system_settings.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'delete_image=' + encodeURIComponent(filename)
    })
    .then(function(response) {
      console.log('[deleteSelectedLogo] Response status:', response.status, response.statusText);
      return response.json();
    })
    .then(function(result) {
      console.log('[deleteSelectedLogo] API response:', JSON.stringify(result));
      if (!result || !result.success) {
        var errorMsg = (result && result.error) ? result.error : 'ไม่สามารถลบรูปได้';
        console.error('[deleteSelectedLogo] Delete failed:', errorMsg);
        throw new Error(errorMsg);
      }

      showSheetToast('ลบรูปสำเร็จ', 'success');
      console.log('[deleteSelectedLogo] Deleted successfully');
      
      // Reset selection and preview
      var select = document.getElementById('oldLogoSelect');
      if (select) {
        select.value = '';
      }
      var previewDiv = document.getElementById('oldLogoPreview');
      if (previewDiv) {
        previewDiv.innerHTML = '';
      }
      var deleteBtn = document.getElementById('deleteLogoBtn');
      if (deleteBtn) {
        deleteBtn.disabled = true;
        deleteBtn.style.opacity = '0.5';
        deleteBtn.style.pointerEvents = 'none';
      }

      // Remove deleted option from logo and background selects without page reload
      function removeOptionByValue(selectEl, value) {
        if (!selectEl) return;
        for (var i = selectEl.options.length - 1; i >= 0; i--) {
          if ((selectEl.options[i].value || '').trim() === value) {
            selectEl.remove(i);
          }
        }
      }

      removeOptionByValue(document.getElementById('oldLogoSelect'), filename);
      removeOptionByValue(document.getElementById('bgSelect'), filename);

      // Restore preview image to current logo when available
      var logoPreviewImg = document.getElementById('logoPreviewImg');
      if (logoPreviewImg) {
        var baseSrc = logoPreviewImg.dataset.baseSrc || '';
        if (baseSrc) {
          logoPreviewImg.src = baseSrc + '?t=' + Date.now();
        }
      }

      console.log('[deleteSelectedLogo] UI refreshed without page reload');
    })
    .catch(function(error) {
      console.error('[deleteSelectedLogo] Error occurred:', error.message || error);
      showSheetToast(error.message || 'ไม่สามารถลบรูปได้', 'error');
    });
  }

  function deleteSelectedBg(filename) {
    if (!filename) {
      console.warn('[deleteSelectedBg] Empty filename');
      return;
    }

    fetch('/dormitory_management/Manage/save_system_settings.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'delete_image=' + encodeURIComponent(filename)
    })
    .then(function(response) {
      return response.json();
    })
    .then(function(result) {
      if (!result || !result.success) {
        throw new Error((result && result.error) ? result.error : 'ไม่สามารถลบรูปได้');
      }

      showSheetToast('ลบรูปสำเร็จ', 'success');

      var bgSelect = document.getElementById('bgSelect');
      if (bgSelect) {
        bgSelect.value = '';
      }

      var bgSelectPreview = document.getElementById('bgSelectPreview');
      if (bgSelectPreview) {
        bgSelectPreview.innerHTML = '';
      }

      var deleteBgBtn = document.getElementById('deleteBgBtn');
      if (deleteBgBtn) {
        deleteBgBtn.disabled = true;
        deleteBgBtn.style.opacity = '0.5';
        deleteBgBtn.style.pointerEvents = 'none';
      }

      function removeOptionByValue(selectEl, value) {
        if (!selectEl) return;
        for (var i = selectEl.options.length - 1; i >= 0; i--) {
          if ((selectEl.options[i].value || '').trim() === value) {
            selectEl.remove(i);
          }
        }
      }

      removeOptionByValue(document.getElementById('bgSelect'), filename);
      removeOptionByValue(document.getElementById('oldLogoSelect'), filename);

      var bgPreviewImg = document.getElementById('bgPreviewImg');
      if (bgPreviewImg) {
        var baseSrc = bgPreviewImg.dataset.baseSrc || '';
        if (baseSrc) {
          bgPreviewImg.src = baseSrc + '?t=' + Date.now();
        }
      }
    })
    .catch(function(error) {
      showSheetToast(error.message || 'ไม่สามารถลบรูปได้', 'error');
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
    if (logoPreviewImg && originalPreviewSrc) {
      logoPreviewImg.dataset.baseSrc = originalPreviewSrc;
    }
    var deleteLogoBtn = document.getElementById('deleteLogoBtn');

    function renderLocalPreview(filename) {
      if (!filename) {
        previewContainer.innerHTML = '';
        if (logoPreviewImg && originalPreviewSrc) {
          logoPreviewImg.src = originalPreviewSrc;
        }
        // Disable delete button when nothing is selected
        if (deleteLogoBtn) {
          deleteLogoBtn.disabled = true;
          deleteLogoBtn.style.opacity = '0.5';
          deleteLogoBtn.style.pointerEvents = 'none';
        }
        return;
      }

      var imageUrl = buildImageUrl(filename);
      previewContainer.innerHTML = '' +
        '<img src="' + imageUrl + '" alt="Preview" style="max-width: 100px; max-height: 100px; border-radius: 12px;">' +
        '<button type="button" class="apple-button primary" data-use-old-logo="' + escapeHtml(filename) + '" style="width: auto; padding: 10px 16px; margin-top: 8px;">ใช้รูปนี้</button>';

      if (logoPreviewImg) {
        logoPreviewImg.src = imageUrl;
      }
      
      // Enable delete button when a logo is selected
      if (deleteLogoBtn) {
        deleteLogoBtn.disabled = false;
        deleteLogoBtn.style.opacity = '1';
        deleteLogoBtn.style.pointerEvents = 'auto';
      }
    }

    select.addEventListener('change', function() {
      var filename = (select.value || '').trim();
      renderLocalPreview(filename);
    });

    // Handle delete button click
    if (deleteLogoBtn) {
      deleteLogoBtn.addEventListener('click', function(event) {
        event.preventDefault();
        event.stopPropagation();
        var filename = (select.value || '').trim();
        if (!filename) {
          showSheetToast('กรุณาเลือกรูปที่ต้องการลบ', 'error');
          return;
        }
        
        // Use custom confirmation dialog
        showDeleteConfirmation(filename, function() {
          deleteSelectedLogo(filename);
        });
      });
    }

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

  if (!window.appleSheetComponent || typeof window.appleSheetComponent.init !== 'function') {
    window.appleSheetComponent = createSheetInteractionComponent({
      closeRatio: 0.5,
      minClosePx: 120
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
        // Keep native click behavior when the file input itself is the target.
        if (event.target === logoInput || event.target.closest('#logoInput')) {
          return;
        }

        logoInput.click();
      });
    }

    logoInput.__logoFallbackBound = true;
    logoInput.dataset.logoFallbackBound = '1';

    // Bind fallback upload only when primary AppleSettings handler is not attached.
    var hasPrimaryLogoHandler = logoInput.dataset.appleUploadBound === '1';
    if (!logoInput.__logoChangeFallbackBound && !hasPrimaryLogoHandler) {
      logoInput.__logoChangeFallbackBound = true;
      logoInput.addEventListener('change', function() {
        window.__uploadLogoFromInput(logoInput);
      });
    }
  }

  function bindBgSelectFallback() {
    var bgSelect = document.getElementById('bgSelect');
    var previewContainer = document.getElementById('bgSelectPreview');
    var bgPreviewImg = document.getElementById('bgPreviewImg');
    var deleteBgBtn = document.getElementById('deleteBgBtn');
    if (!bgSelect || !previewContainer || bgSelect.__bgSelectBound) {
      return;
    }

    bgSelect.__bgSelectBound = true;
    bgSelect.dataset.bgSelectBound = '1';
    var originalPreviewSrc = bgPreviewImg ? bgPreviewImg.getAttribute('src') : '';
    if (bgPreviewImg && originalPreviewSrc) {
      bgPreviewImg.dataset.baseSrc = originalPreviewSrc;
    }

    function renderBgLocalPreview(filename) {
      if (!filename) {
        previewContainer.innerHTML = '';
        if (bgPreviewImg && originalPreviewSrc) {
          bgPreviewImg.src = originalPreviewSrc;
        }
        if (deleteBgBtn) {
          deleteBgBtn.disabled = true;
          deleteBgBtn.style.opacity = '0.5';
          deleteBgBtn.style.pointerEvents = 'none';
        }
        return;
      }

      var imageUrl = buildImageUrl(filename);
      previewContainer.innerHTML = '' +
        '<img src="' + imageUrl + '" alt="Preview" style="max-width: 200px; max-height: 120px; border-radius: 12px; object-fit: cover;">' +
        '<button type="button" class="apple-button primary" data-use-old-bg="' + escapeHtml(filename) + '" style="width: auto; padding: 10px 16px; margin-top: 8px;">ใช้ภาพนี้</button>';

      if (bgPreviewImg) {
        bgPreviewImg.src = imageUrl;
      }

      if (deleteBgBtn) {
        deleteBgBtn.disabled = false;
        deleteBgBtn.style.opacity = '1';
        deleteBgBtn.style.pointerEvents = 'auto';
      }
    }

    bgSelect.addEventListener('change', function() {
      var filename = (bgSelect.value || '').trim();
      renderBgLocalPreview(filename);
    });

    if (deleteBgBtn) {
      deleteBgBtn.addEventListener('click', function(event) {
        event.preventDefault();
        event.stopPropagation();

        var filename = (bgSelect.value || '').trim();
        if (!filename) {
          showSheetToast('กรุณาเลือกรูปที่ต้องการลบ', 'error');
          return;
        }

        showDeleteConfirmation(filename, function() {
          deleteSelectedBg(filename);
        });
      });
    }

    previewContainer.addEventListener('click', function(event) {
      var applyBtn = event.target.closest('[data-use-old-bg]');
      if (!applyBtn) return;

      event.preventDefault();
      event.stopPropagation();

      var filename = (applyBtn.getAttribute('data-use-old-bg') || '').trim();
      if (!filename) return;

      applyOldBg(filename);
    });

    if (!document.__useOldBgDelegatedBound) {
      document.__useOldBgDelegatedBound = true;
      document.addEventListener('click', function(event) {
        var applyBtn = event.target.closest('[data-use-old-bg]');
        if (!applyBtn) {
          return;
        }

        event.preventDefault();
        event.stopPropagation();

        var filename = (applyBtn.getAttribute('data-use-old-bg') || '').trim();
        if (!filename) {
          return;
        }

        applyOldBg(filename);
      }, true);
    }
  }

  function bindBgUploadFallback() {
    var bgInput = document.getElementById('bgInput');
    if (!bgInput || bgInput.__bgFallbackBound) {
      return;
    }

    var uploadArea = document.querySelector('#sheet-background .apple-upload-area');
    if (uploadArea && !uploadArea.__bgAreaBound) {
      uploadArea.__bgAreaBound = true;
      uploadArea.dataset.bgAreaBound = '1';
      uploadArea.addEventListener('click', function(event) {
        if (event.target === bgInput || event.target.closest('#bgInput')) {
          return;
        }

        bgInput.click();
      });
    }

    bgInput.__bgFallbackBound = true;
    bgInput.dataset.bgFallbackBound = '1';

    var hasPrimaryBgHandler = bgInput.dataset.appleUploadBound === '1';
    if (!bgInput.__bgChangeFallbackBound && !hasPrimaryBgHandler) {
      bgInput.__bgChangeFallbackBound = true;
      bgInput.addEventListener('change', function() {
        window.__uploadBgFromInput(bgInput);
      });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
      window.appleSheetComponent.init();
      bindOldLogoSelectFallback();
      bindBgSelectFallback();
      setTimeout(bindLogoUploadFallback, 200);
      setTimeout(bindBgUploadFallback, 200);
    });
  } else {
    window.appleSheetComponent.init();
    bindOldLogoSelectFallback();
    bindBgSelectFallback();
    setTimeout(bindLogoUploadFallback, 200);
    setTimeout(bindBgUploadFallback, 200);
  }
})();
</script>

<!-- Sheet: Background -->
<div class="apple-sheet-overlay" id="sheet-background">
  <div class="apple-sheet">
    <div class="apple-sheet-handle"></div>
    <div class="apple-sheet-header">
      <button type="button" class="apple-sheet-action" data-close-sheet="sheet-background"><?php echo __('cancel'); ?></button>
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
        <div style="display: flex; gap: 8px; margin-top: 8px;">
          <select id="bgSelect" class="apple-input" style="flex: 1;">
            <option value="">-- <?php echo __('select_existing'); ?> --</option>
            <?php foreach ($imageFiles as $file): ?>
              <option value="<?php echo htmlspecialchars($file); ?>" <?php echo ($file === $bgFilename) ? 'selected' : ''; ?>><?php echo htmlspecialchars($file); ?></option>
            <?php endforeach; ?>
          </select>
          <button type="button" id="deleteBgBtn" class="apple-btn" style="background: rgba(239, 68, 68, 0.15); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3); padding: 10px 16px; border-radius: 8px; cursor: pointer; white-space: nowrap; opacity: 0.5; pointer-events: none;" disabled>ลบรูป</button>
        </div>
        <div id="bgSelectPreview" style="margin-top: 12px;"></div>
      </div>
      
      <!-- Upload new -->
      <div class="apple-input-group">
        <label class="apple-input-label"><?php echo __('upload_new'); ?></label>
        <div class="apple-upload-area">
          <div class="apple-upload-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="32" height="32"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg></div>
          <p class="apple-upload-text"><?php echo __('click_to_select'); ?></p>
          <p class="apple-upload-hint">รองรับ JPG, PNG, WebP</p>
          <input type="file" id="bgInput" name="bg" accept="image/jpeg,image/png,image/webp" style="display:block !important; position:absolute; inset:0; width:100%; height:100%; opacity:0; cursor:pointer; pointer-events:auto; z-index:3;">
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
      <button type="button" class="apple-sheet-action" data-close-sheet="sheet-signature"><?php echo __('cancel'); ?></button>
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
        <input type="file" id="signatureInput" accept="image/png" style="display: none;">
        
        <div class="apple-upload-area" id="signatureUploadArea" 
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
      function showSignatureMessage(message, type) {
        var settingsInstance = null;
        if (window.appleSettings && typeof window.appleSettings.showToast === 'function') {
          settingsInstance = window.appleSettings;
        } else if (typeof appleSettings !== 'undefined' && appleSettings && typeof appleSettings.showToast === 'function') {
          settingsInstance = appleSettings;
        }

        if (settingsInstance) {
          settingsInstance.showToast(message, type || 'info');
          return;
        }

        var existingToast = document.querySelector('.apple-toast');
        if (existingToast && existingToast.parentNode) {
          existingToast.parentNode.removeChild(existingToast);
        }

        var toast = document.createElement('div');
        toast.className = 'apple-toast';
        toast.textContent = message;
        document.body.appendChild(toast);

        requestAnimationFrame(function() {
          toast.classList.add('show');
        });

        setTimeout(function() {
          toast.classList.remove('show');
          setTimeout(function() {
            if (toast.parentNode) {
              toast.parentNode.removeChild(toast);
            }
          }, 300);
        }, 2000);
      }

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
          showSignatureMessage('กรุณาเลือกไฟล์ PNG เท่านั้น', 'error');
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
            showSignatureMessage('อัพโหลดลายเซ็นสำเร็จ', 'success');
            // Reload page after short delay
            setTimeout(() => location.reload(), 1000);
          } else {
            throw new Error(result.error || 'เกิดข้อผิดพลาด');
          }
        })
        .catch(error => {
          console.error('Upload error:', error);
          showSignatureMessage('เกิดข้อผิดพลาด: ' + error.message, 'error');
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
