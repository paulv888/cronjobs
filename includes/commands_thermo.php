<?php
//touch( '/home/fratell1/freitag.theinscrutable.us/thermo2/scripts/thermo_update_status.start' );

/**
	*
	* Because of different structure and use of hvac_status table, migration from old version to new version is non-trivial.
	* The best bet is to set up the new code running in parallel and then once it's verified working in your environment
	*  shut down the old data collectors, export your old historic temperature data and then import into the new structure.
	*  Then drop your old tables and delete your old install
	*
	* Updated pretty much directly from phareous code fork.
	*/

/**
	* This script perodically (once a minute) queries the thermostat and writes the status into
	* the hvac_status table. There is just one record in the hvac_status table for each
	* thermostat and it shows the current status of the heat, cool, and fan, plus the
	* time it saw that those first started.
	*
	* For each run the status is updated but not the start time. Once it goes from off to on, the start_time is updated.
	* When it goes from on to off, an entry is added to hvac_cycles
	* Date is simply the last time the status was updated
	*/

function HvacToggle(&$params) {

//$callerID, $deviceID, $status = NULL, $timervalue = Null

$now = date( 'Y-m-d H:i:s' );
// echo "<pre>";
// print_r($params);
// echo "</pre>";


	$thermostats = getThermoStats();
	$thermostatRec = $thermostats[$params['deviceID']];
	try
	{
		$thermostatRec = $thermostats[$params['deviceID']];
		$stat = new Stat( $thermostatRec );
		$stat->getStat();
	
		if (!empty($params['timervalue'])) {
			$properties['Timer Date']['value'] = date("Y-m-d H:i:s");
			$properties['Timer Value']['value'] = $params['timervalue'];
			$properties['Timer Remaining']['value'] = $params['timervalue'];
			$params['commandID'] = COMMAND_ON;
		}

		$properties['Status']['value'] = $stat->Toggle($params['commandID']);
		$properties['Temperature']['value'] = to_celcius($stat->temp);
		$properties['Setpoint']['value'] =  to_celcius($stat->ttemp);
		$params['device']['properties'] = $properties;
// echo "<pre>";
// var_dump($properties['Status']['value'] );
// print_r($params);
// echo "</pre>";
		$feedback['message'] = 'Temperature set to '.$properties['Setpoint']['value'];

		return $feedback;
	}
	catch( Exception $e )
	{
		echo 'Caught exception: ',  $e->getMessage(), CRLF;
	}
}
function HvacOff(&$params) {
	return HvacToggle($params);
}

function HvacStartTimer(&$params) {
	$params['timervalue'] = $params['commandvalue'];
	$result = HvacOn($params);
	return $result;
}

function HvacOn(&$params) {
	return HvacToggle($params);
}

function HvacTempAdd(&$params) {

//global $lock;
global $thermostats;

$now = date( 'Y-m-d H:i:s' );

	$thermostats = getThermoStats();
	$thermostatRec = $thermostats[$params['deviceID']];
	try
	{
		$thermostatRec = $thermostats[$params['deviceID']];
		$stat = new Stat( $thermostatRec );
		$stat->getStat();

		// $result['device'] = getDevice($thermostatRec['deviceID']);
		// $result['device']['previous_properties'] = getDeviceProperties(Array('deviceID' => $thermostatRec['deviceID']));
		$stat->TempAdd($params['commandvalue']);

		$properties['Status']['value'] = $stat->getTargetOnOff();
		$properties['Temperature']['value'] = to_celcius($stat->temp);
		$properties['Setpoint']['value'] =  to_celcius($stat->ttemp);
		$params['device']['properties'] = $properties;
		$feedback['message'] = 'Temperature set to '.$properties['Setpoint']['value'];

		return $feedback;
	}
	catch( Exception $e )
	{
		echo 'Caught exception: ',  $e->getMessage(), CRLF;
	}
}

function HvacUp(&$params) {
	$params['commandvalue'] = 1;
	return HvacTempAdd($params);
}
function HvacDown(&$params) {
	$params['commandvalue'] = -1;
	return HvacTempAdd($params);
}
?>
