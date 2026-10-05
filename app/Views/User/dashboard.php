<?php
// Panel dashboard. Reads straight from MySQL like the original did — no controller
// changes — but every value is bound, never interpolated.

// The old views included a conn.php that no longer exists in the project.
// Use the framework's shared MySQLi connection instead.
$db   = \Config\Database::connect();
$conn = $db->getConnection();

$count = function (string $sql) use ($conn) {
    if (! $conn) {
        return 0;
    }
    $res = mysqli_query($conn, $sql);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    if ($res) {
        mysqli_free_result($res);
    }
    return (int) ($row['c'] ?? 0);
};

// Keys
$keycount  = $count('SELECT COUNT(*) AS c FROM keys_code');
$usedkeys  = $count("SELECT COUNT(*) AS c FROM keys_code WHERE devices IS NOT NULL AND devices <> ''");
$freekeys  = $count("SELECT COUNT(*) AS c FROM keys_code WHERE devices IS NULL OR devices = ''");
$activekeys = $count("SELECT COUNT(*) AS c FROM keys_code WHERE status = '1'");
$blockCount = $count("SELECT COUNT(*) AS c FROM keys_code WHERE status = '0'");

// Users
$users        = $count('SELECT COUNT(*) AS c FROM users');
$resellerusers = $count("SELECT COUNT(*) AS c FROM users WHERE level = '3'");
$banusers     = $count("SELECT COUNT(*) AS c FROM users WHERE status = '2'");

// Devices actually bound — one query, counted here so the tile is honest.
$devcount = 0;
$devres = $conn ? mysqli_query($conn, "SELECT devices FROM keys_code WHERE devices IS NOT NULL AND devices <> ''") : false;
if ($devres) {
    while ($d = mysqli_fetch_assoc($devres)) {
        $parts = explode(',', (string) $d['devices']);
        foreach ($parts as $p) {
            if (trim($p) !== '') {
                $devcount++;
            }
        }
    }
    mysqli_free_result($devres);
}

// Recent keys
$recentKeys = [];
$kres = $conn ? mysqli_query(
    $conn,
    'SELECT id_keys, game, user_key, duration, expired_date, max_devices, devices, status
     FROM keys_code ORDER BY id_keys DESC LIMIT 6'
) : false;
if ($kres) {
    while ($k = mysqli_fetch_assoc($kres)) {
        $recentKeys[] = $k;
    }
    mysqli_free_result($kres);
}

// Account expiry — bound, from the session user id.
$expiryStamp = null;
$userid      = (int) (session()->userid ?? 0);
$stmt = $conn ? mysqli_prepare($conn, 'SELECT expiration_date FROM users WHERE id_users = ?') : false;
if ($stmt && $userid > 0) {
    mysqli_stmt_bind_param($stmt, 'i', $userid);
    mysqli_stmt_execute($stmt);
    $row = mysqli_stmt_get_result($stmt);
    $exp = $row ? mysqli_fetch_assoc($row) : null;
    $ts  = strtotime((string) ($exp['expiration_date'] ?? ''));
    if ($ts) {
        $expiryStamp = $ts;
    }
    mysqli_stmt_close($stmt);
}

// Connection stays open: the framework owns it.

$daysLeft = $expiryStamp ? (int) floor(max(0, $expiryStamp - time()) / 86400) : 0;
$usedPct  = $keycount > 0 ? (int) round(($usedkeys / $keycount) * 100) : 0;

$signedIn = session('time_since');
$signedInLabel = '—';
if ($signedIn && isset($time)) {
    $signedInLabel = (string) $time::parse($signedIn)->humanize();
}

$logoutAt  = session('time_login');
$logoutLeft = '—';
if ($logoutAt && isset($time)) {
    $logoutLeft = (string) $time::now()->difference($time::parse($logoutAt))->humanize();
}

if (! function_exists('HoursToDays')) {
    function HoursToDays($value)
    {
        $value = (int) $value;
        if ($value == 1) {
            return "1 Hour";
        }
        if ($value >= 2 && $value < 24) {
            return "$value Hours";
        }
        if ($value == 24) {
            return "1 Day";
        }
        if ($value > 24) {
            $days = $value / 24;
            return ($days == (int) $days) ? "{$days} Days" : $days . " Days";
        }
        return $value . ' Hours';
    }
}

// Login / redirect feedback (the old glass alert partial is no longer included).
$notes = [];
foreach (['msgSuccess' => 'good', 'msgDanger' => 'bad', 'msgWarning' => 'warn', 'error' => 'bad'] as $key => $kind) {
    $val = (string) (session($key) ?? '');
    if ($val !== '') {
        $notes[] = [$val, $kind];
        session()->remove($key);
    }
}
?>

