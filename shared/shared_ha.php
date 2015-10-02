<?php

//define( 'DEBUG_HA', TRUE );
if (!defined('DEBUG_HA')) define( 'DEBUG_HA', FALSE );


function updateLink($params)
{
 	if (!array_key_exists('caller', $params)) $params['caller'] = $params;
	$params['callerID'] = (array_key_exists('callerID', $params) ? $params['callerID'] : Null);
	$params['deviceID'] = (array_key_exists('deviceID', $params) ? $params['deviceID'] : $params['callerID']);
	$params['commandID'] = (array_key_exists('commandID', $params) ? $params['commandID'] : Null);
	$params['link'] = (array_key_exists('link', $params) ? $params['link'] : LINK_UP);

    if (DEBUG_HA) echo CRLF.CRLF."Update Link params ".CRLF;
    if (DEBUG_HA) print_r($params);
	

	$mysql = "SELECT * FROM `ha_mf_monitor_link` WHERE deviceID = ".$params['deviceID']; 
	if ($row = FetchRow($mysql)) {
		$prevlink = $row['link'];
		// Listen for Statuses StatusOff StatusOn
		if (DEBUG_HA) echo $row['linkmonitor'].' l1: '.$row['listenfor1'].' l2: '.$row['listenfor2'].' l3: '.$row['listenfor3'].' commandID: '.$params['commandID'].CRLF;
		
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
				if (DEBUG_HA) echo 'Found commandID, LINK_UP'.CRLF;
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
				if (DEBUG_HA) echo "CHECK FOR WARNING".CRLF;
				$temp = explode(" ", $row['link_warning']);
				$temp2 = explode(":", $temp[1]);
				$min = $temp[0]*1440 + $temp2[0]*60 + $temp2[1];
				$last = strtotime($row['mdate']);
				$nowdt = strtotime(date("Y-m-d H:i:s"));
				if (abs($nowdt-$last) / 60 > $min) {
					if (DEBUG_HA) echo "Warning Time expired".CRLF;
					$params['link'] = LINK_WARNING;
				}
			}
			$last = strtotime($row['mdate']);
			if ($row['link_timeout'] != Null) {
				if (DEBUG_HA) echo "CHECK FOR DOWN".CRLF;
				$temp = explode(" ", $row['link_timeout']);
				$temp2 = explode(":", $temp[1]);
				$min = $temp[0]*1440 + $temp2[0]*60 + $temp2[1];
				$nowdt = strtotime(date("Y-m-d H:i:s"));
			}
			if ((abs($nowdt-$last) / 60 > $min) || $row['link_timeout'] == Null) {
				if (DEBUG_HA) echo "Timeout Time expired".CRLF;
				$params['link'] = LINK_DOWN;
			}
			if ($params['link'] == LINK_TIMEDOUT)	{
				if (DEBUG_HA) echo "Checked for timeout, was not, so do nothing".CRLF;
				return true;
			} else {
				if (DEBUG_HA) echo "Timeout out, handle transition".CRLF;
			}
		}

		if (DEBUG_HA) echo 'Still here, current link: '.$params['link'].CRLF;

		// New link = warning then update and exit
		if ($params['link'] == LINK_WARNING) {
			if (DEBUG_HA) echo "Went to LINK_WARNING, only update link not time".CRLF;
			$mysql = "UPDATE `ha_mf_monitor_link` SET " .
					  "`link` = '" . LINK_WARNING . "'" .
					  " WHERE(`deviceID` ='" . $params['deviceID'] . "')";
			if (!mysql_query($mysql)) mySqlError($mysql);
			$params['properties']['Link'] = LINK_WARNING;
			setDevicePropertyValue($params, 'Link');
			return true;			// Done exit
		}

		if ($prevlink == LINK_WARNING) $prevlink = LINK_UP;		// Treat warning as was up

		// Handle transitions
		if ($prevlink != $params['link']) { 								// link changed
			if ($prevlink == LINK_UP && $params['link'] == LINK_DOWN) { 	
					if (DEBUG_HA) echo "Down, Previous was up".CRLF;
					logEvent($log = array('inout' => COMMAND_IO_SEND, 'callerID' => $params['callerID'], 'deviceID' => $params['deviceID'], 'commandID' => $params['commandID'], 'data' => ($params['link'] == LINK_UP ? "Up" : "Down")));
					$mysql = "UPDATE `ha_mf_monitor_link` SET " .
							  " `link` = '" . $params['link'] . "'" .
							  " WHERE(`deviceID` ='" . $params['deviceID'] . "')";
					if (!mysql_query($mysql)) mySqlError($mysql);
					$params['properties']['Link'] = $params['link'];
					setDevicePropertyValue($params, 'Link');
					$result = HandleTriggers($params, '225', TRIGGER_AFTER_CHANGE);
					if (!empty($result)) print_r ($result);
					$result = HandleTriggers($params, '225', TRIGGER_AFTER_OFF);
					if (!empty($result)) print_r ($result);
				} 
			if ($prevlink == LINK_DOWN && $params['link'] == LINK_UP) { 	
				if (DEBUG_HA) echo "Up, Previous was down; UPDATE to online and log event".CRLF;
				logEvent($log = array('inout' => COMMAND_IO_SEND, 'callerID' => $params['callerID'], 'deviceID' => $params['deviceID'], 'commandID' => $params['commandID'], 'data' => ($params['link'] == LINK_UP ? "Up" : "Down")));
				$mysql = "UPDATE `ha_mf_monitor_link` SET " .
						  " `mdate` = '" . date("Y-m-d H:i:s") . "'," .
						  " `link` = '" . $params['link'] . "'" .
						  " WHERE(`deviceID` ='" . $params['deviceID'] . "')";
				if (!mysql_query($mysql)) mySqlError($mysql);
				
				$params['properties']['Link'] = $params['link'];
				setDevicePropertyValue($params, 'Link');
				$result =  HandleTriggers($params, '225', TRIGGER_AFTER_CHANGE);
				if (!empty($result)) print_r ($result);
				$result = HandleTriggers($params, '225', TRIGGER_AFTER_ON);
				if (!empty($result)) print_r ($result);
			}
		} else {			// link is same as prev link
			if ($params['link'] == LINK_UP) { 			// Link is up, UPDATE time
				if (DEBUG_HA) echo "Up and same as prev link UPDATE".CRLF;
				$mysql = "UPDATE `ha_mf_monitor_link` SET " .
					  " `link` = '" . $params['link'] . "'," .
					  " `mdate` = '" . date("Y-m-d H:i:s") . "'" .
					  " WHERE(`deviceID` ='" . $params['deviceID'] . "')";
				if (!mysql_query($mysql)) mySqlError($mysql);
				$params['properties']['Link'] = $params['link'];
				setDevicePropertyValue($params, 'Link');
			} else {
				if (DEBUG_HA) echo "Down and same as prev link, Do nothing.</br>\n";
			}
		}
		return true;
	} else {
		return false;
	}
}
		
