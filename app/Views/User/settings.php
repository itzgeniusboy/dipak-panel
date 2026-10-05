<?php
// Controller -> $currentUsername when available, else the row, else the session.
$currentUsername = isset($currentUsername) && $currentUsername
    ? $currentUsername
    : (isset($user->username) ? $user->username : (session('username') ?? ''));

$hasError = function ($field) use (&$validation) {
    return isset($validation) && $validation->hasError($field);
};
$errorOf = function ($field) use (&$validation) {
    return isset($validation) ? $validation->getError($field) : '';
};

$chatId   = isset($user->telegram_chat_id) ? (string) $user->telegram_chat_id : '';
$twofaOn  = ! empty($user->twofa_enabled);
$fullName = (string) ($user->fullname ?? '');

// Plain session data, not flashdata — read it once so a toast never repeats.
$notes = [];
foreach (['msgSuccess' => 'good', 'msgDanger' => 'bad', 'msgWarning' => 'warn', 'error' => 'bad', 'success' => 'good'] as $key => $kind) {
    $val = (string) (session($key) ?? '');
    if ($val !== '') {
        $notes[] = [$val, $kind];
        session()->remove($key);
    }
}
?>

<?= $this->extend('Layout/Starter') ?>

<?= $this->section('title') ?><?= esc('Settings', 'html') ?><?= $this->endSection() ?>

