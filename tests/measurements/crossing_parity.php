<?php
/**
 * Paridad de cruces en FL1-400: por encima del radar cada acimut debe tener un número impar de cruces, por
 * debajo par. Compara el código actual (con W += 0.01) con los cruces robustos de la referencia.
 * Uso: php tests/measurements/crossing_parity.php [radar1,...] [pies_a_metros] 2>/dev/null
 * Resultado en la revisión: 0 errores en los dos, también con 0,3048 exacto (E5, E6).
 */
require __DIR__.'/bootstrap.php'; require __DIR__.'/../prototype/reference.php';
requires('create_matriz_intersecciones', 'the crossing code replaced by Change 6C');
// Parity sweep: above radar every azimuth must have an odd number of crossings, below radar an even number.
$feet = (float)($argv[2] ?? BERTA_FEET_TO_METERS);
foreach (explode(',', $argv[1] ?? 'paracuellos1,paracuellos2,alcolea,barajas,canchoblanco,erillas,monflorite,valdespina,valladolid') as $name) {
  $r = loadRadar($name); $Rk=$r['screening']['radioTerrestreAumentado'];
  $hA=$r['screening']['towerHeight']+$r['screening']['terrainHeight'];
  $perfil = fx_perfil($r, false);
  $badOld=0; $badNew=0; $flsOld=[]; $flsNew=[];
  for ($fl=1; $fl<=400; $fl++) {
    $flm=$fl*100*$feet; $above = $flm >= $hA;
    $mo=create_matriz_obstaculos($r,$flm); $mx=0;
    $old=create_matriz_intersecciones($r,$mo,$flm+$Rk+0.01,$mx);
    $new=fx_intersecciones(fx_obstaculos($r,$perfil,$flm), $flm+$Rk, $Rk);
    foreach ($old as $l) if ((count($l)%2==1) !== $above) { $badOld++; $flsOld[$fl]=1; }
    foreach ($new as $l) if ((count($l)%2==1) !== $above) { $badNew++; $flsNew[$fl]=1; }
  }
  printf("%-13s feet=%.14f  current code: %d bad azimuths at FL %s | fixed: %d bad azimuths %s\n", $name, $feet, $badOld, implode(',',array_keys($flsOld))?:'-', $badNew, implode(',',array_keys($flsNew))?:'');
}
