<?php 
require_once 'includes.php';

define( 'MYDEBUG', TRUE );
if (!defined('MYDEBUG')) define( 'MYDEBUG', FALSE );


if (isset($_POST["callsource"])) {						// All have to tell where they are from.

	$callsource=$_POST["callsource"];
	switch ($callsource)
	{
	case SIGNAL_SOURCE_REMOTE:    									// Key pressed on remote
		if (isset($_POST["remotekey"])) {							// Called with key number
			$callsource = SIGNAL_SOURCE_REMOTE_BUTTON;
			$remotekeyID=$_POST["remotekey"];
			$setvalue=(!empty($_POST["setvalue"]) ? $_POST["setvalue"] : 100);
			$commandID=(!empty($_POST["command"]) ? $_POST["command"] : NULL);
			$mouse = (!empty($_POST["mouse"]) ? $_POST["mouse"] : NULL);
			$schemeID = NULL;
			if (substr($commandID, 0,1)=="S") {
				$callsource = SIGNAL_SOURCE_REMOTE_SCHEME;
				$schemeID = substr($commandID, 1);								// **** Remote can send S12, schemeID as well.
				if (MYDEBUG) echo "REMOTE SCHEME ".$commandID."</p>";
			}	
			echo process($callsource, array( 'remotekeyID' => $remotekeyID, 'commandID' => $commandID, 'schemeID' => $schemeID, 'setvalue' => $setvalue, 'mouse' => $mouse));
			exit;
		}
		break;
	case SIGNAL_SOURCE_SCHEME:												// Call from Macro List
		if (isset($_POST["scheme"])) {							
			$schemeID=$_POST["scheme"];
			if (MYDEBUG) echo "SIGNAL_SOURCE_SCHEME ".$schemeID."</p>";
			echo process($callsource, array( 'schemeID' => $schemeID));
			exit;
		}
		break;
	case SIGNAL_SOURCE_COMMAND:												// !!! not in use
		if (isset($_POST["command"])) {										// Internal, then device not required
			$commandID=$_POST["command"];
			$deviceID=(!empty($_POST["device"]) ? $_POST["device"] : NULL);
			if (MYDEBUG) echo "SIGNAL_SOURCE_COMMAND ".$commandID."</p>";
			var_dump($deviceID);
			echo process($callsource, array( 'commandID' => $commandID, 'deviceID' => $deviceID ));
			exit;
		}
		break;
	}
}

/*
	Process can execute following commands
		1) single X10 command to HTPC over TCPbridge
		2) post to a Rest service 
		3) send an eMail
		4) execute a scheme with any of the above commands
		
	Called from 
		1) inet Remote, Simple, $remotekeyID is the remotekeyID and command optionally is commandID direct,(used for dropdowns)
		2) trade Alerts, command = T12, schemeID (source updatetrades.php)   (Not implemented)
		3) HA Alerts, command = NULL , Alerts has alertID to process (source createalerts.php) (Simple emails are handled locally)
					Changed to direct call from createalerts.
	
	Old interface from Remote (Still in use, depricate)
		1) remotekey !& command = remotekeyID of button pressed											-> execute remotekey or scheme attached
		2) remotekey & command = dropdowns, key=dropdown field and command is selection of dropdown, 	-> execute command
		3) 
*/

