<?php 
require 'connect-db.php'; 
include 'defines.php'; 
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
<meta http-equiv="content-type" content="text/html; charset=utf-8" />
<meta http-equiv="Content-Language" content="en" />
<title>HA Remote</title>
<meta name="viewport" content="width=device-width; initial-scale=1.0; maximum-scale=1.0; user-scalable=0;" />
<meta name="apple-mobile-web-app-capable" content="yes" />
<link rel="stylesheet" href="css/iPHC.css" type="text/css" media="screen" /> 
<script type="text/javascript" src="js/jquery-1.4.4.min.js"></script>
<script type="text/javascript" src="js/iphc.js"></script>
<script type="text/javascript" src="js/jquery.touchSwipe.min.js"></script>
</head>
<body>
  <div id="head">
<!--This is the main navigation-->
   <table id="toolbar">
     <tr>
      <?php
      $resdivs = mysql_query("SELECT * FROM ha_remote_divs WHERE showonremote = '-1' ORDER BY sort");
      $mycount = 1;
      while ($rowdivs = mysql_fetch_array($resdivs)) {
      	 echo "<td><a ";
      	 if ($mycount==1) echo "class=selected "; 
      	 echo "href='#".$rowdivs['name']."'>".$rowdivs['name']."</a></td>";
      	 $mycount = 2;
      }
      ?>
	</tr>
  </table>
 </div>
<div id="body">
<!--These are Schemes or Macros-->
	<?php
	$resdivs = mysql_query("SELECT * FROM ha_remote_divs WHERE showonremote = '-1' ORDER BY sort");
    while ($rowdivs = mysql_fetch_array($resdivs)) {
		echo "<div id=".$rowdivs['name']." ";
		if (strlen($rowdivs['class'])>0) {echo 'class="'.$rowdivs['class'].'">';} else {echo 'class="remotedivs"> <ul>' ;}
		$resremotekeys = mysql_query("SELECT MAX(xpos) as maxx, MAX(ypos) as maxy FROM ha_remote_keys WHERE remotediv =".$rowdivs['id'] );
		$rowremotekeys = mysql_fetch_array($resremotekeys);
		$myxmax = $rowremotekeys['maxx'];
		$myymax = $rowremotekeys['maxy'];
		$tdwidth = floor(100/$myxmax);
		for ($myycell = 1; $myycell <= $myymax; $myycell++) {
			echo "<table><tr class='keysrow'>";
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
   	    				$tdwidthspan=$tdwidth*($rowremotekeys['hspan']+1);
   	    				echo " width=$tdwidthspan%";   
   	    				$myxcell+=$rowremotekeys['hspan'];
   	    				echo " colspan=".$rowremotekeys['hspan'];
   	    			} else {
   	    				echo " width=$tdwidth%";   
   	    			}
	   	    		echo ">";
	   	    		if ($rowremotekeys['inputtype']=="display" || $rowremotekeys['inputtype']=="field") {
	   	    				$fieldtype = "div";
	   	    				$fieldclass = $rowremotekeys['inputtype'];
	   	    		}
	   	    		if ($rowremotekeys['inputtype']=="button") { 
	   	    				$fieldtype = "button";
	   	    				$fieldclass = "button";
	   	    		} 
	   	    		if ($rowremotekeys['inputtype']=="button" || $rowremotekeys['inputtype']=="display" || $rowremotekeys['inputtype']=="field") {
		   	    		echo '<'.$fieldtype.' class="'.$fieldclass;
		   	    		if (strlen($status)>1) echo ' '.$status;
		   	    		if (strlen($link)>1) echo ' '.$link;
		   	    		if (strlen($class)>1) echo ' '.$class;
		   	    		if (strlen($type)>1) echo ' '.$type.'" '; else echo '"';
		   	    		if (strlen($cellid)>1) echo ' id='.$cellid;
		   	    		echo ' remotekey="'.$rowremotekeys['id'].'">';
			   	    	if (strlen($rowremotekeys['picture'])>0) echo '<img src="'.$rowremotekeys['picture'].'">';
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
		   	    			echo '<form class="formdropdown" name=control method="GET" value="'.$rowremotekeys['id'].'">';
				      		echo '<select ';
		   	    			if (strlen($cellid)>1) echo ' id='.$cellid;
		   	    			echo ' class="controlselect">';
				      		$first= true;
			   	    		$options = explode(";",$rowremotekeys['inputoptions']);
			   	    		foreach ($options as &$optionstring) {
			   	    			$option = explode(",",$optionstring);
			   	    			if ($first) { 
				   	    			echo '<option selected="selected" value='.$option[0].'>'.$option[1].'</option>';
			   	    				$first=FALSE;
			   	    			} else {
			   	    				echo "<option value='$option[0]'>$option[1]</option>";
			   	    			}
			   	    		}
			   	    		echo '</select>';
				      		echo '</form>';
			   	    		echo "</td>";
						}
				   	    if ($rowremotekeys['inputtype']=="dropdownlist") {
		   	    			echo '<form class="formdropdownlist" name=control method="GET" value="'.$rowremotekeys['id'].'">';
				      		echo '<select ';
		   	    			if (strlen($cellid)>1) echo ' id='.$cellid;
		   	    			echo ' class="controlselect">';
			   	    		$options = explode(";",$rowremotekeys['inputoptions']);
			   	    		foreach ($options as &$optionstring) {
			   	    			$option = explode(",",$optionstring);
			   	    			echo "<option value='$option[0]'>$option[1]</option>";
			   	    		}
			   	    		echo '</select>';
			   	    		echo '<button type="submit" class="jump';
							if (strlen($class)>1) echo ' '.$class;
							echo '"';
			   	    		echo 'value="'.$rowremotekeys['id'].'">';
		   	    			if (strlen($rowremotekeys['picture'])>0) echo '<img src="'.$rowremotekeys['picture'].'">';
			   	    		if (strlen($rowremotekeys['picture'])==0) echo $rowremotekeys['name']; 
			   	    		echo '</button>';
				      		echo '</form>';
			   	    		echo "</td>";
						}
	   	    		}
				}
				else {
					echo '<td width='.$tdwidth.'% class="keyscellempty"></td>';
				}
			}
	    echo "</tr></table>";
		}
	echo "</ul>";
	echo "<ul><div class='message'></div></ul></div>";
    }
	?>
</div>
 <div id="spinner">Loading
 </div>

<!--div id="footer">
	<p><script type="text/javascript">function img1click() {window.location.reload( false );}</script><img src="images/refresh.png" onmousedown="img1click()" />
	</p>
</div-->
</body>
</html>