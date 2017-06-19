<?php

CONST PERMISOS = 0700;

/**
 * Funcion que da formato al array de coordenadas para poderlas escribir en el fichero
 * 
 * ENTRADA: array $resultado, contiene las coordenadas Geograficas 
 * SALIDA: string $cadena
 */
function toString ($coordenadasG){
	$cadena = "";
	$size = count ($coordenadasG);

	for ($i =0; $i< $size; $i++){
		$cadena .= implode("," , $coordenadasG[$i]). PHP_EOL; // une elementos de un array en un string
	}
	// cerramos el polígono, incluyendo de nuevo el primer punto de la lista
	$cadena .= implode(",", $coordenadasG[0]) . PHP_EOL; 

	return $cadena;
}

//print_r($coordenadasG);


/**
 * Funcion que da formato al array de coordenadas para poderlas escribir en el fichero
 * 
 * ENTRADA: array $isla
 * SALIDA string $cadena
  */
function toStringB ($isla){
	$cadena = "";

	//for ($isla =0; $isla< count($listaC); $i++){ // recorremos el array que contiene las coordenadas geograficas calculadas
		for ($pto=0; $pto< count($isla); $pto++){
			$cadena .= implode("," , $isla[$pto]). PHP_EOL; // une elementos de un array en un string
		}
		
	//}
	// cerramos el polígono, incluyendo de nuevo el primer punto de la lista
	//$cadena .= implode(",", $listaC[0][0]) . PHP_EOL; // isla 0 : punto 0 

	return $cadena;
}


/**
 * Funcion para crear el fichero Kml con los resultados del calculo de la cobertura del radar (CASO A: fl por encima del radar)
 *  
 * ENTRADA: string $coordenadasG
 * ENTRADA: array $radar
 * ENTRADA: string $ruta
 * ENTRADA integer $fl
 */
function crearKML ($coordenadasG, $radar, $ruta, $fl, $altMode){
	
	$cadena = ""; 
	$cadena = toString($coordenadasG);
	$rgb = "7d00ff00"; 
	
	$contenido = "";
	$contenido = '<?xml version="1.0" encoding="UTF-8"?>'.
			'<kml xmlns="http://www.opengis.net/kml/2.2" xmlns:gx="http://www.google.com/kml/ext/2.2" xmlns:kml="http://www.opengis.net/kml/2.2" xmlns:atom="http://www.w3.org/2005/Atom">
		<Document>
			<name>' . $radar['site'] . '</name>'.
				// AQUI HAY QUE METER LA CONFIGURACION CON LA QUE SE HA GENERADO !!!!! (comentario)
			'<Style id="transGreenPoly">
			 	 <LineStyle>
					<width>1.5</width>
				 </LineStyle>
			 	<PolyStyle>
					<color>' .$rgb . '</color>
				</PolyStyle>
		  	</Style>'.
	
			'<Placemark>
				<name>' .  $radar['site'] . '</name>'.
					'<styleUrl>#transGreenPoly</styleUrl>
				<Polygon>
					<extrude>1</extrude>
					<altitudeMode>' .$altMode. ' </altitudeMode>
					<outerBoundaryIs>
						<LinearRing>
							<coordinates>'. $cadena. PHP_EOL. '</coordinates>
						</LinearRing>
					</outerBoundaryIs>
				</Polygon>
			</Placemark>
 		</Document>
	</kml>';
	
	
	$nombreFich = "" ; 
	$nivelVuelo = (string)$fl;
	$nombreFich = $ruta. $radar['site'] ."_FL_" . $nivelVuelo . ".txt"; //  /home/eval/berta/RESULTADOS/LE_VALLADOLID/ LE_VALLADOLID.txt
	
	echo "NOMBRE FICH: " . $nombreFich. PHP_EOL;
	$kml = fopen($nombreFich, 'w+'); 
	
	if (is_writable($nombreFich)){

		fwrite ($kml, $contenido);
		fclose($kml);
		if (rename ($nombreFich, $ruta."/".$radar['site']."_FL_" . $nivelVuelo . ".kml"))
			echo "KML GENERADO CORRECTAMENTE". PHP_EOL;
		else 
			echo "Error al cambiar la extension del fichero, por favor compruebe la carpeta resultados."  . PHP_EOL;
		
	}
	else
		echo "Error al intentar escribir en el fichero" . PHP_EOL;
	
	clearstatcache();
}
	

