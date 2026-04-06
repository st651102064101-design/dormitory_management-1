<!-- Section: Appearance Settings -->
<div class="apple-section-group">
  <h2 class="apple-section-title"><?php echo __('settings_appearance'); ?></h2>
  <div class="apple-section-card">
    <!-- Default View Mode -->
    <div class="apple-settings-row" data-sheet="sheet-default-view">
      <div class="apple-row-icon cyan"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-animated"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg></div>
      <div class="apple-row-content">
        <p class="apple-row-label"><?php echo __('default_view_mode'); ?></p>
        <p class="apple-row-sublabel"><?php echo __('default_view_mode_desc'); ?></p>
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
        <p class="apple-row-label"><?php echo __('public_theme'); ?></p>
        <p class="apple-row-sublabel"><?php echo __('public_theme_desc'); ?></p>
      </div>
      <span class="apple-row-value"><?php 
        $themeNames = ['dark' => __('theme_dark'), 'light' => __('theme_light'), 'auto' => __('theme_auto')];
        echo $themeNames[$publicTheme] ?? __('theme_dark');
      ?></span>
      <span class="apple-row-chevron">›</span>
    </div>
    
    <!-- Use Background Image -->
    <div class="apple-settings-row" id="bgImageToggleRow">
      <div class="apple-row-icon blue"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-animated"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg></div>
      <div class="apple-row-content">
        <p class="apple-row-label"><?php echo __('use_bg_image'); ?></p>
        <p class="apple-row-sublabel"><?php echo __('use_bg_image_desc'); ?></p>
      </div>
      <div class="apple-toggle" id="bgImageToggle" data-setting="use_bg_image" data-value="<?php echo htmlspecialchars($useBgImage); ?>"></div>
    </div>
    <script>
    (function bindInlineBgImageToggle() {
      const toggle = document.getElementById('bgImageToggle');
      const row = document.getElementById('bgImageToggleRow');
      if (!toggle || !row || toggle.dataset.inlineBgToggleBound === '1') {
        return;
      }

      const notify = (message, type) => {
        if (window.appleSettings && typeof window.appleSettings.showToast === 'function') {
          window.appleSettings.showToast(message, type);
          return;
        }

        if (typeof window.showToast === 'function') {
          window.showToast(message, type);
        }
      };

      const setState = (isActive) => {
        toggle.classList.toggle('active', isActive);
        toggle.setAttribute('data-value', isActive ? '1' : '0');
        toggle.setAttribute('aria-checked', isActive ? 'true' : 'false');
      };

      const saveToggle = async (nextValue, prevActive) => {
        if (toggle.dataset.saving === '1') {
          return;
        }

        toggle.dataset.saving = '1';

        try {
          const response = await fetch('/dormitory_management/Manage/save_system_settings.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `use_bg_image=${encodeURIComponent(nextValue)}`
          });

          let result = null;
          try {
            result = await response.json();
          } catch (parseError) {
            throw new Error('ระบบตอบกลับไม่ถูกต้อง');
          }

          if (!response.ok || !result || !result.success) {
            throw new Error((result && (result.error || result.message)) || 'เกิดข้อผิดพลาด');
          }

          notify(nextValue === '1' ? 'เปิดใช้ภาพพื้นหลัง' : 'ปิดใช้ภาพพื้นหลัง', 'success');
        } catch (error) {
          setState(prevActive);
          notify(error.message || 'เกิดข้อผิดพลาด', 'error');
        } finally {
          toggle.dataset.saving = '0';
        }
      };

      const runToggle = () => {
        if (toggle.dataset.saving === '1') {
          return;
        }

        const prevActive = toggle.classList.contains('active');
        const nextActive = !prevActive;
        const nextValue = nextActive ? '1' : '0';
        setState(nextActive);
        saveToggle(nextValue, prevActive);
      };

      toggle.setAttribute('role', 'switch');
      toggle.setAttribute('tabindex', '0');
      setState(toggle.getAttribute('data-value') === '1');

      toggle.addEventListener('click', (event) => {
        event.preventDefault();
        event.stopPropagation();
        if (typeof event.stopImmediatePropagation === 'function') {
          event.stopImmediatePropagation();
        }
        runToggle();
      }, true);

      toggle.addEventListener('keydown', (event) => {
        if (event.key !== ' ' && event.key !== 'Enter') {
          return;
        }

        event.preventDefault();
        event.stopPropagation();
        if (typeof event.stopImmediatePropagation === 'function') {
          event.stopImmediatePropagation();
        }
        runToggle();
      }, true);

      row.addEventListener('click', (event) => {
        if (event.target.closest('a, button, input, select, textarea, [data-close-sheet]')) {
          return;
        }

        event.preventDefault();
        event.stopPropagation();
        if (typeof event.stopImmediatePropagation === 'function') {
          event.stopImmediatePropagation();
        }
        runToggle();
      }, true);

      toggle.dataset.inlineBgToggleBound = '1';
    })();
    </script>
    
    <!-- System Theme Color -->
    <div class="apple-settings-row" data-sheet="sheet-theme-color">
      <div class="apple-row-icon pink"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-animated"><path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"/></svg></div>
      <div class="apple-row-content">
        <p class="apple-row-label"><?php echo __('theme_color_label'); ?></p>
      </div>
      <div id="themeColorSwatch" style="width: 24px; height: 24px; border-radius: 6px; background: <?php echo htmlspecialchars($themeColor); ?>; border: 2px solid rgba(0,0,0,0.1); margin-right: 8px;"></div>
      <span class="apple-row-chevron">›</span>
    </div>
    
    <!-- Font Size -->
    <div class="apple-settings-row" data-sheet="sheet-font-size">
      <div class="apple-row-icon gray"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-animated"><polyline points="4 7 4 4 20 4 20 7"/><line x1="9" y1="20" x2="15" y2="20"/><line x1="12" y1="4" x2="12" y2="20"/></svg></div>
      <div class="apple-row-content">
        <p class="apple-row-label"><?php echo __('font_size'); ?></p>
      </div>
      <span class="apple-row-value"><?php 
        $fontSizeNames = ['0.9' => __('font_small'), '1' => __('font_normal'), '1.1' => __('font_large'), '1.25' => __('font_xlarge')];
        echo $fontSizeNames[$fontSize] ?? __('font_normal');
      ?></span>
      <span class="apple-row-chevron">›</span>
    </div>
    
    <!-- FPS Threshold -->
    <div class="apple-settings-row" data-sheet="sheet-fps-threshold">
      <div class="apple-row-icon orange"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-animated"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg></div>
      <div class="apple-row-content">
        <p class="apple-row-label">FPS Threshold</p>
        <p class="apple-row-sublabel"><?php echo __('fps_threshold_desc'); ?></p>
      </div>
      <span class="apple-row-value"><?php echo htmlspecialchars($fpsThreshold ?? '60'); ?> FPS</span>
      <span class="apple-row-chevron">›</span>
    </div>
    
    <!-- System Language -->
    <div class="apple-settings-row" data-sheet="sheet-language">
      <div class="apple-row-icon teal"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-animated"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg></div>
      <div class="apple-row-content">
        <p class="apple-row-label">ภาษา / Language</p>
        <p class="apple-row-sublabel">เปลี่ยนภาษาทั้งระบบ</p>
      </div>
      <span class="apple-row-value"><?php 
        $langNames = ['th' => '🇹🇭 ไทย', 'en' => '🇺🇸 English'];
        echo $langNames[$systemLanguage ?? 'th'] ?? '🇹🇭 ไทย';
      ?></span>
      <span class="apple-row-chevron">›</span>
    </div>
  </div>
