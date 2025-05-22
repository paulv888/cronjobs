<?php
// process configuration
define("INSTEON_SLEEP_MICRO", 300000); // 300mS

define("SEVERITY_NONE", 0);
define("SEVERITY_DANGER", "Major");
define("SEVERITY_WARNING", "Severe");
define("SEVERITY_INFO", "Minor");

define("SEVERITY_DANGER_CLASS", "alert-danger");
define("SEVERITY_WARNING_CLASS", "alert");
define("SEVERITY_INFO_CLASS", "alert-info");

// Link Values
define("LINK_DOWN", "0");
define("LINK_UP", "1");
define("LINK_WARNING", "2");
define("LINK_TIMEDOUT","3");	// not a real status

// Event Log Levels
define("LOGLEVEL_NONE", 1);
define("LOGLEVEL_DEBUG", 10);
define("LOGLEVEL_MONITOR", 20);
define("LOGLEVEL_COMMAND", 30);
define("LOGLEVEL_MACRO", 40);
define("LOGLEVEL_OVERWRITE", 50);

// Log whether command was (or can) send or received
define("COMMAND_IO_NOT", 0 );    // Not in use
define("COMMAND_IO_RECV", 1 );		// Received or Incoming
define("COMMAND_IO_SEND", 2 );		// Send or Outgoing
define("COMMAND_IO_BOTH", 3 );		// Can be send or received

// Status Values, Retrieved from command
define("STATUS_OFF", "0" );
define("STATUS_ON", "1" );
define("STATUS_UNKNOWN", "2" );
define("STATUS_COMMAND_VALUE", "3" );
define("STATUS_ERROR", "-1" );
define("STATUS_NOT_DEFINED", "10");		// Used for defining status on commands 

define("COMMAND_TOGGLE", 19);
define("COMMAND_DIM", 13);
define("COMMAND_BRIGHTEN", 15);
define("COMMAND_ON", 17);
define("COMMAND_OFF", 20);
define("COMMAND_UNKNOWN", 267);
define("COMMAND_RUN_SCHEME", 154);
define("COMMAND_GET_VALUE", 136);
define("COMMAND_SET_VALUE", 145);
define("COMMAND_SET_RESULT", 285);
define("COMMAND_PING", 151);
define("COMMAND_PLAY", 60);
define("COMMAND_STOP", 58);
define("COMMAND_PAUSE", 59);
define("COMMAND_SET_PROPERTY_VALUE", 314);
define("COMMAND_VOICE", 324);
define("COMMAND_GET_PROPERTIES", 367);
define("COMMAND_LOG_EVENT", 378);
define("COMMAND_GET_LIST", 444);
define("COMMAND_SEND_MESSAGE", 452);
define("COMMAND_SEND_MESSAGE_KODI", 152);
define("COMMAND_NOP", 396);

define("SCHEME_ALERT_CRITICAL", 291);
define("SCHEME_ALERT_HIGH", 288);
define("SCHEME_ALERT_NORMAL", 290);
define("SCHEME_ALERT_LOW", 292);

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
define ("MESS_TYPE_VOICE",  'MESS_TYPE_VOICE');

// Command Classes
define ("COMMAND_CLASS_GENERIC", 1);
define ("COMMAND_CLASS_3MFILTRETE", 2);
define ("COMMAND_CLASS_EMAIL", 3);
define ("COMMAND_CLASS_KODI", 4);
define ("COMMAND_CLASS_SONYCAM",5);
//define ("COMMAND_CLASS_PHP", 6);
define ("COMMAND_CLASS_ARDUINO", 7);
define ("COMMAND_CLASS_INSTEON", 8);
define ("COMMAND_CLASS_IRRIGATIONCADDY", 9);
define ("COMMAND_CLASS_X10_INSTEON", 10);
define ("COMMAND_CLASS_FOSCAM", 11);
define ("COMMAND_CLASS_X10", 13);
define ("COMMAND_CLASS_HASS", 18);
define ("COMMAND_CLASS_BULLET", 28);
define ("COMMAND_CLASS_ADB", 39);

