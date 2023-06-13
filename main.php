<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('memory_limit', '16G');

// DEFINICIÓN DE CONSTANTES
CONST RADIO_TERRESTRE = 6371000;
CONST MILLA_NAUTICA_EN_METROS = 1852; // metros equivalentes a 1 milla nautica
CONST GUARDAR_POR_NIVEL = 0; // puntero para el array de resultados
CONST GUARDAR_POR_RADAR = 1; // puntero para el array de resultados
CONST ANGULO_CONO = 45.0; // ángulo del cono de silencio (si no hay cono, sería 0º)



// INCLUSIÓN DE FICHEROS
include_once('inc.cargarScreening.php');
include_once('inc.cargarCoordenadas.php');
include_once('inc.auxiliares.php');
include_once('inc.conrec.php');
include_once('inc.calculos.php');
include_once('inc.multiCalculos.php');
include_once('inc.guardar.php');
include_once('MartinezRueda/Algorithm.php');

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
    'mode' => array('monoradar' => true, 'multiradar' => false, 'multiradar_unica' => false), // monoradar, multiradar
    'disable-kmz' => false,
);

if ( file_exists('inc.config.php') ) {
    include_once('inc.config.php');
}

/*

/*
$path = array( array(41,-10), array(42,-10), array(42,-9), array(41,-9), array(41,-10));
print computeArea($path) . PHP_EOL;
/*


$prueba = array(
    array(0,0,0,0,0,0,0,0,0,0,0),
    array(0,1,1,1,0,0,0,0,0,0,0),
    array(0,1,2,2,1,0,0,0,0,0,0),
    array(0,1,2,2,1,0,0,0,0,0,0),
    array(0,0,1,1,1,0,0,0,0,0,0),
    array(0,0,0,0,0,0,0,0,0,0,0),
    array(0,0,0,0,0,0,0,0,0,0,0),
    array(0,0,0,0,0,0,0,0,0,0,0),
    );

//$prueba = array(
//    array(0,0,0,0),
//    array(0,1,0,0),
//    array(0,0,1,0),
//    array(0,0,0,0),
//);

$d = $x = $y = array();
$i = 0;
foreach( $prueba as $lat => $lons ) {
    $d[$i] = array();
    $x[$i] = $lat;
    $j = 0;
    foreach( $lons as $lon => $value ) {
        $y[$j] = $lon;
        $d[$i][$j] = $value;
        $j++;
    }
    $i++;
}
printMalla($prueba, "0");
$contornos = CONREC_contour($d, $x, $y, array(0.33,1.33,2.33)); //$numContornos = 3);
//print_r($contornos[0]['segments']);
storeContornosAsImage3($contornos, "prueba");
$listaContornos2 = determinaContornos2($prueba);
creaKml2($listaContornos2, "PRUEBA", array("./"), 100, "absolute");

//print_r($listaContornos2);
//printContornos($listaContornos2, $prueba);
exit(0);
*/

programaPrincipal();

logger (" V> " . "Info memory_usage(" . convertBytes(memory_get_usage(false)) . ") " .
    "Memory_peak_usage(" . convertBytes(memory_get_peak_usage(false)) . ")");

exit(0);

function printHelp() {
    print "-r radar_list     | --radar-list radar_list (lista entre comillas, si lista vacía coje todos los disponibles)" . PHP_EOL;
    print "-m max_range      | --max-range (alcance máximo en NM)" . PHP_EOL;
    print "-1 (default)      | --monoradar (default)" . PHP_EOL;
    print "-2                | --multiradar" . PHP_EOL;
    print "   -u             | --unica (multicobertura como una sola suma de todas)" . PHP_EOL;
    print "   -r (default)   | --rascal (multicobertura estilo rascal)" . PHP_EOL;
    print "                  | --no-rascal" . PHP_EOL;
    print "   -p             | --parcial (multicobertura por radar y tipo->mono,doble,triple...)" . PHP_EOL;
    print "-f                | --force (ignora cache)" . PHP_EOL;
    print "-l                | --list (lista radares disponibles)" . PHP_EOL;
    print "-d                | --radar-data (path of radar_data.rbk, default ./spain.tsk)" . PHP_EOL;
    print "-s min,max,step   | --steps min,max,step (in FL) default (1,400,1)" . PHP_EOL;
    print "-c                | --cone (activa cálculo del cono de silencio)" . PHP_EOL;
    print "-z                | --disable-kmz (desactiva kmz y genera kml)" . PHP_EOL;
    print "-h                | --help" . PHP_EOL;
    return;
}

