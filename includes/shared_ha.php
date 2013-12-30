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

function UpdateMyLink($deviceid, $link = LINK_UP, $callsource = 0, $commandid = 0){

//      echo "*************".$deviceid."*************";

	$mysql = "SELECT * FROM `ha_mf_monitor_link` WHERE deviceID = ".$deviceid; 
	if ($row = FetchRow($mysql)) {
		$prevlink = $row['link'];
		if ($prevlink != $link) { 					// status changed
			if ($prevlink == LINK_UP) { 			// Link was up, wait for error time before acknowledge as error
				if ($row['frequency2'] != Null) {
					$temp = explode(" ", $row['frequency2']);
					$temp2 = explode(":", $temp[1]);
					$min = $temp[0]*1440 + $temp2[0]*60 + $temp2[1];
					$last = strtotime($row['mdate']);
					$nowdt = strtotime(date("Y-m-d H:i:s"));
//					echo $row['mdate']."</br>\n";
//					echo date("Y-m-d H:i:s")."</br>\n";
//					echo abs($nowdt-$last) / 60 . "min</br>\n";
					if (abs($nowdt-$last) / 60 > $min) {
						echo "Down, Previous was up; Time expired"."</br>\n";
						$log = Array ('inout' => COMMAND_SEND, 'sourceID' => $callsource, 'deviceID' => $deviceid, 'commandID' => $commandid, 'data' => ($link == LINK_UP ? "Up" : "Down"));
						logEvent($log);
						$mysql = "Update `ha_vw_monitor_combined` Set " .
								  " `mdate` = '" . date("Y-m-d H:i:s") . "'," .
								  " `link` = '" . $link . "'" .
								  " Where(`deviceid` ='" . $deviceid . "')";
						if (!mysql_query($mysql)) mySqlError($mysql);
						if ($row['on_change']) echo RunScheme ($row['on_change'], $callsource);
					} else {
						echo "Down, Previous was up; Time expired"."</br>\n";
					}
				}
			} else {   								// previous was down update to online and log event
				echo "Up, Previous was down; Update to online and log event"."</br>\n";
				$log = Array ('inout' => COMMAND_SEND, 'sourceID' => $callsource, 'deviceID' => $deviceid, 'commandID' => $commandid, 'data' => ($link == LINK_UP ? "Up" : "Down"));
				logEvent($log);
				$mysql = "Update `ha_vw_monitor_combined` Set " .
						  " `mdate` = '" . date("Y-m-d H:i:s") . "'," .
						  " `link` = '" . $link . "'" .
						  " Where(`deviceid` ='" . $deviceid . "')";
				if (!mysql_query($mysql)) mySqlError($mysql);
				if ($row['on_change']) echo RunScheme ( $row['on_change'], $callsource);
			}
		} else {
			if ($link == LINK_UP) { 			// Link is up, update time
//				echo "Up and same as prev status only update time"."</br>\n";
				$mysql = "Update `ha_vw_monitor_combined` Set " .
						  " `mdate` = '" . date("Y-m-d H:i:s") . "'" .
						  " Where(`deviceid` ='" . $deviceid . "')";
				if (!mysql_query($mysql)) mySqlError($mysql);
			}
		}
	} 
	return 1;
}

function UpdateThermType($deviceid, $typeid){

	$mysql = "Update `ha_mf_devices` Set " .
    			  " `time_date` = '" . date("Y-m-d H:i:s") . "'," .
    			  " `typeID` = " . $typeid . "" .
				  " Where(`id` ='" . $deviceid . "')";
	if (!mysql_query($mysql)) mySqlError($mysql);
	$mysql = "Update `ha_weather_now` Set " .
    			  " `time_date` = '" . date("Y-m-d H:i:s") . "'," .
    			  " `typeID` = " . $typeid . "" .
				  " Where(`deviceID` ='" . $deviceid . "')";
	if (!mysql_query($mysql)) mySqlError($mysql);
	return 1;
}

function UpdateWeatherNow($deviceid,$temp,$set_point){
	
	$mysql = "SELECT temperature_c FROM ha_weather_now  WHERE deviceID = ".$deviceid; 
	if ($row = FetchRow($mysql)) {
		$ttrend = 0;
		if ($temp>$row['temperature_c']) $ttrend=1;
		if ($temp<$row['temperature_c']) $ttrend=2;
	}
			
	$mysql = "UPDATE ha_weather_now SET time_date = '" . mygmdate("Y-m-d H:i:s"). "', mdate = '". mygmdate("Y-m-d H:i:s")."'," .
				" temperature_c = ". $temp ." , set_point = ". $set_point . ", ttrend = ".$ttrend." WHERE deviceID = ".$deviceid;

	if (!mysql_query($mysql)) mySqlError($mysql);
}
			
function UpdateStatus ($deviceid,$status) 
{

	$now = date( 'Y-m-d H:i:s' );
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

function logEvent($log) {
	//	$inout, $source, $deviceID, $commandID, $value = Null, $extdata = Null, $logLevel = Null) {
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

	if (!array_key_exists("deviceID",$log )) $log['deviceID'] = Null;
	if (!array_key_exists("commandID",$log )) $log['commandID'] = Null;
	if (!array_key_exists("inout",$log )) $log['inout'] = Null;
	if (!array_key_exists("sourceID",$log )) $log['sourceID'] = Null;
	
	$mysql = 'SELECT * FROM `ha_events` ' .
			' WHERE `inout` = '.$log['inout']. ' AND `sourceID` ='.$log['sourceID'].' AND commandID = '.$log['commandID'].
			' AND  DATE_ADD(`mdate`,INTERVAL 10 SECOND) > "'.$gmttime.'"';

	if ($log['deviceID'] != Null) $mysql .= ' AND `deviceID` = '.$log['deviceID']; else $mysql .= ' AND `deviceID` IS NULL';

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
		$log['typeID'] = NULL;
		if ($log['deviceID'] != Null) {
			$mysql='SELECT `id`, `typeID` FROM `ha_mf_devices` WHERE `id` ='.$log['deviceID'];
			$resdevice=mysql_query($mysql);
			$rowdevice=mysql_fetch_array($resdevice);
			$log['typeID'] = $rowdevice['typeID'];
		}
		
		$log['time_date'] = $gmttime;
		$log['mdate'] = $gmttime;
		$log['milliseconds'] = udate("u");
		if (!array_key_exists("logLevel",$log )) $log['logLevel'] = 1;
		
		mysql_insert_assoc ("ha_events", $log);
	}
}
?>
