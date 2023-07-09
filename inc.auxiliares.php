<?php

/**
 * Convierte altitudMode en un string
 * 
 * @param int $altitudeMode (ENTRADA)
 * @return string
 */
function altitudeModetoString ($altitudeMode){

    $modo = "";
    switch($altitudeMode){
	case 0: $modo = "clampToGround"; break; // "Subject to the ground"; break;
	case 1: $modo = "clampToSeaFloor"; break; // "Subject to seabed"; break;
	case 2: $modo = "relativeToGround"; break; // "Relative to soil"; break;
	case 3: $modo = "relativeToSeaFloor"; break; // "Relative to the seabed"; break;
	case 4: $modo = "absolute"; break;
    }
    return $modo;
}

function printMalla($malla, $relleno = " ") {

    for($i = 0; $i < count($malla); $i++) {
        for($j = 0; $j < count($malla[$i]); $j++) {
            if ( $malla[$i][$j] == "0" ) {
                print $relleno;
            } else {
                print $malla[$i][$j];
            }
        }
        print PHP_EOL;
    }
}

/*
 * Desde que se utiliza conrec para los contornos, los índices son decimales 
 * (ahora el contorno se situa entre medias de la celda a 1 y la celda a 0
 * y printContornos no funciona)
 *
 */
function printContornos($contornos, $malla) {
/*
    $malla = array();
    for($i=0; $i<$tamMalla; $i++) {
        $malla[$i] = array();
        for($j=0; $j<$tamMalla; $j++) {
            $malla[$i][$j] = 0;
        }
    }
*/
    $alfa = array(
        'a','b','c','d','e','f','g','h','i','j','k','l','m','n','o','p','q','r','s','t','u','v','w','x','y','z',
        'A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z'
    );
    $i = 0;
    foreach($contornos as $contorno) {
        foreach($contorno as $pto) {
            if ( $malla[$pto['fila']][$pto['col']] == "0" ) {
                $malla[$pto['fila']][$pto['col']] = $alfa[(($i++)%count($alfa))];
            } else {
                print "punto repetido!" . PHP_EOL;
            }
        }
    }
    printMalla($malla);

    return true;
}

/**
 * Express number with a byte prefix (byte, Kb, Mb, Gb...)
 * @param size float number
 * @return float rounded number with prefix
*/
function convertBytes($size) {
        $unit = array('b', 'Kb', 'Mb', 'Gb', 'Tb', 'Pb');
        return @round($size / pow(1024, ($i = floor(log($size, 1024)))), 1) . '' . $unit[$i];
}

/**
* If the input arrays have the same string keys, then the later value
* for that key will overwrite the previous one. If, however, the arrays
* contain numeric keys, the later value will not overwrite the original
* value, but will be appended.
* Values in the input array with numeric keys will be renumbered with
* incrementing keys starting from zero in the result array.
* So this function is actually making some conditional statements. You
* can replace array merge with normal adding, consisting of the loop
* (foreach or any other) and the [] operator. You can write a function
* imitating array_merge, like(using reference to not copy the array..).
*
* @url https://stackoverflow.com/questions/23348339/optimizing-array-merge-operation
*
* @param array1 source and destination array
* @param array2 second array to copy to array1
*/
function array_merge_fast(&$array1, &$array2) {
    foreach($array2 as $i) {
        $array1[] = $i;
    }
}

$num_to_bits = array(0, 1, 1, 2, 1, 2, 2, 3,
                     1, 2, 2, 3, 2, 3, 3, 4);
/*
 * Count set bits by pre-storing count set bits in nibbles.
 * @param int number to count bits set to '1'
 * @return int number of bits set to '1'
 */
function countSetBits($num) {
    global $num_to_bits;
    $nibble = 0;
    if (0 == $num)
        return $num_to_bits[0];

    // Find last nibble
    $nibble = $num & 0xf;

    // Use pre-stored values to find count
    // in last nibble plus recursively add
    // remaining nibbles.
    return $num_to_bits[$nibble] +
           countSetBits($num >> 4);
}

/*
 * escribe la cadena enviada a stderr, añadiendo fecha
 * si insert_EOL, añade fecha y salto de línea
 *
 */
function logger($str, $insert_EOL = true) {

    $d = new DateTime();
    if ( $insert_EOL )
        $ret = $d->format("Y-m-d H:i:s.v") . $str . PHP_EOL;
    else
        $ret = $str;
    fwrite(STDERR, $ret);
    return true;
}

/**
 * @author Ovunc Tukenmez <ovunct@live.com>
 * version 1.0.1 - 10/26/2017
 *
 * @url https://github.com/ovunctukenmez/Combinations/blob/master/Combinations.php
 * This class is used to generate combinations with or without repetition allowed
 * as well as permutations with or without repetition allowed
 */
