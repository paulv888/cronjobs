<?php
// define( 'DEBUG_HA', TRUE );
// define( 'DEBUG_PROPERTIES', TRUE );
//define( 'DEBUG_TRIGGERS', TRUE );
if (!defined('DEBUG_HA')) define( 'DEBUG_HA', FALSE );
if (!defined('DEBUG_PROPERTIES')) define( 'DEBUG_PROPERTIES', FALSE );
if (!defined('DEBUG_TRIGGERS')) define( 'DEBUG_TRIGGERS', FALSE );


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
			$params['device']['properties']['Link']['value'] = LINK_WARNING;
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
					$params['device']['properties']['Link']['value'] = $params['link'];
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
				$params['device']['properties']['Link']['value'] = $params['link'];
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
				$params['device']['properties']['Link']['value'] = $params['link'];
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
		
function updateStatus(&$params, $propertyName) {

	$feedback=Array();
	if (DEBUG_HA) {
		echo "<PRE>Update $propertyName ";
		print_r($params);
	}
	
	if ($params['device']['previous_properties'][$propertyName]['invertstatus'] == "0") {
		if (DEBUG_HA) echo "Status Invert".CRLF;
		if ($params['commandID'] == COMMAND_ON) {
			$params['device']['properties'][$propertyName]['value'] = STATUS_OFF;
		} elseif ($params['commandID'] == COMMAND_OFF) {
			$params['device']['properties'][$propertyName]['value'] = STATUS_ON;
		}
	}

	$oldvalue = $params['device']['previous_properties'][$propertyName]['value'];
	$newvalue = $params['device']['properties'][$propertyName]['value'];

	if (DEBUG_HA) echo "Status Status2:".$newvalue.CRLF;
	$feedback['deviceID'] = $params['deviceID'];
	$feedback[$propertyName] = $newvalue;

	if (array_key_exists('type', $params['device']) && $params['device']['type']['has_runtime']) {
		$mysql = 'SELECT * FROM `ha_properties_log`  WHERE deviceID='.$params['deviceID'].'
				AND propertyID='.getProperty('Runtime')['id'].' order by updatedate desc limit 1';
		if ($row = FetchRow($mysql)) {
			$startdate = date("Y-m-d H:i:s", strtotime($row['updatedate']));
			$enddate = date("Y-m-d H:i:s");
			var_dump($startdate);
			var_dump($enddate);
			$system = $params['device']['type']['internal_type'];	// Currently support 1 = Heating, 2 = Cooling
			UpdateStatusCycle($params['deviceID'], false, false, false, true);                // Force cycle insert
			$mysql = 'SELECT deviceID, sum( TIMESTAMPDIFF(MINUTE , start_time, end_time ) ) AS runtime
							FROM `hvac_cycles`
							WHERE deviceID ='.$params['deviceID'].' AND system ='.$system.' AND start_time >= "' .$startdate.'" AND end_time <= "' .$enddate.'"
							GROUP BY deviceID, system';
			if (DEBUG_HA) echo $mysql;
			if ($row = FetchRow($mysql)) {
				$updateProperty = $params;
				unset($updateProperty['device']['properties']);
				$updateProperty['device']['properties']['Runtime']['value'] = $row['runtime'];
				setDevicePropertyValue($updateProperty, 'Runtime');
			}
		}
	}
	
	if (DEBUG_HA) echo "Exit Update Status</PRE>";
	return $feedback;
}	


function updateIsRunning(&$params, $propertyName) {
//
//		This is the actual runtime, while status is on,
//		Within the hour it can flip and accumulate as much as it want, but we are only getting total and logging once/hour 
//
	$feedback=Array();
	if (DEBUG_HA) {
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
	
	$feedback['deviceID'] = $params['deviceID'];
	if (DEBUG_HA) echo "Exit Update $propertyName</PRE>";
	return $feedback;
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
		if (array_key_exists('properties', $params['device']) && array_key_exists('Status', $params['device']['properties'])) echo "status: ".$params['device']['properties']['Status']['value'].CRLF;
	}

	//
	// No status or other props are set, set the new status
	//
	if (!array_key_exists('properties', $params['device']) || !array_key_exists('Status', $params['device']['properties'])) { 
		$commandStatus =  getCommand($params['commandID'])['status'];
		if ($commandStatus !== false && $commandStatus != STATUS_NOT_DEFINED) {
			if ($params['device']['previous_properties']['Status']['invertstatus'] == "0") {
				$params['device']['properties']['Status']['value'] = ($commandStatus == STATUS_ON ? STATUS_OFF : STATUS_ON);
			} else {
				$params['device']['properties']['Status']['value'] = $commandStatus;
			}
		} 
	}
	
	if (array_key_exists('properties', $params['device'])) {		// Do we have props to update?
		$params['device']['properties'] = sortArrayByArray($params['device']['properties'], Array('Status'));
		foreach ($params['device']['properties'] as $key=>$property) {
			$feedback[] = setDevicePropertyValue($params, $key);
		}
	} 
	
	if (DEBUG_PROPERTIES) echo "Exit updateProperties </pre>";
	return $feedback;
}

