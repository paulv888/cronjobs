<?php 
require_once 'includes.php';

// TODO:: callerparms needed?
// TODO:: clean up feedback , status and return JSON

//define( 'DEBUG_FLOW', TRUE );
//define( 'DEBUG_RETURN', TRUE );
//define( 'DEBUG_DEVICES', TRUE );
if (!defined('DEBUG_FLOW')) define( 'DEBUG_FLOW', FALSE );
if (!defined('DEBUG_RETURN')) define( 'DEBUG_RETURN', FALSE );
if (!defined('DEBUG_DEVICES')) define( 'DEBUG_DEVICES', FALSE );


if (isset($_POST["messtype"]) && isset($_POST["caller"])) {						// All have to tell where they are from.

	$messtypeID=$_POST['messtype'];
	$callerID=$_POST['caller'];
	if (DEBUG_FLOW) echo "callerID ".$callerID." ".$messtypeID.CRLF;
	switch ($messtypeID)
	{
	case MESS_TYPE_REMOTE_KEY:    									// Key pressed on remote
		if (isset($_POST["remotekey"])) {							// Called with key number Can come with command from drop-down, key number needed for device
			$remotekeyID= $_POST["remotekey"];
			$commandID=(!empty($_POST["command"]) ? $_POST["command"] : NULL);
			if (DEBUG_FLOW) echo "MESS_TYPE_REMOTE_KEY ".$remotekeyID.CRLF;
			$commandvalue= (!empty($_POST["commandvalue"]) ? $_POST["commandvalue"] : null);
			$mouse = (!empty($_POST["mouse"]) ? $_POST["mouse"] : NULL);
			echo executeCommand($callerID, $messtypeID, array( 'remotekeyID' => $remotekeyID, 'commandID' => $commandID, 'commandvalue' => $commandvalue, 'mouse' => $mouse));
		}
		break;
	case MESS_TYPE_SCHEME:												
		if (isset($_POST["scheme"])) {							
			$schemeID=$_POST["scheme"];
			if (DEBUG_FLOW) echo "MESS_TYPE_SCHEME ".$schemeID.CRLF;
			echo executeCommand($callerID, $messtypeID, array( 'schemeID' => $schemeID)); 
			// exit;
		}
		break;
	case MESS_TYPE_COMMAND:													
		if (isset($_POST["command"])) {										// Internal, then device not required
			$commandID=$_POST["command"];
			if (DEBUG_FLOW) echo "MESS_TYPE_COMMAND ".$commandID.CRLF;
			$deviceID=(!empty($_POST["device"]) ? $_POST["device"] : NULL);
			$commandvalue= (!empty($_POST["commandvalue"]) ? $_POST["commandvalue"] : NULL);
			echo executeCommand($callerID, $messtypeID, array( 'commandID' => $commandID, 'deviceID' => $deviceID,  'commandvalue' => $commandvalue ));
			//exit;
		}
		break;
	case MESS_TYPE_MULTI_KEY:													
		if (isset($_POST["selection"])) {
			$selection=$_POST["selection"];
			$commandID=(!empty($_POST["command"]) ? $_POST["command"] : NULL);
			$commandvalue= (!empty($_POST["commandvalue"]) ? $_POST["commandvalue"] : null);
			if (DEBUG_FLOW) echo "MESS_TYPE_GET_GROUP ";
			if (DEBUG_FLOW) print_r($selection);
			echo executeCommand($callerID, $messtypeID, array( 'selection' => $selection, 'commandID' => $commandID, 'commandvalue' => $commandvalue ));
		}
		break;
	}
}

/*
*/

