<?php

CONST FRONTERA_LATITUD = 90; // latitud complementaria
CONST PIE_EN_METROS =  3.2808;
CONST CIEN_PIES = 100;
CONST PASO_A_GRADOS = 180;
// maxima distancia q puede haber entre dos puntos de un acimut para saber si es necesario interpolar
CONST DISTANCIA_ENTRE_PUNTOS = 5; 
CONST TAM_CELDA = 0.5; // paso de la malla en NM 0.5 , 0.11 es lo mas que pequeño q no peta




/**
 * Funion para convertir el nivel de vuelo dado en metros
 * @param double $num dado en cientos de pies 
 * @return number
 */
function fLtoMeters ($num){
	
	return $num = $num * CIEN_PIES / PIE_EN_METROS;
}

/**
 * Funcion para convertir el parametro dado en millas nauticas a metros
 * @param double $num
 * @return number
 */
function NMtoMeters ($num){
	
	return $num = $num * MILLA_NAUTICA_EN_METROS;
}


 /**
  * Funcion que permite buscar los puntos limitantes necesarios para poder calcular la cobertura.
  * Entrada:
  * @param array $listaObstaculos
  * @param int $flm
  * Salida:
  * @param double $alturaPrimerPtoSinCob
  * @param double $anguloPrimerPtoSinCob
  * @param unknown $alturaUltimoPtoCob
  * @param unknown $anguloUltimoPtoCob
  * @return boolean Devuelve true si encontrado o false en caso contrario
  */ 
function buscarPuntosLimitantes($listaObstaculos, $flm, &$alturaPrimerPtoSinCob, &$anguloPrimerPtoSinCob, &$alturaUltimoPtoCob, &$anguloUltimoPtoCob){
	
	$size = count($listaObstaculos); // da el tamaño de la lista de obstaculos para el acimut concreto
	
	$i=0;
	$enc = false;
	while ($i< $size && !$enc){
		
		if ($flm < $listaObstaculos[$i]['altura']){ // la primera vez que se cumple esto tenemos el primer punto sin cobertura 
		        if ( $i == 0 ) {
		            die("el primer obstÃ¡culo no tiene cobertura, no tenemos ultimo punto con cobertura, revisar para probar soluciÃ³n.");
		        }
			$enc = true;
			$primerPtoSinCobertura = $listaObstaculos[$i];
			$ultimoPtoCobertura = $listaObstaculos[$i-1];
			
			$alturaPrimerPtoSinCob = $listaObstaculos[$i]['altura'];
			$anguloPrimerPtoSinCob = $listaObstaculos[$i]['angulo'];
			$alturaUltimoPtoCob    = $listaObstaculos[$i-1]['altura'];
			$anguloUltimoPtoCob    = $listaObstaculos[$i-1]['angulo'];
		}
		else
		   $i++;   
	}
	
	return $enc; 
}

 
/**
 * Funcion  auxiliar para calcular AlphRange
 * @param unknown $radar
 * @param unknown $radioTerrestreAumentado
 * @param unknown $flm
 * @return number
 */
function calculaAnguloMaximaCobertura($radar, $radioTerrestreAumentado, $flm){
	
	//$a = Distancia del centro de la Tierra al radar.
	$earthToRadar = $radar['towerHeight'] + $radar['terrainHeight'] + $radioTerrestreAumentado;
	
	//$c = Distancia del centro de la Tierra al nivel de vuelo.
	$earthToFl = $radioTerrestreAumentado + $flm;
	// esto antes era AlphaRange
	return $anguloMaxCob = acos((pow($earthToRadar,2) + pow($earthToFl,2) - pow($radar['range'],2)) / (2 * $earthToRadar *$earthToFl));
}


/*                                         COPIA DE SEGURIDAD
 * ** CASO A
 *  Funcion que calcula las distancias a las que hay cobertura y los angulos de apantallamiento cuando el FL esta por encima del radar
 * @param array $radar
 * @param int $flm
 * @param doble $radioTerrestreAumentado
 * @param array $angulosApantallamiento
 * @param array $distanciasCobertura
 
function calculosFLencimaRadar($radar, $flm, $radioTerrestreAumentado, &$angulosApantallamiento, &$distanciasCobertura ){
	  //$a = Distancia del centro de la Tierra al radar.
	 	$earthToRadar = $radar['towerHeight'] + $radar['terrainHeight'] + $radioTerrestreAumentado;
	
	   //$c = Distancia del centro de la Tierra al nivel de vuelo.
   		$earthToFl = $radioTerrestreAumentado + $flm; 
		// esto antes era AlphaRange
   		$anguloMaxCob = acos((pow($earthToRadar,2) + pow($earthToFl,2) - pow($radar['range'],2)) / (2 * $earthToRadar *$earthToFl));

		// SE PUEDEN PASAR COMO PARAMETRO !!! CAMBIAR CABEZA 	
	   // $anguloMaxCob = calculaAnguloMaximaCobertura($radar, $radioTerrestreAumentado, $flm);
	   // $earthToFl = $radioTerrestreAumentado + $flm; // SI DESCOMENTAS LO DE ARRIBA ESTA LINEA SOBRA
	   // $earthToRadar = $radar['towerHeight'] + $radar['terrainHeight'] + $radioTerrestreAumentado;//// SI DESCOMENTAS LO DE ARRIBA ESTA LINEA SOBRA
	
		// recorremos los azimuths 
	 	for ($i=0; $i < $radar['totalAzimuths']; $i++){
 	 		
	 		// obtenemos la ultima linea del array para cada azimut.
	 		$tamaño = count($radar['listaAzimuths'][$i]);
	 	
	 		// obtenemos la altura del ultimo punto para cada azimuth
	 		$obstaculoLimitante = $radar['listaAzimuths'][$i][$tamaño-1]['altura'];  
	 		
	 		if ($flm >= $obstaculoLimitante){
	 			
	 			// esto antes era b
	 			$earthToEvalPoint = $radioTerrestreAumentado + $obstaculoLimitante; 
	 			// obtenemos el angulo del ultimo obstaculo de cada azimuth
	 			$angulo = $radar['listaAzimuths'][$i][$tamaño-1]['angulo'];
	 				 	
	 	 		// calculamos dg1 y con ello calculamos gammaMax
	 			$distanciasCobertura[$i]= sqrt ((pow($earthToRadar,2) + pow($earthToEvalPoint,2)) - 2 * $earthToRadar * $earthToEvalPoint * cos($angulo));
	 	 		$gammaMax = acos((pow($distanciasCobertura[$i],2) + pow($earthToRadar,2) - pow($earthToEvalPoint,2)) / (2 * $earthToRadar * $distanciasCobertura[$i]));
	 	 	
	 			 // una vez obtenido gammaMax podemos calcular theta y epsilon
	 	 		$theta = asin($earthToRadar * sin($gammaMax)/ $earthToFl);
	 	 		$epsilon = PI - $theta - $gammaMax;
	 	
	 	 		if ($epsilon >  $anguloMaxCob)
	 	 			$distanciasCobertura[$i] = $radioTerrestreAumentado * $anguloMaxCob / MILLA_NAUTICA_EN_METROS;
	 			 else
	 	 			$distanciasCobertura[$i] = $radioTerrestreAumentado * $epsilon / MILLA_NAUTICA_EN_METROS; 
	 	
	 	 	
	 	 		$angulosApantallamiento[$i] = ($gammaMax * PASO_A_GRADOS / PI) - FRONTERA_LATITUD; 
	 		}
	 	 	else{ // $fl < $obstaculoLimitante 
	 	 	
	 	 		$anguloLimitante =0;
	 	 		$alturaPrimerPtoSinCob=0;
	 	 		$anguloPrimerPtoSinCob=0;
	 	 		$alturaUltimoPtoCob=0;
	 	 		$anguloUltimoPtoCob =0;
	 	 		
	 	 		 if(buscarPuntosLimitantes($radar['listaAzimuths'][$i], $flm, $alturaPrimerPtoSinCob, $anguloPrimerPtoSinCob, $alturaUltimoPtoCob, $anguloUltimoPtoCob)){
	 	 		 	$anguloLimitante = (($flm-$alturaUltimoPtoCob) * (($anguloPrimerPtoSinCob - $anguloUltimoPtoCob)  / ($alturaPrimerPtoSinCob - $alturaUltimoPtoCob))) + $anguloUltimoPtoCob;
	 	 		 }
	 	 		 else 
	 	 		 	echo " ERROR MALIGNO !! No deberias haber entrado aqui" . PHP_EOL;
	 	 		 	
	 	 		 
	 	 		 if ($anguloLimitante > $anguloMaxCob)
	 	 		 	 $distanciasCobertura[$i] = $radioTerrestreAumentado * $anguloMaxCob/ MILLA_NAUTICA_EN_METROS;
	 	 		 else 
	 	 		 	$distanciasCobertura[$i] = $radioTerrestreAumentado * $anguloLimitante / MILLA_NAUTICA_EN_METROS;
	 	 		
	 	 	}// else
	 	}// fin for para recorrer los azimuths	 
}// fin function
 */

