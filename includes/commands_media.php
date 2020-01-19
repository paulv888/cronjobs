<?php
define("EXCLUDE_EXTENTSIONS", "nfo|tbn|txt|db");
define("ASSORTED_DIR", "_Assorted/");
define("REFRESH_RENAME", 0x01);
define("REFRESH_FIND_MOVE", 0x02);
define("REFRESH_NFO", 0x04);
define("REFRESH_THUMB", 0x08);		// Still bash process
define("REFRESH_LIBRARY", 0x10);
define("REFRESH_RECYCLE_DUPLICATE", 0x20);
define("REFRESH_CHECK_DUPLICATES", 0x40);
define("REFRESH_MOVE", 0x80);
define("REFRESH_CHECK_REVERSE", 0x100);
define("REFRESH_ALL", 0xFFFF);


//
//	Refresh all or All should be independent of DB. 
//		Unclear deleted info? strPath gone?
//


$genres = Array('80s', 'Classical', 'Country', 'Dance', 'Arabic', 'Bulgarian', 'Celtic', 'Japanese',  'OtherEastern',  'Polish',  'Russian',  'Meditation',  'Nederlands', 'Popular',  'Spanish',  'Tropical',  'Turkish', '_XXX_recyclebin');

$genrefolders = Array('/musicvideos/80s/', '/musicvideos/Classical/',
	'/musicvideos/Country/', '/musicvideos/Dance/', 'Eastern/Arabic/',
	'/musicvideos/Eastern/Bulgarian/', '/musicvideos/Eastern/Celtic/', 
	'/musicvideos/Eastern/Japanese/',  '/musicvideos/Eastern/OtherEastern/', 
	'/musicvideos/Eastern/Polish/',  '/musicvideos/Eastern/Russian/', 
	'/musicvideos/Meditation/',  '/musicvideos/Nederlands/',
	'/musicvideos/Popular/',  '/musicvideos/Spanish/', 
	'/musicvideos/Tropical/',  '/musicvideos/Turkish/');

/*Array
(
    [dirname] => /home/www/vlohome/data/musicvideos/_XXX_import/Popular/
    [basename] => Avicii - Waiting For Love.mp4
    [extension] => mp4
    [filename] => Avicii - Waiting For Love
    [batchfile] => 
    [newname] => Avicii - Waiting For Love
    [artist] => Avicii
    [title] => Waiting For Love
    [genre] => Popular
    [moveto] => /home/www/vlohome/data/musicvideos/_XXX_import/Popular/
)*/


//	Command in:
// 		$params
//
//  Command out:
//		$feedback type Array
//			with keys: 
//						'Name'   		(String)	-> Name of executed command						REQUIRED
//						'result'		(Array)		-> result (Going to log (Update Props or ...)	REQUIRED
//						'message' 		(String)	-> To display on remote
//						'commandstr' 	(String)	-> for eventlog, actual command send
//      if error then	'error'			(String)	-> Error description
//						Nothing else allowed 

// function templateFunction(&$params) {

	// $feedback['Name'] = 'templateFunction';
	// $feedback['commandstr'] = "I send this";
	// $feedback['result'] = array();
	// $feedback['message'] = "all good";
	//	$feedback['error'] = "Error - Could not find genre folder for: >".$file['genre']."<".CRLF;

// debug($feedback, 'feedback');
	// return $feedback;
// }

function refreshAllVideos_Delete(&$params = null) {
	debug($params, 'params');
	echo "<pre>";
	$dir = '';
	if (array_key_exists('commandvalue', $params) && !empty($params['commandvalue'])) {
		$dir = ucwords(rtrim($params['commandvalue'], '/') . '/');
	} 
	$params['directory'] = LOCAL_MUSIC_VIDEOS.'/'.$dir;
	$params['importprocess'] = false;
	$params['refreshvideooptions'] = REFRESH_ALL;
	// $params['refreshvideooptions'] = REFRESH_NFO;
	$feedback = refreshVideos_Delete($params);
	debug($feedback, 'feedback');
	return $feedback;
}

function findDuplicateVideos(&$params = null) {
//
// TODO:: export results to table, and allow fixing from there
//
	debug($params, 'params');
	echo "<pre>";
	$dir = '';
	if (array_key_exists('commandvalue', $params) && !empty($params['commandvalue'])) {
		$dir = ucwords(rtrim($params['commandvalue'], '/') . '/');
	} 

	$params['directory'] = LOCAL_MUSIC_VIDEOS.'/'.$dir;
	$params['importprocess'] = false;
	$params['refreshvideooptions'] = REFRESH_CHECK_DUPLICATES;
	$feedback = refreshVideos_Delete($params);
	if (!empty($feedback['result'])) {
		echo "Found Duplicate files: ".CRLF;
		print_r($feedback['result']);
	}
	if (!empty($feedback['findDuplicateByArtistTitle'])) {
		echo "Found Duplicate files, based on Artist - Title: ".CRLF;
		usort($feedback['findDuplicateByArtistTitle'], "cmp");	
		print_r($feedback['findDuplicateByArtistTitle']);
	}

	debug($feedback, 'feedback');
	return $feedback;
}

function refreshVideos_Delete(&$params) {
	debug($params, 'params');

	//mb_internal_encoding("UTF-8");

	set_time_limit(0);
	$feedback['Name'] = 'refreshVideos';

	// $feedback['message'] = "";
	// $feedback['error'] = "";
	// echo "<pre>";
	$files = array();

	$feedback['message'] = $params['directory'].CRLF;
	$result = readDirs($params['directory'], $params['importprocess']); 
	$files = $result['result'];
	usort($files, "cmp");

	if ($params['refreshvideooptions'] & REFRESH_MOVE && !$params['importprocess']) {
		$file = 'mv_vids.sh';
		file_put_contents($file, '#! /bin/bash'."\n");
	}

	foreach ($files as $index => $file) {
		$params['file'] = $file;
		$result = refreshVideo($params);
		$files[$index] = $params['file'];		// Keep files array in order
	}

	if ($params['refreshvideooptions'] & REFRESH_CHECK_DUPLICATES && !$params['importprocess']) {
		foreach ($files as $index => $file) {
			$newnames[] = $file['newname'];
			$params['file'] = $file;
			$result = findDuplicateByArtistTitle($params);
			if (!empty($result['result'])) {
				$feedback['findDuplicateByArtistTitle'][] = $result['result'];
			}
		}
		$feedback['result'] = checkDuplicates($newnames);
	} else {
		$feedback['result'] = $files;
	}

	debug($feedback, 'feedback');
	return $feedback;
}


