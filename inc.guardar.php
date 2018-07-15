<?php

CONST PERMISOS = 0775;

/**
 * Funcion para crear el fichero kml con los resultados del calculo de la cobertura del radar (CASO B: fl por debajo del radar)
 * 
 * @param array $listaContornos    (ENTRADA)
 * @param array $radar     (ENTRADA)
 * @param string $ruta     (ENTRADA)
 * @param int $fl          (ENTRADA) 
 * @param string $altMode  (ENTRADA)
 */
function creaKml2($listaContornos, $radar, $ruta, $fl, $altMode) {

    $rgb = "7d00ff00";
    $nivelVuelo = str_pad( (string)$fl, 3, "0", STR_PAD_LEFT );
    $radarWithFL = $radar['screening']['site']. "_FL" .  $nivelVuelo;
    if ( false ) {
        print "nivelVuelo: " . $nivelVuelo . PHP_EOL;
        print "radarWithFL: " . $radarWithFL . PHP_EOL;
        print "ruta: " . print_r($ruta, true) . PHP_EOL;
    }

    if ( 0 == count($listaContornos) ) {
        print "INFO No se genera fichero para FL $nivelVuelo porque no hay cobertura" . PHP_EOL;
        return false;
    }

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
	    <MultiGeometry>';

    foreach( $listaContornos as &$contorno ) {
        $cadenaOuter = "";

        foreach ( $contorno['polygon'] as &$p ) {
            // transforma las coordenadas del level 0 -> outer
            $cadenaOuter .= $p['lon'] . "," . $p['lat'] . "," . $p['alt'] . " ";
        }
        $contenido .= PHP_EOL .
'                <Polygon>
                <extrude>1</extrude>
		<altitudeMode>' . $altMode . '</altitudeMode>
		<outerBoundaryIs><LinearRing><coordinates>' . PHP_EOL . $cadenaOuter . PHP_EOL .
'                </coordinates></LinearRing></outerBoundaryIs>';

        foreach ( $contorno['inside'] as &$contorno_inside ) {
            $cadenaInner = "";
            foreach ($contorno_inside['polygon'] as &$p_inside) {
                 // transforma las coordenadas del level 1 -> inner
                 $cadenaInner .= $p_inside['lon'] . "," . $p_inside['lat'] . "," . $p_inside['alt'] . " ";
            }
            if ( "" != $cadenaInner ) {
                $contenido .= PHP_EOL .
'                <innerBoundaryIs><LinearRing><coordinates>' . PHP_EOL . $cadenaInner . PHP_EOL . 
'                </coordinates></LinearRing></innerBoundaryIs>';
            }
        }

        $contenido .= PHP_EOL .
'                </Polygon>';
    }

    $contenido .= PHP_EOL .
'            </MultiGeometry>
        </Placemark>
        </Document></kml>';

    writeKMZ($ruta[GUARDAR_POR_NIVEL] . $radarWithFL, $radarWithFL, $contenido);
    writeKMZ($ruta[GUARDAR_POR_RADAR] . $radarWithFL, $radarWithFL, $contenido);
    return;

}

function writeKMZ($fileName, $radarWithFL, $content) {

    print "INFO NOMBRE FICHERO: " . $fileName . ".kmz" . PHP_EOL;
    $zip = new ZipArchive();
    if ( false === $zip->open(
        $fileName . ".kmz",
        ZIPARCHIVE::CREATE | ZIPARCHIVE::OVERWRITE)
    ) {
        die("ERROR can't create " . $fileName . ".kmz" . PHP_EOL);
    }
    $zip->addFromString($radarWithFL . ".kml", $content);
    $zip->close();

    return;
}

/**
 * Funcion para crear una carpeta con los resultados para cada radar
 * 
 * @param string $ruta  (ENTRADA)
 * @return boolean, para comprobar si la funcion a tenido exito o no (SALIDA)
 */
function crearCarpetaResultados($ruta){
	
    if ( !is_dir( $ruta ) ) {
        clearstatcache();
        //$ruta = $ruta ."/". $radar['site'] . "/"; // /home/eval/berta/RESULTADOS/LE_VALLADOLID 
        if (mkdir($ruta, PERMISOS, true)) {
            return true;
        } else {
            return false;
        }
    }

    return true;
}

