<?php require_once 'includes.php';
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
<meta name="theme-color" content="#B7CFE4"/>
<link rel="stylesheet" href="/templates/protostar-remote/css/template.css" type="text/css" media="screen" /> 
<link rel="stylesheet" href="/templates/protostar-remote/css/woffs.css" type="text/css" media="screen" /> 
<script src="/media/jui/js/jquery.min.js"></script>
<script src="/media/jui/js/bootstrap.min.js"></script>
<script async src="/media/jui/js/jquery.min.js"></script>
<script async src="/cronjobs/70D455DC-ACB4-4525-8A85-E6009AE93AF4/js/remote.min.js"></script>
<!--script async src="/cronjobs/70D455DC-ACB4-4525-8A85-E6009AE93AF4/js/remote.combined.js"></script-->
<noscript>
    <div style="position: fixed; top: 0px; left: 0px; z-index: 3000; 
                height: 100%; width: 100%; background-color: #B7CFE4">
        <p style="margin-left: 10px">JavaScript is not enabled.</p>
    </div>
</noscript>
</head>
<body style="padding:0px;">
   <div id='system-message-container'></div>
<?php
   require_once 'includes.php'; 
   loadRemote($remoteID);?>
   <div class=" row-fluid">
   <div class="pull-left" style="padding:2px"><button id="autorefresh" class="btn btn-success active" type="button" data-toggle="button">Auto Refresh</button></div>
   <div class="pull-right" style="padding:2px"><input type="button" class="btn btn-info"  value="Refresh" 
                onClick="window.location.reload()" /></div>
	</div>
</body>
</html>
<?php //ob_end_flush();?>
