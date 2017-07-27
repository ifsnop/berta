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
 	$ruta = "/home/eval/berta/RESULTADOS";
	
	$op = 0;
	$fl = 0;
	$flMax = 0;
	$flMin = 0;
	$paso = 0;
	$hA = 0;
	$altitudeMode = 0;
	$radioTerrestreAumentado = 0;
	$poligono = false;
	$lugares = "";
	$angulosApantallamiento = array();
	$distanciasCobertura = array();
	$coordenadasGeograficas = array();
	$listaC = array();
	
	// Definicion de la estructura de datos que guarda las coordenadas del kml.
	$coordenadasGeograficas = array ( array('longitud' => 0, 'latitud' => 0, 'altura' => 0) );

do{
	$op = menu();
	
	switch ($op) {
		case 0:
			echo "Hasta la vista!". PHP_EOL;
			clearstatcache();
			break;
		case 1:
			pedirDatosUsuario($flMin, $flMax, $paso, $altitudeMode, $poligono, $lugares);
	
			$altMode = altitudeModetoString($altitudeMode);
			// para cada nivel de vuelo se debe generar un KML
			$ruta = "/home/eval/berta/RESULTADOS" . "/". $radar['site'] . "/";
				
			$infoCoral = getRadars($path, $parse_all = true);
			$tope = count ($lugares);
			// recoremos todas las localizaciones que nos ha dado el usuario
			for ($i = 0; $i < $tope; $i++){
				$coordenadas = cargarDatosCoordenadas($infoCoral, $lugares[$i]);
				$radar = cargarDatosTerreno($coordenadas['screening'], $radioTerrestreAumentado);
				$hA = $radar['towerHeight'] + $radar['terrainHeight'];
				
				for ($fl = $flMin; $fl <= $flMax; $fl = $fl+$paso){
					
				    $flm = fltoMeters($fl); 
				    
				    // DISTINCION DE CASOS 
				    
				    // CASO A 
					if ($flm >= $hA){
						calculosFLencimaRadar($radar, $flm, $radioTerrestreAumentado, $angulosApantallamiento, $distanciasCobertura);
						calculaCoordenadasGeograficas($radar, $coordenadas, $distanciasCobertura, $flm, $coordenadasGeograficas);
					}		
					else{ // CASO B 
						calculosFLdebajoRadar($radar, $flm, $radioTerrestreAumentado);
						generacionMallado($radar, $radioTerrestreAumentado, $malla);
						$mallaGrande = array();
						$mallaGrande = mallaMarco($malla);
						determinaContornos($radar, $mallaGrande, $flm, $listaContornos);
						calculaCoordenadasGeograficasB($radar, $flm, $coordenadas, $listaContornos);
					}
		
					if (is_dir($ruta)){ // si la carpeta Resultados ya existe
						if (opendir($ruta)){
							//$ruta = "/home/eval/berta/RESULTADOS" . "/". $radar['site'] . "/"; // modificamos la ruta para crear una carpeta dentro de la carpeta resultados con el nombre del radar
							 if ($flm >= $hA){
								crearKML($coordenadasGeograficas, $radar, $ruta, $fl, $altMode);
								clearstatcache();
							 }
							 else{
							 	crearKmlB($coordenadasGeograficas, $radar, $ruta, $fl, $altMode);
							 	clearstatcache();
							 }
						}
					}else // si la carpeta Resultados  no existe ....
						if(crearCarpetaResultados($radar, $ruta, $coordenadasGeograficas)){ // /home/eval/berta/RESULTADOS/LE_VALLADOLID
							//$ruta = $ruta ."/". $radar['site'] . "/";
							if ($flm >= $hA){
								crearKML($coordenadasGeograficas, $radar, $ruta, $fl, $altMode);
								clearstatcache();
							}
							else{// CASO B
							crearKmlB($listaC, $radar, $ruta, $fl, $altMode);
							clearstatcache();
						}
					}
				}// for interno
			}// for externo
			break;
		}// switch
	}while ($op != 0);
} 
 

