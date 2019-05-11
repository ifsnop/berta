<?php

/**
* Funcion que crea una malla de cobertura global a partir de la cobertura individual
* de las mallas incluidas en $malla y crea el kml correspondiente, con distintos 
* colores dependiendo del tipo de cobertura (simple, doble, triple, cuadruple, etc).
*
* @param array $mallas arrays de mallas con coberturas (ENTRADA)
* @param int $fl nivel de vuelo seleccionado (ENTRADA)
* @param string $ruta donde se genera el archivo(ENTRADA)
* @param string $altMode si lo queres absolute o relative(ENTRADA)
*/
         
function multicobertura($mallas, $fl, $ruta, $altMode) {

    if ( !isset($mallas) || count($mallas) == 0 )
        return false;

    $nivelVuelo = str_pad( (string)$fl, 3, "0", STR_PAD_LEFT );
    $flm = $fl*100*FEET_TO_METERS; // fl en metros para calculoCoordenadasGeograficasX
    $coverageName = array(
        1 => "mono", "doble", "triple",
        "cuadruple", "quintuple", "sextuple",
        "septuple", "octuple", "nonuple"
    );
    $bounding = calculaBoundingBox($mallas);
    print "malla normal=>" . printBoundingBox($bounding);
    // guardamos mallas por separado como imágenes para comprobar
    foreach($mallas as $radar => $malla) {
        storeMallaAsImage3($malla, 'malla_' . $radar . '_FL' . $nivelVuelo, $bounding);
    }

    // ojo, dentro se generará la malla global, vacía, preparada para calcular
    $mallas = interpolaHuecos($mallas, $bounding);

    $b = calculaBoundingBox($mallas);
    print "malla interp=>" . printBoundingBox($b);
    // guardamos mallas sin huecos por separado como imágenes para comprobar
    foreach($mallas as $radar => $malla) {
        if ( 'global' != $radar )
            storeMallaAsImage3($malla, 'mallainterp_' . $radar . '_FL' . $nivelVuelo, $bounding);
    }

    list($malla_global, $indexes, $maxCoverage) = calculaMallaGlobal($mallas, $bounding);
    $b = calculaBoundingBox(array('global'=>$malla_global));
    print "malla interp=>" . printBoundingBox($b);
    print "INFO maxCoverage: $maxCoverage/" . $coverageName[$maxCoverage] . PHP_EOL;

    // guardamos malla global como imagen para comprobar
    storeMallaAsImage3($malla_global, 'mallaglobal_' . $radar . '_FL' . $nivelVuelo, $bounding);
    print "INFO Uso memoria: " . convertBytes(memory_get_usage(false)) . " " .
        "Pico uso memoria: " . convertBytes(memory_get_peak_usage(false)) . PHP_EOL;
    print "[liberandoMemoria]" . PHP_EOL;
    unset($mallas);
    print "INFO Uso memoria: " . convertBytes(memory_get_usage(false)) . " " .
        "Pico uso memoria: " . convertBytes(memory_get_peak_usage(false)) . PHP_EOL;

    print "[check coverage overflow "; $timer0 = microtime(true);
    checkCoverageOverflow($malla_global);
    printf(" %3.4fs]", microtime(true) - $timer0);
    // preparados para generar contornos

/*
    $y = $x = $d = array();
    for ($i = 0; $i < count($malla_global); $i++) {
	for ($j = 0; $j < count($malla_global[$bounding['lat_max']]); $j++) {
	    $y[$j] = ($bounding['lon_min'] + $j) / REDONDEO_LATLON;
	    $x[$i] = ($bounding['lat_min'] + $i) / REDONDEO_LATLON;
	    $d[$i][$j] = $malla_global[$bounding['lat_min'] + $i][$bounding['lon_min'] + $j];
	}
    }
*/

    for($coverageLevel = 1; $coverageLevel <= $maxCoverage; $coverageLevel++) {
        print "================================================" . PHP_EOL;
        print "Cobertura: $coverageLevel/" . $coverageName[$coverageLevel] . PHP_EOL;
        $malla = obtieneMalla($malla_global, $coverageLevel);
        storeMallaAsImage3($malla, 'malla_' . $coverageLevel . '_FL' . $nivelVuelo, $bounding);

        $b = calculaBoundingBox(array('global'=>$malla));
        print "malla nivel=>" . printBoundingBox($b);

        print "[determinaContornos2 start]"; $timer0 = microtime(true);
        $listaContornos2 = determinaContornos2($malla);
        if ( 0 == count($listaContornos2) ) {
            print PHP_EOL . "INFO: No se genera KML/PNG/TXT porque no existe cobertura al nivel de vuelo FL" . $nivelVuelo . PHP_EOL;
        }
        printf("[determinaContornos2 ended %3.4fs]", microtime(true) - $timer0);
        print "[calculaCoordenadasGeograficasC]";
        $listaContornos2 = calculaCoordenadasGeograficasC($flm, $listaContornos2);

        creaKml2(
            $listaContornos2,
            "prueba_" . $coverageName[$coverageLevel], /* faltaría poner aquí los nombres de los radares que han proporcionado esta cobertura */
            $ruta,
            $fl,
            $altMode,
            $appendToFilename = '',
            $coverageName[$coverageLevel]
        );
    }

/*
    $contours = CONREC_contour($d,$x,$y,$numContornos = $maxCoverage);

    for ($i = 0; $i < $maxCoverage; $i++) {
	$c = $contours[$i];

	$listaContornos = determinaContornos2_joinContornos($c);
                    
	$listaContornosCount = count($listaContornos);
	$listaContornos = determinaContornos2_sortContornos($listaContornos);
                            
	$assertListaContornosCount = count($listaContornos);
	foreach( $listaContornos as $k => $l ) {
	    // print $k . "] " . count($l['polygon']) . PHP_EOL;
	    // print "\t level:" . $l['level'] . PHP_EOL;
	    // print "\t inside:" . count($l['inside']) . PHP_EOL;
	    $assertListaContornosCount += count($l['inside']);
	}

	print "[assert listaContornos: " . $listaContornosCount . "=?" . $assertListaContornosCount . "]";
	if ( $listaContornosCount != $assertListaContornosCount ) {
	    die("ERROR al reindexar los contornos" . PHP_EOL);
	}
        // ojo, deberíamos modificar creaKml2 de acuerdo al código de javi
	creaKml2(
	    $listaContornos,
	    $cobertura[$i], // faltaría poner aquí los nombres de los radares que han proporcionado esta cobertura 
	    $ruta,
	    $fl,
	    $altMode,
	    $appendToFilename = '',
	    $coverageLevel = 'mono'
	);
    }
    */
}

