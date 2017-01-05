<?php
define("ALERT_NETWORK_DEVICE_CHANGE", 218);
define("ALERT_NEW_NETWORK_DEVICE", 219);
define("ALERT_UNKNOWN_IP_FOUND", 217);

function natSessions($params = Null) {
	$result['message']=ImportSessions()." Nat Sessions Read <br/>\r\n";
	$result['message'].=MoveHistory()." Sessions moved to History <br/>\r\n";
	return $result;
}

function deviceList($showlist) {

	$result=GetDeviceList($showlist)." Devices Read <br/>\r\n";
	return $result;
}


function trHost() {
	$mysql="SELECT `id`,`ip` ". 
			" FROM  `net_iplookup`"   .
			" WHERE ip=name and processed<>1 LIMIT 5000";  

	$res = mysql_query($mysql);

	if (!$res) mySqlError();

	// While a row of data exists, put that row in $row as an associative array
	// Note: If you're expecting just one row, no need to use a loop
	// Note: If you put extract($row); inside the following loop, you'll
	//       then create $userid, $fullname, and $userstatus
	while ($row = mysql_fetch_assoc($res)) {
		$ip =$row['ip'];

		$hostname = gethostbyaddr($ip);
		echo $ip." ".$hostname."</br>";
	   	$mysql = "UPDATE `net_iplookup` SET `processed` = 1, `name`='".$hostname."' WHERE id =".$row['id'];
		if (!mysql_query($mysql))  mySqlError($mysql);
//		usleep(2000);
 	}
}


function MoveHistory() {
    $mysql="INSERT INTO `net_sessions_history` SELECT * FROM `net_sessions` WHERE active=0;";
	$result=mysql_query($mysql);
	if (!$result) mySqlError($mysql);	
	$num_rows = mysql_affected_rows();
    $mysql="DELETE FROM `net_sessions` WHERE active=0;";
	if (!mysql_query($mysql)) mySqlError($mysql);
	return $num_rows;
}

function GetSessions() {

		// Get myIP
	if (!($myip = FetchRow('SELECT ip FROM `ha_mf_device_ipaddress`  WHERE name="PublicIP"')['ip'])) {
		echo "Could not find 'PublicIP'";
		exit; 
	}
	//echo "MyIP:".$myip.CRLF;
    $dir = (empty($_SERVER['DOCUMENT_ROOT']) ? '/home/pvloon/php' : '.');
    $output = shell_exec($dir.'/telnetcmd '.FIREWALL_USER.' '.FIREWALL_PASSWORD);
    
    $sessions_raw = explode("\n", $output); 
    // unset($sess[0]);
	end($sessions_raw);
	unset($sessions_raw[key($sessions_raw)]);

	foreach ($sessions_raw as $row) {
		$row_split = preg_split('/[\s:]+/', $row);

	// echo "<pre>";
	//echo $row.CRLF;
		unset($session);
		$session['raw'] = $row;
		$session['protocol'] = $row_split[1];
		if (!($session['local_address'] = get_string_between($row, 'src=', ' '))) continue;

		$session['local_port'] = get_string_between($row, 'sport=', ' ');
		if (substr($session['local_address'],0,strlen(MY_SUBNET)) <> MY_SUBNET &&
				substr($session['local_address'],0,strlen(MY_VPN_SUBNET)) <> MY_VPN_SUBNET &&
				$session['local_address'] <> $myip)	{
           	$session['class'] = SEVERITY_DANGER_CLASS;
			$session['remote_address'] = $session['local_address'];
			$session['remote_port'] = $session['local_port'];
			$session['local_address'] = get_string_between($row, 'src=', ' ', 2);
			$session['local_port'] = get_string_between($row, 'sport=', ' ', 2);
			$session['packets']  = get_string_between($row, 'packets=', ' ', 2);
			$session['bytes'] = get_string_between($row, 'bytes=', ' ', 2); 
		}	else {

			$session['remote_address'] = get_string_between($row, 'dst=', ' ');
			$session['remote_port'] = get_string_between($row, 'dport=', ' ');
			$session['packets']  = get_string_between($row, 'packets=', ' ');
			$session['bytes'] = get_string_between($row, 'bytes=', ' '); 
		}
		if ($session['protocol']==6) $session['TCPstate'] = $row_split[3];
		if ($session['protocol']==6) $session['flags'] = $row_split[16];
		$session['active'] = 1;
	// print_r($session);
	// echo "</pre>";
		$sessions[] = $session;

	}
	
    // Protocol name.
    // Protocol number. (6 = TCP. 17 = UDP.)
    // Seconds until this entry expires.
    // TCP only: TCP connection state.
    // Source address of “original”-side packets (packets from the side that initiated the connection).
    // Destination address of original-side packets.
    // Source port of original-side packets.
    // Destination port of original-side packets.
    // “[UNREPLIED]”, if this connection has not seen traffic in both directions. Otherwise not present.
    // Source address of “reply”-side packets (packets from the side that received the connection).
    // Destination address of reply-side packets.
    // Source port of reply-side packets.
    // Destination port of reply-side packets.
    // “[ASSURED]”, if this connection has seen traffic in both directions (for UDP) or an ACK in an ESTABLISHED connection (for TCP). Otherwise not present.
    // Use count of this connection structure. 
	
// echo "<pre>";
// print_r($sessions);
// echo "</pre>";
// exit;
       
	return $sessions;
}

