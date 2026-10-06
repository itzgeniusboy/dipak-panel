<?php

/**
 * Admin · manage users
 *
 * Replaces the legacy page. The controller reads no POST from this view except
 * the delete form, so the filter row is client side and the `search` query
 * string is honoured on load.
 *
 * @var array  $user_list objects: id_users username fullname level status
 *                           saldo uplink created_at expiration_date
 * @var object $user      the acting user
 */

$rows = is_array($user_list) ? $user_list : (array) $user_list;

$total     = count($rows);
$owners    = 0;
$active    = 0;
$banned    = 0;
$pending   = 0;
$expired   = 0;
$saldoSum  = 0;

foreach ($rows as $r) {
    $lv = (int) ($r->level ?? 0);
    $st = (int) ($r->status ?? 0);
    if ($lv === 1) {
        $owners++;
    }
    if ($st === 1) {
        $active++;
    } elseif ($st === 2) {
        $banned++;
    } elseif ($st === 0) {
        $pending++;
    } else {
        $expired++;
    }
    if ($lv !== 1) {
        $saldoSum += (float) ($r->saldo ?? 0);
    }
}

$levels = [1 => 'Owner', 2 => 'Admin', 3 => 'Reseller'];
$q      = trim((string) service('request')->getGet('search'));
$isOwner = isset($user) && (int) $user->level === 1;

?>
<?= $this->extend('Layout/Starter') ?>

<?= $this->section('title') ?>Users<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?php if (session()->getFlashdata('success')): ?>
  <div class="alert alert--good" data-flash><span><?= esc(session()->getFlashdata('success'), 'html') ?></span></div>
<?php endif; ?>


<div class="page-head" data-mt>
  <div>
    <div class="eyebrow">Panel</div>
    <h1>Users</h1>
    <p class="page-head__sub">
      <?= esc($total, 'html') ?> account<?= $total === 1 ? '' : 's' ?> ·
      <?= esc($active, 'html') ?> active ·
      <?= esc($isOwner ? 'every account' : 'your referrals only', 'html') ?>
    </p>
  </div>
  <div class="spacer"></div>
  <div class="page-head__actions">
    <a class="btn btn--ghost" href="<?= site_url('account_approve') ?>">Approvals</a>
    <a class="btn" href="<?= site_url('admin/create-referral') ?>">New referral</a>
  </div>
</div>

<div class="stats" data-mt>
  <div class="stat">
    <span class="stat__label">Accounts</span>
    <span class="stat__value" data-count="<?= esc($total, 'html') ?>"><?= esc($total, 'html') ?></span>
    <span class="stat__foot"><?= esc($owners, 'html') ?> owner<?= $owners === 1 ? '' : 's' ?> in scope</span>
  </div>
  <div class="stat">
    <span class="stat__label">Active</span>
    <span class="stat__value" data-count="<?= esc($active, 'html') ?>"><?= esc($active, 'html') ?></span>
    <span class="stat__foot"><?= esc($pending, 'html') ?> waiting on approval</span>
  </div>
  <div class="stat">
    <span class="stat__label">Blocked</span>
    <span class="stat__value" data-count="<?= esc($banned + $expired, 'html') ?>"><?= esc($banned + $expired, 'html') ?></span>
    <span class="stat__foot"><?= esc($banned, 'html') ?> banned · <?= esc($expired, 'html') ?> expired</span>
  </div>
  <div class="stat">
    <span class="stat__label">Saldo held</span>
    <span class="stat__value" data-count="<?= esc(number_format($saldoSum, 0, '.', ''), 'html') ?>"><?= esc(number_format($saldoSum, 0, '.', ''), 'html') ?></span>
    <span class="stat__foot">owner balances excluded</span>
  </div>
</div>

<div class="card" data-mt>
  <form method="get" action="">
    <div class="field mb-0">
      <label class="label" for="users-q">Search</label>
      <div class="row gap-2">
        <input class="input" type="search" id="users-q" name="search"
               value="<?= esc($q, 'html') ?>" spellcheck="false" autocapitalize="none"
               placeholder="username, fullname, saldo or uplink">
        <button type="submit" class="btn btn--primary" data-press>Search</button>
        <a class="btn btn--ghost" href="<?= site_url('admin/manage-users') ?>">Reset</a>
      </div>
      <p class="hint">Matches username, fullname, saldo or uplink.</p>
    </div>
  </form>

  <div class="mt-4">
    <div class="tabs" role="tablist" aria-label="Filter accounts" data-filter-tabs>
      <button type="button" class="tab" role="tab" aria-selected="true" data-role="" data-status="">All</button>
      <button type="button" class="tab" role="tab" aria-selected="false" data-role="" data-status="1">Active</button>
      <button type="button" class="tab" role="tab" aria-selected="false" data-role="" data-status="0">Pending</button>
      <button type="button" class="tab" role="tab" aria-selected="false" data-role="1">Owner</button>
      <button type="button" class="tab" role="tab" aria-selected="false" data-role="2">Admin</button>
      <button type="button" class="tab" role="tab" aria-selected="false" data-role="3">Reseller</button>
      <button type="button" class="tab" role="tab" aria-selected="false" data-role="" data-status="2">Banned</button>
    </div>
  </div>
</div>