function refreshVideo(&$params) {
	debug($params, 'params');


	$feedback['Name'] = 'refreshVideo';

	//$exectime = -microtime(true);
	// echo $options.CRLF;
	// $feedback['commandstr'] = "I send this";

	// Handle 1 video:
    // Scan Import/All
        // Completely downloaded || Kick-Off manual
    // Clean name							- DONE
	// Get Artist, Title & Ft				- DONE
	// Get Genre							- DONE
    // Move to directory
        // Have dir for artist				- DONE, BUT LOTS OF WRONG ARTIST
        // Artist found in Assorted?
            // 3 or more files?
            // Create Artist dir (where Dance/Pop...)
            // Move all files to New dir
    // Update Library
        // Create .nfo
        // Create thumbnail
        // Update Kodi library (install server version?)
        // Create mp3 version (Leave Separate, batch process)


	$params['file']['batchfile'] = '';
	//
	// Clean name
	//
	$result = '';
	$name = (array_key_exists('window_title',$params['file']) ? $params['file']['window_title'] : $params['file']['filename']);
	$result = cleanName($name);
	$params['file']['newname'] = $result['result'][0];
	$feedback[] = $result;

	//
	// Get Artist/Title
	//
	$result = getArtistTitle($params);
	if (array_key_exists('error',$result)) {
		$feedback[] = $result;
		debug($feedback, 'feedback');
		return $feedback;
	}
	$feedback[] = $result;

	if ($params['refreshvideooptions'] & REFRESH_CHECK_REVERSE) {
		$result = reverseArtistTitle($params);
	}
	if (array_key_exists('error',$result)) {
		$feedback[] = $result;
		debug($feedback, 'feedback');
	}

	//
	// Get Genre
	//
	$result = getGenre($params);
	if (array_key_exists('error',$result)) {
		$feedback[] = $result;
		debug($feedback, 'feedback');
		//return $feedback;
	}
	$feedback[] = $result;

	//
	// Find Dir to move to
	//
	if ($params['refreshvideooptions'] & REFRESH_FIND_MOVE) {
		$result = findMoveTo($params['file'], $params['importprocess']);
		$feedback[] = $result;
	} else {
		// echo "move none".CRLF;
		$result['result']['moveto'] = $params['file']['dirname'];
		$feedback[] = $result;
	}

	if (array_key_exists('error',$result)) {
		debug($feedback, 'feedback');
		//return $feedback;
	}
	$params['file']['moveto'] = $result['result']['moveto'];
	debug($params);

	//
	// Check for old version and abort process
	//
	if ($params['refreshvideooptions'] & REFRESH_CHECK_DUPLICATES) {
		$result = findDuplicateByArtistTitle($params);
		$feedback[] = $result;
		if (array_key_exists('error',$result)) {
			debug($feedback, 'feedback');
			//return $feedback;
		}
	}


	//
	// Create NFO
	//
	if ($params['refreshvideooptions'] & REFRESH_NFO) {
		// echo "move all".CRLF;
		$result = createNFO($params['file'], $params['importprocess']);
		$feedback[] = $result;
		if (array_key_exists('error',$result)) {
			$feedback['error'] = "NFO Error";
			debug($feedback, 'feedback');
			//return $feedback;
		}
	}

	//
	// Create Thumbnail
	//
	// 	Only for online - Import
	//
	if ($params['refreshvideooptions'] & REFRESH_THUMB) {
		$result = createThumbNail($params['file'], $params['importprocess']);
		$feedback[] = $result;
		if (array_key_exists('error',$result)) {
			$feedback['error'] = "Thumbnail Error";
			debug($feedback, 'feedback');
			return $feedback;
		}
	}


	//
	// Now move it
	//
	if ($params['refreshvideooptions'] & REFRESH_MOVE && ( ($params['file']['moveto'].$params['file']['newname'] != $params['file']['dirname'].$params['file']['filename']) ||  $params['file']['batchfile'] != '')) {
		if ($params['importprocess']) {		// Create batch file, and abort futher action?
			$params['createbatchfile'] = 0;
			$params['movetorecycle'] = 0;
			$params['CAMEFROM'] = "REFRESH ONLINE";
			$feedback[] = moveMusicVideo($params);
		} else {
			// echo "move all".CRLF;
			// echo "<pre>".$feedback['Name'].': '; print_r($params['file']); echo "</pre>";
			//$result = findMoveTo($params['file'], $params['importprocess']);
			$params['createbatchfile'] = 1;
			$params['movetorecycle'] = 0;
			$params['CAMEFROM'] = "REFRESH BATCH";
			$result = moveMusicVideo($params);
			$batchfile = $result['batchfile'];
			$feedback[] = $result;
			$file = 'mv_vids.sh';
			$batch = file_get_contents($file);
			file_put_contents($file, $batch . $batchfile);
		}
	}

	//$feedback['result'] = $params['file'];
	
	//if (array_key_exists('error', $feedback) && empty(trim($feedback['error']))) unset($feedback['error']);

	debug($feedback, 'feedback');
	return $feedback;

}

function findDuplicateByArtistTitle(&$params) {
	debug($params, 'params');

	$feedback['Name'] = 'findDuplicateByArtistTitle';
	$feedback['result'] = array();

	if (!$params['importprocess']) { // Find this video
		$mysql = 'SELECT mv.id FROM `xbmc_video_musicvideos` mv JOIN xbmc_path p ON mv.strPathID = p.id WHERE file = "'.mv_toPublic($params['file']['dirname'].$params['file']['basename']).'";'; 
		if (!($thismvid = FetchRow($mysql)['id'])) {
			$feedback['error'] = "Error - Abort, could not find myID for : ".$params['file']['dirname'].$params['file']['basename'];
			debug($feedback, 'feedback');
			return $feedback;
		}
		$params['mvid'] = $thismvid;
		$mysql = 'SELECT mv.* FROM xbmc_video_musicvideos mv 
					JOIN xbmc_path p ON mv.strPathID = p.id 
					WHERE (`artist` = "'.$params['file']['artist']. '" OR artist LIKE "'.$params['file']['artist']. ' /%" OR artist LIKE "%/ '.$params['file']['artist']. '")  
					AND title = "'.$params['file']['title'].'" AND mv.id <>'.$thismvid.';'; 
	} else {
		$mysql = 'SELECT mv.* FROM xbmc_video_musicvideos mv 
					JOIN xbmc_path p ON mv.strPathID = p.id 
					WHERE (`artist` = "'.$params['file']['artist']. '" OR artist LIKE "'.$params['file']['artist']. ' %" OR artist LIKE "% '.$params['file']['artist']. '")  
					AND title = "'.$params['file']['title'].'";'; 
	}
	// echo $mysql.CRLF;
	$feedback['message'] = '';
	if ($mvids = FetchRows($mysql)) {		
		// echo "<pre>".$feedback['Name'].': '; print_r($mvids); echo "</pre>";
		if (!empty($mvids)) { // Found this artist & title, so duplicate
			// Height 
			$mysql = 'SELECT iVideoHeight FROM `xbmc_video_streamdetails` WHERE iStreamType = 0 AND `idFile` = '.$mvids[0]['idFile'];
			$mvids[0]['height'] = '?';
			if ($row = FetchRow($mysql)) $mvids[0]['height'] = $row['iVideoHeight'];
			$feedback['result'] = $mvids;
			$feedback['error'] = "Duplicates found";
		}
	}
	debug($feedback, 'feedback');
	return $feedback;
}

function checkDuplicates($newnames) {
	debug($newnames, 'newnames');

	$feedback['Name'] = 'checkDuplicates';
	// Find duplicates
	// print_r(array_count_values($newnames));
	$dups = array();
	foreach(array_count_values($newnames) as $val => $c) {
		if($c > 1) $dups[] = $val;
	}
	debug($dups, 'dups');
	return $dups;
}

