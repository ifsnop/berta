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
    $lugares = array("canchoblanco");
    $altMode = altitudeModetoString($altitudeMode = 0);
    $infoCoral = getRadars($path, $parse_all = true);

    $flMin = 1;
    $flMax = 400;
    $paso = 1;

    $modo = 'monoradar'; //'multiradar';

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
	    $multiCoberturas = array();
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
                    print "Generando: ${fl}00 feet" . PHP_EOL;
		    $multiCoberturas[] = calculosFL($radar, $fl, $nivelVuelo, $ruta, $altMode, $modo);
		    print "Uso memoria: " . convertBytes(memory_get_usage(false)) . " " .
	                "Pico uso memoria: " . convertBytes(memory_get_peak_usage(false)) . ")" . PHP_EOL;

                } // for interno
	    } // foreach
	    //break; // break para generar solo 1 nivel
	} // switch
    } while ($op != 0);

    if ( 'multiradar' != $modo )
        return;

    //multicobertura($multiCoberturas, $lugares, $flMin, $ruta, $altMode, $switch);

    return;
}

/*
 * @param string $altMode cadena para que el KML utilice la altura como relativa o absoluta...
 */
function calculosFL($radar, $fl, $nivelVuelo, $ruta, $altMode, $modo = 'monoradar') {

    $hA = $radar['screening']['towerHeight'] + $radar['screening']['terrainHeight'];
    $flm = $fl*100*FEET_TO_METERS; // fl en metros

    // DISTINCIÓN DE CASOS 
    if ( $flm >= $hA ) { // CASO A (nivel de vuelo por encima de la posición del radar)
        // inicio para calculo por encima con método vectorial
        $distanciasAlcances = calculosFLencimaRadar($radar, $flm);
	$listaContornos2 = calculaCoordenadasGeograficasA($radar, $flm, $distanciasAlcances);
	if ( 'multiradar' == $modo ) {
	    $mallado = generacionMalladoLatLon($radar, $flm, $distanciasAlcances);
	}
        // fin para calculo por encima normal
        // inicio para generar malla por encima
/*
	print "[calculosFLporEncimaComoSiFueraPorDebajoRadar]";
	calculosFLdebajoRadar($radar, $flm);
	//print "[generacionMallado]";
        $malla = generacionMallado($radar, $flm, $distanciasAlcances);
        storeMallaAsImage($malla, $ruta[GUARDAR_POR_RADAR] . $radar['screening']['site'] . "_FL" . $nivelVuelo);
        print "[mallaMarco]";
	$mallaGrande = mallaMarco($malla);
	print "[determinaContornos]";
        $listaContornos2 = determinaContornos2($mallaGrande);
	if ( 0 == count($listaContornos2) ) {
	    print PHP_EOL . "INFO: No se genera KML/PNG/TXT porque no existe cobertura al nivel de vuelo FL" . $nivelVuelo . PHP_EOL;
	    return;
	}
        print "[calculaCoordenadasGeograficasB]";
        $listaContornos2 = calculaCoordenadasGeograficasB($radar, $flm, $listaContornos2);
*/
        // fin para generar mallas por encima

    } else { // CASO B (nivel de vuelo por debajo de la posición del radar)

        print "[calculosFLdebajoRadar]";
	calculosFLdebajoRadar($radar, $flm);
        $newRange = obtieneMaxAnguloConCobertura($radar);
        $radar['screening']['range'] = round($newRange);
        $radar['range'] = round($newRange);

//	if ( 'multiradar' == $modo ) {
	    // puede ser hasta 10 segundos más lenta que sin LatLon en 170NM
            print "[generacionMalladoLatLon]";
            $timer0 = microtime(true);
	    $mallado = generacionMalladoLatLon($radar, $flm, $distanciasAlcances = array());
	    printf("[generacionMalladoLatLon: %3.4f]", microtime(true) - $timer0);
//	}

        // comprobación si hay cobertura en las esquinas de la malla,
        // determina contorno podría fallar
        for($i=0;$i<count($mallado['malla']); $i++) {
            for($j=0; $j<count($mallado['malla'][$i]); $j++) {
                if ( $i == 0 || $i == (count($mallado['malla'])-1) || 
                     $j == 0 || $j == (count($mallado['malla'][$i])-1) ) {
                    if ($mallado['malla'][$i][$j] == 1) {
                        die("IFSNOP ERROR hay cobertura en una esquina i:$i j:$j");
                    }
                } else {
                    continue;
                }
            }
        }
    

        print "[generacionMallado]";
//        $timer0 = microtime(true);
//        $malla = generacionMallado($radar, $flm, $distanciasAlcances = array() ); // distanciasAlcances no se utiliza
//        printf("[generacionMallado: %3.4f]", microtime(true) - $timer0);
//        print "[mallaMarco]";
//	$mallaGrande = mallaMarco($malla);
//	$mallaGrande = mallaMarco($mallado['malla']);
//	print "[determinaContornos]";
//        $listaContornos2 = determinaContornos2($mallaGrande);

	print "[determinaContornos]";
        $listaContornos2 = determinaContornos2($mallado['malla']);
	if ( 0 == count($listaContornos2) ) {
	    print PHP_EOL . "INFO: No se genera KML/PNG/TXT porque no existe cobertura al nivel de vuelo FL" . $nivelVuelo . PHP_EOL;
	    return false;
	}

        print "[calculaCoordenadasGeograficasB]";
        $listaContornos2 = calculaCoordenadasGeograficasB($radar, $flm, $listaContornos2);
    }
    print "[crearKml]" . PHP_EOL;
    creaKml2($listaContornos2, $radar, $ruta, $fl, $altMode);
    if ( 'multiradar' == $modo ) {
        return $mallado['mallaLatLon'];
    } else {
        return true; // $listaContornos2;
    }
}

/*
 * busca la distancia mayor a la que hay cobertura, con la idea de poder
 * reducir el alcance (y el tamaño de malla) a esa distancia (por ejemplo,
 * si solo hay cobertura hasta 50NM, no tiene sentido hacer una malla de
 * 250NM, porque así nos evitamos calcular un montón de puntos sin
 * cobertura más adelante.
 */
function obtieneMaxAnguloConCobertura($radar) {
    // $timerStart0 = microtime(true);
    $maxAnguloConCobertura = 0;
    foreach($radar['screening']['listaAzimuths'] as $azimuth => $listaObstaculos) {
        foreach($listaObstaculos as $obstaculo) {
            if ( $obstaculo['estePtoTieneCobertura'] && ($obstaculo['angulo'] > $maxAnguloConCobertura) ) {
                $maxAnguloConCobertura = $obstaculo['angulo'];
            }
        }
    }
    // printf("[%3.4fs]", microtime(true) - $timerStart0);
    print "[ánguloAlcanceMáximo: " . round($maxAnguloConCobertura,3) . "º]";
    $newRange = $maxAnguloConCobertura*$radar['screening']['radioTerrestreAumentado'];
    print "[distanciaAlcanceMáximo: " . round($newRange/MILLA_NAUTICA_EN_METROS,2) . "NM / " . round($newRange,2) . "m]";
    // además de alinear el alcance máximo a múltiplos de 1852 (1NM), le sumamos
    // una milla adicional, para que la matriz nunca acabe con cobertura en una de
    // sus esquinas
    $newRange = round($newRange,0) + (1852 - (round($newRange,0) % 1852)) + 1852;
    print "[distanciaAlcanceMáximoAlineada: " . ($newRange/MILLA_NAUTICA_EN_METROS) . " NM / ${newRange}m]";

    return $newRange;
}
