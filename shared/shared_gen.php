<?php
function isCLI()
{
    return (php_sapi_name() === 'cli');
}
 
function getPath($public = false) {
	$url = ($public ? $_SERVER['SCRIPT_NAME'] : $_SERVER['SCRIPT_FILENAME']);
	$parts = explode('/',$url);
	array_pop($parts);
	if (empty($parts)) return "/home/pvloon/php";
	return implode('/', $parts);
}

function to_celcius($f) {
 return roundUpToAny(($f-32)*5/9,0.5);
}

function to_fahrenheit($c) {
 return roundUpToAny(($c*9/5)+32,0.5);
}

function roundUpToAny($n,$x) {
    return round(($n+$x/2)/$x,0,PHP_ROUND_HALF_DOWN)*$x;
}

function IsNullOrEmptyString($question){
    return (!isset($question) || trim($question)==='');
}

function timeExpired(&$lasttime, $minutes) {
	if (is_string($lasttime)) {
		$lasttime = strtotime($lasttime);
	}
//	if ((int)(abs(time()-$lasttime) / 60) >= $minutes) {
// 	Try to prevent drifting (really depends on the calling interval)
	//if  ($minutes > 10) $minutes--;
	if ((int)(abs(time()-$lasttime) / 60) >= $minutes) {
		$lasttime = time();
		return true;
	}
	return false;
}

function dec2hex($num,$count=0)
{
        $ret = "";
 
        while ($num > 0)
        {
                $tmp = $num % 16;
                $num = (int)$num / 16;
 
                if ($num == 0) break;
 
                switch($tmp)
                {
                case 10:
                        $ret .= "A";
                        break;
                case 11:
                        $ret .= "B";
                        break;
                case 12:
                        $ret .= "C";
                        break;
                case 13:
                        $ret .= "D";
                        break;
                case 14:
                        $ret .= "E";
                        break;
                case 15:
                        $ret .= "F";
                        break;
                default:
                        $ret .= (string)$tmp;
                }
 
        }
 
        if (strlen($ret) < $count) $ret .= str_repeat("0",$count-strlen($ret));
 
        return strrev($ret);
}

function createthumb($name,$destination,$new_w,$new_h, $text = "")
{
	$system=explode(".",$name);
	try {
		if (preg_match("/jpg|jpeg/",$system[1])){$src_img=imagecreatefromjpeg($name);}
		if (preg_match("/png/",$system[1])){$src_img=imagecreatefrompng($name);}
		$old_x=imageSX($src_img);
		$old_y=imageSY($src_img);
	}
	catch (Exception $e) {
		echo $e->getMessage();
		return false;
	}
	if ($new_w < $old_x) {
		if ($old_x > $old_y) 
		{
			$thumb_w=$new_w;
			$thumb_h=(int)($old_y*($new_h/$old_x));
		}
		if ($old_x < $old_y) 
		{
			$thumb_w=(int)($old_x*($new_w/$old_y));
			$thumb_h=$new_h;
		}
		if ($old_x == $old_y) 
		{
			$thumb_w=$new_w;
			$thumb_h=$new_h;
		}
		// echo $thumb_w.CRLF;
		// echo $thumb_h.CRLF;
		
		//set image type and set image name
		$dpath = pathinfo($destination);
		// print_r($dpath);
		$ipath = pathinfo($name);
		// print_r($ipath);
		$itype = strtolower($ipath["extension"]);
		$iname = $dpath["filename"]."_".$thumb_w.'.'.$itype;
		// echo $dpath['dirname'].'/'.$iname.CRLF;
		$dst_img=ImageCreateTrueColor($thumb_w,$thumb_h);
		imagecopyresampled($dst_img,$src_img,0,0,0,0,$thumb_w,$thumb_h,$old_x,$old_y); 
		if ($text != "") {
			$white = imagecolorallocate($dst_img, 0xff, 0xff, 0xff);
			$grey = imagecolorallocate($dst_img, 128, 128, 128);
			$font = '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf';
			imagettftext($dst_img, 12, 0, 11, 21, $grey, $font, $text);
			imagettftext($dst_img, 12, 0, 10, 20, $white, $font, $text);
		}
		if (preg_match("/png/",$system[1]))
		{
			imagepng($dst_img,$dpath['dirname'].'/'.$iname); 
		} else {
			imagejpeg($dst_img,$dpath['dirname'].'/'.$iname); 
		}
		imagedestroy($dst_img); 
		imagedestroy($src_img); 
	}
	return true;
}

//clean all empty values from array
function cleanarray($array)
{
    if (is_array($array))
    {
        foreach ($array as $key => $sub_array)
        {
            $result = cleanarray($sub_array);
            if ($result === false)
            {
                unset($array[$key]);
            }
            else
            {
                $array[$key] = $result;
            }
        }
    }

    if (empty($array))
    {
        return false;
    }

    return $array;
}

