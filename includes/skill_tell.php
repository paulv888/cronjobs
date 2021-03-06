<?php
define ("SAY_ASKIM", '<phoneme alphabet="ipa" ph="/ə\'ʃkom">askim</phoneme>');

$to_person = null;
$log = array();

function handleRequest($alexaRequest) {
	
	global $log;
	global $to_person;

	$commandMap = [ 
		"RepeatIntent" =>                "409",
		"HelpIntent" =>                  "417",
		"StopIntent" =>                  "418",
		"CancelIntent" =>                "419",
		"LaunchRequest" =>               "420",
		"SessionEndedRequest"   =>       "421",
		"AlertsIntent"   =>              "422",
		"YesIntent"   =>                 "423",
		"ReportIntent"   =>              "446",
		"NoIntent"   =>                  "424"
	];

	$feedback = array();
	$exectime = -microtime(true); 

	$log['callerID'] = MY_DEVICE_ID;
	// $log['message'] = $sdata;
	$log['inout'] = COMMAND_IO_RECV;
	
	$to_person = ($alexaRequest->applicationId == APP_ID_AYNUR_ECHO ? 'Aynur' :'Paul');

	if ($alexaRequest instanceof \Alexa\Request\IntentRequest) {
		
		$log['commandID'] = (array_key_exists($alexaRequest->intentName, $commandMap) ? $commandMap[$alexaRequest->intentName] : COMMAND_UNKNOWN);
		if (function_exists($alexaRequest->intentName)) {
			$fname= $alexaRequest->intentName;

			$response = new \Alexa\Response\Response;
			// Launched, dialog keep open and save Launched
			if (!$alexaRequest->session->new && isset($alexaRequest->session->attributes) && isset($alexaRequest->session->attributes->Launched)) {
				$response->sessionAttributes(Array("Launched" => true));
				$response->endSession(false);
			}
			$feedback = $fname($alexaRequest, $alexaRequest->session, $response);
		} else {
			$response = new \Alexa\Response\Response;
			$response->respond("<speak> I'm sorry I didn't understand that.</speak>")
			 ->reprompt("<speak> Try that again. You can say things like, " .
				"What is for dinner <break time=\"0.2s\" /> " .
				"My tea glass looks empty <break time=\"0.2s\" /> " .
				"What are you doing " .
				"Now, what can I help you with? </speak>");

			$feedback['result'] = $response->ask();
			$feedback['error'] = "Did not understand.";
		}
	}

	if ($alexaRequest instanceof \Alexa\Request\LaunchRequest) {
		$log['commandID'] = (array_key_exists('LaunchRequest', $commandMap) ? $commandMap['LaunchRequest'] : COMMAND_UNKNOWN);
		$response = new \Alexa\Response\Response;
		$feedback = LaunchRequest($alexaRequest, $alexaRequest->session, $response);
	}

	if ($alexaRequest instanceof \Alexa\Request\SessionEndedRequest) {
		$log['commandID'] = (array_key_exists('SessionEndedRequest', $commandMap) ? $commandMap['SessionEndedRequest'] : COMMAND_UNKNOWN);
		$response = new \Alexa\Response\Response;
		$feedback = SessionEndedRequest($alexaRequest, $alexaRequest->session, $response);
	}

	$log['result'] = $feedback;
	if  (!array_key_exists('commandstr', $feedback['result'])) $feedback['result']['commandstr'] ="Not set.";
	$log['commandstr'] = $feedback['result']['commandstr'];
	$exectime += microtime(true);
	$log['exectime'] = $exectime;
	logEvent($log);
	debug($alexaRequest, 'alexaRequest');
	return;
}