/** CASO 1
 *  Funcion que calcula las distancias a las que hay cobertura y los angulos de apantallamiento cuando el FL esta por encima del radar
 * @param array $radar
 * @param int $flm
 * @param doble $radioTerrestreAumentado
 * @param array $angulosApantallamiento
 * @param array $distanciasCobertura
 */
function calculosFLencimaRadar($radar, $flm, $radioTerrestreAumentado, &$angulosApantallamiento, &$distanciasCobertura ){
		
	    $anguloMaxCob = calculaAnguloMaximaCobertura($radar, $radioTerrestreAumentado, $flm);
	    $earthToFl = $radioTerrestreAumentado + $flm; 
	    $earthToRadar = $radar['towerHeight'] + $radar['terrainHeight'] + $radioTerrestreAumentado;
	
		// recorremos los azimuths 
	 	for ($i=0; $i < $radar['totalAzimuths']; $i++){
 	 		
	 		// obtenemos la ultima linea del array para cada azimut.
	 		$tamaño = count($radar['listaAzimuths'][$i]);
	 	
	 		// obtenemos la altura del ultimo punto para cada azimuth
	 		$obstaculoLimitante = $radar['listaAzimuths'][$i][$tamaño-1]['altura'];  
	 		
	 		if ($flm >= $obstaculoLimitante){
	 			
	 			// esto antes era b
	 			$earthToEvalPoint = $radioTerrestreAumentado + $obstaculoLimitante; 
	 			// obtenemos el angulo del ultimo obstaculo de cada azimuth
	 			$angulo = $radar['listaAzimuths'][$i][$tamaño-1]['angulo'];
	 				 	
	 	 		// calculamos dg1 y con ello calculamos gammaMax
	 			$distanciasCobertura[$i]= sqrt ((pow($earthToRadar,2) + pow($earthToEvalPoint,2)) - 2 * $earthToRadar * $earthToEvalPoint * cos($angulo));
	 	 		$gammaMax = acos((pow($distanciasCobertura[$i],2) + pow($earthToRadar,2) - pow($earthToEvalPoint,2)) / (2 * $earthToRadar * $distanciasCobertura[$i]));
	 	 	
	 			 // una vez obtenido gammaMax podemos calcular theta y epsilon
	 	 		$theta = asin($earthToRadar * sin($gammaMax)/ $earthToFl);
	 	 		$epsilon = PI - $theta - $gammaMax;
	 	
	 	 		if ($epsilon >  $anguloMaxCob)
	 	 			$distanciasCobertura[$i] = $radioTerrestreAumentado * $anguloMaxCob / MILLA_NAUTICA_EN_METROS;
	 			 else
	 	 			$distanciasCobertura[$i] = $radioTerrestreAumentado * $epsilon / MILLA_NAUTICA_EN_METROS; 
	 	
	 	 	
	 	 		$angulosApantallamiento[$i] = ($gammaMax * PASO_A_GRADOS / PI) - FRONTERA_LATITUD; 
	 		}
	 	 	else{ // $fl < $obstaculoLimitante 
	 	 	
	 	 		$anguloLimitante =0;
	 	 		$alturaPrimerPtoSinCob=0;
	 	 		$anguloPrimerPtoSinCob=0;
	 	 		$alturaUltimoPtoCob=0;
	 	 		$anguloUltimoPtoCob =0;
	 	 		
	 	 		 if(buscarPuntosLimitantes($radar['listaAzimuths'][$i], $flm, $alturaPrimerPtoSinCob, $anguloPrimerPtoSinCob, $alturaUltimoPtoCob, $anguloUltimoPtoCob)){
	 	 		 	$anguloLimitante = (($flm-$alturaUltimoPtoCob) * (($anguloPrimerPtoSinCob - $anguloUltimoPtoCob)  / ($alturaPrimerPtoSinCob - $alturaUltimoPtoCob))) + $anguloUltimoPtoCob;
	 	 		 }
	 	 		 else 
	 	 		 	echo " ERROR MALIGNO !! No deberias haber entrado aqui" . PHP_EOL;
	 	 		 	
	 	 		 
	 	 		 if ($anguloLimitante > $anguloMaxCob)
	 	 		 	 $distanciasCobertura[$i] = $radioTerrestreAumentado * $anguloMaxCob/ MILLA_NAUTICA_EN_METROS;
	 	 		 else 
	 	 		 	$distanciasCobertura[$i] = $radioTerrestreAumentado * $anguloLimitante / MILLA_NAUTICA_EN_METROS;
	 	 	}// else
	 	}// fin for para recorrer los azimuths	 
}// fin function


/**
 *  
 * Funcion que calcula las coordenadas geograficas de cobertura de un fichero kml para un determinado
 * radar a partir de las coordenadas, el nivel de vuelo y el array de distancias de cobertura.
 * 
 * Entradas: 	$radar (estructura de datos)
 * 				$coordenadas (grados decimales)
 * 				$flm (metros)
 * 				$distanciasCobertura (millas nauticas)
 * Salida: 		$resultadosCoordenadas (estructura de datos)
 */
