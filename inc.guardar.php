<?php

CONST PERMISOS = 0775;

/**
 * Funcion para crear el fichero kml con los resultados del calculo de la cobertura del radar (CASO B: fl por debajo del radar)
 *
 * @param array $listaContornos (ENTRADA)
 * @param array|string $radarName Radares que se han utilizado para esta cobertura (ENTRADA)
 * @param array string $ruta Paths donde guardar el fichero generado (ENTRADA)
 * @param int $fl Nivel de vuelo (ENTRADA)
 * @param string $altMode Si el KML está pegado al suelo o la altura es relativa/absoluta (ENTRADA)
 * @param string|array $appendToFilename Información a añadir al final del nombre del fichero (ENTRADA)
 * @param bool $kmz true or false for kml
 * @return bool
 */
function creaKml2($listaContornos, $radarName, $rutas, $nivelVuelo, $altMode, $appendToFilename="", $coverageLevel = 'mono', $disableKmz = true) {

    $fl = $nivelVuelo * 100.0 * FEET_TO_METERS;

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
	default:
	    $rgb = "7d00ffff"; break;
    }

    logger(" D> Nivel de cobertura: $coverageLevel");

    if ( is_array($appendToFilename) ) {
        $appendToFilename = "-" . implode("_", $appendToFilename);
    }

    if ( is_array($radarName) ) {
        $radarWithFL = implode(",", $radarName) . "-" .
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
        logger(" E> No se genera fichero kmz para FL $nivelVuelo porque no hay cobertura");
        return false;
    }

    // vamos a transformar los polígonos que pueden venir en lat/lon, fila/col o 0/1 en 0/1/2.
    // porque sino es un lio.
    $group = array(); $vextex = array(); // estadísticas
    foreach( $listaContornos as $contorno ) {
        // $vertexCount = 0; // estadísticas
        $polygon = array();
	if ( isset($contorno['polygon']) ) {
	    foreach ( $contorno['polygon'] as $p ) {
		if ( isset($p['lat']) && isset($p['lon']) && isset($p['alt']) ) {
		    // generado en calculaCoordenadasGeograficasA/B
		    $polygon[] = array($p['lat'], $p['lon'], $p['alt']);
		} elseif ( isset($p['fila']) && isset($p['col']) ) {
		    // generado en calculaCoordenadasGeograficasB
		    $polygon[] = array($p['col'], $p['fila'], $fl*100*FEET_TO_METERS);
		} elseif ( isset($p[0]) && isset($p[1]) ) {
		    $polygon[] = array($p[0], $p[1], $fl*100*FEET_TO_METERS); // /100.0
		} else {
		    logger(" E> Formato de punto incorrecto: " . print_r($p, true));
		}
		// $vertexCount++; // estadísticas
	    } // foreach
	} else {
	    foreach ( $contorno as &$p ) {
		$polygon[] = array($p[0], $p[1], $fl*100*FEET_TO_METERS);
		// $vertexCount++; // estadísticas
	    }
	}

        $inside = array();
	// $vertexCountInside = array();

	if ( isset($contorno['inside']) ) {
	    foreach ( $contorno['inside'] as $contorno_inside ) {
		$polygon_inside = array();
		// $currentCountInside = 0;
		foreach ($contorno_inside['polygon'] as $p_inside) {
            	    if ( isset($p_inside['lat']) && isset($p_inside['lon']) && isset($p_inside['alt']) ) {
                	$polygon_inside[] = array($p_inside['lat'], $p_inside['lon'], $p_inside['alt']);
            	    } elseif ( isset($p_inside['fila']) && isset($p_inside['col']) ) {
                	$polygon_inside[] = array($p_inside['col'], $p_inside['fila'], $fl*100*FEET_TO_METERS);
            	    } elseif ( isset($p_inside[0]) && isset($p_inside[1]) ) {
                	$polygon_inside[] = array($p_inside[0], $p_inside[1], $fl*100*FEET_TO_METERS); //  /100.0
            	    } else {
                	logger(" E> Formato de punto incorrecto: " . print_r($p, true));
            	    }
            	    // $currentCountInside++; // estadísticas
        	}
        	$inside[] = array('polygon' => $polygon_inside);
        	// $vertextCountInside[] = $currentCountInside; // estadísticas
	    }
	}

	$group[] = array('polygon' => $polygon, 'inside' => $inside);
	// if ( 0 == count( $vertexCountInside ) ) $vertexCountInside = false;
	// $vertex[] = array('polygon' => $vertexCount, 'inside' => $vertexCountInside);
    }
    // $group tiene la geometría necesaria para pintar todo, en el formato
    // 0=>lat, 1=>lon, 2=>height

    $kmlContent = fromPolygons2KML($group, $radarWithFL, $rgb, $altMode);

    foreach($rutas as $val) { // GUARDAR_POR_NIVEL y GUARDAR_POR_RADAR o el que sea
	crearCarpetaResultados($val);
	writeKMZ($val . $radarWithFL/* . $appendToFilename*/, $radarWithFL, $kmlContent, $disableKmz);
    }
    return true;
}


