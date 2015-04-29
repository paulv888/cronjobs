#!/usr/bin/php
<?php
require_once 'includes.php';

define("MY_DEVICE_ID", 127);
$a= (deviceList() < 0 ? LINK_DOWN : LINK_UP) ;
echo date("Y-m-d H:i:s").": ".UpdateLink(array('callerID' => MY_DEVICE_ID, 'link' => $a))." My Link Updated <br/>\r\n";
?>
