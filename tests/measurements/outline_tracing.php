<?php
/**
 * Contorno trazado desde los intervalos (referencia fx_bandas) frente al cálculo actual: tiempo, vértices y
 * discrepancia con la verdad de referencia (intervalos por acimut) por muestreo de puntos.
 * Uso: php tests/measurements/outline_tracing.php [radar] [FL1,FL2,...] 2>/dev/null   (N = puntos, 6000)
 * Resultado en la revisión: 4–16 veces más rápido por debajo del radar; 0,00–0,02 % frente a 0,02–0,2 % (E3, S1).
 */
// Compare current mono pipeline vs fx pipeline: time, vertex count, and accuracy vs ground truth (per-azimuth intervals)
require __DIR__.'/bootstrap.php'; require __DIR__.'/../prototype/reference.php';
function inside(array $rings, float $la, float $lo): bool { $in=false; foreach ($rings as $ring) if (pointInPolygon($la,$lo,$ring,count($ring))) $in=!$in; return $in; }
function bearingDist(float $la1,float $lo1,float $la2,float $lo2): array { $p1=deg2rad($la1);$p2=deg2rad($la2);$dl=deg2rad($lo2-$lo1);
  $az=rad2deg(atan2(sin($dl)*cos($p2), cos($p1)*sin($p2)-sin($p1)*cos($p2)*cos($dl))); if($az<0)$az+=360;
  $d=acos(max(-1,min(1,sin($p1)*sin($p2)+cos($p1)*cos($p2)*cos($dl))))*BERTA_RADIO_TERRESTRE/1852; return [$az,$d]; }
$name = $argv[1] ?? 'paracuellos1'; $fls = array_map('intval', explode(',', $argv[2] ?? '20,22,23,26,50,150'));
$r = loadRadar($name); $Rk=$r['screening']['radioTerrestreAumentado']; $hA=$r['screening']['towerHeight']+$r['screening']['terrainHeight'];
$perfil = fx_perfil($r, false);
foreach ($fls as $fl) {
  $flmOld = $fl*100*BERTA_FEET_TO_METERS; $flm=$fl*100*0.3048; $above=$flm>=$hA;
  $t=microtime(true); ob_start(); try { $old = calculosFL($r,(float)$fl,sprintf('%03d',$fl),false); } catch (Throwable $e) { $old = 'EXC'; } ob_end_clean(); $tOld=microtime(true)-$t;
  $t=microtime(true);
  $cross = fx_intersecciones(fx_obstaculos($r,$perfil,$flm), $flm+$Rk, $Rk);
  $iv = fx_intervalos(array_values($cross), $above);
  $new = fx_bandas($r, $iv);
  $tNew=microtime(true)-$t;
  // ground truth sampling within max range
  $maxd=0; foreach ($iv as $l) foreach ($l as $x) $maxd=max($maxd,$x[1]); $maxd=max($maxd,1)*1.05;
  mt_srand(7); $N=(int)(getenv('N')?:6000); $bo=0;$bn=0;$cov=0; $step=360/count($iv);
  for ($i=0;$i<$N;$i++) {
    $d=$maxd*sqrt(mt_rand()/mt_getrandmax()); $az=360*mt_rand()/mt_getrandmax();
    [$la,$lo]=fx_destino($r['lat_rad'],$r['lon_rad'],$d*1852,deg2rad($az));
    [$az2,$d2]=bearingDist($r['lat'],$r['lon'],$la,$lo); $j=((int)floor(($az2+$step/2)/$step))%count($iv);
    $exp=false; foreach ($iv[$j] as [$s,$e]) if ($d2>=$s && $d2<=$e) $exp=true; if($exp)$cov++;
    if (is_array($old) && inside($old,$la,$lo)!==$exp) $bo++; if (inside($new,$la,$lo)!==$exp) $bn++;
  }
  $vo = is_array($old)? array_sum(array_map('count',$old)) : 0; $vn=array_sum(array_map('count',$new));
  printf("%-13s FL%03d %s | current %6.3fs %5d vtx mismatch %5.2f%% %s| fixed %6.3fs %5d vtx mismatch %5.2f%% | speedup x%.0f\n",
    $name,$fl,$above?'above':'below',$tOld,$vo,is_array($old)?100*$bo/$N:0, is_array($old)?'':"($old) ", $tNew,$vn,100*$bn/$N,$tOld/max($tNew,1e-6));
}
