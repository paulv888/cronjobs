<?php
//define('DEBUG_PHOLDERS', TRUE);
if (!defined('DEBUG_PHOLDERS')) define( 'DEBUG_PHOLDERS', FALSE );

function replaceCommandPlaceholders($result, $params) {
//
//  For outgoing command strings and macro stepvalues
//		in: $result
//		out: return

	if (DEBUG_PHOLDERS) {echo "<pre> replaceCommandPlaceholders "; echo $result.CRLF; print_r ($params); echo "</pre>";}

	if (strpos($result, "{mycommandID}") !== false) $result = str_replace("{mycommandID}",trim($params['commandID']),$result);
	if (strpos($result, "{deviceID}") !== false) $result = str_replace("{deviceID}",trim($params['deviceID']),$result);
	if (strpos($result, "{unit}") !== false) $result = str_replace("{unit}",trim($params['device']['unit']),$result);
	// Not tested
	if (strpos($params['commandvalue'],'|') !== false) {
		$cvs = explode('|', $params['commandvalue']);
		foreach ($cvs as $key => $value) {
			 $result = str_replace('{commandvalue'.$key.'}', $value, $result);
		}
	}

	if (strpos($result,"{port}") !== false) {
		$port = getProperty($params['propertyID'])['port'];
		if (is_null($port)) {
			echo "Empty Port found!!!";
			exit;
		}
		$result = str_replace("{port}",$port,$result);
//		echo "******port:"; print_r($port);
	}
	if (strpos($result, "{macro___commandvalue}") !== false) $result = str_replace("{macro___commandvalue}",trim($params['macro___commandvalue']),$result);
	if (strpos($result, "{commandvalue}") !== false) $result = str_replace("{commandvalue}",trim($params['commandvalue']),$result);
	if (strpos($result, "{value}") !== false) $result = str_replace("{value}",trim($params['value']),$result);
	if (strpos($result, "{timervalue}") !== false) $result = str_replace("{timervalue}",trim($params['timervalue']),$result);
	if (preg_match("/\{calculate___(.*?)\}/", $result,$matches)) {
		if (DEBUG_COMMANDS) {echo "<pre> calculate "; print_r ($matches); echo "</pre>";}
		$calcvalue = eval('return '.$matches[1].';');
		$result = str_replace($matches[0], $calcvalue, $result);
	}

	if (array_key_exists('mess_subject',$params)) $result = str_replace("{mess_subject}",trim($params['mess_subject']),$result);
	if (array_key_exists('mess_text',$params)) $result = str_replace("{mess_text}",trim($params['mess_text']),$result);

	if (DEBUG_PHOLDERS) {echo "<pre> replaceCommandPlaceholders result"; echo($result); echo "</pre>";}
	return $result;
}

function replaceResultPlaceholders($mess_subject, &$params, $resultin){

	if (DEBUG_PHOLDERS) {
		echo "<pre> replaceResultPlaceholders "; print_r ($resultin); echo "</pre>";
		echo "<pre>Subject: "; echo $mess_subject.CRLF; echo "</pre>";
	}	

	// Do an array search for the value to replace {postion}
	// execute before clobbering input
	preg_match('/\{result___(.*?)\}/', $mess_subject, $output);
	if (!empty($output)) {	// Found me some
		$resultin['deviceID'] = $params['deviceID'];
		if (DEBUG_PHOLDERS) {
			echo "<pre>Search Array for Key "; echo "DATA0:"; print_r ($params); echo "</pre>";
			echo "<pre>Search Array for Key "; echo "PATTERN0:"; print_r ($output); echo "</pre>";
		}

		$filterkeep = array( $output[1] => 1);
		doFilter($resultin, array(), $filterkeep, $result);
		// echo "Filtered: >";
		// print_r($result);

		if (is_array($result)) {
			//echo $result[0][$output[1]].CRLF;	
			$mess_subject = str_replace($output[0], trim($result[0][$output[1]]), $mess_subject);
			$propname = str_replace('result___', '', $output[1]);
			$params['device']['properties'][$propname]['value']= $result[0][$output[1]];
			//if ($mess_text != Null) $mess_text=preg_replace($pattern, $params, $mess_text); // twice to support tag in tag
		}
		
		if (DEBUG_PHOLDERS) {
			echo "<pre>"; echo $mess_subject.CRLF; echo "</pre>";
		}	
	}
	
	// echo "return replacePlaceholder in: ".$mess_subject.CRLF;
	return $mess_subject;
}


