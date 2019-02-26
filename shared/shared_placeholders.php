<?php
//define('DEBUG_PHOLDERS', TRUE);
if (!defined('DEBUG_PHOLDERS')) define( 'DEBUG_PHOLDERS', FALSE );

function replaceCommandPlaceholders($stepValue, $params) {
//
//  For outgoing command strings and macro stepvalues
//		in: $stepValue
//		out: return

	if (DEBUG_PHOLDERS) {echo "<pre> replaceCommandPlaceholders "; echo $stepValue.CRLF; print_r ($params); echo "</pre>";}

	if (strpos($stepValue, "{mycommandID}") !== false) $stepValue = str_replace("{mycommandID}",trim($params['commandID']),$stepValue);
	if (strpos($stepValue, "{deviceID}") !== false) $stepValue = str_replace("{deviceID}",trim($params['deviceID']),$stepValue);
	if (strpos($stepValue, "{unit}") !== false) $stepValue = str_replace("{unit}",trim($params['device']['unit']),$stepValue);
	// Not tested
	if (strpos($params['commandvalue'],'|') !== false) {
		$cvs = explode('|', $params['commandvalue']);
		foreach ($cvs as $key => $value) {
			 $stepValue = str_replace('{commandvalue'.$key.'}', $value, $stepValue);
		}
	}

	if (strpos($stepValue,"{port}") !== false) {
		$port = getProperty($params['propertyID'])['port'];
		if (is_null($port)) {
			echo "Empty Port found!!!";
			exit;
		}
		$stepValue = str_replace("{port}",$port,$stepValue);
//		echo "******port:"; print_r($port);
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
		if (DEBUG_COMMANDS) {echo "<pre> calculate "; print_r ($matches); echo "</pre>";}
		$calcvalue = eval('return '.$matches[1].';');
		$stepValue = str_replace($matches[0], $calcvalue, $stepValue);
	}

	if (array_key_exists('mess_subject',$params)) $stepValue = str_replace("{mess_subject}",trim($params['mess_subject']),$stepValue);
	if (array_key_exists('mess_text',$params)) $stepValue = str_replace("{mess_text}",trim($params['mess_text']),$stepValue);

	if (DEBUG_PHOLDERS) {echo "<pre> replaceCommandPlaceholders stepValue: "; echo($stepValue); echo "</pre>";}
	return $stepValue;
}

function splitCommandvalue(&$params) {
//
//  For macro stepvalues
//		in: $params
//		in: $params
//		out: add cvs array to $params

	if (DEBUG_PHOLDERS) {echo "<pre> splitCommandvalue "; print_r($params);  echo "</pre>";}

	if (strpos($params['commandvalue'],'|') !== false) {
		$cvs = explode('|', $params['commandvalue']);
		$params['cvs']= $cvs;
	}
	
	if (DEBUG_PHOLDERS) {echo "<pre> splitCommandvalue result "; print_r($params); echo "</pre>";}
	return;
}
function parseLastResult($resultin, $stepValue){

//	$thiscommand['commandvalue'] = replaceResultPlaceholders($text (result___postion), $thiscommand, $feedback['result']);		// Replace placeholders in commandvalue


	if (!is_array($resultin)) return $resultin;

	if (DEBUG_PHOLDERS) {
		echo "<pre> stepValue: "; echo $stepValue.CRLF; echo "</pre>";
		echo "<pre> parseLastResult "; print_r ($resultin); echo "</pre>";
	}	

	// Do an array search for the value to replace {postion}
	// execute before clobbering input
	preg_match('/\{last___result___(.*?)\}/', $stepValue, $output);

	// var_dump($output);
	$flat_resultin = array_flatten($resultin, 1);

	// print_r($flat_resultin);

	$feedback = false;
	if (array_key_exists(1,$output)) {	// Result found

	// print_r($output);
		$findKey = $output[1];
		if (DEBUG_PHOLDERS) {
			echo "<pre>Search Array for Key "; echo "findKey: ".$findKey; echo "</pre>";
		}
		if (array_key_exists($findKey, $flat_resultin)) {	
			
			if (DEBUG_PHOLDERS) {
				echo "<pre>Value found: "; echo $flat_resultin[$findKey]; echo "</pre>";
			}	
			$feedback = trim(str_replace("{last___result___".$findKey."}", $flat_resultin[$findKey], $stepValue));
		} 
	}
	return $feedback;
	
}


function replacePropertyPlaceholders($mess_subject, $params){
//
//		For replacing property names {property___abc}
//		in: $mess_subject
//		out: return
//

// echo "<pre>";
// print_r($params);
// echo "replacePlaceholder in: ".$mess_subject.CRLF;
	$mess_text = Null;
	if (preg_match("/\{property.*\}/", $mess_subject, $matches)) {

		// This is for the device properties
		$deviceID = null;
		//print_r($params);
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
		
		// echo "************".$deviceID."  ".$mess_subject.CRLF.CRLF;
		if (!is_null($deviceID)) {
			
			$mysqlp = 'SELECT ha_mi_properties.description, ha_mf_device_properties.value FROM ha_mf_device_properties 
						JOIN ha_mi_properties ON ha_mf_device_properties.propertyID = ha_mi_properties.id 
						WHERE ha_mf_device_properties.deviceID ='.$deviceID;
			if ($props = FetchRows($mysqlp)) {
				// print_r($props);
				unset ($pattern);
				unset ($newprops);
				foreach ($props as $key => $value) {
					$pattern[$value['description']]="/\{property___".strtolower($value['description'])."\}/";
					$newprops[$value['description']]=$value['value'];
				}
				if (DEBUG_PHOLDERS) {
					echo "<pre>Device Properties "; echo "DATA:"; print_r ($newprops); echo "</pre>";
					echo "<pre>Device Properties "; echo "PATTERN:"; print_r ($pattern); echo "</pre>";
				}
				//$mess_subject = str_replace("{property___", "{", $mess_subject);
				$mess_subject = preg_replace($pattern, $newprops, $mess_subject);
			}
		}


	}
// echo "replacePlaceholder Result: ".$mess_subject.CRLF;
// echo "</pre>";
	return $mess_subject;
}

