<?= $this->extend('Layout/Starter') ?>

<?= $this->section('title') ?>Keys<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php
/* ────────────────────────────────────────────────────────────────────────
 * Prep. The list action hands us $keylist (array of rows) and $user.
 * Owners get a leaner query upstream (user_key only), so the richer
 * columns are rendered only when the row actually carries them.
 * ──────────────────────────────────────────────────────────────────────── */
$rows = (isset($keylist) && is_array($keylist)) ? $keylist : [];
foreach ($rows as $i => $row) {
    if (is_array($row)) {
        $rows[$i] = (object) $row;
    }
}

$total  = count($rows);
$probe  = $total ? $rows[0] : null;
$full   = is_object($probe) && isset($probe->status);
$level  = isset($user) ? (int) $user->level : 0;
$isOwner  = ($level === 1);
$canExtend = ($level === 1 || $level === 2);

$now     = time();
$weekEnd = $now + (7 * 86400);

$cActive = 0;
$cExpiring = 0;
$cUnused  = 0;
$cExpired = 0;
$cBlocked = 0;

$devicesUsed = function ($raw) {
    $raw = trim((string) $raw);
    if ($raw === '') {
        return 0;
    }
    $list = array_filter(
        array_map('trim', explode(',', reduce_multiples($raw, ',', true))),
        function ($v) {
            return $v !== '';
        }
    );

    return count(array_unique($list));
};

$durationLabel = function ($hours) {
    $hours = (int) $hours;
    if ($hours <= 0) {
        return '—';
    }
    if ($hours === 1) {
        return '1 hour';
    }
    if ($hours < 24) {
        return $hours . ' hours';
    }
    if ($hours % 720 === 0) {
        $months = (int) ($hours / 720);

        return $months . ' month' . ($months > 1 ? 's' : '');
    }
    $days = (int) ($hours / 24);

    return $days . ' day' . ($days > 1 ? 's' : '');
};

/* [ state, chip modifier, label, is live ] */
$keyState = function ($row) use ($now) {
    $raw = trim((string) ($row->expired_date ?? ''));
    $ts  = $raw === '' ? 0 : (int) strtotime($raw);

    if ((string) ($row->status ?? '') === '0') {
        return ['blocked', 'chip--bad', 'Blocked', false];
    }
    if ($ts === 0) {
        return ['unused', '', 'Not activated', false];
    }
    if ($ts < $now) {
        return ['expired', 'chip--warn', 'Expired', false];
    }

    return ['active', 'chip--good', 'Active', true];
};

if ($full) {
    foreach ($rows as $row) {
        $raw = trim((string) ($row->expired_date ?? ''));
        $ts  = $raw === '' ? 0 : (int) strtotime($raw);

        if ((string) ($row->status ?? '') === '0') {
            $cBlocked++;
        } elseif ($ts === 0) {
            $cUnused++;
        } elseif ($ts < $now) {
            $cExpired++;
        } else {
            $cActive++;
            if ($ts <= $weekEnd) {
                $cExpiring++;
            }
        }
    }
}

$balance = (isset($user) && isset($user->saldo)) ? (float) $user->saldo : 0.0;
?>

<div class="page-head" data-mt>
  <div>
    <div class="eyebrow">Panel</div>
    <h1>Keys</h1>
    <p class="page-head__sub">
      <?= esc($total, 'html') ?> <?= $total === 1 ? 'key' : 'keys' ?>
      <?php if ($full): ?>
        · <?= esc($cActive, 'html') ?> active
        · <?= esc($cExpiring, 'html') ?> <?= $cExpiring === 1 ? 'expires' : 'expire' ?> this week
        · <?= esc($cUnused, 'html') ?> not activated
      <?php endif; ?>
    </p>
  </div>
  <div class="spacer"></div>
  <div class="page-head__actions">
    <?php if (isset($user) && isset($user->saldo)): ?>
      <span class="chip chip--accent mono nowrap">₹<?= esc(number_format((float) $user->saldo, 2), 'html') ?></span>
    <?php endif; ?>
    <a class="btn btn--ghost" href="<?= site_url('keys/generate-random') ?>">Generate random</a>
    <a class="btn btn--primary" href="<?= site_url('keys/generate') ?>">New key</a>
  </div>
