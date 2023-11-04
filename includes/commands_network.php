<?php
// @@TODO: DO NOT SEND MESSAGES TO KNOW OFFLINE DEVICES?
//
//	Command in:
// 		$params
//
//  Command out:
//		$feedback type Array
//			with keys: 
//						'Name'   		(String)	-> Name of executed command						REQUIRED
//						'result'		(Array)		-> result (Going to log (Update Props or ...)	REQUIRED
//						'message' 		(String)	-> To display on remote
//						'commandstr' 	(String)	-> for eventlog, actual command send
//      if error then	'error'			(String)	-> Error description
//						Nothing else allowed 
// function templateFunction(&$params) {

	// $feedback['Name'] = 'templateFunction';
	// $feedback['commandstr'] = "I send this";
	// $feedback['result'] = array();
	// $feedback['message'] = "all good";
	// if () $feedback['error'] = "Not so good";

	// return $feedback;
// }
function getDeviceList(&$params) {

	$showlist=false;
	if ($params['caller']['callerID'] == DEVICE_REMOTE) $showlist=true; 

	$feedback['Name'] = 'getDeviceList';
	$feedback['result'] = array();

	$cmd = 'ssh -o UserKnownHostsFile=/dev/null -o StrictHostKeyChecking=no remote-jobs@'.$params['device']['ipaddress']['ip'].' -i remote-jobs \'/ip dhcp-server lease print detail terse without-paging where status!=conflict\'';

//address=192.168.2.11 mac-address=52:54:00:B9:1D:67 address-lists="" server=dhcp-lan dhcp-option="" status=bound expires-after=9m57s last-seen=3s active-address=192.168.2.11 active-mac-address=52:54:00:B9:1D:67 active-server=dhcp-lan host-name=vlosite 
	debug($cmd, 'command');
	$feedback['commandstr'] = $cmd;
	$output = shell_exec($cmd);
	debug($output, 'shell_exec');
	$lines =  preg_split('/\n|\r\n?/', $output);
	$feedback['result'][] = $lines;
	debug($lines, 'lines');
//	$columns = ["leaseIime", "hwaddr", "ip", "name", "ip6"];
//	$columns = ["id", "ip", "hwaddr", "interface", "name", "firstSeen", "lastQuery", "numQueries", "macVendor"];
	$addresses = array();
	foreach ($lines as $line) {
		if (empty($line)) continue;
		$values = explode(' ',$line);
		$pairs = array();
		foreach ($values as $value) {
			if (empty($value)) continue;
			$split = explode('=',$value);
			switch ($split[0]) {
			case 'address':
				$pairs ['ip'] = $split[1];
				break;
			case 'mac-address':
				$pairs ['hwaddr'] = $split[1];
				break;
			case 'status':
				$pairs ['status'] = $split[1];
				break;
			case 'comment':		// comment comes before host-name
			case 'host-name':
				if (empty($pairs ['name'])) $pairs ['name'] = $split['1'];
				break;
			}
		}
		if (!empty($pairs)) $addresses[$pairs['hwaddr']] = $pairs;
	}
	debug($addresses, 'addresses');

//$addresses[$pairs['hwaddr']]['name'] = str_replace('.'.DOMAIN_NAME, '', $addresses[$pairs['hwaddr']]['name']);
		// $device['name'] = $address['name'];
		// $device['vendor'] = (array_key_exists('macVendor',$address) ? $address['macVendor'] : "");
		// $device['ip'] = $address['ip'];
		// $device['mac'] = $address['hwaddr'];
		
	usort($addresses, function($a, $b) {return strnatcmp($a['ip'],$b['ip']);});
	debug($addresses, 'addresses');

	$newmacs=0;
	$changedips=0;
	$devicesimported=0;
    if ($showlist) $feedback['message']='<HTML><pre><table style="width:100%"><thead><tr><td><b>Name</b></td><td><b>IP</b></td><td><b>Status</b></td><td><b>Mac</b></td></tr></thead><tbody>';
	
	foreach ($addresses as $address) {
		$device = array();
		$device['name'] = (array_key_exists('name', $address) ? $address['name'] : "");
		$device['vendor'] = (array_key_exists('macVendor',$address) ? $address['macVendor'] : "");
		$device['ip'] = $address['ip'];
		$device['mac'] = $address['hwaddr'];
		$mysql="SELECT * ". 
				" FROM  `ha_mf_device_ipaddress`" .  
				" WHERE mac='".$device['mac']."'";  
		debug($device,'handle->device');
		if ($rowdevice = FetchRow($mysql)) {			// Update existing mac
			if ($showlist) {
					$feedback['message'] .= '<tr><td>'.$device['name'].'</td><td>'.$device['ip'].'</td><td>'.$address['status'].'</td><td>'.$device['mac'].'</td></tr>';
			}
			if (strlen($device['name']) == 0) $device['name'] = $rowdevice['name'];
			if ($rowdevice['name'] <> $device['name'] || $rowdevice['ip'] <> $device['ip']) {		// Something changed
				$mysql="SELECT * ". 
					" FROM  `ha_mf_devices`" .  
					" WHERE ipaddressID =".$rowdevice['id'];  
				if ($rowdev = FetchRow($mysql)) $deviceID = $rowdev['id']; else $deviceID = 0;
				$command = array('callerID' => $params['caller']['callerID'], 
								'deviceID' => $deviceID, 
								'messagetypeID' => 'MESS_TYPE_SCHEME',
								'schemeID' => SCHEME_ALERT_NORMAL,
								"commandvalue" => $rowdevice['friendly_name']." MAC: ".$device['mac']."|Old Name: ".$rowdevice['name']."<br/>New Name: "
								              .$device['name']."<br/>Old IP: ".$rowdevice['ip']."<br/>New IP: ".$device['ip']);
				$feedback['result']['Changed'][] = executeCommand($command);
				PDOUpdate('ha_mf_device_ipaddress', $device, array('id' => $rowdevice['id']));
				$changedips++;
			} else {
				PDOUpdate('ha_mf_device_ipaddress', array('last_list_date' => date("Y-m-d H:i:s")), array('id' => $rowdevice['id']));
				$devicesimported++;
			}
		}	else {				// New MAC
			$command = array('callerID' => $params['caller']['callerID'], 
							'deviceID' => $params['caller']['callerID'], 
							'messagetypeID' => 'MESS_TYPE_SCHEME',
							'schemeID' => SCHEME_ALERT_HIGH,
							'commandvalue'   => 'New MAC '.$device['mac'].' found,<br/> IP '.$device['ip']);
			$feedback['result']['New MAC'][] = executeCommand($command);
			PDOInsert('ha_mf_device_ipaddress', $device);
			$newmacs++;
		}
		//
		//		Release duplicate IP's
		//
		$mysql="UPDATE `ha_mf_device_ipaddress` SET ". 
				" ip = NULL " .  
				" WHERE mac<>'".$device['mac']."' AND  ip='".$device['ip']."'";  
		PDOExec($mysql);
    }
    if ($showlist) 
		$feedback['message'] .= "</tbody></table>";
	else
		$feedback['message'] = "Devices found: $devicesimported, New MACs: $newmacs, Changed IP's: $changedips";

	$feedback['result_raw'] = $addresses;
	debug($feedback,'feedback');
	return $feedback;

}


