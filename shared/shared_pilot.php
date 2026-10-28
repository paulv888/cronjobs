<?php
//
// shared_pilot.php - image measurement for the boiler pilot flame.
//
// Deliberately free of the framework: no database, no config, no includes. The
// caller supplies the image, the region of interest and the threshold, so this
// can be exercised on a saved frame without booting the rest of the system.
//
// The job is small: crop a fixed box, desaturate it, average the luminance. A
// burning pilot lifts that average from about 7 (dark shed) to about 42.
//
// The image may be a local path or a http(s) URL; a URL is fetched with the
// JPEG reader directly, so no temporary file is written.
//

function pilotIsUrl($path) {
	return (bool)preg_match('#^https?://#i', $path);
}

//
// A name for messages: the file part of a URL, the basename of a path.
//
function pilotName($path) {
	if (pilotIsUrl($path)) {
		$parsed = parse_url($path);
		return (is_array($parsed) && !empty($parsed['path']) ? basename($parsed['path']) : $path);
	}
	return basename($path);
}

//
// Opens the image, handing back $error when it cannot.
//
function pilotOpenImage($path, &$error = null) {

	$error = null;

	if (pilotIsUrl($path)) {
		$src = @imagecreatefromjpeg($path);
		if (!$src) {
			$error = 'cannot fetch '.$path;
		}
		return $src;
	}

	if (!is_file($path) || !is_readable($path)) {
		$error = 'no readable image at '.$path;
		return false;
	}

	$src = @imagecreatefromjpeg($path);
	if (!$src) {
		$error = 'cannot read '.$path;
	}
	return $src;
}

//
// $roi is fractions of the frame: array('x0' => 0.33, 'x1' => 0.44, 'y0' => 0.38, 'y1' => 0.60)
// Fractions rather than pixels, so a change of camera or resolution does not
// move the box.
//
// Returns the mean luminance (0-255) of the ROI, or false when the image cannot
// be read.
//
function pilotMeanBrightness($path, $roi) {

	$src = pilotOpenImage($path);
	if (!$src) {
		return false;
	}

	$width  = imagesx($src);
	$height = imagesy($src);

	$x0 = (int)round($width  * $roi['x0']);
	$x1 = (int)round($width  * $roi['x1']);
	$y0 = (int)round($height * $roi['y0']);
	$y1 = (int)round($height * $roi['y1']);

	$cropWidth  = $x1 - $x0;
	$cropHeight = $y1 - $y0;
	if ($cropWidth < 1 || $cropHeight < 1) {
		imagedestroy($src);
		return false;
	}

	$crop = imagecreatetruecolor($cropWidth, $cropHeight);
	imagecopy($crop, $src, 0, 0, $x0, $y0, $cropWidth, $cropHeight);
	imagefilter($crop, IMG_FILTER_GRAYSCALE);

	$sum = 0;
	for ($y = 0; $y < $cropHeight; $y++) {
		for ($x = 0; $x < $cropWidth; $x++) {
			$sum += imagecolorat($crop, $x, $y) & 0xFF;	// grey: any channel
		}
	}
	$mean = $sum / ($cropWidth * $cropHeight);

	imagedestroy($crop);
	imagedestroy($src);

	return $mean;
}

//
// true = pilot burning, false = pilot out, null = no frame worth judging.
//
// $detail is filled with the numbers behind the decision so the caller can
// report them; without that, tuning the threshold is guesswork. age is null for
// a URL, where there is no file timestamp to trust.
//
function pilotIsLit($path, $roi, $threshold, $maxAge, &$detail = null) {

	$detail = array('mean' => null, 'age' => null, 'reason' => '');

	if (!pilotIsUrl($path)) {
		if (!is_file($path) || !is_readable($path)) {
			$detail['reason'] = 'no readable image at '.$path;
			return null;
		}
		$age = time() - filemtime($path);
		$detail['age'] = $age;
		if ($age > $maxAge) {
			$detail['reason'] = 'image is '.$age.'s old';
			return null;
		}
	}

	$mean = pilotMeanBrightness($path, $roi);
	if ($mean === false) {
		$detail['reason'] = 'cannot read '.$path;
		return null;
	}
	$detail['mean'] = $mean;

	return ($mean >= $threshold);
}

