#! /bin/bash
/home/www/ha/bin/check-connected.sh "$1"
adb -s "$1" reboot &    # run in background
sleep 2         # give it time to run
kill -SIGINT $! # send Ctrl-C to PID of last background process 
#adb disconnect




