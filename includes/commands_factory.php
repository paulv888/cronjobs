<?php
define("ALERT_KODI", 224);
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


function monitorDevicesTimeout($params) {
	// No Error checking
	
	// Need to handle inuse and active
	$devs = getDevicesWithProperties(Array( 'properties' => Array("Link")));
	
	// echo "<pre>";
	// print_r($devs);
	$feedback['Name'] = 'monitorDevicesTimeout';
	$feedback['result'] = array();
	$params['callerID'] = $params['callerID'];
	foreach ($devs as $key => $props) {
		if (array_key_exists('linkmonitor', $props['Link'])) {
			if($props['Link']['linkmonitor'] == "INTERNAL" || $props['Link']['linkmonitor'] == "MONSTAT") {
				$params['deviceID'] = $key;
				$params['device']['previous_properties'] = $props;
				$properties['Link']['value'] = LINK_TIMEDOUT;
				$params['device']['properties'] = $properties;
		//		print_r($params);
				$feedback['result'][] = updateDeviceProperties($params);
			}
		}
	}
	// print_r($feedback);
	// echo "</pre>";
	return $feedback;
}

function getGroup($params) {
	$feedback['Name'] = 'getGroup';
	$feedback['result'] = array();
	$groupID = $params['commandvalue'];
	$mysql = 'SELECT g.groupID as groupID, d.id as deviceID, typeID, inuse FROM ha_mf_device_group g 
					JOIN `ha_mf_devices` d ON g.deviceID = d.id 
					WHERE groupID = '.$groupID; 
	$groups = FetchRows($mysql);
	foreach($groups as $device) {
		$feedback['result'][]['groupselect']['DeviceID'] = $device['deviceID'];
	}
	return $feedback;
}

function createAlert($params) {

	$feedback['Name'] = 'createAlert';
	$feedback['result'] = array();
	if (DEBUG_COMMANDS) {
		echo "<pre>Alerts Params: "; print_r($params); echo "</pre>";
	}
	$params['caller']['deviceID'] = (array_key_exists('deviceID',$params['caller']) ? $params['caller']['deviceID'] : $params['caller']['callerID']);
	$feedback['result'][] = 'AlertID: '.PDOInsert("ha_alerts", array('deviceID' => $params['caller']['deviceID'], 'description' => $params['mess_subject'], 'alert_date' => date("Y-m-d H:i:s"), 'alert_text' => $params['mess_text'], 'priorityID' => $params['priorityID'])).' created';
	return $feedback;
}

