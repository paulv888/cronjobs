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
	$mysql='SELECT * FROM `ha_timers_dd` WHERE `active` = "1"';

	if (!$timers = FetchRows($mysql)) {
		exit;
	}

	$runcount = 0;

	foreach ($timers as $timer) {
		// check if we are ready to generate
		if (DEBUG_TIMERS) echo "Timer: ".$timer['id']." ".$timer['description'].CRLF;
		$date = getdate();
		if (is_int(strpos($timer['generate_days'],(string)$date["wday"])) === true) {								// Check Day
			if (DEBUG_TIMERS) echo "Run Today".CRLF;
			if (checktime($timer['generate_start'],$timer['generate_end'], $timer['generate_offset'])) {			// Between Hours

				if (DEBUG_TIMERS) echo "Last Run ".$timer['last_run_date'].CRLF;
				if (DEBUG_TIMERS) echo "Right Time".CRLF;
				if (DEBUG_TIMERS) echo "Repeat ".$timer['repeat'].CRLF;

				$doit = false;
				$last = strtotime($timer['last_run_date']);
				if ($timer['repeat'] == REPEAT_ONCE_DAY) {										// Check interval expired
					if (date('Y-m-d') != date('Y-m-d', $last)) {
						$doit = true;
						$last = time();
					}
				} else {
					if (timeExpired($last, $timer['repeat'])) {
						$doit = true;
					}
				
				}
				if ($doit) {																	// Still good doit
					$runcount++;
					if (!DEBUG_TIMERS) ob_start();
					$message = executeCommand(MY_DEVICE_ID, MESS_TYPE_SCHEME, array( 'schemeID' => $timer['schemeID'])); 
					if (!DEBUG_TIMERS) $result = ob_get_clean();
					logEvent($log = Array ('inout' => COMMAND_IO_BOTH, 'callerID' => MY_DEVICE_ID, 'deviceID' => MY_DEVICE_ID, 'commandID' => COMMAND_RUN_SCHEME, 
								'result' => $result, 'message' => $message, 'loglevel' => LOGLEVEL_DEBUG ));
					
					$mysql="UPDATE `ha_timers_dd` ".
						" SET last_run_date = '". date("Y-m-d H:i:s")."' WHERE `ha_timers_dd`.`id` = ".$timer['id'] ;
					if (DEBUG_TIMERS) echo $mysql."</br>";
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
	if ($setupstart == TIME_DAWN) $start = "6:30";
	if ($setupstart == TIME_DUSK) $start = "17:30";
	if ($setupstart == '-') $start = "00:00:00";
	if ($setupstart == TIME_DAWN || $setupstart == TIME_DUSK || $setupstart == '-' ) {
		$start = strtotime("today $start");
	} else {
		$start = strtotime("today $start hours $offset minutes");
	}

	$end=$setupend;
	if ($setupend == TIME_DAWN) $end = "6:45";
	if ($setupend == TIME_DAWN) $end = "17:45";
	if ($setupend == '-' or $setupend == '00') $end = "23:59:59";	
	if ($setupend == TIME_DAWN || $setupend == TIME_DUSK || $setupend == '-' or $setupend == '00') {
		$end = strtotime("today $end");
	} else {
		$end = strtotime("today $end hours $offset minutes");
	}
	
	if (DEBUG_TIMERS) echo "Start: ".date("Y-m-d H:i:s", $start).CRLF;
	if (DEBUG_TIMERS) echo "End  : ".date("Y-m-d H:i:s", $end).CRLF;
	
	return  time() >= $start AND time() < $end; ;
	
}
?>