function readDirs($main, $importprocess){
	debug($main, 'main');
	//echo "Entry: ".$main.CRLF;
	$feedback['result'] = array();
	if (strpos($main, LOCAL_RECYCLE) !== false) {
		debug($feedback, 'feedback');
		return $feedback;
	}
	
	if (!$importprocess && strpos($main, LOCAL_IMPORT) !== false) {
		debug($feedback, 'feedback');
		return $feedback;
	}
	
	$dirHandle = opendir($main);
	$files = array();
	while($file = readdir($dirHandle)){
		// echo $main.$file.CRLF;
		if (is_dir($main.$file) && $file != '.' && $file != '..') {
			$files = array_merge ( $files, readDirs($main.$file.'/', $importprocess)['result']);
		} else {
			// echo $file.CRLF;
			$file_parts = mb_pathinfo($file);
			// print_r($file_parts);
			$file_parts['dirname'] = rtrim($main, '/') . '/';
			if (array_key_exists('extension', $file_parts)) {
				if (substr($file, 0, 1) != "." && $file != ".." && strpos(EXCLUDE_EXTENTSIONS, $file_parts['extension']) === false) {
					$files[] = $file_parts;
				}
			}
		}
	}
	$feedback['result'] = $files;
	debug($feedback, 'feedback');
	return $feedback;
}

function cmp($a, $b) {
	return strcmp($a["filename"],$b["filename"]);
}

function createNFO($file, $importprocess) {
	debug($file, 'file');

    // GetArtist $base
    // GetSong $base
    // GetGenre $dir
    // echo "    <musicvideo>" > "$nfofile"
    // echo "      <title>$song</title>"  >> "$nfofile"
    // echo "      <artist>$artist</artist>" >> "$nfofile"
    // echo "      <genre>$genre</genre>" >> "$nfofile"
    // echo "    </musicvideo>" >> "$nfofile"
    // chown media "$nfofile"
    // chgrp vloon "$nfofile"
    // chmod 665 "$nfofile"
    // echo "`date` Created nfo: $nfofile"


	$feedback['Name'] = 'createNFO';
	$feedback['result'] = array();
	// echo "<pre>".$feedback['Name'].': '; print_r($file); echo "</pre>";

	// $feedback['message'] = "all good";
	//	$feedback['error'] = "Error - Could not find genre folder for: >".$file['genre']."<".CRLF;
	$data = "\xEF\xBB\xBF".'<?xml version="1.0" encoding="UTF-8" standalone="yes" ?>'."\n";
	$artist = $file['artist'];
	$title = $file['title'];
	$genre = $file['genre'];
	$data .= "<musicvideo>\n   <title>$title</title>\n   <genre>$genre</genre>\n   <artist>$artist</artist>\n   <album>$artist/All</album>";
	if (array_key_exists('sidekicks', $file)) {
		$sidekicks = parseSideKicks($file['sidekicks']);
		foreach ($sidekicks as $sidekick) {
			$data .= "<artist>".$sidekick."</artist>";
		}
	}
	// FileInfo
   // <fileinfo>
       // <streamdetails>
           // <video>
               // <codec></codec>
               // <aspect></aspect>
               // <width></width>
               // <height></height>
               // <durationinseconds></durationinseconds>
               // <stereomode></stereomode>
           // </video>
           // <audio>
               // <codec></codec>
               // <language></language>
               // <channels></channels>
           // </audio>
       // </streamdetails>
   // </fileinfo>	
	$result = readFFProbe($file['dirname'].$file['filename'].'.'.$file['extension']);
	if (array_key_exists('error',$result)) {
		$feedback[] = $result;
		debug($feedback, 'feedback');
		return $feedback;
	}
	$feedback[] = $result;
	$streaminfo = $result['result'];
	$data .= "\n   <fileinfo>";
	$data .= "\n      <streamdetails>";
	$data .= "\n         <video>";
	$data .= "\n            <codec>".$streaminfo[0]['codec_name']."</codec>";
	$data .= "\n            <aspect>".$streaminfo[0]['width']/$streaminfo[0]['height']."</aspect>";
	$data .= "\n            <width>".$streaminfo[0]['width']."</width>";
	$data .= "\n            <height>".$streaminfo[0]['height']."</height>";
	$str_time = 0;
	if (array_key_exists(['TAG:DURATION'], $streaminfo[0])) {
		$str_time = $streaminfo[0]['TAG:DURATION'];
		$str_time = preg_replace("/^([\d]{1,2})\:([\d]{2})$/", "00:$1:$2", $str_time);
	} 
	sscanf($str_time, "%d:%d:%d", $hours, $minutes, $seconds);
	$time_seconds = $hours * 3600 + $minutes * 60 + $seconds;
	$data .= "\n            <durationinseconds>".$time_seconds."</durationinseconds>";
	$data .= "\n         </video>";
	$data .= "\n         <audio>";
	$data .= "\n            <codec>".$streaminfo[1]['codec_name']."</codec>";
	$data .= "\n            <language>".$streaminfo[1]['TAG:language']."</language>";
	$data .= "\n            <channels>".$streaminfo[1]['channels']."</channels>";
	$data .= "\n         </audio>";
	$data .= "\n      </streamdetails>";
	$data .= "\n   </fileinfo>";
	debug($streaminfo);
	debug($feedback);
	$data .= "\n</musicvideo>";
	$data = mb_convert_encoding($data, 'UTF-8', 'auto');
	if ($importprocess) {
		file_put_contents($file['dirname'].$file['filename'].'.nfo', $data);
	} else {
		file_put_contents($file['dirname'].$file['filename'].'.nfo', $data);
	}
	debug($feedback, 'feedback');
	return $feedback;

/*
<?xml version="1.0" encoding="UTF-8" standalone="yes" ?>
<videodb>
    <version>1</version>
    <musicvideo>
        <title>Waves Of Luv</title>
        <rating>0.000000</rating>
        <epbookmark>0.000000</epbookmark>
        <year>0</year>
        <top250>0</top250>
        <track>-1</track>
        <album></album>
        <votes></votes>
        <outline></outline>
        <plot></plot>
        <tagline></tagline>
        <runtime>3</runtime>
        <mpaa></mpaa>
        <playcount>0</playcount>
        <lastplayed></lastplayed>
        <file></file>
        <path>smb://SRVMEDIA/media/My Music Videos/Dance/_Assorted/</path>
        <filenameandpath>smb://SRVMEDIA/media/My Music Videos/Dance/_Assorted/2black - Waves Of Luv.avi</filenameandpath>
        <basepath>smb://SRVMEDIA/media/My Music Videos/Dance/_Assorted/2black - Waves Of Luv.avi</basepath>
        <id></id>
        <genre>Dance</genre>
        <set></set>
        <premiered></premiered>
        <status></status>
        <code></code>
        <aired></aired>
        <trailer></trailer>
        <fileinfo>
            <streamdetails>
                <video>
                    <codec>dx50</codec>
                    <aspect>1.927711</aspect>
                    <width>640</width>
                    <height>332</height>
                    <durationinseconds>228</durationinseconds>
                    <stereomode></stereomode>
                </video>
                <audio>
                    <codec>mp3</codec>
                    <language></language>
                    <channels>2</channels>
                </audio>
            </streamdetails>
        </fileinfo>
        <artist>2black</artist>
        <resume>
            <position>0.000000</position>
            <total>0.000000</total>
        </resume>
        <dateadded>2010-12-24 13:59:25</dateadded>
        <art>
            <thumb>smb://SRVMEDIA/media/My Music Videos/Dance/_Assorted/2black - Waves Of Luv.tbn</thumb>
        </art>
    </musicvideo>
	*/
}

