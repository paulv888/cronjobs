<?php 
require_once 'includes.php';

define( 'MYDEBUG', FALSE );
//define( 'MYDEBUG', TRUE );

// 
// Called with remotekey or 
//                with a dropdown selected remotekey and command = selected
//                no schemes supported with dropdowns 
//  
if (isset($_POST["callsource"])) {						// Called from remote with key number

	$callsource=$_POST["callsource"];
	switch ($callsource)
	{
	case SIGNAL_SOURCE_REMOTE:    									// Key pressed on remote
		if (isset($_POST["remotekey"])) {							// Called with key number
			$callsource = SIGNAL_SOURCE_REMOTE_BUTTON;
			$remotekeyid=$_POST["remotekey"];
			$setvalue=$_POST["setvalue"];
			$commandid=$_POST["command"];
			$mouse=$_POST["mouse"];
			if (substr($commandid, 0,1)=="S") {
				$callsource = SIGNAL_SOURCE_REMOTE_SCHEME;
				$commandid = substr($commandid, 1);								// **** Remote can send S12, Schemeid as well.
				if (MYDEBUG) echo "REMOTE SCHEME ".$commandid."</p>";
			}	
			echo process($callsource, $remotekeyid, $commandid, $alertid, $setvalue, $mouse);
			exit;
		}
		if (isset($_POST["command"])) {							// Called with direct command,PHP stuff CLASS internal (Need to add call source)
			$commandid=$_POST["command"];
			if (substr($commandid, 0,1)=="S") {
				$callsource = SIGNAL_SOURCE_REMOTE_SCHEME;
				$commandid = substr($commandid, 1);								// **** Remote can send S12, Schemeid as well.
				if (MYDEBUG) echo "REMOTE SCHEME ".$commandid."</p>";
			}	
			echo process($callsource, $remotekeyid, $commandid, $alertid, $setvalue);
			exit;
		}
		break;
	}
}
if (isset($_POST["command"])) {							// Called with direct command,PHP stuff CLASS internal (Need to add call source)
	$callsource = SIGNAL_SOURCE_COMMAND;
	$commandid=$_POST["command"];
	echo process($callsource, NULL, $commandid);
	exit;
}

/*
	Process can execute following commands
		1) single X10 command to HTPC over TCPbridge
		2) post to a Rest service 
		3) send an eMail
		4) execute a scheme with any of the above commands
		
	Called from 
		1) inet Remote, Simple, $remotekeyid is the remotekeyid and command optionally is commandid direct,(used for dropdowns)
		2) trade Alerts, command = T12, Schemeid (source updatetrades.php)   (Not implemented)
		3) HA Alerts, command = NULL , Alerts has AlertId to process (source createalerts.php) (Simple emails are handled locally)
					Changed to direct call from createalerts.
	
	Old interface from Remote (Still in use, depricate)
		1) remotekey !& command = remotekeyid of button pressed											-> execute remotekey or scheme attached
		2) remotekey & command = dropdowns, key=dropdown field and command is selection of dropdown, 	-> execute command
		3) 
*/

