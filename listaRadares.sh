#!/bin/bash

cd /opt/data-sassc/tmp/backup/home/eval/%rassv6%/spain.tsk/radar_data.rbk

radares=`find . -maxdepth 1 -type f | grep -v "%" | cut -d"/" -f2 | cut -d "." -f1 | sort`
IFS="
"
listado=""
for i in $radares; do
    listado="$listado $i"
    echo "php main.php -r $i -d \"/opt/data-sassc/tmp/backup/home/eval/%rassv6%/spain.tsk/radar_data.rbk\" > logs/${i}.log 2>&1 &"
done

echo $listado
