<?php
// ---------------------------------------------------------------------------
// Firebase ID token verification, and the remote PWA's server side sessions.
//
// Two separate things live here on purpose:
//
//   1. The Firebase ID token is proof of identity for one request. It lives one
//      hour and is never stored. `firebaseVerifyIdToken()` turns it into claims or
//      into a reason it was rejected.
//   2. The session is what actually authorises `process.php`. It lives in
//      `ha_sessions`, slides on inactivity, and is revoked server side. The cookie
//      carries a random token; only its SHA-256 is stored, so a dump of that table
//      cannot be replayed against us.
//
// Verification uses Google's published certificates for the `securetoken`
// service account. That avoids hand-rolling a JWK-to-PEM converter, which is a
// well known source of subtle bugs; the certificates are already PEM.
// ---------------------------------------------------------------------------

if (!defined('HA_SESSION_COOKIE'))       define('HA_SESSION_COOKIE', 'ha_sid');
if (!defined('HA_SESSION_IDLE_DAYS'))    define('HA_SESSION_IDLE_DAYS', 30);
if (!defined('HA_SESSION_SLIDE_AFTER'))  define('HA_SESSION_SLIDE_AFTER', 600);  // seconds
if (!defined('HA_TOKEN_LEEWAY'))         define('HA_TOKEN_LEEWAY', 60);          // clock skew
if (!defined('HA_REQUIRE_VERIFIED_EMAIL')) define('HA_REQUIRE_VERIFIED_EMAIL', true);

function haFirebaseProjectId() {
	return defined('FIREBASE_PROJECT_ID') ? FIREBASE_PROJECT_ID : '';
}

// ------------------------------------------------------------------ base64url
function haBase64UrlDecode($input) {
	$remainder = strlen($input) % 4;
	if ($remainder) $input .= str_repeat('=', 4 - $remainder);
	return base64_decode(strtr($input, '-_', '+/'), true);
}

// ------------------------------------------------- Google's signing certificates
// Cached to a file with the max-age Google sends, because a login must not depend
// on an outbound round trip every time, and because a cached copy is what keeps
// verification working if the house briefly loses the internet.
function haFirebaseCerts($force = false) {
	$cacheFile = sys_get_temp_dir() . '/ha-firebase-certs.json';
	$now = time();

	if (!$force && is_readable($cacheFile)) {
		$cached = json_decode((string)file_get_contents($cacheFile), true);
		if (is_array($cached) && !empty($cached['certs']) && isset($cached['expires']) && $cached['expires'] > $now) {
			return $cached['certs'];
		}
	}

	$ch = curl_init('https://www.googleapis.com/robot/v1/metadata/x509/securetoken@system.gserviceaccount.com');
	$options = array(
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_HEADER => true,
		CURLOPT_CONNECTTIMEOUT => 3,
		CURLOPT_TIMEOUT => 5,
	);
	// The server finds the system CA bundle on its own. A Windows dev box does not,
	// so allow an explicit bundle without ever disabling verification.
	if (defined('FIREBASE_CAINFO') && FIREBASE_CAINFO !== '') {
		$options[CURLOPT_CAINFO] = FIREBASE_CAINFO;
	}
	curl_setopt_array($ch, $options);
	$response = curl_exec($ch);
	$status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
	$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
	$error = curl_error($ch);
	curl_close($ch);

	if ($status === 200 && $headerSize > 0) {
		$headers = substr($response, 0, $headerSize);
		$body = substr($response, $headerSize);
		$certs = json_decode($body, true);
		if (is_array($certs) && count($certs) > 0) {
			$maxAge = 3600;
			if (preg_match('/cache-control:[^\r\n]*max-age=(\d+)/i', $headers, $m)) {
				$maxAge = max(300, (int)$m[1]);
			}
			$payload = json_encode(array('expires' => $now + $maxAge, 'certs' => $certs));
			$tmp = $cacheFile . '.' . getmypid();
			if (@file_put_contents($tmp, $payload) !== false) {
				@rename($tmp, $cacheFile);
			}
			return $certs;
		}
	}

	// Outbound fetch failed. A stale cache is better than locking everyone out.
	if (is_readable($cacheFile)) {
		$cached = json_decode((string)file_get_contents($cacheFile), true);
		if (is_array($cached) && !empty($cached['certs'])) {
			error_log('shared_auth: using stale Firebase certificates (' . ($error !== '' ? $error : 'HTTP ' . $status) . ')');
			return $cached['certs'];
		}
	}
	error_log('shared_auth: no Firebase certificates available (' . ($error !== '' ? $error : 'HTTP ' . $status) . ')');
	return array();
}