function executeMacro($params) {      // its a scheme, process steps. Scheme setup by a) , b) derived from remotekey

// Check conditions
	$feedback['result'] = array();
	$schemeID = $params['schemeID'];
	$callerparams = $params['caller'];
	$loglevel = (array_key_exists('loglevel', $callerparams) ? $callerparams['loglevel'] : Null);
	$asyncthread = (array_key_exists('ASYNC_THREAD', $callerparams) ? $callerparams['ASYNC_THREAD'] : false);
	
	// Check if a commandvalue was given, if so save this for later use
	if (array_key_exists('commandvalue', $params) && !empty($params['commandvalue'])) {
		$params['macro___commandvalue'] = $params['commandvalue'];
	}

	if (DEBUG_COMMANDS) echo "<pre>Enter executeMacro $schemeID".CRLF;
	if (DEBUG_COMMANDS) print_r($params);
	
	$feedback['Name'] = getSchemeName($schemeID);
	
	$mysql = 'SELECT * FROM `ha_remote_scheme_conditions` WHERE `schemesID` = '.$schemeID;
	
	if (!$rescond = mysql_query($mysql)) {
		mySqlError($mysql); 
		if (DEBUG_COMMANDS) echo "Exit executeMacro</pre>".CRLF;
		return false;
	}
	
	while ($rowcond = mysql_fetch_assoc($rescond)) {	
		$testvalue = array();
		switch ($rowcond['type'])
		{
		case SCHEME_CONDITION_DEVICE_PROPERTY_VALUE: 									// what a mess already :(
			if (DEBUG_COMMANDS) echo "SCHEME_CONDITION_DEVICE_PROPERTY_VALUE".CRLF;
			$condtype = "SCHEME_CONDITION_DEVICE_PROPERTY_VALUE";
			$testvalue[] = getDeviceProperties(Array('propertyID' => $rowcond['propertyID'], 'deviceID' => $rowcond['deviceID']))['value'];
			break;
		case SCHEME_CONDITION_GROUP_PROPERTY_AND:
		case SCHEME_CONDITION_GROUP_PROPERTY_OR:
			if (DEBUG_COMMANDS) echo "SCHEME_CONDITION_GROUP_PROPERTY_AND_OR".CRLF;
			$condtype = "SCHEME_CONDITION_GROUP_PROPERTY_AND_OR";
			if ($rowcond['type'] == SCHEME_CONDITION_GROUP_PROPERTY_AND) {
				$test = 1;
			} else {
				$test = 0;
			}
			$groups = getGroup(array('commandvalue' => $rowcond['groupID']))['result'];
			// [getGroup] => Array (['result'][0] => Array ([groupselect] => Array ([DeviceID] => 1))
			foreach ($groups as $device) {
				if ($rowcond['type'] == SCHEME_CONDITION_GROUP_PROPERTY_AND) {
					$test = $test & getDeviceProperties(Array('deviceID' => $device['groupselect']['DeviceID'], 'propertyID' => $rowcond['propertyID']))['value'];
				} else {
					$test = $test | getDeviceProperties(Array('deviceID' => $device['groupselect']['DeviceID'], 'propertyID' => $rowcond['propertyID']))['value'];
				}
			}
			$testvalue[] = $test;
			break;
		case SCHEME_CONDITION_CURRENT_TIME: 
			if (DEBUG_COMMANDS) echo "SCHEME_CONDITION_CURRENT_TIME</p>";
			$condtype = "SCHEME_CONDITION_CURRENT_TIME";
			$testvalue[] = time();
			break;
		}

		if ($rowcond['value'] !== NULL) {
			switch (strtoupper($rowcond['value']))
			{
			case "ON":
				$testvalue[] = STATUS_ON;
				break;
			case "OFF":
				$testvalue[] = STATUS_OFF;
				break;
			default:
				switch ($rowcond['type'])
				{
				case SCHEME_CONDITION_CURRENT_TIME: 
					$temp = preg_split( "/([+-])/" , $rowcond['value'], -1, PREG_SPLIT_DELIM_CAPTURE);
					$temp[0] = strtoupper($temp[0]);
					if ($temp[0] == "DAWN" || $temp[0] == "DUSK") {
						if ($temp[0] == "DAWN") $temp[0] = getDawn();
						if ($temp[0] == "DUSK") $temp[0] = getDusk();
						if (isset($temp[1])) {
							$testvalue[] = strtotime("today $temp[0] $temp[1]$temp[2] minutes");
						} else {
							$testvalue[] = strtotime("today $temp[0]");
						}
					} else {
						$testvalue[] = strtotime("today $temp[0]");
					}
					break;
				default:
					$testvalue[] = $rowcond['value'];
					break;
				}
				break;
			}
		}
		switch ($rowcond['operator'])
		{
		case CONDITION_GREATER:
			if ($testvalue[0] <= $testvalue[1]) {
				if (DEBUG_COMMANDS) echo 'Fail: "'.$testvalue[0].'" > "'.$testvalue[1].'"'.CRLF;
				$feedback['message'] = $feedback['Name'].': Programme '.getProperty($rowcond['propertyID'])['description'].' aborted, startup test failed '.$testvalue[0].' > '.$testvalue[1];
				if (DEBUG_COMMANDS) echo "Exit executeMacro</pre>".CRLF;
				return $feedback;
			}
			break;
		case CONDITION_LESS:
			if ($testvalue[0] >= $testvalue[1]) {
				if (DEBUG_COMMANDS) echo 'Fail: "'.$testvalue[0].'" < "'.$testvalue[1].'"'.CRLF;
				$feedback['message'] = $feedback['Name'].': Programme '.getProperty($rowcond['propertyID'])['description'].' aborted startup test failed '.$testvalue[0].' < '.$testvalue[1];
				if (DEBUG_COMMANDS) echo "Exit executeMacro</pre>".CRLF;
				return $feedback;
			}
			break;
		case CONDITION_EQUAL:
			if ($testvalue[0] != $testvalue[1]) {
				if (DEBUG_COMMANDS) echo 'Fail: "'.$testvalue[0].'" == "'.$testvalue[1].'"'.CRLF;
				$feedback['message'] = $feedback['Name'].': Programme '.getProperty($rowcond['propertyID'])['description'].' aborted startup test failed '.$testvalue[0].' == '.$testvalue[1];
				if (DEBUG_COMMANDS) echo "Exit executeMacro</pre>".CRLF;
				return $feedback;
			}
			break;
		}
		if (DEBUG_COMMANDS) echo "Condition Pass: condition value: ".$testvalue[0].", test for: ".$testvalue[1].CRLF;
	}
	
		
	//$callerparams['deviceID'] = (array_key_exists('deviceID', $callerparams) ? $callerparams['deviceID'] : $callerID);
	$mysql = 'SELECT ha_remote_schemes.name, ha_remote_schemes.runasync, ha_remote_scheme_steps.id, ha_mf_commands.description as commandName, ha_remote_scheme_steps.groupID, ha_remote_scheme_steps.deviceID, ha_remote_scheme_steps.commandID, ha_remote_scheme_steps.value,ha_remote_scheme_steps.runschemeID,ha_remote_scheme_steps.sort,ha_remote_scheme_steps.alert_textID 
	FROM ha_remote_schemes 
	JOIN ha_remote_scheme_steps ON ha_remote_schemes.id = ha_remote_scheme_steps.schemesID 
	LEFT JOIN ha_mf_commands ON ha_remote_scheme_steps.commandID = ha_mf_commands.id
	WHERE ha_remote_schemes.id ='.$schemeID.'.
	ORDER BY ha_remote_scheme_steps.sort';
	
	// Trap any async SCHEMES here
	if ($rowshemesteps = FetchRows($mysql)) {
		if (!$asyncthread && current($rowshemesteps)['runasync']) {
			$devstr = (array_key_exists('deviceID', $callerparams) ? "deviceID=".$callerparams['deviceID'] : "");
			$curlparams = "ASYNC_THREAD callerID=$callerparams[callerID] $devstr messagetypeID=MESS_TYPE_SCHEME schemeID=$schemeID";
			$cmd = 'nohup nice -n 10 /usr/bin/php -f '.getPath().'process.php '.$curlparams;
			$outputfile=  tempnam( sys_get_temp_dir(), 'async' );
			$pidfile=  tempnam( sys_get_temp_dir(), 'async' );
			exec(sprintf("%s > %s 2>&1 & echo $! >> %s", $cmd, $outputfile, $pidfile));
			$feedback['message'] = "Initiated ".$feedback['Name'].' sequence.'; //."  Log:".$outputfile;
			if (DEBUG_COMMANDS) echo "Exit executeMacro</pre>".CRLF;
			return $feedback;		// GET OUT
		}
		foreach ($rowshemesteps as $step) {
			//Deleting|{property___DeleteFile}
			$text =  $step['value'];
			if (DEBUG_PARAMS) echo 'StepValue: '.$text.CRLF;
			if (DEBUG_PARAMS) echo 'last___message: '.(array_key_exists('last___message', $params) ? $params['last___message'] : 'Non-existent').CRLF;
			$params['deviceID'] =  $step['deviceID'];
			$params['commandID'] = $step['commandID'];
			$params['schemeID'] = $step['runschemeID'];
			$params['alert_textID'] = $step['alert_textID'];
			$params['commandvalue'] = replacePlaceholder($text, $params);		// Replace placeholders in commandvalue
			if (DEBUG_PARAMS) echo 'StepValue after replacePlaceholder: '.$params['commandvalue'].CRLF;
			//replaceText($params, true);
			$feedback['result']['executeMacro:'.$step['id'].'_'.$step['commandName']] = SendCommand($params);
			// TODO:: check for 'error'
			// TODO:: bubble up message?
			// print_r($feedback['result']);
			if (array_key_exists('message',$feedback['result']['executeMacro:'.$step['id'].'_'.$step['commandName']]['result'])) $params['last___message'] = $feedback['result']['executeMacro:'.$step['id'].'_'.$step['commandName']]['result']['message'];
			if (array_key_exists('error',$feedback['result']['executeMacro:'.$step['id'].'_'.$step['commandName']]['result'])) $params['last___message'] = $feedback['result']['executeMacro:'.$step['id'].'_'.$step['commandName']]['result']['error'];
			if (DEBUG_PARAMS) echo 'Loaded last___message: '.(array_key_exists('last___message', $params) ? $params['last___message'] : 'Non-existent').CRLF;
		}
	} else {
		$feedback['error'] = 'No scheme steps found: '.$schemeID;
	}
	if (DEBUG_COMMANDS) echo "Exit executeMacro</pre>".CRLF;
	if (empty($feedback['message'])) unset($feedback['message']);
	return $feedback;
}

