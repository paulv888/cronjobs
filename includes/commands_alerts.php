<?php
// ---------------------------------------------------------------------------
// Alerts to a phone.
//
// Same shape as sendBullet(): it takes a `$params` array carrying `mess_subject`
// and `mess_text`, understands the same little bit of markup (line breaks, an
// <a href> or an <img>), and answers with the same `$feedback` structure. The only
// difference is the destination: instead of a Pushbullet channel it addresses one
// device — the `deviceID` from `$params`, which is a `ha_mf_devices` row of type
// Mobile.
//
// The mapping is: ha_mf_devices (the phone) -> ha_mi_remote_users.device_id (whose
// phone it is) -> fcm_tokens.device_id (the tokens that phone registered).
// `fcm_tokens.device_id` is filled at registration by push.php.
// ---------------------------------------------------------------------------

// ha_mf_device_types row for Mobile. A subscriber of this type cannot render a
// message itself, so it gets a push instead of a COMMAND_SEND_MESSAGE.
if (!defined('HA_DEVICE_TYPE_MOBILE')) define('HA_DEVICE_TYPE_MOBILE', 23);

// Pull the pieces out of the same message shape sendBullet() accepts.
function alertParseMessage($text) {
	$text = (string)$text;
	$text = str_replace(array('<br/>', '<br>', '<br />'), "\n", $text);

	$link = '';
	$image = '';

	if (stripos($text, '<img') !== false) {
		if (preg_match('/<img[^>]+src\s*=\s*["\']([^"\']+)["\']/i', $text, $m)) {
			$image = $m[1];
		}
	}
	// A bare image URL in the text counts too: not everything that produces a
	// message bothers to wrap it in an <img>.
	if ($image === '' && preg_match('#https?://[^\s"\'<>]+\.(?:jpe?g|png|gif|webp)(?:\?[^\s"\'<>]*)?#i', $text, $m)) {
		$image = $m[0];
	}
	if (preg_match('/<a[^>]+href\s*=\s*["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', $text, $m)) {
		$link = $m[1];
	}

	$body = trim(html_entity_decode(strip_tags($text), ENT_QUOTES, 'UTF-8'));

	return array('body' => $body, 'link' => $link, 'image' => $image);
}

// Turn an alert_text template into notification text: fill in what placeholders we
// can, and drop the ones we cannot rather than showing "{caller___device___error}".
function alertResolveText($template, $params) {
	$text = (string)$template;
	if ($text === '') return '';
	if (strpos($text, '{') !== false && is_array($params)) {
		// The same engine the rest of the app uses for macro and alert text: it fills
		// {value_parts___N}, {caller___...}, {now}, {last___result___...} and
		// {calculate___...}. Without it, alert 57's whole message is placeholders.
		$text = replaceCommandPlaceholders($text, $params);
		// Whatever is left may still be a device property lookup.
		$text = replacePropertyPlaceholders($text, $params);
		$text = preg_replace('/\{[a-z0-9_]*\}/i', '', $text);
	}
	$text = preg_replace('/[ \t]+/', ' ', $text);
	return trim($text);
}

// A notification's image has to be an absolute, publicly reachable URL. Anything
// relative - a lastimage path, a filesystem path from a camera result - renders as
// no image at all, silently.
function alertPublicImageUrl($path) {
	$path = trim((string)$path);
	if ($path === '') return '';
	if (preg_match('#^https?://#i', $path)) return $path;

	$base = defined('PUBLIC_BASE_URL') ? rtrim(PUBLIC_BASE_URL, '/') : '';
	$localDir = defined('LOCAL_LASTIMAGEDIR') ? rtrim(LOCAL_LASTIMAGEDIR, '/') : '';
	$publicDir = defined('PUBLIC_LASTIMAGEDIR') ? rtrim(PUBLIC_LASTIMAGEDIR, '/') : '';

	// A filesystem path under the lastimage directory maps onto its public twin.
	if ($localDir !== '' && strpos($path, $localDir) === 0) {
		return $base . $publicDir . substr($path, strlen($localDir));
	}

	// Anything else that is already rooted at the site.
	if ($path[0] === '/') return $base . $path;

	// A bare filename, which is what a camera result usually carries.
	if (preg_match('/^[^\/]+\.(jpe?g|png|gif|webp)$/i', $path)) {
		return $base . $publicDir . '/' . $path;
	}

	return $path;   // relative to nothing we know; leave it alone
}