function replacePropertyPlaceholders($mess_subject, $params){
//
//		For replacing property names {property___abc}
//		in: $mess_subject
//		out: return
//

	//echo "replacePlaceholder in: ".$mess_subject.CRLF;
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
		if ((array_key_exists('deviceID',$params) && $params['deviceID'] != null)) 
			$deviceID = $params['deviceID'];
		elseif (array_key_exists('caller',$params) && array_key_exists('deviceID',$params['caller']) && $params['caller']['deviceID'] != null) 
			$deviceID = $params['caller']['deviceID'];
		
		//echo "************".$deviceID."  ".$mess_subject.CRLF.CRLF;
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
					echo "<pre>Device Properties "; echo "DATA-2:"; print_r ($newprops); echo "</pre>";
					echo "<pre>Device Properties "; echo "PATTERN-2:"; print_r ($pattern); echo "</pre>";
				}
				//$mess_subject = str_replace("{property___", "{", $mess_subject);
				$mess_subject = preg_replace($pattern, $newprops, $mess_subject);
			}
		}


	}
	return $mess_subject;
}

function replaceText(&$params){
//
//		For Alerts
//		in:  $params['mess_subject'], $params['mess_text']
//		out: $params['mess_subject'], $params['mess_text']
//
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
		if (array_key_exists('deviceID', $params['caller'])) {
			if ($cd = FetchRow("SELECT description FROM ha_mf_devices WHERE ha_mf_devices.id =".$params['caller']['deviceID'])) {
				$params['caller___device___description']= $cd['description'];
			}
			$params['caller___device___property'] = getDeviceProperties(Array( 'deviceID' => $params['caller']['deviceID']));
		}
	}
	
	// echo "<pre>";
	// print_r($params);
	// echo 'var_dump(array_flatten($params, 1));'."\n";
	$params['now'] = date("Y-m-d H:i:s");

	$flat_params = array_flatten($params, 1);

	// print_r($flat_params);
	// echo "</pre>";

	replaceFields($mess_subject, $mess_text, $flat_params);

	// if (isset($callerparams)) {
		// $callerparams['deviceID'] = (array_key_exists('deviceID', $callerparams) ? $callerparams['deviceID'] : 'NULL');
		// $mess_subject = str_replace("{caller___", "{", $mess_subject); // Now replace all {caller___ha_table___field} to (ha_table___field}
		// $mess_text = str_replace("{caller___", "{", $mess_text); 	// Now replace all {caller___ha_table___field} to (ha_table___field}
		// replaceFields($mess_subject, $mess_text, $callerparams);
	// }

	$params['mess_subject'] = $mess_subject;
	$params['mess_text'] = $mess_text;
	// $params['caller'] = $callerparams;
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
				$pattern[$key]="/\{".strtolower($key)."\}/";
			}

			if (DEBUG_PHOLDERS) {
				echo "<pre>Param Values "; echo "DATA-3:"; print_r ($params); echo "</pre>";
				echo "<pre>Param Values "; echo "PATTERN-3:"; print_r ($pattern); echo "</pre>";
			}

			// Why whole array, lest just do on base, be
			// $mess_subject = preg_replace_array($pattern, $params, $mess_subject);
			// if ($mess_text != Null) $mess_text = preg_replace_array($pattern, $params, $mess_text); 
			$mess_subject = preg_replace($pattern, $params, $mess_subject);
			if ($mess_text != Null) $mess_text=preg_replace($pattern, $params, $mess_text); // twice to support tag in tag

		}

		// Clean up any left over fields
		$mess_subject = trim(preg_replace('/\{.*?\}/', '' , $mess_subject));
		if ($mess_text != Null) $mess_text = trim(preg_replace('/\{.*?\}/', '', $mess_text)); // twice to support tag in tag
//	}

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
