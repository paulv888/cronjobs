<?php
define( 'DEBUG_HA', FALSE );
//define( 'DEBUG_HA', TRUE );

function ReloadScreenShot() {
	$url = 'http://htpc:8085/HipScreenShot.jpg';
	$img = 'images/HIPScreenshot.jpg';
	file_put_contents($img, file_get_contents($url));
	$post = RestClient::post('http://htpc:8085/index.htm');
	return;  // ReadCurlReturn($post);
}

function UpdateLink($deviceid, $link = LINK_UP, $callsource = 0, $commandid = 0){

    if (DEBUG_HA) echo "UpdateLink Deviceid ".$deviceid."</br>\n";

	$mysql = "SELECT * FROM `ha_mf_monitor_link` WHERE deviceID = ".$deviceid; 
	if ($row = FetchRow($mysql)) {
		$prevlink = $row['link'];
			// Listen for Statuses StatusOff StatusOn
		if ( $row['linkmonitor'] == "INTERNAL" || 
		     $row['linkmonitor'] == "POLL" || 
		    ($row['linkmonitor'] == "MONSTAT" && ($row['listenfor1'] == $commandid || $row['listenfor2'] == $commandid || $row['listenfor3'] == $commandid))) { 				
			if ($prevlink != $link) { 					// status changed
				if ($prevlink == LINK_UP) { 			// Link was up, wait for time_out time before acknowledge as down
					if ($row['link_timeout'] != Null) {
						$temp = explode(" ", $row['link_timeout']);
						$temp2 = explode(":", $temp[1]);
						$min = $temp[0]*1440 + $temp2[0]*60 + $temp2[1];
						$last = strtotime($row['mdate']);
						$nowdt = strtotime(date("Y-m-d H:i:s"));
						//echo $row['mdate']."</br>\n";
						//echo date("Y-m-d H:i:s")."</br>\n";
						//echo abs($nowdt-$last) / 60 . "min</br>\n";
					}
					if ((abs($nowdt-$last) / 60 > $min) || $row['link_timeout'] == Null) {
						echo "Down, Previous was up; Time expired"."</br>\n";
						logEvent($log = Array ('inout' => COMMAND_SEND, 'sourceID' => $callsource, 'deviceID' => $deviceid, 'commandID' => $commandid, 'data' => ($link == LINK_UP ? "Up" : "Down")));
						$mysql = "Update `ha_vw_monitor_combined` Set " .
								  " `mdate` = '" . date("Y-m-d H:i:s") . "'," .
								  " `link` = '" . $link . "'" .
								  " Where(`deviceid` ='" . $deviceid . "')";
						if (!mysql_query($mysql)) mySqlError($mysql);
						if ($row['on_change']) {
							echo process(SIGNAL_SOURCE_STATUS_LINK_UPDATE, "", $row['on_change'])."\n";
						}
					} else {
						echo "Down, Previous was up; Time NOT expired"."</br>\n";
					}
				} else {   								// previous was down update to online and log event
					echo "Up, Previous was down; Update to online and log event"."</br>\n";
					logEvent($log = Array ('inout' => COMMAND_SEND, 'sourceID' => $callsource, 'deviceID' => $deviceid, 'commandID' => $commandid, 'data' => ($link == LINK_UP ? "Up" : "Down")));
					$mysql = "Update `ha_vw_monitor_combined` Set " .
							  " `mdate` = '" . date("Y-m-d H:i:s") . "'," .
							  " `link` = '" . $link . "'" .
							  " Where(`deviceid` ='" . $deviceid . "')";
					if (!mysql_query($mysql)) mySqlError($mysql);
					if ($row['on_change']) {
						echo process(SIGNAL_SOURCE_STATUS_LINK_UPDATE, "", $row['on_change'])."\n";
					}
				}
			} else {
				if ($link == LINK_UP) { 			// Link is up, update time
					echo "Up and same as prev status only update time"."</br>\n";
					$mysql = "Update `ha_vw_monitor_combined` Set " .
							  " `mdate` = '" . date("Y-m-d H:i:s") . "'" .
							  " Where(`deviceid` ='" . $deviceid . "')";
					if (!mysql_query($mysql)) mySqlError($mysql);
				} else {
					echo "Down and same as prev status, Do nothing.</br>\n";
				}
			}
		return 1;
		}
	} 
	return 0;
}

function UpdateThermType($deviceid, $typeid){

	$mysql = "Update `ha_mf_devices` Set " .
    			  " `typeID` = " . $typeid . "" .
				  " Where(`id` ='" . $deviceid . "')";
	if (!mysql_query($mysql)) mySqlError($mysql);
	$mysql = "Update `ha_weather_now` Set " .
    			  " `typeID` = " . $typeid . "" .
				  " Where(`deviceID` ='" . $deviceid . "')";
	if (!mysql_query($mysql)) mySqlError($mysql);
	return 1;
}

function setTrend($new, $old) {
	if ( $new == $old ) return 0;
	if ( $new > $old )  return 1;
	if ( $new < $old )  return 2;
}

function UpdateWeatherNow($deviceid,$temp, $humidity, $set_point){
	
	$mysql = "SELECT temperature_c, humidity_r FROM ha_weather_now  WHERE deviceID = ".$deviceid; 
	if ($row = FetchRow($mysql)) {
		$ttrend = setTrend($temp, $row['temperature_c']);
		$htrend = setTrend($humidity, $row['humidity_r']);
	}
	
	$mysql = "UPDATE ha_weather_now SET mdate = '". gmdate("Y-m-d H:i:s")."'," .
				" temperature_c = ". $temp ." , set_point = ". $set_point . ", ttrend = ".$ttrend.", humidity_r = ".$humidity.", htrend = ".$htrend."  WHERE deviceID = ".$deviceid;

	if (!mysql_query($mysql)) mySqlError($mysql);
}
			