</div>

<!-- Sheet: Public Theme -->
<div class="apple-sheet-overlay" id="sheet-public-theme">
  <div class="apple-sheet">
    <div class="apple-sheet-handle"></div>
    <div class="apple-sheet-header">
      <button type="button" class="apple-sheet-action" data-close-sheet="sheet-public-theme">เสร็จ</button>
      <h3 class="apple-sheet-title">ธีมหน้าสาธารณะ</h3>
      <div style="width: 50px;"></div>
    </div>
    <div class="apple-sheet-body">
      <p style="font-size: 13px; color: var(--apple-text-secondary); margin-bottom: 16px;">
        <?php echo __('public_theme_hint'); ?>
      </p>
      <div class="apple-theme-grid">
        <div class="apple-theme-option <?php echo $publicTheme === 'dark' ? 'active' : ''; ?>" data-theme="dark">
          <div class="apple-theme-preview dark"></div>
          <span class="apple-theme-name"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;vertical-align:-2px;margin-right:3px;"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg><?php echo __('theme_dark'); ?></span>
        </div>
        <div class="apple-theme-option <?php echo $publicTheme === 'light' ? 'active' : ''; ?>" data-theme="light">
          <div class="apple-theme-preview light"></div>
          <span class="apple-theme-name"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;vertical-align:-2px;margin-right:3px;"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg><?php echo __('theme_light'); ?></span>
        </div>
        <div class="apple-theme-option <?php echo $publicTheme === 'auto' ? 'active' : ''; ?>" data-theme="auto">
          <div class="apple-theme-preview auto"></div>
          <span class="apple-theme-name"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;vertical-align:-2px;margin-right:3px;"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg><?php echo __('theme_auto'); ?></span>
        </div>
      </div>
      <script>
      (function bindInlinePublicThemeSave() {
        const sheet = document.getElementById('sheet-public-theme');
        if (!sheet || sheet.dataset.inlinePublicThemeBound === '1') {
          return;
        }

        const options = sheet.querySelectorAll('.apple-theme-option[data-theme]');
        if (!options.length) {
          return;
        }

        const initialTheme = <?php echo json_encode(in_array($publicTheme, ['dark', 'light', 'auto'], true) ? $publicTheme : 'dark'); ?>;
        const themeLabelMap = {
          dark: <?php echo json_encode(__('theme_dark'), JSON_UNESCAPED_UNICODE); ?>,
          light: <?php echo json_encode(__('theme_light'), JSON_UNESCAPED_UNICODE); ?>,
          auto: <?php echo json_encode(__('theme_auto'), JSON_UNESCAPED_UNICODE); ?>
        };

        const notify = (message, type) => {
          if (window.appleSettings && typeof window.appleSettings.showToast === 'function') {
            window.appleSettings.showToast(message, type);
            return;
          }

          if (typeof window.showToast === 'function') {
            window.showToast(message, type);
          }
        };

        const setActiveTheme = (theme) => {
          options.forEach((opt) => {
            opt.classList.toggle('active', opt.dataset.theme === theme);
          });
        };

        const updateRowDisplay = (theme) => {
          const displayEl = document.querySelector('[data-sheet="sheet-public-theme"] .apple-row-value');
          if (displayEl) {
            displayEl.textContent = themeLabelMap[theme] || theme;
          }
        };

        const saveTheme = async (theme, previousTheme) => {
          if (!['dark', 'light', 'auto'].includes(theme)) {
            return;
          }

          if (sheet.dataset.saving === '1') {
            return;
          }

          sheet.dataset.saving = '1';
          setActiveTheme(theme);
          updateRowDisplay(theme);

          try {
            const response = await fetch('/dormitory_management/Manage/save_public_theme.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
              body: `theme=${encodeURIComponent(theme)}`
            });

            let result = null;
            try {
              result = await response.json();
            } catch (parseError) {
              throw new Error('ระบบตอบกลับไม่ถูกต้อง');
            }

            if (!response.ok || !result || !result.success) {
              throw new Error((result && (result.error || result.message)) || 'เกิดข้อผิดพลาด');
            }

            notify(result.message || 'บันทึกธีมสำเร็จ', 'success');
          } catch (error) {
            setActiveTheme(previousTheme);
            updateRowDisplay(previousTheme);
            notify(error.message || 'เกิดข้อผิดพลาด', 'error');
          } finally {
            sheet.dataset.saving = '0';
          }
        };

        options.forEach((option) => {
          option.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            if (typeof event.stopImmediatePropagation === 'function') {
              event.stopImmediatePropagation();
            }

            const nextTheme = option.dataset.theme;
            const activeOption = sheet.querySelector('.apple-theme-option.active');
            const previousTheme = activeOption?.dataset.theme || initialTheme;
            if (nextTheme === previousTheme) {
              return;
            }

            saveTheme(nextTheme, previousTheme);
          }, true);
        });

        sheet.dataset.inlinePublicThemeBound = '1';
      })();
      </script>
    </div>
  </div>
