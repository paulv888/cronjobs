<?php require_once 'includes.php';
if (isset($_GET['remote'])) {
	if (!$remoteID = FetchRow('SELECT id FROM ha_remote_remotes WHERE UCASE(`description`)="'.strtoupper($_GET['remote']).'"')['id']) die('Invalid Remote');
} else {
	$remoteID=1;
}
?>
<!DOCTYPE html>
<!--  PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd"> -->
<html lang="en">
<head>
<meta http-equiv="content-type" content="text/html; charset=utf-8" />
<title>HA Remote</title>
<meta name="viewport" content="width=device-width, maximum-scale=1.0, user-scalable=no" />
<meta name="mobile-web-app-capable" content="yes" />
<link rel="stylesheet" href="/templates/protostar-mod/css/template.css" type="text/css" media="screen" /> 
<script type="text/javascript" src="/media/jui/js/jquery.min.js"></script>
<script type="text/javascript" src="/media/jui/js/jquery-noconflict.js"></script>
<script type="text/javascript" src="/media/jui/js/jquery-migrate.min.js"></script>
<script type="text/javascript" src="/media/system/js/tabs-state.js"></script>
<script type="text/javascript" src="/media/system/js/mootools-core.js"></script>
<script type="text/javascript" src="/media/system/js/core.js"></script>
<script type="text/javascript" src="/media/system/js/mootools-more.js"></script>
<script type="text/javascript" src="/media/jui/js/bootstrap.min.js"></script>
<script type="text/javascript" src="/cronjobs/70D455DC-ACB4-4525-8A85-E6009AE93AF4/js/remote.js?v=2"></script>
</head>
<body style="padding:0px">
<?php
   require_once 'includes.php'; 
   loadRemote($remoteID);?>
   <div id='system-message-container'></div>
   <div class=" row-fluid">
   <div class="pull-left" style="padding:2px"><button id="autorefresh" class="btn btn-success active" type="button" data-toggle="button">Auto Refresh</button></div>
   <div class="pull-right" style="padding:2px"><input type="button" class="btn btn-info"  value="Refresh" 
                onClick="window.location.reload()" /></div>
	</div>
</body>
</html>
