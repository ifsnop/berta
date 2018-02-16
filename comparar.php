<?php

if ( false === ($matlab = @file($argv[1])) ) {
    die("ERROR: " . $argv[1] . " not found" . PHP_EOL);
}

$base = basename($argv[1]);
$flPos = strpos($base, "_FL");
$dotPos = strpos($base, ".");
if ( $flPos === false || $dotPos === false ) {
    die("ERROR malformed filename " . $argv[1] . PHP_EOL);
}
$phpFile = "RESULTADOS/" . substr($base, $flPos+3, $dotPos-$flPos-3) . "/" . $base;

if ( false === ($php = @file($phpFile)) ) {
    die("ERROR: $phpFile not found" . PHP_EOL);
}

//print $argv[1] . " " . $argv[2] . PHP_EOL;
print ".";

$i=0;
foreach($matlab as $lineMatlab) {
    $linePhp = $php[$i++];
    
    $valuesMatlab = explode(",", $lineMatlab);
    $valuesPhp = explode(",", $linePhp);
    
    if ( count($valuesMatlab) != count($valuesPhp) ) {
        die("differences beween " . $argv[1] . "(" . count($valuesMatlab) . ") and " . $phpFile . "(" . count($valuesPhp) . ") in line(" . ($i) . ")" . PHP_EOL);
    }

    for($j = 0; $j<count($valuesMatlab); $j++) {
        $diff=trim($valuesMatlab[$j]) - trim($valuesPhp[$j]);
        if ( abs($diff)>0.0000000002 ) {
            die("differences beween " . $argv[1] . "(" . count($valuesMatlab) . ") and " . $phpFile . "(" . count($valuesPhp) . ")" . PHP_EOL .
            "\tin line(" . ($i) . ")" . PHP_EOL .
            "\tdiff(" . abs($diff) . ")" . PHP_EOL .
            "\tmatlab val(" . $valuesMatlab[$j] . ") php val(" . $valuesPhp[$j] . ")" . PHP_EOL);
        }
    
    }    
}
