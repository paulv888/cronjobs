<?php
function natSessions($params = Null) {
	$result['message']=ImportSessions()." Nat Sessions Read <br/>\r\n";
	$result['message'].=MoveHistory()." Sessions moved to History <br/>\r\n";
	return $result;
}

function trHost() {
	$mysql="SELECT `id`,`ip` ". 
			" FROM  `net_iplookup`"   .
			" WHERE ip=name and processed<>1 LIMIT 5000";  

	// While a row of data exists, put that row in $row as an associative array
	// Note: If you're expecting just one row, no need to use a loop
	// Note: If you put extract($row); inside the following loop, you'll
	//       then create $userid, $fullname, and $userstatus
	if ($rows = FetchRows($mysql)) {
		foreach ($rows as $row) {
			$ip =$row['ip'];

			$hostname = gethostbyaddr($ip);
			echo $ip." ".$hostname."</br>";
			$mysql = "UPDATE `net_iplookup` SET `processed` = 1, `name`='".$hostname."' WHERE id =".$row['id'];
			PDOupdate('net_iplookup', array('processed' => 1, 'name' => $hostname), array( 'id' => $row['id']));
		}
 	}
}


function MoveHistory() {
    $mysql='UPDATE `net_sessions` SET `remote_domain`=if(`remote_name`=`remote_address`, `remote_name`, SUBSTRING_INDEX(`remote_name`, ".", -2))   WHERE `remote_domain` IS Null';
	PDOExec($mysql);
    $mysql="INSERT INTO `net_sessions_history` SELECT * FROM `net_sessions` WHERE active=0;";
	$result = PDOExec($mysql);
    $mysql = "DELETE FROM `net_sessions` WHERE active=0;";
	PDOExec($mysql);
	return $result;
}

function GetSessions() {

		// Get myIP
	if (!($myip = FetchRow('SELECT ip FROM `ha_mf_device_ipaddress`  WHERE name="PublicIP"')['ip'])) {
		echo "Could not find 'PublicIP'";
		exit; 
	}
	//echo "MyIP:".$myip.CRLF;
	$cmd = 'cat /proc/net/ip_conntrack';
    $output = shell_exec(__DIR__.'/telnetcmd.sh '.FIREWALL_USER.' '.FIREWALL_PASSWORD.' '.'"'.$cmd.'"');
    
    $sessions_raw = explode("\n", $output); 
    // unset($sess[0]);
	end($sessions_raw);
	unset($sessions_raw[key($sessions_raw)]);

	$sessions= Array();
	
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
		PDOExec($mysql);
	} else {
		$mysql = "INSERT INTO `net_iplookup` (ip, name, processed) values ('".$ip."','".$hostname."', 1)";
		PDOExec($mysql);
	}

	return  $hostname;
}

function findLocalName($ip, $sendAlert = false) {
	if (!defined('MY_DEVICE_ID')) define( 'MY_DEVICE_ID', DEVICE_REMOTE );

	$mysql="SELECT * FROM  `ha_mf_device_ipaddress` WHERE ip='".$ip."';";  

	if ($row=FetchRow($mysql)) {
		return  $row['friendly_name'];
	} else {
		if ($sendAlert) {
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
				`friendly_name` ,
				`connection` ,
				`trusted`
				)
			VALUES (' . 
				'"'.$ip.'",'.
				'"'.$ip.'",'.
				'"**Unknown",'.
				'"**Unknown",'.
				'"",'.
				'"0");';
			PDOExec($mysql);	
		}
	}
	return false;
}


// function FindAddress($ip) {
	// $mysql="SELECT * ". 
			// " FROM  `net_iplookup`" .  
			// " WHERE ip='".$ip."'";  
	// if ($resset=FetchRow($mysql)) {
		// return $resset['id'];
	// } else { 			// insert and return id
	    // $mysql="INSERT INTO `net_iplookup` (ip, name) values ('".$ip."','".$ip."')";
		// $result = mysql_query($mysql);
		// if (!$result) mySqlError($mysql);	
		// return  mysql_insert_id();
	// }
// }

function ImportSessions() {
 
	$sessionsresponse=GetSessions();
//echo "<pre>";
//print_r($sessionsresponse);
//echo "</pre>";

	if (empty($sessionsresponse)) return -1;
	
	$sessionsimported=0;
   	$mysql = "UPDATE `net_sessions` SET `active` = '0';";
	PDOExec($mysql);
    
	foreach ($sessionsresponse as $session) {
    	
		$mysql="SELECT * ". 
				" FROM  `net_sessions`" .  
				" WHERE local_address='".$session['local_address']."'"." AND local_port='".$session['local_port']."'".
                                     " AND remote_address='".$session['remote_address']."'"." AND remote_port='".$session['remote_port']."'";  
		if ($dbsession = FetchRow($mysql)) {
			// check for same ip's???
				$mysql="DELETE ". 
						" FROM  `net_sessions`" .  
						" WHERE id='".$dbsession['id']."'";  
				PDOExec($mysql);
			}


		$local = $session['local_address'];
		$session['local_name'] = "*unknown";
		if ($lname = findLocalName($session['local_address'], true)) $session['local_name'] = $lname; 
		$remote = $session['remote_address'];
		$session['remote_name'] = findRemoteName($session['remote_address']); 
		$session['createdate'] = date('Y-m-d H:i:s');
		PDOinsert('net_sessions', $session);
		$sessionsimported++;

	}
    
	return $sessionsimported;
}
?>
