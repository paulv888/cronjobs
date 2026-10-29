# Manual — remote access administration

How to add people to the remote, take access away, and what the tables mean.
Everything here is about `/ha/remote.php` and the tables behind it.

Two facts make the rest of this make sense:

- **Firebase holds the credential** (password, email verification, Google account)
  and `ha_mi_remote_users` holds **access**. Both must agree before anyone gets in.
- **Access is a row**, not an account. Creating a Firebase account grants nothing on
  its own; it only matters if a row with that email exists and has `inuse = 1`.

## Tables

| table | what it is |
| --- | --- |
| `ha_mi_remote_users` | who may use the remote. `email` is the join key to Firebase; `inuse = 1` means allowed |
| `ha_sessions` | live browser sessions. One row per signed-in device |
| `fcm_tokens` | push registrations, keyed by user and device |

List everyone and their state:

```sql
SELECT id, username, email, inuse, method, sessions_cutoff
  FROM ha_mi_remote_users ORDER BY id;
```

`method` is `FIREBASE` for a normal person, `PENDING` for an unapproved request, and
`KEY` on rows that predate the current system.

## Onboarding

### Self-registration (recommended — you never touch their password)

1. They open `https://home.vloons.com/ha/remote.php` and tap **Create an account**.
   They choose their own email and password (confirm box included).
2. Firebase sends a verification email. **Nothing works until they click it** — an
   unverified address is rejected, which is what stops anyone registering as
   `paul@…`.
3. They verify, then sign in. Because their email is unknown, the server records a
   **pending request** (`inuse = 0`, `method = 'PENDING'`) and tells them they are
   awaiting approval.
4. You approve it (below). They can then sign in properly.

### The pending queue

```sql
SELECT id, username, email, updatedate
  FROM ha_mi_remote_users
 WHERE inuse = 0 AND UCASE(method) = 'PENDING'
 ORDER BY id;
```

Approve:

```sql
UPDATE ha_mi_remote_users
   SET inuse = 1, method = 'FIREBASE'
 WHERE email = 'their@example.com';
```

Rename them at the same time if the default is unfriendly (it is the part of the
address before the `@`):

```sql
UPDATE ha_mi_remote_users
   SET inuse = 1, method = 'FIREBASE', username = 'Aynur'
 WHERE email = 'their@example.com';
```

`username` is only a display name — the greeting on the remote.

**Set `method` as well as `inuse`.** Only `inuse` is checked at sign-in, so a row
with `inuse = 1` works even if it still says `PENDING` — but it will keep appearing
in anything that reads the queue by method. Both, or the label lies.

Keep the queue short. Unanswered requests are capped at
`HA_MAX_PENDING_REQUESTS` (25 in `shared/shared_auth.php`); past that, new requests
are logged and dropped, and the person is told they are pending when nothing was
recorded. Rejecting is just deleting the row.

### Google sign-in

Simpler still — create the row and skip Firebase entirely:

```sql
INSERT INTO ha_mi_remote_users (username, email, inuse, method, apikey, secret)
VALUES ('Aynur', 'their.address@gmail.com', 1, 'FIREBASE', '', '');
```

Their first press of **Continue with Google** creates the Firebase account. The
address must match **exactly** what Google returns for that account.

### Admin-created password

Only needed if someone wants a password but cannot self-register. Three steps, and
the middle one is the trap:

1. Firebase console → Authentication → Users → **Add user** (email + password,
   minimum 6 characters).
2. **Mark the address verified.** The console creates users unverified, and the
   server rejects unverified addresses. Either send them a verification email, or
   set it through the Admin API. Skipping this looks like "sign-in could not be
   verified" and is not a bug.
3. Add the row, as in the Google example above.

## Killing sessions

Sign-in lasts 30 days, sliding. To end it:

```sql
UPDATE ha_sessions SET revoked = 1 WHERE user_id = 3;
UPDATE ha_mi_remote_users SET sessions_cutoff = UTC_TIMESTAMP() WHERE id = 3;
```

**Both statements, or the kill is cosmetic.** The first kills the cookies. The
second is what stops the browser silently minting a new session from its stored
Firebase login on the next page load; with the cut-off set, `auth.php` answers
`reauth_required` and the person must type their password (or redo Google) again.