function getArtistTitle(&$params) {
	debug($params, 'params');
	$fname = $params['file']['newname'];

	// Expecting correct format "
	//              Artist1 & Artist2 - Name-Song Ft Sidekick 1 Sidekick2"

	$feedback['Name'] = 'getArtistTitle';
	$feedback['result'] = array();
	$result = preg_match( '/^.*? & .*? -/', $fname, $m);	// Found & -
	if ($result) {
		$result = preg_match( '/^.*? & /', $fname, $m);	// Found BOL to & part
		if (!array_key_exists('0',$m)) {
			$feedback['error'] = "No Artist";
		}
		$feedback['result']['artist'] =  substr($m[0], 0, -3);
		$result = preg_match( '/ & .*? - /', $fname, $m);	// Get  & - part
		$feedback['result']['sidekicks'] =  substr($m[0], 3, -3);
	} else {
		$result = preg_match( '/^.*? -/', $fname, $m);
		if (!array_key_exists('0',$m)) {
			$feedback['error'] = "No Artist";
			// echo "No title found: >$fname<".CRLF;
		} else {
			$feedback['result']['artist'] =  substr($m[0], 0, -2);
		}
	}

	$result = preg_match(  '/ - .*? Ft /', $fname, $m);
	if ($result) {
		$feedback['result']['title'] =  substr($m[0], 3, -4);
		$result = preg_match(  '/ Ft .*?$/', $fname, $m);
		$feedback['result']['sidekicks'] = (array_key_exists('sidekicks', $feedback['result']) 	? $feedback['result']['sidekicks'].' '.substr($m[0], 4) : substr($m[0], 4));
	} else {
		$result = preg_match(  '/ - .*?$/', $fname, $m);
		if (!array_key_exists('0',$m)) {
			$feedback['error'] = "No Title";
		} else {
			$feedback['result']['title'] =  substr($m[0], 3);
		}
	}

	// $feedback['message'] = "";
	// if () $feedback['error'] = "Not so good";

	if (array_key_exists('artist', $feedback['result'])) $params['file']['artist'] = $feedback['result']['artist'];
	if (array_key_exists('title', $feedback['result'])) $params['file']['title'] = $feedback['result']['title'];
	if (array_key_exists('sidekicks', $feedback['result'])) $params['file']['sidekicks'] = $feedback['result']['sidekicks'];
	debug($feedback, 'feedback');
	return $feedback;
}

function reverseArtistTitle(&$params) {
	debug($params, 'params');

	// Expecting correct format "
	//              Artist1 & Artist2 - Name-Song Ft Sidekick 1 Sidekick2"

	$feedback['Name'] = 'reverseArtistTitle';

	$mysql = 'SELECT * FROM xbmc_video_musicvideos mv 
				JOIN xbmc_path p ON mv.strPathID = p.id 
				WHERE artist = "'.$params['file']['artist']. '";';
	if (!($mvids = FetchRows($mysql))) {	// Did not find this artist, check title if maybe reversed
		$feedback['error'] = "Artist not found";
		$mysql = 'SELECT * FROM xbmc_video_musicvideos mv 
					JOIN xbmc_path p ON mv.strPathID = p.id 
					WHERE artist = "'.$params['file']['title']. '";';
		if ($mvids = FetchRows($mysql)) {	// Did find title as artist	
			$temp = $params['file']['artist'];
			$params['file']['artist'] = $params['file']['title'];
			$params['file']['title'] = $temp;
			$temp = $params['file']['newname'];
			$params['file']['newname'] = trim(preg_replace('/(.*?) - (.*?)($|Ft.*?$)/', '$2 - $1 $3', $params['file']['newname'])); 
			$feedback['error'] = "Reversed artist-title";
		}
	}

	debug($feedback, 'feedback');
	return $feedback;
}


function getGenre(&$params) {
	
	debug($params, 'params');

	global $genres;
	$feedback['Name'] = 'getGenre';
	$path_break = explode('/', $params['file']['dirname']);
	$result = array_intersect($genres, $path_break);

	if (empty($result)) {
		$params['file']['genre'] = (!empty($params['file']['genre']) ? $params['file']['genre'] : "Not Found");
	} else {
		$params['file']['genre'] = reset($result);
	}

	$feedback['result'] = $result;
	// print_r($result);

	debug($feedback, 'feedback');
	return $feedback;
}


function findBestMatch(&$file, $mvids) {
	debug($file, 'file');

	$file['moveto'] = LOCAL_DATA.$mvids[0]['strPath'];
	$paths = array();
	// echo "<pre>";
	// print_r($mvids);
	foreach($mvids as $mvid) {	// count occurences?
		// [0] => Array
		// (
			// [id] => 322
			// [idFile] => 3678
			// [title] => Crying At The Discoteque
			// [artist] => Alcazar
			// [genre] => Popular
			// [media_type] => musicvideo
			// [c12] => -1
			// [file] => smb://SRVMEDIA/media/My Music Videos/Popular/Alcazar/Alcazar - Crying At The Discoteque.flv
			// [strPathID] => 322
			// [strFileName] => Alcazar - Crying At The Discoteque.flv
			// [playCount] => 
			// [lastPlayed] => 
			// [dateAdded] => 2010-12-24 16:47:50
			// [strPath] => /musicvideos/Popular/_Assorted/
		// )
		if (array_key_exists($mvid['strPath'], $paths)) {
			$paths[$mvid['strPath']]['count']++;
		} else  {
			$paths[$mvid['strPath']]['count'] = 1;
			$paths[$mvid['strPath']]['genre'] = $mvid['genre'];
		}
	}
	// echo "<pre>";
	uasort($paths, function($a, $b) {
		return ($a['count'] <=> $b['count']) * -1;
	});
	// print_r($paths);
	$file['moveto'] = LOCAL_DATA.key($paths);
	if (strpos($file['genre'],'Not Found') !==null) {
			$file['genre'] = $paths[key($paths)]['genre'];
			// echo "updated genre: ". $file['genre'].CRLF; 
	}
	// echo "mfrequent: ". $file['moveto'].CRLF; 
	foreach($paths as $key => $value) {	// try to find non asorted
		if (strpos($key, ASSORTED_DIR) === false) {
			$file['moveto'] = LOCAL_DATA.$key;
			// echo "improved: ". $file['moveto'].CRLF; 
			break;
		}
	}

	debug($file, 'file');
	return;
	// echo "<pre> Same Genre".': '; print_r($mvids); echo "</pre>";
}

function findMoveTo(&$file, $importprocess) {
	debug($file, 'file');

	global $genres;
	global $genrefolders;

	if ($importprocess) {
		$allow_change_genre = true;
	} else {
		$allow_change_genre = false;
		$allow_change_genre = true;
	}

	$feedback['Name'] = 'findMoveTo';
	$file['moveto'] = $file['dirname'];

	$mysql = 'SELECT * FROM xbmc_video_musicvideos mv 
				JOIN xbmc_path p ON mv.strPathID = p.id 
				WHERE (`artist` = "'.$file['artist']. '" OR artist LIKE "'.$file['artist']. ' /%" )
				AND genre = "'.$file['genre'].'";'; 
	// echo $mysql.CRLF;

	if ($mvids = FetchRows($mysql)) {		// Found this artist for this genre
		// extract dir 
		// Just get he first one? Care about majority?
		findBestMatch($file, $mvids);
	} else {								// Try to find artist
		$mysql = 'SELECT * FROM xbmc_video_musicvideos mv 
					JOIN xbmc_path p ON mv.strPathID = p.id 
					WHERE artist = "'.$file['artist']. '";';
		if ($mvids = FetchRows($mysql)) {		// Found this artist
			findBestMatch($file, $mvids);
		} else {								// Must be new artist -> going to assorted for input genre
			if (($key = array_search($file['genre'], $genres)) !== false) {
				$key = array_search($file['genre'], $genres);
				$file['moveto'] = LOCAL_DATA.$genrefolders[$key].ASSORTED_DIR;
				// echo "<pre> New Artist".': '; print_r($mvids); echo "</pre>";
			} else {
				$feedback['error'] = "No Genre";
			}
		}
	}

	$feedback['result'] = $file;
	debug($feedback, 'feedback');
	return $feedback;
}

