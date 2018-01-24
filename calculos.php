<?php

CONST FRONTERA_LATITUD = 90; // latitud complementaria
CONST FEET_TO_METERS = 0.30480370641307;
CONST PASO_A_GRADOS = 180.0;
// CONST DISTANCIA_ENTRE_PUNTOS = 5; // maxima distancia q puede haber entre dos puntos de un acimut para saber si es necesario interpolar 
CONST TAM_CELDA = 0.5; // paso de la malla en NM 0.5 , 0.11 es lo mas que peque�o q no peta
CONST TAM_CELDA_MITAD = 0.25; // NM
CONST TAM_ANGULO_MAXIMO = 1; // NM (lo situamos al doble que tamaño celda)

//// CONSTANTES PARA LA DETECCION DE CONTRONOS /////
CONST NONE = 0;
CONST UP = 1;
CONST LEFT = 2;
CONST DOWN = 3;
CONST RIGHT = 4; 
/////////////////////////////////////////////

/**
 * Funcion que permite buscar los puntos limitantes necesarios para poder calcular la cobertura.
 * 
 * @param array $listaObstaculos (ENTRADA)
 * @param int $flm (ENTRADA)
 * @param float $alturaPrimerPtoSinCob (ENTRADA/SALIDA)
 * @param float $anguloPrimerPtoSinCob (ENTRADA/SALIDA)
 * @param float $alturaUltimoPtoCob (ENTRADA/SALIDA)
 * @param float $anguloUltimoPtoCob (ENTRADA/SALIDA)
 * @return boolean, devuelve true si encontrado o false en caso contrario (SALIDA)
 */
function buscarPuntosLimitantes($listaObstaculos, $flm, &$alturaPrimerPtoSinCob, &$anguloPrimerPtoSinCob, &$alturaUltimoPtoCob, &$anguloUltimoPtoCob, $alturaCentroFasesAntena){

    $i=0;
    $enc = false;
    
    while ( $i < (count($listaObstaculos)) && !$enc ){
        if ($flm < $listaObstaculos[$i]['altura']){ // la primera vez que se cumple esto tenemos el primer punto sin cobertura
            // siempre se va a dar el caso en el que el nivel de vuelo va a ser menor que la altura del obstáculo
	    if ( $i == 0 ) {

        	$alturaPrimerPtoSinCob = $listaObstaculos[$i]['altura']; //+25000;
        	// garantizar que este angulo es siempre mayor que el anguloUltimoPtoCob (y en lugar de restar en el otro una
        	// cantidad fija, sumamos aquí para que siempre sea mayor que cero)
                $anguloPrimerPtoSinCob = $listaObstaculos[$i]['angulo'] + 0.001;
	        $alturaUltimoPtoCob    = $alturaCentroFasesAntena; // ajuste para evitar la división por cero 
	        $anguloUltimoPtoCob    = $listaObstaculos[$i]['angulo'];
	        print "el primer obstaculo no tiene cobertura, no tenemos ultimo punto con cobertura, revisar para probar solucion." . PHP_EOL;
	        // constantina 2800 feet
	    } else {
    	        $primerPtoSinCobertura = $listaObstaculos[$i];
                $ultimoPtoCobertura = $listaObstaculos[$i-1];
	
    	        $alturaPrimerPtoSinCob = $listaObstaculos[$i]['altura'];
                $anguloPrimerPtoSinCob = $listaObstaculos[$i]['angulo'];
                $alturaUltimoPtoCob    = $listaObstaculos[$i-1]['altura'];
	        $anguloUltimoPtoCob    = $listaObstaculos[$i-1]['angulo'];
	    }
	    /*
	    print "alturaPrimerPtoSinCob:" . $alturaPrimerPtoSinCob . PHP_EOL;
	    print "alturaUltimoPtoConCob:" . $alturaUltimoPtoCob . PHP_EOL;
	    print "anguloPrimerPtoSinCob:" . $anguloPrimerPtoSinCob . PHP_EOL;
	    print "anguloUltimoPtoConCob:" . $anguloUltimoPtoCob . PHP_EOL;
	    print "flm:" . $flm . PHP_EOL;
            */
	    $enc = true;

	} else {
	    $i++;
	}
    }
    return $enc;
}

/**
 * Funcion para calcular el angulo de maxima cobertura
 * 
 * @param array $radar (ENTRADA)
 * @param float $radioTerrestreAumentado (ENTRADA)
 * @param int $flm (ENTRADA)
 * @return number (SALIDA)
 */
function calculaAnguloMaximaCobertura($radar, $radioTerrestreAumentado, $flm){
	
	$earthToRadar = $radar['towerHeight'] + $radar['terrainHeight'] + $radioTerrestreAumentado;
	$earthToFl = $radioTerrestreAumentado + $flm;
	
	return $anguloMaxCob = acos( (pow($earthToRadar,2) + pow($earthToFl,2) - pow($radar['range'],2)) / (2 * $earthToRadar * $earthToFl) );
}

/**
 * CASO A
 *  Funcion que calcula las distancias a las que hay cobertura y los angulos de apantallamiento cuando el FL esta por encima del radar
 *  
 * @param array $radar (ENTRADA)
 * @param int $flm (ENTRADA)
 * @param float $radioTerrestreAumentado (ENTRADA)
 * @param float $angulosApantallamiento (ENTRADA/SALIDA)
 * @param array $distanciasCobertura (ENTRADA/SALIDA)
 */
function calculosFLencimaRadar($radar, $flm, $radioTerrestreAumentado, &$angulosApantallamiento, &$distanciasCobertura ){
		
	    $anguloMaxCob = calculaAnguloMaximaCobertura($radar, $radioTerrestreAumentado, $flm);
	    $earthToFl = $radioTerrestreAumentado + $flm; 
	    $earthToRadar = $radar['towerHeight'] + $radar['terrainHeight'] + $radioTerrestreAumentado;
	
		// recorremos los azimuths 
	 	for ($i=0; $i < $radar['totalAzimuths']; $i++){
 	 		
	 		// obtenemos la ultima linea del array para cada azimut.
	 		if ( !isset($radar['listaAzimuths'][$i]) ) {
	 		    print_r($radar);
	 		    die("ERROR: el azimuth $i no existe" . PHP_EOL);
	 		}
	 		$tamano = count($radar['listaAzimuths'][$i]);
	 	
	 		// obtenemos la altura del ultimo punto para cada azimuth
	 		$obstaculoLimitante = $radar['listaAzimuths'][$i][$tamano-1]['altura'];  
	 		
	 		if ($flm >= $obstaculoLimitante){
	 			
	 			$earthToEvalPoint = $radioTerrestreAumentado + $obstaculoLimitante; 
	 			// obtenemos el angulo del ultimo obstaculo de cada azimuth
	 			$angulo = $radar['listaAzimuths'][$i][$tamano-1]['angulo'];
	 				 	
	 			$distanciasCobertura[$i]= sqrt ((pow($earthToRadar,2) + pow($earthToEvalPoint,2)) - 2 * $earthToRadar * $earthToEvalPoint * cos($angulo));
	 	 		$gammaMax = acos((pow($distanciasCobertura[$i],2) + pow($earthToRadar,2) - pow($earthToEvalPoint,2)) / (2 * $earthToRadar * $distanciasCobertura[$i]));
	 	 	
	 	 		$theta = asin($earthToRadar * sin($gammaMax) / $earthToFl);
	 	 		$epsilon = PI - $theta - $gammaMax;
	 	
	 	 		if ($epsilon >  $anguloMaxCob)
	 	 			$distanciasCobertura[$i] = $radioTerrestreAumentado * $anguloMaxCob / MILLA_NAUTICA_EN_METROS;
	 			 else
	 	 			$distanciasCobertura[$i] = $radioTerrestreAumentado * $epsilon / MILLA_NAUTICA_EN_METROS; 
	 	
	 	 		$angulosApantallamiento[$i] = ($gammaMax * PASO_A_GRADOS / PI) - FRONTERA_LATITUD; 
	 		}
	 	 	else{ // $fl < $obstaculoLimitante 
	 	 	
	 	 		$anguloLimitante = 0;
	 	 		$alturaPrimerPtoSinCob = 0;
	 	 		$anguloPrimerPtoSinCob = 0;
	 	 		$alturaUltimoPtoCob = 0;
                                $anguloUltimoPtoCob = 0;
	 	 		
	 	 		// print "azimut: $i" . PHP_EOL;
	 	 		if(buscarPuntosLimitantes($radar['listaAzimuths'][$i], $flm, $alturaPrimerPtoSinCob, $anguloPrimerPtoSinCob, $alturaUltimoPtoCob, $anguloUltimoPtoCob, $alturaCentroFasesAntena = $radar['towerHeight'] + $radar['terrainHeight'])){
	 	 		    $anguloLimitante = (($flm-$alturaUltimoPtoCob) * (($anguloPrimerPtoSinCob - $anguloUltimoPtoCob)  / ($alturaPrimerPtoSinCob - $alturaUltimoPtoCob))) + $anguloUltimoPtoCob;
	 	 		} else {
	 	 		    die("ERROR MALIGNO !! No deberias haber entrado aqui" . PHP_EOL);
	 	 		}
	 	 		 	
	 	 		if ($anguloLimitante > $anguloMaxCob) {
	 	 		    $distanciasCobertura[$i] = $radioTerrestreAumentado * $anguloMaxCob/ MILLA_NAUTICA_EN_METROS;
	 	 		} else {
	 	 		    $distanciasCobertura[$i] = $radioTerrestreAumentado * $anguloLimitante / MILLA_NAUTICA_EN_METROS;
	 	 		}
	 	 	}// else
	 	}// fin for para recorrer los azimuths	 
}// fin function


