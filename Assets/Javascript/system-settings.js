document.addEventListener('DOMContentLoaded', () => {
  // Helper function to update all background colors
  function applyThemeColorToDOM(color) {
    // วิธีที่ 1: Update CSS variable
    document.documentElement.style.setProperty('--theme-bg-color', color, 'important');
    
    // วิธีที่ 2: Update inline background color โดยตรง
    document.documentElement.style.backgroundColor = color;
    document.documentElement.style.background = color;
    document.body.style.backgroundColor = color;
    document.body.style.background = color;
    
    // วิธีที่ 3: Update ทุก elements ที่เกี่ยวข้อง
    const elementsToUpdate = [
      '.app-shell',
      '.app-main',
      '.reports-page'
    ];
    
    elementsToUpdate.forEach(selector => {
      const elements = document.querySelectorAll(selector);
      elements.forEach(el => {
        el.style.backgroundColor = color;
        el.style.background = color;
      });
    });
  }

  // โหลดรูปเก่าจากระบบ
  const oldLogoSelect = document.getElementById('oldLogoSelect');
  const oldLogoPreview = document.getElementById('oldLogoPreview');
  const loadOldLogoBtn = document.getElementById('loadOldLogoBtn');

  async function loadOldLogos() {
    try {
      const response = await fetch('../Manage/get_old_logos.php', {
        headers: {
          'X-Requested-With': 'XMLHttpRequest'
        }
      });
      const result = await response.json();
      if (result.success && result.files.length > 0) {
        const existing = new Set(Array.from(oldLogoSelect.options).map(opt => opt.value));
        result.files.forEach(file => {
          if (existing.has(file)) return;
          const option = document.createElement('option');
          option.value = file;
          option.textContent = file;
          oldLogoSelect.appendChild(option);
        });
      } else {
        showErrorToast(result.error || 'ไม่พบไฟล์เก่าในระบบ');
      }
    } catch (error) {
      console.error('Error loading old logos:', error);
      showErrorToast('โหลดรายชื่อรูปเก่าไม่สำเร็จ');
    }
  }

  if (oldLogoSelect) {
    loadOldLogos();
  }

  if (oldLogoSelect) {
    oldLogoSelect.addEventListener('change', function() {
      if (this.value) {
        oldLogoPreview.innerHTML = `<img src="../Assets/Images/${this.value}" alt="Old Logo" style="max-width: 150px; max-height: 150px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.3);" />`;
      } else {
        oldLogoPreview.innerHTML = '';
      }
    });
  }

  if (loadOldLogoBtn) {
    loadOldLogoBtn.addEventListener('click', async function(e) {
      e.preventDefault();
      const selectedFile = oldLogoSelect.value;
      if (!selectedFile) {
        showErrorToast('กรุณาเลือกรูปเก่า');
        return;
      }

      try {
        const formData = new FormData();
        formData.append('load_old_logo', selectedFile);

        const response = await fetch('../Manage/save_system_settings.php', {
          method: 'POST',
          body: formData,
          headers: {
            'X-Requested-With': 'XMLHttpRequest'
          }
        });

        const result = await response.json();
        if (result.success) {
          showSuccessToast('โหลดรูปเก่าสำเร็จ');
          const downloadLink = document.createElement('a');
          downloadLink.href = `../Assets/Images/${encodeURIComponent(selectedFile)}`;
          downloadLink.download = selectedFile;
          downloadLink.style.display = 'none';
          document.body.appendChild(downloadLink);
          downloadLink.click();
          document.body.removeChild(downloadLink);
        } else {
          showErrorToast(result.error || 'เกิดข้อผิดพลาด');
        }
      } catch (error) {
        console.error('Error:', error);
        showErrorToast('เกิดข้อผิดพลาด');
      }
    });
  }

  // Logo Upload
  const logoForm = document.getElementById('logoForm');
  const logoInput = document.getElementById('logoInput');
  const logoPreview = document.getElementById('logoPreview');
  const newLogoPreview = document.getElementById('newLogoPreview');
  const logoStatus = document.getElementById('logoStatus');

  logoInput?.addEventListener('change', function() {
    const file = this.files[0];
    if (file) {
      const reader = new FileReader();
      reader.onload = function(e) {
        newLogoPreview.innerHTML = `<img src="${e.target.result}" alt="New Logo" style="max-width: 150px; max-height: 150px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.3);" />`;
      };
      reader.readAsDataURL(file);
    }
  });

  logoForm?.addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(this);

    try {
      const response = await fetch('../Manage/save_system_settings.php', {
        method: 'POST',
        body: formData,
        headers: {
          'X-Requested-With': 'XMLHttpRequest'
        }
      });

      const result = await response.json();
      if (result.success) {
        showSuccessToast(result.message || 'บันทึก Logo สำเร็จ');
        logoStatus.textContent = '✓ บันทึกแล้ว';
        
        // อัปเดตรูป Logo ในหน้า
        if (logoInput.files && logoInput.files[0]) {
          const reader = new FileReader();
          reader.onload = function(e) {
            logoPreview.innerHTML = `<img src="${e.target.result}" alt="Logo" style="max-width: 200px; max-height: 200px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.3);" />`;
          };
          reader.readAsDataURL(logoInput.files[0]);
        }

        // รีเฟรช dropdown รูปเก่า
        await loadOldLogos();
        
        // อัปเดต Logo ใน sidebar (ถ้ามี)
        const sidebarLogo = document.querySelector('.team-avatar-img');
        if (sidebarLogo && result.filename) {
          sidebarLogo.src = `../Assets/Images/${result.filename}?t=${Date.now()}`;
        }
      } else {
        showErrorToast(result.error || 'เกิดข้อผิดพลาด');
        logoStatus.textContent = '✗ เกิดข้อผิดพลาด';
      }
    } catch (error) {
      showErrorToast('เกิดข้อผิดพลาด');
      logoStatus.textContent = '✗ เกิดข้อผิดพลาด';
    }
  });

  // Site Name Form
  const siteNameForm = document.getElementById('siteNameForm');
  const siteNameStatus = document.getElementById('siteNameStatus');

  siteNameForm?.addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(this);

    try {
      const response = await fetch('../Manage/save_system_settings.php', {
        method: 'POST',
        body: formData,
        headers: {
          'X-Requested-With': 'XMLHttpRequest'
        }
      });

      const result = await response.json();
      if (result.success) {
        showSuccessToast('บันทึกชื่อสำเร็จ');
        siteNameStatus.textContent = '✓ บันทึกแล้ว';
        
        // อัปเดตชื่อใน sidebar (ถ้ามี)
        const sidebarName = document.querySelector('.team-meta .name');
        if (sidebarName && result.site_name) {
          sidebarName.textContent = result.site_name;
        }
      } else {
        showErrorToast(result.error || 'เกิดข้อผิดพลาด');
        siteNameStatus.textContent = '✗ เกิดข้อผิดพลาด';
      }
    } catch (error) {
      showErrorToast('เกิดข้อผิดพลาด');
      siteNameStatus.textContent = '✗ เกิดข้อผิดพลาด';
    }
  });

  // Theme Color Form
  const themeColorForm = document.getElementById('themeColorForm');
  const themeColorInput = document.getElementById('themeColor');
  const colorPreview = document.getElementById('colorPreview');
  const colorStatus = document.getElementById('colorStatus');
  const quickColorBtns = document.querySelectorAll('.quick-color');

  themeColorInput?.addEventListener('input', function() {
    const color = this.value;
    colorPreview.style.background = color;
    colorPreview.textContent = color;

    // Apply theme immediately
    applyThemeColorToDOM(color);
    document.body.classList.add('theme-softfade');
    
    const brightness = (() => {
      const hex = color.replace('#','');
      if (hex.length !== 6) return 0;
      const r = parseInt(hex.slice(0,2), 16);
      const g = parseInt(hex.slice(2,4), 16);
      const b = parseInt(hex.slice(4,6), 16);
      return ((r * 299) + (g * 587) + (b * 114)) / 1000;
    })();
    
    if (brightness > 155) {
      document.body.classList.add('live-light');
    } else {
      document.body.classList.remove('live-light');
    }
  });

  themeColorInput?.addEventListener('change', async function() {
    const color = this.value;
    console.log('Color picker change event:', color);

    const formData = new FormData();
    formData.append('theme_color', color);
    
    try {
      const response = await fetch('../Manage/save_system_settings.php', {
        method: 'POST',
        body: formData,
        headers: {
          'X-Requested-With': 'XMLHttpRequest'
        }
      });
      
      if (response.ok) {
        const result = await response.json();
        if (result.success) {
          showSuccessToast('เปลี่ยนสีสำเร็จ');
          setTimeout(() => document.body.classList.remove('theme-softfade'), 700);
        } else {
          showErrorToast(result.message || 'เกิดข้อผิดพลาด');
        }
      }
    } catch (error) {
      showErrorToast('เกิดข้อผิดพลาด: ' + error.message);
    }
  });

  quickColorBtns.forEach(btn => {
    btn.addEventListener('click', async function(e) {
      e.preventDefault();
      const color = this.dataset.color;
      
      console.log('Quick color button clicked with color:', color);
      
      if (!color) return;
      
      themeColorInput.value = color;
      colorPreview.style.background = color;
      colorPreview.textContent = color;

      // Apply theme immediately (ไม่ต้องรีหน้า)
      applyThemeColorToDOM(color);
      
      // บังคับให้เบราว์เซอร์ recompute styles
      void document.body.offsetHeight;
      
      document.body.classList.add('theme-softfade');
      
      const brightness = (() => {
        const hex = color.replace('#','');
        if (hex.length !== 6) return 0;
        const r = parseInt(hex.slice(0,2), 16);
        const g = parseInt(hex.slice(2,4), 16);
        const b = parseInt(hex.slice(4,6), 16);
        return ((r * 299) + (g * 587) + (b * 114)) / 1000;
      })();
      if (brightness > 155) {
        document.body.classList.add('live-light');
      } else {
        document.body.classList.remove('live-light');
      }

      // บันทึกสีทันที
      const formData = new FormData();
      formData.append('theme_color', color);
      
      try {
        const response = await fetch('../Manage/save_system_settings.php', {
          method: 'POST',
          body: formData,
          headers: {
            'X-Requested-With': 'XMLHttpRequest'
          }
        });
        
        if (response.ok) {
          const result = await response.json();
          if (result.success) {
            showSuccessToast(result.message || 'บันทึกสีสำเร็จ');
            setTimeout(() => document.body.classList.remove('theme-softfade'), 700);
          } else {
            showErrorToast(result.message || 'เกิดข้อผิดพลาด');
          }
        }
      } catch (error) {
        showErrorToast('เกิดข้อผิดพลาด: ' + error.message);
      }
    });
  });

  themeColorForm?.addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(this);

    try {
      const response = await fetch('../Manage/save_system_settings.php', {
        method: 'POST',
        body: formData,
        headers: {
          'X-Requested-With': 'XMLHttpRequest'
        }
      });

      const result = await response.json();
      if (result.success) {
        showSuccessToast('บันทึกสีสำเร็จ');
        colorStatus.textContent = '✓ บันทึกแล้ว';
      } else {
        showErrorToast(result.error || 'เกิดข้อผิดพลาด');
        colorStatus.textContent = '✗ เกิดข้อผิดพลาด';
      }
    } catch (error) {
      showErrorToast('เกิดข้อผิดพลาด');
      colorStatus.textContent = '✗ เกิดข้อผิดพลาด';
    }
  });

  // Font Size Form
  const fontSizeForm = document.getElementById('fontSizeForm');
  const fontSizeSelect = document.getElementById('fontSize');
  const fontStatus = document.getElementById('fontStatus');

  fontSizeSelect?.addEventListener('change', async function() {
    const preview = fontSizeForm.querySelector('.font-size-preview');
    const newSize = this.value;
    preview.style.fontSize = 'calc(1rem * ' + newSize + ')';
    
    // บันทึกทันทีและใช้งานโดยไม่รีเฟรช
    const formData = new FormData();
    formData.append('font_size', newSize);
    
    try {
      const response = await fetch('../Manage/save_system_settings.php', {
        method: 'POST',
        body: formData,
        headers: {
          'X-Requested-With': 'XMLHttpRequest'
        }
      });

      const result = await response.json();
      if (result.success) {
        showSuccessToast('ปรับขนาดข้อความสำเร็จ');
        fontStatus.textContent = '✓ บันทึกแล้ว';
        
        // ใช้งานทันทีโดยไม่รีเฟรช - ตั้ง CSS variable และปรับ html font-size
        document.documentElement.style.setProperty('--font-scale', newSize);
        document.documentElement.style.fontSize = 'calc(16px * ' + newSize + ')';
      } else {
        showErrorToast(result.error || 'เกิดข้อผิดพลาด');
        fontStatus.textContent = '✗ เกิดข้อผิดพลาด';
      }
    } catch (error) {
      showErrorToast('เกิดข้อผิดพลาด');
      fontStatus.textContent = '✗ เกิดข้อผิดพลาด';
    }
  });

  fontSizeForm?.addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(this);

    try {
      const response = await fetch('../Manage/save_system_settings.php', {
        method: 'POST',
        body: formData,
        headers: {
          'X-Requested-With': 'XMLHttpRequest'
        }
      });

      const result = await response.json();
      if (result.success) {
        showSuccessToast('บันทึกขนาดข้อความสำเร็จ');
        fontStatus.textContent = '✓ บันทึกแล้ว';
        
        // ใช้งานทันทีโดยไม่รีเฟรช
        const newSize = fontSizeSelect.value;
        document.documentElement.style.setProperty('--font-scale', newSize);
        document.documentElement.style.fontSize = 'calc(16px * ' + newSize + ')';
      } else {
        showErrorToast(result.error || 'เกิดข้อผิดพลาด');
        fontStatus.textContent = '✗ เกิดข้อผิดพลาด';
      }
    } catch (error) {
      showErrorToast('เกิดข้อผิดพลาด');
      fontStatus.textContent = '✗ เกิดข้อผิดพลาด';
    }
  });

  // Backup Button
  const backupBtn = document.getElementById('backupBtn');
  const backupStatus = document.getElementById('backupStatus');

  backupBtn?.addEventListener('click', async function(e) {
    e.preventDefault();

    const confirmed = await showConfirmDialog('ยืนยันการสำรองข้อมูล', 'คุณต้องการสำรองข้อมูลฐานข้อมูลหรือไม่?', 'warning');
    if (!confirmed) {
      return;
    }

    backupBtn.disabled = true;
    backupBtn.textContent = '⏳ กำลังสำรอง...';

    try {
      const response = await fetch('../Manage/backup_database.php', {
        method: 'POST',
        headers: {
          'X-Requested-With': 'XMLHttpRequest'
        }
      });

      const result = await response.json();

      if (result.success) {
        showSuccessToast('สำรองข้อมูลสำเร็จ');
        backupStatus.textContent = '✓ สำรองแล้ว';
        
        // Download file
        setTimeout(() => {
          const link = document.createElement('a');
          link.href = result.file;
          link.download = result.filename;
          link.style.display = 'none';
          document.body.appendChild(link);
          link.click();
          document.body.removeChild(link);
        }, 500);
      } else {
        showErrorToast(result.error || 'เกิดข้อผิดพลาด');
        backupStatus.textContent = '✗ ' + (result.error || 'เกิดข้อผิดพลาด');
      }
    } catch (error) {
      showErrorToast('เกิดข้อผิดพลาด: ' + error.message);
      backupStatus.textContent = '✗ ' + error.message;
    } finally {
      backupBtn.disabled = false;
      backupBtn.textContent = '💾 สำรองข้อมูล';
    }
  });
});
