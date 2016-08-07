<?php

function natSessions() {
	$result=ImportSessions()." Nat Sessions Read <br/>\r\n";
	$result.=MoveHistory()." Sessions moved to History <br/>\r\n";
	return $result;
}

function deviceList() {
	$result=GetDeviceList()." Devices Read <br/>\r\n";
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

// post($url,$params=null,$user=null,$pwd=null,$contentType="multipart/form-data") {

/*	 <input type="hidden" name="PAGE" value="A02_POST" />
      <input type="hidden" name="THISPAGE" value="A02_POST" />
      <input type="hidden" name="NEXTPAGE" value="J14" />
      <input type="hidden" name="CMSKICK" value="" />
      <input type="hidden" name="PAGE" value="J14" />
      <input type="hidden" name="THISPAGE" value="A02_POST" />
      <input type="hidden" name="NEXTPAGE" value="J14" />
  	  <input type="password" name="PASSWORD"
  	  "javascript:location=\'/xslt?PAGE=A02_POST 
*/
     	
	$fields = array(
						'PAGE' => "A02_POST",
						'THISPAGE' => "A02_POST",
						'NEXTPAGE' => "J14",
						'CMSKICK' => "",
						'PAGE' => "J14",
						'PASSWORD' => FIREWALL_PASSWORD
				);
	
	$post = RestClient::post("http://192.168.2.1/xslt?PAGE=A02_POST",$fields);
	$response= $post->getResponse();
	//echo $response;

	//  	  <pre class="textmono">Redirection is disabled</pre>
	$pattern = "'<pre class=\"textmono\">(.*?)</pre>'si";
	$noresult = preg_match_all($pattern, $response, $matches);
	$lasterr = preg_last_error();
	$t1 = '';
	if ($matches[1][1] != '')
    {
        $t1=$matches[1][1];
    } else {
    	echo $response;
    } 
    	
    
    /*
    current secs since boot: 8602
	session table 935/1024 available, 0/512 used in inbound sessions:
	sess[13]: bkt 11, flags: 0x000001a1, proto: 6, cnt: 5
  	l: 192.168.2.204:61148, f: 157.55.56.166:40033, n: 74.180.120.20:61148
  	lnd: (60,0), fnd: (44,0)
  	
  	replace \n -> space, replace sess[ -> \nsess[, put in array
	*/
    
	$t1 = preg_replace( '/\((\d+),(\d+)\)/', '$1_$2', $t1); // handle lnd: (60,0) and fnd (44,0):
	$find = array("\n",",sess[","]:","bkt","TCP state","TCP IN: is:","TCP OUT: is:","last used","unack'd","mss","windows_scale");
	$repl = array(",","\nsess=",",","bkt=","TCP state:","IN_is:","OUT_is:","last_used:","unacked:","mss:","windows_scale:");
	$t1 = str_replace($find,$repl, $t1);
//	IN_is: 1434494543, sent: 40984, unacked: 339, mss: 0, windows_scale: 0 ,  OUT_is 3441205173, sent: 4817, unacked: 0, mss: 0, windows_scale: 0
    $sessions = explode("\n", $t1); 
    
	//echo "<pre>";
    //print_r($sessions);
//    echo $sessions[0];
	$a1 = explode(",",$sessions[0]);
	$a2 = explode(":",$a1[1]);
	$secs_boot = $a2[1];
	$a3 = explode("/",$a1[2]);
	$a5 = explode(" ",$a3[0]);
	$a4 = explode("/",$a1[3]);
	$status [0] = "secs_since_boot:".$secs_boot.",sessions_available:". $a5[2].",inbound_sessions:".$a4[0];
	unset($sessions[0]);
	foreach ($sessions  as &$value) {
		$find = array("/ sent/","/ unacked/","/ mss/","/ windows_scale/");
		$repl = array("IN_sent","IN_unacked","IN_mss","IN_windows_scale");
		$value = preg_replace($find, $repl, $value, 1);
		$find = array("/ sent/","/ unacked/","/ mss/","/ windows_scale/");
		$repl = array("OUT_sent","OUT_unacked","OUT_mss","OUT_windows_scale");
		$value = preg_replace($find, $repl, $value, 1);
		$find = array(",",":"," ");
		$repl = array("&","=","");
		$value = str_replace($find,$repl, $value);
		parse_str($value,$value);
		$value['last_used'] = $secs_boot - $value['last_used'];
	}
       
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
	echo $ip." ".$hostname."</br>";

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
		return  $row['name'];
	} else {
		$params = array('deviceID' => MY_DEVICE_ID, "ha_alerts___l1" => 'IP Address', "ha_alerts___v1" => $ip);
		echo Alerts(ALERT_UNKNOWN_IP_FOUND,$params)." Alerts generated <br/>\r\n";
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
	
	$sessionsimported=0;
   	$mysql = "UPDATE `net_sessions` SET `active` = '0';";
	if (!mysql_query($mysql))  mySqlError($mysql);
    	
	foreach ($sessionsresponse AS $session) {
    	
		$mysql="SELECT * ". 
				" FROM  `net_sessions`" .  
				" WHERE sessionid='".$session['sess']."'";  
		$ressessions=mysql_query($mysql);
		if ($dbsession=mysql_fetch_array($ressessions)) {
			// check for same ip's???
				$mysql="DELETE ". 
						" FROM  `net_sessions`" .  
						" WHERE sessionid='".$session['sess']."'";  
				if (!mysql_query($mysql)) mySqlError($mysql);
			}

		$local = explode("=", $session['l']);
		$localname= findLocalName($local['0']); 
		$remote = explode("=", $session['f']);
		$remotename= findRemoteName($remote['0']); 
		$firew = explode("=", $session['n']);
               	$class = "";
        	$row = FetchRow("SELECT `severity` FROM `net_sessions_flags` WHERE `flag` ='".$session['flags']."'");
	    	if ($row['severity'] == SEVERITY_DANGER) {
                	$class = SEVERITY_DANGER_CLASS;
	        }
        	if ($row['severity'] == SEVERITY_WARNING) {
                	$class = SEVERITY_WARNING_CLASS;
	        }
   		$mysql= 'INSERT INTO `net_sessions` (
					`sessionid` ,
					`protocol` ,
					`local_address` ,
					`local_name` ,
					`local_port` ,
					`remote_address` ,
					`remote_name` ,
					`remote_port` ,
					`firewall_address` ,
					`firewall_port` ,
					`TCPstate` ,
					`last_used` ,
					`bkt` ,
					`flags` ,
					`count` ,
					`lnd` ,
					`fnd` ,
					`max_idle` ,
					`IN_is` ,
					`IN_sent` ,
					`IN_unacked` ,
					`IN_mss` ,
					`IN_windows_state` ,
					`OUT_is` ,
					`OUT_sent` ,
					`OUT_unacked` ,
					`OUT_mss` ,
					`OUT_windows_state` ,
					`active`,
					`class`
					)
				VALUES (' . 
					'"'.$session['sess'].'",'.
					'"'.$session['proto'].'",'.
					'"'.$local['0'].'",'.
					'"'.$localname.'",'.
					'"'.$local[1].'",'.
					'"'.$remote['0'].'",'.
					'"'.$remotename.'",'.
					'"'.$remote[1].'",'.
					'"'.$firew['0'].'",'.
					'"'.$firew[1].'",'.
					'"'.(isset($session['TCPstate']) ? $session['TCPstate'] : "").'",'.
					'"'.(isset($session['last_used']) ? $session['last_used'] : "").'",'.
					'"'.(isset($session['bkt']) ? $session['bkt'] : "").'",'.
					'"'.(isset($session['flags']) ? $session['flags'] : "").'",'.
					'"'.(isset($session['cnt']) ? $session['cnt'] : "").'",'.
					'"'.(isset($session['lnd']) ? $session['lnd'] : "").'",'.
					'"'.(isset($session['fnd']) ? $session['fnd'] : "").'",'.
					'"'.(isset($session['max_idle']) ? $session['max_idle'] : "").'",'.
					'"'.(isset($session['IN_is']) ? $session['IN_is'] : "").'",'.
					'"'.(isset($session['IN_sent']) ? $session['IN_sent'] : "").'",'.
					'"'.(isset($session['IN_unacked']) ? $session['IN_unacked'] : "").'",'.
					'"'.(isset($session['IN_mss']) ? $session['IN_mss'] : "").'",'.
					'"'.(isset($session['IN_windows_state']) ? $session['IN_windows_state'] : "").'",'.
					'"'.(isset($session['OUT_is']) ? $session['OUT_is'] : "").'",'.
					'"'.(isset($session['OUT_sent']) ? $session['OUT_sent'] : "").'",'.
					'"'.(isset($session['OUT_unacked']) ? $session['OUT_unacked'] : "").'",'.
					'"'.(isset($session['OUT_mss']) ? $session['OUT_mss'] : "").'",'.
					'"'.(isset($session['OUT_windows_state']) ? $session['OUT_windows_state'] : "").'",'.
					'"'."1".'",'.
					'"'.$class.'");';
	
			if (!mysql_query($mysql)) mySqlError($mysql);	
			$sessionsimported++;
    }
    
	return $sessionsimported;
}

