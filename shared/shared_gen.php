<?php
//define( 'DEBUG_GRAPH', TRUE );
if (!defined('DEBUG_GRAPH')) define( 'DEBUG_GRAPH', FALSE );

define( 'MAX_DATAPOINTS', 1000 );

function getPath(){
	$str = $_SERVER['SCRIPT_FILENAME'];
	$chunks = explode('/', $str);
	unset($chunks[count($chunks)-1]);
	return implode('/', $chunks).'/';
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

function createthumb($name,$filename,$new_w,$new_h)
{
	$system=explode(".",$name);
	if (preg_match("/jpg|jpeg/",$system[1])){$src_img=imagecreatefromjpeg($name);}
	if (preg_match("/png/",$system[1])){$src_img=imagecreatefrompng($name);}
	$old_x=imageSX($src_img);
	$old_y=imageSY($src_img);
	if ($old_x > $old_y) 
	{
		$thumb_w=$new_w;
		$thumb_h=$old_y*($new_h/$old_x);
	}
	if ($old_x < $old_y) 
	{
		$thumb_w=$old_x*($new_w/$old_y);
		$thumb_h=$new_h;
	}
	if ($old_x == $old_y) 
	{
		$thumb_w=$new_w;
		$thumb_h=$new_h;
	}
	$dst_img=ImageCreateTrueColor($thumb_w,$thumb_h);
	imagecopyresampled($dst_img,$src_img,0,0,0,0,$thumb_w,$thumb_h,$old_x,$old_y); 
	if (preg_match("/png/",$system[1]))
	{
		imagepng($dst_img,$filename); 
	} else {
		imagejpeg($dst_img,$filename); 
	}
	imagedestroy($dst_img); 
	imagedestroy($src_img); 
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

function setURL($params, &$commandstr) {

// echo "<pre>";
// print_r($params);
	$connect = $params['device']['connection'];
	if (empty($connect['targetaddress'])) {
		$url = "http://".$params['device']['ipaddress']['ip'];
	} else {
		$url = $connect['targetaddress'];
	}
	if (!empty($connect['targetport'])) $url .= ":".$connect['targetport'].'/';
	if (!empty($connect['page'])) $url .= $connect['page'];
	$commandstr = $url;
	if (!empty($connect['username']) && !empty($connect['password'])) {
		$url = str_replace('//','//'.$connect['username'].':'.$connect['password'].'@', $url);
		$commandstr = str_replace('//','//'.'***:***@', $commandstr);
	}
	return $url;
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
?>
