#!/usr/bin/php
<?php
require_once 'includes.php';

define("MY_DEVICE_ID", 136);

echo date("Y-m-d H:i:s").": ".UpdateTemps();
echo date("Y-m-d H:i:s").": ".UpdateLink(array('callerID' => MY_DEVICE_ID))." My Link Updated <br/>\r\n";

function UpdateTemps() {
	global $pdo;
	global $dbConfig;
	
	$today = date( 'Y-m-d' );
	$yesterday = date( 'Y-m-d', strtotime( 'yesterday' ));
	
		/**
		* This script updates the indoor and outdoor temperatures and today's and yesterday total run time for each thermostat
		*/
	$thermostats = getThermoStats();
	
	try
	{
		$sql = "UPDATE ha_mf_devices_thermostat SET tstat_uuid = ?, model = ?, fw_version = ?, wlan_fw_version = ? WHERE deviceID = ?";
		$queryUpdateSysInfo = $pdo->prepare( $sql );
	
		$sql = "DELETE FROM hvac_run_times WHERE date = ? AND deviceID = ?";
		$queryRunDelete = $pdo->prepare($sql);
	
		$sql = "INSERT INTO hvac_run_times( deviceID, date, heat_runtime, cool_runtime ) VALUES ( ?, ?, ?, ? )";
		$queryRunInsert = $pdo->prepare($sql);
	}
	catch( Exception $e )
	{
		PDOError($sql, array(), $e);
	}
	
	
	$now = (string)date('Y-m-d H:i:s');
	
	foreach( $thermostats as $thermostatRec )
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
	
	
				UpdateStatusCycle($thermostatRec['deviceID'], $heatStatus, $coolStatus, $fanStatus);
	
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
					UpdateThermType($thermostatRec['deviceID'],DEV_TYPE_THERMOSTAT_CT30_HEAT);
				}	elseif ($stat->tmode == 2) {
					UpdateThermType($thermostatRec['deviceID'],DEV_TYPE_THERMOSTAT_CT30_COOL);
				} else {
					UpdateThermType($thermostatRec['deviceID'],DEV_TYPE_THERMOSTAT_CT30_OFF);
				}
					
				$target = ($stat->tmode == 1) ? $stat->t_heat : $stat->t_cool;
	
				logIt( "Target $target" );
				logit( "UUID $stat->uuid IT " . $stat->temp . "IH $stat->humidity TARGT $target" );
				//$queryTemp->execute(array( $stat->uuid, $stat->temp, $outdoorTemp, $stat->humidity, $outdoorHumidity, $target ) );
				$device = getDevice($thermostatRec['deviceID']);
				$device['previous_properties'] = getDeviceProperties(Array('deviceID' => $thermostatRec['deviceID']));
				$properties['Temperature']['value'] = to_celcius($stat->temp);
				$properties['Setpoint']['value'] = to_celcius($target);
				$properties['Status']['value'] = $stat->getTargetOnOff();
				$device['properties'] = $properties;
				updateDeviceProperties(array( 'callerID' => MY_DEVICE_ID, 'deviceID' => $thermostatRec['deviceID'], 'device' => $device));
	
				//$runTimeData = $stat->getDataLog();
				$stat->getDataLog();
				if ($thermostatRec['deviceID']<>999) {
					// Remove zero or one rows for today and then insert one row for today.
					$queryRunDelete->execute( array($today, $thermostatRec['deviceID']) );
					logIt( "Run Time Today - Inserting RTH {$stat->runTimeHeat} RTC {$stat->runTimeCool} U $stat->uuid T $today" );
					$queryRunInsert->execute( array($thermostatRec['deviceID'], $today, $stat->runTimeHeat, $stat->runTimeCool) );
		
					// Remove zero or one rows for yesterday and then insert one row for yesterday.
					$queryRunDelete->execute( array($yesterday,$thermostatRec['deviceID']) );
					logIt( "Run Time Yesterday - Inserting RTH {$stat->runTimeHeatYesterday} RTC {$stat->runTimeCoolYesterday} U $stat->uuid T $yesterday" );
					$queryRunInsert->execute( array($thermostatRec['deviceID'], $yesterday, $stat->runTimeHeatYesterday, $stat->runTimeCoolYesterday) );
				} else {	// 114 = broke so update from run-cylces
					UpdateDailyRuntime(999);
				}
				
				echo date("Y-m-d H:i:s").": ".UpdateLink(array('callerID' => MY_DEVICE_ID, 'deviceID' => $thermostatRec['deviceID']))." My Link Updated <br/>\r\n";
	
			}
			catch( Exception $e )
			{
				echo 'Caught exception: ',  $e->getMessage(), CRLF;
				PDOError($sql, array(), $e);
			}
	}
}
//touch( '/home/fratell1/freitag.theinscrutable.us/thermo2/scripts/thermo_update_temps.end' );
?>
