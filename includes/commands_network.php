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
	$device = $params['device'];
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
		'login_authorization' => base64_encode($device['connection']['username'].":".$device['connection']['password'])
	);
	
	$fields_string = "";
	foreach($fields as $key=>$value) { $fields_string .= $key.'='.$value; }
	debug($fields,'post fields');
	
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
	if (isset($GLOBALS['debug'])) curl_setopt($ch, CURLOPT_VERBOSE, 1);
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
	if (isset($GLOBALS['debug'])) $feedback['result']['curl']['Login']= $information;
	curl_close ($ch);
	debug($feedback,'login->feedback');
	debug($response,'response');

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
	curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file_path);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
	curl_setopt($ch, CURLINFO_HEADER_OUT, false);
	curl_setopt($ch, CURLOPT_FAILONERROR, 1);
	curl_setopt($ch, CURLOPT_MAXREDIRS, 100);
	if (isset($GLOBALS['debug'])) curl_setopt($ch, CURLOPT_VERBOSE, 1);
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
	if (isset($GLOBALS['debug'])) $feedback['result']['curl']['Network']= $information;
	curl_close ($ch);
	$ch = curl_init();
	debug($feedback,'update_clients->feedback');
	debug($response,'response');

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

	$fields = array(
		'action_mode' => 'refresh_networkmap',
		'action_script' => '',
		'action_wait' => '5',
		'current_page' => 'device-map/clients.asp',
		'next_page' => 'device-map/clients.asp'
	);
	$fields_string = "";
	foreach($fields as $key=>$value) { $fields_string .= $key.'='.$value.'&'; }
	debug($fields_string,'fields_string');

	
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
	if (isset($GLOBALS['debug'])) curl_setopt($ch, CURLOPT_VERBOSE, 1);
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
	if (isset($GLOBALS['debug'])) $feedback['result']['curl']['Refresh']= $information;
	curl_close ($ch);
	debug($feedback,'device-map->feedback');
	debug($tmpresponse,'tmpresponse');

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
	if (isset($GLOBALS['debug'])) curl_setopt($ch, CURLOPT_VERBOSE, 1);
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
	if (isset($GLOBALS['debug'])) $feedback['result']['curl']['Logout']= $information;
	curl_close ($ch);
	debug($feedback,'logout->feedback');

/* 
 * 	There is more info here, wired wireless, DB, 2 or 5gb ...
 * 
*/


	$in = explode(PHP_EOL, $response);
	if (array_key_exists(13, $in) and substr($in[13],0, 15) == 'fromNetworkmapd') {
		debug($in,'logout->matches');
		$noresult = rtrim(str_replace( 'fromNetworkmapd : ', '', $in[13]),",");
		debug($noresult,'logout->noresult');
		$deviceslist = json_decode($noresult,true);
		debug($deviceslist[0],'logout->deviceslist');
    } else {
    	// echo $response;
		$feedback['error'] = "Error: No devices found";
		return $feedback;
    } 

	$feedback['result']['devicelist'] = $deviceslist;

	$newmacs=0;
	$changedips=0;
	$devicesimported=0;
    if ($showlist) $feedback['message']="<pre><table><thead><tr><th>Name</th><th>IP</th><th>Vendor</th><th>Mac</th></tr></thead><tbody>";
	foreach ($deviceslist[0] as $mac => $devicefull ) {
		if ($mac == 'maclist') continue; 
		unset($device);
		$device['name'] = $devicefull['name'];
		$device['vendor'] = $devicefull['vendor'];
		$device['ip'] = $devicefull['ip'];
		$device['mac'] = strtoupper($mac);
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

        $hostName = $params['device']['shortdesc'];
        $deviceID = $params['device']['id'];
        $feedback['Name'] = 'getDrives';
        $feedback['result'] = array();
        if ($hostName != 'firebox') {
			$cmd = 'ssh remote-jobs@'.$hostName.' -i remote-jobs df -Pkh --local --exclude-type=tmpfs --exclude-type=devtmpfs';
		} else {
			$cmd = 'ssh remote-jobs@'.$hostName.' -i remote-jobs df -Pkht ufs';
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

function natSessions(&$params) {


   	$mysql = "UPDATE `net_sessions` SET `active` = '0';";
	$feedback['result'][] =  PDOExec($mysql) ." Rows affected";


	$hostName = $params['device']['shortdesc'];
	$deviceID = $params['device']['id'];
	$feedback['Name'] = 'natSessions';
	$cmd = 'ssh remote-jobs@'.$hostName.' -i remote-jobs sudo  pftop -ab -f \"in and dst net 192.168.2.0/24 and src net not 192.168.2.0/24 and proto tcp\"';
	debug($cmd, 'command');
	$output = shell_exec($cmd);
	debug($output, 'shell_exec');
	$feedback['result'][$hostName] = $output;
	$lines = explode(PHP_EOL, $output);
	$feedback['result'][] = $lines;
	debug($lines, 'lines');


	$columns = ["class", "protocol", "flags", "remote_address", "remote_port", "local_address", "local_port", "TCPstate", "packets", "bytes"];
	$x = 0;
	foreach ($lines as $line) {
			if (empty($line)) continue;
			$x++;
			if ($x < 3 ) continue;
			$line = 'alert-danger '.$line;
			$values = preg_split('/[:\s]+/', $line, -1, PREG_SPLIT_NO_EMPTY);
			unset($values['8']);  // Split state
			unset($values['9']);  // Expire
			unset($values['10']);  // Age
			
			debug($columns, 'columns');
			$pairs = array_combine ( $columns , $values );

			$pairs['local_name']= gethostbyaddr($pairs['local_address']); 
			$pairs['remote_name']= gethostbyaddr($pairs['remote_address']); 
			$pairs['active']= 1; 
			debug($pairs, 'pairs');

			PDOupsert('`net_sessions`', $pairs, array('remote_address' => $pairs['remote_address'], 'remote_port' => $pairs['remote_port'], 'local_address' => $pairs['local_address'], 'local_port' => $pairs['local_port']));

			// $properties[substr($pairs['filesystem'], -4).' Size']['value'] = $pairs['blocks'];
			// $properties[substr($pairs['filesystem'], -4).' Capacity']['value'] = $pairs['capacity'];

	}

	// $params['device']['properties'] = $properties;

	$feedback['result']['moveHistory'] = MoveHistory()." Sessions moved to History <br/>\r\n";


	return $feedback;

}

function moveHistory() {
    $mysql = "INSERT INTO `net_sessions_history` SELECT * FROM `net_sessions` WHERE active=0;";
	$result = PDOExec($mysql);
    $mysql = "DELETE FROM `net_sessions` WHERE active=0;";
	$num_rows = PDOExec($mysql);
	return $num_rows;
}

?>