function UpdateStatus ($deviceid, $commandid = NULL, $callsource = 0, $status = NULL) 
{

	// Interpret status value based on current command, i.e. On/Off/Error
	if (DEBUG_HA) echo "Status commandid:".$commandid."</br>\n";
	if (DEBUG_HA) echo "Status deviceid:".$deviceid."</br>\n";
	if ($commandid != NULL) {
		$rescommands = mysql_query("SELECT status FROM ha_mf_commands WHERE ha_mf_commands.id =".$commandid);
		if ($rowcommands = mysql_fetch_array($rescommands)) {
			$status = $rowcommands['status'];
	if (DEBUG_HA) echo "Status Status:".$status."</br>\n";
			if ($status == STATUS_NOT_DEFINED)  return;
		} else {
			return;
		}
	}
	
	$now = date( 'Y-m-d H:i:s' );
	// Only retrieve if Monitor Type = Status Monitor
	$mysql = "SELECT typeID, inuse, monitortypeID, deviceID, status, statusDate, on_change, invertstatus FROM `ha_mf_devices` d " . 
				" JOIN `ha_mf_monitor_status` s ON d.id = s.deviceID ". 
				" WHERE deviceID = ".$deviceid. " AND (monitortypeID = ".MONITOR_STATUS. " OR monitortypeID = ".MONITOR_LINK_STATUS.")"; 
	if ($row = FetchRow($mysql)) {
		if ($row['invertstatus'] == 0) {
			if (DEBUG_HA) echo "Status Invert"."</br>\n";
			if ($status == STATUS_ON) {
				$status = STATUS_OFF;
			} elseif ($status == STATUS_OFF) {
				$status = STATUS_ON;
			}
		}
		if (DEBUG_HA) echo "Status Status2:".$status."</br>\n";
		if ($row['status'] != $status ) {
			// update before scheme to reduce race condition with logger
			$mysql = "UPDATE ha_vw_monitor_combined SET status = " . $status . ", statusDate = '". $now . "' WHERE deviceID = ".$deviceid;
			if (!mysql_query($mysql)) mySqlError($mysql);
			// run on change
			if ($row['on_change']) {
				//echo "RunScheme". $row['on_change']."\n";
				echo process(SIGNAL_SOURCE_STATUS_LINK_UPDATE, "", $row['on_change'])."\n";
			}
		} else {				// not sure why i wnated to update status != previous.. anyway breaks mon status timeout
			// $mysql = "UPDATE ha_vw_monitor_combined SET status = " . $status . ", statusDate = '". $now . "' WHERE deviceID = ".$deviceid;
			// if (!mysql_query($mysql)) mySqlError($mysql);
		}
	}
	return $status;
}	

function udate($format, $utimestamp = null)
{
    if (is_null($utimestamp))
        $utimestamp = microtime(true);

    $timestamp = floor($utimestamp);
    $milliseconds = round(($utimestamp - $timestamp) * 1000000);

    return date(preg_replace('`(?<!\\\\)u`', $milliseconds, $format), $timestamp);
}

function logEvent($log) {

	$repeatcount=1;

	//
	// Check for repeat statussues
	//
	$gmttime = gmdate("Y-m-d H:i:s");
	$ms = udate("u");

	$log['ip']=(!empty($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : NULL);
	if (!array_key_exists("deviceID", $log)) $log['deviceID'] = Null;
	if (!array_key_exists("commandID", $log)) $log['commandID'] = COMMAND_UNKNOWN;
	if (!array_key_exists("inout", $log)) $log['inout'] = Null;
	if (!array_key_exists("sourceID", $log)) $log['sourceID'] = Null;
	if (!array_key_exists("repeatcount", $log)) $log['repeatcount'] = 1;
	if (!array_key_exists("data", $log)) $log['data'] = Null;
	if (!array_key_exists("result", $log)) $log['result'] = Null;
	if (!array_key_exists("extdata", $log)) $log['extdata'] = Null;
	if ($log['result'] === FALSE) $log['result'] = "FALSE";
	if ($log['result'] === TRUE) $log['result'] = "TRUE";
	
	$mysql = 'SELECT * FROM `ha_events` ' .
			' WHERE `inout` = '.$log['inout']. ' AND `sourceID` ='.$log['sourceID'].' AND commandID = '.$log['commandID'].
			' AND  DATE_ADD(`mdate`,INTERVAL 10 SECOND) > "'.$gmttime.'"';

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
			$mysql='SELECT `id`, `typeID`, `invertstatus` FROM `ha_mf_devices` WHERE `id` ='.$log['deviceID'];
			$resdevice=mysql_query($mysql);
			$rowdevice=mysql_fetch_array($resdevice);
			$log['typeID'] = $rowdevice['typeID'];
			if ($rowdevice['invertstatus'] == 0) $log['extdata'] = "Inverted ".$log['extdata']; 
		}
		
		$log['mdate'] = $gmttime;
		$log['milliseconds'] = udate("u");

		$mysql='SELECT `loglevel` FROM `ha_mf_commands` WHERE `id` ='.$log['commandID'];
		$rescommand=mysql_query($mysql);
		$rowcommand=mysql_fetch_array($rescommand);
		if (!is_null($rowcommand['loglevel'])) $log['logLevel'] = $rowcommand['loglevel'];
		if (!array_key_exists("logLevel",$log )) $log['logLevel'] = LOGLEVEL_NORMAL;
			
		mysql_insert_assoc ("ha_events", $log);
	}
}
?>