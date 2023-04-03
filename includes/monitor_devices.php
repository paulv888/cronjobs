<?php
function monitorDevices-old($linkmonitor) {
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

function testMonitorDevice() {
	$mysql = 'SELECT d.`id` AS `deviceID`, l.`linkmonitor` AS `linkmonitor` , l.`active` AS `active` , l.`pingport` AS `pingport` ' .
			 ' FROM ha_mf_monitor_link l' .
			 ' LEFT JOIN ha_mf_devices d ON l.deviceID = d.id ' .
			 ' WHERE d.`id` = 60 and d.`inuse` = 1  AND l.active > 0' ;

	if ($rows = FetchRows($mysql)) {
		foreach ($rows as $rowlinks) {
			if ($rowlinks['active'] > 0) {
				$feedback[] = monitorDevice($rowlinks['deviceID'],$rowlinks['pingport'],$rowlinks['linkmonitor']);
				
			}
			
		}
	}
	debug($feedback, 'feedback');
	return $feedback;
}

function monitorDevice-odsfs($deviceID, $pingport, $linkmonitor) {

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
		// $params['callerID'] = MY_DEVICE_ID;
		$params['deviceID'] = $deviceID;
		$params['commandID'] = COMMAND_PING;
		// $params['device']['previous_properties'] = getDeviceProperties(Array('deviceID' => $deviceID));
		$properties['Link']['value'] = $curlink;
		$params['device']['properties'] = $properties;
		debug($params,'params');
		$feedback['updateDeviceProperties:'][] = updateDeviceProperties($params);
				debug_print_backtrace();
	} else {
		$feedback['error'] = "Did not find deviceID" . $deviceID ;
	}
	return $feedback;
}


?>
