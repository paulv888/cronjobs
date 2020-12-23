<?php
function replaceCommandPlaceholders($stepValue, $params) {
//
//  For outgoing command strings and macro stepvalues
//		in: $stepValue
//		out: return

	if (strpos($stepValue,'{') === false) {
		debug("return", 'return');
		return $stepValue;
	}

	debug($stepValue, 'stepValue');
	debug($params, 'params');

	if (strpos($stepValue, "{SERVER_HOME}") !== false) $stepValue = str_replace("{SERVER_HOME}",SERVER_HOME,$stepValue);
	if (strpos($stepValue, "{mycommandID}") !== false) $stepValue = str_replace("{mycommandID}",trim($params['commandID']),$stepValue);
	if (strpos($stepValue, "{deviceID}") !== false) $stepValue = str_replace("{deviceID}",trim($params['deviceID']),$stepValue);
	if (strpos($stepValue, "{caller___deviceID}") !== false) $stepValue = str_replace("{caller___deviceID}",trim($params['callerparams']['deviceID']),$stepValue);
	if (strpos($stepValue, "{unit}") !== false) $stepValue = str_replace("{unit}",trim($params['device']['unit']),$stepValue);

	if (strpos($stepValue,"{port}") !== false) {
		$port = getProperty($params['propertyID'])['port'];
		if (is_null($port)) {
			echo "Empty Port found!!!";
			exit;
		}
		$stepValue = str_replace("{port}",$port,$stepValue);
	}
	if (strpos($stepValue, "{macro___commandvalue}") !== false) $stepValue = 
			str_replace("{macro___commandvalue}", (array_key_exists('macro___commandvalue', $params) ? trim($params['macro___commandvalue']) : ""),$stepValue);
	if (strpos($stepValue, "{last___message}") !== false) $stepValue = 
			str_replace("{last___message}", (array_key_exists('last___message', $params) ? trim($params['last___message']) : ""),$stepValue);
	if (strpos($stepValue, "{last___result___") !== false) $stepValue = parseLastResult($params['last___result'], $stepValue);
	if (strpos($stepValue, "{commandvalue}") !== false) $stepValue = str_replace("{commandvalue}",trim($params['commandvalue']),$stepValue);
	if (strpos($stepValue, "{value}") !== false) $stepValue = str_replace("{value}",trim($params['value']),$stepValue);
	if (strpos($stepValue, "{timervalue}") !== false) $stepValue = str_replace("{timervalue}",trim($params['timervalue']),$stepValue);
	if (preg_match("/\{calculate___(.*?)\}/", $stepValue,$matches)) {
		debug($matches, 'calculate->matches');

		$calcvalue = eval('return '.$matches[1].';');
		$stepValue = str_replace($matches[0], $calcvalue, $stepValue);
	}

	if (array_key_exists('mess_subject',$params)) $stepValue = str_replace("{mess_subject}",trim($params['mess_subject']),$stepValue);
	if (array_key_exists('mess_text',$params)) $stepValue = str_replace("{mess_text}",trim($params['mess_text']),$stepValue);

	$stepValue = replaceText($params, $stepValue);
	debug($stepValue, 'stepValue');

	return $stepValue;
}

function replaceText($params, $field){
//
//		For Alerts
//		in:  $params['mess_subject'], $params['mess_text']
//		out: $params['mess_subject'], $params['mess_text']
//
	debug($field, 'field');
	debug($params, 'params');

	// $params['deviceID'] = (array_key_exists('deviceID', $params) ? $params['deviceID'] : 'NULL');

	//
	// Find better place for this
	//
	if (array_key_exists('caller', $params)) {
		if ($cd = FetchRow("SELECT description FROM ha_mf_devices WHERE ha_mf_devices.id =".$params['caller']['callerID']))  {
			$params['caller___description']= $cd['description']; 
		}
		if (array_key_exists('deviceID', $params['caller']) && !empty($params['caller']['deviceID'])) {
			if ($cd = FetchRow("SELECT description FROM ha_mf_devices WHERE ha_mf_devices.id =".$params['caller']['deviceID'])) {
				$params['caller___device___description']= $cd['description'];
			}
			$params['caller___device___property'] = getDeviceProperties(Array( 'deviceID' => $params['caller']['deviceID']));
		}
	}
	
	if ($params['deviceID'] != null && $params['deviceID'] == DEVICE_SELECTED_PLAYER) {
		$params['deviceID'] = $params['SESSION']['properties']['SelectedPlayer']['value'];
	}

	if (strpos($field,'{') === false) {
		debug("return", 'return');
		return $field;
	}

	
	$params['now'] = date("Y-m-d H:i:s");

	$flat_params = array_flatten($params, 1);

	for ($i = 1; $i <= 1; $i++) {		// Only once???

		unset ($pattern);
		foreach ($flat_params as $key => $value) {
			if (is_array($value)) unset($flat_params[$key]);
			$pattern[$key]="/\{".strtolower(str_replace(" ","_",$key))."\}/";
		}

		debug ($flat_params, 'flat_params');
		debug ($pattern, 'pattern');

		$field = preg_replace($pattern, $flat_params, $field);
	}

	debug($field, 'result->field');

	return $field;
	
}

