<?php
require_once 'includes.php';

define("MY_DEVICE_ID", 127);

echo deviceList();
echo UpdateLink(MY_DEVICE_ID)." My Link Updated <br/>\r\n";

?>