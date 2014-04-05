<?php
// process configuration
define("INSTEON_SLEEP_MICRO", 300000); // 500mS

// Monitor Link Values
define("MONITOR_LINK", 2);
define("MONITOR_STATUS", 3);
define("MONITOR_LINK_STATUS", 4);

// Link Values
define("LINK_ERROR", 1);
define("LINK_WARNING", 2);
define("LINK_OK", 3);

// Link Values
define("LINK_DOWN", 0);
define("LINK_UP", 1);

// Event log values
define("SOURCE_ARD_BRDIGE", 9);

// Current Status Values
define("STATUS_ON", 1 );
define("STATUS_OFF", 0 );
define("STATUS_UNKNOWN", 2 );

// Current Status Values
define("COMMAND_SEND", 1 );
define("COMMAND_RECV", 2 );

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
define("COMMAND_UNKNOWN", 267);
define("COMMAND_ADDRESS", 21);


// Signal Sources
define ("SIGNAL_SOURCE_X10_RF", 1);
define ("SIGNAL_SOURCE_X10_PLC", 2);
define ("SIGNAL_SOURCE_REMOTE", 3);
define ("SIGNAL_SOURCE_OUTSIDE_CAM", 4);
define ("SIGNAL_SOURCE_CAM_BRIDGE", 5);
define ("SIGNAL_SOURCE_PC_UPDATE_TEMPS", 6);
define ("SIGNAL_SOURCE_PC_WEATHER_GOV", 7);
define ("SIGNAL_SOURCE_TCP_BRIDGE", 8);
define ("SIGNAL_SOURCE_ARDBRIDGE", 9);
define ("SIGNAL_SOURCE_THERMO_UPDATE_TEMPS", 10);
define ("SIGNAL_SOURCE_HA_WINXP", 11); 
define ("SIGNAL_SOURCE_REMOTE_BUTTON", 12); 
define ("SIGNAL_SOURCE_REMOTE_SCHEME", 13); 
define ("SIGNAL_SOURCE_HA_ALERT", 14); 
define ("SIGNAL_SOURCE_TRADE_ALERT", 15); 
define ("SIGNAL_SOURCE_COMMAND", 16); 
define ("SIGNAL_MONITOR_DEVICES", 17); 
define ("SIGNAL_SOURCE_INSTEON", 18);
define ("SIGNAL_SOURCE_STATUS_LINK_UPDATE", 19);

// Command Types
define ("COMMAND_CLASS_X10", 1);
define ("COMMAND_CLASS_3MFILTRETE", 2);
define ("COMMAND_CLASS_EMAIL", 3);
define ("COMMAND_CLASS_MEDIA", 4);
define ("COMMAND_CLASS_SONYCAM",5);
define ("COMMAND_CLASS_INTERNAL", 6);
define ("COMMAND_CLASS_ARDUINO", 7);
define ("COMMAND_CLASS_INSTEON", 8);
define ("COMMAND_CLASS_X10_INSTEON", 10);


// Special X10_INSTEON dim handling
define ("COMMAND_DIM_CLASS_X10_INSTEON", "|{code}480=I=3");

// Scheme Condition Types
define ("SCHEME_CONDITION_DEVICE_STATUS", 1);
define ("SCHEME_CONDITION_SYSTEM_STATUS", 2);
define ("SCHEME_CONDITION_TIME", 3);

// System Status
define ("SYSTEM_STATUS_ARE_HOME", 1);
define ("SYSTEM_STATUS_ALARM_ARMED", 2);
define ("SYSTEM_STATUS_IS_DARK", 3);
define ("SYSTEM_STATUS_PAUL_TRIP", 4);

// These are Internal Commands
// Move these to status!!!!
define("ERROR_CALIBRATE", 127);
define("COMMAND_RF_TIMEOUT", 128);
define("ERROR_RF_SEND_FAILED", 129);
define("ERROR_READ_SENSOR", 130);
define("COMMAND_LINK_STATUS", 151);

// These are Device Types
//define("", );
define("DEV_TYPE_OFF", 17);
define("DEV_TYPE_COOL", 18);
define("DEV_TYPE_HEAT", 19);

// Alerts
define("ALERT_CHANGED_NETWORK_DEVICE", 21);
define("ALERT_NEW_NETWORK_DEVICE", 22);

define("WEATHER_URL","http://www.weather.gov/xml/current_obs/");
define("SOURCE_WEATHER_GOV", 7);
?>