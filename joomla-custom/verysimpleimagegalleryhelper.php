<?php
/*
// "Very Simple Image Gallery" Plugin for Joomla 3.1 - Version 1.6.8
// License: GNU General Public License version 2 or later; see LICENSE.txt
// Author: Andreas Berger - andreas_berger@bretteleben.de
// Copyright (C) 2013 Andreas Berger - http://www.bretteleben.de. All rights reserved.
// Project page and Demo at http://www.bretteleben.de
// ***Last update: 2013-08-15***
*/

defined( '_JEXEC' ) or die( 'Restricted access' );

// Import library dependencies
jimport('joomla.filesystem.file');
jimport('joomla.filesystem.folder');

// Helper Class
class plgContentVerysimpleimagegalleryHelper {

		// replace plugin-calls and try to remove enclosing paragraphs
    public static function beReplaceCall( $myneedle, $myreplacement, $myhaystack) {

		/* parameters
    $myneedle				the string to replace
    $myreplacement	what to insert
    $myhaystack			where to search
    */

			$myneedle = preg_quote($myneedle, '#');
			if(preg_match("#<p>(\s|<br />)*".$myneedle."(\s|<br />)*</p>#s", $myhaystack)>=1){
				$myhaystack = preg_replace( "#<p>(\s|<br />)*".$myneedle."(\s|<br />)*</p>#s", $myreplacement , $myhaystack ,1);
			}
			else{
				$myhaystack = preg_replace( "#".$myneedle."#s", $myreplacement , $myhaystack ,1);
			}
			return $myhaystack;
		}

		// sort image array according the set sort order
    public static function beSortImages( $myarray, $myorder) {

		/* parameters
    $myarray			the array to sort
			($images[] = array('filename' => VALUE, 'flastmod' => VALUE);)
    $myorder			the sort order
    */

			unset($theage); //unset temporary array
			unset($thename); //unset temporary array
			switch ($myorder) {
				case 1: //alphabetic descending caseinsensitive
					foreach ($myarray as $key => $val) {$thename[$key]=substr(strtolower($val['filename']),0,-4);}
					array_multisort($thename, SORT_DESC, $myarray);
					break;
				case 2: //old to new
					foreach ($myarray as $key => $val) {$theage[$key]=$val['flastmod'];}
					array_multisort($theage, SORT_ASC, $myarray);
					break;
				case 3: //new to old
					foreach ($myarray as $key => $val) {$theage[$key]=$val['flastmod'];}
					array_multisort($theage, SORT_DESC, $myarray);
					break;
				case 4: //random
					shuffle($myarray);
					break;
				default: //alphabetic ascending caseinsensitive
					foreach ($myarray as $key => $val) {$thename[$key]=substr(strtolower($val['filename']),0,-4);}
					array_multisort($thename, SORT_ASC, $myarray);
					break;
			}
			return $myarray;
		}

		// replace quotes
    public static function beKickQuotes($e) {

		/* parameters
    $e	string
    */

			$e = str_replace('"', '\\"', $e);
			$e = str_replace("'", "&#39;", $e);
			return $e;
		}

		// create a directory
    public static function beMakeFolder($fld, $func) {

		/* parameters
    $fld	folder
    $func	function
    */

			if(!JFolder::create($fld)) {
				echo "Failed creating ".$func." directory ".$fld;
				return;
			}
			$dontpassbyref="<html>\n<body bgcolor=\"#FFFFFF\">\n</body>\n</html>";
			if(!JFile::write($fld."index.html", $dontpassbyref)) {
				echo "Failed creating index.html in  ".$func." directory ".$fld;
				return;
			}
		}