function calculaCoordenadasGeograficas( $radar, $coordenadas, $distanciasCobertura, $flm, &$coordenadasGeograficas){
	
	// Calcula el paso en funciÃ³n del nÃºmero mÃ¡ximo de azimuth (puede ser desde 360 o 720)
	$paso = 360.0 / $radar['totalAzimuths'];

	// Recorrido de los acimuts 
 	for ($i =0; $i< $radar['totalAzimuths']; $i++){
 		
 		// Calculo de la latitud
 		$anguloCentral = ($distanciasCobertura[$i] * MILLA_NAUTICA_EN_METROS / RADIO_TERRESTRE);
 		$latitudComplementaria = deg2rad(FRONTERA_LATITUD - $coordenadas['latitud']);
 		$r = rad2deg(acos (cos($latitudComplementaria) * cos($anguloCentral) + sin($latitudComplementaria) * sin($anguloCentral)
 			 * cos(deg2rad($i * $paso)))); // tenemos r en grados
 		
 		// Calculo de la longitud
 		$rEnRadianes = deg2rad($r);
 		$numerador = cos($anguloCentral) - cos($latitudComplementaria) * cos($rEnRadianes);
 		$denominador = sin($latitudComplementaria) * sin($rEnRadianes);
 			
 		if($numerador>$denominador)
 			$p = 0;
 		else
 			$p =  rad2deg(acos($numerador/$denominador));
 		
 			// asignacion de valores a la estructura de datos
 		if ($i < ($radar['totalAzimuths'] /2))
 			$coordenadasGeograficas[$i]['longitud'] = $coordenadas['longitud'] + $p;
 		else
 			$coordenadasGeograficas[$i]['longitud'] = $coordenadas['longitud'] - $p;
 							
 			$coordenadasGeograficas[$i]['latitud'] = FRONTERA_LATITUD - $r;
 		// Calculo de la altitud
 			$coordenadasGeograficas[$i]['altura'] = $flm;
 	}
}

/**
 * Funcion auxiliar que permite al usuario ajustar la distancia maxima permitda entre los puntos de un obstaculo para determinar la precision de la 
 * representacion
 * @param unknown $radioTerrestreAumentado
 * @return number
 */
function calculoAnguloMaximoEntrePtos($radioTerrestreAumentado){
	
	$anguloMaximo =0;
	$long =0;
	
	echo "Dime, quieres modificar la distancia minima que debe haber entre ptos? (s/n) . Actualmente son 5 NM". PHP_EOL;
	$line = trim(fgets(STDIN));
	$line = strtolower($line);
	
	if ($line == "n")
		$long = DISTANCIA_ENTRE_PUNTOS;
		else{
			echo "introduce el nuevo valor en NM: " ;
			fscanf (STDIN, "%d\n", $long);
		}
			
		return $anguloMaximo = ($long * MILLA_NAUTICA_EN_METROS) / $radioTerrestreAumentado;
}



/**
 * Funcion que dada la lista de obstaculos y el radio terrestre interpola segun el caso en el que se encuentre y nos devuelve la lista de obstaculos ampliada
 * @param unknown $listaObstaculos (representa un rango de interpolacion)
 * @param unknown $radioTerrestreAumentado
 * @param unknown $casos
 * @return unknown[]
 */
function interpolarPtosTerreno($listaObstaculos, $radioTerrestreAumentado, $casos){

	$diferencia = 0; $anguloNuevoPto = 0; $alturaNuevoPto = 0;
	$listaObstaculosAmpliada = array();
	$PtoNuevo = array ('angulo' => 0, 'altura' => 0, 'estePtoTieneCobertura' => false); // inicializamos el punto nuevo

	// PEDIR AL USUARIO LA DISTANCIA QUE TIENE QUE HABER ENTRE LOS PTOS
	//$anguloMaximo=calculoAnguloMaximoEntrePtos($radioTerrestreAumentado);
	
	$anguloMaximo = (DISTANCIA_ENTRE_PUNTOS * MILLA_NAUTICA_EN_METROS) / $radioTerrestreAumentado; // paso  0.5NM a grados
	$n = count($listaObstaculos); // obtenemos la long de la lista obstaculos del azimuts  
    
	for ($i=0; $i < $n-1 ; $i++){// recorremos la lista de obstaculos del azimut para ver donde tenemos q insertar puntos nuevos (MENOS EL ULTIMO OBSTACULO!)
		
		if ($casos == 1)
			$listaObstaculosAmpliada[] = $listaObstaculos[$i]; // copiamos el punto original

		$diferencia = $listaObstaculos[$i+1]['angulo'] -  $listaObstaculos[$i]['angulo'];
		//echo "DIFERENCIA: " . $diferencia. PHP_EOL;
		//echo "ANGULO MAXIMO: " . $anguloMaximo. PHP_EOL;
		
		if ($diferencia > $anguloMaximo){// es necesario interpolar
			$ptosQueMeter = round(($listaObstaculos[$i+1]['angulo'] -  $listaObstaculos[$i]['angulo']) / $anguloMaximo);
			//echo " PTOS QUE METER: " . $ptosQueMeter .PHP_EOL;
			$distancia = ($listaObstaculos[$i+1]['angulo'] -  $listaObstaculos[$i]['angulo']) / ($ptosQueMeter+1); // se le suma una xq son segmentos
					
			for ($j =0; $j< $ptosQueMeter; $j++){ // creamos los ptos
				// obtenemos el angulo nuevo
				$anguloNuevoPto = $listaObstaculos[$i]['angulo'] +  ($j+1) * $distancia;
					
				// obtenemos la altura nueva
				if($casos == 1){ // zona de terreno
					$alturaNuevoPto = ((($listaObstaculos[$i+1]['altura'] -  $listaObstaculos[$i]['altura']) / 
						($listaObstaculos[$i+1]['angulo'] -  $listaObstaculos[$i]['angulo']))* ($anguloNuevoPto-$listaObstaculos[$i]['angulo']))
						+  $listaObstaculos[$i]['altura'];
						
					$PtoNuevo = array('angulo' => $anguloNuevoPto, 'altura' => $alturaNuevoPto, 'estePtoTieneCobertura' => false);
				}
				elseif ($casos == 2){ // zona de sombra
					$alturaNuevoPto = 0;
					$PtoNuevo = array('angulo' => $anguloNuevoPto, 'altura' => $alturaNuevoPto, 'estePtoTieneCobertura' => false);
					//echo "ANGULO SOMBRA: " . $anguloNuevoPto. PHP_EOL;
				}
				elseif ($casos == 3){ // zona luz
					$alturaNuevoPto = 0;
					$PtoNuevo = array('angulo' => $anguloNuevoPto, 'altura' => $alturaNuevoPto, 'estePtoTieneCobertura' => true);
				}
				$listaObstaculosAmpliada[] = $PtoNuevo; //  metemos el punto nuevo que acabamos de crear al final de la lista de obstaculos Ampliada
	 		}// for interno
		}//if
	}// for externo
	$listaObstaculosAmpliada[] = $listaObstaculos[$n-1];
	return $listaObstaculosAmpliada;
}

/**
 * Funcion que modifica el parametro nuevo dependiendo de si se cumple la condicion que determina si hay o no cobertura
 * @param array $listaObstaculosAmpliada
 * @param int $flm
 */
