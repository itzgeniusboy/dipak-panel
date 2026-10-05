<?= $this->extend('Layout/Auth') ?>

<?= $this->section('content') ?>

<div data-mt>
  <div class="eyebrow">Two-factor</div>
  <h1 class="mt-2">Enter your code</h1>
  <p class="mb-5">
    We sent a 6-digit verification code to the Telegram account on your profile.
    Type it here to finish signing in.
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

  <?= form_open('verify-otp') ?>

    <div class="field">
      <label class="label" for="otp">Verification code</label>
      <input class="input input--mono tc" type="text" name="otp" id="otp"
             autocomplete="one-time-code" inputmode="numeric"
             autocapitalize="none" spellcheck="false"
             pattern="\d{6}" maxlength="6" required
             placeholder="000000">
      <div class="hint">
        The keypad above is numeric on mobile — six digits, nothing else.
      </div>
    </div>

    <button type="submit" class="btn btn--primary btn--lg btn--block" data-press>
      <span>Verify &amp; sign in</span>
    </button>

  <?= form_close() ?>

  <div class="card mt-5 mb-4">
    <div class="eyebrow">No code yet?</div>
    <ul class="auth__list">
      <li><span class="auth__tick">✓</span><span>Open the chat with the panel bot and read the latest message</span></li>
      <li><span class="auth__tick">✓</span><span>Codes expire after 5 minutes and only the newest one works</span></li>
      <li><span class="auth__tick">✓</span><span>Wrong chat ID on file? Fix it in settings or ask an admin</span></li>
    </ul>
  </div>

  <div class="auth__foot">
    Wrong account? <a href="<?= site_url('login') ?>">Start again</a>
  </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('script') ?>
<script>
  (function () {
    var otp = document.getElementById('otp');
    if (!otp) return;

    // A rejected code is worthless — clear it and put the caret back.
    var failed = <?= session()->getFlashdata('msgDanger') ? 'true' : 'false' ?>;
    if (failed) otp.value = '';
    otp.focus();

    otp.addEventListener('input', function () {
      var clean = otp.value.replace(/\D+/g, '');
      if (clean !== otp.value) otp.value = clean;
    });
  })();
</script>
<?= $this->endSection() ?>