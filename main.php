<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

// INCLUSIÓN DE FICHEROS
include 'cargarDatosTerreno.php';
include 'cargarDatosCoordenadas.php';
include 'funcionesAuxiliares.php';
include 'calculos.php';
include 'guardar.php';

// DEFINICIÓN DE RUTAS
$path = "/home/eval/%rassv6%/spain.tsk";

// DEFINICIÓN DE CONSTANTES
CONST PI = M_PI;
CONST RADIO_TERRESTRE = 6371000;
CONST MILLA_NAUTICA_EN_METROS = 1852; // metros equivalentes a 1 milla nautica
CONST TOTAL_AZIMUTHS = 360;
CONST MAX_AZIMUTHS = 720;

/*
$lugares = explode(" ", "aitana alcolea alicante aspontes auchlias barajas barcelona begas biarritz canchoblanco eljudio erillas espineiras foia fuerteventura gazules girona grancanaria inoges lapalma malaga1 malaga2 monflorite montejunto montpellier motril palmamallorca paracuellos1 paracuellos2 penaschache penaschachemil portosanto pozonieves randa sierraespuna soller solorzano taborno tenerifesur turrillas valdespina valencia valladolid villatobas");
foreach($lugares as $l) {
    print "php main.php $l > logs/$l.log &" . PHP_EOL;
}
exit();
*/

programaPrincipal();
exit(0);

function programaPrincipal(){
    global $argv, $argc;

    $path = "/home/eval/%rassv6%/spain.tsk";
    $rutaResultados = "." . DIRECTORY_SEPARATOR . "RESULTADOS" . DIRECTORY_SEPARATOR;

    $radioTerrestreAumentado = 0;
    $poligono = false;
    $ordenarPorRadar = false;
    $lugares = explode(" ", "aitana alcolea alicante aspontes auchlias barajas barcelona begas biarritz canchoblanco eljudio erillas espineiras foia fuerteventura gazules girona grancanaria inoges lapalma malaga1 malaga2 monflorite montejunto montpellier motril palmamallorca paracuellos1 paracuellos2 penaschache penaschachemil portosanto pozonieves randa sierraespuna soller solorzano taborno tenerifesur turrillas valdespina valencia valladolid villatobas");
    $altMode = altitudeModetoString($altitudeMode = 0);
    $infoCoral = getRadars($path, $parse_all = true);
    $flMin = 1;
    $flMax = 400;
    $paso = 1;

    if ( $argc > 1 ){ 
        $lugares = array();
        for($i = 1; $i < $argc; $i++) {
            $lugares[] =$argv[$i];
        }
    }

    // Definicion de la estructura de datos que guarda las coordenadas del kml.
    $coordenadasGeograficas = array ( array('longitud' => 0, 'latitud' => 0, 'altura' => 0) );

    $op = 1;
    do{
        // $op = menu();
        switch ($op) {
	case 0:
	    echo "Hasta la vista!". PHP_EOL;
	    break;
	case 1:
    	    //pedirDatosUsuario($flMin, $flMax, $paso, $altitudeMode, $poligono, $lugares, $ordenarPorRadar);
            $op = 0;
            // para probar con una distancia más pequeña y forzar alcance a 20NM
	    // $infoCoral['canchoblanco']['secondaryMaximumRange'] = 20;
	    // recorremos todas las localizaciones que nos ha dado el usuario
            foreach($lugares as $lugar) {
		$coordenadas = cargarDatosCoordenadas($infoCoral, $lugar);

		$radarOriginal = cargarDatosTerreno(
		    $coordenadas['screening'],
		    $defaultRange = $infoCoral[strtolower($lugar)]['secondaryMaximumRange']
		);
		// print_r($radarOriginal);
	        // para probar con una distancia más pequeña y forzar alcance a 20NM
		// $radarOriginal['range'] = 20*1852;

		for ($fl = $flMin; $fl <= $flMax; $fl += $paso){
                    $nivelVuelo = str_pad((string)$fl,3,"0", STR_PAD_LEFT);
		    if ( $ordenarPorRadar ) {
                        $ruta = $rutaResultados . $radarOriginal['site'] . DIRECTORY_SEPARATOR;
                    } else {
                        $ruta = $rutaResultados . $nivelVuelo . DIRECTORY_SEPARATOR;
                    }
                    if ( !is_dir( $ruta ) ) {
                        crearCarpetaResultados($radarOriginal, $ruta);
                        clearstatcache();
                    }
		    print "Generando: ${fl}00 feet" . PHP_EOL;
		    $radar = $radarOriginal;
		    calculosFL($radar, $fl, $ruta, $coordenadas, $altMode, $ordenarPorRadar);

                } // for interno
	    } // foreach
	    break;
	} // switch
    } while ($op != 0);

    return;
}

/*
 * @param string $altMode cadena para que el KML utilice la altura como relativa o absoluta...
 * @param bool $ordenarPorRadar para guardar por directorios por nivel de vuelo o por nombre de radar
 */
function calculosFL($radar, $fl, $ruta, $coordenadas, $altMode, $ordenarPorRadar) {

    $hA = $radar['towerHeight'] + $radar['terrainHeight'];
    $flm = $fl*100*FEET_TO_METERS; // fl en metros
    $nivelVuelo = str_pad((string)$fl,3,"0", STR_PAD_LEFT);

    // DISTINCIÓN DE CASOS 
    if ( $flm >= $hA ) { // CASO A (nivel de vuelo por encima de la posición del radar)
        $angulosApantallamiento = array();
	$distanciasCobertura = array();
        $coordenadasGeograficas = array();
        calculosFLencimaRadar($radar, $flm, $angulosApantallamiento, $distanciasCobertura);
	calculaCoordenadasGeograficasA($radar, $coordenadas, $distanciasCobertura, $flm, $coordenadasGeograficas);
	crearKML($coordenadasGeograficas, $radar, $ruta, $fl, $altMode, $ordenarPorRadar);
    } else { // CASO B (nivel de vuelo por debajo de la posición del radar)
        print "[calculosFLdebajoRadar]";
	calculosFLdebajoRadar($radar, $flm);
	print "[generacionMallado]";
        $malla = generacionMallado($radar);
        //printMalla($malla);
        storeMallaAsImage($malla, $ruta . $radar['site'] . "_FL" . $nivelVuelo);
        print "[mallaMarco]";
	$mallaGrande = mallaMarco($malla);
	print "[determinaContornos]";
	determinaContornos($radar, $mallaGrande, $flm, $listaContornos);
	print "[calculaCoordenadasGeograficasB]";
	calculaCoordenadasGeograficasB($radar, $flm, $coordenadas, $listaContornos);
	print "[crearKmlB]" . PHP_EOL;
    	crearKmlB($listaContornos, $radar, $ruta, $fl, $altMode, $ordenarPorRadar);
    }
    return;
}