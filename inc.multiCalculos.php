<?php
/**
 * Helper para convertir los contornos que generamos (lat,lon) en una lista
 * de vértices (0=>lat, 1=>lon) que espera MartinezRueda.
 *
 */
function get_vertex($arr) {
    $p = array();
/*
    for($i=12916;$i<12924;$i++) {
	$x = abs($arr[$i]['lat'] - $arr[$i+1]['lat']);
	$y = abs($arr[$i]['lon'] - $arr[$i+1]['lon']);
	print "$i] ===================" . PHP_EOL;
	print "$i] " . $arr[$i]['lat'] . " - " . $arr[$i+1]['lat'] . PHP_EOL;
	print "$i] " . $arr[$i]['lon'] . " - " . $arr[$i+1]['lon'] . PHP_EOL;
        print "$i] $x $y" . PHP_EOL;
	$p[] = array($arr[$i]['lat'], $arr[$i]['lon']);

    }
*/
/*

    $p2 = array();
    for($i=0;$i<count($p)-1;$i++) {
	$x = abs($p[0][0] - $p[1][0]);
	$y = abs($p[0][1] - $p[1][1]);

	print "$x $y" . PHP_EOL;
	if ( $x<0.000001 || $y<0.000001 )
	    continue;
	print "añadiendo:" . print_r($p[$i], true);
	$p2[] = $p[$i];
    }
*/

    foreach($arr as $v) {
	$p[] = array($v['lat'], $v['lon']); // 100.0
    }

    // $p2[] = array_pop(array_reverse($p2));
//    $p2[] = reset($p2);

    //$p = ramer_douglas_peucker($p, 0.01);
    //$p[1][0] = $p[1][0]+0.1;
    //$p[1][0] = $p[1][0]+0.1;
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

    if ( !isset($coberturas) || count($coberturas) == 0 ) {
        return false;
    }

    $timer_multiradar = microtime(true);

    $coverageName = array( 0 => "ninguna",
        "mono", "doble", "triple",
        "cuadruple", "quintuple", "sextuple",
        "septuple", "octuple", "nonuple",
        "decuplo", "undecuplo", "duodecuplo",
        "terciodecuplo",
    );
    $radares = array();
    $mr = array();
    $mr_polygon = array();
    // asigna un número único a cada radar
    $radares2bits = array();
    // obtiene el radar según el número único
    $bits2radares = array();
    $i = 1;
    $timer = microtime(true);
    foreach ($coberturas as $radar => $contornos_por_radar) {
	if ( false === $contornos_por_radar['contornos'] )
	    continue;
	$radares[] = $radar;
	$radares2bits[$radar] = $i;
	$bits2radares[$i] = $radar;
	$i <<= 1;
	$polygons = array();
	// $j = 0;

//	if ( $radar == "erillas" ) {
//	    $polygons = array(array( array(40,-7), array(34,-7), array( 40,-5), array(40,-7) ));
//
//	} else 

	foreach($contornos_por_radar['contornos'] as $indice => $contorno) {
	    //if ( $j>4 ) break; $j++;
	    //if ( count($contorno['polygon'])>1000)
		$polygons[] = get_vertex($contorno['polygon']);
	    //else continue;

	    //$polygons[] = ramer_douglas_peucker($polygon, 0.001);
	    if ( !isset($contorno['inside']) )
		continue;
	    //$k = 0;

	    foreach($contorno['inside'] as $indice_inside => $contorno_inside) {
		//if ( $k>4) break; $k++;
		// if (count($contorno_inside['polygon'])>1000)
		$polygons[] = get_vertex($contorno_inside['polygon']);
		//else continue;
		//$polygons[] = ramer_douglas_peucker($polygon, 0.001);
	    }

	}
	//print_r($polygons);
	$mr_polygon[$radar] = new \MartinezRueda\Polygon($polygons);
    }
    sort($radares);

    // print_r($radares2bits); print_r($bits2radares);

    if ( 1 >= count($radares) ) {
	logger(" E> No existen coberturas suficientes para seguir calculando");
	return false;
    }


    if ( isset($calculoMode['multiradar_unica']) && true === $calculoMode['multiradar_unica'] ) {
	logger(" I> Creando cobertura única/suma");

	if ( count($radares) < 2 ) {
	    logger(" E> Necesitamos dos radares para hacer un cálculo multiradar");
	    exit(-1);
	}

	$result_suma = new \MartinezRueda\Polygon(array());
	$mr_algorithm = new \MartinezRueda\Algorithm();

	foreach($mr_polygon as $k => $p) {
	    logger(" D> Añadiendo {$k} al cálculo");
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
	logger(" V> Finalizado en " . round(microtime(true) - $timer_multiradar,3) . " segundos");
	return true;
    }

    $vsr = array(); // variaciones sin repetición
    for($i = 1; $i<count($radares); $i++) {
	$Combinations = new Combinations($radares);
	$vsr[$i] = $Combinations->getCombinations($i, false);
	// print_r($vsr[$i]);
    }
    $vsr[] = array($radares);
    logger(" D> Estructuras generadas en " . round(microtime(true) - $timer, 3) . "s");

    $radares_interseccion_cache = array();
    $radares_resta_cache = array();
    $radares_suma_cache = array();

    foreach($vsr as $numero_solape => $grupo_solape) {
	if ( $numero_solape >= count($coverageName) ) {
	    $coverageName_fixed = "de más de {$numero_solape}";
	} else {
	    $coverageName_fixed = $coverageName[$numero_solape];
	}
	
	logger(" N> Calculando cobertura $coverageName_fixed");

	foreach($grupo_solape as $grupo_radares_interseccion) {
	    logger (" D> " . "Info memory_usage(" . convertBytes(memory_get_usage(false)) . ") " .
		"Memory_peak_usage(" . convertBytes(memory_get_peak_usage(false)) . ")");

	    $grupo_radares_resta = array_values(array_diff($radares, $grupo_radares_interseccion));

	    logger(" V> Intersección: " . implode(',', $grupo_radares_interseccion));
	    logger(" V> Resta: " . implode(',', $grupo_radares_resta));

	    // bucle principal, aquí tendríamos que calcular todas las coberturas.
	    $mr_algorithm = new \MartinezRueda\Algorithm();

	    // interseccionar
	    $count_grupo_radares_interseccion = count($grupo_radares_interseccion);
	    if ( $count_grupo_radares_interseccion == 1 ) {
		$result_inter = $mr_polygon[$grupo_radares_interseccion[0]];
	    } else if ( $count_grupo_radares_interseccion == 2 ) { // estos nunca estarán en caché
		$nombre_grupo_interseccion = implode('^', $grupo_radares_interseccion);
		// print "QUERY: $nombre_grupo_interseccion" . PHP_EOL;
		$result_inter = $mr_algorithm->getIntersection(
		    $mr_polygon[$grupo_radares_interseccion[0]],
		    $mr_polygon[$grupo_radares_interseccion[1]]
		);
		$radares_interseccion_cache[$nombre_grupo_interseccion] = $result_inter;
		print "STORED: $nombre_grupo_interseccion" . PHP_EOL;
	    } else { // 3 o más
		//print "CACHESTATUS: " . implode(',' , array_keys($radares_interseccion_cache)). PHP_EOL;
		// los anteriores ya están en la caché, sólo hay que calcular la intersección con el nuevo
		// se cogen todos los radares menos el último y se generan dos listas, subgrupo y el resto.
		$subgrupo = array_slice($grupo_radares_interseccion,
		    0,
		    $count_grupo_radares_interseccion - 1
		);
		// nombre del último radar, para la intersección
		$resto = $grupo_radares_interseccion[$count_grupo_radares_interseccion - 1];
		$nombre_subgrupo_interseccion = implode('^', $subgrupo);
		$nombre_grupo_interseccion = implode('^', $grupo_radares_interseccion);
		print "RETRIEVED: $nombre_subgrupo_interseccion" . PHP_EOL;
		$result_inter = $mr_algorithm->getIntersection(
		    $radares_interseccion_cache[$nombre_subgrupo_interseccion],
		    $mr_polygon[$resto]
		);
		$radares_interseccion_cache[$nombre_grupo_interseccion] = $result_inter;
		print "STORED: $nombre_grupo_interseccion" . PHP_EOL;
	    }

	    logger(" D> Polígonos en intersección: " . $result_inter->ncontours()) . PHP_EOL;

	    if ( 0 == count($result_inter->contours) ) {
		logger(" V> Intersección vacia, no hay resultado");
		continue;
	    }

	    // podríamos implementar esto como una suma de grupos a restar y luego una resta
	    // print "RESTA: " . implode(',', $grupo_radares_resta) . PHP_EOL;
	    $mr_algorithm = new \MartinezRueda\Algorithm();
	    $result_resta = false;
	    $count_grupo_radares_resta = count($grupo_radares_resta);

	    if ( $count_grupo_radares_resta == 1 ) {
		// print ">grr: {$grupo_radares_resta[0]}" . PHP_EOL;
		$result_resta = $mr_polygon[$grupo_radares_resta[0]];
	    } else if ( $count_grupo_radares_resta > 1 ) {

		// calcula un id según los radares que haya en el grupo
		$bits = getBitsRadares($radares2bits, $grupo_radares_resta);
//		print "estos son los radares que hay que sumar:" . PHP_EOL;
//		print_r($bits);
		// obtiene de todas las sumas que hay que hacer, las que ya están hechas.
		$resto = 0; $suma_bits = 0;
		$result_suma = getSumasFromCache($radares2bits, $bits2radares, $bits, $radares_suma_cache, $suma_bits, $resto_bits);
		// hay que averiguar qué suma está hecha y sumar lo que quede pendiente
		$resto_radares = getRadaresBits($bits2radares, $resto_bits);
		$is_empty = $result_suma->ncontours() > 0 ? false : true;
		print "result_suma: " . count($result_suma->contours) . PHP_EOL;
		print "resto radares" . PHP_EOL;
		print_r($resto_radares);
		print "cache suma: " . count($radares_suma_cache) . PHP_EOL;
		
		// ahora hay que sumar resto_radares
		for($i = 0; $i < count($resto_radares); $i++) {
		    print ".";
//		    print "sumando : " . $resto_radares[$i]. PHP_EOL;
		    $result_suma = $mr_algorithm->getUnion(
		        $result_suma,
		        $mr_polygon[$resto_radares[$i]]
		    );
		    $suma_bits += $radares2bits[$resto_radares[$i]];
		    // comprobamos que getSumasFromCache devolvió datos, porque entonces
		    // la primera suma hay que guardarla en caché.
		    if ( false == $is_empty ) {
//			print "GUARDANDO ($suma_bits)" . PHP_EOL;
			if ( isset($radares_suma_cache[$suma_bits]) ) {
			    logger(" E> Guardando en caché un elemento que previamente existía: $suma_bits"); exit(-1);
			}
			$radares_suma_cache[$suma_bits] = $result_suma;
		    }
		    // a partir de la primera iteración, el resultado de la suma siempre
		    // se deberá cachear
		    $is_empty = false;
		}
		//print PHP_EOL;
		// todos sumados en result_suma
		$result_resta = $result_suma;
	    }

	    logger(" D> Polígonos en resta: " . ($result_resta !== false ? $result_resta->ncontours() : 0));
	    $timer_difference = microtime(true);
	    if ( false === $result_resta ) {
		$result = $result_inter;
	    } else {
		$result = $mr_algorithm->getDifference(
		    $result_inter,
		    $result_resta
		);
	    }
	    logger(" D> getDifference: " . round(microtime(true) - $timer_difference,2) . " segundos");
	    $result_arr2 = $result->toArray();


	$listaContornos = genera_contornos($result_arr2);

	creaKml2(
	    $listaContornos,
	    $grupo_radares_interseccion, //$radares,
	    $ruta,
	    $nivelVuelo,
	    $altMode,
	    $appendToFilename = "",
	    $coverageLevel = $coverageName[$numero_solape]
	);

	}
    }


    $timer_diff = microtime(true) - $timer_multiradar; // string = date('Y/m/d H:i:s', round(microtime(true) - $timer_multiradar);

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

/*
 * Devuelve la suma de los radares activos y un array con los bits
 * de cada uno de los radares
 *
 */

function getBitsRadares($radares2bits, $grupo_radares_resta) {
    $bits = array( 'suma' => 0, 'bits' => array() );
    $bits['suma'] = 0;
    foreach($grupo_radares_resta as $radar) {
	$bits['suma'] += $radares2bits[$radar];
	$bits['bits'][] = $radares2bits[$radar];
    }

    return $bits;
}

// si se devuelve resto, son los radares que quedan por sumar, que no se han
// encontrado en la cache.
// la función devuelve el polígono con las sumas, false si no hay ninguna 
// en la cache

function getSumasFromCache($radares2bits, $bits2radares, $bits, $radares_suma_cache, &$suma_bits, &$resto_bits) {


// id = 5 y en suma_cache no hay 5, sólo hay 4... así que tengo que descomponer id en su suma de factores
    $resto_bits = 0;
    $suma_bits = 0;

//    print_r($radares2bits); print_r($bits2radares); exit(1);

    // print "SEARCH START" . PHP_EOL;

    $id = $bits['suma'];

    $vsr = array(); // variaciones sin repetición
    for($i = count($bits['bits']); $i>1; $i--) {
	$Combinations = new Combinations($bits['bits']);
	$vsr[$i] = $Combinations->getCombinations($i, false);
	print "$i] " .  count($vsr[$i]) . PHP_EOL;
	foreach($vsr[$i] as $variacion_radares) {
	    $suma = array_sum($variacion_radares);
	    print "SEARCHING $suma" . PHP_EOL;
	//    if ( count($variacion_radares) == 7 ){
	//	print "variacion_radares:" . PHP_EOL;
	//	print_r($variacion_radares);
	//    }

	    if ( isset($radares_suma_cache[$suma]) ) {
		    print "FOUND IN CACHE" . PHP_EOL;
		    $resto_bits = array_sum(array_diff($bits['bits'], $variacion_radares));
		    $suma_bits = $suma;
		    return $radares_suma_cache[$suma ];
	    }
/*
	    foreach($radares_suma_cache as $key => $polygon) {
		if ( $suma == $key ) {
	//	    print "MATCH!!! $key" . PHP_EOL;
		    $resto_bits = array_sum(array_diff($bits['bits'], $variacion_radares));
	//	    print "resto_bits: $resto_bits" . PHP_EOL;
		    $suma_bits = $suma;
	//	    print "variacion radares" . PHP_EOL;
		    //print_r($variacion_radares);
	//	    foreach($variacion_radares as $bit) {
	//		print $bits2radares[$bit] . " " ;
	//	    }
	//	    print PHP_EOL;
//		    print "resto radares sin sumar" . PHP_EOL;
//		    foreach($resto_bits as $bit) {
//			print $bits2radares[$bit] . " " ;
//		    }
//		    print PHP_EOL;
//		    //print_r($resto_bits);
		    //exit(-1);
		    return $polygon;
		}
	    }
*/
	}
    }

/*
    // exact match
    if ( isset($radares_suma_cache[$id]) ) {
	$resto_bits = 0;
	$suma_bits = $id;
	print "EXACT MATCH" . PHP_EOL;
	return $radares_suma_cache[$id];
    }

    $found_key = false;
    foreach($radares_suma_cache as $key => $polygon) {
	print "SEARCHING id($id) key($key)" . PHP_EOL;
	if ( $key > $id ) {
	    print "SEARCH ABORTED" . PHP_EOL;
	    continue;
	}
	// si es 0, es que key contiene sólo polígonos creados con id.
	// es justo lo que estamos buscando.
	// estamos haciendo una resta, de id quitar 
	$cmp = ($id & $key);
	// ahora hay que averiguar las operaciones que se piden en id
	// y que no están en key, para devolverlo o bien si existe alternativa mejo-r
	if ( $cmp == $key ) {
	    print "CANDIDATO key: $key para tener cacheado la petición id: $id (cmp:$cmp & key:$key)" . PHP_EOL;
	    $found_key = $key;
	}
	// print "id: $id key: $key ~key: " . ~$key . " $id and !$key: " . $cmp . PHP_EOL;
/*
	// comprobar este caso, de q esta parte no se va a dar
	$and = ($id & $key);
	print "id($id) and key($key) = (" . $and . ")" . PHP_EOL;
	if ( $and == $id ) {
	    // encontrado exact match
	    $suma_bits = $and;
	    $resto_bits = 0;
	    return $polygon;
	}
*/
/*    }

    if ( false !== $found_key ) {
	print "SEARCH SOMETHING FOUND" . PHP_EOL;
	$suma_bits = $found_key;
	$resto_bits = $found_key ^ $id;
	return $radares_suma_cache[$found_key];
    }
*/
    print "SEARCH NOT FOUND" . PHP_EOL;
    $resto_bits = $bits['suma'];
    return new \MartinezRueda\Polygon(array());
}

// devuelve un array de radares, según los bits activos en resto_bits
function getRadaresBits($bits2radares, $resto_bits) {

//    print "resto_bits: $resto_bits" . PHP_EOL;

    $ret = array();
    $bit_str = decbin($resto_bits);
    $len = strlen($bit_str) - 1;
    for($i = $len, $pow = 1; $i >= 0; $i--, $pow<<=1) {
//	print "bit_str($bit_str) i($i) pow($pow) bit_str[i](" . $bit_str[$i] . ")" . PHP_EOL;
	if ( 1 == $bit_str[$i] ) {
	    $ret[] = $bits2radares[$pow];
	}
    }
    sort($ret);
    return $ret;
}


function genera_contornos($result_arr) {

    $listaContornos = array();
    foreach($result_arr as $index => $polygon) {
	if ( 0 == count($polygon) )
	    continue;
	$computeArea = computeArea($polygon);
	// print count($polygon) . "] " . computeArea($polygon) . PHP_EOL;
	logger(" V> Polígono ($index) con " . count($polygon) . " vértices y área " . round($computeArea,3) . "km2");
	if ( $computeArea < 0.1 ) {
	    logger(" I> Eliminando polígono ($index) con " . count($polygon) . " vértices y área " . round($computeArea,3) . "km2");
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
