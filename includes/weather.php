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
            		UpdateWeatherCurrent($mydeviceID[$station], $xml->temp_c , $xml->relative_humidity );
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

function UpdateWeatherCurrent ($deviceID, $temp_c, $humidity) {

	
	$values['deviceID'] =  $deviceID;
	$values['mdate'] = date("Y-m-d H:i:s");
	$values['temperature_c'] = $temp_c;
	$values['humidity_r'] = $humidity;
	$sql = "SELECT * FROM `ha_weather_current`  WHERE deviceID=".  $deviceID ." order by mdate desc limit 1";
	if ($row = FetchRow($sql)) {
		$values['ttrend'] = setTrend($temp_c, $row['temperature_c']);
		$values['htrend'] = setTrend($humidity, $row['humidity_r']);
	}
	$values['source'] = $deviceID;
	mysql_insert_assoc ('ha_weather_current', $values);
	
}


function getWBUG($station) {

	$mydeviceID = Array ("HOOVR" => 196);
	
	$row = FetchRow("SELECT * FROM ha_mi_oauth20 where id ='WBUG'");
	//https://thepulseapi.earthnetworks.com/oauth20/token?grant_type=client_credentials&client_id=XtlIwGloXerWOgENDDkXp2qeGji0v3uX&client_secret=JSok7jX6boeSS8t7
	$url = "https://thepulseapi.earthnetworks.com/oauth20/token";
	//echo '<pre>';
	$params['grant_type'] = "client_credentials";
	$params['client_id'] = $row['clientID'];
	$params['client_secret'] = $row['secret'];
	//print_r($params);
	$get = restClient::get($url, $params,"","","application/json");
	$feedback['error'] = ($get->getresponsecode()==200 ? 0 : $get->getresponsecode());
	$feedback['message'] = trim($get->getresponse());
	//echo $feedback['message'].CRLF;
	$result = json_decode( $feedback['message'] );
	//print_r($result);

	unset($params);
	//https://thepulseapi.earthnetworks.com/data/observations/v3/current?providerid=3&stationid=HOOVR&units=metric&cultureinfo=en-en&verbose=true&access_token=setuk1wAqDXmUT3JY44QA1BQsxyj
	$url = "https://thepulseapi.earthnetworks.com/data/observations/v3/current";
	$params['providerid'] = 3;
	$params['stationid'] = $station;
	$params['units'] = "metric";
	$params['cultureinfo'] = "en-en";
	$params['verbose'] = "true" ;  
	$params['access_token'] = $result->{'OAuth20'}->{'access_token'}->{'token'};
	//print_r($params);
	$get = restClient::get($url, $params,"","","application/json");
	$feedback['error'] = ($get->getresponsecode()==200 ? 0 : $get->getresponsecode());
	$feedback['message'] = trim($get->getresponse());
	//echo $feedback['message'].CRLF;
	unset ($result);
	$result = json_decode( $feedback['message'] );
	//print_r($result);
	//echo CRLF;
	//echo "temp: ".$result->{'observation'}->{'temperature'}.CRLF;
	//echo "humi: ".$result->{'observation'}->{'humidity'}.CRLF;
	UpdateWeatherNow($mydeviceID[$station], $result->{'observation'}->{'temperature'} , $result->{'observation'}->{'humidity'});
	UpdateWeatherCurrent($mydeviceID[$station], $result->{'observation'}->{'temperature'} , $result->{'observation'}->{'humidity'} );
	$feedback['updatestatus'] = UpdateStatus($mydeviceID[$station], array( 'deviceID' => $mydeviceID[$station], 'status' => STATUS_ON, 'commandvalue' => $result->{'observation'}->{'temperature'}));
	UpdateLink ($mydeviceID[$station]);


	//$mydeviceID[$station]
	
//	$marketopen=strpos($response["market"]["m_open_close"],"Markets close in");
//			$obj = json_decode( $outputs );
//	$this->temp = $obj->{'temp'};						 // Present temp in deg F (or C depending on thermostat setting)
	return $feedback;
	
	}
?>