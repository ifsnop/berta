<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('memory_limit', '16G');

// INCLUSIÓN DE FICHEROS
include 'cargarDatosTerreno.php';
include 'cargarDatosCoordenadas.php';
include 'funcionesAuxiliares.php';
include 'calculos.php';
include 'guardar.php';

// DEFINICIÓN DE CONSTANTES
CONST RADIO_TERRESTRE = 6371000;
CONST MILLA_NAUTICA_EN_METROS = 1852; // metros equivalentes a 1 milla nautica
CONST GUARDAR_POR_NIVEL = 0; // puntero para el array de resultados
CONST GUARDAR_POR_RADAR = 1; // puntero para el array de resultados

$malla2 = array(
array(0,0,0,0,0,0,0,0),
array(0,0,1,0,0,0,0,0),
array(0,0,0,1,0,0,0,0),
array(0,0,0,1,1,1,0,0),
array(0,0,0,1,0,1,0,0),
array(0,0,0,1,1,1,0,0),
array(0,0,0,0,0,0,0,0),
array(0,0,0,0,0,0,0,0),
);
/*
$contornos = determinaContornos2($malla2);
print_r($contornos);

foreach($contornos as $contorno) {
    foreach($contorno as $p) {
        print $p['fila'] . ";" . $p['col'] . PHP_EOL;    
    }
}
exit(-1);
*/

/*
$lugares = explode(" ", "aitana alcolea alicante aspontes auchlias barajas barcelona begas biarritz canchoblanco eljudio erillas espineiras foia fuerteventura gazules girona grancanaria inoges lapalma malaga1 malaga2 monflorite montejunto montpellier motril palmamallorca paracuellos1 paracuellos2 penaschache penaschachemil portosanto pozonieves randa sierraespuna soller solorzano taborno tenerifesur turrillas valdespina valencia valladolid villatobas");
foreach($lugares as $l) {
    print "php main.php $l > logs/$l.log &" . PHP_EOL;
}
exit();
*/
/*
print roundE("40.1") . PHP_EOL;
print roundE("1") . PHP_EOL;
print roundE("32.123456789") . PHP_EOL;
print roundE("2.123456789") . PHP_EOL;
print roundE("2.12345678") . PHP_EOL;
*/
programaPrincipal();
exit(0);

function programaPrincipal(){
    global $argv, $argc;

    $path = "/home/eval/%rassv6%/spain.tsk";
    $rutaResultados = "." . DIRECTORY_SEPARATOR . "RESULTADOS" . DIRECTORY_SEPARATOR;
    $radioTerrestreAumentado = 0;
    $poligono = false;
    $lugares = explode(" ", "aitana alcolea alicante aspontes auchlias barajas barcelona barcelona-psr begas begas-psr biarritz canchoblanco eljudio eljudio-psr erillas espineiras espineiras-psr foia fuerteventura gazules girona grancanaria grancanaria-psr inoges lapalma malaga1 malaga2 malaga2-psr monflorite montejunto montpellier motril palmamallorca palmamallorca-psr paracuellos1 paracuellos1-psr paracuellos2 paracuellos2-psr penaschache penaschachemil portosanto pozonieves randa randa-psr sierraespuna soller solorzano taborno tenerifesur tenerifesur-psr turrillas valdespina valencia valencia-psr valladolid villatobas");
    $altMode = altitudeModetoString($altitudeMode = 0);
    $infoCoral = getRadars($path, $parse_all = true);

    $flMin = 300;
    $flMax = 300;
    $paso = 1;

    if ( $argc > 1 ){ 
        $lugares = array();
        for($i = 1; $i < $argc; $i++) {
            $lugares[] = $argv[$i];
        }
    }

    $op = 1;
    do{
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

                if (true) {
                    generateMatlabFiles($radar, $rutaResultados);
                    // continue;
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
		    calculosFL($radar, $fl, $nivelVuelo, $ruta, $altMode);
                } // for interno
	    } // foreach
	    break;
	} // switch
    } while ($op != 0);

    return;
}

/*
 * @param string $altMode cadena para que el KML utilice la altura como relativa o absoluta...
 */
function calculosFL($radar, $fl, $nivelVuelo, $ruta, $altMode) {

    $hA = $radar['screening']['towerHeight'] + $radar['screening']['terrainHeight'];
    $flm = $fl*100*FEET_TO_METERS; // fl en metros

    // DISTINCIÓN DE CASOS 
    if ( $flm >= $hA ) { // CASO A (nivel de vuelo por encima de la posición del radar)
        $distanciasAlcances = calculosFLencimaRadar($radar, $flm);
	// $listaContornos2 = calculaCoordenadasGeograficasA($radar, $flm, $distanciasAlcances);
	print "[generacionMallado]";
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
        file_put_contents($ruta[GUARDAR_POR_RADAR] . $radar['screening']['site']. "_FL" .  $nivelVuelo . "_malla.json", json_encode($mallaGrande));
        file_put_contents($ruta[GUARDAR_POR_RADAR] . $radar['screening']['site']. "_FL" .  $nivelVuelo . "_contornos.json", json_encode($listaContornos2));
        // printMalla($malla);
        // storeMallaAsImage($malla, $ruta[GUARDAR_POR_RADAR] . $radar['screening']['site'] . "_FL" . $nivelVuelo);
        // storeListaObstaculos($radar, $ruta[GUARDAR_POR_RADAR], $nivelVuelo);
        print "[calculaCoordenadasGeograficasB]";
        $listaContornos2 = calculaCoordenadasGeograficasB($radar, $flm, $listaContornos2);

    } else { // CASO B (nivel de vuelo por debajo de la posición del radar)


        print "[calculosFLdebajoRadar]";
	calculosFLdebajoRadar($radar, $flm);
	print "[generacionMallado]";
        $malla = generacionMallado($radar, $flm, $distanciasAlcances = array() ); // distanciasAlcances no se utiliza
        print "[mallaMarco]";
	$mallaGrande = mallaMarco($malla);
	print "[determinaContornos]";
        $listaContornos2 = determinaContornos2($mallaGrande);
	if ( 0 == count($listaContornos2) ) {
	    print PHP_EOL . "INFO: No se genera KML/PNG/TXT porque no existe cobertura al nivel de vuelo FL" . $nivelVuelo . PHP_EOL;
	    return;
	}
        file_put_contents($ruta[GUARDAR_POR_RADAR] . $radar['screening']['site']. "_FL" .  $nivelVuelo . "_malla.json", json_encode($mallaGrande));
        file_put_contents($ruta[GUARDAR_POR_RADAR] . $radar['screening']['site']. "_FL" .  $nivelVuelo . "_contornos.json", json_encode($listaContornos2));
        // printMalla($malla);
        // storeMallaAsImage($malla, $ruta[GUARDAR_POR_RADAR] . $radar['screening']['site'] . "_FL" . $nivelVuelo);
        // storeListaObstaculos($radar, $ruta[GUARDAR_POR_RADAR], $nivelVuelo);
        print "[calculaCoordenadasGeograficasB]";
        $listaContornos2 = calculaCoordenadasGeograficasB($radar, $flm, $listaContornos2);
    }
    print "[crearKml]" . PHP_EOL;
    creaKml2($listaContornos2, $radar, $ruta, $fl, $altMode);
    return;
}