function getDuskDawn($params) {

	$feedback['Name'] = 'getDuskDawn';
	$feedback['result'] = array();

	$station = $params['commandvalue'];
	$mydeviceID = DEVICE_DARK_OUTSIDE;
	ini_set('max_execution_time',30);

	$mydeviceID = array("USAL0594" => 196);
	//USAL0594

	// TODO:: should be a command, device, connection
	$url = "https://query.yahooapis.com/v1/public/yql?q=select%20*%20from%20weather.forecast%20where%20location%3D%22".$station.
	"%22%20and%20u%3D%22c%22&format=json&diagnostics=true&callback=";
	$get = restClient::get($url);
//	$response = file_get_contents($url);
	if (DEBUG_COMMANDS) echo "<pre>";
	//if (DEBUG_YAHOOWEATHER) echo "response: ".$response;
	$feedback['error'] = ($get->getresponsecode()==200 ? 0 : $get->getresponsecode());
    if (!$feedback['error']) {
		$result = json_decode($get->getresponse());
		$feedback['result'] =  json_encode(json_decode($get->getresponse(), true));
		//if (DEBUG_YAHOOWEATHER) print_r($result);
		if (DEBUG_COMMANDS) print_r($result);
		$result = $result->{'query'}->{'results'}->{'channel'};

		$tsr = date("H:i", strtotime($result->{'astronomy'}->{'sunrise'}));
		$tss = date("H:i", strtotime($result->{'astronomy'}->{'sunset'}));

		$device['previous_properties'] = getDeviceProperties(Array('deviceID' => DEVICE_DARK_OUTSIDE));

		$properties['Astronomy Sunrise']['value'] = $tsr;
		$properties['Astronomy Sunset']['value'] = $tss;
		$properties['Status']['value'] = $device['previous_properties']['Status']['value'];
		$properties['Link']['value'] = LINK_UP;
		$device['properties'] = $properties;
	} else {
		$properties['Status']['value'] = STATUS_ERROR;
		$properties['Link']['value'] = LINK_DOWN;
		$device['properties'] = $properties;
	}
	//$feedback['result']['updateDeviceProperties'] = updateDeviceProperties(array( 'callerID' => DEVICE_DARK_OUTSIDE, 'deviceID' => DEVICE_DARK_OUTSIDE, 'device' => $device));

	if (DEBUG_COMMANDS) echo "</pre>";
	return $feedback;
}

