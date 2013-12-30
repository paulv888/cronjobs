<?php
require 'connect-db.php';
include_once 'defines.php';
include_once 'includes/shared_db.php';
include_once 'includes/shared_file.php';
include_once 'includes/shared_ha.php';
include_once 'includes/shared_gen.php';
include_once 'myclasses/insteon_hub.class.php';
include_once 'myclasses/insteon_decoder.class.php';
include_once 'myclasses/sockettransport.class.php';

define("MY_DEVICE_ID", 137);
define("INSTEON_HUB_IP", "192.168.2.125");
define("INSTEON_HUB_PORT", 9761);

//define( 'MYDEBUG', FALSE );
define( 'MYDEBUG', TRUE );

$transport = new SocketTransport(array(INSTEON_HUB_IP),INSTEON_HUB_PORT);

class console{
	public static function log($msg, $arr=array()){
		$str = vsprintf($msg, $arr);
		fprintf(STDERR, "$str\n");
	}
}

function cleanup(){
	global $inst_hub;
	echo "Cleaning up\n";
	unset ($inst_hub);
	exit;
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

//  PVTODO: Restart TCP connection on lost.
//  PVTODO: Receive multiple packets, get length and parse sepearately
//  PVTODO: Handle Broken packets
//  PVTODO: Status request, not working
//			Poll All-Link Database numbers and store
//			Interpret CMD1 & CMD2 as All-Link Number and Status

$inst_hub = new InsteonHub(INSTEON_HUB_IP,INSTEON_HUB_PORT);


if ($inst_hub) {

	while (true) {
			$message = $inst_hub->getMessage(); 
			$result = logEventInsteon($message);
		}
	}


function logEventInsteon($log){


//	echo "logEventInsteon\n";
//	print_r($plm_decode_result);
	$mysql='SELECT `id`, `typeID` FROM `ha_mf_devices` WHERE `code` ="'.$log['code'].'" AND `unit` ="'.$log['unit'].'"';
	$resdevice=mysql_query($mysql);
	$rowdevice=mysql_fetch_array($resdevice);
	$log['deviceID'] = $rowdevice['id'];
	$log['typeID'] = $rowdevice['typeID'];
	unset($log['code']);
	unset($log['unit']);
	
	$log['logLevel'] = 1;
	if (!isset($log['commandID'])) {
		$log['commandID'] = COMMAND_UNKNOWN;
		$log['logLevel'] = 2;
	}
	
	logEvent($log);
	
}
?>
