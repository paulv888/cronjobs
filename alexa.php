<?php
// define( 'DEBUG_ALX', TRUE );
if (!defined('DEBUG_ALX')) define( 'DEBUG_ALX', FALSE );
require_once 'includes.php';
require_once 'includesAlexa.php';

define("MY_DEVICE_ID", 283);
define ("SAY_ASKIM", '<phoneme alphabet="ipa" ph="/ə\'ʃkom">askim</phoneme>');


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


	$rcv_message = json_decode($sdata);

	$alexaRequest = \Alexa\Request\Request::fromData($rcv_message);

	try {
	  $alexaRequest->validate(APP_ID);
	} catch(Exception $e) {
	  echo 'Message: ' .$e->getMessage();
	  exit;
	}

	// print_r($alexaRequest);
 	if ($alexaRequest instanceof \Alexa\Request\IntentRequest) {
		if (function_exists($alexaRequest->intentName)) {
			$fname= $alexaRequest->intentName;
			$response = new \Alexa\Response\Response;
			// Launched, dialog keep open and save Launched
			if (!$alexaRequest->session->new && isset($alexaRequest->session->attributes) && isset($alexaRequest->session->attributes->Launched)) {
				$response->sessionAttributes(Array("Launched" => true));
				$response->endSession(false);
			}
			$result = $fname($alexaRequest, $alexaRequest->session, $response);
		} else {
			$response = new \Alexa\Response\Response;
			$response->respond("<speak> I'm sorry I didn't understand that.</speak>")
			 ->reprompt("<speak> Try that again. You can say things like, " .
				"System status <break time=\"0.2s\" /> " .
				"Are there any windows open <break time=\"0.2s\" /> " .
				"What is Playing. Or you can say exit. " .
				"Now, what can I help you with? </speak>");

			$response->ask();
			return;
		}
	}

 	if ($alexaRequest instanceof \Alexa\Request\LaunchRequest) {
		$response = new \Alexa\Response\Response;
		$result = LaunchRequest($alexaRequest, $alexaRequest->session, $response);
	}
	
 	if ($alexaRequest instanceof \Alexa\Request\SessionEndedRequest) {
		$response = new \Alexa\Response\Response;
		$result = SessionEndedRequest($alexaRequest, $alexaRequest->session, $response);
	}

	if (DEBUG_ALX) print_r($alexaRequest);

	return;
}

function ShortAnswerIntent($request, $session, $response) {


	$finddate = strtolower((isset($request->slots->Date) ? $request->slots->Date : ""));
	$findelse = strtolower((isset($request->slots->CatchAll) ? $request->slots->CatchAll : ""));
	$orgIntent = "NotFound";
	if (!$session->new && isset($session->attributes) && isset($session->attributes->intentSequence)) {
		$orgIntent = $session->attributes->intentSequence;
	}
	
	// var_dump(isset('attributes', $session));
	// var_dump($session);
	// var_dump(!$session->new);
	// print_r($request);
	// var_dump(isset($request->slots->CatchAll));
	// print_r($request->slots);
	// echo "$findelse"." "."$orgIntent".CRLF;

	if (empty($findelse) && empty($finddate)) {
		$response->respond("Sorry, I did not understand, please start over.");
		$response->tell();
	}

	switch ($orgIntent) {
		case "WhatsTemperatureIntent":
			$request->slots->TempDevice = $findelse;
			$request->intentName = $orgIntent;
			break;
		case "AnySecurityOpenIntent":
			$request->slots->Types = $findelse;
			$request->intentName = $orgIntent;
			break;
		case "DeviceStatusIntent":
			$request->slots->Device = $findelse;
			$request->intentName = $orgIntent;
			break;
		case "WhatsBedtimeIntent":
			$request->slots->Date = $finddate;
			$request->intentName = $orgIntent;
			break;
		case "WhatsPlayingIntent":
		case "NotFound":
		default:
			$response->respond("Sorry, I did not understand, please start over.");
			$response->tell();
			return;
	}
	
	if (DEBUG_ALX) { print_r($request);}
	$fname= $request->intentName;
	$result = $fname($request, $session, $response);

	return ;
	
}


