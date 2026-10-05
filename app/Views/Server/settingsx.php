<?php
$panelName = defined('BASE_NAME') ? (string) BASE_NAME : 'My Site';

// Legacy flash keys used by the controllers — rendered with the design alert.
$flashKind = '';
$flashText = '';
if (session()->getFlashdata('msgDanger')) {
    $flashKind = 'bad';
    $flashText = (string) session()->getFlashdata('msgDanger');
} elseif (session()->getFlashdata('msgSuccess')) {
    $flashKind = 'good';
    $flashText = (string) session()->getFlashdata('msgSuccess');
} elseif (session()->getFlashdata('msgWarning')) {
    $flashKind = 'warn';
    $flashText = (string) session()->getFlashdata('msgWarning');
}
?>

<?= $this->extend('Layout/Starter') ?>

<?= $this->section('title') ?><?= esc('Panel settings', 'html') ?><?= $this->endSection() ?>

<?= $this->section('content') ?>

  <div class="page-head" data-mt>
    <div>
      <div class="eyebrow">Settings</div>
      <h1>Panel settings</h1>
      <p class="page-head__sub">The name shown in the browser tab, on the sign-in page and in panel emails.</p>
    </div>
    <div class="spacer"></div>
    <div class="page-head__actions">
      <a class="btn btn--ghost" href="<?= site_url('settings') ?>">Account settings</a>
      <a class="btn" href="<?= site_url('dashboard') ?>" data-press>Dashboard</a>
    </div>
  </div>

  <?php if ($flashText !== ''): ?>
    <div class="alert alert--<?= esc($flashKind, 'html') ?> mt-4" data-flash role="alert">
      <span><?= esc($flashText, 'html') ?></span>
    </div>
  <?php endif; ?>

  <?php if ($user->level == 1): ?>

    <div class="w-full mt-4" data-mt style="max-width:560px">
      <div class="card card--glow">
        <div class="card__head">
          <h3>Change panel name</h3>
          <span class="spacer"></span>
          <span class="eyebrow">Owner only</span>
        </div>

        <?= form_open('settings/updateSiteName') ?>
          <div class="field">
            <label class="label" for="base_name">Website name</label>
            <input class="input" type="text" name="base_name" id="base_name"
                   value="<?= esc($panelName, 'html') ?>"
                   placeholder="Enter your site name" maxlength="60" required>
            <?php if ($validation->hasError('base_name')): ?>
              <div class="hint" style="color:var(--bad)"><?= esc($validation->getError('base_name'), 'html') ?></div>
            <?php else: ?>
              <div class="hint">Shown everywhere the panel introduces itself. Keep it short — it sits in the browser tab.</div>
            <?php endif; ?>
          </div>

          <button type="submit" class="btn btn--primary btn--block" data-press>Update site name</button>
        <?= form_close() ?>
      </div>
    </div>

  <?php else: ?>

    <div class="w-full mt-4" data-mt style="max-width:560px">
      <div class="card">
        <div class="card__head">
          <h3>Change panel name</h3>
          <span class="spacer"></span>
          <span class="chip chip--warn">Owner only</span>
        </div>
        <div class="empty">
          <span class="empty__mark">×</span>
          Only the panel owner can rename it. The current name is
          <span class="mono"><?= esc($panelName, 'html') ?></span>.
        </div>
      </div>
    </div>

  <?php endif; ?>

<?= $this->endSection() ?>