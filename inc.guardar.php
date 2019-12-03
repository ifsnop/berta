<?php

CONST PERMISOS = 0775;

/**
 * Funcion para crear el fichero kml con los resultados del calculo de la cobertura del radar (CASO B: fl por debajo del radar)
 *
 * @param array $listaContornos (ENTRADA)
 * @param array|string $radarName Radares que se han utilizado para esta cobertura (ENTRADA)
 * @param string $ruta Path donde guardar el fichero generado (ENTRADA)
 * @param int $fl Nivel de vuelo (ENTRADA)
 * @param string $altMode Si el KML está pegado al suelo o la altura es relativa/absoluta (ENTRADA)
 * @param string|array $appendToFilename Información a añadir al final del nombre del fichero (ENTRADA)
 * @return bool
 */
function creaKml2($listaContornos, $radarName, $ruta, $fl, $altMode, $appendToFilename="", $coverageLevel = 'mono', $epsilon = false) {

    $nivelVuelo = str_pad( (string)$fl, 3, "0", STR_PAD_LEFT );

    switch ( $coverageLevel ) { 
        case "unica": $rgb = "7d00ff00"; break;         // igual que mono
        case "mono": $rgb = "7d00ff00"; break;
        // case "mono": $rgb = "e6ff9724"; break;          // Rascal
        case "doble": $rgb = "7dff0000"; break;
        // case "doble": $rgb = "e63559a5"; break;         // Rascal
        case "triple": $rgb = "7dffff00"; break;
        // case "triple": $rgb = "e69977de"; break;        // Rascal
        case "cuadruple": $rgb = "7d0000ff"; break;
        // case "cuadruple": $rgb = "e67bf600"; break;     // Rascal
        case "quintuple": $rgb = "7dff00ff"; break;
        case "sextuple": $rgb = "7d00ffff"; break;
    }

    if ( is_array($appendToFilename) ) {
        $appendToFilename = "-" . implode("_", $appendToFilename);
    }

    if ( is_array($radarName) ) {
        $radarWithFL = implode("_", $radarName) . "-" .
            $coverageLevel . "-FL" .  $nivelVuelo . $appendToFilename;
    } else {
        $radarWithFL = $radarName . "-FL" .  $nivelVuelo . $appendToFilename;
    }

    if ( false ) {
        print "nivelVuelo: " . $nivelVuelo . PHP_EOL;
        print "radarWithFL: " . $radarWithFL . PHP_EOL;
        print "ruta: " . print_r($ruta, true) . PHP_EOL;
    }

    if ( 0 == count($listaContornos) ) {
        print "INFO No se genera fichero para FL $nivelVuelo porque no hay cobertura" . PHP_EOL;
        return false;
    }

    // vamos a transformar los polígonos que pueden venir en lat/lon, fila/col o 0/1 en 0/1/2.
    // porque sino es un lio.
    $group = array(); $vextex = array(); // estadísticas
    foreach( $listaContornos as &$contorno ) {
        $vertexCount = 0; // estadísticas
        $polygon = array();
        foreach ( $contorno['polygon'] as &$p ) {
            if ( isset($p['lat']) && isset($p['lon']) && isset($p['alt']) ) {
                $polygon[] = array($p['lat'], $p['lon'], $p['alt']);
            } elseif ( isset($p['fila']) && isset($p['col']) ) {
                $polygon[] = array($p['col'], $p['fila'], $fl*100*FEET_TO_METERS);
            } elseif ( isset($p[0]) && isset($p[1]) ) {
                $polygon[] = array($p[0], $p[1], $fl*100*FEET_TO_METERS);
            } else {
                die("ERROR, formato de punto incorrecto: " . print_r($p, true) . PHP_EOL);
            }
            $vertexCount++; // estadísticas
        }

        $inside = array(); $vertexCountInside = array();
        foreach ( $contorno['inside'] as &$contorno_inside ) {
            $polygon_inside = array();
            $currentCountInside = 0;
            foreach ($contorno_inside['polygon'] as &$p_inside) {
                if ( isset($p_inside['lat']) && isset($p_inside['lon']) && isset($p_inside['alt']) ) {
                    $polygon_inside[] = array($p_inside['lat'], $p_inside['lon'], $p_inside['alt']);
                } elseif ( isset($p_inside['fila']) && isset($p_inside['col']) ) {
                    $polygon_inside[] = array($p_inside['col'], $p_inside['fila'], $fl*100*FEET_TO_METERS);
                } elseif ( isset($p_inside['fila']) && isset($p_inside['col']) ) {
                    $polygon_inside[] = array($p_inside[0], $p_inside[1], $fl*100*FEET_TO_METERS);
                } else {
                    die("ERROR, formato de punto incorrecto: " . print_r($p, true) . PHP_EOL);
                }
                $currentCountInside++; // estadísticas
            }
            $inside[] = array('polygon' => $polygon_inside);
            $vertextCountInside[] = $currentCountInside; // estadísticas
        }
        $group[] = array('polygon' => $polygon, 'inside' => $inside);
        if ( 0 == count( $vertexCountInside ) ) $vertexCountInside = false;
        $vertex[] = array('polygon' => $vertexCount, 'inside' => $vertexCountInside);
    }
    // $group tiene la geometría necesaria para pintar todo, en el formato
    // 0=>lat, 1=>lon, 2=>height
    // FIN
    $kml = fromPolygons2KML($group, $radarWithFL, $rgb, $altMode);

    foreach($ruta as $val) { // GUARDAR_POR_NIVEL y GUARDAR_POR_RADAR o el que sea
        writeKMZ($val . $radarWithFL/* . $appendToFilename*/, $radarWithFL, $kml);
    }
    return true;
}

