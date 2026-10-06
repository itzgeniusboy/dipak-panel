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

---

## 2026-10-05 — Hardening pass c688225 (post-7b668fa)

**Deleted (7 loose doc-root scripts):** `file_upload.php`, `public/lib.php`, `delete.php`, `UserMail.php`, `mail.php`, `test.php`, `config.json`. Grep for refs → fixed 4 code hits (`include('UserMail.php')` in `Auth.php:134,276` + `Authold.php:134,276`); **3 view hits left for the Views agent:** `app/Views/Server/lib.php:39,119,128` still point the `.so` upload form at `file_upload.php` (that form is now dead — rebuild it against a real controller or drop it). Root now has only `index.php`.

**Secrets:** restored `vendor/.../Config/DotEnv.php` default `'.env'` (was `/app/Controllers/.env`); moved that file to `./.env` (mode 600, gitignored). Merged the one key `uploads/.env` had and root lacked: `app.sessionMatchIP = true`; deleted `uploads/.env` (it pointed at a *different* database). `CI_ENVIRONMENT = 'production'`. Verified end-to-end by loading `CodeIgniter\Config\DotEnv(ROOTPATH)` — framework picks up the root file, DBDebug false.

**`.htaccess`:** root denies `^\.env(?!\.example$)` and `\.(sql|log|bak|old|orig|ini)$` before the rewrite block (the `!-f` front-controller fallback never protected real files); `.env.example` explicitly allowed last. `app/.htaccess` re-asserted; `app/Controllers/.htaccess` created (blanket deny).

**Filters fail closed:** `AdminFilter` → no markers = `/login`, unknown user = `/login`, level ∉ [1,2] = `dashboard`; `AuthFilter` requires both markers (`and`→`or` bug). Flash keys/targets unchanged. Used `?? null` + explicit `''` checks rather than `empty()` so a `userid` of `0` isn't treated as logged out.

**Keys.php:** private `requireAdminLevel()` (level 1/2) and `requireKeyAccess($dKey)` (admins any key, else own `registrator`). Applied to `delete_all_keys`, `deleteExpired`, `deleteUnused`, `resetAlldevice` + the identical unrouted twins `deleteKeys`/`startDate`/`alterKeys`/`resetAlkeys`. Also fixed `'expired_date ='.null` (invalid SQL, threw) → `where('expired_date', null)`, dropped stray `echo date()` before redirects, and `msgError`→`msgDanger` in `delete_all_keys` (views only render msgDanger/msgSuccess/msgWarning, so msgError was invisible).

**Deliberate deviation:** `resetAllKeys` delete-by-value uses ownership scoping only, NOT `requireAdminLevel()` — `Keys/list.php:299` shows the per-row Delete to *every* role, and `Keys::index` already scopes the list to own keys, so an admin-only gate would have broken reseller self-service delete. Fixes the actual vuln (delete anyone's key) without breaking the UI.

**Connect.php:** `getExpiryData()` → `Database::connect()->query('... user_key = ?', [$uKey])->getRowArray()` (LoginThrottle idiom, `Config\Database` imported); `user_key` rule → `regex_match[/^[^\'"`\\\\;\x00-\x1F]+$/]`. **Escaping trap:** a double-quoted PHP string turned `\\` into an escape char and `\x00` into a raw NUL — use a single-quoted literal (verified with a throwaway harness against 6 real key shapes + 11 payloads). Response shape untouched.

**Pricing:** `price/add`, `price/update` (the "edit" route is `Price::updatePrice`), `price/delete/(:num)` now carry `['filter' => 'admin']`; `price/` GET stays public.

**Verified:** `php -l` clean on all 8 touched PHP files (PHP 8.4.26 via proot debian; paths must be absolute inside the distro). Commit `c688225`, 18 files, only my changes staged — the parallel Views agent's 7 modified view files were left unstaged.

**Still open (reported, not fixed):** `Auth.php:189` interpolates `$usernam` into SQL; password reset still flows token+key through the URL; `verify_pass.php` GET-token path duplicates `Auth::reset_password`; `User::lib()` recurses into itself on POST; `fullname` has no charset rule, so generated keys can embed arbitrary characters; DB password + `TELEGRAM_BOT_TOKEN` still need rotating.

---

## 2026-10-06 — attacker baseURL, CI4 4.7 migration, green E2E

**Biggest find:** `.env` carried `app.baseURL = 'https://SourabhProfessor.in/'` (attacker's own host, present in the *original* untouched copy too). CI4's Debug Toolbar builds its script tag from `site_url()`, so that host rendered into every page — earlier reports of a "planted debugbar script" were a **misdiagnosis**; the script is stock `system/Debug/Toolbar.php:428`, the compromised part was the baseURL. Effect: all generated links/forms/redirects pointed at the attacker's domain.

**Fix:** `app/Config/App.php` — `public string $baseURL = ''` + a constructor (env() is illegal in a property initializer; same lesson as `Database.php:33`) that reads `app.baseURL` and otherwise derives the host from the validated `HTTP_HOST`, falling back to `http://localhost:8080/`. CI 4.7 `SiteURI::normalizeBaseURL()` throws on an empty baseURL, so a blank env value is not enough. `.env` now has an empty `app.baseURL`, `CI_ENVIRONMENT = 'production'`, and the **real** DB credentials merged back from `app/Controllers/.env` in the original (the e2e scripts had left test creds there; `e2e.sh` now backs up/restores via trap).

