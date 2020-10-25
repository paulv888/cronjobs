<?php
function getThermoSettings(&$params) {
	$feedback['result'][] = array();
	$feedback['Name'] = 'getThermoSettings';

	try	{
		$stat = new Stat( $params['device'] );
		$stat->getStat();

		//
		//	Handled in isRunning property update
		//
		// $statData = $stat->getStat();
		// $heatStatus = ($stat->tstate == 1) ? true : false;
		// $coolStatus = ($stat->tstate == 2) ? true : false;
		// $fanStatus  = ($stat->fstate == 1) ? true : false;
		// logIt( 'Heat: ' . ($heatStatus ? 'ON' : 'OFF'));
		// logIt( 'Cool: ' . ($coolStatus ? 'ON' : 'OFF'));
		// logIt( 'Fan: ' . ($fanStatus ? 'ON' : 'OFF'));
		// UpdateStatusCycle($params['deviceID'], $heatStatus, $coolStatus, $fanStatus);


		// Check heating/cooling or off mode and update device type
		// Should I set $params['device']['type']['internal_type'] as well and do before UpdateCycle
		if ($stat->tmode == 1) {
			UpdateThermType($params['deviceID'],DEV_TYPE_THERMOSTAT_CT30_HEAT);
		}	elseif ($stat->tmode == 2) {
			UpdateThermType($params['deviceID'],DEV_TYPE_THERMOSTAT_CT30_COOL);
		}	elseif ($stat->tmode == 3) {
			UpdateThermType($params['deviceID'],DEV_TYPE_THERMOSTAT_CT30_AUTO);
		} else {
			UpdateThermType($params['deviceID'],DEV_TYPE_THERMOSTAT_CT30_OFF);
		}

		//Update Today/Yesterday runtimes from thermostat, TODO: duplicate with isRunning update
		$stat->getDataLog();

		$today = date( 'Y-m-d' );
		PDOupsert('hvac_run_times', array('deviceID' => $params['deviceID'], 'date' => $today, 'heat_runtime' => $stat->runTimeHeat, 'cool_runtime' =>$stat->runTimeCool), array('date' => $today, 'deviceID' => $params['deviceID']));

		$yesterday = date( 'Y-m-d', strtotime( 'yesterday' ));
		PDOupsert('hvac_run_times', array('deviceID' => $params['deviceID'], 'date' => $yesterday, 'heat_runtime' => $stat->runTimeHeatYesterday, 'cool_runtime' =>$stat->runTimeCoolYesterday), array('date' => $yesterday, 'deviceID' => $params['deviceID']));

		$properties['Temperature']['value'] = to_celcius($stat->temp);
		$properties['Setpoint']['value'] =  to_celcius($stat->setpoint);
		$properties['Status']['value'] = $stat->getTargetOnOff();
		$properties['IsRunning']['value'] =  $stat->isrunning;
		$properties['Link']['value'] = LINK_UP;
		$params['device']['properties'] = $properties;

		$feedback['message'] = 'Temp: '.$properties['Temperature']['value']." Set: ".$properties['Setpoint']['value'] =  to_celcius($stat->setpoint)." Running: ".$properties['IsRunning']['value'];
	}
	catch( Exception $e ) {
		$feedback['error'] = 'Caught exception: '. $e->getMessage();
	}
	return $feedback;
}

function updateThermType($deviceID, $typeID){

	PDOUpdate('ha_mf_devices', array('typeID' => $typeID), array('id' => $deviceID ));
	return true;
}