function getDeviceListPiHole(&$params) {

	$showlist=false;
	if ($params['caller']['callerID'] == DEVICE_REMOTE) $showlist=true; 

	$feedback['Name'] = 'getDeviceList';
	$feedback['result'] = array();

	$cmd = 'ssh -o UserKnownHostsFile=/dev/null -o StrictHostKeyChecking=no remote-jobs@'.$params['device']['shortdesc'].' -i remote-jobs \'cat /etc/pihole/dhcp.leases\'';
	debug($cmd, 'command');
	$output = shell_exec($cmd);
	debug($output, 'shell_exec');
	$lines =  preg_split('/\n|\r\n?/', $output);
	$feedback['result'][] = $lines;
	debug($lines, 'lines');
//1578843631 54:04:a6:0a:58:20 192.168.2.246 paul-pc 01:54:04:a6:0a:58:20
	$columns = ["leaseIime", "hwaddr", "ip", "name", "ip6"];
	$x = 0;
	foreach ($lines as $line) {
		if (empty($line)) continue;
		$values = explode(' ',$line);
		$pairs = array_combine ( $columns , $values );
//			PDOinsert('os_df', $pairs );
		$addresses[$pairs['hwaddr']] = $pairs;
	}
	debug($addresses, 'addresses');

	$cmd = 'ssh -o UserKnownHostsFile=/dev/null -o StrictHostKeyChecking=no remote-jobs@'.$params['device']['shortdesc'].' -i remote-jobs \'sqlite3 /etc/pihole/pihole-FTL.db "select n.id, a.ip, n.hwaddr, n.interface, a.name, n.firstSeen, max(a.lastSeen),n.numQueries, n.macVendor from network n left join network_addresses a on n.id = a.network_id group by n.id;"\'';
	debug($cmd, 'command');
	$output = shell_exec($cmd);
	debug($output, 'shell_exec');
	$lines =  preg_split('/\n|\r\n?/', $output);
	$feedback['result'][] = $lines;
	debug($lines, 'lines');
//CREATE TABLE network ( id INTEGER PRIMARY KEY NOT NULL, ip TEXT NOT NULL, hwaddr TEXT NOT NULL, interface TEXT NOT NULL, name TEXT, firstSeen INTEGER NOT NULL, lastQuery INTEGER NOT NULL, numQueries INTEGER NOT NULL,macVendor TEXT)
//9|192.168.2.1|f8:32:e4:a7:d5:d0|ens3||1578455520|0|0|ASUSTek COMPUTER INC.
//CREATE TABLE IF NOT EXISTS "network" ( id INTEGER PRIMARY KEY NOT NULL, hwaddr TEXT UNIQUE NOT NULL, interface TEXT NOT NULL, name TEXT, firstSeen INTEGER NOT NULL, lastQuery INTEGER NOT NULL, numQueries INTEGER NOT NULL, macVendor TEXT);

	$columns = ["id", "ip", "hwaddr", "interface", "name", "firstSeen", "lastQuery", "numQueries", "macVendor"];
	$x = 0;
	foreach ($lines as $line) {
		if (empty($line)) continue;
		$values = explode('|',$line);
	debug($columns, 'columns');
	debug($values, 'values');
		$pairs = array_combine ( $columns , $values );
//			PDOinsert('os_df', $pairs );
		if (array_key_exists($pairs['hwaddr'], $addresses)) {
			$addresses[$pairs['hwaddr']] = array_merge( $pairs, $addresses[$pairs['hwaddr']]);
			//if (empty($addresses[$pairs['hwaddr']]['macVendor'])) $addresses[$pairs['hwaddr']]['macVendor'] = $pairs['macVendor'];
		} else {
			$addresses[$pairs['hwaddr']] = $pairs;
		}
		$addresses[$pairs['hwaddr']]['name'] = str_replace('.'.DOMAIN_NAME, '', $addresses[$pairs['hwaddr']]['name']);
	}
	usort($addresses, function($a, $b) {return strnatcmp($a['ip'],$b['ip']);});
	debug($addresses, 'addresses');

// storing

	$newmacs=0;
	$changedips=0;
	$devicesimported=0;
    if ($showlist) $feedback['message']='<HTML><pre><table style="width:100%"><thead><tr><th>Name</th><th>IP</th><th>Vendor</th><th>Mac</th></tr></thead><tbody>';
	
	foreach ($addresses as $address) {
		$device = array();
		$device['name'] = $address['name'];
		$device['vendor'] = (array_key_exists('macVendor',$address) ? $address['macVendor'] : "");
		$device['ip'] = $address['ip'];
		$device['mac'] = $address['hwaddr'];
		$mysql="SELECT * ". 
				" FROM  `ha_mf_device_ipaddress`" .  
				" WHERE mac='".$device['mac']."'";  
		debug($device,'handle->device');
		if ($rowdevice = FetchRow($mysql)) {			// Update existing mac
			if ($showlist) {
					$feedback['message'] .= '<tr><td>'.$device['name'].'</td><td>'.$device['ip'].'</td><td>'.$device['vendor'].'</td><td>'.$device['mac'].'</td></tr>';
			}
			if (strlen($device['name']) == 0) $device['name'] = $rowdevice['name'];
			if ($rowdevice['name'] <> $device['name'] || $rowdevice['ip'] <> $device['ip']) {		// Something changed
				$mysql="SELECT * ". 
					" FROM  `ha_mf_devices`" .  
					" WHERE ipaddressID =".$rowdevice['id'];  
				if ($rowdev = FetchRow($mysql)) $deviceID = $rowdev['id']; else $deviceID = 0;
				$command = array('callerID' => $params['caller']['callerID'], 
								'deviceID' => $deviceID, 
								'messagetypeID' => 'MESS_TYPE_SCHEME',
								'schemeID' => SCHEME_ALERT_NORMAL,
								"commandvalue" => $rowdevice['friendly_name']." MAC: ".$device['mac']."|Old Name: ".$rowdevice['name']."<br/>New Name: "
								              .$device['name']."<br/>Old IP: ".$rowdevice['ip']."<br/>New IP: ".$device['ip']);
				$feedback['result']['Changed'][] = executeCommand($command);
				PDOUpdate('ha_mf_device_ipaddress', $device, array('id' => $rowdevice['id']));
				$changedips++;
			} else {
				PDOUpdate('ha_mf_device_ipaddress', array('last_list_date' => date("Y-m-d H:i:s")), array('id' => $rowdevice['id']));
				$devicesimported++;
			}
		}	else {				// New MAC
			$command = array('callerID' => $params['caller']['callerID'], 
							'deviceID' => $params['caller']['callerID'], 
							'messagetypeID' => 'MESS_TYPE_SCHEME',
							'schemeID' => SCHEME_ALERT_HIGH,
							'commandvalue'   => 'New MAC '.$device['mac'].' found,<br/> IP '.$device['ip']);
			$feedback['result']['New MAC'][] = executeCommand($command);
			PDOInsert('ha_mf_device_ipaddress', $device);
			$newmacs++;
		}
		//
		//		Release duplicate IP's
		//
		$mysql="UPDATE `ha_mf_device_ipaddress` SET ". 
				" ip = NULL " .  
				" WHERE mac<>'".$device['mac']."' AND  ip='".$device['ip']."'";  
		PDOExec($mysql);
    }
    if ($showlist) 
		$feedback['message'] .= "</tbody></table>";
	else
		$feedback['message'] = "Devices found: $devicesimported, New MACs: $newmacs, Changed IP's: $changedips";

	debug($feedback,'feedback');
	return $feedback;

}

