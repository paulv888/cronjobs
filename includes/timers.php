<?php

define( 'DEBUG_TIMERS', TRUE );
if (!defined('DEBUG_TIMERS')) define( 'DEBUG_TIMERS', FALSE );

function RunTimers(){


// Check Active Timers
// Check If I should run, date - time
// Check If my interval is up (Every Run - Bad) / 1 Hour / 1 Day
// If all true 
// 		run schemeID
// 		log something -> Alert or Log


	// Check Active Timers
	$mysql='SELECT * FROM `ha_timers` WHERE `active` = "1"';

	if (!$timers = FetchRows($mysql)) {
		exit;
	}

	$runcount = 0;

	foreach ($timers as $timer) {
		// check if we are ready to generate
		if (DEBUG_TIMERS) echo date("Y-m-d H:i:s").": "."Timer: ".$timer['id']." ".$timer['description'].CRLF;
		$date = getdate();
		if (is_int(strpos($timer['generate_days'],(string)$date["wday"])) === true) {								// Check Day
			if (DEBUG_TIMERS) echo date("Y-m-d H:i:s").": "."Run Today".CRLF;
			if (checktime($timer['generate_start'],$timer['generate_end'], $timer['generate_offset'])) {			// Between Hours

				if (DEBUG_TIMERS) echo date("Y-m-d H:i:s").": "."Last Run ".$timer['last_run_date'].CRLF;
				if (DEBUG_TIMERS) echo date("Y-m-d H:i:s").": "."Right Time".CRLF;
				if (DEBUG_TIMERS) echo date("Y-m-d H:i:s").": "."Repeat ".$timer['repeat'].CRLF;

				$doit = false;
				$last = strtotime($timer['last_run_date']);
				switch ($timer['repeat']) {
				case REPEAT_ONCE_DAY: 					// Did it run today?
					if (date('Y-m-d') != date('Y-m-d', $last)) {
						$doit = true;
						$last = time();
					}
					break;
				case REPEAT_ONCE_HOUR: 					// If not run today then run, else check hour expired 
					if ((date('Y-m-d') != date('Y-m-d', $last)) || (timeExpired($last, $timer['repeat']))) {
						$doit = true;
						$last = time();
					}
					break;
				default:
					if (timeExpired($last, $timer['repeat'])) {
						$doit = true;
					}
					break;
				}
				if ($doit) {																	// Still good doit
					$runcount++;
					if (!DEBUG_TIMERS) ob_start();
					if ($timer['priorityID'] != '99') {
						$message = executeCommand(MY_DEVICE_ID, MESS_TYPE_SCHEME, array( 'schemeID' => $timer['schemeID'], 'loglevel' => LOGLEVEL_MACRO)); 
					} else {
						$message = executeCommand(MY_DEVICE_ID, MESS_TYPE_SCHEME, array( 'schemeID' => $timer['schemeID'], 'loglevel' => LOGLEVEL_NONE));
					}
					if (!DEBUG_TIMERS) $result = ob_get_clean();
					if ($timer['priorityID'] != '99') logEvent($log = array('inout' => COMMAND_IO_BOTH, 'callerID' => MY_DEVICE_ID, 'deviceID' => MY_DEVICE_ID, 'commandID' => COMMAND_RUN_SCHEME, 'data' => GetSchemaName($timer['schemeID']), 'message' => $message ));
					$mysql="UPDATE `ha_timers` ".
						" SET last_run_date = '". date("Y-m-d H:i:s")."' WHERE `ha_timers`.`id` = ".$timer['id'] ;
					if (DEBUG_TIMERS) echo date("Y-m-d H:i:s").": ".$mysql.CRLF;
					RunQuery($mysql);
				}
				
			}
		}
	}
	return $runcount;
}

