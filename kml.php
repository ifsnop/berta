<?php

include 'template.php';
include 'calculos.php';




/*<?xml version="1.0" encoding="UTF-8"?>
<kml xmlns="http://www.opengis.net/kml/2.2" xmlns:gx="http://www.google.com/kml/ext/2.2" xmlns:kml="http://www.opengis.net/kml/2.2" xmlns:atom="http://www.w3.org/2005/Atom">
<Document>		<name>[@name]			</name>	
<Style id="transGreenPoly">		<LineStyle>			<width>1.5</width>		</LineStyle>		
<PolyStyle>			<color>7d00ff00</color>		</PolyStyle>	</Style>	<Placemark>		<name>[@name]		</name>	
	<styleUrl>#transGreenPoly</styleUrl>		<Polygon>			<extrude>1</extrude>			<altitudeMode>clampToGround</altitudeMode>	
	<outerBoundaryIs>	
	<LinearRing>			
	<coordinates>[@coordinates]

					</coordinates>				</LinearRing>			</outerBoundaryIs>		</Polygon>	</Placemark></Document></kml>*/

$rgb = "ff00ff77";
$kml = array('rgb' => $rgb,  'kml_start' => '' .

'<?xml version="1.0" encoding="UTF-8"?>
<kml xmlns="http://www.opengis.net/kml/2.2" xmlns:gx="http://www.google.com/kml/ext/2.2" xmlns:kml="http://www.opengis.net/kml/2.2" xmlns:atom="http://www.w3.org/2005/Atom">
	<Document>	
		<name>[@name]</name>
		// AQUI HAY QUE METER LA CONFIGURACION CON LA QU SE HA GENERADO !!!!! (comentario)
		<Style id="transGreenPoly">		
			<LineStyle>			
				<width>1.5</width>	
			</LineStyle>	
			<PolyStyle>	
				<color>. $rgb .</color>
			</PolyStyle>	
		 </Style>	
						
		<Style id="downArrowIcon">
			<IconStyle>
				<Icon>		
					<href>http://maps.google.com/mapfiles/kml/pal4/icon28.png</href>	
				</Icon>	
			</IconStyle>
		</Style>
			
		<Placemark>		
			<name>[@name]</name>	
			<styleUrl>#transGreenPoly</styleUrl>	
				<Polygon>		
					<extrude>1</extrude>	
					<altitudeMode>[@altitudeMode]</altitudeMode>
					<outerBoundaryIs>			
						<LinearRing>				
							<coordinates>
							</coordinates>				
						</LinearRing>			
					</outerBoundaryIs>		
				</Polygon>	
		</Placemark>
			
		<Placemark>		
			<name>[@name]</name>		
			<description>Floats a defined distance above the ground.</description>		
			<styleUrl>#downArrowIcon</styleUrl>		
			<Point>			
				<altitudeMode>[@altitudeMode]</altitudeMode>			
				<coordinates>
				</coordinates>		
			</Point>	
		</Placemark>',
		
 'kml_end' => ''.
'</Document>
</kml>'); // final del array
		

function generateKMLPlots($radar, $resultado) {
	global $kml;

	//$acid_old = $plots[0]['acid']; // plots es algo asi como lo que quiero sustituir 
	//$pinFolder = "";
	//$pins = array();
	//$acid = $ret = "";

	foreach($plots as $plot) {
		$acid = $plot['acid'];
		$pin = new Template($kml['placemark_tpl']); // GENERA UN NUEVO KML !!! 
		$pin->set('coodinates', $resultado); // le pasamos el array con nuestras coordenadas 
		 
		if ( $acid_old != $plot['acid'] ) {
			$pinsMerged = Template::merge($pins);
			$pinFolder = new Template($kml['folder_tpl']);
			$pinFolder->set('name', $acid_old);
			$pinFolder->set('placemarks', $pinsMerged);
			//print $pinFolder->output() . PHP_EOL;
			$ret .= $pinFolder->output() . PHP_EOL;
			$acid_old = $acid;
			$pins = array();
		}
		$pins[] = $pin;

	}


	$pinsMerged = Template::merge($pins);
	$pinFolder = new Template($kml['folder_tpl']);
	$pinFolder->set('name', $acid);
	$pinFolder->set('placemarks', $pinsMerged);
	$ret .= $pinFolder->output();

	$ret = $kml['kml_start'] . $ret . $kml['kml_end'];

	$fileName = $config['outputKMLDirectory'] . $radar['radar'] . ".kmz";

	// GENERACION DEL ZIP (KMZ) 
	$zip = new ZipArchive();
	if ( false === $zip->open($fileName, ZIPARCHIVE::CREATE | ZIPARCHIVE::OVERWRITE)){
			print "error can't open " . $fileName . PHP_EOL;
			exit;
	}

	$zip->addFromString($radar['radar'] . ".kml", $ret);
	$zip->close();

	return true;
}
		
  