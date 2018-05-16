<?php
// define( 'DEBUG_HA', TRUE );
// define( 'DEBUG_PROPERTIES', TRUE );
// define( 'DEBUG_TRIGGERS', TRUE );
// define( 'DEBUG_COND', TRUE );
if (!defined('DEBUG_HA')) define( 'DEBUG_HA', FALSE );
if (!defined('DEBUG_PROPERTIES')) define( 'DEBUG_PROPERTIES', FALSE );
if (!defined('DEBUG_TRIGGERS')) define( 'DEBUG_TRIGGERS', FALSE );
if (!defined('DEBUG_COND')) define( 'DEBUG_COND', FALSE );

function updateDLink($deviceID, $link = LINK_UP) {
        $params['callerID'] = MY_DEVICE_ID;
        $params['deviceID'] = MY_DEVICE_ID;
        $params['device'] = getDevice(MY_DEVICE_ID);
        $params['device']['previous_properties'] = getDeviceProperties(Array('deviceID' => MY_DEVICE_ID));
        $properties['Link']['value'] = $link;
        $params['device']['properties'] = $properties;
        return date("Y-m-d H:i:s").": ".json_encode(updateDeviceProperties($params),JSON_UNESCAPED_SLASHES)." My Link Updated <br/>\r\n";
}

