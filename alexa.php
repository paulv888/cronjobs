<?php
//define( 'DEBUG_ALX', TRUE );
if (!defined('DEBUG_ALX')) define( 'DEBUG_ALX', FALSE );
require_once 'includes.php';
define("MY_DEVICE_ID", 283);
define("APP_ID","amzn1.ask.skill.a2ffdb0e-1bd3-414c-bce2-0dfd5ba527b1");

if (isset($_GET["Message"])) {
	$sdata=$_GET["Message"];
} else {
	$sdata = file_get_contents("php://input");
}

$file = 'alexa.log';
$current = file_get_contents($file);
$current .= date("Y-m-d H:i:s").": ".$sdata."\n";
file_put_contents($file, $current);

if (!($sdata=="")) { 					//import_event
	$rcv_message = json_decode($sdata, TRUE);

	if ($rcv_message['session']['application']['applicationId'] != APP_ID) {
		echo '{"errorMessage": "Exception: applicationId not matching"}';
		return ;
	}

	if (DEBUG_ALX) { print_r($rcv_message);}
	$request = $rcv_message['request'];
	if (function_exists($request['intent']['name'])) {
		$text = $request['intent']['name']($request);
	} else {
		$text = "Sorry, I do not understand that";
	}
	
	$result = Array();
	$result['version'] = "1.0";
	$result['response']['outputSpeech']['type'] = "PlainText";
	$result['response']['outputSpeech']['text'] = $text;
	$result['response']['shouldEndSession'] = true;
	$result['sessionAttributes'] = Array();
	$response = json_encode($result);
	header('Content-Type: application/json; charset=utf-8');
	echo $response;
	
	// $rcv_message['code']=WINK_CODE;
	// $message['deviceID'] = setDeviceID($rcv_message);
	// $message['commandID'] = $rcv_message['Command'];
	// if (!array_key_exists('InOut', $rcv_message)) $rcv_message['InOut'] = COMMAND_IO_RECV;
	// $message['inout'] = $rcv_message['InOut'];
	// // Should read primary_status 
	// if (array_key_exists('Status', $rcv_message)) $status_key = 'Status';
	// if (array_key_exists('Locked', $rcv_message)) $status_key = 'Locked';
	
	// if (!array_key_exists($status_key, $rcv_message)) $rcv_message[$status_key] = null;
	// if (array_key_exists('Value', $rcv_message)) {
		// $message['data'] = $rcv_message['Value'];
	// } else {
		// $message['data'] = $rcv_message[$status_key];
	// }
	// $message['typeID'] = getDevice($message['deviceID'])['typeID'];
	
	// $properties = array();
	// if (array_key_exists('attributes', $rcv_message)) {
		// foreach ($rcv_message['attributes'] as $attr) {
			// $properties[str_replace('_', ' ', $attr['attributeName'])]['value'] =  ($attr['value_get'] != "" ? $attr['value_get'] : $attr['value_set']);
		// }
	// }
	// //print_r($properties);
	
	// //$message['message'] = prettyPrint($sdata);
	// $message['callerID'] = MY_DEVICE_ID;
	// $message['message'] = $sdata;
	//print_r($message);
	// if ($message['inout'] == COMMAND_IO_RECV) {
		// $error_message = (array_key_exists('errorMessage', $rcv_message) ? implode(" - ", $errorMessage) : null);
		// $device['previous_properties'] = getDeviceProperties(Array('deviceID' => $message['deviceID']));
		// $properties[$status_key]['value'] = (string)$rcv_message[$status_key];
		// $properties['Link']['value'] = LINK_UP;
		// $properties['Value']['value'] = $v;
		// $device['properties'] = $properties;
		// $message['result'] = updateDeviceProperties(array( 'callerID' => $message['callerID'], 'deviceID' => $message['deviceID'] , 'commandID' => $message['commandID'], 'device' => $device, 'message' => $error_message));
	// }
	// logEvent($message);

return;
}

