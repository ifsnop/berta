<?php

CONST FRONTERA_LATITUD = 90; // latitud complementaria
CONST PIE_EN_METROS =  3.2808;
CONST CIEN_PIES = 100;
CONST PASO_A_GRADOS = 180;
// maxima distancia q puede haber entre dos puntos de un acimut para saber si es necesario interpolar
CONST DISTANCIA_ENTRE_PUNTOS = 5; 
CONST TAM_CELDA = 0.5; // paso de la malla en NM 
CONST X = 0.1; // distancia entre el ultimo pto de cada acimut y el pto extra (NM)


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
	
	// Determinacion del paso (debe ser coherente en todas partes)
	if ($radar['totalAzimuths'] == TOTAL_AZIMUTHS)
		$paso = 1;
	else if ($radar['totalAzimuths'] == MAX_AZIMUTHS)
		$paso = 0.5;

	// Recorrido de los acimuts 
 	for ($i =0; $i< $radar['totalAzimuths']; $i++){
 		
 		// Calculo de la latitud
 		$anguloCentral = ($distanciasCobertura[$i] * MILLA_NAUTICA_EN_METROS / RADIO_TERRESTRE);
 		$latitudComplementaria = deg2rad(FRONTERA_LATITUD - $coordenadas['latitud']);
 		$r = rad2deg(acos (cos($latitudComplementaria) * cos($anguloCentral) + sin($latitudComplementaria) * sin($anguloCentral) * cos(deg2rad($i * $paso)))); // tenemos r en grados
 		
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

/* COPIA SEGURIDAD 
/**
 * Funcion que dada una lista de obstaculos para una acimut concreto, evalua si la distancia entre sus puntos 
 * es mayor que DISTANCIA_ENTRE_PTOS y en ese caso interpola linealmente para aumentar la densidad del fichero
 * y de esta manera la precision de la representacion.
 * 
 * Ademas se comparan los puntos con el nivel de vuelo, para saber si tienen cobertura o no 
 * 
 * @param array $listaObstaculos
 * @param int   $flm
 * @param int   $casos determina si se interpola de manera general o de forma concreta para los subcasos en los que el FL < hA
 *   
 
function interpolarPtosTerreno($listaObstaculos, $radioTerrestreAumentado, $casos){
	
	$diferencia =0;
	$anguloNuevoPto = 0;
	$alturaNuevoPto=0;
	$k=0; // recorre la lista de obstaculos ampliada
	$listaObstaculosAmpliada = array();
	
	// PEDIR AL USUARIO LA DISTANCIA QUE TIENE QUE HABER ENTRE LOS PTOS
	//$anguloMaximo=calculoAnguloMaximoEntrePtos($radioTerrestreAumentado);
	$anguloMaximo = rad2deg(DISTANCIA_ENTRE_PUNTOS/$radioTerrestreAumentado); // paso  5NM a grados
	
	$n = count($listaObstaculos); // obtenemos la long de la lista de azimuts
	
	for ($i=0; $i < $n-1 ; $i++){// recorremos los obstaculos del azimut
		//echo "PARA "."i=".$i. PHP_EOL;
		//echo "  ANGULO: " . $listaObstaculos[$i+1]['angulo']. PHP_EOL;
		$diferencia = $listaObstaculos[$i+1]['angulo'] -  $listaObstaculos[$i]['angulo']; 
		//echo "  DIFERENCIA:  " . $diferencia. PHP_EOL;
		if ($diferencia > $anguloMaximo){// es necesario interpolar 
			
			$ptosQueMeter = round(($listaObstaculos[$i+1]['angulo'] -  $listaObstaculos[$i]['angulo']) / $anguloMaximo);
			$arrayNuevosPtos = array();
			// calculamos la distancia que tiene que haber entre los puntos
			$distancia = ($listaObstaculos[$i+1]['angulo'] -  $listaObstaculos[$i]['angulo']) / ($ptosQueMeter+1); // se le suma una xq son segmentos
			
			for ($j =0; $j< $ptosQueMeter; $j++){ // creamos los ptos nuevos y los metemos en el array de nuevos puntos
				// obtenemos el angulo nuevo 
				$anguloNuevoPto = $listaObstaculos[$i]['angulo'] +  ($j+1)*$distancia;
				// obtenemos la altura nueva
				
				if($casos == 1){ // zona de terreno
					$alturaNuevoPto = ((($listaObstaculos[$i+1]['altura'] -  $listaObstaculos[$i]['altura']) / ($listaObstaculos[$i+1]['angulo'] -  $listaObstaculos[$i]['angulo']))* ($anguloNuevoPto-$listaObstaculos[$i]['angulo'])) +  $listaObstaculos[$i]['altura'];
					$arrayNuevosPtos[$j] = array('angulo' => $anguloNuevoPto, 'altura' => $alturaNuevoPto, 'estePtoTieneCobertura' => false);
				}
				elseif ($casos == 2){ // zona de sombra 
					$alturaNuevoPto = 0; // ALLOWED MEMORY SIZE !!!!!!!! 
					$arrayNuevosPtos[$j] = array('angulo' => $anguloNuevoPto, 'altura' => $alturaNuevoPto, 'estePtoTieneCobertura' => false);
				}
				elseif ($casos == 3){ // zona luz 
					$alturaNuevoPto = 0;
					$arrayNuevosPtos[$j] = array('angulo' => $anguloNuevoPto, 'altura' => $alturaNuevoPto, 'estePtoTieneCobertura' => true);
				}
			}
			// copiamos el punto anterior  
			$listaObstaculosAmpliada[$k] = $listaObstaculos[$i];
			// introducimos el array con los nuevos puntos en la lista ampliada
			array_splice($listaObstaculosAmpliada, $k+1, 0, $arrayNuevosPtos);
			$k = $k+ $ptosQueMeter+1;
		}
		else{ // si no hay que interpolar avanzamos los indices y copiamos los valores de una lista a la otra 
			$listaObstaculosAmpliada[$k] = $listaObstaculos[$i];
			$k++;
		}
	}
	// insertamos el ultimo valor 
	$listaObstaculosAmpliada[$k] = $listaObstaculos[$n-1];
	return $listaObstaculosAmpliada;
}

*/ 



/**
 * Funcion que dada la lista de obstaculos y el radio terrestre interpola segun el caso en el que se encuentre y nos devuelve la lista de obstaculos ampliada
 * @param unknown $listaObstaculos
 * @param unknown $radioTerrestreAumentado
 * @param unknown $casos
 * @return unknown[]
 */
function interpolarPtosTerreno($listaObstaculos, $radioTerrestreAumentado, $casos){

	$diferencia =0; $anguloNuevoPto = 0; $alturaNuevoPto=0;
	$listaObstaculosAmpliada = array();
	$PtoNuevo = array ('angulo' =>0, 'altura' =>0, 'estePtoTieneCobertura' => false);
	
	$k=0; // recorre la lista de obstaculos ampliada
	
	// PEDIR AL USUARIO LA DISTANCIA QUE TIENE QUE HABER ENTRE LOS PTOS
	//$anguloMaximo=calculoAnguloMaximoEntrePtos($radioTerrestreAumentado);
	
	$anguloMaximo = (DISTANCIA_ENTRE_PUNTOS * MILLA_NAUTICA_EN_METROS) / $radioTerrestreAumentado; // paso  5NM a grados
	// angulo maximo es la "distancia" 
	
	$n = count($listaObstaculos); // obtenemos la long de la lista de azimuts
    
	for ($i=0; $i < $n-1 ; $i++){// recorremos la lista de obstaculos del azimut
		$diferencia = $listaObstaculos[$i+1]['angulo'] -  $listaObstaculos[$i]['angulo'];
		
		if ($diferencia > $anguloMaximo){// es necesario interpolar
				
			$ptosQueMeter = round(($listaObstaculos[$i+1]['angulo'] -  $listaObstaculos[$i]['angulo']) / $anguloMaximo);
			$distancia = ($listaObstaculos[$i+1]['angulo'] -  $listaObstaculos[$i]['angulo']) / ($ptosQueMeter+1); // se le suma una xq son segmentos
				
			for ($j =0; $j< $ptosQueMeter; $j++){ // creamos los ptos
				// obtenemos el angulo nuevo
				$anguloNuevoPto = $listaObstaculos[$i]['angulo'] +  ($j+1)*$distancia;
				
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
				}
				elseif ($casos == 3){ // zona luz
					$alturaNuevoPto = 0;
					$PtoNuevo = array('angulo' => $anguloNuevoPto, 'altura' => $alturaNuevoPto, 'estePtoTieneCobertura' => true);	
				}
				array_splice($listaObstaculosAmpliada, $k+1, 0, array($PtoNuevo)); // introducimos el pto nuevo en la lista ampliada
				$k++; // avanzamos posicion en el array listaObstaculosAmpliada
			}
			// copiamos el punto anterior
			$listaObstaculosAmpliada[$k] = $listaObstaculos[$i]; 
			
		}
		else{ // si no hay que interpolar avanzamos los indices y copiamos los valores de una lista a la otra
			$listaObstaculosAmpliada[$k] = $listaObstaculos[$i];
			$k++;
		}
	}
	// insertamos el ultimo valor
	$listaObstaculosAmpliada[$k] = $listaObstaculos[$n-1];
	return $listaObstaculosAmpliada;
}

