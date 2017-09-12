<?php

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
	$angulosApantallamiento = array();
	$distanciasCobertura = array();
	$coordenadasGeograficas = array();
	
	// Definicion de la estructura de datos que guarda las coordenadas del kml.
	$coordenadasGeograficas = array ( array('longitud' => 0, 'latitud' => 0, 'altura' => 0) );

    $op = 1;
    do{
	$op = menu();
	
	switch ($op) {
		case 0:
			echo "Hasta la vista!". PHP_EOL;
			clearstatcache();
			break;
		case 1:
			pedirDatosUsuario($flMin, $flMax, $paso, $altitudeMode, $poligono, $lugares, $ordenarPorRadar);
			/*
			$flMin = 10;
			$flMax = 10;
			$paso = 100;
			$altitudeMode = 0;
			$lugares[] = "canchoblanco";
			$op = 0;
			*/
			$altMode = altitudeModetoString($altitudeMode);
			$infoCoral = getRadars($path, $parse_all = true);
			// recoremos todas las localizaciones que nos ha dado el usuario
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
				
				    print "${fl}00 feet" . PHP_EOL;
				    $radar = $radarOriginal;
				    $flm = fltoMeters($fl); 
				    // DISTINCION DE CASOS 
                                    if ( $flm >= $hA ) { // CASO A (nivel de vuelo por encima de la posición del radar)
					calculosFLencimaRadar($radar, $flm, $radioTerrestreAumentado, $angulosApantallamiento, $distanciasCobertura);
					calculaCoordenadasGeograficas($radar, $coordenadas, $distanciasCobertura, $flm, $coordenadasGeograficas);
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
					print "[crearKmlB]";
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
