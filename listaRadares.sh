#!/bin/bash

cd /home/eval/%rassv6%/spain.tsk/radar_data.rbk

radares=`find . -maxdepth 1 -type f | grep -v "%" | cut -d"/" -f2 | cut -d "." -f1 | sort`
IFS="
"
listado=""
for i in $radares; do listado="$listado $i"; done


echo $listado
