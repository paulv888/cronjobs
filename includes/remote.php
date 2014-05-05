<?php
function loadremote($remoteID) {
    $resdivs = mysql_query('SELECT * FROM ha_remote_divs WHERE showonremote = "-1" AND remoteID = '.$remoteID.' ORDER BY sort');
	$numrows = mysql_num_rows ($resdivs);
    $mycount = 1;
	if ($numrows > 1) {
		echo '<div class="bs-example bs-example-tabs">';
		echo '<ul id="myTab" class="nav nav-tabs nav-justified">';
		while ($rowdivs = mysql_fetch_array($resdivs)) {
			echo '<li class="';
			if ($mycount==1) echo 'active'; 
			$name = str_replace ( ' ' , '' , $rowdivs['name']); 
			echo '"><a href="#'.$name.'"  data-toggle="tab"';
			echo '>';
			$text = $rowdivs['name'];
			$booticon = $rowdivs['booticon'];
			if ($booticon != null) {								// if icon then do icon <i>
				echo '<i class="btn-icon '.$booticon;
				if ($text != null) echo ' '.'rem-icon-left';
				echo '">';
				echo '</i>';
			} 
			if ($text != null) echo ''.$text.'';
			echo '</a>';
			echo '</li>';
			$mycount = 2;
		}
		echo '</ul>';
		echo '<div id="myTabContent" class="tab-content">';
	}
	loadRemoteBootstrap($remoteID);
	if ($numrows > 1) echo '</div></div>';
	echo '<div id="spinner">Executing...</div>';
}

