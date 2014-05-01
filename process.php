<?php 
require_once 'includes.php';

// TODO:: callerparms needed?
// TODO:: clean up feedback , status and return JSON

// define( 'MYDEBUG', TRUE );
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
			$commandvalue= (!empty($_POST["commandvalue"]) ? $_POST["commandvalue"] : 100);
			$mouse = (!empty($_POST["mouse"]) ? $_POST["mouse"] : NULL);
			echo executeCommand($callerID, $messtypeID, array( 'remotekeyID' => $remotekeyID, 'commandID' => $commandID, 'commandvalue' => $commandvalue, 'mouse' => $mouse));
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
	$commandvalue = (array_key_exists('commandvalue', $params) ? $params['commandvalue'] : Null);
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

	logEvent(Array ('inout' => COMMAND_IO_RECV, 'callerID' => $callerID, 'deviceID' => $deviceID, 'commandID' => $tc, 'data' => $commandvalue,
		'message' => $te, 'loglevel' => LOGLEVEL_DEBUG));

		
	global $inst_coder;
	$inst_coder = new InsteonCoder();
	$feedback['messtypeID'] = $messtypeID;
	
	switch ($messtypeID)
	{
	case MESS_TYPE_REMOTE_KEY:    // Key pressed on remote
		$rowkeys = FetchRow("SELECT * FROM ha_remote_keys where id =".$remotekeyID);
		$schemeID = $rowkeys['schemeID'];
		
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
			$feedback['SendCommand'][]=SendCommand($callerID, Array ( 'deviceID' => $rowkeys['deviceID'], 'commandID' => $commandID, 'commandvalue' => $commandvalue), $params);
		} 
		break;
	case MESS_TYPE_SCHEME:
		if (MYDEBUG) echo "MESS_TYPE_SCHEME scheme: ".$schemeID.CRLF;
		break;
	case MESS_TYPE_COMMAND:        
		if (MYDEBUG) echo "MESS_TYPE_COMMAND commandID: ".$commandID." deviceID: ".$deviceID.CRLF;
		$feedback['SendCommand'][]=SendCommand($callerID, Array ( 'deviceID' => $deviceID, 'commandID' => $commandID), $params);
		break;
	}
	
	if ($mouse == 'down') return;
	if ($schemeID>0)  {
		$feedback['RunScheme'][] = RunScheme ($callerID, $schemeID, $params);
	}			
	
	$feedback['show_result'] = false;
	if (!empty($rowkeys)) if ($rowkeys['show_result']) $feedback['show_result'] = true;
			

			
	if (MYDEBUG) echo "Feedback: >";
	if (MYDEBUG) print_r($feedback);
	if (MYDEBUG) echo "executeCommand Exit".CRLF;
	echo "<pre>";
	print_r($feedback);
	echo "</pre>";

	return json_encode($feedback);
			
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
	
	$sqlstr = "SELECT ha_remote_scheme_steps.id, ha_remote_scheme_steps.groupID, ha_remote_scheme_steps.deviceID, ha_remote_scheme_steps.commandID, ha_remote_scheme_steps.value,ha_remote_scheme_steps.sort,ha_remote_scheme_steps.alert_textID ";
	$sqlstr.= " FROM (ha_remote_schemes INNER JOIN ha_remote_scheme_steps ON ha_remote_schemes.id = ha_remote_scheme_steps.schemesID) ";
	$sqlstr.=  "WHERE(((ha_remote_schemes.id) =".$schemeID.")) ORDER BY ha_remote_scheme_steps.sort";
	$resschemesteps	= mysql_query($sqlstr);
	while ($rowshemesteps = mysql_fetch_array($resschemesteps)) {  // loop all steps
		if ($feedback['RunScheme'][]=SendCommand($callerID, Array ( 'deviceID' => $rowshemesteps['deviceID'], 'commandID' => $rowshemesteps['commandID'], 
						'commandvalue' => $rowshemesteps['value'], 'alert_textID' => $rowshemesteps['alert_textID']), $params)) {
		} 
	}

	if (MYDEBUG) echo "Exit RunScheme</pre>".CRLF;
	return $feedback;
}



