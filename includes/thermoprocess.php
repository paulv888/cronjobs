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

function HvacToggle($callerID, $deviceID, $status = NULL) {

// global $lock;

$now = date( 'Y-m-d H:i:s' );

	$thermostats = getThermoStats();
	$thermostatRec = $thermostats[$deviceID];
	// if(openLockFile('/tmp/thermo.lock'. $thermostatRec['deviceID']))
	// {
		try
		{
			$thermostatRec = $thermostats[$deviceID];
			// Query thermostat info
			//logIt( "Connecting to {$thermostatRec['id']} {$thermostatRec['tstat_uuid']} {$thermostatRec['targetaddress']} {$thermostatRec['name']}" );
			$stat = new Stat( $thermostatRec );
			$stat->getStat();
			
			$result['status'] = $stat->Toggle($status);
			$result['commandvalue'] =  to_celcius($stat->temp);
			$result['deviceID'] = $thermostatRec['deviceID'];
			$result['callerID'] = $callerID;
			$result['setpoint'] = to_celcius($stat->ttemp);
// echo "<pre>HVACToggle ";			
// print_r($result);
// echo "</pre>";			
			$feedback = UpdateStatus($result);
			UpdateWeatherNow($thermostatRec['deviceID'], $result['commandvalue'], NULL, $result['setpoint']);

			return $feedback;
		}
		catch( Exception $e )
		{
			PDOError($sql, array(), $e);
		}
		
		// flock( $lock, LOCK_UN );
	// }
	// else
	// {
		// die( "Couldn't get file lock for thermostat {$thermostatRec['id']}" );
	// }
	// fclose( $lock );

}
function HvacOff($callerID, $deviceID) {

	// Using Toggle so flip status ON=OFF
	return HvacToggle($callerID, $deviceID, STATUS_ON);

}

function HvacStartTimer($callerID, $deviceID, $time) {
	HvacOn($callerID, $deviceID);
	RunQuery('UPDATE `ha_mf_monitor_status` SET  `timerMinute` =  '.$time.' , `timerRemaining` = '.$time.', timerDate = NOW() WHERE  `ha_mf_monitor_status`.`deviceID` = '.$deviceID);
}

function HvacOn($callerID, $deviceID) {

	// Using Toggle so flip status ON=OFF
	return HvacToggle($callerID, $deviceID, STATUS_OFF);

}

function HvacTempAdd($callerID, $deviceID, $addtemp) {

//global $lock;
global $thermostats;

$now = date( 'Y-m-d H:i:s' );

	$thermostats = getThermoStats();
	$thermostatRec = $thermostats[$deviceID];
	// if(openLockFile('/tmp/thermo.lock'. $thermostatRec['deviceID']))
	// {
		try
		{
			$thermostatRec = $thermostats[$deviceID];
			// Query thermostat info
			//logIt( "Connecting to {$thermostatRec['id']} {$thermostatRec['tstat_uuid']} {$thermostatRec['targetaddress']} {$thermostatRec['name']}" );
			$stat = new Stat( $thermostatRec );
			$stat->getStat();
			
			$result['status'] = $stat->getTargetOnOff();
			$stat->TempAdd($addtemp);
		
			$result['commandvalue'] =  to_celcius($stat->temp);
			$result['deviceID'] = $thermostatRec['deviceID'];
			$result['callerID'] = $callerID;
			$result['setpoint'] = to_celcius($stat->ttemp);
			
			$feedback = UpdateStatus($result);
			UpdateWeatherNow($thermostatRec['deviceID'], $result['commandvalue'], NULL, $result['setpoint']);
		
			return $feedback;
		}
		catch( Exception $e )
		{
			PDOError($sql, array(), $e);
		}
		
		// flock( $lock, LOCK_UN );
	// }
	// else
	// {
		// die( "Couldn't get file lock for thermostat {$thermostatRec['id']}" );
	// }
	// fclose( $lock );

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