function miraSiHayCobertura(&$listaObstaculosAmpliada, $flm){
	
	$n = count($listaObstaculosAmpliada);
	//echo "El tamanio de la lista de obstaculos ampliada es: " . $n . PHP_EOL; 
	for ($i=0; $i<$n; $i++){
		//echo "i: " . $i. PHP_EOL;
		//echo "altura ".$listaObstaculosAmpliada[$i]['altura']. PHP_EOL;
		//echo "angulo ".$listaObstaculosAmpliada[$i]['angulo']. PHP_EOL;
		//echo "z ".$listaObstaculosAmpliada[$i]['estePtoTieneCobertura']. PHP_EOL. PHP_EOL;
		if ($listaObstaculosAmpliada[$i]['altura'] <  (double)$flm){ // doble < integer  
			$listaObstaculosAmpliada[$i]['estePtoTieneCobertura'] = true; 
			//echo "este pto tiene cob: ". $listaObstaculosAmpliada[$i]['estePtoTieneCobertura'].PHP_EOL;
		}
		else{
			$listaObstaculosAmpliada[$i]['estePtoTieneCobertura'] = false;
			//echo "este pto tiene cob: ". $listaObstaculosAmpliada[$i]['estePtoTieneCobertura'].PHP_EOL;
		}		
	}
}

/**
 * Funcion auxiliar que calcula una serie de parametros necesarios en otras funciones
 * Entrada:
 * @param aray $radar
 * @param array $listaObstaculos
 * @param double $radioTerrestreAumentado
 * @param int $flm
 * @param double $obstaculoLimitante
 * Salida:
 * @param double $gammaMax
 * @param double $theta0
 * @param double $earthToRadar
 * @param double $earthToEvalPoint
 * @param double $earthToFl
 */
function calculador($radar,$listaObstaculos, $radioTerrestreAumentado, $flm, $obstaculoLimitante, &$gammaMax, &$theta0, &$earthToRadar, &$earthToEvalPoint, &$earthToFl, &$radarSupTierra){
	
	$radarSupTierra = $radar['towerHeight'] + $radar['terrainHeight'];  // distancia del radar a la superficie terrestre
	//echo "Distacia Radar Sup terrestre: " . $radarSupTierra. PHP_EOL;
	$earthToRadar = $radar['towerHeight'] + $radar['terrainHeight'] + $radioTerrestreAumentado;
	//echo "EARTH TO RADAR: " . $earthToRadar. PHP_EOL;
	$earthToEvalPoint = $radioTerrestreAumentado + $obstaculoLimitante;
	$earthToFl = $radioTerrestreAumentado + $flm;
	
	$n = count ($listaObstaculos);
	$distanciaUltimoPto = $radioTerrestreAumentado * $listaObstaculos[$n-1]['angulo'];
	
	$distanciaCobertura = sqrt( pow($earthToRadar,2) + pow($earthToEvalPoint,2) - 2 * $earthToRadar * $earthToEvalPoint * cos($listaObstaculos[$n-1]['angulo'])); // angulo en radianes del ultimo pto del acimut
	$gammaMax = acos((pow($distanciaCobertura,2) +  pow($earthToRadar,2) - pow($earthToEvalPoint,2)) / (2 * $earthToRadar * $distanciaCobertura));
	
	$theta0 = $earthToRadar * sin($gammaMax) / $earthToFl;
	//echo "THETA 0: " . $theta0. PHP_EOL;
}


/**
 * Funcion auxiliar para obtener los angulos epsilon1 y epsilon2 que nos permiten calcular los puntos de corte
 * Entrada: 
 * @param double $earthToRadar
 * @param double $gammaMax
 * @param double $earthToFl
 * @param double $radioTerrestreAumentado
 * Salida:
 * @param double $epsilon1
 * @param double $epsilon2
 * @param array $ptosCorte
 */


function obtenerPtosCorte($earthToRadar, $gammaMax, $earthToFl, $radioTerrestreAumentado, &$epsilon1, &$epsilon2, &$ptosCorte){
		
	$epsilon1 =0;
	$epsilon2=0;
	$ptosCorte = array(); // array de dos posiciones
	
	$numerador = $earthToRadar * sin($gammaMax);
	$denominador = $earthToFl;
	
	if ($numerador > $denominador)
		$theta1 =0;
	else 
		$theta1 = asin ($numerador / $denominador);
	
	$epsilon1 = PI - $theta1 - $gammaMax;
	$epsilon2 = PI - (PI-$theta1) - $gammaMax;
	
	//echo "EPSILON 1: ". $epsilon1. PHP_EOL;
	//echo "EPSILON 2: ". $epsilon2. PHP_EOL;
	
	$ptosCorte[0] = $epsilon1 * $radioTerrestreAumentado;
	$ptosCorte[1] = $epsilon2 * $radioTerrestreAumentado;	
}


/** CASO 2 
 * Funcion que calcula las coberturas cuando el nivel de vuelo FL, esta por debajo del radar
 * @param array $radar
 * @param double $flm
 * @param double $radioTerrestreAumentado
 * @param double $anguloMaxCob
 */
			