function getDrives(&$params) {

        $hostName = $params['device']['shortdesc'];
        $deviceID = $params['device']['id'];
        $feedback['Name'] = 'getDrives';
        $feedback['result'] = array();
        if ($hostName != 'firebox') {
			$cmd = 'ssh -o UserKnownHostsFile=/dev/null -o StrictHostKeyChecking=no remote-jobs@'.$hostName.' -i remote-jobs df -Pkh --local --exclude-type=tmpfs --exclude-type=devtmpfs';
		} else {
			$cmd = 'ssh -o UserKnownHostsFile=/dev/null -o StrictHostKeyChecking=no remote-jobs@'.$hostName.' -i remote-jobs df -Pkht ufs';
		}
        debug($cmd, 'command');
		$feedback['commandstr'] = $cmd;
        $output = shell_exec($cmd);
        debug($output, 'shell_exec');
        $feedback['result'][$hostName] = $output;

        $lines =  preg_split('/\n|\r\n?/', $output);
        $feedback['result'][] = $lines;

        debug($lines, 'lines');

        $columns = ["deviceID", "filesystem", "blocks", "used", "available", "capacity", "mounted"];
        $x = 0;
        foreach ($lines as $line) {
                $x++;
                if ($x == 1) continue;
                if (empty($line)) continue;
                $line = $deviceID.' '.$line;
                $values = preg_split('/\s+/', $line, -1, PREG_SPLIT_NO_EMPTY);
                $values[0] = $deviceID;
                $pairs = array_combine ( $columns , $values );
//                print_r($pairs);
                PDOinsert('os_df', $pairs );

                $properties[substr($pairs['filesystem'], -4).' Size']['value'] = $pairs['blocks'];
                $properties[substr($pairs['filesystem'], -4).' Capacity']['value'] = $pairs['capacity'];

        }

        $params['device']['properties'] = $properties;
        return $feedback;

}

