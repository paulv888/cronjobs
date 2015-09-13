#!/usr/bin/php
<?php

//echo dirname(__FILE__);

  include '../db.php';
  include '../defines.php';


$run = 10;
	$devices = Array();

	while($run > 0) {

		$db = get_db();
                $db->busyTimeout(5000);

		$query = 'SELECT m.deviceid AS unit, m.username AS deviceName, d.nodeId, d.genericType  FROM masterDevice AS m
				LEFT JOIN zwaveDevice AS d ON d.masterId = m.deviceId';

		$devices_result = $db->query($query);

        	// Read All deviced
		while($device = $devices_result->fetchArray(SQLITE3_ASSOC)) {
			if (!array_key_exists($device['unit'],$devices)) {
				$devices[$device['unit']] = $device;
			}

		}

//print_r($devices);

		foreach ($devices as $key => $device) {

			$query = 'SELECT a.attributeId, a.description as attributeName, s.value_get, s.value_set
			        FROM zwaveDeviceState AS s
				LEFT JOIN zwaveAttribute AS a ON s.attributeId = a.attributeID
			        WHERE s.nodeId = '.$device['nodeId'].';';

			$attr_result = $db->query($query);
                	$attrs = array();

                	while($attr = $attr_result->fetchArray(SQLITE3_ASSOC)) {
				$id = $attr['attributeId'];
				$attrs[$id] = $attr;
			}

			$reportstatus=false;
			if (array_key_exists('attributes',$device)) {
				foreach ($attrs as $attr) {
					if (($attr['value_get'] != $device['attributes'][$attr['attributeId']]['value_get']) || 
					    ($attr['value_set'] != $device['attributes'][$attr['attributeId']]['value_set'])) {
						$reportstatus=true;
					}
				}
			}
	                $devices[$key]['attributes'] = $attrs;
			if ($reportstatus) report($devices[$key]);
		} 
                $db->close();
//$run--;
        	sleep(3);
	}

function report($device) {
	print_r($device);
	unset($device['nodeId']);
	$type = $device['genericType'];
	unset($device['genericType']);

	switch ($type)
	{
	case 64:
		if ($device['attributes'][10]['value_get'] != $device['attributes'][10]['value_set']) {
			 $device['Status'] = STATUS_UNKNOWN;
		} elseif ($device['attributes'][10]['value_get'] == "TRUE") {
			 $device['Status'] = STATUS_ON;
		} else {
			 $device['Status'] = STATUS_OFF;
		}
		$device['Command'] = COMMAND_SET_RESULT;
		break;
	}
	$data = $device;
	$data_string = json_encode($data);                                                                                   
                                                                                                                     
	$ch = curl_init('http://vlosite/wink.php');                                                                      
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");                                                                     
	curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);                                                                  
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);                                                                      
	curl_setopt($ch, CURLOPT_HTTPHEADER, array(                                                                          
    		'Content-Type: application/json',                                                                                
		'Content-Length: ' . strlen($data_string))                                                                       
	);                                                                                                                   
                                                                                                                     
	$result = curl_exec($ch);
}
?>

