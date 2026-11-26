<?php
/**
 * Precisión de las posiciones frente a la geodésica WGS84 (Vincenty): fórmula actual, esfera exacta y esfera
 * con radio local de Euler; coste por vértice. Incluye la comprobación de Vincenty con valores conocidos.
 * Uso: php tests/measurements/precision_wgs84.php 2>/dev/null
 * Resultado en la revisión: esfera 120–235 m a 50 NM y ~1,1 km a 250 NM; Euler no lo arregla (180 m en
 * diagonal); la esfera exacta es 2,7 veces más rápida que la fórmula actual (P1, P2).
 */
require __DIR__.'/bootstrap.php'; require __DIR__.'/../prototype/reference.php';
requires('calcula_vertices_interseccion', 'the vertex formula replaced by Change 6E');

$v1 = vincenty(0, 0, 111319.4908, M_PI / 2); $v2 = vincenty(0, 0, 110574.3886, 0);
printf("0) Vincenty check: 111319.4908 m east on the equator -> %.7f deg lon; 110574.3886 m north -> %.7f deg lat (both should be 1)\n", $v1[1], $v2[0]);
echo "1) Vertex formula vs WGS84 geodesic (Vincenty), radar at 40.5N -3.5E\n";
$lat=40.5; $lon=-3.5; $c=cos(M_PI_2-deg2rad($lat)); $s=sin(M_PI_2-deg2rad($lat));
foreach ([50,150,250] as $nm) { $e=['cur'=>0,'sph'=>0,'eul'=>0];
  for ($az=0.0;$az<360;$az+=1) { $ref=vincenty($lat,$lon,$nm*1852,deg2rad($az));
    [$p]=calcula_vertices_interseccion($nm*1852,deg2rad($az),null,$c,$s,deg2rad($lat),deg2rad($lon));
    $e['cur']=max($e['cur'],ellDist($p,$ref));
    $e['sph']=max($e['sph'],ellDist(fx_destino(deg2rad($lat),deg2rad($lon),$nm*1852,deg2rad($az)),$ref));
    $e['eul']=max($e['eul'],ellDist(fx_destino(deg2rad($lat),deg2rad($lon),$nm*1852,deg2rad($az),eulerR($lat,deg2rad($az))),$ref)); }
  printf("   %3d NM: current formula %6.0f m | exact sphere R=6371km %6.0f m | sphere with local (Euler) radius %5.1f m\n",$nm,$e['cur'],$e['sph'],$e['eul']); }

echo "2) Speed per vertex (1e6 calls)\n";
$n=1000000; $t=microtime(true); for($i=0;$i<$n;$i++) calcula_vertices_interseccion(100000.0+$i, ($i%720)*M_PI/360, null,$c,$s,deg2rad($lat),deg2rad($lon)); $t1=microtime(true)-$t;
$t=microtime(true); for($i=0;$i<$n;$i++) fx_destino(deg2rad($lat),deg2rad($lon),100000.0+$i,($i%720)*M_PI/360); $t2=microtime(true)-$t;
printf("   current %.2f us | exact sphere %.2f us\n", $t1, $t2);
echo "3) Feet constant: 0.30480370641307 = 1/3.2808; error at FL400 = ".round(40000*(0.30480370641307-0.3048),3)." m\n";
