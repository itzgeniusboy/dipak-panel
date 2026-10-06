<nav class="nav">
  <div class="nav__label">Panel</div>
  <a class="nav__item" href="<?= site_url('dashboard') ?>" data-nav data-circuit="nav:dashboard"><span class="nav__icon">◧</span> Dashboard</a>
  <a class="nav__item" href="<?= site_url('keys') ?>" data-nav data-circuit="nav:keys"><span class="nav__icon">⚿</span> Keys</a>
  <a class="nav__item" href="<?= site_url('keys/generate-random') ?>" data-nav data-circuit="nav:generate"><span class="nav__icon">+</span> Generate random</a>
  <a class="nav__item" href="<?= site_url('keys/generate') ?>" data-nav data-circuit="nav:generate"><span class="nav__icon">✎</span> Generate custom</a>

  <div class="nav__label">Circuit</div>
  <a class="nav__item" href="<?= site_url('assistant') ?>" data-nav data-circuit="nav:assistant"><span class="nav__icon">◐</span> Circuit</a>

  <div class="nav__label">Account</div>
  <a class="nav__item" href="<?= site_url('balance') ?>" data-nav data-circuit="nav:balance"><span class="nav__icon">◈</span> Balance</a>
  <a class="nav__item" href="<?= site_url('license') ?>" data-nav data-circuit="nav:license"><span class="nav__icon">◷</span> Licences</a>
  <a class="nav__item" href="<?= site_url('ExtendDuration') ?>" data-nav><span class="nav__icon">⌾</span> Extend duration</a>
  <a class="nav__item" href="<?= site_url('price') ?>" data-nav data-circuit="nav:price"><span class="nav__icon">≈</span> Price plans</a>
  <a class="nav__item" href="<?= site_url('settings') ?>" data-nav data-circuit="nav:settings"><span class="nav__icon">⚙</span> Settings</a>
  <a class="nav__item" href="<?= site_url('twofa') ?>" data-nav><span class="nav__icon">⛨</span> Two-factor</a>

  <div class="nav__label">Server</div>
  <a class="nav__item" href="<?= site_url('Server') ?>" data-nav><span class="nav__icon">▣</span> Online system</a>
  <a class="nav__item" href="<?= site_url('lib') ?>" data-nav><span class="nav__icon">⇅</span> Online lib</a>

  <div class="nav__label">Admin</div>
  <a class="nav__item" href="<?= site_url('account_approve') ?>" data-nav><span class="nav__icon">✓</span> Approvals</a>
  <a class="nav__item" href="<?= site_url('admin/manage-users') ?>" data-nav><span class="nav__icon">◉</span> Manage users</a>
  <a class="nav__item" href="<?= site_url('admin/create-referral') ?>" data-nav><span class="nav__icon">＋</span> Create referral</a>

  <div class="nav__label" style="margin-top:auto;padding-top:var(--s6)"></div>
  <div class="row gap-2" style="padding:0 var(--s3)">
    <span class="chip"><?= BASE_NAME_FULL ?></span>
  </div>
</nav>