function readFFProbe($file) {
	// $file=$params['commandvalue'];
	// debug($params, 'params');
	debug($file, 'file');

	$feedback['Name'] = 'readFFProbe';
	$feedback['result'] = array();
	$params = '-v error -show_streams -of default=noprint_wrappers=1 '.'"'.$file.'"';
	$cmd = getPath().'/bin/ffprobe.sh '.$params;
	$feedback['commandstr'] = $cmd;
    exec($cmd, $output, $exitCode);
	debug($output, 'exec');
	if ($exitCode != 0) {
		$feedback['error'] = "Error FFProbe $exitCode";
	}
	$feedback['result_raw'] = $output;
	$feedback['exitCode'] = $exitCode;
	debug($feedback, 'feedback');
	
	$feedback['result_raw'] = $output;
	$index = 0;
	foreach ($output as $line) {
		$temp = explode('=', $line);
		if ($temp[0] == 'index') {
			$index = (int)$temp[1];
			$streaminfo[$index] = array();
		}
		$streaminfo[$index][$temp[0]] = $temp[1];
	}
	
	$feedback['result'] = $streaminfo;
	return $feedback;
}

function createThumbNail($file, $importprocess) {
	debug($file, 'file');

	$feedback['Name'] = 'createThumb';
	$feedback['result'] = array();
	$feedback['message'] = "No thumbnails created, kick of on srvmedia: </br>sudo ~/bin/spawn_create_thumbs";
	if (!$importprocess) return $feedback; 
	$feedback['message'] = "";
	$params = '"'.$file['dirname'].'" "'.$file['filename'].'" "'.$file['extension'].'"';
	$cmd = getPath().'/bin/createThumb.sh '.$params;
	$feedback['commandstr'] = $cmd;
    exec($cmd, $output, $exitCode);
	debug($output, 'exec');
	if ($exitCode != 0) {
		$feedback['error'] = "Error FFMPEG $exitCode";
	}
	$feedback['result'] = $output;
	$feedback['exitCode'] = $exitCode;
	debug($feedback, 'feedback');
	return $feedback;
}

function importDirectories(&$params) {


	debug($params, 'params');

	$params['directory'] = LOCAL_IMPORT.'/';
	$params['importprocess'] = true;
	$params['refreshvideooptions'] = REFRESH_ALL;


	set_time_limit(0);
	$feedback['Name'] = 'refreshVideos';

	$files = array();

	$result = readDirs($params['directory'], $params['importprocess']); 
	$files = $result['result'];
	usort($files, "cmp");

	//PARSE RESULTS
	foreach($files as $file) {
		try {
			$feedback['result'][] = PDOInsert("vd_queue", array('statusID' => Q_QUEUED,  'window_title' => $file['filename'], 'filename' => $file['dirname'].$file['basename'] ));
		} catch (Exception $e) {
			$feedback['error'] = 'Error: On insert on vd_queue';
		}
	}
	$feedback['message'] = count($files)." files queued.";

	debug($feedback, 'feedback');
	return $feedback;
}

function addToQueue(&$params) {

	debug($params, 'params');

	$feedback['Name'] = 'addToQueue';
	$feedback['received'] = $params['commandvalue'];
	$feedback['result'] = array();

	$feedback['commandstr'] = 'PDOInsert: '.$params['commandvalue'];
	if (empty($params['commandvalue'])) {
		$feedback['error'] = 'Error: commandvalue is empty';
		return $feedback;
	}
	if (empty($params['value_parts'][0])) {
		$feedback['error'] = 'Error: URL is empty';
		return $feedback;
	}
	if (empty($params['value_parts'][1])) {
		$feedback['error'] = 'Error: windows title is empty';
		return $feedback;
	}

	$feedback['message'] = "";
	$params['value_parts'][0] = urldecode($params['value_parts'][0]);
	$params['value_parts'][1] = urldecode($params['value_parts'][1]);
	$result = cleanName($params['value_parts'][1]);
	if (array_key_exists('message', $result)) {
		$feedback['message'] = $result['message'];
	}
	$windowTitle = $result['result'][0];

	try {
		if ($windowTitle != "Playlist") {
			$feedback['result'][] = PDOInsert("vd_queue", array('url' => $params['value_parts'][0], 'window_title' => $windowTitle));
			$feedback['message'] .= "Inserted: ".$windowTitle;
		} else {
			$feedback['result'][] = PDOInsert("vd_queue", array('statusID' => 5, 'url' => $params['value_parts'][0], 'window_title' => $windowTitle));
			$feedback['message'] .= "Inserted: ".$windowTitle;
		}
	} catch (Exception $e) {
		$feedback['error'] = 'Error: On insert on vd_queue';
	}
	debug($feedback, 'feedback');
	return $feedback;
} 