function writeKMZ($fileName, $radarWithFL, $content) {

    print "INFO guardando fichero: " . $fileName . ".kmz" . PHP_EOL;
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
function crearCarpetaResultados($ruta) {
	
    if ( !is_dir( $ruta ) ) {
        //clearstatcache();
        //$ruta = $ruta ."/". $radar['site'] . "/"; // /home/eval/berta/RESULTADOS/LE_VALLADOLID 
        if (mkdir($ruta, PERMISOS, true)) {
            return true;
        } else {
            return false;
        }
    }

    return true;
}

/*
 * función deprecada
 */
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
    print "INFO guardando fichero: ${nombre}.png" . PHP_EOL;

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

/*
 * Guarda una malla con coordenadas lat/lon en png. Si la malla es global,
 * colorea según el valor de la cobertura (doble, triple...)
 * @param array malla
 * @param string nombre fichero destino
 * @param array bounding dimensiones maximas lat/lon de la malla
 * @param bool debug verdadero para mostrar información de depuración
 * @return bool
 */
function storeMallaAsImage3($malla, $nombre, $bounding, $debug = false) {
    // ojo, el png tendrá el tamaño de la malla global, para poder solapar
    // todas las imágenes en una sola con un programa tipo Paint.NET
    // no se guardan las mallas individuales en su espacio de coordenadas
    // sino en el global.

    $lat_size = $bounding['lat_max'] - $bounding['lat_min'] + 1;
    $lon_size = $bounding['lon_max'] - $bounding['lon_min'] + 1;
    if ( $debug ) print "DEBUG x: " .  $lon_size . " " . "y: " . $lat_size . PHP_EOL;

    if ( false === ($im = imagecreatetruecolor($lon_size, $lat_size)) ) {
        print "ERROR imagecreatetruecolor" . PHP_EOL; exit(-1);
    }
    if ( false === imagealphablending($im, true) ) { // setting alpha blending on
        print "ERROR imagealphablending" . PHP_EOL; exit(-1);
    }
    if ( false === imagesavealpha($im, true) ) {
        print "ERROR imagesavealpha" . PHP_EOL; exit(-1);
    }
    if ( false === ($r = imagecolorallocate($im, 255, 0, 0)) ) {
        print "ERROR imagecolorallocate (0,255,148)" . PHP_EOL; exit(-1);
    }
    if ( false === ($g = imagecolorallocate($im, 0, 255, 0)) ) {
        print "ERROR imagecolorallocate (0,0,255)" . PHP_EOL; exit(-1);
    }
    if ( false === ($b = imagecolorallocate($im, 0, 0, 255)) ) {
        print "ERROR imagecolorallocate (255,148,0)" . PHP_EOL; exit(-1);
    }
    if ( false === ($f = imagecolorallocate($im, 255, 255, 0)) ) {
        print "ERROR imagecolorallocate (255,0,148)" . PHP_EOL; exit(-1);
    }
    if ( false === ($p = imagecolorallocate($im, 0, 255, 255)) ) {
        print "ERROR imagecolorallocate (0,148,255)" . PHP_EOL; exit(-1);
    }
    if ( false === ($q = imagecolorallocate($im, 255, 255, 255)) ) {
        print "ERROR imagecolorallocate (0,148,255)" . PHP_EOL; exit(-1);
    }
    if ( false === ($bg = imagecolorallocatealpha($im, 0, 0, 0, 127)) ) {
        print "ERROR imagecolorallocatealpha" . PHP_EOL; exit(-1);
    }
    if ( false === imagefill($im, 0, 0, $bg) ) {
        print "ERROR imagefill" . PHP_EOL; exit(-1);
    }

    $y = 0;
    for( $i = $bounding['lat_min']; $i <= $bounding['lat_max']; $i++ ) {
        $x = 0;
        for( $j = $bounding['lon_min']; $j <= $bounding['lon_max']; $j++ ) {
            if ( $x > $lon_size ) die ("x>lon_size");
            if ( $y > $lat_size ) die ("y>lat_size");
            // puede no existir porque la malla de cada radar solo cubre
            // la cobertura del radar en concreto
            if ( isset($malla[$i][$j]) ) {
                switch (countSetBits($malla[$i][$j])) {
                    case 0: imagesetpixel($im, $x, $lat_size - $y, $r); break;
                    case 1: imagesetpixel($im, $x, $lat_size - $y, $g); break;
                    case 2: imagesetpixel($im, $x, $lat_size - $y, $b); break;
                    case 3: imagesetpixel($im, $x, $lat_size - $y, $p); break;
                    default: imagesetpixel($im, $x, $lat_size - $y, $f); break; // 4 o más
                }
//            } else { // parece que corrompe la imagen
//                imagesetpixel($im, $x, $y, $q); break;
            }
            $x++;
        }
        $y++;
    }

    /*
    $y = 0;
    foreach( $malla as $i => $lons ) {
        $x = 0;
        foreach( $lons as $j => $value ) {
            switch (countSetBits($value)) {
                case 0: break;
                case 1: imagesetpixel($im, $x, $y, $p); break;
                case 2: imagesetpixel($im, $x, $y, $r); break;
                case 3: imagesetpixel($im, $x, $y, $b); break;
                default: imagesetpixel($im, $x, $y, $f); break; // 4 o más
            }
            $x++;        
        }
        $y++;
    }
    */
    if ( false === imagepng( $im, $nombre . ".png" ) ) {
        print "ERROR imagepng ${nombre}.png" . PHP_EOL; exit(-1);
    }
    if ( false === imagedestroy( $im ) ) {
        print "ERROR imagedestroy" . PHP_EOL; exit(-1);
    }

    print "INFO nombre fichero: ${nombre}.png" . PHP_EOL;
    return true;
}

/*
 * Guarda una imagen generada con las líneas de los contornos
 * @param array malla
 * @param string nombre fichero destino
 * @param array bounding dimensiones maximas lat/lon de la malla
 * @param bool debug verdadero para mostrar información de depuración
 * @return bool
 */
function storeContornosAsImage3($contornos, $nombre, $debug = false) {
    // ojo, el png tendrá el tamaño de la malla global, para poder solapar
    // todas las imágenes en una sola con un programa tipo Paint.NET
    // no se guardan las mallas individuales en su espacio de coordenadas
    // sino en el global.
    $colors = array();

    if ( false === ($im = imagecreatetruecolor(100,100)) ) {
        print "ERROR imagecreatetruecolor" . PHP_EOL; exit(-1);
    }
    if ( false === imagealphablending($im, true) ) { // setting alpha blending on
        print "ERROR imagealphablending" . PHP_EOL; exit(-1);
    }
    if ( false === imagesavealpha($im, true) ) {
        print "ERROR imagesavealpha" . PHP_EOL; exit(-1);
    }
    if ( false === ($colors[0] = imagecolorallocate($im, 255, 0, 0)) ) {
        print "ERROR imagecolorallocate (0,255,148)" . PHP_EOL; exit(-1);
    }
    if ( false === ($colors[1] = imagecolorallocate($im, 0, 255, 0)) ) {
        print "ERROR imagecolorallocate (0,0,255)" . PHP_EOL; exit(-1);
    }
    if ( false === ($colors[2] = imagecolorallocate($im, 0, 0, 255)) ) {
        print "ERROR imagecolorallocate (255,148,0)" . PHP_EOL; exit(-1);
    }
    if ( false === ($colors[3] = imagecolorallocate($im, 255, 255, 0)) ) {
        print "ERROR imagecolorallocate (255,0,148)" . PHP_EOL; exit(-1);
    }
    if ( false === ($colors[4] = imagecolorallocate($im, 0, 255, 255)) ) {
        print "ERROR imagecolorallocate (0,148,255)" . PHP_EOL; exit(-1);
    }
    if ( false === ($colors[5] = imagecolorallocate($im, 255, 255, 255)) ) {
        print "ERROR imagecolorallocate (0,148,255)" . PHP_EOL; exit(-1);
    }
    if ( false === ($bg = imagecolorallocatealpha($im, 0, 0, 0, 127)) ) {
        print "ERROR imagecolorallocatealpha" . PHP_EOL; exit(-1);
    }
    if ( false === imagefill($im, 0, 0, $bg) ) {
        print "ERROR imagefill" . PHP_EOL; exit(-1);
    }
    $colorIndex = 0;
    foreach($contornos as $c) {
        print $c['value'] . PHP_EOL;
        foreach($c['segments'] as $p) {
            //print "(" . ($p['x1']*10.0) . "," . $p['y1']*10.0 . ")=>(" . $p['x2']*10.0 . "," . $p['y2']*10.0 . ")";
            imageline($im, $p['x1']*10.0, 100 - $p['y1']*10.0, $p['x2']*10.0, 100 - $p['y2']*10.0, $colors[$colorIndex]);
        }
        // print PHP_EOL;
        $colorIndex++;
    }

    if ( false === imagepng( $im, $nombre . ".png" ) ) {
        print "ERROR imagepng ${nombre}.png" . PHP_EOL; exit(-1);
    }
    if ( false === imagedestroy( $im ) ) {
        print "ERROR imagedestroy" . PHP_EOL; exit(-1);
    }

    print "INFO nombre fichero: ${nombre}.png" . PHP_EOL;
    return true;
}

/*
 * Genera un KML a partir de una definición de poligonos. Cada polígono
 * puede tener huecos, definidos en los subarrays "inside".
 * @param array polygons
 * @return string kml
 * @return bool
 */
function fromPolygons2KML($polygons, $radarWithFL, $rgb, $altMode) {

    $kmlHeader = '<?xml version="1.0" encoding="UTF-8"?>
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

    $kmlPolygonHeader = PHP_EOL .
'                <Polygon>
                <extrude>1</extrude>
		<altitudeMode>' . $altMode . '</altitudeMode>
		<outerBoundaryIs><LinearRing><coordinates>' . PHP_EOL;
    $kmlPolygonFooter_1 = PHP_EOL .
'                </coordinates></LinearRing></outerBoundaryIs>';

    $kmlPolygonFooter_2 = PHP_EOL . 
'                </Polygon>';

    $kmlInnerHeader = '<innerBoundaryIs><LinearRing><coordinates>';
    $kmlInnerFooter = '</coordinates></LinearRing></innerBoundaryIs>';

    $kmlFooter = PHP_EOL .
'            </MultiGeometry>
        </Placemark>
        </Document></kml>';



    $kml = $kmlHeader;
    foreach( $polygons as &$polygon ) {
        $kmlOuter = "";
        if ( 10000 < count($polygon['polygon']) ) {
            print "DEBUG current/refined vertex cound => " . count($polygon['polygon']) . "/";
            $polygon['polygon'] = ramer_douglas_peucker($polygon['polygon'], 0.0000000001);
            print count($polygon['polygon']) . PHP_EOL;
        }
        foreach ( $polygon['polygon'] as &$p ) {
            $kmlOuter .= $p[1] . "," . $p[0] . "," . $p[2] . " ";
        }
        $kml .= $kmlPolygonHeader . $kmlOuter . $kmlPolygonFooter_1;
        foreach ( $polygon['inside'] as &$polygons_inside ) {
            $kmlInner = "";
            foreach ($polygons_inside['polygon'] as &$p_inside) {
                $kmlInner .=  $p[1] . "," . $p[0] . "," . $p[2] . " ";
            }
            if ( "" != $kmlInner ) {
                $kml .= $kmlInnerHeader . PHP_EOL . $kmlInner . PHP_EOL . $kmlInnerFooter;
            }
        }
        $kml .= $kmlPolygonFooter_2;
    }
    $kml .= $kmlFooter;
    return $kml;
}

/*
    // ahora el código para generar el kmz sería mucho mas sencillo, sin ifs, ni nada.
    foreach( $listaContornos as &$contorno ) {
        $cadenaOuter = "";
        foreach ( $contorno['polygon'] as &$p ) {
            // transforma las coordenadas del level 0 -> outer
            // si no existe lat, lon y alt, utilizar los índices 0,1 y la altura que vino como parámetro
            if ( isset($p['lat']) && isset($p['lon']) && isset($p['alt']) ) {
                $cadenaOuter .= $p['lon'] . "," . $p['lat'] . "," . $p['alt'] . " ";
            } elseif ( isset($p['fila']) && isset($p['col']) ) {
                $cadenaOuter .= $p['fila'] . "," . $p['col'] . "," . $fl*100*FEET_TO_METERS . " ";
            } elseif ( isset($p[0]) && isset($p[1]) ) {
                $cadenaOuter .= $p[1] . "," . $p[0] . "," . $fl*100*FEET_TO_METERS . " ";
            } else {
                die("ERROR, formato de punto incorrecto: " . print_r($p, true) . PHP_EOL);
            }
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
                // $cadenaInner .= $p_inside['lon'] . "," . $p_inside['lat'] . "," . $p_inside['alt'] . " ";
                if ( isset($p_inside['lat']) && isset($p_inside['lon']) && isset($p_inside['alt']) ) {
                    $cadenaInner .= $p_inside['lon'] . "," . $p_inside['lat'] . "," . $p_inside['alt'] . " ";
                } elseif ( isset($p_inside['fila']) && isset($p_inside['col']) ) {
                    $cadenaInner .= $p_inside['fila'] . "," . $p_inside['col'] . "," . $fl*100*FEET_TO_METERS . " ";
                } elseif ( isset($p_inside['fila']) && isset($p_inside['col']) ) {
                    $cadenaInner .= $p_inside[1] . "," . $p_inside[0] . "," . $fl*100*FEET_TO_METERS . " ";
                } else {
                    die("ERROR, formato de punto incorrecto: " . print_r($p, true) . PHP_EOL);
                }
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
*/
    // 

/*
    $contenido .= PHP_EOL .
'            </MultiGeometry>
        </Placemark>
        </Document></kml>';
*/