function checktime ($setupstart,$setupend, $offset) {
	// TODO:: implement dawn/dusk
	$start= $setupstart;
	if ($setupstart == TIME_DAWN) $start = GetDawn();
	if ($setupstart == TIME_DUSK) $start = GetDusk();
	if ($setupstart == '-') $start = "00:00:00";
	if ($setupstart == TIME_DAWN || $setupstart == TIME_DUSK || $setupstart == '-' ) {
		$start = strtotime("today $start");
		$start = $start + $offset*60;
	} else {
		$start = strtotime("today $start hours $offset minutes");
	}

	$end=$setupend;
	if ($setupend == TIME_DAWN) $end = GetDawn();
	if ($setupend == TIME_DAWN) $end = GetDusk();
	if ($setupend == '-' or $setupend == '00') $end = "23:59:59";	
	if ($setupend == TIME_DAWN || $setupend == TIME_DUSK || $setupend == '-' or $setupend == '00') {
		$end = strtotime("today $end");
	} else {
		$end = strtotime("today $end hours $offset minutes");
	}
	
	if (DEBUG_TIMERS) echo date("Y-m-d H:i:s").": "."Start: ".date("Y-m-d H:i:s", $start).CRLF;
	if (DEBUG_TIMERS) echo date("Y-m-d H:i:s").": "."End  : ".date("Y-m-d H:i:s", $end).CRLF;
	
	return  time() >= $start AND time() < $end; ;
	
}

function GetSchemaName($schemaID) {
	$schemarow = FetchRow("SELECT name FROM ha_remote_schemes WHERE id = ".$schemaID);
	return $schemarow['name'];
}


function UpdateTimers($dummy) {
// PHP Command Dummy parm
	$devstatusrows = FetchRows("SELECT deviceID, timerMinute, timerDate, timerRemaining FROM ha_mf_monitor_status  WHERE timerMinute > 0");
	//if (DEBUG_TIMERS) print_r($devstatusrows);
	$feedback = "";
	if ($devstatusrows) {
		foreach ($devstatusrows as $devstatusrow) {
			if (DEBUG_TIMERS) print_r($devstatusrow);
			if ($testvalue[] = $devstatusrow['timerMinute'] > 0 && timeExpired($devstatusrow['timerDate'], $devstatusrow['timerMinute'])) {
				$feedback['SendCommand:'.$devstatusrow['deviceID']]=executeCommand(MY_DEVICE_ID, MESS_TYPE_COMMAND, array( 'deviceID' => $devstatusrow['deviceID'], 'commandID' => COMMAND_OFF));
			} else {
				if ($devstatusrow['timerMinute'] > 0) {
					$minutes = $devstatusrow['timerMinute']-(int)(abs(time()-$devstatusrow['timerDate']) / 60);
					RunQuery('UPDATE ha_mf_monitor_status SET timerRemaining = '.$minutes.' WHERE deviceID = '.$devstatusrow['deviceID']);
				}
			}
		}
	}
	return $feedback;
}

function StartTimer($callerID, $deviceID, $time) {

	$feedback['SendCommand']=SendCommand($callerID, array( 'deviceID' => $deviceID, 'commandID' => COMMAND_ON, 'timervalue' => $time));
	RunQuery('UPDATE `ha_mf_monitor_status` SET  `timerMinute` =  '.$time.' , `timerRemaining` = '.$time.', timerDate = NOW() WHERE  `ha_mf_monitor_status`.`deviceID` = '.$deviceID);
	/*echo "<pre>";
	echo 'UPDATE `ha_mf_monitor_status` SET  `timerMinute` =  '.$time.' , `timerRemaining` = '.$time.', timerDate = NOW() WHERE  `ha_mf_monitor_status`.`deviceID` = '.$deviceID.CRLF;
	$a = FetchRow('SELECT `timerMinute` , `timerRemaining` , timerDate FROM `ha_mf_monitor_status` WHERE  `ha_mf_monitor_status`.`deviceID` = '.$deviceID);
	print_r($a);
	echo "</pre>";
*/
	return $feedback;
}
?>