function handleDownloadQueue(&$params) {
	
	debug($params, 'params');

	$feedback['Name'] = 'handleDownloadQueue';
	// $feedback['message'] = "all good";
	// if () $feedback['error'] = "Not so good";

	//	debug($stepValue, 'stepValue');
    $feedback['result'] = array();

	$mysql = 'SELECT * FROM `vd_queue` q WHERE statusID IN ("'.Q_PLAYLIST_DOWNLOAD.'","'.Q_QUEUED.'","'.Q_VERIFY_SUCCESS.'","'.Q_DOWNLOAD_SUCCESS.'");'; 


//
//	Flow 1 FILE:
//                                                    -----> has(FILENAME) - SKIP ----->   
//                                                  ^                                  |
//                                                  |                                  V
//		addToQueue -> QUEUED -> VERIFYING -> VERIFY_SUCCESS  -> DOWNLOADING  -> DOWNLOAD_SUCCESS -> IMPORTING -> IMPORT_SUCCESS
//                       ^                          ^                                                         -> IMPORT_FAILED
//                       |                          |                        -> DOWNLOAD_FAILED (STOP)     
//                       |                -> VERIFY_FAILED    
//                       |                          |
//						 ------------<---------------
//
	while ($row = FetchRow($mysql)) {
			switch ($row['statusID'])
			{
			case Q_PLAYLIST_DOWNLOAD:
				$url = $row['url'];
				PDOUpdate('vd_queue', array('statusID' => Q_DOWNLOADING) , array('id' => $row['id']));
				$feedback = youtubeDL($url, YT_GET_PLAYLIST);
				$pretty_result = $row['result'].'<pre>DOWNLOAD<br/>'.prettyPrint(json_encode($feedback,JSON_UNESCAPED_SLASHES)).'</pre>';
				debug($params);
				if (array_key_exists('error', $feedback)) {
					PDOUpdate('vd_queue', array('statusID' => Q_DOWNLOAD_FAILED, 'last_error' => $feedback['error'], 'result' => $pretty_result) , array('id' => $row['id']));
				} else {
					//PARSE RESULTS
					foreach($feedback['result'] as $download) {
						$video = json_decode($download, true);
						debug($video);
						try {
							$feedback['result'][] = PDOInsert("vd_queue", array('url' => 'youtube.com/watch?v='.$video['url'], 'window_title' => $video ['title']));
						} catch (Exception $e) {
							$feedback['error'] = 'Error: On insert on vd_queue';
						}
					}
					PDOUpdate('vd_queue', array('statusID' => Q_IMPORT_SUCCESS, 'last_error' => '', 'result' => $pretty_result) , array('id' => $row['id']));
				}
				break;
			case Q_QUEUED:
				$url = $row['url'];
				// PDOUpdate('vd_queue', array('statusID' => Q_PROCESSING) , array('id' => $row['id']));
				$params['importprocess'] = true;
				$params['refreshvideooptions'] = REFRESH_ALL & ~REFRESH_MOVE & ~REFRESH_THUMB & ~REFRESH_NFO;
				$params['file']['window_title'] = $row['window_title'];
				$params['file']['genre'] = $row['genre'];
				if (empty($row['filename'])) {				
					$params['file']['dirname'] = LOCAL_IMPORT. '/';
				} else {		// Importing dir
					$file_parts = mb_pathinfo($row['filename']);
					$params['file']['dirname'] = $file_parts['dirname']. '/';
				}
				$feedback = refreshVideo($params);
				debug($params);
				$pretty_result = $row['result'].'<br/><pre>VERIFY<br/>'.prettyPrint(json_encode($feedback,JSON_UNESCAPED_SLASHES)).'</pre>';
				$store['artist']   = (array_key_exists('artist', $params['file']) ? $params['file']['artist'] : '');
				$store['title']    = (array_key_exists('artist', $params['file']) ? $params['file']['title'] : '');
				$store['genre']    = (array_key_exists('genre', $params['file']) ? $params['file']['genre'] : '');
				if (array_key_exists('moveto', $params['file'])) {
					$store['moved_to'] =  $params['file']['moveto'];
					$store['subdir_move_to'] =  substr(rtrim($params['file']['moveto'],'/'), strrpos(rtrim($params['file']['moveto'],'/'), '/')+1);
				} else {
					$store['moved_to'] =  "";
					$store['subdir_move_to'] =  "";
				}
				$store['newname'] = (array_key_exists('moveto', $params['file']) ? $params['file']['newname'] : '');
				$store['result']   = $pretty_result;

				$error = "";
				foreach ($feedback as $step) {
					if (is_array($step) && array_key_exists('error', $step)) {
						if ($step['error'] == "Duplicates found" ) {
							$store['duplicate_idFile'] = $step['result'][0]['idFile'];
							$error .= $step['error'].' '.$step['result'][0]['height'].CRLF;
						} else {
							$error .= $step['error'].CRLF;
						}
					}
				}
				if ($store['genre'] == 'Not Found') $error .= "No Genre".CRLF;
				$store['last_error'] = 	substr($error, 0, -5);
				if (!empty($error)) {
					$store['statusID'] = Q_VERIFY_FAILED;
					PDOUpdate('vd_queue', $store, array('id' => $row['id']));
				} else {
					$store['statusID'] = Q_VERIFY_SUCCESS;
					PDOUpdate('vd_queue', $store , array('id' => $row['id']));
				}
				break;
			case Q_VERIFY_SUCCESS:
				$isDownloaded = !empty($row['filename']);
				if (!$isDownloaded) {
					$url = $row['url'];
					PDOUpdate('vd_queue', array('statusID' => Q_DOWNLOADING) , array('id' => $row['id']));
					$feedback = youtubeDL($url);
					$pretty_result = $row['result'].'<pre>DOWNLOAD<br/>'.prettyPrint(json_encode($feedback,JSON_UNESCAPED_SLASHES)).'</pre>';
					debug($params);
				} else {
					$pretty_result = $row['result'].'<pre>SKIPPING DOWNLOAD<br/></pre>';
					$feedback['filename'] = $row['filename'];
				}
				if (array_key_exists('error', $feedback)) {
					PDOUpdate('vd_queue', array('statusID' => Q_DOWNLOAD_FAILED, 'last_error' => $feedback['error'], 'result' => $pretty_result) , array('id' => $row['id']));
				} else {
					PDOUpdate('vd_queue', array('statusID' => Q_DOWNLOAD_SUCCESS, 'filename' => $feedback['filename'], 'last_error' => '', 'result' => $pretty_result) , array('id' => $row['id']));
				}
				break;
			case Q_DOWNLOAD_SUCCESS:
				$url = $row['url'];
				PDOUpdate('vd_queue', array('statusID' => Q_PROCESSING) , array('id' => $row['id']));

				$params['importprocess'] = true;
				$params['refreshvideooptions'] = REFRESH_ALL & ~REFRESH_CHECK_DUPLICATES &~REFRESH_CHECK_REVERSE;
				$file_parts = mb_pathinfo($row['filename']);
				$file_parts['dirname'] = rtrim($file_parts['dirname'], '/') . '/';
				$params['file'] = $file_parts;
				$params['file']['window_title'] = $row['newname'];
				$params['file']['genre'] = $row['genre'];
				$feedback = refreshVideo($params);
				debug($params);
				$pretty_result = $row['result'].'<br/><pre>IMPORT<br/>'.prettyPrint(json_encode($feedback,JSON_UNESCAPED_SLASHES)).'</pre>';
				$store['artist']   = (array_key_exists('artist', $params['file']) ? $params['file']['artist'] : '');
				$store['title']    = (array_key_exists('artist', $params['file']) ? $params['file']['title'] : '');
				$store['genre']    = (array_key_exists('genre', $params['file']) ? $params['file']['genre'] : '');
				if (array_key_exists('moveto', $params['file'])) {
					$store['moved_to'] =  $params['file']['moveto'];
					$store['subdir_move_to'] =  substr(rtrim($params['file']['moveto'],'/'), strrpos(rtrim($params['file']['moveto'],'/'), '/')+1);
				} else {
					$store['moved_to'] =  "";
					$store['subdir_move_to'] =  "";
				}
				$store['newname'] = (array_key_exists('moveto', $params['file']) ? $params['file']['newname'] : '');
				$store['result']   = $pretty_result;
				$error = "";
				foreach ($feedback as $step) {
					if (is_array($step) && array_key_exists('error', $step)) {
						if ($step['error'] == "Duplicates found" ) {
							$store['duplicate_idFile'] = $step['result'][0]['idFile'];
							$error .= $step['error'].' '.$step['result'][0]['height'].CRLF;
						} else {
							$error .= $step['error'].CRLF;
						}
					}
				}
				if ($store['genre'] == 'Not Found') $error .= "No Genre".CRLF;
				$store['last_error'] = 	substr($error, 0, -5);
				if (!empty($error)) {
					$store['statusID'] = Q_IMPORT_FAILED;
					PDOUpdate('vd_queue', $store, array('id' => $row['id']));
				} else {
					$store['statusID'] = Q_IMPORT_SUCCESS;
					PDOUpdate('vd_queue', $store , array('id' => $row['id']));
				}
				break;
			default:
				$feedback = $func($params);
				break;
			}
//		}
	}
	debug($feedback, 'feedback');
	return $feedback;
}

