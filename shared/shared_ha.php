<?php

// define( 'DEBUG_HA', TRUE );
if (!defined('DEBUG_HA')) define( 'DEBUG_HA', FALSE );


function ReloadScreenShot() {
	$url = 'http://htpc:8085/HipScreenShot.jpg';
	$img = 'images/HIPScreenshot.jpg';
	file_put_contents($img, file_get_contents($url));
	$post = RestClient::post('http://htpc:8085/index.htm');
	return;  // ReadCurlReturn($post);
}

function UpdateLink($params)
{
	$callerID = (array_key_exists('callerID', $params) ? $params['callerID'] : Null);
	$deviceID = (array_key_exists('deviceID', $params) ? $params['deviceID'] : $callerID);
	$commandID = (array_key_exists('commandID', $params) ? $params['commandID'] : Null);
	$link = (array_key_exists('link', $params) ? $params['link'] : LINK_UP);

    if (DEBUG_HA) echo CRLF.CRLF."Update Link deviceID ".$deviceID." link:".$link.CRLF;

	$mysql = "SELECT * FROM `ha_mf_monitor_link` WHERE deviceID = ".$deviceID; 
	if ($row = FetchRow($mysql)) {
		$prevlink = $row['link'];
		// Listen for Statuses StatusOff StatusOn
		if (DEBUG_HA) echo $row['linkmonitor'].' l1: '.$row['listenfor1'].' l2: '.$row['listenfor2'].' l3: '.$row['listenfor3'].' commandID: '.$commandID.CRLF;
		
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
		switch ($link) {
		case LINK_TIMEDOUT: 		// Treat LINK_DOWN form polling and check TIMEDOUT same
		case LINK_DOWN: 
			if ($row['linkmonitor'] == "MONSTAT" && !is_null($commandID) && ($row['listenfor1'] == $commandID || $row['listenfor2'] == $commandID || $row['listenfor3'] == $commandID)) {
				if (DEBUG_HA) echo 'Found commandID, LINK_UP'.CRLF;
				$link = LINK_UP;
			}
			break;
		//case LINK_WARNING:  SHOULD NOT COME IN
		//case LINK_UP: up is up
		}

		// Determine if timed out (WARNING or DOWN time expired
		if ($link == LINK_DOWN)	$link = LINK_TIMEDOUT;
		if ($link == LINK_TIMEDOUT) {
			if ($row['link_warning'] != Null) {
				if (DEBUG_HA) echo "CHECK FOR WARNING".CRLF;
				$temp = explode(" ", $row['link_warning']);
				$temp2 = explode(":", $temp[1]);
				$min = $temp[0]*1440 + $temp2[0]*60 + $temp2[1];
				$last = strtotime($row['mdate']);
				$nowdt = strtotime(date("Y-m-d H:i:s"));
				if (abs($nowdt-$last) / 60 > $min) {
					if (DEBUG_HA) echo "Warning Time expired".CRLF;
					$link = LINK_WARNING;
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
				$link = LINK_DOWN;
			}
			if ($link == LINK_TIMEDOUT)	{
				if (DEBUG_HA) echo "Checked for timeout, was not, so do nothing".CRLF;
				return true;
			} else {
				if (DEBUG_HA) echo "Timeout out, handle transition".CRLF;
			}
		}

		if (DEBUG_HA) echo 'Still here, current link: '.$link.CRLF;

		// New link = warning then update and exit
		if ($link == LINK_WARNING) {
			if (DEBUG_HA) echo "Went to LINK_WARNING, only update link not time".CRLF;
			$mysql = "UPDATE `ha_mf_monitor_link` SET " .
					  "`link` = '" . LINK_WARNING . "'" .
					  " WHERE(`deviceID` ='" . $deviceID . "')";
			if (!mysql_query($mysql)) mySqlError($mysql);
			return true;			// Done exit
		}

		if ($prevlink == LINK_WARNING) $prevlink = LINK_UP;		// Treat warning as was up

		// Handle transitions
		if ($prevlink != $link) { 								// link changed
			if ($prevlink == LINK_UP && $link == LINK_DOWN) { 	
					if (DEBUG_HA) echo "Down, Previous was up".CRLF;
					logEvent($log = array('inout' => COMMAND_IO_SEND, 'callerID' => $callerID, 'deviceID' => $deviceID, 'commandID' => $commandID, 'data' => ($link == LINK_UP ? "Up" : "Down")));
					$mysql = "UPDATE `ha_mf_monitor_link` SET " .
							  " `link` = '" . $link . "'" .
							  " WHERE(`deviceID` ='" . $deviceID . "')";
					if (!mysql_query($mysql)) mySqlError($mysql);
					$result = HandleTriggers($params, MONITOR_LINK, TRIGGER_AFTER_CHANGE);
					if (!empty($result)) print_r ($result);
					$result = HandleTriggers($params, MONITOR_LINK, TRIGGER_AFTER_OFF);
					if (!empty($result)) print_r ($result);
				} 
			if ($prevlink == LINK_DOWN && $link == LINK_UP) { 	
				if (DEBUG_HA) echo "Up, Previous was down; UPDATE to online and log event".CRLF;
				logEvent($log = array('inout' => COMMAND_IO_SEND, 'callerID' => $callerID, 'deviceID' => $deviceID, 'commandID' => $commandID, 'data' => ($link == LINK_UP ? "Up" : "Down")));
				$mysql = "UPDATE `ha_mf_monitor_link` SET " .
						  " `mdate` = '" . date("Y-m-d H:i:s") . "'," .
						  " `link` = '" . $link . "'" .
						  " WHERE(`deviceID` ='" . $deviceID . "')";
				if (!mysql_query($mysql)) mySqlError($mysql);
				$result =  HandleTriggers($params, MONITOR_LINK, TRIGGER_AFTER_CHANGE);
				if (!empty($result)) print_r ($result);
				$result = HandleTriggers($params, MONITOR_LINK, TRIGGER_AFTER_ON);
				if (!empty($result)) print_r ($result);
			}
		} else {			// link is same as prev link
			if ($link == LINK_UP) { 			// Link is up, UPDATE time
				if (DEBUG_HA) echo "Up and same as prev link UPDATE".CRLF;
				$mysql = "UPDATE `ha_mf_monitor_link` SET " .
					  " `link` = '" . $link . "'," .
					  " `mdate` = '" . date("Y-m-d H:i:s") . "'" .
					  " WHERE(`deviceID` ='" . $deviceID . "')";
				if (!mysql_query($mysql)) mySqlError($mysql);
			} else {
				if (DEBUG_HA) echo "Down and same as prev link, Do nothing.</br>\n";
			}
		}
		return true;
	} else {
		return false;
	}
}
		
function UpdateStatus($params)
{
	// If inverted (from process.php, coming in with negated command


 	$callerID = (array_key_exists('callerID', $params) ? $params['callerID'] : Null);
 	$deviceID = (array_key_exists('deviceID', $params) ? $params['deviceID'] : Null);
	$commandID = (array_key_exists('commandID', $params) ? $params['commandID'] : Null);
	$commandvalue = (array_key_exists('commandvalue', $params) ? $params['commandvalue'] : Null);
	$status = (array_key_exists('status', $params) ? $params['status'] : Null);

	// Interpret status value based on current command, i.e. On/Off/Error
	if (DEBUG_HA) echo "Status commandID:".$commandID.CRLF;
	if (DEBUG_HA) echo "Status deviceID: ".$deviceID.CRLF;
	if (DEBUG_HA) echo "Status status: ".$status.CRLF;
	if (!array_key_exists('status', $params)) {
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
	}
	
	$now = date( 'Y-m-d H:i:s' );
	// Only retrieve if Monitor Type = Status Monitor
	$mysql = "SELECT typeID, inuse, monitortypeID, deviceID, status, statusDate, invertstatus, commandvalue FROM `ha_mf_devices` d " . 
				" JOIN `ha_mf_monitor_status` s ON d.id = s.deviceID ". 
				" WHERE deviceID = ".$deviceID. " AND (monitortypeID = ".MONITOR_STATUS. " OR monitortypeID = ".MONITOR_LINK_STATUS.")"; 
	if (DEBUG_HA) echo "Sql: ".$mysql.CRLF;

	// *** Inverting Start **** 
	// Hack, to make setTimer work.

	if ($row = FetchRow($mysql)) {
		if ($row['invertstatus'] == 0) {
			if (DEBUG_HA) echo "Status Invert".CRLF;
			if ($commandID == COMMAND_ON) {
				$status = STATUS_OFF;
			} elseif ($commandID == COMMAND_OFF) {
				$status = STATUS_ON;
			}
		}
		if (DEBUG_HA) echo "Status Status2:".$status.CRLF;
		$feedback['deviceID'] = $deviceID;
		$feedback['status'] = $status;
		$feedback['commandvalue'] = $commandvalue;
		$params['status'] = $status;

		if ($row['status'] != $status || $row['commandvalue'] != $commandvalue) {
			// UPDATE before scheme to reduce race condition with logger
			$mysql = 'UPDATE ha_mf_monitor_status SET status = ' . $status . ', commandvalue = '. ($commandvalue == Null ? 'NULL' : $commandvalue) .', statusDate = "'. $now .'"';
			if ($status == STATUS_OFF) $mysql .= ', timerMinute = NULL, timerRemaining = NULL, timerDate = NULL';
			$mysql .= ' WHERE deviceID = '.$deviceID;
			if (DEBUG_HA) echo "Update Status: ".$mysql.CRLF;
			if (!mysql_query($mysql)) mySqlError($mysql);
			// run on change
			$result = HandleTriggers($params, MONITOR_STATUS, TRIGGER_AFTER_CHANGE);
			if (!empty($result)) $feedback['Triggers'] = $result;
			if ($status == STATUS_ON ) {
				$result = HandleTriggers($params, MONITOR_STATUS, TRIGGER_AFTER_ON);
				if (!empty($result)) $feedback['Triggers'] = $result;
			} elseif ($status == STATUS_OFF ) {
				$result = HandleTriggers($params, MONITOR_STATUS, TRIGGER_AFTER_OFF);
				if (!empty($result)) $feedback['Triggers'] = $result;
			} elseif ($status == STATUS_ERROR ){
				$result = HandleTriggers($params, MONITOR_STATUS, TRIGGER_AFTER_ERROR);
				if (!empty($result)) $feedback['Triggers'] = $result;

			}
		}
		updateStatusLog($params);
		return $feedback;
	}
	return;
}	

function GetStatusLink($params)
{
 	$deviceID = (array_key_exists('deviceID', $params) ? $params['deviceID'] : Null);

	// Only retrieve if Monitor Type = Status Monitor
	$mysql = "SELECT typeID, inuse, monitortypeID FROM `ha_mf_devices`  WHERE id = ".$deviceID. " AND (monitortypeID <> ".MONITOR_NONE.")"; 
	//if (DEBUG_HA) echo "Sql: ".$mysql.CRLF;
	if ($row = FetchRow($mysql)) {
		$feedback['deviceID'] = $deviceID;
		if ($row['monitortypeID'] == MONITOR_STATUS or $row['monitortypeID'] == MONITOR_LINK_STATUS) { 
			$mysql = "SELECT status, statusDate, commandvalue FROM `ha_mf_monitor_status`  WHERE deviceID = ".$deviceID. ""; 
			if ($rows = FetchRow($mysql)) {
				$feedback['status'] = $rows['status'];
				$feedback['commandvalue'] = $rows['commandvalue'];
			}
		}
		if ($row['monitortypeID'] == MONITOR_LINK or $row['monitortypeID'] == MONITOR_LINK_STATUS) { 
			$mysql = "SELECT link FROM `ha_mf_monitor_link`  WHERE deviceID = ".$deviceID. ""; 
			if ($rowl = FetchRow($mysql)) {
				$feedback['link'] = $rowl['link'];
			}
		}
		return $feedback;
	}
	return;
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
	if (!array_key_exists("result", $log)) $log['result'] = Null;
	if (!array_key_exists("extdata", $log)) $log['extdata'] = Null;
	if (!array_key_exists("loglevel", $log)) $log['loglevel'] = Null;
	if ($log['loglevel'] == LOGLEVEL_NONE) return true;
	if ($log['result'] === FALSE) $log['result'] = "FALSE";
	if ($log['result'] === TRUE) $log['result'] = "TRUE";
	
	$repeatcount=1;
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
		$rowdevice=FetchRow($mysql);
		$log['typeID'] = $rowdevice['typeID'];
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

function HandleTriggers($params, $monitortype, $triggertype) {
	$mysql = 'SELECT * FROM `ha_mf_monitor_triggers` ' .
			'WHERE (`deviceID` = '. $params['deviceID']. ' AND statuslink = '.$monitortype.' AND `triggertype` = '.$triggertype.')';
	$feedback =  Null; 
	
	if (DEBUG_HA) echo "Handle Triggers Params";
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
			logEvent($log = array('inout' => COMMAND_IO_BOTH, 'callerID' => $params['caller']['callerID'], 'deviceID' => $params['deviceID'], 'commandID' => COMMAND_RUN_SCHEME, 'data' => GetSchemaName($trigger['schemeID']), 'message' => $result ));
		}
	}
	return $feedback;
}