function storeMallaAsImage($malla, $nombre) {
    $im = imagecreatetruecolor(count($malla), count($malla[0]));
    if ( false === $im ) {
        die ("ERROR imagecreatetruecolor" . PHP_EOL);
    }
    imagealphablending($im, true); // setting alpha blending on
    imagesavealpha($im, true);
    if ( false === ($p = imagecolorallocate($im, 0, 148, 255)) ) {
        die ("ERROR imagecolorallocate" . PHP_EOL);
    }
    if ( false === ($bg = imagecolorallocatealpha($im, 0, 0, 0, 127)) ) {
        die ("ERROR imagecolorallocatealpha" . PHP_EOL);
    }
    if ( false === imagefill($im, 0, 0, $bg) ) {
        die ("ERROR imagefill" . PHP_EOL);
    }

    for($i = 0; $i < count($malla); $i++) {
        for($j = 0; $j < count($malla[$i]); $j++) {
            if ( $malla[$j][$i] != "0" ) {
                imagesetpixel($im, $i, $j, $p);
            }
        }
    }

    if ( false === imagepng( $im, $nombre . ".png" ) ) {
        die("ERROR imagepng ${nombre}.png" . PHP_EOL);
    }

    if ( false === imagedestroy( $im ) ) {
        die("ERROR imagedestroy" . PHP_EOL);
    }
    print "INFO NOMBRE FICHERO: ${nombre}.png" . PHP_EOL;

    return true;
}

/**
 * Función solo para comparar con la versión de MATLAB
 *
 */
function storeListaObstaculos($radar, $ruta, $nivelVuelo) {

    $obstaculosAzStr = "";
    foreach($radar['screening']['listaAzimuths'] as $az => $obstaculosAz) {
        //$obstaculosAzStr .= $az . ",";
        $obstaculosAzStr .= strtolower(roundE(
            $obstaculosAz[0]['angulo']*$radar['screening']['radioTerrestreAumentado']/MILLA_NAUTICA_EN_METROS
            )). ",";
        foreach($obstaculosAz as $arr) {
            $obstaculosAzStr .= strtolower(roundE(
                $arr['angulo']*$radar['screening']['radioTerrestreAumentado']/MILLA_NAUTICA_EN_METROS
                )). ",";
        }
        $obstaculosAzStr = substr($obstaculosAzStr, 0, -1) . "\r\n"; //PHP_EOL;
    }
    if ( false === file_put_contents($ruta.$radar['screening']['site'] . "_FL" . $nivelVuelo . ".txt", $obstaculosAzStr)) {
        die("ERROR file_put_contents " . $ruta.$radar['screening']['site'] . "_FL" . $nivelVuelo . ".txt" . PHP_EOL);
    }
    print "INFO NOMBRE FICHERO: " . $ruta.$radar['screening']['site'] . "_FL" . $nivelVuelo . ".txt" . PHP_EOL;
    return;
}

/**
 * Genera ficheros para comparar la lista de obstáculos en PHP con la de Matlab
 *
 */
function generateMatlabFiles($radar, $rutaResultados) {
    $rutaTerrenos = $rutaResultados . "Radares_Terrenos" . DIRECTORY_SEPARATOR;
    $rutaCoordenadas = $rutaResultados . "Radares_Coordenadas" . DIRECTORY_SEPARATOR;

    print "Generando fichero de Matlab para " .
        "[" . $radar['radar'] . "=>" . $radar['screening']['site'] . "]" . PHP_EOL;

    crearCarpetaResultados($rutaTerrenos);
    crearCarpetaResultados($rutaCoordenadas);

    if ( 0 == strlen($radar['screening_file']) )
        continue;

    @unlink($rutaTerrenos.$radar['screening_file']['site'].".txt");
    if ( false === copy($radar['screening_file'], $rutaTerrenos.$radar['screening']['site'] . ".txt") )
        die("ERROR: copiando " . $radar['screening_file'] . " a " . $rutaTerrenos.$radarOriginal['site'] . ".txt" .PHP_EOL);

    $coordenadas = $radar['screening']['site'] . "-Latitud=" . $radar['lat'] . ";\r\n" .
        $radar['screening']['site'] . "-Longitud=" . $radar['lon'] . ";\r\n" .
        $radar['screening']['site'] . "-Range=" . ($radar['range']/MILLA_NAUTICA_EN_METROS) . ";";

    @unlink($rutaCoordenadas.$radarOriginal['site'].".txt");
    if ( false === file_put_contents($rutaCoordenadas.$radar['screening']['site'] . ".txt", $coordenadas) )
        die("ERROR: escribiendo " . $rutaCoordenadas.$radar['screening']['site'] . ".txt" . PHP_EOL);

    print "INFO NOMBRE FICHERO: " . $rutaTerrenos.$radar['screening']['site'].".txt" . PHP_EOL;
    return true;
}

/**
 * Redondea estilo MATLAB, dejando 10 números después del punto decimal.
 *
 */
function roundE($n) {
    $val = round($n, 10, PHP_ROUND_HALF_UP);
    return $val;
}
