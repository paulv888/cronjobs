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
	$message['typeID'] = getDevice($message['deviceID'])['typeID'];
	$extdata = (array_key_exists('ExtData', $rcv_message) ? $rcv_message['ExtData'] : $extdata = null);
	$message['extdata'] = $extdata;
	$message['message'] = $sdata;
	$message['callerID'] = MY_DEVICE_ID;
	logEvent($message);
//print_r($message);
	$properties = array();
	if ($message['inout'] == COMMAND_IO_RECV) {
		if ($message['commandID'] == COMMAND_PING || $message['commandID'] == COMMAND_SET_RESULT) {
		if ($message['typeID'] == DEV_TYPE_ARDUINO_MODULES) {
			// Extended Data is there
			$properties['Memory']['value'] = $rcv_message['ExtData']['M'];
			$properties['Uptime']['value'] = $rcv_message['ExtData']['U'];
		}
		if ($message['typeID'] == DEV_TYPE_LIGHT_SENSOR_ANALOG) {
			// Extended Data is there
			$properties['Value']['value'] = $rcv_message['ExtData']['V'];
			$properties['Setpoint']['value'] = $rcv_message['ExtData']['S'];
			$properties['Threshold']['value'] = $rcv_message['ExtData']['T'];
		}
		if ($message['typeID'] == DEV_TYPE_AUTO_DOOR) {
			// Extended Data is there
			$properties['Power']['value'] = $rcv_message['ExtData']['P'];
			$properties['Direction']['value'] = $rcv_message['ExtData']['D'];
			$properties['Top Switch']['value'] = $rcv_message['ExtData']['T'];
			$properties['Bottom Switch']['value'] = $rcv_message['ExtData']['B'];
		}
		if ($message['typeID'] ==  DEV_TYPE_THERMOSTAT_ARD_HEAT || $message['typeID'] == DEV_TYPE_THERMOSTAT_ARD_COOL) {
			// Extended Data is there
			$properties['Temperature']['value'] = $rcv_message['ExtData']['V'];
			$properties['IsRunning']['value'] = $rcv_message['ExtData']['R'];
			$properties['Setpoint']['value'] = $rcv_message['ExtData']['S'];
			$properties['Threshold']['value'] = $rcv_message['ExtData']['T'];

			$heatStatus = false;
			$coolStatus = false;
			$fanStatus  = false;
			if ($message['typeID'] == DEV_TYPE_THERMOSTAT_ARD_HEAT) {
				$heatStatus = $properties['IsRunning']['value'] == 1;
			} else {
				$coolStatus = $properties['IsRunning']['value'] == 1;
			}
			updateStatusCycle($message['deviceID'], $heatStatus, $coolStatus, $fanStatus);
			updateDailyRuntime($message['deviceID']);
		}
		if ($message['typeID'] == DEV_TYPE_WATER_LEVEL) {
			// Extended Data is there
			$properties['Value']['value'] = $rcv_message['ExtData']['V'];
			$properties['Setpoint']['value'] = $rcv_message['ExtData']['S'];
			$properties['Threshold']['value'] = $rcv_message['ExtData']['T'];
		}
		if ($message['typeID'] == DEV_TYPE_TEMP_HUMIDITY) {
			$properties['Temperature']['value'] = $rcv_message['ExtData']['T'];
			$properties['Humidity']['value'] = $rcv_message['ExtData']['H'];
		}
		}
		$properties['Status']['value'] = $rcv_message['Status'];
		
		$device['previous_properties'] = getDeviceProperty(Array('deviceID' => $message['deviceID']));
		$device['properties'] = $properties;
		
		$error_message = (array_key_exists('ExtData', $rcv_message) ? implode(" - ", $extdata) : null);
		updateDeviceProperties(array( 'callerID' => $message['callerID'], 'deviceID' => $message['deviceID'] , 'commandID' => $message['commandID'], 'message' => $error_message, 'device' => $device));
		updateLink(array('callerID' => $message['callerID'], 'deviceID' => $message['deviceID'], 'link' => LINK_TIMEDOUT, 'commandID' => $message['commandID']));
	}
}
?>
