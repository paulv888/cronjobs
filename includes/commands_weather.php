<?php
define('IMAGE_CACHE',"/images/yahoo/");
define('FRONT_DIR',"/images/yahoo/");

// 
//  Part of commandfactory
//
function getWeather($params) {

	debug($params, 'params');

	$deviceID = $params['deviceID'];

	$feedback['Name'] = 'getWeather';
	$feedback['commandstr'] = setURL($params);
	$feedback['result'] = array();

	$get = RestClient::get($feedback['commandstr'],null,setAuthentication($params['device']),$params['device']['connection']['timeout']);

	$error = false;
	if ($get->getresponsecode()!= 200) $error=true;
	if (!$error) {
		$device['previous_properties'] = getDeviceProperties(Array('deviceID' => $deviceID));
		$result = json_decode($get->getresponse());
		debug($result, 'result');
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
				$i++;
			}
			
	//$url = "http://api.wunderground.com/api/275a74de4762b2a3/alerts/q/TX/Houston.json";
	// $url = "http://api.wunderground.com/api/275a74de4762b2a3/alerts/q/PA/Philadelphia.json";
	// $get = RestClient::get($url,null,null,30);
		// $result = json_decode($get->getresponse());
			debug($result, 'result');

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
			
			debug($result->{'sun_phase'}, 'sun_phase');
			if (isset($result->{'sun_phase'})) {
				$dark_params['caller'] = $params['caller'];
				$dark_params['deviceID'] = DEVICE_DARK_OUTSIDE;
				$dark_params['device']['id'] =  DEVICE_DARK_OUTSIDE;
				$dark_params['device']['previous_properties'] = getDeviceProperties(Array('deviceID' => DEVICE_DARK_OUTSIDE));
				$dark_properties['Astronomy Sunrise']['value'] = date("H:i", strtotime(preg_replace("/:(\d) /",":0$1 ",$result->{'sun_phase'}->{'sunrise'}->{'hour'}.':'.$result->{'sun_phase'}->{'sunrise'}->{'minute'})));
				$dark_properties['Astronomy Sunset']['value'] = date("H:i", strtotime(preg_replace("/:(\d) /",":0$1 ",$result->{'sun_phase'}->{'sunset'}->{'hour'}.':'.$result->{'sun_phase'}->{'sunset'}->{'minute'})));
				$dark_properties['Link']['value'] = LINK_UP;
				$dark_params['device']['properties'] = $dark_properties;
				debug($dark_params, 'dark_params');
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

	
	debug($feedback, 'feedback');
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
