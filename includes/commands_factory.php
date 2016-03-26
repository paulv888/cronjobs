<?php
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
			$result = SendCommand($params);
			// TODO:: check for 'error'
			// TODO:: bubble up message?
			// print_r($feedback['result']);
			if (array_key_exists('message',$result)) $params['last___message'] = $result['message'];
			if (array_key_exists('error',$result)) $params['last___message'] = $result['error'];
			if (DEBUG_PARAMS) echo 'Loaded last___message: '.(array_key_exists('last___message', $params) ? $params['last___message'] : 'Non-existent').CRLF;
			$feedback['result']['executeMacro:'.$step['id'].'_'.$step['commandName']] = $result;
		}
	} else {
		$feedback['error'] = 'No scheme steps found: '.$schemeID;
	}
	if (DEBUG_COMMANDS) echo "Exit executeMacro</pre>".CRLF;
	if (empty($feedback['message'])) unset($feedback['message']);
	return $feedback;
}

function getDuskDawn(&$params) {

	$feedback['Name'] = 'getDuskDawn';
	$feedback['result'] = array();

	$station = $params['commandvalue'];
	ini_set('max_execution_time',30);
// echo "<pre>";
// print_r($params);

	$row = FetchRow("SELECT * FROM ha_mi_oauth20 where id ='YAHOO'");
	$credentials['method'] = $row['method'];
	$credentials['client_id'] = $row['clientID'];
	$credentials['secret'] = $row['secret'];
	
    $url = "https://query.yahooapis.com/v1/yql";  
    $args = array();  
    $args["q"] = 'select * from weather.forecast where woeid in (12773052) and u="c"';  
	// $args["diagnostics"] = "true";
	// $args["debug"] = "true";
    $args["format"] = "json";  

	$get = RestClient::get($url,$args,$credentials,30);

	if (DEBUG_COMMANDS) echo "<pre>";
	//if (DEBUG_YAHOOWEATHER) echo "response: ".$response;
	$feedback['error'] = ($get->getresponsecode()==200 ? "" : $get->getresponsecode());
    if (!$feedback['error']) {
		$result = json_decode($get->getresponse());
		$feedback['result'] =  json_encode(json_decode($get->getresponse(), true));
		//if (DEBUG_YAHOOWEATHER) print_r($result);
		if (DEBUG_COMMANDS) print_r($result);
		$result = $result->{'query'}->{'results'}->{'channel'};

		$tsr = date("H:i", strtotime(preg_replace("/:(\d) /",":0$1 ",$result->{'astronomy'}->{'sunrise'})));
		$tss = date("H:i", strtotime(preg_replace("/:(\d) /",":0$1 ",$result->{'astronomy'}->{'sunset'})));

		//$device['previous_properties'] = getDeviceProperties(Array('deviceID' => $params['deviceID']));

		$properties['Astronomy Sunrise']['value'] = $tsr;
		$properties['Astronomy Sunset']['value'] = $tss;
		$properties['Link']['value'] = LINK_UP;
		$params['device']['properties'] = $properties;
	} else {
		$properties['Status']['value'] = STATUS_ERROR;
		$properties['Link']['value'] = LINK_DOWN;
		$params['device']['properties'] = $properties;
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

function setSessionVar(&$params) {


	$feedback['result'] = array();
	
	// calculateProperty($params) ;
	
	$tarr = explode("___",$params['commandvalue']);
	$text = $tarr[1];
	$text = replacePlaceholder($text, Array('deviceID' => $params['deviceID']));
	if (strtoupper($text) == "TOGGLE") { 		// Toggle
		if ($params['device']['previous_properties'][$tarr[0]]['value'] == STATUS_ON) 
			$text = STATUS_OFF;
		else
			$text = STATUS_ON;
	}

	ini_set('session.use_only_cookies', false);
	ini_set('session.use_cookies', false);
	ini_set('session.use_trans_sid', false);
	ini_set('session.cache_limiter', null);	
	session_start();
	$_SESSION['properties'][$tarr[0]]['value'] = $text;
	$feedback['result'] = $_SESSION['properties'];
	session_write_close();

	$feedback['Name'] = $tarr[0];
	// print_r($_SESSION);
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
		} else {
			$properties['Playing']['value'] = substr($result['result']['item']['label'], 0, strrpos ($result['result']['item']['label'], "."));
		}
		if (!empty(trim($result['result']['item']['file']))) {
			$br = strpos( $properties['Playing']['value'] , ' - ');
			if ($br !== false) {
				$properties['Artist']['value'] = substr($properties['Playing']['value'], 0, $br);
				$properties['Title']['value'] =  substr($properties['Playing']['value'], $br + 3);
			}
			$properties['PlayingID']['value'] =  $result['result']['item']['id'];
			$properties['File']['value'] = $result['result']['item']['file'];
			$params['device']['properties'] = $properties;
			$feedback['message'] = $properties['Playing']['value'];
		} else {
			$properties['Playing']['value'] =  'Nothing';
			$properties['PlayingID']['value'] =  '0';
			$properties['File']['value'] = '*';
			$properties['Artist']['value'] = '*';
			$properties['Title']['value'] =  '*';
			$params['device']['properties'] = $properties;
			$feedback['error']='Error - Nothing playing';
		}
		// Handle KODI error
	}	
	return $feedback;
	// echo "</pre>";	
} 

