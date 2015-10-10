#!/usr/bin/php
<?php
//define( 'DEBUG_CAMERAS', TRUE );
if (!defined('DEBUG_CAMERAS')) define( 'DEBUG_CAMERAS', FALSE );

define("MY_DEVICE_ID", 215);
define("LASTIMAGEDIR", "/mnt/data/cameras/lastimage");
define("CAMERASDIR", "/mnt/data/cameras");
define("MAX_FILES_DIR", 1202);
define("MOTION_URL1","https://vlohome.homeip.net/index.php?option=com_content&view=article&id=238&Itemid=30");
define("MOTION_URL2","https://vlohome.homeip.net/index.php?option=com_content&view=article&id=238&Itemid=527");

require_once 'includes.php';

$cameras = readCameras();

while (1) {
	foreach ($cameras AS $key => $camera) {
//		echo "in".$cameras[$key]['lastfiletime'].CRLF;
		if (!array_key_exists('Minimum Alert Files', $cameras[$key]['previous_properties'])) $cameras[$key]['previous_properties']['Minimum Alert Files']['value'] = 0;
		if (!array_key_exists('lastfiletime', $camera)) $cameras[$key]['lastfiletime'] = 0;
		$cameras[$key]['lastfiletime'] = movePictures($cameras[$key]);		// Make Prop?
//		echo "out".$cameras[$key]['lastfiletime'].CRLF;
	}
	sleep(15);
    echo date("Y-m-d H:i:s").": ".UpdateLink(array('callerID' => MY_DEVICE_ID))." My Link Updated".CRLF;
}

function readCameras() {
	$mysql = 'SELECT id AS deviceID,  description FROM ha_mf_devices 
			  WHERE ha_mf_devices.typeID = '.DEV_TYPE_CAMERA. ' AND inuse = 1';
			  
	$camrows = FetchRows($mysql);
	foreach ($camrows as $cam) {
		$cameras[] = getDevice($cam['deviceID']);			// TODO:: Add where to getDevice
		$cameras[getLastKey($cameras)]['previous_properties'] = getDeviceProperties(Array('deviceID' => $cam['deviceID']));
	}
	//
	// print_r($cameras);
	return $cameras;
}

