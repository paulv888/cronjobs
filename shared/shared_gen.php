<?php
//define( 'DEBUG_GRAPH', TRUE );
if (!defined('DEBUG_GRAPH')) define( 'DEBUG_GRAPH', FALSE );

define( 'MAX_DATAPOINTS', 1000 );

function graphCreate($params) {
	if (DEBUG_GRAPH) echo "<pre>params: ";
	if (DEBUG_GRAPH) print_r($params);
	parse_str(urldecode($params['commandvalue']), $fparams);
	if (DEBUG_GRAPH) print_r($fparams);

	if (!array_key_exists('0', $fparams['fabrik___filter']['list_231_com_fabrik_231']['value'])) {
		$result['error']="No Device selected";
		return $result;
	}
	$devices = implode(",",$fparams['fabrik___filter']['list_231_com_fabrik_231']['value']['0']);
	foreach ($fparams['fabrik___filter']['list_231_com_fabrik_231']['value']['0'] as $deviceID) {
		$caption[] = FetchRow('select description from ha_mf_devices where id='.$deviceID)['description'];
	}
	if (!array_key_exists('1', $fparams['fabrik___filter']['list_231_com_fabrik_231']['value'])) {
		//$result['error']="No Device selected";
		//return $result;
		$result = listDeviceProperties($devices);
		$result = array_unique($result, SORT_NUMERIC);
		//if (DEBUG_GRAPH) print_r($result);
		$properties = implode(",", $result);
	} else {
		$properties = implode(",",$fparams['fabrik___filter']['list_231_com_fabrik_231']['value']['1']);
	}
	
	if (empty($fparams['fabrik___filter']['list_231_com_fabrik_231']['value']['2']['0'])) {
		$startdate = date( 'Y-m-d 00:00:00', strtotime("-1 days"));
		$enddate = date( 'Y-m-d 23:59:59', strtotime("tomorrow"));
	} else {
		$startdate = $fparams['fabrik___filter']['list_231_com_fabrik_231']['value']['2']['0'];
		$enddate = $fparams['fabrik___filter']['list_231_com_fabrik_231']['value']['2']['1'];
		$startdate = date( 'Y-m-d 00:00:00', strtotime($startdate));
		$enddate = date( 'Y-m-d 23:59:59', strtotime($enddate));
	}
	
	$debug = 1;
	if (DEBUG_GRAPH) {
		echo $devices.CRLF;
		echo $properties.CRLF;
		echo $startdate.CRLF; 
		echo $enddate.CRLF; 
	}
	
	//call sp_properties( '60,114,201', '138,126,123,127,124', "2015-09-25 00:00:00", "2015-09-27 23:59:59", 1) 
	$mysql='call sp_properties( "'.$devices.'", "'.$properties.'", "'.$startdate.'" , "'.$enddate.'", 1);';
	if (DEBUG_GRAPH) echo $mysql.CRLF;
	if ($rows = FetchRows($mysql)) {
		//print_r($rows);
		global $mysql_link;
		mysql_close($mysql_link);
		openMySql();


		$mysql='SELECT * FROM ha_mi_properties WHERE id IN ('.$properties.');';
		$rowsprops = FetchRows($mysql);
	
		$tablename="graph_0";
		$hidden = (DEBUG_GRAPH ? '' : ' hidden ');
		
		if (count($rows)> MAX_DATAPOINTS) {
			$rows = array_slice($rows, -MAX_DATAPOINTS, count($rows) ); 
			echo "Too much data, took last : ".MAX_DATAPOINTS;
		}
		$s = $rows[0]['Date'];
		$e = $rows[count($rows)-1]['Date'];
		//	echo (int)(abs(strtotime($e)-strtotime($s))/300); //60*5 min intervals = 300?
		$tickinterval=(abs(strtotime($e)-strtotime($s)))*100; 
		//var_dump ($tickinterval);
		$tickinterval=roundUpToAny(abs(strtotime($e)-strtotime($s))*50,3600000);
		//var_dump($tickinterval);
		//
		//echo $tickinterval.CRLF;
		//echo count($rows).CRLF;
		
		echo '<table id="'.$tablename.'" class="'.$tablename.$hidden.' table table-striped table-hover" data-graph-xaxis-type="datetime" data-graph-yaxis-2-opposite="1" 
				data-graph-xaxis-tick-interval="'.$tickinterval.'" data-graph-xaxis-align="right" data-graph-xaxis-rotation="270" data-graph-type="spline" 
				data-graph-container-before="1" data-graph-zoom-type="x" data-graph-height="500" >';
		//data-graph-xaxis-type="datetime"

		if (DEBUG_GRAPH) print_r($rowsprops);

		if (DEBUG_GRAPH) echo "</pre>";
		echo '<caption>'.implode(", ",$caption).'</caption>';
		echo '<thead><tr class="fabrik___heading">';
		foreach($rows[0] as $header=>$value){
			if ($header != "id") {
				$datastr="";
				if ($header != "Date") {
					//print_r($rows[0]);
					$t = explode('`',$header);
					$propID = getProperty($t[0])['id'];
					if (($prodIdx = findByKeyValue($rowsprops,'id',$propID)) !== false) {
						//echo "Found: ".$rowsprops[$prodIdx]['description'].CRLF;
						if (!empty($rowsprops[$prodIdx]['color'])) {
							if (strpos($rowsprops[$prodIdx]['color'],",")>0) { 	// First time  read RGB from DB
								$rowsprops[$prodIdx]['color'] = rgb2hex(explode(",",$rowsprops[$prodIdx]['color']));
							}
							$rowsprops[$prodIdx]['color'] = colorDuplicateProperty($rows[0], $header, $rowsprops[$prodIdx]['color']);
							$datastr.='data-graph-color="'.$rowsprops[$prodIdx]['color'].'" ';
						}
						if (!empty(trim($rowsprops[$prodIdx]['dash_style']))) $datastr.='data-graph-dash-style="'.$rowsprops[$prodIdx]['dash_style'].'" ';
						if ($rowsprops[$prodIdx]['hidden']==1) $datastr.='data-graph-hidden="'.$rowsprops[$prodIdx]['hidden'].'" ';
						if ($rowsprops[$prodIdx]['skip']) $datastr.='data-graph-skip="'.$rowsprops[$prodIdx]['skip'].'" ';
						if ($rowsprops[$prodIdx]['stack_group']) $datastr.='data-graph-stack-group="'.$rowsprops[$prodIdx]['stack_group'].'" ';
						$datastr.='data-graph-yaxis="'.$rowsprops[$prodIdx]['yaxis'].'" ';
						if ($rowsprops[$prodIdx]['type']) $datastr.='data-graph-type="'.$rowsprops[$prodIdx]['type'].'" ';
						if ($rowsprops[$prodIdx]['value_scale']) $datastr.='data-graph-value-scale="'.$rowsprops[$prodIdx]['value_scale'].'" ';
						if ($rowsprops[$prodIdx]['datalabels_enabled']) $datastr.='data-graph-datalabels-enabled="'.$rowsprops[$prodIdx]['datalabels_enabled'].'" ';
						if ($rowsprops[$prodIdx]['datalabels_color']) $datastr.='data-graph-datalabels-color="'.$rowsprops[$prodIdx]['datalabels_color'].'" ';
						if ($rowsprops[$prodIdx]['markers_enabled']) $datastr.='data-graph-markers-enabled="'.$rowsprops[$prodIdx]['markers_enabled'].'" ';
					} else {
						echo "Property $header not found!!!: $prodIdx".CRLF;
					}
				}
				echo '<th id="'.$tablename.'_'.$header.'_header" '.$datastr;
				echo '>' . str_replace('`',' ',$header). '</th>';
			}
//			if ($value == " ") {
//				$rows[0][$header]=" ";
//			}
		}
		echo '</tr></thead>';
		echo '<tbody>';
		$x=0;
		foreach($rows as $key=>$row) {
			echo '<tr id="'.$tablename.'row_'.$row['id'].'">';
			foreach($row as $key2=>$value2){
				if ($key2 != "id") {
					if ($value2 != " ") {
						if ($key2 != "Date") {
							echo '<td id="'.$tablename.'_'.$key2.'_'.$row['id'].'" data-graph-x="'.$x.'">' . $value2 . '</td>';
						} else {
							echo '<td id="'.$tablename.'_'.$key2.'_'.$row['id'].'">' . $value2 . '</td>';
						}
					} else {
						echo '<td id="'.$tablename.'_'.$key2.'_'.'Null'.'">' . $value2 . '</td>';
					}
				}
			}
			echo '</tr>';
			$x++;
		}
		echo '</tbody>';
		echo '</table>';
	}

	return;

}

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
?>
