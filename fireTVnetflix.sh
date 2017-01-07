#! /bin/bash
adb devices | grep '192.168.2.30' &> /dev/null
if [ $? != 0 ]; then
  adb connect 192.168.2.30
  sleep 1         # give it time to run
fi
adb shell am force-stop com.rcreations.WebCamViewerPaid
adb shell am force-stop org.xbmc.kodi
#adb shell am force-stop com.netflix.ninja
adb shell am start -n com.netflix.ninja/.MainActivity
adb disconnect

