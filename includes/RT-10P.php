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

	$post = RestClient::get("http://192.168.2.1/Main_ConnStatus_Content.asp",null,FIREWALL_USER,FIREWALL_PASSWORD);
	$response= $post->getResponse();
	$post = RestClient::get("http://192.168.2.1/Logout.asp");

    /*
<textarea style="width:99%; font-family:'Courier New', Courier, mono; font-size:13px;background:#475A5F;color:#FFFFFF;" cols="63" rows="25" readonly="readonly" wrap=off>Proto NATed Address                            Destination Address                      State 
tcp   192.168.2.110:62703                      192.168.2.101:443                        ESTABLISHED
tcp   192.168.2.110:62589                      208.111.131.125:443                      TIME_WAIT  
</textarea>
	*/
	$pattern = "/State(.*?)</si";
	$noresult = preg_match_all($pattern, $response, $matches);
	$lasterr = preg_last_error();
	$t1 = '';
	if (array_key_exists(1, $matches[1]) and $matches[1][1] != '')
    {
        $t1=$matches[1][1];
    } else {
    	echo $response;
		return -1;
    } 
    	
//print_r($t1);    
    
    $sess = explode("\n", $t1); 
	unset($sess[0]);
	end($sess);
	unset($sess[key($sess)]);
    
	foreach ($sess as $row) {
		$sessions[] = preg_split('/[\s:]+/', $row);
	}
