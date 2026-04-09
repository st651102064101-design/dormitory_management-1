
(function () {
  'use strict';
  var expiresInMs  = 1020000;
  var warnMs       = 960000;
  var loginUrl     = "Login.php?reason=timeout";
  var warned       = false;

  // ── Warning toast 60 s before expiry ──────────────────────────────────────
  if (warnMs > 0) {
    setTimeout(function () {
      if (warned) return;
      warned = true;
      var msg = 'เซสชันจะหมดอายุใน 1 นาที กรุณาบันทึกงานของคุณ';
      if (typeof AppleAlert !== 'undefined' && AppleAlert.show) {
        AppleAlert.show({ type: 'warning', title: '⏰ เซสชันใกล้หมดอายุ', message: msg, duration: 10000 });
      } else if (window.showAppleAlert) {
        showAppleAlert('warning', '⏰ เซสชันใกล้หมดอายุ', msg);
      } else {
        console.warn('[Session]', msg);
      }
    }, warnMs);
  }

  // ── Auto-redirect when expired ─────────────────────────────────────────────
  setTimeout(function () {
    window.location.href = loginUrl;
  }, expiresInMs);

  // ── Refresh last-activity on user interaction (debounced, max once/min) ────
  var lastPing = Date.now();
  function pingServer() {
    var now = Date.now();
    if (now - lastPing < 55000) return; // throttle to once per 55 s
    lastPing = now;
    fetch('Manage/ping_session.php', {
      method: 'POST', credentials: 'same-origin'
    }).then(function (r) {
      return r.ok ? r.json() : null;
    }).then(function (data) {
      if (data && data.remaining_ms > 0) {
        // Reset countdown with fresh expiry from server
        expiresInMs = data.remaining_ms;
        warned = false;
      } else if (data && data.expired) {
        window.location.href = loginUrl;
      }
    }).catch(function () {/* ignore */});
  }
  ['click', 'keydown', 'mousemove', 'touchstart'].forEach(function (evt) {
    document.addEventListener(evt, pingServer, { passive: true });
  });
})();
