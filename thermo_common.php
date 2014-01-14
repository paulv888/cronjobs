<?php
//require 'thermo_config.php';

// Future logging method
function logIt( $msg )
{
   echo $msg . "<br/>\r\n";
}

// Future logging method
function doError( $msg )
{
   echo $msg . "\n";
   file_put_contents( 'php://stderr', $msg . "<br/>\r\n" );
}

// Common code that should run for EVERY page follows here

global $timezone;

// Set timezone for all PHP functions
//date_default_timezone_set( $timezone );

// Always connect to the database, don't wait for a request to connect
$pdo = new PDO( $dbConfig['dsn'], $dbConfig['username'], $dbConfig['password'] );
$pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );

// Set timezone for all MySQL functions
//$pdo->exec( "SET time_zone = '$timezone'" );    // Like old one

// Get list of thermostats
try
{
  $thermostats = array();
  $sql = "SELECT * FROM `ha_mf_devices` inner join ha_mf_devices_thermostat as t ON ". 
  		  "t.deviceID=`ha_mf_devices`.`id` inner join ha_mf_device_links AS l ON devicelink=l.id " .
  		  "WHERE typeID=".DEV_TYPE_HEAT." OR typeID=" . DEV_TYPE_COOL;
  foreach( $pdo->query($sql) as $row )
  {
      $thermostats[$row['deviceID']] = $row;
  }
}
catch( Exception $e )
{
  logIt( "Error getting thermostat list" );
}
?>