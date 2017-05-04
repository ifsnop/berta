<?php

$rgb = "ff00ff77";
$kml = array('rgb' => "ff00ff77",
    'kml_start' => '' .
'<?xml version="1.0" encoding="UTF-8"?>
<kml xmlns="http://www.opengis.net/kml/2.2">
<Document>
 <name>✈</name>
 <description>Live Aircraft Tracking</description>

 <Style id="airway_small"><LineStyle><width>3</width></LineStyle><BalloonStyle><text>$[name]$[description]</text></BalloonStyle></Style>
 <Style id="airway_normal"><LineStyle><width>5</width></LineStyle><BalloonStyle><text>$[name]$[description]</text></BalloonStyle></Style>
 <Style id="airway_highlight"><LineStyle><width>8</width></LineStyle><BalloonStyle><text>$[name]$[description]</text></BalloonStyle></Style>

 <Style id="LinePoly">
   <LineStyle><color>' . $rgb . '</color><width>4</width></LineStyle>
   <PolyStyle><color>7f00ff00</color></PolyStyle>
 </Style>

 <Style id="diamond_normal">
    <IconStyle><color>' . $rgb . '</color><scale>0.8</scale><Icon><href>http://maps.google.com/mapfiles/kml/shapes/open-diamond.png</href></Icon></IconStyle>
    <LabelStyle><scale>0.8</scale></LabelStyle>
    <BalloonStyle><text>$[name]$[description]</text></BalloonStyle>
 </Style>
 <Style id="diamond_highlight">
    <IconStyle><color>' . $rgb . '</color><scale>0.94</scale><Icon><href>http://maps.google.com/mapfiles/kml/shapes/open-diamond.png</href></Icon></IconStyle>
    <LabelStyle><scale>0.8</scale></LabelStyle>
    <BalloonStyle><text>$[name]$[description]</text></BalloonStyle>
 </Style>

 <Style id="airplane_normal">
    <IconStyle><color>' . $rgb . '</color><scale>0.8</scale><Icon><href>http://maps.google.com/mapfiles/kml/shapes/airports.png</href></Icon></IconStyle>
    <LabelStyle><scale>0.8</scale></LabelStyle>
    <BalloonStyle><text>$[name]$[description]</text></BalloonStyle>
 </Style>
 <Style id="airplane_highlight">
    <IconStyle><color>' . $rgb . '</color><scale>0.94</scale><Icon><href>http://maps.google.com/mapfiles/kml/shapes/airports.png</href></Icon></IconStyle>
    <LabelStyle><scale>0.8</scale></LabelStyle>
    <BalloonStyle><text>$[name]$[description]</text></BalloonStyle>
 </Style>

 <Style id="vehicle_normal">
    <IconStyle><color>' . $rgb . '</color><scale>0.8</scale><Icon><href>http://maps.google.com/mapfiles/kml/shapes/cabs.png</href></Icon></IconStyle>
    <LabelStyle><scale>0.8</scale></LabelStyle>
    <BalloonStyle><text>$[name]$[description]</text></BalloonStyle>
 </Style>
 <Style id="vehicle_highlight">
    <IconStyle><color>' . $rgb . '</color><scale>0.94</scale><Icon><href>http://maps.google.com/mapfiles/kml/shapes/cabs.png</href></Icon></IconStyle>
    <LabelStyle><scale>0.8</scale></LabelStyle>
    <BalloonStyle><text>$[name]$[description]</text></BalloonStyle>
 </Style>

 <Style id="txp_normal">
    <IconStyle><color>' . $rgb . '</color><scale>0.8</scale><Icon><href>http://maps.google.com/mapfiles/kml/shapes/triangle.png</href></Icon></IconStyle>
    <LabelStyle><scale>0.8</scale></LabelStyle>
    <BalloonStyle><text>$[name]$[description]</text></BalloonStyle>
 </Style>
 <Style id="txp_highlight">
    <IconStyle><color>' . $rgb . '</color><scale>0.94</scale><Icon><href>http://maps.google.com/mapfiles/kml/shapes/triangle.png</href></Icon></IconStyle>
    <LabelStyle><scale>0.8</scale></LabelStyle>
    <BalloonStyle><text>$[name]$[description]</text></BalloonStyle>
 </Style>

 <Style id="none_normal"><IconStyle><Icon></Icon></IconStyle><ListStyle></ListStyle><BalloonStyle><text>$[description]</text></BalloonStyle></Style>
 <Style id="none_highlight"><IconStyle><Icon></Icon></IconStyle><ListStyle></ListStyle><BalloonStyle><text>$[description]</text></BalloonStyle></Style>

 <StyleMap id="airway"><Pair><key>small</key><styleUrl>#airway_small</styleUrl></Pair><Pair><key>normal</key><styleUrl>#airway_normal</styleUrl></Pair><Pair><key>highlight</key><styleUrl>#airway_highlight</styleUrl></Pair></StyleMap>
 <StyleMap id="diamond"><Pair><key>normal</key><styleUrl>#diamond_normal</styleUrl></Pair><Pair><key>highlight</key><styleUrl>#diamond_highlight</styleUrl></Pair></StyleMap>
 <StyleMap id="none"><Pair><key>normal</key><styleUrl>#none_normal</styleUrl></Pair><Pair><key>highlight</key><styleUrl>#none_highlight</styleUrl></Pair></StyleMap>
 <StyleMap id="airplane"><Pair><key>normal</key><styleUrl>#airplane_normal</styleUrl></Pair><Pair><key>highlight</key><styleUrl>#airplane_highlight</styleUrl></Pair></StyleMap>
 <StyleMap id="vehicle"><Pair><key>normal</key><styleUrl>#vehicle_normal</styleUrl></Pair><Pair><key>highlight</key><styleUrl>#vehicle_highlight</styleUrl></Pair></StyleMap>
 <StyleMap id="txp"><Pair><key>normal</key><styleUrl>#txp_normal</styleUrl></Pair><Pair><key>highlight</key><styleUrl>#txp_highlight</styleUrl></Pair></StyleMap>

',

    'kml_end' => ''.
'</Document>
</kml>
',

    'height_tpl' => "".
"    <Placemark>
        <name>3D</name>
        <description></description>
        <styleUrl>#LinePoly</styleUrl>
        <LineString>
            <extrude>1</extrude>
            <tessellate>0</tessellate>
            <altitudeMode>absolute</altitudeMode>
            <coordinates>[@coordinates]</coordinates>
        </LineString>
    </Placemark>",

    'placemark_tpl' => "".
"    <Placemark targetId=\"[@id]\">
        <name>[@name]</name>
        <description>
            [@description]
        </description>
        <styleUrl>[@styleUrl]</styleUrl>
        <Point>
            <extrude>1</extrude>
            <tessellate>0</tessellate>
            <altitudeMode>[@altitudeMode]</altitudeMode>
            <coordinates>[@coordinates]</coordinates>
        </Point>
    </Placemark>",

    'folder_tpl' => "<Folder><name>[@name]</name><visibility>0</visibility><open>0</open>
[@heights]
[@placemarks]
</Folder>",

    'line_tpl' => "".
"<Placemark><name>[@name]</name>
    <Style><LineStyle><color>[@color]</color></LineStyle></Style>
    <styleUrl>#airway_small</styleUrl>
    <description>[@description]</description>
    <LineString><altitudeMode>absolute</altitudeMode><extrude>1</extrude>
    <coordinates>[@coordinates]</coordinates>
    </LineString>
</Placemark>",

);