**CI4 4.7 porting fixes:** all 31 `match(['get','post'], …)` → uppercase (4.7 deprecation spam), `config_item('app.baseURL')` in `app/Views/Server/Server.php:62` replaced with `site_url()` (CI3-only function, was a 500), stock 4.7 `app/Config/*`, `index.php`, `spark`.

**Schema alignment (test harness only, not the app):** controller expects `price_config` (PK `duration`, hours as int) not `price`; `login_throttle` needs `blocked_until BIGINT` + `last_attempt_at BIGINT`.

**Login round trip finally proven** (was never actually exercised before): seeded owner uses `password_hash(md5('XquxmymXDtWRA66D'.$pw))` per `create_password()`, **and needs a future `expiration_date`** — `Auth::login_action` treats `NULL <= now` as expired. CSRF field name is `csrf_test_name` (from `Security::$tokenName`), not `csrf_token`.

**Verification pass:** `php -l` 108/108; 0 two-arg `section()`; `form_open`/`form_close` 47/47; 0 lowercase routes; 0 CI3 leftovers; 0 attacker-host refs outside `.env`; `node --check` both JS. E2E (MariaDB + `php -S` in one proot run): 25 public routes correct (200s / 302s / 404), login → `/dashboard`, **11 authed routes all 200** incl. `/Server`, 0 fatal/parse, 0 deprecation, no host leak. Commit `53229f4`.

**Still open:** `Auth.php:189` SQL interpolation, reset token in URL, `TELEGRAM_BOT_TOKEN`/DB password rotation, `Connect.php:224` uses md5 for a token, `php-sqlite3` not installed so the `tests` DB group can't run, and the panel still needs a real `app.baseURL` set at deployment (empty → request-derived).

---

## 2026-10-06 — neon-green look, mascot, task board, one-command host

**Reference:** user shared an Instagram reel (a 60s code-drawn launch film for "SCULT OS": camera zooms on every click, one accent colour, sound on every click). This model **cannot read images**, so the reel's frames were useless — the caption metadata (`yt-dlp -J`) is what informed the direction. `yt-dlp` + `ffmpeg` installed in Termux for that.