/*
 * Antiguamente el formato de puntos en listaContornos tenía varios
 * formatos, ahora sólo debería tener un array de puntos en coordenadas
 * lat, lon. Además vamos a usar esta función dos veces, para los
 * puntos del polígono y para los puntos de los polígonos interiores
 * (agujeros).
 */
function extrae_puntos_contorno($polygon, $altitude_meters) {
    $puntos = array();
    foreach ( $polygon as $p ) {
	if ( isset($p[0]) && isset($p[1]) ) {
	    $puntos[] = array($p[0], $p[1], $altitude_meters);
	} else {
	    logger(" E> Formato de punto incorrecto: " . print_r($p, true));
	    exit(-1);
	}
    } // foreach
    return $puntos;
}

function KML_get_rgb_from_coverageLevel($coverageLevel) {

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
	default:
	    $rgb = "7d00ffff"; break;
    }

    return $rgb;
}

/*
 * Si necesitamos crear un KMZ de una multicobertura, crearemos carpeta a carpeta usando
 * esta función. Luego volcaremos todo de golpe, porque la estructura es compleja.
 */
function KML_get_placemarks($listaContornos, $radarName, $rutas, $nivelVuelo, $altMode, $appendToFilename="", $coverageLevel = 'mono', $disableKmz = true) {

    logger(" D> Nivel de cobertura: $coverageLevel");

    $altitude_meters = $nivelVuelo * 100.0 * FEET_TO_METERS;
    $rgb = KML_get_rgb_from_coverageLevel($coverageLevel);

    if ( is_array($appendToFilename) ) {
        $appendToFilename = "-" . implode("_", $appendToFilename);
    }

    if ( is_array($radarName) ) {
        $radarWithFL = implode(",", $radarName) . "-" .
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
        logger(" E> No se genera fichero kmz para FL $nivelVuelo porque no hay cobertura");
        return false;
    }

    // hay que transformar listaContornos en el código KML.
    // queremos un Placemark/name/styleUrl/Polygon por cada polígono.
    // luego pondremos todos los polígonos en una carpeta.
    // no queremos Placemark/name/styleUrl/Multigeometry/Polygon1/Polygon2...
    // esto complica la portabilidad

    $placemarks = array();
    $i = 0;
    foreach( $listaContornos as $contorno1 ) {
	$num_puntos = 0;
	$polygon = array();
	if ( isset($contorno1['polygon']) ) {
	    $polygon = extrae_puntos_contorno($contorno1['polygon'], $altitude_meters);
	    $num_puntos += count($contorno1['polygon']);
	}

	// un polígono puede tener n poligonos dentro que representen n agujeros.
        $inside = array();
	if ( isset($contorno1['inside']) ) {
	    foreach ( $contorno1['inside'] as $contorno2 ) {
		$inside[] = extrae_puntos_contorno($contorno2['polygon'], $altitude_meters);
		$num_puntos += count($contorno2['polygon']);
	    }
	}

	// $polygon tiene una lista de puntos, mientras que inside tiene
	// una lista de polígonos, cada uno con una lista de puntos.
	$placemarks[] = KML_format_placemarks(
	    $radarWithFL . "_{$i}" . " (" . round($contorno1['area'],2) . "km^2, " . $num_puntos . "ptos)",
	    $polygon,
	    $inside,
	    $rgb);
	$i++;
    }
    // $group tiene la geometría necesaria para pintar todo, en el formato:
    // array('polygon', 'inside')
    // donde tanto polygon como inside contienen un array con:
    // 0=>lat, 1=>lon, 2=>height

    // $kmlContent = KML_placemarks_in_Folders($radarWithFL, $placemarks, $rgb, $altMode);

    return $placemarks;

}

