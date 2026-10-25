# Working agreements

## How to respond
- Be direct. One recommendation, then stop.
- Only give alternatives if the first approach fails, or if the choice is expensive
  to reverse.
- If unsure, say what you'd do in two lines and proceed.
- Don't ask for sandbox escalation unless it's genuinely needed, and say why in one
  sentence.

## Environment
- Source repo (this workspace): `C:\Users\paulv.PAUL-PCN\Documents\Sources\cronjobs`
- Live web root (Samba): `\\home\www\ha`
- Remote: `git@github.com:paulv888/cronjobs.git` — public repo
- Public vhost: `https://home.vloons.com/ha/` (`/ha/` is an Alias; `.htaccess` active)
- Local-only vhost: `http://192.168.2.11/` (port 80, source-IP gated to 192.168.2/10/25)
- PHP: `C:\php83\php.exe`, on PATH — use `php -l <file>` for syntax checks

Two consequences of this workspace being the repo:
- `git add` / `git commit` work without approval, because `.git` is inside the workspace.
- Writing to `\\home\www\ha` is outside the workspace, so it needs one approval per command.

Never run git on the server. The repo lives on Windows only; the server has no `.git`.

## Deploy: source -> live
`deploy.ps1` in the repo root. Execution policy is AllSigned, so always invoke it as:

```powershell
powershell -ExecutionPolicy Bypass -File .\deploy.ps1 -DryRun   # show the plan first
powershell -ExecutionPolicy Bypass -File .\deploy.ps1           # deploy
```

- Additive copy, no `/MIR`: files that exist only on the server are never deleted
  unless you pass `-Prune`, which lists them and removes them after confirmation.
  `log/`, `lastimage/`, `media/` and `bin/` are never pruned.
- Excluded: `logins.php`, `info.php`, `php_errors.log`, `test.php`,
  `.gitignore`, `deploy.ps1`, `.git`, `.vscode`, `log/`, `lastimage/`, `bin/`, `media/`.
- `config.php` is deployed. It is tracked, holds only paths/subnets/flags, and the
  repo copy overwrites any hand edit on the server.
- `bin/` is excluded because SMB copies lose the Unix exec bit. Deploy those by hand,
  then `chmod +x` on the server.
- Always run `-DryRun` and read the file list before a real deploy. It caught a
  source tree that was older than live, which would have rolled production back.

After deploying, verify over HTTP before calling it done:
- `http://192.168.2.11/<path>` for LAN behaviour
- `https://home.vloons.com/ha/<path>` for the public vhost

`curl.exe` cannot do TLS on this box (schannel credential error). Use PHP with the
curl extension for HTTPS checks, with `CURLOPT_SSL_VERIFYPEER => false`.

## Never commit
`logins.php`, `info.php`, `test.php`, `php_errors.log`,
`bin/fireTVnetflixLogin.sh` — all covered by `.gitignore`. `config.php` is tracked
on purpose because it holds no secrets; if it ever gains a password, rotate first,
then `git rm --cached config.php`.

Prefer explicit `git add <paths>`. Only use `git add -A` after reading `git status`,
because this tree has a history of local debris sitting next to real code.

## Secrets
- Live credentials live in `/etc/ha/logins.php`, outside the web root. Nothing in the
  repo should contain a password; the tracked `config.php` holds none.
- `/etc/ha/firebase-service-account.json` is a full Firebase project credential. It
  stays outside the repo and must never be copied in, printed, or pasted into a
  ticket. `config.php` stores only its path.
- If a secret lands in a commit: **rotate it first**, then rewrite history. Rewriting
  alone does not undo the exposure; a public repo is scraped within minutes.
- That leak already happened once (`bin/fireTVnetflixLogin.sh`, Netflix credentials).

## Safety
- Verify the resolved absolute path before any delete or move.
- Don't leave test or scratch scripts in the web root.
- Don't create backup files inside the docroot; they become web-reachable.

## Current hardening state — do not undo
- The Alexa skills are decommissioned: `alexa_skills.php`, `includesAlexa.php`,
  `Classes/Alexa/` and `includes/skill_*.php` were deleted. Disable the skills in the
  Alexa developer console too, or Amazon keeps calling a 404.
- No `eval()` anywhere: `{calculate___...}` is evaluated by `shared/shared_math.php`,
  a restricted arithmetic parser.
- `process.php` casts numeric request identifiers at the boundary.
- `executeQuery()` requires `sql_from_template` provenance; the HTTP boundary strips it.
- `esp.php` / `coop.php` validate input; identifiers are cast or bound in
  `shared/shared_ha.php` and `shared/shared_db.php`.
- Deleted: `gpslogger.php`, `garage.php`, `monitor_devices.php`.
- Public vhost denies `/ha/esp.php` and `/ha/coop.php` via `<LocationMatch>`.
- Request-derived SQL and shell arguments in `includes/commands_media.php`,
  `shared/shared_ha.php` and `includes/commands_network.php` are bound parameters,
  `(int)` casts or `escapeshellarg()`; `PDOExec()` takes an optional params array.
  Keep them that way — don't reintroduce `'.$var.'` into those queries.

## Outstanding work
- **`remote.php` becomes the PWA, with Firebase Auth identity.** Agreed plan:
  1. Delete the page key. `ha_mi_remote_users` keeps its rows but `apikey` stops
     being a credential. `requestIsLocal()` still lets the LAN and the Joomla
     embed through with no login — that is what keeps the in-house dashboard
     password-free, which is the agreed behaviour there.
  2. Login is Firebase email/password. The client POSTs the Firebase ID token to
     `auth.php`, which verifies it against Google's JWKS (cached on disk), mints a
     PHP session (`HttpOnly; SameSite=Strict; Secure`) holding the `username` and
     the Firebase `sub`, and `process.php` accepts that session as its only
     non-local auth branch. `Authorization: Bearer <idToken>` is accepted too, so a
     native app or a relocated shell needs no server change.
  3. Remove `params.apikey` from `js/remote.js:266` and the `$apikey` threading in
     `includes/remote.php:36`. The `_apikey_` open question is settled: only
     `ha_remote_keys` 424 and 503 use it and both are links to `remote.php?name=…`,
     so they become plain `/ha/remote.php?name=av` and the placeholder disappears.
  4. `manifest.json` plus one service worker at `/ha/sw.js` (scope `/ha/`), reusing
     the icons already in `images/` (192/512, apple-touch, favicons).
- **Push (FCM HTTP v1) rides on the same login, not a later phase.** Web push needs
  a recipient and a VAPID key pair, and there is one service worker per scope, so
  the shell worker and the messaging worker must be the same file. Sending needs the
  service-account JSON at `/etc/ha/firebase-service-account.json`, referenced by
  path from `config.php` — the JWT/scope half already exists in
  `Classes/RestClient.class.php:272`. `fcm_tokens` gains a user column; the five
  June-2025 rows are stale.
- `sp_properties.sql` now lives only in `bin/`. It is the source for the stored
  procedure still called by `commands_factory.php`; the copy in `includes/` was an
  older revision and was deleted.
