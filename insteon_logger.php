<?php
require 'connect-db.php';
include_once 'defines.php';
include_once 'myclasses/TCPClient.php';
include_once 'includes/shared_db.php';
include_once 'includes/shared_file.php';
include_once 'includes/shared_ha.php';
include_once 'includes/shared_gen.php';
include_once 'myclasses/insteon_decoder.class.php';
include_once 'myclasses/sockettransport.class.php';

define("MY_DEVICE_ID", 137);
define("INSTEON_HUB", 109);

define( 'MYDEBUG', FALSE );
//define( 'MYDEBUG', TRUE );

class console{
	public static function log($msg, $arr=array()){
		$str = vsprintf($msg, $arr);
		fprintf(STDERR, "$str\n");
	}
}

function cleanup(){
	echo "cleaning up\n";
	$transport->close();
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

$inst_coder = new InsteonCoder();

$transport = new SocketTransport(array('192.168.2.125'),9761);
$transport->setRecvTimeout(900000); // for this example wait up to 60 seconds for data
//$transport->setRecvTimeout(60000); // for this example wait up to 60 seconds for data
$transport->setSendTimeout(30000);
if (MYDEBUG) $transport->debug = true;
$result=$transport->open();



//  PVTODO: Restart TCP connection on lost.
//  PVTODO: Receive multiple packets, get length and parse sepearately
//  PVTODO: Handle Broken packets
//  PVTODO: Status request, not working
//			Poll All-Link Database numbers and store
//			Interpret CMD1 & CMD2 as All-Link Number and Status



if ($result) {
//	$all_result = Array();
	while (true) {
/*		try {
			$result=$transport->readAll(100);
		}
		catch (Exception $e) {
			echo "<p>There was an error.</p>";
			echo $e->getCode();
			echo $e->getMessage();
		} */
		$result = $transport->readAll();
		if ($result) {
			$plm_decode_result = $inst_coder->plm_decode(bin2hex($result));
			if ($plm_decode_result['plmcmdID'] == "0273") {
				// received alive message
				//	Update Hub alive
				//	If no message reveived in time_out then restart
				// echo "Alive response received";
				echo UpdateMylink(MY_DEVICE_ID)." My Link Updated <br/>\r\n";
			}
			$result =logEventInsteon($plm_decode_result);
			if (MYDEBUG) print_r($plm_decode_result);
		} else {
			$result = $transport->write(hex2bin("0273"));
//			unset($all_result);
		}
	}
}

function logEventInsteon($plm_decode_result){

$inout_a = Array (
					'0250' => 2,
					'0251' => 2,
					'0252' => 2,
					'0253' => 3,
					'0254' => 2,
					'0255' => 3,
					'0256' => 3,
					'0257' => 3,
					'0258' => 3,
					'0260' => 2,
					'0261' => 1,
					'0262' => 1,
					'0263' => 1,
					'0264' => 3,
					'0265' => 3,
					'0266' => 3,
					'0267' => 3,
					'0268' => 3,
					'0269' => 3,
					'026A' => 3,
					'026B' => 3,
					'026C' => 3,
					'026D' => 3,
					'026E' => 3,
					'026F' => 3,
					'0270' => 2,
					'0271' => 2,
					'0272' => 3,
					'0273' => 3
				);

	$log = Array();
//	echo "logEventInsteon\n";
//	print_r($plm_decode_result);
	$log['inout'] = $inout_a[$plm_decode_result['plmcmdID']];
	$log['source'] = SIGNAL_SOURCE_INSTEON;
	if (array_key_exists("x10",$plm_decode_result)) {
		$x10 = $plm_decode_result['x10'];
		if (array_key_exists("unit",$x10)) $unit = $x10['unit']; else $unit = 0;
		$mysql='SELECT `id`, `typeID` FROM `ha_mf_devices` WHERE `code` ="'.$x10['code'].'" AND `unit` ='.$unit;
		$resdevice=mysql_query($mysql);
		$rowdevice=mysql_fetch_array($resdevice);
		$log['deviceID'] = $rowdevice['id'];
		$log['typeID'] = $rowdevice['typeID'];
		if (array_key_exists("commandID",$x10)) $commandID = $x10['commandID']; else $commandID = COMMAND_ADDRESS;
		$log['commandID'] = $commandID;
	}
	if (array_key_exists("insteon",$plm_decode_result)) {
		$insteon = $plm_decode_result['insteon'];
		if (array_key_exists("from",$plm_decode_result)) $unit = $plm_decode_result['from']; else $unit = $plm_decode_result['to'];
		$mysql='SELECT `id`, `typeID` FROM `ha_mf_devices` WHERE `code` ="I" AND `unit` = "'.strtoupper($unit).'"';
		$resdevice=mysql_query($mysql);
		$rowdevice=mysql_fetch_array($resdevice);
		$log['deviceID'] = $rowdevice['id'];
		$log['typeID'] = $rowdevice['typeID'];
		$log['commandID'] = $insteon['commandID'];
		if (array_key_exists("data",$insteon )) $log['data'] = $insteon['data'];
		if (array_key_exists("extdata",$insteon )) $log['extdata'] = $insteon['extdata'];
	}
	
	$log['logLevel'] = 1;
	if (!isset($log['commandID'])) {
		$log['commandID'] = COMMAND_UNKNOWN;
		$log['logLevel'] = 2;
	}
	$log['plmcmdID'] = $plm_decode_result['plmcmdID'];
	$log['message'] = $plm_decode_result['plm_string']."\n".$plm_decode_result['plm_message'];
	
	logEvent($log);
	
}
?>
