<?php

// Más velocidad y menos precisión aumentando INTERSECTION_SUBDIVISION_LIMIT
// Más precisión y menos velocidad disminuyendo INTERSECTION_SUBDIVISION_LIMIT
const INTERSECTION_TOLERANCE_LIMIT_NM = 20; // Tolerancia para asegurar solape entre azimuths (NM)
const INTERSECTION_TOLERANCE_LIMIT_RAD = INTERSECTION_TOLERANCE_LIMIT_NM / RADIO_TERRESTRE;
const INTERSECTION_TOLERANCE_LIMIT_M = INTERSECTION_TOLERANCE_LIMIT_NM * MILLA_NAUTICA_EN_METROS; // Distancia máxima entre subdivisiones entre vértices
CONST FRONTERA_LATITUD = 90; // latitud complementaria
CONST FEET_TO_METERS = 0.30480370641307;
// CONST PASO_A_GRADOS = 180.0;
// CONST TAM_CELDA = 0.20; //10; // 0.5; // paso de la malla en NM 0.5 , 0.11 es lo mas que pequeño q no desborda
// CONST TAM_CELDA_MITAD = TAM_CELDA/2.0; // 5; // 0.25; // NM
// CONST TAM_ANGULO_MAXIMO = TAM_CELDA*2.0; //20; // 1; // NM (lo situamos al doble que tamaño celda)

/**
 * Funcion que permite buscar los puntos limitantes necesarios para poder calcular la cobertura.
 * 
 * @param array $listaObstaculos (ENTRADA)
 * @param float $flm (ENTRADA)
 * @param float $alturaPrimerPtoSinCob (ENTRADA/SALIDA)
 * @param float $anguloPrimerPtoSinCob (ENTRADA/SALIDA)
 * @param float $alturaUltimoPtoCob (ENTRADA/SALIDA)
 * @param float $anguloUltimoPtoCob (ENTRADA/SALIDA)
 * @return boolean devuelve true si encontrado o false en caso contrario (SALIDA)
 */
