<?php 
require_once 'includes.php';
// TODO:: callerparms needed?
// TODO:: clean up feedback , status and return JSON

//define( 'DEBUG_INPUT', TRUE );
//define( 'DEBUG_FLOW', TRUE );
//define( 'DEBUG_DEVICES', TRUE );
//define( 'DEBUG_RETURN', TRUE );
//define( 'DEBUG_VOICE', TRUE );
if (isset($_POST['DEBUG_INPUT'])) define( 'DEBUG_INPUT', TRUE );
if (isset($_POST['DEBUG_FLOW'])) define( 'DEBUG_FLOW', TRUE );
if (isset($_POST['DEBUG_DEVICES'])) define( 'DEBUG_DEVICES', TRUE );
if (isset($_POST['DEBUG_RETURN'])) define( 'DEBUG_RETURN', TRUE );
if (isset($_POST['DEBUG_VOICE'])) define( 'DEBUG_VOICE', TRUE );
if (!defined('DEBUG_INPUT')) define( 'DEBUG_INPUT', FALSE );
if (!defined('DEBUG_FLOW')) define( 'DEBUG_FLOW', FALSE );
if (!defined('DEBUG_DEVICES')) define( 'DEBUG_DEVICES', FALSE );
if (!defined('DEBUG_RETURN')) define( 'DEBUG_RETURN', FALSE );
if (!defined('DEBUG_VOICE')) define( 'DEBUG_VOICE', FALSE );


// session_start();
// print_r($_SESSION);
// if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > 1800)) {
    // // last request was more than 30 minutes ago
    // session_unset();     // unset $_SESSION variable for the run-time 
    // session_destroy();   // destroy session data in storage
// }
// print_r($_SESSION);
// $_SESSION['LAST_ACTIVITY'] = time(); // update last activity time stamp


if (DEBUG_INPUT) {
	$input = file_get_contents('php://input');
	ob_start();
	var_dump($input);
	$result = ob_get_clean();
	$file = 'process.log';
	$current = file_get_contents($file);
	$headers = apache_request_headers();
	foreach ($headers as $header => $value) {
		$current .= "$header: $value <br />\n";
	}
	$current .= date("Y-m-d H:i:s").": ".$result."\n";
	$current .= date("Y-m-d H:i:s").": ".print_r($_POST,true)."\n";
	file_put_contents($file, $current);
}

if (isset($argv)) {
	var_dump($argv);
	foreach ($argv as $arg) {
		$e=explode("=",$arg);
        if(count($e)==2) {
			$_POST[$e[0]]=urldecode($e[1]);
		} else {
			if ($e[0] == "ASYNC_THREAD") {
				$_POST[$e[0]]=true;
				echo 'ASYNC_THREAD true'.CRLF;
			}
		}
	}
}

if (isset($_GET['callerID'])) {
	//$sdata=json_decode($_GET['Message'], $assoc = TRUE); 
	$_POST=$_GET;
}

if (DEBUG_FLOW) echo json_encode($_POST);
if (DEBUG_FLOW) echo (array_key_exists('CONTENT_TYPE', $_SERVER) ? json_encode($_SERVER["CONTENT_TYPE"]) : "");

if (isset($_POST["callerID"])) {						// All have to tell where they are from.

	if (DEBUG_FLOW) echo "callerID ".$_POST['callerID'].CRLF;
	
	echo executeVoiceCommand($_POST);
	
}


