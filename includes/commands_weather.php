<?php
// define( 'DEBUG_WEATHER', TRUE );
if (!defined('DEBUG_WEATHER')) define( 'DEBUG_WEATHER', FALSE );
if (!defined('DEBUG_WBUG')) define( 'DEBUG_WBUG', FALSE );

define('IMAGE_CACHE',"/images/yahoo/");
define('FRONT_DIR',"/images/yahoo/");

// 
//  Part of commandfactory
//
function getWeather($params) {

	$deviceID = $params['deviceID'];

	$feedback['Name'] = 'getWeather';
	$feedback['commandstr'] = "http://api.wunderground.com/api/".WU_API."/alerts/forecast/astronomy/conditions/q/".MY_ZIP.".json";
	$feedback['result'] = array();
	// $args = array();
	// $args["q"] = 'select * from weather.forecast where woeid in (12773052) and u="c"';
	// $args["diagnostics"] = "true";
	// $args["debug"] = "true";
	// $args["format"] = "json";

	$get = RestClient::get($feedback['commandstr'],null,null,30);

	if (DEBUG_WEATHER) echo "<pre>";
	$error = false;
	if ($get->getresponsecode()!= 200) $error=true;
	if (!$error) {
		$device['previous_properties'] = getDeviceProperties(Array('deviceID' => $deviceID));
		$result = json_decode($get->getresponse());
		if (DEBUG_WEATHER) print_r($result);
		$feedback['result']['WU'] =  json_encode(json_decode($get->getresponse(), true),JSON_UNESCAPED_SLASHES);
		if (!isset($result->{'current_observation'})) {
			$error = true;
		} else {
			$co = $result->{'current_observation'};
			$properties['Temperature']['value'] = $co->{'temp_c'};
			$properties['Humidity']['value'] =  $co->{'relative_humidity'};
			$properties['Status']['value'] = STATUS_ON;
			$device['properties'] = $properties;
			$feedback['result']['updateDeviceProperties'] = updateDeviceProperties(array('callerID' => $params['callerID'], 'deviceID' => $deviceID, 'device' => $device));

			$array['deviceID'] = $deviceID;
			$array['mdate'] = date("Y-m-d H:i:s",strtotime( $co->{'observation_time_rfc822'}));
			$array['temp'] = $co->{'temp_c'};
			$array['humidity'] = $co->{'relative_humidity'};
			$array['pressure'] = $co->{'pressure_mb'};
			$array['rising'] = $co->{'pressure_trend'};
			$array['visibility'] = $co->{'visibility_km'};
			$array['chill'] = $co->{'windchill_c'};
			$array['direction'] = $co->{'wind_dir'};;
			$array['speed'] = $co->{'wind_kph'};
			$array['text'] = $co->{'weather'};
			$array['typeID'] = DEV_TYPE_TEMP_HUMIDITY;
			// Get night or day
			$tpb = time();
			$tsr = strtotime($result->{'sun_phase'}->{'sunrise'}->{'hour'}.":".$result->{'sun_phase'}->{'sunrise'}->{'minute'});
//			$tss = strtotime(preg_replace("/:(\d) /",":0$1 ",$result->{'sun_phase'}->{'sunset'}));
			$tss = strtotime($result->{'sun_phase'}->{'sunset'}->{'hour'}.":".$result->{'sun_phase'}->{'sunset'}->{'minute'});
			if ($tpb>$tsr && $tpb<$tss) { $daynight = ''; } else { $daynight = 'nt_'; }
			// http://icons.wxug.com/i/c/c/partlycloudy.gif
			// http://icons.wxug.com/i/c/c/nt_partlycloudy.gif
			$image = $daynight.$co->{'icon'}.'.svg';
			cache_image(IMAGE_CACHE.$image, 'http://icons.wxug.com/i/c/v4/'.$image);
			$array['link1'] = FRONT_DIR.$image;

			$array['class'] = "";
			if ($daynight == "") {
				$array['class'] = "w-day";
			} else {
				$array['class'] = "w-night";
			}
			// $row = FetchRow("SELECT `severity` FROM `ha_weather_codes` WHERE `code` =".$co->{'item'}->{'condition'}->{'code'});
			// if ($row['severity'] == SEVERITY_DANGER) {
				// $array['class'] = SEVERITY_DANGER_CLASS;
			// }
			// if ($row['severity'] == SEVERITY_WARNING) {
				// $array['class'] = SEVERITY_WARNING_CLASS;
			// }
			PDOupdate("ha_weather_extended", $array, array( 'deviceID' => $deviceID));

			unset($array);
			$i = 0;
			foreach ($result->{'forecast'}->{'simpleforecast'}->{'forecastday'} as $forecast) {
				// print_r($forecast);
				$array['deviceID'] = $deviceID;
				$array['mdate'] = date("Y-m-d H:i:s",$forecast->{'date'}->{'epoch'});
				$array['day'] = $forecast->{'date'}->{'weekday_short'};
				//if ($i == 0) $array['day'] = $array['day'];
				//if ($i == 1) $array['day'] = "Tomorrow";
				$array['low'] = $forecast->{'low'}->{'celsius'};
				$array['high'] = $forecast->{'high'}->{'celsius'};
				$array['text'] = $forecast->{'conditions'};
				//$array['code'] = $forecast->{'code'};
				$image = $forecast->{'icon'}.'.svg';
				cache_image(IMAGE_CACHE.$image, 'http://icons.wxug.com/i/c/v4/'.$image);
				$array['link1'] = FRONT_DIR.$image;
				$array['rain'] = $forecast->{'pop'};
				PDOupdate("ha_weather_forecast", $array, array('id' => $i));
	//			PDOinsert("ha_weather_forecast", $array);
				$i++;
			}
			
	//$url = "http://api.wunderground.com/api/275a74de4762b2a3/alerts/q/TX/Houston.json";
	// $url = "http://api.wunderground.com/api/275a74de4762b2a3/alerts/q/PA/Philadelphia.json";
	// $get = RestClient::get($url,null,null,30);
		// $result = json_decode($get->getresponse());
			if (DEBUG_WEATHER) print_r($result);

			unset($array);
			foreach ($result->{'alerts'} as $alert) {
				// print_r($forecast);
				$array['deviceID'] = $deviceID;
				$array['code'] = $alert->{'type'};
				$array['mdate'] = date("Y-m-d H:i:s",$alert->{'date_epoch'});
				$array['expires'] = date("Y-m-d H:i:s",$alert->{'expires_epoch'});
				$array['description'] = $alert->{'description'};
				$array['message'] = $alert->{'message'};
				$row = FetchRow("SELECT `severity` FROM `ha_weather_codes` WHERE `code` = '".$alert->{'type'}."'");
				$array['class'] = "";
				if ($row['severity'] == SEVERITY_DANGER) {
					$array['class'] = SEVERITY_DANGER_CLASS;
				} elseif ($row['severity'] == SEVERITY_WARNING) {
					$array['class'] = SEVERITY_WARNING_CLASS;
				} elseif ($row['severity'] == SEVERITY_INFO) {
					$array['class'] = SEVERITY_INFO_CLASS;
				}
				PDOupsert("ha_weather_alerts", $array, array('expires' => $array['expires'], 'code' => $array['code']));
			}
			
			if (DEBUG_WEATHER) print_r($result->{'sun_phase'});
			if (isset($result->{'sun_phase'})) {
				$dark_params['caller'] = $params['caller'];
				$dark_params['deviceID'] = DEVICE_DARK_OUTSIDE;
				$dark_params['device']['id'] =  DEVICE_DARK_OUTSIDE;
				$dark_params['device']['previous_properties'] = getDeviceProperties(Array('deviceID' => DEVICE_DARK_OUTSIDE));
				$dark_properties['Astronomy Sunrise']['value'] = date("H:i", strtotime(preg_replace("/:(\d) /",":0$1 ",$result->{'sun_phase'}->{'sunrise'}->{'hour'}.':'.$result->{'sun_phase'}->{'sunrise'}->{'minute'})));
				$dark_properties['Astronomy Sunset']['value'] = date("H:i", strtotime(preg_replace("/:(\d) /",":0$1 ",$result->{'sun_phase'}->{'sunset'}->{'hour'}.':'.$result->{'sun_phase'}->{'sunset'}->{'minute'})));
				$dark_properties['Link']['value'] = LINK_UP;
				$dark_params['device']['properties'] = $dark_properties;
				if (DEBUG_WEATHER) print_r($dark_params);
				$feedback['result']['updateDeviceProperties_Dark'] = updateDeviceProperties($dark_params);
			}
		}
	}
	if ($error) {
		$feedback['error'] = $get->getresponsecode();
		$properties['Status']['value'] = STATUS_ERROR;
		$device['properties'] = $properties;
		$feedback['result']['updateDeviceProperties'] = updateDeviceProperties(array('callerID' => $params['callerID'], 'deviceID' => $deviceID, 'device' => $device));
	}

	
	if (DEBUG_WEATHER) echo "</pre>";
	return $feedback;
}

