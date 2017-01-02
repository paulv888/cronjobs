#!/usr/bin/php
<?php
require_once 'includes.php';

$showlist = (isset($_GET['showlist']) ? $_GET['showlist'] : false);

define("MY_DEVICE_ID", 127);
$a= (deviceList($showlist) < 0 ? LINK_DOWN : LINK_UP) ;
echo updateDLink(MY_DEVICE_ID, $a);
?>
