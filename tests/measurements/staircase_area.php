<?php
/**
 * Efecto del suelo escalonado (línea de visión más alta, E4) en el área, calculado exactamente desde los
 * intervalos por acimut, para varios niveles alrededor de la altura del radar.
 * Uso: php tests/measurements/staircase_area.php [radar1,...] 2>/dev/null
 * Resultado en la revisión: +0,2–3,8 % cerca de la altura del radar; ~0 desde FL100.
 */
require __DIR__.'/bootstrap.php'; require __DIR__.'/../prototype/reference.php';
// exact area from intervals (sector areas on the sphere): sum over bins of (step) * (cos(s/R)-cos(e/R)) * R^2
function ivArea(array $iv): float { $N=count($iv); $R=BERTA_RADIO_TERRESTRE/1000; $a=0; foreach ($iv as $l) foreach ($l as [$s,$e]) $a += (2*M_PI/$N)*(cos($s*1.852/$R)-cos($e*1.852/$R))*$R*$R; return $a; }
foreach (explode(',', $argv[1] ?? 'paracuellos1,paracuellos2,alcolea,barajas,canchoblanco,erillas,monflorite,valdespina,valladolid') as $name) {
  $r = loadRadar($name); $Rk=$r['screening']['radioTerrestreAumentado']; $hA=$r['screening']['towerHeight']+$r['screening']['terrainHeight'];
  $lin=fx_perfil($r,false); $st=fx_perfil($r,true); $flA=(int)ceil($hA/30.48); $cells=[];
  foreach (array_unique([max(1,$flA-6),max(1,$flA-2),$flA+1,$flA+5,$flA+15,100,250]) as $fl) { $flm=$fl*100*0.3048; $ab=$flm>=$hA;
    $a1=ivArea(fx_intervalos(array_values(fx_intersecciones(fx_obstaculos($r,$lin,$flm),$flm+$Rk,$Rk)),$ab));
    $a2=ivArea(fx_intervalos(array_values(fx_intersecciones(fx_obstaculos($r,$st,$flm),$flm+$Rk,$Rk)),$ab));
    $cells[] = sprintf("FL%03d %+6.1f%% (%+.0f km2)", $fl, $a1>0?100*($a2-$a1)/$a1:0, $a2-$a1); }
  printf("%-13s radar FL%03d | %s\n", $name, $flA, implode(' | ', $cells));
}
