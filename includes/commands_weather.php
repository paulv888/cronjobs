<?php
define('IMAGE_CACHE',"/images/weather/");
define('FRONT_DIR',"/images/weather/");

// 
//  Part of commandfactory
//
function getWeather(&$params) {

	debug($params, 'params');

	$deviceID = $params['deviceID'];

	$feedback['Name'] = 'getWeather';
	// $feedback['commandstr'] = setURL($params);
	$feedback['result'] = array();
	// https://api.weather.gov/gridpoints/BMX/60,77/forecast
	
	$feedback['commandstr'] = setUrl($params, '/stations'.$params['commandvalue'].'/observations/latest?require_qc=false' );
	$get = RestClient::get($feedback['commandstr'],null,setAuthentication($params['device']), $params['device']['connection']['timeout']);
	$feedback['result']['current'] = $get->getresponse();

	$error = false;
	if ($get->getresponsecode()!= 200) $error=true;
	if (!$error) {
		$result = json_decode($get->getresponse());
		debug($result, 'extended weather');
		if (!isset($result->{'properties'})) {
			$error = true;
		} else {
			if (isset($result->{'properties'}->{'temperature'}->{'value'})) {
				$result = $result->{'properties'};
				$properties['Temperature']['value'] = $result->{'temperature'}->{'value'};
				$properties['Humidity']['value'] =  $result->{'relativeHumidity'}->{'value'};
				$properties['Wind Speed']['value'] =  $result->{'windSpeed'}->{'value'};
				$properties['Pressure']['value'] =  $result->{'barometricPressure'}->{'value'}/100;
				$properties['Status']['value'] = STATUS_ON;
				$params['device']['properties'] = $properties;

				$array['deviceID'] = $deviceID;
				$array['mdate'] = date("Y-m-d H:i:s",strtotime($result->{'timestamp'}));
				$array['temp'] = $result->{'temperature'}->{'value'};
				$array['humidity'] = $result->{'relativeHumidity'}->{'value'};
				$array['pressure'] = $result->{'barometricPressure'}->{'value'}/100;
				$array['visibility'] = $result->{'visibility'}->{'value'}/1000;
				if (isset($result->{'windDirection'}->{'value'})) $array['direction'] = degToCompass($result->{'windDirection'}->{'value'});
				$to_beaufort = array (0.2, 1.5, 3.3, 5.4, 7.9, 10.7, 13.8, 17.1, 20.7, 24.4, 28.4, 32.6, 32.7);
				foreach ($to_beaufort as $key => $value) {
					if ($result->{'windSpeed'}->{'value'} < $value) break;
				}
				$array['speed'] = $key;
	// BF	m/s			Label
	// 0	0 - 0.2		Calm
	// 1	0.3-1.5		Light Air
	// 2	1.6-3.3		Light Breeze
	// 3	3.4-5.4		Gentle Breeze
	// 4	5.5-7.9		Moderate Breeze
	// 5	8.0-10.7	Fresh Breeze
	// 6	10.8-13.8	strong Breeze
	// 7	13.9-17.1	Near Gale
	// 8	17.2-20.7	Gale
	// 9	20.8-24.4	Severe Gale
	// 10	24.5-28.4	Strong storm
	// 11	28.5-32.6	Violent Storm
	// 12	>32.7		Hurricane

				$array['short_forecast'] = $result->{'textDescription'};
				$array['typeID'] = DEV_TYPE_TEMP_HUMIDITY;
				$array['link1'] = cache_image(str_replace('medium','large',$result->{'icon'}));
				PDOupdate("ha_weather_extended", $array, array( 'deviceID' => $deviceID));
				debug($result, 'result');
			} else {
				$feedback['result']['message'] = "Skipping, did not find temperature";
			}
		}
	}
	if ($error) {
		$feedback['error'] = $get->getresponsecode();
		$properties['Status']['value'] = STATUS_ERROR;
		$params['device']['properties'] = $properties;
	}
	
	debug($feedback, 'feedback');
	return $feedback;
}

function getWeatherForecast(&$params) {

	debug($params, 'params');
//
// Get URL here, for forecast 
//


	$deviceID = $params['deviceID'];

	$feedback['Name'] = 'getWeatherForecast';
	$feedback['result'] = array();
	$feedback['commandstr'] = setUrl($params, '/gridpoints'.$params['commandvalue'].'/forecast' );
	$get = RestClient::get($feedback['commandstr'],null,setAuthentication($params['device']), $params['device']['connection']['timeout']);
	$feedback['result']['current'] = $get->getresponse();

	$error = false;
	if ($get->getresponsecode()!= 200) $error=true;
	if (!$error) {
		$result = json_decode($get->getresponse());
		debug($result, 'extended weather');
		if (!isset($result->{'properties'})) {
			$error = true;
		} else {
			$id = 1;
			foreach ($result->{'properties'}->{'periods'} as $forecast) {
				$array['id'] = $id;
				$array['isday'] = $forecast->{'isDaytime'};
				$array['temperature'] =  to_celcius($forecast->{'temperature'});
				$array['weekday'] = ($forecast->{'isDaytime'} ?  $forecast->{'name'} : ($forecast->{'name'} == "Tonight" ? $forecast->{'name'} : substr($forecast->{'name'},0,3)." Night"));
				$array['short_forecast'] = $forecast->{'shortForecast'};
				$array['long_forecast'] = $forecast->{'detailedForecast'};
				$array['wind'] = $forecast->{'windSpeed'}.' - '.$forecast->{'windDirection'};
				
				// $image = array_count_values($image); arsort($image); reset($image); 			
				$array['link2'] = cache_image(str_replace('medium','large',$forecast->{'icon'}));
				$array['link1'] = cache_image(str_replace('medium','small',$forecast->{'icon'}));
				debug($array, 'Calculated Forecast');
				PDOupsert("ha_weather_forecast", $array, array('id' => $id));
				$id++;
			}
		}
	}
	if ($error) {
		$feedback['error'] = $get->getresponsecode();
		$properties['Status']['value'] = STATUS_ERROR;
		$params['device']['properties'] = $properties;
	}

	
	debug($feedback, 'feedback');
	return $feedback;
}

