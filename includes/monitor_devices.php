<?php
require 'connect-db.php';
include_once 'defines.php';
include_once 'includes/shared_db.php';
include_once 'includes/shared_ha.php';
include_once 'includes/shared_gen.php';
include_once 'process.php';

function monitorDevices() {
	$mysql = 'SELECT `ha_mf_devices`.`id` AS `deviceID` , `ha_mf_devices`.`monitortypeID` AS `monitortypeID` , `ha_mf_monitor_link`.`linkmonitor` AS `linkmonitor` , '.
			'`ha_mf_monitor_link`.`pingport` AS `pingport` FROM ha_mf_devices '.
			' LEFT JOIN `ha_mf_monitor_link` ON `ha_mf_devices`.`id` = `ha_mf_monitor_link`.`deviceID` '.
			' WHERE (`ha_mf_devices`.`monitortypeID` > 1 AND `linkmonitor` = "POLL")';
	
	if (!$reslinks = mysql_query($mysql)) {
		mySqlError($mysql); 
		exit;
	}
	while ($rowlinks = mysql_fetch_assoc($reslinks)) {	
		monitorDevice($rowlinks['deviceID'],$rowlinks['pingport'],$rowlinks['monitortypeID']);
	}
}

function monitorDevice($deviceid, $pingport, $montype) {
	$mysql = 'SELECT `ip`, `name` FROM `ha_mf_device_ipaddress` i JOIN `ha_mf_devices` d ON d.ipaddressID = i.id WHERE d.`id` = '.$deviceid;
	if (!$resip = mysql_query($mysql)) {
		mySqlError($mysql); 
		exit;
	}
	$rowip = mysql_fetch_assoc($resip);
	if ($pingport>0) {
		$status = pingip ($rowip['ip'],$pingport,1);
	} else {
		$status = pingtcp ($rowip['ip'],100);
	}
	if ($status) {
		$curstat = STATUS_ON;
		$curlink = LINK_UP;
		$statverb = "Online";
	} else {
		$curstat = STATUS_OFF;
		$curlink = LINK_DOWN;
		$statverb = "Offline";
	}
	echo $rowip['name']." ".$rowip['ip']." is $statverb, Device: $deviceid</br>";
	UpdateMyLink($deviceid, $curlink, SIGNAL_MONITOR_DEVICES, COMMAND_LINK_STATUS);
	if ($montype == MONITOR_STATUS || $montype == MONITOR_LINK_STATUS) UpdateStatus ($deviceid,$curstat) ;
}

function pingip($host, $port, $timeout)
{ 
	$tB = microtime(true); 
	$fP = @fSockOpen($host, $port, $errno, $errstr, $timeout); 
	if (is_resource($fP)) return true;
	return false; 
	//$tA = microtime(true); 
	//return round((($tA - $tB) * 1000), 0)." ms"; 
	//return true;
}

function pingtcp($host, $timeout)
{ 
	$tB = microtime(true); 
	$fP = exec("fping -t$timeout $host", $output, $status);
	/* print_r ($output);
	echo "</br>TCP status: $status</br>";
	echo "</br>TCP status: $fP</br>"; */
	if ($status==0) return true;
	return $false; 
}
?>