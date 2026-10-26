<?php
// ---------------------------------------------------------------------------
// Push through FCM HTTP v1.
//
// Sending needs a service account, so the credential lives at FIREBASE_SERVICE_ACCOUNT
// outside the docroot. Nothing here is reachable from the web on its own; push.php
// owns the one public entry point.
//
// Messages are data-only on purpose. The service worker at /ha/sw.js builds and
// shows the notification itself, so a message that also carried a `notification`
// block would be displayed twice.
// ---------------------------------------------------------------------------

function haBase64UrlEncode($data) {
	return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function fcmServiceAccountPath() {
	return defined('FIREBASE_SERVICE_ACCOUNT') ? FIREBASE_SERVICE_ACCOUNT : '';
}

// Access tokens live an hour; cache one so a burst of sends does not re-sign a JWT
// every time.
function fcmAccessToken($force = false) {
	$cacheFile = sys_get_temp_dir() . '/ha-fcm-access.json';
	$now = time();

	if (!$force && is_readable($cacheFile)) {
		$cached = json_decode((string)file_get_contents($cacheFile), true);
		if (is_array($cached) && !empty($cached['token']) && isset($cached['expires']) && $cached['expires'] > $now + 60) {
			return $cached['token'];
		}
	}

	$path = fcmServiceAccountPath();
	if ($path === '' || !is_readable($path)) {
		error_log('shared_fcm: service account missing or unreadable at ' . $path);
		return false;
	}
	$service = json_decode((string)file_get_contents($path), true);
	if (empty($service['client_email']) || empty($service['private_key'])) {
		error_log('shared_fcm: service account is missing client_email or private_key');
		return false;
	}

	$header = haBase64UrlEncode(json_encode(array('typ' => 'JWT', 'alg' => 'RS256')));
	$claims = haBase64UrlEncode(json_encode(array(
		'iss' => $service['client_email'],
		'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
		'aud' => 'https://oauth2.googleapis.com/token',
		'iat' => $now - 60,
		'exp' => $now + 3600,
	)));
	$key = openssl_pkey_get_private($service['private_key']);
	if ($key === false || !openssl_sign($header . '.' . $claims, $signature, $key, OPENSSL_ALGO_SHA256)) {
		error_log('shared_fcm: could not sign the assertion');
		return false;
	}
	$assertion = $header . '.' . $claims . '.' . haBase64UrlEncode($signature);

	$ch = curl_init('https://oauth2.googleapis.com/token');
	$options = array(
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_POST => true,
		CURLOPT_POSTFIELDS => http_build_query(array(
			'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
			'assertion' => $assertion,
		)),
		CURLOPT_TIMEOUT => 15,
		CURLOPT_CONNECTTIMEOUT => 5,
	);
	if (defined('FIREBASE_CAINFO') && FIREBASE_CAINFO !== '') $options[CURLOPT_CAINFO] = FIREBASE_CAINFO;
	curl_setopt_array($ch, $options);
	$body = curl_exec($ch);
	$status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
	$error = curl_error($ch);
	curl_close($ch);

	$response = json_decode((string)$body, true);
	if ($status !== 200 || empty($response['access_token'])) {
		error_log('shared_fcm: token exchange failed HTTP ' . $status . ' ' . ($error !== '' ? $error : substr((string)$body, 0, 200)));
		return false;
	}

	$payload = json_encode(array(
		'token' => $response['access_token'],
		'expires' => $now + (int)($response['expires_in'] ?? 3600),
	));
	$tmp = $cacheFile . '.' . getmypid();
	if (@file_put_contents($tmp, $payload) !== false) @rename($tmp, $cacheFile);

	return $response['access_token'];
}

function fcmProjectId() {
	return defined('FIREBASE_PROJECT_ID') ? FIREBASE_PROJECT_ID : '';
}

// One message to one device. Returns array(status, body). $validateOnly asks Google
// to check the message without delivering it, which is how the send path is tested
// without a phone in the loop.
function fcmSendToToken($token, $title, $body = '', $link = '/ha/remote.php', $data = array(), $validateOnly = false) {
	$access = fcmAccessToken();
	if ($access === false) return array(0, array('error' => 'no_access_token'));

	// Data-only: the worker renders it.
	$payload = array(
		'message' => array(
			'token' => $token,
			'data' => array_merge(array(
				'title' => (string)$title,
				'body' => (string)$body,
				'link' => (string)$link,
			), array_map('strval', $data)),
			'webpush' => array(
				'headers' => array('Urgency' => 'high'),
				'fcm_options' => array('link' => (string)$link),
			),
		),
	);
	if ($validateOnly) $payload['validateOnly'] = true;

	$url = 'https://fcm.googleapis.com/v1/projects/' . fcmProjectId() . '/messages:send';
	$ch = curl_init($url);
	$options = array(
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_POST => true,
		CURLOPT_POSTFIELDS => json_encode($payload),
		CURLOPT_HTTPHEADER => array(
			'Authorization: Bearer ' . $access,
			'Content-Type: application/json',
		),
		CURLOPT_TIMEOUT => 15,
		CURLOPT_CONNECTTIMEOUT => 5,
	);
	if (defined('FIREBASE_CAINFO') && FIREBASE_CAINFO !== '') $options[CURLOPT_CAINFO] = FIREBASE_CAINFO;
	curl_setopt_array($ch, $options);
	$response = curl_exec($ch);
	$status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
	curl_close($ch);

	$decoded = json_decode((string)$response, true);

	// A token that Google no longer knows about is dead weight; drop the row rather
	// than paying for it on every future send.
	if ($status === 404 || $status === 400) {
		$reason = $decoded['error']['details'][0]['errorCode'] ?? ($decoded['error']['status'] ?? '');
		if (in_array($reason, array('UNREGISTERED', 'INVALID_ARGUMENT', 'NOT_FOUND'), true)) {
			$removed = PDOExec('DELETE FROM fcm_tokens WHERE fcm_token = ?', array($token));
			if ($removed > 0) error_log('shared_fcm: removed ' . $removed . ' dead token(s) (' . $reason . ')');
		}
	}

	return array($status, $decoded === null ? array('raw' => substr((string)$response, 0, 200)) : $decoded);
}

function fcmTokensForUser($userId) {
	$rows = PDOFetchRows('SELECT fcm_token FROM fcm_tokens WHERE user_id = ? AND fcm_token <> ""', array((int)$userId));
	$tokens = array();
	foreach ((array)$rows as $row) $tokens[] = $row['fcm_token'];
	return $tokens;
}

// Send to one person, on every device they have registered.
function fcmSendToUser($userId, $title, $body = '', $link = '/ha/remote.php', $data = array()) {
	$results = array();
	foreach (fcmTokensForUser($userId) as $token) {
		$results[] = fcmSendToToken($token, $title, $body, $link, $data);
	}
	return $results;
}

// Send to everyone who has a device registered.
function fcmSendToAll($title, $body = '', $link = '/ha/remote.php', $data = array()) {
	$rows = PDOFetchRows('SELECT fcm_token FROM fcm_tokens WHERE fcm_token <> ""');
	$results = array();
	foreach ((array)$rows as $row) {
		$results[] = fcmSendToToken($row['fcm_token'], $title, $body, $link, $data);
	}
	return $results;
}
?>
