<?php

const BERTA_PERMISOS = 0775;

/*
 * Genera un KML a partir de una definición de poligonos. Cada polígono
 * puede tener huecos, definidos en los subarrays "innners". Función para
 * parsear directamente la salida de normalizePolygonsForKML.
 * @param array $multi_polygons_classified
 * @param string $radarWithFL
 * @param string $altMode "clampToGround|clampToSeaFloor|RelativeToGround|absolute" (ENTRADA)
 * @param float $fl
 * @return string kml
 */
function fromPolygons2KML4(array $multi_polygons_classified, string $radarWithFL, string $altMode, float $fl)
{
    $kmlHeader =                    "<?xml version=\"1.0\" encoding=\"UTF-8\"?>" .
                          PHP_EOL . "<kml xmlns=\"http://www.opengis.net/kml/2.2\" xmlns:gx=\"http://www.google.com/kml/ext/2.2\" xmlns:kml=\"http://www.opengis.net/kml/2.2\" xmlns:atom=\"http://www.w3.org/2005/Atom\">" .
                          PHP_EOL . "<Document>" .
                          PHP_EOL . "  <name>BERTA</name>" .
                          PHP_EOL . KML_generate_styles();
  

    $kmlPolygonHeader =   PHP_EOL . "      <Polygon>" .
                          PHP_EOL . "        <extrude>1</extrude>" .
                          PHP_EOL . "        <altitudeMode>{$altMode}</altitudeMode>" .
                          PHP_EOL . "        <outerBoundaryIs><LinearRing><coordinates>" . PHP_EOL;
    $kmlPolygonFooter_1 = PHP_EOL . "        </coordinates></LinearRing></outerBoundaryIs>";
    $kmlInnerHeader =     PHP_EOL . "        <innerBoundaryIs><LinearRing><coordinates>" . PHP_EOL;
    $kmlInnerFooter =     PHP_EOL . "        </coordinates></LinearRing></innerBoundaryIs>";
    $kmlPolygonFooter_2 = PHP_EOL . "      </Polygon>";

    $kmlPlacemarkFooter = PHP_EOL . "    </MultiGeometry>" . 
                          PHP_EOL . "    </Placemark>";

    $kmlFooter =          PHP_EOL . "</Document>" .
                          PHP_EOL . "</kml>";

    // DIVIDIR ESTA FUNCION PARA PODER LLAMARLA DESDE MULICOBERTURA

    $kml = $kmlHeader;
    foreach ($multi_polygons_classified as $level => $multi_polygons) {
        print $level . PHP_EOL;
        // print json_encode($multi_polygons);
        $outer = true;
        $kmlPlacemark =    "  <Placemark>" .
                           PHP_EOL . "    <name>{$radarWithFL}</name>" .
                           PHP_EOL . "    <styleUrl>#transparentPoly-{$level}</styleUrl>" .
                           PHP_EOL . "    <MultiGeometry>";
        $kml .= $kmlPlacemark;
        $kmlOuter = "";
        foreach ($multi_polygons as $polygons) {
            foreach ($polygons as $polygon) {
                if ($outer) {
                    $polygon = ramer_douglas_peucker($polygon, BERTA_RAMER_DOUGLAS_PEUCKER_PRECISION);
                    $outer = false;
                    foreach ($polygon as $p) {
                        $kmlOuter .= $p[1] . "," . $p[0] . "," . $fl . " ";
                    }
                    $kml .= $kmlPolygonHeader . $kmlOuter . $kmlPolygonFooter_1;
                } else {
                    // aquí se ejecutan el resto de polígonos que seran agujeros del primero
                    $kmlInner = "";
                    foreach ($polygon as &$p_inside) {
                        $kmlInner .=  $p_inside[1] . "," . $p_inside[0] . "," . $fl . " ";
                    }
                    if ("" != $kmlInner) {
                        $kml .= $kmlInnerHeader . $kmlInner . $kmlInnerFooter;
                    }
                }
            }
        }
        $kml .= $kmlPolygonFooter_2;
    }
    $kml .= $kmlPlacemarkFooter . $kmlFooter;
    return $kml;
}

/**
 * Utiliza uno o varios placemark dentro de una o varias folders y completa el formato kml
 * 
 * @param string $kml contenido a insertar en un kml completo
 * @param string $nivelVuelo nivel de vuelo en 100ft para añadir al nombre del kml
 */