function programaPrincipal(){
    global $argv, $argc, $config;
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
    $longopts = array( "radar-list:", "max-range:", "monoradar", "multiradar", "unica", "parcial", "rascal", "no-rascal", "force", "list", "radar-data:", "help", "steps:", "cone", "disable-kmz" );
    $options = getopt( $shortopts, $longopts );
    if ( 0 == count($options) ) {
        printHelp();
        exit(0);
    }
    foreach( $options as $key => $value ) {
        switch( $key ) {
            case 'help':
            case 'h':
                printHelp();
                exit(0);
                break;
            case 'list':
            case 'l':
                print_r( $config['sensores'] );
                exit(0);
                break;
            case 'radar-data':
            case 'd':
		$config['radar-data'] = $value;
		break;
            case 'radar-list':
            case 'r':
                // var_dump($value);
                if ( "" != $value ) {
                    $config['sensores'] = explode( " ", $value );
		    foreach($config['sensores'] as $index => $sensor)
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
                $steps = explode( ",", $value );
                // print_r($steps); var_dump($steps);
                if (!is_numeric($steps[0]) || !is_numeric($steps[1]) || !is_numeric($steps[2]) ) {
                    printHelp();
                    exit(0);
                }
                $config['fl'] = array('min' => $steps[0], 'max' => $steps[1], 'step' => $steps[2]);
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
                print "ERROR Parámetro no esperado: $key" . PHP_EOL;
                exit(0);
        }
    }

    if ( count($config['mode']) == 0 )
	$config['mode']['monoradar'] = true;
/*
    // default values, calculate everything
    $modo = 'monoradar';
    $calculosMode = array('unica' => false, 'parcial' => false, 'rascal' => true);
    $radarTerrain = false; // para guardar el fichero de screening una vez leido
    $rutaResultados = "." . DIRECTORY_SEPARATOR . "RESULTADOS" . DIRECTORY_SEPARATOR;
    $poligono = false;
    $altMode = altitudeModetoString($altitudeMode = 0);
*/
    if ( !class_exists('ZipArchive') ) {
	logger(" I> La clase ZipArchive no está instalda, generación de KMZ desactivada");
    }

    $timer = microtime(true);
    logger(" D> Leyendo información de radares de >" . $config['radar-data'] . "<");
    $infoCoral = getRadars($config['radar-data'], $parse_all = true);
    logger(" V> Información de radares procesada (" . round(microtime(true) - $timer,2) . "s)");
    logger(" I> Pasos configurados (min,max,paso): ({$config['fl']['min']},{$config['fl']['max']},{$config['fl']['step']})");
    // comprobamos que todos los sensores solicitados existen
    foreach($config['sensores'] as $sensor) {
        if ( !isset($infoCoral[$sensor]) ) {
	    logger(" E> El sensor $sensor no está configurado en la bbdd del SASS-C"); exit(-1);
	}
    }

    // print_r($config);
    $coberturas = array(); // array con las coberturas
    for ($fl = $config['fl']['min']; $fl <= $config['fl']['max']; $fl += $config['fl']['step']) {
	$nivelVuelo = str_pad( (string)$fl,3,"0", STR_PAD_LEFT );
	logger( " V> Generando nivel de vuelo {$fl}00ft");
	foreach($config['sensores'] as $sensor) {
	    logger(" V> Generando sensor {$sensor}");
	    $coberturas[$sensor]['contornos'] = false;
	    $cache_file = $config['path']['cache'] . $sensor . DIRECTORY_SEPARATOR ."{$sensor}-FL{$nivelVuelo}.json";
	    logger(" D> Evaluando si leer de la caché {$cache_file}");
	    if ( false === $config['force'] ) {
		if ( file_exists($cache_file) ) {
		    if ( false === ($fecha_modificado_cache = filemtime($cache_file)) ) {
			logger(" E> Error leyendo fecha del fichero caché >{$cache_file}<"); exit(-1);
		    }

		    if ( $fecha_modificado_cache >= $infoCoral[$sensor]['fecha_modificado'] ) {
			$cache = file_get_contents( $cache_file );
			if ( false !== $cache ) {
			    $coberturas[$sensor]['contornos'] = json_decode($cache, $assoc = true);
			    logger (" V> Leyendo caché del fichero >{$cache_file}<");
			} else {
			    logger (" E> Error leyendo fichero caché >{$cache_file}<");
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
            if ( false === $coberturas[$sensor]['contornos'] ) {
		$timer = microtime(true);
		logger(" D> Leyendo información del terreno de {$sensor}");
		// ¿tenemos datos del terreno cargados? vamos a cargarlos una sola vez
		if ( !isset($coberturas[$sensor]['terreno']) || false === $coberturas[$sensor]['terreno'] ) {
		    $coberturas[$sensor]['terreno'] = cargarDatosTerreno( $infoCoral[$sensor], $config['max-range'] !== false ? $config['max-range'] : $infoCoral[$sensor]['secondaryMaximumRange'] );
		    if ( false !== strpos($sensor, "-psr") ) {
			logger(" V> Detectado PSR, ajustando alcance");
			$coberturas[$sensor]['terreno']['range'] = $infoCoral[$sensor]['primaryMaximumRange'] * MILLA_NAUTICA_EN_METROS;
			logger(" I>  El alcance definido para el PSR es de " . ($coberturas[$sensor]['terreno']['range'] / MILLA_NAUTICA_EN_METROS) .
			    "NM / " . $coberturas[$sensor]['terreno']['range'] . "m");
		    }
		    logger(" V> Información del terreno procesada (" . round(microtime(true) - $timer,2) . "s)");
		    if ( false ) {
			// para la herramienta de cálculo de kmz de matlab
			generateMatlabFiles($coberturas[$sensor]['terreno'], false); // $rutaResultados);
		    }

		}
		$coberturas[$sensor]['contornos'] = calculosFL($coberturas[$sensor]['terreno'], $fl, $nivelVuelo, $config['cone']);
		crearCarpetaResultados($config['path']['cache'] . $sensor);
		// guardar el cálculo en la cache, siempre que no hayamos forzado el alcance
		// si se ha forzado el alcance, la caché está invalidada automáticamente.
		if ( false === $config['max-range'] ) {
		    logger(" V> Guardando caché para el sensor >{$sensor}< en >{$cache_file}<");
		    file_put_contents($cache_file, json_encode($coberturas[$sensor]['contornos']));
		}

	    }
	    // si estamos en mono cobertura, generamos ya el kml
	    if ( isset($config['mode']['monoradar']) ) {
		$rutas = array(
		    'por_nivel' =>  $config['path']['resultados_mono'] . $nivelVuelo . DIRECTORY_SEPARATOR ,
		    'por_sensor' => $config['path']['resultados_mono'] . $sensor . DIRECTORY_SEPARATOR ,
		);

		creaKml2(
		    $coberturas[$sensor]['contornos'],
		    $sensor,
		    $rutas,
		    $nivelVuelo,
		    altitudeModetoString($altitudeMode = 0),
		    $appendToFilename = '',
		    $coverageLevel = 'mono',
		    $config['disable-kmz']
		);
	    }

/*
    print "[crearKml]" . PHP_EOL;
 // @param string $altMode cadena para que el KML utilice la altura como relativa o absoluta...

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

	logger (" V> " . "Info memory_usage(" . convertBytes(memory_get_usage(false)) . ") " . "Memory_peak_usage(" . convertBytes(memory_get_peak_usage(false)) . ")");

	}
	if ( isset($config['mode']['multiradar']) || isset($config['mode']['multiradar_unica']) ) {
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

function calculosFL($radar, $fl, $nivelVuelo, $calculoCono = false) { //, $modo = 'monoradar') {

    $hA = $radar['screening']['towerHeight'] + $radar['screening']['terrainHeight'];
    $flm = $fl*100*FEET_TO_METERS; // fl en metros

    // DISTINCIÓN DE CASOS 
    if ( $flm >= $hA ) { // CASO A (nivel de vuelo por encima de la posición del radar)
        // inicio para calculo por encima con método vectorial
	// se devuelve para cada azimut, la distancia más lejana
        $distanciasAlcances = calculosFLencimaRadar($radar, $flm);
	if ( $calculoCono ) {
	    $radioConom = ($flm-$hA)*tan(deg2rad(ANGULO_CONO));
	    $radioCono = $radioConom / MILLA_NAUTICA_EN_METROS; // convertimos metros en millas
	    $distanciasConos = array_fill(0, count($distanciasAlcances), $radioCono);
	    printf("[radioCono %3.2fNM / %3.2fm]", $radioCono, $radioConom);
	}
        $newRange = obtieneMaxAnguloConCoberturaA($distanciasAlcances);
        $radar['screening']['range'] = round($newRange);
        $radar['range'] = round($newRange);
	
        $listaContornos2 = calculaCoordenadasGeograficasA($radar, $flm, $distanciasAlcances);
	if ( $calculoCono ) {
	    $listaContornosConos2 = calculaCoordenadasGeograficasA($radar, $flm, $distanciasConos);
	}
/*
	// todo esto no nos hace falta, ya sabemos que la lista exterior de alcances va a estar
	// CW y tenemos que invertirla (porque se calcula siguiendo los azimut de 0 a 360ª)
	// y la interior igual.

	// vamos a comprobar la orientación, recordemos que para un kml deberíamos tener:
	// * exterior rings: counter-clockwise
	// * interior rings (holes): clockwise direction
	// buscamos el punto más a la izquiera y abajo para llamar al cálculo de la orientación
	$listaContornosConos2[0]['polygon'] = array_reverse($listaContornosConos2[0]['polygon']);

	$leftCorner = array(
	    'xMin' => $listaContornos2[0]['polygon'][0]['lon'],
	    'yMin' => $listaContornos2[0]['polygon'][0]['lat'],
	    'key' => 0
	);
	foreach($listaContornos2[0]['polygon'] as $k => $vertex) {
	    $leftCorner = findLeftCorner($vertex['lon'], $vertex['lat'], $leftCorner, false, $k);
	}
	// para el cono de silencio, al ser un círculo, leftCorner siempre será el azimuth 270º,
	// o 540 si estamos con pasos de 0.5º)
	$orientacion = comprobarOrientacion( $listaContornos2[0]['polygon'], $leftCorner );
	// bool true = CCW, false = CW
	if ( $orientacion ) print "[orientación listaContornos2: CCW]"; else print "[orientación listaContornos2: CW]";

	$leftCorner = array(
	    'xMin' => $listaContornosConos2[0]['polygon'][0]['lon'],
	    'yMin' => $listaContornosConos2[0]['polygon'][0]['lat'],
	    'key' => 0
	);
	foreach($listaContornosConos2[0]['polygon'] as $k => $vertex) {
	    $leftCorner = findLeftCorner($vertex['lon'], $vertex['lat'], $leftCorner, false, $k);
	}
	// para el cono de silencio, al ser un círculo, leftCorner siempre será el azimuth 270º,
	// o 540 si estamos con pasos de 0.5º)
	$orientacion = comprobarOrientacion( $listaContornosConos2[0]['polygon'], $leftCorner );
	// bool true = CCW, false = CW
	if ( $orientacion ) print "[orientación listaContornosConos2: CCW]"; else print "[orientación listaContornosConos2: CW]";
	if ( $orientacion ) {
	    array_reverse($listaContornosConos2[0]['polygon']);
	}
*/
	$listaContornos2[0]['polygon'] = array_reverse($listaContornos2[0]['polygon']);

	// si el nivel de vuelo por encima de la posición del radar, sólo hay 1 polígono
	// no hay cálculo del cono para nivel de vuelo por debajo de la posición del radar
	if ( $calculoCono ) {
	    $listaContornosConos2[0]['level'] = 1;
	    $listaContornos2[0]['inside'] = array( ( $listaContornosConos2[0]) );
	}
	// print_r($distanciasAlcances); exit(0);
	// print_r($distanciasConos); exit(0);
	// print_r($listaContornos2); exit(0);
	// print_r($listaContornos2); exit(0);

        // codigo para generar malla por encima para la multicobertura
        // con listaContornos2 ya podemos generar una cobertura vectorial
        print "[generacionMalladoLatLon start]"; $timer0 = microtime(true);
        $mallado = generacionMalladoLatLon($radar, $flm, $distanciasAlcances);
        printf("[generacionMalladoLatLon ended %3.4fs]", microtime(true) - $timer0);
        // comprobación si hay cobertura en las esquinas de la malla. En ese caso,
        // determinaContornos2 podría fallar
        print "[check coverage overflow "; $timer0 = microtime(true);
        checkCoverageOverflow($mallado['malla']);
        printf(" %3.4fs]", microtime(true) - $timer0);
	print PHP_EOL;
	// $mallado['mallaLatLon'] = false;

        // print_r(array_keys($mallado['mallaLatLon']));exit(0);
        // con esto generamos cobertura matricial en el kml, que no creo que sea
        // necesario.
        // se podría probar a interseccionar con Martinez-Rueda, de momento no.

        /*
        // comentar si no queremos hacer coberturas por encima como si fuese por debajo
	print "[determinaContornos2 start]"; $timer0 = microtime(true);
        $listaContornos2 = determinaContornos2($mallado['malla']);
	if ( 0 == count($listaContornos2) ) {
	    print PHP_EOL . "INFO: No se genera KML/PNG/TXT porque no existe cobertura al nivel de vuelo FL" . $nivelVuelo . PHP_EOL;
	    return false;
	}
	printf("[determinaContornos2 ended %3.4fs]", microtime(true) - $timer0);
        print "[calculaCoordenadasGeograficasB]";
        $listaContornos2 = calculaCoordenadasGeograficasB($radar, $flm, $listaContornos2);
        // comentar si no queremos hacer coberturas por encima como si fuese por debajo
        */
        // fin código para generar malla por encima

    } else { // CASO B (nivel de vuelo por debajo de la posición del radar)

	if ( $calculoCono ) {
	    logger(" I> No se calcula cono para niveles de vuelo por debajo de la ubicación del radar");
	}
        print "[calculosFLdebajoRadar]";
	calculosFLdebajoRadar($radar, $flm);
        $newRange = obtieneMaxAnguloConCoberturaB($radar);
        $radar['screening']['range'] = round($newRange);
        $radar['range'] = round($newRange);

        // if ( 'multiradar' == $modo ) { // puede ser hasta 10 segundos más lenta que sin LatLon en 170NM
        print "[generacionMalladoLatLon start]"; $timer0 = microtime(true);
        $mallado = generacionMalladoLatLon($radar, $flm, $distanciasAlcances = array());
        printf("[generacionMalladoLatLon ended %3.4fs]", microtime(true) - $timer0);
        // }

        // comprobación si hay cobertura en las esquinas de la malla. En ese caso,
        // determinaContornos2 podría fallar
        print "[check coverage overflow "; $timer0 = microtime(true);
        checkCoverageOverflow($mallado['malla']);
        printf(" %3.4fs]", microtime(true) - $timer0);

	print "[determinaContornos2 start]"; $timer0 = microtime(true);
        $listaContornos2 = determinaContornos2($mallado['malla']);
	printf("[determinaContornos2 ended %3.4fs]", microtime(true) - $timer0);
	print PHP_EOL;
	if ( 0 == count($listaContornos2) ) {
	    logger(" I> No se generan contornos porque no existe cobertura del sensor {$radar['radar']} a FL{$nivelVuelo}");
	    return false;
	}
        logger(" D> CcalculaCoordenadasGeograficasB");
        $listaContornos2 = calculaCoordenadasGeograficasB($radar, $flm, $listaContornos2);
	// print_r($listaContornos2); exit(0);
    }

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
    return $listaContornos2;
}
