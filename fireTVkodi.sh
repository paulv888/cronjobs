#! /bin/bash
adb devices | grep '192.168.2.30' &> /dev/null
if [ $? != 0 ]; then
  adb connect 192.168.2.30
  sleep 1         # give it time to run
fi
adb shell am start -n org.xbmc.kodi/.Splash
adb disconnect
