<?php

//define( 'DEBUG_HA', TRUE );
if (!defined('DEBUG_HA')) define( 'DEBUG_HA', FALSE );


function ReloadScreenShot() {
	$url = 'http://htpc:8085/HipScreenShot.jpg';
	$img = 'images/HIPScreenshot.jpg';
	file_put_contents($img, file_get_contents($url));
	$post = RestClient::post('http://htpc:8085/index.htm');
	return;  // ReadCurlReturn($post);
}

function UpdateLink($deviceID, $link = LINK_UP, $callerID = 0, $commandID = 0){

    if (DEBUG_HA) echo "Update Link deviceID ".$deviceID.CRLF;

	$mysql = "SELECT * FROM `ha_mf_monitor_link` WHERE deviceID = ".$deviceID; 
	if ($row = FetchRow($mysql)) {
		$prevlink = $row['link'];
			// Listen for Statuses StatusOff StatusOn
		if (DEBUG_HA) echo $row['linkmonitor'].' l1: '.$row['listenfor1'].' l2: '.$row['listenfor2'].' l3: '.$row['listenfor3'].' commandID: '.$commandID.CRLF;

		if ( $row['linkmonitor'] == "INTERNAL" || 
		     $row['linkmonitor'] == "POLL" || 
		    ($row['linkmonitor'] == "MONSTAT" && ($row['listenfor1'] == $commandID || $row['listenfor2'] == $commandID || $row['listenfor3'] == $commandID))) { 				
			if ($prevlink != $link) { 					// status changed
				if ($prevlink == LINK_UP) { 			// Link was up, wait for time_out time before acknowledge as down
					if ($row['link_timeout'] != Null) {
						if (DEBUG_HA) echo "CHECK FOR TIMEOUT".CRLF;
 						$temp = explode(" ", $row['link_timeout']);
						$temp2 = explode(":", $temp[1]);
						$min = $temp[0]*1440 + $temp2[0]*60 + $temp2[1];
						$last = strtotime($row['mdate']);
						$nowdt = strtotime(date("Y-m-d H:i:s"));
					}
					if ((abs($nowdt-$last) / 60 > $min) || $row['link_timeout'] == Null) {
						echo "Down, Previous was up; Time expired".CRLF;
						logEvent($log = Array ('inout' => COMMAND_IO_SEND, 'callerID' => $callerID, 'deviceID' => $deviceID, 'commandID' => $commandID, 'data' => ($link == LINK_UP ? "Up" : "Down")));
						$mysql = "UPDATE `ha_mf_monitor_link` SET " .
								  " `mdate` = '" . date("Y-m-d H:i:s") . "'," .
								  " `link` = '" . $link . "'" .
								  " WHERE(`deviceID` ='" . $deviceID . "')";
						if (!mysql_query($mysql)) mySqlError($mysql);
						HandleTriggers($callerID, $deviceID, MONITOR_LINK, TRIGGER_AFTER_CHANGE);
						HandleTriggers($callerID, $deviceID, MONITOR_LINK, TRIGGER_AFTER_OFF);
					} else {
						echo "Down, Previous was up; Time NOT expired".CRLF;
					}
				} else {   								// previous was down UPDATE to online and log event
					echo "Up, Previous was down; UPDATE to online and log event".CRLF;
					logEvent($log = Array ('inout' => COMMAND_IO_SEND, 'callerID' => $callerID, 'deviceID' => $deviceID, 'commandID' => $commandID, 'data' => ($link == LINK_UP ? "Up" : "Down")));
					$mysql = "UPDATE `ha_mf_monitor_link` SET " .
							  " `mdate` = '" . date("Y-m-d H:i:s") . "'," .
							  " `link` = '" . $link . "'" .
							  " WHERE(`deviceID` ='" . $deviceID . "')";
					if (!mysql_query($mysql)) mySqlError($mysql);
					HandleTriggers($callerID, $deviceID, MONITOR_LINK, TRIGGER_AFTER_CHANGE);
					HandleTriggers($callerID, $deviceID, MONITOR_LINK, TRIGGER_AFTER_ON);
				}
			}
			if ($link == LINK_UP) { 			// Link is up, UPDATE time
				echo "Up and same as prev status only UPDATE time".CRLF;
				$mysql = "UPDATE `ha_mf_monitor_link` SET " .
						  " `mdate` = '" . date("Y-m-d H:i:s") . "'" .
						  " WHERE(`deviceID` ='" . $deviceID . "')";
				if (!mysql_query($mysql)) mySqlError($mysql);
				//HandleTriggers($callerID, $deviceID, MONITOR_LINK, TRIGGER_AFTER_ON);
			} else {
				echo "Down and same as prev status, Do nothing.</br>\n";
				//HandleTriggers($callerID, $deviceID, MONITOR_LINK, TRIGGER_AFTER_OFF);
			}
		return true;
		}
	} 
	return false;
}
		