function updateDeviceProperties($params) {
	// If inverted (from process.php, coming in with negated command

 	if (!array_key_exists('caller', $params)) $params['caller'] = $params;
 	$params['deviceID'] = (array_key_exists('deviceID', $params) ? $params['deviceID'] : Null);

	$feedback = Array();
	if (DEBUG_HA) {	echo "<PRE>updateProperties ";	print_r($params);//		echo "commandID:".$params['commandID'].CRLF;
		echo "deviceID: ".$params['deviceID'].CRLF;
	}

	//
	// Force update based on commandID, or given propertyID
	//
	foreach ($params['device']['previous_properties'] as $property) {
		if ($property['primary_status'] == 1 || (array_key_exists('propertyID', $params) && $property['propertyID'] == $params['propertyID'])) {
			//if (array_key_exists('commandID', $params) && (!array_key_exists('properties', $params['device']) || !array_key_exists($property['description'], $params['device']['properties']))) {
			if (!array_key_exists('properties', $params['device']) || !array_key_exists($property['description'], $params['device']['properties'])) {
				$params['device']['properties'][$property['description']]['value'] = "";
				if (DEBUG_HA) echo $property['description'].": ".$params['device']['properties'][$property['description']]['value'].CRLF;
			}
		}
	}

	// print_r($params['device']['properties']);
	if (array_key_exists('properties', $params['device'])) {		// Do we have props to update?
		$params['device']['properties'] = sortArrayByArray($params['device']['properties'], Array('Status'));
		foreach ($params['device']['properties'] as $key=>$property) {
			$feedback[] = setDevicePropertyValue($params, $key);
		}
	}

	if (DEBUG_HA) echo "Exit updateProperties </pre>";

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
		//print_r ($params);
	}

	if (strtoupper($deviceproperty['value']) == "TRUE" || strtoupper($deviceproperty['value']) == "ON") $deviceproperty['value'] = STATUS_ON;
	if (strtoupper($deviceproperty['value']) == "FALSE" || strtoupper($deviceproperty['value']) == "OFF") $deviceproperty['value'] = STATUS_OFF;

	//
	// Get previous property info (In case this if the first time we logging this property)
	//
	$monitor = false;
	$deviceproperty['trend'] = "0";
	if (array_key_exists('previous_properties',$params['device']) && array_key_exists($propertyName,$params['device']['previous_properties'])) {
		$monitor = $params['device']['previous_properties'][$propertyName]['active'];
		$params['lastUpdateDate'] = $params['device']['previous_properties'][$propertyName]['updatedate'];
	}

	//
	//	Always goto property factory 
	//
	//if (DEBUG_PROPERTIES) print_r($row);
	//
	//	Are we monitoring this property?
	//
	// Update prop back
	$params['device']['properties'][$propertyName]['value'] = $deviceproperty['value'];
	$oldvalue = (array_key_exists($propertyName, $params['device']['previous_properties']) ? $params['device']['previous_properties'][$propertyName]['value'] : Null);
	if ($monitor) {
	
		$func = 'update'.str_replace(' ','',$propertyName);
		if (function_exists ($func)) {
			if(!($feedback['updateStatus'] = $func($params, $propertyName))) {
				$feedback['!Fail'] = 'Factory returned false, exit';
				return;
			}
		} else {
			if(!($feedback['updateStatus'] = updateGeneric($params, $propertyName))) {
				$feedback['!Fail'] = 'Factory returned false, exit';
				return;
			}
		}
		$deviceproperty['value'] = $params['device']['properties'][$propertyName]['value'];
	} else {
		$feedback['!Monitor'] = $deviceproperty;
		if ($deviceproperty['propertyID'] == "243") {
			$feedback['!Monitor']['message'] = 'Level set to '.$deviceproperty['value']."%";
		}
	}

	if (is_null($deviceproperty['value']) || trim($deviceproperty['value'])==='') {
		if (DEBUG_HA) echo "IsNull - Getting out</pre>";
		$feedback['!Empty'] = 'Null or Empty String, exit';
		return $feedback;
	}

	// var_dump($deviceproperty['value']);
	// echo CRLF;
	// var_dump($oldvalue);
	// echo CRLF;
	// var_dump($deviceproperty['value'] !== $oldvalue);
	// echo CRLF;
	if ($deviceproperty['value'] !== $oldvalue && $propertyName != 'Link') {		// Link special, updated in factory (How about not monitor?)
		// echo "<pre>Updateing:";
		// print_r($deviceproperty);
		// echo "</pre>Updateing:";
		$deviceproperty['trend'] = setTrend($deviceproperty['value'], $oldvalue);
		$deviceproperty['updatedate'] = date("Y-m-d H:i:s");
		PDOupsert('ha_mf_device_properties', $deviceproperty, Array('deviceID' => $deviceproperty['deviceID'], 'propertyID' => $deviceproperty['propertyID'] ));
	}

	if (DEBUG_HA) {
		echo "afterFactory $propertyName ";
		print_r ($deviceproperty);
	}

	$sql = 'SELECT * FROM `ha_properties_log`  WHERE deviceID='.  $deviceproperty['deviceID'].' AND propertyID='.$deviceproperty['propertyID'].' order by updatedate desc limit 1';
	if ($row = FetchRow($sql)) {

		unset($deviceproperty['trend']);
		$lastLogDate = strtotime($row['updatedate']);
		$lastvalue = $row['value'];
		// Check for date string as well length  2015-10-06 23:33:01
		$logit = true;
		switch ($property['log']) {
		case "NOT":
			$logit = false;
			break;
		case "HOUR":
			$logit = timeExpired($lastLogDate, 59);
			if (is_numeric($deviceproperty['value'])) {
				$logit = $logit || ((abs(floatval($deviceproperty['value'])-floatval($row['value'])) >= 0.5 ) || strlen($deviceproperty['value']) == 19);
			} else {
				$logit = $logit || ($deviceproperty['value'] != $row['value']);
			}
			break;
		case "CHANGE":
			if (is_numeric($deviceproperty['value'])) {
				$logit = (abs(floatval($deviceproperty['value'])-floatval($row['value'])) >= 0.5 ) || strlen($deviceproperty['value']) == 19;
			} else {
				$logit = $deviceproperty['value'] != $row['value'];
			}
			break;
		case "ALWAYS":
			$logit = true;
			break;
		}
		if ($logit) {
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
// print_r(Array('deviceID' => $deviceproperty['deviceID'], 'propertyID' => $deviceproperty['propertyID'] ));
		PDOupsert('ha_mf_device_properties', $deviceproperty, Array('deviceID' => $deviceproperty['deviceID'], 'propertyID' => $deviceproperty['propertyID'] ));
		unset($deviceproperty['trend']);
		PDOinsert('ha_properties_log', $deviceproperty);
	}
	
	
	//
	// Execute triggers
	// 
	// var_dump($oldvalue);
	// var_dump($deviceproperty['value']);
//	if ($monitor && ($oldvalue !== $deviceproperty['value'])) {
	if ($oldvalue !== $deviceproperty['value']) {
		if ($property['datatype']=="BINARY") { 		// Link can return link warning, no trigger for that
			if ($deviceproperty['value'] == STATUS_ON ) {
				$result = HandleTriggers($params, $property['id'], TRIGGER_AFTER_ON);
				if (!empty($result)) $feedback = array_merge($feedback, $result);
			} elseif ($deviceproperty['value'] == STATUS_OFF ) {
				$result = HandleTriggers($params, $property['id'], TRIGGER_AFTER_OFF);
				if (!empty($result)) $feedback = array_merge($feedback, $result);
			} elseif ($deviceproperty['value'] == STATUS_ERROR ){
				$result = HandleTriggers($params, $property['id'], TRIGGER_AFTER_ERROR);
				if (!empty($result)) $feedback = array_merge($feedback, $result);
			}
		}
		$result = HandleTriggers($params, $property['id'], TRIGGER_AFTER_CHANGE);
		if (!empty($result)) $feedback = array_merge($feedback, $result);
	}
	
	if (DEBUG_HA) echo "</pre>";
	
	return $feedback;
}

