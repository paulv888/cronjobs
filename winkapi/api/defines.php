<?php
define("COMMAND_STATUSON", 8);
define("COMMAND_STATUSOFF", 6);
define("COMMAND_TOGGLE", 19);
define("COMMAND_ON", 17);
define("COMMAND_OFF", 20);
define("COMMAND_DIM", 13);
define("COMMAND_UNKNOWN", 267);
define("COMMAND_SET_RESULT", 285);
define("COMMAND_SET_VALUE", 145);
define("COMMAND_GET_GROUP", 282);
define("COMMAND_GET_VALUE", 136);
define("COMMAND_SET_TIMER", 287);
define("COMMAND_PING", 151);

// Status Values, Retrieved from command
define("STATUS_ON", 1 );
define("STATUS_OFF", 0 );
define("STATUS_UNKNOWN", 2 );
define("STATUS_ERROR", -1 );
define("STATUS_NOT_DEFINED", 10);		// Used for defining status on commands 

?>