/**
 * Funcion que calcula las coordenadas geograficas de cobertura de un fichero kml para un determinado
 * radar a partir de las coordenadas, el nivel de vuelo y el array de distancias de cobertura.
 * 
 * @param array $radar (ENTRADA)
 * @param array $coordenadas, array con las coordenadas en grados decimales (ENTRADA)
 * @param array $distanciasCobertura, en millas nauticas (ENTRADA)
 * @param int $flm, en metros (ENTRADA)
 * @param array $coordenadasGeograficas (ENTRADA/SALIDA)
 */
function calculaCoordenadasGeograficasA( $radar, $coordenadas, $distanciasCobertura, $flm, &$coordenadasGeograficas){
	
	// Calcula el paso en funcion del numero maximo de azimuth (puede ser desde 360 o 720)
	$paso = 360.0 / $radar['totalAzimuths'];

	// Recorrido de los acimuts 
 	for ($i = 0; $i < $radar['totalAzimuths']; $i++){
 		
 		// Calculo de la latitud
 		$anguloCentral = ($distanciasCobertura[$i] * MILLA_NAUTICA_EN_METROS / RADIO_TERRESTRE);
 		$latitudComplementaria = deg2rad(FRONTERA_LATITUD - $coordenadas['latitud']);
 		$r = rad2deg(acos (cos($latitudComplementaria) * cos($anguloCentral) + sin($latitudComplementaria) * sin($anguloCentral)
 			 * cos(deg2rad($i * $paso)))); // tenemos r en grados
 		
 		// Calculo de la longitud
 		$rEnRadianes = deg2rad($r);
 		$numerador = cos($anguloCentral) - cos($latitudComplementaria) * cos($rEnRadianes);
 		$denominador = sin($latitudComplementaria) * sin($rEnRadianes);
 			
 		if($numerador > $denominador)
 			$p = 0;
 		else
 			$p =  rad2deg( acos($numerador / $denominador) );
 		
 		// asignacion de valores a la estructura de datos
 		if ( $i < ($radar['totalAzimuths'] / 2) )
 			$coordenadasGeograficas[$i]['longitud'] = $coordenadas['longitud'] + $p;
 		else
 			$coordenadasGeograficas[$i]['longitud'] = $coordenadas['longitud'] - $p;
 							
 		$coordenadasGeograficas[$i]['latitud'] = FRONTERA_LATITUD - $r;
		$coordenadasGeograficas[$i]['altura'] = $flm;

 	}
}

/**
 * Funcion que dada la lista de obstaculos y el radio terrestre interpola
 * según el caso en el que se encuentre y nos devuelve la lista de obstaculos
 * ampliada
 *
 * @param array $listaObstaculos, representa un rango de interpolacion (ENTRADA)
 * @param float $radioTerrestreAumentado (ENTRADA)
 * @param int $casos (ENTRADA), para diferenciar si se tiene que interpolar en la zona de luz o la de sombra
 * @return array[] (SALIDA), array con los obstaculos calculado con la interpolacion del terreno
 */
function interpolarPtosTerreno($listaObstaculos, $radioTerrestreAumentado, $casos){

    $diferencia = 0; $anguloNuevoPto = 0; $alturaNuevoPto = 0;
    $listaObstaculosAmpliada = array();
    $ptoNuevo = array ('angulo' => 0, 'altura' => 0, 'estePtoTieneCobertura' => false); // inicializamos el punto nuevo

    // El paso para interpolar queda en función del tamaño de la celda.
    // Debería ser la mitad del tamaño de la celda, ponemos 1NM para simplificar el depurado
    $anguloMaximo = (TAM_ANGULO_MAXIMO * MILLA_NAUTICA_EN_METROS) / $radioTerrestreAumentado; // CADA MILLA
    $cuentaListaObstaculos = count($listaObstaculos) - 1;

    // recorremos la lista de obstaculos del azimut, comprobando si es
    // necesario insertar algún obstáculo. (si existe un hueco demasiado
    // grande entre distancias interpolamos los puntos)
    // EL ÚLTIMO OBSTÁCULO LO INSERTAMOS FUERA DEL BUCLE
    for ( $i = 0; $i < $cuentaListaObstaculos; $i++ ){
        if ($casos == 1) {
	    $listaObstaculosAmpliada[] = $listaObstaculos[$i]; // copiamos el punto original
        }
        $diferencia = $listaObstaculos[$i+1]['angulo'] -  $listaObstaculos[$i]['angulo'];

	if ($diferencia > $anguloMaximo) { // demasiada distancia entre puntos, interpolar
	    //print $diferencia . PHP_EOL;
            //print $anguloMaximo . PHP_EOL;
	    // $ptosQueMeter = round(($listaObstaculos[$i+1]['angulo'] - $listaObstaculos[$i]['angulo']) / $anguloMaximo);
	    $ptosQueMeter = round(($listaObstaculos[$i+1]['angulo'] - $listaObstaculos[$i]['angulo']) / $anguloMaximo, $precision = 0, $mode = PHP_ROUND_HALF_UP);
	    //print $ptosQueMeter . PHP_EOL;exit();
            $distancia = ($listaObstaculos[$i+1]['angulo'] -  $listaObstaculos[$i]['angulo']) / ($ptosQueMeter+1); // se le suma una xq son segmentos
            for ($j = 0; $j < $ptosQueMeter; $j++) { // creamos los ptos
	        // obtenemos el angulo nuevo
                $anguloNuevoPto = $listaObstaculos[$i]['angulo'] +  ($j+1) * $distancia;
		// obtenemos la altura nueva
		if ( $casos == 1 ) { // zona de terreno
		    // print "(" . $j . ":" . $distancia . ")" . "-";
                    $alturaNuevoPto = ((($listaObstaculos[$i+1]['altura'] -  $listaObstaculos[$i]['altura']) /
		        ($listaObstaculos[$i+1]['angulo'] -  $listaObstaculos[$i]['angulo']))* ($anguloNuevoPto-$listaObstaculos[$i]['angulo']))
			+  $listaObstaculos[$i]['altura'];
                    $ptoNuevo = array('angulo' => $anguloNuevoPto, 'altura' => $alturaNuevoPto, 'estePtoTieneCobertura' => false);
		} elseif ( $casos == 2 ) { //zona de sombra
		    // print "(" . $j . ")" . "+";
		    $alturaNuevoPto = 0; // comprobar con ruben
		    $ptoNuevo = array('angulo' => $anguloNuevoPto, 'altura' => $alturaNuevoPto, 'estePtoTieneCobertura' => false);
		} elseif ( $casos == 3 ){ //zona luz
		    // print "(" . $j . ")" . "*";
                    $alturaNuevoPto = 0; // comprobar con ruben
		    $ptoNuevo = array('angulo' => $anguloNuevoPto, 'altura' => $alturaNuevoPto, 'estePtoTieneCobertura' => true);
                }
                //print_r($ptoNuevo);
		$listaObstaculosAmpliada[] = $ptoNuevo; //añadimos el nuevo punto a la lista de obstáculos
	    }// for interno
	}//if
    }// for externo

    $listaObstaculosAmpliada[] = $listaObstaculos[$cuentaListaObstaculos]; // añadimos el último punto
    return $listaObstaculosAmpliada;
}

