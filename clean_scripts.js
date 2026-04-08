
    if (typeof window.openRatesSheetFromRow !== 'function') {
      window.openRatesSheetFromRow = function(event, rowId) {
        if (event && event.type === 'keydown' && event.key && event.key !== 'Enter' && event.key !== ' ') {
          return true;
        }

        if (event && typeof event.preventDefault === 'function') {
          event.preventDefault();
        }

        var overlay = document.getElementById('sheet-rates');
        
        // Debugging info explicitly surfaced to user/console
        console.log('[DEBUG] openRatesSheetFromRow executed for row:', rowId);
        if (!overlay) {
            console.error('[DEBUG] overlay sheet-rates missing from DOM');
            if (typeof window.appleToast === 'function') {
                window.appleToast('Error: Sheet not found.', 'error');
            } else {
                alert('Error: ไม่พบหน้าต่าง (Sheet missing)');
            }
        }
        
        if (!overlay && typeof window.ensureRatesSheetFallback === 'function') {
          overlay = window.ensureRatesSheetFallback();
        }

        if (overlay && overlay.parentNode !== document.body && document.body) {
          document.body.appendChild(overlay);
        }

        if (overlay) {
          overlay.classList.add('active');
          document.body.style.overflow = 'hidden';
          if (typeof window.refreshSheetHandleDragBindings === 'function') {
            window.refreshSheetHandleDragBindings();
          }
          return true;
        }

        return false;
      };
    }

    if (typeof window.handleRatesRowKeydown !== 'function') {
      window.handleRatesRowKeydown = function(event, rowId) {
        if (!event || (event.key !== 'Enter' && event.key !== ' ')) {
          return true;
        }

        return window.openRatesSheetFromRow ? window.openRatesSheetFromRow(event, rowId) ? false : true : true;
      };
    }
    

const ratesSheetI18n = {
  title: "PHP_REPLACED",
  done: "PHP_REPLACED"
};

function closeSheetOverlayByElement(overlay) {
  if (!overlay) {
    return;
  }

  overlay.classList.remove('active');
  if (!document.querySelector('.apple-sheet-overlay.active')) {
    document.body.style.overflow = '';
  }
}

function bindSheetHandleDragClose(sheetId) {
  var overlay = document.getElementById(sheetId);
  if (!overlay) return;
  var sheet = overlay.querySelector('.apple-sheet');
  if (!sheet) return;

  var dragTargets = [
    overlay.querySelector('.apple-sheet-handle'),
    overlay.querySelector('.apple-sheet-header')
  ].filter(Boolean);

  if (dragTargets.length === 0 || sheet.dataset.dragCloseFallbackBound === '1') {
    return;
  }
  sheet.dataset.dragCloseFallbackBound = '1';

  var startY = 0, deltaY = 0, dragging = false;

  function getCloseThreshold() {
    var height = sheet.getBoundingClientRect().height || sheet.offsetHeight || 0;
    return height > 0 ? Math.round(height * 0.35) : 72;
  }

  function beginDrag(clientY) {
    if (!overlay.classList.contains('active')) return false;
    startY = clientY;
    deltaY = 0;
    dragging = true;
    sheet.style.transition = 'none';
    sheet.style.willChange = 'transform';
    return true;
  }

  function updateDrag(clientY) {
    if (!dragging) return;
    deltaY = Math.max(0, clientY - startY);
    sheet.style.transform = 'translateY(' + deltaY + 'px)';
  }

  function finishDrag() {
    if (!dragging) return;
    var shouldClose = deltaY >= getCloseThreshold();
    dragging = false;
    sheet.style.willChange = '';
    sheet.style.transition = 'transform 0.25s cubic-bezier(0.32, 0.72, 0, 1)';
    
    if (shouldClose) {
      sheet.style.transform = 'translateY(100%)';
      setTimeout(function() {
        if (typeof closeSheetOverlayByElement !== 'undefined') {
            closeSheetOverlayByElement(overlay);
        } else {
            overlay.classList.remove('active');
            if (!document.querySelector('.apple-sheet-overlay.active')) document.body.style.overflow = '';
        }
        sheet.style.transform = '';
      }, 250);
    } else {
      sheet.style.transform = 'translateY(0)';
      setTimeout(function() { sheet.style.transform = ''; }, 250);
    }
  }

  dragTargets.forEach(function(target) {
    target.style.touchAction = 'none';
    
    var onMouseMove = function(e) { updateDrag(e.clientY); };
    var onMouseUp = function() {
      document.removeEventListener('mousemove', onMouseMove);
      document.removeEventListener('mouseup', onMouseUp);
      finishDrag();
    };

    target.addEventListener('mousedown', function(e) {
      if (e.target.closest && e.target.closest('button, a, input, select, textarea, [data-close-sheet]')) return;
      if (e.button !== 0) return;
      if (beginDrag(e.clientY)) {
        e.preventDefault();
        document.addEventListener('mousemove', onMouseMove);
        document.addEventListener('mouseup', onMouseUp);
      }
    });

    target.addEventListener('touchstart', function(e) {
      if (e.target.closest && e.target.closest('button, a, input, select, textarea, [data-close-sheet]')) return;
      if (!e.touches || !e.touches.length) return;
      beginDrag(e.touches[0].clientY);
    }, { passive: true });

    target.addEventListener('touchmove', function(e) {
      if (!dragging || !e.touches || !e.touches.length) return;
      var curY = e.touches[0].clientY;
      if (curY > startY) e.preventDefault();
      updateDrag(curY);
    }, { passive: false });

    target.addEventListener('touchend', finishDrag);
    target.addEventListener('touchcancel', finishDrag);
  });
}

