<?php
function replaceText($params, &$subject, &$message = NULL, $callerparams = Null){
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
	`ha_mf_devices`.`description` AS `ha_mf_devices___description_raw`, `ha_mf_devices`.`shortdesc` AS `ha_mf_devices___shortdesc`, `ha_mf_devices`.`shortdesc` AS 
	`ha_mf_devices___shortdesc_raw`, `ha_mf_devices`.`monitortypeID` AS `ha_mf_devices___monitortypeID_raw`, `ha_mi_monitor_type`.`description` AS `ha_mf_devices___monitortypeID`, 
	`ha_mf_devices`.`code` AS `ha_mf_devices___code`, `ha_mf_devices`.`code` AS `ha_mf_devices___code_raw`, `ha_mf_devices`.`unit` AS `ha_mf_devices___unit`, 
	`ha_mf_devices`.`unit` AS `ha_mf_devices___unit_raw`, 
	`ha_mf_devices`.`typeID` AS `ha_mf_devices___typeID_raw`, 
	`ha_mf_device_types`.`display_icon` AS `ha_mf_device_types___display_icon`,
	`ha_mf_device_types`.`description` AS  `ha_mf_device_types___description`,
	`ha_mf_devices`.`locationID` AS `ha_mf_devices___locationID_raw`, 
	`ha_mf_locations`.`display_icon` AS `ha_mf_locations___display_icon`, 
	`ha_mf_locations`.`description` AS `ha_mf_locations___description`, 
	`ha_mf_devices`.`inuse` AS `ha_mf_devices___inuse`, 
	`ha_mf_devices`.`inuse` AS `ha_mf_devices___inuse_raw`, `ha_mf_devices`.`ipaddressID` AS `ha_mf_devices___ipaddressID_raw`, 
	`ha_mf_device_ipaddress`.`name` AS `ha_mf_devices___ipaddressID`, `ha_mf_devices`.`devicelinkID` AS `ha_mf_devices___devicelinkID_raw`, 
	`ha_mf_device_links`.`name` AS `ha_mf_devices___devicelinkID`, `ha_mf_devices`.`commandclassID` AS `ha_mf_devices___commandclassID_raw`, 
	`ha_mf_commands_class_dd`.`description` AS `ha_mf_devices___commandclassID`, `ha_mf_devices`.`sort` AS `ha_mf_devices___sort`, 
	`ha_mf_devices`.`sort` AS `ha_mf_devices___sort_raw`, `ha_mf_monitor_status`.`updatedate` AS `ha_mf_monitor_status___updatedate`, 
	`ha_mf_monitor_status`.`updatedate` AS `ha_mf_monitor_status___updatedate_raw`, `ha_mf_monitor_status`.`deviceID` AS `ha_mf_monitor_status___deviceID`, 
	`ha_mf_monitor_status`.`deviceID` AS `ha_mf_monitor_status___deviceID_raw`, `ha_mf_monitor_status`.`toggleignore` AS `ha_mf_monitor_status___toggleignore`, 
	`ha_mf_monitor_status`.`toggleignore` AS `ha_mf_monitor_status___toggleignore_raw`, `ha_mf_monitor_status`.`id` AS `ha_mf_monitor_status___id`, 
	`ha_mf_monitor_status`.`commandvalue` AS `ha_mf_monitor_status___commandvalue`, `ha_mf_monitor_status`.`commandvalue` AS `ha_mf_monitor_status___commandvalue_raw`, 
	`ha_mf_monitor_status`.`id` AS `ha_mf_monitor_status___id_raw`, `ha_mf_monitor_status`.`status` AS `ha_mf_monitor_status___status`, `ha_mf_monitor_status`.`status` AS 
	`ha_mf_monitor_status___status_raw`, `ha_mf_monitor_status`.`statusDate` AS `ha_mf_monitor_status___statusDate`, `ha_mf_monitor_status`.`statusDate` AS 
	`ha_mf_monitor_status___statusDate_raw`, `ha_mf_monitor_status`.`invertstatus` AS `ha_mf_monitor_status___invertstatus`, `ha_mf_monitor_status`.`invertstatus` AS 
	`ha_mf_monitor_status___invertstatus_raw`, `ha_mf_monitor_link`.`linkmonitor` AS `ha_mf_monitor_link___linkmonitor`, `ha_mf_monitor_link`.`linkmonitor` AS 
	`ha_mf_monitor_link___linkmonitor_raw`, `ha_mf_monitor_link`.`id` AS `ha_mf_monitor_link___id`, `ha_mf_monitor_link`.`id` AS `ha_mf_monitor_link___id_raw`, 
	`ha_mf_monitor_link`.`updatedate` AS `ha_mf_monitor_link___updatedate`, `ha_mf_monitor_link`.`updatedate` AS `ha_mf_monitor_link___updatedate_raw`, 
	`ha_mf_monitor_link`.`listenfor1` AS `ha_mf_monitor_link___listenfor1_raw`, `ha_vw_commands_1`.`Description` AS `ha_mf_monitor_link___listenfor1`, `ha_mf_monitor_link`.`listenfor2` AS 
	`ha_mf_monitor_link___listenfor2_raw`, `ha_vw_commands`.`Description` AS `ha_mf_monitor_link___listenfor2`, `ha_mf_monitor_link`.`listenfor3` AS `ha_mf_monitor_link___listenfor3_raw`,
	`ha_vw_commands_0`.`Description` AS `ha_mf_monitor_link___listenfor3`, `ha_mf_monitor_link`.`link_warning` AS `ha_mf_monitor_link___link_warning`, `ha_mf_monitor_link`.`link_warning` AS
	`ha_mf_monitor_link___link_warning_raw`, `ha_mf_monitor_link`.`link_timeout` AS `ha_mf_monitor_link___link_timeout`, `ha_mf_monitor_link`.`link_timeout` AS
	`ha_mf_monitor_link___link_timeout_raw`, `ha_mf_monitor_link`.`pingport` AS `ha_mf_monitor_link___pingport`, `ha_mf_monitor_link`.`pingport` AS `ha_mf_monitor_link___pingport_raw`, 
	`ha_mf_monitor_link`.`link_timeout` AS `ha_mf_monitor_link___link_timeout`, `ha_mf_monitor_link`.`link_timeout` AS `ha_mf_monitor_link___link_timeout_raw`, `ha_mf_monitor_link`.`link`
	AS `ha_mf_monitor_link___link`, `ha_mf_monitor_link`.`link` AS `ha_mf_monitor_link___link_raw`, `ha_mf_monitor_link`.`mdate` AS `ha_mf_monitor_link___mdate`, `ha_mf_monitor_link`.`mdate`
	AS `ha_mf_monitor_link___mdate_raw`, `ha_mf_monitor_link`.`deviceID` AS `ha_mf_monitor_link___deviceID`, `ha_mf_monitor_link`.`deviceID` AS `ha_mf_monitor_link___deviceID_raw`, 
	`ha_mf_devices_thermostat`.`id` AS `ha_mf_devices_thermostat___id`, `ha_mf_devices_thermostat`.`id` AS `ha_mf_devices_thermostat___id_raw`, `ha_mf_devices_thermostat`.`site1` AS 
	`ha_mf_devices_thermostat___site1`, `ha_mf_devices_thermostat`.`site1` AS `ha_mf_devices_thermostat___site1_raw`, `ha_mf_devices_thermostat`.`empty1` AS 
	`ha_mf_devices_thermostat___empty1`, `ha_mf_devices_thermostat`.`empty1` AS `ha_mf_devices_thermostat___empty1_raw`, `ha_mf_devices_thermostat`.`deviceID` AS 
	`ha_mf_devices_thermostat___deviceID`, `ha_mf_devices_thermostat`.`deviceID` AS `ha_mf_devices_thermostat___deviceID_raw`, `ha_mf_devices_thermostat`.`tstat_uuid` 
	AS `ha_mf_devices_thermostat___tstat_uuid`, `ha_mf_devices_thermostat`.`tstat_uuid` AS `ha_mf_devices_thermostat___tstat_uuid_raw`, `ha_mf_devices_thermostat`.`model` 
	AS `ha_mf_devices_thermostat___model`, `ha_mf_devices_thermostat`.`model` AS `ha_mf_devices_thermostat___model_raw`, `ha_mf_devices_thermostat`.`fw_version` AS 
	`ha_mf_devices_thermostat___fw_version`, `ha_mf_devices_thermostat`.`fw_version` AS `ha_mf_devices_thermostat___fw_version_raw`, `ha_mf_devices_thermostat`.`wlan_fw_version` AS 
	`ha_mf_devices_thermostat___wlan_fw_version`, `ha_mf_devices_thermostat`.`wlan_fw_version` AS `ha_mf_devices_thermostat___wlan_fw_version_raw`, `ha_mf_devices_thermostat`.`name` AS 
	`ha_mf_devices_thermostat___name`, `ha_mf_devices_thermostat`.`name` AS `ha_mf_devices_thermostat___name_raw`, `ha_mf_devices_thermostat`.`description` AS
	`ha_mf_devices_thermostat___description`, `ha_mf_devices_thermostat`.`description` AS `ha_mf_devices_thermostat___description_raw`, `ha_mf_devices_thermostat`.`away_heat_temp_c` AS
	`ha_mf_devices_thermostat___away_heat_temp_c`, `ha_mf_devices_thermostat`.`away_heat_temp_c` AS `ha_mf_devices_thermostat___away_heat_temp_c_raw`, 
	`ha_mf_devices_thermostat`.`here_temp_heat_c` AS `ha_mf_devices_thermostat___here_temp_heat_c`, `ha_mf_devices_thermostat`.`here_temp_heat_c` AS 
	`ha_mf_devices_thermostat___here_temp_heat_c_raw`, `ha_mf_devices_thermostat`.`away_cool_temp_c` AS `ha_mf_devices_thermostat___away_cool_temp_c`, 
	`ha_mf_devices_thermostat`.`away_cool_temp_c` AS `ha_mf_devices_thermostat___away_cool_temp_c_raw`, `ha_mf_devices_thermostat`.`here_temp_cool_c` AS 
	`ha_mf_devices_thermostat___here_temp_cool_c`, `ha_mf_devices_thermostat`.`here_temp_cool_c` AS `ha_mf_devices_thermostat___here_temp_cool_c_raw`, `ha_mf_monitor_triggers`.`id` 
	AS `ha_mf_monitor_triggers___id`, `ha_mf_monitor_triggers`.`id` AS `ha_mf_monitor_triggers___id_raw`, `ha_mf_monitor_triggers`.`deviceID` AS `ha_mf_monitor_triggers___deviceID`,
	`ha_mf_monitor_triggers`.`deviceID` AS `ha_mf_monitor_triggers___deviceID_raw`, `ha_mf_monitor_triggers`.`statuslink` AS `ha_mf_monitor_triggers___statuslink`,
	`ha_mf_monitor_triggers`.`statuslink` AS `ha_mf_monitor_triggers___statuslink_raw`, `ha_mf_monitor_triggers`.`triggertype` AS `ha_mf_monitor_triggers___triggertype`,
	`ha_mf_monitor_triggers`.`triggertype` AS `ha_mf_monitor_triggers___triggertype_raw`, `ha_mf_monitor_triggers`.`schemeID` AS `ha_mf_monitor_triggers___schemeID_raw`, 
	`ha_remote_schemes`.`name` AS `ha_mf_monitor_triggers___schemeID`, `ha_mf_devices`.`id` AS slug , `ha_mf_devices`.`id` AS `__pk_val` FROM `ha_mf_devices` LEFT JOIN `ha_mf_locations` 
	AS `ha_mf_locations` ON `ha_mf_locations`.`id` = `ha_mf_devices`.`locationID` LEFT JOIN `ha_mi_monitor_type` AS `ha_mi_monitor_type` ON `ha_mi_monitor_type`.
	`id` = `ha_mf_devices`.`monitortypeID` LEFT JOIN `ha_mf_monitor_link` AS `ha_mf_monitor_link` ON `ha_mf_monitor_link`.`deviceID` = `ha_mf_devices`.`id` LEFT JOIN `ha_vw_commands`
	AS `ha_vw_commands` ON `ha_vw_commands`.`id` = `ha_mf_monitor_link`.`listenfor2` LEFT JOIN `ha_vw_commands` AS `ha_vw_commands_0` ON `ha_vw_commands_0`.`id` = 
	`ha_mf_monitor_link`.`listenfor3` LEFT JOIN `ha_mf_monitor_status` AS `ha_mf_monitor_status` ON `ha_mf_monitor_status`.`deviceID` = `ha_mf_devices`.`id` 
	LEFT JOIN `ha_mf_devices_thermostat` AS `ha_mf_devices_thermostat` ON `ha_mf_devices_thermostat`.`deviceID` = `ha_mf_devices`.`id` LEFT JOIN `ha_vw_commands` AS `ha_vw_commands_1` 
	ON `ha_vw_commands_1`.`id` = `ha_mf_monitor_link`.`listenfor1` LEFT JOIN `ha_mf_device_types` AS `ha_mf_device_types` ON `ha_mf_device_types`.`id` = `ha_mf_devices`.`typeID` 
	LEFT JOIN `ha_mf_commands_class_dd` AS `ha_mf_commands_class_dd` ON `ha_mf_commands_class_dd`.`id` = `ha_mf_devices`.`commandclassID` LEFT JOIN `ha_mf_device_ipaddress` AS 
	`ha_mf_device_ipaddress` ON `ha_mf_device_ipaddress`.`id` = `ha_mf_devices`.`ipaddressID` LEFT JOIN `ha_mf_device_links` AS `ha_mf_device_links` ON `ha_mf_device_links`.`id` = 
	`ha_mf_devices`.`devicelinkID` LEFT JOIN `ha_mf_monitor_triggers` AS `ha_mf_monitor_triggers` ON `ha_mf_monitor_triggers`.`deviceID` = `ha_mf_devices`.`id`
	LEFT JOIN `ha_remote_schemes` AS `ha_remote_schemes` ON `ha_remote_schemes`.`id` = `ha_mf_monitor_triggers`.`schemeID` WHERE ha_mf_devices.id = '.$params['deviceID'];
	}
 
