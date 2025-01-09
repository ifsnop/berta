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
    'mode' => array('monoradar' => false, 'multiradar' => false, 'multiradar_unica' => false, 'list' => false), // monoradar, multiradar
    'disable-kmz' => false,
);

if ( file_exists('inc.config.php') ) {
    include_once('inc.config.php');
}
/*
$p1 =[
	[[-2, 2], [2, 2], [2, -2], [-2, -2], [-2, 2]],
	[[-1, 1], [1, 1], [1, -1], [-1, -1], [-1, 1]]
    ];
$p2 =  [[[-1, 1], [5, 5], [1, -1], [-1, -1], [-1, 1]]];
$p3 =  [[[-2, -2], [2, -2], [2, 2], [-2, 2], [-2, -2]]];


$p1 = [[ [0, 2], [1, 2], [1, 1], [2, 1], [2, 2], [3,2],[3,0], [0,0], [0,2] ]];
$p2 = [[ [0, 2], [0, 3], [3, 3], [3, 2], [0, 2]]];

$p1 = [[ [0,0], [0, 1], [1,1], [1,0], [0,0] ]];
$p2 = [[ [1,1], [1, 2], [2,2], [2,1], [1,1] ]];


print json_encode($p1) . PHP_EOL;
print json_encode($p2) . PHP_EOL;

// $data = [[[-1, 4], [-3, 4], [-3, 0], [-3, -1], [-1, -1], [-1, -2], [2, -2], [2, 1], [-1, 1], [-1, 4]]];
    $subject = new \MartinezRueda\Polygon($p1);
//    $data = [[[-2, 5], [-2, 0], [3, 0], [3, 3], [2, 3], [2, 2], [0, 2], [0, 5], [-2, 5]]];
    $clipping = new \MartinezRueda\Polygon($p2);
    $result = (new \MartinezRueda\Algorithm())->getUnion($subject, $clipping);
    echo json_encode($result->toArray()), PHP_EOL;
//    echo json_encode($p3) . PHP_EOL;
//exit(0);
*/
/*
$listaContornos = genera_contornos($subject->toArray());
$placemarks = KML_get_placemarks(
    $listaContornos,
    "subject 0",
    array("./"),
    "999",
    0,
    $appendToFilename = "",
    "primera"
);
$coverages_per_level_KML['cov']["unica nivel subj"] = $placemarks;

$listaContornos = genera_contornos($clipping->toArray());
$placemarks = KML_get_placemarks(
    $listaContornos,
    "clip 1",
    array("./"),
    "999",
    0,
    $appendToFilename = "",
    "segunda"
);
$coverages_per_level_KML['cov']["unica nivel clip"] = $placemarks;

$listaContornos = genera_contornos($result->toArray());
$placemarks = KML_get_placemarks(
    $listaContornos,
    "res 2",
    array("./"),
    "999",
    0,
    $appendToFilename = "",
    "tercera"
);
$coverages_per_level_KML['cov']["unica nivel res"] = $placemarks;

KML_create_from_placemarks($coverages_per_level_KML, 0, 0);

exit(0);
*/







