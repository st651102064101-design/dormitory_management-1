/**
 * Apple Settings JavaScript
 * จัดการ interactions สำหรับหน้า Settings แบบ Apple Style
 */

class AppleSettings {
  constructor() {
    this.init();
  }

  init() {
    this.initSheets();
    this.initToggles();
    this.initForms();
    this.initImageUploads();
    this.initThemeSelector();
    this.initColorPicker();
    this.initViewModeSelector();
    this.initLanguageSelector();
    this.initHeaderActions();
  }

  // ===== Sheet Modal Management =====
  initSheets() {
    // Open sheet when clicking on settings row
    document.querySelectorAll('[data-sheet]').forEach(row => {
      row.addEventListener('click', (e) => {
        e.preventDefault();
        const sheetId = row.dataset.sheet;
        this.openSheet(sheetId);
      });
    });

    // Close sheet when clicking overlay
    document.querySelectorAll('.apple-sheet-overlay').forEach(overlay => {
      overlay.addEventListener('click', (e) => {
        if (e.target === overlay) {
          this.closeSheet(overlay.id);
        }
      });
    });

    // Close button
    document.querySelectorAll('[data-close-sheet]').forEach(btn => {
      btn.addEventListener('click', () => {
        const sheetId = btn.dataset.closeSheet;
        this.closeSheet(sheetId);
      });
    });

    // Handle swipe down to close
    document.querySelectorAll('.apple-sheet').forEach(sheet => {
      let startY = 0;
      let currentY = 0;

      sheet.addEventListener('touchstart', (e) => {
        if (sheet.scrollTop === 0) {
          startY = e.touches[0].clientY;
        }
      });

      sheet.addEventListener('touchmove', (e) => {
        if (sheet.scrollTop === 0) {
          currentY = e.touches[0].clientY;
          const diff = currentY - startY;
          if (diff > 0) {
            sheet.style.transform = `translateY(${diff}px)`;
          }
        }
      });

      sheet.addEventListener('touchend', () => {
        const diff = currentY - startY;
        if (diff > 100) {
          const overlay = sheet.closest('.apple-sheet-overlay');
          if (overlay) {
            this.closeSheet(overlay.id);
          }
        }
        sheet.style.transform = '';
        startY = 0;
        currentY = 0;
      });
    });
  }

  openSheet(sheetId) {
    const overlay = document.getElementById(sheetId);
    if (overlay) {
      overlay.classList.add('active');
      document.body.style.overflow = 'hidden';
    }
  }

  closeSheet(sheetId) {
    const overlay = document.getElementById(sheetId);
    if (overlay) {
      overlay.classList.remove('active');
      document.body.style.overflow = '';
    }
  }

  initHeaderActions() {
    document.querySelectorAll('[data-settings-action]').forEach((button) => {
      button.addEventListener('click', async (event) => {
        event.preventDefault();
        const action = button.dataset.settingsAction;
        const sheetId = button.dataset.sheetTarget;
        const focusId = button.dataset.sheetFocus;

        if (action === 'delete-signature') {
          const deleteSignatureBtn = document.getElementById('deleteSignatureBtn');
          if (!deleteSignatureBtn) {
            this.showToast('ยังไม่มีลายเซ็นให้ลบ', 'error');
            return;
          }

          await this.deleteSignature();
          return;
        }

        if (sheetId) {
          this.openSheet(sheetId);
          if (focusId) {
            window.setTimeout(() => {
              const target = document.getElementById(focusId);
              if (target) {
                target.focus();
                if (typeof target.select === 'function') {
                  target.select();
                }
              }
            }, 180);
          }
        }
      });
    });
  }

  // ===== Toggle Switches =====
  initToggles() {
    document.querySelectorAll('.apple-toggle').forEach(toggle => {
      // Restore state from data-value attribute
      const value = toggle.getAttribute('data-value');
      if (value === '1') {
        toggle.classList.add('active');
      }
      
      toggle.addEventListener('click', () => {
        toggle.classList.toggle('active');
        
        // Handle background image toggle
        if (toggle.id === 'bgImageToggle') {
          const isChecked = toggle.classList.contains('active') ? '1' : '0';
          this.saveBgImageToggle(isChecked);
        }
      });
    });
  }

