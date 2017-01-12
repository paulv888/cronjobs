<?php
// define( 'DEBUG_ALX', TRUE );
if (!defined('DEBUG_ALX')) define( 'DEBUG_ALX', FALSE );
require_once 'includes.php';
define("MY_DEVICE_ID", 283);
define("APP_ID","amzn1.ask.skill.a2ffdb0e-1bd3-414c-bce2-0dfd5ba527b1");

$status_feedback = array (
	array("off","on"),		// 0
	array("off","on"),		// 1
	array("closed","open"),
	array("un-locked","locked"),
	array("disarmed","armed"),
	array("not seen","detected"),
	array("off","running")
);

if (isset($_GET["Message"])) {
	$sdata=$_GET["Message"];
} else {
	$sdata = file_get_contents("php://input");
}

$file = 'alexa.log';
$current = file_get_contents($file);
$current .= date("Y-m-d H:i:s").": ".$sdata."\n";
file_put_contents($file, $current);

if (!($sdata=="")) { 					//import_event
	$rcv_message = json_decode($sdata, TRUE);

	if ($rcv_message['session']['application']['applicationId'] != APP_ID) {
		echo '{"errorMessage": "Exception: applicationId not matching"}';
		return ;
	}

	if (DEBUG_ALX) { print_r($rcv_message);}
	$session = $rcv_message['session'];
	$request = $rcv_message['request'];
	$type = str_replace('AMAZON.', '', $request['type']);
	if (function_exists($type)) {
		$text = $type($request);
	} else {
		$text = "Sorry, I do not understand that";
	}

 	$result = Array();
	$result['version'] = "1.0";
	$result['response']['outputSpeech']['type'] = "PlainText";
	$result['response']['outputSpeech']['text'] = $text;
	$result['response']['shouldEndSession'] = true;
	$result['sessionAttributes'] = Array();
	$response = json_encode($result);
	header('Content-Type: application/json; charset=utf-8');
	echo $response;
	
	// $rcv_message['code']=WINK_CODE;
	// $message['deviceID'] = setDeviceID($rcv_message);
	// $message['commandID'] = $rcv_message['Command'];
	// if (!array_key_exists('InOut', $rcv_message)) $rcv_message['InOut'] = COMMAND_IO_RECV;
	// $message['inout'] = $rcv_message['InOut'];
	// // Should read primary_status 
	// if (array_key_exists('Status', $rcv_message)) $status_key = 'Status';
	// if (array_key_exists('Locked', $rcv_message)) $status_key = 'Locked';
	
	// if (!array_key_exists($status_key, $rcv_message)) $rcv_message[$status_key] = null;
	// if (array_key_exists('Value', $rcv_message)) {
		// $message['data'] = $rcv_message['Value'];
	// } else {
		// $message['data'] = $rcv_message[$status_key];
	// }
	// $message['typeID'] = getDevice($message['deviceID'])['typeID'];
	
	// $properties = array();
	// if (array_key_exists('attributes', $rcv_message)) {
		// foreach ($rcv_message['attributes'] as $attr) {
			// $properties[str_replace('_', ' ', $attr['attributeName'])]['value'] =  ($attr['value_get'] != "" ? $attr['value_get'] : $attr['value_set']);
		// }
	// }
	// //print_r($properties);
	
	// //$message['message'] = prettyPrint($sdata);
	// $message['callerID'] = MY_DEVICE_ID;
	// $message['message'] = $sdata;
	//print_r($message);
	// if ($message['inout'] == COMMAND_IO_RECV) {
		// $error_message = (array_key_exists('errorMessage', $rcv_message) ? implode(" - ", $errorMessage) : null);
		// $device['previous_properties'] = getDeviceProperties(Array('deviceID' => $message['deviceID']));
		// $properties[$status_key]['value'] = (string)$rcv_message[$status_key];
		// $properties['Link']['value'] = LINK_UP;
		// $properties['Value']['value'] = $v;
		// $device['properties'] = $properties;
		// $message['result'] = updateDeviceProperties(array( 'callerID' => $message['callerID'], 'deviceID' => $message['deviceID'] , 'commandID' => $message['commandID'], 'device' => $device, 'message' => $error_message));
	// }
	// logEvent($message);

return;
}

