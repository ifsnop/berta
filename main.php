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

/* 
    $malla = array(
array(0,0,0,0,0,0),
array(0,1,0,0,0,0),
array(0,0,0,0,0,0),
array(0,0,0,0,0,0),
array(0,0,0,0,1,0),
array(0,0,0,0,0,0),

 );  */
  
/*    $malla = array(
  		array(0,0,0,0,0,0),
  		array(0,0,1,1,0,0),
  		array(0,1,1,1,1,0),
  		array(0,1,1,1,1,0),
  		array(0,0,1,1,0,0),
  		array(0,0,0,0,0,0),
  
  );
    */
  
/*   $malla = array(
  		array(0,0,0,0,0,0),
  		array(0,1,0,0,1,0),
  		array(0,0,1,1,0,0),
  		array(0,0,1,1,0,0),
  		array(0,1,0,0,1,0),
  		array(0,0,0,0,0,0),
  
  ); */



     $malla = array(
 array(1,1,0,0,0,0),
 array(1,1,0,0,0,0),
 array(0,0,0,0,0,0),
 array(0,0,0,0,0,0),
 array(0,0,0,0,1,1),
 array(0,0,0,0,1,1),

 );  
    
  
/*
      $malla = array(
		array(0,0,0,0,0,0,0,0,0,0),
		array(0,1,1,1,1,0,0,0,0,0),
		array(0,1,0,1,1,0,0,0,0,0),
		array(0,1,0,0,1,0,0,0,0,0),
		array(0,1,1,1,1,0,0,0,0,0),
		array(0,0,0,0,0,0,0,0,0,0),
		array(0,0,0,0,0,0,0,0,0,0),
		array(0,0,0,0,0,0,0,1,1,1),
		array(0,0,0,0,0,0,0,1,1,1),
		array(0,0,0,0,0,0,0,1,1,1),

); 
   
*/

calculosFLdebajoRadar($radar, $flm, $radioTerrestreAumentado); // mete la lista de obstaculos ampliada para cada azimut

//generacionMallado($radar, $radioTerrestreAumentado, $malla);


$mallaGrande = array();
$mallaGrande = mallaMarco($malla); // tiene un marco de ceros abrazando  a la malla binaria original


//$isla =marchingSquares($radar, $mallaGrande, $flm);
//print_r($isla);
//exit();

//echo "tamaño de la lista de contornos : " . $n. PHP_EOL;

determinaContornos($radar, $mallaGrande, $flm, $listaContornos);

//print_r($listaContornos);

//exit();

//calculaCoordenadasGeograficasB($radar, $flm, $coordenadas, $listaContornos); // calcula las coordenadas geograficas a partir de la lista de contornos

//print_r($listaContornos);
//exit();

//crearKmlB($listaContornos, $radar, $ruta, $fl, "Tierra");


