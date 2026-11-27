<?php
declare(strict_types=1);

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


function crearRdbFromScreeningV2(string $filepath) {

    if (false === ($contenidoFichero = @file($filepath, FILE_SKIP_EMPTY_LINES | FILE_IGNORE_NEW_LINES))) {
	logger(" E> No ha sido posible leer el fichero: >" . $filepath . "<");
	exit(-1);
    }

    if ("Version 2" != trim($contenidoFichero[1])) {
	logger(" E> Solo válido para screenings v2");
	exit(-1);
    }

    logger(" D> Procesando fichero screening versión 2");
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
    );

    logger(" D> Generando .rdb asociado");
    [$lat, $lon] = DECtoDMS($screening['latitude'], $screening['longitude']);
    $screening['name'] = strtolower(preg_replace('/\s+/', '_', $screening['site']));
    $content = "radarIdentifier: \"{$screening['name']}\"
radarLatitude: \"{$lat}\"
radarLongitude: \"{$lon}\"
radarGroundAltitude: \"{$screening['terrainHeight']}\"
radarType: \"ModeS\"
antennaType: \"single\"
radarRevolutionTime: \"-1\"
screening: \"{$filepath}\"
surveillanceMap: \"\"
nodeNumber: \"\"
interrogationMode: \"Single Interrogation mode\"
acquisitionMode: \"Normal Acquisition mode\"
primaryElectricalHeight: \"0\"
primaryConeOfSilence: \"45.000000\"
primaryMinimumRange: \"0.50\"
primaryMaximumRange: \"100\"
secondaryElectricalHeight: \"{$screening['towerHeight']}\"
secondaryConeOfSilence: \"45.000000\"
secondaryMinimumRange: \"0.250000\"
secondaryMaximumRange: \"{$screening['range']}\"
coordinateType: \"polar\"
validationBit: \"0\"
rangePrecision: \"256\"
azimuthPrecision: \"16384\"
xPrecision: \"19.770000\"
yPrecision: \"19.770000\"
usage: \"civil en-route\"
sic: \"-1\"
sac: \"-1\"
uap: \"-1\"
appl: \"-1\"
comments: \"RADAR: \"
";

    file_put_contents($screening['name'] . ".rdb", $content);

    logger(" N> Generado fichero >{$screening['name']}.rdb<");
    exit(0);

}

/**
 * Error de formato en un fichero de screening: informa del fichero, del registro y de su contenido, y termina.
 * Un screening mal formado no se corrige ni se ignora en silencio, porque daría coberturas falsas.
 *
 * @param string $fichero ruta del screening
 * @param array $lineas contenido del fichero (sin líneas vacías)
 * @param int $indice registro en $lineas donde se detectó el problema (se muestra empezando en 1)
 * @param string $motivo qué se esperaba
 */
