# Remote PWA — functional specification

Status: agreed, not yet implemented.
Scope: `/ha/remote.php` (the remote UI), `process.php` (its API), push notifications.
Out of scope: `esp.php`, `coop.php`, the Joomla site itself.

## A. Identity and login

- **A1** Users sign in with email + password through Firebase Authentication.
  Email/Password provider only — no Google or Apple sign-in, and no self-service
  signup.
- **A2** The login form is part of the PWA. It is not a separate page and does not
  appear in the Joomla site.
- **A3** Login is offered only to non-local callers. A LAN caller goes straight to
  the remote.
- **A4** On success the client POSTs the Firebase ID token to `/ha/auth.php`, same
  origin, as JSON.
- **A5** `auth.php` verifies the token locally: RS256 signature against Google's
  JWKS (cached on disk), `aud` = project ID, `iss` =
  `https://securetoken.google.com/<projectId>`, `iat`/`exp` valid, `sub` present.
  No service account is involved in verification.
- **A6** A Firebase identity maps to an `ha_mi_remote_users` row with `inuse = 1`,
  matched on a new `email` column. Revoking a person stays a database edit.
- **A7** The ID token is exchanged **once** for a server-side session. It is not
  carried on every request.
- **A8** The session cookie is `HttpOnly`, `SameSite=Strict`, path `/ha/`, 30-day
  max-age, and `Secure` only when the request is HTTPS.

### Session lifetime and revocation

- **A9** Sessions slide on **30 days of inactivity**. Any authenticated request
  pushes the expiry out.
- **A10** The Firebase ID token lives 1 hour. The client re-mints it silently and
  re-POSTs to `auth.php` to extend the session. The user never sees a login screen
  mid-session.
- **A11** When 30 days of inactivity is reached the session is dead and the user
  must type their password again. The client calls `signOut()` on Firebase
  automatically when the server reports the session gone — otherwise Firebase's own
  persistence would silently restore the login and defeat the 30-day rule. It also
  stops the poll timer, so an expired session does not produce a 401 every six
  seconds, and then shows the login screen.
- **A12** Authorization is server-side and revocable: sessions live in a table
  (`ha_sessions`), the cookie holds an opaque token whose hash is the key, and
  revoking a row denies the user on their very next request.
- **A13** Setting `ha_mi_remote_users.inuse = 0` also kills live sessions on the
  next request. Disabling a person does not require hunting their session rows.
- **A14** Logout destroys the session row, clears the cookie, and unregisters the
  device's push token.
- **A15** `auth.php` is internet-facing: rate-limited per source IP, and every
  failure is logged with the IP.
- **A16** Every 401 carries a machine-readable `reason`: `no_session`,
  `session_expired` or `user_disabled`. The client tells them apart, because only
  the last two mean "sign out of Firebase and start again".
- **A17** `remote.php` marks itself as the PWA host (`window.VLO_REMOTE_HOST =
  'pwa'`). The Joomla embed leaves it unset, which is how the shared renderer knows
  whether it may show a login in place or has to link out.

## B. Authorization in `process.php`

- **B1** Branches, in order: CLI → allow; local private IP → allow; valid session
  → allow; `Authorization: Bearer <idToken>` with a valid token → allow; else 401.
- **B2** The `apikey` param branch is deleted, along with `PROCESS_REQUIRE_APIKEY`.
- **B3** No regression: cron, async threads, timers, macros, LAN devices and the
  Joomla pages keep working with no credentials at all.
- **B4** The `Bearer` branch ships even though the PWA won't use it — it is the
  escape hatch for a native app or a relocated shell.

## C. Removing the key

- **C1** `remote.php` stops reading `?key=`. It is ignored, not honoured.
- **C2** Deleted: `js/remote.js:266`, the `$apikey` threading at
  `includes/remote.php:36`, the `str_replace('_apikey_', …)` at line 83, and the
  `_apikey_` substitution at `js/remote.js:349-350`.
- **C3** `ha_remote_keys` 424 and 503 become plain `/ha/remote.php?name=av` and
  `/ha/remote.php?name=wifi`.
- **C4** `apikey` values are blanked in `ha_mi_remote_users` after cutover. The
  column stays, unused.
- **C5** `getUrlParameter('key')` disappears entirely — nothing reads a key from
  the URL again.

## D. PWA shell

- **D1** `manifest.json`: `start_url` `/ha/remote.php`, `scope` `/ha/`,
  `display: standalone`, theme `#E9ECE5`.
- **D2** Icons from `images/` — 192, 512, maskable, plus `apple-touch-icon`.
- **D3** Service worker at `/ha/sw.js`, scope `/ha/`.
- **D4** Never cache `process.php` or `auth.php` responses. Shell and static assets
  only.
