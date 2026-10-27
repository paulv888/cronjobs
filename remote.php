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
// There is no page key any more: `?key=` is ignored, and the only credentials are
// the session cookie and the LAN address.
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

// The vhost caches .js for a week, so give each asset a version derived from its
// own mtime. Without this a deploy can leave a browser running last week's script,
// which is exactly the kind of bug that presents as "the page does nothing".
$asset = function ($relative) use ($basePath) {
	$version = @filemtime(__DIR__ . '/' . $relative);
	return $basePath . '/' . $relative . ($version ? '?v=' . $version : '');
};
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
<script src="<?php echo $asset('js/remote.js'); ?>"></script>
<script>
// The worker handles the app shell cache and push. Its scope is the directory it
// is served from, which is /ha/.
if ('serviceWorker' in navigator) {
	window.addEventListener('load', function () {
		navigator.serviceWorker.register('<?php echo $basePath; ?>/sw.js').catch(function () {});
	});
}
</script>
<?php if ($username === null) { ?>
<script>window.VLO_FIREBASE = <?php echo json_encode($firebaseConfig); ?>;</script>
<script src="<?php echo $asset('js/vendor/firebase-app-compat.js'); ?>"></script>
<script src="<?php echo $asset('js/vendor/firebase-auth-compat.js'); ?>"></script>
<script src="<?php echo $asset('js/login.js'); ?>"></script>
<style>
	html, body { height: 100%; }
	body.login-page { background-color: #E9ECE5; }
	.login-wrap { max-width: 380px; margin: 0 auto; padding: 24px 16px calc(24px + env(safe-area-inset-bottom)); }
	.login-card { background: #fff; border-radius: 10px; padding: 20px; box-shadow: 0 1px 4px rgba(0,0,0,.15); }
	/* 16px base: anything smaller makes iOS Safari zoom the page when a field is
	   focused, and this card is used one-handed on a phone. */
	.login-wrap { font-size: 16px; }
	.login-card h1 { font-size: 1.55rem; margin: 0 0 6px; }
	.login-card p.lead { font-size: 1rem; line-height: 1.4; color: #555; margin: 0 0 18px; }
	.login-row { margin-bottom: 14px; }
	.login-row label { display: block; font-size: 1rem; margin-bottom: 6px; color: #333; }
	.login-row input[type=email], .login-row input[type=password], .login-row input[type=text] {
		width: 100%; padding: 13px 12px; font-size: 16px; border: 1px solid #bbb; border-radius: 8px; }
	.password-wrap { position: relative; }
	.password-wrap input { padding-right: 4.9em; }
	.password-reveal { position: absolute; top: 50%; right: 6px; transform: translateY(-50%);
		background: none; border: 0; color: #0d6efd; font-size: .95rem; padding: 8px 10px; cursor: pointer; }
	.login-row input[type=submit] { width: 100%; padding: 15px; font-size: 1.1rem; border: 0; border-radius: 8px; background: #0d6efd; color: #fff; }
	.login-row input[type=submit][disabled] { opacity: .6; }
	.login-status { display: none; font-size: 1rem; line-height: 1.4; margin-bottom: 14px; }
	.login-error { color: #b02a37; }
	.login-google { width: 100%; padding: 13px; font-size: 1rem; background: #fff; color: #3c4043;
		border: 1px solid #dadce0; border-radius: 8px; cursor: pointer; }
	.login-google:hover { background: #f8f9fa; }
	.login-google[disabled] { opacity: .6; }
	.login-or { text-align: center; margin: 16px 0; position: relative; }
	.login-or::before { content: ''; position: absolute; left: 0; right: 0; top: 50%; border-top: 1px solid #ddd; }
	.login-or span { position: relative; background: #fff; padding: 0 12px; font-size: .9rem; color: #777; }
	.login-alt { margin: 18px 0 0; text-align: center; font-size: 1rem; }
	.login-alt a { color: #0d6efd; text-decoration: none; }
	#login-restoring { font-size: 1rem; margin-bottom: 14px; }
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
       <h1 id="login-title">Sign in</h1>
       <p class="lead" id="login-lead">Sign in once and this remote works from anywhere.</p>
       <div id="login-status" class="login-status"></div>
       <div id="login-restoring" style="display:none">Checking your session&hellip;</div>
       <button type="button" id="login-google" class="login-google">
         <svg width="18" height="18" viewBox="0 0 48 48" aria-hidden="true" style="vertical-align:-3px;margin-right:8px">
           <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>
           <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>
           <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>
           <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
         </svg>Continue with Google
       </button>
       <div class="login-or" id="login-or"><span>or</span></div>
       <form id="login-form" novalidate>
         <div class="login-row">
           <label for="login-email">Email</label>
           <input type="email" id="login-email" name="email" autocomplete="username" autocapitalize="none" spellcheck="false" />
         </div>
         <div class="login-row">
           <label for="login-password">Password</label>
           <div class="password-wrap">
             <input type="password" id="login-password" name="password" autocomplete="current-password" />
             <button type="button" id="login-reveal" class="password-reveal" aria-label="Show password" aria-pressed="false">Show</button>
           </div>
         </div>
         <div class="login-row" id="login-confirm-row" style="display:none">
           <label for="login-password2">Confirm password</label>
           <input type="password" id="login-password2" name="password2" autocomplete="new-password" />
         </div>
         <div class="login-row">
           <input type="submit" id="login-submit" value="Sign in" />
         </div>
       </form>
       <p class="login-alt"><a href="#" id="login-toggle">Create an account</a></p>
     </div>
   </div>
<?php } else { ?>
   <div class="keyscell" style="background-color: #222; color:#fff"> <?php echo greeting().' '.htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?></div>
<?php   loadRemote($remoteID); ?>
   <div class=" row-fluid">
   <div class="float-start form-check form-switch">  <input checked="checked" class="form-check-input" type="checkbox" role="switch" id="autorefresh" />  <label class="form-check-label" for="autorefresh">Auto Refresh</label></div>
<?php if ($sessionUser) { ?>
   <div class="float-end" style="padding:2px"><input type="button" class="btn btn-info" value="Sign out"
                onClick="if(window.VloRemoteSignOut){window.VloRemoteSignOut();}" /></div>
<?php } else { ?>
   <div class="float-end" style="padding:2px"><input type="button" class="btn btn-info"  value="Refresh" 
                onClick="window.location.reload()" /></div>
<?php } ?>
	</div>
	<div id="myDebug" class="debug" style="display:none"></div>
<?php } ?>
</body>
</html>