/**
 * Funcion que actualiza el parametro 'estePtoTieneCobertura'  dependiendo de si se cumple la condicion que determina si hay o no cobertura
 *
 * @param array $listaObstaculosAmpliada (ENTRADA)
 * @param int $flm nivel de vuelo a comprobar (ENTRADA)
 * @return array lista de obstaculos ampliada modificada
 */
function miraSiHayCobertura($listaObstaculosAmpliada, $flm){

    for ($i = 0; $i < count($listaObstaculosAmpliada); $i++){
	if ($listaObstaculosAmpliada[$i]['altura'] < (double) $flm){ // doble < integer 
	    // imprimir si no coincide!
	    //if ($listaObstaculosAmpliada[$i]['estePtoTieneCobertura']!== true) {
	    //    print "AQUI NO COINCIDE deberia ser true" . PHP_EOL;
	    //}
            $listaObstaculosAmpliada[$i]['estePtoTieneCobertura'] = true;
	} else {
	    //if ($listaObstaculosAmpliada[$i]['estePtoTieneCobertura']!== false) {
	    //    print "AQUI NO COINCIDE deberia ser false" . PHP_EOL;
	    //}
	    // imrpimir si no coincide, no se si esto hace falta o no
	    $listaObstaculosAmpliada[$i]['estePtoTieneCobertura'] = false;
	}
    }
    
    return $listaObstaculosAmpliada;
    
}

/**
 * Funcion auxiliar que calcula una serie de parametros necesarios en otras funciones
 * 
 * @param array $radar (ENTRADA)
 * @param array $listaObstaculos (ENTRADA)
 * @param float $radioTerrestreAumentado (ENTRADA)
 * @param int   $flm (ENTRADA)
 * @param float $obstaculoLimitante (ENTRADA)
 * @param float $gammaMax (ENTRADA/SALIDA)
 * @param float $theta0 Es el seno del ángulo $theta1 el cual representa el ángulo entre la línea de vista del último 
                punto de cada azimuth y la proyección sobre el terreno del punto de corte con el nivel de vuelo
                (identificado a través de su ángulo central $epsilon1)  (ENTRADA/SALIDA)
 * @param float $earthToRadar (ENTRADA/SALIDA)
 * @param float $earthToEvalPoint (ENTRADA/SALIDA)
 * @param float $earthToFl (ENTRADA/SALIDA)
 * @param float $radarSupTierra (ENTRADA/SALIDA)
 */
function calculador($radar, $listaObstaculos, $radioTerrestreAumentado, $flm, $obstaculoLimitante, &$gammaMax, &$theta0, &$earthToRadar, &$earthToEvalPoint, &$earthToFl, &$radarSupTierra){

    // Distancia del radar a la superficie terrestre
    $radarSupTierra = $radar['towerHeight'] + $radar['terrainHeight']; 

    // Distancia del radar al centro de la Tierra
    $earthToRadar = $radar['towerHeight'] + $radar['terrainHeight'] + $radioTerrestreAumentado;

    // Distancia desde el centro de la tierra al obstáculo limitante
    $earthToEvalPoint = $radioTerrestreAumentado + $obstaculoLimitante;

    // Distancia desde el centro de la tierra al nivel de vuelo
    $earthToFl = $radioTerrestreAumentado + $flm;
    $n = count ($listaObstaculos);

    // Distancia desde el último punto al radar
    $distanciaUltimoPto = $radioTerrestreAumentado * $listaObstaculos[$n-1]['angulo'];

    // Línea de vista del últmio punto del terreno
    $distanciaCobertura = sqrt( pow($earthToRadar,2) + pow($earthToEvalPoint,2) - 2 * $earthToRadar * $earthToEvalPoint * cos($listaObstaculos[$n-1]['angulo']));
	
    // Angulo en radianes que forma el radar con el último punto del terreno
    $gammaMax = acos((pow($distanciaCobertura,2) +  pow($earthToRadar,2) - pow($earthToEvalPoint,2)) / (2 * $earthToRadar * $distanciaCobertura));

    /* sin($theta1) donde $theta1 es el ángulo entre la línea de vista del último punto de cada azimuth y la proyección sobre el terreno del punto
        de corte con el nivel de vuelo(el más alejado).*/
    $theta0 = $earthToRadar * sin($gammaMax) / $earthToFl;

    if (false) {
        print "Radio terrestre aumentado" . ":" . $radioTerrestreAumentado . PHP_EOL;
        print "FL" . ":" . $flm . PHP_EOL;
        print "Obstaculo limitante" . ":" . $obstaculoLimitante . PHP_EOL;
        print "Distancia radar a superficie terrestre: $radarSupTierra" . PHP_EOL;
        print "Earth To Radar" . ":" . $earthToRadar . PHP_EOL;
        print "Earth To Eval Point". ":" . $earthToEvalPoint . PHP_EOL;
        print "Earth To FL". ":" . $earthToFl . PHP_EOL;
    	print "Angulo pto limitante" . ":" . $listaObstaculos[$n-1]['angulo'] . PHP_EOL;
	print "Distancia Cobertura" . ":" . $distanciaCobertura . PHP_EOL;
        print "Gamnamax" . ":" . $gammaMax .  PHP_EOL;
	print "Theta1" . ":" . asin($theta0) . PHP_EOL;
    }
    return;
}

/**
 * Funcion auxiliar para obtener los angulos epsilon1 y epsilon2 que nos permiten calcular los puntos de corte
 * 
 * @param float $earthToRadar (ENTRADA)
 * @param float $gammaMax (ENTRADA)
 * @param float $earthToFl (ENTRADA)
 * @param float $radioTerrestreAumentado (ENTRADA)
 * @param float $epsilon1 (ENTRADA/SALIDA)
 * @param float $epsilon2 (ENTRADA/SALIDA)
 * @param array $ptosCorte (ENTRADA/SALIDA)
 */
function obtenerPtosCorte($earthToRadar, $gammaMax, $earthToFl, $radioTerrestreAumentado, &$epsilon1, &$epsilon2, &$ptosCorte){
		
	$epsilon1 = 0;
	$epsilon2 = 0;
	$ptosCorte = array(); // array de dos posiciones
	
	$numerador = $earthToRadar * sin($gammaMax);
	$denominador = $earthToFl;
	
	if ($numerador > $denominador)
		$theta1 = 0;
	else 
		$theta1 = asin ($numerador / $denominador);
	
	// Ángulo central del punto de corte más alejado de la línea de vista(del último punto de cada azimuth) con el nivel de vuelo
	$epsilon1 = PI - $theta1 - $gammaMax;
	
	// Ángulo central del punto de corte más cercano de la línea de vista(del último punto de cada azimuth)  con el nivel de vuelo
	$epsilon2 = PI - (PI-$theta1) - $gammaMax;
	
	$ptosCorte[0] = $epsilon1 * $radioTerrestreAumentado;
	$ptosCorte[1] = $epsilon2 * $radioTerrestreAumentado;	
}

/**
 * CASO B
 * Funcion que calcula las coberturas cuando el nivel de vuelo FL, esta por debajo del radar
 * 
 * @param array $radar (ENTRADA / SALIDA)
 * @param int $flm nivel de vuelo ¿en qué unidades? (ENTRADA)
 * @param float $radioTerrestreAumentado (ENTRADA)
 */