</div>

<div class="stats" data-mt>
  <div class="stat">
    <div class="stat__label">Total keys</div>
    <div class="stat__value" data-count="<?= esc($total, 'html') ?>"><?= esc($total, 'html') ?></div>
    <div class="stat__foot"><?= $full ? 'every key you can see' : 'visible to you' ?></div>
  </div>
  <div class="stat">
    <div class="stat__label">Active</div>
    <div class="stat__value" data-count="<?= esc($cActive, 'html') ?>"><?= esc($cActive, 'html') ?></div>
    <div class="stat__foot">running right now</div>
  </div>
  <div class="stat">
    <div class="stat__label">Expiring</div>
    <div class="stat__value" data-count="<?= esc($cExpiring, 'html') ?>"><?= esc($cExpiring, 'html') ?></div>
    <div class="stat__foot">inside the next 7 days</div>
  </div>
  <div class="stat">
    <div class="stat__label">Unused</div>
    <div class="stat__value" data-count="<?= esc($cUnused, 'html') ?>"><?= esc($cUnused, 'html') ?></div>
    <div class="stat__foot">never activated</div>
  </div>
</div>

<?php if ($total > 0): ?>
  <div class="card mb-5">
    <div class="card__head">
      <h3>Filters</h3>
      <div class="spacer"></div>
      <span class="mono muted" id="keyCount"><?= esc($total, 'html') ?> of <?= esc($total, 'html') ?> keys</span>
    </div>

    <div class="row row--between row--wrap gap-3">
      <div class="tabs" role="tablist" aria-label="Filter keys by status">
        <button class="tab" type="button" role="tab" data-filter="all" aria-selected="true">All</button>
        <button class="tab" type="button" role="tab" data-filter="active" aria-selected="false">Active</button>
        <button class="tab" type="button" role="tab" data-filter="expired" aria-selected="false">Expired</button>
        <button class="tab" type="button" role="tab" data-filter="blocked" aria-selected="false">Blocked</button>
      </div>

      <div class="row row--wrap gap-2">
        <?= form_open('keys/resetAl', ['method' => 'get', 'class' => 'row']) ?>
          <button class="btn btn--sm btn--ghost" type="submit" title="Clear device counts on every key">Reset devices</button>
        <?= form_close() ?>

        <?= form_open('keys/deleteUnused', ['method' => 'get', 'class' => 'row']) ?>
          <button class="btn btn--sm btn--ghost" type="submit" title="Delete keys that were never activated">Del unused</button>
        <?= form_close() ?>

        <?= form_open('keys/deleteExp', ['method' => 'get', 'class' => 'row']) ?>
          <button class="btn btn--sm btn--ghost" type="submit" title="Delete keys past their expiry">Del expired</button>
        <?= form_close() ?>

        <?php if ($isOwner): ?>
          <?= form_open('keys/DelblockKeys', ['method' => 'get', 'class' => 'row']) ?>
            <button class="btn btn--sm btn--ghost" type="submit" title="Delete blocked or banned keys">Del blocked</button>
          <?= form_close() ?>
        <?php endif; ?>

        <?php if ($isOwner): ?>
          <?= form_open('keys/delete/all', ['method' => 'get', 'class' => 'row']) ?>
            <button class="btn btn--sm btn--danger" type="submit"
                    data-confirm="Every key in the database will be deleted. This cannot be undone."
                    data-confirm-title="Delete all keys" data-confirm-danger
                    title="Delete every key">Delete all</button>
          <?= form_close() ?>
        <?php endif; ?>
      </div>
    </div>

    <div class="field mt-4 mb-0">
      <label class="label" for="keySearch">Search</label>
      <input class="input" id="keySearch" type="search" autocomplete="off" spellcheck="false"
             placeholder="Key, game, duration or date…">
    </div>
  </div>
