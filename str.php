<?php
require_once 'includes.php';

define("MY_DEVICE_ID", 98);

/*
 Process can execute following commands
 1) Store data to HA_Database

 Called from
 1) ARD-Bridge

 Expecting
 1) Type
 2) Fields matching the above query

 */
//while (true) {
$data = file_get_contents("php://input");
	//print_r($data);
	if (!($data=="")) { //import_event
	
		$xmlheader="<?xml version='1.0' standalone='yes'?>";
		$idata = new SimpleXMLElement($xmlheader.$data);
		$extdata = "";
		$i = 1;
	
		echo $idata->asXML();
		
		Switch ($idata->CID) {
			Case COMMAND_STREAM_DATA:
				captureStream($idata);
				break;
			default:
				$extdata = "";
				if (isset($idata->SNS)) {
					$i=0;
					foreach ($idata->SNS->V AS $sensor) {
						$extdata .= "S$i:".$sensor." ";
						$i++;
					}
				}
				logEvent(Array ('inout' => COMMAND_IO_RECV, 'callerID' => $idata->DID, 'deviceID' => $idata->DID, 'commandID' => $idata->CID, 'data' => $idata->RSL, 'extdata' => $extdata, 'message' => &$idata));

				break;
		}
	}
//}

function captureStream(&$idata) {
	$time = date("Y-m-d H:i:s");

	$mysql = 'SELECT * FROM `ha_record_data_streams` '.
			' INNER JOIN `ha_record_current_stream` ON `ha_record_data_streams`.`id`=`ha_record_current_stream`.`current_stream`';

	$rescurrentstream=mysql_query($mysql);
	$rowcurrentstream=mysql_fetch_array($rescurrentstream);
	
	if ($rowcurrentstream['deviceID'] == $idata->DID) {
			$mysql= 'INSERT INTO `ha_record_range_data` (
									  `stream_id`, 
									  `deviceID`, 
									  `commandID`, 
									  `mtime`, 
									  `T1`, 
									  `T2`, 
									  `T3`, 
									  `T4`, 
									  `T5`, 
									  `T6`, 
									  `T7`, 
									  `S1`, 
									  `S2`, 
									  `S3`, 
									  `S4`, 
									  `S5`, 
									  `S6`) 
									  VALUES ( ' .
									''.$rowcurrentstream['current_stream'].','.
									''.$idata->DID.','.
									''.$idata->CID.','.
									'"'.$time.'"';
		
			foreach ($idata->SNS->V AS $sensor) {
				$mysql .= ",".$sensor;
			}
			$mysql .= ")";
			if (!mysql_query($mysql)) mySqlError($mysql);
	} else {
		echo "Unrecognized deviceID";
	}
}


function doEventStatus(&$idata) {

	//
	//   Update Link
	//
/*	if ($rowdevice['monitortypeID'] == MONITOR_LINK || $rowdevice['monitortypeID'] == MONITOR_LINK_STATUS) {
		$mysql = "Update `ha_vw_monitor_combined` Set " .
    			  " `mdate` = '" . $time . "'" .
    			  " Where(`deviceID` ='" . $idata->DID . "')";
		if (!mysql_query($mysql)) mySqlError($mysql);
	}*/
	
	
	//
	// 	Update Status
	//
	//define("COMMAND_RESULT_ERROR", 2);
	//define("COMMAND_STATUSON", 8);
	//define("COMMAND_STATUSOFF", 6);
	//define("COMMAND_CALIBRATE_ERROR", 127);
	//define("COMMAND_RF_TIMEOUT", 128);
	//define("COMMAND_RF_SEND_FAILED", 129);
	//define("COMMAND_SENSOR_ERROR", 130);

/*	if ($rowdevice['monitortypeID'] == MONITOR_STATUS || $rowdevice['monitortypeID'] == MONITOR_LINK_STATUS) {
	// needs to read monitor commands.
		Switch ($idata->CID) {
			Case COMMAND_STATUSON:
			Case COMMAND_ON:
				$mStatus= COMMAND_ON;
				break;
			Case COMMAND_STATUSOFF:
			Case COMMAND_OFF:
				$mStatus= COMMAND_OFF;
				break;
			Case COMMAND_RESULT_ERROR:
				$mStatus= $idata->RSL;
				break;
		}
		$mysql= "Update `ha_vw_monitor_combined` Set " .
				" `statusDate` = '" . $time . "', `status` ='" . $mStatus . "'".
				" Where(`deviceID` ='" .$idata->DID. "')";
		if (!mysql_query($mysql)) mySqlError($mysql);
	}
*/
}
?>