function updateStatus(&$params)
{

	$feedback=Array();
	if (DEBUG_HA) echo "<PRE>Update Status ";

	if (array_key_exists('Status', $params['properties'])) {
		// Only retrieve if Monitor Type = Status Monitor
		$mysql = 'SELECT invertstatus FROM ha_mf_monitor_property WHERE active = 1 AND propertyID = 123 AND deviceID ='.$params['deviceID'];
		$oldstatus = getDevicePropertyValue(Array('deviceID' => $params['deviceID'],'description' => 'Status'));

		// *** Inverting Start **** 
		if ($row = FetchRow($mysql)) {
			if ($row['invertstatus'] == 0) {
				if (DEBUG_HA) echo "Status Invert".CRLF;
				if ($params['commandID'] == COMMAND_ON) {
					$params['properties']['Status'] = STATUS_OFF;
				} elseif ($params['commandID'] == COMMAND_OFF) {
					$params['properties']['Status'] = STATUS_ON;
				}
			}
			if (DEBUG_HA) echo "Status Status2:".$params['properties']['Status'].CRLF;
			$feedback['deviceID'] = $params['deviceID'];
			$feedback['status'] = $params['properties']['Status'];
			$params['status'] = $params['properties']['Status'];

			if ($oldstatus != $params['properties']['Status']) {
				// UPDATE before scheme to reduce race condition with logger
					// $deviceproperty['propertyID'] = getProperty('Status')['id'];
					// $deviceproperty['deviceID'] = $params['deviceID'];
					// $deviceproperty['value'] = $params['status'];
					// PDOupsert('ha_mf_device_properties', $deviceproperty, Array('deviceID' => $deviceproperty['deviceID'], 'propertyID' => $deviceproperty['propertyID'] ));
					// $deviceproperty['propertyID'] = getProperty('Status Date')['id'];
					// $deviceproperty['deviceID'] = $params['deviceID'];
					// $deviceproperty['value'] = $params['properties']['Status Date'];
					// PDOupsert('ha_mf_device_properties', $deviceproperty, Array('deviceID' => $deviceproperty['deviceID'], 'propertyID' => $deviceproperty['propertyID'] ));
				if ($params['properties']['Status'] == STATUS_OFF) {
					removeDeviceProperty(Array('deviceID' => $params['deviceID'], 'description' => 'Timer Date'));
					removeDeviceProperty(Array('deviceID' => $params['deviceID'], 'description' => 'Timer Value'));
					removeDeviceProperty(Array('deviceID' => $params['deviceID'], 'description' => 'Timer Remaining'));
				}
			} // if changed
		}
	}
	if (DEBUG_HA) echo "Exit Update Status</PRE>";
	return $feedback;
}	

