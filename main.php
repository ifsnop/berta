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

programaPrincipal();

exit(0);

function programaPrincipal(){
    global $argv, $argc;

    $path = "/home/eval/%rassv6%/spain.tsk";
    $rutaResultados = "." . DIRECTORY_SEPARATOR . "RESULTADOS" . DIRECTORY_SEPARATOR;
    $radioTerrestreAumentado = 0;
    $poligono = false;
    $lugares = explode(" ", "aitana alcolea alicante alicantetwr_adsb aspontes auchlias barajas barcelona barcelona-psr begas begas-psr biarritz canchoblanco canchoblanco_adsb ced_adsb eljudio eljudio-psr erillas espineiras espineiras-psr foia fuerteventura gazules girona grancanaria grancanaria-psr inoges lapalma malaga1 malaga2 malaga2-psr monflorite montecodi montejunto montpellier motril lanzarote lanzarote_adsb palmamallorca palmamallorca-psr paracuellos1 paracuellos1-psr paracuellos2 paracuellos2-psr penaschache penaschachemil portosanto pozonieves randa randa-psr sierraespuna soller solorzano taborno tanger tenerifesur tenerifesur-psr turrillas turrillas_adsb valdespina valencia valencia-psr valladolid villatobas");
    // $lugares = explode(" ", "paracuellos1 monflorite alcolea canchoblanco");
    // $lugares = array("soller");
    // $lugares = array("paracuellos1");
    // $lugares = array("biarritz");
    $lugares = array("valdespina"); //"alcolea", "paracuellos1", "monflorite", "valdespina", "paracuellos2");
    $altMode = altitudeModetoString($altitudeMode = 0);
    $infoCoral = getRadars($path, $parse_all = true);

    $flMin = 120;
    $flMax = 120;
    $paso = 1;

    $multiCoberturas = array();
    $modo = 'multiradar'; // 'monoradar'; //'multiradar';

    if ( $argc > 1 ){ 
        $lugares = array();
        for($i = 1; $i < $argc; $i++) {
            $lugares[] = $argv[$i];
        }
    }
    if ( count($lugares) > 1 )
        print "INFO Cálculo batch de " . count($lugares) . " radares" . PHP_EOL;

    $op = 1;
    do {
        // $op = menu();
        switch ($op) {
	case 0:
	    echo "Hasta la vista!". PHP_EOL;
	    break;
	case 1:
            //pedirDatosUsuario($flMin, $flMax, $paso, $altitudeMode, $poligono, $lugares);
            $op = 0;
            // para probar con una distancia más pequeña y forzar alcance a 20NM
	    // $infoCoral['canchoblanco']['secondaryMaximumRange'] = 20;
	    // recorremos todas las localizaciones que nos ha dado el usuario
            foreach($lugares as $lugar) {
                print PHP_EOL . "INFO Procesando $lugar" . PHP_EOL;
                $lugar = strtolower($lugar);
                // carga el fichero de screening en memoria
                // tener en cuenta que si es un $lugar acabado en psr, hay que poner el range del psr
                if ( !isset($infoCoral[$lugar]) ) {
                    die("ERROR: el radar $lugar no está configurado en la bbdd del SASS-C" . PHP_EOL);
                }
                $range = $infoCoral[$lugar]['secondaryMaximumRange'];
                if ( false !== strpos($lugar, "psr") ) {
                    print "cargando alcance del psr" . PHP_EOL;
                    $range = $infoCoral[$lugar]['primaryMaximumRange'];
                }

		$radar = cargarDatosTerreno(
		    $infoCoral[$lugar],
		    $range
		);

                if ( true ) {
                    generateMatlabFiles($radar, $rutaResultados);
                    //continue;
                }

		// print_r($radar);
	        // para probar con una distancia más pequeña y forzar alcance a 20NM
		// $radar['screening']['range'] = 20*1852;
		for ($fl = $flMin; $fl <= $flMax; $fl += $paso) {
                    $nivelVuelo = str_pad( (string)$fl,3,"0", STR_PAD_LEFT );
                    $ruta = array();
                    $ruta[GUARDAR_POR_RADAR] = $rutaResultados . $radar['screening']['site'] . DIRECTORY_SEPARATOR;
                    $ruta[GUARDAR_POR_NIVEL] = $rutaResultados . $nivelVuelo . DIRECTORY_SEPARATOR;
                    crearCarpetaResultados($ruta[GUARDAR_POR_NIVEL]);
                    crearCarpetaResultados($ruta[GUARDAR_POR_RADAR]);
                    print "INFO Generando: ${fl}00 feet" . PHP_EOL;
		    $ret = calculosFL($radar, $fl, $nivelVuelo, $ruta, $altMode);
		    if ( 'multiradar' == $modo )
		        $multiCoberturas[$lugar] = $ret;
		    print "INFO Uso memoria: " . convertBytes(memory_get_usage(false)) . " " .
	                "Pico uso memoria: " . convertBytes(memory_get_peak_usage(false)) . PHP_EOL;

                } // for interno
	    } // foreach
	    //break; // break para generar solo 1 nivel
	} // switch
    } while ($op != 0);

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