function executeVoiceCommand($callerparams) {

	$callerparams['schemeID'] = (array_key_exists('schemeID', $callerparams) ? $callerparams['schemeID'] : Null);
	$callerparams['remotekeyID'] = (array_key_exists('remotekeyID', $callerparams) ? $callerparams['remotekeyID'] : Null);
	$callerparams['commandID'] = (array_key_exists('commandID', $callerparams) ? $callerparams['commandID'] : Null);
	$callerparams['commandvalue'] = (array_key_exists('commandvalue', $callerparams) ? $callerparams['commandvalue'] : Null);
	$callerparams['selection'] = (array_key_exists('selection', $callerparams) ? $callerparams['selection'] : Null);
	$callerparams['mouse'] = (array_key_exists('mouse', $callerparams) ? $callerparams['mouse'] : Null);
	
	$feedback['messagetypeID'] = $callerparams['messagetypeID'];

	if (DEBUG_FLOW) echo '<pre>Entry executeCommand - Callerparams: ';
	if (DEBUG_FLOW) echo print_r($callerparams);
			

	if (DEBUG_FLOW) echo "MESS_TYPE_SCHEME voice: ".$callerparams['words'].CRLF;
	//$callerparams['commandID'] = COMMAND_RUN_SCHEME;
	//$callerparams['caller'] = $callerparams;
	//$words = explode(",", $callerparams['words']);
	$feedback['Sentence']=interpretSentence($callerparams);
	break;

	if (DEBUG_RETURN) echo "<pre>Feedback: >";
	if (DEBUG_RETURN) print_r($feedback);
	if (DEBUG_RETURN) echo "executeCommand Exit".CRLF;

	$result = translateHuman($feedback);
	
	return 	$result;
			
}

function translateHuman($in) {
/*Filtered: >Array
(
    [0] => Array
        (
            [message] => 

        )
    [1] => Array
        (
            [updateStatus] => Array
                (
                    [DeviceID] => 1
                    [PropertyID] => 123
                    [Status] => 1
                )
        )
)*/
	$filterkeep = array('Status' => 1, 'DeviceID' => 1, 'PropertyID' => 1, 'Datatype' => 1, 'error' => 1);
	doFilter($in, array( 'updateStatus' => 1, 'message' => 1), $filterkeep, $result);
	if (DEBUG_RETURN) echo "Filtered: >";
	if (DEBUG_RETURN) print_r($result);

	$feedback = "";
	foreach ($result as $key => $res) {
		if (array_key_exists('message', $res)) {
			$res['message'] = preg_replace( '/\r|\n/', '', $res['message']);
			$res['message'] = trim(preg_replace( '/\{(.*?)\}/', '', $res['message']));
			if (strlen($res['message']) > 0) {
				if ($feedback != '') $feedback .= ' and ';
				$feedback .= $res['message'].' ';
			}
		} else if (array_key_exists('error', $res)) {
			if ($feedback != '') $feedback .= ' and ';
			if (is_array($feedback) && array_key_exists('error', $feedback)) {
				$feedback .= ' and '.$res['error'];
			} else {
				$feedback = 'Unable to comply, '.$res['error'].' ';
			}
		} else if (array_key_exists('updateStatus', $res)) {
			if ($feedback != '') $feedback .= ' and ';
			if (array_key_exists('DeviceID',$res['updateStatus'])) $feedback .= getDevice($res['updateStatus']['DeviceID'])['description'].' ';
			if (array_key_exists('PropertyID',$res['updateStatus'])) 
				$feedback .= getProperty($res['updateStatus']['PropertyID'])['description'].' equals '; 
			if (array_key_exists('Status', $res['updateStatus'])) {
				switch ($res['updateStatus']['Datatype'])
				{
				case 'BINARY':
					if ($res['updateStatus']['Status'] == STATUS_OFF) {   
						$feedback .= "off ";
					} elseif ($res['updateStatus']['Status'] == STATUS_UNKNOWN) {
						$feedback .= "unknown ";
					} elseif ($res['updateStatus']['Status'] == STATUS_ON) {
						$feedback .= "on ";
					} elseif ($res['updateStatus']['Status'] == STATUS_ERROR) {
						$feedback .= "error ";
					} else { 										// else assume a value
						$feedback .= "undefined ";
					}
					break;
				case 'DECIMAL':
					$feedback .= $res['updateStatus']['Status'];
					break;
				}
			}
		}
	}
	
	// if (array_key_exists('message', $feedback) && trim($feedback['message']) == '') unset($feedback['message']);
	// if (array_key_exists('error', $feedback) && trim($feedback['error']) == '') unset($feedback['error']);
	// print_r($feedback);
	return $feedback;
}

