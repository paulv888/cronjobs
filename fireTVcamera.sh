#! /bin/bash
adb devices | grep '192.168.2.30' &> /dev/null
if [ $? != 0 ]; then
  adb connect 192.168.2.30
  sleep 1         # give it time to run
fi

GALLERY="adb shell am start -n com.rcreations.WebCamViewerPaid/.IpCamViewerActivity -a 'android.intent.action.VIEW' -e selectView GALLERY_VIEW -e selectCameraName"
SELECT="adb shell am start -n com.rcreations.WebCamViewerPaid/.IpCamViewerActivity -a 'android.intent.action.MAIN' -e selectView GALLERY_VIEW -e selectCameraName"
MATRIX="adb shell am start -n com.rcreations.WebCamViewerPaid/.IpCamViewerActivity -a 'android.intent.action.VIEW' -e selectView MATRIX_VIEW -e selectGroupName"
MSELECT="adb shell am start -n com.rcreations.WebCamViewerPaid/.IpCamViewerActivity -a 'android.intent.action.MAIN' -e selectView MATRIX_VIEW -e selectGroupName"

#adb shell am force-stop com.rcreations.WebCamViewerPaid
adb shell am force-stop org.xbmc.kodi
adb shell am force-stop com.netflix.ninja

case "$1" in
1) CAM="Front"
   echo $GALLERY $CAM
   $GALLERY "$CAM"
   $SELECT "$CAM"
    ;;
2) CAM="Front Door"
   echo $GALLERY $CAM
   $GALLERY "$CAM"
   $SELECT "$CAM"
    ;;
3) CAM="Deck"
   echo $GALLERY $CAM
   $GALLERY "$CAM"
   $SELECT "$CAM"
    ;;
4) CAM="Run"
   echo $GALLERY $CAM
   $GALLERY "$CAM"
   $SELECT "$CAM"
    ;;
5) CAM="Roost"
   echo $GALLERY $CAM
   $GALLERY "$CAM"
   $SELECT "$CAM"
    ;;
6) CAM="Eggbox"
   echo $GALLERY $CAM
   $GALLERY "$CAM"
   $SELECT "$CAM"
    ;;
outside) CAM="Outside"
   echo $MATRIX $CAM
#   $GALLERY "Front"
   $MATRIX "$CAM"
   $MSELECT "$CAM"
    ;;
chickens) CAM="Coop"
   echo $MATRIX $CAM
#   $GALLERY "Front"
   $MATRIX "$CAM"
   $MSELECT "$CAM"
    ;;
*) echo "No View specified >$1<" 
#adb shell am start -n com.rcreations.WebCamViewerPaid/.IpCamViewerActivity
   ;;
esac

#adb shell am start -n com.rcreations.WebCamViewerPaid/.IpCamViewerActivity -a "android.intent.action.MAIN" -e selectView GALLERY_VIEW -e selectGroupName Outside
#adb shell am start -n com.rcreations.WebCamViewerPaid/.IpCamViewerActivity -a "android.intent.action.MAIN" -e selectView GALLERY_VIEW -e selectGroupName Coop
#adb shell am start -n com.rcreations.WebCamViewerPaid/.IpCamViewerActivity -a "android.intent.action.MAIN" -e selectView GALLERY_VIEW -e selectCameraName "Front Door"
adb disconnect