function SendCommand($callerID, $thiscommand, $callerparams ) { 

	$deviceID = (array_key_exists('deviceID', $thiscommand) ? $thiscommand['deviceID'] : Null);
	$callerparams['deviceID'] = (array_key_exists('deviceID', $callerparams) ? $callerparams['deviceID'] : Null);		// not sending non key to createmail
	if (IsNullOrEmptyString($deviceID)) $deviceID = Null;
	$commandID = (array_key_exists('commandID', $thiscommand) ? $thiscommand['commandID'] : Null);
	$commandvalue = (array_key_exists('commandvalue', $thiscommand) ? $thiscommand['commandvalue'] : 100);
	$alert_textID = (array_key_exists('alert_textID', $thiscommand) ? $thiscommand['alert_textID'] : Null);

	if (MYDEBUG) echo "Enter SendCommand ";
	if (MYDEBUG) print_r($thiscommand);
	if (MYDEBUG) echo "callerID: ".$callerID.CRLF;

	
//
//   Sends 1 single command to TCP, REST, EMAIL
//	
	global $inst_coder;

	// Handles 1 single Device
	$feedback['error'] = 0;
	$feedback['type'] = 'SendCommand';
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
			if ($commandvalue==100) {
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
	if (MYDEBUG) echo "commandvalue ".$commandvalue.CRLF;
	if (MYDEBUG) echo " command ". $rowcommands['command'].CRLF;
	//if (MYDEBUG) echo " command commandvalue ". $rowcommands['commandvalue'].CRLF;
	
	switch ($commandclassID)
	{
	case COMMAND_CLASS_3MFILTRETE:          
		if (MYDEBUG) echo "COMMAND_CLASS_3MFILTRETE</p>";
		$func = $rowcommands['command'];
		$result = $func($callerID, $deviceID, $commandvalue);
		$feedback['error'] = 0; 		// no error checking here?
		logEvent(Array ('inout' => COMMAND_IO_SEND, 'callerID' => $callerID, 'deviceID' => $deviceID, 'commandID' => $commandID, 'data' => $commandvalue, 'result' => $feedback));
		break;
	case COMMAND_CLASS_EMAIL:
		if (MYDEBUG) echo "COMMAND_CLASS_EMAIL".CRLF;
		$rowtext = FetchRow("SELECT * FROM ha_alert_text where id =".$alert_textID);
		$subject= $rowtext['description'];
		$message= $rowtext['message'];
		$myresult = createMail(MAIL_TYPE_SCHEME, Array('deviceID' => $callerparams['deviceID']),$subject,$message);
		$feedback['error'] = (sendmail($rowcommands['command'], $subject, $message, 'VloHome') == true ? false : true);
		logEvent(Array ('inout' => COMMAND_IO_SEND, 'callerID' => $callerID, 'deviceID' => $deviceID, 'commandID' => $commandID, 'data' => $commandvalue, 'result' => $feedback));
		break;
	case COMMAND_CLASS_INSTEON:
		if (MYDEBUG) echo "COMMAND_CLASS_INSTEON".CRLF;
		$tcomm = str_replace("{commandID}",$commandID,$rowcommands['command']);
		$tcomm = str_replace("{deviceID}",$deviceID,$tcomm);
		$tcomm = str_replace("{unit}",$rowdevices['unit'],$tcomm);
		$rowextra = FetchRow('SELECT * FROM `ha_mf_device_extra` WHERE deviceID = '. $deviceID);
		if (!$rowextra['dimmable']) {
			$commandvalue = 100;
		}
		if ($commandvalue>100) $commandvalue=100;
		if ($commandvalue==100 && $commandID == COMMAND_ON) $commandvalue= $rowextra['onlevel'];
		if ($commandvalue>0) $commandvalue=255/100*$commandvalue;
		if ($commandvalue == NULL && $commandID == COMMAND_ON) $commandvalue=255;		// Special case so satify the replace in on command
		$commandvalue = dec2hex($commandvalue,2);
		if (MYDEBUG) echo "commandvalue ".$commandvalue.CRLF;
		$tcomm = str_replace("{commandvalue}",$commandvalue,$tcomm);
		if (MYDEBUG) echo "Rest deviceID ".$deviceID." commandID ".$commandID.CRLF;
		$url=$rowdevicelinks['targetaddress'].":".$rowdevicelinks['targetport'].$rowdevicelinks['page'].$tcomm.'=I=3';
		if (MYDEBUG) echo $url.CRLF;
		$get = restClient::get($url);
		$feedback['error'] = ($get->getresponsecode()==200 ? 0 : $get->getresponse());
		echo  $get->getresponse().CRLF;
		echo  $get->getresponsecode().CRLF;
		logEvent(Array ('inout' => COMMAND_IO_SEND, 'callerID' => $callerID, 'deviceID' => $deviceID, 'commandID' => $commandID, 'data' => $commandvalue, 'result' => $feedback));
		usleep(INSTEON_SLEEP_MICRO);
		if (!$feedback['error']) {
			$result[] = ($commandID == COMMAND_OFF ? STATUS_OFF : STATUS_ON);
			$result[] = $commandvalue;
			UpdateStatus($callerID, array( 'deviceID' => $deviceID, 'commandID' => $commandID));
		}
		break;
	case COMMAND_CLASS_X10_INSTEON:
		$tcomm = str_replace("{commandID}",$commandID,$rowcommands['command']);
		$rowextra = FetchRow('SELECT * FROM `ha_mf_device_extra` WHERE deviceID = '. $deviceID);
		if ($rowextra['dimmable']) {
			$dims = 0;
			if ($commandvalue>0 && $commandvalue < 100) $dims=(integer)round(10-10/100*$commandvalue);
			if (MYDEBUG) echo "commandvalue ".$commandvalue.CRLF;
			if (MYDEBUG) echo "dims ".$dims.CRLF;
			while($dims > 0) {
				$tcomm .= COMMAND_DIM_CLASS_X10_INSTEON;
				$dims--;
			}
		} else {
			$commandvalue = 100;
		}
		if ($commandvalue>100) $commandvalue=100;
		if ($commandvalue==100 && $commandID == COMMAND_ON) $commandvalue= $rowextra['onlevel'];
		if ($commandvalue == NULL && $commandID == COMMAND_ON) $commandvalue=100;		// Special case so satify the replace in on command
//		$tcomm .={code}a80=I=3;	$tcomm .={code}b80=I=3 $tcomm .= "|{code}{unit}00=I=3"; $tcomm .= "|{code}a80=I=3";	$tcomm .= "|0b80=I=3";
//		$tcomm .= "|{code}480=I=3";			// dim 480  $tcomm .= "|a780=I=3";	$tcomm .= "|0b80=I=3";
		$tcomm = str_replace("{code}",$inst_coder->x10_code_encode($rowdevices['code']),$tcomm);
		$tcomm = str_replace("{unit}",$inst_coder->x10_unit_encode($rowdevices['unit']),$tcomm);
		if (MYDEBUG) echo "Rest deviceID ".$deviceID." commandID ".$commandID.CRLF;
		$commands=explode("|", $tcomm);
		//
		// handle dimming, cannot give commandvalue so dimming lots of times
		//
		foreach ($commands as $command) {
			$url=$rowdevicelinks['targetaddress'].":".$rowdevicelinks['targetport'].$rowdevicelinks['page'].$command.'=I=3';
			if (MYDEBUG) echo $url.CRLF;
			$get = restClient::get($url);
			$feedback['error'] = $feedback['error'] || ($get->getresponsecode()==200 ? 0 : $get->getresponsecode());
			if ($feedback['error'] == 0) $feedback['message'] = $get->getresponse();
			usleep(INSTEON_SLEEP_MICRO);
		}     
		logEvent(Array ('inout' => COMMAND_IO_SEND, 'callerID' => $callerID, 'deviceID' => $deviceID, 'commandID' => $commandID, 'data' => $commandvalue, 'result' => $feedback));
		if (!$feedback['error']) {
			$result[] = ($commandID == COMMAND_OFF ? STATUS_OFF : STATUS_ON);
			$result[] = $commandvalue;
			UpdateStatus($callerID, array( 'deviceID' => $deviceID, 'commandID' => $commandID));
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
		logEvent(Array ('inout' => COMMAND_IO_SEND, 'callerID' => $callerID, 'deviceID' => $deviceID, 'commandID' => $commandID, 'data' => $commandvalue, 'result' => $feedback));
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
				$feedback['error'] = $feedback['error'] || ($post->getresponsecode()==200 ? 0 : $post->getresponsecode());
				if ($feedback['error'] == 0) $feedback['message'] = $post->getresponse();
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
				$feedback['error'] = $feedback['error'] || ($get->getresponsecode()==200 ? 0 : $get->getresponsecode());
				if ($feedback['error'] == 0) $feedback['message'] = $get->getresponse();
				$message = $url.$tcomm;
				break;
			case null:
			case "NONE":          // Virtual Devices
				if (MYDEBUG) echo "DOING NOTHING</p>";
				$feedback['error'] =  0;
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
				$feedback['RunScheme'][] = RunScheme($callerID, $commandvalue, $callerparams);
				break;
			default:
				$func = $rowcommands['command'];
				$feedback['error'] = $func($commandvalue);
				break;;
			}
		}
		$result[] = UpdateStatus($callerID, array( 'deviceID' => $deviceID, 'commandID' => $commandID));
		$result[] = 100;
		if ($deviceID != NULL) {
			if ($rowdevices['monitortypeID']==MONITOR_STATUS || $rowdevices['monitortypeID']==MONITOR_LINK_STATUS) {
			} 
		}
		logEvent(Array ('inout' => COMMAND_IO_SEND, 'callerID' => $callerID, 'deviceID' => $deviceID, 'commandID' => $commandID, 'data' => $commandvalue, 'result' => $feedback));
		break;
		
	}
	
	/* Array $feedback ('errorcode' => 0 or code,
						'errormessage', buttons
						Array ("remotekey",
								'status',
								'link'.
								'commandvalue') */
	
	if (MYDEBUG) echo "Exit Send".CRLF;
	
	return $feedback;
} 

function NOP() {return;}
?>