function findRemoteName($ip) {
	$mysql="SELECT * FROM  `net_iplookup` WHERE ip='".$ip."';";  

	$res = mysql_query($mysql);

	if (!$res) mySqlError();

	if ($row=FetchRow($mysql)) {
		$last = new DateTime($row['updatedate']);
		$nowdt = new DateTime();
		/*echo "<pre>";
		print_r ($last);
		print_r ($nowdt);
		echo  $nowdt->diff($last, true)->days."</br>";
		echo "</pre>";*/
		if ($nowdt->diff($last, true)->days < 30) {
			return $row['name'];			
		}
	}
	$hostname = gethostbyaddr($ip);
	//echo $ip." ".$hostname."</br>";

	if ($row) {
		$processed = $row['processed'] + 1;
	   	$mysql = "UPDATE `net_iplookup` SET `processed` =".$processed.", `name`='".$hostname."' WHERE id =".$row['id'];
		if (!mysql_query($mysql))  mySqlError($mysql);
	} else {
		$mysql = "INSERT INTO `net_iplookup` (ip, name, processed) values ('".$ip."','".$hostname."', 1)";
		if (!mysql_query($mysql))  mySqlError($mysql);
	}

	return  $hostname;
}

function findLocalName($ip) {
	if (!defined('MY_DEVICE_ID')) define( 'MY_DEVICE_ID', DEVICE_REMOTE );

	$mysql="SELECT * FROM  `ha_mf_device_ipaddress` WHERE ip='".$ip."';";  

	$res = mysql_query($mysql);

	if (!$res) mySqlError();

	if ($row=FetchRow($mysql)) {
		return  $row['friendly_name'];
	} else {
		$params = array('callerID' => MY_DEVICE_ID, 
						'deviceID' => MY_DEVICE_ID, 
						'messagetypeID' => 'MESS_TYPE_SCHEME',
						'schemeID' => ALERT_UNKNOWN_IP_FOUND,
						'ha_alerts___l1' => 'IP Address', 
						'ha_alerts___v1' => $ip);
		print_r(executeCommand($params));
		$mysql= 'INSERT INTO `ha_mf_device_ipaddress` (
			`ip` ,
			`mac` ,
			`name` ,
			`connection` ,
			`trusted`
			)
		VALUES (' . 
			'"'.$ip.'",'.
			'"",'.
			'"**Unknown",'.
			'"",'.
			'"0");';
		if (!mysql_query($mysql)) mySqlError($mysql);	
	}
	return "***Unknown";
}