/*
 * Genera un array de placemarks, cada uno con su polígono y sus agujeros, y
 * un estilo basado en el color rgb
 */
function KML_format_placemarks($name, $polygon, $inside, $rgb) {

    // polygon es una lista de puntos que determinan un polígono
    $outer_coordinates = "";
    foreach($polygon as $points) {
	$outer_coordinates .= "{$points[1]},{$points[0]},{$points[2]} ";
    }

    $inner = ""; 
    foreach($inside as $poly) {
	$inner_coordinates = "";
	foreach($poly as $points) {
	    $inner_coordinates .= "{$points[1]},{$points[0]},{$points[2]} ";
	}
	$inner .= "
			<innerBoundaryIs>
			    <LinearRing>
				<coordinates>
$inner_coordinates
				</coordinates>
			    </LinearRing>
			</innerBoundaryIs>";
    }

    $placemark = "" .
"		<Placemark>
		    <name>$name</name>
		    <styleUrl>#transparentPoly{$rgb}</styleUrl>
		    <Polygon>
			<tessellate>1</tessellate>
			<outerBoundaryIs>
			    <LinearRing>
				<coordinates>
{$outer_coordinates}
				</coordinates>
			    </LinearRing>
			</outerBoundaryIs>{$inner}
		    </Polygon>
		</Placemark>";

    return $placemark;
}

/*
 * Genera un KML completo usando los placemarks por cobertura
 */
function KML_create_from_placemarks($coverages_per_levels, $padded_FL, $file_name) {

    $coverage_levels = array("unica", "mono", "doble", "triple", "cuadruple", "quintuple", "sextuple");
    $kml_styles = "";
    foreach($coverage_levels as $level) {
	$rgb = KML_get_rgb_from_coverageLevel($level);
	$kml_styles .= "" .
"	<Style id=\"transparentPoly{$rgb}\">
	    <LineStyle><width>1.5</width></LineStyle>
	    <PolyStyle><color>{$rgb}</color></PolyStyle>
	</Style>
";
    }

    $kml_header = "".
"<?xml version=\"1.0\" encoding=\"UTF-8\"?>
<kml xmlns=\"http://www.opengis.net/kml/2.2\" xmlns:gx=\"http://www.google.com/kml/ext/2.2\" xmlns:kml=\"http://www.opengis.net/kml/2.2\" xmlns:atom=\"http://www.w3.org/2005/Atom\">
    <Document>
	<name>FL{$padded_FL}</name>
	<open>0</open>
{$kml_styles}";

 $kml_footer = "
    </Document>
</kml>";

    $kml_folder_footer = "" .
"	</Folder>";


    $kml = $kml_header;
    foreach ( $coverages_per_levels as $level => $sensores ) {

        $kml_folder_header = "" .
"	<Folder>
	    <name>Nivel $level</name>
	    <open>0</open>
";
	$kml .= $kml_folder_header;
	// para cada uno de los grupos de los radares, habrá n polígonos/placemarks
	$i = 0;
	foreach ( $sensores as $nombre => $placemarks ) {
	    $header = "" .
"	    <Folder>
		<name>{$nombre}</name>
		<open>0</open>
";
	    $footer = "
	    </Folder>
";
	    $kml .= $header . implode(PHP_EOL, $placemarks) . $footer;
	    $i++;
	}

	$kml .= $kml_folder_footer;

    }

    $kml .= $kml_footer;

    writeKMZ("FL" . $padded_FL, $padded_FL, $kml, $disable_kmz = true);

    return true;

}

/*
 * Genera un KML a partir de una definición de poligonos. Cada polígono
 * puede tener huecos, definidos en los subarrays "inside".
 * Esta función genera el código KML para colocar los polígonos en una carpeta.
 * No crea la carpeta
 * @param array polygons
 * @return string kml
 * @return bool
 */
