<?php
require_once 'includes.php';

define("MY_DEVICE_ID", 228);
/*
	 Process can execute following commands
	 1) Store data to HA_Database

	 Called from
	 1) Wink Hub

	 Expecting
	 1) Type
	 2) Fields matching the above query

	 // 8/9/2015 Now receiving for Axis Cam
 */
 
if (isset($_GET["Message"])) {
	$sdata=$_GET["Message"];
} else {
	$sdata = file_get_contents("php://input");
}

// $file = 'wink.log';
// $current = file_get_contents($file);
// $current .= date("Y-m-d H:i:s").": ".$sdata."\n";
// file_put_contents($file, $current);


if (!($sdata=="")) { 					//import_event
	$v = Null;
	$h = Null;
	$s = Null;
	$rcv_message = json_decode($sdata, $assoc = TRUE);
//print_r($rcv_message);
	$rcv_message['code']=WINK_CODE;
	$message['deviceID'] = setDeviceID($rcv_message);
	$message['commandID'] = $rcv_message['Command'];
	if (!array_key_exists('InOut', $rcv_message)) $rcv_message['InOut'] = COMMAND_IO_RECV;
	$message['inout'] = $rcv_message['InOut'];
	// Should read primary_status 
	if (array_key_exists('Status', $rcv_message)) $status_key = 'Status';
	if (array_key_exists('Locked', $rcv_message)) $status_key = 'Locked';
	
	if (!array_key_exists($status_key, $rcv_message)) $rcv_message[$status_key] = null;
	if (array_key_exists('Value', $rcv_message)) {
		$message['data'] = $rcv_message['Value'];
	} else {
		$message['data'] = $rcv_message[$status_key];
	}
	$message['typeID'] = getDevice($message['deviceID'])['typeID'];
	
	$properties = array();
	if (array_key_exists('attributes', $rcv_message)) {
		foreach ($rcv_message['attributes'] as $attr) {
			$properties[str_replace('_', ' ', $attr['attributeName'])]['value'] =  ($attr['value_get'] != "" ? $attr['value_get'] : $attr['value_set']);
		}
	}
	//print_r($properties);
	
	//$message['message'] = prettyPrint($sdata);
	$message['callerID'] = MY_DEVICE_ID;
	$message['result'] = $sdata;
	logEvent($message);
//print_r($message);
	if ($message['inout'] == COMMAND_IO_RECV) {
		$error_message = (array_key_exists('errorMessage', $rcv_message) ? implode(" - ", $errorMessage) : null);
		$device['previous_properties'] = getDeviceProperties(Array('deviceID' => $message['deviceID']));
		$properties[$status_key]['value'] = (string)$rcv_message[$status_key];
		$properties['Link']['value'] = LINK_UP;
		$properties['Value']['value'] = $v;
		$device['properties'] = $properties;
		updateDeviceProperties(array( 'callerID' => $message['callerID'], 'deviceID' => $message['deviceID'] , 'commandID' => $message['commandID'], 'device' => $device, 'message' => $error_message));
	}
}
?>
