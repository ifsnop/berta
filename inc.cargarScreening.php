<?php

// distancias a las palabras indicadas en el fichero desde donde se va a leer la informacion del terreno
CONST DISTANCIA_A_SITE = 5;
CONST DISTANCIA_A_K_FACTOR = 9;
CONST DISTANCIA_A_METHOD = 7;
CONST DISTANCIA_A_TOWER_HEIGHT = 12;
CONST DISTANCIA_A_RANGE = 6;
CONST DISTANCIA_A_TERRAIN_HEIGHT = 14;
CONST DISTANCIA_A_LONGITUDE = 10;
CONST DISTANCIA_A_LATITUDE = 9;
CONST DISTANCIA_A_VERSION = 8;
CONST DISTANCIA_A_CENTRALANGLE = 13;
CONST DISTANCIA_A_HEIGHTREFERENCE = 16;

/*
// VERSION 1
Site LE_PARACUELLOS1
k_factor 1.33
Method 136
TowerHeight 20.24
Range 250.0
TerrainHeight 706.0
720

// VERSION 2
Site EXP_LE_SESOLLES
Version 2
Longitude 2.437613888888889
Latitude 41.77376388888889
CentralAngle k_factor
HeightReference EGM96
k_factor 1.333333
TowerHeight 35.0
Range 250.0
TerrainHeight 1661.2135000000321
360
*/



/**
 * Esta funcion se encarga de abrir el fichero de terrenos y leer la informacion para almacenarla en memoria.
 * @param array $radar información del radar que vamos a procesar
 * @param float $forzarAlcance Forzar un alcance en millas náuticas (que viene de la definición del radar) si no queremos usar el del screening
 * @return array $radar datos del radar con la información del fichero de screening incorporado
 */
