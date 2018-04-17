DELIMITER $$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_properties`(IN `_deviceIDs` VARCHAR(255), IN `_propertyIDs` VARCHAR(255), IN `_startdate` DATETIME, IN `_enddate` DATETIME, IN `_debug` INT)
proc_label:BEGIN
    SET SESSION group_concat_max_len = (7 * 1024);
 
    SET @sql = NULL;
 
    SET @tsql = NULL;
     
    SET @tsql = CONCAT('
				SELECT GROUP_CONCAT(
								DISTINCT
									CONCAT("
										MAX(CASE WHEN p.propertyID =", p.propertyID," 
                       					AND p.deviceID =", p.deviceID," 
                       					THEN p.value ELSE ',"'"," ","'",' END) ", "`",s.description,"`", "`", d.shortdesc, "`"
										)
									)
					  INTO @sql
					  FROM ha_properties_log p 
					  JOIN ha_mi_properties s ON p.propertyID = s.id
					  JOIN ha_mf_devices d ON p.deviceID = d.id
					  WHERE p.deviceID IN (',_deviceIDs,') AND s.id IN (',_propertyIDs,') AND p.updatedate BETWEEN "',_startdate,'" AND "',_enddate,'" 
                      ORDER BY p.propertyID, p.deviceID'
	  );
    IF _debug = 1 THEN INSERT INTO `debug_msg`(`stage`,`text`) VALUES (1, @tsql); END IF;
    PREPARE tstmt FROM @tsql;
    EXECUTE tstmt;
    DEALLOCATE PREPARE tstmt;
    IF (@sql IS NULL) THEN
    	SELECT 1 as id, DATE_FORMAT(NOW(), "%Y-%m-%d %H:%i:%s") as Date, 1 as `No Results Found` ;
    	LEAVE proc_label;
    END IF;

    IF _debug = 1 THEN INSERT INTO `debug_msg`(`stage`,`text`) VALUES (2, @sql); END IF;
     
    SET @sql = CONCAT(
                 'SELECT max(p.id) as id, DATE_FORMAT(p.updatedate, "%Y-%m-%d %H:%i:%s") as Date, ', 
                    @sql,  
                  ' FROM ha_properties_log p JOIN ha_mf_devices d
                      ON p.deviceID = d.id 
                    WHERE deviceID IN (', _deviceIDs,')', ' AND p.propertyID IN (', _propertyIDs,')', ' AND 
        			p.updatedate BETWEEN "', _startdate,'" AND "', _enddate, '"'
                  ' GROUP BY DATE_FORMAT(p.updatedate, "%Y-%m-%d %H:%i:%s")
                    ORDER BY Date');
    IF _debug = 1 THEN INSERT INTO `debug_msg`(`stage`,`text`) VALUES (3,@sql); END IF;
    
    PREPARE stmt FROM @sql;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
 
END$$
DELIMITER ;