function KML_generate_full_kml(string $kml, string $nivelVuelo = "") {

    if ( !empty($nivelVuelo) )
        $nivelVuelo = "-" . $nivelVuelo;

    $kmlHeader =                    "<?xml version=\"1.0\" encoding=\"UTF-8\"?>" .
                          PHP_EOL . "<kml xmlns=\"http://www.opengis.net/kml/2.2\" xmlns:gx=\"http://www.google.com/kml/ext/2.2\" xmlns:kml=\"http://www.opengis.net/kml/2.2\" xmlns:atom=\"http://www.w3.org/2005/Atom\">" .
                          PHP_EOL . "<Document>" .
                          PHP_EOL . "  <name>BERTA{$nivelVuelo}</name>" .
                          PHP_EOL . KML_generate_styles();
  
    $kmlFooter =          "</Document>" .
                          PHP_EOL . "</kml>" .
                          PHP_EOL;


    return $kmlHeader . $kml . $kmlFooter;
}

function KML_generate_styles()
{
    $coverage_levels = array("unica", "mono", "doble", "triple", "cuadruple", "quintuple", "sextuple");
    $kml_styles = "";
    foreach ($coverage_levels as $level) {
        $rgb = KML_get_rgb_from_coverageLevel($level);
        $kml_styles .=              "  <Style id=\"transparentPoly-{$level}\">" .
            PHP_EOL . "    <LineStyle><width>1.5</width></LineStyle>" .
            PHP_EOL . "    <PolyStyle><color>{$rgb}</color></PolyStyle>" .
            PHP_EOL . "  </Style>" . PHP_EOL;
    }
    return $kml_styles;
}


function KML_check_coverage_levels(string $coverageLevel) {
    $coverage_levels = array(0 => "unica", "mono", "doble", "triple", "cuadruple", "quintuple", "sextuple");
    if ( !in_array($coverageLevel, $coverage_levels) ) {
        debug_print_backtrace(); die("deprecated " . __FUNCTION__ . " in " . __FILE__ . " at line " . __LINE__ . PHP_EOL);
    }
    return true; 
}

/**
 * Mete varios placemarks en una carpeta
 * 
 * @param string $folder_name Nombre de la carpeta que se genera para el kml
 * @param string $kml uno o más placemarks para meter en carpeta
 */
function KML_create_folder(string $folder_name, string $kml) {

    $kmlFolder = "<Folder>" .
        PHP_EOL . "  <name>{$folder_name}</name>" .
        PHP_EOL . "  <open>0</open>" .
        PHP_EOL . $kml .
        PHP_EOL . "</Folder>" .
        PHP_EOL;

    return $kmlFolder;
}

/**
 * Solo genera el KML necesario para un polígono
 * @param string $altMode "clampToGround|clampToSeaFloor|RelativeToGround|absolute" (ENTRADA)
 */
