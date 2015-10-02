<?php
// process configuration
define("INSTEON_SLEEP_MICRO", 300000); // 500mS

define("SEVERITY_NONE", 0);
define("SEVERITY_DANGER", 1);
define("SEVERITY_WARNING", 2);
define("SEVERITY_INFO", 3);

define("SEVERITY_DANGER_CLASS", "alert-danger");
define("SEVERITY_WARNING_CLASS", "alert");
define("SEVERITY_INFO_CLASS", "alert-info");

// Link Values
define("LINK_DOWN", 0);
define("LINK_UP", 1);
define("LINK_WARNING", 2);
define("LINK_TIMEDOUT",3);

// Event Log Levels
define("LOGLEVEL_NONE", 1);
define("LOGLEVEL_DEBUG", 10);
define("LOGLEVEL_MONITOR", 20);
define("LOGLEVEL_COMMAND", 30);
define("LOGLEVEL_MACRO", 40);
define("LOGLEVEL_ALARMS", 50);

// Log whether command was (or can) send or received
define("COMMAND_IO_NOT", 0 );    // Not in use
define("COMMAND_IO_RECV", 1 );		// Received or Incoming
define("COMMAND_IO_SEND", 2 );		// Send or Outgoing
define("COMMAND_IO_BOTH", 3 );		// Can be send or received

// Status Values, Retrieved from command
define("STATUS_OFF", 0 );
define("STATUS_ON", 1 );
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
define("COMMAND_STREAM_DATA", 91);			// ***** Review does not match command
define("COMMAND_TOGGLE_HVAC", 132);
define("COMMAND_UNKNOWN", 267);
define("COMMAND_ADDRESS", 21);
define("COMMAND_RUN_SCHEME", 154);
define("COMMAND_SET_RESULT", 285);
define("COMMAND_GET_GROUP", 282);
define("COMMAND_LOG_ALERT", 152);
define("COMMAND_GET_VALUE", 136);
define("COMMAND_SET_TIMER", 287);
define("COMMAND_PING", 151);
define("COMMAND_SET_PROPERTY_VALUE", 314);
define("COMMAND_GRAPH_CREATE", 315);


// Triggers
define("TRIGGER_AFTER_ON", 1);
define("TRIGGER_AFTER_OFF", 2);
define("TRIGGER_AFTER_CHANGE", 3);
define("TRIGGER_AFTER_ERROR", 4);

// Call executeCommand with either off these
define ("MESS_TYPE_REMOTE_KEY", 'MESS_TYPE_REMOTE_KEY');
define ("MESS_TYPE_SCHEME", 'MESS_TYPE_SCHEME');
define ("MESS_TYPE_COMMAND",  'MESS_TYPE_COMMAND');
define ("MESS_TYPE_MULTI_KEY",  'MESS_TYPE_MULTI_KEY');
define ("MESS_TYPE_REMOTE_DIV",  'MESS_TYPE_REMOTE_DIV');

// Command Classes
define ("COMMAND_CLASS_GENERIC", 1);
define ("COMMAND_CLASS_3MFILTRETE", 2);
define ("COMMAND_CLASS_EMAIL", 3);
define ("COMMAND_CLASS_MEDIA", 4);
define ("COMMAND_CLASS_SONYCAM",5);
//define ("COMMAND_CLASS_PHP", 6);
define ("COMMAND_CLASS_ARDUINO", 7);
define ("COMMAND_CLASS_INSTEON", 8);
define ("COMMAND_CLASS_IRRIGATIONCADDY", 9);
define ("COMMAND_CLASS_X10_INSTEON", 10);
define ("COMMAND_CLASS_FOSCAM", 11);
define ("COMMAND_CLASS_X10", 13);


// Special X10_INSTEON dim handling
define ("COMMAND_DIM_CLASS_X10_INSTEON_OFF", "0263{code}380|");
define ("COMMAND_DIM_CLASS_X10_INSTEON_DIMM", "|0263{code}480");

// Scheme Condition Types
define ("SCHEME_CONDITION_DEVICE_PROPERTY_VALUE", 10);
define ("SCHEME_CONDITION_GROUP_PROPERTY_AND", 20);
define ("SCHEME_CONDITION_GROUP_PROPERTY_OR", 25);
define ("SCHEME_CONDITION_CURRENT_TIME", 40);
 
