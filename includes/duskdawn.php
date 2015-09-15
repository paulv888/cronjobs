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

		$tsr = date("H:i:s", strtotime($result->{'astronomy'}->{'sunrise'}));
		$tss = date("H:i:s", strtotime($result->{'astronomy'}->{'sunset'}));

		//PDOupsert("ha_mf_device_properties", Array('description' => 'Astronomy Sunrise', 'value' => $tsr), array('deviceID' => DEVICE_DARK_OUTSIDE, 'description' => 'Astronomy Sunrise'));
		$properties['Astronomy Sunrise'] = $tsr;
		$properties['Astronomy Sunset'] = $tss;
		$feedback['updatestatus'] = UpdateStatus(array( 'callerID' => DEVICE_DARK_OUTSIDE, 'deviceID' => DEVICE_DARK_OUTSIDE, 'status' => getStatusLink(Array('deviceID' => DEVICE_DARK_OUTSIDE))['status'], 'properties' => $properties));
   		UpdateLink (array('callerID' => 'MY_DEVICE_ID', 'deviceID' => DEVICE_DARK_OUTSIDE));
	}

	if (DEBUG_DUSKDAWN) echo "</pre>";
	return $feedback;
}

function GetDawn() {
	return getPropertyValue(DEVICE_DARK_OUTSIDE, "Astronomy Sunrise");
}

function GetDusk() {
	return getPropertyValue(DEVICE_DARK_OUTSIDE, "Astronomy Sunset");
}

?>
