<?php

CONST PERMISOS = 0775;

/**
 * Funcion que da formato al array de coordenadas para poderlas escribir en el fihero (CASO A: fl por encima del radar)
 * 
 * @param array $coordenadasG, contiene las coordenadas geograficas
 * @return string
 */
function toString ($coordenadasG){
	$cadena = "";
	$size = count ($coordenadasG);

	for ($i = 0; $i < $size; $i++){
		$cadena .= implode("," , $coordenadasG[$i]). PHP_EOL; // une elementos de un array en un string
	}
	// cerramos el polígono, incluyendo de nuevo el primer punto de la lista
	$cadena .= implode(",", $coordenadasG[0]) . PHP_EOL; 

	return $cadena;
}


/**
 * Funcion que da formato al array de coordenadas para poderlas escribir en el fichero (CASO B)
 * 
 * @param array $isla (ENTRADA)
 * @return string (SALIDA)
 */
function toStringB ($isla){
	$cadena = "";

		for ($pto = 0; $pto < count($isla); $pto++){
			$cadena .= implode("," , $isla[$pto]). PHP_EOL; // une elementos de un array en un string
		}
	return $cadena;
}


/**
 * Funcion para crear el fichero kml con los resultados del calculo de la cobertura del radar (CASO A: fl por encima del radar)
 * 
 * @param array $coordenadasG   (ENTRADA)
 * @param array $radar          (ENTRADA)
 * @param string $ruta          (ENTRADA)
 * @param int $fl               (ENTRADA)
 * @param string $altMode       (ENTRADA)
 */
function crearKML ($coordenadasG, $radar, $ruta, $fl, $altMode){
	
	$cadena = ""; 
	$cadena = toString($coordenadasG);
	$rgb = "7d00ff00"; 
	$nivelVuelo = (string)$fl;
	$radarWithFL = $radar['site']."_FL" .  str_pad($nivelVuelo,3,"0", STR_PAD_LEFT);
	$nombreFich = $ruta . $radarWithFL . ".txt"; //  /home/eval/berta/RESULTADOS/LE_VALLADOLID/ LE_VALLADOLID.txt
	
	$contenido = "";
	$contenido = '<?xml version="1.0" encoding="UTF-8"?>'.
			'<kml xmlns="http://www.opengis.net/kml/2.2" xmlns:gx="http://www.google.com/kml/ext/2.2" xmlns:kml="http://www.opengis.net/kml/2.2" xmlns:atom="http://www.w3.org/2005/Atom">
		<Document>
			<name>' . $radarWithFL . '</name>'.
			'<Style id="transGreenPoly">
			 	 <LineStyle>
					<width>1.5</width>
				 </LineStyle>
			 	<PolyStyle>
					<color>' .$rgb . '</color>
				</PolyStyle>
		  	</Style>'.
	
			'<Placemark>
				<name>' .  $radarWithFL . '</name>'.
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
	
	
	
	echo "NOMBRE FICH: " . $nombreFich. PHP_EOL;
	$kml = fopen($nombreFich, 'w+'); 
	
	if (is_writable($nombreFich)){

		fwrite ($kml, $contenido);
		fclose($kml);
		if (rename ($nombreFich, $ruta . "/" . $radarWithFL . ".kml"))
			echo "KML GENERADO CORRECTAMENTE". PHP_EOL;
		else 
			echo "Error al cambiar la extension del fichero, por favor compruebe la carpeta resultados."  . PHP_EOL;
	}
	else
		echo "Error al intentar escribir en el fichero" . PHP_EOL;
	
	clearstatcache();
}

/**
 * Funcion para crear el fichero kml con los resultados del calculo de la cobertura del radar (CASO B: fl por debajo del radar)
 * 
 * @param array $listaC    (ENTRADA)
 * @param array $radar     (ENTRADA)
 * @param string $ruta     (ENTRADA)
 * @param int $fl          (ENTRADA) 
 * @param string $altMode  (ENTRADA)
 */
function crearKmlB($listaC, $radar, $ruta, $fl, $altMode){ 
 
	$rgb = "7d00ff00";
	$contenido = ""; $cadenaOuter = ""; $cadena = ""; $nombreFich = "" ; $cadenaInner = "";
	
	$numIslas = count($listaC);
	$cadenaOuter = toStringB($listaC[0]);
	$nivelVuelo = (string)$fl;
	$radarWithFL = $radar['site']."_FL" .  str_pad($nivelVuelo,3,"0", STR_PAD_LEFT);
	$nombreFich = $ruta . $radarWithFL . ".txt"; //  /home/eval/berta/RESULTADOS/LE_VALLADOLID/ LE_VALLADOLID.txt
	
	echo "NOMBRE FICH: " . $nombreFich. PHP_EOL;
	$kml = fopen($nombreFich, 'w+');
	
	$contenido = '<?xml version="1.0" encoding="UTF-8"?>'.
	'<kml xmlns="http://www.opengis.net/kml/2.2" xmlns:gx="http://www.google.com/kml/ext/2.2" xmlns:kml="http://www.opengis.net/kml/2.2" xmlns:atom="http://www.w3.org/2005/Atom">
	
	<Document>
			
	<name>' . $radarWithFL . '</name>
			
	<Style id="transGreenPoly">
		<LineStyle>
			<width>1.5</width>
		</LineStyle>
		<PolyStyle>
			<color>'.$rgb .'</color>
		</PolyStyle>
	</Style>
	
	<Placemark>
			
	<name>' .  $radarWithFL . '</name>
			
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

 	     for ($isla = 1; $isla < $numIslas; $isla++){
	
			$cadenaInner = toStringB($listaC[$isla]);
		
			$contenido2 = '<innerBoundaryIs>
			<LinearRing>
				<coordinates>' . $cadenaInner. '</coordinates>
			</LinearRing>
	 			</innerBoundaryIs>';
			
				fwrite ($kml, $contenido2);
 	    }// for  
 	    	$contenido3 = '</Polygon></MultiGeometry></Placemark></Document></kml>';
 	    	
			fwrite ($kml, $contenido3);
			if (rename ($nombreFich, $ruta . "/" . $radarWithFL . ".kml")){
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
 * Funcion para crear una carpeta con los resultados para cada radar
 * 
 * @param array $radar  (ENTRADA)
 * @param string $ruta  (ENTRADA)
 * @return boolean, para comprobar si la funcion a tenido exito o no (SALIDA)
 */
function crearCarpetaResultados($radar, $ruta){
	
	//$ruta = $ruta ."/". $radar['site'] . "/"; // /home/eval/berta/RESULTADOS/LE_VALLADOLID 
	
	if (mkdir($ruta, PERMISOS, true))
		return true;
	else
		return false;
}



