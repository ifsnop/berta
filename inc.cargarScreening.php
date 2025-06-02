<?php

// distancias a las palabras indicadas en el fichero desde donde se va a leer la informacion del terreno
CONST DISTANCIA_A_SITE = 5;
CONST DISTANCIA_A_K_FACTOR = 9;
CONST DISTANCIA_A_METHOD = 7;
CONST DISTANCIA_A_TOWER_HEIGHT = 12;
CONST DISTANCIA_A_RANGE = 6;
CONST DISTANCIA_A_TERRAIN_HEIGHT = 14;

/**
 * Esta funcion se encarga de abrir el fichero de terrenos y leer la informacion para almacenarla en memoria.
 * @param array $radar información del radar que vamos a procesar
 * @param int $forzarAlance Alcance por defecto del radar en caso de no existir dato en el fichero de screening
 * @return array $radar datos del radar con la información del fichero de screening incorporado
 */
function cargarDatosTerreno ($radar, $forzarAlcance = false) {

    $debug = false;

    $first_warning_wallnode = true; // para imprimir el aviso de terreno corrupto una sola vez
    $first_warning_distance = true; // para imprimir el aviso de distancia duplicada

    // esta funcion guarda el contenido del fichero en un array 
    if ( false === ($contenidoFichero = @file($radar['screening'], FILE_SKIP_EMPTY_LINES | FILE_IGNORE_NEW_LINES))) {
        logger(" E> No ha sido posible leer el fichero: >" . $radar['screening'] . "<"); exit(-1);
    }
    // TRATAMIENTO DE LA INFORMACION CAPTURADA (Primera parte del fichero)
    $screening = array(
	'site'     	=> substr(trim($contenidoFichero[0]), DISTANCIA_A_SITE),
	'k-factor' 	=> doubleval(substr(trim($contenidoFichero[1]), DISTANCIA_A_K_FACTOR)),
	'method'   	=> doubleval(substr(trim($contenidoFichero[2]), DISTANCIA_A_METHOD)),
	'towerHeight' 	=> doubleval(substr(trim($contenidoFichero[3]), DISTANCIA_A_TOWER_HEIGHT)),
	'range' 	=> doubleval(substr(trim($contenidoFichero[4]), DISTANCIA_A_RANGE)),
	'terrainHeight' => doubleval(substr(trim($contenidoFichero[5]), DISTANCIA_A_TERRAIN_HEIGHT)),
	'totalAzimuths' => (integer) trim($contenidoFichero[6]),
	'listaAzimuths' => array(),
    );
	
    //TRATAMIENTO DE LOS DATOS DE LOS AZIMUTHS (Segunda parte del fichero)
    $lineaActual = 7; // primera línea donde comienzan los AZIMUT

    // recorremos los azimuths
    logger(" V> Cargando contenido de >" . $radar['screening'] . "< totalAzimuths(" . $screening['totalAzimuths'] . ")");
    $timer = microtime(true);
    $acimutOld = 0;
    for( $i = 0; $i < $screening['totalAzimuths']; $i++ ) {
        $listaObstaculos = array();
	// buscamos el bloque que comienza por AZIMUTH
	while ( "AZIMUTH" != substr($contenidoFichero[$lineaActual], 0, 7) ){
	    $lineaActual++;
	}
	// anotamos el acimut actual
	$acimutActual = substr($contenidoFichero[$lineaActual++], 8) + 0;

	// comprobación de coherencia entre acimuts
        if ( ($acimutActual - $acimutOld) > 1 ) {
	    logger( "E> Problema leyendo el fichero >" . $radar['screening'] .
                "<, salto entre los acimut {$acimutOld} y {$acimutActual}");
	    exit(-1);
        }
	// anotamos el número de obstaculos para ese acimut
	$contadorObstaculos = $contenidoFichero[$lineaActual++];

	if ( 0 == $contadorObstaculos ) {
	    logger(" E> El azimut $i no tiene obstáculos definidos"); exit(-1);
	}

        // insertamos el radar como primer obstaculo, para resolver el caso de que
        // el primer obstaculo este muy alejado. En matlab no se pinta nada hasta que no
        // llega al primer obstaculo.
        $listaObstaculos[] = array(
            'angulo' => 0,
            'altura' => $screening['towerHeight'] + $screening['terrainHeight'],
            'estePtoTieneCobertura' => false
        );
	if ( $debug ) print "Azimut: $i" . PHP_EOL;
        // recorre el numero de obstaculos para cada azimut
	$oldAngulo = false;
	for ($j = 0; $j < $contadorObstaculos; $j++) {
	    $pattern = '/\(\s+(\S+)\s+\|\s+(\S+)\s+\)/';
	    if ( false === ($cuenta = preg_match($pattern, $contenidoFichero[$lineaActual], $salida)) && (3 == $cuenta) ) {
		// $salida tiene 3 posiciones, las dos ultimas contienen los strings que necesitamos
		logger(" E> Error durante la comparacion linea($lineaActual) contenido(" . $contenidoFichero[$lineaActual] . ")");
		exit(-1);
	    }
	    // convierte el string a numero y los almacena en el array
	    $angulo = floatval( $salida[1] );
	    $altura = floatval( $salida[2] );
	    if ( $debug ) print "Ángulo: $angulo" . PHP_EOL . "Altura: $altura" . PHP_EOL;
	    if ( $i==40 && $debug ) print_r($listaObstaculos);

	    // Si es un wallnode, no lo insertamos y abortamos
	    if ( $altura >= 32627 ) { // && $j >= ($contadorObstaculos - 2) ) { // no hay montaña mas alta de 9km en la tierra
		if ( $first_warning_wallnode ) {
		    logger(" !> Ignorando los últimos obstáculos, desde PredictV23.10 están corruptos! j:{$j} contadorObstaculos:{$contadorObstaculos}");
		    $first_warning_wallnode = false;
		}
		break;
	    }

	    // si hay un ángulo con dos altitudes, nos quedamos con el último, que estará mas alto
	    if ( $oldAngulo == $angulo ) { 
		if ( $first_warning_distance ) {
		    logger(" V> Distancia al radar duplicada, omitiendo");
		    $first_warning_distance = false;
		}
		array_pop($listaObstaculos);
	    }
	    if ( $i==367 && $debug ) print_r($listaObstaculos);
	    $oldAngulo = $angulo;

/*
	    if ( $altura >= 32627 ) { // && $j >= ($contadorObstaculos - 2) ) { // no hay montaña mas alta de 9km en la tierra
		if ( $first_warning_wallnode ) {
		    logger(" !> Ignorando los últimos obstáculos, desde PredictV23.10 están corruptos! j:{$j} contadorObstaculos:{$contadorObstaculos}");
		    $first_warning_wallnode = false;
		}
		break;
	    }
*/
	    $listaObstaculos[] = array(
		'angulo' => $angulo,
		'altura' => $altura,
		'estePtoTieneCobertura' => false);
		$lineaActual++;

	    if ( $i==367 && $debug ) { print_r($listaObstaculos); print PHP_EOL; }


	}
	// anadimos un obstaculo mas por que hemos insertado el radar como primer obstaculo
	$screening['listaAzimuths'][$acimutActual] = $listaObstaculos;

	// actualizamos el contador
	$acimutOld = $acimutActual;

    } // end for exterior


    logger(" I> Cargado contenido de >" . $radar['screening'] . "< en " . round(microtime(true)-$timer,3) . "s");
    // Camprobacion extra para algunos valores
    if ($screening['k-factor'] <= 0) {
        $screening['radioTerrestreAumentado'] = (4/3) * RADIO_TERRESTRE;
    } else {
        $screening['radioTerrestreAumentado'] = $screening['k-factor'] * RADIO_TERRESTRE;
    }

    if ( false !== $forzarAlcance && $forzarAlcance > 0 ) {
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
