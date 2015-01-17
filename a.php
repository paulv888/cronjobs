<?php
require_once 'includes.php';

/*
	 Process can execute following commands
	 1) Store data to HA_Database

	 Called from
	 1) ARD-Coop

	 Expecting
	 1) Type
	 2) Fields matching the above query

 */
 
$sdata = file_get_contents("php://input");
/*$file = 'tmp1.txt';
$current = file_get_contents($file);
$current .= $sdata."\n";
file_put_contents($file, $current);*/

if (!($sdata=="")) { 					//import_event
	$rcv_message = json_decode($sdata, $assoc = TRUE);
//print_r($rcv_message);
	$message['deviceID'] = $rcv_message['Device'];
	$message['commandID'] = $rcv_message['Command'];
	$message['inout'] = $rcv_message['InOut'];
	if (array_key_exists('Value', $rcv_message)) {
		$message['data'] = $rcv_message['Value'];
	} else {
		$message['data'] = $rcv_message['Status'];
	}
	$message['typeID'] = getDeviceType($message['deviceID']);
	$extdata = (array_key_exists('ExtData', $rcv_message) ? $rcv_message['ExtData'] : $extdata = null);
	$message['extdata'] = $extdata;
	$message['message'] = $sdata;
	$message['callerID'] = $message['deviceID'];
	logEvent($message);
//print_r($message);
	if ($message['inout'] == COMMAND_IO_RECV) {
		if ($message['typeID'] == DEV_TYPE_TEMP_HUM) {
			if (array_key_exists('Value', $rcv_message)) $t = $rcv_message['Value'];
			$h = '0';
			if (array_key_exists('ExtData', $rcv_message)) {
				if (array_key_exists('T', $rcv_message['ExtData'])) $t = $rcv_message['ExtData']['T'];
				$h = (array_key_exists('H', $rcv_message['ExtData']) ? $rcv_message['ExtData']['H'] : $h = '0');
			}
			if (isset($t)) {
			 	UpdateWeatherNow($message['deviceID'], $t, $h );
				UpdateWeatherCurrent($message['deviceID'], $t, $h );
			}
		}
		UpdateStatus($message['callerID'], array ( 'deviceID' => $message['deviceID'] , 'commandID' => $message['commandID'], 'status' => $rcv_message['Status']));
		UpdateLink ($message['deviceID'], LINK_UP, $message['callerID'], $message['commandID']);
	}
}



function getDeviceType($deviceID){

	$mysql='SELECT `id`, `typeID` FROM `ha_mf_devices` WHERE `id` ="'.$deviceID.'"';
	if ($rowdevice = FetchRow($mysql)) {
		return $rowdevice['typeID'];
	}
	
	return false ;
	
}
?>
