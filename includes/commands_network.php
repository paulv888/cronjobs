<?php
define("ALERT_NETWORK_DEVICE_CHANGE", 218);
define("ALERT_NEW_NETWORK_DEVICE", 219);
define("ALERT_UNKNOWN_IP_FOUND", 217);
// define( 'DEBUG_GRAPH', TRUE );

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

	// if (DEBUG_COMMANDS) {
		// echo "<pre>".$feedback['Name'].': '; print_r($params); echo "</pre>";
	// }
	// return $feedback;
// }
function MoveHistory1() {
    $mysql="INSERT INTO `net_sessions_history` SELECT * FROM `net_sessions` WHERE active=0;";
	$result = PDOExec($mysql);
    $mysql='UPDATE `net_sessions_history` SET `remote_domain`=if(`remote_name`=`remote_address`, `remote_name`, SUBSTRING_INDEX(`remote_name`, ".", -2))   WHERE `remote_domain` IS Null';
	PDOExec($mysql);
    $mysql = "DELETE FROM `net_sessions` WHERE active=0;";
	PDOExec($mysql);
	return $result;
}

function GetSessions1() {

		// Get myIP
	if (!($myip = FetchRow('SELECT ip FROM `ha_mf_device_ipaddress`  WHERE name="PublicIP"')['ip'])) {
		echo "Could not find 'PublicIP'";
		exit; 
	}
	//echo "MyIP:".$myip.CRLF;
    $output = shell_exec(__DIR__.'/telnetcmd.sh '.FIREWALL_USER.' '.FIREWALL_PASSWORD);
    
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

function findRemoteName1($ip) {
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

function findLocalName1($ip, $sendAlert = false) {
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


function ImportSessions1() {
 
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

function getDeviceList(&$params) {

	$showlist=false;
	if ($params['caller']['callerID'] == DEVICE_REMOTE) $showlist=true; 

	$feedback['Name'] = 'GetDeviceList';
	$feedback['result'] = array();

// echo  "<pre>";
//print_r($params);

	$device = $params['device'];
//print_r($device);


	//
	//	Login
	//
	//curl -v -L -c c.txt "http://192.168.2.1/login.cgi"  -H "Content-Type: application/x-www-form-urlencoded"  -H "Referer: http://192.168.2.1/Main_Login.asp" 
	//--data "login_authorization=YWRtaW46S2xvb3R6YWswMQ=="	
	$url = setURL(array('device' => $device), '/login.cgi');
	$url_r = setURL(array('device' => $device), '/Main_Login.asp');
// echo $url.CRLF;
// echo $url_r.CRLF;
	$fields = array(
		'login_authorization' => base64_encode($device['connection']['username'].":".$device['connection']['password']),
	);
	$fields_string = "";
	foreach($fields as $key=>$value) { $fields_string .= $key.'='.$value.'&'; }
	rtrim($fields_string, '&');

	$cookie_file_path = $_SERVER['DOCUMENT_ROOT'].'/tmp/cookies.txt';

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_POST, count($fields));
	curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string);
	curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file_path);
	curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file_path);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
	curl_setopt($ch, CURLINFO_HEADER_OUT, true);
	curl_setopt($ch, CURLOPT_FAILONERROR, 1);
	curl_setopt($ch, CURLOPT_MAXREDIRS, 100);
	if (DEBUG_DEVICES) curl_setopt($ch, CURLOPT_VERBOSE, 1);
	curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));
	curl_setopt($ch, CURLOPT_REFERER, $url_r);
	curl_setopt($ch, CURLOPT_HEADER, true);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $device['connection']['timeout']); 
	curl_setopt($ch, CURLOPT_TIMEOUT, $device['connection']['timeout']);


	$tmpresponse=curl_exec ($ch);
	$information = curl_getinfo($ch);
	$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	if ($httpcode != 200 && $httpcode != 204) {
		$feedback['error'] = $httpcode.": ".curl_error($ch);
		return $feedback;
	}
	if (DEBUG_DEVICES) $feedback['result']['curl']['Login']= $information;
	curl_close ($ch);

// echo "code:".$httpcode;
// echo "info".CRLF;
// print_r($information);
// echo "response".CRLF;
// print_r($tmpresponse);

	//
	// Scrape device list
	//
	//curl -v -b c.txt "http://192.168.2.1/update_clients.asp" -H "Referer: http://192.168.2.1/index.asp"
	$url = setURL(array('device' => $device), '/update_clients.asp');
	$url_r = setURL(array('device' => $device), '/index.asp');