/// SAND BOX ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
/*   $isla = array();
 $isla2 = array();
 
$isla[0] = array('fila' => 0, 'col' => 0);     		$isla2[0] = array('fila' => 4, 'col' => 4);
$isla[1] = array('fila' => 1, 'col' => 0);			$isla2[1] = array('fila' => 5, 'col' => 4);
$isla[2] = array('fila' => 2, 'col' => 0);    		$isla2[2] = array('fila' => 6, 'col' => 4);
$isla[3] = array('fila' => 3, 'col' => 0);    		$isla2[3] = array('fila' => 7, 'col' => 4);
$isla[4] = array('fila' => 3, 'col' => 1);    		$isla2[4] = array('fila' => 7, 'col' => 5);
$isla[5] = array('fila' => 3, 'col' => 2);   		$isla2[5] = array('fila' => 7, 'col' => 6);
$isla[6] = array('fila' => 3, 'col' => 3);   		$isla2[6] = array('fila' => 7, 'col' => 7);
$isla[7] = array('fila' => 2, 'col' => 3);    		$isla2[7] = array('fila' => 6, 'col' => 7);
$isla[8] = array('fila' => 1, 'col' => 3);    		$isla2[8] = array('fila' => 5, 'col' => 7);
$isla[9] = array('fila' => 0, 'col' => 3);    		$isla2[9] = array('fila' => 4, 'col' => 7);
$isla[10] = array('fila' => 0, 'col' => 2);   		$isla2[10] = array('fila' => 4, 'col' => 6);
$isla[11] = array('fila' => 0, 'col' => 1);   		$isla2[11] = array('fila' => 4, 'col' => 5);


$lista = array();
$lista[0] = $isla;
$lista[1] = $isla2;  */

  function puntoEnPoligono($x, $y, $isla){ // tiene que funcionar pasandole una lista con varias islas

	$dentro = false;
	
	// si la lista esta vacia, esto te da 0 y considera que todos los puntos estan FUERA
	//echo "COUNT(lista): " .count($lista). PHP_EOL;
	
	//for ($isla=0; $isla< count($lista); $isla++){ // necesitamos recorrer la lista para ver si el punto evaluado esta en alguna de las islas de la lista 
			//echo "ISLA: " .$isla. PHP_EOL;
				// Buscamos Xmin, Xmax, Ymin, Ymax
				$minX = buscaColMin($isla); // se le pasaba la isla
				//echo "Xmin: " . $minX. PHP_EOL;
				
				$minY = buscaFilaMin($isla);
				//echo "Ymin: " . $minY. PHP_EOL;
			
				$maxX = buscaColMax($isla);
				//echo "Xmax: " . $maxX. PHP_EOL;
			
				$maxY = BuscaFilaMax($isla);
				//echo "Ymax: " . $maxY. PHP_EOL;
			
				if ($x <= $minX || $x >= $maxX || $y <= $minY || $y >= $maxY)
					 return false;
					 //$dentro = false;
				
				//$inside = false;
				for ( $i = 0, $j = count($isla)-1 ; $i < count($isla); $j = $i++ ){
						if ( ( $isla[$i]['fila'] >= $y ) != ($isla[ $j ]['fila'] >= $y ) &&
								$x <= ( $isla[ $j ]['col'] - $isla[ $i ]['col'] ) * ( $y - $isla[ $i ]['fila'] ) /
								( $isla[ $j ]['fila'] - $isla[ $i ]['fila'] ) + $isla[ $i ]['col'] ){
									$dentro = !$dentro;	
									
						}
				}
				return $dentro;
	//}
	//return $dentro;
} 

/*  function IsPointInPolygon($x, $y, $isla){  // GOOOOOOOOOOOD 
	
	$minX = buscaColMin($isla);
	//echo "Xmin: " . $minX. PHP_EOL;
	$minY = buscaFilaMin($isla);
	//echo "Ymin: " . $minY. PHP_EOL;
		
	$maxX = buscaColMax($isla);
	//echo "Xmax: " . $maxX. PHP_EOL;
		
	$maxY = BuscaFilaMax($isla);
	//echo "Ymax: " . $maxY. PHP_EOL;

	if ( $x <= $minX || $x >= $maxX || $y <= $minY || $y >= $maxY ){
		return false;
	}

	$inside = false;
	for ( $i = 0, $j = count($isla)-1 ; $i < count($isla); $j = $i++ )
	{
		if ( ( $isla[$i]['fila'] >= $y ) != ( $isla[ $j ]['fila'] >= $y ) &&
				$x <= ( $isla[ $j ]['col'] - $isla[ $i ]['col'] ) * ( $y - $isla[ $i ]['fila'] ) /
			( $isla[ $j ]['fila'] - $isla[ $i ]['fila'] ) + $isla[ $i ]['col'] ){
			$inside = !$inside;
		}
	}
	return $inside;
}
 */

 // PRIMERA ISLA : 
