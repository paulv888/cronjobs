#! /bin/bash
adb connect 192.168.2.30
adb reboot &    # run in background
sleep 2         # give it time to run
kill -SIGINT $! # send Ctrl-C to PID of last background process 
sleep 2         # give it time to run
adb connect 192.168.2.30
adb reboot &    # run in background
sleep 2         # give it time to run
kill -SIGINT $! # send Ctrl-C to PID of last background process 
sleep 2         # give it time to run
adb connect 192.168.2.30
adb reboot &    # run in background
sleep 2         # give it time to run
kill -SIGINT $! # send Ctrl-C to PID of last background process 
