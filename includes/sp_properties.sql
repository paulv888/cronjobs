DROP PROCEDURE `sp_properties`;
DELIMITER $$
CREATE PROCEDURE sp_properties(IN _deviceIDs VARCHAR(255), IN _propertyIDs VARCHAR(255), IN _startdate DATETIME, IN _enddate DATETIME, IN _debug INT)
BEGIN
    SET SESSION group_concat_max_len = (7 * 1024);

    SET @sql = NULL;

    SET @tsql = NULL;
	
	SET @tsql = CONCAT('SELECT GROUP_CONCAT(DISTINCT
             CONCAT("
               MAX(CASE WHEN p.propertyID = ", p.propertyID,
                " THEN p.value ELSE ',"'"," ","'",' END) ", "`", description,"`"))
      INTO @sql
      FROM ha_properties_log p JOIN ha_mi_properties s 
      ON p.propertyID = s.id
      WHERE deviceID IN (',_deviceIDs,') AND s.id IN (',_propertyIDs,') AND p.updatedate BETWEEN "',_startdate,'" AND "',_enddate,'"');
	IF _debug = 1 THEN INSERT INTO `debug_msg`(`text`) VALUES (@tsql); END IF;
    PREPARE tstmt FROM @tsql;
    EXECUTE tstmt;
	DEALLOCATE PREPARE tstmt;
	IF _debug = 1 THEN INSERT INTO `debug_msg`(`text`) VALUES (@sql); END IF;
	
    SET @sql = CONCAT(
                 'SELECT p.id, p.deviceID, concat(DATE_FORMAT(p.updatedate, "%Y-%m-%d")," ", SEC_TO_TIME(FLOOR((TIME_TO_SEC(p.`updatedate`)+150)/300)*300)) as mdate, ', 
					@sql,  
                  ' FROM ha_properties_log p JOIN ha_mf_devices d
                      ON p.deviceID = d.id 
                    WHERE deviceID IN (', _deviceIDs,')', ' AND p.propertyID IN (', _propertyIDs,')', ' AND p.updatedate BETWEEN "', _startdate,'" AND "', _enddate, '"' 
				  ' GROUP BY DATE_FORMAT(p.updatedate, "%Y-%m-%d") ,SEC_TO_TIME(FLOOR((TIME_TO_SEC(p.`updatedate`)+150)/300)*300)
					ORDER BY mdate DESC');
    PREPARE stmt FROM @sql;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;

END$$
DELIMITER ;

call sp_properties( '60,114', '138,126', "2015-09-25 00:00:00", "2015-09-27 23:59:59", 1) 
call sp_properties( '60,114,201', '138,126,123,127,124', "2015-09-25 00:00:00", "2015-09-27 23:59:59", 1) 