function calculosFLdebajoRadar(&$radar, $flm, $radioTerrestreAumentado){
	
	$anguloMaxCob = calculaAnguloMaximaCobertura($radar, $radioTerrestreAumentado, $flm);
	 $X = (0.1 *  MILLA_NAUTICA_EN_METROS)/ (RADIO_TERRESTRE * (4/3)); // angulo (en radianes) entre el ultimo pto de cada acimut y el pto extra para una distancia de 0.1 NM
	
	$ptosNuevos = array();
	$ptoExtra = array( 'angulo' => 0, 'altura' => 0, 'estePtoTieneCobertura' => false);// inicializamos 
	$ptoMaxCob = array('angulo'=> $anguloMaxCob, 'altura'=> 0,'estePtoTieneCobertura'=> true);
	//echo "ANGULO MAX COB:" . $anguloMaxCob. PHP_EOL;
	$x = rad2deg($X); // pequeña distancia que se le suma al angulo de cada punto [0.1 NM] para añadir un ptoExtra y poder aproximar el mallado
	//echo " ESTO ES LO QUE QUIERO VERRRRRR  :" . $x. PHP_EOL;
	for ($i=0; $i < $radar['totalAzimuths']; $i++){ // recorremos todos los azimuts ($i=0; $i < $radar['totalAzimuths']; $i++) 
	 	
	 	//echo "   AZIMUT ". $i. PHP_EOL;
	 	//echo " LISTA ANTES DE INTERPOLAR: " . count($radar['listaAzimuths'][$i]). PHP_EOL;
	 	$listaObstaculosAmpliada = interpolarPtosTerreno($radar['listaAzimuths'][$i], $radioTerrestreAumentado, 1); //interpolamos puntos terreno
	 	//echo " LISTA DESPUES DE METER LOS PTOS TERRENO: " . count($listaObstaculosAmpliada).PHP_EOL; 
	 	miraSiHayCobertura($listaObstaculosAmpliada, $flm);
	 	
	 	// se calcula el punto limitante
	 	$tamaño = count ($listaObstaculosAmpliada);
	 	$numPtosAzimut = count ($radar['listaAzimuths'][$i]);
	 	$obstaculoLimitante = $radar['listaAzimuths'][$i][$numPtosAzimut-1]['altura'];
	 	$anguloLimitante = $radar['listaAzimuths'][$i][$numPtosAzimut-1]['angulo'];
	 	$ptoLimitante = array ('angulo'=>$anguloLimitante, 'altura'=>$obstaculoLimitante, 'estePtoTieneCobertura' =>true);
	 	//echo "OBSTACULO LIMITANTE: " . $obstaculoLimitante. PHP_EOL;
	 	calculador($radar, $listaObstaculosAmpliada, $radioTerrestreAumentado, $flm, $obstaculoLimitante, $gammaMax, $theta0, $earthToRadar, $earthToEvalPoint, $earthToFl,$radarSupTierra);
	 	
	 	// CASO A
	 	if(($obstaculoLimitante < $flm) && ($obstaculoLimitante < $radarSupTierra)){
	 		
	 		//echo "ESTAMOS EN EL CASO A" .PHP_EOL;
	 		//echo PHP_EOL;
	 			if ((abs($theta0)) <= 1){
	 				//echo "THETA 0 <= 1". PHP_EOL;
	 				obtenerPtosCorte($earthToRadar, $gammaMax, $earthToFl, $radioTerrestreAumentado, $epsilon1, $epsilon2, $ptosCorte);
	 				$ptoUno = array('angulo' => $epsilon1, 'altura'=> 0, 'estePtoTieneCobertura'=> true);  
	 				//echo "ANGULO MAX COB : " . $anguloMaxCob. PHP_EOL;
	 				//echo "EPSILON 1: " . $epsilon1. PHP_EOL;
	 				if ($epsilon1 < $anguloMaxCob){
	 					$rangoLuz =  array ($ptoLimitante, $ptoUno); //  se interpola desde el ultimo punto del terreno hasta el punto 1
	 					$ptosLuz = interpolarPtosTerreno($rangoLuz, $radioTerrestreAumentado, 3);// devuelve una lista con los puntos nuevos que se han interpolado
	 					$listaObstaculosAmpliada = array_merge($listaObstaculosAmpliada, $ptosLuz);
	 					//echo " LISTA DESPUES DE METER LOS PTOS LUZ: " . count($listaObstaculosAmpliada).PHP_EOL;
	 					$ptoExtra = array ('angulo' => ($epsilon1 + $x), 'altura' => 0, 'estePtoTieneCobertura' => false);
	 					$listaObstaculosAmpliada= array_merge($listaObstaculosAmpliada,array($ptoExtra));
	 					//echo " LISTA DESPUES DE METER LOS PTO EXTRA: " . count($listaObstaculosAmpliada).PHP_EOL;
	 				}
	 				else{
	 					//echo "ESTAMOS EN EL ELSE DEL CASO A". PHP_EOL;
	 					$rangoLuz =  array ($ptoLimitante, $ptoMaxCob); 
	 					$ptosLuz = interpolarPtosTerreno($rangoLuz, $radioTerrestreAumentado, 3);
	 					$listaObstaculosAmpliada = array_merge($listaObstaculosAmpliada, $ptosLuz);
	 					//echo " LISTA DESPUES DE METER LOS PTOS LUZ: " . count($listaObstaculosAmpliada).PHP_EOL;
	 					$ptoExtra = array ('angulo' => ($anguloMaxCob + $x), 'altura' => 0, 'estePtoTieneCobertura' => false);
	 					$listaObstaculosAmpliada= array_merge($listaObstaculosAmpliada,array($ptoExtra));
	 					//echo " LISTA DESPUES DE METER LOS PTO EXTRA: " . count($listaObstaculosAmpliada).PHP_EOL;
	 				}
	 			}
	 			
	 	elseif (abs($theta0) > 1){
	 		//echo "THETA 0 > 1 del caso A". PHP_EOL;
	 		//echo PHP_EOL;
	 		$ptoExtra = array ('angulo' => ($anguloLimitante + $x), 'altura' => 0, 'estePtoTieneCobertura' => false);
	 		$listaObstaculosAmpliada= array_merge($listaObstaculosAmpliada,array($ptoExtra));
	 		//echo " LISTA DESPUES DE METER LOS PTO EXTRA: " . count($listaObstaculosAmpliada).PHP_EOL;
	 	}
	  }// fin if caso A
	  
	   // CASO B
	  elseif (($obstaculoLimitante > $flm) && ($radarSupTierra > $obstaculoLimitante)){
	 			//echo "ESTAMOS EN EL CASO B" .PHP_EOL;
	 			if ((abs($theta0)) <= 1){
	 				//echo "THETA 0 <= 1". PHP_EOL;
	 				// calcular epsilon 1 y 2
	 				obtenerPtosCorte($earthToRadar, $gammaMax, $earthToFl, $radioTerrestreAumentado, $epsilon1, $epsilon2, $ptosCorte);
	 				$ptoUno = array ('angulo'=> $epsilon1,'altura'=> 0, 'estePtoTieneCobertura'=> true); // epsilon1
	 				$ptoDos = array ('angulo'=> $epsilon2,'altura'=> 0, 'estePtoTieneCobertura'=> true);// epsilon2
	 				// B.1
	 				if(($epsilon1 < $anguloMaxCob) && ($epsilon2 <$anguloMaxCob)){
	 					//echo "ESTAMOS EN EL CASO B.1" .PHP_EOL;
	 					// rango sombra
	 					$rangoSombra = array ($ptoLimitante, $ptoDos);
	 					//print_r($rangoSombra);
	 					$ptosSombra = interpolarPtosTerreno($rangoSombra, $radioTerrestreAumentado, 2);
	 					$listaObstaculosAmpliada = array_merge($listaObstaculosAmpliada, $ptosSombra);
	 					//echo " LISTA DESPUES DE METER LOS PTOS SOMBRA: " . count($listaObstaculosAmpliada).PHP_EOL;
	 					// Rango Luz.  Se itnerpola desde el punto 2 al punto 1
	 					$rangoLuz =  array ($ptoDos, $ptoUno); 
	 					//print_r($rangoLuz);
	 					$ptosLuz = interpolarPtosTerreno($rangoLuz, $radioTerrestreAumentado, 3);
	 					$listaObstaculosAmpliada = array_merge($listaObstaculosAmpliada, $ptosLuz);
	 					//echo " LISTA DESPUES DE METER LOS PTOS LUZ: " . count($listaObstaculosAmpliada).PHP_EOL;
	 					$ptoExtra = array ('angulo' => ($epsilon1 + $x), 'altura' => 0, 'estePtoTieneCobertura' => false); 
	 					$listaObstaculosAmpliada= array_merge($listaObstaculosAmpliada,array($ptoExtra));
	 					//echo " LISTA DESPUES DE METER LOS PTO EXTRA: " . count($listaObstaculosAmpliada).PHP_EOL;
	 			   } // if B.1
	 			   
	 			//B.2	 
	 			elseif (($epsilon1 > $anguloMaxCob) && ($epsilon2 < $anguloMaxCob)){
	 					$ptoDos = array ('angulo' => $epsilon2, 'altura' => 0,  'estePtoTieneCobertura'=> true);// epsilon2 
	 					//echo "ESTAMOS EN EL CASO B.2" .PHP_EOL;
	 					//echo PHP_EOL;
	 					// rango sombra
	 					$rangoSombra = array ($ptoLimitante, $ptoDos);
	 					//print_r($rangoSombra);
	 					$ptosSombra = interpolarPtosTerreno($rangoSombra, $radioTerrestreAumentado, 2);
	 					$listaObstaculosAmpliada = array_merge($listaObstaculosAmpliada, $ptosSombra);
	 					//echo " LISTA DESPUES DE METER LOS PTOS SOMBRA: " . count($listaObstaculosAmpliada).PHP_EOL;
	 					// Rango Luz. Se interpola desde el punto 2 al angulo de maxima cobertura (AlphRange)
	 					$rangoLuz =  array ($ptoDos, $ptoMaxCob); 
	 					//print_r($rangoLuz);
	 					$ptosLuz = interpolarPtosTerreno($rangoLuz, $radioTerrestreAumentado, 3);
	 					$listaObstaculosAmpliada = array_merge($listaObstaculosAmpliada, $ptosLuz);
	 					//echo " LISTA DESPUES DE METER LOS PTOS LUZ: " . count($listaObstaculosAmpliada).PHP_EOL;
	 					$ptoExtra = array ('angulo' => ($anguloMaxCob + $x), 'altura' => 0, 'estePtoTieneCobertura' => false); 
	 					$listaObstaculosAmpliada = array_merge($listaObstaculosAmpliada,array($ptoExtra));
	 					//echo " LISTA DESPUES DE METER LOS PTO EXTRA: " . count($listaObstaculosAmpliada).PHP_EOL;
	 					
	 			} // fin caso B.2
	 			
	 			// caso B.3
	 			elseif((($epsilon1 > $anguloMaxCob) && ($epsilon2 > $anguloMaxCob))){
	 					//echo "ESTAMOS EN EL CASO B.3" .PHP_EOL;
	 					//echo PHP_EOL;
	 					$ptoExtra = array ('angulo' => ($anguloLimitante + $x), 'altura' => 0, 'estePtoTieneCobertura' => false);
	 					$listaObstaculosAmpliada = array_merge($listaObstaculosAmpliada,array($ptoExtra));
	 					//echo " LISTA DESPUES DE METER LOS PTO EXTRA: " . count($listaObstaculosAmpliada).PHP_EOL;
	 			}
	  }
	 	 elseif (abs($theta0) > 1){
	 		//echo "THETA 0 > 1 del caso B". PHP_EOL;
	 		//echo PHP_EOL;
	 		$ptoExtra = array ('angulo' => ($anguloLimitante + $x), 'altura' => 0, 'estePtoTieneCobertura' => false); 
	 		$listaObstaculosAmpliada = array_merge($listaObstaculosAmpliada,array($ptoExtra));
	 		//echo " LISTA DESPUES DE METER LOS PTO EXTRA: " . count($listaObstaculosAmpliada).PHP_EOL;
	 	 }// Fin CASO B
	 	 
	 	 }	// CASO C
	 		elseif(($obstaculoLimitante > $flm) && ($radarSupTierra < $obstaculoLimitante)){
	 			//echo "ESTAMOS EN EL CASO C" .PHP_EOL;
	 			//echo PHP_EOL;
	 			$ptoExtra = array ('angulo' => ($anguloLimitante + $x), 'altura' => 0, 'estePtoTieneCobertura' => false); 
	 			$listaObstaculosAmpliada = array_merge($listaObstaculosAmpliada,array($ptoExtra));
	 			//echo " LISTA DESPUES DE METER LOS PTO EXTRA: " . count($listaObstaculosAmpliada).PHP_EOL;
	 		}
	 		//echo "AZIMUT = " . $i. PHP_EOL;
	 		//echo  "antes de la ampliacion " .count($radar['listaAzimuths'][$i]). PHP_EOL;
	 		$radar['listaAzimuths'][$i] = $listaObstaculosAmpliada; // metemos la lista de obstaculos nueva en la estructura 
	 		//echo  "despues de la ampliacion " . count($radar['listaAzimuths'][$i]). PHP_EOL;
	}//for				 
}
				
