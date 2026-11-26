<?php
/**
 * Tiempo de calculosFL() en FL1-400 para cada radar, con el código que haya (antes o después de los arreglos).
 * Uso: php tests/measurements/sweep_calculosfl.php [radar1,...] 2>/dev/null
 * Resultado en la revisión: original paracuellos1 19,9 s, canchoblanco 88 s, alcolea 8,2 s; con los Cambios
 * 1-8 1,96 s, 5,1 s y 0,59 s.
 */
require __DIR__.'/bootstrap.php';
foreach (explode(',', $argv[1] ?? 'paracuellos1,canchoblanco,alcolea') as $name) { $r=loadRadar($name); $t=microtime(true); $exc=0;
  for ($fl=1;$fl<=400;$fl++) { ob_start(); try { calculosFL($r,(float)$fl,sprintf('%03d',$fl),false); } catch (Throwable $e) { $exc++; } ob_end_clean(); }
  printf("calculosFL %-13s FL1-400: %.2fs (%d exceptions)\n", $name, microtime(true)-$t, $exc); }