</div>

<!-- Sheet: Theme Color -->
<div class="apple-sheet-overlay" id="sheet-theme-color">
  <div class="apple-sheet">
    <div class="apple-sheet-handle"></div>
    <div class="apple-sheet-header">
      <button type="button" class="apple-sheet-action" data-close-sheet="sheet-theme-color">เสร็จ</button>
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
      <script>
      (function bindInlineThemeColorSave() {
        const sheet = document.getElementById('sheet-theme-color');
        if (!sheet || sheet.dataset.inlineThemeColorBound === '1') {
          return;
        }

        const options = sheet.querySelectorAll('.apple-color-option[data-color]');
        const colorInput = sheet.querySelector('#themeColor');
        const hexDisplay = sheet.querySelector('#colorHexDisplay');
        const swatch = document.getElementById('themeColorSwatch');
        if (!colorInput || !hexDisplay) {
          return;
        }

        const initialColor = <?php echo json_encode($themeColor); ?>;
        const fallbackColor = '#0f172a';
        const isValidHex = (value) => /^#[0-9a-fA-F]{6}$/.test(String(value || ''));
        let savedColor = isValidHex(initialColor) ? initialColor : fallbackColor;

        const notify = (message, type) => {
          if (window.appleSettings && typeof window.appleSettings.showToast === 'function') {
            window.appleSettings.showToast(message, type);
            return;
          }

          if (typeof window.showToast === 'function') {
            window.showToast(message, type);
          }
        };

        const isLightColor = (hexColor) => {
          const hex = String(hexColor || '').replace('#', '');
          if (hex.length !== 6) {
            return false;
          }

          const r = parseInt(hex.substring(0, 2), 16);
          const g = parseInt(hex.substring(2, 4), 16);
          const b = parseInt(hex.substring(4, 6), 16);
          const luminance = (0.299 * r + 0.587 * g + 0.114 * b) / 255;
          return luminance > 0.5;
        };

        const applyPreview = (color) => {
          colorInput.value = color;
          hexDisplay.textContent = color;

          options.forEach((opt) => {
            opt.classList.toggle('active', (opt.dataset.color || '').toLowerCase() === color.toLowerCase());
          });

          if (swatch) {
            swatch.style.background = color;
          }

          document.body.setAttribute('data-theme-color', color);
          document.documentElement.style.setProperty('--theme-bg-color', color);

          if (isLightColor(color)) {
            document.body.classList.add('live-light');
            document.body.classList.remove('live-dark');
          } else {
            document.body.classList.remove('live-light');
            document.body.classList.add('live-dark');
          }
        };

        const saveColor = async (nextColor) => {
          if (!isValidHex(nextColor)) {
            notify('รูปแบบสีไม่ถูกต้อง', 'error');
            return;
          }

          if (sheet.dataset.saving === '1') {
            return;
          }

          const previousColor = savedColor;
          sheet.dataset.saving = '1';
          applyPreview(nextColor);

          try {
            const response = await fetch('/dormitory_management/Manage/save_system_settings.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
              body: `theme_color=${encodeURIComponent(nextColor)}`
            });

            let result = null;
            try {
              result = await response.json();
            } catch (parseError) {
              throw new Error('ระบบตอบกลับไม่ถูกต้อง');
            }

            if (!response.ok || !result || !result.success) {
              throw new Error((result && (result.error || result.message)) || 'เกิดข้อผิดพลาด');
            }

            savedColor = nextColor;
            notify(result.message || 'บันทึกสีพื้นหลังสำเร็จ', 'success');
          } catch (error) {
            applyPreview(previousColor);
            notify(error.message || 'เกิดข้อผิดพลาด', 'error');
          } finally {
            sheet.dataset.saving = '0';
          }
        };

        options.forEach((option) => {
          option.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            if (typeof event.stopImmediatePropagation === 'function') {
              event.stopImmediatePropagation();
            }

            const nextColor = option.dataset.color || '';
            if (!isValidHex(nextColor) || nextColor.toLowerCase() === savedColor.toLowerCase()) {
              return;
            }

            saveColor(nextColor);
          }, true);
        });

        colorInput.addEventListener('change', (event) => {
          event.preventDefault();
          event.stopPropagation();
          if (typeof event.stopImmediatePropagation === 'function') {
            event.stopImmediatePropagation();
          }

          const nextColor = (event.target && event.target.value) ? event.target.value : '';
          if (!isValidHex(nextColor) || nextColor.toLowerCase() === savedColor.toLowerCase()) {
            applyPreview(savedColor);
            return;
          }

          saveColor(nextColor);
        }, true);

        colorInput.addEventListener('input', (event) => {
          const color = (event.target && event.target.value) ? event.target.value : '';
          if (!isValidHex(color)) {
            return;
          }

          hexDisplay.textContent = color;
        }, true);

        sheet.dataset.inlineThemeColorBound = '1';
      })();
      </script>
    </div>
  </div>
