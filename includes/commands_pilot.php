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

// Calibration finds the flame's position; the box around it is always this size.
// Fixed size matters: the thresholds are means of this box, so a box that grows
// or shrinks would change what the numbers mean. Measured on the live frames:
//
//   no flame (dark glass)   14.8   -> Status 0
//   pilot only              58.7   -> Status 1
//   burner running         157.3   -> Status 1, and running
//
define("PILOT_ROI_WIDTH",  0.177);	// 0.526 - 0.349
define("PILOT_ROI_HEIGHT", 0.368);	// 0.784 - 0.416

define("PILOT_THRESHOLD",  40.0);	// flame of any kind present
define("BURNER_THRESHOLD", 108.0);	// midway between pilot 58.7 and burner 157.3
define("PILOT_MAX_AGE",   1800);	// never judge a local frame older than this

//
// Three states from one mean. Only two of them reach Status - flame or no flame.
// "running" is measured and reported; recording it is a separate decision.
//
function pilotState($mean) {
	if (!is_numeric($mean) || $mean < PILOT_THRESHOLD) return 'off';
	if ($mean >= BURNER_THRESHOLD) return 'running';
	return 'pilot';
}

// Which Status value a state carries.
function pilotStateStatus($state) {
	return ($state === 'off') ? '0' : '1';
}

// The words for a message.
function pilotStateLabel($state) {
	if ($state === 'running') return 'Pilot on, burner running';
	if ($state === 'pilot')   return 'Pilot on';
	return 'Pilot off';
}

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

	$state = pilotState($detail['mean']);

	$feedback['commandstr'] = sprintf("%s roi mean %.1f  (off<%.0f, running>=%.0f) (%s roi) -> %s",
		pilotName($image), $detail['mean'], PILOT_THRESHOLD, BURNER_THRESHOLD,
		$roiSource, pilotStateLabel($state));

	// Stored by processCommand() afterwards. Only a change writes and only a
	// change fires triggers, so a steady pilot is silent. Two states only: a
	// running burner is still "On".
	$params['device']['properties']['Status']['value'] = pilotStateStatus($state);

	$feedback['result']['image'] = $image;
	$feedback['result']['roi_source'] = $roiSource;
	$feedback['result']['mean'] = round($detail['mean'], 1);
	$feedback['result']['age'] = $detail['age'];
	$feedback['result']['state'] = $state;
	$feedback['result']['running'] = ($state === 'running');
	$feedback['result']['status'] = $params['device']['properties']['Status']['value'];
	$feedback['message'] = pilotStateLabel($state).sprintf(' (roi mean %.1f)', $detail['mean']);

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

	// Where the flame is decides the position; the size is fixed, so the numbers
	// mean the same thing at every calibration.
	$roi = pilotRoiFixedSize($located['roi'], PILOT_ROI_WIDTH, PILOT_ROI_HEIGHT);

	$current = pilotMeanBrightness($image, pilotRoi($params));
	$mean = pilotMeanBrightness($image, $roi);

	// Stored by processCommand() afterwards, and read back by pilotCheck().
	$params['device']['properties'][PILOT_ROI_PROPERTY]['value'] = pilotRoiCsv($roi);

	$feedback['result']['image'] = $image;
	$feedback['result']['located'] = $located['roi'];
	$feedback['result']['roi'] = $roi;
	$feedback['result']['previous_roi_mean'] = (is_null($current) ? null : round($current, 1));
	$feedback['result']['mean'] = (is_null($mean) ? null : round($mean, 1));
	$feedback['result']['state'] = pilotState($mean);
	$feedback['result']['peak'] = round($located['peak'], 1);
	$feedback['result']['stored'] = $params['device']['properties'][PILOT_ROI_PROPERTY]['value'];
	$feedback['result']['define'] = 'define("PILOT_ROI", '.pilotRoiString($roi).');';

	$feedback['message'] = sprintf('%s at %s (fixed %.3fx%.3f), mean %s (was %.1f); stored as %s',
		pilotStateLabel(pilotState($mean)), pilotRoiCsv($roi), PILOT_ROI_WIDTH, PILOT_ROI_HEIGHT,
		(is_null($mean) ? 'n/a' : sprintf('%.1f', $mean)), (is_null($current) ? 0 : $current),
		PILOT_ROI_PROPERTY);

	debug($feedback, 'feedback');
	return $feedback;
}
?>
