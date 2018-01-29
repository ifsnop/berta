<?php

/**
 * Utilizando la lógica del SASS-C, busca un fichero recording_details.par y
 * obtiene los radares configurados para la evaluación. Luego busca en el
 * directorio donde estén definidos todos los radares y extrae, dependiendo
 * de $parse_all, o bien de todos o bien de sólo los definidos en la
 * evaluación, un array con la información.
 *
 * @param string $eval_dir Directorio donde está configurada la evaluación
 * @param bool   $parse_all Ignora recording_details.par y lee todos los
 *   radares definidos
 *
 * @return array Listado de radares configurado
 */
function getRadars($eval_dir, $parse_all = false) {

    $radars = array();

    print "cargando la información de los radares de >$eval_dir<";
    if ( false === $parse_all) {  
	// obtenemos el nombre del fichero que contiene todos los radares activos en la evaluación
	exec("/usr/bin/find $eval_dir -name  \"recording_details.par\" | grep -v \\.eva 2> /dev/null", $recording_details_file);
	if ( 0 == count($recording_details_file) ) {
            die("ERROR couldn't find recording_details.par inside $eval_dir" . PHP_EOL);
	}
	$recording_details_file = $recording_details_file[0]; // $eval_dir . "/recording_details.par";
	if ( false === ( $recording_details_content = file_get_contents($recording_details_file)) ) {
	    die("ERROR couldn't open $recording_details_file" . PHP_EOL);
	}
	// quita las terminaciones de línea msdos/unix y separa por líneas en un array
	$recording_details_content = preg_split("/[\r\n|\n\r|\n]/", $recording_details_content, NULL, PREG_SPLIT_NO_EMPTY);

        foreach( $recording_details_content as $recording_details_line ) { // por cada radar, abre el fichero .rdb correspondiente
	    if ( (($count1 = preg_match("/^RADAR_DATAFILE_NAME(\d+)\s+(\w+)/", $recording_details_line, $m1, PREG_OFFSET_CAPTURE)) !== FALSE) && ($count1>0) ) {
	        $name = $m1[2][0];
	        $sassc_id = $m1[1][0];
	        exec("/usr/bin/find -L $eval_dir -name \"${name}.rdb\" 2> /dev/null", $radar_rbk_file);
	        if ( 0 == count($radar_rbk_file) ) {
	            die("ERROR couldn't find ${name}.rdb inside $eval_dir" . PHP_EOL);
                }
	        $radar_rbk_file = $radar_rbk_file[0];
	        $eval_dir . "/radar_data.rbk/" . $name . ".rdb";
	        $radars = array_merge( $radars, parseRBKFile($radar_rbk_file, $name, $sassc_id) );
	    }
        }
    } else {
        exec("/usr/bin/find -L $eval_dir  -maxdepth 2 -name \"*.rdb\" 2> /dev/null", $radar_rbk_files);
	if ( 0 == count($radar_rbk_files) ) {
            die("ERROR couldn't find ${name}.rdb inside $eval_dir" . PHP_EOL);
        }
	foreach($radar_rbk_files as $radar_rbk_file) {
            $pathinfo = pathinfo($radar_rbk_file);
    	    $name = $pathinfo['filename'];
    	    if ( $name[0] == "%" )
    	        continue;
	    print ".";
            $radars = array_merge( $radars, parseRBKFile($radar_rbk_file, $name, -1) );
	}
	print PHP_EOL;
    }
    return $radars;
}

/**
 * Helper para getRadars(). Lee un fichero .rbk y lo devuelve en array,
 * realizando conversiones para los parámetros que lo necesiten.
 * (Convierte altitud total y posición a grados WGS-84)
 *
 * @param string $eval_dir Directorio donde está configurada la evaluación
 * @param string $name Nombre del radar
 * @param string $sassc_id Identificador del radar para el SASS-C
 *
 * @return array Listado de parámetros asociados a un radar
 */
