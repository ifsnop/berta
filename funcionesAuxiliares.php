<?php



/**
 * Muestra el menu al usuario
 * @return number
 */
 function menu (){
 	
 	$op =0;
	
	do {
		echo "Bienvenido al programa de calculo de coberturas por linea vista". PHP_EOL;
		echo "1. CALCULAR" . PHP_EOL;
		echo "0. SALIR" . PHP_EOL;
		fscanf (STDIN, "%d\n", $op);
		
	}while ($op < 0 || $op > 1);
	
	return $op;
}
 



/**
 * Imprime por pantalla los obstaculos de todos los azimuths
 * @param array $radar
 */
function imprimeObstaculos($radar) {
	print "<pre>";
	foreach ( $radar['listaAzimuths'] as $k => $listaObstaculos ){
		print "acimut: $k" . PHP_EOL;
		foreach ( $listaObstaculos as $obstaculo ) {
			print "angulo: " . $obstaculo['angulo'] . " altura: " . $obstaculo['altura'] . PHP_EOL;
		}
		/*
		 * if (is_array($valor)){
		 print "Azimuth"."$clave ". PHP_EOL;
		 print_r($valor);
		 }
		 */
	}
	print "</pre>";

	return;
}

// DO NOT TOUCH !!
/**
 * muestra por pantalla la informacion de un radar
 * @param array $radar
 */
function mostrarRadar($radar){


	foreach ($radar as $clave => $valor) {
		if (is_array($valor)) {
			print "$clave" . PHP_EOL;
			print_r($valor);
		} else {

			echo " $clave: $valor<br />\n";
		}
	}
}

/**
 * Muestra por pantalla al usuario, las posibles opciones para el modo de altitud
 */
function mostrarAltitudMode(){
	
	echo "0. Subject to the ground" . PHP_EOL;
	echo "1. Subject to seabed" . PHP_EOL;
	echo "2. Relative to soil". PHP_EOL;
	echo "3. Relative to the seabed" . PHP_EOL;
	echo "4. Absolute" .PHP_EOL;
}


function altitudeModetoString ($altitudeMode){
	
	$modo = "";
	switch($altitudeMode){
		case 0: $modo = "Subject to the ground"; break;  
		case 1: $modo = "Subject to seabed"; break;
		case 2: $modo = "Relative to soil"; break;
		case 3: $modo = "Relative to the seabed"; break;
		case 4: $modo = "Absolute"; break;
	}
	return $modo;
}


/**
 *  Funcion para pedir el usuario los valores con los que se quiere realizar el calculo
 *  
 * @param int $flMin : nivel de vuelo minimo introducido por el usuario
 * @param int $flMax : nivel de vuelo maximo introducido por el usuario
 * @param int $paso  : forma en la que va creciendo el nivel de vuelo
 * @param int $modoAltitud : modo de altitud seleccionada
 * @param bool $poligono 
 * @param array $lugares  : array con los nombres de todos los radares para los que se quiere calcular la cobertura
 */
function pedirDatosUsuario(&$flMin, &$flMax, &$paso, &$altitudeMode, &$poligono, &$lugares){
	
	do{ // pedimos los niveles de vuelo y nos aseguramos de que min < max
		echo "Indica el nivel de vuelo minimo:  ";
		fscanf (STDIN, "%d\n", $flMin);
	    // $flMin = fLtoMeters($flMin);
	     //echo " FL min en metros: " . $flMin;
	     
		echo "Indica el nivel de vuelo maximo:  ";
		fscanf (STDIN, "%d\n", $flMax);
		//$flMax = fLtoMeters($flMax);
		//echo " FL min en metros: " . $flMin;
	} while ($flMin > $flMax);
	
	
	echo "Indica el paso:  ";
	fscanf (STDIN, "%d\n", $paso); 
	
	
	do{ // pedimos el modo de altitud y nos aseguramos de que es uno de los predefinidos
		mostrarAltitudMode();
		echo "Indica el modo de altitud: ";
		fscanf (STDIN, "%d\n", $altitudeMode);
	}
	while ($altitudeMode <0 || $altitudeMode >4);
	
	
	echo "Indica si quieres la opcion poligono: (s/n) " .PHP_EOL;
	$line = trim(fgets(STDIN));
	$line = strtolower($line);
	
	if ($line == "n")
		$poligono = FALSE;
	else 
		$poligono = TRUE;
	
    echo "Indica con que radares quieres trabajar:  ";
    $linea = strtolower(trim(fgets(STDIN)));
    $lugares = explode(" ", $linea);
}









