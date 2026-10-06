/**
 * mascot.js — the mascot's brain.
 *
 * Poses live in CSS (`[data-mood]`); this only decides when the mood changes.
 * It answers real events — an `app:toast` event from app.js, a poke on the
 * floating helper — so the character never loops for no reason.
 */
(function () {
  'use strict';

  var idle = new WeakMap();

  function pose(mood, hold) {
    document.querySelectorAll('[data-mascot]').forEach(function (m) {
      m.setAttribute('data-mood', mood);
      var prev = idle.get(m);
      if (prev) clearTimeout(prev);
      if (hold) {
        idle.set(m, setTimeout(function () { m.setAttribute('data-mood', 'idle'); }, hold));
      }
    });
  }

  window.Mascot = { pose: pose };

  document.addEventListener('app:toast', function (e) {
    var kind = (e.detail && e.detail.kind) || 'info';
    if (kind === 'bad') pose('alert', 4200);
    else if (kind === 'good') pose('happy', 2400);
    else pose('thinking', 2000);
  });

  document.addEventListener('DOMContentLoaded', function () {
    var helper = document.querySelector('[data-helper]');
    if (helper) {
      var close = helper.querySelector('[data-helper-close]');
      var mascot = helper.querySelector('[data-mascot]');
      var key = 'panel.helper.hidden';

      try { if (localStorage.getItem(key) === '1') helper.hidden = true; } catch (e) {}

      if (mascot) mascot.addEventListener('click', function () { pose('happy', 1800); });
      if (close) {
        close.addEventListener('click', function () {
          helper.hidden = true;
          try { localStorage.setItem(key, '1'); } catch (e) {}
        });
      }
    }

    if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) pose('happy', 1600);
  });
})();