function KML_placemarks_in_Folders($radarWithFL, $polygons, $rgb, $altMode) {
/*
//    $kmlHeader = '<?xml version="1.0" encoding="UTF-8"?>
//        <kml xmlns="http://www.opengis.net/kml/2.2" xmlns:gx="http://www.google.com/kml/ext/2.2" xmlns:kml="http://www.opengis.net/kml/2.2" xmlns:atom="http://www.w3.org/2005/Atom">
*/
    $kmlFolder_Content = '
  <Document>
    <name>' . $radarWithFL . '</name>
    <open>0</open>
    <Style id="transGreenPoly">
      <LineStyle><width>1.5</width></LineStyle>
      <PolyStyle><color>' . $rgb . '</color></PolyStyle>
    </Style>
    <Placemark>
      <name>' .  $radarWithFL . '</name>
        <styleUrl>#transGreenPoly</styleUrl>
        <MultiGeometry>';

    $kmlPolygonHeader = '
          <Polygon>
            <extrude>1</extrude>
            <altitudeMode>' . $altMode . '</altitudeMode>
            <outerBoundaryIs><LinearRing><coordinates>' . PHP_EOL;

    $kmlPolygonFooter_1 = '
            </coordinates></LinearRing></outerBoundaryIs>';

    $kmlPolygonFooter_2 = '
          </Polygon>';

    $kmlInnerHeader = '<innerBoundaryIs><LinearRing><coordinates>';
    $kmlInnerFooter = '</coordinates></LinearRing></innerBoundaryIs>';

    $kmlFooter_Content = '
        </MultiGeometry>
    </Placemark>
  </Document>';

    $kml = $kmlFolder_Content;
    foreach( $polygons as &$polygon ) {
        $kmlOuter = "";
        //$polygon['polygon'] = ramer_douglas_peucker($polygon['polygon'], 0.0000000001);
        foreach ( $polygon['polygon'] as &$p ) {
            $kmlOuter .= $p[1] . "," . $p[0] . "," . $p[2] . " ";
        }
        $kml .= $kmlPolygonHeader . $kmlOuter . $kmlPolygonFooter_1;
        foreach ( $polygon['inside'] as &$polygons_inside ) {
            $kmlInner = "";
            foreach ($polygons_inside['polygon'] as &$p_inside) {
                $kmlInner .=  $p_inside[1] . "," . $p_inside[0] . "," . $p_inside[2] . " ";
            }
            if ( "" != $kmlInner ) {
                $kml .= $kmlInnerHeader . PHP_EOL . $kmlInner . PHP_EOL . $kmlInnerFooter;
            }
        }
        $kml .= $kmlPolygonFooter_2;
    }
    $kml .= $kmlFooter_Content;
    return $kml;
}

/*
 * Genera un KML a partir de una definición de poligonos. Cada polígono
 * puede tener huecos, definidos en los subarrays "inside".
 * Esta función genera el código KML para colocar los polígonos en una carpeta.
 * No crea la carpeta.
 * @param array polygons
 * @return string kml
 * @return bool
 */
function fromPolygons2KML_One_Folder_Per_Content($polygons, $radarWithFL, $rgb, $altMode) {

die("CODIGO EN SUPERVISION FUNCION NO SOPORTADA UNSUPPORTED");
/*
//    $kmlHeader = '<?xml version="1.0" encoding="UTF-8"?>
//        <kml xmlns="http://www.opengis.net/kml/2.2" xmlns:gx="http://www.google.com/kml/ext/2.2" xmlns:kml="http://www.opengis.net/kml/2.2" xmlns:atom="http://www.w3.org/2005/Atom">
*/
    $kmlFolder_Content = '
  <Document>
    <name>' . $radarWithFL . '</name>
    <open>0</open>
    <Style id="transGreenPoly">
      <LineStyle><width>1.5</width></LineStyle>
      <PolyStyle><color>' . $rgb . '</color></PolyStyle>
    </Style>
    <Placemark>
      <name>' .  $radarWithFL . '</name>
        <styleUrl>#transGreenPoly</styleUrl>
        <MultiGeometry>';

    $kmlPolygonHeader = '
          <Polygon>
            <extrude>1</extrude>
            <altitudeMode>' . $altMode . '</altitudeMode>
            <outerBoundaryIs><LinearRing><coordinates>' . PHP_EOL;

    $kmlPolygonFooter_1 = '
            </coordinates></LinearRing></outerBoundaryIs>';

    $kmlPolygonFooter_2 = '
          </Polygon>';

    $kmlInnerHeader = '<innerBoundaryIs><LinearRing><coordinates>';
    $kmlInnerFooter = '</coordinates></LinearRing></innerBoundaryIs>';

    $kmlFooter_Content = '
        </MultiGeometry>
    </Placemark>
  </Document>';

    $kml = $kmlFolder_Content;
    foreach( $polygons as &$polygon ) {
        $kmlOuter = "";
        //$polygon['polygon'] = ramer_douglas_peucker($polygon['polygon'], 0.0000000001);
        foreach ( $polygon['polygon'] as &$p ) {
            $kmlOuter .= $p[1] . "," . $p[0] . "," . $p[2] . " ";
        }
        $kml .= $kmlPolygonHeader . $kmlOuter . $kmlPolygonFooter_1;
        foreach ( $polygon['inside'] as &$polygons_inside ) {
            $kmlInner = "";
            foreach ($polygons_inside['polygon'] as &$p_inside) {
                $kmlInner .=  $p_inside[1] . "," . $p_inside[0] . "," . $p_inside[2] . " ";
            }
            if ( "" != $kmlInner ) {
                $kml .= $kmlInnerHeader . PHP_EOL . $kmlInner . PHP_EOL . $kmlInnerFooter;
            }
        }
        $kml .= $kmlPolygonFooter_2;
    }
    $kml .= $kmlFooter_Content;
    return $kml;
}


