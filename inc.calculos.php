<?php

CONST FRONTERA_LATITUD = 90; // latitud complementaria
CONST FEET_TO_METERS = 0.30480370641307;
CONST PASO_A_GRADOS = 180.0;
CONST TAM_CELDA = 0.5; //10; // 0.5; // paso de la malla en NM 0.5 , 0.11 es lo mas que pequeño q no desborda
CONST TAM_CELDA_MITAD = 0.25; // 5; // 0.25; // NM
CONST TAM_ANGULO_MAXIMO = 1; //20; // 1; // NM (lo situamos al doble que tamaño celda)

/**
 * Funcion que permite buscar los puntos limitantes necesarios para poder calcular la cobertura.
 * 
 * @param array $listaObstaculos (ENTRADA)
 * @param int $flm (ENTRADA)
 * @param float $alturaPrimerPtoSinCob (ENTRADA/SALIDA)
 * @param float $anguloPrimerPtoSinCob (ENTRADA/SALIDA)
 * @param float $alturaUltimoPtoCob (ENTRADA/SALIDA)
 * @param float $anguloUltimoPtoCob (ENTRADA/SALIDA)
 * @return boolean devuelve true si encontrado o false en caso contrario (SALIDA)
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
	        // esto antes era un warning
	        die("ERROR el primer obstaculo no tiene cobertura, no tenemos ultimo punto con cobertura, revisar para probar solucion." . PHP_EOL);
	        // constantina 2800 feet
	    } else {
    	        $primerPtoSinCobertura = $listaObstaculos[$i];
                $ultimoPtoCobertura = $listaObstaculos[$i-1];
	
    	        $alturaPrimerPtoSinCob = $listaObstaculos[$i]['altura'];
                $anguloPrimerPtoSinCob = $listaObstaculos[$i]['angulo'];
                $alturaUltimoPtoCob    = $listaObstaculos[$i-1]['altura'];
	        $anguloUltimoPtoCob    = $listaObstaculos[$i-1]['angulo'];
	    }
	    if (false) {
	        print "alturaPrimerPtoSinCob:" . $alturaPrimerPtoSinCob . PHP_EOL;
	        print "alturaUltimoPtoConCob:" . $alturaUltimoPtoCob . PHP_EOL;
	        print "anguloPrimerPtoSinCob:" . $anguloPrimerPtoSinCob . PHP_EOL;
	        print "anguloUltimoPtoConCob:" . $anguloUltimoPtoCob . PHP_EOL;
	        print "flm:" . $flm . PHP_EOL;
            }
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
 * @param int $flm (ENTRADA)
 * @return number (SALIDA)
 */
function calculaAnguloMaximaCobertura($radar, $flm){

    $earthToRadar = $radar['screening']['towerHeight'] +
        $radar['screening']['terrainHeight'] +
        $radar['screening']['radioTerrestreAumentado'];
    $earthToFl = $radar['screening']['radioTerrestreAumentado'] + $flm;

    $anguloMaxCob = acos(
        (pow($earthToRadar,2) + pow($earthToFl,2) - pow($radar['range'],2))
        / (2 * $earthToRadar * $earthToFl)
    );
    if (false) {
        print "radioTerrestreAumentado: " . $radar['screening']['radioTerrestreAumentado'] . PHP_EOL;
        print "flm: " . $flm . PHP_EOL;
        print "earthToRadar: " . $earthToRadar . PHP_EOL;
        print "earthToFl: " . $earthToFl . PHP_EOL;
        print "radar['range']: " . $radar['range'] . PHP_EOL;
        print "radar['screening']['range']: " . $radar['screening']['range'] . PHP_EOL;
        print "anguloMaxCob: " . $anguloMaxCob . PHP_EOL;
    }
    return $anguloMaxCob;
}

/**
 * CASO A
 *  Funcion que calcula las distancias a las que hay cobertura y los angulos de apantallamiento cuando el FL esta por encima del radar
 *  
 * @param array $radar (ENTRADA)
 * @param int $flm nivel de vuelo en metros (ENTRADA)
 * @return array distancias a los alcances máximos por cada azimut (SALIDA)
 */
function calculosFLencimaRadar($radar, $flm ){
		
    // $angulosApantallamiento = array();
    $distanciasAlcances = array();
    $radioTerrestreAumentado = $radar['screening']['radioTerrestreAumentado'];
    $anguloMaxCob = calculaAnguloMaximaCobertura($radar, $flm); // AlphaRange en Matlab
    $earthToFl = $radioTerrestreAumentado + $flm;
    $earthToRadar = $radar['screening']['towerHeight'] + $radar['screening']['terrainHeight'] + $radioTerrestreAumentado;
	
    // recorremos los azimuths
    for ($i=0; $i < $radar['screening']['totalAzimuths']; $i++) {

        // obtenemos la última linea del array para cada azimut.
	if ( !isset($radar['screening']['listaAzimuths'][$i]) ) {
	    print_r($radar);
	    die("ERROR: el azimuth $i no existe" . PHP_EOL);
	}
	$count = count($radar['screening']['listaAzimuths'][$i]);

	// obtenemos la altura del último punto para cada azimuth
	$obstaculoLimitante = $radar['screening']['listaAzimuths'][$i][$count-1]['altura'];
        if ($flm >= $obstaculoLimitante){
            // caso en el que el nivel de vuelo está por encima del obstáculo que limita
	    $earthToEvalPoint = $radioTerrestreAumentado + $obstaculoLimitante;
	    // ángulo del ultimo obstaculo de cada azimuth
	    $angulo = $radar['screening']['listaAzimuths'][$i][$count-1]['angulo'];
            // distancia que corresponde a ese ángulo
            $distancia = sqrt ((pow($earthToRadar,2) + pow($earthToEvalPoint,2)) - 2 * $earthToRadar * $earthToEvalPoint * cos($angulo));
            // ángulo formado entre la vertical del radar (hacia el centro de la tierra) y el obstáculo más alto
	    $gammaMax = acos(
	        (pow($distancia,2) +
	        pow($earthToRadar,2) -
	        pow($earthToEvalPoint,2)) /
	        (2 * $earthToRadar * $distancia)
	    );
	    
	    $theta = asin($earthToRadar * sin($gammaMax) / $earthToFl);
	    // ángulo formado entre el la vertical del radar y el punto de
	    // corte de la recta que psa por el obstáculo más alto y el nivel
	    // de vuelo.
	    $epsilon = M_PI - $theta - $gammaMax;
            // escogemos el ángulo menor entre el ángulo para la máxima
            // cobertura del radar al nivel de vuelo estudiado y el ángulo
            // epsilon. (puede ser que por nivel de vuelo no lleguemos al obstáculo,
            // entonces la cobertura es menor)
            if ($epsilon >  $anguloMaxCob) {
                $distanciasAlcances[$i] = $radioTerrestreAumentado * $anguloMaxCob / MILLA_NAUTICA_EN_METROS;
	    } else {
                $distanciasAlcances[$i] = $radioTerrestreAumentado * $epsilon / MILLA_NAUTICA_EN_METROS;
            }

            if (false) {
                print "radioTerrestreAumentado: " . $radioTerrestreAumentado . PHP_EOL;
                print "count: " . $count . PHP_EOL;
                print "obstaculoLimitante: " . $obstaculoLimitante . PHP_EOL;
                print "earthToEvalPoint: " . $earthToEvalPoint . PHP_EOL;
                print "angulo:" . $angulo . PHP_EOL;
                print "distancia: " . $distancia . PHP_EOL;
                print "gammaMax: " . $gammaMax . PHP_EOL;
	        print "theta: " . $theta . PHP_EOL;
	        print "epsilon: " . $epsilon . PHP_EOL;
                print "anguloMaxCob (AlphaRange): " . $anguloMaxCob . PHP_EOL;
	        print "distanciasAlcances[" . $i . "]: " . $distanciasAlcances[$i] . PHP_EOL;
            }
	 } else { // $fl < $obstaculoLimitante
            // caso en el que el nivel de vuelo está por debajo del obstáculo
            // que limita. es necesario calcular dónde está el obstáculo que
            // limita, porque no está al final de la lista de obstáculos, sino
            // que depende del nivel de vuelo. (en la documentación de Matlab
            // esto no está explicado).
            $anguloLimitante = 0;
	    $alturaPrimerPtoSinCob = 0;
	    $anguloPrimerPtoSinCob = 0;
	    $alturaUltimoPtoCob = 0;
            $anguloUltimoPtoCob = 0;
            $ret = buscarPuntosLimitantes(
                $radar['screening']['listaAzimuths'][$i],
                $flm,
                $alturaPrimerPtoSinCob,
                $anguloPrimerPtoSinCob,
                $alturaUltimoPtoCob,
                $anguloUltimoPtoCob,
                $alturaCentroFasesAntena = $radar['screening']['towerHeight'] + $radar['screening']['terrainHeight']
            );
            if ( !$ret ) {
	        die("ERROR MALIGNO !! No deberias haber entrado aqui" . PHP_EOL);
            }
	    $anguloLimitante = (($flm-$alturaUltimoPtoCob) * (($anguloPrimerPtoSinCob - $anguloUltimoPtoCob) / ($alturaPrimerPtoSinCob - $alturaUltimoPtoCob))) + $anguloUltimoPtoCob;

            if ($anguloLimitante > $anguloMaxCob) {
	        $distanciasAlcances[$i] = $radioTerrestreAumentado * $anguloMaxCob / MILLA_NAUTICA_EN_METROS;
	    } else {
                $distanciasAlcances[$i] = $radioTerrestreAumentado * $anguloLimitante / MILLA_NAUTICA_EN_METROS;
	    }
	 }// else
    }// fin for para recorrer los azimuths

    return $distanciasAlcances;

}