/*
 * calcula los límites máximos y mínimos en latitud y longitud de todas
 * las mallas, necesarios para dimensionar la malla global.
 * ojo porque va a haber cobertura en el marco, y eso hace que los
 * contornos no funcionen, habrá que hacer un +1 para cada valor del
 * bounding box. No es tan fácil porque no sabemos cual es el siguiente
 * valor.
 * IMPORTANTE: Las mallas tienen que estar ordenadas, y por defecto
 * no se generan así. En "generacionMalladoLatLon" se hace un ksort
 * de las filas y las columnas.
 *
 * @param array $mallas array con las mallas individuales
 * @return array límites máximos y mínimos para latitud y longitud
 */
function calculaBoundingBox($mallas) {
    $timer0 = microtime(true);

    $lat_dupes = $lon_dupes = array();
    $lats_maxmin = $lons_maxmin = array();
    print "[calculaBoundingBox";
    foreach ($mallas as $radar => $malla) {
        print " $radar";
        // hay que buscar la latitud y la longitud máxima y mínima
        // de cada malla que tenga valor

        // en $lats_keys y $lons_keys están todos los valores
        // de lat y lon que tienen valores para la malla
        // hay que sumarle uno al máximo y restarle uno al
        // mínimo.
        $lats_keys = array_keys($malla);
        foreach ($malla as $lat => $lons) {
            $lons_keys = array_keys($lons);
            foreach ($lons as $lon => $hayCobertura) {
                if ( 0 != $hayCobertura ) {
                    // antes simplemente se guardaba la posición del punto con cobertura
                    // para buscar de todos esos puntos, los más extremos
                    // $lats_maxmin[$lat] = true; $lons_maxmin[] = $lon;
                    // pero necesitamos buscar extremos que estén por fuera
                    
                    // como hay que dejar un marco, de cada punto con valor guardamos el anterior
                    // y el siguiente, para asegurar que cuando busquemos el valor máximo o mínimo
                    // habrá siempre un punto sin valor

                    if ( !isset($lat_dupes[$lat]) ) {
                        $lat_dupes[$lat] = true;
                        $lat_index_next = array_search($lat, $lats_keys) + 1;
                        $lat_index_prev = array_search($lat, $lats_keys) - 1;
                        $lats_maxmin[$lats_keys[$lat_index_next]] = true;
                        $lats_maxmin[$lats_keys[$lat_index_prev]] = true;
                        // print "lat: $lat lat_index_next: $lat_index_next lat_index_prev: $lat_index_prev" . PHP_EOL;
                        // print "DEBUG: " . $lats_keys[$lat_index_prev] . " " . $lat . " " . $lats_keys[$lat_index_next] . PHP_EOL;
                    }
                    if ( !isset($lon_dupes[$lon]) ) {
                        $lon_dupes[$lon] = true;
                        $lon_index_next = array_search($lon, $lons_keys) + 1;
                        $lon_index_prev = array_search($lon, $lons_keys) - 1;                    
                        $lons_maxmin[$lons_keys[$lon_index_next]] = true;
                        $lons_maxmin[$lons_keys[$lon_index_prev]] = true;
                        // print "DEBUG: " . $lons_keys[$lon_index_prev] . " " . $lon . " " . $lons_keys[$lon_index_next] . PHP_EOL;
                    }

                }
            }
        }
    }
    print " " . round(microtime(true) - $timer0, 4) . "s]" . PHP_EOL;

    if ( count($lats_maxmin) == 0 || count($lons_maxmin) == 0 )
        return false;

    return array(
        'lat_max' => max(array_keys($lats_maxmin)),
        'lat_min' => min(array_keys($lats_maxmin)),
        'lon_max' => max(array_keys($lons_maxmin)),
        'lon_min' => min(array_keys($lons_maxmin)),
    );
}

