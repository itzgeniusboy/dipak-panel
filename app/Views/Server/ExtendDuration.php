<?php
// Both extend forms used to be handled here with raw $_SERVER/$_POST, which
// skipped CSRF, had no level or ownership check, and flashed on keys the shell
// never renders. User::ExtendDuration() owns all of it now; this view is
// presentation only, and only reads.
$db   = \Config\Database::connect();
$conn = $db->getConnection();

/* -----------------------------
   Past extensions (read only)
------------------------------*/
$past      = [];
$pastShape = null;
$pastRes   = false;
$colRes    = @mysqli_query($conn, "SHOW COLUMNS FROM history");
$historyCols = [];
if ($colRes) {
    while ($c = mysqli_fetch_assoc($colRes)) {
        $historyCols[] = $c['Field'];
    }
    mysqli_free_result($colRes);
}
if (in_array('info', $historyCols, true) && in_array('user_do', $historyCols, true)) {
    $pastShape = 'old';
    $pastRes = @mysqli_query($conn, "SELECT * FROM history WHERE info LIKE '%xtend%' ORDER BY id_history DESC LIMIT 20");
} elseif (in_array('description', $historyCols, true) && in_array('activity', $historyCols, true)) {
    $pastShape = 'new';
    $pastRes = @mysqli_query($conn, "SELECT * FROM history WHERE description LIKE '%xtend%' ORDER BY id_history DESC LIMIT 20");
}
if ($pastRes) {
    while ($r = mysqli_fetch_assoc($pastRes)) {
        $past[] = $r;
    }
    mysqli_free_result($pastRes);
}

// Connection stays open: the framework owns it.

?>

<?= $this->extend('Layout/Starter') ?>

<?= $this->section('title') ?><?= esc('Extend duration', 'html') ?><?= $this->endSection() ?>

<?= $this->section('content') ?>

  <div class="page-head" data-mt>
    <div>
      <div class="eyebrow">Keys</div>
      <h1>Extend duration</h1>
      <p class="page-head__sub">Add hours or days to one key, or to every key at once.</p>
    </div>
    <div class="spacer"></div>
    <div class="page-head__actions">
      <a class="btn btn--ghost" href="<?= site_url('keys/generate') ?>">Generate key</a>
      <a class="btn" href="<?= site_url('dashboard') ?>" data-press>Dashboard</a>
    </div>
  </div>

  <div class="field-row mt-4" data-mt>

      <div class="card card--glow">
        <div class="card__head">
          <h3>Extend one key</h3>
          <span class="spacer"></span>
          <span class="eyebrow">Single</span>
        </div>

        <?= form_open() ?>
          <input type="hidden" name="extend_single_form" value="1">

          <div class="field">
            <label class="label" for="user_key">User key</label>
            <input class="input input--mono" type="text" name="user_key" id="user_key"
                   placeholder="Enter exact USER KEY" required autocomplete="off">
          </div>

          <div class="field">
            <label class="label" for="duration_single">Add duration</label>
            <input class="input input--mono" type="text" name="duration" id="duration_single"
                   placeholder="e.g. 30D or 12H" required autocomplete="off" data-dur-input>
            <div class="hint">Use <span class="mono">nD</span> for days or <span class="mono">nH</span> for hours.</div>
          </div>

          <div class="alert" data-dur-preview>
            <span class="mono" data-dur-preview-text>Enter a duration</span>
          </div>

          <button type="submit" class="btn btn--primary btn--block" data-press>Extend key</button>
        <?= form_close() ?>
      </div>

      <?php if (in_array((int) $user->level, [1, 2], true)): ?>
      <div class="card">
        <div class="card__head">
          <h3>Extend every key</h3>
          <span class="spacer"></span>
          <span class="eyebrow">Bulk</span>
        </div>

        <?= form_open() ?>
          <input type="hidden" name="extend_keys_form" value="1">

          <div class="field">
            <label class="label" for="duration_all">Duration to add</label>
            <input class="input input--mono" type="text" name="duration" id="duration_all"
                   placeholder="E.g., 30D or 5H" required autocomplete="off" data-dur-input>
            <div class="hint">Format: number followed by H (hours) or D (days). 30D = 30 days.</div>
          </div>

          <div class="alert" data-dur-preview>
            <span class="mono" data-dur-preview-text>Enter a duration</span>
          </div>

          <button type="submit" class="btn btn--primary btn--block"
                  data-confirm="Add this duration to every key in the panel?">
            Extend all keys
          </button>
        <?= form_close() ?>
      </div>
      <?php endif; ?>

  </div>

  <div class="table-wrap mt-4" data-mt>
    <div class="card__head" style="padding:var(--s4) var(--s5) 0">
      <h3>Past extensions</h3>
      <span class="spacer"></span>
      <span class="eyebrow">Last 20</span>
    </div>
    <div class="scroll-x">
      <table class="tbl">
        <thead>
          <tr>
            <th>Key</th>
            <th>Action</th>
            <th class="tbl__num">When</th>
          </tr>
        </thead>
        <tbody>
        <?php if (! $past): ?>
          <tr>
            <td colspan="3">
              <div class="empty">
                <span class="empty__mark">↻</span>
                No extensions recorded yet. Extend a key above and it shows up here.
              </div>
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($past as $row): ?>
            <?php
            $who = $pastShape === 'old'
                ? (string) ($row['user_do'] ?? '')
                : (string) ($row['id_user'] ?? '');
            $what = $pastShape === 'old'
                ? (string) ($row['info'] ?? '')
                : ((string) ($row['activity'] ?? '') . ' — ' . (string) ($row['description'] ?? ''));
            $when = '';
            foreach (['created_at', 'modified', 'created', 'date'] as $stampCol) {
                if (isset($row[$stampCol]) && is_numeric($row[$stampCol]) && (int) $row[$stampCol] > 0) {
                    $when = date('Y-m-d h:i:s a', (int) $row[$stampCol]);
                    break;
                }
            }
            ?>
            <tr>
              <td class="mono"><?= esc($who !== '' ? $who : '—', 'html') ?></td>
              <td><?= esc(trim($what), 'html') ?></td>
              <td class="tbl__num mono"><?= esc($when !== '' ? $when : '—', 'html') ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

<?= $this->endSection() ?>

<?= $this->section('script') ?>
<script>
  // Live "what does this add up to" preview, incl. the resulting expiry date.
  document.querySelectorAll('[data-dur-input]').forEach(function (input) {
    var card = input.closest('.card');
    if (!card) return;
    var out = card.querySelector('[data-dur-preview-text]');

    var update = function () {
      var m = /^\s*(\d+)\s*([hdHD])\s*$/.exec(input.value || '');
      if (!m) {
        out.textContent = 'Enter a duration like 30D or 12H';
        return;
      }
      var amount = parseInt(m[1], 10);
      var hours = m[2].toLowerCase() === 'h' ? amount : amount * 24;
      var days = Math.round((hours / 24) * 100) / 100;
      var until = new Date(Date.now() + hours * 3600 * 1000);
      var when = until.toLocaleString(undefined, {
        year: 'numeric', month: 'short', day: '2-digit', hour: '2-digit', minute: '2-digit'
      });
      out.textContent = '+' + amount + (m[2].toLowerCase() === 'h' ? ' hours' : ' days')
        + ' (' + hours + ' h' + (days % 1 ? ' = ' + days + ' days' : '') + ') → expiry on ' + when;
    };

    input.addEventListener('input', update);
    input.addEventListener('blur', update);
    update();
  });
</script>
<?= $this->endSection() ?>