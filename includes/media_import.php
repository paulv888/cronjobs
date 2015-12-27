<?php
define("ALERT_KODI", 224);
define( 'DEBUG_MEDIA', TRUE );
if (!defined('DEBUG_MEDIA')) define( 'DEBUG_MEDIA', FALSE );
define("LOCAL_MYDOCS", "/home/www/vlohome/data/mydocs/");
define("LOCAL_MUSIC_VIDEOS", "/home/www/vlohome/data/musicvideos/");
define("LOCAL_PLAYLISTS", LOCAL_MYDOCS."My Playlists/");
define("LOCAL_RECYCLE", "recyclebin_XXX_/");
//define("LOCAL_IMPORT", "import_XXX_/");
define("LOCAL_IMPORT", "/");
// define("LOCAL_IMPORT", "80s/");
// define("LOCAL_IMPORT", "Classical/");
// define("LOCAL_IMPORT", "Country/");
// define("LOCAL_IMPORT", "Dance/");
// define("LOCAL_IMPORT", "Eastern/");
// define("LOCAL_IMPORT", "Meditation/");
// define("LOCAL_IMPORT", "Nederlands/");
// define("LOCAL_IMPORT", "Popular/");
// define("LOCAL_IMPORT", "Spanish/");
// define("LOCAL_IMPORT", "Tropical/");
// define("LOCAL_IMPORT", "Turkish/");

mb_internal_encoding("UTF-8");

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
	// if () $feedback['error'] = "Not so good";
	
	// if (DEBUG_MEDIA) {
		// echo "<pre>".$feedback['Name'].': '; print_r($params); echo "</pre>";
	// }
	// return $feedback;
// }
header('Content-Type: text/html; charset=utf-8');
importVideos();

//function importVideos(&$params) {
function importVideos() {

	$feedback['Name'] = 'importVideos';
	// $feedback['commandstr'] = "I send this";
	// $feedback['result'] = array();
	// $feedback['message'] = "all good";
	// if () $feedback['error'] = "Not so good";
	
	// if (DEBUG_MEDIA) {
		// echo "<pre>".$feedback['Name'].': '; print_r($params); echo "</pre>";
	// }

echo "<pre>";
	

	// Handle 1 video:
    // Scan Import.
        // Completely downloaded || Kick-Off manual
    // Clean name							- DONE		
    // Move to directory			
        // Have dir for artist
        // Artist found in Assorted?
            // 3 or more files?
            // Create Artist dir (where Dance/Pop...)
            // Move all files to New dir
    // Update Library
        // Create .nfo
        // Create thumbnail
        // Update Kodi library (install server version?)
        // Create mp3 version (Leave Separate, batch process)
	
	$files = array();
	//$dir = LOCAL_MUSIC_VIDEOS.LOCAL_IMPORT;
	
	$dir = LOCAL_MUSIC_VIDEOS.LOCAL_IMPORT;
	echo $dir."</br>";
	$files = readDirs(LOCAL_MUSIC_VIDEOS.LOCAL_IMPORT);

	// echo '<pre>';
	// print_r($files);
	// echo '</pre>';
	
	



	foreach ($files as $index => $file) {
		$files[$index]['newname1'] = cleanName($file['filename']);
	}

	usort($files, "cmp");	
	
	foreach ($files as $index => $file) {
		$newnames[] = $files[$index]['newname1'];
		if ($files[$index]['newname1'] != $file['filename']) {
			echo $index.": ".$files[$index]['filename']."</br>";
			echo $index.": ".$files[$index]['newname1']."</br>";
		}
	}

	// Find duplicates
	$dups = array();
	foreach(array_count_values($newnames) as $val => $c) {
		if($c > 1) $dups[] = $val;
	}

	echo '<pre>';
	print_r($dups);
	echo '</pre>';
	
	
	// echo '<pre>';
	// print_r($files);
	// echo '</pre>';
	
}

function cmp($a, $b) {
        return $a["filename"] - $b["filename"];
}


function readDirs($main){
 echo "Entry: ".$main."</br>";
 $dirHandle = opendir($main);
  $files = array();
  while($file = readdir($dirHandle)){
	// echo $main.$file."</br>";
    if (is_dir($main.$file) && $file != '.' && $file != '..') {
	   $files = array_merge ( $files, readDirs($main.$file."/"));
    } else {
		$file_parts = pathinfo($file);
		// print_r($file_parts);
		$file_parts['dirname'] = $main;
		if (substr($file, 0, 1) != "." && $file != ".." && $file_parts['extension'] != "tbn" && $file_parts['extension'] != "nfo") {
			$files[] = $file_parts;
		}
    }
  }
  return $files;
}

function clearUTF($s)
{
// echo mb_internal_encoding();
// Not being used
	mb_internal_encoding("UTF-8");
	$special = Array ();

    $r = '';
	echo $s."<br>";
    $s1 = iconv('UTF-8', 'ASCII//TRANSLIT', $s);
	echo $s1."<br>";
    for ($i = 0; $i < strlen($s1); $i++)
    {
        $ch1 = $s1[$i];
        $ch2 = mb_substr($s, $i, 1);
			
        $r .= $ch1=='?' ? $ch2 : $ch1;
    }
	echo $r."<br>";
    return $r;
}



