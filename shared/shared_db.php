<?php
//define( 'DEBUG_DB', TRUE );
if (!defined('DEBUG_DB')) define( 'DEBUG_DB', FALSE );

function openDB() {

	static $pdo = Null;

	$retries = 60;
	if (empty($pdo)) {
		while ($retries > 0)
		{
			try
			{
				$pdo = new PDO('mysql:host=vlosite-16;dbname=homeautomation;charset=utf8',HA_USER,HA_PASSWORD, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES, false));
				$retries = 0;
			}
			catch (PDOException $e)
			{
				// Should probably check $e is a connection error, could be a query error!
				print_r($e);exit;
				echo ".";
				$retries--;
				sleep(1);
			}
		}
	}
	
	return $pdo;
}

function FetchRow($mysql) {

	$pdo = openDB();

	if (DEBUG_DB) echo "Fetching: ".$mysql."</br>";
 	try
	{
		//$mysql = $pdo->quote($mysql);
		if (!$res_row = $pdo->query($mysql)) {
			mySqlError($mysql); 
			return false;
		}
	} 
	catch( Exception $e )
	{
		PDOError($mysql, array(), $e);
		return false;
	}

 	try
	{
		$rows = $res_row->fetch(PDO::FETCH_ASSOC);
	} 
	catch( Exception $e )
	{
		PDOError($mysql, $values, $e);
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
	} 
	catch( Exception $e )
	{
	    die(json_encode(array('outcome' => false)));

		PDOError($mysql, array(), $e);
		return false;
	}

 	try
	{
		$rows = $res_row->fetchAll(PDO::FETCH_ASSOC);
	} 
	catch( Exception $e )
	{
		PDOError($mysql, $values, $e);
		return false;
	}

	if (DEBUG_DB) echo "Fetched: ".$numrows." row(s)</br>";
	return (!empty($rows) ? $rows : false);

}

// function CopyRow($my_table,$where,$posid) {
	// $mysql="SELECT * FROM  ".$my_table.
			// " WHERE ".$where;
	// $res_row = mysql_query($mysql) ;
	// if ($res_row) {
		// while ($row=mysql_fetch_assoc($res_row)) {
			// unset($row['id']);
			// $row['posid']=$posid;
			// mysql_insert_assoc($my_table,$row);
		// }
	// }
// }

function executeQuery($params) {

	$pdo = openDB();		

	$mysql = $params['commandvalue'];
	$mysql=str_replace("{DEVICE_SOMEONE_HOME}",DEVICE_SOMEONE_HOME,$mysql);
	$mysql=str_replace("{DEVICE_ALARM_ZONE1}",DEVICE_ALARM_ZONE1,$mysql);
	$mysql=str_replace("{DEVICE_ALARM_ZONE2}",DEVICE_ALARM_ZONE2,$mysql);
	$mysql=str_replace("{DEVICE_DARK_OUTSIDE}",DEVICE_DARK_OUTSIDE,$mysql);
	$mysql=str_replace("{DEVICE_PAUL_HOME}",DEVICE_PAUL_HOME,$mysql);


 	try
	{
		$rowCount = $pdo->exec($mysql);
	} 
	catch( Exception $e )
	{
		PDOError($mysql, array(), $e);
		return false;
	}

	$feedback['result'] =  $rowCount ." Rows affected";
	return $feedback;
	
}
   
function PDOupsert($table, $fields, $where) {


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
//	echo "Up: ".$sql.CRLF;
	if (FetchRow($sql)) {
		PDOupdate($table, $fields, $where);
	} else {
		PDOinsert($table, $fields, true);
	}
}


function PDOupdate($table, $fields, $where){

//$sql = "UPDATE {$dbConfig['table_prefix']}hvac_status SET date = ?, start_date_heat = ?, start_date_cool = ?, start_date_fan = ?, heat_status = ?, cool_status = ?, fan_status = ? WHERE deviceID = ?";
//$queryUpdate = $pdo->prepare( $sql );
//$queryUpdate->execute( array( $now, $newStartDateHeat, $newStartDateCool, $newStartDateFan, $heatStatus, $coolStatus, $fanStatus, $thermostatRec['deviceID'] ) );

	$pdo = openDB();

    $placeholder = array();

	$values = array_values($fields);
	$cols = array_keys($fields);
	
	$numItems = count($values);
	// print_r($cols);
	// print_r($values);
	
	$sql = 'UPDATE '. $table . ' SET ';
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
		$sql .= "`".$key."` = ?"; 
		if(++$i < $numItems) {
			$sql .= ", ";
		}
    }
  
  
	$sql .= ' WHERE ';
	$i = 0;
	while (list($key, $value) = each($where)) {
		if ($i>0) $sql.= " AND ";
		$sql.= "`".$key."` = ?";
		$values[] = $value;
		$i++;
	}

	// echo "sql sofar: ". $sql.CRLF;
	// print_r($values);

	$result = false;

	try
	{
		$stmt = $pdo->prepare($sql);
		$result = $stmt->execute($values);
	}
	catch( Exception $e )
	{
		PDOError($sql, $values, $e);
		return false;
	}

	return $result;
}

function PDOinsert($table, $fields){
	$pdo = openDB();

    $placeholder = array();
	$values = array_values($fields);
	$cols = array_keys($fields);
	
    for ($i = 0; $i < count($values); $i++) $placeholder[] = '?';

	//echo "count".count($values);
	//print_r($cols);
	//print_r($values);
	
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

	
    $sql = 'INSERT INTO '. $table . ' (`' . implode("`, `", $cols) . '`) ';
    $sql.= 'VALUES (' . implode(", ", $placeholder) . ')';

	// echo "sql sofar: ". $sql.CRLF;
	// print_r($values);
	
 	try
	{
		$stmt = $pdo->prepare($sql);
		$stmt->execute($values);
		return $pdo->lastInsertId();
	} 
	catch( Exception $e )
	{
		PDOError($sql, $values, $e);
		return false;
	}

}

function PDOExec($mysql) {

	$pdo = openDB();		

 	try
	{
		$rowCount = $pdo->exec($mysql);
	} 
	catch( Exception $e )
	{
		PDOError($mysql, array(), $e);
		return false;
	}

	return $rowCount;
	
}

function PDOError($mysql, $values, $e) {

	if ($e->getCode() == "HY000" || $e->getCode() == "08S01") {
		$retry = 5;
		$pdo = null;
		while ($retry-- > 0) {
			echo "Trying to reconnect PDO...\n";
			try {
				$pdo = new PDO( HA_DSN, HA_USER, HA_PASSWORD , array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
				return $pdo;
			}
			catch(PDOException $ex){
				sleep(10);
			}
		}
		echo ('Lost connection PDO, exiting...\n');
		throw(e);
	} else {
		echo "<pre>";
		echo "Error on: ".$mysql."<br/>\r\n";
		echo $e->getCode(). ": " . $e->getMessage() . "<br/>\r\n";
		print_r($values);
		echo "</pre>";
	}
}
?>
