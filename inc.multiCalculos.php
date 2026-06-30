<?php

use Ifsnop\MartinezRueda as MR;

/*
// Quick test code
$region_a =  [[[0,0], [0,1], [1,1], [1,0]]];
$region_b = [[[1,0], [2,0], [2,1], [1,1]]];
$pa = MR\Polygon::create()->fillFromArray($region_a);
$pb = MR\Polygon::create()->fillFromArray($region_b);
$result = MR\Algorithm::union($pa, $pb)->getArray();
print_r($result);
exit(0);

*/

/**
 * Inicializa un array con las coberturas en polígonos Martinez-Rueda
 * y una lista de sensores que tienen polígonos válidos. 
 */
function init_polygons(array $coberturas)
{
	$sensores = array();
	$mr_polygons = array();
	foreach ($coberturas as $sensor => $contornos_por_sensor) {
		if (false === $contornos_por_sensor['polygons'])
			continue;
		$sensores[] = $sensor;
		$polygons = array();

		foreach ($contornos_por_sensor['polygons'] as $indice => $polygon) {
			$p = ramer_douglas_peucker($polygon, BERTA_RAMER_DOUGLAS_PEUCKER_PRECISION);
			$polygons[] = $p;
		}
		// print "RADAR:{$sensor} POLYCUENTA:" . count($contornos_por_sensor['polygons']) . PHP_EOL;
		// print json_encode($contornos_por_sensor['polygons']) . PHP_EOL;
		$mr_polygons[$sensor] = MR\Polygon::create()->fillFromArray($polygons);
	}
	sort($sensores);

	if (0 == count($sensores) || 1 == count($sensores)) {
		logger(" E> No existen coberturas suficientes para seguir calculando (init_polygons)");
		return false;
	}
	return array('radares' => $sensores, 'mr_polygons' => $mr_polygons);
}

/**
 * Genera la cobertura suma de todos los radares
 * Devuelve un array normalizado
 *
 */
function create_unica(array $mr_polygons)
{
	$timer = microtime(true);
	logger(" D> Generando cobertura única");
	$p_mr1 = MR\Algorithm::unionMany($mr_polygons);
	$normalized = normalizePolygonsForKML($p_mr1->getArray());
	logger(" V> Finalizada cobertura única: " . round(microtime(true) - $timer, 3) . "s");
	return $normalized;
}

/**
 * Funcion que calcula los distintos polígonos de cobertura a partir de la cobertura individual
 * de los polígonos incluidos en $coberturas y crea el kml correspondiente, con distintos
 * colores dependiendo del tipo de cobertura (simple, doble, triple, cuadruple, etc).
 *
 
 * @param array $coberturas arrays de mallas con coberturas para cada sensor(ENTRADA)
 * @param int $fl nivel de vuelo seleccionado (ENTRADA)
 * @param array $calculoMode array con los modos de cálculo seleccionados (ENTRADA)
 * 'mode' => array('monoradar' => true, 'multiradar' => false, 'multiradar_unica' => false, 'list' => false)
 * @return string kml completo con el resultado de los cálculos
 */