function refreshSheetHandleDragBindings() {
  bindSheetHandleDragClose('sheet-rates');
  bindSheetHandleDragClose('sheet-billing-schedule');

  if (window.appleSheetComponent && typeof window.appleSheetComponent.refresh === 'function') {
    window.appleSheetComponent.refresh();
  }
}

window.refreshSheetHandleDragBindings = refreshSheetHandleDragBindings;

(function initRatesSettings() {
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', refreshSheetHandleDragBindings);
  } else {
    refreshSheetHandleDragBindings();
  }
})();

function ensureRatesSheetFallback() {
  var existingOverlay = document.getElementById('sheet-rates');
  if (existingOverlay) {
    refreshSheetHandleDragBindings();
    return existingOverlay;
  }

  var overlay = document.createElement('div');
  overlay.className = 'apple-sheet-overlay';
  overlay.id = 'sheet-rates';
  overlay.innerHTML = `
    <div class="apple-sheet">
      <div class="apple-sheet-handle"></div>
      <div class="apple-sheet-header">
        <button type="button" class="apple-sheet-action" data-close-sheet="sheet-rates">${escapeBillingSheetText(ratesSheetI18n.done)}</button>
        <h3 class="apple-sheet-title">${escapeBillingSheetText(ratesSheetI18n.title)}</h3>
        <div style="width: 50px;"></div>
      </div>
      <div class="apple-sheet-body">
        <p style="font-size: 14px; color: var(--apple-text-secondary); margin: 0;">ไม่พบข้อมูลอัตราค่าน้ำค่าไฟของหน้านี้ กรุณารีเฟรชหน้าอีกครั้ง</p>
      </div>
    </div>
  `;

  document.body.appendChild(overlay);
  refreshSheetHandleDragBindings();
  console.warn('[SheetDebug] Injected fallback overlay for missing sheet-rates');
  return overlay;
}

if (typeof window.openManageRatesSheetFromRow !== 'function') {
  window.openManageRatesSheetFromRow = function(event) {
    return window.openRatesSheetFromRow ? window.openRatesSheetFromRow(event, 'manageRatesRow') ? false : true : true;
  };
}

if (typeof window.handleRatesRowKeydown !== 'function') {
  window.handleRatesRowKeydown = function(event, rowId) {
    if (!event || (event.key !== 'Enter' && event.key !== ' ')) {
      return true;
    }

    return window.openRatesSheetFromRow ? window.openRatesSheetFromRow(event, rowId) ? false : true : true;
  };
}

const billingScheduleI18n = {
  title: "PHP_REPLACED",
  generatePrefix: "PHP_REPLACED",
  duePrefix: "PHP_REPLACED",
  done: "PHP_REPLACED",
  save: "PHP_REPLACED",
  savedSuccess: "PHP_REPLACED",
  saveError: "PHP_REPLACED"
};

const billingScheduleDefaults = {
  generateDay: "PHP_REPLACED",
  dueDay: "PHP_REPLACED"
};

function escapeBillingSheetText(value) {
  return String(value || '').replace(/[&<>"']/g, function(ch) {
    return {
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#39;'
    }[ch] || ch;
  });
}

