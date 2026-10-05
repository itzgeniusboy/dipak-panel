<?php
// Duration -> human label, exactly as the price list has always rendered it.
$planLabel = function ($d) {
    $d = (int) $d;
    if ($d === 1) {
        return '1 Hours Trail';
    }
    if ($d === 2) {
        return '2 Hours Trail';
    }
    if ($d < 24) {
        return $d . ' Hours';
    }
    if ($d % 720 === 0) {
        return ($d / 720) . ' Months';
    }
    if ($d % 24 === 0) {
        return ($d / 24) . ' Days';
    }
    return $d . ' Hours';
};

$durations = isset($durations) && is_array($durations) ? $durations : [];
$overrides = isset($overrides) && is_array($overrides) ? $overrides : [];

$plans = [];
foreach ($overrides as $dur => $price) {
    $plans[] = ['duration' => (int) $dur, 'price' => (int) $price, 'label' => $planLabel($dur)];
}
usort($plans, function ($a, $b) { return $a['duration'] <=> $b['duration']; });

$totalValue = 0;
$avgPrice   = 0;
foreach ($plans as $p) {
    $totalValue += $p['price'];
}
if ($plans) {
    $avgPrice = round($totalValue / count($plans), 2);
}
$cheapest = $plans ? $plans[0] : null;
?>

<?= $this->extend('Layout/Starter') ?>

<?= $this->section('title') ?><?= esc('Price plans', 'html') ?><?= $this->endSection() ?>