</div>

<!-- Sheet: Font Size -->
<div class="apple-sheet-overlay" id="sheet-font-size">
  <div class="apple-sheet">
    <div class="apple-sheet-handle"></div>
    <div class="apple-sheet-header">
      <button type="button" class="apple-sheet-action" data-close-sheet="sheet-font-size">เสร็จ</button>
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
      <script>
      (function bindInlineFontSizeSave() {
        const sheet = document.getElementById('sheet-font-size');
        if (!sheet || sheet.dataset.inlineFontSizeBound === '1') {
          return;
        }

        const select = sheet.querySelector('#fontSize');
        const preview = sheet.querySelector('.font-size-preview');
        if (!select) {
          return;
        }

        const labelMap = {
          '0.9': <?php echo json_encode(__('font_small'), JSON_UNESCAPED_UNICODE); ?>,
          '1': <?php echo json_encode(__('font_normal'), JSON_UNESCAPED_UNICODE); ?>,
          '1.1': <?php echo json_encode(__('font_large'), JSON_UNESCAPED_UNICODE); ?>,
          '1.25': <?php echo json_encode(__('font_xlarge'), JSON_UNESCAPED_UNICODE); ?>
        };

        const notify = (message, type) => {
          if (window.appleSettings && typeof window.appleSettings.showToast === 'function') {
            window.appleSettings.showToast(message, type);
            return;
          }

          if (typeof window.showToast === 'function') {
            window.showToast(message, type);
          }
        };

        const isAllowed = (value) => Object.prototype.hasOwnProperty.call(labelMap, value);

        const applyPreview = (value) => {
          if (!isAllowed(value)) {
            return;
          }

          select.value = value;

          if (preview) {
            preview.style.fontSize = `calc(1rem * ${value})`;
          }

          document.documentElement.style.setProperty('--font-scale', value);
          document.documentElement.style.setProperty('--admin-font-scale', value);

          const displayEl = document.querySelector('[data-sheet="sheet-font-size"] .apple-row-value');
          if (displayEl) {
            displayEl.textContent = labelMap[value] || value;
          }
        };

        let savedValue = isAllowed(select.value) ? select.value : <?php echo json_encode(in_array($fontSize, ['0.9', '1', '1.1', '1.25'], true) ? $fontSize : '1'); ?>;
        applyPreview(savedValue);

        const saveFontSize = async (nextValue) => {
          if (!isAllowed(nextValue)) {
            notify('ขนาดข้อความไม่ถูกต้อง', 'error');
            return;
          }

          if (sheet.dataset.saving === '1') {
            return;
          }

          const previousValue = savedValue;
          sheet.dataset.saving = '1';
          applyPreview(nextValue);

          try {
            const response = await fetch('/dormitory_management/Manage/save_system_settings.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
              body: `font_size=${encodeURIComponent(nextValue)}`
            });

            let result = null;
            try {
              result = await response.json();
            } catch (parseError) {
              throw new Error('ระบบตอบกลับไม่ถูกต้อง');
            }

            if (!response.ok || !result || !result.success) {
              throw new Error((result && (result.error || result.message)) || 'เกิดข้อผิดพลาด');
            }

            savedValue = nextValue;
            try {
              localStorage.setItem('adminFontScale', nextValue);
            } catch (storageError) {
              // Ignore storage errors.
            }

            notify(result.message || 'บันทึกขนาดตัวอักษรสำเร็จ', 'success');
          } catch (error) {
            applyPreview(previousValue);
            notify(error.message || 'เกิดข้อผิดพลาด', 'error');
          } finally {
            sheet.dataset.saving = '0';
          }
        };

        select.addEventListener('change', (event) => {
          event.preventDefault();
          event.stopPropagation();
          if (typeof event.stopImmediatePropagation === 'function') {
            event.stopImmediatePropagation();
          }

          const nextValue = select.value;
          if (nextValue === savedValue) {
            return;
          }

          saveFontSize(nextValue);
        }, true);

        sheet.dataset.inlineFontSizeBound = '1';
      })();
      </script>
    </div>
  </div>
