<?php 
require_once 'includes.php';

// TODO:: callerparms needed?
// TODO:: clean up feedback , status and return JSON

//define( 'DEBUG_FLOW', TRUE );
//define( 'DEBUG_DEVICES', TRUE );
//define( 'DEBUG_RETURN', TRUE );
if (!defined('DEBUG_FLOW')) define( 'DEBUG_FLOW', FALSE );
if (!defined('DEBUG_DEVICES')) define( 'DEBUG_DEVICES', FALSE );
if (!defined('DEBUG_RETURN')) define( 'DEBUG_RETURN', FALSE );

if (isset($argv)) {
	var_dump($argv);
	foreach ($argv as $arg) {
		$e=explode("=",$arg);
        if(count($e)==2) {
			$_POST[$e[0]]=$e[1];
		} else {
			if ($e[0] == "ASYNC_THREAD") {
				define( 'ASYNC_THREAD', TRUE );
				echo "ASYNC_THREAD".CRLF;
			}
		}
	}
}

if (!defined('ASYNC_THREAD')) define( 'ASYNC_THREAD', false);

if (isset($_GET['callerID'])) {
	// Loading JSON get variables form cam-5 in Post
	//$sdata=json_decode($_GET['Message'], $assoc = TRUE); 
	$_POST=$_GET;
}

if (DEBUG_FLOW) echo json_encode($_POST);
if (DEBUG_FLOW) echo (array_key_exists('CONTENT_TYPE', $_SERVER) ? json_encode($_SERVER["CONTENT_TYPE"]) : "");

if (isset($_POST["messagetypeID"]) && isset($_POST["callerID"])) {						// All have to tell where they are from.

	if (DEBUG_FLOW) echo "callerID ".$_POST['callerID']." ".$_POST['messagetypeID'].CRLF;
	echo executeCommand($_POST);
}


// Public (Timers, Triggers, cameras)
function executeCommand($callerparams) {
// New entry point for execute chain, from external i.e. remote
// This will store and keep original caller params

	/* Get the Keys Schema or Device */
	//$callerparams['deviceID'] = (array_key_exists('deviceID', $callerparams) ? $callerparams['deviceID'] : Null);
	//if (IsNullOrEmptyString($callerparams['deviceID'])) $callerparams['deviceID'] = Null;
	$callerparams['schemeID'] = (array_key_exists('schemeID', $callerparams) ? $callerparams['schemeID'] : Null);
	$callerparams['remotekeyID'] = (array_key_exists('remotekeyID', $callerparams) ? $callerparams['remotekeyID'] : Null);
	$callerparams['commandID'] = (array_key_exists('commandID', $callerparams) ? $callerparams['commandID'] : Null);
	$callerparams['commandvalue'] = (array_key_exists('commandvalue', $callerparams) ? $callerparams['commandvalue'] : Null);
	$callerparams['selection'] = (array_key_exists('selection', $callerparams) ? $callerparams['selection'] : Null);
	$callerparams['mouse'] = (array_key_exists('mouse', $callerparams) ? $callerparams['mouse'] : Null);
	
	if ($callerparams['callerID'] == DEVICE_REMOTE) header('Content-type: application/json'); 

	global $inst_coder;

	$inst_coder = new InsteonCoder();
	$feedback['messagetypeID'] = $callerparams['messagetypeID'];

	if (DEBUG_FLOW) echo '<pre>Entry executeCommand - Callerparams: ';
	if (DEBUG_FLOW) echo print_r($callerparams);
			
	$feedback['show_result'] = false;
	switch ($callerparams['messagetypeID'])
	{
	case MESS_TYPE_REMOTE_KEY:    // Key pressed on remote
		foreach ($callerparams['keys'] AS $remotekeyID) {
			unset($callerparams['keys']);
			$rowkeys = FetchRow("SELECT * FROM ha_remote_keys where id =".$remotekeyID);
			$callerparams['schemeID'] = $rowkeys['schemeID'];
			$feedback['show_result'] = $rowkeys['show_result'];
			$callerparams['deviceID'] = $rowkeys['deviceID'];
		
			if ($callerparams['schemeID'] <=0) {  													// not a scheme, Execute
				if ($callerparams['commandID']===NULL) {
					if ($callerparams['mouse']=='down') { 
						$callerparams['commandID']=$rowkeys['commandIDdown'];
						if (is_null($callerparams['commandID'])) {
							return false;
						}
					} else {
						$callerparams['commandID']=$rowkeys['commandID'];
					}
				}
			} else {
				$callerparams['commandID'] = COMMAND_RUN_SCHEME;
			}
			if (!array_key_exists('caller', $callerparams)) $callerparams['caller'] = $callerparams;
			$feedback['SendCommand'][]=SendCommand($callerparams);
		}
		break;
	case MESS_TYPE_SCHEME:
		if (DEBUG_FLOW) echo "MESS_TYPE_SCHEME scheme: ".$callerparams['schemeID'].CRLF;
		$callerparams['commandID'] = COMMAND_RUN_SCHEME;
		$callerparams['caller'] = $callerparams;
		$feedback['SendCommand'][]=SendCommand($callerparams);
		break;
	case MESS_TYPE_COMMAND:
		// Comes either with deviceID or keys
		if (array_key_exists('keys', $callerparams)) {
			foreach ($callerparams['keys'] AS $remotekeyID) {
				unset($callerparams['keys']);
				$rowkeys = FetchRow("SELECT * FROM ha_remote_keys where id =".$remotekeyID);
				$callerparams['deviceID'] = $rowkeys['deviceID'];
				if ($callerparams['commandID'] == COMMAND_GET_VALUE) {
					$feedback[]['updatestatus']=GetStatusLink($callerparams);
				} else {
					if (!array_key_exists('caller', $callerparams)) $callerparams['caller'] = $callerparams;
					$feedback['SendCommand'][]=SendCommand($callerparams);
				}
			}
		} else {			
			if (DEBUG_FLOW) echo "MESS_TYPE_COMMAND commandID: ".$callerparams['commandID'].CRLF;
			if (DEBUG_FLOW && isset($deviceID)) echo "deviceID: ".$callerparams['deviceID'].CRLF;
			$callerparams['caller'] = $callerparams;
			$feedback['SendCommand'][]=SendCommand($callerparams);
		}
		break;
	}

	if (DEBUG_RETURN) echo "<pre>Feedback: >";
	if (DEBUG_RETURN) print_r($feedback);
	if (DEBUG_RETURN) echo "executeCommand Exit".CRLF;

	if ($feedback['show_result']) {
		$filterkeep = array( 'status' => 1, 'commandvalue' => 1, 'deviceID' => 1, 'message' => 1, 'link' => 1, 'error' => 1);
		doFilter($feedback, array( 'updatestatus' => 1,  'groupselect' => 1, 'message' => 1), $filterkeep, $result);
	} else {
		$filterkeep = array( 'status' => 1, 'commandvalue' => 1, 'deviceID' => 1, 'link' => 1, 'error' => 1);
		doFilter($feedback, array( 'updatestatus' => 1,  'groupselect' => 1), $filterkeep, $result);
	}
	if (DEBUG_RETURN) echo "Filtered: >";
	if (DEBUG_RETURN) print_r($result);
	if ($callerparams['callerID'] == DEVICE_REMOTE) {
		if ($result != null) {
			$result = RemoteKeys($result);
		} else { 
			$result['message'] = '';
		}
	}
	
	return 	json_encode($result);
			
}

