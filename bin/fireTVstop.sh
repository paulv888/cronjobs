#! /bin/bash
#set -x
/home/www/ha/bin/check-connected.sh "$1"

case "$2" in
camera)
  echo "Stopping $2"
  adb -s "$1" shell am force-stop com.rcreations.WebCamViewerPaid
  ;;

kodi)
  echo "Stopping $2"
  adb -s "$1" shell am force-stop org.xbmc.kodi
  ;;

netflix)
  echo "Stopping $2"
  adb -s "$1" shell am force-stop com.netflix.ninja
  ;;
*)
 echo "Unknown parameter: $2"
 exit 1
  ;;
esac

/home/www/ha/bin/check-connected.sh "$1"


