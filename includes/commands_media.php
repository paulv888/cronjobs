<?php
define("EXCLUDE_EXTENTSIONS", "nfo|tbn|txt|db");
define("ASSORTED_DIR", "_Assorted/");
define("REFRESH_CLEAN_NAME", 0x01);
define("REFRESH_FIND_MOVE", 0x02);
define("REFRESH_NFO", 0x04);
define("REFRESH_THUMB", 0x08);		// Still bash process
define("REFRESH_GET_GENRE", 0x10);
define("REFRESH_RECYCLE_DUPLICATE-NOTINUSE", 0x20);
define("REFRESH_CHECK_DUPLICATES", 0x40);
define("REFRESH_MOVE", 0x80);
define("REFRESH_CHECK_REVERSE", 0x100);
define("REFRESH_ALL", 0xFFFF);
define("MAX_DOWNLOADS", 2);


//
//	Refresh all or All should be independent of DB. 
//		Unclear deleted info? strPath gone?
//


// If multiple matches, put in order of match needed i.e. Karadeniz before Tukish
$genres = Array('80s', 'Classical', 'Country', 'Dance', 'Arabic', 'Bulgarian', 'Celtic', 'Japanese',  
        'OtherEastern',  'Polish',  'Russian',  'Meditation',  'Nederlands', 'Popular',  'Spanish',  'Tropical',  'Karadeniz', 'Halk', 'Turkish',
         '_XXX_recyclebin');

$genrefolders = Array('/musicvideos/80s/', '/musicvideos/Classical/',
	'/musicvideos/Country/', '/musicvideos/Dance/', 'Eastern/Arabic/',
	'/musicvideos/Eastern/Bulgarian/', '/musicvideos/Eastern/Celtic/', 
	'/musicvideos/Eastern/Japanese/',  '/musicvideos/Eastern/OtherEastern/', 
	'/musicvideos/Eastern/Polish/',  '/musicvideos/Eastern/Russian/', 
	'/musicvideos/Meditation/',  '/musicvideos/Nederlands/',
	'/musicvideos/Popular/',  '/musicvideos/Spanish/', 
	'/musicvideos/Tropical/',  
	'/musicvideos/Turkish/Halk/', '/musicvideos/Turkish/Karadeniz/', '/musicvideos/Turkish/'     );

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

function refreshVideo(&$params) {
	debug($params, 'params');


	$feedback['Name'] = 'refreshVideo';

	//
	// Clean name
	//
	$result = '';
	if ($params['refreshvideooptions'] & REFRESH_CLEAN_NAME) {
		$name = (array_key_exists('window_title',$params['file']) ? $params['file']['window_title'] : $params['file']['filename']);
		$result = cleanName($name);
		$params['file']['newname'] = $result['result'][0];
		$feedback['cleanName'] = $result;
	} else {
		$params['file']['newname'] = (array_key_exists('newname', $params['file']) ? $params['file']['newname'] : $params['file']['filename']);
	}

	//
	// Get Artist/Title
	//
	$result = getArtistTitle($params);
	if (array_key_exists('error',$result)) {
		$feedback['getArtistTitle'] = $result;
		debug($feedback, 'feedback');
		return $feedback;
	}


	if ($params['refreshvideooptions'] & REFRESH_CHECK_REVERSE) {
		$result = reverseArtistTitle($params);
		if (array_key_exists('error',$result)) {
			$feedback['reverseArtistTitle'] = $result;
			debug($feedback, 'feedback');
		}
	}

	//
	// Get Genre
	//
	if ($params['refreshvideooptions'] & REFRESH_GET_GENRE) {
		$result = getGenre($params);
		$feedback['getGenre'] = $result;
		if (array_key_exists('error',$result)) {
			debug($feedback, 'feedback');
			//return $feedback;
		}
	}

	//
	// Find Dir to move to
	//
	if ($params['refreshvideooptions'] & REFRESH_FIND_MOVE) {
		$result = findMoveTo($params['file']);
		$feedback['findMoveTo'] = $result;
		if (array_key_exists('error',$result)) {
			debug($feedback, 'feedback');
			return $feedback;
		}
	}

	debug($params);

	//
	// Check for old version and abort process
	//
	if ($params['refreshvideooptions'] & REFRESH_CHECK_DUPLICATES) {
		$result = findDuplicateByArtistTitle($params);
		$feedback['findDuplicateByArtistTitle'] = $result;
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
		$result = createNFO($params['file']);
		$feedback['createNFO'] = $result;
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
		$result = createThumbNail($params['file']);
		$feedback['createThumbNail'] = $result;
		if (array_key_exists('error',$result)) {
			$feedback['error'] = "Thumbnail Error";
			debug($feedback, 'feedback');
			return $feedback;
		}
	}

	//
	// Now move it
	//
	if ($params['refreshvideooptions'] & REFRESH_MOVE) {
		if ( !empty($params['file']['moveto']) && $params['file']['moveto'].$params['file']['newname'] != $params['file']['dirname'].$params['file']['filename'])  {
			$feedback['moveMusicVideo'] = moveMusicVideo($params);
		} else {
			$feedback['moveMusicVideo'] = "Source == Dest.";
		}
	}

	debug($feedback, 'feedback');
	return $feedback;

}

function findDuplicateByArtistTitle(&$params) {
	debug($params, 'params');

	$feedback['Name'] = 'findDuplicateByArtistTitle';
	$feedback['result'] = array();

	// 
	// Find artist title / exclude this video 
	//
	$mysql = 'SELECT mv.* FROM xbmc_video_musicvideos mv 
				JOIN xbmc_path p ON mv.strPathID = p.id 
				WHERE (`artist` = "'.$params['file']['artist']. '" OR artist LIKE "'.$params['file']['artist']. ' %" OR artist LIKE "% '.$params['file']['artist']. '")  
				AND title = "'.$params['file']['title'].'"';
	if (!empty($params['file']['idFile'])) $mysql .= ' AND idFile != '.$params['file']['idFile'];
	$mysql .=';';

	// }
	// echo $mysql.CRLF;
	$feedback['message'] = '';
	if ($mvids = FetchRows($mysql)) {
		if (!empty($mvids)) { // Found this artist & title, so duplicate
			// Height 
			$mysql = 'SELECT iVideoHeight FROM `xbmc_video_streamdetails` WHERE iStreamType = 0 AND `idFile` = '.$mvids[0]['idFile'];
			$mvids[0]['height'] = '?';
			$mvids[0]['file'] = mv_toLocal($mvids[0]['file']);
			if ($row = FetchRow($mysql)) $mvids[0]['height'] = $row['iVideoHeight'];
			if (!array_key_exists('idFile', $params['file'])) $params['file']['idFile'] = $mvids[0]['idFile'];
			$feedback['result'][$params['file']['idFile']] = $mvids;
			$feedback['error'] = "Duplicate";
		}
	}
	debug($feedback, 'feedback');
	return $feedback;
}


function checkFile($params){
	// Return true if not exist or checkfile os older
	$file = $params['file'];
	debug($file, 'file');

	$filename = $file['dirname'].$file['basename'];
	$checkfile = $file['dirname'].$file['filename'].'.'.$file['checkextension'];
	if (!file_exists($checkfile) || (filemtime($filename) > filemtime($checkfile))) {	// Not exist or now newer
		return true;
	}
	return false;
}

