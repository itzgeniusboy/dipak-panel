<?= $this->extend('Layout/Auth') ?>

<?= $this->section('content') ?>

<div data-mt>
  <div class="eyebrow">Registration</div>
  <h1 class="mt-2">Create your account</h1>
  <p class="mb-5">
    One referral code decides your level, balance and expiry. An admin reviews the
    account before the first sign-in.
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

  <?= form_open() ?>

    <div class="field">
      <label class="label" for="email">Email</label>
      <input class="input" type="email" name="email" id="email"
             autocomplete="email" inputmode="email" spellcheck="false"
             autocapitalize="none" required
             minlength="13" maxlength="40"
             placeholder="you@example.com"
             value="<?= esc(old('email'), 'html') ?>"
             <?= $validation->hasError('email') ? 'aria-invalid="true"' : '' ?>>
      <?php if ($validation->hasError('email')): ?>
        <div class="error-text"><?= esc($validation->getError('email'), 'html') ?></div>
      <?php else: ?>
        <div class="hint">Used for account notices only.</div>
      <?php endif; ?>
    </div>

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
        <div class="hint">Letters and numbers, 4 to 24 characters.</div>
      <?php endif; ?>
    </div>

    <div class="field">
      <label class="label" for="fullname">Full name</label>
      <input class="input" type="text" name="fullname" id="fullname"
             autocomplete="name" inputmode="text" spellcheck="false"
             autocapitalize="words" required
             minlength="4" maxlength="24"
             placeholder="how your name appears"
             value="<?= esc(old('fullname'), 'html') ?>"
             <?= $validation->hasError('fullname') ? 'aria-invalid="true"' : '' ?>>
      <?php if ($validation->hasError('fullname')): ?>
        <div class="error-text"><?= esc($validation->getError('fullname'), 'html') ?></div>
      <?php endif; ?>
    </div>

    <div class="field">
      <label class="label" for="telegram_chat_id">Telegram chat ID</label>
      <input class="input input--mono" type="text" name="telegram_chat_id" id="telegram_chat_id"
             autocomplete="off" inputmode="numeric" spellcheck="false"
             autocapitalize="none" pattern="\d*" required
             maxlength="20"
             placeholder="1234567890"
             value="<?= esc(old('telegram_chat_id'), 'html') ?>"
             <?= $validation->hasError('telegram_chat_id') ? 'aria-invalid="true"' : '' ?>>
      <?php if ($validation->hasError('telegram_chat_id')): ?>
        <div class="error-text"><?= esc($validation->getError('telegram_chat_id'), 'html') ?></div>
      <?php else: ?>
        <div class="hint">
          Numbers only. Message the panel bot once so it can reach you — codes and
          reset links arrive here.
        </div>
      <?php endif; ?>
    </div>

    <div class="field">
      <div class="row row--between mb-3">
        <label class="label" for="password">Password</label>
        <button type="button" class="btn btn--ghost btn--sm" data-reveal="password"
                aria-pressed="false" data-press>Show</button>
      </div>
      <input class="input" type="password" name="password" id="password"
             autocomplete="new-password" required
             minlength="6" maxlength="24"
             placeholder="••••••••"
             <?= $validation->hasError('password') ? 'aria-invalid="true"' : '' ?>>
      <?php if ($validation->hasError('password')): ?>
        <div class="error-text"><?= esc($validation->getError('password'), 'html') ?></div>
      <?php endif; ?>
    </div>

    <div class="field">
      <div class="row row--between mb-3">
        <label class="label" for="password2">Confirm password</label>
        <button type="button" class="btn btn--ghost btn--sm" data-reveal="password2"
                aria-pressed="false" data-press>Show</button>
      </div>
      <input class="input" type="password" name="password2" id="password2"
             autocomplete="new-password" required
             minlength="6" maxlength="24"
             placeholder="••••••••"
             <?= $validation->hasError('password2') ? 'aria-invalid="true"' : '' ?>>
      <div class="error-text" data-match hidden>The two passwords do not match.</div>
      <?php if ($validation->hasError('password2')): ?>
        <div class="error-text"><?= esc($validation->getError('password2'), 'html') ?></div>
      <?php endif; ?>
    </div>

    <div class="field">
      <label class="label" for="referral">Referral code</label>
      <input class="input input--mono" type="text" name="referral" id="referral"
             autocomplete="off" inputmode="text" spellcheck="false"
             autocapitalize="characters" required
             maxlength="25"
             placeholder="CODE123"
             value="<?= esc(old('referral'), 'html') ?>"
             <?= $validation->hasError('referral') ? 'aria-invalid="true"' : '' ?>>
      <?php if ($validation->hasError('referral')): ?>
        <div class="error-text"><?= esc($validation->getError('referral'), 'html') ?></div>
      <?php else: ?>
        <div class="hint">From whoever invited you. Each code works once.</div>
      <?php endif; ?>
    </div>

    <button type="submit" class="btn btn--primary btn--lg btn--block" data-press>
      <span>Create account</span>
    </button>

  <?= form_close() ?>

  <div class="auth__foot">
    Already registered? <a href="<?= site_url('login') ?>">Sign in</a>
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

    // Live confirm check — the server still has the final word.
    var one = document.getElementById('password');
    var two = document.getElementById('password2');
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