function getDomInfo(&$params) {

        $hostName = $params['device']['shortdesc'];
        $deviceID = $params['device']['id'];
        $feedback['Name'] = 'getDomInfo';
        $feedback['result'] = array();
		$cmd = 'ssh -o UserKnownHostsFile=/dev/null -o StrictHostKeyChecking=no remote-jobs@kvm-host -i remote-jobs sudo /home/remote-jobs/bin/getdominfo '.$hostName;
        debug($cmd, 'command');
        $output = shell_exec($cmd);
        debug($output, 'shell_exec');
        $feedback['result'][$hostName] = $output;

        $lines =  preg_split('/\n|\r\n?/', $output);
        $feedback['result'][] = $lines;

        debug($lines, 'lines');

        $x = 0;
        foreach ($lines as $line) {
                $x++;
                if ($x == 1) continue;
                if (empty($line)) continue;
                $values=preg_split('/\s*:\s*/', trim($line), -1, PREG_SPLIT_NO_EMPTY);
        debug($values, 'values');
                $properties[$values[0]]['value'] = $values[1];
        }

        debug($properties, 'properties');

	$thiscommand['messagetypeID'] = MESS_TYPE_COMMAND;
	$thiscommand['caller'] = $params['caller'];
	$thiscommand['commandID'] = COMMAND_NOP;
	$thiscommand['deviceID'] = $params['deviceID'];
	$thiscommand['device']['id'] =  $params['deviceID'];
	$thiscommand['device']['properties'] = $properties;
	$feedback['SendCommand']=sendCommand($thiscommand); 



        // $params['device']['properties'] = $properties;
        return $feedback;

}