/*



$listaContornos = genera_contornos($subject->toArray());
print_r($listaContornos);
creaKml2(
$listaContornos,
"PASO X_sub", //$radares,
array("./"),
"999",
0,
$appendToFilename = "",
$coverageLevel = "unica_SUMANDO_PARCIAL"
);

$listaContornos = genera_contornos($clipping->toArray());
creaKml2(
$listaContornos,
"PASO X_clip", //$radares,
array("./"),
"999",
0,
$appendToFilename = "",
$coverageLevel = "unica_SUMANDO_PARCIAL"
);

$listaContornos = genera_contornos($result->toArray());
creaKml2(
$listaContornos,
"PASO X", //$radares,
array("./"),
"999",
0,
$appendToFilename = "",
$coverageLevel = "unica_SUMANDO_PARCIAL"
);


exit(0);    
*/
/*

function parse($str) {
    $r = array();
    $str2coords = explode(' ', $str);
    foreach($str2coords as $coords) {
	$triple = explode(',', $coords);
	$r[] = array($triple[1]+0, $triple[0]+0);
    }
    return array($r);
}

//$polygons = array(
//    "-1.666471247035827,41.08882135860311,0 -1.457976862019867,41.0553531551108,0 -1.608324977452921,40.97172952854883,0 -1.439277568088438,40.91406255359713,0 -1.668869563971784,40.84062691607907,0 -1.666471247035827,41.08882135860311,0",
//    "-1.602167908033331,41.144320694597,0 -1.474646577917061,40.99437169595932,0 -1.584918609153559,40.80903763591684,0 -1.395738579939014,40.8130114539392,0 -1.23480387124576,40.92719867458443,0 -1.288989749059445,41.13960516507462,0 -1.602167908033331,41.144320694597,0",
//"-1.315904377843086,42.01986297599004,1219.2148256523 -1.797439473176442,41.9408538514228,1219.2148256523 -1.6746926533447,41.711030585832,1219.2148256523 -1.5707925640914,41.721999281597,1219.2148256523 -1.748116622882,41.647224007981,1219.2148256523 -1.6822470285165,41.647173573827,1219.2148256523 -1.7924178072361,41.587669917041,1219.2148256523 -1.8062692956384,41.559303507465,1219.2148256523 -1.7839943276439,41.543848475482,1219.2148256523 -1.7332660959758,41.540003370542,1219.2148256523 -1.6967174273796,41.531541905275,1219.2148256523 -1.6742326186376,41.517818525132,1219.2148256523 -1.6557734550271,41.50260686078,1219.2148256523 -1.6896257055522,41.46317478381,1219.2148256523 -1.6945810865259,41.435979315566,1219.2148256523 -1.734456055032,41.390409888057,1219.2148256523 -1.6487288347964,41.4084637109,1219.2148256523 -1.247374971059368,41.48818154847839,1219.2148256523 -1.315904377843086,42.01986297599004,1219.2148256523",
// "-1.708909411913143,41.371081074865,0 -1.646295557461827,41.39757947187241,0 -1.658204241828161,41.40960513534188,0 -1.616418799664223,41.42828316773638,0 -1.627344759689704,41.44228026955555,0 -1.637590206037666,41.43651706748737,0 -1.644254380860515,41.44228854273827,0 -1.913886358712549,41.31401804758378,0 -1.922109707904213,41.31941093989618,0 -1.671581610077715,41.44642276109045,0 -1.677717513447392,41.45566310222506,0 -1.711874625636544,41.44439178705969,0 -1.720582043144888,41.44978669197253,0 -1.654588843522676,41.48823847425621,0 -1.672329265531682,41.50553690364867,0 -1.686758855231967,41.50531935518308,0 -1.692833775580991,41.51629536996229,0 -1.719813527497118,41.51240975525591,0 -1.739958761397365,41.52437854506762,0 -1.753603076626061,41.53487871415597,0 -1.657673776326596,41.61027600671475,0 -1.670550066852506,41.61958496149262,0 -1.52673646358601,41.72829593381083,0 -1.539792647219961,41.7394330131654,0 -1.549352891652586,41.73241199050742,0 -1.560091472869927,41.74475237444577,0 -1.640337454253863,41.68555022661488,0 -1.669230073812894,41.66102776786627,0 -1.686778345348837,41.66517629038065,0 -1.663358996094735,41.69144321201187,0 -1.676581443364594,41.69834211072831,0 -1.709584258954642,41.67410638805638,0 -1.736436092906148,41.67330005129888,0 -1.773339740674378,41.64248701733003,0 -1.8128300201396,41.64284023599508,0 -1.87221956703111,41.59082749050854,0 -1.955750570109892,41.53722955689987,0 -2.049639191333586,41.34060703201146,0 -1.944878118015827,41.24927687020132,0 -1.708909411913143,41.371081074865,0",
//);

include_once("peque.php");
include_once("grande.php");

$polygons = array();
$polygons[] = $a;
$polygons[] = $b;
$polygons[] = "-2.296616121624463,39.24686350629728,0 -2.296834713482048,39.24276068874091,0 -2.290270019027112,39.24275283382593,0 -2.293359121711842,39.24696442904251,0 -2.296616121624463,39.24686350629728,0";

$coverages_per_level_KML['cov'] = array();
$mrs = array();
foreach($polygons as $i => $polygon) {
    $parsed = parse($polygon);
    echo json_encode($parsed) . PHP_EOL . PHP_EOL . PHP_EOL;
    $p = new \MartinezRueda\Polygon($parsed);
    $mrs[] = $p;

    $listaContornos = genera_contornos($p->toArray());
    $placemarks = KML_get_placemarks(
	$listaContornos,
	"subject $i",
	array("./"),
	"999",
	0,
	$appendToFilename = "",
	"primera"
    );
    $coverages_per_level_KML['cov']["poly $i"] = $placemarks;
}


$res = new \MartinezRueda\Polygon(array());
foreach($mrs as $mr) {
    $mr_algorithm = new \MartinezRueda\Algorithm();
    $res = $mr_algorithm->getUnion($res, $mr);
}

//print_r($res->toArray());
// echo json_encode($res->toArray());

$listaContornos = genera_contornos($res->toArray());
$placemarks = KML_get_placemarks(
    $listaContornos,
    "subject union",
    array("./"),
    "999",
    0,
    $appendToFilename = "",
    "primera"
);
$coverages_per_level_KML['cov']["union"] = $placemarks;
KML_create_from_placemarks($coverages_per_level_KML, 0, 0);
exit(0);
*/
/*

$listaContornos = genera_contornos($res->toArray());
creaKml2(
$listaContornos,
"PASO X", //$radares,
array("./"),
"999",
0,
$appendToFilename = "",
$coverageLevel = "unica_SUMANDO_PARCIAL"
);
// exit(0);
*/