function haFirebasePublicKey($keyId) {
	$certs = haFirebaseCerts();
	if (empty($certs[$keyId])) {
		// The key may simply be new: refetch once, ignoring the cache.
		$certs = haFirebaseCerts(true);
	}
	if (empty($certs[$keyId])) return false;

	$certificate = openssl_x509_read($certs[$keyId]);
	if ($certificate === false) return false;
	$publicKey = openssl_pkey_get_public($certificate);
	return $publicKey === false ? false : $publicKey;
}

// ------------------------------------------------------------- verification
// Returns the claims array on success, or null with $reason set to one of
// 'malformed', 'algorithm', 'unknown_key', 'signature', 'expired', 'not_yet_valid',
// 'audience', 'issuer', 'subject'.
function firebaseVerifyIdToken($idToken, &$reason = null) {
	$reason = 'malformed';
	if (!is_string($idToken) || $idToken === '') return null;

	$parts = explode('.', $idToken);
	if (count($parts) !== 3) return null;

	$header = json_decode((string)haBase64UrlDecode($parts[0]), true);
	$claims = json_decode((string)haBase64UrlDecode($parts[1]), true);
	if (!is_array($header) || !is_array($claims)) return null;

	$reason = 'algorithm';
	if (($header['alg'] ?? '') !== 'RS256') return null;
	if (empty($header['kid'])) return null;

	$reason = 'unknown_key';
	$publicKey = haFirebasePublicKey($header['kid']);
	if ($publicKey === false) return null;

	$reason = 'signature';
	$signature = haBase64UrlDecode($parts[2]);
	if ($signature === false || $signature === '') return null;
	if (openssl_verify($parts[0] . '.' . $parts[1], $signature, $publicKey, OPENSSL_ALGO_SHA256) !== 1) {
		return null;
	}

	$project = haFirebaseProjectId();
	if ($project === '') {
		$reason = 'audience';
		return null;
	}

	$now = time();
	$reason = 'expired';
	if (!isset($claims['exp']) || (int)$claims['exp'] < $now - HA_TOKEN_LEEWAY) return null;

	$reason = 'not_yet_valid';
	if (!isset($claims['iat']) || (int)$claims['iat'] > $now + HA_TOKEN_LEEWAY) return null;

	$reason = 'audience';
	if (($claims['aud'] ?? '') !== $project) return null;

	$reason = 'issuer';
	if (($claims['iss'] ?? '') !== 'https://securetoken.google.com/' . $project) return null;

	$reason = 'subject';
	if (!is_string($claims['sub'] ?? '') || $claims['sub'] === '') return null;

	// Access is granted by matching the account to a local user row, so the email
	// has to be trustworthy. Public sign-up is open on a Firebase project by
	// default, and the web api key ships in the client bundle, so anyone can create
	// an account claiming any address. An unverified email proves nothing.
	$reason = 'email_unverified';
	if (!is_string($claims['email'] ?? '') || $claims['email'] === '') return null;
	if (HA_REQUIRE_VERIFIED_EMAIL && empty($claims['email_verified'])) return null;

	$reason = null;
	return $claims;
}

// --------------------------------------------------------------- date helpers
// Sessions are stored in UTC so PHP's and MySQL's time zones can never disagree
// about when a session expired.
function haSqlDate($unixTime) {
	return gmdate('Y-m-d H:i:s', (int)$unixTime);
}

function haSqlDateToUnix($sqlDate) {
	return (int)strtotime($sqlDate . ' UTC');
}

