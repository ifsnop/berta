<?php
/**
 * Cuántas veces el tope del muro final mueve el punto fuera de la recta de alcance (E7).
 * Uso: php tests/measurements/wall_cap.php [radar1,...] 2>/dev/null
 * Resultado en la revisión: sólo monflorite (140 de 1800 combinaciones acimut-nivel), sin efecto visible.
 */
require __DIR__.'/bootstrap.php';
requires('create_muro', 'the wall construction');
// count azimuths/levels where create_muro's yi > yil branch fires (wall point moved off the ray)
foreach (explode(',', $argv[1] ?? 'paracuellos1,alcolea,canchoblanco,erillas,valdespina,monflorite') as $name) { $r=loadRadar($name); $hits=0; $tot=0; $alpha0=null;
  foreach ([10,100,200,300,400] as $fl) { $flm=$fl*100*BERTA_FEET_TO_METERS; $alpha=calculaAnguloMaximaCobertura($r,$flm); $m=tan(M_PI_2-$alpha);
    $Rk=$r['screening']['radioTerrestreAumentado']; $ya=$Rk+$r['screening']['towerHeight']+$r['screening']['terrainHeight'];
    foreach ($r['screening']['listaAzimuths'] as $obs) { $xb=$yb=0.0; foreach ($obs as $o) { if ($o['angulo']>$alpha) break; $rr=$Rk+$o['altura']; $xb=$rr*sin($o['angulo']); $yb=$rr*cos($o['angulo']); }
      $ret=create_muro($r,0.0,$ya,$xb,$yb,$m); $tot++; if ($ret!==false) { [$xi,$yi]=$ret; if ($yi > (BERTA_MAX_WALL_HEIGHT+$Rk)*sin(atan($m))) $hits++; } } }
  printf("%-13s wall cap moved off the ray in %d of %d azimuth-levels\n",$name,$hits,$tot); }