- **D5** Versioned cache, old caches dropped on activate. A deploy must not leave a
  phone running old JS against new server code.
- **D6** One worker per scope, so `/ha/sw.js` also handles `push`,
  `pushsubscriptionchange` and `notificationclick`. There is no separate
  `firebase-messaging-sw.js`.
- **D7** Standalone layout: `viewport-fit=cover`, safe-area insets, existing dark
  mode and `theme-color` preserved.
- **D8** Offline shows an explicit state, not a dead remote — controls cannot work
  without the server, so say so rather than fail silently.
- **D9** Session bootstrap moves into `includes.php`. Today `process.php` calls
  `session_start()` by hand and `remote.php` never starts one, while
  `includes/remote.php:4` already reads `$_SESSION`.

## E. Push

- **E1** After login the client asks for notification permission and registers
  with `getToken({vapidKey})`.
- **E2** `fcm_tokens` gains `user_id`; keyed `(user_id, machine_id)`.
- **E3** The browser's `machine_id` is a UUID generated once and kept in
  localStorage.
- **E4** Sending uses FCM HTTP v1 with the service account at
  `/etc/ha/vlo-remote-cf179-firebase-adminsdk-fbsvc-0c7f3c9d96.json`; `config.php` holds the path only.
- **E5** One helper sends to a user (all their tokens) or to all users. Tokens are
  deleted on `UNREGISTERED` or `INVALID_ARGUMENT`.
- **E6** Tokens unseen for 90 days are pruned. The five June-2025 rows go now.
- **E7** Payload carries `notification` plus `webpush.fcm_options.link` pointing at
  the relevant remote.
- **E8** `notificationclick` focuses an open client if there is one, otherwise
  opens the link.
- **E9** Foreground messages route through `onMessage` into the existing
  `showMessage()` toast.
- **E10** Push carries events; the 6-second poll keeps doing state. They must not
  double-report the same change.
- **E11** Any send can target one user or all users.

## F. Constraints

- **F1** No secret in the repo. The service account lives outside the docroot,
  `chmod 600`, gitignored, never printed or pasted.
- **F2** No internet must still mean a working house: LAN, cron and the Joomla
  embed are unaffected. Only new remote logins need Google.
- **F3** Deploy stays `deploy.ps1`, followed by a check of both
  `http://192.168.2.11/` and `https://home.vloons.com/ha/`.
- **F4** `auth.php`, `sw.js` and `manifest.json` are public by design. The service
  account is not in the docroot at all.
- **F5** Android is primary. iOS degrades to no push until the PWA is installed on
  the Home Screen, and must not break.
- **F6** The existing `.htaccess` denials stay: `log`, `bin` and `includes/` are
  not reachable from the web.

## G. The Joomla-embedded remotes

- **G1** Embedded remotes never render a sign-in, and are never asked for a
  credential. They keep loading exactly as they do today.
- **G2** That path stays unauthenticated on our side. Whatever protects it is
  Joomla's problem, not `process.php`'s.
- **G3** Consequence to keep in mind: the grid's HTML is renderable by anyone who
  can load the Joomla page, while the controls only work for LAN callers. This is
  today's behaviour and it is not being changed.
- **G4** Pressing a control in the embed with no session returns 401, and the embed
  then offers a **Sign in** link that opens `/ha/remote.php?name=…` in a new tab. It
  never renders a login form itself.
- **G5** In the PWA the same 401 puts the sign-in in place instead of linking out,
  so an expired session costs one password and not a lost page.

## Verification

| Area | How |
| --- | --- |
| Auth matrix | PHP CLI test with synthetic `$_SERVER` and session: local, cookie, bearer, expired, absent, wrong `aud` |
| Login → cookie → `process.php` | `identitytoolkit signInWithPassword` mints a real ID token, no browser needed |
| Push send | `messages:send` with `"validateOnly": true` — proves service account, scope, project ID and payload shape without a device |
| Browser behaviour | `puppeteer-core` against the installed Chrome: submit login, capture console errors and DOM state |
| Session/revocation | PDO assertions on `ha_sessions` and `fcm_tokens` |
| Device-side | Manual: install prompt, standalone display, a notification actually arriving (Android first) |

Non-local calls need a test hook: every request from the workstation arrives with a
LAN `REMOTE_ADDR`, so `requestIsLocal()` would shortcut the whole path under test.
`config.php` carries `TEST_FORCE_REMOTE` (off by default) plus a list of source IPs
for which locality is ignored. That block is deleted once the work is done.