  async saveBgImageToggle(value) {
    try {
      const response = await fetch('../Manage/save_system_settings.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `use_bg_image=${encodeURIComponent(value)}`
      });

      const result = await response.json();
      if (result.success) {
        this.showToast(value === '1' ? 'เปิดใช้ภาพพื้นหลัง' : 'ปิดใช้ภาพพื้นหลัง', 'success');
      } else {
        throw new Error(result.error || 'เกิดข้อผิดพลาด');
      }
    } catch (error) {
      this.showToast(error.message, 'error');
    }
  }

  // ===== Form Handling =====
  initForms() {
    // Site Name Form
    const siteNameForm = document.getElementById('siteNameForm');
    if (siteNameForm) {
      siteNameForm.addEventListener('submit', (e) => {
        e.preventDefault();
        this.saveSiteName();
      });

      // Fallback: some layouts can interfere with submit bubbling; bind button click directly too.
      const saveSiteNameBtn = document.getElementById('saveSiteNameBtn');
      if (saveSiteNameBtn && !saveSiteNameBtn.dataset.boundClick) {
        saveSiteNameBtn.dataset.boundClick = '1';
        saveSiteNameBtn.addEventListener('click', (e) => {
          e.preventDefault();
          this.saveSiteName();
        });
      }
    }

    // Phone Form
    const phoneForm = document.getElementById('phoneForm');
    if (phoneForm) {
      phoneForm.addEventListener('submit', (e) => {
        e.preventDefault();
        this.savePhone();
      });

      // Fallback click binding in case submit flow is interrupted by overlay handlers.
      const savePhoneBtn = document.getElementById('savePhoneBtn');
      if (savePhoneBtn && !savePhoneBtn.dataset.boundClick) {
        savePhoneBtn.dataset.boundClick = '1';
        savePhoneBtn.addEventListener('click', (e) => {
          e.preventDefault();
          this.savePhone();
        });
      }
    }

    // Email Form
    const emailForm = document.getElementById('emailForm');
    if (emailForm) {
      emailForm.addEventListener('submit', (e) => {
        e.preventDefault();
        this.saveEmail();
      });

      // Fallback click binding in case submit flow is interrupted by overlay handlers.
      const saveEmailBtn = document.getElementById('saveEmailBtn');
      if (saveEmailBtn && !saveEmailBtn.dataset.boundClick) {
        saveEmailBtn.dataset.boundClick = '1';
        saveEmailBtn.addEventListener('click', (e) => {
          e.preventDefault();
          this.saveEmail();
        });
      }
    }

    // Bank Name Form
    const bankNameForm = document.getElementById('bankNameForm');
    if (bankNameForm) {
      bankNameForm.addEventListener('submit', (e) => {
        e.preventDefault();
        this.saveBankName();
      });

      // Fallback click binding in case submit flow is interrupted by overlay handlers.
      const saveBankNameBtn = document.getElementById('saveBankNameBtn');
      if (saveBankNameBtn && !saveBankNameBtn.dataset.boundClick) {
        saveBankNameBtn.dataset.boundClick = '1';
        saveBankNameBtn.addEventListener('click', (e) => {
          e.preventDefault();
          this.saveBankName();
        });
      }
    }

    // Bank Account Name Form
    const bankAccountNameForm = document.getElementById('bankAccountNameForm');
    if (bankAccountNameForm) {
      bankAccountNameForm.addEventListener('submit', (e) => {
        e.preventDefault();
        this.saveBankAccountName();
      });

      // Fallback click binding in case submit flow is interrupted by overlay handlers.
      const saveBankAccountNameBtn = document.getElementById('saveBankAccountNameBtn');
      if (saveBankAccountNameBtn && !saveBankAccountNameBtn.dataset.boundClick) {
        saveBankAccountNameBtn.dataset.boundClick = '1';
        saveBankAccountNameBtn.addEventListener('click', (e) => {
          e.preventDefault();
          this.saveBankAccountName();
        });
      }
    }

    // Bank Account Number Form
    const bankAccountNumberForm = document.getElementById('bankAccountNumberForm');
    if (bankAccountNumberForm) {
      bankAccountNumberForm.addEventListener('submit', (e) => {
        e.preventDefault();
        this.saveBankAccountNumber();
      });
    }

    // PromptPay Form
    const promptpayForm = document.getElementById('promptpayForm');
    if (promptpayForm) {
      promptpayForm.addEventListener('submit', (e) => {
        e.preventDefault();
        this.savePromptpay();
      });
    }

    // Font Size Change
    const fontSizeSelect = document.getElementById('fontSize');
    if (fontSizeSelect) {
      fontSizeSelect.addEventListener('change', () => {
        this.saveFontSize();
      });
    }

    // FPS Threshold Change
    const fpsThresholdSelect = document.getElementById('fpsThreshold');
    if (fpsThresholdSelect) {
      fpsThresholdSelect.addEventListener('change', () => {
        this.saveFpsThreshold();
      });
    }

    const quickActionsForm = document.getElementById('quickActionsForm');
    if (quickActionsForm) {
      quickActionsForm.addEventListener('submit', (e) => {
        e.preventDefault();
        this.saveQuickActions();
      });
    }

    // Session Timeout Form
    const sessionTimeoutForm = document.getElementById('sessionTimeoutForm');
    if (sessionTimeoutForm) {
      sessionTimeoutForm.addEventListener('submit', (e) => {
        e.preventDefault();
        this.saveSessionTimeout();
      });
    }
  }

  async saveSiteName() {
    if (this.isSavingSiteName) {
      return;
    }

    const siteName = document.getElementById('siteName')?.value.trim();
    if (!siteName) {
      this.showToast('กรุณากรอกชื่อหอพัก', 'error');
      return;
    }

    this.isSavingSiteName = true;

    try {
      const response = await fetch('/dormitory_management/Manage/save_system_settings.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `site_name=${encodeURIComponent(siteName)}`
      });

      const result = await response.json();
      if (result.success) {
        this.showToast('บันทึกชื่อหอพักสำเร็จ', 'success');
        this.closeSheet('sheet-sitename');

        // Update display in settings row
        const displayEl = document.querySelector('[data-display="sitename"]');
        if (displayEl) displayEl.textContent = siteName;

        // Update profile header card
        const profileNameEl = document.querySelector('.apple-profile-name');
        if (profileNameEl) profileNameEl.textContent = siteName;

        // Update document title immediately
        document.title = `${siteName} - จัดการระบบ`;
      } else {
        throw new Error(result.error || 'เกิดข้อผิดพลาด');
      }
    } catch (error) {
      this.showToast(error.message, 'error');
    } finally {
      this.isSavingSiteName = false;
    }
  }

  async savePhone() {
    if (this.isSavingPhone) {
      return;
    }

    const phone = document.getElementById('contactPhone')?.value.trim();
    if (!phone || !/^[0-9\-\+\s()]{8,20}$/.test(phone)) {
      this.showToast('รูปแบบเบอร์โทรไม่ถูกต้อง', 'error');
      return;
    }

    this.isSavingPhone = true;

    try {
      const response = await fetch('/dormitory_management/Manage/save_system_settings.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `contact_phone=${encodeURIComponent(phone)}`
      });

      const result = await response.json();
      if (result.success) {
        this.showToast('บันทึกเบอร์โทรสำเร็จ', 'success');
        this.closeSheet('sheet-phone');
        const displayEl = document.querySelector('[data-display="phone"]');
        if (displayEl) displayEl.textContent = phone;
      } else {
        throw new Error(result.error || 'เกิดข้อผิดพลาด');
      }
    } catch (error) {
      this.showToast(error.message, 'error');
    } finally {
      this.isSavingPhone = false;
    }
  }

  async saveEmail() {
    if (this.isSavingEmail) {
      return;
    }

    const email = document.getElementById('contactEmail')?.value.trim();
    if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
      this.showToast('รูปแบบอีเมลไม่ถูกต้อง', 'error');
      return;
    }

    this.isSavingEmail = true;

    try {
      const response = await fetch('/dormitory_management/Manage/save_system_settings.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `contact_email=${encodeURIComponent(email)}`
      });

      const result = await response.json();
      if (result.success) {
        this.showToast('บันทึกอีเมลสำเร็จ', 'success');
        this.closeSheet('sheet-email');
        const displayEl = document.querySelector('[data-display="email"]');
        if (displayEl) displayEl.textContent = email;
      } else {
        throw new Error(result.error || 'เกิดข้อผิดพลาด');
      }
    } catch (error) {
      this.showToast(error.message, 'error');
    } finally {
      this.isSavingEmail = false;
    }
  }

  async saveQuickActions() {
    const quickActions = [];

    for (let index = 0; index < 5; index += 1) {
      const label = document.getElementById(`quickActionLabel${index}`)?.value.trim() || '';
      const href = document.getElementById(`quickActionHref${index}`)?.value.trim() || '';
      const shortcut = document.getElementById(`quickActionShortcut${index}`)?.value.trim() || '';
      const enabled = !!document.getElementById(`quickActionEnabled${index}`)?.checked;

      if (enabled && (!label || !href)) {
        this.showToast(`กรุณากรอกชื่อและลิงก์ของปุ่ม ${index + 1}`, 'error');
        return;
      }

      quickActions.push({ label, href, shortcut, enabled });
    }

    if (!quickActions.some((action) => action.enabled)) {
      this.showToast('ต้องเปิดใช้งานอย่างน้อย 1 ปุ่ม', 'error');
      return;
    }

    try {
      const response = await fetch('../Manage/save_system_settings.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `admin_quick_actions=${encodeURIComponent(JSON.stringify(quickActions))}`
      });

      const result = await response.json();
      if (result.success) {
        const enabledCount = quickActions.filter((action) => action.enabled).length;
        const displayEl = document.querySelector('[data-display="quickactions-count"]');
        if (displayEl) displayEl.textContent = `${enabledCount} ปุ่ม`;
        this.showToast('บันทึกปุ่มลัดสำเร็จ', 'success');
        this.closeSheet('sheet-quick-actions');
      } else {
        throw new Error(result.error || 'เกิดข้อผิดพลาด');
      }
    } catch (error) {
      this.showToast(error.message, 'error');
    }
  }

  // ===== Session Timeout =====
  async saveSessionTimeout() {
    const timeout = document.getElementById('sessionTimeoutInput')?.value.trim();

    if (!timeout || timeout < 1 || timeout > 999) {
      this.showToast('กรุณากรอกระยะเวลาระหว่าง 1-999 นาที', 'error');
      return;
    }

    try {
      const response = await fetch('../Manage/save_system_settings.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `session_timeout_minutes=${encodeURIComponent(timeout)}`
      });

      const result = await response.json();
      if (result.success) {
        const displayEl = document.querySelector('[data-display="session-timeout-display"]');
        if (displayEl) displayEl.textContent = `${timeout} นาที`;
        this.showToast('บันทึกการตั้งค่า Session สำเร็จ', 'success');
        this.closeSheet('sheet-session-timeout');
      } else {
        throw new Error(result.error || 'เกิดข้อผิดพลาด');
      }
    } catch (error) {
      this.showToast(error.message, 'error');
    }
  }

  // ===== Bank Information Forms =====
  async saveBankName() {
    if (this.isSavingBankName) {
      return;
    }

    const bankName = document.getElementById('bankName')?.value;

    this.isSavingBankName = true;
    
    try {
      const response = await fetch('/dormitory_management/Manage/save_system_settings.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `bank_name=${encodeURIComponent(bankName)}`
      });

      const result = await response.json();
      if (result.success) {
        this.showToast('บันทึกชื่อธนาคารสำเร็จ', 'success');
        this.closeSheet('sheet-bankname');
        const displayEl = document.querySelector('[data-display="bankname"]');
        if (displayEl) displayEl.textContent = bankName || 'ไม่ระบุ';
      } else {
        throw new Error(result.error || 'เกิดข้อผิดพลาด');
      }
    } catch (error) {
      this.showToast(error.message, 'error');
    } finally {
      this.isSavingBankName = false;
    }
  }

  async saveBankAccountName() {
    if (this.isSavingBankAccountName) {
      return;
    }

    const bankAccountName = document.getElementById('bankAccountName')?.value.trim();

    this.isSavingBankAccountName = true;
    
    try {
      const response = await fetch('/dormitory_management/Manage/save_system_settings.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `bank_account_name=${encodeURIComponent(bankAccountName)}`
      });

      const result = await response.json();
      if (result.success) {
        this.showToast('บันทึกชื่อบัญชีสำเร็จ', 'success');
        this.closeSheet('sheet-bankaccountname');
        const displayEl = document.querySelector('[data-display="bankaccountname"]');
        if (displayEl) displayEl.textContent = bankAccountName || 'ไม่ระบุ';
      } else {
        throw new Error(result.error || 'เกิดข้อผิดพลาด');
      }
    } catch (error) {
      this.showToast(error.message, 'error');
    } finally {
      this.isSavingBankAccountName = false;
    }
  }

  async saveBankAccountNumber() {
    const bankAccountNumber = document.getElementById('bankAccountNumber')?.value.trim();
    
    try {
      const response = await fetch('../Manage/save_system_settings.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `bank_account_number=${encodeURIComponent(bankAccountNumber)}`
      });

      const result = await response.json();
      if (result.success) {
        this.showToast('บันทึกเลขบัญชีสำเร็จ', 'success');
        this.closeSheet('sheet-bankaccountnumber');
        const displayEl = document.querySelector('[data-display="bankaccountnumber"]');
        if (displayEl) displayEl.textContent = bankAccountNumber || 'ไม่ระบุ';
      } else {
        throw new Error(result.error || 'เกิดข้อผิดพลาด');
      }
    } catch (error) {
      this.showToast(error.message, 'error');
    }
  }

  async savePromptpay() {
    const promptpayNumber = document.getElementById('promptpayNumber')?.value.trim();
    
    try {
      const response = await fetch('../Manage/save_system_settings.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `promptpay_number=${encodeURIComponent(promptpayNumber)}`
      });

      const result = await response.json();
      if (result.success) {
        this.showToast('บันทึกพร้อมเพย์สำเร็จ', 'success');
        this.closeSheet('sheet-promptpay');
        const displayEl = document.querySelector('[data-display="promptpay"]');
        if (displayEl) displayEl.textContent = promptpayNumber || 'ไม่ระบุ';
      } else {
        throw new Error(result.error || 'เกิดข้อผิดพลาด');
      }
    } catch (error) {
      this.showToast(error.message, 'error');
    }
  }

  async saveFontSize() {
    const fontSize = document.getElementById('fontSize')?.value;
    
    try {
      const response = await fetch('../Manage/save_system_settings.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `font_size=${encodeURIComponent(fontSize)}`
      });

      const result = await response.json();
      if (result.success) {
        this.showToast('บันทึกขนาดตัวอักษรสำเร็จ', 'success');
        
        // Apply font size to entire page immediately via CSS variables
        document.documentElement.style.setProperty('--font-scale', fontSize);
        document.documentElement.style.setProperty('--admin-font-scale', fontSize);
        
        // Update preview
        const preview = document.querySelector('.font-size-preview');
        if (preview) {
          preview.style.fontSize = `calc(1rem * ${fontSize})`;
        }
        
        // ====== Global Sync ======
        // Store in localStorage for ALL admin pages to pick up
        localStorage.setItem('adminFontScale', fontSize);
        
        // Dispatch storage event manually for same-page listeners (storage event only fires on OTHER tabs)
        // Other pages will get notified via 'storage' event automatically
      } else {
        throw new Error(result.error || 'เกิดข้อผิดพลาด');
      }
    } catch (error) {
      this.showToast(error.message, 'error');
    }
  }

  async saveFpsThreshold() {
    const fpsThreshold = document.getElementById('fpsThreshold')?.value;
    
    try {
      const response = await fetch('../Manage/save_system_settings.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `fps_threshold=${encodeURIComponent(fpsThreshold)}`
      });

      const result = await response.json();
      if (result.success) {
        this.showToast('บันทึกค่า FPS สำเร็จ', 'success');
        
        // Update display value in row
        const rowValue = document.querySelector('[data-sheet="sheet-fps-threshold"] .apple-row-value');
        if (rowValue) {
          rowValue.textContent = fpsThreshold;
        }
        
        // Store in localStorage for other pages
        localStorage.setItem('fpsThreshold', fpsThreshold);
      } else {
        throw new Error(result.error || 'เกิดข้อผิดพลาด');
      }
    } catch (error) {
      this.showToast(error.message, 'error');
    }
  }

  // ===== Image Upload =====
  initImageUploads() {
    console.log('initImageUploads called');
    
    // Logo upload
    const logoInput = document.getElementById('logoInput');
    if (logoInput) {
      logoInput.dataset.appleUploadBound = '1';
      logoInput.addEventListener('change', (e) => {
        this.handleLogoUpload(e.target.files[0]);
      });
    }

    // Background upload
    const bgInput = document.getElementById('bgInput');
    if (bgInput) {
      bgInput.dataset.appleUploadBound = '1';
      bgInput.addEventListener('change', (e) => {
        this.handleBgUpload(e.target.files[0]);
      });
    }

    // Old logo select
    const oldLogoSelect = document.getElementById('oldLogoSelect');
    if (oldLogoSelect) {
      oldLogoSelect.addEventListener('change', (e) => {
        this.previewOldLogo(e.target.value);
      });
    }

    // Background select
    const bgSelect = document.getElementById('bgSelect');
    if (bgSelect) {
      bgSelect.addEventListener('change', (e) => {
        this.previewBgSelect(e.target.value);
      });
    }

    // Signature upload
    const signatureInput = document.getElementById('signatureInput');
    const signatureUploadArea = document.getElementById('signatureUploadArea');
    
    console.log('signatureInput element:', signatureInput);
    console.log('signatureUploadArea element:', signatureUploadArea);
    
    // Click on upload area to trigger file input
    if (signatureUploadArea && signatureInput) {
      signatureUploadArea.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        console.log('Upload area clicked');
        signatureInput.click();
      });
    }
    
    if (signatureInput) {
      signatureInput.addEventListener('change', (e) => {
        console.log('Signature file selected:', e.target.files);
        const file = e.target.files[0];
        if (file) {
          console.log('File details:', file.name, file.type, file.size);
          // แสดง preview ทันที
          this.showSignaturePreview(file);
          // จากนั้นค่อยอัพโหลด
          this.handleSignatureUpload(file);
        }
      });
    } else {
      console.warn('signatureInput not found!');
    }

    // Delete signature button
    const deleteSignatureBtn = document.getElementById('deleteSignatureBtn');
    if (deleteSignatureBtn) {
      deleteSignatureBtn.addEventListener('click', () => {
        this.deleteSignature();
      });
    }
  }

  async handleLogoUpload(file) {
    if (!file) return;

    const formData = new FormData();
    formData.append('logo', file);

    try {
      const response = await fetch('../Manage/save_system_settings.php', {
        method: 'POST',
        body: formData
      });

      const result = await response.json();
      if (result.success) {
        this.showToast('อัพโหลด Logo สำเร็จ', 'success');
        // Update preview
        const preview = document.getElementById('logoPreviewImg');
        if (preview) {
          preview.src = `/dormitory_management/Public/Assets/Images/${result.filename}?t=${Date.now()}`;
        }
      } else {
        throw new Error(result.error || 'เกิดข้อผิดพลาด');
      }
    } catch (error) {
      this.showToast(error.message, 'error');
    }
  }

  async handleBgUpload(file) {
    if (!file) return;

    const formData = new FormData();
    formData.append('bg', file);

    try {
      const response = await fetch('../Manage/save_system_settings.php', {
        method: 'POST',
        body: formData
      });

      const result = await response.json();
      if (result.success) {
        this.showToast('อัพโหลดภาพพื้นหลังสำเร็จ', 'success');
        // Update preview
        const preview = document.getElementById('bgPreviewImg');
        if (preview) {
          preview.src = `/dormitory_management/Public/Assets/Images/${result.filename}?t=${Date.now()}`;
        }
      } else {
        throw new Error(result.error || 'เกิดข้อผิดพลาด');
      }
    } catch (error) {
      this.showToast(error.message, 'error');
    }
  }

  showSignaturePreview(file) {
    const previewContainer = document.getElementById('signatureUploadPreview');
    const previewImg = document.getElementById('signatureUploadPreviewImg');
    const fileNameSpan = document.getElementById('signatureFileName');
    const uploadArea = document.getElementById('signatureUploadArea');
    
    if (previewContainer && previewImg && fileNameSpan) {
      // อ่านไฟล์และแสดง preview
      const reader = new FileReader();
      reader.onload = (e) => {
        previewImg.src = e.target.result;
        fileNameSpan.textContent = file.name;
        previewContainer.style.display = 'block';
        if (uploadArea) {
          uploadArea.style.display = 'none';
        }
      };
      reader.readAsDataURL(file);
    }
  }

  async handleSignatureUpload(file) {
    if (!file) return;

    // Validate file type - only PNG allowed for transparent signature
    if (file.type !== 'image/png') {
      this.showToast('กรุณาเลือกไฟล์ PNG เท่านั้น', 'error');
      // ซ่อน preview ถ้าไฟล์ไม่ถูกต้อง
      const previewContainer = document.getElementById('signatureUploadPreview');
      const uploadArea = document.getElementById('signatureUploadArea');
      if (previewContainer) previewContainer.style.display = 'none';
      if (uploadArea) uploadArea.style.display = 'block';
      return;
    }

    const formData = new FormData();
    formData.append('signature', file);

    try {
      const response = await fetch('../Manage/save_system_settings.php', {
        method: 'POST',
        body: formData
      });

      const result = await response.json();
      if (result.success) {
        this.showToast('อัพโหลดลายเซ็นสำเร็จ', 'success');
        
        const newTimestamp = Date.now();
        const newSrc = `/dormitory_management/Public/Assets/Images/${result.filename}?t=${newTimestamp}`;
        
        // Update preview in sheet
        const preview = document.getElementById('signaturePreviewImg');
        if (preview) {
          if (preview.tagName === 'IMG') {
            preview.src = newSrc;
          } else {
            // Replace placeholder with actual image
            preview.outerHTML = `<img id="signaturePreviewImg" src="${newSrc}" alt="Signature" style="max-width: 200px; max-height: 80px; object-fit: contain;">`;
          }
        }
        
        // Update preview in row
        const rowImg = document.getElementById('signatureRowImg');
        if (rowImg) {
          if (rowImg.tagName === 'IMG') {
            rowImg.src = newSrc;
          } else {
            // Replace span with image
            rowImg.outerHTML = `<img id="signatureRowImg" src="${newSrc}" alt="Signature" style="max-width: 60px; max-height: 24px; object-fit: contain; border-radius: 4px;">`;
          }
        }
        
        // Update info text
        const infoP = document.querySelector('#sheet-signature .apple-image-info p');
        if (infoP) {
          infoP.textContent = result.filename;
        }
        
        // Reload page to show delete button if it wasn't there before
        setTimeout(() => location.reload(), 500);
      } else {
        throw new Error(result.error || 'เกิดข้อผิดพลาด');
      }
    } catch (error) {
      this.showToast(error.message, 'error');
    }
  }

  async deleteSignature() {
    const confirmed = await this.showConfirm('คุณต้องการลบลายเซ็นนี้หรือไม่?', 'ยืนยันการลบลายเซ็น');
    if (!confirmed) return;

    try {
      const response = await fetch('../Manage/save_system_settings.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'delete_signature=1'
      });

      const result = await response.json();
      if (result.success) {
        this.showToast('ลบลายเซ็นสำเร็จ', 'success');
        
        // Reload page to update UI
        setTimeout(() => location.reload(), 500);
      } else {
        throw new Error(result.error || 'เกิดข้อผิดพลาด');
      }
    } catch (error) {
      this.showToast(error.message, 'error');
    }
  }

  previewOldLogo(filename) {
    const previewContainer = document.getElementById('oldLogoPreview');
    if (!previewContainer) return;

    if (filename) {
      previewContainer.innerHTML = `
        <img src="/dormitory_management/Public/Assets/Images/${filename}" alt="Preview" style="max-width: 100px; max-height: 100px; border-radius: 12px;">
        <button type="button" class="apple-button primary" style="width: auto; padding: 10px 16px;" onclick="appleSettings.useOldLogo('${filename}')">
          ใช้รูปนี้
        </button>
      `;
    } else {
      previewContainer.innerHTML = '';
    }
  }

  async useOldLogo(filename) {
    try {
      const response = await fetch('../Manage/save_system_settings.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `load_old_logo=${encodeURIComponent(filename)}`
      });

      const result = await response.json();
      if (result.success) {
        this.showToast('เปลี่ยน Logo สำเร็จ', 'success');
        
        const newTimestamp = Date.now();
        const newSrc = `/dormitory_management/Public/Assets/Images/${filename}?t=${newTimestamp}`;
        
        // Update preview image in sheet
        const preview = document.getElementById('logoPreviewImg');
        if (preview) {
          preview.src = newSrc;
        }
        
        // Update filename text next to preview
        const infoP = document.querySelector('.apple-image-preview .apple-image-info p');
        if (infoP) {
          infoP.textContent = filename;
        }
        
        // Update logo in settings row
        const logoRowImg = document.getElementById('logoRowImg');
        if (logoRowImg) {
          logoRowImg.src = newSrc;
        }
        
        // Update sidebar logo (team-avatar-img class)
        const sidebarLogo = document.querySelector('.team-avatar-img');
        if (sidebarLogo) {
          sidebarLogo.src = `/dormitory_management/Public/Assets/Images/${filename}?t=${newTimestamp}`;
        }
        
        // Update any other logo images on page
        document.querySelectorAll('img[alt="Logo"]').forEach(img => {
          if (img.id !== 'logoPreviewImg' && !img.classList.contains('team-avatar-img')) {
            img.src = newSrc;
          }
        });
        
        // Clear the select dropdown
        const selectEl = document.getElementById('oldLogoSelect');
        if (selectEl) {
          selectEl.value = '';
        }
        
        // Clear the preview area below select
        const oldLogoPreview = document.getElementById('oldLogoPreview');
        if (oldLogoPreview) {
          oldLogoPreview.innerHTML = '';
        }
        
        this.closeSheet('sheet-logo');
      } else {
        throw new Error(result.error || 'เกิดข้อผิดพลาด');
      }
    } catch (error) {
      this.showToast(error.message, 'error');
    }
  }

  previewBgSelect(filename) {
    const previewContainer = document.getElementById('bgSelectPreview');
    if (!previewContainer) return;

    if (filename) {
      previewContainer.innerHTML = `
        <img src="/dormitory_management/Public/Assets/Images/${filename}" alt="Preview" style="max-width: 200px; max-height: 120px; border-radius: 12px; object-fit: cover;">
        <button type="button" class="apple-button primary" style="width: auto; padding: 10px 16px; margin-top: 8px;" onclick="appleSettings.useBgImage('${filename}')">
          ใช้ภาพนี้
        </button>
      `;
    } else {
      previewContainer.innerHTML = '';
    }
  }

  async useBgImage(filename) {
    try {
      const response = await fetch('../Manage/save_system_settings.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `bg_filename=${encodeURIComponent(filename)}`
      });

      const result = await response.json();
      if (result.success) {
        this.showToast('เปลี่ยนภาพพื้นหลังสำเร็จ', 'success');
        
        const newTimestamp = Date.now();
        const newSrc = `/dormitory_management/Public/Assets/Images/${filename}?t=${newTimestamp}`;
        
        // Update preview image in sheet
        const preview = document.getElementById('bgPreviewImg');
        if (preview) {
          preview.src = newSrc;
        }
        
        // Update background image in settings row
        const bgRowImg = document.getElementById('bgRowImg');
        if (bgRowImg) {
          bgRowImg.src = newSrc;
        }
        
        // Update filename text next to preview
        const bgInfoP = document.querySelector('#sheet-background .apple-image-preview .apple-image-info p');
        if (bgInfoP) {
          bgInfoP.textContent = filename;
        }
        
        // Clear the select dropdown
        const selectEl = document.getElementById('bgSelect');
        if (selectEl) {
          selectEl.value = '';
        }
        
        // Clear the preview area below select
        const bgSelectPreview = document.getElementById('bgSelectPreview');
        if (bgSelectPreview) {
          bgSelectPreview.innerHTML = '';
        }
        
        this.closeSheet('sheet-background');
      } else {
        throw new Error(result.error || 'เกิดข้อผิดพลาด');
      }
    } catch (error) {
      this.showToast(error.message, 'error');
    }
  }

  // ===== Theme Selector =====
  initThemeSelector() {
    document.querySelectorAll('.apple-theme-option').forEach(option => {
      option.addEventListener('click', () => {
        document.querySelectorAll('.apple-theme-option').forEach(opt => opt.classList.remove('active'));
        option.classList.add('active');
        const theme = option.dataset.theme;
        this.savePublicTheme(theme);
      });
    });
  }

  // ===== View Mode Selector =====
  initViewModeSelector() {
    document.querySelectorAll('.apple-view-option').forEach(option => {
      option.addEventListener('click', () => {
        document.querySelectorAll('.apple-view-option').forEach(opt => opt.classList.remove('active'));
        option.classList.add('active');
        const viewMode = option.dataset.view;
        this.saveDefaultViewMode(viewMode);
        
        // Update display value
        const displayEl = document.querySelector('[data-sheet="sheet-default-view"] .apple-row-value');
        if (displayEl) {
          displayEl.textContent = viewMode === 'grid' ? 'Grid' : 'List';
        }
      });
    });
  }

  async saveDefaultViewMode(viewMode) {
    try {
      const response = await fetch('../Manage/save_system_settings.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `default_view_mode=${encodeURIComponent(viewMode)}`
      });

      const result = await response.json();
      if (result.success) {
        // Also update localStorage so it takes effect immediately
        localStorage.setItem('adminDefaultViewMode', viewMode);
        this.showToast('บันทึกรูปแบบการแสดงผลสำเร็จ', 'success');

        window.setTimeout(() => {
          const sheet = document.getElementById('sheet-default-view');
          if (!sheet || !sheet.classList.contains('active')) {
            return;
          }

          sheet.classList.remove('active');
          if (!document.querySelector('.apple-sheet-overlay.active')) {
            document.body.style.overflow = '';
          }
        }, 1000);
      } else {
        throw new Error(result.error || 'เกิดข้อผิดพลาด');
      }
    } catch (error) {
      this.showToast(error.message, 'error');
    }
  }

  async savePublicTheme(theme) {
    if (this.isSavingPublicTheme) {
      return;
    }

    this.isSavingPublicTheme = true;

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

      const displayEl = document.querySelector('[data-sheet="sheet-public-theme"] .apple-row-value');
      if (displayEl) {
        const nameEl = document.querySelector(`.apple-theme-option[data-theme="${theme}"] .apple-theme-name`);
        displayEl.textContent = nameEl ? nameEl.textContent.trim() : theme;
      }

      this.showToast(result.message || 'บันทึกธีมสำเร็จ', 'success');
    } catch (error) {
      this.showToast(error.message, 'error');
    } finally {
      this.isSavingPublicTheme = false;
    }
  }

  // ===== Language Selector =====
  initLanguageSelector() {
    // Use event delegation on the document to handle dynamically loaded content
    document.addEventListener('click', (e) => {
      const languageOption = e.target.closest('.apple-language-option');
      if (languageOption) {
        console.log('Language option clicked:', languageOption.dataset.language);
        
        // Prevent default behavior
        e.preventDefault();
        e.stopPropagation();
        
        // Remove active from all language options
        document.querySelectorAll('.apple-language-option').forEach(opt => {
          opt.classList.remove('active');
        });
        
        // Add active to clicked element
        languageOption.classList.add('active');
        
        const language = languageOption.dataset.language;
        if (language) {
          console.log('Saving language:', language);
          this.saveLanguage(language);
        }
      }
    }, true); // Use capture phase for better event handling
  }

  async saveLanguage(language) {
    console.log('[Language] Starting save for:', language);
    
    try {
      const body = `system_language=${encodeURIComponent(language)}`;
      console.log('[Language] Request body:', body);
      
      const response = await fetch('../Manage/save_system_settings.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body
      });

      console.log('[Language] Response status:', response.status);
      console.log('[Language] Response headers:', response.headers);
      
      const result = await response.json();
      console.log('[Language] Response JSON:', result);
      
      if (result.success) {
        console.log('[Language] Success! Language saved:', result.language);
        
        // Update display value
        const displayEl = document.querySelector('[data-sheet="sheet-language"] .apple-row-value');
        const langNames = { 'th': '🇹🇭 ไทย', 'en': '🇺🇸 English' };
        if (displayEl) {
          displayEl.textContent = langNames[language] || language;
          console.log('[Language] Updated display element');
        }
        
        // Store in localStorage for immediate effect
        localStorage.setItem('systemLanguage', language);
        console.log('[Language] Stored in localStorage');
        
        // Set cookie for server-side
        document.cookie = `system_language=${language}; path=/; max-age=${365*24*60*60}; SameSite=Lax`;
        console.log('[Language] Set cookie');
        
        const message = language === 'th' 
          ? 'บันทึกภาษาสำเร็จ กำลังโหลดหน้าใหม่...' 
          : 'Language saved. Reloading page...';
        this.showToast(message, 'success');
        
        // Reload page to apply language change (with cache bypass)
        console.log('[Language] Reloading page in 1500ms with cache bypass...');
        setTimeout(() => {
          console.log('[Language] Hard reload initiated');
          window.location.href = window.location.href;
        }, 1500);
      } else {
        const errorMsg = result.error || 'Unknown error';
        console.error('[Language] Save failed:', errorMsg);
        throw new Error(errorMsg);
      }
    } catch (error) {
      console.error('[Language] Catch block error:', error);
      this.showToast('เกิดข้อผิดพลาด: ' + error.message, 'error');
    }
  }

  // ===== Color Picker =====
  initColorPicker() {
    document.querySelectorAll('.apple-color-option').forEach(option => {
      option.addEventListener('click', () => {
        document.querySelectorAll('.apple-color-option').forEach(opt => opt.classList.remove('active'));
        option.classList.add('active');
        const color = option.dataset.color;
        
        // Update color input and display
        const colorInput = document.getElementById('themeColor');
        const hexDisplay = document.getElementById('colorHexDisplay');
        if (colorInput) colorInput.value = color;
        if (hexDisplay) hexDisplay.textContent = color;
        
        this.saveThemeColor(color);
      });
    });

    // Custom color picker
    const colorInput = document.getElementById('themeColor');
    if (colorInput) {
      colorInput.addEventListener('change', (e) => {
        const color = e.target.value;
        const hexDisplay = document.getElementById('colorHexDisplay');
        if (hexDisplay) hexDisplay.textContent = color;
        
        // Remove active from preset colors
        document.querySelectorAll('.apple-color-option').forEach(opt => opt.classList.remove('active'));
        
        this.saveThemeColor(color);
      });
    }
  }

  async saveThemeColor(color) {
    try {
      const response = await fetch('../Manage/save_system_settings.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `theme_color=${encodeURIComponent(color)}`
      });

      const result = await response.json();
      if (result.success) {
        this.showToast('บันทึกสีพื้นหลังสำเร็จ', 'success');
        // Update theme color immediately
        document.body.setAttribute('data-theme-color', color);
        document.documentElement.style.setProperty('--theme-bg-color', color);
        
        // ===== Check if light or dark color =====
        const isLightColor = this.isLightColor(color);
        
        if (isLightColor) {
          // Light mode - add live-light class
          document.body.classList.add('live-light');
          document.body.classList.remove('live-dark');
        } else {
          // Dark mode - remove live-light class
          document.body.classList.remove('live-light');
          document.body.classList.add('live-dark');
        }
        
        // Update active state of color options
        document.querySelectorAll('.apple-color-option').forEach(opt => {
          opt.classList.toggle('active', opt.dataset.color === color);
        });
      } else {
        throw new Error(result.error || 'เกิดข้อผิดพลาด');
      }
    } catch (error) {
      this.showToast(error.message, 'error');
    }
  }
  
  // Helper function to determine if a color is light or dark
  isLightColor(hexColor) {
    // Convert hex to RGB
    const hex = hexColor.replace('#', '');
    const r = parseInt(hex.substr(0, 2), 16);
    const g = parseInt(hex.substr(2, 2), 16);
    const b = parseInt(hex.substr(4, 2), 16);
    
    // Calculate luminance (perceived brightness)
    const luminance = (0.299 * r + 0.587 * g + 0.114 * b) / 255;
    
    // Return true if light (luminance > 0.5)
    return luminance > 0.5;
  }

  // ===== Utility Rates =====
  async saveUtilityRates() {
    const waterRate = document.getElementById('waterRate')?.value;
    const electricRate = document.getElementById('electricRate')?.value;
    const waterBaseUnits = document.getElementById('waterBaseUnits')?.value;
    const waterBasePrice = document.getElementById('waterRate')?.value; // same field
    const waterExcessRate = document.getElementById('waterExcessRate')?.value;
    let effectiveDate = document.getElementById('effectiveDate')?.value;

    // normalize Thai-style date entry (dd/mm/yyyy) into ISO
    if (effectiveDate && !/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/.test(effectiveDate)) {
      const m = effectiveDate.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/);
      if (m) {
        effectiveDate = `${m[3]}-${m[2].padStart(2,'0')}-${m[1].padStart(2,'0')}`;
        document.getElementById('effectiveDate').value = effectiveDate;
      } else {
        this.showToast('วันที่ไม่ถูกต้อง (พิมพ์ YYYY-MM-DD หรือเลือกจากปฏิทิน)', 'error');
        return;
      }
    }

    if (!waterRate || !electricRate || !effectiveDate) {
      this.showToast('กรุณากรอกข้อมูลให้ครบ', 'error');
      return;
    }

    try {
      const params = new URLSearchParams();
      params.append('rate_water', waterRate);
      params.append('rate_elec', electricRate);
      if (waterBaseUnits !== undefined) params.append('water_base_units', waterBaseUnits);
      if (waterExcessRate !== undefined) params.append('water_excess_rate', waterExcessRate);
      params.append('effective_date', effectiveDate);

      const response = await fetch('../Manage/add_rate.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: params.toString()
      });

      const result = await response.json();
      console.log('add_rate result', result);
      if (result.success) {
        // Update UI without reload - Apple style
        this.updateRateUI(
          result.rate_water,
          result.rate_elec,
          result.rate_id,
          result.water_base_units,
          result.water_base_price,
          result.water_excess_rate
        );
        this.showToast('บันทึกอัตราค่าน้ำค่าไฟสำเร็จ', 'success');
        
        // Reset date to today for next entry
        const dateInput = document.getElementById('effectiveDate');
        if (dateInput) {
          const today = new Date();
          dateInput.value = today.toISOString().split('T')[0];
        }
      } else {
        throw new Error(result.message || result.error || 'เกิดข้อผิดพลาด');
      }
    } catch (error) {
      this.showToast(error.message, 'error');
    }
  }

  // live-update sheet preview when rate inputs change
  bindRateInputs() {
    const baseUnitsEl = document.getElementById('waterBaseUnits');
    const basePriceEl = document.getElementById('waterRate');
    const excessEl = document.getElementById('waterExcessRate');
    const sheetUnit = document.querySelector('#sheet-rates .apple-rate-unit');
    const sheetWater = document.getElementById('sheetWaterRate');

    function refresh() {
      const units = baseUnitsEl ? baseUnitsEl.value : '';
      const price = basePriceEl ? basePriceEl.value : '';
      const excess = excessEl ? excessEl.value : '';
      if (sheetUnit) {
        sheetUnit.innerHTML =
          `เหมาจ่าย ฿${Number(price).toLocaleString()} ≤${units} หน่วย` +
          `<br><span style="font-size:11px;">เกินหน่วยละ ฿${excess}</span>`;
      }
      // update main card previews too
      const unitLabel = document.getElementById('currentWaterUnit');
      const excessLabel = document.getElementById('currentWaterExcess');
      const mainWater = document.getElementById('currentWaterRate');
      if (unitLabel) unitLabel.textContent = `เหมาจ่าย ฿${Number(price).toLocaleString()} ≤${units} หน่วย`;
      if (excessLabel) excessLabel.textContent = `เกินหน่วยละ ฿${excess}`;
      if (mainWater && price !== '') mainWater.textContent = '฿' + Number(price).toLocaleString();
      if (sheetWater && price !== '') {
        sheetWater.textContent = '฿' + Number(price).toLocaleString();
      }
    }

    [baseUnitsEl, basePriceEl, excessEl].forEach(el => {
      if (el) el.addEventListener('input', refresh);
    });
  }

  async deleteRate(rateId) {
    const confirmed = await this.showConfirm('ต้องการลบอัตรานี้หรือไม่?', 'ลบอัตรา');
    if (!confirmed) return;

    try {
      const response = await fetch('../Manage/delete_rate.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `rate_id=${rateId}`
      });

      const result = await response.json();
      if (result.success) {
        // Remove row with animation - Apple style
        const row = document.getElementById(`rate-row-${rateId}`);
        if (row) {
          row.style.transition = 'all 0.3s ease';
          row.style.transform = 'translateX(100%)';
          row.style.opacity = '0';
          
          setTimeout(() => {
            row.style.height = row.offsetHeight + 'px';
            row.style.overflow = 'hidden';
            
            requestAnimationFrame(() => {
              row.style.height = '0';
              row.style.padding = '0';
              row.style.margin = '0';
              
              setTimeout(() => row.remove(), 200);
            });
          }, 200);
        }
        
        this.showToast('ลบอัตราสำเร็จ', 'success');
      } else {
        throw new Error(result.message || 'เกิดข้อผิดพลาด');
      }
    } catch (error) {
      this.showToast(error.message, 'error');
    }
  }

  // Use existing rate as current
  async useRate(rateId) {
    const confirmed = await this.showConfirm('ต้องการใช้อัตรานี้เป็นอัตราปัจจุบันหรือไม่?', 'เปลี่ยนอัตรา');
    if (!confirmed) return;

    // Get rate data from the row
    const row = document.getElementById(`rate-row-${rateId}`);
    const waterRate = row?.dataset.water;
    const elecRate = row?.dataset.elec;

    try {
      const response = await fetch('../Manage/add_rate.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `use_rate_id=${rateId}`
      });

      const result = await response.json();
      if (result.success) {
        // Update UI without reload - Apple style
        this.updateRateUI(
          result.rate_water,
          result.rate_elec,
          rateId,
          result.water_base_units,
          result.water_base_price,
          result.water_excess_rate
        );
        this.showToast('เปลี่ยนอัตราสำเร็จ', 'success');
      } else {
        throw new Error(result.message || result.error || 'เกิดข้อผิดพลาด');
      }
    } catch (error) {
      this.showToast(error.message, 'error');
    }
  }

  // Update rate UI without page reload
  updateRateUI(waterRate, elecRate, newActiveRateId, baseUnits, basePrice, excessRate) {
    const today = new Date();
    const dateStr = `${String(today.getDate()).padStart(2, '0')}/${String(today.getMonth() + 1).padStart(2, '0')}/${today.getFullYear() + 543}`;
    
    // Update main display - ค่าน้ำเหมาจ่ายจะแสดงราคาและหน่วยฐานจาก response/settings
    const currentWater = document.getElementById('currentWaterRate');
    const currentElec = document.getElementById('currentElecRate');
    const currentDateLabel = document.getElementById('currentRateDateLabel');
    
    // water display: show base price (waterRate) which is same as flat charge
    if (currentWater) {
      currentWater.style.transform = 'scale(1.1)';
      let basePriceText = '฿' + Number(basePrice ?? waterRate).toLocaleString();
      currentWater.textContent = basePriceText;
      setTimeout(() => currentWater.style.transform = 'scale(1)', 200);
    }
    // update the summary labels on the main card as well
    const unitLabel = document.getElementById('currentWaterUnit');
    const excessLabel = document.getElementById('currentWaterExcess');
    if (unitLabel && baseUnits !== undefined) {
      unitLabel.textContent = `เหมาจ่าย ฿${Number(basePrice).toLocaleString()} สำหรับ ≤${baseUnits} หน่วย`;
    }
    if (excessLabel) {
      excessLabel.textContent = `เกินหน่วยละ ฿${excessRate ?? WATER_EXCESS_RATE}`;
    }
    if (currentElec) {
      currentElec.style.transform = 'scale(1.1)';
      currentElec.textContent = `฿${Number(elecRate).toLocaleString()}`;
      setTimeout(() => currentElec.style.transform = 'scale(1)', 200);
    }
    // also update threshold display areas if present
    const rateUnitElem = document.querySelector('.apple-rate-unit');
    if (rateUnitElem && baseUnits !== undefined) {
      rateUnitElem.innerHTML =
        `เหมาจ่าย ฿${Number(basePrice).toLocaleString()} สำหรับ ≤${baseUnits} หน่วย` +
        `<br><span style="font-size:11px;">เกินหน่วยละ ฿${excessRate ?? WATER_EXCESS_RATE}</span>`;
    }
    if (currentDateLabel) {
      currentDateLabel.textContent = `เริ่มใช้: ${dateStr}`;
    }
    
    // Update sheet display
    const sheetWater = document.getElementById('sheetWaterRate');
    const sheetElec = document.getElementById('sheetElecRate');
    const sheetDateLabel = document.getElementById('sheetRateDateLabel');
    
    if (sheetWater) sheetWater.textContent = '฿' + Number(basePrice ?? waterRate).toLocaleString();
    if (sheetElec) sheetElec.textContent = `฿${Number(elecRate).toLocaleString()}`;
    if (sheetDateLabel) sheetDateLabel.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;vertical-align:-2px;margin-right:3px;"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>อัตราปัจจุบัน (ใช้ตั้งแต่ ${dateStr})`;
    
    // Update input fields
    const waterInput = document.getElementById('waterRate');
    const elecInput = document.getElementById('electricRate');
    if (waterInput) waterInput.value = waterRate;
    if (elecInput) elecInput.value = elecRate;
    
    // Update table: remove old active state and update status column
    document.querySelectorAll('.apple-rate-table tbody tr').forEach(tr => {
      const wasActive = tr.classList.contains('current-rate');
      tr.classList.remove('current-rate');
      
      // Get status cell (6th column, index 5)
      const statusCell = tr.querySelectorAll('td')[5];
      
      // If this row was active, update status to "ยังไม่ถูกใช้"
      if (wasActive && statusCell) {
        statusCell.innerHTML = `<span style="font-size: 11px; color: var(--apple-text-secondary);">ยังไม่ถูกใช้</span>`;
      }
      
      // Show use/delete buttons for previously active row
      const actionCell = tr.querySelector('td:last-child');
      if (wasActive && actionCell) {
        const rId = tr.dataset.rateId;
        if (rId) {
          actionCell.innerHTML = `
            <button type="button" class="apple-use-btn" onclick="useRate(${rId})" title="ใช้อัตรานี้">ใช้</button>
            <button type="button" class="apple-delete-btn" onclick="deleteRate(${rId})">ลบ</button>
          `;
        }
      }
    });
    
    // Add new row at top for the new rate
    const tbody = document.querySelector('.apple-rate-table tbody');
    if (tbody) {
      // Use real rate_id if provided, otherwise use timestamp
      const rateId = newActiveRateId || Date.now();
      
      const newRow = document.createElement('tr');
      newRow.id = `rate-row-${rateId}`;
      newRow.className = 'current-rate';
      newRow.dataset.rateId = rateId;
      newRow.dataset.water = waterRate;
      newRow.dataset.elec = elecRate;
      newRow.dataset.baseUnits = baseUnits;
      newRow.dataset.basePrice = basePrice;
      newRow.dataset.excessRate = excessRate;
      newRow.innerHTML = `
        <td data-label="วันที่">${dateStr}</td>
        <td data-label="เหมาจ่าย" style="text-align: center; color: var(--apple-blue); font-weight: 600;">
          ฿${basePrice !== undefined ? Number(basePrice).toLocaleString() : Number(waterRate).toLocaleString()}
        </td>
        <td data-label="หน่วยฐาน" style="text-align: center; color: var(--apple-blue);">
          ${baseUnits !== undefined && baseUnits !== '' ? `≤${baseUnits} หน่วย` : '-'}
        </td>
        <td data-label="เกิน/หน่วย" style="text-align: center; color: var(--apple-blue);">
          ${excessRate !== undefined && excessRate !== '' ? `฿${Number(excessRate).toLocaleString()}` : '-'}
        </td>
        <td data-label="ค่าไฟ" style="text-align: center; color: var(--apple-orange); font-weight: 600;">฿${Number(elecRate).toLocaleString()}</td>
        <td data-label="สถานะ" style="text-align: center;">
          <span class="apple-badge green rate-active-badge" style="font-size: 10px;">✓ ใช้งานอยู่</span>
          <div style="margin-top:4px;font-size:11px;color:var(--apple-text-secondary);">0 บิล</div>
        </td>
        <td style="text-align: right; white-space: nowrap;"></td>
      `;
      
      // Insert at top with animation
      newRow.style.opacity = '0';
      newRow.style.transform = 'translateY(-20px)';
      newRow.style.backgroundColor = 'rgba(52, 199, 89, 0.2)';
      tbody.insertBefore(newRow, tbody.firstChild);
      
      requestAnimationFrame(() => {
        newRow.style.transition = 'all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1)';
        newRow.style.opacity = '1';
        newRow.style.transform = 'translateY(0)';
        
        // Remove highlight after animation
        setTimeout(() => {
          newRow.style.backgroundColor = '';
        }, 600);
      });
    }
  }

  // Show rate usage details
  showRateUsage(usageData) {
    const usage = typeof usageData === 'string' ? JSON.parse(usageData) : usageData;
    const modal = document.getElementById('rateUsageModal');
    const content = document.getElementById('rateUsageContent');
    
    if (modal && content) {
      content.innerHTML = `
        <div style="background: var(--apple-bg); border-radius: 12px; padding: 16px; margin-bottom: 12px;">
          <div style="display: flex; justify-content: space-around; text-align: center;">
            <div>
              <div style="font-size: 24px; color: var(--apple-blue); font-weight: 700;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:20px;height:20px;vertical-align:-4px;margin-right:4px;"><path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"/></svg>฿${usage.rate_water}</div>
              <div style="font-size: 12px; color: var(--apple-text-secondary);">ค่าน้ำ/หน่วย</div>
            </div>
            <div>
              <div style="font-size: 24px; color: var(--apple-orange); font-weight: 700;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:20px;height:20px;vertical-align:-4px;margin-right:4px;"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>฿${usage.rate_elec}</div>
              <div style="font-size: 12px; color: var(--apple-text-secondary);">ค่าไฟ/หน่วย</div>
            </div>
          </div>
        </div>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px;">
          <div style="background: rgba(0, 122, 255, 0.1); padding: 12px; border-radius: 10px; text-align: center;">
            <div style="font-size: 28px; font-weight: 700; color: var(--apple-blue);">${usage.expense_count}</div>
            <div style="font-size: 12px; color: var(--apple-text-secondary);">บิลที่ใช้อัตรานี้</div>
          </div>
          <div style="background: rgba(52, 199, 89, 0.1); padding: 12px; border-radius: 10px; text-align: center;">
            <div style="font-size: 28px; font-weight: 700; color: var(--apple-green);">${usage.room_count}</div>
            <div style="font-size: 12px; color: var(--apple-text-secondary);">ห้องที่เกี่ยวข้อง</div>
          </div>
        </div>
        
        ${usage.rooms ? `
        <div style="background: var(--apple-bg); border-radius: 12px; padding: 12px;">
          <div style="font-size: 13px; font-weight: 600; color: var(--apple-text-secondary); margin-bottom: 8px;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;vertical-align:-2px;margin-right:3px;"><path d="M18 8h2a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1h-2"/><path d="M4 8h2a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H4"/><rect x="8" y="2" width="8" height="20" rx="1"/><circle cx="12" cy="14" r="1"/></svg>ห้องที่ใช้อัตรานี้:</div>
          <div style="font-size: 15px; color: var(--apple-text);">${usage.rooms}</div>
        </div>
        ` : ''}
        
        <p style="font-size: 12px; color: var(--apple-red); margin-top: 12px; text-align: center;">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;vertical-align:-2px;margin-right:3px;"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>ไม่สามารถลบอัตรานี้ได้ เพราะมีบิลใช้งานอยู่
        </p>
      `;
      modal.style.display = 'flex';
    }
  }

  closeRateUsageModal() {
    const modal = document.getElementById('rateUsageModal');
    if (modal) modal.style.display = 'none';
  }

  // ===== Database Backup =====
  async backupDatabase() {
    const btn = document.getElementById('backupBtn');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px;vertical-align:-3px;margin-right:4px;animation:spin 1s linear infinite;"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>กำลังสำรองข้อมูล...';
    btn.disabled = true;

    try {
      const response = await fetch('../Manage/backup_database.php', {
        method: 'POST'
      });

      const result = await response.json();
      if (result.success) {
        this.showToast('สำรองข้อมูลสำเร็จ', 'success');
        
        // Show download area
        const downloadArea = document.getElementById('backupDownloadArea');
        const downloadLink = document.getElementById('backupDownloadLink');
        if (downloadArea && downloadLink && result.file) {
          downloadLink.href = result.file;
          downloadLink.setAttribute('download', result.filename);
          downloadArea.style.display = 'block';
        }
        
        // Refresh backup list
        setTimeout(() => {
          location.reload();
        }, 1500);
      } else {
        throw new Error(result.error || result.message || 'เกิดข้อผิดพลาด');
      }
    } catch (error) {
      this.showToast(error.message, 'error');
    } finally {
      btn.innerHTML = originalText;
      btn.disabled = false;
    }
  }

  // Download existing backup
  downloadBackup(filename) {
    const link = document.createElement('a');
    link.href = '../backups/' + filename;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  }

  // ===== Apple-style Confirm Dialog =====
  showConfirm(message, title = 'ยืนยัน') {
    return new Promise((resolve) => {
      // Remove existing confirm dialog
      const existingDialog = document.querySelector('.apple-confirm-overlay');
      if (existingDialog) existingDialog.remove();

      const overlay = document.createElement('div');
      overlay.className = 'apple-confirm-overlay';
      overlay.innerHTML = `
        <div class="apple-confirm-dialog">
          <div class="apple-confirm-content">
            <h3 class="apple-confirm-title">${title}</h3>
            <p class="apple-confirm-message">${message}</p>
          </div>
          <div class="apple-confirm-actions">
            <button class="apple-confirm-btn cancel">ยกเลิก</button>
            <button class="apple-confirm-btn confirm">ตกลง</button>
          </div>
        </div>
      `;

      document.body.appendChild(overlay);

      // Trigger animation
      requestAnimationFrame(() => {
        overlay.classList.add('show');
      });

      const closeDialog = (result) => {
        overlay.classList.remove('show');
        setTimeout(() => overlay.remove(), 200);
        resolve(result);
      };

      // Cancel button
      overlay.querySelector('.cancel').addEventListener('click', () => closeDialog(false));
      
      // Confirm button
      overlay.querySelector('.confirm').addEventListener('click', () => closeDialog(true));
      
      // Click overlay to cancel
      overlay.addEventListener('click', (e) => {
        if (e.target === overlay) closeDialog(false);
      });
      
      // ESC key to cancel
      const escHandler = (e) => {
        if (e.key === 'Escape') {
          document.removeEventListener('keydown', escHandler);
          closeDialog(false);
        }
      };
      document.addEventListener('keydown', escHandler);
    });
  }

  // ===== Toast Notification =====
  showToast(message, type = 'info') {
    // Remove existing toast
    const existingToast = document.querySelector('.apple-toast');
    if (existingToast) {
      existingToast.remove();
    }

    const toast = document.createElement('div');
    toast.className = 'apple-toast';
    
    let icon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>';
    if (type === 'success') icon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px;"><polyline points="20 6 9 17 4 12"/></svg>';
    if (type === 'error') icon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px;"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';

    toast.innerHTML = `
      <span class="apple-toast-icon">${icon}</span>
      ${message}
    `;

    document.body.appendChild(toast);

    // Trigger animation
    requestAnimationFrame(() => {
      toast.classList.add('show');
    });

    // Remove after delay
    setTimeout(() => {
      toast.classList.remove('show');
      setTimeout(() => toast.remove(), 300);
    }, 2000);
  }
}