<?= $this->section('content') ?>

  <div class="page-head" data-mt>
    <div>
      <div class="eyebrow">Account</div>
      <h1>Settings</h1>
      <p class="page-head__sub">
        Signed in as <span class="mono"><?= esc((string) $currentUsername, 'html') ?></span> ·
        <?= esc(getLevel($user->level ?? 0), 'html') ?>
      </p>
    </div>
    <div class="spacer"></div>
    <div class="page-head__actions">
      <a class="btn btn--ghost" href="<?= site_url('twofa') ?>">Two-factor</a>
      <a class="btn" href="<?= site_url('dashboard') ?>" data-press>Back to panel</a>
    </div>
  </div>

  <div class="stack" data-mt>

    <div class="card card--glow">
      <div class="card__head">
        <h3>Profile</h3>
        <span class="spacer"></span>
        <span class="chip chip--accent"><?= esc(getLevel($user->level ?? 0), 'html') ?></span>
      </div>

      <?= form_open() ?>
        <input type="hidden" name="fullname_form" value="1">

        <div class="field-row">
          <div class="field">
            <label class="label" for="fullname">Full name</label>
            <input class="input" type="text" name="fullname" id="fullname"
                   maxlength="155" spellcheck="false"
                   value="<?= esc((string) (old('fullname') ?: $fullName), 'html') ?>"
                   placeholder="Your name">
            <?php if ($hasError('fullname')): ?>
              <div class="error-text"><?= esc((string) $errorOf('fullname'), 'html') ?></div>
            <?php endif; ?>
          </div>

          <div class="field">
            <label class="label" for="username_ro">Username</label>
            <input class="input input--mono" type="text" id="username_ro"
                   value="<?= esc((string) $currentUsername, 'html') ?>" disabled>
            <div class="hint">Your sign-in handle — change it in the card below.</div>
          </div>
        </div>

        <button type="submit" class="btn btn--primary" data-press>Save profile</button>
      <?= form_close() ?>
    </div>

    <div class="card">
      <div class="card__head">
        <h3>Username</h3>
        <span class="spacer"></span>
        <span class="eyebrow">Sign-in handle</span>
      </div>

      <?= form_open('settings/update-username') ?>
        <div class="field">
          <label class="label" for="new_username">New username</label>
          <input class="input input--mono" type="text" name="new_username" id="new_username"
                 maxlength="32" autocomplete="off" spellcheck="false" autocapitalize="none" required
                 value="<?= esc((string) old('new_username'), 'html') ?>"
                 placeholder="e.g. ravan">
          <?php if ($hasError('new_username')): ?>
            <div class="error-text"><?= esc((string) $errorOf('new_username'), 'html') ?></div>
          <?php endif; ?>
          <div class="hint">3–32 characters: letters, numbers, dot, underscore or hyphen.</div>
        </div>

        <button type="submit" class="btn btn--primary" data-press>Update username</button>
      <?= form_close() ?>
    </div>

    <div class="card">
      <div class="card__head">
        <h3>Password</h3>
        <span class="spacer"></span>
        <span class="eyebrow">Security</span>
      </div>

      <?= form_open() ?>
        <input type="hidden" name="password_form" value="1">

        <div class="field">
          <label class="label" for="current">Current password</label>
          <input class="input" type="password" name="current" id="current"
                 autocomplete="current-password" placeholder="••••••••"
                 value="<?= esc((string) old('current'), 'html') ?>">
          <?php if ($hasError('current')): ?>
            <div class="error-text"><?= esc((string) $errorOf('current'), 'html') ?></div>
          <?php endif; ?>
        </div>

        <div class="field-row">
          <div class="field">
            <label class="label" for="password">New password</label>
            <input class="input" type="password" name="password" id="password"
                   autocomplete="new-password" placeholder="At least 6 characters">
            <?php if ($hasError('password')): ?>
              <div class="error-text"><?= esc((string) $errorOf('password'), 'html') ?></div>
            <?php endif; ?>
          </div>

          <div class="field">
            <label class="label" for="password2">Confirm password</label>
            <input class="input" type="password" name="password2" id="password2"
                   autocomplete="new-password" placeholder="Repeat it">
            <?php if ($hasError('password2')): ?>
              <div class="error-text"><?= esc((string) $errorOf('password2'), 'html') ?></div>
            <?php endif; ?>
          </div>
        </div>

        <div class="row row--wrap gap-2">
          <button type="submit" class="btn btn--primary" data-press>Update password</button>
          <button type="button" class="btn btn--ghost btn--sm" data-reveal-pw aria-pressed="false">Show passwords</button>
        </div>
      <?= form_close() ?>
    </div>

    <div class="card">
      <div class="card__head">
        <h3>Preferences</h3>
        <span class="spacer"></span>
        <?php if ($twofaOn): ?>
          <span class="chip chip--good"><span class="dot dot--live"></span>2FA on</span>
        <?php else: ?>
          <span class="chip">2FA off</span>
        <?php endif; ?>
      </div>

      <?= form_open() ?>
        <input type="hidden" name="twofa_form" value="1">
        <input type="hidden" name="twofa" value="off">

        <div class="row row--between mb-4">
          <div>
            <div class="label">Two-step verification</div>
            <div class="hint">
              <?php if ($chatId): ?>
                One-time codes go to Telegram chat <span class="mono"><?= esc($chatId, 'html') ?></span>.
              <?php else: ?>
                Save a Telegram chat ID below, then this switch becomes live.
              <?php endif; ?>
            </div>
          </div>
          <label class="switch">
            <input type="checkbox" name="twofa" id="twofa_pref" value="on"
                   <?= $twofaOn ? 'checked' : '' ?> <?= $chatId ? '' : 'disabled' ?>>
            <span class="switch__track"><span class="switch__knob"></span></span>
          </label>
        </div>
      <?= form_close() ?>

      <?= form_open() ?>
        <input type="hidden" name="telegram_form" value="1">

        <div class="field">
          <label class="label" for="telegram_chat_id">Telegram chat ID</label>
          <input class="input input--mono" type="text" name="telegram_chat_id" id="telegram_chat_id"
                 inputmode="numeric" autocomplete="off"
                 value="<?= esc((string) (old('telegram_chat_id') ?: $chatId), 'html') ?>"
                 placeholder="e.g. 123456789">
          <div class="hint">
            Start the bot first, then ask @userinfobot for your chat ID. Clearing it switches two-step verification off.
          </div>
        </div>

        <button type="submit" class="btn btn--primary" data-press>Save chat ID</button>
      <?= form_close() ?>

      <?php if ($chatId !== ''): ?>
        <?= form_open() ?>
          <input type="hidden" name="telegram_form" value="1">
          <input type="hidden" name="telegram_chat_id" value="">
          <div class="mt-3">
            <button type="submit" class="btn btn--danger"
                    data-confirm="Remove your Telegram chat ID? Two-step verification will be switched off."
                    data-confirm-danger
                    data-confirm-ok="Remove">Remove chat ID</button>
          </div>
        <?= form_close() ?>
      <?php endif; ?>

      <div class="row row--wrap gap-2 mt-4">
        <a class="btn btn--ghost" href="https://t.me/Panelauthentication_bot" target="_blank" rel="noopener">Open the bot</a>
        <a class="btn btn--ghost" href="<?= site_url('twofa') ?>">Full two-factor setup</a>
      </div>
    </div>

  </div>

<?= $this->endSection() ?>

<?= $this->section('script') ?>
<script>
  // Reveal the three password fields. No framework, no re-render.
  (function () {
    var btn = document.querySelector('[data-reveal-pw]');
    if (!btn) return;
    var fields = ['current', 'password', 'password2']
      .map(function (id) { return document.getElementById(id); })
      .filter(Boolean);
    btn.addEventListener('click', function () {
      var show = btn.getAttribute('aria-pressed') !== 'true';
      fields.forEach(function (f) { f.type = show ? 'text' : 'password'; });
      btn.setAttribute('aria-pressed', show ? 'true' : 'false');
      btn.textContent = show ? 'Hide passwords' : 'Show passwords';
    });
  })();

  // The two-step switch posts the moment it changes: the hidden "off" above is the
  // default, the checked box arrives after it, so last-one-wins is the right answer.
  (function () {
    var sw = document.getElementById('twofa_pref');
    if (!sw || sw.disabled) return;
    sw.addEventListener('change', function () {
      var form = sw.closest('form');
      if (form) form.requestSubmit();
    });
  })();

  // Flash strings the shell does not render.
  (function () {
    var notes = <?= json_encode($notes, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    notes.forEach(function (n) { App.toast(n[0], n[1]); });
  })();
</script>
<?= $this->endSection() ?>