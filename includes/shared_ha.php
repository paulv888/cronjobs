<?php
define( 'DEBUG', FALSE );

function ReloadScreenShot() {
	$url = 'http://htpc:8085/HipScreenShot.jpg';
	$img = 'images/HIPScreenshot.jpg';
	file_put_contents($img, file_get_contents($url));
	$post = RestClient::post('http://htpc:8085/index.htm');
	return  ReadCurlReturn($post);
}

function ReadCurlReturn($mpost) {
	$myreturn =	$mpost->getResponse();
	return $myreturn;
}

function UpdateMyLink($deviceID){

	$mysql = "Update `ha_vw_monitor_combined` Set " .
    			  " `mdate` = '" . date("Y-m-d H:i:s") . "'" .
    			  " Where(`deviceid` ='" . $deviceID . "')";
	if (!mysql_query($mysql)) mySqlError($mysql);
	return 1;
}

function UpdateThermType($deviceID, $typeid){

	$mysql = "Update `ha_mf_devices` Set " .
    			  " `time_date` = '" . date("Y-m-d H:i:s") . "'," .
    			  " `typeID` = " . $typeid . "" .
				  " Where(`myid` ='" . $deviceID . "')";
	if (!mysql_query($mysql)) mySqlError($mysql);
	return 1;
}

function UpdateWeatherNow($deviceID,$temp,$set_point){
	
	$mysql = "SELECT temperature_c FROM ha_weather_now  WHERE deviceID = ".$deviceID; 
	if ($row = FetchRow($mysql)) {
		$ttrend = 0;
		if ($temp>$row['temperature_c']) $ttrend=1;
		if ($temp<$row['temperature_c']) $ttrend=2;
	}
			
	$mysql = "UPDATE ha_weather_now SET time_date = '" . mygmdate("Y-m-d H:i:s"). "', mdate = '". mygmdate("Y-m-d H:i:s")."'," .
				" temperature_c = ". $temp ." , set_point = ". $set_point . ", ttrend = ".$ttrend." WHERE deviceID = ".$deviceID;

	if (!mysql_query($mysql)) mySqlError($mysql);
}
			
function UpdateStatus ($deviceid,$status) 
{
//		UPDATE `homeautomation`.`ha_vw_monitor_combined` SET `status` = '19' WHERE `ha_vw_monitor_combined`.`deviceID` =60;
	$now = date( 'Y-m-d H:i:s' );
//	$dstatus = ($status) ? STATUS_ON : STATUS_OFF;
	$mysql = "SELECT status, statusDate FROM ha_vw_monitor_combined WHERE deviceID = ".$deviceid;
	if ($row = FetchRow($mysql)) {
		if ($row['status'] != $status ) {	
			$mysql = "UPDATE ha_vw_monitor_combined SET status = " . $status . ", statusDate = '". $now . "' WHERE deviceID = ".$deviceid;
			if (!mysql_query($mysql)) mySqlError($mysql);
		}
	}
	return;
}	

function mygmdate($format) {
	return date($format,strtotime(gmdate($format)." +".date('I')." hour"));
}

function udate($format, $utimestamp = null)
{
    if (is_null($utimestamp))
        $utimestamp = microtime(true);

    $timestamp = floor($utimestamp);
    $milliseconds = round(($utimestamp - $timestamp) * 1000000);

    return date(preg_replace('`(?<!\\\\)u`', $milliseconds, $format), $timestamp);
}

?>
