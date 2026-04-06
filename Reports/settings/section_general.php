<!-- Section: General Settings -->
<div class="apple-section-group">
  <h2 class="apple-section-title"><?php echo __('settings_general'); ?></h2>
  <div class="apple-section-card">
    <!-- Site Name -->
    <div class="apple-settings-row" data-sheet="sheet-sitename">
      <div class="apple-row-icon blue"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-animated"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg></div>
      <div class="apple-row-content">
        <p class="apple-row-label"><?php echo __('site_name'); ?></p>
      </div>
      <span class="apple-row-value" data-display="sitename"><?php echo htmlspecialchars($siteName); ?></span>
      <span class="apple-row-chevron">›</span>
    </div>
    
    <!-- Phone -->
    <div class="apple-settings-row" data-sheet="sheet-phone">
      <div class="apple-row-icon green"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-animated"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg></div>
      <div class="apple-row-content">
        <p class="apple-row-label"><?php echo __('contact_phone'); ?></p>
      </div>
      <span class="apple-row-value" data-display="phone"><?php echo htmlspecialchars($contactPhone); ?></span>
      <span class="apple-row-chevron">›</span>
    </div>
    
    <!-- Email -->
    <div class="apple-settings-row" data-sheet="sheet-email">
      <div class="apple-row-icon teal"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-animated"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg></div>
      <div class="apple-row-content">
        <p class="apple-row-label"><?php echo __('contact_email'); ?></p>
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
      <button class="apple-sheet-action" data-close-sheet="sheet-sitename"><?php echo __('cancel'); ?></button>
      <h3 class="apple-sheet-title"><?php echo __('site_name'); ?></h3>
      <div style="width: 50px;"></div>
    </div>
    <div class="apple-sheet-body">
      <form id="siteNameForm" method="post" action="" onsubmit="if (window.__siteNameSubmitHandler) { window.__siteNameSubmitHandler(event); } return false;">
        <div class="apple-input-group">
          <label class="apple-input-label"><?php echo __('name_label'); ?></label>
          <input type="text" id="siteName" name="site_name" class="apple-input" value="<?php echo htmlspecialchars($siteName); ?>" maxlength="100" required>
        </div>
        <button type="submit" id="saveSiteNameBtn" class="apple-button primary"><?php echo __('save'); ?></button>
      </form>
      <script>
      (function bindInlineSiteNameSubmit() {
        const form = document.getElementById('siteNameForm');
        const input = document.getElementById('siteName');
        const button = document.getElementById('saveSiteNameBtn');
        if (!form || !input || !button || form.dataset.inlineSiteNameBound === '1') {
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

        const submitSiteName = async (event) => {
          if (event) {
            event.preventDefault();
            event.stopPropagation();
            if (typeof event.stopImmediatePropagation === 'function') {
              event.stopImmediatePropagation();
            }
          }

          const siteName = input.value.trim();
          if (!siteName) {
            notify('กรุณากรอกชื่อหอพัก', 'error');
            input.focus();
            return false;
          }

          if (form.dataset.saving === '1') {
            return false;
          }

          form.dataset.saving = '1';
          button.disabled = true;

          try {
            const response = await fetch('/dormitory_management/Manage/save_system_settings.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
              body: `site_name=${encodeURIComponent(siteName)}`
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

            const displayEl = document.querySelector('[data-display="sitename"]');
            if (displayEl) {
              displayEl.textContent = siteName;
            }

            const profileNameEl = document.querySelector('.apple-profile-name');
            if (profileNameEl) {
              profileNameEl.textContent = siteName;
            }

            document.title = `${siteName} - จัดการระบบ`;

            const sheet = document.getElementById('sheet-sitename');
            if (sheet) {
              sheet.classList.remove('active');
              document.body.style.overflow = '';
            }

            notify('บันทึกชื่อหอพักสำเร็จ', 'success');
          } catch (error) {
            notify(error.message || 'เกิดข้อผิดพลาด', 'error');
          } finally {
            form.dataset.saving = '0';
            button.disabled = false;
          }

          return false;
        };

        window.__siteNameSubmitHandler = submitSiteName;
        form.dataset.inlineSiteNameBound = '1';
        form.addEventListener('submit', submitSiteName, true);
        button.addEventListener('click', submitSiteName, true);
      })();
      </script>
    </div>
  </div>
</div>

<!-- Sheet: Phone -->
<div class="apple-sheet-overlay" id="sheet-phone">
  <div class="apple-sheet">
    <div class="apple-sheet-handle"></div>
    <div class="apple-sheet-header">
      <button class="apple-sheet-action" data-close-sheet="sheet-phone"><?php echo __('cancel'); ?></button>
      <h3 class="apple-sheet-title"><?php echo __('contact_phone'); ?></h3>
      <div style="width: 50px;"></div>
    </div>
    <div class="apple-sheet-body">
      <form id="phoneForm" method="post" action="" onsubmit="if (window.__phoneSubmitHandler) { window.__phoneSubmitHandler(event); } return false;">
        <div class="apple-input-group">
          <label class="apple-input-label"><?php echo __('phone'); ?></label>
          <input type="tel" id="contactPhone" name="contact_phone" class="apple-input" value="<?php echo htmlspecialchars($contactPhone); ?>" inputmode="tel" maxlength="20" required>
          <p style="font-size: 13px; color: var(--apple-text-secondary); margin-top: 8px;">เช่น 089-565-6083</p>
        </div>
        <button type="submit" id="savePhoneBtn" class="apple-button primary"><?php echo __('save'); ?></button>
      </form>
      <script>
      (function bindInlinePhoneSubmit() {
        const form = document.getElementById('phoneForm');
        const input = document.getElementById('contactPhone');
        const button = document.getElementById('savePhoneBtn');
        if (!form || !input || !button || form.dataset.inlinePhoneBound === '1') {
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

        const isValidPhone = (value) => /^[0-9+\s()\-]{8,20}$/.test(value);

        const submitPhone = async (event) => {
          if (event) {
            event.preventDefault();
            event.stopPropagation();
            if (typeof event.stopImmediatePropagation === 'function') {
              event.stopImmediatePropagation();
            }
          }

          const phone = input.value.trim();
          if (!isValidPhone(phone)) {
            notify('รูปแบบเบอร์โทรไม่ถูกต้อง', 'error');
            input.focus();
            return false;
          }

          if (form.dataset.saving === '1') {
            return false;
          }

          form.dataset.saving = '1';
          button.disabled = true;

          try {
            const response = await fetch('/dormitory_management/Manage/save_system_settings.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
              body: `contact_phone=${encodeURIComponent(phone)}`
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

            const displayEl = document.querySelector('[data-display="phone"]');
            if (displayEl) {
              displayEl.textContent = phone;
            }

            const sheet = document.getElementById('sheet-phone');
            if (sheet) {
              sheet.classList.remove('active');
              document.body.style.overflow = '';
            }

            notify('บันทึกเบอร์โทรสำเร็จ', 'success');
          } catch (error) {
            notify(error.message || 'เกิดข้อผิดพลาด', 'error');
          } finally {
            form.dataset.saving = '0';
            button.disabled = false;
          }

          return false;
        };

        window.__phoneSubmitHandler = submitPhone;
        form.dataset.inlinePhoneBound = '1';
        form.addEventListener('submit', submitPhone, true);
        button.addEventListener('click', submitPhone, true);
      })();
      </script>
    </div>
  </div>
</div>

<!-- Sheet: Email -->
<div class="apple-sheet-overlay" id="sheet-email">
  <div class="apple-sheet">
    <div class="apple-sheet-handle"></div>
    <div class="apple-sheet-header">
      <button class="apple-sheet-action" data-close-sheet="sheet-email"><?php echo __('cancel'); ?></button>
      <h3 class="apple-sheet-title"><?php echo __('contact_email'); ?></h3>
      <div style="width: 50px;"></div>
    </div>
    <div class="apple-sheet-body">
      <form id="emailForm" method="post" action="" onsubmit="if (window.__emailSubmitHandler) { window.__emailSubmitHandler(event); } return false;">
        <div class="apple-input-group">
          <label class="apple-input-label"><?php echo __('email'); ?></label>
          <input type="email" id="contactEmail" name="contact_email" class="apple-input" value="<?php echo htmlspecialchars($contactEmail); ?>" maxlength="100" required>
        </div>
        <button type="submit" id="saveEmailBtn" class="apple-button primary"><?php echo __('save'); ?></button>
      </form>
      <script>
      (function bindInlineEmailSubmit() {
        const form = document.getElementById('emailForm');
        const input = document.getElementById('contactEmail');
        const button = document.getElementById('saveEmailBtn');
        if (!form || !input || !button || form.dataset.inlineEmailBound === '1') {
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

        const isValidEmail = (value) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);

        const submitEmail = async (event) => {
          if (event) {
            event.preventDefault();
            event.stopPropagation();
            if (typeof event.stopImmediatePropagation === 'function') {
              event.stopImmediatePropagation();
            }
          }

          const email = input.value.trim();
          if (!isValidEmail(email)) {
            notify('รูปแบบอีเมลไม่ถูกต้อง', 'error');
            input.focus();
            return false;
          }

          if (form.dataset.saving === '1') {
            return false;
          }

          form.dataset.saving = '1';
          button.disabled = true;

          try {
            const response = await fetch('/dormitory_management/Manage/save_system_settings.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
              body: `contact_email=${encodeURIComponent(email)}`
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

            const displayEl = document.querySelector('[data-display="email"]');
            if (displayEl) {
              displayEl.textContent = email;
            }

            const sheet = document.getElementById('sheet-email');
            if (sheet) {
              sheet.classList.remove('active');
              document.body.style.overflow = '';
            }

            notify('บันทึกอีเมลสำเร็จ', 'success');
          } catch (error) {
            notify(error.message || 'เกิดข้อผิดพลาด', 'error');
          } finally {
            form.dataset.saving = '0';
            button.disabled = false;
          }

          return false;
        };

        window.__emailSubmitHandler = submitEmail;
        form.dataset.inlineEmailBound = '1';
        form.addEventListener('submit', submitEmail, true);
        button.addEventListener('click', submitEmail, true);
      })();
      </script>
    </div>
  </div>
</div>

<!-- Section: Payment Information -->
<div class="apple-section-group">
  <h2 class="apple-section-title"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px;height:18px;vertical-align:-3px;margin-right:6px;"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg><?php echo __('bank_info'); ?></h2>
  <div class="apple-section-card">
    <!-- Bank Name -->
    <div class="apple-settings-row" data-sheet="sheet-bankname">
      <div class="apple-row-icon orange"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-animated"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg></div>
      <div class="apple-row-content">
        <p class="apple-row-label"><?php echo __('bank_name'); ?></p>
      </div>
      <span class="apple-row-value" data-display="bankname"><?php echo htmlspecialchars($bankName) ?: __('not_set'); ?></span>
      <span class="apple-row-chevron">›</span>
    </div>
    
    <!-- Bank Account Name -->
    <div class="apple-settings-row" data-sheet="sheet-bankaccountname">
      <div class="apple-row-icon purple"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-animated"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></div>
      <div class="apple-row-content">
        <p class="apple-row-label"><?php echo __('bank_account_name'); ?></p>
      </div>
      <span class="apple-row-value" data-display="bankaccountname"><?php echo htmlspecialchars($bankAccountName) ?: __('not_set'); ?></span>
      <span class="apple-row-chevron">›</span>
    </div>
    
    <!-- Bank Account Number -->
    <div class="apple-settings-row" data-sheet="sheet-bankaccountnumber">
      <div class="apple-row-icon blue"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-animated"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><path d="M22 6l-10 7L2 6"/></svg></div>
      <div class="apple-row-content">
        <p class="apple-row-label"><?php echo __('bank_account_number'); ?></p>
      </div>
      <span class="apple-row-value" data-display="bankaccountnumber"><?php echo htmlspecialchars($bankAccountNumber) ?: __('not_set'); ?></span>
      <span class="apple-row-chevron">›</span>
    </div>
    
    <!-- PromptPay -->
    <div class="apple-settings-row" data-sheet="sheet-promptpay">
      <div class="apple-row-icon green"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-animated"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg></div>
      <div class="apple-row-content">
        <p class="apple-row-label"><?php echo __('promptpay_number'); ?></p>
      </div>
      <span class="apple-row-value" data-display="promptpay"><?php echo htmlspecialchars($promptpayNumber) ?: __('not_set'); ?></span>
      <span class="apple-row-chevron">›</span>
    </div>
  </div>
  <p class="apple-section-hint" id="paymentInfoTenantHint"><?php echo __('payment_info_tenant_hint'); ?></p>
</div>

<!-- Sheet: Bank Name -->
<div class="apple-sheet-overlay" id="sheet-bankname">
  <div class="apple-sheet">
    <div class="apple-sheet-handle"></div>
    <div class="apple-sheet-header">
      <button class="apple-sheet-action" data-close-sheet="sheet-bankname">ยกเลิก</button>
      <h3 class="apple-sheet-title">ธนาคาร</h3>
      <div style="width: 50px;"></div>
    </div>
    <div class="apple-sheet-body">
      <form id="bankNameForm" method="post" action="" onsubmit="if (window.__bankNameSubmitHandler) { window.__bankNameSubmitHandler(event); } return false;">
        <div class="apple-input-group">
          <label class="apple-input-label">ชื่อธนาคาร</label>
          <select id="bankName" name="bank_name" class="apple-input">
            <option value="">-- เลือกธนาคาร --</option>
            <option value="ธนาคารกรุงเทพ" <?php echo $bankName === 'ธนาคารกรุงเทพ' ? 'selected' : ''; ?>>ธนาคารกรุงเทพ (BBL)</option>
            <option value="ธนาคารกสิกรไทย" <?php echo $bankName === 'ธนาคารกสิกรไทย' ? 'selected' : ''; ?>>ธนาคารกสิกรไทย (KBANK)</option>
            <option value="ธนาคารกรุงไทย" <?php echo $bankName === 'ธนาคารกรุงไทย' ? 'selected' : ''; ?>>ธนาคารกรุงไทย (KTB)</option>
            <option value="ธนาคารไทยพาณิชย์" <?php echo $bankName === 'ธนาคารไทยพาณิชย์' ? 'selected' : ''; ?>>ธนาคารไทยพาณิชย์ (SCB)</option>
            <option value="ธนาคารกรุงศรีอยุธยา" <?php echo $bankName === 'ธนาคารกรุงศรีอยุธยา' ? 'selected' : ''; ?>>ธนาคารกรุงศรีอยุธยา (BAY)</option>
            <option value="ธนาคารทหารไทยธนชาต" <?php echo $bankName === 'ธนาคารทหารไทยธนชาต' ? 'selected' : ''; ?>>ธนาคารทหารไทยธนชาต (TTB)</option>
            <option value="ธนาคารยูโอบี" <?php echo $bankName === 'ธนาคารยูโอบี' ? 'selected' : ''; ?>>ธนาคารยูโอบี (UOB)</option>
            <option value="ธนาคารเกียรตินาคินภัทร" <?php echo $bankName === 'ธนาคารเกียรตินาคินภัทร' ? 'selected' : ''; ?>>ธนาคารเกียรตินาคินภัทร (KKP)</option>
            <option value="ธนาคารซีไอเอ็มบี ไทย" <?php echo $bankName === 'ธนาคารซีไอเอ็มบี ไทย' ? 'selected' : ''; ?>>ธนาคารซีไอเอ็มบี ไทย (CIMBT)</option>
            <option value="ธนาคารแลนด์ แอนด์ เฮ้าส์" <?php echo $bankName === 'ธนาคารแลนด์ แอนด์ เฮ้าส์' ? 'selected' : ''; ?>>ธนาคารแลนด์ แอนด์ เฮ้าส์ (LH BANK)</option>
            <option value="ธนาคารไอซีบีซี (ไทย)" <?php echo $bankName === 'ธนาคารไอซีบีซี (ไทย)' ? 'selected' : ''; ?>>ธนาคารไอซีบีซี (ไทย) (ICBC)</option>
            <option value="ธนาคารไทยเครดิต" <?php echo $bankName === 'ธนาคารไทยเครดิต' ? 'selected' : ''; ?>>ธนาคารไทยเครดิต (CREDIT)</option>
            <option value="ธนาคารออมสิน" <?php echo $bankName === 'ธนาคารออมสิน' ? 'selected' : ''; ?>>ธนาคารออมสิน (GSB)</option>
            <option value="ธนาคารเพื่อการเกษตรและสหกรณ์การเกษตร" <?php echo ($bankName === 'ธนาคารเพื่อการเกษตรและสหกรณ์การเกษตร' || $bankName === 'ธนาคาร ธกส.') ? 'selected' : ''; ?>>ธนาคารเพื่อการเกษตรและสหกรณ์การเกษตร (BAAC)</option>
            <option value="ธนาคารอาคารสงเคราะห์" <?php echo $bankName === 'ธนาคารอาคารสงเคราะห์' ? 'selected' : ''; ?>>ธนาคารอาคารสงเคราะห์ (GHB)</option>
            <option value="ธนาคารเพื่อการส่งออกและนำเข้าแห่งประเทศไทย" <?php echo $bankName === 'ธนาคารเพื่อการส่งออกและนำเข้าแห่งประเทศไทย' ? 'selected' : ''; ?>>ธนาคารเพื่อการส่งออกและนำเข้าแห่งประเทศไทย (EXIM)</option>
            <option value="ธนาคารพัฒนาวิสาหกิจขนาดกลางและขนาดย่อมแห่งประเทศไทย" <?php echo $bankName === 'ธนาคารพัฒนาวิสาหกิจขนาดกลางและขนาดย่อมแห่งประเทศไทย' ? 'selected' : ''; ?>>ธนาคารพัฒนาวิสาหกิจขนาดกลางและขนาดย่อมแห่งประเทศไทย (SME D Bank)</option>
            <option value="ธนาคารอิสลามแห่งประเทศไทย" <?php echo $bankName === 'ธนาคารอิสลามแห่งประเทศไทย' ? 'selected' : ''; ?>>ธนาคารอิสลามแห่งประเทศไทย (isbank)</option>
          </select>
        </div>
        <button type="submit" id="saveBankNameBtn" class="apple-button primary">บันทึก</button>
      </form>
      <script>
      (function bindInlineBankNameSubmit() {
        const form = document.getElementById('bankNameForm');
        const select = document.getElementById('bankName');
        const button = document.getElementById('saveBankNameBtn');
        if (!form || !select || !button || form.dataset.inlineBankNameBound === '1') {
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

        const submitBankName = async (event) => {
          if (event) {
            event.preventDefault();
            event.stopPropagation();
            if (typeof event.stopImmediatePropagation === 'function') {
              event.stopImmediatePropagation();
            }
          }

          if (form.dataset.saving === '1') {
            return false;
          }

          form.dataset.saving = '1';
          button.disabled = true;

          const bankName = select.value.trim();

          try {
            const response = await fetch('/dormitory_management/Manage/save_system_settings.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
              body: `bank_name=${encodeURIComponent(bankName)}`
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

            const displayEl = document.querySelector('[data-display="bankname"]');
            if (displayEl) {
              displayEl.textContent = bankName || <?php echo json_encode(__('not_set'), JSON_UNESCAPED_UNICODE); ?>;
            }

            const sheet = document.getElementById('sheet-bankname');
            if (sheet) {
              sheet.classList.remove('active');
              document.body.style.overflow = '';
            }

            notify('บันทึกชื่อธนาคารสำเร็จ', 'success');
          } catch (error) {
            notify(error.message || 'เกิดข้อผิดพลาด', 'error');
          } finally {
            form.dataset.saving = '0';
            button.disabled = false;
          }

          return false;
        };

        window.__bankNameSubmitHandler = submitBankName;
        form.dataset.inlineBankNameBound = '1';
        form.addEventListener('submit', submitBankName, true);
        button.addEventListener('click', submitBankName, true);
      })();
      </script>
    </div>
  </div>
</div>

<!-- Sheet: Bank Account Name -->
<div class="apple-sheet-overlay" id="sheet-bankaccountname">
  <div class="apple-sheet">
    <div class="apple-sheet-handle"></div>
    <div class="apple-sheet-header">
      <button class="apple-sheet-action" data-close-sheet="sheet-bankaccountname">ยกเลิก</button>
      <h3 class="apple-sheet-title">ชื่อบัญชี</h3>
      <div style="width: 50px;"></div>
    </div>
    <div class="apple-sheet-body">
      <form id="bankAccountNameForm" method="post" action="" onsubmit="if (window.__bankAccountNameSubmitHandler) { window.__bankAccountNameSubmitHandler(event); } return false;">
        <div class="apple-input-group">
          <label class="apple-input-label">ชื่อบัญชี</label>
          <input type="text" id="bankAccountName" name="bank_account_name" class="apple-input" value="<?php echo htmlspecialchars($bankAccountName); ?>" maxlength="100" placeholder="เช่น นาย สมชาย ใจดี">
        </div>
        <button type="submit" id="saveBankAccountNameBtn" class="apple-button primary">บันทึก</button>
      </form>
      <script>
      (function bindInlineBankAccountNameSubmit() {
        const form = document.getElementById('bankAccountNameForm');
        const input = document.getElementById('bankAccountName');
        const button = document.getElementById('saveBankAccountNameBtn');
        if (!form || !input || !button || form.dataset.inlineBankAccountNameBound === '1') {
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

        const submitBankAccountName = async (event) => {
          if (event) {
            event.preventDefault();
            event.stopPropagation();
            if (typeof event.stopImmediatePropagation === 'function') {
              event.stopImmediatePropagation();
            }
          }

          if (form.dataset.saving === '1') {
            return false;
          }

          form.dataset.saving = '1';
          button.disabled = true;

          const bankAccountName = input.value.trim();

          try {
            const response = await fetch('/dormitory_management/Manage/save_system_settings.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
              body: `bank_account_name=${encodeURIComponent(bankAccountName)}`
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

            const displayEl = document.querySelector('[data-display="bankaccountname"]');
            if (displayEl) {
              displayEl.textContent = bankAccountName || <?php echo json_encode(__('not_set'), JSON_UNESCAPED_UNICODE); ?>;
            }

            const sheet = document.getElementById('sheet-bankaccountname');
            if (sheet) {
              sheet.classList.remove('active');
              document.body.style.overflow = '';
            }

            notify('บันทึกชื่อบัญชีสำเร็จ', 'success');
          } catch (error) {
            notify(error.message || 'เกิดข้อผิดพลาด', 'error');
          } finally {
            form.dataset.saving = '0';
            button.disabled = false;
          }

          return false;
        };

        window.__bankAccountNameSubmitHandler = submitBankAccountName;
        form.dataset.inlineBankAccountNameBound = '1';
        form.addEventListener('submit', submitBankAccountName, true);
        button.addEventListener('click', submitBankAccountName, true);
      })();
      </script>
    </div>
  </div>
</div>

<!-- Sheet: Bank Account Number -->
<div class="apple-sheet-overlay" id="sheet-bankaccountnumber">
  <div class="apple-sheet">
    <div class="apple-sheet-handle"></div>
    <div class="apple-sheet-header">
      <button class="apple-sheet-action" data-close-sheet="sheet-bankaccountnumber">ยกเลิก</button>
      <h3 class="apple-sheet-title">เลขบัญชี</h3>
      <div style="width: 50px;"></div>
    </div>
    <div class="apple-sheet-body">
      <form id="bankAccountNumberForm" method="post" action="" onsubmit="if (window.__bankAccountNumberSubmitHandler) { window.__bankAccountNumberSubmitHandler(event); } return false;">
        <div class="apple-input-group">
          <label class="apple-input-label">เลขบัญชีธนาคาร</label>
          <input type="text" id="bankAccountNumber" name="bank_account_number" class="apple-input" value="<?php echo htmlspecialchars($bankAccountNumber); ?>" maxlength="20" placeholder="เช่น 123-4-56789-0" pattern="[0-9\-]{10,20}">
        </div>
        <button type="submit" id="saveBankAccountNumberBtn" class="apple-button primary">บันทึก</button>
      </form>
      <script>
      (function bindInlineBankAccountNumberSubmit() {
        const form = document.getElementById('bankAccountNumberForm');
        const input = document.getElementById('bankAccountNumber');
        const button = document.getElementById('saveBankAccountNumberBtn');
        if (!form || !input || !button || form.dataset.inlineBankAccountNumberBound === '1') {
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

        const submitBankAccountNumber = async (event) => {
          if (event) {
            event.preventDefault();
            event.stopPropagation();
            if (typeof event.stopImmediatePropagation === 'function') {
              event.stopImmediatePropagation();
            }
          }

          if (form.dataset.saving === '1') {
            return false;
          }

          form.dataset.saving = '1';
          button.disabled = true;

          const bankAccountNumber = input.value.trim();

          try {
            const response = await fetch('/dormitory_management/Manage/save_system_settings.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
              body: `bank_account_number=${encodeURIComponent(bankAccountNumber)}`
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

            const displayEl = document.querySelector('[data-display="bankaccountnumber"]');
            if (displayEl) {
              displayEl.textContent = bankAccountNumber || <?php echo json_encode(__('not_set'), JSON_UNESCAPED_UNICODE); ?>;
            }

            const sheet = document.getElementById('sheet-bankaccountnumber');
            if (sheet) {
              sheet.classList.remove('active');
              document.body.style.overflow = '';
            }

            notify('บันทึกเลขบัญชีสำเร็จ', 'success');
          } catch (error) {
            notify(error.message || 'เกิดข้อผิดพลาด', 'error');
          } finally {
            form.dataset.saving = '0';
            button.disabled = false;
          }

          return false;
        };

        window.__bankAccountNumberSubmitHandler = submitBankAccountNumber;
        form.dataset.inlineBankAccountNumberBound = '1';
        form.addEventListener('submit', submitBankAccountNumber, true);
        button.addEventListener('click', submitBankAccountNumber, true);
      })();
      </script>
    </div>
  </div>
</div>

<!-- Sheet: PromptPay -->
<div class="apple-sheet-overlay" id="sheet-promptpay">
  <div class="apple-sheet">
    <div class="apple-sheet-handle"></div>
    <div class="apple-sheet-header">
      <button class="apple-sheet-action" data-close-sheet="sheet-promptpay">ยกเลิก</button>
      <h3 class="apple-sheet-title">พร้อมเพย์</h3>
      <div style="width: 50px;"></div>
    </div>
    <div class="apple-sheet-body">
      <form id="promptpayForm" method="post" action="" onsubmit="if (window.__promptpaySubmitHandler) { window.__promptpaySubmitHandler(event); } return false;">
        <div class="apple-input-group">
          <label class="apple-input-label">หมายเลขพร้อมเพย์</label>
          <input type="tel" id="promptpayNumber" name="promptpay_number" class="apple-input" value="<?php echo htmlspecialchars($promptpayNumber); ?>" inputmode="numeric" maxlength="13" placeholder="เบอร์โทรหรือเลขบัตรประชาชน">
          <p style="font-size: 13px; color: var(--apple-text-secondary); margin-top: 8px;">เช่น เบอร์โทร 089-565-6083 หรือเลข 13 หลัก</p>
        </div>
        <button type="submit" id="savePromptpayBtn" class="apple-button primary">บันทึก</button>
      </form>
      <script>
      (function bindInlinePromptpaySubmit() {
        const form = document.getElementById('promptpayForm');
        const input = document.getElementById('promptpayNumber');
        const button = document.getElementById('savePromptpayBtn');
        if (!form || !input || !button || form.dataset.inlinePromptpayBound === '1') {
          return;
        }

        const promptpayI18n = {
          invalidFormat: <?php echo json_encode(__('promptpay_invalid_format'), JSON_UNESCAPED_UNICODE); ?>,
          invalidResponse: <?php echo json_encode(__('invalid_server_response'), JSON_UNESCAPED_UNICODE); ?>,
          saveError: <?php echo json_encode(__('error_occurred'), JSON_UNESCAPED_UNICODE); ?>,
          notSet: <?php echo json_encode(__('not_set'), JSON_UNESCAPED_UNICODE); ?>,
          savedSuccess: <?php echo json_encode(__('promptpay_saved_success'), JSON_UNESCAPED_UNICODE); ?>
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

        const isValidPromptpay = (value) => {
          const digits = value.replace(/[^0-9]/g, '');
          return digits.length === 10 || digits.length === 13;
        };

        const submitPromptpay = async (event) => {
          if (event) {
            event.preventDefault();
            event.stopPropagation();
            if (typeof event.stopImmediatePropagation === 'function') {
              event.stopImmediatePropagation();
            }
          }

          if (form.dataset.saving === '1') {
            return false;
          }

          const promptpayNumber = input.value.trim();
          if (promptpayNumber !== '' && !isValidPromptpay(promptpayNumber)) {
            notify(promptpayI18n.invalidFormat, 'error');
            input.focus();
            return false;
          }

          form.dataset.saving = '1';
          button.disabled = true;

          try {
            const response = await fetch('/dormitory_management/Manage/save_system_settings.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
              body: `promptpay_number=${encodeURIComponent(promptpayNumber)}`
            });

            let result = null;
            try {
              result = await response.json();
            } catch (parseError) {
              throw new Error(promptpayI18n.invalidResponse);
            }

            if (!response.ok || !result || !result.success) {
              throw new Error((result && result.error) || promptpayI18n.saveError);
            }

            const displayEl = document.querySelector('[data-display="promptpay"]');
            if (displayEl) {
              displayEl.textContent = promptpayNumber || promptpayI18n.notSet;
            }

            const sheet = document.getElementById('sheet-promptpay');
            if (sheet) {
              sheet.classList.remove('active');
              document.body.style.overflow = '';
            }

            notify(promptpayI18n.savedSuccess, 'success');
          } catch (error) {
            notify(error.message || promptpayI18n.saveError, 'error');
          } finally {
            form.dataset.saving = '0';
            button.disabled = false;
          }

          return false;
        };

        window.__promptpaySubmitHandler = submitPromptpay;
        form.dataset.inlinePromptpayBound = '1';
        form.addEventListener('submit', submitPromptpay, true);
        button.addEventListener('click', submitPromptpay, true);
      })();
      </script>
    </div>
  </div>
</div>
