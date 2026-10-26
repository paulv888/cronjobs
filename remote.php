<?php require_once 'includes.php';

header('Cache-Control: no-store');
header('Referrer-Policy: same-origin');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');

// ---------------------------------------------------------------------------
// Who is this?
//
//   - A LAN caller (or the box itself) is trusted, as it always was. That is what
//     keeps the in-house dashboards and the Joomla embed password free.
//   - Anyone else needs the session cookie that auth.php mints after a Firebase
//     sign-in. No session means the login form, not a refusal.
//
// The page key is still honoured while the shell is being swapped over; it goes
// away together with the apikey parameter in process.php.
// ---------------------------------------------------------------------------
$isLocal = requestIsLocal();
$username = null;
$sessionUser = null;
$sessionReason = null;

$sessionToken = haSessionTokenFromRequest();
if ($sessionToken !== '') {
	$sessionUser = haSessionValidate($sessionToken, $sessionReason);
	if ($sessionUser) $username = $sessionUser['username'];
}

if ($username === null && $isLocal) $username = 'Local';

$apikey = '';
if ($username === null) {
	$apikey = (isset($_GET['key']) && is_string($_GET['key'])) ? trim($_GET['key']) : '';
	if ($apikey !== '') {
		$found = PDOFetchRow('SELECT username FROM ha_mi_remote_users WHERE inuse = 1 and `apikey` = ?', [$apikey]);
		if (!empty($found['username'])) $username = $found['username'];
	}
}

$remoteID = 1;
if (isset($_GET['name'])) {
	$remote = PDOFetchRow('SELECT id FROM ha_remote_remotes WHERE UCASE(`description`) = ?',
		[is_string($_GET['name']) ? strtoupper(trim($_GET['name'])) : '']);
	if (!$remote || empty($remote['id'])) {
		http_response_code(404);
		die('Invalid Remote');
	}
	$remoteID = $remote['id'];
}