function getCommandStatus($commandID) {
//
// Interpret status value based on current command, i.e. On/Off/Error
//
	$mysql = "SELECT status FROM ha_mf_commands WHERE ha_mf_commands.id =".$commandID;
	if ($rowcommands = FetchRow($mysql)) {
		$status = $rowcommands['status'];
		if ($status != STATUS_NOT_DEFINED) {
			if (DEBUG_HA) echo "CommandStatus:".$status.CRLF;
			return $status;
		}
	} 
	return false;
}

function updateDeviceProperties($params) {
	// If inverted (from process.php, coming in with negated command

 	if (!array_key_exists('caller', $params)) $params['caller'] = $params;
 	$params['deviceID'] = (array_key_exists('deviceID', $params) ? $params['deviceID'] : Null);
	$params['commandID'] = (array_key_exists('commandID', $params) ? $params['commandID'] : Null);

	$feedback = Array();
	if (DEBUG_HA) {
		echo "<PRE>updateProperties ";
		print_r($params);
		echo "commandID:".$params['commandID'].CRLF;
		echo "deviceID: ".$params['deviceID'].CRLF;
		if (array_key_exists('properties', $params) && array_key_exists('Status', $params['properties'])) echo "status: ".$params['properties']['Status'].CRLF;
	}

	if (!array_key_exists('properties', $params) || !array_key_exists('Status', $params['properties'])) { // No status or other props are set, get the new status
		$commandStatus =  getCommandStatus($params['commandID']);
		if ($commandStatus !== false) $params['properties']['Status'] = $commandStatus;
	}
	if (array_key_exists('properties', $params)) {		// Do we have props to update?
		foreach ($params['properties'] as $key=>$property) {
			$feedback[] = setDevicePropertyValue($params, $key);
		}
	}
	if (DEBUG_HA) echo "Exit updateProperties </pre>";
	return $feedback;
}

