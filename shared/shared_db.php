<?php
function openDB() {

	static $pdo = null;

	if (!empty($pdo)) {
		try
		{
			$status = $pdo->query("SELECT 1");
			$connected = true;
		}
		catch (PDOException $e)
		{
			$connected = false;
		}
	} else {
		$connected = false;
	}

	$retries = 60;
	if (!$connected) {
		while ($retries > 0)
		{
			try
			{
				$pdo = new PDO('mysql:host='.HA_DB_SERVER.';dbname='.HA_DATABASE.';charset=utf8',HA_USER,HA_PASSWORD, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
				$retries = 0;
				return $pdo;
			}
			catch (PDOException $e)
			{
				echo ".";
				error_log($e->getMessage());
				$retries--;
				sleep(1);
			}
		}
	        die($e->getMessage());
	}
	return $pdo;
}

function FetchRow($mysql) {

	$pdo = openDB();

	debug($mysql, 'mysql');
 	try
	{
		$res_row = $pdo->query($mysql);
		$rows = $res_row->fetch(PDO::FETCH_ASSOC);
	} 
	catch( Exception $e )
	{
		PDOError($e, "FetchRow", $mysql);
		return false;
	}

	debug($rows, 'rows');
	return (!empty($rows) ? $rows : false);
}

function FetchRows($mysql) {

	$pdo = openDB();

	debug($mysql, 'mysql');
	$result = array();

 	try
	{
		$res_row = $pdo->query($mysql);
		$rows = $res_row->fetchAll(PDO::FETCH_ASSOC);
	} 
	catch( Exception $e )
	{
		PDOError($e, "FetchRows", $mysql);
		return false;
	}

	debug($rows, 'rows');
	return (!empty($rows) ? $rows : false);

}

function PDOExec($mysql) {

	debug($mysql, 'mysql');

	$pdo = openDB();		

 	try
	{
		$rowCount = $pdo->exec($mysql);
	} 
	catch( Exception $e )
	{
		PDOError($e, "PDOExec", $mysql);
		return false;
	}

	debug($rowCount, 'rowCount');
	return $rowCount;
	
}

function PDOUpsert($table, $fields, $where) {

	debug($fields, $table.' fields');
	debug($where, $table.' where');

	$pdo = openDB();

	$i=0;
	foreach( $where as $key => $value ){
		if ($i==0) {
			$mysql = 'SELECT '.$key.' FROM '.$table.' WHERE  ';
		} else {
			$mysql.= " AND ";
		}
		$mysql.= '`'.$key.'` = "'.$value.'"';
		$i++;
	}

	debug($mysql, 'mysql');
	
	if (FetchRow($mysql)) {
		PDOupdate($table, $fields, $where);
	} else {
		PDOinsert($table, $fields);
	}
}


function PDOUpdate($table, $fields, $where){

	debug($fields, $table.' fields');
	debug($where, $table.' where');

	$pdo = openDB();

    $placeholder = array();

	$values = array_values($fields);
	$cols = array_keys($fields);
	$numItems = count($values);

	$mysql = 'UPDATE '. $table . ' SET ';
	$i = 0;
	foreach( $fields as $key => $value ){
//		echo "Key: $key; Value: $value<br />\n";
		if (is_array($value)) $value = json_encode($value,JSON_UNESCAPED_SLASHES);
		// if (get_magic_quotes_gpc()) { $value = stripslashes($value); }
		if (is_null($value)) {
			$value="NULL";
		} elseif (strlen($value)==0) {
			$value="'"."'";
		} elseif (!is_numeric($value)) {
			$value = "'" . $value . "'";
		}
		$mysql .= "`".$key."` = ?"; 
		if(++$i < $numItems) {
			$mysql .= ", ";
		}
    	}

	$mysql .= ' WHERE ';
	$i = 0;
	foreach( $where as $key => $value ){
		if ($i>0) $mysql.= " AND ";
		$mysql.= "`".$key."` = ?";
		$values[] = $value;
		$i++;
	}

	debug($mysql, 'mysql');
	debug($values, 'values');

	$result = false;

	try
	{
		$stmt = $pdo->prepare($mysql);
		$result = $stmt->execute($values);
		if ($result) $result = $stmt->rowCount();
	}
	catch( Exception $e )
	{
		PDOError($e, "PDOupdate" , $mysql, $table, $fields, $where);
		return false;
	}

	return $result;
}

function PDOInsert($table, $fields){

	debug($fields, $table.' fields');

	$pdo = openDB();

    $placeholder = array();
	$values = array_values($fields);
	$cols = array_keys($fields);

    for ($i = 0; $i < count($values); $i++) $placeholder[] = '?';

	foreach ($values AS $key => $value) {
		if (is_array($value)) $values[$key] = json_encode($value, JSON_UNESCAPED_SLASHES);
		if (is_null($value)) {
			$values[$key]='NULL';
		} 
	}	
	
    $mysql = 'INSERT INTO '. $table . ' (`' . implode("`, `", $cols) . '`) ';
    $mysql.= 'VALUES (' . implode(", ", $placeholder) . ');';

	debug($mysql, 'mysql');
	debug($values, 'values');
	
 	try
	{
		$stmt = $pdo->prepare($mysql);
		$stmt->execute($values);
		return $pdo->lastInsertId();
	} 
	catch( Exception $e )
	{
		PDOError($e, "PDOinsert", $mysql, $table, $fields);
		return false;
	}

}

function PDOError($e, $function, $mysql) {

	echo "<pre>";
	echo "PDOError:".print_r($e);
	echo CRLF."MySql: ".$mysql.CRLF;
	echo "</pre>";
	$command = array(
			'callerID' => 164,
			'messagetypeID' => MESS_TYPE_SCHEME,
			'schemeID'=>SCHEME_ALERT_PDO,
			'commandvalue'=> substr($mysql, 0, 30).'| SQL: '.$mysql.'ERROR: <pre>'.prettyPrint(json_encode($e,JSON_UNESCAPED_SLASHES)).'</pre>'
	);
	$feedback['result'][] = executeCommand($command);
	return;
}
?>