// ------------------------------------------------------------- caller locality
// Whether a request may skip authentication entirely. CLI, loopback and the LAN
// are trusted: that is what keeps cron, the async threads, LAN devices and the
// Joomla-embedded remotes working with no credential, exactly as before.
function requestIsLocal() {
	if (php_sapi_name() === 'cli') return true;
	$ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
	if (stripos($ip, '::ffff:') === 0) $ip = substr($ip, 7);

	// Test hook, see SPEC-remote-pwa.md. A LAN request can never exercise the remote
	// branch, so these addresses are deliberately treated as remote while the flag is
	// on. Neither constant exists in a normal deployment, and both are removed when
	// this work is finished.
	if (defined('TEST_FORCE_REMOTE') && TEST_FORCE_REMOTE
		&& defined('TEST_FORCE_REMOTE_IPS') && in_array($ip, TEST_FORCE_REMOTE_IPS, true)) {
		return false;
	}

	if (filter_var($ip, FILTER_VALIDATE_IP) === false) return false;
	if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) return true;
	if (isset($_SERVER['SERVER_ADDR']) && $ip === $_SERVER['SERVER_ADDR']) return true;
	return false;
}

function authBearerToken() {
	$headers = function_exists('apache_request_headers') ? apache_request_headers() : array();
	foreach ($headers as $name => $value) {
		if (strcasecmp($name, 'Authorization') === 0 && is_string($value)) {
			if (preg_match('/^\s*Bearer\s+(\S+)\s*$/i', $value, $m)) return $m[1];
		}
	}
	if (!empty($_SERVER['HTTP_AUTHORIZATION']) && is_string($_SERVER['HTTP_AUTHORIZATION'])) {
		if (preg_match('/^\s*Bearer\s+(\S+)\s*$/i', $_SERVER['HTTP_AUTHORIZATION'], $m)) return $m[1];
	}
	return '';
}

// Resolves a Firebase identity to a provisioned local user. The same rule auth.php
// applies at login: a verified email is not enough, the row has to exist and be in
// use.
function authUserFromClaims($claims) {
	if (empty($claims['email'])) return false;
	$user = PDOFetchRow('SELECT id, username, sessions_cutoff FROM ha_mi_remote_users WHERE inuse = 1 AND email = ?',
		array($claims['email']));
	return empty($user) ? false : $user;
}

// ------------------------------------------------------------------- sessions
function haSessionTokenFromRequest() {
	if (!empty($_COOKIE[HA_SESSION_COOKIE]) && is_string($_COOKIE[HA_SESSION_COOKIE])) {
		return $_COOKIE[HA_SESSION_COOKIE];
	}
	return '';
}

function haSessionCreate($userId, $username, $firebaseUid) {
	$token = bin2hex(random_bytes(32));
	$now = time();
	$ip = isset($_SERVER['REMOTE_ADDR']) ? substr($_SERVER['REMOTE_ADDR'], 0, 45) : null;
	$agent = isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 255) : null;

	PDOExec('INSERT INTO ha_sessions
	         (session_hash, user_id, username, firebase_uid, created_at, last_seen, expires_at, revoked, ip, user_agent)
	         VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?, ?)',
		array(
			hash('sha256', $token),
			(int)$userId,
			$username,
			(string)$firebaseUid,
			haSqlDate($now),
			haSqlDate($now),
			haSqlDate($now + HA_SESSION_IDLE_DAYS * 86400),
			$ip,
			$agent,
		));

	return $token;
}

