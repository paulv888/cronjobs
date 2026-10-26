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

// The email is verified at this point, but that only proves the account exists.
// A local row must also exist and be in use: there is no self provisioning, and
// turning someone off is `inuse = 0` rather than anything in the console.
$user = PDOFetchRow('SELECT id, username FROM ha_mi_remote_users WHERE inuse = 1 AND email = ?',
	array($claims['email']));

if (empty($user)) {
	haRateLimitFail($bucket);
	error_log('auth.php: no provisioned user for ' . $claims['email'] . ' from ' . $ip);
	authRespond(403, array('ok' => false, 'reason' => 'not_provisioned',
		'message' => 'This account does not have access.'));
}

$token = haSessionCreate($user['id'], $user['username'], $claims['sub']);
haSessionCookieSend($token, time() + HA_SESSION_IDLE_DAYS * 86400);
haRateLimitClear($bucket);

error_log('auth.php: login ' . $user['username'] . ' from ' . $ip);
authRespond(200, array(
	'ok' => true,
	'username' => $user['username'],
	'expires_in' => HA_SESSION_IDLE_DAYS * 86400,
));