function replaceText(&$params){
//
//		For Alerts
//		in:  $params['mess_subject'], $params['mess_text']
//		out: $params['mess_subject'], $params['mess_text']
//
//echo "replaceText in: ".CRLF;
//echo  $params['mess_subject'].CRLF;
//echo  $params['mess_text'].CRLF;
//echo "<pre>";
//print_r($params);
//echo "</pre>";

	$params['deviceID'] = (array_key_exists('deviceID', $params) ? $params['deviceID'] : 'NULL');
	
	$mess_subject = (array_key_exists('mess_subject',$params) ? $params['mess_subject'] : "");
	$mess_text = (array_key_exists('mess_text',$params) ? $params['mess_text'] : "");
	
	// if (array_key_exists('caller', $params)) {
		// $callerparams = $params['caller'];
		// unset ($params['caller']);
	// }
	
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
	
//	 echo "<pre>";
//	 print_r($params);
//	 echo 'var_dump(array_flatten($params, 1));'."\n";
	$params['now'] = date("Y-m-d H:i:s");

	$flat_params = array_flatten($params, 1);

//	 print_r($flat_params);
//	 echo "</pre>";

	replaceFields($mess_subject, $mess_text, $flat_params);
  
 	$params['mess_subject'] = $mess_subject;
	$params['mess_text'] = $mess_text;
	return; 
}

function replaceFields(&$mess_subject, &$mess_text, $params){
//
//	private
//

	if (DEBUG_PHOLDERS) {
		echo "<pre> Replace Fields Params "; print_r ($params); echo "</pre>";
		echo "<pre>Subject: "; echo $mess_subject.CRLF; echo "</pre>";
		echo "<pre>Message: "; echo $mess_text.CRLF; echo "</pre>";
	}

	if ($params['deviceID'] != null && $params['deviceID'] == DEVICE_CURRENT_SESSION) {
		$params['deviceID'] = $params['SESSION']['properties']['SelectedPlayer']['value'];
	}

//	if ($params['deviceID'] != Null) {

		for ($i = 1; $i <= 1; $i++) {		// Only once???

			// Handle commandvalue1, ...
			if (array_key_exists('commandvalue', $params) && strpos($params['commandvalue'],'|') !== false) {
				$cvs = explode('|', $params['commandvalue']);
				foreach ($cvs as $key => $value) {
					$params['commandvalue'.$key] = $value;
				}
			}

			unset ($pattern);
			foreach ($params as $key => $value) {
				if (is_array($value)) unset($params[$key]);
				$pattern[$key]="/\{".strtolower(str_replace(" ","_",$key))."\}/";
			}

			if (DEBUG_PHOLDERS) {
				echo "<pre>Param Values "; echo "DATA:"; print_r ($params); echo "</pre>";
				echo "<pre>Param Values "; echo "PATTERN:"; print_r ($pattern); echo "</pre>";
			}

			$mess_subject = preg_replace($pattern, $params, $mess_subject);
			if (!empty($mess_text)) $mess_text = preg_replace($pattern, $params, $mess_text); // twice to support tag in tag
		}

		// Clean up any left over fields
		$mess_subject = trim(preg_replace('/\{.*?\}/', '' , $mess_subject));
		if (!empty($mess_text)) $mess_text = trim(preg_replace('/\{.*?\}/', '', $mess_text)); // twice to support tag in tag
//	}

	if (DEBUG_PHOLDERS) {
		echo "<pre> Replace Fields Results "; echo "</pre>";
		echo "<pre>Subject: "; echo $mess_subject.CRLF; echo "</pre>";
		echo "<pre>Message: "; echo $mess_text.CRLF; echo "</pre>";
	}
	return true;
}

function preg_replace_array__delete($pattern, $replacement, $subject, $limit=-1) {
    if (is_array($subject)) {
        foreach ($subject as &$value) $value=preg_replace_array($pattern, $replacement, $value, $limit);
        return $subject;
    } else {
        return preg_replace($pattern, $replacement, $subject, $limit);
    }
} 

function array_flatten($array, $preserve_keys = 1, &$newArray = Array(), $parentkey = "") {
  foreach ($array as $key => $child) {
    if (is_array($child)) {
      $newArray = array_flatten($child, $preserve_keys, $newArray, $parentkey.$key."___");
    } elseif ($preserve_keys + is_string($key) > 1) {
      $newArray[str_replace(array("previous_properties___", "___value"),array("property___",""),$parentkey.$key)] = $child;
    } else {
      $newArray[] = $child;
    }
  }
  return $newArray;
}
?>
