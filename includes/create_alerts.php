<?php
//define("DEBUG_ALERT", TRUE);
define("DEBUG_ALERT", FALSE);

function Alerts($alert_textID , $params = NULL ){

	if (!is_array($params)) $params[] ='';
	
	$labels = array ( 'l1', 'l2', 'l3', 'l4', 'l5' ) ;
	$values = array ( 'v1', 'v2', 'v3', 'v4', 'v5' ) ;
	$params['deviceID'] = (array_key_exists('deviceID', $params) ? $params['deviceID'] : 'NULL');
	$params['priorityID']  = (array_key_exists('priorityID', $params) ? $params['priorityID'] : 'NULL');
/*	foreach ($labels as $i) {
		$params[$i] = (array_key_exists($i, $params) ? $params[$i] : 'NULL');
	}
	foreach ($values as $i) {
		$params[$i] = (array_key_exists($i, $params) ? $params[$i] : 'NULL');
	} */
	
//		" FROM  `ha_alert_text` WHERE active='1' AND id = ".$alert_textID ;
	$rowtext = FetchRow("SELECT * FROM ha_alert_text where id =".$alert_textID);
	$description= $rowtext['description'];
	$alert_text= $rowtext['message'];
	if ($params['deviceID'] != Null) {
		replaceText(Array('deviceID' => $params['deviceID']), $description, $alert_text, $params);
	}
	if ($params['priorityID'] != Null) $params['priorityID']= $rowtext['priorityID'];

	$mysql = 'INSERT INTO `ha_alerts` (`description`, `alert_date`, `alert_text`, `priorityID`) 
				(
				   SELECT  "'. $description.'", NOW(), "'.$alert_text.'","'. $params['priorityID'].'"
				)';

	if (DEBUG_ALERT) echo $mysql."</br>";				

	$inserts = RunQuery($mysql);
	return $inserts;
}
?>
