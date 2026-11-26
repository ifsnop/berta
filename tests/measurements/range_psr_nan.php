<?php
/**
 * Alcance: PSR, alcance forzado (-m) y ángulo de máxima cobertura cuando el alcance no llega al nivel.
 * Uso: php tests/measurements/range_psr_nan.php 2>/dev/null
 * Resultado en la revisión (código original): PSR con alpha NaN y sin cobertura (M1); -m 5 a FL400 NaN (M5).
 * Con los Cambios 2, 5b y 5c: PSR hasta 100 NM y alpha 0 en lugar de NaN.
 */
require __DIR__.'/bootstrap.php';
function maxDist(array $r, $polys): float { $m=0; foreach ($polys as $ring) foreach ($ring as $p) $m=max($m, hav([$r['lat'],$r['lon']], $p)); return $m/1852; }
foreach ([['paracuellos1',-1.0],['paracuellos1-psr',-1.0],['paracuellos1',60.0],['paracuellos1',5.0]] as [$n,$mr]) {
  $r = loadRadar($n, $mr);
  $alpha = calculaAnguloMaximaCobertura($r, 100*100*BERTA_FEET_TO_METERS);
  $p = @calculosFL($r, 100.0, '100', false);
  printf("%-17s maxrange_arg=%5.0f  r['range']=%10.1f  alpha_max=%s  farthest vertex at FL100 = %s NM\n", $n, $mr, $r['range'], var_export($alpha,true), $p===false?'none':round(maxDist($r,$p[0]===null?[]:$p),1));
}
// FL400 with 5NM forced range: range shorter than vertical distance
$r = loadRadar('paracuellos1', 5.0);
printf("FL400 & range 5NM: alpha_max=%s\n", var_export(calculaAnguloMaximaCobertura($r, 400*100*BERTA_FEET_TO_METERS), true));