function setResult($params) {
	$feedback['Name'] = 'setResult';
	$feedback['result'] = array();
	$feedback['message'] = $params['commandvalue'];
	break;
}


function setDevicePropertyCommand(&$params) {
	$feedback['result'] = array();
	
	calculateProperty($params) ;
	
	$tarr = explode("___",$params['commandvalue']);
	$text = $tarr[1];
	$text = replacePlaceholder($text, Array('deviceID' => $params['deviceID']));
	if (strtoupper($text) == "TOGGLE") { 		// Toggle
		if ($params['device']['previous_properties'][$tarr[0]]['value'] == STATUS_ON) 
			$text = STATUS_OFF;
		else
			$text = STATUS_ON;
	}
	$params['device']['properties'][$tarr[0]]['value'] = $text;
	$feedback['Name'] = $tarr[0];
	return $feedback;
}

function getDevicePropertiesCommand___delete($params) {
//getDeviceProperties(array('deviceID' => $params['deviceID'], 'description' => 'Recording Type'))['value']
//getDeviceProperties(Array('propertyID' => $rowcond['propertyID'], 'deviceID' => $rowcond['deviceID']))['value']
//getDeviceProperties(Array('deviceID' => $deviceID))
	$feedback['result'] = array();

	if (array_key_exists('propertyID', $params)) $devprop['propertyID'] = $params['propertyID'];
	$devprop['deviceID'] = $params['deviceID'];
	$feedback['result'] = Array();
	if (!empty($devprop['deviceID'])) {
		if ($properties  = getDeviceProperties($devprop)) {
			if (array_key_exists('propertyID', $params)) { // Returning different format
				$feedback['result'][$properties['propertyID']]['updateStatus']['Status'] = $properties['value'];
				$feedback['result'][$properties['propertyID']]['updateStatus']['PropertyID'] =$properties['propertyID'];
				$feedback['result'][$properties['propertyID']]['updateStatus']['DeviceID'] = $properties['deviceID'];
				$feedback['result'][$properties['propertyID']]['updateStatus']['Datatype'] = $properties['datatype'];
			} else {
				foreach ($properties as $property) {
					$feedback['result'][$property['propertyID']]['updateStatus']['Status'] = $property['value'];
					$feedback['result'][$property['propertyID']]['updateStatus']['PropertyID'] =$property['propertyID'];
					$feedback['result'][$property['propertyID']]['updateStatus']['DeviceID'] = $property['deviceID'];
					$feedback['result'][$property['propertyID']]['updateStatus']['Datatype'] = $property['datatype'];
				}
			}
		}
		// if (($property  = getDeviceProperties(Array( 'deviceID' => $devprop['deviceID'], 'description' => 'Link')))) $feedback['Link'] = $property['value'];
		// if (($property  = getDeviceProperties(Array( 'deviceID' => $devprop['deviceID'], 'description' => 'Timer Remaining')))) $feedback['Timer Remaining'] = $property['value'];
	}
	return $feedback;
}

