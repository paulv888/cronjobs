<?php
$dbConfig = array(
	'server'       => 'vlosite',
	'database'     => 'homeautomation',
	'dsn'          => 'mysql:host=vlosite;dbname=homeautomation',
	'username'     => HA_USER,
	'password'     => HA_PASSWORD,
	'table_prefix' => ''             // Prefix to attach to all table/procedure names to make unique in unknown environment.
	// DO make this prefix DIFFERENT than you used for version 1 (if you had the old code installed)

);
# wait until mysql started
$mysql_link = false;
$retries = 60;
while (!$mysql_link and $retries) {
	if (!$mysql_link=mysql_connect($dbConfig['server'], $dbConfig['username'], $dbConfig['password'])) {
		echo ".";
		$retries--;
		sleep(1);
	}
}
if (!$mysql_link) die(mysql_error());
mysql_select_db($dbConfig['database'],$mysql_link) or die(mysql_error());
$pdo = new PDO( $dbConfig['dsn'], $dbConfig['username'], $dbConfig['password'] );
$pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
?>
