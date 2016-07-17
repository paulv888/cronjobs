<?php 
require_once 'includes.php';
// TODO:: callerparms needed?
// TODO:: clean up feedback , status and return JSON

//define( 'DEBUG_SESSION', TRUE );
//define( 'DEBUG_VOICE', TRUE );
//define( 'DEBUG_RET', TRUE );
if (isset($_POST['DEBUG_INPUT'])) define( 'DEBUG_INPUT', TRUE );
if (isset($_POST['DEBUG_SESSION'])) define( 'DEBUG_SESSION', TRUE );
if (isset($_POST['DEBUG_VOICE'])) define( 'DEBUG_VOICE', TRUE );
if (isset($_POST['DEBUG_RET'])) define( 'DEBUG_RET', TRUE );
if (!defined('DEBUG_INPUT')) define( 'DEBUG_INPUT', FALSE );
if (!defined('DEBUG_SESSION')) define( 'DEBUG_SESSION', FALSE );
if (!defined('DEBUG_VOICE')) define( 'DEBUG_VOICE', FALSE );
if (!defined('DEBUG_RET')) define( 'DEBUG_RET', FALSE );


session_start();
if (DEBUG_SESSION) {echo 'Prev Session Params '; print_r($_SESSION);}
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > 600)) {
    // last request was more than 30 minutes ago
    session_unset();     // unset $_SESSION variable for the run-time 
    session_destroy();   // destroy session data in storage
}
$_SESSION['LAST_ACTIVITY'] = time(); // update last activity time stamp


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

if (DEBUG_INPUT) echo json_encode($_POST,JSON_UNESCAPED_SLASHES);
if (DEBUG_INPUT) echo (array_key_exists('CONTENT_TYPE', $_SERVER) ? json_encode($_SERVER["CONTENT_TYPE"],JSON_UNESCAPED_SLASHES) : "");

if (isset($_POST["callerID"])) {						// All have to tell where they are from.
	if (DEBUG_INPUT) echo "callerID ".$_POST['callerID'].CRLF;

	if (isset($_SESSION['PARAMS'])){ 
if (DEBUG_SESSION) {echo '$post '; print_r($_POST);}
		$params = array_merge ($_SESSION['PARAMS'], $_POST);
if (DEBUG_SESSION) {echo 'Merged Params '; print_r($params);}
	} else {
		$params = $_POST;
	}
	
	if (!array_key_exists('caller', $params)) $params['caller'] = $params;
	$result = executeVoiceCommand($params);
	if (strpos(strtolower($result), "unable to comply") === false) {
		$_SESSION['PARAMS'] = $params;
	} else {
		session_unset();     // unset $_SESSION variable for the run-time 
		session_destroy();   // destroy session data in storage
	}
	echo $result;
	
	//if (DEBUG_SESSION) {echo 'Storing Params '; print_r($_SESSION);}
}


function executeVoiceCommand(&$callerparams) {

	//$callerparams['schemeID'] = (array_key_exists('schemeID', $callerparams) ? $callerparams['schemeID'] : Null);
	//$callerparams['remotekeyID'] = (array_key_exists('remotekeyID', $callerparams) ? $callerparams['remotekeyID'] : Null);
	$callerparams['commandID'] = (array_key_exists('commandID', $callerparams) ? $callerparams['commandID'] : Null);
	$callerparams['commandvalue'] = (array_key_exists('commandvalue', $callerparams) ? $callerparams['commandvalue'] : Null);
	$callerparams['wherestr'] = (array_key_exists('wherestr', $callerparams) ? $callerparams['wherestr'] : Null);
	//$callerparams['mouse'] = (array_key_exists('mouse', $callerparams) ? $callerparams['mouse'] : Null);
	
	if (DEBUG_RET) echo '<pre>Entry executeCommand - Callerparams: ';
	if (DEBUG_RET) echo print_r($callerparams);
			

	if (DEBUG_RET) echo "MESS_TYPE_SCHEME voice: ".$callerparams['words'].CRLF;
	//$callerparams['commandID'] = COMMAND_RUN_SCHEME;
	//$callerparams['caller'] = $callerparams;
	//$params['words'] = explode(",", $callerparams['words']);
	$feedback['executeVC']=interpretSentence($callerparams);

	if (DEBUG_RET) echo "<pre>Feedback: >";
	if (DEBUG_RET) print_r($feedback);
	if (DEBUG_RET) echo "executeCommand Exit".CRLF;

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
	if (DEBUG_RET) echo "Filtered: >";
	if (DEBUG_RET) print_r($result);

	if ($result != null) {		
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
					$feedback .= getProperty($res['updateStatus']['PropertyID'])['description'].' = '; 
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
							$feedback .= $res['updateStatus']['Status'];
						}
						break;
					case 'DECIMAL':
						$feedback .= $res['updateStatus']['Status'];
						break;
					}
				}
			}
		}
	} else { 
		$feedback = '';
	}
	return $feedback;
}

