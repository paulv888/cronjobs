<?php
require_once 'includes.php';

define("MY_DEVICE_ID", 135);

echo UpdateRowvalues()." Calculation Queries Executed <br/>\r\n";
echo UpdateLink(MY_DEVICE_ID)." My Link Updated <br/>\r\n";

function UpdateRowvalues() {

	$queries=0;

	// update min/max
	$mysql='UPDATE `ha_weather_now`  w JOIN `ha_vw_weather_min_max`  m ON w.deviceID = m.deviceID SET w.`min`=m.`min`, w.`max`= m.`max` WHERE 1';
	(!mysql_query($mysql) ? mySqlError($mysql) : $queries+=1);
	
//	$mysql='UPDATE `ha_mf_device_types` SET `display_icon`= CONCAT('<i class="',`description`,' condensed-icon ',`booticon`,'"></i>') WHERE 1';

//	$mysql='UPDATE `ha_mf_locations` SET `display_icon`= CONCAT('<i class="',`description`,' condensed-icon ',`booticon`,'"></i>') WHERE 1';
 
	
	return $queries;
} 