function setDevicePropertyValue($params, $description) {
//
// $property = Array('deviceID' => $deviceID, 'description' => 'Status', 'value' => '1');
//

	$feedback = Array();
	if ($description == "Status") {
		$feedback = updateStatus($params);
	}

	$property = getProperty($description);
	$deviceproperty['propertyID'] = $property['id'];
	$deviceproperty['deviceID'] = $params['deviceID'];
	$deviceproperty['value'] = $params['properties'][$description];
	$deviceproperty['updatedate'] = date("Y-m-d H:i:s");
	
	
	if (DEBUG_HA) {
		echo "<pre>setDevicePropertyValue $description ";
		print_r ($deviceproperty);
	}
	if (is_null($deviceproperty['value'])) {
		if (DEBUG_HA) echo "</pre>";
		return 'Null Value, exit';
	}
	
	
	if (strtoupper($deviceproperty['value']) == "TRUE" || strtoupper($deviceproperty['value']) == "ON") $deviceproperty['value'] = STATUS_ON;
	if (strtoupper($deviceproperty['value']) == "FALSE" || strtoupper($deviceproperty['value']) == "OFF") $deviceproperty['value'] = STATUS_OFF;
	
	//
	//	Update properties Log
	// 		Check for age and value changed
	//
	$sql = 'SELECT * FROM `ha_properties_log`  WHERE deviceID='.  $deviceproperty['deviceID'].' AND propertyID='.$deviceproperty['propertyID'].' order by updatedate desc limit 1';
	if ($row = FetchRow($sql)) {
		if (DEBUG_HA) print_r($row);
		$last = strtotime($row['updatedate']);
		$lastvalue = $row['value'];
		if (timeExpired($last, 59) || abs(floatval($deviceproperty['value'])-floatval($row['value'])) >= 1 ) {
			if ($property['datatype']=="BINARY" && $deviceproperty['value'] != $row['value']) {		// relog old value with current time to make nice graph
				PDOupsert('ha_properties_log', 	Array('propertyID' => $deviceproperty['propertyID'], 'deviceID' => $deviceproperty['deviceID'],	'value' => $row['value'], 
						'updatedate' => date("Y-m-d H:i:s", strtotime("-1 second"))), Array('propertyID' => $deviceproperty['propertyID'], 'deviceID' => $deviceproperty['deviceID'],	'value' => $row['value'], 
						'updatedate' => date("Y-m-d H:i:s", strtotime("-1 second"))));
			}
			PDOupsert('ha_properties_log', $deviceproperty,Array('propertyID' => $deviceproperty['propertyID'], 'deviceID' => $deviceproperty['deviceID'],	
						'updatedate' => date("Y-m-d H:i:s")));
		} else {
			if (DEBUG_HA) echo "Not Logging: ".$description.CRLF;
		}
	} else {		// First one
		PDOinsert('ha_properties_log', $deviceproperty);
	}
	$deviceproperty['trend'] = setTrend($deviceproperty['value'], ($oldvalue = getDevicePropertyByID($deviceproperty)['value']));
	if ($oldvalue != $deviceproperty['value']) {
		PDOupsert('ha_mf_device_properties', $deviceproperty, Array('deviceID' => $deviceproperty['deviceID'], 'propertyID' => $deviceproperty['propertyID'] ));

		// Run on change on only binary
		if ($property['datatype']=="BINARY" && $description != "Link") { 		// Link still handled locally (Skip)
			$result = HandleTriggers($params, $property['id'], TRIGGER_AFTER_CHANGE);
			if (!empty($result)) $feedback['Triggers'] = $result;
			if ($deviceproperty['value'] == STATUS_ON ) {
				$result = HandleTriggers($params, $property['id'], TRIGGER_AFTER_ON);
				if (!empty($result)) $feedback['Triggers'] = $result;
			} elseif ($deviceproperty['value'] == STATUS_OFF ) {
				$result = HandleTriggers($params, $property['id'], TRIGGER_AFTER_OFF);
				if (!empty($result)) $feedback['Triggers'] = $result;
			} elseif ($deviceproperty['value'] == STATUS_ERROR ){
				$result = HandleTriggers($params, $property['id'], TRIGGER_AFTER_ERROR);
				if (!empty($result)) $feedback['Triggers'] = $result;
			}
		}
	}
	
	if (DEBUG_HA) echo "</pre>";
	
	return $feedback;
}

