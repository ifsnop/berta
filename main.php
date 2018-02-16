<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

// INCLUSIÓN DE FICHEROS
include 'cargarDatosTerreno.php';
include 'cargarDatosCoordenadas.php';
include 'funcionesAuxiliares.php';
include 'calculos.php';
include 'guardar.php';

// DEFINICIÓN DE CONSTANTES
CONST RADIO_TERRESTRE = 6371000;
CONST MILLA_NAUTICA_EN_METROS = 1852; // metros equivalentes a 1 milla nautica

/*
$lugares = explode(" ", "aitana alcolea alicante aspontes auchlias barajas barcelona begas biarritz canchoblanco eljudio erillas espineiras foia fuerteventura gazules girona grancanaria inoges lapalma malaga1 malaga2 monflorite montejunto montpellier motril palmamallorca paracuellos1 paracuellos2 penaschache penaschachemil portosanto pozonieves randa sierraespuna soller solorzano taborno tenerifesur turrillas valdespina valencia valladolid villatobas");
foreach($lugares as $l) {
    print "php main.php $l > logs/$l.log &" . PHP_EOL;
}
exit();
*/
/*
print roundE("40.1") . PHP_EOL;
print roundE("1") . PHP_EOL;
print roundE("32.123456789") . PHP_EOL;
print roundE("2.123456789") . PHP_EOL;
print roundE("2.12345678") . PHP_EOL;
*/
programaPrincipal();
exit(0);

function programaPrincipal(){
    global $argv, $argc;

    $path = "/home/eval/%rassv6%/spain.tsk";
    $rutaResultados = "." . DIRECTORY_SEPARATOR . "RESULTADOS" . DIRECTORY_SEPARATOR;
    $radioTerrestreAumentado = 0;
    $poligono = false;
    $ordenarPorRadar = false;
    $lugares = explode(" ", "aitana alcolea alicante aspontes auchlias barajas barcelona barcelona-psr begas begas-psr biarritz canchoblanco eljudio eljudio-psr erillas espineiras espineiras-psr foia fuerteventura gazules girona grancanaria grancanaria-psr inoges lapalma malaga1 malaga2 malaga2-psr monflorite montejunto montpellier motril palmamallorca palmamallorca-psr paracuellos1 paracuellos1-psr paracuellos2 paracuellos2-psr penaschache penaschachemil portosanto pozonieves randa randa-psr sierraespuna soller solorzano taborno tenerifesur tenerifesur-psr turrillas valdespina valencia valencia-psr valladolid villatobas");
    $altMode = altitudeModetoString($altitudeMode = 0);
    $infoCoral = getRadars($path, $parse_all = true);

    $flMin = 1;
    $flMax = 400;
    $paso = 1;

    if ( $argc > 1 ){ 
        $lugares = array();
        for($i = 1; $i < $argc; $i++) {
            $lugares[] =$argv[$i];
        }
    }

    $op = 1;
    do{
        // $op = menu();
        switch ($op) {
	case 0:
	    echo "Hasta la vista!". PHP_EOL;
	    break;
	case 1:
    	    //pedirDatosUsuario($flMin, $flMax, $paso, $altitudeMode, $poligono, $lugares, $ordenarPorRadar);
            $op = 0;
            // para probar con una distancia más pequeña y forzar alcance a 20NM
	    // $infoCoral['canchoblanco']['secondaryMaximumRange'] = 20;
	    // recorremos todas las localizaciones que nos ha dado el usuario
            foreach($lugares as $lugar) {
                $lugar = strtolower($lugar);
                // carga el fichero de screening en memoria
                // tener en cuenta que si es un $lugar acabado en psr, hay que poner el range del psr
                $range = $infoCoral[$lugar]['secondaryMaximumRange'];
                if ( false !== strpos($lugar, "psr") ) {
                    print "cargando alcance del psr" . PHP_EOL;
                    $range = $infoCoral[$lugar]['primaryMaximumRange'];
                }

		$radar = cargarDatosTerreno(
		    $infoCoral[$lugar],
		    $range
		);

                if (false) {
                    generateMatlabFiles($radar, $rutaResultados);
                    continue;
                }

		// print_r($radar);
	        // para probar con una distancia más pequeña y forzar alcance a 20NM
		// $radar['screening']['range'] = 20*1852;

		for ($fl = $flMin; $fl <= $flMax; $fl += $paso) {
                    $nivelVuelo = str_pad( (string)$fl,3,"0", STR_PAD_LEFT );
		    if ( $ordenarPorRadar ) {
                        $ruta = $rutaResultados . $radar['screening']['site'] . DIRECTORY_SEPARATOR;
                    } else {
                        $ruta = $rutaResultados . $nivelVuelo . DIRECTORY_SEPARATOR;
                    }
                    crearCarpetaResultados($ruta);
		    print "Generando: ${fl}00 feet" . PHP_EOL;
		    calculosFL($radar, $fl, $ruta, $altMode, $ordenarPorRadar);
                } // for interno
	    } // foreach
	    break;
	} // switch
    } while ($op != 0);

    return;
}

