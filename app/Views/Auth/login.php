<?= $this->extend('Layout/Auth') ?>

<?= $this->section('content') ?>

<h1>Sign in</h1>
<p>Use the account you registered with.</p>

<?php if (session()->getFlashdata('msgSuccess')): ?>
  <div class="alert alert--good" data-flash>
    <span><?= esc(session()->getFlashdata('msgSuccess'), 'html') ?></span>
  </div>
<?php endif; ?>
<?php if (session()->getFlashdata('msgDanger')): ?>
  <div class="alert alert--bad" data-flash>
    <span><?= esc(session()->getFlashdata('msgDanger'), 'html') ?></span>
  </div>
<?php endif; ?>

<?= form_open() ?>
  <div class="field">
    <label class="label" for="username">Username</label>
    <input class="input" type="text" name="username" id="username"
           autocomplete="username" inputmode="text" spellcheck="false"
           autocapitalize="none" required
           value="<?= esc(old('username'), 'html') ?>"
           placeholder="your username">
  </div>

  <div class="field">
    <label class="label" for="password">Password</label>
    <input class="input" type="password" name="password" id="password"
           autocomplete="current-password" required
           placeholder="••••••••">
  </div>

  <input type="hidden" name="device_id" id="device_id" value="<?= esc(old('device_id'), 'html') ?>">
  <input type="hidden" name="ip" id="ip" value="">

  <div class="row row--between mb-4">
    <label class="switch">
      <input type="checkbox" id="stay_log" name="stay_log" value="yes">
      <span class="switch__track"><span class="switch__knob"></span></span>
      <span class="muted" style="font-size:var(--t-sm)">Keep me signed in</span>
    </label>
    <a class="muted" style="font-size:var(--t-sm)" href="<?= site_url('forgot-password') ?>">Forgot password?</a>
  </div>

  <button type="submit" class="btn btn--primary btn--lg btn--block" data-press>
    <span>Sign in</span>
  </button>
<?= form_close() ?>

<div class="auth__foot">
  No account yet? <a href="<?= site_url('register') ?>">Create one</a>
</div>

<?= $this->endSection() ?>

<?= $this->section('script') ?>
<script>
  (function () {
    var d = document.getElementById('device_id');
    if (d && !d.value) {
      var KEY = 'dpk_device';
      var id = null;
      try { id = localStorage.getItem(KEY); } catch (e) {}
      if (!id) {
        var r = (self.crypto && self.crypto.randomUUID)
          ? self.crypto.randomUUID()
          : String(Math.random()).slice(2) + String(Math.random()).slice(2);
        id = r.replace(/-/g, '').slice(0, 32);
        try { localStorage.setItem(KEY, id); } catch (e) {}
      }
      d.value = id;
    }
    var ip = document.getElementById('ip');
    if (ip) ip.value = navigator.userAgent;
  })();
</script>
<?= $this->endSection() ?>
