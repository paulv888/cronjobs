<?php
function updateGeneric(&$params, $propertyName) {
//
//
	debug($propertyName, 'propertyName');
	debug($params, 'params');

	$feedback=Array();

	$oldvalue = $params['device']['previous_properties'][$propertyName]['value'];
	$newvalue = $params['device']['properties'][$propertyName]['value'];

	// echo "dumpie";
	// var_dump($params['device']['properties'][$propertyName]['value']);
	if (array_key_exists('commandID', $params) && 
	      ($params['device']['properties'][$propertyName]['value'] == "" ||
				is_null($params['device']['properties'][$propertyName]['value']))) {
		$commandStatus =  getCommand($params['commandID'])['status'];
		// echo var_dump($commandStatus);
		if ($commandStatus !== false && $commandStatus != STATUS_NOT_DEFINED) {
			if ($params['device']['previous_properties'][$propertyName]['invertstatus'] == "0") {
				$newvalue = ($commandStatus == STATUS_ON ? STATUS_OFF : STATUS_ON);
			} else {
				$newvalue = $commandStatus;
			}
			if ($commandStatus == STATUS_COMMAND_VALUE) {
				$newvalue = $params['commandvalue'];
			}
		} else {
			unset($params['device']['properties'][$propertyName]);
			debug("false", 'false');
			return false;
		}
	}

	if ($oldvalue != $newvalue) {			// Value changed
		$feedback['DeviceID'] = $params['deviceID'];
		$feedback['PropertyID'] = $params['device']['previous_properties'][$propertyName]['propertyID'];
		$feedback['Status'] = $newvalue;
	}

	$params['device']['properties'][$propertyName]['value'] = $newvalue;

	debug($feedback, 'feedback');
	return $feedback;
}

function updateLocked(&$params, $propertyName) {
	debug($propertyName, 'propertyName');
	debug($params, 'params');
	// $newvalue = STATUS_NOT_DEFINED;
	// Only set if we got value, else get from command status
	if (strtoupper($params['device']['properties'][$propertyName]['value']) == "LOCKED") {
		$newvalue = STATUS_ON;
		$params['device']['properties'][$propertyName]['value'] = $newvalue;
		$params['device']['properties']['Link']['value'] = LINK_UP;
	} elseif (strtoupper($params['device']['properties'][$propertyName]['value']) == "UNLOCKED") {
		$newvalue = STATUS_OFF;
		$params['device']['properties'][$propertyName]['value'] = $newvalue;
		$params['device']['properties']['Link']['value'] = LINK_UP;
	} elseif (strtoupper($params['device']['properties'][$propertyName]['value']) == "UNAVAILABLE") {
		$newvalue = STATUS_NOT_DEFINED;
		$params['device']['properties'][$propertyName]['value'] = $newvalue;
		$params['device']['properties']['Link']['value'] = LINK_DOWN;
		updateLink($params, 'Link');
	}
	return updateStatus($params, $propertyName);
}

function updateStatus(&$params, $propertyName) {
	debug($params, 'params');

	$feedback=Array();

	$oldvalue = $params['device']['previous_properties'][$propertyName]['value'];
	$newvalue = $params['device']['properties'][$propertyName]['value'];
	if (array_key_exists('commandID', $params) && $params['device']['properties'][$propertyName]['value'] == "") {
		$commandStatus =  getCommand($params['commandID'])['status'];
		// echo var_dump($commandStatus);
		if ($commandStatus !== false && $commandStatus != STATUS_NOT_DEFINED) {
			if ($params['device']['previous_properties'][$propertyName]['invertstatus'] == "0") {
				$newvalue = ($commandStatus == STATUS_ON ? STATUS_OFF : STATUS_ON);
			} else {
				$newvalue = $commandStatus;
			}
		} else {
			unset($params['device']['properties'][$propertyName]);
			debug("false", 'false');
			return false;
		}
	}

	if ($oldvalue != $newvalue && $newvalue == STATUS_OFF) {
		removeDeviceProperty(Array('deviceID' => $params['deviceID'], 'description' => 'Timer Date'));
		removeDeviceProperty(Array('deviceID' => $params['deviceID'], 'description' => 'Timer Value'));
		removeDeviceProperty(Array('deviceID' => $params['deviceID'], 'description' => 'Timer Remaining'));
	}

	$feedback['DeviceID'] = $params['deviceID'];
	$feedback['PropertyID'] = $params['device']['previous_properties'][$propertyName]['propertyID'];
	$feedback['Status'] = $newvalue;
	$feedback['Datatype'] = $params['device']['previous_properties'][$propertyName]['datatype'];

	$params['device']['properties'][$propertyName]['value'] = $newvalue;
	debug($feedback, 'feedback');
	return $feedback;
}

