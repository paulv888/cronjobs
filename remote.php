<?php
require_once 'includes.php';
//define( 'REM_DEBUG', TRUE );
if (!defined('REM_DEBUG')) define( 'REM_DEBUG', FALSE );

if (isset($_GET["messtype"]) && isset($_GET["caller"])) {						// All have to tell where they are from.

	$messtypeID=$_GET["messtype"];
	$callerID=$_GET["caller"];
	if (REM_DEBUG) echo "callerID ".$callerID." ".$messtypeID.CRLF;

	switch ($messtypeID)
	{
	case MESS_TYPE_REMOTE_DIV:    									// Key pressed on remote
		if (isset($_GET["divid"])) {							// Called with key number		Can come with command from drop-down, key number needed for device
			$remotedivID= $_GET["divid"];
			if (REM_DEBUG) echo "MESS_TYPE_REMOTE_DIV ".$remotedivID.CRLF;
			$divid = explode ( '_' , $remotedivID);
			echo loadRemoteDiv($divid[1]);
		}
		break;
	}
}
?>
