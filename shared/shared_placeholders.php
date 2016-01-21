<?php

// define('DEBUG_PHOLDERS', TRUE);
if (!defined('DEBUG_PHOLDERS')) define( 'DEBUG_PHOLDERS', FALSE );

function replaceCommandPlaceholders($params) {

	if (DEBUG_PHOLDERS) {echo "<pre> replaceCommandPlaceholders "; print_r ($params); echo "</pre>";}

	$result = str_replace("{mycommandID}",trim($params['commandID']),$params['command']);
	$result = str_replace("{deviceID}",trim($params['deviceID']),$result);
	$result = str_replace("{unit}",trim($params['device']['unit']),$result);
	// Not tested
	if (strpos($params['commandvalue'],'|') !== false) {
		$cvs = explode('|', $params['commandvalue']);
		foreach ($cvs as $key => $value) {
			 $result = str_replace('{commandvalue'.$key.'}', $value, $result);
		}
	}
	$result = str_replace("{commandvalue}",trim($params['commandvalue']),$result);
	$result = str_replace("{value}",trim($params['value']),$result);
	$result = str_replace("{timervalue}",trim($params['timervalue']),$result);
	if (array_key_exists('mess_subject',$params)) $result = str_replace("{mess_subject}",trim($params['mess_subject']),$result);
	if (array_key_exists('mess_text',$params)) $result = str_replace("{mess_text}",trim($params['mess_text']),$result);
	

	if (DEBUG_PHOLDERS) {echo "<pre> replaceCommandPlaceholders result"; echo($result); echo "</pre>";}
	return $result;
}

function replaceResultPlaceholders($mess_subject, &$params, $resultin){

	if (DEBUG_PHOLDERS) {
		echo "<pre> replaceResultPlaceholders "; print_r ($resultin); echo "</pre>";
		echo "<pre>Subject: "; echo $mess_subject.CRLF; echo "</pre>";
	}	

	// Do an array search for the value to replace {postion}
	// execute before clobbering input
	preg_match('/\{result___(.*?)\}/', $mess_subject, $output);
	if (!empty($output)) {	// Found me some
		$resultin['deviceID'] = $params['deviceID'];
		if (DEBUG_PHOLDERS) {
			echo "<pre>Search Array for Key "; echo "DATA0:"; print_r ($params); echo "</pre>";
			echo "<pre>Search Array for Key "; echo "PATTERN0:"; print_r ($output); echo "</pre>";
		}

		$filterkeep = array( $output[1] => 1);
		doFilter($resultin, array(), $filterkeep, $result);
		// echo "Filtered: >";
		// print_r($result);

		if (is_array($result)) {
			//echo $result[0][$output[1]].CRLF;	
			$mess_subject = str_replace($output[0], trim($result[0][$output[1]]), $mess_subject);
			$propname = str_replace('result___', '', $output[1]);
			$params['device']['properties'][$propname]['value']= $result[0][$output[1]];
			//if ($mess_text != Null) $mess_text=preg_replace($pattern, $params, $mess_text); // twice to support tag in tag
		}
		
		if (DEBUG_PHOLDERS) {
			echo "<pre>"; echo $mess_subject.CRLF; echo "</pre>";
		}	
	}
	
	// echo "return replacePlaceholder in: ".$mess_subject.CRLF;
	return $mess_subject;
}


function replacePlaceholder($mess_subject, $params){

	// echo "replacePlaceholder in: ".$mess_subject.CRLF;
	$mess_text = Null;
	if (preg_match("/\{.*\}/", $mess_subject, $matches)) {
		replaceFields($mess_subject, $mess_text, $params, false);
	}
	// echo "return replacePlaceholder in: ".$mess_subject.CRLF;
	return $mess_subject;
}

