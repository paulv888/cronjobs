#! /bin/bash
adb devices | grep '$1' &> /dev/null
if [ $? != 0 ]; then
  adb connect $1
  sleep 1         # give it time to run
fi

GALLERYNOCAM="adb shell am start -n com.rcreations.WebCamViewerPaid/.IpCamViewerActivity -a 'android.intent.action.VIEW' -e selectView GALLERY_VIEW"
GALLERY="adb shell am start -n com.rcreations.WebCamViewerPaid/.IpCamViewerActivity -a 'android.intent.action.VIEW' -e selectView GALLERY_VIEW -e selectCameraName"
SELECT="adb shell am start -n com.rcreations.WebCamViewerPaid/.IpCamViewerActivity -a 'android.intent.action.MAIN' -e selectView GALLERY_VIEW -e selectCameraName"
MATRIX="adb shell am start -n com.rcreations.WebCamViewerPaid/.IpCamViewerActivity -a 'android.intent.action.VIEW' -e selectView MATRIX_VIEW -e selectGroupName"
MSELECT="adb shell am start -n com.rcreations.WebCamViewerPaid/.IpCamViewerActivity -a 'android.intent.action.MAIN' -e selectView MATRIX_VIEW -e selectGroupName"

adb shell am force-stop com.rcreations.WebCamViewerPaid
adb shell am force-stop org.xbmc.kodi
adb shell am force-stop com.android.deskclock
case "$1" in
192.168.2.18)
        adb shell am force-stop com.netflix.mediaclient
    ;;
192.168.2.30)
        adb shell am force-stop com.netflix.ninja
    ;;
*) echo Nothing Done
   ;;
esac


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

#adb shell am start -n com.rcreations.WebCamViewerPaid/.IpCamViewerActivity -a "android.intent.action.MAIN" -e selectView GALLERY_VIEW -e selectGroupName Outside
#adb shell am start -n com.rcreations.WebCamViewerPaid/.IpCamViewerActivity -a "android.intent.action.MAIN" -e selectView GALLERY_VIEW -e selectGroupName Coop
#adb shell am start -n com.rcreations.WebCamViewerPaid/.IpCamViewerActivity -a "android.intent.action.MAIN" -e selectView GALLERY_VIEW -e selectCameraName "Front Door"
#adb disconnect