		// resize images and save the result to a given folder
    public static function beResizeImg( $isrc, $idst, $iw, $ih, $ip='keep', $imax='yes', $iqual=95) {

		/* parameters
    $isrc					source file
    $idst					destination folder
    $iw						new width
    $ih						new height
    $ip='keep'		keep proportions
    $imax='yes'		treat width/height as maximums
    $iqual=95			image quality
    */

			//read array with source image information
			$imagedata = getimagesize($isrc);
	
			//calculate new width and height
				$srcwid=$imagedata[0];
				$srchei=$imagedata[1];
				$srcx=$srcy=0;
			if ($srcwid < $iw) {

    // [0] => /home/www/vlohome/data/cameras/cam-1/2017-12-03/23-45-15/vsig_thumbs/2017-12-03 20234516_00_990_742_80.jpg
    // [1] => 2017-12-03%20234516_00_990_742_80.jpg
    // [2] => jpg
    // [3] => 990
    // [4] => 742
				//set image type and set image name
				$ipath = pathinfo($isrc);
				$itype = strtolower($ipath["extension"]);
				$iname = substr($ipath["basename"], 0, -(strlen($itype)+1)).".".$itype;
				$idir = $ipath["dirname"];
				return $thenewimage=array($idir.'/'.$iname,$iname,$itype,$srcwid,$srchei);
			}

			//if no height is set we calculate it
			if($ih=="full"){
				$ih=(int)($imagedata[1]*($iw/$imagedata[0]));
			}
				
			//the new width
				$new_iw = (int)$iw;
			//the new height
			if($ip=='keep'){ //keep proportions
				$new_ih = (int)($imagedata[1]*($new_iw/$imagedata[0]));
				if($new_ih > $ih) {
					$new_ih = (int)$ih;
					$new_iw = (int)($imagedata[0]*($new_ih/$imagedata[1]));
				}
			}
			else { //set fixed height
				$new_ih = (int)$ih;
				//crop
				if($imagedata[0]/$new_iw<$imagedata[1]/$new_ih){ //original to heigh
					$srchei=(int)($new_ih*($imagedata[0]/$new_iw));
					$srcy=(int)(($imagedata[1]-$srchei)/2);
				}
				elseif($imagedata[0]/$new_iw>$imagedata[1]/$new_ih) { //original to wide
					$srcwid=(int)($new_iw*($imagedata[1]/$new_ih));
					$srcx=(int)(($imagedata[0]-$srcwid)/2);
				}
			}

			//set image type and set image name
			$ipath = pathinfo($isrc);
			$itype = strtolower($ipath["extension"]);
			$iname = substr($ipath["basename"], 0, -(strlen($itype)+1))."_".$new_iw."_".$new_ih."_".$iqual.".".$itype;
	
			//check if $idst is set to a subdirectory and if so add it to the path
			$idir = ($idst!="")?($ipath["dirname"].'/'.$idst):($ipath["dirname"]);
	
			//check if image exists, else create it
				if(!JFile::exists($idir.'/'.$iname)){
				if($itype=="jpg"&&function_exists("imagecreatefromjpeg")){
					$image = imagecreatefromjpeg($isrc);
					$image_dest = imagecreatetruecolor($new_iw, $new_ih);
					imagecopyresampled($image_dest, $image, 0, 0, $srcx, $srcy, $new_iw, $new_ih, $srcwid, $srchei);
					ob_start(); // start a new output buffer
					 imagejpeg($image_dest, NULL, $iqual);
					$buffer = ob_get_contents();
					ob_end_clean(); // stop this output buffer
				}
				elseif($itype=="gif"&&function_exists("imagecreatefromgif")){
					$image=imagecreatefromgif($isrc);
					$image_dest=imagecreatetruecolor($new_iw,$new_ih);
					imagealphablending($image_dest, false);
					// get and reallocate transparency-color
					$transindex = imagecolortransparent($image);
					if($transindex >= 0) {
					  $transcol = imagecolorsforindex($image, $transindex);
					  $transindex = imagecolorallocatealpha($image_dest, $transcol['red'], $transcol['green'], $transcol['blue'], 127);
					  imagefill($image_dest, 0, 0, $transindex);
					}
					// resample
					imagecopyresampled($image_dest, $image, 0, 0, $srcx, $srcy, $new_iw, $new_ih, $srcwid, $srchei);
					// restore transparency
					if($transindex >= 0) {
					  imagecolortransparent($image_dest, $transindex);
					  for($y=0; $y<$new_ih; ++$y){
					    for($x=0; $x<$new_iw; ++$x){
					      if(((imagecolorat($image_dest, $x, $y)>>24) & 0x7F) >= 100) {imagesetpixel($image_dest, $x, $y, $transindex);}
					    }
					  }
					}
					imagetruecolortopalette($image_dest, true, 255);
					imagesavealpha($image_dest, false);
					ob_start(); // start a new output buffer
					imagegif($image_dest, NULL, $iqual);
					$buffer = ob_get_contents();
					ob_end_clean(); // stop this output buffer
				}
				elseif($itype=="png"&&function_exists("imagecreatefrompng")){
					$image = ImageCreateFromPng($isrc);
					$image_dest = ImageCreateTrueColor($new_iw,$new_ih);
					$transindex = imagecolortransparent($image);
					$istruecolor = imageistruecolor($image);

					if($transindex>=0) {
						ImageColorTransparent($image_dest, ImageColorAllocate($image_dest, 0, 0, 0));
						ImageAlphaBlending($image_dest, false);
					}
					elseif(!$istruecolor) {
						ImagePaletteCopy($image_dest,$image);
					}
					else {
						ImageColorTransparent($image_dest, ImageColorAllocate($image_dest, 0, 0, 0));
						ImageAlphaBlending($image_dest, false);
						ImageSaveAlpha($image_dest, true);
					}
					ImageCopyResized($image_dest, $image, 0, 0, $srcx, $srcy, $new_iw, $new_ih, $srcwid, $srchei);
					$iqual_png = 100-$iqual;
					if(substr(phpversion(), 0, 1)>=5){$iqual_png=intval(($iqual-10)/10);}
					ob_start(); // start a new output buffer
					Imagepng($image_dest,NULL,$iqual_png);
					$buffer = ob_get_contents();
					ob_end_clean(); // stop this output buffer
				}

				if(isset($buffer)&&$buffer!=""){JFile::write($idir.'/'.$iname, $buffer);unset($buffer);}
				if(isset($image)){imagedestroy($image);}
				if(isset($image_dest)){imagedestroy($image_dest);}
			}

			//utf8_encode and rawurlencode file name
			//$iname=rawurlencode(utf8_encode($iname));
			//return path/filename/type/width/height
			return $thenewimage=array($idir.'/'.$iname,$idst."/".$iname,$itype,$new_iw,$new_ih);
		}

