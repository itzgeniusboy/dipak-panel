/**
 * circuit-agent.js — Circuit walks the screen and presses the buttons.
 *
 * The mascot is a fixed element; this moves it to a real control, plays a
 * press (ripple + squash + tick), then acts. A plan that crosses pages stores
 * its remaining steps in sessionStorage and resumes after the navigation, so
 * "generate a key" visibly opens Keys and then speaks the new key.
 *
 * Voice uses SpeechRecognition and calls the same command() the API does.
 */
(function () {
  'use strict';

  var RESUME = 'circuit.resume';
  var agentEl = null;
  var bodyEl = null;
  var sayEl = null;
  var textEl = null;
  var busy = false;
  var listening = false;
  var recog = null;
  var home = { x: 0, y: 0 };

  function meta(name) {
    var m = document.querySelector('meta[name="' + name + '"]');
    return m ? m.getAttribute('content') : '';
  }

  function reduced() {
    return (window.MT && MT.reduced && MT.reduced()) ||
      (window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches);
  }

  function wait(ms) {
    return new Promise(function (res) { setTimeout(res, reduced() ? Math.min(ms, 60) : ms); });
  }

  function base() {
    var b = meta('app-base');
    return b || '/';
  }

  /* ── movement ──────────────────────────────────────────────────────── */

  function place(x, y, animate) {
    if (!agentEl) return;
    if (!animate) {
      agentEl.style.transition = 'none';
      agentEl.style.transform = 'translate3d(' + x + 'px,' + y + 'px,0)';
      void agentEl.offsetWidth;
      agentEl.style.transition = '';
      return;
    }
    agentEl.style.transform = 'translate3d(' + x + 'px,' + y + 'px,0)';
  }

  function computeHome() {
    var w = agentEl ? agentEl.offsetWidth : 72;
    var h = agentEl ? agentEl.offsetHeight : 72;
    home = {
      x: Math.max(8, window.innerWidth - w - 16),
      y: Math.max(8, window.innerHeight - h - 16)
    };
  }

  function visible(el) {
    if (!el) return false;
    var r = el.getBoundingClientRect();
    return r.width > 0 && r.height > 0 &&
      r.bottom > 0 && r.top < window.innerHeight &&
      r.right > 0 && r.left < window.innerWidth;
  }

  function openDrawerIfNeeded(el) {
    if (!el || !el.closest('[data-drawer]')) return Promise.resolve();
    var side = document.querySelector('[data-drawer]');
    if (side && side.getAttribute('data-open') === 'true') return Promise.resolve();
    var btn = document.querySelector('[data-drawer-open]');
    if (btn) btn.click();
    return wait(320);
  }

  function travelTo(el) {
    return openDrawerIfNeeded(el).then(function () {
      if (!el || reduced()) return wait(120);
      if (el.scrollIntoView) {
        try { el.scrollIntoView({ block: 'center', inline: 'center' }); } catch (e) { el.scrollIntoView(); }
      }
      return wait(240).then(function () {
        var r = el.getBoundingClientRect();
        var w = agentEl ? agentEl.offsetWidth : 72;
        var h = agentEl ? agentEl.offsetHeight : 72;
        var x = Math.min(Math.max(8, r.left + r.width / 2 - w / 2), window.innerWidth - w - 8);
        var y = Math.min(Math.max(8, r.top - h - 6), window.innerHeight - h - 8);
        place(Math.round(x), Math.round(y), true);
        return wait(520);
      });
    });
  }

  function moveHome() {
    if (!agentEl) return;
    computeHome();
    place(home.x, home.y, true);
  }

  /* ── the visible press ─────────────────────────────────────────────── */

  function press(el) {
    if (!el) return;
    var r = el.getBoundingClientRect();
    var dot = document.createElement('span');
    dot.className = 'circuit-tap';
    dot.style.left = (r.left + r.width / 2) + 'px';
    dot.style.top = (r.top + r.height / 2) + 'px';
    document.body.appendChild(dot);
    setTimeout(function () { dot.remove(); }, 720);

    if (window.Sound) Sound.tick();
    el.classList.add('is-circuited');
    setTimeout(function () { el.classList.remove('is-circuited'); }, 760);
  }

  function pressSelector(selector) {
    var el = selector ? document.querySelector(selector) : null;
    return travelTo(el).then(function () {
      press(el);
      return wait(560);
    });
  }

  /* ── speech ────────────────────────────────────────────────────────── */

  function say(text, hold) {
    if (!text) return Promise.resolve();
    if (sayEl) sayEl.hidden = false;
    if (textEl) textEl.textContent = text;
    if (window.Mascot) Mascot.pose('happy', 2200);
    try {
      if (window.speechSynthesis) {
        speechSynthesis.cancel();
        var u = new SpeechSynthesisUtterance(text);
        u.lang = 'en-IN';
        u.rate = 1;
        speechSynthesis.speak(u);
      }
    } catch (e) {}
    return wait(hold == null ? 800 : hold);
  }

  /* ── plans ─────────────────────────────────────────────────────────── */

  function run(plan) {
    plan = Array.isArray(plan) ? plan : [];
    if (busy || !agentEl) return Promise.resolve();
    busy = true;
    var i = 0;

    function next() {
      if (i >= plan.length) { busy = false; moveHome(); return Promise.resolve(); }
      var step = plan[i++];
      var p;

      if (step.type === 'say') {
        p = say(step.text);
      } else if (step.type === 'goto') {
        var pre = step.speak ? say(step.speak, 250) : Promise.resolve();
        p = pre.then(function () { return pressSelector(step.target); }).then(function () {
          var rest = plan.slice(i);
          try { sessionStorage.setItem(RESUME, JSON.stringify(rest)); } catch (e) {}
          if (step.url) window.location.href = step.url;
          return wait(500);
        });
      } else {
        var lead = step.speak ? say(step.speak, 250) : Promise.resolve();
        p = lead.then(function () { return pressSelector(step.target); });
      }

      return p.then(next, next);
    }

    return next();
  }

  function resume() {
    var raw = null;
    try { raw = sessionStorage.getItem(RESUME); } catch (e) {}
    if (!raw) return;
    try { sessionStorage.removeItem(RESUME); } catch (e) {}
    var plan;
    try { plan = JSON.parse(raw); } catch (e) { return; }
    if (Array.isArray(plan) && plan.length) {
      setTimeout(function () { run(plan); }, 450);
    }
  }

  /* A single hello per session, so Circuit feels alive the moment you arrive. */
  function greet() {
    var seen = null;
    try { seen = sessionStorage.getItem('circuit.greeted'); } catch (e) {}
    if (seen) return;
    try { sessionStorage.setItem('circuit.greeted', '1'); } catch (e) {}
    if (busy) return;
    var pending = null;
    try { pending = sessionStorage.getItem(RESUME); } catch (e) {}
    if (pending) return;
    setTimeout(function () {
      if (!busy) say("Hi, I'm Circuit — tap the mic and tell me what you need.", 1600);
    }, 700);
  }

  /* ── the command endpoint ──────────────────────────────────────────── */

  function command(text) {
    if (!text) return Promise.resolve({ ok: false });
    var headerName = meta('csrf-header-name') || 'X-CSRF-TOKEN';
    var headers = { 'Content-Type': 'application/json', 'Accept': 'application/json' };
    headers[headerName] = meta('csrf-token');

    return fetch(base() + 'assistant/command', {
      method: 'POST',
      headers: headers,
      credentials: 'same-origin',
      body: JSON.stringify({ text: text })
    }).then(function (r) {
      return r.json();
    }).then(function (res) {
      if (res.csrf) {
        var m = document.querySelector('meta[name="csrf-token"]');
        if (m) m.setAttribute('content', res.csrf);
      }
      if (res.toast && window.App) App.toast(res.toast.msg, res.toast.kind);
      return run(res.plan).then(function () { return res; });
    }).catch(function () {
      if (window.App) App.toast('Assistant request failed.', 'bad');
      return say('I could not reach the panel.').then(function () { return { ok: false }; });
    });
  }

  /* ── voice ─────────────────────────────────────────────────────────── */

  function setListening(on) {
    listening = on;
    document.querySelectorAll('[data-circuit-mic]').forEach(function (b) {
      b.setAttribute('aria-pressed', on ? 'true' : 'false');
      b.classList.toggle('is-listening', on);
    });
    if (agentEl) agentEl.classList.toggle('is-listening', on);
  }

  function hint(msg) {
    if (window.App) App.toast(msg, 'warn');
    say(msg, 2200);
  }

  function initVoice() {
    var SR = window.SpeechRecognition || window.webkitSpeechRecognition;
    var buttons = document.querySelectorAll('[data-circuit-mic]');

    if (!SR) {
      buttons.forEach(function (b) {
        b.addEventListener('click', function () {
          hint('This browser cannot listen — type your command below instead.');
        });
      });
      return;
    }

    recog = new SR();
    recog.lang = 'en-IN';
    recog.interimResults = false;
    recog.maxAlternatives = 1;
    recog.onstart = function () { setListening(true); };
    recog.onend = function () { setListening(false); };
    recog.onerror = function (e) {
      setListening(false);
      var code = e && e.error ? e.error : '';
      if (code === 'not-allowed' || code === 'service-not-allowed') {
        hint('Microphone is blocked. Allow mic access for this site, or type below.');
      } else if (code === 'no-speech') {
        hint("I didn't hear anything — try again or type below.");
      } else if (code === 'audio-capture') {
        hint('No microphone found — type your command below instead.');
      } else if (code === 'network') {
        hint('Voice needs a network connection — type your command below.');
      } else {
        hint('Mic trouble — type your command below instead.');
      }
      listening = false;
    };
    recog.onresult = function (e) {
      var text = e.results[e.results.length - 1][0].transcript;
      command(text);
    };

    function toggle() {
      if (listening) { try { recog.stop(); } catch (e) {} return; }
      try {
        recog.start();
        setListening(true);
      } catch (e) {
        hint('This browser cannot listen — type your command below instead.');
      }
    }

    buttons.forEach(function (b) { b.addEventListener('click', toggle); });
    if (bodyEl) bodyEl.addEventListener('click', toggle);
  }

  /* ── boot ──────────────────────────────────────────────────────────── */

  function boot() {
    agentEl = document.querySelector('[data-agent]');
    if (!agentEl) return;
    bodyEl = agentEl.querySelector('[data-agent-body]');
    sayEl = agentEl.querySelector('[data-agent-say]');
    textEl = agentEl.querySelector('[data-agent-text]');

    var close = agentEl.querySelector('[data-agent-close]');
    if (close) close.addEventListener('click', function () { sayEl.hidden = true; });

    var form = agentEl.querySelector('[data-agent-ask]');
    var input = agentEl.querySelector('[data-agent-input]');
    if (form && input) {
      form.addEventListener('submit', function (e) {
        e.preventDefault();
        var text = input.value.trim();
        if (!text) return;
        input.value = '';
        command(text);
      });
    }

    computeHome();
    place(home.x, home.y, false);
    requestAnimationFrame(function () { agentEl.classList.add('is-ready'); });
    window.addEventListener('resize', moveHome);

    initVoice();
    resume();
    greet();
  }

  window.Circuit = {
    command: command,
    run: run,
    say: say,
    moveHome: moveHome,
    isBusy: function () { return busy; }
  };

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
  else boot();
})();