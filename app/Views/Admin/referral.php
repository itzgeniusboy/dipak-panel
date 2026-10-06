<?php

/**
 * Admin · create referral
 *
 * Names read by User::reff_action(): set_saldo, accExpire, accLevel
 *
 * @var array  $code       referral_code rows (newest first)
 * @var array  $accExpire  days => label
 * @var array  $accLevel   level => label
 * @var int    $total_code total rows in referral_code
 * @var \CodeIgniter\Validation\Services $validation
 */

$rows = is_array($code) ? $code : (array) $code;

$levels    = [1 => 'Owner', 2 => 'Admin', 3 => 'Reseller'];
$unused    = 0;
$used      = 0;
$saldoPaid = 0.0;

foreach ($rows as $c) {
    $by = trim((string) ($c->used_by ?? ''));
    if ($by === '') {
        $unused++;
    } else {
        $used++;
        $saldoPaid += (float) ($c->set_saldo ?? 0);
    }
}

?>
<?= $this->extend('Layout/Starter') ?>

<?= $this->section('title') ?>Referral<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="page-head" data-mt>
  <div>
    <div class="eyebrow">Panel</div>
    <h1>Referrals</h1>
    <p class="page-head__sub">
      <?= esc((int) ($total_code ?? count($rows)), 'html') ?> code<?= (int) ($total_code ?? count($rows)) === 1 ? '' : 's' ?> issued ·
      <?= esc($unused, 'html') ?> still open
    </p>
  </div>
  <div class="spacer"></div>
  <div class="page-head__actions">
    <a class="btn btn--ghost" href="<?= site_url('admin/manage-users') ?>">Manage users</a>
  </div>
</div>

<div class="stats" data-mt>
  <div class="stat">
    <span class="stat__label">Open codes</span>
    <span class="stat__value" data-count="<?= esc($unused, 'html') ?>"><?= esc($unused, 'html') ?></span>
    <span class="stat__foot">waiting to be redeemed</span>
  </div>
  <div class="stat">
    <span class="stat__label">Redeemed</span>
    <span class="stat__value" data-count="<?= esc($used, 'html') ?>"><?= esc($used, 'html') ?></span>
    <span class="stat__foot">of the last <?= esc(count($rows), 'html') ?></span>
  </div>
  <div class="stat">
    <span class="stat__label">Saldo issued</span>
    <span class="stat__value" data-count="<?= esc(number_format($saldoPaid, 0, '.', ''), 'html') ?>"><?= esc(number_format($saldoPaid, 0, '.', ''), 'html') ?></span>
    <span class="stat__foot">through redeemed codes</span>
  </div>
</div>

<?= form_open() ?>
  <div class="card" data-mt>
    <div class="card__head">
      <h3>Generate a code</h3>
      <div class="spacer"></div>
      <span class="chip chip--accent">new</span>
    </div>

    <div class="field-row">
      <div class="field">
        <label class="label" for="set_saldo">Set saldo</label>
        <input class="input input--mono" type="number" id="set_saldo" name="set_saldo"
               inputmode="numeric" min="0" maxlength="11"
               value="<?= esc(old('set_saldo') ?: '5', 'html') ?>">
        <?php if ($validation->hasError('set_saldo')): ?>
          <p class="error-text"><?= esc($validation->getError('set_saldo'), 'html') ?></p>
        <?php else: ?>
          <p class="hint">Credited to the account that redeems the code. 0 is allowed.</p>
        <?php endif; ?>
      </div>

      <div class="field">
        <label class="label" for="accLevel">Account level</label>
        <?= form_dropdown(['class' => 'select', 'name' => 'accLevel', 'id' => 'accLevel'], $accLevel, old('accLevel') ?: '') ?>
        <?php if ($validation->hasError('accLevel')): ?>
          <p class="error-text"><?= esc($validation->getError('accLevel'), 'html') ?></p>
        <?php else: ?>
          <p class="hint">The role the new account gets.</p>
        <?php endif; ?>
      </div>
    </div>

    <div class="field">
      <label class="label" for="accExpire">Account expiration</label>
      <?= form_dropdown(['class' => 'select', 'name' => 'accExpire', 'id' => 'accExpire'], $accExpire, old('accExpire') ?: '') ?>
      <?php if ($validation->hasError('accExpire')): ?>
        <p class="error-text"><?= esc($validation->getError('accExpire'), 'html') ?></p>
      <?php else: ?>
        <p class="hint">Counted from the moment the code is redeemed, not now.</p>
      <?php endif; ?>
    </div>

    <button type="submit" class="btn btn--primary" data-press>Generate code</button>
  </div>
