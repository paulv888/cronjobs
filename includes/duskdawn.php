<?php
//define( 'DEBUG_DUSKDAWN', TRUE );
if (!defined('DEBUG_DUSKDAWN')) define( 'DEBUG_DUSKDAWN', FALSE );

function getDuskDawn() {
	
	$mydeviceID = DEVICE_DARK_OUTSIDE;
	$rowconf = FetchRow("SELECT * FROM ha_configuration WHERE id=1");
	$retry = 5;
        $success = False;

        while ($retry > 0 && !$success) {
            try {

//            	$url= 'http://www.earthtools.org/sun/33.371241/-86.756570/16/1/-6/0';
            	$url= 'http://www.earthtools.org/sun/'.$rowconf['long'].'/'.$rowconf['lat'].'/'.date("j").'/'.date("m").'/'.str_replace("0","",date('O')).'/'.date('I');
            	$get = restClient::get($url);
            	$feedback = ($get->getresponsecode()==200 ? TRUE : FALSE);
//echo "<pre>";
//echo $url.CRLF;
//echo htmlspecialchars($get->getresponsecode()).CRLF;
//echo trim(htmlspecialchars($get->getresponse())).CRLF;
            	if ($feedback) {
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
            			UpdateLink (DEVICE_DARK_OUTSIDE);
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
	return ($success ? true : false);
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