// Properties
define ("PROPERTY_STATUS", 123);
define ("PROPERTY_LINK", 225);
define ("PROPERTY_SEMAPHORE", 376);
define ("PROPERTY_LEVEL", 243);


// Special X10_INSTEON dim handling
define ("COMMAND_DIM_CLASS_X10_INSTEON_OFF", "0263{code}380|");
define ("COMMAND_DIM_CLASS_X10_INSTEON_DIMM", "|0263{code}480");

// Scheme Condition Types
define ("SCHEME_CONDITION_DEVICE_PROPERTY_VALUE", 10);
define ("SCHEME_CONDITION_DEVICE_PROPERTY_STATUS_VALUE_AS_STEP", 123);
define ("SCHEME_CONDITION_DEVICE_PROPERTY_LINK_VALUE_AS_STEP", 225);
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
define("DEV_TYPE_THERMOSTAT_CT30_AUTO",40);
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

define("DEV_INTERNAL_TYPE_GENERIC",0);
define("DEV_INTERNAL_TYPE_HEAT",1);
define("DEV_INTERNAL_TYPE_COOL",2);

define("SOURCE_WEATHER_GOV", 7);

define("RECORDING_TYPE_DOOR", 10);
define("RECORDING_TYPE_MOTION_CAMERA", 20);
define("RECORDING_TYPE_SOUND_CAMERA", 25);
define("RECORDING_TYPE_MOTION_SENSOR_FRONT", 30);
define("RECORDING_TYPE_MOTION_SENSOR_GARAGE", 30);
define("RECORDING_TYPE_TOUR", 40);
define("RECORDING_TYPE_USER", 50);
define("RECORDING_TYPE_CONTINUOUS", 99);

// Insteon Decoder Errors
define("ERROR_STX_MISSING", -1);
define("ERROR_MESSAGE_TO_SHORT", -2);
define("ERROR_UNKNOWN_MESSAGE", -3);

// Query replacement devices
define("DEVICE_SOMEONE_HOME", 157);
define("DEVICE_ALARM_ZONE1", 158);
define("DEVICE_ALARM_ZONE2", 159);
define("DEVICE_MOTION_MUTED", 288);
define("DEVICE_DARK_OUTSIDE", 170);
define("DEVICE_PAUL_HOME", 161);
define("DEVICE_REMOTE", 164);
define("DEVICE_REMOTE_TEXT", 350);
define("DEVICE_SELECTED_PLAYER", 260);
define("DEVICE_SYSTEM_PARAMETERS", 342);
define("DEVICE_CALLING_DEVICE_ID", 315);

define("TIME_DAWN", 90);
define("TIME_DUSK", 91);

define("CONDITION_GREATER", 10);
define("CONDITION_LESS", 20);
define("CONDITION_EQUAL", 30);


define("THERMO_CONNECTION_TIMEOUT", 10);

define("PRIORITY_CRITICAL", 1);
define("PRIORITY_HIGH", 2);
define("PRIORITY_NORMAL", 3);
define("PRIORITY_LOW", 4);
define("PRIORITY_HIDE", 99);

define("Q_QUEUED", 5);
define("Q_RELEASED", 10);
define("Q_VERIFY_SUCCESS", 20);
define("Q_VERIFY_FAILED", 30);
define("Q_DOWNLOAD_SUCCESS", 40);
define("Q_DOWNLOAD_FAILED", 50);
define("Q_IMPORT_SUCCESS", 60);
define("Q_IMPORT_FAILED", 70);
define("Q_PROCESSING", 90);
define("Q_DOWNLOADING", 93);
define("Q_CANCEL", 95);

define("YT_GET_PLAYLIST", "10");
define("YT_VIDEO_ONLY", "20");

define("REPEAT_EVERY_RUN", -1);
define("REPEAT_ONCE_DAY", 0);


define("WINK_CODE", "U");

define("CRLF", "<br>\n");
?>