function UpdateStatus ($callerID, $params)
{
//$deviceID, $commandID = NULL, $status = NULL) 
 	$deviceID = (array_key_exists('deviceID', $params) ? $params['deviceID'] : Null);
	$commandID = (array_key_exists('commandID', $params) ? $params['commandID'] : Null);
	$commandvalue = (array_key_exists('commandvalue', $params) ? $params['commandvalue'] : Null);
	$status = (array_key_exists('status', $params) ? $params['status'] : Null);

	// Interpret status value based on current command, i.e. On/Off/Error
	if (DEBUG_HA) echo "Status commandID:".$commandID.CRLF;
	if (DEBUG_HA) echo "Status deviceID: ".$deviceID.CRLF;
	if ($commandID != NULL) {
		if (!$rescommands = mysql_query("SELECT status FROM ha_mf_commands WHERE ha_mf_commands.id =".$commandID)) mySqlError($mysql);
		if ($rowcommands = mysql_fetch_array($rescommands)) {
			$status = $rowcommands['status'];
	if (DEBUG_HA) echo "Status Status:".$status.CRLF;
			if ($status == STATUS_NOT_DEFINED)  return;
		} else {
			return;
		}
	}
	
	$now = date( 'Y-m-d H:i:s' );
	// Only retrieve if Monitor Type = Status Monitor
	$mysql = "SELECT typeID, inuse, monitortypeID, deviceID, status, statusDate, invertstatus, commandvalue FROM `ha_mf_devices` d " . 
				" JOIN `ha_mf_monitor_status` s ON d.id = s.deviceID ". 
				" WHERE deviceID = ".$deviceID. " AND (monitortypeID = ".MONITOR_STATUS. " OR monitortypeID = ".MONITOR_LINK_STATUS.")"; 
	if (DEBUG_HA) echo "Sql: ".$mysql.CRLF;
	if ($row = FetchRow($mysql)) {
		if ($row['invertstatus'] == 0) {
			if (DEBUG_HA) echo "Status Invert".CRLF;
			if ($status == STATUS_ON) {
				$status = STATUS_OFF;
			} elseif ($status == STATUS_OFF) {
				$status = STATUS_ON;
			}
		}
		if (DEBUG_HA) echo "Status Status2:".$status.CRLF;
		$feedback['deviceID'] = $deviceID;
		$feedback['status'] = $status;
		$feedback['commandvalue'] = $commandvalue;
//		if ($row['status'] != $status || $row['value'] != $commandvalue) {			// need to retrieve commandvalue
		if ($row['status'] != $status || $row['commandvalue'] != $commandvalue) {
			// UPDATE before scheme to reduce race condition with logger
			$mysql = "UPDATE ha_mf_monitor_status SET status = " . $status . ", commandvalue = ". ($commandvalue == Null ? 'NULL' : $commandvalue) .", statusDate = '". $now . "' WHERE deviceID = ".$deviceID;
			if (!mysql_query($mysql)) mySqlError($mysql);
			// run on change
			$result = HandleTriggers($callerID, $deviceID, MONITOR_STATUS, TRIGGER_AFTER_CHANGE);
			if (!empty($result)) $feedback['Triggers'] = $result;
		}
		if ($status == STATUS_ON ) {
			$result = HandleTriggers($callerID, $deviceID, MONITOR_STATUS, TRIGGER_AFTER_ON);
			if (!empty($result)) $feedback['Triggers'] = $result;
		} else {
			$result = HandleTriggers($callerID, $deviceID, MONITOR_STATUS, TRIGGER_AFTER_OFF);
			if (!empty($result)) $feedback['Triggers'] = $result;
		}
		
		return $feedback;
	}
	return;
}	

