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

// Event Log Levels
define("LOGLEVEL_NONE", 0);
define("LOGLEVEL_DEBUG", 10);
define("LOGLEVEL_AWAKE", 20);
define("LOGLEVEL_COMMANDS", 30);
define("LOGLEVEL_MACROS", 40);
define("LOGLEVEL_ALARMS", 50);

// Log whether command was (or can) send or received
define("COMMAND_IO_NOT", 0 );    // Not in use
define("COMMAND_IO_RECV", 1 );		// Received or Incoming
define("COMMAND_IO_SEND", 2 );		// Send or Outgoing
define("COMMAND_IO_BOTH", 3 );		// Can be send or received

// Status Values, Retrieved from command
define("STATUS_ON", 1 );
define("STATUS_OFF", 0 );
define("STATUS_UNKNOWN", 2 );
define("STATUS_ERROR", -1 );
define("STATUS_NOT_DEFINED", 10);		// Used for defining status on commands 

// Commands from Device   ??? obsolete ???
//define("COMMAND_RESULT_OK", 1);
//define("COMMAND_RESULT_ERROR", 2);
define("COMMAND_STATUSON", 8);
define("COMMAND_STATUSOFF", 6);
define("COMMAND_TOGGLE", 19);
define("COMMAND_ON", 17);
define("COMMAND_OFF", 20);
define("COMMAND_DIM", 13);
//define("COMMAND_STREAM_DATA", 91);
define("COMMAND_TOGGLE_HVAC", 132);
define("COMMAND_UNKNOWN", 267);
define("COMMAND_ADDRESS", 21);
define("COMMAND_RUN_SCHEME", 154);


// Triggers
define("TRIGGER_AFTER_ON", 1);
define("TRIGGER_AFTER_OFF", 2);
define("TRIGGER_AFTER_CHANGE", 3);

// Call executeCommand with either off these
define ("MESS_TYPE_REMOTE_KEY", 'MESS_TYPE_REMOTE_KEY');
define ("MESS_TYPE_SCHEME", 'MESS_TYPE_SCHEME');
define ("MESS_TYPE_COMMAND",  'MESS_TYPE_COMMAND');
define ("MESS_TYPE_GET_GROUP",  'MESS_TYPE_GET_GROUP');
define ("MESS_TYPE_MULTI_KEY",  'MESS_TYPE_MULTI_KEY');

// Command Classes
define ("COMMAND_CLASS_GENERIC", 1);
define ("COMMAND_CLASS_3MFILTRETE", 2);
define ("COMMAND_CLASS_EMAIL", 3);
define ("COMMAND_CLASS_MEDIA", 4);
define ("COMMAND_CLASS_SONYCAM",5);
//define ("COMMAND_CLASS_PHP",6);
define ("COMMAND_CLASS_ARDUINO", 7);
define ("COMMAND_CLASS_INSTEON", 8);
define ("COMMAND_CLASS_X10_INSTEON", 10);
define ("COMMAND_CLASS_FOSCAM", 11);
define ("COMMAND_CLASS_X10", 13);

// Special X10_INSTEON dim handling
define ("COMMAND_DIM_CLASS_X10_INSTEON_OFF", "0263{code}380|");
define ("COMMAND_DIM_CLASS_X10_INSTEON_DIMM", "|0263{code}480");

// Scheme Condition Types
define ("SCHEME_CONDITION_DEVICE_STATUS", 1);
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
define("ALERT_UNKNOWN_IP_FOUND", 35);

define("WEATHER_URL","http://www.weather.gov/xml/current_obs/");
define("SOURCE_WEATHER_GOV", 7);

// Insteon Decoder Errors
define("ERROR_STX_MISSING", -1);
define("ERROR_MESSAGE_TO_SHORT", -2);

// Query replacement devices
define("DEVICE_SOMEONE_HOME", 157);
define("DEVICE_ALARM_ZONE1", 158);
define("DEVICE_ALARM_ZONE2", 159);
define("DEVICE_DARK_OUTSIDE", 160);
define("DEVICE_PAUL_HOME", 161);
define("DEVICE_REMOTE", 164);

// Call executeCommand with either off these
define ("MAIL_TYPE_TRADE", 1);
define ("MAIL_TYPE_SCHEME", 2);

define("TIME_DAWN", 90);
define("TIME_DUSK", 91);

define("REPEAT_ONCE_DAY", 0);

define("CRLF", "</br>\n");
?>
