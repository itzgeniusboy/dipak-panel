/* eslint-disable */
/**
 * Soft navigation — the panel never does a full page load.
 *
 * Internal links and form submits are fetched, and only <main id="main"> is
 * swapped. The sidebar, header and the Circuit agent live outside that region,
 * so the page keeps its JavaScript context — which means SpeechRecognition (the
 * mic) keeps running instead of dying on every reload.
 *
 * Falls back to a normal navigation whenever anything is off (external link,
 * download, logout, non-HTML response, or a fetch error).
 */
(function () {
  'use strict';

  if (typeof window === 'undefined' || !window.fetch || !window.DOMParser) {
    window.SoftNav = { go: function (u) { window.location.href = u; }, onCleanup: function () {}, active: false };
    return;
  }

  var CORE = /(motion|app|mascot|sound|circuit-agent|soft-nav)\.js(\?|$)/;
  var busy = false;
  var cleanups = [];

  function onCleanup(fn) {
    if (typeof fn === 'function') { cleanups.push(fn); }
  }

  function runCleanups() {
    var list = cleanups;
    cleanups = [];
    list.forEach(function (fn) { try { fn(); } catch (e) {} });
  }

  function sameOrigin(url) {
    try {
      return new URL(url, window.location.href).origin === window.location.origin;
    } catch (e) {
      return false;
    }
  }

  function isInternal(url) {
    if (!url) { return false; }
    if (/^(mailto:|tel:|javascript:|#)/i.test(url)) { return false; }
    if (!sameOrigin(url)) { return false; }
    return !/\/(logout)\b/i.test(url);
  }

  function setMeta(name, value) {
    if (!value) { return; }
    var el = document.querySelector('meta[name="' + name + '"]');
    if (el) { el.setAttribute('content', value); }
  }

  function highlight(url) {
    var path;
    try { path = new URL(url, window.location.href).pathname; } catch (e) { return; }
    document.querySelectorAll('[data-nav]').forEach(function (a) {
      var href;
      try { href = new URL(a.getAttribute('href'), window.location.href).pathname; } catch (e) { return; }
      if (href === path) {
        a.setAttribute('aria-current', 'page');
      } else {
        a.removeAttribute('aria-current');
      }
    });
  }

  /* Re-run the page's own inline scripts (view "script" section). Core bundles
   * are skipped. Appended fresh, so they execute once each. */
  function runScripts(doc) {
    var scripts = Array.prototype.slice.call(doc.body.querySelectorAll('script')).filter(function (s) {
      var src = s.getAttribute('src') || '';
      return !CORE.test(src);
    });

    return scripts.reduce(function (chain, old) {
      return chain.then(function () {
        return new Promise(function (done) {
          var s = document.createElement('script');
          s.setAttribute('data-soft-nav', '1');
          Array.prototype.forEach.call(old.attributes, function (attr) {
            if (attr.name !== 'src') { s.setAttribute(attr.name, attr.value); }
          });
          if (old.getAttribute('src')) { s.src = old.getAttribute('src'); }
          if (!old.getAttribute('src')) { s.text = old.textContent; }
          s.onload = done;
          s.onerror = done;
          document.body.appendChild(s);
          if (!old.getAttribute('src')) { done(); }
        });
      });
    }, Promise.resolve());
  }

  function removeOldScripts() {
    document.querySelectorAll('script[data-soft-nav]').forEach(function (s) { s.remove(); });
  }

  function afterSwap() {
    if (window.MT && typeof window.MT.init === 'function') {
      try { window.MT.init(); } catch (e) {}
    }
    if (window.App && window.App.bind) {
      try { window.App.bind.forms(); } catch (e) {}
      try { window.App.bind.flash(); } catch (e) {}
    }
    window.scrollTo(0, 0);
    var side = document.querySelector('[data-drawer]');
    if (side) { side.setAttribute('data-open', 'false'); }
  }

  function swap(doc, url, push) {
    var fresh = doc.querySelector('#main');
    var here = document.querySelector('#main');
    if (!fresh || !here) { return false; }

    runCleanups();
    removeOldScripts();
    here.innerHTML = fresh.innerHTML;

    var title = doc.querySelector('title');
    if (title) { document.title = title.textContent; }

    var csrf = doc.querySelector('meta[name="csrf-token"]');
    if (csrf) { setMeta('csrf-token', csrf.getAttribute('content')); }
    var head = doc.querySelector('meta[name="csrf-header-name"]');
    if (head) { setMeta('csrf-header-name', head.getAttribute('content')); }

    highlight(url);
    if (push) { try { window.history.pushState({ soft: true, url: url }, '', url); } catch (e) {} }

    return runScripts(doc).then(function () {
      afterSwap();
      return true;
    });
  }

  function load(url, push) {
    if (busy) { return Promise.resolve(false); }
    busy = true;

    return fetch(url, {
      credentials: 'same-origin',
      headers: { 'Accept': 'text/html', 'X-Requested-With': 'fetch' }
    }).then(function (res) {
      if (res.redirected && /\/login\b/.test(res.url)) {
        window.location.href = res.url;
        return false;
      }
      if (!res.ok) { throw new Error('HTTP ' + res.status); }
      var type = res.headers.get('content-type') || '';
      if (type.indexOf('text/html') === -1) { window.location.href = res.url; return false; }
      return res.text().then(function (html) {
        var doc = new DOMParser().parseFromString(html, 'text/html');
        if (!doc.querySelector('#main')) {
          window.location.href = res.url;
          return false;
        }
        return swap(doc, res.url, push !== false);
      });
    }).catch(function () {
      window.location.href = url;
      return false;
    }).then(function (r) {
      busy = false;
      return r;
    });
  }

  function go(url, push) {
    if (!isInternal(url)) { window.location.href = url; return Promise.resolve(false); }
    return load(url, push !== false);
  }

  /* ── intercept links ─────────────────────────────────────────────── */
  document.addEventListener('click', function (ev) {
    if (ev.defaultPrevented || ev.metaKey || ev.ctrlKey || ev.shiftKey || ev.altKey) { return; }
    if (ev.button !== undefined && ev.button !== 0) { return; }
    var a = ev.target.closest ? ev.target.closest('a[href]') : null;
    if (!a) { return; }
    if (a.target && a.target !== '_self') { return; }
    if (a.hasAttribute('download') || a.hasAttribute('data-no-soft')) { return; }
    var url = a.href;
    if (!isInternal(url) || a.getAttribute('href').charAt(0) === '#') { return; }
    ev.preventDefault();
    go(url, true);
  }, false);

  /* ── intercept form submits ──────────────────────────────────────── */
  document.addEventListener('submit', function (ev) {
    var form = ev.target;
    if (!form || form.tagName !== 'FORM') { return; }
    if (form.hasAttribute('data-no-soft') || form.hasAttribute('target')) { return; }
    var method = (form.getAttribute('method') || 'get').toLowerCase();
    if (method !== 'get' && method !== 'post') { return; }
    var action = form.getAttribute('action');
    if (action && !sameOrigin(action)) { return; }

    ev.preventDefault();
    if (method === 'get') {
      var qs = new URLSearchParams(new FormData(form)).toString();
      var base = form.getAttribute('action') || window.location.href;
      go(base + (base.indexOf('?') === -1 ? '?' : '&') + qs, true);
      return;
    }

    var fd = new FormData(form);
    fetch(form.getAttribute('action') || window.location.href, {
      method: 'POST',
      body: fd,
      credentials: 'same-origin',
      headers: { 'Accept': 'text/html', 'X-Requested-With': 'fetch' }
    }).then(function (res) {
      if (res.redirected && /\/login\b/.test(res.url)) { window.location.href = res.url; return; }
      var type = res.headers.get('content-type') || '';
      if (type.indexOf('text/html') === -1) { window.location.reload(); return; }
      return res.text().then(function (html) {
        var doc = new DOMParser().parseFromString(html, 'text/html');
        if (!doc.querySelector('#main')) { window.location.reload(); return; }
        swap(doc, res.url, true);
      });
    }).catch(function () {
      form.submit();
    });
  }, false);

  /* ── back / forward ──────────────────────────────────────────────── */
  window.addEventListener('popstate', function (ev) {
    var url = (ev.state && ev.state.url) || window.location.href;
    if (!busy) { load(url, false); }
  });

  window.SoftNav = { go: go, onCleanup: onCleanup, active: true };
})(window);