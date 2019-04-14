#! /bin/bash
/home/www/ha/bin/check-connected.sh "$1"
adb shell am force-stop com.rcreations.WebCamViewerPaid
adb shell am force-stop org.xbmc.kodi
adb shell am force-stop com.android.deskclock
case "$1" in
192.168.2.18) 
	adb shell am force-stop com.netflix.mediaclient
	adb shell am start -n com.netflix.mediaclient/.ui.launch.NetflixComLaunchActivity   
    ;;
192.168.2.30)
	adb shell am force-stop com.netflix.ninja
	adb shell am start -n com.netflix.ninja/.MainActivity
    ;;
*) echo Nothing Done
   ;;
esac
#adb disconnect