function calculosFLdebajoRadar(&$radar, $flm, $radioTerrestreAumentado){

    $anguloMaxCob = calculaAnguloMaximaCobertura($radar, $radioTerrestreAumentado, $flm);
    // Ángulo (en radianes) entre el último pto de cada acimut y el punto
    // extra para una distancia de 0.1 NM. Es una pequeña distancia que se
    // le suma al ángulo de cada punto [0.1 NM]
    // para añadir un ptoExtra y poder aproximar el mallado
    $anguloMinimo = (0.1 *  MILLA_NAUTICA_EN_METROS ) / ( RADIO_TERRESTRE * (4/3) );
    $ptosNuevos = array();
    $ptoExtra = array( 'angulo' => 0, 'altura' => 0, 'estePtoTieneCobertura' => false);
    $ptoMaxCob = array('angulo'=> $anguloMaxCob, 'altura'=> 0,'estePtoTieneCobertura'=> true);
    for ($i=0; $i < $radar['totalAzimuths']; $i++){
        // print "Azimut: $i" . PHP_EOL;
        //print_r($radar['listaAzimuths'][$i]);
        // interpolamos puntos terreno
	$listaObstaculosAmpliada = interpolarPtosTerreno( $radar['listaAzimuths'][$i], $radioTerrestreAumentado, 1 );
        // print_r($listaObstaculosAmpliada);
        //exit();
	$listaObstaculosAmpliada = miraSiHayCobertura($listaObstaculosAmpliada, $flm);
 	// print_r($listaObstaculosAmpliada);
 	// exit();
        // se calcula el punto limitante
 	$tamano = count( $listaObstaculosAmpliada );
 	$numPtosAzimut = count( $radar['listaAzimuths'][$i] );
 	$obstaculoLimitante = $radar['listaAzimuths'][$i][$numPtosAzimut-1]['altura'];
 	$anguloLimitante = $radar['listaAzimuths'][$i][$numPtosAzimut-1]['angulo'];

 	$ptoLimitante = array( 'angulo' => $anguloLimitante, 'altura' => $obstaculoLimitante, 'estePtoTieneCobertura' => true );
 	calculador( $radar, $listaObstaculosAmpliada, $radioTerrestreAumentado, $flm, $obstaculoLimitante, $gammaMax, $theta0, $earthToRadar, $earthToEvalPoint, $earthToFl, $radarSupTierra );

 	// CASO A: Último punto del acimut por debajo del nivel de vuelo y por debajo del radar
 	if( ( $obstaculoLimitante < $flm ) && ( $obstaculoLimitante < $radarSupTierra ) ) {
 	    if ((abs($theta0)) <= 1){
	 	obtenerPtosCorte($earthToRadar, $gammaMax, $earthToFl, $radioTerrestreAumentado, $epsilon1, $epsilon2, $ptosCorte);
		$ptoUno = array('angulo' => $epsilon1, 'altura'=> 0, 'estePtoTieneCobertura'=> true);

		// A.1: se interpola desde el último punto del terreno hasta el punto 1
		if ($epsilon1 < $anguloMaxCob) {
	 	    $rangoLuz =  array ($ptoLimitante, $ptoUno);
	 	    // devuelve una lista con los puntos que se han interpolado 
	 	    $ptosLuz = interpolarPtosTerreno($rangoLuz, $radioTerrestreAumentado, 3);
	 	    // print "IFSNOP CASO A1" . PHP_EOL;
	 	    // print_r($ptosLuz);

	 	    $listaObstaculosAmpliada = array_merge( $listaObstaculosAmpliada, $ptosLuz );
	 	    $ptoExtra = array( 'angulo' => ($epsilon1 + $anguloMinimo), 'altura' => 0, 'estePtoTieneCobertura' => false );
	 	    $listaObstaculosAmpliada= array_merge( $listaObstaculosAmpliada, array($ptoExtra) );
	 	
	 	// A.2: se interpola desde el último punto del terreno hasta el punto de máxima cobertura
	 	} else {
	 	    $rangoLuz =  array ($ptoLimitante, $ptoMaxCob);
                    $ptosLuz = interpolarPtosTerreno($rangoLuz, $radioTerrestreAumentado, 3);
                    // print "IFSNOP CASO A2" . PHP_EOL;
	 	    // print_r($ptosLuz);

                    $listaObstaculosAmpliada = array_merge( $listaObstaculosAmpliada, $ptosLuz );
	 	    $ptoExtra = array( 'angulo' => ($anguloMaxCob + $anguloMinimo), 'altura' => 0, 'estePtoTieneCobertura' => false );
	 	    $listaObstaculosAmpliada = array_merge( $listaObstaculosAmpliada, array($ptoExtra) );
	 	}
            // A.3: El corte con el nivel de vuelo se traduce en angulos negativos
            } elseif ( abs($theta0) > 1 ) {
	        $ptoExtra = array( 'angulo' => ($anguloLimitante + $anguloMinimo), 'altura' => 0, 'estePtoTieneCobertura' => false );
	        // print "IFSNOP CASO A3" . PHP_EOL;
 	        // print_r($ptoExtra);
	        $listaObstaculosAmpliada= array_merge( $listaObstaculosAmpliada, array($ptoExtra) );
	    }
	// fin if caso A
	// CASO B: Último punto del acimut por encima del nivel de vuelo y por debajo del radar
        } elseif ( ( $obstaculoLimitante > $flm ) && ( $radarSupTierra > $obstaculoLimitante ) ) {
	    if ( (abs($theta0)) <= 1 ) {
                obtenerPtosCorte($earthToRadar, $gammaMax, $earthToFl, $radioTerrestreAumentado, $epsilon1, $epsilon2, $ptosCorte);
		$ptoUno = array( 'angulo'=> $epsilon1, 'altura'=> 0, 'estePtoTieneCobertura'=> true ); // epsilon1
		$ptoDos = array( 'angulo'=> $epsilon2, 'altura'=> 0, 'estePtoTieneCobertura'=> true ); // epsilon2
		// B.1: se interpola desde el último punto del terreno hasta el punto 1 pasando por el punto 2
	 	if ( ($epsilon1 < $anguloMaxCob) && ($epsilon2 < $anguloMaxCob) ) {
	 	    // rango sombra
	 	    // ptoDos debería estar ¿después? de ptoLimitante, o bien
	 	    // cambiar el orden al crear el array. PtoLimitante es el
	 	    // último punto del array de obstáculosAmpliados
	 	    // PROBLEMA 1
	 	    // B.1.1: se interpola desde el último punto del terreno hasta el punto 2 (rango SOMBRA)
                    if ( ($epsilon1 > $anguloLimitante) ) {
                        $rangoSombra = array( $ptoLimitante, $ptoDos );
                        // print "RANGO SOMBRA (ptlimitante, epsilon2)" . PHP_EOL;
                        // print_r($rangoSombra);
                        $ptosSombra = interpolarPtosTerreno( $rangoSombra, $radioTerrestreAumentado, 2);
                        // print "IFSNOP CASO B11" . PHP_EOL;
                        // print_r($ptosSombra);
                        $listaObstaculosAmpliada = array_merge( $listaObstaculosAmpliada, $ptosSombra );

                        // B.1.2: Se interpola desde el punto 2 al punto 1 (rango LUZ)
                        $rangoLuz =  array( $ptoDos, $ptoUno );
                        // print "RANGO LUZ(epsilon2, epsilon1)" . PHP_EOL;
                        // print_r($rangoLuz);
                        $ptosLuz = interpolarPtosTerreno( $rangoLuz, $radioTerrestreAumentado, 3 );
                        // print "IFSNOP CASO B12" . PHP_EOL;
                        // print_r($ptosLuz);

                        $listaObstaculosAmpliada = array_merge( $listaObstaculosAmpliada, $ptosLuz );
                        $ptoExtra = array('angulo' => ($epsilon1 + $anguloMinimo), 'altura' => 0, 'estePtoTieneCobertura' => false );
                        $listaObstaculosAmpliada= array_merge( $listaObstaculosAmpliada, array($ptoExtra) );
                    }  elseif ( ($epsilon1 <= $anguloLimitante) && ($epsilon2 <= $anguloLimitante) ) {
                        // B.1.3: Los dos puntos están entre el radar y el obstáculo limitante.
                        // para acabar la lista de obstaculos con un punto sin cobertura
                        $ptoExtra = array('angulo' => ($anguloLimitante + $anguloMinimo), 'altura' => 0, 'estePtoTieneCobertura' => false );
                        $listaObstaculosAmpliada= array_merge( $listaObstaculosAmpliada, array($ptoExtra) );
                    }
	 	// if B.1
	 	// B.2: se interpola desde el último punto del terreno hasta el punto de máxima cobertura pasando por el punto 2
	 	} elseif ( ($epsilon1 > $anguloMaxCob) && ($epsilon2 < $anguloMaxCob) ) { // B.2
                    // B.2.1: se interpola desde el último punto del terreno hasta el punto 2 (rango SOMBRA)
                    $ptoDos = array ('angulo' => $epsilon2, 'altura' => 0,  'estePtoTieneCobertura'=> true);
	 	    $rangoSombra = array ($ptoLimitante, $ptoDos);
                    //print "RANGO SOMBRA (ptlimitante, epsilon2)" . PHP_EOL;
                    //print_r($rangoSombra);
                    $ptosSombra = interpolarPtosTerreno($rangoSombra, $radioTerrestreAumentado, 2);
	 	    //print "IFSNOP CASO B21" . PHP_EOL;
	 	    //print_r($ptosSombra);
                    $listaObstaculosAmpliada = array_merge($listaObstaculosAmpliada, $ptosSombra);

                    // B.2.2: Se interpola desde el punto 2 hasta el punto de máxima cobertura (rango LUZ)
                    $rangoLuz =  array ($ptoDos, $ptoMaxCob);
                    //print "RANGO LUZ (epsilon2, maxcobertura)" . PHP_EOL;
                    //print_r($rangoLuz);
                    $ptosLuz = interpolarPtosTerreno($rangoLuz, $radioTerrestreAumentado, 3);
                    //print "IFSNOP CASO B22" . PHP_EOL;
                    //print_r($ptosLuz);

                    $listaObstaculosAmpliada = array_merge($listaObstaculosAmpliada, $ptosLuz);
                    $ptoExtra = array ('angulo' => ($anguloMaxCob + $anguloMinimo), 'altura' => 0, 'estePtoTieneCobertura' => false);
                    $listaObstaculosAmpliada = array_merge($listaObstaculosAmpliada, array($ptoExtra));

	 	// fin caso B.2
	 	// B.3: Los cortes con el nivel de vuelo están más allá del punto de máxima cobertura
	 	} elseif ( (($epsilon1 > $anguloMaxCob) && ($epsilon2 > $anguloMaxCob)) ) { // caseo B.3
                    $ptoExtra = array ('angulo' => ($anguloLimitante + $anguloMinimo), 'altura' => 0, 'estePtoTieneCobertura' => false);
	 	    //print "IFSNOP CASO B3, maxima cobertura" . PHP_EOL;
 		    //print_r($ptoExtra);
                    $listaObstaculosAmpliada = array_merge($listaObstaculosAmpliada, array($ptoExtra));
	 	}
	    // B.4: Los cortes con el nivel de vuelo se traducen en angulos negativos
	    } elseif ( abs($theta0) > 1 ) {
	 	$ptoExtra = array ('angulo' => ($anguloLimitante + $anguloMinimo), 'altura' => 0, 'estePtoTieneCobertura' => false); 
	 	//print "IFSNOP CASO B4" . PHP_EOL;
 		//print_r($ptoExtra);

	 	$listaObstaculosAmpliada = array_merge($listaObstaculosAmpliada, array($ptoExtra));
	    
            } // Fin CASO B
            // CASO C: Último punto del acimut por encima del nivel de vuelo y por encima del radar
        } elseif ( ($obstaculoLimitante > $flm) && ($radarSupTierra < $obstaculoLimitante) ) {
            $ptoExtra = array ('angulo' => ($anguloLimitante + $anguloMinimo), 'altura' => 0, 'estePtoTieneCobertura' => false);
	    //print "IFSNOP CASO C" . PHP_EOL;
 	    //print_r($ptoExtra);

            $listaObstaculosAmpliada = array_merge($listaObstaculosAmpliada, array($ptoExtra));
	}
        $radar['listaAzimuths'][$i] = $listaObstaculosAmpliada; // metemos la lista de obstaculos nueva en la estructura
        /*
        for($jj=0;$jj<count($radar['listaAzimuths'][$i]); $jj++) {
            print $radar['listaAzimuths'][$i][$jj]['angulo'] . "|" .
                $radar['listaAzimuths'][$i][$jj]['angulo']*$radioTerrestreAumentado/MILLA_NAUTICA_EN_METROS . "|" .
                $radar['listaAzimuths'][$i][$jj]['altura'] . PHP_EOL;
        }
        */
    } // for

    return;
}