// Private
function SendCommand($thiscommand) { 

	$callerparams = (array_key_exists('caller', $thiscommand) ? $thiscommand['caller'] : Array());
	$thiscommand['loglevel'] = (array_key_exists('loglevel', $thiscommand['caller']) ? $thiscommand['caller']['loglevel'] : Null);
	$thiscommand['deviceID'] = (array_key_exists('deviceID', $thiscommand) ? $thiscommand['deviceID'] : Null);
	if (IsNullOrEmptyString($thiscommand['deviceID'])) $thiscommand['deviceID'] = Null;
	$thiscommand['commandID'] = (array_key_exists('commandID', $thiscommand) ? $thiscommand['commandID'] : Null);
	$thiscommand['commandvalue'] = (array_key_exists('commandvalue', $thiscommand) ? $thiscommand['commandvalue'] : 100);
	$thiscommand['timervalue'] = (array_key_exists('timervalue', $thiscommand) ? $thiscommand['timervalue'] : 0);
	$thiscommand['schemeID'] = (array_key_exists('schemeID', $thiscommand) ? $thiscommand['schemeID'] : Null);
	$thiscommand['alert_textID'] = (array_key_exists('alert_textID', $thiscommand) ? $thiscommand['alert_textID'] : Null);
	$feedback = Array();
	
	if ($thiscommand['commandID'] == null) {
		$feedback['error'] = "No Command given";
		return $feedback;			// error abort
	}

	if (DEBUG_FLOW || DEBUG_DEVICES) {
		echo "<pre>Enter SendCommand ".CRLF;
		echo "This Command: ";
		if ($ct = FetchRow("SELECT description FROM ha_mf_commands  WHERE ha_mf_commands.id =".$thiscommand['commandID']))  {
			echo $ct['description'].CRLF;			// error abort
		} 
		print_r($thiscommand);
	}
	
//
//   Sends 1 single command to TCP, REST, EMAIL
//	
	global $inst_coder;
	if ($inst_coder instanceof InsteonCoder) {
	} else {
		$inst_coder = new InsteonCoder();
	}

	$targettype = Null;
	if ($thiscommand['deviceID'] != NULL) {
		$resdevices = mysql_query("SELECT * FROM ha_mf_devices where id =".$thiscommand['deviceID'].' AND inuse= 1');
		if (!$rowdevices = mysql_fetch_array($resdevices)) return;
		if ($resdevicelinks = mysql_query("SELECT * FROM ha_mf_device_links where id =".$rowdevices['devicelinkID'])) {
			($rowdevicelinks = mysql_fetch_array($resdevicelinks));
			if ($rowdevicelinks) {
				$targettype = $rowdevicelinks['targettype'];
			}
		}
		$commandclassID = $rowdevices['commandclassID'];
		if (DEBUG_DEVICES) echo "targettype ".$targettype.CRLF;

		$resmonitor = mysql_query("SELECT status, invertstatus FROM ha_mf_monitor_status WHERE ha_mf_monitor_status.deviceID =".$thiscommand['deviceID']);
		$rowmonitor = mysql_fetch_array($resmonitor);

		// Special handling for toggle
		if ($thiscommand['commandID']==COMMAND_TOGGLE) {   
			if ($thiscommand['commandvalue'] > 0 && $thiscommand['commandvalue'] < 100) { // if dimvalue given then update dim, else toggle
				$thiscommand['commandID'] = COMMAND_ON;						
			} else {
				if ($rowmonitor) {
					if (DEBUG_DEVICES) echo "Status Toggle: ".$rowmonitor['status'].CRLF;
					$thiscommand['commandID'] = ($rowmonitor['status'] == STATUS_ON ? COMMAND_OFF : COMMAND_ON); // toggle on/off
				} else {		// not status monitoring 
					if (DEBUG_DEVICES) echo "NO STATUS RECORD FOUND, GETTING OUT".CRLF;
					return;
				}
			}
		}
		
		// Invert Status is set
		if ($rowmonitor && !$rowmonitor['invertstatus']) {  
			if (DEBUG_DEVICES) echo "Status Invert: ".$rowmonitor['status'].CRLF;
			if ($thiscommand['commandID'] == COMMAND_OFF) {
				$thiscommand['commandID'] = COMMAND_ON;
			} elseif ($thiscommand['commandID'] == COMMAND_ON) {
				$thiscommand['commandID'] = COMMAND_OFF;
			}
		}

	} else {
		$commandclassID = COMMAND_CLASS_GENERIC;
	}
	

	$mysql = "SELECT * FROM ha_mf_commands JOIN ha_mf_commands_detail ON ".
			" ha_mf_commands.id=ha_mf_commands_detail.commandID" .
			" WHERE ha_mf_commands.id =".$thiscommand['commandID']. " AND commandclassID = ".$commandclassID." AND `inout` IN (".COMMAND_IO_SEND.','.COMMAND_IO_BOTH.')';
	if (!$rowcommands = FetchRow($mysql))  {			// No device specific command found, try generic, else exit
		$commandclassID = COMMAND_CLASS_GENERIC;
		$mysql = "SELECT * FROM ha_mf_commands JOIN ha_mf_commands_detail ON ".
				" ha_mf_commands.id=ha_mf_commands_detail.commandID" .
				" WHERE ha_mf_commands.id =".$thiscommand['commandID']. " AND commandclassID = ".$commandclassID." AND `inout` IN (".COMMAND_IO_SEND.','.COMMAND_IO_BOTH.')';
		if (!$rowcommands = FetchRow($mysql))  {
			$feedback['error'] = "No outgoing Command found";
			return $feedback;			// error abort
		}
	}

	if ($targettype == 'NONE') $commandclassID = COMMAND_CLASS_GENERIC; // Treat command for devices with no outgoing as virtual, i.e. set day/night to on/off
	if (DEBUG_FLOW || DEBUG_DEVICES) echo "commandID ".$thiscommand['commandID'].CRLF;
	if (DEBUG_FLOW || DEBUG_DEVICES) echo "commandclassID ".$commandclassID.CRLF;
	if (DEBUG_FLOW || DEBUG_DEVICES) echo "commandvalue ".$thiscommand['commandvalue'].CRLF;
	if (DEBUG_FLOW || DEBUG_DEVICES) echo "command ". $rowcommands['command'].CRLF;
	//if (DEBUG_DEVICES) echo " command commandvalue ". $rowcommands['commandvalue'].CRLF;

	switch ($commandclassID)
	{
	case COMMAND_CLASS_3MFILTRETE:          
		if (DEBUG_DEVICES) echo "COMMAND_CLASS_3MFILTRETE</p>";
		$func = $rowcommands['command'];
		$feedback['updatestatus'] = $func($callerparams['callerID'], $thiscommand['deviceID'], $thiscommand['commandvalue']);
		break;
	case COMMAND_CLASS_EMAIL:
		if (DEBUG_DEVICES) echo "COMMAND_CLASS_EMAIL".CRLF;
		$rowtext = FetchRow("SELECT * FROM ha_alert_text where id =".$thiscommand['alert_textID']);
		$subject= $rowtext['description'];
		$message= $rowtext['message'];
		if (strlen($message) == 0) $message = " ";
		// echo "thiscommand".CRLF.CRLF;
		// print_r($thiscommand);
		replaceText($subject, $message, $thiscommand);
		//echo $message.CRLF.CRLF;
		if (!($error = sendmail($rowcommands['command'], $subject, $message, 'VloHome'))) $feedback['error'] = $error;
		break;
	case COMMAND_CLASS_INSTEON:
		if (DEBUG_DEVICES) echo "COMMAND_CLASS_INSTEON".CRLF;
		$tcomm = str_replace("{mycommandID}",$thiscommand['commandID'],$rowcommands['command']);
		$tcomm = str_replace("{deviceID}",$thiscommand['deviceID'],$tcomm);
		$tcomm = str_replace("{unit}",$rowdevices['unit'],$tcomm);
		$rowextra = FetchRow('SELECT * FROM `ha_mf_device_extra` WHERE deviceID = '. $thiscommand['deviceID']);
		if (!$rowextra['dimmable']) {
			$thiscommand['commandvalue'] = 100;
		}
		if (DEBUG_DEVICES) echo "commandvalue a".$thiscommand['commandvalue'].CRLF;
		if ($thiscommand['commandvalue']>100) $thiscommand['commandvalue']=100;
		if (DEBUG_DEVICES) echo "commandvalue b".$thiscommand['commandvalue'].CRLF;
		if (is_null($thiscommand['commandvalue']) && $thiscommand['commandID'] == COMMAND_ON) $thiscommand['commandvalue']= $rowextra['onlevel'];
		if (DEBUG_DEVICES) echo "commandvalue c".$thiscommand['commandvalue'].CRLF;
		if ($thiscommand['commandvalue']>0) $thiscommand['commandvalue']=255/100*$thiscommand['commandvalue'];
		if (DEBUG_DEVICES) echo "commandvalue d".$thiscommand['commandvalue'].CRLF;
		if ($thiscommand['commandvalue'] == NULL && $thiscommand['commandID'] == COMMAND_ON) $thiscommand['commandvalue']=255;		// Special case so satify the replace in on command
		$commandvalue = dec2hex($thiscommand['commandvalue'],2);
		if (DEBUG_DEVICES) echo "commandvalue ".$commandvalue.CRLF;
		$tcomm = str_replace("{commandvalue}",$commandvalue,$tcomm);
		if (DEBUG_DEVICES) echo "Rest deviceID ".$thiscommand['deviceID']." commandID ".$thiscommand['commandID'].CRLF;
		$url=$rowdevicelinks['targetaddress'].":".$rowdevicelinks['targetport'].$rowdevicelinks['page'].$tcomm.'=I=3';
		if (DEBUG_DEVICES) echo $url.CRLF;
		$get = restClient::get($url);
		if ($get->getresponsecode() != 200 && $get->getresponsecode() != 204) 
			$feedback['error'] = $get->getresponsecode().": ".$get->getresponse();
		else 
			$feedback['message'] = $get->getresponse();
		usleep(INSTEON_SLEEP_MICRO);
		if (!array_key_exists('error', $feedback)) {
			$result[] = ($thiscommand['commandID'] == COMMAND_OFF ? STATUS_OFF : STATUS_ON);
			$result[] = $thiscommand['commandvalue'];
			$feedback['updatestatus'] = UpdateStatus($thiscommand); // Need caller params here for triggers
		}
		break;
	case COMMAND_CLASS_X10_INSTEON:
		$tcomm = str_replace("{mycommandID}",$thiscommand['commandID'],$rowcommands['command']);
		$rowextra = FetchRow('SELECT * FROM `ha_mf_device_extra` WHERE deviceID = '. $thiscommand['deviceID']);
		if ($rowextra['dimmable']) {
			$dims = 0;
			if ($thiscommand['commandvalue']>0 && $thiscommand['commandvalue'] < 100) $dims=(integer)round(10-10/100*$thiscommand['commandvalue']);
			if (DEBUG_DEVICES) echo "commandvalue ".$thiscommand['commandvalue'].CRLF;
			if (DEBUG_DEVICES) echo "dims ".$dims.CRLF;
			while($dims > 0) {
				$tcomm .= COMMAND_DIM_CLASS_X10_INSTEON_DIMM;
				$dims--;
			}
			$tcomm = COMMAND_DIM_CLASS_X10_INSTEON_OFF.$tcomm; 	// Add off in front
		} else {
			$thiscommand['commandvalue'] = 100;
		}
		if ($thiscommand['commandvalue']>100) $thiscommand['commandvalue']=100;
		if ($thiscommand['commandvalue']!=100 && $thiscommand['commandID'] == COMMAND_ON) $thiscommand['commandvalue']= $rowextra['onlevel'];
		if ($thiscommand['commandvalue'] == NULL && $thiscommand['commandID'] == COMMAND_ON) $thiscommand['commandvalue']=100;		// Special case so satify the replace in on command
//		$tcomm .={code}a80=I=3;	$tcomm .={code}b80=I=3 $tcomm .= "|{code}{unit}00=I=3"; $tcomm .= "|{code}a80=I=3";	$tcomm .= "|0b80=I=3";
//		$tcomm .= "|{code}480=I=3";			// dim 480  $tcomm .= "|a780=I=3";	$tcomm .= "|0b80=I=3";
		$tcomm = str_replace("{code}",$inst_coder->x10_code_encode($rowdevices['code']),$tcomm);
		$tcomm = str_replace("{unit}",$inst_coder->x10_unit_encode($rowdevices['unit']),$tcomm);
		if (DEBUG_DEVICES) echo "Rest deviceID ".$thiscommand['deviceID']." commandID ".$thiscommand['commandID'].CRLF;
		$commands=explode("|", $tcomm);
		//
		// handle dimming, cannot give commandvalue so dimming lots of times
		//
		foreach ($commands as $command) {
			$url=$rowdevicelinks['targetaddress'].":".$rowdevicelinks['targetport'].$rowdevicelinks['page'].$command.'=I=3';
			if (DEBUG_DEVICES) echo $url.CRLF;
			$get = restClient::get($url);
			if ($get->getresponsecode() != 200 && $get->getresponsecode() != 204) 
				$feedback['error'] = $get->getresponsecode().": ".$get->getresponse();
			else 
				$feedback['message'] = $get->getresponse();
			usleep(INSTEON_SLEEP_MICRO);
		}     
		if (!array_key_exists('error', $feedback)){
			$result[] = ($thiscommand['commandID'] == COMMAND_OFF ? STATUS_OFF : STATUS_ON);
			$result[] = $thiscommand['commandvalue'];
			$feedback['updatestatus'] = UpdateStatus($thiscommand); // need callerparms here for triggers
		}
		break;
	case COMMAND_CLASS_X10:				// Obsolete TCP bridge gone, might use later for comm between VMs
		$xmlfile="X10Command.xml";
		$x10 = simplexml_load_file($xmlfile);
		OpenTCP($rowdevicelinks['targetaddress'], $rowdevicelinks['targetport'],"X10");
		$x10[0]->CallerID = "web";
		$x10[0]->Operation = "send";
		$x10[0]->Sender = "plc";
		$x10[0]->HouseCode = $rowdevices['code'];
		$x10[0]->Unit = $rowdevices['unit'];
		if ($thiscommand['commandID'] ==  COMMAND_ON && $thiscommand['commandvalue']>0 && $thiscommand['commandvalue']<100) {
			$x10[0]->Command = "On";
			$x10[0]->CmdData = NULL;
			$x10[0]->Date = date("Y-m-d H:i:s");
			WriteTCP($x10[0]->asXML());
			$x10[0]->Command = "Bright";
			$x10[0]->CmdData = 100;
			$x10[0]->Date = date("Y-m-d H:i:s");
			WriteTCP($x10[0]->asXML());
			$x10[0]->Command = "Dim";
			$x10[0]->CmdData = 100-$thiscommand['commandvalue'];
			$x10[0]->Date = date("Y-m-d H:i:s");
			WriteTCP($x10[0]->asXML());
		} else {
			$x10[0]->Command = $rowcommands['description'];
			$x10[0]->CmdData = $thiscommand['commandvalue'];
			$x10[0]->Date = date("Y-m-d H:i:s");
			WriteTCP($x10[0]->asXML());
		}
		CloseTCP("X10");
		$result[] = (commandID == COMMAND_OFF ? STATUS_OFF : STATUS_ON);
		$result[] = $thiscommand['commandvalue'];
		break;
	case COMMAND_CLASS_GENERIC:								// No device 
		if (DEBUG_DEVICES) echo "COMMAND_CLASS_GENERIC</p>";
		switch ($thiscommand['commandID'])
		{
		case COMMAND_RUN_SCHEME:
			$feedback['runscheme'] = RunScheme($thiscommand);
			$thiscommand['commandvalue'] = (array_key_exists('RunSchemeName', $feedback['runscheme']) ? $feedback['runscheme']['RunSchemeName'] : "Not Executed");
			break;
		case COMMAND_LOG_ALERT:
			$feedback['message'] = Alerts($thiscommand['alert_textID'], $thiscommand).' created';
			break;
		case COMMAND_GET_GROUP:
			$func = $rowcommands['command'];
			$groups = $func($thiscommand['commandvalue']);
			$feedback = array();
			foreach($groups as $device) {
				$feedback[]['groupselect']['deviceID'] = $device['deviceID'];
			}
			break;
		case COMMAND_SET_RESULT:
			$feedback['message'] = $thiscommand['commandvalue'];
			break;
		case COMMAND_SET_TIMER:
			$func = $rowcommands['command'];
			$feedback['StartTimer'] = $func($thiscommand);
			break;
		default:
			$func = $rowcommands['command'];
			$feedback[$rowcommands['command']] = $func($thiscommand['commandvalue']);
			break;
		}
		$feedback['updatestatus'] = UpdateStatus($thiscommand);
		break;
	default:								// Everything else Ard/Sony/Cam/Irrigation/Virtual Devices
		if (DEBUG_DEVICES) echo "COMMAND_CLASS_OTHER</p>";
		switch ($targettype)
		{
		case "POSTAPP":          // PHP - vlosite
		case "POSTTEXT":         // Only HTPC & IrrigationCaddy at the moment
		case "POSTURL":          // Web Arduino
		case "JSON":          // Wink
			if (DEBUG_DEVICES) echo $targettype."</p>";
			$tcomm = str_replace("{mycommandID}",trim($thiscommand['commandID']),$rowcommands['command']);
			$tcomm = str_replace("{deviceID}",trim($thiscommand['deviceID']),$tcomm);
			$tcomm = str_replace("{unit}",trim($rowdevices['unit']),$tcomm);
			$tcomm = str_replace("{commandvalue}",trim($thiscommand['commandvalue']),$tcomm);
			$tcomm = str_replace("{timervalue}",trim($thiscommand['timervalue']),$tcomm);
			$tmp1 = explode('?', $tcomm);
			if (array_key_exists('1', $tmp1)) { 	// found '?', take page from command string
				$url= $rowdevicelinks['targetaddress'].":".$rowdevicelinks['targetport'].'/'.$tmp1[0];
				$tcomm = $tmp1[1];
			} else {
				$url= $rowdevicelinks['targetaddress'].":".$rowdevicelinks['targetport'].'/'.$rowdevicelinks['page'];
			}
			if (DEBUG_DEVICES) echo $url." Params: ".$tcomm.CRLF;
			if ($targettype == "POSTTEXT") { 
				$post = restClient::post($url, $tcomm,"","","text/plain");
			} elseif ($targettype == "POSTAPP") {
				$post = restClient::post($url, $tcomm);
			} elseif ($targettype == "JSON") {
				parse_str($tcomm, $params);
				if (DEBUG_DEVICES) echo $url." Params: ".json_encode($params).CRLF;
				$post = restClient::post($url, json_encode($params),"","","application/json");
			} else { 
				$post = restClient::post($url.$tcomm);
			}
			if ($post->getresponsecode() != 200 && $post->getresponsecode() != 204) 
				$feedback['error'] = $post->getresponsecode().": ".$post->getresponse();
			else 
				$feedback['message'] = $post->getresponse();
			break;
		case "GET":          // Sony Cam at the moment
			if (DEBUG_DEVICES) echo "GET</p>";
			$tcomm = str_replace("{mycommandID}",$thiscommand['commandID'],$rowcommands['command']);
			$tcomm = str_replace("{deviceID}",$thiscommand['deviceID'],$tcomm);
			$tcomm = str_replace("{unit}",$rowdevices['unit'],$tcomm);
			$tcomm = str_replace("{commandvalue}",trim($thiscommand['commandvalue']),$tcomm);
			$tcomm = str_replace("{timervalue}",trim($thiscommand['timervalue']),$tcomm);
			$url= $rowdevicelinks['targetaddress'].":".$rowdevicelinks['targetport'].'/'.$rowdevicelinks['page'];
			if (DEBUG_DEVICES) echo $url.$tcomm.CRLF;
			$get = restClient::get($url.$tcomm);
			if ($get->getresponsecode() != 200 && $get->getresponsecode() != 204)
				$feedback['error'] = $get->getresponsecode().": ".$get->getresponse();
			else 
				$feedback['message'] = $get->getresponse();
			break;
		case null:
		case "NONE":          // Virtual Devices
			if (DEBUG_DEVICES) echo "DOING NOTHING</p>";
			break;
		}
		$feedback['updatestatus'] = UpdateStatus($thiscommand);		// Update based on command assumptions
		break;		
	}
	logEvent(array('inout' => COMMAND_IO_SEND, 'callerID' => $callerparams['callerID'], 'deviceID' => $thiscommand['deviceID'], 'commandID' => $thiscommand['commandID'], 'data' => $thiscommand['commandvalue'], 'message' => $feedback, 'loglevel' => $thiscommand['loglevel']));
	
	if (DEBUG_FLOW) echo "Exit Send</pre>".CRLF;
	
	return $feedback;
} 