</div>

<!-- Sheet: Default View Mode -->
<div class="apple-sheet-overlay" id="sheet-default-view">
  <div class="apple-sheet">
    <div class="apple-sheet-handle"></div>
    <div class="apple-sheet-header">
      <button type="button" class="apple-sheet-action" data-close-sheet="sheet-default-view">เสร็จ</button>
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
      <script>
      (function bindInlineDefaultViewModeSave() {
        const sheet = document.getElementById('sheet-default-view');
        if (!sheet || sheet.dataset.inlineDefaultViewBound === '1') {
          return;
        }

        const options = sheet.querySelectorAll('.apple-view-option[data-view]');
        if (!options.length) {
          return;
        }

        const notify = (message, type) => {
          if (window.appleSettings && typeof window.appleSettings.showToast === 'function') {
            window.appleSettings.showToast(message, type);
            return;
          }

          if (typeof window.showToast === 'function') {
            window.showToast(message, type);
          }
        };

        const setActiveView = (viewMode) => {
          options.forEach((opt) => {
            opt.classList.toggle('active', opt.dataset.view === viewMode);
          });

          const displayEl = document.querySelector('[data-sheet="sheet-default-view"] .apple-row-value');
          if (displayEl) {
            displayEl.textContent = viewMode === 'grid' ? 'Grid' : 'List';
          }
        };

        const closeSheetNow = () => {
          if (!sheet.classList.contains('active')) {
            return;
          }

          sheet.classList.remove('active');
          if (!document.querySelector('.apple-sheet-overlay.active')) {
            document.body.style.overflow = '';
          }
        };

        const saveViewMode = async (viewMode, previousMode) => {
          if (!['grid', 'list'].includes(viewMode)) {
            return;
          }

          if (sheet.dataset.saving === '1') {
            return;
          }

          sheet.dataset.saving = '1';
          setActiveView(viewMode);

          try {
            const response = await fetch('/dormitory_management/Manage/save_system_settings.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
              body: `default_view_mode=${encodeURIComponent(viewMode)}`
            });

            let result = null;
            try {
              result = await response.json();
            } catch (parseError) {
              throw new Error('ระบบตอบกลับไม่ถูกต้อง');
            }

            if (!response.ok || !result || !result.success) {
              throw new Error((result && result.error) || 'เกิดข้อผิดพลาด');
            }

            try {
              localStorage.setItem('adminDefaultViewMode', viewMode);
            } catch (storageError) {
              // Ignore storage errors.
            }

            notify('บันทึกรูปแบบการแสดงผลสำเร็จ', 'success');
            window.setTimeout(closeSheetNow, 1000);
          } catch (error) {
            setActiveView(previousMode);
            notify(error.message || 'เกิดข้อผิดพลาด', 'error');
          } finally {
            sheet.dataset.saving = '0';
          }
        };

        options.forEach((option) => {
          option.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            if (typeof event.stopImmediatePropagation === 'function') {
              event.stopImmediatePropagation();
            }

            const nextMode = option.dataset.view;
            const activeOption = sheet.querySelector('.apple-view-option.active');
            const prevMode = activeOption?.dataset.view || '<?php echo $defaultViewMode === 'list' ? 'list' : 'grid'; ?>';

            if (nextMode === prevMode) {
              return;
            }

            if (sheet.dataset.saving === '1') {
              return;
            }

            saveViewMode(nextMode, prevMode);
          }, true);
        });

        sheet.dataset.inlineDefaultViewBound = '1';
      })();
      </script>
    </div>
  </div>
