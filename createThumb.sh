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
      ss=5
      tnsize=7000
      tmpfile=$(mktemp /tmp/ffmpeg.XXXXXX)
      while [ "$keeptrying" -eq "-1" ]
      do
        ffmpeg  -itsoffset -$ss  -y -i "$curfile" -vcodec mjpeg -vframes 1 -an -f rawvideo -s 320x240 "$thumbfile" > "$tmpfile" 2>&1
        keeptrying=$(checkthumb "$thumbfile" "$tnsize")
        ss=$(( ss +5))
        if [ "$keeptrying" -eq "-1" ] ;then echo "$keeptrying File: $thumbfile Size: $thumbsize Retry: $ss $keeptrying" ; fi
        if [ $ss -gt 30 ] ; then
           ss=10
           tnsize=5000
        fi 
      done
      chown media "$thumbfile"
      chgrp vloon "$thumbfile"
      chmod 665 "$thumbfile"
      echo "`date` Created thumbnail: $thumbfile"
    fi
  fi

