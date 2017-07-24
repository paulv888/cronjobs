#! /bin/bash
adb devices | grep '$1' &> /dev/null
if [ $? != 0 ]; then
  adb connect $1
  sleep 1         # give it time to run
fi
adb shell am force-stop com.rcreations.WebCamViewerPaid
#adb shell am force-stop org.xbmc.kodi
adb shell am force-stop com.netflix.ninja
adb shell am start -n org.xbmc.kodi/.Splash
adb disconnect