<?php endif; ?>

<div class="table-wrap" data-mt>
  <?php if ($total > 0): ?>
    <div class="scroll-x">
      <table class="tbl">
        <thead>
          <tr>
            <?php if ($full): ?>
              <th class="tbl__num">#</th>
            <?php endif; ?>
            <th>Key</th>
            <?php if ($full): ?>
              <th>Game</th>
              <th>Status</th>
              <th class="tbl__num">Devices</th>
              <th>Duration</th>
              <th>Expires</th>
            <?php endif; ?>
            <th class="tr">Actions</th>
          </tr>
        </thead>
        <tbody id="keyRows">
          <?php foreach ($rows as $row): ?>
            <?php
            $id    = isset($row->id_keys) ? (int) $row->id_keys : 0;
            $key   = trim((string) ($row->user_key ?? ''));
            $game  = trim((string) ($row->game ?? ''));
            $state = $full ? $keyState($row) : ['unused', '', 'Not activated', false];
            $used  = $full ? $devicesUsed($row->devices ?? '') : 0;
            $maxd  = isset($row->max_devices) ? (int) $row->max_devices : 0;
            $dur   = $durationLabel($row->duration ?? 0);
            $raw   = trim((string) ($row->expired_date ?? ''));
            $ts    = $raw === '' ? 0 : (int) strtotime($raw);

            $hay = strtolower(trim($key . ' ' . $game . ' ' . $state[2] . ' ' . $dur . ' ' . $id . ' ' . ($ts ? date('d M Y', $ts) : 'not activated')));
            ?>
            <tr data-row data-state="<?= esc($state[0], 'html') ?>" data-search="<?= esc($hay, 'html') ?>">
              <?php if ($full): ?>
                <td class="tbl__num muted"><?= $id ? esc($id, 'html') : '—' ?></td>
              <?php endif; ?>

              <td>
                <?php if ($key !== ''): ?>
                  <span class="tbl__key" data-copy="<?= esc($key, 'html') ?>" role="button" tabindex="0"
                        title="Copy this key"><?= esc($key, 'html') ?></span>
                <?php else: ?>
                  <span class="muted">—</span>
                <?php endif; ?>
              </td>

              <?php if ($full): ?>
                <td class="nowrap"><?= $game !== '' ? esc($game, 'html') : '<span class="muted">—</span>' ?></td>

                <td>
                  <span class="chip <?= esc($state[1], 'html') ?>">
                    <?php if ($state[3]): ?><i class="dot dot--live"></i><?php endif; ?>
                    <?= esc($state[2], 'html') ?>
                  </span>
                </td>

                <td class="tbl__num" data-used><?= esc($used, 'html') ?>/<?= esc($maxd, 'html') ?></td>

                <td class="nowrap"><?= esc($dur, 'html') ?></td>

                <td class="nowrap">
                  <?php if ($ts > 0): ?>
                    <?= esc(date('d M Y · H:i', $ts), 'html') ?>
                  <?php else: ?>
                    <span class="muted">Not activated</span>
                  <?php endif; ?>
                </td>
              <?php endif; ?>

              <td>
                <div class="row row--end gap-2">
                  <?php if ($canExtend && $key !== ''): ?>
                    <button class="btn btn--sm btn--ghost" type="button" data-extend="<?= esc($key, 'html') ?>"
                            title="Add hours or days to this key">Extend</button>
                  <?php endif; ?>

                  <?php if ($full && $id > 0): ?>
                    <a class="btn btn--sm btn--ghost" href="<?= site_url('keys/' . $id) ?>" title="Edit this key">Edit</a>
                  <?php endif; ?>

                  <?php if ($key !== ''): ?>
                    <button class="btn btn--sm btn--ghost" type="button" data-reset="<?= esc($key, 'html') ?>"
                            title="Reset the device count for this key">Reset</button>

                    <?= form_open('keys/resetAll', ['method' => 'get', 'class' => 'row']) ?>
                      <input type="hidden" name="userkey" value="<?= esc($key, 'html') ?>">
                      <button class="btn btn--sm btn--danger" type="submit"
                              data-confirm="Key <?= esc($key, 'html') ?> will be deleted permanently."
                              data-confirm-title="Delete key" data-confirm-danger
                              title="Delete this key">Delete</button>
                    <?= form_close() ?>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="empty" id="keyEmpty" hidden>
      <div class="empty__mark">⌕</div>
      <p class="mb-0">No key matches this filter.</p>
    </div>
  <?php else: ?>
    <div class="empty">
      <div class="empty__mark">⚿</div>
      <p>No keys in the database yet.</p>
      <p class="mb-0">
        <a href="<?= site_url('keys/generate') ?>">Generate the first one →</a>
      </p>
    </div>
  <?php endif; ?>