function getStatusLink($params){
// Move this to getDeviceProperties 

	$deviceID = (array_key_exists('deviceID', $params) ? $params['deviceID'] : Null);
	$feedback['deviceID'] = $deviceID;

	// Only retrieve if Monitor Type = Status Monitor
	
	$mysql = 'SELECT p.value as status FROM ha_mf_monitor_property m ' .
		' LEFT JOIN ha_mf_device_properties p ON m.deviceID = p.deviceID AND m.propertyID = p.propertyID' .
		' WHERE m.deviceID ='.$deviceID.
		' AND active = 1' ;
	if ($rowmonitor=FetchRow($mysql)) {
		$feedback['status'] = $rowmonitor['status'];
	}

	$mysql = 'SELECT p.value as link FROM ha_mf_monitor_link m' .
		' LEFT JOIN ha_mf_device_properties p ON m.deviceID = p.deviceID AND m.propertyID = p.propertyID' .
		' WHERE m.deviceID ='.$deviceID.
		' AND active = 1' ;
	if ($rowmonitor = FetchRow($mysql)) {
		$feedback['link'] = $rowmonitor['link'];
	}
	return $feedback;
}	

function updateStatusLog($params) {		// Remove this one... staging

//$deviceID, $status, $commandvalue = 0, $humidity = NULL, $setpoint = NULL) {

	$typeIDArr = getDeviceType($params['deviceID']);
	
	if (DEBUG_HA) {
		echo "<pre>UpdateStatusLog ";
		print_r ($params);
		print_r ($typeIDArr);
	}
	
 	if (array_key_exists('properties', $params)) {		
		$properties = $params['properties'];
		if (DEBUG_HA) print_r($properties);
		unset($startdate);
		foreach($properties as $key => $value) {
			$p = Array();
			$p['propertyID'] = getPropertyID($key)['id'];
			$p['value'] = $value;
			$p['deviceID'] = $params['deviceID'];
			$description =  $key;
			$properties[$key] = $p;
			
			if (strtoupper($properties[$key]['value']) == "TRUE" || strtoupper($properties[$key]['value']) == "ON") $properties[$key]['value'] = STATUS_ON;
			if (strtoupper($properties[$key]['value']) == "FALSE" || strtoupper($properties[$key]['value']) == "OFF") $properties[$key]['value'] = STATUS_OFF;
			
			// Check for age and value changed
			$sql = 'SELECT * FROM `ha_properties_log`  WHERE deviceID='.  $params['deviceID'].' AND propertyID='.$properties[$key]['propertyID'].' order by updatedate desc limit 1';
			if ($row = FetchRow($sql)) {
				if (DEBUG_HA) print_r($row);
				$last = strtotime($row['updatedate']);
				if (timeExpired($last, 59) || abs(floatval($properties[$key]['value'])-floatval($row['value'])) >= 1 ) {
				// echo "<pre>";
				// print_r($properties[$key]);
					PDOinsert('ha_properties_log', $properties[$key]);
					if ($typeIDArr['has_runtime'] && ($description == "IsRunning" || $description == "Status")) $startdate= strtotime($row['updatedate']);
				} else {
					if (DEBUG_HA) echo "Not Logging: ".$description.CRLF;
				}
			} else {		// First one
				PDOinsert('ha_properties_log', $properties[$key]);
			}
				// print_r($properties[$key]);
				// echo "</pre>";
			$properties[$key]['trend'] = setTrend($properties[$key]['value'], getDevicePropertyByID(Array('deviceID' => $params['deviceID'], 'propertyID' => $properties[$key]['propertyID']))['value']);
			//PDOupsert('ha_mf_device_properties', $properties[$key], Array('deviceID' => $params['deviceID'], 'propertyID' => $properties[$key]['propertyID'] ));
	}
		
		// This does not belong here
		$deviceproperty = Array();
		if (isset($startdate)) {	
				$startdate = date("Y-m-d H:i:s", $startdate);
				$enddate = date("Y-m-d H:i:s");
				// var_dump($startdate);
				// var_dump($enddate);
				$system = $typeIDArr['internal_type'];	// Currently support 1 = Heating, 2 = Cooling
				UpdateStatusCycle($params['deviceID'], false, false, false, true);                // Force cycle insert
				$mysql = 'SELECT deviceID, sum( TIMESTAMPDIFF(MINUTE , start_time, end_time ) ) AS runtime
								FROM `hvac_cycles`
								WHERE deviceID ='.$params['deviceID'].' AND system ='.$system.' AND start_time >= "' .$startdate.'" AND end_time <= "' .$enddate.'"
								GROUP BY deviceID, system';
				if (DEBUG_HA) echo $mysql;
			
				if ($row = FetchRow($mysql)) {
					$deviceproperty['deviceID']= $params['deviceID'];
					$deviceproperty['propertyID'] = getdeviceproperty("Runtime")['id'];
					$deviceproperty['value'] = $row['runtime'];
					if (DEBUG_HA) print_r($deviceproperty);
					PDOinsert('ha_properties_log', $deviceproperty);
					setDevicedevicepropertyValueByID($deviceproperty);
					//PDOupsert('ha_mf_device_properties', $deviceproperty, Array('deviceID' => $params['deviceID'], 'propertyID' => $deviceproperty['propertyID'] ));
				}
		}
		
		if (DEBUG_HA) echo "</pre>";
	}
}

