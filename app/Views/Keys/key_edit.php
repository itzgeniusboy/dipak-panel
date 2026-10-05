<?= $this->extend('Layout/Starter') ?>

<?= $this->section('title') ?>Edit key<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php
$level = isset($user) ? (int) $user->level : 0;
$mayEdit = ($level === 1 || $level === 2);   // owner + admin own these columns

$id       = isset($key->id_keys) ? (int) $key->id_keys : 0;
$userKey  = trim((string) ($key->user_key ?? ''));
$gameName = trim((string) ($key->game ?? ''));
$maxd     = (int) ($key->max_devices ?? 0);
$hours    = (int) ($key->duration ?? 0);

$used   = isset($key_info) ? (int) $key_info->total : 0;
$deviceList = isset($key_info) ? (string) $key_info->devices : '';

$rawExp = trim((string) ($key->expired_date ?? ''));
$tsExp  = $rawExp === '' ? 0 : (int) strtotime($rawExp);
$now    = time();

if ((string) ($key->status ?? '') === '0') {
    $state    = 'blocked';
    $chip     = 'chip--bad';
    $stateTxt = 'Blocked';
} elseif ($tsExp === 0) {
    $state    = 'unused';
    $chip     = '';
    $stateTxt = 'Not activated';
} elseif ($tsExp < $now) {
    $state    = 'expired';
    $chip     = 'chip--warn';
    $stateTxt = 'Expired';
} else {
    $state    = 'active';
    $chip     = 'chip--good';
    $stateTxt = 'Active';
}

$live        = ($state === 'active');
$daysLeft    = ($tsExp > 0) ? (int) floor(($tsExp - $now) / 86400) : 0;
$pct         = ($maxd > 0) ? max(0, min(100, (int) round(($used / $maxd) * 100))) : 0;
$meterClass  = ($maxd > 0 && $used >= $maxd) ? 'meter--bad' : 'meter--good';

$deviceRows = max(3, ($used > $maxd) ? 3 : $used);
$hasErrors  = false;
if (isset($validation)) {
    $errs = $validation->getErrors();
    $hasErrors = is_array($errs) && count($errs) > 0;
}

$devices = $used ? $deviceList : '';
?>

<div class="page-head" data-mt>
  <div>
    <div class="eyebrow">Panel</div>
    <h1>Edit key</h1>
    <p class="page-head__sub">
      <span class="mono"><?= esc($userKey, 'html') ?></span>
      <?php if ($gameName !== ''): ?>
        · <?= esc($gameName, 'html') ?>
      <?php endif; ?>
      · registered by <?= esc($key->registrator ?? '—', 'html') ?>
    </p>
  </div>
  <div class="spacer"></div>
  <div class="page-head__actions">
    <a class="btn btn--ghost" href="<?= site_url('keys/generate') ?>">New key</a>
    <a class="btn" href="<?= site_url('keys') ?>">All keys</a>
  </div>
</div>

<div class="stats" data-mt>
  <div class="stat">
    <div class="stat__label">Status</div>
    <div class="stat__value">
      <span class="chip <?= esc($chip, 'html') ?>">
        <?php if ($live): ?><i class="dot dot--live"></i><?php endif; ?>
        <?= esc($stateTxt, 'html') ?>
      </span>
    </div>
    <div class="stat__foot">id <?= esc($id, 'html') ?></div>
  </div>

  <div class="stat">
    <div class="stat__label">Devices bound</div>
    <div class="stat__value" data-count="<?= esc($used, 'html') ?>"><?= esc($used, 'html') ?></div>
    <div class="stat__foot">of <?= esc($maxd, 'html') ?> allowed</div>
  </div>

  <div class="stat">
    <div class="stat__label">Duration</div>
    <div class="stat__value" data-count="<?= esc($hours, 'html') ?>"><?= esc($hours, 'html') ?></div>
    <div class="stat__foot">hours of runtime</div>
  </div>

  <div class="stat">
    <div class="stat__label">Time left</div>
    <?php if ($tsExp > 0): ?>
      <div class="stat__value" data-count="<?= esc(max(0, $daysLeft), 'html') ?>"><?= esc(max(0, $daysLeft), 'html') ?></div>
      <div class="stat__foot">days · <?= esc(date('d M Y · H:i', $tsExp), 'html') ?></div>
    <?php else: ?>
      <div class="stat__value">—</div>
      <div class="stat__foot">clock has not started</div>
    <?php endif; ?>
  </div>
</div>

