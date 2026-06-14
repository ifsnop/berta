<?php

use Ifsnop\MartinezRueda as MR;

// Más velocidad y menos precisión aumentando INTERSECTION_SUBDIVISION_LIMIT
// Más precisión y menos velocidad disminuyendo INTERSECTION_SUBDIVISION_LIMIT
const BERTA_INTERSECTION_TOLERANCE_LIMIT_NM = 20; // Tolerancia para asegurar solape entre azimuths (NM)
const BERTA_INTERSECTION_TOLERANCE_LIMIT_RAD = BERTA_INTERSECTION_TOLERANCE_LIMIT_NM / RADIO_TERRESTRE;
const BERTA_INTERSECTION_TOLERANCE_LIMIT_M = BERTA_INTERSECTION_TOLERANCE_LIMIT_NM * MILLA_NAUTICA_EN_METROS; // Distancia máxima entre subdivisiones entre vértices
const BERTA_MALLA_TOO_SMALL_CHECK = 22500; // Si la malla es menor que este número de celdas, se considera demasiado pequeña y se vuelve a intentar con más precisión

/**
 * Calcula el ángulo de máxima cobertura del radar sobre la superficie terrestre.
 *
 * Se modela un triángulo cuyos tres vértices son:
 *   - El centro de la Tierra
 *   - El radar, situado a una distancia del centro igual a la suma del radio
 *     terrestre aumentado + altura del terreno + altura de la torre ($earthToRadar)
 *   - El objetivo, situado a una distancia del centro igual al radio terrestre
 *     aumentado + el flight level en metros ($earthToFl)
 *
 * El tercer lado del triángulo es el alcance máximo del radar convertido a metros.
 *
 * Aplicando la ley de cosenos:
 *
 *   cos(α) = (earthToRadar² + earthToFl² - range²) / (2 · earthToRadar · earthToFl)
 *
 * Se despeja α, que es el ángulo geocéntrico (en el vértice del centro de la Tierra)
 * entre el radar y el punto más lejano alcanzable en el flight level indicado.
 * Este ángulo representa la separación angular máxima sobre la superficie esférica
 * terrestre que el radar es capaz de cubrir para ese nivel de vuelo.
 *
 * @param array $radar   Datos del radar, incluyendo geometría de emplazamiento y alcance
 * @param float $flm     Flight level objetivo en metros
 * @return float         Ángulo de máxima cobertura en radianes
 */
