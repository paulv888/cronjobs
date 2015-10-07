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

function HvacToggle($callerID, $deviceID, $status = NULL, $timervalue = Null) {

$now = date( 'Y-m-d H:i:s' );

	$thermostats = getThermoStats();
	$thermostatRec = $thermostats[$deviceID];
	try
	{
		$thermostatRec = $thermostats[$deviceID];
		$stat = new Stat( $thermostatRec );
		$stat->getStat();
	
		$result['device'] = getDevice($thermostatRec['deviceID']);
		$result['device']['previous_properties'] = getDeviceProperties(Array('deviceID' => $thermostatRec['deviceID']));

		$result['deviceID'] = $thermostatRec['deviceID'];
		$result['callerID'] = $callerID;
		
		$properties['Status']['value'] = $stat->Toggle($status);
		$properties['Temperature']['value'] = to_celcius($stat->temp);
		$properties['Setpoint']['value'] =  to_celcius($stat->ttemp);
		
		if (!is_null($timervalue)) {
			$properties['Timer Date']['value'] = date("Y-m-d H:i:s");
			$properties['Timer Value']['value'] = $timervalue;
			$properties['Timer Remaining']['value'] = $timervalue;
		}
		
		$result['device']['properties'] = $properties;
		$feedback = updateDeviceProperties($result);
		return $feedback;
	}
	catch( Exception $e )
	{
		echo 'Caught exception: ',  $e->getMessage(), CRLF;
	}
}
function HvacOff($callerID, $deviceID) {

	// Using Toggle so flip status ON=OFF
	return HvacToggle($callerID, $deviceID, STATUS_ON);

}

function HvacStartTimer($callerID, $deviceID, $timervalue) {
	$result = HvacOn($callerID, $deviceID, $timervalue);
	return $result;
}

function HvacOn($callerID, $deviceID, $timervalue = Null) {

	// Using Toggle so flip status ON=OFF
	return HvacToggle($callerID, $deviceID, STATUS_OFF, $timervalue);

}

function HvacTempAdd($callerID, $deviceID, $addtemp) {

//global $lock;
global $thermostats;

$now = date( 'Y-m-d H:i:s' );

	$thermostats = getThermoStats();
	$thermostatRec = $thermostats[$deviceID];
	try
	{
		$thermostatRec = $thermostats[$deviceID];
		$stat = new Stat( $thermostatRec );
		$stat->getStat();

		$result['device'] = getDevice($thermostatRec['deviceID']);
		$result['device']['previous_properties'] = getDeviceProperties(Array('deviceID' => $thermostatRec['deviceID']));
		$stat->TempAdd($addtemp);

		$result['deviceID'] = $thermostatRec['deviceID'];
		$result['callerID'] = $callerID;
		
		$properties['Status']['value'] = $stat->getTargetOnOff();
		$properties['Temperature']['value'] = to_celcius($stat->temp);
		$properties['Setpoint']['value'] =  to_celcius($stat->ttemp);
		$result['device']['properties'] = $properties;
		$feedback = updateDeviceProperties($result);
	
		return $feedback;
	}
	catch( Exception $e )
	{
		echo 'Caught exception: ',  $e->getMessage(), CRLF;
	}
}

function HvacUp($callerID, $deviceID) {

	// Using Toggle so flip status ON=OFF
	return HvacTempAdd($callerID, $deviceID, 1);

}
function HvacDown($callerID, $deviceID) {

	// Using Toggle so flip status ON=OFF
	return HvacTempAdd($callerID, $deviceID, -1);

}
?>