function UpdateStatusLog($params) {

//$deviceID, $status, $commandvalue = 0, $humidity = NULL, $setpoint = NULL) {

	$values['deviceID'] =  $params['deviceID'];
	$values['mdate'] = date("Y-m-d H:i:s");
	$values['status'] = $params['status'];
	$typeIDArr = getDeviceType($params['deviceID']);
	
	if (DEBUG_HA) {
		echo "<pre>UpdateStatusLog ";
		print_r ($params);
		print_r ($typeIDArr);
	}
	
	if ($typeIDArr['has_value'] || $typeIDArr['has_humidity'] || $typeIDArr['has_setpoint']) {
	        $values['temperature_c'] = (array_key_exists("commandvalue", $params)) ? $params['commandvalue'] : 0;
        	$values['humidity_r'] = (array_key_exists("humidity", $params)) ? $params['humidity'] : NULL;
	        $values['set_point'] = (array_key_exists("setpoint", $params)) ? $params['setpoint'] : NULL;
        	$sql = 'SELECT * FROM `ha_weather_current`  WHERE deviceID='.  $params['deviceID'] .' order by mdate desc limit 1';
	        if ($row = FetchRow($sql)) {
				if (DEBUG_HA) print_r($row);
				$last = strtotime($row['mdate']);
				$typeID = $typeIDArr['id'];
				if (timeExpired($last, 59) || $values['status'] <> $row['status'] ||  abs($values['temperature_c'] - $row['temperature_c']) >= 1 || abs($values['set_point'] - $row['set_point']) >= 1) {
//	echo "Inside";
						$sdate = $row['mdate'];
						$edate = $values['mdate'];
						$values['runtime'] = NULL;
						if ($typeIDArr['has_runtime']) {	
								$system = $typeIDArr['internal_type'];	// Currently support 1 = Heating, 2 = Cooling
								$values['runtime'] = 0;
								UpdateStatusCycle($params['deviceID'], false, false, false, true);                // Force cycle insert
								$sql = 'SELECT deviceID, sum( TIMESTAMPDIFF(MINUTE , start_time, end_time ) ) AS runtime
												FROM `hvac_cycles`
												WHERE deviceID ='.$params['deviceID'].' AND system ='.$system.' AND start_time >= "' .$sdate.'" AND end_time <= "' .$edate.'"
												GROUP BY deviceID, system';
								if ($row = FetchRow($sql)) {
										$values['runtime'] = $row['runtime'];
								}
						}
						// End Runtime handling
						mysql_insert_assoc ('ha_weather_current', $values);
				}
        	} else {		// First one
				mysql_insert_assoc ('ha_weather_current', $values);
		}
	}
	if (DEBUG_HA) echo "</pre>";
}




