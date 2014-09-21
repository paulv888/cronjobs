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

	
	// update dusk/dawn
	if ($rows = FetchRows('SELECT HOUR(`updatedate`) * 60 + MINUTE(`updatedate`) as minutes
						FROM ha_events
						WHERE `deviceID` ='.DEVICE_DARK_OUTSIDE.' AND `commandID` = ' .COMMAND_OFF.
						' ORDER BY id DESC LIMIT 10')) {
		foreach ($rows as $row) $dawn[] = $row['minutes'];
		$time = strtotime('00:00');
		if (RunQuery('UPDATE `homeautomation`.`ha_mf_device_extra` SET `dawn` = "'.date("H:i:s", strtotime((int)calculate_median($dawn)." minutes", $time)).'" WHERE `ha_mf_device_extra`.`deviceID` = '.DEVICE_DARK_OUTSIDE)) 	$queries++;
	}
	if ($rows = FetchRows('SELECT HOUR(`updatedate`) * 60 + MINUTE(`updatedate`) as minutes
						FROM ha_events
						WHERE `deviceID` ='.DEVICE_DARK_OUTSIDE.' AND `commandID` = ' .COMMAND_ON.
						' ORDER BY id DESC LIMIT 10')) {
		
		foreach ($rows as $row) $dusk[] = $row['minutes'];
		$time = strtotime('00:00');
		if (RunQuery('UPDATE `homeautomation`.`ha_mf_device_extra` SET `dusk` = "'.date("H:i:s", strtotime((int)calculate_median($dusk)." minutes", $time)).'" WHERE `ha_mf_device_extra`.`deviceID` = '.DEVICE_DARK_OUTSIDE)) $queries++;
	}
	return $queries;
}
function calculate_median($arr) {
    sort($arr);
    $count = count($arr); //total numbers in array
    $middleval = floor(($count-1)/2); // find the middle value, or the lowest middle value
    if($count % 2) { // odd number, middle is the median
        $median = $arr[$middleval];
    } else { // even number, calculate avg of 2 medians
        $low = $arr[$middleval];
        $high = $arr[$middleval+1];
        $median = (($low+$high)/2);
    }
    return $median;
}
?>