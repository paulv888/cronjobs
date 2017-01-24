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
				default:
					if ((date('Y-m-d') != date('Y-m-d', $last)) || (timeExpired($last, $timer['repeat']))) {
						$doit = true;
						$last = time();
					}
					break;
				}
				if ($doit) {																	// Still good doit
					$runcount++;
					var_dump($timer['priorityID']);
					var_dump($timer['priorityID']);
					if (!DEBUG_TIMERS) ob_start();
					if ($timer['priorityID'] != PRIORITY_HIDE) {
						$message = runTimerSteps(array('callerID' => MY_DEVICE_ID, 'messagetypeID' => MESS_TYPE_SCHEME, 'timer' => $timer, 'loglevel' => LOGLEVEL_MACRO)); 
					} else {
						$message = runTimerSteps(array('callerID' => MY_DEVICE_ID, 'messagetypeID' => MESS_TYPE_SCHEME, 'timer' => $timer, 'loglevel' => LOGLEVEL_NONE));
					}
					if (!DEBUG_TIMERS) $result = ob_get_clean();
					$mysql="UPDATE `ha_timers` ".
						" SET last_run_date = '". date("Y-m-d H:i:00")."' WHERE `ha_timers`.`id` = ".$timer['id'] ;
					if (DEBUG_TIMERS) echo date("Y-m-d H:i:s").": ".$mysql.CRLF;
					executeQuery(array( 'commandvalue' => $mysql));
				}
				
			}
		}
	}
	return $runcount;
}


function runTimerSteps($params) {


	if (!array_key_exists('timer',$params))	{ // Called directly from executeCommands (Not in Use? There is a command runTimerSteps, but not used?
		$timer['runasync'] = false;
		$timer['priorityID'] = 1;
		$timer['description'] = "";
		$timerID = $params['commandvalue'];
	} else {
		if (DEBUG_TIMERS) echo "<pre>".CRLF;
		if (DEBUG_TIMERS) print_r($params);
		$timer = $params['timer'];
		$timerID = $params['timer']['id'];
	}

	$deviceID = $params['callerID'];
	//$callerparams['deviceID'] = (array_key_exists('deviceID', $callerparams) ? $callerparams['deviceID'] : $callerID);
	$mysql = 'SELECT ha_timers.description, ha_remote_scheme_steps.deviceID,
		ha_remote_scheme_steps.commandID, ha_remote_scheme_steps.value as commandvalue, ha_remote_scheme_steps.runschemeID as schemeID ,
		ha_remote_scheme_steps.alert_textID 
		FROM (ha_timers INNER JOIN ha_remote_scheme_steps ON ha_timers.id = ha_remote_scheme_steps.timerID) 
		WHERE(((ha_timers.id) = '.$timerID.')) ORDER BY ha_remote_scheme_steps.sort';
	
	$feedback['result'] = "";
	if ($timersteps = FetchRows($mysql)) {
		foreach ($timersteps as $step) {
			$description = $step['description'];
			unset($step['description']);
			$step['callerID'] = $params['callerID'];
			$step['messagetypeID'] = "MESS_TYPE_COMMAND";
			$step['loglevel'] = $params['loglevel'];
			if ($timer['runasync']) {
				$getparams = http_build_query($step, '',' ');
				$cmd = 'nohup nice -n 10 /usr/bin/php -f '.getPath().'/process.php ASYNC_THREAD '.$getparams;
				$outputfile=  tempnam( sys_get_temp_dir(), 'async' );
				$pidfile=  tempnam( sys_get_temp_dir(), 'async' );
				echo "***".sprintf("%s > %s 2>&1 & echo $! >> %s", $cmd, $outputfile, $pidfile)."****".CRLF;
				exec(sprintf("%s > %s 2>&1 & echo $! >> %s", $cmd, $outputfile, $pidfile));
				$feedback['Name'] = $description;
				$feedback['result'] .= "Spawned: ".$feedback['Name']." ".$cmd." Log:".$outputfile.'</br>';
				if ($timer['priorityID'] != PRIORITY_HIDE) logEvent($log = array('inout' => COMMAND_IO_BOTH, 'callerID' => $deviceID, 'deviceID' => $deviceID, 'commandID' => 316, 'data' => $timer['description'], 'result' => $feedback['result'] ));
				if (DEBUG_FLOW) echo "Exit Spawn Timer</pre>".CRLF;
			} else {
				$feedback['Name'] = $description;
				$feedback['result'] .= json_encode(executeCommand($step),JSON_UNESCAPED_SLASHES).'</br>';
				// No logging here, is done in Command
				//if ($timer['priorityID'] != PRIORITY_HIDE) 
//logEvent($log = array('inout' => COMMAND_IO_BOTH, 'callerID' => $deviceID, 'deviceID' => $deviceID, 'commandID' => $step['commandID'], 'data' => $timer['description'], 'result' => $feedback['result'] ));
			}
		}
		return $feedback;		// GET OUT
	} else {
		$feedback['error'] = 'No steps found!';
	}
}

