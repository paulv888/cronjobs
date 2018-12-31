<?php
//define( 'DEBUG_ALX', TRUE );
if (!defined('DEBUG_ALX')) define( 'DEBUG_ALX', FALSE );

define("MY_DEVICE_ID", 283);

require_once 'includes.php';
require_once 'includesAlexa.php';

if (isset($_GET["Message"])) {
	$sdata=$_GET["Message"];
} else {
	$sdata = file_get_contents("php://input");
}

$file = 'alexa_skills.log';
$current = file_get_contents($file);
$current .= date("Y-m-d H:i:s").": ".$sdata."\n";
file_put_contents($file, $current);

if (!($sdata=="")) { 					
	$rcv_message = json_decode($sdata);
	
	$alexaRequest = \Alexa\Request\Request::fromData($rcv_message);

	try {
	  $alexaRequest->validate(array(APP_ID_ME_ECHO,APP_ID_KODI_ECHO,APP_ID_AYNUR_ECHO,APP_ID_PAUL_ECHO));
	} catch(Exception $e) {
	  echo 'Message: ' .$e->getMessage();
	  // Log something
	  exit;
	}
	
	if ($alexaRequest->applicationId == APP_ID_ME_ECHO ) {
		require_once 'includes/skill_me.php';
	} elseif ($alexaRequest->applicationId == APP_ID_KODI_ECHO) {
		require_once 'includes/skill_kodi.php';
	} elseif ($alexaRequest->applicationId == APP_ID_AYNUR_ECHO) {
		require_once 'includes/skill_tell.php';
	} elseif ($alexaRequest->applicationId == APP_ID_PAUL_ECHO) {
		require_once 'includes/skill_tell.php';
	}
	handleRequest($alexaRequest);
	
}
?>
