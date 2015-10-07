<?php

define( 'DEBUG_TIMERS', TRUE );
if (!defined('DEBUG_TIMERS')) define( 'DEBUG_TIMERS', FALSE );
if (!defined('ASYNC_THREAD')) define( 'ASYNC_THREAD', false);

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
		if (DEBUG_TIMERS) echo CRLF.date("Y-m-d H:i:s").": "."Timer: ".$timer['id']."<B>".$timer['description']."</B>".CRLF;
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
				case REPEAT_EVERY_RUN:
					$doit = true;
					break;
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
					var_dump($timer['priorityID']);
					var_dump($timer['priorityID']);
					if (!DEBUG_TIMERS) ob_start();
					if ($timer['priorityID'] != PRIORITY_HIDE) {
						$message = runSteps(array('callerID' => MY_DEVICE_ID, 'messagetypeID' => MESS_TYPE_SCHEME, 'timerID' => $timer['id'], 'loglevel' => LOGLEVEL_MACRO)); 
					} else {
						$message = runSteps(array('callerID' => MY_DEVICE_ID, 'messagetypeID' => MESS_TYPE_SCHEME, 'timerID' => $timer['id'], 'loglevel' => LOGLEVEL_NONE));
					}
					if (!DEBUG_TIMERS) $result = ob_get_clean();
					if ($timer['priorityID'] != PRIORITY_HIDE) logEvent($log = array('inout' => COMMAND_IO_BOTH, 'callerID' => MY_DEVICE_ID, 'deviceID' => MY_DEVICE_ID, 'commandID' => 316, 'data' => $timer['description'], 'message' => $message ));
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


function runSteps($params) {

	if (DEBUG_TIMERS) echo "<pre>".CRLF;
	if (DEBUG_TIMERS) print_r($params);

	//$callerparams['deviceID'] = (array_key_exists('deviceID', $callerparams) ? $callerparams['deviceID'] : $callerID);
	$mysql = 'SELECT ha_timers.description, ha_remote_scheme_steps.deviceID,
		ha_remote_scheme_steps.commandID, ha_remote_scheme_steps.value as commandvalue, ha_remote_scheme_steps.runschemeID as schemeID ,
		ha_remote_scheme_steps.alert_textID 
		FROM (ha_timers INNER JOIN ha_remote_scheme_steps ON ha_timers.id = ha_remote_scheme_steps.timerID) 
		WHERE(((ha_timers.id) = '.$params['timerID'].')) ORDER BY ha_remote_scheme_steps.sort';
	
	if ($timersteps = FetchRows($mysql)) {
		foreach ($timersteps as $step) {
			// All Async, no option
			$description = $step['description'];
			unset($step['description']);
			$step['callerID'] = $params['callerID'];
			$step['messagetypeID'] = "MESS_TYPE_COMMAND";
			$step['loglevel'] = $params['loglevel'];
			$getparams = http_build_query($step, '',' ');
			$cmd = 'nohup nice -n 10 /usr/bin/php -f '.getPath().'process.php ASYNC_THREAD '.$getparams;
			$outputfile=  tempnam( sys_get_temp_dir(), 'async' );
			$pidfile=  tempnam( sys_get_temp_dir(), 'async' );
			exec(sprintf("%s > %s 2>&1 & echo $! >> %s", $cmd, $outputfile, $pidfile));
			$feedback['TimerName'] = $description;
			$feedback['message'] = "Spawned: ".$feedback['TimerName']." ".$cmd." Log:".$outputfile;
			if (DEBUG_FLOW) echo "Exit Spawn Timer</pre>".CRLF;
			return $feedback;		// GET OUT
		}
	} else {
		$feedback['message'] = 'No steps found!';
	}
}

function checkTime ($setupstart,$setupend, $offset) {
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

function updateTimers($dummy) {
// PHP Command Dummy parm

	$devs = getDevicesWithProperties(Array( 'properties' => Array("Timer Date", "Timer Value", "Timer Remaining")));
	
	$feedback = "";
	foreach ($devs as $key => $device) {
		//if (DEBUG_TIMERS) print_r($device);
		if (!array_key_exists('Timer Date', $device)) $devs[$key]['Timer Date']['value']="1970-01-01";
		if (!array_key_exists('Timer Value', $device)) $devs[$key]['Timer Value']['value']=0;
		if (!array_key_exists('Timer Remaining', $device)) $devs[$key]['Timer Remaining']['value']=0;
		$timerStarted = $device['Timer Date']['value'];
		if ($testvalue[] = $device['Timer Value']['value'] > 0 && timeExpired($timerStarted, $device['Timer Value']['value'])) {
			removeDeviceProperty(Array('deviceID' => $key, 'description' => 'Timer Date'));
			removeDeviceProperty(Array('deviceID' => $key, 'description' => 'Timer Value'));
			removeDeviceProperty(Array('deviceID' => $key, 'description' => 'Timer Remaining'));

			$feedback['ExecuteCommand:'.$key]=executeCommand(array('callerID' => 'MY_DEVICE_ID', 'messagetypeID' => MESS_TYPE_COMMAND, 'deviceID' => $key, 'commandID' => COMMAND_OFF));
			//echo 'ExecuteCommand:'.$key.'-----'.executeCommand(array('callerID' => 'MY_DEVICE_ID', 'messagetypeID' => MESS_TYPE_COMMAND, 'deviceID' => $key, 'commandID' => COMMAND_OFF));
		} else {
			if ($device['Timer Value']['value'] > 0) {
				$minutes = (int)$device['Timer Value']['value']-(int)(abs(time() - strtotime($device['Timer Date']['value'])) / 60);
				$deviceproperty['propertyID'] = getProperty('Timer Remaining')['id'];
				$deviceproperty['deviceID'] = $key;
				$deviceproperty['value'] = $minutes;
				PDOupsert('ha_mf_device_properties', $deviceproperty, Array('deviceID' => $deviceproperty['deviceID'], 'propertyID' => $deviceproperty['propertyID'] ));
			}
		}
	}
	return $feedback;
}


function startTimer($params) {

	// echo "<pre>+++StartTimer";
	// print_r($params);
	// echo "</pre>===StartTimer";
	$thiscommand['loglevel'] = LOGLEVEL_COMMAND;
	$thiscommand['messagetypeID'] = MESS_TYPE_COMMAND;
	$thiscommand['caller'] = $params['caller'];
	$thiscommand['commandID'] = COMMAND_ON;
	$thiscommand['timervalue'] = $params['commandvalue'];
	$thiscommand['deviceID'] = $params['deviceID'];
	$thiscommand['device']['properties']['Timer Date']['value'] = date("Y-m-d H:i:s");
	$thiscommand['device']['properties']['Timer Value']['value'] = $params['commandvalue'];
	$thiscommand['device']['properties']['Timer Remaining']['value'] = $params['commandvalue'];
	$feedback['SendCommand']=sendCommand($thiscommand); 
	return $feedback;
}
?>