/*  echo "PRIMERA ISLA" .PHP_EOL;

echo "(0,0)";
if (puntoEnPoligono(0,0, $lista)) // se le pasaba la isla 
	echo "DENTRO" . PHP_EOL;
else 
	echo "FUERA" . PHP_EOL;										

echo "(1,0)";
if (puntoEnPoligono(1,0, $lista))
 	echo "DENTRO" . PHP_EOL;
else
	echo "FUERA" . PHP_EOL;

echo "(2,0)";
if (puntoEnPoligono(2,0, $lista))
	echo "DENTRO" . PHP_EOL;
else
	echo "FUERA" . PHP_EOL;

echo "(3,0)";
if (puntoEnPoligono(3,0, $lista))
	echo "DENTRO" . PHP_EOL;
else
	echo "FUERA" . PHP_EOL;

	
echo "(3,1)";
if (puntoEnPoligono(3,1, $lista))
	echo "DENTRO" . PHP_EOL;
else
	echo "FUERA" . PHP_EOL;

	
echo "(3,2)";
if (puntoEnPoligono(3,2, $lista))
	echo "DENTRO" . PHP_EOL;
else
	echo "FUERA" . PHP_EOL;

	
echo "(3,3)";
if (puntoEnPoligono(3,3, $lista))
	echo "DENTRO" . PHP_EOL;
else
	echo "FUERA" . PHP_EOL;

echo "(2,3)";
if (puntoEnPoligono(2,3, $lista))
	echo "DENTRO" . PHP_EOL;
else
	echo "FUERA" . PHP_EOL;

	
echo "(1,3)";
if (puntoEnPoligono(1,3, $lista))
	echo "DENTRO" . PHP_EOL;
else
	echo "FUERA" . PHP_EOL;

	
echo "(0,3)";
if (puntoEnPoligono(0,3, $lista))
	echo "DENTRO" . PHP_EOL;
else
	echo "FUERA" . PHP_EOL;

	
echo "(0,2)";
if (puntoEnPoligono(0,2, $lista))
	echo "DENTRO" . PHP_EOL;
else
	echo "FUERA" . PHP_EOL;

	
echo "(0,1)";
if (puntoEnPoligono(0,1, $lista))
	echo "DENTRO" . PHP_EOL;
else
	echo "FUERA" . PHP_EOL;

echo PHP_EOL;

// DENTRO

echo "(1,1)";
if (puntoEnPoligono(1,1, $lista))
	echo "DENTRO" . PHP_EOL;
else
	echo "FUERA" . PHP_EOL;

echo "(2,1)";
if (puntoEnPoligono(2,1, $lista))
	echo "DENTRO" . PHP_EOL;
else
	echo "FUERA" . PHP_EOL;

echo "(2,2)";
if (puntoEnPoligono(2,2, $lista))
	echo "DENTRO" . PHP_EOL;
else
	echo "FUERA" . PHP_EOL;

	
echo "(1,2)";
if (puntoEnPoligono(1,2, $lista))
	echo "DENTRO" . PHP_EOL;
else
	echo "FUERA" . PHP_EOL;
 

echo PHP_EOL;
echo PHP_EOL;
echo PHP_EOL;

// SEGUNDA ISLA: 	
echo "SEGUNDA ISLA" . PHP_EOL;

// FUERA 		
echo "(4,4)";
if (puntoEnPoligono(4,4, $lista))
	echo "DENTRO" . PHP_EOL;
else
	echo "FUERA" . PHP_EOL;

 	
echo "(5,4)";
if (puntoEnPoligono(5,4, $lista))
	echo "DENTRO" . PHP_EOL;
else
	echo "FUERA" . PHP_EOL;

	
echo "(6,4)";
if (puntoEnPoligono(6,4, $lista))
	echo "DENTRO" . PHP_EOL;
else
	echo "FUERA" . PHP_EOL;

echo "(7,4)";
if (puntoEnPoligono(7,4, $lista))
	echo "DENTRO" . PHP_EOL;
else
	echo "FUERA" . PHP_EOL;

	
echo "(7,5)";
if (puntoEnPoligono(7,5, $lista))
	echo "DENTRO" . PHP_EOL;
else
	echo "FUERA" . PHP_EOL;

	
echo "(7,6)";
if (puntoEnPoligono(7,6, $lista))
	echo "DENTRO" . PHP_EOL;
else
	echo "FUERA" . PHP_EOL;

	
echo "(7,7)";
if (puntoEnPoligono(7,7, $lista))
	echo "DENTRO" . PHP_EOL;
else
	echo "FUERA" . PHP_EOL;

	
echo "(6,7)";
if (puntoEnPoligono(6,7, $lista))
	echo "DENTRO" . PHP_EOL;
else
	echo "FUERA" . PHP_EOL;

	
echo "(5,7)";
if (puntoEnPoligono(5,7, $lista))
	echo "DENTRO" . PHP_EOL;
else
	echo "FUERA" . PHP_EOL;

	
echo "(4,7)";
if (puntoEnPoligono(4,7, $lista))
	echo "DENTRO" . PHP_EOL;
else
	echo "FUERA" . PHP_EOL;

	
echo "(4,6)";
if (puntoEnPoligono(4,6, $lista))
	echo "DENTRO" . PHP_EOL;
else
	echo "FUERA" . PHP_EOL;

	
echo "(4,5)";
if (puntoEnPoligono(4,5, $lista))
	echo "DENTRO" . PHP_EOL;
else
	echo "FUERA" . PHP_EOL;

echo PHP_EOL;

// DENTRO
	
echo "(5,5)";
if (puntoEnPoligono(5,5, $lista))
	echo "DENTRO" . PHP_EOL;
else
	echo "FUERA" . PHP_EOL;

	
echo "(5,6)";
if (puntoEnPoligono(5,6, $lista))
	echo "DENTRO" . PHP_EOL;
else
	echo "FUERA" . PHP_EOL;

	
echo "(6,5)";
if (puntoEnPoligono(6,5, $lista))
	echo "DENTRO" . PHP_EOL;
else
	echo "FUERA" . PHP_EOL; 

	
echo "(6,6)";
if (puntoEnPoligono(6,6, $lista))
	echo "DENTRO" . PHP_EOL;
else
	echo "FUERA" . PHP_EOL;
 */
	