function multicobertura(array &$coberturas, int $fl, array $calculoMode): string
{
	$debug = false;
	$timer = microtime(true);

	if (!isset($coberturas) || count($coberturas) == 0) {
		logger(" E> No existen coberturas suficientes para seguir calculando (multicobertura)");
		return false;
	}

	$coverageNames = array(
		0 => "ninguna",
		"mono",
		"doble",
		"triple",
		"cuadruple",
		"quintuple",
		"sextuple",
		"septuple",
		"octuple",
		"nonuple",
		"decuplo",
		"undecuplo",
		"duodecuplo",
		"terciodecuplo",
	);

	$coverages_per_level_KML = array();

	$ret = init_polygons($coberturas);
	if (false === $ret)
		return false;

	$radares = $ret['radares'];
	$mr_polygons = $ret['mr_polygons'];
	logger(" D> Polígonos de radares cacheados: " . implode(',', (array_keys($mr_polygons))));

	$flm = round($fl * 100.0 * BERTA_FEET_TO_METERS, 2);
    $flWithPad = str_pad((string) $fl, 3, "0", STR_PAD_LEFT);
    $radarWithFl = implode(',', $radares) . "-" . $flWithPad;

	logger(" I> Creando cobertura única/suma");
	$normalized = create_unica($mr_polygons);
	$kml = normalized2KML($normalized, 'unica', $radares, $fl);
	$kml = KML_create_folder('unica', $kml);
		
	// si se selecciona única, sólo se genera la única y se vuelve.
	if (isset($calculoMode['multiradar_unica']) && true === $calculoMode['multiradar_unica']) {
		return $kml;
	}

	$vsr = array(); // variaciones sin repetición
	$vsr_count = 0;
	for ($i = 1; $i <= count($radares); $i++) {
		$combinations = new combinations($radares);
		$vsr[$i] = $combinations->getCombinations($i, false);
		$vsr_count += count($vsr[$i]);
	}
	logger(" D> " . $vsr_count . " estructuras generadas en " . round(microtime(true) - $timer, 3) . " segundos");

	$radares_interseccion_cache = array();
	$radares_suma_cache = array();

	// cacheo de intersecciones y sumas
	$ret = populate_cache($vsr, $vsr_count, $mr_polygons, $radares_interseccion_cache, $radares_suma_cache);

	logger(" D> count radares_suma_cache: " . implode(',', array_keys($radares_suma_cache)));
	logger(" D> count radares_interseccion_cache: " . implode(',', array_keys($radares_interseccion_cache)));

	// ejecución
	$count = 1;
	foreach ($vsr as $numero_solape => $grupo_solape) { // numero_solape = 1, 2, 3...
		// $grupo_solape en la primera iteración serán los radares individuales
		if ($numero_solape >= count($coverageNames)) {
			$coverageNames_fixed = "de más de {$numero_solape}";
		} else {
			$coverageNames_fixed = $coverageNames[$numero_solape];
		}
		logger(" N> == Calculando cobertura $coverageNames_fixed"); // mono, doble, triple, etc...
		$coverages_per_level_KML[$numero_solape] = array();
		$mr_polygons[$numero_solape] = array();
		
		foreach ($grupo_solape as $grupo_radares) { // primera iteración, radares individuales

			logger(" V> $count/$vsr_count");
			$count++;

			// extraemos los radares que van a intervenir en esta iteración
			// mono cobertura del primer radar es el primer radar menos todos los demás
			$count_grupo_radares = count($grupo_radares);
			$nombre_grupo_radares = implode(',', $grupo_radares);
			$nombre_grupo_radares_interseccion = implode('^', $grupo_radares);
			$grupo_radares_suma = array_values(array_diff($radares, $grupo_radares));
			$count_grupo_radares_suma = count($grupo_radares_suma);
			$nombre_grupo_radares_suma = implode('+', $grupo_radares_suma);

			/** @var MR\Polygon $result_interseccion */
			if ( !isset($radares_interseccion_cache[$nombre_grupo_radares_interseccion]) ) {
				logger(" N> Intersección no existe, no hay resultado. Se buscó {$nombre_grupo_radares_interseccion}");
				continue;
			}
			$result_interseccion =  $radares_interseccion_cache[$nombre_grupo_radares_interseccion];
			logger(" V> Intersección: $nombre_grupo_radares_interseccion Polygon_count: " . ($result_interseccion !== false ? $result_interseccion->numPoints : 0));
			if (0 == $result_interseccion->numPoints) {
				logger(" N> Intersección vacia, no hay resultado. Se buscó: {$nombre_grupo_radares_interseccion}");
				continue;
			}

			/** @var MR\Polygon|bool $result_suma */
			$result_suma = false;
			if (isset($radares_suma_cache[$nombre_grupo_radares_suma])) {
				$result_suma = $radares_suma_cache[$nombre_grupo_radares_suma];
				logger(" D> Suma: $nombre_grupo_radares_suma Polygon_count: " . ($result_suma !== false ? $result_suma->numPoints : 0));
			} else {
				logger(" D> Suma no existe, no hay resultado. Se buscó: {$nombre_grupo_radares_suma}");
				// pero no es un error
			}

			// ahora calculamos la diferencia de intersección - suma (para sacar la cobertura sólo del tipo $numero_solape)
			$timer_difference = microtime(true);
			/** @var MR\Polygon|bool $result_resta */
			$result_resta = false;
			if (false === $result_suma) {
				$result_resta = $result_interseccion;
			} else {
				$result_resta = MR\Algorithm::difference(
					$result_interseccion,
					$result_suma
				);
			}
			logger(" D> Tiempo de resta: " . round(microtime(true) - $timer_difference, 3) . " segundos");

			if ( false === $result_resta || 0 == $result_resta->numPoints ) {
				logger(" V> Resta vacía, no hay resultado");
				continue;
			}
			
			// en mr_polygons guardamos para cada nivel de cobertura (mono, doble, triple) todos los polígonos que forman
			// ese nivel
			logger(" D> Polígono resultante:" . $result_resta->numPoints . " nivel {$numero_solape}");
			$mr_polygons[$numero_solape][$nombre_grupo_radares_interseccion . "-" . $nombre_grupo_radares_suma] = $result_resta;
			$normalized = normalizePolygonsForKML($result_resta->getArray());
			$kml = normalized2KML($normalized, $coverageNames[$numero_solape] , $grupo_radares, $fl);
			logger(" D> Calculada: nivel {$numero_solape} {$nombre_grupo_radares} => {$nombre_grupo_radares_interseccion} - ( {$nombre_grupo_radares_suma} )");
			// guardamos el kml para luego juntarlo en uno global, que contenga todos los niveles de cobertura
			// y todos los radares
			if (false !== $kml) {
				$coverages_per_level_KML[$numero_solape][$nombre_grupo_radares] = $kml;
				// print json_encode(array_keys($coverages_per_level_KML[$numero_solape])) . PHP_EOL;
				// print_r($coverages_per_level_KML[$numero_solape][$nombre_grupo_radares]);
			}
			/*
			if ( $numero_solape==3) {
				print json_encode($normalized);
				print $kml . PHP_EOL;
				exit(0);
			}
			*/
		}

		// aquí se calcula la cobertura unica de este nivel (una total por mono, doble, triple) podríamos no utilizarla
		// y de momento generar ya los folders con el contenido.

		/*
		// generar aquí la cobertura suma usando todo lo contenido en $mr_polygons[$numero_solape];
		// no acaba de funcionar bien, hay coberturas que no se suman

		logger(" V> =====CALCULO COBERTURA UNICA====");

		$timer_unica = microtime(true);

		$result_unica = new \MartinezRueda\Polygon(array());
		$i = 0;
		$j = 0;
		foreach ($mr_polygons[$numero_solape] as $n => $polygon) {
			logger(" D> Uniendo {$n}");
			$result_arr2 = $polygon->toArray();
			$listaContornos = genera_contornos($result_arr2);
			creaKml2(
				$listaContornos,
				"N{$numero_solape}_PASO {$i}.0={$n}", //$radares,
				$rutas,
				$nivelVuelo,
				$altMode,
				$appendToFilename = "",
				$coverageLevel = "unica_SUMANDO"
			);
			$j += $polygon->ncontours();
			logger(" D> POLYCUENTA SUMANDO:" . $polygon->ncontours() . " j:{$j} NIVEL{$numero_solape}");

			//unset($polygon->contours[0]);
			//$polygon->contours = array_values($polygon->contours);

			//	    if ( $i >= 2) { logger(" D> AQUI VA A FALLAR");
			//		print $n . PHP_EOL;
			//		$i++;
			//		continue;
			//		\MartinezRueda\Debug::$debug_on = true;
			//		print_r($polygon);
			//	    }
			print "QQ!!" . PHP_EOL;
			//print_r($qq);

			print "IFSNOP" . PHP_EOL;
			var_dump($result_unica);

			file_put_contents("N{$numero_solape}_PASO {$i}.0={$n}.json", json_encode($result_unica->toArray()));
			file_put_contents("N{$numero_solape}_PASO {$i}.1={$n}.json", json_encode($polygon->toArray()));
			foreach ($result_unica->toArray() as $idx => $poly) print $idx . " " . count($poly) . PHP_EOL;
			$mr_algorithm = new \MartinezRueda\Algorithm();
			$result_unica = $mr_algorithm->getUnion($result_unica, $polygon);
			$qq = $result_unica->toArray();
			print "WW!!" . PHP_EOL;
			foreach ($result_unica->toArray() as $idx => $poly) print $idx . " " . count($poly) . PHP_EOL;
			file_put_contents("N{$numero_solape}_PASO {$i}.2={$n}.json", json_encode($result_unica->toArray()));

			//if ( $i > 10)
			// break;


			$result_arr2 = $result_unica->toArray();
			$listaContornos = genera_contornos($result_arr2);
			// print_r($listaContornos);
			creaKml2(
				$listaContornos,
				"N{$numero_solape}_PASO {$i}.1={$n}", //$radares,
				$rutas,
				$nivelVuelo,
				$altMode,
				$appendToFilename = "",
				$coverageLevel = "unica_SUMANDO_PARCIAL"
			);

			$i++;
		}
		*/

		/*
		*logger(" D> 00 POLYCUENTA TOTAL:". $result_unica->ncontours() ." NIVEL{$numero_solape}");
		*
	    *$polygon = $mr_polygons[$numero_solape]['monflorite-alcolea+paracuellos1+paracuellos2'];
	    *$mr_algorithm = new \MartinezRueda\Algorithm();
	    *$result_unica = $mr_algorithm->getUnion( $result_unica, $polygon );
	    *$result_arr2 = $result_unica->toArray();
	    *$listaContornos = genera_contornos($result_arr2);
	    *creaKml2(
		*$listaContornos,
		*"N{$numero_solape}_PASO UNICO", //$radares,
		*$rutas,
		*$nivelVuelo,
		*$altMode,
		*$appendToFilename = "",
		*$coverageLevel = "unica_SUMANDO_PARCIAL"
	    *);
		*
		*logger(" D> 01 POLYCUENTA TOTAL:". $result_unica->ncontours() ." NIVEL{$numero_solape}");
		*/
		/*
		logger(" D> POLYCUENTA TOTAL:" . $result_unica->ncontours() . " NIVEL{$numero_solape}");

		$result_arr2 = $result_unica->toArray();
		$listaContornos = genera_contornos($result_arr2);
		$placemarks = KML_get_placemarks(
			$listaContornos,
			"unica nivel " . $coverageNames[$numero_solape],
			$rutas,
			$nivelVuelo,
			$altMode,
			$appendToFilename = "",
			$coverageLevel = $coverageNames[$numero_solape]
		);
		if (false !== $ret) {
			$coverages_per_level_KML[$numero_solape]["unica nivel " . $coverageNames[$numero_solape]] = $placemarks;
		}
		logger(" D> Tiempo de unica: " . round(microtime(true) - $timer_unica, 2) . " segundos");
		KML_create_from_placemarks($coverages_per_level_KML, $nivelVuelo, $nivelVuelo);
		exit(0);
		*/
	}

	// Estructura para meter los kml de los polígonos en carpetas
	/*
	<Folder>
		<name>Carpeta sin título</name>
		<open>1</open>
		<Placemark>
			<name>Línea de medida</name>
			<styleUrl>#inline</styleUrl>
			<LineString>
				<tessellate>1</tessellate>
				<coordinates>
					-1.481390979352614,40.18070450543358,2014.93172611603 1.102616491258408,37.51228781817466,-4190.136407117736 
				</coordinates>
			</LineString>
		</Placemark>
		<atom:link rel="app" href="https://www.google.com/earth/about/versions/#earth-pro" title="Google Earth Pro 7.3.7.1155"></atom:link>
	</Folder>
	*/
	$kml = "";
	foreach ($coverages_per_level_KML as $numero_solape => $solapes) {
		$kml_per_level = ""; // para cada tipo (mono, doble, triple) guardamos todos los kml
		foreach($solapes as $nombre_grupos_radares => $kml_group) {
			print "\t" . $nombre_grupos_radares . PHP_EOL;
			$kml_per_level .= $kml_group;
		}
		if ( !empty($kml_per_level) ) {
			// luego los metemos en un folder
			$kml .= KML_create_folder( (string) $numero_solape, $kml_per_level);
		}
	}

	// writeKMZ !!!!
	logger(" D> " . "Info memory_usage(" . convertBytes(memory_get_usage(false)) . ") " .
		"Memory_peak_usage(" . convertBytes(memory_get_peak_usage(false)) . ")");
	$timer_diff = microtime(true) - $timer;
	logger(" V> Fin del cálculo de la cobertura multiradar, duración " . timer_unidades($timer_diff));
		
	return $kml;

	exit(0);

	// aquí encarpetar en estructura de folders!
	
	// genera el kml suma de todos los kml guardados
	KML_create_from_placemarks($coverages_per_level_KML, $nivelVuelo, $nivelVuelo);

	$timer_diff = microtime(true) - $timer; // string = date('Y/m/d H:i:s', round(microtime(true) - $timer_multiradar);
	logger(" D> " . "Info memory_usage(" . convertBytes(memory_get_usage(false)) . ") " .
		"Memory_peak_usage(" . convertBytes(memory_get_peak_usage(false)) . ")");

	logger(" V> Fin del cálculo de la cobertura multiradar, duración " . timer_unidades($timer_diff));
	return true;
}

