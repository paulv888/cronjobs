#! /bin/bash
adb connect 192.168.2.30
sleep 1         # give it time to run
adb connect 192.168.2.30
sleep 1         # give it time to run
adb shell am start -n org.xbmc.kodi/.Splash 
