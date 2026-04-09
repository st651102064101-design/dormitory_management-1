
(function() {
  var quickLinks = Array.from(document.querySelectorAll('.quick-action-link'));
  if (!quickLinks.length) return;

  function normalizePath(value) {
    if (!value) return '';

    try {
      value = new URL(value, window.location.href).pathname || '';
    } catch (e) {
      value = String(value);
    }

    value = String(value).split('?')[0].split('#')[0].trim();
    if (!value) return '';

    value = value.replace(/\/+$/, '');
    if (!value) return '';

    return value;
  }

  function basename(value) {
    var normalized = normalizePath(value);
    if (!normalized) return '';

    var parts = normalized.split('/').filter(Boolean);
    return parts.length ? parts[parts.length - 1] : normalized;
  }

  var currentPath = normalizePath(window.location.pathname);
  var currentBase = basename(window.location.pathname);

  quickLinks.forEach(function(link) {
    var href = (link.getAttribute('href') || '').trim();
    var linkPath = normalizePath(href);
    var linkBase = basename(href);

    if (href && (
      (linkPath && linkPath === currentPath) ||
      (linkBase && linkBase === currentBase)
    )) {
      link.classList.add('active');
      link.setAttribute('aria-current', 'page');
    }
  });

  document.addEventListener('keydown', function(e) {
    var isCtrlPrimary = e.ctrlKey && !e.shiftKey && !e.altKey && !e.metaKey;
    var isCtrlShiftFallback = e.ctrlKey && e.shiftKey && !e.altKey && !e.metaKey;
    var isAltFallback = e.altKey && !e.metaKey;
    if (!isCtrlPrimary && !isCtrlShiftFallback && !isAltFallback) return;

    var active = document.activeElement;
    if (active && (active.tagName === 'INPUT' || active.tagName === 'TEXTAREA' || active.tagName === 'SELECT' || active.isContentEditable)) {
      return;
    }

    var codeMap = {
      'Digit1': 'manage_payments.php',
      'Digit2': 'manage_booking.php',
      'Digit3': 'manage_expenses.php',
      'Digit4': 'manage_contracts.php',
      'Digit5': 'tenant_wizard.php',
      'Numpad1': 'manage_payments.php',
      'Numpad2': 'manage_booking.php',
      'Numpad3': 'manage_expenses.php',
      'Numpad4': 'manage_contracts.php',
      'Numpad5': 'tenant_wizard.php'
    };

    var target = codeMap[e.code];
    if (!target) return;

    e.preventDefault();
    window.location.href = target;
  });
})();

// Drag-to-scroll for quick-actions nav
(function() {
  var nav = document.querySelector('.quick-actions');
  if (!nav) return;
  var isDown = false, startX, scrollLeft;
  nav.addEventListener('mousedown', function(e) {
    if (e.target.closest('a, button')) return;
    isDown = true;
    nav.style.cursor = 'grabbing';
    startX = e.pageX - nav.offsetLeft;
    scrollLeft = nav.scrollLeft;
  });
  document.addEventListener('mouseup', function() {
    isDown = false;
    if (nav) nav.style.cursor = 'grab';
  });
  nav.addEventListener('mousemove', function(e) {
    if (!isDown) return;
    e.preventDefault();
    var x = e.pageX - nav.offsetLeft;
    nav.scrollLeft = scrollLeft - (x - startX);
  });
  nav.addEventListener('mouseleave', function() {
    isDown = false;
    nav.style.cursor = 'grab';
  });
})();

// Drag-to-scroll for filter bars (all pages)
(function() {
  function initFilterDrag(el) {
    if (!el || el._dragInit) return;
    el._dragInit = true;
    var isDown = false, startX, scrollLeft;
    el.addEventListener('mousedown', function(e) {
      if (e.target.closest('a, button, input, select')) return;
      isDown = true;
      el.style.cursor = 'grabbing';
      startX = e.pageX - el.offsetLeft;
      scrollLeft = el.scrollLeft;
    });
    document.addEventListener('mouseup', function() {
      isDown = false;
      if (el) el.style.cursor = '';
    });
    el.addEventListener('mousemove', function(e) {
      if (!isDown) return;
      e.preventDefault();
      var x = e.pageX - el.offsetLeft;
      el.scrollLeft = scrollLeft - (x - startX);
    });
    el.addEventListener('mouseleave', function() {
      isDown = false;
      el.style.cursor = '';
    });
  }
  // Expose globally so pages can re-attach after AJAX refresh
  window.initFilterDrag = initFilterDrag;
  function attachFilterBars() {
    document.querySelectorAll('.ctr-filter-bar, .wiz-filter-bar, .expense-filter-tabs, .payment-filter-tabs, .info-bar').forEach(initFilterDrag);
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', attachFilterBars);
  } else {
    attachFilterBars();
  }
})();