$firebaseConfig = array(
	'apiKey' => FIREBASE_API_KEY,
	'authDomain' => FIREBASE_AUTH_DOMAIN,
	'projectId' => FIREBASE_PROJECT_ID,
	'appId' => FIREBASE_APP_ID,
	'messagingSenderId' => FIREBASE_MESSAGING_SENDER_ID,
);
$basePath = getPath(true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta http-equiv="content-type" content="text/html; charset=utf-8" />
<title>HA Remote</title>
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=3, viewport-fit=cover" />
<meta name="mobile-web-app-capable" content="yes" />
<meta name="apple-mobile-web-app-capable" content="yes" />
<meta name="apple-mobile-web-app-title" content="Remote" />
<meta name="theme-color" content="#E9ECE5"/>
<link rel="manifest" href="<?php echo $basePath; ?>/manifest.json" />
<link rel="apple-touch-icon" href="<?php echo $basePath; ?>/images/apple-touch-icon.png" />
<link rel="icon" type="image/png" sizes="32x32" href="<?php echo $basePath; ?>/images/favicon-32x32.png" />
<link rel="stylesheet" href="/media/templates/site/mod-cassiopeia/css/template.css" type="text/css" media="screen" />
<link rel="stylesheet" href="/media/templates/site/mod-cassiopeia/css/user.css" type="text/css" media="screen" />
<link rel="stylesheet" href="/media/templates/site/mod-cassiopeia/css/global/colors_alternative.css" type="text/css" media="screen" />
<link rel="stylesheet" href="/media/system/css/joomla-fontawesome.css" type="text/css" media="screen" />
<script src="/media/vendor/jquery/js/jquery.min.js"></script>
<script async src="/templates/mod-cassiopeia/js/bstrap3.min.js"></script>
<script>window.VLO_REMOTE_HOST = 'pwa';</script>
<script src="<?php echo $basePath; ?>/js/remote.js"></script>
<?php if ($username === null) { ?>
<script>window.VLO_FIREBASE = <?php echo json_encode($firebaseConfig); ?>;</script>
<script src="<?php echo $basePath; ?>/js/vendor/firebase-app-compat.js"></script>
<script src="<?php echo $basePath; ?>/js/vendor/firebase-auth-compat.js"></script>
<script src="<?php echo $basePath; ?>/js/login.js"></script>
<style>
	html, body { height: 100%; }
	body.login-page { background-color: #E9ECE5; }
	.login-wrap { max-width: 380px; margin: 0 auto; padding: 24px 16px calc(24px + env(safe-area-inset-bottom)); }
	.login-card { background: #fff; border-radius: 10px; padding: 20px; box-shadow: 0 1px 4px rgba(0,0,0,.15); }
	.login-card h1 { font-size: 1.25rem; margin: 0 0 4px; }
	.login-card p.lead { font-size: .9rem; color: #666; margin: 0 0 16px; }
	.login-row { margin-bottom: 12px; }
	.login-row label { display: block; font-size: .85rem; margin-bottom: 4px; color: #444; }
	.login-row input[type=email], .login-row input[type=password] { width: 100%; padding: 10px; font-size: 1rem; border: 1px solid #ccc; border-radius: 6px; }
	.login-row input[type=submit] { width: 100%; padding: 12px; font-size: 1rem; border: 0; border-radius: 6px; background: #0d6efd; color: #fff; }
	.login-row input[type=submit][disabled] { opacity: .6; }
	.login-status { display: none; font-size: .9rem; margin-bottom: 12px; }
	.login-error { color: #b02a37; }
	.signout-row { padding: 6px 10px; text-align: right; background-color: #222; }
	.signout-row a { color: #9ecbff; font-size: .85rem; }
	body.dark-mode .login-card { background: #242526; color: #e4e6eb; }
	body.dark-mode .login-card p.lead { color: #adb5bd; }
	body.dark-mode .login-row label { color: #adb5bd; }
	body.dark-mode .login-row input[type=email], body.dark-mode .login-row input[type=password] { background: #2e3033; border-color: #44474d; color: #e4e6eb; }
</style>
<?php } ?>
</head>
<body style="padding:0px;"<?php if ($username === null) echo ' class="login-page"'; ?>>
<noscript>
    <div style="position: fixed; top: 0; left: 0; z-index: 3000; height: 100%; width: 100%; background-color: #E9ECE5">
        <p style="margin-left: 10px">JavaScript is not enabled.</p>
    </div>
</noscript>
   <div id='system-message-container'></div>
<?php if ($username === null) { ?>
   <div class="login-wrap">
     <div class="login-card">
       <h1>Sign in</h1>
       <p class="lead">Sign in once and this remote works from anywhere.</p>
       <div id="login-status" class="login-status"></div>
       <div id="login-restoring" style="display:none">Checking your session&hellip;</div>
       <form id="login-form" novalidate style="display:none">
         <div class="login-row">
           <label for="login-email">Email</label>
           <input type="email" id="login-email" name="email" autocomplete="username" autocapitalize="none" spellcheck="false" />
         </div>
         <div class="login-row">
           <label for="login-password">Password</label>
           <input type="password" id="login-password" name="password" autocomplete="current-password" />
         </div>
         <div class="login-row">
           <input type="submit" id="login-submit" value="Sign in" />
         </div>
       </form>
     </div>
   </div>
<?php } else { ?>
   <div class="keyscell" style="background-color: #222; color:#fff"> <?php echo greeting().' '.htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?></div>
<?php if ($sessionUser) { ?>
   <div class="signout-row"><a href="#" onclick="if(window.VloAuth){event.preventDefault();window.VloAuth.signOut();}return false;">Sign out</a></div>
<?php } ?>
<?php   loadRemote($remoteID, $apikey); ?>
   <div class=" row-fluid">
   <div class="float-start form-check form-switch">  <input checked="checked" class="form-check-input" type="checkbox" role="switch" id="autorefresh" />  <label class="form-check-label" for="autorefresh">Auto Refresh</label></div>
   <div class="float-end" style="padding:2px"><input type="button" class="btn btn-info"  value="Refresh" 
                onClick="window.location.reload()" /></div>
	</div>
	<div id="myDebug" class="debug" style="display:none"></div>
<?php } ?>
</body>
</html>
