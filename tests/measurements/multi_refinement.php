<?php
/**
 * Multiradar: algoritmo actual (todos los grupos, 2^N-1) frente al refinamiento incremental de la referencia
 * (fx_multi_refine): operaciones, tiempo y área de cada grupo. Sin caché de disco.
 * Uso: php tests/measurements/multi_refinement.php [FL] [radar1 radar2 ...] 2>/dev/null
 * Las coberturas monoradar se calculan con calculosFL() del código probado.
 * Resultado en la revisión (9 radares, FL150): 1323 operaciones y 68 s frente a 328 y 9,2 s; áreas idénticas (S3).
 */
require __DIR__.'/bootstrap.php'; require __DIR__.'/../prototype/reference.php';
use Ifsnop\MartinezRueda as MR;
$fl = sprintf('%03d', (int)($argv[1] ?? 150)); $sensors = array_slice($argv, 2) ?: explode(',', 'paracuellos1,alcolea,barajas,canchoblanco,erillas,monflorite,valdespina,valladolid,paracuellos2'); sort($sensors);
$cob = []; foreach ($sensors as $s) { ob_start(); $p = calculosFL(loadRadar($s), (float)$fl, $fl, false); ob_end_clean(); if ($p !== false) $cob[$s] = ['polygons' => $p]; }
// RDP cost (init_polygons)
$t=microtime(true); $ip = init_polygons($cob); $tInit=microtime(true)-$t; $mr=$ip['mr_polygons'];
$t=microtime(true); $mr2=[]; foreach ($cob as $s=>$c) $mr2[$s]=MR\Polygon::create()->fillFromArray($c['polygons']); $tNoRdp=microtime(true)-$t;
$vin = array_sum(array_map(fn($c)=>array_sum(array_map('count',$c['polygons'])),$cob));
printf("inputs: %d radars, %d vertices | init_polygons with RDP %.3fs, without RDP %.3fs\n", count($sensors), $vin, $tInit, $tNoRdp);
// current algorithm, no disk cache
$t=microtime(true); $ops=0; $inter=[]; $suma=[]; $groups=[];
for ($i=1;$i<=count($sensors);$i++) foreach ((new Combinations($sensors))->getCombinations($i,false) as $g) $groups[]=$g;
foreach ($groups as $g) { $ki=implode('^',$g); $ks=implode('+',$g);
  if (count($g)==1) { $inter[$ki]=$mr[$g[0]]; $suma[$ks]=$mr[$g[0]]; continue; }
  $sub=array_slice($g,0,-1); $last=end($g);
  $inter[$ki]=MR\Algorithm::intersect($inter[implode('^',$sub)],$mr[$last]); $suma[$ks]=MR\Algorithm::union($suma[implode('+',$sub)],$mr[$last]); $ops+=2; }
$cur=[]; foreach ($groups as $g) { $I=$inter[implode('^',$g)]; if ($I->numPoints==0) continue; $rest=array_values(array_diff($sensors,$g));
  $D = $rest ? MR\Algorithm::difference($I,$suma[implode('+',$rest)]) : $I; if ($rest) $ops++; if ($D->numPoints>0) $cur[implode(',',$g)]=$D; }
$tCur=microtime(true)-$t;
$t=microtime(true); $new = fx_multi_refine($mr, $ops2); $tNew=microtime(true)-$t;
// compare
$keys = array_unique(array_merge(array_keys($cur),array_keys($new))); sort($keys); $maxd=0; $tot=0;
foreach ($keys as $k) { $a=isset($cur[$k])?areaKm2($cur[$k]->getArray()):0; $b=isset($new[$k])?areaKm2($new[$k]->getArray()):0; $maxd=max($maxd,abs($a-$b)); $tot+=$a; }
printf("current: %4d boolean ops %7.2fs, %d non-empty groups | refinement: %4d ops %6.2fs, %d groups | max per-group area difference %.3f km2 of %.0f km2 total | speedup x%.1f\n",
  $ops,$tCur,count($cur),$ops2,$tNew,count($new),$maxd,$tot,$tCur/$tNew);
