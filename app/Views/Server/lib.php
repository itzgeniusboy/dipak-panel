<?php
// The old views included a conn.php that no longer exists in the project.
// Use the framework's shared MySQLi connection instead.
$db   = \Config\Database::connect();
$conn = $db->getConnection();

$userDetails21 = null;
$result21      = @mysqli_query($conn, "SELECT * FROM lib WHERE id='1'");
if ($result21 !== false) {
    $userDetails21 = mysqli_fetch_assoc($result21);
}
$userDetails21 = $userDetails21 ?: [];

date_default_timezone_set('Asia/Kolkata');

$lastModified = isset($userDetails21['last_modified']) ? (int) $userDetails21['last_modified'] : 0;
$last = $lastModified > 0 ? date('Y-m-d h:i:s a', $lastModified) : '';
$current = date('Y-m-d h:i:s a');

$name = isset($userDetails21['name']) ? (string) $userDetails21['name'] : '';
$size = isset($userDetails21['size']) ? (string) $userDetails21['size'] : '';
$path = isset($userDetails21['path']) ? (string) $userDetails21['path'] : '';
$link = isset($userDetails21['link']) ? (string) $userDetails21['link'] : '';

// Upload is handled by User::libUpload, which enforces this allowlist server side.
// The form below only mirrors it so a wrong file never costs a round trip.
$accepted = ['so'];
$maxBytes = 10097152;
$maxMb    = round($maxBytes / 1024 / 1024, 2);
?>

<?= $this->extend('Layout/Starter') ?>

<?= $this->section('title') ?><?= esc('Online lib', 'html') ?><?= $this->endSection() ?>

<?= $this->section('content') ?>

  <div class="page-head" data-mt>
    <div>
      <div class="eyebrow">Server</div>
      <h1>Online library</h1>
      <p class="page-head__sub">
        The one file every client downloads before it starts.
        <?= $name !== '' ? ' Currently serving <span class="mono">' . esc($name, 'html') . '</span>.' : ' Nothing published yet.' ?>
      </p>
    </div>
    <div class="spacer"></div>
    <div class="page-head__actions">
      <a class="btn btn--ghost" href="<?= site_url('Server') ?>">Online system</a>
      <a class="btn" href="<?= site_url('dashboard') ?>" data-press>Dashboard</a>
    </div>
  </div>

  <div class="table-wrap mb-4" data-mt>
    <div class="scroll-x">
      <table class="tbl">
        <thead>
          <tr>
            <th>File</th>
            <th class="tbl__num">Size</th>
            <th class="tbl__num">Downloads</th>
            <th class="tr">Upload</th>
          </tr>
        </thead>
        <tbody>
        <?php if ($name === ''): ?>
          <tr>
            <td colspan="4">
              <div class="empty">
                <span class="empty__mark">⇅</span>
                No library published. Upload one from the card below.
              </div>
            </td>
          </tr>
        <?php else: ?>
          <tr>
            <td>
              <div class="tbl__key"><?= esc($name, 'html') ?></div>
              <div class="hint"><?= esc($path !== '' ? $path : 'ONLINELIB', 'html') ?><?= $last !== '' ? ' · updated ' . esc($last, 'html') : '' ?></div>
            </td>
            <td class="tbl__num mono"><?= esc($size !== '' ? $size : '—', 'html') ?></td>
            <td class="tbl__num">
              <?php if ($link !== ''): ?>
                <a class="btn btn--ghost btn--sm" href="<?= esc($link, 'html') ?>" data-press>Download</a>
              <?php else: ?>
                <span class="muted">—</span>
              <?php endif; ?>
            </td>
            <td class="tr">
              <button type="button" class="btn btn--sm" data-pick-lib data-press>Replace file</button>
            </td>
          </tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="card card--glow" data-mt>
    <div class="card__head">
      <h3>Upload a library</h3>
      <span class="spacer"></span>
      <span class="eyebrow"><?= esc(implode(', ', array_map(function ($e) { return '.' . $e; }, $accepted)), 'html') ?> only</span>
    </div>

    <?= form_open('lib/upload') ?>
      <div class="field">
        <label class="label" for="libfile">Library file</label>
        <input class="input" type="file" name="libfile" id="libfile"
               accept=".so" data-lib-input data-lib-max="<?= esc((string) $maxBytes, 'html') ?>" required>
        <div class="hint">
          Accepted extensions: <span class="mono">.so</span> — nothing else is written to
          <span class="mono">ONLINELIB/</span>. Maximum <?= esc((string) $maxMb, 'html') ?> MB.
          The check is enforced server-side by <span class="mono">User::libUpload</span>, this form only mirrors it.
        </div>
      </div>

      <button type="submit" class="btn btn--primary" data-press>Upload library</button>
    <?= form_close() ?>

    <div class="hint mt-4">
      Panel time <?= esc($current, 'html') ?> · uploading replaces the file every client downloads.
    </div>
  </div>

<?= $this->endSection() ?>

<?= $this->section('script') ?>
<script>
  // The row's "Replace file" button drives the one real input in the card.
  (function () {
    var input = document.querySelector('[data-lib-input]');
    document.querySelectorAll('[data-pick-lib]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        if (!input) return;
        input.click();
      });
    });
    if (!input) return;

    // Mirror the server rule so a wrong file never costs a round trip.
    var allowed = ['so'];
    var max = parseInt(input.getAttribute('data-lib-max'), 10) || 0;
    input.addEventListener('change', function () {
      var file = input.files && input.files[0];
      if (!file) return;
      var parts = file.name.split('.');
      var ext = (parts.length > 1 ? parts[parts.length - 1] : '').toLowerCase();
      if (allowed.indexOf(ext) === -1) {
        App.toast('Only .so files are accepted — "' + file.name + '" was rejected before upload.', 'bad', 'Wrong file type');
        input.value = '';
        return;
      }
      if (max && file.size > max) {
        App.toast('That file is ' + (file.size / 1048576).toFixed(2) + ' MB, the limit is ' + (max / 1048576).toFixed(2) + ' MB.', 'bad', 'File too large');
        input.value = '';
      }
    });
  })();
</script>
<?= $this->endSection() ?>