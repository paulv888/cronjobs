<?php
//define( 'DEBUG_VALUES', TRUE );
if (!defined('DEBUG_VALUES')) define( 'DEBUG_VALUES', FALSE );

function updateValuesNew() {

	if (DEBUG_VALUES) echo "<pre>";

	// Rebuild from mongoDB_users
	$text = "";
	$text.= date("Y-m-d H:i:s").": Truncate table mc_usage".CRLF;
	$mysql = 'TRUNCATE TABLE `mc_usage`';
	if (!mysql_query($mysql)){
					mySqlError($mysql);
					return false;
	}
	
	// Set domain to determine user type
	$mysql = 'UPDATE `mongoDB_users` SET `domain`=substring(`email`, LOCATE("@", `email`)+1)';
	if (!$result = mysql_query($mysql)){
					mySqlError($mysql);
					return false;
	}
	$text.= date("Y-m-d H:i:s").": ".mysql_affected_rows()." Rows updated in mongoDB_users".CRLF;

	// Reset logins (not sales
	$mysql = 'UPDATE `mongoDB_users` SET logins_period=0 WHERE sales=0';
	if (!$result = mysql_query($mysql)){
					mySqlError($mysql);
					return false;
	}
	$text.= date("Y-m-d H:i:s").": ".mysql_affected_rows()." Rows updated in mongoDB_users".CRLF;
	
	
	// Add new domains to  domain table
	$mysql = 'INSERT INTO mongoDB_user_domains (`domain`)
			SELECT `a`.`domain`
			FROM   mongoDB_users a
			LEFT OUTER JOIN mongoDB_user_domains b
			  ON (a.domain = b.domain)
			  WHERE b.id IS NULL
			GROUP BY `a`.`domain`';
	if (!$result = mysql_query($mysql)){
					mySqlError($mysql);
					return false;
	}
	$text.= date("Y-m-d H:i:s").": ".mysql_affected_rows()." Rows inserted in mongoDB_user_domain".CRLF;
	
	// Setup user type based on domain table
	$mysql = 'UPDATE `mongoDB_users` a LEFT JOIN mongoDB_user_domains b ON a.domain = b.domain SET a.user_type = b.user_type;';
	if (!$result = mysql_query($mysql)){
					mySqlError($mysql);
					return false;
	}
	$text.= date("Y-m-d H:i:s").": ".mysql_affected_rows()." Rows updated in mongoDB_users".CRLF;

	// Overwrite any special cases based on email
	$mysql = 'UPDATE `mongoDB_users` a INNER JOIN mongoDB_user_email b ON a.email = b.email SET a.user_type = b.user_type;';
	if (!$result = mysql_query($mysql)){
					mySqlError($mysql);
					return false;
	}
	$text.= date("Y-m-d H:i:s").": ".mysql_affected_rows()." Rows updated in mongoDB_users".CRLF;

	// Overwrite where no commerce customer
	$mysql = 'UPDATE `mongoDB_users` a SET a.user_type =3 WHERE user_type =4 AND commerce_customer = ""';
	if (!$result = mysql_query($mysql)){
					mySqlError($mysql);
					return false;
	}
	$text.= date("Y-m-d H:i:s").": ".mysql_affected_rows()." Rows updated in mongoDB_users".CRLF;

	// Set main product
	$mysql = 'UPDATE mongoDB_users SET main_productID = 
			CASE 	WHEN commerce =1 THEN "1" 
					WHEN sales =1 	THEN "3" 
					WHEN ticket =1 THEN "4" 
					WHEN hauler =1 THEN "5" 
					WHEN supply =1 THEN "6" 
					ELSE "99" END';		// Set Main product, no stats per product, might go wrong when 2 rows per username
	if (!$result = mysql_query($mysql)){
					mySqlError($mysql);
					return false;
	}
	$text.= date("Y-m-d H:i:s").": ".mysql_affected_rows()." Rows updated in mongoDB_users".CRLF;
	
	
	//---- Not subbing seems ok (for first rows) followed by above?
	// Create rows for first months 
	$mysql = 'CREATE TEMPORARY TABLE IF NOT EXISTS tempTable AS (
			SELECT t.id, t.period, t.company, t.user_type, t.main_productID, t.totalLogins,  IF(t.period_prec IS NULL,t.totalLogins,0) as logins_period
			FROM (
			  SELECT t1.id, t1.period, t1.company,t1.user_type, t1.main_productID, t1.userName, t1.totalLogins, MAX(t2.period) period_prec
			  FROM
				mongoDB_users t1 LEFT JOIN mongoDB_users t2
				ON t1.userName=t2.userName AND t1.main_productID = t2.main_productID AND t1.period>t2.period
			  GROUP BY
				t1.period, t1.userName, t1.totalLogins) t INNER JOIN mongoDB_users tp
			  ON t.userName=tp.userName AND t.main_productID = tp.main_productID
			WHERE t.period_prec is null);';
	if (!$result = mysql_query($mysql)){
					mySqlError($mysql);
					return false;
	}
	$text.= date("Y-m-d H:i:s").": ".mysql_affected_rows()." Rows inserted in tempTable".CRLF;


	// Update mongoUsers based on calculations (not for sales)
	$mysql = 'UPDATE mongoDB_users t1 INNER JOIN tempTable t2 ON t1.id=t2.id SET t1.logins_period=t2.logins_period WHERE sales=0;';
	if (!$result = mysql_query($mysql)){
					mySqlError($mysql);
					return false;
	}
	$text.= date("Y-m-d H:i:s").": ".mysql_affected_rows()." Rows updated in mongoDB_users".CRLF;

	$mysql = 'DROP TABLE tempTable;';
	if (!$result = mysql_query($mysql)){
					mySqlError($mysql);
					return false;
	}
	$text.= date("Y-m-d H:i:s").": ".mysql_affected_rows()." Rows updated in mongoDB_users".CRLF;

	
	// Calculate logins based on Difference between total and prev total
	$mysql = 'CREATE TEMPORARY TABLE IF NOT EXISTS tempTable AS 
			(SELECT t.id, t.period, t.company, t.user_type, t.main_productID, t.totalLogins, if(t.totalLogins-tp.totalLogins<0,t.totalLogins,t.totalLogins-tp.totalLogins) as logins_period
			FROM (
			  SELECT t1.id, t1.period, t1.company,t1.user_type, t1.main_productID, t1.userName, t1.totalLogins, IFNULL(MAX(t2.period),0) period_prec
			  FROM
				mongoDB_users t1 INNER JOIN mongoDB_users t2
				ON t1.userName=t2.userName AND t1.main_productID = t2.main_productID AND t1.period>t2.period
			  GROUP BY
				t1.period, t1.userName, t1.totalLogins) t INNER JOIN mongoDB_users tp
			  ON t.userName=tp.userName AND t.main_productID = tp.main_productID  and t.period_prec=tp.period );';
	if (!$result = mysql_query($mysql)){
					mySqlError($mysql);
					return false;
	}
	$text.= date("Y-m-d H:i:s").": ".mysql_affected_rows()." Rows inserted in tempTable".CRLF;

	// And store in mongoDB
	$mysql = 'update mongoDB_users t1 inner join tempTable t2 on t1.id=t2.id set t1.logins_period=t2.logins_period WHERE sales=0;';
	if (!$result = mysql_query($mysql)){
					mySqlError($mysql);
					return false;
	}
	$text.= date("Y-m-d H:i:s").": ".mysql_affected_rows()." Rows updated in mongoDB_users".CRLF;
	
	$mysql = 'INSERT INTO `mc_usage`(`period`, `company`,`user_type`,`main_productID`,  `logins_period` , `total_logins`)
							SELECT  period,
									company,
									user_type,
									main_productID,
									sum(logins_period) AS `logins_period`,
									sum(totalLogins) AS `total_logins`
							FROM    mongoDB_users
							GROUP  by period,
									company,
									user_type,
									main_productID;';
	if (!$result = mysql_query($mysql)){
					mySqlError($mysql);
					return false;
	}
	$text.= date("Y-m-d H:i:s").": ".mysql_affected_rows()." Rows updated in mc_usage".CRLF;

	return $text;
	if (DEBUG_VALUES) echo "</pre>";
}?>