/**
 * Funcion que busca el punto más próximo al punto dado dentro de una lista de obstaculos comparando los angulos y devuelve la posicion de ese punto
 * Entradas: $lista obstaculos, $punto
 * Salidas: $posPunto
 */
function buscaDistanciaMenor($listaObstaculos, $punto){
	
	$posPunto = 0;
	$n = count ($listaObstaculos);
	//echo "SIZE LISTA OBSTACULOS AMPLIADA: " . $n. PHP_EOL; // ESTA MOSTRANDO EL TAMAÑO DE LA LISTA DE OBSTACULOS AMPLIADA  (GOOD)
	//echo "pto: " . $punto. PHP_EOL;
	//echo "lista de obstaculos " . PHP_EOL;
	//print_r($listaObstaculos);
	// miramos la diferencia con el primer punto para poder comparar 
    $min = abs($punto - $listaObstaculos[0]['angulo']);
   // echo "PUNTO : " . $punto . PHP_EOL;
   // echo "PRIMER OBSTACULO: " . $listaObstaculos[0]['angulo']. PHP_EOL;
   
    
	for ($i =0; $i< $n ; $i++){
		//echo " MIN: ". $min . PHP_EOL;
		//echo " VALOR ABSOLUTO DE LA DIFERENCIA: ". abs($punto - $listaObstaculos[$i]['angulo']). PHP_EOL;
		if(abs($punto - $listaObstaculos[$i]['angulo']) < $min){
		
			// si la diferencia es mas pequeña que el min anterior actualizamos min 
			$min = abs($punto - $listaObstaculos[$i]['angulo']);
			//echo "MIN DENTRO DEL IF: " . $min . PHP_EOL;
			$posPunto = $i; // me guardo el punto que tiene la distancia minima hasta el momento
			//echo "i: " . $i. PHP_EOL;
		}
	}
	//echo "POS PTO: " . $posPunto. PHP_EOL;
	return $posPunto; //  devolvemos la posicion del punto xq lo que nos interesa luego es mirar si tiene cobertura
}
/**
 * Dadas las coordenadas del pto central de una casilla, nos devuelve el acimut de la misma
 * @param float $x
 * @param float  $y
 */
function calculaAcimut($x, $y){
	
	$acimut =0;
	$acimutCelda =0;
	
	// DIFERENCIAMOS LOS CASOS SEGUN EL CUADRANTE
	
	if ($x < 0){
		//echo "ESTAMOS EN EL CASO C" . PHP_EOL;
		$acimut = rad2deg(atan($y/$x) + PI);
		//echo "ACIMUT : " . $acimut. PHP_EOL;
	}
	elseif($x > 0){
			if ($y < 0){
				//echo "ESTAMOS EN EL CASO E" . PHP_EOL;
				$acimut = rad2deg(atan($y/$x) + 2*PI);
				//echo "ACIMUT : " . $acimut. PHP_EOL;
			}
			else{ // $y>= 0
				//echo "ESTAMOS EN EL CASO A" . PHP_EOL;
				$acimut = rad2deg(atan($y/$x));
				//echo "ACIMUT : " . $acimut. PHP_EOL;
			}
	}
	elseif ($x == 0){
			if($y < 0){
				//echo "ESTAMOS EN EL CASO D" . PHP_EOL;
				$acimut = rad2deg((3*PI)/2);
				//echo "ACIMUT : " . $acimut. PHP_EOL;
			}
			elseif($y > 0){
				//echo "ESTAMOS EN EL CASO B" . PHP_EOL;
				$acimut = rad2deg(PI /2);
				//echo "ACIMUT : " . $acimut. PHP_EOL;
			}
	}
	
	$acimutCelda = 90 - $acimut;
	if ($acimutCelda < 0)
		$acimutCelda = $acimutCelda + 360;
		
	return $acimutCelda;
}