		// check for mb_strtolower and use it if available
    public static function beStrtolower($mystring) {

		/* parameters
    $mystring				the string to convert
    */
		$mystring=(plgContentVerysimpleimagegalleryHelper::beIs_utf8($mystring))?($mystring):(utf8_encode($mystring));

		$mystring=(function_exists('mb_strtolower'))?(mb_strtolower($mystring)):(strtolower($mystring));
		return $mystring;
		}

		// Returns true if $string is valid UTF-8 and false otherwise.
		public static function beIs_utf8($string) {
    // From http://w3.org/International/questions/qa-forms-utf-8.html
    return preg_match('%^(?:
          [\x09\x0A\x0D\x20-\x7E]            # ASCII
        | [\xC2-\xDF][\x80-\xBF]             # non-overlong 2-byte
        |  \xE0[\xA0-\xBF][\x80-\xBF]        # excluding overlongs
        | [\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}  # straight 3-byte
        |  \xED[\x80-\x9F][\x80-\xBF]        # excluding surrogates
        |  \xF0[\x90-\xBF][\x80-\xBF]{2}     # planes 1-3
        | [\xF1-\xF3][\x80-\xBF]{3}          # planes 4-15
        |  \xF4[\x80-\x8F][\x80-\xBF]{2}     # plane 16
    )*$%xs', $string);
		}

}
?>