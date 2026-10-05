/**
 * motion.js — runtime motion for the panel.
 *
 * Curves, springs and rests follow the onetake motion library's measured numbers
 * (github.com/feitangyuan/onetake, PolyForm Noncommercial): the same curve table and
 * its t80, the same damped-spring closed form, the same SwiftUI spring mapping.
 *
 * onetake drives those numbers on a timeline and renders to video. This file drives them
 * on the Web Animations API for a live page — so it is the curves, not the renderer.
 *
 * Nothing here hand-rolls an ease. Every move picks a named curve or spring.
 */
(function (global) {
  'use strict';

  var clamp = function (v, a, b) { return v < a ? a : v > b ? b : v; };
  var lerp = function (a, b, k) { return a + (b - a) * k; };
  var seg = function (t, t0, t1) { return t1 === t0 ? (t >= t1 ? 1 : 0) : clamp((t - t0) / (t1 - t0), 0, 1); };
  var sstep = function (e0, e1, x) { var k = clamp((x - e0) / (e1 - e0), 0, 1); return k * k * (3 - 2 * k); };
  var ssstep = function (e0, e1, x) { var k = clamp((x - e0) / (e1 - e0), 0, 1); return k * k * k * (k * (k * 6 - 15) + 10); };

  /* ── curves ──────────────────────────────────────────────────────────────
   * t80 = share of the duration spent before 80% of the distance is covered.
   * Low t80 = snaps then settles. ~.7 = symmetric S-curve, reads as a soft web transition.
   * `bez` is the CSS cubic-bezier that tracks the curve, for properties CSS animates natively.
   */
  var powOut = function (n) { return function (u) { return 1 - Math.pow(1 - u, n); }; };
  var powInOut = function (n) {
    return function (u) { return u < 0.5 ? Math.pow(2, n - 1) * Math.pow(u, n) : 1 - Math.pow(-2 * u + 2, n) / 2; };
  };
  var EXPO_NORM = 1 - Math.pow(2, -10);
  var expoOut = function (u) { return u <= 0 ? 0 : u >= 1 ? 1 : (1 - Math.pow(2, -10 * u)) / EXPO_NORM; };
  var expoIn = function (u) { return u <= 0 ? 0 : u >= 1 ? 1 : (Math.pow(2, 10 * u) - 1) / 1023; };

  var ease = {
    linear: function (u) { return u; },
    smooth: function (u) { return u * u * (3 - 2 * u); },
    smoother: function (u) { return u * u * u * (u * (u * 6 - 15) + 10); },
    sineInOut: function (u) { return -(Math.cos(Math.PI * u) - 1) / 2; },
    cubicInOut: powInOut(3),
    quintInOut: powInOut(5),
    quadOut: powOut(2),
    cubicOut: powOut(3),
    quartOut: powOut(4),
    quintOut: powOut(5),
    expoOut: expoOut,
    expoIn: expoIn,
    expoInOut: function (u) { return u < 0.5 ? expoIn(2 * u) / 2 : 1 - expoIn(2 - 2 * u) / 2; },
    circOut: function (u) { return Math.sqrt(1 - Math.pow(1 - clamp(u, 0, 1), 2)); },
    backOut: function (u) {
      var s = 1.70158;
      return 1 + (s + 1) * Math.pow(u - 1, 3) + s * Math.pow(u - 1, 2);
    },
    back: function (s) {
      s = s == null ? 1.70158 : s;
      return function (u) { return 1 + (s + 1) * Math.pow(u - 1, 3) + s * Math.pow(u - 1, 2); };
    }
  };

  var CURVES = {
    snap:    { ease: expoOut,       t80: 0.23, bez: [0.16, 1, 0.3, 1],           use: 'things that arrive and land' },
    quick:   { ease: ease.quartOut, t80: 0.33, bez: [0.25, 1, 0.5, 1],           use: 'exits, dismissals' },
    settle:  { ease: ease.cubicOut, t80: 0.42, bez: [0.33, 1, 0.68, 1],          use: 'enters, reveals' },
    glide:   { ease: ease.smooth,   t80: 0.71, bez: [0.3333, 0, 0.6667, 1],      use: 'travel between places' },
    drift:   { ease: ease.smoother, t80: 0.67, bez: [0.3333, -0.1667, 0.6667, 1.1667], use: 'longer travel' },
    whip:    { ease: ease.expoInOut, t80: 0.42, bez: [0.87, 0, 0.13, 1],         use: 'the one camera move per page' }
  };

  /* t80 of a curve, measured not assumed. */
  function t80(curve, frac, steps) {
    frac = frac == null ? 0.8 : frac;
    steps = steps || 4000;
    for (var i = 0; i <= steps; i++) { var u = i / steps; if (curve(u) >= frac) return u; }
    return 1;
  }

  /* CSS cubic-bezier(x1,y1,x2,y2) as a function of u. */
  function bezier(x1, y1, x2, y2) {
    var cx = 3 * x1, bx = 3 * (x2 - x1) - cx, ax = 1 - cx - bx;
    var cy = 3 * y1, by = 3 * (y2 - y1) - cy, ay = 1 - cy - by;
    var sx = function (s) { return ((ax * s + bx) * s + cx) * s; };
    var sy = function (s) { return ((ay * s + by) * s + cy) * s; };
    var dsx = function (s) { return (3 * ax * s + 2 * bx) * s + cx; };
    var solve = function (x) {
      var s = x, i, e, d;
      for (i = 0; i < 8; i++) {
        e = sx(s) - x;
        if (Math.abs(e) < 1e-7) return s;
        d = dsx(s);
        if (Math.abs(d) < 1e-6) break;
        s -= e / d;
        if (s < 0 || s > 1) break;
      }
      var lo = 0, hi = 1;
      s = x;
      for (i = 0; i < 60; i++) {
        var v = sx(s);
        if (Math.abs(v - x) < 1e-7) return s;
        if (x > v) lo = s; else hi = s;
        s = (lo + hi) / 2;
      }
      return s;
    };
    return function (u) { return u <= 0 ? 0 : u >= 1 ? 1 : sy(solve(u)); };
  }

  /* ── springs ─────────────────────────────────────────────────────────────
   * Damped spring step response 0 → 1 from rest. zeta < 1 overshoots
   * (.4 bouncy … .8 firm), 1 is critical.
   */
  function spring(tau, zeta, omega) {
    zeta = zeta == null ? 0.6 : zeta;
    omega = omega == null ? 20 : omega;
    if (tau <= 0) return 0;
    if (zeta < 1) {
      var wd = omega * Math.sqrt(1 - zeta * zeta);
      return 1 - Math.exp(-zeta * omega * tau) * (Math.cos(wd * tau) + (zeta * omega / wd) * Math.sin(wd * tau));
    }
    if (zeta === 1) return 1 - Math.exp(-omega * tau) * (1 + omega * tau);
    var q = omega * Math.sqrt(zeta * zeta - 1), r1 = -zeta * omega + q, r2 = -zeta * omega - q;
    return 1 - (r2 * Math.exp(r1 * tau) - r1 * Math.exp(r2 * tau)) / (r2 - r1);
  }

  function springVel(tau, zeta, omega) {
    zeta = zeta == null ? 0.6 : zeta;
    omega = omega == null ? 20 : omega;
    if (tau <= 0) return 0;
    if (zeta < 1) {
      var s = Math.sqrt(1 - zeta * zeta);
      return (omega / s) * Math.exp(-zeta * omega * tau) * Math.sin(omega * s * tau);
    }
    if (zeta === 1) return omega * omega * tau * Math.exp(-omega * tau);
    var q = omega * Math.sqrt(zeta * zeta - 1), r1 = -zeta * omega + q, r2 = -zeta * omega - q;
    return omega * omega * (Math.exp(r1 * tau) - Math.exp(r2 * tau)) / (r1 - r2);
  }

  /* Decaying oscillation around 0 — a click, a landing, a wobble that rings out. */
  function ring(tau, decay, omega) {
    decay = decay == null ? 9 : decay;
    omega = omega == null ? 26 : omega;
    return tau <= 0 ? 0 : Math.exp(-decay * tau) * Math.sin(omega * tau);
  }

  /* Seconds until the spring stays within eps of 1 — how long a beat must hold. */
  function settle(zeta, omega, eps) {
    zeta = zeta == null ? 0.6 : zeta;
    omega = omega == null ? 20 : omega;
    eps = eps == null ? 0.02 : eps;
    var last = 0;
    for (var i = 1; i <= 20000; i++) {
      var t = i / 1000;
      if (Math.abs(1 - spring(t, zeta, omega)) > eps) last = t;
    }
    return last;
  }

  /* SwiftUI's named springs: Spring(duration d, bounce b) = zeta 1 − b, omega 2pi / d. */
  var SWIFT = { smooth: 0, snappy: 0.15, bouncy: 0.3 };

  function swiftSpring(tau, kind, opts) {
    opts = opts || {};
    var b = opts.bounce != null ? opts.bounce : (SWIFT[kind] != null ? SWIFT[kind] : 0);
    var d = opts.duration != null ? opts.duration : 0.5;
    return spring(tau, 1 - b, 2 * Math.PI / d);
  }

  /* ── springs as CSS easings ──────────────────────────────────────────────
   * A spring is not a cubic-bezier. Sample it and emit a CSS `linear()` easing,
   * so the browser runs the real spring without a JS frame loop.
   * Falls back to the curve's bezier where linear() is unsupported.
   */
  function supportsLinear() {
    try { return CSS.supports('transition-timing-function', 'linear(0, 1)'); } catch (e) { return false; }
  }
  var HAS_LINEAR = supportsLinear();

  function springEasing(zeta, omega, steps) {
    steps = steps || 34;
    if (!HAS_LINEAR) return CURVES.snap.bez.join(',');
    var dur = settle(zeta, omega, 0.004);
    var pts = [], i, u;
    for (i = 0; i <= steps; i++) {
      u = i / steps;
      pts.push(spring(u * dur, zeta, omega).toFixed(4));
    }
    return 'linear(' + pts.join(',') + ')';
  }

  /* ── the runtime ─────────────────────────────────────────────────────── */

  var mq = null;
  function reduced() {
    if (mq === null) {
      mq = global.matchMedia ? global.matchMedia('(prefers-reduced-motion: reduce)') : { matches: false };
    }
    return mq.matches;
  }

  /* Any curve, exactly, as a CSS `linear()` easing. Sampled, so it is the curve
   * itself and not an approximation of it. */
  function linearEasing(curve, steps) {
    steps = steps || 40;
    if (!HAS_LINEAR) return CURVES.settle.bez.join(',');
    var pts = [], i, u;
    for (i = 0; i <= steps; i++) {
      u = i / steps;
      pts.push(curve(u).toFixed(4));
    }
    return 'linear(' + pts.join(',') + ')';
  }

  /* The easing to hand the browser for a named curve: the exact sampled curve
   * where linear() exists, the fitted cubic-bezier where it does not. */
  var easingCache = {};
  function easing(name) {
    var c = CURVES[name] || CURVES.settle;
    if (!HAS_LINEAR) return c.bez.join(',');
    if (!easingCache[name]) easingCache[name] = linearEasing(c.ease);
    return easingCache[name];
  }

  var DUR = {
    hover: 88,
    tap: 120,
    enter: 320,
    exit: 190,
    page: 460,
    stagger: 42
  };

  function ms(v) { return reduced() ? 0 : v; }

  function to(node) {
    return typeof node === 'string' ? document.querySelector(node) : node;
  }

  function all(node) {
    var list = typeof node === 'string'
      ? Array.prototype.slice.call(document.querySelectorAll(node))
      : Array.prototype.slice.call(node || []);
    return list;
  }

  function write(el, a) {
    var parts = [];
    if (a.x !== undefined || a.y !== undefined) {
      parts.push('translate3d(' + (a.x || 0) + 'px,' + (a.y || 0) + 'px,0)');
    }
    if (a.s !== undefined && a.s !== 1) parts.push('scale(' + a.s + ')');
    if (a.sx !== undefined || a.sy !== undefined) {
      parts.push('scale(' + (a.sx == null ? 1 : a.sx) + ',' + (a.sy == null ? 1 : a.sy) + ')');
    }
    if (a.rot !== undefined) parts.push('rotate(' + a.rot + 'deg)');
    if (parts.length) el.style.transform = parts.join(' ');
    if (a.op !== undefined) el.style.opacity = a.op;
    if (a.blur !== undefined) el.style.filter = a.blur ? 'blur(' + a.blur + 'px)' : '';
  }

  function run(el, frames, opts) {
    opts = opts || {};
    if (reduced()) {
      if (opts.final) write(el, opts.final);
      return null;
    }
    var anim = el.animate(frames, {
      duration: ms(opts.duration != null ? opts.duration : DUR.enter),
      easing: opts.easing || easing('settle'),
      delay: ms(opts.delay || 0),
      fill: opts.fill || 'both'
    });
    if (opts.final) {
      anim.onfinish = function () { write(el, opts.final); };
    }
    return anim;
  }

  /* An element arrives. Opacity leads the spring so it never reads as a fade —
   * the wordRise relationship: lift + scale on a snap, opacity ahead of it. */
  function enter(el, opts) {
    el = to(el);
    if (!el) return;
    opts = opts || {};
    var dist = opts.dist == null ? 14 : opts.dist;
    var s0 = opts.s0 == null ? 0.965 : opts.s0;
    var op0 = opts.op0 == null ? 0 : opts.op0;
    var easeName = opts.curve || 'settle';
    run(el, [
      { transform: 'translate3d(0,' + dist + 'px,0) scale(' + s0 + ')', opacity: op0 },
      { transform: 'translate3d(0,0,0) scale(1)', opacity: 1 }
    ], {
      duration: opts.duration != null ? opts.duration : DUR.enter,
      easing: opts.easing || easing(easeName),
      delay: opts.delay,
      final: { x: 0, y: 0, s: 1, op: 1 }
    });
  }

  /* An element leaves. Lift, grow a touch — quick, not settled. */
  function leave(el, opts) {
    el = to(el);
    if (!el) return;
    opts = opts || {};
    var dist = opts.dist == null ? -8 : opts.dist;
    var anim = run(el, [
      { transform: 'translate3d(0,0,0) scale(1)', opacity: 1 },
      { transform: 'translate3d(0,' + dist + 'px,0) scale(' + (opts.s1 || 0.985) + ')', opacity: 0 }
    ], {
      duration: opts.duration != null ? opts.duration : DUR.exit,
      easing: easing('quick')
    });
    return anim;
  }

  /* Staggered entrance. Spacing varies on purpose — an even stagger is a metronome
   * and reads as a slideshow. Wide at the top of a group, tighter as it settles. */
  function stagger(nodes, opts) {
    nodes = all(nodes);
    opts = opts || {};
    var step = opts.stagger == null ? DUR.stagger : opts.stagger;
    var start = opts.delay || 0;
    nodes.forEach(function (n, i) {
      if (n.hasAttribute && n.hasAttribute('data-no-enter')) return;
      var t = i * step;
      var falloff = Math.pow(0.88, i);
      enter(n, {
        delay: start + t * (opts.stretch == null ? 1 : opts.stretch),
        duration: (opts.duration != null ? opts.duration : DUR.enter) * (1 - 0.25 * falloff),
        dist: (opts.dist == null ? 14 : opts.dist) * (0.7 + 0.5 * falloff),
        curve: opts.curve
      });
    });
    return nodes.length;
  }

  /* Press: dip, ring out, state flip. The cause the user sees. */
  function press(el, opts) {
    el = to(el);
    if (!el) return;
    opts = opts || {};
    var depth = opts.depth == null ? 0.965 : opts.depth;
    var o = opts.on || {};
    el.addEventListener('pointerdown', function () {
      write(el, { s: depth });
    });
    var release = function () {
      write(el, { s: 1 });
      if (o.onTap) o.onTap(el);
    };
    el.addEventListener('pointerup', release);
    el.addEventListener('pointerleave', function () { write(el, { s: 1 }); });
  }

  /* The ring-out of a landing — feed into whatever just got hit. */
  function land(el, opts) {
    el = to(el);
    if (!el || reduced()) return;
    opts = opts || {};
    var amp = opts.amp == null ? 0.06 : opts.amp;
    var decay = opts.decay == null ? 11 : opts.decay;
    var omega = opts.omega == null ? 30 : opts.omega;
    var dur = opts.duration != null ? opts.duration : 520;
    var samples = 26;
    var frames = [];
    for (var i = 0; i <= samples; i++) {
      var u = i / samples;
      var r = 1 + ring(u * (dur / 1000), decay, omega) * amp;
      frames.push({ transform: 'scale(' + r.toFixed(4) + ')', offset: u });
    }
    el.animate(frames, { duration: ms(dur), easing: 'linear', fill: 'none' });
  }

  /* One container becomes another — the pressed thing is the result.
   * Used for: a row that opens into a detail panel, a tile that becomes the page. */
  function morphRect(fromSel, toSel, opts) {
    fromSel = toBox(fromSel);
    toSel = toBox(toSel);
    opts = opts || {};
    var host = opts.host || document.body;
    var a = fromSel.getBoundingClientRect();
    var b = host.getBoundingClientRect();
    var ghost = document.createElement('div');
    ghost.className = opts.class || 'mt-ghost';
    ghost.style.cssText =
      'position:fixed;z-index:999;pointer-events:none;margin:0;' +
      'left:' + a.left + 'px;top:' + a.top + 'px;width:' + a.width + 'px;height:' + a.height + 'px;' +
      'border-radius:' + (opts.fromRadius || 10) + 'px;' +
      'background:' + (opts.fromBg || 'rgba(124,108,255,0.18)') + ';' +
      'border:1px solid rgba(124,108,255,0.5)';
    document.body.appendChild(ghost);
    var anim = reduced() ? null : ghost.animate([
      { left: a.left + 'px', top: a.top + 'px', width: a.width + 'px', height: a.height + 'px', borderRadius: (opts.fromRadius || 10) + 'px' },
      { left: b.left + 'px', top: b.top + 'px', width: b.width + 'px', height: b.height + 'px', borderRadius: (opts.toRadius || 14) + 'px' }
    ], { duration: ms(opts.duration != null ? opts.duration : DUR.page), easing: easing('glide'), fill: 'both' });
    var done = function () { ghost.remove(); if (opts.then) opts.then(); };
    if (anim) anim.onfinish = done; else done();
    return ghost;
  }

  function toBox(node) {
    if (typeof node === 'string') node = document.querySelector(node);
    if (node && !node.getBoundingClientRect) node = document.querySelector(node);
    return node;
  }

  /* A number that counts to its value on a glide. Kept off the rests. */
  function count(el, to_, opts) {
    el = to(el);
    if (!el) return;
    opts = opts || {};
    var from = opts.from != null ? opts.from : 0;
    var dur = opts.duration != null ? opts.duration : 900;
    var dec = opts.decimals || 0;
    if (reduced()) { el.textContent = fmt(to_, dec); return; }
    var start = null;
    var step = function (ts) {
      if (start === null) start = ts;
      var u = clamp((ts - start) / dur, 0, 1);
      el.textContent = fmt(lerp(from, to_, CURVES.drift.ease(u)), dec);
      if (u < 1) requestAnimationFrame(step);
    };
    requestAnimationFrame(step);
  }

  function fmt(v, dec) {
    var n = Number(v).toFixed(dec);
    var parts = n.split('.');
    parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    return parts.join('.');
  }

  /* ── the doctrine, in code ───────────────────────────────────────────── */

  function init() {
    if (!document.documentElement.classList.contains('mt')) {
      document.documentElement.classList.add('mt');
    }
    if (reduced()) {
      document.documentElement.setAttribute('data-motion', 'reduced');
      return;
    }

    var nav = all('[data-mt]');
    stagger(nav, { delay: 60, stagger: 40, dist: 12 });

    all('[data-carry]').forEach(function (el) {
      el.addEventListener('click', function (ev) {
        var target = el.getAttribute('data-carry');
        if (!target || !document.querySelector(target)) return;
        ev.preventDefault();
        morphRect(el, target, { then: function () { location.href = el.href; } });
      });
    });

    all('[data-press]').forEach(function (el) { press(el); });

    all('[data-count]').forEach(function (el) {
      var v = parseFloat(el.getAttribute('data-count'));
      if (!isNaN(v)) count(el, v);
    });

    var bar = document.querySelector('[data-progress]');
    if (bar) {
      var w = parseFloat(bar.getAttribute('data-progress'));
      if (!isNaN(w)) {
        bar.style.setProperty('--w', clamp(w, 0, 100) + '%');
        if (!reduced()) {
          bar.animate(
            [{ transform: 'scaleX(0)' }, { transform: 'scaleX(' + clamp(w, 0, 100) / 100 + ')' }],
            { duration: ms(760), easing: easing('snap'), fill: 'both', transformOrigin: 'left center' }
          );
        }
      }
    }
  }

  var api = {
    clamp: clamp, lerp: lerp, seg: seg, sstep: sstep, ssstep: ssstep,
    ease: ease, curves: CURVES, bezier: bezier, t80: t80,
    spring: spring, springVel: springVel, ring: ring, settle: settle,
    swiftSpring: swiftSpring, SWIFT: SWIFT, springEasing: springEasing,
    linearEasing: linearEasing, easing: easing, hasLinear: HAS_LINEAR,
    reduced: reduced, DUR: DUR,
    enter: enter, leave: leave, stagger: stagger, press: press, land: land,
    morphRect: morphRect, count: count, init: init,
    all: all, to: to, write: write
  };

  global.MT = api;
  if (typeof module !== 'undefined' && module.exports) module.exports = api;

  if (typeof document === 'undefined') return;

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})(typeof window !== 'undefined' ? window : globalThis);