<?php 
require_once 'includes.php';
//define( 'DEBUG_INPUT', TRUE );
if (isset($_POST['DEBUG_INPUT'])) define( 'DEBUG_INPUT', TRUE );
if (!defined('DEBUG_INPUT')) define( 'DEBUG_INPUT', FALSE );

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

if (DEBUG_INPUT) echo json_encode($_POST);
if (DEBUG_INPUT) echo (array_key_exists('CONTENT_TYPE', $_SERVER) ? json_encode($_SERVER["CONTENT_TYPE"]) : "");

if (isset($_POST["messagetypeID"]) && isset($_POST["callerID"])) {						// All have to tell where they are from.

	if (DEBUG_INPUT) echo "callerID ".$_POST['callerID']." ".$_POST['messagetypeID'].CRLF;
	
	$result = executeCommand($_POST);
	if (is_array($result)) 
		print_r($result);
	else
		echo $result;
}

// Public (Timers, Triggers, cameras)
function executeCommand($callerparams) {
// New entry point for execute chain, from external i.e. remote
// This will store and keep original caller params

	/* Get the Keys Scheme or Device */
	//$callerparams['deviceID'] = (array_key_exists('deviceID', $callerparams) ? $callerparams['deviceID'] : Null);
	//if (IsNullOrEmptyString($callerparams['deviceID'])) $callerparams['deviceID'] = Null;
	$callerparams['schemeID'] = (array_key_exists('schemeID', $callerparams) ? $callerparams['schemeID'] : Null);
	$callerparams['remotekeyID'] = (array_key_exists('remotekeyID', $callerparams) ? $callerparams['remotekeyID'] : Null);
	$callerparams['commandID'] = (array_key_exists('commandID', $callerparams) ? $callerparams['commandID'] : Null);
	$callerparams['commandvalue'] = (array_key_exists('commandvalue', $callerparams) ? $callerparams['commandvalue'] : Null);
	$callerparams['selection'] = (array_key_exists('selection', $callerparams) ? $callerparams['selection'] : Null);
	$callerparams['mouse'] = (array_key_exists('mouse', $callerparams) ? $callerparams['mouse'] : Null);
	
	if ($callerparams['callerID'] == DEVICE_REMOTE) header('Content-type: application/json'); 

	$feedback['messagetypeID'] = $callerparams['messagetypeID'];

	if (DEBUG_FLOW) echo '<pre>Entry executeCommand - Callerparams: ';
	if (DEBUG_FLOW) echo print_r($callerparams);
			
	$feedback['show_result'] = true;
	switch ($callerparams['messagetypeID'])
	{
	case MESS_TYPE_REMOTE_KEY:    // Key pressed on remote
		foreach ($callerparams['keys'] AS $remotekeyID) {
			unset($callerparams['keys']);
			$rowkeys = FetchRow("SELECT * FROM ha_remote_keys where id =".$remotekeyID);
			$callerparams['schemeID'] = $rowkeys['schemeID'];
			$feedback['show_result'] = $rowkeys['show_result'];
			$callerparams['deviceID'] = $rowkeys['deviceID'];
		
			if ($callerparams['schemeID'] <=0) {  													// not a scheme, Execute
				if ($callerparams['commandID']===NULL) {
					if ($callerparams['mouse']=='down') { 
						$callerparams['commandID']=$rowkeys['commandIDdown'];
						if (is_null($callerparams['commandID'])) {
							return false;
						}
					} else {
						$callerparams['commandID']=$rowkeys['commandID'];
					}
				}
			} else {
				$callerparams['commandID'] = COMMAND_RUN_SCHEME;
			}
			if (!array_key_exists('caller', $callerparams)) $callerparams['caller'] = $callerparams;
			$feedback['SendCommand'][]=sendCommand($callerparams);
		}
		break;
	case MESS_TYPE_SCHEME:
		if (DEBUG_FLOW) echo "MESS_TYPE_SCHEME scheme: ".$callerparams['schemeID'].CRLF;
		$callerparams['commandID'] = COMMAND_RUN_SCHEME;
		$callerparams['caller'] = $callerparams;
		$feedback['SendCommand'][]=sendCommand($callerparams);
		break;
	case MESS_TYPE_COMMAND:
		// Comes either with deviceID or keys
		if (array_key_exists('keys', $callerparams)) {
			if ($callerparams['commandID'] == COMMAND_GET_VALUE) {
				foreach ($callerparams['keys'] AS $remotekeyID) {
					unset($callerparams['keys']);
					$rowkeys = FetchRow("SELECT * FROM ha_remote_keys where id =".$remotekeyID);
					if (!empty($rowkeys['deviceID']) && !empty($rowkeys['propertyID'])) {
						$propertyID = $rowkeys['propertyID'];
						$devicesprop[$rowkeys['deviceID'].$propertyID]['deviceID'] = $rowkeys['deviceID'];
						$devicesprop[$rowkeys['deviceID'].$propertyID]['propertyID'] = $propertyID;
					}
				}
				foreach ($devicesprop as $devprop) {
					$feedback[]['updateStatus'] = getStatusLink($devprop);
				}
			} else {
				foreach ($callerparams['keys'] AS $remotekeyID) {
					unset($callerparams['keys']);
					$rowkeys = FetchRow("SELECT * FROM ha_remote_keys where id =".$remotekeyID);
					$callerparams['deviceID'] = $rowkeys['deviceID'];
					if (!array_key_exists('caller', $callerparams)) $callerparams['caller'] = $callerparams;
					$feedback['SendCommand'][]=sendCommand($callerparams);
				}
			}
		} else {			
			if (DEBUG_FLOW) echo "MESS_TYPE_COMMAND commandID: ".$callerparams['commandID'].CRLF;
			if (DEBUG_FLOW && isset($deviceID)) echo "deviceID: ".$callerparams['deviceID'].CRLF;
			$callerparams['caller'] = $callerparams;
			$feedback['SendCommand'][]=sendCommand($callerparams);
		}
		break;
	}

	if (DEBUG_RETURN) echo "<pre>Feedback: >";
	if (DEBUG_RETURN) print_r($feedback);
	if (DEBUG_RETURN) echo "executeCommand Exit".CRLF;

	if ($callerparams['callerID'] == DEVICE_REMOTE) {
		$result = RemoteKeys($feedback);
		$encode = true;
	} else {
		$result = $feedback;
	}
	
	// print_r($result);
	if (isset($encode)) {
		$result = json_encode($result);
		switch (json_last_error()) {
			case JSON_ERROR_NONE:
				//echo ' - No errors';
			break;
			case JSON_ERROR_DEPTH:
				echo ' - Maximum stack depth exceeded';
			break;
			case JSON_ERROR_STATE_MISMATCH:
				echo ' - Underflow or the modes mismatch';
			break;
			case JSON_ERROR_CTRL_CHAR:
				echo ' - Unexpected control character found';
			break;
			case JSON_ERROR_SYNTAX:
				echo ' - Syntax error, malformed JSON';
			break;
			case JSON_ERROR_UTF8:
				echo ' - Malformed UTF-8 characters, possibly incorrectly encoded';
			break;
			default:
				echo ' - Unknown error';
			break;
		}
	}
	// echo "<pre>";

	return 	$result;
			
}

