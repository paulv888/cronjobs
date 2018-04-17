#! /bin/bash
grep -rl $'\xEF\xBB\xBF' .
#tail --bytes=+4 cj_configuration.php > t.txt
#mv t.txt cj_configuration.php
#rm t.txt
