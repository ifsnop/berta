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
function cargarDatosTerreno ($radar, $forzarAlcance = 0) {

    // esta funcion guarda el contenido del fichero en un array 
    if ( false === ($contenidoFichero = file($radar['screening'], FILE_SKIP_EMPTY_LINES | FILE_IGNORE_NEW_LINES))) {
        die("ERROR no ha sido posible leer el fichero: " . $radar['screening'] . PHP_EOL);
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
    print "cargando contenido de >" . $radar['screening'] . "< totalAzimuths(" . $screening['totalAzimuths'] . ")" . PHP_EOL;
    for($i = 0; $i < $screening['totalAzimuths']; $i++){
        $listaObstaculos = array();
	// buscamos el bloque que comienza por AZIMUTH
	while ( "AZIMUTH" != substr($contenidoFichero[$lineaActual], 0, 7) ){
	    $lineaActual++;
	}
	print ".";
	// anotamos el acimut actual
	$acimutActual = substr($contenidoFichero[$lineaActual++], 8) + 0;
	// anotamos el número de obstaculos para ese acimut
	$contadorObstaculos = $contenidoFichero[$lineaActual++];
        // insertamos el radar como primer obstaculo, para resolver el caso de que
        // el primer obstaculo este muy alejado. En matlab no se pinta nada hasta que no
        // llega al primer obstaculo.
        $listaObstaculos[] = array(
            'angulo' => 0,
            'altura' => $screening['towerHeight'] + $screening['terrainHeight'],
            'estePtoTieneCobertura' => false
        );

        // recorre el numero de obstaculos para cada azimut
	for ($j = 0; $j < $contadorObstaculos; $j++) { 
	    $pattern = '/\(\s+(\S+)\s+\|\s+(\S+)\s+\)/';
	    if ( false === ($cuenta = preg_match($pattern, $contenidoFichero[$lineaActual], $salida)) && (3 == $cuenta) ) {
	        // $salida tiene 3 posiciones, las dos ultimas contienen los strings que necesitamos
		die("ERROR durante la comparacion linea($lineaActual) contenido(" . $contenidoFichero[$lineaActual] . ")");
	    }
	    // convierte el string a numero y los almacena en el array
	    $listaObstaculos[] = array(
	        'angulo' => floatval ($salida[1]), 
		'altura' => floatval ($salida[2]), 
		'estePtoTieneCobertura' => false);
		$lineaActual++;
	}
	// anadimos un obstaculo mas por que hemos insertado el radar como primer obstaculo
	$screening['listaAzimuths'][$acimutActual] = $listaObstaculos;	
    } // end for exterior
    print PHP_EOL;

    // Camprobacion extra para algunos valores
    if ($screening['k-factor'] <= 0) {
        $screening['radioTerrestreAumentado'] = (4/3) * RADIO_TERRESTRE;
    } else {
        $screening['radioTerrestreAumentado'] = $screening['k-factor'] * RADIO_TERRESTRE;
    }

    if ( $forzarAlcance > 0 ) {
        // utiliza el alcance que pasamos a la función
	$radar['range'] = $forzarAlcance * MILLA_NAUTICA_EN_METROS;
    } else {
        // utiliza el alcance definido en el fichero de screening
        $radar['range'] = $screening['range'] * MILLA_NAUTICA_EN_METROS;
    }

    echo "El alcance del radar es de " .
        ($radar['range'] / MILLA_NAUTICA_EN_METROS) . 
        "NM / " . $radar['range'] . "m" . PHP_EOL;

    $radar['screening_file'] = $radar['screening'];
    $radar['screening'] = $screening;

    //print_r($radar);
    
    if ( false !== strpos($radar['radar'], "-psr") ) {
        print "Detectado PSR" . PHP_EOL;
        $radar['screening']['site'] = $radar['screening']['site'] . "_PSR";
    }
    return $radar;
}
