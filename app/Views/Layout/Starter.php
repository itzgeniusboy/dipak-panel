<!doctype html>
<html lang="en" data-look="dusk">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="color-scheme" content="dark">
<meta name="theme-color" content="#0B0F0C">
<title><?= isset($title) && $title ? esc($title) . ' · ' : '' ?><?= BASE_NAME ?></title>

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

<a class="skip-link" href="#main">Skip to content</a>

<div class="shell">

  <header class="shell__top">
    <button class="btn btn--ghost btn--sm" data-drawer-open aria-expanded="false" aria-label="Menu"
            style="display:none" data-drawer-btn>☰</button>

    <a class="brand" href="<?= site_url('dashboard') ?>">
      <span class="brand__mark">D</span>
      <span><?= BASE_NAME ?><br><span class="brand__sub">v<?= defined('APP_VERSION') ? esc(APP_VERSION, 'html') : '1.0' ?></span></span>
    </a>

    <div class="spacer" style="flex:1"></div>

    <div class="row gap-2">
      <span class="chip chip--accent nowrap"><?= esc(session('username') ?? 'user', 'html') ?></span>
      <button class="btn btn--ghost btn--sm" type="button" data-sound-toggle aria-pressed="true" title="Sound on">🔊</button>
      <a class="btn btn--ghost btn--sm" href="<?= site_url('logout') ?>">Sign out</a>
    </div>
  </header>

  <aside class="shell__side" data-drawer aria-hidden="true" aria-label="Sidebar">
    <?= $this->include('Layout/Header') ?>
  </aside>

  <main class="shell__main" id="main">
    <?php if (session()->getFlashdata('msgSuccess')): ?>
      <div class="alert alert--good" data-flash><span><?= esc(session()->getFlashdata('msgSuccess'), 'html') ?></span></div>
    <?php endif; ?>
    <?php if (session()->getFlashdata('msgDanger')): ?>
      <div class="alert alert--bad" data-flash><span><?= esc(session()->getFlashdata('msgDanger'), 'html') ?></span></div>
    <?php endif; ?>
    <?php if (session()->getFlashdata('msgWarning')): ?>
      <div class="alert alert--warn" data-flash><span><?= esc(session()->getFlashdata('msgWarning'), 'html') ?></span></div>
    <?php endif; ?>

    <?= $this->renderSection('content') ?>
  </main>
</div>

<div class="scrim"></div>
<div class="toasts" id="toasts"></div>

<div class="helper" data-helper>
  <div class="helper__bubble">
    <button class="helper__close" type="button" data-helper-close aria-label="Hide helper">✕</button>
    I'm <b>Circuit</b> — every key you generate, I count it.
  </div>
  <button class="helper__mascot" type="button" data-press aria-label="Poke the mascot">
    <?= $this->include('Partials/Mascot', ['mood' => 'idle', 'size' => 72]) ?>
  </button>
</div>

<script src="<?= base_url('assets/js/motion.js') ?>"></script>
<script src="<?= base_url('assets/js/app.js') ?>"></script>
<script src="<?= base_url('assets/js/mascot.js') ?>"></script>
<script src="<?= base_url('assets/js/sound.js') ?>"></script>
<?= $this->renderSection('script') ?>
<?= $this->renderSection('js') ?>

<script>
  (function () {
    var btn = document.querySelector('[data-drawer-btn]');
    if (btn && window.matchMedia('(max-width: 900px)').matches) btn.style.display = '';
  })();
</script>
</body>
</html>