// Initialize
let appleSettings;
document.addEventListener('DOMContentLoaded', () => {
  appleSettings = new AppleSettings();
  window.appleSettings = appleSettings;
  
  // Initialize sidebar toggle for Apple Settings page
  initSidebarToggle();

  // bind live preview handlers to rate inputs if available
  if (typeof appleSettings.bindRateInputs === 'function') {
    appleSettings.bindRateInputs();
  }

  // treat effectiveDate as free text and normalize user input
  const effEl = document.getElementById('effectiveDate');
  if (effEl) {
    // on blur convert common formats to ISO
    effEl.addEventListener('blur', function() {
      let v = this.value.trim();
      let m;
      if ((m = v.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/))) {
        v = `${m[3]}-${m[2].padStart(2,'0')}-${m[1].padStart(2,'0')}`;
      }
      // if user typed iso with slashes, also normalize
      if ((m = v.match(/^(\d{4})\/(\d{1,2})\/(\d{1,2})$/))) {
        v = `${m[1]}-${m[2].padStart(2,'0')}-${m[3].padStart(2,'0')}`;
      }
      this.value = v;
    });
    effEl.addEventListener('input', () => effEl.setCustomValidity(''));
  }
});

// ===== Sidebar Toggle =====
function initSidebarToggle() {
  const toggleBtn = document.getElementById('apple-menu-btn');
  const sidebar = document.querySelector('.app-sidebar');
  
  if (!toggleBtn) {
    console.log('Toggle button not found');
    return;
  }
  if (!sidebar) {
    console.log('Sidebar not found');
    return;
  }
  
  // Create overlay for sidebar
  let overlay = document.querySelector('.sidebar-overlay');
  if (!overlay) {
    overlay = document.createElement('div');
    overlay.className = 'sidebar-overlay';
    document.body.appendChild(overlay);
  }
  
  // Close sidebar when clicking overlay
  overlay.addEventListener('click', closeSidebar);
  
  toggleBtn.addEventListener('click', (e) => {
    e.preventDefault();
    e.stopPropagation();
    
    const isOpen = sidebar.classList.contains('mobile-open') || document.body.classList.contains('sidebar-open');
    if (isOpen) {
      closeSidebar();
    } else {
      openSidebar();
    }
  });
  
  function openSidebar() {
    sidebar.classList.add('mobile-open');
    document.body.classList.add('sidebar-open');
    overlay.classList.add('active');
    toggleBtn.classList.add('active');
  }
  
  function closeSidebar() {
    sidebar.classList.remove('mobile-open');
    document.body.classList.remove('sidebar-open');
    overlay.classList.remove('active');
    toggleBtn.classList.remove('active');
  }
  
  // Close sidebar on escape key
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && (sidebar.classList.contains('mobile-open') || document.body.classList.contains('sidebar-open'))) {
      closeSidebar();
    }
  });
}