class Combinations
{
    private $_elements = array();
    
    public function __construct($elements)
    {
	$this->setElements($elements);
    }
    
    public function setElements($elements){
	$this->_elements = array_values($elements);
    }

    public function getCombinations($length, $with_repetition = false){
	$combinations = array();
	
	foreach ($this->x_calculateCombinations($length, $with_repetition) as $value){
	    $combinations[] = $value;
	}
	
	return $combinations;
    }
    
    public function getPermutations($length, $with_repetition = false){
	$permutations = array();
	
	foreach ($this->x_calculatePermutations($length, $with_repetition) as $value){
	    $permutations[] = $value;
	}
	
	return $permutations;
    }
    
    private function x_calculateCombinations($length, $with_repetition = false, $position = 0, $elements = array()){

	$items_count = count($this->_elements);
	
	for ($i = $position; $i < $items_count; $i++){
	    
	    $elements[] = $this->_elements[$i];
	    
	    if (count($elements) == $length){
		yield $elements;
	    }
	    else{
		foreach ($this->x_calculateCombinations($length, $with_repetition, ($with_repetition == true ? $i : $i + 1), $elements) as $value2){
		    yield $value2;
		}
	    }
	    
	    array_pop($elements);
	}
    }
    
    private function x_calculatePermutations($length, $with_repetition = false, $elements = array(), $keys = array()){

	foreach($this->_elements as $key => $value){

	    if ($with_repetition == false){
		if (in_array($key, $keys)){
		    continue;
		}
	    }

	    $keys[] = $key;
	    $elements[] = $value;
	    
	    if (count($elements) == $length){
		yield $elements;
	    }
	    else{
		foreach ($this->x_calculatePermutations($length, $with_repetition, $elements, $keys) as $value2){
		    yield $value2;
		}
	    }
	    
	    array_pop($keys);
	    array_pop($elements);
	}
    }
}

/*
 * Convierte un número de segundos en una cadena legible para humanos con unidades
 *
 */
function timer_unidades( $t ) {

    $t = floor($t);
    $unidad = "";
    if ( $t > 24*60*60 ) {
	$format = "d H:i:s";
    } else if ( $t > 60*60 ) {
	$format = "H:i:s";
    } else if ( $t > 120 ) {
	$format = "i:s";
    } else {
	$format = "U";
	if ( $t > 1 ) {
	    $unidad = "segundos";
	} else {
	    $unidad = "segundo";
	}
    }

    $timer_string = date($format, $t);

    return $timer_string . " " . $unidad;
}

/**
 * Returns the area of a closed path on Earth.
 * @param path A closed path.
 * @return The path's area in square kilometers.
 */
function computeArea($path) {
    return abs(computeSignedArea($path)/1000000.0);
}

/**
 * Returns the signed area of a closed path on Earth. The sign of the area may be used to
 * determine the orientation of the path.
 * "inside" is the surface that does not contain the South Pole.
 * @param path A closed path.
 * @return The loop's area in square meters.
 */
function computeSignedArea($path) {
    return computeSignedAreaP($path, RADIO_TERRESTRE);
}

/**
 * Returns the signed area of a closed path on a sphere of given radius.
 * The computed area uses the same units as the radius squared.
 * Used by SphericalUtilTest.
 */
function computeSignedAreaP($path,  $radius) {
        $size = count($path);
        if ($size < 3) { return 0; }
        $total = 0;
        $prev = $path[$size - 1];
        $prevTanLat = tan((M_PI / 2 - deg2rad($prev[0])) / 2); // lat
        $prevLng = deg2rad($prev[1]); //lon
        // For each edge, accumulate the signed area of the triangle formed by the North Pole
        // and that edge ("polar triangle").
        foreach($path as $point) {
            $tanLat = tan((M_PI / 2 - deg2rad($point[0])) / 2); // lat
            $lng = deg2rad($point[1]); // lon
            $total += polarTriangleArea($tanLat, $lng, $prevTanLat, $prevLng);
            $prevTanLat = $tanLat;
            $prevLng = $lng;
        }
        return $total * ($radius * $radius);
    }

/**
 * Returns the signed area of a triangle which has North Pole as a vertex.
 * Formula derived from "Area of a spherical triangle given two edges and the included angle"
 * as per "Spherical Trigonometry" by Todhunter, page 71, section 103, point 2.
 * See http://books.google.com/books?id=3uBHAAAAIAAJ&pg=PA71
 * The arguments named "tan" are tan((pi/2 - latitude)/2).
 */
function polarTriangleArea($tan1,  $lng1, $tan2, $lng2) {
        $deltaLng = $lng1 - $lng2;
        $t = $tan1 * $tan2;
        return 2 * atan2($t * sin($deltaLng), 1 + $t * cos($deltaLng));
}
