<?php
define("MY_SUBNET", "192.168.2");
define("MY_VPN_SUBNET", "192.168.10");

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

define("SCHEME_ALERT_PDO", 309);
define("COMMAND_SNAPSHOT", 450);
?>
