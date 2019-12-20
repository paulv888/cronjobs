#!/usr/bin/php
<?php
require_once 'includes.php';



define("MY_DEVICE_ID", 137);

if (isset($argv)) {
	var_dump($argv);
	foreach ($argv as $arg) {
		$e=explode("=",$arg);
        if(count($e)==2) {
			$_GET[$e[0]]=urldecode($e[1]);
		} 
	}
}


if (isset($_GET['ONLINE_DEBUG'])) {
	echo date("Y-m-d H:i:s").": trying to connect to VLOSITE:3333".CRLF;
	echo date("Y-m-d H:i:s").": send a command with nc -l 3333".CRLF;
	define("INSTEON_HUB_IP", "vlosite");
	define("INSTEON_HUB_PORT", 3333);
	define("DEBUG_MODE", true);
} else {
	define("INSTEON_HUB_DEVICE", "115");
	define("DEBUG_MODE", false);
} 

// define("DEBUG_MODE", true);
// $GLOBALS['debug'] = 1;

if (!DEBUG_MODE) {
	class console{
		public static function log($msg, $arr=array()){
			$str = vsprintf($msg, $arr);
			fprintf(STDERR, "$str".CRLF);
		}
	}

	declare(ticks=1); // PHP internal, make signal handling work

	pcntl_signal(SIGTERM, "signal_handler");
	pcntl_signal(SIGHUP, "signal_handler");
	pcntl_signal(SIGINT, "signal_handler");

//	pcntl_signal_dispatch();
	console::log("Signal dispatched");

}

$errors = 0;

echo "Initialize...".CRLF;
while (true) {

	$last = strtotime(date("Y-m-d H:i:s"));
	updateDLink(MY_DEVICE_ID);
	
	if ($device = getDevice(INSTEON_HUB_DEVICE)) {

		$page = 'buffstatus.xml';
		$device['connection']['page'] = $page;
		$url = setURL(array('device' => $device));

		$page = '/1?XB=M=1';
		$device['connection']['page'] = $page;
		$clearurl = setURL(array('device' => $device), $page);
	
	}  else {
			echo "Error: Insteon device not found".CRLF;
			exit();
	}


	$last_buff_len = 0;
	$last_result = "0000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000";
	$max_buff_len = strlen($last_result);
	$processed = "";
	$mybuffer = "";

	// Clear buffer for a fresh start
	$curl = restClient::get($clearurl,null, setAuthentication($device), $device['connection']['timeout']);
	$inst_coder = new InsteonCoder();
	
	while (true) {
		
		usleep(250000); // 250ms
		// echo date("Y-m-d H:i:s")." Start curl:".$url.CRLF;
		$curl = restClient::get($url,null, setAuthentication($device),  $device['connection']['timeout']);
		if ($curl->getresponsecode() != 200 && $curl->getresponsecode() != 204) {
			// handle error?
			echo date("Y-m-d H:i:s")." ".$curl->getresponsecode().": ".$curl->getresponse().CRLF;
		} else {
			preg_match('#<BS>(.*?)</BS>#', $curl->getresponse(), $matches);
			$inbuffer = substr($matches[0],  4, strlen($matches[0]) -9 );
			$buff_len = hexdec(substr($inbuffer, -2));
			if ( $inbuffer != $last_result) {		// Something changed, content or length
				
				//
				// Sub what we already processed, cannot rely on counter bc auto empty
				//
				debug("Input buffer         :".$inbuffer, 'Input');
				debug("Left Processed length:".substr($inbuffer, 0, strlen($processed)), 'Left');
				debug("Actual processed     :".$processed, 'processed');
				if (substr($inbuffer, 0, strlen($processed)) == $processed) {							  // Start position or received additional 
					$mybuffer .= substr($inbuffer, strlen($processed), $buff_len - strlen($processed));   // Left part the same, cut off
					$end_buffer_len = 0;
					debug("Left = Proc, Remaindr:".$mybuffer, 'Remaindr');
				} else {
					// 2 Scenario's 
					//		Normal circle -> grab end and beginning
					//		A command was send and we want to ignore and since f.cking insteon reset our buffer
						
					// Normal 
					if (substr($inbuffer, strlen($processed)  , 2) == "02" ) {
						$mybuffer .= substr($inbuffer, strlen($processed) , $max_buff_len - strlen($processed) - 2 );
						$end_buffer_len = $max_buff_len - strlen($processed) - 2 ;
					}
					debug("Left <> Proc, grab end:".$mybuffer, 'grab end');

					//= preg_replace('/^00+/', '02', $mybuffer);
					debug("end_buffer_len: $end_buffer_len", 'buffer length');
					$mybuffer .= substr($inbuffer, 0 , $buff_len );
					$processed = "";

					debug("Left <> Prc, add start:".$mybuffer, 'add start');

					// Was command send and do we only want to get the start
					//  Remove leading zero's
				}

				// Incoming commands buffer keeps growing.
				// On sending a command, buffer is auto cleared HOW TO RECOGNIZE?
				debug("Buffer Length:".$buff_len, 'Buffer Length');
				debug("->".$mybuffer, 'buffer');
				$last_buff_len = $buff_len;
				$last_result = $inbuffer;
				
				//
				// Lets decode all of the result (if possible, reset or keep if not)
				//
				do {
					$plm_decode_result = $inst_coder->plm_decode($mybuffer);
					
					// check for to short for PLM message, if so save result for rest
					// if (!array_key_exists("etdata", $plm_decode_result)) $plm_decode_result['extdata'] = "";
					if ($plm_decode_result['length'] == ERROR_MESSAGE_TO_SHORT) {  							// leave result and wait for more
						echo "\e[31mERROR_MESSAGE_TO_SHORT"." Empty mybuffer, refill from buffer\e[39;49m".CRLF;
						$mybuffer = "";
						// Need to make sure to not get stuck, retry and discard input buffer
						//exit;
					} elseif ($plm_decode_result['length'] == ERROR_STX_MISSING) {									// basically to short as well
						echo "\e[31mERROR_STX_MISSING"." Start nibbling to catch up\e[39;49m".CRLF;
						// $mybuffer = "";									// Clear result padding
						// Need to handle these, for now start over
						continue 3;
					} elseif ($plm_decode_result['length'] <= -3) {									// basically to short as well
						echo "\e[31mERROR_UNKNOWN_MESSAGE"." Do something\e[39;49m".CRLF;
						// $mybuffer = "";									// Clear result padding
						//
						//
						// Need to handle these, for now start over
						continue 3;
					} else {
						// if ($plm_decode_result['loglevel'] != LOGLEVEL_NONE)  $addMessage($plm_decode_result);
						storeMessage($plm_decode_result);
						$processed .= substr($mybuffer, $end_buffer_len, $plm_decode_result['length']);	
						$mybuffer = substr($mybuffer,$plm_decode_result['length']);	
						debug("%%%%%%%:".$mybuffer, 'buffer');
					}
				} while ($plm_decode_result['length']>0 && strlen($mybuffer>0));
			}
		} // end else process successful/ call
		// Update My Link 
		if (timeExpired($last, 15)) updateDLink(MY_DEVICE_ID);
	} // End while (true)
}