function loadRemoteBootstrap($remoteID) {

    $resdivs = mysql_query("SELECT * FROM ha_remote_divs WHERE showonremote = '-1' AND remoteID = ".$remoteID." ORDER BY sort");
	$mycount=1;
    while ($rowdivs = mysql_fetch_array($resdivs)) {
		$name = str_replace ( ' ' , '' , $rowdivs['name']); 
		if ($mycount==1) {
			echo '<div class="tab-pane active in" id="'.$name.'">';
		} else {
			echo '<div class="tab-pane" id="'.$name.'">';
		}
		$mycount=2;
		$resremotekeys = mysql_query("SELECT MAX(xpos) as maxx, MAX(ypos) as maxy FROM ha_remote_keys WHERE remotediv =".$rowdivs['id'] );
		$rowremotekeys = mysql_fetch_array($resremotekeys);
		$myxmax = $rowremotekeys['maxx'];
		$myymax = $rowremotekeys['maxy'];
		$tdwidth = floor(100/$myxmax);
		echo '<table class="table table-condensed table">';
		for ($myycell = 1; $myycell <= $myymax; $myycell++) {
			echo '<tr class="keysrow">';
			for ($myxcell = 1; $myxcell <= $myxmax; $myxcell++) {
				$resremotekeys = mysql_query("SELECT * FROM ha_remote_keys where remotediv =".$rowdivs['id']." AND xpos =".$myxcell." AND ypos =".$myycell." ORDER BY remotediv DESC");
				$rowremotekeys = mysql_fetch_array($resremotekeys);
				$typeicon = null;
				$status = "";
				if ($rowremotekeys) {
					$class = $rowremotekeys['class'];
					($cellid = strlen($rowremotekeys['cellid']) > 0 ? $rowremotekeys['cellid'] : "");
					if (strlen($rowremotekeys['deviceID'])>0) {
						$mysql = 'SELECT ha_mf_devices.id, ha_mf_device_types.typeID, inuse, monitortypeID, booticon FROM ha_mf_devices ' .
								' LEFT JOIN ha_mf_device_types ON ha_mf_devices.typeID = ha_mf_device_types.id WHERE ha_mf_devices.id ='.$rowremotekeys['deviceID'];
						$resdevices = mysql_query($mysql);
						if  (!$resdevices) {
							mySqlError($mysql);
						} else {						
							$rowdevices = mysql_fetch_array($resdevices);
							if  ($rowdevices['inuse'] == 0) {
								echo '<td style="width:'.$tdwidth.'%" class="keyscellempty">'.'</td>';
								continue;
							}
							if  ($rowremotekeys['type_image'] > 0) {
								if ($rowdevices) {
									$typeicon = $rowdevices['booticon'] ;
								} else {
									$typeicon = null;
								}
							}
							$status = '';
							if ($rowdevices['monitortypeID']==MONITOR_STATUS || $rowdevices['monitortypeID']==MONITOR_LINK_STATUS) {
								$resmonitor = mysql_query("SELECT ha_mf_monitor_status.status FROM ha_mf_monitor_status WHERE ha_mf_monitor_status.deviceID =".$rowremotekeys['deviceID']);
								if  ($resmonitor) {
									$rowmonitor = mysql_fetch_array($resmonitor);
									if ($rowmonitor && ($rowremotekeys['inputtype']=="button" || $rowremotekeys['inputtype']=="field")) {
										$status = ($rowmonitor['status'] == STATUS_ON ? 'on' : 
										          ($rowmonitor['status'] == STATUS_OFF ? 'off' : 
											       ($rowmonitor['status'] == STATUS_UNKNOWN ? 'unknown' : 
												   ($rowmonitor['status'] == STATUS_ERROR ? 'error' : 'undefined'))));
									} else {
										$status = '';
									}
								}
							}
							$link = '';
							if ($rowdevices['monitortypeID']==MONITOR_LINK || $rowdevices['monitortypeID']==MONITOR_LINK_STATUS) {
								$resmonitor = mysql_query("SELECT ln FROM ha_vw_monitor_link_status WHERE deviceID =".$rowremotekeys['deviceID']);
								if  ($resmonitor) {
									$rowmonitor = mysql_fetch_array($resmonitor);
										$link = ($rowmonitor['ln'] == LINK_OK ? '' : ($rowmonitor['ln'] == LINK_WARNING ? 'btn-warning' : 'btn-danger'));
								}
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
	   	    		echo ">";
					$clicks = (is_null($rowremotekeys['commandIDdown']) ? "click-up rem-button" : "click-down rem-button");
	   	    		if ($rowremotekeys['inputtype']=="display" || $rowremotekeys['inputtype']=="field") {
	   	    				$fieldtype = "div";
	   	    				$fieldclass = $rowremotekeys['inputtype'];
	   	    		}
	   	    		if ($rowremotekeys['inputtype']=="button") { 
	   	    				$fieldtype = "button";
	   	    				$fieldclass = "btn button ". $clicks;
	   	    		} 
	   	    		if ($rowremotekeys['inputtype']=="button" || $rowremotekeys['inputtype']=="display" || $rowremotekeys['inputtype']=="field") {
						if ($typeicon != null) {				// what icon to show
							$booticon = $typeicon;
						} elseif ($rowremotekeys['booticon'] != null) {
							$booticon = $rowremotekeys['booticon'];
						} else {
							$booticon = null;
						}
						$text = null;
			   	    	if ($booticon == null) {			// what text to show
			   	    		if ($rowremotekeys['inputtype']=="field") {					// execute query from field
			   	    			$tres = mysql_query($rowremotekeys['inputoptions']); 
								$trow = mysql_fetch_array($tres);
								$text = $trow[0];
			   	    		} else {													// no icon show name
//								if  ($booticon != null && $typeicon != null) $text = ' ';
								$text = ' '.$rowremotekeys['name'];
							} 
			   	    	} else {														// display type icon, if not then use name, else check if something in input options
							if ($typeicon != null || $rowremotekeys['type_image'] == 2) {
								$text = ' '.$rowremotekeys['name'];
							} else {
								$text = ' '.$rowremotekeys['inputoptions']; 
							}
						}
						$text = rtrim($text);
		   	    		echo '<'.$fieldtype.' class="'.$fieldclass;
		   	    		if (strlen($status)>1) echo ' '.$status;
		   	    		if (strlen($link)>1) echo ' '.$link;
		   	    		if (strlen($class)>1) echo ' '.$class;
						echo '"';
		   	    		if (strlen($cellid)>1) echo ' id="'.$cellid.'"';
		   	    		echo ' remotekey="'.$rowremotekeys['id'].'">';
						if ($booticon != null) {								// if icon then do icon <i>
							echo '<i class="btn-icon '.$booticon;
							if ($text != null) echo ' '.'rem-icon-left';
							echo '">';
							echo '</i>';
						} 
						if ($text != null) echo '<div>'.$text.'</div>';

			   	    	//echo '</p>';
			   	    	//echo "</td>";
		   	    		echo '</'.$fieldtype.'>';
			   	    	echo "</td>\n\r";
	   	    		} else {
				   	    if ($rowremotekeys['inputtype']=="btndropdown") {
							echo '<div style="position: relative">';
							echo '<button class="btn btn-info dropdown-toggle rem-button';
			   	    		if (strlen($class)>1) echo ' '.$class;
							echo '"';
							echo ' remotekey="'.$rowremotekeys['id'].'"';
							echo ' type="button" data-toggle="dropdown">';
//							echo '<div>'.$rowremotekeys['name'].'</div> </button>';
//							echo '<i class="btn-icon icon-arrow-down-3 rem-icon-left"></i>';
							echo $rowremotekeys['name'].' '.'</span><span class="caret"></span></button>';

			   	    		$options = explode(";",$rowremotekeys['inputoptions']);
		   	    			$option = explode(",",$options[0]);
							echo '<ul class="dropdown-menu btndropdown" role="menu" myvalue="'.$option[0].'"'; 			// properly set default to first
		   	    			if (strlen($cellid)>1) echo ' id="'.$cellid.'"';
							echo '>';
				      		//$first= true;
			   	    		foreach ($options as $optionstring) {
			   	    			$option = explode(",",$optionstring);
								if ($option[0] == '-') {
									echo '<li class="divider"></li>';
								} else {
									echo '<li><a href="#" value="'.$option[0].'">'.$option[1].'</a></li>';
								}
							}
							echo '</ul></div>';
			   	    		echo '</td>';
						}
				   	    if ($rowremotekeys['inputtype']=="dropdown") {
		   	    			echo '<form class="formdropdown" method="get" remotekey="'.$rowremotekeys['id'].'">';
				      		echo '<select';
		   	    			if (strlen($cellid)>1) echo ' id='.$cellid;
		   	    			echo ' class="controlselect"';
							echo ' remotekey="'.$rowremotekeys['id'].'"';
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
		   	    			echo '<form class="formdropdownlist" method="get" remotekey="'.$rowremotekeys['id'].'">';
				      		echo '<select';
		   	    			if (strlen($cellid)>1) echo ' id='.$cellid;
		   	    			echo ' class="controlselect-button"';
							echo ' remotekey="'.$rowremotekeys['id'].'"';
		   	    			echo '>';
			   	    		$options = explode(";",$rowremotekeys['inputoptions']);
			   	    		foreach ($options as $optionstring) {
			   	    			$option = explode(",",$optionstring);
			   	    			echo '<option value="'.$option[0].'">'.$option[1].'</option>';
			   	    		}
			   	    		echo '</select>';
			   	    		echo '<button type="submit" class="btn button jump-button';
							if (strlen($class)>1) echo ' '.$class;
							echo '"';
			   	    		echo ' remotekey="'.$rowremotekeys['id'].'">';
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
	echo "</div>";
    }
}

?>