/**
 * Funcion que busca el punto más próximo al punto dado dentro de una
 * lista de obstáculos comparando los angulos y devuelve la posicion
 * de ese punto en la lista de obstáculos.
 *
 * @param array $listaObstaculos (ENTRADA)
 * @param float $punto (ENTRADA)
 * @return number (SALIDA)
 */
function buscaDistanciaMenor($listaObstaculos, $punto){

    if ( !isset($listaObstaculos) || !is_array($listaObstaculos) ) {
        die ("buscaDistanciaMenor: $$listaObstaculos debería ser un array");
    }

    $cuentaListaObstaculos = count($listaObstaculos);
    $min_new_old = abs($punto - $listaObstaculos[0]['angulo']);
    for ( $i = 1; $i < $cuentaListaObstaculos ; $i++ ) {
	$min_new_act = abs($punto - $listaObstaculos[$i]['angulo']);
        if ( $min_new_old < $min_new_act ) {
            return ($i-1);
        }
        $min_new_old = $min_new_act;
    }
    return ($i-1);
}

function buscaDistanciaMenor2($listaObstaculos, $punto){

    if ( !isset($listaObstaculos) || !is_array($listaObstaculos) ) {
        die ("buscaDistanciaMenor: $$listaObstaculos debería ser un array");
    }

    $maxIndex = $i = count($listaObstaculos) - 1;
    // print $i . "]" . $punto . " " . number_format($listaObstaculos[$i]['angulo'],6) . PHP_EOL;
    while ( ($i > -1) && ($punto < $listaObstaculos[$i]['angulo']) ) { 
        print $i . "]" . $punto . " " . number_format($listaObstaculos[$i]['angulo'],10) . PHP_EOL;
        $i--;
    }
    if ( $i == $maxIndex) {
        return $i;
    } elseif ( $i == -1 ) {
        return 0;
    } else {
        $diff1 = abs($punto - $listaObstaculos[$i]['angulo']);
        $diff2 = abs($punto - $listaObstaculos[$i+1]['angulo']);
        if ( $diff1 > $diff2 ) {
            return $i;
        } else {
            return $i+1;
        }
    }

    return 0;
    
}


/**
 * Dadas las coordenadas respecto del pto central de una casilla, nos
 * devuelve el acimut de la misma.
 *
 * @param int $x (ENTRADA)
 * @param int $y (ENTRADA)
 * @return float (SALIDA)
 */
function calculaAcimut($x, $y){

    $acimut = 0;

    if ($x < 0){
    	$acimut = rad2deg( atan($y / $x) + PI );
    } elseif ($x > 0){
        if ($y < 0){
            $acimut = rad2deg( atan($y / $x) + 2 * PI );
        } else{ // $y>= 0
            $acimut = rad2deg( atan($y / $x) );
        }
    } elseif ($x == 0) {
        if ($y < 0) {
            $acimut = rad2deg( ( 3 * PI ) / 2 );
	} elseif ($y > 0) {
            $acimut = rad2deg( PI / 2 );
	}
    }

    $acimut = 90.0 - $acimut;
    if ($acimut < 0) {
        $acimut = $acimut + 360.0;
    }

    return $acimut;
}

/**
 * Funcion que crea una malla de tama�o el doble del alcance del radar y la rellena con 0 o 1 en funci�n de si el punto al que se aproxima el acimut de cada 
 * celda de la malla tiene o no cobertura.
 * 
 * @param array $radar (ENTRADA)
 * @param float $radioTerrestreAumentado (ENTRADA)
 * @return array $malla (SALIDA)
 */