<?= $this->section('content') ?>

  <?php if (session()->getFlashdata('msg')): ?>
    <div class="alert alert--good mt-4" data-flash><span><?= esc((string) session()->getFlashdata('msg'), 'html') ?></span></div>
  <?php elseif (session()->getFlashdata('err')): ?>
    <div class="alert alert--bad mt-4" data-flash><span><?= esc((string) session()->getFlashdata('err'), 'html') ?></span></div>
  <?php endif; ?>

  <div class="page-head" data-mt>
    <div>
      <div class="eyebrow">Store</div>
      <h1>Price plans</h1>
      <p class="page-head__sub">
        <?= esc((string) count($plans), 'html') ?> active durations ·
        <?= esc((string) count($durations), 'html') ?> selectable ·
        prices are ₹ per device
      </p>
    </div>
    <div class="spacer"></div>
    <div class="page-head__actions">
      <a class="btn btn--ghost" href="<?= site_url('keys/generate') ?>">Go to generate</a>
      <a class="btn" href="<?= site_url('dashboard') ?>" data-press>Dashboard</a>
    </div>
  </div>

  <div class="stats" data-mt>
    <div class="stat" data-press>
      <span class="stat__label">Active durations</span>
      <span class="stat__value" data-count="<?= esc((string) count($plans), 'html') ?>">0</span>
      <span class="stat__foot"><?= $plans ? 'Cheapest ₹' . esc((string) $cheapest['price'], 'html') : 'Nothing to sell yet' ?></span>
    </div>
    <div class="stat" data-press>
      <span class="stat__label">Total price value</span>
      <span class="stat__value">₹<span data-count="<?= esc((string) $totalValue, 'html') ?>">0</span></span>
      <span class="stat__foot">Sum across every plan</span>
    </div>
    <div class="stat" data-press>
      <span class="stat__label">Average price</span>
      <span class="stat__value">₹<span data-count="<?= esc((string) $avgPrice, 'html') ?>">0</span></span>
      <span class="stat__foot">Per device, per plan</span>
    </div>
  </div>

  <div class="table-wrap" data-mt>
    <div class="scroll-x">
      <table class="tbl">
        <thead>
          <tr>
            <th>Plan</th>
            <th class="tbl__num">Hours</th>
            <th class="tbl__num">Days</th>
            <th class="tbl__num">Price</th>
            <th class="tr">Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php if (! $plans): ?>
          <tr>
            <td colspan="5">
              <div class="empty">
                <span class="empty__mark">≈</span>
                No active durations. Add one below and it appears in the generator.
              </div>
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($plans as $p): ?>
            <tr>
              <td>
                <strong><?= esc($p['label'], 'html') ?></strong>
                <?php if ($p === $cheapest): ?>
                  <span class="chip chip--accent">entry</span>
                <?php endif; ?>
              </td>
              <td class="tbl__num mono"><?= esc((string) $p['duration'], 'html') ?></td>
              <td class="tbl__num"><?= esc(rtrim(rtrim(number_format($p['duration'] / 24, 2), '0'), '.'), 'html') ?></td>
              <td class="tbl__num mono">₹<?= esc((string) $p['price'], 'html') ?></td>
              <td class="tr">
                <?= form_open('price/delete/' . (int) $p['duration']) ?>
                  <button type="submit" class="btn btn--danger btn--sm"
                          data-confirm="Remove &quot;<?= esc($p['label'], 'html') ?>&quot;? It disappears from the generator."
                          data-confirm-danger
                          data-confirm-ok="Remove">Remove</button>
                <?= form_close() ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="field-row mt-4" data-mt>

    <div class="card card--glow">
      <div class="card__head">
        <h3>Add duration</h3>
        <span class="spacer"></span>
        <span class="eyebrow">New plan</span>
      </div>

      <?= form_open('price/add') ?>
        <div class="field">
          <label class="label" for="new_duration">Duration in hours</label>
          <input class="input input--mono" type="number" name="duration" id="new_duration"
                 min="1" step="1" inputmode="numeric" required placeholder="24">
          <div class="hint">24 = 1 day · 720 = 1 month. Anything else stays in hours.</div>
        </div>

        <div class="field">
          <label class="label" for="new_price">Price per device (₹)</label>
          <input class="input input--mono" type="number" name="price" id="new_price"
                 min="1" step="1" inputmode="numeric" required placeholder="150">
        </div>

        <button type="submit" class="btn btn--primary btn--block" data-press>Add duration</button>
      <?= form_close() ?>
    </div>

    <div class="card">
      <div class="card__head">
        <h3>Update price</h3>
        <span class="spacer"></span>
        <span class="eyebrow">Active only</span>
      </div>

      <?php if (! $durations): ?>
        <div class="empty">
          <span class="empty__mark">!</span>
          No active durations yet — add one on the left first.
        </div>
      <?php else: ?>
        <?= form_open('price/update') ?>
          <div class="field">
            <label class="label" for="duration">Duration</label>
            <select class="select" name="duration" id="duration" required>
              <?php foreach ($durations as $d): ?>
                <option value="<?= esc((string) $d, 'html') ?>"><?= esc($planLabel($d), 'html') ?></option>
              <?php endforeach; ?>
            </select>
            <div class="hint" id="currentPriceHelp">Current: —</div>
          </div>

          <div class="field">
            <label class="label" for="price">New price per device (₹)</label>
            <input class="input input--mono" type="number" name="price" id="price"
                   min="1" step="1" inputmode="numeric" required placeholder="1200">
          </div>

          <button type="submit" class="btn btn--primary btn--block" data-press>Update price</button>
        <?= form_close() ?>
      <?php endif; ?>
    </div>

  </div>

<?= $this->endSection() ?>

<?= $this->section('script') ?>
<script>
  // Show the price a duration already carries, so nobody guesses.
  (function () {
    var map = <?= json_encode($overrides, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var sel = document.getElementById('duration');
    var price = document.getElementById('price');
    var help = document.getElementById('currentPriceHelp');
    if (!sel || !price || !help) return;

    var sync = function () {
      var current = map[sel.value];
      if (current === undefined) {
        help.textContent = 'Current: —';
        return;
      }
      help.textContent = 'Current: ₹ ' + current;
      price.placeholder = current;
    };
    sel.addEventListener('change', sync);
    sync();
  })();
</script>
<?= $this->endSection() ?>