function moveToRecycle($params) {

	$feedback['Name'] = 'moveToRecycle';
	$feedback['result'] = array();
	// echo "<pre>".$feedback['Name'].CRLF;
	// print_r($params);

	//		smb://SRVMEDIA/media/My Music Videos/Popular/_Assorted/Milk And Honey - Didi.avi
	// 		  /home/www/vlohome/data/musicvideos/Popular/_Assorted/Milk And Honey - Didi.avi
	
	//		smb://SRVMEDIA/media/My Music Videos/Popular/_Assorted/Milk And Honey - Didi.avi
	// 		  /home/www/vlohome/data/musicvideos/Popular/_Assorted/Milk And Honey - Didi.avi

	$cmvfile = mv_toLocal($params['commandvalue']);
	//$result = stat($infile);
	$fparsed = mb_pathinfo($cmvfile);
	$fparsed['dirname'] = rtrim($fparsed['dirname'], '/') . '/';
	$params['file'] = $fparsed ;
	$params['createbatchfile'] = 0;		// have to stay online, not returning $batchfile
	$params['movetorecycle'] = 1;
	$result = moveMusicVideo($params);
	$feedback['result'][] = $result;
	$command = array('callerID' => $params['caller']['callerID'], 
		'caller'  => $params['caller'],
		'deviceID' => $params['deviceID'], 
		'commandID' => COMMAND_RUN_SCHEME,
		'schemeID' => ALERT_KODI);
		
	if (!array_key_exists('error',$result)) {
		$command['macro___commandvalue'] = $result['message'];
	} else {
		$command['macro___commandvalue'] = $result['error'];
	}
	if (!array_key_exists('createbatchfile',$params)) $feedback['result'][] = sendCommand($command);
	return $feedback;
}
	