function updateRuntime(&$params, $propertyName) {
//
//		This is the actual runtime, while status is on,
//		We are only getting total and logging once/hour 
//
	debug($propertyName, 'propertyName');
	debug($params, 'params');

	$feedback=Array();

	$oldvalue = $params['device']['previous_properties'][$propertyName]['value'];
	$newvalue = $params['device']['properties'][$propertyName]['value'];

	if (timeExpired($params['lastUpdateDate'],60)) {
		$mysql = 'SELECT * FROM `ha_properties_log`  WHERE deviceID='.$params['deviceID'].'
				AND propertyID='.getProperty('Runtime')['id'].' order by updatedate desc limit 1';
		if ($row = FetchRow($mysql)) {
			$startdate = date("Y-m-d H:i:s", strtotime($row['updatedate']));
			$enddate = date("Y-m-d H:i:s");
			$heat_cool = $params['device']['type']['internal_type'];	// Currently support 1 = Heating, 2 = Cooling
			UpdateStatusCycle($params['deviceID'], false, false, false, true);                // Force cycle insert
			$mysql = 'SELECT deviceID, sum( TIMESTAMPDIFF(MINUTE , start_time, end_time ) ) AS runtime
							FROM `hvac_cycles`
							WHERE deviceID ='.$params['deviceID'].' AND heat_cool ='.$heat_cool.' AND start_time >= "' .$startdate.'" AND end_time <= "' .$enddate.'"
							GROUP BY deviceID, heat_cool';
			debug($mysql, 'mysql');
			if ($row = FetchRow($mysql)) {
				$newvalue = $row['runtime'];
				$feedback['DeviceID'] = $params['deviceID'];
				$feedback['PropertyID'] = $params['device']['previous_properties'][$propertyName]['propertyID'];
				$feedback['Status'] = $newvalue;
			} else {
				unset($params['device']['properties'][$propertyName]);
				debug("false", 'false');
				return false;
			}
		}
	}

	$params['device']['properties'][$propertyName]['value'] = $newvalue;
	debug($feedback, 'feedback');
	return $feedback;
}

function updateIsRunning(&$params, $propertyName) {
//
//		Is currently heating/cooling, insert new cycle on toggle
//
	debug($propertyName, 'propertyName');
	debug($params, 'params');

	$feedback=Array();

	$oldvalue = $params['device']['previous_properties'][$propertyName]['value'];
	$newvalue = $params['device']['properties'][$propertyName]['value'];

	if ($oldvalue != $newvalue) {			// Value changed
	//
	//		Insert new cycle
	//
		$heatStatus = false;
		$coolStatus = false;
		$fanStatus  = false;
		// Log generic under heat, we are only interested in runtime anyway
		if (($params['device']['type']['internal_type'] == DEV_INTERNAL_TYPE_HEAT) || ($params['device']['type']['internal_type'] == DEV_INTERNAL_TYPE_GENERIC)) { 
			$heatStatus = $newvalue == 1;
		} else {		// DEV_INTERNAL_TYPE_COOL
			$coolStatus = $newvalue == 1;
		}
		updateStatusCycle($params['deviceID'], $heatStatus, $coolStatus, $fanStatus);
		// Do not do this here, is updated directly from thermostat in getThermoSettings
		//	updateDailyRuntime($params['deviceID']);
	}	// Force create Runtime on status change

	$feedback['DeviceID'] = $params['deviceID'];
	$feedback['PropertyID'] = $params['device']['previous_properties'][$propertyName]['propertyID'];
	$feedback['Status'] = $newvalue;
	debug($feedback, 'feedback');
	return $feedback;
}

