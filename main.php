<?php

// INCLUSION DE FICHEROS
include 'cargarDatosTerreno.php';
include 'cargarDatosCoordenadas.php';
include 'funcionesAuxiliares.php';
include 'calculos.php';
include 'guardar.php';

// DEFINICION DE RUTAS
$path = "/home/eval/%rassv6%/spain.tsk";
$ruta = "/home/eval/berta/RESULTADOS";


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
	$lugares = array();
	$angulosApantallamiento = array();
	$distanciasCobertura = array();
	$coordenadasGeograficas = array();
	
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
			// pedirDatosUsuario($flMin, $flMax, $paso, $altitudeMode, $poligono, $lugares);
			$flMin = 7;
			$flMax = 7;
			$paso = 100;
			$altitudeMode = 0;
			$lugares[] = "canchoblanco";
			$op = 0;
	
			$altMode = altitudeModetoString($altitudeMode);
			$infoCoral = getRadars($path, $parse_all = true);
			// recoremos todas las localizaciones que nos ha dado el usuario
                        foreach($lugares as $lugar) {
				$coordenadas = cargarDatosCoordenadas($infoCoral, $lugar);
				$radarOriginal = cargarDatosTerreno($coordenadas['screening'], $radioTerrestreAumentado);
				$hA = $radarOriginal['towerHeight'] + $radarOriginal['terrainHeight'];
                                $ruta = $rutaResultados . $radarOriginal['site'] . "/";
                                if ( !is_dir( $ruta ) ) {
                                    crearCarpetaResultados($radarOriginal, $ruta);
                                    clearstatcache();
                                }
				for ($fl = $flMin; $fl <= $flMax; $fl += $paso){
				    print "${fl}00 feet" . PHP_EOL;
				    $radar = $radarOriginal;
				    $flm = fltoMeters($fl); 
				    // DISTINCION DE CASOS 
                                    if ( $flm >= $hA ) { // CASO A (nivel de vuelo por encima de la posición del radar)
					calculosFLencimaRadar($radar, $flm, $radioTerrestreAumentado, $angulosApantallamiento, $distanciasCobertura);
					calculaCoordenadasGeograficas($radar, $coordenadas, $distanciasCobertura, $flm, $coordenadasGeograficas);
					crearKML($coordenadasGeograficas, $radar, $ruta, $fl, $altMode);
				    } else { // CASO B (nivel de vuelo por debajo de la posición del radar)
				        print "[calculosFLdebajoRadar]";
				        calculosFLdebajoRadar($radar, $flm, $radioTerrestreAumentado);
				        print "[generacionMallado]";
                                        generacionMallado($radar, $radioTerrestreAumentado, $malla);
                                        print "[mallaMarco]";
	                                $mallaGrande = mallaMarco($malla);
	                                print "[determinaContornos]";
					determinaContornos($radar, $mallaGrande, $flm, $listaContornos);
					print "[calculaCoordenadasGeograficasB]";
					calculaCoordenadasGeograficasB($radar, $flm, $coordenadas, $listaContornos);
					print "[crearKmlB]";
    					crearKmlB($listaContornos, $radar, $ruta, $fl, $altMode);
                                    }
				    clearstatcache();
				}// for interno
			}// foreach
			break;
	}// switch
    } while ($op != 0);

    return;
}
