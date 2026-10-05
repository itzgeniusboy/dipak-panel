<?= $this->extend('Layout/Auth') ?>

<?= $this->section('content') ?>

<div data-mt>
  <div class="eyebrow">Account</div>
  <h1 class="mt-2">Reset device lock</h1>
  <p class="mb-5">
    Each account is tied to the first browser that signs in. If you changed device,
    reinstalled, or cleared your browser data, the stored device no longer matches
    and sign-in is refused. This clears the stored device so you can sign in here.
  </p>

  <?php if (session()->getFlashdata('msgDanger')): ?>
    <div class="alert alert--bad" data-flash>
      <span><?= esc(session()->getFlashdata('msgDanger'), 'html') ?></span>
    </div>
  <?php endif; ?>
  <?php if (session()->getFlashdata('msgSuccess')): ?>
    <div class="alert alert--good" data-flash>
      <span><?= esc(session()->getFlashdata('msgSuccess'), 'html') ?></span>
    </div>
  <?php endif; ?>

  <div class="card mb-4">
    <div class="eyebrow">Before you reset</div>
    <ul class="auth__list">
      <li><span class="auth__tick">✓</span><span>Your password is required — it proves the account is yours</span></li>
      <li><span class="auth__tick">✓</span><span>The next sign-in from any device claims the lock again</span></li>
      <li><span class="auth__tick">✓</span><span>Two resets per 24 hours, then the option locks</span></li>
      <li><span class="auth__tick">✓</span><span>Anyone else signed in on the old device keeps their session until it expires</span></li>
    </ul>
  </div>

  <?= form_open(site_url('device-reset')) ?>

    <div class="field">
      <label class="label" for="username">Username</label>
      <input class="input" type="text" name="username" id="username"
             autocomplete="username" inputmode="text" spellcheck="false"
             autocapitalize="none" required
             placeholder="your username"
             value="<?= esc(old('username'), 'html') ?>"
             <?= $validation->hasError('username') ? 'aria-invalid="true"' : '' ?>>
      <?php if ($validation->hasError('username')): ?>
        <div class="error-text"><?= esc($validation->getError('username'), 'html') ?></div>
      <?php endif; ?>
    </div>

    <div class="field">
      <div class="row row--between mb-3">
        <label class="label" for="password">Password</label>
        <button type="button" class="btn btn--ghost btn--sm" data-reveal="password"
                aria-pressed="false" data-press>Show</button>
      </div>
      <input class="input" type="password" name="password" id="password"
             autocomplete="current-password" required
             placeholder="••••••••"
             <?= $validation->hasError('password') ? 'aria-invalid="true"' : '' ?>>
      <?php if ($validation->hasError('password')): ?>
        <div class="error-text"><?= esc($validation->getError('password'), 'html') ?></div>
      <?php endif; ?>
    </div>

    <button type="submit" class="btn btn--primary btn--lg btn--block"
            data-press
            data-confirm="Clear the device lock on this account? The next sign-in claims it."
            data-confirm-title="Reset device lock"
            data-confirm-ok="Reset lock"
            data-confirm-danger>
      <span>Reset device</span>
    </button>

  <?= form_close() ?>

  <div class="auth__foot">
    Password still works? <a href="<?= site_url('login') ?>">Sign in instead</a>
  </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('script') ?>
<script>
  (function () {
    var btn = document.querySelector('[data-reveal="password"]');
    var input = document.getElementById('password');
    if (!btn || !input) return;
    btn.addEventListener('click', function () {
      var show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      btn.textContent = show ? 'Hide' : 'Show';
      btn.setAttribute('aria-pressed', show ? 'true' : 'false');
      input.focus();
    });
  })();
</script>
<?= $this->endSection() ?>