// Private (Only Called from SendCommand)
function RunScheme($params) {      // its a scheme, process steps. Scheme setup by a) , b) derived from remotekey

// Check conditions
	
	$schemeID = $params['schemeID'];
	$callerparams = $params['caller'];
	$loglevel = (array_key_exists('loglevel', $callerparams) ? $callerparams['loglevel'] : Null);

	if (DEBUG_FLOW) echo "<pre>Enter Runscheme $schemeID".CRLF;
	if (DEBUG_FLOW) print_r($params);
	
	
	$mysql = 'SELECT * FROM `ha_remote_scheme_conditions` WHERE `schemesID` = '.$schemeID;
	
	if (!$rescond = mysql_query($mysql)) {
		mySqlError($mysql); 
		return false;
	}
	
	while ($rowcond = mysql_fetch_assoc($rescond)) {	
		$testvalue = array();
		switch ($rowcond['type'])
		{
		case SCHEME_CONDITION_DEVICE_STATUS_VALUE: 									// what a mess already :(
//		case SCHEME_CONDITION_DEVICE_STATUS_GROUP_AND: 
//		case SCHEME_CONDITION_DEVICE_STATUS_GROUP_OR: 
			if (DEBUG_FLOW) echo "SCHEME_CONDITION_DEVICE_STATUS</p>";
			$devstatusrow = FetchRow("SELECT status FROM ha_mf_monitor_status  WHERE deviceID = ".$rowcond['deviceID']);
			$testvalue[] = $devstatusrow['status'];
			break;
		case SCHEME_CONDITION_DEVICE_VALUE_VALUE: 									// what a mess already :(
			if (DEBUG_FLOW) echo "SCHEME_CONDITION_DEVICE_VALUE</p>";
			$devstatusrow = FetchRow("SELECT commandvalue FROM ha_mf_monitor_status  WHERE deviceID = ".$rowcond['deviceID']);
			$testvalue[] = $devstatusrow['commandvalue'];
			break;
		case SCHEME_CONDITION_GROUP_STATUS_AND:
		case SCHEME_CONDITION_GROUP_STATUS_OR:
			$groups = GetGroup($rowcond['groupID']);
			if ($rowcond['type'] == SCHEME_CONDITION_GROUP_STATUS_AND) {
// || $rowcond['type'] == SCHEME_CONDITION_DEVICE_STATUS_GROUP_AND) {
				$test = 1;
			} else {
				$test = 0;
			}
			foreach ($groups as $device) {
				if ($rowcond['type'] == SCHEME_CONDITION_GROUP_STATUS_AND) {
// || $rowcond['type'] == SCHEME_CONDITION_DEVICE_STATUS_GROUP_AND) {
					$test = $test & $device['status'];
				} else {
					$test = $test | $device['status'];
				}
			}
			$testvalue[] = $test;
			break;
		case SCHEME_CONDITION_GROUP_LINK_OR:
			$groups = GetGroup($rowcond['groupID']);
			$test = 0;
			foreach ($groups as $device) {
				$test = $test | $device['link'];
			}
			$testvalue[] = $test;
			break;
		case SCHEME_CONDITION_TIMER_EXPIRED: 
			if (DEBUG_FLOW) echo "SCHEME_CONDITION_TIMER_EXPIRED</p>";
			$devstatusrow = FetchRow("SELECT deviceID, timerMinute, timerDate FROM ha_mf_monitor_status  WHERE deviceID = ".$rowcond['deviceID']);
			if (DEBUG_FLOW) print_r($devstatusrow);
			$testvalue[] = $devstatusrow['timerRemaining'];
			break;
		case SCHEME_CONDITION_CURRENT_TIME: 
			if (DEBUG_FLOW) echo "SCHEME_CONDITION_CURRENT_TIME</p>";
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
						if ($temp[0] == "DAWN") $temp[0] = GetDawn();
						if ($temp[0] == "DUSK") $temp[0] = GetDusk();
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
				if (DEBUG_FLOW) echo 'Condition Fail: "'.$testvalue[0].'" > "'.$testvalue[1].'"'.CRLF;
				$feedback['message'] = 'Condition Fail: "'.$testvalue[0].'" > "'.$testvalue[1].'"';
				return $feedback;
			}
			break;
		case CONDITION_LESS:
			if ($testvalue[0] >= $testvalue[1]) {
				if (DEBUG_FLOW) echo 'Condition Fail: "'.$testvalue[0].'" < "'.$testvalue[1].'"'.CRLF;
				$feedback['message'] = 'Condition Fail: "'.$testvalue[0].'" < "'.$testvalue[1].'"';
				return $feedback;
			}
			break;
		case CONDITION_EQUAL:
			if ($testvalue[0] != $testvalue[1]) {
				if (DEBUG_FLOW) echo 'Condition Fail: "'.$testvalue[0].'" == "'.$testvalue[1].'"'.CRLF;
				$feedback['message'] = 'Condition Fail: "'.$testvalue[0].'" == "'.$testvalue[1].'"';
				return $feedback;
			}
			break;
		}
		if (DEBUG_FLOW) echo "Condition Pass: condition value: ".$testvalue[0].", test for: ".$testvalue[1].CRLF;
	}
	
		
	//$callerparams['deviceID'] = (array_key_exists('deviceID', $callerparams) ? $callerparams['deviceID'] : $callerID);
	$sqlstr = "SELECT ha_remote_schemes.name, ha_remote_schemes.runasync, ha_remote_scheme_steps.id, ha_remote_scheme_steps.groupID, ha_remote_scheme_steps.deviceID, ha_remote_scheme_steps.commandID, ha_remote_scheme_steps.value,ha_remote_scheme_steps.runschemeID,ha_remote_scheme_steps.sort,ha_remote_scheme_steps.alert_textID ";
	$sqlstr.= " FROM (ha_remote_schemes INNER JOIN ha_remote_scheme_steps ON ha_remote_schemes.id = ha_remote_scheme_steps.schemesID) ";
	$sqlstr.=  "WHERE(((ha_remote_schemes.id) =".$schemeID.")) ORDER BY ha_remote_scheme_steps.sort";
	
	if ($resschemesteps = mysql_query($sqlstr)) {
		// Trap any async SCHEMES here
		$rowshemesteps = mysql_fetch_array($resschemesteps);
		if (!ASYNC_THREAD && $rowshemesteps['runasync']) {
			//$pid = shell_exec($cmd);
			$getparams = "ASYNC_THREAD callerID=$callerparams[callerID] messagetypeID=MESS_TYPE_SCHEME schemeID=$schemeID";
			//$cmd = 'nohup nice -n 10 /usr/bin/php -f /home/www/cronjobs/70D455DC-ACB4-4525-8A85-E6009AE93AF4/process.php '.$getparams;
			$cmd = 'nohup nice -n 10 /usr/bin/php -f '.getPath().'process.php '.$getparams;
			$outputfile=  tempnam( sys_get_temp_dir(), 'async' );
			$pidfile=  tempnam( sys_get_temp_dir(), 'async' );
			exec(sprintf("%s > %s 2>&1 & echo $! >> %s", $cmd, $outputfile, $pidfile));
			$feedback['message'] = $rowshemesteps['name']." spawned successfully";
			return $feedback;		// GET OUT
		}
		do {  // loop all steps
			$feedback['RunSchemeName'] = $rowshemesteps['name'];
			if ($feedback['RunScheme:'.$rowshemesteps['id']]=SendCommand(array( 'deviceID' => $rowshemesteps['deviceID'], 
						'commandID' => $rowshemesteps['commandID'], 'commandvalue' => $rowshemesteps['value'], 'schemeID' => $rowshemesteps['runschemeID'], 
						'alert_textID' => $rowshemesteps['alert_textID'], 'caller' => $callerparams))) {
			} 
		} while ($rowshemesteps = mysql_fetch_array($resschemesteps));
	} else {
		$feedback['message'] = 'No scheme steps found!';
	}
	if (DEBUG_FLOW) echo "Exit RunScheme</pre>".CRLF;

	return $feedback;

}

