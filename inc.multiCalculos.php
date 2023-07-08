<?php
/**
 * Helper para convertir los contornos que generamos (lat,lon) en una lista
 * de vértices (0=>lat, 1=>lon) que espera MartinezRueda.
 *
 */
function get_vertex($arr) {
    $p = array();
    foreach($arr as $v) {
	$p[] = array($v['lat'], $v['lon']); // 100.0
    }
    return $p;
}

/**
* Funcion que crea una malla de cobertura global a partir de la cobertura individual
* de las mallas incluidas en $malla y crea el kml correspondiente, con distintos
* colores dependiendo del tipo de cobertura (simple, doble, triple, cuadruple, etc).
*
* @param array $coberturas arrays de mallas con coberturas para cada sensor(ENTRADA)
* @param string $nivelVuelo nivel de vuelo seleccionado (ENTRADA)
* @param string $ruta donde se genera el archivo(ENTRADA)
* @param string $altMode si lo queres absolute o relative(ENTRADA)
*/

function multicobertura($coberturas, $nivelVuelo, $ruta, $altMode, $calculoMode) { // , $calculosMode = array('parcial' => true, 'rascal'=>true, 'unica' => true) ) {

    $debug = false;

    if ( !isset($coberturas) || count($coberturas) == 0 ) {
        return false;
    }


    $coverageName = array( 0 => "ninguna",
        "mono", "doble", "triple",
        "cuadruple", "quintuple", "sextuple",
        "septuple", "octuple", "nonuple",
        "decuplo", "undecuplo", "duodecuplo",
        "terciodecuplo",
    );
    $radares = array();
    $mr_polygon = array();
    $timer = microtime(true);
    foreach ($coberturas as $radar => $contornos_por_radar) {
	if ( false === $contornos_por_radar['contornos'] )
	    continue;
	$radares[] = $radar;
	$polygons = array();

	foreach($contornos_por_radar['contornos'] as $indice => $contorno) {
	    $polygons[] = get_vertex($contorno['polygon']);
	    //$polygons[] = ramer_douglas_peucker($polygon, 0.001);
	    if ( !isset($contorno['inside']) )
		continue;
	    foreach($contorno['inside'] as $indice_inside => $contorno_inside) {
		$polygons[] = get_vertex($contorno_inside['polygon']);
	    }
	}
	$mr_polygon[$radar] = new \MartinezRueda\Polygon($polygons);
    }
    sort($radares);

    if ( 1 >= count($radares) ) {
	logger(" E> No existen coberturas suficientes para seguir calculando");
	return false;
    }

    if ( isset($calculoMode['multiradar_unica']) && true === $calculoMode['multiradar_unica'] ) {
	logger(" I> Creando cobertura única/suma");

	$result_suma = new \MartinezRueda\Polygon(array());

	foreach($mr_polygon as $k => $p) {
	    logger(" D> Añadiendo {$k} al cálculo");
	    $mr_algorithm = new \MartinezRueda\Algorithm();
	    $result_suma = $mr_algorithm->getUnion(
		$result_suma,
		$p
	    );
	}

	$result_arr = $result_suma->toArray();
	$listaContornos = genera_contornos($result_arr);
	creaKml2(
	    $listaContornos,
	    $radares, //$radares,
	    $ruta,
	    $nivelVuelo,
	    $altMode,
	    $appendToFilename = "",
	    $coverageLevel = "unica"
	);
	logger(" V> Finalizado en " . round(microtime(true) - $timer,3) . " segundos");
	return true;
    }

    $vsr = array(); // variaciones sin repetición
    $vsr_count = 0;
    for($i = 1; $i<=count($radares); $i++) {
	$combinations = new combinations($radares);
	$vsr[$i] = $combinations->getCombinations($i, false);
	$vsr_count += count($vsr[$i]);
	// print_r($vsr[$i]);
    }
    // print_r($vsr);
    logger(" D> " . $vsr_count . " estructuras generadas en " . round(microtime(true) - $timer, 3) . " segundos");

    $radares_interseccion_cache = array();
    $radares_resta_cache = array();
    $radares_suma_cache = array();

    // cacheo de intersecciones y sumas
    $count = 0;
    foreach($vsr as $numero_solape => $grupo_solape) {
	if ( $numero_solape >= count($coverageName) ) {
	    $coverageName_fixed = "de más de {$numero_solape}";
	} else {
	    $coverageName_fixed = $coverageName[$numero_solape];
	}
	logger(" N> == Calculando cobertura $coverageName_fixed");

	foreach($grupo_solape as $grupo_radares) {
	    logger (" D> " . "Info memory_usage(" . convertBytes(memory_get_usage(false)) . ") " .
		"Memory_peak_usage(" . convertBytes(memory_get_peak_usage(false)) . ")");
	    logger( " N> $count/$vsr_count");
	    $count++;

	    if ( $debug) {
		foreach($radares_interseccion_cache as $r => $d) {
		    print "($numero_solape) r: $r md5:" . md5(serialize($d)) . PHP_EOL;
		}
	    }

	    $count_grupo_radares = count($grupo_radares);
	    $nombre_grupo_radares_suma = implode('+', $grupo_radares);
	    $nombre_grupo_radares_interseccion = implode('^', $grupo_radares);

	    // cacheamos en funcion de cuantos radares haya.
	    // si es solo uno, es directo
	    if ( $count_grupo_radares == 1 ) {

		$result_interseccion = clone $mr_polygon[$grupo_radares[0]];
		$result_suma = clone $mr_polygon[$grupo_radares[0]];

	    // si son dos radares, hay que coger los dos (serán los dos primeros)
	    } else if ( $count_grupo_radares == 2 ) { // estos nunca estarán en caché
		// PRIMERO CACHEAMOS LA INTERSECCION
		$mr_algorithm = new \MartinezRueda\Algorithm();
		$subject = clone $mr_polygon[$grupo_radares[0]];
		$clipping = clone $mr_polygon[$grupo_radares[1]];
		$result_interseccion = $mr_algorithm->getIntersection( $subject, $clipping );
		//$radares_interseccion_cache[$nombre_grupo_radares_interseccion] = $result_interseccion;
		// DEBUG
		if ( $debug) {
		    logger(" D> store interseccion_cache r: $nombre_grupo_radares_interseccion md5: " . md5(serialize($result_interseccion)));
		    $result_interseccion_arr = $result_interseccion->toArray();
		    $listaContornos = genera_contornos($result_interseccion_arr);
		    creaKml2(
			$listaContornos,
			$nombre_grupo_radares_interseccion, //$radares,
			$ruta,
			$nivelVuelo,
			$altMode,
			$appendToFilename = "_interseccionPRE",
			$coverageLevel = $coverageName[1]
		    );
		}

		// LUEGO CACHEAMOS LA SUMA
		$mr_algorithm = new \MartinezRueda\Algorithm();
		$subject = clone $mr_polygon[$grupo_radares[0]];
		$clipping = clone $mr_polygon[$grupo_radares[1]];
		$result_suma = $mr_algorithm->getUnion( $subject, $clipping );
		// $radares_suma_cache[$nombre_grupo_radares_suma] = $result_suma;
		// DEBUG
		if ( $debug ) {
		    logger(" V> store suma_cache r: $nombre_grupo_radares_suma md5: " . md5(serialize($result_suma)));
		    $result_suma_arr = $result_suma->toArray();
		    $listaContornos = genera_contornos($result_suma_arr);
		    creaKml2(
			$listaContornos,
			$nombre_grupo_radares_suma, //$radares,
			$ruta,
			$nivelVuelo,
			$altMode,
			$appendToFilename = "_sumaPRE",
			$coverageLevel = $coverageName[1]
		    );
		}

	    } else { // 3 o más
		// los anteriores ya están en la caché, sólo hay que calcular la suma/intersección con el nuevo
		// se cogen todos los radares menos el último y se generan dos listas, subgrupo y el resto.
		$subgrupo_radares = array_slice($grupo_radares,
		    0,
		    $count_grupo_radares - 1
		);
		// nombre del último radar, para la intersección
		$ultimo_radar = $grupo_radares[$count_grupo_radares - 1]; // resto
		$nombre_subgrupo_radares_interseccion = implode('^', $subgrupo_radares);
		$nombre_subgrupo_radares_suma = implode('+', $subgrupo_radares);

		$subject = clone $radares_interseccion_cache[$nombre_subgrupo_radares_interseccion];
		if ( $debug )
		    logger(" D> retrieve interseccion_cache r: $nombre_subgrupo_radares_interseccion md5: " . md5(serialize($subject)));
		$clipping = clone $mr_polygon[$ultimo_radar];
		$mr_algorithm = new \MartinezRueda\Algorithm();
		$result_interseccion = $mr_algorithm->getIntersection( $subject, $clipping );
		// $radares_interseccion_cache[$nombre_grupo_radares_interseccion] = $result_interseccion;
		// DEBUG
		if ( $debug )
		    logger(" D> store interseccion_cache r: $nombre_grupo_radares_interseccion md5: " . md5(serialize($result_interseccion)));


		$subject = clone $radares_suma_cache[$nombre_subgrupo_radares_suma];
		if ( $debug )
		    logger(" D> retrieve suma_cache r: $nombre_subgrupo_radares_suma md5: " . md5(serialize($subject)));
		$clipping = clone $mr_polygon[$ultimo_radar];
		$mr_algorithm = new \MartinezRueda\Algorithm();
		$result_suma = $mr_algorithm->getUnion( $subject, $clipping );
		//$radares_suma_cache[$nombre_grupo_radares_suma] = $result_suma;
		// DEBUG
		if ( $debug )
		    logger(" D> store suma_cache r: $nombre_grupo_radares_suma md5: " . md5(serialize($result_suma)));
	    }

	    $radares_interseccion_cache[$nombre_grupo_radares_interseccion] = $result_interseccion;
	    $radares_suma_cache[$nombre_grupo_radares_suma] = $result_suma;

	}
    }

    logger(" V> radares_suma_cache: " . implode(',', array_keys($radares_suma_cache)));
    logger(" V> radares_interseccion_cache: " . implode(',', array_keys($radares_interseccion_cache)));

    // ejecución
    $count = 0;
    foreach($vsr as $numero_solape => $grupo_solape) {
	if ( $numero_solape >= count($coverageName) ) {
	    $coverageName_fixed = "de más de {$numero_solape}";
	} else {
	    $coverageName_fixed = $coverageName[$numero_solape];
	}
	
	logger(" N> == Calculando cobertura $coverageName_fixed");

	foreach($grupo_solape as $grupo_radares) {

	    if ( $debug ) {
		foreach($radares_interseccion_cache as $r => $d) {
		    print "$r] " . md5(serialize($d)) . PHP_EOL;
		}
	    }

	    logger (" D> " . "Info memory_usage(" . convertBytes(memory_get_usage(false)) . ") " .
	    	"Memory_peak_usage(" . convertBytes(memory_get_peak_usage(false)) . ")");

	    logger( " N> $count/$vsr_count");
	    $count++;

	    $count_grupo_radares = count($grupo_radares);
	    $nombre_grupo_radares = implode(',', $grupo_radares);
	    $nombre_grupo_radares_interseccion = implode('^', $grupo_radares);
	    $grupo_radares_suma = array_values(array_diff($radares, $grupo_radares));
	    $count_grupo_radares_suma = count($grupo_radares_suma);
	    $nombre_grupo_radares_suma = implode('+', $grupo_radares_suma);

	    if ( true ) {// $debug ) {
		logger(" V> =======================");
		logger(" V> Intersección: $nombre_grupo_radares_interseccion");
		logger(" V> Suma: $nombre_grupo_radares_suma");
	    }

	    // bucle principal, aquí tendríamos que calcular todas las coberturas.

	    // interseccionar
//	    $count_grupo_radares_interseccion = count($grupo_radares_interseccion);
//	    $nombre_grupo_interseccion = implode('^', $grupo_radares_interseccion);
/*
	    if ( $count_grupo_radares == 1 ) {
		$result_interseccion = $mr_polygon[$grupo_radares[0]];
		// logger(" D> recuperando de polygon interseccion: {$grupo_radares[0]} md5: " . md5(serialize($result_interseccion)));
	    } else {
		$result_interseccion = $radares_interseccion_cache[$nombre_grupo_radares_interseccion];
		// logger(" D> recuperando de cache_interseccion {$nombre_grupo_radares_interseccion} md5: " . md5(serialize($result_interseccion)));
	    }
*/


	    $result_interseccion = $radares_interseccion_cache[$nombre_grupo_radares_interseccion];

	    if ( 0 == count($result_interseccion->contours) ) {
		// logger(" V> Intersección vacia, no hay resultado");
		continue;
	    }
/*
	    $result_resta = false;
	    if ( $count_grupo_radares_suma == 0 ) {
		// logger(" D> no existe polígono que recuperar para el grupo resta");
		$result_suma = false;
	    } else if ( $count_grupo_radares_suma == 1 ) {
		// logger(" D> recuperando de polygon suma: {$grupo_radares_suma[0]}");
		$result_suma = $mr_polygon[$grupo_radares_suma[0]];
	    } else {
		// logger(" D> recuperando de cache_suma: {$nombre_grupo_radares_suma} md5: " . md5(serialize($radares_suma_cache[$nombre_grupo_radares_suma])));
		$result_suma = $radares_suma_cache[$nombre_grupo_radares_suma];
	    }
*/

	    if ( !isset($radares_suma_cache[$nombre_grupo_radares_suma]) ) {
		$result_suma = false;
		logger(" D> Polígonos en suma: false");
	    } else {
		$result_suma = $radares_suma_cache[$nombre_grupo_radares_suma];
		logger(" D> Polígonos en suma: " . ($result_suma !== false ? $result_suma->ncontours() : 0));
	    }

	    logger(" D> Polígonos en interseccion: " . ($result_interseccion !== false ? $result_interseccion->ncontours() : 0));

	    $timer_difference = microtime(true);

	    if ( false === $result_suma ) {
		$result_resta = $result_interseccion;
	    } else {

		if ( $debug ) {
		    $result_suma_arr = $result_suma->toArray();
		    $listaContornos = genera_contornos($result_suma_arr);
		    creaKml2(
			$listaContornos,
			$nombre_grupo_radares_suma, //$radares,
			$ruta,
			$nivelVuelo,
			$altMode,
			$appendToFilename = "_suma",
			$coverageLevel = $coverageName[1]
		    );

		    $result_interseccion_arr = $result_interseccion->toArray();
		    $listaContornos = genera_contornos($result_interseccion_arr);
		    creaKml2(
			$listaContornos,
			$nombre_grupo_radares_interseccion, //$radares,
			$ruta,
			$nivelVuelo,
			$altMode,
			$appendToFilename = "_interseccion",
			$coverageLevel = $coverageName[1]
		    );
		}

		$mr_algorithm = new \MartinezRueda\Algorithm();
		$result_resta = $mr_algorithm->getDifference(
		    $result_interseccion,
		    $result_suma
		);
	    }
	    logger(" D> getDifference: " . round(microtime(true) - $timer_difference,2) . " segundos");

	    if ( false === $result_resta || $result_resta->ncontours() == 0) {
		logger(" V> Resta vacía, no hay resultado");
		continue;
	    }

	    $result_arr2 = $result_resta->toArray();
	    $listaContornos = genera_contornos($result_arr2);
	    creaKml2(
		$listaContornos,
		$grupo_radares,
		$ruta,
		$nivelVuelo,
		$altMode,
		$appendToFilename = "",
		$coverageLevel = $coverageName[$numero_solape]
	    );
	}
    }

    $timer_diff = microtime(true) - $timer; // string = date('Y/m/d H:i:s', round(microtime(true) - $timer_multiradar);

    $timer_unidad = "";
    if ( $timer_diff > 24*60*60 ) {
	$format = "d H:i:s";
    } else if ( $timer_diff > 60*60 ) {
	$format = "H:i:s";
    } else if ( $timer_diff > 120 ) {
	$format = "i:s";
    } else {
	$format = "U";
	$timer_unidad = "segundos";
    }

    $timer_string = date($format, $timer_diff);

    logger(" V> Fin del cálculo de la cobertura multiradar, duración $timer_string $timer_unidad");

    return true;
}