/**
 * Funcion que calcula las coordenadas geograficas de cobertura de un fichero kml para un determinado
 * radar a partir de las coordenadas, el nivel de vuelo y el array de distancias de cobertura.
 * 
 * @param array $radar (ENTRADA)
 * @param int $flm, en metros (ENTRADA)
 * @param array $distanciasAlcances, distancia en millas nauticas al borde de la cobertura, por cada acimut (ENTRADA)
 * @return array $coordenadasGeograficas (SALIDA)
 */
function calculaCoordenadasGeograficasA( $radar, $flm, $distanciasAlcances ){

    $listaContornos = array();
    
    // Calcula el paso en funcion del numero maximo de azimuth (puede ser desde 360 o 720)
    $paso = 360.0 / $radar['screening']['totalAzimuths'];

    $latitudComplementaria = deg2rad(FRONTERA_LATITUD - $radar['lat']);
    $latComp = array(
        'cos' => cos($latitudComplementaria),
        'sin' => sin($latitudComplementaria),
    );

    // Recorrido de los acimuts 
    for ($i = 0; $i < $radar['screening']['totalAzimuths']; $i++) {

        $res = transformaFromPolarToLatLong($radar, $rho = $distanciasAlcances[$i], $theta = $i * $paso, $latComp);

        $listaContornos[$i]['lat'] = $res['lat'];
        $listaContornos[$i]['lon'] = $res['lon'];
        $listaContornos[$i]['alt'] = $flm;
    }

    // cerramos el polígono, repitiendo como último punto el primero
    $listaContornos[] = $listaContornos[0];
    // generamos la misma estructura que se hace en calculaCoordenadasGeograficasB
    $listaContornos = array(
        array(
            'level' => 0,
            'polygon' => $listaContornos,
            'inside' => array(),
        )
    );
    return $listaContornos;
}

/**
 * Transforma de coordenadas polares a latitud longitud en grados
 * @param array $radar información sobre el radar
 * @param float $rho distancia
 * @param float $theta ángulo
 * @param array $latComp seno y coseno de la latitud complementaria, en radianes.
 *
 */
