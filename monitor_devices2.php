<?php
require_once 'includes.php';

define("MY_DEVICE_ID", 211);

echo monitorDevices("POLL2");
echo UpdateLink(MY_DEVICE_ID)." My Link Updated <br/>\r\n";
?>
