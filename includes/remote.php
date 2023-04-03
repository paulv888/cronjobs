<?php
function loadRemote($remoteID, $apikey = null) {

	if (isset($_SESSION) && array_key_exists('properties', $_SESSION) && array_key_exists('SelectedPlayer', $_SESSION['properties']) ) {
		$params['SESSION']['properties']['SelectedPlayer'] = $_SESSION['properties']['SelectedPlayer'];
	} else {
		$params['SESSION']['properties']['SelectedPlayer']['value'] = getCurrentPlayer();
	}

    $select = (substr  ($_SERVER['REMOTE_ADDR'],0,9) == '192.168.2' ? 3 : 2);
    $mysql = 'SELECT a.remoteID, a.divID, b.* FROM ha_remote_divs_cross a LEFT JOIN ha_remote_divs b ON a.divID = b.id WHERE b.showonremote != "0" AND b.showonremote < "'.$select.'" AND a.remoteID = '.$remoteID.' ORDER BY sort';
	if ($divs = FetchRows($mysql)) {
		if (count($divs) > 1) {
			echo '<div class="bs-example bs-example-tabs">';
			echo '<ul id="myTab" class="nav nav-tabs nav-dark nav-remote">';
			foreach ($divs as $key => $rowdivs) {
				echo '<li class="';
				if ($key==0) echo 'active'; 
				echo '" '.'style="width:'.(100/count($divs)).'%;"><a href="#divid_'.$rowdivs['id'].'"  data-toggle="tab"';
				echo '>';
				$text = $rowdivs['name'];
				$booticon = $rowdivs['booticon'];
				if ($booticon != null) {								// if icon then do icon <i>
					echo '<i class="btn-icon '.$booticon;
					if ($text != null) echo ' '.'';
					echo '">';
					echo '</i>';
				} 
				if ($text != null) echo '<div>'.$text.'</div>';
				echo '</a>';
				echo '</li>';
			}
			echo '</ul>';
			echo '<div id="myTabContent" class="tab-content">';
		}
		$params['apikey'] = $apikey;
		loadRemotePaneContent($remoteID, $select, $params);
		if (count($divs) > 1) echo '</div></div>';
		echo '<div id="spinner">Executing...</div>';
	}
}

function loadRemotePaneContent($remoteID, $select, $params) {


    $mysql = 'SELECT a.remoteID, a.divID, b.* FROM ha_remote_divs_cross a LEFT JOIN ha_remote_divs b ON a.divID = b.id WHERE b.showonremote != "0" AND b.showonremote < "'.$select.'" AND a.remoteID = '.$remoteID.' ORDER BY sort';
    $mycount=1;
	if ($rows = FetchRows($mysql)) {
		foreach ($rows as $rowdivs) {
			if ($mycount==1) {
				echo '<div class="tab-pane active in" id="divid_'.$rowdivs['id'].'">';
			} else {
				echo '<div class="tab-pane" id="divid_'.$rowdivs['id'].'">';
			}
			$mycount=2;
			loadRemoteDiv($rowdivs['divID'], $params);
			echo "</div>";
		}
	}
}