</div>

<?php if ($total > 0 && $canExtend): ?>
  <dialog class="dlg" id="extendDlg" aria-labelledby="extendTitle">
    <div class="dlg__head">
      <div>
        <h3 id="extendTitle">Extend key</h3>
        <p class="muted mono" id="extendKeyLabel"></p>
      </div>
      <button class="dlg__x" type="button" data-dlg-close aria-label="Close">✕</button>
    </div>

    <?= form_open('ExtendDuration', ['method' => 'post']) ?>
      <input type="hidden" name="extend_single_form" value="1">
      <input type="hidden" name="user_key" id="extendUserKey" value="">

      <div class="dlg__body">
        <div class="field mb-0">
          <label class="label" for="extendDuration">Add duration</label>
          <input class="input input--mono" type="text" id="extendDuration" name="duration"
                 placeholder="30D or 12H" autocomplete="off" spellcheck="false" required>
          <div class="hint">A number then <span class="mono">D</span> for days or <span class="mono">H</span> for hours.</div>
        </div>
      </div>

      <div class="dlg__foot">
        <button class="btn btn--ghost" type="button" data-dlg-close>Cancel</button>
        <button class="btn btn--primary" type="submit" data-press>Extend</button>
      </div>
    <?= form_close() ?>
  </dialog>
<?php endif; ?>

<?= $this->endSection() ?>