function WhatsTemperatureIntent($request, $session, $response) {

	$responses = array ( "At the moment, the %s temperature is %s degrees.", "<speak>".SAY_ASKIM.", The %s temperature is %s degrees.</speak>", "%s, %s degrees.");

	$find = strtolower((isset($request->slots->TempDevice) ? $request->slots->TempDevice : "outside"));

	$mysql = 'SELECT deviceID, LOWER(description) as description FROM `ha_voice_names` WHERE deviceID > 0'; 
	$voicenames = FetchRows($mysql);
	$found = search_array_key_value($voicenames, 'description' , $find);
	if (DEBUG_ALX) { print_r($found);}
	if (!empty($found)) { 
		$deviceID = $found[0]['deviceID'];	// Handle multiple matches?
	} else {
		$response->respond(sprintf("I do not know what the temperature is in the %s, do you want to try another device?",  $find))
		 ->reprompt("Sorry which device you want?");
		$response->sessionAttributes(Array("intentSequence"=>"WhatsTemperatureIntent"));
		$response->ask();
		return false;
	}

	$propertyName = 'Temperature';
	$deviceProperty = getDeviceProperties(array('description'=>$propertyName, 'deviceID'=>$deviceID));
	
	if (DEBUG_ALX) { print_r($deviceProperty); print_r($voicenames);}
	
	$response->respond(sprintf($responses[rand(0,count($responses)-1)], defaultFeedbackName($deviceID), round((float)$deviceProperty['value'],1) + 0));
    $response->tell();
	return ;
	
}

function AnySecurityOpenIntent($request, $session, $response) {

	$responses_all = array ("All %s are closed.");
	$responses_some = array ("The %s is open.");

	$find = strtolower(isset($request->slots->Types) ? $request->slots->Types : 'doors');
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
		$response->respond("Sorry which device group you want?")
		 ->reprompt("Sorry which device group you want?");
		$response->sessionAttributes(Array("intentSequence"=>"AnySecurityOpenIntent"));
		$response->ask();
		return false;
	}
	
	
	$answer = "";
	$anyopen = 0;
	foreach ($feedback as $status) {
		if ($status['Status']) {
			$anyopen = true;
			$answer .= ($answer != "" ? ' and ' : '').sprintf($responses_some[rand(0,count($responses_some)-1)], defaultFeedbackName($status['DeviceID'])); 
		}
	}
	
	if (!$anyopen) {
		$answer = sprintf($responses_all[rand(0,count($responses_all)-1)], $find);
	} 
	
	if (DEBUG_ALX) { print_r($devices); print_r($voicenames);}

	$response->respond($answer);
    $response->tell();
	return ;

}

