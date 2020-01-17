<?php 
require_once 'includes.php';

if (isset($_GET['callerID'])) {
	$_POST=$_GET;
}


if (isset($argv)) {
	// var_dump($argv);
	foreach ($argv as $arg) {
		$e=explode("=",$arg);
        if(count($e)==2) {
			// No clue what this was for.... W'll see what breaks how... 
			$_POST[$e[0]]=urldecode($e[1]);
		} else {
			if ($e[0] == "ASYNC_THREAD") {
				$_POST[$e[0]]=true;
				echo 'ASYNC_THREAD true'.CRLF;
			}
		}
	}
}
if (isset($_POST['debug'])) {
	define( 'DEBUG', TRUE );
	$GLOBALS['debug'] = $_POST['debug'];
}
debug($_POST, 'POST');


$input = file_get_contents('php://input');
// ob_start();
// var_dump($input);
// $result = ob_get_clean();
$current = "";
$headers = apache_request_headers();
foreach ($headers as $header => $value) {
	$current .= "$header: $value".CRLF;
}
debug($current, 'headers');

$params = $_POST;
debug($params, 'params');

if (!headers_sent()) {
  if(!isset($_SESSION)) 
    { 
        session_start(); 
    } 
	// unset($_SESSION['PARAMS']);
	debug($_SESSION, 'Prev Session _SESSION');
	if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > 3600)) {
		// last request was more than 30 minutes ago
		session_unset();     // unset $_SESSION variable for the run-time 
		session_destroy();   // destroy session data in storage
	}
}
//
// Set default value for SelectedPlayer
//
if (isset($_SESSION) && array_key_exists('properties', $_SESSION) && array_key_exists('SelectedPlayer', $_SESSION['properties']) && is_numeric($_SESSION['properties']['SelectedPlayer']['value'])) {
	$params['SESSION']['properties']['SelectedPlayer'] = $_SESSION['properties']['SelectedPlayer'];
} else {
	$params['SESSION']['properties']['SelectedPlayer']['value'] = (isset($params['playerID']) ? $params['playerID'] : getCurrentPlayer());
}
$_SESSION['LAST_ACTIVITY'] = time(); // update last activity time stamp
debug($_SESSION, 'Current _SESSION');
session_write_close();

if (isset($params["messagetypeID"]) && isset($params["callerID"])) {						// All have to tell where they are from.

	debug($params, 'params');

	$result = executeCommand($params);
	if (is_array($result)) {
		foreach ($result as $r) {
			if (is_array($r)) {
				if (array_key_exists('redirect', $r)) {
					header($r['redirect']);
				}
				if (array_key_exists('message', $r)) {
					echo $r['message']."\r\n";
				} 
				if (array_key_exists('error', $r)) {
					echo "KO\r\n".$r['error'];
				} else {
					echo "OK\r\n";
				}
				if (array_key_exists('message', $r)) echo $r['message'] ;
			} 
		}
	} else {
		// ob_start("ob_gzhandler");
		echo $result;
		// ob_end_flush();
	}
}
//echo get_current_user().CRLF;
?>