Use `UTC_TIMESTAMP()`, not `NOW()`. Sessions are stored in UTC, and the server's own
timezone would offset the comparison by a few hours — quietly making the cut-off
either too strict or useless.

One device only:

```sql
UPDATE ha_sessions SET revoked = 1 WHERE id = 15;
```

This is *not* durable on its own — their next page load re-mints. It is only
durable when the client signs out too, which is what the **Sign out** button does.

In code: `haSessionRevokeUser($userId)` does the pair, `haSessionRevoke($token)`
does one device.

## Temporarily disabling a user

```sql
UPDATE ha_mi_remote_users SET inuse = 0 WHERE id = 3;
```

One statement, and stronger than killing sessions: it ends their sessions on the
next request *and* blocks new logins, because both `auth.php` and `process.php`
require `inuse = 1`. They are told the account is temporarily suspended and to
contact the administrator.

Restore, including a clean cut-off so they are not forced through an extra password
change:

```sql
UPDATE ha_mi_remote_users SET inuse = 1, sessions_cutoff = NULL WHERE id = 3;
```

Leave `sessions_cutoff` alone if you would rather they re-authenticate from scratch.

**Do not delete the row to revoke access.** Deleting loses the history and does not
end sessions on its own. `inuse = 0` is the off switch.

## Inspecting sessions

```sql
SELECT id, user_id, username, ip, revoked, created_at, last_seen, expires_at
  FROM ha_sessions ORDER BY id DESC;
```

Sessions are purged automatically a week after they expire
(`haSessionPurge()`). `revoked = 1` stays until then, which is fine — revoked rows
cost nothing.

## Push notifications

Alerts reach a phone through four things, in this order:

```
ha_mf_devices (a Mobile row)  ->  ha_mi_remote_users.device_id
                              ->  fcm_tokens            ->  FCM
```

`fcm_tokens.device_id` is filled automatically when a phone registers, taken from
`ha_mi_remote_users.device_id`. So **the only thing you set by hand is the user's
device**, and everything downstream follows.

### Turning alerts on, on a phone

Open the remote and press **Enable alerts**. That asks the browser for permission,
gets an FCM token and files it against the signed-in user and their device.

At home the remote never asks for a password, so there is no session — and a token
has to belong to somebody. Append `?login=1` to the URL to force the sign-in form:

```
https://home.vloons.com/ha/remote.php?login=1
```

Sign in once, press Enable alerts, and the token is attributed properly. The choice
is remembered per user, so a later sign-in does not ask again.

### Pointing a user at their phone

```sql
UPDATE ha_mi_remote_users SET device_id = 303 WHERE username = 'Aynur';
```

`303` is a `ha_mf_devices` row of type *Mobile*. If the phone is not in that table
yet, add it first — the device name is only a label, the type is what matters.

### Subscribing that phone to alerts

Push is addressed by device, so a phone subscribes exactly like any other device:

```sql
INSERT INTO ha_alert_subscriptions (alert_catalogID, deviceID, alert_textID, sort)
VALUES (6, 303, 51, 0);
```

`createAlert()` looks at each subscriber's device type. A **Mobile** device gets a
push built from its `alert_text`; every other type takes the old
`COMMAND_SEND_MESSAGE` path. That is why one catalogue can go to a phone, a Kodi box
and an email address at the same time.

### Sending one from code

```php
sendAlertToDevice([
    'deviceID'     => 303,
    'mess_subject' => 'Front door',
    'mess_text'    => 'Motion detected. Tap to open.',
    'image'        => 'https://home.vloons.com/ha/lastimage/O-Front.jpg',
    'link'         => '/ha/remote.php?name=garage',
]);
```

`mess_text` understands `<br/>`, an `<a href>` (becomes the tap target) and an
`<img>` (becomes the large picture). `image` and `link` keys win over anything
scraped out of the text. Alert text is expanded with the same placeholder engine as
the rest of the app, so `{value_parts___0}`, `{caller___device___description}`,
`{last___result___…}` and `{now}` all work.