function HandleTriggers($params, $propertyID, $triggertype) {
	$mysql = 'SELECT * FROM `ha_mf_monitor_triggers` ' .
			'WHERE (`deviceID` = '. $params['deviceID']. ' AND propertyID = '.$propertyID.' AND `triggertype` = '.$triggertype.')';
	$feedback =  Null; 
	
	if (DEBUG_HA) echo "Handle Triggers Params: ";
	if (DEBUG_HA) print_r($params);
	
	if ($triggerrows = FetchRows($mysql)) {
		foreach ($triggerrows as $trigger) {
			if (DEBUG_HA) echo "trigger: ";
			if (DEBUG_HA) print_r($trigger);
			$thiscommand['commandID'] = COMMAND_RUN_SCHEME;
			$thiscommand['schemeID'] = $trigger['schemeID'];
			$thiscommand['loglevel'] = LOGLEVEL_MACRO;
			$thiscommand['messagetypeID'] = MESS_TYPE_SCHEME;
			$thiscommand['caller'] = $params['caller'];
			$result = sendCommand($thiscommand); 
			$feedback['Trigger:'.$trigger['id']] = $result;
			logEvent($log = array('inout' => COMMAND_IO_BOTH, 'callerID' => $params['caller']['callerID'], 'deviceID' => $params['deviceID'], 'commandID' => COMMAND_RUN_SCHEME, 'data' => getSchemeName($trigger['schemeID']), 'message' => $result ));
		}
	}
	return $feedback;
}

function updateThermType($deviceID, $typeID){

	$mysql = "UPDATE `ha_mf_devices` SET " .
    			  " `typeID` = " . $typeID . "" .
				  " WHERE(`id` ='" . $deviceID . "')";
	if (!mysql_query($mysql)) mySqlError($mysql);
	return true;
}

function getGroup($groupID){

	$mysql = 'SELECT g.groupID as groupID, d.id as deviceID, typeID, inuse FROM ha_mf_device_group g 
					JOIN `ha_mf_devices` d ON g.deviceID = d.id 
					WHERE groupID = '.$groupID; 
	return FetchRows($mysql);
}

function setTrend($new, $old) {
	if ( $new == $old ) return 0;
	if ( $new > $old )  return 1;
	if ( $new < $old )  return 2;
	return 0;
}

function getDeviceType($deviceID){

	$mysql='SELECT b.* FROM `ha_mf_devices` a JOIN `ha_mf_device_types` b ON a.typeID = b.id  WHERE a.id ="'.$deviceID.'"';
	if ($rowdevice = FetchRow($mysql)) {
		return $rowdevice;
	}
	return false ;
	
}

function getDeviceProperties($params){
// List of properties
//
//	$devs = getDeviceProperties(Array( 'properties' => Array("Timer Date", "Timer Value", "Timer Remaining")));
//
// OUT: combArray
// (
    // [60] => Array
        // (
            // [Timer Date] => 2015-09-29 17:57:43
            // [Timer Value] => 
            // [Timer Remaining] => 
        // )

 	if (array_key_exists('deviceID', $params)) {		// DeviceID given (only one)
		echo "******Not implemented";
	} else {
		$comb = Array();
		foreach ($params['properties'] as $description) {
			$deviceproperty['description'] = $description;
			$res = getDevicePropertyValue($deviceproperty);
			$comb = array_replace_recursive($comb, $res);
// echo "<pre>comb";
// print_r($comb);
// echo "</pre>";
		}
		return $comb; // Format
	}
}


