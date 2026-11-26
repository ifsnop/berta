<?php
/**
 * Coste por vértice de la fórmula actual, de la esfera exacta y de Vincenty; tamaño del perfil con y sin
 * suelo escalonado.
 * Uso: php tests/measurements/vertex_cost.php 2>/dev/null
 * Resultado en la revisión: 0,86 / 0,31 / 1,48 µs por vértice; el perfil escalonado duplica los puntos.
 */
require __DIR__.'/bootstrap.php'; require __DIR__.'/../prototype/reference.php';
requires('calcula_vertices_interseccion', 'the vertex formula replaced by Change 6E');
$n=200000; $t=microtime(true); for($k=0;$k<$n;$k++) vincenty(40.5,-3.5,50000.0+$k,($k%720)*M_PI/360); $tv=(microtime(true)-$t)/$n*1e6;
$t=microtime(true); for($k=0;$k<$n;$k++) fx_destino(deg2rad(40.5),deg2rad(-3.5),50000.0+$k,($k%720)*M_PI/360); $ts=(microtime(true)-$t)/$n*1e6;
$t=microtime(true); for($k=0;$k<$n;$k++) calcula_vertices_interseccion(50000.0+$k,($k%720)*M_PI/360,null,cos(M_PI_2-deg2rad(40.5)),sin(M_PI_2-deg2rad(40.5)),deg2rad(40.5),deg2rad(-3.5)); $tc=(microtime(true)-$t)/$n*1e6;
printf("per vertex: current %.2f us | exact sphere %.2f us | WGS84 Vincenty %.2f us  -> for ~3,000 vertices per level: %.1f / %.1f / %.1f ms\n",$tc,$ts,$tv,$tc*3,$ts*3,$tv*3);
$r=loadRadar('canchoblanco'); foreach ([false,true] as $st) { $t=microtime(true); $p=fx_perfil($r,$st); $pts=array_sum(array_map('count',$p)); printf("profile %s: %.3fs, %d points\n", $st?'staircase':'linear', microtime(true)-$t, $pts); }
