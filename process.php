<?php 
require_once 'includes.php';
// define( 'DEBUG_INPUT', TRUE );
if (isset($_POST['DEBUG_INPUT'])) define( 'DEBUG_INPUT', TRUE );
if (!defined('DEBUG_INPUT')) define( 'DEBUG_INPUT', FALSE );

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

$params = $_POST;

if (!headers_sent()) {
	session_start();
	// unset($_SESSION['PARAMS']);
	if (DEBUG_INPUT) {echo '<pre>Prev Session Params '; print_r($_SESSION);echo '</pre>';}
	if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > 600)) {
		// last request was more than 30 minutes ago
		session_unset();     // unset $_SESSION variable for the run-time 
		session_destroy();   // destroy session data in storage
	}
}
//
// Set default value for SelectedPlayer
//
if (isset($_SESSION) && array_key_exists('properties', $_SESSION) && array_key_exists('SelectedPlayer', $_SESSION['properties']) ) {
	$params['SESSION']['properties']['SelectedPlayer'] = $_SESSION['properties']['SelectedPlayer'];
} else {
	$params['SESSION']['properties']['SelectedPlayer']['value'] = DEVICE_DEFAULT_PLAYER;
}
$_SESSION['LAST_ACTIVITY'] = time(); // update last activity time stamp
session_write_close();

if (isset($params["messagetypeID"]) && isset($params["callerID"])) {						// All have to tell where they are from.

	if (DEBUG_INPUT) {echo "<pre>before executeCommand ";print_r($params);echo "</pre>";}
	
	$result = executeCommand($params);
	if (is_array($result)) 
//		print_r($result);
		echo "ok";
	else
		echo $result;
}
?>