function normalized2KML(array $multi_polygons, string $coverageLevel, array $sensors, int $fl, string $altMode = "clampToGround")
{
    KML_check_coverage_levels($coverageLevel);
    $flm = round($fl * 100.0 * BERTA_FEET_TO_METERS, 2);
    $flWithPad = str_pad((string) $fl, 3, "0", STR_PAD_LEFT);
    $radarWithFl = implode(',', $sensors) . "-" . $flWithPad;
    $kml = "";

    $kmlPlacemark =    "  <Placemark>" .
        PHP_EOL . "    <name>{$radarWithFl}</name>" .
        PHP_EOL . "    <styleUrl>#transparentPoly-{$coverageLevel}</styleUrl>" .
        PHP_EOL . "    <MultiGeometry>";

    $kmlPolygonHeader =   PHP_EOL . "      <Polygon>" .
        PHP_EOL . "        <extrude>1</extrude>" .
        PHP_EOL . "        <altitudeMode>{$altMode}</altitudeMode>" .
        PHP_EOL . "        <outerBoundaryIs><LinearRing><coordinates>" . PHP_EOL;

    $kmlPolygonFooter_1 = PHP_EOL . "        </coordinates></LinearRing></outerBoundaryIs>";
    $kmlInnerHeader =     PHP_EOL . "        <innerBoundaryIs><LinearRing><coordinates>" . PHP_EOL;
    $kmlInnerFooter =     PHP_EOL . "        </coordinates></LinearRing></innerBoundaryIs>";
    $kmlPolygonFooter_2 = PHP_EOL . "      </Polygon>";

    $kmlPlacemarkFooter = PHP_EOL . "    </MultiGeometry>" .
        PHP_EOL . "  </Placemark>";

    $kml .= $kmlPlacemark;
    foreach ($multi_polygons as $polygons) {
        $outer = true;
        $kmlOuter = "";
        foreach ($polygons as $polygon) {
            if ($outer) {
                $polygon = ramer_douglas_peucker($polygon, BERTA_RAMER_DOUGLAS_PEUCKER_PRECISION);
                $outer = false;
                foreach ($polygon as $p) {
                    $kmlOuter .= $p[1] . "," . $p[0] . "," . $flm . " ";
                }
                $kml .= $kmlPolygonHeader . $kmlOuter . $kmlPolygonFooter_1;
            } else {
                // aquí se ejecutan el resto de polígonos que seran agujeros del primero
                $kmlInner = "";
                foreach ($polygon as &$p_inside) {
                    $kmlInner .=  $p_inside[1] . "," . $p_inside[0] . "," . $flm . " ";
                }
                if ("" != $kmlInner) {
                    $kml .= $kmlInnerHeader . $kmlInner . $kmlInnerFooter;
                } else {
                    die("NO PUEDE SER");
                }
            }
        }
        $kml .= $kmlPolygonFooter_2;
    }
    $kml .= $kmlPlacemarkFooter;
    return $kml;
}


/**
 * Funcion para crear el fichero kml con los resultados del calculo de la cobertura del radar
 *
 * @param array $multi_polygons_classified con el nuevo formato de poligonos, donde level indica si es mono, doble, triple... (ENTRADA)
 * Entrada:
 * [ level => 
 * [
 *   [
 *     [... CCW ...], // outer
 *     [... CW ...], // inner
 *          ...
 *   ],
 *   ...
 * ]
 * ]
 * @param array $rutas Paths donde guardar el fichero generado (ENTRADA)
 * @param string $nivelVuelo Nivel de vuelo usado para el cálculo
 * @param string $altMode "clampToGround|clampToSeaFloor|RelativeToGround|absolute" (ENTRADA)
 * @param array $appendToFilename Información a añadir al final del nombre del fichero (ENTRADA)
 * @param bool $disableKmz Disable generation of compressed kml (kmz) files
 * @return bool
 */
function creaKml3(array $multi_polygons_classified, array $rutas, string $nivelVuelo, string $altMode, array $appendToFilename = array(), string $coverageLevel = 'mono', bool $disableKmz = true)
{
    // conversión a metros para usar en el kmz
    $flm = round(((float)$nivelVuelo) * 100.0 * BERTA_FEET_TO_METERS, 2);
    // aseguramos el formato del nivel de vuelo
    $nivelVuelo = str_pad($nivelVuelo, 3, "0", STR_PAD_LEFT);
    $coverageLevelAppend = "-" . $coverageLevel;

    logger(" D> Nivel de cobertura: $coverageLevel");

    if ( count($appendToFilename) > 1 ) {
        $appendStr = "-" . implode("_", $appendToFilename);
    } else if ( count($appendToFilename) == 1 ) {
        $appendStr = $appendToFilename[0];
    } else {
        $appendStr = "";

    }

    $radarWithFL = implode(",", $sensors) . 
        $coverageLevelAppend . "-FL" . $nivelVuelo . $appendStr;
   
    if (false) {
        print "nivelVuelo: " . $nivelVuelo . PHP_EOL;
        print "radarWithFL: " . $radarWithFL . PHP_EOL;
        print "ruta: " . print_r($rutas, true) . PHP_EOL;
    }

    if (0 == count($multi_polygons_classified)) {
        logger(" E> No se genera fichero kmz para FL $nivelVuelo porque no hay cobertura");
        return false;
    }

    $kmlContent = fromPolygons2KML4($multi_polygons_classified, $radarWithFL, $altMode, $flm);

    foreach ($rutas as $val) { // GUARDAR_POR_NIVEL y GUARDAR_POR_RADAR o el que sea
        crearCarpetaResultados($val);
        writeKMZ($val . $radarWithFL/* . $appendToFilename*/, $radarWithFL, $kmlContent, $disableKmz);
    }
    return true;
}