function WhatsTemperatureIntent($request) {

	$responses = array ( "At the moment, the %s temperature is %s degrees.", "Asjkum, The %s temperature is %s degrees.", "%s, %s degrees, thank you");

	$find = (array_key_exists('value', $request['intent']['slots']['TempDevice']) ? $request['intent']['slots']['TempDevice']['value'] : 'outside');

	$mysql = 'SELECT deviceID, LOWER(description) as description FROM `ha_voice_names` WHERE deviceID > 0'; 
	$voicenames = FetchRows($mysql);
	$found = search_array_key_value($voicenames, 'description' , $find);
	if (DEBUG_ALX) { print_r($found);}
	if (!empty($found)) { 
		$deviceID = $found[0]['deviceID'];	// Handle multiple matches?
	} else {
		return sprintf("I do not know what the temperature is in the %s",  $find);
	}

	$propertyName = 'Temperature';
	$deviceProperty = getDeviceProperties(array('description'=>$propertyName, 'deviceID'=>$deviceID));
	
	if (DEBUG_ALX) { print_r($deviceProperty); print_r($voicenames);}
	return sprintf($responses[rand(0,count($responses)-1)], defaultFeedbackName($deviceID), round((float)$deviceProperty['value'],1) + 0);

}

function AnySecurityOpenIntent($request) {

	$responses_all = array ("All %s are closed.");
	$responses_some = array ("The %s is open");

	$find = (array_key_exists('value', $request['intent']['slots']['Types']) ? $request['intent']['slots']['Types']['value'] : 'doors');
	
	$propertyID = getProperty('Status', false)['id'];

	$mysql = 'SELECT typeID, LOWER(description) as description FROM `ha_voice_names` WHERE typeID > 0'; 
	$voicenames = FetchRows($mysql);
	$found = search_array_key_value($voicenames, 'description' , $find);
	if (DEBUG_ALX) { echo "Found:" ; print_r($found);}
	if (!empty($found)) { 
		$typeID = $found[0]['typeID'];
		$mysql = 'SELECT id as deviceID, typeID FROM `ha_mf_devices` WHERE typeID IN ('.$typeID.') and inuse = 1'; // Handle multiple matches?
		$devices = FetchRowsIdDescription($mysql);
		if (DEBUG_ALX) { echo "Matching devices:" ; print_r($devices);}
		foreach ($devices as $deviceID => $value) {
			$feedback[] = getStatusLink(array('deviceID'=>$deviceID, 'propertyID'=>$propertyID));
		}
		if (DEBUG_ALX) { echo "Feedback:" ; print_r($feedback);}
	} else {
		return sprintf("I cannot find devices for %s",  $find);
	}
	
	$response = "";
	$anyopen = 0;
	foreach ($feedback as $status) {
		if ($status['Status']) {
			$anyopen = true;
			$response .= ($response != "" ? ' and ' : '').sprintf($responses_some[rand(0,count($responses_some)-1)], defaultFeedbackName($status['DeviceID'])); 
		}
	}
	if (!$anyopen) {
		$response = sprintf($responses_all[rand(0,count($responses_all)-1)], $find);
	} 
	
	if (DEBUG_ALX) { print_r($devices); print_r($voicenames);}
	return $response;

}

