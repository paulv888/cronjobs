<?php
require_once 'includes.php';

define("MY_DEVICE_ID", 214);
/*
	 Process can execute following commands
	 1) Store data to HA_Database

	 Called from
	 1) ARD-Coop

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

// $file = 'arduino.log';
// $current = file_get_contents($file);
// $current .= $sdata."\n";
// file_put_contents($file, $current);


if (!($sdata=="")) { 					//import_event
	$rcv_message = json_decode($sdata, $assoc = TRUE);
//print_r($rcv_message);
	$message['deviceID'] = $rcv_message['Device'];
	$message['commandID'] = $rcv_message['Command'];
	if (!array_key_exists('InOut', $rcv_message)) $rcv_message['InOut'] = 1;
	$message['inout'] = $rcv_message['InOut'];
	if (!array_key_exists('Status', $rcv_message)) $rcv_message['Status'] = null;
	if (array_key_exists('Value', $rcv_message)) {
		$message['data'] = $rcv_message['Value'];
	} else {
		$message['data'] = $rcv_message['Status'];
	}
	$devType = getDeviceType($message['deviceID']);
	$message['typeID'] = $devType['id'];
	$extdata = (array_key_exists('ExtData', $rcv_message) ? $rcv_message['ExtData'] : $extdata = null);
	$message['extdata'] = $extdata;
	$message['message'] = $sdata;
	$message['callerID'] = MY_DEVICE_ID;
	logEvent($message);
//print_r($message);
	$properties = array();
	if ($message['inout'] == COMMAND_IO_RECV) {
		if ($message['typeID'] == DEV_TYPE_ARDUINO_MODULES) {
			// Extended Data is there
			$properties['Memory'] = $rcv_message['ExtData']['M'];
			$properties['Uptime'] = $rcv_message['ExtData']['U'];
			$properties['Value'] = $rcv_message['ExtData']['U'];
		}
		if ($message['typeID'] == DEV_TYPE_LIGHT_SENSOR_ANALOG) {
			// Extended Data is there
			$properties['Value'] = $rcv_message['ExtData']['V'];
			$properties['Setpoint'] = $rcv_message['ExtData']['S'];
			$properties['Threshold'] = $rcv_message['ExtData']['T'];
		}
		if ($message['typeID'] == DEV_TYPE_AUTO_DOOR) {
			// Extended Data is there
			$properties['Power'] = $rcv_message['ExtData']['P'];
			$properties['Direction'] = $rcv_message['ExtData']['D'];
			$properties['Top Switch'] = $rcv_message['ExtData']['T'];
			$properties['Bottom Switch'] = $rcv_message['ExtData']['B'];
		}
		if ($message['typeID'] ==  DEV_TYPE_THERMOSTAT_ARD_HEAT || $message['typeID'] == DEV_TYPE_THERMOSTAT_ARD_COOL) {
			// Extended Data is there
			$properties['Temperature'] = $rcv_message['ExtData']['V'];
			$properties['IsRunning'] = $rcv_message['ExtData']['R'];
			$properties['Setpoint'] = $rcv_message['ExtData']['S'];
			$properties['Threshold'] = $rcv_message['ExtData']['T'];

			$heatStatus = false;
			$coolStatus = false;
			$fanStatus  = false;
			if ($message['typeID'] == DEV_TYPE_THERMOSTAT_ARD_HEAT) {
				$heatStatus = $properties['IsRunning'] == 1;
			} else {
				$coolStatus = $properties['IsRunning'] == 1;
			}
			UpdateStatusCycle($message['deviceID'], $heatStatus, $coolStatus, $fanStatus);
			UpdateDailyRuntime($message['deviceID']);
		}
		if ($message['typeID'] == DEV_TYPE_WATER_LEVEL) {
			// Extended Data is there
			$properties['Value'] = $rcv_message['ExtData']['V'];
			$properties['Setpoint'] = $rcv_message['ExtData']['S'];
			$properties['Threshold'] = $rcv_message['ExtData']['T'];
		}
		if ($message['typeID'] == DEV_TYPE_TEMP_HUMIDITY) {
			$properties['Temperature'] = $rcv_message['ExtData']['T'];
			$properties['Humidity'] = $rcv_message['ExtData']['H'];
		}
		$error_message = (array_key_exists('ExtData', $rcv_message) ? implode(" - ", $extdata) : null);
		UpdateStatus(array( 'callerID' => $message['callerID'], 'deviceID' => $message['deviceID'] , 'commandID' => $message['commandID'], 'status' => $rcv_message['Status'], 'message' => $error_message, 'properties' => $properties));
		UpdateLink(array('callerID' => $message['callerID'], 'deviceID' => $message['deviceID'], 'link' => LINK_TIMEDOUT, 'commandID' => $message['commandID']));
	}
}
?>
