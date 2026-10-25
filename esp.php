<?php
require_once 'includes.php';
define("MY_DEVICE_ID", 272);
/*
	 Receives one property value from an ESP8266 / ESP Easy unit.

	 Called from: ESP Easy hub (HTTP GET on the local vhost only -- the public
	              vhost denies this file).
	 Expecting:   device=<int>  param=<property name>  value=<text>

	 Everything here is caller supplied, so the identifiers are validated before
	 they are used and the stored value is length limited.
 */

$deviceID = (isset($_GET['device']) && is_string($_GET['device'])) ? trim($_GET['device']) : '';
$param    = (isset($_GET['param'])  && is_string($_GET['param']))  ? trim($_GET['param'])  : '';
$value    = (isset($_GET['value'])  && is_string($_GET['value']))  ? $_GET['value'] : '';

function espLog($line) {
	@file_put_contents(__DIR__.'/log/esp.log', date("Y-m-d H:i:s").": ".substr($line, 0, 500)."\n", FILE_APPEND | LOCK_EX);
}

function espReject($reason) {
	espLog('REJECT '.$reason);
	http_response_code(400);
	header('Content-Type: text/plain');
	echo "REJECTED\n";
	exit;
}

if ($deviceID === '' || !ctype_digit($deviceID) || (int)$deviceID === 0) {
	espReject('bad device ['.$deviceID.']');
}
if (!preg_match('/^[A-Za-z0-9 ._-]{1,64}$/', $param)) {
	espReject('bad param ['.substr($param, 0, 80).']');
}
if (strlen($value) > 255) {
	espReject('value too long ('.strlen($value).')');
}

// esp.php?device=270&param=Temperature&value=26.00
// esp.php?device=291&param=Status&value=0
espLog('Get '.json_encode($_GET));

$message['deviceID'] = (int)$deviceID;
$message['inout'] = COMMAND_IO_RECV;
$message['commandID'] = COMMAND_SET_RESULT;
$message['callerID'] = MY_DEVICE_ID;
$message['data'] = $value;
$message['message'] = json_encode($_GET);

$properties = array();
$properties[str_replace('_', ' ', $param)]['value'] = $value;
$properties['Link']['value'] = LINK_UP;

$device['previous_properties'] = getDeviceProperties(Array('deviceID' => $message['deviceID']));
$device['properties'] = $properties;

$message['result'] = updateDeviceProperties(array('callerID' => $message['callerID'], 'deviceID' => $message['deviceID'], 'commandID' => $message['commandID'], 'device' => $device));

logEvent($message);
?>
