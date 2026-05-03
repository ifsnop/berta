<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('memory_limit', '1G');

// DEFINICIÓN DE CONSTANTES
const RADIO_TERRESTRE = 6371000.0;
const MILLA_NAUTICA_EN_METROS = 1852.0; // metros equivalentes a 1 milla nautica
const GUARDAR_POR_NIVEL = 0; // puntero para el array de resultados
const GUARDAR_POR_RADAR = 1; // puntero para el array de resultados
const ANGULO_CONO = 45.0; // ángulo del cono de silencio (si no hay cono, sería 0º)
const BERTA_MAX_WALL_HEIGHT = 32714.4; // máxima altitud de la pared que marca final de cobertura

// INCLUSIÓN DE FICHEROS
include_once('inc.cargarScreening.php');
include_once('inc.cargarCoordenadas.php');
include_once('inc.auxiliares.php');
include_once('inc.calculos.php');
include_once('inc.multiCalculos.php');
include_once('inc.guardar.php');

include_once('martinez-rueda-php/src/Ifsnop/MartinezRueda/Algorithm.php');
include_once('martinez-rueda-php/src/Ifsnop/MartinezRueda/CombinedPolySegments.php');
include_once('martinez-rueda-php/src/Ifsnop/MartinezRueda/Fill.php');
include_once('martinez-rueda-php/src/Ifsnop/MartinezRueda/Intersecter.php');
include_once('martinez-rueda-php/src/Ifsnop/MartinezRueda/IntersectionPoint.php');
include_once('martinez-rueda-php/src/Ifsnop/MartinezRueda/LinkedList.php');
include_once('martinez-rueda-php/src/Ifsnop/MartinezRueda/Matcher.php');
include_once('martinez-rueda-php/src/Ifsnop/MartinezRueda/Node.php');
include_once('martinez-rueda-php/src/Ifsnop/MartinezRueda/Point.php');
include_once('martinez-rueda-php/src/Ifsnop/MartinezRueda/PolyBoolException.php');
include_once('martinez-rueda-php/src/Ifsnop/MartinezRueda/Polygon.php');
include_once('martinez-rueda-php/src/Ifsnop/MartinezRueda/PolySegments.php');
include_once('martinez-rueda-php/src/Ifsnop/MartinezRueda/RegionIntersecter.php');
include_once('martinez-rueda-php/src/Ifsnop/MartinezRueda/SegmentChainerMatcher.php');
include_once('martinez-rueda-php/src/Ifsnop/MartinezRueda/SegmentIntersecter.php');
include_once('martinez-rueda-php/src/Ifsnop/MartinezRueda/Segment.php');
include_once('martinez-rueda-php/src/Ifsnop/MartinezRueda/Transition.php');


$config = array(
    'sensores' => array(),
    /*
	    "paracuellos1",
	    "alcolea",
	    "monflorite"
    */
    'radar-data' => "spain.tsk/",
    'fl' => array('min' => 1, 'max' => 400, 'step' => 1),
    'cone' => false,
    'max-range' => false,
    'force' => false,
    'path' => array(
        'resultados_mono' => "." . DIRECTORY_SEPARATOR . "RESULTADOS" . DIRECTORY_SEPARATOR . "MONO" . DIRECTORY_SEPARATOR,
        'cache' => "." . DIRECTORY_SEPARATOR . "cache" . DIRECTORY_SEPARATOR,
        'resultados_multi' => "." . DIRECTORY_SEPARATOR . "RESULTADOS" . DIRECTORY_SEPARATOR . "MULTI" . DIRECTORY_SEPARATOR,
    ),
    'mode' => array('monoradar' => false, 'multiradar' => false, 'multiradar_unica' => false, 'list' => false), // monoradar, multiradar
    'disable-kmz' => false,
);

programaPrincipal($config);

