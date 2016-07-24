#! /bin/bash
adb connect 192.168.2.30
sudo adb push kodilogs/advancedsettings.xml /storage/emulated/legacy/Android/data/org.xbmc.kodi/files/.kodi/userdata