<?= form_close() ?>

<div class="table-wrap scroll-x mt-5" data-mt>
  <table class="tbl">
    <thead>
      <tr>
        <th scope="col">ID</th>
        <th scope="col">Referral</th>
        <th scope="col">Hashed</th>
        <th scope="col" class="tr">Saldo</th>
        <th scope="col">Level</th>
        <th scope="col">Expiration</th>
        <th scope="col">Used by</th>
        <th scope="col">Created by</th>
        <th scope="col">Created</th>
      </tr>
    </thead>
    <tbody>
      <?php if ($rows): ?>
        <?php foreach ($rows as $c): ?>
          <?php
          $id      = (int) ($c->id_reff ?? 0);
          $code    = (string) ($c->Referral ?? '');
          $hash    = (string) ($c->code ?? '');
          $saldo   = (float) ($c->set_saldo ?? 0);
          $lv      = (int) ($c->level ?? 3);
          $expires = (string) ($c->acc_expiration ?? '');
          $usedBy  = trim((string) ($c->used_by ?? ''));
          $byWhom  = trim((string) ($c->created_by ?? ''));
          $made    = !empty($c->created_at) ? date('Y-m-d H:i', strtotime((string) $c->created_at)) : '—';
          ?>
          <tr>
            <td class="tbl__key"><?= esc($id, 'html') ?></td>
            <td>
              <div class="row gap-2 nowrap">
                <span class="tbl__key mono"><?= esc($code, 'html') ?></span>
                <?php if ($code !== ''): ?>
                  <button type="button" class="btn btn--sm btn--ghost"
                          data-copy="<?= esc($code, 'html') ?>">Copy</button>
                <?php endif; ?>
              </div>
            </td>
            <td><span class="tbl__key muted"><?= esc($hash !== '' ? substr($hash, 1, 15) : '—', 'html') ?></span></td>
            <td class="tbl__num mono"><?= esc(number_format($saldo, 0, '.', ''), 'html') ?></td>
            <td>
              <?php if ($lv === 1): ?>
                <span class="chip chip--accent">Owner</span>
              <?php elseif ($lv === 2): ?>
                <span class="chip chip--good">Admin</span>
              <?php else: ?>
                <span class="chip"><?= esc($levels[$lv] ?? 'Reseller', 'html') ?></span>
              <?php endif; ?>
            </td>
            <td class="nowrap muted"><?= esc($expires !== '' ? $expires : '—', 'html') ?></td>
            <td>
              <?php if ($usedBy !== ''): ?>
                <span class="chip chip--good"><?= esc($usedBy, 'html') ?></span>
              <?php else: ?>
                <span class="chip">open</span>
              <?php endif; ?>
            </td>
            <td class="tbl__key"><?= esc($byWhom !== '' ? $byWhom : '—', 'html') ?></td>
            <td class="nowrap muted"><?= esc($made, 'html') ?></td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>

  <?php if (! $rows): ?>
    <div class="empty">
      <div class="empty__mark">＋</div>
      <p>No referral codes yet.</p>
      <p>Generate one above and it lands here with its saldo and expiry.</p>
    </div>
  <?php endif; ?>
</div>

<?= $this->endSection() ?>

<?= $this->section('script') ?>
<script>
  (function () {
    if (window.__copyCodeBound) { return; }
    window.__copyCodeBound = true;
    document.addEventListener('click', function (e) {
      var btn = e.target.closest('button[data-copy]');
      if (!btn) return;
      var value = btn.getAttribute('data-copy') || '';
      var done = function () { App.toast('Code ' + value + ' copied', 'good', 'Copied'); };
      var fail = function () { App.toast('Clipboard blocked — copy it by hand', 'warn', 'Not copied'); };

      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(value).then(done, fail);
      } else {
        var ta = document.createElement('textarea');
        ta.value = value;
        ta.setAttribute('readonly', 'readonly');
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy') ? done() : fail(); } catch (err) { fail(); }
        ta.remove();
      }
    });
  })();
</script>
<?= $this->endSection() ?>