function findByKeyValue($array, $field, $value)
{
   foreach($array as $key => $row)
   {
      if ( $row[$field] === $value )
         return $key;
   }
   return false;
}

function ReloadScreenShot() {
	$url = 'http://htpc:8085/HipScreenShot.jpg';
	$img = 'images/HIPScreenshot.jpg';
	file_put_contents($img, file_get_contents($url));
	$post = RestClient::post('http://htpc:8085/index.htm');
	return;  // ReadCurlReturn($post);
}

function hex2rgb($hex) {
   $hex = str_replace("#", "", $hex);

   if(strlen($hex) == 3) {
      $r = hexdec(substr($hex,0,1).substr($hex,0,1));
      $g = hexdec(substr($hex,1,1).substr($hex,1,1));
      $b = hexdec(substr($hex,2,1).substr($hex,2,1));
   } else {
      $r = hexdec(substr($hex,0,2));
      $g = hexdec(substr($hex,2,2));
      $b = hexdec(substr($hex,4,2));
   }
   $rgb = array($r, $g, $b);
   //return implode(",", $rgb); // returns the rgb values separated by commas
   return $rgb; // returns an array with the rgb values
}

function rgb2hex($rgb) {
   $hex = "#";
   $hex .= str_pad(dechex($rgb[0]), 2, "0", STR_PAD_LEFT);
   $hex .= str_pad(dechex($rgb[1]), 2, "0", STR_PAD_LEFT);
   $hex .= str_pad(dechex($rgb[2]), 2, "0", STR_PAD_LEFT);

   return $hex; // returns the hex value including the number sign (#)
}
function colorDuplicateProperty($rows0, $columnname, $lastcolor) {
// Array {'Status`Zone 1' => 1,	'Status`Zone 2' => ""}

	if ($columnname != "id" ||$columnname != "Date") {
		$t = explode('`',$columnname);
		$propname = $t[0];
		//echo $propname.CRLF;
		foreach ($rows0 as $header=>$value) {
			if(substr($header, 0, strlen($propname))==$propname) {
				if ($columnname!=$header) {
					$colors = hex2rgb($lastcolor);
					foreach($colors as $key=>$value) {
						if ($value <= 127) {
							$colors[$key] = $value+20;
						} else {
							$colors[$key] = $value-20;
						}
					}
					$lastcolor=rgb2hex($colors);
				}
				break;
			}
		}
	}
//	echo $propname.' '.$lastcolor.CRLF;
	return $lastcolor;
}

function sortArrayByArray(Array $array, Array $orderArray) {
    $ordered = array();
    foreach($orderArray as $key) {
        if(array_key_exists($key,$array)) {
            $ordered[$key] = $array[$key];
            unset($array[$key]);
        }
    }
    return $ordered + $array;
}

function getLastKey($arr) {
	end($arr);
	return key($arr);
}

function prettyPrint( $json ) {
    $result = '';
    $level = 0;
    $in_quotes = false;
    $in_escape = false;
    $ends_line_level = NULL;
    $json_length = strlen( $json );

    for( $i = 0; $i < $json_length; $i++ ) {
        $char = $json[$i];
        $new_line_level = NULL;
        $post = "";
        if( $ends_line_level !== NULL ) {
            $new_line_level = $ends_line_level;
            $ends_line_level = NULL;
        }
        if ( $in_escape ) {
            $in_escape = false;
        } else if( $char === '"' ) {
            $in_quotes = !$in_quotes;
        } else if( ! $in_quotes ) {
            switch( $char ) {
                case '}': case ']':
                    $level--;
                    $ends_line_level = NULL;
                    $new_line_level = $level;
                    break;

                case '{': case '[':
                    $level++;
                case ',':
                    $ends_line_level = $level;
                    break;

                case ':':
                    $post = " ";
                    break;

                case " ": case "\t": case "\n": case "\r":
                    $char = "";
                    $ends_line_level = $new_line_level;
                    $new_line_level = NULL;
                    break;
            }
        } else if ( $char === '\\' ) {
            $in_escape = true;
        }
        if( $new_line_level !== NULL ) {
            $result .= "\n".str_repeat( "\t", $new_line_level );
        }
        $result .= $char.$post;
    }

    return $result;
}

function setURL($params, $morepage = null) {

	$connect = $params['device']['connection'];
	if (empty($connect['targetaddress'])) {
		$url = "http://".$params['device']['ipaddress']['ip'];
	} else {
		$url = $connect['targetaddress'];
	}
	if (!empty($connect['targetport'])) $url .= ":".$connect['targetport'];
	if (!empty($connect['page'])) $url .= '/'.ltrim($connect['page'],'/');
	if (!empty($morepage)) $url .= $morepage;
	
	// Do not do this http://
	// $url = str_replace('//', '/', $url);
	
	return trim($url);
}