function ensureBillingScheduleSheetFallback() {
  var existingOverlay = document.getElementById('sheet-billing-schedule');
  if (existingOverlay) {
    refreshSheetHandleDragBindings();
    return existingOverlay;
  }

  var overlay = document.createElement('div');
  overlay.className = 'apple-sheet-overlay';
  overlay.id = 'sheet-billing-schedule';
  overlay.innerHTML = `
    <div class="apple-sheet">
      <div class="apple-sheet-handle"></div>
      <div class="apple-sheet-header">
        <button type="button" class="apple-sheet-action" data-close-sheet="sheet-billing-schedule">${escapeBillingSheetText(billingScheduleI18n.done)}</button>
        <h3 class="apple-sheet-title">${escapeBillingSheetText(billingScheduleI18n.title)}</h3>
        <div style="width: 50px;"></div>
      </div>
      <div class="apple-sheet-body">
        <div class="apple-input-group" style="margin-bottom: 12px;">
          <label class="apple-input-label">${escapeBillingSheetText(billingScheduleI18n.generatePrefix)} (1-28)</label>
          <input type="number" id="billingGenerateDay" class="apple-input" value="${billingScheduleDefaults.generateDay}" min="1" max="28" step="1">
        </div>
        <div class="apple-input-group" style="margin-bottom: 16px;">
          <label class="apple-input-label">${escapeBillingSheetText(billingScheduleI18n.duePrefix)} (1-28)</label>
          <input type="number" id="paymentDueDay" class="apple-input" value="${billingScheduleDefaults.dueDay}" min="1" max="28" step="1">
        </div>
        <button type="button" class="apple-button primary" style="width: 100%;" onclick="saveBillingSchedule()">${escapeBillingSheetText(billingScheduleI18n.save)}</button>
      </div>
    </div>
  `;

  document.body.appendChild(overlay);
  refreshSheetHandleDragBindings();
  console.warn('[SheetDebug] Injected fallback overlay for missing sheet-billing-schedule');

  return overlay;
}

window.ensureBillingScheduleSheetFallback = ensureBillingScheduleSheetFallback;

if (!document.getElementById('sheet-billing-schedule')) {
  ensureBillingScheduleSheetFallback();
}

// Billing Generate Day - live preview
document.getElementById('billingGenerateDay')?.addEventListener('input', function() {
  const val = Math.max(1, Math.min(28, parseInt(this.value) || 1));
  const exEl = document.getElementById('genDayExample');
  const dateEl = document.getElementById('genDateExample');
  const beforeEl = document.getElementById('genDayBefore');
  if (exEl) exEl.textContent = val;
  if (dateEl) dateEl.textContent = val + ' เม.ย. 2569';
  if (beforeEl) beforeEl.textContent = val;
});

// Payment Due Day - live preview
document.getElementById('paymentDueDay')?.addEventListener('input', function() {
  const val = Math.max(1, Math.min(28, parseInt(this.value) || 5));
  const exEl = document.getElementById('dueDayExample');
  const dateEl = document.getElementById('dueDateExample');
  if (exEl) exEl.textContent = val;
  if (dateEl) dateEl.textContent = val + ' มี.ค. 2569';
});

// Save both settings at once
function saveBillingSchedule() {
  const genDay = Math.max(1, Math.min(28, parseInt(document.getElementById('billingGenerateDay').value) || 1));
  const dueDay = Math.max(1, Math.min(28, parseInt(document.getElementById('paymentDueDay').value) || 5));

  // Save billing_generate_day
  const fd1 = new FormData();
  fd1.append('billing_generate_day', genDay);

  // Save payment_due_day
  const fd2 = new FormData();
  fd2.append('payment_due_day', dueDay);

  Promise.all([
    fetch('../Manage/save_system_settings.php', { method: 'POST', body: fd1 }).then(r => r.json()),
    fetch('../Manage/save_system_settings.php', { method: 'POST', body: fd2 }).then(r => r.json())
  ])
  .then(([res1, res2]) => {
    if (res1.success && res2.success) {
      // Update sublabel on main settings page
      const sublabel = document.getElementById('billingScheduleSublabel');
      if (sublabel) {
        sublabel.textContent = `${billingScheduleI18n.generatePrefix} ${genDay} · ${billingScheduleI18n.duePrefix} ${dueDay}`;
      }

      if (typeof appleToast === 'function') {
        appleToast(billingScheduleI18n.savedSuccess, 'success');
      } else {
        alert(billingScheduleI18n.savedSuccess);
      }
    } else {
      alert(res1.error || res2.error || billingScheduleI18n.saveError);
    }
  })
  .catch(() => alert(billingScheduleI18n.saveError));
}