function writeKMZ($fileName, $radarWithFL, $content, $disableKmz = false) {

    if ( true === $disableKmz || !class_exists('ZipArchive') ) {
	// generar kml y volver
	logger(" V> Guardando fichero {$fileName}.kml");
	if ( false === ($ret = file_put_contents("{$fileName}.kml", $content)) ) {
	    logger(" E> Problema al guardar {$fileName}.kml");
	    return false;
	}

	return true;
    }
    logger(" V> Guardando fichero {$fileName}.kmz");

    $zip = new ZipArchive();
    if ( false === $zip->open(
        $fileName . ".kmz",
        ZIPARCHIVE::CREATE | ZIPARCHIVE::OVERWRITE)
    ) {
	logger(" E> No se puede crear el fichero {$filename}.kmz"); exit(-1);
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
	logger(" V> Creando carpeta >{$ruta}<");
        //clearstatcache();
        //$ruta = $ruta ."/". $radar['site'] . "/"; // /home/eval/berta/RESULTADOS/LE_VALLADOLID 
        if (mkdir($ruta, PERMISOS, true)) {
	    logger(" V> Carpeta >{$ruta}< creada correctamente");
            return true;
        } else {
	    logger(" E> Error creando carpeta >{$ruta}<"); exit(-1);
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
        die("ERROR imagepng {$nombre}.png" . PHP_EOL);
    }

    if ( false === imagedestroy( $im ) ) {
        die("ERROR imagedestroy" . PHP_EOL);
    }
    print "INFO guardando fichero: {$nombre}.png" . PHP_EOL;

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
        return;

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
        print "ERROR imagepng {$nombre}.png" . PHP_EOL; exit(-1);
    }
    if ( false === imagedestroy( $im ) ) {
        print "ERROR imagedestroy" . PHP_EOL; exit(-1);
    }

    print "INFO nombre fichero: {$nombre}.png" . PHP_EOL;
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
        print "ERROR imagepng {$nombre}.png" . PHP_EOL; exit(-1);
    }
    if ( false === imagedestroy( $im ) ) {
        print "ERROR imagedestroy" . PHP_EOL; exit(-1);
    }

    print "INFO nombre fichero: {$nombre}.png" . PHP_EOL;
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
        //if ( 10000 < count($polygon['polygon']) ) {
	$count = count($polygon['polygon']);
        //    print "DEBUG current/refined vertex count => " . count($polygon['polygon']) . "/";
        $polygon['polygon'] = ramer_douglas_peucker($polygon['polygon'], 0.0000000001);
	$new_count = count($polygon['polygon']);
	//if ( $count != $new_count ) {
	//    logger(" V> current/refined vertex cound => $count/$new_count");
        //    print count($polygon['polygon']) . PHP_EOL;
        //}
        foreach ( $polygon['polygon'] as &$p ) {
            $kmlOuter .= $p[1] . "," . $p[0] . "," . $p[2] . " ";
        }
        $kml .= $kmlPolygonHeader . $kmlOuter . $kmlPolygonFooter_1;
        foreach ( $polygon['inside'] as &$polygons_inside ) {
            $kmlInner = "";
            foreach ($polygons_inside['polygon'] as &$p_inside) {
                $kmlInner .=  $p_inside[1] . "," . $p_inside[0] . "," . $p_inside[2] . " ";
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