function readDirs($main){
	debug($main, 'main');
	//echo "Entry: ".$main.CRLF;
	$feedback['result'] = array();
	if (strpos($main, LOCAL_RECYCLE) !== false) {
		debug($feedback, 'feedback');
		return $feedback;
	}
	
	$dirHandle = opendir($main);
	$files = array();
	while($file = readdir($dirHandle)){
		// echo $main.$file.CRLF;
		if (is_dir($main.$file) && $file != '.' && $file != '..') {
			$files = array_merge ( $files, readDirs($main.$file.'/')['result']);
		} else {
			// echo $file.CRLF;
			$file_parts = mb_pathinfo($file);
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


function createThumbNails(&$params) {

	set_time_limit(0);

	debug($params, 'params');

	$feedback['Name'] = 'createThumNailbs';
	//$feedback['commandstr'] = "I send this";
	$feedback['result'] = array();
	// $feedback['message'] = "all good";
	// if () $feedback['error'] = "Not so good";

	$mysql = 'SELECT * FROM xbmc_video_musicvideos'; 
	// $mysql = 'SELECT * FROM xbmc_video_musicvideos WHERE idFile = "37969";'; 
	// $mysql = 'SELECT * FROM xbmc_video_musicvideos WHERE strPathID = "29";'; 

	$feedback['message'] = '';
	if ($mvids = FetchRows($mysql)) {
		foreach ($mvids as $mvid) { // Itereate all
			$file = $mvid;
			$file_parts = mb_pathinfo(mv_toLocal($mvid['file']));
			$file_parts['dirname'] = rtrim($file_parts['dirname'], '/') . '/';
			$file = array_merge($file, $file_parts);
			$params['file'] = $file;
			$params['refreshvideooptions'] = REFRESH_THUMB ;
			$params['file'] = $file_parts;
			$params['file']['checkextension'] = 'tbn';
			if (($params['commandvalue'] == "1") || (empty($params['commandvalue']) && checkFile($params))) {	//	Overwrite
				print_r($file);		// Batch with temp file, so we can find corrupted files
				$feedback = refreshVideo($params);
			} else {
				echo "Skip: ".$params['file']['filename'].CRLF;
			}
		}
	}

	debug($feedback, 'feedback');
	return $feedback;
}

function createNFOs(&$params) {

	set_time_limit(0);

	debug($params, 'params');

	$feedback['Name'] = 'createNFOs';
	//$feedback['commandstr'] = "I send this";
	$feedback['result'] = array();
	// $feedback['message'] = "all good";
	// if () $feedback['error'] = "Not so good";

	$mysql = 'SELECT * FROM xbmc_video_musicvideos'; 
	// $mysql = 'SELECT * FROM xbmc_video_musicvideos WHERE idFile = "37969";'; 
	// $mysql = 'SELECT * FROM xbmc_video_musicvideos WHERE strPathID = "29";'; 

	// echo $mysql.CRLF;
	$feedback['message'] = '';
	if ($mvids = FetchRows($mysql)) {
		foreach ($mvids as $mvid) { // Itereate all
			$file = $mvid;
			$file_parts = mb_pathinfo(mv_toLocal($mvid['file']));
			$file_parts['dirname'] = rtrim($file_parts['dirname'], '/') . '/';
			$file = array_merge($file, $file_parts);
			$params['file'] = $file;
			$params['refreshvideooptions'] = REFRESH_CLEAN_NAME | REFRESH_GET_GENRE | REFRESH_NFO ;
			$params['file'] = $file_parts;
			$params['file']['checkextension'] = 'nfo';
//			print_r($file);		// Batch with temp file, so we can find corrupted files
			if (($params['commandvalue'] == "1") || (empty($params['commandvalue']) && checkFile($params))) {	//	Overwrite
				print_r($file);		// Batch with temp file, so we can find corrupted files
				$feedback = refreshVideo($params);
			} else {
				echo "Skip: ".$params['file']['filename'].CRLF;
			}
		}
	}

	debug($feedback, 'feedback');
	return $feedback;
}

function createNFO($file) {
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

	if (!file_exists($file['dirname'].$file['filename'].'.'.$file['extension'])) {
		$feedback['error'] = "Not Found.";
		return $feedback;
	}

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
			$data .= "\n   <artist>".$sidekick."</artist>";
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
	$audio = $result['result']['audio'];
	$video = $result['result']['video'];
	debug($audio, 'audio');
	debug($video, 'video');

	$data .= "\n   <fileinfo>";
	$data .= "\n      <streamdetails>";
	$data .= "\n         <video>";
	$data .= "\n            <codec>".$video['codec_name']."</codec>";
	$data .= "\n            <aspect>".$video['width']/$video['height']."</aspect>";
	$data .= "\n            <width>".$video['width']."</width>";
	$data .= "\n            <height>".$video['height']."</height>";
	$str_time = 0;
	if (array_key_exists('TAG:DURATION', $video)) {
		$str_time = $video['TAG:DURATION'];
		$str_time = preg_replace("/^([\d]{1,2})\:([\d]{2})$/", "00:$1:$2", $str_time);
		sscanf($str_time, "%d:%d:%d", $hours, $minutes, $seconds);
		$time_seconds = $hours * 3600 + $minutes * 60 + $seconds;
	} else {
		$time_seconds = (int)$video['duration'];
	}
	$data .= "\n            <durationinseconds>".$time_seconds."</durationinseconds>";
	$data .= "\n         </video>";
	$data .= "\n         <audio>";
	$data .= "\n            <codec>".$audio['codec_name']."</codec>";
	if (array_key_exists('TAG:language', $audio)) $data .= "\n            <language>".$audio['TAG:language']."</language>";
	$data .= "\n            <channels>".$audio['channels']."</channels>";
	$data .= "\n         </audio>";
	$data .= "\n      </streamdetails>";
	$data .= "\n   </fileinfo>";
	debug($feedback);
	$data .= "\n</musicvideo>";
	$data = mb_convert_encoding($data, 'UTF-8', 'auto');
	file_put_contents($file['dirname'].$file['filename'].'.nfo', $data);
	$feedback['result'] = $data;
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

function findBadNamesLocations(&$params) {

	set_time_limit(0);

	debug($params, 'params');

	$feedback['Name'] = 'findBadNamesLocations';
	//$feedback['commandstr'] = "I send this";
	$feedback['result'] = array();
	// $feedback['message'] = "all good";
	// if () $feedback['error'] = "Not so good";

	// $mysql = 'SELECT * FROM xbmc_video_musicvideos WHERE idFile = "37969";';
	//$mysql = 'SELECT * FROM xbmc_video_musicvideos WHERE strPathID = "1379";';
	$mysql = 'SELECT * FROM xbmc_video_musicvideos';

	// echo $mysql.CRLF;
	$feedback['message'] = '';
	$count=0;
	if ($mvids = FetchRows($mysql)) {
		foreach ($mvids as $mvid) { // Itereate all
			$file = $mvid;
			$file_parts = mb_pathinfo(mv_toLocal($mvid['file']));
			$file_parts['dirname'] = rtrim($file_parts['dirname'], '/') . '/';
			$file = array_merge($file, $file_parts);
			$params['file'] = $file;
			print_r($file);		// Batch with temp file, so we can find corrupted files

			$params['refreshvideooptions'] = REFRESH_CLEAN_NAME | REFRESH_FIND_MOVE | REFRESH_GET_GENRE;
			$result = refreshVideo($params);  // Not checking for errors here???
			debug($result);

			$check = $result['findMoveTo']['result'];

			if (($check['filename'] != $check['newname']) || ($check['dirname'] != $check['moveto'])) {  // Found mismatch 
				$params['value_parts'][0] = $check['idFile'];
				$params['value_parts'][1] = $check['moveto'];
				$params['value_parts'][2] = $check['newname'];
				$result[] = createMoveQueueItem($params);
				$count++;
			}
			//$feedback['result'][] = $result;
		}
	}
	$feedback['message'] = $count." Move rows created";
	debug($feedback, 'feedback');
	return $feedback;
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
		$result = preg_match( '/^.*? & /', $fname, $m);		// Found BOL to & part
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
				WHERE (`artist` = "'.$params['file']['artist']. '" OR artist LIKE "'.$params['file']['artist']. ' /%" )';
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

	//$result[] = $params['file']['genre'] = reset($result);
	$feedback['result'] = $result;

	debug($feedback, 'feedback');
	return $feedback;
}

function findBestMatch(&$file, $mvids) {
	debug($file, 'file');

	$file['moveto'] = LOCAL_DATA.$mvids[0]['strPath'];
	$paths = array();
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
}

function findMoveTo(&$file) {
	debug($file, 'file');

	global $genres;
	global $genrefolders;

	$allow_change_genre = true;

	$feedback['Name'] = 'findMoveTo';
	$file['moveto'] = $file['dirname'];


	// Check if we have a dir
	$found = false;
	if (($key = array_search($file['genre'], $genres)) !== false) {
		$key = array_search($file['genre'], $genres);
		$file['moveto'] = LOCAL_DATA.$genrefolders[$key].ASSORTED_DIR;
		$key = array_search($file['genre'], $genres);
		debug (LOCAL_DATA.$genrefolders[$key].$file['artist']);
		if (file_exists(LOCAL_DATA.$genrefolders[$key].$file['artist']) && is_dir(LOCAL_DATA.$genrefolders[$key].$file['artist'])) {
			$found = true;
		}
	}

	if ($found) {
		$file['moveto'] = LOCAL_DATA.$genrefolders[$key].$file['artist'].'/';
	} else {

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
						WHERE (`artist` = "'.$file['artist']. '" OR artist LIKE "'.$file['artist']. ' /%");';
			if ($mvids = FetchRows($mysql)) {		// Found this artist
				findBestMatch($file, $mvids);
			} else {								// Must be new artist -> going to assorted for input genre
				if (($key = array_search($file['genre'], $genres)) !== false) {
					$key = array_search($file['genre'], $genres);
					$file['moveto'] = LOCAL_DATA.$genrefolders[$key].ASSORTED_DIR;
				} else {
					$feedback['error'] = "No Genre";
				}
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
	$params = '-v error -show_streams -show_entries stream=index,codec_type,codec_name,width,height,duration,channels -of default=noprint_wrappers=1 '.'"'.$file.'"';
	$cmd = getPath().'/bin/ffprobe.sh '.$params;
	$feedback['commandstr'] = $cmd;
	exec($cmd, $output, $exitCode);
	debug($output, 'exec');
	if ($exitCode != 0) {
		$feedback['error'] = "Error FFProbe $exitCode";
	}
	$feedback['exitCode'] = $exitCode;
	debug($feedback, 'feedback');

	// $feedback['result_raw'] = $output;
	$index = 0;
	foreach ($output as $line) {
		$temp = explode('=', $line);
		if ($temp[0] == 'index') {
			$index = (int)$temp[1];
			$streaminfo[$index] = array();
		}
		$streaminfo[$index][$temp[0]] = $temp[1];
	}

	foreach ($streaminfo as $stream) {
		if ($stream['codec_type'] == 'audio') $feedback['result']['audio'] = $stream;
		if ($stream['codec_type'] == 'video') $feedback['result']['video'] = $stream;
	}

	debug ($feedback, 'feedback');
	return $feedback;
}

function createThumbNail($file) {
	debug($file, 'file');

	$feedback['Name'] = 'createThumbNail';
	$feedback['result'] = array();
	if (!file_exists($file['dirname'].$file['filename'].'.'.$file['extension'])) {
		$feedback['error'] = "Not Found.";
		return $feedback;
	}

	$feedback['message'] = "No thumbnails created, kick of on srvmedia: </br>sudo ~/bin/spawn_create_thumbs";
	$feedback['message'] = "";
	$params = '"'.$file['dirname'].'" "'.$file['filename'].'" "'.$file['extension'].'"'." 1";
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

function searchTracksForAllArtist(&$params) {

	debug($params, 'params');
	$feedback['Name'] = 'searchTracksForAllArtist';
	$feedback['result'] = array();

	if (!empty($params['commandvalue'])) {
		$mysql = 'SELECT `id`,`name` FROM `xbmc_actor` WHERE (`id` >=  '.$params['value_parts'][0].' AND `id` <=  '.$params['value_parts'][1].')' ;
	} else {
$mysql = 'SELECT a.id , a.name FROM `vd_spotify_actor` s JOIN `xbmc_actor` a on s.actor_id = a.id WHERE s.`name`!=a.`name` and calc=-1SELECT a.id , a.name FROM `vd_spotify_actor` s JOIN `xbmc_actor` a on s.actor_id = a.id WHERE s.`name`!=a.`name` and calc=-1';

		//$mysql = 'SELECT `id`,`name` FROM `xbmc_actor` WHERE 1' ;
	}
	$count = 0;
	$result = Array();
	$artist = Array();
	if ($rows = FetchRows($mysql)) {
		foreach ($rows as $row) { // Itereate all (till count of 4 found
			$params['commandvalue'] = $row['id'];
			$params['file']['artist'] = $row['name'];
			debug($row['name'],'actor');
			echo $row['name'].CRLF;
			$result = searchTracksForArtist($params);
			print_r($result);
		}
	}
}

function searchTracksForArtist(&$params) {

	debug($params, 'params');
	$feedback['Name'] = 'searchTracksForArtist';
	$feedback['result'] = array();

	$artistID = $params['commandvalue'];
	$mysql = 'SELECT `actor_id`,`artist`,`title` FROM `xbmc_actor_link` WHERE `actor_id`= '.$artistID;
	$count = 0;
	$result = Array();
	$artist = Array();
	if ($rows = FetchRows($mysql)) {
		foreach ($rows as $row) { // Itereate all (till count of 4 found
			$params['commandvalue'] = $params['file']['artist'].' '.$row['title'];
			$params['search']['type'] = 'track';
			$result = searchSpotify($params);
			if (array_key_exists('error',$result)) {
				$feedback['result'][] = $result;
				debug($feedback, 'feedback');
				return $feedback;
			} else {
				$result = $result['result'];
				$items = $result->tracks->items;
				if (array_key_exists(0, $items)) {
					$id = $items[0]->album->artists[0]->id;
					if (array_key_exists($id, $artist)) {
						$artist[$id]->count++;
						if ($artist[$id]->count > 3) break; 
					} else {
						$artist[$id] = $items[0]->album->artists[0];
						$artist[$id]->count = 1;
					}
				}
				$feedback['result']['tracks'][] = $result;
			}
		}
	}

	$params['commandvalue'] = $params['file']['artist'];
	$params['search']['type'] = 'artist';
	$result = searchSpotify($params);
	debug($result, 'searchArtist');
	$result = $result['result'];
	$items = $result->artists->items;
	if (array_key_exists(0, $items)) {
		$id = $items[0]->id;
		if (array_key_exists($id, $artist)) {
			$artist[$id]->count++;
		} else {
			$artist[$id] = $items[0];
			$artist[$id]->count = 1;
		}
	}

	usort($artist, function($a, $b) {return $a->count < $b->count;});
	$feedback['result'][] = $artist;
	debug($artist, 'Artists/Tracks found');

	foreach ($artist as $art) {
		debug(strtolower(clearUTF ($art->name)), 'clearUTF'); 
		debug(strtolower(clearUTF ($params['file']['artist'])), 'clearUTF');
		if (strtolower(clearUTF ($art->name)) == 
			 strtolower(clearUTF ($params['file']['artist']))) {
			$foundArtist = $art;
			$foundArtist->count = $foundArtist->count+10;
			debug (true, 'Found matching name');
			break;
		}
	}


	if (count($artist) > 0) {

		if (empty($foundArtist)) $foundArtist = $artist[0];

		if (array_key_exists('properties', $params['device']) && array_key_exists('Token', $params['device']['properties'])) {
			$accessToken = $params['device']['properties']['Token']['value'];
		} else {
			$accessToken = $params['device']['previous_properties']['Token']['value'];
		}
		try {

			$api = new SpotifyWebAPI\SpotifyWebAPI();
			$api->setAccessToken($accessToken);
			$spot_artist = $api->getArtist($foundArtist->id);
			debug($spot_artist, 'spot_artist');
		} catch (Exception $e) {
			$feedback['error'] = 'Caught exception ('.$e->getCode().'): '.  $e->getMessage();
		}
		
		// Update Genres Counts or insert new one
		foreach ($spot_artist->genres as $genre) {
			$mysql = 'SELECT * FROM `vd_spotify_genre` WHERE lookup ="'.$genre.'"'; 
			if ($genre_row = FetchRow($mysql)) {
				PDOUpdate('vd_spotify_genre', array('uses' => $genre_row['uses']+1), array('id' => $genre_row['id']));
			} else {
				PDOInsert('vd_spotify_genre', array('lookup' => $genre,'description' => mb_convert_case($genre, MB_CASE_TITLE, 'UTF-8'),'uses' => $genre_row['uses']+1));
			}
		}

		// Find vd_spotify_actor id
		$mysql = 'SELECT id FROM vd_spotify_actor WHERE actor_id = '.$artistID ; 
		if (!($vd_spotify_actorID = FetchRow($mysql)['id'])) $vd_spotify_actorID = PDOInsert('vd_spotify_actor', Array('actor_id' => $artistID, 'name' => '*',  'calc' => 0));
		
		debug($vd_spotify_actorID,'vd_spotify_actorID');
		
		// Find most used one and assign to artist
		$genres = implode('","', $spot_artist->genres);
		$mysql = 'SELECT * FROM vd_spotify_genre WHERE lookup in ("'.$genres.'") order by uses desc'; 
		if ($SPgenre = FetchRow($mysql)) {
			PDOUpsert('vd_spotify_actor_repeat_genres', array('parent_id' => $vd_spotify_actorID, 'genres' => $SPgenre['id']), array('parent_id' => $vd_spotify_actorID, 'genres' => $SPgenre['id']));
		} else {
			echo "Not found: ".$genres;
		}
	
		$store = Array('actor_id' => $artistID, 'external_urls' => $spot_artist->external_urls->spotify,
				'followers' => $spot_artist->followers->total , 
				'image1' => $spot_artist->images[0]->url, 'image2' => $spot_artist->images[1]->url, 'image3' => $spot_artist->images[2]->url,
				'name' => $spot_artist->name, 'uri' => $spot_artist->id, 'calc' => $foundArtist->count );
	} else {
		$store = Array('actor_id' => $artistID, 'name' => '*',  'calc' => 0);
	}
	
	if (!empty($spot_artist)) debug($spot_artist,'spot_artist');

	$feedback['result'] = $result;
	
	PDOUpsert('vd_spotify_actor', $store, Array( 'actor_id' => $artistID ));
	
	$feedback['message'] = 'Upserted row for:' .$params['file']['artist'];
	return $feedback;
}


function searchSpotify(&$params) {

include_once 'includesSpotify.php';	
	
	debug($params, 'params');

	$feedback['Name'] = 'searchSpotify';
	$feedback['result'] = array();

	if (array_key_exists('properties', $params['device']) && array_key_exists('Token', $params['device']['properties'])) {
		$accessToken = $params['device']['properties']['Token']['value'];
	} else {
		$accessToken = $params['device']['previous_properties']['Token']['value'];
	}
	try {
		$api = new SpotifyWebAPI\SpotifyWebAPI();
		$api->setAccessToken($accessToken);
		$result = $api->search($params['commandvalue'], $params['search']['type']);
	} catch (Exception $e) {
		echo 'Caught exception ('.$e->getCode().'): ',  $e->getMessage(), "\n";
		if ($e->getCode() == 429) { // 429 is Too Many Requests
			$lastResponse = $api->getRequest()->getLastResponse();
			$retryAfter = $lastResponse['headers']['Retry-After']; // Number of seconds to wait before sending another request
			sleep($retryAfter);
		} elseif  ($e->getCode() == 401) {
			$session = new SpotifyWebAPI\Session(
				$params['device']['connection']['username'],
				$params['device']['connection']['password']
			);

			$session->requestCredentialsToken();
			$accessToken = $session->getAccessToken();
			$params['device']['properties']['Token']['value'] = $accessToken;
			//$params['device']['previous_properties']['Token']['value'] = $accessToken;
			$params['device']['properties']['TokenExpire']['value'] = $session->getTokenExpiration();
			debug($accessToken,'New Token');
			$api->setSession($session);
		} else  {
			$feedback['error'] = 'Caught exception ('.$e->getCode().'): '.  $e->getMessage();
			return $feedback;
		}
		$result = $api->search($params['commandvalue'], $params['search']['type']);
	}
	$feedback['result'] = $result;
	debug($feedback, 'feedback');
	return $feedback;
}




function importDirectories(&$params) {


	debug($params, 'params');

	$params['directory'] = LOCAL_IMPORT.'/';


	set_time_limit(0);
	$feedback['Name'] = 'importDirectories';

	$files = array();

	$result = readDirs($params['directory']); 
	$files = $result['result'];
	usort($files, "cmp");

	//PARSE RESULTS
	foreach($files as $file) {
		try {
			$feedback['result'][] = PDOInsert("vd_queue", array('statusID' => Q_RELEASED, 'type' => 'IMPORT',  'window_title' => $file['filename'], 'filename' => $file['dirname'].$file['basename'] ));
		} catch (Exception $e) {
			$feedback['error'] = 'Error: On insert on vd_queue';
		}
	}
	$feedback['message'] = count($files)." files queued.";

	debug($feedback, 'feedback');
	return $feedback;
}

function renameVideo($idFile){
	echo "";
}

function createArtistDirs(&$params) {

	debug($params, 'params');

	$feedback['Name'] = 'createArtistDir';
	//$feedback['received'] = $params['commandvalue'];
	$feedback['result'] = array();

	//$feedback['commandstr'] = 'PDOInsert: '.$params['commandvalue'];


	$mysql = 'SELECT count(artist) as count, artist, file FROM `xbmc_video_musicvideos` WHERE instr(`file`,"Assorted")>1 GROUP BY artist, strPathID HAVING COUNT(*) > 3';
	$count = 0;
	$result = Array();
	if ($rows = FetchRows($mysql)) {
		foreach ($rows as $row) { // Itereate all

			$file_parts = mb_pathinfo($row['file']);
			$params['file'] = $file_parts;
			$params['file']['newname'] = $file_parts['filename'];
			$result[] = getArtistTitle($params);
			if (array_key_exists('error',$result)) {
				$feedback['result'][] = $result;
				debug($feedback, 'feedback');
				return $feedback;
			}

			$artist = $params['file']['artist'];
			$result[] = getGenre($params);
			if (array_key_exists('error',$result)) {
				$feedback['result'][] = $result;
				debug($feedback, 'feedback');
				//return $feedback;
			}

			$new_dir = str_replace(ASSORTED_DIR, '', mv_toLocal($file_parts['dirname']).'/').$artist;
			if (!file_exists($new_dir)) {
				if (mkdir($new_dir)) {
					$result[] = 'Created dir: '. $new_dir;
				} else {
					$result[]['error'] = "Failed to create dir". $new_dir;
				}
			}

//JOIN xbmc_path p ON mv.strPathID = p.id
			$mysql = 'SELECT mv.* FROM xbmc_video_musicvideos mv 
					WHERE (`artist` = "'.$params['file']['artist']. '" OR artist LIKE "'.$params['file']['artist']. ' %" OR artist LIKE "% '.$params['file']['artist']. '")  
					AND genre = "'.$params['file']['genre'].'";'; 

			if ($mvids = FetchRows($mysql)) {
				foreach ($mvids as $mvid) {
					$params['value_parts'][0] = $mvid['idFile'];
					$params['value_parts'][1] = $new_dir;
					$result[] = createMoveQueueItem($params);
					$count++;
				}
			}
		}
	}
	$feedback['result'] = $result;
	$feedback['message'] = $count.' move rows created';
	debug($feedback, 'feedback');
	return $feedback;

}

function createMoveQueueItem($params) {
	//
	//	If empty value_parts_1 then move to import and create record to move back after edit
	//	If empty value_parts_2 then same name else rename
	//
	debug($params);
	if (!array_key_exists(2,$params['value_parts'])) $params['value_parts'][2] = "";
	if (empty(trim($params['value_parts'][1]))) {
		$to_dir = LOCAL_IMPORT;
	} else {
		$to_dir = rtrim($params['value_parts'][1], '/');
	}
	$mysql = 'SELECT * FROM xbmc_video_musicvideos WHERE `idFile` = '.$params['value_parts'][0].';'; 
	if ($mvid = FetchRow($mysql)) {	
		debug($mvid, 'mvid');
		$file_parts = mb_pathinfo(mv_toLocal($mvid['file']));
		debug($file_parts, 'parts');
		$store = Array();
		$store['type']     = "MOVE";
		$store['artist']   = $mvid['artist'];
		$store['window_title']  = $file_parts['filename'];
		if (empty(trim($params['value_parts'][2]))) {
			$store['newname']  = $file_parts['filename'];
		} else {
			$store['newname']  = trim($params['value_parts'][2]);
		}
		$store['title']    = $mvid['title'];
		$store['genre']    = $mvid['genre'];
		$store['statusID'] = Q_QUEUED;
		if (empty(trim($params['value_parts'][1]))) {
			$store['statusID'] = Q_DOWNLOAD_SUCCESS;
		}
		$store['move_to'] =  $to_dir.'/';
		$store['subdir_move_to'] = $mvid['artist'] ;
		$store['idFile'] = $mvid['idFile'];
		$store['filename'] = mv_toLocal($mvid['file']);
		$result[] = PDOInsert("vd_queue", $store);
		$result['message'] = 'Move row created: '.$to_dir.'/'.$file_parts['basename'].CRLF;
		if (empty(trim($params['value_parts'][1]))) {
			$store['statusID'] = Q_QUEUED;
			$store['type'] = "IMPORT";
			$store['filename'] = LOCAL_IMPORT.'/'.$file_parts['basename'];
			$store['move_to'] =  '';
			$store['subdir_move_to'] = '';
			$result[] = PDOInsert("vd_queue", $store);
			$result['message'] .= 'Move from Import created.'.CRLF;
		}
		$result[] = $store['newname'];
	} else {
		$result['error'] = "Not found";
	}
	return $result;
}

function findDuplicateVideos(&$params) {

	set_time_limit(0);

	debug($params, 'params');

	$feedback['Name'] = 'findDuplicateVideos';
	$feedback['result'] = array();

	// $mysql = 'SELECT * FROM xbmc_video_musicvideos WHERE idFile = "54208";'; 
	// $mysql = 'SELECT * FROM xbmc_video_musicvideos WHERE strPathID = "365";'; 
	$mysql = 'SELECT * FROM xbmc_video_musicvideos'; 

	// echo $mysql.CRLF;
	$feedback['message'] = '';
	if ($mvids = FetchRows($mysql)) {
		foreach ($mvids as $mvid) { // Itereate all
			$file_parts = mb_pathinfo(mv_toLocal($mvid['file']));
			$file_parts['dirname'] = rtrim($file_parts['dirname'], '/') . '/';
			$params['file'] = $file_parts;
			$params['file']['idFile'] = $mvid['idFile'];
			$params['file']['newname'] = $params['file']['filename'];
			print_r($params['file']);		// Batch with temp file, so we can find corrupted files
			getArtistTitle($params);
			$result = findDuplicateByArtistTitle($params);
			if (array_key_exists('error', $result)) {
				$result[] = createDuplicateQueueItem($result['result'][$mvid['idFile']], $mvid);
				$feedback['result'][] = $result;
			}
			debug($result);
		}
	}
	debug($feedback, 'feedback');
	return $feedback;
}

function createDuplicateQueueItem($duplicates, $mvid) {

	debug($duplicates, 'duplicates');
	debug($mvid, 'mvid');
	$to_dir = LOCAL_RECYCLE;
//
// TODO:: link to counterpart (if any)
	foreach ($duplicates as $duplicate) {
		$file_parts = mb_pathinfo(mv_toLocal($mvid['file']));
		debug($file_parts, 'parts');
		$store = Array();
		$store['type']     = "DUPL";
		$store['artist']   = $mvid['artist'];
		$store['last_error']  = 'This: '.$mvid['height'].' Dupl: '.$duplicate['height'];
		$store['window_title']  = $file_parts['filename'];
		$store['newname']  = $file_parts['filename'];
		$store['title']    = $mvid['title'];
		$store['genre']    = $mvid['genre'];
		$store['statusID'] = Q_QUEUED;
		$store['move_to'] =  $to_dir.'/';
		$store['subdir_move_to'] = $mvid['artist'] ;
		$store['idFile'] = $mvid['idFile'];
		$store['filename'] = mv_toLocal($mvid['file']);
		$store['duplicate_filename'] = mv_toLocal($duplicate['file']);
		$result[] = PDOInsert("vd_queue", $store);
		$result['message'] = 'Move row created for: '.$to_dir.$file_parts['basename'].CRLF;
		$result[] = $store['newname'];
	}
	return $result;
}


function undoVideoImport(&$params) {

	debug($params, 'params');

	$feedback['Name'] = 'undoVideoImport';
	$feedback['received'] = $params['commandvalue'];
	$feedback['result'] = array();

	//$feedback['commandstr'] = 'PDOInsert: '.$params['commandvalue'];
	if (empty($params['commandvalue'])) {
		$feedback['error'] = 'Error: No ID given';
		return $feedback;
	}

	$mysql = 'SELECT * FROM `vd_queue` WHERE id = '.$params['commandvalue'].' AND statusID = '.Q_IMPORT_SUCCESS;
	if (!($row = FetchRow($mysql))) {
		$feedback['error'] = 'Error: could not find id: '.$params['commandvalue'].' in imported Status' ;
		return $feedback;
	}

	$file['dirname'] = $row['move_to'];
	$file['filename'] = $row['newname'];
	$file['moveto'] = LOCAL_IMPORT.'/';
	$file['newname'] = $row['newname'];
	$file_parts = mb_pathinfo($row['filename']);
	$file['extension'] = $file_parts['extension'];
	$params['file'] = $file;
	$params['movetorecycle'] = false;
	$result = moveMusicVideo($params);
	$feedback['result'][] = $result;
	if (!array_key_exists('error',$result)) {
		$command['commandvalue'] = 'DELETED: '.$result['message'];
	} else {
		$command['commandvalue'] = $result['error'];
	}

	try {
		$pretty_result = $row['result'].'<br/><pre>UNDO<br/>'.json_encode($result, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT).'</pre>';
		PDOUpdate('vd_queue', array('statusID' => Q_QUEUED, 'last_error' => 'Undo', 'filename' => $file['moveto'].$file['newname'].'.'.$file['extension'], 'result' => $pretty_result) , array('id' => $row['id']));
	} catch (Exception $e) {
		$feedback['error'] = 'Error: On insert on vd_queue';
	}
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

	$mysql = 'SELECT * FROM `vd_queue` WHERE window_title = "'.$windowTitle.'"';
	if ($windowTitle <> "Playlist" && FetchRow($mysql)) {
		$feedback['error'] = 'Error: already in queue';
		return $feedback;
	}

	try {
		if ($windowTitle != "Playlist") {
			$feedback['result'][] = PDOInsert("vd_queue", array('statusID' => Q_RELEASED, 'type' => 'DLOAD',   'url' => 'https://'.$params['value_parts'][0], 'window_title' => $windowTitle));
			$feedback['message'] .= "Inserted: ".$windowTitle;
		} else {
			$feedback['result'][] = PDOInsert("vd_queue", array('statusID' => Q_RELEASED, 'type' => 'PLIST', 'url' => 'https://'.$params['value_parts'][0], 'window_title' => $windowTitle));
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


	$mysql_t = 'SELECT count(*) as downloads FROM `vd_queue` q WHERE statusID IN ("'.Q_DOWNLOADING.'");'; 
	if (FetchRow($mysql_t)['downloads'] >= MAX_DOWNLOADS) {
		$mysql = 'SELECT * FROM `vd_queue` q WHERE statusID IN ("'.Q_RELEASED.'","'.Q_DOWNLOAD_SUCCESS.'");'; 
	} else {
		$mysql = 'SELECT * FROM `vd_queue` q WHERE statusID IN ("'.Q_RELEASED.'","'.Q_VERIFY_SUCCESS.'","'.Q_DOWNLOAD_SUCCESS.'");'; 
		// $mysql = 'SELECT * FROM `vd_queue` q WHERE statusID IN ("'.Q_RELEASED.'","'.Q_VERIFY_SUCCESS.'");'; 
	}
//
//	Flow 1 FILE:
//                                                               -----> has(FILENAME) - (skip) ------>   
//                                                              ^                                    |
//                                                              |                                    V
//		addToQueue     QUEUED -> RELEASED -> PROCESSING -> VERIFY_SUCCESS  -> DOWNLOADING  -> DOWNLOAD_SUCCESS -> PROCESSING -> IMPORT_SUCCESS
//                                   ^                          ^                                                            -> IMPORT_FAILED
//                                   |                          |                          -> DOWNLOAD_FAILED (STOP)     
//                                   |                  -> VERIFY_FAILED    
//                                   |                          |
//				                     ------------<---------------
//
	while ($row = FetchRow($mysql)) {
		$result = array();
		switch ($row['statusID'])
		{
		case Q_RELEASED:
			switch ($row['type']) {
			case "DLOAD":
			case "IMPORT":
				$store = Array();
				$url = $row['url'];
				PDOUpdate('vd_queue', array('statusID' => Q_PROCESSING) , array('id' => $row['id']));
				$params['refreshvideooptions'] = REFRESH_ALL & ~REFRESH_MOVE & ~REFRESH_THUMB &  ~REFRESH_NFO;
				$params['file']['window_title'] = $row['window_title'];
				$params['file']['genre'] = $row['genre'];
				if (empty($row['filename'])) {
					$params['file']['dirname'] = LOCAL_IMPORT. '/';
				} else {		// Importing dir
					$file_parts = mb_pathinfo($row['filename']);
					$params['file']['dirname'] = $file_parts['dirname']. '/';
				}
				$result = refreshVideo($params);
				debug($params);
//					$pretty_result = $row['result'].'<br/><pre>VERIFY<br/>'.prettyPrint(json_encode($result,JSON_UNESCAPED_SLASHES)).'</pre>';
				$pretty_result = $row['result'].'<br/><pre>VERIFY<br/>'.json_encode($result, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT).'</pre>';
				$store['artist']   = (array_key_exists('artist', $params['file']) ? $params['file']['artist'] : '');
				$store['title']    = (array_key_exists('artist', $params['file']) ? $params['file']['title'] : '');
				$store['genre']    = (array_key_exists('genre', $params['file']) ? $params['file']['genre'] : '');
				if (array_key_exists('moveto', $params['file'])) {
					$store['move_to'] =  $params['file']['moveto'];
					$store['subdir_move_to'] =  substr(rtrim($params['file']['moveto'],'/'), strrpos(rtrim($params['file']['moveto'],'/'), '/')+1);
				} else {
					$store['move_to'] =  "";
					$store['subdir_move_to'] =  "";
				}
				$store['newname'] = (array_key_exists('moveto', $params['file']) ? $params['file']['newname'] : '');
				$store['result']   = $pretty_result;

				$error = "";
				foreach ($result as $step) {
					if (is_array($step) && array_key_exists('error', $step)) {
						if ($step['error'] == "Duplicate" ) {
							$temp = reset($step['result']);
							debug($temp);
							$store['duplicate_filename'] = $temp[0]['file'];
							$error .= $step['error'].' '.$temp[0]['height'].CRLF;
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
			case "PLIST":
				$url = $row['url'];
				PDOUpdate('vd_queue', array('statusID' => Q_DOWNLOADING) , array('id' => $row['id']));
				$result = youtubeDL($url, YT_GET_PLAYLIST);
//					$pretty_result = $row['result'].'<pre>DOWNLOAD<br/>'.prettyPrint(json_encode($result,JSON_UNESCAPED_SLASHES)).'</pre>';
				$pretty_result = $row['result'].'<pre>DOWNLOAD<br/>'.json_encode($result, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT).'</pre>';
				debug($params);
				if (array_key_exists('error', $result)) {
					PDOUpdate('vd_queue', array('statusID' => Q_DOWNLOAD_FAILED, 'last_error' => $result['error'], 'result' => $pretty_result) , array('id' => $row['id']));
				} else {
					//PARSE RESULTS
					foreach($result['result'] as $download) {
						$mvid = json_decode($download, true);
						debug($mvid);
						try {
							$result[] = PDOInsert("vd_queue", array('type' => 'DLOAD', 'statusID' => Q_QUEUED, 'url' => 'https://youtube.com/watch?v='.$mvid['url'], 'window_title' => $mvid ['title']));
						} catch (Exception $e) {
							$result['error'] = 'Error: On insert on vd_queue';
						}
					}
					PDOUpdate('vd_queue', array('statusID' => Q_IMPORT_SUCCESS, 'last_error' => '', 'result' => $pretty_result) , array('id' => $row['id']));
				}
				break;
			case "DUPL":
				$tempparams = $params;
				$tempparams['commandvalue'] = $row['filename'];
				$result = moveToRecycle($tempparams);
				$feedback[]['result'] = $result;
//					$pretty_result = $row['result'].'<br/><pre>REMOVE DUPLICATE<br/>'.prettyPrint(json_encode($result,JSON_UNESCAPED_SLASHES)).'</pre>';
				$pretty_result = $row['result'].'<br/><pre>REMOVE DUPLICATE<br/>'.json_encode($result, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT).'</pre>';
				if (array_key_exists('error', $result)) {
					PDOUpdate('vd_queue', array('statusID' => Q_IMPORT_FAILED, 'last_error' => $result['error'], 'result' => $pretty_result) , array('id' => $row['id']));
				} else {
					PDOUpdate('vd_queue', array('statusID' => Q_IMPORT_SUCCESS, 'last_error' => '', 'result' => $pretty_result) , array('id' => $row['id']));
				}
				break;
			default:
				$pretty_result = $row['result'].'<pre>SKIPPING VERIFY<br/></pre>';
				PDOUpdate('vd_queue', array('statusID' => Q_VERIFY_SUCCESS , 'result' => $pretty_result) , array('id' => $row['id']));
				break;
			}
			break;
		case Q_VERIFY_SUCCESS:
			$mysql_t = 'SELECT count(*) as downloads FROM `vd_queue` q WHERE statusID IN ("'.Q_DOWNLOADING.'");'; 
			if (FetchRow($mysql_t)['downloads'] >= MAX_DOWNLOADS) {
				$feedback['message'] = 'Reached max downloads getting out';
				return;
			}
			$isDownloaded = !empty($row['filename']);
			if (!$isDownloaded) {
				$url = $row['url'];
				PDOUpdate('vd_queue', array('statusID' => Q_DOWNLOADING) , array('id' => $row['id']));
				$result = youtubeDL($url);
//					$pretty_result = $row['result'].'<pre>DOWNLOAD<br/>'.prettyPrint(json_encode($result,JSON_UNESCAPED_SLASHES)).'</pre>';
				$pretty_result = $row['result'].'<pre>DOWNLOAD<br/>'.json_encode($result, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT).'</pre>';
				debug($params);
			} else {
				$pretty_result = $row['result'].'<pre>SKIPPING DOWNLOAD<br/></pre>';
				$result['filename'] = $row['filename'];
			}
			if (array_key_exists('error', $result)) {
				PDOUpdate('vd_queue', array('statusID' => Q_DOWNLOAD_FAILED, 'last_error' => $result['error'], 'result' => $pretty_result) , array('id' => $row['id']));
			} else {
				PDOUpdate('vd_queue', array('statusID' => Q_DOWNLOAD_SUCCESS, 'filename' => $result['filename'], 'last_error' => '', 'result' => $pretty_result) , array('id' => $row['id']));
			}
			break;
		case Q_DOWNLOAD_SUCCESS:
			$store = Array();
			PDOUpdate('vd_queue', array('statusID' => Q_PROCESSING) , array('id' => $row['id']));
			$pretty_result = $row['result'];
			if (!empty($row['duplicate_filename'])) {		// Delete now, might not find globbing on Import 
				$tempparams = $params;
				$tempparams['commandvalue'] = $row['duplicate_filename'];
				$result = moveToRecycle($tempparams);
				$feedback[]['result'] = $result;
				$pretty_result = $pretty_result.'<br/><pre>REMOVE DUPLICATE<br/>'.json_encode($result, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT).'</pre>';
			}
			$file_parts = mb_pathinfo($row['filename']);
			$file_parts['dirname'] = rtrim($file_parts['dirname'], '/') . '/';
			$params['file'] = $file_parts;
			$params['file']['window_title'] = $row['newname'];
			$params['file']['newname'] = $row['newname'];
			$params['file']['genre'] = $row['genre'];
			switch ($row['type']) {
			case "DLOAD":
			case "IMPORT":
				$params['refreshvideooptions'] = REFRESH_ALL & ~REFRESH_CHECK_DUPLICATES &~REFRESH_CHECK_REVERSE &~REFRESH_CLEAN_NAME &~REFRESH_GET_GENRE;
				break;
			case "MOVE":
				$params['file']['moveto'] = $row['move_to'];
				$params['refreshvideooptions'] = REFRESH_MOVE | REFRESH_NFO;
				break;
			}
			$result = refreshVideo($params);
			debug($params);
//				$pretty_result = $pretty_result.'<br/><pre>'.$row['type'].'<br/>'.prettyPrint(json_encode($result,JSON_UNESCAPED_SLASHES)).'</pre>';
			$pretty_result = $pretty_result.'<br/><pre>'.$row['type'].'<br/>'.json_encode($result, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT).'</pre>';
			if (array_key_exists('moveto', $params['file'])) {
				$store['move_to'] =  $params['file']['moveto'];
				$store['subdir_move_to'] =  substr(rtrim($params['file']['moveto'],'/'), strrpos(rtrim($params['file']['moveto'],'/'), '/')+1);
			} else {
				$store['move_to'] =  "";
				$store['subdir_move_to'] =  "";
			}
			$store['newname'] = (array_key_exists('moveto', $params['file']) ? $params['file']['newname'] : '');
			$store['result']   = $pretty_result;
			$error = "";
			foreach ($result as $step) {
				if (is_array($step) && array_key_exists('error', $step)) {
					if ($step['error'] == "Duplicate" ) {
						$store['duplicate_filename'] = $step['result'][0]['file'];
						$error .= $step['error'].' '.$step['result'][0]['height'].CRLF;
					} elseif ($step['error'] == "Multiple Dash") {
						$a=1;
					} else {
						$error .= $step['error'].CRLF;
					}
				}
			}
			$store['artist']   = (array_key_exists('artist', $params['file']) ? $params['file']['artist'] : '');
			$store['title']    = (array_key_exists('artist', $params['file']) ? $params['file']['title'] : '');
			$store['genre']    = (array_key_exists('genre', $params['file']) ? $params['file']['genre'] : '');
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
		}
		$feedback[] = $result;
	 // exit;
	}
	if (count($feedback) != 2) $params['loglevel'] = LOGLEVEL_COMMAND;
	$feedback['count'] = count($feedback);
	debug($feedback, 'feedback');
	return $feedback;
}

function findVideoArtistTitle($params) {

	debug($params, 'params');

	$feedback['Name'] = 'findVideoArtistTitle';
	$feedback['received'] = $params['commandvalue'];
	$feedback['result'] = array();
	$name = urldecode($params['commandvalue']);
	$result = cleanName($name);
	$params['file']['newname'] = $result['result'][0];
	$feedback[] = $result;
	$url = '/index.php/music-videos-list?resetfilters=1';
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
	$search = '&order_by=xbmc_video_musicvideos___title&order_dir=asc';
	$search .= '&xbmc_video_musicvideos___artist[condition]=CONTAINS&xbmc_video_musicvideos___artist[value][]=%s';
	//$search .= '&xbmc_video_musicvideos___title[condition]=BEGINS WITH&xbmc_video_musicvideos___title[value][]=%s';
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
		$i = 0;
		$len = strlen($sidekickstr);
		$found = false;
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
function moveToRecycle($params) {

	debug($params, 'params');

	$feedback['Name'] = 'moveToRecycle';
	$feedback['result'] = array();

	$cmvfile = mv_toLocal($params['commandvalue']);
	//$result = stat($infile);
	$fparsed = mb_pathinfo($cmvfile);
	$fparsed['dirname'] = rtrim($fparsed['dirname'], '/') . '/';
	$params['file'] = $fparsed ;
	$params['movetorecycle'] = 1;
	$result = moveMusicVideo($params);
	$feedback['result'][] = $result;
	$command = array('callerID' => $params['caller']['callerID'], 
		'caller'  => $params['caller'],
		'deviceID' => $params['deviceID'], 
		'commandID' => COMMAND_SEND_MESSAGE_KODI);

	if (!array_key_exists('error',$result)) {
		$command['commandvalue'] = 'DELETED: '.$result['message'];
	} else {
		$command['commandvalue'] = $result['error'];
	}
	$feedback['result'][] = sendCommand($command);

	debug($feedback, 'feedback');
	return $feedback;
}
	
function moveMusicVideo($params) {
//
//	1) Move from Import into Lib
//       b) Found existing one and move to Recycle first
//       a) Did not exist, just add
//  2) Called from moveToRecycle - Delete video ($params['movetorecycle'] = true)

	debug($params, 'params');

	$feedback['Name'] = 'moveMusicVideo';
	$feedback['result'] = array();
	$feedback['message'] = '';

	$file = $params['file'];
	if (!array_key_exists('movetorecycle', $params)) $params['movetorecycle'] = false;
	if ($params['movetorecycle']) {
		$rand = rand(100000,999999);
		$file['moveto'] = LOCAL_RECYCLE.'/';
		$file['newname'] = $file['filename'].' ('.$rand.')';
	}

	if (!file_exists($file['dirname'].$file['filename'].'.'.$file['extension'])) {
		$feedback['error'] = "Not Found.";
		return $feedback;
	}
	//
	//	Find matching based on basename and move to recycle
	//
	if (empty($file['dirname']) || empty($file['filename']) ||  empty($file['moveto']) ||  empty($file['newname']) ) {
		$feedback['error'] = 'Required params missing:'.$file['dirname'].'|'.$file['filename'].'|'.$file['moveto'].'|'.$file['newname'].'|';
		return $feedback;
	}
	$matches = glob($file['moveto'].$file['newname'].'.*');
	if (strtolower($file['dirname'].$file['filename']) != strtolower($file['moveto'].$file['newname'])) {
		if (!empty($matches)) {			// Duplicate file name
			// Find vid match
			// echo "<pre>Found old one ".$feedback['Name'].' '.$dirname.$fparsed['basename'].CRLF;
			foreach ($matches as $match) {
				$fparsed = mb_pathinfo($match);
				if (!in_array($fparsed['extension'], Array("tbn", "nfo"))) {
					$feedback['message'] .= "***";
					$dirname = rtrim($fparsed['dirname'], '/') . '/';
					$params['commandvalue'] = mv_toPublic($dirname.$fparsed['basename']);
					$feedback[]['result'] = moveToRecycle($params);
				}
			}
		}
	}

	foreach (Array ($file['extension'], "tbn", "nfo") as $ext) {
		$filename = $file['filename'].'.'.$ext;
		$infile = $file['dirname'].$file['filename'].'.'.$ext;
		$tofile = $file['moveto'].$file['newname'].'.'.$ext;

		if (!array_key_exists('error', $feedback)) {
			$cmd = 'mv -v "'.$infile.'" "'.$tofile.'"';
			$feedback['commandstr'] = $cmd;
			debug($cmd, 'cmd');
			exec($cmd, $output, $exitCode);
			$feedback['result']['mv'] = $output;
			$feedback['exitCode'] = $exitCode;
			debug($output, 'exec');
			debug($exitCode, 'exitCode');
			if ($exitCode != 0) {
				$feedback['error'] = "Error Moving file: $exitCode";
				return $feedback;
			}
			// echo "$copy".CRLF;
			// if ($copy) $unlink = unlink($infile);
			// echo "$unlink".CRLF;
			// touch ($tofile, $filedate);
			if (!in_array($ext, Array("tbn", "nfo"))) {
				if (!$exitCode) {
					$feedback['message'] .= $filename.'| moved to '.$tofile;
					$mysql = 'SELECT mv.id FROM `xbmc_video_musicvideos` mv JOIN xbmc_path p ON mv.strPathID = p.id WHERE file = "'.mv_toPublic($infile).'";'; 
					// Remove from Kodi Lib
					if ($mvid = FetchRow($mysql)['id']) {
						$command['caller'] = $params['caller'];
						$command['callerparams'] = $params['caller'];
						$command['deviceID'] = getCurrentPlayer();
						$command['commandID'] = 374;						// removeMusicVideo
						$command['commandvalue'] = $mvid;
						$feedback[]['result'] = sendCommand($command);
					}
				} else {
					$feedback['error'] = 'Error moving '.$infile.' | to '.$tofile;
				}
			}
		}
	}

	// After all got moved
	// Refresh the directory
	if (!$params['movetorecycle']) {
		$command['caller'] = $params['caller'];
		$command['callerparams'] = $params;
		$command['deviceID'] = 	getCurrentPlayer();	
		$command['commandID'] = 	373;	// Scan Directory
		$command['commandvalue'] = mv_toPublic($file['moveto']); 
		// $result = sendCommand($command);					// Not doing for now, takes to long
		// $feedback[]['result'] = $result;
	}

	$file = 'log/mv_videos.log';
	$log = file_get_contents($file);
	if (array_key_exists('error', $feedback)) 
		$log .= date("Y-m-d H:i:s").": Error: ".$feedback['error']."\n";
	else 
		$log .= date("Y-m-d H:i:s").": Moved: ".$feedback['message']."\n";
	file_put_contents($file, $log);

	debug($feedback, 'feedback');
	return $feedback;
} 

function addToFavorites(&$params) {

	debug($params, 'params');

	$feedback['Name'] = 'addToFavorites';
	$feedback['result'] = array();
 
	$file = LOCAL_PLAYLISTS.'/'.$params['macro___commandvalue'].'.m3u';
	$error = "";
	if (($playlist = file_get_contents($file)) !== false) {
		$playingfile = $params['device']['previous_properties']['File']['value'];
		$playing = $params['device']['previous_properties']['Playing']['value'];
		if (strpos($playlist, $playingfile) === false) {
			$playlist .= $playingfile."\n";
			if (file_put_contents($file, $playlist) === false) $error = "Could not write playlist ".$file.'|';
			$feedback['message'] = 'Added to - '.$params['macro___commandvalue'].'|'.$playing;
		} else {
			$feedback['message'] = 'Already part of - '.$params['macro___commandvalue'].'|'.$playing;
		}
	} else {
		$error = 'Could not open playlist: '.$params['macro___commandvalue'].'|';
	}
	if (!empty($error)) {
		$feedback['error'] = 'Could not open playlist - '.$params['macro___commandvalue'].'|';
	}

	debug($feedback, 'feedback');
	return $feedback;
} 

function mv_toLocal($filename) {
	return (str_ireplace(KODI_MUSIC_VIDEOS,LOCAL_MUSIC_VIDEOS,$filename));
}

function mv_toPublic($filename) {
	return (str_ireplace(LOCAL_MUSIC_VIDEOS, KODI_MUSIC_VIDEOS,$filename));
}

function cleanName($inname) {
	debug($inname, 'inname');
	$feedback['message'] = '';

	foreach (Array('-', '::') as $seperator) {
		$fname = mb_convert_case($inname, MB_CASE_LOWER, 'UTF-8');
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
		//$pattern[] = '/original$/';					$replace[] = '';
		$pattern[] = '/\//';						$replace[] = '';
		$pattern[] = '/^(.*?) y [-](.*? -)/';		$replace[] = '$1 & $2';
		$pattern[] = '/^(.*?) λ (.*? -)/';			$replace[] = '$1 & $2';
		$pattern[] = '/^(.*?) x [-](.*? -)/';		$replace[] = '$1 & $2';
		//$pattern[] = '/(.*?) e (.*?)/';		    $replace[] = '$1 & $2';
		$pattern[] = '/^(.*?) with (.*? -)/';		$replace[] = '$1 & $2';
		$pattern[] = '/^(.*?) ve (.*? -)/';			$replace[] = '$1 & $2';
		$pattern[] = '/,/';							$replace[] = ' & ';
		$pattern[] = '//';						$replace[] = '';
		$pattern[] = '/\?/';						$replace[] = '';
		$pattern[] = '/¿/';						$replace[] = '';
		$pattern[] = '/'.$seperator.'/'; 			$replace[] = ' - ';

		$m=0;
		$fname = transliterate($fname);
		$fname = preg_replace($pattern, $replace, $fname);
		$fname = preg_replace($pattern, $replace, $fname);
		
		if (substr_count ($fname, ' - ') > 1) break;
	}

	$fname = preg_replace($pattern, $replace, $fname);
	$fname = preg_replace('/\s+/u', ' ', $fname);
	$fname = preg_replace('/rendez - vous/u', 'rendez-vous', $fname); 	// exception
	$fname = preg_replace('/fu - gee - la/u', 'fu-gee-la', $fname); 	// exception
	$fname = preg_replace('/angel - a/u', 'angel-a', $fname); 			// exception
	$fname = preg_replace('/ann - g/u', 'ann-g', $fname); 				// exception
	$fname = preg_replace('/& ambassadors/u', 'x ambassadors', $fname); // exception

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
	
	$fname = mb_convert_case($fname, MB_CASE_TITLE, 'UTF-8');
	$fname = preg_replace_callback('/\b[a-z]/u', function ($matches) {return mb_strtoupper($matches[0], 'UTF-8');},$fname); // Uppercase on all word breaks
	$fname = preg_replace_callback('/\'[a-z]/ui',function ($matches) {return mb_strtolower($matches[0], 'UTF-8');},$fname); // Lowercase after ' I'm Don't
	$pattern = array();
	$pattern[] = '/\b(Mc)([a-z])/u';
	$pattern[] = '/\b(Mac)([a-z])/u';
	$pattern[] = '/\b([OD]\')([a-z])/u';
	$fname = preg_replace_callback($pattern, function ($matches) {return $matches[1].mb_strtoupper($matches[2], 'UTF-8');},$fname); // Uppercase after Mc
	$fname = preg_replace('/Dj /','DJ ',$fname); // 
	$fname = preg_replace('/\$/u', 'S', $fname); // $-sign t- S
	$fname = preg_replace('/cnco /i', 'CNCO ', $fname); // exception
	$fname = preg_replace('/10cc /i', '10cc ', $fname); // exception
	$fname = preg_replace('/^Atb /i', 'ATB ', $fname); // exception
debug($fname);
	if ($m==1)	$feedback['error'] =  "Multiple Dash";

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

function transliterate($string) {
    $roman = array("Sch","sch",'Yo','Zh','Kh','Ts','Ch','Sh','Yu','ya','yo','zh','kh','ts','ch','sh','yu','ya','A','B','V','G','D','E','Z','I','Y','K','L','M','N','O','P','R','S','T',
                     'U','F','','Y', '','E','a','b','v','g','d','e','z','i','y','k','l','m','n','o','p','r','s','t','u','f','','y', '','e');
    $cyrillic = array("Щ","щ",'Ё','Ж','Х','Ц','Ч','Ш','Ю','я','ё','ж','х','ц','ч','ш','ю','я','А','Б','В','Г','Д','Е','З','И','Й','К','Л','М','Н','О','П','Р','С','Т',
                    'У','Ф','Ь','Ы','Ъ','Э','а','б','в','г','д','е','з','и','й','к','л','м','н','о','п','р','с','т','у','ф','ь','ы','ъ','э');
    return str_replace($cyrillic, $roman, $string);
}


function fixstuff() {
	set_time_limit(0);

	$mysql = 'SELECT * FROM xbmc_actor where id = 3892'; 
	$mysql = 'SELECT * FROM xbmc_actor where 1'; 
	$feedback['message'] = '';
	if ($rows = FetchRows($mysql)) {
		foreach ($rows as $row) { // Itereate all
			$newname = str_ireplace('-TEST','',str_ireplace(' - TEST','',cleanName($row['name'].' - TEST')['result'][0]));
			$newname = str_ireplace(' - ','-',$newname);
			if ($newname != $row['name']) {
				$feedback['message'] .= $row['id'].CRLF.$row['name'].CRLF.$newname.CRLF.CRLF;
			}
					
			
			PDOUpdate('xbmc_actor', array('name' => $newname), array('id' => $row['id']));
		}
	}
	debug($feedback['message']);


exit;
	$mysql = 'SELECT * FROM vd_spotify_genres'; 
	$feedback['message'] = '';
	if ($genres = FetchRows($mysql)) {
		foreach ($genres as $genre) { // Itereate all
			$genre['description'] = mb_convert_case($genre['lookup'], MB_CASE_TITLE, 'UTF-8');
			PDOUpdate('vd_spotify_genres', array('description' => $genre['description']), array('id' => $genre['id']));
		}
	}

exit;
	$mysql = 'SELECT * FROM vd_spotify_actor'; 
	$feedback['message'] = '';
	if ($artists = FetchRows($mysql)) {
		foreach ($artists as $artist) { // Itereate all
			$genres = $artist['filename'];
			$genres = explode('|', $genres);
			$genres = implode('","', $genres);
			$mysql = 'SELECT * FROM vd_spotify_genre WHERE lookup in ("'.$genres.'") order by uses desc'; 
			if ($spgenre = FetchRow($mysql)) {
				PDOInsert('vd_spotify_actor_repeat_genres', array('parent_id' => $artist['id'], 'genres' => $spgenre['id']));
			} else {
				echo "Not found: ".$genre;
			}
		}
	}
	
}


function clearUTF($s)
{
    $r = '';
    $s1 = iconv('UTF-8', 'ASCII//TRANSLIT', $s);
    for ($i = 0; $i < strlen($s1); $i++)
    {
        $ch1 = $s1[$i];
        $ch2 = mb_substr($s, $i, 1);

        $r .= $ch1=='?'?$ch2:$ch1;
    }
    return $r;
}

?>
