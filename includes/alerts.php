<?php

//define('DEBUG_ALERT', TRUE);
if (!defined('DEBUG_ALERT')) define( 'DEBUG_ALERT', FALSE );

function Alerts($alert_textID , $params ) {

	
	$params['priorityID']  = (array_key_exists('priorityID', $params) ? $params['priorityID'] : 'NULL');
	
	$rowtext = FetchRow("SELECT * FROM ha_alert_text where id =".$alert_textID);
	$subject = $rowtext['description'];
	$message  = $rowtext['message'];
	
if (DEBUG_ALERT) {
	echo "<pre>Alerts Params: "; print_r($params); echo "</pre>";
}
	
	replaceText($subject, $message, $params);
		
	if ($params['priorityID'] != Null) $params['priorityID']= $rowtext['priorityID'];

	$inserts = PDOInsert("ha_alerts", array('deviceID' => $params['deviceID'], 'description' => $subject, 'alert_date' => date("Y-m-d H:i:s"), 'alert_text' => $message, 'priorityID' => $params['priorityID']));
	
	return $inserts;
}


function replacePlaceholder(&$subject, $params){

	$message = Null;
	if (preg_match("/\{.*\}/", $subject, $matches)) {
		replaceFields($subject, $message, $params);
	}
}

function replaceText(&$subject, &$message, $params){

	$params['deviceID'] = (array_key_exists('deviceID', $params) ? $params['deviceID'] : 'NULL');
	
	if (array_key_exists('caller', $params)) {
		$callerparams = $params['caller'];
		unset ($params['caller']);
	}

	replaceFields($subject, $message, $params);

	if (isset($callerparams)) {
		$callerparams['deviceID'] = (array_key_exists('deviceID', $callerparams) ? $callerparams['deviceID'] : 'NULL');
		$subject = str_replace("{caller___", "{", $subject); 			// Now replace all {caller___ha_table___field} to (ha_table___field}
		$message = str_replace("{caller___", "{", $message); 			// Now replace all {caller___ha_table___field} to (ha_table___field}
		replaceFields($subject, $message, $callerparams);
	}
}

