<?php
declare(strict_types=1);

const BERTA_PERMISOS = 0775;

/**
 *
 * Flujo de trabajo de las llamadas para generar KML, desde las distintas partes
 * del código que necesitan generar salida.
 *
 * La mono queda integrada junto con el resto de llamadas, antes se usaba CreaKML3/4
 *
 * mono: normalizePolygonsForKML ->                                      KML_normalized2KML ->                      KML_generate_full_kml -> creaCarpetaResultados -> KMZ_write
 * multi: multicobertura ->               KML_normalizePolygonsForKML -> KML_normalized2KML -> KML_create_folder -> KML_generate_full_kml -> creaCarpetaResultados -> KMZ_write
 * unica: multicobertura -> crea_unica -> KML_normalizePolygonsForKML -> KML_normalized2KML -> KML_create_folder -> KML_generate_full_kml -> creaCarpetaResultados -> KMZ_write
 *
 */

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
    $coverage_levels = array(0=>"unica", "mono", "doble", "triple", "cuadruple", "quintuple", "sextuple");
    $kml_styles = "";
    foreach ($coverage_levels as $i => $level) {
        $rgb = KML_get_rgb_from_coverageLevel($level);
        $kml_styles .=              "  <Style id=\"transparentPoly-{$level}\">" .
            PHP_EOL . "    <LineStyle><color>{$rgb['line']}</color><width>{$rgb['width']}</width></LineStyle>" .
            PHP_EOL . "    <PolyStyle><color>{$rgb['poly']}</color></PolyStyle>" .
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
function KML_normalized2KML(array $multi_polygons, string $coverageLevel, array $sensors, int $fl, string $altMode = "clampToGround")
{
    KML_check_coverage_levels($coverageLevel);
    $flm = round($fl * 100.0 * BERTA_FEET_TO_METERS, 2);
    $flWithPad = str_pad((string) $fl, 3, "0", STR_PAD_LEFT);
    $radarWithFl = implode(',', $sensors) . "-" . $flWithPad;
    $kml = "";

    $kmlPlacemark =    "  <Placemark>" .
        PHP_EOL . "    <name>{$radarWithFl}</name>" .
        PHP_EOL . "    <visibility>0</visibility>" .
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
function KML_normalizePolygonsForKML(array $polygons): array
{
    $items = [];

    foreach ($polygons as $poly) {
        if (!is_array($poly)) continue;
        if (count($poly) < 3) continue;

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

function KML_write(string $filename, string $FL, string $content, bool $disableKmz = false)
{

    if (true === $disableKmz || !class_exists('ZipArchive')) {
        // generar kml y volver
        logger(" V> Guardando fichero {$filename}{$FL}.kml");
        if (false === ($ret = @file_put_contents("{$filename}{$FL}.kml", $content))) {
            logger(" E> Problema al guardar {$filename}{$FL}.kml");
            exit(-1);
            //return false;
        }

        return true;
    }
    logger(" V> Guardando fichero {$filename}.kmz");

    $zip = new ZipArchive();
    $res = $zip->open($filename . ".kmz", ZIPARCHIVE::CREATE | ZIPARCHIVE::OVERWRITE);
    if (! $res) {
        logger(" E> No se puede crear el fichero {$filename}.kmz");
        exit(-1);
    }
    $zip->addFromString("{$filename}{$FL}.kml", $content);
    $zip->close();

    return;
}



/**
 * Funcion para crear una carpeta con los resultados para cada radar
 * 
 * @param string $ruta  (ENTRADA)
 * @return boolean para comprobar si la funcion a tenido exito o no (SALIDA)
 */
function crearCarpetaResultados(string $ruta)
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
function KML_get_rgb_from_coverageLevel(string $coverageLevel, string &$coverageLevelAppend = ""): array
{
    switch ($coverageLevel) {
        case "unica":
        case "mono":
            // $rgb = "e6ff9724"; // Rascal
            $rgb = ['poly' => "7d00ff00", 'line' => "80ffffff", 'width' => 1.5];
            break;
        case "doble":
            // $rgb = "e63559a5"; // Rascal
            $rgb = ['poly' => "99004C98", 'line' => "80ffffff", 'width' => 1.5];
            break;
        case "triple":
            // $rgb = "e69977de"; // Rascal
            $rgb = ['poly' => "997F00FF", 'line' => "80ffffff", 'width' => 1.5];
            break;
        case "cuadruple":
            // $rgb = "e67bf600"; // Rascal
            $rgb = ['poly' => "B30055FF", 'line' => "", 'width' => 0];
            break;
        default:  // más de cuadruple
            // $rgb = "e67bf600"; // Rascal
            $rgb = ['poly' => "B30055FF", 'line' => "", 'width' => 0];
    }
    return $rgb;
}
