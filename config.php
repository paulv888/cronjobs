<?php
define("MY_SUBNET", "192.168.2");
define("MY_VPN_SUBNET", "192.168.123");

define("ALEXA_REQUIRE_SIGNATURE", true);

define("KODI_MUSIC_VIDEOS","smb://SRVMEDIA/media/My Music Videos");

if (strtoupper(gethostname()) == "HOME") {
	define("DOCUMENT_ROOT", "/home/www/vlohome");
	define("LOCAL_DATA", DOCUMENT_ROOT."/data");
	define("LOCAL_MUSIC_VIDEOS", LOCAL_DATA."/musicvideos");
	define("LOCAL_PLAYLISTS", LOCAL_DATA."/mydocs/My Playlists");
	define("LOCAL_L_DATA", LOCAL_DATA);
} else {
	define("LOCAL_L_DATA", "/mnt/data");
	define("DOCUMENT_ROOT", "/mnt");
	define("LOCAL_DATA", DOCUMENT_ROOT."/data");
	define("LOCAL_MUSIC_VIDEOS", LOCAL_DATA."/My Music Videos");
	define("LOCAL_PLAYLISTS", LOCAL_DATA."/Documents/My Playlists");
}
define("LOCAL_CAMERAS", LOCAL_L_DATA."/cameras");
define("LOCAL_LASTIMAGEDIR", LOCAL_CAMERAS."/lastimage");
define("LOCAL_RECYCLE", LOCAL_MUSIC_VIDEOS."/_XXX_recyclebin");
define("LOCAL_IMPORT", LOCAL_MUSIC_VIDEOS."/_XXX_import");

define("PUBLIC_LASTIMAGEDIR", "/data/cameras/lastimage");
define("PROCESS_REQUIRE_APIKEY", true);

define("SSH_IDENTITY",    "/etc/ha/remote-jobs");
define("SSH_KNOWN_HOSTS", "/etc/ha/known_hosts");

define("SCHEME_ALERT_PDO", 309);
define("COMMAND_SNAPSHOT", 450);

// ---------------------------------------------------------------------------
// Firebase, for the remote PWA. These are public identifiers that ship in the
// client bundle anyway: the web apiKey identifies the project, it does not
// authorise anything on its own. The one credential that must never live here is
// the service account JSON, which stays at FIREBASE_SERVICE_ACCOUNT, outside the
// docroot, and is read only by the push sender.
// ---------------------------------------------------------------------------
define("FIREBASE_PROJECT_ID", "vlo-remote-cf179");
define("FIREBASE_API_KEY", "AIzaSyDzO5Rc21-FO23rP9A-w9Z3B-p3rJWqZUY");
define("FIREBASE_AUTH_DOMAIN", "vlo-remote-cf179.firebaseapp.com");
define("FIREBASE_APP_ID", "1:948979009736:web:6d4d4fa5cf7c377f463dfd");
define("FIREBASE_MESSAGING_SENDER_ID", "948979009736");
define("FIREBASE_SERVICE_ACCOUNT", "/etc/ha/vlo-remote-cf179-firebase-adminsdk-fbsvc-0c7f3c9d96.json");
define("FIREBASE_VAPID_KEY", "BFJqLVHHZvu6xsqKkQL9qtfUMV7vaNWX9-0Ezl8dzTBuZu-t5xohuAEhqwq805SQrvYrlf4gXPuM15PLV3EW7xY");

// Remote sessions slide on this many days of inactivity. The ID token behind them
// only lives an hour and is re-minted silently; this is the long stop.
define("HA_SESSION_IDLE_DAYS", 30);

// Test hook, described in SPEC-remote-pwa.md. A request from the LAN can never
// reach the remote authorization branch, so while this flag is on these addresses
// are treated as remote instead. Off: the login flow has been verified from a
// genuinely external address, and leaving it on breaks the Joomla-embedded remotes,
// which send no credential by design. Turn it on only while testing the
// remote branch from the build machine.
define("TEST_FORCE_REMOTE", false);
define("TEST_FORCE_REMOTE_IPS", array("192.168.2.84", "192.168.2.54", "192.168.10.246", "192.168.10.248"));
?>
