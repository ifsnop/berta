<?php

CONST FRONTERA_LATITUD = 90; // latitud complementaria
CONST PIE_EN_METROS =  3.2808;
CONST CIEN_PIES = 100;
CONST PASO_A_GRADOS = 180;
CONST DISTANCIA_ENTRE_PUNTOS = 5; // maxima distancia q puede haber entre dos puntos de un acimut para saber si es necesario interpolar 
CONST TAM_CELDA = 2.5; // paso de la malla en NM 0.5 , 0.11 es lo mas que pequeño q no peta

//// CONSTANTES PARA LA DETECCION DE CONTRONOS /////
CONST NONE = 0;
CONST UP = 1;
CONST LEFT = 2;
CONST DOWN = 3;
CONST RIGHT = 4; 
/////////////////////////////////////////////


/**
 * Funion para convertir el nivel de vuelo dado en metros
 * 
 * ENTRADA: double $num dado en cientos de pies 
 * SAIDA:   number
 */
function fLtoMeters ($num){
	
	return $num = $num * CIEN_PIES / PIE_EN_METROS;
}

/**
 * Funcion para convertir el parametro dado en millas nauticas a metros
 * 
 * ENTRADA: double $num
 * SALIDA:  $num
 */
function NMtoMeters ($num){
	
	return $num = $num * MILLA_NAUTICA_EN_METROS;
}


 /**
  * Funcion que permite buscar los puntos limitantes necesarios para poder calcular la cobertura.
  * 
  * ENRADA: array $listaObstaculos
  * ENRADA: int $flm
  * ENTRADA/SALIDA: double $alturaPrimerPtoSinCob
  * ENTRADA/SALIDA: double $anguloPrimerPtoSinCob
  * ENTRADA/SALIDA: double $alturaUltimoPtoCob
  * ENTRADA/SALIDA: double $anguloUltimoPtoCob
  * SALIDA: boolean Devuelve true si encontrado o false en caso contrario
  */ 
