<?php
//define( 'DEBUG_PROP', TRUE );
if (!defined('DEBUG_PROP')) define( 'DEBUG_PROP', FALSE );

function updateGeneric(&$params, $propertyName) {
//
//		This is the actual runtime, while status is on,
//		Within the hour it can flip and accumulate as much as it want, but we are only getting total and logging once/hour 
//
	$feedback=Array();
	if (DEBUG_PROP) {
		echo "<PRE>Update $propertyName ";
		print_r($params);
	}
	
	$oldvalue = $params['device']['previous_properties'][$propertyName]['value'];
	$newvalue = $params['device']['properties'][$propertyName]['value'];
	
	if ($oldvalue != $newvalue) {			// Value changed
		$feedback['DeviceID'] = $params['deviceID'];
		$feedback['PropertyID'] = $params['device']['previous_properties'][$propertyName]['propertyID'];
		$feedback['Status'] = $newvalue;
	}
	
	if (DEBUG_PROP) echo "Exit Update $propertyName</PRE>";
	return $feedback;
}

function updateStatus(&$params, $propertyName) {

	$feedback=Array();
	if (DEBUG_PROP) {
		echo "<PRE>Update $propertyName ";
		print_r($params);
	}

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
			return false;
		}
	}
	
	if ($oldvalue != $newvalue && $newvalue == STATUS_OFF) {
		removeDeviceProperty(Array('deviceID' => $params['deviceID'], 'description' => 'Timer Date'));
		removeDeviceProperty(Array('deviceID' => $params['deviceID'], 'description' => 'Timer Value'));
		removeDeviceProperty(Array('deviceID' => $params['deviceID'], 'description' => 'Timer Remaining'));
	}
	
	if (DEBUG_PROP) echo "Status NewValue: ".$newvalue.CRLF;
	$feedback['DeviceID'] = $params['deviceID'];
	$feedback['PropertyID'] = $params['device']['previous_properties'][$propertyName]['propertyID'];
	$feedback[$propertyName] = $newvalue;

	
	// 
	// Handle HVAC cycles
	//
	if (array_key_exists('type', $params['device']) && $params['device']['type']['has_runtime']) {
		$mysql = 'SELECT * FROM `ha_properties_log`  WHERE deviceID='.$params['deviceID'].'
				AND propertyID='.getProperty('Runtime')['id'].' order by updatedate desc limit 1';
		if ($row = FetchRow($mysql)) {
			$startdate = date("Y-m-d H:i:s", strtotime($row['updatedate']));
			$enddate = date("Y-m-d H:i:s");
			$system = $params['device']['type']['internal_type'];	// Currently support 1 = Heating, 2 = Cooling
			UpdateStatusCycle($params['deviceID'], false, false, false, true);                // Force cycle insert
			$mysql = 'SELECT deviceID, sum( TIMESTAMPDIFF(MINUTE , start_time, end_time ) ) AS runtime
							FROM `hvac_cycles`
							WHERE deviceID ='.$params['deviceID'].' AND system ='.$system.' AND start_time >= "' .$startdate.'" AND end_time <= "' .$enddate.'"
							GROUP BY deviceID, system';
			if (DEBUG_PROP) echo $mysql;
			if ($row = FetchRow($mysql)) {
				$updateProperty = $params;
				unset($updateProperty['device']['properties']);
				$updateProperty['device']['properties']['Runtime']['value'] = $row['runtime'];
				setDevicePropertyValue($updateProperty, 'Runtime');
			}
		}
	}
	
	$params['device']['properties'][$propertyName]['value'] = $newvalue;
	if (DEBUG_PROP) echo "Exit Update Status</PRE>";
	return $feedback;
}	

function updateIsRunning(&$params, $propertyName) {
//
//		This is the actual runtime, while status is on,
//		Within the hour it can flip and accumulate as much as it want, but we are only getting total and logging once/hour 
//
	$feedback=Array();
	if (DEBUG_PROP) {
		echo "<PRE>Update $propertyName ";
		print_r($params);
	}
	
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
		updateDailyRuntime($params['deviceID']);
	}	// Force create Runtime on status change

	$feedback['DeviceID'] = $params['deviceID'];
	$feedback['PropertyID'] = $params['device']['previous_properties'][$propertyName]['propertyID'];
	$feedback['Status'] = $newvalue;
	if (DEBUG_PROP) echo "Exit Update $propertyName</PRE>";
	return $feedback;
}			

