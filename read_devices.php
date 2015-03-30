<?php
require_once 'includes.php';

define("MY_DEVICE_ID", 127);
$a= (deviceList() < 0 ? LINK_DOWN : LINK_UP) ;
echo UpdateLink(MY_DEVICE_ID, $a)." My Link Updated <br/>\r\n";
?>