function generacionMallado($radar, $radioTerrestreAumentado){
	
	// pasamos a  millas nauticas el rango del radar que esta almacenado en metros en la estructura radar
	$tamMalla = (( 2 * $radar['range'] ) / TAM_CELDA) / MILLA_NAUTICA_EN_METROS;
	// $xR = 0; // coordenada x del radar
	// $yR = 0; // coordenada y del radar
	$radioTerrestreAumentadoEnMillas  = $radioTerrestreAumentado / MILLA_NAUTICA_EN_METROS;
	
	$malla = array(); // creamos una malla vacia 
	$azimutTeorico = 0; // azimut teorico calculado
	$azimutCelda = 0; // azimut aproximado 
	$pos = 0;
	
	// CENTRAMOS LA MALLA Y CALCULAMOS EL PTO MEDIO DE CADA CELDA
	
	$tamMallaMitad = $tamMalla / 2.0;
	print "radar['range']: " . $radar['range'] . PHP_EOL;
	print "Malla Mitad: " . $tamMallaMitad . PHP_EOL;

	// CALCULAMOS LAS COORDENADAS X DE CADA CELDA (sacamos la parte común del cálculo fuera del bucle)
        $x_fixed = -( $tamMallaMitad * TAM_CELDA ); // + ( TAM_CELDA_MITAD ); // ($i * TAM_CELDA) 
        
        // Factor de corrección según el número de azimut total que haya en el fichero de screening.
        // Como los ángulos son siempre 360, si en el fichero de screening se define otro número,
        // tendremos que ajustar el ángulo que calculamos para adecuarlo al número de azimut guardado
        // según screening. Es decir, si hay 720 azimut, y nos sale un ángulo de 360, realmente será de 720.
        
        $ajusteAzimut = $radar['totalAzimuths'] / 360.0;
        print "[Tamaño malla: " . $tamMalla . "]" . PHP_EOL;
        print "[00%]";
        $countPct_old = 0;
	for ($i = 0; $i <= $tamMalla; $i++){ // recorre las columnas de la malla 
	        //print "[$i]";
	        $countPct = $i*100.0 / $tamMalla;
	        if ( ($countPct - $countPct_old) > 10 ) { print "[" . round($countPct) . "%]"; $countPct_old = $countPct; }

		// CALCULAMOS LAS COORDENADAS X DE CADA CELDA
	        $x = $x_fixed + ($i * TAM_CELDA);
	        // $x = ($i * TAM_CELDA) - ( $tamMallaMitad * TAM_CELDA ) + ( TAM_CELDA_MITAD );
		
		// CALCULAMOS LAS COORDENADAS X DE CADA CELDA (sacamos la parte común del cálculo fuera del bucle)
	        $y_fixed = ( $tamMallaMitad * TAM_CELDA ); // - ( TAM_CELDA_MITAD );//  #- ( $j * TAM_CELDA ) 

		for ($j = 0; $j <= $tamMalla; $j++){ // recorre las filas de la malla 
			// CALCULAMOS LAS COORDENADAS Y DE CADA CELDA
                        $y = $y_fixed - ($j * TAM_CELDA);
                        // $y = ( $tamMallaMitad * TAM_CELDA ) - ( TAM_CELDA_MITAD ) - ( $j * TAM_CELDA );

		        // CALCULAMOS EL AZIMUT DE CADA CELDA Y APROXIMAMOS
			$azimutTeorico = calculaAcimut($x, $y); 
			
			$azimutCelda = round( $azimutTeorico * $ajusteAzimut );
                        // Un último paso, por si acaso al redondear nos salimos de la lista de azimut, ajustamos al máximo.
                        // La lista va de 0 a 359 (o de 0 a 719)...
			if ( $azimutCelda == $radar['totalAzimuths'] ) {
			    $azimutCelda--;
			}

			// al dividir entre el radio tenemos el angulo deseado
			// $distanciaCeldaAradar = ( sqrt( pow( ($xR - $x),2 )+ pow( ($yR - $y),2) ) ) / $radioTerrestreAumentadoEnMillas;
			$distanciaCeldaAradar = ( sqrt(pow($x,2)+pow($y,2)) ) / $radioTerrestreAumentadoEnMillas;

                        // print "$i)" . $azimutCelda . "|" . ( sqrt(pow($x,2)+pow($y,2)) ) . "|" . $x . "|" . $y . " ";
                        // print "$i)" . $x . "|" . $y . "  ";

			// busca la posicion de la  distancia mas proxima en la lista de obstaculos del acimut aproximado (el menor)
			$pos = buscaDistanciaMenor($radar['listaAzimuths'][$azimutCelda], $distanciaCeldaAradar);
			$pos2 = buscaDistanciaMenor2($radar['listaAzimuths'][$azimutCelda], $distanciaCeldaAradar);
			
		        if ( $pos != $pos2 ) {
		            print "azimutCelda: $azimutCelda" . PHP_EOL;
		            print "$i,$j] total: " . count($radar['listaAzimuths'][$azimutCelda]) . ": values:" . $pos . " " . $pos2 . PHP_EOL;
		            print "distancia: $distanciaCeldaAradar" . PHP_EOL;
    		            for($jj=0;$jj<count($radar['listaAzimuths'][$azimutCelda]); $jj++) {
    		                print $jj . ";" . number_format($radar['listaAzimuths'][$azimutCelda][$jj]['angulo'],10) . " " . number_format($radar['listaAzimuths'][$azimutCelda][$jj]['altura'],10) . PHP_EOL;
    		                //print_r($radar['listaAzimuths'][$azimutCelda][$jj]);
    		            }
                            exit(-1);
    		        }

			//print "(" . $pos . "/" . count($radar['listaAzimuths'][$azimutCelda]) . ")";
				
			if ( ($radar['listaAzimuths'][$azimutCelda][$pos]['estePtoTieneCobertura']) === false){
			        // aqui trasponemos la matriz, no se si es sin querer o es a propósito
				$malla[$j][$i] = 0;
				//print "0";
			}
			else{
				$malla[$j][$i] = 1; // entiendase el valor 1 para representar el caso en el que  hay cobertura y 0 para lo contrario
				//print "1";
			}	
		}
		// print PHP_EOL . PHP_EOL;
	}
	print "[100%]" . PHP_EOL;
	return $malla;
}

/**
 * Funcion que crea una matriz con la matriz que le entra como parametro de entrada y la bordea con 0's
 * 
 * @param array $malla (ENTRADA)
 * @return array $mallaMarco (SALIDA)
 */
function mallaMarco($malla){
	
	$mallaMarco= array();
	
	// creamos una malla mayor y la inicializamos a 0
	for($i = 0; $i < count($malla)+2; $i++){
		for ($j = 0; $j < count($malla)+2; $j++){
			$mallaMarco[$i][$j] = 0;
		}
	}
	// recorremos la malla y la copiamos en la malla de mayor tama�o 
	for($i = 0; $i < count($malla); $i++){
		for ($j = 0; $j < count($malla); $j++){
			if ($malla[$i][$j] == 1){
				$mallaMarco[$i+1][$j+1] = $malla[$i][$j];
			}
		}
	}
	return $mallaMarco;
}

/**
 * Funcion que calcula las coordenadas geograficas para el caso B (fl debajo del radar)
 *
 * @param array $radar (ENTRADA)
 * @param int $flm (ENTRADA)
 * @param array $coordenadas (ENTRADA), array con la long y la latitud
 * @param array $listaC (ENTRADA/SALIDA), estructura que asocia la fila con la long, la col con la latitud y que ademas almacena la altura
 */
function calculaCoordenadasGeograficasB($radar, $flm, $coordenadas, &$listaC){
    // DUDA ¿es necesario?
    // $xR = 0;
    // $yR = 0;
    // pasamos a  millas nauticas el rango del radar que esta almacenado en metros en la estructura radar
    $tamMalla = (( 2 * $radar['range'] ) / TAM_CELDA) / MILLA_NAUTICA_EN_METROS;
    $tamMallaMitad = $tamMalla / 2.0;
    $islaCount = count($listaC);
    for($isla = 0; $isla < $islaCount; $isla++){ // recorre la lista de islas/ contornos

	$n = count($listaC[$isla]);

        for($i = 0; $i < $n; $i++){ // recorre la lista de puntos del contorno
            // DUDA ¿por qué se utiliza el -1?
	    $x = ( (($listaC[$isla][$i]['col']-1) * TAM_CELDA) - ($tamMallaMitad * TAM_CELDA) + TAM_CELDA_MITAD );
	    $y = ( ( $tamMallaMitad * TAM_CELDA) - (($listaC[$isla][$i]['fila']-1) * TAM_CELDA) - TAM_CELDA_MITAD );

	    // CALCULO DE LA DISTANCIA
	    // DUDA ¿es necesario $xR e $yR?
	    // $distanciaCeldaAradar = (sqrt(pow(($xR- $x),2)+ pow(($yR - $y),2)) );
	    $distanciaCeldaAradar = sqrt(pow($x,2) + pow($y,2));

	    // CALCULO DEL ACIMUT
	    $azimutTeorico = calculaAcimut($x, $y);

	    // CALCULO DE LA LATITUD
	    $anguloCentral = ($distanciaCeldaAradar * MILLA_NAUTICA_EN_METROS / RADIO_TERRESTRE);
	    $latitudComplementaria = deg2rad(FRONTERA_LATITUD - $coordenadas['latitud']);
	    $r = rad2deg(acos (cos($latitudComplementaria) * cos($anguloCentral) + sin($latitudComplementaria) * sin($anguloCentral)
                * cos(deg2rad($azimutTeorico)))); // tenemos r en grados

            // CALCULO DE LA LONGITUD
	    $rEnRadianes = deg2rad($r);
	    $numerador = cos($anguloCentral) - cos($latitudComplementaria) * cos($rEnRadianes);
	    $denominador = sin($latitudComplementaria) * sin($rEnRadianes);

	    if( $numerador > $denominador ) {
                $p = 0;
            } else {
	        $p = rad2deg(acos($numerador/$denominador));
	    }

	    // asignacion de valores a la estructura de datos
	    if ( $azimutTeorico < 180 ) {
	        $listaC[$isla][$i]['fila'] = $coordenadas['longitud'] + $p;
	    } else {
	        $listaC[$isla][$i]['fila'] = $coordenadas['longitud'] - $p;
	    }
	    $listaC[$isla][$i]['col'] = FRONTERA_LATITUD - $r;
	    $listaC[$isla][$i]['altura'] = $flm;
	}
    }
}

