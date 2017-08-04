<?php
function openDB() {

	static $pdo = Null;

	$dbConfig = array(
		'dsn'          => 'mysql:host=vlosite-16;dbname=homeautomation;charset=utf8',
		'username'     => HA_USER,
		'password'     => HA_PASSWORD,
		'table_prefix' => ''
	);

	$retries = 60;
	if (!empty($pdo)) {
		while ($retries > 0)
		{
			try
			{
				$pdo = new PDO( $dbConfig['dsn'], $dbConfig['username'], $dbConfig['password'] );
				// Do query, etc.
				$retries = 0;
			}
			catch (PDOException $e)
			{
				// Should probably check $e is a connection error, could be a query error!
				echo ".";
				$retries--;
				sleep(1);
			}
		}
	}
	$pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
	
	return $pdo;
}
?>