/**
 * Funcion para crear el fichero Kml con los resultados del calculo de la cobertura del radar (CASO B: fl por debajo del radar)

 * ENTRADA: array $listaC
 * ENTRADA: array $radar
 * ENTRADA: string $ruta
 * ENTRADA: int $fl
 * ENTRADA: string $altMode
 */
function crearKmlB($listaC, $radar, $ruta, $fl, $altMode){ 
 
	$rgb = "7d00ff00";
	$contenido = ""; $cadenaOuter = ""; $cadena = ""; $nombreFich = "" ; $cadenaInner = "";
	
	$numIslas = count($listaC);
	//echo "numISlas: " . $numIslas. PHP_EOL;
	$cadenaOuter = toStringB($listaC[0]); // la primera isla sera siempre el Outer Boundry (En este caso tenemos  6000 ptos aproximadamente) ($listaC[0])
	//echo $cadenaOuter. PHP_EOL;
	$nivelVuelo = (string)$fl;
	$nombreFich = $ruta. $radar['site'] ."_FL_" . $nivelVuelo . ".txt"; //  /home/eval/berta/RESULTADOS/LE_VALLADOLID/ LE_VALLADOLID.txt
	
	echo "NOMBRE FICH: " . $nombreFich. PHP_EOL;
	$kml = fopen($nombreFich, 'w+');
	
	$contenido = '<?xml version="1.0" encoding="UTF-8"?>'.
	'<kml xmlns="http://www.opengis.net/kml/2.2" xmlns:gx="http://www.google.com/kml/ext/2.2" xmlns:kml="http://www.opengis.net/kml/2.2" xmlns:atom="http://www.w3.org/2005/Atom">
	
	<Document>
			
	<name>' . $radar['site'] . '</name>
			
	<Style id="transGreenPoly">
		<LineStyle>
			<width>1.5</width>
		</LineStyle>
		<PolyStyle>
			<color>'.$rgb .'</color>
		</PolyStyle>
	</Style>
	
	<Placemark>
			
	<name>' .  $radar['site'] . '</name>
			
	<styleUrl>#transGreenPoly</styleUrl>
		<MultiGeometry>
			<Polygon>
				<extrude>1</extrude>
					<altitudeMode>' . $altMode . '</altitudeMode>'. 
					'<outerBoundaryIs>
		<LinearRing>
			<coordinates>'. $cadenaOuter . '</coordinates>
		</LinearRing>
	 </outerBoundaryIs>' ;
	
	
	if (is_writable($nombreFich)){
	
		fwrite ($kml, $contenido);

 	     for ($isla = 1; $isla <$numIslas; $isla++){ // ($isla = 1; $isla < $numIslas; $isla++)
	
			$cadenaInner = toStringB($listaC[$isla]); // ($listaC)
		
			$contenido2 = '<innerBoundaryIs>
			<LinearRing>
				<coordinates>' . $cadenaInner. '</coordinates>
			</LinearRing>
	 			</innerBoundaryIs>';
			
				fwrite ($kml, $contenido2);
 	    }// for  
 	    	$contenido3 = '</Polygon></MultiGeometry></Placemark></Document></kml>';
 	    	
			fwrite ($kml, $contenido3);
			if (rename ($nombreFich, $ruta."/".$radar['site']."_FL_" . $nivelVuelo . ".kml")){
				echo "KML GENERADO CORRECTAMENTE". PHP_EOL;
			}
			else{
				echo "Error al cambiar la extension del fichero, por favor compruebe la carpeta resultados."  . PHP_EOL;			
			}
	}
	else{
		echo "Error al intentar escribir en el fichero" . PHP_EOL;
	}
	
fclose($kml);
clearstatcache();

}

/**
 * Funcion que crea una carpeta con los resultados para cada radar 
 * 
 * ENTRADA: array $radar
 * ENTRADA: string $ruta
 * SALIDA:  boolean, para comprobar si el la función ha tenido o no exito
 */
function crearCarpetaResultados($radar, $ruta){
	
	//$ruta = $ruta ."/". $radar['site'] . "/"; // /home/eval/berta/RESULTADOS/LE_VALLADOLID 
	
	if (mkdir($ruta, PERMISOS, true))
		
		return true;
		
	else
		return false;
}