</div>

<!-- Sheet: FPS Threshold -->
<div class="apple-sheet-overlay" id="sheet-fps-threshold">
  <div class="apple-sheet">
    <div class="apple-sheet-handle"></div>
    <div class="apple-sheet-header">
      <button type="button" class="apple-sheet-action" data-close-sheet="sheet-fps-threshold">เสร็จ</button>
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
      <script>
      (function bindInlineFpsThresholdSave() {
        const sheet = document.getElementById('sheet-fps-threshold');
        if (!sheet || sheet.dataset.inlineFpsBound === '1') {
          return;
        }

        const select = sheet.querySelector('#fpsThreshold');
        if (!select) {
          return;
        }

        const allowedValues = ['30', '45', '60', '90', '120', '180', '240', '300'];
        const isAllowed = (value) => allowedValues.includes(value);

        const notify = (message, type) => {
          if (window.appleSettings && typeof window.appleSettings.showToast === 'function') {
            window.appleSettings.showToast(message, type);
            return;
          }

          if (typeof window.showToast === 'function') {
            window.showToast(message, type);
          }
        };

        const updateRowValue = (value) => {
          const rowValue = document.querySelector('[data-sheet="sheet-fps-threshold"] .apple-row-value');
          if (rowValue) {
            rowValue.textContent = `${value} FPS`;
          }
        };

        const applyValue = (value) => {
          if (!isAllowed(value)) {
            return;
          }

          select.value = value;
          updateRowValue(value);
        };

        let savedValue = isAllowed(select.value) ? select.value : <?php echo json_encode(in_array($fpsThreshold, ['30', '45', '60', '90', '120', '180', '240', '300'], true) ? $fpsThreshold : '60'); ?>;
        applyValue(savedValue);

        const saveFps = async (nextValue) => {
          if (!isAllowed(nextValue)) {
            notify('ค่า FPS ไม่ถูกต้อง', 'error');
            return;
          }

          if (sheet.dataset.saving === '1') {
            return;
          }

          const previousValue = savedValue;
          sheet.dataset.saving = '1';
          applyValue(nextValue);

          try {
            const response = await fetch('/dormitory_management/Manage/save_system_settings.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
              body: `fps_threshold=${encodeURIComponent(nextValue)}`
            });

            let result = null;
            try {
              result = await response.json();
            } catch (parseError) {
              throw new Error('ระบบตอบกลับไม่ถูกต้อง');
            }

            if (!response.ok || !result || !result.success) {
              throw new Error((result && (result.error || result.message)) || 'เกิดข้อผิดพลาด');
            }

            savedValue = nextValue;
            try {
              localStorage.setItem('fpsThreshold', nextValue);
            } catch (storageError) {
              // Ignore storage errors.
            }

            notify(result.message || 'บันทึกค่า FPS สำเร็จ', 'success');
          } catch (error) {
            applyValue(previousValue);
            notify(error.message || 'เกิดข้อผิดพลาด', 'error');
          } finally {
            sheet.dataset.saving = '0';
          }
        };

        select.addEventListener('change', (event) => {
          event.preventDefault();
          event.stopPropagation();
          if (typeof event.stopImmediatePropagation === 'function') {
            event.stopImmediatePropagation();
          }

          const nextValue = select.value;
          if (nextValue === savedValue) {
            return;
          }

          saveFps(nextValue);
        }, true);

        sheet.dataset.inlineFpsBound = '1';
      })();
      </script>
    </div>
  </div>