function DeviceStatusIntent($request, $session, $response) {


	$responses = array ("The %s is %s", "The status of the %s is %s.");

	$findDevice = strtolower(isset($request->slots->Device) ? $request->slots->Device : "system");
	// $findProperty = (isset($request->slots->Status) ? $request->slots->Status : "Status");
	$findProperty = "Status";

	if ($findDevice == "home" || $findDevice == "system") {homeStatus($request, $session, $response); return;}
	
	$mysql = 'SELECT deviceID, LOWER(description) as description FROM `ha_voice_names` WHERE deviceID > 0'; 
	$voicenames = FetchRows($mysql);
	$found = search_array_key_value($voicenames, 'description' , $findDevice);
	if (DEBUG_ALX) { print_r($found);}
	if (!empty($found)) { 
		$deviceID = $found[0]['deviceID'];	// Handle multiple matches?
	} else {
		$response->respond("Sorry which device you want?")
		 ->reprompt("Sorry which device you want?");
		$response->sessionAttributes(Array("intentSequence"=>"DeviceStatusIntent"));
		$response->ask();
		return false;
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
	
	$response->respond(sprintf($responses[rand(0,count($responses)-1)], defaultFeedbackName($deviceID), $status));
    $response->tell();
	return ;

}

function WhatsBedtimeIntent($request, $session, $response) {

	// Handle not getting date
	$find = strtolower(isset($request->slots->Date) ? $request->slots->Date :  date("Y-m-d"));

	if ($find > date("Y-m-d")) { 		// Future
		$date = new DateTime($find);
		$response->respond("Only past dates please, which day did you want to know about?")
				->reprompt("Which day did you want to know about.");
		$response->sessionAttributes(Array("intentSequence"=>"WhatsBedtimeIntent"));
		$response->ask();
		return false;
	} else {
		// Yesterday should be day before, others should be normal?
		$fromdate = $find;
		$to_date = date("Y-m-d H:i", strtotime($fromdate." +24 hour"));
		$mysql = 'SELECT alert_date FROM `ha_alerts`  WHERE (description LIKE "%goodnight%" and `alert_date` > "'.$fromdate.'" and `alert_date` < "'. $to_date.'") order by alert_date desc limit 1';
		// echo $mysql;
		$answer = "Insufficient data. Please specify parameters.";
		if ($row = FetchRow($mysql)) {
			$date = new DateTime($row['alert_date']);
		} else { 
			$mysql = 'SELECT alert_date FROM `ha_alerts`  WHERE (description LIKE "%goodnight%") order by alert_date desc limit 1';
			if ($row = FetchRow($mysql)) {
				$date = new DateTime($row['alert_date']);
			}
		}
	}

	$datestr = checkYesterday($date->format("Y-m-d"));
	// var_dump($datestr);
	if ($datestr) {
		$answer = sprintf("%s you went to bed at %s.", $datestr, $date->format('g:i'));
	} else {
		$answer = sprintf("On %s you went at %s to bed.", $date->format('l'), $date->format('g:i'));
	}
	$response->respond($answer);
    $response->tell();
	return ;

}

function WhatsPlayingIntent($request, $session, $response) {

	$responses = array("<speak>This is <break time=\"0.2s\" /> %s <break time=\"0.2s\" />by %s.</speak>","<speak>We're listening to <break time=\"0.2s\" /> %s  <break time=\"0.2s\" /> by %s </speak>", "<speak>%s  <break time=\"0.2s\" /> by %s.</speak>");

	$deviceProperties = getDeviceProperties(array('deviceID'=>DEVICE_DEFAULT_PLAYER));
	if (DEBUG_ALX) { print_r($deviceProperties); }
	
	$response->respond(sprintf($responses[rand(0,count($responses)-1)], $deviceProperties['Title']['value'], $deviceProperties['Artist']['value']));
    $response->tell();
	
	return;

}

function DoingIntent($request, $session, $response) {

	$responses1 = array("Let me check on %s. <break time=\"3s\" />","Searching %s... <break time=\"3s\" />", "Looking for %s, Switching on cameras... <break time=\"3s\" />","Searching %s... <break time=\"3s\" />Still searching, checking under the couch now <break time=\"3s\" />");
	$responses2 = array("Yep, looks like it","I bet you!", "Hmm, No probably just resting the eyes!","Yep, what's new?","Yep, no suprise here!");
	$responses3 = array("No, off course not!","Nope, looks like you are completely wrong!", "Absolutely not! Really!, how dare you","Absolutely not, maybe you should check on yourself!","Nope, it could not be farther from the thruth!");

	$find = strtolower(isset($request->slots->People) ? $request->slots->People : "that");
	if ($find == "paul" || $find == "the husband" || $find == "husband") {
		$response->respond("<speak>".sprintf($responses1[rand(0,count($responses1)-1)], $find).$responses3[rand(0,count($responses2)-1)]."</speak>");
	} else {
		$response->respond("<speak>".sprintf($responses1[rand(0,count($responses1)-1)], $find).$responses2[rand(0,count($responses2)-1)]."</speak>");
	}
    $response->tell();
	
	return;

}

function homeStatus($request, $session, $response) {

	$responses_all = [ "Primary systems are on-line <break time=\"0.2s\" />", "Secondary systems functioning at peak efficiency." ];
	$responses_some = [ "Following primary systems are off-line: <break time=\"0.2s\" />", "Following secondary systems are off-line: <break time=\"0.2s\" />" ];
	$responses_offline = ["%s <break time=\"0.2s\" />", "%s <break time=\"0.2s\" />"];


	$find = strtolower(isset($request->slots->Types) ? $request->slots->Types : 'doors');
	$propertyID = getProperty('Link', false)['id'];

	$index = 0;
	$answer = ["" , ""];
	foreach (['17', '18'] as $groupID) {
		$mysql = 'SELECT deviceID, groupID FROM `ha_mf_device_group` WHERE groupID = '.$groupID; 
		$devices = FetchRowsIdDescription($mysql);
		if (DEBUG_ALX) { echo "Matching devices:" ; print_r($devices);}
		$feedback = array();
		foreach ($devices as $deviceID => $value) {
			$feedback[] = getStatusLink(array('deviceID'=>$deviceID, 'propertyID'=>$propertyID));
		}
		if (DEBUG_ALX) { echo "Feedback:" ; print_r($feedback);}
		
		$anydown = 0;
		foreach ($feedback as $status) {
			if (array_key_exists('Link', $status) && $status['Link'] != "1") {
				$anydown = true;
				$answer[$index] .= ($answer[$index] != "" ? ' ; ' : $responses_some[$index].' ; ').sprintf($responses_offline[$index], defaultFeedbackName($status['DeviceID'])); 
			}
		}
		
		if (!$anydown) {
			$answer[$index] = sprintf($responses_all[$index]);
		} 
		$index++;
		
	}
	$response->respond("<speak>".implode(". ", $answer)."</speak>");
    $response->tell();
	return ;

}

function HelpIntent($request, $session, $response) {

    $response->respond("You can ask for the status of Vlohome devices, recent alert" .
        "For example, is the front door locked or what the temperature in the coop. " .
        "Now, what can I help you with?")
     ->reprompt("<speak> I'm sorry I didn't understand that. You can say things like, " .
        "System status <break time=\"0.2s\" /> " .
        "Are there any windows open <break time=\"0.2s\" /> " .
        "What is Playing. Or you can say exit. " .
        "Now, what can I help you with? </speak>");

    $response->ask();
	return;
}

function StopIntent($request, $session, $response) {

	CancelIntent($request, $session, $response);
	return;
}

function CancelIntent($request, $session, $response) {
	$responses = array ("adiós"," adieu"," addio"," adeus","aloha","arrivederci","ciao","auf Wiedersehen","au revoir","bon voyage",
				"sayonara","tot ziens","Goodbye","Farewell","Have a good day","Take care","See you later","Hear you later","OK. have a good one.",
				"Bye bye!","Later!","Later"," man","Have a good one.","So long.","All right then.","Catch you later",
				"Peace!","Peace out.","I'm out!","Smell you later.","Hoşçakal","Güle güle");
				
	$response->sessionAttributes(Array("Launched" => null));
	$response->endSession(true);
    $response->respond($responses[rand(0,count($responses)-1)]);
    $response->tell();
	return;
}

function LaunchRequest($request, $session, $response) {
    // If we wanted to initialize the session to have some attributes we could add those here.
    $response->respond("<speak> Welcome to home automation <break strength=\"weak\" />reporting service, <break time=\"0.3s\" />Please state the nature of your emergency?</speak>")
     ->reprompt("<speak> I'm sorry I didn't understand that. You can say things like, " .
        "System status <break time=\"0.2s\" /> " .
        "Are there any windows open <break time=\"0.2s\" /> " .
        "What is Playing. Or you can say exit. " .
        "Now, what can I help you with? </speak>");

	$response->sessionAttributes(Array("Launched" => true));

	$response->ask();
	return;
}

function SessionEndedRequest($request, $session, $response) {
// logit();
	echo "{}";
}

function defaultFeedbackName($deviceID) {
		$mysql = 'SELECT LOWER(description) as description FROM `ha_voice_names` WHERE default_feedback = 1 and deviceID ='.$deviceID; 
		if ($row = FetchRow($mysql)) {
			return $row['description'];
		} else if ($row = FetchRow('SELECT LOWER(description) as description FROM `ha_voice_names` WHERE deviceID ='.$deviceID)) {
				return $row['description'];		
		} else if ($row = FetchRow('SELECT LOWER(description) as description FROM `ha_mf_devices` WHERE id ='.$deviceID)) {
				return $row['description'];		
		} 
		return "no name";
}

function checkYesterday($timestamp) {

	// echo $timestamp.CRLF;
	$today = new DateTime(); // This object represents current date/time
	$today->setTime( 0, 0, 0 ); // reset time part, to prevent partial comparison

	$match_date = DateTime::createFromFormat("Y-m-d", $timestamp );
	$match_date->setTime( 0, 0, 0 ); // reset time part, to prevent partial comparison

	$diff = $today->diff( $match_date );
	$diffDays = (integer)$diff->format( "%R%a" ); // Extract days count in interval

	switch( $diffDays ) {
		case 0:
			return "last night";
			break;
		case -1:
			return "yesterday evening";
			break;
		case +1:
			return false;
			break;
		default:
			return false;
	}
}
?>
