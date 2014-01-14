<?php
require_once 'includes.php';

define("MY_DEVICE_ID", 108);

echo natSessions();
echo UpdateLink(MY_DEVICE_ID)." My Link Updated <br/>\r\n";

?>