function transformaFromPolarToLatLong($radar, $rho, $theta, $latComp) {

    $ret = array();

    // CALCULO LATITUD
    $anguloCentral = ($rho * MILLA_NAUTICA_EN_METROS / RADIO_TERRESTRE);
    $r_rad = acos(
            $latComp['cos'] * cos($anguloCentral) +
            $latComp['sin'] * sin($anguloCentral) * cos(deg2rad($theta))
        ); // tenemos r en radianes
    $r_deg = rad2deg($r_rad);

    // CALCULO LONGITUD
    $numerador = cos($anguloCentral) - $latComp['cos'] * cos($r_rad);
    $denominador = $latComp['sin'] * sin($r_rad);

    if ($numerador > $denominador) {
        $offsetLongitud = 0;
    } else {
        $offsetLongitud = rad2deg( acos($numerador / $denominador) );
    }

    // asignacion de valores a la estructura de datos
    // si el ángulo actuale es menor de 180, se le suma el offset.
    // si es mayor de 180, se le resta el offset
    if ( $theta < 180 ) {
        $ret['lon'] = $radar['lon'] + $offsetLongitud;
    } else {
        $ret['lon'] = $radar['lon'] - $offsetLongitud;
    }

    $ret['lat'] = FRONTERA_LATITUD - $r_deg;

    return $ret;
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
    // este punto no se usa, quitar
    // $ptoNuevo = array ('angulo' => 0, 'altura' => 0, 'estePtoTieneCobertura' => false); // inicializamos el punto nuevo

    // El paso para interpolar queda en función del tamaño de la celda.
    // Debería ser la mitad del tamaño de la celda, ponemos 1NM para simplificar el depurado
    $anguloMaximo = (TAM_ANGULO_MAXIMO * MILLA_NAUTICA_EN_METROS) / $radioTerrestreAumentado; // CADA MILLA
    $cuentaListaObstaculos = count($listaObstaculos) - 1;

    // recorremos la lista de obstaculos del azimut, comprobando si es
    // necesario insertar algún obstáculo. (si existe un hueco demasiado
    // grande entre distancias interpolamos los puntos)
    // EL ÚLTIMO OBSTÁCULO LO INSERTAMOS FUERA DEL BUCLE
    for ( $i = 0; $i < $cuentaListaObstaculos; $i++ ) {
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
function miraSiHayCobertura($listaObstaculosAmpliada, $flm) {

    for ($i = 0; $i < count($listaObstaculosAmpliada); $i++) {
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
 * @param int   $flm (ENTRADA)
 * @param float $obstaculoLimitante (ENTRADA)
 * @param float $gammaMax Ángulo en radianes que forma el radar con el último punto del terreno (SALIDA)
 * @param float $theta0 Es el seno del ángulo $theta1 el cual representa el ángulo entre la línea de vista del último 
                punto de cada azimuth y la proyección sobre el terreno del punto de corte con el nivel de vuelo
                (identificado a través de su ángulo central $epsilon1)  (ENTRADA/SALIDA)
 * @param float $earthToRadar Distancia del radar al centro de la Tierra (SALIDA)
 * @param float $earthToEvalPoint Distancia desde el centro de la tierra al obstáculo limitante (SALIDA)
 * @param float $earthToFl Distancia desde el centro de la tierra al nivel de vuelo (SALIDA)
 * @param float $radarSupTierra Distancia del radar a la superficie terrestre (SALIDA)
 */
function calculador($radar, $listaObstaculos, $flm, $obstaculoLimitante, &$gammaMax, &$theta0, &$earthToRadar, &$earthToEvalPoint, &$earthToFl, &$radarSupTierra){

    // Distancia del radar a la superficie terrestre
    $radarSupTierra = $radar['screening']['towerHeight'] + $radar['screening']['terrainHeight'];

    // Distancia del radar al centro de la Tierra
    $earthToRadar = $radar['screening']['towerHeight'] + $radar['screening']['terrainHeight'] + $radar['screening']['radioTerrestreAumentado'];

    // Distancia desde el centro de la tierra al obstáculo limitante
    $earthToEvalPoint = $radar['screening']['radioTerrestreAumentado'] + $obstaculoLimitante;

    // Distancia desde el centro de la tierra al nivel de vuelo
    $earthToFl = $radar['screening']['radioTerrestreAumentado'] + $flm;

    // Distancia desde el último punto al radar
    $n = count($listaObstaculos);
    $distanciaUltimoPto = $radar['screening']['radioTerrestreAumentado'] * $listaObstaculos[$n-1]['angulo'];

    // Línea de vista del últmio punto del terreno
    $distanciaCobertura = sqrt(
        pow($earthToRadar,2) +
        pow($earthToEvalPoint,2) -
        2 * $earthToRadar * $earthToEvalPoint * cos($listaObstaculos[$n-1]['angulo'])
    );
	
    // Angulo en radianes que forma el radar con el último punto del terreno
    $gammaMax = acos((pow($distanciaCobertura,2) +  pow($earthToRadar,2) - pow($earthToEvalPoint,2)) / (2 * $earthToRadar * $distanciaCobertura));

    // sin($theta1) donde $theta1 es el ángulo entre la línea de vista del
    // último punto de cada azimuth y la proyección sobre el terreno del punto
    // de corte con el nivel de vuelo(el más alejado)
    $theta0 = $earthToRadar * sin($gammaMax) / $earthToFl;

    if (false) {
        print "Radio terrestre aumentado" . ":" . $radar['screening']['radioTerrestreAumentado'] . PHP_EOL;
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
    $epsilon1 = M_PI - $theta1 - $gammaMax;

    // Ángulo central del punto de corte más cercano de la línea de vista(del último punto de cada azimuth)  con el nivel de vuelo
    $epsilon2 = M_PI - (M_PI-$theta1) - $gammaMax;
    $ptosCorte[0] = $epsilon1 * $radioTerrestreAumentado;
    $ptosCorte[1] = $epsilon2 * $radioTerrestreAumentado;

    return;
}

/**
 * CASO B
 * Funcion que calcula las coberturas cuando el nivel de vuelo FL, esta por debajo del radar
 * 
 * @param array $radar (ENTRADA / SALIDA)
 * @param int $flm nivel de vuelo en metros (ENTRADA)
 */
function calculosFLdebajoRadar(&$radar, $flm){

    $anguloMaxCob = calculaAnguloMaximaCobertura($radar, $flm);
    // Ángulo (en radianes) entre el último pto de cada acimut y el punto
    // extra para una distancia de 0.1 NM. Es una pequeña distancia que se
    // le suma al ángulo de cada punto [0.1 NM]
    // para añadir un ptoExtra y poder aproximar el mallado
    $anguloMinimo = (0.1 *  MILLA_NAUTICA_EN_METROS ) / $radar['screening']['radioTerrestreAumentado'];
    $anguloMaximo = (TAM_ANGULO_MAXIMO * MILLA_NAUTICA_EN_METROS) / $radar['screening']['radioTerrestreAumentado'];
    $ptosNuevos = array();
    $ptoExtra = array( 'angulo' => 0, 'altura' => 0, 'estePtoTieneCobertura' => false);
    $ptoMaxCob = array('angulo'=> $anguloMaxCob, 'altura'=> 0,'estePtoTieneCobertura'=> true);

    print "[00%]";
    $countPct_old = 0;

    for ($i=0; $i < $radar['screening']['totalAzimuths']; $i++) {

        $countPct = $i*100.0 / $radar['screening']['totalAzimuths'];
        if ( ($countPct - $countPct_old) > 10 ) { print "[" . round($countPct) . "%]"; $countPct_old = $countPct; }

        // Interpolamos puntos terreno
	$listaObstaculosAmpliada = interpolarPtosTerreno(
	    $radar['screening']['listaAzimuths'][$i],
	    $radar['screening']['radioTerrestreAumentado'],
	    1
	);
	// Comprobamos si para el nivel de vuelo dado, existe cobertura y lo apuntamos
	$listaObstaculosAmpliada = miraSiHayCobertura($listaObstaculosAmpliada, $flm);

        // Se obtiene el punto limitante, último punto del terreno donde tenemos
        // información de los obstáculos
 	$numPtosAzimut = count( $radar['screening']['listaAzimuths'][$i] );
 	$obstaculoLimitante = $radar['screening']['listaAzimuths'][$i][$numPtosAzimut-1]['altura'];
 	$anguloLimitante = $radar['screening']['listaAzimuths'][$i][$numPtosAzimut-1]['angulo'];
 	$ptoLimitante = array(
 	    'angulo' => $anguloLimitante,
 	    'altura' => $obstaculoLimitante,
 	    'estePtoTieneCobertura' => true
 	);

 	calculador( $radar, $listaObstaculosAmpliada, $flm, $obstaculoLimitante, $gammaMax, $theta0, $earthToRadar, $earthToEvalPoint, $earthToFl, $radarSupTierra );

 	// CASO A: Último punto del acimut por debajo del nivel de vuelo y por debajo del radar
 	if( ( $obstaculoLimitante < $flm ) && ( $obstaculoLimitante < $radarSupTierra ) ) {
 	    if ((abs($theta0)) <= 1){
	 	obtenerPtosCorte($earthToRadar, $gammaMax, $earthToFl, $radar['screening']['radioTerrestreAumentado'], $epsilon1, $epsilon2, $ptosCorte);
		$ptoUno = array('angulo' => $epsilon1, 'altura'=> 0, 'estePtoTieneCobertura'=> true);
		// A.1: se interpola desde el último punto del terreno hasta el punto 1
		if ($epsilon1 < $anguloMaxCob) {
	 	    $rangoLuz =  array ($ptoLimitante, $ptoUno);
	 	    // devuelve una lista con los puntos que se han interpolado 
	 	    $ptosLuz = interpolarPtosTerreno($rangoLuz, $radar['screening']['radioTerrestreAumentado'], 3);
	 	    $listaObstaculosAmpliada = array_merge( $listaObstaculosAmpliada, $ptosLuz );
	 	    $ptoExtra = array( 'angulo' => ($epsilon1 + $anguloMinimo), 'altura' => 0, 'estePtoTieneCobertura' => false );
	 	    $listaObstaculosAmpliada= array_merge( $listaObstaculosAmpliada, array($ptoExtra) );
	 	
	 	// A.2: se interpola desde el último punto del terreno hasta el punto de máxima cobertura
	 	} else {
	 	    $rangoLuz =  array ($ptoLimitante, $ptoMaxCob);
                    $ptosLuz = interpolarPtosTerreno($rangoLuz, $radar['screening']['radioTerrestreAumentado'], 3);
                    $listaObstaculosAmpliada = array_merge( $listaObstaculosAmpliada, $ptosLuz );
	 	    $ptoExtra = array( 'angulo' => ($anguloMaxCob + $anguloMinimo), 'altura' => 0, 'estePtoTieneCobertura' => false );
	 	    $listaObstaculosAmpliada = array_merge( $listaObstaculosAmpliada, array($ptoExtra) );
	 	}
            // A.3: El corte con el nivel de vuelo se traduce en angulos negativos
            } elseif ( abs($theta0) > 1 ) {
	        $ptoExtra = array( 'angulo' => ($anguloLimitante + $anguloMinimo), 'altura' => 0, 'estePtoTieneCobertura' => false );
	        $listaObstaculosAmpliada= array_merge( $listaObstaculosAmpliada, array($ptoExtra) );
	    }
	// fin if caso A
	// CASO B: Último punto del acimut por encima del nivel de vuelo y por debajo del radar
	// incluye el caso en el que la altura del radar esté al mismo nivel que el último obstáculo
        } elseif ( ( $obstaculoLimitante > $flm ) && ( $radarSupTierra >= $obstaculoLimitante ) ) {
	    if ( (abs($theta0)) <= 1 ) {
                obtenerPtosCorte($earthToRadar, $gammaMax, $earthToFl, $radar['screening']['radioTerrestreAumentado'], $epsilon1, $epsilon2, $ptosCorte);
		$ptoUno = array( 'angulo'=> $epsilon1, 'altura'=> 0, 'estePtoTieneCobertura'=> true ); // epsilon1
		$ptoDos = array( 'angulo'=> $epsilon2, 'altura'=> 0, 'estePtoTieneCobertura'=> true ); // epsilon2
		$anguloMedio = ($epsilon2 + $anguloLimitante) / 2.0; //punto medio entre el ultimo obstaculo y epsilon2
		$ptoMedio = array( 'angulo'=> $anguloMedio, 'altura'=> 0, 'estePtoTieneCobertura'=> false );
		// B.1: se interpola desde el último punto del terreno hasta el punto 1 pasando por el punto 2
	 	if ( ($epsilon1 < $anguloMaxCob) && ($epsilon2 < $anguloMaxCob) ) {
	 	    // rango sombra
	 	    // ptoDos debería estar ¿después? de ptoLimitante, o bien
	 	    // cambiar el orden al crear el array. PtoLimitante es el
	 	    // último punto del array de obstáculosAmpliados
	 	    // PROBLEMA 1
	 	    // B.1.1: se interpola desde el último punto del terreno hasta el punto 2 (rango SOMBRA)
                    if ( ($epsilon1 > $anguloLimitante) ) {
                        // En el caso en que la zona de sombra sea menor de una milla, añadimos un punto
                        // intermedio entre el último obstáculo y el epsilon2, sin cobertura, para que en
                        // la malla haya una discontinuidad.
                        if ($epsilon2 - $anguloLimitante <= $anguloMaximo){
                            $ptosSombra = array($ptoMedio, $ptoDos);
                        } else {
                            $rangoSombra = array( $ptoLimitante, $ptoDos );
                            $ptosSombra = interpolarPtosTerreno( $rangoSombra, $radar['screening']['radioTerrestreAumentado'], 2);
                        }
                        $listaObstaculosAmpliada = array_merge( $listaObstaculosAmpliada, $ptosSombra );
                        // B.1.2: Se interpola desde el punto 2 al punto 1 (rango LUZ)
                        $rangoLuz =  array( $ptoDos, $ptoUno );
                        $ptosLuz = interpolarPtosTerreno( $rangoLuz, $radar['screening']['radioTerrestreAumentado'], 3 );
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
                    // En el caso en que la zona de sombra sea menor de una milla, añadimos un punto
                    // intermedio entre el último obstáculo y el epsilon2, sin cobertura, para que en
                    // la malla haya una discontinuidad.
                    if ($epsilon2 - $anguloLimitante <= $anguloMaximo){
                        $ptosSombra = array($ptoMedio, $ptoDos);
                    } else {
                        $rangoSombra = array ($ptoLimitante, $ptoDos);
                        $ptosSombra = interpolarPtosTerreno($rangoSombra, $radar['screening']['radioTerrestreAumentado'], 2);
                    }
                    $listaObstaculosAmpliada = array_merge($listaObstaculosAmpliada, $ptosSombra);
                    // B.2.2: Se interpola desde el punto 2 hasta el punto de máxima cobertura (rango LUZ)
                    $rangoLuz =  array ($ptoDos, $ptoMaxCob);
                    $ptosLuz = interpolarPtosTerreno($rangoLuz, $radar['screening']['radioTerrestreAumentado'], 3);
                    $listaObstaculosAmpliada = array_merge($listaObstaculosAmpliada, $ptosLuz);
                    $ptoExtra = array ('angulo' => ($anguloMaxCob + $anguloMinimo), 'altura' => 0, 'estePtoTieneCobertura' => false);
                    $listaObstaculosAmpliada = array_merge($listaObstaculosAmpliada, array($ptoExtra));

	 	// fin caso B.2
	 	// B.3: Los cortes con el nivel de vuelo están más allá del punto de máxima cobertura
	 	} elseif ( (($epsilon1 > $anguloMaxCob) && ($epsilon2 > $anguloMaxCob)) ) { // caseo B.3
                    $ptoExtra = array ('angulo' => ($anguloLimitante + $anguloMinimo), 'altura' => 0, 'estePtoTieneCobertura' => false);
                    $listaObstaculosAmpliada = array_merge($listaObstaculosAmpliada, array($ptoExtra));
	 	}
	    // B.4: Los cortes con el nivel de vuelo se traducen en angulos negativos
	    } elseif ( abs($theta0) > 1 ) {
	 	$ptoExtra = array ('angulo' => ($anguloLimitante + $anguloMinimo), 'altura' => 0, 'estePtoTieneCobertura' => false);
	 	$listaObstaculosAmpliada = array_merge($listaObstaculosAmpliada, array($ptoExtra));
	    
            } // Fin CASO B
            // CASO C: Último punto del acimut por encima del nivel de vuelo y por encima del radar
        } elseif ( ($obstaculoLimitante > $flm) && ($radarSupTierra < $obstaculoLimitante) ) {
            $ptoExtra = array ('angulo' => ($anguloLimitante + $anguloMinimo), 'altura' => 0, 'estePtoTieneCobertura' => false);
	    //print "IFSNOP CASO C" . PHP_EOL;
 	    //print_r($ptoExtra);

            $listaObstaculosAmpliada = array_merge($listaObstaculosAmpliada, array($ptoExtra));
	}

        // safety check
	if ( !isset($listaObstaculosAmpliada) || !is_array($listaObstaculosAmpliada) ) {
            die ("buscaDistanciaMenor: $$listaObstaculos debería ser un array");
        }
        // metemos la lista de obstaculos nueva en la estructura
        $radar['screening']['listaAzimuths'][$i] = $listaObstaculosAmpliada;

        /*
        // OUTPUT DEBUG
        for($jj=0;$jj<count($radar['listaAzimuths'][$i]); $jj++) {
            print $radar['listaAzimuths'][$i][$jj]['angulo'] . "|" .
                $radar['listaAzimuths'][$i][$jj]['angulo']*$radioTerrestreAumentado/MILLA_NAUTICA_EN_METROS . "|" .
                $radar['listaAzimuths'][$i][$jj]['altura'] . PHP_EOL;
        }
        */
    } // for
    print "[100%]";
    return;
}

/**
 * Función que busca el índice del punto más próximo al valor dado dentro
 * de una lista de obstáculos comparando los angulos, con la clave
 * de búsqueda pasada como parámetro.
 *
 * @param float $value valor para buscar (ENTRADA)
 * @param array $arr lista de valores (ENTRADA)
 * @param int $low índice inferior donde buscar
 * @param int $high índice superior donde buscar
 * @param string $key índice del campo a comparar con $value
 * @url https://stackoverflow.com/questions/4257838/how-to-find-closest-value-in-sorted-array
 *
 * @return int índice del valor más cercano
 */
function findNearestValue($value, $arr, $low, $high, $key) {
    $res = false;
    if ( ($high - $low) > 1 ) {
        $mid = round($low + ($high - $low) / 2, $precision = 0, $mode = PHP_ROUND_HALF_UP );
        if ( $arr[$mid][$key] > $value ) {
            $res = findNearestValue($value, $arr, $low, $mid, $key);
        } else if ( $arr[$mid][$key] < $value ) {
            $res = findNearestValue($value, $arr, $mid, $high, $key);
        } else {
            $res = $mid;
        }
    } else {
        $res = (abs($value-$arr[$low][$key]) < abs($value-$arr[$high][$key])) ? $low : $high;
    }
    return $res;
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
        $acimut = rad2deg( atan($y / $x) + M_PI );
    } elseif ($x > 0){
        if ($y < 0){
            $acimut = rad2deg( atan($y / $x) + 2 * M_PI );
        } else{ // $y>= 0
            $acimut = rad2deg( atan($y / $x) );
        }
    } elseif ($x == 0) {
        if ($y < 0) {
            $acimut = rad2deg( ( 3 * M_PI ) / 2 );
	} elseif ($y > 0) {
            $acimut = rad2deg( M_PI / 2 );
	}
    }

    $acimut = 90.0 - $acimut;
    if ($acimut < 0) {
        $acimut = $acimut + 360.0;
    }

    return $acimut;
}

/**
 * Funcion que crea una malla de tamaño el doble del alcance del radar y
 * la rellena con 0 o 1 en función de si el punto al que se aproxima el
 * acimut de cada celda de la malla tiene o no cobertura.
 * 
 * @param array $radar (ENTRADA)
 * @param float $flm (ENTRADA)
 * @param array $distanciasAlcances (ENTRADA)
 * @return array $malla (SALIDA)
 */
function generacionMallado($radar, $flm, $distanciasAlcances) {

    // pasamos a  millas nauticas el rango del radar que esta almacenado en metros en la estructura radar
    $tamMalla = (( 2 * $radar['range'] ) / TAM_CELDA) / MILLA_NAUTICA_EN_METROS;
    // $xR = 0; // coordenada x del radar
    // $yR = 0; // coordenada y del radar
    $radioTerrestreAumentadoEnMillas  = $radar['screening']['radioTerrestreAumentado'] / MILLA_NAUTICA_EN_METROS;

    $malla = array(); // creamos una malla vacia 
    $azimutTeorico = 0; // azimut teorico calculado
    $azimutCelda = 0; // azimut aproximado 
    $pos = 0;

    // CENTRAMOS LA MALLA Y CALCULAMOS EL PTO MEDIO DE CADA CELDA
    $tamMallaMitad = $tamMalla / 2.0;
    print "[tamMallaMitad: " . $tamMallaMitad . "]";

    // CALCULAMOS LAS COORDENADAS X DE CADA CELDA (sacamos la parte común del cálculo fuera del bucle)
    $x_fixed = -( $tamMallaMitad * TAM_CELDA ); // + ( TAM_CELDA_MITAD ); // ($i * TAM_CELDA) 
    // Factor de corrección según el número de azimut total que haya en el fichero de screening.
    // Como los ángulos son siempre 360, si en el fichero de screening se define otro número,
    // tendremos que ajustar el ángulo que calculamos para adecuarlo al número de azimut guardado
    // según screening. Es decir, si hay 720 azimut, y nos sale un ángulo de 360, realmente será de 720.

    $ajusteAzimut = $radar['screening']['totalAzimuths'] / 360.0;
    print "[Tamaño malla: " . $tamMalla . "]";
    print "[00%]";
    $countPct_old = 0;
    // la malla tiene tamMalla + 1, para que el centro siempre quede en una celda
    for ($i = 0; $i <= $tamMalla; $i++){ // recorre las columnas de la malla 
        //print "[$i]";
	$countPct = $i*100.0 / $tamMalla;
	if ( ($countPct - $countPct_old) > 10 ) { print "[" . round($countPct) . "%]"; $countPct_old = $countPct; }

        // CALCULAMOS LAS COORDENADAS X DE CADA CELDA
        $x = $x_fixed + ($i * TAM_CELDA);
        // $x = ($i * TAM_CELDA) - ( $tamMallaMitad * TAM_CELDA ) + ( TAM_CELDA_MITAD );
		
        // CALCULAMOS LAS COORDENADAS X DE CADA CELDA (sacamos la parte común del cálculo fuera del bucle)
        $y_fixed = ( $tamMallaMitad * TAM_CELDA ); // - ( TAM_CELDA_MITAD );//  #- ( $j * TAM_CELDA ) 

        for ($j = 0; $j <= $tamMalla; $j++){ // recorre las filas de la malla 
            // CALCULAMOS LAS COORDENADAS Y DE CADA CELDA
            $y = $y_fixed - ($j * TAM_CELDA);
            // $y = ( $tamMallaMitad * TAM_CELDA ) - ( TAM_CELDA_MITAD ) - ( $j * TAM_CELDA );

            // CALCULAMOS EL AZIMUT DE CADA CELDA Y APROXIMAMOS
            $azimutTeorico = calculaAcimut($x, $y);
			
	    $azimutCelda = round( $azimutTeorico * $ajusteAzimut, $precision = 0, $mode = PHP_ROUND_HALF_UP);
            // Un último paso, por si acaso al redondear nos salimos de la lista de azimut, ajustamos al máximo.
            // La lista va de 0 a 359 (o de 0 a 719)...
	    if ( $azimutCelda == $radar['screening']['totalAzimuths'] ) {
	        $azimutCelda--;
	    }

            // al dividir entre el radio tenemos el angulo deseado
	    // $distanciaCeldaAradar = ( sqrt( pow( ($xR - $x),2 )+ pow( ($yR - $y),2) ) ) / $radioTerrestreAumentadoEnMillas;
	    $distanciaCeldaAradar = ( sqrt(pow($x,2)+pow($y,2)) ) / $radioTerrestreAumentadoEnMillas;

            // print "$i)" . $azimutCelda . "|" . ( sqrt(pow($x,2)+pow($y,2)) ) . "|" . $x . "|" . $y . " ";
            // print "$i)" . $x . "|" . $y . "  ";

	    // busca la posicion de la  distancia mas proxima en la lista de obstaculos del acimut aproximado (el menor)
	    // $pos = buscaDistanciaMenor($radar['listaAzimuths'][$azimutCelda], $distanciaCeldaAradar);
	    // $pos = buscaDistanciaMenor2($radar['listaAzimuths'][$azimutCelda], $distanciaCeldaAradar);
	    $pos = findNearestValue(
	        $distanciaCeldaAradar,
	        $radar['screening']['listaAzimuths'][$azimutCelda],
	        0,
	        count($radar['screening']['listaAzimuths'][$azimutCelda]) - 1,
	        $key = "angulo"
	    );

	    $alturaCentroFasesAntena = $radar['screening']['towerHeight'] + $radar['screening']['terrainHeight'];
            if ( $flm >= $alturaCentroFasesAntena ) {
	        if ($distanciaCeldaAradar * $radioTerrestreAumentadoEnMillas > $distanciasAlcances[$azimutCelda]){
	            // aqui trasponemos la matriz, no se si es sin querer o es a prop..sito
	            // el valor 1 para representar el caso en el que hay cobertura y 0 para lo contrario
	            $malla[$j][$i] = 0;
	        } else {
                    $malla[$j][$i] = 1;
                }
	    } else {
	        if ( ($radar['screening']['listaAzimuths'][$azimutCelda][$pos]['estePtoTieneCobertura']) ===false){
	            // aqui trasponemos la matriz, no se si es sin querer o es a prop..sito
	            // el valor 1 para representar el caso en el que hay cobertura y 0 para lo contrario
                    $malla[$j][$i] = 0;
	        } else {
                    $malla[$j][$i] = 1;
	        }
	    }
	}
	// print PHP_EOL . PHP_EOL;
    }
    print "[100%]";
    return $malla;
}

/**
 * Funcion que crea una matriz con la matriz que le entra como parametro de entrada y la bordea con 0's
 * 
 * @param array $malla (ENTRADA)
 * @return array $mallaMarco (SALIDA)
 */
function mallaMarco($malla){
	
    $mallaMarco = array();
	
    // creamos una malla mayor y la inicializamos a 0
    for($i = 0; $i < count($malla)+2; $i++) {
        for ($j = 0; $j < count($malla)+2; $j++) {
	    $mallaMarco[$i][$j] = 0;
	}
    }
    // recorremos la malla y la copiamos en la malla de mayor tamaño 
    for($i = 0; $i < count($malla); $i++) {
        for ($j = 0; $j < count($malla); $j++) {
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
 * @param array $listaC (ENTRADA), estructura que asocia la fila con la long, la col con la latitud y que ademas almacena la altura
 * @return array filas asociadas con la longitud y columnas con latitud 
 */
function calculaCoordenadasGeograficasB( $radar, $flm, $listaContornos ) {
/*
$listaC = array(
    array(
        'level' => 0,
        'polygon' => array(
            0 => array('fila' => 444, 'col' => 333),
            1 =>  array('fila' => 444, 'col' => 333)),
        'inside' => array(
            'level' => 1,
            'polygon' => array(
                0 => array('fila' => 444, 'col' => 333),
                1 =>  array('fila' => 444, 'col' => 333)),
            )
        ),
    );
*/
    // DUDA ¿es necesario?
    // $xR = 0;
    // $yR = 0;
    // pasamos a  millas nauticas el rango del radar que esta almacenado en metros en la estructura radar
    $tamMalla = (( 2 * $radar['range'] ) / TAM_CELDA) / MILLA_NAUTICA_EN_METROS;
    $tamMallaMitad = $tamMalla / 2.0;
    // CALCULO DE LA LATITUD COMPLEMENTARIA
    $latitudComplementaria = deg2rad(FRONTERA_LATITUD - $radar['lat']);
    $latComp = array(
        'cos' => cos($latitudComplementaria),
        'sin' => sin($latitudComplementaria),
    );
    foreach( $listaContornos as &$contorno ) {
        foreach ( $contorno['polygon'] as &$p ) {
            // transforma las coordenadas del level 0
            $p = transformaCoordenadas($radar, $flm, $tamMallaMitad, $p, $latComp);
        }
        foreach ( $contorno['inside'] as &$contorno_inside ) {
            foreach ($contorno_inside['polygon'] as &$p_inside) {
                // transforma las coordenadas del level 1
                $p_inside = transformaCoordenadas($radar, $flm, $tamMallaMitad, $p_inside, $latComp);
            }
        }
    }

    return $listaContornos;
}

/**
 * Transforma coordenadas X/Y (col/fila) en latitud/longitud (en grados)
 *
 * @param array $radar datos del centro de coordenadas, definidos por un radar (ENTRADA)
 * @param float $flm nivel de vuelo que se va a poner a los puntos transformados (ENTRADA)
 * @param int $tamMallaMitad distancia al centro de la malla (ENTRADA)
 * @param array $p punto con col/fila de las coordenadas a transformar (ENTRADA)
 * @return array nuevo punto con filas asociadas con la longitud y columnas con latitud en grados (SALIDA)
 */
function transformaCoordenadas($radar, $flm, $tamMallaMitad, $p, $latComp) {
    // ¿por qué se utiliza el -1? RESPUESTA porque le hemos añadido un 1 a la malla cuando
    // la generábamos, para hacer la malla impar y que la celda del centro es la que contenga
    // al radar
    $x = (($p['col'] - 1) * TAM_CELDA) - ($tamMallaMitad * TAM_CELDA);
    $y = ($tamMallaMitad * TAM_CELDA) - (($p['fila'] - 1) * TAM_CELDA);

    // CALCULO DE LA DISTANCIA
    // $distanciaCeldaAradar = (sqrt(pow(($xR- $x),2)+ pow(($yR - $y),2)) );
    $distanciaCeldaAradar = sqrt(pow($x,2) + pow($y,2));
    // CALCULO DEL ACIMUT
    $azimutTeorico = calculaAcimut($x, $y);

    $res = transformaFromPolarToLatLong($radar, $distanciaCeldaAradar, $azimutTeorico, $latComp);

    $res['alt'] = $flm;
    return $res;
}

/**
 * Helper de determinaContornos2. Wrapper para llamar a CONREC_contour
 * @param array $malla (ENTRADA)
 * @return array lista de segmentos de contornos
 */
function determinaContornos2_getContornos($malla) {

//    if ( NULL === ($listaContornos = json_decode(file_get_contents("aitana.json"), true)) ) {

    $d = array();
    $x = array();
    $y = array();
    $empty = 0;
    // inicializamos los arrays de coordenadas necesarios para CONREC_contour
    $iMalla = count($malla);
    for($i=0; $i<$iMalla; $i++) {
        $d[$i] = array();
        $jMalla = count($malla[$i]);
        for($j=0; $j < $jMalla; $j++) {
            // cambiamos los valores de x y de y por los valores que CONREC espera.
            $val = $malla[($iMalla-1) - $j][$i];
            // cálculo para saber si la malla está toda a 0, y
            // por lo tanto no habrá cobertura
            $empty += $val;
            $d[$i][$j] = $val;
        }
    }
    
    if ($empty == 0) {
        // sanity check. si no hay ningún 1 en toda la malla,
        // abortar porque significa que la malla está vacía.
        return array();
    }
    
    // nuestra malla siempre es cuadrada. CONREC necesita los índices de x y de y.
    // al ser cuadrada, atajamos.
    for($i=0; $i<count($malla); $i++) {
        $x[$i] = $i; $y[$i] = $i;
    }

    // se llama a CONREC pidiendo 2 contornos. Si pidiésemos uno, se calcularía al 50% entre
    // la celda con valor a 1 y la celda con valor a 0, es decir, entre se interpola entre medias.
    // Al pedir 2, se hacen dos contornos, uno al 33% y otro al 66%. Por como se ordenan, si
    // elegimos el segundo, se queda más cerca de la celda con valor.
    // Debería comprobar si es así o al revés, porque el comentario de más abajo me ha dejado
    // la duda. Sea como sea, la salida que tenemos es la más correcta, uniendo agueros de
    // no cobertura en zonas de cobertura, y nos interesa porque es más conservador.
    $contornos = CONREC_contour($malla, $x, $y, $numContornos = 2);

/*
    foreach($contornos as $contorno) {
        foreach($contorno['segments'] as $segmento) {
            fwrite(STDERR,  $segmento['x1'] . ";" . $segmento['y1'] . ";" . $segmento['x2'] . ";" . $segmento['y2'] . PHP_EOL);
        }
        exit(-1);
    }
*/
    // para quedarnos con el segundo contorno generado, que siempre será el más conservador
    $c = $contornos[1];
    print "[conrec: " . count($c) . "]";

    return $c;

}

/**
 * Helper de determinaContornos2. Procesa la salida de CONREC_contour para obtener listas de polígonos
 * Se recorren todos los segmentos y se ordenan, para unir unos con otros. Puede haber varios
 * polígonos, así que se pueden crear varios contornos.
 * Apuntamos cual es la esquina inferior izquierda porque luego la usaremos para rotar el polígono
 * (si está dentro o fuera, debe ir CW o CCW).
 * @param array $contorno lista de segmentos de contornos (ENTRADA)
 * @return array lista de contornos cerrados
 */
function determinaContornos2_joinContornos($c) {

    if ( !is_array($c) || 0 == count($c) ) {
        return array();
    }
    $contornoFixed = array();
    $sgm = array_shift($c['segments']);
    $x1 = $sgm['x1']; $y1 = $sgm['y1']; $contornoFixed[] = array( 'fila'=>$x1, 'col'=>$y1 );
    $leftCorner = array( 'xMin' => $x1, 'yMin' => $y1, 'key' => 0 );
    $x2 = $sgm['x2']; $y2 = $sgm['y2']; $contornoFixed[] = array( 'fila'=>$x2, 'col'=>$y2 );
    $leftCorner = findLeftCorner( $x2, $y2, $leftCorner, $contornoFixed );

    $countPct_old = 0; $cuentaTotal = count($c['segments']); $cuentaActual_old = -1;

    print "[Segmentos: $cuentaTotal][00%]";
    while(count($c['segments'])>0) {
        $cuentaActual = count($c['segments']);

//        print "[cuentaActual:" . $cuentaActual . "] " .
//            "[cuentaActual_old:" . $cuentaActual_old ."] " .
//            "[listaContornos:" . count($listaContornos) . "] " .
//            "[contornoFixed:" . count($contornoFixed) . "] " .
//            PHP_EOL;

        if ( $cuentaActual_old == $cuentaActual ) {
            // si no hemos conseguido encontrar ningún segmento que contine al último, es que el segmento
            // se ha cerrado, así que abriremos otro segmento

            /*
            foreach($c['segments'] as $segmento) {
                fwrite(STDERR,  $segmento['x1'] . ";" . $segmento['y1'] . ";" . $segmento['x2'] . ";" . $segmento['y2'] . PHP_EOL);
            }
            print_r($c['segments']);
            print_r($contornoFixed);
            die("ERROR determinaContornos2: no se ha encontrado punto siguiente" . PHP_EOL);
            */

            // antes de añadir, mirar si el contorno está generado en counter-clockwise
            // $orientacion = comprobarOrientacion($contornoFixed, $leftCorner);
            //foreach($contornoFixed as $s) {
            //    print $s['fila'] . ";" . $s['col'] . PHP_EOL;
            //}
            // if ($orientacion) print "CCW:" . count($contornoFixed) . PHP_EOL; else print "CW:" . count($contornoFixed) . PHP_EOL;
            
            $listaContornos[] = array('level' => -1, 'polygon' =>$contornoFixed, 'leftCorner' => $leftCorner, 'inside' => array());
            $contornoFixed = array();
            $sgm = array_shift($c['segments']);
            $x1 = $sgm['x1']; $y1 = $sgm['y1']; $contornoFixed[] = array( 'fila'=>$x1, 'col'=>$y1 );
            $leftCorner = array( 'xMin' => $x1, 'yMin' => $y1, 'key' => 0 );
            $x2 = $sgm['x2']; $y2 = $sgm['y2']; $contornoFixed[] = array( 'fila'=>$x2, 'col'=>$y2 );
            $leftCorner = findLeftCorner( $x2, $y2, $leftCorner, $contornoFixed );

        }
        $cuentaActual_old = $cuentaActual;
        $countPct = ($cuentaTotal - $cuentaActual)*100.0 / $cuentaTotal;
        if ( ($countPct - $countPct_old) > 10 ) { print "[" . round($countPct) . "%]"; $countPct_old = $countPct; }

        $oldx = $contornoFixed[count($contornoFixed)-1]['fila'];
        $oldy = $contornoFixed[count($contornoFixed)-1]['col'];
        // print "count: " . count($c['segments']) . PHP_EOL;
        foreach($c['segments'] as $k => $sgm) {
        // print $k . PHP_EOL;
            $x1 = $sgm['x1']; $y1 = $sgm['y1'];
            $x2 = $sgm['x2']; $y2 = $sgm['y2'];
            if ( (abs($oldx - $x1) < 0.0001) &&
                (abs($oldy - $y1) < 0.0001) ) {
                // print "found $k para oldx,oldy,x1,y1" . PHP_EOL;
                // print "oldx: $oldx oldy: $oldy x1: $x1 y1: $y1" . PHP_EOL;
                // $contornoFixed[] = array('fila'=>$x1, 'col' => $y1);
                $contornoFixed[] = array('fila'=>$x2, 'col' => $y2);
                unset($c['segments'][$k]);
                $leftCorner = findLeftCorner( $x2, $y2, $leftCorner, $contornoFixed );
                break;
            } elseif ( (abs($oldx - $x2) < 0.0001) &&
                (abs($oldy - $y2) < 0.0001) ) {
                // print "found $k para oldx,oldy,x2,y2" . PHP_EOL;
                // print "oldx: $oldx oldy: $oldy x2: $x2 y2: $y2" . PHP_EOL;
                // $contornoFixed[] = array('fila'=>$x2, 'col'=>$y2);
                $contornoFixed[] = array('fila'=>$x1, 'col'=>$y1);
                unset($c['segments'][$k]);
                $leftCorner = findLeftCorner( $x1, $y1, $leftCorner, $contornoFixed );
                break;
            }
        }
    }
    // añadimos el último polígono que nos quedaba pendiente
    $listaContornos[] = array('level' => -1, 'polygon' =>$contornoFixed, 'leftCorner' => $leftCorner, 'inside' => array());
    print "[100%]";

//    file_put_contents("aitana.json", json_encode($listaContornos));
//    }
//    print "sin pasar por la casilla de salida" . PHP_EOL;

    return $listaContornos;
}

/**
 * Helper de determinaContornos2. Ordena la jerarquía de una lista de
 * contornos cerrados, para saber quién depende de quién. También rota
 *los contornos dependiendo de si están dentro o fuera de otro.
 * @param array $contorno lista de contornos cerrados (ENTRADA)
 * @return array jerarquía de contornos ya clasificados y rotados
 */
function determinaContornos2_sortContornos($listaContornos) {
/*
    $new = array();
    unset($listaContornos[0]);
    unset($listaContornos[1]);
    $listaContornos = array_values($listaContornos);
    foreach( $listaContornos as $k => $l ) {
        if ( count($l['polygon']) == 15 ) continue;
        $new[] = $l;
    }
    $listaContornos = $new;
    unset($listaContornos[count($listaContornos)-1]);
    $listaContornos = array_values($listaContornos);
    
    foreach( $listaContornos as $k => $l ) {
        foreach ( $l['polygon'] as $k2 => $l2) {
            print $l2['fila'] . ";" . $l2['col'] . PHP_EOL;
        }
        print PHP_EOL;
    }
*/

    if ( !isset($listaContornos) || 0 == count($listaContornos) ) {
        return array();
    }

    // calculamos la jerarquía de los polígonos y los rotamos según su profundidad
    $salir = false;
    while ( !$salir ) {
/*
        print"STATUS" . PHP_EOL;
        foreach( $listaContornos as $k => $l ) {
            print $k . "] " . count($l['polygon']) . PHP_EOL;
            print "\t level:" . $l['level'] . PHP_EOL;
            print "\t inside:" . count($l['inside']) . PHP_EOL;
        }
        print "============================================" . PHP_EOL;
*/
        // print count($listaContornos) . " ";
        // print_r(array_keys($listaContornos));
        // print PHP_EOL;
        $c = array_shift( $listaContornos );
        if ( -1 != $c['level'] ) {
            // nunca deberíamos comprobar dos veces si un polígono tiene elementos dentro
            die("ERROR al analizar la jerarquía de contornos" . PHP_EOL . print_r($c, true) . PHP_EOL);
        }
        $is_in_polygon = false;
/*
        print "C:" . PHP_EOL;
        print_r($c);
        print PHP_EOL;
*/
        foreach( $listaContornos as $k => $l ) {
            // comprobamos si el contorno tiene algún contorno dentro
/*
            print "L:" . PHP_EOL;
            print_r($l['polygon']);
            print PHP_EOL;
*/
            $is_in_polygon = is_in_polygon2( $c['polygon'], $l['polygon'][0] );
/*
            if ( true === $is_in_polygon ) {
                print "DENTRO" . PHP_EOL;
            }
*/
            // si lo tiene, archivamos el interno y el externo, guardando su relación
            if ( true === $is_in_polygon ) {
//                print "polygono interior" . PHP_EOL;
                // actualizamos el nivel (quizás no nos haga falta nunca)
                $l['level'] = 1;
                $c['level'] = 0;
                // comprobamos la orientación
                $orientacion = comprobarOrientacion( $l['polygon'], $l['leftCorner'] );
                // exterior rings: counter-clockwise
                // interior rings (holes): clockwise direction.
                // @url https://gis.stackexchange.com/questions/119150/order-of-polygon-vertices-in-general-gis-clockwise-or-counterclockwise
                // al ser interior, debería ser CW
                if ( true === $orientacion ) { // orientación es CCW, lo rotamos para dejarlo CW
                    $l['polygon'] = array_reverse( $l['polygon'] );
                }
                // no lo vamos a necesitar mas, así que lo podemos borrar
                unset($l['leftCorner']);
                // metemos el que estaba dentro en su sitio
                $c['inside'][] = $l;
                // borramos $l de lista contornos (referenciado por $k)
//                print "inside count:" . count($c['inside']) . PHP_EOL;
//                print "deleted $k" . PHP_EOL;
                unset($listaContornos[$k]);
                //break;
            }
        }
/*
        print "=======" . PHP_EOL;
*/
        // el contorno no tiene a nadie dentro, es un level 0
        if ( false === $is_in_polygon ) { // $c no tiene a nadie dentro
//            print "ponemos el que tiene count de: " . count($c['polygon']) . " como level 0" . PHP_EOL;
            $c['level'] = 0;
        }
        $listaContornos[] = $c;

        // si no existe ningún polígono de nivel -1, es que los hemos comprobado todos
        // en ese caso, salir.
        $salir = true;
        foreach( $listaContornos as $l ) {
            if ( $l['level'] == -1 ) {
                $salir = false;
                break;
            }
        }

    }
/*
        print"STATUS FINAL" . PHP_EOL;
        foreach( $listaContornos as $k => $l ) {
            print $k . "] " . count($l['polygon']) . PHP_EOL;
            print "\t level:" . $l['level'] . PHP_EOL;
            print "\t inside:" . count($l['inside']) . PHP_EOL;
        }
        print "============================================" . PHP_EOL;
*/
    return $listaContornos;
}

/**
 * Función que determina los contornos de cobertura que hay en una matriz
 *
 * @url http://paulbourke.net/papers/conrec/
 * @param array $malla (ENTRADA)
 * @return array $listaContornos (SALIDA)
 */
function determinaContornos2($malla){

    //if ( NULL === ($listaContornos = json_decode(@file_get_contents("turrillas.json"), true)) ) {
        $c = determinaContornos2_getContornos($malla);
        $listaContornos = determinaContornos2_joinContornos($c);
        //file_put_contents("turrillas.json", json_encode($listaContornos));
    //}

    $listaContornosCount = count($listaContornos);
    $listaContornos = determinaContornos2_sortContornos($listaContornos);

    $assertListaContornosCount = count($listaContornos);
    foreach( $listaContornos as $k => $l ) {
        // print $k . "] " . count($l['polygon']) . PHP_EOL;
        // print "\t level:" . $l['level'] . PHP_EOL;
        // print "\t inside:" . count($l['inside']) . PHP_EOL;
        $assertListaContornosCount += count($l['inside']);
    }

    print "[assert listaContornos: " . $listaContornosCount . "=?" . $assertListaContornosCount . "]";
    if ( $listaContornosCount != $assertListaContornosCount ) {
        die("ERROR al reindexar los contornos" . PHP_EOL);
    }

    return $listaContornos;
}

/*
 * Point Inclusion in Polygon Test
 *
 * @url https://wrf.ecse.rpi.edu//Research/Short_Notes/pnpoly.html
 * @param $vertices de la forma array((0,0), (0,1), (1,1), (1,0), (0,0)) <- cerrado!
 * @param $point de la forma array(x,y)
 * @return boolean true if inside polygon
 */
function is_in_polygon($v, $p) {
    $inside = false;
    for ($i = 0, $j = count($v) - 1; $i < count($v); $j = $i++) {
        if ( (($v[$i][1] > $p[1] != ($v[$j][1] > $p[1])) &&
            ($p[0] < ($v[$j][0] - $v[$i][0]) * ($p[1] - $v[$i][1]) / ($v[$j][1] - $v[$i][1]) + $v[$i][0]) ) ) {
            $inside = !$inside;
        }
    }
    return $inside;
}
// cambiamos fila por x = [0] y col por y = [1]
function is_in_polygon2($v, $p) {
    $inside = false;
    for ($i = 0, $j = count($v) - 1; $i < count($v); $j = $i++) {
        if ( (($v[$i]['col'] > $p['col'] != ($v[$j]['col'] > $p['col'])) &&
            ($p['fila'] < ($v[$j]['fila'] - $v[$i]['fila']) * ($p['col'] - $v[$i]['col']) / ($v[$j]['col'] - $v[$i]['col']) + $v[$i]['fila']) ) ) {
            $inside = !$inside;
        }
    }
    return $inside;
}

/**
 * Find point with lower x value, if equal, choose the point with lower y value
 *
 * @param int $x new x
 * @param int $y new y
 * @param array $leftCorner array with lower x & y until call
 * @param array $arr to get key count if new point is found
 * @param int $k index, if not set, get index from $arr count
 * @return $leftCorner
 */
function findLeftCorner( $x, $y, $leftCorner, $arr, $k = false ) {

    if ( ($x < $leftCorner['xMin']) || 
        (($x == $leftCorner['xMin']) &&
        ($y < $leftCorner['yMin'])) ) {
    
        $leftCorner['xMin'] = $x;
        $leftCorner['yMin'] = $y;
        if ( false === $k ) {
            $leftCorner['key'] = count($arr) - 1;
        } else {
            $leftCorner['key'] = $k;
        }
    }
    return $leftCorner;
}

/**
 * Calcula la orientación de un polígono, necesario para saber si lo giramos
 * exterior rings: counter-clockwise
 * interior rings (holes): clockwise direction
 *
 * @url https://gis.stackexchange.com/questions/119150/order-of-polygon-vertices-in-general-gis-clockwise-or-counterclockwise
 * @url https://en.wikipedia.org/wiki/Curve_orientation
 * @return bool true = CCW, false = CW
 */
function comprobarOrientacion($contornoFixed, $leftCorner) {
/* 
 One does not need to construct the convex hull of a polygon to find
 a suitable vertex. A common choice is the vertex of the polygon with
 the smallest X-coordinate. If there are several of them, the one with
 the smallest Y-coordinate is picked. It is guaranteed to be the vertex
 of the convex hull of the polygon. Alternatively, the vertex with the
 smallest Y-coordinate among the ones with the largest X-coordinates or
 the vertex with the smallest X-coordinate among the ones with the
 largest Y-coordinates (or any other of 8 "smallest, largest" X/Y
 combinations) will do as well.

 If the orientation of a convex polygon is sought, then, of course, any
 vertex may be picked.

 For numerical reasons, the following equivalent formula for the
 determinant is commonly used:

    det ( O ) = ( x B − x A ) ( y C − y A ) − ( x C − x A ) ( y B − y A )
    
 If the determinant is negative, then the polygon is oriented clockwise.
 If the determinant is positive, the polygon is oriented counterclockwise.
 The determinant is non-zero if points A, B, and C are non-collinear.

*/ 

    $n = count($contornoFixed);
    if ( $n < 2 ) {
        die("ERROR un polígono debería estar formado por dos puntos!");
    }
    $k = $leftCorner['key'];

    // 0 1 2 3 4 5 6 7   8
    //               ^
    $xA = $contornoFixed[(($k-1) + $n) % $n]['fila']; $yA = $contornoFixed[(($k-1) + $n) % $n]['col'];
    $xB = $contornoFixed[$k]['fila']; $yB = $contornoFixed[$k]['col'];
    $xC = $contornoFixed[($k+1) % $n]['fila']; $yC = $contornoFixed[($k+1) % $n]['col'];
    $det = (( $xB - $xA )*( $yC - $yA )) - (( $xC - $xA )*( $yB - $yA ));

    if ( $det>0 )
        return true; // CCW
    else
        return false; // CW
}