function handleTriggers($params, $propertyID, $triggertype) {
	$mysql = 'SELECT * FROM `ha_mf_monitor_triggers` ' .
			'WHERE (`deviceID` = '. $params['deviceID']. ' AND propertyID = '.$propertyID.' AND `triggertype` = '.$triggertype.') ORDER BY sort';
	$feedback =  array(); 
	
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
			logEvent(array('inout' => COMMAND_IO_BOTH, 'callerID' => $params['caller']['callerID'], 'deviceID' => $params['deviceID'], 
				'commandID' => COMMAND_RUN_SCHEME, 'data' => getSchemeName($trigger['schemeID']), 'result' => $result, 'loglevel' => $thiscommand['loglevel'] ));
		}
	}
	return $feedback;
}

function getDevice($deviceID){

	$mysql='SELECT * FROM `ha_mf_devices` d	WHERE id ='.$deviceID.' AND inuse = 1';
	if ($rowdevice = FetchRow($mysql)) {
		$mysql='SELECT * FROM ha_mi_connection where id ='.$rowdevice['connectionID'];
		$mysql='SELECT `id`, `name`, `targetaddress`, `targetport`, `targettype`, `page`, `timeout`, `video_link`, `admin_page`, `authentication`, `username`,  cast(aes_decrypt(`password`, "'.SECRET.'") as char(100)) as password, `semaphore`, `notes`, `pingport`, `updatedate` FROM `ha_mi_connection` WHERE `id` ='.$rowdevice['connectionID'];
		if ($rowconn = FetchRow($mysql)) {
			$rowdevice['connection'] = $rowconn;
		}
		$mysql = 'SELECT * FROM `ha_mf_device_types` WHERE id = '.$rowdevice['typeID'];
		if ($rowtype = FetchRow($mysql)) {
			$rowdevice['type'] = $rowtype;
		}
		if (!empty($rowdevice['ipaddressID'])) {
			$mysql = 'SELECT * FROM `ha_mf_device_ipaddress` WHERE id = '.$rowdevice['ipaddressID'];
			if ($rowaddress = FetchRow($mysql)) {
				$rowdevice['ipaddress'] = $rowaddress;
			}
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
// Array
// (
    // [137] => Array
        // (
            // [Link] => Array
                // (
                    // [id] => 14700
                    // [deviceID] => 137
                    // [propertyID] => 225
                    // [value] => 1
                    // [trend] => 0
                    // [sort] => 1020
                    // [updatedate] => 2015-10-13 21:49:36
                    // [description] => Link
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
// If DeviceID given, then only that device, (or deviceList) 

	if (array_key_exists('description', $deviceproperty)) {			// Find property ID if description given
		$propertyName = $deviceproperty['description'];
		$deviceproperty['propertyID'] = getProperty($deviceproperty['description'])['id'];
		unset($deviceproperty['description']);  //
		//echo "Found id for $propertyName ".$deviceproperty['propertyID'].CRLF;
	}

	if (array_key_exists('deviceID', $deviceproperty) && array_key_exists('propertyID', $deviceproperty)) {		// DeviceID and PropertyID
		$result = False;
		if ($rowproperty = FetchRow(
			'SELECT dp.*, mp.active, mp.invertstatus, mp.toggleignore, `p`.`description`, `p`.`datatype`,  `p`.`primary_status`, `p`.`report_status`  FROM ha_mf_device_properties dp 
			 LEFT JOIN ha_mf_monitor_property mp ON dp.propertyID = mp.propertyID AND dp.deviceID = mp.deviceID 
			 JOIN ha_mi_properties p ON dp.propertyID = p.id 
			 WHERE dp.deviceID = '.$deviceproperty['deviceID'].' AND dp.propertyID = '.$deviceproperty['propertyID'].' ORDER BY p.sort')) {
			if (DEBUG_PROPERTIES) {
				echo "<pre> Dev & Prop ";
				print_r($rowproperty);
				echo "</pre>";
			}
			if ($deviceproperty['propertyID'] == '225') {
				if ($link = FetchRow('SELECT active, linkmonitor, listenfor1, listenfor2, pingport, link_warning, link_timeout FROM `ha_mf_monitor_link` 
						WHERE deviceID = '.$deviceproperty['deviceID'])) $rowproperty = array_merge($rowproperty, $link);
			}
			if (DEBUG_PROPERTIES) {
				echo "<pre> Dev & Prop merged";
				print_r($rowproperty);
				echo "</pre>";
			}
			return $rowproperty;
		}
	} elseif (array_key_exists('propertyID', $deviceproperty)) {		// Only PropertyID Used from upateTimer/Link to get all devices with timer set 
		$result = Array();
		if ($rowproperties = FetchRows('SELECT dp.*, p.description, `p`.`datatype`,  `p`.`primary_status`, `p`.`report_status` FROM ha_mf_device_properties dp JOIN ha_mi_properties p ON dp.propertyID = p.id WHERE propertyID = '.$deviceproperty['propertyID'].'  ORDER BY p.sort')) {
			foreach ($rowproperties AS $key => $prop) {
				if ($propertyName == 'Link') {
					if ($link = FetchRow('SELECT active, linkmonitor, listenfor1, listenfor2, pingport, link_warning, link_timeout FROM `ha_mf_monitor_link` 
						WHERE deviceID = '.$prop['deviceID'])) $rowproperties[$key] = array_merge($prop, $link);
				}
				$result[$prop['deviceID']][$propertyName] = $rowproperties[$key];
			}
			if (DEBUG_PROPERTIES) {
				echo "<pre> Only Prop (All devs) ";
				print_r($result);
				echo "</pre>";
			}
		}
		return $result;
	} elseif (array_key_exists('deviceID', $deviceproperty))  {		// Only DeviceID (Support deviceList
		$result = Array();
		if (!is_null($deviceproperty['deviceID']) && $rowproperties = FetchRows(
			'SELECT dp.*, mp.active, mp.invertstatus, mp.toggleignore, `p`.`description`, `p`.`datatype`, `p`.`primary_status`, `p`.`report_status` FROM ha_mf_device_properties dp 
			 LEFT JOIN ha_mf_monitor_property mp ON dp.propertyID = mp.propertyID AND dp.deviceID = mp.deviceID 
			 JOIN ha_mi_properties p ON dp.propertyID = p.id 
			 WHERE dp.deviceID = '.$deviceproperty['deviceID'].'  ORDER BY p.sort')) {
 			foreach ($rowproperties AS $key => $prop) {
				if ($prop['description'] == 'Link') {
					if ($link = FetchRow('SELECT active, linkmonitor, listenfor1, listenfor2, pingport, link_warning, link_timeout FROM `ha_mf_monitor_link` 
						WHERE deviceID = '.$deviceproperty['deviceID'])) $rowproperties[$key] = array_merge($rowproperties[$key], $link);
				}
				$result[$prop['description']] = $rowproperties[$key];
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
			$feedback['PropertyID'] =$devprop['propertyID'];
		}
		if ($link = FetchRow('SELECT ln FROM `ha_vw_monitor_link_status` WHERE active = 1 AND deviceID = '.$devprop['deviceID'])) $feedback['Link'] = $link['ln'];
		if (($property  = getDeviceProperties(Array( 'deviceID' => $devprop['deviceID'], 'description' => 'Timer Remaining')))) $feedback['Timer Remaining'] = $property['value'];
		$feedback['DeviceID'] = $devprop['deviceID'];
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
	PDOExec($mysql);

	return true ;
}

function getProperty($key_description, $autocreate = true){
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
	} elseif ($descriptiongiven && $autocreate) {		// Create
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
	if (array_key_exists('ip', $log)) {
		if ($lname = findLocalName($log['ip'])) {
			$log['ip'] = $lname; 
		} else {
			$log['ip'] = findRemoteName($log['ip']);
		}
	}
 	if (!array_key_exists('deviceID', $log)) $log['deviceID'] = 0;
	if (!array_key_exists('commandID', $log)) $log['commandID'] = COMMAND_UNKNOWN;
	if (!array_key_exists('inout', $log)) $log['inout'] = COMMAND_IO_NOT;
	if (!array_key_exists('callerID', $log)) $log['callerID'] = 0;
	if (!array_key_exists('data', $log)) $log['data'] = Null;
	if (!array_key_exists('loglevel', $log)) $log['loglevel'] = LOGLEVEL_COMMAND;
	if (!array_key_exists('message', $log)) $log['message'] = Null;
	if (!array_key_exists('result', $log)) $log['result'] = Null;
	if (!array_key_exists('commandstr', $log)) $log['commandstr']=Null;

	if ($log['loglevel'] == LOGLEVEL_NONE) return true;
	
	//
	//	Get device type and monitorid
	//
	$log['typeID'] = 0;
	if ($log['deviceID'] != 0) {
		$log['typeID'] = getDevice($log['deviceID'])['typeID'];
		$mysql = 'SELECT invertstatus FROM `ha_mf_monitor_property` ' .
					' WHERE propertyID = 123 AND deviceID = '.$log['deviceID']; 
		$rowdevice=FetchRow($mysql);
		if ($rowdevice['invertstatus'] == '0') {
			$log['data'] .= ' Inverted'; 
			if ($log['commandID'] == COMMAND_OFF) {
				$log['commandID'] = COMMAND_ON;
			} elseif ($log['commandID'] == COMMAND_ON) {
				$log['commandID'] = COMMAND_OFF;
			}
		}
	}
	
	$log['mdate'] = date('Y-m-d H:i:s');

	if (is_null($log['loglevel']))	{
		if (!is_null($log['commandID'])) {
			$mysql='SELECT `loglevel` FROM `ha_mf_commands` WHERE `id` ='.$log['commandID'];
			if ($rowcommand=FetchRow($mysql)) {
				$log['loglevel'] = $rowcommand['loglevel'];
			}
		}
	}
	if (is_null($log['loglevel'])) $log['loglevel'] = LOGLEVEL_COMMAND;

	if (!is_null($log['result'])) {
		if (is_array($log['result'])) 
			$log['result'] = '<pre>'.prettyPrint(json_encode($log['result'],JSON_UNESCAPED_SLASHES)).'</pre>';
		else
			$log['result'] = '<pre>'.str_replace('\n','</br>',$log['result']).'</pre>';
	}
		
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
	if ($rows = FetchRows('SELECT propertyID FROM ha_mf_device_properties  WHERE deviceID IN ('.$devices.')')) {
		foreach ($rows AS $prop) {
			$result[] = $prop['propertyID'];
		}
		return $result;
	}
	return false ;
}

function updateThermType($deviceID, $typeID){

	PDOUpdate('ha_mf_devices', array('typeID' => $typeID), array('id' => $deviceID ));
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

function doFilter(&$arr, $nodefilter, &$filter, &$result) {

    foreach ($arr as $key => $value) {
        if (array_key_exists($key, $nodefilter)) {
			if (is_array($value)) {
				$result[][$key] = array_intersect_key($arr[$key], $filter);
				//$arr[$key] = doFilter($value, $nodefilter, $filter,  $result);
				 // echo "Key1: $key".CRLF;
			} else {
				if ($arr[$key] != Null) {
					if (trim($arr[$key]) != '') $result[][$key] =$arr[$key];
				}
				// echo "Key2: $key".CRLF;
			}
        } else if (is_array($value)) {
            $arr[$key] = doFilter($value, $nodefilter, $filter,  $result);
			// echo "Key3: $key".CRLF;
        } else if (array_key_exists($key, $filter)) {
			$result[][$key] =$arr[$key];
			// echo "Key4: $key".CRLF;
		}
    }
	// print_r($result);
    return;
}

function checkConditions($rows, $params) {

	$feedback = array();
	$feedback['result'] = array( false );
	foreach ($rows as $rowcond) {
		$testvalue = array();
		switch ($rowcond['cond_type'])
		{
		case SCHEME_CONDITION_DEVICE_PROPERTY_STATUS_VALUE_AS_STEP:
		case SCHEME_CONDITION_DEVICE_PROPERTY_LINK_VALUE_AS_STEP:
			$rowcond['cond_propertyID'] = $rowcond['cond_type']; // Set property to Status or Link
		case SCHEME_CONDITION_DEVICE_PROPERTY_VALUE:
			if (DEBUG_COND) echo "SCHEME_CONDITION_DEVICE_PROPERTY_VALUE".CRLF;
			$condtype = "SCHEME_CONDITION_DEVICE_PROPERTY_VALUE";
        		$deviceID = ($rowcond['cond_deviceID'] == DEVICE_CALLER_ID ? $params['caller']['callerID'] : $rowcond['cond_deviceID']);
			$testvalue[] = getDeviceProperties(Array('propertyID' => $rowcond['cond_propertyID'], 'deviceID' => $deviceID))['value'];
			$message = getProperty($rowcond['cond_propertyID'])['description']." of device ".getDevice($deviceID)['description']." "." is not";
			$savedeviceID = $rowcond['cond_deviceID'];
			// echo "<pre>";
			// print_r(getDeviceProperties(Array('propertyID' => $rowcond['cond_propertyID'], 'deviceID' => $rowcond['cond_deviceID'])));
			break;
		case SCHEME_CONDITION_GROUP_PROPERTY_AND:
		case SCHEME_CONDITION_GROUP_PROPERTY_OR:
			if (DEBUG_COND) echo "SCHEME_CONDITION_GROUP_PROPERTY_AND_OR".CRLF;
			$condtype = "SCHEME_CONDITION_GROUP_PROPERTY_AND_OR";
			if ($rowcond['cond_type'] == SCHEME_CONDITION_GROUP_PROPERTY_AND) {
				$test = 1;
			} else {
				$test = 0;
			}
			$mysql = 'SELECT g.groupID as groupID, gr.description as group_description, d.id as deviceID, typeID, inuse FROM ha_mf_device_group g 
					JOIN `ha_mf_devices` d ON g.deviceID = d.id 
					JOIN `ha_mf_groups` gr ON g.groupID = gr.id 
					WHERE groupID = '.$rowcond['cond_groupID']; 
			$groups = FetchRows($mysql);
			//$groups = getGroup(array('commandvalue' => $rowcond['cond_groupID']));
			// [getGroup] => Array (['result'][0] => Array ([groupselect] => Array ([DeviceID] => 1))
			foreach ($groups as $device) {
				if ($rowcond['cond_type'] == SCHEME_CONDITION_GROUP_PROPERTY_AND) {
					$test = $test & getDeviceProperties(Array('deviceID' => $device['deviceID'], 'propertyID' => $rowcond['cond_propertyID']))['value'];
					$message = "Some devices in group ".$device['group_description']." are ";
				} else {
					$test = $test | getDeviceProperties(Array('deviceID' => $device['deviceID'], 'propertyID' => $rowcond['cond_propertyID']))['value'];
					$message = "No devices in group ".$device['group_description']." are ";
				}
				$savedeviceID = $device['deviceID'];
			// echo "<pre>";
			// print_r(getDeviceProperties(Array('propertyID' => $rowcond['cond_propertyID'], 'deviceID' => $device['groupselect']['DeviceID'])));
			}
			$testvalue[] = $test;
			break;
		case SCHEME_CONDITION_CURRENT_TIME:
			if (DEBUG_COND) echo "SCHEME_CONDITION_CURRENT_TIME</p>";
			$condtype = "SCHEME_CONDITION_CURRENT_TIME";
			$testvalue[] = time();
			$message = "Current time is ".$device['group_description'];
			break;
		default:	// No or not recognized cond_type
			$feedback['result'] = array (true);
			return $feedback;
			break;
		}

		if ($rowcond['cond_value'] !== NULL) {
			switch (strtoupper($rowcond['cond_value']))
			{
			case "ON":
				$testvalue[] = STATUS_ON;
				$message2 = getFeedbackStatus($savedeviceID, STATUS_ON);
				break;
			case "OFF":
				$testvalue[] = STATUS_OFF;
				$message2 = getFeedbackStatus($savedeviceID, STATUS_OFF);
				break;
			default:
				switch ($rowcond['cond_type'])
				{
				case SCHEME_CONDITION_CURRENT_TIME:
					$temp = preg_split( "/([+-])/" , $rowcond['cond_value'], -1, PREG_SPLIT_DELIM_CAPTURE);
					$temp[0] = strtoupper($temp[0]);
					if ($temp[0] == "DAWN" || $temp[0] == "DUSK") {
						if ($temp[0] == "DAWN") {$temp[0] = getDawn(); $message2 = "dawn. ";}
						if ($temp[0] == "DUSK") {$temp[0] = getDusk(); ; $message2 = "dusk. ";}
						if (isset($temp[1])) {
							$testvalue[] = strtotime("today $temp[0] $temp[1]$temp[2] minutes. ");
							$message2 = "today $temp[0] $temp[1]$temp[2] minutes. ";
						} else {
							$testvalue[] = strtotime("today $temp[0]");
							$message2 = "today $temp[0]. ";
						}
					} else {
						$testvalue[] = strtotime("today $temp[0]");
						$message2 = "today $temp[0]. ";
					}
					break;
				default:
					$testvalue[] = $rowcond['cond_value'];
					$message2 = ($rowcond['cond_value'] == STATUS_ON || $rowcond['cond_value'] == STATUS_OFF ? getFeedbackStatus($savedeviceID, $rowcond['cond_value']) : $rowcond['cond_value']);
					break;
				}
				break;
			}
		}
		switch ($rowcond['cond_operator'])
		{
		case CONDITION_GREATER:
			if ($testvalue[0] <= $testvalue[1]) {
				if (DEBUG_COND) echo 'Fail: "'.$testvalue[0].'" > "'.$testvalue[1].'"'.CRLF;
				$feedback['message'] = 'Programme skipped, '.$message.' greater than '.$message2;
				if (DEBUG_COND) echo "Exit executeMacro</pre>".CRLF;
				return $feedback;
			}
			break;
		case CONDITION_LESS:
			if ($testvalue[0] >= $testvalue[1]) {
				if (DEBUG_COND) echo 'Fail: "'.$testvalue[0].'" < "'.$testvalue[1].'"'.CRLF;
				$feedback['message'] = 'Programme skipped, '.$message.' less than '.$message2;
				if (DEBUG_COND) echo "Exit executeMacro</pre>".CRLF;
				return $feedback;
			}
			break;
		case CONDITION_EQUAL:
			if ($testvalue[0] != $testvalue[1]) {
				if (DEBUG_COND) echo 'Fail: "'.$testvalue[0].'" == "'.$testvalue[1].'"'.CRLF;
				$feedback['message'] = 'Programme skipped, '.$message.' '.$message2;
				if (DEBUG_COND) echo "Exit executeMacro</pre>".CRLF;
				return $feedback;
			}
			break;
		}
		if (DEBUG_COND) echo "Condition Pass: condition value: ".$testvalue[0].", test for: ".$testvalue[1].CRLF;
	}
	
	// All passed unset false result
	$feedback['result'] = array (true);
	return $feedback;
}

function getFeedbackStatus($deviceID, $status) {
$status_feedback = array (
	array("off","on"),		// 0
	array("off","on"),		// 1
	array("closed","open"),
	array("un-locked","locked"),
	array("disarmed","armed"),
	array("not seen","detected"),
	array("off","running")
);

        if  (!getDevice($deviceID)) {
                echo "<pre>";
                echo "Unknown/in-active device: $deviceID";
                echo "</pre>";
        }

	$statusNames = $status_feedback[getDevice($deviceID)['type']['status_feedback']];
	
	// print_r($statusNames);
	if ($status == STATUS_OFF) {
		$feedbackstatus=$statusNames[STATUS_OFF];
	} elseif ($status == STATUS_UNKNOWN) {
		$feedbackstatus="unknown";
	} elseif ($status == STATUS_ON) {
		$feedbackstatus=$statusNames[STATUS_ON];
	} elseif ($status == STATUS_ERROR) {
		$feedbackstatus="error";
	} else { 							
		$feedbackstatus=$status;
	}
	return $feedbackstatus;

}
?>
