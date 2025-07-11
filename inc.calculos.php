<?php

CONST FRONTERA_LATITUD = 90; // latitud complementaria
CONST FEET_TO_METERS = 0.30480370641307;
CONST PASO_A_GRADOS = 180.0;
CONST TAM_CELDA = 0.20; //10; // 0.5; // paso de la malla en NM 0.5 , 0.11 es lo mas que pequeño q no desborda
CONST TAM_CELDA_MITAD = TAM_CELDA/2.0; // 5; // 0.25; // NM
CONST TAM_ANGULO_MAXIMO = TAM_CELDA*2.0; //20; // 1; // NM (lo situamos al doble que tamaño celda)

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

    $debug = false;
    $i = 0;
    $enc = false;

    while ( $i < (count($listaObstaculos)) && !$enc ) {
        if ( $flm < $listaObstaculos[$i]['altura'] ) { // la primera vez que se cumple esto tenemos el primer punto sin cobertura
            // siempre se va a dar el caso en el que el nivel de vuelo va a ser menor que la altura del obstáculo
            // porque llamamos a la función desde calculosFLencimaRadar
	    if ( 0 == $i ) {
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
	    if ( $debug ) {
		print "buscarPuntosLimitantes] i:" . $i . PHP_EOL;
	        print "buscarPuntosLimitantes] alturaPrimerPtoSinCob:" . $alturaPrimerPtoSinCob . PHP_EOL;
	        print "buscarPuntosLimitantes] alturaUltimoPtoConCob:" . $alturaUltimoPtoCob . PHP_EOL;
	        print "buscarPuntosLimitantes] anguloPrimerPtoSinCob:" . $anguloPrimerPtoSinCob . PHP_EOL;
	        print "buscarPuntosLimitantes] anguloUltimoPtoConCob:" . $anguloUltimoPtoCob . PHP_EOL;
	        print "buscarPuntosLimitantes] flm:" . $flm . PHP_EOL;
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

    $debug = false;
    $earthToRadar = $radar['screening']['towerHeight'] +
        $radar['screening']['terrainHeight'] +
        $radar['screening']['radioTerrestreAumentado'];
    $earthToFl = $radar['screening']['radioTerrestreAumentado'] + $flm;

    $anguloMaxCob = acos(
        (pow($earthToRadar,2) + pow($earthToFl,2) - pow($radar['range'],2))
        / (2 * $earthToRadar * $earthToFl)
    );
    if ( $debug ) {
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

    $debug = false;
    $distanciasAlcances = array();
    $radioTerrestreAumentado = $radar['screening']['radioTerrestreAumentado'];
    $anguloMaxCob = calculaAnguloMaximaCobertura($radar, $flm); // AlphaRange en Matlab
    $earthToFl = $radioTerrestreAumentado + $flm;
    $earthToRadar = $radar['screening']['towerHeight'] + $radar['screening']['terrainHeight'] + $radioTerrestreAumentado;
    $earthToRadarPow = pow($earthToRadar, 2);
    $distanciaMaxCobertura = $radioTerrestreAumentado * $anguloMaxCob / MILLA_NAUTICA_EN_METROS;

    // recorremos los azimuths
    for ( $i=0; $i < $radar['screening']['totalAzimuths']; $i++ ) {
        // obtenemos la última linea del array para cada azimut.
	if ( !isset($radar['screening']['listaAzimuths'][$i]) ) {
	    print_r($radar);
	    logger(" E> El azimut $i no existe");
	    exit(-1);
	}
	$count = count($radar['screening']['listaAzimuths'][$i]);
	$ultimoPunto = $count - 1;
	// al cargar el fichero de screening, como primer obstáculo siempre se inserta
	// el radar, para evitar casos en los que el primer obstáculo esté demasiado
	// lejos. Eso provoca que siempre haya un obstáculo. La comprobación de si el
	// fichero de screening tiene un problema en el acimut se debe hacer al cargar
	// y no aquí (en cargarDatosTerreno)

	// obtenemos la altura del último punto para cada azimuth
	$obstaculoLimitante = $radar['screening']['listaAzimuths'][$i][$ultimoPunto]['altura'];
        if ( $flm >= $obstaculoLimitante ) {
            // caso en el que el nivel de vuelo está por encima del obstáculo que limita

	    $earthToEvalPoint = $radioTerrestreAumentado + $obstaculoLimitante;
	    $earthToEvalPointPow = pow($earthToEvalPoint, 2);
	    // ángulo del ultimo obstaculo de cada azimuth
	    $angulo = $radar['screening']['listaAzimuths'][$i][$ultimoPunto]['angulo'];
            // distancia que corresponde a ese ángulo
            $distancia = sqrt ( ($earthToRadarPow + $earthToEvalPointPow) - 2 * $earthToRadar * $earthToEvalPoint * cos($angulo));
	    if ( 0  == $distancia ) {
		// si el ángulo es 0, la distancia es 0, y el ángulo entre la vertical del radar y el obstáculo
		// más alto dará una visión por cero. No hay solución posible, daremos error.
		logger(" E> Para el azimut ($i) hay dos puntos con distancia angular muy pequeña ($distancia) con ángulo ($angulo). Se fuerza a 1m.");
		//$distancia = 0.00000000000001;
		// $distanciasAlcances[$i] = 0;
		//continue;
		exit(-1);
	    }
            // ángulo formado entre la vertical del radar (hacia el centro de la tierra) y el obstáculo más alto
	    $gammaMax = acos(
	        (pow($distancia,2) +
	        $earthToRadarPow -
	        $earthToEvalPointPow) /
	        (2 * $earthToRadar * $distancia)
	    );

	    $theta = asin($earthToRadar * sin($gammaMax) / $earthToFl);
	    // ángulo formado entre la vertical del radar y el punto de
	    // corte de la recta que pasa por el obstáculo más alto y el nivel
	    // de vuelo.
	    $epsilon = M_PI - $theta - $gammaMax;
            // escogemos el ángulo menor entre el ángulo para la máxima
            // cobertura del radar al nivel de vuelo estudiado y el ángulo
            // epsilon. (puede ser que por nivel de vuelo no lleguemos al obstáculo,
            // entonces la cobertura es menor)
            if ($epsilon >  $anguloMaxCob) {
		$distanciasAlcances[$i] = $distanciaMaxCobertura;
		// $distanciasAlcances[$i] = $radioTerrestreAumentado * $anguloMaxCob / MILLA_NAUTICA_EN_METROS;
	    } else {
                $distanciasAlcances[$i] = $radioTerrestreAumentado * $epsilon / MILLA_NAUTICA_EN_METROS;
            }
	    // print ($i/2) . "\tA1 distancia: " . $distanciasAlcances[$i] . "NM" . PHP_EOL;
	    if ( $debug /* && $i == 180 */ ) {
		print "flm >= obstaculoLimitante " . PHP_EOL;
		print "  radioTerrestreAumentado: " . $radioTerrestreAumentado . PHP_EOL;
		print "  count: " . $count . PHP_EOL;
		print "  obstaculoLimitante: " . $obstaculoLimitante . PHP_EOL;
		print "  earthToEvalPoint: " . $earthToEvalPoint . PHP_EOL;
		print "  angulo:" . $angulo . PHP_EOL;
		print "  distancia: " . $distancia . PHP_EOL;
		print "  gammaMax: " . $gammaMax . PHP_EOL;
		print "  theta: " . $theta . PHP_EOL;
		print "  epsilon: " . $epsilon . PHP_EOL;
		print "  anguloMaxCob (AlphaRange): " . $anguloMaxCob . PHP_EOL;
		print "  distanciasAlcances[" . $i . "]: " . $distanciasAlcances[$i] . PHP_EOL;
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
	    if ( false === $ret ) {
	        die("ERROR MALIGNO !! No deberias haber entrado aqui" . PHP_EOL);
	    }
	    // A2.3 (con objeto de paliar una posible excesiva separación entre puntos consecutivos,
	    // se procede a calcular la intersección entre el nivel de vuelo y la recta que une los dos
	    // puntos consecutivos límite, es decir el último punto con cobertura y el primero sin ella.
	    // ¿es una interpolación? ¿por qué?
	    $anguloLimitante = (($flm-$alturaUltimoPtoCob) * (($anguloPrimerPtoSinCob - $anguloUltimoPtoCob) / ($alturaPrimerPtoSinCob - $alturaUltimoPtoCob))) + $anguloUltimoPtoCob;

	    if ($anguloLimitante > $anguloMaxCob) {
		// este valor se puede precalcular siempre será el mismo
		$distanciasAlcances[$i] = $distanciaMaxCobertura;
		// $distanciasAlcances[$i] = $radioTerrestreAumentado * $anguloMaxCob / MILLA_NAUTICA_EN_METROS;
	    } else {
                $distanciasAlcances[$i] = $radioTerrestreAumentado * $anguloLimitante / MILLA_NAUTICA_EN_METROS;
	    }

	    // print ($i) . "\tA2 distancia: " . $distanciasAlcances[$i] . "NM" . PHP_EOL;

	    if ( $debug /* && $i == 180 */ ) {
		print "flm < obstaculoLimitante " . PHP_EOL;
		print "  radioTerrestreAumentado: " . $radioTerrestreAumentado . PHP_EOL;
		print "  count: " . $count . PHP_EOL;
		print "  obstaculoLimitante: " . $obstaculoLimitante . PHP_EOL;
		print "  anguloMaxCob (AlphaRange): " . $anguloMaxCob . PHP_EOL;
		print "  distanciasAlcances[" . $i . "]: " . $distanciasAlcances[$i] . PHP_EOL;
		print "  alturaPrimerPtoSinCob: " . $alturaPrimerPtoSinCob . PHP_EOL;
		print "  anguloPrimerPtoSinCob: " . $anguloPrimerPtoSinCob . PHP_EOL;
		print "  alturaUltimoPtoCob: " . $alturaUltimoPtoCob . PHP_EOL;
		print "  anguloUltimoPtoCob: " . $anguloUltimoPtoCob . PHP_EOL;
		print "  anguloLimitante: " . $anguloLimitante . PHP_EOL;
	    }
	 }// else
    }// fin for para recorrer los azimuths

    if ( $debug )
	foreach($distanciasAlcances as $i => $nm)
	    print "acimut: " . ($i) . "\t distancia: " . round($nm,2) . PHP_EOL;

    return $distanciasAlcances;

}

/**
 * Funcion que calcula las coordenadas geograficas de cobertura de un fichero kml para un determinado
 * radar a partir de las coordenadas, el nivel de vuelo y el array de distancias de cobertura.
 * La diferencia con calculaCoordenadasGeograficasB es que la entrada es distinta. En este caso
 * (A) recorremos el punto más alejado de los azimuts para encontrar la cobertura, en caso (B)
 * hay que recorrer una lista de contornos que pueden tener contornos dentro.
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

        $res = transformaFromPolarToLatLon($radar, $rho = $distanciasAlcances[$i], $theta = $i * $paso, $latComp);

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
            'alt' => $flm,
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
function transformaFromPolarToLatLon($radar, $rho, $theta, $latComp) {

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
 * @param int   $azimut (ENTRADA) Solo se usa a efecto de imprimir en el debug el ángulo que se está procesando
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
function calculador($azimut, $radar, $listaObstaculos, $flm, $obstaculoLimitante, &$gammaMax, &$theta0, &$earthToRadar, &$earthToEvalPoint, &$earthToFl, &$radarSupTierra){
    $debug = false;

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
    if ( $n < 2 ) {
	logger("E > No puede haber menos de dos puntos por obstáculo (el radar en origen y un límite). Abortando");
	exit(-1);
    }

    $distanciaUltimoPto = $radar['screening']['radioTerrestreAumentado'] * $listaObstaculos[$n-1]['angulo'];

    // Línea de vista del último punto del terreno
    $distanciaCobertura = sqrt(
        pow($earthToRadar,2) +
        pow($earthToEvalPoint,2) -
        2 * $earthToRadar * $earthToEvalPoint * cos($listaObstaculos[$n-1]['angulo'])
    );

    // NO ESTA BIEN:  Si solo hay dos puntos, el primero es el radar, así que la distancia va a ser 0.
    // Forzamos a coger el segundo
    if ( $distanciaCobertura ==  0) {
	logger(" E> Para el azimut ($azimut) hay dos puntos con distancia angular muy pequeña ($distanciaCobertura) para obstaculoLimitante ($obstaculoLimitante) con ángulo ({$listaObstaculos[$n-1]['angulo']}). Se fuerza a 1m.");
	// $distanciaCobertura = 0.0000001;
	exit(-1);
    }

    // Angulo en radianes que forma el radar con el último punto del terreno
    $gammaMax = acos((pow($distanciaCobertura,2) +  pow($earthToRadar,2) - pow($earthToEvalPoint,2)) / (2 * $earthToRadar * $distanciaCobertura));

    // sin($theta1) donde $theta1 es el ángulo entre la línea de vista del
    // último punto de cada azimuth y la proyección sobre el terreno del punto
    // de corte con el nivel de vuelo(el más alejado)
    $theta0 = $earthToRadar * sin($gammaMax) / $earthToFl;

    if ( $debug ) {
	print "Ángulo" . ":" . $azimut . PHP_EOL;
	print "Cuenta de obstáculos: $n" . PHP_EOL;
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
	print "Theta0" . ":" . $theta0 . PHP_EOL;
	print "Theta1" . ":" . asin($theta0) . PHP_EOL;
	// print_r($listaObstaculos);
	// exit(1);
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
    $debug = false;
    $anguloMaxCob = calculaAnguloMaximaCobertura($radar, $flm);
    // Ángulo (en radianes) entre el último pto de cada acimut y el punto
    // extra para una distancia de 0.1 NM. Es una pequeña distancia que se
    // le suma al ángulo de cada punto [0.1 NM]
    // para añadir un ptoExtra y poder aproximar el mallado
    $anguloMinimo = (0.1 *  MILLA_NAUTICA_EN_METROS ) / $radar['screening']['radioTerrestreAumentado'];
    $anguloMaximo = (TAM_ANGULO_MAXIMO * MILLA_NAUTICA_EN_METROS) / $radar['screening']['radioTerrestreAumentado'];
    $ptosNuevos = array();
    $ptoExtra = array( 'angulo' => 0, 'altura' => 0, 'estePtoTieneCobertura' => false);
    $ptoMaxCob = array('angulo'=> $anguloMaxCob, 'altura'=> 0, 'estePtoTieneCobertura'=> true);

    logger("[00%]", false);
    $countPct_old = 0;

    for ($i=0; $i < $radar['screening']['totalAzimuths']; $i++) {

        $countPct = $i*100.0 / $radar['screening']['totalAzimuths'];
        if ( ($countPct - $countPct_old) > 10 ) { logger("[" . round($countPct) . "%]", false); $countPct_old = $countPct; }

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

 	calculador( $i, $radar, $listaObstaculosAmpliada, $flm, $obstaculoLimitante, $gammaMax, $theta0, $earthToRadar, $earthToEvalPoint, $earthToFl, $radarSupTierra );

 	// CASO A: Último punto del acimut por debajo del nivel de vuelo y por debajo del radar
        $timerStart0 = microtime(true);
 	if( ( $obstaculoLimitante < $flm ) && ( $obstaculoLimitante < $radarSupTierra ) ) {
            if ( (abs($theta0)) <= 1 ) {
                obtenerPtosCorte( $earthToRadar, $gammaMax, $earthToFl, $radar['screening']['radioTerrestreAumentado'], $epsilon1, $epsilon2, $ptosCorte );
		$ptoUno = array( 'angulo' => $epsilon1, 'altura'=> 0, 'estePtoTieneCobertura'=> true );
		// A.1: se interpola desde el último punto del terreno hasta el punto 1
		if ( $epsilon1 < $anguloMaxCob ) {
		    if ( $debug ) print "A1";
	 	    $rangoLuz =  array( $ptoLimitante, $ptoUno );
	 	    // devuelve una lista con los puntos que se han interpolado
	 	    // $timerStart1 = microtime(true);
	 	    $ptosLuz = interpolarPtosTerreno($rangoLuz, $radar['screening']['radioTerrestreAumentado'], 3);
	 	    //printf("(a%3.4f)", microtime(true) - $timerStart1);
                    //print "loa: " . count($listaObstaculosAmpliada) . " pl: " . count($ptosLuz) . PHP_EOL;
                    // print_r($listaObstaculosAmpliada); print_r($ptosLuz);
	 	    //$timerStart1 = microtime(true);
	 	    // $listaObstaculosAmpliada = array_merge( $listaObstaculosAmpliada, $ptosLuz );
	 	    array_merge_fast( $listaObstaculosAmpliada, $ptosLuz );
	 	    //printf("(b" . "_${i}_" . "%3.4f)", microtime(true) - $timerStart1);
                    // if ($i >= 20) exit(0);

	 	    //$timerStart1 = microtime(true);

	 	    // $ptoExtra = array( 'angulo' => ($epsilon1 + $anguloMinimo), 'altura' => 0, 'estePtoTieneCobertura' => false );
	 	    // Solves Fatal error: Only variables can be passed by reference in /home/eval/berta/inc.calculos.ph
	 	    $ptoExtra = array(array( 'angulo' => ($epsilon1 + $anguloMinimo), 'altura' => 0, 'estePtoTieneCobertura' => false ));
	 	    //printf("(c" . "_${i}_" . "%3.4f)", microtime(true) - $timerStart1);

	 	    //$timerStart1 = microtime(true);
	 	    // $listaObstaculosAmpliada = array_merge( $listaObstaculosAmpliada, array($ptoExtra) );
	 	    array_merge_fast( $listaObstaculosAmpliada, $ptoExtra );
	 	    //printf("(d" . "_${i}_" . "%3.4f)", microtime(true) - $timerStart1);
	 	// A.2: se interpola desde el último punto del terreno hasta el punto de máxima cobertura
	 	} else {
	 	    if ( $debug ) print "A2";
	 	    $rangoLuz =  array ($ptoLimitante, $ptoMaxCob);
                    $ptosLuz = interpolarPtosTerreno($rangoLuz, $radar['screening']['radioTerrestreAumentado'], 3);
                    // $listaObstaculosAmpliada = array_merge( $listaObstaculosAmpliada, $ptosLuz );
                    array_merge_fast( $listaObstaculosAmpliada, $ptosLuz );
	 	    // $ptoExtra = array( 'angulo' => ($anguloMaxCob + $anguloMinimo), 'altura' => 0, 'estePtoTieneCobertura' => false );
	 	    $ptoExtra = array(array( 'angulo' => ($anguloMaxCob + $anguloMinimo), 'altura' => 0, 'estePtoTieneCobertura' => false ));
	 	    // $listaObstaculosAmpliada = array_merge( $listaObstaculosAmpliada, $ptoExtra );
	 	    array_merge_fast( $listaObstaculosAmpliada, $ptoExtra );
	 	}
            // A.3: El corte con el nivel de vuelo se traduce en angulos negativos
            } elseif ( abs($theta0) > 1 ) {
                if ( $debug ) print "A3";
	        // $ptoExtra = array( 'angulo' => ($anguloLimitante + $anguloMinimo), 'altura' => 0, 'estePtoTieneCobertura' => false );
	        $ptoExtra = array(array( 'angulo' => ($anguloLimitante + $anguloMinimo), 'altura' => 0, 'estePtoTieneCobertura' => false ));
	        // $listaObstaculosAmpliada = array_merge( $listaObstaculosAmpliada, array($ptoExtra) );
	        array_merge_fast( $listaObstaculosAmpliada, $ptoExtra );
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
            	        if ( $debug ) print "B1";
                        // En el caso en que la zona de sombra sea menor de una milla, añadimos un punto
                        // intermedio entre el último obstáculo y el epsilon2, sin cobertura, para que en
                        // la malla haya una discontinuidad.
                        if ($epsilon2 - $anguloLimitante <= $anguloMaximo){
                            $ptosSombra = array($ptoMedio, $ptoDos);
                        } else {
                            $rangoSombra = array( $ptoLimitante, $ptoDos );
                            $ptosSombra = interpolarPtosTerreno( $rangoSombra, $radar['screening']['radioTerrestreAumentado'], 2);
                        }
                        // $listaObstaculosAmpliada = array_merge( $listaObstaculosAmpliada, $ptosSombra );
                        array_merge_fast( $listaObstaculosAmpliada, $ptosSombra );
                        // B.1.2: Se interpola desde el punto 2 al punto 1 (rango LUZ)
                        $rangoLuz =  array( $ptoDos, $ptoUno );
                        $ptosLuz = interpolarPtosTerreno( $rangoLuz, $radar['screening']['radioTerrestreAumentado'], 3 );
                        // $listaObstaculosAmpliada = array_merge( $listaObstaculosAmpliada, $ptosLuz );
                        array_merge_fast( $listaObstaculosAmpliada, $ptosLuz );
                        // $ptoExtra = array('angulo' => ($epsilon1 + $anguloMinimo), 'altura' => 0, 'estePtoTieneCobertura' => false );
                        $ptoExtra = array(array('angulo' => ($epsilon1 + $anguloMinimo), 'altura' => 0, 'estePtoTieneCobertura' => false ));
                        // $listaObstaculosAmpliada= array_merge( $listaObstaculosAmpliada, array($ptoExtra) );
                        array_merge_fast( $listaObstaculosAmpliada, $ptoExtra );
                    }  elseif ( ($epsilon1 <= $anguloLimitante) && ($epsilon2 <= $anguloLimitante) ) {
                        if ( $debug ) print "B13";
                        // B.1.3: Los dos puntos están entre el radar y el obstáculo limitante.
                        // para acabar la lista de obstaculos con un punto sin cobertura
                        // $ptoExtra = array('angulo' => ($anguloLimitante + $anguloMinimo), 'altura' => 0, 'estePtoTieneCobertura' => false );
                        $ptoExtra = array(array('angulo' => ($anguloLimitante + $anguloMinimo), 'altura' => 0, 'estePtoTieneCobertura' => false ));
                        // $listaObstaculosAmpliada = array_merge( $listaObstaculosAmpliada, array($ptoExtra) );
                        array_merge_fast( $listaObstaculosAmpliada, $ptoExtra );
                    }
	 	// if B.1
	 	// B.2: se interpola desde el último punto del terreno hasta el punto de máxima cobertura pasando por el punto 2
	 	} elseif ( ($epsilon1 > $anguloMaxCob) && ($epsilon2 < $anguloMaxCob) ) { // B.2
                    // B.2.1: se interpola desde el último punto del terreno hasta el punto 2 (rango SOMBRA)
                    // En el caso en que la zona de sombra sea menor de una milla, añadimos un punto
                    // intermedio entre el último obstáculo y el epsilon2, sin cobertura, para que en
                    // la malla haya una discontinuidad.
                    if ( ($epsilon2 - $anguloLimitante) <= $anguloMaximo ){
                        if ( $debug ) print "B21";
                        $ptosSombra = array($ptoMedio, $ptoDos);
                    } else {
                        if ( $debug ) print "B22";
                        $rangoSombra = array ($ptoLimitante, $ptoDos);
                        $ptosSombra = interpolarPtosTerreno($rangoSombra, $radar['screening']['radioTerrestreAumentado'], 2);
                    }
                    $listaObstaculosAmpliada = array_merge($listaObstaculosAmpliada, $ptosSombra);
                    // B.2.2: Se interpola desde el punto 2 hasta el punto de máxima cobertura (rango LUZ)
                    $rangoLuz =  array ($ptoDos, $ptoMaxCob);
                    $ptosLuz = interpolarPtosTerreno($rangoLuz, $radar['screening']['radioTerrestreAumentado'], 3);
                    // $listaObstaculosAmpliada = array_merge($listaObstaculosAmpliada, $ptosLuz);
                    array_merge_fast($listaObstaculosAmpliada, $ptosLuz);
                    // $ptoExtra = array ('angulo' => ($anguloMaxCob + $anguloMinimo), 'altura' => 0, 'estePtoTieneCobertura' => false);
                    $ptoExtra = array(array ('angulo' => ($anguloMaxCob + $anguloMinimo), 'altura' => 0, 'estePtoTieneCobertura' => false));
                    // $listaObstaculosAmpliada = array_merge($listaObstaculosAmpliada, array($ptoExtra));
                    array_merge_fast( $listaObstaculosAmpliada, $ptoExtra );
	 	// fin caso B.2
	 	// B.3: Los cortes con el nivel de vuelo están más allá del punto de máxima cobertura
	 	} elseif ( (($epsilon1 > $anguloMaxCob) && ($epsilon2 > $anguloMaxCob)) ) { // caseo B.3
	 	    if ( $debug ) print "B3";
                    $ptoExtra = array( 'angulo' => ($anguloLimitante + $anguloMinimo), 'altura' => 0, 'estePtoTieneCobertura' => false );
                    $ptoExtra = array( $ptoExtra );
                    array_merge_fast($listaObstaculosAmpliada, $ptoExtra);
	 	}
	    // B.4: Los cortes con el nivel de vuelo se traducen en angulos negativos
	    } elseif ( abs($theta0) > 1.0 ) {
	        if ( $debug ) print "B4";
	 	$ptoExtra = array( 'angulo' => ($anguloLimitante + $anguloMinimo), 'altura' => 0, 'estePtoTieneCobertura' => false );
	 	$ptoExtra = array( $ptoExtra );
	 	array_merge_fast($listaObstaculosAmpliada, $ptoExtra);
	    } // Fin CASO B
            // CASO C: Último punto del acimut por encima del nivel de vuelo y por encima del radar
        } elseif ( ($obstaculoLimitante > $flm) && ($radarSupTierra < $obstaculoLimitante) ) {
            if ( $debug ) print "C";
            $ptoExtra = array( 'angulo' => ($anguloLimitante + $anguloMinimo), 'altura' => 0, 'estePtoTieneCobertura' => false );
            $ptoExtra = array( $ptoExtra );
            array_merge_fast( $listaObstaculosAmpliada, $ptoExtra );
	}

        if ( $debug ) printf("[%3.4f]", microtime(true) - $timerStart0);

        // safety check
        if ( !isset($listaObstaculosAmpliada) || !is_array($listaObstaculosAmpliada) ) {
            logger(" E> buscaDistanciaMenor: $$listaObstaculos debería ser un array"); exit(-1);
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
	if ( $debug) print PHP_EOL;
    } // for
    logger("[100%]" . PHP_EOL, false);
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

    if ($x < 0) {
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
 * TODO: $mallaLatLon NO SE USA, se podría borrar
 *
 * @param array $radar (ENTRADA)
 * @param float $flm (ENTRADA)
 * @param array $distanciasAlcances (ENTRADA)
 * @return array compuesto de $malla y $mallalatlon (SALIDA)
 */
function generacionMalladoLatLon($radar, $flm, $distanciasAlcances) {

    // pasamos a millas nauticas el rango del radar que esta almacenado en metros en la estructura radar
    $tamMalla = (( 2 * $radar['range'] ) / TAM_CELDA) / MILLA_NAUTICA_EN_METROS;
    $radioTerrestreAumentadoEnMillas  = $radar['screening']['radioTerrestreAumentado'] / MILLA_NAUTICA_EN_METROS;
    $alturaCentroFasesAntena = $radar['screening']['towerHeight'] + $radar['screening']['terrainHeight'];

    $malla = array(); // creamos una malla vacia
    $mallaLatLon = array(); // malla indexadaa por lat/lon
    $azimutTeorico = 0; // azimut teorico calculado
    $azimutCelda = 0; // azimut aproximado
    $pos = 0;

    // CENTRAMOS LA MALLA Y CALCULAMOS EL PTO MEDIO DE CADA CELDA
    $tamMallaMitad = $tamMalla / 2.0;
    logger(" D> tamMallaMitadLatLon: {$tamMallaMitad}");

    // CALCULAMOS LAS COORDENADAS X DE CADA CELDA (sacamos la parte común del cálculo fuera del bucle)
    $x_fixed = -( $tamMallaMitad * TAM_CELDA ); // + ( TAM_CELDA_MITAD ); // ($i * TAM_CELDA)
    // Factor de corrección según el número de azimut total que haya en el fichero de screening.
    // Como los ángulos son siempre 360, si en el fichero de screening se define otro número,
    // tendremos que ajustar el ángulo que calculamos para adecuarlo al número de azimut guardado
    // según screening. Es decir, si hay 720 azimut, y nos sale un ángulo de 360, realmente será de 720.
    $ajusteAzimut = $radar['screening']['totalAzimuths'] / 360.0;
    // microptimización
    $listaAzimuts = $radar['screening']['listaAzimuths']; // para acelerar

    logger(" D> Tamaño mallaLatLon: {$tamMalla}");
    print "[00%]";
    $countPct_old = 0;
    $latitudComplementaria = deg2rad(FRONTERA_LATITUD - $radar['lat']);
    $latComp = array(
        'cos' => cos($latitudComplementaria),
        'sin' => sin($latitudComplementaria),
    );
    //$timer0 = microtime(true);
    // la malla tiene tamMalla + 1, para que el centro siempre quede en una celda
    for ($i = 0; $i <= $tamMalla; $i++){ // recorre las columnas de la malla
        //$timer1 = microtime(true);
        //print "[$i]";
	$countPct = $i*100.0 / $tamMalla;
	if ( ($countPct - $countPct_old) > 10 ) { print "[" . round($countPct) . "%]"; $countPct_old = $countPct; }

        // CALCULAMOS LAS COORDENADAS X DE CADA CELDA
        $x = $x_fixed + ($i * TAM_CELDA);
        $powX = $x*$x;
        // CALCULAMOS LAS COORDENADAS X DE CADA CELDA (sacamos la parte común del cálculo fuera del bucle)
        $y_fixed = ( $tamMallaMitad * TAM_CELDA ); // - ( TAM_CELDA_MITAD );//  #- ( $j * TAM_CELDA ) 

        // la malla tiene tamMalla + 1, para que el centro siempre quede en una celda
        for ($j = 0; $j <= $tamMalla; $j++){ // recorre las filas de la malla
            // CALCULAMOS LAS COORDENADAS Y DE CADA CELDA
            $y = $y_fixed - ($j * TAM_CELDA);
            // CALCULAMOS EL AZIMUT DE CADA CELDA Y APROXIMAMOS
            //$timer2 = microtime(true);
            $azimutTeorico = calculaAcimut($x, $y);
            //printf("[timer azimutTeorico: %3.5f]", microtime(true) - $timer2);

	    $azimutCelda = round( $azimutTeorico * $ajusteAzimut, $precision = 0, $mode = PHP_ROUND_HALF_UP);
            // Un último paso, por si acaso al redondear nos salimos de la lista de azimut, ajustamos al máximo.
            // La lista va de 0 a 359 (o de 0 a 719)...
	    if ( $azimutCelda == $radar['screening']['totalAzimuths'] ) {
	        $azimutCelda--;
	    }

            // al dividir entre el radio tenemos el angulo deseado
            //$timer2 = microtime(true);
	    $distanciaCeldaAradarXY = sqrt($powX+$y*$y);
            $distanciaCeldaAradarAngulo = ( sqrt($powX+$y*$y) ) / $radioTerrestreAumentadoEnMillas;
            //printf("[timer distancia: %3.6f]", microtime(true) - $timer2);

	    //$timer2 = microtime(true);
	    $puntoLatLon = transformaFromPolarToLatLon(
	        $radar,
	        $distanciaCeldaAradarXY,
	        $azimutTeorico,
	        $latComp
	    );
	    //printf("[timer transforma: %3.6f]", microtime(true) - $timer2);
	    //print_r($puntoLatLon);
            if ( $flm >= $alturaCentroFasesAntena ) {
	        if ( ($distanciaCeldaAradarXY > $distanciasAlcances[$azimutCelda]) ) {
		    $malla[$j][$i] = 0;
	        } else {
		    $malla[$j][$i] = 1;
                }
            } else {
                //$timer2 = microtime(true);
	        // busca la posicion de la  distancia mas proxima en la lista de obstaculos del acimut aproximado (el menor)
	        $pos = findNearestValue(
	            $distanciaCeldaAradarAngulo,
	            $listaAzimuts[$azimutCelda],
	            0,
	            count($listaAzimuts[$azimutCelda]) - 1,
	            $key = "angulo"
	        );
	        //printf("[timer find1: %3.6f]", microtime(true) - $timer2);

	        if ( ($radar['screening']['listaAzimuths'][$azimutCelda][$pos]['estePtoTieneCobertura']) === false ) {
	            $malla[$j][$i] = 0;
	        } else {
		    $malla[$j][$i] = 1;
	        }
	    }
	    // antes, independientemente de si había o no cobertura, el punto en lat lon era el mismo
            //$malla[$j][$i][1] = $puntoLatLon['lat'];
            //$malla[$j][$i][2] = $puntoLatLon['lon'];
            //print PHP_EOL;
	}
	// print PHP_EOL . PHP_EOL;
	//printf("[timer1: %3.6f]", microtime(true) - $timer1);
    }
    print "[100%]" . PHP_EOL;
    //printf("[timer0: %3.3f]", microtime(true) - $timer0);

    logger(" D> sortingMallaLatLon");
    ksort($mallaLatLon);
    foreach($mallaLatLon as $lat => &$lons) {
        ksort($lons);
    }
    return array('malla' => $malla);
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
 * Funcion que calcula las coordenadas geograficas para el caso C (malla global en lat/lon)
 *
 * @param int $flm (ENTRADA)
 * @param array $listaC (ENTRADA), estructura que asocia la fila con la long, la col con la latitud y que ademas almacena la altura
 * @return array filas asociadas con la longitud y columnas con latitud 
 */
function calculaCoordenadasGeograficasC( $flm, $listaContornos ) {
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
    foreach( $listaContornos as &$contorno ) {
        foreach ( $contorno['polygon'] as &$p ) {
            // transforma las coordenadas del level 0
            $p['alt'] = $flm;
            $p['lat'] = $p['fila'] / REDONDEO_LATLON;
            $p['lon'] = $p['col'] / REDONDEO_LATLON;
            unset($p['fila']); unset($p['col']);
            //$p = transformaCoordenadas($radar, $flm, $tamMallaMitad, $p, $latComp);
        }
        foreach ( $contorno['inside'] as $k1 => &$contorno_inside ) {
            foreach ($contorno_inside['polygon'] as $k2 => &$p_inside) {
                // transforma las coordenadas del level 1
                $p_inside['alt'] = $flm;
                $p_inside['lat'] = $p_inside['fila'] / REDONDEO_LATLON;
                $p_inside['lon'] = $p_inside['col'] / REDONDEO_LATLON;
                unset($p_inside['fila']); unset($p_inside['col']);
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
    // $x = (($p['col'] - 1) * TAM_CELDA) - ($tamMallaMitad * TAM_CELDA);
    // $y = ($tamMallaMitad * TAM_CELDA) - (($p['fila'] - 1) * TAM_CELDA);
    $x = (($p['col']) * TAM_CELDA) - ($tamMallaMitad * TAM_CELDA);
    $y = ($tamMallaMitad * TAM_CELDA) - (($p['fila']) * TAM_CELDA);

    // CALCULO DE LA DISTANCIA
    // $distanciaCeldaAradar = (sqrt(pow(($xR- $x),2)+ pow(($yR - $y),2)) );
    $distanciaCeldaAradar = sqrt(pow($x,2) + pow($y,2));
    // CALCULO DEL ACIMUT
    $azimutTeorico = calculaAcimut($x, $y);

    $res = transformaFromPolarToLatLon($radar, $distanciaCeldaAradar, $azimutTeorico, $latComp);

    $res['alt'] = $flm;
    return $res;
}

/**
 * Helper de determinaContornos2. Wrapper para llamar a CONREC_contour
 * generamos los arrays x, y, d para la llamada
 * @param array $malla (ENTRADA)
 * @return array lista de segmentos de contornos
 */
function determinaContornos2_getContornos($malla) {

    $d = array();
    $x = array();
    $y = array();
    $empty = 0;
    // inicializamos los arrays de coordenadas necesarios para CONREC_contour
    /*
    // nuestra malla siempre es cuadrada. CONREC necesita los índices de x y de y.
    // al ser cuadrada, atajamos.
    // DEPRECADO
    // for( $i=0; $i < count($malla); $i++ ) {
    //     $x[$i] = $i; $y[$i] = $i;
    // }
    // ya no es cuadrada, cuando hacemos mallas globales pueden salir rectangulares
    // así que hay que guardar los índices para acceder a la malla en x e y
    $k = 0;
    foreach($malla as $i => $row) {
        $x[$k++] = $i;
    }
    $k = 0;
    foreach($malla[$i] as $j => $value) {
        $y[$k++] = $j;
    }

    $iMalla = count($malla);
    for( $i = 0; $i < $iMalla; $i++ ) {
        $d[$i] = array();
        $jMalla = count($malla[$i]);
        for( $j = 0; $j < $jMalla; $j++ ) {
            // cambiamos los valores de x y de y por los valores que CONREC espera.
            $val = $malla[($iMalla-1) - $j][$i];
            // cálculo para saber si la malla está toda a 0, y
            // por lo tanto no habrá cobertura
            $empty += $val;
            $d[$i][$j] = $val;
        }
    }
    */

    $i = 0;
    foreach( $malla as $lat => $lons ) {
        $d[$i] = array();
        $x[$i] = $lat;
        $j = 0;
        foreach( $lons as $lon => $value ) {
            $y[$j] = $lon;
            $d[$i][$j] = $value;
            $empty += $value;
            $j++;
        }
        $i++;
    }

    if ( 0 == $empty ) {
        // sanity check. si no hay ningún 1 en toda la malla,
        // abortar porque significa que la malla está vacía.
        return array();
    }

    // se llama a CONREC pidiendo 4 contornos. Si pidiésemos uno, se calcularía al 50% entre
    // la celda con valor a 1 y la celda con valor a 0, es decir, entre se interpola entre medias.
    // esto da problemas en los cruces cuando se llama a _joinContornos, porque se pueden
    // cruzar los contornos.
    // Al pedir 2, se hacen dos contornos, uno al 33% y otro al 66%.
    // Al pedir 4, se generan 0.2, 0.4, 0.6, 0.8.
    // La idea es coger el de 0.6 para solapar poquito, y no dejar un hueco grande entre
    // dos coberturas que deberían estar juntas (una doble pegada a una mono).

    $contornos = CONREC_contour($d, $x, $y, $numContornos = array(0.33)); // era 0.33
    // contornos tiene un value y un segment
    //print_r($contornos);
    print "[contornos: " . count($contornos) . " => (";
    foreach($contornos as $c) {
        print " value:" . round($c['value'],2) . " segment_count:" . count($c['segments']);
    }
    print " )]";

    // Si nos quedamos con el contorno 0, nos estamos quedando con el 0.2 (de 4) o con el 0.33 (de 2).
    // Eso implica que solapa con el contorno vecino, porque te metes en el terreno del vecino.
    // Lo mejor es lo más próximo al 0.5, sin ser 0.5 y solapando (quedandose por debajo) cuando hay
    // multiradar. En monoradar, cogemos 0.66, que no junta los huecos de cobertura.

    $c = $contornos[0];
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

    // insertamos en varias hash lists los segmentos, el original y el invertido
    $nDirNor = $nInvNor = $nDirOver1 = $nInvOver1 = array();
    $nDirOver2 = $nInvOver2 = $nDirOver3 = $nInvOver3 = array();
    foreach($c['segments'] as $sgm) {
        $vertex1 = $sgm['x1'].";".$sgm['y1'];
        $vertex2 = $sgm['x2'].";".$sgm['y2'];
        if ( !isset($nDirNor[$vertex1]) ) {
            $nDirNor[$vertex1] = $vertex2;
        } elseif ( !isset($nDirOver1[$vertex1]) ) {
            $nDirOver1[$vertex1] = $vertex2;
        } elseif ( !isset($nDirOver2[$vertex1]) ) {
            $nDirOver2[$vertex1] = $vertex2;
        } elseif ( !isset($nDirOver3[$vertex1]) ) {
            $nDirOver3[$vertex1] = $vertex2;
        } else {
            print("assert $vertex1 => $vertex2 exists in nDirOver1,2,3" . PHP_EOL); exit(-1);
        }
        if ( !isset($nInvNor[$vertex2]) ) {
            $nInvNor[$vertex2] = $vertex1;
        } elseif ( !isset($nInvOver1[$vertex2]) ) {
            $nInvOver1[$vertex2] = $vertex1;
        } elseif ( !isset($nInvOver2[$vertex2]) ) {
            $nInvOver2[$vertex2] = $vertex1;
        } elseif ( !isset($nInvOver3[$vertex2]) ) {
            $nInvOver3[$vertex2] = $vertex1;
        } else {
            print("assert $vertex2 => $vertex1 exists in nInvOver1,2,3" . PHP_EOL); exit(-1);
        }
    }

    // lista completa de contornos
    $nListaContornos = array();
    // polígono actual
    $nFixed = array();
    // cogemos el primer segmento de la lista de normales
    // print_r($nDirNor);exit(0);
    // list($vertex1, $vertex2) = each($nDirNor); array_shift($nDirNor); // each is deprecated
    $vertex1 = key($nDirNor); $vertex2 = current($nDirNor); array_shift($nDirNor);

    // print PHP_EOL . $vertex1 . "=>" . $vertex2 . PHP_EOL;
    list($x1, $y1) = explode(";", $vertex1); list($x2, $y2) = explode(";", $vertex2);
    // lo insertamos en la lista de definitivos, buscando leftCorner de lo que será el polígono
    $nFixed[] = array( 'fila'=>$x1, 'col'=>$y1 );
    $leftCorner = array( 'xMin' => $x1, 'yMin' => $y1, 'key' => 0 );
    $nFixed[] = array( 'fila'=>$x2, 'col'=>$y2 );
    $leftCorner = findLeftCorner( $x2, $y2, $leftCorner, $nFixed );
    // borramos el inverso del segmento que acabamos de coger (tiene que ser igual key y value!)
    $assert = 0;
    if ( isset($nInvNor[$vertex2]) && ($nInvNor[$vertex2]==$vertex1) ) { unset($nInvNor[$vertex2]); $assert++; }
    if ( isset($nInvOver1[$vertex2]) && ($nInvOver1[$vertex2]==$vertex1) ) { unset($nInvOver1[$vertex2]); $assert++; }
    if ( isset($nInvOver2[$vertex2]) && ($nInvOver2[$vertex2]==$vertex1) ) { unset($nInvOver2[$vertex2]); $assert++; }
    if ( isset($nInvOver3[$vertex2]) && ($nInvOver3[$vertex2]==$vertex1) ) { unset($nInvOver3[$vertex2]); $assert++; }
    if ( $assert != 1 ) die("assert($assert != 1) in unset 1st try" . PHP_EOL);

    // unos contadores
    $countPct_old = 0; $cuentaActual_old = -1;
    $cuentaTotal = count($nDirNor)+count($nDirOver1)+count($nInvNor)+count($nInvOver1);
    print "[nSegmentos: $cuentaTotal][00%]";



    // ejecutar mientras tenga elementos en las listas
    while ( (count($nDirNor)+
            count($nInvNor)+
            count($nDirOver1)+
            count($nInvOver1)+
            count($nDirOver2)+
            count($nInvOver2)+
            count($nDirOver3)+
            count($nInvOver3)
            ) > 0 ) {
        $cuentaActual = count($nDirNor) + count($nDirOver1) + count($nDirOver2) + count($nDirOver3);
        // buscamos el siguiente segmento, solo estará en uno de los cuatro
        $found = false;
        $vertex1 = $vertex2;
        if     ( isset($nDirNor[$vertex1]) ) { $vertex2 = $nDirNor[$vertex1]; unset($nDirNor[$vertex1]); $found = true; }
        elseif ( isset($nDirOver1[$vertex1]) ) { $vertex2 = $nDirOver1[$vertex1]; unset($nDirOver1[$vertex1]); $found = true; }
        elseif ( isset($nInvNor[$vertex1]) ) { $vertex2 = $nInvNor[$vertex1]; unset($nInvNor[$vertex1]); $found = true; }
        elseif ( isset($nInvOver1[$vertex1]) ) { $vertex2 = $nInvOver1[$vertex1]; unset($nInvOver1[$vertex1]); $found = true; }
        elseif ( isset($nDirOver2[$vertex1]) ) { $vertex2 = $nDirOver2[$vertex1]; unset($nDirOver2[$vertex1]); $found = true; }
        elseif ( isset($nInvOver2[$vertex1]) ) { $vertex2 = $nInvOver2[$vertex1]; unset($nInvOver2[$vertex1]); $found = true; }
        elseif ( isset($nDirOver3[$vertex1]) ) { $vertex2 = $nDirOver3[$vertex1]; unset($nDirOver3[$vertex1]); $found = true; }
        elseif ( isset($nInvOver3[$vertex1]) ) { $vertex2 = $nInvOver3[$vertex1]; unset($nInvOver3[$vertex1]); $found = true; }

        if ( $found ) {
            // tenemos que borrar el inverso del segmento que hemos seleccionado
            // ¿deberíamos buscar en todas las listas o solo en las de inversos?
            // yo creo que en todas (en la de origen no, pero tampoco importa preguntar)
            $assert = 0;
            if ( isset($nDirNor[$vertex2]) && ($nDirNor[$vertex2]==$vertex1) ) { unset($nDirNor[$vertex2]); $assert++; }
            elseif ( isset($nDirOver1[$vertex2]) && ($nDirOver1[$vertex2]==$vertex1) ) { unset($nDirOver1[$vertex2]); $assert++; }
            elseif ( isset($nInvNor[$vertex2]) && ($nInvNor[$vertex2]==$vertex1) ) { unset($nInvNor[$vertex2]); $assert++; }
            elseif ( isset($nInvOver1[$vertex2]) && ($nInvOver1[$vertex2]==$vertex1) ) { unset($nInvOver1[$vertex2]); $assert++; }
            elseif ( isset($nDirOver2[$vertex2]) && ($nDirOver2[$vertex2]==$vertex1) ) { unset($nDirOver2[$vertex2]); $assert++; }
            elseif ( isset($nInvOver2[$vertex2]) && ($nInvOver2[$vertex2]==$vertex1) ) { unset($nInvOver2[$vertex2]); $assert++; }
            elseif ( isset($nDirOver3[$vertex2]) && ($nDirOver3[$vertex2]==$vertex1) ) { unset($nDirOver3[$vertex2]); $assert++; }
            elseif ( isset($nInvOver3[$vertex2]) && ($nInvOver3[$vertex2]==$vertex1) ) { unset($nInvOver3[$vertex2]); $assert++; }
            if ( $assert != 1 ) { print_r($nListaContornos); die("assert($assert != 1) in unset 2nd try" . PHP_EOL); }

            // como vertex1 lo insertamos anteriormente, solo insertaremos vertex2
            // print $vertex1 . "=>" . $vertex2 . PHP_EOL;
            list($x2, $y2) = explode(";", $vertex2);
            // lo insertamos en la lista de definitivos, buscando leftCorner de lo que será el polígono
            $nFixed[] = array( 'fila'=>$x2, 'col'=>$y2 );
            $leftCorner = findLeftCorner( $x2, $y2, $leftCorner, $nFixed );
        }

        if ( !$found ) { // hay que cerrar el polígono, no hemos encontrado vértices que coincidan
            $nListaContornos[] = array('level' => -1, 'polygon' =>$nFixed, 'leftCorner' => $leftCorner, 'inside' => array());
            // repetimos el código para insertar el primer elemento de la lista
            $nFixed = array();
            // buscamos la lista que todavía tenga elementos y escogemos uno
            if ( count($nDirNor) > 0 ) {
		$vertex1 = key($nDirNor); $vertex2 = current($nDirNor); array_shift($nDirNor);
                // list($vertex1, $vertex2) = each($nDirNor); array_shift($nDirNor);
            } elseif ( count($nDirOver1) > 0 ) {
		$vertex1 = key($nDirOver1); $vertex2 = current($nDirOver1); array_shift($nDirOver1);
                // list($vertex1, $vertex2) = each($nDirOver1); array_shift($nDirOver1);
            } elseif ( count($nInvNor) > 0 ) {
		$vertex1 = key($nInvNor); $vertex2 = current($nInvNor); array_shift($nInvNor);
                // list($vertex1, $vertex2) = each($nInvNor); array_shift($nInvNor);
            } elseif ( count($nInvOver1) > 0 ) {
		$vertex1 = key($nInvOver1); $vertex2 = current($nInvOver1); array_shift($nInvOver1);
                // list($vertex1, $vertex2) = each($nInvOver1); array_shift($nInvOver1);
            } elseif ( count($nDirOver2) > 0 ) {
		$vertex1 = key($nDirOver2); $vertex2 = current($nDirOver2); array_shift($nDirOver2);
                // list($vertex1, $vertex2) = each($nDirOver2); array_shift($nDirOver2);
            } elseif ( count($nInvOver2) > 0 ) {
		$vertex1 = key($nInvOver2); $vertex2 = current($nInvOver2); array_shift($nInvOver2);
                // list($vertex1, $vertex2) = each($nInvOver2); array_shift($nInvOver2);
            } elseif ( count($nDirOver3) > 0 ) {
		$vertex1 = key($nDirOver3); $vertex2 = current($nDirOver3); array_shift($nDirOver3);
                // list($vertex1, $vertex2) = each($nDirOver3); array_shift($nDirOver3);
            } elseif ( count($nInvOver3) > 0 ) {
		$vertex1 = key($nInvOver3); $vertex2 = current($nInvOver3); array_shift($nInvOver3);
                // list($vertex1, $vertex2) = each($nInvOver3); array_shift($nInvOver3);
            }
            list($x1, $y1) = explode(";", $vertex1); list($x2, $y2) = explode(";", $vertex2);
            // print "NEW LIST" . PHP_EOL . $vertex1 . "=>" . $vertex2 . PHP_EOL;

            // lo insertamos en la lista de definitivos, buscando leftCorner de lo que será el polígono
            $nFixed[] = array( 'fila'=>$x1, 'col'=>$y1 );
            $leftCorner = array( 'xMin' => $x1, 'yMin' => $y1, 'key' => 0 );
            $nFixed[] = array( 'fila'=>$x2, 'col'=>$y2 );
            $leftCorner = findLeftCorner( $x2, $y2, $leftCorner, $nFixed );

            // borramos el inverso del segmento que acabamos de coger (tiene que ser igual key y value!)
            $assert = 0;
            if ( isset($nDirNor[$vertex2]) && ($nDirNor[$vertex2] == $vertex1) ) { unset($nDirNor[$vertex2]); $assert++; }
            elseif ( isset($nDirOver1[$vertex2]) && ($nDirOver1[$vertex2]==$vertex1) ) { unset($nDirOver1[$vertex2]); $assert++; }
            elseif ( isset($nInvNor[$vertex2]) && ($nInvNor[$vertex2]==$vertex1) ) { unset($nInvNor[$vertex2]); $assert++; }
            elseif ( isset($nInvOver1[$vertex2]) && ($nInvOver1[$vertex2]==$vertex1) ) { unset($nInvOver1[$vertex2]); $assert++; }
            elseif ( isset($nDirOver2[$vertex2]) && ($nDirOver2[$vertex2]==$vertex1) ) { unset($nDirOver2[$vertex2]); $assert++; }
            elseif ( isset($nInvOver2[$vertex2]) && ($nInvOver2[$vertex2]==$vertex1) ) { unset($nInvOver2[$vertex2]); $assert++; }
            elseif ( isset($nDirOver3[$vertex2]) && ($nDirOver3[$vertex2]==$vertex1) ) { unset($nDirOver3[$vertex2]); $assert++; }
            elseif ( isset($nInvOver3[$vertex2]) && ($nInvOver3[$vertex2]==$vertex1) ) { unset($nInvOver3[$vertex2]); $assert++; }
            if ( $assert != 1 ) { print_r($nListaContornos); die("assert($assert != 1) in unset 3rd try" . PHP_EOL); }
        }

        $cuentaActual_old = $cuentaActual;
        $countPct = ($cuentaTotal - $cuentaActual)*100.0 / $cuentaTotal;
        if ( ($countPct - $countPct_old) > 10 ) { print "[" . round($countPct) . "%]"; $countPct_old = $countPct; }

    }
    // añadimos el último polígono que nos quedaba pendiente
    $nListaContornos[] = array('level' => -1, 'polygon' =>$nFixed, 'leftCorner' => $leftCorner, 'inside' => array());
    print "[100%]";
    return $nListaContornos;

    // CODIGO ORIGINAL (SOPORTA DE FORMA NATIVA LOS CRUCES)
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

            
            //foreach($c['segments'] as $segmento) {
            //    fwrite(STDERR,  $segmento['x1'] . ";" . $segmento['y1'] . ";" . $segmento['x2'] . ";" . $segmento['y2'] . PHP_EOL);
            //}
            //print_r($c['segments']);
            //print_r($contornoFixed);
            //die("ERROR determinaContornos2: no se ha encontrado punto siguiente" . PHP_EOL);
            
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
                // assert
                // if ( abs($oldx-$x1)>0 ) print abs($oldx-$x1);
                // if ( abs($oldy-$y1)>0 ) print abs($oldy-$y1);
                // si del segmento que toca probar estamos muy cerca de uno de sus vértices,
                // añadimos el otro punto del vértice, borramos el segmento y seguimos.
                // print "found $k para oldx,oldy,x1,y1" . PHP_EOL;
                // print "oldx: $oldx oldy: $oldy x1: $x1 y1: $y1" . PHP_EOL;
                // $contornoFixed[] = array('fila'=>$x1, 'col' => $y1);
                $contornoFixed[] = array('fila'=>$x2, 'col'=>$y2);
                unset($c['segments'][$k]);
                $leftCorner = findLeftCorner( $x2, $y2, $leftCorner, $contornoFixed );
                break;
            } elseif ( (abs($oldx - $x2) < 0.0001) &&
                (abs($oldy - $y2) < 0.0001) ) {
                // assert
                // if ( abs($oldx-$x2)>0 ) print abs($oldx-$x2);
                // if ( abs($oldy-$y2)>0 ) print abs($oldy-$y2);
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
//    print_r($listaContornos);
    return $listaContornos;

}

/**
 * Helper de determinaContornos2. Ordena la jerarquía de una lista de
 * contornos cerrados, para saber quién depende de quién. También rota
 * los contornos dependiendo de si están dentro o fuera de otro.
 * @param array $contorno lista de contornos cerrados (ENTRADA)
 * @return array jerarquía de contornos ya clasificados y rotados
 */
function determinaContornos2_sortContornos($listaContornos, $is_in_polygon_function = 'is_in_polygon2') {

    $debug = false;

    if ( !isset($listaContornos) || 0 == count($listaContornos) ) {
        return array();
    }

    $nuevaListaContornos = array();
    $cuentaContornosOriginal = count($listaContornos);

    // calculamos la jerarquía de los polígonos y los rotamos según su profundidad
    $salir = false;
    while ( !$salir ) {

	if ( $debug ) {
	    print PHP_EOL . "STATUS" . PHP_EOL;
	    print "listaContornos:      #" . count($listaContornos) . PHP_EOL;
	    print "nuevaListaContornos: #" . count($nuevaListaContornos) . PHP_EOL;
	    foreach( $listaContornos as $k => $l ) {
		print $k . "/" . count($listaContornos) . " con #" . count($l['polygon']) . " vértices" . PHP_EOL;
		print "\t level:" . $l['level'] . PHP_EOL;
		print "\t inside:" . count($l['inside']) . PHP_EOL;
	    }
	    print "============================================" . PHP_EOL;
	}



	// print_r($listaContornos);

        // print_r(array_keys($listaContornos));

	// extraemos el primer contorno e iremos viendo si dentro tiene a alguien.
        $c = array_shift( $listaContornos );
	// comprobación innecesaria
        if ( -1 != $c['level'] ) {
            // nunca deberíamos comprobar dos veces si un polígono tiene elementos dentro
	    logger(" E> Error al analizar la jerarquía de contornos, " .
		"nunca deberíamos comprobar dos veces si un polígono tiene elementos dentro");
	    print_r($c);
	    exit(-1);
        }

	// Comparamos el primer contorno con el resto, para ver si una de las esquinas está dentro de alguno.
	// Si está dentro, indicamos en el primer contorno que tiene uno dentro.

        $is_in_polygon = false;
	foreach( $listaContornos as $k => $l ) {
	    $is_in_polygon = false;
	    /*foreach( $l['polygon'] as $vertex ) {
		if ( true === ( $is_in_polygon = $is_in_polygon_function( $c['polygon'], $vertex )) ) {
		    print "ACABAMOS de encontrar a alguien dentro" . PHP_EOL;
		    break;
		}
	    }*/
	    // print "procesando $k/" . count($listaContornos) . " de listaContornos con #" . count($l['polygon']) . " vértices" . PHP_EOL;
	    // print_r($l['polygon'][0]);
	    // print_r($c['polygon']);
	    $is_in_polygon = $is_in_polygon_function( $c['polygon'], $l['polygon'][0]);
	    // exit(0);
	    // hay un polígono (l) que está dentro del contorno (c)
	    if ( $is_in_polygon ) {
		// print "uno dentro ($k)" . PHP_EOL;
		// actualizamos el nivel
		$l['level'] = 1;
		$c['level'] = 0;
		// comprobamos la orientación del interno.
		// https://developers.google.com/kml/documentation/kmlreference?hl=en
		// The <coordinates> for polygons must be specified in counterclockwise order.
		// Polygons follow the "right-hand rule," which states that if you place the
		// fingers of your right hand in the direction in which the coordinates are
		// specified, your thumb points in the general direction of the geometric
		// normal for the polygon.
		$orientacion = comprobarOrientacion( $l['polygon'], $l['leftCorner'] );
		// print "IN] " . count($l['polygon']) . " => " . ($orientacion ? "CCW" : "CW") . PHP_EOL;
		// exterior rings: counter-clockwise directorion.
		// interior rings (holes): clockwise direction.
		// @url https://gis.stackexchange.com/questions/119150/order-of-polygon-vertices-in-general-gis-clockwise-or-counterclockwise
		// al ser interior, debería ser CW
		if ( true === $orientacion ) { // orientación es CCW, lo rotamos para dejarlo CW
		    // print "ROTando de CCW a CW" . PHP_EOL;
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
		// print "SI tiene a alguien dentro" . PHP_EOL;
		// echo json_encode($l['polygon']) . PHP_EOL;
	    }
	}

	// hemos encontrado todos los contornos de listaContornos que están dentro de $c,
	// y los hemos dejado colgando de él, borrándolos de listaContornos.

	// nuevaListaContornos en una primera pasada está vacía.
	// en siguientes pasadas puede contener a alguien, puede no contener a nadie o puede ser contenido (sin saberlo)
	// solo nos fijamos si contiene contornos que no contengan a nadie
	foreach( $nuevaListaContornos as $k => $l ) {
	    if ( $l['level'] != -1 )
		continue;
	    $is_in_polygon = false;
	    /*
	    foreach( $l['polygon'] as $vertex ) {
		if ( true === ( $is_in_polygon = $is_in_polygon_function( $c['polygon'], $vertex )) ) {
		    print "ACABAMOS de encontrar a alguien dentro EN LA NUEVA LISTA" . PHP_EOL;
		    break;
		}
	    }*/

	    if ( $debug )
		print "procesando $k/" . count($nuevaListaContornos) . " de nuevaListaContornos con #" . count($l['polygon']) . " vertices" . PHP_EOL;
	    // print "_M_>" . PHP_EOL;
	    // print $is_in_polygon_function . PHP_EOL;
	    // print_r($c['polygon']);
	    $is_in_polygon = $is_in_polygon_function( $c['polygon'], $l['polygon'][0]); // array('col' => $l['leftCorner']['yMin'], 'fila' => $l['leftCorner']['xMin']));
	    // $is_in_polygon = $is_in_polygon_function( $c['polygon'], $l['polygon'][0]);
	    // print "<_M_" . PHP_EOL;

	    if ( $is_in_polygon ) {
		$l['level'] = 1;
		$c['level'] = 0;
		$orientacion = comprobarOrientacion( $l['polygon'], $l['leftCorner'] );
		if ( true === $orientacion ) { // orientación es CCW, lo rotamos para dejarlo CW
		    $l['polygon'] = array_reverse( $l['polygon'] );
		}
		unset($l['leftCorner']);
		$c['inside'][] = $l;
		unset($nuevaListaContornos[$k]);
		// print "SI tiene a alguien dentro EN LA NUEVA LISTA" . PHP_EOL;
		// echo json_encode($l['polygon']);
	    }
	}

	// si contiene a alguien, ya sabemos su orientación, porque es contenedor.
	if ( 0 == $c['level'] ) { // el contorno contiene polígonos
	    // print "El polígono ráiz tiene dentro otros polígonos, así que comprobaremos su orientación: ";
	    $orientacion = comprobarOrientacion( $c['polygon'], $c['leftCorner'] );
	    if ( false === $orientacion ) {
		$c['polygon'] = array_reverse( $c['polygon'] );
		// print "rotando polígono raiz ";
	    }
	    // print "ok" . PHP_EOL;
	    unset($c['leftCorner']);
	}

	// sea como sea, insertamos el contorno en la lista de nuevos contornos
	// puede contener a alguien, puede no contener a nadie o puede ser contenido.
	$nuevaListaContornos[] = $c;

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

    // hemos acabado con listaContornos, todos los que queden en nuevaListaContornos
    // o bien tienen a alguien dentro y están procesados o bien no tienen a nadie
    // así que serán nivel 0
    foreach( $nuevaListaContornos as $k => $l ) {
	if ( -1 == $l['level'] ) {
	    $nuevaListaContornos[$k]['level'] = 0;
	    $orientacion = comprobarOrientacion( $l['polygon'], $l['leftCorner'] );
	    if ( false === $orientacion ) { // debería ser CCW
		$nuevaListaContornos[$k]['polygon'] = array_reverse ($l['polygon'] );
	    }
	    unset($nuevaListaContornos[$k]['leftCorner']);
	}
    }
/*
    print"STATUS FINAL LISTACONTORNOS" . PHP_EOL;
    foreach( $listaContornos as $k => $l ) {
	print $k . "] " . count($l['polygon']) . PHP_EOL;
	print "\t level:" . $l['level'] . PHP_EOL;
	print "\t inside:" . count($l['inside']) . PHP_EOL;
    }
*/
    if ( count($listaContornos) > 0 ) {
	logger("E> listaContornos deberia estar vacia!!!!, abortando");
	exit(-1);
    }

    $cuentaContornosNueva = 0;
    // print"STATUS FINAL NUEVALISTACONTORNOS" . PHP_EOL;
    foreach( $nuevaListaContornos as $k => $l ) {
	$cuentaContornosNueva++;
	// print $k . "] " . count($l['polygon']) . PHP_EOL;
	// print "\t level:" . $l['level'] . PHP_EOL;
	// print "\t inside:" . count($l['inside']) . PHP_EOL;
	$cuentaContornosNueva += count($l['inside']);
	if ( isset($l['leftCorner']) ) {
	    print "algo salio mal" . PHP_EOL;
	    print_r($nuevaListaContornos[$k]); exit(-1);
	}
    }
    // print "============================================" . PHP_EOL;

    if ( $cuentaContornosOriginal != $cuentaContornosNueva ) {
	print "ERROR> Algo salió mal" . PHP_EOL;
	print "cuentaContornosOriginal: $cuentaContornosOriginal" . PHP_EOL;
	print "cuentaContornosNueva: $cuentaContornosNueva" . PHP_EOL;
	exit(-1);
    }

    return $nuevaListaContornos;

}

/**
 * Función que determina los contornos de cobertura que hay en una matriz
 *
 * @url http://paulbourke.net/papers/conrec/
 * @param array $malla (ENTRADA)
 * @return array $listaContornos (SALIDA)
 */
function determinaContornos2($malla) {

    //if ( NULL === ($listaContornos = json_decode(@file_get_contents("turrillas.json"), true)) ) {
        $c = determinaContornos2_getContornos($malla);
        $listaContornos = determinaContornos2_joinContornos($c);
        //file_put_contents("turrillas.json", json_encode($listaContornos));
    //}

    $listaContornosCount = count($listaContornos);

/*
    for($j=0;$j<1000;$j++) {
	shuffle($listaContornos);
	$listaContornosRes = determinaContornos2_sortContornos($listaContornos, 'is_in_polygon2');
    }
    $listaContornos = $listaContornosRes;
*/

    $listaContornos = determinaContornos2_sortContornos($listaContornos, 'is_in_polygon2');
    $assertListaContornosCount = count($listaContornos);
    foreach( $listaContornos as $k => $l ) {
        // print $k . "] " . count($l['polygon']) . PHP_EOL;
        // print "\t level:" . $l['level'] . PHP_EOL;
        // print "\t inside:" . count($l['inside']) . PHP_EOL;
        $assertListaContornosCount += count($l['inside']);
    }

    print "[assert listaContornos: " . $listaContornosCount . "=?" . $assertListaContornosCount . "]" . PHP_EOL;
    if ( $listaContornosCount != $assertListaContornosCount ) {
	logger(" E> Error al reindexar los contornos");
	exit(-1);
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
	// print_r($v[$i]); print_r($p);
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

    // si no se le pasa una estructura leftCorner o está vacía,
    // devuelve el elemento pasado
    if ( (false === $leftCorner) || (0 == count($leftCorner)) ) {
	$leftCorner = array(
	    'xMin' => $x,
	    'yMin' => $y);
	if ( false === $k ) {
            $leftCorner['key'] = count($arr) - 1;
        } else {
            $leftCorner['key'] = $k;
        }
	return $leftCorner;
    }

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
    // cuando lo llamamos para contornos creados de malla, trabajamos con fila/col, pero
    // si el contorno viene directamente una cobertura por encima del radar, tendremos
    // lat/lon
    if ( isset($contornoFixed[0]['fila']) ) {
	$fila = 'fila'; $col = 'col';
    } else if ( isset($contornoFixed[0]['lat']) ) {
	$fila = 'lon'; $col = 'lat'; //después de varias pruebas, esto debe ser así
    } else {
	$fila = 1; $col = 0; // hacemos caso al comentario de arriba, pero esto es justo al revés de lo que debería ser
    }

//    $xA = $contornoFixed[(($k-1) + $n) % $n]['fila']; $yA = $contornoFixed[(($k-1) + $n) % $n]['col'];
//    $xB = $contornoFixed[$k]['fila']; $yB = $contornoFixed[$k]['col'];
//    $xC = $contornoFixed[($k+1) % $n]['fila']; $yC = $contornoFixed[($k+1) % $n]['col'];

    $xA = $contornoFixed[(($k-1) + $n) % $n][$fila]; $yA = $contornoFixed[(($k-1) + $n) % $n][$col];
    $xB = $contornoFixed[$k][$fila]; $yB = $contornoFixed[$k][$col];
    $xC = $contornoFixed[($k+1) % $n][$fila]; $yC = $contornoFixed[($k+1) % $n][$col];

    $det = (( $xB - $xA )*( $yC - $yA )) - (( $xC - $xA )*( $yB - $yA ));

    if ( $det>0 )
        return true; // CCW
    else
        return false; // CW
}

/*
 * Para coberturas por debajo de la altura del radar,
 * busca la distancia mayor a la que hay cobertura, con la idea de poder
 * reducir el alcance (y el tamaño de malla) a esa distancia (por ejemplo,
 * si solo hay cobertura hasta 50NM, no tiene sentido hacer una malla de
 * 250NM, porque así nos evitamos calcular un montón de puntos sin
 * cobertura más adelante.
 * @return float nuevo alcance en metros
 */
function obtieneMaxAnguloConCoberturaB($radar) {
    // $timerStart0 = microtime(true);
    $maxAnguloConCobertura = 0;
    foreach($radar['screening']['listaAzimuths'] as $azimuth => $listaObstaculos) {
        foreach($listaObstaculos as $obstaculo) {
            if ( $obstaculo['estePtoTieneCobertura'] && ($obstaculo['angulo'] > $maxAnguloConCobertura) ) {
                $maxAnguloConCobertura = $obstaculo['angulo'];
            }
        }
    }
    // printf("[%3.4fs]", microtime(true) - $timerStart0);
    logger(" V> ánguloAlcanceMáximo: " . round($maxAnguloConCobertura,3) . "º");
    $newRange = $maxAnguloConCobertura*$radar['screening']['radioTerrestreAumentado'];
    logger(" V> distanciaAlcanceMáximo: " . round($newRange/MILLA_NAUTICA_EN_METROS,2) . "NM / " . round($newRange,2) . "m");
    // además de alinear el alcance máximo a múltiplos de 1852 (1NM), le sumamos
    // una milla adicional, para que la matriz nunca acabe con cobertura en una de
    // sus esquinas
    // no debería hacer falta hacer un round
    $newRange = round($newRange,0) + (1852 - (round($newRange,0) % 1852)) + 1852;
    logger(" V> distanciaAlcanceMáximoAlineada: " . ($newRange/MILLA_NAUTICA_EN_METROS) . "NM / {$newRange}m");

    return $newRange;
}

/*
 * Para coberturas por encima de la altura del radar,
 * busca la distancia mayor a la que hay cobertura, con la idea de poder
 * reducir el alcance (y el tamaño de malla) a esa distancia (por ejemplo,
 * si solo hay cobertura hasta 50NM, no tiene sentido hacer una malla de
 * 250NM, porque así nos evitamos calcular un montón de puntos sin
 * cobertura más adelante.
 * @param array distanciasAlcances con el máximo alcance en NM por cada azimuth.
 * @return float nuevo alcance en metros
 */
function obtieneMaxAnguloConCoberturaA($distanciasAlcances) {
    // Como es por encima, habrá un array con la máxima distancia en millas
    $newRange = max($distanciasAlcances)*MILLA_NAUTICA_EN_METROS;
    logger(" V> distanciaAlcanceMáximo: " . round($newRange/MILLA_NAUTICA_EN_METROS,2) . "NM / " . round($newRange,2) . "m");
    // además de alinear el alcance máximo a múltiplos de 1852 (1NM), le sumamos
    // una milla adicional, para que la matriz nunca acabe con cobertura en una de
    // sus esquinas
    $newRange = round($newRange,0) + (1852 - (round($newRange,0) % 1852)) + 1852;
    // no debería hacer falta hacer un round
    logger(" V> distanciaAlcanceMáximoAlineada: " . ($newRange/MILLA_NAUTICA_EN_METROS) . "NM / {$newRange}m");

    return $newRange;
}

/*
 * comprueba que no haya cobertura en ninguna de las esquinas de la malla,
 * porque sino el algoritmo de contorno fallaría.
 * si hay cobertura, cerramos la ejecución.
 */
function checkCoverageOverflow($malla) {
    // obtiene primer índice de la malla
    $index_i = array_keys($malla);
    $index_j = array_keys($malla[$index_i[0]]);

    $i_first = $index_i[0];
    $j_first = $index_j[0];

    $i_last = $index_i[count($index_i)-1];
    $j_last = $index_j[count($index_j)-1];

    if ( false ) print "DEBUG i_first: $i_first i_last: $i_last j_first: $j_first j_last: $j_last";
    foreach( $malla as $i => $rows ) {
        foreach ( $rows as $j => $value ) {
            // miramos solo en las esquinas
            if ( $i == $i_first || $i == $i_last ||
                 $j == $j_first || $j == $j_last ) {
                if ( $value == 1 ) {
                    print "ERROR hay cobertura en una esquina (i:$i j:$j)" . PHP_EOL; exit(-1);
                }
            } else {
                continue;
            }
        }
    }
/*
    for( $i=0; $i<count($malla); $i++ ) {
        for( $j=0; $j<count($malla[$i]); $j++ ) {
            // miramos solo en las esquinas
            if ( $i == 0 || $i == (count($malla)-1) ||
                 $j == 0 || $j == (count($malla[$i])-1) ) {
                if ($malla[$i][$j] == 1) {
                    print "ERROR hay cobertura en una esquina (i:$i j:$j)" . PHP_EOL; exit(-1);
                }
            } else {
                continue;
            }
        }
    }
*/
    return true;
}

/*
 * Helper function para Ramer–Douglas–Peucker
 * https://rosettacode.org/wiki/Ramer-Douglas-Peucker_line_simplification#PHP
 *
 * @param array punto
 * @param array línea
 * @return float distancia perpendicular del punto a la línea
 */
function perpendicular_distance(array $pt, array $line) {
    // Calculate the normalized delta x and y of the line.
    $dx = $line[1][0] - $line[0][0];
    $dy = $line[1][1] - $line[0][1];
    $mag = sqrt($dx * $dx + $dy * $dy);
    if ($mag > 0) {
        $dx /= $mag;
        $dy /= $mag;
    }

    // Calculate dot product, projecting onto normalized direction.
    $pvx = $pt[0] - $line[0][0];
    $pvy = $pt[1] - $line[0][1];
    $pvdot = $dx * $pvx + $dy * $pvy;

    // Scale line direction vector and subtract from pv.
    $dsx = $pvdot * $dx;
    $dsy = $pvdot * $dy;
    $ax = $pvx - $dsx;
    $ay = $pvy - $dsy;

    return sqrt($ax * $ax + $ay * $ay);
}

/*
 * The Ramer–Douglas–Peucker algorithm is a line simplification algorithm
 * for reducing the number of points used to define its shape.
 * https://rosettacode.org/wiki/Ramer-Douglas-Peucker_line_simplification#PHP
 *
 * @param array points lista de parejas de puntos con un polígono a simplificar.
 * @param float epsilon límite para descartar puntos demasiado juntos.
 * @return array lista de puntos simplificada
 */
function ramer_douglas_peucker(array $points, $epsilon) {
    if (count($points) < 2) {
        throw new InvalidArgumentException('Not enough points to simplify');
    }
    // Find the point with the maximum distance from the line between start/end.
    $dmax = 0;
    $index = 0;
    $end = count($points) - 1;
    $start_end_line = array( $points[0], $points[$end] );
    for ($i = 1; $i < $end; $i++) {
        $dist = perpendicular_distance($points[$i], $start_end_line);
        if ($dist > $dmax) {
            $index = $i;
            $dmax = $dist;
        }
    }

    // If max distance is larger than epsilon, recursively simplify.
    if ($dmax > $epsilon) {
        $new_start = ramer_douglas_peucker(array_slice($points, 0, $index + 1), $epsilon);
        $new_end = ramer_douglas_peucker(array_slice($points, $index), $epsilon);
        array_pop($new_start);
        return array_merge($new_start, $new_end);
    }

    // Max distance is below epsilon, so return a line from with just the
    // start and end points.
    return array( $points[0], $points[$end] );
}