/////////////////////////////////////////////// FUNCIONES NECESARIAS PARA PODER APLICAR EL ALGORITMO MARCHING SQUARES ////////////////////////////////////////////////////////////// 

/**
 * Funcion que dada una malla y la posici�n de una fila, nos devuelve la fila en un array
 * 
 * @param int $y (ENTRADA)
 * @param array $malla (ENTRADA)
 * @return array[] (SALIDA)
 */
// FUNCION SUSCEPTIBLE DE ELIMINACION
function getFila($y, $malla){
	
	$rowData = array();

	for ($j = 0; $j < count($malla); $j++){
		$rowData[] = $malla[$y][$j];
	}
 return $rowData;
}

/**
 * Funcion que copia una matriz en un vector
 * 
 * @param array $malla (ENTRADA)
 * @return array[] (SALIDA)
 */
function matrixToVector ($malla){
	
	$vector = array();
	
	for ($i = 0; $i < count($malla); $i++){
		for ($j = 0; $j < count($malla); $j++){
			$vector[] = $malla[$i][$j];
		}
	}
	return $vector;
}

/**
 * Busca el primer 1 en la malla para empezar a contornear
 * 
 * @param array $malla (ENTRADA)
 * @param int $searchValue (ENTRADA)
 * @param int $x (ENTRADA/SALIDA)
 * @param int $y (ENTRADA/SALIDA)
 */
// FUNCION SUSCEPTIBLE DE MEJORA
function getFirstPoint($malla, &$x, &$y, $searchValue){

	$x = -1; $y = -1;
	
	$rowData = array();
	
	$fila = 0;
	$enc = false;
	$salir = false;

	while ($fila < count($malla) && !$salir){

		$rowData = getFila($fila, $malla); // nos quedamos con la fila de la matriz
		$j = 0;
			
		while ($j < count($rowData) && !$enc){

			if ($rowData[$j] == $searchValue){
				$enc = true;
				$salir = true;
				$x = $j;
				$y = $fila;
			}
			else{
				$j++;
			}
		}
		$fila++;
	}

	return $enc;
}

/**
 * Funcion que determina y establece un conjunto de 4 pixels que representan nuestro estado actual, para deerminar nuestra direccion actual y la siguiente
 * 
 * @param int $index (ENTRADA)
 * @param array $vector (ENTRADA)
 * @param int $tamMalla (ENTRADA)
 * @param int $nextStep (ENTRADA/SALIDA)
 * @param int $state (ENTRADA/SALIDA)
 */
function step($index, $vector, $tamMalla, &$nextStep, &$state, $searchValue){

	$previousStep = 0;

	// representa el marco de 4*4
	$upLeft = $vector[$index];
	$upRight = $vector[$index + 1];
	$downLeft = $vector[$index + $tamMalla];
	$downRight = $vector[$index + $tamMalla + 1] ;

	// store our previous step
	$previousStep = $nextStep;

	// determine which state we are in
	$state = 0;
	if ($upLeft == $searchValue){
		$state = $state|1;
	}
	if ($upRight == $searchValue){
		$state = $state|2;
	}
	if ($downLeft == $searchValue){
		$state = $state|4;
	}
	if ($downRight == $searchValue){
		$state = $state|8;
	}

	switch ($state){
		case 1: $nextStep = UP; break;
		case 2: $nextStep = RIGHT; break;
		case 3: $nextStep = RIGHT; break;
		case 4: $nextStep = LEFT; break;
		case 5: $nextStep = UP; break;
		case 6:
			if ($previousStep == UP)
				$nextStep = RIGHT;
				else
					$nextStep = LEFT;
					break;

		case 7: $nextStep = RIGHT; break;
		case 8: $nextStep = DOWN; break;

		case 9:
			if ($previousStep == RIGHT)
				$nextStep = DOWN;
				else
					$nextStep = UP;
					break;

		case 10: $nextStep = DOWN; break;
		case 11: $nextStep = DOWN; break;
		case 12: $nextStep = LEFT; break;
		case 13: $nextStep = UP; break;
		case 14: $nextStep = LEFT; break;
		default: $nextStep = NONE; break; // this should never happen
	}
}
/**
 * Recorre la malla delineando el contorno desde el punto inicial que le entra por parametro.
 * 
 * @param array $radar (ENTRADA)
 * @param int $startX (ENTRADA)
 * @param int $startY (ENTRADA)
 * @param array $malla (ENTRADA)
 * @param array $vector (ENTRADA)
 * @param int $flm (ENTRADA)
 * @param int $searchValue (ENTRADA)
 * @return number[][]|unknown[][] (SALIDA)
 */
function walkPerimeter($radar, $startX, $startY, $malla, $vector, $flm, $searchValue){ // empezamos desde la primera posicion y recorremos la malla

	// set up our return list
	$pointList = array();

	$x = $startX;
	$y = $startY;

	$sizeMalla = count ($malla);

	// comprobamos que no nos salimos de la malla. NO DEBERIA SER NECESARIO
	if ( $startX < 0 ) $startX = 0;
	if ( $startY < 0 ) $startY = 0;
	if ( $startX > ($sizeMalla-1) ) $startX = $sizeMalla-1;
	if ( $startY > ($sizeMalla-1) ) $startY = $sizeMalla-1;

	do{
		// evaluate our state, and set up our next direction
		$index = ($y-1) * $sizeMalla + ($x-1); // indexa el vector

		step($index, $vector, $sizeMalla, $nextStep, $state, $searchValue);

		// if the current point is within our image add it to the list of points
		if ( ( ($x >= 0) && ($x < $sizeMalla) ) && ( ($y >= 0) && ($y < $sizeMalla) ) ){
				
			if($state == 1){
				$pointList[] = array ('fila'=> $y, 'col' => $x, 'altura' =>$flm);
				$pointList[] = array ('fila'=> $y-1, 'col' => $x, 'altura' =>$flm);
			}
			elseif($state == 2){
				$pointList[] = array ('fila'=> $y, 'col' => $x-1, 'altura' =>$flm);
				$pointList[] = array ('fila'=> $y, 'col' => $x, 'altura' =>$flm);
			}
			elseif($state == 3){
				$pointList[] = array ('fila'=> $y, 'col' => $x, 'altura' =>$flm);
			}
			elseif($state == 4){
				$pointList[] = array ('fila'=> $y-1, 'col' => $x, 'altura' =>$flm);
				$pointList[] = array ('fila'=> $y-1, 'col' => $x-1, 'altura' =>$flm);
			}
			elseif($state == 5){
				$pointList[] = array ('fila'=> $y-1, 'col' => $x, 'altura' =>$flm);
			}
			elseif($state == 8){
				$pointList[] = array ('fila'=> $y-1, 'col' => $x-1, 'altura' =>$flm);
				$pointList[] = array ('fila'=> $y, 'col' => $x-1, 'altura' =>$flm);
			}
			elseif($state == 10){
				$pointList[] = array ('fila'=> $y, 'col' => $x-1, 'altura' =>$flm);
			}
			elseif($state == 12){
				$pointList[] = array ('fila'=> $y-1, 'col' => $x-1, 'altura' =>$flm);
			}
		}

		switch ($nextStep){
			case UP: $y--; break;
			case LEFT: $x--; break;
			case DOWN: $y++; break;
			case RIGHT: $x++; break;
			default : break;
		}
	} while (($x != $startX || $y != $startY) && ($index < count($vector)));

        // si hay mas de un punto en la lista, es que tenemos un contorno
        // hay que cerrarlo para que luego se pinte bien.
	if ( count($pointList) > 0 ) {
		$pointList[] = $pointList[0];
		return $pointList;
	} else {
		return false;
	}

}

/**
 * Determina el contorno, mas info en : 
 * 
 * https://github.com/sakri/MarchingSquaresJS
 * https://codepen.io/sakri/full/aIirl
 * http://htmlpreview.github.io/?https://github.com/mamrehn/MarchingSquaresJS/blob/master/marchingSquaresTest.html
 * https://en.wikipedia.org/wiki/Marching_squares
 * 
 *
 * @param array $radar (ENTRADA)
 * @param array $malla (ENTRADA)
 * @param int $flm (ENTRADA)
 * @param int $searchValue (ENTRADA)
 * @return (SALIDA)
 */
