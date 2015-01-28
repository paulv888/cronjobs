<?php
//define( 'DEBUG_YAHOOWEATHER', TRUE );
if (!defined('DEBUG_YAHOOWEATHER')) define( 'DEBUG_YAHOOWEATHER', FALSE );
if (!defined('DEBUG_WBUG')) define( 'DEBUG_WBUG', FALSE );

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

function getYahooWeather($station) {

	$mydeviceID = Array ("USAL0594" => 196);
	//USAL0594
	
	$url = "https://query.yahooapis.com/v1/public/yql?q=select%20*%20from%20weather.forecast%20where%20location%3D%22".$station.
	"%22%20and%20u%3D%22c%22&format=json&diagnostics=true&callback=";
	$get = restClient::get($url);
	$response = file_get_contents($url);
	if (DEBUG_YAHOOWEATHER) echo "<pre>";
	//if (DEBUG_YAHOOWEATHER) echo "response: ".$response;

	$result = json_decode( $response );
	//if (DEBUG_YAHOOWEATHER) print_r($result);
	$result = $result->{'query'}->{'results'}->{'channel'};
	if (DEBUG_YAHOOWEATHER) print_r($result);
	UpdateWeatherNow($mydeviceID[$station], $result->{'item'}->{'condition'}->{'temp'} , $result->{'atmosphere'}->{'humidity'});
	UpdateWeatherCurrent($mydeviceID[$station], $result->{'item'}->{'condition'}->{'temp'} , $result->{'atmosphere'}->{'humidity'} );
	$feedback['updatestatus'] = UpdateStatus($mydeviceID[$station], array( 'deviceID' => $mydeviceID[$station], 'status' => STATUS_ON, 'commandvalue' => $result->{'item'}->{'condition'}->{'temp'}));
//	UpdateLink ($mydeviceID[$station]);

	$array['deviceID'] = $mydeviceID[$station];
	$array['mdate'] = date("Y-m-d H:i:s",strtotime( $result->{'item'}->{'pubDate'}));
	$array['temp'] = $result->{'item'}->{'condition'}->{'temp'};
	$array['humidity'] = $result->{'atmosphere'}->{'humidity'};
	$array['pressure'] = $result->{'atmosphere'}->{'pressure'};
	$array['rising'] = $result->{'atmosphere'}->{'rising'};
	$array['visibility'] = $result->{'atmosphere'}->{'visibility'};
	$array['chill'] = $result->{'wind'}->{'chill'};
	$wd = $result->{'wind'}->{'direction'};
	if($wd>=348.75&&$wd<=360) $wdt="N";
	if($wd>=0&&$wd<11.25) $wdt="N";
	if($wd>=11.25&&$wd<33.75) $wdt="NNE";
	if($wd>=33.75&&$wd<56.25) $wdt="NE";
	if($wd>=56.25&&$wd<78.75) $wdt="ENE";
	if($wd>=78.75&&$wd<101.25) $wdt="E";
	if($wd>=101.25&&$wd<123.75) $wdt="ESE";
	if($wd>=123.75&&$wd<146.25) $wdt="SE";
	if($wd>=146.25&&$wd<168.75) $wdt="SSE";
	if($wd>=168.75&&$wd<191.25) $wdt="S";
	if($wd>=191.25&&$wd<213.75) $wdt="SSW";
	if($wd>=213.75&&$wd<236.25) $wdt="SW";
	if($wd>=236.25&&$wd<258.75) $wdt="WSW";
	if($wd>=258.75&&$wd<281.25) $wdt="W";
	if($wd>=281.25&&$wd<303.75) $wdt="WNW";
	if($wd>=303.75&&$wd<326.25) $wdt="NW";
	if($wd>=326.25&&$wd<348.75) $wdt="NNW";
	$array['direction'] = $wdt;
	$array['speed'] = $result->{'wind'}->{'speed'};
	$array['code'] = $result->{'item'}->{'condition'}->{'code'};
	$array['text'] = $result->{'item'}->{'condition'}->{'text'};
	$array['typeID'] = DEV_TYPE_TEMP_HUM;
	// Get night or day
	$tpb = time();
	$tsr = strtotime($result->{'astronomy'}->{'sunrise'});
	$tss = strtotime($result->{'astronomy'}->{'sunset'});
	if ($tpb>$tsr && $tpb<$tss) { $daynight = 'd'; } else { $daynight = 'n'; }
	$array['link1'] = "http://l.yimg.com/a/i/us/nws/weather/gr/".$result->{'item'}->{'condition'}->{'code'}.$daynight.'.png';
	if ($daynight == "d") {
		$array['class'] = "w-day";
	} else {
		$array['class'] = "w-night";
	}

	PDOupdate("ha_weather_extended", $array, "deviceID");
	
	unset($array);
	$i = 0;
	foreach ($result->{'item'}->{'forecast'} as $forecast) {
		//print_r($forecast);
		$array['id'] = $i;
		$array['deviceID'] = $mydeviceID[$station];
		$array['mdate'] = date("Y-m-d H:i:s",strtotime($forecast->{'date'}));
		$array['day'] = $forecast->{'day'};
		if ($i == 0) $array['day'] = "Tdy";
		if ($i == 1) $array['day'] = "Tom";
		$array['low'] = $forecast->{'low'};
		$array['high'] = $forecast->{'high'};
		$array['text'] = $forecast->{'text'};
		$array['link1'] = "http://l.yimg.com/a/i/us/nws/weather/gr/".$forecast->{'code'}."s.png";
		PDOupdate("ha_weather_forecast", $array, "id");
//		PDOinsert("ha_weather_forecast", $array);
		$i++;
	}

	if (DEBUG_YAHOOWEATHER) echo "</pre>";
	
	return $feedback;
	
}
	

	
function getWBUG($station) {

	$mydeviceID = Array ("HOOVR" => 196);
	
	$row = FetchRow("SELECT * FROM ha_mi_oauth20 where id ='WBUG'");
	//https://thepulseapi.earthnetworks.com/oauth20/token?grant_type=client_credentials&client_id=XtlIwGloXerWOgENDDkXp2qeGji0v3uX&client_secret=JSok7jX6boeSS8t7
	//{"OAuth20":{"access_token":{"token":"efbc8548b81843a5a81ead3dfb3d","refresh_token":"efbc8548b81843a5a81ead3dfb3d","token_type":"bearer","expires_in":86399}}}
	$burl = "https://thepulseapi.earthnetworks.com/oauth20/token";
	if (DEBUG_WBUG) echo '<pre>';
	$params['grant_type'] = "client_credentials";
	$params['client_id'] = $row['clientID'];
	$params['client_secret'] = $row['secret'];
	if (DEBUG_WBUG) print_r($params);
	
	
	$url = $burl."?grant_type=client_credentials&client_id=".$row['clientID']."&client_secret=".$row['secret'];
	//$get = restClient::get($url);
	$response = file_get_contents($url);
	if (DEBUG_WBUG) echo "response: ".$response;

	
//	$feedback['error'] = ($get->getresponsecode()==200 ? 0 : $get->getresponsecode());
//	$feedback['message'] = trim($get->getresponse());
//	if (DEBUG_WBUG) echo "feedback: ";
//	if (DEBUG_WBUG) print_r($feedback);
	$result = json_decode( $response );
	if (DEBUG_WBUG) print_r($result);

	unset($params);
	//https://thepulseapi.earthnetworks.com/data/observations/v3/current?providerid=3&stationid=HOOVR&units=metric&cultureinfo=en-en&verbose=true&access_token=setuk1wAqDXmUT3JY44QA1BQsxyj
	$burl = "https://thepulseapi.earthnetworks.com/data/observations/v3/current";
	$params['providerid'] = 3;
	$params['stationid'] = $station;
	$params['units'] = "metric";
	$params['cultureinfo'] = "en-en";
	$params['verbose'] = "true" ;  
	$params['access_token'] = $result->{'OAuth20'}->{'access_token'}->{'token'};
	$url = $burl."?providerid=3&stationid=".$params['stationid']."&units=metric&cultureinfo=en-en&verbose=true&access_token=".$params['access_token'];
	//"https://thepulseapi.earthnetworks.com/data/observations/v3/current?providerid=3&stationid=HOOVR&units=metric&cultureinfo=en-en&verbose=true&access_token=2988eb34e2f640d9a98e20b36486"
	$response = file_get_contents($url);
	if (DEBUG_WBUG) echo "response: ".$response;
	
	//if (DEBUG_WBUG) print_r($params);
	//$get = restClient::get($url, $params);
	//$feedback['error'] = ($get->getresponsecode()==200 ? 0 : $get->getresponsecode());
	//$feedback['message'] = trim($get->getresponse());
	//if (DEBUG_WBUG) echo $feedback['message'].CRLF;
	
	unset ($result);
	$result = json_decode( $response );
	if (DEBUG_WBUG) print_r($result);
	if (DEBUG_WBUG) echo CRLF;
	if (DEBUG_WBUG) echo "temp: ".$result->{'observation'}->{'temperature'}.CRLF;
	if (DEBUG_WBUG) echo "humi: ".$result->{'observation'}->{'humidity'}.CRLF;
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