function setAuthentication($device) {

	if ($device['connection']['authentication'] == "NONE") return null;
	$authentication['method'] = $device['connection']['authentication'];
	$authentication['username'] = $device['connection']['username'];
	$authentication['password'] = $device['connection']['password'];
	$authentication['api_key'] = $device['connection']['api_key'];
	return $authentication;
}

function getDawn() {
	return getDeviceProperties(Array('deviceID' => DEVICE_DARK_OUTSIDE, 'description' => "Astronomy Sunrise"))['value'];
}

function getDusk() {
	return getDeviceProperties(Array('deviceID' => DEVICE_DARK_OUTSIDE, 'description' => "Astronomy Sunset"))['value'];
}

function search_array_key_value($array, $key, $value)
{
    $results = array();
    search_r($array, $key, $value, $results);
    return $results;
}

function search_r($array, $key, $value, &$results)
{
    if (!is_array($array)) {
        return;
    }

    if (isset($array[$key]) && $array[$key] == $value) {
        $results[] = $array;
    }

    foreach ($array as $subarray) {
        search_r($subarray, $key, $value, $results);
    }
}

function get_string_between($string, $start, $end, $occ = 1){
    $string = ' ' . $string;
	$ini = -1;
	for ($i=1; $i<=$occ; $i++) {
		$ini++;
		$ini = strpos($string, $start, $ini);
	}
    if ($ini == 0) return '';
    $ini += strlen($start);
    $len = strpos($string, $end, $ini) - $ini;
    return substr($string, $ini, $len);
}

function data_uri($file, $mime) 
{  
  $contents = file_get_contents($file);
  $base64   = base64_encode($contents); 
  return ('data:' . $mime . ';base64,' . $base64);
}
if( !function_exists('apache_request_headers') ) {
///
	function apache_request_headers() {
	  $arh = array();
	  $rx_http = '/\AHTTP_/';
	  foreach($_SERVER as $key => $val) {
		if( preg_match($rx_http, $key) ) {
		  $arh_key = preg_replace($rx_http, '', $key);
		  $rx_matches = array();
		  // do some nasty string manipulations to restore the original letter case
		  // this should work in most cases
		  $rx_matches = explode('_', $arh_key);
		  if( count($rx_matches) > 0 and strlen($arh_key) > 2 ) {
			foreach($rx_matches as $ak_key => $ak_val) $rx_matches[$ak_key] = ucfirst($ak_val);
			$arh_key = implode('-', $rx_matches);
		  }
		  $arh[$arh_key] = $val;
		}
	  }
	  return( $arh );
	}
///
}
///

/**
 * Add a debug out put section
 *
 * @param   mixed  $content String/object
 * @param   string $title   Debug title
 *
 * @return  void
 */
function debug($content, $title = 'output:') {

if (isset($GLOBALS['debug'])) {
		
		$function = "";
		$trace = array_reverse(debug_backtrace());
		foreach ($trace as $key => $level) {
			if ($key > $GLOBALS['debug']) return;
			if ($level['function'] == 'sendCommand') {
				// echo "<pre>";
				// print_r($level);
				// echo "</pre>";
				$mysql = 'SELECT description FROM ha_mf_commands WHERE id = '.(is_array($level['args'][0]) && array_key_exists('commandID', $level['args'][0]) ? $level['args'][0]['commandID'] : -1); 
				$pdo = openDB();
				$found = true;
				try	{
					$res_row = $pdo->query($mysql);
					$rows = $res_row->fetch(PDO::FETCH_ASSOC);
				} catch( Exception $e )	{
					$found = false;
				}
				if ($found) 
					$function .=  $level['function'].'('.$rows['description'].')->';
				else
					$function .= $level['function'].'->';
			} elseif ($level['function'] == 'setDevicePropertyValue') {
				if (array_key_exists('propertyID', $level['args'][0])) {
					$mysql = 'SELECT description FROM ha_mi_properties WHERE id = '.$level['args'][0]['propertyID']; 
					$pdo = openDB();
					$found = true;
					try	{
						$res_row = $pdo->query($mysql);
						$rows = $res_row->fetch(PDO::FETCH_ASSOC);
					} catch( Exception $e )	{
						$found = false;
					}
					if ($found) 
						$function .=  $level['function'].'('.$rows['description'].')->';
					else
						$function .= $level['function'].'->';
				}
			} else {
				$function .= ($level['function'] != 'debug' ? $level['function'].'->' : '');
			}
		}

		echo (!isCLI() ? '<div class="myDebugOutputTitle">' .$function.$title . '</div>' : $function.$title."\n");
		if (!isCLI()) echo '<div class="myDebugOutput myDebugHidden">';

		if (is_object($content) || is_array($content)) {
			echo (!isCLI() ? '<pre>' . htmlspecialchars(print_r($content, true)) . '</pre>' : print_r($content, true));
		} else {
			// Remove any <pre> tags provided by e.g. JQuery::dump
			if (!isCLI()) {
				$content = preg_replace('/(^\s*<pre( .*)?>)|(<\/pre>\s*$)/i', '', $content);
				echo nl2br(htmlspecialchars($content));
			} else {
				echo $content.CRLF;
			}
		}
		if (!isCLI()) echo '</div>';
	}
}