// Private
function NOP() {return;}

// Private
// $filterkeep = array( 'status' => 1, 'commandvalue' => 1, 'deviceID' => 1, 'message' => 1, 'link' => 1);
// doFilter($feedback, array( 'updatestatus' => 1,  'groupselect' => 1, 'message' => 1), $filterkeep, $result);

// $filterkeep = array( 'status' => 1, 'commandvalue' => 1, 'deviceID' => 1, 'link' => 1);
// doFilter($feedback, array( 'updatestatus' => 1,  'groupselect' => 1), $filterkeep, $result);

function doFilter(&$arr, $nodefilter, &$filter, &$result) {

    foreach ($arr as $key => $value) {
        if (array_key_exists($key, $nodefilter)) {
			if (is_array($value)) {
				$result[][$key] = array_intersect_key($arr[$key], $filter);
				//$arr[$key] = doFilter($value, $nodefilter, $filter,  $result);
//				echo "Key1: $key".CRLF;
			} else {
				if ($arr[$key] != Null) {
					$result[][$key] =$arr[$key];
				}
//				echo "Key2: $key".CRLF;
			}
        } else if (is_array($value)) {
            $arr[$key] = doFilter($value, $nodefilter, $filter,  $result);
//			echo "Key3: $key".CRLF;
        } else if (array_key_exists($key, $filter)) {
			$result[][$key] =$arr[$key];
//			echo "Key4: $key".CRLF;
		}
    }
//	print_r($result);
    return;
}

