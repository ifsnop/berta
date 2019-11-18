<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('memory_limit', '16G');

// INCLUSIÓN DE FICHEROS
include_once('inc.cargarScreening.php');
include_once('inc.cargarCoordenadas.php');
include_once('inc.auxiliares.php');
include_once('inc.conrec.php');
include_once('inc.calculos.php');
include_once('inc.multiCalculos.php');
include_once('inc.guardar.php');
// include_once('MartinezRueda/Algorithm.php');

// DEFINICIÓN DE CONSTANTES
CONST RADIO_TERRESTRE = 6371000;
CONST MILLA_NAUTICA_EN_METROS = 1852; // metros equivalentes a 1 milla nautica
CONST GUARDAR_POR_NIVEL = 0; // puntero para el array de resultados
CONST GUARDAR_POR_RADAR = 1; // puntero para el array de resultados

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

exit(0);

function printHelp() {
    print "-r radar_list     | --radar-list radar_list (lista entre comillas)" . PHP_EOL;
    print "-m max_range      | --max-range max_range (in NM)" . PHP_EOL;
    print "-1 (default) | -2 | --monoradar (default)" . PHP_EOL;
    print "                  | --multiradar" . PHP_EOL;
    print "-l                | --list" . PHP_EOL;
    print "-s min,max,step   | --steps min,max,step" . PHP_EOL;
    print "-h                | --help" . PHP_EOL;
    return;
}

function programaPrincipal(){
    global $argv, $argc;
    // default values, calculate everything
    $lugares = explode(" ", "aitana alcolea alicante alicantetwr_adsb aspontes auchlias barajas barcelona barcelona-psr begas begas-psr biarritz bilbaotwr_adsb canchoblanco canchoblanco_adsb ced_adsb elgoro eljudio eljudio-psr erillas espineiras espineiras-psr foia fuerteventura gazules girona grancanaria grancanaria-psr inoges lapalma malaga1 malaga2 malaga2-psr monflorite montecodi montejunto montpellier motril lanzarote lanzarote_adsb palmamallorca palmamallorca-psr paracuellos1 paracuellos1-psr paracuellos2 paracuellos2-psr penaschache penaschachemil portosanto pozonieves randa randa-psr randa_asdb sierraespuna soller solorzano taborno tanger tenerifesur tenerifesur-psr turrillas turrillas_adsb valdespina valencia valencia-psr valladolid villatobas");
    $flMin = 1;
    $flMax = 400;
    $paso = 1;
    $maxRange = false;
    $modo = 'monoradar';

    $shortopts  = "r:"; // radar name
    $shortopts .= "m:"; // max range in NM
    $shortopts .= "1"; // modo monoradar
    $shortopts .= "2"; // modo multiradar
    $shortopts .= "l"; // list available radars
    $shortopts .= "h"; // help
    $shortopts .= "s:"; // steps
    $longopts = array( "radar-list:", "max-range:", "monoradar", "multiradar", "list", "help", "steps:" );
    $options = getopt( $shortopts, $longopts );
    foreach( $options as $key => $value ) {
        switch( $key ) {
            case 'help':
            case 'h':
                printHelp();
                exit(0);
                break;
            case 'list':
            case 'l':
                print_r( $lugares );
                exit(0);
                break;
            case 'radar-list':
            case 'r':
                $lugares = explode( " ", $value );
                print "INFO Ejecutando con la siguiente selección de radar(es) [" . implode(",", $lugares) . "]" . PHP_EOL;
                break;
            case 'max-range':
            case 'm':
                $maxRange = $value;
                print "INFO Alcance forzado a $value NM" . PHP_EOL;
                break;
            case 'steps':
            case 's':
                $steps = explode( ",", $value );
                // print_r($steps); var_dump($steps);
                if (!is_numeric($steps[0]) || !is_numeric($steps[1]) || !is_numeric($steps[2]) ) {
                    printHelp();
                    exit(0);
                }
                $flMin = $steps[0]; $flMax = $steps[1]; $paso = $steps[2];
                break;
            case '1':
            case 'monoradar':
                break;
            case '2':
            case 'multiradar':
                $modo = 'multiradar';
                print "INFO Modo *multiradar* activado" . PHP_EOL;
                break;
            default:
                print "ERROR Parámetro no esperado: $key" . PHP_EOL;
                exit(0);
        }
    }

    $path = "/home/eval/%rassv6%/spain.tsk";
    $rutaResultados = "." . DIRECTORY_SEPARATOR . "RESULTADOS" . DIRECTORY_SEPARATOR;
    $poligono = false;
    $altMode = altitudeModetoString($altitudeMode = 0);
    $infoCoral = getRadars($path, $parse_all = true);

    print "Pasos configurados (min,max,paso): (${flMin},${flMax},${paso})" . PHP_EOL;

    $multiCoberturas = array();

    //pedirDatosUsuario($flMin, $flMax, $paso, $altitudeMode, $poligono, $lugares);
    // recorremos todas las localizaciones que nos ha dado el usuario
    foreach($lugares as $lugar) {
        print "INFO Procesando $lugar" . PHP_EOL;
        $lugar = strtolower($lugar);
        // carga el fichero de screening en memoria
        // tener en cuenta que si es un $lugar acabado en psr, hay que poner el range del psr
        if ( !isset($infoCoral[$lugar]) ) {
            die("ERROR el radar $lugar no está configurado en la bbdd del SASS-C" . PHP_EOL);
        }
        $range = $infoCoral[$lugar]['secondaryMaximumRange'];
        if ( false !== strpos($lugar, "psr") ) {
            print "INFO Cargando alcance del psr" . PHP_EOL;
            $range = $infoCoral[$lugar]['primaryMaximumRange'];
        }
        // si hubiese un alcance forzado, configurarlo ahora para evaluar
        // es el equivalente a hacerlo aquí:
        // $infoCoral['canchoblanco']['secondaryMaximumRange'] = 20;
        if ( false !== $maxRange ) {
            $range = $maxRange;
        }

	$radar = cargarDatosTerreno( $infoCoral[$lugar], $range );

        if ( true ) {
            // para la herramienta de cálculo de kmz de matlab
            generateMatlabFiles($radar, $rutaResultados);
            //continue;
        }

        $ruta = array();
        $ruta[GUARDAR_POR_RADAR] = $rutaResultados . $radar['screening']['site'] . DIRECTORY_SEPARATOR;
        crearCarpetaResultados($ruta[GUARDAR_POR_RADAR]);
        for ($fl = $flMin; $fl <= $flMax; $fl += $paso) {
            $ret = false; $from_cache = false;
            $nivelVuelo = str_pad( (string)$fl,3,"0", STR_PAD_LEFT );
            $ruta[GUARDAR_POR_NIVEL] = $rutaResultados . $nivelVuelo . DIRECTORY_SEPARATOR;
            crearCarpetaResultados($ruta[GUARDAR_POR_NIVEL]);
            print "INFO Generando: ${fl}00 feet";
            // mira primero si la malla está en la cache
            if ( file_exists($ruta[GUARDAR_POR_RADAR] . $radar['screening']['site'] . "-FL${nivelVuelo}.json") ) {
                $ret = file_get_contents($ruta[GUARDAR_POR_RADAR] . $radar['screening']['site'] . "-FL${nivelVuelo}.json");
                if ( false !== $ret ) {
                    $ret = json_decode($ret, $assoc = true);
                    $from_cache = true;
                    print " [cached]";
                }
            }
            print PHP_EOL;
            if ( $ret === NULL || $ret === false ) {
                $ret = calculosFL($radar, $fl, $nivelVuelo, $ruta, $altMode);
            }
            // si hemos generado la malla, la guardamos en la cache
            if ( false == $from_cache) {
                file_put_contents($ruta[GUARDAR_POR_RADAR] . $radar['screening']['site'] .  "-FL${nivelVuelo}.json", json_encode($ret));
            }
            if ( 'multiradar' == $modo ) {
                $multiCoberturas[$lugar] = $ret;
                // print "DEBUG Uso memoria: " . convertBytes(memory_get_usage(false)) . " " .
                //    "Pico uso memoria: " . convertBytes(memory_get_peak_usage(false)) . PHP_EOL;
            }
        } // for interno
    } // foreach

    if ( 'multiradar' != $modo )
        return;

    $ruta = array('MULTI' => $rutaResultados . "MULTI" . DIRECTORY_SEPARATOR);
    crearCarpetaResultados($ruta['MULTI']);                
    multicobertura($multiCoberturas, $flMin, $ruta, $altMode);

    return;
}