// Validates the cookie's token and slides the expiry. $reason is left null on
// success, otherwise it carries the same vocabulary the clients switch on.
function haSessionValidate($token, &$reason = null) {
	$reason = 'no_session';
	if (!is_string($token) || strlen($token) < 32) return null;

	$row = PDOFetchRow('SELECT s.id, s.user_id, s.username, s.firebase_uid, s.last_seen, s.expires_at, s.revoked,
	                           u.inuse AS user_inuse
	                      FROM ha_sessions s
	                 LEFT JOIN ha_mi_remote_users u ON u.id = s.user_id
	                     WHERE s.session_hash = ? LIMIT 1',
		array(hash('sha256', $token)));

	if (empty($row)) return null;                       // unknown or already deleted

	$reason = 'session_expired';
	if ((int)$row['revoked'] === 1) return null;

	// A blocked or removed user loses access on their next request, whatever the
	// session says. There is one message for a blocked account: see
	// includes/commands_users.php.
	if ((int)$row['user_inuse'] !== 1) {
		haSessionRevokeById((int)$row['id']);
		$reason = 'user_suspended';
		return null;
	}

	$reason = 'session_expired';
	$now = time();
	if (haSqlDateToUnix($row['expires_at']) <= $now) {
		haSessionRevokeById((int)$row['id']);
		return null;
	}

	// Slide, but not on every request: the remote polls every six seconds and a
	// write per poll is pointless load.
	if ($now - haSqlDateToUnix($row['last_seen']) > HA_SESSION_SLIDE_AFTER) {
		PDOExec('UPDATE ha_sessions SET last_seen = ?, expires_at = ? WHERE id = ?',
			array(haSqlDate($now), haSqlDate($now + HA_SESSION_IDLE_DAYS * 86400), (int)$row['id']));
	}

	$reason = null;
	return array(
		'id' => (int)$row['id'],
		'user_id' => (int)$row['user_id'],
		'username' => $row['username'],
		'uid' => $row['firebase_uid'],
	);
}

function haSessionRevokeById($sessionId) {
	PDOExec('UPDATE ha_sessions SET revoked = 1 WHERE id = ?', array((int)$sessionId));
}

function haSessionRevoke($token) {
	if (!is_string($token) || $token === '') return 0;
	return PDOExec('UPDATE ha_sessions SET revoked = 1 WHERE session_hash = ?', array(hash('sha256', $token)));
}

// Used when a person is disabled, and by logout-everywhere.
//
// Revoking the rows alone is not enough: the browser still holds a Firebase login,
// so the next page load would silently exchange a fresh token for a fresh session
// and be straight back in. Recording a cut-off makes the next exchange demand a real
// password, because Firebase's `auth_time` claim only moves when the user actually
// authenticates.
function haSessionRevokeUser($userId) {
	$revoked = PDOExec('UPDATE ha_sessions SET revoked = 1 WHERE user_id = ? AND revoked = 0', array((int)$userId));
	PDOExec('UPDATE ha_mi_remote_users SET sessions_cutoff = ? WHERE id = ?',
		array(haSqlDate(time()), (int)$userId));
	return $revoked;
}

// True when this identity authenticated before the user's cut-off, i.e. it predates
// the last revocation and must not be honoured.
function haReauthRequired($user, $claims) {
	if (empty($user['sessions_cutoff'])) return false;
	$cutoff = haSqlDateToUnix($user['sessions_cutoff']);
	$authenticatedAt = isset($claims['auth_time']) ? (int)$claims['auth_time'] : 0;
	return $authenticatedAt < $cutoff;
}

// A real sign-in has happened since the cut-off, so it can be cleared.
function haReauthClear($userId) {
	return PDOExec('UPDATE ha_mi_remote_users SET sessions_cutoff = NULL WHERE id = ?', array((int)$userId));
}

// ------------------------------------------------------------ access requests
// Anyone can register an account and verify an address; that only proves they own
// the inbox. Joining the remote is a row with `method = 'PENDING'` and `inuse = 0`,
// which the admin turns into access by setting `inuse = 1` (the Fabrik form does
// that; haAccessRequestApprove is the same thing in code).
if (!defined('HA_MAX_PENDING_REQUESTS')) define('HA_MAX_PENDING_REQUESTS', 25);