function WhatsTemperatureIntent($request) {

	$responses = array ( "It is %s degrees in the %s", "At the moment it is %s degrees in the %s", "Ashk\xC4\xB1m, it is %s degrees in the %s", "%s degrees, thank you");

	$find = (array_key_exists('value', $request['intent']['slots']['Device']) ? $request['intent']['slots']['Device']['value'] : 'outside');

	$propertyName = 'Temperature';
	$devices = getDeviceProperties(array('description'=>$propertyName));

	$mysql = 'SELECT deviceID, LOWER(description) as description FROM `ha_voice_names` WHERE deviceID IS NOT NULL'; 
	$voicenames = FetchRows($mysql);

	$found = search_array_key_value($voicenames, 'description' , $find);
	if (DEBUG_ALX) { print_r($found);}
	if (!empty($found)) { 
		$deviceID = $found[0]['deviceID'];	// Handle multiple matches?
	} else {
		return sprintf("I do not know what the temperature is in the %s",  $find);
	}
	
	if (DEBUG_ALX) { print_r($devices); print_r($voicenames);}
	return sprintf($responses[rand(0,count($responses)-1)], round((float)$devices[$deviceID][$propertyName]['value'],1) + 0, $find);

}

function AnySecurityOpenIntent($request) {

	$responses_all = array ("All %s are closed.");
	$responses_some = array ("The %s is open");

	$find = (array_key_exists('value', $request['intent']['slots']['Types']) ? $request['intent']['slots']['Types']['value'] : 'doors');
	
	$propertyID = getProperty('Status', false)['id'];

	$mysql = 'SELECT typeID, LOWER(description) as description FROM `ha_voice_names` WHERE typeID IS NOT NULL'; 
	$voicenames = FetchRows($mysql);
	$found = search_array_key_value($voicenames, 'description' , $find);
	if (DEBUG_ALX) { echo "Found:" ; print_r($found);}
	if (!empty($found)) { 
		$typeID = $found[0]['typeID'];
		$mysql = 'SELECT id as deviceID, typeID FROM `ha_mf_devices` WHERE typeID IN ('.$typeID.') and inuse = 1'; // Handle multiple matches?
		$devices = FetchRowsIdDescription($mysql);
		if (DEBUG_ALX) { echo "Matching devices:" ; print_r($devices);}
		foreach ($devices as $deviceID => $value) {
			$feedback[] = getStatusLink(array('deviceID'=>$deviceID, 'propertyID'=>$propertyID));
		}
		if (DEBUG_ALX) { echo "Feedback:" ; print_r($feedback);}
	} else {
		return sprintf("I cannot find devices for %s",  $find);
	}
	
	$response = "";
	$anyopen = 0;
	foreach ($feedback as $status) {
		if ($status['Status']) {
			$anyopen = true;
			$response .= ($response != "" ? ' and ' : '').sprintf($responses_some[rand(0,count($responses_some)-1)], getDevice($status['DeviceID'])['shortdesc']); 
		}
	}
	if (!$anyopen) {
		$response = sprintf($responses_all[rand(0,count($responses_all)-1)], $find);
	} 
	
	if (DEBUG_ALX) { print_r($devices); print_r($voicenames);}
	return $response;

}


function WhatsBedtimeIntent($request) {

	$responses = array("On %s you went to bed at %s.");

	
	// Handle not getting date
	$find = (array_key_exists('value', $request['intent']['slots']['Date']) ? $request['intent']['slots']['Date']['value'] : date("Y-m-d"));
	
	// Yesterday should be day before, others should be normal?
	$fromdate = $find;
	$todate = date("Y-m-d H:i", strtotime($fromdate." +24 hour"));
	$mysql = 'SELECT alert_date FROM `ha_alerts`  WHERE (description LIKE "%goodnight%" and `alert_date` > "'.$fromdate.'" and `alert_date` < "'. $todate.'") order by alert_date desc limit 1';
	// echo $mysql;
	$response = "Insufficient data. Please specify parameters.";
	if ($row = FetchRow($mysql)) {
		$date = new DateTime($row['alert_date']);
		$response = sprintf($responses[rand(0,count($responses)-1)], $date->format('l'), $date->format('g:i'));
	}

	return $response;

}

function WhatsPlayingIntent($request) {

	$responses = array("This is %s by %s","We're listening to %s by %s", "%s by %s, thank you!");

	$deviceProperties = getDeviceProperties(array('deviceID'=>DEVICE_DEFAULT_PLAYER));
	if (DEBUG_ALX) { print_r($deviceProperties); }
	return sprintf($responses[rand(0,count($responses)-1)], $deviceProperties['Title']['value'], $deviceProperties['Artist']['value']);

}
	//	$result['currentTrack'] = array( "title" => "Current Title", "artist" => 'Alexa');

?>
