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
			if ($rowlinks['active'] > 0) {
				$feedback[] = monitorDevice($rowlinks['deviceID'],$rowlinks['pingport'],$rowlinks['linkmonitor']);
			}
			
		}
	}
	return $feedback;
}

function dmonitorDevices() {
	// $feedback[] = monitorDevices('"POLL","NMAP"');
	$feedback = monitorDevices('"POLL2"');
		logEvent(array(
			'inout' => COMMAND_IO_SEND, 
			'callerID' => MY_DEVICE_ID, 
			'commandID' => 464, 
			'result' => $feedback,  
			'commandstr' => $feedback['commandstr'], 
			'data' => "monit-fast"));
	return $feedback;
}

function monitorDevice($deviceID, $pingport, $linkmonitor) {

	$mysql = 'SELECT `ip`, `name`,`friendly_name` FROM `ha_mf_device_ipaddress` i JOIN `ha_mf_devices` d ON d.ipaddressID = i.id WHERE d.`id` = '.$deviceID;
	if ($rowip = FetchRow($mysql)) {
		$status = false;
		//echo "$mysql\n";
		$feedback['Name'] = $rowip['friendly_name'];
		$feedback['result'] = array();
		if (isset($rowip['ip'])) {
			if ($pingport>0) {
				if ($linkmonitor=='NMAP') {
					$feedback['ping'] = pingnmp($rowip['ip'],$pingport);
					$status = $feedback['ping']['result'];
				} else {
					$feedback['ping'] = pingport($rowip['ip'],$pingport,2);
					$status = $feedback['ping']['result'];
				}
			} else {
				$feedback['ping'] = pingicmp($rowip['ip'],100);
				$status = $feedback['ping']['result'];
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
	//print_r($params);
		$feedback['updateDeviceProperties:'][] = updateDeviceProperties($params);
	} else {
		$feedback['error'] = "Did not find deviceID" . $deviceID ;
	}
	return $feedback;
}

function pingport($host, $port, $timeout) {
	$fP = @fSockOpen($host, $port, $errno, $errstr, $timeout);
	$feedback['commandstr'] = "fSockOpen(".$host.", ".$port.", ".$errno.", ".$errstr.", ".$timeout.")";
	if (is_resource($fP)) {
		$feedback['result'] = 1;
		return $feedback;
	} else {
		$feedback['result'] = 0;
		return $feedback;
	}
}

function pingnmp($host, $port) {
	$cmd = 'nmap -sS -p'.$port.' '.$host.' | grep -Fq "1 host"';
	$fP = exec($cmd, $output, $status);
	$feedback['commandstr'] = $cmd;
	if ($status==0) {
		$feedback['result'] = 1;
		return $feedback;
	} else {
		$feedback['result'] = 0;
		return $feedback;
	}
}

function pingicmp($host, $timeout) { 
	$tB = microtime(true); 
	$fP = exec("fping -t$timeout $host", $output, $status);
	$feedback['commandstr'] = "fping -t$timeout $host";
	if ($status==0) {
		$feedback['result'] = 1;
		return $feedback;
	} else {
		$feedback['result'] = 0;
		return $feedback;
	}
}
?>