/**
 * Funcion que crea una malla de tamaño el doble del alcance del radar y la rellena con 0 o 1 en función de si el punto al que se aproxima el acimut de cada 
 * celda de la malla tiene o no cobertura.
 * 
 * @param  $radar
 * @param  $radioTerrestreAumentado
 * 
 * Devuelve la malla
 */
function generacionMallado($radar, $radioTerrestreAumentado, &$malla){
	
	// pasamos a  millas nauticas el rango del radar que esta almacenado en metros en la estructura radar
	$tamMalla = (( 2 * $radar['range'] ) / TAM_CELDA) / MILLA_NAUTICA_EN_METROS;
	//$tamMalla = 4;
	$xR= 0; // coordenada x del radar
	$yR =0; // coordenada y del radar
	$radioTerrestreAumentadoEnMillas  = $radioTerrestreAumentado / MILLA_NAUTICA_EN_METROS;
	
	$malla = array(); // creamos una malla vacia 
	$azimutTeorico =0; // azimut teorico calculado
	$azimutCelda =0; // azimut aproximado 
	$pos =0;
	
	// CENTRAMOS LA MALLA Y CALCULAMOS EL PTO MEDIO DE CADA CELDA
	
	for ($i =0; $i<$tamMalla; $i++){ // recorre las columnas de la malla 
		for ($j=0; $j<$tamMalla; $j++){ // recorre las filas de la malla 
			//echo PHP_EOL;
			//echo "i: " . $i.PHP_EOL;
			//echo "j: " . $j.PHP_EOL;
			// CALCULAMOS LAS COORDENADAS DE CADA CELDA
			$x = ($i * TAM_CELDA) - (($tamMalla /2) * TAM_CELDA) + (TAM_CELDA/2);
			$y = (($tamMalla /2) * TAM_CELDA) - ($j * TAM_CELDA) - (TAM_CELDA/2);
			//echo "x: " . $x. PHP_EOL;
			//echo "y: " . $y. PHP_EOL;
			// CALCULAMOS EL AZIMUT DE CADA CELDA Y APROXIMAMOS
		
			$azimutTeorico = calculaAcimut($x, $y); // grados GOOD
			//echo "azimut teorico: " . $azimutTeorico. PHP_EOL;
			if ($radar['totalAzimuths'] == 720){
				$azimutCelda = round($azimutTeorico*2) /2; // calculamos el azimut aproximado
				if ($azimutCelda == 720)
				 $azimutCelda = 719; // sobreescribimos el ultimo valor para no salirnos de rangos
			}
			else{ // tenemos 360 azimuts en total
			   $azimutCelda = round($azimutTeorico); // calculamos el azimut aproximado
			   if ($azimutCelda == 360)
			   		$azimutCelda = 359; 
			}
			//echo "azimut celda: " . $azimutCelda. PHP_EOL;
			// al dividir entre el radio tenemos el angulo deseado
			$distanciaCeldaAradar = (sqrt(pow(($xR- $x),2)+ pow(($yR - $y),2)) ) / $radioTerrestreAumentadoEnMillas;
			//echo "distancia Celda Radar : " . $distanciaCeldaAradar. PHP_EOL;
	
			// busca la posicion de la  distancia mas proxima en la lista de obstaculos del acimut aproximado (el menor)
			$pos = buscaDistanciaMenor($radar['listaAzimuths'][$azimutCelda], $distanciaCeldaAradar); 
				
			if (($radar['listaAzimuths'][$azimutCelda][$pos]['estePtoTieneCobertura']) === false){
				$malla[$i][$j] = 0;
				//echo "El pto aproximado no tiene cobertura". PHP_EOL;
			}
			else{
				$malla[$i][$j] = 1; // entiendase el valor 1 para representar el caso en el que  hay cobertura y 0 para lo contrario
				//echo "El pto aproximado  tiene cobertura". PHP_EOL;
			}	
			//echo "Malla :" . $malla[$i][$j]. PHP_EOL;
		}	
	}
}
/**
 * Genera una imagen en la que las zonas blancas tienen cobertura y las negras no a partir de la matriz binaria que se le pasa
 * @param unknown $malla
 * @param unknown $nombre
 * @return boolean
 */
function tratamientoMallado($malla, $nombre){
	 
	$sizeMalla = count($malla); 
	
	if ( ( $im = imagecreatetruecolor($sizeMalla, $sizeMalla) ) === false ) { // creamos la imagen "vacia"
		echo "error creando imagen"	. PHP_EOL;
		return false;
	}
	
 	if ( ( $blanco = imagecolorallocate( $im, 255, 255, 255 ) ) === false ) { // definimos el color blanco
 		echo "error definiendo color" . PHP_EOL;
 		return false;
 	}
	 	
 	for ($i=0; $i< $sizeMalla; $i++) { // recorremos la malla y coloreamos solo los pixeles que tengan cobertura
		for($j=0; $j<$sizeMalla; $j++) {
	 	
			if ($malla[$i][$j] == 1)
				imagesetpixel( $im, $i, $j, $blanco );
 		}//for interno
 	}//for externo
	 				
	if ( false === imagepng( $im, $nombre, 0) ) { // guardamos la imagen con los pixeles coloreados
		echo "Error al guardar la imagen" . PHP_EOL;
	}
	
	imagedestroy( $im ); // liberamos memoria
}



function esFrontera($malla, $i, $j) {

	if ( $malla[$i-1][$j] == 1 && $malla[$i+1][$j] == 1 && $malla[$i][$j-1] == 1 && $malla[$i][$j+1] == 1 ) {
		return true;
	} else {
		return false;
	   }
}

/**
 * Generamos una matriz con los contornos que encontramos en la malla binaria
 * @param unknown $malla
 * @param unknown $nuevaMalla
 */

function contornos($malla, &$mallaContornos){
	
	$mallaContornos = array();
	$sizeMalla = count($malla);

        // suavizado de malla para evitar casos que no se pueden resolver al calcular el borde xej: islas rodeadas
	for( $i = 1; $i < $sizeMalla - 1; $i++ ){  // recorrre las columnas
		for( $j = 1; $j < $sizeMalla - 1; $j++ ){ // recorre las filas 
	            if ( $malla[$i][$j] == 0 &&
	                $malla[$i+1][$j] == 1 &&
	                $malla[$i-1][$j] == 1 &&
    	                $malla[$i][$j+1] == 1 &&
	                $malla[$i][$j-1] == 1) {
	                $malla[$i][$j] = 1;
	            }
		}
	}
	pintaMalla($malla);
	
	for( $i = 0; $i < $sizeMalla; $i++ ){  // recorrre las columnas
		for( $j = 0; $j < $sizeMalla; $j++ ){ // recorre las filas 
	
			if ( $i == 0 || $j == 0 || $i == ($sizeMalla - 1) || $j == ($sizeMalla - 1) ) {
	
				$mallaContornos[$i][$j] = $malla[$i][$j];// pone los bordes
				continue;
			}
			if ( 1 == $malla[$i][$j] ) {
				if ( esFrontera($malla, $i, $j) ) { // mira si sus 4 vecinos tienen cobertura
					$mallaContornos[$i][$j] = 0; 
				} else {
					$mallaContornos[$i][$j] = 1; 
				   }
			 } else {
				$mallaContornos[$i][$j] = 0;
			   }
		}// for interno
	}// for externo
	//print_r($mallaContornos);
}