function FindAddress($ip) {
	$mysql="SELECT * ". 
			" FROM  `net_iplookup`" .  
			" WHERE ip='".$ip."'";  
	if ($resset=FetchRow($mysql)) {
		return $resset['id'];
	} else { 			// insert and return id
	    $mysql="INSERT INTO `net_iplookup` (ip, name) values ('".$ip."','".$ip."')";
		$result=mysql_query($mysql);
		if (!$result) mySqlError($mysql);	
		return  mysql_insert_id();
	}
}

function ImportSessions() {
 
	$sessionsresponse=GetSessions();
//echo "<pre>";
//print_r($sessionsresponse);
//echo "</pre>";

	if ($sessionsresponse < 0) return -1;
	
	$sessionsimported=0;
   	$mysql = "UPDATE `net_sessions` SET `active` = '0';";
	if (!mysql_query($mysql))  mySqlError($mysql);
    
	foreach ($sessionsresponse as $session) {
    	
		$mysql="SELECT * ". 
				" FROM  `net_sessions`" .  
				" WHERE local_address='".$session['local_address']."'"." AND local_port='".$session['local_port']."'".
                                     " AND remote_address='".$session['remote_address']."'"." AND remote_port='".$session['remote_port']."'";  
		$ressessions=mysql_query($mysql);
		if ($dbsession=mysql_fetch_array($ressessions)) {
			// check for same ip's???
				$mysql="DELETE ". 
						" FROM  `net_sessions`" .  
						" WHERE id='".$dbsession['id']."'";  
				if (!mysql_query($mysql)) mySqlError($mysql);
			}


		$local = $session['local_address'];
		$session['local_name'] = findLocalName($session['local_address']); 
		$remote = $session['remote_address'];
		$session['remote_name'] = findRemoteName($session['remote_address']); 
		$session['createdate'] = date('Y-m-d H:i:s');
		PDOinsert('net_sessions', $session);
		$sessionsimported++;

	}
    
	return $sessionsimported;
}

