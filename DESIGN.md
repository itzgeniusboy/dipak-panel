# Design contract — DIPAK GAMING panel

Every view is rebuilt against this. Read this file before writing any view.

## Files that already exist (use them, do not recreate)

- `assets/css/tokens.css` — colours, type, spacing, curves (**the only place hex lives**)
- `assets/css/app.css` — every component class
- `assets/css/components/mascot.css` — mascot poses + floating helper
- `assets/css/components/board.css` — task board surface
- `assets/css/components/transitions.css` — cross-page View Transitions
- `assets/js/motion.js` — `window.MT`, the runtime
- `assets/js/app.js` — `window.App`, toasts and dialogs
- `assets/js/mascot.js` — `window.Mascot`, mood from real events
- `assets/js/board.js` — task board behaviour (pointer drag)
- `app/Views/Partials/Mascot.php` — the mascot SVG (inline, themed)
- `app/Views/Layout/Auth.php` — auth shell, has `$this->renderSection('content')`
- `app/Views/Auth/login.php` — **reference implementation, copy its idioms**

## Look

`dusk` (neon cut). `data-look="dusk"` on `<html>`. Tokens:

| token | value | use |
|---|---|---|
| `--ground` | `#0B0F0C` | the page, ~70% of the frame |
| `--deep` | `#060806` | inputs, table head, insets, board lanes |
| `--card` | `#131813` | surfaces |
| `--card-raised` | `#1A211A` | buttons, toasts |
| `--line` | `#263027` | hairlines and borders |
| `--mute` | `#7C8A7E` | labels, secondary text (4.9:1 on ground) |
| `--ink` | `#EAF3EA` | body and headings (15.6:1) |
| `--accent` | `#39FF14` | the one colour that means something (15.2:1) |
| `--accent-hi` | `#6BFF4D` | accent hover |
| `--accent-lo` | `#22C40B` | accent pressed |
| `--accent-ink` | `#061004` | text **on** the accent — never white |
| `--accent2` | `#B6FF3D` | its partner, used rarely |
| `--good` `--warn` `--bad` | `#2EE6A8` `#FFC24A` `#FF5A5F` | status only |

Type: `--font-display` (Geist) headings, `--font-text` (Inter) body, `--font-mono` (Geist Mono) keys, numbers, labels.

## Markup rules

**Shelled pages** (everything inside the panel) extend `Layout/Starter.php`:

```php
<?= $this->extend('Layout/Starter') ?>
<?= $this->section('title', 'Keys') ?>
<?= $this->section('content') ?>
  <div class="page-head" data-mt>
    <div>
      <div class="eyebrow">Panel</div>
      <h1>Keys</h1>
      <p class="page-head__sub">128 keys · 3 expire this week</p>
    </div>
    <div class="spacer"></div>
    <div class="page-head__actions">
      <a class="btn" href="<?= site_url('keys') ?>">Back</a>
      <a class="btn btn--primary" href="<?= site_url('keys/generate') ?>">New key</a>
    </div>
  </div>
  <div class="stats" data-mt> … </div>
  <div class="table-wrap" data-mt> … </div>
<?= $this->endSection() ?>
```

**Auth pages** (login, register, forgot, reset, otp, device-reset) extend `Layout/Auth` with only a `content` section — see `Auth/login.php`.

## Motion — `MT`, never a hand-rolled ease

| attribute | effect |
|---|---|
| `data-mt` | entrance: lift + scale on `settle`, staggered down the page |
| `data-press` | press: dip and ring-out (buttons, tiles) |
| `data-count="128"` | number counts up on `drift` |
| `data-carry="#target"` | the pressed element morphs into the target, then navigates |
| `data-progress="72"` | meter fills on `snap` |
| `data-no-enter` | opt a row out of a stagger |

Curves: `snap` t80 .23 (lands), `quick` .33 (leaves), `settle` .42 (arrives), `glide` .71 (travel), `drift` .67 (long travel). Springs are `ζ`/`ω`. Never write `ease-in-out` in a view.

Rules that make it feel made rather than animated:

- **Never hide and re-show.** A `display:none` swap is a slide change, however good the timing. Carry something instead.
- **Vary the timing.** An even stagger is a metronome. Group elements and let the gap tighten as it settles.
- **Rests are allowed.** A block that never moves makes the ones that do land.
- **Nothing animates on hover except a tint, a border or a 2px lift.** A table row that slides on hover is a video.
- Small elements, big ground. Do not fill the page edge to edge.

## Components available (see `app.css`)

`.page-head` `.eyebrow` `.spacer` `.stats` `.stat` `.stat__label` `.stat__value` `.stat__foot`
`.card` `.card--flush` `.card--glow` `.card--lift` `.card__head`
`.table-wrap` `table.tbl` `.tbl__key` `.tbl__num` `.empty`
`.btn` `.btn--primary` `.btn--ghost` `.btn--danger` `.btn--lg` `.btn--sm` `.btn--block`
`.field` `.label` `.input` `.input--mono` `.select` `.textarea` `.hint` `.error-text` `.field-row`
`.switch` `.tabs` `.tab` `.chip` `.chip--good` `.chip--warn` `.chip--bad` `.chip--accent` `.dot` `.dot--live`
`.alert` `.alert--good` `.alert--bad` `.alert--warn` `.meter` `.meter__fill`
`.row` `.row--wrap` `.row--end` `.row--between` `.stack` `.gap-2` `.gap-3` `.mt-2..5` `.mb-0..5` `.tr` `.tc` `.nowrap`

### Component files

- **Mascot** — `<?= $this->include('Partials/Mascot', ['mood' => 'idle', 'size' => 72]) ?>`.
  Poses: `idle` `happy` `alert` `thinking`. Drive it with `Mascot.pose('happy', 2000)`;
  it also answers every `app:toast`. The floating helper (`.helper`) lives in `Starter.php`.
- **Task board** — `[data-board]` with `[data-col]` lanes, `[data-list]`, `[data-count]`,
  `[data-add="<col>"]` add forms (mark them `data-no-busy`) and `[data-card]` items.
  State lives in `localStorage('panel.tasks.v1')`; never read the DOM back.
- **Page transitions** — `transitions.css` only; cross-document View Transitions.
  `.brand__mark` carries (morphs) between pages.
`.dialog` (native `<dialog class="dlg">` via `App.dialog`)

## Forms

```php
<?= form_open() ?> … <?= form_close() ?>
```
Always. Keep the original `name=` attributes exactly as they were — the controllers read them. Field-level validation errors come from `$validation` the controller already passes.

## Never

- No Bootstrap classes (`row`, `col-*`, `btn btn-primary` **without** the `--`, `form-control`, `table` without `tbl`, `card` is fine but needs no Bootstrap)
- No `mt-1` `mb-2` `px-3` — use the design system's `--s` scale
- No jQuery, no SweetAlert2 calls. Use `App.toast()`, `App.dialog()`, `App.confirm()` and `data-confirm="text"`
- No inline `<style>` in a view. If a rule is needed twice, add it to `app.css`
- No external CDN. Fonts come from `Layout/*.php`
- No hardcoded credentials, no `$_SERVER` echoes

## Mobile

- Sidebar becomes a drawer (`data-drawer`, `data-drawer-open`, `.scrim`) — `Starter.php` already wires it
- `.shell__main` has its own padding; never add page padding
- Tables: wrap in `.scroll-x` if it has more than 5 columns