function moveMusicVideo($params) {

	$feedback['Name'] = 'moveMusicVideo';
	$feedback['result'] = array();
	$feedback['message'] = '';
	// echo "<pre>".$feedback['Name'].CRLF;
	// print_r($params);

	$file = $params['file'];
	if ($params['movetorecycle']) {
		$rand = rand(100000,999999);
		$file['moveto'] = LOCAL_MUSIC_VIDEOS.LOCAL_RECYCLE;
		$file['newname'] = $file['filename'].' ('.$rand.')';
	}
	if ($params['createbatchfile']) {
		$batchfile = $params['file']['batchfile'];
		if ($params['movetorecycle']) $batchfile .= '#Recycle'."\n";
		$batchfile .= '#From: '.$file['dirname'].$file['filename']."\n";
		$batchfile .= '#__To: '.$file['moveto'].$file['newname']."\n";
	}
	
	$matches = glob($file['moveto'].$file['newname'].'.*');
	if (strtolower($file['dirname'].$file['filename']) != strtolower($file['moveto'].$file['newname'])) {
		// echo "<pre>Matches ".$feedback['Name'].CRLF;
		// print_r($matches);
		if (!empty($matches)) {			// Assume we found an upgrade and move to recycle
			// Find vid match
			// echo "<pre>Found old one ".$feedback['Name'].' '.$dirname.$fparsed['basename'].CRLF;
			foreach ($matches as $match) {
				$fparsed = mb_pathinfo($match);
				if (!in_array($fparsed['extension'], Array("tbn", "nfo"))) {
					$feedback['message'] .= "***";
					$dirname = rtrim($fparsed['dirname'], '/') . '/';
					$params['commandvalue'] = mv_toPublic($dirname.$fparsed['basename']);
					$feedback[]['result'] = moveToRecycle($params);
				}
			}
		}
	}

	foreach (Array ($file['extension'], "tbn", "nfo") as $ext) {
		$filename = $file['filename'].'.'.$ext;
		$infile = $file['dirname'].$file['filename'].'.'.$ext;
		$tofile = $file['moveto'].$file['newname'].'.'.$ext;
		
		// echo "cp ".$infile.' '.$tofile.CRLF;
		if (!array_key_exists('error', $feedback)) {
			if (!$params['createbatchfile']) {
	// $cp=1;
				$copy = copy($infile, $tofile) ;
				// echo "$copy".CRLF;
				if ($copy) $unlink = unlink($infile);
				// echo "$unlink".CRLF;
				touch ($tofile);
				if (!in_array($ext, Array("tbn", "nfo"))) {
					if ($copy && $unlink) {
						$feedback['message'] .= $filename.'| moved to '.$tofile."\n";
						$mysql = 'SELECT mv.id FROM `xbmc_video_musicvideos` mv JOIN xbmc_path p ON mv.strPathID = p.id WHERE file = "'.mv_toPublic($infile).'";'; 
						// Remove from Kodi Lib
						if ($mvid = FetchRow($mysql)['id']) {
							$command['caller'] = $params['caller'];
							$command['callerparams'] = $params['caller'];
							$command['deviceID'] = 259;						// Will be back-end KODI now force Paul-PC
							$command['commandID'] = 374;					// removeMusicVideo
							$command['commandvalue'] = $mvid;
							$feedback[]['result'] = sendCommand($command);
						}
						// Refresh to directory
						if (!$params['movetorecycle']) {
							$command['caller'] = $params['caller'];
							$command['callerparams'] = $params;
							$command['deviceID'] = 		259;	// Will be back-end KODI now force Paul-PC
							$command['commandID'] = 	373;	// Scan Directory
							$command['commandvalue'] = mv_toPublic($file['moveto']);
							$result = sendCommand($command); 
							$feedback[]['result'] = $result;
						}
					} else {
						$feedback['error'] = 'Error moving '.$infile.' | to '.$tofile."\n";
					}
				}
			} else {
				if (strtolower($infile) != strtolower($tofile)) {
					$batchfile .= str_replace('`' ,"\`", 'mv -vn "'.$infile.'" "'.$tofile.'"'."\n");
				} else {
					// Trick to allow rename case (seems to be related to CIFS and duplicate inodes
					$batchfile .= str_replace('`' ,"\`", 'mv -vn "'.$infile.'" "'.$tofile.'.1"'."\n");
					$batchfile .= str_replace('`' ,"\`", 'touch "'.$tofile.'.1"'."\n");
					$batchfile .= str_replace('`' ,"\`", 'mv -vn "'.$infile.'.1" "'.$tofile.'"'."\n");
				}
				$feedback['batchfile'] = $batchfile;
			}
		}
	}
	$file = 'mv_videos.log';
	$log = file_get_contents($file);
	if (array_key_exists('error', $feedback)) 
		$log .= date("Y-m-d H:i:s").": Error: ".$feedback['error'];
	else 
		$log .= date("Y-m-d H:i:s").": Moved: ".$feedback['message'];
	file_put_contents($file, $log);
	return $feedback;
	// echo "</pre>";	
} 