function getYahooWeather($params) {

	$station = $params['commandvalue'];
	$deviceID = $params['deviceID'];

	$row = FetchRow("SELECT * FROM ha_mi_oauth20 where id ='YAHOO'");
	$credentials['method'] = $row['method'];
	$credentials['client_id'] = $row['clientID'];
	$credentials['secret'] = $row['secret'];

	$url = "https://query.yahooapis.com/v1/yql";
	$args = array();
	$args["q"] = 'select * from weather.forecast where woeid in (12773052) and u="c"';
	// $args["diagnostics"] = "true";
	// $args["debug"] = "true";
	$args["format"] = "json";

	$get = RestClient::get($url,$args,$credentials,30);

	if (DEBUG_WEATHER) echo "<pre>";
	//if (DEBUG_WEATHER) echo "response: ".$response;
	$error = false;
	if ($get->getresponsecode()!=200) $error=true;
	if (!$error) {
		$device['previous_properties'] = getDeviceProperties(Array('deviceID' => $deviceID));
		$result = json_decode($get->getresponse());
		if (DEBUG_WEATHER) print_r($result);
		$feedback['result'] =  json_encode(json_decode($get->getresponse(), true),JSON_UNESCAPED_SLASHES);
		if (!isset($result->{'query'}->{'results'})) {
			$error = true;
		} else {
			$result = $result->{'query'}->{'results'}->{'channel'};
			$properties['Temperature']['value'] = $result->{'item'}->{'condition'}->{'temp'};
		$properties['Humidity']['value'] =  $result->{'atmosphere'}->{'humidity'};
		$properties['Status']['value'] = STATUS_ON;
		$device['properties'] = $properties;
		$feedback['updateDeviceProperties'] = updateDeviceProperties(array('callerID' => $params['callerID'], 'deviceID' => $deviceID, 'device' => $device));

		$array['deviceID'] = $deviceID;
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
		$array['typeID'] = DEV_TYPE_TEMP_HUMIDITY;
		// Get night or day
		$tpb = time();
		$tsr = strtotime(preg_replace("/:(\d) /",":0$1 ",$result->{'astronomy'}->{'sunrise'}));
		$tss = strtotime(preg_replace("/:(\d) /",":0$1 ",$result->{'astronomy'}->{'sunset'}));
		if ($tpb>$tsr && $tpb<$tss) { $daynight = 'd'; } else { $daynight = 'n'; }
		$image = $result->{'item'}->{'condition'}->{'code'}.$daynight.'.png';
		cache_image(IMAGE_CACHE.$image, 'http://l.yimg.com/a/i/us/nws/weather/gr/'.$image);
		$array['link1'] = FRONT_DIR.$image;

		$array['class'] = "";
		if ($daynight == "d") {
			$array['class'] = "w-day";
		} else {
			$array['class'] = "w-night";
		}
		$row = FetchRow("SELECT `severity` FROM `ha_weather_codes` WHERE `code` =".$result->{'item'}->{'condition'}->{'code'});
		if ($row['severity'] == SEVERITY_DANGER) {
			$array['class'] = SEVERITY_DANGER_CLASS;
		}
		if ($row['severity'] == SEVERITY_WARNING) {
			$array['class'] = SEVERITY_WARNING_CLASS;
		}
		PDOupdate("ha_weather_extended", $array, array( 'deviceID' => $deviceID));

		unset($array);
		$i = 0;
		foreach ($result->{'item'}->{'forecast'} as $forecast) {
			//print_r($forecast);
			$array['deviceID'] = $deviceID;
			$array['mdate'] = date("Y-m-d H:i:s",strtotime($forecast->{'date'}));
			$array['day'] = $forecast->{'day'};
			//if ($i == 0) $array['day'] = $array['day'];
			//if ($i == 1) $array['day'] = "Tomorrow";
			$array['low'] = $forecast->{'low'};
			$array['high'] = $forecast->{'high'};
			$array['text'] = $forecast->{'text'};
			$array['code'] = $forecast->{'code'};
			$image = $forecast->{'code'}.'s.png';
			cache_image(IMAGE_CACHE.$image, 'http://l.yimg.com/a/i/us/nws/weather/gr/'.$image);
			$array['link1'] = FRONT_DIR.$image;
			$row = FetchRow("SELECT `severity` FROM `ha_weather_codes` WHERE `code` = ".$forecast->{'code'});
			$array['class'] = "";
			if ($row['severity'] == SEVERITY_DANGER) {
				$array['class'] = SEVERITY_DANGER_CLASS;
			}
			if ($row['severity'] == SEVERITY_WARNING) {
				$array['class'] = SEVERITY_WARNING_CLASS;
			}
			PDOupdate("ha_weather_forecast", $array, array('id' => $i));
//			PDOinsert("ha_weather_forecast", $array);
			$i++;
		}
		}
	}
	if ($error) {
		$feedback['error'] = $get->getresponsecode();
		$properties['Status']['value'] = STATUS_ERROR;
		$device['properties'] = $properties;
		$feedback['updateDeviceProperties'] = updateDeviceProperties(array('callerID' => $params['callerID'], 'deviceID' => $deviceID, 'device' => $device));
	}

	if (DEBUG_WEATHER) echo "</pre>";
	return $feedback;
}


