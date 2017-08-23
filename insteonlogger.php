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

//define( 'DEBUG_INSTEON', TRUE );
if (!defined('DEBUG_INSTEON')) define( 'DEBUG_INSTEON', FALSE );

if (isset($_GET['DEBUG'])) {
	echo date("Y-m-d H:i:s").": trying to connect to VLOSITE:3333".CRLF;
	echo date("Y-m-d H:i:s").": send a command with nc -l 3333".CRLF;
	define("INSTEON_HUB_IP", "vlosite");
	define("INSTEON_HUB_PORT", 3333);
	define("DEBUG_MODE", true);
} else {
	define("INSTEON_HUB_DEVICE", "115");
	define("DEBUG_MODE", false);
} 


if (!DEBUG_MODE) {
	class console{
		public static function log($msg, $arr=array()){
			$str = vsprintf($msg, $arr);
			fprintf(STDERR, "$str\n");
		}
	}

	pcntl_signal(SIGTERM, "signal_handler");
	pcntl_signal(SIGHUP, "signal_handler");
	pcntl_signal(SIGINT, "signal_handler");

	pcntl_signal_dispatch();
	console::log("Signal dispatched");

}

$errors = 0;
try {

	$last = strtotime(date("Y-m-d H:i:s"));
	echo updateDLink(MY_DEVICE_ID);

	if ($device = getDevice(INSTEON_HUB_DEVICE)) {

		$page = 'buffstatus.xml';
		$device['connection']['page'] = $page;
		$url = setURL(array('device' => $device), $page);

		$page = '/1?XB=M=1';
		$device['connection']['page'] = $page;
		$clearurl = setURL(array('device' => $device), $page);
	
	} 

	$last_buff_len = 0;
	$last_result = "0000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000";
	$max_buff_len = strlen($last_result);
	$processed = "";
	$mybuffer = "";

	// Clear buffer for a fresh start
	$curl = restClient::get($clearurl,null, null, 1);

	$inst_coder = new InsteonCoder();
	
	while (true){
		
		usleep(250000);
			// if (DEBUG_MODE) echo $this->url.CRLF;
		$curl = restClient::get($url,null, null, 1);
		if ($curl->getresponsecode() != 200 && $curl->getresponsecode() != 204) {
			// handle error?
			echo $curl->getresponsecode().": ".$curl->getresponse();
		} else {
			preg_match('#<BS>(.*?)</BS>#', $curl->getresponse(), $matches);
			$tmpresult = substr($matches[0],  4, strlen($matches[0]) -9 );
			$buff_len = hexdec(substr($tmpresult, -2));
			if ( $tmpresult != $last_result) {		// Something changed, content or length
				
				//
				// Sub what we already processed, cannot rely on counter bc auto empty
				//
				if (DEBUG_INSTEON) echo "tmpresult:".$tmpresult."\n";
				if (DEBUG_INSTEON) echo "left temp:".substr($tmpresult, 0, strlen($processed))."\n";
				if (DEBUG_INSTEON) echo "processed:".$processed."\n";
				if (substr($tmpresult, 0, strlen($processed)) == $processed) {							 // Start position or received additional 
					$mybuffer .= substr($tmpresult, strlen($processed), $buff_len - strlen($processed)); // Left part the same, cut off
					$end_buffer_len = 0;
				} else {
					// 2 Scenario's 
					//		Normal circle -> grab end and beginning
					//		A command was send and we want to ignore and since f.cking insteon reset our buffer
						
					// Normal 
					if (substr($tmpresult, strlen($processed)  , 2) == "02" ) {
						$mybuffer .= substr($tmpresult, strlen($processed) , $max_buff_len - strlen($processed) - 2 );
						$end_buffer_len = $max_buff_len - strlen($processed) - 2 ;

					}
					//= preg_replace('/^00+/', '02', $mybuffer);
					if (DEBUG_INSTEON) echo "end_buffer_len: $end_buffer_len\n";
					$mybuffer .= substr($tmpresult, 0 , $buff_len );
					$processed = "";

					// Was command send and do we only want to get the start
					//  Remove leading zero's
				}

				// Incoming commands buffer keeps growing.
				// On sending a command, buffer is auto cleared HOW TO RECOGNIZE?
				if (DEBUG_INSTEON) echo "Buffer Length:".$buff_len."\n";
				if (DEBUG_INSTEON) echo "->".$mybuffer."\n";
				$last_buff_len = $buff_len;
				$last_result = $tmpresult;
				
				//
				// Lets decode some of the result
				//
				do {
					$plm_decode_result = $inst_coder->plm_decode($mybuffer);
					
					// check for to short for PLM message, if so save result for rest
					// if (!array_key_exists("extdata", $plm_decode_result)) $plm_decode_result['extdata'] = "";
					echo date("Y-m-d H:i:s")." +++plm_decode_result\n";
					echo json_encode($plm_decode_result)."\n";
					echo date("Y-m-d H:i:s")." ===end plm_decode_result\n";
					if ($plm_decode_result['length'] == ERROR_MESSAGE_TO_SHORT) {  							// leave result and wait for more
						echo "ERROR_MESSAGE_TO_SHORT"." Empty mybuffer, refill from buffer"."\n";
						$mybuffer = "";
						// Need to make sure to not get stuck, retry and discard input buffer
						//exit;

					} elseif ($plm_decode_result['length'] == ERROR_STX_MISSING) {									// basically to short as well
						echo "ERROR_STX_MISSING"." Start nibbling to catch up"."\n";
						// $mybuffer = "";									// Clear result padding
						// Need to handle these as they arrise
						exit;
					} elseif ($plm_decode_result['length'] <= -3) {									// basically to short as well
						echo "ERROR_UNKNOWN_MESSAGE"." Do something :)"."\n";
						// $mybuffer = "";									// Clear result padding
						//
						//
						// Need to handle these as they arrise
						exit;
					} else {
						// if ($plm_decode_result['loglevel'] != LOGLEVEL_NONE)  $addMessage($plm_decode_result);
						// echo "All good storing!!! ".$plm_decode_result['plm_message']." ".$plm_decode_result['insteon']['command']." Len: ".$plm_decode_result['length']."\n\n";
						storeMessage($plm_decode_result);
						$processed .= substr($mybuffer, $end_buffer_len, $plm_decode_result['length']);	
						$mybuffer = substr($mybuffer,$plm_decode_result['length']);	
						if (DEBUG_INSTEON) echo "%%%%%%%:".$mybuffer."\n";
					}
				} while ($plm_decode_result['length']>0 && strlen($mybuffer>0));
			}
		}
	}
}	
catch (Exception $e) {
	$errors = $errors + 1;
	echo "Retry: ".$errors." ".$e->getMessage();
	unset ($inst_hub);
	sleep(30 * $errors);
}