function genera_contornos($result_arr) {

    $listaContornos = array();
    foreach($result_arr as $index => $polygon) {
	if ( 0 == count($polygon) )
	    continue;
	$computeArea = computeArea($polygon);
	// print count($polygon) . "] " . computeArea($polygon) . PHP_EOL;
	// logger(" V> Polígono ($index) con " . count($polygon) . " vértices y área " . round($computeArea,3) . "km2");
	if ( $computeArea < 0.1 ) {
	    // logger(" I> Eliminando polígono ($index) con " . count($polygon) . " vértices y área " . round($computeArea,3) . "km2");
	    continue;
	}

	$leftCorner = array();
	foreach($polygon as $k => $vertex)
	    $leftCorner = findLeftCorner($vertex[1], $vertex[0], $leftCorner, $polygon, $k);

	$listaContornos[] = array(
	    'level' => -1,
	    'polygon' => $polygon,
	    'inside' => array(),
	    'leftCorner' => $leftCorner,
	);
    }
    // usaremos is_in_polygon porque $listaContornos tiene '0' y '1' como índices de los
    // vértices en lugar de 'fila' y col.
    $listaContornos = determinaContornos2_sortContornos($listaContornos, 'is_in_polygon');

    return $listaContornos;

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
 * @return array límites máximos y mínimos para latitud y longitud de cobertura, y limites sup,inf,right,left para los bordes reales de la malla
 */
function calculaBoundingBox_($mallas) {
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
        // print_r($malla);
        $lat_sup = max(array_merge($lats_keys, array($lat_sup))); // cogemos la mayor de las latitudes
        $lat_inf = min(array_merge($lats_keys, array($lat_inf))); // cogemos la menor de las latitudes
        foreach ($malla as $lat => $lons) {
            $lons_keys = array_keys($lons);
            $lon_rig = max(array_merge($lons_keys, array($lon_rig))); // cogemos la mayor de las longitudes
            $lon_lef = min(array_merge($lons_keys, array($lon_lef))); // cogemos la menor de las lontigudes
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
                        $lat_index_prev = array_search($lat, $lats_keys) - 2; // turrillas a 150NM la lat inferior no es suficiente con quitar 1
                        if ( isset($lats_keys[$lat_index_next]) )
                            $lats_maxmin[$lats_keys[$lat_index_next]] = true;
                        if ( isset($lats_keys[$lat_index_prev]) )
                            $lats_maxmin[$lats_keys[$lat_index_prev]] = true;
                        // print "lat: $lat lat_index_next: $lat_index_next lat_index_prev: $lat_index_prev" . PHP_EOL;
                        // print "DEBUG: " . $lats_keys[$lat_index_prev] . " " . $lat . " " . $lats_keys[$lat_index_next] . PHP_EOL;
                    }
                    if ( !isset($lon_dupes[$lon]) ) {
                        $lon_dupes[$lon] = true;
                        $lon_index_next = array_search($lon, $lons_keys) + 2; // por la derecha y por abajo hay que ampliar un poco mas
                        $lon_index_prev = array_search($lon, $lons_keys) - 1; // dada la forma de interpolar que utilizamos
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

    if ( count($lats_maxmin) == 0 || count($lons_maxmin) == 0 ) {
        die("ERROR malla vacia" . PHP_EOL);
    }

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
function interpolaHuecos_($mallas, $bounding) {

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
function interpolaHuecos2_($mallas, $bounding) {

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
function eliminaHuecos_($mallas, $bounding) {

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
function calculaMallaGlobal_($mallas, $bounding) {
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
function obtieneMallaCoberturaPorNivel_($malla, $level) {
    $m = array();
    $cache_bits = array();
    $cache_values = array();
    $found = false;
    foreach($malla as $lat => $lons) {
        $m[$lat] = array();
        foreach($lons as $lon => $value) {
/*
            if ( !isset($cache_bits[countSetBits($value)]) ) {
                // print "bits:" . countSetBits($value) . PHP_EOL;
                $cache_bits[countSetBits($value)] = true;
            }
            if ( !isset($cache_values[$value]) ) {
                // print "values:" . $value . PHP_EOL;
                $cache_values[$value] = true;
            }
            // $m[$lat][$lon] = (countSetBits($value) == $level) ? 1 : 0;
*/
            if ( countSetBits($value) == $level ) {
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
 * Extrae de una malla, los elementos con cobertura, independietemente
 * del nivel (mono, doble, triple, etc). Da igual, con que haya algo
 * nos vale.
 * La nueva malla tendrá 1 para las zonas de cobertura y 0 para las zonas
 * de no cobertura.
 * @param array $malla
 * @return array malla filtrada
 */
function obtieneMallaCoberturaUnica_($malla) {
    $m = array();
    $cache_bits = array();
    $cache_values = array();
    $found = false;
    foreach($malla as $lat => $lons) {
        $m[$lat] = array();
        foreach($lons as $lon => $value) {
            if ( countSetBits($value) > 0 ) {
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
 * Extrae de una malla, las celdas con cobertura de los radares seleccionados.
 * 1 -> el primer radar, 2 -> el segundo radar, 3-> el primer y segundo
 * radar activos.
 * La nueva malla tendrá 1 para las zonas de cobertura y 0 para las zonas
 * de no cobertura.
 * @param array $malla
 * @param int $level radar(es) seleccionados
 * @return array malla filtrada según radares seleccionados
 */
function obtieneMallaPorRadar_($malla, $radarBits) {
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
function printBoundingBox_($bounding) {
    $ret = "" . PHP_EOL . 
        "\tlat_max:" . $bounding['lat_max'] . "c (" . round($bounding['lat_max']/REDONDEO_LATLON,3) . "º) " .
        "lon_max:" .   $bounding['lon_max'] . "c (" . round($bounding['lon_max']/REDONDEO_LATLON,3) . "º) " .
        "lat_min:" .   $bounding['lat_min'] . "c (" . round($bounding['lat_min']/REDONDEO_LATLON,3) . "º) " .
        "lon_min:" .   $bounding['lon_min'] . "c (" . round($bounding['lon_min']/REDONDEO_LATLON,3) . "º)" . PHP_EOL .
        "\tlat_sup:" . $bounding['lat_sup'] . "c (" . round($bounding['lat_sup']/REDONDEO_LATLON,3) . "º) " .
        "lon_rig:" .   $bounding['lon_rig'] . "c (" . round($bounding['lon_rig']/REDONDEO_LATLON,3) . "º) " .
        "lat_inf:" .   $bounding['lat_inf'] . "c (" . round($bounding['lat_inf']/REDONDEO_LATLON,3) . "º) " .
        "lon_lef:" .   $bounding['lon_lef'] . "c (" . round($bounding['lon_lef']/REDONDEO_LATLON,3) . "º)";
    return $ret;
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