function readNatSessions(&$params) {

	$hostName = $params['device']['shortdesc'];
	$deviceID = $params['device']['id'];
	$feedback['Name'] = 'readNatSessions';
	$feedback['result'] = array();
//	$cmd = 'ssh -o UserKnownHostsFile=/dev/null -o StrictHostKeyChecking=no remote-jobs@'.$hostName.' -i remote-jobs \'/ip firewall connection  print detail terse where src-address~"192.168.2.50:*"\'';
	$cmd = 'ssh -o UserKnownHostsFile=/dev/null -o StrictHostKeyChecking=no remote-jobs@'.$hostName.' -i remote-jobs \'/ip firewall connection  print detail terse \'';
	$feedback['commandstr'] = $cmd;
	$output = shell_exec($cmd);
	debug($output, 'shell_exec');
	if (empty(trim($output))) $feedback['error'] = $output;
	
	$feedback['result_raw'] = $output;
	// $feedback['result'][$hostName] = $output;
	return $feedback;
}

function getPublicIP(&$params) {

	$hostName = $params['device']['shortdesc'];
	$deviceID = $params['device']['id'];
	$feedback['Name'] = 'getPublicIP';
	$feedback['result'] = array();
	$cmd = 'ssh -o UserKnownHostsFile=/dev/null -o StrictHostKeyChecking=no remote-jobs@'.$hostName.' -i remote-jobs \'/ip dhcp-client print terse \'';
	$feedback['commandstr'] = $cmd;
	$output = shell_exec($cmd);
	debug($output, 'shell_exec');
	if (empty(trim($output))) $feedback['error'] = $output;

	preg_match('#address=(.*?)/#',$output, $matches, PREG_OFFSET_CAPTURE);

	debug($matches, 'matches');
		
	$feedback['result_raw'] = $matches[1][0];
	// $feedback['result'][$hostName] = $output;
	return $feedback;
}

