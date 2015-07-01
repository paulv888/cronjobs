<?php
//define("DEBUG_ALERT", TRUE);
define("DEBUG_ALERT", FALSE);

function Alerts($alert_textID , $params ){

	
	$params['deviceID'] = (array_key_exists('deviceID', $params) ? $params['deviceID'] : 'NULL');
	$params['priorityID']  = (array_key_exists('priorityID', $params) ? $params['priorityID'] : 'NULL');
	if (array_key_exists('callerID', $params)) {
		if ($cd = FetchRow("SELECT description FROM ha_mf_devices WHERE ha_mf_devices.id =".$params['callerID']))  {
			$params['callerID___description']= $cd['description'];
		}
	}
	if ($params['deviceID'] != Null) {
		if ($cd = FetchRow("SELECT description FROM ha_mf_devices WHERE ha_mf_devices.id =".$params['deviceID']))  {
			$params['deviceID___description']= $cd['description'];
		}
	}
	
	$rowtext = FetchRow("SELECT * FROM ha_alert_text where id =".$alert_textID);
	$description= $rowtext['description'];
	$alert_text= $rowtext['message'];
	
//echo "<pre>Alerts Params: ";print_r($params);echo "</pre>";

	if ($params['deviceID'] != Null) {
		replaceText(array('deviceID' => $params['deviceID']), $description, $alert_text, $params);
		$deviceID = $params['deviceID'];
	} else {
		$deviceID = 'NULL';
	}
	if ($params['priorityID'] != Null) $params['priorityID']= $rowtext['priorityID'];

	$inserts = PDOInsert("ha_alerts", array('deviceID' => $deviceID, 'description' => $description, 'alert_date' => date("Y-m-d H:i:s"), 'alert_text' => $alert_text, 'priorityID' => $params['priorityID']));
	return $inserts;
}
?>