function executeCommand($callerID, $messtypeID, $params) {

	/* Get the Keys Schema or Device */
	$deviceID = (array_key_exists('deviceID', $params) ? $params['deviceID'] : Null);
	if (IsNullOrEmptyString($deviceID)) $deviceID = Null;
	$schemeID = (array_key_exists('schemeID', $params) ? $params['schemeID'] : Null);
	$remotekeyID = (array_key_exists('remotekeyID', $params) ? $params['remotekeyID'] : Null);
	$commandID = (array_key_exists('commandID', $params) ? $params['commandID'] : Null);
	$commandvalue = (array_key_exists('commandvalue', $params) ? $params['commandvalue'] : Null);
	$selection = (array_key_exists('selection', $params) ? $params['selection'] : Null);
	$mouse = (array_key_exists('mouse', $params) ? $params['mouse'] : Null);
	if ($callerID == DEVICE_REMOTE) header('Content-type: application/json'); 


	if (DEBUG_FLOW) echo '<pre>Entry executeCommand - Params: ';
	if (DEBUG_FLOW) echo print_r($params);
	if (DEBUG_FLOW) echo "callerID: ".$callerID.CRLF;

	$tc = ($commandID == null  ? COMMAND_UNKNOWN : $commandID);

	ob_start(); // Start output buffering				move to logevent 
	print_r($params);
	echo "callerID: ".$callerID.CRLF;
	$te = ob_get_clean(); // End buffering and clean up


		
	global $inst_coder;
	$inst_coder = new InsteonCoder();
	$feedback['messtypeID'] = $messtypeID;

	$callerparams = $params;
	$callerparams['deviceID'] = (array_key_exists('deviceID', $callerparams) ? $callerparams['deviceID'] : $callerID);

	$feedback['show_result'] = false;
	switch ($messtypeID)
	{
	case MESS_TYPE_REMOTE_KEY:    // Key pressed on remote
		$rowkeys = FetchRow("SELECT * FROM ha_remote_keys where id =".$remotekeyID);
		$schemeID = $rowkeys['schemeID'];
		$feedback['show_result'] = false;
		if (!empty($rowkeys)) if ($rowkeys['show_result']) $feedback['show_result'] = true;
		
		if ($schemeID <=0) {  													// not a scheme, Execute
			if ($commandID===NULL) {
				if ($mouse=='down') { 
					$commandID=$rowkeys['commandIDdown'];
					if (is_null($commandID)) {
						return false;
					}
				} else {
					$commandID=$rowkeys['commandID'];
				}
			}
			$feedback['SendCommand']=SendCommand($callerID, array( 'deviceID' => $rowkeys['deviceID'], 'commandID' => $commandID, 'commandvalue' => $commandvalue), $callerparams);
		} else {
			$callerparams['schemeID'] = $schemeID;
			$feedback['RunScheme'] = RunScheme ($callerID, $callerparams);
		}
		break;
	case MESS_TYPE_SCHEME:
		if (DEBUG_FLOW) echo "MESS_TYPE_SCHEME scheme: ".$schemeID.CRLF;
		$feedback['SendCommand']=SendCommand($callerID, array('commandID' => COMMAND_RUN_SCHEME,  'commandvalue' => $schemeID), $callerparams);
		break;
	case MESS_TYPE_COMMAND:        
		if (DEBUG_FLOW) echo "MESS_TYPE_COMMAND commandID: ".$commandID." deviceID: ".$deviceID.CRLF;
		$feedback['SendCommand']=SendCommand($callerID, array( 'deviceID' => $deviceID, 'commandID' => $commandID,  'commandvalue' => $commandvalue), $callerparams);
		break;
	case MESS_TYPE_MULTI_KEY:
		if ($commandID != COMMAND_GET_VALUE) {
			switch ($commandvalue)
			{
				case 0:
					$commandID = COMMAND_OFF;
					break;
				case 100:
					$commandID = COMMAND_ON;
					break;
				default:
					$commandID = COMMAND_DIM;
					break;
			}
			foreach ($selection AS $remotekeyID) {
				$rowkeys = FetchRow("SELECT * FROM ha_remote_keys where id =".$remotekeyID);
				$feedback['SendCommand'][]=SendCommand($callerID, array( 'deviceID' => $rowkeys['deviceID'], 'commandID' => $commandID, 'commandvalue' => $commandvalue), $callerparams);
			}
		} else {
			foreach ($selection AS $remotekeyID) {
				$rowkeys = FetchRow("SELECT * FROM ha_remote_keys where id =".$remotekeyID);
				$feedback[]['updatestatus']=GetStatusLink(array( 'callerID' => $callerID, 'deviceID' => $rowkeys['deviceID']));
			}
		}
	}
	
	if ($mouse == 'down') return;
	if (DEBUG_RETURN) echo "Feedback: >";
	if (DEBUG_RETURN) print_r($feedback);
	if (DEBUG_RETURN) echo "executeCommand Exit".CRLF;

	$filterkeep = array( 'status' => 1, 'commandvalue' => 1, 'deviceID' => 1, 'message' => 1, 'link' => 1);
	doFilter($feedback, array( 'updatestatus' => 1,  'groupselect' => 1, 'message' => 1), $filterkeep, $result);
	if (DEBUG_RETURN) echo "Filtered: >";
	if (DEBUG_RETURN) print_r($result);
	if ($callerID == DEVICE_REMOTE) {
		if ($result != null) {
			$result = RemoteKeys($result);
		} else { 
			$result['message'] = '';
		}
	}
	
	return 	json_encode($result);
			
}

