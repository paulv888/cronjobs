#! /bin/bash
adb connect 192.168.2.30
sudo adb pull /storage/emulated/legacy/Android/data/org.xbmc.kodi/files/.kodi/temp/kodi.log kodilogs
sudo adb pull /storage/emulated/legacy/Android/data/org.xbmc.kodi/files/.kodi/temp/kodi.old.log kodilogs