function DeviceStatusIntent($request) {

	$responses = array ("The %s is %s", "The status of the %s is %s");

	$findDevice = (array_key_exists('value', $request['intent']['slots']['Device']) ? $request['intent']['slots']['Device']['value'] : ""); // No default
	$findProperty = (array_key_exists('value', $request['intent']['slots']['Status']) ? $request['intent']['slots']['Status']['value'] : "Status"); // No default

	$mysql = 'SELECT deviceID, LOWER(description) as description FROM `ha_voice_names` WHERE deviceID > 0'; 
	$voicenames = FetchRows($mysql);
	$found = search_array_key_value($voicenames, 'description' , $findDevice);
	//if (DEBUG_ALX) { print_r($found);}
	if (!empty($found)) { 
		$deviceID = $found[0]['deviceID'];	// Handle multiple matches?
	} else {
		return sprintf("Unable to find device called %s",  $findDevice);
	}

	$deviceProperties = getDeviceProperties(array('deviceID'=>$deviceID));
	if (DEBUG_ALX) { print_r($deviceProperties); print_r($voicenames);}

	$found = findByKeyValue($deviceProperties, 'primary_status' , "1");
	if (DEBUG_ALX) { echo "Found primary:"; echo($found);}
	
 
	if ($deviceProperties[$found]['invertstatus'] == "0") {  
		if ($deviceProperties[$found]['value'] == STATUS_OFF) {
			$deviceProperties[$found]['value'] == STATUS_ON;
		} else if ($deviceProperties[$found]['value'] == STATUS_ON) {
			$deviceProperties[$found]['value'] == STATUS_OFF;
		}
	}
	
	global $status_feedback;
	$statusNames = $status_feedback[getDevice($deviceID)['type']['status_feedback']];
	// print_r($statusNames);
	if ($deviceProperties[$found]['value'] == STATUS_OFF) {
		$status=$statusNames[STATUS_OFF];
	} elseif ($deviceProperties[$found]['value'] == STATUS_UNKNOWN) {
		$status="unknown";
	} elseif ($deviceProperties[$found]['value'] == STATUS_ON) {
		$status=$statusNames[STATUS_ON];
	} elseif ($deviceProperties[$found]['value'] == STATUS_ERROR) {
		$status="error";
	} else { 							
		$status="undefined";
	}
	
	return sprintf($responses[rand(0,count($responses)-1)], defaultFeedbackName($deviceID), $status);
	//return sprintf($responses[rand(0,count($responses)-1)], $findDevice, $status);

}

function WhatsBedtimeIntent($request) {

	$responses = array("On %s you went to bed at %s.");

	
	// Handle not getting date
	$find = (array_key_exists('value', $request['intent']['slots']['Date']) ? $request['intent']['slots']['Date']['value'] : date("Y-m-d"));
	
	if ($find > date("Y-m-d")) { 		// Future
		$date = new DateTime($find);
		$response = sprintf("Sorry, I can not look into the future, please let me know once it is %s", $date->format("jS F Y"));
	} else {
		// Yesterday should be day before, others should be normal?
		$fromdate = $find;
		$todate = date("Y-m-d H:i", strtotime($fromdate." +24 hour"));
		$mysql = 'SELECT alert_date FROM `ha_alerts`  WHERE (description LIKE "%goodnight%" and `alert_date` > "'.$fromdate.'" and `alert_date` < "'. $todate.'") order by alert_date desc limit 1';
		// echo $mysql;
		$response = "Insufficient data. Please specify parameters.";
		if ($row = FetchRow($mysql)) {
			$date = new DateTime($row['alert_date']);
			$response = sprintf($responses[rand(0,count($responses)-1)], $date->format('l'), $date->format('g:i'));
		} else { 
			$mysql = 'SELECT alert_date FROM `ha_alerts`  WHERE (description LIKE "%goodnight%") order by alert_date desc limit 1';
			if ($row = FetchRow($mysql)) {
				$date = new DateTime($row['alert_date']);
				$response = sprintf("The latest bedtime I could find was on %s at %s", $date->format('l'), $date->format('g:i'));
			}
		}
	}

	return $response;

}