function loadRemoteDiv($divid, $params) {

	$mysql = "SELECT MAX(xpos) as maxx, MAX(ypos) as maxy FROM ha_remote_keys WHERE remotediv =".$divid;
	$rowremotekeys = FetchRow($mysql);
	$myxmax = $rowremotekeys['maxx'];
	$myymax = $rowremotekeys['maxy'];
	$tdwidth = floor(100/$myxmax);
	echo '<table class="table table-rem-condensed table">';
	for ($myycell = 1; $myycell <= $myymax; $myycell++) {
		echo '<tr class="keysrow">';
		for ($myxcell = 1; $myxcell <= $myxmax; $myxcell++) {
			$mysql = ('SELECT r.*, d.id as d__deviceID , t.booticon as type___booticon
			           FROM ha_remote_keys r 
			           LEFT JOIN ha_mf_devices d ON r.deviceID = d.id 
					   LEFT JOIN ha_mf_device_types t ON d.typeID = t.id 
					   WHERE remotediv ='.$divid.' AND xpos ='.$myxcell.' AND ypos ='.$myycell.' AND (inuse = 1 OR inuse IS NULL)  ORDER BY remotediv DESC');
			$rowremotekeys = FetchRow($mysql);
			if ($rowremotekeys) {
				$status = '';
				$link = '';
				$booticon = null;
				$rowremotekeys['inputoptions'] = str_replace('_apikey_', $params['apikey'], $rowremotekeys['inputoptions']);
				$class = $rowremotekeys['class'];
				($cellid = strlen($rowremotekeys['cellid']) > 0 ? $rowremotekeys['cellid'] : "");
				if (!empty($rowremotekeys['deviceID'])) {
					$deviceID = ($rowremotekeys['deviceID'] == DEVICE_SELECTED_PLAYER ? $params['SESSION']['properties']['SelectedPlayer']['value'] : $rowremotekeys['deviceID']);
					if  (!empty($deviceID)) {
						$statuslink = array();
						if (array_key_exists('Status',$statuslink)) {
							$status = ($statuslink['Status'] == STATUS_ON ? 'on' : 
									($statuslink['Status'] == STATUS_OFF ? 'off' : 
									($statuslink['Status'] == STATUS_UNKNOWN ? 'unknown' : 
									($statuslink['Status'] == STATUS_ERROR ? 'error' : 
									  'undefined'))));
						}
						if (array_key_exists('Link',$statuslink)) {
							$link = ($statuslink['Link'] == LINK_UP ? '' : ($statuslink['Link'] == LINK_WARNING ? 'link-warning' : 'link-down'));
						}
					} else {
						echo '<td style="width:'.$tdwidth.'%" class="keyscellempty">'.'</td>';
						continue;
					}
				}
				echo '<td class="keyscell"';
				if ($rowremotekeys['hspan']>0) {
					$tdwidthspan=$tdwidth*($rowremotekeys['hspan']);
					echo ' style="width:'.$tdwidthspan.'%"';   
					$myxcell+=$rowremotekeys['hspan']-1;
					echo ' colspan="'.$rowremotekeys['hspan'].'"';
				} else {
					echo ' style="width:'.$tdwidth.'%"';   
				}
				if ($rowremotekeys['vspan']>0) {
					echo ' rowspan="'.$rowremotekeys['vspan'].'"';   
					if ($myymax < $myycell + $rowremotekeys['vspan']) $myymax = $myycell + $rowremotekeys['vspan'];
				}
				echo ">";
				if ($rowremotekeys['repeat_time'] == 0) {
					$clicks = (is_null($rowremotekeys['commandIDdown'])  ? "click-up rem-button" : "click-down rem-button");
				} else {
					$clicks = "repeat-click-down rem-button";
				}
				if ($rowremotekeys['inputtype']=="display") {
						$fieldtype = "div";
						$fieldclass = $rowremotekeys['inputtype'];
				}
				if ($rowremotekeys['inputtype']=="button" || $rowremotekeys['inputtype']=="link") { 
						$fieldtype = "button";
						$fieldclass = "btn btn-block button ";
						if ($rowremotekeys['inputtype']=="button") $fieldclass .= $clicks;
				} 
				if  ($rowremotekeys['type_image'] == 1 || $rowremotekeys['type_image'] == 2) {
					if ($rowremotekeys['booticon'] != null) {
						$booticon = $rowremotekeys['booticon'];
					} else {
						$booticon = $rowremotekeys['type___booticon'];
					}
				}
				if ($rowremotekeys['inputtype']=="button" || $rowremotekeys['inputtype']=="display" || $rowremotekeys['inputtype']=="link") {
					$text = getDisplayText($rowremotekeys);
					echo '<'.$fieldtype.' class="'.$fieldclass;
					if (strlen($status)>1) echo ' '.$status;
					if (strlen($link)>1) echo ' '.$link;
					if (strlen($class)>1) echo ' '.$class;
					echo '"';
					if (strlen($cellid)>1) echo ' id="'.$cellid.'"';
					echo ' data-remotekey="'.$rowremotekeys['id'].'"';
					echo ' title="'.$rowremotekeys['name'].'"';
					if ($rowremotekeys['repeat_time'] != 0) echo ' data-repeat-time="'.$rowremotekeys['repeat_time'].'"';
					echo '>';
					if ($booticon != null) {								// if icon then do icon <i>
						echo '<i class="btn-icon '.$booticon;
						if ($text != null) echo ' '.'rem-icon-left';
						echo '">';
						echo '</i>';
					} 
					if (isset($deviceID))$text = replacePropertyPlaceholders($text, Array('deviceID' => $deviceID));
					//<a href="/index.php/outside" class="btn btn-block button">Outside</a>
					if ($rowremotekeys['inputtype']=="link") $text = '<a target="_blank" href="'.$rowremotekeys['inputoptions'].'" class="buttontext">'.$rowremotekeys['name'].'</a>';
					if ($text != null) 	echo $text;
					//echo '<span class="buttontext">'.$text.'</span>';
					echo '</'.$fieldtype.'>';
					echo "</td>";
				} else {
					if ($rowremotekeys['inputtype']=="btndropdown") {
						echo '<div style="position: relative;height:100%">';
						echo '<button class="btn btn-block dropdown-toggle rem-button';
						if (strlen($status)>1) echo ' '.$status;
						if (strlen($link)>1) echo ' '.$link;
						if (strlen($class)>1) echo ' '.$class;
						echo '"';
						echo ' data-remotekey="'.$rowremotekeys['id'].'"';
						echo ' type="button" data-toggle="dropdown">';
//							echo '<div>'.$rowremotekeys['name'].'</div> </button>';
//							echo '<i class="btn-icon icon-arrow-down-3 rem-icon-left"></i>';
						if ($booticon != null) {								// if icon then do icon <i>
							echo '<i class="btn-icon '.$booticon;
							echo ' '.'rem-icon-left';
							echo '">';
							echo '</i>';
						}
						$text = getDisplayText($rowremotekeys);
						echo '<span class="buttontext">'.$text.'</span><span> </span><span class="caret"></span></button>';

						$options = explode(";",$rowremotekeys['inputoptions']);
						$option = explode(",",$options[0]);
						echo '<ul class="dropdown-menu btndropdown ';
						// if (strlen($class)>1) echo ' '.$class;
						echo '" role="menu" data-myvalue="'.$option[0].'"'; 			// properly set default to first
						echo ' data-remotekey="'.$rowremotekeys['id'].'"';
						if (strlen($cellid)>1) echo ' id="'.$cellid.'"';
						echo '>';
						//$first= true;
						foreach ($options as $optionstring) {
							$option = explode(",",$optionstring);
							if ($option[0] == '-') {
								echo '<li class="divider"></li>';
							} else {
								echo '<li><a href=# data-value="'.$option[0].'">'.$option[1].'</a></li>';
							}
						}
						echo '</ul></div>';
						echo '</td>';
					}
					if ($rowremotekeys['inputtype']=="dropdown") {
						echo '<form class="formdropdown" method="get" data-remotekey="'.$rowremotekeys['id'].'">';
						echo '<select';
						if (strlen($cellid)>1) echo ' id='.$cellid;
						echo ' class="controlselect"';
						echo ' data-remotekey="'.$rowremotekeys['id'].'"';
						echo '>';
						$first= true;
						$options = explode(";",$rowremotekeys['inputoptions']);
						foreach ($options as $optionstring) {
							$option = explode(",",$optionstring);
							if ($first) { 
								echo '<option selected="selected" value="'.$option[0].'">'.$option[1].'</option>';
								$first=FALSE;
							} else {
								echo '<option value="'.$option[0].'">'.$option[1].'</option>';
							}
						}
						echo '</select>';
						echo '</form>';
						echo '</td>';
					}
					if ($rowremotekeys['inputtype']=="dropdownlist") {
						echo '<form class="formdropdownlist" method="get" data-remotekey="'.$rowremotekeys['id'].'">';
						echo '<select';
						if (strlen($cellid)>1) echo ' id='.$cellid;
						echo ' class="controlselect-button"';
						echo ' data-remotekey="'.$rowremotekeys['id'].'"';
						echo '>';
						$options = explode(";",$rowremotekeys['inputoptions']);
						foreach ($options as $optionstring) {
							$option = explode(",",$optionstring);
							echo '<option value="'.$option[0].'">'.$option[1].'</option>';
						}
						echo '</select>';
						echo '<button type="submit" class="btn btn-block button jump-button';
						if (strlen($class)>1) echo ' '.$class;
						echo '"';
						echo ' data-remotekey="'.$rowremotekeys['id'].'">';
						if (strlen($rowremotekeys['booticon'])>0) {
							echo '<i class="'.$rowremotekeys['booticon'];
							echo '"></i>';
						}
						if  (strlen($rowremotekeys['booticon'])>0) echo ' ';
						echo $rowremotekeys['name']; 
						echo '</button>';
						echo '</form>';
						echo '</td>';
					}
				}
			}
			else {
				echo '<td style="width:'.$tdwidth.'%" class="keyscellempty">'.'</td>';
			}
		}
	echo "</tr>";
	}
echo "</table>";
}

function getDisplayText($row) {
	$text = "";
	if ($row['type_image'] == 0 || $row['type_image'] == 2) {
		if (!empty($row['inputoptions'])) {
			if (substr($row['inputoptions'], 0, 2) == "@@") {
				$fname = substr($row['inputoptions'], 2);
				$text = fname($params);
			} else {
				$text = $row['inputoptions'];
			}
		} else {
			$text = $row['name'];
		}
		if ($row['inputtype']=="btndropdown") $text = $row['name'];
	}
	$text = rtrim($text);
	return $text;
}
?>
