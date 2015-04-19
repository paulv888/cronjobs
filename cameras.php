#!/usr/bin/php
<?php
define("MY_DEVICE_ID", 215);
define("LASTIMAGEDIR", "/mnt/data/cameras/lastimage");
define("CAMERASDIR", "/mnt/data/cameras");
define("MAX_FILES_DIR", 1202);

require_once 'includes.php';

// 1 - 15041622571600.jpg
// 2 - 00606E608DDA(cam-2) motion alarm at 20150416215944
// 3 - 


$cameras = readCameraProperties(readCameras());
while (1) {
	foreach ($cameras AS $key => $camera) {
//		echo "in".$cameras[$key]['lastfiletime'].CRLF;
		$cameras[$key]['lastfiletime'] = movePictures($camera);
//		echo "out".$cameras[$key]['lastfiletime'].CRLF;
	}
	sleep(5);
        echo date("Y-m-d H:i:s").": ".UpdateLink(MY_DEVICE_ID)." My Link Updated <br/>\r\n";
}

function readCameras() {
	$mysql = 'SELECT id, description FROM ha_mf_devices 
			  WHERE ha_mf_devices.typeID = '.DEV_TYPE_CAMERA. ' AND inuse = 1';
	$cameras = FetchRows($mysql);
	return $cameras;
}

function readCameraProperties($cameras) {

	foreach ($cameras AS $key => $camera) {
		$mysql = 'SELECT ha_mi_property.description, ha_mf_device_properties.value FROM ha_mf_device_properties 
					JOIN ha_mi_property ON ha_mf_device_properties.propertyID = ha_mi_property.id 
				  WHERE ha_mf_device_properties.deviceID ='.$camera['id'];
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

	// echo ">$camera[description]".CRLF;

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
			//echo "nothing to do".CRLF;
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
							// New Motion Group on 1 minute interval OR max_files Else keep together

				if (is_null($group_dir) || (substr($group_dir,0,2) != date("H",$filetime)) ) {	// no dir found
					$nextcount = 1;
				} else {
					$nextcount = (int)(substr($group_dir,3)) + 1;
					PDOupdate("ha_cam_recordings", Array('count' => $numfiles), Array('folder' => $camera['properties']['DIRECTORY'].'/'.$datedir.'/'.$group_dir));
				}
				$group_dir = date("H",$filetime).'_'.str_pad($nextcount, 5, '0', STR_PAD_LEFT);
				$targetdir = $dir.$datedir.'/'.$group_dir;
				$numfiles = 0;

				// Do not generate alert on New Group /bc Max Files
				if ((int)(abs($filetime-$camera['lastfiletime']) / 60) >= 1 && $camera['properties']['ALERTS'] > 0) {
				//https://vlohome.homeip.net/index.php?option=com_content&view=article&id=238&Itemid=30&folder=/cam-8/2015-04-18/15_00003
					$html='<a href=\"https://vlohome.homeip.net/index.php?option=com_content&view=article&id=238&Itemid=30&folder='.$camera['properties']['DIRECTORY'].'/'.$datedir.'/'.$group_dir.'\">Goto Gallery</a>';
					Alerts($camera['properties']['ALERTS'], Array('deviceID' => $camera['id'], 'ha_alerts___v1' => $html));
				}
			} 

			if (!file_exists($dir.$datedir)) {
				mkdir($dir.$datedir);
			}
			if (!file_exists($targetdir)) {
				mkdir($targetdir);
				PDOinsert('ha_cam_recordings', Array('mdate' => date ("Y-m-d").'_', 'cam' => $camera['id'], 'event' => $group_dir, 'folder' => $camera['properties']['DIRECTORY'].'/'.$datedir.'/'.$group_dir));
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
		if (isset($newname)) {
			// echo "inside".CRLF;
			$thumbname = LASTIMAGEDIR.'/'.$camera['description'].'.jpg';
			createthumb($newname,$thumbname,200,200);
			PDOupdate("ha_cam_recordings", Array('count' => $numfiles), Array('folder' => $camera['properties']['DIRECTORY'].'/'.$datedir.'/'.$group_dir));
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
