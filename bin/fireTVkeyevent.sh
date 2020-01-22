#! /bin/bash
/home/www/ha/bin/check-connected.sh "$1"
adb -s "$1" shell input keyevent "$2"
#adb disconnect

