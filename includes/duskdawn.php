<?php
//define( 'DEBUG_DUSKDAWN', TRUE );
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

		$tsr = date("H:i", strtotime($result->{'astronomy'}->{'sunrise'}));
		$tss = date("H:i", strtotime($result->{'astronomy'}->{'sunset'}));

		$properties['Astronomy Sunrise'] = $tsr;
		$properties['Astronomy Sunset'] = $tss;
		$properties['Status'] = getStatusLink(Array('deviceID' => DEVICE_DARK_OUTSIDE))['status'];
		$feedback['updatestatus'] = updateStatus(array( 'callerID' => DEVICE_DARK_OUTSIDE, 'deviceID' => DEVICE_DARK_OUTSIDE, 'properties' => $properties));
   		UpdateLink (array('callerID' => 'MY_DEVICE_ID', 'deviceID' => DEVICE_DARK_OUTSIDE));
	}

	if (DEBUG_DUSKDAWN) echo "</pre>";
	return $feedback;
}

function GetDawn() {
	return getDevicePropertyValue(Array('deviceID' => DEVICE_DARK_OUTSIDE, 'description' => "Astronomy Sunrise"));
}

function GetDusk() {
	return getDevicePropertyValue(Array('deviceID' => DEVICE_DARK_OUTSIDE, 'description' => "Astronomy Sunset"));
}

?>