function var_debug($variable,$strlen=100,$width=25,$depth=10,$i=0,&$objects = array()) {
  $search = array("\0", "\a", "\b", "\f", "\n", "\r", "\t", "\v");
  $replace = array('\0', '\a', '\b', '\f', '\n', '\r', '\t', '\v');
 
  $string = '';
 
  switch(gettype($variable)) {
    case 'boolean':      $string.= $variable?'true':'false'; break;
    case 'integer':      $string.= $variable;                break;
    case 'double':       $string.= $variable;                break;
    case 'resource':     $string.= '[resource]';             break;
    case 'NULL':         $string.= "null";                   break;
    case 'unknown type': $string.= '???';                    break;
    case 'string':
      $len = strlen($variable);
      $variable = str_replace($search,$replace,substr($variable,0,$strlen),$count);
      $variable = substr($variable,0,$strlen);
      if ($len<$strlen) $string.= '"'.$variable.'"';
      else $string.= 'string('.$len.'): "'.$variable.'"...';
      break;
    case 'array':
      $len = count($variable);
      if ($i==$depth) $string.= 'array('.$len.') {...}';
      elseif(!$len) $string.= 'array(0) {}';
      else {
        $keys = array_keys($variable);
        $spaces = str_repeat(' ',$i*2);
        $string.= "array($len)\n".$spaces.'{';
        $count=0;
        foreach($keys as $key) {
          if ($count==$width) {
            $string.= "\n".$spaces."  ...";
            break;
          }
          $string.= "\n".$spaces."  [$key] => ";
          //$string.= var_debug($variable[$key],$strlen,$width,$depth,$i+1,$objects);
          $count++;
        }
        $string.="\n".$spaces.'}';
      }
      break;
    case 'object':
      $id = array_search($variable,$objects,true);
      if ($id!==false)
        $string.=get_class($variable).'#'.($id+1).' {...}';
      else if($i==$depth)
        $string.=get_class($variable).' {...}';
      else {
        $id = array_push($objects,$variable);
        $array = (array)$variable;
        $spaces = str_repeat(' ',$i*2);
        $string.= get_class($variable)."#$id\n".$spaces.'{';
        $properties = array_keys($array);
        foreach($properties as $property) {
          $name = str_replace("\0",':',trim($property));
          $string.= "\n".$spaces."  [$name] => ";
          //$string.= var_debug($array[$property],$strlen,$width,$depth,$i+1,$objects);
        }
        $string.= "\n".$spaces.'}';
      }
      break;
  }
 
  if ($i>0) return $string;
 
  $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
  do $caller = array_shift($backtrace); while ($caller && !isset($caller['file']));
  if ($caller) $string = $caller['file'].':'.$caller['line']."\n".$string;
 
  echo $string;
}

function myUrlEncode($string) {
    $entities =     array('%21', '%2A', '%27', '%28', '%29', '%3B', '%3A', '%40', '%26', '%3D', '%2B', '%24', '%2C', '%2F', '%3F', '%25', '%23', '%5B', '%5D');
    $replacements = array('!',   '*',    "'",   "(",   ")",   ";",   ":",   "@",   "&",   "=",   "+",   "$",   ",",   "/",   "?",   "%",   "#",   "[",   "]");
    return str_replace($entities, $replacements, urlencode($string));
}

function greeting() {
    /* This sets the $time variable to the current hour in the 24 hour clock format */
    $time = date("H");
    /* Set the $timezone variable to become the current timezone */
    $timezone = date("e");
    /* If the time is less than 1200 hours, show good morning */
    if ($time < "12") {
        $greeting = "Good morning";
    } else
    /* If the time is grater than or equal to 1200 hours, but less than 1700 hours, so good afternoon */
    if ($time >= "12" && $time < "17") {
        $greeting = "Good afternoon";
    } else
    /* Should the time be between or equal to 1700 and 1900 hours, show good evening */
    if ($time >= "17" && $time < "19") {
        $greeting = "Good evening";
    } else
    /* Finally, show good night if the time is greater than or equal to 1900 hours */
    if ($time >= "19") {
        $greeting = "Good night";
    }
	return $greeting;
}
?>
