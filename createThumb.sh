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

  viddir="${1}"
  if [[ -d "${viddir}" ]]; then
    cd "${viddir}"
    curfile="${2}.${3}"
    thumbfile="${2}.tbn"
    echo $curfile
    echo $thumbfile

    if [ "$curfile" -nt "$thumbfile" ] ;then
      keeptrying=-1
      itsoffset=5
      step=5

      tnsize=10000
      tmpfile=$(mktemp /tmp/ffmpeg.XXXXXX)
      while [ "$keeptrying" -eq "-1" ]
      do
        ffmpeg  -itsoffset -$itsoffset  -y -i "$curfile" -vcodec mjpeg -vframes 1 -an -f rawvideo -s 320x240 "$thumbfile" > "$tmpfile" 2>&1
        keeptrying=$(checkthumb "$thumbfile" "$tnsize")
        itsoffset=$(( itsoffset + step ))
        if [ "$keeptrying" -eq "-1" ] ;then echo "$keeptrying File: $thumbfile Size: $thumbsize Retry: $itsoffset $keeptrying" ; fi
        if [ $itsoffset -gt 60 ] ; then
           itsoffset=5
           step=1
           tnsize=5000
        fi
      done
      echo "`date` Created thumbnail: $thumbfile"
    fi
  fi

