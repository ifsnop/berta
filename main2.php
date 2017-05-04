<?php

// INCLUSION DE FICHEROS
include 'cargarDatosTerreno.php';
include 'cargarDatosCoordenadas.php';
include 'funcionesAuxiliares.php';
include 'calculos2.php';
include 'guardar.php';

// DEFINICION DE RUTAS
$path = "/home/eval/%rassv6%/spain.tsk";
$ruta = "/home/eval/berta/RESULTADOS";


CONST PI = M_PI;
CONST RADIO_TERRESTRE = 6371000;
CONST MILLA_NAUTICA_EN_METROS = 1852; // metros equivalentes a 1 milla nautica
CONST TOTAL_AZIMUTHS = 360;
CONST MAX_AZIMUTHS = 720;

/*

programaPrincipal();

 function programaPrincipal(){
 	
 	$path = "/home/eval/%rassv6%/spain.tsk";
 	$ruta = "/home/eval/berta/RESULTADOS";
	
	$op =0;
	$fl =0;
	$flMax =0;
	$flMin=0;
	$paso =0;
	$hA =0;
	$altitudeMode =0;
	$radioTerrestreAumentado =0;
	$poligono = false;
	$lugares = "";
	$angulosApantallamiento = array();
	$distanciasCobertura = array();
	$coordenadasGeograficas = array();
	
	// Definicion de la estructura de datos que guarda las coordenadas del kml.
	$coordenadasGeograficas = array ( array('longitud' =>0, 'latitud'  =>0, 'altura'   =>0) );

do{
	$op = menu();
	
	switch ($op) {
		case 0:
			echo "Hasta la vista!". PHP_EOL;
			clearstatcache();
			break;
		case 1:
			pedirDatosUsuario($flMin, $flMax, $paso, $altitudeMode, $poligono, $lugares);
			
			// obtenemos  la informacion de todos los rades de Coral
			$infoCoral   = getRadars($path, $parse_all = true);
		
			$tope = count ($lugares);
			// recoremos todas las localizaciones que nos ha dado el usuario
			for ($i = 0; $i < $tope; $i++){
				// nos quedamos con las coordenadas y la ruta del terreno del radar con el que queremos trabajar
				$coordenadas = cargarDatosCoordenadas($infoCoral, $lugares[$i]);
				// cargamos en memoria los datos del terreno
				$radar = cargarDatosTerreno($coordenadas['screening'], $radioTerrestreAumentado);
				// calculamos la altura del radar
				$hA = $radar['towerHeight'] + $radar['terrainHeight'];
				
				
				// recorremos todos los niveles de vuelo que nos ha indicado el usuario
				for ($fl = $flMin; $fl <= $flMax; $fl = $fl+$paso){
				    $flm = fltoMeters($fl); 
					if ($flm >= $hA){
						calculosFLencimaRadar($radar, $flm, $radioTerrestreAumentado, $angulosApantallamiento, $distanciasCobertura);
						calculaCoordenadasGeograficas($radar, $coordenadas, $distanciasCobertura, $flm, $coordenadasGeograficas);
					}		
					else{
						//calculosFLdebajoRadar($radar, $flm, $radioTerrestreAumentado, $angulosApantallamiento, $distanciasCobertura);
						//calculaCoordenadasChungas($radar, $coordenadas, $distanciasCobertura, $flm, $coordenadasGeograficas);
					}
					
					$altMode = altitudeModetoString($altitudeMode);
					
					// para cada nivel de vuelo se debe generar un KML
					$ruta = "/home/eval/berta/RESULTADOS" . "/". $radar['site'] . "/";
					//echo "RUTA ANTES DE ENTRAR EN EL IF Q COMPRUEBA SI ES DIR:" . $ruta. PHP_EOL;
					if (is_dir($ruta)){ // si la carpeta Resultados ya existe
						//echo "RUTA DESPUES DE COMPROBAR SI ES DIR:" . $ruta. PHP_EOL;
						if (opendir($ruta)){
							//$ruta = "/home/eval/berta/RESULTADOS" . "/". $radar['site'] . "/"; // modificamos la ruta para crear una carpeta dentro de la carpeta resultados con el nombre del radar
							//echo "RUTA ABIERTA: " . $ruta. PHP_EOL;
							crearKML($coordenadasGeograficas, $radar, $ruta, $fl, $altMode);
							clearstatcache();
						}
					}else // si la carpeta Resultados  no existe ....
						if(crearCarpetaResultados($radar, $ruta, $coordenadasGeograficas)){ // /home/eval/berta/RESULTADOS/LE_VALLADOLID
							//$ruta = $ruta ."/". $radar['site'] . "/";
							crearKML($coordenadasGeograficas, $radar, $ruta, $fl, $altMode);
							//echo "LA CARPETA RESULTADOS NO EXISTIA: " . $ruta. PHP_EOL;
							clearstatcache();
						}
				}// for interno
			}// for externo
			break;
		}// switch
	}while ($op != 0);
}

	
*/

// PRUEBAS A FUEGO 
	 
$fl = 10;
$flm = fLtoMeters($fl); // 304 m 
//$fl = 1524; // fl 50 en metros

$coordenadasGeograficas = array (array('longitud' =>0, 'latitud'  =>0, 'altura'   =>0));

$infoCoral = getRadars($path, $parse_all = true);