function marchingSquares($radar, $malla, $flm, $searchValue){

	$contorno = array();

	// Find the starting point
	getFirstPoint($malla, $x,$y, $searchValue);

	if ( $x == -1 || $y == -1 )
		return false;

		$vector = matrixToVector($malla);

		// Return list of x and y positions
		$contorno = walkPerimeter($radar,$x, $y, $malla, $vector, $flm, $searchValue); // nos devuelve la isla
		
		return $contorno;
}

/////////////////////////////////////// FUNCIONES NECESARIAS PARA PODER DETECTAR LOS CONTORNOS /////////////////////////////////////////

// https://github.com/Geekfish/flood-filler/blob/master/filler.php

class FloodFiller {
	
	private $x, $y, $fill, $searchNext, $map, $searchValue, $floodValue;
	
	public function Scan( $map, $point, $floodValue, $searchValue ){
	
	// We create the list of traversable squares(fill)
	// and a to-search queue(searchNext[])
	// where we insert our starting point.

	$this->map          = $map;
	$this->fill         = array();
	$this->searchNext   = array();
	//$this->searchNext[] = array('x' => $point[ 'x' ], 'y' => $point[ 'y' ]);
	$this->searchNext[$point['x'] . ";" . $point['y']] = array('x' => $point[ 'x' ], 'y' => $point[ 'y' ]);
	$this->floodValue   = $floodValue;
	$this->searchValue  = $searchValue;
	
	// As long as there are items in the queue
	// keep filling!
	while ( !empty( $this->searchNext ) ) {
	
		// Get the next square item and erase it from the list
//		print "===========";
//		print_r($this->searchNext);
		// $key = key( $this->searchNext );
		// $this->searchNext = array_slice($this->searchNext, 1);
		// list($this->x, $this->y) = explode(";", $key);
		
		$value = reset($this->searchNext);
		$key = key($this->searchNext);
		unset($this->searchNext[$key]);
		$this->x = $value['x'];
		$this->y = $value['y'];
		
//		print $key . " >>" . $this->x . " " . $this->y . PHP_EOL;
//		print_r($this->searchNext);
//		print PHP_EOL . PHP_EOL;
		// $this->x = $next[ 'x' ];
		// $this->y = $next[ 'y' ];
	
		// Check square. If it's traversable we add
		// the square to our fill list and we turn the
		// square untraversable to prevent future checking.
	
			if ( $this->map[ $this->y ][ $this->x ] == $this->searchValue ){
					$this->map[ $this->y ][ $this->x ] = $this->floodValue;
					$this->fill[] = array( 'x' => $this->x, 'y' => $this->y );
					$this->CheckDirections();
			}
	}
	
	return $this->map;
}

		/*    private function CheckSquare( $checkX, $checkY ) {
 				// if we can fill this square we add it to our queue
				   if ( $this->map[ $checkX ][ $checkY ] == $this->searchValue ) {
 						$this->searchNext[] = array( 'x' => $checkX, 'y' => $checkY );
 					}
 				}
		 */

	private function CheckSquare( $checkX, $checkY ) {
		// if we can fill this square we add it to our queue
		if ( isset($this->map[ $checkY ][ $checkX ]) &&
			$this->map[ $checkY ][ $checkX ] == $this->searchValue ) {
			// print count($this->searchNext) . " ";
			// optimizar esto para que no inserte dos veces el mismo punto
			// $this->searchNext[] = array( 'x' => $checkX, 'y' => $checkY );
			$this->searchNext[$checkX . ";" . $checkY] = array( 'x' => $checkX, 'y' => $checkY );
			//print_r($this->searchNext);
			// die("IFSNOP");
		}
	}

	private function CheckDirections() {
		// Perform a check of all adjacent squares
		$this->CheckSquare( $this->x, $this->y - 1 );
		$this->CheckSquare( $this->x, $this->y + 1 );
		$this->CheckSquare( $this->x - 1, $this->y );
		$this->CheckSquare( $this->x + 1, $this->y );
		// diagonals
		$this->CheckSquare( $this->x + 1, $this->y + 1 );
		$this->CheckSquare( $this->x - 1, $this->y - 1 );
		$this->CheckSquare( $this->x + 1, $this->y - 1 );
		$this->CheckSquare( $this->x - 1, $this->y + 1 );
	
	}
	
} //  fin class 

/**
 * Mezcla el contorno con la malla original y rellena por dentro con value
 * 
 * @param array $malla (ENTRADA)
 * @param array $contorno (ENTRADA)
 * @param int $value (ENTRADA)
 * @return array (SALIDA)
 */
/* function mergeContorno($malla, $contorno, $value){
	
  $nuevaMalla = array();
  
  for($i=0;$i<count($malla);$i++){
	  $nuevaMalla[$i] = array();
	  for($j=0;$j<count($malla[$i]);$j++) {
			$nuevaMalla[$i][$j] = $malla[$i][$j];
		}
  }
  foreach($contorno as $punto) {
		$nuevaMalla[$punto['fila']][$punto['col']] = $value;
  }
	
  return $nuevaMalla;
} */
	
/**
 * Funcion que determina los contornos de cobertura que hay en una matriz
 * 
 * @param array $radar (ENTRADA)
 * @param array $malla (ENTRADA)
 * @param int $flm (ENTRADA)
 * @param array $listaContornos (ENTRADA/SALIDA)
 */
function determinaContornos($radar, $malla, $flm, &$listaContornos){

    $listaContornos = array();
    // busca todos los contornos "externos" de las zonas con cobertura
    // rellenando el interior con el mismo valor que ponemos para marcar el contorno
    // seran contornos de zonas CON cobertura
    $malla_original = $malla;
    while ( false !== ($contorno = marchingSquares($radar, $malla, $flm, $searchValue = 1 )) ) { // nos da el contorno de una isla
        // mezclamos el contorno con el mapa original, para luego rellenar DENTRO del contorno con un flood fill
	// este paso podra ser opcional, desde que floodfill funciona bien no es necesario delimitar la zona
	// $nuevaMalla = mergeContorno($malla, $contorno, $value = 2);

	$pInicial = array( 'x' => $contorno[0]['col']+1, 'y' => $contorno[0]['fila']+1 ); // el primer punto del contorno siempre esta arriba a la izq.
	$floodFiller = new FloodFiller();

	//$nuevaMalla = $floodFiller->Scan($nuevaMalla, $pInicial, $floodValue = 2, $searchValue = 1);
	$nuevaMalla = $floodFiller->Scan($malla, $pInicial, $floodValue = 2, $searchValue = 1);

	$malla = $nuevaMalla;
	// $contorno = eliminaPuntosRepetidos($contorno);
	$listaContornos[] = $contorno;
    }

    // printContornos($listaContornos, $malla_original);

    // ahora buscamos los contornos dentro de las zonas con cobertura, seran islas SIN cobertura
    // para ello rellenamos la matriz con '2', y solo tendremos '2' y '0'
    // (zona sin cobertura que tengo que apuntar en la lista de contornos)

    $floodFiller = new FloodFiller();
    $malla = $floodFiller->Scan($malla, array( 'x' => 0, 'y' => 0 ), $floodValue = 2, $searchValue = 0);

    while ( false !== ($contorno = marchingSquares($radar, $malla, $flm, $searchValue = 0 )) ) { // nos da el contorno de una isla
	// mezclamos el contorno con el mapa original, para luego rellenar DENTRO del contorno con un flood fill
	// este paso podra ser opcional

	//$nuevaMalla = mergeContorno($malla, $contorno, $value = 3);
	// el primer punto del contorno siempre esta arriba a la izq.
	$pInicial = array( 'x' => $contorno[0]['col']+1, 'y' => $contorno[0]['fila']+1 );
	$floodFiller = new FloodFiller();

	//$nuevaMalla = $floodFiller->Scan($nuevaMalla, $pInicial, $floodValue = 3, $searchValue = 0);
	$nuevaMalla = $floodFiller->Scan($malla, $pInicial, $floodValue = 3, $searchValue = 0);

	$malla = $nuevaMalla;
	// $contorno = eliminaPuntosRepetidos($contorno);
	$listaContornos[] = $contorno;
    }
    return true;
}

function eliminaPuntosRepetidos($contorno) {
    $newContorno = array();
    $ptosUsados = array();
    foreach($contorno as $c) {
        $k = $c['fila'] . ";" . $c['col'];
        if ( !isset($ptosUsados[$k]) ) {
            $ptosUsados[$k] = $c;
            $newContorno[] = $c;
        }
    }
    $newContorno[] = $contorno[0]; // necesitamos contornos cerrados
    return $newContorno;

}
