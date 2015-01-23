<?php
require_once 'includes.php';

define("MY_DEVICE_ID", 211);

$counter = 0;
while ($counter++ < 5) {
	echo monitorDevices("POLL2");
	sleep (9);
}
echo UpdateLink(MY_DEVICE_ID)." My Link Updated <br/>\r\n";
?>
