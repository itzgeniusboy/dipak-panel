/**
 * app.js — the panel's behaviour layer.
 * Vanilla JS. Replaces jQuery and SweetAlert2: native <dialog> for modals,
 * a toast stack, busy states on submit, and the mobile drawer.
 * Every transition goes through MT so the curves stay in one place.
 */
(function () {
  'use strict';

  /* ── toasts ─────────────────────────────────────────────────────────── */

  var ICONS = { good: '✓', bad: '!', warn: '!', info: 'i' };

  function toast(msg, kind, title) {
    kind = kind || 'info';
    var host = document.getElementById('toasts');
    if (!host) return;

    var el = document.createElement('div');
    el.className = 'toast toast--' + kind;
    el.setAttribute('role', kind === 'bad' ? 'alert' : 'status');
    el.innerHTML =
      '<span class="toast__bar"></span>' +
      '<span class="mono" style="color:var(--' + (kind === 'info' ? 'accent' : kind) + ');font-size:var(--t-sm)">' +
      (ICONS[kind] || '') + '</span>' +
      '<span><span class="toast__title">' + esc(title || (kind === 'bad' ? 'Something went wrong' : kind === 'good' ? 'Done' : 'Note')) +
      '</span><span class="toast__msg">' + esc(msg) + '</span></span>';
    host.appendChild(el);

    var life = parseInt(el.getAttribute('data-life') || (kind === 'bad' ? 7000 : 3800), 10);
    var close = function () {
      if (el.getAttribute('data-leaving')) return;
      el.setAttribute('data-leaving', 'true');
      var done = function () { el.remove(); };
      if (MT.reduced()) done();
      else setTimeout(done, 200);
    };
    el.addEventListener('click', close);
    setTimeout(close, life);

    /* Let anything else on the page answer the toast — the mascot does. */
    document.dispatchEvent(new CustomEvent('app:toast', { detail: { kind: kind, msg: msg } }));

    return el;
  }

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  /* ── dialogs ────────────────────────────────────────────────────────── */

  function dialog(opts) {
    return new Promise(function (resolve) {
      var dlg = document.createElement('dialog');
      dlg.className = 'dlg';
      dlg.innerHTML =
        '<form method="dialog">' +
          '<div class="dlg__head"><div><h3>' + esc(opts.title || 'Confirm') + '</h3>' +
          (opts.text ? '<p>' + esc(opts.text) + '</p>' : '') + '</div>' +
          '<button class="dlg__x" value="cancel" aria-label="Close">✕</button></div>' +
          '<div class="dlg__body">' + (opts.html || '') + '</div>' +
          '<div class="dlg__foot">' +
            '<button class="btn btn--ghost" value="cancel">' + esc(opts.cancel || 'Cancel') + '</button>' +
            '<button class="btn ' + (opts.danger ? 'btn--danger' : 'btn--primary') + '" value="ok">' +
            esc(opts.ok || 'Confirm') + '</button>' +
          '</div>' +
        '</form>';
      document.body.appendChild(dlg);
      dlg.showModal();
      dlg.addEventListener('close', function () {
        resolve(dlg.returnValue === 'ok');
        dlg.remove();
      });
      var ok = dlg.querySelector('[value="ok"]');
      if (ok) ok.focus();
    });
  }

  /* ── busy states ────────────────────────────────────────────────────── */

  function bindForms() {
    document.querySelectorAll('form').forEach(function (form) {
      if (form.hasAttribute('data-no-busy')) return;
      form.addEventListener('submit', function () {
        var btn = form.querySelector('button[type="submit"]');
        if (!btn || btn.disabled) return;
        btn.classList.add('is-busy');
        btn.disabled = true;
        var spin = document.createElement('span');
        spin.className = 'btn__spin';
        btn.appendChild(spin);
        // A failed validation never navigates, so the button must come back.
        setTimeout(function () {
          if (!document.body.contains(btn)) return;
          btn.classList.remove('is-busy');
          btn.disabled = false;
          spin.remove();
        }, 12000);
      });
    });
  }

  /* ── mobile drawer ──────────────────────────────────────────────────── */

  function bindDrawer() {
    var side = document.querySelector('[data-drawer]');
    var open = document.querySelector('[data-drawer-open]');
    var scrim = document.querySelector('.scrim');
    if (!side || !open) return;

    var set = function (on) {
      side.setAttribute('data-open', on ? 'true' : 'false');
      side.setAttribute('aria-hidden', on ? 'false' : 'true');
      if (scrim) scrim.setAttribute('data-open', on ? 'true' : 'false');
      open.setAttribute('aria-expanded', on ? 'true' : 'false');
    };
    open.addEventListener('click', function () { set(side.getAttribute('data-open') !== 'true'); });
    if (scrim) scrim.addEventListener('click', function () { set(false); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') set(false); });
    side.querySelectorAll('a').forEach(function (a) { a.addEventListener('click', function () { set(false); }); });
    set(false);
  }

  /* ── confirm on [data-confirm] ──────────────────────────────────────── */

  function bindConfirms() {
    document.addEventListener('click', function (e) {
      var el = e.target.closest('[data-confirm]');
      if (!el) return;
      e.preventDefault();
      dialog({
        title: el.getAttribute('data-confirm-title') || 'Are you sure?',
        text: el.getAttribute('data-confirm'),
        ok: el.getAttribute('data-confirm-ok') || 'Confirm',
        danger: el.hasAttribute('data-confirm-danger')
      }).then(function (yes) { if (yes) el.form ? el.form.requestSubmit() : el.click(); });
    }, true);
  }

  /* ── flash toasts from PHP ──────────────────────────────────────────── */

  function readFlash() {
    var host = document.querySelector('[data-flash]');
    if (!host) return;
    var cls = host.classList.contains('alert--bad') ? 'bad'
      : host.classList.contains('alert--good') ? 'good'
      : host.classList.contains('alert--warn') ? 'warn' : 'info';
    toast(host.textContent.trim(), cls);
    if (MT.reduced()) host.remove();
    else MT.leave(host, { dist: -6 });
  }

  /* ── public ─────────────────────────────────────────────────────────── */

  window.App = {
    toast: toast,
    dialog: dialog,
    confirm: function (text, title) {
      return dialog({ title: title || 'Are you sure?', text: text, ok: 'Yes', danger: true });
    }
  };

  window.Swal = {
    fire: function (o) {
      if (!o) return;
      return dialog({
        title: o.title && (o.title.text || o.title),
        html: o.html || '',
        ok: o.ok && (o.ok.text || o.ok) || 'OK',
        cancel: o.cancel && (o.cancel.text || o.cancel) || 'Cancel',
        danger: !!o.danger
      }).then(function (yes) { return { value: yes ? (o.ok && o.ok.value !== undefined ? o.ok.value : true) : false }; });
    },
    close: function () {},
    html: function () { return Promise.resolve({}); },
    isVisible: function () { return !!document.querySelector('dialog[open]'); }
  };

  document.addEventListener('DOMContentLoaded', function () {
    bindForms();
    bindDrawer();
    bindConfirms();
    readFlash();
  });
})();