function UpdateWeatherNow($deviceID, $temp, $humidity = NULL, $set_point = NULL){
	
	$mysql = "SELECT temperature_c, humidity_r FROM ha_weather_now  WHERE deviceID = ".$deviceID; 
	$ttrend = 1;
	$htrend = 1;
	if ($row = FetchRow($mysql)) {
		$ttrend = setTrend($temp, $row['temperature_c']);
		$htrend = (!is_null($humidity) ? setTrend($humidity, $row['humidity_r']) :  "NULL");
	} 
	if (is_null($humidity)) $humidity="NULL";
	if (is_null($set_point)) $set_point="NULL";

	$devTypeID = getDeviceType($deviceID)['id'];
	//$message['typeID'] = $devType['id'];
	
	
	$weathernow = Array('deviceID' => $deviceID, 'mdate' => date("Y-m-d H:i:s"), 'temperature_c' => $temp, 'set_point' => $set_point , 
			'ttrend' => $ttrend, 'humidity_r' => $humidity, 'htrend' => $htrend, 'typeID' => $devTypeID,
			'link1' => '<i class="condensed-icon iconmoon-ascendant6" alt="Chart"></i>');
	PDOupsert("ha_weather_now", $weathernow, Array('deviceID' => $deviceID));

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

	$mysql = 'SELECT g.groupID as groupID, d.id as deviceID, typeID, inuse, monitortypeID, status, statusDate, link, invertstatus, commandvalue FROM ha_mf_device_group g 
					JOIN `ha_mf_devices` d ON g.deviceID = d.id 
					LEFT JOIN `ha_mf_monitor_status` s ON d.id = s.deviceID 
					LEFT JOIN `ha_mf_monitor_link` l ON d.id = l.deviceID 
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
?>