function getDevicePropertyValue($deviceproperty){
//
//	Expect propertyID, description  
//
//	If DeviceID and PropertyID given then return 1 device & 1 deviceproperty
//	If No DeviceID => All Devices with that PropertyID
//  ****If No description => All Properties for given DeviceID
//

	$description = $deviceproperty['description'];
	$deviceproperty['propertyID'] = getProperty($deviceproperty['description'])['id'];
	if (array_key_exists('deviceID', $deviceproperty) && array_key_exists('propertyID', $deviceproperty)) {		// DeviceID and PropertyID
		$result = False;
		if ($rowproperty = FetchRow('SELECT value FROM ha_mf_device_properties  WHERE deviceID = '.$deviceproperty['deviceID'].' AND propertyID = '.$deviceproperty['propertyID'])) {
			return $rowproperty['value'];
		}
	} elseif (array_key_exists('propertyID', $deviceproperty)) {		// Only PropertyID Used from upateTimer to get all devices with timer set 
		$result = Array();
		if ($rowproperties = FetchRows('SELECT deviceID, propertyID, value FROM ha_mf_device_properties  WHERE propertyID = '.$deviceproperty['propertyID'])) {
			foreach ($rowproperties AS $prop) {
				$result[$prop['deviceID']][$description] = $prop['value'];
			}
 // echo "<pre>";
 // print_r($result);
 // echo "<pre>";
		}
		return $result;
	} else {
		return false;
	}
	return false;
}

function listDeviceProperties($devices){
//
// Feed in property Array?
// Make generic to retrieve values and combine
//
	//$id = getPropertyID($description);
	if ($rows = FetchRows('SELECT propertyID FROM ha_mf_device_properties  WHERE deviceID IN ('.$devices.')')) {
		foreach ($rows AS $prop) {
			$result[] = $prop['propertyID'];
		}
		return $result;
	}
	return false ;
}

function getDevicePropertyByID($deviceproperty){
//
//	If DeviceID and PropertyID given then return 1 device & 1 property
//	***If No DeviceID => All Devices with that PropertyID
//  ***If No PropertyID => All Properties for given DeviceID
//

 	if (array_key_exists('deviceID', $deviceproperty)) {		// DeviceID given (only one)
		if ($rowproperty = FetchRow('SELECT value FROM ha_mf_device_properties  WHERE deviceID = '.$deviceproperty['deviceID'].' AND propertyID = '.$deviceproperty['propertyID'])) {
			return $rowproperty;
		}
	} 
	// else {
		// if ($rowproperties = FetchRows('SELECT deviceID, propertyID, value FROM ha_mf_device_properties  WHERE propertyID = '.$property['propertyID'])) {
			// return $rowproperties;   // [0] device = 123, property = 456, value = 1
		// }
	// }
	return false ;
}

function removeDeviceProperty($deviceproperty) {
//
//	Need DeviceID and description
//
	$propertyID = getProperty($deviceproperty['description'])['id'];
	unset($deviceproperty['description']);
	$deviceproperty['propertyID'] = $propertyID;
	removeDevicePropertyByID($deviceproperty);
	return true ;
}

function removeDevicePropertyByID($deviceproperty){
//
//	Need DeviceID and PropertyID 
//
	$mysql = 'DELETE FROM `ha_mf_device_properties` WHERE(`deviceID` ='.$deviceproperty['deviceID'].' AND `propertyID`='.$deviceproperty['propertyID'].');';
	if (!mysql_query($mysql)) mySqlError($mysql);

	return true ;
}

function getProperty($key_description){
//
//	In:  Description or ID
//	Out: Property 
//	Create if not found and $descriptiongiven
//

	if (is_numeric($key_description)) { 
		$mysql='SELECT * FROM `ha_mi_properties` WHERE `id`='.(int)$key_description;
		$descriptiongiven=false;
	} else {
		$mysql='SELECT * FROM `ha_mi_properties` WHERE UCASE(description) ="'.strtoupper($key_description).'"';
		$descriptiongiven=true;
	}
	if ($rowproperty = FetchRow($mysql)) {
		return $rowproperty;
	} elseif ($descriptiongiven) {		// Create
		$id = PDOinsert('ha_mi_properties', Array('description' => $key_description));
		$mysql='SELECT * FROM `ha_mi_properties` WHERE `id`='.$id;;
		return FetchRow($mysql);
	}
	return false ;
}

