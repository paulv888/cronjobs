#!/bin/bash
NEWFILE=remote.combined.js
JSDIR=/home/www/cronjobs/70D455DC-ACB4-4525-8A85-E6009AE93AF4/js
cd $JSDIR
cat jquery-3.3.1.min.js > $NEWFILE
cat bootstrap.min.js >> $NEWFILE
yui-compressor remote.js >> $NEWFILE
