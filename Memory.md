# Memory — DIPAK GAMING panel

> Managed by opencode. This is your project's memory — edit it freely.

## Project
- **Goal:** Rebuild the CodeIgniter 4 auth screens of the DIPAK GAMING panel against the `dusk` design system in `DESIGN.md`.
- **Last worked:** 2026-10-05

## Progress Log
- 2026-10-05: Rewrote 5 auth views to the new design system — `register`, `forgot-password`, `reset-password`, `otp`, `device-reset`. All now extend `Layout/Auth`, use `.field/.label/.input/.hint/.error-text`, `form_open()`/`form_close()`, `data-mt` on the outer wrapper only, `data-press` on submits, `data-flash` alerts. No Bootstrap, jQuery, SweetAlert2, inline `<style>` or CDN.
- 2026-10-05: Preserved every original `name=` attribute and the original `form_open()` action strings (`forgot-password`, `reset-password`, `verify-otp`, `site_url('device-reset')`).

## Decisions
- Dropped `Layout/msgStatus` from the auth views — it is legacy FontAwesome/`.cyber-alert` markup and the reference `login.php` uses `.alert` + `data-flash` instead. `app.js readFlash()` toasts and removes the first `[data-flash]`.
- Password reveal is a `btn btn--ghost btn--sm` "Show"/"Hide" button (`data-reveal="<input id>"`), not the old floating FontAwesome eye icon — the design system has no affix/positioned-inside-input component and views cannot add CSS.
- `msgInfo` is now rendered (the controllers set it on redirect to `verify-otp` and `reset-password`; the old views read a `getFlashdata('error')` key that is never set, so that message was silently lost).
- `fullname` keeps the original client-side `maxlength="24"` even though the server rule is `max_length[100]` — preserved deliberately to avoid silent behaviour drift.
- `device-reset` submit uses `data-confirm` (App.dialog) because it clears the device lock.

## Next Steps
- Nothing pending on the auth views. Remaining legacy views may still extend `Layout/Starter` / use Bootstrap.

## Open Questions
- `mt-1`, `mt-2`… `mb-2` etc.: DESIGN.md advertises `.mb-0..5` but `app.css` only defines `.mb-0`, `.mb-3`, `.mb-4`, `.mb-5` (and `.mt-2..5`). `.mb-1`/`.mb-2` are missing. Either add them or correct DESIGN.md.
- `app.css` has no spinner-to-error state beyond `bindForms`' 12 s timeout; `is-busy` has no visual `.btn__spin` rules visible yet.
---

## 2026-10-05 — Security & cleanup pass (attacker artifacts, DB config)

**Deleted (attacker/defacement):** `ONLINELIB/sourabhprofessor.so` + `ONLINELIB/` dir, `public/new.txt`, `public/newName.txt`, `public/Newkeys.txt`, `log.txt`, `error_log`, `public/error_log`.
**Deleted (backdoor + dup DB conns):** root `lib.php`, `lib_action.php`, `conn.php`, `db.php`, `public/conn.php`, and `conn.php` in each of `app/Views/{Admin,Auth,Server,User}/`.
**Rewritten:** `public/index.php` — was a WordPress stub (`require wp-blog-header.php`); now a 404 guard pointing at the real root front controller.
**Changed:** `app/Config/Database.php` — all `default` credentials now `env()`-driven. NOTE: `env()` cannot be used in a property initializer (PHP: "Constant expression contains invalid operations", fatal at compile), so the reads happen in `__construct()` after `parent::__construct()`. Property defaults are now empty strings.
**Created:** `.gitignore`, `.env.example` (all 67 keys from the leaked `.env`, placeholders only).

**Key discovery:** `vendor/codeigniter4/framework/system/Config/DotEnv.php` was patched by the attacker — default `$file` changed from `.env` to `/app/Controllers/.env` (verified against upstream v4.1.5; it is the only vendor file with an mtime after the rest of the bundle). That is why `app/Controllers/.env` exists and is the LIVE env file; `uploads/.env` is an unused copy. Both contain DB creds; `app/Controllers/.env` also has `TELEGRAM_BOT_TOKEN`. Fix = restore vendor + reinstall deps + rotate the token.

**Still open (reported, not fixed):** `file_upload.php` and `public/lib.php` are live copies of the same `.so`-upload + raw-SQL backdoor. Unauth `keys/delete/all` wipes `keys_code`. `password` resets via GET token+key in URL. `Auth.php:189` interpolates `$usernam` into SQL. `Price::*` mutating routes have no admin filter. `verify_pass.php` is an old GET-token reset path duplicating `Auth::reset_password`.

**Next:** rotate DB password + bot token, restore vendor, decide on file_upload.php/public/lib.php.

---

## 2026-10-05 — Rebuilt Keys views (list, generate, generate_random, key_edit)

**Rewritten:** `app/Views/Keys/{list,generate,generate_random,key_edit}.php` — all extend `Layout/Starter`, dusk design system only. No Bootstrap/jQuery/SweetAlert/CDN/inline `<style>`; every echo uses `esc($v, 'html')`; forms via `form_open()`.

**Key decisions:**
- `list.php` is server-rendered (`$keylist`), no DataTables. Full columns: #, Key, Game, Status, Devices, Duration, Expires, Actions. `data-mt` only on `.page-head`, `.stats`, `.table-wrap`; `.scroll-x` only on that wide table.
- **Upstream limitation:** `KeysModel` level-1 (owner) list query selects ONLY `user_key`, so `$full` is false → lean Key/Actions table and 0 stats. Edit link needs `id_keys`, so it is hidden for owners; Reset/Delete work by `user_key` and stay enabled.
- `key_edit.php`: levels 1 AND 2 get `registrator`, `expired_date`, `devices` enabled (original disabled them for level 2, which silently discarded controller-validated input). Level 3 sees status only.
- Status semantics kept from controller: `status === '0'` = blocked (wins over expiry); no `expired_date` = "not activated", not a separate state.
- Destructive row/delete actions are submit buttons inside GET forms so `app.js` `data-confirm` uses `form.requestSubmit()` (capture phase); a `data-confirm` on a JS button would loop.
- CI4 **4.1.5** `View::section()` takes ONE argument. `$this->section('title', 'X')` silently opens an unclosed buffer and renders only `"\n"`. All title sections use `section('title')` + content + `endSection()`.

**Verified:** `php -l` clean; offline render harness (`/data/data/com.termux/files/usr/tmp/opencode/render_keys.php`, outputs in `/data/data/com.termux/files/usr/tmp/keysrender/`) renders 13 variants — full/lean/empty list, both generate pages, edit for owner/reseller/validation-error, plus XSS payloads (escaping verified). Harness workarounds: PHP 8.5 vs CI 4.1.5 `Time::createFromTimestamp()` signature conflict → use `stdClass`; `view()` returns a string (no `->render()`); load helpers for `form_open()`.

**Next:** remaining legacy view folders (Admin/Auth/Server/User) still use old markup; `.mb-1`/`.mb-2` are missing from `app.css` (see Open Questions above).