function calculaAnguloMaximaCobertura($radar, $flm){

    $debug = false;
    // Distancias desde el centro de la Tierra hasta el radar y hasta el objetivo,
    // modelando la Tierra como una esfera de radio aumentado (corrección por refracción)
    $earthToRadar = $radar['screening']['towerHeight'] +
        $radar['screening']['terrainHeight'] +
        $radar['screening']['radioTerrestreAumentado'];
    $earthToFl = $radar['screening']['radioTerrestreAumentado'] + $flm;
    // Ley de cosenos aplicada al triángulo geocéntrico:
    // vértices en el centro de la Tierra, el radar y el objetivo al flight level dado.
    // El ángulo resultante es la separación angular geocéntrica máxima alcanzable.
    $anguloMaxCob = acos(
        (pow($earthToRadar,2) + pow($earthToFl,2) - pow($radar['range']*MILLA_NAUTICA_EN_METROS,2))
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
 * Calcula las coordenadas del polígono que representa el cono de silencio de un radar.
 *
 * El cono de silencio es la zona circular sobre la vertical del radar donde
 * no llega el haz de emisión. Se aproxima como un polígono de tantos vértices
 * como azimuths totales tiene configurado el radar.
 *
 * @param array $radar    Datos del radar. Se esperan las claves:
 *                         - 'lat_rad'   float  Latitud del radar en radianes.
 *                         - 'lon'       float  Longitud del radar en grados.
 *                         - 'screening' array  Con la clave 'totalAzimuths' (int).
 * @param float $radioCono Radio del cono en millas náuticas.
 *
 * @return array Polígono envuelto en un array externo: [ [ [lat, lon], ... ] ]
 */
function calculaCoordenadasCono(array $radar, float $radioCono): array
{
    $polygon = [];

    // Precalculamos el seno y coseno de la colatitud (90° - latitud) del radar,
    // ya que son constantes para todos los azimuths.
    $colatitud = M_PI_2 - $radar['lat_rad'];
    $cosColatitud = cos($colatitud);
    $sinColatitud = sin($colatitud);

    // Paso angular entre vértices del polígono, en grados.
    $azimuthStep = 360.0 / $radar['screening']['totalAzimuths'];

    for ($azimuth = 0.0; $azimuth < 360.0; $azimuth += $azimuthStep) {
        $polygon[] = polarToLatLon($radar['lon'], $radioCono, $azimuth, $cosColatitud, $sinColatitud);
    }

    return [$polygon];
}

/**
 * Convierte una posición en coordenadas polares (distancia + azimuth) relativa
 * a un punto de referencia en la superficie terrestre a latitud/longitud en grados.
 *
 * Usa la fórmula de trigonometría esférica sobre el triángulo formado por
 * el polo norte, el punto de referencia (el radar) y el punto destino.
 *
 * @param float $lonRadar      Longitud del radar en grados.
 * @param float $rho           Distancia al punto destino en millas náuticas.
 * @param float $azimuth       Ángulo desde el norte, en grados (0–360°).
 * @param float $cosColatitud  cos(π/2 − latRadar), precalculado por el llamador.
 * @param float $sinColatitud  sin(π/2 − latRadar), precalculado por el llamador.
 *
 * @return array Par [latitud, longitud] del punto destino, en grados.
 */
function polarToLatLon(
    float $lonRadar,
    float $rho,
    float $azimuth,
    float $cosColatitud,
    float $sinColatitud
): array {
    // Ángulo central subtendido por la distancia $rho sobre la esfera terrestre.
    $anguloCentral = $rho * MILLA_NAUTICA_EN_METROS / RADIO_TERRESTRE;

    // Colatitud del punto destino mediante la regla del coseno esférico.
    // cos(c) = cos(a)·cos(b) + sin(a)·sin(b)·cos(C)
    // donde 'a' es la colatitud del radar, 'b' el ángulo central y 'C' el azimuth.
    $colatitudDestino = acos(
        $cosColatitud * cos($anguloCentral) +
        $sinColatitud * sin($anguloCentral) * cos(deg2rad($azimuth))
    );

    // Latitud del destino: lat = 90° − colatitud
    $latDestino = 90.0 - rad2deg($colatitudDestino);

    // Diferencia de longitud mediante la segunda regla del coseno esférico.
    // cos(b) = cos(a)·cos(c) + sin(a)·sin(c)·cos(B)  →  despejamos cos(B)
    $numerador   = cos($anguloCentral) - $cosColatitud * cos($colatitudDestino);
    $denominador = $sinColatitud * sin($colatitudDestino);

    // Si el denominador es cero o el cociente supera 1 (polo), el offset es 0.
    $offsetLon = ($denominador == 0.0 || $numerador >= $denominador)
        ? 0.0
        : rad2deg(acos($numerador / $denominador));

    // El offset de longitud se suma (Este) para azimuths < 180° y se resta (Oeste) para el resto.
    $lonDestino = ($azimuth < 180.0)
        ? $lonRadar + $offsetLon
        : $lonRadar - $offsetLon;

    return [$latDestino, $lonDestino];
}

/**
 * CASO A
 * Funcion que calcula las distancias a las que hay cobertura y los angulos de apantallamiento cuando el FL esta por encima del radar
 *  
 * @param array $radar (ENTRADA)
 * @param float $flm nivel de vuelo en metros (ENTRADA)
 * @return array distancias a los alcances máximos por cada azimut (SALIDA)
 */
function calculosFLencimaRadar2(array $radar, float $flm): array
{
    // hay dos calculos de alpha_max, simplemente el angulo que salga al dividir entre radioterrestre aumentado (que serán radianes)
    // o bien calcular la distancia teniendo en cuenta el nivel de vuelo y que la distancia es oblicua. así que saldrán menos 
    // radianes que el método anterior.
    $max_distancia_nm = 0; // distancia al obstáculo más lejano, en millas náuticas
    $matriz_obstaculos = create_matriz_obstaculos($radar , $flm);
    $W = $flm +  $radar['screening']['radioTerrestreAumentado'];  // Radio de circunferencia del nivel de vuelo
    $W += 0.01;                     // Suma 10 cm para evitar errores numéricos
    logger(" D> Radio Terrestre Aumentado: {$radar['screening']['radioTerrestreAumentado']}m");
    logger(" D> Radio Circunferencia al Nivel de Vuelo: {$W}m");
    // Esto es porque a veces el screening da dos valores de
    // altitud consecutivos iguales. Si además coinciden con el
    // FL, da lugar a errores al calcular la intersección ya que
    // línea y circunferencia son tangentes.
    $intersec = create_matriz_intersecciones($radar, $matriz_obstaculos, $W, $max_distancia_nm);

    $lat_rad = $radar['lat_rad'];
    $lon_rad = $radar['lon_rad'];
    $lat90_rad = M_PI_2 - $lat_rad;  // Ángulo complementario en radianes
    $cos_lat90 = cos($lat90_rad);
    $sin_lat90 = sin($lat90_rad);
    $azimuth_step = 360.0 / $radar['screening']['totalAzimuths'];

    $polygons = array();

    $count_intersec = count($intersec);
    for ($azimuth = 0; $azimuth < $count_intersec; $azimuth++) {
        $azimuth_real = $azimuth * $azimuth_step;
        $a_rad = deg2rad($azimuth_real); //* $azimuth_step - ($azimuth_step / 2) );  // Primer ángulo [rad]

        // Cada lista de obstáculos se recorre hacia atrás empezando sin cobertura
        $p = array(); // Esquina del polígono
        $r = 0; // Radio de las esquina del polígono
        $count_intersec_azi = count($intersec[$azimuth]) - 1;

        for ($i = $count_intersec_azi; $i >= 0; $i--) {
            $r = $intersec[$azimuth][$i] * MILLA_NAUTICA_EN_METROS;             // Último radio [m]
            [$p] = calcula_vertices_interseccion(
                $r,
                $a_rad,
                null,
                $cos_lat90,
                $sin_lat90,
                $lat_rad,
                $lon_rad
            );
            $polygons[] = $p;
        }
    }
    return [$polygons];
}

function create_muro(array &$radar, float $xa, float $ya, float $xb, float $yb, float $m) {

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
        $xi = $xa + $t * $dx;   // Punto de intersección
        $yi = $ya + $t * $dy;   // Punto de intersección

        $alpha_m = atan($m);
        $xil = (BERTA_MAX_WALL_HEIGHT + $radar['screening']['radioTerrestreAumentado']) * cos($alpha_m); // Punto límite del muro
        $yil = (BERTA_MAX_WALL_HEIGHT + $radar['screening']['radioTerrestreAumentado']) * sin($alpha_m); // Punto límite del muro

        if ($yi > $yil) $yil = $yi; // Punto de intersección por encima del límite del muro
        return array(
            $xi,
            $yi,
            $xil,
            $yil,
        );
    }
    return false;
}

/*
 * CASO B
 * Funcion que calcula las coberturas cuando el nivel de vuelo FL, esta por debajo del radar
 *
 * @param array $radar (ENTRADA / SALIDA)
 * @param float $flm nivel de vuelo en metros (ENTRADA)
 * @return array malla[i][j] = array(lat,lon,cobertura)
 */
function calculosFLdebajoRadar2(array &$radar, float $flm) {

    $max_distancia_nm = 0; // distancia al obstáculo más lejano, en millas náuticas
    $matriz_obstaculos = create_matriz_obstaculos($radar, $flm);

    /*******************************
    * INTERSECCIONES POR ACIMUT
    *******************************/
    $W = $flm +  $radar['screening']['radioTerrestreAumentado'];  // Radio de circunferencia del nivel de vuelo
    $W += 0.01;                     // Suma 10 cm para evitar errores numéricos
    // Esto es porque a veces el screening da dos valores de altitud consecutivos
    // iguales. Si además coinciden con el FL, da lugar a errores al calcular
    //la intersección ya que línea y circunferencia son tangentes.
    logger(" D> Radio Terrestre Aumentado: {$radar['screening']['radioTerrestreAumentado']}m");
    logger(" D> Radio Circunferencia al Nivel de Vuelo: {$W}m");

    $intersec = create_matriz_intersecciones($radar, $matriz_obstaculos, $W, $max_distancia_nm);

    /*******************************
     * MALLA DE COBERTURA
     ******************************/
    // Este paso ha quedado obsoleto. Desde que unimos los polígonos usando martinez-rueda, ya no es necesario
    // crear la malla de cobertura. Antes se preguntaban que celdas de la malla caían dentro de los polígonos, y
    // se rellenaban rasterizando los polígonos en la malla.Se mantiene el código comentado por
    // si se quiere volver a usar en el futuro.
    /*
     * esta configuración (precision = 2, resolucion = 0.01
     * produce una malla de 395x529
     * 43.74 -0.2    43.74 -0.19    43.74 -0.18    
     * 43.73 -0.2    43.73 -0.19    43.73 -0.18    
     * 43.72 -0.2    43.72 -0.19    43.72 -0.18  
     */
    // $precision_malla = 2;
    // $resolucion_malla = pow(10, -$precision_malla);  // Resolución vertical [º] -> 0.01º  que equivale a 1.11 km
    // $malla = create_malla($radar, $max_distancia_nm, $precision_malla, $resolucion_malla);
    // if ( 0 == count($malla) ) {
    //     $precision_malla++;
    //     $resolucion_malla = pow(10, -$precision_malla);  // Resolución vertical [º] -> 0.01º  que equivale a 1.11 km
    //     $malla = create_malla($radar, $max_distancia_nm, $precision_malla, $resolucion_malla, true);
    // }

    // [$malla_lat_lon, $malla_lat_lon_rows, $malla_lat_lon_cols, $malla_lat_nw, $malla_lon_nw] = $malla;
    // Código de depuración (imprime la esquina sup izq de la malla en 3x3)
    // for($i=0;$i<3; $i++) { for($j=0; $j<3; $j++) {
    //    print $malla_lat_lon[$i][$j][0] . " " . $malla_lat_lon[$i][$j][1] . "    ";
    // } print PHP_EOL; }

    /*******************************
     * POLÍGONO DE COBERTURA
     *******************************/

    $polygons = create_poligonos_cobertura($radar, $intersec/*, $malla*/);
    return $polygons;

    // for($i=0; $i<count($malla_lat_lon); $i++) {
    //    for($j=0;$j<count($malla_lat_lon[$i]); $j++) {
    //        print $malla_lat_lon[$i][$j][2];
    //    }
    //    print PHP_EOL;
    // }
    // $segments = marchingSquares($malla_lat_lon);
    // $polygons = buildPolygonsFromSegments($segments);
}

/**
 * Genera polígonos de cobertura que cubren las intersecciones y modifica la malla para marcar
 * con 1's y 0's las zonas donde hay cobertura.
 * Se tiene en cuenta el número de azimuts que existen en el screening, dado que la precisión
 * de PREDICT es configurable.
 * @param array $radar parámetros de radar
 * @param array $intersec lista de intersecciones para cada azimut
 * //param array $malla malla inicializada a 0's del tamaño de la cobertura (desde que unimos con martinz rueda, no se usa la malla)
 *
 * @return array malla de cobertura (malla_lat_lon)
 */
function create_poligonos_cobertura(array &$radar, array &$intersec/* , array &$malla */) {
    // Cada celda es un polígono de 4 esquinas (sector anular)
    // Se comprueba qué puntos de la malla están contenidos en cada polígono
    // Para un punto [R=20NM, A=5º], la celda se define a partir de R en adelante y entre 4,5º y 5,5º

    $debug = false;
    $start_time = microtime(true);

    $time_malla_coverage_total = 0;
    $time_calcula_vertices_interseccion_total = 0;

    // [$malla_lat_lon, $malla_lat_lon_rows, $malla_lat_lon_cols, $malla_lat_nw, $malla_lon_nw, $resolucion_malla] = $malla;

    $polygons = array();

    $lat_rad = $radar['lat_rad'];
    $lon_rad = $radar['lon_rad'];
    $lat90_rad = M_PI_2 - $lat_rad;  // Ángulo complementario en radianes
    $cos_lat90 = cos($lat90_rad);
    $sin_lat90 = sin($lat90_rad);
    $azimuth_step = 360.0 / $radar['screening']['totalAzimuths']; 
    
    logger("[00%]", false); $countPct_old = 0;
    $count_intersec = count($intersec);
    for ($azimuth = 0; $azimuth < $count_intersec; $azimuth++) {
        $countPct = $azimuth / $count_intersec;
        if ( ($countPct - $countPct_old) >= 0.10 ) { logger("[" . round($countPct*100) . "%]", false); $countPct_old = $countPct; }

        // transformar azimuth en un azimuth real
        $azimuth_real = $azimuth * $azimuth_step; // MAL

        $last = 1; // se empieza en la última fila con cobertura del polígono

        // Para un punto [R=20NM, A=5º], la celda se define a partir de R en adelante y entre 4,5º y 5,5º
        $a1_rad = deg2rad( $azimuth_real - ($azimuth_step / 2) );  // Primer ángulo [rad]
        $a2_rad = deg2rad( $azimuth_real + ($azimuth_step / 2) );  // Segundo ángulo [rad]

        // Cada lista de obstáculos se recorre hacia atrás empezando sin cobertura
        $p1 = $p2 = $p3 = $p4 = array(); // Esquinas del polígono
        $r1 = $r2 = 0; // Radios de las esquinas del polígono
        $count_intersec_azi = count($intersec[$azimuth])-1;
        for ($i = $count_intersec_azi; $i >= 0; $i--) {

            if ($intersec[$azimuth][$i] != 0 && $last == 1) {     // Última fila con cobertura del polígono

                // Para un punto [R=20NM, A=5º], la celda se define a partir de R y entre 4,5º y 5,5º
                // Ùltimo radio [m]
                $r2 = $intersec[$azimuth][$i] * MILLA_NAUTICA_EN_METROS;             // Último radio [m]
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
            }
            elseif ($last == 0) {   // Primera fila con cobertura del polígono

                // Para un punto [R=20NM, A=5º], la celda se define a partir de R y entre 4,5º y 5,5º
                $r1 = $intersec[$azimuth][$i] * MILLA_NAUTICA_EN_METROS;             // Último radio [m]
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

                // Aumento de resolución
                if (($r2 - $r1) >= BERTA_INTERSECTION_TOLERANCE_LIMIT_RAD) {   // Polígono demasiado largo
                    $n_subdivisiones = (int)floor(2 * ($r2 - $r1) / (BERTA_INTERSECTION_TOLERANCE_LIMIT_M)) - 1;
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
                    // $poly = ordenarVerticesHorario($poly); // no es necesario, los vértices ya se introducen ordenados
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
                $polygons[] = $poly;
                // Se hallan los puntos del mallado contenidos en el polígono
                // $timer_malla_coverage = microtime(true);
                // set_malla_coverage($malla_lat_lon, $poly, $resolucion_malla, $malla_lat_lon_rows, $malla_lat_lon_cols, $malla_lat_nw, $malla_lon_nw);
                // $time_malla_coverage_total += microtime(true) - $timer_malla_coverage;
                $last = 1;
            }
        }
    }

    logger("[100%]" . PHP_EOL, false);
    logger(" D> Tiempo total generación malla: " . round(microtime(true) - $start_time, 3) . "s");
    // logger(" I> Tiempo en set_malla_coverage: " . round($time_malla_coverage_total,3) . "s");
    logger(" D> Tiempo en calcula_vertices_interseccion: " . round($time_calcula_vertices_interseccion_total,3) . "s");
    logger(" D> " . "Info memory_usage(" . convertBytes(memory_get_usage(false)) . ") " .
        "Memory_peak_usage(" . convertBytes(memory_get_peak_usage(false)) . ")");
    $n_polygons = count($polygons);
    logger(" V> Número de polígonos para la unión: " . $n_polygons);
    if ( $n_polygons == 0) {
        logger(" D> No hay polígonos de cobertura, se devuelve un polígono vacío");
        return array();
    }
    $time_union = microtime(true);
    $mr_polygons = array();
    foreach($polygons as $polygon) {
        $mr_polygons[] = MR\Polygon::create()->fillFromArray($polygon);
    }
    $p_mr1 = MR\Algorithm::unionMany($mr_polygons);
    logger(" D> Tiempo en union: " . round(microtime(true) - $time_union,3) . "s");
    logger(" D> " . "Info memory_usage(" . convertBytes(memory_get_usage(false)) . ") " .
        "Memory_peak_usage(" . convertBytes(memory_get_peak_usage(false)) . ")");
    return $p_mr1->getArray();
}

/*
 * Convierte el fichero de screening en una matriz de obstáculos teniendo en cuenta el radio terrestre
 * @param array $radar Datos del sensor
 * @param float $flm Nivel de vuelo en metros
 * @return array matriz de obstáculos con respecto al centro de la tierra
 *
 */
function create_matriz_obstaculos(array &$radar, float $flm)
{
    $debug = false;

    // Ángulo central máximo según rango en millas (sin tener en cuenta el nivel de vuelo)
    // $alpha_max = ($radar['screening']['range'] * MILLA_NAUTICA_EN_METROS) / $radar['screening']['radioTerrestreAumentado'];

    // este si tiene en cuenta el nivel de vuelo
    $alpha_max = calculaAnguloMaximaCobertura($radar, $flm);
    logger(" V> Ángulo central máximo según rango en fichero screening: " . round($alpha_max,3) . "rad / " .
         round(rad2deg($alpha_max),3) . "º / " .
         round($alpha_max*$radar['screening']['radioTerrestreAumentado']/MILLA_NAUTICA_EN_METROS,3) .  "NM");
    // línea de rango máximo
    $m = tan(pi() / 2 - $alpha_max); // pi()/2 = 90º en radianes

    $matriz_obstaculos = []; // la estructura es [numero de azimuth][numero de obstaculo]
    foreach ($radar['screening']['listaAzimuths'] as $azimut => $listaObstaculos) {
        $xa = $xb = $yb = $alt = 0.0;
        $ya = $radar['screening']['towerHeight'] +
            $radar['screening']['terrainHeight'] +
            $radar['screening']['radioTerrestreAumentado'];
        // se añade el radar como primer obstáculo (ojo porque ya estaba añadido al cargar screening)
        // $matriz_obstaculos[$azimut][0] = [$xa, $ya];
        if ($debug)
            print "[0,$azimut] alt: $alt => $xa,$ya" . PHP_EOL;

        $i = 0;
        foreach ($listaObstaculos as $i => $obstaculo) {
            $alt = $obstaculo['altura']; // Altitud del obstáculo i
            $ang = $obstaculo['angulo']; // Ángulo central del obstáculo i
            if ( $ang > $alpha_max ) { // generar muro
                // print "Generando muro en azimut $azimut, obstáculo $i, altitud $alt, ángulo $ang" . PHP_EOL;
                $i--; // el muro siempre se añade a continuación del obstáculo, pero
                // en este caso lo añadimos en lugar del actual.
                break;
            }

            $r = $radar['screening']['radioTerrestreAumentado'] + $alt; // Radio del obstáculo
            $xb = $r * sin($ang); // Coordenada horizontal del obstáculo
            $yb = $r * cos($ang); // Coordenada vertical del obstáculo
            if ($debug)
                print "[" . ($i + 1) . ",$azimut] alt: $alt, ang: $ang, r: $r => $xb,$yb" . PHP_EOL;

            $matriz_obstaculos[$azimut][$i] = [$xb, $yb];
        }

        $i++;
        // Si no existe muro final, se calcula el muro: el primer punto será la intersección
        // entre la línea que forman el radar y el último punto con la línea de máximo rango.
        if ($alt <= 30000) {
            $ret = create_muro($radar, $xa, $ya, $xb, $yb, $m);
            if ( $ret !== false ) { // no es necesario insertarlo porque el muro queda detrás del último obstáculo
                [$xi, $yi, $xil, $yil] = $ret;
                // Se añaden los dos nuevos puntos
                $matriz_obstaculos[$azimut][$i++] = [$xi, $yi];
                $matriz_obstaculos[$azimut][$i++] = [$xil, $yil];
                // print "[$azimut, $i] => $xi,$yi" . PHP_EOL;
                // print "[$azimut, $i] => $xil,$yil" . PHP_EOL;
            }
        }
    }

    return $matriz_obstaculos;
}

/*
 * Calcula intersecciones a partir de la matriz de obstáculos
 * @param array $radar parámetros del radar 
 * @param array $matriz_obstaculos
 * @param float $W radio de la circunferencia del nivel de vuelo
 * @param float $max_distancia_nm se actualiza la máxima distancia de cobertura en NM después de los cálculos
 * @return array matriz intersecciones
 */
function create_matriz_intersecciones(array &$radar, array &$matriz_obstaculos, float $W, float &$max_distancia_nm) {
    $debug = false;

    // Intersección entre nivel de vuelo (arco de circunferencia) y polilínea de screening para acimuth a
    $intersec = array();
    for ($azimuth = 0; $azimuth < count($matriz_obstaculos); $azimuth++) {
        $count = 0;
        $intersec[$azimuth] = array();
        for ($obs = 0; $obs < count($matriz_obstaculos[$azimuth]) - 1; $obs++) {

            $x1 = $matriz_obstaculos[$azimuth][$obs][0];
            $y1 = $matriz_obstaculos[$azimuth][$obs][1];
            $x2 = $matriz_obstaculos[$azimuth][$obs + 1][0];
            $y2 = $matriz_obstaculos[$azimuth][$obs + 1][1];

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
            //print "$azimuth,$obs: " . json_encode($matriz_obstaculos[$azimuth][$obs]) . PHP_EOL;
            //print "rowsX: $obs Azimut: $azimuth x1: $x1 y1: $y1 x2: $x2 y2: $y2" . PHP_EOL;
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
                        $intersec[$azimuth][$count] = $dist_nm;

                        if ($debug)
                            print "intersec count: $count obs: $obs azimut: $azimuth => dist_nm: $dist_nm" . PHP_EOL;

                        $count++;
                    }
                }
            }
        }
    }
    return $intersec;
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
    ?float $a2_rad,
    float $cos_lat90,
    float $sin_lat90,
    float $lat_rad,
    float $lon_rad
) {

    static $alpha_cache = [];
    static $a1a2_cache = [];

    $r = round($r, 0);

    $a1_rad  = round($a1_rad, 2);

    [$cos_alpha, $sin_alpha] = getCached($alpha_cache, (string)$r, function () use ($r) {
        $alpha = $r / RADIO_TERRESTRE;
        return [
            cos($alpha),
            sin($alpha),
        ];
    });

    [$cos_a1, $sin_a1] = getCached($a1a2_cache, (string) $a1_rad, function () use ($a1_rad) {
        return [
            cos($a1_rad),
            sin($a1_rad),
        ];
    });

    $cos_lat90xcos_alpha2 = $cos_lat90 * $cos_alpha;
    $sin_lat90xsin_alpha2 = $sin_lat90 * $sin_alpha;

    // Teorema del Coseno Esférico - Latitud
    $cos_lat1 = $cos_lat90xcos_alpha2 +
        $sin_lat90xsin_alpha2 * $cos_a1;
    $asin_lat1_rad = asin($cos_lat1);
    $lat1_rad = $asin_lat1_rad - $lat_rad;

    // Teorema del Coseno Esférico - Longitud (optimizada para no usar cos(asin_lat1_rad))
    $cos_par1 = cos($lat1_rad) * $cos_alpha +
        sin($lat1_rad) * $sin_alpha * $cos_a1;

    $cos_lat_abs1 = sqrt(1.0 - $cos_lat1 * $cos_lat1);
    $lon1_d = acos($cos_par1) / $cos_lat_abs1;

    // Signo según sin(ángulo)
    $lon1_d = $lon_rad + (($sin_a1 >= 0) ? $lon1_d : -$lon1_d);

    // Corrección al acimut (+ x metros para asegurar solape entre polígonos)
    // a1
    $asin_lat1_rad += BERTA_INTERSECTION_TOLERANCE_LIMIT_RAD * $sin_a1;
    $lon1_d -= BERTA_INTERSECTION_TOLERANCE_LIMIT_RAD * $cos_a1;

    $p1 = [rad2deg($asin_lat1_rad), rad2deg($lon1_d)];

    if ( !is_null($a2_rad) ) {
        $a2_rad  = round($a2_rad, 2);
        [$cos_a2, $sin_a2] = getCached($a1a2_cache, (string) $a2_rad, function () use ($a2_rad) {
            return [
                cos($a2_rad),
                sin($a2_rad),
            ];
        });
        $cos_lat2 = $cos_lat90xcos_alpha2 +
            $sin_lat90xsin_alpha2 * $cos_a2;
        $asin_lat2_rad = asin($cos_lat2);
        $lat2_rad = $asin_lat2_rad - $lat_rad;

        $cos_par2 = cos($lat2_rad) * $cos_alpha +
            sin($lat2_rad) * $sin_alpha * $cos_a2;

        $cos_lat_abs2 = sqrt(1.0 - $cos_lat2 * $cos_lat2);
        $lon2_d = acos($cos_par2) / $cos_lat_abs2;

        $lon2_d = $lon_rad + (($sin_a2 >= 0) ? $lon2_d : -$lon2_d);

        // a2
        $asin_lat2_rad -= BERTA_INTERSECTION_TOLERANCE_LIMIT_RAD * $sin_a2;
        $lon2_d += BERTA_INTERSECTION_TOLERANCE_LIMIT_RAD * $cos_a2;
        $p2 = [rad2deg($asin_lat2_rad), rad2deg($lon2_d)];
        return [$p1, $p2];
    }

    return [$p1];
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
    debug_print_backtrace(); die("deprecated " . __FUNCTION__ . " in " . __FILE__ . " at line " . __LINE__);
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
 * Genera la malla donde marcar la cobertura
 * arrray $radar datos para ubicar el centro de la malla en el radar
 * int $precision_malla Número de cifras decimales
 * float $resolucion_malla LSB del salto de una celda de la malla a la siguiente, depende de precision_malla
 * bool $force genera la matriz aunque tenga poca resolucion, pensado para cuando se aumenta la resolución y sigue siendo pequeña
 * return array Matriz con índices genéricos para anotar los obstáculos 
 */
function create_malla(array $radar, float $max_distancia_nm, int $precision_malla, float $resolucion_malla, bool $force = false)
{
    debug_print_backtrace(); die("deprecated " . __FUNCTION__ . " in " . __FILE__ . " at line " . __LINE__);
    $lat_rad = $radar['lat_rad'];
    // $lon_rad = $radar['lon_rad'];
    $lat_deg = $radar['lat_deg'];
    $lon_deg = $radar['lon_deg'];
    
    logger(" D> Generando malla de cobertura con precision: {$precision_malla} y resolución: {$resolucion_malla}");
    
    $max_distancia_nm = round(ceil($max_distancia_nm), 0) + 1; // Redondear hacia arriba y sumar 1 NM de margen
    logger(" V> Distancia Alcance Máximo Alineada: {$max_distancia_nm}NM / " . $max_distancia_nm * MILLA_NAUTICA_EN_METROS . "m");
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
    logger(" D> Tamaño de la malla {$rows}x{$cols}");

    // si la malla es demasiado pequeña, abortamos y volveremos a intentar con más precisión
    if ( !$force && ($rows*$cols < BERTA_MALLA_TOO_SMALL_CHECK) )
        return array();

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

    return array($malla_lat_lon, $rows, $cols, $north, $west, $resolucion_malla);
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
    debug_print_backtrace(); die("deprecated " . __FUNCTION__ . " in " . __FILE__ . " at line " . __LINE__);
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
    debug_print_backtrace(); die("deprecated " . __FUNCTION__ . " in " . __FILE__ . " at line " . __LINE__);
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
    debug_print_backtrace(); die("deprecated " . __FUNCTION__ . " in " . __FILE__ . " at line " . __LINE__);
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
    debug_print_backtrace(); die("deprecated " . __FUNCTION__ . " in " . __FILE__ . " at line " . __LINE__);
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
    debug_print_backtrace(); die("deprecated " . __FUNCTION__ . " in " . __FILE__ . " at line " . __LINE__);
    return round($p[0], $precision) . ',' . round($p[1], $precision);
}

function edgeKey(string $a, string $b): string
{
    debug_print_backtrace(); die("deprecated " . __FUNCTION__ . " in " . __FILE__ . " at line " . __LINE__);
    return strcmp($a, $b) < 0 ? "$a|$b" : "$b|$a";
}