/*
 * @param string $altMode cadena para que el KML utilice la altura como relativa o absoluta...
 */
function calculosFL($radar, $fl, $nivelVuelo, $ruta, $altMode) { //, $modo = 'monoradar') {

    $hA = $radar['screening']['towerHeight'] + $radar['screening']['terrainHeight'];
    $flm = $fl*100*FEET_TO_METERS; // fl en metros

    // DISTINCIÓN DE CASOS 
    if ( $flm >= $hA ) { // CASO A (nivel de vuelo por encima de la posición del radar)
        // inicio para calculo por encima con método vectorial
        $distanciasAlcances = calculosFLencimaRadar($radar, $flm);

        $newRange = obtieneMaxAnguloConCoberturaA($distanciasAlcances);
        $radar['screening']['range'] = round($newRange);
        $radar['range'] = round($newRange);
        $listaContornos2 = calculaCoordenadasGeograficasA($radar, $flm, $distanciasAlcances);
        	
        // codigo para generar malla por encima para la multicobertura
        // con listaContornos2 ya podemos generar una cobertura vectorial
        $mallado = generacionMalladoLatLon($radar, $flm, $distanciasAlcances);

        // print_r(array_keys($mallado['mallaLatLon']));exit(0);
        // con esto generamos cobertura matricial en el kml, que no creo que sea
        // necesario.
        // se podría probar a interseccionar con Martinez-Rueda, de momento no.
        /*
	print "[determinaContornos2 start]"; $timer0 = microtime(true);
        $listaContornos2 = determinaContornos2($mallado['malla']);
	if ( 0 == count($listaContornos2) ) {
	    print PHP_EOL . "INFO: No se genera KML/PNG/TXT porque no existe cobertura al nivel de vuelo FL" . $nivelVuelo . PHP_EOL;
	    return false;
	}
	printf("[determinaContornos2 ended %3.4fs]", microtime(true) - $timer0);
        print "[calculaCoordenadasGeograficasB]";
        $listaContornos2 = calculaCoordenadasGeograficasB($radar, $flm, $listaContornos2);
        */
        // fin código para generar malla por encima

    } else { // CASO B (nivel de vuelo por debajo de la posición del radar)

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
	if ( 0 == count($listaContornos2) ) {
	    print PHP_EOL . "INFO: No se genera KML/PNG/TXT porque no existe cobertura al nivel de vuelo FL" . $nivelVuelo . PHP_EOL;
	    return false;
	}
	printf("[determinaContornos2 ended %3.4fs]", microtime(true) - $timer0);

        print "[calculaCoordenadasGeograficasB]";
        $listaContornos2 = calculaCoordenadasGeograficasB($radar, $flm, $listaContornos2);
    }
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
    return $mallado['mallaLatLon'];
}
