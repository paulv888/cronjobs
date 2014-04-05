<?php 
require_once 'includes.php';
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
<meta http-equiv="content-type" content="text/html; charset=utf-8" />
<meta http-equiv="Content-Language" content="en" />
<title>HA Remote</title>
<meta name="viewport" content="width=device-width; initial-scale=0.9; maximum-scale=1.0; user-scalable=0;" />
<meta name="apple-mobile-web-app-capable" content="yes" />
<link rel="stylesheet" href="css/iPHC.css?v=4" type="text/css" media="screen" /> 
<script type="text/javascript" src="js/jquery-1.11.0.min.js"></script>
<script type="text/javascript" src="js/iphc.js?v=1"></script>
<script type="text/javascript" src="js/jquery.touchSwipe.min.js?v=1"></script>
</head>
<body>
  <div id="head">
<!--This is the main navigation-->
   <table id="toolbar">
     <tr>
      <?php
      $resdivs = mysql_query("SELECT * FROM ha_remote_divs WHERE showonremote = '-1' AND remoteID = 1 ORDER BY sort");
      $mycount = 1;
      while ($rowdivs = mysql_fetch_array($resdivs)) {
      	 echo "<td><a ";
      	 if ($mycount==1) echo 'class="selected "'; 
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
	loadRemote(1);
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