<?php
//define( 'DEBUG_DB', TRUE );
if (!defined('DEBUG_DB')) define( 'DEBUG_DB', FALSE );

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
				$pdo = new PDO('mysql:host='.HA_DB_SERVER.';dbname=homeautomation;charset=utf8',HA_USER,HA_PASSWORD, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
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

	if (DEBUG_DB) echo "Fetching: ".$mysql."</br>";
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

	if (DEBUG_DB) echo "Fetched: ".$numrows." row(s)</br>";
	return (!empty($rows) ? $rows : false);
}

function FetchRows($mysql) {

	$pdo = openDB();

	if (DEBUG_DB) echo "Fetching: ".$mysql."</br>";
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

	if (DEBUG_DB) echo "Fetched: ".$numrows." row(s)</br>";
	return (!empty($rows) ? $rows : false);

}

function PDOExec($mysql) {

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

	return $rowCount;
	
}

function PDOupsert($table, $fields, $where, $debug=false) {


	$i=0;
	while (list($key, $value) = each($where)) {
		if ($i==0) {
			$sql = 'SELECT '.$key.' FROM '.$table.' WHERE  ';
		} else {
			$sql.= " AND ";
		}
		$sql.= '`'.$key.'` = "'.$value.'"';
		$i++;
	}

	// print_r(FetchRow($sql));
	if (FetchRow($sql)) {
	// echo "update";
		PDOupdate($table, $fields, $where);
	} else {
	// echo "insert";
		PDOinsert($table, $fields, $debug);
	}
}


function PDOupdate($table, $fields, $where){

	$pdo = openDB();

    $placeholder = array();

	$values = array_values($fields);
	$cols = array_keys($fields);
	$numItems = count($values);
	// print_r($cols);
	// print_r($values);

	$mysql = 'UPDATE '. $table . ' SET ';
	$i = 0;
	while (list($key, $value) = each($fields)) {
//		echo "Key: $key; Value: $value<br />\n";
		if (is_array($value)) $value = json_encode($value,JSON_UNESCAPED_SLASHES);
		if (get_magic_quotes_gpc()) { $value = stripslashes($value); }
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
	while (list($key, $value) = each($where)) {
		if ($i>0) $mysql.= " AND ";
		$mysql.= "`".$key."` = ?";
		$values[] = $value;
		$i++;
	}

//	 echo $mysql.CRLF;
//	 print_r($values);

	$result = false;

	try
	{
		$stmt = $pdo->prepare($mysql);
		$result = $stmt->execute($values);
	}
	catch( Exception $e )
	{
		PDOError($e, "PDOupdate" , $mysql, $table, $fields, $where);
		return false;
	}

//	echo $stmt->rowCount().CRLF;
	return $result;
}

function PDOinsert($table, $fields, $debug = false){
	$pdo = openDB();

    $placeholder = array();
	$values = array_values($fields);
	$cols = array_keys($fields);

    for ($i = 0; $i < count($values); $i++) $placeholder[] = '?';

	// echo "count".count($values);
	// print_r($cols);
	// print_r($values);

	
	foreach ($values AS $key => $value) {
//		echo "Key: $key; Value: $value<br />\n";
		if (is_array($value)) $values[$key] = json_encode($value, JSON_UNESCAPED_SLASHES);
		if (is_null($value)) {
			$values[$key]='NULL';
		} 
		// if (is_String($value) && strlen($value)==0) {
			// echo '>'.$value.'<'.CRLF;
			// $values[$key] = $pdo->quote($value);
			// echo '>'.$value.'<'.CRLF;
		// }
	}

	
    $mysql = 'INSERT INTO '. $table . ' (`' . implode("`, `", $cols) . '`) ';
    $mysql.= 'VALUES (' . implode(", ", $placeholder) . ')';

	// echo($mysql);

 // if ($debug) exit;
	
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

	// if ($e->errorInfo['1'] == 2006) {
		// $pdo = openDB();
		// return;
	// } else {
	echo "<pre>";
	echo "PDOError:".print_r($e);
	echo CRLF."MySql: ".$mysql.CRLF;
	// print_r($values);
	echo "</pre>";
        $command = array(
                'callerID' => 164,
                'messagetypeID' => MESS_TYPE_SCHEME,
                'schemeID'=>SCHEME_ALERT_PDO,
                'commandvalue'=> $mysql.'|'.'<pre>'.prettyPrint(json_encode($e,JSON_UNESCAPED_SLASHES)).'</pre>'
        );
        $feedback['result'][] = executeCommand($command);
	return;
}
?>
