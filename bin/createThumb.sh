#! /bin/bash
function checkthumb
{
#  result=`identify -verbose "$1" | grep Type`
#  case $result in
#    *"Palette"*) echo -1 ;;
#  esac
  thumbsize=$(stat -c%s "$1")
  if [ "$thumbsize" -lt $2 ] ;then
    echo -1
  else
    echo 0
  fi
}

#
#  Main
#
#set -x
  viddir="${1}"
  overwrite="${4:-0}"
  if [[ -d "${viddir}" ]]; then
    cd "${viddir}"
    curfile="${2}.${3}"
    thumbfile="${2}.tbn"
    echo $curfile $thumbfile
    if [ "$overwrite" -eq "1" ] || [ "$curfile" -nt "$thumbfile" ] || [ ! -s "$thumbfile" ];then
      keeptrying=-1
      ss=15
      step=1

      tnsize=15000
      tmpfile=$(mktemp /tmp/ffmpeg.XXXXXX)
      while [ "$keeptrying" -eq "-1" ]
      do
        #ffmpeg  -ss $ss  -y -i "$curfile" -vcodec mjpeg -vframes 1 -an -f rawvideo -s 320x240 "$thumbfile" > /dev/null 2>&1
		ffmpeg  -ss $ss  -y -i "$curfile" -vcodec mjpeg -vframes 1 -an -f rawvideo -filter:v scale="320:-1"  "$thumbfile" 
	if [ $? -ne 0 ];then
	  echo "ffmpeg error"
	  exit 1
	fi
        keeptrying=$(checkthumb "$thumbfile" "$tnsize")
        if [ "$keeptrying" -eq "-1" ] ;then echo "$keeptrying File: $thumbfile LessThan: $tnsize StartSec: $ss" ; fi
        ss=$(( ss + step ))
        if [ $ss -gt 25 ] ; then
           ss=15
           step=1
           tnsize=$((tnsize -3000))
	   if [ $tnsize -lt 0 ] ; then
	  	echo "tnsize lt 0"
		exit 1
	  fi
        fi
      done
      echo "`date` Created thumbnail: $thumbfile"
    else
      echo "`date` File exists: $thumbfile"
      exit 0;
    fi
  fi

