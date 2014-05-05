<?php
require_once 'includes.php';


define("MY_DEVICE_ID", 136);

echo UpdateTemps();
echo UpdateLink(MY_DEVICE_ID)." My Link Updated <br/>\r\n";




function UpdateTemps() {
	global $pdo;
	global $thermostats;
	global $dbConfig;
	
	$today = date( 'Y-m-d' );
	$yesterday = date( 'Y-m-d', strtotime( 'yesterday' ));
	
		/**
		* This script updates the indoor and outdoor temperatures and today's and yesterday total run time for each thermostat
		*/
	
	
	try
	{
		$sql = "SELECT * FROM {$dbConfig['table_prefix']}hvac_status WHERE deviceID=?"; // Really should name columns instead of using *
		$queryStatus = $pdo->prepare( $sql );
	
		$sql = "INSERT INTO {$dbConfig['table_prefix']}hvac_status( deviceID, date, start_date_heat, start_date_cool, start_date_fan, heat_status, cool_status, fan_status ) VALUES( ?, ?, ?, ?, ?, ?, ?, ? )";
		$queryInsert = $pdo->prepare( $sql );
	
		$sql = "UPDATE ha_mf_devices_thermostat SET tstat_uuid = ?, model = ?, fw_version = ?, wlan_fw_version = ? WHERE deviceID = ?";
		$queryUpdateSysInfo = $pdo->prepare( $sql );
	
		$sql = "UPDATE {$dbConfig['table_prefix']}hvac_status SET date = ?, start_date_heat = ?, start_date_cool = ?, start_date_fan = ?, heat_status = ?, cool_status = ?, fan_status = ? WHERE deviceID = ?";
		$queryUpdate = $pdo->prepare( $sql );
	
		$sql = "INSERT INTO {$dbConfig['table_prefix']}hvac_cycles( deviceID, system, start_time, end_time ) VALUES( ?, ?, ?, ? )";
		$cycleInsert = $pdo->prepare( $sql );
	
		$sql = "INSERT INTO ha_weather_current ( mdate, source, temperature_c, set_point, ttrend, deviceID ) VALUES ('".date("Y-m-d H:i:s")."', '".MY_DEVICE_ID."', ?, ?, ?, ?)";
		$queryCurrent = $pdo->prepare($sql);
		
		$sql = "DELETE FROM {$dbConfig['table_prefix']}hvac_run_times WHERE date = ? AND deviceID = ?";
		$queryRunDelete = $pdo->prepare($sql);
	
		$sql = "INSERT INTO {$dbConfig['table_prefix']}hvac_run_times( deviceID, date, heat_runtime, cool_runtime ) VALUES ( ?, ?, ?, ? )";
		$queryRunInsert = $pdo->prepare($sql);
	}
	catch( Exception $e )
	{
		doError( 'DB Exception: ' . $e->getMessage() );
	}
	
	global $lock;
	
	$now = (string)date('Y-m-d H:i:s');
	
	foreach( $thermostats as $thermostatRec )
	{
		
		if(openLockFile('/tmp/thermo.lock'. $thermostatRec['deviceID']))
		{
			try
			{
				// Query thermostat info
				$indoorHumidity = null;
				logIt( "Connecting to {$thermostatRec['id']} {$thermostatRec['deviceID']} {$thermostatRec['targetaddress']} {$thermostatRec['name']}" );
				$stat = new Stat( $thermostatRec);
	
	/*			$sysInfo = $stat->getSysInfo();
				$stat->getSysInfo();
				sleep(2); // allow thermostat to catch up
				$model = $stat->getModel();
				$stat->getModel();
				$queryUpdateSysInfo->execute( array( $sysInfo['uuid'], $model, $sysInfo['fw_version'], $sysInfo['wlan_fw_version'], $thermostatRec['deviceID'] ) );
	*/			
				// Get thermostat state
				$statData = $stat->getStat();
				$heatStatus = ($stat->tstate == 1) ? true : false;
				$coolStatus = ($stat->tstate == 2) ? true : false;
				$fanStatus  = ($stat->fstate == 1) ? true : false;
				logIt( 'Heat: ' . ($heatStatus ? 'ON' : 'OFF'));
				logIt( 'Cool: ' . ($coolStatus ? 'ON' : 'OFF'));
				logIt( 'Fan: ' . ($fanStatus ? 'ON' : 'OFF'));
	
				// Get prior state info from DB
				$priorStartDateHeat = null;
				$priorStartDateCool = null;
				$priorStartDateFan = null;
				$priorHeatStatus = false;
				$priorCoolStatus = false;
				$priorFanStatus = false;
	
				$queryStatus->execute(array($thermostatRec['deviceID']));
				if( $queryStatus->rowCount() < 1 )
				{ // not found - this is the first time for this thermostat
				  // Perhaps key in on this logic to drive the deep query for the stat??
					$startDateHeat = ($heatStatus) ? $now : null;
					$startDateCool = ($coolStatus) ? $now : null;
					$startDateFan = ($fanStatus) ? $now : null;
	
					logIt( "Inserting record with $now H $heatStatus C $coolStatus F $fanStatus SDH $startDateHeat SDC $startDateCool SDF $startDateFan for ID ". $thermostatRec['deviceID'] );
					$queryInsert->execute( array( $thermostatRec['deviceID'], $now, $startDateHeat, $startDateCool, $startDateFan, $heatStatus, $coolStatus, $fanStatus ) );
				}
				else
				{
					while( $row = $queryStatus->fetch( PDO::FETCH_ASSOC ) )
					{ // This SQL had better pull only one row or else there is a data integrity problem!
						// and without an ORDER BY on the SELECT there is no way to know you're geting the same row from this each time
						$priorStartDateHeat = $row['start_date_heat'];
						$priorStartDateCool = $row['start_date_cool'];
						$priorStartDateFan = $row['start_date_fan'];
						$priorHeatStatus = (bool)$row['heat_status'];
						$priorCoolStatus = (bool)$row['cool_status'];
						$priorFanStatus = (bool)$row['fan_status'];
					}
					logIt( "$stat->uuid GOT PRIOR STATE H $priorHeatStatus C $priorCoolStatus F $priorFanStatus SDH $priorStartDateHeat SDC $priorStartDateCool SDC $priorStartDateFan" );
	
					// update start dates if the cycle just started
					$newStartDateHeat = (!$priorHeatStatus && $heatStatus) ? $now : $priorStartDateHeat;
					$newStartDateCool = (!$priorCoolStatus && $coolStatus) ? $now : $priorStartDateCool;
					$newStartDateFan = (!$priorFanStatus && $fanStatus) ? $now : $priorStartDateFan;
	
					// if status has changed from on to off, update hvac_cycles
					if( $priorHeatStatus && !$heatStatus )
					{
						logIt( "$stat->uuid Finished Heat Cycle - Adding Hvac Cycle Record for $stat->uuid 1 $priorStartDateHeat $now" );
						$cycleInsert->execute( array( $thermostatRec['deviceID'], 1, $priorStartDateHeat, $now ) );
						$newStartDateHeat = null;
					}
					if( $priorCoolStatus && !$coolStatus )
					{
						logIt( "$stat->uuid Finished Cool Cycle - Adding Hvac Cycle Record for $stat->uuid 2 $priorStartDateCool $now" );
						$cycleInsert->execute( array( $thermostatRec['deviceID'], 2, $priorStartDateCool, $now ) );
						$newStartDateCool = null;
					}
					if( $priorFanStatus && !$fanStatus )
					{
						logIt( "$stat->uuid Finished Fan Cycle - Adding Hvac Cycle Record for $stat->uuid 3 $priorStartDateFan $now" );
						$cycleInsert->execute( array( $thermostatRec['deviceID'], 3, $priorStartDateFan, $now ) );
						$newStartDateFan = null;
					}
					
					// update the status table
					logIt( "Updating record with $now SDH $newStartDateHeat SDC $newStartDateCool SDF $newStartDateFan H $heatStatus C $coolStatus F $fanStatus for UUID $stat->uuid" );
					$queryUpdate->execute( array( $now, $newStartDateHeat, $newStartDateCool, $newStartDateFan, $heatStatus, $coolStatus, $fanStatus, $thermostatRec['deviceID'] ) );
				}
				
				// Instead of asking the thermostat what his model is, rely upon the entry in the thermostat table
				if( strstr($stat->model, 'CT80') !== false )
				{ // Get indoor humidity for CT80
					//sleep(2); // let thermostat catch up
					//$indoorHumidity = $stat->getHumidity();
					$stat->getHumidity();
				}
	
				// Log the indoor and outdoor temperatures for this half-hour increment
				// t_heat or t_cool may not exist if thermostat is running in battery mode
				if ($stat->tmode == 1) { 
					UpdateThermType($thermostatRec['deviceID'],DEV_TYPE_HEAT);
				}	elseif ($stat->tmode == 2) {
					UpdateThermType($thermostatRec['deviceID'],DEV_TYPE_COOL);
				} else {
					UpdateThermType($thermostatRec['deviceID'],DEV_TYPE_OFF);
				}
					
				$target = ($stat->tmode == 1) ? $stat->t_heat : $stat->t_cool;
	
				logIt( "Target $target" );
				logit( "UUID $stat->uuid IT " . $stat->temp . "IH $stat->humidity TARGT $target" );
				//$queryTemp->execute(array( $stat->uuid, $stat->temp, $outdoorTemp, $stat->humidity, $outdoorHumidity, $target ) );
				UpdateWeatherNow($thermostatRec['deviceID'], to_celcius($stat->temp),0 , to_celcius($target));
				UpdateStatus(MY_DEVICE_ID, array( 'deviceID' => $thermostatRec['deviceID'], 'status' => $stat->getTargetOnOff(), 'commandvalue' => to_celcius($stat->temp) ));

	
	
				$sql = "SELECT * FROM `ha_weather_current`  WHERE deviceID=". $thermostatRec['deviceID'] ." order by mdate desc limit 1";
				if ($row = FetchRow($sql)) {
					$last = strtotime($row['mdate']);
					if (timeExpired($last, 60)) {
						logit( "Insert row into Weather Current" );
						$ctemp = to_celcius($stat->temp);
						$ttrend = setTrend($ctemp, $row['temperature_c']);
						$queryCurrent->execute(array( $ctemp, to_celcius($target), $ttrend, $thermostatRec['deviceID']));
					}
				}
	
				//$runTimeData = $stat->getDataLog();
				$stat->getDataLog();
	
				// Need to verify success of thermostat query before deleting/inserting data...
				// Unless it throws an exception?
	
				// Remove zero or one rows for today and then insert one row for today.
				$queryRunDelete->execute( array($today, $thermostatRec['deviceID']) );
				logIt( "Run Time Today - Inserting RTH {$stat->runTimeHeat} RTC {$stat->runTimeCool} U $stat->uuid T $today" );
				$queryRunInsert->execute( array($thermostatRec['deviceID'], $today, $stat->runTimeHeat, $stat->runTimeCool) );
	
				// Remove zero or one rows for yesterday and then insert one row for yesterday.
				$queryRunDelete->execute( array($yesterday,$thermostatRec['deviceID']) );
				logIt( "Run Time Yesterday - Inserting RTH {$stat->runTimeHeatYesterday} RTC {$stat->runTimeCoolYesterday} U $stat->uuid T $yesterday" );
				$queryRunInsert->execute( array($thermostatRec['deviceID'], $yesterday, $stat->runTimeHeatYesterday, $stat->runTimeCoolYesterday) );
				
				echo UpdateLink($thermostatRec['deviceID'])." My Link Updated <br/>\r\n";
	
			}
			catch( Exception $e )
			{
				doError( 'Thermostat Exception: ' . $e->getMessage() );
			}
			flock( $lock, LOCK_UN );
		}
		else
		{
			// never comese here fopen has no timeout
			die( "Couldn't get file lock for thermostat {$thermostatRec['id']}" );
		}
		fclose($lock);
	}
}

//touch( '/home/fratell1/freitag.theinscrutable.us/thermo2/scripts/thermo_update_temps.end' );
?>