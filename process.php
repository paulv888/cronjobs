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
	
	echo executeCommand($_POST);
}
?>