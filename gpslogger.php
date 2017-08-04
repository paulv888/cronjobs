<?php
require_once 'includes.php';
define("MY_DEVICE_ID", 302);
define("MIN_ACCURACY", 100);
/*
	 Process can execute following commands
	 1) Store data to HA_Database
	 Called from
	 1) esp8266 Hub
	 Expecting
	 1) Type
	 2) Fields matching the above query
	 // 8/9/2015 Now receiving for Axis Cam
 */


$file = 'gpslogger.log';
$current = file_get_contents($file);
if (!empty($_GET)) {
  $current .= date("Y-m-d H:i:s").":Get ".json_encode($_GET)."\n";
  $sdata = $_GET;
} else {
  $current .= date("Y-m-d H:i:s").":Post ".$sdata."\n";
  $sdata = $_POST;
}
file_put_contents($file, $current);
// esp.php?device=270&param=Temperature&value=26.00
// esp.php?device=291&param=Status&value=0

if (!($sdata=="")) { 					//import_event
	$rcv_message = $sdata;
	// print_r($rcv_message);
	$message['deviceID'] = $rcv_message['Device'];
	unset($rcv_message['Device']);
	$message['commandID'] = COMMAND_SET_RESULT;
	$message['inout'] = COMMAND_IO_RECV;
	//$message['typeID'] = getDevice($message['deviceID'])['typeID'];
	$properties = array();
	foreach ($rcv_message as $key => $value) {
		$properties[$key]['value'] = $value;
	}
	// print_r($properties);
	// $message['message'] = prettyPrint($sdata);
	$message['callerID'] = MY_DEVICE_ID;
	$message['data'] = $properties['Latitude']['value'].' / '.$properties['Longitude']['value'];
	$message['message'] = json_encode($sdata);
	// print_r($sdata);
	// print_r($message);
	if ($message['inout'] == COMMAND_IO_RECV && (int)$rcv_message['Accuracy'] > MIN_ACCURACY) {
		//$error_message = (array_key_exists('errorMessage', $sdata) ? implode(" - ", $errorMessage) : null);
		$device['previous_properties'] = getDeviceProperties(Array('deviceID' => $message['deviceID']));
		//$properties[$status_key]['value'] = (string)$sdata[$status_key];
		
		//
		// Cannot update link, will trigger someone home for my phone
		//
		//$properties['Link']['value'] = LINK_UP;
		//$properties['Value']['value'] = $v;
		$device['properties'] = $properties;
		// print_r($device);
		$message['result'] = updateDeviceProperties(array( 'callerID' => $message['callerID'], 'deviceID' => $message['deviceID'] , 'commandID' => $message['commandID'], 'device' => $device));

		$image = '/map_point.png';
		date_default_timezone_set('UTC');

		// Get prev row
		$sql = 'SELECT * FROM `geo_location`  WHERE deviceID='.$message['deviceID'].' order by updatedate desc limit 1';
		if ($row = FetchRow($sql)) {
			$thisMessageTime = strtotime($rcv_message['Time']);
			$prevmessageTime = strtotime($row['datetime']);
			$interval  = abs($thisMessageTime - $prevmessageTime);
			$minutes   = round($interval / 60);
			// echo 'Diff. in minutes is: '.$minutes; 
			if ($minutes > 60 ) { // Assume break
				PDOUpsert('geo_location', Array('image' => '/map_end.png'), Array('id' => $row['id']));
				$image = '/map_start.png';
			}
		}
		PDOinsert('geo_location', Array('deviceID' => $message['deviceID'], 'description' => $rcv_message['Description'] , 
			'datetime' => date('Y-m-d H:i:s', $thisMessageTime), 'speed' => $rcv_message['Speed'], 
			'speed' => $rcv_message['Speed'],'altitude' => $rcv_message['Altitude'], 'direction' => $rcv_message['Direction'],
			'lat_long' => '('.$rcv_message['Latitude'].','.$rcv_message['Longitude'].'):'.(int)$rcv_message['Accuracy'],
			'accuracy' => $rcv_message['Accuracy'],'image' => $image
			));
	} else {
		$message['data'] = 'Skipping: Accuracy -'.(int)$rcv_message['Accuracy'];
	}
	logEvent($message);
}
?>
