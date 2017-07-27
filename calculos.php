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
 * Funcion para convertir el nivel de vuelo dado en metros
 * 
 * @param int $num, dado en cientos de pies  (ENTRADA)
 * @return number (SALIDA)
 */
function fLtoMeters ($num){
	
	return $num = $num * CIEN_PIES / PIE_EN_METROS;
}

/**
 * Funcion para convertir el parametro dado en millas nauticas a metros
 *  
 * @param int $num (ENTRADA)
 * @return number (SALIDA)
 */
function NMtoMeters ($num){
	
	return $num = $num * MILLA_NAUTICA_EN_METROS;
}

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
function buscarPuntosLimitantes($listaObstaculos, $flm, &$alturaPrimerPtoSinCob, &$anguloPrimerPtoSinCob, &$alturaUltimoPtoCob, &$anguloUltimoPtoCob){

	$i=0;
	$enc = false;
	
	while ( $i < (count($listaObstaculos)) && !$enc ){
		
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
	 		$tamaño = count($radar['listaAzimuths'][$i]);
	 	
	 		// obtenemos la altura del ultimo punto para cada azimuth
	 		$obstaculoLimitante = $radar['listaAzimuths'][$i][$tamaño-1]['altura'];  
	 		
	 		if ($flm >= $obstaculoLimitante){
	 			
	 			$earthToEvalPoint = $radioTerrestreAumentado + $obstaculoLimitante; 
	 			// obtenemos el angulo del ultimo obstaculo de cada azimuth
	 			$angulo = $radar['listaAzimuths'][$i][$tamaño-1]['angulo'];
	 				 	
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
 * Funcion que calcula las coordenadas geograficas de cobertura de un fichero kml para un determinado
 * radar a partir de las coordenadas, el nivel de vuelo y el array de distancias de cobertura.
 * 
 * @param array $radar (ENTRADA)
 * @param array $coordenadas, array con las coordenadas en grados decimales (ENTRADA)
 * @param array $distanciasCobertura, en millas nauticas (ENTRADA)
 * @param int $flm, en metros (ENTRADA)
 * @param array $coordenadasGeograficas (ENTRADA/SALIDA)
 */
function calculaCoordenadasGeograficas( $radar, $coordenadas, $distanciasCobertura, $flm, &$coordenadasGeograficas){
	
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
 * Funcion auxiliar que permite al usuario ajustar la distancia maxima permitda entre los puntos de un obstaculo para determinar la precision de la 
 * representacion
 * @param float $radioTerrestreAumentado (ENTRADA)
 * @return number (SALIDA)
 */
function calculoAnguloMaximoEntrePtos($radioTerrestreAumentado){
	
	$anguloMaximo = 0;
	$long = 0;
	
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
 * @param array $listaObstaculos, representa un rango de interpolacion (ENTRADA)
 * @param float $radioTerrestreAumentado (ENTRADA)
 * @param int $casos (ENTRADA), para diferenciar si se tiene que interpolar en la zona de luz o la de sombra
 * @return array[]|boolean[][]|number[][] (SALIDA), array con los obstaculos calculado con la interpolacion del terreno
 */
function interpolarPtosTerreno($listaObstaculos, $radioTerrestreAumentado, $casos){

	$diferencia = 0; $anguloNuevoPto = 0; $alturaNuevoPto = 0;
	$listaObstaculosAmpliada = array();
	$PtoNuevo = array ('angulo' => 0, 'altura' => 0, 'estePtoTieneCobertura' => false); // inicializamos el punto nuevo

	// PEDIR AL USUARIO LA DISTANCIA QUE TIENE QUE HABER ENTRE LOS PTOS
	//$anguloMaximo=calculoAnguloMaximoEntrePtos($radioTerrestreAumentado);
	
	$anguloMaximo = (DISTANCIA_ENTRE_PUNTOS * MILLA_NAUTICA_EN_METROS) / $radioTerrestreAumentado; // paso  0.5NM a grados
	$n = count($listaObstaculos); //obtenemos la long de la lista obstaculos del azimuts  
    
	for ($i = 0; $i < $n-1 ; $i++){//recorremos la lista de obstaculos del azimut para ver donde tenemos q insertar puntos nuevos (MENOS EL ULTIMO OBSTACULO!)
		
		if ($casos == 1)
			$listaObstaculosAmpliada[] = $listaObstaculos[$i]; // copiamos el punto original

		$diferencia = $listaObstaculos[$i+1]['angulo'] -  $listaObstaculos[$i]['angulo'];
		
		if ($diferencia > $anguloMaximo){// es necesario interpolar
			$ptosQueMeter = round(($listaObstaculos[$i+1]['angulo'] -  $listaObstaculos[$i]['angulo']) / $anguloMaximo);
			$distancia = ($listaObstaculos[$i+1]['angulo'] -  $listaObstaculos[$i]['angulo']) / ($ptosQueMeter+1); // se le suma una xq son segmentos
					
			for ($j = 0; $j < $ptosQueMeter; $j++){ // creamos los ptos
				// obtenemos el angulo nuevo
				$anguloNuevoPto = $listaObstaculos[$i]['angulo'] +  ($j+1) * $distancia;
					
				// obtenemos la altura nueva
				if($casos == 1){ // zona de terreno
					$alturaNuevoPto = ((($listaObstaculos[$i+1]['altura'] -  $listaObstaculos[$i]['altura']) / 
						($listaObstaculos[$i+1]['angulo'] -  $listaObstaculos[$i]['angulo']))* ($anguloNuevoPto-$listaObstaculos[$i]['angulo']))
						+  $listaObstaculos[$i]['altura'];
						
					$PtoNuevo = array('angulo' => $anguloNuevoPto, 'altura' => $alturaNuevoPto, 'estePtoTieneCobertura' => false);
				}
				elseif ($casos == 2){ //zona de sombra
					$alturaNuevoPto = 0;
					$PtoNuevo = array('angulo' => $anguloNuevoPto, 'altura' => $alturaNuevoPto, 'estePtoTieneCobertura' => false);
				}
				elseif ($casos == 3){ //zona luz
					$alturaNuevoPto = 0;
					$PtoNuevo = array('angulo' => $anguloNuevoPto, 'altura' => $alturaNuevoPto, 'estePtoTieneCobertura' => true);
				}
				$listaObstaculosAmpliada[] = $PtoNuevo; //metemos el punto nuevo que acabamos de crear al final de la lista de obstaculos Ampliada
	 		}// for interno
		}//if
	}// for externo
	$listaObstaculosAmpliada[] = $listaObstaculos[$n-1];
	
	return $listaObstaculosAmpliada;
}

/**
 * Funcion que actualiza el parametro 'estePtoTieneCobertura'  dependiendo de si se cumple la condicion que determina si hay o no cobertura
 * 
 * @param array $listaObstaculosAmpliada (ENTRADA/SALIDA)
 * @param int $flm (ENTRADA)
 */
function miraSiHayCobertura(&$listaObstaculosAmpliada, $flm){
		
	for ($i = 0; $i < count($listaObstaculosAmpliada); $i++){

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
 * @param array $radar (ENTRADA)
 * @param array $listaObstaculos (ENTRADA)
 * @param float $radioTerrestreAumentado (ENTRADA)
 * @param int  $flm (ENTRADA)
 * @param float $obstaculoLimitante (ENTRADA)
 * @param float $gammaMax (ENTRADA/SALIDA)
 * @param float $theta0 (ENTRADA/SALIDA)
 * @param float $earthToRadar (ENTRADA/SALIDA)
 * @param float $earthToEvalPoint (ENTRADA/SALIDA)
 * @param float $earthToFl (ENTRADA/SALIDA)
 * @param float $radarSupTierra (ENTRADA/SALIDA)
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
	
	$epsilon1 = PI - $theta1 - $gammaMax;
	$epsilon2 = PI - (PI-$theta1) - $gammaMax;
	
	$ptosCorte[0] = $epsilon1 * $radioTerrestreAumentado;
	$ptosCorte[1] = $epsilon2 * $radioTerrestreAumentado;	
}

/**
 * CASO B 
 * Funcion que calcula las coberturas cuando el nivel de vuelo FL, esta por debajo del radar
 * 
 * @param array $radar (ENTRADA)
 * @param int $flm (ENTRADA)
 * @param float $radioTerrestreAumentado (ENTRADA/ SALIDA)
 */
function calculosFLdebajoRadar(&$radar, $flm, $radioTerrestreAumentado){
	
	$anguloMaxCob = calculaAnguloMaximaCobertura($radar, $radioTerrestreAumentado, $flm);
	 $X = (0.1 *  MILLA_NAUTICA_EN_METROS ) / ( RADIO_TERRESTRE * (4/3) ); // angulo (en radianes) entre el ultimo pto de cada acimut y el pto extra para una distancia de 0.1 NM
	
	$ptosNuevos = array();
	$ptoExtra = array( 'angulo' => 0, 'altura' => 0, 'estePtoTieneCobertura' => false);
	$ptoMaxCob = array('angulo'=> $anguloMaxCob, 'altura'=> 0,'estePtoTieneCobertura'=> true);
	
	$x = rad2deg($X); // pequeña distancia que se le suma al angulo de cada punto [0.1 NM] para añadir un ptoExtra y poder aproximar el mallado
	
	for ($i=0; $i < $radar['totalAzimuths']; $i++){
	 	
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
	 	if( ( $obstaculoLimitante < $flm ) && ( $obstaculoLimitante < $radarSupTierra ) ){
	 	
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
	  elseif ( ( $obstaculoLimitante > $flm ) && ( $radarSupTierra > $obstaculoLimitante ) ){
	 			
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
	 					$listaObstaculosAmpliada = array_merge($listaObstaculosAmpliada, array($ptoExtra));
	 			} // fin caso B.2
	 			
	 			// caso B.3
	 			elseif((($epsilon1 > $anguloMaxCob) && ($epsilon2 > $anguloMaxCob))){
	 					$ptoExtra = array ('angulo' => ($anguloLimitante + $x), 'altura' => 0, 'estePtoTieneCobertura' => false);
	 					$listaObstaculosAmpliada = array_merge($listaObstaculosAmpliada, array($ptoExtra));
	 			}
	  }
	 	 elseif (abs($theta0) > 1){
	 		$ptoExtra = array ('angulo' => ($anguloLimitante + $x), 'altura' => 0, 'estePtoTieneCobertura' => false); 
	 		$listaObstaculosAmpliada = array_merge($listaObstaculosAmpliada, array($ptoExtra));
	 	 }// Fin CASO B
	 	 
	 	 }// CASO C
	 		elseif(($obstaculoLimitante > $flm) && ($radarSupTierra < $obstaculoLimitante)){
	 			$ptoExtra = array ('angulo' => ($anguloLimitante + $x), 'altura' => 0, 'estePtoTieneCobertura' => false); 
	 			$listaObstaculosAmpliada = array_merge($listaObstaculosAmpliada, array($ptoExtra));
	 		}
	 		$radar['listaAzimuths'][$i] = $listaObstaculosAmpliada; // metemos la lista de obstaculos nueva en la estructura 
	}//for				 
}
				
/**
 * Funcion que busca el punto más próximo al punto dado dentro de una lista de obstaculos comparando los angulos y devuelve la posicion de ese punto
 * 
 * @param array $listaObstaculos (ENTRADA)
 * @param float $punto (ENTRADA)
 * @return number (SALIDA)
 */
function buscaDistanciaMenor($listaObstaculos, $punto){
	
	$posPunto = 0;
	
	// miramos la diferencia con el primer punto para poder comparar 
    $min = abs($punto - $listaObstaculos[0]['angulo']);
    
	for ($i = 0; $i < count ($listaObstaculos); $i++){

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
 * @param int $x (ENTRADA)
 * @param int $y (ENTRADA)
 * @return number (SALIDA)
 */
function calculaAcimut($x, $y){
	
	$acimut =0;
	$acimutCelda =0;
		
	if ($x < 0){
		$acimut = rad2deg( atan($y / $x) + PI );
	}
	elseif($x > 0){
			if ($y < 0){
				$acimut = rad2deg( atan($y / $x) + 2 * PI );
			}
			else{ // $y>= 0
				$acimut = rad2deg( atan($y / $x) );
			}
	}
	elseif ($x == 0){
			if($y < 0){
				$acimut = rad2deg( ( 3 * PI ) / 2 );
			}
			elseif($y > 0){
				$acimut = rad2deg( PI / 2 );
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
 * @param array $radar (ENTRADA)
 * @param float $radioTerrestreAumentado (ENTRADA)
 * @param array $malla (ENTRADA/SALIDA)
 */
function generacionMallado($radar, $radioTerrestreAumentado, &$malla){
	
	// pasamos a  millas nauticas el rango del radar que esta almacenado en metros en la estructura radar
	$tamMalla = (( 2 * $radar['range'] ) / TAM_CELDA) / MILLA_NAUTICA_EN_METROS;
	$xR = 0; // coordenada x del radar
	$yR = 0; // coordenada y del radar
	$radioTerrestreAumentadoEnMillas  = $radioTerrestreAumentado / MILLA_NAUTICA_EN_METROS;
	
	$malla = array(); // creamos una malla vacia 
	$azimutTeorico = 0; // azimut teorico calculado
	$azimutCelda = 0; // azimut aproximado 
	$pos = 0;
	
	// CENTRAMOS LA MALLA Y CALCULAMOS EL PTO MEDIO DE CADA CELDA
	
	for ($i = 0; $i < $tamMalla; $i++){ // recorre las columnas de la malla 
		for ($j = 0; $j < $tamMalla; $j++){ // recorre las filas de la malla 
		
			// CALCULAMOS LAS COORDENADAS DE CADA CELDA
			$x = ($i * TAM_CELDA) - ( ($tamMalla / 2 ) * TAM_CELDA ) + ( TAM_CELDA / 2 );
			$y = ( ( $tamMalla / 2 ) * TAM_CELDA ) - ( $j * TAM_CELDA ) - ( TAM_CELDA / 2 );
		
			// CALCULAMOS EL AZIMUT DE CADA CELDA Y APROXIMAMOS
			$azimutTeorico = calculaAcimut($x, $y); 
			
			if ($radar['totalAzimuths'] == 720){
				$azimutCelda = round( $azimutTeorico * 2 ) / 2; // calculamos el azimut aproximado
				if ($azimutCelda == 720)
				 $azimutCelda = 719; // sobreescribimos el ultimo valor para no salirnos de rangos
			}
			else{ // tenemos 360 azimuts en total
			   $azimutCelda = round($azimutTeorico); // calculamos el azimut aproximado
			   if ($azimutCelda == 360)
			   		$azimutCelda = 359; 
			}
			// al dividir entre el radio tenemos el angulo deseado
			$distanciaCeldaAradar = ( sqrt( pow( ($xR - $x),2 )+ pow( ($yR - $y),2) ) ) / $radioTerrestreAumentadoEnMillas;
			
			// busca la posicion de la  distancia mas proxima en la lista de obstaculos del acimut aproximado (el menor)
			$pos = buscaDistanciaMenor($radar['listaAzimuths'][$azimutCelda], $distanciaCeldaAradar); 
				
			if ( ($radar['listaAzimuths'][$azimutCelda][$pos]['estePtoTieneCobertura']) === false){
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
	// recorremos la malla y la copiamos en la malla de mayor tamaño 
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

	$xR = 0;
	$yR = 0;
	// pasamos a  millas nauticas el rango del radar que esta almacenado en metros en la estructura radar
	$tamMalla = (( 2 * $radar['range'] ) / TAM_CELDA) / MILLA_NAUTICA_EN_METROS;

	for($isla = 0; $isla < count($listaC); $isla++){ // recorre la lista de islas/ contornos

		$n = count($listaC[$isla]);

		for($i = 0; $i < $n; $i++){ // recorre la lista de puntos del contorno

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


/////////////////////////////////////////////// FUNCIONES NECESARIAS PARA PODER APLICAR EL ALGORITMO MARCHING SQUARES ////////////////////////////////////////////////////////////// 

/**
 * Funcion que dada una malla y la posición de una fila, nos devuelve la fila en un array
 * 
 * @param int $y (ENTRADA)
 * @param array $malla (ENTRADA)
 * @return array[] (SALIDA)
 */
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
function getFirstPoint($malla, &$x, &$y, $searchValue){

	$x = -1; $y = -1;
	
	$rowData = array();
	
	$fila = 0;
	$enc = false;
	$salir = false;

	while ($fila < count($malla) && !$salir){

		$rowData = getFila($fila, $malla); // no quedamos con la fila de la matriz
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
 * Funcion que deternina y establece un conjunto de 4 pixels que representan nuestro estado actual, para deerminar nuestra direccion actual y la siguiente
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
			case UP: $y --; break;
			case LEFT: $x--; break;
			case DOWN: $y++; break;
			case RIGHT: $x++; break;
			default : break;
		}
	} while (($x != $startX || $y != $startY) && ($index < count($vector)));

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
	$this->searchNext[] = array('x' => $point[ 'x' ], 'y' => $point[ 'y' ]);
	$this->floodValue   = $floodValue;
	$this->searchValue  = $searchValue;
	
	// As long as there are items in the queue
	// keep filling!
	while ( !empty( $this->searchNext ) ) {
	
		// Get the next square item and erase it from the list
		$next = array_pop( $this->searchNext );
		$this->x = $next[ 'x' ];
		$this->y = $next[ 'y' ];
	
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
			$this->searchNext[] = array( 'x' => $checkX, 'y' => $checkY );
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
	
		while ( false !== ($contorno = marchingSquares($radar, $malla, $flm, $searchValue = 1 )) ) { // nos da el contorno de una isla
			
			// mezclamos el contorno con el mapa original, para luego rellenar DENTRO del contorno con un flood fill
			// este paso podra ser opcional, desde que floodfill funciona bien no es necesario delimitar la zona
			
			//$nuevaMalla = mergeContorno($malla, $contorno, $value = 2);
			
			
			$pInicial = array( 'x' => $contorno[0]['col']+1, 'y' => $contorno[0]['fila']+1 ); // el primer punto del contorno siempre esta arriba a la izq.
			$floodFiller = new FloodFiller();	
			
			
			//$nuevaMalla = $floodFiller->Scan($nuevaMalla, $pInicial, $floodValue = 2, $searchValue = 1);
			$nuevaMalla = $floodFiller->Scan($malla, $pInicial, $floodValue = 2, $searchValue = 1);
			
			$malla = $nuevaMalla;
			$listaContornos[] = $contorno;
		}
	
		// ahora buscamos los contornos dentro de las zonas con cobertura, seran islas SIN cobertura
		// para ello rellenamos la matriz con '2', y solo tendremos '2' y '0'
		// (zona sin cobertura que tengo que apuntar en la lista de contornos)
		$floodFiller = new FloodFiller();
		$malla = $floodFiller->Scan($malla, array( 'x' => 0, 'y' => 0 ), $floodValue = 2, $searchValue = 0);
	
		while ( false !== ($contorno = marchingSquares($radar, $malla, $flm, $searchValue = 0 )) ) { // nos da el contorno de una isla
	
			// mezclamos el contorno con el mapa original, para luego rellenar DENTRO del contorno con un flood fill
			// este paso podra ser opcional
			
			//$nuevaMalla = mergeContorno($malla, $contorno, $value = 3);
			
			
			$pInicial = array( 'x' => $contorno[0]['col']+1, 'y' => $contorno[0]['fila']+1 ); // el primer punto del contorno siempre esta arriba a la izq.
			$floodFiller = new FloodFiller();
			
			
			//$nuevaMalla = $floodFiller->Scan($nuevaMalla, $pInicial, $floodValue = 3, $searchValue = 0);
			$nuevaMalla = $floodFiller->Scan($malla, $pInicial, $floodValue = 3, $searchValue = 0);
			
			
			$malla = $nuevaMalla;
			$listaContornos[] = $contorno;
		}	
		return true;
}