function process($callsource, $remotekeyid = NULL, $commandid = NULL, $alertid = NULL, $setvalue = NULL, $mouse=NULL) {
	/* Get the Keys Schema or Device */
	
	global $inst_coder;
	$inst_coder = new InsteonCoder();
	
	switch ($callsource)
	{
	case SIGNAL_SOURCE_REMOTE_BUTTON:    // Key pressed on remote
		$reskeys = mysql_query("SELECT * FROM ha_remote_keys where id =".$remotekeyid);
		$rowkeys = mysql_fetch_array($reskeys);
		$schemeid=$rowkeys['scheme'];
		
		if ($schemeid <=0) {  													// not a scheme, execute here now
			if ($commandid===NULL) {
				if ($mouse=='down') { 
					$commandid=$rowkeys['commandIDdown'];
				} else {
					$commandid=$rowkeys['commandID'];
				}
			}  		
			if ($commandid==COMMAND_TOGGLE) {   // Special handling for toggle
				if ($setvalue==100) {
					$resmonitor = mysql_query("SELECT ha_mf_monitor_status.status FROM ha_mf_monitor_status WHERE ha_mf_monitor_status.deviceID =".$rowkeys['deviceID']);
					$rowmonitor = mysql_fetch_array($resmonitor);
					if ($rowmonitor) {
						$commandid = ($rowmonitor['status'] == STATUS_ON ? COMMAND_OFF : COMMAND_ON); // toggle on/off
					}
				} else {
						$commandid = COMMAND_ON;						
				}
			}
			if ($result=SendCommand($callsource, $rowkeys['deviceID'], $commandid,  $setvalue)) {
				$feedback .= $result;
			} else {
				$feedback = FALSE;
			}
		} 
		break;
	case SIGNAL_SOURCE_REMOTE_SCHEME:        // Received S12 
		$schemeid = $commandid;
		if (MYDEBUG) echo "SIGNAL_SOURCE_REMOTE_SCHEME ".$schemeid."</p>";
		break;
	case SIGNAL_SOURCE_HA_ALERT:       	 // process from alerts
		$sqlstr = "SELECT ha_alerts.id, ha_alerts_dd.schemeid FROM ha_alerts LEFT JOIN ha_alerts_dd ON ha_alerts.alertid = ha_alerts_dd.id WHERE (ha_alerts.id =".$alertid.")";
		if (MYDEBUG) echo $sqlstr."</p>";
		$resalerts = mysql_query($sqlstr);
		$rowalerts = mysql_fetch_array($resalerts);
		$schemeid = $rowalerts['schemeid'];
		break;
	case SIGNAL_SOURCE_TRADE_ALERT:        // process from trade alerts
		break;
	case SIGNAL_SOURCE_COMMAND:        
		if (MYDEBUG) echo "SIGNAL_SOURCE_COMMAND ".$commandid."</p>";
		if ($result=SendCommand($callsource, NULL, $commandid)) {
			$feedback .= $result;
		} else {
			$feedback = FALSE;
		}
		break;
	}
	
	
	if ($schemeid>0)  $feedback .= RunScheme ( $schemeid, $callsource);
	
	if ($rowkeys) 
		if ($rowkeys['show_result']) 
			return $feedback;
	
	return ($feedback ? "OK;".$feedback : false);
			
}


function RunScheme($schemeid, $callsource = SIGNAL_SOURCE_REMOTE_SCHEME, $alertid = NULL) {      // its a scheme, process steps. Scheme setup by a) , b) derived from remotekey, c) derived from alerts

// Check conditions
	preg_match ( "/^[1-9][0-9]*/", $schemeid, $matches);
	$schemeid = $matches[0];
	
	$mysql = 'SELECT * FROM `ha_remote_scheme_conditions` WHERE `schemesID` = '.$schemeid;
	
	if (!$rescond = mysql_query($mysql)) {
		mySqlError($mysql); 
		exit;
	}
	
	while ($rowcond = mysql_fetch_assoc($rescond)) {	
		switch ($rowcond['type'])
		{
		case SCHEME_CONDITION_DEVICE_STATUS: 
			if (MYDEBUG) echo "SCHEME_CONDITION_DEVICE_STATUS</p>";
			echo "Not Implemented</p>";
			break;
		case SCHEME_CONDITION_SYSTEM_STATUS: 
			if (MYDEBUG) echo "SCHEME_CONDITION_SYSTEM_STATUS</p>";
			$rowconf = FetchRow("SELECT * FROM `ha_configuration` WHERE id = 1");
			$condvalue = $rowcond['status'];
			switch ($rowcond['system'])
			{
			case SYSTEM_STATUS_ARE_HOME: 
				if (MYDEBUG) echo "SYSTEM_STATUS_ARE_HOME</p>";
				$testvalue = $rowconf['are_home'];
				break;
			case SYSTEM_STATUS_ALARM_ARMED: 
				if (MYDEBUG) echo "SYSTEM_STATUS_ALARM_ARMED</p>";
				$testvalue = $rowconf['alarm_armed'];
				break;
			case SYSTEM_STATUS_IS_DARK: 
				if (MYDEBUG) echo "SYSTEM_STATUS_IS_DARK</p>";
				$testvalue = $rowconf['is_dark'];
				break;
			case SYSTEM_STATUS_PAUL_TRIP: 
				if (MYDEBUG) echo "SYSTEM_STATUS_PAUL_TRIP</p>";
				$testvalue = $rowconf['paul_trip'];
				break;
			}
			if ($condvalue <> $testvalue) {
				if (MYDEBUG) echo "Condition fail: confd". $condvalue. " ,test: ". $testvalue. "<>".$confvalue !== $testvalue."</p>";
				return 1;
			}
			break;
		case SCHEME_CONDITION_TIME: 
			if (MYDEBUG) echo "SCHEME_CONDITION_TIME</p>";
			echo "Not Implemented</p>";
			break;
		}
	}

	if (MYDEBUG) echo "Condition Pass: confd". $condvalue. " ,test: ". $testvalue. "==".$confvalue == $testvalue."</p>";

	
	$sqlstr = "SELECT ha_remote_scheme_steps.id, ha_remote_scheme_steps.deviceID, ha_remote_scheme_steps.commandID, ha_remote_scheme_steps.value,ha_remote_scheme_steps.sort,ha_remote_scheme_steps.alert_textID ";
	$sqlstr.= " FROM (ha_remote_schemes INNER JOIN ha_remote_scheme_steps ON ha_remote_schemes.id = ha_remote_scheme_steps.schemesID) ";
	$sqlstr.=  "WHERE(((ha_remote_schemes.id) =".$schemeid.")) ORDER BY ha_remote_scheme_steps.sort";
	$resschemesteps	= mysql_query($sqlstr);
	$feedback = '';
	while ($rowshemesteps = mysql_fetch_array($resschemesteps)) {  // loop all steps
		if ($result=SendCommand($callsource, $rowshemesteps['deviceID'], $rowshemesteps['commandID'],  (!IsNullOrEmptyString($rowshemesteps['value']) ? $rowshemesteps['value'] : NULL),$alertid,($rowshemesteps['alert_textID']>0 ? $rowshemesteps['alert_textID'] : 0))) {
			$feedback .= $result;
		} else {
			$feedback = FALSE;
		}
	}
	return $feedback;
}



