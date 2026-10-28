<?php 
include_once '/etc/ha/logins.php' ;
include_once 'config.php' ;
include_once 'constants.php';
include_once 'includes/remote.php';
include_once 'shared/shared_db.php';
include_once 'shared/shared_auth.php';
include_once 'shared/shared_fcm.php';
include_once 'includes/commands_users.php';
include_once 'includes/commands_alerts.php';
include_once 'shared/shared_file.php';
include_once 'shared/shared_ha.php';
include_once 'shared/shared_pilot.php';
include_once 'shared/shared_gen.php';
include_once 'shared/shared_math.php';
include_once 'shared/shared_placeholders.php';
include_once 'includes/properties_factory.php';
include_once 'includes/commands_factory.php';
include_once 'includes/commands_pilot.php';
include_once 'includes/commands_network.php';
include_once 'includes/processor.php';
include_once 'includes/timers.php';
include_once 'includes/commands_weather.php';
// include_once 'includes/monitor_devices.php';
include_once 'shared/simple_html_dom.php';
include_once 'includes/commands_thermo.php';
include_once 'includes/commands_media.php';

include_once 'Classes/OAuth.php';
include_once 'Classes/thermo_lib.php';
include_once 'Classes/RestClient.class.php';
include_once 'Classes/TCPClient.php';
include_once 'Classes/insteon_decoder.class.php';
include_once 'Classes/sockettransport.class.php';
// Pushbullet removed: alerts go out through shared_fcm.php now.
?>