function haAccessRequestCreate($email) {
	if (!is_string($email) || $email === '') return false;

	$existing = PDOFetchRow('SELECT id FROM ha_mi_remote_users WHERE email = ?', array($email));
	if (!empty($existing)) return false;   // already pending, or deliberately off

	// A verified email costs nothing, so unanswered requests are capped. Without
	// this the table becomes somewhere for anyone to leave a row.
	$pending = PDOFetchRow('SELECT COUNT(*) AS n FROM ha_mi_remote_users WHERE inuse = 0 AND UCASE(method) = ?',
		array('PENDING'));
	if ((int)$pending['n'] >= HA_MAX_PENDING_REQUESTS) {
		error_log('shared_auth: pending request cap reached, dropped ' . $email);
		return false;
	}

	// The row needs a display name. The local part of the address is a sensible
	// default and the admin can rename it when approving.
	$name = strstr($email, '@', true);
	if ($name === false || $name === '') $name = $email;

	$inserted = PDOExec('INSERT INTO ha_mi_remote_users (username, email, inuse, method, apikey, secret)
	                     VALUES (?, ?, 0, ?, "", "")',
		array(substr($name, 0, 50), $email, 'PENDING'));

	return $inserted > 0;
}

function haAccessRequestApprove($email, $username = null) {
	$row = PDOFetchRow('SELECT id FROM ha_mi_remote_users WHERE email = ?', array($email));
	if (empty($row)) return false;
	PDOExec('UPDATE ha_mi_remote_users SET inuse = 1, method = ?, username = COALESCE(?, username) WHERE id = ?',
		array('FIREBASE', $username, (int)$row['id']));
	return true;
}

function haAccessRequestsPending() {
	return PDOFetchRows('SELECT id, username, email FROM ha_mi_remote_users
	                      WHERE inuse = 0 AND UCASE(method) = ? ORDER BY id', array('PENDING'));
}

function haSessionCookieSend($token, $expiresUnix) {
	if (headers_sent()) return false;
	setcookie(HA_SESSION_COOKIE, $token, array(
		'expires' => (int)$expiresUnix,
		'path' => '/ha/',
		'secure' => (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off'),
		'httponly' => true,
		'samesite' => 'Strict',
	));
	return true;
}

function haSessionCookieClear() {
	if (headers_sent()) return false;
	setcookie(HA_SESSION_COOKIE, '', array(
		'expires' => time() - 3600,
		'path' => '/ha/',
		'secure' => (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off'),
		'httponly' => true,
		'samesite' => 'Strict',
	));
	return true;
}

// Housekeeping: drop sessions that have been dead for a while. Cheap, and keeps
// the table from growing forever on a box that is never tidied.
function haSessionPurge() {
	$cutoff = haSqlDate(time() - 7 * 86400);
	return PDOExec('DELETE FROM ha_sessions WHERE expires_at < ? OR (revoked = 1 AND last_seen < ?)',
		array($cutoff, $cutoff));
}

// ------------------------------------------------------------- rate limiting
// Failed logins only. Backed by a file keyed on the source IP, so it survives
// process restarts and needs no table. Successes clear the counter, so a person
// who fumbles a password twice is never locked out of their own house.
function haRateLimitFile($bucket) {
	return sys_get_temp_dir() . '/ha-rl-' . hash('sha256', $bucket) . '.json';
}

function haRateLimitState($file) {
	if (!is_readable($file)) return array();
	$raw = @file_get_contents($file);
	$state = $raw === false ? null : json_decode($raw, true);
	return is_array($state) ? $state : array();
}

function haRateLimitExceeded($bucket, $maxFailures = 10, $windowSeconds = 900) {
	$now = time();
	$failures = array_filter(haRateLimitState(haRateLimitFile($bucket)),
		function ($t) use ($now, $windowSeconds) { return $t > $now - $windowSeconds; });
	return count($failures) >= $maxFailures;
}

function haRateLimitFail($bucket, $windowSeconds = 900) {
	$file = haRateLimitFile($bucket);
	$now = time();
	$failures = array_values(array_filter(haRateLimitState($file),
		function ($t) use ($now, $windowSeconds) { return $t > $now - $windowSeconds; }));
	$failures[] = $now;
	$tmp = $file . '.' . getmypid();
	if (@file_put_contents($tmp, json_encode($failures)) !== false) @rename($tmp, $file);
}

function haRateLimitClear($bucket) {
	@unlink(haRateLimitFile($bucket));
}
?>