function createBackup(&$params) {

        $hostName = $params['device']['shortdesc'];
        $deviceID = $params['device']['id'];
        $feedback['Name'] = 'createBackup';
        $feedback['result'] = array();
		$cmd = 'ssh -o UserKnownHostsFile=/dev/null -o StrictHostKeyChecking=no remote-jobs@'.$params['device']['shortdesc'].' -i remote-jobs /system backup save name backup.backup';
        debug($cmd, 'command');
        $output = shell_exec($cmd);
        debug($output, 'shell_exec');
        $feedback['result'][$hostName] = $output;

        $lines =  preg_split('/\n|\r\n?/', $output);
        $feedback['result'][] = $lines;

        debug($lines, 'lines');

        // $x = 0;
        // foreach ($lines as $line) {
                // $x++;
                // if ($x == 1) continue;
                // if (empty($line)) continue;
                // $values=preg_split('/\s*:\s*/', trim($line), -1, PREG_SPLIT_NO_EMPTY);
        // debug($values, 'values');
                // $properties[$values[0]]['value'] = $values[1];
        // }

        // debug($properties, 'properties');

	// $thiscommand['messagetypeID'] = MESS_TYPE_COMMAND;
	// $thiscommand['caller'] = $params['caller'];
	// $thiscommand['commandID'] = COMMAND_NOP;
	// $thiscommand['deviceID'] = $params['deviceID'];
	// $thiscommand['device']['id'] =  $params['deviceID'];
	// $thiscommand['device']['properties'] = $properties;
	// $feedback['SendCommand']=sendCommand($thiscommand); 



        // $params['device']['properties'] = $properties;
        return $feedback;

}

