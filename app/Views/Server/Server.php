<?php
// The old views included a conn.php that no longer exists in the project.
// Use the framework's shared MySQLi connection instead.
$db   = \Config\Database::connect();
$conn = $db->getConnection();

// Maintenance mode
$sql1 = "select * from onoff where id=1";
$result1 = mysqli_query($conn, $sql1);
$userDetails1 = mysqli_fetch_assoc($result1);

// Floating text + its status
$sql2 = "select * from _ftext where id=1";
$result2 = mysqli_query($conn, $sql2);
$userDetails2 = mysqli_fetch_assoc($result2);

// Feature switches
$sql3 = "SELECT * FROM Feature WHERE id=1";
$result3 = mysqli_query($conn, $sql3);
$ModFeatureStatus = mysqli_fetch_assoc($result3);

$status  = isset($userDetails1['status']) ? (string) $userDetails1['status'] : 'off';
$offline = isset($userDetails1['myinput']) ? (string) $userDetails1['myinput'] : '';
$modname = isset($row['modname']) ? (string) $row['modname'] : '';

$ftextStatus = isset($userDetails2['_status']) ? (string) $userDetails2['_status'] : '';
$ftext       = isset($userDetails2['_ftext']) ? (string) $userDetails2['_ftext'] : '';

$features = [
    'ESP' => 'ESP',
    'Item' => 'Items',
    'AIM' => 'Aim-Bot',
    'SilentAim' => 'Silent Aim',
    'BulletTrack' => 'Bullet Track',
    'Memory' => 'Memory',
    'Floating' => 'Floating Texts',
    'Setting' => 'Settings',
];
$enabled = 0;
foreach (array_keys($features) as $fkey) {
    if (isset($ModFeatureStatus[$fkey]) && $ModFeatureStatus[$fkey] === 'on') {
        $enabled++;
    }
}
$loadPct = (int) round(($enabled / count($features)) * 100);

// The library the client downloads — one row, used as the reachability probe.
$libName = '';
$libSize = '';
$sql4 = "SELECT name, size FROM lib WHERE id='1'";
$result4 = mysqli_query($conn, $sql4);
if ($result4) {
    $lib = mysqli_fetch_assoc($result4);
    mysqli_free_result($result4);
    if ($lib) {
        $libName = isset($lib['name']) ? (string) $lib['name'] : '';
        $libSize = isset($lib['size']) ? (string) $lib['size'] : '';
    }
}
// Connection stays open: the framework owns it.

$baseUrl = (string) site_url();
$host    = parse_url($baseUrl, PHP_URL_HOST);
$host    = is_string($host) && $host !== '' ? $host : $baseUrl;

$maintenanceOn = ($status === 'on');

// Controller feedback — the legacy Layout/msgStatus partial is gone, so the shell
// shows these as toasts.
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

<?= $this->section('title') ?><?= esc('Online system', 'html') ?><?= $this->endSection() ?>