//echo "<pre>"; print_r ($params); echo "</pre>";
 
	if (isset($mysql)) {

		if ($data = FetchRow($mysql)) {
			foreach ($data as $key => $value) {
				$pattern[$key]="/\{".$key."\}/";
			}
//echo "<pre>"; print_r ($data); echo "</pre>";
//echo "<pre>"; print_r ($pattern); echo "</pre>";
			$subject=preg_replace($pattern, $data, $subject);
			$subject=preg_replace($pattern, $data, $subject); // twice to support tag in tag
			if ($message != Null) $message=preg_replace($pattern, $data, $message); // twice to support tag in tag
			if ($message != Null) $message=preg_replace($pattern, $data, $message);
		}
	}
	if ($callerparams != Null) {
		unset ($pattern);
		foreach ($callerparams as $key => $value) {
			$pattern[$key]="/\{".$key."\}/";
		}
//echo "<pre>"; print_r ($callerparams); echo "</pre>";
//echo "<pre>"; print_r ($pattern); echo "</pre>";
		$subject=preg_replace($pattern, $callerparams, $subject);
		if ($message != Null) $message=preg_replace($pattern, $callerparams, $message); // twice to support tag in tag
	}

	
	return true;
}


function sendmail($to, $subject, $message, $fromname) {

	$mailer = new PHPMailer();
	$mailer->IsSMTP();
	$mailer->Host = 'ssl://smtp.gmail.com:465';
	$mailer->SMTPAuth = true;
	
	$mailer->Username = GMAIL_USER;
	$mailer->Password = GMAIL_PASSWORD;
	
	$mailer->From = GMAIL_USER;
	$mailer->FromName = $fromname;
	$mailer->Body = $message;
	$mailer->Subject = $subject;
	
	$mailer->AddAddress($to);
	$mailer->AddCustomHeader("Content-Type: text/html; charset=UTF-8\r\n");
	$send = 0;
	
	if(!$mailer->Send()) {
	    error_log("Mailer :  error ".$mailer->ErrorInfo)." : $to";
	    return false;
	}
	else {
		return true;
	}
}
?>