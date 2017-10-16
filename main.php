<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

// INCLUSION DE FICHEROS
include 'cargarDatosTerreno.php';
include 'cargarDatosCoordenadas.php';
include 'funcionesAuxiliares.php';
include 'calculos.php';
include 'guardar.php';

// DEFINICION DE RUTAS
$path = "/home/eval/%rassv6%/spain.tsk";

CONST PI = M_PI;
CONST RADIO_TERRESTRE = 6371000;
CONST MILLA_NAUTICA_EN_METROS = 1852; // metros equivalentes a 1 milla nautica
CONST TOTAL_AZIMUTHS = 360;
CONST MAX_AZIMUTHS = 720;


programaPrincipal();

function programaPrincipal(){
 	
 	$path = "/home/eval/%rassv6%/spain.tsk";
 	$rutaResultados = "./RESULTADOS/";
	
	$op = 0;
	$fl = 0;
	$flMax = 0;
	$flMin = 0;
	$paso = 0;
	$hA = 0;
	$altitudeMode = 0;
	$radioTerrestreAumentado = 0;
	$poligono = false;
	$ordenarPorRadar = true;
	$lugares = array();
	
	// Definicion de la estructura de datos que guarda las coordenadas del kml.
	$coordenadasGeograficas = array ( array('longitud' => 0, 'latitud' => 0, 'altura' => 0) );

    $op = 1;
    do{
	// $op = menu();
	
	switch ($op) {
		case 0:
			echo "Hasta la vista!". PHP_EOL;
			clearstatcache();
			break;
		case 1:
			//pedirDatosUsuario($flMin, $flMax, $paso, $altitudeMode, $poligono, $lugares, $ordenarPorRadar);
			
			// tenemos un problema con constantina a 2800, que el primer punto no tiene cobertura y hay que probar
			// una nueva solucion
			
			// ademas, todos los scr por debajo del radar (caso b) que tengan 720 azimut, se pintan mal
			// ejemlo: constantina, auch lias...
			
			$flMin = 0;
			$flMax = 400;
			$paso = 1;
			$altitudeMode = 0;
			$lugares = explode(" ", "aitana alcolea alicante aspontes auchlias barajas barcelona begas biarritz canchoblanco eljudio erillas espineiras foia fuerteventura gazules girona grancanaria inoges lapalma malaga1 malaga2 monflorite montejunto montpellier motril palmamallorca paracuellos1 paracuellos2 penaschache penaschachemil portosanto pozonieves randa sierraespuna soller solorzano taborno tenerifesur turrillas valdespina valencia valladolid villatobas");
			// $lugares = array("auchlias", "begas");
			$lugares = array("montejunto");
			$op = 0;
			$ordenarPorRadar = false;
			
			$altMode = altitudeModetoString($altitudeMode);
			$infoCoral = getRadars($path, $parse_all = true);
			// recorremos todas las localizaciones que nos ha dado el usuario
                        foreach($lugares as $lugar) {
				$coordenadas = cargarDatosCoordenadas($infoCoral, $lugar);
				$radarOriginal = cargarDatosTerreno(
				    $coordenadas['screening'],
				    $radioTerrestreAumentado,
				    $defaultRange = $infoCoral[strtolower($lugar)]['secondaryMaximumRange']
				);
				$hA = $radarOriginal['towerHeight'] + $radarOriginal['terrainHeight'];
				for ($fl = $flMin; $fl <= $flMax; $fl += $paso){
				    if ( $ordenarPorRadar ) {
                                        $ruta = $rutaResultados . $radarOriginal['site'] . "/";
                                    } else {
                                        $nivelVuelo = str_pad((string)$fl,3,"0", STR_PAD_LEFT);
                                        $ruta = $rutaResultados . $nivelVuelo . DIRECTORY_SEPARATOR;
                                    }
                                    if ( !is_dir( $ruta ) ) {
                                        crearCarpetaResultados($radarOriginal, $ruta);
                                        clearstatcache();
                                    }
				
				    print "Generando: ${fl}00 feet" . PHP_EOL;
				    $radar = $radarOriginal;
				    $flm = $fl*100*FEET_TO_METERS; 
				    // DISTINCION DE CASOS 
                                    if ( $flm >= $hA ) { // CASO A (nivel de vuelo por encima de la posición del radar)
                                        $angulosApantallamiento = array();
	                                $distanciasCobertura = array();
                                        $coordenadasGeograficas = array();

					calculosFLencimaRadar($radar, $flm, $radioTerrestreAumentado, $angulosApantallamiento, $distanciasCobertura);
					calculaCoordenadasGeograficasA($radar, $coordenadas, $distanciasCobertura, $flm, $coordenadasGeograficas);
					crearKML($coordenadasGeograficas, $radar, $ruta, $fl, $altMode, $ordenarPorRadar);
				    } else { // CASO B (nivel de vuelo por debajo de la posición del radar)
				        print "[calculosFLdebajoRadar]";
				        calculosFLdebajoRadar($radar, $flm, $radioTerrestreAumentado);
				        print "[generacionMallado]";
                                        generacionMallado($radar, $radioTerrestreAumentado, $malla);
                                        // printMalla($malla);
                                        print "[mallaMarco]";
	                                $mallaGrande = mallaMarco($malla);
	                                print "[determinaContornos]";
					determinaContornos($radar, $mallaGrande, $flm, $listaContornos);
					print "[calculaCoordenadasGeograficasB]";
					calculaCoordenadasGeograficasB($radar, $flm, $coordenadas, $listaContornos);
					print "[crearKmlB]" . PHP_EOL;
    					crearKmlB($listaContornos, $radar, $ruta, $fl, $altMode, $ordenarPorRadar);
                                    }
				    clearstatcache();
				}// for interno
			}// foreach
			break;
	}// switch
    } while ($op != 0);

    return;
}