function RunScheme($callerID, $params) {      // its a scheme, process steps. Scheme setup by a) , b) derived from remotekey

// Check conditions
	
	$schemeID = $params['schemeID'];
	$loglevel = (array_key_exists('loglevel', $params) ? $params['loglevel'] : Null);

	preg_match ( "/^[1-9][0-9]*/", $schemeID, $matches);
	$schemeID = $matches[0];

	if (DEBUG_FLOW) echo "<pre>Enter Runscheme $schemeID".CRLF;
	if (DEBUG_FLOW) print_r($params);
	if (DEBUG_FLOW) echo "callerID: ".$callerID.CRLF;
	
	
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
	
	$callerparams = $params;
	$callerparams['deviceID'] = (array_key_exists('deviceID', $callerparams) ? $callerparams['deviceID'] : $callerID);
	$sqlstr = "SELECT ha_remote_schemes.name, ha_remote_scheme_steps.id, ha_remote_scheme_steps.groupID, ha_remote_scheme_steps.deviceID, ha_remote_scheme_steps.commandID, ha_remote_scheme_steps.value,ha_remote_scheme_steps.sort,ha_remote_scheme_steps.alert_textID ";
	$sqlstr.= " FROM (ha_remote_schemes INNER JOIN ha_remote_scheme_steps ON ha_remote_schemes.id = ha_remote_scheme_steps.schemesID) ";
	$sqlstr.=  "WHERE(((ha_remote_schemes.id) =".$schemeID.")) ORDER BY ha_remote_scheme_steps.sort";
	if ($resschemesteps = mysql_query($sqlstr)) {
		while ($rowshemesteps = mysql_fetch_array($resschemesteps)) {  // loop all steps
				$feedback['RunSchemeName'] = $rowshemesteps['name'];
				if ($feedback['RunScheme:'.$rowshemesteps['id']]=SendCommand($callerID, array( 'deviceID' => $rowshemesteps['deviceID'], 
							'commandID' => $rowshemesteps['commandID'], 'commandvalue' => $rowshemesteps['value'], 
							'alert_textID' => $rowshemesteps['alert_textID']), $callerparams)) {
			} 
		}
	} else {
		$feedback['message'] = 'No scheme steps found!';
	}
	if (DEBUG_FLOW) echo "Exit RunScheme</pre>".CRLF;

	return $feedback;

}



