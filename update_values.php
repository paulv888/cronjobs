<?php
require 'connect-db.php';
include_once 'defines.php';
include_once 'includes/shared_db.php';
include_once 'includes/shared_ha.php';
include_once 'includes/shared_gen.php';

define("MY_DEVICE_ID", 135);

echo UpdateRowvalues()." Calculation Queries Executed <br/>\r\n";
echo UpdateMylink(MY_DEVICE_ID)." My Link Updated <br/>\r\n";

function UpdateRowvalues() {

	$queries=0;

	// update min/max
	$mysql='UPDATE `ha_weather_now`  w JOIN `ha_vw_weather_min_max`  m ON w.deviceID = m.deviceID SET w.`min`=m.`min`, w.`max`= m.`max` WHERE 1';
	(!mysql_query($mysql) ? mySqlError($mysql) : $queries+=1);
	
	return $queries;
} 