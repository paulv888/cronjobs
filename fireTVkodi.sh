#! /bin/bash
./fireTVconnected.sh
adb shell am start -n org.xbmc.kodi/.Splash
adb disconnect