function updateLastRecordingType(&$params, $propertyName) {
//
//
//
	debug($propertyName, 'propertyName');
	debug($params, 'params');

	$feedback=Array();
	
	$oldvalue = $params['device']['previous_properties'][$propertyName]['value'];
	$newvalue = $params['device']['properties'][$propertyName]['value'];
	
	if ($newvalue < $oldvalue) {			// Higher Priority
		$params['device']['properties'][$propertyName]['value'] = $newvalue;
		$feedback['DeviceID'] = $params['deviceID'];
		$feedback['PropertyID'] = $params['device']['previous_properties'][$propertyName]['propertyID'];
		$feedback['Status'] = $newvalue;
	} else {
		$params['device']['properties'][$propertyName]['value'] = $oldvalue;
	}
	
	debug($feedback, 'feedback');
	return $feedback;
}

function updateLink(&$params,  $propertyName) {
	/*
	*
	*	3 Method INTERNAL/POLL(2)/MONSTAT
	*				-> Internal only send if ran, so auto up
	*				-> Monstat only recognize certain stat, if expected stat -> up. Else time expired?
	*				-> POLL(2) runs every minute, Either up or down, if Down check warning/Timeout
	*
	*	3 stats UP/DOWN/WARNING/ 
	*	If isUP   	-> UP
	*	if isDOWN 	-> Warning exp -> Warning
	*			 	-> Timeout exp   -> Down
	*
	*	LINK_TIMEDOUT ? go check if timed out
	*
	*   If link transition then run triggers
	* 		WARNING, consider as up, cannot go from DOWN to WARNING 
	* 		UP->WARNING->DOWN
	* 		UP->DOWN->WARNING
	* 		UP->DOWN
	*		DOWN->UP
	*
	*/
	debug($propertyName, 'propertyName');
	debug($params, 'params');

	if (!array_key_exists('caller', $params)) $params['caller'] = $params;
	$params['callerID'] = (array_key_exists('callerID', $params) ? $params['callerID'] : Null);
	$params['deviceID'] = (array_key_exists('deviceID', $params) ? $params['deviceID'] : $params['callerID']);
	$params['commandID'] = (array_key_exists('commandID', $params) ? $params['commandID'] : Null);

	$params['device']['properties'][$propertyName]['value'] = (array_key_exists($propertyName, $params['device']['properties']) ?
			$params['device']['properties'][$propertyName]['value'] : LINK_UP);
	
	$prev_prop = $params['device']['previous_properties'][$propertyName];
	$oldvalue = $prev_prop['value'];
	$newvalue = $params['device']['properties'][$propertyName]['value'];
	
	
	debug($newvalue, 'newvalue');


		// Listen for Statuses StatusOff StatusOn
		debug('Monitor: '.$prev_prop['linkmonitor'].' L1: '.$prev_prop['listenfor1'].' L2: '.$prev_prop['listenfor2'].' commandID: '.$params['commandID']);

		// Check current link UP for MONSTAT
		switch ($newvalue) {
		case LINK_TIMEDOUT: 		// Treat LINK_DOWN form polling and check TIMEDOUT same
		case LINK_DOWN: 
			if ($prev_prop['linkmonitor'] == "MONSTAT" && !is_null($params['commandID']) &&
				($prev_prop['listenfor1'] == $params['commandID'] || $prev_prop['listenfor2'] == $params['commandID'])) {
				debug("Found commandID, LINK_UP", 'Found commandID, LINK_UP');
				$newvalue = LINK_UP;
			}
			break;
		//case LINK_WARNING:  SHOULD NOT COME IN
		//case LINK_UP: up is up
		}

		// Determine if timed out (WARNING or DOWN time expired
		if ($newvalue == LINK_DOWN)	$newvalue = LINK_TIMEDOUT;
		if ($newvalue == LINK_TIMEDOUT) {
			if ($prev_prop['link_warning'] != Null) {
				debug("Check for warning", 'Check for warning');
				$temp = explode(" ", $prev_prop['link_warning']);
				$temp2 = explode(":", $temp[1]);
				$min = $temp[0]*1440 + $temp2[0]*60 + $temp2[1];
				$last = strtotime($prev_prop['updatedate']);
				$nowdt = strtotime(date("Y-m-d H:i:s"));
				if (abs($nowdt-$last) / 60 > $min) {
					debug("Warning Time expired", 'Warning Time expired');
					$newvalue = LINK_WARNING;
				}
			}
			$last = strtotime($prev_prop['updatedate']);
			if ($prev_prop['link_timeout'] != Null) {
				debug("Check for down", 'Check for down');
				$temp = explode(" ", $prev_prop['link_timeout']);
				$temp2 = explode(":", $temp[1]);
				$min = $temp[0]*1440 + $temp2[0]*60 + $temp2[1];
				$nowdt = strtotime(date("Y-m-d H:i:s"));
			}
			if ((abs($nowdt-$last) / 60 > $min) || $prev_prop['link_timeout'] == Null) {
				debug("Timeout time expired", 'Timeout time expired');
				$newvalue = LINK_DOWN;
			}
			if ($newvalue == LINK_TIMEDOUT)	{
				debug("Checked for Timeout time expired, was not, so do nothing", 'Checked for Timeout time expired, was not, so do nothing, return');
					// $feedback['DeviceID'] = $params['deviceID'];
					// $feedback['PropertyID'] = $params['device']['previous_properties'][$propertyName]['propertyID'];
					// $feedback['Status'] = $newvalue;
				return;
			} else {
				debug("Timed out, handle transition", 'Timed out, handle transition');
			}
		}

		debug($newvalue, 'newvalue');

		// New link = warning then update and exit
		if ($newvalue == LINK_WARNING) {
			debug("Went to LINK_WARNING, only update link not time", 'Went to LINK_WARNING, only update link not time');
			$params['device']['properties'][$propertyName]['value'] = $newvalue;
			PDOupsert('ha_mf_device_properties', Array('value' => $newvalue), Array('deviceID' => $prev_prop['deviceID'], 'propertyID' => $prev_prop['propertyID'] ));
			$feedback['DeviceID'] = $params['deviceID'];
			$feedback['PropertyID'] = $params['device']['previous_properties'][$propertyName]['propertyID'];
			$feedback['Status'] = $newvalue;
			debug($feedback, 'feedback');
			return $feedback;			// Done exit
		} 

		if ($oldvalue == LINK_WARNING) $oldvalue = LINK_UP;		// Treat warning as was up

		// Handle transitions
		if ($oldvalue != $newvalue) { 								// link changed
			if ($oldvalue == LINK_UP && $newvalue == LINK_DOWN) { 	
				debug("Down, Previous was up", 'Down, Previous was up');
				PDOupsert('ha_mf_device_properties', Array('value' => $newvalue), Array('deviceID' => $prev_prop['deviceID'], 'propertyID' => $prev_prop['propertyID'] ));
				logEvent($log = array('inout' => COMMAND_IO_SEND, 'callerID' => $params['callerID'], 'deviceID' => $params['deviceID'], 'commandID' => $params['commandID'], 'data' => ($newvalue == LINK_UP ? "Up" : "Down")));
			} 
			if ($oldvalue == LINK_DOWN && $newvalue == LINK_UP) { 	
				debug("Up, Previous was down; UPDATE to online and log event", 'Up, Previous was down; UPDATE to online and log event');
				PDOupsert('ha_mf_device_properties', Array('value' => $newvalue, 'updatedate' => date("Y-m-d H:i:s")), Array('deviceID' => $prev_prop['deviceID'], 'propertyID' => $prev_prop['propertyID'] ));
				logEvent($log = array('inout' => COMMAND_IO_SEND, 'callerID' => $params['callerID'], 'deviceID' => $params['deviceID'], 'commandID' => $params['commandID'], 'data' => ($newvalue == LINK_UP ? "Up" : "Down")));
			}
		} else {			// link is same as prev link
			if ($newvalue == LINK_UP) { 			// Link is up, UPDATE time
				debug("Link is still up, Update time", 'Link is still up, Update time');
				PDOupsert('ha_mf_device_properties', Array('value' => $newvalue, 'updatedate' => date("Y-m-d H:i:s")), Array('deviceID' => $prev_prop['deviceID'], 'propertyID' => $prev_prop['propertyID']));
			} else {
				debug("Link is still down, Do nothing", 'Link is still down, Do nothing');
			}
		}
		$feedback['DeviceID'] = $params['deviceID'];
		$feedback['PropertyID'] = $params['device']['previous_properties'][$propertyName]['propertyID'];
		$feedback['Status'] = $newvalue;
		$params['device']['properties'][$propertyName]['value'] = $newvalue;
		debug($feedback, 'feedback');
		return $feedback;			// Done exit
	} 