function buscarPuntosLimitantes($listaObstaculos, $flm, &$alturaPrimerPtoSinCob, &$anguloPrimerPtoSinCob, &$alturaUltimoPtoCob, &$anguloUltimoPtoCob){
	
	$size = count($listaObstaculos); // da el tamaño de la lista de obstaculos para el acimut concreto
	
	$i=0;
	$enc = false;
	while ($i< $size && !$enc){
		
		if ($flm < $listaObstaculos[$i]['altura']){ // la primera vez que se cumple esto tenemos el primer punto sin cobertura 
		        if ( $i == 0 ) {
		            die("el primer obstaculo no tiene cobertura, no tenemos ultimo punto con cobertura, revisar para probar solucion.");
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
 * Funcion para calcular el angulo de maxima cobertura  (AlphRange)
 * 
 * ENTRADA: array $radar
 * ENTRADA: double $radioTerrestreAumentado
 * ENTRADA: double $flm
 * SALIDA:  $anguloMaxCob
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

/** CASO A
 *  Funcion que calcula las distancias a las que hay cobertura y los angulos de apantallamiento cuando el FL esta por encima del radar
 *  
 * ENTRADA: array $radar
 * ENTRADA: int $flm
 * ENTRADA: doble $radioTerrestreAumentado
 * ENTRADA/SALIDA: array $angulosApantallamiento
 * ENTRADA/SALIDA: array $distanciasCobertura
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
 * ENTRADA: array $radar 
 * ENTRADA:	array $coordenadas (grados decimales)
 * ENTRADA:	int	  $flm (metros)
 * ENTRADA:	array $distanciasCobertura (millas nauticas)
 * ENTRADA/SALIDA: 	array	$resultadosCoordenadas 
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
 * 
 * ENTRADA: number $radioTerrestreAumentado
 * SALIDA:  number $anguloMaximo
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
 * 
 * ENTRADA: array $listaObstaculos (representa un rango de interpolacion)
 * ENTRADA: float $radioTerrestreAumentado
 * ENTRADA: int $casos
 * SALIDA:  array $listaObstaculosAmpliada
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
 * Funcion que actualiza el parametro 'estePtoTieneCobertura'  dependiendo de si se cumple la condicion que determina si hay o no cobertura
 * 
 * ENTRADA: int $flm
 * ENTRADA/SALIDA:  array $listaObstaculosAmpliada
 */
function miraSiHayCobertura(&$listaObstaculosAmpliada, $flm){
		
	for ($i=0; $i<count($listaObstaculosAmpliada); $i++){

		if ($listaObstaculosAmpliada[$i]['altura'] <  (double)$flm){ // doble < integer  
			$listaObstaculosAmpliada[$i]['estePtoTieneCobertura'] = true; 	
		}
		else{
			$listaObstaculosAmpliada[$i]['estePtoTieneCobertura'] = false;
		}		
	}
}

/**
 * Funcion auxiliar que calcula una serie de parametros necesarios en otras funciones
 *
 * ENRADA: aray $radar
 * ENRADA: array $listaObstaculos
 * ENRADA: double $radioTerrestreAumentado
 * ENRADA: int $flm
 * ENRADA: double $obstaculoLimitante
 * ENRADA/SALIDA: double $gammaMax
 * ENRADA/SALIDA: double $theta0
 * ENRADA/SALIDA: double $earthToRadar
 * ENRADA/SALIDA: double $earthToEvalPoint
 * ENRADA/SALIDA: double $earthToFl
 */
function calculador($radar,$listaObstaculos, $radioTerrestreAumentado, $flm, $obstaculoLimitante, &$gammaMax, &$theta0, &$earthToRadar, &$earthToEvalPoint, &$earthToFl, &$radarSupTierra){
	
	$radarSupTierra = $radar['towerHeight'] + $radar['terrainHeight'];  // distancia del radar a la superficie terrestre

	$earthToRadar = $radar['towerHeight'] + $radar['terrainHeight'] + $radioTerrestreAumentado;

	$earthToEvalPoint = $radioTerrestreAumentado + $obstaculoLimitante;
	$earthToFl = $radioTerrestreAumentado + $flm;
	
	$n = count ($listaObstaculos);
	$distanciaUltimoPto = $radioTerrestreAumentado * $listaObstaculos[$n-1]['angulo'];
	
	$distanciaCobertura = sqrt( pow($earthToRadar,2) + pow($earthToEvalPoint,2) - 2 * $earthToRadar * $earthToEvalPoint * cos($listaObstaculos[$n-1]['angulo'])); // angulo en radianes del ultimo pto del acimut
	$gammaMax = acos((pow($distanciaCobertura,2) +  pow($earthToRadar,2) - pow($earthToEvalPoint,2)) / (2 * $earthToRadar * $distanciaCobertura));
	
	$theta0 = $earthToRadar * sin($gammaMax) / $earthToFl;

}


/**
 * Funcion auxiliar para obtener los angulos epsilon1 y epsilon2 que nos permiten calcular los puntos de corte
 *  
 * ENTRADA: double $earthToRadar
 * ENTRADA: double $gammaMax
 * ENTRADA: double $earthToFl
 * ENTRADA: double $radioTerrestreAumentado
 * ENTRADA/SALIDA: double $epsilon1
 * ENTRADA/SALIDA: double $epsilon2
 * ENTRADA/SALIDA: array $ptosCorte
 */
function obtenerPtosCorte($earthToRadar, $gammaMax, $earthToFl, $radioTerrestreAumentado, &$epsilon1, &$epsilon2, &$ptosCorte){
		
	$epsilon1 = 0;
	$epsilon2 = 0;
	$ptosCorte = array(); // array de dos posiciones
	
	$numerador = $earthToRadar * sin($gammaMax);
	$denominador = $earthToFl;
	
	if ($numerador > $denominador)
		$theta1 =0;
	else 
		$theta1 = asin ($numerador / $denominador);
	
	$epsilon1 = PI - $theta1 - $gammaMax;
	$epsilon2 = PI - (PI-$theta1) - $gammaMax;
	
	$ptosCorte[0] = $epsilon1 * $radioTerrestreAumentado;
	$ptosCorte[1] = $epsilon2 * $radioTerrestreAumentado;	
}


/** CASO B 
 * Funcion que calcula las coberturas cuando el nivel de vuelo FL, esta por debajo del radar
 * 
 * ENTRADA: double $flm
 * ENTRADA: double $radioTerrestreAumentado
 * ENTRADA/SALIDA: array $radar
 */
			
function calculosFLdebajoRadar(&$radar, $flm, $radioTerrestreAumentado){
	
	$anguloMaxCob = calculaAnguloMaximaCobertura($radar, $radioTerrestreAumentado, $flm);
	 $X = (0.1 *  MILLA_NAUTICA_EN_METROS)/ (RADIO_TERRESTRE * (4/3)); // angulo (en radianes) entre el ultimo pto de cada acimut y el pto extra para una distancia de 0.1 NM
	
	$ptosNuevos = array();
	$ptoExtra = array( 'angulo' => 0, 'altura' => 0, 'estePtoTieneCobertura' => false);// inicializamos 
	$ptoMaxCob = array('angulo'=> $anguloMaxCob, 'altura'=> 0,'estePtoTieneCobertura'=> true);
	
	$x = rad2deg($X); // pequeña distancia que se le suma al angulo de cada punto [0.1 NM] para añadir un ptoExtra y poder aproximar el mallado
	
	for ($i=0; $i < $radar['totalAzimuths']; $i++){ // recorremos todos los azimuts ($i=0; $i < $radar['totalAzimuths']; $i++) 
	 	
	 	$listaObstaculosAmpliada = interpolarPtosTerreno($radar['listaAzimuths'][$i], $radioTerrestreAumentado, 1); //interpolamos puntos terreno
	 	miraSiHayCobertura($listaObstaculosAmpliada, $flm);
	 	
	 	// se calcula el punto limitante
	 	$tamaño = count ($listaObstaculosAmpliada);
	 	$numPtosAzimut = count ($radar['listaAzimuths'][$i]);
	 	$obstaculoLimitante = $radar['listaAzimuths'][$i][$numPtosAzimut-1]['altura'];
	 	$anguloLimitante = $radar['listaAzimuths'][$i][$numPtosAzimut-1]['angulo'];
	 	$ptoLimitante = array ('angulo'=>$anguloLimitante, 'altura'=>$obstaculoLimitante, 'estePtoTieneCobertura' =>true);
	 	calculador($radar, $listaObstaculosAmpliada, $radioTerrestreAumentado, $flm, $obstaculoLimitante, $gammaMax, $theta0, $earthToRadar, $earthToEvalPoint, $earthToFl,$radarSupTierra);
	 	
	 	// CASO A
	 	if(($obstaculoLimitante < $flm) && ($obstaculoLimitante < $radarSupTierra)){
	 	
	 			if ((abs($theta0)) <= 1){
	 				
	 				obtenerPtosCorte($earthToRadar, $gammaMax, $earthToFl, $radioTerrestreAumentado, $epsilon1, $epsilon2, $ptosCorte);
	 				$ptoUno = array('angulo' => $epsilon1, 'altura'=> 0, 'estePtoTieneCobertura'=> true);  

	 				if ($epsilon1 < $anguloMaxCob){
	 					$rangoLuz =  array ($ptoLimitante, $ptoUno); //  se interpola desde el ultimo punto del terreno hasta el punto 1
	 					$ptosLuz = interpolarPtosTerreno($rangoLuz, $radioTerrestreAumentado, 3);// devuelve una lista con los puntos nuevos que se han interpolado
	 					$listaObstaculosAmpliada = array_merge($listaObstaculosAmpliada, $ptosLuz);
	 					$ptoExtra = array ('angulo' => ($epsilon1 + $x), 'altura' => 0, 'estePtoTieneCobertura' => false);
	 					$listaObstaculosAmpliada= array_merge($listaObstaculosAmpliada,array($ptoExtra));
	 				}
	 				else{
	 					$rangoLuz =  array ($ptoLimitante, $ptoMaxCob); 
	 					$ptosLuz = interpolarPtosTerreno($rangoLuz, $radioTerrestreAumentado, 3);
	 					$listaObstaculosAmpliada = array_merge($listaObstaculosAmpliada, $ptosLuz);
	 					$ptoExtra = array ('angulo' => ($anguloMaxCob + $x), 'altura' => 0, 'estePtoTieneCobertura' => false);
	 					$listaObstaculosAmpliada= array_merge($listaObstaculosAmpliada,array($ptoExtra));
	 				}
	 			}
	 			
	 	elseif (abs($theta0) > 1){
	 		$ptoExtra = array ('angulo' => ($anguloLimitante + $x), 'altura' => 0, 'estePtoTieneCobertura' => false);
	 		$listaObstaculosAmpliada= array_merge($listaObstaculosAmpliada,array($ptoExtra));
	 	}
	  }// fin if caso A
	  
	   // CASO B
	  elseif (($obstaculoLimitante > $flm) && ($radarSupTierra > $obstaculoLimitante)){
	 			
	 			if ((abs($theta0)) <= 1){
	 				obtenerPtosCorte($earthToRadar, $gammaMax, $earthToFl, $radioTerrestreAumentado, $epsilon1, $epsilon2, $ptosCorte);
	 				$ptoUno = array ('angulo'=> $epsilon1,'altura'=> 0, 'estePtoTieneCobertura'=> true); // epsilon1
	 				$ptoDos = array ('angulo'=> $epsilon2,'altura'=> 0, 'estePtoTieneCobertura'=> true);// epsilon2
	 				// B.1
	 				if(($epsilon1 < $anguloMaxCob) && ($epsilon2 <$anguloMaxCob)){
	 					
	 					// rango sombra
	 					$rangoSombra = array ($ptoLimitante, $ptoDos);
	 					$ptosSombra = interpolarPtosTerreno($rangoSombra, $radioTerrestreAumentado, 2);
	 					$listaObstaculosAmpliada = array_merge($listaObstaculosAmpliada, $ptosSombra);
	 					// Rango Luz.  Se itnerpola desde el punto 2 al punto 1
	 					$rangoLuz =  array ($ptoDos, $ptoUno); 
	 					$ptosLuz = interpolarPtosTerreno($rangoLuz, $radioTerrestreAumentado, 3);
	 					$listaObstaculosAmpliada = array_merge($listaObstaculosAmpliada, $ptosLuz);
	 					$ptoExtra = array ('angulo' => ($epsilon1 + $x), 'altura' => 0, 'estePtoTieneCobertura' => false); 
	 					$listaObstaculosAmpliada= array_merge($listaObstaculosAmpliada,array($ptoExtra));
	 			   } // if B.1
	 			   
	 			//B.2	 
	 			elseif (($epsilon1 > $anguloMaxCob) && ($epsilon2 < $anguloMaxCob)){
	 					$ptoDos = array ('angulo' => $epsilon2, 'altura' => 0,  'estePtoTieneCobertura'=> true);// epsilon2 
	 					// rango sombra
	 					$rangoSombra = array ($ptoLimitante, $ptoDos);
	 					$ptosSombra = interpolarPtosTerreno($rangoSombra, $radioTerrestreAumentado, 2);
	 					$listaObstaculosAmpliada = array_merge($listaObstaculosAmpliada, $ptosSombra);
	 					// Rango Luz. Se interpola desde el punto 2 al angulo de maxima cobertura (AlphRange)
	 					$rangoLuz =  array ($ptoDos, $ptoMaxCob); 
	 					$ptosLuz = interpolarPtosTerreno($rangoLuz, $radioTerrestreAumentado, 3);
	 					$listaObstaculosAmpliada = array_merge($listaObstaculosAmpliada, $ptosLuz);
	 					$ptoExtra = array ('angulo' => ($anguloMaxCob + $x), 'altura' => 0, 'estePtoTieneCobertura' => false); 
	 					$listaObstaculosAmpliada = array_merge($listaObstaculosAmpliada,array($ptoExtra));
	 			} // fin caso B.2
	 			
	 			// caso B.3
	 			elseif((($epsilon1 > $anguloMaxCob) && ($epsilon2 > $anguloMaxCob))){
	 					$ptoExtra = array ('angulo' => ($anguloLimitante + $x), 'altura' => 0, 'estePtoTieneCobertura' => false);
	 					$listaObstaculosAmpliada = array_merge($listaObstaculosAmpliada,array($ptoExtra));
	 			}
	  }
	 	 elseif (abs($theta0) > 1){
	 		$ptoExtra = array ('angulo' => ($anguloLimitante + $x), 'altura' => 0, 'estePtoTieneCobertura' => false); 
	 		$listaObstaculosAmpliada = array_merge($listaObstaculosAmpliada,array($ptoExtra));
	 	 }// Fin CASO B
	 	 
	 	 }// CASO C
	 		elseif(($obstaculoLimitante > $flm) && ($radarSupTierra < $obstaculoLimitante)){
	 			$ptoExtra = array ('angulo' => ($anguloLimitante + $x), 'altura' => 0, 'estePtoTieneCobertura' => false); 
	 			$listaObstaculosAmpliada = array_merge($listaObstaculosAmpliada,array($ptoExtra));
	 		}
	 		$radar['listaAzimuths'][$i] = $listaObstaculosAmpliada; // metemos la lista de obstaculos nueva en la estructura 
	}//for				 
}
				
/**
 * Funcion que busca el punto más próximo al punto dado dentro de una lista de obstaculos comparando los angulos y devuelve la posicion de ese punto
 * 
 * ENTRADA: array $lista obstaculos
 * ENTRADA: array $punto
 * SALIDA:  int   $posPunto
 */
function buscaDistanciaMenor($listaObstaculos, $punto){
	
	$posPunto = 0;
	
	// miramos la diferencia con el primer punto para poder comparar 
    $min = abs($punto - $listaObstaculos[0]['angulo']);
    
	for ($i =0; $i < count ($listaObstaculos) ; $i++){

		if(abs($punto - $listaObstaculos[$i]['angulo']) < $min){
			// si la diferencia es mas pequeña que el min anterior actualizamos min 
			$min = abs($punto - $listaObstaculos[$i]['angulo']);
			$posPunto = $i; // me guardo el punto que tiene la distancia minima hasta el momento
		}
	}
	return $posPunto; //  devolvemos la posicion del punto xq lo que nos interesa luego es mirar si tiene cobertura
}
/**
 * Dadas las coordenadas del pto central de una casilla, nos devuelve el acimut de la misma
 * 
 * ENTRADA: float $x
 * ENTRADA: float  $y
 * SALIDA:  int $acimutCelda
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
 * ENTRADA: array  $radar
 * ENTRADA: float  $radioTerrestreAumentado
 * ENTRADA/SALIDA: array $malla
 */
function generacionMallado($radar, $radioTerrestreAumentado, &$malla){
	
	// pasamos a  millas nauticas el rango del radar que esta almacenado en metros en la estructura radar
	$tamMalla = (( 2 * $radar['range'] ) / TAM_CELDA) / MILLA_NAUTICA_EN_METROS;
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
		
			// CALCULAMOS LAS COORDENADAS DE CADA CELDA
			$x = ($i * TAM_CELDA) - (($tamMalla /2) * TAM_CELDA) + (TAM_CELDA/2);
			$y = (($tamMalla /2) * TAM_CELDA) - ($j * TAM_CELDA) - (TAM_CELDA/2);
		
			// CALCULAMOS EL AZIMUT DE CADA CELDA Y APROXIMAMOS
			$azimutTeorico = calculaAcimut($x, $y); // grados GOOD
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
			// al dividir entre el radio tenemos el angulo deseado
			$distanciaCeldaAradar = (sqrt(pow(($xR- $x),2)+ pow(($yR - $y),2)) ) / $radioTerrestreAumentadoEnMillas;
			
			// busca la posicion de la  distancia mas proxima en la lista de obstaculos del acimut aproximado (el menor)
			$pos = buscaDistanciaMenor($radar['listaAzimuths'][$azimutCelda], $distanciaCeldaAradar); 
				
			if (($radar['listaAzimuths'][$azimutCelda][$pos]['estePtoTieneCobertura']) === false){
				$malla[$j][$i] = 0;
			}
			else{
				$malla[$j][$i] = 1; // entiendase el valor 1 para representar el caso en el que  hay cobertura y 0 para lo contrario
			}	
		}	
	}
}

/**
 * Funcion que crea una matriz con la matriz que le entra como parametro de entrada y la bordea con 0's 
 * ENTRADA: array $malla
 * SALIDA: array $mallaMarco
 */
function mallaMarco($malla){
	
	$mallaMarco= array();
	
	// creamos una malla mayor y la inicializamos a 0
	for($i =0; $i< count($malla)+2; $i++){
		for ($j =0; $j< count($malla)+2; $j++){
			$mallaMarco[$i][$j] = 0;
		}
	}
	
	// recorremos la malla y la copiamos en la malla de mayor tamaño 
	for($i =0; $i< count($malla); $i++){
		for ($j =0; $j< count($malla); $j++){
			if ($malla[$i][$j] == 1){
				$mallaMarco[$i+1][$j+1] = $malla[$i][$j];
			}
		}
	}
	return $mallaMarco;
}



/**
 * Genera una imagen en la que las zonas blancas tienen cobertura y las negras no a partir de la matriz binaria que se le pasa
 * 
 * ENTRADA: array $malla
 * ENTRADA string  $nombre
 * SALIDA: boolean, que nos ayuda a ver si la funcion ha tenido exito o no 
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





/////////////////////////////////////////////// DETECCION DE CONTORNOS (MARCHING SQUARES) ////////////////////////////////////////////////////////////// 

/**
 * Funcion que dada una malla y la posición de una fila, nos devuelve la fila en un array
 * 
 * ENTRADA int $y
 * ENTRADA aray $malla
 * SALIDA: array $rowData
 */
function getFila($y, $malla){
	
	$rowData = array();

	for ($j=0; $j<count($malla); $j++){
		$rowData[] = $malla[$y][$j];
	
	}
 // echo "rowData: " .print_r($rowData) . PHP_EOL;
 return $rowData;
}

/**
 * Funcion que copia una matriz en un vector
 * 
 * ENTRADA: array $malla
 * SALIDA: array  $vector
 */
function matrixToVector ($malla){
	
	$vector = array();
	
	for ($i=0; $i< count($malla); $i++){
		for ($j=0; $j< count($malla); $j++){
			$vector[] = $malla[$i][$j];
		}
	}
	return $vector;
}


 /**Busca el primer 1 en la malla para empezar a contornear
 *
 * ENTRADA: matriz $malla
 * ENTRADA/SALIDA: int $x
 * ENTRADA/SALIDA: int $y
 */
function getFirstPoint($malla, &$x, &$y){
	
 	$rowData = array();
	$fila = 0;
	$enc = false;
	$salir = false;
	
	while ($fila < count($malla) && !$salir){
		
		 $rowData = getFila($fila, $malla); // no quedamos con la fila de la matriz
		 $j = 0;
		 
		 while ($j < count($rowData) && !$enc){
		 	
		 	if ($rowData[$j] > 0){	
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
}	


/**
 * Busca unos en la matriz para poder detectar varios contornos si los hubiera
 * 
 * ENTRADA:  array $malla
 * ENTRADA/SALIDA: int $x
 * ENTRADA/SALIDA: int $y
 * ENTRADA/SALIDA: array $mallaVisitados
 */
function dameUno($malla, &$x, &$y, $mallaVisitados){
	
	$enc = false;
	$salir = false;
	$fila =0;
	
	while ($fila < count($malla) && !$salir){
		$col =0;
		while ($col < count($malla)  && !$enc){
			if ($malla[$fila][$col] > 0 && $mallaVisitados[$fila][$col] === false){
				$enc = true;
				$salir = true;
				$x = $col;
				$y= $fila ;
			}
			else{
				$col++;
			}
		}
		$fila++;
	}
}
	

/**
 * Funcion que deternina y establece un conjunto de 4 pixels que representan nuestro estado actual, para deerminar nuestra direccion actual y la siguiente
 * 
 * ENTRADA: int $index
 * ENTRADA: array $vector
 * ENTRADA: int $tamMalla
 * ENTRADA/SALIDA: int $nextStep
 * ENTRADA/SALIDA: int $state
 */
function step($index, $vector, $tamMalla, &$nextStep, &$state){
	
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
	if ($upLeft){
		$state = $state|1;
	}
	if ($upRight){
		$state = $state|2;
	}
	if ($downLeft){
		$state = $state|4;
	}
	if ($downRight){
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
	
	
/** Recorre la malla delineando el contorno desde el punto inicial que le entra por parametro.
 * 
 * ENTRADA: array $radar
 * ENTRADA: int      $startX
 * ENTRADA: int      $startY
 * ENTRADA: array    $vector
 * ENTRADA: int      $flm
 * ENTRADA: matrix   $malla
 * SALIDA:  array    $pointList, lista con los puntos del contorno
 */		
function walkPerimeter($radar, $startX, $startY, $malla, $vector, $flm){ // empezamos desde la primera posicion y recorremos la malla 
	
	// set up our return list
	$pointList = array();

	$x = $startX;
	$y = $startY;
	
	$sizeMalla = count ($malla);  
	
	// comprobamos que no nos salimos de la malla. NO DEBERIA SER NECESARIO
	if ($startX < 0) $startX = 0;  if ($startY < 0) $startY = 0; if ($startX > $sizeMalla) $startX = $sizeMalla; if ($startY > $sizeMalla) $startY = $sizeMalla;
	 
	do{
		// evaluate our state, and set up our next direction 
		$index = ($y-1) * $sizeMalla + ($x-1); // indexa el vector
		
		step($index, $vector, $sizeMalla, $nextStep, $state);
		
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
			case UP: $y --; break;
			case LEFT: $x--; break;
			case DOWN: $y++; break;
			case RIGHT: $x++; break;
			default : break;
		}
	 } while (($x != $startX || $y != $startY) && ($index < count($vector)));
	
	
	$pointList[] = array ('fila' => $y-1 , 'col'=> $x-1, 'altura' => $flm); // para que el contorno sea cerrado
	
	return $pointList;
	
}

/**
 * Funcion que determina cual es el menor punto de una fila 
 * 
 * ENTRADA: array $isla
 * SALIDA:  int $filaMin
 */
function buscaFilaMin($isla){
	
	$filaMin = 99999999999;

	for($i=0; $i< count($isla); $i++){
				
		if ($isla[$i]['fila'] < $filaMin){
			$filaMin = $isla[$i]['fila'];
		}
	}
	return $filaMin;
}

/**
 * Funcion que determina cual es el menor punto de una columna 
 * 
 * ENTRADA: array $isla
 * SALIDA:  int   $colMin
 */
function buscaColMin($isla){

	$colMin = 99999999999;

	for($i=0; $i< count($isla); $i++){

		if ($isla[$i]['col'] < $colMin){
			$colMin = $isla[$i]['col'];
		}
	}
	return $colMin;
}

/**
 * Funcion que determina cual es el mayor punto de una columna
 *
 * ENTRADA: array $isla
 * SALIDA:  int   $colMax
 */
function buscaColMax($isla){

	$colMax = 0;

	for($i=0; $i< count($isla); $i++){

		if ($isla[$i]['col'] > $colMax){
			$colMax = $isla[$i]['col'];
		}
	}
	return $colMax;
}

/**
 * Funcion que determina cual es el mayor punto de una fila
 *
 * ENTRADA: array $isla
 * SALIDA:  int $filaMax
 */
function buscaFilaMax($isla){

	$filaMax = 0;

	for($i=0; $i< count($isla); $i++){

		if ($isla[$i]['fila'] > $filaMax){
			$filaMax = $isla[$i]['fila'];
		}
	}
	return $filaMax;
}

/**
 * Funcion que determina si un punto dado se encuentra dentro de una lista de punto que represetna un poligono
 * 
 * ENTRADA: int $x
 * ENTRADA: int $y
 * ENTRADA: array $isla
 * SALIDA:  boolena $dentro
 */
function puntoEnPoligono($x, $y, $isla){

	$dentro = false;

	// Buscamos Xmin, Xmax, Ymin, Ymax
	$minX = buscaColMin($isla); // se le pasaba la isla
	//echo "Xmin: " . $minX. PHP_EOL;

	$minY = buscaFilaMin($isla);
	//echo "Ymin: " . $minY. PHP_EOL;
		
	$maxX = buscaColMax($isla);
	//echo "Xmax: " . $maxX. PHP_EOL;
		
	$maxY = BuscaFilaMax($isla);
	//echo "Ymax: " . $maxY. PHP_EOL;
		
	if ($x <= $minX || $x >= $maxX || $y <= $minY || $y >= $maxY)
		return false;

		for ( $i = 0, $j = count($isla)-1 ; $i < count($isla); $j = $i++ ){
			if ( ( $isla[$i]['fila'] >= $y ) != ($isla[ $j ]['fila'] >= $y ) &&
					$x <= ( $isla[ $j ]['col'] - $isla[ $i ]['col'] ) * ( $y - $isla[ $i ]['fila'] ) /
					( $isla[ $j ]['fila'] - $isla[ $i ]['fila'] ) + $isla[ $i ]['col'] ){
						$dentro = !$dentro;
							
			}
		}
		return $dentro;
}

function puntoEnPoligono2($x, $y, $isla){

	$point = array('col'=>$x,'fila'=>$y);

	$pointLocation = new pointLocation();
	
	$ret = $pointLocation->pointInPolygon($point, $isla);
	
	if ( "inside" == $ret)
		return true;
	else return false;

}

class pointLocation
{

	public function __construct(){
	}

	var $pointOnVertex = true; // Check if the point sits exactly on one of the vertices
	 

	public function pointInPolygon($point, $polygon, $pointOnVertex = true) {

		$this->pointOnVertex = $pointOnVertex;

		// Transform string coordinates into arrays with x and y values
		//$point = $this->pointStringToCoordinates($point);
		//$vertices = array();
		//foreach ($polygon as $vertex) {
		//	$vertices[] = $this->pointStringToCoordinates($vertex);
		//}

		$vertices = $polygon;
		
		// Check if the point sits exactly on a vertex
		if ($this->pointOnVertex == true and $this->pointOnVertex($point, $vertices) == true) {
			return "vertex";
		}

		// Check if the point is inside the polygon or on the boundary
		$intersections = 0;
		$vertices_count = count($vertices);

		for ($i=1; $i < $vertices_count; $i++) {
			$vertex1 = $vertices[$i-1];
			$vertex2 = $vertices[$i];
			
			if ($vertex1['fila'] == $vertex2['fila'] and $vertex1['fila'] == $point['fila'] and $point['col'] > min($vertex1['col'], $vertex2['col']) and $point['col'] < max($vertex1['col'], $vertex2['col'])) { // Check if point is on an horizontal polygon boundary
				return "boundary";
			}
			if ($point['fila'] > min($vertex1['fila'], $vertex2['fila']) and $point['fila'] <= max($vertex1['fila'], $vertex2['fila']) and $point['col'] <= max($vertex1['col'], $vertex2['col']) and $vertex1['fila'] != $vertex2['fila']) {
				$xinters = ($point['fila'] - $vertex1['fila']) * ($vertex2['col'] - $vertex1['col']) / ($vertex2['fila'] - $vertex1['fila']) + $vertex1['col'];
				if ($xinters == $point['col']) { // Check if point is on the polygon boundary (other than horizontal)
					return "boundary";
				}
				if ($vertex1['col'] == $vertex2['col'] || $point['col'] <= $xinters) {
					$intersections++;
				}
			}
		}
		// If the number of edges we passed through is even, then it's in the polygon.
		if ($intersections % 2 != 0) {
			return "inside";
		} else {
			return "outside";
		}
	}

	protected function pointOnVertex($point, $vertices) {
		foreach($vertices as $vertex) {
			if ($point == $vertex) {
				return true;
			}
		}

	}

	protected function pointStringToCoordinates($pointString) {
		$coordinates = explode(" ", $pointString);
		return array("x" => $coordinates[0], "y" => $coordinates[1]);
	}


}


/**
 * Funcion que determina los contornos de cobertura que hay en una matriz
 * 
 * ENTRADA: array $radar
 * ENTRADA: array $malla
 * ENTRADA: int $flm
 * ENTRADA/SALIDA: array $listaContornos
 */
 function determinaContornos($radar, $malla, $flm, &$listaContornos){
	
	$mallaNL = array();
	$listaContornos = array(); // creamos el array vacio
	
	
	// inicializamos la malla del siguiente nivel
	for ($i=0; $i< count($malla); $i++){ 
		for($j=0; $j< count($malla); $j++){
			$mallaNL[$i][$j] = $malla[$i][$j];
		}
	}
	
	for ($nivel = 0 ; $nivel < 1; $nivel++){ // porque solo queremos analizar tres niveles de profundidad
	echo "NiVEL: " . $nivel. PHP_EOL;
		
	// Actualizamos la malla del siguiente nivel
	
		 for ($i=0; $i< count($malla); $i++){ // NO TIENE SENTIDO EN EL NIVEL 0
			for($j=0; $j< count($malla); $j++){
				$malla[$i][$j] = $mallaNL[$i][$j];
			}
		 } 

		//pintaMalla($mallaNL); 

		$lista = array(); // creamos la lista
	
		for ($i=0; $i<count($malla); $i++){ 
			//echo "i: " .$i;
			for($j=0; $j<count($malla); $j++){
				//echo "i: " .$i. PHP_EOL;
				$puntoEnPoligono = false;
				
				for( $isla=0; $isla<count($lista); $isla++ ) { // recorre la lista de islas de ese nivel
					print $isla . "] " . count($lista[$isla]) . " ";
					if ( $isla!= 0) 
						continue;
					
					//print_r($lista[$isla]);
					//echo  "r: ". $r = puntoEnPoligono(5, 5, $isla). PHP_EOL;
					//print_r($isla);
					if ( puntoEnPoligono($i, $j, $lista[$isla]) ) { 
						
						$puntoEnPoligono = true;
						if ( $malla[$i][$j] == 1 ) {
							$mallaNL[$i][$j] = 0;
						} else { 
							$mallaNL[$i][$j] = 1;
						}
					}
				}
				print PHP_EOL;
				//print_r($lista);
				 // si el punto esta fuera del poligono copiamos el valor en la malla del siguiente nivel
				 // si el punto esta fuera y es un 1, significa que estamos ante un nuevo contorno
				//echo "malla: " . $malla[$i][$i]. PHP_EOL;
				
				if ( !$puntoEnPoligono ) {
					if ( $malla[$i][$j] == 1 ) {
						$contorno = marchingSquares($radar, $mallaNL, $flm, $i,$j); // nos da el contorno de una isla 
						// buscar repetidos 
						//echo "count(contorno)".count($contorno). PHP_EOL;
						$lista[] = $contorno; // si esto esta aquí , la lista contiene 20 veces el mismo contorno
						//if ($i == 5 && $j == 5){
							//echo " i=5, j=5 " .PHP_EOL;
							//echo "CONTORNO: ". print_r($contorno). PHP_EOL;
						//}
						//echo " count (lista): " . count($lista). PHP_EOL;
						$mallaNL[$i][$j]=0;
						//print_r($lista);
					} else { // si es un cero copiamos el valor
						$mallaNL[$i][$j] = $malla[$i][$j];
					} 
				}
			}// for interno 
		} // for externo
		// copiamos los contornos detectados en cada nivel en la lista de contornos totales
		for ($isla=0; $isla < count($lista); $isla++){
			$listaContornos[] = $lista[$isla];
		}
		
		//////////////////////////////////////////////////////////// metemos la lista en una malla///////////////////////////////////////
		$mallaContornos = array();
		for($a=0; $a< count($malla); $a++){
			for($b =0; $b < count($malla); $b++){
				$mallaContornos[$a][$b] =0;
			}
		}
		
		
		/* echo "count(contorno)".count($contorno). PHP_EOL;
		for ($z=0; $z < count($lista[0]); $z++){ // recorremos los puntos del primer contorno
			echo "z: " . $z. PHP_EOL;
			$mallaContornos[$lista[0][$z]['fila']][$lista[0][$z]['col']] = 1 ;
		} */
		///////////////////////////////////////////////////////////////////////////////////////////////////////
		if ($nivel ==0){
			tratamientoMallado($malla, "MALLA1.png");// genera una imagen de la malla grande
			tratamientoMallado($mallaContornos, "MallaContornos1.png"); 
		}
		if ($nivel ==1) {
			tratamientoMallado($malla, "MALLA2.png");
		
		}
		if($nivel ==2)
			tratamientoMallado($malla, "MALLA3.png");
	}// fin del  nivel de profundidad
	//print_r($listaContornos);
}

/**
 * Funcion que determina los contornos de cobertura que hay en una matriz
 * 
 * ENTRADA: array $radar
 * ENTRADA: array $malla
 * ENTRADA: int $flm
 * ENTRADA/SALIDA: array $listaContornos
 */
 function determinaContornosPablo($radar, $malla, $flm, &$listaContornos){
	
	$mallaNL = array();
	$listaContornos = array(); // creamos el array vacio
	
	// inicializamos la malla del siguiente nivel
	for ($i=0; $i< count($malla); $i++){ 
		for($j=0; $j< count($malla); $j++){
			$mallaNL[$i][$j] = $malla[$i][$j];
		}
	}
	
	for ($nivel = 0 ; $nivel < 1; $nivel++){ // porque solo queremos analizar tres niveles de profundidad
		echo "NiVEL: " . $nivel. PHP_EOL;
		
		// Actualizamos la malla del siguiente nivel
	
		 for ($i=0; $i< count($malla); $i++){
			for($j=0; $j< count($malla); $j++){
				$malla[$i][$j] = $mallaNL[$i][$j];
			}
		 } 

		 for ($i=0; $i< count($malla); $i++){ 
			for($j=0; $j< count($malla); $j++){
				$mallaNL[$i][$j] = 0;
			}
		 }

		//pintaMalla($mallaNL); 

		$lista = array(); // creamos la lista
	
		for ($i=0; $i<count($malla); $i++){ 
			//echo "i: " .$i;
			for($j=0; $j<count($malla); $j++){
				//echo "i: " .$i. PHP_EOL;
				$puntoEnPoligono = false;
				
				for( $isla=0; $isla<count($lista); $isla++ ) { // recorre la lista de islas de ese nivel
					//print $isla . "] " . count($lista[$isla]) . " ";
					//if ( $isla!= 0) 
						//continue;
					
					//print_r($lista[$isla]);
					//echo  "r: ". $r = puntoEnPoligono(5, 5, $isla). PHP_EOL;
					//print_r($isla);
					if ( puntoEnPoligono($i, $j, $lista[$isla]) ) { 
						
						$puntoEnPoligono = true;
						if ( $malla[$i][$j] == 1 ) {
							$mallaNL[$i][$j] = 0;
						} else { 
							$mallaNL[$i][$j] = 1;
						}
					}
				}
				print PHP_EOL;
				//print_r($lista);
				 // si el punto esta fuera del poligono copiamos el valor en la malla del siguiente nivel
				 // si el punto esta fuera y es un 1, significa que estamos ante un nuevo contorno
				//echo "malla: " . $malla[$i][$i]. PHP_EOL;
				
				if ( !$puntoEnPoligono ) {
					if ( $malla[$i][$j] == 1 ) {
						$contorno = marchingSquares($radar, $mallaNL, $flm, $i,$j); // nos da el contorno de una isla 
						// buscar repetidos 
						//echo "count(contorno)".count($contorno). PHP_EOL;
						$lista[] = $contorno; // si esto esta aquí , la lista contiene 20 veces el mismo contorno
						//if ($i == 5 && $j == 5){
							//echo " i=5, j=5 " .PHP_EOL;
							//echo "CONTORNO: ". print_r($contorno). PHP_EOL;
						//}
						//echo " count (lista): " . count($lista). PHP_EOL;
						$mallaNL[$i][$j]=0;
						//print_r($lista);
					} else { // si es un cero copiamos el valor
						$mallaNL[$i][$j] = $malla[$i][$j];
					} 
				}
			}// for interno 
		} // for externo
		// copiamos los contornos detectados en cada nivel en la lista de contornos totales
		for ($isla=0; $isla < count($lista); $isla++){
			$listaContornos[] = $lista[$isla];
		}
		
		//////////////////////////////////////////////////////////// metemos la lista en una malla///////////////////////////////////////
		$mallaContornos = array();
		for($a=0; $a< count($malla); $a++){
			for($b =0; $b < count($malla); $b++){
				$mallaContornos[$a][$b] =0;
			}
		}
		
		
		/* echo "count(contorno)".count($contorno). PHP_EOL;
		for ($z=0; $z < count($lista[0]); $z++){ // recorremos los puntos del primer contorno
			echo "z: " . $z. PHP_EOL;
			$mallaContornos[$lista[0][$z]['fila']][$lista[0][$z]['col']] = 1 ;
		} */
		///////////////////////////////////////////////////////////////////////////////////////////////////////
		if ($nivel ==0){
			tratamientoMallado($malla, "MALLA1.png");// genera una imagen de la malla grande
			tratamientoMallado($mallaContornos, "MallaContornos1.png"); 
		}
		if ($nivel ==1) {
			tratamientoMallado($malla, "MALLA2.png");
		
		}
		if($nivel ==2)
			tratamientoMallado($malla, "MALLA3.png");
	}// fin del  nivel de profundidad
	//print_r($listaContornos);
}


/**
 *  Determina y  establece el estado de 4 pixeles que representan nuestro estado actual y establece nuestra direccion actual y la siguiente
 * ENTRADA: array $radar
 * ENTRADA: array $malla, donde queremos encontrar los contornos
 * ENTRADA: int $flm
 * SALIDA: array $contorno
 */
 function marchingSquares($radar, $malla, $flm, $x,$y){ // 

	//$x =0; //
	//$y=0; // 
	$contorno = array();


	// Find the starting point
	//getFirstPoint($malla, $x,$y); // 

	$vector = matrixToVector($malla);

	// Return list of x and y positions
	$contorno = walkPerimeter($radar,$x, $y, $malla, $vector, $flm); // nos devuelve la isla
	//print_r($contorno);
	return $contorno;

} 
 
/**
 * Funcion que calcula las coordenadas geograficas para el caso B (fl debajo del radar)
 * 
 * ENTRADA: array    $radar
 * ENTRADA: int      $flm
 * ENTRADA: array    $coordenadas
 * ENTRDA/SALIDA: array $listaC
 */
function calculaCoordenadasGeograficasB($radar, $flm, $coordenadas, &$listaC){ 
	
	$xR =0;
	$yR =0; 
	// pasamos a  millas nauticas el rango del radar que esta almacenado en metros en la estructura radar
	$tamMalla = (( 2 * $radar['range'] ) / TAM_CELDA) / MILLA_NAUTICA_EN_METROS;
	
	for($isla=0; $isla < count($listaC); $isla++){ // recorre la lista de islas/ contornos

		$n = count($listaC[$isla]);
		
		for($i=0; $i < $n; $i++){ // recorre la lista de puntos del contorno
						
			 $x = ( (($listaC[$isla][$i]['col']-1) * TAM_CELDA) - (($tamMalla /2) * TAM_CELDA) + (TAM_CELDA/2) );  
			 $y = ( ( ($tamMalla /2) * TAM_CELDA) - (($listaC[$isla][$i]['fila']-1) * TAM_CELDA) - (TAM_CELDA/2) );

			// CALCULO DE LA DISTANCIA
			$distanciaCeldaAradar = (sqrt(pow(($xR- $x),2)+ pow(($yR - $y),2)) );
			
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

		}
	}
}

/**
 * Funcion auxiliar para representar por pantalla una matrix
 * 
 * ENTRADA: array $malla
 */
function pintaMalla($malla) {

    for($i=0;$i<count($malla);$i++) {
        for($j=0;$j<count($malla);$j++) {
            print ($malla[$j][$i] == 1 ? "1" : "0");
        }
        print PHP_EOL;
    }
    print PHP_EOL;
    return;

}

/**
 * Funcion auxiliar para reprensentar por pantalla una matriz con las letras del alfabeto
 * 
 * ENTRADA: array $malla
 */
function pintaMallaAlfabeto($malla) {

    for($i=0;$i<count($malla);$i++) {
        for($j=0;$j<count($malla);$j++) {
            print ($malla[$j][$i]);
        }
        print PHP_EOL;
    }
    print PHP_EOL;
    return;

}
