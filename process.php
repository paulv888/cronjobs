<?php 
require_once 'includes.php';

// TODO:: callerparms needed?
// TODO:: clean up feedback , verbstatus to status and return JSON

//define( 'MYDEBUG', TRUE );
if (!defined('MYDEBUG')) define( 'MYDEBUG', FALSE );


if (isset($_POST["messtype"]) && isset($_POST["caller"])) {						// All have to tell where they are from.

	$messtypeID=$_POST["messtype"];
	$callerID=$_POST["caller"];
	if (MYDEBUG) echo "callerID ".$callerID." ".$messtypeID.CRLF;
	switch ($messtypeID)
	{
	case MESS_TYPE_REMOTE_KEY:    									// Key pressed on remote
		if (isset($_POST["remotekey"])) {							// Called with key number		Can come with command from drop-down, key number needed for device
			$remotekeyID= $_POST["remotekey"];
			$commandID=(!empty($_POST["command"]) ? $_POST["command"] : NULL);
			if (MYDEBUG) echo "MESS_TYPE_REMOTE_KEY ".$remotekeyID.CRLF;
			$setvalue= (!empty($_POST["setvalue"]) ? $_POST["setvalue"] : 100);
			$mouse = (!empty($_POST["mouse"]) ? $_POST["mouse"] : NULL);
			echo executeCommand($callerID, $messtypeID, array( 'remotekeyID' => $remotekeyID, 'commandID' => $commandID, 'setvalue' => $setvalue, 'mouse' => $mouse));
		}
		break;
	case MESS_TYPE_SCHEME:												
		if (isset($_POST["scheme"])) {							
			$schemeID=$_POST["scheme"];
			if (MYDEBUG) echo "MESS_TYPE_SCHEME ".$schemeID.CRLF;
			echo executeCommand($callerID, $messtypeID, array( 'schemeID' => $schemeID)); 
			exit;
		}
		break;
	case MESS_TYPE_COMMAND:													
		if (isset($_POST["command"])) {										// Internal, then device not required
			$commandID=$_POST["command"];
			if (MYDEBUG) echo "MESS_TYPE_COMMAND ".$commandID.CRLF;
			$deviceID=(!empty($_POST["device"]) ? $_POST["device"] : NULL);
			echo executeCommand($callerID, $messtypeID, array( 'commandID' => $commandID, 'deviceID' => $deviceID ));
			exit;
		}
		break;
	case MESS_TYPE_GET_GROUP:													
		if (isset($_POST["group"])) {										// Internal, then device not required
			$groupID=$_POST["group"];
			if (MYDEBUG) echo "MESS_TYPE_GET_GROUP ".$groupID.CRLF;
//			$deviceID=(!empty($_POST["device"]) ? $_POST["device"] : NULL);
			echo executeCommand($callerID, $messtypeID, array( 'groupID' => $groupID ));
			exit;
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
	$setvalue = (array_key_exists('setvalue', $params) ? $params['setvalue'] : Null);
	$mouse = (array_key_exists('mouse', $params) ? $params['mouse'] : Null);


	if (MYDEBUG) echo '<pre>Entry executeCommand - Params: ';
	if (MYDEBUG) echo print_r($params);
	if (MYDEBUG) echo "callerID: ".$callerID.CRLF;

	
	$tc = ($commandID == null  ? COMMAND_UNKNOWN : $commandID);

	// Move this to logevents?
	ob_start(); // Start output buffering
	print_r($params);
	echo "callerID: ".$callerID.CRLF;
	$te = ob_get_clean(); // End buffering and clean up

	logEvent(Array ('inout' => COMMAND_IO_RECV, 'callerID' => $callerID, 'deviceID' => $deviceID, 'commandID' => $tc, 'data' => $setvalue,
		'message' => $te, 'loglevel' => LOGLEVEL_DEBUG));

		
	global $inst_coder;
	$inst_coder = new InsteonCoder();
	$feedback = "";
	//$schemeID = 0;
	
	switch ($messtypeID)
	{
	case MESS_TYPE_REMOTE_KEY:    // Key pressed on remote
		$reskeys = mysql_query("SELECT * FROM ha_remote_keys where id =".$remotekeyID);
		$rowkeys = mysql_fetch_array($reskeys);
		$schemeID=$rowkeys['schemeID'];
		
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
			if ($result=SendCommand($callerID, Array ( 'deviceID' => $rowkeys['deviceID'], 'commandID' => $commandID, 'setvalue' => $setvalue), $params)) {
				$feedback .= $result;
			} else {
				$feedback = false;
			}
		} 
		break;
	case MESS_TYPE_SCHEME:
		if (MYDEBUG) echo "MESS_TYPE_SCHEME scheme: ".$schemeID.CRLF;
		break;
	case MESS_TYPE_COMMAND:        
		if (MYDEBUG) echo "MESS_TYPE_COMMAND commandID: ".$commandID." deviceID: ".$deviceID.CRLF;
		if ($result=SendCommand($callerID, Array ( 'deviceID' => $deviceID, 'commandID' => $commandID), $params)) {
			$feedback .= $result;
		} else {
			$feedback = false;
		}
		break;
	}
	
	if ($mouse == 'down') return;
	if ($schemeID>0)  {
		$result = RunScheme ($callerID, $schemeID, $params);
		if (!is_bool($result)) $feedback .= $result;				// do not convert true's to 1's
	}			
	
	if (!empty($rowkeys)) 
		if ($rowkeys['show_result']) 
			return $feedback;
	
	if (MYDEBUG) echo "Feedback: >".$feedback."<";
	if (MYDEBUG) echo "executeCommand Exit".CRLF;

	var_dump($result);
	var_dump($feedback);

	return ($feedback  ? "OK;".$feedback : "An error occurred");
			
}

function RunScheme($callerID, $schemeID, $params) {      // its a scheme, process steps. Scheme setup by a) , b) derived from remotekey, c) derived from alerts

// Check conditions
	if (MYDEBUG) echo "<pre>Enter Runscheme $schemeID".CRLF;
	if (MYDEBUG) print_r($params);
	if (MYDEBUG) echo "callerID: ".$callerID.CRLF;
	
	preg_match ( "/^[1-9][0-9]*/", $schemeID, $matches);
	$schemeID = $matches[0];
	
	$mysql = 'SELECT * FROM `ha_remote_scheme_conditions` WHERE `schemesID` = '.$schemeID;
	
	if (!$rescond = mysql_query($mysql)) {
		mySqlError($mysql); 
		exit;
	}
	
	while ($rowcond = mysql_fetch_assoc($rescond)) {	
		switch ($rowcond['type'])
		{
		case SCHEME_CONDITION_DEVICE_STATUS: 
			if (MYDEBUG) echo "SCHEME_CONDITION_DEVICE_STATUS</p>";
			$devstatusrow = FetchRow("SELECT status FROM ha_mf_monitor_status  WHERE deviceID = ".$rowcond['deviceID']);
			$testvalue = $devstatusrow['status'];
			$condvalue = $rowcond['status'];
			if ($condvalue !== $testvalue) {
				if (MYDEBUG) echo "Condition fail: confd:".$condvalue." ,test: ".$testvalue.CRLF;
				return true;
			}
			break;
		case SCHEME_CONDITION_TIME: 
			if (MYDEBUG) echo "SCHEME_CONDITION_TIME</p>";
			echo "Not Implemented</p>";
			break;
		}
		if (MYDEBUG) echo "Condition Pass: confd".$condvalue." ,test: ". $testvalue.CRLF;
	}
	
	$sqlstr = "SELECT ha_remote_scheme_steps.id, ha_remote_scheme_steps.deviceID, ha_remote_scheme_steps.commandID, ha_remote_scheme_steps.value,ha_remote_scheme_steps.sort,ha_remote_scheme_steps.alert_textID ";
	$sqlstr.= " FROM (ha_remote_schemes INNER JOIN ha_remote_scheme_steps ON ha_remote_schemes.id = ha_remote_scheme_steps.schemesID) ";
	$sqlstr.=  "WHERE(((ha_remote_schemes.id) =".$schemeID.")) ORDER BY ha_remote_scheme_steps.sort";
	$resschemesteps	= mysql_query($sqlstr);
	$feedback = '';
	while ($rowshemesteps = mysql_fetch_array($resschemesteps)) {  // loop all steps
		if ($result=SendCommand($callerID, Array ( 'deviceID' => $rowshemesteps['deviceID'], 'commandID' => $rowshemesteps['commandID'], 
						'setvalue' => $rowshemesteps['value'], 'alert_textID' => $rowshemesteps['alert_textID']), $params)) {
			if (!is_bool($result)) $feedback .= $result;				// do not convert true's to 1's
		} else {
			$feedback = false;
		}
	}
	var_dump($result);
	var_dump($feedback);
	if (strlen($feedback) == 0) return true;
	if (MYDEBUG) echo "Exit RunScheme</pre>".CRLF;
	return $feedback;
}



function SendCommand($callerID, $thiscommand, $callerparams ) { 

	$deviceID = (array_key_exists('deviceID', $thiscommand) ? $thiscommand['deviceID'] : Null);
	$callerparams['deviceID'] = (array_key_exists('deviceID', $callerparams) ? $callerparams['deviceID'] : Null);		// not sending non key to createmail
	if (IsNullOrEmptyString($deviceID)) $deviceID = Null;
	$commandID = (array_key_exists('commandID', $thiscommand) ? $thiscommand['commandID'] : Null);
	$value = (array_key_exists('setvalue', $thiscommand) ? $thiscommand['setvalue'] : 100);
	$alert_textID = (array_key_exists('alert_textID', $thiscommand) ? $thiscommand['alert_textID'] : Null);

	if (MYDEBUG) echo "Enter SendCommand ";
	if (MYDEBUG) print_r($thiscommand);
	if (MYDEBUG) echo "callerID: ".$callerID.CRLF;

	
//
//   Sends 1 single command to TCP, REST, EMAIL
//	
	global $inst_coder;

	// Handles 1 single Device
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
		if (MYDEBUG) echo "targettype ".$targettype.CRLF;

		if ($commandID==COMMAND_TOGGLE) {   // Special handling for toggle
			if ($value==100) {
				$resmonitor = mysql_query("SELECT ha_mf_monitor_status.status FROM ha_mf_monitor_status WHERE ha_mf_monitor_status.deviceID =".$deviceID);
				$rowmonitor = mysql_fetch_array($resmonitor);
				if ($rowmonitor) {
					if (MYDEBUG) echo "Status Toggle: ".$rowmonitor['status'].CRLF;
					$commandID = ($rowmonitor['status'] == STATUS_ON ? COMMAND_OFF : COMMAND_ON); // toggle on/off
				} else {		// not status monitoring 
					if (MYDEBUG) echo "NO STATUS RECORD FOUND, GETTING OUT".CRLF;
					return true;
				}
			} else {
				$commandID = COMMAND_ON;						
			}
		}


	} else {
		$commandclassID = COMMAND_CLASS_GENERIC;
	}
	

	$mysql = "SELECT * FROM ha_mf_commands JOIN ha_mf_commands_detail ON ".
			" ha_mf_commands.id=ha_mf_commands_detail.commandID" .
			" WHERE ha_mf_commands.id =".$commandID. " AND commandclassID = ".$commandclassID." AND `inout` IN (".COMMAND_IO_SEND.','.COMMAND_IO_BOTH.')';
	if (!$rowcommands = FetchRow($mysql))  {
		return false;			// error abort
	} 		

	if (MYDEBUG) echo "commandID ".$commandID.CRLF;
	if (MYDEBUG) echo "commandclassID ".$commandclassID.CRLF;
	if (MYDEBUG) echo "value ".$value.CRLF;
	if (MYDEBUG) echo " command ". $rowcommands['command'].CRLF;
	if (MYDEBUG) echo " command value ". $rowcommands['value'].CRLF;
	
	switch ($commandclassID)
	{
	case COMMAND_CLASS_3MFILTRETE:          // Should be internal as well, make setvalue and verbStatus dependent on Caller
		if (MYDEBUG) echo "COMMAND_CLASS_3MFILTRETE</p>";
		$func = $rowcommands['command'];
		$result = $func($callerID, $deviceID, $value);
		$feedback[] = verbStatus($deviceID, $result[0]);
		$feedback[] = setValue($deviceID, $result[1]);
		echo "***";
		print_r($feedback);

		logEvent(Array ('inout' => COMMAND_IO_SEND, 'callerID' => $callerID, 'deviceID' => $deviceID, 'commandID' => $commandID, 'data' => $value, 'result' => $feedback));
		break;
	case COMMAND_CLASS_EMAIL:
		if (MYDEBUG) echo "COMMAND_CLASS_EMAIL".CRLF;
		$rowtext = FetchRow("SELECT * FROM ha_alert_text where id =".$alert_textID);
		$subject= $rowtext['description'];
		$message= $rowtext['message'];
		$myresult = createMail(MAIL_TYPE_SCHEME, Array('deviceID' => $callerparams['deviceID']),$subject,$message);
		$feedback= sendmail($rowcommands['command'], $subject, $message, 'VloHome');
		logEvent(Array ('inout' => COMMAND_IO_SEND, 'callerID' => $callerID, 'deviceID' => $deviceID, 'commandID' => $commandID, 'data' => $value, 'result' => $feedback));
		break;
	case COMMAND_CLASS_INSTEON:
		if (MYDEBUG) echo "COMMAND_CLASS_INSTEON".CRLF;
		$tcomm = str_replace("{commandID}",$commandID,$rowcommands['command']);
		$tcomm = str_replace("{deviceID}",$deviceID,$tcomm);
		$tcomm = str_replace("{unit}",$rowdevices['unit'],$tcomm);
		if ($value>100) $value=100;
		if ($value>0) $value=255/100*$value;
		
		if ($value == NULL && $commandID == COMMAND_ON) $value=255;		// Special case so satify the replace in on command
		$value = dec2hex($value,2);
		if (MYDEBUG) echo "value ".$value.CRLF;
		$tcomm = str_replace("{commandvalue}",$value,$tcomm);
		if (MYDEBUG) echo "Rest deviceID ".$deviceID." commandID ".$commandID.CRLF;
		$url=$rowdevicelinks['targetaddress'].":".$rowdevicelinks['targetport'].$rowdevicelinks['page'].$tcomm.'=I=3';
		if (MYDEBUG) echo $url.CRLF;
		$get = restClient::get($url);
		$feedback = ($get->getresponsecode()==200 ? TRUE : $get->getresponse());
		echo  $get->getresponse().CRLF;
		echo  $get->getresponsecode().CRLF;
		logEvent(Array ('inout' => COMMAND_IO_SEND, 'callerID' => $callerID, 'deviceID' => $deviceID, 'commandID' => $commandID, 'data' => $value, 'result' => $feedback));
		usleep(INSTEON_SLEEP_MICRO);
		if ($feedback !== FALSE) {
			$feedback = verbStatus($deviceID,($commandID == COMMAND_OFF ? STATUS_OFF : STATUS_ON));
			if ($feedback == NULL) $feedback = true;
			UpdateStatus($callerID, $deviceID, $commandID);
		}
		break;
	case COMMAND_CLASS_X10_INSTEON:
		$tcomm = str_replace("{commandID}",$commandID,$rowcommands['command']);
		if ($value>100) $value=100;
		if ($value == NULL && $commandID == COMMAND_ON) $value=100;		// Special case so satify the replace in on command
		$dims = 0;
		if ($value>0 && $value < 100) $dims=(integer)round(10-10/100*$value);
		if (MYDEBUG) echo "value ".$value.CRLF;
		if (MYDEBUG) echo "dims ".$dims.CRLF;
		while($dims > 0) {
			$tcomm .= COMMAND_DIM_CLASS_X10_INSTEON;
			$dims--;
		}
//		$tcomm .={code}a80=I=3;
//		$tcomm .={code}b80=I=3
//		$tcomm .= "|{code}{unit}00=I=3";
//		$tcomm .= "|{code}a80=I=3";
//		$tcomm .= "|0b80=I=3";
//		$tcomm .= "|{code}480=I=3";			// dim 480
//		$tcomm .= "|a780=I=3";				// ext code
//		$tcomm .= "|0b80=I=3";
		$tcomm = str_replace("{code}",$inst_coder->x10_code_encode($rowdevices['code']),$tcomm);
		$tcomm = str_replace("{unit}",$inst_coder->x10_unit_encode($rowdevices['unit']),$tcomm);
		if (MYDEBUG) echo "Rest deviceID ".$deviceID." commandID ".$commandID.CRLF;
		$commands=explode("|", $tcomm);
		//
		// handle dimming, cannot give value so dimming lots of times
		//
		foreach ($commands as $command) {
			$url=$rowdevicelinks['targetaddress'].":".$rowdevicelinks['targetport'].$rowdevicelinks['page'].$command.'=I=3';
			if (MYDEBUG) echo $url.CRLF;
			$get = restClient::get($url);
			$feedback = ($get->getresponsecode()==200 ? TRUE : FALSE);
			usleep(INSTEON_SLEEP_MICRO);
		}     
		logEvent(Array ('inout' => COMMAND_IO_SEND, 'callerID' => $callerID, 'deviceID' => $deviceID, 'commandID' => $commandID, 'data' => $value, 'result' => $feedback));
		if ($feedback) {
			$feedback = verbStatus($deviceID,($commandID == COMMAND_OFF ? STATUS_OFF : STATUS_ON));
			if ($feedback == NULL) $feedback = true;
			UpdateStatus($callerID, $deviceID, $commandID);
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
		if ($commandID ==  COMMAND_ON && $value>0 && $value<100) {
			$x10[0]->Command = "On";
			$x10[0]->CmdData = NULL;
			$x10[0]->Date = date("Y-m-d H:i:s");
			WriteTCP($x10[0]->asXML());
			$x10[0]->Command = "Bright";
			$x10[0]->CmdData = 100;
			$x10[0]->Date = date("Y-m-d H:i:s");
			WriteTCP($x10[0]->asXML());
			$x10[0]->Command = "Dim";
			$x10[0]->CmdData = 100-$value;
			$x10[0]->Date = date("Y-m-d H:i:s");
			WriteTCP($x10[0]->asXML());
		} else {
			$x10[0]->Command = $rowcommands['description'];
			$x10[0]->CmdData = $value;
			$x10[0]->Date = date("Y-m-d H:i:s");
			WriteTCP($x10[0]->asXML());
		}
		CloseTCP("X10");
		$feedback = verbStatus($deviceID,($commandID == COMMAND_OFF ? STATUS_OFF : STATUS_ON));
		if ($feedback == NULL) $feedback = true;
		logEvent(Array ('inout' => COMMAND_IO_SEND, 'callerID' => $callerID, 'deviceID' => $deviceID, 'commandID' => $commandID, 'data' => $value, 'result' => $feedback));
		break;
	default:								// We have a device
		if (MYDEBUG) echo "COMMAND_CLASS_GENERIC</p>";
		$message = null;
		if ($deviceID != NULL) {
			switch ($targettype)
			{
			case "POSTTEXT":          // Only HTPC at the moment
				if (MYDEBUG) echo "POSTTEXT</p>";
				$tcomm = str_replace("{commandID}",$commandID,$rowcommands['command']);
				$tcomm = str_replace("{deviceID}",$deviceID,$tcomm);
				$tcomm = str_replace("{unit}",$rowdevices['unit'],$tcomm);
				$url= $rowdevicelinks['targetaddress'].":".$rowdevicelinks['targetport'].'/'.$rowdevicelinks['page'];
				if (MYDEBUG) echo $url.$tcomm.CRLF;
				$post = restClient::post($url, $tcomm,"","","text/plain");
				$feedback = ($post->getResponseCode()==200 ? $post->getResponse() : FALSE);
				$message = $url.$tcomm;
				break;
			case "GET":          // Sony Cam at the moment
				if (MYDEBUG) echo "GET</p>";
				$tcomm = str_replace("{commandID}",$commandID,$rowcommands['command']);
				$tcomm = str_replace("{deviceID}",$deviceID,$tcomm);
				$tcomm = str_replace("{unit}",$rowdevices['unit'],$tcomm);
				$url= $rowdevicelinks['targetaddress'].":".$rowdevicelinks['targetport'].'/'.$rowdevicelinks['page'];
				if (MYDEBUG) echo $url.$tcomm.CRLF;
				$get = restClient::get($url.$tcomm);
				$feedback = ($get->getResponseCode()==200 ? $get->getResponse() : FALSE);
				$message = $url.$tcomm;
				break;
			case null:
			case "NONE":          // Virtual Devices
				if (MYDEBUG) echo "DOING NOTHING</p>";
				$feedback = true;
				$message = "NOP";
				break;
			}
		}
//		var_dump ($deviceID);
//		var_dump ($targettype);
		
		if ($deviceID == NULL || $targettype == "PHP") {         // Internal PHP commands
			if (MYDEBUG) echo "COMMAND_CLASS_PHP</p>";
			switch ($commandID)
			{
			case COMMAND_RUN_SCHEME:
				$feedback = RunScheme($callerID, $value, $callerparams);
				break;
			default:
				$func = $rowcommands['command'];
				$feedback = $func($value);
				if ($feedback === 0) $feedback = true; // sleep return 0
				break;;
			}
		}
		$result[] = UpdateStatus($callerID, $deviceID, $commandID);
		$result[] = 100;
		if ($deviceID != NULL) {
			if ($rowdevices['monitortypeID']==MONITOR_STATUS || $rowdevices['monitortypeID']==MONITOR_LINK_STATUS) {
				$feedback['buttons'] = verbStatus($deviceID, $result[0]);
				$feedback['buttons'] = setValue($deviceID, $result[1]);
				echo "***";
				print_r($feedback);
			} 
		}
		logEvent(Array ('inout' => COMMAND_IO_SEND, 'callerID' => $callerID, 'deviceID' => $deviceID, 'commandID' => $commandID, 'data' => $value, 'result' => $feedback));
		break;
		
	}
	
	/* Array $feedback ('errorcode' => 0 or code,
						'errormessage', buttons
						Array ("remotekey",
								'status',
								'link'.
								'value') */
	
	if (MYDEBUG) echo "Exit Send".CRLF;
	return $feedback;
} 

function verbStatus ($deviceID, $status) {
// breaks if multiple keys for same device only 1 will be udated
// 
// needs to go to Update Status
// Need to read status meaning based on commandID 
//
		$reskeys = mysql_query("SELECT * FROM ha_remote_keys where deviceID =".$deviceID);
		while ($rowkeys = mysql_fetch_array($reskeys)) {
			if ($rowkeys['inputtype']== "button") {
				$feedback[]["remotekeyID"] = $rowkeys['id'];
				$last_id=key(end($feedback));
				if ($status == STATUS_OFF) {    			// if monitoring status and command not off then new status is on (dim/bright)
					$feedback[$last_id]["status"]="off";
				} elseif ($status == STATUS_UNKNOWN) {
					$feedback[$last_id]["status"]="unknown";
				} elseif ($status == STATUS_ON) {
					$feedback[$last_id]["status"]=" on";
				} else { 										// else assume a value
					$feedback[$last_id]["status"]=" on";
				}				
			}
		}
		echo "verbstatus";
		print_r($feedback);
		
	return $feedback;
}

function setValue ($deviceID, $value) {
// breaks if multiple keys for same device only 1 will be udated
//	$resmonitor = mysql_query("SELECT ha_mf_monitor_status.status FROM ha_mf_monitor_status WHERE ha_mf_monitor_status.deviceID =".$deviceID);
//	$rowmonitor = mysql_fetch_array($resmonitor);
//	if ($rowmonitor) {
		$reskeys = mysql_query("SELECT * FROM ha_remote_keys where deviceID =".$deviceID);
		while ($rowkeys = mysql_fetch_array($reskeys)) {
			if ($rowkeys['inputtype']== "field") {
				$feedback[]["remotekeyID"]=$rowkeys['id'];
				$last_id=key(end($feedback));
				$feedback[$last_id]["value"]=$value;
			}
		}

		print_r($feedback);
		
	return $feedback;
}

function NOP() {return;}
?>