function replaceFields(&$subject, &$message, $params){
// $type == TRADE_ALERT, HA_ALERT, SCHEME_STEPS

		/*  ha_mf_devices:
				 `ha_mf_devices___code`
				 `ha_mf_devices___description`
				 `ha_mf_devices___unit`
				 
				 ha_mf_locations:
				 `ha_mf_locations___description`
				 `ha_mf_locations___id`
				 
			ha_remote_scheme_steps:
				 `ha_remote_scheme_steps___alert_textID_raw` 
				 `ha_remote_scheme_steps___alert_textID`
				 `ha_remote_scheme_steps___commandID_raw`
				 `ha_remote_scheme_steps___commandID`
				 `ha_remote_scheme_steps___updatedate_raw` 
				 `ha_remote_scheme_steps___updatedate`
				 `ha_remote_scheme_steps___deviceID_raw`
				 `ha_remote_scheme_steps___id_raw` 
				 `ha_remote_scheme_steps___id`
				 `ha_remote_scheme_steps___schemesID_raw`
				 `ha_remote_scheme_steps___schemesID` 
				 `ha_remote_scheme_steps___sort_raw` 
				 `ha_remote_scheme_steps___sort`
				 `ha_remote_scheme_steps___value_raw`
				 `ha_remote_scheme_steps___value` 
				 
			ha_remote_schemes:
				 `ha_remote_schemes___updatedate_raw` 
				 `ha_remote_schemes___updatedate`
				 `ha_remote_schemes___group_raw` 
				 `ha_remote_schemes___group`
				 `ha_remote_schemes___id_raw`
				 `ha_remote_schemes___id`
				 `ha_remote_schemes___id`
				 `ha_remote_schemes___name_raw` 
				 `ha_remote_schemes___name`
				 `ha_remote_schemes___rkey_raw` 
				 `ha_remote_schemes___rkey`
				 `ha_remote_schemes___sort_raw` 
				 `ha_remote_schemes___sort`
			*/
/*			$mysql='SELECT SQL_CALC_FOUND_ROWS DISTINCT `ha_remote_schemes`.`id` AS `ha_remote_schemes___id`, '.
		'`ha_remote_schemes`.`id` AS `ha_remote_schemes___id_raw`, '.
		'`ha_remote_schemes`.`updatedate` AS `ha_remote_schemes___updatedate`, '.
		'`ha_remote_schemes`.`updatedate` AS `ha_remote_schemes___updatedate_raw`, '.
		'`ha_remote_schemes`.`name` AS `ha_remote_schemes___name`, '.
		'`ha_remote_schemes`.`name` AS `ha_remote_schemes___name_raw`, '.
		'`ha_remote_schemes`.`group` AS `ha_remote_schemes___group`, '.
		'`ha_remote_schemes`.`group` AS `ha_remote_schemes___group_raw`, '.
		'`ha_remote_schemes`.`rkey` AS `ha_remote_schemes___rkey`, `ha_remote_schemes`.`rkey` AS `ha_remote_schemes___rkey_raw`, '.
		'`ha_remote_schemes`.`sort` AS `ha_remote_schemes___sort`, `ha_remote_schemes`.`sort` AS `ha_remote_schemes___sort_raw`, '.
		'`ha_remote_scheme_steps`.`id` AS `ha_remote_scheme_steps___id`, '.
		'`ha_remote_scheme_steps`.`id` AS `ha_remote_scheme_steps___id_raw`, '.
		'`ha_remote_scheme_steps`.`updatedate` AS `ha_remote_scheme_steps___updatedate`, '.
		'`ha_remote_scheme_steps`.`updatedate` AS `ha_remote_scheme_steps___updatedate_raw`,'.
		'`ha_remote_scheme_steps`.`schemesID` AS `ha_remote_scheme_steps___schemesID_raw`, '.
		'`ha_remote_schemes_0`.`name` AS `ha_remote_scheme_steps___schemesID`, '.
		'`ha_remote_scheme_steps`.`sort` AS `ha_remote_scheme_steps___sort`, '.
		'`ha_remote_scheme_steps`.`sort` AS `ha_remote_scheme_steps___sort_raw`, '.
		'`ha_remote_scheme_steps`.`deviceID` AS `ha_remote_scheme_steps___deviceID_raw`, `ha_mf_devices`.`code` AS `ha_mf_devices___code`, '.
		'`ha_mf_devices`.`unit` AS `ha_mf_devices___unit`, `ha_mf_devices`.`description` AS `ha_mf_devices___description`, '.
		'`ha_remote_scheme_steps`.`commandID` AS `ha_remote_scheme_steps___commandID_raw`, '.
		'`ha_mf_commands`.`description` AS `ha_remote_scheme_steps___commandID`, '.
		'`ha_remote_scheme_steps`.`value` AS `ha_remote_scheme_steps___value`,  '.
		'`ha_remote_scheme_steps`.`value` AS `ha_remote_scheme_steps___value_raw`, '.
		'`ha_remote_scheme_steps`.`alert_textID` AS `ha_remote_scheme_steps___alert_textID_raw`, '.
		'`ha_alert_text`.`description` AS `ha_remote_scheme_steps___alert_textID`, '.
		'`ha_remote_schemes`.`id` AS `ha_remote_schemes___id`, `ha_mf_locations`.`id` AS `ha_mf_locations___id`, '.
		'`ha_mf_locations`.`description` AS `ha_mf_locations___description`'.
		' FROM `ha_remote_schemes` '.
		' LEFT JOIN `ha_remote_scheme_steps` AS `ha_remote_scheme_steps` ON `ha_remote_scheme_steps`.`schemesID` = `ha_remote_schemes`.`id` '.
		' LEFT JOIN `ha_mf_commands` AS `ha_mf_commands` ON `ha_mf_commands`.`id` = `ha_remote_scheme_steps`.`commandID` '.
		' LEFT JOIN `ha_mf_devices` AS `ha_mf_devices` ON `ha_mf_devices`.`id` = `ha_remote_scheme_steps`.`deviceID` '.
		' LEFT JOIN `ha_alert_text` AS `ha_alert_text` ON `ha_alert_text`.`id` = `ha_remote_scheme_steps`.`alert_textID` '.
		' LEFT JOIN `ha_remote_schemes` AS `ha_remote_schemes_0` ON `ha_remote_schemes_0`.`id` = `ha_remote_scheme_steps`.`schemesID` '.
		' LEFT JOIN `ha_mf_locations` AS `ha_mf_locations` ON `ha_mf_locations`.`id` = `ha_mf_devices`.`locationID` '.
		' WHERE `ha_remote_scheme_steps`.`id` = "'.$id.'"';
*/			
		if ($params['deviceID'] != null) {

		$mysql = ' SELECT DISTINCT `ha_mf_devices`.`updatedate` AS `ha_mf_devices___updatedate`, `ha_mf_devices`.`updatedate` AS `ha_mf_devices___updatedate_raw`, 
	`ha_mf_devices`.`id` AS `ha_mf_devices___id`, `ha_mf_devices`.`id` AS `ha_mf_devices___id_raw`, `ha_mf_devices`.`description` AS `ha_mf_devices___description`, 
	`ha_mf_devices`.`description` AS `ha_mf_devices___description_raw`, `ha_mf_devices`.`shortdesc` AS `ha_mf_devices___shortdesc`, `ha_mf_devices`.`shortdesc` AS shortdesc, 
	`ha_mf_devices`.`code` AS `ha_mf_devices___code`, `ha_mf_devices`.`code` AS `ha_mf_devices___code_raw`, `ha_mf_devices`.`unit` AS `ha_mf_devices___unit`, 
	`ha_mf_devices`.`unit` AS `ha_mf_devices___unit_raw`, 
	`ha_mf_devices`.`typeID` AS `ha_mf_devices___typeID_raw`, 
	`ha_mf_devices`.`locationID` AS `ha_mf_devices___locationID_raw`, 
	`ha_mf_devices`.`inuse` AS `ha_mf_devices___inuse`, 
	`ha_mf_devices`.`inuse` AS `ha_mf_devices___inuse_raw`, `ha_mf_devices`.`ipaddressID` AS `ha_mf_devices___ipaddressID_raw` from ha_mf_devices WHERE id=
	'.$params['deviceID'];
	
	$mysqlp = 'SELECT ha_mi_properties.description, ha_mf_device_properties.value FROM ha_mf_device_properties 
				JOIN ha_mi_properties ON ha_mf_device_properties.propertyID = ha_mi_properties.id 
				WHERE ha_mf_device_properties.deviceID ='.$params['deviceID'];
	}
 
if (DEBUG_ALERT) {
	echo "<pre> params"; print_r ($params); echo "</pre>";
	echo "<pre>"; echo $subject.CRLF; echo "</pre>";
	echo "<pre>"; echo $message.CRLF; echo "</pre>";
}	

	if (isset($mysql)) {

		if ($data = FetchRow($mysql)) {
			foreach ($data as $key => $value) {
				$pattern[$key]="/\{".$key."\}/";
			}
if (DEBUG_ALERT) {
	//echo "<pre>"; echo "DATA:"; print_r ($data); echo "</pre>";
	//echo "<pre>"; echo "PATTERN:"; print_r ($pattern); echo "</pre>";
}
			$subject=preg_replace($pattern, $data, $subject);
			$subject=preg_replace($pattern, $data, $subject); // twice to support tag in tag
			if ($message != Null) $message=preg_replace($pattern, $data, $message); // twice to support tag in tag
			if ($message != Null) $message=preg_replace($pattern, $data, $message);
		}
	}
	
	// This is for all other values in the params i.e. Message
	if ($params != Null) {
	
		if (array_key_exists('callerID', $params)) {
			if ($cd = FetchRow("SELECT description FROM ha_mf_devices WHERE ha_mf_devices.id =".$params['callerID']))  {
				$params['callerID___description']= $cd['description'];
			}
		}	

		unset ($pattern);
		foreach ($params as $key => $value) {
			$pattern[$key]="/\{".$key."\}/";
		}
if (DEBUG_ALERT) {
	echo "<pre>"; echo "DATA2:"; print_r ($params); echo "</pre>";
	echo "<pre>"; echo "PATTERN2:"; print_r ($pattern); echo "</pre>";
}
		$subject = preg_replace($pattern, $params, $subject);
		if ($message != Null) $message=preg_replace($pattern, $params, $message); 
	}
	

	// This is for the device properties
	if (isset($mysql)) {
		if ($props = FetchRows($mysqlp)) {
		
			unset ($pattern);
			foreach ($props as $key => $value) {
				$pattern[$value['description']]="/\{".$value['description']."\}/";
				$newprops[$value['description']]=$value['value'];
			}
	if (DEBUG_ALERT) {
		// echo "<pre>"; echo "DATA3:"; print_r ($newprops); echo "</pre>";
		// echo "<pre>"; echo "PATTERN3:"; print_r ($pattern); echo "</pre>";
	}
			$subject = str_replace("{property___", "{", $subject);
			$subject = preg_replace($pattern, $newprops, $subject);
			if ($message != Null) $message=preg_replace($pattern, $newprops, $message); 
		}
	}
	
if (DEBUG_ALERT) {
	echo "<pre>"; echo $subject.CRLF; echo "</pre>";
	echo "<pre>"; echo $message.CRLF; echo "</pre>";
}	
	return true;
}
?>
