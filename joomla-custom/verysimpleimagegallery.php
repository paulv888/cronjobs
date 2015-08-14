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
jimport('joomla.plugin.plugin');

class plgContentVerysimpleimagegallery extends JPlugin {

//#############################################################################

	//substitute article id - start
	//public $myarticleid = '00';//substitute article id on frontpage
	public $vsigcounter = 0;//substitute article id
	//substitute article id - end


//#############################################################################

	public function onContentPrepare($context, &$article, &$params, $limitstart=0) {

		// checking
		$document=JFactory::getDocument();   
		if($document->getType() != 'html') {
			return;
		}
		if (!isset($article->text)||!preg_match("#{vsig}(.*?){/vsig}#s", $article->text) ) {
			return;
		}
		//paths
		$path_absolute = 	JPATH_SITE;
		$path_site = 			JURI :: base(true);
		if(substr($path_site, -1)=="/") $path_site = substr($path_site, 0, -1);
		$path_imgroot = 	'/images/'; 																			// default image root folder //1.6
		$path_ctrls = 		'/images/vsig_buttons/'; 													// button folder
		$path_plugin = 		'/plugins/content/verysimpleimagegallery/files/'; // path to plugin folder
		$folder_thumbs = 	'vsig_thumbs'; 																		// thumbnail subfolder
//		$folder_images = 	'vsig_images'; 																		// image subfolder
		$folder_images = 	''; 																		// image subfolder

		// import helper
    JLoader::import( 'verysimpleimagegalleryhelper', dirname( __FILE__ ).'/files' );

//captions
		if (preg_match_all("#{vsig_c}(.*?){/vsig_c}#s", $article->text, $matches, PREG_PATTERN_ORDER) > 0) { //1.6
			foreach ($matches[0] as $match) {
				$_raw_cap_ = preg_replace("/{.+?}/", "", $match);
				$_raw_cap_exp_ = explode("|",$_raw_cap_);
				$cap1=($_raw_cap_exp_[1]&&trim($_raw_cap_exp_[1])!="")?(trim(plgContentVerysimpleimagegalleryHelper::beStrtolower($_raw_cap_exp_[1]))):("CAPDEFAULT");
				$cap2=($_raw_cap_exp_[2]&&trim($_raw_cap_exp_[2])!="")?(trim($_raw_cap_exp_[2])):("");
				$cap3=($_raw_cap_exp_[3]&&trim($_raw_cap_exp_[3])!="")?(trim($_raw_cap_exp_[3])):("");
				$caparray="cap_ar".$_raw_cap_exp_[0];
				if(!isset($$caparray)){$$caparray=array();};
				${$caparray}[$cap1]=array($cap2,$cap3);
				//remove the call
				$article->text = plgContentVerysimpleimagegalleryHelper::beReplaceCall("{vsig_c}".$_raw_cap_."{/vsig_c}",'', $article->text);
			}
		}
//captions

//links
		if (preg_match_all("#{vsig_l}(.*?){/vsig_l}#s", $article->text, $matches, PREG_PATTERN_ORDER) > 0) { //1.6
			foreach ($matches[0] as $match) {
				$_raw_link_ = preg_replace("/{.+?}/", "", $match);
				$_raw_link_exp_ = explode("|",$_raw_link_);
				$_link1=($_raw_link_exp_[1]&&trim($_raw_link_exp_[1])!="")?(trim(plgContentVerysimpleimagegalleryHelper::beStrtolower($_raw_link_exp_[1]))):("LINKDEFAULT");
				$_link2=($_raw_link_exp_[2]&&trim($_raw_link_exp_[2])!="")?(trim($_raw_link_exp_[2])):("");
				$_link3=($_raw_link_exp_[3]&&trim($_raw_link_exp_[3])!="")?(trim($_raw_link_exp_[3])):($_link2);
				$_link4=($_raw_link_exp_[4]&&trim($_raw_link_exp_[4])!="")?(trim($_raw_link_exp_[4])):("_self");
				$_linkarray="_linkar".$_raw_link_exp_[0];
				if(!isset($$_linkarray)){$$_linkarray=array();};
				${$_linkarray}[$_link1]=array($_link2,$_link3,$_link4);
				//remove the call
				$article->text = plgContentVerysimpleimagegalleryHelper::beReplaceCall("{vsig_l}".$_raw_link_."{/vsig_l}",'', $article->text);
			}
		}
//links

//images
		if (preg_match_all("#{vsig}(.*?){/vsig}#s", $article->text, $matches, PREG_PATTERN_ORDER) > 0) { //1.6
			$sigcount = -1;
			//substitute article id - start
			$headerstuff = $document->getHeadData();
			foreach($headerstuff['custom'] as $key => $custom){
				if(stristr($custom, 'vsig_count') !== false){
					$vsigcount=explode(" ", trim($custom));
					$this->vsigcounter=$vsigcount[2];
					unset($headerstuff['custom'][$key]);
				}
			}
		  $document->setHeadData($headerstuff);
			$this->vsigcounter = $this->vsigcounter+1;
			$document->addCustomTag('<!-- vsig_count '.$this->vsigcounter.' -->' );
			//substitute article id - end

			$_target=JRequest::getURI();
			$vsig_cssadd=""; //collect css for page head
			$vsig_script=""; //collect javascript for page head
			foreach ($matches[0] as $match) {
				$sigcount++;

				//split string and check for overrides
				$vsig_code = preg_replace("/{.+?}/", "", $match);
				$vsig_raw = explode ("|", $vsig_code);
				$_images_dir_ = trim($vsig_raw[0]);

				if(substr($_images_dir_,-1,1)!="/"&&$_images_dir_!=""){$_images_dir_=$_images_dir_."/";} //add trailing slash
				if(substr($_images_dir_,0,1)=="/"&&$_images_dir_!=""){$_images_dir_=substr($_images_dir_,1,strlen($_images_dir_)-1);} //remove leading slash
				//process overrides if any
				unset ($vsig_overrides);
				$vsig_overrides=array();
				if(count($vsig_raw)>=2){ //there are parameteroverrides
					for($i=1;$i<count($vsig_raw);$i++){
						$overr_temp=explode("=",$vsig_raw[$i]);
						if(count($overr_temp)>=2){
							$vsig_overrides[strtolower(trim($overr_temp[0]))]=trim($overr_temp[1]);
						}
					}
				}

				unset($images);
				$noimage = 0;
				//read and process the param for the image root
				$path_imgroot	= trim($this->params->get('imagepath', $path_imgroot));
				if(substr($path_imgroot, -1)!="/"){$path_imgroot=$path_imgroot."/";} //add trailing slash
				if(substr($path_imgroot,0,1)!="/"){$path_imgroot="/".$path_imgroot;} //add leading slash

				//check permissions and set default if possible
				//jimport('joomla.filesystem.path');
				//$dpcr = JPath::canChmod($path_absolute.$path_imgroot.$_images_dir_);
				//if($dpcr==1){$dpcr = JPath::setPermissions($path_absolute.$path_imgroot.$_images_dir_);};

				// read directory and check for images
				if ($dh = @opendir($path_absolute.$path_imgroot.$_images_dir_)) {
					while (($f = readdir($dh)) !== false) {
						if((substr(strtolower($f),-4) == '.jpg') || (substr(strtolower($f),-4) == '.gif') || (substr(strtolower($f),-4) == '.png')) {
							$noimage++;
							$images[] = array('filename' => $f, 'flastmod' => filemtime($path_absolute.$path_imgroot.$_images_dir_.$f)); 
						}
					}
					closedir($dh);
					//damn, found the folder but it is empty
					$html1="<br />Very Simple Image Gallery:<br />No images found in folder ".$path_absolute.$path_imgroot.$_images_dir_."<br />";
				}
				else {
					//you promised me a folder - where is it?
					$html1="<br />Very Simple Image Gallery:<br />Could not find folder ".$path_absolute.$path_imgroot.$_images_dir_."<br />";
				}

				if($noimage) {
					//read in parameters and overrides
					$_width_ 			= (array_key_exists("twidth",$vsig_overrides)&&$vsig_overrides['twidth']!="")?($vsig_overrides['twidth']):($this->params->get('th_width', 120));	//thumbs
					$_height_ 		= (array_key_exists("theight",$vsig_overrides)&&$vsig_overrides['theight']!="")?($vsig_overrides['theight']):($this->params->get('th_height', 90));	//thumbs
					$_keep_		 		= (array_key_exists("crop",$vsig_overrides)&&$vsig_overrides['crop']!="")?($vsig_overrides['crop']):($this->params->get('th_keep', 'keep'));	//thumbs
					$_imquality_ 	= (array_key_exists("imquality",$vsig_overrides)&&$vsig_overrides['imquality']!="")?($vsig_overrides['imquality']):($this->params->get('im_quality', 90));	//image
					$_quality_ 		= (array_key_exists("quality",$vsig_overrides)&&$vsig_overrides['quality']!="")?($vsig_overrides['quality']):($this->params->get('th_quality', 80));	//thumbs
					$_space_ 			= (array_key_exists("space",$vsig_overrides)&&$vsig_overrides['space']!="")?($vsig_overrides['space']):($this->params->get('th_space', 5));		//thumbs
					$_imwidth_ 		= (array_key_exists("width",$vsig_overrides)&&$vsig_overrides['width']!="")?($vsig_overrides['width']):($this->params->get('im_width', 400));		//image
					$_imheight_ 	= (array_key_exists("height",$vsig_overrides))?($vsig_overrides['height']):($this->params->get('im_height', ''));		//image
					$_im_align_ 	= (array_key_exists("align",$vsig_overrides)&&$vsig_overrides['align']!="")?($vsig_overrides['align']):($this->params->get('im_align', 1));		//image
					$_usescript_ 	= (array_key_exists("script",$vsig_overrides)&&$vsig_overrides['script']!="")?($vsig_overrides['script']):($this->params->get('usescript', 1));		//javascript
					$_hover_ 			= (array_key_exists("hover",$vsig_overrides)&&$vsig_overrides['hover']!="")?($vsig_overrides['hover']):($this->params->get('th_hover', 0));		//javascript
					$_preload_ 		= (array_key_exists("preload",$vsig_overrides)&&$vsig_overrides['preload']!="")?($vsig_overrides['preload']):($this->params->get('preload', 0));		//javascript
					$_tooltip_		= (array_key_exists("tooltip",$vsig_overrides)&&$vsig_overrides['tooltip']!="")?($vsig_overrides['tooltip']):($this->params->get('tooltip', 0));		//tooltips
					$_th_right_ 	= (array_key_exists("right",$vsig_overrides)&&$vsig_overrides['right']!="")?($vsig_overrides['right']):($this->params->get('th_right', 2));		//layout
					$_th_cols_ 		= (array_key_exists("cols",$vsig_overrides)&&$vsig_overrides['cols']!="")?($vsig_overrides['cols']):($this->params->get('th_cols', 1));		//layout
					$_th_sort_		= (array_key_exists("sort",$vsig_overrides)&&$vsig_overrides['sort']!="")?($vsig_overrides['sort']):($this->params->get('th_sort', 0));	//thumbs
					$_setid_ 			= (array_key_exists("folderid",$vsig_overrides)&&$vsig_overrides['folderid']!="")?($vsig_overrides['folderid']):($this->params->get('setid', 0));				//foldername
					$_cap_show_ 	= (array_key_exists("caps",$vsig_overrides)&&$vsig_overrides['caps']!="")?($vsig_overrides['caps']):($this->params->get('cap_show', 1));		//captions
					$_cap_pos_ 		= (array_key_exists("inout",$vsig_overrides)&&$vsig_overrides['inout']!="")?($vsig_overrides['inout']):($this->params->get('cap_pos', 1));			//captions
					$_link_use_ 	= (array_key_exists("links",$vsig_overrides)&&$vsig_overrides['links']!="")?($vsig_overrides['links']):($this->params->get('link_use', 1));		//links
					$_link_orig_ 	= (array_key_exists("autolink",$vsig_overrides)&&$vsig_overrides['autolink']!="")?($vsig_overrides['autolink']):($this->params->get('link_orig', 0));		//links
					$_sets_use_ 	= (array_key_exists("sets",$vsig_overrides)&&$vsig_overrides['sets']!="")?($vsig_overrides['sets']):($this->params->get('sets_use', ''));		//sets
					$_sets_txt_ 	= (array_key_exists("setstxt",$vsig_overrides)&&$vsig_overrides['setstxt']!="")?($vsig_overrides['setstxt']):($this->params->get('sets_txt', 'Set'));		//sets
					$path_ctrls 	= trim($this->params->get('buttonpath', $path_ctrls));
					//controls
					$_ctrl_fwd_ 		= (array_key_exists("cfwd",$vsig_overrides))?($vsig_overrides['cfwd']):(trim($this->params->get('ctrl_fwd', '')));
					$_ctrl_back_ 		= (array_key_exists("cbwd",$vsig_overrides))?($vsig_overrides['cbwd']):(trim($this->params->get('ctrl_back', '')));
					$_ctrl_height_ 	= 24;
					//check if images are used for controls and allow default buttons
					$_ctrl_fwd_type = $_ctrl_back_type = "noimg";
					if(is_file($path_absolute.$path_ctrls.$_ctrl_fwd_)&&((substr(strtolower($_ctrl_fwd_),-3)=='jpg')||(substr(strtolower($_ctrl_fwd_),-3)=='gif')||(substr(strtolower($_ctrl_fwd_),-3)=='png'))){$_ctrl_fwd_=$path_ctrls.$_ctrl_fwd_;$_ctrl_fwd_type="img";}
					elseif($_ctrl_fwd_==""){$_ctrl_fwd_=$path_plugin.'fwd.png';$_ctrl_fwd_type="img";}
					if(is_file($path_absolute.$path_ctrls.$_ctrl_back_)&&((substr(strtolower($_ctrl_back_),-3)=='jpg')||(substr(strtolower($_ctrl_back_),-3)=='gif')||(substr(strtolower($_ctrl_back_),-3)=='png'))){$_ctrl_back_=$path_ctrls.$_ctrl_back_;$_ctrl_back_type="img";}
					elseif($_ctrl_back_==""){$_ctrl_back_=$path_plugin.'bwd.png';$_ctrl_back_type="img";}

					//calculations
					$_im_area_=$_imwidth_-10;
					if($_th_right_=="1"||$_th_right_=="3"){
					$_im_area_=$_imwidth_+10;
						$_th_cols_=(($_im_area_-($_width_+8+$_space_)*$_th_cols_) >=($_width_+10))?($_th_cols_):(1);

						$_im_area_=intval($_imwidth_-($_width_+8+$_space_)*$_th_cols_);
						$_rulerspace_=intval(($_width_+8+$_space_)*$_th_cols_);
						$_rulerspace_.="px";
						$_im_area_=$_im_area_-10;
					}

					//calculate the number of sets
					$_sets_number_=($_sets_use_&&$_sets_use_<=(count($images)+1))?(ceil(count($images)/$_sets_use_)):1;

					//justify
					if($_th_right_=="2"){
						$_im_area_=$_im_area_+10;
						$countthumbs=intval($_im_area_/(($_width_+8+$_space_)));
						$toremove=$_im_area_-($countthumbs*($_width_+8+$_space_));
						$newwidth=$_width_+intval($toremove/$countthumbs);
						$_height_=intval($_height_/$_width_*$newwidth);
					  $_width_=$newwidth;
						$_im_area_=$countthumbs*($_width_+8+$_space_)-$_space_-10;
					}

					//calulate the image proportions
					if(trim($_imheight_)!=""){$therealimageheight=(int)($_imheight_*($_im_area_/$_imwidth_));}
					else{$therealimageheight="full";}

					//create a unique identifier for the current gallery
					$identifier=$this->vsigcounter."_".$sigcount;

					//write the styles for the gallery
					if($_th_right_!="1"&&$_th_right_!="3"){
						$vsig_cssadd.=".vsig_cont".$identifier." {margin:0 ".$_space_."px ".$_space_."px 0 !important;width:".($_width_+8)."px;height:".($_height_+8)."px;}\n";
						//$vsig_cssadd.=".vsig_top".$identifier." {width:".$_im_area_."px;margin:0px 0px 0px 0px;}\n";
						}
					else{
						if($_th_right_=="1"){
						$vsig_cssadd.=".vsig_cont".$identifier." {margin:0 0 ".$_space_."px ".$_space_."px !important;width:".($_width_+8)."px;height:".($_height_+7)."px;}\n";
						$vsig_cssadd.=".vsig_top".$identifier." {width:".$_im_area_."px;float:left;margin:0px 0px 0px 0px;}\n.vsig_ruler".$identifier." {float:right !important;width:".$_rulerspace_.";}\n";
							}
						else{
						$vsig_cssadd.=".vsig_cont".$identifier." {margin:0 ".$_space_."px ".$_space_."px 0 !important;width:".($_width_+8)."px;height:".($_height_+7)."px;}\n";
						$vsig_cssadd.=".vsig_top".$identifier." {width:".$_im_area_."px;float:left;margin:0px 0px 0px 0px;}\n.vsig_ruler".$identifier." {float:left !important;width:".$_rulerspace_.";}\n";
						}
						}
					if($_im_align_==0){$vsig_cssadd.=".vsig".$identifier." {margin:0 0 0 auto;padding:0;display:block;width:".$_imwidth_."px;}\n";}
					elseif($_im_align_==1){$vsig_cssadd.=".vsig".$identifier." {margin:auto;display:block;width:".$_imwidth_."px;}\n";}
					elseif($_im_align_==3){$vsig_cssadd.=".vsig".$identifier." {margin:10px;float:left;width:".$_imwidth_."px;}\n";}
					elseif($_im_align_==4){$vsig_cssadd.=".vsig".$identifier." {margin:10px;float:right;width:".$_imwidth_."px;}\n";}
					else{$vsig_cssadd.=".vsig".$identifier." {width:".$_imwidth_."px;}\n";}
					$vsig_cssadd.=".vsig_ctrls_".$identifier." {display:block;width:".($_im_area_+10)."px;height:".$_ctrl_height_."px;text-align:right;";
					if($_th_right_==3){
						$vsig_cssadd.="float:right !important;";
						} else {
						$vsig_cssadd.="float:left !important;";
					}
					$vsig_cssadd.="}\n";

					//manipulate uri
					$aktimg=0;
					$target[$identifier]=htmlspecialchars($_target);
					$foundrequest=JRequest::getVar('vsig'.$identifier, 'FALSE');
					if($foundrequest!='FALSE'){
						$aktimg=(int)$foundrequest;
						//if we find on of our request-params a potential random sort is changed to the default sort
						if($_th_sort_==4){$_th_sort_=0;}
						$target[$identifier] = preg_replace('@[&|&amp;]?vsig'.$identifier.'=[0-9]+@', '', $target[$identifier] );
						$target[$identifier] = @preg_replace('@(\?&amp;)@', '?', $target[$identifier] );
						}
					$vsig_adqm = strpos($target[$identifier], '?');
					$target[$identifier].=($vsig_adqm===false)?("?"):("");
					$target[$identifier].=(substr($target[$identifier], -1)!="?")?("&amp;"):("");    
					$target[$identifier] = @preg_replace('@(&amp&amp;)@', '&amp;', $target[$identifier] );

					//current set according to main image else 1
					$_sets_current_=($_sets_use_)?(intval($aktimg/$_sets_use_)+1):1;

					//sort images
					$images = plgContentVerysimpleimagegalleryHelper::beSortImages($images,$_th_sort_);

					//set path of thumbnail directory
					$thumbdir=$path_absolute.$path_imgroot.$_images_dir_.$folder_thumbs.'/';
					//check_existence_of/create thumbdirectory
					if(!is_dir($thumbdir)){plgContentVerysimpleimagegalleryHelper::beMakeFolder($thumbdir,'thumbnail');}

					//set path of image directory
					$imgdir=$path_absolute.$path_imgroot.$_images_dir_.$folder_images.'/';
					//check_existence_of/create imagedirectory
					if(!is_dir($imgdir)){plgContentVerysimpleimagegalleryHelper::beMakeFolder($imgdir,'image');}

					//set the var for the current array of captions
					$captions="cap_ar".$sigcount;
					//set the var for the current array of links
					$vsiglinks="_linkar".$sigcount;

					//start collecting html and set folder identity - if set to do so
					if($_setid_){
						$_tempstring=explode("/",$_images_dir_);
						$_tempstring=$_tempstring[count($_tempstring)-2];
						$html1 = "\n<a id='g_vsig".$identifier."'></a><div class='vsig vsig".$identifier."' id='".$_tempstring."'>";
					}else{
						$html1 = "\n<a id='g_vsig".$identifier."'></a><div class='vsig vsig".$identifier."'>";
					}

					//start collecting html for main-image-part
					$html2='';
					//start collecting html for thumbs-part
					$html3='<div>';

					//create current javascript array
					if($_usescript_){
						$vsig_script.="var vsig_".$identifier."= new Array();\n";
						$vsig_script.="var vsig_".$identifier."_b=new Array('".$path_site.$path_imgroot.$_images_dir_."','".$folder_thumbs."/','".$identifier."','".$target[$identifier]."vsig".$identifier."=');\n";
						}
					//set counter for thumbs in set to 1
					$thumbs_in_set=1;
					//traverse the found images#######################
					for($a = 0;$a<$noimage;$a++) {
						if($images[$a]['filename'] != '') {

							//prepare captions############################
							$cur_cap=array("",""); //reset captions
							$cur_cap_set=0; //set trigger for captions
							if($_cap_show_&&isset($$captions)){ //are captions activated and are there captions?
								if(array_key_exists(plgContentVerysimpleimagegalleryHelper::beStrtolower($images[$a]['filename']),$$captions)){ //check for specific caption
									$cur_cap=array(${$captions}[plgContentVerysimpleimagegalleryHelper::beStrtolower($images[$a]['filename'])][0],${$captions}[plgContentVerysimpleimagegalleryHelper::beStrtolower($images[$a]['filename'])][1]);
									$cur_cap_set=1;
								}
								elseif(array_key_exists("CAPDEFAULT",$$captions)){ //check for default caption
									$cur_cap=array(${$captions}["CAPDEFAULT"][0],${$captions}["CAPDEFAULT"][1]);
									$cur_cap_set=1;
								}
							}
							//create values for js
							$cur_cap_js=$cur_cap;
							//encode captions
							$cur_cap_js[0] = plgContentVerysimpleimagegalleryHelper::beKickQuotes($cur_cap_js[0]);
							$cur_cap_js[1] = plgContentVerysimpleimagegalleryHelper::beKickQuotes($cur_cap_js[1]);

							//prepare links###############################
							$cur_link=array("#g_vsig".$identifier,"","_self"); //reset links
							//check for autolinks
							if($_link_orig_){
								$setlinks="_linkar".$sigcount;
								if(!isset($$setlinks)){$$setlinks=array();}
								$_link1=$images[$a]['filename'];
								$_link2=$path_site.$path_imgroot.$_images_dir_.$images[$a]['filename'];
								$_link3=$images[$a]['filename'];
								${$setlinks}[$_link1]=array($_link2,$_link3,"_blank");
							}

							if($_link_use_&&isset($$vsiglinks)){ //are links activated and are there links?
								if(array_key_exists(plgContentVerysimpleimagegalleryHelper::beStrtolower($images[$a]['filename']),$$vsiglinks)){ //check for specific links
									$cur_link=${$vsiglinks}[plgContentVerysimpleimagegalleryHelper::beStrtolower($images[$a]['filename'])];
								}
								elseif(array_key_exists("LINKDEFAULT",$$vsiglinks)){ //check for default link
									$cur_link=${$vsiglinks}["LINKDEFAULT"];
								}
							}
							//create values for js
							$cur_link_js=$cur_link;							
							//encode links
							$cur_link[1] = htmlspecialchars($cur_link_js[1], ENT_QUOTES);
							$cur_link_js[1] = plgContentVerysimpleimagegalleryHelper::beKickQuotes($cur_link_js[1]);

							//prepare alt and title#######################
							$cur_alt=($_tooltip_>=1)?(substr($images[$a]['filename'], 0, -4)):(""); //reset alt-text and title
							//create values for js
							$cur_alt_js=$cur_alt;
							//encode alt-title from image name
							$cur_alt = utf8_encode($cur_alt);
							$cur_alt_js = $cur_alt;
							//check captions to use instead of file name
							if(isset($$captions)){ //are there captions?
								if(array_key_exists(plgContentVerysimpleimagegalleryHelper::beStrtolower($images[$a]['filename']),$$captions)){ //check for specific caption
									$cur_alt=${$captions}[plgContentVerysimpleimagegalleryHelper::beStrtolower($images[$a]['filename'])][0];
								}
								elseif(array_key_exists("CAPDEFAULT",$$captions)){ //check for default caption
									$cur_alt=${$captions}["CAPDEFAULT"][0];
								}
							//create values for js
							$cur_alt_js=$cur_alt;
							//encode alt-title from captions
							}
							//if a specific link is set, the title overrides all
							if($_link_use_&&isset($$vsiglinks)&&array_key_exists(plgContentVerysimpleimagegalleryHelper::beStrtolower($images[$a]['filename']),$$vsiglinks)&&${$vsiglinks}[plgContentVerysimpleimagegalleryHelper::beStrtolower($images[$a]['filename'])][1]!=""){
								$cur_alt=${$vsiglinks}[plgContentVerysimpleimagegalleryHelper::beStrtolower($images[$a]['filename'])][1];
							//encode alt-title from links
							//$cur_alt=$cur_alt;
							$cur_alt_js=$cur_alt;
							}
							//encode alt-title
							$cur_alt_js = plgContentVerysimpleimagegalleryHelper::beKickQuotes($cur_alt_js);
							$cur_alt = plgContentVerysimpleimagegalleryHelper::beKickQuotes($cur_alt);

							//check_existence_of/create img
							//$theimage = plgContentVerysimpleimagegalleryHelper::beResizeImg($path_absolute.$path_imgroot.$_images_dir_.$images[$a]['filename'],$folder_images,$_im_area_,$therealimageheight,'keep','no',$_imquality_);

							// echo "<pre>";
							// echo $images[$a]['filename'];
							// print_r($theimage);
							// echo "</pre>";
							
							$theimage[1]= $images[$a]['filename'];
							//prepare and encode webpath main image#######
							$cur_webpath=$path_site.$path_imgroot.$_images_dir_.$folder_images."/".$theimage[1];

							//top image###################################
							if($images[$a]['filename']==$images[$aktimg]['filename']) {
								$html2 .= "\n<div class='vsig_top vsig_top".$identifier."'>";
								if($_link_use_&&isset($$vsiglinks)){ //links are activated and set
								$html2 .= "\n<a href='".$cur_link[0]."' title='".$cur_link[1]."' target='".$cur_link[2]."'>";
								}
								$html2 .= "\n<img id='topimg".$identifier."' data-thumbid='0' src='".$cur_webpath."' title='".$cur_alt."' alt='".$cur_alt."'/>";
								if($_link_use_&&isset($$vsiglinks)){ //links are activated and set
								$html2 .= "\n</a>";
								}
								if($_cap_show_&&isset($$captions)){ //we have to show captions
								$html2 .= "\n<div class='".(($_cap_pos_)?'inside':'outside')."' style='width:".$_im_area_."px;'>";
									if($cur_cap_set){ //captions (specific or default) are set for this image
								$cur_cap[1] = preg_replace('/[\$]/', '$', $cur_cap[1]);//prevent the $ from getting executed as a variable;
								$html2 .= "<span>".$cur_cap[0]."</span><span>".$cur_cap[1]."</span>";
									}
								$html2 .= "\n</div>".(($_cap_pos_)?"":"<br class='vsig_clr' />");
								}
								$html2 .= "\n</div>\n";
							}

							//thumbnails##################################
							//check_existence_of/create thumb
							$thethumb = plgContentVerysimpleimagegalleryHelper::beResizeImg($path_absolute.$path_imgroot.$_images_dir_.$images[$a]['filename'],$folder_thumbs,$_width_,$_height_,$_keep_,'no',$_quality_);

							//prepare and encode webpath thumbs#######
							$cur_thumb_webpath=$path_site.$path_imgroot.$_images_dir_.$folder_thumbs.'/'.$thethumb[1];

							//check if sets are used and if the current thumb belongs to the current set
							if(!$_sets_use_||($a>=($_sets_use_*$_sets_current_-$_sets_use_)&&$a<=($_sets_use_*$_sets_current_-1)&&$_sets_use_>=2)){
								//write html for thumbs
								$html3 .= '<div id="thbvsig_'.$identifier.'_'.$thumbs_in_set.'" class="vsig_cont vsig_cont'.$identifier.'"><div class="vsig_thumb">';
								$html3 .= '<a href="'.$target[$identifier].'vsig'.$identifier.'='.$a.'" rel="nofollow"';
								if($_usescript_>=1){ //javascript is activated, insert the onclick event
								$html3 .= ' onclick=\'switchimg("vsig_'.$identifier.'",'.$a.');return false;\'';
									if($_hover_>=1){ //change main image on thumbhover too
									$html3 .= ' onmouseover=\'switchimg("vsig_'.$identifier.'",'.$a.');return false;\'';
									}
								}
								$html3 .= ' title="'.$cur_alt.'">';
								$html3 .= '<img src="'.$cur_thumb_webpath.'" alt="'.$cur_alt.'"/>';
								$html3 .= "</a></div></div>\n";
								//increment counter for thumbs in set
								$thumbs_in_set++;
							}
							//array for javascript; name: identifier+prefix
							if($_usescript_){
							$vsig_script.="vsig_".$identifier."[".$a."]=new Array(";	
							$vsig_script.="'".$theimage[1]."',";		//0-image-url
							$vsig_script.="'".$cur_cap_js[0]."',";	//1-captitle
							$vsig_script.="'".$cur_cap_js[1]."',";	//2-captext
							$vsig_script.="'".$cur_link_js[0]."',";	//3-linkhref
							$vsig_script.="'".$cur_link_js[1]."',";	//4-linktitle
							$vsig_script.="'".$cur_link_js[2]."',";	//5-linktarget
							$vsig_script.="'".$cur_alt_js."',";			//6-alt-txt
							$vsig_script.="'".$thethumb[1]."');\n";	//7-thumb-url
							}
						}
					}

					//if the last set contains less thumbs than a full set, add dummies
					if($_sets_use_&&count($images)>=($_sets_use_+1)&&$_sets_use_>=2){
						for($b=$thumbs_in_set;$b<=$_sets_use_;$b++){
							$html3 .= '<div id="thbvsig_'.$identifier.'_'.$thumbs_in_set.'" class="vsig_cont vsig_cont'.$identifier.'" style="visibility:hidden;"><div class="vsig_thumb">';
							$html3 .= '<a href="" rel="nofollow"';
							if($_usescript_>=1){ //javascript is activated, insert the onclick event
							$html3 .= ' onclick=\'return false;\'';
								if($_hover_>=1){ //change main image on thumbhover too
								$html3 .= ' onmouseover=\'\'';
								}
							}
							$html3 .= ' title=""><img src=""/>';
							$html3 .= "</a></div></div>\n";
							//increment counter for thumbs in set
							$thumbs_in_set++;
						}
					}
					$html3.='</div>'; 
					//add controls
					$html4="<div class='controls'>";
					if($_sets_use_&&$_sets_number_>=2) {
						$html4.="<div class='pagination'>";
						$html4.="<ul class='pagination-list'>";
						//back
						if($_sets_current_>=2){
							$html4.= '<li class="pagination-prev">';
							$html4.= '<a href="'.$target[$identifier].'vsig'.$identifier.'='.(($_sets_current_-1)*$_sets_use_-$_sets_use_).'" rel="nofollow"';
							if($_usescript_>=1){ //javascript is activated, insert the onclick event
							$html4.= ' id=\'bbackvsig_'.$identifier.'\'';
							$html4.= ' onclick=\'switchset("vsig_'.$identifier.'",'.(($_sets_current_-1)*$_sets_use_-$_sets_use_).','.$_sets_use_.');return false;\'';
							}
							$html4.= '>Prev';
						}
						else{
							$html4.= '<li class="pagination-prev">';
							$html4.= '<a href="'.$target[$identifier].'vsig'.$identifier.'='.(($_sets_number_-1)*$_sets_use_).'" rel="nofollow"';
							if($_usescript_>=1){ //javascript is activated, insert the onclick event
							$html4.= ' id=\'bbackvsig_'.$identifier.'\'';
							$html4.= ' onclick=\'switchset("vsig_'.$identifier.'",'.(($_sets_number_-1)*$_sets_use_).','.$_sets_use_.');return false;\'';
							}
							$html4.= '>';
						}
						// if($_ctrl_back_type=="img"){$html4.="<img src='".$path_site.$_ctrl_back_."' alt='".substr($_ctrl_back_, (strrpos($_ctrl_back_, "/")+1), -4)."'/>";}
						// else{$html4.=$_ctrl_back_;}
						$html4.="Prev";
						$html4.="</a></li>";
						//counter
						$html4.= '<li class="">';
//						$html4.="&nbsp;&nbsp;".$_sets_txt_."<span id='countervsig_".$identifier."' class='vsig_counter'>&nbsp;".$_sets_current_."/".$_sets_number_."</span>&nbsp;&nbsp;";
						$html4.="<span id='countervsig_".$identifier."' class='vsig_counter'>&nbsp;".$_sets_current_."/".$_sets_number_."</span>";
						//$html4.= '</il>';
						//forward
						if($_sets_current_<=$_sets_number_-1){
							$html4.= '<li class="pagination-next">';
							$html4.= '<a href="'.$target[$identifier].'vsig'.$identifier.'='.($_sets_current_*$_sets_use_).'" rel="nofollow"';
							if($_usescript_>=1){ //javascript is activated, insert the onclick event
							$html4.= ' id=\'bfwdvsig_'.$identifier.'\'';
							$html4.= ' onclick=\'switchset("vsig_'.$identifier.'",'.($_sets_current_*$_sets_use_).','.$_sets_use_.');return false;\'';
							}
							$html4.= '>';
						}
						else{
							$html4.= '<li class="pagination-prev">';
							$html4.= '<a href="'.$target[$identifier].'vsig'.$identifier.'=0" rel="nofollow"';
							if($_usescript_>=1){ //javascript is activated, insert the onclick event
							$html4.= ' id=\'bfwdvsig_'.$identifier.'\'';
							$html4.= ' onclick=\'switchset("vsig_'.$identifier.'",0,'.$_sets_use_.');return false;\'';
							}
							$html4.= '>';
						}
						// if($_ctrl_fwd_type=="img"){$html4.="<img src='".$path_site.$_ctrl_fwd_."' alt='".substr($_ctrl_fwd_, (strrpos($_ctrl_fwd_, "/")+1), -4)."'/>";}
						// else{$html4.=$_ctrl_fwd_;}
						$html4.="Next";
						$html4.="</a></li>";
					}
					$html4.='</ul>';
					
					$html4.='<ul>';
					$html4.='<li class="prev-img"><a onclick="change_img(-1, \'vsig_'.$identifier.'\'); return false;" rel="nofollow" href="">Prev Frame</a></li>';
					$html4.='<li><a onclick="play(-1, 0, \'vsig_'.$identifier.'\'); return false;"  rel="nofollow" href="">Rewind</a></li>';
					$html4.='<li class="stop" id="stop"><a onclick="stop_img(1, \'vsig_'.$identifier.'\'); return false;"  rel="nofollow" href="">Stop</a></li>';
					$html4.='<li class="play" id="play"><a onclick="play(1, 0, \'vsig_'.$identifier.'\'); return false;"  rel="nofollow" href="">Play</a></li>';
					$html4.='<li class="next-img"><a onclick="change_img(1, \'vsig_'.$identifier.'\'); return false;" rel="nofollow" href="">Next Frame</a></li>';
					$html4.='</ul>';

					$html4.='<ul>';
					$html4.='<li><a onclick="play( 0, -1, \'vsig_'.$identifier.'\'); return false;"  rel="nofollow" href="">Speed /2</a></li>';
					$html4.='<li><a onclick="play( 0, 1, \'vsig_'.$identifier.'\'); return false;"  rel="nofollow" href="">Speed x2</a></li>';
					$html4.='</ul>';
					
					$html4.="</div></div>\n";

					//combine top image, controls and thumbs####################
					if($_sets_use_&&$_sets_number_>=2) {
						if($_th_right_=="1"){ //if thumbs are right PLUS sets are used => topimage-ruler-thumbs-controls
							$html1 .=$html2;
							$html1 .="<div class='vsig_ruler".$identifier." vsig_ruler'>\n";
							$html1 .=$html3;
							$html1 .="</div>\n";
							$html1 .=$html4;
						}
						elseif($_th_right_=="3"){//if thumbs are left PLUS sets are used => topimage-ruler-thumbs-controls
							$html1 .="<div class='vsig_ruler".$identifier." vsig_ruler'>\n";
							$html1 .=$html3;
							$html1 .="</div>\n";
							$html1 .=$html2;
							$html1 .=$html4;
						}
						else{ //if thumbs are below PLUS sets are used => topimage-controls-thumbs
							$html1 .=$html2;
							$html1 .=$html4;
							$html1 .=$html3;
						}
					}
					else{
						if($_th_right_=="1"){ //if thumbs are right NO sets are used => topimage-ruler-thumbs
							$html1 .=$html2;
							$html1 .="<div class='vsig_ruler".$identifier." vsig_ruler'>\n";
							$html1 .=$html3;
							$html1 .="</div>\n";
						}
						elseif($_th_right_=="3"){ //if thumbs are left NO sets are used => ruler-thumbs-topimage
							$html1 .="<div class='vsig_ruler".$identifier." vsig_ruler'>\n";
							$html1 .=$html3;
							$html1 .="</div>\n";
							$html1 .=$html2;
						}
						else{ //if thumbs are below NO sets are used => topimage-thumbs
							$html1 .=$html2;
							$html1 .=$html3;
						}
					}

					$html1 .="<div class=\"vsig_clr\"></div>\n</div>\n";
					if($_usescript_==1){ //javascript is activated, link the js-file
						$document->addScript($path_site.$path_plugin.'vsig.js');
					}
					if($_preload_){
						$vsig_script.="var vsig_".$identifier."_preload = vsig_daisychain('vsig_".$identifier."','vsig_".$identifier."_b');\n";
					}
				}

				//replace the call with the gallery#################
				$article->text = plgContentVerysimpleimagegalleryHelper::beReplaceCall("{vsig}".$vsig_code."{/vsig}",$html1, $article->text);
			}
			//add arrays with alt, caption and link
			if($vsig_script!=""){
				$document->addScriptDeclaration($vsig_script);
			}
			//write collected CSS setting to the head of the page
			if($vsig_cssadd!=""){
			$document->addCustomTag("<style type='text/css'>\n".$vsig_cssadd."</style>\n");
			//remove duplicate links to stylesheet - start
			$headerstuff = $document->getHeadData();
			foreach($headerstuff['custom'] as $key => $custom){
				if(stristr($custom, 'vsig.css') !== false){
					unset($headerstuff['custom'][$key]);
				}
			}
      $document->setHeadData($headerstuff);
			//remove duplicate links to stylesheet - end
			$document->addCustomTag('<link rel="stylesheet" href="'.$path_site.$path_plugin.'vsig.css" type="text/css" />' );
			}
		}
//images
	}

public function onContentAfterDisplay($context, &$article, &$params, $limitstart=0) {
	if(preg_match('#vsig[0-9]+_#s', $_SERVER['REQUEST_URI'])>=1){
		// checking
		$document=JFactory::getDocument();   
		if($document->getType() != 'html') {
			return;
		}
		$document->setMetaData("robots", "noindex, nofollow");
		}
		if(isset($article->id)){$this->myarticleid=$article->id;} //substitute article id on frontpage
	}

}
?>