function movePictures($camera) {

	echo date("Y-m-d H:i:s").": ".$camera['description'].CRLF;

	$files = array();
	$filetimes = array();
	$dir = $_SERVER['DOCUMENT_ROOT'].CAMERASDIR.$camera['previous_properties']['Directory']['value'].'/';
	$lastfiletime = $camera['lastfiletime'];
	if ($handle = opendir($dir)) {
		while (false !== ($file = readdir($handle))) {
			$file_parts = pathinfo($file);
			if ($file != "." && $file != ".." && !is_dir($dir.$file) && $file_parts['extension']=='jpg') {
				$files[] = $file;
				$filetimes[] = filemtime($dir.$file);
			}
		}
		closedir($handle);

		if (count($files) == 0) {
			echo date("Y-m-d H:i:s").": ".$camera['description']." Nothing to do.".CRLF;
			return $lastfiletime;
		}
		
 // echo '<pre>';
 // print_r($files);
 // print_r($filetimes);
 // echo '</pre>';

        //echo executeCommand(array('callerID' => MY_DEVICE_ID, 'messagetypeID' => MESS_TYPE_COMMAND, 'deviceID' => $camera['deviceID'], 'commandID' => COMMAND_SET_PROPERTY_VALUE, 'commandvalue' => 'Recording___1'));

		$params['callerID'] = MY_DEVICE_ID;
		$params['device'] = $camera;
		$params['deviceID'] = $camera['id'];
		$params['device']['previous_properties']['Recording']['value'] = STATUS_OFF;
		$params['device']['properties'] = array( 'Recording' => array ( 'value' => STATUS_ON));	// Make sure other props are empty
		if (DEBUG_CAMERAS) print_r(setDevicePropertyValue($params, 'Recording'));

		array_multisort($filetimes, $files); 
		//echo '<pre>';
		//print_r($files);
		//print_r($filetimes);
		$seq = 0;
		$group_dir = null;
		$numfiles = 0;
		$newgroupcreated = false;
		$smssend = false;
		$emailsend = false;

		// Sleep .5 second before moving...
		usleep(500000);
		foreach ($files as $index => $file) {
			$filetime = $filetimes[$index];		// Time of currently handled file
			// echo ">$file<".CRLF;

			$datedir = date ("Y-m-d", $filetime);
			if (is_null($group_dir)) {					// Did we find a group dir? If not find the last one and the num files in it
				if ($group_dir = findLastGroupDir($dir.$datedir)) {		
					$targetdir = $dir.$datedir.'/'.$group_dir;
					$numfiles  = iterator_count(new DirectoryIterator($targetdir)) - 2;
					$mysql = 'SELECT * FROM `ha_cam_recordings` WHERE folder ="'.$camera['previous_properties']['Directory']['value'].'/'.$datedir.'/'.$group_dir.'"';
					if (DEBUG_CAMERAS) echo $mysql.CRLF;
					$recording = FetchRow($mysql);
				} 
			}

			if ((int)(abs($filetime-$camera['lastfiletime']) / 60) >= 1 || $numfiles >= MAX_FILES_DIR) {  // New Motion Group on; 1 minute gap OR max_files
			
				// If we had an old dir, update this first
				//$camera['newfilename'] = $newfilename;
				$camera['datedir'] = $datedir;
				$camera['group_dir'] = $group_dir;
				$camera['smssend'] = $smssend;
				$camera['emailsend'] = $emailsend;
				$camera['numfiles'] = $numfiles;
				$camera['updatetype'] = false;
				closeGroup($camera);

				// Open new group dir
				echo date("Y-m-d H:i:s").": ".$camera['description']." Create new group directory.".CRLF;
				$group_dir = date("H-i-s",$filetime);
				$targetdir = $dir.$datedir.'/'.$group_dir;
				$numfiles = 0;
				$newgroupcreated = true;
				$recording['cam'] = $params['deviceID'];
				$recording['mdate'] = date ("Y-m-d").'_';
				$recording['event'] = $group_dir;
				$recording['folder'] = $camera['previous_properties']['Directory']['value'].'/'.$datedir.'/'.$group_dir;
				$recording['firstfiletime'] = date ("H:i:s",$filetime);
				unset($recording['id']);
				PDOinsert('ha_cam_recordings', $recording);
			} 

			if (!file_exists($dir)) {
				mkdir($dir);
			}
			if (!file_exists($dir.$datedir)) {
				mkdir($dir.$datedir);
			}
			if (!file_exists($targetdir)) {
				mkdir($targetdir);
				echo "TargetDir: ".$targetdir.CRLF;
			}

			if ($camera['lastfiletime'] != $filetime) $seq = 0;		// Handle multiple files per second
			$camera['lastfiletime'] = $filetime;
			//echo $dir.$file.'->';
			//echo $targetdir.'/'.date("Y-m-d H:i:s",$filetime).'_'.str_pad($seq, 2, '0', STR_PAD_LEFT).'.jpg'.CRLF;
			$newfilename = $targetdir.'/'.date('Y-m-d His',$filetime).'_'.str_pad($seq++, 2, '0', STR_PAD_LEFT).'.jpg';
			rename($dir.$file, $newfilename);
			$numfiles++;
		}	// Handled all file in old to new order
		echo date("Y-m-d H:i:s").": ".$camera['description']." Creating Thumbnail.".CRLF;
		if (!file_exists(LASTIMAGEDIR)) {
			mkdir(LASTIMAGEDIR);
		}
		$thumbname = LASTIMAGEDIR.'/'.$camera['description'].'.jpg';
		createthumb($newfilename,$thumbname,200,200);

		// Close group (for now)
		$camera['datedir'] = $datedir;
		$camera['group_dir'] = $group_dir;
		$camera['smssend'] = $smssend;
		$camera['emailsend'] = $emailsend;
		$camera['numfiles'] = $numfiles-1;
		$camera['updatetype'] = true;
		closeGroup($camera);

		return $filetime;
	}  
	return $lastfiletime;
}