logger (" V> " . "Info memory_usage(" . convertBytes(memory_get_usage(false)) . ") " .
    "Memory_peak_usage(" . convertBytes(memory_get_peak_usage(false)) . ")");

exit(0);

function printHelp()
{
    print "-r radar_list            | --radar-list radar_list (lista entre comillas, si lista vacía coje todos los disponibles)" . PHP_EOL;
    print "-m max_range             | --max-range (alcance máximo en NM)" . PHP_EOL;
    print "-1 (default)             | --monoradar (default)" . PHP_EOL;
    print "-2                       | --multiradar" . PHP_EOL;
    print "   -u                    | --unica (multicobertura como una sola suma de todas)" . PHP_EOL;
    print "   -r (default)          | --rascal (multicobertura estilo rascal)" . PHP_EOL;
    print "                         | --no-rascal" . PHP_EOL;
    print "   -p                    | --parcial (multicobertura por radar y tipo->mono,doble,triple...)" . PHP_EOL;
    print "-f                       | --force (ignora cache)" . PHP_EOL;
    print "-l                       | --list (lista radares disponibles)" . PHP_EOL;
    print "-d                       | --radar-data (path of radar_data.rbk, default ./spain.tsk)" . PHP_EOL;
    print "-s single|min,max,step   | --steps min,max,step (in FL) default (1,400,1) or calculate only single FL" . PHP_EOL;
    print "-c                       | --cone (activa cálculo del cono de silencio)" . PHP_EOL;
    print "-z                       | --disable-kmz (desactiva kmz y genera kml)" . PHP_EOL;
    print "-h                       | --help" . PHP_EOL;
    return;
}