function GetStatus ($callerID, $params)
{
//$deviceID, $commandID = NULL, $status = NULL) 
 	$deviceID = (array_key_exists('deviceID', $params) ? $params['deviceID'] : Null);

	// Only retrieve if Monitor Type = Status Monitor
	$mysql = "SELECT typeID, inuse, monitortypeID, deviceID, status, statusDate, invertstatus, commandvalue FROM `ha_mf_devices` d " . 
				" JOIN `ha_mf_monitor_status` s ON d.id = s.deviceID ". 
				" WHERE deviceID = ".$deviceID. " AND (monitortypeID = ".MONITOR_STATUS. " OR monitortypeID = ".MONITOR_LINK_STATUS.")"; 
	if (DEBUG_HA) echo "Sql: ".$mysql.CRLF;
	if ($row = FetchRow($mysql)) {
		if (DEBUG_HA) echo "Status Status2:".$status.CRLF;
		$feedback['deviceID'] = $deviceID;
		$feedback['status'] = $row['status'];
		$feedback['commandvalue'] = $row['commandvalue'];

		return $feedback;
	}
	return;
}	

function logEvent($log) {

	$log['ip']=(!empty($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : NULL);
	if (!array_key_exists("deviceID", $log)) $log['deviceID'] = Null;
	if (!array_key_exists("commandID", $log)) $log['commandID'] = COMMAND_UNKNOWN;
	if (!array_key_exists("inout", $log)) $log['inout'] = COMMAND_IO_NOT;
	if (!array_key_exists("callerID", $log)) $log['callerID'] = Null;
	if (!array_key_exists("repeatcount", $log)) $log['repeatcount'] = 1;
	if (!array_key_exists("data", $log)) $log['data'] = Null;
	if (!array_key_exists("result", $log)) $log['result'] = Null;
	if (!array_key_exists("extdata", $log)) $log['extdata'] = Null;
	if (!array_key_exists("loglevel", $log)) $log['loglevel'] = Null;
	if ($log['loglevel'] == LOGLEVEL_NONE) return true;
	if ($log['result'] === FALSE) $log['result'] = "FALSE";
	if ($log['result'] === TRUE) $log['result'] = "TRUE";
	
	//
	// Check for repeat logs
	//
	$repeatcount=1;
	$mysql = 'SELECT * FROM `ha_events` ' .
			' WHERE `inout` = '.$log['inout']. ' AND `callerID` ='.$log['callerID'].' AND commandID = '.$log['commandID'].
			' AND  DATE_ADD(`mdate`,INTERVAL  65 SECOND) > "'.date("Y-m-d H:i:s").'"  AND  repeatcount < 10';

	if ($log['deviceID'] != Null) $mysql .= ' AND `deviceID` = '.$log['deviceID']; else $mysql .= ' AND `deviceID` IS NULL';
	if ($log['data'] != Null) $mysql .= ' AND `data` = "'.$log['data'].'"'; else $mysql .= ' AND `data` IS NULL';

	if (!$resevents=mysql_query($mysql)) {	
		mySqlError($mysql);
		return false;
	} 
	if ($rowevents=mysql_fetch_array($resevents)) {
		$repeatcount = $rowevents['repeatcount'] + 1;
		$mysql='UPDATE `ha_events` SET `repeatcount` = '.$repeatcount. ' WHERE `ha_events`.`id` ='.$rowevents['id'];
		if (!mysql_query($mysql)) mySqlError($mysql);
	} else {
		//
		//	Get device type and monitorid
		//
		$log['typeID'] = NULL;
		if ($log['deviceID'] != Null) {
			$mysql = "SELECT typeID, inuse, monitortypeID, deviceID, status, statusDate, invertstatus FROM `ha_mf_devices` d " . 
						" LEFT JOIN `ha_mf_monitor_status` s ON d.id = s.deviceID ". 
						" WHERE d.id = ".$log['deviceID']; 
			if (!$resdevice=mysql_query($mysql)) {
				mySqlError($mysql); 
				return false;
			}
			$rowdevice=mysql_fetch_array($resdevice);
			$log['typeID'] = $rowdevice['typeID'];
			if ($rowdevice['invertstatus'] === 0) $log['extdata'] = "Inverted ".$log['extdata']; 
		}
		
		$log['mdate'] = date("Y-m-d H:i:s");

		if (is_null($log['loglevel']))	{
			$mysql='SELECT `loglevel` FROM `ha_mf_commands` WHERE `id` ='.$log['commandID'];
			if (!$rescommand=mysql_query($mysql)) {
				mySqlError($mysql);
			} else {
				$rowcommand=mysql_fetch_array($rescommand);
				$log['loglevel'] = $rowcommand['loglevel'];
			}
		}
		if (is_null($log['loglevel'])) $log['loglevel'] = LOGLEVEL_COMMANDS;
		
		if (DEBUG_HA) echo "***log";
		if (DEBUG_HA) print_r($log);
			
		mysql_insert_assoc ("ha_events", $log);
	}
}

function HandleTriggers($callerID, $deviceID, $monitortype, $triggertype) {
	$mysql = 'SELECT * FROM `ha_mf_monitor_triggers` ' .
			'WHERE (`deviceID` = '. $deviceID. ' AND statuslink = '.$monitortype.' AND `triggertype` = '.$triggertype.')';
	$feedback =  Null; 
	if ($triggerrows = FetchRows($mysql)) {
		foreach ($triggerrows as $trigger) {
			if (DEBUG_HA) echo "trigger: ";
			if (DEBUG_HA) print_r($trigger);
			$feedback['Trigger:'.$trigger['id']] = RunScheme ($callerID, array ( 'deviceID' => $deviceID, 'schemeID' => $trigger['schemeID']));
		}
	}
	return $feedback;
}

function UpdateWeatherNow($deviceID,$temp, $humidity, $set_point = "NULL"){
	
	$mysql = "SELECT temperature_c, humidity_r FROM ha_weather_now  WHERE deviceID = ".$deviceID; 
	if ($row = FetchRow($mysql)) {
		$ttrend = setTrend($temp, $row['temperature_c']);
		$htrend = setTrend($humidity, $row['humidity_r']);
	}
	
	$mysql = "UPDATE ha_weather_now SET mdate = '". date("Y-m-d H:i:s")."'," .
				" temperature_c = ". $temp ." , set_point = ". $set_point . ", ttrend = ".$ttrend.", humidity_r = ".$humidity.", htrend = ".$htrend."  WHERE deviceID = ".$deviceID;

	if (!mysql_query($mysql)) mySqlError($mysql);
}

function UpdateThermType($deviceID, $typeID){

	$mysql = "UPDATE `ha_mf_devices` SET " .
    			  " `typeID` = " . $typeID . "" .
				  " WHERE(`id` ='" . $deviceID . "')";
	if (!mysql_query($mysql)) mySqlError($mysql);
	$mysql = "UPDATE `ha_weather_now` SET " .
    			  " `typeID` = " . $typeID . "" .
				  " WHERE(`deviceID` ='" . $deviceID . "')";
	if (!mysql_query($mysql)) mySqlError($mysql);
	return true;
}

function GetGroup($groupID){

	$mysql = 'SELECT g.groupID as groupID, d.id as deviceID, typeID, inuse, monitortypeID, status, statusDate, invertstatus, commandvalue FROM ha_mf_device_group g 
					JOIN `ha_mf_devices` d ON g.deviceID = d.id 
					LEFT JOIN `ha_mf_monitor_status` s ON d.id = s.deviceID 
					WHERE groupID = '.$groupID; 
	return FetchRows($mysql);
}


function setTrend($new, $old) {
	if ( $new == $old ) return 0;
	if ( $new > $old )  return 1;
	if ( $new < $old )  return 2;
}
?>
