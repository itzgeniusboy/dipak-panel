<?= $this->extend('Layout/Auth') ?>

<?= $this->section('content') ?>

<div data-mt>
  <div class="eyebrow">Recovery</div>
  <h1 class="mt-2">Set a new password</h1>
  <p class="mb-5">
    Enter the 6-digit code we sent to your Telegram account, then choose a new
    password for this username.
  </p>

  <?php if (session()->getFlashdata('msgDanger')): ?>
    <div class="alert alert--bad" data-flash>
      <span><?= esc(session()->getFlashdata('msgDanger'), 'html') ?></span>
    </div>
  <?php endif; ?>
  <?php if (session()->getFlashdata('msgInfo')): ?>
    <div class="alert" data-flash>
      <span><?= esc(session()->getFlashdata('msgInfo'), 'html') ?></span>
    </div>
  <?php endif; ?>

  <?= form_open('reset-password') ?>

    <div class="field">
      <label class="label" for="otp">Code</label>
      <input class="input input--mono tc" type="text" name="otp" id="otp"
             autocomplete="one-time-code" inputmode="numeric"
             autocapitalize="none" spellcheck="false"
             pattern="\d{6}" maxlength="6" required
             placeholder="000000">
      <div class="hint">Six digits, case irrelevant. It expires 5 minutes after it was sent.</div>
    </div>

    <div class="field">
      <div class="row row--between mb-3">
        <label class="label" for="new_password">New password</label>
        <button type="button" class="btn btn--ghost btn--sm" data-reveal="new_password"
                aria-pressed="false" data-press>Show</button>
      </div>
      <input class="input" type="password" name="new_password" id="new_password"
             autocomplete="new-password" required
             minlength="6" maxlength="45"
             placeholder="••••••••">
    </div>

    <div class="field">
      <div class="row row--between mb-3">
        <label class="label" for="confirm_password">Confirm password</label>
        <button type="button" class="btn btn--ghost btn--sm" data-reveal="confirm_password"
                aria-pressed="false" data-press>Show</button>
      </div>
      <input class="input" type="password" name="confirm_password" id="confirm_password"
             autocomplete="new-password" required
             minlength="6" maxlength="45"
             placeholder="••••••••">
      <div class="error-text" data-match hidden>The two passwords do not match.</div>
    </div>

    <button type="submit" class="btn btn--primary btn--lg btn--block" data-press>
      <span>Reset password</span>
    </button>

  <?= form_close() ?>

  <div class="card mt-5 mb-4">
    <div class="eyebrow">What happens next</div>
    <ul class="auth__list">
      <li><span class="auth__tick">✓</span><span>The code is burned and the session that requested it is cleared</span></li>
      <li><span class="auth__tick">✓</span><span>Your device lock stays as it is — only the password changes</span></li>
      <li><span class="auth__tick">✓</span><span>A confirmation lands in Telegram so you know the reset went through</span></li>
    </ul>
  </div>

  <div class="auth__foot">
    Code expired? <a href="<?= site_url('forgot-password') ?>">Send another</a>
  </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('script') ?>
<script>
  (function () {
    // Password reveal
    document.querySelectorAll('[data-reveal]').forEach(function (btn) {
      var input = document.getElementById(btn.getAttribute('data-reveal'));
      if (!input) return;
      btn.addEventListener('click', function () {
        var show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        btn.textContent = show ? 'Hide' : 'Show';
        btn.setAttribute('aria-pressed', show ? 'true' : 'false');
        input.focus();
      });
    });

    var otp = document.getElementById('otp');
    if (otp) {
      var failed = <?= session()->getFlashdata('msgDanger') ? 'true' : 'false' ?>;
      if (failed) otp.value = '';
      otp.focus();
      otp.addEventListener('input', function () {
        var clean = otp.value.replace(/\D+/g, '');
        if (clean !== otp.value) otp.value = clean;
      });
    }

    // Live confirm check — the server still has the final word.
    var one = document.getElementById('new_password');
    var two = document.getElementById('confirm_password');
    var note = document.querySelector('[data-match]');
    if (one && two && note) {
      var check = function () {
        var bad = one.value.length > 0 && two.value.length > 0 && one.value !== two.value;
        note.hidden = !bad;
        two.setAttribute('aria-invalid', bad ? 'true' : 'false');
      };
      one.addEventListener('input', check);
      two.addEventListener('input', check);
    }
  })();
</script>
<?= $this->endSection() ?>