function storeMessage($plm_decode_result) {

        $exectime = -microtime(true);

	$message['inout'] = $plm_decode_result['inout'];
	$message['code'] = "I";
	if (array_key_exists("from",$plm_decode_result)) {
		$message['unit'] = strtoupper($plm_decode_result['from']);
	} elseif (array_key_exists("to",$plm_decode_result)) {
		$message['unit']  = strtoupper($plm_decode_result['to']);
	} else {
		$message['unit'] = NULL;
	}
	$properties = Array();
	if (array_key_exists("insteon",$plm_decode_result)) {
		$insteon = $plm_decode_result['insteon'];
		if (array_key_exists("data",$insteon )) $message['data'] = $insteon['data'];
		$message['commandID'] = $insteon['commandID'];
		if (array_key_exists('status', $insteon )) {
			$properties['Status']['value'] = ($insteon['status'] == "0" ? STATUS_OFF : STATUS_ON);
		}
		if (array_key_exists("commandvalue",$insteon )) {
			$properties['Level']['value'] = round(100/255*$insteon['commandvalue']);
		}
	}
	$message ['plmcmdID'] = $plm_decode_result['plmcmdID'];
	$message ['commandstr'] = $plm_decode_result['plm_string'];
	$message ['loglevel'] = $plm_decode_result['loglevel'];
	//
	// Better make sure this is all quick, need to get the next buffer
	//
	$message['callerID'] = MY_DEVICE_ID;
	if ($message['plmcmdID'] == '027F') { 		// If 027F (Hub keep alive?)
		$message['deviceID'] = INSTEON_HUB_DEVICE;
	}
	if (is_null($deviceID = setDeviceID($message))) { 		// No device founds so use my_id as deviceID
//		$message['deviceID'] = MY_DEVICE_ID;
	}

	//echo date("Y-m-d H:i:s")." \e[32mStoring message: \e[39;49m\n";
	if (!array_key_exists('commandID', $message)) $message['commandID'] = COMMAND_UNKNOWN;

	$message ['message'] = $plm_decode_result['plm_message'];



	$result = array();
	if ($message['inout'] == COMMAND_IO_RECV && array_key_exists('deviceID', $message)) {
		$device['previous_properties'] = getDeviceProperties(Array('deviceID' => $message['deviceID']));
		$properties['Link']['value'] = LINK_UP;
		$device['properties'] = $properties;
		$result = updateDeviceProperties(array('callerID' => $message['callerID'], 'deviceID' => $message['deviceID'],'commandID' => $message['commandID'], 
			'device' => $device, 'caller' => $message));
	}

        $exectime += microtime(true);
	$message['result'] = $result;
	$message['exectime'] = $exectime;

	logEvent($message);


	return;
}


function signal_handler($signo){
	console::log("Caught a signal %d", array($signo));
	switch ($signo) {
	 case SIGINT:
	 	// handle restart tasks
	 	cleanup();
	 	break;
	 case SIGTERM:
	 	// handle shutdown tasks
	 	cleanup();
	 	break;
	 case SIGHUP:
	 	// handle restart tasks
	 	cleanup();
	 	break;
	 default:
	 	fprintf(STDERR, "Unknown signal ". $signo);
	}
}

function cleanup(){
	echo "Cleaning up".CRLF;
	exit (1);
}
?>
