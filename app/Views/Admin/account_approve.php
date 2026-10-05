<?php

/**
 * Admin · account approvals
 *
 * Actions are the GET routes user/approve/{id} and user/decline/{id}; each row
 * carries its own form so data-confirm has something to submit.
 *
 * @var array  $pending_users accounts with status 0
 * @var object $user          the acting user
 */

$pending = is_array($pending_users) ? $pending_users : (array) $pending_users;
$count   = count($pending);
$isOwner = isset($user) && (int) $user->level === 1;

$levelNames = [1 => 'Owner', 2 => 'Admin', 3 => 'Reseller'];

?>
<?= $this->extend('Layout/Starter') ?>

<?= $this->section('title', 'Approvals') ?>

<?= $this->section('content') ?>

<?php if (session()->getFlashdata('success')): ?>
  <div class="alert alert--good" data-flash><span><?= esc(session()->getFlashdata('success'), 'html') ?></span></div>
<?php endif; ?>

<div class="page-head" data-mt>
  <div>
    <div class="eyebrow">Panel</div>
    <h1>Approvals</h1>
    <p class="page-head__sub">
      <?= esc($count, 'html') ?> account<?= $count === 1 ? '' : 's' ?> waiting ·
      <?= esc($isOwner ? 'every pending signup' : 'your referrals only', 'html') ?>
    </p>
  </div>
  <div class="spacer"></div>
  <div class="page-head__actions">
    <a class="btn btn--ghost" href="<?= current_url() ?>">Refresh</a>
  </div>
</div>

<div class="alert" data-mt>
  <span class="muted">
    <?= esc(
      $isOwner
        ? 'As owner you can approve or decline any pending signup. Declining sets the account to banned.'
        : 'As admin you only see signups from users you referred. Declining sets the account to banned.',
      'html'
    ) ?>
  </span>
</div>

<div class="table-wrap scroll-x" data-mt>
  <table class="tbl">
    <thead>
      <tr>
        <th scope="col">ID</th>
        <th scope="col">Username</th>
        <th scope="col">Fullname</th>
        <th scope="col">Referred by</th>
        <th scope="col">Registered</th>
        <th scope="col">Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if ($pending): ?>
        <?php foreach ($pending as $u): ?>
          <?php
          $uid      = (int) ($u->id_users ?? 0);
          $username = (string) ($u->username ?? '');
          $fullname = (string) ($u->fullname ?? '');
          $uplink   = trim((string) ($u->uplink ?? ''));
          $joined   = !empty($u->created_at) ? date('Y-m-d H:i', strtotime((string) $u->created_at)) : '—';
          ?>
          <tr>
            <td class="tbl__key"><?= esc($uid, 'html') ?></td>
            <td>
              <div class="row gap-2 nowrap">
                <span class="tbl__key"><?= esc($username, 'html') ?></span>
                <?php if ($isOwner): ?>
                  <span class="chip"><?= esc($levelNames[(int) ($u->level ?? 3)] ?? 'Reseller', 'html') ?></span>
                <?php endif; ?>
              </div>
            </td>
            <td><?= esc($fullname !== '' ? $fullname : '—', 'html') ?></td>
            <td>
              <?php if ($uplink !== ''): ?>
                <span class="chip chip--accent"><?= esc($uplink, 'html') ?></span>
              <?php else: ?>
                <span class="chip">Direct</span>
              <?php endif; ?>
            </td>
            <td class="nowrap muted"><?= esc($joined, 'html') ?></td>
            <td>
              <div class="row gap-2 nowrap">
                <?= form_open(site_url('user/approve/' . $uid), ['method' => 'get']) ?>
                  <button type="submit" class="btn btn--sm btn--primary" data-press
                          data-confirm="Approve <?= esc($username, 'html') ?>? They can sign in immediately."
                          data-confirm-ok="Approve">Approve</button>
                <?= form_close() ?>

                <?= form_open(site_url('user/decline/' . $uid), ['method' => 'get']) ?>
                  <button type="submit" class="btn btn--sm btn--danger"
                          data-confirm="Decline <?= esc($username, 'html') ?>? The registration is rejected and cannot be undone."
                          data-confirm-ok="Decline"
                          data-confirm-danger>Decline</button>
                <?= form_close() ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>

  <?php if (! $pending): ?>
    <div class="empty">
      <div class="empty__mark">✓</div>
      <p>
        <?= esc(
          $isOwner
            ? 'No pending account approvals at the moment.'
            : 'No pending account approvals from your referrals.',
          'html'
        ) ?>
      </p>
    </div>
  <?php endif; ?>
</div>

<?php if ($pending): ?>
  <p class="hint mt-3">
    Showing <?= esc($count, 'html') ?> pending approval<?= $count === 1 ? '' : 's' ?>
    <?= esc($isOwner ? '' : '(from your referrals only)', 'html') ?>.
  </p>
<?php endif; ?>

<?= $this->endSection() ?>