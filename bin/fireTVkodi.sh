#! /bin/bash
/home/www/ha/bin/check-connected.sh "$1"
adb -s "$1" shell am force-stop org.xbmc.kodi
adb -s "$1" shell am force-stop com.rcreations.WebCamViewerPaid
adb -s "$1" shell am force-stop com.netflix.ninja
adb -s "$1" shell am start -n org.xbmc.kodi/.Splash
/home/www/ha/bin/check-connected.sh "$1"
#adb disconnect