function replaceText(&$params, $skip_fields = false){

	$params['deviceID'] = (array_key_exists('deviceID', $params) ? $params['deviceID'] : 'NULL');
	
	$mess_subject = (array_key_exists('mess_subject',$params) ? $params['mess_subject'] : "");
	$mess_text = (array_key_exists('mess_text',$params) ? $params['mess_text'] : "");
	
	if (array_key_exists('caller', $params)) {
		$callerparams = $params['caller'];
		unset ($params['caller']);
	}
	
	replaceFields($mess_subject, $mess_text, $params, $skip_fields);

	if (isset($callerparams)) {
		$callerparams['deviceID'] = (array_key_exists('deviceID', $callerparams) ? $callerparams['deviceID'] : 'NULL');
		$mess_subject = str_replace("{caller___", "{", $mess_subject); // Now replace all {caller___ha_table___field} to (ha_table___field}
		$mess_text = str_replace("{caller___", "{", $mess_text); 	// Now replace all {caller___ha_table___field} to (ha_table___field}
		replaceFields($mess_subject, $mess_text, $callerparams, $skip_fields);
	}

	$params['mess_subject'] = $mess_subject;
	$params['mess_text'] = $mess_text;
	$params['caller'] = $callerparams;
}

function replaceFields(&$mess_subject, &$mess_text, $params, $skip_fields){

		if (DEBUG_PHOLDERS) {
			echo "<pre> Replace Fields Params "; print_r ($params); echo "</pre>";
			echo "<pre>Subject: "; echo $mess_subject.CRLF; echo "</pre>";
			echo "<pre>Message: "; echo $mess_text.CRLF; echo "</pre>";
		}	

		if ($params['deviceID'] != null && $params['deviceID'] == DEVICE_CURRENT_SESSION) {
			$params['deviceID'] = $params['SESSION']['properties']['SelectedPlayer']['value'];
		}

		if (!$skip_fields && $params['deviceID'] != null) {
			$mysql = ' SELECT DISTINCT `ha_mf_devices`.`updatedate` AS `ha_mf_devices___updatedate`, `ha_mf_devices`.`updatedate` AS `ha_mf_devices___updatedate_raw`, `ha_mf_devices`.`id` AS `ha_mf_devices___id`, `ha_mf_devices`.`id` AS `ha_mf_devices___id_raw`, `ha_mf_devices`.`description` AS `ha_mf_devices___description`, `ha_mf_devices`.`description` AS `ha_mf_devices___description_raw`, `ha_mf_devices`.`shortdesc` AS `ha_mf_devices___shortdesc`, `ha_mf_devices`.`shortdesc` AS `ha_mf_devices___shortdesc_raw`, `ha_mf_devices`.`code` AS `ha_mf_devices___code`, `ha_mf_devices`.`code` AS `ha_mf_devices___code_raw`, `ha_mf_devices`.`unit` AS `ha_mf_devices___unit`, `ha_mf_devices`.`unit` AS `ha_mf_devices___unit_raw`, `ha_mf_devices`.`typeID` AS `ha_mf_devices___typeID_raw`, ha_mf_device_types.description AS `ha_mf_devices___typeID`, `ha_mf_devices`.`locationID` AS `ha_mf_devices___locationID_raw`,    ha_mf_locations.description AS `ha_mf_devices___locationID`, `ha_mf_devices`.`inuse` AS `ha_mf_devices___inuse`, `ha_mf_devices`.`inuse` AS `ha_mf_devices___inuse_raw`, `ha_mf_devices`.`ipaddressID` AS `ha_mf_devices___ipaddressID_raw`, `ha_mf_device_ipaddress`.`ip` AS `ha_mf_devices___ipaddressID`, `ha_mf_devices`.`connectionID` AS `ha_mf_devices___connectionID_raw`, `ha_mi_connection`.`name` AS `ha_mf_devices___connectionID`, `ha_mf_devices`.`commandclassID` AS `ha_mf_devices___commandclassID_raw`, `ha_mf_commands_class_dd`.`description` AS `ha_mf_devices___commandclassID`, `ha_mf_devices`.`sort` AS `ha_mf_devices___sort`, `ha_mf_devices`.`sort` AS `ha_mf_devices___sort_raw`, `ha_mf_device_group`.`id` AS `ha_mf_device_group___id`, `ha_mf_device_group`.`id` AS `ha_mf_device_group___id_raw`, `ha_mf_device_group`.`groupID` AS `ha_mf_device_group___groupID_raw`, `ha_mf_groups`.`description` AS `ha_mf_device_group___groupID`, `ha_mf_device_group`.`deviceID` AS `ha_mf_device_group___deviceID`, `ha_mf_device_group`.`deviceID` AS `ha_mf_device_group___deviceID_raw`, `ha_mf_device_group`.`updatedate` AS `ha_mf_device_group___updatedate`, `ha_mf_device_group`.`updatedate` AS `ha_mf_device_group___updatedate_raw`, `ha_mf_device_properties`.`id` AS `ha_mf_device_properties___id`, `ha_mf_device_properties`.`id` AS `ha_mf_device_properties___id_raw`, `ha_mf_device_properties`.`deviceID` AS `ha_mf_device_properties___deviceID`, `ha_mf_device_properties`.`deviceID` AS `ha_mf_device_properties___deviceID_raw`, `ha_mf_device_properties`.`propertyID` AS `ha_mf_device_properties___propertyID_raw`, ha_mi_properties.description AS `ha_mf_device_properties___propertyID`, `ha_mf_device_properties`.`value` AS `ha_mf_device_properties___value`, `ha_mf_device_properties`.`value` AS `ha_mf_device_properties___value_raw`, `ha_mf_device_properties`.`updatedate` AS `ha_mf_device_properties___updatedate`, `ha_mf_device_properties`.`updatedate` AS `ha_mf_device_properties___updatedate_raw`, `ha_mf_device_properties`.`sort` AS `ha_mf_device_properties___sort`, `ha_mf_device_properties`.`sort` AS `ha_mf_device_properties___sort_raw`, `ha_mf_monitor_link`.`linkmonitor` AS `ha_mf_monitor_link___linkmonitor`, `ha_mf_monitor_link`.`linkmonitor` AS `ha_mf_monitor_link___linkmonitor_raw`, `ha_mf_monitor_link`.`id` AS `ha_mf_monitor_link___id`, `ha_mf_monitor_link`.`id` AS `ha_mf_monitor_link___id_raw`, `ha_mf_monitor_link`.`active` AS `ha_mf_monitor_link___active`, `ha_mf_monitor_link`.`active` AS `ha_mf_monitor_link___active_raw`, `ha_mf_monitor_link`.`listenfor1` AS `ha_mf_monitor_link___listenfor1_raw`, `ha_mf_commands_0`.`Description` AS `ha_mf_monitor_link___listenfor1`, `ha_mf_monitor_link`.`updatedate` AS `ha_mf_monitor_link___updatedate`, `ha_mf_monitor_link`.`updatedate` AS `ha_mf_monitor_link___updatedate_raw`, `ha_mf_monitor_link`.`listenfor2` AS `ha_mf_monitor_link___listenfor2_raw`, `ha_mf_commands`.`Description` AS `ha_mf_monitor_link___listenfor2`, `ha_mf_monitor_link`.`link_timeout` AS `ha_mf_monitor_link___link_timeout`, `ha_mf_monitor_link`.`link_timeout` AS `ha_mf_monitor_link___link_timeout_raw`, `ha_mf_monitor_link`.`link_warning` AS `ha_mf_monitor_link___link_warning`, `ha_mf_monitor_link`.`link_warning` AS `ha_mf_monitor_link___link_warning_raw`, `ha_mf_monitor_link`.`pingport` AS `ha_mf_monitor_link___pingport`, `ha_mf_monitor_link`.`pingport` AS `ha_mf_monitor_link___pingport_raw`, `ha_mf_monitor_link`.`deviceID` AS `ha_mf_monitor_link___deviceID`, `ha_mf_monitor_link`.`deviceID` AS `ha_mf_monitor_link___deviceID_raw`, `ha_mf_monitor_property`.`id` AS `ha_mf_monitor_property___id`, `ha_mf_monitor_property`.`id` AS `ha_mf_monitor_property___id_raw`, `ha_mf_monitor_property`.`deviceID` AS `ha_mf_monitor_property___deviceID`, `ha_mf_monitor_property`.`deviceID` AS `ha_mf_monitor_property___deviceID_raw`, `ha_mf_monitor_property`.`propertyID` AS `ha_mf_monitor_property___propertyID_raw`, `ha_mi_properties_1`.`description` AS `ha_mf_monitor_property___propertyID`, `ha_mf_monitor_property`.`active` AS `ha_mf_monitor_property___active`, `ha_mf_monitor_property`.`active` AS `ha_mf_monitor_property___active_raw`, `ha_mf_monitor_property`.`invertstatus` AS `ha_mf_monitor_property___invertstatus`, `ha_mf_monitor_property`.`invertstatus` AS `ha_mf_monitor_property___invertstatus_raw`, `ha_mf_monitor_property`.`toggleignore` AS `ha_mf_monitor_property___toggleignore`, `ha_mf_monitor_property`.`toggleignore` AS `ha_mf_monitor_property___toggleignore_raw`, `ha_mf_monitor_property`.`updatedate` AS `ha_mf_monitor_property___updatedate`, `ha_mf_monitor_property`.`updatedate` AS `ha_mf_monitor_property___updatedate_raw`, `ha_mf_monitor_property`.`sort` AS `ha_mf_monitor_property___sort`, `ha_mf_monitor_property`.`sort` AS `ha_mf_monitor_property___sort_raw`, `ha_mf_monitor_triggers`.`id` AS `ha_mf_monitor_triggers___id`, `ha_mf_monitor_triggers`.`id` AS `ha_mf_monitor_triggers___id_raw`, `ha_mf_monitor_triggers`.`deviceID` AS `ha_mf_monitor_triggers___deviceID`, `ha_mf_monitor_triggers`.`deviceID` AS `ha_mf_monitor_triggers___deviceID_raw`, `ha_mf_monitor_triggers`.`propertyID` AS `ha_mf_monitor_triggers___propertyID_raw`, `ha_mi_properties_0`.`description` AS `ha_mf_monitor_triggers___propertyID`, `ha_mf_monitor_triggers`.`triggertype` AS `ha_mf_monitor_triggers___triggertype`, `ha_mf_monitor_triggers`.`triggertype` AS `ha_mf_monitor_triggers___triggertype_raw`, `ha_mf_monitor_triggers`.`schemeID` AS `ha_mf_monitor_triggers___schemeID_raw`, `ha_remote_schemes`.`name` AS `ha_mf_monitor_triggers___schemeID`, `ha_mf_monitor_triggers`.`updatedate` AS `ha_mf_monitor_triggers___updatedate`, `ha_mf_monitor_triggers`.`updatedate` AS `ha_mf_monitor_triggers___updatedate_raw`, `ha_mf_monitor_triggers`.`sort` AS `ha_mf_monitor_triggers___sort`, `ha_mf_monitor_triggers`.`sort` AS `ha_mf_monitor_triggers___sort_raw`, `ha_mf_devices_thermostat`.`id` AS `ha_mf_devices_thermostat___id`, `ha_mf_devices_thermostat`.`id` AS `ha_mf_devices_thermostat___id_raw`, `ha_mf_devices_thermostat`.`site1` AS `ha_mf_devices_thermostat___site1`, `ha_mf_devices_thermostat`.`site1` AS `ha_mf_devices_thermostat___site1_raw`, `ha_mf_devices_thermostat`.`empty1` AS `ha_mf_devices_thermostat___empty1`, `ha_mf_devices_thermostat`.`empty1` AS `ha_mf_devices_thermostat___empty1_raw`, `ha_mf_devices_thermostat`.`deviceID` AS `ha_mf_devices_thermostat___deviceID`, `ha_mf_devices_thermostat`.`deviceID` AS `ha_mf_devices_thermostat___deviceID_raw`, `ha_mf_devices_thermostat`.`tstat_uuid` AS `ha_mf_devices_thermostat___tstat_uuid`, `ha_mf_devices_thermostat`.`tstat_uuid` AS `ha_mf_devices_thermostat___tstat_uuid_raw`, `ha_mf_devices_thermostat`.`model` AS `ha_mf_devices_thermostat___model`, `ha_mf_devices_thermostat`.`model` AS `ha_mf_devices_thermostat___model_raw`, `ha_mf_devices_thermostat`.`fw_version` AS `ha_mf_devices_thermostat___fw_version`, `ha_mf_devices_thermostat`.`fw_version` AS `ha_mf_devices_thermostat___fw_version_raw`, `ha_mf_devices_thermostat`.`wlan_fw_version` AS `ha_mf_devices_thermostat___wlan_fw_version`, `ha_mf_devices_thermostat`.`wlan_fw_version` AS `ha_mf_devices_thermostat___wlan_fw_version_raw`, `ha_mf_devices_thermostat`.`name` AS `ha_mf_devices_thermostat___name`, `ha_mf_devices_thermostat`.`name` AS `ha_mf_devices_thermostat___name_raw`, `ha_mf_devices_thermostat`.`description` AS `ha_mf_devices_thermostat___description`, `ha_mf_devices_thermostat`.`description` AS `ha_mf_devices_thermostat___description_raw`, `ha_mf_devices_thermostat`.`away_heat_temp_c` AS `ha_mf_devices_thermostat___away_heat_temp_c`, `ha_mf_devices_thermostat`.`away_heat_temp_c` AS `ha_mf_devices_thermostat___away_heat_temp_c_raw`, `ha_mf_devices_thermostat`.`here_temp_heat_c` AS `ha_mf_devices_thermostat___here_temp_heat_c`, `ha_mf_devices_thermostat`.`here_temp_heat_c` AS `ha_mf_devices_thermostat___here_temp_heat_c_raw`, `ha_mf_devices_thermostat`.`away_cool_temp_c` AS `ha_mf_devices_thermostat___away_cool_temp_c`, `ha_mf_devices_thermostat`.`away_cool_temp_c` AS `ha_mf_devices_thermostat___away_cool_temp_c_raw`, `ha_mf_devices_thermostat`.`here_temp_cool_c` AS `ha_mf_devices_thermostat___here_temp_cool_c`, `ha_mf_devices_thermostat`.`here_temp_cool_c` AS `ha_mf_devices_thermostat___here_temp_cool_c_raw`, `ha_mf_device_arduino`.`id` AS `ha_mf_device_arduino___id`, `ha_mf_device_arduino`.`id` AS `ha_mf_device_arduino___id_raw`, `ha_mf_device_arduino`.`deviceID` AS `ha_mf_device_arduino___deviceID`, `ha_mf_device_arduino`.`deviceID` AS `ha_mf_device_arduino___deviceID_raw`, `ha_mf_device_arduino`.`name1` AS `ha_mf_device_arduino___name1`, `ha_mf_device_arduino`.`name1` AS `ha_mf_device_arduino___name1_raw`, `ha_mf_device_arduino`.`value1` AS `ha_mf_device_arduino___value1`, `ha_mf_device_arduino`.`value1` AS `ha_mf_device_arduino___value1_raw`, `ha_mf_device_arduino`.`dummy1` AS `ha_mf_device_arduino___dummy1`, `ha_mf_device_arduino`.`dummy1` AS `ha_mf_device_arduino___dummy1_raw`, `ha_mf_device_arduino`.`name2` AS `ha_mf_device_arduino___name2`, `ha_mf_device_arduino`.`name2` AS `ha_mf_device_arduino___name2_raw`, `ha_mf_device_arduino`.`value2` AS `ha_mf_device_arduino___value2`, `ha_mf_device_arduino`.`value2` AS `ha_mf_device_arduino___value2_raw`, `ha_mf_device_arduino`.`dummy2` AS `ha_mf_device_arduino___dummy2`, `ha_mf_device_arduino`.`dummy2` AS `ha_mf_device_arduino___dummy2_raw`, `ha_mf_device_arduino`.`name3` AS `ha_mf_device_arduino___name3`, `ha_mf_device_arduino`.`name3` AS `ha_mf_device_arduino___name3_raw`, `ha_mf_device_arduino`.`value3` AS `ha_mf_device_arduino___value3`, `ha_mf_device_arduino`.`value3` AS `ha_mf_device_arduino___value3_raw`, `ha_mf_device_arduino`.`dummy3` AS `ha_mf_device_arduino___dummy3`, `ha_mf_device_arduino`.`dummy3` AS `ha_mf_device_arduino___dummy3_raw`, `ha_mf_device_arduino`.`name4` AS `ha_mf_device_arduino___name4`, `ha_mf_device_arduino`.`name4` AS `ha_mf_device_arduino___name4_raw`, `ha_mf_device_arduino`.`value4` AS `ha_mf_device_arduino___value4`, `ha_mf_device_arduino`.`value4` AS `ha_mf_device_arduino___value4_raw`, `ha_mf_device_arduino`.`dummy4` AS `ha_mf_device_arduino___dummy4`, `ha_mf_device_arduino`.`dummy4` AS `ha_mf_device_arduino___dummy4_raw`, `ha_mf_device_arduino`.`name5` AS `ha_mf_device_arduino___name5`, `ha_mf_device_arduino`.`name5` AS `ha_mf_device_arduino___name5_raw`, `ha_mf_device_arduino`.`value5` AS `ha_mf_device_arduino___value5`, `ha_mf_device_arduino`.`value5` AS `ha_mf_device_arduino___value5_raw`, `ha_mf_device_arduino`.`dummy5` AS `ha_mf_device_arduino___dummy5`, `ha_mf_device_arduino`.`dummy5` AS `ha_mf_device_arduino___dummy5_raw`, `ha_mf_devices`.`id` AS slug , `ha_mf_devices`.`id` AS `__pk_val` FROM `ha_mf_devices` LEFT JOIN `ha_mf_locations` AS `ha_mf_locations` ON `ha_mf_locations`.`id` = `ha_mf_devices`.`locationID` LEFT JOIN `ha_mf_monitor_link` AS `ha_mf_monitor_link` ON `ha_mf_monitor_link`.`deviceID` = `ha_mf_devices`.`id` LEFT JOIN `ha_mf_commands` AS `ha_mf_commands` ON `ha_mf_commands`.`id` = `ha_mf_monitor_link`.`listenfor2` LEFT JOIN `ha_mf_devices_thermostat` AS `ha_mf_devices_thermostat` ON `ha_mf_devices_thermostat`.`deviceID` = `ha_mf_devices`.`id` LEFT JOIN `ha_mf_commands` AS `ha_mf_commands_0` ON `ha_mf_commands_0`.`id` = `ha_mf_monitor_link`.`listenfor1` LEFT JOIN `ha_mf_device_types` AS `ha_mf_device_types` ON `ha_mf_device_types`.`id` = `ha_mf_devices`.`typeID` LEFT JOIN `ha_mf_commands_class_dd` AS `ha_mf_commands_class_dd` ON `ha_mf_commands_class_dd`.`id` = `ha_mf_devices`.`commandclassID` LEFT JOIN `ha_mf_device_ipaddress` AS `ha_mf_device_ipaddress` ON `ha_mf_device_ipaddress`.`id` = `ha_mf_devices`.`ipaddressID` LEFT JOIN `ha_mi_connection` AS `ha_mi_connection` ON `ha_mi_connection`.`id` = `ha_mf_devices`.`connectionID` LEFT JOIN `ha_mf_monitor_triggers` AS `ha_mf_monitor_triggers` ON `ha_mf_monitor_triggers`.`deviceID` = `ha_mf_devices`.`id` LEFT JOIN `ha_remote_schemes` AS `ha_remote_schemes` ON `ha_remote_schemes`.`id` = `ha_mf_monitor_triggers`.`schemeID` LEFT JOIN `ha_mf_device_group` AS `ha_mf_device_group` ON `ha_mf_device_group`.`deviceID` = `ha_mf_devices`.`id` LEFT JOIN `ha_mf_groups` AS `ha_mf_groups` ON `ha_mf_groups`.`id` = `ha_mf_device_group`.`groupID` LEFT JOIN `ha_mf_device_arduino` AS `ha_mf_device_arduino` ON `ha_mf_device_arduino`.`deviceID` = `ha_mf_devices`.`id` LEFT JOIN `ha_mf_device_properties` AS `ha_mf_device_properties` ON `ha_mf_device_properties`.`deviceID` = `ha_mf_devices`.`id` LEFT JOIN `ha_mi_properties` AS `ha_mi_properties` ON `ha_mi_properties`.`id` = `ha_mf_device_properties`.`propertyID` LEFT JOIN `ha_mi_properties` AS `ha_mi_properties_0` ON `ha_mi_properties_0`.`id` = `ha_mf_monitor_triggers`.`propertyID` LEFT JOIN `ha_mf_monitor_property` AS `ha_mf_monitor_property` ON `ha_mf_monitor_property`.`deviceID` = `ha_mf_devices`.`id` LEFT JOIN `ha_mi_properties` AS `ha_mi_properties_1` ON `ha_mi_properties_1`.`id` = `ha_mf_monitor_property`.`propertyID` WHERE ha_mf_devices.id = '.$params['deviceID'];
		
	 
		foreach ($params as $key => $value) {
			if (is_array($value)) unset($params[$key]);
		}

		if ($params['deviceID'] != null) {

			if ($data = FetchRow($mysql)) {
				foreach ($data as $key => $value) {
					$pattern[$key]="/\{".$key."\}/";
				}
				if (DEBUG_PHOLDERS) {
					echo "<pre>"; echo "BIG HEAVY SQL FOR FIELDS VALUES";echo "</pre>";
					//echo "<pre>"; echo "DATA:"; print_r ($data); echo "</pre>";
					//echo "<pre>"; echo "PATTERN:"; print_r ($pattern); echo "</pre>";
				}
				$mess_subject=preg_replace($pattern, $data, $mess_subject);
				$mess_subject=preg_replace($pattern, $data, $mess_subject); // twice to support tag in tag
				if ($mess_text != Null) $mess_text=preg_replace($pattern, $data, $mess_text); // twice to support tag in tag
				if ($mess_text != Null) $mess_text=preg_replace($pattern, $data, $mess_text);
			}
		}
	}
	
	// echo "****".$params['deviceID'].CRLF;
	// This is for the session devices
	// if ($params['deviceID'] != null && $params['deviceID'] == DEVICE_CURRENT_SESSION) {
	// // echo "1****".$params['deviceID'].CRLF;
	
		// if (isset($params['SESSION']) && array_key_exists('properties', $params['SESSION']) && array_key_exists('SelectedPlayer', $params['SESSION']['properties'])   ) {
			// $params['deviceID'] = $params['SESSION']['properties']['SelectedPlayer']['value'];
			// // echo "Replaced deviceID ".$_SESSION['properties']['SelectedPlayer']['value'].CRLF;
		// } 
		// unset ($pattern);
		// unset ($newprops);
		// foreach ($_SESSION['properties'] as $key => $value) {
			// $pattern[$key]="/\{".$key."\}/";
			// $newprops[$key]=$value['value'];
		// }
		// if (DEBUG_PHOLDERS) {
			// echo "<pre>Session Properties "; echo "DATA-1:"; print_r ($newprops); echo "</pre>";
			// echo "<pre>Session Properties "; echo "PATTERN-1:"; print_r ($pattern); echo "</pre>";
		// }
		// $mess_subject = str_replace("{session___", "{", $mess_subject);
		// $mess_subject = preg_replace($pattern, $newprops, $mess_subject);
		// if ($mess_text != Null) {
			// $mess_text = str_replace("{session___", "{", $mess_text);
			// $mess_text = preg_replace($pattern, $newprops, $mess_text); 
		// }
	// }
	// echo "2****".$params['deviceID'].CRLF;

	// This is for the device properties
	if ($params['deviceID'] != null) {
		$mysqlp = 'SELECT ha_mi_properties.description, ha_mf_device_properties.value FROM ha_mf_device_properties 
					JOIN ha_mi_properties ON ha_mf_device_properties.propertyID = ha_mi_properties.id 
					WHERE ha_mf_device_properties.deviceID ='.$params['deviceID'];
		if ($props = FetchRows($mysqlp)) {
		// print_r($props);
			unset ($pattern);
			unset ($newprops);
			foreach ($props as $key => $value) {
				$pattern[$value['description']]="/\{".$value['description']."\}/";
				$newprops[$value['description']]=$value['value'];
			}
			if (DEBUG_PHOLDERS) {
				echo "<pre>Device Properties "; echo "DATA-2:"; print_r ($newprops); echo "</pre>";
				echo "<pre>Device Properties "; echo "PATTERN-2:"; print_r ($pattern); echo "</pre>";
			}
			$mess_subject = str_replace("{property___", "{", $mess_subject);
			$mess_subject = preg_replace($pattern, $newprops, $mess_subject);
			if ($mess_text != Null) {
				$mess_text = str_replace("{property___", "{", $mess_text);
				$mess_text = preg_replace($pattern, $newprops, $mess_text); 
			}
		}
	}
	

	// This is for all other values in the params i.e. Message
	// Do this twice (placeholders in placeholders, ie {message} => {commandvalue0}|{commandvalue1}
	if ($params['deviceID'] != Null) {

		for ($i = 1; $i <= 1; $i++) {		// Only once???

			// Handle commandvalue1, ...
			if (array_key_exists('commandvalue', $params) && strpos($params['commandvalue'],'|') !== false) {
				$cvs = explode('|', $params['commandvalue']);
				foreach ($cvs as $key => $value) {
					$params['commandvalue'.$key] = $value;
				}
			}

			if (array_key_exists('callerID', $params)) {
				if ($cd = FetchRow("SELECT description FROM ha_mf_devices WHERE ha_mf_devices.id =".$params['callerID']))  {
					$params['callerID___description']= $cd['description']; 
				}
			}	

			unset ($pattern);
			foreach ($params as $key => $value) {
				if (is_array($value)) unset($params[$key]);
				$pattern[$key]="/\{".$key."\}/";
			}

			if (DEBUG_PHOLDERS) {
				echo "<pre>Param Values "; echo "DATA-3:"; print_r ($params); echo "</pre>";
				echo "<pre>Param Values "; echo "PATTERN-3:"; print_r ($pattern); echo "</pre>";
			}

			// Why whole array, lest just do on base, be
			// $mess_subject = preg_replace_array($pattern, $params, $mess_subject);
			// if ($mess_text != Null) $mess_text = preg_replace_array($pattern, $params, $mess_text); 
			$mess_subject = preg_replace($pattern, $params, $mess_subject);
			if ($mess_text != Null) $mess_text=preg_replace($pattern, $params, $mess_text); // twice to support tag in tag
			
		}
	}

	return true;
}

function preg_replace_array($pattern, $replacement, $subject, $limit=-1) {
    if (is_array($subject)) {
        foreach ($subject as &$value) $value=preg_replace_array($pattern, $replacement, $value, $limit);
        return $subject;
    } else {
        return preg_replace($pattern, $replacement, $subject, $limit);
    }
}  
?>
