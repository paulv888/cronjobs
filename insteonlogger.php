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

if (isset($_GET['DEBUG'])) {
	echo date("Y-m-d H:i:s").": trying to connect to VLOSITE:3333".CRLF;
	echo date("Y-m-d H:i:s").": send a command with nc -l 3333".CRLF;
	define("INSTEON_HUB_IP", "vlosite");
	define("INSTEON_HUB_PORT", 3333);
	define("DEBUG_MODE", true);
} else {
	define("INSTEON_HUB_IP", "192.168.2.25");
	define("INSTEON_HUB_PORT", 9761);
	define("DEBUG_MODE", false);
} 


define( 'DEBUG_INSTEON', TRUE );
if (!defined('DEBUG_INSTEON')) define( 'DEBUG_INSTEON', FALSE );


if (!DEBUG_MODE) {
	class console{
		public static function log($msg, $arr=array()){
			$str = vsprintf($msg, $arr);
			fprintf(STDERR, "$str\n");
		}
	}

	if(version_compare(PHP_VERSION, "5.3.0", '<')){
		// tick use required as of PHP 4.3.0
		declare(ticks = 1);
	}

	pcntl_signal(SIGTERM, "signal_handler");
	pcntl_signal(SIGHUP, "signal_handler");
	pcntl_signal(SIGINT, "signal_handler");

	if(version_compare(PHP_VERSION, "5.3.0", '>=')){
		pcntl_signal_dispatch();
		console::log("Signal dispatched");
	}

}
//  PVTODO: Restart TCP connection on lost.
//  PVTODO: Receive multiple packets, get length and parse sepearately
//  PVTODO: Handle Broken packets
//  PVDONE: Status request, not working
//			Poll All-Link Database numbers and store
//			Interpret CMD1 & CMD2 as All-Link Number and Status

$errors = 0;

// Inf loop till signal
while (true) {
	try {
		$transport = new SocketTransport(array(INSTEON_HUB_IP),INSTEON_HUB_PORT);
		$inst_hub = new InsteonHub(INSTEON_HUB_IP,INSTEON_HUB_PORT);

		if ($inst_hub) {

			$last = strtotime(date("Y-m-d H:i:s"));
        		echo updateDLink(MY_DEVICE_ID);
 
			// Inf loop till signal or error
			while (true) {
				$errors = 0;
				$message = $inst_hub->getMessage(); 
				$message['callerID'] = MY_DEVICE_ID;
				if (is_null($deviceID = setDeviceID($message))) { 		// No device founds so use my_id as callerID
					$message['deviceID'] = MY_DEVICE_ID;
				}
				echo date("Y-m-d H:i:s")." +++Logger: message\n";
				print_r($message);
				echo date("Y-m-d H:i:s")." ===Logger: message\n";
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
							'commandID' => $message['commandID'], 'device' => $device, 'caller' => $message)))."</br>\n";
				}
				
				// Update My Link 
				if (timeExpired($last, 15)) echo updateDLink(MY_DEVICE_ID);
			}
		}
	}
	catch (Exception $e) {
		$errors = $errors + 1;
		echo "Retry: ".$errors." ".$e->getMessage();
		unset ($inst_hub);
		sleep(30 * $errors);
	}
	
}	

function cleanup(){
	global $inst_hub;
	echo "Cleaning up\n";
	unset ($inst_hub);
	exit (1);
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
?>