function getNowPlaying(&$params) {

	$feedback['Name'] = 'getNowPlaying';
	$feedback['result'] = array();

 	$command['caller'] = $params['caller'];
	$command['callerparams'] = $params;
	$command['deviceID'] = $params['deviceID']; 
	$command['commandID'] = COMMAND_GET_VALUE;
	$result = sendCommand($command); 
	

	// echo "<pre>";	
	
	if (array_key_exists('error', $result)) {
		echo "Handle Transport Error";
		print_r($result);
	} else {
		//$result = json_decode($result['result'][0],true);
		// print_r($result['result']);
		$result = $result['result'];
		if (array_key_exists('artist', $result['result']['item']) && array_key_exists('0', $result['result']['item']['artist'])) {
			$properties['Playing']['value'] =  $result['result']['item']['artist'][0].' - '.$result['result']['item']['title'];
			$properties['PlayingID']['value'] =  $result['result']['item']['id'];
		} else {
			$properties['Playing']['value'] = substr($result['result']['item']['label'], 0, strrpos ($result['result']['item']['label'], "."));
			$properties['PlayingID']['value'] =  $result['result']['item']['id'];
		}
		if (!empty(trim($result['result']['item']['file']))) {
			$properties['File']['value'] = $result['result']['item']['file'];
			$params['device']['properties'] = $properties;
			$feedback['message'] = $properties['Playing']['value'];
		} else {
			$feedback['error']='Error - Nothing playing';
		}
		// Handle KODI error
	}	
	return $feedback;
	// echo "</pre>";	
} 

function moveToRecycle(&$params) {

	$feedback['Name'] = 'getNowPlaying';
	$feedback['result'] = array();
// echo "<pre>";
// eg.globals.currentpath\n
// file = eg.globals.currentfile\n
// eg.plugins.XBMC2.SkipNext()\n
// time.sleep(5)\n
// print \'move to Recylce: \' + path + \'\\\\\' + file\nos.rename(path + \'\\\\\' + file,eg.globals.MyRecycle + \'\\\\\' + file)\n
// eg.globals.message=["Deleted",eg.globals.currentfile]\neg.TriggerEvent("SendNotification")')
	// echo "<pre>";
	// print_r($params);

	//		smb://SRVMEDIA/media/My Music Videos/Popular/_Assorted/Milk And Honey - Didi.avi
	// 		  /home/www/vlohome/data/musicvideos/Popular/_Assorted/Milk And Honey - Didi.avi
	
	$infile = str_replace('smb://SRVMEDIA/media/My Music Videos/',LOCAL_MUSIC_VIDEOS,$params['commandvalue']);
	//$result = stat($infile);
	$fparsed = pathinfo($infile);
	$filename = $fparsed['filename'].'.'.$fparsed['extension'];
	// echo $filename.CRLF;
	$tofile = getcwd().LOCAL_RECYCLE.$filename;
	$sendCommand = array('callerID' => $params['caller']['callerID'], 
				'caller'  => $params['caller'],
				'deviceID' => $params['deviceID'], 
				'commandID' => COMMAND_RUN_SCHEME,
				'schemeID' => ALERT_KODI);
	// echo "cp ".$infile.' '.$tofile.CRLF;
	if (copy($infile, $tofile) && unlink($infile)) {
		$feedback['message'] = 'Moved '.$filename.' to recycle bin.';
		$sendCommand['macro___commandvalue'] = 'Deleted File|'.$filename;
	} else {
		$feedback['error'] = 'Error moving '.$filename.' to recycle bin.';
		$sendCommand['macro___commandvalue'] = 'Error deleting File|'.$filename;
	}
	$feedback = sendCommand($sendCommand);

	return $feedback;
	// echo "</pre>";	
} 

function addToPlaylist(&$params) {

//echo "<pre>";
//print_r($params);
	$feedback['Name'] = 'addToPlaylist';
	$feedback['result'] = array();
 
	$file = LOCAL_PLAYLISTS.$params['macro___commandvalue'].'.m3u';
	$error = "";
	if (($playlist = file_get_contents($file)) !== false) {
		$playingfile = $params['device']['previous_properties']['File']['value'];
		$playing = $params['device']['previous_properties']['Playing']['value'];
		if (strpos($playlist, $playingfile) === false) {
			$playlist .= $playingfile."\n";
			if (file_put_contents($file, $playlist) === false) $error = "Could not write playlist ".$file.'|';
		}
		$feedback['message'] = $playing.'|Added to - '.$params['macro___commandvalue'];
	} else {
		$error = 'Could not open playlist: '.$params['macro___commandvalue'].'|';
	}
	if (!empty($error)) {
		$feedback['error'] = 'Could not open playlist - '.$params['macro___commandvalue'].'|';
	}
	return $feedback;
//echo "</pre>";	
} 