// System Status
// Remove? Not in use anymore
define ("SYSTEM_STATUS_ARE_HOME", 1);
define ("SYSTEM_STATUS_ALARM_ARMED", 2);
define ("SYSTEM_STATUS_IS_DARK", 3);
define ("SYSTEM_STATUS_PAUL_TRIP", 4);

// These are Device Types
//define("", );
define("DEV_TYPE_DIMMER_CANDESCENT",1);
define("DEV_TYPE_SWITCH",3);
define("DEV_TYPE_TRANSCEIVER",4);
define("DEV_TYPE_MOTION_SENSOR",5);
define("DEV_TYPE_FAN_MOTOR",6);
define("DEV_TYPE_UNKNOWN",8);
define("DEV_TYPE_NOT_IN_USE",9);
define("DEV_TYPE_CHIME",10);
define("DEV_TYPE_TEMP_HUMIDITY",11);
define("DEV_TYPE_DAY_NIGHT_SENSOR",12);
define("DEV_TYPE_CAMERA",13);
define("DEV_TYPE_WATER_SENSOR",14);
define("DEV_TYPE_SOFTWARE",15);
define("DEV_TYPE_ARDUINO_MODULES",16);
define("DEV_TYPE_THERMOSTAT_CT30_OFF",17);
define("DEV_TYPE_THERMOSTAT_CT30_COOL",18);
define("DEV_TYPE_THERMOSTAT_CT30_HEAT",19);
define("DEV_TYPE_NETWORK_DEVICES",20);
define("DEV_TYPE_COMPUTERS",21);
define("DEV_TYPE_DOOR_SENSOR",22);
define("DEV_TYPE_MOBILE",23);
define("DEV_TYPE_CONFIGURATION",26);
define("DEV_TYPE_ALARM",27);
define("DEV_TYPE_WINDOW_SENSOR",28);
define("DEV_TYPE_SPRINKLER",29);
define("DEV_TYPE_AUTO_DOOR",30);
define("DEV_TYPE_THERMOSTAT_ARD_HEAT",31);
define("DEV_TYPE_THERMOSTAT_ARD_COOL",32);
define("DEV_TYPE_WATER_LEVEL",33);
define("DEV_TYPE_LIGHT_SENSOR_ANALOG",34);
define("DEV_TYPE_LOCK",35);

// Alerts
define("ALERT_NETWORK_DEVICE_CHANGE", 21);
define("ALERT_NEW_NETWORK_DEVICE", 9);
define("ALERT_LEFT_HOME", 24);
define("ALERT_CAME_HOME", 28);
define("ALERT_OLIVIA_LEFT_HOME", 31);
define("ALERT_OLIVIA_CAME_HOME", 32);
define("ALERT_UNKNOWN_IP_FOUND", 35);
define("WATER_ALARM_TRIGGERED", 36);
define("ALARM-2_TRIGGERED", 37);

define("WEATHER_URL","http://www.weather.gov/xml/current_obs/");
define("SOURCE_WEATHER_GOV", 7);

// Insteon Decoder Errors
define("ERROR_STX_MISSING", -1);
define("ERROR_MESSAGE_TO_SHORT", -2);

// Query replacement devices
define("DEVICE_SOMEONE_HOME", 157);
define("DEVICE_ALARM_ZONE1", 158);
define("DEVICE_ALARM_ZONE2", 159);
define("DEVICE_DARK_OUTSIDE", 170);
define("DEVICE_PAUL_HOME", 161);
define("DEVICE_REMOTE", 164);

define("TIME_DAWN", 90);
define("TIME_DUSK", 91);

define("CONDITION_GREATER", 10);
define("CONDITION_LESS", 20);
define("CONDITION_EQUAL", 30);


define("THERMO_CONNECTION_TIMEOUT", 10);

define("PRIORITY_HIDE", 99);

define("REPEAT_ONCE_DAY", 0);
define("REPEAT_ONCE_HOUR", 60);

define("MY_SUBNET", "192.168.2");
define("MY_VPN_SUBNET", "192.168.10");

define("WINK_CODE", "U");

define("CRLF", "</br>\n");
?>
