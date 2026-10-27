<?php
// ---------------------------------------------------------------------------
// Exchange a Firebase ID token for a server side session.
//
// This is the only place a credential is minted. The browser SDK signs the user in
// and hands us the ID token; we verify it, match the (verified) email to a local
// user, and set an opaque cookie. The ID token itself is never stored and never
// used again - every later request is authorised by the session row, which is what
// makes revocation possible.
//
// Reachable from the internet, so it is POST only, rate limited per source IP, and
// logs every rejection with the address it came from.
// ---------------------------------------------------------------------------
require_once 'includes.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

function authRespond($status, $payload) {
	http_response_code($status);
	echo json_encode($payload);
	exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
	header('Allow: POST');
	authRespond(405, array('ok' => false, 'reason' => 'method_not_allowed'));
}

// Prefer JSON, but accept a plain form so the endpoint can be poked with curl.
$idToken = '';
$body = json_decode((string)file_get_contents('php://input'), true);
if (is_array($body) && isset($body['idToken']) && is_string($body['idToken'])) {
	$idToken = $body['idToken'];
} elseif (isset($_POST['idToken']) && is_string($_POST['idToken'])) {
	$idToken = $_POST['idToken'];
}

$ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
$bucket = 'auth|' . $ip;

// Signing out. No token needed: the cookie is the credential, revoking it is
// idempotent, and a stale cookie deserves the same answer as a live one.
$action = (is_array($body) && isset($body['action']) && is_string($body['action'])) ? $body['action'] : '';
if ($action === 'logout') {
	haSessionRevoke(haSessionTokenFromRequest());
	haSessionCookieClear();
	authRespond(200, array('ok' => true, 'logged_out' => true));
}

if (haRateLimitExceeded($bucket)) {
	error_log('auth.php: rate limited ' . $ip);
	authRespond(429, array('ok' => false, 'reason' => 'rate_limited',
		'message' => 'Too many attempts. Try again later.'));
}

if ($idToken === '') {
	haRateLimitFail($bucket);
	authRespond(400, array('ok' => false, 'reason' => 'missing_token',
		'message' => 'No ID token supplied.'));
}

$reason = null;
$claims = firebaseVerifyIdToken($idToken, $reason);
if ($claims === null) {
	haRateLimitFail($bucket);
	error_log('auth.php: rejected token from ' . $ip . ' reason=' . $reason);
	authRespond(401, array('ok' => false, 'reason' => 'invalid_token',
		'message' => 'Sign-in could not be verified.'));
}

// The email is verified at this point. That proves the caller controls the inbox,
// but not that they may use the remote: a local row has to say so.
//
// Someone who verifies an address we have never seen is a request to join, not a
// login. Record it as a pending row so the admin has something to approve, and say
// so plainly instead of "not authorised".
$user = PDOFetchRow('SELECT id, username, inuse, method, sessions_cutoff
                       FROM ha_mi_remote_users WHERE email = ?', array($claims['email']));

if (empty($user)) {
	$recorded = haAccessRequestCreate($claims['email']);
	haRateLimitFail($bucket);
	error_log('auth.php: access request from ' . $claims['email'] . ' (' . $ip . ')'
		. ($recorded ? ' recorded' : ' not recorded'));
	authRespond(403, array('ok' => false, 'reason' => 'pending_approval',
		'message' => 'Your account is waiting for approval.'));
}

// A row that exists but is not active is either a pending request or a blocked
// account. There is no separate "disabled" message: if the row is still here, the
// person is told they are suspended and to contact the administrator.
if ((int)$user['inuse'] !== HA_USER_ACTIVE) {
	haRateLimitFail($bucket);

	if (haUserBlockReason($user) === 'pending_approval') {
		error_log('auth.php: pending approval for ' . $claims['email'] . ' from ' . $ip);
		authRespond(403, array('ok' => false, 'reason' => 'pending_approval',
			'message' => haUserBlockMessage($user)));
	}

	error_log('auth.php: suspended user ' . $user['username'] . ' from ' . $ip);
	authRespond(403, array('ok' => false, 'reason' => 'user_suspended',
		'message' => HA_USER_SUSPENDED_MESSAGE));
}

// A revocation happened after this identity authenticated. The token is genuine, but
// it predates the cut-off, so only a fresh password will do. Without this a revoked
// person simply reloads the page and is silently let back in.
if (haReauthRequired($user, $claims)) {
	haRateLimitFail($bucket);
	error_log('auth.php: reauth required for ' . $user['username'] . ' from ' . $ip);
	authRespond(403, array('ok' => false, 'reason' => 'reauth_required',
		'message' => 'Sign in again with your password.'));
}

$token = haSessionCreate($user['id'], $user['username'], $claims['sub']);
haSessionCookieSend($token, time() + HA_SESSION_IDLE_DAYS * 86400);
haReauthClear($user['id']);
haRateLimitClear($bucket);

error_log('auth.php: login ' . $user['username'] . ' from ' . $ip);
authRespond(200, array(
	'ok' => true,
	'username' => $user['username'],
	'expires_in' => HA_SESSION_IDLE_DAYS * 86400,
));
