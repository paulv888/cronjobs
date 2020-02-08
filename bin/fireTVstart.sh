#! /bin/bash
#set -x
/home/www/ha/bin/check-connected.sh "$1"
GALLERYNOCAM="adb -s $1 shell am start -n com.rcreations.WebCamViewerPaid/.IpCamViewerActivity -a 'android.intent.action.VIEW' -e selectView GALLERY_VIEW"
GALLERY="adb -s $1 shell am start -n com.rcreations.WebCamViewerPaid/.IpCamViewerActivity -a 'android.intent.action.VIEW' -e selectView GALLERY_VIEW -e selectCameraName"
SELECT="adb -s $1 shell am start -n com.rcreations.WebCamViewerPaid/.IpCamViewerActivity -a 'android.intent.action.MAIN' -e selectView GALLERY_VIEW -e selectCameraName"
MATRIX="adb -s $1 shell am start -n com.rcreations.WebCamViewerPaid/.IpCamViewerActivity -a 'android.intent.action.VIEW' -e selectView MATRIX_VIEW -e selectGroupName"
MSELECT="adb -s $1 shell am start -n com.rcreations.WebCamViewerPaid/.IpCamViewerActivity -a 'android.intent.action.MAIN' -e selectView MATRIX_VIEW -e selectGroupName"


case "$2" in
camera)
  adb -s "$1" shell am force-stop com.rcreations.WebCamViewerPaid
  adb -s "$1" shell am force-stop org.xbmc.kodi
  adb -s "$1" shell am force-stop com.android.deskclock
  adb -s "$1" shell am force-stop com.netflix.ninja

  case "$2" in
  Outside) echo $MATRIX $2
     $MSELECT "$2"
     $MATRIX "$2"
      ;;
  Coop) echo $MATRIX $2
     $MSELECT "$2"
     $MATRIX "$2"
      ;;
  *) echo $GALLERY
     $GALLERYNOCAM
#   $GALLERY "$2"
#   $SELECT "$2"
     ;;
  esac
#adb shell am start -n com.rcreations.WebCamViewerPaid/.IpCamViewerActivity -a "android.intent.action.MAIN" -e selectView GALLERY_VIEW -e selectGroupName Out$
#adb shell am start -n com.rcreations.WebCamViewerPaid/.IpCamViewerActivity -a "android.intent.action.MAIN" -e selectView GALLERY_VIEW -e selectGroupName Coop
#adb shell am start -n com.rcreations.WebCamViewerPaid/.IpCamViewerActivity -a "android.intent.action.MAIN" -e selectView GALLERY_VIEW -e selectCameraName "F$
#adb disconnect
  ;;

kodi)
  adb -s "$1" shell am force-stop org.xbmc.kodi
  adb -s "$1" shell am force-stop com.rcreations.WebCamViewerPaid
  adb -s "$1" shell am force-stop com.netflix.ninja
  adb -s "$1" shell am start -n org.xbmc.kodi/.Splash
  ;;

netflix)
  adb -s "$1" shell am force-stop com.rcreations.WebCamViewerPaid
  adb -s "$1" shell am force-stop org.xbmc.kodi
  adb -s "$1" shell am force-stop com.android.deskclock
  adb -s "$1" shell am force-stop com.netflix.ninja
  adb -s "$1" shell am start -n com.netflix.ninja/.MainActivity
  ;;
*)
 echo "Unknown parameter: $2"
 exit 1
  ;;
esac

/home/www/ha/bin/check-connected.sh "$1"


