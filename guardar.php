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
 * @param boolean $ordenarPorRadar Si true, una carpeta por radar, si false una carpeta por nivel de vuelo (ENTRADA)
 */
function crearKML ($coordenadasG, $radar, $ruta, $fl, $altMode, $ordenarPorRadar){
	
	$cadena = ""; 
	$cadena = toString($coordenadasG);
	$rgb = "7d00ff00"; 
	$nivelVuelo = str_pad((string)$fl,3,"0", STR_PAD_LEFT);
	$radarWithFL = $radar['site']."_FL" .  $nivelVuelo;
	$fileName = $ruta . $radarWithFL; //  /home/eval/berta/RESULTADOS/LE_VALLADOLID/ LE_VALLADOLID.txt
	
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
					<altitudeMode>' .$altMode. '</altitudeMode>
					<outerBoundaryIs>
						<LinearRing>
							<coordinates>'. $cadena. PHP_EOL. '</coordinates>
						</LinearRing>
					</outerBoundaryIs>
				</Polygon>
			</Placemark>
 		</Document>
	</kml>';

	print "NOMBRE FICHERO: " . $fileName . ".kmz" . PHP_EOL;
	$zip = new ZipArchive();
        if ( false === $zip->open(
            $fileName . ".kmz",
            ZIPARCHIVE::CREATE | ZIPARCHIVE::OVERWRITE)
        ) {
            print "ERROR can't create " . $fileName . ".kmz" . PHP_EOL; exit;
        }
        $zip->addFromString($radarWithFL . ".kml", $contenido);
        $zip->close();
}

/**
 * Funcion para crear el fichero kml con los resultados del calculo de la cobertura del radar (CASO B: fl por debajo del radar)
 * 
 * @param array $listaC    (ENTRADA)
 * @param array $radar     (ENTRADA)
 * @param string $ruta     (ENTRADA)
 * @param int $fl          (ENTRADA) 
 * @param string $altMode  (ENTRADA)
 * @param boolean $ordenarPorRadar Si true, una carpeta por radar, si false una carpeta por nivel de vuelo (ENTRADA)
 */
function crearKmlB($listaC, $radar, $ruta, $fl, $altMode, $ordenarPorRadar){

    $rgb = "7d00ff00";
    $contenido = ""; $cadenaOuter = ""; $cadena = ""; $cadenaInner = "";

    $numIslas = count($listaC);
    $nivelVuelo = str_pad((string)$fl,3,"0", STR_PAD_LEFT);
    $radarWithFL = $radar['site']."_FL" .  $nivelVuelo;
    $fileName = $ruta . $radarWithFL;
    if ( count($listaC) == 0 ) {
        print "No se genera fichero para FL $nivelVuelo porque no hay cobertura" . PHP_EOL;
        return false;
    }
    $cadenaOuter = toStringB($listaC[0]);

    $contenido = '<?xml version="1.0" encoding="UTF-8"?>
	<kml xmlns="http://www.opengis.net/kml/2.2" xmlns:gx="http://www.google.com/kml/ext/2.2" xmlns:kml="http://www.opengis.net/kml/2.2" xmlns:atom="http://www.w3.org/2005/Atom">
	<Document>
	<name>' . $radarWithFL . '</name>
	<Style id="transGreenPoly">
            <LineStyle><width>1.5</width></LineStyle>
            <PolyStyle><color>' . $rgb . '</color></PolyStyle>
	</Style>
	<Placemark>
            <name>' .  $radarWithFL . '</name>
	    <styleUrl>#transGreenPoly</styleUrl>
	    <MultiGeometry>
		<Polygon>
                <extrude>1</extrude>
		<altitudeMode>' . $altMode . '</altitudeMode>
		<outerBoundaryIs>
		    <LinearRing><coordinates>'. $cadenaOuter . '</coordinates></LinearRing>
	        </outerBoundaryIs>';

    // el resto de islas son interiores siempre
    for ($isla = 1; $isla < $numIslas; $isla++) {
	$cadenaInner = toStringB($listaC[$isla]);
	$contenido .= '
	        <innerBoundaryIs>
		    <LinearRing><coordinates>' . $cadenaInner. '</coordinates></LinearRing>
                </innerBoundaryIs>';
    }// for
    $contenido .= '
                </Polygon>
            </MultiGeometry>
        </Placemark>
        </Document></kml>';


    print "NOMBRE FICHERO: " . $fileName . ".kmz" . PHP_EOL;
    $zip = new ZipArchive();
    if ( false === $zip->open(
        $fileName . ".kmz",
        ZIPARCHIVE::CREATE | ZIPARCHIVE::OVERWRITE)
    ) {
        print "ERROR can't create " . $fileName . ".kmz" . PHP_EOL; exit;
    }
    $zip->addFromString($radarWithFL . ".kml", $contenido);
    $zip->close();
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



