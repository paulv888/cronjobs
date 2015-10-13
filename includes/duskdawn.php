<?php
//define( 'DEBUG_DUSKDAWN', TRUE );
if (!defined('DEBUG_DUSKDAWN')) define( 'DEBUG_DUSKDAWN', FALSE );

function GetDawn() {
	return getDeviceProperties(Array('deviceID' => DEVICE_DARK_OUTSIDE, 'description' => "Astronomy Sunrise"))['value'];
}

function GetDusk() {
	return getDeviceProperties(Array('deviceID' => DEVICE_DARK_OUTSIDE, 'description' => "Astronomy Sunset"))['value'];
}

?>
