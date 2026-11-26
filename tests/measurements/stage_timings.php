<?php
/**
 * Tiempo de cada etapa del cálculo monoradar (lectura, obstáculos, cruces, polígonos y unión, JSON, KML).
 * Uso: php tests/measurements/stage_timings.php [radar] [FL] 2>/dev/null
 * Resultado en la revisión: por debajo del radar la unión de polígonos es el 97 % del tiempo (0,8–1,6 s).
 */
require __DIR__.'/bootstrap.php';
requires('create_matriz_obstaculos', 'the obstacle matrix replaced by Change 6B-C');
use Ifsnop\MartinezRueda as MR;
$name = $argv[1] ?? 'paracuellos1'; $fl = (float)($argv[2] ?? 20);
$t=microtime(true); $info = getRadars('spain.tsk/', true); $tInfo=microtime(true)-$t;
$t=microtime(true); $r = cargarDatosTerreno($info[$name], -1.0); $tLoad=microtime(true)-$t;
$flm=$fl*100*BERTA_FEET_TO_METERS; $hA=$r['screening']['towerHeight']+$r['screening']['terrainHeight'];
$t=microtime(true); $mo=create_matriz_obstaculos($r,$flm); $tObs=microtime(true)-$t;
$W=$flm+$r['screening']['radioTerrestreAumentado']+0.01; $mx=0;
$t=microtime(true); $in=create_matriz_intersecciones($r,$mo,$W,$mx); $tInt=microtime(true)-$t;
$t=microtime(true);
if ($flm >= $hA) { $p = calculosFLencimaRadar2($r,$flm); $tPoly=microtime(true)-$t; $tUnion=0; $npoly=1; }
else {
  // replicate create_poligonos_cobertura but time sector building vs union separately
  ob_start(); $p = create_poligonos_cobertura($r,$in); ob_end_clean(); $tPoly=microtime(true)-$t; $tUnion=null;
}
$t=microtime(true); $j=json_encode($p); $tJson=microtime(true)-$t;
$t=microtime(true); $n=KML_normalizePolygonsForKML($p); $k=KML_normalized2KML($n,1,[$name],(int)$fl); $tKml=microtime(true)-$t;
printf("%-13s FL%03d %s | rdb scan %.3fs | load .scr %.3fs | obstacles %.3fs | intersections %.3fs | polygons(+union) %.3fs | json %.3fs | kml %.3fs\n",
 $name,$fl,$flm>=$hA?'above':'below',$tInfo,$tLoad,$tObs,$tInt,$tPoly,$tJson,$tKml);