<?= $this->section('script') ?>
<script>
  (function () {
    var RESET_URL   = <?= json_encode(site_url('keys/reset')) ?>;
    var rowNodes    = Array.prototype.slice.call(document.querySelectorAll('[data-row]'));
    var tabs        = Array.prototype.slice.call(document.querySelectorAll('[data-filter]'));
    var search      = document.getElementById('keySearch');
    var counter     = document.getElementById('keyCount');
    var emptyBox    = document.getElementById('keyEmpty');
    var current     = 'all';

    /* ── copy a key ─────────────────────────────────────────────────── */
    function copyText(value) {
      if (navigator.clipboard && navigator.clipboard.writeText) {
        return navigator.clipboard.writeText(value);
      }
      var ta = document.createElement('textarea');
      ta.value = value;
      ta.setAttribute('readonly', '');
      ta.style.position = 'fixed';
      ta.style.opacity = '0';
      document.body.appendChild(ta);
      ta.select();
      try { document.execCommand('copy'); } catch (e) {}
      ta.remove();
      return Promise.resolve();
    }

    Array.prototype.forEach.call(document.querySelectorAll('[data-copy]'), function (el) {
      el.addEventListener('click', function () {
        var key = el.getAttribute('data-copy');
        copyText(key).then(function () {
          App.toast('Key copied to the clipboard.', 'good', 'Copied');
        });
      });
      el.addEventListener('keydown', function (ev) {
        if (ev.key === 'Enter' || ev.key === ' ') { ev.preventDefault(); el.click(); }
      });
    });

    /* ── reset the device count of one key ──────────────────────────── */
    Array.prototype.forEach.call(document.querySelectorAll('[data-reset]'), function (btn) {
      btn.addEventListener('click', function () {
        var key  = btn.getAttribute('data-reset');
        var cell = btn.closest('td');
        btn.disabled = true;

        fetch(RESET_URL + '?userkey=' + encodeURIComponent(key) + '&reset=1', {
          headers: { 'Accept': 'application/json' },
          credentials: 'same-origin'
        })
          .then(function (r) { return r.json(); })
          .then(function (d) {
            btn.disabled = false;
            if (d && d.registered && d.reset) {
              var out = cell ? cell.querySelector('[data-used]') : null;
              if (out) { out.textContent = '0/' + d.devices_max; }
              App.toast('Device count reset.', 'good', 'Done');
            } else if (d && d.devices_total) {
              App.toast('Access denied for this key.', 'bad', 'Failed');
            } else {
              App.toast('The device list is already empty.', 'warn', 'Nothing to reset');
            }
          })
          .catch(function () {
            btn.disabled = false;
            App.toast('The server did not answer.', 'bad', 'Failed');
          });
      });
    });

    /* ── extend dialog ──────────────────────────────────────────────── */
    var dlg = document.getElementById('extendDlg');
    if (dlg) {
      var dlgKey   = document.getElementById('extendUserKey');
      var dlgLabel = document.getElementById('extendKeyLabel');
      var dlgInput = document.getElementById('extendDuration');

      Array.prototype.forEach.call(document.querySelectorAll('[data-extend]'), function (btn) {
        btn.addEventListener('click', function () {
          var key = btn.getAttribute('data-extend');
          if (dlgKey) { dlgKey.value = key; }
          if (dlgLabel) { dlgLabel.textContent = key; }
          if (dlgInput) { dlgInput.value = ''; }
          dlg.showModal();
        });
      });

      Array.prototype.forEach.call(dlg.querySelectorAll('[data-dlg-close]'), function (btn) {
        btn.addEventListener('click', function () { dlg.close(); });
      });
    }

    /* ── tabs + search ──────────────────────────────────────────────── */
    function matches(row, filter) {
      if (filter === 'all') { return true; }
      var state = row.getAttribute('data-state');
      if (filter === 'active') { return state === 'active' || state === 'unused'; }
      return state === filter;
    }

    function apply() {
      var q = search ? search.value.trim().toLowerCase() : '';
      var shown = 0;

      rowNodes.forEach(function (row) {
        var okState = matches(row, current);
        var hay = row.getAttribute('data-search') || '';
        var okText = q === '' || hay.indexOf(q) !== -1;
        row.hidden = !(okState && okText);
        if (!row.hidden) { shown++; }
      });

      if (counter) {
        counter.textContent = shown + ' of ' + rowNodes.length + ' keys';
      }
      if (!emptyBox) { return; }

      if (shown === 0) {
        if (emptyBox.hidden) {
          emptyBox.hidden = false;
          MT.enter(emptyBox, { dist: 8 });
        }
      } else if (!emptyBox.hidden) {
        var anim = MT.leave(emptyBox);
        if (anim && anim.finished) { anim.finished.then(function () { emptyBox.hidden = true; }); }
        else { emptyBox.hidden = true; }
      }
    }

    tabs.forEach(function (tab) {
      tab.addEventListener('click', function () {
        tabs.forEach(function (t) { t.setAttribute('aria-selected', 'false'); });
        tab.setAttribute('aria-selected', 'true');
        current = tab.getAttribute('data-filter');
        apply();
      });
    });

    if (search) { search.addEventListener('input', apply); }
  })();
</script>
<?= $this->endSection() ?>