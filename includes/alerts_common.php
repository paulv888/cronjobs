<?php

//define('DEBUG_ALERT', TRUE);
if (!defined('DEBUG_ALERT')) define( 'DEBUG_ALERT', FALSE );

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
		if ($params['deviceID'] != null) {

		$mysql = ' SELECT DISTINCT `ha_mf_devices`.`updatedate` AS `ha_mf_devices___updatedate`, `ha_mf_devices`.`updatedate` AS `ha_mf_devices___updatedate_raw`, `ha_mf_devices`.`id` AS `ha_mf_devices___id`, `ha_mf_devices`.`id` AS `ha_mf_devices___id_raw`, `ha_mf_devices`.`description` AS `ha_mf_devices___description`, `ha_mf_devices`.`description` AS `ha_mf_devices___description_raw`, `ha_mf_devices`.`shortdesc` AS `ha_mf_devices___shortdesc`, `ha_mf_devices`.`shortdesc` AS `ha_mf_devices___shortdesc_raw`, `ha_mf_devices`.`code` AS `ha_mf_devices___code`, `ha_mf_devices`.`code` AS `ha_mf_devices___code_raw`, `ha_mf_devices`.`unit` AS `ha_mf_devices___unit`, `ha_mf_devices`.`unit` AS `ha_mf_devices___unit_raw`, `ha_mf_devices`.`typeID` AS `ha_mf_devices___typeID_raw`, ha_mf_device_types.description AS `ha_mf_devices___typeID`, `ha_mf_devices`.`locationID` AS `ha_mf_devices___locationID_raw`,    ha_mf_locations.description AS `ha_mf_devices___locationID`, `ha_mf_devices`.`inuse` AS `ha_mf_devices___inuse`, `ha_mf_devices`.`inuse` AS `ha_mf_devices___inuse_raw`, `ha_mf_devices`.`ipaddressID` AS `ha_mf_devices___ipaddressID_raw`, `ha_mf_device_ipaddress`.`ip` AS `ha_mf_devices___ipaddressID`, `ha_mf_devices`.`connectionID` AS `ha_mf_devices___connectionID_raw`, `ha_mi_connection`.`name` AS `ha_mf_devices___connectionID`, `ha_mf_devices`.`commandclassID` AS `ha_mf_devices___commandclassID_raw`, `ha_mf_commands_class_dd`.`description` AS `ha_mf_devices___commandclassID`, `ha_mf_devices`.`sort` AS `ha_mf_devices___sort`, `ha_mf_devices`.`sort` AS `ha_mf_devices___sort_raw`, `ha_mf_device_group`.`id` AS `ha_mf_device_group___id`, `ha_mf_device_group`.`id` AS `ha_mf_device_group___id_raw`, `ha_mf_device_group`.`groupID` AS `ha_mf_device_group___groupID_raw`, `ha_mf_groups`.`description` AS `ha_mf_device_group___groupID`, `ha_mf_device_group`.`deviceID` AS `ha_mf_device_group___deviceID`, `ha_mf_device_group`.`deviceID` AS `ha_mf_device_group___deviceID_raw`, `ha_mf_device_group`.`updatedate` AS `ha_mf_device_group___updatedate`, `ha_mf_device_group`.`updatedate` AS `ha_mf_device_group___updatedate_raw`, `ha_mf_device_properties`.`id` AS `ha_mf_device_properties___id`, `ha_mf_device_properties`.`id` AS `ha_mf_device_properties___id_raw`, `ha_mf_device_properties`.`deviceID` AS `ha_mf_device_properties___deviceID`, `ha_mf_device_properties`.`deviceID` AS `ha_mf_device_properties___deviceID_raw`, `ha_mf_device_properties`.`propertyID` AS `ha_mf_device_properties___propertyID_raw`, ha_mi_properties.description AS `ha_mf_device_properties___propertyID`, `ha_mf_device_properties`.`value` AS `ha_mf_device_properties___value`, `ha_mf_device_properties`.`value` AS `ha_mf_device_properties___value_raw`, `ha_mf_device_properties`.`updatedate` AS `ha_mf_device_properties___updatedate`, `ha_mf_device_properties`.`updatedate` AS `ha_mf_device_properties___updatedate_raw`, `ha_mf_device_properties`.`sort` AS `ha_mf_device_properties___sort`, `ha_mf_device_properties`.`sort` AS `ha_mf_device_properties___sort_raw`, `ha_mf_monitor_link`.`linkmonitor` AS `ha_mf_monitor_link___linkmonitor`, `ha_mf_monitor_link`.`linkmonitor` AS `ha_mf_monitor_link___linkmonitor_raw`, `ha_mf_monitor_link`.`id` AS `ha_mf_monitor_link___id`, `ha_mf_monitor_link`.`id` AS `ha_mf_monitor_link___id_raw`, `ha_mf_monitor_link`.`active` AS `ha_mf_monitor_link___active`, `ha_mf_monitor_link`.`active` AS `ha_mf_monitor_link___active_raw`, `ha_mf_monitor_link`.`listenfor1` AS `ha_mf_monitor_link___listenfor1_raw`, `ha_vw_commands_0`.`Description` AS `ha_mf_monitor_link___listenfor1`, `ha_mf_monitor_link`.`updatedate` AS `ha_mf_monitor_link___updatedate`, `ha_mf_monitor_link`.`updatedate` AS `ha_mf_monitor_link___updatedate_raw`, `ha_mf_monitor_link`.`listenfor2` AS `ha_mf_monitor_link___listenfor2_raw`, `ha_vw_commands`.`Description` AS `ha_mf_monitor_link___listenfor2`, `ha_mf_monitor_link`.`link_timeout` AS `ha_mf_monitor_link___link_timeout`, `ha_mf_monitor_link`.`link_timeout` AS `ha_mf_monitor_link___link_timeout_raw`, `ha_mf_monitor_link`.`link_warning` AS `ha_mf_monitor_link___link_warning`, `ha_mf_monitor_link`.`link_warning` AS `ha_mf_monitor_link___link_warning_raw`, `ha_mf_monitor_link`.`pingport` AS `ha_mf_monitor_link___pingport`, `ha_mf_monitor_link`.`pingport` AS `ha_mf_monitor_link___pingport_raw`, `ha_mf_monitor_link`.`deviceID` AS `ha_mf_monitor_link___deviceID`, `ha_mf_monitor_link`.`deviceID` AS `ha_mf_monitor_link___deviceID_raw`, `ha_mf_monitor_property`.`id` AS `ha_mf_monitor_property___id`, `ha_mf_monitor_property`.`id` AS `ha_mf_monitor_property___id_raw`, `ha_mf_monitor_property`.`deviceID` AS `ha_mf_monitor_property___deviceID`, `ha_mf_monitor_property`.`deviceID` AS `ha_mf_monitor_property___deviceID_raw`, `ha_mf_monitor_property`.`propertyID` AS `ha_mf_monitor_property___propertyID_raw`, `ha_mi_properties_1`.`description` AS `ha_mf_monitor_property___propertyID`, `ha_mf_monitor_property`.`active` AS `ha_mf_monitor_property___active`, `ha_mf_monitor_property`.`active` AS `ha_mf_monitor_property___active_raw`, `ha_mf_monitor_property`.`invertstatus` AS `ha_mf_monitor_property___invertstatus`, `ha_mf_monitor_property`.`invertstatus` AS `ha_mf_monitor_property___invertstatus_raw`, `ha_mf_monitor_property`.`toggleignore` AS `ha_mf_monitor_property___toggleignore`, `ha_mf_monitor_property`.`toggleignore` AS `ha_mf_monitor_property___toggleignore_raw`, `ha_mf_monitor_property`.`updatedate` AS `ha_mf_monitor_property___updatedate`, `ha_mf_monitor_property`.`updatedate` AS `ha_mf_monitor_property___updatedate_raw`, `ha_mf_monitor_property`.`sort` AS `ha_mf_monitor_property___sort`, `ha_mf_monitor_property`.`sort` AS `ha_mf_monitor_property___sort_raw`, `ha_mf_monitor_triggers`.`id` AS `ha_mf_monitor_triggers___id`, `ha_mf_monitor_triggers`.`id` AS `ha_mf_monitor_triggers___id_raw`, `ha_mf_monitor_triggers`.`deviceID` AS `ha_mf_monitor_triggers___deviceID`, `ha_mf_monitor_triggers`.`deviceID` AS `ha_mf_monitor_triggers___deviceID_raw`, `ha_mf_monitor_triggers`.`propertyID` AS `ha_mf_monitor_triggers___propertyID_raw`, `ha_mi_properties_0`.`description` AS `ha_mf_monitor_triggers___propertyID`, `ha_mf_monitor_triggers`.`triggertype` AS `ha_mf_monitor_triggers___triggertype`, `ha_mf_monitor_triggers`.`triggertype` AS `ha_mf_monitor_triggers___triggertype_raw`, `ha_mf_monitor_triggers`.`schemeID` AS `ha_mf_monitor_triggers___schemeID_raw`, `ha_remote_schemes`.`name` AS `ha_mf_monitor_triggers___schemeID`, `ha_mf_monitor_triggers`.`updatedate` AS `ha_mf_monitor_triggers___updatedate`, `ha_mf_monitor_triggers`.`updatedate` AS `ha_mf_monitor_triggers___updatedate_raw`, `ha_mf_monitor_triggers`.`sort` AS `ha_mf_monitor_triggers___sort`, `ha_mf_monitor_triggers`.`sort` AS `ha_mf_monitor_triggers___sort_raw`, `ha_mf_devices_thermostat`.`id` AS `ha_mf_devices_thermostat___id`, `ha_mf_devices_thermostat`.`id` AS `ha_mf_devices_thermostat___id_raw`, `ha_mf_devices_thermostat`.`site1` AS `ha_mf_devices_thermostat___site1`, `ha_mf_devices_thermostat`.`site1` AS `ha_mf_devices_thermostat___site1_raw`, `ha_mf_devices_thermostat`.`empty1` AS `ha_mf_devices_thermostat___empty1`, `ha_mf_devices_thermostat`.`empty1` AS `ha_mf_devices_thermostat___empty1_raw`, `ha_mf_devices_thermostat`.`deviceID` AS `ha_mf_devices_thermostat___deviceID`, `ha_mf_devices_thermostat`.`deviceID` AS `ha_mf_devices_thermostat___deviceID_raw`, `ha_mf_devices_thermostat`.`tstat_uuid` AS `ha_mf_devices_thermostat___tstat_uuid`, `ha_mf_devices_thermostat`.`tstat_uuid` AS `ha_mf_devices_thermostat___tstat_uuid_raw`, `ha_mf_devices_thermostat`.`model` AS `ha_mf_devices_thermostat___model`, `ha_mf_devices_thermostat`.`model` AS `ha_mf_devices_thermostat___model_raw`, `ha_mf_devices_thermostat`.`fw_version` AS `ha_mf_devices_thermostat___fw_version`, `ha_mf_devices_thermostat`.`fw_version` AS `ha_mf_devices_thermostat___fw_version_raw`, `ha_mf_devices_thermostat`.`wlan_fw_version` AS `ha_mf_devices_thermostat___wlan_fw_version`, `ha_mf_devices_thermostat`.`wlan_fw_version` AS `ha_mf_devices_thermostat___wlan_fw_version_raw`, `ha_mf_devices_thermostat`.`name` AS `ha_mf_devices_thermostat___name`, `ha_mf_devices_thermostat`.`name` AS `ha_mf_devices_thermostat___name_raw`, `ha_mf_devices_thermostat`.`description` AS `ha_mf_devices_thermostat___description`, `ha_mf_devices_thermostat`.`description` AS `ha_mf_devices_thermostat___description_raw`, `ha_mf_devices_thermostat`.`away_heat_temp_c` AS `ha_mf_devices_thermostat___away_heat_temp_c`, `ha_mf_devices_thermostat`.`away_heat_temp_c` AS `ha_mf_devices_thermostat___away_heat_temp_c_raw`, `ha_mf_devices_thermostat`.`here_temp_heat_c` AS `ha_mf_devices_thermostat___here_temp_heat_c`, `ha_mf_devices_thermostat`.`here_temp_heat_c` AS `ha_mf_devices_thermostat___here_temp_heat_c_raw`, `ha_mf_devices_thermostat`.`away_cool_temp_c` AS `ha_mf_devices_thermostat___away_cool_temp_c`, `ha_mf_devices_thermostat`.`away_cool_temp_c` AS `ha_mf_devices_thermostat___away_cool_temp_c_raw`, `ha_mf_devices_thermostat`.`here_temp_cool_c` AS `ha_mf_devices_thermostat___here_temp_cool_c`, `ha_mf_devices_thermostat`.`here_temp_cool_c` AS `ha_mf_devices_thermostat___here_temp_cool_c_raw`, `ha_mf_device_arduino`.`id` AS `ha_mf_device_arduino___id`, `ha_mf_device_arduino`.`id` AS `ha_mf_device_arduino___id_raw`, `ha_mf_device_arduino`.`deviceID` AS `ha_mf_device_arduino___deviceID`, `ha_mf_device_arduino`.`deviceID` AS `ha_mf_device_arduino___deviceID_raw`, `ha_mf_device_arduino`.`name1` AS `ha_mf_device_arduino___name1`, `ha_mf_device_arduino`.`name1` AS `ha_mf_device_arduino___name1_raw`, `ha_mf_device_arduino`.`value1` AS `ha_mf_device_arduino___value1`, `ha_mf_device_arduino`.`value1` AS `ha_mf_device_arduino___value1_raw`, `ha_mf_device_arduino`.`dummy1` AS `ha_mf_device_arduino___dummy1`, `ha_mf_device_arduino`.`dummy1` AS `ha_mf_device_arduino___dummy1_raw`, `ha_mf_device_arduino`.`name2` AS `ha_mf_device_arduino___name2`, `ha_mf_device_arduino`.`name2` AS `ha_mf_device_arduino___name2_raw`, `ha_mf_device_arduino`.`value2` AS `ha_mf_device_arduino___value2`, `ha_mf_device_arduino`.`value2` AS `ha_mf_device_arduino___value2_raw`, `ha_mf_device_arduino`.`dummy2` AS `ha_mf_device_arduino___dummy2`, `ha_mf_device_arduino`.`dummy2` AS `ha_mf_device_arduino___dummy2_raw`, `ha_mf_device_arduino`.`name3` AS `ha_mf_device_arduino___name3`, `ha_mf_device_arduino`.`name3` AS `ha_mf_device_arduino___name3_raw`, `ha_mf_device_arduino`.`value3` AS `ha_mf_device_arduino___value3`, `ha_mf_device_arduino`.`value3` AS `ha_mf_device_arduino___value3_raw`, `ha_mf_device_arduino`.`dummy3` AS `ha_mf_device_arduino___dummy3`, `ha_mf_device_arduino`.`dummy3` AS `ha_mf_device_arduino___dummy3_raw`, `ha_mf_device_arduino`.`name4` AS `ha_mf_device_arduino___name4`, `ha_mf_device_arduino`.`name4` AS `ha_mf_device_arduino___name4_raw`, `ha_mf_device_arduino`.`value4` AS `ha_mf_device_arduino___value4`, `ha_mf_device_arduino`.`value4` AS `ha_mf_device_arduino___value4_raw`, `ha_mf_device_arduino`.`dummy4` AS `ha_mf_device_arduino___dummy4`, `ha_mf_device_arduino`.`dummy4` AS `ha_mf_device_arduino___dummy4_raw`, `ha_mf_device_arduino`.`name5` AS `ha_mf_device_arduino___name5`, `ha_mf_device_arduino`.`name5` AS `ha_mf_device_arduino___name5_raw`, `ha_mf_device_arduino`.`value5` AS `ha_mf_device_arduino___value5`, `ha_mf_device_arduino`.`value5` AS `ha_mf_device_arduino___value5_raw`, `ha_mf_device_arduino`.`dummy5` AS `ha_mf_device_arduino___dummy5`, `ha_mf_device_arduino`.`dummy5` AS `ha_mf_device_arduino___dummy5_raw`, `ha_mf_devices`.`id` AS slug , `ha_mf_devices`.`id` AS `__pk_val` FROM `ha_mf_devices` LEFT JOIN `ha_mf_locations` AS `ha_mf_locations` ON `ha_mf_locations`.`id` = `ha_mf_devices`.`locationID` LEFT JOIN `ha_mf_monitor_link` AS `ha_mf_monitor_link` ON `ha_mf_monitor_link`.`deviceID` = `ha_mf_devices`.`id` LEFT JOIN `ha_vw_commands` AS `ha_vw_commands` ON `ha_vw_commands`.`id` = `ha_mf_monitor_link`.`listenfor2` LEFT JOIN `ha_mf_devices_thermostat` AS `ha_mf_devices_thermostat` ON `ha_mf_devices_thermostat`.`deviceID` = `ha_mf_devices`.`id` LEFT JOIN `ha_vw_commands` AS `ha_vw_commands_0` ON `ha_vw_commands_0`.`id` = `ha_mf_monitor_link`.`listenfor1` LEFT JOIN `ha_mf_device_types` AS `ha_mf_device_types` ON `ha_mf_device_types`.`id` = `ha_mf_devices`.`typeID` LEFT JOIN `ha_mf_commands_class_dd` AS `ha_mf_commands_class_dd` ON `ha_mf_commands_class_dd`.`id` = `ha_mf_devices`.`commandclassID` LEFT JOIN `ha_mf_device_ipaddress` AS `ha_mf_device_ipaddress` ON `ha_mf_device_ipaddress`.`id` = `ha_mf_devices`.`ipaddressID` LEFT JOIN `ha_mi_connection` AS `ha_mi_connection` ON `ha_mi_connection`.`id` = `ha_mf_devices`.`connectionID` LEFT JOIN `ha_mf_monitor_triggers` AS `ha_mf_monitor_triggers` ON `ha_mf_monitor_triggers`.`deviceID` = `ha_mf_devices`.`id` LEFT JOIN `ha_remote_schemes` AS `ha_remote_schemes` ON `ha_remote_schemes`.`id` = `ha_mf_monitor_triggers`.`schemeID` LEFT JOIN `ha_mf_device_group` AS `ha_mf_device_group` ON `ha_mf_device_group`.`deviceID` = `ha_mf_devices`.`id` LEFT JOIN `ha_mf_groups` AS `ha_mf_groups` ON `ha_mf_groups`.`id` = `ha_mf_device_group`.`groupID` LEFT JOIN `ha_mf_device_arduino` AS `ha_mf_device_arduino` ON `ha_mf_device_arduino`.`deviceID` = `ha_mf_devices`.`id` LEFT JOIN `ha_mf_device_properties` AS `ha_mf_device_properties` ON `ha_mf_device_properties`.`deviceID` = `ha_mf_devices`.`id` LEFT JOIN `ha_mi_properties` AS `ha_mi_properties` ON `ha_mi_properties`.`id` = `ha_mf_device_properties`.`propertyID` LEFT JOIN `ha_mi_properties` AS `ha_mi_properties_0` ON `ha_mi_properties_0`.`id` = `ha_mf_monitor_triggers`.`propertyID` LEFT JOIN `ha_mf_monitor_property` AS `ha_mf_monitor_property` ON `ha_mf_monitor_property`.`deviceID` = `ha_mf_devices`.`id` LEFT JOIN `ha_mi_properties` AS `ha_mi_properties_1` ON `ha_mi_properties_1`.`id` = `ha_mf_monitor_property`.`propertyID` WHERE ha_mf_devices.id = '.$params['deviceID'];
	
	$mysqlp = 'SELECT ha_mi_properties.description, ha_mf_device_properties.value FROM ha_mf_device_properties 
				JOIN ha_mi_properties ON ha_mf_device_properties.propertyID = ha_mi_properties.id 
				WHERE ha_mf_device_properties.deviceID ='.$params['deviceID'];
	}
 
if (DEBUG_ALERT) {
	echo "<pre> params"; print_r ($params); echo "</pre>";
	echo "<pre>"; echo $subject.CRLF; echo "</pre>";
	echo "<pre>"; echo $message.CRLF; echo "</pre>";
}	

	foreach ($params as $key => $value) {
		if (is_array($value)) unset($params[$key]);
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
		$subject = preg_replace_array($pattern, $params, $subject);
		if ($message != Null) $message=preg_replace_array($pattern, $params, $message); 
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

function preg_replace_array($pattern, $replacement, $subject, $limit=-1) {
    if (is_array($subject)) {
        foreach ($subject as &$value) $value=preg_replace_array($pattern, $replacement, $value, $limit);
        return $subject;
    } else {
        return preg_replace($pattern, $replacement, $subject, $limit);
    }
}  
?>
