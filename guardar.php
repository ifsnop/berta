<?php

CONST PERMISOS = 0700;

/**
 * Funcion que da formato al array de coordenadas para poderlas escribir en el fichero
 * @param array $resultado, contiene las coordenadas Geograficas 
 * @return string $cadena
 */
function toString ($coordenadasG){
	$cadena = "";
	$size = count ($coordenadasG);

	for ($i =0; $i< $size; $i++){
		$cadena = $cadena . implode("," , $coordenadasG[$i]). PHP_EOL;
	}

	return $cadena;
}

/**
 * Funcion para crear el fichero Kml con los resultados del calculo de la cobertura del radar 
 * @param string $coordenadasG
 * @param array $radar
 * @param string $ruta
 * @param integer $fl
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
	


function crearKmlB($listaC, $radar, $ruta, $fl, $altMode){ 
 
	$rgb = "7d00ff00";
	$contenido = ""; $cadenaOuter = ""; $cadena = ""; $nombreFich = "" ; $cadenaInner = "";
	
	$numIslas = count($listaC);
	$cadenaOuter = toString($listaC[0]); // la primera isla sera siempre el Outer Boundry (En este caso tenemos  6000 ptos aproximadamente)
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

 	     for ($isla = 1; $isla < $numIslas; $isla++){
	
			$cadenaInner = toString($listaC[$isla]);
		
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
 * @param array $radar 
 */
function crearCarpetaResultados($radar, $ruta, $resultado){
	
	//$ruta = $ruta ."/". $radar['site'] . "/"; // /home/eval/berta/RESULTADOS/LE_VALLADOLID 
	
	if (mkdir($ruta, PERMISOS, true))
		
		return true;
		
	else
		return false;
}