function findVideoArtistTitle($params) {

	debug($params, 'params');
	// echo "<pre>";
	// print_r($params);
	// echo "</pre>";
	// exit;
	
	$feedback['Name'] = 'findVideoArtistTitle';
	$feedback['received'] = $params['commandvalue'];
	$feedback['result'] = array();
	$name = urldecode($params['commandvalue']);
	$result = cleanName($name);
	$params['file']['newname'] = $result['result'][0];
	$feedback[] = $result;
	$url = '/index.php/music-videos?resetfilters=1';
	$feedback['redirect'] = "refresh:3;url=".$url;
	if (array_key_exists('error',$result)) {
		$feedback[] = $result;
		debug($feedback, 'feedback');
		return $feedback;
	}

	//
	// Get Artist/Title
	//
	$result[] = getArtistTitle($params);
	$result[] = reverseArtistTitle($params);
	if (array_key_exists('error',$result)) {
		$feedback[] = $result;
		debug($feedback, 'feedback');
		return $feedback;
	}
	$artist = $params['file']['artist'];
	$title  = substr($params['file']['title'], 0, 1);
	$search = '&xbmc_video_mvids___artist[condition]=CONTAINS&xbmc_video_mvids___artist[value][]=%s';
	$search .= '&xbmc_video_mvids___title[condition]=BEGINS WITH&xbmc_video_mvids___title[value][]=%s';
	$feedback['redirect'] = "Location: ".sprintf($url.$search, $artist, $title);
	$feedback['commandstr'] = $feedback['redirect'];
	debug($feedback, 'feedback');
	return $feedback;

}


function youtubeDL($url, $options = YT_VIDEO_ONLY) {
	
	debug($url, 'url');

	$feedback['Name'] = 'youtube-dl';
	// $feedback['message'] = "all good";
	// if () $feedback['error'] = "Not so good";

	//	debug($stepValue, 'stepValue');
    $feedback['result'] = array();
	if ($options == YT_GET_PLAYLIST) $textOptions = "-j --flat-playlist";
	if ($options == YT_VIDEO_ONLY) $textOptions = "--no-playlist ";
	$cmd = getPath().'/bin/youtube-dl.sh "'.$textOptions.'" "'.$url.'"';
	$feedback['commandstr'] = $cmd;
    debug($cmd, 'command');
    exec($cmd, $output, $exitCode);
	$feedback['result'] = $output;
	$feedback['exitCode'] = $exitCode;
	debug($output, 'exec');
	if ($exitCode != 0) {
		$feedback['error'] = "Error Youtube-DL $exitCode";
		return $feedback;
	}


	switch ($options) {
	case YT_GET_PLAYLIST:
		if (count($output) == 1) {
			$feedback['error'] = "No Playlist";
		}
		break;
	case YT_VIDEO_ONLY:
		$strOut = implode(PHP_EOL, $output);
		if (strpos($strOut, '[download] 100%') !== null) {
			// $found = preg_match('/\[info\] Writing video description metadata as JSON to: (.*)\n/',$output,$matches);
			// if (array_key_exists(1, $matches)) { 	// JSON
				// $filename = $matches[1];
				// $feedback['json'] = $filename;
			// }
			// debug($matches, 'matches');
			
			$found = preg_match('/\[ffmpeg\] Merging formats into \"(.*)\"/',$strOut,$matches);
			if (array_key_exists(1, $matches)) { 	// Filename found
				$filename = $matches[1];
				$feedback['filename'] = $filename;
			}
			debug($matches,'matches');
			$found = preg_match('/\[download\] (.*) has already been downloaded/',$strOut,$matches);
			if (array_key_exists(1, $matches)) { 	// Filename found
				$filename = $matches[1];
				$feedback['filename'] = $filename;
			}
			debug($matches,'matches');
		} 
		if (!array_key_exists('filename', $feedback)) $feedback['error'] = "No Filename";
		break;
	}
	

	// if (!array_key_exists('json', $feedback)) $feedback['error'] = "No JSON file";
	
	debug($feedback, 'feedback');
	return $feedback;
}


function parseSideKicks($sidekickstr) {
	debug($sidekickstr, 'sidekickstr');

	$feedback['Name'] = 'parseSideKicks';
	$feedback['result'] = array();

	// First/Last
	$sidekicks = array();
	do {
		$sks_split = preg_split('/[\s,&]/', $sidekickstr, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_OFFSET_CAPTURE);
		$len_sks = count($sks_split);
		// echo "cnt: ".$len_sks.CRLF;
		$i = 0;
		$len = strlen($sidekickstr);
		// echo $len.CRLF;
		$found = false;
		// echo "<pre>".$feedback['Name'].': '; print_r($sks_split); //echo "</pre>";
		// echo "<pre>".$feedback['Name'].': '; print_r($sidekicks); //echo "</pre>";
		while (!$found && $i < $len_sks-1) {
			$find = substr($sidekickstr, $sks_split[$i][1],$sks_split[$i+1][1] + strlen($sks_split[$i+1][0]));
			// echo "find: $find $i".CRLF;
			if (FetchRow('SELECT * FROM `xbmc_actor` WHERE name = "'.$find.'"')) {
				// echo $sidekickstr.CRLF;
				$sidekicks[] = $sks_split[$i][0].' '.$sks_split[$i+1][0];
				$sidekickstr = substr($sidekickstr,0,$sks_split[$i][1]).' '.substr($sidekickstr,$sks_split[$i+1][1] + strlen($sks_split[$i+1][0])+1);
				// echo $sidekickstr.CRLF;
				$found = true;
			}
			$i++;
		}
		// echo strlen($sidekickstr).CRLF;
	} while($len != strlen($sidekickstr));


	// Single names
	//$sidekicks = array();
	do {
		$sks_split = preg_split('/[\s,&]/', $sidekickstr, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_OFFSET_CAPTURE);
		$len_sks = count($sks_split);
		// echo "cnt: ".$len_sks.CRLF;
		$i = 0;
		$len = strlen($sidekickstr);
		// echo $len.CRLF;
		$found = false;
		// echo "<pre>".$feedback['Name'].': '; print_r($sks_split); //echo "</pre>";
		// echo "<pre>".$feedback['Name'].': '; print_r($sidekicks); //echo "</pre>";
		while (!$found && $i < $len_sks) {
			$find = substr($sidekickstr, $sks_split[$i][1],$sks_split[$i][1] + strlen($sks_split[$i][0]));
			// echo "find: $find $i".CRLF;
			if (FetchRow('SELECT * FROM `xbmc_actor` WHERE name = "'.$find.'"')) {
				// echo $sidekickstr.CRLF;
				$sidekicks[] = $sks_split[$i][0];
				$sidekickstr = substr($sidekickstr,0,$sks_split[$i][1]).' '.substr($sidekickstr,$sks_split[$i][1] + strlen($sks_split[$i][0])+1);
				// echo $sidekickstr.CRLF;
				$found = true;
			}
			$i++;
		}
		// echo strlen($sidekickstr).CRLF;
	} while($len != strlen($sidekickstr));

	debug($sidekicks, 'sidekicks');
	return $sidekicks;
}

function mv_toLocal($filename) {
	return (str_ireplace(KODI_MUSIC_VIDEOS,LOCAL_MUSIC_VIDEOS,$filename));
}

function mv_toPublic($filename) {
	return (str_ireplace(LOCAL_MUSIC_VIDEOS, KODI_MUSIC_VIDEOS,$filename));
}