**Host tool `tools/host.sh`** (proot MariaDB + `php -S 127.0.0.1:8080`, local seeded DB, real `.env` backed up and restored): added `--stop` / `--restore`, a pidfile, a stale-backup recovery, and a guard that refuses to start a second session over a live one.
**Incident:** two instances did run at once (user's terminal + my test). My run's Termux-branch stale-restore rewrote the *live* session's `.env` back to production creds → that session served 500. Diagnosed via `ps` (procs on `pts/1`), `tools/.env.hostbak`, and a live `/login` fetch. Repaired **without** killing the user's session: pointed `.env` at the local DB (backup kept in `tools/.env.hostbak`, ignored by git) → `/login` 200 again.

**Look `dusk` → neon cut:** `tokens.css` is now the only file with hex. Canonical set: ground `#0B0F0C`, deep `#060806`, card `#131813`, raised `#1A211A`, line `#263027`, ink `#EAF3EA`, mute `#7C8A7E`, accent `#39FF14` (+ `--accent-hi #6BFF4D`, `--accent-lo #22C40B`, `--accent-ink #061004`), accent2 `#B6FF3D`. Tints (`--accent-soft/line/glow`) derive via `color-mix`, so they can't drift. **A11y fix:** `--btn-ink`/skip-link were white on the accent (1.4:1) → `--accent-ink` (15.9:1). `app.css` is now hex-free.

**New components (modular):** `assets/css/components/{mascot,board,transitions}.css`, `app/Views/Partials/Mascot.php` (inline SVG, themed, 4 moods), `assets/js/mascot.js` (moods from real events; app.js now dispatches `app:toast`), `assets/js/board.js` (pointer drag — works on touch, unlike HTML5 DnD — plus ←/→ and Backspace, state in `localStorage('panel.tasks.v1')`, DOM never read back). Task board card added to `User/dashboard.php`; its add-forms carry `data-no-busy` because `app.js` binds a 12s busy spinner to every form. Cross-document **View Transitions** via `transitions.css` only (`.brand__mark` morphs between pages).

**Verified:** `php -l` clean (Starter, Auth, Mascot, dashboard) + `bash -n host.sh`; `node --check` on all 4 JS; live HTTP 200 for `/login`, `/register` and every new asset; login round trip → `303 /dashboard`; **jsdom runtime test** (shimmed `Element.animate`/`matchMedia`): 6 entrance animations fired, 3 lanes rendered, add-task → 1 card + count 1 + persisted JSON, mascot mood `happy` then `alert` on `app:toast`, 0 JS errors.

**Open:** the reel's **sound-on-click** is not implemented (not asked for explicitly — say the word and it's a WebAudio tick); visual fidelity to the reel is unverified since I can't see images; `tools/.env.hostbak` exists right now because the user's live session is still running — its cleanup or `--stop` restores it.

**Sound added (2026-10-06):** `assets/js/sound.js` — `window.Sound`, first revision was a duplicated-tone mess and was rewritten clean. Cues are Web Audio synthesis (no files/requests) in one family: `tick` on press of `button/a/[data-press]/[data-card]`, `chime`/`warn` on `app:toast` good/bad, `blip` on unmute. Master gain 0.035. Mute button `[data-sound-toggle]` in the `Starter` header (also on Auth via script link only); state in `localStorage('panel.sound')`, reflected as `data-sound` on `<html>`; AudioContext is created lazily on first gesture (autoplay law) and gracefully no-ops where Web Audio is absent. Verified in jsdom: API present, default on, click toggles glyph 🔊→🔇 + stores `off`, click/toast still hit while muted with **0 JS errors**.

---

## 2026-10-06 — Circuit assistant (voice + on-screen) and owner-unlimited

**What Circuit is:** a voice/on-screen assistant with two front doors — an in-panel Web Speech mic and a per-user **API key** for external callers. It controls the panel (generate/list/delete keys, open pages, prices, identity) but by design **refuses** anything touching password or username (`Actions::FORBIDDEN`, blocked by name *and* by param key).

**Backend:** `app/Models/AssistantKeysModel.php` (`assistant_keys` table, self-provisioning via `ensureSchema()`, key `ckt_`+40hex, **sha256 at rest**, prefix lookup + `hash_equals`, revoked skipped); `app/Libraries/Assistant/{Context,KeyFactory,Actions,CommandRouter}.php`; `app/Filters/ApiKeyFilter.php`; `app/Controllers/{Assistant,Api}.php`; `app/Views/Assistant/index.php`. Routes: `assistant` (GET/POST), `assistant/create`, `assistant/revoke/(:num)`, `assistant/command`; `api` group behind the `apikey` filter (Bearer or `X-API-Key`) for `/api`, `ping`, `actions`, `keys`, `run`. CSRF excepted for `api`/`api/*`.

