<?php
/**
 * Le entra como parametros el nombre del radar con el que queremos trabajar ($name)
 * y la informacion de todos los radares y nos devuelve las coordeadas y el screening concretos para el radar seleccionado
 * @param array $infoCoral
 * @param string $name nombre del  lugar donde esta el radar 
 * @return array $coordenadas, array con la informacion que necesitamos para generar los kml del radar con el que estamos trabajando
 */
function cargarDatosCoordenadas($infoCoral, $name){
	
	$name = strtolower($name);
	
	$coordenadas = array(
			'longitud'  => $infoCoral[$name]['lon'],
			'latitud'   => $infoCoral[$name]['lat'],
			'screening' => $infoCoral[$name]['screening']
	);

	return $coordenadas;
}


/**
 * Utilizando la l�gica del SASS-C, busca un fichero recording_details.par y
 * obtiene los radares configurados para la evaluaci�n. Luego busca en el
 * directorio donde est�n definidos todos los radares y extrae, dependiendo
 * de $parse_all, o bien de todos o bien de s�lo los definidos en la
 * evaluaci�n, un array con la informaci�n.
 *
 * @param string $eval_dir Directorio donde est� configurada la evaluaci�n
 * @param bool   $parse_all Ignora recording_details.par y lee todos los
 *   radares definidos
 *
 * @return array Listado de radares configurado
 */
function getRadars($eval_dir, $parse_all = false) {
	
	$radars = array();

	if ( false === $parse_all) {  
		
		// obtenemos el nombre del fichero que contiene todos los radares activos en la evaluaci�n
		exec("/usr/bin/find $eval_dir -name  \"recording_details.par\" | grep -v \\.eva 2> /dev/null", $recording_details_file);
		if ( 0 == count($recording_details_file) ) {
			print "error couldn't find recording_details.par inside $eval_dir" . PHP_EOL;
			exit;
		}
		$recording_details_file = $recording_details_file[0]; // $eval_dir . "/recording_details.par";
		if ( false === ( $recording_details_content = file_get_contents($recording_details_file)) ) {
			print "error couldn't open $recording_details_file" . PHP_EOL;
			exit;
		}
		// quita las terminaciones de l�nea msdos/unix y separa por l�neas en un array
		$recording_details_content = preg_split("/[\r\n|\n\r|\n]/", $recording_details_content, NULL, PREG_SPLIT_NO_EMPTY);

		foreach( $recording_details_content as $recording_details_line ) { // por cada radar, abre el fichero .rdb correspondiente
			if ( (($count1 = preg_match("/^RADAR_DATAFILE_NAME(\d+)\s+(\w+)/", $recording_details_line, $m1, PREG_OFFSET_CAPTURE)) !== FALSE) && ($count1>0) ) {
				$name = $m1[2][0];
			
				$sassc_id = $m1[1][0];
				exec("/usr/bin/find -L $eval_dir -name \"${name}.rdb\" 2> /dev/null", $radar_rbk_file);
				
				if ( 0 == count($radar_rbk_file) ) {
					print "error couldn't find ${name}.rdb inside $eval_dir" . PHP_EOL;
					exit;
		}
		$radar_rbk_file = $radar_rbk_file[0];
		 $eval_dir . "/radar_data.rbk/" . $name . ".rdb";
		$radars = array_merge( $radars, parseRBKFile($radar_rbk_file, $name, $sassc_id) );

	}
}
} else {
	exec("/usr/bin/find -L $eval_dir -name \"*.rdb\" 2> /dev/null", $radar_rbk_files);
	if ( 0 == count($radar_rbk_files) ) {

		print "error couldn't find ${name}.rdb inside $eval_dir" . PHP_EOL;
		exit;
}
foreach($radar_rbk_files as $radar_rbk_file) {
	$pathinfo = pathinfo($radar_rbk_file);
	$name = $pathinfo['filename'];
	$radars = array_merge( $radars, parseRBKFile($radar_rbk_file, $name, -1) );
}

}
return $radars;
}

/**
 * Helper para getRadars(). Lee un fichero .rbk y lo devuelve en array,
 * realizando conversiones para los par�metros que lo necesiten.
 * (Convierte altitud total y posici�n a grados WGS-84)
 *
 * @param string $eval_dir Directorio donde est� configurada la evaluaci�n
 * @param string $name Nombre del radar
 * @param string $sassc_id Identificador del radar para el SASS-C
 *
 * @return array Listado de par�metros asociados a un radar
 */
function parseRBKFile($radar_rbk_file, $name, $sassc_id) {

	if ( false === ($radar_rbk_contents = file_get_contents($radar_rbk_file)) ) {
		print "error couldn't file_get_contents from $radar_rbk_file" . PHP_EOL;
		exit;
	}

	$lat = $lon = ""; $h = 0; $values = array(); $radars = array();
	// quita las terminaciones de l�nea msdos/unix y separa por l�neas en un array
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
		if ( (($count2 = preg_match("/^radarGroundAltitude: \"([-+]?[0-9]*\.?[0-9]+)/", $line, $m2, PREG_OFFSET_CAPTURE)) !== FALSE) && ($count2>0) ) {
			$h += $m2[1][0];
			continue;
		}
		if ( (($count2 = preg_match("/^secondaryElectricalHeight: \"([-+]?[0-9]*\.?[0-9]+)/", $line, $m2, PREG_OFFSET_CAPTURE)) !== FALSE) && ($count2>0) ) {
			$h += $m2[1][0];
			continue;
		}

		// este grupo captura el resto de valores que no necesitan transformaci�n
		if ( (($count2 = preg_match("/^(\w+): \"(.*)\"/", $line, $m2, PREG_OFFSET_CAPTURE)) !== FALSE) && ($count2>0) ) {
			$values[$m2[1][0]] = $m2[2][0];
			continue;
		}
	}
	if ( !empty($lat) && !empty($lon) ) {
		$radars[$name] = array(
				'radar' => $name,
				'lat' => $lat,
				'lon' => $lon,
				'h' => $h,
				'sassc_id' => $sassc_id
		);
		$radars[$name] = array_merge($radars[$name], $values);
	}

	return $radars;
}






// [$nombreRadar][Ruta al fichero de terrenos]

//print_r($infoCoral['valladolid']['screening']); // muestra la ruta donde esta el screening
//print_r($infoCoral['valladolid']['lat']); // muesta la latitud
//print_r($infoCoral['valladolid']['lon']); //  muestra la longitud
//print_r($infoCoral);

