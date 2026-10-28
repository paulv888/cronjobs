<?php
//
// commands_pilot.php - Is the boiler pilot flame lit?
//
// Two commands, called with $params and answered through $feedback. The device
// is named by $params['deviceID'] (393, Boiler); a timer fires the command and
// the answer lands on that device's Status property like any other binary
// device, so triggers and remote.php need to learn nothing new.
//
//   pilotCheck      measure the camera frame, set Status from it, return the
//                   numbers behind the decision
//   pilotCalibrate  find where the flame actually is, store that region on the
//                   device as "Pilot ROI", return it
//
// Both take $params by reference and only set $params['device']['properties'];
// processCommand() calls updateDeviceProperties() afterwards, which creates and
// stores whatever is there. That is why the command row needs need_device = 1 -
// with it cleared, nothing is written back.
//
// The image is $params['commandvalue'] - a local path or a http(s) URL. PILOT_IMAGE
// is only the fallback for a command row that carries no value.
//
// The ROI lives on the device, not only in this file: pilotCalibrate writes it,
// pilotCheck reads it and falls back to PILOT_ROI when the property is missing
// or unreadable. So moving the camera is a calibrate run, not an edit and a
// deploy.
//
// To wire one up, add a command row whose `command` is pilotCheck (or
// pilotCalibrate), commandclassID generic, and fire it at device 393.
//
// Nothing here writes to the event log by itself; the caller's executeCommand()
// does that with the returned commandstr and message.
//

define("PILOT_IMAGE", LOCAL_LASTIMAGEDIR.'/O-Shed.jpg');

// Fallback region of interest, used until the device has been calibrated. This
// is what pilotCalibrate measured on the live frame: the flame and its glow,
// nothing else.
define("PILOT_ROI",       Array('x0' => 0.364, 'x1' => 0.461, 'y0' => 0.456, 'y1' => 0.744));

define("PILOT_ROI_PROPERTY", "Pilot ROI");	// holds x0,x1,y0,y1 as fractions

define("PILOT_THRESHOLD", 40.0);	// ROI mean: shed dark ~7, this box lit ~101
define("PILOT_MAX_AGE",   1800);	// never judge a local frame older than this

//
// The frame to measure: the command's value, or the default when it is empty.
//
function pilotImagePath($params) {

	if (array_key_exists('commandvalue', $params) && is_string($params['commandvalue'])
		&& trim($params['commandvalue']) !== '') {
		return trim($params['commandvalue']);
	}

	return PILOT_IMAGE;
}

//
// The region to measure: the device's stored ROI, else the constant above.
// $source is set to 'property' or 'default' so the caller can report which.
//
function pilotRoi($params, &$source = null) {

	$source = 'default';

	$property = getDeviceProperties(Array('deviceID' => (int)$params['deviceID'], 'description' => PILOT_ROI_PROPERTY));

	if (!empty($property['value'])) {
		$roi = pilotParseRoi($property['value']);
		if ($roi !== false) {
			$source = 'property';
			return $roi;
		}
	}

	return PILOT_ROI;
}

function pilotCheck(&$params) {

	debug($params, 'params');

	$feedback['Name'] = 'pilotCheck';
	$feedback['result'] = array();

	$image = pilotImagePath($params);
	$roi = pilotRoi($params, $roiSource);
	debug($roi, 'ROI');
	$feedback['commandstr'] = $image;

	$detail = array();
	$lit = pilotIsLit($image, $roi, PILOT_THRESHOLD, PILOT_MAX_AGE, $detail);

	if (is_null($lit)) {		// no usable frame: report it, leave Status alone
		$feedback['error'] = $detail['reason'];
		debug($feedback, 'feedback');
		return $feedback;
	}

	$feedback['commandstr'] = sprintf("%s roi mean %.1f threshold %.1f (%s roi) -> pilot %s",
		pilotName($image), $detail['mean'], PILOT_THRESHOLD, $roiSource, ($lit ? 'on' : 'off'));

	// Stored by processCommand() afterwards. Only a change writes and only a
	// change fires triggers, so a steady pilot is silent.
	$params['device']['properties']['Status']['value'] = ($lit ? 'On' : 'Off');

	$feedback['result']['image'] = $image;
	$feedback['result']['roi_source'] = $roiSource;
	$feedback['result']['mean'] = round($detail['mean'], 1);
	$feedback['result']['age'] = $detail['age'];
	$feedback['result']['status'] = $params['device']['properties']['Status']['value'];
	$feedback['message'] = ($lit ? 'Pilot on' : 'Pilot off').sprintf(' (roi mean %.1f)', $detail['mean']);

	debug($feedback, 'feedback');
	return $feedback;
}

function pilotCalibrate(&$params) {

	debug($params, 'params');

	$feedback['Name'] = 'pilotCalibrate';
	$feedback['result'] = array();

	$image = pilotImagePath($params);
	$feedback['commandstr'] = $image;

	if (!pilotIsUrl($image) && (!is_file($image) || !is_readable($image))) {
		$feedback['error'] = 'no readable image at '.$image;
		debug($feedback, 'feedback');
		return $feedback;
	}

	$located = pilotLocate($image);

	if ($located === false) {
		$feedback['error'] = 'No bright region in '.pilotName($image)
			.' - is the pilot lit and the camera uncovered?';
		debug($feedback, 'feedback');
		return $feedback;
	}

	$current = pilotMeanBrightness($image, pilotRoi($params));

	// Stored by processCommand() afterwards, and read back by pilotCheck().
	$params['device']['properties'][PILOT_ROI_PROPERTY]['value'] = pilotRoiCsv($located['roi']);

	$feedback['result']['image'] = $image;
	$feedback['result']['roi'] = $located['roi'];
	$feedback['result']['previous_roi_mean'] = (is_null($current) ? null : round($current, 1));
	$feedback['result']['mean'] = round($located['mean'], 1);
	$feedback['result']['peak'] = round($located['peak'], 1);
	$feedback['result']['stored'] = $params['device']['properties'][PILOT_ROI_PROPERTY]['value'];
	$feedback['result']['define'] = 'define("PILOT_ROI", '.pilotRoiString($located['roi']).');';

	$feedback['message'] = 'Pilot at '.pilotRoiCsv($located['roi'])
		.sprintf(', mean %.1f (was %.1f); stored as %s',
			$located['mean'], (is_null($current) ? 0 : $current), PILOT_ROI_PROPERTY);

	debug($feedback, 'feedback');
	return $feedback;
}
?>