function cargarDatosTerreno($radar, float $forzarAlcance = -1.0)
{

	$first_warning_wallnode = true; // para imprimir el aviso de terreno corrupto una sola vez
	$first_warning_distance = true; // para imprimir el aviso de distancia duplicada

	// esta funcion guarda el contenido del fichero en un array 
	if (false === ($contenidoFichero = @file($radar['screening'], FILE_SKIP_EMPTY_LINES | FILE_IGNORE_NEW_LINES))) {
		logger(" E> No ha sido posible leer el fichero: >" . $radar['screening'] . "<");
		exit(-1);
	}

	if ("Version 2" == trim($contenidoFichero[1])) {
		logger(" V> Procesando fichero screening versión 2");
		$screening = array(
			'site'     	=> substr(trim($contenidoFichero[0]), DISTANCIA_A_SITE),
			'version' => (int) substr(trim($contenidoFichero[1]), DISTANCIA_A_VERSION),
			'longitude' 	=> doubleval(substr(trim($contenidoFichero[2]), DISTANCIA_A_LONGITUDE)),
			'latitude' 	=> doubleval(substr(trim($contenidoFichero[3]), DISTANCIA_A_LATITUDE)),
			'centralAngle' => substr(trim($contenidoFichero[4]), DISTANCIA_A_CENTRALANGLE),
			'heightReference' => substr(trim($contenidoFichero[5]), DISTANCIA_A_HEIGHTREFERENCE),
			'k-factor' 	=> doubleval(substr(trim($contenidoFichero[6]), DISTANCIA_A_K_FACTOR)),
			'towerHeight' 	=> doubleval(substr(trim($contenidoFichero[7]), DISTANCIA_A_TOWER_HEIGHT)),
			'range' 	=> doubleval(substr(trim($contenidoFichero[8]), DISTANCIA_A_RANGE)),
			'terrainHeight' => doubleval(substr(trim($contenidoFichero[9]), DISTANCIA_A_TERRAIN_HEIGHT)),
			'totalAzimuths' => (int) trim($contenidoFichero[10]),
			'method'   	=> false,
			'listaAzimuths' => array(),
		);
		$lineaActual = 11; // primera línea donde comienzan los AZIMUT
	} else {
		logger(" V> Procesando fichero screening versión 1");
		$screening = array(
			'site'     	=> substr(trim($contenidoFichero[0]), DISTANCIA_A_SITE),
			'k-factor' 	=> doubleval(substr(trim($contenidoFichero[1]), DISTANCIA_A_K_FACTOR)),
			'method'   	=> doubleval(substr(trim($contenidoFichero[2]), DISTANCIA_A_METHOD)),
			'towerHeight' 	=> doubleval(substr(trim($contenidoFichero[3]), DISTANCIA_A_TOWER_HEIGHT)),
			'range' 	=> doubleval(substr(trim($contenidoFichero[4]), DISTANCIA_A_RANGE)),
			'terrainHeight' => doubleval(substr(trim($contenidoFichero[5]), DISTANCIA_A_TERRAIN_HEIGHT)),
			'totalAzimuths' => (int) trim($contenidoFichero[6]),
			'latitude' => false,
			'longitude' => false,
			'version' => 1,
			'centralAngle' => false,
			'heightReference' => false,
			'listaAzimuths' => array(),
		);
		$lineaActual = 7; // primera línea donde comienzan los AZIMUT
	}
	//TRATAMIENTO DE LOS DATOS DE LOS AZIMUTHS (Segunda parte del fichero)
	// recorremos los azimuths
	logger(" V> Cargando contenido de >" . $radar['screening'] . "< totalAzimuths(" . $screening['totalAzimuths'] . ")");
	$timer = microtime(true);
	$acimutOld = 0;
	for ($i = 0; $i < $screening['totalAzimuths']; $i++) {
		$listaObstaculos = array();
		// buscamos el bloque que comienza por AZIMUTH
		while ("AZIMUTH" != substr($contenidoFichero[$lineaActual], 0, 7)) {
			$lineaActual++;
		}
		// anotamos el acimut actual
		$acimutActual = (int) substr($contenidoFichero[$lineaActual++], 8);

		// comprobación de coherencia entre acimuts
		if (($acimutActual - $acimutOld) > 1) {
			logger("E> Problema leyendo el fichero >" . $radar['screening'] .
				"<, salto entre los acimut {$acimutOld} y {$acimutActual}");
			exit(-1);
		}
		// anotamos el número de obstaculos para ese acimut
		$contadorObstaculos = $contenidoFichero[$lineaActual++];

		if (0 == $contadorObstaculos) {
			logger(" E> El azimut $i no tiene obstáculos definidos");
			exit(-1);
		}

		// insertamos el radar como primer obstaculo, para resolver el caso de que
		// el primer obstaculo este muy alejado. 

		$listaObstaculos[] = array(
			'angulo' => 0,
			'altura' => $screening['towerHeight'] + $screening['terrainHeight']
		);

		// recorre el numero de obstaculos para cada azimut
		$oldAngulo = false;
		for ($j = 0; $j < $contadorObstaculos; $j++) {
			$pattern = '/\(\s+(\S+)\s+\|\s+(\S+)\s+\)/';
			if (false === ($cuenta = preg_match($pattern, $contenidoFichero[$lineaActual], $salida))) {
				// $salida tiene 3 posiciones, las dos ultimas contienen los strings que necesitamos
				logger(" E> Error durante la comparacion linea($lineaActual) contenido(" . $contenidoFichero[$lineaActual] . ")");
				debug_print_backtrace(); 
				fwrite(STDERR, "Unexpected error: " . __FUNCTION__ . " in " . __FILE__ . " at line " . __LINE__ . PHP_EOL);
				exit(-1);
			}
			// convierte el string a numero y los almacena en el array
			$angulo = doubleval($salida[1]);
			$altura = doubleval($salida[2]);

			/*
	    if ( $oldAngulo == $angulo ) { // si hay un ángulo con dos altitudes, quitar el último insertado
			if ( $first_warning_distance ) {
		    	logger(" V> Distancia al radar duplicada, eliminando");
		    	$first_warning_distance = false;
			}
			array_pop($listaObstaculos);
	    }
	    $oldAngulo = $angulo;
	    if ( $altura >= 32627 ) { // && $j >= ($contadorObstaculos - 2) ) {
			if ( $first_warning_wallnode ) {
		    	logger(" !> Ignorando los dos últimos obstáculos, desde PredictV23.10 están corruptos! j:{$j} contadorObstaculos:{$contadorObstaculos}");
		    	$first_warning_wallnode = false;
			}
			break;
	    }
		*/

			$listaObstaculos[] = array(
				'angulo' => $angulo,
				'altura' => $altura
			);
			$lineaActual++;
		}
		// anadimos un obstaculo mas por que hemos insertado el radar como primer obstaculo
		$screening['listaAzimuths'][$acimutActual] = $listaObstaculos;
		// actualizamos el contador
		$acimutOld = $acimutActual;
	} // end for exterior


	logger(" I> Cargado contenido de >" . $radar['screening'] . "< en " . round(microtime(true) - $timer, 3) . "s");
	// Camprobacion extra para algunos valores
	//    if ($screening['k-factor'] <= 0) {
	//        $screening['radioTerrestreAumentado'] = (4/3) * RADIO_TERRESTRE;
	//    } else {
	$screening['radioTerrestreAumentado'] = $screening['k-factor'] * RADIO_TERRESTRE;
	//    }

	if ($forzarAlcance > 0) {
		// utiliza el alcance que pasamos a la función
		$radar['range'] = $forzarAlcance * MILLA_NAUTICA_EN_METROS;
		logger(" I> Se ha forzado un alcance de " . ($radar['range'] / MILLA_NAUTICA_EN_METROS) .
			"NM / " . $radar['range'] . "m");
	} else {
		// utiliza el alcance definido en el fichero de screening
		$radar['range'] = $screening['range'] * MILLA_NAUTICA_EN_METROS;
		logger(" I> El alcance definido en el fichero de terreno es de " . ($radar['range'] / MILLA_NAUTICA_EN_METROS) .
			"NM / " . $radar['range'] . "m");
	}

	$radar['screening_file'] = $radar['screening'];
	$radar['screening'] = $screening;

	/*
    if ( false !== strpos($radar['radar'], "-psr") ) {
        logger(" I> Detectado PSR, ajustando nombre, REVISAR porque ahora screening|site no se usa"); exit(-1);
        $radar['screening']['site'] = $radar['screening']['site'] . "_PSR";
    }
*/
	return $radar;
}