/**
 * Función que genera la cache de intersecciones y sumas de radares, para poder calcular
 * la cobertura multiradar de forma más eficiente.
 * 
 * No se puede optimizar usando las funciones de unionMany y DifferenceMany porque
 * necesitamos guardar todos los resultados intermedios, dado que los usaremos más adelante
 * @param array $vsr array de variaciones sin repetición
 * @param int $vsr_count número total de combinaciones
 * @param array $mr_polygons array de polígonos Martinez-Rueda por radar
 * @param array &$radares_interseccion_cache array de cache de intersecciones (referencia)
 * @param array &$radares_suma_cache array de cache de sumas (referencia)
 * @return bool true si se ha generado la cache correctamente, false si no.
 * 
 */
function populate_cache(array $vsr, int $vsr_count, array $mr_polygons, array &$radares_interseccion_cache, array &$radares_suma_cache)
{
	$radares_interseccion_cache = array();
	$radares_suma_cache = array();

	$count = 1;
	$debug = false;
	foreach ($vsr as $numero_solape => $grupo_solape) {
		logger(" N> == Calculando cache para cobertura nivel {$numero_solape}"); // mono, doble, triple, etc...

		foreach ($grupo_solape as $grupo_radares) {
			logger("$count/$vsr_count ", false);
			$count++;
			// print json_encode($grupo_radares) . " ";
			$count_grupo_radares = count($grupo_radares);
			$nombre_grupo_radares_suma = implode('+', $grupo_radares);
			$nombre_grupo_radares_interseccion = implode('^', $grupo_radares);

			// cacheamos en funcion de cuantos radares haya.
			// si es solo uno, es directo
			if ($count_grupo_radares == 1) {
				$result_interseccion = $mr_polygons[$grupo_radares[0]];
				$result_suma = $mr_polygons[$grupo_radares[0]];
				// si son dos radares, hay que coger los dos (serán los dos primeros)
			} else if ($count_grupo_radares == 2) { // estos nunca estarán en caché
				// PRIMERO CACHEAMOS LA INTERSECCION
				$subject = $mr_polygons[$grupo_radares[0]];
				$clipping = $mr_polygons[$grupo_radares[1]];
				$result_interseccion = MR\Algorithm::intersect($subject, $clipping);
				// LUEGO CACHEAMOS LA SUMA
				$result_suma = MR\Algorithm::union($subject, $clipping);
			} else { // 3 o más
				// los anteriores ya están en la caché, sólo hay que calcular la suma/intersección con el nuevo
				// se cogen todos los radares menos el último y se generan dos listas, subgrupo y el resto.
				$subgrupo_radares = array_slice(
					$grupo_radares,
					0,
					$count_grupo_radares - 1
				);
				// nombre del último radar, para la intersección
				$ultimo_radar = $grupo_radares[$count_grupo_radares - 1]; // resto
				$nombre_subgrupo_radares_interseccion = implode('^', $subgrupo_radares);
				$nombre_subgrupo_radares_suma = implode('+', $subgrupo_radares);

				$subject = $radares_interseccion_cache[$nombre_subgrupo_radares_interseccion];
				if ($debug)
					logger(" D> retrieve interseccion_cache: $nombre_subgrupo_radares_interseccion md5: " . md5(serialize($subject)));
				$clipping = $mr_polygons[$ultimo_radar];
				$result_interseccion = MR\Algorithm::intersect($subject, $clipping);
				if ($debug)
					logger(" D> store interseccion_cache: $nombre_grupo_radares_interseccion md5: " . md5(serialize($result_interseccion)));


				$subject = $radares_suma_cache[$nombre_subgrupo_radares_suma];
				if ($debug)
					logger(" D> retrieve suma_cache: $nombre_subgrupo_radares_suma md5: " . md5(serialize($subject)));
				$clipping = $mr_polygons[$ultimo_radar];
				$result_suma = MR\Algorithm::union($subject, $clipping);
				if ($debug)
					logger(" D> store suma_cache: $nombre_grupo_radares_suma md5: " . md5(serialize($result_suma)));
			}
			if ( $debug ) {
				logger("radares para interseccion: " . $nombre_grupo_radares_interseccion . " ", false);
				logger("radares para suma: " . $nombre_grupo_radares_suma . " ", false);
			}

			$radares_interseccion_cache[$nombre_grupo_radares_interseccion] = $result_interseccion;
			$radares_suma_cache[$nombre_grupo_radares_suma] = $result_suma;
		}
		logger(PHP_EOL, false);
	}
	foreach(array_keys($radares_interseccion_cache) as $k)
		logger(" D> cache interseccion: $k");
	foreach(array_keys($radares_suma_cache) as $k)
		logger(" D> cache suma: $k");
	return true;
}

