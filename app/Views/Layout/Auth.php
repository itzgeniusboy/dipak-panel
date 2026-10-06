<!doctype html>
<html lang="en" data-look="dusk">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="color-scheme" content="dark">
<meta name="theme-color" content="#0B0F0C">
<title><?= isset($title) ? esc($title) . ' · ' : '' ?><?= BASE_NAME ?></title>

<link rel="icon" href="<?= base_url('assets/logo.svg') ?>" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700&family=Inter:wght@400;450;500;600&family=Geist+Mono:wght@400;500&display=swap">

<link rel="stylesheet" href="<?= base_url('assets/css/tokens.css') ?>">
<link rel="stylesheet" href="<?= base_url('assets/css/app.css') ?>">
<link rel="stylesheet" href="<?= base_url('assets/css/components/mascot.css') ?>">
<link rel="stylesheet" href="<?= base_url('assets/css/components/board.css') ?>">
<link rel="stylesheet" href="<?= base_url('assets/css/components/transitions.css') ?>">
<?= $this->renderSection('head') ?>
</head>
<body>

<div class="auth">
  <aside class="auth__aside" data-mt>
    <div>
      <a class="brand" href="<?= site_url() ?>">
        <span class="brand__mark">D</span>
        <span><?= BASE_NAME ?></span>
      </a>

      <h2 class="display mt-5" style="max-width:14ch">Keys that never sit still.</h2>
      <p class="mt-3" style="max-width:36ch">
        One panel for licences, durations and balances. Every screen carries into the next,
        so nothing waits for a page load.
      </p>

      <ul class="auth__list">
        <li><span class="auth__tick">✓</span><span>Generate and track keys from one list</span></li>
        <li><span class="auth__tick">✓</span><span>Duration and price plans you control</span></li>
        <li><span class="auth__tick">✓</span><span>Two-factor and device binding</span></li>
        <li><span class="auth__tick">✓</span><span>Admin review for new accounts</span></li>
      </ul>
    </div>

    <div class="row gap-2" style="flex-wrap:wrap">
      <span class="chip">v<?= defined('APP_VERSION') ? esc(APP_VERSION, 'html') : '1.0' ?></span>
      <span class="chip chip--good"><i class="dot dot--live"></i>Secure</span>
    </div>
  </aside>

  <main class="auth__main">
    <div class="auth__card" data-mt>
      <div class="auth__mascot"><?= $this->include('Partials/Mascot', ['mood' => 'idle', 'size' => 84]) ?></div>
      <?= $this->renderSection('content') ?>
    </div>
  </main>
</div>

<div class="toasts" id="toasts"></div>

<script src="<?= base_url('assets/js/motion.js') ?>"></script>
<script src="<?= base_url('assets/js/app.js') ?>"></script>
<script src="<?= base_url('assets/js/mascot.js') ?>"></script>
<script src="<?= base_url('assets/js/sound.js') ?>"></script>
<?= $this->renderSection('script') ?>
</body>
</html>