<div class="stack">
  <div class="card" data-mt>
    <div class="card__head">
      <h3>Edit</h3>
      <div class="spacer"></div>
      <?php if ($hasErrors): ?>
        <span class="chip chip--warn"><?= esc(count($validation->getErrors()), 'html') ?> <?= count($validation->getErrors()) === 1 ? 'field' : 'fields' ?> to fix</span>
      <?else: ?>
        <span class="chip"><?= $mayEdit ? 'full access' : 'status only' ?></span>
      <?php endif; ?>
    </div>

    <?= form_open('keys/edit') ?>
      <input type="hidden" name="id_keys" value="<?= esc($id, 'html') ?>">

      <?php if ($mayEdit): ?>
        <div class="field-row">
          <div class="field">
            <label class="label" for="game">Game</label>
            <input class="input" type="text" name="game" id="game" autocomplete="off"
                   value="<?= esc(old('game') ?: ($key->game ?? ''), 'html') ?>" placeholder="Game name">
            <?php if ($validation->hasError('game')): ?>
              <div class="error-text"><?= esc($validation->getError('game'), 'html') ?></div>
            <?php endif; ?>
          </div>

          <div class="field">
            <label class="label" for="user_key">License key</label>
            <input class="input input--mono" type="text" name="user_key" id="user_key" autocomplete="off"
                   spellcheck="false" autocapitalize="off"
                   value="<?= esc(old('user_key') ?: $userKey, 'html') ?>" placeholder="RandomKey">
            <?php if ($validation->hasError('user_key')): ?>
              <div class="error-text"><?= esc($validation->getError('user_key'), 'html') ?></div>
            <?php endif; ?>
          </div>
        </div>

        <div class="field-row">
          <div class="field">
            <label class="label" for="duration">Duration <span class="muted">hours</span></label>
            <input class="input" type="number" name="duration" id="duration" min="1" step="1" inputmode="numeric"
                   value="<?= esc(old('duration') ?: $hours, 'html') ?>" placeholder="24">
            <?php if ($validation->hasError('duration')): ?>
              <div class="error-text"><?= esc($validation->getError('duration'), 'html') ?></div>
            <?php endif; ?>
          </div>

          <div class="field">
            <label class="label" for="max_devices">Max devices</label>
            <input class="input" type="number" name="max_devices" id="max_devices" min="1" step="1" inputmode="numeric"
                   value="<?= esc(old('max_devices') ?: $maxd, 'html') ?>" placeholder="3">
            <?php if ($validation->hasError('max_devices')): ?>
              <div class="error-text"><?= esc($validation->getError('max_devices'), 'html') ?></div>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>

      <div class="field-row">
        <div class="field">
          <label class="label" for="status">Status</label>
          <?php
          $sel_status = [
              ''  => '— Select status —',
              '0' => 'Banned / Blocked',
              '1' => 'Active',
          ];
          ?>
          <?= form_dropdown(['class' => 'select', 'name' => 'status', 'id' => 'status'], $sel_status, old('status') ?? (string) ($key->status ?? '')) ?>
          <?php if ($validation->hasError('status')): ?>
            <div class="error-text"><?= esc($validation->getError('status'), 'html') ?></div>
          <?php endif; ?>
        </div>

        <?php if ($mayEdit): ?>
          <div class="field">
            <label class="label" for="registrator">Registrator</label>
            <input class="input" type="text" name="registrator" id="registrator" autocomplete="off"
                   value="<?= esc(old('registrator') ?: ($key->registrator ?? ''), 'html') ?>" placeholder="reseller">
            <?php if ($validation->hasError('registrator')): ?>
              <div class="error-text"><?= esc($validation->getError('registrator'), 'html') ?></div>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>

      <?php if ($mayEdit): ?>
        <div class="field">
          <label class="label" for="expired_date">
            Expiration
            <?php if ($tsExp === 0): ?>
              <span class="muted">— not activated yet</span>
            <?php endif; ?>
          </label>
          <input class="input input--mono" type="text" name="expired_date" id="expired_date"
                 autocomplete="off" spellcheck="false"
                 value="<?= esc(old('expired_date') ?: $rawExp, 'html') ?>"
                 placeholder="<?= esc(date('Y-m-d H:i:s'), 'html') ?>">
          <div class="hint">Format <?= esc(date('Y-m-d H:i:s'), 'html') ?>. Leave it empty to put the key back to “not activated”.</div>
          <?php if ($validation->hasError('expired_date')): ?>
            <div class="error-text"><?= esc($validation->getError('expired_date'), 'html') ?></div>
          <?php endif; ?>
        </div>

        <div class="field mb-0">
          <label class="label" for="devices">
            Devices
            <span class="chip mono nowrap" id="devBadge"><?= esc($used, 'html') ?>/<?= esc($maxd, 'html') ?></span>
            <span class="muted">— one per line</span>
          </label>
          <textarea class="textarea input--mono" name="devices" id="devices" rows="<?= esc($deviceRows, 'html') ?>"
                    spellcheck="false" autocomplete="off"
                    placeholder="No device has bound this key yet."><?= esc(old('devices') ?: $devices, 'html') ?></textarea>
          <div class="meter <?= esc($meterClass, 'html') ?> mt-3" data-progress="<?= esc($pct, 'html') ?>">
            <div class="meter__fill"></div>
          </div>
          <?php if ($validation->hasError('devices')): ?>
            <div class="error-text"><?= esc($validation->getError('devices'), 'html') ?></div>
          <?php else: ?>
            <div class="hint">Only the first “max devices” entries are stored; the rest are dropped on save.</div>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <div class="row row--end gap-2 mt-5">
        <a class="btn btn--ghost" href="<?= site_url('keys') ?>">Cancel</a>
        <button type="submit" class="btn btn--primary" id="updateBtn" data-press <?= $hasErrors ? '' : 'disabled' ?>>
          <span>Update key</span>
        </button>
      </div>
    <?= form_close() ?>
  </div>

  <div class="table-wrap" data-mt>
    <table class="tbl">
      <thead>
        <tr>
          <th>Field</th>
          <th>Value</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td class="muted">ID</td>
          <td class="tbl__num mono"><?= esc($id, 'html') ?></td>
        </tr>
        <tr>
          <td class="muted">License key</td>
          <td><span class="tbl__key"><?= esc($userKey, 'html') ?></span></td>
        </tr>
        <tr>
          <td class="muted">Game</td>
          <td><?= esc($gameName !== '' ? $gameName : '—', 'html') ?></td>
        </tr>
        <tr>
          <td class="muted">Status</td>
          <td>
            <span class="chip <?= esc($chip, 'html') ?>">
              <?php if ($live): ?><i class="dot dot--live"></i><?php endif; ?>
              <?= esc($stateTxt, 'html') ?>
            </span>
          </td>
        </tr>
        <tr>
          <td class="muted">Duration</td>
          <td class="tbl__num mono"><?= esc($hours, 'html') ?> h</td>
        </tr>
        <tr>
          <td class="muted">Max devices</td>
          <td class="tbl__num mono"><?= esc($maxd, 'html') ?></td>
        </tr>
        <tr>
          <td class="muted">Devices bound</td>
          <td class="tbl__num mono"><?= esc($used, 'html') ?></td>
        </tr>
        <tr>
          <td class="muted">Registrator</td>
          <td class="mono"><?= esc($key->registrator ?? '—', 'html') ?></td>
        </tr>
        <tr>
          <td class="muted">Expires</td>
          <td class="nowrap mono"><?= $tsExp > 0 ? esc(date('d M Y · H:i', $tsExp), 'html') : '<span class="muted">not activated</span>' ?></td>
        </tr>
        <?php if (!empty($key->created_at)): ?>
          <tr>
            <td class="muted">Created</td>
            <td class="nowrap mono"><?= esc(date('d M Y · H:i', (int) strtotime((string) $key->created_at)), 'html') ?></td>
          </tr>
        <?php endif; ?>
        <?php if (!empty($key->updated_at)): ?>
          <tr>
            <td class="muted">Last update</td>
            <td class="nowrap mono"><?= esc(date('d M Y · H:i', (int) strtotime((string) $key->updated_at)), 'html') ?></td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <div class="table-wrap" data-mt>
    <?php if ($used > 0): ?>
      <table class="tbl">
        <thead>
          <tr>
            <th class="tbl__num">#</th>
            <th>Device</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach (array_filter(array_map('trim', explode("\n", $deviceList)), function ($v) { return $v !== ''; }) as $i => $device): ?>
            <tr>
              <td class="tbl__num muted"><?= esc($i + 1, 'html') ?></td>
              <td><span class="tbl__key"><?= esc($device, 'html') ?></span></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <div class="empty">
        <div class="empty__mark">▤</div>
        <p class="mb-0">No device has bound this key yet.</p>
      </div>
    <?php endif; ?>
  </div>

  <?php if ($mayEdit && $userKey !== ''): ?>
    <div class="card" data-mt>
      <div class="card__head">
        <h3>Danger zone</h3>
      </div>
      <p class="muted">Deleting removes the key and its device list. Nothing can bring it back.</p>
      <?= form_open('keys/resetAll', ['method' => 'get', 'class' => 'row']) ?>
        <input type="hidden" name="userkey" value="<?= esc($userKey, 'html') ?>">
        <button class="btn btn--danger" type="submit"
                data-confirm="Key <?= esc($userKey, 'html') ?> will be deleted permanently."
                data-confirm-title="Delete key" data-confirm-danger>Delete this key</button>
      <?= form_close() ?>
    </div>
  <?php endif; ?>
</div>

<?= $this->endSection() ?>

<?= $this->section('script') ?>
<script>
  (function () {
    var btn      = document.getElementById('updateBtn');
    var maxIn    = document.getElementById('max_devices');
    var badge    = document.getElementById('devBadge');
    var devArea  = document.getElementById('devices');
    var used     = <?= json_encode($used) ?>;

    /* The button stays off until something actually changes. */
    document.querySelectorAll('input, select, textarea').forEach(function (el) {
      el.addEventListener('input', function () { if (btn) { btn.disabled = false; } });
      el.addEventListener('change', function () { if (btn) { btn.disabled = false; } });
    });

    if (maxIn) {
      maxIn.addEventListener('input', function () {
        var max = parseInt(maxIn.value, 10);
        if (isNaN(max) || max < 0) { max = 0; }
        if (badge) { badge.textContent = used + '/' + max; }
        if (devArea && max > 0) { devArea.rows = Math.max(used, 3); }
      });
    }
  })();
</script>
<?= $this->endSection() ?>