function programaPrincipal(array $config)
{

    $shortopts  = "r:"; // radar name
    $shortopts .= "m:"; // max range in NM
    $shortopts .= "1"; // modo monoradar
    $shortopts .= "2"; // modo multiradar
    $shortopts .= "u"; // multi unica
    $shortopts .= "p"; // multi parcial
    // $shortopts .= "r"; // multi rascal <- choca con radar name
    $shortopts .= "f"; // forzado (ignorar cache)
    $shortopts .= "l"; // list available radars
    $shortopts .= "d:"; // radar database path
    $shortopts .= "h"; // help
    $shortopts .= "s:"; // steps
    $shortopts .= "c"; // cálculo del cono
    $shortopts .= "z"; // desactiva kmz y genera kml
    $longopts = array("radar-list:", "max-range:", "monoradar", "multiradar", "unica", "parcial", "rascal", "no-rascal", "force", "list", "radar-data:", "help", "steps:", "cone", "disable-kmz");
    $options = getopt($shortopts, $longopts);
    if (0 == count($options)) {
        printHelp();
        exit(0);
    }
    foreach ($options as $key => $value) {
        switch ($key) {
            case 'help':
            case 'h':
                printHelp();
                exit(0);
            case 'list':
            case 'l':
                $config['mode']['list'] = true;
                // print_r( $config['sensores'] );
                // exit(0);
                break;
            case 'radar-data':
            case 'd':
                $config['radar-data'] = $value;
                break;
            case 'radar-list':
            case 'r':
                // var_dump($value);
                if ("" != $value) {
                    $config['sensores'] = explode(" ", trim($value));
                    foreach ($config['sensores'] as $index => $sensor)
                        $config['sensores'][$index] = strtolower($sensor);
                } // else coje la lista completa por defecto
                logger(" I> Ejecutando con la siguiente selección de sensor(es) (" . implode(",", $config['sensores']) . ")");
                break;
            case 'cone':
            case 'c':
                $config['cone'] =  true;
                logger(" I> Implementando cálculo del cono de silencio en cobertura");
                break;
            case 'max-range':
            case 'm':
                $config['max-range'] = $value;
                logger(" I> Alcance forzado a $value NM");
                $config['force'] = true; // forzar alcance implica ignorar caché y forzar recálculo
                logger(" I> Modo *forzado* activado");
                break;
            case 'steps':
            case 's':
                $steps = explode(",", $value);
                if (1 == count($steps)) {
                    if (!is_numeric($steps[0])) {
                        printHelp();
                        exit(0);
                    } else {
                        $config['fl'] = array('min' => (int) $steps[0], 'max' => (int) $steps[0], 'step' => 1);
                    }
                } elseif (3 == count($steps)) {
                    if (!is_numeric($steps[1]) || !is_numeric($steps[2])) {
                        printHelp();
                        exit(0);
                    } else {
                        $config['fl'] = array('min' => (int) $steps[0], 'max' => (int) $steps[1], 'step' => (int) $steps[2]);
                    }
                } else {
                    printHelp();
                    exit(0);
                }
                break;
            case '1':
            case 'monoradar':
                $config['mode']['monoradar'] = true;
                logger(" I> Modo *monoradar* activado");
                break;
            case '2':
            case 'multiradar':
                $config['mode']['multiradar'] = true;
                logger(" I> Modo *multiradar* activado");
                break;
            case 'u':
            case 'unica':
                $config['mode']['multiradar_unica'] = true;
                logger(" I> Modo *multiradar con cobertura única* activado");
                break;
            /*
            case 'rascal':
                $calculosMode['rascal'] = true;
                print "INFO calculo *multiradar al estilo rascal* activado" . PHP_EOL;
                break;
            case 'no-rascal':
                $calculosMode['rascal'] = false;
                print "INFO calculo *multiradar al estilo rascal* desactivado" . PHP_EOL;
                break;
            case 'p':
            case 'parcial':
                $calculosMode['parcial'] = true;
                print "INFO calculo *multiradar por radares y tipo (mono,doble,triple...)* activado" . PHP_EOL;
                break;
*/
            case 'f':
            case 'force':
                $config['force'] = true;
                logger(" I> Modo *forzado* activado");
                break;
            case 'z':
            case 'disable-kmz':
                $config['disable-kmz'] = true;
                logger(" I> Generación KMZ desactivada");
                break;
            default:
                logger(" E> Parámetro no esperado: $key");
                exit(-1);
        }
    }

    //    if ( count($config['mode']) == 0 )
    //	$config['mode']['monoradar'] = true;
    /*
    // default values, calculate everything
    $modo = 'monoradar';
    $calculosMode = array('unica' => false, 'parcial' => false, 'rascal' => true);
    $radarTerrain = false; // para guardar el fichero de screening una vez leido
    $rutaResultados = "." . DIRECTORY_SEPARATOR . "RESULTADOS" . DIRECTORY_SEPARATOR;
    $poligono = false;
    $altMode = altitudeModetoString($altitudeMode = 0);
*/
    if (!class_exists('ZipArchive')) {
        logger(" I> La clase ZipArchive no está instalda, generación de KMZ desactivada");
    }
    $timer = microtime(true);
    logger(" D> Leyendo información de radares de >" . $config['radar-data'] . "<");
    $infoCoral = getRadars($config['radar-data'], $parse_all = true);
    logger(" V> Información de radares procesada (" . round(microtime(true) - $timer, 3) . "s)");
    if ($config['mode']['list']) {
        logger(" V> Listado de radares configurados:");
        print implode(" ", array_keys($infoCoral)) . PHP_EOL;
        exit(0);
    }

    logger(" I> Pasos configurados (min,max,paso): ({$config['fl']['min']},{$config['fl']['max']},{$config['fl']['step']})");
    // comprobamos que todos los sensores solicitados existen
    if (0 == count($config['sensores'])) {
        logger(" E> No se han solicitado sensores para el estudio");
        exit(-1);
    }
    foreach ($config['sensores'] as $sensor) {
        if (!isset($infoCoral[$sensor])) {
            logger(" E> El sensor $sensor no está configurado en la bbdd del SASS-C");
            exit(-1);
        }
    }

    // print_r($config);
    $coberturas = array(); // array con las coberturas
    for ($fl = $config['fl']['min']; $fl <= $config['fl']['max']; $fl += $config['fl']['step']) {
        $nivelVuelo = str_pad((string)$fl, 3, "0", STR_PAD_LEFT);
        logger(" V> Generando nivel de vuelo {$fl}00ft");
        foreach ($config['sensores'] as $sensor) {
            logger(" V> Generando sensor {$sensor}");
            $coberturas[$sensor]['contornos'] = false;
            $cache_file = $config['path']['cache'] . $sensor . DIRECTORY_SEPARATOR . "{$sensor}-FL{$nivelVuelo}.json";
            logger(" D> Evaluando si leer de la caché {$cache_file}");
            if (false === $config['force']) {
                if (file_exists($cache_file)) {
                    if (false === ($fecha_modificado_cache = filemtime($cache_file))) {
                        logger(" E> Error leyendo fecha del fichero caché >{$cache_file}<");
                        exit(-1);
                    }

                    if ($fecha_modificado_cache >= $infoCoral[$sensor]['fecha_modificado']) {
                        $cache = file_get_contents($cache_file);
                        if (false !== $cache) {
                            $coberturas[$sensor]['contornos'] = json_decode($cache, $assoc = true);
                            logger(" V> Leyendo caché del fichero >{$cache_file}<");
                            if (false === $coberturas[$sensor]['contornos']) {
                                logger(" D> La caché no contenía datos, no hay cobertura en FL{$nivelVuelo} para {$sensor}");
                                continue;
                            }
                        } else {
                            logger(" E> Error leyendo fichero caché >{$cache_file}<");
                        }
                    } else {
                        logger(" V> El caché ha quedado invalidado: la fecha de creación del caché (" .
                            date("Y/m/d H:i:s", $fecha_modificado_cache) .
                            ") es anterior a la última modificación del fichero radar (" .
                            date("Y/m/d H:i:s", $infoCoral[$sensor]['fecha_modificado']) . ")");
                    }
                } else {
                    logger(" V> No había datos en la caché {$cache_file}");
                }
            } else {
                logger(" I> Modo forzado, Ignorando caché");
            }

            // como no había nada en la caché, hay que calcularlo todo
            if (false === $coberturas[$sensor]['contornos']) {
                $timer = microtime(true);
                logger(" D> Leyendo información del terreno de {$sensor}");
                // ¿tenemos datos del terreno cargados? vamos a cargarlos una sola vez
                if (!isset($coberturas[$sensor]['terreno']) || false === $coberturas[$sensor]['terreno']) {
                    $coberturas[$sensor]['terreno'] = cargarDatosTerreno($infoCoral[$sensor], $config['max-range'] !== false ? $config['max-range'] : $infoCoral[$sensor]['secondaryMaximumRange']);
                    if (false !== strpos($sensor, "-psr")) {
                        logger(" V> Detectado PSR, ajustando alcance");
                        $coberturas[$sensor]['terreno']['range'] = $infoCoral[$sensor]['primaryMaximumRange'] * MILLA_NAUTICA_EN_METROS;
                        logger(" I>  El alcance definido para el PSR es de " . ($coberturas[$sensor]['terreno']['range'] / MILLA_NAUTICA_EN_METROS) .
                            "NM / " . $coberturas[$sensor]['terreno']['range'] . "m");
                    }
                    logger(" V> Información del terreno procesada (" . round(microtime(true) - $timer, 3) . "s)");
                    if (false) {
                        // para la herramienta de cálculo de kmz de matlab
                        generateMatlabFiles($coberturas[$sensor]['terreno'], false); // $rutaResultados);
                    }
                }

                $coberturas[$sensor]['contornos'] = calculosFL($coberturas[$sensor]['terreno'], $fl, $nivelVuelo, $config['cone']);

                if (false === $config['max-range']) {
                    logger(" V> Guardando caché para el sensor >{$sensor}< en >{$cache_file}<");
                    crearCarpetaResultados($config['path']['cache'] . $sensor);
                    file_put_contents($cache_file, json_encode($coberturas[$sensor]['contornos']));
                }
                // contornos es false cuando no existe contorno. Eso sucede cuando hemos pedido un
                // nivel de vuelo que está muy bajo.
                if (false === $coberturas[$sensor]['contornos']) {
                    logger(" N> No se han generado contornos para el sensor >{$sensor}<");
                    continue;
                }
                // guardar el cálculo en la cache, siempre que no hayamos forzado el alcance
                // si se ha forzado el alcance, la caché está invalidada automáticamente.
            }

            // si estamos en mono cobertura, generamos ya el kml
            if (isset($config['mode']['monoradar']) && $config['mode']['monoradar']) {
                $rutas = array(
                    'por_nivel' =>  $config['path']['resultados_mono'] . $nivelVuelo . DIRECTORY_SEPARATOR,
                    'por_sensor' => $config['path']['resultados_mono'] . $sensor . DIRECTORY_SEPARATOR,
                );

                creaKml3(
                    $coberturas[$sensor]['contornos'],
                    array($sensor),
                    $rutas,
                    $nivelVuelo,
                    $altMode = "clampToGround",
                    $appendToFilename = '',
                    $coverageLevel = 'mono',
                    $config['disable-kmz']
                );
            }

            logger(" D> " . "Info memory_usage(" . convertBytes(memory_get_usage(false)) . ") " .
                "Memory_peak_usage(" . convertBytes(memory_get_peak_usage(false)) . ")");
        }
	if ( (isset($config['mode']['multiradar']) && $config['mode']['multiradar'] === true ) ||
	    (isset($config['mode']['multiradar_unica']) && $config['mode']['multiradar_unica'] === true) ) {

	    crearCarpetaResultados($config['path']['resultados_multi'] . $nivelVuelo);
	    multicobertura(
		$coberturas,
		$nivelVuelo,
		array($config['path']['resultados_multi'] . $nivelVuelo . DIRECTORY_SEPARATOR),
		altitudeModetoString($altitudeMode = 0),
		$config['mode']
	    );
	}
    }
    exit(0);

}

