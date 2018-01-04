<?php
require_once 'includes.php';

//print_r(executeCommand(Array('callerID'=>164,'messagetypeID'=>"MESS_TYPE_SCHEME",'schemeID'=>304)));

$mysql="SELECT...";

        $command = array(
		'callerID' => 164, 
		'messagetypeID' => MESS_TYPE_SCHEME,
		'schemeID'=>SCHEME_ALERT_PDO, 
		'commandvalue'=> $mysql.'|PDOblah blah'
	);

//.CRLF.'<pre>'.prettyPrint(json_encode($e,JSON_UNESCAPED_SLASHES)).'</pre>');
//        $feedback['result'][] = sendCommand($command);

        $feedback['result'][] = executeCommand($command);

        echo "<pre>";
        echo print_r($feedback);
        echo "</pre>";

?>