function buscarPuntosLimitantes(array $listaObstaculos, float $flm, float &$alturaPrimerPtoSinCob, float &$anguloPrimerPtoSinCob, float &$alturaUltimoPtoCob, float &$anguloUltimoPtoCob, float $alturaCentroFasesAntena): bool {

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
 * @param float $flm (ENTRADA)
 * @return float (SALIDA)
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
 * @param float $flm nivel de vuelo en metros (ENTRADA)
 * @return array distancias a los alcances máximos por cada azimut (SALIDA)
 */
function calculosFLencimaRadar(array $radar, float $flm): array
{

    $debug = false;
    $distanciasAlcances = array();
    $radioTerrestreAumentado = $radar['screening']['radioTerrestreAumentado'];
    $anguloMaxCob = calculaAnguloMaximaCobertura($radar, $flm); // AlphaRange en Matlab
    $earthToFl = $radioTerrestreAumentado + $flm;
    $earthToRadar = $radar['screening']['towerHeight'] + $radar['screening']['terrainHeight'] + $radioTerrestreAumentado;
    $earthToRadarPow = pow($earthToRadar, 2);
    $distanciaMaxCobertura = $radioTerrestreAumentado * $anguloMaxCob / MILLA_NAUTICA_EN_METROS;

    // recorremos los azimuths
    for ($i = 0; $i < $radar['screening']['totalAzimuths']; $i++) {
        // obtenemos la última linea del array para cada azimut.
        if (!isset($radar['screening']['listaAzimuths'][$i])) {
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
        if ($flm >= $obstaculoLimitante) {
            // caso en el que el nivel de vuelo está por encima del obstáculo que limita

            $earthToEvalPoint = $radioTerrestreAumentado + $obstaculoLimitante;
            $earthToEvalPointPow = pow($earthToEvalPoint, 2);
            // ángulo del ultimo obstaculo de cada azimuth
            $angulo = $radar['screening']['listaAzimuths'][$i][$ultimoPunto]['angulo'];
            // distancia que corresponde a ese ángulo
            $distancia = sqrt(($earthToRadarPow + $earthToEvalPointPow) - 2 * $earthToRadar * $earthToEvalPoint * cos($angulo));
            if (0  == $distancia) {
                // si el ángulo es 0, la distancia es 0, y el ángulo entre la vertical del radar y el obstáculo
                // más alto dará una visión por cero. No hay solución posible, daremos error.
                logger(" E> La distancia $distancia para obstaculoLimitante $obstaculoLimitante con angulo $angulo impide continuar. No debería suceder.");
                exit(-1);
            }
            // ángulo formado entre la vertical del radar (hacia el centro de la tierra) y el obstáculo más alto
            $gammaMax = acos(
                (pow($distancia, 2) +
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
            if ($debug /* && $i == 180 */) {
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
            if (false === $ret) {
                debug_print_backtrace(); die("Unexpected error: " . __FUNCTION__ . " in " . __FILE__ . " at line " . __LINE__);    
            }
            // A2.3 (con objeto de paliar una posible excesiva separación entre puntos consecutivos,
            // se procede a calcular la intersección entre el nivel de vuelo y la recta que une los dos
            // puntos consecutivos límite, es decir el último punto con cobertura y el primero sin ella.
            // ¿es una interpolación? ¿por qué?
            $anguloLimitante = (($flm - $alturaUltimoPtoCob) * (($anguloPrimerPtoSinCob - $anguloUltimoPtoCob) / ($alturaPrimerPtoSinCob - $alturaUltimoPtoCob))) + $anguloUltimoPtoCob;

            if ($anguloLimitante > $anguloMaxCob) {
                // este valor se puede precalcular siempre será el mismo
                $distanciasAlcances[$i] = $distanciaMaxCobertura;
                // $distanciasAlcances[$i] = $radioTerrestreAumentado * $anguloMaxCob / MILLA_NAUTICA_EN_METROS;
            } else {
                $distanciasAlcances[$i] = $radioTerrestreAumentado * $anguloLimitante / MILLA_NAUTICA_EN_METROS;
            }

            // print ($i) . "\tA2 distancia: " . $distanciasAlcances[$i] . "NM" . PHP_EOL;

            if ($debug /* && $i == 180 */) {
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
        } // else
    } // fin for para recorrer los azimuths

    if ($debug)
        foreach ($distanciasAlcances as $i => $nm)
            print "acimut: " . ($i) . "\t distancia: " . round($nm, 2) . PHP_EOL;

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
 * @param float $flm, en metros (ENTRADA)
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
        $listaContornos[$i] = array($res['lat'], $res['lon']);
        //$listaContornos[$i]['lat'] = $res['lat'];
        //$listaContornos[$i]['lon'] = $res['lon'];
        //$listaContornos[$i]['alt'] = $flm;
    }

    // cerramos el polígono, repitiendo como último punto el primero
    $listaContornos[] = $listaContornos[0];
    // generamos la misma estructura que se hace en calculaCoordenadasGeograficasB
    //$listaContornos = array(
    //    array(
    //        'level' => 0,
    //        'alt' => $flm,
    //        'polygon' => $listaContornos,
    //        'inside' => array(),
    //    )
    //);
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
 * CASO B
 * Funcion que calcula las coberturas cuando el nivel de vuelo FL, esta por debajo del radar
 * 
 * @param array $radar (ENTRADA / SALIDA)
 * @param float $flm nivel de vuelo en metros (ENTRADA)
 * @return array malla[i][j] = array(lat,lon,cobertura)
 */
function calculosFLdebajoRadar2(array &$radar, float $flm) {
    $time_malla_coverage_total = 0;
    $time_calcula_vertices_interseccion_total = 0;
    $debug = false;
/*
    $radar['lat_deg'] = $radar['lat'] = 41.773763888889;
    $radar['lon_deg'] = $radar['lon'] = 2.4376138888889;
    $radar['lat_rad'] = deg2rad(41.773763888889);
    $radar['lon_rad'] = deg2rad(2.4376138888889);
*/
    $max_distancia_nm = 0; // distancia al obstáculo más lejano, en millas náuticas

    // Ángulo central máximo según rango en millas
    $alpha_max = ($radar['screening']['range'] * MILLA_NAUTICA_EN_METROS) / $radar['screening']['radioTerrestreAumentado'];
    if ($debug)
        print "angulo central maximo segun rango en millas nauicas: " . $alpha_max . PHP_EOL;
    // línea de rango máximo
    $m = tan(pi()/2 - $alpha_max); // pi()/2 = 90º en radianes

    logger(" V> fl: {$flm}m range: {$radar['screening']['range']}NM " .
        "lat: {$radar['lat']}º lon: {$radar['lon']}º " . 
        "num az: {$radar['screening']['totalAzimuths']}");

    $matriz_obstaculos = []; // la estructura es [numero de azimuth][numero de obstaculo] 
    foreach($radar['screening']['listaAzimuths'] as $azimut => $listaObstaculos) {
        $i = 0;
        $alt = 0;
        $xb = 0; $yb = 0;
        $xa = 0.0;
        $ya = $radar['screening']['towerHeight'] +
            $radar['screening']['terrainHeight'] +
            $radar['screening']['radioTerrestreAumentado'];
        $matriz_obstaculos[$azimut][0] = [$xa, $ya];
        if ($debug)
            print "[0,$azimut] alt: $alt => $xa,$ya" . PHP_EOL;
        
        $i = 0;
        foreach($listaObstaculos as $i => $obstaculo) {
            $alt = $obstaculo['altura']; // Altitud del obstáculo i
            $ang = $obstaculo['angulo']; // Ángulo central del obstáculo i
            $r = $radar['screening']['radioTerrestreAumentado'] + $alt; // Radio del obstáculo
            $xb = $r * sin($ang); // Coordenada horizontal del obstáculo
            $yb = $r * cos($ang); // Coordenada vertical del obstáculo
            if ($debug)
                print "[" . $i+1 . ",$azimut] alt: $alt, ang: $ang, r: $r => $xb,$yb" . PHP_EOL;
            
            $matriz_obstaculos[$azimut][$i + 1] = [$xb, $yb];
        }
        $i++;
        
        // Si no existe muro final, se calcula el muro: el primer punto será la intersección
        // entre la línea que forman el radar y el último punto con la línea de máximo rango.
        if ( $alt <= 30000 ) {
            // coordenadas del radar en $xa, $ya
            // coordenadas del último obstáculo en $x, $y
            // línea de rango máximo en $m
             /* Intersección segmento - línea
              * Segmento:
              *   x = xa + t*(xb-xa)
              *   y = ya + t*(yb-ya)
              *   t [0,1]
              *
              * Línea:
              *   y = m*x
              *
              * Intersección:
              *                m*xa - ya
              *   ti = - ---------------------
              *           m*(xb-xa) - (yb-ya)
              */

            $dx = $xb - $xa;
            $dy = $yb - $ya;
            $t = ($m * $xa - $ya) / ($dy - $m * $dx);
            if ($t >= 1) { // Más allá del último obstáculo
                //print "IFSNOP $azimut" . PHP_EOL; exit(-1);
                $xi = $xa + $t * $dx;   // Punto de intersección
                $yi = $ya + $t * $dy;   // Punto de intersección

                $alpha_m = atan($m);
                $xil = (BERTA_MAX_WALL_HEIGHT + $radar['screening']['radioTerrestreAumentado']) * cos($alpha_m); // Punto límite del muro
                $yil = (BERTA_MAX_WALL_HEIGHT + $radar['screening']['radioTerrestreAumentado']) * sin($alpha_m); // Punto límite del muro

                if ($yi > $yil) $yil = $yi; // Punto de intersección por encima del límite del muro

                // Se añaden los dos nuevos puntos
                $matriz_obstaculos[$azimut][$i++] = [$xi, $yi];
                // print "[$i,$azimut] => $xi,$yi" . PHP_EOL;
                $matriz_obstaculos[$azimut][$i++] = [$xil, $yil];
                // print "[$i,$azimut] => $xil,$yil" . PHP_EOL;
            }
        }
    }

    /*******************************
    * INTERSECCIONES POR ACIMUT
    *******************************/
    $intersec = array();

    $W = $flm +  $radar['screening']['radioTerrestreAumentado'];  // Radio de circunferencia del nivel de vuelo
    $W += 0.01;                     // Suma 10 cm para evitar errores numéricos
    print "Rt: " . $radar['screening']['radioTerrestreAumentado'] . PHP_EOL;
    print "W: $W" . PHP_EOL;
    /* Esto es porque a veces el screening da dos valores de
     * altitud consecutivos iguales. Si además coinciden con el
     * FL, da lugar a errores al calcular la intersección ya que
     * línea y circunferencia son tangentes.
     */

    // Intersección entre nivel de vuelo (arco de circunferencia) y polilínea de screening para acimuth a
    for ($azi = 0; $azi < count($matriz_obstaculos); $azi++) {
        $count = 0;
        $intersec[$azi] = array();
        for ($obs = 0; $obs < count($matriz_obstaculos[$azi]) - 1; $obs++) {

            $x1 = $matriz_obstaculos[$azi][$obs][0];
            $y1 = $matriz_obstaculos[$azi][$obs][1];
            $x2 = $matriz_obstaculos[$azi][$obs + 1][0];
            $y2 = $matriz_obstaculos[$azi][$obs + 1][1];

            $dx = $x2 - $x1;
            $dy = $y2 - $y1;
            /* Segmento:
             *   x = x1 + t*dx
             *   y = y1 + t*dy
             * Circunferencia:
             *   x^2 + y^2 = W^2
            */
            // Coeficientes At^2 + Bt + C=0
            $A = $dx * $dx + $dy * $dy;
            $B = 2 * ($x1 * $dx + $y1 * $dy);
            $C = $x1 * $x1 + $y1 * $y1 - $W * $W;

            // Discriminante
            $D = $B * $B - 4 * $A * $C;
            //print "$azi,$obs: " . json_encode($matriz_obstaculos[$azi][$obs]) . PHP_EOL;
            //print "rowsX: $obs Azimut: $azi x1: $x1 y1: $y1 x2: $x2 y2: $y2" . PHP_EOL;
            //print "A: $A B: $B C: $C D: $D" . PHP_EOL;
            $epsilon = 1e-12 * max(1.0, abs($B), abs($C));

            // $A suficientemente distinto de 0 → sí es cuadrática
            // $D no claramente negativo → hay raíces reales o casi reales
            if ((abs($A) > $epsilon) && $D >= -$epsilon) {
                //if (($D >= 0) && ($A != 0)) {       // Hay intersección

                $sqrt_d = sqrt($D);
                $A2 = $A * 2;
                $t1 = (-$B - $sqrt_d) / $A2;
                $t2 = (-$B + $sqrt_d) / $A2;

                // Asegurarse de que la primera solución es la menor
                if ($t1 > $t2)
                    [$t1, $t2] = [$t2, $t1];
                // print "t1: $t1 t2: $t2" . PHP_EOL;
                foreach ([$t1, $t2] as $t) {
                    if ($t >= 0 && $t <= 1) {   // Límites del segmento
                        // print "t1: $t1 t2: $t2 DENTRO SEGMENTO" . PHP_EOL;

                        $xi = $x1 + $t * $dx;   // Punto de intersección
                        $yi = $y1 + $t * $dy;   // Punto de intersección

                        // Distancia del radar a la intersección, un acimut por columna [nm]
                        $alpha = M_PI / 2 - atan2($yi, $xi);    // atan2() pone el ángulo 0º en x=1, y=0, en sentido antihorario
                        $dist_nm = ($radar['screening']['radioTerrestreAumentado'] * $alpha) / MILLA_NAUTICA_EN_METROS;
                        if ($dist_nm > $max_distancia_nm)
                            $max_distancia_nm = $dist_nm;


                        $intersec[$azi][$count] = $dist_nm;
                        if ($debug)
                            print "intersec count: $count obs: $obs azimut: $azi => dist_nm: $dist_nm" . PHP_EOL;
                        $count++;
                    }
                }
            }
        }
    }

    logger(" D> Generando malla de cobertura");

    /*******************************
     * MALLA DE COBERTURA
     ******************************/

    $precision_malla = 2;
    $resolucion_malla = pow(10, -$precision_malla);  // Resolución vertical [º] -> 0.01º  que equivale a 1.11 km
    /*
     * esta configuración (precision = 2, resolucion = 0.01
     * produce una malla de 395x529
     * 43.74 -0.2    43.74 -0.19    43.74 -0.18    
     * 43.73 -0.2    43.73 -0.19    43.73 -0.18    
     * 43.72 -0.2    43.72 -0.19    43.72 -0.18  
     */
    [$malla_lat_lon, $malla_lat_lon_rows, $malla_lat_lon_cols, $malla_lat_nw, $malla_lon_nw] = create_malla($radar, $max_distancia_nm, $precision_malla, $resolucion_malla);
    /*
     * // Código de depuración
     * for($i=0;$i<3; $i++) { for($j=0; $j<3; $j++) {
     * print $malla_lat_lon[$i][$j][0] . " " . $malla_lat_lon[$i][$j][1] . "    ";
     * } print PHP_EOL; }
     */
    logger(" V> " . "Info memory_usage(" . convertBytes(memory_get_usage(false)) . ") " .
        "Memory_peak_usage(" . convertBytes(memory_get_peak_usage(false)) . ")");

    /*******************************
     * POLÍGONO DE COBERTURA
     *******************************/

    // Cada celda es un polígono de 4 esquinas (sector anular)
    // Se comprueba qué puntos de la malla están contenidos en cada polígono
    // Para un punto [R=20NM, A=5º], la celda se define a partir de R en adelante y entre 4,5º y 5,5º

    $lat_rad = $radar['lat_rad'];
    $lon_rad = $radar['lon_rad'];
    $lat90_rad = M_PI_2 - $lat_rad;  // Ángulo complementario en radianes
    $cos_lat90 = cos($lat90_rad);
    $sin_lat90 = sin($lat90_rad);
    
    $start_time = microtime(true);
    $azimuth_step = 360.0 / $radar['screening']['totalAzimuths'];
    
    logger("[00%]", false); $countPct_old = 0;
    $count_intersec = count($intersec);
    for ($azi = 0; $azi < $count_intersec; $azi++) {
        $countPct = $azi / $count_intersec;
        if ( ($countPct - $countPct_old) >= 0.10 ) { logger("[" . round($countPct*100) . "%]", false); $countPct_old = $countPct; }

        $last = 1; // se empieza en la última fila con cobertura del polígono
        // Cada columna se recorre hacia atrás empezando sin cobertura
        // $cont_i = 0;

        // Para un punto [R=20NM, A=5º], la celda se define a partir de R en adelante y entre 4,5º y 5,5º
        // $a1 = deg2rad( ($azi + 1) * $azimuth_step - ($azimuth_step / 2) );  // Primer ángulo [rad]
        // $a2 = deg2rad( ($azi + 1) * $azimuth_step + ($azimuth_step / 2) );  // Segundo ángulo [rad]
        // ojo, si tenemos 720º, en la lista de obstáculos tendremos 720 entradas. No vamos a poder entrar usando a1 como índice!
        $a1_rad = deg2rad( $azi * $azimuth_step - ($azimuth_step / 2) );  // Primer ángulo [rad]
        $a2_rad = deg2rad( $azi * $azimuth_step + ($azimuth_step / 2) );  // Segundo ángulo [rad]
        // $cos_a1 = $cos_a2 = $sin_a1 = $sin_a2 = false;

        // Cada lista de obstáculos se recorre hacia atrás empezando sin cobertura
        $p1 = $p2 = $p3 = $p4 = array(); // Esquinas del polígono
        $r1 = $r2 = 0; // Radios de las esquinas del polígono
        $count_intersec_azi = count($intersec[$azi])-1;
        for ($i = $count_intersec_azi; $i >= 0; $i--) {

            if ($intersec[$azi][$i] != 0 && $last == 1) {     // Última fila con cobertura del polígono

                // Para un punto [R=20NM, A=5º], la celda se define a partir de R y entre 4,5º y 5,5º
                // Ùltimo radio [m]
                $r2 = $intersec[$azi][$i] * MILLA_NAUTICA_EN_METROS;             // Último radio [m]
                $time_calcula_vertices_interseccion = microtime(true);
                [$p1, $p2] = calcula_vertices_interseccion(
                    $r2,
                    $a1_rad,
                    $a2_rad,
                    $cos_lat90,
                    $sin_lat90,
                    $lat_rad,
                    $lon_rad
                );
                $time_calcula_vertices_interseccion_total += microtime(true) - $time_calcula_vertices_interseccion;
                $last = 0; // marcamos que en esta intersección ya no hay cobertura del polígono
                // print "ts: " . (microtime(true) - $start_time)*1000 . PHP_EOL;
                // print "p1: " . json_encode($p1) . " p2: " . json_encode($p2) . PHP_EOL;
                if ( $debug && $azi == 34 && $i == 5) {
                    $p1_check = [43.08976905930698, 3.6356157745885453];
                    $p2_check = [43.07377059296113, 3.667011200629223];
                    print $p1_check[0] - $p1[0] . " " . $p1_check[1] - $p1[1] . PHP_EOL;
                    print $p2_check[0] - $p2[0] . " " . $p2_check[1] - $p2[1] . PHP_EOL;
                }
            }
            elseif ($last == 0) {   // Primera fila con cobertura del polígono

                // Para un punto [R=20NM, A=5º], la celda se define a partir de R y entre 4,5º y 5,5º
                $r1 = $intersec[$azi][$i] * MILLA_NAUTICA_EN_METROS;             // Último radio [m]
                $time_calcula_vertices_interseccion = microtime(true);
                [$p4, $p3] = calcula_vertices_interseccion(
                    $r1,
                    $a1_rad,
                    $a2_rad,
                    $cos_lat90,
                    $sin_lat90,
                    $lat_rad,
                    $lon_rad
                );
                $time_calcula_vertices_interseccion_total += microtime(true) - $time_calcula_vertices_interseccion;

                // print "ts: " . (microtime(true) - $start_time)*1000 . PHP_EOL;

                // print "p3: " . json_encode($p3) . " p4: " . json_encode($p4) . PHP_EOL;
                
                if ( $debug && $azi== 34 && $i == 4) {
                    $p3_check = [42.89364390134427, 3.492870493185009];
                    $p4_check = [42.90740443622323, 3.4658423401029874];
                    print $p3_check[0] - $p3[0] . " " . $p3_check[1] - $p3[1] . PHP_EOL;
                    print $p4_check[0] - $p4[0] . " " . $p4_check[1] - $p4[1] . PHP_EOL;
                }
                // Aumento de resolución
                // print "En azimut $azi, la distancia entre vertices es: " .(($r2 - $r1) / MILLA_NAUTICA_EN_METROS) . "NM" . PHP_EOL;
                if (($r2 - $r1) >= INTERSECTION_TOLERANCE_LIMIT_RAD) {   // Polígono demasiado largo
                    $n_subdivisiones = (int)floor(2 * ($r2 - $r1) / (INTERSECTION_TOLERANCE_LIMIT_M)) - 1;
                    // print "Necesarias $n_subdivisiones subdivisiones en azimut $azi, distancia entre vertices es de " . round(($r2 - $r1)/MILLA_NAUTICA_EN_METROS,2) . " NM" . PHP_EOL;
                    // print "r2: " . $r2 . " r1: " . $r1 . PHP_EOL;
                    $poly = [$p1, $p2];
                    
                    // Subdivisiones
                    $subdivisiones = array();
                    for ($s = 1; $s <= $n_subdivisiones; $s++) {
                        // Para un punto [R=20NM, A=5º], la celda se define a partir de R y entre 4,5º y 5,5º
                        $r_subd  = $r2 - $s * ($r2 - $r1) / ($n_subdivisiones+1);    // Radio de subdivisión s [m]
                        $time_calcula_vertices_interseccion = microtime(true);
                        [$ps1, $ps2] = calcula_vertices_interseccion(
                            $r_subd,
                            $a1_rad,
                            $a2_rad,
                            $cos_lat90,
                            $sin_lat90,
                            $lat_rad,
                            $lon_rad
                        );
                        $time_calcula_vertices_interseccion_total += microtime(true) - $time_calcula_vertices_interseccion;
                        // apuntamos las soluciones del azimut derecho, y guardamos las del izquierdo

                        $subdivisiones[] = $ps1;
                        $poly[] = $ps2;
                    }
                    $poly[] = $p3;
                    $poly[] = $p4;
                    //$poly = ordenarVerticesHorario($poly); // no es necesario, los vértices ya se introducen ordenados
                    // acabamos de copiar las soluciones del izquierdo que habíamos guardado, para
                    // cerrar el polígono. Es el equivalente a apuntarlos todos y ordenar los vértices
                    // en sentido horario (realmente anti-horario, pero da igual porque el algoritmo de 
                    // punto en polígono no distingue entre ambos sentidos)
                    $count_subdivisiones = count($subdivisiones) - 1;
                    for ($s = $count_subdivisiones; $s >= 0; $s--) {
                        $poly[] = $subdivisiones[$s];
                    }

                } else {
                 // Polígono sin aumento de resolución y sin cerrar (no es necesario repetir el primer punto) [lat,lon] [º]
                    $poly = [ $p1, $p2, $p3, $p4 ]; 
                }

                // Se hallan los puntos del mallado contenidos en el polígono
                $timer_malla_coverage = microtime(true);
                set_malla_coverage($malla_lat_lon, $poly, $resolucion_malla, $malla_lat_lon_rows, $malla_lat_lon_cols, $malla_lat_nw, $malla_lon_nw);
                $time_malla_coverage_total += microtime(true) - $timer_malla_coverage;
                $last = 1;
            }
        }
    }
    
    logger("[100%]" . PHP_EOL, false);
    logger(" I> Tiempo total generación malla: " . round(microtime(true) - $start_time, 3) . "s");
    logger(" I> Tiempo en set_malla_coverage: " . round($time_malla_coverage_total,3) . "s");
    logger(" I> Tiempo en calcula_vertices_interseccion: " . round($time_calcula_vertices_interseccion_total,3) . "s");
    logger(" V> " . "Info memory_usage(" . convertBytes(memory_get_usage(false)) . ") " .
        "Memory_peak_usage(" . convertBytes(memory_get_peak_usage(false)) . ")");

    $segments = marchingSquares($malla_lat_lon);
    $polygons = buildPolygonsFromSegments($segments);

    return $polygons;

}

/*
 * Función que calcula los vértices de intersección entre el círculo definido por el radio r y el sector anular definido por los ángulos a1 y a2
 * Se optimiza para no calcular sin(a1), cos(a1), sin(a2), cos(a2)
 * Se corrige el azimuth con una tolerancia para asegurar solape entre polígonos
 * @param float $cos_lat90 depende del radar, no cambia
 * @param float $sin_lat90 depende del radar, no cambia
 * @param float $lat_rad depende del radar, no cambia
 * @param float $lon_rad depende del radar, no cambia
 * 
 * @return array con los vértices de intersección [lat, lon] [º]
 */
function calcula_vertices_interseccion(
    float $r,
    float $a1_rad,
    float $a2_rad,
    float $cos_lat90,
    float $sin_lat90,
    float $lat_rad,
    float $lon_rad
) {

    static $alpha_cache = [];
    static $a1a2_cache = [];

    $r = round($r,0);

    $a1_rad  = round($a1_rad,2);
    $a2_rad  = round($a2_rad,2);
    
    if (!isset($alpha_cache[(string)$r])) {
        $alpha = $r / RADIO_TERRESTRE;
        $cos_alpha = cos($alpha);
        $sin_alpha = sin($alpha);
        $alpha_cache[(string)$r] = [
            'cos' => $cos_alpha,
            'sin' => $sin_alpha
        ];
    } else {
        $cos_alpha = $alpha_cache[(string)$r]['cos'];
        $sin_alpha = $alpha_cache[(string)$r]['sin'];
    }

    if (!isset($a1a2_cache[(string)$a1_rad])) {
        $cos_a1 = cos($a1_rad);
        $sin_a1 = sin($a1_rad);
        $a1a2_cache[(string)$a1_rad] = [
            'cos' => $cos_a1,
            'sin' => $sin_a1
        ];
    } else {
        $cos_a1 = $a1a2_cache[(string)$a1_rad]['cos'];
        $sin_a1 = $a1a2_cache[(string)$a1_rad]['sin'];
    }
    
    if (!isset($a1a2_cache[(string)$a2_rad])) {
        $cos_a2 = cos($a2_rad);
        $sin_a2 = sin($a2_rad);
        $a1a2_cache[(string)$a2_rad] = [
            'cos' => $cos_a2,
            'sin' => $sin_a2
        ];
    } else {
        $cos_a2 = $a1a2_cache[(string)$a2_rad]['cos'];
        $sin_a2 = $a1a2_cache[(string)$a2_rad]['sin'];
    }
    
    $cos_lat90xcos_alpha2 = $cos_lat90 * $cos_alpha;
    $sin_lat90xsin_alpha2 = $sin_lat90 * $sin_alpha;

    // Teorema del Coseno Esférico - Latitud
    $cos_lat1 = $cos_lat90xcos_alpha2 +
        $sin_lat90xsin_alpha2 * $cos_a1;
    $asin_lat1_rad = asin($cos_lat1);
    $lat1_rad = $asin_lat1_rad - $lat_rad;

    $cos_lat2 = $cos_lat90xcos_alpha2 +
        $sin_lat90xsin_alpha2 * $cos_a2;
    $asin_lat2_rad = asin($cos_lat2);
    $lat2_rad = $asin_lat2_rad - $lat_rad;

    // Teorema del Coseno Esférico - Longitud (optimizada para no usar cos(asin_lat1_rad))
    $cos_par1 = cos($lat1_rad) * $cos_alpha +
        sin($lat1_rad) * $sin_alpha * $cos_a1;
    $cos_par2 = cos($lat2_rad) * $cos_alpha +
        sin($lat2_rad) * $sin_alpha * $cos_a2;

    $cos_lat_abs1 = sqrt(1.0 - $cos_lat1 * $cos_lat1);
    $cos_lat_abs2 = sqrt(1.0 - $cos_lat2 * $cos_lat2);
    $lon1_d = acos($cos_par1) / $cos_lat_abs1;
    $lon2_d = acos($cos_par2) / $cos_lat_abs2;

    // Signo según sin(ángulo)
    $lon1_d = $lon_rad + (($sin_a1 >= 0) ? $lon1_d : -$lon1_d);
    $lon2_d = $lon_rad + (($sin_a2 >= 0) ? $lon2_d : -$lon2_d);

    // Corrección al acimut (+ x metros para asegurar solape entre polígonos)
    // a1
    $asin_lat1_rad += INTERSECTION_TOLERANCE_LIMIT_RAD * $sin_a1;
    $lon1_d -= INTERSECTION_TOLERANCE_LIMIT_RAD * $cos_a1;
    // a2
    $asin_lat2_rad -= INTERSECTION_TOLERANCE_LIMIT_RAD * $sin_a2;
    $lon2_d += INTERSECTION_TOLERANCE_LIMIT_RAD * $cos_a2;

    $p1 = [rad2deg($asin_lat1_rad), rad2deg($lon1_d)];
    $p2 = [rad2deg($asin_lat2_rad), rad2deg($lon2_d)];

    return [$p1, $p2];
}

/*
 * Marca en la malla los puntos que están dentro del polígono
 * Se accede directamente a la malla sin necesidad de consultar las coordenadas,
 * dado que conocemos el salto de la malla, que [0][0] es la esquina superior noroeste
 * y que a mayor $i, la latitud decrece [99][99] será la esquina sureste.
 * int $num_rows numero de filas
 * int $num_cols número de columnas
 */ 

function set_malla_coverage(array &$malla_lat_lon, array &$poly, float $paso_de_malla, int $num_rows, int $num_cols, float $lat_nw, float $lon_nw)
{
    // Bounding box del polígono en coordenadas
    $minLat = INF;  $maxLat = -INF;
    $minLon = INF;  $maxLon = -INF;
    foreach ($poly as $p) {
        if ($p[0] < $minLat) $minLat = $p[0];
        if ($p[0] > $maxLat) $maxLat = $p[0];
        if ($p[1] < $minLon) $minLon = $p[1];
        if ($p[1] > $maxLon) $maxLon = $p[1];
    }

    // Convertir bounding box a índices, con margen de 1 celda por redondeo
    $i_min = max(0,           (int) floor(($lat_nw - $maxLat) / $paso_de_malla));
    $i_max = min($num_rows - 1, (int) ceil (($lat_nw - $minLat) / $paso_de_malla));
    $j_min = max(0,           (int) floor(($minLon - $lon_nw) / $paso_de_malla));
    $j_max = min($num_cols  - 1, (int) ceil (($maxLon - $lon_nw) / $paso_de_malla));

    $count_poly = count($poly);

    // Iterar solo sobre la subregión de la malla que intersecta el polígono
    for ($i = $i_min; $i <= $i_max; $i++) {
        $lat = $lat_nw - $i * $paso_de_malla;   // lat del punto sin leer el array
        for ($j = $j_min; $j <= $j_max; $j++) {
            $lon = $lon_nw + $j * $paso_de_malla;   // lon del punto sin leer el array
            if (pointInPolygon($lat, $lon, $poly, $count_poly)) {
                $malla_lat_lon[$i][$j][2] = 1;
            }
        }
    }

    return $malla_lat_lon;
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
function obtieneMaxAnguloConCoberturaA(array $distanciasAlcances) {
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
 * Genera la malla donde marcar la cobertura
 * arrray $radar datos para ubicar el centro de la malla en el radar
 * int $precision_malla Número de cifras decimales
 * float $resolucion_malla LSB del salto de una celda de la malla a la siguiente, depende de precision_malla
 * return array Matriz con índices genéricos para anotar los obstáculos 
 */
function create_malla(array $radar, float $max_distancia_nm, int $precision_malla, float $resolucion_malla)
{
    $lat_rad = $radar['lat_rad'];
    // $lon_rad = $radar['lon_rad'];
    $lat_deg = $radar['lat_deg'];
    $lon_deg = $radar['lon_deg'];
    
    $max_distancia_nm = round(ceil($max_distancia_nm), 0) + 1; // Redondear hacia arriba y sumar 1 NM de margen
    logger(" V> Distancia Alcance Máximo Alineada: $max_distancia_nm NM / " . $max_distancia_nm * MILLA_NAUTICA_EN_METROS . " m");
    $range_maximum = $max_distancia_nm * MILLA_NAUTICA_EN_METROS; // Rango máximo [m]
    
    $latitude_limit = rad2deg($range_maximum / RADIO_TERRESTRE); // Latitud límite desde el radar [º]
    $longitude_limit = rad2deg($range_maximum / (RADIO_TERRESTRE * cos($lat_rad)));   // Longitud límite desde el radar [º]
    //print "range_maximum: $range_maximum latitude_limit: $latitude_limit longitude_limit: $longitude_limit" . PHP_EOL;

    $north = round(ceil(($lat_deg + $latitude_limit) / $resolucion_malla) * $resolucion_malla, $precision_malla); // Límite norte
    $south = round(floor(($lat_deg - $latitude_limit) / $resolucion_malla) * $resolucion_malla, $precision_malla); // Límite sur
    $west  = round(floor(($lon_deg - $longitude_limit) / $resolucion_malla) * $resolucion_malla, $precision_malla); // Límite oeste
    $east  = round(ceil(($lon_deg + $longitude_limit) / $resolucion_malla) * $resolucion_malla, $precision_malla); // Límite este
    logger(" V> Esquinas de la malla north: $north south: $south east: $east west: $west");

    // Malla: rows = latitud, cols = longitud
    $rows = intval(abs($north - $south) / $resolucion_malla) + 1;
    $cols = intval(abs($east - $west) / $resolucion_malla) + 1;

    print $rows . " " . $cols . PHP_EOL;

    $malla_lat_lon = array();
    for ($i = 0; $i < $rows; $i++) {
        for ($j = 0; $j < $cols; $j++) {
            $malla_lat_lon[$i][$j] = [
                $north - $i * $resolucion_malla,
                $west  + $j * $resolucion_malla,
                0,
            ];
        }
    }

    return array($malla_lat_lon, $rows, $cols, $north, $west);
}

/*
 * The Ramer–Douglas–Peucker algorithm is a line simplification algorithm
 * for reducing the number of points used to define its shape.
 * https://rosettacode.org/wiki/Ramer-Douglas-Peucker_line_simplification#PHP
 *
 * @param array $points lista de parejas de puntos con un polígono a simplificar.
 * @param float $epsilon límite para descartar puntos demasiado juntos.
 * @return array lista de puntos simplificada
 */
function ramer_douglas_peucker(array $points, float $epsilon) {
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
 * Implementación del algoritmo de Marching Squares para extraer segmentos de contorno
 * de una matriz binaria.
 *
 * @param array $grid matriz 2D de la forma [[lat, lon, value], ...]
 * @return array lista de segmentos, cada segmento es un par de puntos [[lat, lon], [lat, lon]]
 */ 
function marchingSquares(array $grid): array
{
    $rows = count($grid);
    if ($rows < 2) return [];

    $cols = count($grid[0]);
    if ($cols < 2) return [];

    $segments = [];

    for ($y = 0; $y < $rows - 1; $y++) {
        for ($x = 0; $x < $cols - 1; $x++) {

            // Esquinas celda 2x2
            $tl = $grid[$y][$x];
            $tr = $grid[$y][$x + 1];
            $br = $grid[$y + 1][$x + 1];
            $bl = $grid[$y + 1][$x];

            $v0 = $tl[2] ? 1 : 0; // top-left
            $v1 = $tr[2] ? 1 : 0; // top-right
            $v2 = $br[2] ? 1 : 0; // bottom-right
            $v3 = $bl[2] ? 1 : 0; // bottom-left

            $state = ($v0 << 3) | ($v1 << 2) | ($v2 << 1) | $v3;

            if ($state === 0 || $state === 15) {
                continue;
            }

            // Midpoints edges
            $top    = midpoint($tl, $tr);
            $right  = midpoint($tr, $br);
            $bottom = midpoint($bl, $br);
            $left   = midpoint($tl, $bl);

            switch ($state) {

                case 1:   // 0001
                case 14:  // 1110
                    $segments[] = [$left, $bottom];
                    break;

                case 2:
                case 13:
                    $segments[] = [$bottom, $right];
                    break;

                case 3:
                case 12:
                    $segments[] = [$left, $right];
                    break;

                case 4:
                case 11:
                    $segments[] = [$top, $right];
                    break;

                case 5:
                    $segments[] = [$top, $left];
                    $segments[] = [$bottom, $right];
                    break;

                case 6:
                case 9:
                    $segments[] = [$top, $bottom];
                    break;

                case 7:
                case 8:
                    $segments[] = [$top, $left];
                    break;

                case 10:
                    $segments[] = [$top, $right];
                    $segments[] = [$left, $bottom];
                    break;
            }
        }
    }

    return $segments;
}

/*
    * Helper function para marchingSquares, calcula el punto medio entre dos puntos.
    * @param array $a punto [lat, lon, value]
    * @param array $b punto [lat, lon, value]
    * @return array punto medio [lat, lon]
    */  
function midpoint(array $a, array $b): array
{
    return [
        ($a[0] + $b[0]) / 2, // lat
        ($a[1] + $b[1]) / 2  // lon
    ];
}

/**
 * Entrada:
 * $segments = [
 *   [
 *     ['lat'=>..., 'lon'=>...],
 *     ['lat'=>..., 'lon'=>...]
 *   ],
 *   ...
 * ];
 *
 * Salida:
 * [
 *   [ [p1], [p2], [p3], ..., [p1] ],   // polígono cerrado
 *   ...
 * ]
 */

function buildPolygonsFromSegments(array $segments, int $precision = 8): array
{
    $adj = [];

    foreach ($segments as $seg) {
        $a = $seg[0];
        $b = $seg[1];

        $ka = pointKey($a, $precision);
        $kb = pointKey($b, $precision);

        $adj[$ka]['point'] = $a;
        $adj[$kb]['point'] = $b;

        $adj[$ka]['neighbors'][] = $kb;
        $adj[$kb]['neighbors'][] = $ka;
    }

    $visitedEdges = [];
    $polygons = [];

    foreach ($adj as $startKey => $node) {
        foreach ($node['neighbors'] as $nextKey) {

            $edgeId = edgeKey($startKey, $nextKey);

            if (isset($visitedEdges[$edgeId])) {
                continue;
            }

            $polygon = tracePolygon($adj, $startKey, $nextKey, $visitedEdges);

            if (count($polygon) >= 4) {
                $polygons[] = $polygon;
            }
        }
    }

    return $polygons;
}

function tracePolygon(array $adj, string $startKey, string $nextKey, array &$visitedEdges): array
{
    $polygon = [];
    $prev = $startKey;
    $curr = $nextKey;

    $polygon[] = $adj[$startKey]['point'];
    $polygon[] = $adj[$nextKey]['point'];

    $visitedEdges[edgeKey($startKey, $nextKey)] = true;

    while (true) {

        $neighbors = $adj[$curr]['neighbors'];

        $candidate = null;

        foreach ($neighbors as $n) {
            if ($n !== $prev) {
                $candidate = $n;
                break;
            }
        }

        if ($candidate === null) {
            return [];
        }

        $eid = edgeKey($curr, $candidate);
        $visitedEdges[$eid] = true;

        if ($candidate === $startKey) {
            $polygon[] = $adj[$startKey]['point'];
            return $polygon;
        }

        $polygon[] = $adj[$candidate]['point'];

        $prev = $curr;
        $curr = $candidate;

        if (count($polygon) > 100000) {
            return [];
        }
    }
}

function pointKey(array $p, int $precision = 8): string
{
    return round($p[0], $precision) . ',' . round($p[1], $precision);
}

function edgeKey(string $a, string $b): string
{
    return strcmp($a, $b) < 0 ? "$a|$b" : "$b|$a";
}
