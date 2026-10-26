<?php
require_once 'includes.php';

// move to header
//if (isset($_POST['apikey'])) {
	// $found  = FetchRow('SELECT username FROM ha_mi_remote_users WHERE inuse = 1 and `apikey`="'.$_POST['apikey'].'"');
	// if ($found) {
		// $username = $found['username'];
	// } else {
	    // die('Not Authorized'); 
    // }
	// $apikey = $_POST['apikey'];
// } else {
	// die('Not Authorized');
// }

$params = array();
if (isset($_GET['callerID'])) {
	$params = $_GET;
} elseif (isset($_POST['callerID'])) {
	$params = $_POST;
} else {
	$json = str_replace(["\r", "\n"], '', file_get_contents('php://input'));
	$params = json_decode($json, true);
}
debug($params, 'params');

if (isset($argv)) {
	foreach ($argv as $arg) {
		$e=explode("=",$arg);
        if(count($e)==2) {
			// No clue what this was for.... W'll see what breaks how... 
			$params[$e[0]]=urldecode($e[1]);
		} else {
			if ($e[0] == "ASYNC_THREAD") {
				$params[$e[0]]=true;
				echo 'ASYNC_THREAD true'.CRLF;
			}
		}
	}
}

if (!is_array($params)) {
	$params = array();
}

// ---------------------------------------------------------------------------
// Normalise the numeric identifiers at the request boundary.
//
// Everything downstream concatenates these into SQL (processor.php,
// shared_ha.php), and commandvalue is evaluated as arithmetic, so nothing
// arriving from the caller is trusted as a string. Casting here covers every
// code path that reads them.
// ---------------------------------------------------------------------------
$numericParams = array('callerID', 'deviceID', 'commandID', 'propertyID',
	'schemeID', 'remotekeyID', 'alert_textID', 'playerID');
foreach ($numericParams as $numericKey) {
	if (isset($params[$numericKey]) && !is_array($params[$numericKey])) {
		$params[$numericKey] = (int)$params[$numericKey];
	}
}
if (isset($params['keys']) && is_array($params['keys'])) {
	foreach ($params['keys'] as $keyIndex => $keyValue) {
		if (!is_array($keyValue)) {
			$params['keys'][$keyIndex] = (int)$keyValue;
		}
	}
}

// Raw SQL (executeQuery) is an internal capability. Timers, thermostat
// maintenance and macro steps set this flag on their own arrays; a web caller
// must never be able to claim it, or it could run any SQL it likes.
// CLI is trusted here because the only CLI callers are cron and the async
// threads that timers and macros spawn.
if (php_sapi_name() !== 'cli') {
	unset($params['sql_from_template']);
}

if (isset($params['debug'])) {
	if ($params['debug'] == true) $GLOBALS['debug'] = true;
}

$current = "";
$headers = apache_request_headers();
foreach ($headers as $header => $value) {
	$current .= "$header: $value".CRLF;
}
debug($current, 'headers');
// print_r($headers);

if (!headers_sent()) {
  if(!isset($_SESSION)) 
    { 
        session_start(); 
    } 
	debug($_SESSION, 'Prev Session _SESSION');
	if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > 3600)) {
		// last request was more than 30 minutes ago
		session_unset();     // unset $_SESSION variable for the run-time 
		session_destroy();   // destroy session data in storage
	}
}
//
// Set default value for SelectedPlayer
//
if (isset($_SESSION) && array_key_exists('properties', $_SESSION) && array_key_exists('SelectedPlayer', $_SESSION['properties']) && is_numeric($_SESSION['properties']['SelectedPlayer']['value'])) {
	$params['SESSION']['properties']['SelectedPlayer'] = $_SESSION['properties']['SelectedPlayer'];
} else {
	$params['SESSION']['properties']['SelectedPlayer']['value'] = (isset($params['playerID']) ? $params['playerID'] : getCurrentPlayer());
}
$_SESSION['LAST_ACTIVITY'] = time(); // update last activity time stamp
debug($_SESSION, 'Current _SESSION');
session_write_close();
// ---------------------------------------------------------------------------
// Authorization
//
// Local callers (CLI, loopback and the LAN), the Joomla pages, LAN devices and the
// async threads spawned by timers and macros are allowed without a credential.
// Everyone else must present one of:
//
//   - the session cookie minted by auth.php, which is checked against ha_sessions
//     and is therefore revocable server side, or
//   - a Firebase ID token as `Authorization: Bearer <token>`, verified per request.
//
// The legacy `apikey` parameter is still honoured for the time being, because the
// remote UI that replaces it has not shipped yet. It goes away with that change;
// see SPEC-remote-pwa.md B2.
// ---------------------------------------------------------------------------
if (!defined('PROCESS_REQUIRE_APIKEY')) define('PROCESS_REQUIRE_APIKEY', true);

$authReason = 'no_session';
$authUser = false;

if (php_sapi_name() === 'cli' || requestIsLocal()) {
	$authUser = true;
} else {
	$sessionToken = haSessionTokenFromRequest();
	if ($sessionToken !== '') {
		$authUser = haSessionValidate($sessionToken, $authReason);
	}

	if (!$authUser) {
		$bearer = authBearerToken();
		if ($bearer !== '') {
			$bearerReason = null;
			$claims = firebaseVerifyIdToken($bearer, $bearerReason);
			$candidate = $claims === null ? false : authUserFromClaims($claims);
			if ($candidate !== false && haReauthRequired($candidate, $claims)) {
				// Authenticated before the user's revocation cut-off: token is genuine but stale.
				$candidate = false;
				$authReason = 'reauth_required';
			}
			if ($candidate !== false) {
				$authUser = $candidate;
				$authReason = null;
			} else {
				// Logged in with Firebase but not provisioned here.
				$authReason = $claims === null ? 'invalid_token' : 'not_provisioned';
			}
		}
	}

	// Transitional: the old page key still works until the new remote UI ships.
	if (!$authUser && isset($params['apikey']) && is_string($params['apikey']) && $params['apikey'] !== '') {
		$keyuser = PDOFetchRow('SELECT username FROM ha_mi_remote_users WHERE inuse = 1 and `apikey` = ?',
			array($params['apikey']));
		if (!empty($keyuser)) {
			$authUser = $keyuser;
			$authReason = null;
		} else {
			$authReason = 'not_authorized';
		}
	}
}

if (PROCESS_REQUIRE_APIKEY && !$authUser) {
	$authIP = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'cli';
	error_log('process.php: unauthorized request from '.$authIP
		.' messagetypeID='.(isset($params['messagetypeID']) ? $params['messagetypeID'] : '-')
		.' reason='.$authReason);
	http_response_code(401);
	header('Content-Type: application/json');
	echo json_encode(array('error' => 'Not Authorized', 'reason' => $authReason));
	exit;
}

if (isset($params["messagetypeID"]) && isset($params["callerID"])) {						// All have to tell where they are from.
	debug($params, 'params');

	$result = executeCommand($params);
	if (is_array($result)) {
		foreach ($result as $r) {
			if (is_array($r)) {
				if (array_key_exists('redirect', $r)) {
					header($r['redirect']);
				}
				if (array_key_exists('message', $r)) {
					echo $r['message']."\r\n";
				} 
				if (array_key_exists('error', $r)) {
					echo "KO\r\n".$r['error'];
				} else {
					echo "OK\r\n";
				}
				if (array_key_exists('message', $r)) echo $r['message'] ;
			} 
		}
	} else {
		// ob_start("ob_gzhandler");
		echo $result;
		// ob_end_flush();
	}
}
//echo get_current_user().CRLF;
?>
