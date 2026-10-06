/**
 * sound.js — the click language.
 *
 * Every cue is synthesised with Web Audio: no files, no requests, and they all
 * share one timbre family so the panel sounds like a single object. The
 * AudioContext is created on the first real gesture (browsers block audio
 * before that) and nothing plays while muted.
 *
 * Mute lives in localStorage('panel.sound') and the header button toggles it.
 */
(function () {
  'use strict';

  var KEY = 'panel.sound';
  var VOL = 0.035;
  var ctx = null;
  var master = null;

  function off() {
    return document.documentElement.getAttribute('data-sound') === 'off';
  }

  function ensure() {
    if (off()) return null;
    var AC = window.AudioContext || window.webkitAudioContext;
    if (!AC) return null;
    if (!ctx) {
      try { ctx = new AC(); } catch (e) { return null; }
      master = ctx.createGain();
      master.gain.value = VOL;
      master.connect(ctx.destination);
    }
    if (ctx.state === 'suspended') ctx.resume();
    return ctx;
  }

  function tone(freq, to, dur, type, when, gain) {
    var c = ensure();
    if (!c) return;
    var t0 = c.currentTime + (when || 0);
    var osc = c.createOscillator();
    var g = c.createGain();
    osc.type = type || 'triangle';
    osc.frequency.setValueAtTime(freq, t0);
    if (to && to !== freq) osc.frequency.exponentialRampToValueAtTime(to, t0 + dur);
    g.gain.setValueAtTime(0.0001, t0);
    g.gain.exponentialRampToValueAtTime(gain == null ? 0.5 : gain, t0 + 0.006);
    g.gain.exponentialRampToValueAtTime(0.0001, t0 + dur);
    osc.connect(g).connect(master);
    osc.start(t0);
    osc.stop(t0 + dur + 0.03);
  }

  function syncToggle() {
    var isOff = off();
    document.querySelectorAll('[data-sound-toggle]').forEach(function (b) {
      b.setAttribute('aria-pressed', isOff ? 'false' : 'true');
      b.setAttribute('title', isOff ? 'Sound off' : 'Sound on');
      b.textContent = isOff ? '🔇' : '🔊';
    });
  }

  function setOff(v) {
    document.documentElement.setAttribute('data-sound', v ? 'off' : 'on');
    try { localStorage.setItem(KEY, v ? 'off' : 'on'); } catch (e) {}
    syncToggle();
  }

  var api = {
    tick: function () { tone(1720, 980, 0.045, 'triangle'); },
    chime: function () { tone(880, 880, 0.10, 'sine'); tone(1320, 1320, 0.14, 'sine', 0.075, 0.38); },
    warn: function () { tone(340, 210, 0.16, 'sawtooth', 0, 0.35); },
    toggle: function () { var v = !off(); setOff(v); if (!v) tone(1440, 1720, 0.05, 'square', 0, 0.28); },
    enabled: function () { return !off(); }
  };

  window.Sound = api;

  function boot() {
    try { if (localStorage.getItem(KEY) === 'off') document.documentElement.setAttribute('data-sound', 'off'); } catch (e) {}
    syncToggle();
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
  else boot();

  var INTERACTIVE = 'button, a, [data-press], [data-card], .nav__item, .tab';

  function hit(target) {
    return target && target.closest && target.closest(INTERACTIVE) && !target.closest('[data-no-sound]');
  }

  document.addEventListener('pointerdown', function (e) {
    if (!e.target.closest || e.target.closest('[data-sound-toggle]')) return;
    if (hit(e.target)) api.tick();
  }, true);

  document.addEventListener('keydown', function (e) {
    if ((e.key === 'Enter' || e.key === ' ') && hit(e.target)) api.tick();
  }, true);

  document.addEventListener('click', function (e) {
    if (e.target.closest && e.target.closest('[data-sound-toggle]')) {
      e.preventDefault();
      api.toggle();
    }
  });

  document.addEventListener('app:toast', function (e) {
    var kind = e.detail && e.detail.kind;
    if (kind === 'bad') api.warn();
    else if (kind === 'good') api.chime();
  });
})();