</div>

<!-- Sheet: Language Selection -->
<div class="apple-sheet-overlay" id="sheet-language">
  <div class="apple-sheet">
    <div class="apple-sheet-handle"></div>
    <div class="apple-sheet-header">
      <button type="button" class="apple-sheet-action" data-close-sheet="sheet-language">เสร็จ / Done</button>
      <h3 class="apple-sheet-title">ภาษา / Language</h3>
      <div style="width: 50px;"></div>
    </div>
    <div class="apple-sheet-body">
      <p style="font-size: 13px; color: var(--apple-text-secondary); margin-bottom: 16px;">
        <span id="languageDescMain">เลือกภาษาที่ใช้แสดงในระบบ</span><br>
        <span id="languageDescSub" style="font-size: 12px;">Select display language for the system</span>
      </p>
      <div class="apple-theme-grid" style="grid-template-columns: repeat(2, 1fr);">
        <div class="apple-language-option <?php echo ($systemLanguage ?? 'th') === 'th' ? 'active' : ''; ?>" data-language="th">
          <div class="apple-language-preview">
            <span style="font-size: 48px;">🇹🇭</span>
          </div>
          <span class="apple-theme-name" id="languageOptionThLabel">ไทย (Thai)</span>
        </div>
        <div class="apple-language-option <?php echo ($systemLanguage ?? 'th') === 'en' ? 'active' : ''; ?>" data-language="en">
          <div class="apple-language-preview">
            <span style="font-size: 48px;">🇺🇸</span>
          </div>
          <span class="apple-theme-name" id="languageOptionEnLabel">English</span>
        </div>
      </div>
      
      <div style="margin-top: 20px; padding: 12px; background: rgba(59, 130, 246, 0.1); border-radius: 10px; border: 1px solid rgba(59, 130, 246, 0.2);">
        <p style="font-size: 13px; color: var(--apple-text-secondary); margin: 0; display: flex; align-items: center; gap: 6px;">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
          <span id="languageInfoMain">การเปลี่ยนภาษาจะมีผลกับทุกหน้าในระบบ</span><br>
          <span id="languageInfoSub" style="font-size: 11px;">Language change will affect all pages in the system</span>
        </p>
      </div>
      <script>
      (function bindInlineLanguageSave() {
        const sheet = document.getElementById('sheet-language');
        if (!sheet || sheet.dataset.inlineLanguageBound === '1') {
          return;
        }

        const options = sheet.querySelectorAll('.apple-language-option[data-language]');
        if (!options.length) {
          return;
        }

        const allowedLanguages = ['th', 'en'];
        const languageLabels = {
          th: '🇹🇭 ไทย',
          en: '🇺🇸 English'
        };
        const languageUiText = {
          th: {
            done: 'เสร็จ',
            title: 'ภาษา',
            descMain: 'เลือกภาษาที่ใช้แสดงในระบบ',
            descSub: 'Select display language for the system',
            optionTh: 'ไทย (Thai)',
            optionEn: 'English',
            infoMain: 'การเปลี่ยนภาษาจะมีผลกับทุกหน้าในระบบ',
            infoSub: 'Language change will affect all pages in the system',
            settingsTitle: 'ตั้งค่า',
            settingsSubtitle: 'จัดการระบบหอพัก'
          },
          en: {
            done: 'Done',
            title: 'Language',
            descMain: 'Select display language for the system',
            descSub: 'Language change will affect all pages in the system',
            optionTh: 'Thai',
            optionEn: 'English',
            infoMain: 'Language change will affect all pages in the system',
            infoSub: 'Changes are applied immediately without page refresh',
            settingsTitle: 'Settings',
            settingsSubtitle: 'Manage Dormitory System'
          }
        };

        const doneBtn = sheet.querySelector('[data-close-sheet="sheet-language"]');
        const titleEl = sheet.querySelector('.apple-sheet-title');
        const descMainEl = document.getElementById('languageDescMain');
        const descSubEl = document.getElementById('languageDescSub');
        const optionThLabelEl = document.getElementById('languageOptionThLabel');
        const optionEnLabelEl = document.getElementById('languageOptionEnLabel');
        const infoMainEl = document.getElementById('languageInfoMain');
        const infoSubEl = document.getElementById('languageInfoSub');

        const notify = (message, type) => {
          if (window.appleSettings && typeof window.appleSettings.showToast === 'function') {
            window.appleSettings.showToast(message, type);
            return;
          }

          if (typeof window.showToast === 'function') {
            window.showToast(message, type);
          }
        };

        const setActiveLanguage = (language) => {
          options.forEach((opt) => {
            opt.classList.toggle('active', opt.dataset.language === language);
          });

          const displayEl = document.querySelector('[data-sheet="sheet-language"] .apple-row-value');
          if (displayEl) {
            displayEl.textContent = languageLabels[language] || language;
          }
        };

        const applyLanguageUi = (language) => {
          const ui = languageUiText[language] || languageUiText.th;

          if (doneBtn) {
            doneBtn.textContent = ui.done;
          }
          if (titleEl) {
            titleEl.textContent = ui.title;
          }
          if (descMainEl) {
            descMainEl.textContent = ui.descMain;
          }
          if (descSubEl) {
            descSubEl.textContent = ui.descSub;
          }
          if (optionThLabelEl) {
            optionThLabelEl.textContent = ui.optionTh;
          }
          if (optionEnLabelEl) {
            optionEnLabelEl.textContent = ui.optionEn;
          }
          if (infoMainEl) {
            infoMainEl.textContent = ui.infoMain;
          }
          if (infoSubEl) {
            infoSubEl.textContent = ui.infoSub;
          }

          const settingsTitleEl = document.querySelector('.apple-settings-header h1');
          if (settingsTitleEl) {
            settingsTitleEl.textContent = ui.settingsTitle;
          }

          const settingsSubtitleEl = document.querySelector('.apple-settings-header p');
          if (settingsSubtitleEl) {
            settingsSubtitleEl.textContent = ui.settingsSubtitle;
          }
        };

        const initialActive = sheet.querySelector('.apple-language-option.active')?.dataset.language;
        let savedLanguage = allowedLanguages.includes(initialActive)
          ? initialActive
          : <?php echo json_encode((($systemLanguage ?? 'th') === 'en') ? 'en' : 'th'); ?>;

        setActiveLanguage(savedLanguage);
        applyLanguageUi(savedLanguage);

        const saveLanguage = async (language) => {
          if (!allowedLanguages.includes(language)) {
            notify('ภาษาไม่ถูกต้อง', 'error');
            return;
          }

          if (sheet.dataset.saving === '1') {
            return;
          }

          const previousLanguage = savedLanguage;
          sheet.dataset.saving = '1';
          setActiveLanguage(language);
          applyLanguageUi(language);

          try {
            const response = await fetch('/dormitory_management/Manage/save_system_settings.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
              body: `system_language=${encodeURIComponent(language)}`
            });

            let result = null;
            try {
              result = await response.json();
            } catch (parseError) {
              throw new Error('ระบบตอบกลับไม่ถูกต้อง');
            }

            if (!response.ok || !result || !result.success) {
              throw new Error((result && (result.error || result.message)) || 'เกิดข้อผิดพลาด');
            }

            savedLanguage = language;

            try {
              localStorage.setItem('systemLanguage', language);
            } catch (storageError) {
              // Ignore storage errors.
            }

            document.cookie = `system_language=${language}; path=/; max-age=${365 * 24 * 60 * 60}; SameSite=Lax`;

            notify(result.message || (language === 'th' ? 'บันทึกภาษาสำเร็จ' : 'Language saved successfully'), 'success');
          } catch (error) {
            setActiveLanguage(previousLanguage);
            applyLanguageUi(previousLanguage);
            notify(error.message || 'เกิดข้อผิดพลาด', 'error');
          } finally {
            sheet.dataset.saving = '0';
          }
        };

        options.forEach((option) => {
          option.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            if (typeof event.stopImmediatePropagation === 'function') {
              event.stopImmediatePropagation();
            }

            const language = option.dataset.language;
            if (!allowedLanguages.includes(language) || language === savedLanguage) {
              return;
            }

            saveLanguage(language);
          }, true);
        });

        sheet.dataset.inlineLanguageBound = '1';
      })();
      </script>
    </div>
  </div>
</div>