/**
 * Funcion que modifica el parametro nuevo dependiendo de si se cumple la condicion que determina si hay o no cobertura
 * @param array $listaObstaculosAmpliada
 * @param int $flm
 */
function miraSiHayCobertura(&$listaObstaculosAmpliada, $flm){
	
	$n = count($listaObstaculosAmpliada);
//	echo "El tamanio de la lista de obstaculos ampliada es: " . $n . PHP_EOL; 
	for ($i=0; $i<$n; $i++){
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
function calculador($radar,$listaObstaculos, $radioTerrestreAumentado, $flm, $obstaculoLimitante, &$gammaMax, &$theta0, &$earthToRadar, &$earthToEvalPoint, &$earthToFl){
	
	$earthToRadar = $radar['towerHeight'] + $radar['terrainHeight'] + $radioTerrestreAumentado;
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


/*
function obtenerPtosCorte ($listaObstaculos,$flm, $radioTerrestreAumentado, $obstaculoLimitante, $earthToRadar, $anguloMaxCob, &$ptosCorte, &$epsilon1, &$epsilon2){
	
	$ptosCorte = array(2);
	$theta0=0;
	$n = count ($listaObstaculos);
	//echo "N : " . $n . PHP_EOL;
	$earthToEvalPoint = $radioTerrestreAumentado + $obstaculoLimitante;
	$distanciaUltimoPto = $radioTerrestreAumentado * $listaObstaculos[$n-1]['angulo'];
	$earthToFl = $radioTerrestreAumentado + $flm;
	//echo "DISTANCIA ULTIMO PTO:" . $distanciaUltimoPto . PHP_EOL;
	
		$distanciaCobertura = sqrt( pow($earthToRadar,2) + pow($earthToEvalPoint,2) -
				2 * $earthToRadar * $earthToEvalPoint * cos($listaObstaculos[$n-1]['angulo'])); // angulo en radianes del ultimo pto del acimut
		
		// GAMMAMAX ES IGUAL EN EL CASO 1
		$gammaMax = acos((pow($distanciaCobertura,2) +  pow($earthToRadar,2) - pow($earthToEvalPoint,2)) / (2 * $earthToRadar * $distanciaCobertura)); 
		
		//echo " EARTH TO EVAL POINT: ". $earthToEvalPoint. PHP_EOL;
		//echo "DISTANCIA COBERTURA: ". $distanciaCobertura. PHP_EOL;
		//echo "DISTANCIA ULTIMO PUNTO: ". $distanciaUltimoPto. PHP_EOL;
		//echo " RADIO TERRESTRE AUMENTADO: ".  $radioTerrestreAumentado. PHP_EOL;
		//echo " OBSTACULO LIMITANTE: ". $obstaculoLimitante. PHP_EOL;
		$numerador = $earthToRadar * sin($gammaMax); 
		$denominador = $earthToFl; 
		echo "NUMERADOR: " . $numerador. PHP_EOL;
		echo "DENOMINADOR: ". $denominador. PHP_EOL;
		
		if ($numerador > $denominador) //  POR SEGURIDAD 
			$theta1 =0;
		else
			$theta1 =  asin ($numerador/ $denominador);
		
		 //echo " DENTRO DE LA FUNCION QUE LOS CALCULA:". PHP_EOL;
		 
		$epsilon1 = PI - $theta1 - $gammaMax;
		
		
		//echo " SENO DE GAMMA :". sin($gammaMax). PHP_EOL;
		//echo " EARTH TO RADAR :" . $earthToRadar. PHP_EOL;
		//echo " EARTH TO FL :". $earthToFl. PHP_EOL;
		echo "THETA 1 : ". $theta1. PHP_EOL; 
		
		echo PHP_EOL;
		
		echo "EPSILON 1: " . $epsilon1. PHP_EOL;
		echo PHP_EOL;
		
		$theta2 = PI - $theta1;
		echo "THETA 2 : ". $theta2. PHP_EOL;
		
		$epsilo2 = PI - $theta2 - $gammaMax;
		echo "EPSILON 2: " . $epsilon2. PHP_EOL;
		echo PHP_EOL;
		
		
		// proyeccion sobre el terreno del alcance radar 
		$sRange = $radioTerrestreAumentado * $anguloMaxCob / MILLA_NAUTICA_EN_METROS;
		
		// ESTAS ALMACENANDO UNA DISTANCIA NO UN PTO !!!!!! 
		$ptosCorte[0]= $epsilon1 * $radioTerrestreAumentado; // obtenemos el primer punto de corte y lo almacenamos en el array de salida 
		$ptosCorte[1]= $epsilon2 * $radioTerrestreAumentado; // obtenemos el segundo punto de corte y lo almacenamos en el array de salida 
		//echo "PTOS_CORTE[0] (epsilon1): " . $ptosCorte[0]. PHP_EOL;
		//echo "PTOS_CORTE[1] (epsilon2): " . $ptosCorte[1]. PHP_EOL;
		//echo "SALIMOS DE LA FUNCION QUE LOS CALCULA". PHP_EOL;
} 

*/

/** CASO 2 
 * Funcion que calcula las coberturas cuando el nivel de vuelo FL, esta por debajo del radar
 * @param array $radar
 * @param double $flm
 * @param double $radioTerrestreAumentado
 * @param double $anguloMaxCob
 */
function calculosFLdebajoRadar(&$radar, $flm, $radioTerrestreAumentado, $anguloMaxCob){
	
	$ptosNuevos = array();
	$ptoExtra = array( 'angulo' =>0, 'altura' =>0, 'estePtoTieneCobertura' => false);
	$ptoMaxCob = array('angulo'=>$anguloMaxCob, 'altura'=>0,'estePtoTieneCobertura'=> true);
	
	$x = rad2deg(X); // pequeña distancia que se le suma al angulo de cada punto [0.1 NM] para añadir un ptoExtra y poder aproximar el mallado

	 for ($i=0; $i < $radar['totalAzimuths']; $i++){// recorremos los acimuts
	 	
	 	//echo "AZIMUT ". $i. PHP_EOL;
	 	$listaObstaculosAmpliada =interpolarPtosTerreno($radar['listaAzimuths'][$i], $radioTerrestreAumentado, 1); //interpolamos puntos terreno
	 	miraSiHayCobertura($listaObstaculosAmpliada, $flm);
	 	$tamaño = count ($listaObstaculosAmpliada);
	 	$numPtosAzimut = count ($radar['listaAzimuths'][$i]);
	 	//echo "NUMERO DE PTOS DEL AZIMUT: " . $numPtosAzimut.PHP_EOL;
	 	
	 	$obstaculoLimitante = $radar['listaAzimuths'][$i][$numPtosAzimut-1]['altura'];
	 	//echo "OBSTACULO LIMITANTE: " . $obstaculoLimitante. PHP_EOL;
	 
	 	$anguloLimitante = $radar['listaAzimuths'][$i][$numPtosAzimut-1]['angulo'];
	 	//echo "ANGULO LIMITANTE: " . $anguloLimitante. PHP_EOL;
	 	$ptoLimitante = array ('angulo'=>$anguloLimitante, 'altura'=>$obstaculoLimitante, 'estePtoTieneCobertura' =>true); // TIENE COBERTURA ????
	 	
	 	calculador($radar, $listaObstaculosAmpliada, $radioTerrestreAumentado, $flm, $obstaculoLimitante, $gammaMax, $theta0, $earthToRadar, $earthToEvalPoint, $earthToFl);
	 	
	 		// CASO A
	 		if(($obstaculoLimitante < $flm) && ($obstaculoLimitante < $earthToRadar)){
	 		//	echo "ESTAMOS EN EL CASO A" .PHP_EOL;
	 			if ((abs($theta0)) <= 1){
	 			//	echo "THETA 0 <= 1". PHP_EOL;
	 				obtenerPtosCorte($earthToRadar, $gammaMax, $earthToFl, $radioTerrestreAumentado, $epsilon1, $epsilon2, $ptosCorte);
	 				$ptoUno = array('angulo' =>$epsilon1, 'altura'=>0, 'estePtoTieneCobertura'=>true); // epsilon 1 TIENE COBERTURA ???? 
	 				//echo "ANGULO MAX COB : " . $anguloMaxCob. PHP_EOL;
	 				//echo "EPSILON 1: " . $epsilon1. PHP_EOL;
	 				if ($epsilon1 < $anguloMaxCob){
	 					//echo "ESTAMOS EN EL IF DEL CASO A". PHP_EOL;
	 					// interpolamos
	 					$rangoInterpolacion =  array ($ptoLimitante, $ptoUno); //  se interpola desde el ultimo punto del terreno hasta el punto 1
	 					//echo "YA TENEMOS EL RANGO DE INTERPOLACION". PHP_EOL;
	 					$ptosNuevos = interpolarPtosTerreno($rangoInterpolacion, $radioTerrestreAumentado, 3);
	 					//echo "RANGO DE INTERPOLACION 0 (ptoLimitnate): " . $rangoInterpolacion[0]. PHP_EOL; //
	 						//echo "CAMPO 0 DEL ARRAY (angulo ptoLimitante): ".$rangoInterpolacion[0][0]. PHP_EOL;
	 						//echo "CAMPO 1 DEL ARRAY (altura ptoLimitante): ".$rangoInterpolacion[0][1]. PHP_EOL;
	 						//echo "CAMPO 2 DEL ARRAY (z ptoLimitante): ".$rangoInterpolacion[0][2]. PHP_EOL;
	 					//echo PHP_EOL;
	 					//echo "RANGO DE INTERPOLACION 1 (ptoUno): " . $rangoInterpolacion[1]. PHP_EOL; //  ARRAY 
	 						//echo "CAMPO 0 DEL ARRAY (angulo ptoUno): ".$rangoInterpolacion[1][0]. PHP_EOL; 
	 						//echo "CAMPO 1 DEL ARRAY (altura ptoUno): ".$rangoInterpolacion[1][1]. PHP_EOL;
	 					//echo "CAMPO 2 DEL ARRAY (z ptoUno): ".$rangoInterpolacion[1][2]. PHP_EOL;
	 				
	 					//echo "YA HEMOS INTERPOLADO". PHP_EOL;
	 			
	 					//echo "PTOS_NUEVOS: ". $ptosNuevos. PHP_EOL;
	 					array_splice($listaObstaculosAmpliada, $tamaño, 0, $ptosNuevos);
	 					//echo "YA HEMOS METIDO LOS PUNTOS NUEVOS". PHP_EOL;
	 					// añadimos el pto extra
	 					$tamaño = count ($listaObstaculosAmpliada);
	 					$ptoExtra = array ('angulo' => ($anguloLimitante + $x), 'altura' => 3, 'estePtoTieneCobertura' => false);
	 					//echo " YA HEMOS CREADO EL PTO EXTRA". PHP_EOL;
	 					array_splice($listaObstaculosAmpliada, $tamaño, 0, $ptoExtra);
	 					//echo "YA HEMOS METIDO EL PTO EXTRA". PHP_EOL;
	 				}
	 				else{
	 					//echo "ESTAMOS EN EL ELSE DEL CASO A". PHP_EOL;
	 					// interpolamos
	 					$rangoInterpolacion =  array ($ptoLimitante, $ptoMaxCob); 
	 					//echo "YA TENEMOS EL RANGO DE INTERPOLACION". PHP_EOL;
	 					//echo PHP_EOL;
	 					//echo "RANGO DE INTERPOLACION 0 (ptoLimitnate): " . $rangoInterpolacion[0]. PHP_EOL; //
	 						//echo "CAMPO 0 DEL ARRAY (angulo ptoLimitante): ".$rangoInterpolacion[0]['angulo']. PHP_EOL;
	 						//echo "CAMPO 1 DEL ARRAY (altura ptoLimitante): ".$rangoInterpolacion[0]['altura']. PHP_EOL;
	 						//echo "CAMPO 2 DEL ARRAY (z ptoLimitante): ".$rangoInterpolacion[0]['estePtoTieneCobertura']. PHP_EOL;
	 					//echo PHP_EOL;
	 					//echo "RANGO DE INTERPOLACION 1 (ptoMaxCob): " . $rangoInterpolacion[1]. PHP_EOL; //  ARRAY
	 						//echo "CAMPO 0 DEL ARRAY (angulo ptoMaxCob): ".$rangoInterpolacion[1]['angulo']. PHP_EOL;
	 						//echo "CAMPO 1 DEL ARRAY (altura ptoMaxCob): ".$rangoInterpolacion[1]['altura']. PHP_EOL;
	 						//echo "CAMPO 2 DEL ARRAY (z ptoMAXCob): ".$rangoInterpolacion[1]['estePtoTieneCobertura']. PHP_EOL;
	 					//echo PHP_EOL;
	 					$ptosNuevos = interpolarPtosTerreno($rangoInterpolacion, $radioTerrestreAumentado, 3);
	 					array_splice($listaObstaculosAmpliada, $tamaño, 0, $ptosNuevos);
	 					//echo "YA HEMOS METIDO LOS PUNTOS NUEVOS". PHP_EOL;
	 					// añadimos el pto extra
	 					$tamaño = count ($listaObstaculosAmpliada);
	 					$ptoExtra = array ('angulo' => ($anguloLimitante + $x), 'altura' => 3, 'estePtoTieneCobertura' => false);
	 					//echo "GENERAMOS EL PTO EXTRA: " . PHP_EOL;
	 					//echo "ANGULO PTO EXTRA : "  . ($anguloLimitante + $x). PHP_EOL;
	 					//echo "ALTURA PTO EXTRA: " . $ptoExtra['altura']. PHP_EOL;
	 					//echo "COB PTO EXTRA: " . $ptoExtra['estePtoTieneCobertura']. PHP_EOL;
	 					//echo " YA HEMOS CREADO EL PTO EXTRA". PHP_EOL;
	 					array_splice($listaObstaculosAmpliada, $tamaño, 0, array($ptoExtra));
	 					//echo PHP_EOL;
	 					//echo "YA HEMOS METIDO EL PTO EXTRA". PHP_EOL;
	 					//echo "ANGULO PTO EXTRA: " . $listaObstaculosAmpliada[$tamaño]['angulo']. PHP_EOL;
	 					//echo "ALTURA PTO EXTRA: " . $listaObstaculosAmpliada[$tamaño]['altura']. PHP_EOL;
	 					//echo "COB PTO EXTRA: " . $listaObstaculosAmpliada[$tamaño]['estePtoTieneCobertura']. PHP_EOL;	
	 				}
	 			}
	 	
	 	elseif (abs($theta0 > 1)){
	 		//echo "THETA 0 > 1". PHP_EOL;
	 		$ptoExtra = array ('angulo' => ($anguloLimitante + $x), 'altura' => 0, 'estePtoTieneCobertura' => false);
	 		array_splice($listaObstaculosAmpliada, $tamaño, 0, $ptoExtra);
	 	}
	 		
	 }
	 		// CASO B
	 		elseif (($obstaculoLimitante > $flm) && ($earthToRadar > $obstaculoLimitante)){
	 			//echo "ESTAMOS EN EL CASO B" .PHP_EOL;
	 			if ((abs($theta0)) <= 1){
	 				//echo "THETA 0 <= 1". PHP_EOL;
	 				// calcular epsilon 1 y 2
	 				obtenerPtosCorte($earthToRadar, $gammaMax, $earthToFl, $radioTerrestreAumentado, $epsilon1, $epsilon2, $ptosCorte);
	 				$ptoUno = array('angulo'=>$epsilon1,'altura'=>0, 'estePtoTieneCobertura'=>true); // epsilon1
	 				$ptoDos = array ('angulo'=>$epsilon2,'altura'=>0, 'estePtoTieneCoberura'=>true);// epsilon2
	 				// B.1
	 				if(($epsilon1 < $anguloMaxCob) && ($epsilon2 <$anguloMaxCob)){
	 					//echo "ESTAMOS EN EL CASO B.1" .PHP_EOL;
	 					// rango sombra
	 					$rangoSombra = array ($ptoLimitante, $ptoDos);
	 					$ptosSombra = interpolarPtosTerreno($rangoSombra, $radioTerrestreAumentado, 2);
	 					array_splice($listaObstaculosAmpliada, $tamaño, 0, $rangoSombra);
	 					// Rango Luz.  Se itnerpola desde el punto 2 al punto 1
	 					$rangoInterpolacion =  array ($ptoDos, $ptoUno);  
	 					$ptosNuevos = interpolarPtosTerreno($rangoInterpolacion, $radioTerrestreAumentado, 3);
	 					$tamaño = count ($listaObstaculosAmpliada);
	 					array_splice($listaObstaculosAmpliada, $tamaño, 0, $ptosNuevos);
	 					// añadimos el pto extra
	 					$tamaño = count ($listaObstaculosAmpliada);
	 					//echo "POS PTO LUZ ". $tamaño .PHP_EOL;
	 					$ptoExtra = array ('angulo' => ($anguloLimitante + $x), 'altura' => 0, 'estePtoTieneCobertura' => false);
	 					array_splice($listaObstaculosAmpliada, $tamaño, 0, $ptoExtra);
	 				}
	 				//B.2	 
	 				elseif (($epsilon1 > $anguloMaxCob) && ($epsilon2 < $anguloMaxCob)){
	 					$ptoDos = array ('angulo' =>$epsilon2, 'altura' =>0,  'estePtoTieneCobertura'=>true);// epsilon2 
	 					//echo "ESTAMOS EN EL CASO B.2" .PHP_EOL;
	 					// rango sombra
	 					$rangoSombra = array ($ptoLimitante, $ptoDos);
	 			
	 					//echo "CAMPO 0 DEL ARRAY RANGO SOMBRA: ". $rangoSombra[0] .PHP_EOL; // ARRAY
	 						//echo "CAMPO 0 DEL ARRAY (angulo ptoLimitante): ". $rangoSombra[0]['angulo'] .PHP_EOL;
	 						//echo "CAMPO 1 DEL ARRAY (altura ptoLimitante): ". $rangoSombra[0]['altura'] .PHP_EOL;
	 						//echo "CAMPO 2 DEL ARRAY (z ptoLimitante): "		. $rangoSombra[0]['estePtoTieneCobertura'] .PHP_EOL;
	 					//echo PHP_EOL;
	 			
	 					//echo "CAMPO 1 DEL ARRAY RANGO SOMBRA: ". $rangoSombra[1] .PHP_EOL; // ARRAY
	 						//echo "CAMPO 0 DEL ARRAY (angulo ptoDos): ". $rangoSombra[1]['angulo'] .PHP_EOL;
	 						//echo "CAMPO 1 DEL ARRAY (altura ptoDos): ". $rangoSombra[1]['altura'] .PHP_EOL;
	 						//echo "CAMPO 2 DEL ARRAY (z ptoDos): "	  . $rangoSombra[1]['estePtoTieneCobertura'] .PHP_EOL;
	 					//echo PHP_EOL;
	 			
	 					$ptosSombra = interpolarPtosTerreno($rangoSombra, $radioTerrestreAumentado, 2);
	 					array_splice($listaObstaculosAmpliada, $tamaño, 0, $rangoSombra);
	 					// Rango Luz. Se interpola desde el punto 2 al angulo de maxima cobertura (AlphRange)
	 					$rangoInterpolacion =  array ($ptoDos, $ptoMaxCob); 
	 					$ptosNuevos = interpolarPtosTerreno($rangoInterpolacion, $radioTerrestreAumentado, 3);
	 					$tamaño = count ($listaObstaculosAmpliada);
	 					array_splice($listaObstaculosAmpliada, $tamaño, 0, $ptosNuevos);
	 					// añadimos el pto extra
	 					$tamaño = count ($listaObstaculosAmpliada);
	 					$ptoExtra = array ('angulo' => ($anguloLimitante + $x), 'altura' => 0, 'estePtoTieneCobertura' => false);
	 					array_splice($listaObstaculosAmpliada, $tamaño, 0, $ptoExtra);
	 				}
	 				// caso B.3
	 				elseif((($epsilon1 > $anguloMaxCob) && ($epsilon2 > $anguloMaxCob))){
	 					//echo "ESTAMOS EN EL CASO B.3" .PHP_EOL;
	 					$ptoExtra = array ('angulo' => ($anguloLimitante + $x), 'altura' => 0, 'estePtoTieneCobertura' => false);
	 					array_splice($listaObstaculosAmpliada, $tamaño, 0, $ptoExtra);
	 				}
	 			}
	 			elseif (abs($theta0) > 1){
	 				//echo "THETA 0 > 1". PHP_EOL;
	 				$ptoExtra = array ('angulo' => ($anguloLimitante + $x), 'altura' => 0, 'estePtoTieneCobertura' => false);
	 				array_splice($listaObstaculosAmpliada, $tamaño, 0, $ptoExtra);
	 			}
	 		}	// CASO C
	 		elseif(($obstaculoLimitante > $flm) && ($earthToRadar < $obstaculoLimitante)){
	 			//echo "ESTAMOS EN EL CASO C" .PHP_EOL;
	 			$ptoExtra = array ('angulo' => ($anguloLimitante + $x), 'altura' => 0, 'estePtoTieneCobertura' => false);
	 			array_splice($listaObstaculosAmpliada, $tamaño, 0, $ptoExtra);
	 		}
	 		//echo "i = " . $i. PHP_EOL;
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
	//echo "SIZE LISTA OBSTACULOS: " . $n. PHP_EOL;

	// miramos el primer punto para poder comparar 
    $min = abs($punto - $listaObstaculos[0]['angulo']);
   // echo "PUNTO : " . $punto . PHP_EOL;
   // echo "PRIMER OBSTACULO: " . $listaObstaculos[0]['angulo']. PHP_EOL;
    //echo "PRIMER MIN: ". $min . PHP_EOL;
    
	for ($i =0; $i< $n ; $i++){
		
		if(abs($punto - $listaObstaculos[$i]['angulo']) < $min){
			//echo " VALOR ABSOLUTO DE LA DIFERENCIA: ". abs($punto - $listaObstaculos[$i]['angulo']). PHP_EOL;
			// si la diferencia es mas pequeña que el min anterior actualizamos min 
			$min = abs($punto - $listaObstaculos[$i]['angulo']);
			//echo "MIN DENTRO DEL IF: " . $min . PHP_EOL;
			$posPunto = $i; // me guardo el punto que tiene la distancia minima hasta el momento
			//echo "i: " . $i. PHP_EOL;
		}
	}
	//echo "POS PTO: " . $posPunto. PHP_EOL;
	return $posPunto; //  devolvemos la posicion del punto sxq lo que nos interesa luego es mirar si tiene cobertura
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
	
	//echo "ACIMUT CELDA : " . $acimutCelda. PHP_EOL;
	
	return $acimutCelda;
}

/**
 * Funcion que crea una malla de tamaño el doble del alcance del radar y la rellena con 0 o 1 en función de si el punto al que se aproxima el acimut de cada 
 * celda de la malla tiene o no cobertura.
 * @param unknown $radar
 * @param unknown $radioTerrestreAumentado
 * 
 * Devuelve la malla y el tamaño de la misma 
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
		//echo "i: ". $i. PHP_EOL;
		for ($j=0; $j<$tamMalla; $j++){ // recorre las filas de la malla
 		 	//echo "j: ".$j . PHP_EOL;
		 	
			// CALCULAMOS LAS COORDENADAS DE CADA CELDA
			$x = ($i * TAM_CELDA) - (($tamMalla /2) * TAM_CELDA) + (TAM_CELDA/2);
			$y = (($tamMalla /2) * TAM_CELDA) - ($j * TAM_CELDA) - (TAM_CELDA/2);
			
			// CALCULAMOS EL AZIMUT DE CADA CELDA Y APROXIMAMOS
		
			$azimutTeorico = calculaAcimut($x, $y); // grados GOOD
			
			if ($radar['totalAzimuths'] == 720){
				$azimutCelda = round($azimutTeorico*2) /2; //  azimut aproximado
				if ($azimutCelda == 720)
					$azimutCelda = 719;
			}
			else{ // tenemos 360 azimuts en total
			   $azimutCelda = round($azimutTeorico); //  azimut aproximado
			   if ($azimutCelda == 360)
			   		$azimutCelda = 359;
			}
			
			// al dividir entre el radio tenemos el angulo deseado
			$distanciaCeldaAradar = (sqrt(pow(($xR- $x),2)+ pow(($yR - $y),2)) ) / $radioTerrestreAumentadoEnMillas; // LO TENEMOS EN GRADOS
	
			// busca la posicion de la  distancia mas proxima en la lista de obstaculos del acimut aproximado (el menor)
			$pos = buscaDistanciaMenor($radar['listaAzimuths'][$azimutCelda], $distanciaCeldaAradar); 
				
				//echo "Azimut Aproximado para cada celda = " . $azimutCelda . PHP_EOL;
				//echo "  pos: ". $pos. PHP_EOL;
				//echo "  cob " . $radar['listaAzimuths'][$azimutCelda][$pos]['estePtoTieneCobertura']. PHP_EOL;
				
			if ($radar['listaAzimuths'][$azimutCelda][$pos]['estePtoTieneCobertura']){
				$malla[$i][$j] = 1;
				//echo "El pto aproximado tiene cobertura". PHP_EOL;
			}
			else{
				$malla[$i][$j] = 0; // entiendase el valor 0 para representar el caso en el que no hay cobertura
				//echo "El pto aproximado no tiene cobertura". PHP_EOL;
			}
				
		}	
	}
}

/*
 * Funcion para aplicar el algortimo de Moore
 * Entrda: la malla generada en la funcion anterior 
 * Salida: 
 */
function tratarMalla($malla){
	
	// debemos recorrer la malla completamente y aplicar el algoritmo de Moore tantas veces como celdas a 1 encontremos
	$sizeMalla = count($malla);
	
	for ($i=0; $i< $sizeMalla; $i++){ 
		for($j=0; $j<$sizeMalla; $j++){
			
			if ($malla[$i][$j] == 1)
				vencindadMoore();
		}
	}	
	
	
	
}

function tratamientoMallado($malla, $nombre){
	 
	$sizeMalla = count($malla); // da una dimension, pero nosotros sabemos que es cuadrada
	
	if ( ( $im = imagecreatetruecolor($sizeMalla, $sizeMalla) ) === false ) {
		echo "error creando imagen" . PHP_EOL;
		return false;
	}
	
 	if ( ( $azul = imagecolorallocate( $im, 0, 0, 255 ) ) === false ) { // definimos el color blanco para los pixeles con cobertura
 		echo "error definiendo color" . PHP_EOL;
 		return false;
 	}
 	if ( ( $verde = imagecolorallocate( $im, 0, 255, 0 ) ) === false ) { // definimos el color blanco para los pixeles con cobertura
 		echo "error definiendo color" . PHP_EOL;
 		return false;
 	}
 	if ( ( $rojo = imagecolorallocate( $im, 255, 0, 0 ) ) === false ) { // definimos el color blanco para los pixeles con cobertura
 		echo "error definiendo color" . PHP_EOL;
 		return false;
 	}
	 	
 	for ($i=0; $i< $sizeMalla; $i++) { // recorremos la malla y coloreamos solo los pixeles que tengan cobertura
		for($j=0; $j<$sizeMalla; $j++) {
	 	    if ( !isset($malla[$i][$j]) ) {
	 		imagesetpixel( $im, $j, $i, $verde);
	 	    } else if ( $malla[$i][$j] == 1 ) {
			imagesetpixel( $im, $j, $i, $azul );
	 	    } else if ( $malla[$i][$j] == 2 ) {
			imagesetpixel( $im, $j, $i, $rojo );
		    }
 		}//for interno
 	}//for externo
	 				
	if ( false === imagepng( $im, $nombre, 0) ) { // guardamos la imagen con los pixeles coloreados  // imagepng( $im, "imagenJ.png",0 ) // imagejpeg( $im, "imagenJ.png", 100)
		echo "Error al guardar la imagen" . PHP_EOL;
	}
	
	imagedestroy( $im ); // liberamos memoria
}

	
 