function calculosFL(array $radar, float $fl, string $nivelVuelo, bool $calculoCono = false) { //, $modo = 'monoradar') {

    $hA = $radar['screening']['towerHeight'] + $radar['screening']['terrainHeight'];
    $flm = $fl * 100 * FEET_TO_METERS; // fl en metros


    // DISTINCIÓN DE CASOS 
    if ($flm >= $hA) { // CASO A (nivel de vuelo por encima de la posición del radar)
        // inicio para calculo por encima con método vectorial
        // se devuelve para cada azimut, la distancia más lejana
        $distanciasAlcances = calculosFLencimaRadar($radar, $flm);

        $newRange = obtieneMaxAnguloConCoberturaA($distanciasAlcances);
        $radar['screening']['range'] = round($newRange);
        $radar['range'] = round($newRange);
        $listaContornos2 = calculaCoordenadasGeograficasA($radar, $flm, $distanciasAlcances);
        // ya sabemos que la lista exterior de alcances va a estar
	    // CW y tenemos que invertirla (porque se calcula siguiendo los azimut de 0 a 360ª)
	    // y la interior igual.
	    // para un kml deberíamos tener:
	    // exterior rings: counter-clockwise CCW
	    // interior rings (holes): clockwise direction CW
        // todo eso se hará en normalizePolygonsForKML

        
        if ($calculoCono) {
            $radioConom = ($flm - $hA) * tan(deg2rad(ANGULO_CONO));
            $radioCono = $radioConom / MILLA_NAUTICA_EN_METROS; // convertimos metros en millas
            $distanciasConos = array_fill(0, count($distanciasAlcances), $radioCono);
            logger(" N> Radio del Cono: " . round($radioCono, 2) . "NM / " . round($radioConom, 2) . "m");
            $listaContornosConos2 = calculaCoordenadasGeograficasA($radar, $flm, $distanciasConos);
        } else {
            $listaContornosConos2 = false;
        }
        
        $result = normalizePolygonsForKML( array_merge(array($listaContornos2), array($listaContornosConos2)));

        creaKml3(
            $result,
            array($radar['screening']['site']),
            array("./"),
            $nivelVuelo,
            $altMode = "RelativeToGround",
            $appendToFilename = '',
            $coverageLevel = 'mono'
        );


    } else { // CASO B (nivel de vuelo por debajo de la posición del radar)

        if ($calculoCono) {
            logger(" I> No se calcula cono para niveles de vuelo por debajo de la ubicación del radar");
        }

        logger(" D> calculosFLdebajoRadar2");
        $polygons = calculosFLdebajoRadar2($radar, $flm);

        if (0 == count($polygons)) {
            logger(" I> No existe cobertura para el sensor {$radar['radar']} a FL{$nivelVuelo}");
            return false;
        }
        print_r($polygons);exit(0);
        $result = normalizePolygonsForKML($polygons);

        // esta llamada no debería estar aquí, sino en main.
        creaKml3(
            $result,
            array($radar['screening']['site']),
            array("./"),
            $nivelVuelo,
            $altMode = "RelativeToGround",
            $appendToFilename = '',
            $coverageLevel = 'mono'
        );

        //exit(0);

        /*
        $malla = calculosFLdebajoRadar2($radar, $flm);
        $newRange = obtieneMaxAnguloConCoberturaB($radar);
        $radar['screening']['range'] = round($newRange);
        $radar['range'] = round($newRange);

        // if ( 'multiradar' == $modo ) { // puede ser hasta 10 segundos más lenta que sin LatLon en 170NM
        logger(" D> generacionMalladoLatLon start"); $timer0 = microtime(true);
        $mallado = generacionMalladoLatLon($radar, $flm, $distanciasAlcances = array());
        logger(" D> generacionMalladoLatLon ended " . round(microtime(true) - $timer0,3) . " segundos");
        // }

        // comprobación si hay cobertura en las esquinas de la malla. En ese caso,
        // determinaContornos2 podría fallar
        */
        /*
        logger(" D> check coverage overflow start"); $timer0 = microtime(true);
        checkCoverageOverflow($mallado['malla']);
        logger(" D> check coverage overflow ended " . round(microtime(true) - $timer0,3) . " segundos");;

	logger(" D> determinaContornos2 start"); $timer0 = microtime(true);
        $listaContornos2 = determinaContornos2($mallado['malla']);
	logger(" D> determinaContornos2 ended " . round(microtime(true) - $timer0,3) . " segundos");

	if ( 0 == count($listaContornos2) ) {
	    logger(" I> No se generan contornos porque no existe cobertura del sensor {$radar['radar']} a FL{$nivelVuelo}");
	    return false;
	}
        logger(" D> calculaCoordenadasGeograficasB"); $timer0 = microtime(true);
        $listaContornos2 = calculaCoordenadasGeograficasB($radar, $flm, $listaContornos2);
	logger(" D> calculaCoordenadasGeograficasB ended " . round(microtime(true) - $timer0, 3) . " segundos");
	// print_r($listaContornos2); exit(0);
    }
*/
        // print_r($listaContornos2);exit(0);
        /*
    print "[crearKml]" . PHP_EOL;
    creaKml2(
        $listaContornos2,
        $radar['screening']['site'],
        $ruta,
        $fl,
        $altMode,
        $appendToFilename = '',
        $coverageLevel = 'mono'
    );
*/
        return false; //$listaContornos2;
    }
}