function rebootFireTV($params) {

	$feedback['Name'] = 'rebootFireTV';
	$feedback['result'] = array();
	// echo "<pre>";
	// $devstr = (array_key_exists('deviceID', $callerparams) ? "deviceID=".$callerparams['deviceID'] : "");
	// $curlparams = "ASYNC_THREAD callerID=$callerparams[callerID] $devstr messagetypeID=MESS_TYPE_SCHEME schemeID=$schemeID";
	$cmd = 'nohup nice -n 10 '.getPath().'rebootFireTV.sh';
	$outputfile=  tempnam( sys_get_temp_dir(), 'adb' );
	$pidfile=  tempnam( sys_get_temp_dir(), 'adb' );
	exec(sprintf("%s > %s 2>&1 & echo $! >> %s", $cmd, $outputfile, $pidfile));
	$feedback['message'] = "Initiated ".$feedback['Name'].' sequence'.'  Log:'.$outputfile;
	return $feedback;		// GET OUT

} 

function sendmail(&$params) {

	$feedback['Name'] = 'sendmail';
	$feedback['result'] = array();
	$to = $params['device']['previous_properties']['Address']['value'];
	$fromname = 'VloHome'; 

	$headers = 'MIME-Version: 1.0' . "\r\n".
    'From: '.$fromname. "\r\n" .
    'Reply-To: '.$fromname. "\r\n" .
    'X-Mailer: PHP/' . phpversion();
	
	if(!mail($to, $params['mess_subject'],  $params['mess_text'], $headers)) {
	    $feedback['error'] = "Mailer - error";
	    return $feedback;
	}
	else {
		return array();
	}
}

function sendInsteonCommand(&$params) {

	$feedback['Name'] = 'sendInsteonCommand';
	$feedback['result'] = array();

	global $inst_coder;
	if ($inst_coder instanceof InsteonCoder) {
	} else {
		$inst_coder = new InsteonCoder();
	}
	if (DEBUG_DEVICES) echo "commandvalue a".$params['commandvalue'].CRLF;
	if ($params['commandID'] == COMMAND_ON && $params['commandvalue'] == NULL) $params['commandvalue']= $params['onlevel'];
	if (DEBUG_DEVICES) echo "commandvalue b".$params['commandvalue'].CRLF;
	if ($params['commandvalue']>100) $params['commandvalue']=100;
	if (DEBUG_DEVICES) echo "commandvalue c".$params['commandvalue'].CRLF;
	if ($params['commandvalue']>0) $params['commandvalue']=255/100*$params['commandvalue'];
	if (DEBUG_DEVICES) echo "commandvalue d".$params['commandvalue'].CRLF;
	if ($params['commandvalue'] == NULL && $params['commandID'] == COMMAND_ON) $params['commandvalue']=255;		// Special case so satify the replace in on command
	$cv_save = $params['commandvalue'];
	$params['commandvalue'] = dec2hex($params['commandvalue'],2);
	if (DEBUG_DEVICES) echo "commandvalue ".$params['commandvalue'].CRLF;

	$tcomm = replaceCommandPlaceholders($params);
	$params['commandvalue'] = $cv_save;
	
	if (DEBUG_DEVICES) echo "Rest deviceID ".$params['deviceID']." commandID ".$params['commandID'].CRLF;
	$url=setURL($params, $feedback['commandstr']);
	$feedback['commandstr'] .= $tcomm.'=I=3';
	if (DEBUG_DEVICES) echo $url.CRLF;
	$curl = restClient::get($url.$tcomm.'=I=3',null, "", "", $params['device']['connection']['timeout']);
	if ($curl->getresponsecode() != 200 && $curl->getresponsecode() != 204) 
		$feedback['error'] = $curl->getresponsecode().": ".$curl->getresponse();
	else 
		$feedback['result'][] = $curl->getresponse();
	usleep(INSTEON_SLEEP_MICRO);
	
	if (array_key_exists('error', $feedback)) {
		if ($params['dimmable'] == "YES") {
			if (!is_null($params['commandvalue'])) $params['device']['properties']['Level']['value'] = $params['commandvalue'];
		}
	}
	return $feedback;
}

