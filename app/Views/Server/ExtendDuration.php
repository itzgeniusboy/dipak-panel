<?php
// The old views included a conn.php that no longer exists in the project.
// Use the framework's shared MySQLi connection instead.
$db   = \Config\Database::connect();
$conn = $db->getConnection();

/* -----------------------------
   Extend ALL Keys (POST)
------------------------------*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['extend_keys_form'])) {
    $input = trim($_POST['duration'] ?? '');
    if (preg_match('/^(\d+[HD])$/i', $input, $matches)) {
        $extra_duration = strtoupper($matches[1]);
        preg_match('/^(\d+)([HD])$/', $extra_duration, $d_match);
        $extra_hours = ($d_match[2] === "H") ? (int)$d_match[1] : ((int)$d_match[1] * 24);

        $stmt = $conn->prepare("UPDATE keys_code SET duration = duration + ?");
        $stmt->bind_param("i", $extra_hours);

        if ($stmt->execute()) {
            $history_cols = [];
            $colRes = @mysqli_query($conn, "SHOW COLUMNS FROM history");
            if ($colRes) {
                while ($c = mysqli_fetch_assoc($colRes)) {
                    $history_cols[] = $c['Field'];
                }
                mysqli_free_result($colRes);
            }
            if (in_array('info', $history_cols, true) && in_array('user_do', $history_cols, true)) {
                @mysqli_query($conn, "INSERT INTO history (keys_id, user_do, info) VALUES (NULL, 'admin', 'Extended all keys by {$extra_duration}')");
            } elseif (in_array('description', $history_cols, true) && in_array('activity', $history_cols, true)) {
                @mysqli_query($conn, "INSERT INTO history (id_user, activity, description) VALUES (NULL, 'Extend Key', 'Extended all keys by {$extra_duration}')");
            }

            session()->setFlashdata('message', "All keys extended by {$extra_duration}!");
            session()->setFlashdata('alert-class', 'success');
        } else {
            session()->setFlashdata('message', "Failed to extend keys: " . $stmt->error);
            session()->setFlashdata('alert-class', 'danger');
        }
        $stmt->close();
    } else {
        session()->setFlashdata('message', "Invalid duration format. Use e.g. 30D or 5H");
        session()->setFlashdata('alert-class', 'danger');
    }
}

/* -----------------------------
   Extend SINGLE Key (POST)
------------------------------*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['extend_single_form'])) {
    $user_key = trim($_POST['user_key'] ?? '');
    $input    = trim($_POST['duration'] ?? '');

    if ($user_key === '') {
        session()->setFlashdata('message', "Please enter a User Key.");
        session()->setFlashdata('alert-class', 'danger');
    } elseif (!preg_match('/^\d+[HD]$/i', $input)) {
        session()->setFlashdata('message', "Invalid duration format. Use e.g. 30D or 5H");
        session()->setFlashdata('alert-class', 'danger');
    } else {
        $extra_duration = strtoupper($input);
        preg_match('/^(\d+)([HD])$/', $extra_duration, $d_match);
        $extra_hours = ($d_match[2] === "H") ? (int)$d_match[1] : ((int)$d_match[1] * 24);

        $stmt = $conn->prepare("UPDATE keys_code SET duration = duration + ? WHERE user_key = ?");
        $stmt->bind_param("is", $extra_hours, $user_key);

        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                $history_cols = [];
                $colRes = @mysqli_query($conn, "SHOW COLUMNS FROM history");
                if ($colRes) {
                    while ($c = mysqli_fetch_assoc($colRes)) {
                        $history_cols[] = $c['Field'];
                    }
                    mysqli_free_result($colRes);
                }
                $safeKey = mysqli_real_escape_string($conn, $user_key);
                $note = 'Extended key ' . $safeKey . ' by ' . $extra_duration;
                if (in_array('info', $history_cols, true) && in_array('user_do', $history_cols, true)) {
                    @mysqli_query($conn, "INSERT INTO history (keys_id, user_do, info) VALUES (NULL, 'panel', '{$note}')");
                } elseif (in_array('description', $history_cols, true) && in_array('activity', $history_cols, true)) {
                    @mysqli_query($conn, "INSERT INTO history (id_user, activity, description) VALUES (NULL, 'Extend Key', '{$note}')");
                }

                session()->setFlashdata('message', "Extended key '{$user_key}' by {$extra_hours} hours.");
                session()->setFlashdata('alert-class', 'success');
            } else {
                session()->setFlashdata('message', "No key updated. Check if the USER KEY exists.");
                session()->setFlashdata('alert-class', 'warning');
            }
        } else {
            session()->setFlashdata('message', "Failed to extend key: " . $stmt->error);
            session()->setFlashdata('alert-class', 'danger');
        }
        $stmt->close();
    }
}

/* -----------------------------
   Past extensions (read only)
------------------------------*/
$past      = [];
$pastShape = null;
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

$flashMsg   = (string) (session()->getFlashdata('message') ?? '');
$flashClass = (string) (session()->getFlashdata('alert-class') ?? '');
// Same cleanup the old SweetAlert did — the design has no emoji.
$flashMsg   = trim(preg_replace('/[\x{2705}\x{274C}\x{26A0}\x{FE0F}]/u', '', $flashMsg));
$flashKind  = $flashClass === 'success' ? 'good' : ($flashClass === 'warning' ? 'warn' : 'bad');
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

  <?php if ($flashMsg !== ''): ?>
    <div class="alert alert--<?= esc($flashKind, 'html') ?>" data-flash style="margin-top:var(--s4)">
      <span><?= esc($flashMsg, 'html') ?></span>
    </div>
  <?php endif; ?>

  <?php if ($user->level != 2): ?>
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

    </div>
  <?php endif; ?>

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