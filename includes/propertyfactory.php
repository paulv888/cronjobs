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
	
	
	if (array_key_exists('commandID', $params) && $params['device']['properties'][$propertyName]['value'] == STATUS_NOT_DEFINED) {
		$commandStatus =  getCommand($params['commandID'])['status'];
		if ($commandStatus !== false && $commandStatus != STATUS_NOT_DEFINED) {
			if ($params['device']['previous_properties'][$propertyName]['invertstatus'] == "0") {
				$params['device']['properties'][$propertyName]['value'] = ($commandStatus == STATUS_ON ? STATUS_OFF : STATUS_ON);
			} else {
				$params['device']['properties'][$propertyName]['value'] = $commandStatus;
			}
		} else {
			unset($params['device']['properties'][$propertyName]);
			return false;
		}
	}
	
	$oldvalue = $params['device']['previous_properties'][$propertyName]['value'];
	$newvalue = $params['device']['properties'][$propertyName]['value'];


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
		$feedback['deviceID'] = $params['deviceID'];
		$feedback['propertyID'] = $params['device']['previous_properties'][$propertyName]['propertyID'];
	} else {
		$params['device']['properties'][$propertyName]['value'] = $oldvalue;
	}
	
	if (DEBUG_PROP) echo "Exit Update $propertyName</PRE>";
	return $feedback;
}