function closeGroup($camera) {

			echo 'numfiles: '.$camera['numfiles'].CRLF;
			if (!empty($camera['previous_properties']['Minimum SMS Alert Files']['value']) && $camera['numfiles'] >= $camera['previous_properties']['Minimum SMS Alert Files']['value'])  {
				echo date("Y-m-d H:i:s").": ".$camera['description']." Creating SMS Alert.".CRLF;
				$html=MOTION_URL2.'&folder='.$camera['previous_properties']['Directory']['value'].'/'.$camera['datedir'].'/'.$camera['group_dir'].'"';
				echo executeCommand(array('callerID' => MY_DEVICE_ID, 'messagetypeID' => MESS_TYPE_COMMAND, 'deviceID' => $params['deviceID'], 'commandID' => COMMAND_RUN_SCHEME, 'schemeID' => 116, 'message' => $html ));
				$smssend = true;

			}
				
			if (!empty($camera['previous_properties']['Minimum Email Alert Files']['value']) && $camera['numfiles'] >= $camera['previous_properties']['Minimum Email Alert Files']['value'])  {
				echo date("Y-m-d H:i:s").": ".$camera['description']." Creating SMS Alert.".CRLF;
				$html='<a href="'.MOTION_URL1.'&folder='.$camera['previous_properties']['Directory']['value'].'/'.$camera['datedir'].'/'.$camera['group_dir'].'">Motion Detected</a>';
				echo executeCommand(array('callerID' => MY_DEVICE_ID, 'messagetypeID' => MESS_TYPE_COMMAND, 'deviceID' => $params['deviceID'], 'commandID' => COMMAND_RUN_SCHEME, 'schemeID' => 115, 'message' => $html ));
				$emailsend = true;
			}

			// update device
			$params['callerID'] = MY_DEVICE_ID;
			$params['device'] = $camera;
			$params['deviceID'] = $camera['id'];
			$params['previous_properties']['Recording']['value'] = STATUS_ON;
			$properties['Recording']['value'] = STATUS_OFF;
			$properties['Pictures']['value'] = $camera['numfiles'];
			$properties['Last Recording Folder']['value'] = $camera['previous_properties']['Directory']['value'].'/'.$camera['datedir'].'/'.$camera['group_dir'];
			$properties['Last File Time']['value'] = date("H:i:s",$camera['lastfiletime']); 
			$params['device']['properties'] = $properties;			

			if ($camera['updatetype']) {		// Previous old group closere, do no update type again
				if (!($recording['recording_typeID'] = getDeviceProperties(array('deviceID' => $params['deviceID'], 'description' => 'Last Recording Type'))['value'])) {
					$recording['recording_typeID'] = RECORDING_TYPE_MOTION_CAMERA;
				}
	
				if ($recording['recording_typeID'] != RECORDING_TYPE_CONTINUOUS) updateDeviceProperties($params);

				// Need to reset type after this to allwo for recognize MOTION i.e. emptpy
				// Ok for now, only cam with multiple sources, (Deck) will tell us about  cam motion

			}
		
			$recording['count'] = $camera['numfiles'];
			$recording['lastfiletime'] = date("H:i:s",$camera['lastfiletime']);
			$recording['smssend'] =  $camera['smssend'];
			$recording['emailsend'] = $camera['emailsend'];
			PDOupdate("ha_cam_recordings", $recording, array('folder' => $params['device']['properties']['Last Recording Folder']['value']));
			
	return $camera;
}


function findLastGroupDir($dir) {
// Last dir or nul if no dir found
	$files = Array();
	if (file_exists($dir)) {
		if ($handle = opendir($dir)) {
			while (false !== ($file = readdir($handle))) {
				if ($file != "." && $file != ".." && is_dir($dir.'/'.$file)) {
					$files[] = $file;
				}
			}
			closedir($handle);
			if (count($files)>0) {
				sort($files);
//print_r($files);
				return end($files);
			}
			return null;
			}
		}
	return null;
}
?>
