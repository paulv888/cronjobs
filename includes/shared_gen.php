<?php
function to_celcius($f) {
 return roundUpToAny((5/9)*($f-32),0.5);
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
	$nowdt = time();
	if ((int)(abs($nowdt-$lasttime) / 60) >= $minutes) {
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
?>