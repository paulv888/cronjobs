<?php
//define("DEBUG_ALERT", TRUE);
define("DEBUG_ALERT", FALSE);

function Alerts($alertID = NULL, $params  ){

	preg_match ( "/^[1-9][0-9]*/", $alertID, $matches);
	$alertID = $matches[0];

	$labels = array ( 'l1', 'l2', 'l3', 'l4', 'l5' ) ;
	$values = array ( 'v1', 'v2', 'v3', 'v4', 'v5' ) ;
	$params['deviceID'] = (array_key_exists('deviceID', $params) ? $params['deviceID'] : 'NULL');
	foreach ($labels as $i) {
		$params[$i] = (array_key_exists($i, $params) ? $params[$i] : 'NULL');
	}
	foreach ($values as $i) {
		$params[$i] = (array_key_exists($i, $params) ? $params[$i] : 'NULL');
	}
	
	$mysql="SELECT * ". 
		" FROM  `ha_alerts_dd` WHERE active='2' AND id = ".$alertID ;

	if (!$rowalerts = FetchRow($mysql)) {
		exit;
	}

	$inserts = 0;
	$queries = 0;

	$mysql = 'INSERT INTO `ha_alerts` (`deviceID` , `alertid`, `date_key`,  `alert_date`, l1 , v1, l2 , v2,  l3 , v3, l4, v4, l5, v5) 
				(
				   SELECT  {deviceID}, {alertID}, DATE(NOW()), NOW(),
					 "{l1}","{v1}",
					 "{l2}","{v2}",
					 "{l3}","{v3}",
					 "{l4}","{v4}",
					 "{l5}","{v5}"
				)';
	$mysql = str_replace("{alertID}", $rowalerts['id'], $mysql);
	$mysql = str_replace("{deviceID}",$rowalerts['deviceID'],$mysql);
	$mysql = str_replace("{DEVICE_SOMEONE_HOME}",DEVICE_SOMEONE_HOME,$mysql);
	$mysql = str_replace("{DEVICE_ALARM_ZONE1}",DEVICE_ALARM_ZONE1,$mysql);
	$mysql = str_replace("{DEVICE_ALARM_ZONE2}",DEVICE_ALARM_ZONE2,$mysql);
	$mysql = str_replace("{DEVICE_DARK_OUTSIDE}",DEVICE_DARK_OUTSIDE,$mysql);
	$mysql = str_replace("{DEVICE_PAUL_HOME}",DEVICE_PAUL_HOME,$mysql);
	foreach ($labels as $i) {
		$mysql=str_replace("{".$i."}", $params[$i] , $mysql);
	}
	foreach ($values as $i) {
		$mysql=str_replace("{".$i."}", $params[$i] , $mysql);
	}
	if (DEBUG_ALERT) echo $mysql."</br>";				

	if (mysql_query($mysql)) {
		$inserts+=mysql_affected_rows();
	} else {
		if (mysql_errno()<>1062) mySqlError($mysql); 
	}
	return $inserts;
}
?>