function interpretSentence(&$params) {
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

	$savewords = $params['words'];
	$feedback = array();
	
	$sentences = explode(',and,', $params['words']);
	foreach ($sentences as $sentence) {
	
		$params['words'] = $sentence;
	
		$replacestr = array(
			'computer,' => '', 
			'light' => 'light', 
			'want' => 'execute', 
			'initiates' => 'execute', 
			'initiate' => 'execute', 
			'executed' => 'execute', 
			'tell,me' => 'report',
			'which' => 'report',
			'what,is' => 'report',
			'is,the' => 'report',
			'at,what,is' => 'report',
			'how,high' => 'report,high',
			'turn' => 'switch',
			'current' => '',
			'yesterday' => '',
			'tomorrow' => '',
			'forecast' => '',

			'for' => '',
			'seconds' => 'second',
			'minutes' => 'minute',
			'hours' => 'hour',
			
			'full' => '100',

			
			'lights' => 'light',
			'sweet' => 'sweep',
			'tech' => 'deck',
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
		
		// Generic cleanup
		foreach ($replacestr as $key => $value) {
			$params['words'] = str_replace($key, $value, $params['words']);
		}
		$params['words'] = explode(',', $params['words']);
		// remove trailing s
		// foreach ($params['words'] as $key => $value) {
			// if (strlen($value) > 4 && substr($value, -1) == 's') $params['words'][$key] = substr($value, 0, -1);
		// }

		$defaultdim = 15;
		
		
		
		// $devices   = FetchRowsIdDescription('SELECT id as deviceID, description FROM ha_mf_devices');

		// foreach ($params['words'] as $word) {
			// $params['words']pattern[] = '/'.$word.'/';
		// }
		// foreach ($params['words'] as $word) {
			// $params['words']filter[] = 'found';
		// }
		
		
		
		if (DEBUG_VOICE) {echo 'Words '; print_r($params['words']);}
		// print_r($params['words']pattern);
		// print_r($actions);
		// print_r($reports);
		// print_r($locations);
		// print_r($devices);
		
		$result = checkAction($params);
		$feedback[] = $result;
		
				// if ($commandID == COMMAND_RUN_SCHEME || $commandID == COMMAND_ON || $commandID == COMMAND_OFF ||
				// $commandID == COMMAND_BRIGHTEN || $commandID == COMMAND_DIM || $commandID == COMMAND_GET_PROPERTIES) {
				// First check to Find scheme, if so execute, still try to set previous device, so we can set and give short to these
				
		if (!array_key_exists('error', $result)) {
			$result = checkValues($params);
			$feedback[] = $result;
		}

		if (!array_key_exists('error', $result)) {
			$result = checkScheme($params);
			$feedback = $result;
		}

		$params['selectsql'] = 'SELECT DISTINCT d.* FROM ha_mf_devices d 
							LEFT JOIN ha_mf_device_group g on d.id = g.deviceID 
							LEFT JOIN ha_mf_device_properties p on d.id = p.deviceID 
							WHERE `inuse` = 1 AND ';
		if (!array_key_exists('error', $result) && (!isset($params['commandID']) || $params['commandID']  == COMMAND_GET_PROPERTIES)) {
			$result = checkProperty($params);
			$feedback[] = $result;
		} else {
			if (DEBUG_VOICE) {echo 'Not checking properties'.CRLF;}
		}
		
		if (!isset($params['schemeID'])) {		// Continue, no scheme found

			// If not reporting then only limit to outgoing
			if ($params['commandID'] != COMMAND_GET_PROPERTIES) $params['selectsql'] .= '`connectionID` <> 1 AND ';
			
			if (!array_key_exists('error', $result)) {
				$result = checkLocations($params);
				$feedback[] = $result;
			} else {
				if (DEBUG_VOICE) {echo 'Error found'.CRLF;}
			}
			if (!array_key_exists('error', $result)) {
				$result = checkGroups($params);
				$feedback[] = $result;
			} else {
				if (DEBUG_VOICE) {echo 'Error found'.CRLF;}
			}
			if (!array_key_exists('error', $result)) {
				$result = checkTypes($params);
				$feedback[] = $result;
			} else {
				if (DEBUG_VOICE) {echo 'Error found'.CRLF;}
			}
					
			if (!array_key_exists('error', $result)) {
				$result = checkDevices($params);
				$feedback[] = $result;
			} else {
				if (DEBUG_VOICE) {echo 'Error found'.CRLF;}
			}
		}
		
		if (DEBUG_VOICE) {echo 'Collected Params '; print_r($params);}

		if (!array_key_exists('error', $result)) {
			if (!isset($params['commandID'])) $params['commandID'] = COMMAND_GET_PROPERTIES;
			if (array_key_exists('devices', $params)) {
				$devices = $params['devices'];
				if ($params['commandID'] == COMMAND_DIM || $params['commandID'] == COMMAND_BRIGHTEN) $params['commandvalue'] = $defaultdim;
				foreach ($devices as $device) {
					$params['deviceID'] = $device['id'];
					$feedback['interpretSentence'.$device['id']] = sendCommand($params);
				}
			} else if (array_key_exists('schemeID', $params)) {		// Scheme
				$feedback['interpretSentence'.$params['schemeID']] = sendCommand($params);
			} else {
				$feedback['error'] = 'Secondary command processors off-line';
			}
		}
		logEvent(array('inout' => COMMAND_IO_RECV, 'callerID' => $params['callerID'], 'deviceID' => (array_key_exists('deviceID',$params) ? $params['deviceID'] : ''),
			'commandID' => COMMAND_VOICE, 'data' => $params['words'], 'message' => $feedback, 'commandstr' => $savewords));
		// $file = 'process1.log';
		// $current = file_get_contents($file);
		// ob_start();
		// var_dump($params);

		// $result = ob_get_clean();
		// $current .= date("Y-m-d H:i:s").": ".$result."\n";
		// file_put_contents($file, $current);
	}
	
	return $feedback;
}


function checkAction(&$params) {
	if (DEBUG_VOICE) echo "Start Action ".CRLF;
	$feedback = array();
	
	$actions         = array('switch', 'set',           'start'     ,'stop'      , 'open'    , 'close'    , 'dim',      'brighten'      , 
							 'lock',     'unlock',     'raise',         'lower',      'pause',       'play',       'show', 'execute'         ,   'report');

	$actioncommands = array(0       , COMMAND_SET_VALUE, COMMAND_PLAY,COMMAND_STOP,COMMAND_ON, COMMAND_OFF, COMMAND_DIM, COMMAND_BRIGHTEN, 
	                         COMMAND_ON,  COMMAND_OFF, COMMAND_BRIGHTEN, COMMAND_DIM, COMMAND_PAUSE, COMMAND_PLAY,  'show', COMMAND_RUN_SCHEME, COMMAND_GET_PROPERTIES);

    $actiondetails   =      array('on'      , 'off');
	$actiondetailcommands = array(COMMAND_ON,  COMMAND_OFF);

	$commandID = 0;
	$action = array_intersect($actions, $params['words']);
	if (DEBUG_VOICE) {echo 'Action '; print_r($action);}
	$actiondetail = array_intersect($actiondetails, $params['words']);
	if (DEBUG_VOICE) {echo 'Action Detail '; print_r($actiondetail);}

	// Check for value anywhere x degrees, 25%
	// if no device or location found then assume last location/device
	// store these in session 
	
	// Find action
	if (count($action) == 1) {			// Found Action
		if (DEBUG_VOICE) echo "Found Action ".current($action).CRLF;
		$commandID = $actioncommands[key($action)];
		$key = array_search(current($action), $params['words']);
		unset($params['words'][$key]);
		foreach ($actiondetail as $key => $value) {
			if ($commandID == 0)  {
				$commandID = $actiondetailcommands[$key];
			} 
			$keyword = array_search($value, $params['words']);
			unset($params['words'][$keyword]);
		}
		if ($commandID == 0) {
			$feedback['error'] = "failed to locate current action parameters";
		}
	} else {
		if (isset($params['commandID'])) {
			// $commandID = $params['lastcommandID'];
			// using as previous, no session yet
		} else {
			//$feedback['error'] = "failed to locate current or previous action parameters";
		}
	}
	$params['commandID'] = $commandID;
	if (DEBUG_VOICE) {echo 'Words Rem after Action '; print_r($params['words']);}
	return $feedback;
}

function checkValues(&$params) {
	if (DEBUG_VOICE) echo "Start Values".CRLF;
	$feedback = array();
    // function get_numerics ($str) {
        // preg_match_all('/\d+/', $str, $matches);
        // return $matches[0];
    // }
	
	//fetch key of the last element of the array.
	end($params['words']);
	$lastElementKey = key($params['words']);
	$newvalue = -1;
	foreach ($params['words'] as $key => $value) {
		if (floatval($value) > 0) { // Found something
			if ($key != $lastElementKey && ($params['words'][$key+1] == 'second' || $params['words'][$key+1] == 'minute' || $params['words'][$key+1] == 'hour')) {
				$params['timervalue'] = $params['words'][$key].$params['words'][$key+1];	// figure out later
				unset($params['words'][$key]);
				unset($params['words'][$key+1]);
			} else { // assume commandvalue, could be multple, take largerst
				if (floatval($value) > $newvalue) {
					$newvalue = floatval($value);
				}
				unset($params['words'][$key]);
			}
		}
	}
	if ($newvalue != -1) $params['commandvalue'] = $newvalue;
	//$params['commandID'] = $commandID;
	if (DEBUG_VOICE) {echo 'Words Rem after Values '; print_r($params['words']);}
	if (DEBUG_VOICE) echo "Found Values ".$params['commandvalue'].CRLF;
	return $feedback;
}


function checkScheme(&$params) {
	if (DEBUG_VOICE) echo "Start Scheme".CRLF;

	$feedback = array();
	// Read from table, only where allow_voice = 1
	$schemename = '';
	$wherestr = '';
	foreach ($params['words'] as $key => $word) { 
		$schemename .=  ' LOWER(name) LIKE "%'.$word.'%" AND';
	}
	if ($schemename != '') {
		$schemename =  substr($schemename, 0, -4);	// remove extra AND
		if ($wherestr != '') $wherestr .= ' AND ';
		$wherestr .= $schemename;
	}
	// Now find a scheme and send
	if ($wherestr != '') {
		$mysql = 'SELECT id, name as description FROM ha_remote_schemes WHERE allow_voice = 1 AND '.$wherestr;
		if (DEBUG_VOICE) var_dump($mysql);
		if ($schemes = FetchRows($mysql)) {
			if (DEBUG_VOICE) {echo 'Schemes '; print_r($schemes); echo 'Count: '.count($schemes).CRLF;}
			if (count($schemes) == 1) {
				$params['schemeID'] = $schemes[0]['id'];
				$params['commandID'] = COMMAND_RUN_SCHEME;
				//$feedback['executeScheme'] = sendCommand($params);
			} else {
				$feedback['error'] = 'failed to find unique programme';
			}
		} else {
			// Not found then carry on
			// $feedback['error'] = 'failed no programme qualifies the given profile';
		}
	} else {
		//$feedback['error'] = 'failed no programme specified';
	}
	if (DEBUG_VOICE) {echo 'Words Rem after Scheme '; print_r($params['words']);}
	if (DEBUG_VOICE) echo "Found Scheme ".$params['schemeID'].CRLF;
	return $feedback;
}

function checkProperty(&$params) {
	$feedback = array();
	// Find property (not required)
	$properties = FetchRowsIdDescription('SELECT id as propertyID, description FROM ha_mi_properties');
	$property = array_intersect($properties, $params['words']);
	if (DEBUG_VOICE) {echo 'Property '; print_r($property);}
	$propertyin = '';
	if (count($property) == 1) {			// Found 1 property (set params propertyID) so only retrieve 1
		$params['propertyID'] = key($property);
	}
	if (count($property) > 0) {			// Found property(s)
		if (DEBUG_VOICE) echo "Doing property ".CRLF;
		foreach ($property as $key => $value) {
			$propertyin .= $key.',';
			$keyword = array_search($value, $params['words']);
			unset($params['words'][$keyword]);
		}
		if ($propertyin != '') {
			$propertyin =  substr($propertyin, 0, -1);	// remove extra comma
			if ($params['wherestr'] != '') $params['wherestr'] .= ' AND ';
			$params['wherestr'] .= ' propertyID IN ('.$propertyin.') ';
			$params['properties'] = $propertyin;
		}
	}  
	if (DEBUG_VOICE) {echo 'Words Rem after Properties '; print_r($params['words']);}
	if (DEBUG_VOICE) echo "Found Properties ".$propertyin.CRLF;
	return $feedback;
}

function checkLocations(&$params) {
	$feedback = array();

	$locationprepositions  = array('on', 'in');

	// Find location (group or location required)
	$locations = FetchRowsIdDescription('SELECT id as locationID, description FROM ha_mf_locations');
	$locationadj = array_intersect($params['words'], $locationprepositions);
	if (DEBUG_VOICE) {echo 'Loc Adj '; print_r($locationadj);}

	// Assume end of sentence
	if (count($locationadj) > 0) {
		// find the index of the key so we can splice
		$index = array_search(array_search(current($locationadj), $params['words']),array_keys($params['words']));
		$locationchunk = array_slice($params['words'], $index + 1);
		$params['words'] = array_slice($params['words'], 0, $index);
		if (DEBUG_VOICE) {echo 'Loc Chunk '; print_r($locationchunk);}
		if (DEBUG_VOICE) {echo 'Rem Words after location taken off'; print_r($params['words']);}
		$location = array_intersect($locations, $locationchunk);
	} else {
		$location = array_intersect($locations, $params['words']);
	}
	if (DEBUG_VOICE) {echo 'Location '; print_r($location);}
	$locationin = '';
	if (count($location) > 0) {			// Found Location(s)
		if (DEBUG_VOICE) echo "Doing location ".key($location).CRLF;
		foreach ($location as $key => $value) {
			$locationin .= $key.',';
			if ($keyword = array_search($value, $params['words'])) unset($params['words'][$keyword]); // could have been in chunk
		}
		if ($locationin != '') {
			$locationin =  substr($locationin, 0, -1);	// remove extra comma
			$params['locations'] = $locationin;
			if ($params['wherestr'] != '') $params['wherestr'] .= ' AND ';
			$params['wherestr'] .= ' locationID IN ('.$locationin.') ';
		}
	}
	
	if (DEBUG_VOICE) {echo 'Words Rem after Location '; print_r($params['words']);}
	if (DEBUG_VOICE) echo "Found Locations ".$locationin.CRLF;
	return $feedback;
}

function checkGroups(&$params) {
	if (DEBUG_VOICE) echo "Start Groups".CRLF;

	$feedback = array();

	// Find group (not required)
	$groups    = FetchRowsIdDescription('SELECT id as groupID, description FROM ha_mf_groups');
	$group = array_intersect($groups, $params['words']);
	if (DEBUG_VOICE) {echo 'Group '; print_r($group);}
	$groupin = '';
	if (count($group) > 0) {			// Found group(s)
		if (DEBUG_VOICE) echo "Doing group ".CRLF;
		foreach ($group as $key => $value) {
			$groupin .= $key.',';
			$keyword = array_search($value, $params['words']);
			unset($params['words'][$keyword]);
			if ($value == 'all' && $params['wherestr'] == '') {		// group is OR all or would be a lot of lights.
				$feedback['error'] = "failed to find unique group";
				break;
			}
		}
		if ($groupin != '') {
			$groupin =  substr($groupin, 0, -1);	// remove extra comma
			if ($params['wherestr'] != '') $params['wherestr'] .= ' AND ';
			$params['groups'] = $groupin;
			$params['wherestr'] .= ' groupID IN ('.$groupin.') ';
		}
	} 
	if (DEBUG_VOICE) {echo 'Words Rem after Groups '; print_r($params['words']);}
	if (DEBUG_VOICE) echo "Found Groups".$groupin.CRLF;
	return $feedback;
}

function checkTypes(&$params) {
	$types         = array('light' => '1, 3', 'hvac' => '17, 18, 19, 31, 32', 'door' => '30, 35');
	
	if (DEBUG_VOICE) echo "Start Types".CRLF;

	$feedback = array();

	// Find type (not required)
	$typein = '';
	foreach ($params['words'] as $key => $word) {
		if (array_key_exists($word, $types)) {	// found type
			$typein .= $types[$word].',';
			unset($params['words'][$key]);
		}
	}
	if ($typein != '') {
		$typein =  substr($typein, 0, -1);	// remove extra comma
		if ($params['wherestr'] != '') $params['wherestr'] .= ' AND ';
		$params['wherestr'] .= ' typeID IN ('.$typein.') ';
		$params['types'] = $typein;
	}
	if (DEBUG_VOICE) {echo 'Words Rem after Types '; print_r($params['words']);}
	if (DEBUG_VOICE) echo "Found Types ".$typein.CRLF;
	return $feedback;
}
	

function checkDevices(&$params) {
	if (DEBUG_VOICE) echo "Start Devices".CRLF;

	$feedback = array();

	// Find device with leftover
	$devicedescription = '';
	foreach ($params['words'] as $key => $word) { 
		$devicedescription .=  ' LOWER(description) LIKE "%'.$word.'%" AND';
	}
	if ($devicedescription != '') {
		$devicedescription =  substr($devicedescription, 0, -4);	// remove extra AND
		if ($params['wherestr'] != '') $params['wherestr'] .= ' AND ';
		$params['wherestr'] .= $devicedescription;
	}

	// Now find a device
	if ($params['wherestr'] != '') {
		$mysql = $params['selectsql'].$params['wherestr'];
		if (DEBUG_VOICE) var_dump($mysql);
		if ($devices = FetchRows($mysql)) {
			$params['devices'] = $devices;
		} else {
			$feedback['error'] = 'failed to locate matching device';
		}
	} else {
		//$feedback['error'] = 'failed to locate devices';
	}
	if (DEBUG_VOICE) {echo 'Words Rem after Device '; print_r($params['words']);}
	if (DEBUG_VOICE) echo "Found Devices".CRLF;
	return $feedback;
}
	
	
	// $mysql = 'SELECT vcID, command, MATCH (command) AGAINST ("'.$params['words'].'" IN BOOLEAN MODE) as score  FROM ha_voice_commands_sentences ORDER BY score DESC LIMIT 1';
	// var_dump($mysql);
	// if ($row = FetchRow($mysql)) {
		// var_dump($row);
	
		// if ($voicecommand = FetchRow('SELECT * FROM ha_voice_commands WHERE id ='.$row['vcID']))
			// $feedback[] = runSteps(array('callerID' => $params['callerID'], 'type'=>'voice', 'parent' => $voicecommand, 'loglevel' => LOGLEVEL_MACRO));
		// else 
			// $feedback['error'] = "failed to locate primary command processors";
	// } else {
		// $feedback['error'] = "failed Unable to comply";
	// }
?>