function storeNatSessions(&$params) {


   	$mysql = "UPDATE `net_sessions` SET `active` = '0';";
	$feedback['result'][] =  PDOExec($mysql) ." Rows affected";


	$feedback['Name'] = 'updateNatSessions';

	$lines =  preg_split('/\n|\r\n?/', $params['commandvalue']);
	debug($lines, 'lines');

//tcp      6 1393 ESTABLISHED src=192.168.2.24 dst=54.236.3.170 sport=2644 dport=443 packets=2 bytes=84 src=54.236.3.170 dst=71.8.81.33 sport=443 dport=2644 packets=1 bytes=44 [ASSURED] mark=0 use=1
// 0  SAC Fs  protocol=tcp src-address=192.168.2.248:32815 dst-address=74.125.138.188:5228 reply-src-address=74.125.138.188:5228 reply-dst-address=174.85.202.204:32815 tcp-state=established timeout=23h49m47s orig-packets=346 orig-bytes=25 675 orig-fasttrack-packets=48 orig-fasttrack-bytes=5 395 repl-packets=242 repl-bytes=56 568 repl-fasttrack-packets=48 repl-fasttrack-bytes=20 460 orig-rate=0bps repl-rate=0bps

    // [0] => class
    // [1] => 0
    // [2] => SAC
    // [3] => Fs
    // [4] => protocol
    // [5] => tcp
    // [6] => src-address
    // [7] => 192.168.2.248
    // [8] => 32815
    // [9] => dst-address
    // [10] => 74.125.138.188
    // [11] => 5228
    // [12] => reply-src-address
    // [13] => 74.125.138.188
    // [14] => 5228
    // [15] => reply-dst-address
    // [16] => 174.85.202.204
    // [17] => 32815
    // [18] => tcp-state
    // [19] => established
    // [20] => timeout
    // [21] => 23h47m24s
    // [22] => orig-packets
    // [23] => 350
    // [24] => orig-bytes
    // [25] => 25
    // [26] => 941
    // [27] => orig-fasttrack-packets
    // [28] => 48
    // [29] => orig-fasttrack-bytes
    // [30] => 5
    // [31] => 395
    // [32] => repl-packets
    // [33] => 244
    // [34] => repl-bytes
    // [35] => 56
    // [36] => 726
    // [37] => repl-fasttrack-packets
    // [38] => 48
    // [39] => repl-fasttrack-bytes
    // [40] => 20
    // [41] => 460
    // [42] => orig-rate
    // [43] => 0bps
    // [44] => repl-rate
    // [45] => 0bps


	$columns = ["class", "protocol", "TCPstate",  "local_address",  "remote_address", "local_port", "remote_port", "packets", "bytes", "flags"]; 
	foreach ($lines as $line) {
			if (empty($line)) continue;

			$values = array();
			$tvalues = preg_split('/[\s]+/', $line, -1, PREG_SPLIT_NO_EMPTY);
			debug ($tvalues , 'tvalues');
			
			$x = 0;
			$svalues = array();
			foreach ($tvalues as $tvalue) {
				$x++;
				if ($x == 1) continue;
				$temp = preg_split('/=/', $tvalue);
				if (array_key_exists(1,$temp)) { 
					$svalues[$temp[0]] = $temp[1];
				} else {
					if ($x == 2) $svalues['flags'] = $temp[0];
					if ($x == 3) $svalues['flags'] = $svalues['flags'].$temp[0];
					if ($x == 4) $svalues['flags'] = $svalues['flags'].$temp[0];
				}
			}
			debug ($svalues , 'svalues');

			$pairs = array();
			$pairs['protocol'] = $svalues['protocol'];
			if ($svalues['protocol'] == 'tcp')  {
				$state=true;
				//if ($tvalues['12'] == 'reply-src-address') $state=false;
				$pairs['TCPstate'] = $svalues['tcp-state'];
				$pairs['local_address'] = preg_split('/:/', $svalues['src-address'])[0];
				// $values[] = ($state ? $tvalues[19] : $tvalues[18]);
				$pairs['remote_address'] = preg_split('/:/', $svalues['reply-src-address'])[0];
				$pairs['local_port'] =  preg_split('/:/', $svalues['src-address'])[1];
				// $values[] = ($state ? $tvalues[23] : $tvalues[22]);
				$pairs['remote_port'] = preg_split('/:/', $svalues['reply-src-address'])[1];
				$pairs['packets'] = $svalues['orig-packets'];
				$pairs['bytes'] = $svalues['orig-bytes'];
				$pairs['flags'] = $svalues['flags'];
			} elseif ($svalues['protocol'] == 'udp') {		// udp 
				$state=true;
				//if ($tvalues['12'] == 'reply-src-address') $state=false;
				$pairs['TCPstate'] = "";
				$pairs['local_address'] = preg_split('/:/', $svalues['src-address'])[0];
				// $values[] = ($state ? $tvalues[19] : $tvalues[18]);
				$pairs['remote_address'] = preg_split('/:/', $svalues['reply-src-address'])[0];
				$pairs['local_port'] =  preg_split('/:/', $svalues['src-address'])[1];
				// $values[] = ($state ? $tvalues[23] : $tvalues[22]);
				$pairs['remote_port'] = preg_split('/:/', $svalues['reply-src-address'])[1];
				$pairs['packets'] = $svalues['orig-packets'];
				$pairs['bytes'] = $svalues['orig-bytes'];
				$pairs['flags'] = $svalues['flags'];
			} else { 	// icmp
				//if ($tvalues['12'] == 'reply-src-address') $state=false;
				$pairs['TCPstate'] = "";
				$pairs['local_address'] = $svalues['src-address'];
				$pairs['remote_address'] = $svalues['reply-src-address'];
				$pairs['local_port'] =  '';
				$pairs['remote_port'] = '';
				$pairs['packets'] = $svalues['orig-packets'];
				$pairs['bytes'] = $svalues['orig-bytes'];
				$pairs['flags'] = $svalues['flags'];
			}

			$pairs['local_name']= gethostbyaddr($pairs['local_address']);
			// $pairs['remote_name']= gethostbyaddr($pairs['remote_address']);
			// $pairs['local_name']= $pairs['local_address'];
			// $pairs['remote_name']= $pairs['remote_address'];
			$pairs['active']= 1; 

			$pairs['class'] = '';
			if (substr($pairs['local_address'],0,strlen(MY_SUBNET)) != MY_SUBNET && substr($pairs['remote_address'],0,strlen(MY_SUBNET)) == MY_SUBNET) {
				$temp = $pairs['local_address']; $pairs['local_address'] = $pairs['remote_address']; $pairs['remote_address'] = $temp;
				$temp = $pairs['local_port']; $pairs['local_port'] = $pairs['remote_port']; $pairs['remote_port'] = $temp;
				$temp = $pairs['local_name']; $pairs['local_name'] = $pairs['remote_name']; $pairs['remote_name'] = $temp;
				$pairs['class'] = 'alert-danger';
			}
			debug($pairs, 'pairs');

			PDOupsert('`net_sessions`', $pairs, array('remote_address' => $pairs['remote_address'], 'remote_port' => $pairs['remote_port'], 'local_address' => $pairs['local_address'], 'local_port' => $pairs['local_port']));

			// $properties[substr($pairs['filesystem'], -4).' Size']['value'] = $pairs['blocks'];
			// $properties[substr($pairs['filesystem'], -4).' Capacity']['value'] = $pairs['capacity'];

	}

	// $params['device']['properties'] = $properties;

	$feedback['result']['message'] = count($lines)." Sesstions read".CRLF.MoveHistory()." Sessions moved to History".CRLF;


	return $feedback;

}

