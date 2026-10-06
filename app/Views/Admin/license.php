<?php

/**
 * Admin · licence
 *
 * Names read by User::license(): license
 *
 * @var string $current the key currently written into the licence file
 * @var object $user    the acting user (owner only)
 */

$key    = trim((string) ($current ?? ''));
$length = $key !== '' ? strlen($key) : 0;
$isSet  = $length > 0;

?>
<?= $this->extend('Layout/Starter') ?>

<?= $this->section('title') ?>Licence<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="page-head" data-mt>
  <div>
    <div class="eyebrow">Panel</div>
    <h1>Licence</h1>
    <p class="page-head__sub">The key this panel is currently running on.</p>
  </div>
  <div class="spacer"></div>
</div>

<?php if (! isset($user) || (int) $user->level !== 1): ?>
  <div class="alert alert--bad" data-flash><span>Access denied — only the owner can read or change the licence.</span></div>
<?php else: ?>

<div class="card" data-mt>
  <div class="card__head">
    <h3>Installed key</h3>
    <div class="spacer"></div>
    <?php if ($isSet): ?>
      <span class="chip chip--good"><span class="dot dot--live"></span>Written</span>
    <?php else: ?>
      <span class="chip chip--warn">Not set</span>
    <?php endif; ?>
  </div>

  <dl class="stack">
    <div class="row row--between gap-3">
      <dt class="eyebrow">Key</dt>
      <dd class="tbl__key tr"><?= esc($isSet ? $key : '—', 'html') ?></dd>
    </div>
    <div class="row row--between gap-3">
      <dt class="eyebrow">Length</dt>
      <dd class="mono tr"><?= esc($length, 'html') ?> char<?= $length === 1 ? '' : 's' ?></dd>
    </div>
    <div class="row row--between gap-3">
      <dt class="eyebrow">Accepted</dt>
      <dd class="mono tr">8–128 characters</dd>
    </div>
    <div class="row row--between gap-3">
      <dt class="eyebrow">Alphabet</dt>
      <dd class="mono tr">A–Z a–z 0–9 _ -</dd>
    </div>
    <div class="row row--between gap-3">
      <dt class="eyebrow">Stored in</dt>
      <dd class="mono tr">apiserver.php</dd>
    </div>
  </dl>
</div>

<?= form_open() ?>
  <div class="card mt-4" data-mt>
    <div class="card__head">
      <h3>Change licence</h3>
      <div class="spacer"></div>
      <span class="chip chip--accent">owner only</span>
    </div>

    <div class="field mb-0">
      <label class="label" for="license">New licence</label>
      <input class="input input--mono" type="text" id="license" name="license"
             autocomplete="off" spellcheck="false" autocapitalize="none"
             minlength="8" maxlength="128" required
             pattern="[A-Za-z0-9_\-]{8,128}"
             placeholder="XXXX_XXXX-XXXX"
             value="<?= esc(old('license'), 'html') ?>">
      <p class="hint">
        Letters, numbers, dash and underscore only. The previous value is backed up
        beside the file before it is written.
      </p>
    </div>

    <div class="row row--end gap-2 mt-4">
      <a class="btn btn--ghost" href="<?= site_url('dashboard') ?>">Cancel</a>
      <button type="submit" class="btn btn--primary" data-press
              data-confirm="Write this licence key into the panel file?">Save changes</button>
    </div>
  </div>
<?= form_close() ?>

<?php endif; ?>

<?= $this->endSection() ?>