function HvacToggle(&$params) {

	try
	{
		$stat = new Stat( $params['device'] );
//		$stat->getStat();

		if (!empty($params['timervalue'])) {
			$properties['Timer Date']['value'] = date("Y-m-d H:i:s");
			$properties['Timer Value']['value'] = $params['timervalue'];
			$properties['Timer Remaining']['value'] = $params['timervalue'];
			$params['commandID'] = COMMAND_ON;
		}

		$properties['Status']['value'] = $stat->Toggle($params['commandID']);
		$properties['Setpoint']['value'] =  to_celcius($stat->setpoint);
		$params['device']['properties'] = $properties;
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

function HvacWinter(&$params) {

	$feedback['Name'] = 'HvacWinter';
	$feedback['result'] = array();
	// $feedback['message'] = "all good";
	// if () $feedback['error'] = "Not so good";

	//	debug($stepValue, 'stepValue');

	//	debug($feedback, 'feedback');
	if ($params['commandvalue'] == "1") {
		UpdateThermType($params['deviceID'],DEV_TYPE_THERMOSTAT_CT30_HEAT);
		$feedback['commandstr'] = $params['commandvalue'].' DEV_TYPE_THERMOSTAT_CT30_HEAT';
	} else {
		UpdateThermType($params['deviceID'],DEV_TYPE_THERMOSTAT_CT30_COOL);
		$feedback['commandstr'] = $params['commandvalue'].' DEV_TYPE_THERMOSTAT_CT30_COOL';
	}
	// $params['commandID'] = COMMAND_ON;
	// $result = HvacOn($params);
	$result = array();
	return $feedback;
}

function HvacOn(&$params) {
	return HvacToggle($params);
}

function HvacTempAdd(&$params) {

	try
	{
		$stat = new Stat( $params['device'] );
		//$stat->getStat();

		$properties['Status']['value'] = $stat->getTargetOnOff();
		$feedback = $stat->TempAdd($params['commandvalue']);
		//$properties['Temperature']['value'] = to_celcius($stat->temp);
		$properties['Setpoint']['value'] =  to_celcius($stat->setpoint);
		//$properties['IsRunning']['value'] =  $stat->isrunning;
		$params['device']['properties'] = $properties;
		$feedback['message'] = 'Temperature set to '.$properties['Setpoint']['value'];

		return $feedback;
	}
	catch( Exception $e )
	{
		echo 'Caught exception: ',  $e->getMessage(), CRLF;
	}
}

function HvacSetTemp(&$params) {

//global $lock;

	try
	{
		$stat = new Stat( $params['device'] );
		//$stat->getStat();

		// $result['device'] = getDevice($params['deviceID']);
		// $result['device']['previous_properties'] = getDeviceProperties(Array('deviceID' => $params['deviceID']));
		$setpoint = ($params['commandvalue'] < 50 ? to_fahrenheit($params['commandvalue']) : $params['commandvalue']); 
		$feedback['result'] = $stat->setTemp($setpoint);

		$properties['Status']['value'] = $stat->getTargetOnOff();
		//$properties['Temperature']['value'] = to_celcius($stat->temp);
		$properties['Setpoint']['value'] =  to_celcius($stat->setpoint);
		//$properties['IsRunning']['value'] =  $stat->isrunning;
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

function UpdateStatusCycle($deviceID, $heatStatus, $coolStatus, $fanStatus, $forcemove = false) {

	$pdo = openDB();
	$now = (string)date('Y-m-d H:i:s');

	try
	{
		$sql = "SELECT * FROM hvac_status WHERE deviceID=?"; // Really should name columns instead of using *
		$queryStatus = $pdo->prepare( $sql );
		$sql = "INSERT INTO hvac_status( deviceID, date, start_date_heat, start_date_cool, start_date_fan, heat_status, cool_status, fan_status ) VALUES( ?, ?, ?, ?, ?, ?, ?, ? )";
		$queryInsert = $pdo->prepare( $sql );
		$sql = "UPDATE hvac_status SET date = ?, start_date_heat = ?, start_date_cool = ?, start_date_fan = ?, heat_status = ?, cool_status = ?, fan_status = ? WHERE deviceID = ?";
		$statusUpdate = $pdo->prepare( $sql );
		$sql = "INSERT INTO hvac_cycles( deviceID, system, start_time, end_time ) VALUES( ?, ?, ?, ? )";
		$cycleInsert = $pdo->prepare( $sql );
	}
	catch( Exception $e )
	{
		PDOError($sql, array(), $e);
	}

	// Get prior state info from DB
	$priorStartDateHeat = null;
	$priorStartDateCool = null;
	$priorStartDateFan = null;
	$priorHeatStatus = false;
	$priorCoolStatus = false;
	$priorFanStatus = false;

	$queryStatus->execute(array($deviceID));
	if( $queryStatus->rowCount() < 1 )
	{ // not found - this is the first time for this thermostat
	  // Perhaps key in on this logic to drive the deep query for the stat??
		$startDateHeat = ($heatStatus) ? $now : null;
		$startDateCool = ($coolStatus) ? $now : null;
		$startDateFan = ($fanStatus) ? $now : null;

		//logit( "Inserting record with $now H $heatStatus C $coolStatus F $fanStatus SDH $startDateHeat SDC $startDateCool SDF $startDateFan for ID ". $deviceID );
		$queryInsert->execute( array( $deviceID, $now, $startDateHeat, $startDateCool, $startDateFan, $heatStatus, $coolStatus, $fanStatus ) );
	}
	else
	{
		while( $row = $queryStatus->fetch( PDO::FETCH_ASSOC ) )
		{ // This SQL had better pull only one row or else there is a data integrity problem!
			// and without an ORDER BY on the SELECT there is no way to know you're geting the same row from this each time
			$priorStartDateHeat = $row['start_date_heat'];			// NULL or actual start
			$priorStartDateCool = $row['start_date_cool'];
			$priorStartDateFan = $row['start_date_fan'];
			$priorHeatStatus = (bool)$row['heat_status'];			// 0 or 1
			$priorCoolStatus = (bool)$row['cool_status'];
			$priorFanStatus = (bool)$row['fan_status'];
		}
		//logit( "$deviceID GOT PRIOR STATE H $priorHeatStatus C $priorCoolStatus F $priorFanStatus SDH $priorStartDateHeat SDC $priorStartDateCool SDC $priorStartDateFan" );

		// update start dates if the cycle just started
		if ($forcemove) {
			$newStartDateHeat = ($priorHeatStatus) ? $now : null;		// Reset to now if was on
			$newStartDateCool = ($priorCoolStatus) ? $now : null;
			$newStartDateFan =  ($priorFanStatus ) ? $now : null;
			$heatStatus = $priorHeatStatus; 								// Leave as is (got here without current status
			$coolStatus = $priorCoolStatus;
			$fanStatus  = $priorFanStatus;
		} else {
			$newStartDateHeat = (!$priorHeatStatus && $heatStatus) ? $now : $priorStartDateHeat;
			$newStartDateCool = (!$priorCoolStatus && $coolStatus) ? $now : $priorStartDateCool;
			$newStartDateFan =  (!$priorFanStatus  && $fanStatus)  ? $now  : $priorStartDateFan;
		}

		// if status has changed from on to off, update hvac_cycles or forced
		if (($forcemove && $priorHeatStatus) || ($priorHeatStatus && !$heatStatus)) {
			//logit( "$deviceID  Finished Heat Cycle - Adding Hvac Cycle Record for $deviceID 1 $priorStartDateHeat $now" );
			$cycleInsert->execute( array( $deviceID, 1, $priorStartDateHeat, $now ) );
			if (!$forcemove) $newStartDateHeat = null;
		}
		if (($forcemove && $priorCoolStatus) || ($priorCoolStatus && !$coolStatus)) {
			//logit( "$deviceID Finished Cool Cycle - Adding Hvac Cycle Record for $deviceID 2 $priorStartDateCool $now" );
			$cycleInsert->execute( array( $deviceID, 2, $priorStartDateCool, $now ) );
			if (!$forcemove) $newStartDateCool = null;
		}
		if (($forcemove && $priorFanStatus) || $priorFanStatus && !$fanStatus ) {
			//logit( "$deviceID  Finished Fan Cycle - Adding Hvac Cycle Record for $deviceID 3 $priorStartDateFan $now" );
			$cycleInsert->execute( array( $deviceID, 3, $priorStartDateFan, $now ) );
			if (!$forcemove) $newStartDateFan = null;
		}

		// update the status table
		//logit( "Updating record with $now SDH $newStartDateHeat SDC $newStartDateCool SDF $newStartDateFan H $heatStatus C $coolStatus F $fanStatus for $deviceID" );
		$statusUpdate->execute( array( $now, $newStartDateHeat, $newStartDateCool, $newStartDateFan, $heatStatus, $coolStatus, $fanStatus, $deviceID ) );
	}
}


function UpdateDailyRuntime($deviceID) {
	//
	//	NOT IN USE, maybe re-use for smoker coop fan
	//
	// update hvac runtime from run-cycles broken timer upstarts
	// Clear/Heat/Cool (Yesterday/Today)
	$sql = 'delete FROM `hvac_run_times` 
			WHERE deviceID = '.$deviceID.' AND date = subdate( DATE_FORMAT( NOW( ) , "%Y-%m-%d" ) , 1 )';
	executeQuery(array( 'commandvalue' => $sql));		// Remove Yesterday
	$sql = 'INSERT INTO `hvac_run_times` (deviceID, date)
				SELECT "'.$deviceID.'", subdate( DATE_FORMAT( NOW( ) , "%Y-%m-%d" ) , 1 )';
	executeQuery(array( 'commandvalue' => $sql));		// Insert Empty Yesterday
	$sql = 'UPDATE `hvac_run_times` h INNER JOIN
			(
				SELECT deviceID, DATE_FORMAT( start_time,"%Y-%m-%d" ) AS date, sum( TIMESTAMPDIFF(MINUTE , start_time, end_time ) ) AS runtime
				FROM `hvac_cycles`
				WHERE deviceID = '.$deviceID.' AND system = 1 AND DATE_FORMAT( start_time, "%Y-%m-%d" ) = subdate( DATE_FORMAT( NOW( ) , "%Y-%m-%d" ) , 1 )
				GROUP BY deviceID, system, DATE_FORMAT( start_time, "%Y-%m-%d" )
			) c ON h.deviceID = c.deviceID AND c.date = h.date
			SET `heat_runtime` = c.runtime';
	executeQuery(array( 'commandvalue' => $sql));		// Update Yesterday heat runtime
	$sql = 'UPDATE `hvac_run_times` h INNER JOIN
			(
				SELECT deviceID, DATE_FORMAT( start_time,"%Y-%m-%d" ) AS date, sum( TIMESTAMPDIFF(MINUTE , start_time, end_time ) ) AS runtime
				FROM `hvac_cycles`
				WHERE deviceID ='.$deviceID.' AND system = 2 AND DATE_FORMAT( start_time, "%Y-%m-%d" ) = subdate( DATE_FORMAT( NOW( ) , "%Y-%m-%d" ) , 1 )
				GROUP BY deviceID, system, DATE_FORMAT( start_time, "%Y-%m-%d" )
			) c ON h.deviceID = c.deviceID AND c.date = h.date
			SET `cool_runtime` = c.runtime';
	executeQuery(array( 'commandvalue' => $sql));	// Update Yesterday Cool runtime
	$sql = 'delete FROM `hvac_run_times` 
			WHERE deviceID = '.$deviceID.' AND date = DATE_FORMAT( NOW( ) , "%Y-%m-%d" )';
	executeQuery(array( 'commandvalue' => $sql));	// Delete Today
	$sql = 'INSERT INTO `hvac_run_times` (deviceID, date)
				SELECT "'.$deviceID.'", DATE_FORMAT( NOW( ) , "%Y-%m-%d" )';
	executeQuery(array( 'commandvalue' => $sql)); // Insert Empty Today
	$sql = 'UPDATE `hvac_run_times` h INNER JOIN
			(
				SELECT deviceID, DATE_FORMAT( start_time,"%Y-%m-%d" ) AS date, sum( TIMESTAMPDIFF(MINUTE , start_time, end_time ) ) AS runtime
				FROM `hvac_cycles`
				WHERE deviceID ='.$deviceID.' AND system = 1 AND DATE_FORMAT( start_time, "%Y-%m-%d" ) = DATE_FORMAT( NOW( ) , "%Y-%m-%d" )
				GROUP BY deviceID, system, DATE_FORMAT( start_time, "%Y-%m-%d" )
			) c ON h.deviceID = c.deviceID AND c.date = h.date
			SET `heat_runtime` = c.runtime';
	executeQuery(array( 'commandvalue' => $sql));	// Update Heat runtime
	$sql = 'UPDATE `hvac_run_times` h INNER JOIN
			(
				SELECT deviceID, DATE_FORMAT( start_time,"%Y-%m-%d" ) AS date, sum( TIMESTAMPDIFF(MINUTE , start_time, end_time ) ) AS runtime
				FROM `hvac_cycles`
				WHERE deviceID = '.$deviceID.' AND system = 2 AND DATE_FORMAT( start_time, "%Y-%m-%d" ) = DATE_FORMAT( NOW( ) , "%Y-%m-%d" )
				GROUP BY deviceID, system, DATE_FORMAT( start_time, "%Y-%m-%d" )
			) c ON h.deviceID = c.deviceID AND c.date = h.date
			SET `cool_runtime` = c.runtime';
	executeQuery(array( 'commandvalue' => $sql)); // Update Cool runtime
}
?>