function replacePropertyPlaceholders($mess_subject, $params){
//
//		For replacing property names {property___abc}
//		in: $mess_subject
//		out: return
//
	debug($mess_subject, 'mess_subject');
	debug($params, 'params');

	if (strpos($mess_subject,'{') === false) {
		debug("return", 'return');
	return $mess_subject;
	}
	
	$mess_text = Null;
	if (preg_match("/\{property.*\}/", $mess_subject, $matches)) {

		// This is for the device properties
		$deviceID = null;
		//
		//	Problem, where is the current device or properties?
		//		From caller? Ie.. Insteonlog
		//		From current properties? 
		//
		if ((array_key_exists('deviceID',$params) && $params['deviceID'] != null)) {
			$deviceID = $params['deviceID'];
		} 
		if (array_key_exists('caller',$params) && array_key_exists('deviceID',$params['caller']) && $params['caller']['deviceID'] != null) {
			$deviceID = $params['caller']['deviceID'];
		}
		
		debug($deviceID, '****deviceID');

		if (!is_null($deviceID)) {
			
			if ($deviceID == DEVICE_SELECTED_PLAYER) {
				$deviceID = $params['SESSION']['properties']['SelectedPlayer']['value'];
			}

			$mysqlp = 'SELECT ha_mi_properties.description, ha_mf_device_properties.value FROM ha_mf_device_properties 
						JOIN ha_mi_properties ON ha_mf_device_properties.propertyID = ha_mi_properties.id 
						WHERE ha_mf_device_properties.deviceID ='.$deviceID;
						
			if ($props = FetchRows($mysqlp)) {
				unset ($pattern);
				unset ($newprops);
				foreach ($props as $key => $value) {
					$pattern[$value['description']]="/\{property___".strtolower($value['description'])."\}/";
					$newprops[$value['description']]=$value['value'];
				}
				debug($newprops, 'newprops');
				debug($pattern, 'pattern');

				//$mess_subject = str_replace("{property___", "{", $mess_subject);
				$mess_subject = preg_replace($pattern, $newprops, $mess_subject);
			}
		}


	}
	debug($mess_subject, 'mess_subject');

	return $mess_subject;
}

function splitCommandvalue(&$params) {
//
//  For macro stepvalues
//		in: $params
//		in: $params
//		out: add value_parts array to $params

	debug($params, 'params');
	if (is_array($params['commandvalue'])) return;
	if (strpos($params['commandvalue'],'|') !== false) {
		$value_parts = explode('|', $params['commandvalue']);
		$params['value_parts']= $value_parts;
	} else {
		$params['value_parts'][0] = $params['commandvalue'];
	}

	if (!array_key_exists(1, $params['value_parts'])) $params['value_parts'][1] = "";
	if (!array_key_exists(2, $params['value_parts'])) $params['value_parts'][2] = "";
	debug($params, 'resultParams');

	return;
}

function parseLastResult($resultin, $stepValue){

//	$thiscommand['commandvalue'] = replaceResultPlaceholders($text (result___postion), $thiscommand, $feedback['result']);		// Replace placeholders in commandvalue


	if (!is_array($resultin)) return $resultin;

	debug($stepValue, 'stepValue');
	debug($resultin, 'resultin');


	// Do an array search for the value to replace {postion}
	// execute before clobbering input
	preg_match('/\{last___result___(.*?)\}/', $stepValue, $output);

	// var_dump($output);
	$flat_resultin = array_flatten($resultin, 1);

	$feedback = false;
	if (array_key_exists(1,$output)) {	// Result found

		$findKey = $output[1];
		debug($findKey, 'Search Array for Key->findKey');

		if (array_key_exists($findKey, $flat_resultin)) {	
			
			debug($flat_resultin[$findKey], 'Found');

			$feedback = trim(str_replace("{last___result___".$findKey."}", $flat_resultin[$findKey], $stepValue));
		} 
	}
	return $feedback;
	
}

function array_flatten($array, $preserve_keys = 1, &$newArray = Array(), $parentkey = "") {
  foreach ($array as $key => $child) {
    if (is_array($child)) {
      $newArray = array_flatten($child, $preserve_keys, $newArray, $parentkey.$key."___");
    } elseif ($preserve_keys == 1) {
      $newArray[str_replace(array("previous_properties___", "___value"),array("property___",""),$parentkey.$key)] = $child;
    } else {
		$newArray[] = $child;
    }
  }
  return $newArray;
}
?>
