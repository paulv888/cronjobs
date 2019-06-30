<?php
function monitorDevices($linkmonitor) {
	$mysql = 'SELECT d.`id` AS `deviceID`, l.`linkmonitor` AS `linkmonitor` , l.`active` AS `active` , l.`pingport` AS `pingport` ' .
			 ' FROM ha_mf_monitor_link l' .
			 ' LEFT JOIN ha_mf_devices d ON l.deviceID = d.id ' .
			 ' WHERE d.`inuse` = 1 AND l.`linkmonitor` IN ('.$linkmonitor .')' .
			 ' AND l.active > 0' ;
	$date = getdate();
	$day = $date["wday"];
	if ($rows = FetchRows($mysql)) {
		foreach ($rows as $rowlinks) {
			if ($rowlinks['active'] > 0)
			monitorDevice($rowlinks['deviceID'],$rowlinks['pingport'],$rowlinks['linkmonitor']);
		}
	}
}

function monitorDevice($deviceID, $pingport, $linkmonitor) {

	$mysql = 'SELECT `ip`, `name`,`friendly_name` FROM `ha_mf_device_ipaddress` i JOIN `ha_mf_devices` d ON d.ipaddressID = i.id WHERE d.`id` = '.$deviceID;
	$rowip = FetchRow($mysql);
	$status = false;
	if ($rowip['ip'] != NULL) {
		if ($pingport>0) {
			if ($linkmonitor=='NMAP') {
				//echo pingnmp($rowip['ip'],$pingport);
				$status = pingnmp($rowip['ip'],$pingport);
			} else {
				$status = pingport($rowip['ip'],$pingport,2);
			}
		} else {
			$status = pingicmp($rowip['ip'],100);
		}
	}

	if ($status) {
		$curlink = LINK_UP;
		$statverb = "Online";
	} else {
		$curlink = LINK_DOWN;
		$statverb = "Offline";
	}

	$params['callerID'] = MY_DEVICE_ID;
	$params['deviceID'] = $deviceID;
	$params['commandID'] = COMMAND_PING;
	$params['device']['previous_properties'] = getDeviceProperties(Array('deviceID' => $deviceID));
	$properties['Link']['value'] = $curlink;
	$params['device']['properties'] = $properties;
	$feedback['updateDeviceProperties:'][] = updateDeviceProperties($params);
	return $feedback;
}

function pingport($host, $port, $timeout) {
	$fP = @fSockOpen($host, $port, $errno, $errstr, $timeout);
	if (is_resource($fP)) return true;
	return false;
}

function pingnmp($host, $port) {
//	$cmd = 'nmap -sS -p'.$port.' '.$host.' | grep -Fq "1 host"';
	$fP = exec('nmap -sS -p'.$port.' '.$host, $output, $status);
	if ($status==0) return true;
	return false;
}

function pingicmp($host, $timeout) { 
	$tB = microtime(true); 
	$fP = exec("fping -t$timeout $host", $output, $status);
	if ($status==0) return true;
	return false;
}
?>
