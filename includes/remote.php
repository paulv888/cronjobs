<?php
function loadRemote($remoteID) {
	$resdivs = mysql_query("SELECT * FROM ha_remote_divs WHERE showonremote = '-1' AND remoteID = ".$remoteID." ORDER BY sort");
    while ($rowdivs = mysql_fetch_array($resdivs)) {
		echo '<div id="'.$rowdivs['name'].'"';
		if (strlen($rowdivs['class'])>0) {echo 'class="'.$rowdivs['class'].'">';} else {echo 'class="remotedivs">' ;}
		$resremotekeys = mysql_query("SELECT MAX(xpos) as maxx, MAX(ypos) as maxy FROM ha_remote_keys WHERE remotediv =".$rowdivs['id'] );
		$rowremotekeys = mysql_fetch_array($resremotekeys);
		$myxmax = $rowremotekeys['maxx'];
		$myymax = $rowremotekeys['maxy'];
		$tdwidth = floor(100/$myxmax);
		echo '<table class="table">';
		for ($myycell = 1; $myycell <= $myymax; $myycell++) {
			echo '<tr class="keysrow">';
			for ($myxcell = 1; $myxcell <= $myxmax; $myxcell++) {
				$resremotekeys = mysql_query("SELECT * FROM ha_remote_keys where remotediv =".$rowdivs['id']." AND xpos =".$myxcell." AND ypos =".$myycell." ORDER BY remotediv DESC");
				$rowremotekeys = mysql_fetch_array($resremotekeys);
				$type = "";
				$status = "";
				if ($rowremotekeys) {
					$class = $rowremotekeys['class'];
					($cellid = strlen($rowremotekeys['cellid']) > 0 ? $rowremotekeys['cellid'] : "");
					if (strlen($rowremotekeys['deviceID'])>0) {
						$resdevices = mysql_query("SELECT * FROM ha_mf_devices Where id =".$rowremotekeys['deviceID']);
						if  ($resdevices) {
							$rowdevices = mysql_fetch_array($resdevices);
							if  ($rowdevices['inuse'] == 0) {
								echo '<td style="width:'.$tdwidth.'%" class="keyscellempty">'.'</td>';
								continue;
							}
							if  ($rowremotekeys['type_image'] == 1) {
								if ($rowdevices) {
									$type = "type".$rowdevices['typeID'] ;
								} else {
									$type = "";
								}
							}
							$status = '';
							if ($rowdevices['monitortypeID']==MONITOR_STATUS || $rowdevices['monitortypeID']==MONITOR_LINK_STATUS) {
								$resmonitor = mysql_query("SELECT ha_mf_monitor_status.status FROM ha_mf_monitor_status WHERE ha_mf_monitor_status.deviceID =".$rowremotekeys['deviceID']);
								if  ($resmonitor) {
									$rowmonitor = mysql_fetch_array($resmonitor);
									if ($rowmonitor && ($rowremotekeys['inputtype']=="button" || $rowremotekeys['inputtype']=="field")) {
										$status = ($rowmonitor['status'] == STATUS_ON ? 'on' : ($rowmonitor['status'] == STATUS_OFF ? 'off' : 'unknown'));
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
	   	    		if ($rowremotekeys['inputtype']=="display" || $rowremotekeys['inputtype']=="field") {
	   	    				$fieldtype = "div";
	   	    				$fieldclass = $rowremotekeys['inputtype'];
	   	    		}
	   	    		if ($rowremotekeys['inputtype']=="button") { 
	   	    				$fieldtype = "button";
	   	    				$fieldclass = "btn button rem-button";
	   	    		} 
	   	    		if ($rowremotekeys['inputtype']=="button" || $rowremotekeys['inputtype']=="display" || $rowremotekeys['inputtype']=="field") {
		   	    		echo '<'.$fieldtype.' class="'.$fieldclass;
		   	    		if (strlen($status)>1) echo ' '.$status;
		   	    		if (strlen($link)>1) echo ' '.$link;
		   	    		if (strlen($class)>1) echo ' '.$class;
		   	    		if (strlen($type)>1) echo ' '.$type.'" '; else echo '"';
		   	    		if (strlen($cellid)>1) echo ' id="'.$cellid.'"';
		   	    		echo ' remotekey="'.$rowremotekeys['id'].'">';
			   	    	if (strlen($rowremotekeys['picture'])>0) echo '<img alt="" src="'.$rowremotekeys['picture'].'"/>';
			   	    	if (strlen($rowremotekeys['picture'])==0) {
			   	    		if ($rowremotekeys['inputtype']=="field") {
			   	    			$tres = mysql_query($rowremotekeys['inputoptions']); 
								$trow = mysql_fetch_array($tres);
								echo $trow[0];
			   	    		} else {
			   	    			echo $rowremotekeys['name'];
							} 
			   	    	}
			   	    	//echo '</p>';
			   	    	//echo "</td>";
		   	    		echo '</'.$fieldtype.'>';
			   	    	echo "</td>\n\r";
	   	    		} else {
				   	    if ($rowremotekeys['inputtype']=="dropdown") {
		   	    			echo '<form class="formdropdown" method="get" remotekey="'.$rowremotekeys['id'].'">';
				      		echo '<select';
		   	    			if (strlen($cellid)>1) echo ' id='.$cellid;
		   	    			echo ' class="controlselect"';
							echo ' remotekey="'.$rowremotekeys['id'].'"';
		   	    			echo '>';
				      		$first= true;
			   	    		$options = explode(";",$rowremotekeys['inputoptions']);
			   	    		foreach ($options as &$optionstring) {
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
			   	    		foreach ($options as &$optionstring) {
			   	    			$option = explode(",",$optionstring);
			   	    			echo '<option value="'.$option[0].'">'.$option[1].'</option>';
			   	    		}
			   	    		echo '</select>';
			   	    		echo '<button type="submit" class="btn button jump-button';
							if (strlen($class)>1) echo ' '.$class;
							echo '"';
			   	    		echo 'remotekey="'.$rowremotekeys['id'].'">';
		   	    			if (strlen($rowremotekeys['picture'])>0) echo '<img alt="" src="'.$rowremotekeys['picture'].'"/>';
			   	    		if (strlen($rowremotekeys['picture'])==0) echo $rowremotekeys['name']; 
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
	echo "<div class='message'></div></div>";
    }
}
?>