//
// Finds the flame: the brightest compact region of the frame, returned as ROI
// fractions ready to paste into PILOT_ROI. This is what makes a moved camera
// recoverable - the box is fixed, so if the camera is nudged the box fills with
// wall and the reading collapses to "off" forever.
//
// The timestamp strip along the top and the logo at the bottom are the
// brightest things in the frame and are always present, so both are excluded
// before anything is compared.
//
// Returns array('roi' => array(x0,x1,y0,y1), 'mean' => float, 'peak' => float,
// 'block' => int) or false when the frame has no bright region at all (pilot
// out, or camera covered).
//
function pilotLocate($path, $topMargin = 0.10, $bottomMargin = 0.10, $growRatio = 0.35, $minPeak = 30.0) {

	$src = pilotOpenImage($path);
	if (!$src) {
		return false;
	}

	$width  = imagesx($src);
	$height = imagesy($src);
	imagefilter($src, IMG_FILTER_GRAYSCALE);

	$block = max(8, (int)round($width / 40));	// ~16px on a 640 wide frame
	$cols  = (int)floor($width  / $block);
	$rows  = (int)floor($height / $block);
	if ($cols < 4 || $rows < 4) {
		imagedestroy($src);
		return false;
	}

	$rowFrom = (int)ceil($rows * $topMargin);
	$rowTo   = (int)floor($rows * (1 - $bottomMargin));

	//
	// Coarse map: mean luminance of every block, every other pixel sampled.
	//
	$means = array();
	$peak = 0; $peakCol = 0; $peakRow = 0;
	for ($r = $rowFrom; $r < $rowTo; $r++) {
		for ($c = 0; $c < $cols; $c++) {
			$sum = 0; $n = 0;
			for ($y = $r * $block; $y < ($r + 1) * $block; $y += 2) {
				for ($x = $c * $block; $x < ($c + 1) * $block; $x += 2) {
					$sum += imagecolorat($src, $x, $y) & 0xFF;
					$n++;
				}
			}
			$means[$r][$c] = ($n ? $sum / $n : 0);
			if ($means[$r][$c] > $peak) {
				$peak = $means[$r][$c];
				$peakCol = $c;
				$peakRow = $r;
			}
		}
	}

	if ($peak < $minPeak) {		// nothing in the frame stands out
		imagedestroy($src);
		return false;
	}

	//
	// Grow out from the peak while neighbouring blocks stay within $growRatio of
	// it, so one blob is returned rather than every bright block in the frame.
	//
	$floor = $peak * $growRatio;
	$seen = array();
	$queue = array(array($peakRow, $peakCol));
	$minRow = $maxRow = $peakRow;
	$minCol = $maxCol = $peakCol;

	while (!empty($queue)) {
		list($r, $c) = array_pop($queue);
		if ($r < $rowFrom || $r >= $rowTo || $c < 0 || $c >= $cols) continue;
		if (isset($seen[$r][$c]) || !isset($means[$r][$c]) || $means[$r][$c] < $floor) continue;

		$seen[$r][$c] = true;
		$minRow = min($minRow, $r); $maxRow = max($maxRow, $r);
		$minCol = min($minCol, $c); $maxCol = max($maxCol, $c);

		$queue[] = array($r - 1, $c);
		$queue[] = array($r + 1, $c);
		$queue[] = array($r, $c - 1);
		$queue[] = array($r, $c + 1);
	}

	$x0 = ($minCol * $block) / $width;
	$x1 = (($maxCol + 1) * $block) / $width;
	$y0 = ($minRow * $block) / $height;
	$y1 = (($maxRow + 1) * $block) / $height;

	$marginX = ($x1 - $x0) * 0.15;	// a little air, as in the hand measured box
	$marginY = ($y1 - $y0) * 0.15;

	$roi = array(
		'x0' => max(0.0, $x0 - $marginX), 'x1' => min(1.0, $x1 + $marginX),
		'y0' => max(0.0, $y0 - $marginY), 'y1' => min(1.0, $y1 + $marginY),
	);

	$detail = array(
		'roi'   => $roi,
		'mean'  => pilotMeanBrightness($path, $roi),
		'peak'  => $peak,
		'block' => $block,
	);

	imagedestroy($src);

	return $detail;
}

//
// Puts a box of a fixed size around the centre of a located region. Calibration
// finds where the flame is; the size is fixed, because both thresholds are means
// of this box and a box that changes size changes what the numbers mean.
//
// The box slides to stay inside the frame rather than being clipped or shrunk, so
// its size is the same whatever the camera does.
//
function pilotRoiFixedSize($around, $width, $height) {

	$cx = ($around['x0'] + $around['x1']) / 2;
	$cy = ($around['y0'] + $around['y1']) / 2;

	$width  = min(1.0, max(0.01, $width));
	$height = min(1.0, max(0.01, $height));

	$x0 = $cx - $width / 2;
	$y0 = $cy - $height / 2;

	if ($x0 < 0) $x0 = 0.0;
	if ($x0 + $width > 1.0) $x0 = 1.0 - $width;
	if ($y0 < 0) $y0 = 0.0;
	if ($y0 + $height > 1.0) $y0 = 1.0 - $height;

	return array(
		'x0' => $x0, 'x1' => $x0 + $width,
		'y0' => $y0, 'y1' => $y0 + $height,
	);
}

//
// Prints an ROI array as the four numbers PILOT_ROI wants.
//
function pilotRoiString($roi) {
	return sprintf("Array('x0' => %.3f, 'x1' => %.3f, 'y0' => %.3f, 'y1' => %.3f)",
		$roi['x0'], $roi['x1'], $roi['y0'], $roi['y1']);
}

//
// Stores an ROI as one property value: "x0,x1,y0,y1".
//
function pilotRoiCsv($roi) {
	return sprintf("%.3f,%.3f,%.3f,%.3f", $roi['x0'], $roi['x1'], $roi['y0'], $roi['y1']);
}

//
// Reads that back. Returns false on anything that is not four sane fractions in
// order, so a mangled property falls back to the compiled default instead of
// measuring an inverted or off-frame box.
//
function pilotParseRoi($value) {

	$parts = array_map('trim', explode(',', (string)$value));
	if (count($parts) != 4) {
		return false;
	}
	foreach ($parts as $part) {
		if (!is_numeric($part)) {
			return false;
		}
	}

	list($x0, $x1, $y0, $y1) = array_map('floatval', $parts);

	if ($x1 <= $x0 || $y1 <= $y0) return false;
	if ($x0 < 0 || $y0 < 0 || $x1 > 1 || $y1 > 1) return false;

	return array('x0' => $x0, 'x1' => $x1, 'y0' => $y0, 'y1' => $y1);
}
?>
