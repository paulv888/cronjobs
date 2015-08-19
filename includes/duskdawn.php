<?php
define( 'DEBUG_DUSKDAWN', TRUE );
if (!defined('DEBUG_DUSKDAWN')) define( 'DEBUG_DUSKDAWN', FALSE );

function getDuskDawn($station) {

	$mydeviceID = DEVICE_DARK_OUTSIDE;
	ini_set('max_execution_time',30);

	$mydeviceID = array("USAL0594" => 196);
	//USAL0594

	$url = "https://query.yahooapis.com/v1/public/yql?q=select%20*%20from%20weather.forecast%20where%20location%3D%22".$station.
	"%22%20and%20u%3D%22c%22&format=json&diagnostics=true&callback=";
	$get = restClient::get($url);
//	$response = file_get_contents($url);
	if (DEBUG_DUSKDAWN) echo "<pre>";
	//if (DEBUG_YAHOOWEATHER) echo "response: ".$response;
	$feedback['error'] = ($get->getresponsecode()==200 ? 0 : $get->getresponsecode());
    if (!$feedback['error']) {
		$result = json_decode($get->getresponse());
		$feedback['message'] =  json_encode(json_decode($get->getresponse(), true));
		//if (DEBUG_YAHOOWEATHER) print_r($result);
		if (DEBUG_DUSKDAWN) print_r($result);
		$result = $result->{'query'}->{'results'}->{'channel'};

		$tsr = date("H:i:s", strtotime($result->{'astronomy'}->{'sunrise'}));
		$tss = date("H:i:s", strtotime($result->{'astronomy'}->{'sunset'}));

		PDOupdate("ha_mf_device_extra", Array('dawn' => $tsr, 'dusk' => $tss), array( 'deviceID' => DEVICE_DARK_OUTSIDE));
		$feedback['updatestatus'] = UpdateStatus(array('callerID' => MY_DEVICE_ID, 'deviceID' => DEVICE_DARK_OUTSIDE, 'status' => STATUS_ON));
   		UpdateLink (array('callerID' => MY_DEVICE_ID, 'deviceID' => DEVICE_DARK_OUTSIDE));
	}

	if (DEBUG_DUSKDAWN) echo "</pre>";
	return $feedback;
}

function getDuskDawnEarthTools() {

	$mydeviceID = DEVICE_DARK_OUTSIDE;
	$rowconf = FetchRow("SELECT * FROM ha_configuration WHERE id=1");
	$retry = 5;
        $success = False;

        while ($retry > 0 && !$success) {
            try {

//            	$url= 'http://www.earthtools.org/sun/33.371241/-86.756570/16/1/-6/0';
            	$url= 'http://www.earthtools.org/sun/'.$rowconf['long'].'/'.$rowconf['lat'].'/'.date("j").'/'.date("m").'/'.str_replace("0","",date('O')).'/'.date('I');
            	$get = restClient::get($url);
                $feedback['error'] = ($get->getresponsecode()==200 ? 0 : $get->getresponse());
                $feedback['message'] = trim($get->getresponse());

//echo "<pre>";
//echo $url.CRLF;
//echo htmlspecialchars($get->getresponsecode()).CRLF;
//echo trim(htmlspecialchars($get->getresponse())).CRLF;
            	if (!$feedback['error']) {
              		$xml = new SimpleXMLElement(trim($get->getresponse()));
					if ($xml->date->dst == "1") {
						$dawn = date('H:i:s', strtotime(date('H:i:s',strtotime($xml->morning->twilight->civil)). ' -1 hour')); 
						$dusk = date('H:i:s', strtotime(date('H:i:s',strtotime($xml->evening->twilight->civil)). ' -1 hour')); 
					} else {
						$dawn = $xml->morning->twilight->civil;
						$dusk = $xml->evening->twilight->civil;
					}
//print_r($xml);
					$mySql = 'UPDATE `ha_mf_device_extra` SET `dawn` = "'.$dawn. '", `dusk` ="'.$dusk.'" WHERE deviceID = {DEVICE_DARK_OUTSIDE}'; 
					if (RunQuery($mySql)) {
            			UpdateLink (array( 'callerID' => MY_DEVICE_ID, 'deviceID' => DEVICE_DARK_OUTSIDE));
					}
					$success = true; 
            	}
		}
            catch (Exception $e) {
                //Error trapping
                //My.Application.Log.WriteException(exc, TraceEventType.Error, "Error reading data from" & My.Settings.WeatherUrl & MyStation & ".xml", 301)
                 echo 'Caught exception: ',  $e->getMessage(), "\n";
			} 
			$retry = $retry - 1;
		}
	return ($feedback);
//	return ($success ? true : false);
//echo "</pre>";
}

function GetDawn() {
	$devextraow = FetchRow("SELECT dawn FROM ha_mf_device_extra  WHERE deviceID = ".DEVICE_DARK_OUTSIDE);
	return $devextraow['dawn'];
}

function GetDusk() {
	$devextraow = FetchRow("SELECT dusk FROM ha_mf_device_extra  WHERE deviceID = ".DEVICE_DARK_OUTSIDE);
	return $devextraow['dusk'];
}

?>