// Private
function RemoteKeys($result) {

// add link status to this

	$feedback = Array();
	foreach ($result as $key => $res) {
		if (array_key_exists('message', $res)) {
			//echo "***".$res['message']."___".preg_replace( "/\r|\n/", "", $res['message'])."***".strlen(str_replace(' ', '', preg_replace( "/\r|\n/", "", $res['message']))).CRLF;
			if (is_array($feedback) && array_key_exists('message', $feedback)) {
				if (strlen(str_replace(' ', '', preg_replace( "/\r|\n/", "", $res['message']))) > 0) $feedback['message'].= $res['message'].' ';
			} else {
				if (strlen(str_replace(' ', '', preg_replace( "/\r|\n/", "", $res['message']))) > 0) $feedback['message'] = $res['message'].' ';
			}
		} else if (array_key_exists('error', $res)) {
			if (is_array($feedback) && array_key_exists('error', $feedback)) {
				$feedback['message'].= $res['error'].' ';
			} else {
				$feedback['message'] = $res['error'].' ';
			}
		} else {
			if (array_key_exists('updatestatus', $res)) $node = 'updatestatus';
			if (array_key_exists('groupselect', $res)) $node = 'groupselect';
				

			$reskeys = mysql_query("SELECT * FROM ha_remote_keys where deviceID =".$res[$node]['deviceID']);
			while ($rowkeys = mysql_fetch_array($reskeys)) {
				if ($rowkeys['inputtype']== "button" || $rowkeys['inputtype']== "btndropdown") {
					$feedback[][$node] = true;
					$last_id=GetLastKey($feedback);
					$feedback[$last_id]["remotekey"] = $rowkeys['id'];
					if ($node == 'updatestatus') {
						if (array_key_exists('status', $res['updatestatus'])) {
							if ($res['updatestatus']['status'] == STATUS_OFF) {    			// if monitoring status and command not off then new status is on (dim/bright)
								$feedback[$last_id]["status"]="off";
							} elseif ($res['updatestatus']['status'] == STATUS_UNKNOWN) {
								$feedback[$last_id]["status"]="unknown";
							} elseif ($res['updatestatus']['status'] == STATUS_ON) {
								$feedback[$last_id]["status"]="on";
							} elseif ($res['updatestatus']['status'] == STATUS_ERROR) {
								$feedback[$last_id]["status"]="error";
							} else { 										// else assume a value
								$feedback[$last_id]["status"]="undefined";
							}
						}
						if (array_key_exists('link', $res['updatestatus'])) {
							if ($res['updatestatus']['link'] == LINK_DOWN) {    	
									$feedback[$last_id]["link"]="link-down";
								} elseif ($res['updatestatus']['link'] == LINK_UP) {
								} elseif ($res['updatestatus']['link'] == LINK_WARNING) {
									$feedback[$last_id]["link"]="link-warning";
								} else { 										// else assume a value
									$feedback[$last_id]["link"]="undefined";
							}
						}
					}
				}
				if ($rowkeys['inputtype']== "field") {
					$feedback[]["remotekey"] = $rowkeys['id'];
					$tarr = explode("___",$rowkeys['inputoptions']);
					$row = FetchRow("SELECT ".$tarr[1]." FROM ".$tarr[0]." WHERE `deviceID` =".$res['updatestatus']['deviceID']);
					$last_id=GetLastKey($feedback);
					$feedback[$last_id]["commandvalue"]=$row[$tarr[1]];
					//echo "****".
					//print_r($res['updatestatus']);
					//echo "field ".$tarr[1]." table ".$tarr[0]." val ".$row[$tarr[1]].CRLF;
				}
			}
		}
	}
	//if (array_key_exists('message'.$feedback) && $feedback['message'] = preg_replace("/\s+/", " ", $$feedback['message'] );
	return array_map("unserialize", array_unique(array_map("serialize", $feedback)));
}

// Private
function GetLastKey($arr) {
	end($arr);
	return key($arr);
}
?>