function WhatsPlayingIntent($request) {

	$responses = array("This is %s by %s","We're listening to %s by %s", "%s by %s, thank you!");

	$deviceProperties = getDeviceProperties(array('deviceID'=>DEVICE_DEFAULT_PLAYER));
	if (DEBUG_ALX) { print_r($deviceProperties); }
	return sprintf($responses[rand(0,count($responses)-1)], $deviceProperties['Title']['value'], $deviceProperties['Artist']['value']);

}
	//	$result['currentTrack'] = array( "title" => "Current Title", "artist" => 'Alexa');

function defaultFeedbackName($deviceID) {
		$mysql = 'SELECT LOWER(description) as description FROM `ha_voice_names` WHERE default_feedback = 1 and deviceID ='.$deviceID; 
		if ($row = FetchRow($mysql)) {
			return $row['description'];
		} else {
			$mysql = 'SELECT LOWER(description) as description FROM `ha_voice_names` WHERE and deviceID ='.$deviceID; 
			if ($row = FetchRow($mysql)) {
				return $row['description'];		
			}
		}
		return "no name";
}

function HelpIntent($request) {
    $speechText = "You can ask for the best sellers on Amazon for a given category. " +
        "For example, get best sellers for books, or you can say exit. " +
        "Now, what can I help you with?";
    $repromptText = "<speak> I'm sorry I didn't understand that. You can say things like, " +
        "books <break time=\"0.2s\" /> " +
        "movies <break time=\"0.2s\" /> " +
        "music. Or you can say exit. " +
        "Now, what can I help you with? </speak>";

    $speechOutput = array (
        'speech'=>$speechText,
        'type'=>"PlainText"
    );
    $repromptOutput = array(
        'speech'=>$repromptText,
        'type'=>'SSML'
    );
    askResponse($speechOutput, $repromptOutput);
}

function LaunchRequest($request) {
    // If we wanted to initialize the session to have some attributes we could add those here.
    $speechText = "Welcome to me. What do you want to know about your home?";
    $repromptText = "<speak>Please choose a category by saying, " +
        "books <break time=\"0.2s\" /> " +
        "fashion <break time=\"0.2s\" /> " +
        "movie <break time=\"0.2s\" /> " +
        "kitchen</speak>";

    $speechOutput = array(
        'speech'=>$speechText,
        'type'=>"PlainText"
    );
    
	$repromptOutput = array(
        'speech'=>$repromptText,
        'type'=>AlexaSkill.speechOutputType.SSML
    );
    askResponse($speechOutput, $repromptOutput);
}

function IntentRequest($request) {
	$intent = str_replace('AMAZON.', '', $request['intent']['name']);
	if (function_exists($intent)) {
		$text = $intent($request);
	} else {
		$text = "Sorry, I do not understand that";
	}
	return $text;
}

function SessionEndedRequest($request) {
    // If we wanted to initialize the session to have some attributes we could add those here.
    // var speechText = "Welcome to me. What do you want to know about your home?";
    // var repromptText = "<speak>Please choose a category by saying, " +
        // "books <break time=\"0.2s\" /> " +
        // "fashion <break time=\"0.2s\" /> " +
        // "movie <break time=\"0.2s\" /> " +
        // "kitchen</speak>";

    // var speechOutput = {
        // speech: speechText,
        // type: AlexaSkill.speechOutputType.PLAIN_TEXT
    // };
    // var repromptOutput = {
        // speech: repromptText,
        // type: AlexaSkill.speechOutputType.SSML
    // };
    // response.ask(speechOutput, repromptOutput);
}

function askResponse($speechOutput, $repromptOutput) {
 // Do something
 }

function sendResponse ($speechOutput, $repromptOutput) {
 	$result = Array();
	$result['version'] = "1.0";
	$result['response']['outputSpeech'] = $speechOutput;
	//$result['response']['outputSpeech']['text'] = $text;
	$result['response']['shouldEndSession'] = true;
	$result['sessionAttributes'] = Array();
	$response = json_encode($result);
	header('Content-Type: application/json; charset=utf-8');
	echo $response;
}
?>
