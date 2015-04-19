<?php
//define("DEBUG_ALERT", TRUE);
define("DEBUG_ALERT", FALSE);

function Alerts($alert_textID , $params = NULL ){

	if (!is_array($params)) $params[] ='';
	
	$labels = array ( 'l1', 'l2', 'l3', 'l4', 'l5' ) ;
	$values = array ( 'v1', 'v2', 'v3', 'v4', 'v5' ) ;
	$params['deviceID'] = (array_key_exists('deviceID', $params) ? $params['deviceID'] : 'NULL');
	$params['priorityID']  = (array_key_exists('priorityID', $params) ? $params['priorityID'] : 'NULL');

	$rowtext = FetchRow("SELECT * FROM ha_alert_text where id =".$alert_textID);
	$description= $rowtext['description'];
	$alert_text= $rowtext['message'];
	
//echo "<pre>";print_r($params);echo "</pre>";
	if ($params['deviceID'] != Null) {
		replaceText(Array('deviceID' => $params['deviceID']), $description, $alert_text, $params);
		$deviceID = $params['deviceID'];
	} else {
		$deviceID = 'NULL';
	}
	if ($params['priorityID'] != Null) $params['priorityID']= $rowtext['priorityID'];

	$mysql = 'INSERT INTO `ha_alerts` (`deviceID`, `description`, `alert_date`, `alert_text`, `priorityID`) 
				(
				   SELECT  '. $deviceID. ', "'. $description.'", NOW(), "'.$alert_text.'","'. $params['priorityID'].'"
				)';

	if (DEBUG_ALERT) echo $mysql."</br>";				

	$inserts = RunQuery($mysql);
	return $inserts;
}
?>