//function process($callsource, $remotekeyID = NULL, $commandID = NULL, $alertID = NULL, $setvalue = NULL, $mouse=NULL) {
function process($callsource, $params) {
// ALERTS 				process(SIGNAL_SOURCE_HA_ALERT, array ( 'alertID' => $rowalerts['ha_alerts_id']));
// STATUS/LINK CHANGE  	process(SIGNAL_SOURCE_STATUS_LINK_UPDATE, array ( 'deviceID' => $deviceID, 'schemeID' => $row['on_change']))."\n";
// LOCAL				process($callsource, array( 'remotekeyID' => $remotekeyID, 'commandID' => $commandID, 'setvalue' => $setvalue, 'mouse' => $mouse));
// LOCAL				process($callsource, array( 'remotekeyID' => $remotekeyID, 'commandID' => $commandID, 'setvalue' => $setvalue));
// LOCAL				process($callsource, array( 'commandID' => $commandID));

	/* Get the Keys Schema or Device */
	$deviceID = (array_key_exists('deviceID', $params) ? $params['deviceID'] : Null);
	if (IsNullOrEmptyString($deviceID)) $deviceID = Null;
	$alertID = (array_key_exists('alertID', $params) ? $params['alertID'] : Null);
	$schemeID = (array_key_exists('schemeID', $params) ? $params['schemeID'] : Null);
	$remotekeyID = (array_key_exists('remotekeyID', $params) ? $params['remotekeyID'] : Null);
	$commandID = (array_key_exists('commandID', $params) ? $params['commandID'] : Null);
	$setvalue = (array_key_exists('setvalue', $params) ? $params['setvalue'] : Null);
	$mouse = (array_key_exists('mouse', $params) ? $params['mouse'] : Null);

	
	if (MYDEBUG) echo "<pre>Entry Process";
	if (MYDEBUG) print_r($params);
	
	global $inst_coder;
	$inst_coder = new InsteonCoder();
	$feedback = "";
	//$schemeID = 0;
	
	switch ($callsource)
	{
	case SIGNAL_SOURCE_REMOTE_BUTTON:    // Key pressed on remote
		$reskeys = mysql_query("SELECT * FROM ha_remote_keys where id =".$remotekeyID);
		$rowkeys = mysql_fetch_array($reskeys);
		$schemeID=$rowkeys['schemeID'];
		
		if ($schemeID <=0) {  													// not a scheme, execute here now
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
			if ($result=SendCommand($callsource, $rowkeys['deviceID'], $commandID,  $setvalue)) {
				$feedback .= $result;
			} else {
				$feedback = false;
			}
		} 
		break;
	case SIGNAL_SOURCE_SCHEME:
	case SIGNAL_SOURCE_REMOTE_SCHEME:        // Received S12 
	case SIGNAL_SOURCE_STATUS_LINK_UPDATE: 	  // 
		//$schemeID = $commandID;
		if (MYDEBUG) echo "SIGNAL_SOURCE_REMOTE_SCHEME or SIGNAL_SOURCE_STATUS_LINK_UPDATE or SIGNAL_SOURCE_SCHEME scheme: ".$schemeID."</p>";
		break;
	case SIGNAL_SOURCE_HA_ALERT:       	 // process from alerts
		$sqlstr = "SELECT ha_alerts.id, ha_alerts_dd.schemeID FROM ha_alerts LEFT JOIN ha_alerts_dd ON ha_alerts.alertID = ha_alerts_dd.id WHERE (ha_alerts.id =".$alertID.")";
		if (MYDEBUG) echo $sqlstr."</p>";
		$resalerts = mysql_query($sqlstr);
		$rowalerts = mysql_fetch_array($resalerts);
		$schemeID = $rowalerts['schemeID'];
		break;
	case SIGNAL_SOURCE_TRADE_ALERT:        // process from trade alerts
		break;
	case SIGNAL_SOURCE_COMMAND:        
		if (MYDEBUG) echo "SIGNAL_SOURCE_COMMAND commandID: ".$commandID." deviceID: ".$deviceID."</p>";
		if ($result=SendCommand($callsource, $deviceID, $commandID)) {
			$feedback .= $result;
		} else {
			$feedback = false;
		}
		break;
	}
	
	if ($mouse == 'down') return;
	if ($schemeID>0)  {
		$result = RunScheme ( $schemeID, $callsource);
		if (!is_bool($result)) $feedback .= $result;				// do not convert true's to 1's
	}			

	
	if (!empty($rowkeys)) 
		if ($rowkeys['show_result']) 
			return $feedback;
	
	if (MYDEBUG) echo "Feedback: >".$feedback."<";
	if (MYDEBUG) echo "</pre>Process Exit\n";

	return ($feedback || strlen($feedback) == 0 ? "OK;".$feedback : false);
			
}