function GetDeviceList() {
	if (!defined('MY_DEVICE_ID')) define( 'MY_DEVICE_ID', DEVICE_REMOTE );
    	
	$fields = array(
						'PAGE' => "A02_POST",
						'THISPAGE' => "A02_POST",
						'NEXTPAGE' => "J08",
						'CMSKICK' => "",
						'PAGE' => "J08",
						'PASSWORD' => FIREWALL_PASSWORD
				);
				
			//	http://192.168.2.1/xslt?PAGE=J08&THISPAGE=J01&NEXTPAGE=J08
	
	$post = RestClient::post("http://192.168.2.1/xslt?PAGE=A02_POST",$fields);
	$response= $post->getResponse();

	$pattern = "'<div class=\"mdcpagetitle\">Local Network – Device List</div>(.*?)</table>'si";
	$noresult = preg_match_all($pattern, $response, $matches);
	$lasterr = preg_last_error();
	$t1 = '';
	if ($matches[0][0] != '')
    {
        $t1=$matches[0][0];
    } else {
    	echo $response;
    } 
   	


	// create empty document 
	$document = new DOMDocument();
	// load html
	$document->loadHTML($t1);
	// create xpath selector
	$selector = new DOMXPath($document);
	// selects the parent node of <tr> nodes
	$rows = $selector->query('//tr');


	$loop=0;
	$deviceslist = array();
	foreach($rows as $row) {
		$cells = $selector->query('.//td',$row);
		$rowvals = array();
		foreach($cells as $cell) {
			$rowvals[] = $cell->nodeValue;
		}
		$deviceslist[] = $rowvals;
	}

	//echo "<pre>";
	//print_r($deviceslist);
    
	$devicesimported=0;
    	
	foreach ($deviceslist as $device) {
		$name = $device[0];
		$ip = $device[6];
		$connection = preg_replace("/[^A-Za-z0-9]/", '', $device[2]);
		$mac = $device[4];
		if ($mac == "MAC Address") continue; 
		$mysql="SELECT * ". 
				" FROM  `ha_mf_device_ipaddress`" .  
				" WHERE mac='".$mac."'";  
		$resdevices = mysql_query($mysql);
		if ($rowdevice = mysql_fetch_array($resdevices)) {			// Update existing mac
			if ($rowdevice['name'] <> $name || $rowdevice['ip'] <> $ip || $rowdevice['connection'] <> $connection) {		// Something changed
				$mysql="SELECT * ". 
					" FROM  `ha_mf_devices`" .  
					" WHERE ipaddressID =".$rowdevice['id'];  
				$resdev = mysql_query($mysql);
				if ($rowdev = mysql_fetch_array($resdev)) $deviceID = $rowdev['id']; else $deviceID = 0;
				$params = array('deviceID' => MY_DEVICE_ID, "ha_alerts___l1" => $mac, "ha_alerts___l2" => $rowdevice['connection'], "ha_alerts___l3" => $connection, "ha_alerts___l4" => $deviceID, "ha_alerts___v1" => $rowdevice['name'],"ha_alerts___v2" => $name, "ha_alerts___v3" => $rowdevice['ip'],"ha_alerts___v4" => $ip);
				echo Alerts(ALERT_NETWORK_DEVICE_CHANGE, $params)." Alerts generated <br/>\r\n";
				$mysql= 'UPDATE `ha_mf_device_ipaddress` SET 
					`name` = "'. $name.'", `ip` = "'.$ip.'" , `connection` = "'.$connection.'" WHERE `ha_mf_device_ipaddress`.`id` = '.$rowdevice['id'];
				if (!mysql_query($mysql)) mySqlError($mysql);	
			} else {
				$mysql= 'UPDATE `ha_mf_device_ipaddress` SET 
					`last_list_date` = NOW() WHERE `ha_mf_device_ipaddress`.`id` = '.$rowdevice['id'];
				if (!mysql_query($mysql)) mySqlError($mysql);	
			}
		}	else {				// New MAC
			$params = array('deviceID' => MY_DEVICE_ID, "ha_alerts___v1" => $mac, "ha_alerts___v2" => $name, "ha_alerts___v3" => $ip, "ha_alerts___v4" => $connection);
			echo Alerts(ALERT_NEW_NETWORK_DEVICE, $params)." Alerts generated <br/>\r\n";
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
	
	
    
    //echo "</pre>";
	return $devicesimported;

}
?>
