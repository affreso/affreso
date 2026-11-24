/* Affreso — small progressive enhancements. Every page works without JavaScript. */
(function () {
  'use strict';
  document.addEventListener('click', function (e) {
    var t = e.target.closest('[data-toggle="sidebar"]');
    if (t) { document.getElementById('sidebar').classList.toggle('open'); }
    var c = e.target.closest('[data-confirm]');
    if (c && !window.confirm(c.getAttribute('data-confirm'))) { e.preventDefault(); }
  });
  document.addEventListener('submit', function (e) {
    var f = e.target;
    if (f.hasAttribute('data-confirm') && !window.confirm(f.getAttribute('data-confirm'))) { e.preventDefault(); }
  });
  // Show/hide lesson fields by type.
  document.querySelectorAll('[data-lesson-type]').forEach(function (sel) {
    var form = sel.closest('form');
    function sync() {
      form.querySelectorAll('[data-show-for]').forEach(function (el) {
        el.hidden = el.getAttribute('data-show-for').split(' ').indexOf(sel.value) === -1;
      });
    }
    sel.addEventListener('change', sync); sync();
  });
  // Check / uncheck all in a group.
  document.querySelectorAll('[data-check-all]').forEach(function (box) {
    box.addEventListener('change', function () {
      document.querySelectorAll('input[name="' + box.getAttribute('data-check-all') + '"]').forEach(function (b) { b.checked = box.checked; });
    });
  });
  // Messaging: character counter for SMS.
  var body = document.getElementById('msg-body'), counter = document.getElementById('sms-count');
  if (body && counter) {
    var upd = function () { var n = body.value.length; counter.textContent = n + ' characters · ' + Math.max(1, Math.ceil(n / 153)) + ' SMS part(s)'; };
    body.addEventListener('input', upd); upd();
  }
  var channel = document.getElementById('msg-channel');
  if (channel) {
    var syncCh = function () { document.querySelectorAll('[data-email-only]').forEach(function (el) { el.hidden = channel.value !== 'email'; }); };
    channel.addEventListener('change', syncCh); syncCh();
  }
  // Render MFA QR code locally (secret never leaves this server).
  var qr = document.getElementById('mfa-qr');
  if (qr && window.qrcode) {
    var q = window.qrcode(0, 'M'); q.addData(qr.getAttribute('data-uri')); q.make();
    qr.innerHTML = q.createSvgTag({ cellSize: 5, margin: 2, scalable: true });
  }
  document.querySelectorAll('[data-reveal]').forEach(function (sel) {
    var scope = sel.closest('[data-reveal-group]') || sel.form || document;
    var sync = function () {
      scope.querySelectorAll('[data-reveal-for]').forEach(function (el) {
        el.hidden = el.getAttribute('data-reveal-for').split(' ').indexOf(sel.value) === -1;
      });
    };
    sel.addEventListener('change', sync); sync();
  });
  document.querySelectorAll('[data-keypad]').forEach(function (pad) {
    var input = document.getElementById(pad.getAttribute('data-keypad'));
    pad.addEventListener('click', function (e) {
      var b = e.target.closest('[data-key]'); if (!b) { return; }
      var k = b.getAttribute('data-key');
      input.value = k === 'clear' ? '' : (k === 'back' ? input.value.slice(0, -1) : (input.value + k).slice(0, 20));
    });
  });
  document.querySelectorAll('[data-fullscreen]').forEach(function (b) {
    b.addEventListener('click', function () { var d = document.documentElement; (d.requestFullscreen || d.webkitRequestFullscreen || function () {}).call(d); });
  });
  var idle = document.body.getAttribute('data-idle');
  if (idle) {
    var timer, resetIdle = function () { window.clearTimeout(timer); timer = window.setTimeout(function () { window.location.href = document.body.getAttribute('data-idle-url'); }, parseInt(idle, 10) * 1000); };
    ['pointerdown', 'keydown', 'touchstart'].forEach(function (ev) { document.addEventListener(ev, resetIdle, { passive: true }); });
    resetIdle();
  }
  if (document.querySelector('[data-autoprint]')) { window.setTimeout(function () { window.print(); }, 400); }
  document.querySelectorAll('[data-autosubmit]').forEach(function (s) { s.addEventListener('change', function () { s.form.submit(); }); });
  // Ticket quantities on a form: keep the total (and the instalment amounts) up to date.
  document.querySelectorAll('[data-tickets]').forEach(function (box) {
    var cur = box.getAttribute('data-currency') || 'AUD';
    var fmt = function (c) { try { return new Intl.NumberFormat(document.documentElement.lang || 'en-AU', { style: 'currency', currency: cur, currencyDisplay: 'narrowSymbol' }).format(c / 100); } catch (e) { return '$' + (c / 100).toFixed(2); } };
    var form = box.closest('form');
    var calc = function () {
      var total = 0;
      box.querySelectorAll('input[data-price]').forEach(function (i) { total += Math.max(0, parseInt(i.value || '0', 10) || 0) * parseInt(i.getAttribute('data-price'), 10); });
      var t = form.querySelector('[data-total]'); if (t) { t.textContent = fmt(total); }
      form.querySelectorAll('[data-each]').forEach(function (s) { var n = parseInt(s.getAttribute('data-each'), 10); s.textContent = fmt(Math.ceil(total / n)); });
    };
    box.addEventListener('input', calc); calc();
  });
  // Print buttons.
  document.querySelectorAll('[data-print]').forEach(function (b) { b.addEventListener('click', function () { window.print(); }); });
})();
/* Signature pad (library e-signatures) */
(function () {
  document.querySelectorAll('[data-signature-form]').forEach(function (form) {
    var c = form.querySelector('canvas.sig-pad'), out = form.querySelector('input[name="signature"]');
    if (!c) return;
    var ctx = c.getContext('2d'), drawing = false, used = false;
    ctx.lineWidth = 2.2; ctx.lineCap = 'round'; ctx.strokeStyle = '#1f2a2e';
    function pos(e) { var r = c.getBoundingClientRect(), t = e.touches ? e.touches[0] : e; return [(t.clientX - r.left) * c.width / r.width, (t.clientY - r.top) * c.height / r.height]; }
    function start(e) { drawing = true; used = true; var p = pos(e); ctx.beginPath(); ctx.moveTo(p[0], p[1]); e.preventDefault(); }
    function move(e) { if (!drawing) return; var p = pos(e); ctx.lineTo(p[0], p[1]); ctx.stroke(); e.preventDefault(); }
    function end() { drawing = false; }
    c.addEventListener('mousedown', start); c.addEventListener('mousemove', move); window.addEventListener('mouseup', end);
    c.addEventListener('touchstart', start, { passive: false }); c.addEventListener('touchmove', move, { passive: false }); c.addEventListener('touchend', end);
    form.querySelector('[data-sig-clear]').addEventListener('click', function () { ctx.clearRect(0, 0, c.width, c.height); used = false; });
    form.addEventListener('submit', function () { out.value = used ? c.toDataURL('image/png') : ''; });
  });
})();
/* Installable app: register the service worker and offer push notifications. */
(function () {
  var meta = document.querySelector('meta[name="base-path"]');
  if (!('serviceWorker' in navigator) || !meta) return;
  var base = meta.getAttribute('content');
  navigator.serviceWorker.register(base + 'sw.js', { scope: base }).catch(function () {});
  var btn = document.querySelector('[data-push-enable]'), status = document.querySelector('[data-push-status]');
  if (!btn) return;
  if (!('PushManager' in window)) { btn.disabled = true; status.textContent = 'Not supported in this browser.'; return; }
  function keyBytes(b64) { var p = '='.repeat((4 - b64.length % 4) % 4), s = atob((b64 + p).replace(/-/g, '+').replace(/_/g, '/')); var a = new Uint8Array(s.length); for (var i = 0; i < s.length; i++) a[i] = s.charCodeAt(i); return a; }
  btn.addEventListener('click', function () {
    Notification.requestPermission().then(function (perm) {
      if (perm !== 'granted') { status.textContent = 'Permission was not given.'; return; }
      return navigator.serviceWorker.ready.then(function (reg) {
        return reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: keyBytes(btn.getAttribute('data-key')) });
      }).then(function (sub) {
        var token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        return fetch(base + 'index.php?r=%2Fpush%2Fsubscribe', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': token }, body: JSON.stringify(sub) });
      }).then(function (r) { status.textContent = r && r.ok ? '✓ Notifications are on for this device.' : 'Could not turn on notifications.'; });
    }).catch(function () { status.textContent = 'Could not turn on notifications (HTTPS is required).'; });
  });
})();
