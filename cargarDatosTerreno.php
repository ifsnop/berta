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
 * @return array $radar con la informacion del radar leido de fichero
 */
function cargarDatosTerreno ($nombreFichero = NULL, &$radioTerrestreAumentado) {

	// esta funcion guarda el contenido del fichero en un array 
	if ( false === ($contenidoFichero = file($nombreFichero, FILE_SKIP_EMPTY_LINES | FILE_IGNORE_NEW_LINES))) {
		print "error, no ha sido posible leer el fichero" . PHP_EOL;
		exit;
	}
	
	// TRATAMIENTO DE LA INFORMACION CAPTURADA (Primera parte del fichero)
	$radar= array(
		'site'     			=> substr(trim($contenidoFichero[0]), DISTANCIA_A_SITE),
		'k-factor' 			=> doubleval(substr(trim($contenidoFichero[1]), DISTANCIA_A_K_FACTOR)),
		'method'   			=> doubleval(substr(trim($contenidoFichero[2]), DISTANCIA_A_METHOD)),
		'towerHeight' 	    => doubleval(substr(trim($contenidoFichero[3]), DISTANCIA_A_TOWER_HEIGHT)),
		'range' 			=> doubleval(substr(trim($contenidoFichero[4]), DISTANCIA_A_RANGE)),
		'terrainHeight' 	=> doubleval(substr(trim($contenidoFichero[5]), DISTANCIA_A_TERRAIN_HEIGHT)),
		'totalAzimuths' 	=> (integer) trim($contenidoFichero[6]),
		'listaAzimuths' 	=> array(),
	);
		
		
	//TRATAMIENTO DE LOS DATOS DE LOS AZIMUTHS (Segunda parte del fichero)
	
	$lineaActual = 7; // primera línea donde comienzan los AZIMUT
	
	// recorremos los azimuths
	for($i = 0; $i < $radar['totalAzimuths']; $i++){
			
			$listaObstaculos = array();
			// buscamos el bloque que comienza por AZIMUTH
			while ("AZIMUTH" != substr($contenidoFichero[$lineaActual], 0, 7)){
				  $lineaActual++;
			}
			// anotamos el acimut actual
			list(, $acimutActual) = explode(' ', $contenidoFichero[$lineaActual++]);
			// normaliza el número de acimuts para que siempre sea de 0 a 360º
			$acimutActual = (integer)(round(($acimutActual / $radar['totalAzimuths']) * TOTAL_AZIMUTHS));
			
			// anotamos el número de obstaculos para ese acimut
			$contadorObstaculos = $contenidoFichero[$lineaActual++];
			
			// recorre el numero de obstaculos para cada azimuths
			for ($j = 0; $j < $contadorObstaculos; $j++) { 
				$pattern = '/\(\s+(\S+)\s+\|\s+(\S+)\s+\)/';
				if ( false === ($cuenta = preg_match($pattern, $contenidoFichero[$lineaActual], $salida)) && (3 == $cuenta) ) {
					// $salida tiene 3 posiciones, las dos ultimas contienen los strings que necesitamos
					echo "Error durante la comparacion"; 
					die("Error durante la comparacion linea($lineaActual) contenido(" . $contenidoFichero[$lineaActual] . ")");
				}
				// convierte el string a numero y los almacena en el array
				$listaObstaculos[$j] = array('angulo' => floatval ($salida[1]), 'altura' => floatval ($salida[2]), 'estePtoTieneCobertura' => false);
				$lineaActual++;
			}
			$radar['listaAzimuths'][$acimutActual] = $listaObstaculos;	
	} // end for exterior
	
	// Camprobacion extra para algunos valores
	if ($radar['k-factor'] <=0)
		$radioTerrestreAumentado= (4/3) * RADIO_TERRESTRE;
		else
			$radioTerrestreAumentado= $radar['k-factor']* RADIO_TERRESTRE;
	
	
	if ($radar['range']<=0){
		 echo ' Introduce el alcance del radar (NM): ';
				fscanf(STDIN, "%d/n", $rango);
				$radar['range']= $rango * MILLA_NAUTICA_EN_METROS;
	}
	 else
		$radar['range']=$radar['range'] * MILLA_NAUTICA_EN_METROS;
			
	return $radar;	
	
}// end function cargarDatosTerreno