function sendX10Command(&$params) {

	$feedback['Name'] = 'sendX10Command';
	$feedback['result'] = array();
	
	global $inst_coder;
	if ($inst_coder instanceof InsteonCoder) {
	} else {
		$inst_coder = new InsteonCoder();
	}
	$tcomm = str_replace("{mycommandID}",$params['commandID'],$params['command']);
	if ($params['dimmable'] == "YES") {
		$dims = 0;
		if ($params['commandvalue']>0 && $params['commandvalue'] < 100) $dims=(integer)round(10-10/100*$params['commandvalue']);
		if (DEBUG_DEVICES) echo "commandvalue ".$params['commandvalue'].CRLF;
		if (DEBUG_DEVICES) echo "dims ".$dims.CRLF;
		while($dims > 0) {
			$tcomm .= COMMAND_DIM_CLASS_X10_INSTEON_DIMM;
			$dims--;
		}
		$tcomm = COMMAND_DIM_CLASS_X10_INSTEON_OFF.$tcomm; 	// Add off in front
	} else {
		$params['commandvalue'] = 100;
	}
	if ($params['commandvalue']>100) $params['commandvalue']=100;
	if ($params['commandvalue']!=100 && $params['commandID'] == COMMAND_ON) $params['commandvalue'] = $params['onlevel'];
	if ($params['commandvalue'] == NULL && $params['commandID'] == COMMAND_ON) $params['commandvalue']=100;		// Special case so satify the replace in on command
//		$tcomm .={code}a80=I=3;	$tcomm .={code}b80=I=3 $tcomm .= "|{code}{unit}00=I=3"; $tcomm .= "|{code}a80=I=3";	$tcomm .= "|0b80=I=3";
//		$tcomm .= "|{code}480=I=3";			// dim 480  $tcomm .= "|a780=I=3";	$tcomm .= "|0b80=I=3";
	$tcomm = str_replace("{code}",$inst_coder->x10_code_encode($params['device']['code']),$tcomm);
	$tcomm = str_replace("{unit}",$inst_coder->x10_unit_encode($params['device']['unit']),$tcomm);
	if (DEBUG_DEVICES) echo "Rest deviceID ".$params['deviceID']." commandID ".$params['commandID'].CRLF;
	$commands=explode("|", $tcomm);
	//
	// handle dimming, cannot give commandvalue so dimming lots of times
	//
	foreach ($commands as $command) {
		//$url=$params['device']['connection']['targetaddress'].":".$params['device']['connection']['targetport'].$params['device']['connection']['page'].$command.'=I=3';
		$url=setURL($params, $feedback['commandstr']);
		$feedback['commandstr'] .= $command.'=I=3';
		if (DEBUG_DEVICES) echo $url.CRLF;
		$curl = restClient::get($url.$command.'=I=3');
		if ($curl->getresponsecode() != 200 && $curl->getresponsecode() != 204) 
			$feedback['error'] = $curl->getresponsecode().": ".$curl->getresponse();
		else 
			$feedback['result'][] = $curl->getresponse();
		usleep(INSTEON_SLEEP_MICRO);
	}     
	if (!array_key_exists('error', $feedback)){
		if ($params['dimmable'] == "YES") {
			if (!is_null($params['commandvalue'])) $params['device']['properties']['Level']['value'] = $params['commandvalue'];
		}
	}
	return $feedback;
}

function sendGenericPHP(&$params) {
// TODO: Result not arrays?
	$feedback['Name'] = 'sendGenericPHP';
	$feedback['result'] = array();

	$func = $params['command'];
	if ($func == "sleep") {
		$feedback['commandstr'] = $func.' '.$params['commandvalue'];
		$feedback['result'][] = $func($params['commandvalue']);
	} else {
		$feedback['commandstr'] = $func.' '.json_encode($params);
		$feedback['result'] = $func($params);
	}
	return $feedback;
}


