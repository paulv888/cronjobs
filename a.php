<?php
require_once 'includes.php';

/*
	 Process can execute following commands
	 1) Store data to HA_Database

	 Called from
	 1) ARD-Coop

	 Expecting
	 1) Type
	 2) Fields matching the above query

 */
 

$sdata = file_get_contents("php://input");
/*
$file = 'tmp1.txt';
$current = file_get_contents($file);
$current .= $sdata."\n";
file_put_contents($file, $current);
*/

if (!($sdata=="")) { 					//import_event
	$rcv_message = json_decode($sdata, $assoc = TRUE);
//print_r($rcv_message);
	$message['deviceID'] = $rcv_message['Device'];
	$message['commandID'] = $rcv_message['Command'];
	$message['inout'] = $rcv_message['InOut'];
	if (array_key_exists('Value', $rcv_message)) {
		$message['data'] = $rcv_message['Value'];
	} else {
		$message['data'] = $rcv_message['Status'];
	}
	$message['typeID'] = getDeviceType($message['deviceID']);
	$extdata = (array_key_exists('ExtData', $rcv_message) ? $rcv_message['ExtData'] : $extdata = null);
	$message['extdata'] = $extdata;
	$message['message'] = $sdata;
	$message['callerID'] = $message['deviceID'];
	logEvent($message);
//print_r($message);
	if ($message['inout'] == COMMAND_IO_RECV) {
                if ($message['typeID'] == DEV_TYPE_TEMP_HUM) {
                        if (array_key_exists('Value', $rcv_message)) $t = $rcv_message['Value'];
                        $h = NULL;
                        if (array_key_exists('ExtData', $rcv_message)) {
                                if (array_key_exists('T', $rcv_message['ExtData'])) $t = $rcv_message['ExtData']['T'];
                                $h = (array_key_exists('H', $rcv_message['ExtData']) ? $rcv_message['ExtData']['H'] : $h = NULL);
                        }
                        if (isset($t)) {
                                UpdateWeatherNow($message['deviceID'], $t, $h );
                                UpdateWeatherCurrent($message['deviceID'], $t, $h );
                        }
                }
     		if ($message['typeID'] == DEV_TYPE_ARD_HEAT || $message['typeID'] == DEV_TYPE_ARD_COOL) {
//Add running or not to HVAC cylcles and calc hours/day
//{"Device" : "202" , "Command" : "285" , "Status" : "0" , "Value" : "438" , "InOut" : "1" , "ExtData" : {"V":"438","R":"0","S":"500","T":"10"}}
//{"Device" : "208" , "Command" : "285" , "Status" : "1" , "Value" : "27" , "InOut" : "1" , "ExtData" : {"V":"27","R":"1","S":"24","T":"1"}}
			if (array_key_exists('Value', $rcv_message)) $t = $rcv_message['Value'];
			$h = NULL;
			if (array_key_exists('ExtData', $rcv_message)) {
				if (array_key_exists('V', $rcv_message['ExtData'])) $t = $rcv_message['ExtData']['V'];
				$s = (array_key_exists('S', $rcv_message['ExtData']) ? $rcv_message['ExtData']['S'] : $s = NULL);
				$h = (array_key_exists('H', $rcv_message['ExtData']) ? $rcv_message['ExtData']['H'] : $h = NULL);
			}
			if (isset($t)) {
			 	UpdateWeatherNow($message['deviceID'], $t, $h , $s);
				UpdateWeatherCurrent($message['deviceID'], $t, $h, $s);
			}
		}
		UpdateStatus($message['callerID'], array ( 'deviceID' => $message['deviceID'] , 'commandID' => $message['commandID'], 'status' => $rcv_message['Status'], 'errormessage' => implode(" - ", $extdata)));
		UpdateLink ($message['deviceID'], LINK_TIMEDOUT, $message['callerID'], $message['commandID']);
	}
}



function getDeviceType($deviceID){

	$mysql='SELECT `id`, `typeID` FROM `ha_mf_devices` WHERE `id` ="'.$deviceID.'"';
	if ($rowdevice = FetchRow($mysql)) {
		return $rowdevice['typeID'];
	}
	
	return false ;
	
}
?>