function ShortAnswerIntent($request, $session, $response) {
	debug($request, 'request');
	debug($session, 'session');
	debug($response, 'response');

	global $log;

	$finddate = strtolower((isset($request->slots->Date) ? $request->slots->Date : ""));
	$findelse = strtolower((isset($request->slots->CatchAll) ? $request->slots->CatchAll : ""));
	$orgIntent = "NotFound";
	if (!$session->new && isset($session->attributes) && isset($session->attributes->intentSequence)) {
		$orgIntent = $session->attributes->intentSequence;
	}
	
	$log['data'] = "Date: ".$finddate." CatchAll: ".$findelse." orgIntent: ".$orgIntent;
	
	if (empty($findelse) && empty($finddate)) {
		$response->respond("Sorry, I did not understand, please start over.");
		$response->tell();
	}

	switch ($orgIntent) {
		case "RepeatIntent":
			$request->slots->TempDevice = $findelse;
			$request->intentName = $orgIntent;
			break;
		case "AnySecurityOpenIntent":
			$request->slots->Types = $findelse;
			$request->intentName = $orgIntent;
			break;
		case "DeviceStatusIntent":
		case "DeviceReportIntent":
			$request->slots->Device = $findelse;
			$request->intentName = $orgIntent;
			break;
		case "WhatsBedtimeIntent":
			$request->slots->Date = $finddate;
			$request->intentName = $orgIntent;
			break;
		case "AlertsIntent":
			$request->slots->Date = date("Y-m-d");
			$request->intentName = $orgIntent;
			break;
		case "WhatsPlayingIntent":
		case "NotFound":
		default:
			$response->respond("Sorry, I did not understand, please start over.");
			$feedback['result'] = $response->tell();
			$feedback['error'] = "Restart it";
			debug($feedback, 'feedback');
			return $feedback;
	}
	
	$fname= $request->intentName;
	$feedback['result'] = $fname($request, $session, $response);

	debug($feedback, 'feedback');
	return $feedback;
	
}



function RepeatIntent($request, $session, $response) {
	debug($request, 'request');
	debug($session, 'session');
	debug($response, 'response');

	global $log;
	global $to_person;

	$feedback['Name'] = 'RepeatIntent';
	
	$responses1 = array($to_person .", %s");

	$repeatthis = strtolower(isset($request->slots->RepeatThis) ? $request->slots->RepeatThis : "Sorry");
	$log['data'] = "Repeat: ".$repeatthis;
	$log['deviceID'] = MY_DEVICE_ID;
	$log['typeID'] = getDevice($log['deviceID'])['typeID'];

	$answer = "<speak>".sprintf($responses1[rand(0,count($responses1)-1)], $repeatthis)."</speak>";
	
	$response->respond($answer)->withCard(strip_tags($answer));

	$feedback['message'] = $answer;
	$feedback['result'] = $response->tell();
	debug($feedback, 'feedback');
	return $feedback;

}

function HelpIntent($request, $session, $response) {
	debug($request, 'request');
	debug($session, 'session');
	debug($response, 'response');

	global $log;
	global $to_person;

	$feedback['Name'] = 'HelpIntent';

	$log['deviceID'] = MY_DEVICE_ID;
	$log['typeID'] = getDevice($log['deviceID'])['typeID'];

	
    $response->respond("You can ask or tell ".$to_person." anyting" .
        "For example, ".$to_person." is blondie in" .
        "Now, what can I help you with?")
     ->reprompt("<speak> I'm sorry I didn't understand that. You can say things like, " .
        $to_person. ", my tea glass is empty <break time=\"0.2s\" /> " .
        $to_person. ", what is for dinner tonight <break time=\"0.2s\" /> " .
        "Now, what can I help you with? </speak>");

	$feedback['message'] = "Help";
	$feedback['result'] = $response->ask();
	debug($feedback, 'feedback');
	return $feedback;
}

function NoIntent($request, $session, $response) {
	debug($request, 'request');
	debug($session, 'session');
	debug($response, 'response');

	global $log;

	$feedback['Name'] = 'NoIntent';

	$log['deviceID'] = MY_DEVICE_ID;
	$log['typeID'] = getDevice($log['deviceID'])['typeID'];

	$responses = array ("ok");
				
	$response->sessionAttributes(Array("Launched" => null));
	$response->endSession(true);
	$answer = $responses[rand(0,count($responses)-1)];
	$response->respond($answer);
	$feedback['message'] = $answer;
	$feedback['result'] = $response->tell();
	debug($feedback, 'feedback');
	return $feedback;
	
}

function StopIntent($request, $session, $response) {
	debug($request, 'request');
	debug($session, 'session');
	debug($response, 'response');

	global $log;

	$feedback['Name'] = 'StopIntent';

	$log['deviceID'] = MY_DEVICE_ID;
	$log['typeID'] = getDevice($log['deviceID'])['typeID'];

	$feedback['result'] = CancelIntent($request, $session, $response);
	debug($feedback, 'feedback');
	return $feedback;
}

