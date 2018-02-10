#!/bin/bash

IFS="
"

for i in `find MATLAB -name "*.txt"|grep -v 000 | sort`; do

    #base=`basename $i`
    
    php comparar.php $i

done
