#! /bin/bash
HOST=$1
USER=$2
PASSWD=$3
CMD=$4

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

