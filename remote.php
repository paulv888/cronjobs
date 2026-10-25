<?php require_once 'includes.php';

header('Cache-Control: no-store');
header('Referrer-Policy: same-origin');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');

// The page key is the only credential, so it is required for every request.
$apikey = (isset($_GET['key']) && is_string($_GET['key'])) ? trim($_GET['key']) : '';
$found = ($apikey !== '')
	? PDOFetchRow('SELECT username FROM ha_mi_remote_users WHERE inuse = 1 and `apikey` = ?', [$apikey])
	: false;

if (!$found || empty($found['username'])) {
	http_response_code(401);
	die('Not Authorized');
}
$username = $found['username'];

if (isset($_GET['name'])) {
	$remote = PDOFetchRow('SELECT id FROM ha_remote_remotes WHERE UCASE(`description`) = ?', [is_string($_GET['name']) ? strtoupper(trim($_GET['name'])) : '']);
	if (!$remote || empty($remote['id'])) {
		http_response_code(404);
		die('Invalid Remote');
	}
	$remoteID = $remote['id'];
} else {
	$remoteID = 1;
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
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=3" />
<meta name="mobile-web-app-capable" content="yes" />
<meta name="theme-color" content="#E9ECE5"/>
<link rel="stylesheet" href="/media/templates/site/mod-cassiopeia/css/template.css" type="text/css" media="screen" /> 
<link rel="stylesheet" href="/media/templates/site/mod-cassiopeia/css/user.css" type="text/css" media="screen" /> 
<link rel="stylesheet" href="/media/templates/site/mod-cassiopeia/css/global/colors_alternative.css" type="text/css" media="screen" /> 
<link rel="stylesheet" href="/media/system/css/joomla-fontawesome.css" type="text/css" media="screen" /> 
<script src="/media/vendor/jquery/js/jquery.min.js"></script>
<script async src="/templates/mod-cassiopeia/js/bstrap3.min.js"></script>
<script src="<?php echo getPath(true);?>/js/remote.js"></script>
</head>
<body style="padding:0px;">
<noscript>
    <div style="position: fixed; top: 0px; left: 0px; z-index: 3000; height: 100%; width: 100%; background-color: #E9ECE5">
        <p style="margin-left: 10px">JavaScript is not enabled.</p>
    </div>
</noscript>
   <div id='system-message-container'></div>
<?php
   echo '<div class="keyscell" style="background-color: #222; color:#fff"> '.greeting().' '.htmlspecialchars($username, ENT_QUOTES, 'UTF-8').'</div>';
   loadRemote($remoteID, $apikey);?>
   <div class=" row-fluid">
   <div class="float-start form-check form-switch">  <input checked="checked" class="form-check-input" type="checkbox" role="switch" id="autorefresh" />  <label class="form-check-label" for="autorefresh">Auto Refresh</label></div>
   <div class="float-end" style="padding:2px"><input type="button" class="btn btn-info"  value="Refresh" 
                onClick="window.location.reload()" /></div>
	</div>
	<div id="myDebug" class="debug" style="display:none"></div>
</body>
</html>
<?php //ob_end_flush();?>