**Voice router (`CommandRouter`)** — rule-based, EN + Hinglish. Ordering matters and was corrected this pass: assistant-keys → delete-unused → delete-one → **list** (`key(s)` plural now) → **generate** (making verb + key/duration/device, so "banao 2 din ke liye 3 device" parses) → **navigate** (explicit open-verb + page slug, runs *before* noun rules so "open price" opens the page, not the price list) → balance → identity → prices → rename. `keyParams` defaults `duration = 0` = let the panel pick its cheapest plan (owner's plans are 1h/72h/168h/720h; there is no 24h plan).

**Owner-unlimited:** new `isUnlimitedBalance($user)` (level 1) in `nata_helper.php`. `KeyFactory::generate()` loads `helper('nata')`, computes `$unlimited`, skips the balance check, and **skips the saldo deduction**; message says "owner — unlimited, nothing charged" and reports `fees: 0`. `Keys.php` `generate_action` / `generate_random_action` use `$charge = isUnlimitedBalance($user) ? 0 : $getPrice` so owner is never blocked/deducted. `Actions` `user.info`/`balance.show` return `saldo: null, unlimited: true`; dashboard Balance stat shows `∞ / Owner account — unlimited`.

**On-screen agent:** `assets/css/components/circuit-agent.css` + `assets/js/circuit-agent.js` (`window.Circuit` = command/run/say/moveHome/isBusy). Plan steps: `say`, `tap`, `goto` (with optional `speak`; a `goto` parks the remaining steps in `sessionStorage('circuit.resume')` and resumes after navigation). `Starter` carries `[data-agent]`, bubble `[data-agent-say]/[data-agent-text]`, `[data-agent-close]`, `[data-circuit-mic]`, `[data-sound-toggle]`, metas `app-base`/`csrf-token`/`csrf-header-name`; `Header` nav links get `data-circuit="nav:*"` + a new Assistant item. `Actions::targetFor()` maps pages → `[data-circuit="nav:*"]`. `command()` writes the rotated CSRF token back into the meta each call.

**Mobile:** `app.css` `.table-wrap` → `overflow-x:auto`, new `@media (max-width:560px)` (header gap/padding, hide `.brand__sub` + `.shell__top .chip`, `table.tbl{min-width:520px}`, card padding, `.stack` gap, `pre` scroll) and `@media (max-width:380px)` (1-col stats, tighter padding).

**Verified this pass (3 passes):** `php -l` all app PHP clean; `node --check` all JS OK; hex leak check — `app.css`/components hex-free (only legacy `main.css` has hex). Live HTTP: `/dashboard`,`/keys`, `/assistant`, `/price` all 200; all new assets 200; all `data-agent*`/`data-circuit-mic`/`data-circuit="nav:*"` markers present. API curl: 401 no/bad key; ping/actions/user.info/price.list/keys; `key.generate` charges non-owner and returns **owner — unlimited, nothing charged** for owner. Voice `/assistant/command` re-tested with a fresh CSRF token per call — list, navigate, generate, identity, delete-unused all route correctly. **jsdom**: `circuit-agent.js` fetch → bubble text, toast, tap ripple, `is-circuited`, csrf meta rewritten, `circuit.resume` tail stored, 0 JS errors; motion/app/mascot/board/sound harness still green.

**Caveat:** the CSRF cookie token regenerates on every verified POST; `/assistant/command` returns the new token as `csrf` and the JS writes it back — any curl re-test must re-fetch the meta token each call or requests 302.

**Open:** on-screen travel/voice can only be checked via jsdom + curl (no browser/Playwright → no visual check); a requested 48h/2-day key correctly reports "No price is configured for 48h" because no such plan exists.

**Next:** commit the assistant work in focused commits; consider adding a 48h plan if the owner wants 2-day keys; still open from before — `Auth.php:189` SQL interpolation, reset token in URL, DB/bot-token rotation.

---

## 2026-10-06 — optional Gemini layer for Circuit

`app/Libraries/Assistant/Gemini.php` — a natural-language front door, **off unless configured**. `interpret()` sends the sentence to `generativelanguage.googleapis.com` (`gemini-2.0-flash` default) with a system prompt that lists the whole action registry, the pages, and the available durations, and asks for one JSON object. `parse()` accepts only a declared action, drops any param not in the registry's whitelist, int-casts `duration`/`max_devices`/`id`, and re-refuses the password/username reservation; anything unknown or malformed returns `null`. `Assistant::command()` does `Gemini::interpret(...) ?? CommandRouter::route(...)`, so **the LLM is a hint, the rules are the floor** — no key, timeout, HTTP error or bad JSON all fall back cleanly. Key/`model` come from `.env` (`gemini.apiKey`, `gemini.model`), never the front-end; `.env.example` documents them, the running `.env` and `tools/.env.hostbak` got blank slots so it survives a host stop/restore. Responses now carry `via` (gemini|rules) and the Assistant page shows an **AI on / Rules** chip.

**Verified:** `php -l` clean; `parse()` unit table (int casting, fenced JSON tolerance, unknown action → null, `account.password` → null, extra `password` param dropped); live `/assistant/command` with no key and with a **fake** key — both fall back, correct action, no 500; Assistant page chip renders "Rules". Also fixed a duplicate `Actions::run()` call in `Assistant::command()`.

**To enable:** put your Gemini key on the `gemini.apiKey` line of `.env` (blank = built-in rules).