function CancelIntent($request, $session, $response) {
	debug($request, 'request');
	debug($session, 'session');
	debug($response, 'response');

	global $log;

	$feedback['Name'] = 'CancelIntent';

	$log['deviceID'] = MY_DEVICE_ID;
	$log['typeID'] = getDevice($log['deviceID'])['typeID'];

	$responses = array ("adiós"," adieu"," addio"," adeus","aloha","arrivederci","ciao","auf Wiedersehen","au revoir","bon voyage",
				"sayonara","tot ziens","Goodbye","Farewell","Have a good day","Take care","See you later","Hear you later","OK. have a good one.",
				"Bye bye!","Later!","Later"," man","Have a good one.","So long.","All right then.","Catch you later",
				"Peace!","Peace out.","I'm out!","Smell you later.","Hoşçakal","Güle güle");
				
	$response->sessionAttributes(Array("Launched" => null));
	$response->endSession(true);
	$answer = $responses[rand(0,count($responses)-1)];
	$response->respond($answer);
	$feedback['message'] = $answer;
	$feedback['result'] = $response->tell();
	debug($feedback, 'feedback');
	return $feedback;
}

function LaunchRequest($request, $session, $response) {
	debug($request, 'request');
	debug($session, 'session');
	debug($response, 'response');

	global $log;
	global $to_person;

	$feedback['Name'] = 'LaunchRequest';

	$log['deviceID'] = MY_DEVICE_ID;
	$log['typeID'] = getDevice($log['deviceID'])['typeID'];

    // If we wanted to initialize the session to have some attributes we could add those here.
    $response->respond("<speak> Welcome to ".$to_person." interface, <break time=\"0.3s\" />Please state the nature of your emergency?</speak>")
     ->reprompt("<speak> I'm sorry I didn't understand that. You can say things like, " .
        $to_person. ", my tea glass is empty <break time=\"0.2s\" /> " .
        $to_person. ", what is for dinner tonight <break time=\"0.2s\" /> " .
        "Now, what can I help you with? </speak>");

	$response->sessionAttributes(Array("Launched" => true));

	$feedback['message'] = "Launch Prompt";
	$feedback['result'] = $response->ask();
	debug($feedback, 'feedback');
	return $feedback;

}

function SessionEndedRequest($request, $session, $response) {
	debug($request, 'request');
	debug($session, 'session');
	debug($response, 'response');

	global $log;

	$feedback['Name'] = 'SessionEndedRequest';

	$log['deviceID'] = MY_DEVICE_ID;
	$log['typeID'] = getDevice($log['deviceID'])['typeID'];
	$log['data'] = $request->reason;
	echo "{}";
	
	debug($feedback, 'feedback');
	return $feedback;
}

function defaultFeedbackName($deviceID) {
		if ($row = FetchRow('SELECT LOWER(description) as description FROM `ha_voice_names` WHERE default_feedback = 1 and deviceID ='.$deviceID)) {
			return $row['description'];
		} else if ($row = FetchRow('SELECT LOWER(description) as description FROM `ha_voice_names` WHERE deviceID ='.$deviceID)) {
				return $row['description'];		
		} else if ($row = FetchRow('SELECT LOWER(description) as description FROM `ha_mf_devices` WHERE id ='.$deviceID)) {
				return $row['description'];		
		} 
		return "no name";
}

function dateToSpeach($timestamp) {

	// echo $timestamp.CRLF;
	$today = new DateTime(); // This object represents current date/time
	$today->setTime( 0, 0, 0 ); // reset time part, to prevent partial comparison

	$match_date = DateTime::createFromFormat("Y-m-d", $timestamp );
	$match_date->setTime( 0, 0, 0 ); // reset time part, to prevent partial comparison

	$diff = $today->diff( $match_date );
	$diffDays = (integer)$diff->format( "%R%a" ); // Extract days count in interval

	switch( $diffDays ) {
		case 0:
			return "today";
			break;
		case -1:
			return "yesterday";
			break;
		case +1:
			return "for tomorrow";
			break;
		case -2:
		case -3:
		case -4:
		case -5:
		case -6:
			return "on last ".$match_date->format('l');
			break;
		default:
			return "on ".$match_date->format('F, jS');
			break;
	}
}

function findDeviceByName($find) {
	$mysql = 'SELECT deviceID, LOWER(description) as description FROM `ha_voice_names` WHERE deviceID > 0 and LOWER(description) LIKE "%'.$find.'%"'; 
	$found = FetchRows($mysql);
	if (empty($found)) {		//		Try by description
		$mysql = 'SELECT id as deviceID, LOWER(description) as description FROM `ha_mf_devices` WHERE LOWER(description) LIKE "%'.$find.'%" OR LOWER(shortdesc) LIKE "%'.$find.'%"'; 
		$found = FetchRows($mysql);
	}
	return $found;
}
?>
