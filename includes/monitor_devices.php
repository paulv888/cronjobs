<?php
function monitorDevices($linkmonitor) {
	$mysql = 'SELECT d.`id` AS `deviceID`, l.`linkmonitor` AS `linkmonitor` , l.`pingport` AS `pingport` ' .
			 ' FROM ha_mf_monitor_link l' .
			 ' LEFT JOIN ha_mf_devices d ON l.deviceID = d.id ' .
			 ' WHERE d.`inuse` = 1 AND l.`linkmonitor` = "'.$linkmonitor .'"' .
			 ' AND l.active = 1' ;
	if (!$reslinks = mysql_query($mysql)) {
		mySqlError($mysql); 
		return false;
	}
	while ($rowlinks = mysql_fetch_assoc($reslinks)) {	
		monitorDevice($rowlinks['deviceID'],$rowlinks['pingport']);
	}
}

function monitorDevice($deviceID, $pingport) {
	$mysql = 'SELECT `ip`, `name` FROM `ha_mf_device_ipaddress` i JOIN `ha_mf_devices` d ON d.ipaddressID = i.id WHERE d.`id` = '.$deviceID;
	if (!$resip = mysql_query($mysql)) {
		mySqlError($mysql); 
		return false;
	}
	$rowip = mysql_fetch_assoc($resip);
	$status = false;
	if ($rowip['ip'] != NULL) {
		if ($pingport>0) {
			$status = pingip ($rowip['ip'],$pingport,2);
		} else {
			$status = pingtcp ($rowip['ip'],100);
		}
	}
	if ($status) {
		$curlink = LINK_UP;
		$statverb = "Online";
	} else {
		$curlink = LINK_DOWN;
		$statverb = "Offline";
	}

	echo date("Y-m-d H:i:s").": ".$rowip['name']." ".$rowip['ip']." is $statverb, Device: $deviceID".CRLF;
	$params['callerID'] = MY_DEVICE_ID;
	$params['deviceID'] = $deviceID;
	$params['commandID'] = COMMAND_PING;
    $params['device']['previous_properties'] = getDeviceProperties(Array('deviceID' => $deviceID));
	$properties['Link']['value'] = $curlink;
	$params['device']['properties'] = $properties;
	$feedback['updateDeviceProperties:'][] = updateDeviceProperties($params);
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
	return FALSE; 
}
?>
