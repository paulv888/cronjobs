<?php
// process configuration
define("INSTEON_SLEEP_MICRO", 300000); // 300mS

// Monitor Link Values
define("MONITOR_LINK", 2);
define("MONITOR_STATUS", 3);
define("MONITOR_LINK_STATUS", 4);

// Link Values
define("LINK_ERROR", 1);
define("LINK_WARNING", 2);
define("LINK_OK", 3);

// Event log values
define("EVENT_OUT", 1);
define("EVENT_IN", 2);
define("SOURCE_ARD_BRDIGE", 9);

// Current Status Values
define("STATUS_ON", 17 );
define("STATUS_OFF", 20 );
define("STATUS_UNKNOWN", 0 );

// Commands from Device
define("COMMAND_RESULT_OK", 1);
define("COMMAND_RESULT_ERROR", 2);
define("COMMAND_STATUSON", 8);
define("COMMAND_STATUSOFF", 6);
define("COMMAND_TOGGLE", 19);
define("COMMAND_ON", 17);
define("COMMAND_OFF", 20);
define("COMMAND_STREAM_DATA", 91);
define("COMMAND_TOGGLE_HVAC", 132);

// Command Types
define ("COMMAND_CLASS_X10", 1);
define ("COMMAND_CLASS_3MFILTRETE", 2);
define ("COMMAND_CLASS_EMAIL", 3);
define ("COMMAND_CLASS_MEDIA", 4);
define ("COMMAND_CLASS_SONYCAM",5);
define ("COMMAND_CLASS_INTERNAL", 6);
define ("COMMAND_CLASS_ARDUINO", 7);
define ("COMMAND_CLASS_INSTEON", 8);

// Data field from Device 
// These are Internal Commands
define("ERROR_CALIBRATE", 127);
define("COMMAND_RF_TIMEOUT", 128);
define("ERROR_RF_SEND_FAILED", 129);
define("ERROR_READ_SENSOR", 130);

// Front End Call types
define("CALL_SOURCE_REMOTE_BUTTON", 1); 
define("CALL_SOURCE_REMOTE_SCHEME", 2); 
define("CALL_SOURCE_HA_ALERT", 3); 
define("CALL_SOURCE_TRADE_ALERT", 4); 
define("CALL_SOURCE_COMMAND", 5); 

// These are Device Types
//define("", );
define("DEV_TYPE_HEAT", 19);
define("DEV_TYPE_COOL", 18);
?>