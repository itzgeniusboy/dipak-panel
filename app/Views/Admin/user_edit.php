<?php

/**
 * Admin · edit a user
 *
 * Field names are read by User::user_edit_action() — do not rename them:
 *   user_id (hidden) username fullname level status saldo uplink expiration
 *   telegram_chat_id (owner only, display + carry for the OTP settings)
 *
 * @var object      $target     the account being edited
 * @var object      $user       the acting user
 * @var \CodeIgniter\Validation\Services $validation
 */

$target      = $target ?? null;
$actingLevel = isset($user) ? (int) $user->level : 3;

$selLevel  = ['' => '— Select role —', '1' => 'Owner', '2' => 'Admin', '3' => 'Reseller'];
$selStatus = ['' => '— Select status —', '1' => 'Active', '2' => 'Banned', '3' => 'Expired'];

$curLevel  = (int) ($target->level ?? 3);
$curStatus = (int) ($target->status ?? 0);

// The expiry meter: how much of a 30 day window is left, as a percentage.
$expTs  = $target ? strtotime((string) ($target->expiration_date ?? '')) : false;
$days   = $expTs ? (int) floor(($expTs - time()) / 86400) : null;
$pct    = $days === null ? 0 : max(0, min(100, (int) round(($days / 30) * 100)));
$meterMod = $days === null ? '' : ($days <= 0 ? ' meter--bad' : ($days <= 7 ? '' : ' meter--good'));

$joined = !empty($target->created_at) ? date('Y-m-d H:i', strtotime((string) $target->created_at)) : '—';

?>
<?= $this->extend('Layout/Starter') ?>

<?= $this->section('title', 'Edit user') ?>

<?= $this->section('content') ?>

<div class="page-head" data-mt>
  <div>
    <div class="eyebrow">Panel</div>
    <h1><?= esc($target->username ?? 'User', 'html') ?></h1>
    <p class="page-head__sub">
      <?= esc($target->fullname ?? 'No name on file', 'html') ?> ·
      joined <?= esc($joined, 'html') ?>
    </p>
  </div>
  <div class="spacer"></div>
  <div class="page-head__actions">
    <a class="btn btn--ghost" href="<?= site_url('admin/manage-users') ?>">Back to users</a>
    <?php if ((int) ($target->id_users ?? 0) > 0): ?>
      <a class="btn" href="<?= site_url('user/' . (int) $target->id_users) ?>">Reload</a>
    <?php endif; ?>
  </div>
</div>

<?php if (! $target): ?>
  <div class="alert alert--bad" data-flash><span>That account no longer exists.</span></div>
<?php else: ?>