/**
 * Funcion para crear el fichero kml con los resultados del calculo de la cobertura del radar
 *
 * @param array $rutas Paths donde guardar el fichero generado (ENTRADA)
 * @param array $appendToFilename Información a añadir al final del nombre del fichero (ENTRADA)
 * @param bool $disableKmz Disable generation of compressed kml (kmz) files
 * @return bool
 */
function creaKml4(string $kml, array $rutas, string $nivelVuelo, array $sensors, array $appendToFilename = array(), bool $disableKmz = true)
{
    $coverage_levels = array("unica", "mono", "doble", "triple", "cuadruple", "quintuple", "sextuple");
    $kml_styles = "";
    foreach ($coverage_levels as $level) {
        $rgb = KML_get_rgb_from_coverageLevel($level);
        $kml_styles .=              "  <Style id=\"transparentPoly-{$level}\">" .
                          PHP_EOL . "    <LineStyle><width>1.5</width></LineStyle>" .
                          PHP_EOL . "    <PolyStyle><color>{$rgb}</color></PolyStyle>" .
                          PHP_EOL . "  </Style>" . PHP_EOL;
    }

    $kmlHeader =                    "<?xml version=\"1.0\" encoding=\"UTF-8\"?>" .
                          PHP_EOL . "<kml xmlns=\"http://www.opengis.net/kml/2.2\" xmlns:gx=\"http://www.google.com/kml/ext/2.2\" xmlns:kml=\"http://www.opengis.net/kml/2.2\" xmlns:atom=\"http://www.w3.org/2005/Atom\">" .
                          PHP_EOL . "<Document>" .
                          PHP_EOL . "  <name>BERTA</name>" .
                          PHP_EOL . "{$kml_styles}";

    $kmlFooter =          PHP_EOL . "</Document>" .
                          PHP_EOL . "</kml>";

    // $coverageLevelAppend = "-" . $coverageLevel;

    if ( count($appendToFilename) > 1 ) {
        $appendStr = "-" . implode("_", $appendToFilename);
    } else if ( count($appendToFilename) == 1 ) {
        $appendStr = $appendToFilename[0];
    } else {
        $appendStr = "";

    }

    $radarWithFL = implode(",", $sensors) . 
        "-FL" . $nivelVuelo . $appendStr;
   
    $kmlContent = $kmlHeader . $kml . $kmlFooter;

    foreach ($rutas as $val) { // GUARDAR_POR_NIVEL y GUARDAR_POR_RADAR o el que sea
        crearCarpetaResultados($val);
        writeKMZ($val . $radarWithFL/* . $appendToFilename*/, $radarWithFL, $kmlContent, $disableKmz);
    }
    return true;
}



/**
 * Entrada:
 * $polygons = [
 *   [ [ lat, lon ], ... cerrado ],
 *   ...
 * ]
 *
 * Salida:
 * [
 *   [
 *     [... CCW ...], // 'outer' 
 *     [ // 'inners'
 *          [... CW ...],
 *          ...
 *     ]
 *   ],
 *   ...
 * ]
 *
 * Regla:
 * - Nivel 0 = exterior
 * - Nivel 1 = agujero
 * - Nivel 2 = nuevo exterior
 */