// echo $url.CRLF;
// echo $url_r.CRLF;

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file_path);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
	curl_setopt($ch, CURLINFO_HEADER_OUT, false);
	curl_setopt($ch, CURLOPT_FAILONERROR, 1);
	curl_setopt($ch, CURLOPT_MAXREDIRS, 100);
	if (DEBUG_DEVICES) curl_setopt($ch, CURLOPT_VERBOSE, 1);
	curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));
	curl_setopt($ch, CURLOPT_REFERER, $url_r);
	curl_setopt($ch, CURLOPT_HEADER, true);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $device['connection']['timeout']); 
	curl_setopt($ch, CURLOPT_TIMEOUT, $device['connection']['timeout']);
	
	$response=curl_exec ($ch);
	$information = curl_getinfo($ch);
	$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	if ($httpcode != 200 && $httpcode != 204) {
		$feedback['error'] = $httpcode.": ".curl_error($ch);
		return $feedback;
	}
	if (DEBUG_DEVICES) $feedback['result']['curl']['Network']= $information;
	curl_close ($ch);

// echo "code:".$httpcode;
// echo "info".CRLF;
// print_r($information);
// echo "response".CRLF;
// print_r($response);
// exit;


	//
	// Add traffic_warning_0 cookie
	//
	// printf "#HttpOnly_192.168.2.1   FALSE   /       FALSE   0       traffic_warning_0       2018.3:1\n" >> c.txt
	$cookie = file_get_contents($cookie_file_path);
	if (strpos($cookie, "traffic") === false) {
		$cookie .= "#HttpOnly_192.168.2.1   FALSE   /       FALSE   0       traffic_warning_0       2018.3:1\n";
		file_put_contents($cookie_file_path, $cookie);
	}

	//
	// Refresh for next run
	//
	//curl -v -b c.txt "http://192.168.2.1/apply.cgi" -H "Content-Type: application/x-www-form-urlencoded" -H "Accept: text/html" 
	//-H "Referer: http://192.168.2.1/device-map/clients.asp" --data "action_mode=refresh_networkmap&action_script=&action_wait=5&
	//current_page=device-map/clients.asp&next_page=device-map/clients.asp"
	$url = setURL(array('device' => $device), '/apply.cgi');
	$url_r = setURL(array('device' => $device), '/device-map/clients.asp');
// echo $url.CRLF;

	$fields = array(
		'action_mode' => 'refresh_networkmap',
		'action_script' => '',
		'action_wait' => '5',
		'current_page' => 'device-map/clients.asp',
		'next_page' => 'device-map/clients.asp'
	);
	$fields_string = "";
	foreach($fields as $key=>$value) { $fields_string .= $key.'='.$value.'&'; }
	rtrim($fields_string, '&');

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_POST, count($fields));
	curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string);
	curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file_path);
	curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file_path);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
	curl_setopt($ch, CURLINFO_HEADER_OUT, true);
	curl_setopt($ch, CURLOPT_FAILONERROR, 1);
	curl_setopt($ch, CURLOPT_MAXREDIRS, 100);
	if (DEBUG_DEVICES) curl_setopt($ch, CURLOPT_VERBOSE, 1);
	curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded','Accept: text/html'));
	curl_setopt($ch, CURLOPT_REFERER, $url_r);
	curl_setopt($ch, CURLOPT_HEADER, true);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $device['connection']['timeout']); 
	curl_setopt($ch, CURLOPT_TIMEOUT, $device['connection']['timeout']);

	$tmpresponse=curl_exec ($ch);
	$information = curl_getinfo($ch);
	$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	if ($httpcode != 200 && $httpcode != 204) {
		$feedback['error'] = $httpcode.": ".curl_error($ch);
	}
	if (DEBUG_DEVICES) $feedback['result']['curl']['Refresh']= $information;
	curl_close ($ch);

// echo "code:".$httpcode;
// echo "info".CRLF;
// print_r($information);
// echo "response".CRLF;
// print_r($tmpresponse);


	//
	// Logout
	//
	$url = setURL(array('device' => $device), '/Logout.asp');
	$url_r = setURL(array('device' => $device), '/index.asp');
// echo $url.CRLF;
// echo $url_r.CRLF;

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file_path);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
	curl_setopt($ch, CURLINFO_HEADER_OUT, false);
	curl_setopt($ch, CURLOPT_FAILONERROR, 1);
	curl_setopt($ch, CURLOPT_MAXREDIRS, 100);
	if (DEBUG_DEVICES) curl_setopt($ch, CURLOPT_VERBOSE, 1);
	curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));
	curl_setopt($ch, CURLOPT_REFERER, $url_r);
	curl_setopt($ch, CURLOPT_HEADER, true);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $device['connection']['timeout']); 
	curl_setopt($ch, CURLOPT_TIMEOUT, $device['connection']['timeout']);
	
	$tmpresponse=curl_exec ($ch);
	$information = curl_getinfo($ch);
	$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	if ($httpcode != 200 && $httpcode != 204) {
		$feedback['error'] = $httpcode.": ".curl_error($ch);
		return $feedback;
	}
	if (DEBUG_DEVICES) $feedback['result']['curl']['Logout']= $information;
	curl_close ($ch);