<?= form_open() ?>
  <input type="hidden" name="user_id" value="<?= esc((int) $target->id_users, 'html') ?>">

  <div class="stack" data-mt>

    <div class="card">
      <div class="card__head">
        <h3>Identity</h3>
        <div class="spacer"></div>
        <span class="chip">#<?= esc((int) $target->id_users, 'html') ?></span>
      </div>

      <div class="field-row">
        <div class="field">
          <label class="label" for="username">Username</label>
          <input class="input input--mono" type="text" id="username" name="username"
                 autocomplete="off" spellcheck="false" autocapitalize="none"
                 value="<?= esc(old('username') ?: ($target->username ?? ''), 'html') ?>">
          <?php if ($validation->hasError('username')): ?>
            <p class="error-text"><?= esc($validation->getError('username'), 'html') ?></p>
          <?php else: ?>
            <p class="hint">4–25 letters and numbers. Letters only, no spaces.</p>
          <?php endif; ?>
        </div>

        <div class="field">
          <label class="label" for="fullname">Fullname</label>
          <input class="input" type="text" id="fullname" name="fullname"
                 autocomplete="off"
                 value="<?= esc(old('fullname') ?: ($target->fullname ?? ''), 'html') ?>">
          <?php if ($validation->hasError('fullname')): ?>
            <p class="error-text"><?= esc($validation->getError('fullname'), 'html') ?></p>
          <?php else: ?>
            <p class="hint">Optional. Shown next to the username.</p>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card__head">
        <h3>Balance</h3>
        <div class="spacer"></div>
        <span class="chip chip--accent">saldo</span>
      </div>

      <div class="field">
        <label class="label" for="saldo">Saldo</label>
        <input class="input input--mono" type="number" id="saldo" name="saldo"
               inputmode="numeric" min="0" step="1"
               value="<?= esc(old('saldo') ?: ($target->saldo ?? 0), 'html') ?>">
        <?php if ($validation->hasError('saldo')): ?>
          <p class="error-text"><?= esc($validation->getError('saldo'), 'html') ?></p>
        <?php else: ?>
          <p class="hint">Written as an absolute value — anything below 1 is stored as 0.</p>
        <?php endif; ?>
      </div>
    </div>

    <div class="card">
      <div class="card__head">
        <h3>Access</h3>
        <div class="spacer"></div>
        <?php if ($actingLevel === 1): ?>
          <span class="chip chip--accent">owner scope</span>
        <?php else: ?>
          <span class="chip">reseller scope</span>
        <?php endif; ?>
      </div>

      <div class="field-row">
        <div class="field">
          <label class="label" for="level">Role</label>
          <?= form_dropdown(['class' => 'select', 'name' => 'level', 'id' => 'level'], $selLevel, old('level') ?: $curLevel) ?>
          <?php if ($validation->hasError('level')): ?>
            <p class="error-text"><?= esc($validation->getError('level'), 'html') ?></p>
          <?php endif; ?>
        </div>

        <div class="field">
          <label class="label" for="uplink">Uplink</label>
          <input class="input input--mono" type="text" id="uplink" name="uplink"
                 autocomplete="off" spellcheck="false" autocapitalize="none"
                 value="<?= esc(old('uplink') ?: ($target->uplink ?? ''), 'html') ?>">
          <?php if ($validation->hasError('uplink')): ?>
            <p class="error-text"><?= esc($validation->getError('uplink'), 'html') ?></p>
          <?php else: ?>
            <p class="hint">The referrer username this account belongs to.</p>
          <?php endif; ?>
        </div>
      </div>

      <div class="field">
        <span class="label">Status</span>
        <div class="row row--between row--wrap gap-3">
          <label class="switch">
            <input type="checkbox" id="status-live" data-status-live <?= $curStatus === 1 ? 'checked' : '' ?>>
            <span class="switch__track"><span class="switch__knob"></span></span>
            <span>Account can sign in</span>
          </label>

          <div class="tabs" role="radiogroup" aria-label="Account status" data-status-tabs>
            <?php foreach ($selStatus as $val => $label): ?>
              <?php if ($val === '') { continue; } ?>
              <button type="button" class="tab" role="radio" data-status="<?= esc($val, 'html') ?>"
                      aria-selected="<?= $curStatus === (int) $val ? 'true' : 'false' ?>">
                <?= esc($label, 'html') ?>
              </button>
            <?php endforeach; ?>
          </div>
        </div>
        <input type="hidden" name="status" id="status" value="<?= esc(old('status') ?: $curStatus, 'html') ?>">
        <p class="hint">Banning an account also blocks every key it generated.</p>
      </div>

      <div class="field">
        <label class="label" for="expiration">Expiration</label>
        <input class="input input--mono" type="text" id="expiration" name="expiration"
               autocomplete="off" spellcheck="false"
               value="<?= esc(old('expiration') ?: ($target->expiration_date ?? ''), 'html') ?>">
        <?php if ($validation->hasError('expiration')): ?>
          <p class="error-text"><?= esc($validation->getError('expiration'), 'html') ?></p>
        <?php else: ?>
          <div class="meter<?= esc($meterMod, 'html') ?>" data-progress="<?= esc($pct, 'html') ?>">
            <div class="meter__fill"></div>
          </div>
          <p class="hint">
            <?php if ($days === null): ?>
              No expiry date on file.
            <?php elseif ($days < 0): ?>
              Expired <?= esc(abs($days), 'html') ?> day<?= abs($days) === 1 ? '' : 's' ?> ago.
            <?php else: ?>
              <?= esc($days, 'html') ?> day<?= $days === 1 ? '' : 's' ?> left<?= $days >= 30 ? ' — past the 30 day window' : '' ?>.
            <?php endif; ?>
          </p>
        <?php endif; ?>
      </div>

      <?php if ($actingLevel === 1): ?>
        <div class="field mb-0">
          <label class="label" for="telegram_chat_id">Telegram chat ID</label>
          <input class="input input--mono" type="text" id="telegram_chat_id" name="telegram_chat_id"
                 inputmode="numeric" autocomplete="off"
                 value="<?= esc(old('telegram_chat_id') ?: ($target->telegram_chat_id ?? ''), 'html') ?>">
          <?php if ($validation->hasError('telegram_chat_id')): ?>
            <p class="error-text"><?= esc($validation->getError('telegram_chat_id'), 'html') ?></p>
          <?php else: ?>
            <p class="hint">Owner only — where OTP codes are delivered. Saved from Settings → Telegram.</p>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>

    <div class="row row--end gap-2">
      <a class="btn btn--ghost" href="<?= site_url('admin/manage-users') ?>">Cancel</a>
      <button type="submit" class="btn btn--primary" data-press>Save profile</button>
    </div>

  </div>
<?= form_close() ?>

<?php endif; ?>

<?= $this->endSection() ?>

<?= $this->section('script') ?>
<script>
  (function () {
    var hidden = document.getElementById('status');
    var live   = document.getElementById('status-live');
    var tabs   = document.querySelectorAll('[data-status-tabs] .tab');
    if (!hidden || !tabs.length) return;

    var paint = function (value) {
      hidden.value = value;
      tabs.forEach(function (t) {
        t.setAttribute('aria-selected', t.getAttribute('data-status') === String(value) ? 'true' : 'false');
      });
      if (live) live.checked = String(value) === '1';
    };

    tabs.forEach(function (t) {
      t.addEventListener('click', function () { paint(t.getAttribute('data-status')); });
    });

    if (live) {
      live.addEventListener('change', function () { paint(live.checked ? '1' : '2'); });
    }

    paint(hidden.value);
  })();
</script>
<?= $this->endSection() ?>