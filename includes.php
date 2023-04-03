<?php 
include 'config.php' ;
include 'logins.php' ;
include_once 'constants.php';
include_once 'includes/remote.php';
include_once 'shared/shared_db.php';
include_once 'shared/shared_file.php';
include_once 'shared/shared_ha.php';
include_once 'shared/shared_gen.php';
include_once 'shared/shared_placeholders.php';
include_once 'includes/properties_factory.php';
include_once 'includes/commands_factory.php';
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
include_once 'includesPushbullet.php';
?>
