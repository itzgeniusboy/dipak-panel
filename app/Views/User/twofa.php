<?php
$chatId  = isset($chatId) && $chatId ? (string) $chatId : (string) ($user->telegram_chat_id ?? '');
$twofaOn = ! empty($twofaOn);

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

<?= $this->section('title') ?><?= esc('Two-Factor', 'html') ?><?= $this->endSection() ?>

<?= $this->section('content') ?>

  <div class="page-head" data-mt>
    <div>
      <div class="eyebrow">Account</div>
      <h1>Two-factor authentication</h1>
      <p class="page-head__sub">
        <?php if ($twofaOn): ?>
          Codes are sent to Telegram chat <span class="mono"><?= esc($chatId, 'html') ?></span> at every sign-in.
        <?php else: ?>
          One extra step at sign-in, delivered over Telegram.
        <?php endif; ?>
      </p>
    </div>
    <div class="spacer"></div>
    <div class="page-head__actions">
      <?php if ($twofaOn): ?>
        <span class="chip chip--good"><span class="dot dot--live"></span>Active</span>
      <?php else: ?>
        <span class="chip">Not enabled</span>
      <?php endif; ?>
    </div>
  </div>

  <div class="stack" data-mt>

    <div class="card card--glow">
      <div class="card__head">
        <h3>Status</h3>
        <span class="spacer"></span>
        <?php if ($twofaOn): ?>
          <span class="chip chip--good"><span class="dot dot--live"></span>Two-step verification on</span>
        <?php elseif ($chatId): ?>
          <span class="chip chip--warn">Ready to enable</span>
        <?php else: ?>
          <span class="chip chip--warn">Chat ID missing</span>
        <?php endif; ?>
      </div>

      <div class="row row--between mb-4">
        <div>
          <div class="label">Two-step verification</div>
          <div class="hint">
            <?php if ($chatId === ''): ?>
              Add your Telegram chat ID first — the switch stays locked until then.
            <?php elseif ($twofaOn): ?>
              A one-time code is requested before the password form opens.
            <?php else: ?>
              Switch on to request a one-time code before the password form opens.
            <?php endif; ?>
          </div>
        </div>

        <?= form_open('user/toggle-twofa') ?>
          <input type="hidden" name="twofa_form" value="1">
          <input type="hidden" name="twofa" value="off">
          <label class="switch" title="<?= $chatId === '' ? 'Add your Chat ID first' : 'Enable or disable two-step verification' ?>">
            <input type="checkbox" name="twofa" id="twofa_switch" value="on"
                   <?= $twofaOn ? 'checked' : '' ?> <?= $chatId === '' ? 'disabled' : '' ?>>
            <span class="switch__track"><span class="switch__knob"></span></span>
          </label>
        <?= form_close() ?>
      </div>

      <div class="field-row">
        <div class="field mb-0">
          <div class="label">Linked chat ID</div>
          <div class="mono"><?= $chatId !== '' ? esc($chatId, 'html') : '—' ?></div>
          <div class="hint">One chat per account. To move 2FA, change it below.</div>
        </div>
        <div class="field mb-0">
          <div class="label">Delivery</div>
          <div class="mono">Telegram bot</div>
          <div class="hint">Codes expire with the sign-in window.</div>
        </div>
      </div>
    </div>

    <?php if (! $twofaOn): ?>
      <div class="card">
        <div class="card__head">
          <h3>Set it up in three steps</h3>
          <span class="spacer"></span>
          <span class="eyebrow"><?= $chatId === '' ? 'Step 1 pending' : 'Step 3 pending' ?></span>
        </div>

        <div class="stack">
          <div class="row gap-3">
            <span class="empty__mark" style="margin:0">1</span>
            <div>
              <div class="label">Open the bot</div>
              <div class="hint">
                Start <span class="mono">@Panelauthentication_bot</span> and press Start so it can message you.
              </div>
              <div class="mt-2">
                <a class="btn btn--ghost btn--sm" href="https://t.me/Panelauthentication_bot"
                   target="_blank" rel="noopener">Open bot</a>
              </div>
            </div>
          </div>

          <div class="row gap-3">
            <span class="empty__mark" style="margin:0">2</span>
            <div>
              <div class="label">Find your chat ID</div>
              <div class="hint">Message <span class="mono">@userinfobot</span> and copy the number it replies with.</div>
            </div>
          </div>

          <div class="row gap-3">
            <span class="empty__mark" style="margin:0">3</span>
            <div>
              <div class="label">Save it, then flip the switch</div>
              <div class="hint">Paste the ID in the card below. The status switch unlocks the moment it saves.</div>
            </div>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <div class="card">
      <div class="card__head">
        <h3>Telegram chat ID</h3>
        <span class="spacer"></span>
        <?php if ($chatId !== ''): ?>
          <span class="chip chip--good"><span class="dot dot--live"></span>Linked</span>
        <?php endif; ?>
      </div>

      <?= form_open('user/update-telegram-chat-id') ?>
        <div class="field">
          <label class="label" for="telegram_chat_id">Chat ID</label>
          <input class="input input--mono" type="text" name="new_telegram_chat_id" id="telegram_chat_id"
                 inputmode="numeric" autocomplete="off"
                 value="<?= esc($chatId, 'html') ?>" placeholder="e.g. 123456789">
          <div class="hint">Digits only, 5–20 characters, and no other account may use it.</div>
        </div>

        <button type="submit" class="btn btn--primary" data-press>Update chat ID</button>
      <?= form_close() ?>

      <?php if ($chatId !== ''): ?>
        <?= form_open('user/update-telegram-chat-id') ?>
          <input type="hidden" name="new_telegram_chat_id" value="">
          <div class="mt-3">
            <button type="submit" class="btn btn--danger"
                    data-confirm="Remove your Telegram chat ID? Two-step verification will be switched off."
                    data-confirm-danger
                    data-confirm-ok="Remove">Remove chat ID</button>
          </div>
        <?= form_close() ?>
      <?php endif; ?>

      <div class="mt-4">
        <svg width="44" height="44" viewBox="0 0 44 44" fill="none" aria-hidden="true" focusable="false">
          <rect x="1" y="1" width="42" height="42" rx="12" stroke="var(--line)"></rect>
          <path d="M12 32V12h11a7 7 0 0 1 0 14h-6l8 6"
                stroke="var(--accent)" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"></path>
          <circle cx="33" cy="11" r="2.5" fill="var(--accent2)"></circle>
        </svg>
        <div class="hint">
          Codes are delivered by the panel bot over Telegram. This panel never stores a shared secret —
          there is nothing to scan, so keep your chat ID private.
        </div>
      </div>
    </div>

  </div>

<?= $this->endSection() ?>

<?= $this->section('script') ?>
<script>
  // The switch posts as soon as it changes: hidden "off" first, checked box after it,
  // so the controller reads the state the user just chose.
  (function () {
    var sw = document.getElementById('twofa_switch');
    if (!sw) return;

    if (sw.disabled) return;

    sw.addEventListener('click', function (ev) {
      ev.preventDefault();
      var on = sw.checked;
      App.confirm('Switch two-step verification ' + (on ? 'on' : 'off') + '?', 'Two-step verification')
        .then(function (yes) {
          if (yes) {
            var form = sw.closest('form');
            if (form) form.requestSubmit();
          } else {
            sw.checked = !on;
          }
        });
    });
  })();

  (function () {
    var notes = <?= json_encode($notes, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    notes.forEach(function (n) { App.toast(n[0], n[1]); });
  })();
</script>
<?= $this->endSection() ?>