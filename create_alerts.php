<?php
require 'connect-db.php';
include_once 'includes/shared_db.php';
include_once 'includes/shared_file.php';
include_once 'includes/shared_ha.php';
include_once 'includes/shared_gen.php';
include 'includes/mysendmail.php';
include 'process.php';

define("MY_DEVICE_ID", 97);

//define('MYDEBUG', 1);

echo Alerts()." Alerts generated <br/>\r\n";
echo AlertsActions()." Alerts sent <br/>\r\n";
echo UpdateMylink(MY_DEVICE_ID)." My Link Updated <br/>\r\n";



function Alerts(){

	$mysql="SELECT * ". 
			" FROM  `ha_alerts_dd` WHERE active='1'" ;
	if (!$resalerts = mysql_query($mysql)) {
		mySqlError($mysql); 
		exit;
	}

	$inserts = 0;
	while ($rowalerts = mysql_fetch_array($resalerts)) {
		// check if we are ready to generate
		$date = getdate();
		if (is_int(strpos($rowalerts['generate_days'],(string)$date["wday"])) === true) {
			if (checktime($rowalerts['generate_start'],$rowalerts['generate_end'])) {	// good 
				$mysql=$rowalerts['sql'];
				$mysql=str_replace("{alertsid}",$rowalerts['id'],$mysql);
				if (MYDEBUG) echo $mysql."</br>";
				if (mysql_query($mysql)) {
					$inserts+=mysql_affected_rows();
				} else {
					if (mysql_errno()<>1062) mySqlError($sql); 
				}
			}
		}
	}
	return $inserts;

}

function AlertsActions(){


	$mysql='SELECT `ha_alerts`.`id` as `ha_alerts_id`, `ha_alerts`.`deviceID` as al_deviceID, `ha_alerts`.`alertid`, `ha_alerts`.`date_key`, `ha_alerts`.`action_date`, `ha_alerts_dd`.* , `ha_alert_text`.`description`, '.
			' `ha_alert_text`.`message`, `ha_mf_commands`.`id`, `ha_mf_commands_detail`.`command` '.
			'FROM `ha_alerts_dd` '.
			'LEFT JOIN `ha_alerts` ON `ha_alerts_dd`.`id`=`ha_alerts`.`alertid` '.
			'LEFT JOIN `ha_alert_text` ON `ha_alerts_dd`.`alert_textID`=`ha_alert_text`.`id` '.
			'LEFT JOIN `ha_mf_commands` ON `ha_alerts_dd`.`commandID`=`ha_mf_commands`.`id` '.
			'LEFT JOIN `ha_mf_commands_detail` ON `ha_mf_commands`.`id`=`ha_mf_commands_detail`.`commandid` '.
	'WHERE `ha_alerts`.`processed` <> "1"';

	if (!$resalerts = mysql_query($mysql)) {
		mySqlError($mysql); 
		exit;
	}

	$send = 0;
	while ($rowalerts = mysql_fetch_assoc($resalerts)) {

		if (checktime($rowalerts['send_start'],$rowalerts['send_end'])) {	  					// good 
			if ($rowalerts['schemeid']>0) { 													// Scheme setup so run scheme
				$result= process(SIGNAL_SOURCE_HA_ALERT,"","",$rowalerts['ha_alerts_id']);
			} else {
				$subject= $rowalerts['description'];
				$message= $rowalerts['message'];
				$myresult = createMail(SIGNAL_SOURCE_HA_ALERT,$rowalerts['ha_alerts_id'],$subject,$message);
				$result=sendmail($rowalerts['command'], $subject, $message, 'VloHome');
			}
			if ($result) { 																// OK Message
				$send++;
				$copyrow = false;
				if (($rowalerts['repeat']==1) OR ($rowalerts['repeat']==2)) {
					$mysql="SELECT `ha_alerts`.`id` as `ha_alerts_id`, MAX( repeat_count ) AS max, action_date FROM  `ha_alerts` ".
						"WHERE (deviceID = ".$rowalerts['al_deviceID'].") AND (alertid =".$rowalerts['alertid'].  ") " .
						"AND (date_key = '".$rowalerts['date_key']."')";
						if (MYDEBUG) echo $mysql."</br>";
					if ($resmax = mysql_query($mysql)) {
						$rowmax = mysql_fetch_assoc($resmax);
						if ($rowalerts['repeat']==1) $copyrow= true;
						if (($rowalerts['repeat']==2) AND ((int)date("i", $rowmax['action_date']) >= 55)) $copyrow= true;
					}
				} 
				if ($copyrow) {  																// Handle every run repeat & Every Hour if time since last alert > 55 min
					$newmax = $rowmax['max'] + 1 ;
					$mysql="UPDATE `ha_alerts` ".
						" SET action_date = NOW(), processed = 1, repeat_count = ".$newmax. 
						" WHERE `ha_alerts`.`id` = ". $rowmax['ha_alerts_id']  ;
					if (MYDEBUG) echo $mysql."</br>";
					(!mysql_query($mysql) ? mySqlError($mysql) : $queries+=1);
				} else {																		// Once / Day
					$mysql="UPDATE `ha_alerts` ".
						" SET action_date = NOW(), processed = 1 ".
						" WHERE `ha_alerts`.`id` = ". $rowalerts['ha_alerts_id'] ;
						if (MYDEBUG) echo $mysql."</br>";
						(!mysql_query($mysql) ? mySqlError($mysql) : $queries+=1);
				}
			} else exit;
		}
	}
	
	return $send;

}


function checktime ($setupstart,$setupend) {
	
	$start= $setupstart;
	$end=$setupend;
	if ($setupstart == '-') $start = "00";
	if ($setupend == '-' or $setupend == '00') $end = "24";
	return  (date("H") >= $start AND date("H") <= $end) ;	// good 
	
}
?>
