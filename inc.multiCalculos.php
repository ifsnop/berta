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

    if ( !isset($mallas) || count($mallas) == 0 ) {
        return false;
    }

    $nivelVuelo = str_pad( (string)$fl, 3, "0", STR_PAD_LEFT );
    $flm = $fl*100*FEET_TO_METERS; // fl en metros para calculoCoordenadasGeograficasX
    $coverageName = array(
        1 => "mono", "doble", "triple",
        "cuadruple", "quintuple", "sextuple",
        "septuple", "octuple", "nonuple"
    );
    $radares = array_keys($mallas);
    $radaresName = implode(" ", $radares);
    $radaresCount = count($mallas);

    $bounding = calculaBoundingBox($mallas);
    print "DEBUG boundingBox malla normal: " . printBoundingBox($bounding) . PHP_EOL;
    // guardamos mallas por separado como imágenes para comprobar
    foreach($mallas as $radar => $malla) {
        storeMallaAsImage3($malla, 'malla_' . $radar . '_FL' . $nivelVuelo, $bounding);
    }

    // ojo, dentro se generará la malla global, vacía, preparada para calcular
    $mallas = interpolaHuecos2($mallas, $bounding);
    $mallas = interpolaHuecos2($mallas, $bounding);
    $mallas = eliminaHuecos($mallas, $bounding);
    $b = calculaBoundingBox($mallas);
    // print_r($bounding);
    // guardamos mallas sin huecos por separado como imágenes para comprobar
    foreach($mallas as $radar => $malla) {
        if ( 'global' != $radar )
            storeMallaAsImage3($malla, 'mallainterp_' . $radar . '_FL' . $nivelVuelo, $bounding);
    }

    list($malla_global, $indexes, $maxCoverage) = calculaMallaGlobal($mallas, $bounding);
    $b = calculaBoundingBox(array('global'=>$malla_global));
    print "DEBUG boundingBox malla global: " . printBoundingBox($b) . PHP_EOL;
    print "INFO maxCoverage: $maxCoverage/" . $coverageName[$maxCoverage] . PHP_EOL;

    // guardamos malla global como imagen para comprobar
    storeMallaAsImage3($malla_global, 'mallaglobal_' . $radar . '_FL' . $nivelVuelo, $bounding);
    //print "INFO Uso memoria: " . convertBytes(memory_get_usage(false)) . " " .
    //    "Pico uso memoria: " . convertBytes(memory_get_peak_usage(false)) . PHP_EOL;
    print "[liberandoMemoria]" . PHP_EOL;
    unset($mallas);
    //print "INFO Uso memoria: " . convertBytes(memory_get_usage(false)) . " " .
    //    "Pico uso memoria: " . convertBytes(memory_get_peak_usage(false)) . PHP_EOL;

    print "[check coverage overflow "; $timer0 = microtime(true);
    checkCoverageOverflow($malla_global);
    printf(" %3.4fs]", microtime(true) - $timer0);
    // preparados para generar contornos

    // coverage level irá pasando por todas las combinaciones de radares
    for($coverageLevel = 1; $coverageLevel <= pow(2,$radaresCount)-1; $coverageLevel++) {
        print PHP_EOL . "================================================" . PHP_EOL;
        print "INFO $coverageLevel => " . $coverageName[countSetBits($coverageLevel)] . " =>";
        // buscamos los radares que han influido en este nivel de cobertura para el nombre del fichero
        $radaresUsed = array();
        for($i = $coverageLevel, $radarPtr = 0; $i>0; $radarPtr++, $i=$i>>1) {
            if ( $i & 1 ) {
                //$radaresUsed[$radares[$radarPtr]] = true;
                $radaresUsed[] = $radares[$radarPtr];
                print " " . $radares[$radarPtr];
            }
            // else { $radarsUsed[$radares[$radarPtr]] = false;  // print "No utilizado: " . $radares[$radarPtr] . PHP_EOL; }
        }
        print PHP_EOL;

        $malla = obtieneMalla($malla_global, $coverageLevel);
        if ( false === $malla ) {
            print "INFO: No se genera KML/PNG/TXT porque no existe cobertura al nivel de vuelo FL" . $nivelVuelo . PHP_EOL;
            continue;
        }
        storeMallaAsImage3($malla, 'malla_' . str_pad( $coverageLevel , 3, "0", STR_PAD_LEFT ) . '-FL' . $nivelVuelo, $bounding);

        $b = calculaBoundingBox(array('global'=>$malla));
        print "DEBUG boundingBox malla nivel $coverageLevel: " . printBoundingBox($b) . PHP_EOL;

        print "[determinaContornos2 start]"; $timer0 = microtime(true);
        $listaContornos2 = determinaContornos2($malla);
        if ( 0 == count($listaContornos2) ) {
            print "INFO: No se genera KML/PNG/TXT porque no existe cobertura al nivel de vuelo FL" . $nivelVuelo . PHP_EOL;
            continue;
        }
        printf("[determinaContornos2 ended %3.4fs]", microtime(true) - $timer0);
        print "[calculaCoordenadasGeograficasC]" . PHP_EOL;
        $listaContornos2 = calculaCoordenadasGeograficasC($flm, $listaContornos2);

        creaKml2(
            $listaContornos2,
            $radares,
            $ruta,
            $fl,
            $altMode,
            $appendToFilename = $radaresUsed,
            $coverageName[countSetBits($coverageLevel) < 5 ? countSetBits($coverageLevel) : 4]
        );
    }

    return true;
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
    $lat_sup = -999999999; $lat_inf = 999999999;
    $lon_lef = 999999999; $lon_rig = -999999999;
    foreach ($mallas as $radar => $malla) {
        print " $radar";
        // hay que buscar la latitud y la longitud máxima y mínima
        // de cada malla que tenga valor

        // en $lats_keys y $lons_keys están todos los valores
        // de lat y lon que tienen valores para la malla
        // hay que sumarle uno al máximo y restarle uno al
        // mínimo.
        $lats_keys = array_keys($malla);
        $lat_sup = max(array_merge($lats_keys, array($lat_sup)));
        $lat_inf = min(array_merge($lats_keys, array($lat_inf)));
        foreach ($malla as $lat => $lons) {
            $lons_keys = array_keys($lons);
            $lon_lef = min(array_merge($lons_keys, array($lon_lef)));
            $lon_rig = max(array_merge($lons_keys, array($lon_rig)));
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
                        if ( isset($lats_keys[$lat_index_next]) )
                            $lats_maxmin[$lats_keys[$lat_index_next]] = true;
                        if ( isset($lats_keys[$lat_index_prev]) )
                            $lats_maxmin[$lats_keys[$lat_index_prev]] = true;
                        // print "lat: $lat lat_index_next: $lat_index_next lat_index_prev: $lat_index_prev" . PHP_EOL;
                        // print "DEBUG: " . $lats_keys[$lat_index_prev] . " " . $lat . " " . $lats_keys[$lat_index_next] . PHP_EOL;
                    }
                    if ( !isset($lon_dupes[$lon]) ) {
                        $lon_dupes[$lon] = true;
                        $lon_index_next = array_search($lon, $lons_keys) + 1;
                        $lon_index_prev = array_search($lon, $lons_keys) - 1;                    
                        if ( isset($lons_keys[$lon_index_next]) )
                            $lons_maxmin[$lons_keys[$lon_index_next]] = true;
                        if ( isset($lons_keys[$lon_index_prev]) )
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
        'lon_max' => max(array_keys($lons_maxmin)),
        'lat_min' => min(array_keys($lats_maxmin)),
        'lon_min' => min(array_keys($lons_maxmin)),
        'lat_sup' => $lat_sup,
        'lon_rig' => $lon_rig,
        'lat_inf' => $lat_inf,
        'lon_lef' => $lon_lef,
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
        // for( $i = $bounding['lat_min']; $i <= $bounding['lat_max']; $i++ ) // no sabemos por qué @jslorenzo lo hizo así
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
 * Rellenamos las mallas parciales para eliminar huecos
 * una especie de interpolación (copiamos el valor siguiente o
 * inferior). Podrían buscarse otras formas mejores pero esta
 * funciona bien.
 * Además generamos una malla global vacía
 * @param array mallas
 * @param array bounding límites globales para lat/lon
 */
function interpolaHuecos2($mallas, $bounding) {

    $timer0 = microtime(true);
    $mallas['global'] = array();
    print "[interpolaHuecos";
    foreach( $mallas as $radar => $malla ) {
        print " $radar";
        //for( $i = $bounding['lat_max']; $i >= $bounding['lat_min']; $i-- ) { // si lo hacemos de menos a mas, no interpolamos todos los huecos
        for( $i = $bounding['lat_min']; $i <= $bounding['lat_max']; $i++ ) { // no sabemos por qué @jslorenzo lo hizo así
            for( $j = $bounding['lon_min']; $j <= $bounding['lon_max']; $j++ ) {
                if ( 'global' == $radar ) {
                    $malla[$i][$j] = 0;
                    continue;
                }
                if ( !isset($malla[$i][$j]) ) { // si no existe valor
                    if ( isset($malla[$i][$j+1]) && 
                         isset($malla[$i][$j-1]) &&
                         1 == $malla[$i][$j+1] &&
                         1 == $malla[$i][$j-1] ) { // pero existe valor a la derecha e izquierda
                        $malla[$i][$j] = 1; //$malla[$i][$j+1]; // copiamos
                    } elseif ( isset($malla[$i+1][$j]) &&
                         isset($malla[$i-1][$j]) &&
                         1 == $malla[$i+1][$j] &&
                         1 == $malla[$i-1][$j] ) { // sino, pero existe debajo
                        $malla[$i][$j] = 1; //$malla[$i+1][$j];
/*                    } elseif ( isset($malla[$i+1][$j+1]) &&// si existe en las diagonales
                        isset($malla[$i+1][$j-1]) &&
                        isset($malla[$i-1][$j+1]) &&
                        isset($malla[$i-1][$j-1]) &&
                         1 == $malla[$i+1][$j+1] &&
                         1 == $malla[$i+1][$j-1] &&
                         1 == $malla[$i-1][$j+1] &&
                         1 == $malla[$i-1][$j-1] ) {
                        $malla[$i][$j] = 1; //$malla[$i+1][$j];
*/
                //    } else {
                //        $malla[$i][$j] = 0;
                    }
                }
/*                if ( 0 == $malla[$i][$j] ) { // si no existe valor
                    if ( isset($malla[$i][$j+1]) && 
                         isset($malla[$i][$j-1]) &&
                         1 == $malla[$i][$j+1] &&
                         1 == $malla[$i][$j-1] ) { // pero existe valor a la derecha e izquierda
                        $malla[$i][$j] = 1; //$malla[$i][$j+1]; // copiamos
                    } elseif ( isset($malla[$i+1][$j]) &&
                         isset($malla[$i-1][$j]) &&
                         1 == $malla[$i+1][$j] &&
                         1 == $malla[$i-1][$j] ) { // sino, pero existe debajo
                        $malla[$i][$j] = 1; //$malla[$i+1][$j];
                    } else {
                        $malla[$i][$j] = 0;
                    }
                }*/
            }
        }
        $mallas[$radar] = $malla;
    }
    print " " . round(microtime(true)-$timer0,3) . "s]" . PHP_EOL;
    return $mallas;
}
/*
 * Sustituye los elementos sin definir en la matriz por puntos sin cobertura
 * @param array mallas
 * @param array bounding límites globales para lat/lon
 */
function eliminaHuecos($mallas, $bounding) {

    $timer0 = microtime(true);
    print "[eliminaHuecos";
    foreach( $mallas as $radar => $malla ) {
        print " $radar";
        //for( $i = $bounding['lat_max']; $i >= $bounding['lat_min']; $i-- ) { // si lo hacemos de menos a mas, no interpolamos todos los huecos
        for( $i = $bounding['lat_min']; $i <= $bounding['lat_max']; $i++ ) { // no sabemos por qué @jslorenzo lo hizo así
            for( $j = $bounding['lon_min']; $j <= $bounding['lon_max']; $j++ ) {
                if ( 'global' == $radar ) {
                    continue;
                }
                if ( !isset($malla[$i][$j]) ) { // si no existe valor
                    $malla[$i][$j] = 0;
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
        // for( $i = $bounding['lat_max']; $i >= $bounding['lat_min']; $i-- ) // no sabemos por qué @jslorenzo hizo esto
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
 * Extrae de una malla, los elementos con nivel de cobertura seleccionado.
 * (mono, doble, triple, etc)
 * La nueva malla tendrá 1 para las zonas de cobertura y 0 para las zonas
 * de no cobertura.
 * @param array $malla
 * @param int $level nivel de cobertura
 * @return array malla filtrada
 */
function obtieneMallaCobertura($malla, $level) {
    $m = array();
    $cache_bits = array();
    $cache_values = array();
    $found = false;
    foreach($malla as $lat => $lons) {
        $m[$lat] = array();
        foreach($lons as $lon => $value) {
            if ( !isset($cache_bits[countSetBits($value)]) ) {
                // print "bits:" . countSetBits($value) . PHP_EOL;
                $cache_bits[countSetBits($value)] = true;
            }
            if ( !isset($cache_values[$value]) ) {
                // print "values:" . $value . PHP_EOL;
                $cache_values[$value] = true;
            }
            // $m[$lat][$lon] = (countSetBits($value) == $level) ? 1 : 0;
            if ( countSetBits($value) == $level ) {
                $m[$lat][$lon] = 1;
                $found = true;
            } else {
                $found = false;
            }

        }
    }
    if ( $found ) { return $m; } else { return false; }
}

/*
 * Extrae de una malla, las celdas con cobertura de los radares seleccionados.
 * 1 -> el primer radar, 2 -> el segundo radar, 3-> el primer y segundo
 * radar activos.
 * La nueva malla tendrá 1 para las zonas de cobertura y 0 para las zonas
 * de no cobertura.
 * @param array $malla
 * @param int $level radar(es) seleccionados
 * @return array malla filtrada según radares seleccionados
 */
function obtieneMalla($malla, $radarBits) {
    $m = array(); $found = false;
    foreach($malla as $lat => $lons) {
        $m[$lat] = array();
        foreach($lons as $lon => $value) {
            // $m[$lat][$lon] = ($radarBits == $value) ? 1 : 0;
            if ( $radarBits == $value ) {
                $m[$lat][$lon] = 1;
                $found = true;
            } else {
                $m[$lat][$lon] = 0;
            }
        }
    }
    if ( $found ) { return $m; } else { return false; }
}

/*
 * imprime los valores de las cajas de límites
 * @param array $bounding caja con los límites
 * @return string cadena con los límites formateados
 */
function printBoundingBox($bounding) {
    $ret = "" .
        "lat_max:" . $bounding['lat_max'] . "c (" . round($bounding['lat_max']/REDONDEO_LATLON,3) . "º) " .
        "lon_max:" . $bounding['lon_max'] . "c (" . round($bounding['lon_max']/REDONDEO_LATLON,3) . "º) " .
        "lat_min:" . $bounding['lat_min'] . "c (" . round($bounding['lat_min']/REDONDEO_LATLON,3) . "º) " .
        "lon_min:" . $bounding['lon_min'] . "c (" . round($bounding['lon_min']/REDONDEO_LATLON,3) . "º)";
    return $ret;
}