function sendGenericHTTP(&$params) {

	$feedback['Name'] = 'sendGenericHTTP';
	$feedback['result'] = array();

	$targettype = $params['device']['connection']['targettype'];
	switch ($targettype)
	{
	case "POSTAPP":          // PHP - vlosite
	case "POSTTEXT":         // Only HTPC & IrrigationCaddy at the moment
	case "POSTURL":          // Web Arduino
	case "JSON":             // Wink
		if (DEBUG_DEVICES) echo $targettype."</p>";
		$tcomm = replaceCommandPlaceholders($params);
		$tmp1 = explode('?', $tcomm);
		if (array_key_exists('1', $tmp1)) { 	// found '?' inside command then take page from command string and add to url
			$params['device']['connection']['page'] .= $tmp1[0];
			$tcomm = $tmp1[1];
		} 
		$url=setURL($params, $feedback['commandstr']);
		if (DEBUG_DEVICES) echo $url." Params: ".$tcomm.CRLF;
		if ($targettype == "POSTTEXT") { 
			$feedback['commandstr'] .= ' '.$tcomm;
			$curl = restClient::post($url, $tcomm, "", "", "text/plain", $params['device']['connection']['timeout']);
		} elseif ($targettype == "POSTAPP") {
			$feedback['commandstr'] .= ' '.$tcomm;
			$curl = restClient::post($url, $tcomm, "", "", "", $params['device']['connection']['timeout']);
		} elseif ($targettype == "JSON") {
			//parse_str($tcomm, $params);
			$postparams = $tcomm;
			if (DEBUG_DEVICES) echo $url." Params: ".$postparams.CRLF;
			$feedback['commandstr'] .= ' '.$postparams;
			$curl = restClient::post($url, $postparams, "", "", "application/json" , $params['device']['connection']['timeout']);
		} else { 
			$feedback['commandstr'] .= $tcomm;
			$curl = restClient::post($url.$tcomm,"","","","",$params['device']['connection']['timeout']);
		}
		if ($curl->getresponsecode() != 200 && $curl->getresponsecode() != 204) 
			$feedback['error'] = $curl->getresponsecode().": ".$curl->getresponse();
		else 
			if ($targettype == "JSON") {
				$feedback['result'] = json_decode($curl->getresponse(), true);
			} else {
				$feedback['result'] = $curl->getresponse();
			}
			//if (array_key_exists('message',$feedback) && $feedback['message'] == "\n[]") unset($feedback['message']); //  TODO:: Some crap coming back from winkapi, fix later
			// echo "***";
			// print_r($feedback);
		break;
	case "GET":          // Sony Cam at the moment
		if (DEBUG_DEVICES) echo "GET</p>";
		$tcomm = replaceCommandPlaceholders($params);
		$url=setURL($params, $feedback['commandstr']);
		if (DEBUG_DEVICES) echo $url.$tcomm.CRLF;
		$feedback['commandstr'] .= $tcomm;
		$curl = restClient::get($url.$tcomm, array(), null, null, $params['device']['connection']['timeout']);
		if ($curl->getresponsecode() != 200 && $curl->getresponsecode() != 204)
			$feedback['error'] = $curl->getresponsecode().": ".$curl->getresponse();
		else 
			$feedback['result'][] = $curl->getresponse();
		break;
	case "TCP":              // iTach
		if (DEBUG_DEVICES) echo "TCP_IR</p>";
		$url = setURL($params, $feedback['commandstr']);
		$feedback['commandstr'] .= $params['command']."\r";
		if (DEBUG_DEVICES) echo $params['device']['connection']['targetaddress'].':'.$params['device']['connection']['targetport'].' - '.$feedback['commandstr'].CRLF;
		// open a client connection
		$client = stream_socket_client('tcp://'.$params['device']['connection']['targetaddress'].':'.$params['device']['connection']['targetport'], $errno, $errorMessage, $params['device']['connection']['timeout']);
		if ($client === false) {
			echo $errno.' '.$errorMessage;
			$result['error'] = "Failed to connect: $errorMessage";
		} else {
			stream_set_timeout($client, $params['device']['connection']['timeout']);
			fwrite($client, $feedback['commandstr']);
			$feedback['result'] = stream_get_line ( $client , 1024 , "\r" );
			fclose($client);
		}
		// TODO:: Error handling (GCache errors)
		//if ($feedback['result'] != "ERR", or busy...
		break;
	case null:
	case "NONE":          // Virtual Devices
		if (DEBUG_DEVICES) echo "DOING NOTHING</p>";
		break;
	}
	if (array_key_exists('result', $feedback)) {
		// if (!preg_match('/[\[\]$*}{@#~><>|=_+¬]/', $feedback['result'])) $feedback['message'] = $feedback['result'];
//			$feedback['message'] = $feedback['result'];
//			$feedback['message'] = preg_replace('/\s+/',' ',preg_replace('~\R~',' ',strip_tags($feedback['result'])));

	}
	return $feedback;
}

function calculateProperty(&$params) {

	$feedback['Name'] = 'calculateProperty';
	//$feedback['commandstr'] = "I send this";
	$feedback['result'] = array();

	if (DEBUG_COMMANDS) {
		echo "<pre>".$feedback['Name'].': '; print_r($params); echo "</pre>";
	}

	// 	{calculate___{property_position}+1
	if (preg_match("/\{calculate___(.*?)\}/", $params['commandvalue'], $matches)) {
		if (DEBUG_COMMANDS) {echo "<pre> calculate "; print_r ($matches); echo "</pre>";}
		$calcvalue = eval('return '.$matches[1].';');
		$params['commandvalue'] = str_replace($matches[0], $calcvalue, $params['commandvalue']);
	}

	// $feedback['message'] = "all good";
	// if () $feedback['error'] = "Not so good";
	
	return $feedback;
}

// Private
function NOP() {
	$feedback['result'] = "Nothing done";
	return $feedback;
}
?>