function RunScheme($schemeID, $callsource = SIGNAL_SOURCE_REMOTE_SCHEME, $alertID = NULL) {      // its a scheme, process steps. Scheme setup by a) , b) derived from remotekey, c) derived from alerts

// Check conditions
	// logEvent(Array ('inout' => COMMAND_SEND, 'sourceID' => $callsource, 'commandID' => $commandID, 'data' => $value, 'result' => $feedback));
	// create a command for start schema

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
			$devstatusrow = FetchRow("SELECT status FROM ha_vw_monitor_combined  WHERE deviceID = ".$rowcond['deviceID']);
			$testvalue = $devstatusrow['status'];
			$condvalue = $rowcond['status'];
			if ($condvalue !== $testvalue) {
				if (MYDEBUG) echo "Condition fail: confd:".$condvalue." ,test: ".$testvalue."</p>";
				return true;
			}
			break;
		case SCHEME_CONDITION_TIME: 
			if (MYDEBUG) echo "SCHEME_CONDITION_TIME</p>";
			echo "Not Implemented</p>";
			break;
		}
		if (MYDEBUG) echo "Condition Pass: confd".$condvalue." ,test: ". $testvalue."</p>";
	}
	
	$sqlstr = "SELECT ha_remote_scheme_steps.id, ha_remote_scheme_steps.deviceID, ha_remote_scheme_steps.commandID, ha_remote_scheme_steps.value,ha_remote_scheme_steps.sort,ha_remote_scheme_steps.alert_textID ";
	$sqlstr.= " FROM (ha_remote_schemes INNER JOIN ha_remote_scheme_steps ON ha_remote_schemes.id = ha_remote_scheme_steps.schemesID) ";
	$sqlstr.=  "WHERE(((ha_remote_schemes.id) =".$schemeID.")) ORDER BY ha_remote_scheme_steps.sort";
	$resschemesteps	= mysql_query($sqlstr);
	$feedback = '';
	while ($rowshemesteps = mysql_fetch_array($resschemesteps)) {  // loop all steps
		if ($result=SendCommand($callsource, $rowshemesteps['deviceID'], $rowshemesteps['commandID'], 
				(!IsNullOrEmptyString($rowshemesteps['value']) ? $rowshemesteps['value'] : NULL),
					$alertID,($rowshemesteps['alert_textID']>0 ? $rowshemesteps['alert_textID'] : 0))) {
			if (!is_bool($result)) $feedback .= $result;				// do not convert true's to 1's
		} else {
			$feedback = false;
		}
	}
	//var_dump($feedback);
	if (strlen($feedback) == 0) return true;
	return $feedback;
}