function setDeviceID(&$log){


	$deviceID = null;
	$mysql='SELECT `id`, `typeID` FROM `ha_mf_devices` WHERE `code` ="'.$log['code'].'" AND `unit` ="'.$log['unit'].'"';
	if ($rowdevice = FetchRow($mysql)) {
		$log['deviceID'] = $rowdevice['id'];
		$log['typeID'] = $rowdevice['typeID'];
		$deviceID = $rowdevice['id'];
	}
	unset($log['code']);
	unset($log['unit']);
	
	return $deviceID ;
	
}

function logEvent($log) {

//	$log['ip']=(!empty($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : NULL);
	if (isset($_SERVER['HTTP_CLIENT_IP']) && $_SERVER['HTTP_CLIENT_IP'] != '')
        	$log['ip'] = $_SERVER['HTTP_CLIENT_IP'];
    	elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && $_SERVER['HTTP_X_FORWARDED_FOR'] != '')
        	$log['ip'] = $_SERVER['HTTP_X_FORWARDED_FOR'];
	elseif (isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] != '')
        	$log['ip'] = $_SERVER['REMOTE_ADDR'];
 	if (!array_key_exists("deviceID", $log)) $log['deviceID'] = Null;
	if (!array_key_exists("commandID", $log)) $log['commandID'] = COMMAND_UNKNOWN;
	if (!array_key_exists("inout", $log)) $log['inout'] = COMMAND_IO_NOT;
	if (!array_key_exists("callerID", $log)) $log['callerID'] = Null;
	if (!array_key_exists("repeatcount", $log)) $log['repeatcount'] = 1;
	if (!array_key_exists("data", $log)) $log['data'] = Null;
	if (!array_key_exists("extdata", $log)) $log['extdata'] = Null;
	if (!array_key_exists("loglevel", $log)) $log['loglevel'] = Null;
	if ($log['loglevel'] == LOGLEVEL_NONE) return true;
	
	$repeatcount=1;
	//
	//	Get device type and monitorid
	//
	$log['typeID'] = NULL;
	if ($log['deviceID'] != Null) {
		$log['typeID'] = getDeviceType($log['deviceID'])['id'];
		$mysql = "SELECT invertstatus FROM `ha_mf_monitor_property` " .
					" WHERE propertyID = 123 AND deviceID = ".$log['deviceID']; 
		if (!$resdevice=mysql_query($mysql)) {
			mySqlError($mysql); 
			return false;
		}
		$rowdevice=FetchRow($mysql);
		if ($rowdevice['invertstatus'] == "0") {
			$log['extdata'] = "Inverted ".$log['extdata']; 
			if ($log['commandID'] == COMMAND_OFF) {
				$log['commandID'] = COMMAND_ON;
			} elseif ($log['commandID'] == COMMAND_ON) {
				$log['commandID'] = COMMAND_OFF;
			}
		}
	}
	
	$log['mdate'] = date("Y-m-d H:i:s");

	if (is_null($log['loglevel']))	{
		if (!is_null($log['commandID'])) {
			$mysql='SELECT `loglevel` FROM `ha_mf_commands` WHERE `id` ='.$log['commandID'];
			if (!$rescommand=mysql_query($mysql)) {
				mySqlError($mysql);
			} else {
				$rowcommand=mysql_fetch_array($rescommand);
				$log['loglevel'] = $rowcommand['loglevel'];
			}
		}
	}
	if (is_null($log['loglevel'])) $log['loglevel'] = LOGLEVEL_COMMAND;
	
	if (DEBUG_HA) echo "***log";
	if (DEBUG_HA) print_r($log);
		
	PDOinsert("ha_events", $log);
}

function getSchemeName($schemaID) {
        $schemarow = FetchRow("SELECT name FROM ha_remote_schemes WHERE id = ".$schemaID);
        return $schemarow['name'];
}
?>
