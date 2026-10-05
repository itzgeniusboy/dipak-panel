<?php
// Password reset landing. Confirms the outcome of the emailed link, then shows
// one narrow card. No sidebar, no menus — this is a dead end by design.

$state = 'broken';
$token   = isset($_GET['token']) ? (string) $_GET['token'] : '';
$newPass = isset($_GET['key']) ? (string) $_GET['key'] : '';
$mail    = isset($_GET['mail']) ? (string) $_GET['mail'] : '';

if ($token !== '' && $newPass !== '' && $mail !== '') {
    date_default_timezone_set('Asia/Calcutta');

    try {
        $db   = \Config\Database::connect();
        $conn = $db->getConnection();

        $stmt = $conn->prepare('SELECT id_users, exp_date FROM users WHERE reset_link_token = ? LIMIT 1');
        $stmt->bind_param('s', $token);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($row) {
            $exp = strtotime((string) $row['exp_date']);
            if ($exp !== false && time() <= $exp) {
                // Stored the way every other write path stores it, so the new
                // password actually works at the sign-in form.
                $hashPassword = function_exists('create_password') ? create_password($newPass) : $newPass;
                $upd = $conn->prepare('UPDATE users SET password = ?, reset_link_token = NULL, exp_date = NULL
                                       WHERE reset_link_token = ?');
                $upd->bind_param('ss', $hashPassword, $token);
                $upd->execute();
                $done = $upd->affected_rows > 0;
                $upd->close();
                $state = $done ? 'done' : 'failed';
            } else {
                $state = 'expired';
            }
        } else {
            $state = 'failed';
        }
    } catch (\Throwable $e) {
        // The live reset flow is Telegram-OTP based; this legacy link path can no
        // longer reach the user row. Fail soft, never 500.
        $state = 'failed';
    }
    // Connection stays open: the framework owns it.
}

$copy = [
    'done' => [
        'chip' => 'chip--good',
        'chipText' => 'Password updated',
        'title' => 'Your password has been changed',
        'body' => 'Use the new password the next time you sign in. This link cannot be used twice.',
        'cta' => ['label' => 'Sign in', 'href' => site_url('login')],
    ],
    'expired' => [
        'chip' => 'chip--warn',
        'chipText' => 'Link expired',
        'title' => 'This reset link has expired',
        'body' => 'Reset links are short-lived. Request a fresh one and try again.',
        'cta' => ['label' => 'Request a new link', 'href' => site_url('forgot-password')],
    ],
    'failed' => [
        'chip' => 'chip--bad',
        'chipText' => 'Could not update',
        'title' => 'Something went wrong',
        'body' => 'The link did not match any account, or the password could not be saved. Nothing has changed.',
        'cta' => ['label' => 'Try again', 'href' => site_url('forgot-password')],
    ],
    'broken' => [
        'chip' => 'chip--bad',
        'chipText' => 'Link broken',
        'title' => 'This link is incomplete',
        'body' => 'Open the link exactly as it appears in your email — all three parts are needed.',
        'cta' => ['label' => 'Request a new link', 'href' => site_url('forgot-password')],
    ],
];

$view = $copy[$state];
?>

<?= $this->extend('Layout/Starter') ?>

<?= $this->section('title') ?><?= esc('Password reset', 'html') ?><?= $this->endSection() ?>

<?= $this->section('content') ?>

  <div class="row" data-mt>
    <div class="card card--glow w-full" style="max-width:460px;margin:0 auto">

      <div class="tc">
        <?php if ($state === 'done'): ?>
          <span class="chip <?= esc($view['chip'], 'html') ?>"><span class="dot dot--live"></span><?= esc($view['chipText'], 'html') ?></span>
        <?php else: ?>
          <span class="chip <?= esc($view['chip'], 'html') ?>"><?= esc($view['chipText'], 'html') ?></span>
        <?php endif; ?>
      </div>

      <h1 class="mt-4 mb-0" style="font-size:var(--t-xl)"><?= esc($view['title'], 'html') ?></h1>
      <p class="mt-3"><?= esc($view['body'], 'html') ?></p>

      <?php if ($state === 'done'): ?>
        <div class="alert alert--good" style="margin-top:var(--s4)">
          <span>Your old reset link is now void. Sign in with the new password.</span>
        </div>
      <?php endif; ?>

      <div class="mt-4">
        <a class="btn btn--primary btn--lg btn--block" href="<?= esc($view['cta']['href'], 'html') ?>" data-press>
          <?= esc($view['cta']['label'], 'html') ?>
        </a>
      </div>

      <div class="hint tc mt-4">
        Never share a reset link. <?= esc((string) BASE_NAME, 'html') ?> support will never ask for one.
      </div>

    </div>
  </div>

<?= $this->endSection() ?>