exit();   

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////



	
/* $mallaContornos = array();
// ponemos a cero toda la malla de contornos
for ($i=0; $i< count($mallaGrande); $i++){
	for($j=0; $j< count($mallaGrande); $j++){
		$mallaContornos[$i][$j] = 0;
	}
}
 */

//tratamientoMallado($malla, "MALLA.png"); // genera una imagen de la malla grande

//tratamientoMallado($mallaGrande, "MALLA_GRANDE.png");


//echo "tam lista contronos de 0: " . $n . PHP_EOL;
//print_r($listaContornos[0]);


// metemos a pinchos los puntos de la listaContornos en mallaContornos
/* for ($i=0; $i < count($listaContornos[0]); $i++){
		$x = $listaContornos[0][$i]['col'];
		$y = $listaContornos[0][$i]['fila'];
		$mallaContornos[$y][$x] = 1;
}
 */
//pintaMalla($mallaContornos);

//tratamientoMallado($mallaContornos, "MALLA_CONTORNOS.png"); // genera una imagen con los contornos

/* print PHP_EOL . "var cobertura=[" . PHP_EOL;
for ($i=0; $i<count($malla); $i++) {
	print "[" . implode(",", $malla[$i]) . "]," . PHP_EOL;
}
print "];";

 */





//pintaMalla($malla);
//pintaMalla($mallaGrande);
//print_r($malla);



//print_r($listaContornos);
//$v = array();
//$v = matrixToVector($malla);

//echo "tam M: " . count($malla). PHP_EOL;

//echo "tam V: " . count($v). PHP_EOL;

//exit();

//tratamientoMallado($malla, "HOLA_MUNDO.png"); // genera una imagen 

//contornos($malla, $mallaContornos); // genera una malla con los contornos 
/*$invertida = array();
for($i=0;$i<count($mallaContornos); $i++) {
    $invertida[$i] = array();
    for($j=0;$j<count($mallaContornos); $j++) {
        $invertida[$i][$j] = ($mallaContornos[$i][$j] == 1 ? 0 : 1);
    }
}
pintaMalla($invertida);
$invertidaContornos = array();
contornos($invertida, $invertidaContornos); // genera una malla con los contornos 
for ($j=0; $j<count($invertidaContornos); $j++) {
    $invertidaContornos[0][$j] = 0;
    $invertidaContornos[count($invertidaContornos)-1][$j] = 0;
    $invertidaContornos[$j][0] = 0;
    $invertidaContornos[$j][count($invertidaContornos)-1] = 0;
}

pintaMalla($invertidaContornos);
*/
//pintaMalla($mallaContornos);