function normalizePolygonsForKML(array $polygons): array
{
    $items = [];

    foreach ($polygons as $poly) {
        if (!is_array($poly)) continue;
        if (count($poly) < 4) continue;

        if (!isClosed($poly)) {
            $poly[] = $poly[0];
        }

        $items[] = [
            'poly' => $poly,
            'area' => abs(signedArea($poly)),
            'parent' => null,
            'depth' => 0
        ];
    }

    // ordenar grandes primero
    // usort($items, fn($a, $b) => $b['area'] <=> $a['area']);

    usort($items, function ($a, $b) {
        return $b['area'] <=> $a['area'];
    });

    $n = count($items);

    // detectar contención directa
    for ($i = 0; $i < $n; $i++) {

        $pt = interiorPoint($items[$i]['poly']);

        //for ($j = 0; $j < $i; $j++) {
        for ($j = $i - 1; $j >= 0; $j--) {

            if (pointInPolygon($pt[0], $pt[1], $items[$j]['poly'], count($items[$j]['poly']))) {
                $items[$i]['parent'] = $j;
                break;
            }
        }
    }

    // calcular profundidad
    for ($i = 0; $i < $n; $i++) {
        $depth = 0;
        $p = $items[$i]['parent'];

        while ($p !== null) {
            $depth++;
            $p = $items[$p]['parent'];
        }

        $items[$i]['depth'] = $depth;
    }

    $result = [];

    // crear exteriores válidos: depth par (0,2,4...)
    foreach ($items as $idx => $item) {

        if ($item['depth'] % 2 === 0) {

            $outer = ensureCCW($item['poly']);
            /*
            $result[$idx] = [
                'outer' => $outer,
                'inners' => []
            ];
            */
            $result[$idx] = [$outer];
        }
    }

    // asignar agujeros: depth impar (1,3,5...)
    foreach ($items as $idx => $item) {

        if ($item['depth'] % 2 === 1) {

            // buscar ancestro exterior inmediato
            $p = $item['parent'];

            while ($p !== null && ($items[$p]['depth'] % 2 === 1)) {
                $p = $items[$p]['parent'];
            }

            if ($p !== null && isset($result[$p])) {
                // $result[$p]['inners'][] = ensureCW($item['poly']);
                $result[$p][] = ensureCW($item['poly']);
            }
        }
    }

    return array_values($result);
}

/* =======================================================
   GEOMETRÍA
======================================================= */

/*
 * Calcula el área de la figura
 * Helper de normalizePolygonsForKML
 */
function signedArea(array $poly): float
{
    $sum = 0.0;
    $n = count($poly);

    for ($i = 0; $i < $n - 1; $i++) {
        $x1 = $poly[$i][1]; // longitude
        $y1 = $poly[$i][0]; // latitude

        $x2 = $poly[$i + 1][1]; // longitude
        $y2 = $poly[$i + 1][0]; // latitude

        $sum += ($x1 * $y2) - ($x2 * $y1);
    }

    return $sum / 2.0;
}
/*
 * Helper de normalizePolygonsForKML
 */
function isClosed(array $poly): bool
{
    $a = $poly[0];
    $b = $poly[count($poly) - 1];

    return $a[0] == $b[0] && $a[1] == $b[1];
}
/*
 * Helper de normalizePolygonsForKML
 */
function ensureCCW(array $poly): array
{
    return signedArea($poly) > 0 ? $poly : array_reverse($poly);
}
/*
 * Helper de normalizePolygonsForKML
 */
function ensureCW(array $poly): array
{
    return signedArea($poly) < 0 ? $poly : array_reverse($poly);
}
/*
 * Helper de normalizePolygonsForKML
 */
function interiorPoint(array $poly): array
{
    // primer vértice sirve normalmente
    return $poly[0];
}
/*
    * Test if point is inside polygon
    * @param float $x latitude
    * @param float $y longitude
    * @param array $poly list of points [[lat, lon], ...] doesn't need to be closed
    * @param int $n number of points in poly
    * @return bool true if inside
    */
function pointInPolygon(float $y, float $x, array &$poly, int $n): bool
{
    // Devuelve:
    //  true  -> dentro
    //  false  -> fuera
    //  exception -> sobre el borde 
    
    $inside = false;
    // $x = $p[1]; // lon
    // $y = $p[0]; // lat
    // $n = count($poly);
                
    for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
        $xi = $poly[$i][1];
        $yi = $poly[$i][0];

        $xj = $poly[$j][1];
        $yj = $poly[$j][0];
       
        $intersect =
            (($yi > $y) != ($yj > $y)) &&
            ($x < ($xj - $xi) * ($y - $yi) / (($yj - $yi)) + $xi);

        if ($intersect) {
            $inside = !$inside;
        }
        
    }
    return $inside;
}