function SendCommand($callsource, $deviceid = NULL, $commandid = NULL,  $value = NULL, $alertid = NULL, $alert_textID = NULL) { 
//
//   Sends 1 single command to TCP, REST, EMAIL
//	
	global $inst_coder;

	// Handles 1 single Device
	if ($deviceid != NULL) {
		$resdevices = mysql_query("SELECT * FROM ha_mf_devices where id =".$deviceid);
		$rowdevices = mysql_fetch_array($resdevices);
		$resdevicelinks = mysql_query("SELECT * FROM ha_mf_device_links where id =".$rowdevices['devicelink']);
		$rowdevicelinks = mysql_fetch_array($resdevicelinks);
		$commandclassid = $rowdevices['commandclassid'];
		if (MYDEBUG) echo "device ".$deviceid."</p>";
		if (MYDEBUG) echo "targettype ".$rowdevicelinks['targettype']."</p>";
	} else {
		$commandclassid = COMMAND_CLASS_INTERNAL;
	}
	
	$rescommands = mysql_query("SELECT * FROM ha_mf_commands JOIN ha_mf_commands_detail ON ".
			" ha_mf_commands.id=ha_mf_commands_detail.commandid" .
			" WHERE ha_mf_commands.id =".$commandid. " AND commandclassid = ".$commandclassid." AND inuse = 1");
	if (!$rowcommands = mysql_fetch_array($rescommands))  {
		mySqlError($mysql);
		return false;			// error abort
	} elseif (!mysql_num_rows($rescommands)) {
			return true;		// device not in use or command not found, just skip it
	}		

	if (MYDEBUG) echo "commandid ".$commandid."</p>";
	if (MYDEBUG) echo "commandclassid ".$commandclassid."</p>";
	if (MYDEBUG) echo "value ".$value."</p>";
	if (MYDEBUG) echo " command ". $rowcommands['command']."</p>";
	
	switch ($commandclassid)
	{
	case COMMAND_CLASS_3MFILTRETE:          // Should be internal as well, make setvalue and verbStatus dependent on Caller
		if (MYDEBUG) echo "COMMAND_CLASS_3MFILTRETE</p>";
		$func = $rowcommands['command'];
		$result = $func($deviceid, $value);
		$feedback = verbStatus($deviceid, $result[0]);
		$feedback .= setValue($deviceid, $result[1]);
		logEvent(Array ('inout' => COMMAND_SEND, 'sourceID' => $callsource, 'deviceID' => $deviceid, 'commandID' => $commandid, 'data' => $value, 'result' => $feedback));
		break;
	case COMMAND_CLASS_EMAIL:
		if (MYDEBUG) echo "COMMAND_CLASS_EMAIL alertid ".$alertsid." alert_textID ".$alert_textID."</p>";
		$restext = mysql_query("SELECT * FROM ha_alert_text where id =".$alert_textID);
		$rowtext = mysql_fetch_array($restext);
			
		$subject= $rowtext['description'];
		$message= $rowtext['message'];
		$myresult = createMail($callsource,$alertid,$subject,$message);
		$feedback= sendmail($rowcommands['command'], $subject, $message, 'VloHome');
		logEvent(Array ('inout' => COMMAND_SEND, 'sourceID' => $callsource, 'deviceID' => $deviceid, 'commandID' => $commandid, 'data' => $value, 'result' => $feedback));
		break;
	case COMMAND_CLASS_INSTEON:
		$tcomm = str_replace("{commandid}",$commandid,$rowcommands['command']);
		$tcomm = str_replace("{deviceid}",$deviceid,$tcomm);
		$tcomm = str_replace("{unit}",$rowdevices['unit'],$tcomm);
		if ($value>100) $value=100;
		if ($value>0) $value=255/100*$value;
		if ($value == NULL && $commandid == COMMAND_ON) $value=255;		// Special case so satify the replace in on command
		$value = dec2hex($value,2);
		if (MYDEBUG) echo "value ".$value."</p>";
		$tcomm = str_replace("{value}",$value,$tcomm);
		if (MYDEBUG) echo "Rest deviceid ".$deviceid." commandid ".$commandid."</p>";
		$url=$rowdevicelinks['targetaddress'].":".$rowdevicelinks['targetport'].$rowdevicelinks['page'].$tcomm;
		if (MYDEBUG) echo $url."</p>";
		$get = restClient::get($url);
		$feedback = ($get->getresponsecode()==200 ? TRUE : FALSE);
		logEvent(Array ('inout' => COMMAND_SEND, 'sourceID' => $callsource, 'deviceID' => $deviceid, 'commandID' => $commandid, 'data' => $value, 'result' => $feedback));
		usleep(INSTEON_SLEEP_MICRO);
		if ($feedback !== FALSE) {
			$feedback = verbStatus($deviceid,($commandid == COMMAND_OFF ? STATUS_OFF : STATUS_ON));
			if ($feedback == NULL) $feedback = true;
			UpdateStatus($deviceid, $commandid, $callsource);
		}
		break;
	case COMMAND_CLASS_X10_INSTEON:
		$tcomm = str_replace("{commandid}",$commandid,$rowcommands['command']);
		if ($value>100) $value=100;
		if ($value == NULL && $commandid == COMMAND_ON) $value=100;		// Special case so satify the replace in on command
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
		//$tcomm .= "|0b80=I=3";
		$tcomm = str_replace("{code}",$inst_coder->x10_code_encode($rowdevices['code']),$tcomm);
		$tcomm = str_replace("{unit}",$inst_coder->x10_unit_encode($rowdevices['unit']),$tcomm);
		if (MYDEBUG) echo "Rest deviceid ".$deviceid." commandid ".$commandid."</p>";
		$commands=explode("|", $tcomm);
		//
		// handle dimming, cannot give value so dimming lots of times
		//
		foreach ($commands as $command) {
			$url=$rowdevicelinks['targetaddress'].":".$rowdevicelinks['targetport'].$rowdevicelinks['page'].$command;
			if (MYDEBUG) echo $url."</p>";
			$get = restClient::get($url);
			$feedback = ($get->getresponsecode()==200 ? TRUE : FALSE);
			usleep(INSTEON_SLEEP_MICRO);
		}     
		logEvent(Array ('inout' => COMMAND_SEND, 'sourceID' => $callsource, 'deviceID' => $deviceid, 'commandID' => $commandid, 'data' => $value, 'result' => $feedback));
		if ($feedback) {
			$feedback = verbStatus($deviceid,($commandid == COMMAND_OFF ? STATUS_OFF : STATUS_ON));
			if ($feedback == NULL) $feedback = true;
			UpdateStatus($deviceid, $commandid, $callsource);
		}
		break;
	case COMMAND_CLASS_X10:
		$xmlfile="X10Command.xml";
		$x10 = simplexml_load_file($xmlfile);
		OpenTCP($rowdevicelinks['targetaddress'], $rowdevicelinks['targetport'],"X10");
		$x10[0]->CallerID = "web";
		$x10[0]->Operation = "send";
		$x10[0]->Sender = "plc";
		$x10[0]->HouseCode = $rowdevices['code'];
		$x10[0]->Unit = $rowdevices['unit'];
		if ($commandid ==  COMMAND_ON && $value>0 && $value<100) {
			$x10[0]->Command = "On";
			$x10[0]->CmdData = NULL;
			$x10[0]->GMTTime = mygmdate("Y-m-d H:i:s");
			WriteTCP($x10[0]->asXML());
			$x10[0]->Command = "Bright";
			$x10[0]->CmdData = 100;
			$x10[0]->GMTTime = mygmdate("Y-m-d H:i:s");
			WriteTCP($x10[0]->asXML());
			$x10[0]->Command = "Dim";
			$x10[0]->CmdData = 100-$value;
			$x10[0]->GMTTime = mygmdate("Y-m-d H:i:s");
			WriteTCP($x10[0]->asXML());
		} else {
			$x10[0]->Command = $rowcommands['description'];
			$x10[0]->CmdData = $value;
			$x10[0]->GMTTime = mygmdate("Y-m-d H:i:s");
			WriteTCP($x10[0]->asXML());
		}
		CloseTCP("X10");
		$feedback = verbStatus($deviceid,($commandid == COMMAND_OFF ? STATUS_OFF : STATUS_ON));
		// TODO: if device not on remote verbStatus gives null, halting processing schema/alert
		if ($feedback == NULL) $feedback = true;
		//echo "feedback***" . $feedback."***</p>";
		// handled in TCP bridge
		//UpdateStatus($deviceid,($commandid == COMMAND_OFF ? STATUS_OFF : STATUS_ON));
		logEvent(Array ('inout' => COMMAND_SEND, 'sourceID' => $callsource, 'deviceID' => $deviceid, 'commandID' => $commandid, 'data' => $value, 'result' => $feedback));
		break;
	case COMMAND_CLASS_INTERNAL:
		$func = $rowcommands['command'];
		if (MYDEBUG) echo "COMMAND_CLASS_INTERNAL deviceid ".$deviceid." command ". $rowcommands['command']." value ". $value."</p>";
		$feedback = $func($value);
		if ($feedback === 0) $feedback = 1; // sleep return 0
		logEvent(Array ('inout' => COMMAND_SEND, 'sourceID' => $callsource, 'deviceID' => $deviceid, 'commandID' => $commandid, 'data' => $value, 'result' => $feedback));
		break;
	default:
		if (MYDEBUG) echo "COMMAND_CLASS_NO_SPECIFIC</p>";
		switch ($rowdevicelinks['targettype'])
		{
		case "POSTTEXT":          // Only HTPC at the moment
			if (MYDEBUG) echo "POSTTEXT</p>";
			$tcomm = str_replace("{commandid}",$commandid,$rowcommands['command']);
			$tcomm = str_replace("{deviceid}",$deviceid,$tcomm);
			$tcomm = str_replace("{unit}",$rowdevices['unit'],$tcomm);
			$url= $rowdevicelinks['targetaddress'].":".$rowdevicelinks['targetport'].'/'.$rowdevicelinks['page'];
			if (MYDEBUG) echo $url.$tcomm."</p>";
			$post = restClient::post($url, $tcomm,"","","text/plain");
			$feedback = ($post->getresponsecode()==200 ? $post->getresponse() : FALSE);
			logEvent(Array ('inout' => COMMAND_SEND, 'sourceID' => $callsource, 'deviceID' => $deviceid, 'commandID' => $commandid, 'data' => $value, 'result' => $feedback));
			break;
		case "GET":          // Sony Cam at the moment
			if (MYDEBUG) echo "GET</p>";
			$tcomm = str_replace("{commandid}",$commandid,$rowcommands['command']);
			$tcomm = str_replace("{deviceid}",$deviceid,$tcomm);
			$tcomm = str_replace("{unit}",$rowdevices['unit'],$tcomm);
			$url= $rowdevicelinks['targetaddress'].":".$rowdevicelinks['targetport'].'/'.$rowdevicelinks['page'];
			if (MYDEBUG) echo $url.$tcomm."</p>";
			$get = restClient::get($url.$tcomm);
			$feedback = ($get->getresponsecode()==200 ? $post->getresponse() : FALSE);
			logEvent(Array ('inout' => COMMAND_SEND, 'sourceID' => $callsource, 'deviceID' => $deviceid, 'commandID' => $commandid, 'data' => $value, 'result' => $feedback));
			break;
		}
		break;
	}
	return $feedback;
} 

function verbStatus ($deviceid, $status) {
// breaks if multiple keys for same device only 1 will be udated
//	$resmonitor = mysql_query("SELECT ha_mf_monitor_status.status FROM ha_mf_monitor_status WHERE ha_mf_monitor_status.deviceID =".$deviceid);
//	$rowmonitor = mysql_fetch_array($resmonitor);
//	if ($rowmonitor) {
		$reskeys = mysql_query("SELECT * FROM ha_remote_keys where deviceID =".$deviceid);
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


function setValue ($deviceid, $value) {
// breaks if multiple keys for same device only 1 will be udated
//	$resmonitor = mysql_query("SELECT ha_mf_monitor_status.status FROM ha_mf_monitor_status WHERE ha_mf_monitor_status.deviceID =".$deviceid);
//	$rowmonitor = mysql_fetch_array($resmonitor);
//	if ($rowmonitor) {
		$reskeys = mysql_query("SELECT * FROM ha_remote_keys where deviceID =".$deviceid);
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
?>