// <Folder><name>alicante</name>
//    <Folder><name>A32</name>
//    <Placemark><name>A32_GDV_a_LOMAS_01</name>
//      <Style><LineStyle><color>dd0000ff</color></LineStyle></Style>
//      <styleUrl>#smallState</styleUrl>
//      <description>MIN DEFINIDO FL400</description>
//      <LineString><altitudeMode>absolute</altitudeMode><extrude>0</extrude>
//      <coordinates>-15.428888888889,28.076944444444,12200 -15.480428049787,28.007121591155,12200</coordinates>
//      </LineString>
//    </Placemark>
//    </Folder>
// </Folder>

function generateKMLPlots($config, $radar, $plots) {
global $kml;
/*
    [315] => Array(
        [acid] => 3
        [h] => 1365.1849414371
        [lon_deg] => 2.3670652809913
        [lat_deg] => 39.255630311454
    )
*/
    $acid_old = $plots[0]['acid'];
    $pinFolder = "";
    $pins = array();
    $acid = $ret = "";

    foreach($plots as $plot) {
        $acid = $plot['acid'];
        $pin = new Template($kml['placemark_tpl']);
        $pin->set('id', $acid);
        $pin->set('name', $acid);
        $pin->set('altitudeMode', "absolute"); // "clampToGround"
        $pin->set('styleUrl', "diamond"); // "airplane" // change last style to plane
        $pin->set('coordinates', "${plot['lon_deg']},${plot['lat_deg']},${plot['h']}");
        $pin->set('description', "<![CDATA[<html><body><br /><br /><table border='0'>" .
             "<tr><td>ModeA</td><td>" . $plot['modeA'] . "</td></tr>" .
             "<tr><td>FL</td><td>" . $plot['fl'] . "</td></tr>" .
             "<tr><td>Time</td><td>" . asterixDate($plot['time']) . "</td></tr>" .
             "</table></body></html>]]>"
        );

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
    $zip = new ZipArchive();
    if ( false === $zip->open(
        $fileName,
        ZIPARCHIVE::CREATE | ZIPARCHIVE::OVERWRITE) 
    ) {
        print "error can't open " . $fileName . PHP_EOL; exit;
    }

    $zip->addFromString($radar['radar'] . ".kml", $ret);
    $zip->close();

    return true;
}
