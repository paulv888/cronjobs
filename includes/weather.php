<?php

function loadWeather($station) {
	
		$mydeviceID = Array ("KBHM" => 65 , "KEET" => 66);
		$retry = 5;
        $success = False;

        while ($retry > 0 && !$success) {
            try {

            	$url= WEATHER_URL.$station.".xml";
            	$get = restClient::get($url);
            	$feedback = ($get->getresponsecode()==200 ? TRUE : FALSE);
            	if ($feedback) {
            		$xml = new SimpleXMLElement($get->getresponse());
            		UpdateWeatherNow($mydeviceID[$station], $xml->temp_c , $xml->relative_humidity);
            		UpdateWeatherCurrent($xml, $values, $mydeviceID[$station] );
            		UpdateLink ($mydeviceID[$station]);
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
}

function UpdateWeatherCurrent ($xml, &$values, $deviceID) {

	
	$values['deviceID'] =  $deviceID;
	$values['mdate'] = gmdate("Y-m-d H:i:s");
	$values['temperature_c'] = (string)$xml->temp_c;
	$values['humidity_r'] = (string)$xml->relative_humidity;
	$sql = "SELECT * FROM `ha_weather_current`  WHERE deviceID=".  $deviceID ." order by mdate desc limit 1";
	if ($row = FetchRow($sql)) {
		$values['ttrend'] = setTrend((string)$xml->temp_c, $row['temperature_c']);
		$values['htrend'] = setTrend((string)$xml->relative_humidity, $row['humidity_r']);
	}
	$values['source'] = SOURCE_WEATHER_GOV;
	mysql_insert_assoc ('ha_weather_current', $values);
	
}
?>