function writeKMZ(string $fileName, string $radarWithFL, string $content, bool $disableKmz = false)
{

    if (true === $disableKmz || !class_exists('ZipArchive')) {
        // generar kml y volver
        logger(" V> Guardando fichero {$fileName}.kml");
        if (false === ($ret = @file_put_contents("{$fileName}.kml", $content))) {
            logger(" E> Problema al guardar {$fileName}.kml");
            exit(-1);
            //return false;
        }

        return true;
    }
    logger(" V> Guardando fichero {$fileName}.kmz");

    $zip = new ZipArchive();
    $res = $zip->open($fileName . ".kmz", ZIPARCHIVE::CREATE | ZIPARCHIVE::OVERWRITE);
    if (! $res) {
        logger(" E> No se puede crear el fichero {$fileName}.kmz");
        exit(-1);
    }
    $zip->addFromString($radarWithFL . ".kml", $content);
    $zip->close();

    return;
}

function KMZ_write(string $fileName, string $radarWithFL, string $content, bool $disableKmz = false)
{

    if (true === $disableKmz || !class_exists('ZipArchive')) {
        // generar kml y volver
        logger(" V> Guardando fichero {$fileName}.kml");
        if (false === ($ret = @file_put_contents("{$fileName}.kml", $content))) {
            logger(" E> Problema al guardar {$fileName}.kml");
            exit(-1);
            //return false;
        }

        return true;
    }
    logger(" V> Guardando fichero {$fileName}.kmz");

    $zip = new ZipArchive();
    $res = $zip->open($fileName . ".kmz", ZIPARCHIVE::CREATE | ZIPARCHIVE::OVERWRITE);
    if (! $res) {
        logger(" E> No se puede crear el fichero {$fileName}.kmz");
        exit(-1);
    }
    $zip->addFromString($radarWithFL . ".kml", $content);
    $zip->close();

    return;
}



/**
 * Funcion para crear una carpeta con los resultados para cada radar
 * 
 * @param string $ruta  (ENTRADA)
 * @return boolean para comprobar si la funcion a tenido exito o no (SALIDA)
 */
function crearCarpetaResultados($ruta)
{

    if (!is_dir($ruta)) {
        logger(" V> Creando carpeta >{$ruta}<");
        //clearstatcache();
        //$ruta = $ruta ."/". $radar['site'] . "/"; // /home/eval/berta/RESULTADOS/LE_VALLADOLID 
        if (mkdir($ruta, BERTA_PERMISOS, true)) {
            logger(" V> Carpeta >{$ruta}< creada correctamente");
            return true;
        } else {
            logger(" E> Error creando carpeta >{$ruta}<");
            exit(-1);
            // return false;
        }
    }

    return true;
}

/*
 * Helper de creaKML
 */
function KML_get_rgb_from_coverageLevel(string $coverageLevel, string &$coverageLevelAppend = ""): string
{
    switch ($coverageLevel) {
        case "unica":
            $rgb = "7d00ff00";
            $coverageLevelAppend = "";
            break;         // igual que mono
        case "mono":
            $rgb = "7d00ff00";
            $coverageLevelAppend = "";
            break;
        // case "mono": $rgb = "e6ff9724"; break;          // Rascal
        case "doble":
            $rgb = "7dff0000";
            break;
        // case "doble": $rgb = "e63559a5"; break;         // Rascal
        case "triple":
            $rgb = "7dffff00";
            break;
        // case "triple": $rgb = "e69977de"; break;        // Rascal
        case "cuadruple":
            $rgb = "7d0000ff";
            break;
        // case "cuadruple": $rgb = "e67bf600"; break;     // Rascal
        case "quintuple":
            $rgb = "7dff00ff";
            break;
        case "sextuple":
            $rgb = "7d00ffff";
            break;
        default:
            debug_print_backtrace(); die("deprecated " . __FUNCTION__ . " in " . __FILE__ . " at line " . __LINE__ . PHP_EOL);
            // $rgb = "7d00ffff";
            // break;
    }

    return $rgb;
}

/*
 * Para multicoberturas
 * Si necesitamos crear un KMZ de una multicobertura, crearemos carpeta a carpeta usando
 * esta función. Luego volcaremos todo de golpe, porque la estructura es compleja.
 */
