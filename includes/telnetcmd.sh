#! /bin/bash
HOST='192.168.2.1'
USER=$1
PASSWD=$2
CMD=$3

(
echo open "$HOST"
sleep 0.1
echo "$USER"
sleep 0.1
echo "$PASSWD"
sleep 0.1
echo "$CMD"
sleep 0.1
echo "exit"
) | telnet

