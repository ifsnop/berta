<?php
/**
 * Precisión de calcula_vertices_interseccion() (fórmula de vértices actual) frente al problema directo
 * exacto sobre la esfera, para varias latitudes y distancias; también polarToLatLon() (cono).
 * Uso: php tests/measurements/vertex_accuracy.php 2>/dev/null
 * Resultado en la revisión: ~20 m (el desplazamiento de solape), 80 m a 250 NM a 40,5°N, 309 m a 60°N;
 * polarToLatLon exacta (0 m). Problemas P1 y M4.
 */
require __DIR__.'/bootstrap.php';
requires('calcula_vertices_interseccion', 'the vertex formula replaced by Change 6E');
// 1) calcula_vertices_interseccion vs exact destination, for several latitudes/azimuths/distances
$worst = 0; $rows=[];
foreach ([0.0, 28.0, 40.5, 43.5, 60.0] as $lat) foreach ([5.0, 50.0, 150.0, 250.0] as $nm) {
  $max = 0;
  for ($az=0.0; $az<360; $az+=0.5) {
    $lat_rad=deg2rad($lat); $lon=-3.5; $c=cos(M_PI_2-$lat_rad); $s=sin(M_PI_2-$lat_rad);
    [$p] = calcula_vertices_interseccion($nm*1852, deg2rad($az), null, $c, $s, $lat_rad, deg2rad($lon));
    $e = hav($p, dest($lat, $lon, $nm*1852, deg2rad($az)));
    $max = max($max, $e);
  }
  printf("lat %5.1f  dist %5.0fNM  max position error %9.1f m\n", $lat, $nm, $max);
}
// 2) polarToLatLon (cone) vs exact
$max=0; $lat=40.5; foreach ([1.0,5.0,10.0] as $nm) for ($az=0.0;$az<360;$az+=1) {
  $p = polarToLatLon(-3.5, $nm, $az, cos(M_PI_2-deg2rad($lat)), sin(M_PI_2-deg2rad($lat)));
  $max=max($max, hav($p, dest($lat,-3.5,$nm*1852,deg2rad($az))));
}
printf("polarToLatLon (cone) max error: %.3f m\n", $max);
// 3) tolerance constant
if (defined('BERTA_INTERSECTION_TOLERANCE_LIMIT_RAD')) printf("BERTA_INTERSECTION_TOLERANCE_LIMIT_RAD = %.3e rad = %.1f m on ground (name says 20 NM = 37040 m)\n", BERTA_INTERSECTION_TOLERANCE_LIMIT_RAD, BERTA_INTERSECTION_TOLERANCE_LIMIT_RAD*BERTA_RADIO_TERRESTRE);
