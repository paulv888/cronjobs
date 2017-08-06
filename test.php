<?php
require_once 'includes.php';

$mysql = 'select * from ha_mf_devices WHERE id = 302';

$row = FetchRow($mysql);
var_dump($row);

echo "sleep...".CRLF;
sleep(10);

$row = FetchRows($mysql);
print_r($row);

$row = FetchRows($mysql);
print_r($row);

//require_once '/home/www/cronjobs/70D455DC-ACB4-4525-8A85-E6009AE93AF4/includes.php'; 
//loadRemote(20);

// $params['commandvalue'] = 'insert into ha_alerts (deviceID, description) VALUES (302, "hello")';
//print_r(executeQuery($params));

?>
