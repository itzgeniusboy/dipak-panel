<?= $this->extend('Layout/Starter') ?>

<?= $this->section('title') ?><?= esc('Circuit assistant', 'html') ?><?= $this->endSection() ?>

<?= $this->section('content') ?>

  <div class="page-head" data-mt>
    <div>
      <div class="eyebrow">Assistant</div>
      <h1>Circuit</h1>
      <p class="page-head__sub">
        One key per assistant. A key acts as <span class="mono"><?= esc((string) $user->username, 'html') ?></span>
        — it can run every button in the panel except changing the password or the username.
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

    <?php if (! empty($newKey)): ?>
      <div class="card card--glow" data-new-key>
        <div class="card__head">
          <h3>Your new key</h3>
          <span class="spacer"></span>
          <span class="chip chip--warn">shown once</span>
        </div>
        <p class="hint">Copy it now — it is stored hashed and can never be shown again.</p>
        <div class="row gap-2 row--wrap" style="align-items:center">
          <code class="kbd mono" id="newKeyValue" style="flex:1;min-width:0;word-break:break-all"><?= esc($newKey, 'html') ?></code>
          <button class="btn btn--primary" type="button" data-copy="#newKeyValue" data-press>Copy</button>
        </div>
        <p class="hint" style="margin-top:var(--s3)">
          Label: <b><?= esc((string) $newKeyLabel, 'html') ?></b>
        </p>
      </div>
    <?php endif; ?>

    <div class="card">
      <div class="card__head">
        <h3>Create a key</h3>
        <span class="spacer"></span>
        <span class="chip chip--accent"><?= count($keys) ?> active</span>
      </div>
      <?= form_open('assistant/create') ?>
        <div class="field-row">
          <div class="field">
            <label class="label" for="label">Label</label>
            <input class="input" id="label" name="label" maxlength="64" placeholder="Phone assistant" value="<?= esc(old('label', ''), 'attr') ?>">
            <span class="hint">A name so you can recognise this key later.</span>
          </div>
          <div class="field" style="align-self:end">
            <button class="btn btn--primary btn--block" type="submit" data-press data-circuit="create-api-key">Create assistant key</button>
          </div>
        </div>
      <?= form_close() ?>
    </div>

    <div class="card card--flush">
      <div class="card__head">
        <h3>Keys</h3>
      </div>
      <?php if (empty($keys)): ?>
        <div class="empty">No keys yet. Create one above.</div>
      <?php else: ?>
        <div class="table-wrap">
          <table class="tbl">
            <thead>
              <tr>
                <th>#</th>
                <th>Label</th>
                <th>Prefix</th>
                <th>Last used</th>
                <th>Status</th>
                <th class="tr">Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($keys as $k): $revoked = ! empty($k->revoked_at); ?>
                <tr>
                  <td class="mono"><?= (int) $k->id ?></td>
                  <td><?= esc((string) $k->label, 'html') ?></td>
                  <td class="mono"><?= esc((string) $k->key_prefix, 'html') ?>…</td>
                  <td class="mono nowrap"><?= esc((string) ($k->last_used_at ?: '—'), 'html') ?></td>
                  <td>
                    <span class="chip <?= $revoked ? 'chip--bad' : 'chip--good' ?>">
                      <?= $revoked ? 'revoked' : 'active' ?>
                    </span>
                  </td>
                  <td class="tr">
                    <?php if (! $revoked): ?>
                      <?= form_open('assistant/revoke/' . (int) $k->id, ['style' => 'display:inline']) ?>
                        <button class="btn btn--danger btn--sm" type="submit"
                                data-confirm="Revoke the &quot;<?= esc((string) $k->label, 'attr') ?>&quot; key? Any assistant using it stops immediately."
                                data-confirm-ok="Revoke" data-confirm-danger>Revoke</button>
                      <?= form_close() ?>
                    <?php else: ?>
                      <span class="hint">—</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

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
        <h3>Use it from outside</h3>
      </div>
      <p class="hint">Send the key as a bearer token. Everything runs as you.</p>
      <pre class="kbd" style="overflow:auto"><code>curl <?= esc(site_url('api/ping'), 'html') ?> \
  -H "Authorization: Bearer ckt_YOUR_KEY"

curl <?= esc(site_url('api/run'), 'html') ?> \
  -H "Authorization: Bearer ckt_YOUR_KEY" \
  -H "Content-Type: application/json" \
  -d '{"action":"key.generate","params":{"duration":24,"max_devices":1}}'</code></pre>
    </div>

  </div>

<?= $this->endSection() ?>

<?= $this->section('script') ?>
<script>
  (function () {
    document.addEventListener('click', function (e) {
      var b = e.target.closest('[data-copy]');
      if (!b) return;
      var el = document.querySelector(b.getAttribute('data-copy'));
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