<div class="table-wrap scroll-x mt-4" data-mt>
  <table class="tbl">
    <thead>
      <tr>
        <th scope="col">ID</th>
        <th scope="col">Username</th>
        <th scope="col">Fullname</th>
        <th scope="col">Access level</th>
        <th scope="col" class="tr">Balance</th>
        <th scope="col">Status</th>
        <th scope="col">Uplink</th>
        <th scope="col">Registered</th>
        <th scope="col">Expiration</th>
        <th scope="col">Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if ($rows): ?>
        <?php foreach ($rows as $u): ?>
          <?php
          $uid       = (int) ($u->id_users ?? 0);
          $lv        = (int) ($u->level ?? 0);
          $st        = (int) ($u->status ?? 0);
          $username  = (string) ($u->username ?? '');
          $fullname  = (string) ($u->fullname ?? '');
          $uplink    = (string) ($u->uplink ?? '');
          $saldo     = (float) ($u->saldo ?? 0);
          $createdAt = $u->created_at ?? null;
          $expiresAt = (string) ($u->expiration_date ?? '');

          $joined = $createdAt ? date('Y-m-d H:i', strtotime((string) $createdAt)) : '—';

          $needle = strtolower(trim($username . ' ' . $fullname . ' ' . $uplink . ' ' . (string) (int) $saldo));
          ?>
          <tr data-role="<?= esc($lv, 'html') ?>" data-status="<?= esc($st, 'html') ?>" data-search="<?= esc($needle, 'html') ?>">
            <td class="tbl__key"><?= esc($uid, 'html') ?></td>
            <td class="tbl__key"><?= esc($username, 'html') ?></td>
            <td><?= esc($fullname !== '' ? $fullname : '—', 'html') ?></td>
            <td>
              <?php if ($lv === 1): ?>
                <span class="chip chip--accent">Owner</span>
              <?php elseif ($lv === 2): ?>
                <span class="chip chip--good">Admin</span>
              <?php else: ?>
                <span class="chip"><?= esc($levels[$lv] ?? 'Reseller', 'html') ?></span>
              <?php endif; ?>
            </td>
            <td class="tbl__num mono">
              <?php if ($lv === 1): ?>
                &infin;
              <?php else: ?>
                <?= esc(number_format($saldo, 0, '.', ''), 'html') ?>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($st === 1): ?>
                <span class="chip chip--good"><span class="dot dot--live"></span>Active</span>
              <?php elseif ($st === 2): ?>
                <span class="chip chip--bad">Banned</span>
              <?php elseif ($st === 0): ?>
                <span class="chip chip--warn">Pending</span>
              <?php else: ?>
                <span class="chip chip--warn">Expired</span>
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
            <td class="nowrap muted"><?= esc($expiresAt !== '' ? $expiresAt : '—', 'html') ?></td>
            <td>
              <div class="row gap-2 nowrap">
                <a class="btn btn--sm" href="<?= site_url('user/' . $uid) ?>">Edit</a>

                <?php if ($st === 0): ?>
                  <?= form_open(site_url('user/approve/' . $uid), ['method' => 'get']) ?>
                    <button type="submit" class="btn btn--sm btn--primary"
                            data-confirm="Approve <?= esc($username, 'html') ?>? They will be able to sign in straight away."
                            data-confirm-ok="Approve"
                            data-press>Approve</button>
                  <?= form_close() ?>
                <?php endif; ?>

                <?= form_open(site_url('user/delete/' . $uid)) ?>
                  <?= form_hidden('_method', 'DELETE') ?>
                  <button type="submit" class="btn btn--sm btn--danger"
                          data-confirm="Delete <?= esc($username, 'html') ?>? This cannot be undone."
                          data-confirm-ok="Delete"
                          data-confirm-danger>Delete</button>
                <?= form_close() ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>

  <?php if (!$rows): ?>
    <div class="empty">
      <div class="empty__mark">◌</div>
      <p>No accounts in range yet.</p>
      <p><a href="<?= site_url('admin/create-referral') ?>">Create a referral code</a> to add the first one.</p>
    </div>
  <?php else: ?>
    <div class="empty" data-filter-empty hidden>
      <div class="empty__mark">⌕</div>
      <p>No account matches this filter.</p>
    </div>
  <?php endif; ?>
</div>

<?= $this->endSection() ?>

<?= $this->section('script') ?>
<script>
  (function () {
    var input = document.getElementById('users-q');
    var tabs  = document.querySelectorAll('[data-filter-tabs] .tab');
    var rows  = document.querySelectorAll('table.tbl tbody tr[data-role]');
    var empty = document.querySelector('[data-filter-empty]');
    var role = '';
    var status = '';

    function apply() {
      var term = input ? input.value.trim().toLowerCase() : '';
      var shown = 0;

      rows.forEach(function (tr) {
        var okRole   = !role   || tr.getAttribute('data-role') === role;
        var okStatus = !status || tr.getAttribute('data-status') === status;
        var hay      = tr.getAttribute('data-search') || '';
        var okTerm   = !term   || hay.indexOf(term) !== -1;
        var show     = okRole && okStatus && okTerm;
        tr.hidden = !show;
        if (show) shown++;
      });

      if (empty) empty.hidden = shown !== 0;
    }

    tabs.forEach(function (tab) {
      tab.addEventListener('click', function () {
        tabs.forEach(function (t) { t.setAttribute('aria-selected', 'false'); });
        tab.setAttribute('aria-selected', 'true');
        role   = tab.getAttribute('data-role') || '';
        status = tab.getAttribute('data-status') || '';
        apply();
      });
    });

    if (input) {
      input.addEventListener('input', apply);
      apply();
    }
  })();
</script>
<?= $this->endSection() ?>