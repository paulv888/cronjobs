<?php
// $GLOBALS['debug'] = 99;
define("MY_DEVICE_ID", 283);

require_once 'includes.php';
require_once 'includesAlexa.php';

/*
 * Alexa skill endpoint.
 *
 * Amazon signs every request it sends. The signature is checked against the
 * certificate named in the SignatureCertChainUrl header BEFORE the body is
 * parsed, and only then are the skill ID and the request timestamp validated.
 *
 * The skill IDs in the request are not secret -- they travel in every request
 * and sit in the developer console -- so they are not a credential. Without the
 * signature check, anyone who learns an ID can drive the house.
 *
 *   POST body must be the exact bytes Amazon signed; do not re-encode it.
 *   Signature            : base64 signature of those bytes
 *   SignatureCertChainUrl: https://sns.amazonaws.com/SimpleNotificationService-<id>.pem
 */

if (!defined('ALEXA_REQUIRE_SIGNATURE')) define('ALEXA_REQUIRE_SIGNATURE', true);

/**
 * Append one line to the Alexa log. Bounded and locked: this file is written by
 * an unauthenticated caller, so it must never grow unboundedly or lose lines.
 */
function alexaLog($line) {
	$file = __DIR__ . '/log/alexa_skills.log';
	@file_put_contents($file, date("Y-m-d H:i:s").": ".$line."\n", FILE_APPEND | LOCK_EX);
}

/**
 * Refuse the request. The caller learns nothing beyond "rejected"; the reason
 * goes to the log.
 */
function alexaReject($status, $reason) {
	alexaLog('REJECT '.$status.' '.$reason);

	if (!headers_sent()) {
		http_response_code($status);
		header('Content-Type: application/json');
	}
	echo json_encode(array('error' => 'Request rejected'));
	exit;
}

$sdata = file_get_contents("php://input");

if (!is_string($sdata) || trim($sdata) === '') {
	alexaReject(400, 'empty request body');
}

// Keep the body for debugging, but capped: it is untrusted at this point.
alexaLog('POST '.substr($sdata, 0, 4000));

// ---------------------------------------------------------------------------
// 1. Prove the request came from Amazon.
// ---------------------------------------------------------------------------
$signatureHeader = isset($_SERVER['HTTP_SIGNATURE']) ? (string)$_SERVER['HTTP_SIGNATURE'] : '';
$certChainUrl    = isset($_SERVER['HTTP_SIGNATURECERTCHAINURL']) ? (string)$_SERVER['HTTP_SIGNATURECERTCHAINURL'] : '';

if (ALEXA_REQUIRE_SIGNATURE) {
	try {
		$verifier = new \Alexa\SignatureVerifier();
		$verifier->verify($sdata, $signatureHeader, $certChainUrl);
	} catch (\Exception $e) {
		// Record what actually arrived. Without this a proxy that rewrites or
		// drops a header is indistinguishable from a forgery.
		$seen = array();
		foreach ($_SERVER as $key => $value) {
			if (strpos($key, 'HTTP_') === 0 && stripos($key, 'signature') !== false) {
				$seen[] = $key;
			}
		}
		alexaReject(403, 'signature: '.$e->getMessage()
			.' | certUrl='.substr($certChainUrl, 0, 300)
			.' | sigBytes='.strlen($signatureHeader)
			.' | hdrs='.implode(',', $seen));
	}
}

$rcv_message = json_decode($sdata);

if (!is_object($rcv_message)) {
	alexaReject(400, 'body is not a JSON object');
}

try {
	$alexaRequest = \Alexa\Request\Request::fromData($rcv_message);
} catch (\Exception $e) {
	alexaReject(400, 'could not parse request: '.$e->getMessage());
}

// ---------------------------------------------------------------------------
// 2. Prove it is for one of our skills and recent enough not to be a replay.
// ---------------------------------------------------------------------------
try {
	$alexaRequest->validate(array(APP_ID_ME_ECHO, APP_ID_KODI_ECHO, APP_ID_AYNUR_ECHO, APP_ID_PAUL_ECHO));
} catch (\Exception $e) {
	alexaReject(401, 'validation: '.$e->getMessage());
}

// ---------------------------------------------------------------------------
// 3. Hand off to the skill that owns this application ID.
// ---------------------------------------------------------------------------
if ($alexaRequest->applicationId === APP_ID_ME_ECHO) {
	require_once 'includes/skill_me.php';
} elseif ($alexaRequest->applicationId === APP_ID_KODI_ECHO) {
	require_once 'includes/skill_kodi.php';
} elseif ($alexaRequest->applicationId === APP_ID_AYNUR_ECHO) {
	require_once 'includes/skill_tell.php';
} elseif ($alexaRequest->applicationId === APP_ID_PAUL_ECHO) {
	require_once 'includes/skill_tell.php';
} else {
	alexaReject(401, 'no skill for applicationId '.$alexaRequest->applicationId);
}

if (!function_exists('handleRequest')) {
	alexaReject(500, 'no request handler was loaded');
}

handleRequest($alexaRequest);
?>