// nos quedamos con las coordenadas y la ruta del terreno del radar con el que queremos trabajar
$coordenadas = cargarDatosCoordenadas($infoCoral, "Begas");

// cargamos en memoria la informacion de terreno del radar con el que vamos a trabajar
$radar = cargarDatosTerreno('/home/eval/berta/le_begasPRUEBA.scr', $radioTerrestreAumentado);


//calculosFLencimaRadar($radar, $flm, $radioTerrestreAumentado, $angulosApantallamiento, $distanciasCobertura);
//calculaCoordenadasGeograficas($radar, $coordenadas, $distanciasCobertura, $flm, $coordenadasGeograficas);


$anguloMaxCob = calculaAnguloMaximaCobertura($radar, $radioTerrestreAumentado, $flm);
calculosFLdebajoRadar($radar, $flm, $radioTerrestreAumentado, $anguloMaxCob); // mete la lista de obstaculos ampliada para cada azimut



generacionMallado($radar, $radioTerrestreAumentado, $malla);


/*
$malla = array(
    array(0,1,1,1,1,1),
    array(1,1,1,1,1,1),
    array(1,1,0,0,1,1),
    array(1,1,0,0,0,0),
    array(1,1,0,1,1,1),
    array(2,1,0,1,1,0),
    );
*/
printMalla($malla);
tratamientoMallado($malla, "origen.png");
    
$nuevaMalla = array();
$sizeMallaI = count($malla);

for( $i = 0; $i < $sizeMallaI; $i++ ) {
    $sizeMallaJ = count($malla[$i]);
    //print $sizeMallaI . " " . $sizeMallaJ . PHP_EOL;
    
    for( $j = 0; $j < $sizeMallaJ; $j++ ) {
        if ( !isset($malla[$i][$j]) ) {
    	    $nuevaMalla[$i][$j] = 2;
	    continue;
	}
	if ( $i == 0 ||
	    $j == 0 ||
	    $i == ($sizeMallaI - 1) ||
	    $j == ($sizeMallaJ - 1) ) {
	    $nuevaMalla[$i][$j] = $malla[$i][$j];
	    continue;
	}
	if ( (1 == $malla[$i][$j]) ) {
	    if ( esRodeado($malla, $i, $j) ) {
		$nuevaMalla[$i][$j] = 0;
	    } else {
		$nuevaMalla[$i][$j] = 1;
	    }
	} else {
	    $nuevaMalla[$i][$j] = 0;
	}
    }
}


print PHP_EOL;
printMalla($nuevaMalla);
tratamientoMallado($nuevaMalla, "destino.png");


function esRodeado($malla, $i, $j) {

/*print $malla[$i-1][$j] . " " . 
    $malla[$i+1][$j] . " " . 
    $malla[$i][$j-1] . " " . 
    $malla[$i][$j+1] . " ";
 */
    if ( $malla[$i-1][$j] == 1 &&
	$malla[$i+1][$j] == 1 &&
	$malla[$i][$j-1] == 1 &&
	$malla[$i][$j+1] == 1 ) {
	return true;
    } else {
	return false;
    }

}


function printMalla($malla) {
for($i = 0; $i < ( count($malla) > 25 ? 25 : count($malla) ); $i++) {
    for($j = 0; $j < ( count($malla[$i]) > 25 ? 25 : count($malla[$i]) ); $j++)
	print $malla[$i][$j];
    print PHP_EOL;
}
}










// PROBAMOS EL CASO A
//echo "PROBAMOS EL CASO A". PHP_EOL;
//$x=  count($radar['listaAzimuths'][0]);
//for ($i=0; $i<$x; $i++){ // recorremos los obstaculos del azimut 0
//	echo "ANGULO: " . $radar['listaAzimuths'][0][$i]['angulo']. PHP_EOL;
//	echo "ALTURA: " . $radar['listaAzimuths'][0][$i]['altura']. PHP_EOL;
//	echo "COB: " . $radar['listaAzimuths'][0][$i]['estePtoTieneCobertura']. PHP_EOL;
//}


//$listaObstaculosAmpliada = interpolarPtosTerreno($radar['listaAzimuths'][0], $radioTerrestreAumentado, false); (GOOD)
//miraSiHayCobertura($listaObstaculosAmpliada, $flm); (GOOD)
exit;


//echo "LISTA AMPLIADA: " . PHP_EOL;
//for ($i=0; $i< $n; $i++){
	//echo "ALTURA: " . $listaObstaculosAmpliada[$i]['altura']. PHP_EOL;
	//echo "ANGULO: " . $listaObstaculosAmpliada[$i]['angulo']. PHP_EOL;
	//echo "COB? : " . $listaObstaculosAmpliada[$i]['estePtoTieneCobertura']. PHP_EOL;
//}

//for ($i=0; $i<5; $i++){
	//echo "ALTURA: " . $radar['listaAzimuths'][0][$i]['altura']. PHP_EOL;
	//echo "ANGULO: " .$radar['listaAzimuths'][0][$i]['angulo']. PHP_EOL;
	//echo "ESTE PTO TIENE COB? : ".$radar['listaAzimuths'][0][$i]['estePtoTieneCobertura']. PHP_EOL;
//}
	
//$tiempo_inicio = microtime(true);
// funcion a medir
//$tiempo_fin = microtime(true);
//echo "Tiempo empleado: " . ($tiempo_fin - $tiempo_inicio);