function checkTime ($setupstart,$setupend, $offset) {
	// TODO:: implement dawn/dusk
	$start= $setupstart;
	if ($setupstart == TIME_DAWN) $start = getDawn();
	if ($setupstart == TIME_DUSK) $start = getDusk();
	if ($setupstart == '-') $start = "00:00:00";
	if ($setupstart == TIME_DAWN || $setupstart == TIME_DUSK || $setupstart == '-' ) {
		$start = strtotime("today $start");
		$start = $start + $offset*60;
	} else {
		$start = strtotime("today $start hours $offset minutes");
	}

	$end=$setupend;
	if ($setupend == TIME_DAWN) $end = getDawn();
	if ($setupend == TIME_DAWN) $end = getDusk();
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

function updateTimers($params) {
// PHP Command Dummy parm

	$devs = getDevicesWithProperties(Array( 'properties' => Array("Timer Date", "Timer Value", "Timer Remaining")));
	if (DEBUG_TIMERS) print_r($devs);
	
	$feedback = array();
	foreach ($devs as $key => $device) {
		if (!array_key_exists('Timer Date', $device)) $devs[$key]['Timer Date']['value']="1970-01-01";
		if (!array_key_exists('Timer Value', $device)) $devs[$key]['Timer Value']['value']=0;
		if (!array_key_exists('Timer Remaining', $device)) $devs[$key]['Timer Remaining']['value']=0;
		$timerStarted = $devs[$key]['Timer Date']['value'];
		if ($devs[$key]['Timer Value']['value'] > 0 && timeExpired($timerStarted, $devs[$key]['Timer Value']['value'])) {
			removeDeviceProperty(Array('deviceID' => $key, 'description' => 'Timer Date'));
			removeDeviceProperty(Array('deviceID' => $key, 'description' => 'Timer Value'));
			removeDeviceProperty(Array('deviceID' => $key, 'description' => 'Timer Remaining'));
			$feedback['ExecuteCommand:'.$key]=executeCommand(array('callerID' => $params['callerID'], 'messagetypeID' => MESS_TYPE_COMMAND, 'deviceID' => $key, 'commandID' => COMMAND_OFF));
		} else {
			if ($devs[$key]['Timer Value']['value'] > 0) {
				$minutes = (int)$devs[$key]['Timer Value']['value']-(int)(abs(time() - strtotime($devs[$key]['Timer Date']['value'])) / 60);
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

	$thiscommand['loglevel'] = LOGLEVEL_COMMAND;
	$thiscommand['messagetypeID'] = MESS_TYPE_COMMAND;
	$thiscommand['caller'] = $params['caller'];
	$thiscommand['commandID'] = COMMAND_ON;
	$thiscommand['timervalue'] = $params['commandvalue'];
	$thiscommand['deviceID'] = $params['deviceID'];
	$thiscommand['device']['id'] =  $params['deviceID'];
	$thiscommand['device']['properties']['Timer Date']['value'] = date("Y-m-d H:i:s");
	$thiscommand['device']['properties']['Timer Value']['value'] = $params['commandvalue'];
	$thiscommand['device']['properties']['Timer Remaining']['value'] = $params['commandvalue'];
//	echo "<pre>+++StartTimer";
//	print_r($params);
//	echo "</pre>===StartTimer";
	$feedback['SendCommand']=sendCommand($thiscommand); 
	return $feedback;
}
?>