function errorScreening(string $fichero, array $lineas, int $indice, string $motivo): never
{
	$contenido = $lineas[$indice] ?? '(fin de fichero)';
	logger(" E> Screening >{$fichero}< mal formado en el registro " . ($indice + 1) .
		" (sin contar líneas vacías): {$motivo}. Contenido: >" . trim((string) $contenido) . "<");
	exit(-1);
}

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
		logger(" D> Procesando fichero screening versión 2");
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
			'version'	=> 1,
			'listaAzimuths' => array(),
		);
		$lineaActual = 11; // primera línea donde comienzan los AZIMUT
	} else {
		logger(" D> Procesando fichero screening versión 1");
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
	logger(" V> Cargado contenido de >" . $radar['screening'] . "< totalAzimuths(" . $screening['totalAzimuths'] . ")");
	$timer = microtime(true);
	for ($i = 0; $i < $screening['totalAzimuths']; $i++) {
		$listaObstaculos = array();
		// buscamos el bloque que comienza por AZIMUTH (sin salirnos del fichero)
		while (isset($contenidoFichero[$lineaActual]) && "AZIMUTH" != substr($contenidoFichero[$lineaActual], 0, 7)) {
			$lineaActual++;
		}
		if (!isset($contenidoFichero[$lineaActual])) {
			errorScreening($radar['screening'], $contenidoFichero, $lineaActual,
				"el fichero termina tras {$i} de {$screening['totalAzimuths']} acimuts");
		}
		// anotamos el acimut actual: deben ir seguidos desde 0 (0, 1, 2... totalAzimuths-1),
		// porque el resto del cálculo usa el número de acimut como índice
		if (1 !== preg_match('/^AZIMUTH\s+(\d+)\s*$/', trim($contenidoFichero[$lineaActual]), $m)) {
			errorScreening($radar['screening'], $contenidoFichero, $lineaActual, "se esperaba 'AZIMUTH <número>'");
		}
		$acimutActual = (int) $m[1];
		if ($acimutActual !== $i) {
			errorScreening($radar['screening'], $contenidoFichero, $lineaActual,
				"se esperaba el acimut {$i} y aparece el {$acimutActual}");
		}
		$lineaActual++;

		// anotamos el número de obstaculos para ese acimut: entero positivo
		$contadorObstaculos = trim((string) ($contenidoFichero[$lineaActual] ?? ''));
		if (!ctype_digit($contadorObstaculos) || 0 == (int) $contadorObstaculos) {
			errorScreening($radar['screening'], $contenidoFichero, $lineaActual,
				"se esperaba el número de obstáculos (entero mayor que 0) del acimut {$acimutActual}");
		}
		$contadorObstaculos = (int) $contadorObstaculos;
		$lineaActual++;

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
			// la línea debe existir, tener la forma "( ángulo | altura )" y ambos valores deben ser numéricos;
			// antes una línea que no encajaba (preg_match devuelve 0) se aceptaba como el obstáculo (0, 0)
			if (!isset($contenidoFichero[$lineaActual])) {
				errorScreening($radar['screening'], $contenidoFichero, $lineaActual,
					"el fichero termina tras " . $j . " de {$contadorObstaculos} obstáculos del acimut {$acimutActual}");
			}
			if (1 !== preg_match($pattern, $contenidoFichero[$lineaActual], $salida)
				|| !is_numeric($salida[1]) || !is_numeric($salida[2])) {
				// $salida tiene 3 posiciones, las dos ultimas contienen los strings que necesitamos
				errorScreening($radar['screening'], $contenidoFichero, $lineaActual,
					"se esperaba un obstáculo '( ángulo | altura )' numérico (" . ($j + 1) .
					" de {$contadorObstaculos} del acimut {$acimutActual})");
			}
			// convierte el string a numero y los almacena en el array
			$angulo = doubleval($salida[1]);
			$altura = doubleval($salida[2]);

			$listaObstaculos[] = array(
				'angulo' => $angulo,
				'altura' => $altura
			);
			$lineaActual++;
		}
		// anadimos un obstaculo mas por que hemos insertado el radar como primer obstaculo
		$screening['listaAzimuths'][$acimutActual] = $listaObstaculos;
	} // end for exterior

	logger(" I> Cargado contenido de >" . $radar['screening'] . "< en " . round(microtime(true) - $timer, 3) . "s");
	$screening['radioTerrestreAumentado'] = $screening['k-factor'] * BERTA_RADIO_TERRESTRE;

	// tenemos tres alcances posibles: el que viene definido en el screening, el definido en el .rdb o uno forzado por línea de comandos
	// vamos a descartar siempre el que viene en el screening, y usaremos el que viene del .rdb
	if ($forzarAlcance > 0) {
		// utiliza el alcance que pasamos a la función
		$radar['range'] = $forzarAlcance;
		logger(" I> Se ha forzado un alcance de " . $radar['range'] .
			"NM / " . ($radar['range'] * BERTA_MILLA_NAUTICA_EN_METROS) . "m");
	} else {
		// utiliza el alcance definido en el .rdb para un radar secundario
		$radar['range'] = $radar['secondaryMaximumRange'];
		logger(" I> El alcance definido en el fichero del radar es {$radar['range']}NM / " .
			$radar['range'] * BERTA_MILLA_NAUTICA_EN_METROS . "m");
	}
	$screening['range'] = $radar['range'];
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