function RemoteKeys($in) {
	if ($in['show_result']) {
		$filterkeep = array( 'Status' => 1, 'DeviceID' => 1, 'PropertyID' => 1, 'message' => 1, 'Link' => 1, 'error' => 1, 'Timer Remaining' => 1);
		doFilter($in, array( 'updateStatus' => 1,  'groupselect' => 1, 'message' => 1), $filterkeep, $result);
	} else {
		$filterkeep = array( 'Status' => 1, 'DeviceID' => 1, 'PropertyID' => 1, 'Link' => 1, 'error' => 1, 'Timer Remaining' => 1);
		doFilter($in, array( 'updateStatus' => 1,  'groupselect' => 1), $filterkeep, $result);
	}
	if (DEBUG_RETURN) echo "Filtered: >";
	if (DEBUG_RETURN) print_r($result);
	if ($result != null) {
		$feedback = Array();
		foreach ($result as $key => $res) {
			if (array_key_exists('message', $res)) {
				if (is_array($feedback) && array_key_exists('message', $feedback)) {
					if (strlen(str_replace(' ', '', preg_replace( "/\r|\n/", "", $res['message']))) > 0) $feedback['message'].= $res['message'].' ';
				} else {
					if (strlen(str_replace(' ', '', preg_replace( "/\r|\n/", "", $res['message']))) > 0) $feedback['message'] = $res['message'].' ';
				}
			} else if (array_key_exists('error', $res)) {
				if (is_array($feedback) && array_key_exists('error', $feedback)) {
					$feedback['message'].= $res['error'].' ';
				} else {
					$feedback['message'] = $res['error'].' ';
				}
			} else {
				if (array_key_exists('updateStatus', $res)) $node = 'updateStatus';
				if (array_key_exists('groupselect', $res)) $node = 'groupselect';
				if (array_key_exists('DeviceID',$res[$node])) {
					$wherestr = (array_key_exists('PropertyID', $res[$node]) ? ' AND propertyID ='.$res[$node]['PropertyID'] : ''); // Not getting propID for Link
					$reskeys = mysql_query('SELECT * FROM ha_remote_keys where deviceID ='.$res[$node]['DeviceID'].$wherestr);
					while ($rowkeys = mysql_fetch_array($reskeys)) {
						if ($rowkeys['inputtype']== "button" || $rowkeys['inputtype']== "btndropdown" || $rowkeys['inputtype']== "display") {
							$feedback[][$node] = true;
							$last_id=GetLastKey($feedback);
							$feedback[$last_id]["remotekey"] = $rowkeys['id'];
							if ($node == 'updateStatus') {
								$propertyID = (empty($rowkeys['propertyID']) ? '123' : $rowkeys['propertyID']);
								if (array_key_exists('Status', $res['updateStatus']) && $res['updateStatus']['PropertyID'] == $propertyID) {
									if ($res['updateStatus']['Status'] == STATUS_OFF) {    			// if monitoring status and command not off then new status is on (dim/bright)
										$feedback[$last_id]["status"]="off";
									} elseif ($res['updateStatus']['Status'] == STATUS_UNKNOWN) {
										$feedback[$last_id]["status"]="unknown";
									} elseif ($res['updateStatus']['Status'] == STATUS_ON) {
										$feedback[$last_id]["status"]="on";
									} elseif ($res['updateStatus']['Status'] == STATUS_ERROR) {
										$feedback[$last_id]["status"]="error";
									} else { 										// else assume a value
										$feedback[$last_id]["status"]="undefined";
									}
								}
								
								if (array_key_exists('Link',$res['updateStatus'])) {
									$feedback[$last_id]['link'] = ($res['updateStatus']['Link'] == LINK_UP ? '' : ($res['updateStatus']['Link'] == LINK_WARNING ? 'link-warning' : 'link-down'));
								}
								
								$starttext = getDisplayText($rowkeys);
								$text = $starttext;
								if($rowkeys['inputtype']== "btndropdown" || $rowkeys['inputtype']== "button") {
									if (array_key_exists('Timer Remaining',$res['updateStatus'])) $text.=' ('.$res['updateStatus']['Timer Remaining'].'min)';
								}
								$feedback[$last_id]["text"] = replacePlaceholder($text, Array('deviceID' => $res['updateStatus']['DeviceID']));
							}
						}
					}
				}
			}
		}
	} else { 
		$feedback['message'] = '';
	}
	return array_map("unserialize", array_unique(array_map("serialize", $feedback)));
}

?>