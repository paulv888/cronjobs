#! /bin/bash
adb devices | grep '$1' &> /dev/null
if [ $? != 0 ]; then
  adb connect $1
  sleep 1         # give it time to run
fi

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
adb disconnect

