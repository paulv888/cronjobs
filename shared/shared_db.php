<?php
//define( 'DEBUG_DB', TRUE );
if (!defined('DEBUG_DB')) define( 'DEBUG_DB', FALSE );

// Future logging method
function doError( $msg )
{
   echo $msg . "\n";
   file_put_contents( 'php://stderr', $msg . "<br/>\r\n" );
}


function mysql_insert_assoc ($my_table, $my_array) {
     //
   // Insert values into a MySQL database
   // Includes quote_smart code to foil SQL Injection
   //
   // A call to this function of:
   //
   //  $val1 = "foobar";
   //  $val2 = 495;
   //  mysql_insert_assoc("tablename", array(col1=>$val1, col2=>$val2, col3=>"val3", col4=>720, col5=>834.987));
   //
   // Sends the following query:
   //  INSERT INTO 'tablename' (col1, col2, col3, col4, col5) values ('foobar', 495, 'val3', 720, 834.987)
   //
 
		global $mysql_link;
      
       // Find all the keys (column names) from the array $my_array
       $columns = array_keys($my_array);

       // Find all the values from the array $my_array
       $values = array_values($my_array);
      
       // quote_smart the values
       $values_number = count($values);
       for ($i = 0; $i < $values_number; $i++) {
         	$value = $values[$i];
			if (is_array($value)) $value = json_encode($value);
         	if (get_magic_quotes_gpc()) { $value = stripslashes($value); }
          
         	if (is_null($value)) {
         		$value="NULL";
         	} elseif (strlen($value)==0) {
         		$value="'"."'";
         	} elseif (!is_numeric($value)) {
         	//	$value = "'" . mysql_real_escape_string($value, $db_link) . "'";
         		$value = "'" . $value . "'";
         	}
         	$values[$i] = $value;
         }
        
       // Compose the query
       $sql = "INSERT INTO $my_table ";

       // create comma-separated string of column names, enclosed in parentheses
       $sql .= "(`" . implode("`, `", $columns) . "`)";
       $sql .= " values ";

       // create comma-separated string of values, enclosed in parentheses
       $sql .= "(" . implode(", ", $values) . ")";
      
//       $result = @mysql_query ($sql) OR die ("<br />\n<span style=\"color:red\">Query: $sql UNsuccessful :</span> " . mysql_error() . "\n<br />");
		if (!mysql_query($sql)) {
			mySqlError($sql);
			return false;
		}
		return true;
}


function FetchRow($mysql) {
	if (DEBUG_DB) echo "Fetching: ".$mysql."</br>";
	if (!$res_row = mysql_query($mysql)) {
		mySqlError($mysql); 
		return false;
	}
	if (!$numrows = mysql_num_rows($res_row)) {
//		echo "0 Rows returned".CRLF; 
		return false;
	}
	if (!$rows = mysql_fetch_assoc($res_row)) {
		mySqlError($mysql); 
		return false;
	}
	if (DEBUG_DB) echo "Fetched: ".$numrows." row(s)</br>";
	return $rows;
}

function FetchRows($mysql) {
	if (DEBUG_DB) echo "Fetching: ".$mysql."</br>";
	$result = array();
	if (!$res_row = mysql_query($mysql)) {
		mySqlError($mysql); 
		return false;
	}
	if (!$numrows = mysql_num_rows($res_row)) {
//		echo "0 Rows returned".CRLF; 
		return false;
	}
	if (!$rows = mysql_fetch_assoc($res_row)) {
		mySqlError($mysql); 
		return false;
	}
	$result[] = $rows;
	while ($rows = mysql_fetch_assoc($res_row)) {
			$result[] = $rows;
	}
	if (DEBUG_DB) echo "Fetched: ".$numrows." row(s)</br>";
	return $result;
}
   
function CopyRow($my_table,$where,$posid) {
	$mysql="SELECT * FROM  ".$my_table.
			" WHERE ".$where;
	$res_row = mysql_query($mysql) ;
	if ($res_row) {
		while ($row=mysql_fetch_assoc($res_row)) {
			unset($row['id']);
			$row['posid']=$posid;
			mysql_insert_assoc($my_table,$row);
		}
	}
}

function RunQuery($mysql) {
	$mysql=str_replace("{DEVICE_SOMEONE_HOME}",DEVICE_SOMEONE_HOME,$mysql);
	$mysql=str_replace("{DEVICE_ALARM_ZONE1}",DEVICE_ALARM_ZONE1,$mysql);
	$mysql=str_replace("{DEVICE_ALARM_ZONE2}",DEVICE_ALARM_ZONE2,$mysql);
	$mysql=str_replace("{DEVICE_DARK_OUTSIDE}",DEVICE_DARK_OUTSIDE,$mysql);
	$mysql=str_replace("{DEVICE_PAUL_HOME}",DEVICE_PAUL_HOME,$mysql);
	$res = mysql_query($mysql);
	if (!$res) {
		mySqlError($mysql); 
		return false;
	}
	return true;
}
   
function mySqlError($mysql) {
		global $mysql_link;
		global $dbConfig;
		
		if (mysql_errno($mysql_link)==2006) {
			$retry = 5;
			while ($retry-- > 0) {
				echo "Trying to reconnect...\n";
				sleep (10);
				mysql_close($mysql_link);
				$mysql_link = mysql_connect($dbConfig['server'], $dbConfig['username'], $dbConfig['password']);
				if (mysql_select_db($dbConfig['database'],$mysql_link)) return true;
			}
			echo 'Lost connection, exiting...\n';
			exit;
		} else {
			echo "Error on: ".$mysql."<br/>\r\n";
			echo mysql_errno($mysql_link) . ": " . mysql_error($mysql_link) . "<br/>\r\n";
		}
}

function PDOError($mysql, $values, $e) {
	global $pdo;		
	global $dbConfig;		

	if ($e->getCode() == "HY000" || $e->getCode() == "08S01") {
		$retry = 5;
		$pdo = null;
		while ($retry-- > 0) {
			echo "Trying to reconnect PDO...\n";
			try {
				$pdo = new PDO( $dbConfig['dsn'], $dbConfig['username'], $dbConfig['password'] , array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
				return true;
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

function PDOupsert($table, $fields, $where) {

	while (list($key, $value) = each($where)) {
		$sql = 'SELECT '.$key.' FROM '.$table.' WHERE `'.$key.'` = "'.$value.'"';
	}
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

	global $pdo;

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
		if (is_array($value)) $value = json_encode($value);
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
	while (list($key, $value) = each($where)) {
		$sql .= "`".$key."` = ?";
		$values[] = $value;
	}

//	echo "sql sofar: ". $sql.CRLF;
//	print_r($values);

	$result = false;

	try
	{
		$stmt = $pdo->prepare($sql);
		$result = $stmt->execute($values);
	}
	catch( Exception $e )
	{
		PDOError($sql, $values, $e);
	}

	return $result;
}

function PDOinsert($table, $fields){
	global $pdo;

    $placeholder = array();
	$values = array_values($fields);
	$cols = array_keys($fields);
	
    for ($i = 0; $i < count($values); $i++) $placeholder[] = '?';

	//echo "count".count($values);
	//print_r($cols);
	//print_r($values);
	
	foreach ($values AS $key => $value) {
//		echo "Key: $key; Value: $value<br />\n";
		if (is_array($value)) $values[$key] = json_encode($value);
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
	} 
	catch( Exception $e )
	{
		PDOError($sql, $values, $e);
	}

}
?>