// Every token registered for one ha_mf_devices row.
function haDeviceTokens($deviceID) {	$deviceID = (int)$deviceID;
	if ($deviceID <= 0) return array();

	$rows = PDOFetchRows('SELECT fcm_token FROM fcm_tokens
	                       WHERE device_id = ? AND fcm_token <> ""', array($deviceID));
	$tokens = array();
	// PDOFetchRows answers false when there is no row, and (array)false is [false],
	// so the shape has to be checked rather than cast.
	if (is_array($rows)) {
		foreach ($rows as $row) {
			if (!empty($row['fcm_token'])) $tokens[] = $row['fcm_token'];
		}
	}

	// Fall back to the user whose phone this is, for tokens that were registered
	// before the device link existed.
	if (empty($tokens)) {
		$user = PDOFetchRow('SELECT id FROM ha_mi_remote_users WHERE device_id = ? AND inuse = 1', array($deviceID));
		if (!empty($user) && !empty($user['id'])) $tokens = fcmTokensForUser((int)$user['id']);
	}

	return $tokens;
}

// Send one message to one phone. Returns the same $feedback shape as the other
// command functions, so it can be dropped into a command handler unchanged.
function sendAlertToDevice($params) {
	$feedback = array('Name' => 'sendAlertToDevice', 'result' => array(), 'commandstr' => '');

	$deviceID = isset($params['deviceID']) ? (int)$params['deviceID'] : 0;
	if ($deviceID <= 0) {
		$feedback['error'] = 'No deviceID given.';
		return $feedback;
	}

	$subject = isset($params['mess_subject']) ? (string)$params['mess_subject'] : '';
	$subject = trim(html_entity_decode(strip_tags(str_replace(array('<br/>', '<br>'), ' ', $subject)), ENT_QUOTES, 'UTF-8'));

	$parsed = alertParseMessage(isset($params['mess_text']) ? $params['mess_text'] : '');
	$body = $parsed['body'];

	// An explicit link in $params wins over one scraped out of the text.
	$link = !empty($params['link']) ? (string)$params['link'] : $parsed['link'];
	if ($link === '') $link = '/ha/remote.php';

	$image = !empty($params['image']) ? alertPublicImageUrl($params['image']) : alertPublicImageUrl($parsed['image']);

	if ($subject === '' && $body === '') {
		$feedback['error'] = 'Nothing to send: mess_subject and mess_text were both empty.';
		return $feedback;
	}
	if ($subject === '') $subject = 'Home';

	$data = array();
	if ($image !== '') $data['image'] = $image;
	if (!empty($params['alert_catalogID'])) $data['kind'] = (string)$params['alert_catalogID'];

	$tokens = haDeviceTokens($deviceID);
	if (empty($tokens)) {
		$feedback['error'] = 'Device ' . $deviceID . ' has no registered phone.';
		return $feedback;
	}

	foreach ($tokens as $token) {
		if (!is_string($token) || $token === '') continue;
		list($status, $response) = fcmSendToToken($token, $subject, $body, $link, $data, !empty($params['validateOnly']));
		$ok = ($status === 200);
		$feedback['result'][] = array(
			'deviceID' => $deviceID,
			'token' => substr($token, 0, 8) . '…',
			'sent' => $ok,
			'status' => $status,
			'name' => $ok ? ($response['name'] ?? '') : '',
			'error' => $ok ? '' : ($response['error']['message'] ?? 'unknown'),
		);
	}

	return $feedback;
}

// The same message to every phone that has one registered. Handy for a broadcast
// without listing devices.
function sendAlertToAllDevices($params) {
	$feedback = array('Name' => 'sendAlertToAllDevices', 'result' => array(), 'commandstr' => '');

	$rows = PDOFetchRows('SELECT DISTINCT device_id FROM fcm_tokens
	                       WHERE device_id IS NOT NULL AND fcm_token <> ""');
	if (empty($rows)) {
		$feedback['error'] = 'No phones are registered.';
		return $feedback;
	}

	foreach ($rows as $row) {
		$one = $params;
		$one['deviceID'] = (int)$row['device_id'];
		$result = sendAlertToDevice($one);
		$feedback['result'][$row['device_id']] = $result['result'];
		if (!empty($result['error'])) $feedback['error'] = $result['error'];
	}

	return $feedback;
}
?>
