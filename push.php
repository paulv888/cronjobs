<?php
// ---------------------------------------------------------------------------
// Push subscription endpoint.
//
// The browser gets an FCM token from Firebase and hands it here, authenticated by
// the session cookie. Tokens are stored against the user, not the device, because
// push is addressed to a person who may have several phones and tablets.
//
// Also handles unregistering one device, or all of them on sign-out.
// ---------------------------------------------------------------------------
require_once 'includes.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function pushRespond($status, $payload) {
	http_response_code($status);
	echo json_encode($payload);
	exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
	header('Allow: POST');
	pushRespond(405, array('ok' => false, 'reason' => 'method_not_allowed'));
}

$body = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($body)) $body = $_POST;

// Same credential rules as process.php: a session, or a bearer token. Local callers
// are allowed too, so the in-house pages can register a device without a login.
$user = false;
$reason = 'no_session';

if (php_sapi_name() === 'cli' || requestIsLocal()) {
	$user = array('id' => 0, 'user_id' => 0, 'username' => 'Local');
} else {
	$token = haSessionTokenFromRequest();
	if ($token !== '') $user = haSessionValidate($token, $reason);
	if (!$user) {
		$bearer = authBearerToken();
		if ($bearer !== '') {
			$claims = firebaseVerifyIdToken($bearer, $reason);
			if ($claims !== null) $user = authUserFromClaims($claims);
		}
	}
}

if (!$user) {
	pushRespond(401, array('ok' => false, 'reason' => $reason));
}

$action = isset($body['action']) && is_string($body['action']) ? $body['action'] : 'register';

if ($action === 'unregister_all') {
	PDOExec('DELETE FROM fcm_tokens WHERE user_id = ?', array((int)$user['user_id']));
	pushRespond(200, array('ok' => true, 'removed' => 'all'));
}

$fcmToken = isset($body['token']) && is_string($body['token']) ? trim($body['token']) : '';
$machineId = isset($body['machineId']) && is_string($body['machineId']) ? trim($body['machineId']) : '';

if ($action === 'unregister') {
	if ($machineId !== '') {
		PDOExec('DELETE FROM fcm_tokens WHERE user_id = ? AND machine_id = ?', array((int)$user['user_id'], $machineId));
	}
	pushRespond(200, array('ok' => true, 'removed' => 'one'));
}

if ($fcmToken === '' || strlen($fcmToken) > 255) {
	pushRespond(400, array('ok' => false, 'reason' => 'bad_token'));
}
if ($machineId === '' || strlen($machineId) > 36) {
	pushRespond(400, array('ok' => false, 'reason' => 'bad_machine_id'));
}

// Which ha_mf_devices row this phone is. That is what sendAlertToDevice() targets,
// so a message can be addressed to a device rather than a person.
$owner = PDOFetchRow('SELECT device_id FROM ha_mi_remote_users WHERE id = ?', array((int)$user['user_id']));
$deviceId = empty($owner['device_id']) ? null : (int)$owner['device_id'];

// fcm_tokens has no unique key on (user_id, machine_id), so update first and
// insert only when nothing matched.
//
// One token, one row. The installed app and the browser tab have separate storage,
// so the same browser can register the same FCM token under two machine ids; without
// this, that is two rows and every alert arrives twice.
PDOExec('DELETE FROM fcm_tokens WHERE fcm_token = ? AND machine_id <> ?',
	array($fcmToken, $machineId));

$existing = PDOFetchRow('SELECT id FROM fcm_tokens WHERE user_id = ? AND machine_id = ? LIMIT 1',
	array((int)$user['user_id'], $machineId));

if (!empty($existing['id'])) {
	PDOExec('UPDATE fcm_tokens SET fcm_token = ?, device_id = ?, update_date = NOW() WHERE id = ?',
		array($fcmToken, $deviceId, (int)$existing['id']));
	pushRespond(200, array('ok' => true, 'updated' => true));
}

PDOExec('INSERT INTO fcm_tokens (user_id, device_id, machine_id, fcm_token, update_date) VALUES (?, ?, ?, ?, NOW())',
	array((int)$user['user_id'], $deviceId, $machineId, $fcmToken));
pushRespond(200, array('ok' => true, 'inserted' => true));
