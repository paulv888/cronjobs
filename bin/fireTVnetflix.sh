#! /bin/bash
/home/www/ha/bin/check-connected.sh "$1"
adb -s "$1" shell am force-stop com.rcreations.WebCamViewerPaid
adb -s "$1" shell am force-stop org.xbmc.kodi
adb -s "$1" shell am force-stop com.android.deskclock
adb -s "$1" shell am force-stop com.netflix.ninja
adb -s "$1" shell am start -n com.netflix.ninja/.MainActivity
/home/www/ha/bin/check-connected.sh "$1"
#adb disconnect
