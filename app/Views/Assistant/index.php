<?= $this->extend('Layout/Starter') ?>

<?= $this->section('title') ?><?= esc('Circuit assistant', 'html') ?><?= $this->endSection() ?>

<?= $this->section('content') ?>

  <div class="page-head" data-mt>
    <div>
      <div class="eyebrow">Voice assistant</div>
      <h1>Circuit</h1>
      <p class="page-head__sub">
        Circuit runs as <span class="mono"><?= esc((string) $user->username, 'html') ?></span>.
        Tap the mic and talk — it answers you and does the panel work: keys, prices,
        opening pages. It never touches the password or the username.
      </p>
    </div>
    <div class="spacer"></div>
    <div class="page-head__actions">
      <?php if (! empty($gemini)): ?>
        <span class="chip chip--accent" title="Natural language is handled by Gemini">AI on</span>
      <?php else: ?>
        <span class="chip" title="Using the built-in rule-based router; add gemini.apiKey to .env to enable AI">Rules</span>
      <?php endif; ?>
      <a class="btn btn--ghost" href="<?= site_url('keys') ?>">Keys</a>
      <a class="btn" href="<?= site_url('dashboard') ?>" data-press>Back to panel</a>
    </div>
  </div>

  <div class="stack" data-mt>

    <?php if (! empty($canAi)): ?>
      <div class="card">
        <div class="card__head">
          <h3>AI brain (Gemini)</h3>
          <span class="spacer"></span>
          <?php if (! empty($gemini)): ?>
            <span class="chip chip--accent">configured</span>
          <?php else: ?>
            <span class="chip">not set</span>
          <?php endif; ?>
        </div>
        <p class="hint">
          Optional. With a key, Circuit understands free-form talk; without one it uses the
          built-in rules. The key is stored server-side and never shown again.
        </p>
        <?= form_open('assistant/ai') ?>
          <div class="field-row">
            <div class="field">
              <label class="label" for="gemini_key">Gemini API key</label>
              <input class="input" id="gemini_key" name="gemini_key" type="password" autocomplete="off"
                     placeholder="<?= $aiKeyHint !== '' ? esc($aiKeyHint, 'attr') . ' (leave blank to keep)' : 'paste your key' ?>">
            </div>
            <div class="field">
              <label class="label" for="gemini_model">Model</label>
              <input class="input" id="gemini_model" name="gemini_model" maxlength="64"
                     value="<?= esc($aiModel, 'attr') ?>" placeholder="gemini-2.0-flash">
            </div>
          </div>
          <div class="row gap-2 row--wrap" style="margin-top:var(--s3)">
            <button class="btn btn--primary" type="submit" name="action" value="save" data-press>Save AI key</button>
            <?php if (! empty($gemini)): ?>
              <button class="btn btn--ghost" type="submit" name="action" value="clear" formnovalidate
                      data-confirm="Remove the Gemini key? Circuit falls back to the built-in rules.">Remove</button>
            <?php endif; ?>
          </div>
        <?= form_close() ?>
      </div>
    <?php endif; ?>

    <div class="card">
      <div class="card__head">
        <h3>What Circuit can do</h3>
      </div>
      <div class="row row--wrap gap-2">
        <?php foreach ($actions as $name => $meta): ?>
          <span class="chip <?= $meta['write'] ? 'chip--accent' : '' ?>" title="<?= esc($meta['label'], 'attr') ?>">
            <span class="mono"><?= esc($name, 'html') ?></span>
          </span>
        <?php endforeach; ?>
      </div>
      <p class="hint" style="margin-top:var(--s3)">
        Blocked for every key: <span class="mono">account.password</span>, <span class="mono">account.username</span>.
      </p>
    </div>

    <div class="card">
      <div class="card__head">
        <h3>Voice commands</h3>
      </div>
      <p class="hint">
        Tap the mic button (or Circuit) and speak, e.g. “generate a key”,
        “show my keys”, “open price”, “3 din ka key banao”. Circuit answers and
        does it on screen.
      </p>
    </div>

  </div>

<?= $this->endSection() ?>

<?= $this->section('script') ?>
<script>
  (function () {
    if (window.__copyRefBound) { return; }
    window.__copyRefBound = true;
    document.addEventListener('click', function (e) {
      var b = e.target.closest('[data-copy-el]');
      if (!b) return;
      var el = document.querySelector(b.getAttribute('data-copy-el'));
      if (!el) return;
      navigator.clipboard.writeText(el.textContent.trim()).then(function () {
        window.App && App.toast('Key copied to clipboard.', 'good');
      }).catch(function () {
        window.App && App.toast('Copy failed — select it manually.', 'warn');
      });
    });
  })();
</script>
<?= $this->endSection() ?>