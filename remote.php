<?php require_once 'includes.php';
if (isset($_GET['key'])) {
	$found  = FetchRow('SELECT username FROM ha_mi_remote_users WHERE inuse = 1 and `apikey`="'.$_GET['key'].'"');
	if ($found) {
		$username = $found['username'];
	} else {
	    die('Not Authorized'); 
    }
	$apikey = $_GET['key'];
} else {
    $apikey='6785157a-27e6-483f-a52c-d73b7529fa21';
	$found  = FetchRow('SELECT username FROM ha_mi_remote_users WHERE inuse = 1 and `apikey`="'.$apikey.'"');
	if ($found) {
		$username = $found['username'];
	} else {
	    die('Not Authorized'); 
    }
//	die('Not Authorized');
}
if (isset($_GET['name'])) {
	if (!$remoteID = FetchRow('SELECT id FROM ha_remote_remotes WHERE UCASE(`description`)="'.strtoupper($_GET['name']).'"')['id']) die('Invalid Remote');
} else {
	$remoteID=1;
}
// header("Link: </templates/protostar-remote/css/template.css>; rel=preload; as=style");
// header("Link: </media/com_fabrik/images/ajax-loader.gif>; rel=preload; as=image", false);
//ob_start("ob_gzhandler");
?>
<!DOCTYPE html>
<!--  PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd"> -->
<html lang="en">
<head>
<meta http-equiv="content-type" content="text/html; charset=utf-8" />
<title>HA Remote</title>
<meta name="viewport" content="width=device-width, maximum-scale=1.0, user-scalable=no" />
<meta name="viewport" content="width=device-width, initial-scale=1, minimum-scale=1, maximum-scale=3">
<meta name="mobile-web-app-capable" content="yes" />
<meta name="theme-color" content="#E9ECE5"/>
<link rel="manifest" href="/manifest.json">
<link rel="stylesheet" href="/media/templates/site/mod-cassiopeia/css/template.css" type="text/css" media="screen" /> 
<link rel="stylesheet" href="/media/templates/site/mod-cassiopeia/css/user.css" type="text/css" media="screen" /> 
<link rel="stylesheet" href="/media/templates/site/mod-cassiopeia/css/global/colors_alternative.css" type="text/css" media="screen" /> 
<link rel="stylesheet" href="/media/system/css/joomla-fontawesome.css" type="text/css" media="screen" /> 
<script src="/media/vendor/jquery/js/jquery.min.js"></script>
<script async src="/templates/mod-cassiopeia/js/bstrap3.min.js"></script>
<script src="<?php echo getPath(true);?>/js/remote.js"></script>
<script>
// if ('serviceWorker' in navigator) {
  // navigator.serviceWorker.register('<?php echo getPath(true);?>/js/service-worker.js')
    // .then(reg => console.log('Service worker registered:',reg))
    // .catch(err => console.log('SW registration failed:',err));
// }
</script>
</head>
<script src="https://www.gstatic.com/firebasejs/9.23.0/firebase-app-compat.js"></script>
<script src="https://www.gstatic.com/firebasejs/9.23.0/firebase-messaging-compat.js"></script>
<body style="padding:0px;">
<noscript>
    <div style="position: fixed; top: 0px; left: 0px; z-index: 3000; height: 100%; width: 100%; background-color: #E9ECE5">
        <p style="margin-left: 10px">JavaScript is not enabled.</p>
    </div>
</noscript>
   <div id='system-message-container'></div>
   <script src="js/app.js"></script>
<?php
   require_once 'includes.php'; 
   echo '<div class="keyscell" style="background-color: #222; color:#fff"> '.greeting().' '.$username.'</div>';
   loadRemote($remoteID, $apikey);?>
   <div class=" row-fluid">
   <!--div class="float-start" style="padding:2px"><button id="autorefresh" class="btn btn-success active" type="button" data-toggle="button">Auto Refresh</button></div-->
   <div class="float-start form-check form-switch">  <input checked="checked" class="form-check-input" type="checkbox" role="switch" id="autorefresh" />  <label class="form-check-label" for="flexSwitchCheckDefault">Auto Refresh</label></div>
   <div class="float-end" style="padding:2px"><input type="button" class="btn btn-info"  value="Refresh" 
                onClick="window.location.reload()" /></div>
	</div>
	<div id="myDebug" class="debug" style="display:none"></div>
</body>
</html>
<?php //ob_end_flush();?>