<?= $this->section('content') ?>

  <div class="page-head" data-mt>
    <div>
      <div class="eyebrow">Server</div>
      <h1>Online system</h1>
      <p class="page-head__sub">
        <?= $maintenanceOn ? 'Maintenance mode is on — clients are held at the gate.' : 'Serving normally.' ?>
        <?= $modname !== '' ? ' · mod “' . esc($modname, 'html') . '”' : '' ?>
      </p>
    </div>
    <div class="spacer"></div>
    <div class="page-head__actions">
      <a class="btn btn--ghost" href="<?= site_url('lib') ?>">Online lib</a>
      <a class="btn" href="<?= site_url('dashboard') ?>" data-press>Dashboard</a>
    </div>
  </div>

  <div class="card mb-4" data-mt>
    <div class="card__head">
      <h3>Endpoints</h3>
      <span class="spacer"></span>
      <span class="eyebrow">Reachability</span>
    </div>

    <div class="row row--wrap gap-2">
      <?php if ($maintenanceOn): ?>
        <span class="chip chip--warn">Panel API · maintenance</span>
      <?php else: ?>
        <span class="chip chip--good"><span class="dot dot--live"></span>Panel API · serving</span>
      <?php endif; ?>

      <?php if ($libName !== ''): ?>
        <span class="chip chip--good"><span class="dot dot--live"></span>Library published</span>
      <?php else: ?>
        <span class="chip chip--warn">No library file</span>
      <?php endif; ?>

      <span class="chip chip--good"><span class="dot dot--live"></span><?= esc($host, 'html') ?></span>
    </div>

    <div class="mt-4">
      <div class="row row--between mb-3">
        <span class="label">Feature load</span>
        <span class="mono muted"><?= esc((string) $enabled, 'html') ?> of <?= esc((string) count($features), 'html') ?> on</span>
      </div>
      <div class="meter" data-progress="<?= esc((string) $loadPct, 'html') ?>">
        <span class="meter__fill"></span>
      </div>
      <div class="hint">
        <?= $libName !== ''
            ? 'Live library: <span class="mono">' . esc($libName, 'html') . '</span> · ' . esc($libSize !== '' ? $libSize : 'size unknown', 'html')
            : 'Upload a library file so clients have something to download.' ?>
      </div>
    </div>
  </div>

  <div class="field-row mb-4" data-mt>
    <?php if ($user->level != 2): ?>
      <div class="card">
        <div class="card__head">
          <h3>Server based mode</h3>
          <span class="spacer"></span>
          <?php if ($maintenanceOn): ?>
            <span class="chip chip--warn">Maintenance on</span>
          <?php else: ?>
            <span class="chip chip--good"><span class="dot dot--live"></span>Live</span>
          <?php endif; ?>
        </div>

        <?= form_open() ?>
          <input type="hidden" name="status_form" value="1">

          <div class="row row--between mb-4">
            <div>
              <div class="label">Maintenance mode</div>
              <div class="hint">Currently <span class="mono"><?= esc($status !== '' ? $status : 'off', 'html') ?></span>.</div>
            </div>
            <label class="switch">
              <input type="checkbox" name="radios" id="radio" value="on"
                     <?= $maintenanceOn ? 'checked' : '' ?>>
              <span class="switch__track"><span class="switch__knob"></span></span>
            </label>
          </div>

          <div class="field">
            <label class="label" for="myInput">Offline message</label>
            <textarea class="textarea" name="myInput" id="myInput"
                      placeholder="Server is under maintenance"><?= esc($offline, 'html') ?></textarea>
            <div class="hint">Shown to every client while maintenance mode is on.</div>
          </div>

          <button type="submit" class="btn btn--primary btn--block" data-press>Save mode</button>
        <?= form_close() ?>
      </div>
    <?php endif; ?>

    <div class="card">
      <div class="card__head">
        <h3>Change mod name</h3>
        <span class="spacer"></span>
        <span class="eyebrow">Clients</span>
      </div>

      <?= form_open() ?>
        <input type="hidden" name="modname_form" value="1">

        <div class="field">
          <label class="label" for="modname">Mod name</label>
          <input class="input" type="text" name="modname" id="modname"
                 value="<?= esc($modname, 'html') ?>" placeholder="Enter your new mod name" required>
          <div class="hint">Shown in the client window. Current: <?= $modname !== '' ? esc($modname, 'html') : 'not set' ?>.</div>
        </div>

        <button type="submit" class="btn btn--primary btn--block" data-press>Update name</button>
      <?= form_close() ?>
    </div>
  </div>

  <div class="field-row" data-mt>
    <?php if ($user->level == 1): ?>
    <div class="card card--glow">
      <div class="card__head">
        <h3>Mod features</h3>
        <span class="spacer"></span>
        <span class="chip chip--accent"><?= esc((string) $enabled, 'html') ?>/<?= esc((string) count($features), 'html') ?></span>
      </div>

      <?= form_open() ?>
        <input type="hidden" name="feature_form" value="1">

        <div class="stack gap-2 mb-4">
          <?php foreach ($features as $key => $label): ?>
            <?php $on = isset($ModFeatureStatus[$key]) && $ModFeatureStatus[$key] === 'on'; ?>
            <div class="row row--between">
              <span><?= esc($label, 'html') ?></span>
              <label class="switch">
                <input type="checkbox" name="<?= esc($key, 'html') ?>" id="<?= esc($key, 'html') ?>" value="on"
                       <?= $on ? 'checked' : '' ?>>
                <span class="switch__track"><span class="switch__knob"></span></span>
              </label>
            </div>
          <?php endforeach; ?>
        </div>

        <button type="submit" class="btn btn--primary btn--block" data-press>Save features</button>
      <?= form_close() ?>
    </div>
    <?php endif; ?>

    <div class="card">
      <div class="card__head">
        <h3>Floating text</h3>
        <span class="spacer"></span>
        <?php if ($ftextStatus === 'Safe'): ?>
          <span class="chip chip--good"><span class="dot dot--live"></span>Safe mode</span>
        <?php else: ?>
          <span class="chip chip--warn"><?= esc($ftextStatus !== '' ? $ftextStatus : 'Unset', 'html') ?></span>
        <?php endif; ?>
      </div>

      <?= form_open() ?>
        <input type="hidden" name="_ftext" value="1">

        <div class="row row--between mb-4">
          <div>
            <div class="label">Safe mode</div>
            <div class="hint">Currently <span class="mono"><?= esc($ftextStatus !== '' ? $ftextStatus : 'off', 'html') ?></span>.</div>
          </div>
          <label class="switch">
            <input type="checkbox" name="_ftextr" id="_ftextr" value="Safe"
                   <?= $ftextStatus === 'Safe' ? 'checked' : '' ?>>
            <span class="switch__track"><span class="switch__knob"></span></span>
          </label>
        </div>

        <div class="field">
          <label class="label" for="_ftext">Floating text</label>
          <input class="input" type="text" name="_ftext" id="_ftext"
                 value="<?= esc($ftext, 'html') ?>" placeholder="Give feedback else key removed!" required>
          <div class="hint">The line every client sees on screen.</div>
        </div>

        <button type="submit" class="btn btn--primary btn--block" data-press>Update text</button>
      <?= form_close() ?>
    </div>
  </div>

  <div class="field-row mt-4" data-mt>

    <div class="card">
      <div class="card__head">
        <h3>Display name</h3>
        <span class="spacer"></span>
        <span class="eyebrow">Your account</span>
      </div>

      <?= form_open() ?>
        <input type="hidden" name="fullname_form" value="1">

        <div class="field">
          <label class="label" for="fullname">Full name</label>
          <input class="input" type="text" name="fullname" id="fullname"
                 value="<?= esc(old('fullname') ?: (string) ($user->fullname ?? ''), 'html') ?>"
                 placeholder="Letters and spaces only" minlength="4" maxlength="155" required>
          <div class="hint">At least 4 characters, alphabetical and spaces only.</div>
        </div>

        <button type="submit" class="btn btn--primary btn--block" data-press>Save name</button>
      <?= form_close() ?>
    </div>

    <?php if ($user->level == 1): ?>
      <div class="card">
        <div class="card__head">
          <h3>Owner password</h3>
          <span class="spacer"></span>
          <span class="eyebrow">Security</span>
        </div>

        <?= form_open() ?>
          <input type="hidden" name="password_form" value="1">

          <div class="field">
            <label class="label" for="server_current">Current password</label>
            <input class="input" type="password" name="current" id="server_current"
                   autocomplete="current-password" minlength="8" required>
          </div>

          <div class="field">
            <label class="label" for="server_password">New password</label>
            <input class="input" type="password" name="password" id="server_password"
                   autocomplete="new-password" minlength="8" required>
          </div>

          <div class="field">
            <label class="label" for="server_password2">Repeat new password</label>
            <input class="input" type="password" name="password2" id="server_password2"
                   autocomplete="new-password" minlength="8" required>
            <div class="hint">8+ characters with upper, lower, a number and a symbol.</div>
          </div>

          <button type="submit" class="btn btn--primary btn--block" data-press>Update password</button>
        <?= form_close() ?>
      </div>
    <?php endif; ?>

  </div>

<?= $this->endSection() ?>
<?= $this->section('script') ?>
<script>
  // Controller feedback that used to arrive through the old glass alert partial.
  (function () {
    var notes = <?= json_encode($notes, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    notes.forEach(function (n) { App.toast(n[0], n[1]); });
  })();
</script>
<?= $this->endSection() ?>