// echo "<pre>";
// print_r($sessions);
       
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
	if ($sessionsresponse < 0) return -1;
	
	$sessionsimported=0;
   	$mysql = "UPDATE `net_sessions` SET `active` = '0';";
	if (!mysql_query($mysql))  mySqlError($mysql);
    	
	foreach ($sessionsresponse AS $session) {
    	
		$mysql="SELECT * ". 
				" FROM  `net_sessions`" .  
				" WHERE local_address='".$session[1]."'"." AND local_port='".$session[2]."'"." AND remote_address='".$session[3]."'"." AND remote_port='".$session[4]."'";  
		$ressessions=mysql_query($mysql);
		if ($dbsession=mysql_fetch_array($ressessions)) {
			// check for same ip's???
				$mysql="DELETE ". 
						" FROM  `net_sessions`" .  
						" WHERE id='".$dbsession['id']."'";  
				if (!mysql_query($mysql)) mySqlError($mysql);
			}

			
		$class = "";
			
		if (substr ($session[1],0,strlen(MY_SUBNET)) <> MY_SUBNET) {
           	$class = SEVERITY_DANGER_CLASS;
			$ip = $session[1];
			$po = $session[2];
			$session[1] = $session[3];
			$session[2] = $session[4];
			$session[3] = $ip;
			$session[4] = $po;
		}
		
		$local = $session[1];
		$localname= findLocalName($session[1]); 
		$remote = $session[3];
		$remotename= findRemoteName($session[3]); 
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
					'"",'.
					'"'.$session[0].'",'.
					'"'.$session[1].'",'.
					'"'.$localname.'",'.
					'"'.$session[2].'",'.
					'"'.$session[3].'",'.
					'"'.$remotename.'",'.
					'"'.$session[4].'",'.
					'"",'.
					'"",'.
					'"'.(isset($session[5]) ? $session[5] : "").'",'.
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

	$post = RestClient::get("http://192.168.2.1/device-map/clients.asp",null,FIREWALL_USER,FIREWALL_PASSWORD);
	$response= $post->getResponse();
	$post = RestClient::get("http://192.168.2.1/Logout.asp");

	$pattern = "/client_list_array = '(.*?)';/si";
	$noresult = preg_match_all($pattern, $response, $matches);
// echo "<pre>";
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
// print_r($deviceslist);
    
	$devicesimported=0;
// echo "</pre>";
   	
	foreach ($deviceslist as $device) {
		$name = $device[1];
		$ip = $device[2];
		$connection = $device[4];
		$mac = $device[3];
		$mysql="SELECT * ". 
				" FROM  `ha_mf_device_ipaddress`" .  
				" WHERE mac='".$mac."'";  
		$resdevices = mysql_query($mysql);
		if ($rowdevice = mysql_fetch_array($resdevices)) {			// Update existing mac

		// does not work anymore on name, names are empty
			if (strlen($name) == 0) $name = $rowdevice['name'];
			if ($rowdevice['name'] <> $name || $rowdevice['ip'] <> $ip || $rowdevice['connection'] <> $connection) {		// Something changed
				$mysql="SELECT * ". 
					" FROM  `ha_mf_devices`" .  
					" WHERE ipaddressID =".$rowdevice['id'];  
				$resdev = mysql_query($mysql);
				if ($rowdev = mysql_fetch_array($resdev)) $deviceID = $rowdev['id']; else $deviceID = 0;
				$params = array('deviceID' => MY_DEVICE_ID, "ha_alerts___l1" => $mac, "ha_alerts___l2" => $rowdevice['connection'], "ha_alerts___l3" => $connection, "ha_alerts___l4" => $deviceID, "ha_alerts___v1" => $rowdevice['name'],"ha_alerts___v2" => $name, "ha_alerts___v3" => $rowdevice['ip'],"ha_alerts___v4" => $ip);
				echo Alerts(ALERT_NETWORK_DEVICE_CHANGE,$params)." Alerts generated <br/>\r\n";
				$mysql= 'UPDATE `ha_mf_device_ipaddress` SET 
					`name` = "'. $name.'", `ip` = "'.$ip.'" , `connection` = "'.$connection.'", `last_list_date` = NOW() WHERE `ha_mf_device_ipaddress`.`id` = '.$rowdevice['id'];
				if (!mysql_query($mysql)) mySqlError($mysql);	
			} else {
				$mysql= 'UPDATE `ha_mf_device_ipaddress` SET 
					`last_list_date` = NOW() WHERE `ha_mf_device_ipaddress`.`id` = '.$rowdevice['id'];
				if (!mysql_query($mysql)) mySqlError($mysql);	
			}
		}	else {				// New MAC
			$params = array('deviceID' => MY_DEVICE_ID, "ha_alerts___v1" => $mac, "ha_alerts___v2" => $name, "ha_alerts___v3" => $ip, "ha_alerts___v4" => $connection);
			echo Alerts(ALERT_NEW_NETWORK_DEVICE,$params)." Alerts generated <br/>\r\n";
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

/* 
function showclient_list(list){

var client_list_col = client_list_row[i].split('>');
var overlib_str = "";
if(client_list_col[1] == "")
client_list_col[1] = retHostName(client_list_col[3]);
if(client_list_col[1].length > 16){
overlib_str += "<p>User Name</p>" + client_list_col[1];
client_list_col[1] = client_list_col[1].substring(0, 13);
client_list_col[1] += "...";
}
overlib_str += "<p>MAC address:</p>" + client_list_col[3];
if(login_ip_str() == client_list_col[2])
overlib_str += "<p>Local Device:</p>YES";
if(client_list_col[5] == 1)
overlib_str += "<p>Printer Service:</p>YES";
if(client_list_col[6] == 1)
overlib_str += "<p>iTunes Service:</p>YES";
for(var j = 0; j < client_list_col.length-3; j++){
if(j == 0){
if(client_list_col[0] == "0" || client_list_col[0] == ""){
code +='<td width="12%" height="30px;" title="'+DEVICE_TYPE[client_list_col[0]]+'"><div id="device_img6"></div></td>';
networkmap_scanning = 1;
}
else{
code +='<td width="12%" height="30px;" title="'+DEVICE_TYPE[client_list_col[0]]+'">';
code +='<div id="device_img'+client_list_col[0]+'"></div></td>';
}
}
else if(j == 1){
if(client_list_col[1] != "")
code += '<td width="40%" onclick="oui_query(\'' + client_list_col[3] + '\');overlib_str_tmp=\''+ overlib_str +'\';return overlib(\''+ overlib_str +'\');" onmouseout="nd();" class="ClientName" style="cursor:pointer;text-decoration:underline;">'+ client_list_col[1] +'</td>'; // Show Device-name
else
code += '<td width="40%" onclick="oui_query(\'' + client_list_col[3] + '\');overlib_str_tmp=\''+ overlib_str +'\';return overlib(\''+ overlib_str +'\');" onmouseout="nd();" class="ClientName" style="cursor:pointer;text-decoration:underline;">'+ client_list_col[3] +'</td>'; // Show MAC
}
else if(j == 2){
if(client_list_col[4] == "1")
code += '<td width="36%"><a title="LAN IP" class="ClientName" style="text-decoration:underline;" target="_blank" href="http://'+ client_list_col[2] +'">'+ client_list_col[2] +'</a></td>';
else
code += '<td width="36%"><span title="LAN IP" class="ClientName">'+ client_list_col[2] +'</span></td>';
}
else if(j == client_list_col.length-4)
code += '';
else
code += '<td width="36%" class="ClientName" onclick="oui_query(\'' + client_list_col[3] + '\');overlib_str_tmp=\''+ overlib_str +'\';return overlib(\''+ overlib_str +'\');" onmouseout="nd();">'+ client_list_col[j] +'</td>';
}
if(parent.sw_mode == 1 && ParentalCtrl_support)
code += '<td width="12%"><input class="remove_btn_NM" type="submit" title="Block" onclick="block_this_client(this);" value=""/></td></tr>';
else
code += '</tr>';
}
}
code +='</table>';
$("client_list_Block").innerHTML = code;
$("client_list_Block").style.display = "none";
for(var i=client_list_row.length-1; i>0; i--){
var client_list_col = client_list_row[i].split('>');
if(list == is_blocked_client(client_list_col[3])){
$('client_list_table').deleteRow(i-1);
}
}
if($('client_list_table').innerHTML == "<tbody></tbody>"){
code ='<tr><td style="color:#FFCC00;" colspan="4">No data in table.</td></tr>'
$("client_list_Block").innerHTML = code;
}
parent.client_list_array = client_list_array;
parent.show_client_status();
} */
?>