function storeMessage($plm_decode_result) {
	// if (array_key_exists("x10_nothandle",$plm_decode_result)) {		// handle x10 message
		// $x10 = $plm_decode_result['x10'];
		// if (!array_key_exists("commandID",$x10)) {   		// Enqueue incomplete messages and exit
			// //Complete messages and push 
			// $incompl ['code'] = $x10['code'];
			// $incompl ['unit'] = $x10['unit'];
			// $incompl ['plmcmdID'] = $plm_decode_result['plmcmdID'];
			// $incompl ['message'] = $plm_decode_result['plm_string']."\n".$plm_decode_result['plm_message'];
			// // $this->incompl_messages->enqueue($incompl); 
			// return ;								
		// } else {													// Complete messages and push				
			// unset ($newincompl);
			// foreach ($this->incompl_messages as $incompl) {			// Handle many addresses with 1 command
				// if ($incompl['code'] == $x10['code'] && ($incompl['plmcmdID'] == $plm_decode_result['plmcmdID'] || $x10['commandID'] == COMMAND_STATUSON || $x10['commandID'] == COMMAND_STATUSOFF)) {
					// $message = $incompl;
					// $message['commandID'] = $x10['commandID'];
					// $message['inout'] = $plm_decode_result['inout'];
					// $message ['plmcmdID'] = $plm_decode_result['plmcmdID'];
					// $message ['message'] .= "\n".$plm_decode_result['plm_string']."\n".$plm_decode_result['plm_message'];
					// $message ['loglevel'] = $plm_decode_result['loglevel'];
					// // $this->messages->enqueue($message);
					// //if (DEBUG_INSTEON) print_r($message);
					// if ($message['commandID'] == 5) {			// Push extra message for status request response (does not have an Unit Code)
						// $newincompl ['code'] = $message['code'];
						// $newincompl ['unit'] = $message['unit'];
						// $newincompl ['plmcmdID'] = $plm_decode_result['plmcmdID'];
						// $newincompl ['message'] = $plm_decode_result['plm_string']."\n".$plm_decode_result['plm_message'];
					// }
				// } 			
				// $this->incompl_messages->dequeue(); // dequeue , different code or handled message  
				// // Should only trow old ones away... 
			// }
			// // if (isset($newincompl)) $this->incompl_messages->enqueue($newincompl); 
			// //echo "3pushing newincompl".$this->incompl_messages->count()."\n";
		// }
	// } else {
		$message['inout'] = $plm_decode_result['inout'];
		$message['code'] = "I";
		if (array_key_exists("from",$plm_decode_result)) {
			$message['unit'] = strtoupper($plm_decode_result['from']);
		} elseif (array_key_exists("to",$plm_decode_result)) {
			$message['unit']  = strtoupper($plm_decode_result['to']);
		} else {
			$message['unit'] = NULL;
		}
		if (array_key_exists("insteon",$plm_decode_result)) {
			$insteon = $plm_decode_result['insteon'];
			if (array_key_exists("commandvalue",$insteon )) $message['commandvalue'] = $insteon['commandvalue'];
			if (array_key_exists("data",$insteon )) $message['data'] = $insteon['data'];
			// if (array_key_exists("extdata",$insteon )) $message['extdata'] = $insteon['extdata'];
			$message['commandID'] = $insteon['commandID'];
			if (array_key_exists('status', $insteon )) $message['status'] = $insteon['status'];
		}
		$message ['plmcmdID'] = $plm_decode_result['plmcmdID'];
		$message ['message'] = $plm_decode_result['plm_string']."\n".$plm_decode_result['plm_message'];
		$message ['loglevel'] = $plm_decode_result['loglevel'];
		// $this->messages->enqueue($compl);
	// }
	//
	// Better make sure this is all quick, need to get the next buffer
	//
	
	$message['callerID'] = MY_DEVICE_ID;
	if (is_null($deviceID = setDeviceID($message))) { 		// No device founds so use my_id as deviceID
		$message['deviceID'] = MY_DEVICE_ID;
	}
	echo date("Y-m-d H:i:s")."\t+++Storing: message\n\t";
	print_r($message);
	echo date("Y-m-d H:i:s")."\t===Logger: message\n";
	if (!array_key_exists('commandID', $message)) $message['commandID'] = COMMAND_UNKNOWN;
	$properties = Array();
	if (array_key_exists('commandvalue',$message)) {
		$properties['Level']['value'] = round(100/255*$message['commandvalue']);
		unset($message['commandvalue']);
	}
	logEvent($message);
	if ($message['inout'] == COMMAND_IO_RECV) {
		$device['previous_properties'] = getDeviceProperties(Array('deviceID' => $message['deviceID']));
		$properties['Link']['value'] = LINK_UP;
		$device['properties'] = $properties;
		echo date("Y-m-d H:i:s").": ".'Update Status: '.json_encode(updateDeviceProperties(array('callerID' => $message['callerID'], 'deviceID' => $message['deviceID'], 
				'commandID' => $message['commandID'], 'device' => $device, 'caller' => $message)),JSON_UNESCAPED_SLASHES)."</br>\n";
	}

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
	global $inst_hub;
	echo "Cleaning up\n";
	unset ($inst_hub);
	exit (1);
}
?>