function parseRBKFile($radar_rbk_file, $name, $sassc_id) {

    if ( false === ($radar_rbk_contents = file_get_contents($radar_rbk_file)) ) {
        die("ERROR couldn't file_get_contents from $radar_rbk_file" . PHP_EOL);
    }

    $lat = $lon = ""; $radarGroundAltitude = 0; $values = array(); $radar = array();
    // quita las terminaciones de línea msdos/unix y separa por líneas en un array
    $radar_rbk_contents = preg_split("/[\r\n|\n\r|\n]/", $radar_rbk_contents, NULL, PREG_SPLIT_NO_EMPTY);

    foreach($radar_rbk_contents as $line) {
        //print $line . PHP_EOL;
        if ( (($count2 = preg_match("/^radarLatitude: \"(\d+):(\d+):(\d+).(\d+)([N|S])\"/", $line, $m2, PREG_OFFSET_CAPTURE)) !== FALSE) && ($count2>0) ) {
            $lat = $m2[1][0] + $m2[2][0]/60 + ($m2[3][0] + $m2[4][0]/100)/3600;
	    if ($m2[5][0]=="N") $lat *=1; else if ($m2[5][0]=="S") $lat *=-1;
	    continue;
        }
        if ( (($count2 = preg_match("/^radarLongitude: \"(\d+):(\d+):(\d+).(\d+)([E|W])\"/", $line, $m2, PREG_OFFSET_CAPTURE)) !== FALSE) && ($count2>0) ) {
	    $lon = $m2[1][0] + $m2[2][0]/60 + ($m2[3][0] + $m2[4][0]/100)/3600;
	    if ($m2[5][0]=="E") $lon *=1; else if ($m2[5][0]=="W") $lon *=-1;
	    continue;
        }
        /*
        // Vamos a capturar las alturas en el grupo genérico, y luego
        // sumarlas cuando calculemos la cobertura
        if ( (($count2 = preg_match("/^radarGroundAltitude: \"([-+]?[0-9]*\.?[0-9]+)/", $line, $m2, PREG_OFFSET_CAPTURE)) !== FALSE) && ($count2>0) ) {
	    $radarGroundAltitude = $m2[1][0];
	    continue;
        }
	if ( (($count2 = preg_match("/^secondaryElectricalHeight: \"([-+]?[0-9]*\.?[0-9]+)/", $line, $m2, PREG_OFFSET_CAPTURE)) !== FALSE) && ($count2>0) ) {
	    $h += $m2[1][0];
	    continue;
	}
	if ( (($count2 = preg_match("/^primaryElectricalHeight: \"([-+]?[0-9]*\.?[0-9]+)/", $line, $m2, PREG_OFFSET_CAPTURE)) !== FALSE) && ($count2>0) ) {
	    $h += $m2[1][0];
	    continue;
	}
	*/
	// este grupo captura el resto de valores que no necesitan transformación
	if ( (($count2 = preg_match("/^(\w+): \"(.*)\"/", $line, $m2, PREG_OFFSET_CAPTURE)) !== FALSE) && ($count2>0) ) {
            $values[$m2[1][0]] = $m2[2][0];
	    continue;
	}
    }
    if ( !empty($lat) && !empty($lon) ) {
    	$radar[$name] = array(
            'radar' => $name,
	    'lat' => $lat,
	    'lon' => $lon,
	    'sassc_id' => $sassc_id
	);
	$radar[$name] = array_merge($radar[$name], $values);
    }
    // si en el fichero se definen dos radares (PSR y SSR, hay que devolver dos radares, no uno!)
    if ( false !== strpos($radar[$name]['radarType'], "primary") ) {
        $radar[$name . "-psr"] = $radar[$name];
        $radar[$name . "-psr"]['radar'] = $radar[$name . "-psr"]['radar'] . "-psr";
    }
    return $radar;
}