function cleanName($fname) {
	debug($fname, 'fname');
	$feedback['message'] = '';
	$fname = mb_convert_case($fname, MB_CASE_LOWER, 'UTF-8');

	$pattern[] = '/"/';							$replace[] = '';
	$pattern[] = '/full1080p/'; 				$replace[] = '';
	$pattern[] = '/ - youtube.*$/'; 			$replace[] = '';
	$pattern[] = '/ \|.*$/'; 					$replace[] = '';
	$pattern[] = '/_/'; 						$replace[] = ' ';
	$pattern[] = '/new video/'; 				$replace[] = '';
	$pattern[] = '/official music/'; 			$replace[] = '';
	$pattern[] = '/new video/'; 				$replace[] = '';
	$pattern[] = '/gull original song/'; 		$replace[] = '';
	$pattern[] = '/yeni orijinal/'; 			$replace[] = '';
	$pattern[] = '/orjinal/'; 					$replace[] = '';
	$pattern[] = '/yeni$/'; 					$replace[] = '';
	$pattern[] = '/ klip/'; 					$replace[] = '';
	$pattern[] = '/klip /'; 					$replace[] = '';
	$pattern[] = '/clip /'; 					$replace[] = '';
	$pattern[] = '/ clip/'; 					$replace[] = '';
	$pattern[] = '/orijinal/'; 					$replace[] = '';
	$pattern[] = '/official/'; 					$replace[] = '';
	$pattern[] = '/lyrics/'; 					$replace[] = '';
	$pattern[] = '/lyric/'; 					$replace[] = '';
	$pattern[] = '/video/'; 					$replace[] = '';
	$pattern[] = '/dvd/'; 						$replace[] = '';
	$pattern[] = '/720p/'; 						$replace[] = '';
	$pattern[] = '/ hd /'; 						$replace[] = '';
	$pattern[] = '/ featuring /'; 				$replace[] = ' ft ';
	$pattern[] = '/ feat. /'; 					$replace[] = ' ft ';
	$pattern[] = '/ feat /'; 					$replace[] = ' ft ';
	$pattern[] = '/ ft. /'; 					$replace[] = ' ft ';
	$pattern[] = '/high definition/'; 			$replace[] = '';
	$pattern[] = '/hq/'; 						$replace[] = '';
	$pattern[] = '/ hd /'; 						$replace[] = '';
	$pattern[] = '/-/'; 						$replace[] = ' - ';
	$pattern[] = '/\[\s+\]/';					$replace[] = '';
	$pattern[] = '/- -/'; 						$replace[] = '-';
	$pattern[] = '/`/'; 						$replace[] = "'";
	$pattern[] = '/uk edit/'; 					$replace[] = '';
	$pattern[] = '/out now/'; 					$replace[] = '';
	$pattern[] = '//'; 							$replace[] = '';
	$pattern[] = '/\[.*?\]/'; 					$replace[] = '';
	$pattern[] = '/\(.*?\)/';					$replace[] = '';
	$pattern[] = '/\)/';						$replace[] = '';
	$pattern[] = '/-\s+$/';						$replace[] = '';
	$pattern[] = '/-$/';						$replace[] = '';
	$pattern[] = '/[\x{2013}}]/u';			 	$replace[] = '-';
	$pattern[] = '/j\. balvin/';				$replace[] = 'j balvin';
	$pattern[] = '/remix$/';					$replace[] = '';
	$pattern[] = '/mix$/';						$replace[] = '';
	$pattern[] = '/original$/';					$replace[] = '';
	$pattern[] = '/\//';						$replace[] = '';
	$pattern[] = '/^(.*?) y (.*? -)/';			$replace[] = '$1 & $2';
	$pattern[] = '/^(.*?) x (.*? -)/';			$replace[] = '$1 & $2';
	$pattern[] = '/^(.*?) with (.*? -)/';			$replace[] = '$1 & $2';
	$pattern[] = '/^(.*?) ve (.*? -)/';			$replace[] = '$1 & $2';
	$pattern[] = '/,/';							$replace[] = ' & ';

	$m=0;
	$fname = preg_replace($pattern, $replace, $fname);
	$fname = preg_replace($pattern, $replace, $fname);
	$fname = preg_replace($pattern, $replace, $fname);
	$fname = preg_replace('/\s+/u', ' ', $fname);
	$fname = preg_replace('/rendez - vous/u', 'rendez-vous', $fname); // exception
	$fname = preg_replace('/fu - gee - la/u', 'fu-gee-la', $fname); // exception
	$fname = preg_replace('/angel - a/u', 'angel-a', $fname); // exception
	$fname = preg_replace('/ann - g/u', 'ann-g', $fname); // exception

	if (substr_count ($fname, ' - ') > 1) {
		$m=1;
		// echo "Multiple BOL A- $fname".CRLF;
		$fname = preg_replace("/^(\S|\S\S) - /u", "$1-", $fname); // Always assume AA-Name or A-Name is artist name
	}
	if (substr_count ($fname, ' - ') > 1) {
		// echo "Multiple \sA- $fname".CRLF;
		$fname = preg_replace("/(\W\S|\W\S\S) - /u", "$1-", $fname); // Always assume AA-Name or A-Name is artist name
	}
	if (substr_count ($fname, ' - ') > 1) {
		// echo "Multiple -A\s $fname".CRLF;
		$fname = preg_replace("/ - (\S\W|\S\S\W)/u", "-$1", $fname);; // Always assume Name-AA Or Name-A 
	}
	if (substr_count ($fname, ' - ') > 1) {
		// echo "Multiple -A\$ $fname".CRLF;
		$fname = preg_replace("/ - (\S$|\S\S$)/u", "-$1", $fname);
	}
	// if ($m==1)	echo "End result Multiple $fname".CRLF;

	//if (substr_count ($fname, ' - ') > 1) $fname = preg_replace("/\s(\S|\S\S) - /", "$1-", $fname); // Always assume AA- or A-B is artist name
	if (substr_count ($fname, ' - ') > 1) {
//		echo "Multiple -, assume 1st is artist part $fname".CRLF;
		$fname = preg_replace('/ - /u', '-', $fname);
		$fname = preg_replace('/-/u', ' - ', $fname, 1);
	}

	if (substr_count ($fname, ' - ') == 0 && substr_count($fname, '-') > 1) {// did to much assume first is artist
		$fname = preg_replace('/-/u', ' - ', $fname, 1);
	}

	if (substr_count ($fname, ' - ') == 0) $feedback['error'] =  "No Dash";

//	$fname = mb_convert_case($fname, MB_CASE_TITLE, 'UTF-8');
	$fname = preg_replace_callback('/\b[a-z]/u',function ($matches) {return strtoupper($matches[0]);},$fname); // Uppercase on all word breaks
	$fname = preg_replace_callback('/\'[a-z]/ui',function ($matches) {return strtolower($matches[0]);},$fname); // Lowercase after ' I'm Don't
	$fname = preg_replace('/Dj /','DJ ',$fname); // Lowercase after ' I'm Don't
	$fname = preg_replace('/\$/u', 'S', $fname); // $-sign t- S

	if ($m==1)	$feedback['message'] .= "Info - Multiple \"-\": >$fname"."<</br>";

	$savname = trim($fname);
	$fname = trim(preg_replace('/(.*?\s)(Ft.*?) - (.*?$)/', '$1- $3 $2', $fname)); // Handel Ft in wrong place
	// if ($fname != $savname) {
		// $feedback['message'] .= "Info: Updated Ft old: >$savname< "."new: >$fname< </br>";
	// }

	$feedback['Name'] = 'cleanName';
	$feedback['result'][] =  trim($fname);
	debug($feedback, 'feedback');
	return $feedback;

}
?>
