<?= $this->extend('Layout/Auth') ?>

<?= $this->section('content') ?>

<div data-mt>
  <div class="eyebrow">Recovery</div>
  <h1 class="mt-2">Forgot password</h1>
  <p class="mb-5">
    Give us your username and we send a one-time code to the Telegram account
    already bound to it.
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

  <?= form_open('forgot-password') ?>

    <div class="field">
      <label class="label" for="username">Username</label>
      <input class="input" type="text" name="username" id="username"
             autocomplete="username" inputmode="text" spellcheck="false"
             autocapitalize="none" required
             minlength="4" maxlength="24"
             placeholder="your username"
             value="<?= esc(old('username'), 'html') ?>"
             <?= $validation->hasError('username') ? 'aria-invalid="true"' : '' ?>>
      <?php if ($validation->hasError('username')): ?>
        <div class="error-text"><?= esc($validation->getError('username'), 'html') ?></div>
      <?php else: ?>
        <div class="hint">The account name, not your email address.</div>
      <?php endif; ?>
    </div>

    <button type="submit" class="btn btn--primary btn--lg btn--block" data-press>
      <span>Send code</span>
    </button>

  <?= form_close() ?>

  <div class="card mt-5 mb-4">
    <div class="eyebrow">Before you wait</div>
    <ul class="auth__list">
      <li><span class="auth__tick">✓</span><span>The code is 6 digits and expires after 5 minutes</span></li>
      <li><span class="auth__tick">✓</span><span>Start a chat with the panel bot so it can deliver the code</span></li>
      <li><span class="auth__tick">✓</span><span>No Telegram chat ID on file? An admin has to reset it by hand</span></li>
    </ul>
  </div>

  <div class="auth__foot">
    Remembered it? <a href="<?= site_url('login') ?>">Sign in</a>
  </div>
</div>

<?= $this->endSection() ?>