function isSafe($mallaContornos, $col, $fila, $visitados){ // he cambiado el orden de las filas y las col en todos los sitios
	
	$n = count ($mallaContornos);  
	
	if( (($col >= 0) && ($col < $n)) && (($fila >= 0) &&  ($fila < $n))  &&  ($mallaContornos[$col][$fila] == 1 && $visitados[$col][$fila] === false)) 
		return true;
	else 
		return false;
	
}

function dfs($mallaContornos, $col, $fila, &$visitados, &$listaPtos){
		
	$colNbr =  array (0, 1, 1, 1, 0, -1, -1, -1);  // i
	$rowNbr =  array (1, 1, 0, -1, -1, -1, 0, 1);  // j
	
	$visitados[$col][$fila] = true; // marcamos la casilla como visitada
	$listaPtos[] = array ('fila' => $fila, 'col' => $col, 'altura' => 0); // guardamos ese uno en la lista de puntos de esa isla
			 
	// recursion para todos los vecinos conectados
	$veces = 0;
	for ($k =0; $k < 8; $k++){
		if ( isSafe($mallaContornos, $col + $colNbr[$k], $fila + $rowNbr[$k], $visitados) ) {
		        $veces++;
			dfs($mallaContornos, $col + $colNbr[$k], $fila + $rowNbr[$k], $visitados, $listaPtos);
		}
	}
	if ( $veces == 1) {
	
	}
	
}
			
function cuentaIslas($mallaContornos, &$listaC){

	$visitados = array();	
	$tamMalla = count ($mallaContornos);
	
	// inicializamos a false la malla de visitados
	
	for($i=0; $i<$tamMalla; $i++) { // recorre las columnas 
		$visitados[$i] = array();
		for($j=0; $j<$tamMalla; $j++) { // recorre las filas 
			$visitados[$i][$j] = false;
		}
	}
	
	$numIslas = 0;
	
	// recorremos la malla que tiene los contornos 
	
	for ($i=0; $i < $tamMalla; $i++){   // recorre las columnas
		for ($j =0; $j < $tamMalla; $j++){  // recorre las filas 
			
			if ($mallaContornos[$i][$j] == 1 && $visitados[$i][$j] === false){  
				$listaPtos = array();
				dfs($mallaContornos,$i, $j, $visitados, $listaPtos); 
				$listaC[$numIslas] = $listaPtos;
				$numIslas++;
			}	
		}
	}
	//print_r($listaC);
	return $numIslas; // el numero de islas debe coincidir con el numero de elementos que tiene la listaC
}
 

function calculaCoordenadasGeograficasB($radar, $numIslas, $flm, $coordenadas, &$listaC){ // 
	
	$xR =0;
	$yR =0; 
	// pasamos a  millas nauticas el rango del radar que esta almacenado en metros en la estructura radar
	$tamMalla = (( 2 * $radar['range'] ) / TAM_CELDA) / MILLA_NAUTICA_EN_METROS;
	
	//echo "Num Islas: " . $numIslas. PHP_EOL;
	
	for($isla=0; $isla < $numIslas; $isla++){ // recorre la lista de islas
		//echo "ISLA: " . $isla. PHP_EOL;
		//echo PHP_EOL;
		$n = count($listaC[$isla]);
		//echo "ptos totales en la isla " . $isla. ": " . $n. PHP_EOL; 
		for($i=0; $i < $n; $i++){ // recorre la lista de puntos
			
			//echo "pto: " . $i. PHP_EOL;
			//echo PHP_EOL;
			//echo "fila: " . $listaC[$isla][$i]['fila']. PHP_EOL;
			//echo "col: " . $listaC[$isla][$i]['col']. PHP_EOL;
			
			 $x = ( ($listaC[$isla][$i]['col'] * TAM_CELDA) - (($tamMalla /2) * TAM_CELDA) + (TAM_CELDA/2) );  
			 $y = ( ( ($tamMalla /2) * TAM_CELDA) - ($listaC[$isla][$i]['fila'] * TAM_CELDA) - (TAM_CELDA/2) );
			
			//$x = - (($tamMalla /2) * TAM_CELDA) + (TAM_CELDA/2);
			//echo "X : " . $x. PHP_EOL;
			//$y = ( ($tamMalla /2) * TAM_CELDA) - (TAM_CELDA/2);
			//echo "Y : " . $y. PHP_EOL;
			// CALCULO DE LA DISTANCIA
			$distanciaCeldaAradar = (sqrt(pow(($xR- $x),2)+ pow(($yR - $y),2)) );
			//echo "distancia: " . $distanciaCeldaAradar. PHP_EOL;
			// CALCULO DEL ACIMUT
			$azimutTeorico = calculaAcimut($x, $y);
			//echo "Azimut Teorico: " . $azimutTeorico. PHP_EOL;
			
			// CALCULO DE LA LATITUD
			$anguloCentral = ($distanciaCeldaAradar * MILLA_NAUTICA_EN_METROS / RADIO_TERRESTRE);
			$latitudComplementaria = deg2rad(FRONTERA_LATITUD - $coordenadas['latitud']);
			$r = rad2deg(acos (cos($latitudComplementaria) * cos($anguloCentral) + sin($latitudComplementaria) * sin($anguloCentral)
				* cos(deg2rad($azimutTeorico)))); // tenemos r en grados
						
			// CALCULO DE LA LONGITUD
			$rEnRadianes = deg2rad($r);
			$numerador = cos($anguloCentral) - cos($latitudComplementaria) * cos($rEnRadianes);
			$denominador = sin($latitudComplementaria) * sin($rEnRadianes);
			
			if($numerador>$denominador)
				$p = 0;
			else
				$p =  rad2deg(acos($numerador/$denominador));
								
			// asignacion de valores a la estructura de datos
			if (round($azimutTeorico) < 180)
				$listaC[$isla][$i]['fila'] = $coordenadas['longitud'] + $p; 
			else
				$listaC[$isla][$i]['fila'] = $coordenadas['longitud'] - $p; 			
			$listaC[$isla][$i]['col'] = FRONTERA_LATITUD - $r; 
			$listaC[$isla][$i]['altura'] = $flm;
			
			
			 //echo PHP_EOL;
			 //echo "LONGITUD: " .$listaC[$isla][$i]['fila'] . PHP_EOL;
			 //echo "LATITUD: " .$listaC[$isla][$i]['col'] . PHP_EOL;
			 //echo "ALTURA: " .$listaC[$isla][$i]['altura'] . PHP_EOL;
			 //echo PHP_EOL;
		}
	}
}


function pintaMalla($malla) {

//    for($i=count($malla)-1;$i>=0;$i--) {
    for($i=0;$i<count($malla);$i++) {
        for($j=0;$j<count($malla);$j++) {
            print ($malla[$j][$i] == 1 ? "*" : " ");
        }
        print PHP_EOL;
    }
    print PHP_EOL;
    return;

}

function pintaMallaAlfabeto($malla) {

//    for($i=count($malla)-1;$i>=0;$i--) {
    for($i=0;$i<count($malla);$i++) {
        for($j=0;$j<count($malla);$j++) {
            print ($malla[$j][$i]);
        }
        print PHP_EOL;
    }
    print PHP_EOL;
    return;

}