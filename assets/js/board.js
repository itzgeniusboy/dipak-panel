/**
 * board.js — the dashboard task board.
 *
 * Vanilla pointer drag: works with a mouse and with a thumb, no library and no
 * HTML5 drag API (which never fires on touch). State is a plain object kept in
 * localStorage; the DOM is always rendered *from* it, never read back.
 *
 * Keyboard: a focused card moves with ← / → and deletes with Backspace, so the
 * board works without a pointer too.
 */
(function () {
  'use strict';

  var KEY = 'panel.tasks.v1';
  var COLS = ['todo', 'doing', 'done'];

  function blank() { return { todo: [], doing: [], done: [] }; }

  function el(tag, cls, text) {
    var n = document.createElement(tag);
    if (cls) n.className = cls;
    if (text != null) n.textContent = text;
    return n;
  }

  function Board(root) {
    this.root = root;
    this.state = this.load();
    this.drag = null;
    this.bind();
    this.render();
  }

  Board.prototype.load = function () {
    try {
      var raw = JSON.parse(localStorage.getItem(KEY));
      if (raw && COLS.every(function (c) { return Array.isArray(raw[c]); })) return raw;
    } catch (e) {}
    return blank();
  };

  Board.prototype.save = function () {
    try { localStorage.setItem(KEY, JSON.stringify(this.state)); } catch (e) {}
  };

  Board.prototype.find = function (id) {
    for (var i = 0; i < COLS.length; i++) {
      var list = this.state[COLS[i]];
      for (var j = 0; j < list.length; j++) {
        if (list[j].id === id) return { col: COLS[i], index: j, item: list[j] };
      }
    }
    return null;
  };

  Board.prototype.move = function (id, toCol) {
    var f = this.find(id);
    if (!f || f.col === toCol) return;
    this.state[f.col].splice(f.index, 1);
    this.state[toCol].push(f.item);
    this.save();
    this.render();
  };

  Board.prototype.remove = function (id) {
    var f = this.find(id);
    if (!f) return;
    this.state[f.col].splice(f.index, 1);
    this.save();
    this.render();
  };

  Board.prototype.add = function (col, text) {
    this.state[col].push({
      id: 't' + Date.now().toString(36) + Math.random().toString(36).slice(2, 6),
      text: text
    });
    this.save();
    this.render();
  };

  Board.prototype.render = function () {
    var self = this;
    COLS.forEach(function (col) {
      var list = self.root.querySelector('[data-list="' + col + '"]');
      var count = self.root.querySelector('[data-count="' + col + '"]');
      if (!list) return;
      list.textContent = '';
      if (count) count.textContent = String(self.state[col].length);

      if (!self.state[col].length) {
        list.appendChild(el('p', 'board__empty', 'Drop a task here'));
        return;
      }
      self.state[col].forEach(function (task) { list.appendChild(self.card(task)); });
    });
  };

  Board.prototype.card = function (task) {
    var self = this;
    var c = el('div', 'tcard');
    c.setAttribute('data-card', task.id);
    c.setAttribute('tabindex', '0');
    c.setAttribute('role', 'listitem');
    c.appendChild(el('span', 'tcard__grip', '⠿'));
    c.appendChild(el('span', 'tcard__text', task.text));

    var x = el('button', 'tcard__x', '✕');
    x.type = 'button';
    x.setAttribute('aria-label', 'Delete task');
    x.addEventListener('click', function (e) { e.stopPropagation(); self.remove(task.id); });
    c.appendChild(x);
    return c;
  };

  Board.prototype.colAt = function (x, y) {
    var node = document.elementFromPoint(x, y);
    var col = node && node.closest ? node.closest('[data-col]') : null;
    return col ? col.getAttribute('data-col') : null;
  };

  Board.prototype.bind = function () {
    var self = this;
    var root = this.root;

    root.addEventListener('pointerdown', function (e) {
      var c = e.target.closest('[data-card]');
      if (!c || e.target.closest('.tcard__x')) return;
      var r = c.getBoundingClientRect();
      self.drag = {
        id: c.getAttribute('data-card'), node: c,
        sx: e.clientX, sy: e.clientY,
        dx: e.clientX - r.left, dy: e.clientY - r.top, moved: false
      };
      try { c.setPointerCapture(e.pointerId); } catch (err) {}
    });

    root.addEventListener('pointermove', function (e) { self.onMove(e); });
    root.addEventListener('pointerup', function (e) { self.onEnd(e); });
    root.addEventListener('pointercancel', function (e) { self.onEnd(e); });

    root.addEventListener('keydown', function (e) {
      var c = e.target.closest('[data-card]');
      if (!c) return;
      var f = self.find(c.getAttribute('data-card'));
      if (!f) return;

      if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') {
        e.preventDefault();
        var to = COLS[COLS.indexOf(f.col) + (e.key === 'ArrowRight' ? 1 : -1)];
        if (to) {
          self.move(f.item.id, to);
          var again = root.querySelector('[data-card="' + f.item.id + '"]');
          if (again) again.focus();
        }
      } else if (e.key === 'Delete' || e.key === 'Backspace') {
        e.preventDefault();
        self.remove(f.item.id);
      }
    });

    root.querySelectorAll('[data-add]').forEach(function (form) {
      form.addEventListener('submit', function (e) {
        e.preventDefault();
        var input = form.querySelector('input');
        var text = (input.value || '').trim();
        if (!text) return;
        self.add(form.getAttribute('data-add'), text);
        input.value = '';
      });
    });
  };

  Board.prototype.onMove = function (e) {
    var d = this.drag;
    if (!d) return;

    if (!d.moved) {
      if (Math.abs(e.clientX - d.sx) < 5 && Math.abs(e.clientY - d.sy) < 5) return;
      d.moved = true;
      d.node.setAttribute('data-dragging', 'true');
      d.node.style.width = d.node.getBoundingClientRect().width + 'px';
    }

    d.node.style.left = (e.clientX - d.dx) + 'px';
    d.node.style.top = (e.clientY - d.dy) + 'px';

    var over = this.colAt(e.clientX, e.clientY);
    this.root.querySelectorAll('[data-col]').forEach(function (col) {
      col.setAttribute('data-over', col.getAttribute('data-col') === over ? 'true' : 'false');
    });
  };

  Board.prototype.onEnd = function (e) {
    var d = this.drag;
    if (!d) return;

    var over = d.moved ? this.colAt(e.clientX, e.clientY) : null;
    d.node.removeAttribute('data-dragging');
    d.node.style.left = d.node.style.top = d.node.style.width = '';
    this.root.querySelectorAll('[data-col]').forEach(function (col) { col.removeAttribute('data-over'); });
    this.drag = null;

    if (d.moved) {
      if (over) this.move(d.id, over);
      else this.render();
    }
  };

  function boot() {
    document.querySelectorAll('[data-board]').forEach(function (root) { new Board(root); });
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
  else boot();
})();