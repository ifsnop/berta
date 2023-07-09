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
 * Inicializa un array con las coberturas en polígonos Martinez-Rueda
 * y una lista de radares que tienen polígonos válidos. Se puede llamar
 * varias veces a esta función, porque las uniones de polígonos en
 * Martinez Rueda machacan el primer array que se le pasa al
 * algorítmo.
 */
function init_polygons($coberturas) {
    $radares = array();
    $mr_polygons = array();
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
	$mr_polygons[$radar] = new \MartinezRueda\Polygon($polygons);
    }
    sort($radares);

    if ( 0 == count($radares) || 1 == count($radares) ) {
	logger(" E> No existen coberturas suficientes para seguir calculando (init_polygons)");
	return false;
    }

    return array('radares' => $radares, 'mr_polygons' => $mr_polygons);
}

/**
 * Genera la cobertura suma de todos los radares
 * Devuelve el segmento de kmz.
 *
*/
function create_unica($radares, $mr_polygons) {
    $timer = microtime(true);

    $result_suma = new \MartinezRueda\Polygon(array());
    foreach($mr_polygons as $k => $p) {
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
    logger(" V> Finalizada cobertura única en " . round(microtime(true) - $timer,3) . " segundos");
    return true;
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
    $timer = microtime(true);

    if ( !isset($coberturas) || count($coberturas) == 0 ) {
	logger(" E> No existen coberturas suficientes para seguir calculando (multicobertura)");
        return false;
    }

    $coverageName = array( 0 => "ninguna",
        "mono", "doble", "triple",
        "cuadruple", "quintuple", "sextuple",
        "septuple", "octuple", "nonuple",
        "decuplo", "undecuplo", "duodecuplo",
        "terciodecuplo",
    );

    $ret = init_polygons($coberturas);
    if ( false === $ret )
	return false;

    $radares = $ret['radares'];
    $mr_polygons = $ret['mr_polygons'];

    if ( isset($calculoMode['multiradar_unica']) && true === $calculoMode['multiradar_unica'] ) {
	logger(" I> Creando cobertura única/suma");
	create_unica($radares, $mr_polygons);
    }

    $vsr = array(); // variaciones sin repetición
    $vsr_count = 0;
    for($i = 1; $i<=count($radares); $i++) {
	$combinations = new combinations($radares);
	$vsr[$i] = $combinations->getCombinations($i, false);
	$vsr_count += count($vsr[$i]);
    }
    logger(" D> " . $vsr_count . " estructuras generadas en " . round(microtime(true) - $timer, 3) . " segundos");

    $radares_interseccion_cache = array();
    //$radares_resta_cache = array();
    //$radares_suma_cache = array();

    // cacheo de intersecciones y sumas

    $ret = populate_cache($vsr, $vsr_count, $coverageName, $mr_polygons, $radares_interseccion_cache, $radares_suma_cache);

    // logger(" D> count radares_suma_cache: " . implode(',', array_keys($radares_suma_cache)));
    // logger(" D> count radares_interseccion_cache: " . implode(',', array_keys($radares_interseccion_cache)));

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

	    logger( " V> $count/$vsr_count");
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

	    $result_interseccion = $radares_interseccion_cache[$nombre_grupo_radares_interseccion];

	    if ( 0 == count($result_interseccion->contours) ) {
		logger(" V> Intersección vacia, no hay resultado");
		continue;
	    }

	    logger(" D> Polígonos en interseccion: " . ($result_interseccion !== false ? $result_interseccion->ncontours() : 0));
	    if ( !isset($radares_suma_cache[$nombre_grupo_radares_suma]) ) {
		$result_suma = false;
		logger(" D> Polígonos en suma: false");
	    } else {
		$result_suma = $radares_suma_cache[$nombre_grupo_radares_suma];
		logger(" D> Polígonos en suma: " . ($result_suma !== false ? $result_suma->ncontours() : 0));
	    }

	    $timer_difference = microtime(true);
	    if ( false === $result_suma ) {
		$result_resta = $result_interseccion;
	    } else {
		$mr_algorithm = new \MartinezRueda\Algorithm();
		$result_resta = $mr_algorithm->getDifference(
		    $result_interseccion,
		    $result_suma
		);
	    }
	    logger(" D> Tiempo de resta: " . round(microtime(true) - $timer_difference,2) . " segundos");

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
    logger (" D> " . "Info memory_usage(" . convertBytes(memory_get_usage(false)) . ") " .
	"Memory_peak_usage(" . convertBytes(memory_get_peak_usage(false)) . ")");

    logger(" V> Fin del cálculo de la cobertura multiradar, duración " . timer_unidades( $timer_diff ));
    return true;
}


function populate_cache($vsr, $vsr_count, $coverageName, $mr_polygons, &$radares_interseccion_cache, &$radares_suma_cache) {
    $radares_interseccion_cache = array();
    $radares_suma_cache = array();

    $count = 0;
    $debug = false;
    foreach($vsr as $numero_solape => $grupo_solape) {
	if ( $numero_solape >= count($coverageName) ) {
	    $coverageName_fixed = "de más de {$numero_solape}";
	} else {
	    $coverageName_fixed = $coverageName[$numero_solape];
	}
	logger(" N> == Calculando cache para cobertura $coverageName_fixed");

	foreach($grupo_solape as $grupo_radares) {
	    logger( "$count/$vsr_count ", false);
	    $count++;

	    $count_grupo_radares = count($grupo_radares);
	    $nombre_grupo_radares_suma = implode('+', $grupo_radares);
	    $nombre_grupo_radares_interseccion = implode('^', $grupo_radares);

	    // cacheamos en funcion de cuantos radares haya.
	    // si es solo uno, es directo
	    if ( $count_grupo_radares == 1 ) {
		$result_interseccion = clone $mr_polygons[$grupo_radares[0]];
		$result_suma = clone $mr_polygons[$grupo_radares[0]];
	    // si son dos radares, hay que coger los dos (serán los dos primeros)
	    } else if ( $count_grupo_radares == 2 ) { // estos nunca estarán en caché
		// PRIMERO CACHEAMOS LA INTERSECCION
		$mr_algorithm = new \MartinezRueda\Algorithm();
		$subject = clone $mr_polygons[$grupo_radares[0]];
		$clipping = clone $mr_polygons[$grupo_radares[1]];
		$result_interseccion = $mr_algorithm->getIntersection( $subject, $clipping );

		// LUEGO CACHEAMOS LA SUMA
		$mr_algorithm = new \MartinezRueda\Algorithm();
		$subject = clone $mr_polygons[$grupo_radares[0]];
		$clipping = clone $mr_polygons[$grupo_radares[1]];
		$result_suma = $mr_algorithm->getUnion( $subject, $clipping );

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
		$clipping = clone $mr_polygons[$ultimo_radar];
		$mr_algorithm = new \MartinezRueda\Algorithm();
		$result_interseccion = $mr_algorithm->getIntersection( $subject, $clipping );
		if ( $debug )
		    logger(" D> store interseccion_cache r: $nombre_grupo_radares_interseccion md5: " . md5(serialize($result_interseccion)));

		$subject = clone $radares_suma_cache[$nombre_subgrupo_radares_suma];
		if ( $debug )
		    logger(" D> retrieve suma_cache r: $nombre_subgrupo_radares_suma md5: " . md5(serialize($subject)));
		$clipping = clone $mr_polygons[$ultimo_radar];
		$mr_algorithm = new \MartinezRueda\Algorithm();
		$result_suma = $mr_algorithm->getUnion( $subject, $clipping );
		if ( $debug )
		    logger(" D> store suma_cache r: $nombre_grupo_radares_suma md5: " . md5(serialize($result_suma)));
	    }

	    $radares_interseccion_cache[$nombre_grupo_radares_interseccion] = $result_interseccion;
	    $radares_suma_cache[$nombre_grupo_radares_suma] = $result_suma;
	}
	logger(PHP_EOL, false);

    }

    return true;
}



/*
 * Convierte una lista de coordenadas en polígonos ordenados (dentro/fuera),
 * eliminando los que sean muy pequeños y usando las funciones de conrec.
 *
 */
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