/*
 * Convierte una lista de coordenadas en polígonos ordenados (dentro/fuera),
 * eliminando los que sean muy pequeños y usando las funciones de conrec.
 */
function genera_contornos($result_arr) {
	debug_print_backtrace(); die("deprecated " . __FUNCTION__ . " in " . __FILE__ . " at line " . __LINE__ . PHP_EOL);

	$listaContornos = array();
    foreach($result_arr as $index => $polygon) {
	if ( 0 == count($polygon) )
	    continue;
	$computeArea = computeArea($polygon);
	// print count($polygon) . "] " . computeArea($polygon) . PHP_EOL;
	// logger(" V> Polígono ($index) con " . count($polygon) . " vértices y área " . round($computeArea,3) . "km2");
	// si eliminamos las áreas pequeñas, puede ser que al crear la cobertura única, esas áreas pequeñas pasen a ser
	// más grandes, y ya no se eliminen.
	if ( $computeArea < 0.1 ) {
	    logger(" I> NO Eliminando polígono ($index) con " . count($polygon) . " vértices y área " . round($computeArea,3) . "km2");
	    //continue;
	}

	$leftCorner = array();
	foreach($polygon as $k => $vertex)
	    $leftCorner = findLeftCorner($vertex[1], $vertex[0], $leftCorner, $polygon, $k);

	$listaContornos[] = array(
	    'level' => -1,
	    'polygon' => $polygon,
	    'inside' => array(),
	    'area' => $computeArea,
	    'leftCorner' => $leftCorner,
	);
    }
    // usaremos is_in_polygon porque $listaContornos tiene '0' y '1' como índices de los
    // vértices en lugar de 'fila' y col.
    $listaContornos = determinaContornos2_sortContornos($listaContornos, 'is_in_polygon');

    return $listaContornos;
}