// function templateFunction(&$params) {
//      debug($params, 'params');

        // $feedback['Name'] = 'templateFunction';
        // $feedback['commandstr'] = "I send this";
        // $feedback['result'] = array();
        // $feedback['message'] = "all good";
        // if () $feedback['error'] = "Not so good";

        //      debug($stepValue, 'stepValue');

        //      debug($feedback, 'feedback');
        // return $feedback;
// }


function moveHistory() {
    $mysql = "INSERT INTO `net_sessions_history` (`sessionid`, `protocol`, `local_address`, `local_name`, `local_port`, `remote_address`, `remote_name`, `remote_domain`, `remote_port`, `TCPstate`, `packets`, `bytes`, `flags`, `raw`, `active`, `class`,  `createdate` ) SELECT`sessionid`, `protocol`, `local_address`, `local_name`, `local_port`, `remote_address`, `remote_name`, `remote_domain`, `remote_port`, `TCPstate`, `packets`, `bytes`, `flags`, `raw`, `active`, `class`,  `createdate` FROM `net_sessions` WHERE active=0;";
	$result = PDOExec($mysql);
    $mysql = "DELETE FROM `net_sessions` WHERE active=0;";
	$num_rows = PDOExec($mysql);
	return $num_rows;
}

function speedTest(&$params) {
	set_time_limit(0);
	debug($params, 'params');
	$feedback['Name'] = 'speedTest';
	$feedback['result'] = array();
	// $params = '--progress=no --format=json';
	$cmd = getPath().'/bin/speedTest.sh';
	$feedback['commandstr'] = $cmd;
	exec($cmd, $output, $exitCode);
	debug($output, 'exec');
	if ($exitCode != 0) {
		$feedback['error'] = "Error speedTest $exitCode";
	}
	$feedback['exitCode'] = $exitCode;
	debug($feedback, 'feedback');

	// $feedback['result_raw'] = $output;
	$index = 0;
	$result = json_decode($output[0], true);
	debug ($result);
	$params['device']['properties']['Download']['value'] = (int)$result['download'];
	$params['device']['properties']['Upload']['value'] = (int)$result['upload'];
	$params['device']['properties']['Ping']['value'] = (int)$result['ping'];

	$feedback['result']['speedTest'] = $result;
	debug ($feedback, 'feedback');
	return $feedback;
}

function updateUnifi(&$params) {

	debug($params, 'params');
	$addresses = $params['last___result'];
	unset($params['last___message']);

	$feedback['Name'] = 'updateUnifi';

	debug($addresses, 'addresses');
	$query = '';
	foreach ($addresses as $address) {
		$query .= 'db.user.findOneAndUpdate({"mac" : /^'.$address['hwaddr'].'$/i},{$set:{"name": "'.$address['name'].'"}});'."\n";
	}

	$file = './query.js';
	if (file_put_contents($file, $query) === false) {
		$feedback['error'] = "Error writing query.js";	
		debug($feedback, 'feedback');
		return $feedback;
	}
	$feedback['result'][] = $query;

	$cmd = 'scp -o UserKnownHostsFile=/dev/null -o StrictHostKeyChecking=no -i remote-jobs ./query.js remote-jobs@'.$params['device']['shortdesc'].':~/query.js';
	$feedback['commandstr'] = $cmd.CRLF;
	debug($cmd, 'command');
	$output = shell_exec($cmd);
	debug($output, 'shell_exec');
	$lines =  preg_split('/\n|\r\n?/', $output);
	$feedback['result'][] = $lines;
	debug($lines, 'lines');

	// ace is db
	$cmd = 'ssh -o UserKnownHostsFile=/dev/null -o StrictHostKeyChecking=no remote-jobs@'.$params['device']['shortdesc'].' -i remote-jobs \'mongo --port 27117 ace query.js\'';
	$feedback['commandstr'] .= $cmd;
	debug($cmd, 'command');
	$output = shell_exec($cmd);
	debug($output, 'shell_exec');
	$lines =  preg_split('/\n|\r\n?/', $output);
	$feedback['result'][] = $lines;
	debug($lines, 'lines');


	// debug($feedback, 'feedback');
	return $feedback;
}
?>