//$listaC = array();
//$numIslas = cuentaIslas($mallaContornos, $listaC); // cuenta el numero de contornos que hay y nos da sus coordenadas
// $numIslas = cuentaIslas($invertidaContornos, $listaC); // cuenta el numero de contornos que hay y nos da sus coordenadas

//echo "numero de islas: " . $numIslas. PHP_EOL;
//echo "cuenta de puntos en cada isla" . PHP_EOL;

/* for($i=0;$i<count($listaContornos);$i++) {
    print $i . "] " . count($listaContornos[$i]) . PHP_EOL;
}
//$listaContornos[0] = ordenaLista($listaContornos[0]);

$alfabeto = "0123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjklmnpqrstuvwxyz";

$mapa = array();
for($i=0;$i<count($mallaGrande);$i++) {
    $mapa[$i] = array();
    for($j=0;$j<count($mallaGrande);$j++) {
        $mapa[$i][$j] = " ";
    }
}
for($i=0; $i<count($listaContornos); $i++) {
    //print $listaC[0][$i]['fila'] . " " . $listaC[0][$i]['col'] . " " . $alfabeto[$i%strlen($alfabeto)] . PHP_EOL;
    $mapa[$listaContornos[$i]['y']][$listaContornos[$i]['x']] = $alfabeto[$i%strlen($alfabeto)];
}

pintaMallaAlfabeto($mapa);

 

//tratamientoMallado(, "CONTORNOS_OUTER.png");


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










/* function ordenaLista($l) {
    $n = array($l[0]);
    
    for($i = 1; $i < count($l); $i++) {
        if ( isset($l[$i]['visitado']) ) {
            continue;
        }
        $difFila = $n[count($n)-1]['fila'] - $l[$i]['fila'];
        $difCol = $n[count($n)-1]['col'] - $l[$i]['col'];
        $dist = sqrt($difFila*$difFila+$difCol*$difCol);
        print "dist1:" . round($dist,1) . " pendientes:" . cuentaPendientes($l) . " nuevos:" . count($n) . PHP_EOL;
        if ( $dist < 3.7 )  {
            $n[] = $l[$i];
            $l[$i]['visitado'] = true;
        } else {
            $minDist = 9999; $minIndex = -1;
            for($j = 1;$j < count($l); $j++) {
                if ( !isset($l[$j]['visitado']) ) {
                    $difFila = $n[count($n)-1]['fila'] - $l[$j]['fila'];
                    $difCol = $n[count($n)-1]['col'] - $l[$j]['col'];
                    $dist = sqrt($difFila*$difFila+$difCol*$difCol);
                    if ( $dist < $minDist ) {
                        $minDist = $dist;
                        $minIndex = $j;
                    }
                }
            }
            if ( $minDist < 3.7 ) {
                print "dist2:" . round($minDist,1) . " pendientes:" . cuentaPendientes($l) . " nuevos:" . count($n) . PHP_EOL;
                $n[] = $l[$minIndex];
                $l[$minIndex]['visitado'] = true;
                $i = $minIndex;
                $encontrado = true;
            }
        }
        print ">" . $i . PHP_EOL;
    }
    for($i=0;$i<count($n);$i++) {
        if (isset($n[$i]['visitado'])) {
            unset($n[$i]['visitado']);
        }
    }   
    return $n;

}

function cuentaPendientes($l) {
    $j=0;
    for($i=0;$i<count($l);$i++) {
    
        if ( !isset($l[$i]['visitado']) ) {
            $j++;
        }
    }

    return $j . "/" . count($l);
}
 */







////////////////////////////////////////// RECORTE DE IMAGENES /////////////////////////////////////////

/*   $img1 = new Imagick('HOLA_MUNDO.png');
 $img2 = new Imagick('MUNDO_CONTORNOS.png');

 $diff12 = $img1->compareImageChannels($img2,
 Imagick::CHANNEL_ALL, Imagick::METRIC_MEANABSOLUTEERROR);

 echo "EY OH " .PHP_EOL;
 print_r($diff12); */
 
//////////////////////////////////////////////////////////////////////////////////////////