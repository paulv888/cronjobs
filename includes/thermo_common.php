<?php
//require 'thermo_config.php';

// Future logging method
function logit( $msg )
{
   echo $msg . "<br/>\r\n";
}

function getThermostats(){

	global $pdo;

	try
	{
	  $thermostats = array();
	  $sql = "SELECT * FROM `ha_mf_devices` inner join ha_mf_devices_thermostat as t ON ". 
			  "t.deviceID=`ha_mf_devices`.`id` inner join ha_mf_device_links AS l ON devicelinkID=l.id " .
			  "WHERE typeID=".DEV_TYPE_THERMOSTAT_CT30_HEAT." OR typeID=" . DEV_TYPE_THERMOSTAT_CT30_COOL ." OR typeID= " . DEV_TYPE_THERMOSTAT_CT30_OFF;
	  foreach( $pdo->query($sql) as $row )
	  {
		  $thermostats[$row['deviceID']] = $row;
	  }
	}
	catch( Exception $e )
	{
	  //logit( "Error getting thermostat list" );
	}
	return $thermostats;

}

function UpdateStatusCycle($deviceID, $heatStatus, $coolStatus, $fanStatus, $forcemove = false) {
				
	global $pdo;
	global $dbConfig;
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