function KML_get_placemarks($listaContornos, $radarName, $rutas, $nivelVuelo, $altMode, $appendToFilename = "", $coverageLevel = 'mono', $disableKmz = true)
{
    debug_print_backtrace(); die("deprecated " . __FUNCTION__ . " in " . __FILE__ . " at line " . __LINE__ . PHP_EOL);    
    logger(" D> Nivel de cobertura: $coverageLevel");

    $altitude_meters = $nivelVuelo * 100.0 * BERTA_FEET_TO_METERS;
    $rgb = KML_get_rgb_from_coverageLevel($coverageLevel);

    if (is_array($appendToFilename)) {
        $appendToFilename = "-" . implode("_", $appendToFilename);
    }

    if (is_array($radarName)) {
        $radarWithFL = implode(",", $radarName) . "-" .
            $coverageLevel . "-FL" .  $nivelVuelo . $appendToFilename;
    } else {
        $radarWithFL = $radarName . "-FL" .  $nivelVuelo . $appendToFilename;
    }

    if (false) {
        print "nivelVuelo: " . $nivelVuelo . PHP_EOL;
        print "radarWithFL: " . $radarWithFL . PHP_EOL;
        print "rutas: " . print_r($rutas, true) . PHP_EOL;
    }

    if (0 == count($listaContornos)) {
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
    foreach ($listaContornos as $contorno1) {
        $num_puntos = 0;
        $polygon = array();
        if (isset($contorno1['polygon'])) {
            $polygon = extrae_puntos_contorno($contorno1['polygon'], $altitude_meters);
            $num_puntos += count($contorno1['polygon']);
        }

        // un polígono puede tener n poligonos dentro que representen n agujeros.
        $inside = array();
        if (isset($contorno1['inside'])) {
            foreach ($contorno1['inside'] as $contorno2) {
                $inside[] = extrae_puntos_contorno($contorno2['polygon'], $altitude_meters);
                $num_puntos += count($contorno2['polygon']);
            }
        }

        // $polygon tiene una lista de puntos, mientras que inside tiene
        // una lista de polígonos, cada uno con una lista de puntos.
        $placemarks[] = KML_format_placemarks(
            $radarWithFL . "_{$i}" . " (" . round($contorno1['area'], 2) . "km^2, " . $num_puntos . "ptos)",
            $polygon,
            $inside,
            $rgb
        );
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
 * Helper de KML_get_placermarks para multicoberturas
 * Genera un array de placemarks, cada uno con su polígono y sus agujeros, y
 * un estilo basado en el color rgb
 */
function KML_format_placemarks($name, $polygon, $inside, $rgb)
{
    debug_print_backtrace(); die("deprecated " . __FUNCTION__ . " in " . __FILE__ . " at line " . __LINE__ . PHP_EOL);    
    // polygon es una lista de puntos que determinan un polígono
    $outer_coordinates = "";
    foreach ($polygon as $points) {
        $outer_coordinates .= "{$points[1]},{$points[0]},{$points[2]} ";
    }

    $inner = "";
    foreach ($inside as $poly) {
        $inner_coordinates = "";
        foreach ($poly as $points) {
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
 * Helper de KML_get_placemarks para multicobertura
 * Genera un KML completo usando los placemarks por cobertura
 */
function KML_create_from_placemarks($coverages_per_levels, $padded_FL, $file_name)
{
    debug_print_backtrace(); die("deprecated " . __FUNCTION__ . " in " . __FILE__ . " at line " . __LINE__ . PHP_EOL);            
    $coverage_levels = array("unica", "mono", "doble", "triple", "cuadruple", "quintuple", "sextuple");
    $kml_styles = "";
    foreach ($coverage_levels as $level) {
        $rgb = KML_get_rgb_from_coverageLevel($level);
        $kml_styles .= "" .
            "	<Style id=\"transparentPoly{$rgb}\">
	    <LineStyle><width>1.5</width></LineStyle>
	    <PolyStyle><color>{$rgb}</color></PolyStyle>
	</Style>
";
    }

    $kml_header = "" .
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
    foreach ($coverages_per_levels as $level => $sensores) {

        $kml_folder_header = "" .
            "	<Folder>
	    <name>Nivel $level</name>
	    <open>0</open>
";
        $kml .= $kml_folder_header;
        // para cada uno de los grupos de los radares, habrá n polígonos/placemarks
        $i = 0;
        foreach ($sensores as $nombre => $placemarks) {
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
function KML_placemarks_in_Folders($radarWithFL, $polygons, $rgb, $altMode)
{
    debug_print_backtrace(); die("deprecated " . __FUNCTION__ . " in " . __FILE__ . " at line " . __LINE__ . PHP_EOL);    
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
    foreach ($polygons as &$polygon) {
        $kmlOuter = "";
        //$polygon['polygon'] = ramer_douglas_peucker($polygon['polygon'], BERTA_RAMER_DOUGLAS_PEUCKER_PRECISION);
        foreach ($polygon['polygon'] as &$p) {
            $kmlOuter .= $p[1] . "," . $p[0] . "," . $p[2] . " ";
        }
        $kml .= $kmlPolygonHeader . $kmlOuter . $kmlPolygonFooter_1;
        foreach ($polygon['inside'] as &$polygons_inside) {
            $kmlInner = "";
            foreach ($polygons_inside['polygon'] as &$p_inside) {
                $kmlInner .=  $p_inside[1] . "," . $p_inside[0] . "," . $p_inside[2] . " ";
            }
            if ("" != $kmlInner) {
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
function fromPolygons2KML_One_Folder_Per_Content($polygons, $radarWithFL, $rgb, $altMode)
{
    debug_print_backtrace(); die("deprecated " . __FUNCTION__ . " in " . __FILE__ . " at line " . __LINE__ . PHP_EOL);
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
    foreach ($polygons as &$polygon) {
        $kmlOuter = "";
        //$polygon['polygon'] = ramer_douglas_peucker($polygon['polygon'], BERTA_RAMER_DOUGLAS_PEUCKER_PRECISION);
        foreach ($polygon['polygon'] as &$p) {
            $kmlOuter .= $p[1] . "," . $p[0] . "," . $p[2] . " ";
        }
        $kml .= $kmlPolygonHeader . $kmlOuter . $kmlPolygonFooter_1;
        foreach ($polygon['inside'] as &$polygons_inside) {
            $kmlInner = "";
            foreach ($polygons_inside['polygon'] as &$p_inside) {
                $kmlInner .=  $p_inside[1] . "," . $p_inside[0] . "," . $p_inside[2] . " ";
            }
            if ("" != $kmlInner) {
                $kml .= $kmlInnerHeader . PHP_EOL . $kmlInner . PHP_EOL . $kmlInnerFooter;
            }
        }
        $kml .= $kmlPolygonFooter_2;
    }
    $kml .= $kmlFooter_Content;
    return $kml;
}

/**
 * Genera ficheros para comparar la lista de obstáculos en PHP con la de Matlab
 *
 */
function generateMatlabFiles($radar, $rutaResultados)
{
    debug_print_backtrace(); die("deprecated " . __FUNCTION__ . " in " . __FILE__ . " at line " . __LINE__ . PHP_EOL);
    $rutaTerrenos = $rutaResultados . "Radares_Terrenos" . DIRECTORY_SEPARATOR;
    $rutaCoordenadas = $rutaResultados . "Radares_Coordenadas" . DIRECTORY_SEPARATOR;

    print "Generando fichero de Matlab para " .
        "[" . $radar['radar'] . "=>" . $radar['screening']['site'] . "]" . PHP_EOL;

    crearCarpetaResultados($rutaTerrenos);
    crearCarpetaResultados($rutaCoordenadas);

    if (0 == strlen($radar['screening_file']))
        return;

    @unlink($rutaTerrenos . $radar['screening_file']['site'] . ".txt");
    if (false === copy($radar['screening_file'], $rutaTerrenos . $radar['screening']['site'] . ".txt"))
        die("ERROR: copiando " . $radar['screening_file'] . " a " . $rutaTerrenos . $radarOriginal['site'] . ".txt" . PHP_EOL);

    $coordenadas = $radar['screening']['site'] . "-Latitud=" . $radar['lat'] . ";\r\n" .
        $radar['screening']['site'] . "-Longitud=" . $radar['lon'] . ";\r\n" .
        $radar['screening']['site'] . "-Range=" . ($radar['range'] / MILLA_NAUTICA_EN_METROS) . ";";

    @unlink($rutaCoordenadas . $radarOriginal['site'] . ".txt");
    if (false === file_put_contents($rutaCoordenadas . $radar['screening']['site'] . ".txt", $coordenadas))
        die("ERROR: escribiendo " . $rutaCoordenadas . $radar['screening']['site'] . ".txt" . PHP_EOL);

    print "INFO NOMBRE FICHERO: " . $rutaTerrenos . $radar['screening']['site'] . ".txt" . PHP_EOL;
    return true;
}