function getWeatherAlerts(&$params) {
	// https://api.weather.gov/points/33.371,-86.7589
	// https://api.weather.gov/gridpoints/BMX/60,77/stations 
	
	// ALZ025 - Shelby

	debug($params, 'params');

	$deviceID = $params['deviceID'];
	$feedback['Name'] = 'getWeatherAlerts';
	$feedback['result'] = array();
	
	$feedback['commandstr'] = setUrl($params, '/alerts/active/zone'.$params['commandvalue']);
	$get = RestClient::get($feedback['commandstr'],null,setAuthentication($params['device']), $params['device']['connection']['timeout']);
	$feedback['result']['current'] = $get->getresponse();

	$error = false;
	if ($get->getresponsecode()!= 200) $error=true;
	if (!$error) {
		$result = json_decode($get->getresponse());
		debug($result, 'Weather Alerts');
		if (!isset($result->{'type'})) {
			$error = true;
		} else {
			if (isset($result->{'features'})) {		// We have alerts
			
				foreach ($result->{'features'} as $alert) {
					$array['deviceID'] = $deviceID;
					$array['code'] = $alert->{'properties'}->{'event'};
					$array['mdate'] = date("Y-m-d H:i:s",strtotime($alert->{'properties'}->{'effective'}));
					$array['expires'] = date("Y-m-d H:i:s",strtotime($alert->{'properties'}->{'expires'}));
					$array['description'] =$alert->{'properties'}->{'event'};
					$array['headline'] = $alert->{'properties'}->{'headline'};
					$array['message'] = str_replace(PHP_EOL, '<br>', $alert->{'properties'}->{'description'});
					$array['class'] = "";
					if ($alert->{'properties'}->{'severity'} == SEVERITY_DANGER) {
						$array['class'] = SEVERITY_DANGER_CLASS;
					} elseif ($alert->{'properties'}->{'severity'} == SEVERITY_WARNING) {
						$array['class'] = SEVERITY_WARNING_CLASS;
					} elseif ($alert->{'properties'}->{'severity'} == SEVERITY_INFO) {
						$array['class'] = SEVERITY_INFO_CLASS;
					}
					PDOupsert("ha_weather_alerts", $array, array('expires' => $array['expires'], 'code' => $array['code']));
				}
			}
		}
	}
	debug($feedback, 'feedback');
	return $feedback;
}

function getDawnDusk(&$params) {

	debug($params, 'params');
	$deviceID = $params['deviceID'];

	$feedback['Name'] = 'getDawnDusk';
	// $feedback['commandstr'] = setURL($params);
	$feedback['result'] = array();

	$feedback['commandstr'] = setUrl($params, '?timeZoneId=America%2FChicago&latitude=33.543682&longitude=-86.779633&date='.date("Y-m-d"));
	$headers = array();
	$headers['x-rapidapi-host'] = 'sunrise-sunset-times.p.rapidapi.com';
	$headers['x-rapidapi-key']=	$params['device']['connection']['api_key'] ;
	
	
	
	$get = RestClient::get($feedback['commandstr'],null,setAuthentication($params['device']), $params['device']['connection']['timeout'], $headers);
	$feedback['result']['current'] = $get->getresponse();

	$error = false;
	if ($get->getresponsecode()!= 200) $error=true;
	if (!$error) {
		$result = json_decode($get->getresponse());
		date_default_timezone_set('America/Chicago');
		$properties['Astronomy Sunrise']['value'] = date("H:i",strtotime(str_replace("[America/Chicago]","",$result->{'sunrise'})));
		$properties['Astronomy Sunset']['value'] = date("H:i",strtotime( str_replace("[America/Chicago]","",$result->{'sunset'})));
		$properties['Link']['value'] = LINK_UP;
		$params['device']['properties'] = $properties;
		date_default_timezone_set('UTC');
		debug($params, 'dark_params');
		}
	if ($error) {
		$feedback['error'] = $get->getresponsecode();
		$properties['Status']['value'] = STATUS_ERROR;
		$params['device']['properties'] = $properties;
	}

	debug($feedback, 'feedback');
	return $feedback;
}

function degToCompass($num) {
	$val=intval (($num/22.5)+.5);
	$compass= array("N","NNE","NE","ENE","E","ESE", "SE", "SSE","S","SSW","SW","WSW","W","WNW","NW","NNW");
	return $compass[($val % 16)];
}

function cache_image($url, $hours = 168) {
	
	$image = str_replace('https://api.weather.gov/icons/', '', $url);
	$image = str_replace('?size=', '_', $image);
	$image = str_replace('/', '_', $image);

	$file = '/home/www/vlohome'.IMAGE_CACHE.$image ;

	$current_time = time(); 
	$expire_time = $hours * 60 * 60; 

	if(file_exists($file)) {
		$file_time = filemtime($file);
		if($current_time - $expire_time < $file_time) {
			return FRONT_DIR.$image;
		}
	}

	$get = RestClient::get($url);
	file_put_contents($file, $get->getresponse());
	
	return FRONT_DIR.$image;
}
?>