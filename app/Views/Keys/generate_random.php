<?= $this->extend('Layout/Starter') ?>

<?= $this->section('title') ?>Generate random key<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php
$priceMap = isset($price) ? json_decode($price, true) : array();
if (!is_array($priceMap)) {
    $priceMap = array();
}

$level   = isset($user) ? (int) $user->level : 0;
$balance = (isset($user) && isset($user->saldo)) ? (float) $user->saldo : 0.0;

$oldDevices = old('max_devices');
$maxDevices = ($oldDevices !== null && $oldDevices !== '') ? (int) $oldDevices : 1;
if ($level === 3 && $maxDevices > 2) {
    $maxDevices = 2;
}

/* Flashdata from a successful generate — read once, the session pops it. */
$fKey  = session()->getFlashdata('user_key');
$fGame = session()->getFlashdata('game');
$fDur  = session()->getFlashdata('duration');
$fDev  = session()->getFlashdata('max_devices');
$fFees = session()->getFlashdata('fees');

$cost = null;
if ($fFees !== null && $fFees !== '' && is_numeric($fFees)) {
    $cost = (float) $fFees;
} elseif ($fDur !== null && isset($priceMap[$fDur]) && $fDev !== null) {
    $cost = (float) $priceMap[$fDur] * (float) $fDev;
}
?>

<div class="page-head" data-mt>
  <div>
    <div class="eyebrow">Panel</div>
    <h1>Generate random</h1>
    <p class="page-head__sub">A fifteen character key, generated on the server. No custom value to remember.</p>
  </div>
  <div class="spacer"></div>
  <div class="page-head__actions">
    <span class="chip chip--accent mono nowrap">₹<?= esc(number_format($balance, 2), 'html') ?></span>
    <a class="btn btn--ghost" href="<?= site_url('keys/generate') ?>">Custom key</a>
    <a class="btn" href="<?= site_url('keys') ?>">All keys</a>
  </div>
</div>

<?php if ($balance <= 0): ?>
  <div class="alert alert--warn mb-5">Your balance is empty — top up before generating a key.</div>
<?php endif; ?>

<div class="stack">
  <?= form_open() ?>
    <div class="card">
      <div class="card__head">
        <h3>License</h3>
        <div class="spacer"></div>
        <span class="chip">random</span>
      </div>

      <div class="field-row">
        <div class="field">
          <label class="label" for="game">Game</label>
          <?= form_dropdown(['class' => 'select', 'name' => 'game', 'id' => 'game'], $game, old('game') ?: '') ?>
          <?php if ($validation->hasError('game')): ?>
            <div class="error-text"><?= esc($validation->getError('game'), 'html') ?></div>
          <?php endif; ?>
        </div>

        <div class="field">
          <label class="label" for="max_devices">Max devices</label>
          <input class="input" type="number" name="max_devices" id="max_devices" min="1" step="1"
                 inputmode="numeric" required placeholder="1" value="<?= esc($maxDevices, 'html') ?>">
          <?php if ($validation->hasError('max_devices')): ?>
            <div class="error-text"><?= esc($validation->getError('max_devices'), 'html') ?></div>
          <?php endif; ?>
        </div>
      </div>

      <div class="field">
        <label class="label" for="duration">Duration</label>
        <?= form_dropdown(['class' => 'select', 'name' => 'duration', 'id' => 'duration'], $duration, old('duration') ?: '') ?>
        <?php if ($validation->hasError('duration')): ?>
          <div class="error-text"><?= esc($validation->getError('duration'), 'html') ?></div>
        <?php else: ?>
          <div class="hint">Each label already carries its price per device.</div>
        <?php endif; ?>
      </div>

      <div class="mt-5">
        <button type="submit" class="btn btn--primary btn--lg btn--block" data-press>
          <span>Generate license</span>
        </button>
      </div>
    </div>

    <div class="card">
      <div class="card__head">
        <h3>Preview</h3>
        <div class="spacer"></div>
        <span class="chip mono">auto</span>
      </div>

      <div class="field mb-0">
        <label class="label" for="keyPreview">Shape of the key</label>
        <input class="input input--mono" type="text" id="keyPreview" readonly tabindex="-1" value="XXXXX-XXXXX-XXXXX">
        <div class="hint">Fifteen random letters and digits — the real value appears right after submitting.</div>
      </div>

      <div class="field mt-4 mb-0">
        <label class="label" for="estimation">Order total</label>
        <input class="input input--mono" type="text" id="estimation" readonly tabindex="-1" value="—">
        <div class="hint" id="unitHint">Device count × unit price of the chosen duration.</div>
      </div>
    </div>
  <?= form_close() ?>

  <div class="card">
    <div class="card__head">
      <h3>How it works</h3>
    </div>
    <div class="stack">
      <div class="row gap-3">
        <span class="chip mono">1</span>
        <span class="muted">The key is built by the server, so two people never collide on the same value.</span>
      </div>
      <div class="row gap-3">
        <span class="chip mono">2</span>
        <span class="muted">The total is subtracted from your balance the moment the key is created.</span>
      </div>
      <div class="row gap-3">
        <span class="chip mono">3</span>
        <span class="muted">The key shows as “not activated” until a device connects; the clock starts then.</span>
      </div>
    </div>
  </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('script') ?>
