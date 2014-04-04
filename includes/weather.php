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
            		UpdateWeatherNow($mydeviceID[$station], $xml->temp_c , $xml->relative_humidity  , 0);
            		UpdateWeather($xml, $values, $mydeviceID[$station] );
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
		echo "<pre>";
		print_r ($values);
		echo "</pre>";
}

function UpdateWeather ($xml, &$values, $deviceID) {

	
	$values['deviceID'] =  $deviceID;
	$values['mdate'] = gmdate("Y-m-d H:i:s");
	$values['weather'] = (string)$xml->weather;
	$values['temp_c'] = (string)$xml->temp_c;
	$values['humidity_r'] = (string)$xml->relative_humidity;
	$values['wind_string'] = (string)$xml->wind_string;
	$values['wind_dir'] = (string)$xml->wind_dir;
	$values['wind_degrees'] = (string)$xml->wind_degrees;
	$values['wind_mph'] = (string)$xml->wind_mph;
	$values['pressure_mb'] = (string)$xml->pressure_mb;
	$values['dewpoint_c'] = (string)$xml->dewpoint_c;
	$values['visibility_mi'] = (string)$xml->visibility_mi;
	$values['icon_url_name'] = (string)$xml->icon_url_name;
	$sql = "SELECT * FROM `ha_weather_current`  WHERE deviceID=".  $deviceID ." order by mdate desc limit 1";
	if ($row = FetchRow($sql)) {
		$values['ttrend'] = setTrend((string)$xml->temp_c, $row['temperature_c']);
		$values['htrend'] = setTrend((string)$xml->relative_humidity, $row['humidity_r']);
	}
	$values['source'] = SOURCE_WEATHER_GOV;
	
}
?>