// echo "code:".$httpcode;
// echo "info".CRLF;
// print_r($information);
// echo "tmpresponse".CRLF;
// print_r($response);
// exit;

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
		$feedback['error'] = "Error: No devices found";
		return $feedback;
    } 
   	
	unset($t1[0]);
	
//print_r($t1);

	$loop=0;
	$deviceslist = array();
	foreach($t1 as $row) {
		$deviceslist[] = explode(">", $row);
	}


// echo "<pre>";
	$feedback['result']['devicelist'] = $deviceslist;
// echo "</pre>";

	usort($deviceslist, function($a, $b) {
	    return ip2long($a[2]) - ip2long($b[2]);
	});

	$newmacs=0;
	$changedips=0;
	$devicesimported=0;
    if ($showlist) $feedback['message']="<pre><table><thead><tr><th>Name</th><th>IP</th><th>Connection</th><th>Mac</th></tr></thead><tbody>";
	foreach ($deviceslist as $device) {
		$name = $device[1];
		$ip = $device[2];
		$connection = $device[4];
		$mac = strtoupper($device[3]);
		$mysql="SELECT * ". 
				" FROM  `ha_mf_device_ipaddress`" .  
				" WHERE mac='".$mac."'";  
		if ($rowdevice = FetchRow($mysql)) {			// Update existing mac
			if ($showlist) {
					$feedback['message'] .= "<tr><td>$name</td><td>$ip</td><td>$connection</td><td>$mac</td></tr>";
			}
 		// does not work anymore on name, names are empty
			if (strlen($name) == 0) $name = $rowdevice['name'];
//			if ($rowdevice['name'] <> $name || $rowdevice['ip'] <> $ip || $rowdevice['connection'] <> $connection) {		// Something changed
			if ($rowdevice['name'] <> $name || $rowdevice['ip'] <> $ip) {		// Something changed
				$mysql="SELECT * ". 
					" FROM  `ha_mf_devices`" .  
					" WHERE ipaddressID =".$rowdevice['id'];  
				if ($rowdev = FetchRow($mysql)) $deviceID = $rowdev['id']; else $deviceID = 0;
				$command = array('callerID' => $params['caller']['callerID'], 
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
								"ha_alerts___v4" => $ip,
								"commandvalue" => $rowdevice['friendly_name']);
				$feedback['result']['Changed'][] = executeCommand($command);
				$mysql= 'UPDATE `ha_mf_device_ipaddress` SET `mac` = "'. $mac .'", 
					`name` = "'. $name.'", `ip` = "'.$ip.'" , `connection` = "'.$connection.'", 
					`last_list_date` = "'.date("Y-m-d H:i:s").'" WHERE `ha_mf_device_ipaddress`.`id` = '.$rowdevice['id'];
				//echo $mysql;
				PDOExec($mysql);	
				$changedips++;
			} else {
				PDOUpdate('ha_mf_device_ipaddress',array('last_list_date' => date("Y-m-d H:i:s")),array('id' => $rowdevice['id']));	
				$devicesimported++;
			}
		}	else {				// New MAC
			$command = array('callerID' => $params['caller']['callerID'], 
							'deviceID' => $params['caller']['callerID'], 
							'messagetypeID' => 'MESS_TYPE_SCHEME',
							'schemeID' => ALERT_NEW_NETWORK_DEVICE,
							"ha_alerts___v1" => $mac, 
							"ha_alerts___v2" => $name, 
							"ha_alerts___v3" => $ip, 
							"ha_alerts___v4" => $connection,
							'commandvalue'   => $rowdevice['name']."\n".$ip);
			$feedback['result']['New MAC'][] = executeCommand($command);
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
		
				PDOExec($mysql);	
				$newmacs++;
		}
		//
		//		Release duplicate IP's
		//
		$mysql="UPDATE `ha_mf_device_ipaddress` SET ". 
				" ip = NULL " .  
				" WHERE mac<>'".$mac."' AND  ip='".$ip."'";  
		PDOExec($mysql);
    }
    if ($showlist) 
		$feedback['message'] .= "</tbody></table>";
	else
		$feedback['message'] = "Devices found: $devicesimported, New MACs: $newmacs, Changed IP's: $changedips";

    //echo "</pre>";
	
	return $feedback;
;

}
?>