function SendCommand($callerID, $thiscommand, $callerparams = array()) { 

	$deviceID = (array_key_exists('deviceID', $thiscommand) ? $thiscommand['deviceID'] : Null);
	$callerparams['deviceID'] = (array_key_exists('deviceID', $callerparams) ? $callerparams['deviceID'] : Null);		// not sending non key to a
	if (IsNullOrEmptyString($deviceID)) $deviceID = Null;
	$commandID = (array_key_exists('commandID', $thiscommand) ? $thiscommand['commandID'] : Null);
	$commandvalue = (array_key_exists('commandvalue', $thiscommand) ? $thiscommand['commandvalue'] : 100);
	$timervalue = (array_key_exists('timervalue', $thiscommand) ? $thiscommand['timervalue'] : 0);
	$loglevel = (array_key_exists('loglevel', $callerparams) ? $callerparams['loglevel'] : Null);
	$alert_textID = (array_key_exists('alert_textID', $thiscommand) ? $thiscommand['alert_textID'] : Null);

	if (DEBUG_FLOW || DEBUG_DEVICES) {
		echo "Enter SendCommand ".CRLF;
		echo "This Command: ";
		if ($ct = FetchRow("SELECT description FROM ha_mf_commands  WHERE ha_mf_commands.id =".$commandID))  {
			echo $ct['description'].' ';			// error abort
		} 
		print_r($thiscommand);
		echo "Caller Params ";
		print_r($callerparams);
		echo "callerID: ".$callerID.CRLF;
	}

	if ($commandID == null) {
		$feedback['error'] = 1;
		$feedback['message'] = "No Command given";
		return $feedback;			// error abort
	}
//
//   Sends 1 single command to TCP, REST, EMAIL
//	
	
	global $inst_coder;
	if ($inst_coder instanceof InsteonCoder) {
	} else {
		$inst_coder = new InsteonCoder();
	}

	
	// Handles 1 single Device
	$feedback['error'] = 0;
	$targettype = Null;
	if ($deviceID != NULL) {
		$resdevices = mysql_query("SELECT * FROM ha_mf_devices where id =".$deviceID.' AND inuse= 1');
		if (!$rowdevices = mysql_fetch_array($resdevices)) return;
		if ($resdevicelinks = mysql_query("SELECT * FROM ha_mf_device_links where id =".$rowdevices['devicelinkID'])) {
			($rowdevicelinks = mysql_fetch_array($resdevicelinks));
			if ($rowdevicelinks) {
				$targettype = $rowdevicelinks['targettype'];
			}
		}
		$commandclassID = $rowdevices['commandclassID'];
		if (DEBUG_DEVICES) echo "targettype ".$targettype.CRLF;

		if ($commandID==COMMAND_TOGGLE) {   // Special handling for toggle
			if ($commandvalue > 0 && $commandvalue < 100) { // if dimvalue given then update dim, else toggle
				$commandID = COMMAND_ON;						
			} else {
				$resmonitor = mysql_query("SELECT ha_mf_monitor_status.status FROM ha_mf_monitor_status WHERE ha_mf_monitor_status.deviceID =".$deviceID);
				$rowmonitor = mysql_fetch_array($resmonitor);
				if ($rowmonitor) {
					if (DEBUG_DEVICES) echo "Status Toggle: ".$rowmonitor['status'].CRLF;
					$commandID = ($rowmonitor['status'] == STATUS_ON ? COMMAND_OFF : COMMAND_ON); // toggle on/off
				} else {		// not status monitoring 
					if (DEBUG_DEVICES) echo "NO STATUS RECORD FOUND, GETTING OUT".CRLF;
					return;
				}
			}
		}


	} else {
		$commandclassID = COMMAND_CLASS_PHP;
	}
	

	$mysql = "SELECT * FROM ha_mf_commands JOIN ha_mf_commands_detail ON ".
			" ha_mf_commands.id=ha_mf_commands_detail.commandID" .
			" WHERE ha_mf_commands.id =".$commandID. " AND commandclassID = ".$commandclassID." AND `inout` IN (".COMMAND_IO_SEND.','.COMMAND_IO_BOTH.')';
	if (!$rowcommands = FetchRow($mysql))  {			// No device specific command found, try generic, else exit
		$commandclassID = COMMAND_CLASS_PHP;
		$mysql = "SELECT * FROM ha_mf_commands JOIN ha_mf_commands_detail ON ".
				" ha_mf_commands.id=ha_mf_commands_detail.commandID" .
				" WHERE ha_mf_commands.id =".$commandID. " AND commandclassID = ".$commandclassID." AND `inout` IN (".COMMAND_IO_SEND.','.COMMAND_IO_BOTH.')';
		if (!$rowcommands = FetchRow($mysql))  {
			$feedback['error'] = 1;
			$feedback['message'] = "No outgoing Command found";
			return $feedback;			// error abort
		}
	}

	if ($targettype == 'NONE') $commandclassID = COMMAND_CLASS_GENERIC; // Treat command for devices with no outgoing as virtual, i.e. set day/night to on/off
	if (DEBUG_FLOW || DEBUG_DEVICES) echo "commandID ".$commandID.CRLF;
	if (DEBUG_FLOW || DEBUG_DEVICES) echo "commandclassID ".$commandclassID.CRLF;
	if (DEBUG_FLOW || DEBUG_DEVICES) echo "commandvalue ".$commandvalue.CRLF;
	if (DEBUG_FLOW || DEBUG_DEVICES) echo " command ". $rowcommands['command'].CRLF;
	//if (DEBUG_DEVICES) echo " command commandvalue ". $rowcommands['commandvalue'].CRLF;
	
	switch ($commandclassID)
	{
	case COMMAND_CLASS_3MFILTRETE:          
		if (DEBUG_DEVICES) echo "COMMAND_CLASS_3MFILTRETE</p>";
		$func = $rowcommands['command'];
		$feedback['updatestatus'] = $func($callerID, $deviceID, $commandvalue);
		break;
	case COMMAND_CLASS_EMAIL:
		if (DEBUG_DEVICES) echo "COMMAND_CLASS_EMAIL".CRLF;
		$rowtext = FetchRow("SELECT * FROM ha_alert_text where id =".$alert_textID);
		$subject= $rowtext['description'];
		$message= $rowtext['message'];
		if (strlen($message) == 0) $message = " ";
		//echo $commandvalue.CRLF.CRLF;
		$callerparams['stepmessage'] = $commandvalue;
		replaceText(array('deviceID' => $callerparams['deviceID']), $subject, $message, $callerparams);
		//echo $message.CRLF.CRLF;
		$feedback['error'] = (sendmail($rowcommands['command'], $subject, $message, 'VloHome') == true ? false : true);
		break;
	case COMMAND_CLASS_INSTEON:
		if (DEBUG_DEVICES) echo "COMMAND_CLASS_INSTEON".CRLF;
		$tcomm = str_replace("{mycommandID}",$commandID,$rowcommands['command']);
		$tcomm = str_replace("{deviceID}",$deviceID,$tcomm);
		$tcomm = str_replace("{unit}",$rowdevices['unit'],$tcomm);
		$rowextra = FetchRow('SELECT * FROM `ha_mf_device_extra` WHERE deviceID = '. $deviceID);
		if (!$rowextra['dimmable']) {
			$commandvalue = 100;
		}
		if ($commandvalue>100) $commandvalue=100;
		if ($commandvalue!=100 && $commandID == COMMAND_ON) $commandvalue= $rowextra['onlevel'];
		if ($commandvalue>0) $commandvalue=255/100*$commandvalue;
		if ($commandvalue == NULL && $commandID == COMMAND_ON) $commandvalue=255;		// Special case so satify the replace in on command
		$commandvalue = dec2hex($commandvalue,2);
		if (DEBUG_DEVICES) echo "commandvalue ".$commandvalue.CRLF;
		$tcomm = str_replace("{commandvalue}",$commandvalue,$tcomm);
		if (DEBUG_DEVICES) echo "Rest deviceID ".$deviceID." commandID ".$commandID.CRLF;
		$url=$rowdevicelinks['targetaddress'].":".$rowdevicelinks['targetport'].$rowdevicelinks['page'].$tcomm.'=I=3';
		if (DEBUG_DEVICES) echo $url.CRLF;
		$get = restClient::get($url);
		$feedback['error'] = ($get->getresponsecode()==200 ? 0 : $get->getresponse());
		$feedback['message'] = trim($get->getresponse());
		usleep(INSTEON_SLEEP_MICRO);
		if (!$feedback['error']) {
			$result[] = ($commandID == COMMAND_OFF ? STATUS_OFF : STATUS_ON);
			$result[] = $commandvalue;
			$feedback['updatestatus'] = UpdateStatus(array( 'callerID' => $callerID, 'deviceID' => $deviceID, 'commandID' => $commandID));
		}
		break;
	case COMMAND_CLASS_X10_INSTEON:
		$tcomm = str_replace("{mycommandID}",$commandID,$rowcommands['command']);
		$rowextra = FetchRow('SELECT * FROM `ha_mf_device_extra` WHERE deviceID = '. $deviceID);
		if ($rowextra['dimmable']) {
			$dims = 0;
			if ($commandvalue>0 && $commandvalue < 100) $dims=(integer)round(10-10/100*$commandvalue);
			if (DEBUG_DEVICES) echo "commandvalue ".$commandvalue.CRLF;
			if (DEBUG_DEVICES) echo "dims ".$dims.CRLF;
			while($dims > 0) {
				$tcomm .= COMMAND_DIM_CLASS_X10_INSTEON_DIMM;
				$dims--;
			}
			$tcomm = COMMAND_DIM_CLASS_X10_INSTEON_OFF.$tcomm; 	// Add off in front
		} else {
			$commandvalue = 100;
		}
		if ($commandvalue>100) $commandvalue=100;
		if ($commandvalue!=100 && $commandID == COMMAND_ON) $commandvalue= $rowextra['onlevel'];
		if ($commandvalue == NULL && $commandID == COMMAND_ON) $commandvalue=100;		// Special case so satify the replace in on command
//		$tcomm .={code}a80=I=3;	$tcomm .={code}b80=I=3 $tcomm .= "|{code}{unit}00=I=3"; $tcomm .= "|{code}a80=I=3";	$tcomm .= "|0b80=I=3";
//		$tcomm .= "|{code}480=I=3";			// dim 480  $tcomm .= "|a780=I=3";	$tcomm .= "|0b80=I=3";
		$tcomm = str_replace("{code}",$inst_coder->x10_code_encode($rowdevices['code']),$tcomm);
		$tcomm = str_replace("{unit}",$inst_coder->x10_unit_encode($rowdevices['unit']),$tcomm);
		if (DEBUG_DEVICES) echo "Rest deviceID ".$deviceID." commandID ".$commandID.CRLF;
		$commands=explode("|", $tcomm);
		//
		// handle dimming, cannot give commandvalue so dimming lots of times
		//
		foreach ($commands as $command) {
			$url=$rowdevicelinks['targetaddress'].":".$rowdevicelinks['targetport'].$rowdevicelinks['page'].$command.'=I=3';
			if (DEBUG_DEVICES) echo $url.CRLF;
			$get = restClient::get($url);
			$feedback['error'] = $feedback['error'] | ($get->getresponsecode()==200 ? 0 : $get->getresponsecode());
			$feedback['message'] = trim($get->getresponse());
			usleep(INSTEON_SLEEP_MICRO);
		}     
		if (!$feedback['error']) {
			$result[] = ($commandID == COMMAND_OFF ? STATUS_OFF : STATUS_ON);
			$result[] = $commandvalue;
			$feedback['updatestatus'] = UpdateStatus(array( 'callerID' => $callerID, 'deviceID' => $deviceID, 'commandID' => $commandID));
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
		if ($commandID ==  COMMAND_ON && $commandvalue>0 && $commandvalue<100) {
			$x10[0]->Command = "On";
			$x10[0]->CmdData = NULL;
			$x10[0]->Date = date("Y-m-d H:i:s");
			WriteTCP($x10[0]->asXML());
			$x10[0]->Command = "Bright";
			$x10[0]->CmdData = 100;
			$x10[0]->Date = date("Y-m-d H:i:s");
			WriteTCP($x10[0]->asXML());
			$x10[0]->Command = "Dim";
			$x10[0]->CmdData = 100-$commandvalue;
			$x10[0]->Date = date("Y-m-d H:i:s");
			WriteTCP($x10[0]->asXML());
		} else {
			$x10[0]->Command = $rowcommands['description'];
			$x10[0]->CmdData = $commandvalue;
			$x10[0]->Date = date("Y-m-d H:i:s");
			WriteTCP($x10[0]->asXML());
		}
		CloseTCP("X10");
		$result[] = (commandID == COMMAND_OFF ? STATUS_OFF : STATUS_ON);
		$result[] = $commandvalue;
		$feedback['error'] = 0;
		break;
	case COMMAND_CLASS_PHP:								// No device or no outgoing data
		if (DEBUG_DEVICES) echo "COMMAND_CLASS_PHP</p>";
		switch ($commandID)
		{
		case COMMAND_RUN_SCHEME:
			$callerparams['schemeID'] = $commandvalue;
			$feedback['runscheme'] = RunScheme($callerID, $callerparams);
			break;
		case COMMAND_LOG_ALERT:
			$callerparams['stepmessage'] = $commandvalue;
			$feedback['message'] = Alerts($alert_textID, $callerparams).' created';
			break;
		case COMMAND_GET_GROUP:
			$func = $rowcommands['command'];
			$groups = $func($commandvalue);
			$feedback = array();
			foreach($groups as $device) {
				$feedback[]['groupselect']['deviceID'] = $device['deviceID'];
			}
			break;
		case COMMAND_SET_RESULT:
			$feedback['message'] = $commandvalue;
			break;
		case COMMAND_SET_TIMER:
			$func = $rowcommands['command'];
			$feedback['error'] = $func($callerID, $deviceID,$commandvalue);
			break;
		default:
			$func = $rowcommands['command'];
			$feedback['error'] = $func($commandvalue);
			break;
		}
		$feedback['updatestatus'] = UpdateStatus(array( 'callerID' => $callerID, 'deviceID' => $deviceID, 'commandID' => $commandID));
		break;
	default:								// Generid
		if (DEBUG_DEVICES) echo "COMMAND_CLASS_GENERIC</p>";
		switch ($targettype)
		{
		case "POSTTEXT":         // Only HTPC & IrrigationCaddy at the moment
		case "POSTURL":          // Web Arduino
			if (DEBUG_DEVICES) echo "POSTURL</p>";
			$tcomm = str_replace("{mycommandID}",trim($commandID),$rowcommands['command']);
			$tcomm = str_replace("{deviceID}",trim($deviceID),$tcomm);
			$tcomm = str_replace("{unit}",trim($rowdevices['unit']),$tcomm);
			$tcomm = str_replace("{commandvalue}",trim($commandvalue),$tcomm);
			$tcomm = str_replace("{timervalue}",trim($timervalue),$tcomm);
			$tmp1 = explode('?', $tcomm);
			if (array_key_exists('1', $tmp1)) { 	// found '?', take page from command string
				$url= $rowdevicelinks['targetaddress'].":".$rowdevicelinks['targetport'].'/'.$tmp1[0];
				$tcomm = $tmp1[1];
			} else {
				$url= $rowdevicelinks['targetaddress'].":".$rowdevicelinks['targetport'].'/'.$rowdevicelinks['page'];
			}
			if (DEBUG_DEVICES) echo $url.$tcomm.CRLF;
			if ($targettype == "POSTTEXT") { 
				$post = restClient::post($url, $tcomm,"","","text/plain");
			} else { 
				$post = restClient::post($url.$tcomm);
			}
			$feedback['error'] = $feedback['error'] | ($post->getresponsecode()==200 ? 0 : $post->getresponsecode());
			$feedback['message'] = trim($post->getresponse());
			break;
		case "GET":          // Sony Cam at the moment
			if (DEBUG_DEVICES) echo "GET</p>";
			$tcomm = str_replace("{mycommandID}",$commandID,$rowcommands['command']);
			$tcomm = str_replace("{deviceID}",$deviceID,$tcomm);
			$tcomm = str_replace("{unit}",$rowdevices['unit'],$tcomm);
			$url= $rowdevicelinks['targetaddress'].":".$rowdevicelinks['targetport'].'/'.$rowdevicelinks['page'];
			if (DEBUG_DEVICES) echo $url.$tcomm.CRLF;
			$get = restClient::get($url.$tcomm);
			$feedback['error'] = $feedback['error'] | ($get->getresponsecode()==200 ? 0 : $get->getresponsecode());
			$feedback['message'] = trim($get->getresponse());
			break;
		case null:
		case "NONE":          // Virtual Devices
			if (DEBUG_DEVICES) echo "DOING NOTHING</p>";
			$feedback['error'] =  0;
			break;
		}
		
		$feedback['updatestatus'] = UpdateStatus(array( 'callerID' => $callerID, 'deviceID' => $deviceID, 'commandID' => $commandID));		// Update base on command assumptions
		break;		
	}
	logEvent(array('inout' => COMMAND_IO_SEND, 'callerID' => $callerID, 'deviceID' => $deviceID, 'commandID' => $commandID, 'data' => $commandvalue, 'message' => $feedback, 'loglevel' => $loglevel));
	
	if (DEBUG_FLOW) echo "Exit Send".CRLF;
	
	return $feedback;
} 

function NOP() {return;}

function doFilter(&$arr, $nodefilter, &$filter, &$result) {

    foreach ($arr as $key => $value) {
        if (array_key_exists($key, $nodefilter)) {
			if (is_array($value)) {
				$result[][$key] = array_intersect_key($arr[$key], $filter);
				$arr[$key] = doFilter($value, $nodefilter, $filter,  $result);
			} else {
				if ($arr[$key] != Null) {
					$result[][$key] =$arr[$key];
				}
			}
        } else if (is_array($value)) {
            $arr[$key] = doFilter($value, $nodefilter, $filter,  $result);
        }
    }
    return;
}

function RemoteKeys($result) {

// add link status to this

	$feedback = null;
	foreach ($result as $key => $res) {
		if (array_key_exists('message', $res)) {
			if (is_array($feedback) && array_key_exists('message', $feedback)) {
				$feedback['message'].= $res['message'].' ';
			} else {
				$feedback['message'] = $res['message'].' ';
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
									$feedback[$last_id]["link"]="link-down";
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
	return array_map("unserialize", array_unique(array_map("serialize", $feedback)));
}

function GetLastKey($arr) {
	end($arr);
	return key($arr);
}
?>