/*
 * Rellenamos las mallas parciales para eliminar huecos
 * una especie de interpolación (copiamos el valor siguiente o
 * inferior). Podrían buscarse otras formas mejores pero esta
 * funciona bien.
 * Además generamos una malla global vacía
 * @param array mallas
 * @param array bounding límites globales para lat/lon
 */
function interpolaHuecos($mallas, $bounding) {

    $timer0 = microtime(true);
    $mallas['global'] = array();
    print "[interpolaHuecos";
    foreach( $mallas as $radar => $malla ) {
        print " $radar";
        for( $i = $bounding['lat_max']; $i >= $bounding['lat_min']; $i-- ) { // si lo hacemos de menos a mas, no interpolamos todos los huecos
        //for( $i = $bounding['lat_min']; $i <= $bounding['lat_max']; $i++ ) { // no sabemos por qué @jslorenzo lo hizo así
            for( $j = $bounding['lon_min']; $j <= $bounding['lon_max']; $j++ ) {
                if ( 'global' == $radar ) {
                    $malla[$i][$j] = 0;
                    continue;
                }
                if ( !isset($malla[$i][$j]) ) { // si no existe valor
                    if ( isset($malla[$i][$j+1]) ) { // pero existe valor a la derecha
                        $malla[$i][$j] = $malla[$i][$j+1]; // copiamos
                    } elseif ( isset($malla[$i+1][$j]) ) { // sino, pero existe debajo
                        $malla[$i][$j] = $malla[$i+1][$j];
                    } else {
                        $malla[$i][$j] = 0;
                    }
                }
            }
        }
        $mallas[$radar] = $malla;
    }
    print " " . round(microtime(true)-$timer0,3) . "s]" . PHP_EOL;
    return $mallas;
}

