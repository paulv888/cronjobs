#! /bin/bash
adb connect 192.168.2.30
sleep 2         # give it time to run
adb connect 192.168.2.30
sleep 2         # give it time to run
adb shell am start -n com.netflix.ninja/.MainActivity
adb disconnect

