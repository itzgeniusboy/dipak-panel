<?php

/**
 * Admin · add balance
 *
 * Names read by User::balance(): user_id, amount, note.
 *
 * @var array  $users objects: id_users username saldo level status uplink created_at
 * @var object $user  the acting user
 */

$rows = is_array($users) ? $users : (array) $users;

$saldoSum = 0.0;
$topName  = '';
$topSaldo = 0.0;
$active   = 0;

foreach ($rows as $r) {
    $saldo = (float) ($r->saldo ?? 0);
    $saldoSum += $saldo;
    if ($saldo > $topSaldo) {
        $topSaldo = $saldo;
        $topName  = (string) ($r->username ?? '');
    }
    if ((int) ($r->status ?? 0) === 1) {
        $active++;
    }
}

$levels = [1 => 'Owner', 2 => 'Admin', 3 => 'Reseller'];

?>
<?= $this->extend('Layout/Starter') ?>

<?= $this->section('title') ?>Add balance<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="page-head" data-mt>
  <div>
    <div class="eyebrow">Panel</div>
    <h1>Add balance</h1>
    <p class="page-head__sub">
      <?= esc(count($rows), 'html') ?> account<?= count($rows) === 1 ? '' : 's' ?> ·
      <?= esc($active, 'html') ?> active
    </p>
  </div>
  <div class="spacer"></div>
  <div class="page-head__actions">
    <a class="btn btn--ghost" href="<?= site_url('admin/manage-users') ?>">Manage users</a>
  </div>
</div>

<div class="stats" data-mt>
  <div class="stat">
    <span class="stat__label">Saldo held</span>
    <span class="stat__value" data-count="<?= esc(number_format($saldoSum, 0, '.', ''), 'html') ?>"><?= esc(number_format($saldoSum, 0, '.', ''), 'html') ?></span>
    <span class="stat__foot">across every account</span>
  </div>
  <div class="stat">
    <span class="stat__label">Largest balance</span>
    <span class="stat__value" data-count="<?= esc(number_format($topSaldo, 0, '.', ''), 'html') ?>"><?= esc(number_format($topSaldo, 0, '.', ''), 'html') ?></span>
    <span class="stat__foot"><?= esc($topName !== '' ? $topName : 'no saldo yet', 'html') ?></span>
  </div>
  <div class="stat">
    <span class="stat__label">Accounts</span>
    <span class="stat__value" data-count="<?= esc(count($rows), 'html') ?>"><?= esc(count($rows), 'html') ?></span>
    <span class="stat__foot"><?= esc($active, 'html') ?> can sign in</span>
  </div>
</div>

<?= form_open() ?>
  <div class="card" data-mt>
    <div class="card__head">
      <h3>Top up an account</h3>
      <div class="spacer"></div>
      <span class="chip chip--accent">+ saldo</span>
    </div>

    <div class="field">
      <label class="label" for="user_id">Account</label>
      <select class="select" name="user_id" id="user_id" required>
        <option value="">Choose an account…</option>
        <?php foreach ($rows as $u): ?>
          <option value="<?= esc((int) ($u->id_users ?? 0), 'html') ?>"
                  <?= old('user_id') == ($u->id_users ?? 0) ? 'selected' : '' ?>>
            <?= esc((string) ($u->username ?? ''), 'html') ?> — <?= esc(number_format((float) ($u->saldo ?? 0), 0, '.', ''), 'html') ?>
          </option>
        <?php endforeach; ?>
      </select>
      <p class="hint">Pick a row in the ledger below to fill this in.</p>
    </div>

    <div class="field">
      <label class="label" for="amount">Amount</label>
      <input class="input input--mono" type="number" id="amount" name="amount"
             inputmode="numeric" min="1" step="1" placeholder="100" required
             value="<?= esc(old('amount'), 'html') ?>">
      <div class="tabs mt-3" role="group" aria-label="Quick amounts" data-amount-tabs>
        <button type="button" class="tab" data-amount="100">100</button>
        <button type="button" class="tab" data-amount="250">250</button>
        <button type="button" class="tab" data-amount="500">500</button>
        <button type="button" class="tab" data-amount="1000">1,000</button>
        <button type="button" class="tab" data-amount="5000">5,000</button>
      </div>
    </div>

    <div class="field">
      <label class="label" for="note">Note</label>
      <input class="input" type="text" id="note" name="note"
             maxlength="120" placeholder="cash, UPI, transfer…"
             value="<?= esc(old('note'), 'html') ?>">
      <p class="hint">Kept with the entry so the next admin knows where it came from.</p>
    </div>

    <button type="submit" class="btn btn--primary" data-press>Add balance</button>
  </div>
<?= form_close() ?>

<div class="table-wrap scroll-x mt-5" data-mt>
  <table class="tbl">
    <thead>
      <tr>
        <th scope="col">ID</th>
        <th scope="col">Account</th>
        <th scope="col">Role</th>
        <th scope="col">Uplink</th>
        <th scope="col">Registered</th>
        <th scope="col" class="tr">Balance</th>
      </tr>
    </thead>
    <tbody>
      <?php if ($rows): ?>
        <?php foreach ($rows as $u): ?>
          <?php
          $uid      = (int) ($u->id_users ?? 0);
          $lv       = (int) ($u->level ?? 0);
          $saldo    = (float) ($u->saldo ?? 0);
          $uplink   = (string) ($u->uplink ?? '');
          $joined   = !empty($u->created_at) ? date('Y-m-d H:i', strtotime((string) $u->created_at)) : '—';
          ?>
          <tr data-pick="<?= esc($uid, 'html') ?>">
            <td class="tbl__key"><?= esc($uid, 'html') ?></td>
            <td class="tbl__key"><?= esc((string) ($u->username ?? ''), 'html') ?></td>
            <td>
              <?php if ($lv === 1): ?>
                <span class="chip chip--accent">Owner</span>
              <?php elseif ($lv === 2): ?>
                <span class="chip chip--good">Admin</span>
              <?php else: ?>
                <span class="chip"><?= esc($levels[$lv] ?? 'Reseller', 'html') ?></span>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($uplink !== ''): ?>
                <span class="tbl__key"><?= esc($uplink, 'html') ?></span>
              <?php else: ?>
                <span class="muted">—</span>
              <?php endif; ?>
            </td>
            <td class="nowrap muted"><?= esc($joined, 'html') ?></td>
            <td class="tbl__num mono">
              <?php if ($saldo > 0): ?>
                +<?= esc(number_format($saldo, 0, '.', ''), 'html') ?>
              <?php elseif ($saldo < 0): ?>
                &minus;<?= esc(number_format(abs($saldo), 0, '.', ''), 'html') ?>
              <?php else: ?>
                0
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>

  <?php if (! $rows): ?>
    <div class="empty">
      <div class="empty__mark">◈</div>
      <p>No accounts to credit yet.</p>
    </div>
  <?php endif; ?>
</div>

<?= $this->endSection() ?>

<?= $this->section('script') ?>
<script>
  (function () {
    var amount = document.getElementById('amount');
    var picker = document.getElementById('user_id');

    document.querySelectorAll('[data-amount-tabs] .tab').forEach(function (tab) {
      tab.addEventListener('click', function () {
        if (!amount) return;
        amount.value = tab.getAttribute('data-amount');
        amount.focus();
      });
    });

    document.querySelectorAll('tr[data-pick]').forEach(function (tr) {
      tr.addEventListener('click', function () {
        if (!picker) return;
        picker.value = tr.getAttribute('data-pick');
        if (amount && !amount.value) amount.focus();
      });
    });
  })();
</script>
<?= $this->endSection() ?>