function cache_image($file, $url, $hours = 168) {
	//vars

	// Has to run on vlosite
//	$file = $_SERVER['DOCUMENT_ROOT'].$file ;
	$file = '/home/www/vlohome'.$file ;

//echo "***".$file.CRLF;

	$current_time = time(); 
	$expire_time = $hours * 60 * 60; 

	if(file_exists($file)) {
		$file_time = filemtime($file);
		if($current_time - $expire_time < $file_time) {
			return true;
		}
	}

	$content = get_url($url);
	file_put_contents($file, $content);
	return true;
}

function get_url($url) {
	$ch = curl_init();
	curl_setopt($ch,CURLOPT_URL,$url);
	curl_setopt($ch,CURLOPT_RETURNTRANSFER,1); 
	curl_setopt($ch,CURLOPT_CONNECTTIMEOUT,5);
	$content = curl_exec($ch);
	curl_close($ch);
	return $content;
}

function loadWeather($station) {

	ini_set('max_execution_time',30);

	$mydeviceID = array("KBHM" => 65 , "KEET" => 66);
	$retry = 5;
        $success = False;

        while ($retry > 0 && !$success) {
            try {

            	$url= WEATHER_URL.$station.".xml";
            	$get = restClient::get($url);
            	$feedback = ($get->getresponsecode()==200 ? TRUE : FALSE);
            	if ($feedback) {
            		$xml = new SimpleXMLElement($get->getresponse());
					$device['previous_properties'] = getDeviceProperties(Array('deviceID' => $mydeviceID[$station]));
					$properties['Temperature']['value'] = $xml->temp_c;
					$properties['Humidity']['value'] = $xml->relative_humidity;
					$properties['Status']['value'] = STATUS_ON;
					$device['properties'] = $properties;
					updateDeviceProperties(array('callerID' => 'MY_DEVICE_ID', 'deviceID' => $mydeviceID[$station], 'device' => $device));
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
?>