function interpretSentence($params) {
//
//	Split on and, next command
//

	// Types
	// lights = type switch and candencendent
	// dim, brighten only on candencendent
	// lower, raise on temperature volume, (with value)
	// 
	// $properties = what is / report  the battery level of the front door
	// temperature downstairs, upstairs
	// what is the temperature in the living room / downstairs
	// are there any windows open
	// is the front door locked

	$feedback = array();
// Split by " and "
	$words = $params['words'];
	// switch,on,the,recessed,lights,in,the,living,room
	$replacestr = array(
		'computer,' => '', 
		'lights' => 'light', 
		'want' => 'execute', 
		'initiate' => 'execute', 
		'initiates' => 'execute', 
		'tell,me' => 'report',
		'which' => 'report',
		'status' => 'report,status',
		'what,is' => 'report',
		'at,what,is' => 'report',
		'how,high' => 'report,high',
		'turn' => 'switch',
		'set' => 'open',
		'switzerland' => 'switch', 
		'life' => 'light', 
		'lite' => 'light', 
		'race' => 'raise', 
		'recessed' => 'recess', 
		'air,conditioner' => 'hvac', 
		'airco' => 'hvac',
		'heater' => 'hvac', 
		'battery,level' => 'batterylevel', 
		'high,' => 'level,',
		'the,' => '',
		'of,' => '',
		'is,' => '',
		'are,' => '',
		'to' => '2',
		'-' => '2',
		'i,' => '',
		',,' => ','
		
	);
	foreach ($replacestr as $key => $value) {
		$words = str_replace($key, $value, $words);
	}
	
	$words = explode(',', $words);
	
	//if (strpos($words, "all ") === false) $words.= " -all";
	$actions         = array('switch', 'start'     ,'stop'      ,'open'    , 'close'    , 'dim',      'brighten'      , 
							 'lock',     'unlock',     'raise',         'lower',      'pause',       'play',         'show',  'execute'       ,'report');
	$actioncommands = array(0       , COMMAND_PLAY,COMMAND_STOP,COMMAND_ON, COMMAND_OFF, COMMAND_DIM, COMMAND_BRIGHTEN, 
	                         COMMAND_ON,  COMMAND_OFF, COMMAND_BRIGHTEN, COMMAND_DIM, COMMAND_PAUSE, COMMAND_PLAY,  'show', COMMAND_RUN_SCHEME, COMMAND_GET_PROPERTIES);
	$actiondetails   =      array('on'      , 'off');
	$actiondetailcommands = array(COMMAND_ON,  COMMAND_OFF);
	$types         = array('light' => '1, 3', 'hvac' => '17, 18, 19, 31, 32', 'door' => '30, 35');

	$groups        = array('all' => '*' );

	$locationadjectives = array('on', 'in');
//	$locationadjectives = array('on', 'in');

	$defaultdim = 15;
	
	
	
	// $devices   = FetchRowsIdDescription('SELECT id as deviceID, description FROM ha_mf_devices');

	// foreach ($words as $word) {
		// $wordspattern[] = '/'.$word.'/';
	// }
	// foreach ($words as $word) {
		// $wordsfilter[] = 'found';
	// }
	
	
	
	
	if (DEBUG_VOICE) {echo 'Words '; print_r($words);}
	// print_r($wordspattern);
	// print_r($actions);
	// print_r($reports);
	// print_r($locations);
	// print_r($devices);
	
	$action = array_intersect($actions, $words);
	if (DEBUG_VOICE) {echo 'Action '; print_r($action);}
	$actiondetail = array_intersect($actiondetails, $words);
	if (DEBUG_VOICE) {echo 'Action Detail '; print_r($actiondetail);}
	// search group or type?
	
	// Check for value anywhere x degrees, 25%
	// if no device or location found then assume last location/device
	// store these in session 
	
	// Find action (required)
	if (count($action) == 1) {			// Found Action
		if (DEBUG_VOICE) echo "Doing Action ".key($action).CRLF;
		$commandID = $actioncommands[key($action)];
		$key = array_search(current($action), $words);
		unset($words[$key]);
		foreach ($actiondetail as $key => $value) {
			if ($commandID == 0)  {
				$commandID = $actiondetailcommands[$key];
			} 
			$keyword = array_search($value, $words);
			unset($words[$keyword]);
		}
		if ($commandID == 0) {
			$feedback['error'] = "unable to locate current action parameters";
		}
	} else {
		if (isset($lastcommandID)) {
			$commandID = $lastcommandID;
		} else {
			$feedback['error'] = "unable to locate current or previous action parameters";
		}
	}
	if (DEBUG_VOICE) {echo 'Words Rem'; print_r($words);}

	$wherestr = '';
	if (!array_key_exists('error', $feedback)) {
		if ($commandID == COMMAND_ON || $commandID == COMMAND_OFF || $commandID == COMMAND_BRIGHTEN || $commandID == COMMAND_DIM || $commandID == COMMAND_GET_PROPERTIES) {
		
			if ($commandID == COMMAND_GET_PROPERTIES) {
				// Find property (not required)
				$properties = FetchRowsIdDescription('SELECT id as propertyID, description FROM ha_mi_properties');
				if (!array_key_exists('error', $feedback)) {
					$property = array_intersect($properties, $words);
					if (DEBUG_VOICE) {echo 'Property '; print_r($property);}
					$propertyin = '';
					if (count($property) > 0) {			// Found property(s)
						if (DEBUG_VOICE) echo "Doing property ".CRLF;
						foreach ($property as $key => $value) {
							$propertyin .= $key.',';
							$keyword = array_search($value, $words);
							unset($words[$keyword]);
						}
						if ($propertyin != '') {
							$propertyin =  substr($propertyin, 0, -1);	// remove extra comma
							if ($wherestr != '') $wherestr .= ' AND ';
							$wherestr .= ' propertyID IN ('.$propertyin.') ';
						}
					} 
				}
				if (DEBUG_VOICE) {echo 'Words Rem'; print_r($words);}
				$selectsql = 'SELECT DISTINCT d.*, p.propertyID FROM ha_mf_devices d 
									LEFT JOIN ha_mf_device_group g on d.id = g.deviceID 
									LEFT JOIN ha_mf_device_properties p on d.id = p.deviceID 
									WHERE `inuse` = 1 AND ';
			} else {
				$selectsql = 'SELECT DISTINCT d.* FROM ha_mf_devices d 
									LEFT JOIN ha_mf_device_group g on d.id = g.deviceID 
									WHERE `connectionID` <> 1 AND `inuse` = 1 AND ';
			}
		
			// Find location (group or location required)
			$locations = FetchRowsIdDescription('SELECT id as locationID, description FROM ha_mf_locations');
			$locationadj = array_intersect($words, $locationadjectives);
			if (DEBUG_VOICE) {echo 'Loc Adj '; print_r($locationadj);}

			// Assume end of sentence
			if (count($locationadj) > 0) {
				$key = array_search(current($locationadj), $words);
				if ($key>0) $key--;
				$locationchunk = array_slice($words, $key);
				$words = array_slice($words, 0, $key-1);
				if (DEBUG_VOICE) {echo 'Loc Chunk '; print_r($locationchunk);}
				$location = array_intersect($locations, $locationchunk);
			} else {
				$location = array_intersect($locations, $words);
			}
			if (DEBUG_VOICE) {echo 'Location '; print_r($location);}
			$locationin = '';
			if (count($location) > 0) {			// Found Location(s)
				if (DEBUG_VOICE) echo "Doing location ".key($location).CRLF;
				foreach ($location as $key => $value) {
					$locationin .= $key.',';
					if ($keyword = array_search($value, $words)) unset($words[$keyword]); // could have been in chunk
				}
				if ($locationin != '') {
					$locationin =  substr($locationin, 0, -1);	// remove extra comma
					if ($wherestr != '') $wherestr .= ' AND ';
					$wherestr .= ' locationID IN ('.$locationin.') ';
				}
			}
			if (DEBUG_VOICE) {echo 'Words Rem'; print_r($words);}
			
			// Find group (not required)
			$groups    = FetchRowsIdDescription('SELECT id as groupID, description FROM ha_mf_groups');
			if (!array_key_exists('error', $feedback)) {
				$group = array_intersect($groups, $words);
				if (DEBUG_VOICE) {echo 'Group '; print_r($group);}
				$groupin = '';
				if (count($group) > 0) {			// Found group(s)
					if (DEBUG_VOICE) echo "Doing group ".CRLF;
					foreach ($group as $key => $value) {
						$groupin .= $key.',';
						$keyword = array_search($value, $words);
						unset($words[$keyword]);
						if ($value == 'all' && $wherestr == '') {		// group is OR all or would be a lot of lights.
							$feedback['error'] = "please narrow parameters";
							break;
						}
					}
					if ($groupin != '') {
						$groupin =  substr($groupin, 0, -1);	// remove extra comma
						if ($wherestr != '') $wherestr .= ' AND ';
						$wherestr .= ' groupID IN ('.$groupin.') ';
					}
				} 
			}
			if (DEBUG_VOICE) {echo 'Words Rem'; print_r($words);}

			// Check for previous group or location?

			// if (count($words == 0) && $wherestr ='') { // no group or loc found
				// if (isset($lastlocationstr)) {
					// $locationstr = $lastlocationstr;
				// } 
				// if (isset($lastgroupstr)) {
					// $groupstr = $lastgroupstr;
				// }
				// if ($locationstr == '' && $groupstr == '') { // no group or loc found
					// $feedback['error'] = "unable to locate current or previous location or group parameters";
				// }
			// }

			// Find type (not required)
			if (!array_key_exists('error', $feedback)) {
				$typein = '';
				foreach ($words as $key => $word) {
					if (array_key_exists($word, $types)) {	// found type
						$typein .= $types[$word].',';
						unset($words[$key]);
					}
				}
				if ($typein != '') {
					$typein =  substr($typein, 0, -1);	// remove extra comma
					if ($wherestr != '') $wherestr .= ' AND ';
					$wherestr .= ' typeID IN ('.$typein.') ';
				}
				if (DEBUG_VOICE) {echo 'Words Rem'; print_r($words);}
			}
			
			// Find device with leftover
			if (!array_key_exists('error', $feedback)) {
				$devicedescription = '';
				foreach ($words as $key => $word) { 
					$devicedescription .=  ' LOWER(description) LIKE "%'.$word.'%" AND';
				}
				if ($devicedescription != '') {
					$devicedescription =  substr($devicedescription, 0, -4);	// remove extra AND
					if ($wherestr != '') $wherestr .= ' AND ';
					$wherestr .= $devicedescription;
				}

				// Now find a device and send
				if ($wherestr != '') {
					$mysql = $selectsql.$wherestr ;
					if (DEBUG_VOICE) var_dump($mysql);
					if ($devices = FetchRows($mysql)) {
						foreach ($devices as $device) {
							if (DEBUG_VOICE) {echo 'Device '; print_r($device);}
							if (!array_key_exists('caller', $params)) $params['caller'] = $params;
							if ($params['commandvalue'] == '' && ($commandID == COMMAND_DIM || $commandID == COMMAND_BRIGHTEN)) $params['commandvalue'] = $defaultdim;
							$params['deviceID'] = $device['id'];
							$params['commandID'] = $commandID;
							if (array_key_exists('propertyID', $device)) $params['propertyID'] = $device['propertyID'];
							$feedback['interpretSentence'][] = sendCommand($params);
						}
					} else {
						$feedback['error'] = 'no device qualifies the given profile';
					}
				} else {
					$feedback['error'] = 'no devices specified';
				}
			}
			if (!array_key_exists('error', $feedback)) {
				logEvent(array('inout' => COMMAND_IO_RECV, 'callerID' => $params['callerID'], 'deviceID' => $params['deviceID'], 'commandID' => COMMAND_VOICE, 'data' => $params['words'], 'message' => $feedback, 'commandstr' => $params['words']));
			}
		} else if ($commandID == COMMAND_RUN_SCHEME) {
			// Find scheme (required)
			//$schemes = FetchRowsIdDescription('SELECT id as schemeID, name as description FROM ha_remote_schemes');
			if (!array_key_exists('error', $feedback)) {
				$wherestr = '';
				$schemename = '';
				foreach ($words as $key => $word) { 
					$schemename .=  ' LOWER(name) LIKE "%'.$word.'%" AND';
				}
				if ($schemename != '') {
					$schemename =  substr($schemename, 0, -4);	// remove extra AND
					if ($wherestr != '') $wherestr .= ' AND ';
					$wherestr .= $schemename;
				}
				// Now find a scheme and send
				if ($wherestr != '') {
					$mysql = 'SELECT id, name as description FROM ha_remote_schemes WHERE '.$wherestr;
					if (DEBUG_VOICE) var_dump($mysql);
					if ($schemes = FetchRows($mysql)) {
						if (DEBUG_VOICE) {echo 'Schemes '; print_r($schemes); echo 'Count: '.count($schemes).CRLF;}
						if (count($schemes) == 1) {
							if (!array_key_exists('caller', $params)) $params['caller'] = $params;
							$params['schemeID'] = $schemes[0]['id'];
							$params['commandID'] = $commandID;
							$feedback['interpretSentence'][] = sendCommand($params);
						} else {
							$feedback['error'] = 'Please restate a single programme';
						}
					} else {
						$feedback['error'] = 'no programme qualifies the given profile';
					}
				} else {
					$feedback['error'] = 'no programme specified';
				}
			}
			if (!array_key_exists('error', $feedback)) {
				logEvent(array('inout' => COMMAND_IO_RECV, 'callerID' => $params['callerID'], 'commandID' => COMMAND_VOICE, 'data' => $params['words'], 'message' => $feedback, 'commandstr' => $params['words']));
			}
		}
	}
		
	if (array_key_exists('error', $feedback)) {
		logEvent(array('inout' => COMMAND_IO_RECV, 'callerID' => $params['callerID'], 'commandID' => COMMAND_VOICE, 'data' => $params['words'], 'message' => $feedback, 'commandstr' => $params['words']));
	}
	// $file = 'process1.log';
	// $current = file_get_contents($file);
	// ob_start();
	// var_dump($params);

	// $result = ob_get_clean();
	// $current .= date("Y-m-d H:i:s").": ".$result."\n";
	// file_put_contents($file, $current);

	return $feedback;
}




	// $mysql = 'SELECT vcID, command, MATCH (command) AGAINST ("'.$words.'" IN BOOLEAN MODE) as score  FROM ha_voice_commands_sentences ORDER BY score DESC LIMIT 1';
	// var_dump($mysql);
	// if ($row = FetchRow($mysql)) {
		// var_dump($row);
	
		// if ($voicecommand = FetchRow('SELECT * FROM ha_voice_commands WHERE id ='.$row['vcID']))
			// $feedback[] = runSteps(array('callerID' => $params['callerID'], 'type'=>'voice', 'parent' => $voicecommand, 'loglevel' => LOGLEVEL_MACRO));
		// else 
			// $feedback['error'] = "Unable to comply, unable to locate primary command processors";
	// } else {
		// $feedback['error'] = "Unable to comply";
	// }
?>