<?php
//define( 'DEBUG', FALSE );

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
				  " Where(`id` ='" . $deviceID . "')";
	if (!mysql_query($mysql)) mySqlError($mysql);
	$mysql = "Update `ha_weather_now` Set " .
    			  " `time_date` = '" . date("Y-m-d H:i:s") . "'," .
    			  " `typeID` = " . $typeid . "" .
				  " Where(`deviceID` ='" . $deviceID . "')";
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

function logEvent($inout, $sourceID, $deviceID, $commandID, $value = Null, $extdata = Null, $logLevel = Null) {
	// Do some validation first

	// get device id
	// get repeat cnt
	$repeatcount=1;

	//	      '
	//        '  Check last Statuses for duplicate
	//        '
	//        mess = CStr(mIDs.InOutID) & CStr(mIDs.SourceID) & CStr(mIDs.DeviceID) & CStr(mIDs.CommandID)
	//        If mCmd.Operation = "send" Then
	//            OffSet = My.Settings.IgnoreSameSendMessageTime
	//        Else
	//            OffSet = My.Settings.IgnoreSameMessageTime
	//        End If
	//        mt = mCmd.oDTmS.oTime
	//        For m As Integer = mStack.Count To 1 Step -1
	//            If DateDiff(DateInterval.Second, mStack(m), mt) > OffSet Then mStack.Remove(m)
	//        Next
	//        If mStack.Contains(mess) Then
	//            WriteEvent("UPDATE `homeautomation`.`ha_events` SET `repeatcount` = `repeatcount` + '1' " & _
	//            "WHERE `mdate` = '" & mStack(mess) & "' and `inout` = '" & mIDs.InOutID & "' and `sourceID` ='" & mIDs.SourceID & _
	//                                         "' and `deviceID` = '" & mIDs.DeviceID & "' and `commandID` = '" & mIDs.CommandID & "';")
	//
	//            FilterMonitorMessage = pvLogLevelDebug
	//        Else
	//            mStack.Add(mCmd.oDTmS.oTime, CStr(mIDs.InOutID) & CStr(mIDs.SourceID) & CStr(mIDs.DeviceID) & CStr(mIDs.CommandID))
	//        End If

	//
	// Check for repeat statussues
	//
	$gmttime = mygmdate("Y-m-d H:i:s");
	$ms = udate("u");
	$time = date("Y-m-d H:i:s");

	$mysql = 'SELECT * FROM `ha_events` ' .
			' WHERE `inout` = '.$inout. ' AND `source` ='.$sourceID.' AND `deviceID` = '.$deviceID.' AND commandID = '.$commandID.
			' AND  DATE_ADD(`mdate`,INTERVAL 5 SECOND) > "'.$gmttime.'"';

	if (!$resevents=mysql_query($mysql)) {	
		mySqlError($mysql);
		return false;
	}
	if ($rowevents=mysql_fetch_array($resevents)) {
		$repeatcount = $rowevents['repeatcount'] + 1;
		$mysql='UPDATE `ha_events` SET `repeatcount` = '.$repeatcount. ' WHERE `ha_events`.`id` ='.$rowevents['id'];
		if (!mysql_query($mysql)) mySqlError($mysql);
	} else {
		//
		//	Get device type and monitorid
		//
		$mysql='SELECT `id`, `typeID`, `monitortypeID` FROM `ha_mf_devices` WHERE `id` ='.$deviceID;
		$resdevice=mysql_query($mysql);
		$rowdevice=mysql_fetch_array($resdevice);
		$devicetype=$rowdevice['typeID'];

		$mysql= 'INSERT INTO `ha_events` (
						`time_date` ,
						`mdate` ,
						`milliseconds` ,
						`inout` ,
						`source` ,
						`typeID` ,
						`deviceID` ,
						`commandID` ,
						`data` , 
						`extdata` ,
						`repeatcount` ,
						`logLevel`
					)
					VALUES ( '.
						'"'.$gmttime.'",'.
						'"'.$gmttime.'",'. 
					 	''.$ms.',' .		 				/* millisec 		*/
						''.$inout.','. 					/*  in 				*/
						''.$sourceID.','.			/* source arduino 	*/
						''.$devicetype.','.
						''.$deviceID.','.
						''.$commandID.','.
						'"'.$value.'",'.
						'"'.$extdata.'",' .
						''.$repeatcount.','.
						 	'1)';
		if (!mysql_query($mysql)) mySqlError($mysql);
	}
}
?>