/*
include('grande2.php');
include('peque2.php');
$p1 = array(array( array(0,0), array(1,1), array(0,2), array(0,0) ));
$p2 = array(array( array(1,1), array(2,0), array(2,2), array(1,1) ));
$mr1 = new \MartinezRueda\Polygon($grande);
$mr2 = new \MartinezRueda\Polygon($peque);
$mr_algorithm = new \MartinezRueda\Algorithm();
//$res = $mr_algorithm->getDifference($mr1, $mr2);
$res = $mr_algorithm->getUnion($mr1, $mr2);
//print_r($res->toArray());
//print_r($mr1->toArray());

$listaContornos = genera_contornos($res->toArray());
creaKml2(
$listaContornos,
"PASO X", //$radares,
array("./"),
"220",
0,
$appendToFilename = "",
$coverageLevel = "unica_SUMANDO_PARCIAL"
);



exit();
*/

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
                if ( "" != $value ) {
		    $config['sensores'] = explode( " ", trim($value) );
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
		if ( 1 == count($steps) ) {
		    if (!is_numeric($steps[0]) ) {
                	printHelp();
                	exit(0);
            	    } else {
			$config['fl'] = array('min' => $steps[0], 'max' => $steps[0], 'step' => 1);
		    }
		} elseif ( 3 == count($steps) ) {
		    if ( !is_numeric($steps[1]) || !is_numeric($steps[2]) ) {
                	printHelp();
                	exit(0);
		    } else {
			$config['fl'] = array('min' => $steps[0], 'max' => $steps[1], 'step' => $steps[2]);
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
    if ( !class_exists('ZipArchive') ) {
	logger(" I> La clase ZipArchive no está instalda, generación de KMZ desactivada");
    }
    $timer = microtime(true);
    logger(" D> Leyendo información de radares de >" . $config['radar-data'] . "<");
    $infoCoral = getRadars($config['radar-data'], $parse_all = true);
    logger(" V> Información de radares procesada (" . round(microtime(true) - $timer,2) . "s)");
    if ( $config['mode']['list'] ) {
	logger(" V> Listado de radares configurados:");
	print implode(array_keys($infoCoral), " ") . PHP_EOL;
	exit(0);
    }


    logger(" I> Pasos configurados (min,max,paso): ({$config['fl']['min']},{$config['fl']['max']},{$config['fl']['step']})");
    // comprobamos que todos los sensores solicitados existen
    if ( 0 == count($config['sensores']) ) {
	logger(" E> No se han solicitado sensores para el estudio"); exit(-1);
    }
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
			    logger(" V> Leyendo caché del fichero >{$cache_file}<");
			    if ( false === $coberturas[$sensor]['contornos'] ) {
				logger(" D> La caché no contenía datos, no hay cobertura en FL{$nivelVuelo} para {$sensor}");
				continue;
			    }
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

		if ( false === $config['max-range'] ) {
		    logger(" V> Guardando caché para el sensor >{$sensor}< en >{$cache_file}<");
		    crearCarpetaResultados($config['path']['cache'] . $sensor);
		    file_put_contents($cache_file, json_encode($coberturas[$sensor]['contornos']));
		}
		if ( false === $coberturas[$sensor]['contornos']) {
		    continue;
		}

		// guardar el cálculo en la cache, siempre que no hayamos forzado el alcance
		// si se ha forzado el alcance, la caché está invalidada automáticamente.

	    }
	    // si estamos en mono cobertura, generamos ya el kml
	    if ( isset($config['mode']['monoradar']) && $config['mode']['monoradar'] ) {
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

	    logger (" D> " . "Info memory_usage(" . convertBytes(memory_get_usage(false)) . ") " .
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
	    logger(" N> Radio del Cono: " . round($radioCono, 2) . "NM / " . round($radioConom, 2 ) . "m");
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

/* // probando a quitar esto ahora
        print "[generacionMalladoLatLon start]"; $timer0 = microtime(true);
        $mallado = generacionMalladoLatLon($radar, $flm, $distanciasAlcances);
        printf("[generacionMalladoLatLon ended %3.4fs]", microtime(true) - $timer0);
        // comprobación si hay cobertura en las esquinas de la malla. En ese caso,
        // determinaContornos2 podría fallar
        print "[check coverage overflow "; $timer0 = microtime(true);
        checkCoverageOverflow($mallado['malla']);
        printf(" %3.4fs]", microtime(true) - $timer0);
	print PHP_EOL;
*/

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
        logger(" D> calculosFLdebajoRadar");;
	calculosFLdebajoRadar($radar, $flm);
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
