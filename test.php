<?php
require_once 'includes.php';

$status_feedback = array (
        array("off","on"),              // 0
        array("off","on"),              // 1
        array("closed","open"),
        array("un-locked","locked"),
        array("disarmed","armed"),
        array("not seen","detected"),
        array("off","running")
);



for ($deviceID = 1; $deviceID <= 305; $deviceID++){

	if  (getDevice($deviceID)) {

        	$statusNames = $status_feedback[getDevice($deviceID)['type']['status_feedback']];

		print_r($statusNames);

		echo $deviceID;
		echo " ".getDevice($deviceID)['type']['status_feedback']." ";
		echo getFeedbackStatus($deviceID, STATUS_OFF)." ";
		echo getFeedbackStatus($deviceID, STATUS_ON).CRLF;
	}
}
?>
