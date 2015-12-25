<?php 
// define( 'DEBUG_INPUT', TRUE );
//define( 'DEBUG_FLOW', TRUE );
// define( 'DEBUG_DEVICES', TRUE );
// define( 'DEBUG_RETURN', TRUE );
if (isset($_POST['DEBUG_INPUT'])) define( 'DEBUG_INPUT', TRUE );
if (isset($_POST['DEBUG_FLOW'])) define( 'DEBUG_FLOW', TRUE );
if (isset($_POST['DEBUG_DEVICES'])) define( 'DEBUG_DEVICES', TRUE );
if (isset($_POST['DEBUG_RETURN'])) define( 'DEBUG_RETURN', TRUE );
if (!defined('DEBUG_INPUT')) define( 'DEBUG_INPUT', FALSE );
if (!defined('DEBUG_FLOW')) define( 'DEBUG_FLOW', FALSE );
if (!defined('DEBUG_DEVICES')) define( 'DEBUG_DEVICES', FALSE );
if (!defined('DEBUG_RETURN')) define( 'DEBUG_RETURN', FALSE );

// Private
function sendCommand($thiscommand) { 

	$exittrap=false;
	$callerparams = (array_key_exists('caller', $thiscommand) ? $thiscommand['caller'] : Array());
	$thiscommand['loglevel'] = (array_key_exists('loglevel', $thiscommand['caller']) ? $thiscommand['caller']['loglevel'] : Null);
	$thiscommand['deviceID'] = (array_key_exists('deviceID', $thiscommand) ? $thiscommand['deviceID'] : Null);
	if (IsNullOrEmptyString($thiscommand['deviceID'])) $thiscommand['deviceID'] = Null;
	$thiscommand['commandID'] = (array_key_exists('commandID', $thiscommand) ? $thiscommand['commandID'] : Null);
	$thiscommand['commandvalue'] = (array_key_exists('commandvalue', $thiscommand) ? $thiscommand['commandvalue'] : Null);
	$thiscommand['timervalue'] = (array_key_exists('timervalue', $thiscommand) ? $thiscommand['timervalue'] : 0);
	$thiscommand['schemeID'] = (array_key_exists('schemeID', $thiscommand) ? $thiscommand['schemeID'] : Null);
	$thiscommand['alert_textID'] = (array_key_exists('alert_textID', $thiscommand) ? $thiscommand['alert_textID'] : Null);
	$thiscommand['priorityID']  = (array_key_exists('priorityID', $thiscommand) ? $thiscommand['priorityID'] : 'NULL');

	$feedback = Array();
	$feedback['result'] = '';
	$params['commandstr'] = "";
	
	$exectime = -microtime(true); 
	
	if ($thiscommand['commandID'] == null) {
		$feedback['error'] = "No Command given";
		return $feedback;			// error abort
	}

	if (DEBUG_FLOW || DEBUG_DEVICES) {
		echo "<pre>Enter SendCommand ".CRLF;
		echo "This Command: ";
		if ($ct = FetchRow("SELECT description FROM ha_mf_commands  WHERE ha_mf_commands.id =".$thiscommand['commandID']))  {
			echo '<b>'.$ct['description'].'</b>'.CRLF;			// error abort
		} 
		print_r($thiscommand);
	}
	
//
//   Sends 1 single command to TCP, REST, EMAIL
//	

	if ($thiscommand['deviceID'] != NULL) {
		if (!$device = getDevice($thiscommand['deviceID'])) return; // not found or not in use continue silently
		$thiscommand['device'] = (array_key_exists('device',$thiscommand) ? array_merge($thiscommand['device'], $device) : $device);
		// print_r($thiscommand['device']);
		// if ($thiscommand['device'] = $device) {
		// $feedback['error'] = 'Device '.$thiscommand['deviceID'].' not found or inactive';
		// return false;
		// }
		$thiscommand['device']['previous_properties'] = getDeviceProperties(Array('deviceID' => $thiscommand['deviceID']));
		$commandclassID = $thiscommand['device']['commandclassID'];
		// if (DEBUG_DEVICES) echo "ThisCommand: DeviceID: ";
		// if (DEBUG_DEVICES) print_r($thiscommand);
		
		if (array_key_exists('previous_properties', $thiscommand['device'])) {
			$statusarr = search($thiscommand['device']['previous_properties'], 'primary_status', 1);
			// Just take the first one 
			if (array_key_exists(0, $statusarr)) {
				$status_key = $statusarr[0]['description'];
				$status = $thiscommand['device']['previous_properties'][$status_key]['value'];
				$rowmonitor = $thiscommand['device']['previous_properties'][$status_key];
				// Special handling for toggle
				if ($thiscommand['commandID']==COMMAND_TOGGLE) {   
					if ($thiscommand['commandvalue'] > 0 && $thiscommand['commandvalue'] < 100) { // if dimvalue given then update dim, else toggle
						$thiscommand['commandID'] = COMMAND_ON;						
					} else {
						if ($rowmonitor) {
							if (DEBUG_DEVICES) echo "Status Toggle: ".$status.CRLF;
							$thiscommand['commandID'] = ($status == STATUS_ON ? COMMAND_OFF : COMMAND_ON); // toggle on/off
						} else {		// not status monitoring 
							if (DEBUG_DEVICES) echo "NO STATUS RECORD FOUND, GETTING OUT".CRLF;
							return;
						}
					}
				}
				if ($thiscommand['commandID']==COMMAND_DIM || $thiscommand['commandID']==COMMAND_BRIGHTEN) {
					if ($status != STATUS_OFF) {
						if ($thiscommand['commandID']==COMMAND_DIM) {
							$thiscommand['commandvalue'] = $thiscommand['device']['previous_properties']['Level']['value'] - $thiscommand['commandvalue'];
							if ($thiscommand['commandvalue'] < 0) $thiscommand['commandID'] = COMMAND_OFF;
						} else {
							$thiscommand['commandvalue'] = $thiscommand['device']['previous_properties']['Level']['value'] + $thiscommand['commandvalue'];
							if ($thiscommand['commandvalue'] > 100) $thiscommand['commandvalue'] = 100;
						}
					} else {
						if (DEBUG_DEVICES) echo "STATUS IS OFF, NOT DIMMING, GETTING OUT".CRLF;
						return;
					}
				}
			}
			$thiscommand['onlevel'] = 100;
			if (array_key_exists('On Level', $thiscommand['device']['previous_properties'])) $thiscommand['onlevel'] = $thiscommand['device']['previous_properties']['On Level']['value'];
			$thiscommand['dimmable'] = "NO";
			if (array_key_exists('Dimmable', $thiscommand['device']['previous_properties'])) $thiscommand['dimmable'] = strtoupper($thiscommand['device']['previous_properties']['Dimmable']['value']);
		}
		// Invert Status is set
		if (isset($rowmonitor) && $rowmonitor['invertstatus'] == "0") {  
			if (DEBUG_DEVICES) echo "Status Invert: ".$status.CRLF;
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
	$thiscommand['command'] = $rowcommands['command'];

	// Load Message Template
	if (!empty($thiscommand['alert_textID'])) {
		if (DEBUG_DEVICES) echo "COMMAND_PREP_ALERT".CRLF;
		$rowtext = FetchRow("SELECT * FROM ha_alert_text where id =".$thiscommand['alert_textID']);
		$thiscommand['mess_subject'] = $rowtext['subject'];
		$thiscommand['mess_text'] = $rowtext['message'];
		if ($rowtext['priorityID'] != Null) $thiscommand['priorityID']= $rowtext['priorityID'];
		if (strlen($thiscommand['mess_text']) == 0) $thiscommand['mess_text'] = " ";
		replaceText($thiscommand);
	}	
	
	if (array_key_exists('device', $thiscommand) && $thiscommand['device']['connection']['targettype'] == 'NONE') $commandclassID = COMMAND_CLASS_GENERIC; // Treat command for devices with no outgoing as virtual, i.e. set day/night to on/off
	if (DEBUG_FLOW || DEBUG_DEVICES) echo "commandID ".$thiscommand['commandID'].CRLF;
	if (DEBUG_FLOW || DEBUG_DEVICES) echo "commandclassID ".$commandclassID.CRLF;
	if (DEBUG_FLOW || DEBUG_DEVICES) echo "commandvalue ".$thiscommand['commandvalue'].CRLF;
	if (DEBUG_FLOW || DEBUG_DEVICES) echo "command ".$thiscommand['command'].CRLF;

	switch ($commandclassID)
	{
	case COMMAND_CLASS_INSTEON:
		if (DEBUG_DEVICES) echo "COMMAND_CLASS_INSTEON".CRLF;
		$feedback = sendInsteonCommand($thiscommand);
		break;
	case COMMAND_CLASS_X10_INSTEON:
		if (DEBUG_DEVICES) echo "COMMAND_CLASS_X10".CRLF;
		$feedback = sendX10Command($thiscommand);
		break;
	case COMMAND_CLASS_3MFILTRETE:          
	case COMMAND_CLASS_GENERIC:	
	case COMMAND_CLASS_EMAIL:
		if (DEBUG_DEVICES) echo "COMMAND_CLASS_GENERIC/COMMAND_CLASS_3MFILTRETE/EMAIL</p>";
		if ($thiscommand['command'] == "exit") 
			$exittrap=true;
		else {
			$feedback = sendGenericPHP($thiscommand);
		}
		break;
	default:								// Everything else Ard/Sony/Cam/Irrigation/Virtual Devices
		if (DEBUG_DEVICES) echo "COMMAND_CLASS_OTHER</p>";
		$feedback = sendGenericHTTP($thiscommand);
		break;		
	}

	// Check for errors first?
	if ($rowcommands['need_device']) {
		$feedback['updateDeviceProperties'] = updateDeviceProperties($thiscommand);
	}
	
	$exectime += microtime(true);
	
	// Generic error parsing
	//sendmail
//	if (array_key_exists('error', $result)) $feedback['result']['error'] = $result['error'];
	
	if (DEBUG_RETURN) echo "<pre>End sendCommandd: >";
	if (DEBUG_RETURN) print_r($feedback);
	if (DEBUG_RETURN) echo "</pre>".CRLF;
	
	if (!array_key_exists('message', $feedback)) $feedback['message']='';
	logEvent(array('inout' => COMMAND_IO_SEND, 'callerID' => $callerparams['callerID'], 'deviceID' => $thiscommand['deviceID'], 'commandID' => $thiscommand['commandID'], 'data' => $thiscommand['commandvalue'], 'message'=> $feedback['message'], 'result' => $feedback, 'loglevel' => $thiscommand['loglevel'], 'commandstr' => $params['commandstr'], 'exectime' => $exectime));
	if ($exittrap) exit($thiscommand['commandvalue']);
	
	if (DEBUG_FLOW) echo "Exit Send</pre>".CRLF;
	return $feedback;
} 
?>