function setDevicePropertyValue($params, $propertyName) {
//
// $property = Array('deviceID' => $deviceID, 'description' => 'Status', 'value' => '1');
//

	$feedback = Array();

	// Could get these from previous+properties ??
	$property = getProperty($propertyName);
	$deviceproperty['propertyID'] = $property['id'];
	$deviceproperty['deviceID'] = $params['deviceID'];
	$deviceproperty['value'] = $params['device']['properties'][$propertyName]['value'];
	$deviceproperty['updatedate'] = date("Y-m-d H:i:s");
	
	
	if (DEBUG_HA) {
		echo "<pre>setDevicePropertyValue $propertyName ";
		print_r ($deviceproperty);
	}
	
	if (is_null($deviceproperty['value'])) {
		if (DEBUG_HA) echo "</pre>";
		return 'Null Value, exit';
	}
	
	
	if (strtoupper($deviceproperty['value']) == "TRUE" || strtoupper($deviceproperty['value']) == "ON") $deviceproperty['value'] = STATUS_ON;
	if (strtoupper($deviceproperty['value']) == "FALSE" || strtoupper($deviceproperty['value']) == "OFF") $deviceproperty['value'] = STATUS_OFF;
	

	//
	// Get previous property info
	// 
	if (array_key_exists('previous_properties',$params['device']) && array_key_exists($propertyName,$params['device']['previous_properties'])) {
		$oldvalue = $params['device']['previous_properties'][$propertyName]['value'];
		$monitor = $params['device']['previous_properties'][$propertyName]['active'];
	} else {
		$oldvalue = Null;
		$monitor = false;
	}
	$deviceproperty['trend'] = setTrend($deviceproperty['value'], $oldvalue);

	//
	//	Always update properties Log (if Time > 60 or changed)
	//
	$sql = 'SELECT * FROM `ha_properties_log`  WHERE deviceID='.  $deviceproperty['deviceID'].' AND propertyID='.$deviceproperty['propertyID'].' order by updatedate desc limit 1';
	if ($row = FetchRow($sql)) {
		if (DEBUG_PROPERTIES) print_r($row);
		//
		//	Are we monitoring this property?
		//
		$params['lastlogdate'] = $row['updatedate'];
		$lastLogDate = strtotime($row['updatedate']);
		if ($monitor) {
			if (timeExpired($lastLogDate, 60) || ($oldvalue !== $deviceproperty['value'])) {
				if ($propertyName != "Link") {
					$func = 'update'.str_replace(' ','',$propertyName);
					if(!($feedback[$func] = $func($params, $propertyName))) return;
				}
			}
		} 
		PDOupsert('ha_mf_device_properties', $deviceproperty, Array('deviceID' => $deviceproperty['deviceID'], 'propertyID' => $deviceproperty['propertyID'] ));
		
		unset($deviceproperty['trend']);
		$lastLogDate = strtotime($row['updatedate']);
		$lastvalue = $row['value'];
		if (timeExpired($lastLogDate, 60) || (abs(floatval($deviceproperty['value'])-floatval($row['value'])) >= 1 )) {
				if ($property['datatype']=="BINARY" && $deviceproperty['value'] != $row['value']) {		// relog old value with current time to make nice graph
					PDOupsert('ha_properties_log', 	Array('propertyID' => $deviceproperty['propertyID'], 'deviceID' => $deviceproperty['deviceID'],	'value' => $row['value'], 
							'updatedate' => date("Y-m-d H:i:s", strtotime("-1 second"))), Array('propertyID' => $deviceproperty['propertyID'], 'deviceID' => $deviceproperty['deviceID'],	'value' => $row['value'], 
							'updatedate' => date("Y-m-d H:i:s", strtotime("-1 second"))));
				}
				PDOupsert('ha_properties_log', $deviceproperty,Array('propertyID' => $deviceproperty['propertyID'], 'deviceID' => $deviceproperty['deviceID'],	
							'updatedate' => date("Y-m-d H:i:s")));
		} else {
			if (DEBUG_HA) echo "Not Logging: ".$propertyName.CRLF;
		}
	} else {		// First one
		PDOupsert('ha_mf_device_properties', $deviceproperty, Array('deviceID' => $deviceproperty['deviceID'], 'propertyID' => $deviceproperty['propertyID'] ));
		unset($deviceproperty['trend']);
		PDOinsert('ha_properties_log', $deviceproperty);
	}
	
	
	//
	// Execute triggers
	// 
	if ($monitor && $oldvalue !== $deviceproperty['value']) {
		if ($property['datatype']=="BINARY" && $propertyName != "Link") { 		// Link still handled locally (Skip)
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

function HandleTriggers($params, $propertyID, $triggertype) {
	$mysql = 'SELECT * FROM `ha_mf_monitor_triggers` ' .
			'WHERE (`deviceID` = '. $params['deviceID']. ' AND propertyID = '.$propertyID.' AND `triggertype` = '.$triggertype.')';
	$feedback =  Null; 
	
	if (DEBUG_TRIGGERS) echo "Handle Triggers Params: ";
	if (DEBUG_TRIGGERS) print_r($params);
	
	if ($triggerrows = FetchRows($mysql)) {
		foreach ($triggerrows as $trigger) {
			if (DEBUG_TRIGGERS) echo "trigger: ";
			if (DEBUG_TRIGGERS) print_r($trigger);
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

function getDevice($deviceID){

	$mysql='SELECT * FROM `ha_mf_devices` d	WHERE id ='.$deviceID.' AND inuse= 1';
	if ($rowdevice = FetchRow($mysql)) {
		$mysql='SELECT * FROM ha_mf_device_links where id ='.$rowdevice['devicelinkID'];
		if ($rowlink = FetchRow($mysql)) {
			$rowdevice['link'] = $rowlink;
		}
		$mysql = 'SELECT * FROM `ha_mf_device_types` WHERE id = '.$rowdevice['typeID'];
		if ($rowtype = FetchRow($mysql)) {
			$rowdevice['type'] = $rowtype;
		}
		// if ($props = getDeviceProperties(Array('deviceID' => $deviceID))) {
			// $rowdevice['properties'] = $props;
		// }
// echo "<pre>getDevice ".$deviceID;
// print_r($rowdevice);
// echo "</pre>";		
		return $rowdevice ;
	}
	return false ;
}


function getDevicesWithProperties($params){
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

	$comb = Array();
	foreach ($params['properties'] as $propertyName) {
		$deviceproperty['description'] = $propertyName;
		$res = getDeviceProperties($deviceproperty);
		$comb = array_replace_recursive($comb, $res);
 // echo "<pre>comb";
 // print_r($comb);
 // echo "</pre>";
	}
	return $comb; // Format
}

function getDeviceProperties($deviceproperty){
//
//  If given Description, then lookup by description
//
/*
		If DeviceID and PropertyID given then return 1 device & 1 property
		In: Array( 'deviceID' => $deviceID, 'propertyID' || 'description')
		Out: Dev & Prop Array
			(
				[id] => 89
				[deviceID] => 170
				[propertyID] => 116
				[value] => 06:42
				[trend] => 1
				[sort] => 3000
				[updatedate] => 2015-10-04 03:00:02
				[active] => 
				[invertstatus] => 
				[toggleignore] => 
			)
 */
//	If No DeviceID => All Devices with that PropertyID
/*  
			If No PropertyID => All Properties for given DeviceID
			In: Array( 'deviceID' => $deviceID)
			Out: Dev & Prop Array
			All props for device 155 Array
			(
				[Status] => Array
					(
						[id] => 136
						[deviceID] => 155
						[propertyID] => 123
						[value] => 0
						[trend] => 2
						[sort] => 1000
						[updatedate] => 2015-10-04 18:12:15
						[active] => 1
						[invertstatus] => 1
						[toggleignore] => 15 0
						[description] => Status
					) */



	if (array_key_exists('description', $deviceproperty)) {
		$propertyName = $deviceproperty['description'];
		$deviceproperty['propertyID'] = getProperty($deviceproperty['description'])['id'];
		unset($deviceproperty['description']);  //
		//echo "Found id for $propertyName ".$deviceproperty['propertyID'].CRLF;
	}

	if (array_key_exists('deviceID', $deviceproperty) && array_key_exists('propertyID', $deviceproperty)) {		// DeviceID and PropertyID
		$result = False;
		if ($rowproperty = FetchRow(
			'SELECT dp.*, mp.active, mp.invertstatus, mp.toggleignore FROM ha_mf_device_properties dp 
			 LEFT JOIN ha_mf_monitor_property mp ON dp.propertyID = mp.propertyID AND dp.deviceID = mp.deviceID 
			 WHERE dp.deviceID = '.$deviceproperty['deviceID'].' AND dp.propertyID = '.$deviceproperty['propertyID'])) {
			if (DEBUG_PROPERTIES) {
				echo "<pre> Dev & Prop ";
				print_r($rowproperty);
				echo "</pre>";
			}
			return $rowproperty;
		}
	} elseif (array_key_exists('propertyID', $deviceproperty)) {		// Only PropertyID Used from upateTimer to get all devices with timer set 
		$result = Array();
		if ($rowproperties = FetchRows('SELECT dp.*, p.description FROM ha_mf_device_properties dp JOIN ha_mi_properties p ON dp.propertyID = p.id WHERE propertyID = '.$deviceproperty['propertyID'])) {
			foreach ($rowproperties AS $prop) {
				$result[$prop['deviceID']][$propertyName] = $prop;
			}
			if (DEBUG_PROPERTIES) {
				echo "<pre> Only Prop (All devs) ";
				print_r($result);
				echo "</pre>";
			}
		}
		return $result;
	} elseif (array_key_exists('deviceID', $deviceproperty))  {		// Only DeviceID
		$result = Array();
		if (!is_null($deviceproperty['deviceID']) && $rowproperties = FetchRows(
			'SELECT dp.*, mp.active, mp.invertstatus, mp.toggleignore, p.description FROM ha_mf_device_properties dp 
			 LEFT JOIN ha_mf_monitor_property mp ON dp.propertyID = mp.propertyID AND dp.deviceID = mp.deviceID 
			 JOIN ha_mi_properties p ON dp.propertyID = p.id 
			 WHERE dp.deviceID = '.$deviceproperty['deviceID'])) {
 			foreach ($rowproperties AS $prop) {
				$result[$prop['description']] = $prop;
			}
			if (DEBUG_PROPERTIES) {
				echo "<pre> All props for device ".$deviceproperty['deviceID']." ";
				print_r($result);
				echo "</pre>";
			}
		}
		return $result;
	}
	return false;
}

function getStatusLink($deviceID) {
	$feedback = Array();
	if (($properties  = getDeviceProperties(Array( 'deviceID' => $deviceID)))) {
		if (array_key_exists('Status', $properties) && $properties['Status']['active']==1) $feedback['Status'] = $properties['Status']['value'];
		if (array_key_exists('Link', $properties)) $feedback['Link'] = $properties['Link']['value'];
		if (array_key_exists('Timer Remaining', $properties)) $feedback['Timer Remaining'] = $properties['Timer Remaining']['value'];
		$feedback['deviceID'] = $deviceID;
	}
	return $feedback;
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
		$log['typeID'] = getDevice($log['deviceID'])['typeID'];
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

function listDeviceProperties($devices){
//
// Feed in property Array (Only used from highchart graphs
//
	//$id = getPropertyID($propertyName);
	if ($rows = FetchRows('SELECT propertyID FROM ha_mf_device_properties  WHERE deviceID IN ('.$devices.')')) {
		foreach ($rows AS $prop) {
			$result[] = $prop['propertyID'];
		}
		return $result;
	}
	return false ;
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

function getCommand($commandID) {
//
// Interpret status value based on current command, i.e. On/Off/Error
//
	$mysql = "SELECT * FROM ha_mf_commands WHERE ha_mf_commands.id =".$commandID;
	if ($rowcommand = FetchRow($mysql)) {
		// $status = $rowcommands['status'];
		// if ($status != STATUS_NOT_DEFINED) {
			// if (DEBUG_HA) echo "CommandStatus:".$status.CRLF;
			// return $status;
		return $rowcommand;
	}
	return false;
}
?>