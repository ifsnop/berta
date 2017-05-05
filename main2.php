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
	$listaC = array();
	
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
				    
				    // DISTINCION DE CASOS 
					if ($flm >= $hA){
						calculosFLencimaRadar($radar, $flm, $radioTerrestreAumentado, $angulosApantallamiento, $distanciasCobertura);
						calculaCoordenadasGeograficas($radar, $coordenadas, $distanciasCobertura, $flm, $coordenadasGeograficas);
					}		
					else{
					    $islas = 0;
						calculosFLdebajoRadar($radar, $flm, $radioTerrestreAumentado);
						generacionMallado($radar, $radioTerrestreAumentado, $malla);
						contornos($malla, $mallaContornos);
						$islas = cuentaIslas($mallaContornos, $listaC);
						calculaCoordenadasGeograficasB($islas, $listaC, &$coordenadasGeograficas);
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
$coordenadas = cargarDatosCoordenadas($infoCoral, "Begas"); // "Begas", "Valladolid"
//print_r($coordenadas);
// cargamos en memoria la informacion de terreno del radar con el que vamos a trabajar
$radar = cargarDatosTerreno('/home/eval/berta/le_begas.scr', $radioTerrestreAumentado); 

// ('/home/eval/berta/le_begasPRUEBA.scr', $radioTerrestreAumentado);
// ('/home/eval/berta/le_valladolidPRUEBA.scr', $radioTerrestreAumentado);


///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

/*    $mallaContornos = array(
 array(0,0,0,1,1,1),
 array(1,1,0,0,1,0),
 array(1,0,1,1,0,1),
 array(1,0,0,0,0,1),
 array(1,0,0,0,0,1),
 array(1,1,1,1,1,1),
 );
 */


calculosFLdebajoRadar($radar, $flm, $radioTerrestreAumentado); // mete la lista de obstaculos ampliada para cada azimut
//print_r($radar['listaAzimuths'][135]);
generacionMallado($radar, $radioTerrestreAumentado, $malla);
for($i=0;$i<count($malla);$i++) {
	for($j=0;$j<count($malla);$j++) {
		print $malla[$i][$j];
	}
	print PHP_EOL;
}
print PHP_EOL;
//print_r($malla);
tratamientoMallado($malla, "HOLA_MUNDO.png"); // genera una imagen 
contornos($malla, $mallaContornos); // genera una malla con los contornos 
for($i=0;$i<count($mallaContornos);$i++) {
	for($j=0;$j<count($mallaContornos);$j++) {
		print $mallaContornos[$i][$j];
	}
	print PHP_EOL;
}
print PHP_EOL;


//tratamientoMallado($mallaContornos, "MUNDO_CONTORNOS.png"); // HASTA AQUI TODO GOOD  Genera la imagen de los contornos
$listaC = array();
$numIslas = cuentaIslas($mallaContornos,$listaC); // cuenta el numero de contornos que hay y nos da sus coordenadas

echo "numero de islas: " . $numIslas. PHP_EOL;
print_r($listaC);

//tratamientoMallado(, "CONTORNOS_OUTER.png");
calculaCoordenadasGeograficasB($radar, $numIslas, $flm, $coordenadas, $listaC); // calcula las coordenadas geograficas a partir de la lista de contornos
//print_r($listaC);
crearKmlB($listaC, $radar, $ruta, $fl, "Tierra");


/* 
$listaC = array();
$listaC[0][0]['fila'] = 439;
$listaC[0][0]['col'] = 439;
$listaC[0][0]['altura'] = 100;

$listaC[0][1]['fila'] = 439;
$listaC[0][1]['col'] = 438;
$listaC[0][1]['altura'] = 100;

$listaC[0][2]['fila'] = 438;
$listaC[0][2]['col'] = 438;
$listaC[0][2]['altura'] = 100;

$listaC[0][3]['fila'] = 438;
$listaC[0][3]['col'] = 439;
$listaC[0][3]['altura'] = 100;

 */


/* 

   $N = array(
 array(1,0,0,0,0,0),
 array(0,0,0,1,1,0),
 array(1,1,1,1,1,0),
 array(0,0,0,0,0,0),
 array(1,1,0,0,0,1),
 array(0,1,0,0,1,0),
 );

tratamientoMallado($N, "Cero.png");

 */









