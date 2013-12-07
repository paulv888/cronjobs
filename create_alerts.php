<?php
include_once 'includes/create_alerts.php';

define("MY_DEVICE_ID", 97);

echo Alerts()." Alerts generated <br/>\r\n";
echo AlertsActions()." Alerts sent <br/>\r\n";
echo UpdateMylink(MY_DEVICE_ID)." My Link Updated <br/>\r\n";
?>
