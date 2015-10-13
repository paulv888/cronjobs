<?php
//define( 'DEBUG_HA', TRUE );
// define( 'DEBUG_PROPERTIES', TRUE );
// define( 'DEBUG_TRIGGERS', TRUE );
if (!defined('DEBUG_HA')) define( 'DEBUG_HA', FALSE );
if (!defined('DEBUG_PROPERTIES')) define( 'DEBUG_PROPERTIES', FALSE );
if (!defined('DEBUG_TRIGGERS')) define( 'DEBUG_TRIGGERS', FALSE );

function updateDeviceProperties($params) {
	// If inverted (from process.php, coming in with negated command

 	if (!array_key_exists('caller', $params)) $params['caller'] = $params;
 	$params['deviceID'] = (array_key_exists('deviceID', $params) ? $params['deviceID'] : Null);

	$feedback = Array();
	if (DEBUG_HA) {
		echo "<PRE>updateProperties ";
		print_r($params);
//		echo "commandID:".$params['commandID'].CRLF;
		echo "deviceID: ".$params['deviceID'].CRLF;
		
	}

	//
	// No status or other props are set, force a status (if we have commandID)
	//
	if (array_key_exists('commandID', $params) && (!array_key_exists('properties', $params['device']) || !array_key_exists('Status', $params['device']['properties']))) {
		$params['device']['properties']['Status']['value'] = STATUS_NOT_DEFINED;
		if (DEBUG_HA) echo "status: ".$params['device']['properties']['Status']['value'].CRLF;
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
// $params, name
//

	$feedback = Array('propertyName' => $propertyName);

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
	
	if (strtoupper($deviceproperty['value']) == "TRUE" || strtoupper($deviceproperty['value']) == "ON") $deviceproperty['value'] = STATUS_ON;
	if (strtoupper($deviceproperty['value']) == "FALSE" || strtoupper($deviceproperty['value']) == "OFF") $deviceproperty['value'] = STATUS_OFF;
	

	//
	// Get previous property info (In case this if the first time we logging this property)
	// 
	$oldvalue = Null;
	$monitor = false;
	$deviceproperty['trend'] = "0";
	if (array_key_exists('previous_properties',$params['device']) && array_key_exists($propertyName,$params['device']['previous_properties'])) {
		$monitor = $params['device']['previous_properties'][$propertyName]['active'];
	} 

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
			if ($propertyName != "Link") {
				$func = 'update'.str_replace(' ','',$propertyName);
				if (function_exists ($func)) {
					if(!($feedback['updateStatus'] = $func($params, $propertyName))) return;
				} else {
					if(!($feedback['updateStatus'] = updateGeneric($params, $propertyName))) return;
				}
			}
			$oldvalue = $params['device']['previous_properties'][$propertyName]['value'];
			$deviceproperty['value'] = $params['device']['properties'][$propertyName]['value'];
			$deviceproperty['trend'] = setTrend($deviceproperty['value'], $oldvalue);
		} else {
			$feedback['!Monitor'] = $deviceproperty;
		}
		
		if (is_null($deviceproperty['value']) || trim($deviceproperty['value'])==='') {
			if (DEBUG_HA) echo "</pre>";
			$feedback['!Empty'] = 'Null or Empty String, exit';
			return $feedback;
		}
		
		
		PDOupsert('ha_mf_device_properties', $deviceproperty, Array('deviceID' => $deviceproperty['deviceID'], 'propertyID' => $deviceproperty['propertyID'] ));
		
		unset($deviceproperty['trend']);
		$lastLogDate = strtotime($row['updatedate']);
		$lastvalue = $row['value'];
		// Check for date string as well length  2015-10-06 23:33:01
		if (timeExpired($lastLogDate, 60) || (abs(floatval($deviceproperty['value'])-floatval($row['value'])) >= 1 ) || strlen($deviceproperty['value']) == 19) {
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
print_r(Array('deviceID' => $deviceproperty['deviceID'], 'propertyID' => $deviceproperty['propertyID'] ));
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

function getStatusLink($devprop) {
	// print_r($devprop);
	$feedback = Array();
	if (!empty($devprop['propertyID'])) {
		if (($property  = getDeviceProperties(Array( 'deviceID' => $devprop['deviceID'], 'propertyID' => $devprop['propertyID'])))) {
			$feedback['Status'] = $property['value'];
			$feedback['propertyID'] =$devprop['propertyID'];
		}
		if (($property  = getDeviceProperties(Array( 'deviceID' => $devprop['deviceID'], 'description' => 'Link')))) $feedback['Link'] = $property['value'];
		if (($property  = getDeviceProperties(Array( 'deviceID' => $devprop['deviceID'], 'description' => 'Timer Remaining')))) $feedback['Timer Remaining'] = $property['value'];
		$feedback['deviceID'] = $devprop['deviceID'];
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
	
        if (!is_null($log['message']) && is_array($log['message'])) $log['message'] = '<pre>'.prettyPrint(json_encode($log['message'])).'</pre>';

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
