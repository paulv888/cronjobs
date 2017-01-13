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
	$feedback['commandstr'] = 'PDOInsert...';
	$params['caller']['deviceID'] = (array_key_exists('deviceID',$params['caller']) ? $params['caller']['deviceID'] : $params['caller']['callerID']);
	$feedback['result'][] = 'AlertID: '.PDOInsert("ha_alerts", array('deviceID' => $params['caller']['deviceID'], 'description' => $params['mess_subject'], 'alert_date' => date("Y-m-d H:i:s"), 'alert_text' => $params['mess_text'], 'priorityID' => $params['priorityID'])).' created';
	if ($params['priorityID'] <= 1) $feedback['result'][] = sendBullet($params);
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
	$mysql = 'SELECT ha_remote_schemes.name, ha_remote_schemes.runasync, ha_remote_scheme_steps.id, ha_mf_commands.description as commandName, ha_remote_scheme_steps.groupID, ha_remote_scheme_steps.deviceID, ha_remote_scheme_steps.propertyID, ha_remote_scheme_steps.commandID, ha_remote_scheme_steps.value,ha_remote_scheme_steps.runschemeID,ha_remote_scheme_steps.sort,ha_remote_scheme_steps.alert_textID, s2.runasync as step_async 
	FROM ha_remote_schemes 
	JOIN ha_remote_scheme_steps ON ha_remote_schemes.id = ha_remote_scheme_steps.schemesID 
	LEFT JOIN ha_mf_commands ON ha_remote_scheme_steps.commandID = ha_mf_commands.id
	LEFT JOIN ha_remote_schemes s2 ON ha_remote_scheme_steps.runschemeID = s2.id
	WHERE ha_remote_schemes.id ='.$schemeID.'
	ORDER BY ha_remote_scheme_steps.sort';
		
	//
	// Trap any async SCHEMES here 
	//		Trapping at first step after checking conditions. Nice for root level, however does not allow async in async
	//		Adding to check individual steps as well and spawn immediately
	//
	if ($rowshemesteps = FetchRows($mysql)) {
		if (!$asyncthread && current($rowshemesteps)['runasync']) {
			unset($values);
			$values['callerID'] = $callerparams['callerID'];
			$values['deviceID'] = (array_key_exists('deviceID', $callerparams) ? $callerparams['deviceID'] : "");
			$values['messagetypeID'] = "MESS_TYPE_SCHEME";
			$values['commandvalue'] = (array_key_exists('commandvalue', $callerparams) ? $callerparams['commandvalue'] : null);
			$values['schemeID'] = $schemeID;
			$getparams = http_build_query($values, '',' ');
			$cmd = 'nohup nice -n 10 /usr/bin/php -f '.getPath().'process.php ASYNC_THREAD '.$getparams;

			$outputfile=  tempnam( sys_get_temp_dir(), 'async' );
			$pidfile=  tempnam( sys_get_temp_dir(), 'async' );
			exec(sprintf("%s > %s 2>&1 & echo $! >> %s", $cmd, $outputfile, $pidfile));
			$feedback['message'] = "Initiated ".current($rowshemesteps)['name'].' sequence. Log: '.$outputfile;
			$feedback['commandstr'] = $cmd;
			if (DEBUG_COMMANDS) echo "Exit executeMacro</pre>".CRLF;
			return $feedback;		// GET OUT
		}
		foreach ($rowshemesteps as $step) {
			//Deleting|{property___DeleteFile}
			$text =  $step['value'];
			if (DEBUG_PARAMS) echo '<pre>StepValue: '.$text.CRLF;
			if (DEBUG_PARAMS) echo 'last___message: '.(array_key_exists('last___message', $params) ? $params['last___message'] : 'Non-existent').CRLF;
			$params['deviceID'] =  $step['deviceID'];
			$params['commandID'] = $step['commandID'];
			if (!empty($step['propertyID'])) $params['propertyID'] = $step['propertyID'];
			$params['schemeID'] = $step['runschemeID'];
			$params['alert_textID'] = $step['alert_textID'];

 // {echo "<pre>before error ";print_r($params);echo "</pre>";}

			if ($params['deviceID'] == DEVICE_CURRENT_SESSION) {
				if (array_key_exists('SESSION', $params)) {
					$params['deviceID'] = $params['SESSION']['properties']['SelectedPlayer']['value'];
				} else if (array_key_exists('SESSION', $params['caller'])) {
					$params['deviceID'] = $params['caller']['SESSION']['properties']['SelectedPlayer']['value'];
				} else $params['SESSION']['properties']['SelectedPlayer']['value'] = DEVICE_DEFAULT_PLAYER;
			}

			$text = replacePropertyPlaceholders($text, $params);		// Replace placeholders in commandvalue
			if (DEBUG_PARAMS) echo 'StepValue after replacePropertyPlaceholders: '.$text.CRLF;

			$text = replaceCommandPlaceholders($text, $params);		// Replace placeholders in commandvalue
			$params['commandvalue'] = $text;
			splitCommandvalue($params);
			if (DEBUG_PARAMS) echo 'StepValue after replaceCommandPlaceholders: '.$params['commandvalue'].CRLF;
			if (DEBUG_PARAMS) echo 'Split after splitCommandvalue: '.print_r((array_key_exists('cvs',$params) ? $params['cvs'] : array("No params to split"))).CRLF;

			$result = Array();
			if ($step['step_async']) {			// Spawn it
				unset($values);
				$values['callerID'] = $callerparams['callerID'];
				$values['deviceID'] = (array_key_exists('deviceID', $callerparams) ? $callerparams['deviceID'] : "");
				$values['messagetypeID'] = "MESS_TYPE_SCHEME";
				$values['commandvalue'] = (array_key_exists('commandvalue', $params) ? $params['commandvalue'] : null);
				$values['schemeID'] = $params['schemeID'];
				$getparams = http_build_query($values, '',' ');
				
				$cmd = 'nohup nice -n 10 /usr/bin/php -f '.getPath().'process.php ASYNC_THREAD '.$getparams;

				$outputfile=  tempnam( sys_get_temp_dir(), 'async' );
				$pidfile=  tempnam( sys_get_temp_dir(), 'async' );
				exec(sprintf("%s > %s 2>&1 & echo $! >> %s", $cmd, $outputfile, $pidfile));
				$result['message'] = "Initiated ".$step['name'].' sequence. Log: '.$outputfile;
				$result['commandstr'] = $cmd;
				if (DEBUG_COMMANDS) echo "Spawned async step</pre>".CRLF;
			} else {
				$result = SendCommand($params);
			}
			//echo "****";print_r($result);
			if (array_key_exists('message',$result)) $params['last___message'] = $result['message'];
			if (array_key_exists('error',$result)) $params['last___message'] = $result['error'];
			if (DEBUG_PARAMS) echo 'Loaded last___message: >'.(array_key_exists('last___message', $params) ? $params['last___message'] : 'Non-existent').'<'.CRLF;
			if (DEBUG_PARAMS) echo '</pre>';
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
        $error = false;
        if ($get->getresponsecode()!=200) $error=true;
        if (!$error) {
                $result = json_decode($get->getresponse());
		if (DEBUG_COMMANDS) print_r($result);
                $feedback['result'] =  json_encode(json_decode($get->getresponse(), true),JSON_UNESCAPED_SLASHES);
                if (!isset($result->{'query'}->{'results'})) {
                        $error = true;
                } else {
			$result = $result->{'query'}->{'results'}->{'channel'};

			$tsr = date("H:i", strtotime(preg_replace("/:(\d) /",":0$1 ",$result->{'astronomy'}->{'sunrise'})));
			$tss = date("H:i", strtotime(preg_replace("/:(\d) /",":0$1 ",$result->{'astronomy'}->{'sunset'})));
			$properties['Astronomy Sunrise']['value'] = $tsr;
			$properties['Astronomy Sunset']['value'] = $tss;
			$properties['Link']['value'] = LINK_UP;
			$params['device']['properties'] = $properties;
			if (DEBUG_COMMANDS) print_r($params);
		}
	}
	if ($error) {
                $feedback['error'] = $get->getresponsecode();
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

	if (DEBUG_COMMANDS) { echo "<pre>"; print_r($params); echo "</pre>"; };

	calculateProperty($params) ;
	$tarr = explode("___",$params['commandvalue']);
	$text = $tarr[1];
	$text = replacePropertyPlaceholders($text, Array('deviceID' => $params['deviceID']));
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
	$text = replacePropertyPlaceholders($text, Array('deviceID' => $params['deviceID']));
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
		$properties['Playing']['value'] =  'Nothing';
		$properties['File']['value'] = '*';
		$properties['Artist']['value'] = '*';
		$properties['Title']['value'] =  '*';
		$properties['Thumbnail']['value'] = "https://vlohome.no-ip.org/images/headers/offline.png?t=".rand();
		$properties['PlayingID']['value'] =  '0';
		$params['device']['properties'] = $properties;
		$feedback['error']='Error - Nothing playing';
	} else {
// echo "<pre>";
// print_r($result);
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
			$properties['File']['value'] = $result['result']['item']['file'];
			$properties['Thumbnail']['value'] = $result['result']['item']['thumbnail'];
			$properties['PlayingID']['value'] =  $result['result']['item']['id'];
			$params['device']['properties'] = $properties;
			$feedback['message'] = $properties['Playing']['value'];
		} else {
			$properties['Playing']['value'] =  'Nothing';
			$properties['File']['value'] = '*';
			$properties['Artist']['value'] = '*';
			$properties['Title']['value'] =  '*';
			$properties['Thumbnail']['value'] = "https://vlohome.no-ip.org/images/headers/offline.png?t=".rand();
			$properties['PlayingID']['value'] =  '0';
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
	$cmd = 'nohup nice -n 10 '.getPath().'rebootFireTV.sh';
	$outputfile=  tempnam( sys_get_temp_dir(), 'adb' );
	$pidfile=  tempnam( sys_get_temp_dir(), 'adb' );
	exec(sprintf("%s > %s 2>&1 & echo $! >> %s", $cmd, $outputfile, $pidfile));
	$feedback['message'] = "Initiated ".$feedback['Name'].' sequence'.'  Log:'.$outputfile;
	return $feedback;		// GET OUT

} 

function fireTVnetflix($params) {

        $feedback['Name'] = 'fireTVnetflix';
        $feedback['result'] = array();
        $cmd = 'nohup nice -n 10 '.getPath().'fireTVnetflix.sh';
        $outputfile=  tempnam( sys_get_temp_dir(), 'adb' );
        $pidfile=  tempnam( sys_get_temp_dir(), 'adb' );
        exec(sprintf("%s > %s 2>&1 & echo $! >> %s", $cmd, $outputfile, $pidfile));
        $feedback['message'] = "Initiated ".$feedback['Name'].' sequence'.'  Log:'.$outputfile;
        return $feedback;               // GET OUT

}

function fireTVkodi($params) {

        $feedback['Name'] = 'fireTVkodi';
        $feedback['result'] = array();
        $cmd = 'nohup nice -n 10 '.getPath().'fireTVkodi.sh';
        $outputfile=  tempnam( sys_get_temp_dir(), 'adb' );
        $pidfile=  tempnam( sys_get_temp_dir(), 'adb' );
        exec(sprintf("%s > %s 2>&1 & echo $! >> %s", $cmd, $outputfile, $pidfile));
        $feedback['message'] = "Initiated ".$feedback['Name'].' sequence'.'  Log:'.$outputfile;
        return $feedback;               // GET OUT

}

function fireTVcamera($params) {

        $feedback['Name'] = 'fireTVcamera';
        $feedback['result'] = array();
        $cmd = 'nohup nice -n 10 '.getPath().'fireTVcamera.sh '.$params['commandvalue'];
        $outputfile=  tempnam( sys_get_temp_dir(), 'adb' );
        $pidfile=  tempnam( sys_get_temp_dir(), 'adb' );
        exec(sprintf("%s > %s 2>&1 & echo $! >> %s", $cmd, $outputfile, $pidfile));
        $feedback['message'] = "Initiated ".$feedback['Name'].' sequence'.'  Log:'.$outputfile;
        return $feedback;               // GET OUT

}

function storeCamImage($params) {

        $feedback['Name'] = 'storeCamImage';
        $feedback['result'] = array();

        echo "<pre>";
		print_r($params['cvs']);
        echo "</pre>";
		if (!copy($params['cvs'][0], $params['cvs'][1])) $feedback['error'] = "Error during copy";

        $feedback['message'] = "Copy ".$params['cvs'][0].' to '.$params['cvs'][1];
        return $feedback;               // GET OUT

}

function sendEmail(&$params) {

	$feedback['Name'] = 'sendmail';
	$feedback['result'] = array();
	$to = $params['device']['previous_properties']['Address']['value'];
	$fromname = 'VloHome'; 

	$headers = 'MIME-Version: 1.0' . "\r\n".
    'From: '.$fromname. "\r\n" .
    'Reply-To: '.'myvlohome@gmail.com'. "\r\n" .
    'X-Mailer: PHP/' . phpversion() . "\r\n" ;
	
	if(strlen($params['mess_text']) != strlen(strip_tags($params['mess_text']))) {
		$headers.= "Content-Type: text/html; \r\n"; 
	}
	
	$feedback['commandstr'] = 'mail('.$to.', '.$params['mess_subject'].',  '.$params['mess_text'].', '.$headers.')';
	if(!mail($to, $params['mess_subject'],  $params['mess_text'], $headers)) {
	    $feedback['error'] = "Mailer - error";
	    return $feedback;
	}
	else {
	    $feedback['message'] = 'Email to: '.$to.' Subj:'.$params['mess_subject'];
		return $feedback;
	}
}

function sendBullet(&$params) {

	$feedback['Name'] = 'sendBullet';
	$feedback['result'] = array();

	
	if(strlen($params['mess_text']) != strlen(strip_tags($params['mess_text']))) {

		// $str = 'My long <a href="http://example.com/abc" rel="link">string</a> has any
			// <a href="/local/path" title="with attributes">number</a> of
			// <a href="#anchor" data-attr="lots">links</a>.';

		$dom = new DomDocument();
		$dom->loadHTML($params['mess_text']);
		$output = array();
		foreach ($dom->getElementsByTagName('a') as $item) {
		   $output[] = array (
			  'str' => $dom->saveHTML($item),
			  'href' => $item->getAttribute('href'),
			  'anchorText' => $item->nodeValue
		   );
		}
		$text = strip_tags($params['mess_text']);
	}
	
	try {
		$pb = new Pushbullet\Pushbullet(PUSHBULLET_TOKEN);
		if (empty($output)) 
			$pb->channel(PUSH_CHANNEL)->pushNote($params['mess_subject'], $params['mess_text']);
		else {
			//echo "else\n";
			$pb->channel(PUSH_CHANNEL)->pushLink($params['mess_subject'], $output[0]['href'], $text);
		}
	} catch (Exception $e) {
		$feedback['error'] = 'Error: '.$e->getMessage();
	}
    return $feedback;

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

	$tcomm = replaceCommandPlaceholders($params['command'],$params);
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
	//$feedback['result'] = array();

	$func = $params['command'];
	if ($func == "sleep") {
		//$feedback['commandstr'] = $func.' '.$params['commandvalue'];
		$feedback['result'][] = $func($params['commandvalue']);
	} else {
		///$feedback['commandstr'] = $func.' '.json_encode($params,JSON_UNESCAPED_SLASHES);
		$feedback = $func($params);
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
	case "POSTURL":          // Web Arduino/ESP8266
	case "JSON":             // Wink
		if (DEBUG_DEVICES) echo $targettype."</p>";
		$tcomm = replaceCommandPlaceholders($params['command'],$params);
		$tmp1 = explode('?', $tcomm);
		if (array_key_exists('1', $tmp1)) { 	// found '?' inside command then take page from command string and add to url
			$params['device']['connection']['page'] .= $tmp1[0];
			array_shift($tmp1);
			$tcomm = implode('?',$tmp1);
			// echo "<pre>";
			// print_r($tmp1);
			// echo "******".$tcomm;
			// echo "</pre>";
		} 
		$url=setURL($params, $feedback['commandstr']);
		if (DEBUG_DEVICES) echo $url." Params: ".htmlentities($tcomm).CRLF;
		if ($targettype == "POSTTEXT") { 
			$feedback['commandstr'] .= ' '.htmlentities($tcomm);
			$curl = restClient::post($url, $tcomm, null, "text/plain", $params['device']['connection']['timeout']);
		} elseif ($targettype == "POSTAPP") {
			$feedback['commandstr'] .= ' '.$tcomm;
			$curl = restClient::post($url, $tcomm, null, "application/x-www-form-urlencoded", $params['device']['connection']['timeout']);
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
		$tcomm = replaceCommandPlaceholders($params['command'],$params);
		$url=setURL($params, $feedback['commandstr']);
		if (DEBUG_DEVICES) echo $url.$tcomm.CRLF;
		$feedback['commandstr'] .= implode('/', array_map('rawurlencode', explode('/', $tcomm)));;
		$curl = restClient::get($url.$tcomm, array(), null, $params['device']['connection']['timeout']);
		if ($curl->getresponsecode() != 200 && $curl->getresponsecode() != 204)
			$feedback['error'] = $curl->getresponsecode().": ".$curl->getresponse();
		else 
			$feedback['result'][] = $curl->getresponse();
		break;
	case "TCP":              // iTach (Only \r)
		if (DEBUG_DEVICES) echo "TCP_IR</p>";
		//print_r($params);
		$url = setURL($params, $feedback['commandstr']);
		$feedback['commandstr'] .= $params['command']."\r";
		if (empty($params['device']['connection']['targetaddress'])) {
			$ipaddress = $params['device']['ipaddress']['ip'];
		} else {
			$ipaddress = $params['device']['connection']['targetaddress'];
		}
		if (DEBUG_DEVICES) echo $ipaddress.':'.$params['device']['connection']['targetport'].' - '.$feedback['commandstr'].CRLF;
		// open a client connection
		$client = stream_socket_client('tcp://'.$ipaddress.':'.$params['device']['connection']['targetport'], $errno, $errorMessage, $params['device']['connection']['timeout']);
		if ($client === false) {
			echo $errno.' '.$errorMessage;
			$result['error'] = "Failed to connect: $errorMessage";
		} else {
			stream_set_timeout($client, $params['device']['connection']['timeout']);
			if ($targettype == "TCP") { 
				$binout = $feedback['commandstr'];
			} else {
				$binout = base64_decode($feedback['commandstr']);
			}
			fwrite($client, $binout);
			$feedback['result'][] = stream_get_line ( $client , 1024 , "\r" );	
			fclose($client);
			//echo "**>**".print_r($feedback['result'])."**<**";
		}
		// TODO:: Error handling (GCache errors)
		// completeir,1:1,2
		//if ($feedback['result'] != "ERR", or busy...
		break;
	case "TCP64":          // TP-Link
		if (DEBUG_DEVICES) echo "TP-Link</p>";
		if (empty($params['device']['connection']['targetaddress'])) {
			$ipaddress = $params['device']['ipaddress']['ip'];
		} else {
			$ipaddress = $params['device']['connection']['targetaddress'];
		}
		if (DEBUG_DEVICES) echo $ipaddress.':'.$params['device']['connection']['targetport'].' - '.$params['command'].CRLF;
		// open a client connection
//		$p="AAA0QcNAAEAGY0DAqAoawKgKVicP0VZB4a7Q3VhsD4ARC1AJYAAAAQEICgAP780AL6k=";
		//decodeTPLink(base64_decode($feedback['commandstr']));
		$feedback['commandstr'] = "tcp://".$ipaddress.":".$params['device']['connection']['targetport']."/".$params['command'];
		$feedback['result'][] = sendtoplug($ipaddress, $params['device']['connection']['targetport'], $params['command'], $params['device']['connection']['timeout']);
//		$feedback['result'][] = sendtoplug($ipaddress, $params['device']['connection']['targetport'], $p, $params['device']['connection']['timeout']);
//		print_r($feedback['result']);
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

function graphCreate($params) {
	if (DEBUG_GRAPH) echo "<pre>params: ";
	if (DEBUG_GRAPH) print_r($params);
	parse_str(urldecode($params['commandvalue']), $fparams);
	if (DEBUG_GRAPH) print_r($fparams);

	if (!array_key_exists('0', $fparams['fabrik___filter']['list_231_com_fabrik_231']['value'])) {
		$feedback['error']="No Device selected";
		return $feedback;
	}
	$devices = implode(",",$fparams['fabrik___filter']['list_231_com_fabrik_231']['value']['0']);
	foreach ($fparams['fabrik___filter']['list_231_com_fabrik_231']['value']['0'] as $deviceID) {
		$caption[] = FetchRow('select description from ha_mf_devices where id='.$deviceID)['description'];
	}
	if (!array_key_exists('1', $fparams['fabrik___filter']['list_231_com_fabrik_231']['value'])) {
		//$result['error']="No Device selected";
		//return $result;
		$result = listDeviceProperties($devices);
		$result = array_unique($result, SORT_NUMERIC);
		//if (DEBUG_GRAPH) print_r($result);
		$properties = implode(",", $result);
	} else {
		$properties = implode(",",$fparams['fabrik___filter']['list_231_com_fabrik_231']['value']['1']);
	}

	if (empty($fparams['fabrik___filter']['list_231_com_fabrik_231']['value']['2']['0'])) {
		$startdate = date( 'Y-m-d 00:00:00', strtotime("-1 days"));
		$enddate = date( 'Y-m-d 23:59:59', strtotime("tomorrow"));
	} else {
		$startdate = $fparams['fabrik___filter']['list_231_com_fabrik_231']['value']['2']['0'];
		$enddate = $fparams['fabrik___filter']['list_231_com_fabrik_231']['value']['2']['1'];
		$startdate = date( 'Y-m-d 00:00:00', strtotime($startdate));
		$enddate = date( 'Y-m-d 23:59:59', strtotime($enddate));
	}

	$debug = 1;
	if (DEBUG_GRAPH) {
		echo $devices.CRLF;
		echo $properties.CRLF;
		echo $startdate.CRLF; 
		echo $enddate.CRLF; 
	}

	//call sp_properties( '60,114,201', '138,126,123,127,124', "2015-09-25 00:00:00", "2015-09-27 23:59:59", 1) 
	$mysql='call sp_properties( "'.$devices.'", "'.$properties.'", "'.$startdate.'" , "'.$enddate.'",'.(DEBUG_GRAPH ? 1 : 0).');';
	if (DEBUG_GRAPH) echo $mysql.CRLF;
	$feedback['message'] = '';
	if ($rows = FetchRows($mysql)) {
		//print_r($rows);
		global $mysql_link;
		mysql_close($mysql_link);
		openMySql();


		$mysql='SELECT * FROM ha_mi_properties_graph WHERE propertyID IN ('.$properties.');';
		$rowsprops = FetchRows($mysql);

		$tablename="graph_0";
		$hidden = (DEBUG_GRAPH ? '' : ' hidden ');

		if (count($rows)> MAX_DATAPOINTS) {
			$rows = array_slice($rows, -MAX_DATAPOINTS, count($rows) ); 
			$s = $rows[0]['Date'];
			$e = $rows[count($rows)-1]['Date'];
			$feedback['message'] .= '<p class="badge badge-info">Too much data; Showing: '.count($rows).' from '.$s.' through '.$e.'<p>';
		}
		$s = $rows[0]['Date'];
		$e = $rows[count($rows)-1]['Date'];
		//	echo (int)(abs(strtotime($e)-strtotime($s))/300); //60*5 min intervals = 300?
		$tickinterval=(abs(strtotime($e)-strtotime($s)))*100; 
		//var_dump ($tickinterval);
		$tickinterval=roundUpToAny(abs(strtotime($e)-strtotime($s))*50,3600000);
		//var_dump($tickinterval);
		//
		//echo $tickinterval.CRLF;
		//echo count($rows).CRLF;
		$feedback['message'] .= '<table id="'.$tablename.'" class="'.$tablename.$hidden.' table table-striped table-hover" data-graph-xaxis-type="datetime" data-graph-yaxis-2-opposite="1" 
				data-graph-xaxis-tick-interval="'.$tickinterval.'" data-graph-xaxis-align="right" data-graph-xaxis-rotation="270" data-graph-type="spline" 
				data-graph-container-before="1" data-graph-zoom-type="x" data-graph-height="500" >';
		//data-graph-xaxis-type="datetime"

		if (DEBUG_GRAPH) {echo "RowsProps:"; print_r($rowsprops);}

		if (DEBUG_GRAPH) echo "</pre>";
		$feedback['message'] .= '<caption>'.implode(", ",$caption).'</caption>';
		$feedback['message'] .= '<thead><tr class="fabrik___heading">';
		foreach($rows[0] as $header=>$value){
			if ($header != "id") {
				$datastr="";
				if ($header != "Date") {
					//print_r($rows[0]);
					$t = explode('`',$header);
					$propID = getProperty($t[0])['id'];
					if (($prodIdx = findByKeyValue($rowsprops,'propertyID',$propID)) !== false) {
						//echo "Found: ".$rowsprops[$prodIdx]['description'].CRLF;
						if (!empty($rowsprops[$prodIdx]['color'])) {
							if (strpos($rowsprops[$prodIdx]['color'],",")>0) { 	// First time  read RGB from DB
								$rowsprops[$prodIdx]['color'] = rgb2hex(explode(",",$rowsprops[$prodIdx]['color']));
							}
							$rowsprops[$prodIdx]['color'] = colorDuplicateProperty($rows[0], $header, $rowsprops[$prodIdx]['color']);
							$datastr.='data-graph-color="'.$rowsprops[$prodIdx]['color'].'" ';
						}
						if (!empty(trim($rowsprops[$prodIdx]['dash_style']))) $datastr.='data-graph-dash-style="'.$rowsprops[$prodIdx]['dash_style'].'" ';
						if ($rowsprops[$prodIdx]['hidden']==1) $datastr.='data-graph-hidden="'.$rowsprops[$prodIdx]['hidden'].'" ';
						if ($rowsprops[$prodIdx]['skip']) $datastr.='data-graph-skip="'.$rowsprops[$prodIdx]['skip'].'" ';
						if ($rowsprops[$prodIdx]['stack_group']) $datastr.='data-graph-stack-group="'.$rowsprops[$prodIdx]['stack_group'].'" ';
						$datastr.='data-graph-yaxis="'.$rowsprops[$prodIdx]['yaxis'].'" ';
						if ($rowsprops[$prodIdx]['type']) $datastr.='data-graph-type="'.$rowsprops[$prodIdx]['type'].'" ';
						if ($rowsprops[$prodIdx]['value_scale']) $datastr.='data-graph-value-scale="'.$rowsprops[$prodIdx]['value_scale'].'" ';
						if ($rowsprops[$prodIdx]['datalabels_enabled']) $datastr.='data-graph-datalabels-enabled="'.$rowsprops[$prodIdx]['datalabels_enabled'].'" ';
						if ($rowsprops[$prodIdx]['datalabels_color']) $datastr.='data-graph-datalabels-color="'.$rowsprops[$prodIdx]['datalabels_color'].'" ';
						if ($rowsprops[$prodIdx]['markers_enabled']) $datastr.='data-graph-markers-enabled="'.$rowsprops[$prodIdx]['markers_enabled'].'" ';
					} else {
						$feedback['message'] .= "Property $header not found!!!: $prodIdx".CRLF;
					}
				}
				$feedback['message'] .= '<th id="'.$tablename.'_'.$header.'_header" '.$datastr;
				$feedback['message'] .= '>' . str_replace('`',' ',$header). '</th>';
			}
//			if ($value == " ") {
//				$rows[0][$header]=" ";
//			}
		}
		$feedback['message'] .= '</tr></thead>';
		$feedback['message'] .= '<tbody>';
		$x=0;
		foreach($rows as $key=>$row) {
			$feedback['message'] .= '<tr id="'.$tablename.'row_'.$row['id'].'">';
			foreach($row as $key2=>$value2){
				if ($key2 != "id") {
					if ($value2 != " ") {
						if ($key2 != "Date") {
							$feedback['message'] .= '<td id="'.$tablename.'_'.$key2.'_'.$row['id'].'" data-graph-x="'.$x.'">' . $value2 . '</td>';
						} else {
							$feedback['message'] .= '<td id="'.$tablename.'_'.$key2.'_'.$row['id'].'">' . $value2 . '</td>';
						}
					} else {
						$feedback['message'] .= '<td id="'.$tablename.'_'.$key2.'_'.'Null'.'">' . $value2 . '</td>';
					}
				}
			}
			$feedback['message'] .= '</tr>';
			$x++;
		}
		$feedback['message'] .= '</tbody>';
		$feedback['message'] .= '</table>';
	}
//	$feedback['message'] = "";
	return $feedback;

}

function sendEchoBridge($params) {


	// if (!array_key_exists('timer',$params))	{ // Called directly from executeCommands
		// $timer['runasync'] = false;
		// $timer['priorityID'] = 1;
		// $timer['description'] = "";
		// $timerID = $params['commandvalue'];
	// } else {
		// if (DEBUG_TIMERS) echo "<pre>".CRLF;
		// if (DEBUG_TIMERS) print_r($params);
		// $timer = $params['timer'];
		// $timerID = $params['timer']['id'];
	// }

	// echo "<pre>";
	// print_r($params);

	$vcIDs = explode(",", $params['commandvalue']);

	foreach ($vcIDs as $vcID) {
		// echo "vcID ".$vcID.CRLF;
		$mysql = 'SELECT d.id, description, bridge_id, url_type, url FROM `ha_voice_devices` d LEFT JOIN  ha_voice_devices_urls u ON d.id = u.vdeviceID 
			WHERE(((d.id) = '.$vcID.') AND active = 1) ORDER BY u.url_type';


		$vlosite = "http://192.168.2.101/process.php?";
		if ($in_commands = FetchRows($mysql)) {
		// print_r($in_commands);
			unset($send_params);
			$send_params['id'] = (empty($in_commands[0]['bridge_id']) ? null : $in_commands[0]['bridge_id']);
			$send_params['name'] = $in_commands[0]['description'];
			$send_params['deviceType'] = "TCP";
			$send_params['targetDevice'] = "Encapsulated";
			foreach ($in_commands as $in_command) {
				if ($in_command['url_type'] == 1) {		// On
					$send_params['onUrl'] = $vlosite.$in_command['url'];
				} elseif ($in_command['url_type'] == 2) {		// Dim
					$send_params['dimUrl'] = $vlosite.$in_command['url'];
				} elseif ($in_command['url_type'] == 3) {		// Off
					$send_params['offUrl'] = $vlosite.$in_command['url'];
				}
			}
		}
	// print_r($send_params);

/*
Add Device - Post
{"id":null,"name":"Kitchen Recess1","deviceType":"TCP","targetDevice":"Encapsulated","offUrl":"http:
//192.168.2.101/process.php?callerID=264&deviceID=9&messagetypeID=MESS_TYPE_COMMAND&commandID=20","dimUrl"
:"http://192.168.2.101/process.php?callerID=264&deviceID=9&messagetypeID=MESS_TYPE_COMMAND&commandID
=145&commandvalue=${intensity.percent}","onUrl":"http://192.168.2.101/process.php?callerID=264&deviceID
=9&messagetypeID=MESS_TYPE_COMMAND&commandID=17"}


Response
[{"id":"78892528","name":"Kitchen Recess3","deviceType":"TCP","targetDevice":"Encapsulated","offUrl"
:"http://192.168.2.101/process.php?callerID\u003d264\u0026deviceID\u003d9\u0026messagetypeID\u003dMESS_TYPE_COMMAND
\u0026commandID\u003d20","dimUrl":"http://192.168.2.101/process.php?callerID\u003d264\u0026deviceID\u003d9
\u0026messagetypeID\u003dMESS_TYPE_COMMAND\u0026commandID\u003d145\u0026commandvalue\u003d${intensity
.percent}","onUrl":"http://192.168.2.101/process.php?callerID\u003d264\u0026deviceID\u003d9\u0026messagetypeID
\u003dMESS_TYPE_COMMAND\u0026commandID\u003d17"}]

Update - Put
{"id":"910143788","name":"Kitchen Recess","deviceType":"TCP","targetDevice":"Encapsulated","offUrl":"http
://192.168.2.101/process.php?callerID=264&deviceID=9&messagetypeID=MESS_TYPE_COMMAND&commandID=20","dimUrl"
:"http://192.168.2.101/process.php?callerID=264&deviceID=9&messagetypeID=MESS_TYPE_COMMAND&commandID
=145&commandvalue=${intensity.percent}","onUrl":"http://192.168.2.101/process.php?callerID=264&deviceID
=9&messagetypeID=MESS_TYPE_COMMAND&commandID=17"}
*/

		$tcomm = replaceCommandPlaceholders($params['command'],$params);
		$tmp1 = explode('?', $tcomm);
		if (array_key_exists('1', $tmp1)) { 	// found '?' inside command then take page from command string and add to url
			$params['device']['connection']['page'] .= $tmp1[0];
			$tcomm = $tmp1[1];
		} 
		$url=setURL($params, $feedback['commandstr']);

		$postparams = json_encode($send_params,JSON_UNESCAPED_SLASHES);
		$feedback['commandstr'] .= ' '.$postparams;


		if (!empty($send_params['id'])) {		// Update - PUT
			$url .= '/'.$send_params['id'];
			if (DEBUG_DEVICES) echo $url." PUT-Params: ".htmlentities($postparams).CRLF;
			$curl = restClient::put($url, $postparams, null, "application/json" , $params['device']['connection']['timeout']);
			if ($curl->getresponsecode() == 200 || $curl->getresponsecode() == 201) {
				$result = $curl->getresponse();
				$feedback['result'][] = $result;
			} elseif ($curl->getresponsecode() == 400) { // Try Adding device does not exist (400: {"message":"Could not save an edited device, Device Id not found: 402389166 "} 
				$send_params['id'] = "";
				$postparams = json_encode($send_params,JSON_UNESCAPED_SLASHES);
			} else {
				$feedback['error'] = $curl->getresponsecode().": ".$curl->getresponse();
			}
		} 
		if (empty($send_params['id'])) {				// Add - Post
			if (DEBUG_DEVICES) echo $url." POST-Params: ".htmlentities($postparams).CRLF;
			$curl = restClient::post($url, $postparams, null, "application/json" , $params['device']['connection']['timeout']);
			if ($curl->getresponsecode() != 200 && $curl->getresponsecode() != 201) {
				$feedback['error'] = $curl->getresponsecode().": ".$curl->getresponse();
			} else {
				$result = $curl->getresponse();
				$feedback['result'][] = $result;
				$result = json_decode($result,true);
				// print_r($result);
				PDOupdate("ha_voice_devices", array('bridge_id' => $result[0]['id']), array( 'id' => $vcID));
			}
		}
		// echo "</pre>";
	}

	return $feedback;
}

function sendtoplug ($ip, $port, $payload, $timeout) {
	$client = stream_socket_client('tcp://'.$ip.':'.$port, $errno, $errorMessage, $timeout);
	if ($client === false) {
		return "Failed to connect: $errno $errorMessage";
	} else {
		stream_set_timeout($client, $timeout);
		fwrite($client, base64_decode($payload));
//		return base64_encode(stream_get_line ( $client , 1024 ));	
		return decodeTPLink(stream_get_line ( $client , 1024 ));	
		fclose($client);
	}
}

function decodeTPLink($raw) {
/*int main(int argc, char *argv[])
{
  int c=getchar();
  int skip=4;
  int code=0xAB;
  while(c!=EOF)
  {
    if (skip>0)
    {
      skip--;
      c=getchar();
    }
    else
    {
     putchar(c^code);
     code=c;
     c=getchar();
    }
 }
 printf("\n");
}*/
//echo "<pre>";
//echo "instring:".base64_encode($raw).CRLF;
$code = chr(0xAB);
$decoded = "";
$c = substr( $raw, 4, 1 );
for( $i = 5; $i <= strlen($raw); $i++ ) {
    $decoded.=$c^$code;
    $code = $c;
    $c = substr( $raw, $i, 1 );
}
//echo "decoded:".$decoded.CRLF;
//print_r(json_decode($decoded));
//echo "</pre>";
return json_decode($decoded,TRUE);
}
?>
