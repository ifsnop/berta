<?php
/**
 * Comprueba el código de los Cambios 6-8 contra la referencia: cruces idénticos en todos los radares y niveles
 * (con paridad correcta) y polígonos frente a la verdad de referencia por muestreo.
 * Sólo tiene sentido con el Cambio 6 aplicado (calcula_cruces, intervalos_desde_cruces, destino).
 * Uso: php tests/measurements/patched_vs_prototype.php [radar1,...] 2>/dev/null
 * Resultado en la revisión (copia parcheada): 1,87 M listas de cruces sin diferencias; 0,00–0,02 % en polígonos.
 */
require __DIR__.'/bootstrap.php'; require __DIR__.'/../prototype/reference.php';
requires('calcula_cruces', 'added by Change 6C');
function inside(array $rings, float $la, float $lo): bool { $in=false; foreach ($rings as $ring) if (pointInPolygon($la,$lo,$ring,count($ring))) $in=!$in; return $in; }
// 1) calcula_cruces == fx reference crossings, all radars, FL1..400
$radars = explode(',', $argv[1] ?? 'paracuellos1,paracuellos2,alcolea,barajas,canchoblanco,erillas,monflorite,valdespina,valladolid'); $diff=0; $parity=0; $n=0;
foreach ($radars as $name) { $r=loadRadar($name); $Rk=$r['screening']['radioTerrestreAumentado']; $hA=$r['screening']['towerHeight']+$r['screening']['terrainHeight']; $pf=fx_perfil($r,false);
  for ($fl=1;$fl<=400;$fl++) { $flm=$fl*100*BERTA_FEET_TO_METERS; $a=calcula_cruces($r,$flm); $b=fx_intersecciones(fx_obstaculos($r,$pf,$flm),$flm+$Rk,$Rk);
    foreach ($a as $az=>$l) { $n++; if (count($l)!=count($b[$az])) $diff++; else foreach ($l as $i=>$v) if (abs($v-$b[$az][$i])>1e-9) { $diff++; break; } if ((count($l)%2==1)!==($flm>=$hA)) $parity++; } } }
printf("1) crossings: %d radar-azimuth-levels, %d differ from prototype, %d parity errors\n", $n, $diff, $parity);
// 2) polygons vs ground truth (intervals) by point sampling
mt_srand(3);
foreach ([['paracuellos1',20],['paracuellos1',23],['canchoblanco',20],['barajas',19],['valdespina',25],['paracuellos1',100],['paracuellos1-psr',100],['monflorite',300]] as [$name,$fl]) {
  $r=loadRadar($name); $flm=$fl*100*BERTA_FEET_TO_METERS; $hA=$r['screening']['towerHeight']+$r['screening']['terrainHeight']; $above=$flm>=$hA;
  ob_start(); $poly = calculosFL($r,(float)$fl,sprintf('%03d',$fl),false); ob_end_clean(); if ($poly===false) $poly=[];
  $iv = intervalos_desde_cruces(calcula_cruces($r,$flm), $above); $N=count($iv); $step=360/$N; $maxd=1; foreach ($iv as $l) foreach ($l as $x) $maxd=max($maxd,$x[1]);
  $bad=0; $S=5000;
  for ($i=0;$i<$S;$i++) { $d=$maxd*1.05*sqrt(mt_rand()/mt_getrandmax()); $az=360*mt_rand()/mt_getrandmax(); [$la,$lo]=destino($r['lat_rad'],$r['lon_rad'],$d*1852,deg2rad($az));
    $j=((int)floor(($az+$step/2)/$step))%$N; $exp=false;
    if ($above && count(array_filter($iv, fn($l)=>count($l)!=1))==0) { // star: interpolate between azimuth centres
      $j0=(int)floor($az/$step)%$N; $j1=($j0+1)%$N; $f=($az-$j0*$step)/$step; $rr=$iv[$j0][0][1]*(1-$f)+$iv[$j1][0][1]*$f; $exp = abs($d-$rr) > 0.05*$maxd/100 ? $d < $rr : inside($poly,$la,$lo);
    } else foreach ($iv[$j] as [$s,$e]) if ($d>=$s && $d<=$e) $exp=true;
    if (inside($poly,$la,$lo)!==$exp) $bad++; }
  printf("2) %-16s FL%03d %s: %d rings, %d vertices, mismatch vs ground truth %.2f%%\n",$name,$fl,$above?'above':'below',count($poly),array_sum(array_map('count',$poly)),100*$bad/$S);
}