function parseDeviceResult(&$params, $last_feedback) {
//
//
//
	debug($params, 'params');
	debug($last_feedback, 'last_feedback');
	// $feedback['result'][] = array();
	$feedback['Name'] = 'parseDeviceResult';
	
	
	$properties = array();
	if ($params['commandID'] == COMMAND_GET_VALUE || 
	   ($params['command']['commandclassID'] == COMMAND_CLASS_KODI )) { // GETVALUE AND KODI GETPROPERTIES (POSITON)


		if (array_key_exists('result_raw', $last_feedback)) {
			$rcv_message = json_decode($last_feedback['result_raw'], $assoc = TRUE);
		} 
	
		if (isset($rcv_message)) {

			debug($rcv_message, 'rcv_message');

			if ($params['commandID'] == COMMAND_GET_VALUE) {  // getValue
			
				// Front Door Lock
				if ($params['device']['typeID'] == DEV_TYPE_LOCK) {
					$properties['Locked']['value'] = $rcv_message['state'];
					$properties['Link']['value'] = LINK_UP;
				}
				
				if ($params['device']['typeID'] == DEV_TYPE_ARDUINO_MODULES) {
					// Extended Data is there
					if (array_key_exists('M',$rcv_message['ExtData'])) $properties['Memory']['value'] = $rcv_message['ExtData']['M'];
					if (array_key_exists('U',$rcv_message['ExtData']))$properties['Uptime']['value'] = $rcv_message['ExtData']['U'];
					$properties['Status']['value'] = $rcv_message['Status'];
					$properties['Link']['value'] = LINK_UP;
				}
				if ($params['device']['typeID']  == DEV_TYPE_LIGHT_SENSOR_ANALOG) {
					// Extended Data is there
					if (array_key_exists('V',$rcv_message['ExtData'])) $properties['Value']['value'] = $rcv_message['ExtData']['V'];
					if (array_key_exists('S',$rcv_message['ExtData'])) $properties['Setpoint']['value'] = $rcv_message['ExtData']['S'];
					if (array_key_exists('T',$rcv_message['ExtData'])) $properties['Threshold']['value'] = $rcv_message['ExtData']['T'];
					$properties['Status']['value'] = $rcv_message['Status'];
					$properties['Link']['value'] = LINK_UP;
				}
				if ($params['device']['typeID']  == DEV_TYPE_AUTO_DOOR) {
					// Extended Data is there
					if (array_key_exists('P',$rcv_message['ExtData'])) $properties['Power']['value'] = $rcv_message['ExtData']['P'];
					if (array_key_exists('D',$rcv_message['ExtData'])) $properties['Direction']['value'] = $rcv_message['ExtData']['D'];
					if (array_key_exists('T',$rcv_message['ExtData'])) $properties['Top Switch']['value'] = $rcv_message['ExtData']['T'];
					if (array_key_exists('B',$rcv_message['ExtData'])) $properties['Bottom Switch']['value'] = $rcv_message['ExtData']['B'];
					$properties['Status']['value'] = $rcv_message['Status'];
					$properties['Link']['value'] = LINK_UP;
				}
				if ($params['device']['typeID'] ==  DEV_TYPE_THERMOSTAT_ARD_HEAT || $params['device']['typeID'] == DEV_TYPE_THERMOSTAT_ARD_COOL) {
					// Extended Data is there
					if (array_key_exists('V',$rcv_message['ExtData'])) $properties['Temperature']['value'] = $rcv_message['ExtData']['V'];
					if (array_key_exists('R',$rcv_message['ExtData'])) $properties['IsRunning']['value'] = $rcv_message['ExtData']['R'];
					if (array_key_exists('S',$rcv_message['ExtData'])) $properties['Setpoint']['value'] = $rcv_message['ExtData']['S'];
					if (array_key_exists('T',$rcv_message['ExtData'])) $properties['Threshold']['value'] = $rcv_message['ExtData']['T'];
					$properties['Status']['value'] = $rcv_message['Status'];
					$properties['Link']['value'] = LINK_UP;
				}
				if ($params['device']['typeID'] == DEV_TYPE_WATER_LEVEL) {
					// Extended Data is there
					if (array_key_exists('V',$rcv_message['ExtData'])) $properties['Value']['value'] = $rcv_message['ExtData']['V'];
					if (array_key_exists('S',$rcv_message['ExtData'])) $properties['Setpoint']['value'] = $rcv_message['ExtData']['S'];
					if (array_key_exists('T',$rcv_message['ExtData'])) $properties['Threshold']['value'] = $rcv_message['ExtData']['T'];
					$properties['Status']['value'] = $rcv_message['Status'];
					$properties['Link']['value'] = LINK_UP;
				}
				if ($params['device']['typeID'] == DEV_TYPE_TEMP_HUMIDITY) {
					if (array_key_exists('T',$rcv_message['ExtData'])) $properties['Temperature']['value'] = $rcv_message['ExtData']['T'];
					if (array_key_exists('H',$rcv_message['ExtData'])) $properties['Humidity']['value'] = $rcv_message['ExtData']['H'];
					$properties['Status']['value'] = $rcv_message['Status'];
					$properties['Link']['value'] = LINK_UP;
				}
			}
				
			if ($params['command']['commandclassID'] == COMMAND_CLASS_KODI) {   // KODI getPROPERTIES (POSITON)
				if (array_key_exists('result',$rcv_message) && is_array($rcv_message['result'])) {
					if (array_key_exists('position',$rcv_message['result'])) $properties['position']['value'] = $rcv_message['result']['position'];
					if (array_key_exists('size',$rcv_message['result'])) $properties['size']['value'] = $rcv_message['result']['size'];
				}
			}
			
			debug($properties, 'properties');

			if (isset($properties)) {
				$params['device']['properties'] = $properties;
				$feedback['properties'] = $properties;
				$error_message = (array_key_exists('ExtData', $rcv_message) ? implode(" - ", $rcv_message['ExtData'] ) : null);
			}
		}
	}

	debug($feedback, 'feedback');
	return $feedback;
}

?>