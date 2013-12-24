<?php 
require 'connect-db.php';
include_once 'defines.php';
include_once 'myclasses/TCPClient.php';
include_once 'includes/shared_db.php';
include_once 'includes/shared_file.php';
include_once 'includes/shared_ha.php';
include_once 'includes/shared_gen.php';
include_once 'includes/insteon_logger.php';

define("MY_DEVICE_ID", 137);
define("INSTEON_HUB", 109);

echo UpdateMylink(MY_DEVICE_ID)." My Link Updated <br/>\r\n";

define( 'MYDEBUG', FALSE );

$mysql = 'SELECT `ip`, `name`, `pingport` FROM `ha_mf_devices` d '.
		 ' JOIN `ha_mf_device_ipaddress` i ON d.ipaddressID = i.id '.
		 ' JOIN `ha_mf_monitor_link` l i ON l.deviceID = d.id '.
		 ' WHERE d.`id` = '.INSTEON_HUB;

if (!$res = mysql_query($mysql)) {
	mySqlError($mysql);
	exit;
}
$row = mysql_fetch_assoc($res);


if (OpenTCP($row['ip'], $row['pingport'])) {
	while (true) {
		$result=ReadTCP();
		usleep(1000);
		echo plm_decode($result);
	}
}
?>