<script>
  (function () {
    var PRICE    = <?= json_encode($priceMap) ?>;
    var BALANCE  = <?= json_encode($balance) ?>;
    var RESELLER = <?= $level === 3 ? 'true' : 'false' ?>;

    var devInput = document.getElementById('max_devices');
    var durSel   = document.getElementById('duration');
    var estimate = document.getElementById('estimation');
    var unitHint = document.getElementById('unitHint');

    function money(n) { return '₹' + Number(n).toFixed(2); }

    function render() {
      var dev = parseInt(devInput && devInput.value, 10);
      if (isNaN(dev) || dev < 1) { dev = 1; }
      var unit = Number(PRICE[durSel ? durSel.value : '']);
      if (isNaN(unit)) {
        if (estimate) { estimate.value = 'Estimation error'; }
        return;
      }
      var sum = dev * unit;
      if (estimate) { estimate.value = money(sum); }
      if (unitHint) {
        unitHint.textContent = 'Charged ' + money(sum) + ' — balance left ₹' + Math.max(0, BALANCE - sum).toFixed(2) + '.';
      }
    }

    if (durSel) { durSel.addEventListener('change', render); }
    if (devInput) {
      devInput.addEventListener('input', render);
      devInput.addEventListener('blur', function () {
        var dev = parseInt(devInput.value, 10);
        if (isNaN(dev) || dev < 1) { devInput.value = '1'; }
        if (RESELLER && dev > 2) { devInput.value = '2'; }
        render();
      });
    }

    render();

    /* ── the key that was just generated ────────────────────────────── */
    var flash = {
      key:  <?= json_encode($fKey) ?>,
      game: <?= json_encode($fGame) ?>,
      dur:  <?= json_encode($fDur) ?>,
      dev:  <?= json_encode($fDev) ?>,
      cost: <?= json_encode($cost) ?>
    };

    if (flash.key) {
      var rows = [
        ['Game', flash.game || '—'],
        ['Duration', (flash.dur || '—') + ' hours'],
        ['Devices', flash.dev === null ? '—' : flash.dev],
        ['Charged', flash.cost === null ? '—' : money(flash.cost)]
      ].map(function (r) {
        return '<div class="row row--between gap-3"><span class="muted">' + r[0] +
               '</span><span class="mono">' + String(r[1]).replace(/[<>&]/g, '') + '</span></div>';
      }).join('');

      App.dialog({
        title: 'Key generated',
        text: 'Copy it now — it is also waiting on the Keys screen.',
        ok: 'Close',
        html: rows +
              '<div class="field mt-4 mb-0">' +
                '<label class="label" for="madeKey">Your key</label>' +
                '<input class="input input--mono" id="madeKey" type="text" readonly value="' +
                String(flash.key).replace(/[<>&"]/g, '') + '">' +
                '<button class="btn btn--primary btn--sm mt-3" type="button" data-copy-in="madeKey">Copy key</button>' +
              '</div>'
      });
    }

    /* Copy from inside any dialog the panel opens. */
    document.addEventListener('click', function (ev) {
      var btn = ev.target.closest('[data-copy-in]');
      if (!btn) { return; }
      var src = document.getElementById(btn.getAttribute('data-copy-in'));
      if (!src) { return; }
      var value = src.value;
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(value).then(function () {
          App.toast('Key copied to the clipboard.', 'good', 'Copied');
        });
        return;
      }
      src.removeAttribute('readonly');
      src.select();
      try { document.execCommand('copy'); } catch (e) {}
      App.toast('Key copied to the clipboard.', 'good', 'Copied');
    });
  })();
</script>
<?= $this->endSection() ?>