function GetDeviceList($showlist = false) {
	if (!defined('MY_DEVICE_ID')) define( 'MY_DEVICE_ID', DEVICE_REMOTE );

	
	$get = RestClient::get('http://icanhazip.com/');

    if ($get->getresponsecode() == 200) {
		$myip = trim($get->getresponse());
		//echo ">".$myip."<";
		$mysql='UPDATE `ha_mf_device_ipaddress` SET ip = "'.$myip.'" WHERE name="PublicIP"';  
		if (!mysql_query($mysql)) mySqlError($mysql);
		//exit;
	} else {
		echo "Could not connect to http://icanhazip.com/".CRLF;
	}

	
	$post = RestClient::get("http://192.168.2.1/update_clients.asp",null,Array( 'method' => "BASIC", 'username' => FIREWALL_USER ,'password' => FIREWALL_PASSWORD));
	$response= $post->getResponse();
//echo $response;
	// refresh for next run
	$post = RestClient::get("http://192.168.2.1/apply.cgi?action_mode=refresh_networkmap&action_script=&action_wait=5&current_page=device-map%2Fclients.asp&next_page=device-map%2Fclients.asp",null,Array( 'method' => "BASIC", 'username' => FIREWALL_USER ,'password' => FIREWALL_PASSWORD));
	$post = RestClient::get("http://192.168.2.1/Logout.asp");

	$pattern = "/fromNetworkmapd: '(.*?)'./si";
	
	
/* 
 * 	There is more info here, wired wireless, DB, 2 or 5gb ...
 * 
?originDataTmp = originData;
originData = {
customList: decodeURIComponent('').replace(/>/g, ">").replace(/</g, "<").split('<'),
asusDevice: decodeURIComponent('%3C3%3ERT%2DN65U%3E192%2E168%2E2%2E1%3E74%3AD0%3A2B%3A8B%3A08%3AA4%3E0%3E%3E%3Evlohome%3E255%2E255%2E255%2E0%3C3%3ERT%2DN10P%3E192%2E168%2E2%2E2%3EE0%3A3F%3A49%3AF0%3AF7%3AB8%3E0%3E%3E%3Evlohome1%3E255%2E255%2E255%2E0').replace(/>/g, ">").replace(/</g, "<").split('<'),
fromDHCPLease: '',
staticList: decodeURIComponent('').replace(/>/g, ">").replace(/</g, "<").split('<'),
fromNetworkmapd: '<6>>192.168.2.110>54:04:A6:0A:58:20>0>0>0<0>>192.168.2.2>E0:3F:49:F0:F7:B8>0>0>0<0>>192.168.2.101>52:54:00:62:E5:67>0>0>0<0>>192.168.2.100>00:00:00:00:00:01>0>0>0<0>>192.168.2.102>52:54:00:30:98:3C>0>0>0<0>>192.168.2.104>54:04:A6:80:F9:69>0>0>0<0>>192.168.2.120>00:1E:C0:11:D5:AA>0>0>0<0>>192.168.2.125>00:0E:F3:1E:CC:75>0>0>0<0>>192.168.2.131>00:01:4A:2F:B6:E6>0>0>0<0>>192.168.2.132>00:18:1A:0C:30:3E>0>0>0<0>>192.168.2.162>F0:25:B7:A3:44:56>0>0>0<0>>192.168.2.195>4C:82:CF:F0:E6:BE>0>0>0<0>>192.168.2.127>14:7D:C5:74:11:F9>0>0>0<0>>192.168.2.128>88:30:8A:1A:0A:02>0>0>0<0>>192.168.2.218>60:A1:0A:C0:40:61>0>0>0<0>>192.168.2.122>44:A7:CF:51:1E:D6>0>0>0<0>>192.168.2.138>00:14:D1:7A:E3:77>0>0>0'.replace(/>/g, ">").replace(/</g, "<").split('<'),
fromBWDPI: ''.replace(/>/g, ">").replace(/</g, "<").split('<'),
wlList_2g: [["00:14:D1:7A:E3:77", "Yes", "", "-62"], ["F0:25:B7:A3:44:56", "Yes", "", "-54"], ["00:18:1A:0C:30:3E", "Yes", "", "-79"], ["00:1E:C0:11:D5:AA", "Yes", "", "-79"], ["4C:82:CF:F0:E6:BE", "Yes", "", "-66"], ["14:7D:C5:74:11:F9", "Yes", "", "-63"], ["88:30:8A:1A:0A:02", "Yes", "", "-68"], ["60:A1:0A:C0:40:61", "Yes", "", "-77"], ["44:A7:CF:51:1E:D6", "Yes", "", "-53"]],
wlList_5g: [],
qosRuleList: decodeURIComponent('%3CWeb%20Surf%3E%3E80%3Etcp%3E0%7E512%3E0%3CHTTPS%3E%3E443%3Etcp%3E0%7E512%3E0%3CFile%20Transfer%3E%3E80%3Etcp%3E512%7E%3E3%3CFile%20Transfer%3E%3E443%3Etcp%3E512%7E%3E3').replace(/>/g, ">").replace(/</g, "<").split('<')
}
networkmap_fullscan = '0';
if(networkmap_fullscan == 1) genClientList();
*/



 
	$noresult = preg_match_all($pattern, $response, $matches);
//echo "<pre>";
//echo $response;
//print_r($matches);
	$lasterr = preg_last_error();
	if (array_key_exists(0, $matches[0]) and $matches[0][0] != '')
    {
        $t1 = explode("<", $matches[1][0]);
    } else {
    	echo $response;
		return -1;
    } 
   	
	unset($t1[0]);
	
//print_r($t1);

	$loop=0;
	$deviceslist = array();
	foreach($t1 as $row) {
		$deviceslist[] = explode(">", $row);
	}


//echo "<pre>";
//print_r($deviceslist);
//echo "</pre>";

	usort($deviceslist, function($a, $b) {
	    return ip2long($a[2]) - ip2long($b[2]);
	});

	$devicesimported=0;
        if ($showlist) echo "<pre><table><thead><tr><th>Name</th><th>IP</th><th>Connection</th><th>Mac</th></tr></thead><tbody>";
	foreach ($deviceslist as $device) {
		$name = $device[1];
		$ip = $device[2];
		$connection = $device[4];
		$mac = strtoupper($device[3]);
		$mysql="SELECT * ". 
				" FROM  `ha_mf_device_ipaddress`" .  
				" WHERE mac='".$mac."'";  
		$resdevices = mysql_query($mysql);
		if ($rowdevice = mysql_fetch_array($resdevices)) {			// Update existing mac
                        if ($showlist) {
                                echo "<tr><td>$name</td><td>$ip</td><td>$connection</td><td>$mac</td></tr>";
                        }
 		// does not work anymore on name, names are empty
			if (strlen($name) == 0) $name = $rowdevice['name'];
//			if ($rowdevice['name'] <> $name || $rowdevice['ip'] <> $ip || $rowdevice['connection'] <> $connection) {		// Something changed
			if ($rowdevice['name'] <> $name || $rowdevice['ip'] <> $ip) {		// Something changed
				$mysql="SELECT * ". 
					" FROM  `ha_mf_devices`" .  
					" WHERE ipaddressID =".$rowdevice['id'];  
				$resdev = mysql_query($mysql);
				if ($rowdev = mysql_fetch_array($resdev)) $deviceID = $rowdev['id']; else $deviceID = 0;
				$params = array('callerID' => MY_DEVICE_ID, 
								'deviceID' => $deviceID, 
								'messagetypeID' => 'MESS_TYPE_SCHEME',
								'schemeID' => ALERT_NETWORK_DEVICE_CHANGE,
								"ha_alerts___l1" => $mac, 
								"ha_alerts___l2" => $rowdevice['connection'], 
								"ha_alerts___l3" => $connection, 
								"ha_alerts___l4" => $deviceID, 
								"ha_alerts___v1" => $rowdevice['name'],
								"ha_alerts___v2" => $name, 
								"ha_alerts___v3" => $rowdevice['ip'],
								"ha_alerts___v4" => $ip);
				print_r(executeCommand($params));
				$mysql= 'UPDATE `ha_mf_device_ipaddress` SET `mac` = "'. $mac .'", 
					`name` = "'. $name.'", `ip` = "'.$ip.'" , `connection` = "'.$connection.'", `last_list_date` = NOW() WHERE `ha_mf_device_ipaddress`.`id` = '.$rowdevice['id'];
				//echo $mysql;
				if (!mysql_query($mysql)) mySqlError($mysql);	
			} else {
				$mysql= 'UPDATE `ha_mf_device_ipaddress` SET 
					`last_list_date` = NOW() WHERE `ha_mf_device_ipaddress`.`id` = '.$rowdevice['id'];
				if (!mysql_query($mysql)) mySqlError($mysql);	
			}
		}	else {				// New MAC
			$params = array('callerID' => MY_DEVICE_ID, 
							'deviceID' => MY_DEVICE_ID, 
							'messagetypeID' => 'MESS_TYPE_SCHEME',
							'schemeID' => ALERT_NEW_NETWORK_DEVICE,
							"ha_alerts___v1" => $mac, 
							"ha_alerts___v2" => $name, 
							"ha_alerts___v3" => $ip, 
							"ha_alerts___v4" => $connection);
			print_r(executeCommand($params));
			$mysql= 'INSERT INTO `ha_mf_device_ipaddress` (
						`ip` ,
						`mac` ,
						`name` ,
						`connection` ,
						`trusted`
						)
					VALUES (' . 
						'"'.$ip.'",'.
						'"'.$mac.'",'.
						'"'.$name.'",'.
						'"'.$connection.'",'.
						'"0");';
		
				if (!mysql_query($mysql)) mySqlError($mysql);	
				$devicesimported++;
		}
		//
		//		Release duplicate IP's
		//
		$mysql="UPDATE `ha_mf_device_ipaddress` SET ". 
				" ip = NULL " .  
				" WHERE mac<>'".$mac."' AND  ip='".$ip."'";  
		if (!mysql_query($mysql)) mySqlError($mysql);
    }
    if ($showlist) echo "</tbody></table>";


    //echo "</pre>";
	return $devicesimported;

}
?>
