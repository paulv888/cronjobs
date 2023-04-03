<?php
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
		debug($timer['id']." ".$timer['description'], 'Timer:');
		$date = getdate();
		if (is_int(strpos($timer['generate_days'],(string)$date["wday"])) === true) {								// Check Day
			debug("Run Today", 'Run Today');
			if (checktime($timer['generate_start'],$timer['generate_end'], $timer['generate_offset'])) {			// Between Hours
				debug($timer['last_run_date'], 'Last Ran');
				debug("Right Time", 'Right Time');
				debug($timer['repeat'], 'Repeat');

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
					// var_dump($timer['priorityID']);
					if (!isset($GLOBALS['debug'])) ob_start();
					if ($timer['priorityID'] != PRIORITY_HIDE) {
						$message = runTimerSteps(array('callerID' => MY_DEVICE_ID, 'messagetypeID' => MESS_TYPE_SCHEME, 'timer' => $timer)); 
						// logEvent($log = array('inout' => COMMAND_IO_BOTH, 'callerID' => $deviceID, 'deviceID' => $deviceID, 'commandID' => $step['commandID'], 
									// 'data' => $timer['description'], 'result' => $feedback['result'] ));
					} else {
						$message = runTimerSteps(array('callerID' => MY_DEVICE_ID, 'messagetypeID' => MESS_TYPE_SCHEME, 'timer' => $timer, 'loglevel' => LOGLEVEL_NONE));
					}
					if (!isset($GLOBALS['debug'])) $result = ob_get_clean();
					$mysql="UPDATE `ha_timers` ".
						" SET last_run_date = '". date("Y-m-d H:i:00")."' WHERE `ha_timers`.`id` = ".$timer['id'] ;
					debug($mysql, 'mysql');
					executeQuery(array( 'commandvalue' => $mysql));
				}
			}
		}
	}
	return $runcount;
}


function runTimerSteps($params) {
	debug($params, 'params');

	if (!array_key_exists('timer',$params))	{ // Called directly from remote from form
		$timer['runasync'] = false;
		$timer['priorityID'] = PRIORITY_HIGH;
		$timer['description'] = "";
		$timerID = $params['commandvalue'];
	} else {
		$timer = $params['timer'];
		$timerID = $params['timer']['id'];
	}

	$deviceID = $params['callerID'];
	//$callerparams['deviceID'] = (array_key_exists('deviceID', $callerparams) ? $callerparams['deviceID'] : $callerID);
	$mysql = 'SELECT st.id, t.description, st.deviceID, c.description as commandName,
		st.commandID, st.value as commandvalue, st.runschemeID as schemeID, 
		st.cond_deviceID, st.has_condition as cond_type, NULL as cond_groupID, 
		"123" as cond_propertyID, st.cond_operator, st.cond_value
		FROM (ha_timers t INNER JOIN ha_remote_scheme_steps st ON t.id = st.timerID
			LEFT JOIN ha_mf_commands c ON st.commandID = c.id) 
		WHERE(((t.id) = '.$timerID.')) ORDER BY st.sort';

	//$feedback['result'] = "";
	if ($timersteps = FetchRows($mysql)) {
		foreach ($timersteps as $step) {
			$description = $step['description'];
			unset($step['description']);
			$feedback['Name'] = $description;
			$check_result = checkConditions(array($step), $params);
			if ($check_result['result'][0]) {
				$step['callerID'] = $params['callerID'];
				$step['messagetypeID'] = "MESS_TYPE_COMMAND";
                if (array_key_exists('loglevel', $params)) $step['loglevel'] = $params['loglevel'];
				$step['debug'] = (isset($GLOBALS['debug']) ? $GLOBALS['debug'] : 0);
				if ($timer['runasync']) {
					$getparams = http_build_query($step, '',' '); 
					$cmd = 'nohup nice -n 10 /usr/bin/php -f '.getPath().'/process.php ASYNC_THREAD '.$getparams;
					$outputfile=  tempnam( sys_get_temp_dir(), 'async-T'.$timerID.'-o-' );
					$pidfile=  tempnam( sys_get_temp_dir(), 'async-T'.$timerID.'-p-' );
					echo "***".sprintf("%s > %s 2>&1 & echo $! >> %s", $cmd, $outputfile, $pidfile)."****".CRLF;
					exec(sprintf("%s > %s 2>&1 & echo $! >> %s", $cmd, $outputfile, $pidfile));
					if 	(!array_key_exists('result', $feedback)) $feedback['result'] = "";
					$feedback['result'] .= "Spawned: ".$feedback['Name']." ".$cmd." Log:".$outputfile.'</br>';
					if ($timer['priorityID'] != PRIORITY_HIDE) logEvent($log = array('inout' => COMMAND_IO_BOTH, 'callerID' => $deviceID, 'deviceID' => $deviceID, 'commandID' => 316, 'data' => $timer['description'], 'result' => $feedback['result'] ));
				} else {
					$feedback['result']['runTimerSteps:'.$step['id'].'_'.$step['commandName']] = executeCommand($step);
				}
			} else {
				$feedback['result'] = 'Skipped';
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

	debug($start, 'start');
	debug($end, 'end');
	return  time() >= $start AND time() < $end; ;

}

function updateTimers($params) {
	debug($params, 'params');

	$devs = getDevicesWithProperties(Array( 'properties' => Array("Timer Date", "Timer Value", "Timer Remaining")));
	debug($devs, 'devs');

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

	$thiscommand['messagetypeID'] = MESS_TYPE_COMMAND;
	$thiscommand['caller'] = $params['caller'];
	$thiscommand['commandID'] = COMMAND_ON;
	$thiscommand['timervalue'] = $params['commandvalue'];
	$thiscommand['deviceID'] = $params['deviceID'];
	$thiscommand['device']['id'] =  $params['deviceID'];
	$thiscommand['device']['properties']['Timer Date']['value'] = date("Y-m-d H:i:s");
	$thiscommand['device']['properties']['Timer Value']['value'] = $params['commandvalue'];
	$thiscommand['device']['properties']['Timer Remaining']['value'] = $params['commandvalue'];
	$feedback['SendCommand']=sendCommand($thiscommand); 
	return $feedback;
}
?>