/*
 * @param string $altMode cadena para que el KML utilice la altura como relativa o absoluta...
 * @param bool $ordenarPorRadar para guardar por directorios por nivel de vuelo o por nombre de radar
 */
function calculosFL($radar, $fl, $ruta, $altMode, $ordenarPorRadar) {

    $hA = $radar['screening']['towerHeight'] + $radar['screening']['terrainHeight'];
    $flm = $fl*100*FEET_TO_METERS; // fl en metros
    $nivelVuelo = str_pad((string)$fl,3,"0", STR_PAD_LEFT);

    // DISTINCIÓN DE CASOS 
    if ( $flm >= $hA ) { // CASO A (nivel de vuelo por encima de la posición del radar)
        $angulosApantallamiento = array();
	$distanciasCobertura = array();
        calculosFLencimaRadar($radar, $flm, $angulosApantallamiento, $distanciasCobertura);
	$coordenadasGeograficas = calculaCoordenadasGeograficasA($radar, $distanciasCobertura, $flm);
	crearKML($coordenadasGeograficas, $radar, $ruta, $fl, $altMode, $ordenarPorRadar);
    } else { // CASO B (nivel de vuelo por debajo de la posición del radar)
        print "[calculosFLdebajoRadar]";
	calculosFLdebajoRadar($radar, $flm);
	print "[generacionMallado]";
        $malla = generacionMallado($radar);
        print "[mallaMarco]";
	$mallaGrande = mallaMarco($malla);
	print "[determinaContornos]";
	determinaContornos($radar, $mallaGrande, $flm, $listaContornos);
	if ( 0 == count($listaContornos) ) {
	    print "INFO: No se genera KML/PNG/TXT porque no existe cobertura al nivel de vuelo FL" . $nivelVuelo . PHP_EOL;
	    return;
	}
	//printMalla($malla);
        storeMallaAsImage($malla, $ruta . $radar['screening']['site'] . "_FL" . $nivelVuelo);
        storeListaObstaculos($ruta, $radar, $nivelVuelo);
	print "[calculaCoordenadasGeograficasB]";
	calculaCoordenadasGeograficasB($radar, $flm, $listaContornos);
	print "[crearKmlB]" . PHP_EOL;
    	crearKmlB($listaContornos, $radar, $ruta, $fl, $altMode, $ordenarPorRadar);
    }
    return;
}

function storeListaObstaculos($ruta, $radar, $nivelVuelo) {

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

/*
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

/*
 * Redondea estilo MATLAB, dejando 10 números después del punto decimal.
 *
 */
function roundE($n) {
    $val = round($n, 10, PHP_ROUND_HALF_UP);
    return $val;
}
