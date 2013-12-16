<?php
include_once 'includes/monitor_devices.php';

define("MY_DEVICE_ID", 128);

echo monitorDevices();
echo UpdateMylink(MY_DEVICE_ID)." My Link Updated <br/>\r\n";
?>