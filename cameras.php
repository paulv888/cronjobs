#!/usr/bin/php
<?php
define("MY_DEVICE_ID", 215);
define("LASTIMAGEDIR", "/mnt/data/cameras/lastimage");
define("CAMERASDIR", "/mnt/data/cameras");
define("MAX_FILES_DIR", 1202);
define("MIN_ALERT_FILES", 3);
define("MOTION_URL1","https://vlohome.homeip.net/index.php?option=com_content&view=article&id=238&Itemid=30");
define("MOTION_URL2","https://vlohome.homeip.net/index.php?option=com_content&view=article&id=238&Itemid=527");

require_once 'includes.php';

$cameras = readCameraProperties(readCameras());
while (1) {
	foreach ($cameras AS $key => $camera) {
//		echo "in".$cameras[$key]['lastfiletime'].CRLF;
		$cameras[$key]['lastfiletime'] = movePictures($camera);
//		echo "out".$cameras[$key]['lastfiletime'].CRLF;
	}
	sleep(5);
        echo date("Y-m-d H:i:s").": ".UpdateLink(MY_DEVICE_ID)." My Link Updated".CRLF;
}

function readCameras() {
	$mysql = 'SELECT id AS deviceID,  description FROM ha_mf_devices 
			  WHERE ha_mf_devices.typeID = '.DEV_TYPE_CAMERA. ' AND inuse = 1';
	$cameras = FetchRows($mysql);
	return $cameras;
}

function readCameraProperties($cameras) {

	foreach ($cameras AS $key => $camera) {
		$mysql = 'SELECT ha_mi_property.description, ha_mf_device_properties.value FROM ha_mf_device_properties 
					JOIN ha_mi_property ON ha_mf_device_properties.propertyID = ha_mi_property.id 
				  WHERE ha_mf_device_properties.deviceID ='.$camera['deviceID'];
		$props = FetchRows($mysql);
		unset($cams);
		foreach ($props as $prop) {
			$camprop[strtoupper(preg_replace('/\s+/', '', $prop['description']))] = $prop['value'];
		}
		$cameras[$key]['properties']=$camprop;
		$cameras[$key]['lastfiletime']=0;
	}
	return $cameras;
}

function movePictures($camera) {

	echo date("Y-m-d H:i:s").": ".$camera['description'].CRLF;

	$files = array();
	$filetimes = array();
	$dir = $_SERVER['DOCUMENT_ROOT'].CAMERASDIR.$camera['properties']['DIRECTORY'].'/';
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
	 
	 
		array_multisort($filetimes, $files); 
		//echo '<pre>';
		//print_r($files);
		//print_r($filetimes);
		$seq = 0;
		$group_dir = null;
		$numfiles = 0;
		$newgroupcreated = false;

		// Sleep .5 second before moving...
		usleep(500000);
		foreach ($files as $index => $file) {
			$filetime = $filetimes[$index];
			// echo ">$file<".CRLF;

			$datedir = date ("Y-m-d", $filetime);
			if (is_null($group_dir)) {									// Only execute on entry
				if ($group_dir = findLastGroupDir($dir.$datedir)) {			// Only if found
					$targetdir = $dir.$datedir.'/'.$group_dir;
					$numfiles  = iterator_count(new DirectoryIterator($targetdir)) - 2;
				} 
			}

			if ((int)(abs($filetime-$camera['lastfiletime']) / 60) >= 1 || $numfiles >= MAX_FILES_DIR) {			
							// New Motion Group on; 1 minute gap OR max_files

				echo date("Y-m-d H:i:s").": ".$camera['description']." Create new group directory.".CRLF;
				if (is_null($group_dir) || (substr($group_dir,0,2) != date("H",$filetime)) ) {	// no dir found
					$nextcount = 1;
				} else {
					$nextcount = (int)(substr($group_dir,3)) + 1;
					PDOupdate("ha_cam_recordings", Array('count' => $numfiles), Array('folder' => $camera['properties']['DIRECTORY'].'/'.$datedir.'/'.$group_dir));
				}
				$group_dir = date("H",$filetime).'_'.str_pad($nextcount, 5, '0', STR_PAD_LEFT);
				$targetdir = $dir.$datedir.'/'.$group_dir;
				$numfiles = 0;
				$newgroupcreated = true;

			} 

			if (!file_exists($dir)) {
				mkdir($dir);
			}
			if (!file_exists($dir.$datedir)) {
				mkdir($dir.$datedir);
			}
			if (!file_exists($targetdir)) {
				mkdir($targetdir);
				PDOinsert('ha_cam_recordings', Array('mdate' => date ("Y-m-d").'_', 'cam' => $camera['deviceID'], 'event' => $group_dir, 'folder' => $camera['properties']['DIRECTORY'].'/'.$datedir.'/'.$group_dir));
			}

			if ($camera['lastfiletime'] != $filetime) $seq = 0;
			$camera['lastfiletime'] = $filetime;
			//echo $dir.$file.'->';
			//echo $targetdir.'/'.date("Y-m-d H:i:s",$filetime).'_'.str_pad($seq, 2, '0', STR_PAD_LEFT).'.jpg'.CRLF;
			$newname = $targetdir.'/'.date('Y-m-d His',$filetime).'_'.str_pad($seq++, 2, '0', STR_PAD_LEFT).'.jpg';
			rename($dir.$file, $newname);
			$numfiles++;
		}	// Handled all file in old to new order
		// echo "Done: ".$numfiles.CRLF;
		if (isset($newname)) {		// i.e. we did something
			if ($newgroupcreated && $numfiles >= MIN_ALERT_FILES)  {
				echo date("Y-m-d H:i:s").": ".$camera['description']." Updating Status.".CRLF;
				$html='<a href="'.MOTION_URL1.'&folder='.$camera['properties']['DIRECTORY'].'/'.$datedir.'/'.$group_dir.'">Motion Detected</a>';
				$html1=MOTION_URL2.'&folder='.$camera['properties']['DIRECTORY'].'/'.$datedir.'/'.$group_dir.'"';
				UpdateStatus(MY_DEVICE_ID, array( 'deviceID' => $camera['deviceID'], 'device_description' => $camera['description'], 'status' => STATUS_ON, 'emailmessage' => $html, 'smsmessage' => $html1));
			}
			echo date("Y-m-d H:i:s").": ".$camera['description']." Creating Thumbnail.".CRLF;
			if (!file_exists(LASTIMAGEDIR)) {
				mkdir(LASTIMAGEDIR);
			}
			$thumbname = LASTIMAGEDIR.'/'.$camera['description'].'.jpg';
			createthumb($newname,$thumbname,200,200);
			PDOupdate("ha_cam_recordings", Array('count' => $numfiles), Array('folder' => $camera['properties']['DIRECTORY'].'/'.$datedir.'/'.$group_dir));
			UpdateStatus(MY_DEVICE_ID, array( 'deviceID' => $camera['deviceID'], 'status' => STATUS_OFF));
		}
		return $filetime;
	}  
	return $lastfiletime;
}

function findLastGroupDir($dir) {
// Last dir or nul if no dir found
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
				return end($files);
			}
			return null;
			}
		}
	return null;
}
?>