// Global functions for inline onclick handlers
function saveUtilityRates() {
  appleSettings.saveUtilityRates();
}

function deleteRate(rateId) {
  appleSettings.deleteRate(rateId);
}

function useRate(rateId) {
  appleSettings.useRate(rateId);
}

function showRateUsage(usageData) {
  appleSettings.showRateUsage(usageData);
}

function closeRateUsageModal() {
  appleSettings.closeRateUsageModal();
}

async function updateAllBillsRate() {
  const confirmed = await appleSettings.showConfirm(
    'ต้องการอัปเดตบิลทั้งหมดให้ใช้อัตราค่าน้ำค่าไฟปัจจุบันหรือไม่?\n\nระบบจะคำนวณค่าน้ำค่าไฟและยอดรวมใหม่ทุกบิล',
    'อัปเดตทุกบิล'
  );
  if (!confirmed) return;

  try {
    const response = await fetch('../Manage/update_all_expense_rates.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
    });

    const result = await response.json();
    if (result.success) {
      appleSettings.showToast(result.message, 'success');
      // Reload page after short delay to refresh rate usage counts
      setTimeout(() => location.reload(), 1500);
    } else {
      throw new Error(result.message || 'เกิดข้อผิดพลาด');
    }
  } catch (error) {
    appleSettings.showToast(error.message, 'error');
  }
}

function backupDatabase() {
  appleSettings.backupDatabase();
}

function downloadBackup(filename) {
  appleSettings.downloadBackup(filename);
}