/*
 * Suma las mallas (asignando pesos a los radares) para generar una
 * cobertura global.
 * @param array mallas
 * @param array bounding
 * @param array malla global, array de lugares con pesos en potencias de dos, máximo solape de cobertura
 */
function calculaMallaGlobal($mallas, $bounding) {

    // ya nos ha llegado una malla global metida en mallas
    $timer0 = microtime(true);
    $ptr = 1;
    $indexes = array();
    $maxCoverage = 0;
    print "[calculaMallaGlobal";
    foreach( $mallas as $radar => $malla ) {
        print " $radar";
        if ( 'global' == $radar )
            continue;
        $indexes[$radar] = $ptr;
        // for( $i = $bounding['lat_max']; $i >= $bounding['lat_min']; $i-- ) { // no sabemos por qué @jslorenzo hizo esto
        for( $i = $bounding['lat_min']; $i <= $bounding['lat_max']; $i++ ) {
            for( $j = $bounding['lon_min']; $j <= $bounding['lon_max']; $j++ ) {
                if ( !isset($malla[$i][$j]) ) { // si no existe valor
                    die("ERROR no exista valor i:$i j:$j en malla $radar");
                }
                if ( !isset($mallas['global'][$i][$j]) ) {
                    die("ERROR no existe valor i:$i j:$j en malla_global");
                }
                $mallas['global'][$i][$j] += $malla[$i][$j]*$ptr;
                $maxCoverage = max($mallas['global'][$i][$j], $maxCoverage);
            }
        }
        $mallas[$radar] = $malla;
        $ptr *= 2;
    }
    print " " . round(microtime(true)-$timer0,3) . "s]" . PHP_EOL;
    return array($mallas['global'], $indexes, countSetBits($maxCoverage));
}

/*
 * extrae de una malla, los elementos con nivel de cobertura seleccionado
 * @param array $malla
 * @param int $level nivel de cobertura
 * @return array malla filtrada
 */
function obtieneMalla($malla, $level) {
    $m = array();
    $cache_bits = array();
    $cache_values = array();
    foreach($malla as $lat => $lons) {
        $m[$lat] = array();
        foreach($lons as $lon => $value) {
            if ( !isset($cache_bits[countSetBits($value)]) ) {
                print "bits:" . countSetBits($value) . PHP_EOL;
                $cache_bits[countSetBits($value)] = true;
            }
            if ( !isset($cache_values[$value]) ) {
                print "values:" . $value . PHP_EOL;
                $cache_values[$value] = true;
            }
            $m[$lat][$lon] = (countSetBits($value) == $level) ? 1 : 0;
        }
    }
    return $m;
}

/*
 * imprime los valores de las cajas de límites
 * @param array $bounding caja con los límites
 * @return string cadena con los límites formateados
 */
function printBoundingBox($bounding) {

    $ret = "INFO " .
        "lat_max: " . $bounding['lat_max'] . " " .
        "lon_max: " . $bounding['lon_max'] . " " .
        "lat_min: " . $bounding['lat_min'] . " " .
        "lon_min: " . $bounding['lon_min'] .
        PHP_EOL .
        "INFO " .
        "lat_max: " . round($bounding['lat_max']/REDONDEO_LATLON,3) . " " .
        "lon_max: " . round($bounding['lon_max']/REDONDEO_LATLON,3) . " " .
        "lat_min: " . round($bounding['lat_min']/REDONDEO_LATLON,3) . " " .
        "lon_min: " . round($bounding['lon_min']/REDONDEO_LATLON,3) .
        PHP_EOL;
    return $ret;
}