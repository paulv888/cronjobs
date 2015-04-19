#!/usr/bin/php
<?php
require_once 'includes.php';

define('MY_DEVICE_ID', 108);
$a= (natSessions() < 0 ? LINK_DOWN : LINK_UP) ;
echo date("Y-m-d H:i:s").": ".UpdateLink(MY_DEVICE_ID, $a)." My Link Updated <br/>\r\n";
?>