function updateLink($params) {
 	if (!array_key_exists('caller', $params)) $params['caller'] = $params;
	$params['callerID'] = (array_key_exists('callerID', $params) ? $params['callerID'] : Null);
	$params['deviceID'] = (array_key_exists('deviceID', $params) ? $params['deviceID'] : $params['callerID']);
	$params['commandID'] = (array_key_exists('commandID', $params) ? $params['commandID'] : Null);
	$params['link'] = (array_key_exists('link', $params) ? $params['link'] : LINK_UP);

    if (DEBUG_PROP) echo CRLF.CRLF."Update Link params ".CRLF;
    if (DEBUG_PROP) print_r($params);
	

	$mysql = "SELECT * FROM `ha_mf_monitor_link` WHERE deviceID = ".$params['deviceID']; 
	if ($row = FetchRow($mysql)) {
		$prevlink = $row['link'];
		// Listen for Statuses StatusOff StatusOn
		if (DEBUG_PROP) echo $row['linkmonitor'].' l1: '.$row['listenfor1'].' l2: '.$row['listenfor2'].' l3: '.$row['listenfor3'].' commandID: '.$params['commandID'].CRLF;
		
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

		// Check current link UP for MONSTAT
		switch ($params['link']) {
		case LINK_TIMEDOUT: 		// Treat LINK_DOWN form polling and check TIMEDOUT same
		case LINK_DOWN: 
			if ($row['linkmonitor'] == "MONSTAT" && !is_null($params['commandID']) && ($row['listenfor1'] == $params['commandID'] || $row['listenfor2'] == $params['commandID'] || $row['listenfor3'] == $params['commandID'])) {
				if (DEBUG_PROP) echo 'Found commandID, LINK_UP'.CRLF;
				$params['link'] = LINK_UP;
			}
			break;
		//case LINK_WARNING:  SHOULD NOT COME IN
		//case LINK_UP: up is up
		}

		// Determine if timed out (WARNING or DOWN time expired
		if ($params['link'] == LINK_DOWN)	$params['link'] = LINK_TIMEDOUT;
		if ($params['link'] == LINK_TIMEDOUT) {
			if ($row['link_warning'] != Null) {
				if (DEBUG_PROP) echo "CHECK FOR WARNING".CRLF;
				$temp = explode(" ", $row['link_warning']);
				$temp2 = explode(":", $temp[1]);
				$min = $temp[0]*1440 + $temp2[0]*60 + $temp2[1];
				$last = strtotime($row['mdate']);
				$nowdt = strtotime(date("Y-m-d H:i:s"));
				if (abs($nowdt-$last) / 60 > $min) {
					if (DEBUG_PROP) echo "Warning Time expired".CRLF;
					$params['link'] = LINK_WARNING;
				}
			}
			$last = strtotime($row['mdate']);
			if ($row['link_timeout'] != Null) {
				if (DEBUG_PROP) echo "CHECK FOR DOWN".CRLF;
				$temp = explode(" ", $row['link_timeout']);
				$temp2 = explode(":", $temp[1]);
				$min = $temp[0]*1440 + $temp2[0]*60 + $temp2[1];
				$nowdt = strtotime(date("Y-m-d H:i:s"));
			}
			if ((abs($nowdt-$last) / 60 > $min) || $row['link_timeout'] == Null) {
				if (DEBUG_PROP) echo "Timeout Time expired".CRLF;
				$params['link'] = LINK_DOWN;
			}
			if ($params['link'] == LINK_TIMEDOUT)	{
				if (DEBUG_PROP) echo "Checked for timeout, was not, so do nothing".CRLF;
				return true;
			} else {
				if (DEBUG_PROP) echo "Timeout out, handle transition".CRLF;
			}
		}

		if (DEBUG_PROP) echo 'Still here, current link: '.$params['link'].CRLF;

		// New link = warning then update and exit
		if ($params['link'] == LINK_WARNING) {
			if (DEBUG_PROP) echo "Went to LINK_WARNING, only update link not time".CRLF;
			$mysql = "UPDATE `ha_mf_monitor_link` SET " .
					  "`link` = '" . LINK_WARNING . "'" .
					  " WHERE(`deviceID` ='" . $params['deviceID'] . "')";
			if (!mysql_query($mysql)) mySqlError($mysql);
			$params['device']['properties']['Link']['value'] = LINK_WARNING;
			setDevicePropertyValue($params, 'Link');
			return true;			// Done exit
		}

		if ($prevlink == LINK_WARNING) $prevlink = LINK_UP;		// Treat warning as was up

		// Handle transitions
		if ($prevlink != $params['link']) { 								// link changed
			if ($prevlink == LINK_UP && $params['link'] == LINK_DOWN) { 	
					if (DEBUG_PROP) echo "Down, Previous was up".CRLF;
					logEvent($log = array('inout' => COMMAND_IO_SEND, 'callerID' => $params['callerID'], 'deviceID' => $params['deviceID'], 'commandID' => $params['commandID'], 'data' => ($params['link'] == LINK_UP ? "Up" : "Down")));
					$mysql = "UPDATE `ha_mf_monitor_link` SET " .
							  " `link` = '" . $params['link'] . "'" .
							  " WHERE(`deviceID` ='" . $params['deviceID'] . "')";
					if (!mysql_query($mysql)) mySqlError($mysql);
					$params['device']['properties']['Link']['value'] = $params['link'];
					setDevicePropertyValue($params, 'Link');
					$result = HandleTriggers($params, '225', TRIGGER_AFTER_CHANGE);
					if (!empty($result)) print_r ($result);
					$result = HandleTriggers($params, '225', TRIGGER_AFTER_OFF);
					if (!empty($result)) print_r ($result);
				} 
			if ($prevlink == LINK_DOWN && $params['link'] == LINK_UP) { 	
				if (DEBUG_PROP) echo "Up, Previous was down; UPDATE to online and log event".CRLF;
				logEvent($log = array('inout' => COMMAND_IO_SEND, 'callerID' => $params['callerID'], 'deviceID' => $params['deviceID'], 'commandID' => $params['commandID'], 'data' => ($params['link'] == LINK_UP ? "Up" : "Down")));
				$mysql = "UPDATE `ha_mf_monitor_link` SET " .
						  " `mdate` = '" . date("Y-m-d H:i:s") . "'," .
						  " `link` = '" . $params['link'] . "'" .
						  " WHERE(`deviceID` ='" . $params['deviceID'] . "')";
				if (!mysql_query($mysql)) mySqlError($mysql);
				$params['device']['properties']['Link']['value'] = $params['link'];
				setDevicePropertyValue($params, 'Link');
				$result =  HandleTriggers($params, '225', TRIGGER_AFTER_CHANGE);
				if (!empty($result)) print_r ($result);
				$result = HandleTriggers($params, '225', TRIGGER_AFTER_ON);
				if (!empty($result)) print_r ($result);
			}
		} else {			// link is same as prev link
			if ($params['link'] == LINK_UP) { 			// Link is up, UPDATE time
				if (DEBUG_PROP) echo "Up and same as prev link UPDATE".CRLF;
				$mysql = "UPDATE `ha_mf_monitor_link` SET " .
					  " `link` = '" . $params['link'] . "'," .
					  " `mdate` = '" . date("Y-m-d H:i:s") . "'" .
					  " WHERE(`deviceID` ='" . $params['deviceID'] . "')";
				if (!mysql_query($mysql)) mySqlError($mysql);
				$params['device']['properties']['Link']['value'] = $params['link'];
				setDevicePropertyValue($params, 'Link');
			} else {
				if (DEBUG_PROP) echo "Down and same as prev link, Do nothing.</br>\n";
			}
		}
		return true;
	} else {
		return false;
	}
}
?>
