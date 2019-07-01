<?php
//$GLOBALS['debug'] = 10;

define ("SAY_ASKIM", '<phoneme alphabet="ipa" ph="/ə\'ʃkom">askim</phoneme>');
define ("SOUND_DEVICE_ID", 241);

$log = array();

function handleRequest($alexaRequest) {
	debug($alexaRequest, 'alexaRequest');

	$commandMap = [ 
		"ShortAnswerIntent" 	=>          "409",
		"PlayArtistIntent" 		=> 		    "431",
		"WhatsPlayingIntent" 	=> 			"414",
		"ShowIntent" 			=> 			"448",
		"PlayPreviousSongIntent" =>         "433",
		"PlayNextSongIntent" 	=>          "432",
		"PlayLoudIntent" 		=>          "434",
		"PlayExtraLoudIntent" 	=>          "435",
		"StartPlaylistIntent" 	=>          "436",
		"PlaySongIntent" 		=>          "437",
		"HelpIntent" 			=>          "417",
		"StopIntent" 			=>          "418",
		"CancelIntent" 			=>          "419",
		"LaunchRequest" 		=>          "420",
		"SessionEndedRequest"   =>  	    "421",
		"AlertsIntent"   		=>          "422",
		"YesIntent"   			=>          "423",
		"NoIntent"   			=>           "424"
	];

	$feedback = array();
	$exectime = -microtime(true); 
	
	$log['callerID'] = MY_DEVICE_ID;
	$log['inout'] = COMMAND_IO_RECV;
	
	
 	if ($alexaRequest instanceof \Alexa\Request\IntentRequest) {
		$log['commandID'] = (array_key_exists($alexaRequest->intentName, $commandMap) ? $commandMap[$alexaRequest->intentName] : COMMAND_UNKNOWN);
		$fname = ($alexaRequest->intentName == "PlayLatestArtistIntent" ? "PlayArtistIntent"  : $alexaRequest->intentName);
		if (function_exists($fname)) {

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
				"Play Enigma<break time=\"0.2s\" />" .
				"Now, what can I help you with? </speak>");

			$feedback['result']['ask'] = $response->ask();
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

	$log['result'][] = $feedback;
	if  (!array_key_exists('commandstr', $feedback['result'])) $feedback['result']['commandstr'] ="Not set.";
	$log['commandstr'] = $feedback['result']['commandstr'];
	$exectime += microtime(true);
	$log['exectime'] = $exectime;
	logEvent($log);

	debug($log, 'log');
	return;
}

function ShortAnswerIntent($request, $session, $response) {
	debug($request, 'request');
	debug($session, 'session');
	debug($response, 'response');

	global $log;

	$findartist = strtolower((isset($request->slots->Artist) ? $request->slots->Artist : ""));
	$findsong = strtolower((isset($request->slots->Song) ? $request->slots->Song : (isset($request->slots->Artist) ? $request->slots->Artist : (isset($request->slots->CatchAll) ? $request->slots->CatchAll : ""))));;
	$findelse = strtolower((isset($request->slots->CatchAll) ? $request->slots->CatchAll : ""));
	$showwhat = strtolower((isset($request->slots->ShowWhat) ? $request->slots->ShowWhat : ""));
	$orgIntent = "NotFound";
	if (!$session->new && isset($session->attributes) && isset($session->attributes->intentSequence)) {
		$orgIntent = $session->attributes->intentSequence;
	}
	
	$log['data'] = "Artist: ".$findartist." CatchAll: ".$findelse." orgIntent: ".$orgIntent;
	
	if ($findsong == 'cancel' || $findsong == 'stop' || $findelse == 'cancel' || $findelse == 'stop') 
		CancelIntent($request, $session, $response);
	switch ($orgIntent) {
		case "PlayArtistIntent":
			$request->slots->Artist = $findartist;
			$request->intentName = $orgIntent;
			break;
		case "PlaySongIntent":
			$request->slots->Song = $findsong;
			$request->intentName = $orgIntent;
			break;
		case "ShowIntent":
			$request->slots->ShowWhat = $showwhat;
			$request->intentName = $orgIntent;
			break;
		case "WhatsPlayingIntent":
		case "NotFound":
		default:
			$response->respond("Sorry, I did not understand, please start over.");
			$feedback['result']['tell'] = $response->tell();
			$feedback['error'] = "Restart it";
			debug($feedback, 'feedback');
			return $feedback;
	}
	
	debug($request, 'request');
	$fname= $request->intentName;
	$feedback['result'][$request->intentName] = $fname($request, $session, $response);

	debug($feedback, 'feedback');
	return $feedback;
	
}

function PlaySongIntent($request, $session, $response) {
	debug($request, 'request');
	debug($session, 'session');
	debug($response, 'response');

	global $log;
	$feedback['Name'] = 'PlaySongIntent';

	$responses = array ("Found %s songs in %s, starting");

	$find = strtolower((isset($request->slots->Song) ? $request->slots->Song : ""));

	if (empty($find)) {
		$response->respond(sprintf("Which song?"))
		 ->reprompt("Sorry what song are you looking for?");
		$response->sessionAttributes(Array("intentSequence"=>"PlaySongIntent"));
		$feedback['result']['ask'] = $response->ask();
		$feedback['error'] = "Which song?";
		debug($feedback, 'feedback');
		return $feedback;
	}
	
	$log['data'] = "Song Title: ".$find;

	$found = findSongsByTitle($find);
	debug($found, 'found');
	if (empty($found)) { 
		$response->respond(sprintf("I could not find song with %s, please try again?",  $find))
		 ->reprompt("Which song are you looking for?");
		$response->sessionAttributes(Array("intentSequence"=>"PlaySongIntent"));
		$feedback['result']['ask'] = $response->ask();
		$feedback['error'] = "What song?";
		debug($feedback, 'feedback');
		return $feedback;
	}

	$deviceProperties = getDeviceProperties(array('deviceID'=>getCurrentPlayer()));
	$log['deviceID'] = getCurrentPlayer();
	$log['typeID'] = getDevice($log['deviceID'])['typeID'];

	if (count($found) == 1) { 	// play 1 song
		$feedback['result']['action'] = executeCommand(array('callerID' => MY_DEVICE_ID, 'messagetypeID' => MESS_TYPE_SCHEME, 'deviceID' => getCurrentPlayer(),  'schemeID'=>232, 'commandvalue'=>$found[0]['file']));
	} else {
		$songs = '#EXTM3U'."\n";
		foreach ($found as $song) {
			$songs .= $song['file']."\n";
		}
		
		$playlistname = 'AlexaList';
		$playlist = LOCAL_PLAYLISTS.'/'.$playlistname.'.m3u';
		file_put_contents($playlist, $songs);
		
		
		//$log = executeCommand(Array('callerID'=>MY_DEVICE_ID,'messagetypeID'=>"MESS_TYPE_SCHEME",'schemeID'=>221, 'commandvalue'=>'AlexaList.m3u'));
		$feedback['result']['action'] = executeCommand(array('callerID' => MY_DEVICE_ID, 'messagetypeID' => MESS_TYPE_SCHEME, 'deviceID' => getCurrentPlayer(),  'schemeID'=>221, 'commandvalue'=>$playlistname));
	}
	
	// $status = getFeedbackStatus($deviceID, $deviceProperties[$found]['value']);
	$answer = sprintf($responses[rand(0,count($responses)-1)], count($found), $find);
	$response->respond($answer);
	$feedback['message'] = $answer;
	$feedback['result']['tell'] = $response->tell();
	debug($feedback, 'feedback');
	return $feedback;

}


function PlayArtistIntent($request, $session, $response) {
	debug($request, 'request');
	debug($session, 'session');
	debug($response, 'response');

$playLists = [ 
	"turkish"			=>  ["Smart", "Turkish"],
	"popular"			=>	["Smart", "Popular"],
	"dance"				=>	["Smart", "Dance"],
	"spanish"			=>	["Genre", ""],
	"paul favorites"	=> 	["m3u", "Paul Favorites"],
	"paul popular"		=> 	["m3u", "Paul Popular"],
	"favorites"			=> 	["m3u", "Paul Favorites"],
	"dance favorites"	=> 	["m3u", "Paul Favorites Dance"],
	"party favorites"	=> 	["m3u", "Paul Favorites Party"]
	];
	
	global $log;
	$feedback['Name'] = 'PlayArtistIntent';

	$responses = array ("Found %s songs by %s, starting");

	$find = strtolower((isset($request->slots->Artist) ? $request->slots->Artist : ""));

	//$log['data'] = "TempDevice: ".$find;

	if (empty($find)) {
		$response->respond(sprintf("Which artist?"))
		 ->reprompt("Sorry which artist you want?");
		$response->sessionAttributes(Array("intentSequence"=>"PlayArtistIntent"));
		$feedback['result']['ask'] = $response->ask();
		$feedback['error'] = "Which Artist?";
		debug($feedback, 'feedback');
		return $feedback;
	}

	$deviceProperties = getDeviceProperties(array('deviceID'=>getCurrentPlayer()));
	$log['deviceID'] = getCurrentPlayer();
	$log['typeID'] = getDevice($log['deviceID'])['typeID'];
	
	if (array_key_exists(strtolower($find), $playLists )) {
		switch ($playLists[$find][0]) {
		case "Smart":
			$feedback['result']['action'] = executeCommand(array('callerID' => MY_DEVICE_ID, 'messagetypeID' => MESS_TYPE_SCHEME, 'deviceID' => getCurrentPlayer(),  'schemeID'=>214, 'commandvalue'=>$playLists[$find][1]));
			$answer = "Loaded playlist ".$playLists[$find][1]; 
			break;
		case "m3u":
			$feedback['result']['action'] = executeCommand(array('callerID' => MY_DEVICE_ID, 'messagetypeID' => MESS_TYPE_SCHEME, 'deviceID' => getCurrentPlayer(),  'schemeID'=>221, 'commandvalue'=> $playLists[$find][1]));
			$answer = "Loaded playlist ". $playLists[$find][1]; 
			break;
		case "Genre":
			break;
		}
	} else {
		$log['data'] = "Artist: ".$find;

		if ($request->intentName == "PlayArtistIntent") {
			$found = findSongsByArtist($find);
			$feedback['Name'] = 'PlayArtistIntent';
		} else {
			$found = findSongsByArtist($find, true);
			$feedback['Name'] = 'PlayLatestArtistIntent';
		}
		debug($found, 'found');
		if (empty($found)) { 
			$response->respond(sprintf("I could not find artist %s, please try again?",  $find))
			 ->reprompt("Sorry which artist you want?");
			$response->sessionAttributes(Array("intentSequence"=>"PlayArtistIntent"));
			$feedback['result']['ask'] = $response->ask();
			$feedback['error'] = "Which Artist?";
			debug($feedback, 'feedback');
			return $feedback;
		}


		if (count($found) == 1) { 	// play 1 song
			$feedback['result']['action'] = executeCommand(array('callerID' => MY_DEVICE_ID, 'messagetypeID' => MESS_TYPE_SCHEME, 'deviceID' => getCurrentPlayer(),  'schemeID'=>232, 'commandvalue'=>$found[0]['file']));
		} else {
			$songs = '#EXTM3U'."\n";
			foreach ($found as $song) {
				$songs .= $song['file']."\n";
			}
			
			$playlistname = 'AlexaList';
			$playlist = LOCAL_PLAYLISTS.'/'.$playlistname.'.m3u';
			file_put_contents($playlist, $songs);
			
			
			//$log = executeCommand(Array('callerID'=>MY_DEVICE_ID,'messagetypeID'=>"MESS_TYPE_SCHEME",'schemeID'=>221, 'commandvalue'=>'AlexaList.m3u'));
			if ($request->intentName == "PlayArtistIntent") {
				$feedback['result']['action'] = executeCommand(array('callerID' => MY_DEVICE_ID, 'messagetypeID' => MESS_TYPE_SCHEME, 'deviceID' => getCurrentPlayer(),  'schemeID'=>221, 'commandvalue'=>$playlistname));
			} else {
				$feedback['result']['action'] = AddPlaylistOneByOne($found);
				//$feedback['result']['action'] = executeCommand(array('callerID' => MY_DEVICE_ID, 'messagetypeID' => MESS_TYPE_SCHEME, 'deviceID' => getCurrentPlayer(),  'schemeID'=>285, 'commandvalue'=>$playlistname));
			}
		}
		
		// $status = getFeedbackStatus($deviceID, $deviceProperties[$found]['value']);
		$answer = sprintf($responses[rand(0,count($responses)-1)], count($found), $find);
	}
	
	$response->respond($answer);
	$feedback['message'] = $answer;
	$feedback['result']['tell'] = $response->tell();
	debug($feedback, 'feedback');
	return $feedback;

}



function PlayPreviousSongIntent($request, $session, $response) {
	debug($request, 'request');
	debug($session, 'session');
	debug($response, 'response');

	global $log;
	
	$feedback['Name'] = 'PlayPreviousSongIntent';
	
	$responses = array("ok");

	$deviceProperties = getDeviceProperties(array('deviceID'=>getCurrentPlayer()));
	$log['deviceID'] = getCurrentPlayer();
	$log['typeID'] = getDevice($log['deviceID'])['typeID'];
	
	debug($deviceProperties, 'deviceProperties');
	$feedback['result']['action'] = executeCommand(array('callerID' => MY_DEVICE_ID, 'messagetypeID' => MESS_TYPE_SCHEME, 'deviceID' => getCurrentPlayer(),  'schemeID'=>283));
	
	$answer = sprintf($responses[rand(0,count($responses)-1)]);
	$response->respond($answer);
	$feedback['message'] = $answer;
	$feedback['result']['tell'] = $response->tell();
	debug($feedback, 'feedback');
	return $feedback;

}

function PlayNextSongIntent($request, $session, $response) {
	debug($request, 'request');
	debug($session, 'session');
	debug($response, 'response');

	global $log;
	
	$feedback['Name'] = 'PlayNextSongIntent';
	
	$responses = array("ok");

	$deviceProperties = getDeviceProperties(array('deviceID'=>getCurrentPlayer()));
	$log['deviceID'] = getCurrentPlayer();
	$log['typeID'] = getDevice($log['deviceID'])['typeID'];
	
	debug($deviceProperties, 'deviceProperties');
	$feedback['result']['action'] = executeCommand(array('callerID' => MY_DEVICE_ID, 'messagetypeID' => MESS_TYPE_COMMAND, 'deviceID' => getCurrentPlayer(),  'commandID'=>30));
	
	$answer = sprintf($responses[rand(0,count($responses)-1)]);
	$response->respond($answer);
	$feedback['message'] = $answer;
	$feedback['result']['tell'] = $response->tell();
	debug($feedback, 'feedback');
	return $feedback;

}

function PlayLoudIntent($request, $session, $response) {
	debug($request, 'request');
	debug($session, 'session');
	debug($response, 'response');

	global $log;
	
	$feedback['Name'] = 'PlayLoudIntent';
	
	$responses = array("ok");

	$deviceProperties = getDeviceProperties(array('deviceID'=>getCurrentPlayer()));
	$log['deviceID'] = getCurrentPlayer();
	$log['typeID'] = getDevice($log['deviceID'])['typeID'];
	
	$feedback['result']['action'] = executeCommand(array('callerID' => MY_DEVICE_ID, 'messagetypeID' => MESS_TYPE_SCHEME, 'deviceID' => getCurrentPlayer(),  'schemeID'=>251));
	
	$answer = sprintf($responses[rand(0,count($responses)-1)]);
	$response->respond($answer);
	$feedback['message'] = $answer;
	$feedback['result']['tell'] = $response->tell();
	debug($feedback, 'feedback');
	return $feedback;

}

function PlayExtraLoudIntent($request, $session, $response) {
	debug($request, 'request');
	debug($session, 'session');
	debug($response, 'response');

	global $log;
	
	$feedback['Name'] = 'PlayExtraLoudIntent';
	
	$responses = array("ok");

	$deviceProperties = getDeviceProperties(array('deviceID'=>getCurrentPlayer()));
	$log['deviceID'] = getCurrentPlayer();
	$log['typeID'] = getDevice($log['deviceID'])['typeID'];
	
	$feedback['result']['action'] = executeCommand(array('callerID' => MY_DEVICE_ID, 'messagetypeID' => MESS_TYPE_SCHEME, 'deviceID' => getCurrentPlayer(),  'schemeID'=>284));
	
	$answer = sprintf($responses[rand(0,count($responses)-1)]);
	$response->respond($answer);
	$feedback['message'] = $answer;
	$feedback['result']['tell'] = $response->tell();
	debug($feedback, 'feedback');
	return $feedback;

}


function WhatsPlayingIntent($request, $session, $response) {
	debug($request, 'request');
	debug($session, 'session');
	debug($response, 'response');

	global $log;
	
	$feedback['Name'] = 'WhatsPlayingIntent';
	
	$responses = array("<speak>This is <break time=\"0.2s\" /> %s <break time=\"0.2s\" />by %s.</speak>","<speak>We're listening to <break time=\"0.2s\" /> %s  <break time=\"0.2s\" /> by %s </speak>", "<speak>%s  <break time=\"0.2s\" /> by %s.</speak>");

	$deviceProperties = getDeviceProperties(array('deviceID'=>getCurrentPlayer()));
	$log['deviceID'] = getCurrentPlayer();
	$log['typeID'] = getDevice($log['deviceID'])['typeID'];
	
	debug($deviceProperties, 'deviceProperties');
	
	$answer = sprintf($responses[rand(0,count($responses)-1)], $deviceProperties['Title']['value'], $deviceProperties['Artist']['value']);
	$response->respond($answer);
	$feedback['message'] = $answer;
	$feedback['result']['tell'] = $response->tell();
	debug($feedback, 'feedback');
	return $feedback;

}

function ShowIntent($request, $session, $response) {
	debug($request, 'request');
	debug($session, 'session');
	debug($response, 'response');

	global $log;
	
	$feedback['Name'] = 'ShowIntent';
	
	$responses = array("<speak>Please check the T.V. for a image off the Run Camera</speak>");

//	$deviceProperties = getDeviceProperties(array('deviceID'=>getCurrentPlayer()));
	$deviceProperties = getDeviceProperties(array(259));
	$log['deviceID'] = getCurrentPlayer();
	$log['typeID'] = getDevice($log['deviceID'])['typeID'];
	
	debug($deviceProperties, 'deviceProperties');
	
	$answer = sprintf($responses[rand(0,count($responses)-1)]);
	$response->respond($answer);
	$feedback['message'] = $answer;
	$feedback['result']['tell'] = $response->tell();

	$feedback['result']['display'] = executeCommand(array('callerID' => MY_DEVICE_ID, 'messagetypeID' => MESS_TYPE_SCHEME, 'deviceID' => getCurrentPlayer(),  'schemeID'=>315));

	debug($feedback, 'feedback');
	return $feedback;

}

function HelpIntent($request, $session, $response) {
	debug($request, 'request');
	debug($session, 'session');
	debug($response, 'response');

	global $log;

	$feedback['Name'] = 'HelpIntent';

	$log['deviceID'] = MY_DEVICE_ID;
	$log['typeID'] = getDevice($log['deviceID'])['typeID'];

	
    $response->respond("You can start a play list like start random or start turkish " .
        "Or you can play songs by a artist or ask for a certain song, i.e. play chantaje by shakira" .
        "Now, what can I help you with?")
     ->reprompt("<speak> I'm sorry I didn't understand that. You can say things like, " .
        "Play Enigma <break time=\"0.2s\" /> " .
        "Start Paul Favorites <break time=\"0.2s\" /> " .
        "What is Playing. Or you can say exit. " .
        "Now, what can I help you with? </speak>");

	$feedback['message'] = "Help";
	$feedback['result']['ask'] = $response->ask();
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
	$feedback['result']['tell'] = $response->tell();
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

	$feedback['result']['cancel'] = CancelIntent($request, $session, $response);
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
	$feedback['result']['tell'] = $response->tell();
	debug($feedback, 'feedback');
	return $feedback;
}

function LaunchRequest($request, $session, $response) {
	debug($request, 'request');
	debug($session, 'session');
	debug($response, 'response');

	global $log;

	$feedback['Name'] = 'LaunchRequest';

	$log['deviceID'] = MY_DEVICE_ID;
	$log['typeID'] = getDevice($log['deviceID'])['typeID'];

    // If we wanted to initialize the session to have some attributes we could add those here.
    $response->respond("<speak> Welcome to home automation <break strength=\"weak\" />Kodi service, <break time=\"0.3s\" />What can I play for you today?</speak>")
     ->reprompt("<speak> I'm sorry I didn't understand that. You can say things like, " .
        "System status <break time=\"0.2s\" /> " .
        "Are there any windows open <break time=\"0.2s\" /> " .
        "What is Playing. Or you can say exit. " .
        "Now, what can I help you with? </speak>");

	$response->sessionAttributes(Array("Launched" => true));

	$feedback['message'] = "Launch Prompt";
	$feedback['result']['ask'] = $response->ask();
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

function AddPlaylistOneByOne($playlist) {
	$result[] = executeCommand(array('callerID' => MY_DEVICE_ID, 'messagetypeID' => MESS_TYPE_COMMAND, 'deviceID' => getCurrentPlayer(),  'commandID'=>58));
	$result[] = executeCommand(array('callerID' => MY_DEVICE_ID, 'messagetypeID' => MESS_TYPE_COMMAND, 'deviceID' => getCurrentPlayer(),  'commandID'=>358));
	$result[] = executeCommand(array('callerID' => MY_DEVICE_ID, 'messagetypeID' => MESS_TYPE_COMMAND, 'deviceID' => getCurrentPlayer(),  'commandID'=>364, 'commandvalue'=>'false'));
	
	foreach ($playlist as $song) {
	//getJsonRemote(host, port, 'Playlist.Add' , { 'playlistid' : 0 , 'item' : {'file' : '%s' % item } } )
		$result[] = executeCommand(array('callerID' => MY_DEVICE_ID, 'messagetypeID' => MESS_TYPE_COMMAND, 'deviceID' => getCurrentPlayer(),  'commandID'=>377, 'commandvalue'=>$song['file']));
	}
	$result[] = executeCommand(array('callerID' => MY_DEVICE_ID, 'messagetypeID' => MESS_TYPE_COMMAND, 'deviceID' => getCurrentPlayer(),  'commandID'=>350));

	return $result;
}

function findSongsByArtist($find, $latest = false) {

	$where = str_replace('SEARCHFIELD','`artist`',breakWords($find));
	
	$mysql = 'SELECT * FROM `xbmc_video_musicvideos` WHERE '.$where; 
	if (!$latest) 
		$mysql .= 'ORDER BY RAND() LIMIT 500'; 
	else
		$mysql .= 'ORDER BY dateAdded DESC LIMIT 5'; 
	$found = FetchRows($mysql);
	return $found;
//33034 	397 	Gravity Of Love 	Enigma 	Classical 	musicvideo 	-1 	smb://SRVMEDIA/media/My Music Videos/Classical/Eni... 	35 	Enigma - Gravity Of Love.mp4 	6 	2016-12-22 02:41:08 	2012-01-21 20:12:58 	1234567890
//33035 	398 	Indian Chanting 	Enigma 	Classical 	musicvideo 	-1 	smb://SRVMEDIA/media/My Music Videos/Classical/Eni... 	35 	Enigma - Indian Chanting.flv 	7 	2017-07-08 13:13:30 	2011-08-14 12:36:06 	1234567890
}

function findSongsByTitle($find) {
	
	$where = str_replace('SEARCHFIELD','`title`',breakWords($find));

	$mysql = 'SELECT * FROM `xbmc_video_musicvideos` WHERE  '.$where.' ORDER BY RAND() LIMIT 500'; 
	$found = FetchRows($mysql);
	return $found;
//33034 	397 	Gravity Of Love 	Enigma 	Classical 	musicvideo 	-1 	smb://SRVMEDIA/media/My Music Videos/Classical/Eni... 	35 	Enigma - Gravity Of Love.mp4 	6 	2016-12-22 02:41:08 	2012-01-21 20:12:58 	1234567890
//33035 	398 	Indian Chanting 	Enigma 	Classical 	musicvideo 	-1 	smb://SRVMEDIA/media/My Music Videos/Classical/Eni... 	35 	Enigma - Indian Chanting.flv 	7 	2017-07-08 13:13:30 	2011-08-14 12:36:06 	1234567890
}

function findSongsByGenre($find) {
	$where = str_replace('SEARCHFIELD','`genre`',breakWords($find));

	$mysql = 'SELECT * FROM `xbmc_video_musicvideos` WHERE '.$where.' ORDER BY RAND() LIMIT 500'; 
	$found = FetchRows($mysql);
	return $found;
//33034 	397 	Gravity Of Love 	Enigma 	Classical 	musicvideo 	-1 	smb://SRVMEDIA/media/My Music Videos/Classical/Eni... 	35 	Enigma - Gravity Of Love.mp4 	6 	2016-12-22 02:41:08 	2012-01-21 20:12:58 	1234567890
//33035 	398 	Indian Chanting 	Enigma 	Classical 	musicvideo 	-1 	smb://SRVMEDIA/media/My Music Videos/Classical/Eni... 	35 	Enigma - Indian Chanting.flv 	7 	2017-07-08 13:13:30 	2011-08-14 12:36:06 	1234567890
}

function breakWords($find) {

	$words = explode(' ', $find);
	
	$x = 0;
	$wordCount = count($words);
	do {
		if ($x == 0) {
			$where =  ($wordCount == 1 ? 'SEARCHFIELD LIKE "'.$words[$x].'"' : 'SEARCHFIELD LIKE "'.$words[$x].' %"');
		}
		if ($x > 0) {
			$where .=  ($x < $wordCount-1 ? ' AND SEARCHFIELD LIKE "% '.$words[$x].' %"' : ' AND SEARCHFIELD LIKE "% '.$words[$x].'"');
		}
		$x++;
	} while ($x <= $wordCount - 1);
	
	return $where;
	
}

?>
