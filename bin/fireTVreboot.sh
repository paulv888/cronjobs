#! /bin/bash
adb devices | grep '192.168.2.30' &> /dev/null
if [ $? != 0 ]; then
  adb connect 192.168.2.30
  sleep 1         # give it time to run
fi
adb reboot &    # run in background
sleep 2         # give it time to run
kill -SIGINT $! # send Ctrl-C to PID of last background process 
adb disconnect
