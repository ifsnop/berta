<?php
/**
 * Barrido completo FL1-400 de un radar: código actual (calculosFL) frente al prototipo (perfil precalculado,
 * descarte rápido de segmentos y contorno por intervalos); comprueba además que los cruces coinciden.
 * Uso: php tests/measurements/sweep_speed.php [radar] [paso] 2>/dev/null   (STAIR=1 para el suelo escalonado)
 * Resultado en la revisión: 2,7–5,2 veces más rápido (canchoblanco 88 s -> 17 s); cruces idénticos (S2).
 */
require __DIR__.'/bootstrap.php'; require __DIR__.'/../prototype/reference.php';
$name = $argv[1] ?? 'paracuellos1'; $step = (int)($argv[2] ?? 1);
$r = loadRadar($name); $Rk=$r['screening']['radioTerrestreAumentado']; $hA=$r['screening']['towerHeight']+$r['screening']['terrainHeight'];
$t=microtime(true); $nfl=0; $exc=0;
for ($fl=1;$fl<=400;$fl+=$step) { $nfl++; ob_start(); try { calculosFL($r,(float)$fl,sprintf('%03d',$fl),false); } catch (Throwable $e) { $exc++; } ob_end_clean(); }
$tCur=microtime(true)-$t;
$t=microtime(true); $perfil=fx_perfil($r,(bool)getenv("STAIR")); $prep=fx_prep_segmentos($perfil); $tPrep=microtime(true)-$t;
$t=microtime(true); $diff=0;
for ($fl=1;$fl<=400;$fl+=$step) { $flm=$fl*100*0.3048;
  $c = fx_intersecciones_rapido($r,$perfil,$prep,$flm);
  if ($fl % 37 == 1) { $ref = fx_intersecciones(fx_obstaculos($r,$perfil,$flm),$flm+$Rk,$Rk); foreach ($ref as $az=>$l) { $a=$l; $b=$c[$az]; if (count($a)!=count($b)) $diff++; else foreach ($a as $i=>$v) if (abs($v-$b[$i])>1e-9) $diff++; } }
  fx_bandas($r, fx_intervalos(array_values($c), $flm>=$hA)); }
$tNew=microtime(true)-$t;
printf("%-13s %3d levels | current %7.2fs (%d exceptions) | fixed %6.2fs (+%.2fs one-off profile prep) | speedup x%.1f | fast-path vs reference crossings: %d differences\n",
  $name,$nfl,$tCur,$exc,$tNew,$tPrep,$tCur/($tNew+$tPrep),$diff);
