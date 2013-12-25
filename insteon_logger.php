<?php
require 'connect-db.php';
include_once 'defines.php';
include_once 'myclasses/TCPClient.php';
include_once 'includes/shared_db.php';
include_once 'includes/shared_file.php';
include_once 'includes/shared_ha.php';
include_once 'includes/shared_gen.php';
include_once 'includes/insteon_decoder.php';

define("MY_DEVICE_ID", 137);
define("INSTEON_HUB", 109);

//echo UpdateMylink(MY_DEVICE_ID)." My Link Updated <br/>\r\n";

define( 'MYDEBUG', FALSE );

class console{
	public static function log($msg, $arr=array()){
		$str = vsprintf($msg, $arr);
		fprintf(STDERR, "$str\n");
	}
}

function cleanup(){
	echo "cleaning up\n";
	CloseTCP("");
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

$mysql = 'SELECT `ip`, `name`, `pingport` FROM `ha_mf_devices` d '.
		 ' JOIN `ha_mf_device_ipaddress` i ON d.ipaddressID = i.id '.
		 ' JOIN `ha_mf_monitor_link` l ON l.deviceID = d.id '.
		 ' WHERE d.`id` = '.INSTEON_HUB;

if (!$res = mysql_query($mysql)) {
	mySqlError($mysql);
	exit;
}
$row = mysql_fetch_assoc($res);


if (OpenTCP( $row['ip'], $row['pingport'], "Insteon")) {
	echo "Listening...";
	while (true) {
		$result=ReadTCP();
		usleep(1000);
		echo plm_decode(bin2hex($result));
	}
}
?>