<?= $this->extend('Layout/Starter') ?>

<?= $this->section('title') ?><?= esc('Dashboard', 'html') ?><?= $this->endSection() ?>

<?= $this->section('content') ?>

  <div class="page-head" data-mt>
    <div>
      <div class="eyebrow">Panel</div>
      <h1>Welcome back, <?= esc(getName($user), 'html') ?></h1>
      <p class="page-head__sub">
        <?= esc((string) $keycount, 'html') ?> keys · <?= esc((string) $usedkeys, 'html') ?> in use ·
        <?= esc((string) $blockCount, 'html') ?> blocked · signed in as <?= esc(getLevel($user->level), 'html') ?>
      </p>
    </div>
    <div class="spacer"></div>
    <div class="page-head__actions">
      <a class="btn btn--ghost" href="<?= site_url('keys') ?>">All keys</a>
      <a class="btn btn--primary" href="<?= site_url('keys/generate') ?>" data-press>New key</a>
    </div>
  </div>

  <div class="stats" data-mt>
    <div class="stat" data-press>
      <span class="stat__label">Balance</span>
      <span class="stat__value">₹<span data-count="<?= esc((string) (int) $user->saldo, 'html') ?>">0</span></span>
      <span class="stat__foot">Credited to <?= esc(getName($user), 'html') ?></span>
    </div>

    <div class="stat" data-press>
      <span class="stat__label">Active keys</span>
      <span class="stat__value" data-count="<?= esc((string) $activekeys, 'html') ?>">0</span>
      <span class="stat__foot">
        <?php if ($blockCount > 0): ?>
          <span class="down"><?= esc((string) $blockCount, 'html') ?> blocked</span>
        <?php else: ?>
          <span class="up">none blocked</span>
        <?php endif; ?>
      </span>
    </div>

    <div class="stat" data-press>
      <span class="stat__label">Days left</span>
      <span class="stat__value" data-count="<?= esc((string) $daysLeft, 'html') ?>">0</span>
      <span class="stat__foot">
        <?= $expiryStamp ? esc(date('d M Y', $expiryStamp), 'html') : 'No expiry set' ?>
      </span>
    </div>

    <div class="stat" data-press>
      <span class="stat__label">Devices</span>
      <span class="stat__value" data-count="<?= esc((string) $devcount, 'html') ?>">0</span>
      <span class="stat__foot">Bound across <?= esc((string) $usedkeys, 'html') ?> keys</span>
    </div>
  </div>

  <div class="table-wrap" data-mt>
    <div class="scroll-x">
      <table class="tbl">
        <thead>
          <tr>
            <th>Key</th>
            <th>Game</th>
            <th class="tbl__num">Duration</th>
            <th class="tbl__num">Devices</th>
            <th>Expires</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
        <?php if (! $recentKeys): ?>
          <tr>
            <td colspan="6">
              <div class="empty">
                <span class="empty__mark">○</span>
                No keys yet — generate one to see it here.
              </div>
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($recentKeys as $k): ?>
            <?php $bound = getDevice($k['devices'] ?? ''); ?>
            <tr>
              <td>
                <a class="tbl__key" href="<?= site_url('keys/' . (int) $k['id_keys']) ?>"><?= esc($k['user_key'] ?? '—', 'html') ?></a>
              </td>
              <td><?= esc(($k['game'] ?? '') ?: '—', 'html') ?></td>
              <td class="tbl__num"><?= esc(HoursToDays($k['duration'] ?? 0), 'html') ?></td>
              <td class="tbl__num"><?= esc((string) $bound->total, 'html') ?> / <?= esc((string) ($k['max_devices'] ?: 0), 'html') ?></td>
              <td class="nowrap"><?= esc(($k['expired_date'] ?? '') ? date('d M Y', strtotime((string) $k['expired_date'])) : '—', 'html') ?></td>
              <td>
                <?php if ((string) ($k['status'] ?? '') === '1'): ?>
                  <span class="chip chip--good"><span class="dot dot--live"></span>Active</span>
                <?php elseif ((string) $k['status'] === '0'): ?>
                  <span class="chip chip--bad">Blocked</span>
                <?php else: ?>
                  <span class="chip">Inactive</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="row row--wrap gap-3 mt-4" data-mt>
    <div class="card card--lift w-full">
      <div class="card__head">
        <h3>Quick actions</h3>
        <span class="spacer"></span>
        <span class="eyebrow">Shortcuts</span>
      </div>

      <div class="row row--wrap gap-2">
        <a class="btn btn--primary" href="<?= site_url('keys/generate') ?>" data-press>Generate a key</a>
        <a class="btn" href="<?= site_url('balance') ?>" data-press>Add balance</a>
        <a class="btn" href="<?= site_url('ExtendDuration') ?>" data-press>Extend duration</a>
        <a class="btn btn--ghost" href="<?= site_url('price') ?>">Price plans</a>
      </div>

      <div class="mt-4">
        <div class="row row--between mb-3">
          <span class="label">Keys in use</span>
          <span class="mono muted"><?= esc((string) $usedPct, 'html') ?>% of <?= esc((string) $keycount, 'html') ?></span>
        </div>
        <div class="meter" data-progress="<?= esc((string) $usedPct, 'html') ?>">
          <span class="meter__fill"></span>
        </div>
        <div class="hint">
          <?= esc((string) $usedkeys, 'html') ?> used · <?= esc((string) $freekeys, 'html') ?> unused ·
          <?= esc((string) $banusers, 'html') ?> banned account<?= $banusers == 1 ? '' : 's' ?> ·
          <?= esc((string) $resellerusers, 'html') ?> resellers · <?= esc((string) $users, 'html') ?> accounts
        </div>
      </div>
    </div>
  </div>

  <div class="card mt-4">
    <div class="card__head">
      <h3>This session</h3>
      <span class="spacer"></span>
      <?php if ((int) $user->status === 1): ?>
        <span class="chip chip--good"><span class="dot dot--live"></span>Account active</span>
      <?php else: ?>
        <span class="chip chip--bad">Account <?= (int) $user->status === 2 ? 'banned' : 'pending' ?></span>
      <?php endif; ?>
    </div>

    <div class="stats" style="margin-bottom:0">
      <div class="stat" style="min-height:0">
        <span class="stat__label">Role</span>
        <span class="stat__value" style="font-size:var(--t-lg)"><?= esc(getLevel($user->level), 'html') ?></span>
        <span class="stat__foot">Balance ₹<?= esc((string) (int) $user->saldo, 'html') ?></span>
      </div>
      <div class="stat" style="min-height:0">
        <span class="stat__label">Signed in</span>
        <span class="stat__value" style="font-size:var(--t-lg)"><?= esc($signedInLabel, 'html') ?></span>
        <span class="stat__foot">Session started</span>
      </div>
      <div class="stat" style="min-height:0">
        <span class="stat__label">Auto log-out</span>
        <span class="stat__value" style="font-size:var(--t-lg)"><?= esc($logoutLeft, 'html') ?></span>
        <span class="stat__foot">Remaining</span>
      </div>
      <div class="stat" style="min-height:0">
        <span class="stat__label">Account expiry</span>
        <span class="stat__value" style="font-size:var(--t-lg)" <?= $expiryStamp ? 'data-expiry="' . esc(date('c', $expiryStamp), 'html') . '"' : '' ?>>
          <?= $expiryStamp ? esc(date('d M Y', $expiryStamp), 'html') : '—' ?>
        </span>
        <span class="stat__foot"><?= $expiryStamp ? 'Counts down live' : 'No expiry set' ?></span>
      </div>
    </div>
  </div>

<?= $this->endSection() ?>

<?= $this->section('script') ?>
<script>
  // Text-only ticker on the expiry tile. No easing, no layout shift: the ground
  // stays still and only the digits change.
  (function () {
    var el = document.querySelector('[data-expiry]');
    if (!el) return;
    var end = new Date(el.getAttribute('data-expiry')).getTime();
    if (isNaN(end)) return;
    var pad = function (n) { return n < 10 ? '0' + n : String(n); };
    var tick = function () {
      var diff = end - Date.now();
      if (diff <= 0) { el.textContent = 'Expired'; return; }
      var d = Math.floor(diff / 86400000);
      var h = Math.floor(diff % 86400000 / 3600000);
      var m = Math.floor(diff % 3600000 / 60000);
      var s = Math.floor(diff % 60000 / 1000);
      el.textContent = d + 'd ' + pad(h) + ':' + pad(m) + ':' + pad(s);
      setTimeout(tick, 1000);
    };
    tick();
  })();

  (function () {
    var notes = <?= json_encode($notes, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    notes.forEach(function (n) { App.toast(n[0], n[1]); });
  })();
</script>
<?= $this->endSection() ?>