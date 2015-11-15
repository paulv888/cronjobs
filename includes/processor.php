<?php 
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
	$feedback = Array();
	$commandstr = "";
	
	$exectime = -microtime(true); 
	
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

	$targettype = Null;
	if ($thiscommand['deviceID'] != NULL) {
		if (!$device = getDevice($thiscommand['deviceID'])) return; // not found or not in use continue silently
		$thiscommand['device'] = (array_key_exists('device',$thiscommand) ? array_merge($thiscommand['device'], $device) : $device);
// print_r($thiscommand['device']);
		// if ($thiscommand['device'] = $device) {
			// $feedback['error'] = 'Device '.$thiscommand['deviceID'].' not found or inactive';
			// return false;
		// }
		$thiscommand['device']['previous_properties'] = getDeviceProperties(Array('deviceID' => $thiscommand['deviceID']));
		$targettype = $thiscommand['device']['connection']['targettype'];
		$commandclassID = $thiscommand['device']['commandclassID'];
		if (DEBUG_DEVICES) echo "targettype ".$targettype.CRLF;

		if (array_key_exists('previous_properties', $thiscommand['device'])) {
			if (array_key_exists('Status', $thiscommand['device']['previous_properties'])) {
				$status = $thiscommand['device']['previous_properties']['Status']['value'];
				$rowmonitor = $thiscommand['device']['previous_properties']['Status'];
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
			$onlevel = 100;
			if (array_key_exists('On Level', $thiscommand['device']['previous_properties'])) $onlevel = $thiscommand['device']['previous_properties']['On Level']['value'];
			$dimmable = "NO";
			if (array_key_exists('Dimmable', $thiscommand['device']['previous_properties'])) $dimmable = strtoupper($thiscommand['device']['previous_properties']['Dimmable']['value']);
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

	if ($targettype == 'NONE') $commandclassID = COMMAND_CLASS_GENERIC; // Treat command for devices with no outgoing as virtual, i.e. set day/night to on/off
	if (DEBUG_FLOW || DEBUG_DEVICES) echo "commandID ".$thiscommand['commandID'].CRLF;
	if (DEBUG_FLOW || DEBUG_DEVICES) echo "commandclassID ".$commandclassID.CRLF;
	if (DEBUG_FLOW || DEBUG_DEVICES) echo "commandvalue ".$thiscommand['commandvalue'].CRLF;
	if (DEBUG_FLOW || DEBUG_DEVICES) echo "command ". $rowcommands['command'].CRLF;

	switch ($commandclassID)
	{
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
		global $inst_coder;
		if ($inst_coder instanceof InsteonCoder) {
		} else {
			$inst_coder = new InsteonCoder();
		}
		if (DEBUG_DEVICES) echo "COMMAND_CLASS_INSTEON".CRLF;
		$tcomm = str_replace("{mycommandID}",$thiscommand['commandID'],$rowcommands['command']);
		$tcomm = str_replace("{deviceID}",$thiscommand['deviceID'],$tcomm);
		$tcomm = str_replace("{unit}",$thiscommand['device']['unit'],$tcomm);
		if (DEBUG_DEVICES) echo "commandvalue a".$thiscommand['commandvalue'].CRLF;
		if ($thiscommand['commandvalue']>100) $thiscommand['commandvalue']=100;
		if (DEBUG_DEVICES) echo "commandvalue b".$thiscommand['commandvalue'].CRLF;
		if (empty($thiscommand['commandvalue']) && $thiscommand['commandID'] == COMMAND_ON) $thiscommand['commandvalue']= $onlevel;
		if (DEBUG_DEVICES) echo "commandvalue c".$thiscommand['commandvalue'].CRLF;
		if ($thiscommand['commandvalue']>0) $thiscommand['commandvalue']=255/100*$thiscommand['commandvalue'];
		if (DEBUG_DEVICES) echo "commandvalue d".$thiscommand['commandvalue'].CRLF;
		if ($thiscommand['commandvalue'] == NULL && $thiscommand['commandID'] == COMMAND_ON) $thiscommand['commandvalue']=255;		// Special case so satify the replace in on command
		$commandvalue = dec2hex($thiscommand['commandvalue'],2);
		if (DEBUG_DEVICES) echo "commandvalue ".$commandvalue.CRLF;
		$tcomm = str_replace("{commandvalue}",$commandvalue,$tcomm);
		if (DEBUG_DEVICES) echo "Rest deviceID ".$thiscommand['deviceID']." commandID ".$thiscommand['commandID'].CRLF;
		$url=setURL($thiscommand, $commandstr);
		$commandstr .= $tcomm.'=I=3';
		if (DEBUG_DEVICES) echo $url.CRLF;
		$curl = restClient::get($url.$tcomm.'=I=3',null, "", "", 2);
		if ($curl->getresponsecode() != 200 && $curl->getresponsecode() != 204) 
			$feedback['error'] = $curl->getresponsecode().": ".$curl->getresponse();
		else 
			$feedback['message'] = $curl->getresponse();
		usleep(INSTEON_SLEEP_MICRO);
		if (!array_key_exists('error', $feedback)) {
			$result[] = ($thiscommand['commandID'] == COMMAND_OFF ? STATUS_OFF : STATUS_ON);
			$result[] = $thiscommand['commandvalue'];
			if ($dimmable == "YES") {
				if (!is_null($thiscommand['commandvalue'])) $thiscommand['device']['properties']['Level']['value'] = round(100/255*$thiscommand['commandvalue']);
			}
			$feedback['updateDeviceProperties'] = updateDeviceProperties($thiscommand);
		}
		break;
	case COMMAND_CLASS_X10_INSTEON:
		global $inst_coder;
		if ($inst_coder instanceof InsteonCoder) {
		} else {
			$inst_coder = new InsteonCoder();
		}
		$tcomm = str_replace("{mycommandID}",$thiscommand['commandID'],$rowcommands['command']);
		if ($dimmable == "YES") {
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
		if ($thiscommand['commandvalue']!=100 && $thiscommand['commandID'] == COMMAND_ON) $thiscommand['commandvalue'] = $onlevel;
		if ($thiscommand['commandvalue'] == NULL && $thiscommand['commandID'] == COMMAND_ON) $thiscommand['commandvalue']=100;		// Special case so satify the replace in on command
//		$tcomm .={code}a80=I=3;	$tcomm .={code}b80=I=3 $tcomm .= "|{code}{unit}00=I=3"; $tcomm .= "|{code}a80=I=3";	$tcomm .= "|0b80=I=3";
//		$tcomm .= "|{code}480=I=3";			// dim 480  $tcomm .= "|a780=I=3";	$tcomm .= "|0b80=I=3";
		$tcomm = str_replace("{code}",$inst_coder->x10_code_encode($thiscommand['device']['code']),$tcomm);
		$tcomm = str_replace("{unit}",$inst_coder->x10_unit_encode($thiscommand['device']['unit']),$tcomm);
		if (DEBUG_DEVICES) echo "Rest deviceID ".$thiscommand['deviceID']." commandID ".$thiscommand['commandID'].CRLF;
		$commands=explode("|", $tcomm);
		//
		// handle dimming, cannot give commandvalue so dimming lots of times
		//
		foreach ($commands as $command) {
			//$url=$thiscommand['device']['connection']['targetaddress'].":".$thiscommand['device']['connection']['targetport'].$thiscommand['device']['connection']['page'].$command.'=I=3';
			$url=setURL($thiscommand, $commandstr);
			$commandstr .= $command.'=I=3';
			if (DEBUG_DEVICES) echo $url.CRLF;
			$curl = restClient::get($url.$command.'=I=3');
			if ($curl->getresponsecode() != 200 && $curl->getresponsecode() != 204) 
				$feedback['error'] = $curl->getresponsecode().": ".$curl->getresponse();
			else 
				$feedback['message'] = $curl->getresponse();
			usleep(INSTEON_SLEEP_MICRO);
		}     
		if (!array_key_exists('error', $feedback)){
			$result[] = ($thiscommand['commandID'] == COMMAND_OFF ? STATUS_OFF : STATUS_ON);
			$result[] = $thiscommand['commandvalue'];
			if ($dimmable == "YES") {
				if (!is_null($thiscommand['commandvalue'])) $thiscommand['device']['properties']['Level']['value'] = round(100/255*$thiscommand['commandvalue']);
			}
			$feedback['updateDeviceProperties'] = updateDeviceProperties($thiscommand);
		}
		break;
	case COMMAND_CLASS_3MFILTRETE:          
	case COMMAND_CLASS_GENERIC:	
		if (DEBUG_DEVICES) echo "COMMAND_CLASS_GENERIC/COMMAND_CLASS_3MFILTRETE</p>";
		$func = $rowcommands['command'];
		if ($func == "exit") 
			$exittrap=true;
		elseif ($func == "sleep") {
			$commandstr = $func.' '.$thiscommand['commandvalue'];
			$feedback[$func] = $func($thiscommand['commandvalue']);
		} else {
			$commandstr = $func.' '.json_encode($thiscommand);
			$feedback[$func] = $func($thiscommand);
			if  (array_key_exists('error', $feedback[$func])) {
				$thiscommand['commandvalue'] = $feedback[$func]['error']; // Commandvalue so it will end up in data for log
			} elseif (array_key_exists('Name', $feedback[$func])) {
				$thiscommand['commandvalue'] = $feedback[$func]['Name'];
			} elseif (array_key_exists('error', $feedback[$func]) && is_string($feedback[$func]['error'])) {
				$thiscommand['commandvalue'] = $feedback[$func]['error'];
			} elseif (array_key_exists('message', $feedback[$func]) && is_string($feedback[$func]['message'])) {
				$thiscommand['commandvalue'] = $feedback[$func]['message'];
			}
		}
		if ($rowcommands['need_device']) {
			$feedback['updateDeviceProperties'] = updateDeviceProperties($thiscommand);
		}
		break;
	default:								// Everything else Ard/Sony/Cam/Irrigation/Virtual Devices
		if (DEBUG_DEVICES) echo "COMMAND_CLASS_OTHER</p>";
		switch ($targettype)
		{
		case "POSTAPP":          // PHP - vlosite
		case "POSTTEXT":         // Only HTPC & IrrigationCaddy at the moment
		case "POSTURL":          // Web Arduino
		case "JSON":             // Wink
			if (DEBUG_DEVICES) echo $targettype."</p>";
			$tcomm = str_replace("{mycommandID}",trim($thiscommand['commandID']),$rowcommands['command']);
			$tcomm = str_replace("{deviceID}",trim($thiscommand['deviceID']),$tcomm);
			$tcomm = str_replace("{unit}",trim($thiscommand['device']['unit']),$tcomm);
			$tcomm = str_replace("{commandvalue}",trim($thiscommand['commandvalue']),$tcomm);
			$tcomm = str_replace("{timervalue}",trim($thiscommand['timervalue']),$tcomm);
			$tmp1 = explode('?', $tcomm);
			if (array_key_exists('1', $tmp1)) { 	// found '?' inside command then take page from command string and add to url
				$thiscommand['device']['connection']['page'] .= $tmp1[0];
				$tcomm = $tmp1[1];
			} 
			$url=setURL($thiscommand, $commandstr);
			
			if (DEBUG_DEVICES) echo $url." Params: ".$tcomm.CRLF;
			if ($targettype == "POSTTEXT") { 
				$commandstr .= ' '.$tcomm;
				$curl = restClient::post($url, $tcomm, "", "", "text/plain", 2);
			} elseif ($targettype == "POSTAPP") {
				$commandstr .= ' '.$tcomm;
				$curl = restClient::post($url, $tcomm, "", "", "", 2);
			} elseif ($targettype == "JSON") {
				parse_str($tcomm, $params);
				if (DEBUG_DEVICES) echo $url." Params: ".json_encode($params).CRLF;
				$commandstr .= ' '.json_encode($params);
				$curl = restClient::post($url, json_encode($params), "", "", "application/json" , 2);
			} else { 
				$commandstr .= $tcomm;
				$curl = restClient::post($url.$tcomm,"","","","",2);
			}
			if ($curl->getresponsecode() != 200 && $curl->getresponsecode() != 204) 
				$feedback['error'] = $curl->getresponsecode().": ".$curl->getresponse();
			else 
				$feedback['message'] = preg_replace('/\s+/',' ',preg_replace('~\R~',' ',strip_tags($curl->getresponse())));
				if (array_key_exists('message',$feedback) && $feedback['message'] == "\n[]") unset($feedback['message']); //  TODO:: Some crap coming back from winkapi, fix later
			break;
		case "GET":          // Sony Cam at the moment
			if (DEBUG_DEVICES) echo "GET</p>";
			$tcomm = str_replace("{mycommandID}",$thiscommand['commandID'],$rowcommands['command']);
			$tcomm = str_replace("{deviceID}",$thiscommand['deviceID'],$tcomm);
			$tcomm = str_replace("{unit}",$thiscommand['device']['unit'],$tcomm);
			$tcomm = str_replace("{commandvalue}",trim($thiscommand['commandvalue']),$tcomm);
			$tcomm = str_replace("{timervalue}",trim($thiscommand['timervalue']),$tcomm);
			// $url= $thiscommand['device']['connection']['targetaddress'].":".$thiscommand['device']['connection']['targetport'].'/'.$thiscommand['device']['connection']['page'];
			$url=setURL($thiscommand, $commandstr);
			if (DEBUG_DEVICES) echo $url.$tcomm.CRLF;
			$commandstr .= $tcomm;
			$curl = restClient::get($url.$tcomm);
			if ($curl->getresponsecode() != 200 && $curl->getresponsecode() != 204)
				$feedback['error'] = $curl->getresponsecode().": ".$curl->getresponse();
			else 
				$feedback['message'] = $curl->getresponse();
			break;
		case null:
		case "NONE":          // Virtual Devices
			if (DEBUG_DEVICES) echo "DOING NOTHING</p>";
			break;
		}
		if (!is_null($thiscommand['commandvalue']) && trim($thiscommand['commandvalue'])!=='') $thiscommand['device']['properties']['Value']['value']= $thiscommand['commandvalue'];
		$feedback['updateDeviceProperties'] = updateDeviceProperties($thiscommand);
		break;		
	}
	
	$exectime += microtime(true);

	logEvent(array('inout' => COMMAND_IO_SEND, 'callerID' => $callerparams['callerID'], 'deviceID' => $thiscommand['deviceID'], 'commandID' => $thiscommand['commandID'], 'data' => $thiscommand['commandvalue'], 'message' => $feedback, 'loglevel' => $thiscommand['loglevel'], 'commandstr' => $commandstr, 'exectime' => $exectime));
	if ($exittrap) exit($thiscommand['commandvalue']);
	
	if (DEBUG_FLOW) echo "Exit Send</pre>".CRLF;
	
	return $feedback;
} 
?>