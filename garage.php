<?php require_once 'includes.php';?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
<meta http-equiv="content-type" content="text/html; charset=utf-8" />
<meta http-equiv="Content-Language" content="en" />
<title>HA Remote</title>
<meta name="viewport" content="width=device-width; maximum-scale=1.0; user-scalable=no;" />
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
<script type="text/javascript" language="javaScript" src="/cronjobs/70D455DC-ACB4-4525-8A85-E6009AE93AF4/js/remote.js"></script>
</head>
<body style="padding:0px">
<?php
   require_once '/home/public_html/cronjobs/70D455DC-ACB4-4525-8A85-E6009AE93AF4/includes.php'; 
   loadRemote(6);?>
   <div id='system-message'></div>
   <div class="pull-right" style="padding:2px"><input type="button" class="btn button btn-info"  value="Refresh" 
                onClick="window.location.reload()" /></div>
</body>
</html>
