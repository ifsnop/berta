<?php
/**
 * Muestra el menu por pantalla al usuario
 * 
 * @return number
 */
 function menu(){
 	
 	$op = 0;
	
	do {
		echo "Bienvenido al programa de calculo de coberturas por linea vista". PHP_EOL;
		echo "1. CALCULAR" . PHP_EOL;
		echo "0. SALIR" . PHP_EOL;
		fscanf (STDIN, "%d\n", $op);
		
	}while ($op < 0 || $op > 1);
	
	return $op;
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

/**
 * Convierte altitudMode en un string
 * 
 * @param int $altitudeMode (ENTRADA)
 * @return string
 */
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
 * Funcion para pedir al usuario los valores con los que se quiere realizar el calculo
 * 
 * @param int $flMin : nivel de vuelo minimo introducido por el usuario (ENTRADA/SALIDA)
 * @param int $flMax : nivel de vuelo maximo introducido por el usuario (ENTRADA/SALIDA)
 * @param float $paso  : forma en la que va creciendo el nivel de vuelo   (ENTRADA/SALIDA)
 * @param int $altitudeMode : modo de altitud seleccionada (ENTRADA/SALIDA)
 * @param boolean $poligono (ENTRADA/SALIDA)
 * @param array $lugares : array con los nombres de todos los radares para los que se quiere calcular la cobertura (ENTRADA/SALIDA)
 * @param boolean $ordenarPorRadar (true = ordenar los ficheros por radar, false ordenar por FL)
 */
function pedirDatosUsuario(&$flMin, &$flMax, &$paso, &$altitudeMode, &$poligono, &$lugares, &$ordenarPorRadar){

    do {
	echo "Indica el nivel de vuelo minimo (FL):  ";
	fscanf (STDIN, "%d\n", $flMin);
	echo "Indica el nivel de vuelo maximo (FL):  ";
	fscanf (STDIN, "%d\n", $flMax);

    } while ($flMin > $flMax);

    if ( $flMin == $flMax ) {
        $paso = 100;
    } else {
        echo "Indica el paso (pies*100):  ";
        fscanf (STDIN, "%d\n", $paso); 
    }

    do { 
	mostrarAltitudMode();
	echo "Indica el modo de altitud: ";
	fscanf (STDIN, "%d\n", $altitudeMode);
    } while ($altitudeMode < 0 || $altitudeMode > 4);
	
    $poligono = FALSE;
/*
    echo "Indica si quieres la opcion poligono: (s/n) " .PHP_EOL;
    $line = trim(fgets(STDIN));
    $line = strtolower($line);
    if ($line == "n") {
        $poligono = FALSE;
    } else {
	$poligono = TRUE;
    }
*/
    echo "Los ficheros de salida irán ordenados por radar(r) o por nivel de vuelo(f) (R/f): ";
    $line = trim(fgets(STDIN));
    $line = strtolower($line);
    if ("f" == $line ) {
	$ordenarPorRadar = false;
	echo "Ordenando por nivel de vuelo" . PHP_EOL;
    } else {
        $ordenarPorRadar = true;
        echo "Ordenando por radar" . PHP_EOL;
    }

    echo "Indica con que radares quieres trabajar (separados por espacios): ";
    $linea = strtolower(trim(fgets(STDIN)));
    $lugares = explode(" ", $linea);
}

function printMalla($malla, $relleno = " ") {

    for($i = 0; $i < count($malla); $i++) {
        for($j = 0; $j < count($malla[$i]); $j++) {
            if ( $malla[$i][$j] == "0" ) {
                print $relleno;
            } else {
                print $malla[$i][$j];
            }
        }
        print PHP_EOL;
    }
}

function printContornos($contornos, $malla) {
/*
    $malla = array();
    for($i=0; $i<$tamMalla; $i++) {
        $malla[$i] = array();
        for($j=0; $j<$tamMalla; $j++) {
            $malla[$i][$j] = 0;
        }
    }
*/
    $alfa = array(
        'a','b','c','d','e','f','g','h','i','j','k','l','m','n','o','p','q','r','s','t','u','v','w','x','y','z',
        'A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z'
    );
    $i = 0;
    foreach($contornos as $contorno) {
        foreach($contorno as $pto) {
            if ( $malla[$pto['fila']][$pto['col']] == "0" ) {
                $malla[$pto['fila']][$pto['col']] = $alfa[(($i++)%count($alfa))];
            } else {
                print "punto repetido!" . PHP_EOL;
            }
        }
    }
    printMalla($malla);

    return true;
}