function updateLastRecordingType(&$params, $propertyName) {
//
//		This is the actual runtime, while status is on,
//		Within the hour it can flip and accumulate as much as it want, but we are only getting total and logging once/hour 
//
	$feedback=Array();
	if (DEBUG_PROP) {
		echo "<PRE>Update $propertyName ";
		print_r($params);
	}
	
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
	
	if (DEBUG_PROP) echo "Exit Update $propertyName</PRE>";
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
	*	If inUP   	-> UP
	*	if inDOWN 	-> Warning exp -> Warning
	*			 	-> Timeout exp   -> Down
	*
	*	LINK_TIMEDOUT ? go check if timed out
	*
	*   If link transition then run triggers
	* 		WARNING, consider as up, cannot go from DOWN to WARNING 
	* 		UP->WARNING->DOWN
	* 		UP->DOWN
	*		DOWN->UP
	*
	*/
	if (!array_key_exists('caller', $params)) $params['caller'] = $params;
	$params['callerID'] = (array_key_exists('callerID', $params) ? $params['callerID'] : Null);
	$params['deviceID'] = (array_key_exists('deviceID', $params) ? $params['deviceID'] : $params['callerID']);
	$params['commandID'] = (array_key_exists('commandID', $params) ? $params['commandID'] : Null);

	if (DEBUG_PROP) {
		echo "<PRE>Update $propertyName ";
		print_r($params);
	}

	$params['device']['properties'][$propertyName]['value'] = (array_key_exists($propertyName, $params['device']['properties']) ?
			$params['device']['properties'][$propertyName]['value'] : LINK_UP);
	
	$prev_prop = $params['device']['previous_properties'][$propertyName];
	$oldvalue = $prev_prop['value'];
	$newvalue = $params['device']['properties'][$propertyName]['value'];
	
	
	if (DEBUG_PROP) echo "Link NewValue: ".$newvalue.CRLF;


		// Listen for Statuses StatusOff StatusOn
		if (DEBUG_PROP) 
			echo $prev_prop['linkmonitor'].' l1: '.$prev_prop['listenfor1'].' l2: '.$prev_prop['listenfor2'].' commandID: '.$params['commandID'].CRLF;
		
		// Check current link UP for MONSTAT
		switch ($newvalue) {
		case LINK_TIMEDOUT: 		// Treat LINK_DOWN form polling and check TIMEDOUT same
		case LINK_DOWN: 
			if ($prev_prop['linkmonitor'] == "MONSTAT" && !is_null($params['commandID']) &&
				($prev_prop['listenfor1'] == $params['commandID'] || $prev_prop['listenfor2'] == $params['commandID'])) {
				if (DEBUG_PROP) echo 'Found commandID, LINK_UP'.CRLF;
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
				if (DEBUG_PROP) echo "Check for warning".CRLF;
				$temp = explode(" ", $prev_prop['link_warning']);
				$temp2 = explode(":", $temp[1]);
				$min = $temp[0]*1440 + $temp2[0]*60 + $temp2[1];
				$last = strtotime($prev_prop['updatedate']);
				$nowdt = strtotime(date("Y-m-d H:i:s"));
				if (abs($nowdt-$last) / 60 > $min) {
					if (DEBUG_PROP) echo "Warning Time expired".CRLF;
					$newvalue = LINK_WARNING;
				}
			}
			$last = strtotime($prev_prop['updatedate']);
			if ($prev_prop['link_timeout'] != Null) {
				if (DEBUG_PROP) echo "Check for down".CRLF;
				$temp = explode(" ", $prev_prop['link_timeout']);
				$temp2 = explode(":", $temp[1]);
				$min = $temp[0]*1440 + $temp2[0]*60 + $temp2[1];
				$nowdt = strtotime(date("Y-m-d H:i:s"));
			}
			if ((abs($nowdt-$last) / 60 > $min) || $prev_prop['link_timeout'] == Null) {
				if (DEBUG_PROP) echo "Timeout time expired".CRLF;
				$newvalue = LINK_DOWN;
			}
			if ($newvalue == LINK_TIMEDOUT)	{
				if (DEBUG_PROP) echo "Checked for Timeout time expired, was not, so do nothing".CRLF;
					// $feedback['DeviceID'] = $params['deviceID'];
					// $feedback['PropertyID'] = $params['device']['previous_properties'][$propertyName]['propertyID'];
					// $feedback['Status'] = $newvalue;
				if (DEBUG_PROP) echo "Exit Update $propertyName</PRE>";
				return;
			} else {
				if (DEBUG_PROP) echo "Timed out, handle transition".CRLF;
			}
		}

		if (DEBUG_PROP) echo 'Still here, current link: '.$newvalue.CRLF;

		// New link = warning then update and exit
		if ($newvalue == LINK_WARNING) {
			if (DEBUG_PROP) echo "Went to LINK_WARNING, only update link not time".CRLF;
			$params['device']['properties'][$propertyName]['value'] = $newvalue;
			PDOupsert('ha_mf_device_properties', Array('value' => $newvalue), Array('deviceID' => $prev_prop['deviceID'], 'propertyID' => $prev_prop['propertyID'] ));
			$feedback['DeviceID'] = $params['deviceID'];
			$feedback['PropertyID'] = $params['device']['previous_properties'][$propertyName]['propertyID'];
			$feedback['Status'] = $newvalue;
			if (DEBUG_PROP) echo "Exit Update $propertyName</PRE>";
			return $feedback;			// Done exit
		} 

		if ($oldvalue == LINK_WARNING) $oldvalue = LINK_UP;		// Treat warning as was up

		// Handle transitions
		if ($oldvalue != $newvalue) { 								// link changed
			if ($oldvalue == LINK_UP && $newvalue == LINK_DOWN) { 	
				if (DEBUG_PROP) echo "Down, Previous was up".CRLF;
				PDOupsert('ha_mf_device_properties', Array('value' => $newvalue), Array('deviceID' => $prev_prop['deviceID'], 'propertyID' => $prev_prop['propertyID'] ));
				logEvent($log = array('inout' => COMMAND_IO_SEND, 'callerID' => $params['callerID'], 'deviceID' => $params['deviceID'], 'commandID' => $params['commandID'], 'data' => ($newvalue == LINK_UP ? "Up" : "Down")));
			} 
			if ($oldvalue == LINK_DOWN && $newvalue == LINK_UP) { 	
				if (DEBUG_PROP) echo "Up, Previous was down; UPDATE to online and log event".CRLF;
				PDOupsert('ha_mf_device_properties', Array('value' => $newvalue, 'updatedate' => date("Y-m-d H:i:s")), Array('deviceID' => $prev_prop['deviceID'], 'propertyID' => $prev_prop['propertyID'] ));
				logEvent($log = array('inout' => COMMAND_IO_SEND, 'callerID' => $params['callerID'], 'deviceID' => $params['deviceID'], 'commandID' => $params['commandID'], 'data' => ($newvalue == LINK_UP ? "Up" : "Down")));
			}
		} else {			// link is same as prev link
			if ($newvalue == LINK_UP) { 			// Link is up, UPDATE time
				if (DEBUG_PROP) echo "Link is still up, Update time".CRLF;
					PDOupsert('ha_mf_device_properties', Array('value' => $newvalue, 'updatedate' => date("Y-m-d H:i:s")), Array('deviceID' => $prev_prop['deviceID'], 'propertyID' => $prev_prop['propertyID']));
			} else {
				if (DEBUG_PROP) echo "Link is still down, Do nothing.</br>\n";
			}
		}
		$feedback['DeviceID'] = $params['deviceID'];
		$feedback['PropertyID'] = $params['device']['previous_properties'][$propertyName]['propertyID'];
		$feedback['Status'] = $newvalue;
		$params['device']['properties'][$propertyName]['value'] = $newvalue;
		if (DEBUG_PROP) echo "Exit Update $propertyName</PRE>";
		return $feedback;			// Done exit
	} 
?>