function SendCommand($callsource, $deviceID = NULL, $commandID = NULL,  $value = 100, $alertID = NULL, $alert_textID = NULL) { 
//ALL SCHEME's						if ($result=SendCommand($callsource, $rowshemesteps['deviceID'], $rowshemesteps['commandID'],
//										(!IsNullOrEmptyString($rowshemesteps['value']) ? $rowshemesteps['value'] : NULL),
//										$alertID,($rowshemesteps['alert_textID']>0 ? $rowshemesteps['alert_textID'] : 0))) 
//SIGNAL_SOURCE_COMMAND				if ($result=SendCommand($callsource, $deviceID, $commandID)) {
//SIGNAL_SOURCE_REMOTE_BUTTON		if ($result=SendCommand($callsource, $rowkeys['deviceID'], $commandID,  $setvalue)) {



//
//   Sends 1 single command to TCP, REST, EMAIL
//	
	global $inst_coder;

	// Handles 1 single Device
	if ($deviceID != NULL) {
		$resdevices = mysql_query("SELECT * FROM ha_mf_devices where id =".$deviceID.' AND inuse= 1');
		if (!$rowdevices = mysql_fetch_array($resdevices)) return;
		$resdevicelinks = mysql_query("SELECT * FROM ha_mf_device_links where id =".$rowdevices['devicelinkID']);
		$rowdevicelinks = mysql_fetch_array($resdevicelinks);
		$commandclassID = $rowdevices['commandclassID'];
		if (MYDEBUG) echo "device ".$deviceID."</p>";
		if (MYDEBUG) echo "targettype ".$rowdevicelinks['targettype']."</p>";

		if ($commandID==COMMAND_TOGGLE) {   // Special handling for toggle
			if ($value==100) {
				$resmonitor = mysql_query("SELECT ha_mf_monitor_status.status FROM ha_mf_monitor_status WHERE ha_mf_monitor_status.deviceID =".$deviceID);
				$rowmonitor = mysql_fetch_array($resmonitor);
				if ($rowmonitor) {
					if (MYDEBUG) echo "Toggling Old Status ".$rowmonitor['status']."</p>";
					$commandID = ($rowmonitor['status'] == STATUS_ON ? COMMAND_OFF : COMMAND_ON); // toggle on/off
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
	$rescommands = mysql_query($mysql);
	if (!$rowcommands = mysql_fetch_array($rescommands))  {
		mySqlError($mysql);
		return false;			// error abort
	} elseif (!mysql_num_rows($rescommands)) {
			return true;		// device not in use or command not found, just skip it
	}		

	if (MYDEBUG) echo "commandID ".$commandID."</p>";
	if (MYDEBUG) echo "commandclassID ".$commandclassID."</p>";
	if (MYDEBUG) echo "value ".$value."</p>";
	if (MYDEBUG) echo " command ". $rowcommands['command']."</p>";
	if (MYDEBUG) echo " command value ". $rowcommands['value']."</p>";
	
	switch ($commandclassID)
	{
	case COMMAND_CLASS_3MFILTRETE:          // Should be internal as well, make setvalue and verbStatus dependent on Caller
		if (MYDEBUG) echo "COMMAND_CLASS_3MFILTRETE</p>";
		$func = $rowcommands['command'];
		$result = $func($deviceID, $value);
		$feedback = verbStatus($deviceID, $result[0]);
		$feedback .= setValue($deviceID, $result[1]);
		logEvent(Array ('inout' => COMMAND_IO_SEND, 'sourceID' => $callsource, 'deviceID' => $deviceID, 'commandID' => $commandID, 'data' => $value, 'result' => $feedback));
		break;
	case COMMAND_CLASS_EMAIL:
		if (MYDEBUG) echo "COMMAND_CLASS_EMAIL alertID ".$alertID." alert_textID ".$alert_textID."</p>";
		$restext = mysql_query("SELECT * FROM ha_alert_text where id =".$alert_textID);
		$rowtext = mysql_fetch_array($restext);
			
		$subject= $rowtext['description'];
		$message= $rowtext['message'];
		$myresult = createMail($callsource,$alertID,$subject,$message);
		var_dump($myresult);
		$feedback= sendmail($rowcommands['command'], $subject, $message, 'VloHome');
		var_dump($feedback);
		logEvent(Array ('inout' => COMMAND_IO_SEND, 'sourceID' => $callsource, 'deviceID' => $deviceID, 'commandID' => $commandID, 'data' => $value, 'result' => $feedback));
		break;
	case COMMAND_CLASS_INSTEON:
		if (MYDEBUG) echo "COMMAND_CLASS_INSTEON"."</p>";
		$tcomm = str_replace("{commandID}",$commandID,$rowcommands['command']);
		$tcomm = str_replace("{deviceID}",$deviceID,$tcomm);
		$tcomm = str_replace("{unit}",$rowdevices['unit'],$tcomm);
		if ($value>100) $value=100;
		if ($value>0) $value=255/100*$value;
		
		if ($value == NULL && $commandID == COMMAND_ON) $value=255;		// Special case so satify the replace in on command
		$value = dec2hex($value,2);
		if (MYDEBUG) echo "value ".$value."</p>";
		$tcomm = str_replace("{commandvalue}",$value,$tcomm);
		if (MYDEBUG) echo "Rest deviceID ".$deviceID." commandID ".$commandID."</p>";
		$url=$rowdevicelinks['targetaddress'].":".$rowdevicelinks['targetport'].$rowdevicelinks['page'].$tcomm.'=I=3';
		if (MYDEBUG) echo $url."</p>";
		$get = restClient::get($url);
		$feedback = ($get->getresponsecode()==200 ? TRUE : FALSE);
		logEvent(Array ('inout' => COMMAND_IO_SEND, 'sourceID' => $callsource, 'deviceID' => $deviceID, 'commandID' => $commandID, 'data' => $value, 'result' => $feedback));
		usleep(INSTEON_SLEEP_MICRO);
		if ($feedback !== FALSE) {
			$feedback = verbStatus($deviceID,($commandID == COMMAND_OFF ? STATUS_OFF : STATUS_ON));
			if ($feedback == NULL) $feedback = true;
			UpdateStatus($callsource, $deviceID, $commandID);
		}
		break;
	case COMMAND_CLASS_X10_INSTEON:
		$tcomm = str_replace("{commandID}",$commandID,$rowcommands['command']);
		if ($value>100) $value=100;
		if ($value == NULL && $commandID == COMMAND_ON) $value=100;		// Special case so satify the replace in on command
		$dims = 0;
		if ($value>0 && $value < 100) $dims=(integer)round(10-10/100*$value);
		if (MYDEBUG) echo "value ".$value."</p>";
		if (MYDEBUG) echo "dims ".$dims."</p>";
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
		if (MYDEBUG) echo "Rest deviceID ".$deviceID." commandID ".$commandID."</p>";
		$commands=explode("|", $tcomm);
		//
		// handle dimming, cannot give value so dimming lots of times
		//
		foreach ($commands as $command) {
			$url=$rowdevicelinks['targetaddress'].":".$rowdevicelinks['targetport'].$rowdevicelinks['page'].$command.'=I=3';
			if (MYDEBUG) echo $url."</p>";
			$get = restClient::get($url);
			$feedback = ($get->getresponsecode()==200 ? TRUE : FALSE);
			usleep(INSTEON_SLEEP_MICRO);
		}     
		logEvent(Array ('inout' => COMMAND_IO_SEND, 'sourceID' => $callsource, 'deviceID' => $deviceID, 'commandID' => $commandID, 'data' => $value, 'result' => $feedback));
		if ($feedback) {
			$feedback = verbStatus($deviceID,($commandID == COMMAND_OFF ? STATUS_OFF : STATUS_ON));
			if ($feedback == NULL) $feedback = true;
			UpdateStatus($callsource, $deviceID, $commandID);
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
		// TODO: if device not on remote verbStatus gives null, halting processing schema/alert
		if ($feedback == NULL) $feedback = true;
		//echo "feedback***" . $feedback."***</p>";
		// handled in TCP bridge
		//UpdateStatus($deviceID,($commandID == COMMAND_OFF ? STATUS_OFF : STATUS_ON));
		logEvent(Array ('inout' => COMMAND_IO_SEND, 'sourceID' => $callsource, 'deviceID' => $deviceID, 'commandID' => $commandID, 'data' => $value, 'result' => $feedback));
		break;
	default:								// We have a device
		if (MYDEBUG) echo "COMMAND_CLASS_GENERIC</p>";
		if ($deviceID != NULL) {
			switch ($rowdevicelinks['targettype'])
			{
			case "POSTTEXT":          // Only HTPC at the moment
				if (MYDEBUG) echo "POSTTEXT</p>";
				$tcomm = str_replace("{commandID}",$commandID,$rowcommands['command']);
				$tcomm = str_replace("{deviceID}",$deviceID,$tcomm);
				$tcomm = str_replace("{unit}",$rowdevices['unit'],$tcomm);
				$url= $rowdevicelinks['targetaddress'].":".$rowdevicelinks['targetport'].'/'.$rowdevicelinks['page'];
				if (MYDEBUG) echo $url.$tcomm."</p>";
				$post = restClient::post($url, $tcomm,"","","text/plain");
				$feedback = ($post->getresponsecode()==200 ? $post->getresponse() : FALSE);
				//logEvent(Array ('inout' => COMMAND_IO_SEND, 'sourceID' => $callsource, 'deviceID' => $deviceID, 'commandID' => $commandID, 'data' => $value, 'result' => $feedback));
				break;
			case "GET":          // Sony Cam at the moment
				if (MYDEBUG) echo "GET</p>";
				$tcomm = str_replace("{commandID}",$commandID,$rowcommands['command']);
				$tcomm = str_replace("{deviceID}",$deviceID,$tcomm);
				$tcomm = str_replace("{unit}",$rowdevices['unit'],$tcomm);
				$url= $rowdevicelinks['targetaddress'].":".$rowdevicelinks['targetport'].'/'.$rowdevicelinks['page'];
				if (MYDEBUG) echo $url.$tcomm."</p>";
				$get = restClient::get($url.$tcomm);
				$feedback = ($get->getresponsecode()==200 ? $get->getresponse() : FALSE);
				//logEvent(Array ('inout' => COMMAND_IO_SEND, 'sourceID' => $callsource, 'deviceID' => $deviceID, 'commandID' => $commandID, 'data' => $value, 'result' => $feedback));
				break;
			case "NONE":          // Virtual Devices
				if (MYDEBUG) echo "DOING NOTHING</p>";
				$feedback = true;
				//logEvent(Array ('inout' => COMMAND_IO_SEND, 'sourceID' => $callsource, 'deviceID' => $deviceID, 'commandID' => $commandID, 'data' => $value, 'result' => $feedback));
				break;
			}
		}
		var_dump ($deviceID);
		var_dump ($rowdevicelinks['targettype']);
		
		if ($deviceID == NULL || $rowdevicelinks['targettype'] == "PHP") {         // Internal PHP commands
			if (MYDEBUG) echo "COMMAND_CLASS_PHP</p>";
			$func = $rowcommands['command'];
			$feedback = $func($value);
			if ($feedback === 0) $feedback = true; // sleep return 0
		}
		$result[] = UpdateStatus( $callsource, $deviceID, $commandID);
		$result[] = 100;
		$feedback = "";
		if ($deviceID != NULL) {
			if ($rowdevices['monitortypeID']==MONITOR_STATUS || $rowdevices['monitortypeID']==MONITOR_LINK_STATUS) {
				$feedback = verbStatus($deviceID, $result[0]);
				$feedback .= setValue($deviceID, $result[1]);
			} 
		}
		logEvent(Array ('inout' => COMMAND_IO_SEND, 'sourceID' => $callsource, 'deviceID' => $deviceID, 'commandID' => $commandID, 'data' => $value, 'result' => $feedback));
		break;
		
	}
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
				if ($status == STATUS_OFF) {    			// if monitoring status and command not off then new status is on (dim/bright)
					$feedback[$rowkeys['id']]=$rowkeys['id']." off";
				} elseif ($status == STATUS_UNKNOWN) {
					$feedback[]=$rowkeys['id']." unknown";
				} elseif ($status == STATUS_ON) {
					$feedback[]=$rowkeys['id']." on";				
				} else { 										// else assume a value
					$feedback[]=$rowkeys['id']." on";
				}				
			}
		}
	if (!empty($feedback))
		return implode(";", $feedback).";";
	else 
		return;
}

function setValue ($deviceID, $value) {
// breaks if multiple keys for same device only 1 will be udated
//	$resmonitor = mysql_query("SELECT ha_mf_monitor_status.status FROM ha_mf_monitor_status WHERE ha_mf_monitor_status.deviceID =".$deviceID);
//	$rowmonitor = mysql_fetch_array($resmonitor);
//	if ($rowmonitor) {
		$reskeys = mysql_query("SELECT * FROM ha_remote_keys where deviceID =".$deviceID);
		while ($rowkeys = mysql_fetch_array($reskeys)) {
			if ($rowkeys['inputtype']== "field") {
				$feedback[]=$rowkeys['id']." val: $value";
			}
		}
	if (!empty($feedback))
		return implode(";", $feedback).";";
	else 
		return;
}

function NOP() {return;}
?>