function addToFavorites(&$params) {

//echo "<pre>";
//print_r($params);
	$feedback['Name'] = 'addToFavorites';
	$feedback['result'] = array();
 
	$file = LOCAL_PLAYLISTS.$params['macro___commandvalue'].'.m3u';
	$error = "";
	if (($playlist = file_get_contents($file)) !== false) {
		$playingfile = $params['device']['previous_properties']['File']['value'];
		$playing = $params['device']['previous_properties']['Playing']['value'];
		if (strpos($playlist, $playing) === false) {
			$playlist .= $playingfile."\n";
			if (file_put_contents($file, $playlist) === false) $error = "Could not write playlist ".$file.'|';
			$feedback['message'] = $playing.'|Added to - '.$params['macro___commandvalue'];
		} else {
			$feedback['message'] = $playing.'|Already part of - '.$params['macro___commandvalue'];
		}
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

function sendEmail(&$params) {

	$feedback['Name'] = 'sendmail';
	$feedback['result'] = array();
	$to = $params['device']['previous_properties']['Address']['value'];
	$fromname = 'VloHome'; 

	$headers = 'MIME-Version: 1.0' . "\r\n".
    'From: '.$fromname. "\r\n" .
    'Reply-To: '.'vlohome@inbox.com'. "\r\n" .
    'X-Mailer: PHP/' . phpversion() . "\r\n" ;
	
	if(strlen($params['mess_text']) != strlen(strip_tags($params['mess_text']))) {
		$headers.= "Content-Type: text/html; \r\n"; 
	}
	
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
	$curl = restClient::get($url.$tcomm.'=I=3',null, null, $params['device']['connection']['timeout']);
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
	case "POSTTEXT":         // Yahama AV & IrrigationCaddy at the moment
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
		if (DEBUG_DEVICES) echo $url." Params: ".htmlentities($tcomm).CRLF;
		if ($targettype == "POSTTEXT") { 
			$feedback['commandstr'] .= ' '.htmlentities($tcomm);
			$curl = restClient::post($url, $tcomm, null, "text/plain", $params['device']['connection']['timeout']);
		} elseif ($targettype == "POSTAPP") {
			$feedback['commandstr'] .= ' '.$tcomm;
			$curl = restClient::post($url, $tcomm, null, "", $params['device']['connection']['timeout']);
		} elseif ($targettype == "JSON") {
			//parse_str($tcomm, $params);
			$postparams = $tcomm;
			if (DEBUG_DEVICES) echo $url." Params: ".$postparams.CRLF;
			$feedback['commandstr'] .= ' '.$postparams;
			$curl = restClient::post($url, $postparams, null, "application/json" , $params['device']['connection']['timeout']);
		} else { 
			$feedback['commandstr'] .= $tcomm;
			$curl = restClient::post($url.$tcomm ,"" ,null ,"" ,$params['device']['connection']['timeout']);
		}
		if ($curl->getresponsecode() != 200 && $curl->getresponsecode() != 204) 
			$feedback['error'] = $curl->getresponsecode().": ".$curl->getresponse();
		else 
			if ($targettype == "JSON") {
				$feedback['result'] = json_decode($curl->getresponse(), true);
			} else {
				$feedback['result'] = htmlentities($curl->getresponse());
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
		$curl = restClient::get($url.$tcomm, array(), null, $params['device']['connection']['timeout']);
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
			$feedback['result'][] = stream_get_line ( $client , 1024 , "\r" );	
			fclose($client);
		}
		// TODO:: Error handling (GCache errors)
		// completeir,1:1,2
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
//			$feedback['message'] = preg_replace('/\s+/',' ',preg_replace('~\n~',' ',strip_tags($feedback['result'])));

	}
	return $feedback;
}

function calculateProperty(&$params) {

	$feedback['Name'] = 'calculateProperty';
	//$feedback['commandstr'] = "I send this";
	$feedback['result'] = array();

	if (DEBUG_COMMANDS) {echo "<pre>".$feedback['Name'].': '; print_r($params); echo "</pre>";}

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