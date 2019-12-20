#! /bin/bash
function_connected () {
  adb devices | grep -Fq "$1"
  if [ $? == 0 ]; then
    return 1
  fi
  return 0
}
function_unauthorized () {
  adb devices | grep -Fq 'unauth'
  if [ $? == 0 ]; then
    return 1
  fi
  return 0
}
function_sleep () {
#  adb -s "$1" shell dumpsys power | grep -Fq "state=OFF"
  adb -s "$1" shell dumpsys display | grep -Fq "mScreenState=OFF"
  if [ $? == 0 ]; then
    return 1
  fi
  return 0
}
set -x
adb devices | grep -Fq "$1" | grep -Fq "device"
IP="$1"
function_connected $IP
if [ $? == 0 ]; then
  adb connect $IP
  sleep 1
fi

function_unauthorized
if [ $? == 1 ]; then
  adb kill-server
  sleep 1
  adb connect $IP
  sleep 1
fi

function_sleep $IP
if [ $? == 1 ]; then
  adb -s "$IP" shell input keyevent 26
#echo  adb -s "$IP" shell input keyevent 26
fi

exit