### Troubleshooting, in the order that actually finds it

1. **Nothing arrives at all.** Check the phone's OS permission for **Chrome**, not
   just the site. A site can hold `granted` while Android blocks Chrome from posting
   anything, and then the send succeeds and nothing appears, silently.
2. **The tray notification is there but the in-app banner is plain.** The page is
   running an old script. Reload the app; the worker asks for an update on every
   load.
3. **"Device N has no registered phone."** No token: the phone has not pressed
   Enable alerts, or its user has no `device_id`.
4. **Alerts stop after a while.** Battery optimisation. Set `home.vloons.com` to
   Unrestricted on the phone.
5. **The image never appears.** It must be an absolute, publicly reachable URL. A
   relative path or a filesystem path is mapped automatically by
   `alertPublicImageUrl()`, but anything behind the Joomla login renders blank.

Signing out deliberately does **not** unregister the phone: the session ends, the
subscription stays. `window.VloPush.unregister()` exists for an explicit
"turn alerts off" control if you ever add one.

## What the person sees

| message | meaning |
| --- | --- |
| Sign-in could not be verified | the email is not verified in Firebase, or the token failed |
| Your account is waiting for approval | a pending row exists; approve it |
| This account has been disabled | `inuse = 0` |
| Enter your password again | a cut-off was set; re-authenticate |
| Wrong email or password | Firebase rejected the credentials |

## Front-of-house

The remote's bottom-right button reads **Sign out** for anyone with a session and
**Refresh** for LAN callers, who have no session to end. Under the greeting, an
**Enable alerts** bar appears until the phone is registered, then disappears.

When a LAN caller has **no session**, the greeting ends in `Local` and a small
**Local access – sign in** link appears next to it (`?login=1`). That is the only
on-screen way to tell, because the LAN works without a login: at home a dead session
looks exactly like a working one, and it is not noticed until the phone leaves the
WiFi. If someone says they were logged out, check the greeting first, then
`log/remote-auth.log`.

## Session diagnostics

`remote.php` writes one line to `log/remote-auth.log` when a session cookie is
refused, or when a non-LAN caller arrives with no cookie at all:

```
2026-10-29 18:54:17 remote.php: session refused ip=192.168.2.54 reason=no_session
```

The reasons are `no_session` (unrecognised or deleted), `session_expired` (past
`expires_at`), and `user_suspended` (`inuse = 0`).
An anonymous LAN render is normal and deliberately not logged, so the file stays
quiet. It exists because PHP's own error log here is `/var/log/php8.3-fpm.log`,
mode 0600, which the diagnosing account cannot read.


## The Joomla embed

The System Status page loads the remote in an iframe:

```html
<iframe src="/ha/remote.php?name=main" style="width:100%;height:100vh;border:0"></iframe>
```

A frame is a normal page load, so it inherits the session cookie and the LAN bypass
exactly as a standalone visit would. Two things to get right:

- **Never put `loading="lazy"` on it.** A lazy frame below the fold is not fetched at
  all, and looks like a hung request in the network panel — it cost an hour once.
- **Give it a height**, or it collapses to the default 150px.

Because the frame does its own rendering, the Joomla page needs no `includes.php` and
no `loadRemote()` call.

## Rules not to break

- **Sign-up must stay enabled** on the Firebase project — self-registration depends
  on it. Safety comes from the verified-email requirement plus approval, not from
  disabling sign-up.
- **The email is the join key.** If someone changes their address in Firebase, the
  row must change too, or they get "no access".
- **LAN callers never see the login.** `requestIsLocal()` allows the LAN, CLI and
  the Joomla embed through with no credential. That is deliberate; removing it
  breaks cron, the async threads and the embedded remotes.
- **`push.php` is the one exception: it requires a real identity.** A push token is
  only useful attached to a person, and the LAN bypass was filing them against
  `user_id = 0` where no device could ever match. That is what `?login=1` is for.
- **Pushbullet is gone.** `sendBullet()` survives as a shim over
  `sendAlertToDevice(ALERT_DEVICE_ID)` because device command rows still reference
  it by name; the class tree and `includesPushbullet.php` were removed.
