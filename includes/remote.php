<?php
function loadremote($remoteID) {

	if (isset($_SESSION) && array_key_exists('properties', $_SESSION) && array_key_exists('SelectedPlayer', $_SESSION['properties']) ) {
		$params['SESSION']['properties']['SelectedPlayer'] = $_SESSION['properties']['SelectedPlayer'];
	} else {
		$params['SESSION']['properties']['SelectedPlayer']['value'] = DEVICE_DEFAULT_PLAYER;
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
		loadRemotePaneContent($remoteID, $select, $params);
		if (count($divs) > 1) echo '</div></div>';
		echo '<div id="spinner">Executing...</div>';
	}
}

function loadRemotePaneContent($remoteID, $select, $params) {

    
    $mysql = 'SELECT a.remoteID, a.divID, b.* FROM ha_remote_divs_cross a LEFT JOIN ha_remote_divs b ON a.divID = b.id WHERE b.showonremote != "0" AND b.showonremote < "'.$select.'" AND a.remoteID = '.$remoteID.' ORDER BY sort';
    $resdivs = mysql_query($mysql);
    $mycount=1;
    while ($rowdivs = mysql_fetch_array($resdivs)) {
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

function loadRemoteDiv($divid, $params) {

	$resremotekeys = mysql_query("SELECT MAX(xpos) as maxx, MAX(ypos) as maxy FROM ha_remote_keys WHERE remotediv =".$divid );
	$rowremotekeys = mysql_fetch_array($resremotekeys);
	$myxmax = $rowremotekeys['maxx'];
	$myymax = $rowremotekeys['maxy'];
	$tdwidth = floor(100/$myxmax);
	echo '<table class="table table-rem-condensed table">';
	for ($myycell = 1; $myycell <= $myymax; $myycell++) {
		echo '<tr class="keysrow">';
		for ($myxcell = 1; $myxcell <= $myxmax; $myxcell++) {
			$resremotekeys = mysql_query("SELECT * FROM ha_remote_keys where remotediv =".$divid." AND xpos =".$myxcell." AND ypos =".$myycell." ORDER BY remotediv DESC");
			$rowremotekeys = mysql_fetch_array($resremotekeys);
			if ($rowremotekeys) {
				$status = '';
				$link = '';
				$booticon = null;
				$class = $rowremotekeys['class'];
				($cellid = strlen($rowremotekeys['cellid']) > 0 ? $rowremotekeys['cellid'] : "");
				if (strlen($rowremotekeys['deviceID'])>0) {
					$deviceID = ($rowremotekeys['deviceID'] == DEVICE_CURRENT_SESSION ? $params['SESSION']['properties']['SelectedPlayer']['value'] : $rowremotekeys['deviceID']);
					$mysql = 'SELECT ha_mf_devices.id, ha_mf_device_types.id, inuse, booticon FROM ha_mf_devices ' .
							' LEFT JOIN ha_mf_device_types ON ha_mf_devices.typeID = ha_mf_device_types.id WHERE ha_mf_devices.id ='.$deviceID.
							' AND inuse = 1' ;
					$resdevices = mysql_query($mysql);
					if  (!$resdevices) {
						mySqlError($mysql);
					} else {						
						$rowdevices = mysql_fetch_array($resdevices);
						if  ($rowdevices['inuse'] == 0) {
							echo '<td style="width:'.$tdwidth.'%" class="keyscellempty">'.'</td>';
							continue;
						}
						if  ($rowremotekeys['type_image'] == 1 || $rowremotekeys['type_image'] == 2) {
							if ($rowdevices) {
								$booticon = $rowdevices['booticon'] ;
							} else {
								$booticon = null;
							}
						}
						// Really should read all at once
						$statuslink = getStatusLink(Array('deviceID' => $deviceID, 'propertyID' => $rowremotekeys['propertyID']));
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
				if ($rowremotekeys['inputtype']=="button") { 
						$fieldtype = "button";
						$fieldclass = "btn btn-block button ". $clicks;
				} 
				if  ($rowremotekeys['type_image'] == 1 || $rowremotekeys['type_image'] == 2) {
					if ($rowremotekeys['booticon'] != null) {
						$booticon = $rowremotekeys['booticon'];
					}
				}
				if ($rowremotekeys['inputtype']=="button" || $rowremotekeys['inputtype']=="display") {
					$text = getDisplayText($rowremotekeys);
					echo '<'.$fieldtype.' class="'.$fieldclass;
					if (strlen($status)>1) echo ' '.$status;
					if (strlen($link)>1) echo ' '.$link;
					if (strlen($class)>1) echo ' '.$class;
					echo '"';
					if (strlen($cellid)>1) echo ' id="'.$cellid.'"';
					echo ' data-remotekey="'.$rowremotekeys['id'].'"';
					if ($rowremotekeys['repeat_time'] != 0) echo ' data-repeat-time="'.$rowremotekeys['repeat_time'].'"';
					echo '>';
					if ($booticon != null) {								// if icon then do icon <i>
						echo '<i class="btn-icon '.$booticon;
						if ($text != null) echo ' '.'rem-icon-left';
						echo '">';
						echo '</i>';
					} 
					if (isset($deviceID))$text = replacePlaceholder($text, Array('deviceID' => $deviceID));
					if ($text != null) 	echo '<span class="buttontext">'.$text.'</span>';
					echo '</'.$fieldtype.'>';
					echo "</td>\n\r";
				} else {
					if ($rowremotekeys['inputtype']=="btndropdown") {
						echo '<div style="position: relative;height:100%">';
						echo '<button class="btn btn-block btn-success dropdown-toggle rem-button';
						if (strlen($status)>1) echo ' '.$status;
						if (strlen($link)>1) echo ' '.$link;
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
						if (strlen($class)>1) echo ' '.$class;
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
	$text = null;
	if ($row['type_image'] == 0 || $row['type_image'] == 2) {
		$text = (!empty($row['inputoptions']) ? $row['inputoptions'] : $row['name']);
		if ($row['inputtype']=="btndropdown") $text = $row['name'];
	}
	$text = rtrim($text);
	return $text;
}
?>
