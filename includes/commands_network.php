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

	$feedback['Name'] = 'GetDeviceList';
	$feedback['result'] = array();

	$cmd = 'ssh -o UserKnownHostsFile=/dev/null -o StrictHostKeyChecking=no remote-jobs@'.$params['device']['shortdesc'].' -i remote-jobs \'cat /etc/pihole/dhcp.leases\'';
	debug($cmd, 'command');
	$output = shell_exec($cmd);
	debug($output, 'shell_exec');
	$lines = explode(PHP_EOL, $output);
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

	$cmd = 'ssh -o UserKnownHostsFile=/dev/null -o StrictHostKeyChecking=no remote-jobs@'.$params['device']['shortdesc'].' -i remote-jobs \'sqlite3 /etc/pihole/pihole-FTL.db "SELECT * FROM network"\'';
	debug($cmd, 'command');
	$output = shell_exec($cmd);
	debug($output, 'shell_exec');
	$lines = explode(PHP_EOL, $output);
	$feedback['result'][] = $lines;
	debug($lines, 'lines');
//CREATE TABLE network ( id INTEGER PRIMARY KEY NOT NULL, ip TEXT NOT NULL, hwaddr TEXT NOT NULL, interface TEXT NOT NULL, name TEXT, firstSeen INTEGER NOT NULL, lastQuery INTEGER NOT NULL, numQueries INTEGER NOT NULL,macVendor TEXT)
//9|192.168.2.1|f8:32:e4:a7:d5:d0|ens3||1578455520|0|0|ASUSTek COMPUTER INC.
	$columns = ["id", "ip", "hwaddr", "interface", "name", "firstSeen", "lastQuery", "numQueries", "macVendor"];
	$x = 0;
	foreach ($lines as $line) {
		if (empty($line)) continue;
		$values = explode('|',$line);
		$pairs = array_combine ( $columns , $values );
//			PDOinsert('os_df', $pairs );
		if (array_key_exists($pairs['hwaddr'], $addresses)) {
			$addresses[$pairs['hwaddr']] = array_merge( $pairs, $addresses[$pairs['hwaddr']]);
			//if (empty($addresses[$pairs['hwaddr']]['macVendor'])) $addresses[$pairs['hwaddr']]['macVendor'] = $pairs['macVendor'];
		} else {
			$addresses[$pairs['hwaddr']] = $pairs;
		}
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
								'schemeID' => SCHEME_ALERT_LOW,
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

        $deviceID = $params['device']['id'];
        $feedback['Name'] = 'getDrives';
        $feedback['result'] = array();
        if ($hostName != 'firebox') {
			$cmd = 'ssh -o UserKnownHostsFile=/dev/null -o StrictHostKeyChecking=no remote-jobs@'.$hostName.' -i remote-jobs df -Pkh --local --exclude-type=tmpfs --exclude-type=devtmpfs';
		} else {
			$cmd = 'ssh -o UserKnownHostsFile=/dev/null -o StrictHostKeyChecking=no remote-jobs@'.$hostName.' -i remote-jobs df -Pkht ufs';
		}
        debug($cmd, 'command');
        $output = shell_exec($cmd);
        debug($output, 'shell_exec');
        $feedback['result'][$hostName] = $output;

        $lines = explode(PHP_EOL, $output);
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

function storeNatSessions(&$params) {


   	$mysql = "UPDATE `net_sessions` SET `active` = '0';";
	$feedback['result'][] =  PDOExec($mysql) ." Rows affected";


	$feedback['Name'] = 'updateNatSessions';

	$lines = explode(PHP_EOL, $params['commandvalue']);
	debug($lines, 'lines');

//tcp      6 1393 ESTABLISHED src=192.168.2.24 dst=54.236.3.170 sport=2644 dport=443 packets=2 bytes=84 src=54.236.3.170 dst=71.8.81.33 sport=443 dport=2644 packets=1 bytes=44 [ASSURED] mark=0 use=1


	$columns = ["class", "protocol", "TCPstate",  "local_address",  "remote_address", "local_port", "remote_port", "packets", "bytes", "flags"]; 
	$x = 0;
	foreach ($lines as $line) {
			if (empty($line)) continue;
			$x++;
			$line = 'class '.$line;
			$values = array();
			$tvalues = preg_split('/[=\s]+/', $line, -1, PREG_SPLIT_NO_EMPTY);
			debug($tvalues, 'tvalues');
			$values[] = $tvalues[0];
			$values[] = $tvalues[1];
			if ($tvalues[1] == 'tcp')  {
				$state=true;
				if ($tvalues['17'] == 'src') $state=false;
				$values[] = $tvalues[4];
				$values[] = $tvalues[6];
				$values[] = ($state ? $tvalues[19] : $tvalues[18]);
				$values[] = $tvalues[10];
				$values[] = ($state ? $tvalues[23] : $tvalues[22]);
				$values[] = $tvalues[14];
				$values[] = $tvalues[16];
				$values[] = $tvalues[29];
			} else {		// udp - icmp
				$state=true;
				if ($tvalues['16'] == 'src') $state=false;
				$values[] = ($state ? $tvalues[16] : "" );
				$values[] = $tvalues[5];
				$values[] = ($state ? $tvalues[18] : $tvalues[17]);
				$values[] = $tvalues[9];
				$values[] = ($state ? $tvalues[22] : $tvalues[21]);
				$values[] = $tvalues[13];
				$values[] = $tvalues[15];
				$values[] = $tvalues[28];
			}
			debug($values, 'values');
			debug($columns, 'columns');
			$pairs = array_combine ( $columns , $values );

			$pairs['local_name']= gethostbyaddr($pairs['local_address']); 
			$pairs['remote_name']= gethostbyaddr($pairs['remote_address']); 
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
    $mysql = "INSERT INTO `net_sessions_history` SELECT * FROM `net_sessions` WHERE active=0;";
	$result = PDOExec($mysql);
    $mysql = "DELETE FROM `net_sessions` WHERE active=0;";
	$num_rows = PDOExec($mysql);
	return $num_rows;
}
?>