function cleanName($fname) {

	// $feedback['Name'] = 'templateFunction';
	// $feedback['commandstr'] = "I send this";
	// $feedback['result'] = array();
	// $feedback['message'] = "all good";
	// if () $feedback['error'] = "Not so good";
	
	// if (DEBUG_COMMANDS) {
		// echo "<pre>".$feedback['Name'].': '; print_r($params); echo "</pre>";
	// }


	$fname = strtolower($fname);
//	$fname = clearUTF($fname);
	// Html Decode?
	// fname = str_replace(, , fname);%A_Space%amp%A_Space%,%A_Space%&%A_Space%,All 
	// fname = str_replace(, , fname);%A_Space%quot%A_Space%, %A_Space%-%A_Space% ,All 

	$pattern[] = '/"/';							$replace[] = '';
	$pattern[] = '/full1080p/'; 				$replace[] = '';
	$pattern[] = '/_/'; 						$replace[] = '';
	$pattern[] = '/new video/'; 				$replace[] = '';
	$pattern[] = '/official music/'; 			$replace[] = '';
	$pattern[] = '/new video/'; 				$replace[] = '';
	$pattern[] = '/gull original song/'; 		$replace[] = '';
	$pattern[] = '/yeni orijinal/'; 			$replace[] = '';
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
	$pattern[] = '/\(hd\)/'; 					$replace[] = '';
	$pattern[] = '/\(hd/'; 						$replace[] = '';
	$pattern[] = '/hd\)/'; 						$replace[] = '';
	$pattern[] = '/-/'; 						$replace[] = ' - ';
	$pattern[] = '/\(\s+\)/';					$replace[] = '';
	$pattern[] = '/\[\s+\]/';					$replace[] = '';
	$pattern[] = '/- -/'; 						$replace[] = '-';
	$pattern[] = '/`/'; 						$replace[] = "'";
	$pattern[] = '/uk edit/'; 					$replace[] = '';
	$pattern[] = '/out now/'; 					$replace[] = '';
	$pattern[] = '//'; 							$replace[] = '';
	$pattern[] = '/\[.*?\]/'; 					$replace[] = '';
	$pattern[] = '/\(.*?\)/';					$replace[] = '';
	$pattern[] = '/ new$/';						$replace[] = '';
	$pattern[] = '/-$/';						$replace[] = '';

	$m=0;
	$fname = preg_replace($pattern, $replace, $fname);
	$fname = preg_replace($pattern, $replace, $fname);
	$fname = preg_replace('/\s+/u', ' ', $fname);
	$fname = preg_replace('/rendez - vous/u', 'rendez-vous', $fname); // exception
	$fname = preg_replace('/fu - gee - la/u', 'fu-gee-la', $fname); // exception
	$fname = preg_replace('/angel - a/u', 'angel-a', $fname); // exception

	if (substr_count ($fname, ' - ') > 1) {
		$m=1;
		// echo "Multiple BOL A- $fname"."</br>";
		$fname = preg_replace("/^(\S|\S\S) - /u", "$1-", $fname); // Always assume AA-Name or A-Name is artist name
	}
	if (substr_count ($fname, ' - ') > 1) {
		// echo "Multiple \sA- $fname"."</br>";
		$fname = preg_replace("/(\W\S|\W\S\S) - /u", "$1-", $fname); // Always assume AA-Name or A-Name is artist name
		//echo "Multiple - $fname"."</br>";
	}
	if (substr_count ($fname, ' - ') > 1) {
		// echo "Multiple -A\s $fname"."</br>";
		$fname = preg_replace("/ - (\S\W|\S\S\W)/u", "-$1", $fname);; // Always assume Name-AA Or Name-A 
	}
	if (substr_count ($fname, ' - ') > 1) {
		// echo "Multiple -A\$ $fname"."</br>";
		$fname = preg_replace("/ - (\S$|\S\S$)/u", "-$1", $fname);
	}
	// if ($m==1)	echo "End result Multiple $fname"."</br>";

	//if (substr_count ($fname, ' - ') > 1) $fname = preg_replace("/\s(\S|\S\S) - /", "$1-", $fname); // Always assume AA- or A-B is artist name
	if (substr_count ($fname, ' - ') > 1) {
//		echo "Multiple -, assume 1st is artist part $fname"."</br>";
		$fname = preg_replace('/ - /u', '-', $fname, 1);
	}

	if (substr_count ($fname, ' - ') == 0 && substr_count($fname, '-') > 1) {// did to much assume last is artist
		$fname = preg_replace('/-/u', ' - ', $fname, 1);
	}

	if (substr_count ($fname, ' - ') == 0) echo "No Artist title found $fname"."</br>";
	
//	$fname = mb_convert_case($fname, MB_CASE_TITLE, 'UTF-8');
	$fname = preg_replace_callback('/\b[a-z]/u',function ($matches) {return strtoupper($matches[0]);},$fname); // Uppercase on all word breaks
	$fname = preg_replace_callback('/\'[a-z]/ui',function ($matches) {return strtolower($matches[0]);},$fname); // Lowercase after ' I'm Don't
	$fname = preg_replace('/Dj /','DJ ',$fname); // Lowercase after ' I'm Don't

	if ($m==1)	echo "End result Multiple->$fname"."<-</br>";


	// ; specials
	// fname = str_replace('', '', fname);Ne - Yo,Ne-Yo,All 
	// fname = str_replace('', '', fname);Don T,Don't,All 
	// fname = str_replace('', '', fname);%A_Space%S%A_Space%,'s%A_Space%
	// fname = str_replace('', '', fname);%A_Space%i%A_Space%m%A_Space%,%A_Space%I'm%A_Space%
	return trim($fname);
	
}