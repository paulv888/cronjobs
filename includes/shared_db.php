<?php
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
   $res_row = mysql_query($mysql) ;
	if ($res_row) {
		return mysql_fetch_array($res_row);
	} else {
		return false;
	}
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
		exit;
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
				if (mysql_select_db($dbConfig['database'],$mysql_link)) return 1;
			}
			echo 'Lost connection, exiting...\n';
			exit;
		} else {
			echo "Error on: ".$mysql."<br/>\r\n";
			echo mysql_errno($mysql_link) . ": " . mysql_error($mysql_link) . "<br/>\r\n";
		}
}

?>
