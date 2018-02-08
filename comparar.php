<?php

if ( false === ($matlab = @file($argv[1])) ) {
    exit(0);
}
if ( false === ($php = @file($argv[2])) ) {
    exit(0);
}

//print $argv[1] . " " . $argv[2] . PHP_EOL;

$i=0;
foreach($matlab as $lineMatlab) {
    $linePhp = $php[$i++];
    
    $valuesMatlab = explode(",", $lineMatlab);
    $valuesPhp = explode(",", $linePhp);
    
    if ( count($valuesMatlab) != count($valuesPhp) ) {
        die("differences beween " . $argv[1] . "(" . count($valuesMatlab) . ") and " . $argv[2] . "(" . count($valuesPhp) . ") in line(" . ($i) . ")" . PHP_EOL);
    }

    for($j = 0; $j<count($valuesMatlab); $j++) {
        $diff=trim($valuesMatlab[$j]) - trim($valuesPhp[$j]);
        if ( abs($diff)>0.0000000002 ) {
            die("differences beween " . $argv[1] . "(" . count($valuesMatlab) . ") and " . $argv[2] . "(" . count($valuesPhp) . ")" . PHP_EOL .
            "\tin line(" . ($i) . ")" . PHP_EOL .
            "\tdiff(" . abs($diff) . ")" . PHP_EOL .
            "\tmatlab val(" . $valuesMatlab[$j] . ") php val(" . $valuesPhp[$j] . ")" . PHP_EOL);
        }
    
    }    
    

}